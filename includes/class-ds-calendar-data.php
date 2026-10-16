<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps team schedules for the LeagueApps Calendar module.
 *
 * LeagueApps has no site-level schedule endpoint: a club's calendar is the
 * `/programs/<id>/schedule` of every program, merged. The rules that keep that
 * polite to LeagueApps and fast for visitors:
 *
 *   - The program list comes from DS_Programs_Data::raw(): the Programs table's
 *     own cache, lock, back-off and ledger. A page with both modules costs one
 *     listing call.
 *   - Each program's schedule is stored PER SITE with its own clock: fresh for an
 *     hour, "Schedule Not Found" remembered for six hours, a failure retried on
 *     an escalating timer (5 minutes doubling to an hour). A refresh asks
 *     LeagueApps only about programs that are due, and a program that keeps
 *     failing keeps its last good schedule instead of dragging the others into
 *     a refetch.
 *   - A refresh has a 15-second budget, stops at the first batch nobody
 *     answered, and stops at once on a 429 (the site then holds off for the
 *     Retry-After, at least 5 minutes, at most an hour).
 *   - Visitors never wait once a copy exists: an expired calendar is served
 *     from its last copy while a one-off WP-cron job (ds_calendar_refresh)
 *     refreshes it. Only the first load, a copy older than six hours, or an
 *     editor's "Refresh LeagueApps listings" fetches inline.
 *   - One refresh at a time per site set, through an atomic database lock.
 *   - Past months are pruned before anything is stored or sent to the page.
 *
 * Every refresh that calls LeagueApps is one line in the shared "Feed activity"
 * ledger (Settings > DS Toolkit > LeagueApps) with the real request count.
 *
 * LeagueApps schedule epochs are Eastern wall-clock: they are formatted in
 * America/New_York on purpose, whatever the site's timezone.
 */
class DS_Calendar_Data {

	const TTL           = HOUR_IN_SECONDS;        // a schedule and the merged calendar stay fresh this long
	const STALE_TTL     = 7 * DAY_IN_SECONDS;     // last-good copies
	const NOT_FOUND_TTL = 6 * HOUR_IN_SECONDS;    // "Schedule Not Found" is not asked again for this long
	const FAIL_TTL      = 5 * MINUTE_IN_SECONDS;
	const FAIL_TTL_MAX  = HOUR_IN_SECONDS;
	const FLUSH_MIN_AGE = 10 * MINUTE_IN_SECONDS; // a manual refresh never refetches anything younger
	const MAX_AGE       = 6 * HOUR_IN_SECONDS;    // a copy older than this is refreshed inline, not in the background
	const LOCK_TTL      = 60;
	const BUDGET        = 15;                     // seconds of LeagueApps calls per refresh
	const TIMEOUT       = 8;
	const BATCH         = 8;
	const MAX_PROGRAMS  = 150;
	const MAX_EVENTS    = 3000;
	const MAX_SPAN_DAYS = 31;
	const TZ            = 'America/New_York';

	/** Saved LeagueApps sites (Settings > DS Toolkit > LeagueApps). */
	public static function sites() {
		return class_exists( 'DS_Programs_Data' ) ? DS_Programs_Data::configured_sites() : array();
	}

	public static function site_options() {
		$out = array();
		foreach ( self::sites() as $s ) {
			$out[ $s['site_id'] ] = '' !== $s['label'] ? $s['label'] . ' (' . $s['site_id'] . ')' : $s['site_id'];
		}
		return $out;
	}

	/** Teams / event types seen by the last refresh, for the builder pickers (never calls the API). */
	public static function known( $what ) {
		$k = get_transient( 'ds_calendar_known' );
		return ( is_array( $k ) && isset( $k[ $what ] ) && is_array( $k[ $what ] ) ) ? $k[ $what ] : array();
	}

	/**
	 * The merged calendar for these site ids (empty = every saved site).
	 *
	 * @return array( 'events', 'teams', 'types', 'stale' => bool, 'errors' => string[], 'no_sites' => bool, 'fetched' => int )
	 */
	public static function get( array $site_ids = array() ) {
		list( $sites, $ids ) = self::resolve( $site_ids );
		if ( ! $sites ) {
			return self::pack( array(), array( 'no_sites' => true ) );
		}
		list( $key, $stale_key ) = self::keys( $ids );

		$hit = get_transient( $key );
		if ( is_array( $hit ) && isset( $hit['events'] ) ) {
			return $hit;
		}
		$stale   = get_transient( $stale_key );
		$flushed = false !== get_transient( 'ds_calendar_flushed' );
		if ( ! $flushed && is_array( $stale ) && isset( $stale['events'] ) && time() - (int) ( $stale['fetched'] ?? 0 ) < self::MAX_AGE ) {
			self::queue( $ids );
			return $stale; // a recent copy: fine to show while the refresh runs
		}
		return self::refresh_now( $sites, $ids );
	}

	/** WP-cron handler for ds_calendar_refresh: refresh unless a fresh merged copy already exists. */
	public static function refresh( $site_ids = array() ) {
		list( $sites, $ids ) = self::resolve( (array) $site_ids );
		if ( ! $sites ) {
			return;
		}
		list( $key ) = self::keys( $ids );
		if ( is_array( get_transient( $key ) ) ) {
			return;
		}
		self::refresh_now( $sites, $ids );
	}

	/**
	 * "Refresh LeagueApps listings" (and any Programs flush): drop the merged copies so the
	 * next view refreshes inline, and make schedules older than FLUSH_MIN_AGE due again
	 * (including "Schedule Not Found" ones, so a schedule just added in LeagueApps appears).
	 */
	public static function flush() {
		set_transient( 'ds_calendar_flushed', time(), MINUTE_IN_SECONDS );
		$keys = get_transient( 'ds_calendar_keys' );
		foreach ( is_array( $keys ) ? $keys : array() as $k ) {
			delete_transient( $k );
		}
	}

	/* ------------------------------------------------------------------ refresh */

	private static function resolve( array $site_ids ) {
		$sites = self::sites();
		if ( $site_ids ) {
			$want  = array_map( 'strval', $site_ids );
			$sites = array_values( array_filter( $sites, function ( $s ) use ( $want ) { return in_array( (string) $s['site_id'], $want, true ); } ) );
		}
		$ids = $sites ? array_map( 'strval', wp_list_pluck( $sites, 'site_id' ) ) : array();
		sort( $ids );
		return array( $sites, $ids );
	}

	private static function keys( array $ids ) {
		$h = md5( wp_json_encode( $ids ) );
		return array( 'ds_calendar_' . $h, 'ds_calendar_' . $h . '_stale', 'ds_calendar_lock_' . $h );
	}

	/** One background refresh at a time per site set. */
	private static function queue( array $ids ) {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( 'ds_calendar_refresh', array( $ids ) ) ) {
			wp_schedule_single_event( time(), 'ds_calendar_refresh', array( $ids ) );
		}
	}

	/**
	 * Atomic lock: INSERT IGNORE on the options table either creates the row (we own the
	 * lock) or does nothing. A lock older than LOCK_TTL (a request that died) is taken over
	 * with a compare-and-swap UPDATE, so two takers cannot both win.
	 */
	private static function lock( $name ) {
		global $wpdb;
		$now = time();
		if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) $now ) ) ) {
			return true;
		}
		$held = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( $now - $held > self::LOCK_TTL ) {
			return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) $now, $name, (string) $held ) );
		}
		return false;
	}

	private static function unlock( $name ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	}

	/** Fetch what is due, under the lock, and rebuild the merged calendar. */
	private static function refresh_now( array $sites, array $ids ) {
		list( $key, $stale_key, $lock ) = self::keys( $ids );
		if ( ! self::lock( $lock ) ) {
			// Someone else is refreshing. A first-ever load waits briefly for their result.
			for ( $i = 0; $i < 6 && ! is_array( get_transient( $stale_key ) ); $i++ ) {
				usleep( 500000 );
			}
			return self::serve_stale( $stale_key, array() );
		}
		self::remember_key( $key );

		$t0       = microtime( true );
		$deadline = $t0 + self::BUDGET;
		$now      = time();
		$flushed  = false !== get_transient( 'ds_calendar_flushed' );
		$why      = $flushed ? 'calendar flush' : ( is_array( get_transient( $stale_key ) ) ? 'calendar expired' : 'calendar first' );

		$feed   = DS_Programs_Data::raw( $sites );
		$listed = array(); // site id => program ids LeagueApps listed
		foreach ( (array) $feed['rows'] as $p ) {
			$sid = (string) ( $p['_site'] ?? '' );
			$pid = preg_replace( '/\D+/', '', (string) ( $p['programId'] ?? '' ) );
			if ( '' !== $pid && '' !== $sid ) {
				$listed[ $sid ][ $pid ] = true;
			}
		}

		$stores = array();
		$jobs   = array();
		foreach ( $sites as $site ) {
			$sid   = (string) $site['site_id'];
			$store = self::store_get( $sid );
			if ( isset( $listed[ $sid ] ) ) {
				// Programs LeagueApps no longer lists are gone (season over, deleted).
				$store['p'] = array_intersect_key( $store['p'], $listed[ $sid ] );
				foreach ( array_keys( $listed[ $sid ] ) as $pid ) {
					if ( ! isset( $store['p'][ $pid ] ) ) {
						$store['p'][ $pid ] = array( 't' => 0, 'items' => array(), 'nf' => 0, 'fails' => 0, 'retry' => 0 );
					}
				}
			}
			$stores[ $sid ] = $store;
			if ( (int) $store['hold'] > $now ) {
				continue; // this site was rate limited: no calls until the hold ends
			}
			foreach ( $store['p'] as $pid => $e ) {
				if ( self::due( $e, $now, $flushed ) ) {
					$jobs[ $sid . ':' . $pid ] = self::api_url( $site, $pid );
				}
			}
		}
		$jobs = array_slice( $jobs, 0, self::MAX_PROGRAMS, true );

		$requests  = 0;
		$errors    = array();
		$throttled = false;
		$cut_ms    = self::month_start_ms();
		foreach ( array_chunk( $jobs, self::BATCH, true ) as $batch ) {
			if ( microtime( true ) > $deadline ) {
				$errors['budget'] = __( 'stopped at the time budget; the rest refresh next time', 'ds-toolkit' );
				break;
			}
			$requests += count( $batch );
			$answered  = 0;
			$retry     = 0;
			foreach ( self::http_multi( $batch, $deadline ) as $jk => $r ) {
				list( $sid, $pid ) = explode( ':', (string) $jk, 2 );
				if ( ! isset( $stores[ $sid ]['p'][ $pid ] ) ) {
					continue;
				}
				$e    = $stores[ $sid ]['p'][ $pid ];
				$code = (int) ( $r['code'] ?? 0 );
				if ( 200 === $code ) {
					$json  = json_decode( (string) ( $r['body'] ?? '' ), true );
					$games = ( is_array( $json ) && isset( $json['games'] ) && is_array( $json['games'] ) ) ? $json['games'] : array();
					$e     = array( 't' => $now, 'items' => self::prune( $games, $cut_ms ), 'nf' => 0, 'fails' => 0, 'retry' => 0 );
					$answered++;
				} elseif ( 404 === $code ) {
					$e = array( 't' => $now, 'items' => array(), 'nf' => 1, 'fails' => 0, 'retry' => 0 );
					$answered++;
				} elseif ( 429 === $code ) {
					$throttled = true;
					$retry     = max( $retry, (int) ( $r['retry_after'] ?? 0 ) );
				} else {
					// Keep the last good schedule; retry this one program on an escalating timer.
					$e['fails'] = (int) $e['fails'] + 1;
					$e['retry'] = $now + min( self::FAIL_TTL * ( 2 ** min( 6, $e['fails'] - 1 ) ), self::FAIL_TTL_MAX );
					if ( 403 === $code ) {
						$errors['403'] = __( 'LeagueApps rejected the API key (403)', 'ds-toolkit' );
					} else {
						$errors[ 'http' . $code ] = 0 === $code ? __( 'LeagueApps did not answer', 'ds-toolkit' ) : 'HTTP ' . $code;
					}
				}
				$stores[ $sid ]['p'][ $pid ] = $e;
			}
			if ( $throttled ) {
				$hold = $now + min( max( $retry, self::FAIL_TTL ), self::FAIL_TTL_MAX );
				foreach ( array_keys( $stores ) as $sid ) {
					$stores[ $sid ]['hold'] = max( (int) $stores[ $sid ]['hold'], $hold );
				}
				break; // never keep calling into a 429
			}
			if ( 0 === $answered ) {
				break; // nobody answered this batch: LeagueApps is down or slow, leave it alone
			}
		}
		foreach ( $stores as $sid => $st ) {
			self::store_put( $sid, $st );
		}
		if ( $throttled ) {
			$errors['429'] = __( 'LeagueApps is rate limiting requests (429)', 'ds-toolkit' );
		}
		$errors = array_values( $errors );

		if ( $requests > 0 ) {
			DS_Programs_Data::log_fetch( array(
				'site'  => implode( ',', $ids ),
				'ok'    => ! $errors,
				'code'  => $throttled ? 429 : ( $errors ? 0 : 200 ),
				'tries' => $requests,
				'ms'    => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
				'rows'  => 0,
				'err'   => implode( '; ', $errors ),
				'why'   => $why,
			) );
		}

		// Merge every stored schedule (a program that failed keeps its last good copy).
		$items = array();
		foreach ( $stores as $st ) {
			foreach ( $st['p'] as $e ) {
				foreach ( (array) $e['items'] as $it ) {
					$items[] = $it;
				}
			}
		}
		$norm = self::normalise( $items );

		// Camps, clinics, classes, leagues, tournaments and events with no schedule of their own
		// come from their PROGRAM dates (already in the feed: no extra request). A program whose
		// schedule has items is skipped here, so its games are never doubled.
		$has = array();
		foreach ( $stores as $sid => $st ) {
			foreach ( $st['p'] as $pid => $e ) {
				if ( ! empty( $e['items'] ) ) {
					$has[ $sid . ':' . $pid ] = true;
				}
			}
		}
		$prog           = self::program_events( (array) $feed['rows'], $has );
		$norm['events'] = array_merge( $norm['events'], $prog['events'] );
		usort( $norm['events'], function ( $a, $b ) { return strcmp( $a['d'] . $a['sort'] . $a['title'], $b['d'] . $b['sort'] . $b['title'] ); } );
		$norm['types']    = array_values( array_unique( array_merge( $norm['types'], $prog['types'] ) ) );
		$norm['programs'] = $prog['groups'];
		$norm['checks']   = $prog['checks'];
		$cut            = self::month_start_day();
		$norm['events'] = array_slice( array_values( array_filter( $norm['events'], function ( $e ) use ( $cut ) { return $e['d'] >= $cut; } ) ), 0, self::MAX_EVENTS );
		$out            = self::pack( $norm, array( 'errors' => $errors, 'stale' => ! empty( $feed['stale'] ), 'fetched' => $now ) );

		set_transient( $key, $out, $errors ? self::FAIL_TTL : self::TTL );
		set_transient( $stale_key, $out, self::STALE_TTL );
		set_transient( 'ds_calendar_known', array( 'teams' => $out['teams'], 'types' => $out['types'] ), self::STALE_TTL );
		self::unlock( $lock );
		return $out;
	}

	/** Is this stored program due a fetch? */
	private static function due( array $e, $now, $flushed ) {
		$age = $now - (int) $e['t'];
		if ( (int) $e['retry'] > $now ) {
			return false; // backing off after a failure
		}
		if ( (int) $e['fails'] > 0 ) {
			return true; // its back-off has ended
		}
		if ( $flushed && $age >= self::FLUSH_MIN_AGE ) {
			return true;
		}
		return $age >= ( empty( $e['nf'] ) ? self::TTL : self::NOT_FOUND_TTL );
	}

	private static function store_get( $sid ) {
		$s = get_transient( 'ds_calendar_store_' . $sid );
		$s = is_array( $s ) ? $s : array();
		return array( 'p' => ( isset( $s['p'] ) && is_array( $s['p'] ) ) ? $s['p'] : array(), 'hold' => (int) ( $s['hold'] ?? 0 ) );
	}

	private static function store_put( $sid, array $s ) {
		set_transient( 'ds_calendar_store_' . $sid, $s, self::STALE_TTL );
	}

	private static function api_url( array $site, $pid ) {
		return DS_Programs_Data::API_BASE . rawurlencode( $site['site_id'] ) . '/programs/' . rawurlencode( $pid ) . '/schedule?x-api-key=' . rawurlencode( $site['api_key'] );
	}

	/** First day of the current month, Eastern, as Y-m-d and as a LeagueApps millisecond epoch. */
	private static function month_start_day() {
		return ( new DateTime( 'first day of this month', new DateTimeZone( self::TZ ) ) )->format( 'Y-m-d' );
	}

	private static function month_start_ms() {
		return ( new DateTime( self::month_start_day() . ' 00:00:00', new DateTimeZone( self::TZ ) ) )->getTimestamp() * 1000;
	}

	/** Drop schedule items that ended before this month: nobody can navigate to them. */
	private static function prune( array $items, $cut_ms ) {
		$out = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$game = 'STRUCTURED_GAME' === ( $it['scheduleItemType'] ?? '' );
			$end  = $game ? ( $it['endTime'] ?? $it['startTime'] ?? null ) : ( $it['endDate'] ?? $it['startDate'] ?? null );
			if ( is_numeric( $end ) && $end >= $cut_ms ) {
				$out[] = $it;
			}
		}
		return $out;
	}

	/**
	 * GET several URLs at once, within $deadline. Returns key => array( code, body, retry_after ).
	 *
	 * Parallel requests go through Requests::request_multiple, which bypasses the WP HTTP API,
	 * so a site that blocks external requests, uses a proxy, or filters pre_http_request gets
	 * the one-at-a-time wp_remote_get() path instead. `ds_calendar_http` short-circuits (tests).
	 */
	private static function http_multi( array $urls, $deadline ) {
		$pre = apply_filters( 'ds_calendar_http', null, $urls );
		if ( is_array( $pre ) ) {
			return $pre;
		}
		$ua    = 'ds-toolkit-calendar/' . ( defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '0' ) . ' (+' . home_url( '/' ) . ')';
		$out   = array();
		$cls   = class_exists( '\WpOrg\Requests\Requests' ) ? '\WpOrg\Requests\Requests' : ( class_exists( 'Requests' ) ? 'Requests' : '' );
		$wp_ok = ! ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) && ! defined( 'WP_PROXY_HOST' ) && ! has_filter( 'pre_http_request' );
		if ( $cls && $wp_ok && count( $urls ) > 1 ) {
			$reqs = array();
			foreach ( $urls as $k => $u ) {
				$reqs[ $k ] = array( 'url' => $u, 'type' => 'GET', 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => $ua ) );
			}
			try {
				$timeout = max( 2, min( self::TIMEOUT, (int) floor( $deadline - microtime( true ) ) ) );
				$res     = $cls::request_multiple( $reqs, array( 'timeout' => $timeout, 'connect_timeout' => min( 5, $timeout ) ) );
				foreach ( array_keys( $urls ) as $k ) {
					$r = $res[ $k ] ?? null;
					if ( is_object( $r ) && isset( $r->status_code ) ) {
						$out[ $k ] = array( 'code' => (int) $r->status_code, 'body' => (string) $r->body, 'retry_after' => (int) ( $r->headers['retry-after'] ?? 0 ) );
					} else {
						$out[ $k ] = array( 'code' => 0, 'body' => '' );
					}
				}
				return $out;
			} catch ( \Throwable $e ) {
				$out = array(); // fall through to one at a time
			}
		}
		foreach ( $urls as $k => $u ) {
			$left = (int) floor( $deadline - microtime( true ) );
			if ( $left < 2 ) {
				break;
			}
			$r = wp_remote_get( $u, array( 'timeout' => min( self::TIMEOUT, $left ), 'user-agent' => $ua, 'headers' => array( 'Accept' => 'application/json' ) ) );
			if ( is_wp_error( $r ) ) {
				$out[ $k ] = array( 'code' => 0, 'body' => '' );
				continue;
			}
			$out[ $k ] = array( 'code' => (int) wp_remote_retrieve_response_code( $r ), 'body' => wp_remote_retrieve_body( $r ), 'retry_after' => (int) wp_remote_retrieve_header( $r, 'retry-after' ) );
			if ( 429 === $out[ $k ]['code'] ) {
				break;
			}
		}
		return $out;
	}

	private static function pack( array $n, array $extra = array() ) {
		return array_merge( array( 'events' => array(), 'teams' => array(), 'programs' => array(), 'checks' => array(), 'types' => array(), 'stale' => false, 'errors' => array(), 'no_sites' => false, 'fetched' => 0 ), $n, $extra );
	}

	private static function serve_stale( $stale_key, array $errors ) {
		$stale = get_transient( $stale_key );
		if ( is_array( $stale ) && isset( $stale['events'] ) ) {
			$stale['stale']  = true;
			$stale['errors'] = $errors;
			return $stale;
		}
		return self::pack( array(), array( 'errors' => $errors ) );
	}

	private static function remember_key( $key ) {
		$keys = get_transient( 'ds_calendar_keys' );
		$keys = is_array( $keys ) ? $keys : array();
		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			set_transient( 'ds_calendar_keys', $keys, self::STALE_TTL );
		}
	}

	/* ------------------------------------------------------------------ program dates */

	const MAX_SESSIONS = 40; // per program

	/** LeagueApps program type -> the label a family reads. Club teams are left to schedules. */
	private static function type_label( $raw ) {
		$map = array( 'TOURNAMENT' => 'Tournament', 'LEAGUE' => 'League', 'CLASS' => 'Class', 'CAMP' => 'Camp', 'CLINIC' => 'Clinic', 'EVENT' => 'Event' );
		$raw = strtoupper( (string) $raw );
		return $map[ $raw ] ?? '';
	}

	private static function link_url( $u ) {
		$u = trim( (string) $u );
		if ( '' === $u ) {
			return '';
		}
		if ( 0 === strpos( $u, '//' ) ) {
			$u = 'https:' . $u;
		}
		return (string) esc_url_raw( $u, array( 'http', 'https' ) );
	}

	/** Registration open now, by the same rules the Programs table uses. */
	private static function reg_open( array $row, $now ) {
		$last = is_numeric( $row['endTime'] ?? null ) ? $row['endTime'] : ( $row['startTime'] ?? null );
		if ( is_numeric( $last ) && (int) floor( $last / 1000 ) + DAY_IN_SECONDS < $now ) {
			return false; // over: nothing left to register for
		}
		$stat = strtoupper( (string) ( $row['registrationStatus'] ?? '' ) );
		if ( in_array( $stat, array( 'CANCELED', 'CANCELLED', 'CLOSED', 'SOLD_OUT' ), true ) ) {
			return false;
		}
		if ( is_numeric( $row['endRegistrationTime'] ?? null ) && (int) floor( $row['endRegistrationTime'] / 1000 ) < $now ) {
			return false;
		}
		if ( is_numeric( $row['publicRegistrationTime'] ?? null ) && (int) floor( $row['publicRegistrationTime'] / 1000 ) > $now ) {
			return false;
		}
		if ( isset( $row['remainingFreeAgents'], $row['enforceMaxFreeAgentsCount'] ) && $row['enforceMaxFreeAgentsCount'] && (int) $row['remainingFreeAgents'] <= 0 ) {
			return false;
		}
		return '' !== self::link_url( $row['registerUrlHtml'] ?? '' ) || '' !== self::link_url( $row['programUrlHtml'] ?? '' );
	}

	/** "5:30PM to 7:00PM" -> "5:30 PM – 7 PM"; anything else is shown as LeagueApps typed it. */
	private static function times_text( $raw ) {
		$raw = trim( wp_strip_all_tags( (string) $raw ) );
		$fmt = function ( $h, $m, $ap ) { return (int) $h . ( '00' === $m || '' === $m ? '' : ':' . $m ) . ' ' . strtoupper( $ap ); };
		if ( preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*([ap]m)\s*(?:to|-|–)\s*(\d{1,2})(?::(\d{2}))?\s*([ap]m)$/i', $raw, $m ) ) {
			return $fmt( $m[1], $m[2], $m[3] ) . ' – ' . $fmt( $m[4], $m[5], $m[6] );
		}
		if ( preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*([ap]m)$/i', $raw, $m ) ) {
			return $fmt( $m[1], $m[2], $m[3] );
		}
		return $raw;
	}

	/** "Sun,Sat" -> array( 0, 6 ) (PHP 'w' weekday numbers). */
	private static function weekdays( $raw ) {
		$map = array( 'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6 );
		$out = array();
		foreach ( preg_split( '/[\s,]+/', strtolower( (string) $raw ) ) as $d ) {
			$k = substr( $d, 0, 3 );
			if ( isset( $map[ $k ] ) ) {
				$out[ $map[ $k ] ] = true;
			}
		}
		return array_keys( $out );
	}

	/** A single date written in a program's title ("10/13/26", "Oct 16th, 2026", "November 15th"), as Y-m-d, or ''. */
	private static function title_date( $title, DateTime $start ) {
		$found = array();
		if ( preg_match_all( '~\b(\d{1,2})/(\d{1,2})(?:/(\d{2,4}))?\b~', $title, $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $m ) {
				$y = isset( $m[3] ) && '' !== $m[3] ? ( strlen( $m[3] ) === 2 ? 2000 + (int) $m[3] : (int) $m[3] ) : (int) $start->format( 'Y' );
				if ( checkdate( (int) $m[1], (int) $m[2], $y ) ) {
					$found[] = sprintf( '%04d-%02d-%02d', $y, $m[1], $m[2] );
				}
			}
		}
		if ( preg_match_all( '~\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*\.?\s+(\d{1,2})(?:st|nd|rd|th)?\b(?:,?\s*(\d{4}))?~i', $title, $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $m ) {
				$mon = (int) date( 'n', strtotime( '1 ' . substr( $m[1], 0, 3 ) . ' 2000' ) );
				$y   = isset( $m[3] ) && '' !== $m[3] ? (int) $m[3] : (int) $start->format( 'Y' );
				if ( checkdate( $mon, (int) $m[2], $y ) ) {
					$found[] = sprintf( '%04d-%02d-%02d', $y, $mon, $m[2] );
				}
			}
		}
		$found = array_values( array_unique( $found ) );
		return 1 === count( $found ) ? $found[0] : '';
	}

	/** True when a title is only a date ("Oct 16th, 2026"): such a name adds nothing under its parent. */
	private static function title_is_date( $title ) {
		$t = trim( (string) preg_replace( '~\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2}(st|nd|rd|th)?(,?\s*\d{4})?|\b\d{1,2}/\d{1,2}(/\d{2,4})?|\b(Mon|Tue|Wed|Thu|Fri|Sat|Sun)[a-z]*\b~i', '', (string) $title ) );
		return '' === trim( $t, " ,-–" );
	}

	/**
	 * Calendar entries from program dates, for programs without a schedule.
	 *
	 * Shapes seen across the fleet (2026-10-17: Inspire, Absolute, Salt City, Carrier Park, Lamorinda,
	 * DS Storm, LSW) and what each becomes:
	 *   - one date (start = end, or no end) ............................ that day
	 *   - a "rolling session": children of one parent that all carry the parent's END date but start on
	 *     different dates (tryouts / open gyms registered per night) ..... their start date only
	 *   - a range with weekdays (weekly camps, league nights) ............ every listed weekday in range
	 *   - a short range with no weekdays (a weekend) ...................... every day in range
	 *   - a season (5+ weekdays, or no weekdays over more than a week) .... a start and an end marker
	 * Children of one parent on the same day merge into one entry named after the parent, listing the
	 * children (age groups, divisions, sessions) with their own times.
	 */
	public static function program_events( array $rows, array $has_schedule ) {
		$now  = time();
		$cut  = self::month_start_day();
		$keep = array();
		$kids = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) || empty( $r['programId'] ) || '' === self::type_label( $r['type'] ?? '' ) ) {
				continue; // club teams and unknown types: schedules only
			}
			$vis  = (string) ( $r['visibility'] ?? '' );
			$stat = strtoupper( (string) ( $r['registrationStatus'] ?? '' ) );
			if ( ( '' !== $vis && 0 !== strcasecmp( $vis, 'Public' ) ) || in_array( $stat, array( 'CANCELED', 'CANCELLED' ), true ) || 'ARCHIVED' === strtoupper( (string) ( $r['state'] ?? '' ) ) ) {
				continue;
			}
			$r['name']   = wp_strip_all_tags( (string) ( $r['name'] ?? '' ) );
			$sid         = (string) ( $r['_site'] ?? '' );
			$id          = $sid . ':' . preg_replace( '/\D+/', '', (string) $r['programId'] );
			$keep[ $id ] = $r;
			$mid         = preg_replace( '/\D+/', '', (string) ( $r['masterProgramId'] ?? '' ) );
			if ( '' !== $mid && '0' !== $mid ) {
				$kids[ $sid . ':' . $mid ][] = $id;
			}
		}

		// Rolling sessions: within one parent, children that end on the family's last day but start on
		// two or more different dates are one-night registrations, not weekly programs. When some of
		// those children name a single date in their title and others do not ("All Sessions", a
		// drop-in), only the dated ones are sessions: the others keep their weekly run.
		$rolling = array();
		foreach ( $kids as $mid => $ids ) {
			$ends = array();
			foreach ( $ids as $id ) {
				$ends[ $id ] = is_numeric( $keep[ $id ]['endTime'] ?? null ) ? (int) $keep[ $id ]['endTime'] : 0;
			}
			$max  = $ends ? max( $ends ) : 0;
			$cand = array();
			foreach ( $ids as $id ) {
				if ( $max > 0 && $ends[ $id ] === $max && is_numeric( $keep[ $id ]['startTime'] ?? null ) ) {
					$cand[ $id ] = self::dt( (int) $keep[ $id ]['startTime'] )->format( 'Y-m-d' );
				}
			}
			if ( count( array_unique( $cand ) ) < 2 ) {
				continue;
			}
			$dated = array();
			foreach ( array_keys( $cand ) as $id ) {
				if ( '' !== self::title_date( $keep[ $id ]['name'], self::dt( (int) $keep[ $id ]['startTime'] ) ) ) {
					$dated[ $id ] = true;
				}
			}
			foreach ( array_keys( $cand ) as $id ) {
				if ( ! $dated || isset( $dated[ $id ] ) ) {
					$rolling[ $id ] = true;
				}
			}
		}

		$groups = array();
		$checks = array();
		$types  = array();
		$span_of = array(); // real program (site:parent-or-own id) => [first, last] day it occupies
		foreach ( $keep as $id => $r ) {
			if ( ! empty( $kids[ $id ] ) ) {
				continue; // a parent whose children are listed: the children carry its dates
			}
			list( $sid ) = explode( ':', $id );
			$mid    = preg_replace( '/\D+/', '', (string) ( $r['masterProgramId'] ?? '' ) );
			$pkey   = ( '' !== $mid && '0' !== $mid ) ? $sid . ':' . $mid : '';
			if ( isset( $has_schedule[ $id ] ) || ( '' !== $pkey && isset( $has_schedule[ $pkey ] ) ) || ! is_numeric( $r['startTime'] ?? null ) ) {
				continue; // a real schedule (its own or its parent's) wins; no start date, nothing to place
			}
			$parent = ( '' !== $pkey && isset( $keep[ $pkey ] ) ) ? $keep[ $pkey ] : null;
			$gid    = $parent ? $pkey : $id;
			$title  = $parent ? $parent['name'] : $r['name'];
			$child  = $parent ? $r['name'] : '';
			if ( '' === $title ) {
				continue;
			}
			$label           = self::type_label( $r['type'] ?? '' );
			$types[ $label ] = 1;

			$start = self::dt( (int) $r['startTime'] );
			$end   = ( is_numeric( $r['endTime'] ?? null ) && $r['endTime'] >= $r['startTime'] ) ? self::dt( (int) $r['endTime'] ) : null;
			$days  = self::weekdays( $r['scheduleDays'] ?? '' );
			$time  = self::times_text( $r['scheduleTimes'] ?? '' );
			$sd    = $start->format( 'Y-m-d' );
			$ed    = $end ? $end->format( 'Y-m-d' ) : $sd;
			$span  = (int) round( ( strtotime( $ed ) - strtotime( $sd ) ) / DAY_IN_SECONDS );
			// Venue: the parent's when it has one, so divisions of one program at different fields stay
			// one entry; otherwise the program's own.
			$ploc  = $parent ? wp_strip_all_tags( (string) ( $parent['location'] ?? '' ) ) : '';
			$loc   = '' !== $ploc ? $ploc : wp_strip_all_tags( (string) ( $r['location'] ?? '' ) );
			// Merge key venue: the parent's; a parent with no venue keys by itself, so its divisions
			// (some with a field, some without) stay one entry.
			$kloc  = '' !== $ploc ? $ploc : ( $parent ? '#' . $gid : $loc );

			$td = self::title_date( $r['name'], $start );
			if ( '' !== $td && $td !== $sd && ( $ed === $sd || isset( $rolling[ $id ] ) ) ) {
				$checks[] = sprintf( '"%s": the title says %s, LeagueApps start date is %s', $r['name'], $td, $sd );
			}

			$occ = array(); // Y-m-d => marker ('' | 'start' | 'end')
			if ( $ed === $sd || isset( $rolling[ $id ] ) ) {
				$occ[ $sd ] = '';
			} elseif ( count( $days ) >= 5 || ( ! $days && $span > 7 ) ) {
				$occ[ $sd ] = 'start';
				$occ[ $ed ] = 'end';
			} else {
				// Weekly (or every day of a short range), counted from this month, not from the start:
				// a year-long class must still show this month. Too many to list = a season.
				$c = clone $start;
				$c->setTime( 0, 0 );
				if ( $c->format( 'Y-m-d' ) < $cut ) {
					$c = new DateTime( $cut . ' 00:00:00', new DateTimeZone( self::TZ ) );
				}
				$list = array();
				for ( $i = 0; $i <= 800 && $c->format( 'Y-m-d' ) <= $ed; $i++ ) {
					if ( ! $days || in_array( (int) $c->format( 'w' ), $days, true ) ) {
						$list[] = $c->format( 'Y-m-d' );
					}
					$c->modify( '+1 day' );
				}
				if ( count( $list ) > self::MAX_SESSIONS ) {
					$occ[ $sd ] = 'start';
					$occ[ $ed ] = 'end';
				} else {
					foreach ( $list as $d ) {
						$occ[ $d ] = '';
					}
				}
			}

			$open = self::reg_open( $r, $now );
			$reg  = $parent ? self::link_url( $parent['programUrlHtml'] ?? '' ) : self::link_url( $r['registerUrlHtml'] ?? '' );
			$reg  = '' !== $reg ? $reg : self::link_url( $r['programUrlHtml'] ?? '' );
			$more = self::link_url( $parent ? ( $parent['programUrlHtml'] ?? '' ) : ( $r['programUrlHtml'] ?? '' ) );
			// One entry per program name + venue per day: a club that lists the same tournament on two
			// of its LeagueApps sites gets it once, while same-named events at different venues stay apart.
			$tkey = strtolower( $title ) . '|' . strtolower( $kloc );
			foreach ( $occ as $day => $mark ) {
				$span_of[ $gid ] = isset( $span_of[ $gid ] ) ? array( min( $span_of[ $gid ][0], $day ), max( $span_of[ $gid ][1], $day ) ) : array( $day, $day );
				$key             = $tkey . '|' . $day . '|' . $mark;
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'd'     => $day,
						'title' => $title,
						'type'  => $label,
						'label' => '',
						'kind'  => 'prog',
						'grp'   => $title,
						'mark'  => $mark,
						'loc'   => $loc,
						'lat'   => null,
						'lng'   => null,
						'url'   => '',
						'more'  => $more,
						'reg'   => '',
						'note'  => '',
						'teams' => array(),
						'sort'  => self::sort_time( $time ),
						'gids'  => array(),
						'tkey'  => $tkey,
					);
				}
				$g =& $groups[ $key ];
				$g['gids'][ $gid ] = true;
				if ( '' === $g['loc'] && '' !== $loc ) {
					$g['loc'] = $loc;
				}
				if ( $open && '' === $g['reg'] ) {
					$g['reg'] = $reg;
				}
				$nm = ( '' !== $child && ! self::title_is_date( $child ) && $child !== $title ) ? $child : '';
				// Unnamed rows still carry their time; "\0" keys mark them so they never show as a name.
				$g['teams'][ '' !== $nm ? $nm : "\0" . count( $g['teams'] ) ] = ( 'end' === $mark ) ? '' : $time;
				unset( $g );
			}

			// Registration deadline, offered as its own optional entry. LeagueApps closes at midnight
			// AFTER the last day, so a 00:00 close belongs to the day before.
			if ( is_numeric( $r['endRegistrationTime'] ?? null ) && (int) floor( $r['endRegistrationTime'] / 1000 ) >= $now ) {
				$close = self::dt( (int) $r['endRegistrationTime'] );
				if ( '00:00' === $close->format( 'H:i' ) ) {
					$close->modify( '-1 minute' );
				}
				$dd  = $close->format( 'Y-m-d' );
				$key = $tkey . '|' . $dd . '|deadline';
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array( 'd' => $dd, 'title' => $title, 'type' => 'Deadline', 'label' => 'Registration closes', 'kind' => 'deadline', 'grp' => $title, 'mark' => '', 'loc' => '', 'lat' => null, 'lng' => null, 'url' => '', 'more' => $more, 'reg' => $open ? $reg : '', 'note' => '', 'teams' => array(), 'sort' => '23:59', 'gids' => array( $gid => true ), 'tkey' => $tkey );
				}
			}
		}

		// A season marker on a day that already has a session of the same program says nothing new.
		$session = array();
		foreach ( $groups as $g ) {
			if ( 'prog' === $g['kind'] && '' === $g['mark'] ) {
				$session[ $g['tkey'] . '|' . $g['d'] ] = true;
			}
		}

		$events = array();
		$names  = array();
		foreach ( $groups as $g ) {
			if ( 'prog' === $g['kind'] && '' !== $g['mark'] && isset( $session[ $g['tkey'] . '|' . $g['d'] ] ) ) {
				continue;
			}
			$times      = array_values( array_unique( array_filter( array_values( $g['teams'] ), 'strlen' ) ) );
			$g['time']  = 1 === count( $times ) ? $times[0] : '';
			$g['times'] = 1 < count( $times );
			$list       = array();
			foreach ( $g['teams'] as $n => $t ) {
				$n = (string) $n;
				if ( '' !== trim( $n ) && "\0" !== $n[0] ) {
					$list[] = array( 'n' => $n, 't' => $t );
				}
			}
			usort( $list, function ( $a, $b ) { return strnatcasecmp( $a['n'], $b['n'] ); } );
			$g['teams'] = $list;
			// First / last day of the REAL program (not of every program sharing its name).
			$g['first'] = false;
			$g['last']  = false;
			if ( 'prog' === $g['kind'] ) {
				foreach ( array_keys( $g['gids'] ) as $gid ) {
					$g['first'] = $g['first'] || $g['d'] === $span_of[ $gid ][0];
					$g['last']  = $g['last'] || $g['d'] === $span_of[ $gid ][1];
				}
			}
			if ( $g['d'] >= $cut ) {
				$names[ $g['grp'] ] = true;
			}
			unset( $g['gids'], $g['tkey'] );
			$events[] = $g;
		}
		$programs = array_keys( $names );
		natcasesort( $programs );
		return array( 'events' => $events, 'types' => array_keys( $types ), 'groups' => array_values( $programs ), 'checks' => array_slice( $checks, 0, 10 ) );
	}

	private static function sort_time( $time ) {
		return preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)/', (string) $time, $m ) ? sprintf( '%02d:%02d', ( (int) $m[1] % 12 ) + ( 'PM' === $m[3] ? 12 : 0 ), (int) ( $m[2] ?? 0 ) ) : '00:01';
	}

	/**
	 * Apply one module's program-date settings to a calendar (pure, so it is tested without BB).
	 *
	 * @param array $data  get() output.
	 * @param array $o     show (bool), types (labels; empty = all), mode (sessions|first|startend), deadlines (bool), register (bool).
	 */
	public static function apply_program_settings( array $data, array $o ) {
		$show  = ! empty( $o['show'] );
		$types = isset( $o['types'] ) ? (array) $o['types'] : array();
		$mode  = (string) ( $o['mode'] ?? 'sessions' );
		$mode  = in_array( $mode, array( 'sessions', 'first', 'startend' ), true ) ? $mode : 'sessions';
		$out   = array();
		foreach ( $data['events'] as $e ) {
			$kind = $e['kind'] ?? '';
			if ( 'prog' === $kind ) {
				if ( ! $show || ( $types && ! in_array( $e['type'], $types, true ) ) ) {
					continue;
				}
				if ( 'first' === $mode && empty( $e['first'] ) ) {
					continue;
				}
				if ( 'startend' === $mode && empty( $e['first'] ) && empty( $e['last'] ) && '' === ( $e['mark'] ?? '' ) ) {
					continue;
				}
			} elseif ( 'deadline' === $kind ) {
				if ( ! $show || empty( $o['deadlines'] ) ) {
					continue;
				}
			}
			if ( empty( $o['register'] ) ) {
				$e['reg'] = '';
			}
			$out[] = $e;
		}
		$data['events'] = $out;
		// The visitor dropdown lists only programs that still have something on this calendar.
		$names = array();
		foreach ( $out as $e ) {
			if ( in_array( $e['kind'] ?? '', array( 'prog', 'deadline' ), true ) ) {
				$names[ $e['grp'] ] = true;
			}
		}
		$names = array_keys( $names );
		natcasesort( $names );
		$data['programs'] = array_values( $names );
		return $data;
	}

	/* ------------------------------------------------------------------ normalise */

	private static function dt( $ms ) {
		$d = new DateTime( '@' . (int) floor( $ms / 1000 ) );
		$d->setTimezone( new DateTimeZone( self::TZ ) );
		return $d;
	}

	private static function clock( DateTime $d ) {
		return '00' === $d->format( 'i' ) ? $d->format( 'g A' ) : $d->format( 'g:i A' );
	}

	/** Only http(s) links, never script or data URLs. */
	private static function link( $desc ) {
		if ( preg_match( '~https?://[^\s<>"\']+~i', $desc, $m ) ) {
			$u = esc_url_raw( rtrim( $m[0], '.,);' ), array( 'http', 'https' ) );
			return array( $u, trim( wp_strip_all_tags( str_replace( $m[0], '', $desc ) ) ) );
		}
		return array( '', trim( wp_strip_all_tags( $desc ) ) );
	}

	/**
	 * Schedule items -> calendar events. One occurrence per day per item, then
	 * occurrences that are the same event on the same day at the same place are
	 * merged (title compared without a trailing "*" or "(...)"), because
	 * LeagueApps often holds one all-day AND one timed entry per team for the
	 * same tournament day; the timed one wins for that team.
	 *
	 * All day = starts at midnight and ends at midnight or 23:59 (or has no end).
	 * Games (STRUCTURED_GAME) use startTime/endTime; events use startDate/endDate.
	 */
	public static function normalise( array $items ) {
		$groups = array();
		$teams  = array();
		$types  = array();
		$seen   = array();

		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$is_game = isset( $it['scheduleItemType'] ) && 'STRUCTURED_GAME' === $it['scheduleItemType'];
			$s       = $is_game ? ( $it['startTime'] ?? null ) : ( $it['startDate'] ?? null );
			$e       = $is_game ? ( $it['endTime'] ?? null ) : ( $it['endDate'] ?? null );
			if ( ! is_numeric( $s ) ) {
				continue;
			}
			$uid = $is_game ? 'g' . ( $it['gameId'] ?? '' ) : 'e' . ( $it['eventId'] ?? '' );
			if ( 'g' !== $uid && 'e' !== $uid ) {
				if ( isset( $seen[ $uid ] ) ) {
					continue;
				}
				$seen[ $uid ] = 1;
			}

			$start = self::dt( $s );
			$end   = ( is_numeric( $e ) && $e > $s ) ? self::dt( $e ) : null;
			if ( $end && '23:59' === $end->format( 'H:i' ) && '00:00' === $start->format( 'H:i' ) ) {
				$end = ( clone $end )->modify( '+1 day' )->setTime( 0, 0 ); // 23:59 = the end of that day
			}
			$all_day = '00:00' === $start->format( 'H:i' ) && ( ! $end || '00:00' === $end->format( 'H:i' ) );

			// Days covered: an all-day span runs to the day before its end; a timed span to its end day.
			$days   = array();
			$last   = $end ? ( $all_day ? ( clone $end )->modify( '-1 day' ) : clone $end ) : clone $start;
			$cursor = ( clone $start )->setTime( 0, 0 );
			for ( $i = 0; $i < self::MAX_SPAN_DAYS && $cursor->format( 'Y-m-d' ) <= $last->format( 'Y-m-d' ); $i++ ) {
				$days[] = $cursor->format( 'Y-m-d' );
				$cursor->modify( '+1 day' );
			}
			if ( ! $days ) {
				$days = array( $start->format( 'Y-m-d' ) );
			}
			if ( $all_day ) {
				$time = '';
			} elseif ( $end && $end->format( 'Y-m-d' ) !== $start->format( 'Y-m-d' ) ) {
				$time = $start->format( 'D' ) . ' ' . self::clock( $start ) . ' – ' . $end->format( 'D' ) . ' ' . self::clock( $end );
			} else {
				$time = self::clock( $start ) . ( $end ? ' – ' . self::clock( $end ) : '' );
			}

			if ( $is_game ) {
				$title      = trim( ( $it['team1'] ?? 'TBD' ) . ' vs ' . ( $it['team2'] ?? 'TBD' ) );
				$type       = 'Game';
				$label      = (string) ( $it['typeLabel'] ?? '' );
				$loc        = trim( ( $it['locationName'] ?? '' ) . ( empty( $it['subLocationName'] ) ? '' : ' · ' . $it['subLocationName'] ) );
				$team_names = array_values( array_filter( array( (string) ( $it['team1'] ?? '' ), (string) ( $it['team2'] ?? '' ) ), 'strlen' ) );
				$desc       = (string) ( $it['notes'] ?? '' );
			} else {
				$title      = rtrim( trim( (string) ( $it['title'] ?? '' ) ), '* ' );
				$type       = (string) ( $it['type'] ?? '' );
				$type       = '' === $type ? 'Event' : $type;
				$label      = '';
				$title      = '' === $title ? $type : $title;
				$loc        = trim( ( $it['location'] ?? '' ) . ( empty( $it['subLocationName'] ) ? '' : ' · ' . $it['subLocationName'] ) );
				$team_names = array_values( array_filter( array( (string) ( $it['teamName'] ?? '' ) ), 'strlen' ) );
				$desc       = (string) ( $it['description'] ?? '' );
			}
			$title          = wp_strip_all_tags( $title );
			$loc            = wp_strip_all_tags( $loc );
			$label          = wp_strip_all_tags( $label );
			$type           = wp_strip_all_tags( $type );
			$team_names     = array_map( 'wp_strip_all_tags', $team_names );
			$types[ $type ] = 1;
			list( $url, $note ) = self::link( $desc );

			foreach ( $team_names as $tn ) {
				$teams[ $tn ] = 1;
			}

			$base = strtolower( trim( (string) preg_replace( '/\s*\([^)]*\)\s*$/', '', $title ) ) );
			foreach ( $days as $day ) {
				$key = $is_game ? $uid . '|' . $day : $day . '|' . strtolower( $loc ) . '|' . $base;
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'd'     => $day,
						'title' => $title,
						'type'  => $type,
						'label' => $label,
						'loc'   => $loc,
						'lat'   => is_numeric( $it['latitude'] ?? null ) ? (float) $it['latitude'] : null,
						'lng'   => is_numeric( $it['longitude'] ?? null ) ? (float) $it['longitude'] : null,
						'url'   => $url,
						'note'  => $note,
						'teams' => array(),
						'sort'  => $all_day ? '00:00' : $start->format( 'H:i' ),
					);
				}
				$g =& $groups[ $key ];
				if ( strlen( $title ) < strlen( $g['title'] ) ) {
					$g['title'] = $title;
				}
				if ( '' === $g['url'] && '' !== $url ) {
					$g['url'] = $url;
				}
				foreach ( $team_names as $tn ) {
					if ( ! isset( $g['teams'][ $tn ] ) || ( '' === $g['teams'][ $tn ] && '' !== $time ) ) {
						$g['teams'][ $tn ] = $time;
					}
				}
				if ( ! $team_names && '' !== $time ) {
					$g['teams'][''] = $time;
				}
				unset( $g );
			}
		}

		$events = array();
		foreach ( $groups as $g ) {
			$times = array_values( array_unique( array_filter( array_values( $g['teams'] ), 'strlen' ) ) );
			$named = array_filter( array_map( 'strval', array_keys( $g['teams'] ) ), 'strlen' );
			natsort( $named );
			$g['time']  = 1 === count( $times ) ? $times[0] : '';
			$g['times'] = 1 < count( $times );
			$list       = array();
			foreach ( $named as $tn ) {
				$list[] = array( 'n' => $tn, 't' => $g['teams'][ $tn ] );
			}
			$g['teams'] = $list;
			$events[]   = $g;
		}
		usort( $events, function ( $a, $b ) { return strcmp( $a['d'] . $a['sort'] . $a['title'], $b['d'] . $b['sort'] . $b['title'] ); } );

		$team_list = array_map( 'strval', array_keys( $teams ) );
		natsort( $team_list );
		$type_list = array_keys( $types );
		sort( $type_list );

		return array( 'events' => $events, 'teams' => array_values( $team_list ), 'types' => $type_list );
	}
}
