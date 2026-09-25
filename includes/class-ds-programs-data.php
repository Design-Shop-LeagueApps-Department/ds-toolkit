<?php
/**
 * LeagueApps program feed for the Programs module: fetch, retry, cache,
 * normalise, and the FIELD CATALOGUE that the module's columns, filters and
 * sorting are all driven from.
 *
 * Why a catalogue. The first version of this module (a site-scoped mu-plugin
 * on saltcitysports, 2026-09-20) hardcoded eleven columns and five filters,
 * and three of those columns were one partner's data-entry convention: their
 * admins type the event dates into LeagueApps' Sponsor field, the game count
 * into Season and the month into Experience Level. Another partner's Sponsor
 * field holds a sponsor. A fleet module cannot ship that mapping, so every
 * value the table can show is declared once here with where it comes from,
 * and the builder only ever picks from this list. Adding a column or filter
 * is a new entry, not new code.
 *
 * Dates and months are DERIVED from the API's own startTime / endTime rather
 * than read from typed text. On the partner that motivated this, 25 of 226
 * typed date ranges and 10 of 219 typed months disagreed with the program's
 * real dates (typos like 10/14/26, a tournament advertised to 11/06 that ends
 * 10/14, "April" on a program that starts in June). The typed fields stay
 * available through the module's field-mapping override for a partner who
 * insists on them.
 *
 * Credentials live ONCE per site in ds_toolkit_settings['leagueapps_sites']
 * (Settings > DS Toolkit > LeagueApps), never in a module instance, so a key
 * rotation is one edit rather than one per page, and no key ever sits in a
 * page layout or reaches the browser. The fetch is server-side.
 *
 * @package ds-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DS_Programs_Data {

	/** Fresh-cache lifetime. */
	const TTL = 10 * MINUTE_IN_SECONDS;

	/** Stale fallback lifetime: what is served when the API is unreachable. */
	const STALE_TTL = 7 * DAY_IN_SECONDS;

	/**
	 * How long one request may hold the refetch lock. Long enough for a slow
	 * fetch of several sites, short enough that a request that dies mid-fetch
	 * cannot block refreshes for long.
	 */
	const LOCK_TTL = 30;

	/**
	 * After a fetch fails outright, how long to serve the stale copy before
	 * trying LeagueApps again. Raised to a 429's Retry-After when one is sent,
	 * capped at FAIL_TTL_MAX. Without this every page view during an outage
	 * is a fresh round of requests against an API that is already struggling.
	 */
	const FAIL_TTL     = 2 * MINUTE_IN_SECONDS;
	const FAIL_TTL_MAX = 10 * MINUTE_IN_SECONDS;

	/** Per-request HTTP timeout. */
	const TIMEOUT = 8;

	/**
	 * Fetch ledger. A meter, not a throttle: the cache above is what bounds
	 * the traffic, this records what the site actually sent so the number
	 * per site can be read off the settings tab instead of assumed. One
	 * non-autoloaded option holding the last LEDGER_MAX fetches (newest
	 * first) plus per-hour counters for a rolling day. Not a file: a file in
	 * the plugin folder is flagged by the fleet integrity scanner, wiped by a
	 * plugin update, and unreadable from wp-admin.
	 */
	const LEDGER_OPTION = 'ds_programs_ledger';
	const LEDGER_MAX    = 50;

	/** Attempt count and last HTTP status of the most recent fetch_site() call, for the ledger. */
	private static $last_tries = 0;
	private static $last_code  = 0;

	const API_BASE = 'https://public.leagueapps.io/v1/sites/';

	/* ------------------------------------------------------------------
	 * Credentials
	 * ---------------------------------------------------------------- */

	/**
	 * Configured LeagueApps sites, from DS Toolkit settings.
	 *
	 * @return array list of array( 'site_id' => '46287', 'api_key' => '…', 'label' => 'Carrier Park' )
	 */
	public static function configured_sites() {
		$opts = get_option( 'ds_toolkit_settings', array() );
		$raw  = ( is_array( $opts ) && isset( $opts['leagueapps_sites'] ) && is_array( $opts['leagueapps_sites'] ) ) ? $opts['leagueapps_sites'] : array();
		return self::clean_sites( $raw );
	}

	/** Trim / validate site rows. Site ids are digits; keys are the public widget key (hex-ish). */
	public static function clean_sites( array $sites ) {
		$out = array();
		foreach ( $sites as $s ) {
			$s  = (array) $s;
			$id = preg_replace( '/\D+/', '', (string) ( $s['site_id'] ?? '' ) );
			$k  = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $s['api_key'] ?? '' ) );
			if ( '' === $id || '' === $k ) { continue; }
			$out[ $id ] = array(
				'site_id' => $id,
				'api_key' => $k,
				'label'   => sanitize_text_field( (string) ( $s['label'] ?? '' ) ),
			);
		}
		return array_values( $out );
	}

	/* ------------------------------------------------------------------
	 * Field catalogue
	 * ---------------------------------------------------------------- */

	/**
	 * Every value the table can show or filter on.
	 *
	 * key => array(
	 *   label   default header / filter label
	 *   all     the filter's "show everything" option text
	 *   column  can be a column
	 *   filter  can be a filter
	 *   sort    how filter options order: month | age | day | num | natural
	 *   multi   filter matches if the row's comma list CONTAINS the option (days)
	 *   align   default column alignment
	 * )
	 * Values themselves are produced by normalize(); the raw API field each
	 * one reads is documented there.
	 */
	public static function catalog() {
		static $c = null;
		if ( $c ) { return $c; }
		$t = 'ds-toolkit';
		$c = array(
			'program'     => array( 'label' => __( 'Program', $t ),            'all' => '',                            'column' => true,  'filter' => false, 'sort' => 'natural' ),
			'ageGroup'    => array( 'label' => __( 'Age Group', $t ),          'all' => __( 'All Age Groups', $t ),    'column' => true,  'filter' => true,  'sort' => 'age' ),
			'dateRange'   => array( 'label' => __( 'Dates', $t ),              'all' => '',                            'column' => true,  'filter' => false, 'sort' => 'natural', 'nowrap' => true ),
			'startDate'   => array( 'label' => __( 'Start Date', $t ),         'all' => '',                            'column' => true,  'filter' => false, 'sort' => 'natural', 'nowrap' => true ),
			'endDate'     => array( 'label' => __( 'End Date', $t ),           'all' => '',                            'column' => true,  'filter' => false, 'sort' => 'natural', 'nowrap' => true ),
			'month'       => array( 'label' => __( 'Month', $t ),              'all' => __( 'All Months', $t ),        'column' => true,  'filter' => true,  'sort' => 'month' ),
			'sport'       => array( 'label' => __( 'Sport', $t ),              'all' => __( 'All Sports', $t ),        'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'gender'      => array( 'label' => __( 'Gender', $t ),             'all' => __( 'All Genders', $t ),       'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'type'        => array( 'label' => __( 'Type', $t ),               'all' => __( 'Tournaments & Leagues', $t ), 'column' => true, 'filter' => true, 'sort' => 'natural' ),
			'mode'        => array( 'label' => __( 'Youth / Adult', $t ),      'all' => __( 'Youth & Adult', $t ),     'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'status'      => array( 'label' => __( 'Registration', $t ),       'all' => __( 'Any Status', $t ),        'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'state'       => array( 'label' => __( 'Season Status', $t ),      'all' => __( 'Upcoming & In Season', $t ), 'column' => true, 'filter' => true, 'sort' => 'natural' ),
			'days'        => array( 'label' => __( 'Days', $t ),               'all' => __( 'Any Day', $t ),           'column' => true,  'filter' => true,  'sort' => 'day', 'multi' => true ),
			'location'    => array( 'label' => __( 'Location', $t ),           'all' => __( 'All Locations', $t ),     'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'city'        => array( 'label' => __( 'City', $t ),               'all' => __( 'All Cities', $t ),        'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'season'      => array( 'label' => __( 'Season', $t ),             'all' => __( 'All Seasons', $t ),       'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'level'       => array( 'label' => __( 'Level', $t ),              'all' => __( 'All Levels', $t ),        'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'format'      => array( 'label' => __( 'Format', $t ),             'all' => __( 'All Formats', $t ),       'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'sponsor'     => array( 'label' => __( 'Sponsor', $t ),            'all' => __( 'All Sponsors', $t ),      'column' => true,  'filter' => true,  'sort' => 'natural' ),
			'price'       => array( 'label' => __( 'Price', $t ),              'all' => '',                            'column' => true,  'filter' => false, 'sort' => 'natural', 'align' => 'right', 'nowrap' => true ),
			'spots'       => array( 'label' => __( 'Spots Left', $t ),         'all' => '',                            'column' => true,  'filter' => false, 'sort' => 'natural', 'align' => 'center', 'nowrap' => true ),
			'register'    => array( 'label' => __( 'Register Button', $t ),    'all' => '',                            'column' => true,  'filter' => false, 'sort' => 'natural', 'align' => 'right' ),
		);
		return $c;
	}

	/** Catalogue keys usable as columns (key => label). */
	public static function column_options() {
		$out = array();
		foreach ( self::catalog() as $k => $f ) { if ( ! empty( $f['column'] ) ) { $out[ $k ] = $f['label']; } }
		return $out;
	}

	/** Catalogue keys usable as filters (key => label). */
	public static function filter_options() {
		$out = array();
		foreach ( self::catalog() as $k => $f ) { if ( ! empty( $f['filter'] ) ) { $out[ $k ] = $f['label']; } }
		return $out;
	}

	/**
	 * Raw LeagueApps fields an editor may map onto a catalogue key, for the
	 * partners whose admins type meaning into a field LeagueApps meant for
	 * something else. Key => label shown in the builder.
	 */
	public static function raw_field_options() {
		$t = 'ds-toolkit';
		return array(
			'sponsor'         => __( 'Sponsor field', $t ),
			'season'          => __( 'Season field', $t ),
			'experienceLevel' => __( 'Experience Level field', $t ),
			'leagueFormat'    => __( 'League Format field', $t ),
			'name'            => __( 'Program name', $t ),
			'host'            => __( 'Host field', $t ),
			'scheduleDays'    => __( 'Schedule Days field', $t ),
			'description'     => __( 'Description (text only)', $t ),
		);
	}

	/* ------------------------------------------------------------------
	 * Feed
	 * ---------------------------------------------------------------- */

	/**
	 * Normalised programs for a set of sites.
	 *
	 * @param array $sites        Rows from configured_sites() (or a subset).
	 * @param array $overrides    catalogue key => raw field name (field mapping).
	 * @param array $format       array( 'date' => 'numeric|short|long' )
	 * @return array{programs:array,stale:bool,errors:array,fetched:int}
	 */
	public static function get( array $sites, array $overrides = array(), array $format = array() ) {
		$sites = self::clean_sites( $sites );
		if ( empty( $sites ) ) {
			return array( 'programs' => array(), 'stale' => false, 'errors' => array( __( 'No LeagueApps site is configured. Add one under Settings > DS Toolkit > LeagueApps.', 'ds-toolkit' ) ), 'fetched' => 0 );
		}

		$raw = self::raw_feed( $sites );
		$programs = self::build_rows( $raw['rows'], $overrides, $format );
		return array( 'programs' => $programs, 'stale' => $raw['stale'], 'errors' => $raw['errors'], 'fetched' => $raw['fetched'] );
	}

	/**
	 * The raw merged feed, cached. Kept raw (not normalised) so a change to the
	 * module's field mapping or date format never needs a refetch.
	 */
	private static function raw_feed( array $sites ) {
		$key       = 'ds_programs_' . md5( wp_json_encode( wp_list_pluck( $sites, 'site_id' ) ) );
		$stale_key = $key . '_stale';
		$lock_key  = $key . '_lock';

		$hit = get_transient( $key );
		if ( is_array( $hit ) && isset( $hit['rows'] ) ) {
			if ( empty( $hit['failed'] ) ) {
				return array( 'rows' => $hit['rows'], 'stale' => false, 'errors' => array(), 'fetched' => (int) ( $hit['fetched'] ?? 0 ) );
			}
			// The last fetch failed outright and its back-off has not expired.
			// Serve the stale copy and do not touch the API.
			return self::serve_stale( $stale_key, (array) ( $hit['errors'] ?? array() ) );
		}

		// Cache miss. Exactly one request refetches; every other request that
		// lands in the same moment serves the stale copy instead of also calling
		// LeagueApps. Without this, a burst of traffic at the instant the cache
		// expires (a bot, a newsletter, a share) becomes that many identical
		// requests to their API.
		if ( ! self::lock( $lock_key ) ) {
			// Another request is fetching. If a stale copy exists, serve it NOW: a
			// request that sleeps here holds a PHP worker, and a burst at the moment
			// the cache expires would exhaust the worker pool while LeagueApps was
			// being protected. Only a first-ever load with nothing cached waits.
			$stale = get_transient( $stale_key );
			if ( is_array( $stale ) && ! empty( $stale['rows'] ) ) {
				return self::serve_stale( $stale_key, array() );
			}
			for ( $i = 0; $i < 6; $i++ ) {
				usleep( 250000 );
				$hit = get_transient( $key );
				if ( is_array( $hit ) && isset( $hit['rows'] ) && empty( $hit['failed'] ) ) {
					return array( 'rows' => $hit['rows'], 'stale' => false, 'errors' => array(), 'fetched' => (int) ( $hit['fetched'] ?? 0 ) );
				}
			}
			return self::serve_stale( $stale_key, array( __( 'feed refresh in progress', 'ds-toolkit' ) ) );
		}

		$rows        = array();
		$errors      = array();
		$retry_after = 0;
		$why         = self::fetch_reason( $stale_key );

		foreach ( $sites as $site ) {
			// Re-arm per site: one site's worst case (3 attempts x TIMEOUT + back-off)
			// fits inside LOCK_TTL; several sites in a row would not.
			set_transient( $lock_key, time(), self::LOCK_TTL );
			$t0  = microtime( true );
			$res = self::fetch_site( $site['site_id'], $site['api_key'] );
			$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );
			if ( is_wp_error( $res ) ) {
				self::record( array( 'site' => $site['site_id'], 'ok' => false, 'code' => self::$last_code, 'tries' => self::$last_tries, 'ms' => $ms, 'rows' => 0, 'err' => $res->get_error_message(), 'why' => $why ) );
				// One site failing must not take the others down with it.
				$errors[] = sprintf( 'site %s: %s', $site['site_id'], $res->get_error_message() );
				$data     = $res->get_error_data();
				if ( is_array( $data ) && ! empty( $data['retry_after'] ) ) {
					$retry_after = max( $retry_after, (int) $data['retry_after'] );
				}
				continue;
			}
			self::record( array( 'site' => $site['site_id'], 'ok' => true, 'code' => 200, 'tries' => self::$last_tries, 'ms' => $ms, 'rows' => count( $res ), 'err' => '', 'why' => $why ) );
			foreach ( $res as $row ) {
				if ( is_array( $row ) ) {
					$row['_site']  = $site['site_id'];
					$row['_label'] = $site['label'];
					$rows[]        = $row;
				}
			}
		}

		if ( empty( $rows ) ) {
			// Total failure. Remember it, so the next page view serves the stale
			// copy instead of retrying LeagueApps, which is exactly when it can
			// least afford the traffic. A 429's Retry-After lengthens the wait.
			$backoff = self::FAIL_TTL;
			if ( $retry_after > 0 ) {
				$backoff = min( max( $retry_after, self::FAIL_TTL ), self::FAIL_TTL_MAX );
			}
			set_transient( $key, array( 'rows' => array(), 'fetched' => 0, 'failed' => true, 'errors' => $errors ), $backoff );
			self::remember_key( $key );
			delete_transient( $lock_key );
			return self::serve_stale( $stale_key, $errors );
		}

		$pack = array( 'rows' => $rows, 'fetched' => time() );
		// A partial result is served but not cached as the good copy for long,
		// so the next request retries the site that failed.
		set_transient( $key, $pack, $errors ? MINUTE_IN_SECONDS : self::TTL );
		set_transient( $stale_key, $pack, self::STALE_TTL );
		self::remember_key( $key );
		self::remember_sports( $rows );
		delete_transient( $lock_key );

		return array( 'rows' => $rows, 'stale' => false, 'errors' => $errors, 'fetched' => $pack['fetched'] );
	}

	/** The 7-day fallback, or an empty result carrying the errors. */
	private static function serve_stale( $stale_key, array $errors ) {
		$stale = get_transient( $stale_key );
		if ( is_array( $stale ) && ! empty( $stale['rows'] ) ) {
			return array( 'rows' => $stale['rows'], 'stale' => true, 'errors' => $errors, 'fetched' => (int) ( $stale['fetched'] ?? 0 ) );
		}
		return array( 'rows' => array(), 'stale' => false, 'errors' => $errors, 'fetched' => 0 );
	}

	/**
	 * Best-effort mutex around a refetch. A transient, not an option, so it
	 * expires on its own if the fetching request dies. Not perfectly atomic on
	 * a database-backed cache, so two requests in the same millisecond can both
	 * refetch; that bounds a burst to a couple of API calls instead of one per
	 * visitor, which is the point.
	 */
	private static function lock( $lock_key ) {
		if ( false !== get_transient( $lock_key ) ) { return false; }
		set_transient( $lock_key, time(), self::LOCK_TTL );
		return true;
	}

	/**
	 * One site. Only `/programs/current` exists; the API has no server-side
	 * filtering. Retries are for connection-level failures and a garbled body
	 * only. Any HTTP error is returned at once: a 429 or a 5xx means LeagueApps
	 * is struggling, and retrying into that is the one thing a well-behaved
	 * client must not do.
	 */
	private static function fetch_site( $site_id, $api_key ) {
		$url  = self::API_BASE . rawurlencode( $site_id ) . '/programs/current?x-api-key=' . rawurlencode( $api_key );
		$last = null;
		self::$last_tries = 0;
		self::$last_code  = 0;

		for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
			self::$last_tries = $attempt;
			$res = wp_remote_get( $url, array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => 'ds-toolkit-programs/' . ( defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '0' ) . ' (+' . home_url( '/' ) . ')',
				'headers'    => array( 'Accept' => 'application/json' ),
			) );

			if ( is_wp_error( $res ) ) {
				// DNS, TLS, timeout: a blip on our side, worth one more try.
				$last = $res;
			} else {
				$code = (int) wp_remote_retrieve_response_code( $res );
				$body = wp_remote_retrieve_body( $res );
				self::$last_code = $code;
				if ( 200 === $code ) {
					$json = json_decode( $body, true );
					if ( is_array( $json ) ) { return $json; }
					$last = new WP_Error( 'ds_programs_json', 'malformed JSON' );
				} elseif ( 403 === $code ) {
					return new WP_Error( 'ds_programs_key', __( 'LeagueApps rejected the API key (403)', 'ds-toolkit' ) );
				} elseif ( 404 === $code ) {
					return new WP_Error( 'ds_programs_site', __( 'site not found for this key (404)', 'ds-toolkit' ) );
				} elseif ( 429 === $code ) {
					// Throttled. Stop now and pass Retry-After up so the back-off honours it.
					$ra = (int) wp_remote_retrieve_header( $res, 'retry-after' );
					return new WP_Error( 'ds_programs_throttled', __( 'LeagueApps is rate limiting requests (429)', 'ds-toolkit' ), array( 'retry_after' => $ra ) );
				} else {
					return new WP_Error( 'ds_programs_http', 'HTTP ' . $code );
				}
			}
			if ( $attempt < 3 ) { usleep( 300000 * $attempt ); }
		}
		return $last ?: new WP_Error( 'ds_programs_unknown', 'unknown error' );
	}

	/* ------------------------------------------------------------------
	 * Normalisation
	 * ---------------------------------------------------------------- */

	/**
	 * Raw API rows -> table rows.
	 *
	 * LeagueApps models a tournament as a MASTER program with one CHILD per
	 * age group. One table row = one child; "Program" is the master's name and
	 * "Age Group" the child's own name. The API does not populate
	 * masterProgramName, so the join is done here on masterProgramId. A master
	 * that has children is a heading, not a row.
	 */
	private static function build_rows( array $raw, array $overrides, array $format ) {
		$masters   = array();
		$has_child = array();

		foreach ( $raw as $row ) {
			$pid = (int) ( $row['programId'] ?? 0 );
			if ( ! empty( $row['isMaster'] ) && $pid ) { $masters[ $pid ] = $row; }
			$mid = (int) ( $row['masterProgramId'] ?? 0 );
			if ( $mid && $pid !== $mid ) { $has_child[ $mid ] = true; }
		}

		$rows = array();
		foreach ( $raw as $row ) {
			$pid = (int) ( $row['programId'] ?? 0 );
			if ( ! empty( $has_child[ $pid ] ) ) { continue; }
			$p = self::normalize( $row, $masters, $overrides, $format );
			if ( $p ) { $rows[] = $p; }
		}
		return $rows;
	}

	/** One raw program -> the flat shape the table renders. */
	private static function normalize( $row, array $masters, array $overrides, array $format ) {
		if ( ! is_array( $row ) ) { return null; }

		$visibility = (string) ( $row['visibility'] ?? '' );
		if ( $visibility && 0 !== strcasecmp( $visibility, 'Public' ) ) { return null; }
		if ( ! empty( $row['deleted'] ) ) { return null; }

		$mid    = (int) ( $row['masterProgramId'] ?? 0 );
		$pid    = (int) ( $row['programId'] ?? 0 );
		$master = ( $mid && isset( $masters[ $mid ] ) && $mid !== $pid ) ? $masters[ $mid ] : null;

		// A value on the child wins; blank falls through to the master.
		$inherit = function ( $field ) use ( $row, $master ) {
			$v = trim( (string) ( $row[ $field ] ?? '' ) );
			if ( '' !== $v ) { return $v; }
			return $master ? trim( (string) ( $master[ $field ] ?? '' ) ) : '';
		};

		$own_name = trim( (string) ( $row['name'] ?? '' ) );
		$start    = (int) ( $row['startTime'] ?? ( $master['startTime'] ?? 0 ) );
		$end      = (int) ( $row['endTime'] ?? ( $master['endTime'] ?? 0 ) );
		$start_s  = $start ? (int) floor( $start / 1000 ) : 0;
		$end_s    = $end ? (int) floor( $end / 1000 ) : 0;

		$gender_map = array( 'MALE' => __( 'Boys', 'ds-toolkit' ), 'FEMALE' => __( 'Girls', 'ds-toolkit' ), 'ANY' => __( 'Coed', 'ds-toolkit' ) );
		$type_map   = array( 'TOURNAMENT' => __( 'Tournament', 'ds-toolkit' ), 'LEAGUE' => __( 'League', 'ds-toolkit' ), 'CLASS' => __( 'Class', 'ds-toolkit' ), 'CAMP' => __( 'Camp', 'ds-toolkit' ), 'CLINIC' => __( 'Clinic', 'ds-toolkit' ), 'EVENT' => __( 'Event', 'ds-toolkit' ), 'CLUBTEAM' => __( 'Club Team', 'ds-toolkit' ) );
		$mode_map   = array( 'YOUTH' => __( 'Youth', 'ds-toolkit' ), 'ADULT' => __( 'Adult', 'ds-toolkit' ) );
		$state_map  = array( 'UPCOMING' => __( 'Upcoming', 'ds-toolkit' ), 'LIVE' => __( 'In Season', 'ds-toolkit' ), 'ARCHIVED' => __( 'Past', 'ds-toolkit' ) );
		$stat_map   = array( 'OPEN' => __( 'Open', 'ds-toolkit' ), 'OPENS_SOON' => __( 'Opens Soon', 'ds-toolkit' ), 'SOLD_OUT' => __( 'Sold Out', 'ds-toolkit' ), 'CLOSED' => __( 'Closed', 'ds-toolkit' ), 'WAITLIST' => __( 'Waitlist', 'ds-toolkit' ) );

		$type_raw  = strtoupper( (string) ( $row['type'] ?? '' ) );
		$mode_raw  = strtoupper( (string) ( $row['mode'] ?? '' ) );
		$state_raw = strtoupper( (string) ( $row['state'] ?? '' ) );
		$stat_raw  = strtoupper( (string) ( $row['registrationStatus'] ?? '' ) );
		$spots     = self::spots( $row );

		// registrationStatus is blank on most programs (189 of 226 on the
		// reference site). Derive what can be derived; otherwise leave blank
		// so the filter never claims something it does not know.
		if ( '' === $stat_raw ) {
			if ( '' !== $spots && 0 >= (int) $spots ) { $stat_raw = 'SOLD_OUT'; }
			elseif ( ! empty( $row['endRegistrationTime'] ) && (int) floor( $row['endRegistrationTime'] / 1000 ) < time() ) { $stat_raw = 'CLOSED'; }
			elseif ( ! empty( $row['publicRegistrationTime'] ) && (int) floor( $row['publicRegistrationTime'] / 1000 ) > time() ) { $stat_raw = 'OPENS_SOON'; }
			elseif ( ! empty( $row['registerUrlHtml'] ) ) { $stat_raw = 'OPEN'; }
		}

		$out = array(
			'programId'  => $pid,
			'siteId'     => (string) ( $row['_site'] ?? '' ),
			'siteLabel'  => (string) ( $row['_label'] ?? '' ),
			'groupKey'   => $mid ?: $pid,
			'program'    => $master ? trim( (string) ( $master['name'] ?? '' ) ) : $own_name,
			'ageGroup'   => $master ? $own_name : '',
			'startTs'    => $start_s,
			'endTs'      => $end_s,
			'dateRange'  => self::date_range( $start_s, $end_s, $format['date'] ?? 'numeric' ),
			'startDate'  => self::date_one( $start_s, $format['date'] ?? 'numeric' ),
			'endDate'    => self::date_one( $end_s, $format['date'] ?? 'numeric' ),
			'month'      => $start_s ? date_i18n( 'F', $start_s ) : '',
			'sport'      => $inherit( 'sport' ),
			'gender'     => $gender_map[ strtoupper( (string) ( $row['gender'] ?? '' ) ) ] ?? '',
			'type'       => $type_map[ $type_raw ] ?? ucfirst( strtolower( $type_raw ) ),
			'typeRaw'    => $type_raw,
			'mode'       => $mode_map[ $mode_raw ] ?? ucfirst( strtolower( $mode_raw ) ),
			'modeRaw'    => $mode_raw,
			'status'     => $stat_map[ $stat_raw ] ?? '',
			'statusRaw'  => $stat_raw,
			'state'      => $state_map[ $state_raw ] ?? ucfirst( strtolower( $state_raw ) ),
			'stateRaw'   => $state_raw,
			'days'       => self::days( $inherit( 'scheduleDays' ) ),
			'location'   => $inherit( 'location' ),
			'city'       => $inherit( 'locationCity' ),
			'season'     => $inherit( 'season' ),
			'level'      => $inherit( 'experienceLevel' ),
			'format'     => $inherit( 'leagueFormat' ),
			'sponsor'    => $inherit( 'sponsor' ),
			'price'      => self::price( $row, $master ),
			'spots'      => $spots,
			'registerUrl'=> self::url( (string) ( $row['registerUrlHtml'] ?? '' ) ) ?: self::url( (string) ( $row['programUrlHtml'] ?? '' ) ),
			'programUrl' => self::url( (string) ( $row['programUrlHtml'] ?? '' ) ),
			'soldOut'    => ( 'SOLD_OUT' === $stat_raw ),
		);

		// Field mapping: a partner's typed convention replaces the derived value.
		foreach ( $overrides as $target => $source ) {
			if ( ! isset( $out[ $target ] ) || ! is_string( $source ) || '' === $source ) { continue; }
			$v = $inherit( $source );
			if ( 'description' === $source ) { $v = trim( wp_strip_all_tags( $v ) ); }
			if ( 'scheduleDays' === $source ) { $v = self::days( $v ); }
			$out[ $target ] = $v;
		}

		return $out;
	}

	/** `//host/path` -> `https://host/path`. */
	private static function url( $u ) {
		$u = trim( $u );
		if ( '' === $u ) { return ''; }
		if ( 0 === strpos( $u, '//' ) ) { $u = 'https:' . $u; }
		return esc_url_raw( $u );
	}

	/**
	 * Where the Register button sends people: the program's LeagueApps page,
	 * not the registration form.
	 *
	 * LeagueApps fills registerUrlHtml only for club teams, and there it is
	 * `/registration/init?bid=…`, which drops the visitor straight into
	 * checkout past the page that explains the program. Every other type
	 * leaves it blank and the button already fell through to the program page,
	 * so this makes the two behave the same. The program page carries its own
	 * Register button, so nothing is lost. Falls back to the registration link
	 * for a program that has no page URL at all.
	 */
	public static function button_url( array $row ) {
		$u = (string) ( $row['programUrl'] ?? '' );
		return '' !== $u ? $u : (string) ( $row['registerUrl'] ?? '' );
	}

	private static function price( $row, $master ) {
		foreach ( array( 'teamFee', 'freeAgentFee', 'teamIndividualFee', 'individualFee', 'fee' ) as $f ) {
			$v = $row[ $f ] ?? ( $master[ $f ] ?? null );
			if ( is_numeric( $v ) && $v > 0 ) {
				$v = (float) $v;
				return '$' . ( floor( $v ) == $v ? number_format( $v ) : number_format( $v, 2 ) );
			}
		}
		return '';
	}

	private static function spots( $row ) {
		foreach ( array( 'remainingTeamCount', 'remainingPlayers', 'remainingFreeAgents' ) as $f ) {
			if ( isset( $row[ $f ] ) && is_numeric( $row[ $f ] ) ) {
				return (string) max( 0, (int) $row[ $f ] );
			}
		}
		return '';
	}

	/** "Sun,Sat,Fri" -> "Fri, Sat, Sun" in week order, de-duplicated. */
	private static function days( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) { return ''; }
		$parts = array();
		foreach ( preg_split( '/\s*[,\/]\s*/', $v ) as $d ) {
			$d = ucfirst( strtolower( substr( trim( $d ), 0, 3 ) ) );
			if ( self::day_rank( $d ) < 99 ) { $parts[ $d ] = true; }
		}
		$parts = array_keys( $parts );
		usort( $parts, function ( $a, $b ) { return self::day_rank( $a ) <=> self::day_rank( $b ); } );
		return implode( ', ', $parts );
	}

	/* ------------------------------------------------------------------
	 * Formatting + ordering helpers (public: the module's filters use them)
	 * ---------------------------------------------------------------- */

	public static function date_one( $ts, $style = 'numeric' ) {
		if ( ! $ts ) { return ''; }
		switch ( $style ) {
			case 'short': return date_i18n( 'M j, Y', $ts );
			case 'long':  return date_i18n( 'F j, Y', $ts );
			default:      return date_i18n( 'm/d/Y', $ts );
		}
	}

	/**
	 * "09/19/2026-09/20/2026" (numeric), "Sep 19-20, 2026" / "Sep 30 - Oct 2, 2026"
	 * (short), "September 19-20, 2026" (long). A one-day program prints one date.
	 */
	public static function date_range( $start, $end, $style = 'numeric' ) {
		if ( ! $start ) { return ''; }
		if ( ! $end || $end < $start ) { $end = $start; }
		$same_day = date_i18n( 'Y-m-d', $start ) === date_i18n( 'Y-m-d', $end );

		if ( 'numeric' === $style ) {
			return $same_day ? date_i18n( 'm/d/Y', $start ) : date_i18n( 'm/d/Y', $start ) . '-' . date_i18n( 'm/d/Y', $end );
		}
		$m = ( 'long' === $style ) ? 'F' : 'M';
		if ( $same_day ) { return date_i18n( "$m j, Y", $start ); }
		$same_month = date_i18n( 'Y-m', $start ) === date_i18n( 'Y-m', $end );
		$same_year  = date_i18n( 'Y', $start ) === date_i18n( 'Y', $end );
		if ( $same_month ) { return date_i18n( "$m j", $start ) . '-' . date_i18n( 'j, Y', $end ); }
		if ( $same_year )  { return date_i18n( "$m j", $start ) . ' - ' . date_i18n( "$m j, Y", $end ); }
		return date_i18n( "$m j, Y", $start ) . ' - ' . date_i18n( "$m j, Y", $end );
	}

	/** Chronological month ordering for filter options. */
	public static function month_rank( $label ) {
		static $months = array( 'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
			'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12 );
		$k = strtolower( trim( (string) $label ) );
		foreach ( $months as $name => $n ) {
			if ( 0 === strpos( $k, substr( $name, 0, 3 ) ) ) { return $n; }
		}
		return 99;
	}

	/**
	 * Natural age-group ordering: 8u before 10u, U9 before U10 (soccer's
	 * U-prefix form), "11\12U" and "15/16u" by their first number, and names
	 * with no age ("5v5 2019-2021 Division", "Adult") last, alphabetically.
	 */
	public static function age_rank( $label ) {
		$label = (string) $label;
		if ( preg_match( '/(\d{1,2})\s*[uU]\b/', $label, $m ) )      { return (int) $m[1]; }
		if ( preg_match( '/\b[uU]\s*-?\s*(\d{1,2})\b/', $label, $m ) ) { return (int) $m[1]; }
		if ( preg_match( '/^\s*(\d{1,2})\b/', $label, $m ) )           { return (int) $m[1]; }
		return 999;
	}

	public static function day_rank( $label ) {
		static $days = array( 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7 );
		return $days[ ucfirst( strtolower( substr( trim( (string) $label ), 0, 3 ) ) ) ] ?? 99;
	}

	/* ------------------------------------------------------------------
	 * Cache control
	 * ---------------------------------------------------------------- */

	/**
	 * Drop every fresh copy so the next render refetches. Keeps the stale
	 * fallback, so a flush can never leave a site worse off if LeagueApps
	 * happens to be down at that moment.
	 */
	public static function flush() {
		global $wpdb;
		// So the ledger can tell a manual refresh from a cache expiry.
		set_transient( 'ds_programs_flushed', time(), MINUTE_IN_SECONDS );
		// delete_transient() reaches the object cache as well as the database,
		// so the fresh copy and any refetch lock go wherever they live. The
		// old version called wp_cache_flush(), which emptied the whole site's
		// object cache for a Contributor-level button press.
		foreach ( self::known_keys() as $k ) {
			delete_transient( $k );
			delete_transient( $k . '_lock' );
		}
		// Belt and braces for database-backed rows written before the registry existed.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			  WHERE ( option_name LIKE '_transient_ds_programs_%' OR option_name LIKE '_transient_timeout_ds_programs_%' )
			    AND option_name NOT LIKE '%\_stale'"
		);
	}

	/* ------------------------------------------------------------------
	 * Fetch ledger
	 * ---------------------------------------------------------------- */

	/** Why this refetch is happening: first load, cache expiry, or a manual refresh / settings save. */
	private static function fetch_reason( $stale_key ) {
		if ( false !== get_transient( 'ds_programs_flushed' ) ) {
			delete_transient( 'ds_programs_flushed' );
			return 'flush';
		}
		$stale = get_transient( $stale_key );
		return ( is_array( $stale ) && ! empty( $stale['rows'] ) ) ? 'expired' : 'first';
	}

	/**
	 * Append one fetch to the ledger and bump the hour's counters. Fires
	 * `ds_programs_fetch` with the entry, for a site that wants its own log.
	 *
	 * Entry: t, site, ok, code, tries (HTTP requests this fetch made,
	 * retries included), ms, rows, err, why (first | expired | flush).
	 */
	private static function record( array $e ) {
		$e['t'] = time();
		$book   = get_option( self::LEDGER_OPTION, array() );
		if ( ! is_array( $book ) ) { $book = array(); }
		$recent = ( isset( $book['recent'] ) && is_array( $book['recent'] ) ) ? $book['recent'] : array();
		$hours  = ( isset( $book['hours'] ) && is_array( $book['hours'] ) ) ? $book['hours'] : array();

		array_unshift( $recent, $e );
		$recent = array_slice( $recent, 0, self::LEDGER_MAX );

		// Per-hour counters (fetches, HTTP requests, failures) so the daily
		// totals stay exact however busy the site is, while the detail list
		// above stays short. Keys sort as strings, so pruning is a compare.
		$h = gmdate( 'YmdH', $e['t'] );
		if ( ! isset( $hours[ $h ] ) || ! is_array( $hours[ $h ] ) ) { $hours[ $h ] = array( 0, 0, 0 ); }
		$hours[ $h ][0]++;
		$hours[ $h ][1] += max( 1, (int) $e['tries'] );
		if ( empty( $e['ok'] ) ) { $hours[ $h ][2]++; }
		$cut = gmdate( 'YmdH', $e['t'] - DAY_IN_SECONDS );
		foreach ( array_keys( $hours ) as $k ) {
			if ( (string) $k < $cut ) { unset( $hours[ $k ] ); }
		}
		ksort( $hours );

		update_option( self::LEDGER_OPTION, array( 'recent' => $recent, 'hours' => $hours ), false );
		do_action( 'ds_programs_fetch', $e );
	}

	/** The most recent fetches, newest first. */
	public static function ledger() {
		$book = get_option( self::LEDGER_OPTION, array() );
		return ( is_array( $book ) && isset( $book['recent'] ) && is_array( $book['recent'] ) ) ? $book['recent'] : array();
	}

	/**
	 * Rolling 24-hour totals against what the cache should allow. `budget`
	 * is one fetch per TTL per configured site (144 a day per site at ten
	 * minutes), the most a site with constant traffic can send; a count well
	 * above it means the host's object cache is dropping the feed between
	 * visits. `over` is that comparison, with a quarter of headroom for
	 * manual refreshes and the one-minute partial-result retry.
	 */
	public static function ledger_summary() {
		$book  = get_option( self::LEDGER_OPTION, array() );
		$hours = ( is_array( $book ) && isset( $book['hours'] ) && is_array( $book['hours'] ) ) ? $book['hours'] : array();
		$cut   = gmdate( 'YmdH', time() - DAY_IN_SECONDS );
		$f = $r = $x = 0;
		foreach ( $hours as $k => $c ) {
			if ( (string) $k < $cut || ! is_array( $c ) ) { continue; }
			$f += (int) ( $c[0] ?? 0 );
			$r += (int) ( $c[1] ?? 0 );
			$x += (int) ( $c[2] ?? 0 );
		}
		$sites  = max( 1, count( self::configured_sites() ) );
		$budget = (int) floor( DAY_IN_SECONDS / self::TTL ) * $sites;
		$recent = self::ledger();
		return array(
			'fetches'  => $f,
			'requests' => $r,
			'failures' => $x,
			'budget'   => $budget,
			'over'     => $f > (int) ceil( $budget * 1.25 ),
			'last'     => $recent[0] ?? null,
		);
	}

	/** Every raw-feed cache key this class has written, so flush() can find them in an object cache. */
	private static function known_keys() {
		$k = get_transient( 'ds_programs_keys' );
		return is_array( $k ) ? $k : array();
	}

	private static function remember_key( $key ) {
		$keys = self::known_keys();
		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			set_transient( 'ds_programs_keys', $keys, self::STALE_TTL );
		}
	}

	/**
	 * Sports seen in the last fetched feed, for the builder's Sport picker.
	 * The settings form is built on init, far too early to call an API, so
	 * this reads only what a previous render already cached.
	 */
	public static function known_sports() {
		$cached = get_transient( 'ds_programs_sports' );
		return ( is_array( $cached ) ) ? $cached : array();
	}

	private static function remember_sports( array $raw ) {
		$sports = array();
		foreach ( $raw as $r ) {
			$s = trim( (string) ( $r['sport'] ?? '' ) );
			if ( '' !== $s ) { $sports[ $s ] = true; }
		}
		if ( $sports ) {
			$sports = array_keys( $sports );
			natcasesort( $sports );
			set_transient( 'ds_programs_sports', array_values( $sports ), self::STALE_TTL );
		}
	}
}
