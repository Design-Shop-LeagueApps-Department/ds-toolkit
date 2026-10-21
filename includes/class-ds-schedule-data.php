<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * One LeagueApps program's schedule (a club team's practices and tournaments, a league's
 * games) for the LeagueApps Team Schedule module.
 *
 * The public API has a schedule per PROGRAM only (`/programs/<id>/schedule`), and a team page
 * needs exactly one, so this asks for that one and nothing else (the Calendar's per-site merge
 * would ask about every program the club has). The rules that keep it polite:
 *
 *   - Each program's schedule is stored with its own clock: fresh for an hour, "Schedule Not
 *     Found" remembered for six hours, a failure retried on an escalating timer (5 minutes
 *     doubling to an hour) while the last good copy keeps showing.
 *   - Visitors never wait once a copy exists: an expired copy younger than six hours is served
 *     while a one-off WP-cron job (ds_la_schedule_refresh) refreshes it.
 *   - One fetch at a time per program, through an atomic database lock.
 *   - A 429 holds that program for the Retry-After (at least 5 minutes, at most an hour).
 *   - Every request is one line in the shared "Feed activity" ledger.
 *
 * LeagueApps schedule epochs are Eastern wall-clock: they are formatted in America/New_York
 * on purpose, whatever the site's timezone (same rule as the Calendar).
 */
class DS_Schedule_Data {

	const TTL           = HOUR_IN_SECONDS;
	const KEEP          = 14 * DAY_IN_SECONDS;  // last good copy
	const NOT_FOUND_TTL = 6 * HOUR_IN_SECONDS;
	const FAIL_TTL      = 5 * MINUTE_IN_SECONDS;
	const FAIL_TTL_MAX  = HOUR_IN_SECONDS;
	const MAX_AGE       = 6 * HOUR_IN_SECONDS;  // older than this is refreshed inline, not in the background
	const LOCK_TTL      = 30;
	const TIMEOUT       = 8;
	const MAX_ITEMS     = 1500;
	const TZ            = 'America/New_York';
	const PRE           = 'ds_la_sched_';

	/* ------------------------------------------------------------------ builder picker */

	/**
	 * "site:program" => label, for the builder. Reads only what the Programs listing already
	 * cached (the settings form is built on every request); while the builder is open and nothing
	 * is cached yet, it asks for the listing once (the same cached call every LeagueApps module shares).
	 */
	public static function program_options() {
		$rows = class_exists( 'DS_Programs_Data' ) ? DS_Programs_Data::cached_rows() : array();
		if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() && class_exists( 'DS_Programs_Data' ) ) {
			// In the builder, a saved site nobody has listed yet (no Programs or Calendar module shows
			// it) is listed once, through the shared cache, so its teams appear in the picker.
			$have = array_flip( array_map( 'strval', wp_list_pluck( $rows, '_site' ) ) );
			foreach ( DS_Programs_Data::configured_sites() as $site ) {
				if ( ! isset( $have[ (string) $site['site_id'] ] ) ) {
					$feed = DS_Programs_Data::raw( array( $site ) );
					$rows = array_merge( $rows, (array) ( $feed['rows'] ?? array() ) );
				}
			}
		}
		$labels = array();
		foreach ( class_exists( 'DS_Programs_Data' ) ? DS_Programs_Data::configured_sites() : array() as $s ) {
			$labels[ (string) $s['site_id'] ] = (string) $s['label'];
		}
		$multi = count( $labels ) > 1;
		$list  = array();
		foreach ( $rows as $r ) {
			$sid = (string) ( $r['_site'] ?? '' );
			$pid = preg_replace( '/\D+/', '', (string) ( $r['programId'] ?? '' ) );
			if ( '' === $sid || '' === $pid || ! isset( $labels[ $sid ] ) || ! empty( $r['isMaster'] ) && 'false' !== strtolower( (string) $r['isMaster'] ) ) {
				continue;
			}
			$type  = (string) ( $r['type'] ?? '' );
			$label = wp_strip_all_tags( (string) ( $r['name'] ?? $pid ) );
			$label .= ' · ' . ( 'CLUBTEAM' === $type ? __( 'Club team', 'ds-toolkit' ) : ucfirst( strtolower( $type ) ) );
			if ( $multi ) {
				$label .= ' (' . ( '' !== $labels[ $sid ] ? $labels[ $sid ] : $sid ) . ')';
			}
			$list[] = array( 'k' => $sid . ':' . $pid, 'l' => $label, 'o' => 'CLUBTEAM' === $type ? 0 : 1 );
		}
		usort( $list, function ( $a, $b ) { return $a['o'] <=> $b['o'] ?: strnatcasecmp( $a['l'], $b['l'] ); } );
		$out = array();
		foreach ( $list as $i ) {
			$out[ $i['k'] ] = $i['l'];
		}
		return $out;
	}

	/** "57065:1234" => array( site row, program id ) for a saved site, else null. */
	public static function parse_key( $key ) {
		if ( ! preg_match( '/^(\d+):(\d+)$/', (string) $key, $m ) || ! class_exists( 'DS_Programs_Data' ) ) {
			return null;
		}
		foreach ( DS_Programs_Data::configured_sites() as $s ) {
			if ( (string) $s['site_id'] === $m[1] ) {
				return array( $s, $m[2] );
			}
		}
		return null;
	}

	/**
	 * The program's listing row (name, schedule page) from the shared cache. With $site given and
	 * nothing cached for it, the site's listing is fetched once through that same cache (lock,
	 * back-off, ledger), as the Programs and Calendar modules do.
	 */
	public static function program_row( $sid, $pid, $site = null ) {
		$find = function ( array $rows ) use ( $sid, $pid ) {
			foreach ( $rows as $r ) {
				if ( (string) ( $r['_site'] ?? '' ) === (string) $sid && preg_replace( '/\D+/', '', (string) ( $r['programId'] ?? '' ) ) === (string) $pid ) {
					return $r;
				}
			}
			return null;
		};
		if ( ! class_exists( 'DS_Programs_Data' ) ) {
			return array();
		}
		$cached = DS_Programs_Data::cached_rows();
		$row    = $find( $cached );
		if ( null === $row && is_array( $site ) && ! in_array( (string) $sid, array_map( 'strval', wp_list_pluck( $cached, '_site' ) ), true ) ) {
			$feed = DS_Programs_Data::raw( array( $site ) );
			$row  = $find( (array) ( $feed['rows'] ?? array() ) );
		}
		return $row ?: array();
	}

	/** The program's public schedule page on LeagueApps (https only), or ''. */
	public static function schedule_url( array $row ) {
		foreach ( array( 'scheduleUrlHtml', 'programUrlHtml' ) as $f ) {
			$u = trim( (string) ( $row[ $f ] ?? '' ) );
			if ( 0 === strpos( $u, '//' ) ) {
				$u = 'https:' . $u;
			}
			$u = esc_url_raw( $u, array( 'https' ) );
			if ( '' !== $u ) {
				return $u;
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------ fetch */

	/**
	 * Raw schedule items for a program key, cached.
	 *
	 * @return array( 'items' => array, 'ok' => bool, 'nf' => bool, 'fetched' => int )
	 */
	public static function items( $key ) {
		$p = self::parse_key( $key );
		if ( ! $p ) {
			return array( 'items' => array(), 'ok' => false, 'nf' => false, 'fetched' => 0 );
		}
		list( $site, $pid ) = $p;
		$sid = (string) $site['site_id'];
		$e   = self::store_get( $sid, $pid );
		$now = time();

		if ( ! self::due( $e, $now ) ) {
			return self::pack( $e );
		}
		// An editor's "Refresh LeagueApps listings" fetches now; otherwise a recent copy is shown while it refreshes.
		if ( $e['t'] && $now - $e['t'] < self::MAX_AGE && false === get_transient( self::PRE . 'flushed' ) ) {
			self::queue( $key );
			return self::pack( $e ); // a recent copy: fine to show while the refresh runs
		}
		return self::pack( self::fetch( $site, $pid, $e ) );
	}

	/** WP-cron handler for ds_la_schedule_refresh. */
	public static function refresh( $key ) {
		$p = self::parse_key( $key );
		if ( ! $p ) {
			return;
		}
		list( $site, $pid ) = $p;
		$e = self::store_get( (string) $site['site_id'], $pid );
		if ( self::due( $e, time() ) ) {
			self::fetch( $site, $pid, $e );
		}
	}

	/** "Refresh LeagueApps listings": every stored schedule older than 10 minutes is due again. */
	public static function flush() {
		set_transient( self::PRE . 'flushed', time(), MINUTE_IN_SECONDS );
	}

	private static function due( array $e, $now ) {
		if ( (int) $e['retry'] > $now ) {
			return false; // failing or rate limited: wait for the timer, keep the last good copy
		}
		if ( ! $e['t'] ) {
			return true;
		}
		$age = $now - (int) $e['t'];
		if ( false !== get_transient( self::PRE . 'flushed' ) && $age > 10 * MINUTE_IN_SECONDS ) {
			return true;
		}
		return $age >= ( $e['nf'] ? self::NOT_FOUND_TTL : self::TTL );
	}

	private static function fetch( array $site, $pid, array $e ) {
		$sid  = (string) $site['site_id'];
		$lock = self::PRE . 'lock_' . $sid . '_' . $pid;
		if ( ! self::lock( $lock ) ) {
			return $e; // someone else is fetching this one: show what there is
		}
		$t0  = microtime( true );
		$now = time();
		$why = $e['t'] ? 'schedule expired' : 'schedule first';
		$r   = self::http( DS_Programs_Data::API_BASE . rawurlencode( $sid ) . '/programs/' . rawurlencode( $pid ) . '/schedule?x-api-key=' . rawurlencode( $site['api_key'] ) );
		$code = (int) $r['code'];
		$err  = '';
		if ( 200 === $code ) {
			$json = json_decode( (string) $r['body'], true );
			$list = ( is_array( $json ) && isset( $json['games'] ) && is_array( $json['games'] ) ) ? $json['games'] : array();
			$e    = array( 't' => $now, 'items' => array_slice( self::prune( $list, $now ), 0, self::MAX_ITEMS ), 'nf' => 0, 'fails' => 0, 'retry' => 0 );
		} elseif ( 404 === $code ) {
			$e = array( 't' => $now, 'items' => array(), 'nf' => 1, 'fails' => 0, 'retry' => 0 );
		} else {
			$e['fails'] = (int) $e['fails'] + 1;
			$wait       = 429 === $code ? max( (int) $r['retry_after'], self::FAIL_TTL ) : self::FAIL_TTL * ( 2 ** min( 6, $e['fails'] - 1 ) );
			$e['retry'] = $now + min( $wait, self::FAIL_TTL_MAX );
			$err        = 429 === $code ? __( 'LeagueApps is rate limiting requests (429)', 'ds-toolkit' ) : ( 0 === $code ? __( 'LeagueApps did not answer', 'ds-toolkit' ) : 'HTTP ' . $code );
		}
		self::store_put( $sid, $pid, $e );
		self::unlock( $lock );
		DS_Programs_Data::log_fetch( array(
			'site'  => $sid,
			'ok'    => '' === $err,
			'code'  => $code,
			'tries' => 1,
			'ms'    => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
			'rows'  => count( $e['items'] ),
			'err'   => $err,
			'why'   => $why . ' ' . $pid,
		) );
		return $e;
	}

	private static function http( $url ) {
		$pre = apply_filters( 'ds_la_schedule_http', null, $url );
		if ( is_array( $pre ) ) {
			return $pre + array( 'code' => 0, 'body' => '', 'retry_after' => 0 );
		}
		$ua = 'ds-toolkit-schedule/' . ( defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '0' ) . ' (+' . home_url( '/' ) . ')';
		$r  = wp_remote_get( $url, array( 'timeout' => self::TIMEOUT, 'user-agent' => $ua, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $r ) ) {
			return array( 'code' => 0, 'body' => '', 'retry_after' => 0 );
		}
		return array( 'code' => (int) wp_remote_retrieve_response_code( $r ), 'body' => (string) wp_remote_retrieve_body( $r ), 'retry_after' => (int) wp_remote_retrieve_header( $r, 'retry-after' ) );
	}

	/** Drop what ended more than a season ago (the module never shows it). */
	private static function prune( array $items, $now ) {
		$cut = ( $now - 200 * DAY_IN_SECONDS ) * 1000;
		return array_values( array_filter( $items, function ( $it ) use ( $cut ) {
			if ( ! is_array( $it ) ) {
				return false;
			}
			$end = $it['endTime'] ?? $it['endDate'] ?? $it['startTime'] ?? $it['startDate'] ?? null;
			return is_numeric( $end ) && $end >= $cut;
		} ) );
	}

	private static function pack( array $e ) {
		return array( 'items' => (array) $e['items'], 'ok' => (bool) $e['t'], 'nf' => (bool) $e['nf'], 'fetched' => (int) $e['t'] );
	}

	private static function queue( $key ) {
		if ( function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( 'ds_la_schedule_refresh', array( $key ) ) ) {
			wp_schedule_single_event( time(), 'ds_la_schedule_refresh', array( $key ) );
		}
	}

	private static function store_get( $sid, $pid ) {
		$e = get_option( self::PRE . $sid . '_' . $pid, array() );
		$e = is_array( $e ) ? $e : array();
		return array(
			't'     => (int) ( $e['t'] ?? 0 ),
			'items' => isset( $e['items'] ) && is_array( $e['items'] ) ? $e['items'] : array(),
			'nf'    => (int) ( $e['nf'] ?? 0 ),
			'fails' => (int) ( $e['fails'] ?? 0 ),
			'retry' => (int) ( $e['retry'] ?? 0 ),
		);
	}

	private static function store_put( $sid, $pid, array $e ) {
		update_option( self::PRE . $sid . '_' . $pid, $e, false );
	}

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

	/* ------------------------------------------------------------------ rows */

	private static function dt( $ms ) {
		$d = new DateTime( '@' . (int) floor( $ms / 1000 ) );
		$d->setTimezone( new DateTimeZone( self::TZ ) );
		return $d;
	}

	private static function clock( DateTime $d ) {
		return '00' === $d->format( 'i' ) ? $d->format( 'g A' ) : $d->format( 'g:i A' );
	}

	/** "17 Legends" -> "17L", the code clubs put in front of their event titles. */
	public static function team_code( $team ) {
		if ( ! preg_match( '/^\s*(\d{1,2})\s*[Uu]?\s+(.+)$/', (string) $team, $m ) ) {
			return '';
		}
		$letters = '';
		foreach ( preg_split( '/\s+/', trim( $m[2] ) ) as $w ) {
			$letters .= strtoupper( substr( $w, 0, 1 ) );
		}
		return $m[1] . $letters;
	}

	/**
	 * Title shown for an event: the team's own code ("17L - ") and a leading copy of the type
	 * ("Practice - ECC North") are dropped, because the row already says both.
	 *
	 * @return array( title, rest )  rest = what followed the type, shown only when there is no location
	 */
	public static function clean_title( $title, $type, $team ) {
		$t    = trim( (string) $title );
		$code = self::team_code( $team );
		if ( '' !== $code ) {
			$t = (string) preg_replace( '/^' . preg_quote( $code, '/' ) . '\b\s*[-–:|]?\s*/i', '', $t );
		}
		$rest = '';
		if ( '' !== $type && preg_match( '/^' . preg_quote( $type, '/' ) . '\b\s*(?:[-–:|]\s*(.*))?$/i', $t, $m ) ) {
			$rest = trim( (string) ( $m[1] ?? '' ) );
			$t    = $type;
		}
		$t = rtrim( $t, '* ' );
		return array( '' === $t ? ( '' !== $type ? $type : __( 'Event', 'ds-toolkit' ) ) : $t, $rest );
	}

	/**
	 * Schedule items -> table rows, one per item (a three-day tournament is ONE row with a date
	 * range, not three), oldest first. Same-day duplicates at the same place (LeagueApps often
	 * holds an all-day AND a timed entry for one tournament day) collapse; the timed one wins.
	 *
	 * @param string $team Only items for this team name (a league's schedule holds every team). '' = all.
	 * @param int    $now  Unix time; rows that ended before today are marked past.
	 */
	public static function rows( array $items, $team = '', $now = null ) {
		$now   = null === $now ? time() : (int) $now;
		$today = self::dt( $now * 1000 )->format( 'Y-m-d' );
		$want  = strtolower( trim( (string) $team ) );
		$rows  = array();
		$seen  = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$is_game = 'STRUCTURED_GAME' === ( $it['scheduleItemType'] ?? '' );
			$s       = $is_game ? ( $it['startTime'] ?? null ) : ( $it['startDate'] ?? null );
			$e       = $is_game ? ( $it['endTime'] ?? null ) : ( $it['endDate'] ?? null );
			if ( ! is_numeric( $s ) ) {
				continue;
			}
			$teams = $is_game
				? array_values( array_filter( array( (string) ( $it['team1'] ?? '' ), (string) ( $it['team2'] ?? '' ) ), 'strlen' ) )
				: array_values( array_filter( array( (string) ( $it['teamName'] ?? '' ) ), 'strlen' ) );
			if ( '' !== $want && ! in_array( $want, array_map( 'strtolower', array_map( 'trim', $teams ) ), true ) ) {
				continue;
			}
			$uid = $is_game ? 'g' . ( $it['gameId'] ?? '' ) : 'e' . ( $it['eventId'] ?? '' );
			if ( strlen( $uid ) > 1 ) {
				if ( isset( $seen[ $uid ] ) ) {
					continue;
				}
				$seen[ $uid ] = 1;
			}

			$start = self::dt( $s );
			$end   = ( is_numeric( $e ) && $e > $s ) ? self::dt( $e ) : null;
			if ( $end && '23:59' === $end->format( 'H:i' ) && '00:00' === $start->format( 'H:i' ) ) {
				$end = ( clone $end )->modify( '+1 day' )->setTime( 0, 0 );
			}
			$all_day = '00:00' === $start->format( 'H:i' ) && ( ! $end || '00:00' === $end->format( 'H:i' ) );
			$last    = $end ? ( $all_day ? ( clone $end )->modify( '-1 day' ) : clone $end ) : clone $start;
			if ( $last < $start ) {
				$last = clone $start;
			}
			if ( $all_day ) {
				$time = '';
			} elseif ( $end && $end->format( 'Y-m-d' ) !== $start->format( 'Y-m-d' ) ) {
				$time = $start->format( 'D' ) . ' ' . self::clock( $start ) . ' – ' . $end->format( 'D' ) . ' ' . self::clock( $end );
			} else {
				$time = self::clock( $start ) . ( $end ? ' – ' . self::clock( $end ) : '' );
			}

			if ( $is_game ) {
				$type  = 'Game';
				$title = trim( ( $it['team1'] ?? '' ) ?: 'TBD' ) . ' vs ' . trim( ( $it['team2'] ?? '' ) ?: 'TBD' );
				$rest  = '';
				$loc   = trim( (string) ( $it['locationName'] ?? '' ) . ( empty( $it['subLocationName'] ) ? '' : ' · ' . $it['subLocationName'] ) );
				$label = wp_strip_all_tags( (string) ( $it['typeLabel'] ?? '' ) );
			} else {
				$type = trim( wp_strip_all_tags( (string) ( $it['type'] ?? '' ) ) );
				$type = '' === $type ? 'Event' : $type;
				list( $title, $rest ) = self::clean_title( wp_strip_all_tags( (string) ( $it['title'] ?? '' ) ), $type, $teams[0] ?? '' );
				$loc   = trim( (string) ( $it['location'] ?? '' ) . ( empty( $it['subLocationName'] ) ? '' : ' · ' . $it['subLocationName'] ) );
				$label = '';
			}
			$loc = wp_strip_all_tags( $loc );
			$tbd = '' === $loc || 'tbd' === strtolower( $loc );
			if ( $tbd && '' !== $rest ) {
				$loc = $rest; // "Practice - ECC North" with no location: the gym name is the location
				$tbd = false;
			}
			$lat = is_numeric( $it['latitude'] ?? null ) ? (float) $it['latitude'] : null;
			$lng = is_numeric( $it['longitude'] ?? null ) ? (float) $it['longitude'] : null;
			$map = ( null !== $lat && null !== $lng && ( abs( $lat ) > 0.0001 || abs( $lng ) > 0.0001 ) )
				? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( round( $lat, 6 ) . ',' . round( $lng, 6 ) )
				: '';

			$day  = $start->format( 'Y-m-d' );
			$dkey = $day . '|' . strtolower( $loc ) . '|' . strtolower( preg_replace( '/\s*\([^)]*\)\s*$/', '', $title ) );
			$row  = array(
				'day'     => $day,
				'last'    => $last->format( 'Y-m-d' ),
				'sort'    => $all_day ? '00:00' : $start->format( 'H:i' ),
				'month'   => $start->format( 'Y-m' ),
				'all_day' => $all_day,
				'time'    => $time,
				'type'    => wp_strip_all_tags( $type ),
				'label'   => $label,
				'title'   => wp_strip_all_tags( $title ),
				'loc'     => $tbd ? '' : $loc,
				'map'     => $tbd ? '' : $map,
				'past'    => $last->format( 'Y-m-d' ) < $today,
			);
			if ( ! $is_game && isset( $rows[ $dkey ] ) ) {
				// Same event, same first day, same place: one row. A multi-day entry keeps its date
				// range (more useful than one day's hours); two one-day entries keep the timed one.
				$old       = $rows[ $dkey ];
				$old_range = $old['last'] !== $old['day'];
				if ( $old_range !== ( $row['last'] !== $row['day'] ) ) {
					$keep = $old_range ? $old : $row;                         // the date range wins
				} else {
					$keep = ( $old['all_day'] && ! $row['all_day'] ) ? $row : $old; // else the timed one
				}
				$keep['last']  = max( $old['last'], $row['last'] );
				$keep['past']  = $keep['last'] < $today;
				$rows[ $dkey ] = $keep;
				continue;
			}
			$rows[ $is_game ? $uid : $dkey ] = $row;
		}
		$rows = array_values( $rows );
		usort( $rows, function ( $a, $b ) { return strcmp( $a['day'] . $a['sort'] . $a['title'], $b['day'] . $b['sort'] . $b['title'] ); } );
		return $rows;
	}

	/** Date label pieces for a row: weekday, day number(s), month, and the full date for screen readers. */
	public static function date_parts( array $r ) {
		$a = new DateTime( $r['day'] . ' 12:00', new DateTimeZone( self::TZ ) );
		$b = new DateTime( $r['last'] . ' 12:00', new DateTimeZone( self::TZ ) );
		$range = $r['last'] !== $r['day'];
		if ( ! $range ) {
			return array( 'dow' => $a->format( 'D' ), 'num' => $a->format( 'j' ), 'mon' => $a->format( 'M' ), 'full' => $a->format( 'l, F j, Y' ) );
		}
		$same_month = $a->format( 'Y-m' ) === $b->format( 'Y-m' );
		return array(
			'dow'  => $a->format( 'D' ) . '–' . $b->format( 'D' ),
			'num'  => $a->format( 'j' ) . '–' . $b->format( 'j' ),
			'mon'  => $same_month ? $a->format( 'M' ) : $a->format( 'M' ) . '–' . $b->format( 'M' ),
			'full' => $a->format( 'l, F j' ) . ' to ' . $b->format( 'l, F j, Y' ),
		);
	}
}
