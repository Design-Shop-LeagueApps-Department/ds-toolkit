<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-ds-lock.php';
require_once __DIR__ . '/class-ds-data-client.php';

/**
 * League standings for the LeagueApps Standings module.
 *
 * LeagueApps has no standings endpoint (`/programs/<id>/standings` answers 404). Each division's public
 * `/programs/<id>/schedule` carries every played game with both scores and WIN / LOSS results, and the
 * standings worked out from it matched the LeagueApps standings page for every division tested (Big Cat
 * Basketball, 2026 Girls Winter League: 5 divisions, 47 teams, every value and the order). What the feed
 * does NOT carry is the league's tiebreaker rules and its standings points, so those are module settings.
 *
 * Load rules, the same ones the Calendar follows:
 *   - The league list comes from DS_Programs_Data::raw() (the Programs table's cache): no extra request.
 *   - Each division's games are kept per site and program in their own option (not autoloaded), fresh for
 *     an hour; a division with no schedule (404) is not asked again for six hours; a failure is not retried
 *     for five minutes and keeps the last good games.
 *   - Visitors never wait once a copy exists: an expired copy is served while one WP-Cron job
 *     (ds_standings_refresh) refreshes it. Only a first load or a copy older than six hours fetches inline,
 *     inside a 15-second budget, one request at a time, under one lock per site.
 *   - A 429 stops at once and holds the site off for the Retry-After (at least 5 minutes, at most an hour).
 *   - Every request is a line in the shared "Feed activity" ledger (Settings > DS Toolkit > LeagueApps).
 *
 * LeagueApps schedule times are Eastern wall-clock: they are formatted in America/New_York on purpose.
 */
class DS_Standings_Data {

	const TTL           = HOUR_IN_SECONDS;          // a division's games stay fresh this long
	const MISSING_TTL   = 6 * HOUR_IN_SECONDS;      // "no schedule" is not asked again for this long
	const FAIL_WAIT     = 5 * MINUTE_IN_SECONDS;    // after a failed request, leave the division alone this long
	const INLINE_AGE    = 6 * HOUR_IN_SECONDS;      // a copy older than this is refreshed inside the page view
	const BUDGET        = 15;                       // seconds one refresh may spend on LeagueApps
	const LOCK_TTL      = 60;
	const HOLD_MIN      = 5 * MINUTE_IN_SECONDS;
	const HOLD_MAX      = HOUR_IN_SECONDS;
	const MAX_DIVISIONS = 24;                       // per module: a league with more is cut, never a runaway fetch
	const GOOD_PREFIX   = 'ds_standings_good_';     // option per site and program: the last good games
	const LOCK_PREFIX   = 'ds_standings_lock_';     // DS_Lock row per site
	const REFRESH_HOOK  = 'ds_standings_refresh';
	const TZ            = 'America/New_York';

	/** Tiebreakers an editor can pick, in the words LeagueApps uses on its standings page. */
	const BREAKERS = array(
		'h2h' => 'Head to head',
		'psd' => 'Higher point differential',
		'psa' => 'Fewer points against',
		'ps'  => 'More points scored',
		'w'   => 'More wins',
	);

	/* ------------------------------------------------------------------
	 * Leagues (from the Programs feed: no request of their own)
	 * ---------------------------------------------------------------- */

	/**
	 * Leagues with standings on the saved LeagueApps sites, each with its divisions.
	 *
	 * A program with sub-programs ("divisions" in LeagueApps) is a league whose divisions are those
	 * sub-programs; a program without any is a league of one division (itself). Only programs LeagueApps
	 * publishes a standings page for are listed.
	 *
	 * @return array "site:id" => array( site, id, name, season, sport, start (unix), url, divisions => array( pid => array( name, url ) ) )
	 */
	public static function leagues( array $sites = array() ) {
		if ( ! class_exists( 'DS_Programs_Data' ) ) {
			require_once __DIR__ . '/class-ds-programs-data.php';
		}
		$sites = $sites ? $sites : DS_Programs_Data::configured_sites();
		$feed  = DS_Programs_Data::raw( $sites );
		return self::leagues_from_rows( (array) ( $feed['rows'] ?? array() ) );
	}

	/** leagues() from raw program rows (separate so a test can feed it rows). */
	public static function leagues_from_rows( array $rows ) {
		$by_master = array();
		$progs     = array();
		foreach ( $rows as $p ) {
			if ( ! is_array( $p ) ) { continue; }
			$sid = preg_replace( '/\D+/', '', (string) ( $p['_site'] ?? '' ) );
			$pid = preg_replace( '/\D+/', '', (string) ( $p['programId'] ?? '' ) );
			if ( '' === $sid || '' === $pid ) { continue; }
			$progs[ $sid . ':' . $pid ] = $p;
			$master = preg_replace( '/\D+/', '', (string) ( $p['masterProgramId'] ?? '' ) );
			if ( '' !== $master && '0' !== $master && $master !== $pid ) {
				$by_master[ $sid . ':' . $master ][] = $p;
			}
		}
		$out = array();
		foreach ( $progs as $key => $p ) {
			list( $sid, $pid ) = explode( ':', $key );
			$master = preg_replace( '/\D+/', '', (string) ( $p['masterProgramId'] ?? '' ) );
			if ( '' !== $master && '0' !== $master && $master !== $pid && isset( $progs[ $sid . ':' . $master ] ) ) {
				continue; // listed under its league
			}
			$kids = $by_master[ $key ] ?? array();
			$divs = array();
			foreach ( ( $kids ? $kids : array( $p ) ) as $d ) {
				if ( '' === trim( (string) ( $d['standingsUrlHtml'] ?? '' ) ) ) { continue; }
				$divs[] = $d;
			}
			if ( ! $divs ) { continue; }
			usort( $divs, function ( $a, $b ) {
				$sa = (int) ( $a['sortOrder'] ?? 0 );
				$sb = (int) ( $b['sortOrder'] ?? 0 );
				return $sa === $sb ? ( (int) $a['programId'] <=> (int) $b['programId'] ) : ( $sa <=> $sb );
			} );
			$list = array();
			foreach ( array_slice( $divs, 0, self::MAX_DIVISIONS ) as $d ) {
				$list[ (string) (int) $d['programId'] ] = array(
					'name' => sanitize_text_field( (string) ( $d['name'] ?? '' ) ),
					'url'  => self::link( (string) ( $d['standingsUrlHtml'] ?? '' ) ),
				);
			}
			$out[ $key ] = array(
				'site'      => $sid,
				'id'        => $pid,
				'name'      => sanitize_text_field( (string) ( $p['name'] ?? '' ) ),
				'season'    => sanitize_text_field( (string) ( $p['season'] ?? '' ) ),
				'sport'     => sanitize_text_field( (string) ( $p['sport'] ?? '' ) ),
				'start'     => (int) floor( (float) ( $p['startTime'] ?? 0 ) / 1000 ), // LeagueApps sends milliseconds
				'url'       => self::link( (string) ( $p['standingsUrlHtml'] ?? '' ) ),
				'divisions' => $list,
			);
		}
		uasort( $out, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
		return $out;
	}

	/** A LeagueApps link ("//club.leagueapps.com/...") as https, or '' for anything else. */
	public static function link( $u ) {
		$u = trim( (string) $u );
		if ( 0 === strpos( $u, '//' ) ) {
			$u = 'https:' . $u;
		}
		return preg_match( '#^https://[a-z0-9.-]+\.[a-z]{2,}/#i', $u ) ? esc_url_raw( $u ) : '';
	}

	/* ------------------------------------------------------------------
	 * Games (one request per division, cached)
	 * ---------------------------------------------------------------- */

	/**
	 * The played games of these divisions, from the cache, refreshing as the load rules above say.
	 *
	 * @return array( 'divisions' => pid => array( games, teams, left, next, last, fetched, missing ), 'errors' => list, 'stale' => bool )
	 */
	public static function get( $site_id, array $pids ) {
		$site = self::site( $site_id );
		$pids = self::clean_pids( $pids );
		$out  = array( 'divisions' => array(), 'errors' => array(), 'stale' => false );
		if ( ! $site ) {
			$out['errors'][] = __( 'This LeagueApps site is not saved under Settings > DS Toolkit > LeagueApps.', 'ds-toolkit' );
			return $out;
		}
		$now    = time();
		$inline = array();
		$queue  = false;
		foreach ( $pids as $pid ) {
			$kept = self::kept( $site['site_id'], $pid );
			if ( ! $kept ) {
				$inline[] = $pid;
				continue;
			}
			$age = $now - (int) $kept['fetched'];
			if ( $age <= ( ! empty( $kept['missing'] ) ? self::MISSING_TTL : self::TTL ) ) {
				continue; // fresh
			}
			if ( $age > self::INLINE_AGE ) {
				$inline[] = $pid;
			} else {
				$queue = true;
			}
		}
		if ( $inline ) {
			$errs = self::fetch( $site, $inline, 'page' );
			$out['errors'] = array_merge( $out['errors'], $errs );
		}
		if ( $queue ) {
			self::queue( $site['site_id'], $pids );
		}
		foreach ( $pids as $pid ) {
			$kept = self::kept( $site['site_id'], $pid );
			if ( $kept ) {
				$out['divisions'][ $pid ] = $kept;
				if ( $now - (int) $kept['fetched'] > self::TTL && empty( $kept['missing'] ) ) {
					$out['stale'] = true;
				}
			}
		}
		return $out;
	}

	/** WP-Cron handler: refresh the divisions of one site that are due. Named by ids only (no key in cron). */
	public static function refresh( $site_id, $pids = array() ) {
		$site = self::site( $site_id );
		if ( ! $site ) { return; }
		$now = time();
		$due = array();
		foreach ( self::clean_pids( (array) $pids ) as $pid ) {
			$kept = self::kept( $site['site_id'], $pid );
			$ttl  = ( $kept && ! empty( $kept['missing'] ) ) ? self::MISSING_TTL : self::TTL;
			if ( ! $kept || $now - (int) $kept['fetched'] > $ttl ) {
				$due[] = $pid;
			}
		}
		if ( $due ) {
			self::fetch( $site, $due, 'background' );
		}
	}

	/** Features that read standings call this once, so the background refresh has a handler in the cron request. */
	public static function hooks() {
		if ( ! has_action( self::REFRESH_HOOK, array( __CLASS__, 'refresh' ) ) ) {
			add_action( self::REFRESH_HOOK, array( __CLASS__, 'refresh' ), 10, 2 );
		}
	}

	/** "Refresh LeagueApps listings" also ages every kept division, so the next view asks LeagueApps again. */
	public static function flush() {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) { return; }
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::GOOD_PREFIX ) . '%' ) );
		foreach ( (array) $names as $name ) {
			$pack = get_option( $name );
			if ( is_array( $pack ) ) {
				// Aged, not deleted: if LeagueApps is down the visitor still sees the last standings.
				$pack['fetched'] = min( (int) ( $pack['fetched'] ?? 0 ), time() - self::INLINE_AGE - 1 );
				update_option( $name, $pack, false );
			}
		}
	}

	private static function queue( $site_id, array $pids ) {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) { return; }
		$args = array( (string) $site_id, array_values( $pids ) );
		if ( ! wp_next_scheduled( self::REFRESH_HOOK, $args ) ) {
			wp_schedule_single_event( time(), self::REFRESH_HOOK, $args );
		}
	}

	/** Fetch these divisions now, one at a time, under the site's lock and inside the budget. @return list of errors */
	private static function fetch( array $site, array $pids, $why ) {
		$sid    = $site['site_id'];
		$errors = array();
		$hold   = (int) get_transient( 'ds_standings_hold_' . $sid );
		if ( $hold > time() ) {
			return array( __( 'LeagueApps asked us to slow down; the standings refresh shortly.', 'ds-toolkit' ) );
		}
		$lock = self::LOCK_PREFIX . $sid;
		if ( ! DS_Lock::acquire( $lock, self::LOCK_TTL ) ) {
			return array(); // another request is fetching: serve what is kept
		}
		$deadline = microtime( true ) + self::BUDGET;
		foreach ( $pids as $pid ) {
			if ( false !== get_transient( 'ds_standings_fail_' . $sid . '_' . $pid ) ) {
				continue;
			}
			if ( microtime( true ) > $deadline - 1 ) {
				break;
			}
			$url = DS_Programs_Data::API_BASE . rawurlencode( $sid ) . '/programs/' . rawurlencode( $pid ) . '/schedule?x-api-key=' . rawurlencode( $site['api_key'] );
			$t0  = microtime( true );
			$res = DS_Data_Client::get_json( $url, array( 'timeout' => 8, 'attempts' => 2, 'deadline' => $deadline, 'agent' => 'standings' ) );
			$games = ( $res['ok'] && isset( $res['json']['games'] ) && is_array( $res['json']['games'] ) ) ? $res['json']['games'] : array();
			DS_Programs_Data::log_fetch( array(
				'site'  => $sid,
				'ok'    => $res['ok'] || 404 === (int) $res['code'],
				'code'  => (int) $res['code'],
				'tries' => max( 1, (int) $res['tries'] ),
				'ms'    => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
				'rows'  => count( $games ),
				'err'   => (string) $res['error'],
				'why'   => 'standings ' . $why,
			) );
			if ( $res['ok'] ) {
				DS_Data_Client::keep( self::GOOD_PREFIX . $sid . '_' . $pid, self::slim( $games ) );
			} elseif ( 'missing' === $res['kind'] ) {
				DS_Data_Client::keep( self::GOOD_PREFIX . $sid . '_' . $pid, array_merge( self::slim( array() ), array( 'missing' => true ) ) );
			} elseif ( 'throttled' === $res['kind'] ) {
				$wait = max( self::HOLD_MIN, min( self::HOLD_MAX, (int) $res['retry_after'] ) );
				set_transient( 'ds_standings_hold_' . $sid, time() + $wait, $wait );
				$errors[] = __( 'LeagueApps asked us to slow down; the standings refresh shortly.', 'ds-toolkit' );
				break;
			} else {
				set_transient( 'ds_standings_fail_' . $sid . '_' . $pid, time(), self::FAIL_WAIT );
				$errors[] = sprintf( /* translators: %s: an error such as "HTTP 500" */ __( 'LeagueApps did not answer for one division (%s).', 'ds-toolkit' ), $res['error'] );
			}
		}
		DS_Lock::release( $lock );
		return $errors;
	}

	/**
	 * Only what standings need from a schedule: the played games (both results set), every team that has a
	 * game (so a team that has not played yet still gets a row), and the dates for the "results through" line.
	 */
	public static function slim( array $items ) {
		$games = array();
		$teams = array();
		$left  = 0;
		$next  = 0;
		$last  = 0;
		foreach ( $items as $g ) {
			if ( ! is_array( $g ) || 'STRUCTURED_GAME' !== (string) ( $g['scheduleItemType'] ?? '' ) ) {
				continue;
			}
			$a = (string) (int) ( $g['team1Id'] ?? 0 );
			$b = (string) (int) ( $g['team2Id'] ?? 0 );
			if ( '0' === $a || '0' === $b || $a === $b ) {
				continue; // a game against "TBD" or a bye
			}
			$teams[ $a ] = sanitize_text_field( (string) ( $g['team1'] ?? '' ) );
			$teams[ $b ] = sanitize_text_field( (string) ( $g['team2'] ?? '' ) );
			$state = strtoupper( (string) ( $g['state'] ?? '' ) );
			$start = (int) ( $g['startTime'] ?? 0 );
			if ( in_array( $state, array( 'CANCELED', 'CANCELLED' ), true ) ) {
				continue;
			}
			$ra = strtoupper( (string) ( $g['team1Result'] ?? '' ) );
			$rb = strtoupper( (string) ( $g['team2Result'] ?? '' ) );
			if ( '' === $ra || '' === $rb ) {
				$left++;
				if ( $start && ( ! $next || $start < $next ) ) {
					$next = $start;
				}
				continue;
			}
			$games[] = array(
				'a'  => $a,
				'b'  => $b,
				'as' => (int) ( $g['team1Score'] ?? 0 ),
				'bs' => (int) ( $g['team2Score'] ?? 0 ),
				'ar' => $ra,
				'br' => $rb,
				'f'  => false !== strpos( $state, 'FORFEIT' ) ? 1 : 0,
			);
			if ( $start > $last ) {
				$last = $start;
			}
		}
		return array( 'games' => $games, 'teams' => $teams, 'left' => $left, 'next' => $next, 'last' => $last, 'missing' => false, 'fetched' => time() );
	}

	/* ------------------------------------------------------------------
	 * The table
	 * ---------------------------------------------------------------- */

	/**
	 * Standings rows from a kept division.
	 *
	 * Order: win percentage, then the tiebreakers in the order given. As soon as a rule separates the best of
	 * the tied teams, that team is placed and the rules start again for the teams still tied; that is how
	 * LeagueApps orders a three-way tie whose head-to-head results go round in a circle.
	 *
	 * @param array $div   a kept division (slim())
	 * @param array $rules breakers => list of BREAKERS keys; points => array( win, tie, loss ); forfeit => points
	 *                     taken off per forfeit (shown as PA)
	 * @return list of array( id, team, gp, w, l, t, pct, ps, psa, psd, dnp, pf, pa, pm )
	 */
	public static function table( array $div, array $rules = array() ) {
		$breakers = array_values( array_intersect( (array) ( $rules['breakers'] ?? array( 'h2h', 'psd', 'psa' ) ), array_keys( self::BREAKERS ) ) );
		$points   = array_values( array_map( 'floatval', (array) ( $rules['points'] ?? array( 1, 0.5, 0 ) ) ) ) + array( 1, 0.5, 0 );
		$penalty  = (float) ( $rules['forfeit'] ?? 0 );

		$st  = array();
		$h2h = array();
		foreach ( (array) ( $div['teams'] ?? array() ) as $id => $name ) {
			$st[ (string) $id ] = array( 'id' => (string) $id, 'team' => (string) $name, 'gp' => 0, 'w' => 0, 'l' => 0, 't' => 0, 'ps' => 0, 'psa' => 0, 'dnp' => 0 );
		}
		foreach ( (array) ( $div['games'] ?? array() ) as $g ) {
			foreach ( array( array( 'a', 'b', 'as', 'bs', 'ar' ), array( 'b', 'a', 'bs', 'as', 'br' ) ) as $side ) {
				list( $me, $op, $my, $their, $res ) = $side;
				$id = (string) $g[ $me ];
				$vs = (string) $g[ $op ];
				if ( ! isset( $st[ $id ] ) ) {
					$st[ $id ] = array( 'id' => $id, 'team' => '', 'gp' => 0, 'w' => 0, 'l' => 0, 't' => 0, 'ps' => 0, 'psa' => 0, 'dnp' => 0 );
				}
				$r = &$st[ $id ];
				$r['gp']++;
				$r['ps']  += (int) $g[ $my ];
				$r['psa'] += (int) $g[ $their ];
				$k = $id . '|' . $vs;
				if ( ! isset( $h2h[ $k ] ) ) { $h2h[ $k ] = array( 0, 0 ); }
				$h2h[ $k ][1]++;
				if ( 'WIN' === $g[ $res ] ) {
					$r['w']++;
					$h2h[ $k ][0] += 1;
				} elseif ( 'LOSS' === $g[ $res ] ) {
					$r['l']++;
					if ( ! empty( $g['f'] ) ) { $r['dnp']++; }
				} else {
					$r['t']++;
					$h2h[ $k ][0] += 0.5;
				}
				unset( $r );
			}
		}
		foreach ( $st as &$r ) {
			$r['pct'] = $r['gp'] ? round( 100 * ( $r['w'] + 0.5 * $r['t'] ) / $r['gp'], 2 ) : 0.0;
			$r['psd'] = $r['ps'] - $r['psa'];
			$r['pf']  = $r['w'] * $points[0] + $r['t'] * $points[1] + $r['l'] * $points[2];
			$r['pa']  = $r['dnp'] * $penalty;
			$r['pm']  = $r['pf'] - $r['pa'];
		}
		unset( $r );

		// Group by win percentage (teams that have not played go last), then break each tie.
		$groups = array();
		foreach ( $st as $id => $r ) {
			$groups[ $r['gp'] ? sprintf( '%09.2f', $r['pct'] ) : '-' ][] = $id;
		}
		uksort( $groups, function ( $a, $b ) {
			if ( '-' === $a || '-' === $b ) { return '-' === $a ? ( '-' === $b ? 0 : 1 ) : -1; }
			return strcmp( $b, $a );
		} );
		$order = array();
		foreach ( $groups as $ids ) {
			while ( $ids ) {
				$top     = self::top_of( $ids, $st, $h2h, $breakers );
				$order[] = $st[ $top ];
				$ids     = array_values( array_diff( $ids, array( $top ) ) );
			}
		}
		return $order;
	}

	/** The team that comes first among these tied teams. */
	private static function top_of( array $ids, array $st, array $h2h, array $breakers ) {
		$cands = $ids;
		while ( count( $cands ) > 1 ) {
			$moved = false;
			foreach ( $breakers as $rule ) {
				$vals = array();
				foreach ( $cands as $id ) {
					$vals[ $id ] = self::rule_value( $rule, $id, $cands, $st, $h2h );
				}
				if ( in_array( null, $vals, true ) ) {
					continue; // head to head counts only when every tied team has played one of the others
				}
				$best = max( $vals );
				$lead = array_keys( array_filter( $vals, function ( $v ) use ( $best ) { return abs( $v - $best ) < 1e-9; } ) );
				if ( count( $lead ) < count( $cands ) ) {
					$cands = array_map( 'strval', $lead );
					$moved = true;
					break; // separated some: start the rules again for the teams still level
				}
			}
			if ( ! $moved ) {
				break;
			}
		}
		if ( count( $cands ) > 1 ) {
			usort( $cands, function ( $a, $b ) use ( $st ) { return strcasecmp( $st[ $a ]['team'], $st[ $b ]['team'] ); } );
		}
		return (string) $cands[0];
	}

	/** Higher is better for every rule (fewer points against is negated). null = the rule cannot be applied. */
	private static function rule_value( $rule, $id, array $cands, array $st, array $h2h ) {
		switch ( $rule ) {
			case 'h2h':
				$w = 0;
				$n = 0;
				foreach ( $cands as $o ) {
					if ( $o === $id || ! isset( $h2h[ $id . '|' . $o ] ) ) { continue; }
					$w += $h2h[ $id . '|' . $o ][0];
					$n += $h2h[ $id . '|' . $o ][1];
				}
				return $n ? $w / $n : null;
			case 'psd':
				return (float) $st[ $id ]['psd'];
			case 'psa':
				return -(float) $st[ $id ]['psa'];
			case 'ps':
				return (float) $st[ $id ]['ps'];
			case 'w':
				return (float) $st[ $id ]['w'];
		}
		return 0.0;
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	/** A date from a LeagueApps schedule time (ms, Eastern wall clock), e.g. "Sunday, Nov 15". */
	public static function day( $ms ) {
		if ( ! $ms ) { return ''; }
		try {
			$d = new DateTime( '@' . (int) floor( $ms / 1000 ) );
			$d->setTimezone( new DateTimeZone( self::TZ ) );
			return function_exists( 'wp_date' ) ? wp_date( 'l, M j', $d->getTimestamp(), new DateTimeZone( self::TZ ) ) : $d->format( 'l, M j' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	private static function site( $site_id ) {
		$site_id = preg_replace( '/\D+/', '', (string) $site_id );
		foreach ( DS_Programs_Data::configured_sites() as $s ) {
			if ( (string) $s['site_id'] === $site_id ) {
				return $s;
			}
		}
		return null;
	}

	private static function clean_pids( array $pids ) {
		$out = array();
		foreach ( $pids as $p ) {
			$p = preg_replace( '/\D+/', '', (string) $p );
			if ( '' !== $p ) { $out[ $p ] = $p; }
		}
		return array_slice( array_values( $out ), 0, self::MAX_DIVISIONS );
	}

	private static function kept( $sid, $pid ) {
		$pack = DS_Data_Client::kept( self::GOOD_PREFIX . $sid . '_' . $pid );
		return ( is_array( $pack ) && isset( $pack['games'] ) ) ? $pack : null;
	}
}
