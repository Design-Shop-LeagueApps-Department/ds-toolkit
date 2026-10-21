<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps team rosters for the LeagueApps Roster module.
 *
 * Rosters are only in LeagueApps' PRIVATE API (the registrations export): the public API has
 * teams and schedules but no roster (every roster path 404s; Surfside, 2026-10-21). The private
 * key can read every family's emails, birthdays, addresses and payments, so this class:
 *
 *  - keeps the key ENCRYPTED in its own option (not autoloaded), with a key derived from the
 *    site's wp-config secrets: a database dump alone cannot use it. It is never rendered.
 *  - reads registrations in memory and keeps ONLY the public fields (first name, last name,
 *    position, graduation year, height, captain, role) for rostered players and coaches.
 *    Emails, birthdays, addresses, phones, schools, cities and payments are never stored.
 *  - syncs incrementally (LeagueApps' last-updated cursor) at most hourly in the background,
 *    with a full rebuild once a day so removed players drop off. A visitor never waits on it.
 *
 * Auth (LeagueApps/api-example): an RS256 JWT signed with the key, iss = sub = client id,
 * aud = the token URL, exchanged for a 15-minute bearer token.
 */
class DS_Roster_Data {

	const AUTH_URL   = 'https://auth.leagueapps.io/v2/auth/token';
	const EXPORT_URL = 'https://admin.leagueapps.io/v2/sites/%s/export/registrations-2';
	const KEYS_OPT   = 'ds_la_private_keys';
	const STORE_PRE  = 'ds_la_roster_';      // + site id, autoload no
	const SYNC_EVERY = HOUR_IN_SECONDS;
	const FULL_EVERY = DAY_IN_SECONDS;
	const BUDGET     = 25;                   // seconds per sync run at most (less when the host's time limit is shorter)
	const PAGE_WAIT  = 15;                   // seconds one LeagueApps page may take
	const PAGES_MAX  = 60;                   // 60 000 registrations per run at most
	const KEEP_DAYS  = 400;                  // teams whose season ended longer ago are dropped

	/* ------------------------------------------------------------------ the key */

	/** sodium secretbox key from the site's own secrets (AUTH_KEY etc. in wp-config). */
	private static function box_key() {
		$seed = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . '|' . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' ) . '|' . ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '' ) . '|ds-la-roster';
		return sodium_crypto_generichash( $seed, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	private static function seal( $plain ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::box_key() ) );
	}

	private static function open_box( $sealed ) {
		$raw = base64_decode( (string) $sealed, true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::box_key() );
		return false === $plain ? '' : $plain;
	}

	/**
	 * A key file (.p12 from LeagueApps, or a .pem converted from it) -> PEM private key, or WP_Error.
	 * LeagueApps' .p12 uses the password "notasecret" and old ciphers that OpenSSL 3 may refuse;
	 * the error then says how to convert it once.
	 */
	public static function pem_from_upload( $bytes ) {
		$bytes = (string) $bytes;
		if ( false !== strpos( $bytes, '-----BEGIN' ) ) {
			if ( preg_match( '/-----BEGIN (?:RSA |ENCRYPTED )?PRIVATE KEY-----.+?-----END (?:RSA |ENCRYPTED )?PRIVATE KEY-----/s', $bytes, $m ) && openssl_pkey_get_private( $m[0] ) ) {
				return $m[0];
			}
			return new WP_Error( 'ds_roster_pem', __( 'That file has no readable private key in it.', 'ds-toolkit' ) );
		}
		$certs = array();
		if ( function_exists( 'openssl_pkcs12_read' ) && @openssl_pkcs12_read( $bytes, $certs, 'notasecret' ) && ! empty( $certs['pkey'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- OpenSSL warns on legacy ciphers; handled below
			return $certs['pkey'];
		}
		return new WP_Error( 'ds_roster_p12', __( 'This server\'s OpenSSL cannot open LeagueApps\' .p12 file (it uses an old cipher). Convert it once on a computer and upload the .pem instead: openssl pkcs12 -nodes -in KEY.p12 -passin pass:notasecret -out KEY.pem (add -legacy on OpenSSL 3).', 'ds-toolkit' ) );
	}

	/** Save a site's private key (sealed). Returns true or WP_Error. */
	public static function save_key( $site_id, $client_id, $pem ) {
		$site_id   = preg_replace( '/\D+/', '', (string) $site_id );
		$client_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $client_id );
		if ( '' === $site_id || '' === $client_id || '' === (string) $pem ) {
			return new WP_Error( 'ds_roster_missing', __( 'Site, client ID and key file are all needed.', 'ds-toolkit' ) );
		}
		$pk = openssl_pkey_get_private( $pem );
		if ( ! $pk ) {
			return new WP_Error( 'ds_roster_pem', __( 'That key could not be read.', 'ds-toolkit' ) );
		}
		$det  = openssl_pkey_get_details( $pk );
		$keys = self::keys_raw();
		$keys[ $site_id ] = array(
			'client_id' => $client_id,
			'key'       => self::seal( $pem ),
			'print'     => substr( hash( 'sha256', (string) ( $det['key'] ?? '' ) ), 0, 12 ), // public-key fingerprint, safe to show
			'added'     => time(),
			'by'        => (int) get_current_user_id(),
		);
		update_option( self::KEYS_OPT, $keys, false );
		delete_option( self::STORE_PRE . $site_id ); // a new account starts a fresh roster
		return true;
	}

	public static function delete_key( $site_id ) {
		$site_id = preg_replace( '/\D+/', '', (string) $site_id );
		$keys    = self::keys_raw();
		unset( $keys[ $site_id ] );
		update_option( self::KEYS_OPT, $keys, false );
		delete_option( self::STORE_PRE . $site_id );
	}

	private static function keys_raw() {
		$k = get_option( self::KEYS_OPT, array() );
		return is_array( $k ) ? $k : array();
	}

	/** What the settings screen may show: never the key itself. */
	public static function key_status( $site_id ) {
		$k = self::keys_raw()[ (string) $site_id ] ?? null;
		if ( ! $k ) {
			return null;
		}
		$store = self::store( $site_id );
		return array(
			'client_id' => $k['client_id'],
			'print'     => $k['print'],
			'added'     => (int) $k['added'],
			'readable'  => '' !== self::open_box( $k['key'] ),
			'synced'    => (int) ( $store['synced'] ?? 0 ),
			'full'      => (int) ( $store['full'] ?? 0 ),
			'teams'     => count( (array) ( $store['teams'] ?? array() ) ),
			'error'     => (string) ( $store['error'] ?? '' ),
		);
	}

	/** Site ids that have a usable private key. */
	public static function sites_with_keys() {
		return array_map( 'strval', array_keys( self::keys_raw() ) );
	}

	/* ------------------------------------------------------------------ auth + HTTP */

	private static function b64url( $s ) {
		return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
	}

	/** Bearer token for a site (cached 12 of its 15 minutes), or WP_Error. */
	private static function token( $site_id ) {
		$cached = get_transient( 'ds_la_roster_tok_' . $site_id );
		if ( is_string( $cached ) && '' !== $cached ) {
			return self::open_box( $cached );
		}
		$k = self::keys_raw()[ $site_id ] ?? null;
		$pem = $k ? self::open_box( $k['key'] ) : '';
		if ( '' === $pem ) {
			return new WP_Error( 'ds_roster_key', __( 'The private key cannot be read on this server (the site\'s secret keys changed, or it was never saved). Upload it again on the LeagueApps tab.', 'ds-toolkit' ) );
		}
		$now    = time();
		$head   = self::b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::b64url( wp_json_encode( array( 'aud' => self::AUTH_URL, 'iss' => $k['client_id'], 'sub' => $k['client_id'], 'iat' => $now, 'exp' => $now + 300 ) ) );
		$sig    = '';
		if ( ! openssl_sign( $head . '.' . $claims, $sig, $pem, OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'ds_roster_sign', __( 'Could not sign the LeagueApps login with this key.', 'ds-toolkit' ) );
		}
		$res = wp_remote_post( self::AUTH_URL, array(
			'timeout' => 15,
			'body'    => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $head . '.' . $claims . '.' . self::b64url( $sig ) ),
		) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'ds_roster_auth', __( 'LeagueApps login did not answer.', 'ds-toolkit' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || empty( $json['access_token'] ) ) {
			return new WP_Error( 'ds_roster_auth', sprintf( /* translators: %d HTTP status */ __( 'LeagueApps refused the private key (HTTP %d). Check the client ID belongs to this site.', 'ds-toolkit' ), $code ) );
		}
		set_transient( 'ds_la_roster_tok_' . $site_id, self::seal( (string) $json['access_token'] ), 12 * MINUTE_IN_SECONDS );
		return (string) $json['access_token'];
	}

	/** One page of registrations after the cursor, or WP_Error. */
	private static function page( $site_id, $token, $updated, $id ) {
		$url = sprintf( self::EXPORT_URL, rawurlencode( $site_id ) ) . '?last-updated=' . (int) $updated . '&last-id=' . (int) $id;
		$res = wp_remote_get( $url, array( 'timeout' => self::PAGE_WAIT, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'ds_roster_http', __( 'LeagueApps did not answer.', 'ds-toolkit' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			if ( 401 === $code ) {
				delete_transient( 'ds_la_roster_tok_' . $site_id );
			}
			return new WP_Error( 'ds_roster_http', 'HTTP ' . $code );
		}
		$rows = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return is_array( $rows ) ? $rows : array();
	}

	/* ------------------------------------------------------------------ keeping only public fields */

	/** The club's own question for a field, found by its label ("Position", "High School Graduation Year", "Height"). */
	public static function custom_value( array $r, $what ) {
		foreach ( $r as $k => $v ) {
			if ( ! is_scalar( $v ) || '' === trim( (string) $v ) ) {
				continue;
			}
			$k = strtolower( (string) $k );
			if ( 'position' === $what && false !== strpos( $k, 'position' ) && false === strpos( $k, 'prefer' ) ) {
				return trim( (string) $v );
			}
			if ( 'grad' === $what && ( false !== strpos( $k, 'graduat' ) || false !== strpos( $k, 'grad year' ) || false !== strpos( $k, 'class of' ) ) ) {
				return preg_match( '/(19|20)\d\d/', (string) $v, $m ) ? $m[0] : '';
			}
			if ( 'height' === $what && false !== strpos( $k, 'height' ) ) {
				return self::height( (string) $v );
			}
		}
		return '';
	}

	/** "5'10", "5 ft 10 in", "5-10", "70" (inches) -> 5'10"; anything else as typed (trimmed, short). */
	public static function height( $v ) {
		$v = trim( wp_strip_all_tags( $v ) );
		if ( preg_match( '/^(\d)\s*(?:\'|’|ft|feet|-|\s)\s*(\d{1,2})\s*(?:"|”|in|inches|\'\')?\s*$/i', $v, $m ) && (int) $m[2] < 12 ) {
			return $m[1] . '\'' . (int) $m[2] . '"';
		}
		if ( preg_match( '/^(\d)\s*(?:\'|’|ft|feet)\s*$/i', $v, $m ) ) {
			return $m[1] . '\'0"';
		}
		if ( preg_match( '/^(\d{2})\s*(?:"|in|inches)?$/i', $v, $m ) && (int) $m[1] >= 40 && (int) $m[1] <= 90 ) {
			return intdiv( (int) $m[1], 12 ) . '\'' . ( (int) $m[1] % 12 ) . '"';
		}
		return mb_substr( $v, 0, 12 );
	}

	/** A name as people write it: "molly" -> "Molly", "OLeary" kept, "McDonald" kept. */
	public static function name_case( $s ) {
		$s = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $s ) ) );
		if ( '' === $s ) {
			return '';
		}
		if ( ! function_exists( 'mb_convert_case' ) ) { // mbstring is not polyfilled by WordPress
			return ( $s === strtolower( $s ) || $s === strtoupper( $s ) ) ? ucwords( strtolower( $s ), " -'" ) : $s;
		}
		if ( $s === mb_strtolower( $s ) || $s === mb_strtoupper( $s ) ) {
			$s = mb_convert_case( mb_strtolower( $s ), MB_CASE_TITLE );
		}
		return $s;
	}

	/**
	 * A registration -> the stored public row, or null when it is not a roster spot
	 * (free agents, waitlist, admins, directors, no team).
	 */
	public static function public_row( array $r ) {
		$role = strtolower( (string) ( $r['role'] ?? '' ) );
		if ( empty( $r['teamId'] ) || ! in_array( $role, array( 'player', 'coach' ), true ) ) {
			return null;
		}
		$status = strtoupper( (string) ( $r['registrationStatus'] ?? '' ) );
		if ( in_array( $status, array( 'WAITING_LIST', 'CANCELLED', 'CANCELED', 'DECLINED', 'REMOVED' ), true ) ) {
			return null;
		}
		$first = self::name_case( $r['firstName'] ?? '' );
		$last  = self::name_case( $r['lastName'] ?? '' );
		if ( '' === $first && '' === $last ) {
			return null;
		}
		return array(
			'f'   => $first,
			'l'   => $last,
			'r'   => 'coach' === $role ? 'coach' : 'player',
			'pos' => 'player' === $role ? mb_substr( self::custom_value( $r, 'position' ), 0, 40 ) : '',
			'gy'  => 'player' === $role ? self::custom_value( $r, 'grad' ) : '',
			'ht'  => 'player' === $role ? self::custom_value( $r, 'height' ) : '',
			'cap' => ! empty( $r['isCoCaptain'] ),
		);
	}

	/* ------------------------------------------------------------------ store + sync */

	public static function store( $site_id ) {
		$s = get_option( self::STORE_PRE . preg_replace( '/\D+/', '', (string) $site_id ), array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Apply one batch of registrations to a store. Every registration id the batch names is
	 * first removed from every team (a player who moved team, or left one, moves or goes), then
	 * re-added where it now belongs.
	 */
	public static function apply( array $store, array $rows ) {
		$store['teams'] = (array) ( $store['teams'] ?? array() );
		$where          = (array) ( $store['where'] ?? array() ); // registration id -> team id
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$rid = (string) ( $r['registrationId'] ?? $r['id'] ?? '' );
			if ( '' === $rid ) {
				continue;
			}
			if ( isset( $where[ $rid ] ) ) {
				unset( $store['teams'][ $where[ $rid ] ]['m'][ $rid ] );
				unset( $where[ $rid ] );
			}
			$row = self::public_row( $r );
			if ( ! $row ) {
				continue;
			}
			$tid = (string) (int) $r['teamId'];
			if ( ! isset( $store['teams'][ $tid ] ) ) {
				$store['teams'][ $tid ] = array( 'm' => array() );
			}
			$t =& $store['teams'][ $tid ];
			$t['name']    = mb_substr( wp_strip_all_tags( (string) ( $r['team'] ?? '' ) ), 0, 80 );
			$t['program'] = mb_substr( wp_strip_all_tags( (string) ( $r['programName'] ?? '' ) ), 0, 140 );
			$t['pid']     = (string) (int) ( $r['programId'] ?? 0 );
			$t['end']     = is_numeric( $r['programEndDate'] ?? null ) ? (int) floor( $r['programEndDate'] / 1000 ) : 0;
			$t['start']   = is_numeric( $r['programStartDate'] ?? null ) ? (int) floor( $r['programStartDate'] / 1000 ) : 0;
			$t['sport']   = mb_substr( wp_strip_all_tags( (string) ( $r['sport'] ?? '' ) ), 0, 40 );
			$t['m'][ $rid ] = $row;
			unset( $t );
			$where[ $rid ] = $tid;
		}
		// Teams whose season ended long ago, and teams left empty, are dropped.
		$cut = time() - self::KEEP_DAYS * DAY_IN_SECONDS;
		foreach ( $store['teams'] as $tid => $t ) {
			if ( empty( $t['m'] ) || ( ! empty( $t['end'] ) && $t['end'] < $cut ) ) {
				foreach ( array_keys( (array) ( $t['m'] ?? array() ) ) as $rid ) {
					unset( $where[ $rid ] );
				}
				unset( $store['teams'][ $tid ] );
			}
		}
		$store['where'] = $where;
		return $store;
	}

	/**
	 * Sync a site: incremental from the saved cursor, or a full rebuild (daily, or forced).
	 * A full rebuild builds a NEW store and only swaps it in when it reached the end, so a
	 * timeout never leaves a half-empty roster on the page. Returns the store or WP_Error.
	 */
	public static function sync( $site_id, $force_full = false ) {
		$site_id = preg_replace( '/\D+/', '', (string) $site_id );
		if ( ! isset( self::keys_raw()[ $site_id ] ) ) {
			return new WP_Error( 'ds_roster_key', __( 'No private key saved for this site.', 'ds-toolkit' ) );
		}
		$lock = 'ds_la_roster_lock_' . $site_id;
		if ( false !== get_transient( $lock ) ) {
			return new WP_Error( 'ds_roster_busy', __( 'A roster sync is already running.', 'ds-toolkit' ) );
		}
		set_transient( $lock, 1, self::BUDGET + 30 );

		$old  = self::store( $site_id );
		// A rebuild that stopped half-way is always carried on first, or it would never be swapped in.
		$full = $force_full || ! empty( $old['partial'] ) || empty( $old['full'] ) || time() - (int) $old['full'] > self::FULL_EVERY;
		$work = $full ? ( (array) ( $old['partial'] ?? array() ) ?: array( 'cur' => array( 0, 0 ) ) ) : $old;
		$cur  = (array) ( $work['cur'] ?? array( 0, 0 ) );
		$t0   = microtime( true );
		$budget = self::budget();
		$done = false;
		$err  = '';
		$tok  = self::token( $site_id );
		if ( is_wp_error( $tok ) ) {
			$err = $tok->get_error_message();
		} else {
			for ( $i = 0; $i < self::PAGES_MAX; $i++ ) {
				if ( microtime( true ) - $t0 > $budget ) {
					break;
				}
				$rows = self::page( $site_id, $tok, $cur[0], $cur[1] );
				if ( is_wp_error( $rows ) ) {
					$err = $rows->get_error_message();
					break;
				}
				// The export repeats the cursor row as the first record of the next page.
				if ( $rows && (int) ( $rows[0]['id'] ?? -1 ) === (int) $cur[1] && (int) ( $rows[0]['lastUpdated'] ?? -1 ) === (int) $cur[0] ) {
					array_shift( $rows );
				}
				if ( ! $rows ) {
					$done = true;
					break;
				}
				$work = self::apply( $work, $rows );
				$last = end( $rows );
				$cur  = array( (int) ( $last['lastUpdated'] ?? 0 ), (int) ( $last['id'] ?? 0 ) );
				// Progress is saved page by page: if the host stops this request anyway, the next run
				// carries on from here instead of starting over.
				$work['cur'] = $cur;
				$keep        = $full ? array_merge( $old, array( 'partial' => $work ) ) : $work;
				update_option( self::STORE_PRE . $site_id, $keep, false );
			}
		}
		$work['cur'] = $cur;

		if ( $full ) {
			if ( $done ) {
				$new = $work;
				unset( $new['partial'] );
				$new['full']   = time();
				$new['synced'] = time();
				$new['error']  = '';
			} else {
				$new            = $old; // keep showing the last complete roster
				$new['partial'] = $work; // and carry on from here next run
				$new['error']   = $err;
				// A big club needs more than one run: carry on in a minute, not at the next hourly tick.
				if ( '' === $err && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'ds_la_roster_continue' ) ) {
					wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'ds_la_roster_continue' );
				}
			}
		} else {
			$new           = $work;
			$new['synced'] = $err ? (int) ( $old['synced'] ?? 0 ) : time();
			$new['error']  = $err;
		}
		update_option( self::STORE_PRE . $site_id, $new, false );
		delete_transient( $lock );
		return $err && empty( $new['teams'] ) ? new WP_Error( 'ds_roster_sync', $err ) : $new;
	}

	/**
	 * Seconds this run may spend: the fixed budget, cut down to what is left of the host's own
	 * time limit (measured from the start of the request) minus one page's wait. A local php -S
	 * stops requests at 30 s, WP Engine at about 60 s.
	 */
	public static function budget() {
		$limit = (int) ini_get( 'max_execution_time' );
		if ( $limit <= 0 ) {
			return self::BUDGET;
		}
		$used = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? max( 0, microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) : 0;
		return max( 3, min( self::BUDGET, (int) floor( $limit - $used - self::PAGE_WAIT - 3 ) ) );
	}

	/** Start (or carry on) a sync in the background, right away, without holding up this request. */
	public static function sync_soon() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'ds_la_roster_continue' ) ) {
			wp_schedule_single_event( time(), 'ds_la_roster_continue' );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/** WP-cron handler: every site with a key that is due. */
	public static function cron() {
		foreach ( self::sites_with_keys() as $sid ) {
			$s = self::store( $sid );
			if ( empty( $s['synced'] ) || empty( $s['full'] ) || time() - (int) $s['synced'] >= self::SYNC_EVERY || ! empty( $s['partial'] ) ) {
				self::sync( $sid );
			}
		}
	}

	/* ------------------------------------------------------------------ reading */

	/** Teams for the builder picker: "17 Legends (2026/2027 Surfside Volleyball: 17 Legends)" keyed site:team. */
	public static function team_options() {
		$out = array();
		foreach ( self::sites_with_keys() as $sid ) {
			$teams = (array) ( self::store( $sid )['teams'] ?? array() );
			uasort( $teams, function ( $a, $b ) { return ( $b['start'] ?? 0 ) <=> ( $a['start'] ?? 0 ) ?: strnatcasecmp( $a['name'] ?? '', $b['name'] ?? '' ); } );
			foreach ( $teams as $tid => $t ) {
				$players = count( array_filter( (array) $t['m'], function ( $m ) { return 'player' === $m['r']; } ) );
				$out[ $sid . ':' . $tid ] = sprintf( '%s · %s (%d)', $t['name'] ?? $tid, $t['program'] ?? '', $players );
			}
		}
		return $out;
	}

	/** One team, ready to render, or null. Players by last name, captains noted; coaches apart. */
	public static function team( $key ) {
		if ( ! preg_match( '/^(\d+):(\d+)$/', (string) $key, $m ) ) {
			return null;
		}
		$t = self::store( $m[1] )['teams'][ $m[2] ] ?? null;
		if ( ! $t ) {
			return null;
		}
		$players = array();
		$coaches = array();
		foreach ( (array) $t['m'] as $row ) {
			if ( 'coach' === $row['r'] ) {
				$coaches[] = $row;
			} else {
				$players[] = $row;
			}
		}
		$by = function ( $a, $b ) { return strnatcasecmp( $a['l'] . ' ' . $a['f'], $b['l'] . ' ' . $b['f'] ); };
		usort( $players, $by );
		usort( $coaches, $by );
		return array( 'name' => $t['name'], 'program' => $t['program'], 'sport' => $t['sport'] ?? '', 'start' => $t['start'] ?? 0, 'end' => $t['end'] ?? 0, 'players' => $players, 'coaches' => $coaches );
	}
}
