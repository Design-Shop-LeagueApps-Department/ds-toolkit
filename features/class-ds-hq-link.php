<?php
/**
 * DS Tripwire -> Design Shop HQ link.
 *
 * Sends Tripwire's alerts and a daily check-in to Design Shop HQ (designshophq.wpenginepowered.com), so the team
 * sees the whole fleet on one dashboard instead of in a capped email digest, and a site that goes quiet shows up
 * as Silent. The email alert stays as it is; this is an extra copy.
 *
 * Keys. Each site makes its own Ed25519 key pair (WordPress core ships sodium_compat, so this works on every PHP
 * the fleet runs). The secret half stays in this site's `ds_hq_link` option and is never shown, sent or logged;
 * HQ stores only the public half. Every request is signed over "<install id>\n<time>\n<sha256 body>".
 *
 * Install id. `wpe:<install>.<p>` on WP Engine (PWP_NAME), `fw:<hash of DB name>.<p>` on Flywheel, `site:<hash>.<p>`
 * anywhere else, where <p> is 4 hex of the table prefix (a WP Staging copy shares the database but not the prefix).
 * The id is recomputed on every use: when it no longer matches the stored one, this site is a COPY (blueprint
 * clone, migration, staging copy) and makes a key of its own, telling HQ which install it was copied from. A
 * launch (same install, new domain) keeps its key; HQ accepts the new domain after proving it (below).
 *
 * Proof. HQ checks a new key or a new domain by calling GET /wp-json/ds-toolkit/v1/hq-proof?n=<nonce> on the domain
 * the site claims. This site answers with a signature over "dshq-proof\n<nonce>\n<its own host>". The prefix keeps a
 * proof signature from ever being reusable as a report signature (report messages always start with an install id,
 * which contains ':').
 *
 * Off switch: ds_toolkit_settings.tripwire_hq_report = 0. Endpoint override: the DS_HQ_LINK_ENDPOINT constant or the
 * ds_hq_link_endpoint filter (local testing).
 *
 * @package DS_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DS_HQ_Link {

	const OPT          = 'ds_hq_link';
	const ENROLL_HOOK  = 'ds_hq_link_enroll';
	const ENDPOINT     = 'https://designshophq.wpenginepowered.com/wp-json/dshq/v1/tripwire/';
	const QUEUE_MAX    = 10;
	const RETRY_AFTER  = 43200; // re-enroll at most twice a day

	private static $booted = false;

	/** Called from DS_Tripwire::init(). */
	public static function boot( $settings ) {
		if ( self::$booted || ( isset( $settings['tripwire_hq_report'] ) && ! $settings['tripwire_hq_report'] ) ) {
			return;
		}
		self::$booted = true;
		add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );
		add_action( 'ds_tripwire_checked', array( __CLASS__, 'on_checked' ), 10, 1 );
		add_action( 'ds_tripwire_alert', array( __CLASS__, 'on_alert' ), 10, 2 );
		add_action( self::ENROLL_HOOK, array( __CLASS__, 'enroll' ) );
		add_action( 'admin_post_ds_hq_link_reconnect', array( __CLASS__, 'reconnect' ) );

		// A site with no key (fresh update, new build, a copy) enrolls once, a minute later, from cron.
		if ( ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) && ! self::has_key() && ! wp_next_scheduled( self::ENROLL_HOOK ) ) {
			$st = self::state();
			if ( empty( $st['tried'] ) || time() - (int) $st['tried'] > self::RETRY_AFTER ) {
				wp_schedule_single_event( time() + 60, self::ENROLL_HOOK );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Identity and keys                                                   */
	/* ------------------------------------------------------------------ */

	public static function endpoint() {
		$e = defined( 'DS_HQ_LINK_ENDPOINT' ) ? DS_HQ_LINK_ENDPOINT : self::ENDPOINT;
		return trailingslashit( (string) apply_filters( 'ds_hq_link_endpoint', $e ) );
	}

	public static function install_id() {
		global $table_prefix;
		$p = substr( md5( (string) $table_prefix ), 0, 4 );
		if ( defined( 'PWP_NAME' ) && PWP_NAME ) {
			return 'wpe:' . strtolower( preg_replace( '/[^A-Za-z0-9-]/', '', PWP_NAME ) ) . '.' . $p;
		}
		if ( defined( 'FLYWHEEL_CONFIG_DIR' ) && defined( 'DB_NAME' ) ) {
			return 'fw:' . substr( md5( DB_NAME ), 0, 10 ) . '.' . $p;
		}
		return 'site:' . substr( md5( ( defined( 'DB_HOST' ) ? DB_HOST : '' ) . '|' . ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . '|' . ABSPATH ), 0, 10 ) . '.' . $p;
	}

	public static function platform() {
		if ( defined( 'PWP_NAME' ) ) {
			return 'WP Engine';
		}
		return defined( 'FLYWHEEL_CONFIG_DIR' ) ? 'Flywheel' : 'Other';
	}

	private static function sodium() {
		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}
	}

	public static function state() {
		$s = get_option( self::OPT, array() );
		return is_array( $s ) ? $s : array();
	}

	private static function save( array $s ) {
		update_option( self::OPT, $s, false );
	}

	/** True when this install has its own key (not one copied in from another install). */
	public static function has_key() {
		$s = self::state();
		return ! empty( $s['sk'] ) && ! empty( $s['sid'] ) && self::install_id() === $s['sid'];
	}

	/** This install's key, made now if missing or if the stored one belongs to the install this site was copied from. */
	private static function ensure_key() {
		$s = self::state();
		if ( self::has_key() ) {
			return $s;
		}
		self::sodium();
		$kp = sodium_crypto_sign_keypair();
		$s  = array(
			'sid'      => self::install_id(),
			'pk'       => base64_encode( sodium_crypto_sign_publickey( $kp ) ),
			'sk'       => base64_encode( sodium_crypto_sign_secretkey( $kp ) ),
			'previous' => ! empty( $s['sid'] ) ? (string) $s['sid'] : '',
			'status'   => 'new',
			'created'  => time(),
			'last_t'   => 0,
			'queue'    => array(),
		);
		self::save( $s );
		return $s;
	}

	private static function host() {
		return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/* ------------------------------------------------------------------ */
	/* Transport                                                           */
	/* ------------------------------------------------------------------ */

	/** Sign and POST. Returns [ http code, decoded body ] or [ 0, error message ]. */
	private static function post( $route, array $body, array $s = null ) {
		$s    = $s ?: self::state();
		$json = wp_json_encode( $body );
		$t    = max( time(), (int) ( $s['last_t'] ?? 0 ) + 1 ); // HQ refuses a time that is not newer than the last
		self::sodium();
		$sig = sodium_crypto_sign_detached( $s['sid'] . "\n" . $t . "\n" . hash( 'sha256', $json ), base64_decode( $s['sk'] ) );
		$res = wp_remote_post( self::endpoint() . $route, array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-DSHQ-Site'  => $s['sid'],
				'X-DSHQ-Time'  => (string) $t,
				'X-DSHQ-Sig'   => base64_encode( $sig ),
			),
			'body'    => $json,
		) );
		$s2           = self::state();
		$s2['last_t'] = $t;
		self::save( $s2 );
		if ( is_wp_error( $res ) ) {
			return array( 0, $res->get_error_message() );
		}
		return array( (int) wp_remote_retrieve_response_code( $res ), json_decode( (string) wp_remote_retrieve_body( $res ), true ) );
	}

	/** Introduce this site's public key to HQ. */
	public static function enroll() {
		$s           = self::ensure_key();
		$s['tried']  = time();
		self::save( $s );
		list( $code, $out ) = self::post( 'enroll', array(
			'v'        => 1,
			'kind'     => 'enroll',
			'install'  => $s['sid'],
			'pubkey'   => $s['pk'],
			'previous' => $s['previous'] ?? '',
			'site_url' => home_url(),
			'platform' => self::platform(),
			'toolkit'  => defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '',
		), $s );
		$s = self::state();
		if ( 200 === $code && is_array( $out ) && ! empty( $out['ok'] ) ) {
			$s['status']   = 'pending' === ( $out['status'] ?? '' ) ? 'pending' : 'active';
			$s['enrolled'] = time();
			$s['error']    = '';
		} else {
			$s['error'] = 'Enroll: ' . ( $code ? 'HTTP ' . $code . ' ' . ( is_array( $out ) ? (string) ( $out['code'] ?? '' ) : '' ) : (string) $out );
		}
		self::save( $s );
		return $s;
	}

	/** Findings as Tripwire stores them ("TIER: text") -> [ {tier, text} ]. */
	private static function findings_from_state() {
		$st  = get_option( 'ds_tripwire_state', array() );
		$out = array();
		foreach ( (array) ( is_array( $st ) ? ( $st['last_findings'] ?? array() ) : array() ) as $l ) {
			if ( preg_match( '/^([A-Z]+): (.*)$/s', (string) $l, $m ) ) {
				$out[] = array( 'tier' => $m[1], 'text' => $m[2] );
			}
		}
		return $out;
	}

	private static function payload( $kind, array $findings ) {
		$st  = get_option( 'ds_tripwire_state', array() );
		$st  = is_array( $st ) ? $st : array();
		$set = get_option( 'ds_toolkit_settings', array() );
		$set = is_array( $set ) ? $set : array();
		$c   = isset( $st['content'] ) && is_array( $st['content'] ) ? $st['content'] : array();
		$ln  = get_option( 'ds_tripwire_last_notify', array() );
		$ln  = is_array( $ln ) ? $ln : array();
		return array(
			'v'         => 1,
			'kind'      => $kind,
			'site_url'  => home_url(),
			'login_url' => wp_login_url(), // the masked login URL when Defender masks it
			'platform'  => self::platform(),
			'toolkit'   => defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '',
			'tripwire'  => array(
				'enabled'          => isset( $set['tripwire_enabled'] ) ? (int) $set['tripwire_enabled'] : 1,
				'content_enabled'  => isset( $set['tripwire_content_enabled'] ) ? (int) $set['tripwire_content_enabled'] : 1,
				'last_run'         => (int) ( $st['last_run'] ?? 0 ),
				'content_last_run' => (int) ( $c['last_run'] ?? 0 ),
				'content_scanned'  => (int) ( $c['stats']['scanned'] ?? 0 ),
				'content_total'    => (int) ( $c['stats']['total'] ?? 0 ),
				'content_error'    => (string) ( $c['error'] ?? '' ),
				'last_mail'        => (string) ( $ln['time'] ?? '' ),
				'last_mail_tier'   => (string) ( $ln['tier'] ?? '' ),
			),
			'findings'  => array_values( $findings ),
		);
	}

	/**
	 * Send a report, oldest queued ones first. Never throws and never blocks the scan for long: a failed send is
	 * queued (newest QUEUE_MAX kept) and retried with the next report.
	 */
	public static function send( $kind, array $findings ) {
		if ( 'retired' === ( self::state()['status'] ?? '' ) && self::has_key() ) {
			return; // retired on HQ: stay quiet until someone presses Reconnect on this site (email alerts still go)
		}
		if ( ! self::has_key() ) {
			self::enroll();
		}
		$s       = self::state();
		$queue   = (array) ( $s['queue'] ?? array() );
		$queue[] = self::payload( $kind, $findings );
		$left    = array();
		foreach ( $queue as $i => $body ) {
			if ( $left ) { // HQ unreachable: keep the rest for next time
				$left[] = $body;
				continue;
			}
			list( $code, $out ) = self::post( 'report', $body );
			if ( 200 === $code ) {
				$s2              = self::state();
				$s2['last_ok']   = time();
				$s2['status']    = 'pending' === ( $out['status'] ?? '' ) ? 'pending' : 'active';
				$s2['error']     = '';
				self::save( $s2 );
				continue;
			}
			if ( 403 === $code && 'retired' === ( $out['code'] ?? '' ) ) {
				// Retired on HQ. Never re-enroll by ourselves (that would undo the Retire); drop what is queued.
				$s2           = self::state();
				$s2['status'] = 'retired';
				$s2['queue']  = array();
				$s2['error']  = '';
				self::save( $s2 );
				return;
			}
			if ( 401 === $code && 'unknown_site' === ( $out['code'] ?? '' ) ) {
				// HQ retired this key (Reconnect, or it never knew it): enroll a fresh one, at most twice a day.
				$s2 = self::state();
				if ( empty( $s2['tried'] ) || time() - (int) $s2['tried'] > self::RETRY_AFTER ) {
					unset( $s2['sk'], $s2['pk'] );
					$s2['sid'] = ''; // forces a new key; HQ sees it as a re-key of this install
					self::save( $s2 );
					self::enroll();
					if ( 200 === self::post( 'report', $body )[0] ) { // resend now with the new key
						$s3            = self::state();
						$s3['last_ok'] = time();
						self::save( $s3 );
						continue;
					}
				}
				$left[] = $body;
				continue;
			}
			if ( 0 === $code || $code >= 500 || 429 === $code ) {
				$left[] = $body;
			}
			// Any other refusal (bad signature, replay, malformed) is dropped: resending cannot fix it.
			$s2          = self::state();
			$s2['error'] = 'Report: ' . ( $code ? 'HTTP ' . $code . ' ' . (string) ( is_array( $out ) ? ( $out['code'] ?? '' ) : '' ) : (string) $out );
			self::save( $s2 );
		}
		$s          = self::state();
		$s['queue'] = array_slice( $left, -self::QUEUE_MAX );
		self::save( $s );
	}

	/** After the daily Tripwire run: today's state as a check-in. */
	public static function on_checked( $findings = null ) {
		self::send( 'checkin', self::findings_from_state() );
	}

	/** Every Tripwire alert mail: the same lines as an alert. */
	public static function on_alert( $tier, $lines ) {
		$f = array();
		foreach ( (array) $lines as $l ) {
			if ( preg_match( '/^\[([A-Z]+)\]\s*(.*)$/s', (string) $l, $m ) ) {
				$f[] = array( 'tier' => $m[1], 'text' => $m[2] );
			} else {
				$f[] = array( 'tier' => (string) $tier, 'text' => (string) $l );
			}
		}
		self::send( 'alert', $f );
	}

	/* ------------------------------------------------------------------ */
	/* Proof endpoint and admin                                            */
	/* ------------------------------------------------------------------ */

	public static function rest_routes() {
		register_rest_route( 'ds-toolkit/v1', '/hq-proof', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'proof' ),
			'permission_callback' => '__return_true', // signs a caller's nonce; reveals nothing secret
			'args'                => array( 'n' => array( 'required' => true ) ),
		) );
	}

	public static function proof( WP_REST_Request $r ) {
		$n = (string) $r->get_param( 'n' );
		if ( ! preg_match( '/^[a-f0-9]{16,64}$/', $n ) || ! self::has_key() ) {
			return new WP_Error( 'no_proof', 'Nothing to prove.', array( 'status' => 404 ) );
		}
		self::sodium();
		$s   = self::state();
		$sig = sodium_crypto_sign_detached( "dshq-proof\n" . $n . "\n" . self::host(), base64_decode( $s['sk'] ) );
		$res = rest_ensure_response( array( 'sid' => $s['sid'], 'sig' => base64_encode( $sig ) ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/** Settings screen "Reconnect": drop this site's key and enroll a new one now. */
	public static function reconnect() {
		check_admin_referer( 'ds_hq_link_reconnect' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$s = self::state();
		unset( $s['sk'], $s['pk'] );
		$s['sid']   = '';
		$s['tried'] = 0;
		self::save( $s );
		$s = self::enroll();
		if ( 'active' === ( $s['status'] ?? '' ) ) {
			self::on_checked();
		}
		wp_safe_redirect( add_query_arg( 'ds_hq', 'reconnected', admin_url( 'options-general.php?page=ds-toolkit' ) ) );
		exit;
	}

	/** Ask HQ how it sees this site (the settings card calls this; at most once every 5 minutes). */
	public static function refresh_status() {
		if ( ! self::has_key() || get_transient( 'ds_hq_link_status_checked' ) ) {
			return;
		}
		set_transient( 'ds_hq_link_status_checked', 1, 5 * MINUTE_IN_SECONDS );
		list( $code, $out ) = self::post( 'status', array( 'v' => 1, 'kind' => 'status' ) );
		$s = self::state();
		if ( 200 === $code && is_array( $out ) && ! empty( $out['status'] ) ) {
			$s['status']  = in_array( $out['status'], array( 'active', 'pending', 'retired', 'reconnect' ), true ) ? $out['status'] : $s['status'];
			$s['checked'] = time();
		} elseif ( 401 === $code && 'unknown_site' === ( is_array( $out ) ? ( $out['code'] ?? '' ) : '' ) ) {
			$s['status']  = 'unknown';
			$s['checked'] = time();
		}
		self::save( $s );
	}

	/** One line for the settings card. */
	public static function status_line() {
		self::refresh_status();
		$s = self::state();
		if ( ! self::has_key() ) {
			return array( 'warn', 'Not connected yet. It connects by itself within a few minutes of the next cron run.' );
		}
		$since = ! empty( $s['enrolled'] ) ? wp_date( 'j M Y', (int) $s['enrolled'] ) : '';
		$last  = ! empty( $s['last_ok'] ) ? human_time_diff( (int) $s['last_ok'] ) . ' ago' : 'none yet';
		if ( 'retired' === ( $s['status'] ?? '' ) ) {
			return array( 'bad', 'Retired on Design Shop HQ: this site no longer reports there (email alerts still go). Press Reconnect to ask HQ to take it back; an admin there has to approve it.' );
		}
		if ( in_array( $s['status'] ?? '', array( 'reconnect', 'unknown' ), true ) ) {
			return array( 'warn', 'Design Shop HQ asked this site for a new key. It reconnects by itself with its next report, or press Reconnect now.' );
		}
		if ( 'pending' === ( $s['status'] ?? '' ) ) {
			return array( 'warn', "Waiting for Design Shop HQ to approve this site. Last report: $last." );
		}
		if ( ! empty( $s['error'] ) ) {
			return array( 'bad', 'Connected' . ( $since ? " since $since" : '' ) . ", but the last attempt failed ({$s['error']}). Last report: $last." );
		}
		return array( 'ok', 'Connected to Design Shop HQ' . ( $since ? " since $since" : '' ) . ". Last report: $last." );
	}
}
