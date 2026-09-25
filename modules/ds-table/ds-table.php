<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/includes/class-ds-table-data.php';

/**
 * LeagueApps Table: a data table built in the Beaver Builder panel.
 *
 * Three sources: rows typed into the in-panel spreadsheet editor (js/editor.js),
 * an uploaded CSV file kept in sync (re-read whenever the file changes; "Upload new
 * version" replaces it in place), or a CSV / Google Sheet link fetched on a timer.
 * CSV uploads, parsing and link fetches all run over admin-ajax, so the builder
 * never reloads. Visitors get sortable columns (numbers, dates and times sort as
 * such), optional search, pagination, and a stacked-card view on phones.
 */
class DS_Table_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Table', 'ds-toolkit' ),
			'description'     => __( 'A sortable, searchable table typed in the builder, uploaded as a CSV, or synced from a Google Sheet.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-table/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-table/',
			'partial_refresh' => true,
		) );
	}

	/* ------------------------------------------------------------ Presets */

	/** Palette-neutral base layers; brand = the site's Accent header. A field the editor sets always wins. */
	public static function presets() {
		return array(
			'striped'  => array( 'table_border' => 'horizontal', 'table_border_color' => 'rgba(0,0,0,.09)', 'row_stripe' => 'rgba(0,0,0,.035)', 'head_border_width' => '2', 'head_border_color' => 'rgba(0,0,0,.22)' ),
			'bordered' => array( 'table_border' => 'grid', 'table_border_color' => 'rgba(0,0,0,.14)', 'head_bg' => 'rgba(0,0,0,.04)' ),
			'minimal'  => array( 'table_border' => 'horizontal', 'table_border_color' => 'rgba(0,0,0,.08)', 'head_border_width' => '2', 'head_border_color' => 'rgba(0,0,0,.55)', 'cell_pad' => '14' ),
			'framed'   => array( 'table_border' => 'outer_horizontal', 'table_border_color' => 'rgba(0,0,0,.10)', 'table_radius' => '12', 'table_shadow' => 'soft', 'row_stripe' => 'rgba(0,0,0,.025)', 'cell_pad' => '14' ),
			'brand'    => array( 'table_border' => 'horizontal', 'table_border_color' => 'rgba(0,0,0,.08)', 'row_stripe' => 'rgba(0,0,0,.035)', 'head_bg' => 'var(--fl-global-accent, #1d2327)', 'head_color' => '#ffffff', 'table_radius' => '8', 'cell_pad' => '14' ),
		);
	}

	/** A style value: the editor's, else the preset's, else $default. */
	public function style( $key, $default = '' ) {
		$v = $this->settings->{$key} ?? '';
		if ( '' !== $v && null !== $v ) { return $v; }
		$p = (string) ( $this->settings->style_preset ?? 'striped' );
		$presets = self::presets();
		return isset( $presets[ $p ][ $key ] ) ? $presets[ $p ][ $key ] : $default;
	}

	/* -------------------------------------------------------------- Fonts */

	/**
	 * The heading row, a bold first column, the phone cards' titles and labels and the
	 * current page number are set in weight 700 of the font each part uses. Beaver Builder
	 * only requests the weights its settings name (a global text font usually loads 400
	 * alone), and without the bold face the browser fakes it by thickening the regular one.
	 */
	public function enqueue_scripts() {
		if ( ! class_exists( 'FLBuilderFonts' ) || ! class_exists( 'FLBuilderFontFamilies' ) ) { return; }
		$text = '';
		if ( class_exists( 'FLBuilderGlobalStyles' ) ) {
			$g    = FLBuilderGlobalStyles::get_settings( false );
			$tt   = is_object( $g ) ? (array) ( $g->text_typography ?? array() ) : array();
			$text = (string) ( $tt['font_family'] ?? '' );
		}
		$families = array();
		foreach ( array( 'head_typo', 'row_typo' ) as $key ) {
			$t = (array) ( $this->settings->{$key} ?? array() );
			$f = (string) ( $t['font_family'] ?? '' );
			$families[] = ( '' !== $f && 'Default' !== $f ) ? $f : $text;
		}
		$google = FLBuilderFontFamilies::google();
		foreach ( array_unique( array_filter( $families ) ) as $f ) {
			if ( 'Default' === $f || ( isset( $google[ $f ] ) && ! in_array( '700', (array) $google[ $f ], true ) ) ) { continue; }
			FLBuilderFonts::add_font( array( 'family' => $f, 'weight' => '700' ) );
		}
	}

	/* ------------------------------------------------------------- Render */

	private $table = null;

	public function table() {
		if ( null === $this->table ) { $this->table = DS_Table_Data::resolve( $this->settings ); }
		return $this->table;
	}

	public function render_table() {
		$s     = $this->settings;
		$t     = $this->table();
		$cols  = $t['cols'];
		$rows  = $t['rows'];
		$meta  = $t['meta'];
		$edit  = class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active();
		$link  = 'no' !== ( $s->link_urls ?? 'yes' );
		$head  = 'no' !== ( $s->show_header ?? 'yes' ) && '' !== implode( '', wp_list_pluck( $cols, 'label' ) );
		$first = (string) ( $s->first_col ?? 'normal' );
		$sort  = 'no' !== ( $s->sortable ?? 'yes' ) && $head && count( $rows ) > 1;
		$cards = 'scroll' !== ( $s->mobile_mode ?? 'cards' );

		if ( $edit && $meta['error'] ) {
			echo '<p class="ds-table-note">' . esc_html( $meta['error'] ) . ( ! empty( $meta['stale'] ) ? ' ' . esc_html__( 'Showing the last copy that loaded.', 'ds-toolkit' ) : '' ) . '</p>';
		}
		if ( ! $rows ) {
			if ( $edit ) {
				$msg = 'manual' === $meta['source'] ? __( 'This table is empty. Type rows in the table editor, paste them from a spreadsheet, or import a CSV.', 'ds-toolkit' ) : __( 'No rows yet: choose a CSV file or a link in the module settings.', 'ds-toolkit' );
				echo '<p class="ds-table-note">' . esc_html( $msg ) . '</p>';
			}
			return;
		}

		$types = array();
		foreach ( $cols as $i => $c ) { $types[ $i ] = DS_Table_Data::column_type( array_column( $rows, $i ) ); }

		$search = 'yes' === ( $s->search ?? 'no' );
		$count  = 'yes' === ( $s->show_count ?? 'no' );
		$psize  = max( 0, (int) ( $s->page_size ?? 0 ) );
		$one    = trim( (string) ( $s->count_singular ?? '' ) ) ?: __( 'row', 'ds-toolkit' );
		$many   = trim( (string) ( $s->count_plural ?? '' ) ) ?: __( 'rows', 'ds-toolkit' );
		$uid    = 'ds-table-' . $this->node;

		printf(
			'<div class="ds-table%s" data-ds-table data-page-size="%d" data-one="%s" data-many="%s" data-prev="%s" data-next="%s">',
			$cards ? ' ds-table--cards' : ' ds-table--scroll',
			$psize,
			esc_attr( $one ), esc_attr( $many ),
			esc_attr( trim( (string) ( $s->prev_text ?? '' ) ) ?: __( 'Previous', 'ds-toolkit' ) ),
			esc_attr( trim( (string) ( $s->next_text ?? '' ) ) ?: __( 'Next', 'ds-toolkit' ) )
		);

		if ( $search || $count || $sort ) {
			echo '<div class="ds-table-bar">';
			if ( $search ) {
				$ph = trim( (string) ( $s->search_placeholder ?? '' ) ) ?: __( 'Search this table', 'ds-toolkit' );
				echo '<div class="ds-table-field ds-table-field--search"><label class="screen-reader-text" for="' . esc_attr( $uid ) . '-q">' . esc_html__( 'Search the table', 'ds-toolkit' ) . '</label>';
				echo '<input type="search" id="' . esc_attr( $uid ) . '-q" class="ds-table-input" data-ds-table-search placeholder="' . esc_attr( $ph ) . '" autocomplete="off"></div>';
			}
			if ( $sort && $cards ) {
				// Phones hide the header row in card view, so sorting moves into a dropdown.
				echo '<div class="ds-table-field ds-table-field--sort"><label class="screen-reader-text" for="' . esc_attr( $uid ) . '-s">' . esc_html__( 'Sort by', 'ds-toolkit' ) . '</label><select id="' . esc_attr( $uid ) . '-s" class="ds-table-select" data-ds-table-sortsel>';
				echo '<option value="">' . esc_html__( 'Sort: as listed', 'ds-toolkit' ) . '</option>';
				foreach ( $cols as $i => $c ) {
					if ( '' === $c['label'] ) { continue; }
					/* translators: %s: column name */
					echo '<option value="' . (int) $i . ':asc">' . esc_html( sprintf( __( '%s, first to last', 'ds-toolkit' ), $c['label'] ) ) . '</option>';
					/* translators: %s: column name */
					echo '<option value="' . (int) $i . ':desc">' . esc_html( sprintf( __( '%s, last to first', 'ds-toolkit' ), $c['label'] ) ) . '</option>';
				}
				echo '</select></div>';
			}
			if ( $count ) {
				echo '<p class="ds-table-count" data-ds-table-count aria-live="polite">' . esc_html( count( $rows ) . ' ' . ( 1 === count( $rows ) ? $one : $many ) ) . '</p>';
			}
			echo '</div>';
		}

		echo '<div class="ds-table-scroll" tabindex="0" role="region" aria-label="' . esc_attr( trim( (string) ( $s->caption ?? '' ) ) ?: __( 'Table', 'ds-toolkit' ) ) . '">';
		echo '<table class="ds-table-t">';
		$caption = trim( (string) ( $s->caption ?? '' ) );
		if ( $caption ) { echo '<caption class="screen-reader-text">' . esc_html( $caption ) . '</caption>'; }

		if ( $head ) {
			echo '<thead><tr>';
			foreach ( $cols as $i => $c ) {
				$cls = 'ds-table-th ds-table-c' . $i . ( $c['hide'] ? ' ds-table-hide-sm' : '' );
				echo '<th scope="col" class="' . esc_attr( $cls ) . '"' . ( $sort ? ' aria-sort="none"' : '' ) . '>';
				if ( $sort && '' !== $c['label'] ) {
					echo '<button type="button" class="ds-table-sortbtn" data-sort="' . (int) $i . '" data-type="' . esc_attr( 'text' === $types[ $i ] ? 'text' : 'num' ) . '">' . esc_html( $c['label'] ) . '<span class="ds-table-sorticon" aria-hidden="true"></span></button>';
				} else {
					echo esc_html( $c['label'] );
				}
				echo '</th>';
			}
			echo '</tr></thead>';
		}

		echo '<tbody>';
		foreach ( $rows as $ri => $r ) {
			$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( implode( ' ', $r ) ) : strtolower( implode( ' ', $r ) );
			echo '<tr class="ds-table-row' . ( $ri % 2 ? ' is-alt' : '' ) . '" data-i="' . (int) $ri . '" data-search="' . esc_attr( $hay ) . '"';
			if ( $sort ) { foreach ( $cols as $i => $c ) { echo ' data-s' . (int) $i . '="' . esc_attr( DS_Table_Data::sort_key( $r[ $i ], $types[ $i ] ) ) . '"'; } }
			echo '>';
			foreach ( $cols as $i => $c ) {
				$is_first = 0 === $i;
				$tag      = ( $is_first && 'rowhead' === $first ) ? 'th' : 'td';
				$cls      = 'ds-table-td ds-table-c' . $i . ( $c['hide'] ? ' ds-table-hide-sm' : '' ) . ( $is_first ? ' ds-table-first' : '' ) . ( '' === trim( $r[ $i ] ) ? ' is-empty' : '' );
				echo '<' . $tag . ( 'th' === $tag ? ' scope="row"' : '' ) . ' class="' . esc_attr( $cls ) . '" data-label="' . esc_attr( $c['label'] ) . '">' . DS_Table_Data::cell_html( $r[ $i ], $link ) . '</' . $tag . '>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		if ( $search ) {
			echo '<p class="ds-table-none" data-ds-table-none hidden>' . esc_html( trim( (string) ( $s->none_text ?? '' ) ) ?: __( 'No rows match your search.', 'ds-toolkit' ) ) . '</p>';
		}
		if ( $psize ) { echo '<nav class="ds-table-pager" data-ds-table-pager aria-label="' . esc_attr__( 'Table pages', 'ds-toolkit' ) . '" hidden></nav>'; }

		if ( $edit && 'manual' !== $meta['source'] && ! $meta['error'] ) {
			$when = ! empty( $meta['fetched'] ) ? human_time_diff( (int) $meta['fetched'] ) : ( ! empty( $meta['modified'] ) ? human_time_diff( (int) $meta['modified'] ) : '' );
			/* translators: 1: number of rows, 2: file or link, 3: time ago */
			echo '<p class="ds-table-note ds-table-note--sync">' . esc_html( sprintf( __( '%1$d rows synced from %2$s%3$s. Visitors see the same data.', 'ds-toolkit' ), count( $rows ), 'file' === $meta['source'] ? ( $meta['name'] ?? 'the CSV file' ) : __( 'the link', 'ds-toolkit' ), $when ? ' (' . sprintf( __( 'updated %s ago', 'ds-toolkit' ), $when ) . ')' : '' ) ) . '</p>';
		}
		echo '</div>';
	}
}

/* ---------------------------------------------------------------------
 * Builder UI + AJAX (the editor inside the settings panel)
 * ------------------------------------------------------------------ */

add_action( 'fl_builder_ui_enqueue_scripts', function () {
	$v = function ( $rel ) { $t = @filemtime( DS_TOOLKIT_PATH . $rel ); return $t ? DS_TOOLKIT_VERSION . '.' . $t : DS_TOOLKIT_VERSION; };
	wp_enqueue_style( 'ds-table-editor', DS_TOOLKIT_URL . 'modules/ds-table/css/editor.css', array(), $v( 'modules/ds-table/css/editor.css' ) );
	wp_enqueue_script( 'ds-table-editor', DS_TOOLKIT_URL . 'modules/ds-table/js/editor.js', array( 'jquery' ), $v( 'modules/ds-table/js/editor.js' ), true );
	wp_localize_script( 'ds-table-editor', 'DSTable', array(
		'ajaxurl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'ds_table' ),
		'maxRows'  => DS_Table_Data::MAX_ROWS,
		'maxCols'  => DS_Table_Data::MAX_COLS,
		'maxBytes' => DS_Table_Data::MAX_BYTES,
		'canUpload' => current_user_can( 'upload_files' ),
	) );
} );

/** Shared guard for the table endpoints. */
function ds_table_ajax_guard() {
	check_ajax_referer( 'ds_table', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to edit tables.', 'ds-toolkit' ) ), 403 );
	}
}

/** The reply every endpoint sends: parsed rows plus what the editor shows about the file. */
function ds_table_ajax_reply( $id, array $rows, array $warnings = array(), $name = '' ) {
	$path = $id ? get_attached_file( $id ) : '';
	wp_send_json_success( array(
		'id'       => (int) $id,
		'name'     => $path ? wp_basename( $path ) : (string) $name,
		'url'      => $id ? (string) wp_get_attachment_url( $id ) : '',
		'rows'     => $rows,
		'warnings' => $warnings,
	) );
}

/**
 * A CSV from the editor (the Import / Upload buttons, or a file dropped on the grid).
 * Importing rows into the table only reads the file (store=0) and keeps nothing, so a
 * roster CSV never sits in the Media Library at a public URL with columns the table
 * left out. A file the table stays synced to is stored there; with replace_id the
 * upload overwrites that CSV in place (same attachment, same URL), so every table
 * synced to it shows the new rows at once.
 */
add_action( 'wp_ajax_ds_table_upload', function () {
	ds_table_ajax_guard();
	$replace = isset( $_POST['replace_id'] ) ? absint( $_POST['replace_id'] ) : 0;
	$store   = $replace || ! isset( $_POST['store'] ) || '0' !== (string) $_POST['store'];
	if ( $store && ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( array( 'message' => __( 'Your account cannot upload files.', 'ds-toolkit' ) ), 403 );
	}
	if ( empty( $_FILES['file'] ) || ! empty( $_FILES['file']['error'] ) ) {
		wp_send_json_error( array( 'message' => __( 'The upload did not arrive. Try again.', 'ds-toolkit' ) ) );
	}
	$f   = $_FILES['file'];
	$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'csv', 'tsv', 'txt' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Choose a .csv file (in Excel or Google Sheets: File > Download > CSV).', 'ds-toolkit' ) ) );
	}
	if ( (int) $f['size'] > DS_Table_Data::MAX_BYTES ) {
		wp_send_json_error( array( 'message' => sprintf( __( 'The file is larger than %d MB.', 'ds-toolkit' ), (int) ( DS_Table_Data::MAX_BYTES / 1048576 ) ) ) );
	}
	$raw    = (string) file_get_contents( $f['tmp_name'] );
	$parsed = DS_Table_Data::parse_csv( $raw );
	if ( ! $parsed['rows'] ) {
		wp_send_json_error( array( 'message' => __( 'The file has no rows.', 'ds-toolkit' ) ) );
	}
	if ( ! $store ) {
		ds_table_ajax_reply( 0, $parsed['rows'], $parsed['warnings'], sanitize_file_name( (string) $f['name'] ) );
	}

	if ( $replace ) {
		if ( ! DS_Table_Data::is_csv_attachment( $replace ) || ! current_user_can( 'edit_post', $replace ) ) {
			wp_send_json_error( array( 'message' => __( 'That CSV file cannot be replaced.', 'ds-toolkit' ) ) );
		}
		$path = get_attached_file( $replace );
		if ( ! @copy( $f['tmp_name'], $path ) ) {
			wp_send_json_error( array( 'message' => __( 'The server could not write the new version.', 'ds-toolkit' ) ) );
		}
		clearstatcache( true, $path );
		$meta = (array) wp_get_attachment_metadata( $replace );
		$meta['filesize'] = filesize( $path );
		wp_update_attachment_metadata( $replace, $meta );
		wp_update_post( array( 'ID' => $replace ) ); // bumps the modified date shown in the Media Library
		ds_table_ajax_reply( $replace, $parsed['rows'], $parsed['warnings'] );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	// Some hosts sniff a CSV as text/plain; let the upload through as the CSV it is.
	$allow = function ( $data, $file, $filename ) {
		$e = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		if ( in_array( $e, array( 'csv', 'tsv', 'txt' ), true ) && empty( $data['type'] ) ) {
			$data['ext']  = $e;
			$data['type'] = 'txt' === $e ? 'text/plain' : ( 'tsv' === $e ? 'text/tab-separated-values' : 'text/csv' );
		}
		return $data;
	};
	add_filter( 'wp_check_filetype_and_ext', $allow, 10, 3 );
	$id = media_handle_upload( 'file', 0, array(), array( 'test_form' => false, 'mimes' => array( 'csv' => 'text/csv', 'tsv' => 'text/tab-separated-values', 'txt' => 'text/plain' ) ) );
	remove_filter( 'wp_check_filetype_and_ext', $allow, 10 );
	if ( is_wp_error( $id ) ) {
		wp_send_json_error( array( 'message' => $id->get_error_message() ) );
	}
	ds_table_ajax_reply( $id, $parsed['rows'], $parsed['warnings'] );
} );

/** Parse a CSV that is already in the Media Library (Choose from library, and the file-source preview). */
add_action( 'wp_ajax_ds_table_parse', function () {
	ds_table_ajax_guard();
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( ! DS_Table_Data::is_csv_attachment( $id ) ) {
		wp_send_json_error( array( 'message' => __( 'That file is not a CSV in the Media Library.', 'ds-toolkit' ) ) );
	}
	$got = DS_Table_Data::file_rows( $id );
	ds_table_ajax_reply( $id, $got['rows'], $got['warnings'] ?? array() );
} );

/** Fetch a CSV / Google Sheet link now (the link-source preview and "Refresh now"). */
add_action( 'wp_ajax_ds_table_fetch', function () {
	ds_table_ajax_guard();
	$url   = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	$force = ! empty( $_POST['force'] );
	if ( $force ) {
		// One forced refetch per link per 20 seconds, however often the button is clicked.
		$gate = 'ds_table_uf_' . md5( DS_Table_Data::csv_url( $url ) );
		if ( get_transient( $gate ) ) { $force = false; } else { set_transient( $gate, 1, 20 ); }
	}
	$got = DS_Table_Data::url_rows( $url, 15 * MINUTE_IN_SECONDS, $force );
	if ( ! empty( $got['error'] ) && empty( $got['rows'] ) ) {
		wp_send_json_error( array( 'message' => $got['error'] ) );
	}
	wp_send_json_success( array( 'rows' => $got['rows'], 'warnings' => $got['warnings'] ?? array(), 'error' => $got['error'] ?? '', 'fetched' => $got['fetched'] ?? 0, 'stale' => ! empty( $got['stale'] ) ) );
} );

/* ---------------------------------------------------------------------
 * Settings form
 * ------------------------------------------------------------------ */

$ds_tbl_colour = function ( $label, $extra = array() ) {
	return array_merge( array( 'type' => 'color', 'label' => $label, 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ) ), $extra );
};
$ds_tbl_unit = function ( $label, $min, $max, $extra = array() ) {
	return array_merge( array( 'type' => 'unit', 'label' => $label, 'default' => '', 'description' => 'px', 'slider' => array( 'min' => $min, 'max' => $max, 'step' => 1 ) ), $extra );
};
$ds_tbl_yes = function ( $label, $default = 'yes', $extra = array() ) {
	return array_merge( array( 'type' => 'select', 'label' => $label, 'default' => $default, 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ), $extra );
};

FLBuilder::register_module( 'DS_Table_Module', array(

	'content' => array(
		'title'    => __( 'Table', 'ds-toolkit' ),
		'sections' => array(
			'data' => array(
				'title'  => __( 'Table data', 'ds-toolkit' ),
				'fields' => array(
					'source'     => array(
						'type'    => 'select',
						'label'   => __( 'Rows come from', 'ds-toolkit' ),
						'default' => 'manual',
						'options' => array(
							'manual' => __( 'The table editor below', 'ds-toolkit' ),
							'file'   => __( 'A CSV file (kept in sync)', 'ds-toolkit' ),
							'url'    => __( 'A Google Sheet or CSV link (kept in sync)', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'file' => array( 'fields' => array( 'csv_header' ) ),
							'url'  => array( 'fields' => array( 'csv_url', 'sync_every', 'csv_header' ) ),
						),
						'help'    => __( 'The editor is quickest for a short table. A CSV file stays in sync: upload a new version and the table updates everywhere it is used. A Google Sheet stays in sync on its own: edit the sheet and the site follows.', 'ds-toolkit' ),
					),
					'csv_url'    => array(
						'type'        => 'text',
						'label'       => __( 'Google Sheet or CSV link', 'ds-toolkit' ),
						'default'     => '',
						'placeholder' => 'https://docs.google.com/spreadsheets/d/…',
						'help'        => __( 'Paste the sheet\'s normal link. The sheet must be viewable by anyone with the link (Share > General access), or published to the web.', 'ds-toolkit' ),
						'preview'     => array( 'type' => 'none' ),
					),
					'sync_every' => array(
						'type'    => 'select',
						'label'   => __( 'Check the link for changes', 'ds-toolkit' ),
						'default' => '15',
						'options' => array( '5' => __( 'Every 5 minutes', 'ds-toolkit' ), '15' => __( 'Every 15 minutes', 'ds-toolkit' ), '60' => __( 'Every hour', 'ds-toolkit' ), '360' => __( 'Every 6 hours', 'ds-toolkit' ), '1440' => __( 'Once a day', 'ds-toolkit' ) ),
						'help'    => __( 'If the link cannot be reached, the table keeps showing the last copy that loaded.', 'ds-toolkit' ),
					),
					'csv_header' => $ds_tbl_yes( __( 'First row of the file is the column headings', 'ds-toolkit' ) ),
					'editor'     => array(
						'type'    => 'raw',
						'label'   => '',
						'content' => '<div class="ds-table-editor" data-ds-table-editor><p class="ds-te-loading">' . esc_html__( 'Loading the table editor…', 'ds-toolkit' ) . '</p></div>',
					),
					// Hidden stores written by js/editor.js.
					'table_data' => array( 'type' => 'text', 'label' => '', 'default' => '', 'className' => 'ds-te-store' ),
					'csv_id'     => array( 'type' => 'text', 'label' => '', 'default' => '', 'className' => 'ds-te-store' ),
				),
			),
			'display' => array(
				'title'  => __( 'Display', 'ds-toolkit' ),
				'fields' => array(
					'show_header' => $ds_tbl_yes( __( 'Show the header row', 'ds-toolkit' ) ),
					'first_col'   => array(
						'type'    => 'select',
						'label'   => __( 'First column', 'ds-toolkit' ),
						'default' => 'normal',
						'options' => array( 'normal' => __( 'Same as the others', 'ds-toolkit' ), 'bold' => __( 'Bold', 'ds-toolkit' ), 'rowhead' => __( 'Row headings (bold, read out with each row)', 'ds-toolkit' ) ),
					),
					'link_urls'   => $ds_tbl_yes( __( 'Turn web addresses and emails into links', 'ds-toolkit' ) ),
					'caption'     => array( 'type' => 'text', 'label' => __( 'Table name for screen readers', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'e.g. Tryout schedule', 'ds-toolkit' ), 'help' => __( 'Not shown on the page; the section heading above the table is what sighted visitors read.', 'ds-toolkit' ) ),
				),
			),
			'features' => array(
				'title'  => __( 'Sorting, search & pages', 'ds-toolkit' ),
				'fields' => array(
					'sortable'           => $ds_tbl_yes( __( 'Let visitors sort by column', 'ds-toolkit' ), 'yes', array( 'help' => __( 'Click a heading: first to last, last to first, then back to the order typed. Numbers, prices, dates and times sort as such.', 'ds-toolkit' ) ) ),
					'search'             => $ds_tbl_yes( __( 'Show a search box', 'ds-toolkit' ), 'no', array( 'toggle' => array( 'yes' => array( 'fields' => array( 'search_placeholder', 'none_text' ) ) ) ) ),
					'search_placeholder' => array( 'type' => 'text', 'label' => __( 'Search placeholder', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'Search this table', 'ds-toolkit' ) ),
					'none_text'          => array( 'type' => 'text', 'label' => __( 'No-match message', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'No rows match your search.', 'ds-toolkit' ) ),
					'show_count'         => $ds_tbl_yes( __( 'Show the row count', 'ds-toolkit' ), 'no', array( 'toggle' => array( 'yes' => array( 'fields' => array( 'count_singular', 'count_plural' ) ) ) ) ),
					'count_singular'     => array( 'type' => 'text', 'label' => __( 'Count word (one)', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'row', 'ds-toolkit' ) ),
					'count_plural'       => array( 'type' => 'text', 'label' => __( 'Count word (many)', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'rows', 'ds-toolkit' ) ),
					'page_size'          => array( 'type' => 'unit', 'label' => __( 'Rows per page', 'ds-toolkit' ), 'default' => '', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 5 ), 'help' => __( 'Blank or 0 shows every row.', 'ds-toolkit' ) ),
					'prev_text'          => array( 'type' => 'text', 'label' => __( 'Previous button', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'Previous', 'ds-toolkit' ) ),
					'next_text'          => array( 'type' => 'text', 'label' => __( 'Next button', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'Next', 'ds-toolkit' ) ),
				),
			),
			'phones' => array(
				'title'  => __( 'On phones', 'ds-toolkit' ),
				'fields' => array(
					'mobile_mode'  => array(
						'type'    => 'select',
						'label'   => __( 'Phone layout', 'ds-toolkit' ),
						'default' => 'cards',
						'options' => array( 'cards' => __( 'Stack each row as a card', 'ds-toolkit' ), 'scroll' => __( 'Keep the table, scroll sideways', 'ds-toolkit' ) ),
						'toggle'  => array( 'cards' => array( 'sections' => array( 'cards_style' ) ), 'scroll' => array( 'fields' => array( 'min_col' ) ) ),
						'help'    => __( 'Cards are easier to read on a phone. Columns marked "Hide on phones" in the editor are left out either way.', 'ds-toolkit' ),
					),
					'min_col'      => $ds_tbl_unit( __( 'Minimum column width', 'ds-toolkit' ), 60, 300, array( 'help' => __( 'Blank = 120px. The table scrolls sideways once the columns no longer fit.', 'ds-toolkit' ) ) ),
				),
			),
		),
	),

	'style' => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'preset_sec' => array(
				'title'  => __( 'Preset', 'ds-toolkit' ),
				'fields' => array(
					'style_preset' => array(
						'type'    => 'select',
						'label'   => __( 'Table preset', 'ds-toolkit' ),
						'default' => 'striped',
						'options' => array(
							'striped'  => __( 'Striped rows', 'ds-toolkit' ),
							'brand'    => __( 'Brand header (Accent colour)', 'ds-toolkit' ),
							'framed'   => __( 'Framed (rounded, shadow)', 'ds-toolkit' ),
							'bordered' => __( 'Bordered grid', 'ds-toolkit' ),
							'minimal'  => __( 'Minimal lines', 'ds-toolkit' ),
							'custom'   => __( 'None (only what I set below)', 'ds-toolkit' ),
						),
						'help'    => __( 'A starting look. Anything you set below overrides it; a blank field takes the preset\'s value.', 'ds-toolkit' ),
					),
				),
			),
			'table_sec' => array(
				'title'  => __( 'Table', 'ds-toolkit' ),
				'fields' => array(
					'table_bg'           => $ds_tbl_colour( __( 'Table background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'table_border'       => array( 'type' => 'select', 'label' => __( 'Borders', 'ds-toolkit' ), 'default' => '', 'options' => array( '' => __( 'Preset default', 'ds-toolkit' ), 'none' => __( 'None', 'ds-toolkit' ), 'horizontal' => __( 'Row lines', 'ds-toolkit' ), 'grid' => __( 'Full grid', 'ds-toolkit' ), 'outer' => __( 'Outer frame only', 'ds-toolkit' ), 'outer_horizontal' => __( 'Outer frame + row lines', 'ds-toolkit' ) ) ),
					'table_border_width' => $ds_tbl_unit( __( 'Border width', 'ds-toolkit' ), 1, 6 ),
					'table_border_color' => $ds_tbl_colour( __( 'Border colour', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'table_radius'       => $ds_tbl_unit( __( 'Corner radius', 'ds-toolkit' ), 0, 32 ),
					'table_shadow'       => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => '', 'options' => array( '' => __( 'Preset default', 'ds-toolkit' ), 'none' => __( 'None', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'medium' => __( 'Medium', 'ds-toolkit' ) ) ),
				),
			),
			'head_sec' => array(
				'title'  => __( 'Header row', 'ds-toolkit' ),
				'fields' => array(
					'head_bg_style'     => array(
						'type'    => 'select',
						'label'   => __( 'Background', 'ds-toolkit' ),
						'default' => 'preset',
						'options' => array( 'preset' => __( 'From the preset', 'ds-toolkit' ), 'none' => __( 'None (transparent)', 'ds-toolkit' ), 'custom' => __( 'Pick a colour', 'ds-toolkit' ) ),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'head_bg' ) ) ),
						'help'    => __( 'None removes a preset\'s header colour, which clearing a colour field alone cannot do.', 'ds-toolkit' ),
					),
					'head_bg'           => $ds_tbl_colour( __( 'Header colour', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'head_color'        => $ds_tbl_colour( __( 'Text colour', 'ds-toolkit' ), array( 'help' => __( 'Blank = the site\'s Headings colour (white on the Brand preset).', 'ds-toolkit' ) ) ),
					'head_typo'         => array( 'type' => 'typography', 'label' => __( 'Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-table-th' ) ),
					'head_border_width' => $ds_tbl_unit( __( 'Line under the header', 'ds-toolkit' ), 0, 6 ),
					'head_border_color' => $ds_tbl_colour( __( 'Line colour', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
				),
			),
			'row_sec' => array(
				'title'  => __( 'Rows', 'ds-toolkit' ),
				'fields' => array(
					'row_bg'      => $ds_tbl_colour( __( 'Row background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'row_stripe'  => $ds_tbl_colour( __( 'Alternate row background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'row_hover'   => $ds_tbl_colour( __( 'Hover background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'row_color'   => $ds_tbl_colour( __( 'Text colour', 'ds-toolkit' ), array( 'help' => __( 'Blank = the site\'s Body colour. On a dark band, pick a light colour.', 'ds-toolkit' ) ) ),
					'first_color' => $ds_tbl_colour( __( 'First column text colour', 'ds-toolkit' ) ),
					'row_typo'    => array( 'type' => 'typography', 'label' => __( 'Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-table-td' ) ),
					'cell_pad'    => $ds_tbl_unit( __( 'Cell padding', 'ds-toolkit' ), 4, 40, array( 'responsive' => true ) ),
					'link_color'  => $ds_tbl_colour( __( 'Link colour', 'ds-toolkit' ) ),
					'link_hover'  => $ds_tbl_colour( __( 'Link hover colour', 'ds-toolkit' ) ),
				),
			),
			'controls_sec' => array(
				'title'  => __( 'Search, sort & pages', 'ds-toolkit' ),
				'fields' => array(
					'control_color'  => $ds_tbl_colour( __( 'Text colour', 'ds-toolkit' ) ),
					'control_bg'     => $ds_tbl_colour( __( 'Box background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'control_border' => $ds_tbl_colour( __( 'Box border', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'control_radius' => $ds_tbl_unit( __( 'Corner radius', 'ds-toolkit' ), 0, 30 ),
					'search_width'   => $ds_tbl_unit( __( 'Search box width', 'ds-toolkit' ), 120, 600, array( 'help' => __( 'Blank = up to 320px.', 'ds-toolkit' ) ) ),
					'active_bg'      => $ds_tbl_colour( __( 'Current page background', 'ds-toolkit' ) ),
					'active_color'   => $ds_tbl_colour( __( 'Current page text', 'ds-toolkit' ) ),
					'sort_icon'      => $ds_tbl_colour( __( 'Active sort arrow', 'ds-toolkit' ) ),
					'bar_space'      => $ds_tbl_unit( __( 'Space below the search bar', 'ds-toolkit' ), 0, 60 ),
				),
			),
			'cards_style' => array(
				'title'  => __( 'Phone cards', 'ds-toolkit' ),
				'fields' => array(
					'card_bg'       => $ds_tbl_colour( __( 'Card background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'card_border'   => $ds_tbl_colour( __( 'Card border', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'card_radius'   => $ds_tbl_unit( __( 'Card corner radius', 'ds-toolkit' ), 0, 30 ),
					'card_gap'      => $ds_tbl_unit( __( 'Space between cards', 'ds-toolkit' ), 0, 40 ),
					'card_title'    => $ds_tbl_yes( __( 'First column as the card title', 'ds-toolkit' ) ),
					'label_color'   => $ds_tbl_colour( __( 'Label colour', 'ds-toolkit' ) ),
					'label_size'    => $ds_tbl_unit( __( 'Label size', 'ds-toolkit' ), 9, 16 ),
				),
			),
		),
	),
) );
