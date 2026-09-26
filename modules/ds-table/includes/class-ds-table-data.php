<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Table: data layer (storage format, CSV parsing, sources, caching).
 *
 * Storage. The editor's table lives in the module's hidden `table_data` field as
 * "dst1:" + base64(JSON). Beaver Builder's save path json_decode()s every string
 * setting that parses as JSON into an object, which a text input can then no longer
 * hold (see modules/ds-menu/js/admin.js), and base64 also survives any text filter.
 * JSON shape: { "cols": [ { "label", "align", "nowrap", "hide" } ], "rows": [ [cell, …] ] }.
 *
 * Sources. `manual` renders table_data. `file` reads a CSV from the Media Library
 * on every render (cached by path + mtime + size, so a replaced file shows at once).
 * `url` fetches a CSV link or a Google Sheet (converted to its CSV export) on a
 * timer, keeps a stale copy for when the fetch fails, and never retries into a
 * failure more than once a minute.
 */
class DS_Table_Data {

	const PREFIX    = 'dst1:';
	const MAX_ROWS  = 5000;
	const MAX_COLS  = 50;
	const MAX_BYTES = 2097152; // 2 MB
	const MAX_CELL  = 2000;    // characters per cell
	/**
	 * Cells a table may hold in all (rows x columns). Every row is in the page's HTML
	 * (paging, search and sorting run in the browser), so 5000 rows x 50 columns would be
	 * a 30 MB page. 30,000 cells is 3000 rows x 10 columns, about 3 MB.
	 */
	const MAX_CELLS = 30000;
	/** Column types: text (''), image, link, button. */
	const TYPES     = array( '', 'image', 'link', 'button' );

	/* ------------------------------------------------------------ Storage */

	public static function empty_table() {
		return array( 'cols' => array(), 'rows' => array() );
	}

	/** Stored editor value -> normalised table. Accepts dst1:, raw JSON, or an already-decoded array/object. */
	public static function decode( $stored ) {
		if ( is_object( $stored ) ) { $stored = json_decode( wp_json_encode( $stored ), true ); }
		if ( is_array( $stored ) ) { return self::normalize( $stored ); }
		$stored = trim( (string) $stored );
		if ( '' === $stored ) { return self::empty_table(); }
		if ( 0 === strpos( $stored, self::PREFIX ) ) {
			$json = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
			$data = false !== $json ? json_decode( $json, true ) : null;
			return is_array( $data ) ? self::normalize( $data ) : self::empty_table();
		}
		$data = json_decode( $stored, true );
		return is_array( $data ) ? self::normalize( $data ) : self::empty_table();
	}

	public static function encode( $table ) {
		return self::PREFIX . base64_encode( wp_json_encode( self::normalize( $table ) ) );
	}

	/** Clamp sizes, coerce every cell to a string, pad ragged rows to the column count. */
	public static function normalize( $data ) {
		$cols = array();
		foreach ( array_slice( (array) ( $data['cols'] ?? array() ), 0, self::MAX_COLS ) as $c ) {
			$c      = is_array( $c ) ? $c : array( 'label' => (string) $c );
			$align  = (string) ( $c['align'] ?? '' );
			$type   = (string) ( $c['type'] ?? '' );
			$cols[] = array(
				'label'  => self::cell( $c['label'] ?? '' ),
				'align'  => in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : '',
				'nowrap' => ! empty( $c['nowrap'] ),
				'hide'   => ! empty( $c['hide'] ),
				'type'   => in_array( $type, self::TYPES, true ) ? $type : '',
			);
		}
		$rows = array();
		$max  = count( $cols );
		$src  = array_values( (array) ( $data['rows'] ?? array() ) );
		$wide = count( $cols );
		foreach ( array_slice( $src, 0, 50 ) as $r ) { $wide = max( $wide, min( self::MAX_COLS, count( (array) $r ) ) ); }
		foreach ( array_slice( $src, 0, self::row_limit( $wide ) ) as $r ) {
			$r = array_map( array( __CLASS__, 'cell' ), array_slice( array_values( (array) $r ), 0, self::MAX_COLS ) );
			$max = max( $max, count( $r ) );
			$rows[] = $r;
		}
		while ( count( $cols ) < $max ) { $cols[] = array( 'label' => '', 'align' => '', 'nowrap' => false, 'hide' => false, 'type' => '' ); }
		foreach ( $rows as &$r ) { while ( count( $r ) < count( $cols ) ) { $r[] = ''; } }
		unset( $r );
		return array( 'cols' => $cols, 'rows' => $rows );
	}

	/** Rows a table this wide may have: MAX_ROWS, or fewer so rows x columns stays within MAX_CELLS. */
	public static function row_limit( $cols ) {
		return (int) min( self::MAX_ROWS, max( 1, floor( self::MAX_CELLS / max( 1, (int) $cols ) ) ) );
	}

	public static function cell( $v ) {
		if ( is_array( $v ) || is_object( $v ) ) { return ''; }
		$v = str_replace( array( "\r\n", "\r" ), "\n", (string) $v );
		$v = wp_check_invalid_utf8( $v, true );
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, self::MAX_CELL ) : substr( $v, 0, self::MAX_CELL );
	}

	/* ---------------------------------------------------------------- CSV */

	/**
	 * Parse CSV text into rows of strings.
	 * Handles a UTF-8 BOM, Windows-1252 exports (Excel), comma / semicolon / tab
	 * separators (detected), quoted cells with commas and line breaks, and strips
	 * trailing empty rows and columns. Returns rows, warnings and the separator used.
	 */
	public static function parse_csv( $raw ) {
		$out = array( 'rows' => array(), 'warnings' => array(), 'delimiter' => ',' );
		$raw = (string) $raw;
		if ( strlen( $raw ) > self::MAX_BYTES ) {
			$out['warnings'][] = sprintf( 'The file is larger than %d MB; only the first part was read.', (int) ( self::MAX_BYTES / 1048576 ) );
			// Cut at the last line break: never mid-row, and never inside a multi-byte
			// character (which would fail the UTF-8 check below and garble every accent).
			$raw = substr( $raw, 0, self::MAX_BYTES );
			$nl  = strrpos( $raw, "\n" );
			if ( false !== $nl ) { $raw = substr( $raw, 0, $nl + 1 ); }
		}
		if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) { $raw = substr( $raw, 3 ); }
		// Excel on Windows saves "CSV" as Windows-1252, not UTF-8.
		$utf8 = function_exists( 'mb_check_encoding' ) ? mb_check_encoding( $raw, 'UTF-8' ) : (bool) preg_match( '//u', $raw );
		if ( ! $utf8 ) {
			if ( function_exists( 'mb_convert_encoding' ) ) {
				$raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252' );
			} elseif ( function_exists( 'iconv' ) ) {
				$conv = @iconv( 'CP1252', 'UTF-8//IGNORE', $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( false !== $conv ) { $raw = $conv; }
			}
		}
		if ( '' === trim( $raw ) ) { return $out; }

		$delim = self::detect_delimiter( $raw );
		$out['delimiter'] = $delim;

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $raw );
		rewind( $fh );
		$rows = array();
		$cut  = false;
		$limit = self::MAX_ROWS + 1; // the heading row plus MAX_ROWS rows
		while ( false !== ( $r = fgetcsv( $fh, 0, $delim, '"', '' ) ) ) {
			if ( null === $r ) { continue; }
			if ( count( $rows ) >= $limit ) { $cut = true; break; }
			if ( count( $r ) > self::MAX_COLS ) { $r = array_slice( $r, 0, self::MAX_COLS ); $out['cols_cut'] = true; }
			$rows[] = array_map( function ( $c ) { return trim( self::cell( $c ) ); }, $r );
		}
		fclose( $fh );
		if ( $cut ) { $out['warnings'][] = sprintf( 'Only the first %d rows were imported.', self::MAX_ROWS ); }
		// Rows x columns within MAX_CELLS (the page carries every row).
		$wide = 0;
		foreach ( $rows as $r ) { $wide = max( $wide, count( $r ) ); }
		$fit = self::row_limit( $wide ) + 1;
		if ( ! $cut && count( $rows ) > $fit ) {
			$rows  = array_slice( $rows, 0, $fit );
			$out['warnings'][] = sprintf( 'A table can hold %1$s cells, so only the first %2$d rows of this %3$d-column file were imported.', number_format( self::MAX_CELLS ), $fit - 1, $wide );
		}
		if ( ! empty( $out['cols_cut'] ) ) { $out['warnings'][] = sprintf( 'Only the first %d columns were imported.', self::MAX_COLS ); unset( $out['cols_cut'] ); }

		// A row of nothing but empty cells (fgetcsv returns [null] for a blank line).
		$rows = array_values( array_filter( $rows, function ( $r ) { return '' !== implode( '', $r ); } ) );
		// Trailing columns that are empty in every row (spreadsheets export these).
		$width = 0;
		foreach ( $rows as $r ) {
			for ( $i = count( $r ) - 1; $i >= 0; $i-- ) { if ( '' !== $r[ $i ] ) { $width = max( $width, $i + 1 ); break; } }
		}
		foreach ( $rows as &$r ) { $r = array_pad( array_slice( $r, 0, $width ), $width, '' ); }
		unset( $r );
		$out['rows'] = $rows;
		return $out;
	}

	/** The separator that splits the first lines most consistently (quotes respected). */
	public static function detect_delimiter( $raw ) {
		$sample = substr( $raw, 0, 8192 );
		$best   = ','; $score = -1;
		foreach ( array( ',', ';', "\t", '|' ) as $d ) {
			$counts = array();
			$in     = false; $n = 0; $lines = 0;
			$len    = strlen( $sample );
			for ( $i = 0; $i < $len && $lines < 8; $i++ ) {
				$ch = $sample[ $i ];
				if ( '"' === $ch ) { $in = ! $in; continue; }
				if ( $in ) { continue; }
				if ( $ch === $d ) { $n++; }
				elseif ( "\n" === $ch ) { $counts[] = $n; $n = 0; $lines++; }
			}
			if ( $n || ! $counts ) { $counts[] = $n; }
			$first = $counts[0] ?? 0;
			if ( ! $first ) { continue; }
			$same  = count( array_filter( $counts, function ( $c ) use ( $first ) { return $c === $first; } ) );
			$s     = $first * 10 + $same * 100;
			if ( $s > $score ) { $score = $s; $best = $d; }
		}
		return $best;
	}

	/** CSV rows -> table. With a header row, the first row becomes the column labels. */
	public static function from_rows( array $rows, $has_header = true ) {
		$cols = array();
		if ( $has_header && $rows ) {
			foreach ( array_shift( $rows ) as $label ) { $cols[] = array( 'label' => $label ); }
		}
		return self::normalize( array( 'cols' => $cols, 'rows' => $rows ) );
	}

	/** Table -> CSV text (RFC 4180 quoting), for the editor's Export. */
	public static function to_csv( array $table, $with_header = true ) {
		$fh = fopen( 'php://temp', 'r+' );
		if ( $with_header ) { fputcsv( $fh, wp_list_pluck( $table['cols'], 'label' ), ',', '"', '' ); }
		foreach ( $table['rows'] as $r ) { fputcsv( $fh, $r, ',', '"', '' ); }
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return $csv;
	}

	/* ------------------------------------------------------------ Sources */

	/** Is this attachment a CSV we may read? */
	public static function is_csv_attachment( $id ) {
		$id = (int) $id;
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) { return false; }
		$path = get_attached_file( $id );
		if ( ! $path || ! is_readable( $path ) ) { return false; }
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return in_array( $ext, array( 'csv', 'tsv', 'txt' ), true );
	}

	/** A file's first MAX_BYTES + 1 bytes (enough for parse_csv to see it is too big). */
	public static function read_file( $path ) {
		$raw = @file_get_contents( $path, false, null, 0, self::MAX_BYTES + 1 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return false === $raw ? '' : (string) $raw;
	}

	/** Rows of an uploaded CSV, cached by path + mtime + size so a replaced file shows immediately. */
	public static function file_rows( $id ) {
		if ( ! self::is_csv_attachment( $id ) ) {
			return array( 'rows' => array(), 'error' => 'The CSV file is missing from the Media Library.' );
		}
		$path = get_attached_file( (int) $id );
		clearstatcache( true, $path );
		$key  = 'ds_table_f_' . md5( $path . '|' . filemtime( $path ) . '|' . filesize( $path ) );
		$hit  = self::unpack( get_transient( $key ) );
		if ( is_array( $hit ) ) { return $hit; }
		$parsed = self::parse_csv( self::read_file( $path ) );
		$res    = array( 'rows' => $parsed['rows'], 'warnings' => $parsed['warnings'], 'error' => '', 'name' => wp_basename( $path ), 'modified' => filemtime( $path ) );
		set_transient( $key, self::pack( $res ), 12 * HOUR_IN_SECONDS );
		return $res;
	}

	/**
	 * A Google Sheets link as its CSV export. Accepts the normal share link
	 * (…/spreadsheets/d/<id>/edit#gid=<tab>), a published link (…/pub?…) and any
	 * other URL unchanged.
	 */
	public static function csv_url( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '#^https://docs\.google\.com/spreadsheets/(?:u/\d+/)?d/(e/)?([A-Za-z0-9_-]+)#', $url, $m ) ) {
			$gid = preg_match( '/[#&?]gid=(\d+)/', $url, $g ) ? $g[1] : '0';
			if ( 'e/' === $m[1] ) { // published to the web
				return 'https://docs.google.com/spreadsheets/d/e/' . $m[2] . '/pub?gid=' . $gid . '&single=true&output=csv';
			}
			return 'https://docs.google.com/spreadsheets/d/' . $m[2] . '/export?format=csv&gid=' . $gid;
		}
		return $url;
	}

	/**
	 * Rows from a CSV link. A fresh copy is used while it is younger than $ttl (each table's
	 * own "check every"), one fetch runs at a time, and a failure is retried at most once a
	 * minute. The last copy that loaded is kept with no expiry, so a failed or locked fetch
	 * never renders (and page-caches) an empty table once the link has loaded once.
	 */
	public static function url_rows( $url, $ttl = 900, $force = false ) {
		$src = self::csv_url( $url );
		if ( ! wp_http_validate_url( $src ) ) {
			return array( 'rows' => array(), 'error' => 'Enter a full link starting with https://.' );
		}
		$ttl   = max( 60, (int) $ttl );
		$h     = md5( $src );
		$fresh = 'ds_table_u_' . $h;
		$fail  = 'ds_table_ux_' . $h;
		$lock  = 'ds_table_ul_' . $h;

		if ( ! $force ) {
			$hit = self::unpack( get_transient( $fresh ) );
			if ( is_array( $hit ) && time() - (int) ( $hit['fetched'] ?? 0 ) < $ttl ) { return $hit; }
		}
		$old = self::last_good( $src );
		if ( ! $force && ( get_transient( $fail ) || get_transient( $lock ) ) ) {
			return $old ? $old + array( 'stale' => true ) : array( 'rows' => array(), 'error' => 'The link could not be read just now. It will be retried shortly.' );
		}
		set_transient( $lock, 1, 30 );
		// One byte over the limit, so parse_csv can tell a cut-off download from a file of exactly 2 MB.
		$res = wp_safe_remote_get( $src, array( 'timeout' => 8, 'redirection' => 5, 'limit_response_size' => self::MAX_BYTES + 1, 'user-agent' => 'DS Toolkit Table; ' . home_url( '/' ) ) );
		delete_transient( $lock );

		$err  = '';
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$body = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		$type = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_header( $res, 'content-type' );
		if ( is_wp_error( $res ) ) {
			$err = 'The link could not be reached (' . $res->get_error_message() . ').';
		} elseif ( $code >= 400 ) {
			$err = 'The link returned an error (HTTP ' . $code . ').';
		} elseif ( false !== stripos( $type, 'text/html' ) || preg_match( '/^\s*<(!doctype|html)/i', $body ) ) {
			$err = false !== strpos( $src, 'docs.google.com' )
				? 'Google returned a sign-in page, so the sheet is private. In the sheet: Share > General access > Anyone with the link can view (or File > Share > Publish to web > CSV).'
				: 'The link returned a web page, not a CSV file.';
		}
		if ( $err ) {
			set_transient( $fail, 1, MINUTE_IN_SECONDS );
			return $old ? array_merge( $old, array( 'stale' => true, 'error' => $err ) ) : array( 'rows' => array(), 'error' => $err );
		}
		$parsed = self::parse_csv( $body );
		$out    = array( 'rows' => $parsed['rows'], 'warnings' => $parsed['warnings'], 'error' => '', 'fetched' => time(), 'source' => $src, 'hash' => md5( $body ) );
		// The fresh copy lives as long as the slowest table could want it; freshness is checked above.
		set_transient( $fresh, self::pack( $out ), DAY_IN_SECONDS );
		update_option( 'ds_table_last_' . $h, self::pack( $out ), false );
		return $out;
	}

	/** The last copy of a link that loaded (no expiry), or null. */
	public static function last_good( $src ) {
		$got = self::unpack( get_option( 'ds_table_last_' . md5( $src ) ) );
		return is_array( $got ) ? $got : null;
	}

	/**
	 * Cached rows are stored compressed: a 2 MB sheet is about 3.6 MB serialized, over the
	 * 1 MB item limit of a memcached object cache, where it would silently never persist
	 * and every page view would fetch the sheet again.
	 */
	public static function pack( array $data ) {
		$json = wp_json_encode( $data );
		if ( function_exists( 'gzcompress' ) ) {
			$z = gzcompress( $json, 6 );
			if ( false !== $z ) { return 'z:' . base64_encode( $z ); }
		}
		return 'j:' . $json;
	}

	public static function unpack( $stored ) {
		if ( is_array( $stored ) ) { return $stored; } // written before packing existed
		if ( ! is_string( $stored ) || strlen( $stored ) < 3 ) { return null; }
		$body = substr( $stored, 2 );
		if ( 0 === strpos( $stored, 'z:' ) ) {
			$z    = base64_decode( $body, true );
			$body = ( false !== $z && function_exists( 'gzuncompress' ) ) ? @gzuncompress( $z ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		} elseif ( 0 !== strpos( $stored, 'j:' ) ) {
			return null;
		}
		$data = is_string( $body ) ? json_decode( $body, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	/* ---------------------------------------------------------- Page cache */

	/**
	 * Clear the page cache of a post whose table data changed, so visitors see it without
	 * waiting for the host's cache to expire. WP Engine: WpeCommon purges the post's URLs.
	 * Other hosts and cache plugins can hook ds_table_purge_post.
	 */
	public static function purge_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) { return; }
		clean_post_cache( $post_id );
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache( $post_id );
		}
		do_action( 'ds_table_purge_post', $post_id );
	}

	/**
	 * The table a module shows: cells from its source, column options (alignment,
	 * wrapping, hide on phones, type) always from the editor so they work for every source.
	 */
	public static function resolve( $settings ) {
		$editor = self::decode( $settings->table_data ?? '' );
		$source = (string) ( $settings->source ?? 'manual' );
		$meta   = array( 'source' => $source, 'error' => '', 'warnings' => array() );
		if ( 'file' === $source || 'url' === $source ) {
			$got = 'file' === $source
				? self::file_rows( (int) ( $settings->csv_id ?? 0 ) )
				: self::url_rows( (string) ( $settings->csv_url ?? '' ), self::ttl( $settings ) );
			$meta  = array_merge( $meta, array_intersect_key( $got, array_flip( array( 'error', 'warnings', 'name', 'modified', 'fetched', 'stale' ) ) ) );
			$table = self::from_rows( $got['rows'] ?? array(), 'no' !== ( $settings->csv_header ?? 'yes' ) );
			foreach ( $table['cols'] as $i => &$c ) {
				if ( isset( $editor['cols'][ $i ] ) ) {
					$e = $editor['cols'][ $i ];
					$c['align'] = $e['align']; $c['nowrap'] = $e['nowrap']; $c['hide'] = $e['hide']; $c['type'] = $e['type'];
				}
			}
			unset( $c );
		} else {
			$table = $editor;
		}
		$table['meta'] = $meta;
		return $table;
	}

	public static function ttl( $settings ) {
		$m = (int) ( $settings->sync_every ?? 15 );
		return in_array( $m, array( 5, 15, 60, 360, 1440 ), true ) ? $m * MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS;
	}

	/* ------------------------------------------------------------ Sorting */

	/**
	 * How a column sorts: 'num' (prices, counts), 'time' (6:00 PM, 9-10:30 AM),
	 * 'date' (Jan 17, 2026 / 2026-01-17 / 1/17/2026), else 'text'.
	 */
	public static function column_type( array $values ) {
		$vals = array_values( array_filter( array_map( 'trim', $values ), 'strlen' ) );
		if ( ! $vals ) { return 'text'; }
		$all = function ( $test ) use ( $vals ) { foreach ( $vals as $v ) { if ( ! $test( $v ) ) { return false; } } return true; };
		if ( $all( function ( $v ) { return (bool) preg_match( '/^[\s$€£¥]*[-+]?\d[\d,]*(\.\d+)?\s*%?$/u', $v ); } ) ) { return 'num'; }
		if ( $all( function ( $v ) { return null !== self::time_minutes( $v ) && ! preg_match( '/\d{4}|[a-z]{3,}\s+\d{1,2}\b.*\d/i', preg_replace( '/\b(am|pm|noon|midnight)\b/i', '', $v ) ); } ) ) { return 'time'; }
		if ( $all( function ( $v ) { return null !== self::date_stamp( $v ); } ) ) { return 'date'; }
		return 'text';
	}

	public static function sort_key( $v, $type ) {
		$v = trim( (string) $v );
		if ( '' === $v ) { return ''; }
		switch ( $type ) {
			case 'num':  return (string) (float) preg_replace( '/[^\d.\-]/', '', $v );
			case 'time': return (string) self::time_minutes( $v );
			case 'date': return (string) self::date_stamp( $v );
		}
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $v ) : strtolower( $v );
	}

	/** Minutes after midnight of the first clock time in a cell ("9:00 AM – 10:30 AM" -> 540). */
	public static function time_minutes( $v ) {
		if ( preg_match( '/\b(noon)\b/i', $v ) ) { return 720; }
		if ( ! preg_match( '/\b(\d{1,2})(?::(\d{2}))?\s*(a\.?m\.?|p\.?m\.?)/i', $v, $m ) && ! preg_match( '/\b(\d{1,2}):(\d{2})\b()/', $v, $m ) ) { return null; }
		$h = (int) $m[1]; $min = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
		$ap = strtolower( str_replace( '.', '', $m[3] ?? '' ) );
		if ( $h > 23 || $min > 59 ) { return null; }
		if ( 'pm' === $ap && $h < 12 ) { $h += 12; }
		if ( 'am' === $ap && 12 === $h ) { $h = 0; }
		return $h * 60 + $min;
	}

	/** Timestamp of the first date in a cell ("Sat, Jan 17, 2026", "2026-01-17", "1/17/2026"; ranges use their start). */
	public static function date_stamp( $v ) {
		$v = preg_split( '/\s+(?:–|—|-|to)\s+/u', trim( (string) $v ) )[0];
		if ( ! preg_match( '/\d/', $v ) || preg_match( '/^\d+(\.\d+)?$/', $v ) ) { return null; }
		$t = strtotime( $v );
		return false === $t ? null : $t;
	}

	/* -------------------------------------------------------------- Cells */

	/**
	 * Escaped cell HTML: line breaks kept, [label](address) becomes a link, and (when $link)
	 * bare web addresses and emails become links. No other markup gets through.
	 */
	public static function cell_html( $v, $link = true ) {
		$v     = str_replace( "\x1A", '', (string) $v );
		$slots = array();
		$v     = preg_replace_callback( '/\[([^\]\n]{1,200})\]\(\s*([^()\s]{1,500})\s*\)/u', function ( $m ) use ( &$slots ) {
			$url = self::safe_url( $m[2] );
			if ( '' === $url ) { return $m[0]; }
			$key           = "\x1A" . count( $slots ) . "\x1A";
			$slots[ $key ] = self::anchor( $url, esc_html( $m[1] ) );
			return $key;
		}, $v );
		$html = esc_html( $v );
		if ( $link ) {
			// Web addresses go into slots too, so the email pass below cannot link an
			// address inside one (https://x.org/?ref=coach@club.org).
			$html = preg_replace_callback( '#\bhttps?://[^\s<>"\'\x1A]+[^\s<>"\'.,;:!?)\]\x1A]#i', function ( $m ) use ( &$slots ) {
				$key           = "\x1A" . count( $slots ) . "\x1A";
				$slots[ $key ] = self::anchor( html_entity_decode( $m[0] ), $m[0] );
				return $key;
			}, $html );
			$html = preg_replace_callback( '/(?<![\w.@\/-])[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', function ( $m ) {
				return '<a class="ds-table-link" href="mailto:' . esc_attr( $m[0] ) . '">' . $m[0] . '</a>';
			}, $html );
		}
		return nl2br( strtr( $html, $slots ), false );
	}

	/** A link; other sites open in a new tab. $inner is already-escaped HTML. */
	public static function anchor( $url, $inner, $class = 'ds-table-link' ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$ext  = $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host;
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"' . ( $ext ? ' target="_blank" rel="noopener"' : '' ) . '>' . $inner . '</a>';
	}

	/**
	 * A cell address made safe: http(s), mailto:, tel:, a site path ("/register/") or a bare
	 * domain ("example.org/x", given https). Anything else (javascript:, data:) returns ''.
	 */
	public static function safe_url( $url ) {
		$url = trim( html_entity_decode( (string) $url ) );
		if ( '' === $url ) { return ''; }
		if ( preg_match( '#^/(?!/)#', $url ) ) {
			$url = home_url( $url );
		} elseif ( ! preg_match( '#^(https?://|mailto:|tel:)#i', $url ) ) {
			if ( ! preg_match( '#^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}(/\S*)?$#i', $url ) ) { return ''; }
			$url = 'https://' . $url;
		}
		return (string) esc_url( $url, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * A link or image cell split into its two parts:
	 * "Label | address", "[Label](address)", or a lone address (label ''). Text with no
	 * address comes back as the label with an empty address.
	 */
	public static function split_link( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^\[([^\]]*)\]\(\s*([^()\s]+)\s*\)$/u', $v, $m ) ) { return array( trim( $m[1] ), $m[2] ); }
		if ( false !== strpos( $v, '|' ) ) {
			$p = explode( '|', $v, 2 );
			return array( trim( $p[0] ), trim( $p[1] ) );
		}
		if ( preg_match( '#^(https?://|mailto:|tel:|/)\S+$#i', $v ) ) { return array( '', $v ); }
		return array( $v, '' );
	}

	/** Link or button cell. A lone address takes the column heading (or "View") as its label. */
	public static function link_html( $v, $type, $fallback = '' ) {
		list( $label, $url ) = self::split_link( $v );
		$url = self::safe_url( $url );
		if ( '' === $url ) { return self::cell_html( $v ); }
		if ( '' === $label ) { $label = '' !== trim( (string) $fallback ) ? trim( (string) $fallback ) : __( 'View', 'ds-toolkit' ); }
		if ( 'button' === $type ) {
			return self::anchor( $url, '<span class="fl-button-text">' . esc_html( $label ) . '</span>', 'fl-button ds-table-btn' );
		}
		return self::anchor( $url, esc_html( $label ) );
	}

	/**
	 * Where an image cell's picture comes from: a Media Library ID, or a web address
	 * (a Google Drive share link becomes its public thumbnail URL).
	 */
	public static function image_source( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^\d+$/', $v ) ) {
			return wp_attachment_is_image( (int) $v ) ? array( 'id' => (int) $v, 'url' => '' ) : array( 'id' => 0, 'url' => '' );
		}
		if ( preg_match( '#drive\.google\.com/(?:file/d/|open\?id=|uc\?(?:export=\w+&(?:amp;)?)?id=)([\w-]{10,})#', $v, $m ) ) {
			return array( 'id' => 0, 'url' => 'https://drive.google.com/thumbnail?id=' . $m[1] . '&sz=w800' );
		}
		$url = self::safe_url( $v );
		return array( 'id' => 0, 'url' => preg_match( '#^https?://#i', $url ) ? $url : '' );
	}

	/**
	 * Image cell: "image", or "image | link" to make it clickable. $alt is used when the
	 * Media Library has none; $px sizes the width/height attributes (no layout jump).
	 */
	public static function image_html( $v, $alt = '', $px = 56 ) {
		list( $img, $link ) = self::split_link( $v );
		if ( '' === $img && '' !== $link ) { $img = $link; $link = ''; } // a lone address is the image
		$src = self::image_source( $img );
		if ( ! $src['id'] && ! $src['url'] && '' !== $link ) {
			// "Label | image": the second part is the picture, the first its alt text.
			$alt2 = self::image_source( $link );
			if ( $alt2['id'] || $alt2['url'] ) { $src = $alt2; $alt = $img; $link = ''; }
		}
		$px  = max( 16, min( 600, (int) $px ) );
		$out = '';
		if ( $src['id'] ) {
			$att_alt = trim( (string) get_post_meta( $src['id'], '_wp_attachment_image_alt', true ) );
			$out     = wp_get_attachment_image( $src['id'], $px > 150 ? 'medium' : 'thumbnail', false, array(
				'class'    => 'ds-table-img',
				'alt'      => '' !== $att_alt ? $att_alt : $alt,
				'loading'  => 'lazy',
				'decoding' => 'async',
			) );
		} elseif ( $src['url'] ) {
			$out = '<img class="ds-table-img" src="' . esc_url( $src['url'] ) . '" alt="' . esc_attr( $alt ) . '" width="' . $px . '" height="' . $px . '" loading="lazy" decoding="async">';
		}
		if ( '' === $out ) { return ''; }
		$link = self::safe_url( $link );
		return '' !== $link ? self::anchor( $link, $out, 'ds-table-imglink' ) : $out;
	}

	/** The words a cell shows (for search, sorting and alt text): labels, not addresses. */
	public static function display_text( $v, $type = '' ) {
		if ( 'image' === $type ) { return ''; }
		if ( 'link' === $type || 'button' === $type ) {
			list( $label, $url ) = self::split_link( $v );
			return '' !== $label ? $label : '';
		}
		return (string) preg_replace( '/\[([^\]\n]{1,200})\]\(\s*[^()\s]{1,500}\s*\)/u', '$1', (string) $v );
	}
}
