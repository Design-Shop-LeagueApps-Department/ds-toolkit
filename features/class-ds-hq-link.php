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
 * Check-ins. Besides the copy after every Tripwire scan, the site checks in on its own interval, which HQ sets for the
 * whole fleet (HQ returns `checkin_every` in every reply; default 24 h until HQ has said otherwise). A check-in
 * sends the latest scan state; it never starts a scan. HQ's "Check in now" button POSTs to
 * /wp-json/ds-toolkit/v1/hq-checkin-now, which sends one check-in at once (at most once a minute).
 *
 * HQ-signed calls. hq-proof and hq-checkin-now only answer a call HQ signed with its own key (public half in
 * HQ_PUBKEY, overridable with the DS_HQ_LINK_PUBKEY constant for a local HQ): a signature over
 * "dshq-hq\n<proof|checkin-now>\n<this host>\n<time>\n<nonce>" in X-DSHQ-HQ-Sig, time within 5 minutes. Anyone else
 * gets a 404, so the routes are no CPU amplifier and do not reveal the install id.
 *
 * Sending never happens inside the request that raised the alert. Reports go into a queue (newest QUEUE_MAX kept)
 * and a one-off cron event (FLUSH_HOOK) sends them, one locked flush at a time, at most FLUSH_MAX per run, saving
 * the queue after every accepted report. In cron or WP-CLI (the scans) it flushes straight away.
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
	const CHECKIN_HOOK = 'ds_hq_link_checkin';
	const EVERY_MIN    = 900;    // 15 minutes
	const EVERY_MAX    = 86400;  // 24 hours, also the default
	const FLUSH_HOOK   = 'ds_hq_link_flush';
	const FLUSH_MAX    = 5;      // reports sent per flush run
	const LOCK_OPT     = 'ds_hq_link_lock';
	const HQ_PUBKEY    = 'jx502Rgzondxga5IEo7/EHxAVJml9e9a9EN9dlqRF38='; // Design Shop HQ's public key
	const HQ_SKEW      = 300;

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
		add_action( self::CHECKIN_HOOK, array( __CLASS__, 'on_checked' ) );
		add_action( self::FLUSH_HOOK, array( __CLASS__, 'flush' ) );
		add_action( 'admin_post_ds_hq_link_reconnect', array( __CLASS__, 'reconnect' ) );

		// The site's own check-in timer (interval set by HQ). Re-armed after every send; armed here if it went missing.
		if ( ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) && self::has_key()
			&& 'retired' !== ( self::state()['status'] ?? '' ) && ! wp_next_scheduled( self::CHECKIN_HOOK ) ) {
			self::schedule_next();
		}

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
		// Multisite: every subsite shares PWP_NAME, DB_NAME and $table_prefix, so the blog id keeps their ids apart.
		$p = substr( md5( (string) $table_prefix ), 0, 4 ) . ( function_exists( 'is_multisite' ) && is_multisite() ? '.b' . (int) get_current_blog_id() : '' );
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

	/** Seconds between check-ins, as HQ last asked (clamped). */
	public static function every() {
		$e = (int) ( self::state()['every'] ?? self::EVERY_MAX );
		return max( self::EVERY_MIN, min( self::EVERY_MAX, $e ?: self::EVERY_MAX ) );
	}

	/** One single event at now + interval; any earlier one is replaced, so a changed interval applies at once. */
	public static function schedule_next() {
		wp_clear_scheduled_hook( self::CHECKIN_HOOK );
		wp_schedule_single_event( time() + self::every(), self::CHECKIN_HOOK );
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
	private static function post( $route, array $body, ?array $s = null, $timeout = 10 ) {
		$s    = $s ?: self::state();
		$json = wp_json_encode( $body );
		$t    = max( time(), (int) ( $s['last_t'] ?? 0 ) + 1 ); // HQ refuses a time that is not newer than the last
		self::sodium();
		$sig = sodium_crypto_sign_detached( $s['sid'] . "\n" . $t . "\n" . hash( 'sha256', $json ), base64_decode( $s['sk'] ) );
		$res = wp_remote_post( self::endpoint() . $route, array(
			'timeout' => $timeout,
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
		$code = (int) wp_remote_retrieve_response_code( $res );
		$out  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( is_array( $out ) && ! empty( $out['checkin_every'] ) ) {
			$s3          = self::state();
			$new         = max( self::EVERY_MIN, min( self::EVERY_MAX, (int) $out['checkin_every'] ) );
			$changed     = (int) ( $s3['every'] ?? 0 ) !== $new;
			$s3['every'] = $new;
			self::save( $s3 );
			if ( $changed && function_exists( 'wp_schedule_single_event' ) ) {
				self::schedule_next(); // HQ changed the interval: re-time now, not after the old wait
			}
		}
		return array( $code, $out );
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
	 * Queue a report and get it sent without holding up the caller: in cron or WP-CLI (the scans) it is sent now,
	 * anywhere else (an admin creating a user, a page view) a one-off cron event sends it.
	 */
	public static function send( $kind, array $findings ) {
		if ( 'retired' === ( self::state()['status'] ?? '' ) && self::has_key() ) {
			return; // retired on HQ: stay quiet until someone presses Reconnect on this site (email alerts still go)
		}
		self::enqueue( self::payload( $kind, $findings ) );
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			self::flush();
		} elseif ( ! wp_next_scheduled( self::FLUSH_HOOK ) ) {
			wp_schedule_single_event( time(), self::FLUSH_HOOK );
		}
	}

	private static function enqueue( array $payload ) {
		$s          = self::state();
		$q          = (array) ( $s['queue'] ?? array() );
		$q[]        = $payload;
		$s['queue'] = array_slice( $q, -self::QUEUE_MAX );
		self::save( $s );
	}

	/**
	 * One flush at a time. INSERT IGNORE on the options table's unique option_name is atomic, so of two processes
	 * exactly one gets the row (add_option() is not: it checks first and then upserts, so both can "win").
	 * A lock older than 5 minutes is stale (a killed request) and is taken over.
	 */
	private static function lock() {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return add_option( self::LOCK_OPT, time(), '', 'no' );
		}
		$take = function () use ( $wpdb ) {
			return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::LOCK_OPT, (string) time() ) );
		};
		if ( $take() ) {
			return true;
		}
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPT ) );
		if ( time() - $since > 300 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK_OPT, (string) $since ) );
			return $take();
		}
		return false;
	}

	private static function unlock() {
		global $wpdb;
		if ( is_object( $wpdb ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPT ) );
			wp_cache_delete( self::LOCK_OPT, 'options' );
		} else {
			delete_option( self::LOCK_OPT );
		}
	}

	/** Remove the first queued report once HQ has it, and save at once, so a killed request never sends it twice. */
	private static function shift() {
		$s = self::state();
		$q = (array) ( $s['queue'] ?? array() );
		array_shift( $q );
		$s['queue'] = $q;
		self::save( $s );
	}

	/** Send queued reports, oldest first, at most $max of them. Anything HQ could not take stays queued. */
	public static function flush( $max = self::FLUSH_MAX ) {
		if ( ! self::lock() ) {
			return;
		}
		try {
			if ( ! self::has_key() ) {
				self::enroll();
			}
			for ( $i = 0; $i < (int) $max; $i++ ) {
				$s = self::state();
				if ( 'retired' === ( $s['status'] ?? '' ) || empty( $s['queue'] ) ) {
					break;
				}
				$body = reset( $s['queue'] );
				list( $code, $out ) = self::post( 'report', (array) $body );
				if ( 200 === $code ) {
					self::shift();
					$s2            = self::state();
					$s2['last_ok'] = time();
					$s2['status']  = 'pending' === ( $out['status'] ?? '' ) ? 'pending' : 'active';
					$s2['error']   = '';
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
					break;
				}
				if ( 401 === $code && 'unknown_site' === ( $out['code'] ?? '' ) ) {
					// HQ retired this key (Reconnect, or it never knew it): enroll a fresh one, at most twice a day,
					// and the loop resends the same report with the new key.
					$s2 = self::state();
					if ( empty( $s2['tried'] ) || time() - (int) $s2['tried'] > self::RETRY_AFTER ) {
						unset( $s2['sk'], $s2['pk'] );
						$s2['sid'] = ''; // forces a new key; HQ sees it as a re-key of this install
						self::save( $s2 );
						self::enroll();
						continue;
					}
					break;
				}
				$s2          = self::state();
				$s2['error'] = 'Report: ' . ( $code ? 'HTTP ' . $code . ' ' . (string) ( is_array( $out ) ? ( $out['code'] ?? '' ) : '' ) : (string) $out );
				self::save( $s2 );
				// Unreachable, overloaded, or a time clash with another sender (409 replay): keep it for next time.
				if ( 0 === $code || $code >= 500 || 429 === $code || 409 === $code ) {
					break;
				}
				self::shift(); // any other refusal (bad signature, malformed) cannot be fixed by resending
			}
		} finally {
			self::unlock();
		}
		$s = self::state();
		if ( 'retired' !== ( $s['status'] ?? '' ) ) {
			self::schedule_next();
			if ( ! empty( $s['queue'] ) && ! wp_next_scheduled( self::FLUSH_HOOK ) ) {
				wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::FLUSH_HOOK ); // retry what is left
			}
		}
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
		register_rest_route( 'ds-toolkit/v1', '/hq-checkin-now', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'checkin_now' ),
			'permission_callback' => '__return_true', // can only make this site report to HQ, once a minute
		) );
		register_rest_route( 'ds-toolkit/v1', '/hq-proof', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'proof' ),
			'permission_callback' => '__return_true', // signs a caller's nonce; reveals nothing secret
			'args'                => array( 'n' => array( 'required' => true ) ),
		) );
	}

	/** True when the request carries HQ's signature for $what on this host (see the class docblock). */
	public static function hq_signed( WP_REST_Request $r, $what, $nonce = '' ) {
		$t   = (int) $r->get_header( 'x_dshq_hq_time' );
		$sig = base64_decode( (string) $r->get_header( 'x_dshq_hq_sig' ), true );
		$pub = base64_decode( defined( 'DS_HQ_LINK_PUBKEY' ) ? DS_HQ_LINK_PUBKEY : self::HQ_PUBKEY, true );
		if ( ! $sig || 64 !== strlen( $sig ) || ! $pub || 32 !== strlen( $pub ) || abs( time() - $t ) > self::HQ_SKEW ) {
			return false;
		}
		self::sodium();
		return sodium_crypto_sign_verify_detached( $sig, "dshq-hq\n$what\n" . self::host() . "\n$t\n$nonce", $pub );
	}

	private static function not_found() {
		return new WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', array( 'status' => 404 ) );
	}

	/** HQ's "Check in now": send one check-in at once. */
	public static function checkin_now( WP_REST_Request $r ) {
		if ( ! self::hq_signed( $r, 'checkin-now' ) ) {
			return self::not_found();
		}
		if ( ! self::has_key() || 'retired' === ( self::state()['status'] ?? '' ) ) {
			return new WP_Error( 'not_connected', 'This site is not connected to HQ.', array( 'status' => 409 ) );
		}
		if ( get_transient( 'ds_hq_link_ping' ) ) {
			return new WP_Error( 'slow_down', 'A check-in was sent less than a minute ago.', array( 'status' => 429 ) );
		}
		set_transient( 'ds_hq_link_ping', 1, MINUTE_IN_SECONDS );
		$before = (int) ( self::state()['last_ok'] ?? 0 );
		self::enqueue( self::payload( 'checkin', self::findings_from_state() ) );
		self::flush( 2 ); // HQ is waiting on this request: this check-in plus at most one older report
		$res = rest_ensure_response( array( 'ok' => (int) ( self::state()['last_ok'] ?? 0 ) > $before ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	public static function proof( WP_REST_Request $r ) {
		$n = (string) $r->get_param( 'n' );
		if ( ! preg_match( '/^[a-f0-9]{16,64}$/', $n ) || ! self::has_key() || ! self::hq_signed( $r, 'proof', $n ) ) {
			return self::not_found();
		}
		self::sodium();
		$s   = self::state();
		$sig = sodium_crypto_sign_detached( "dshq-proof\n" . $n . "\n" . self::host(), base64_decode( $s['sk'] ) );
		$res = rest_ensure_response( array( 'sig' => base64_encode( $sig ) ) );
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
			self::send( 'checkin', self::findings_from_state() );
		}
		wp_safe_redirect( add_query_arg( 'ds_hq', 'reconnected', admin_url( 'options-general.php?page=ds-toolkit' ) ) );
		exit;
	}

	/** When reporting is switched off, and on uninstall: no HQ events left behind. */
	public static function unschedule() {
		foreach ( array( self::CHECKIN_HOOK, self::ENROLL_HOOK, self::FLUSH_HOOK ) as $h ) {
			if ( wp_next_scheduled( $h ) ) {
				wp_clear_scheduled_hook( $h );
			}
		}
	}

	/** Ask HQ how it sees this site (the settings card calls this; at most once every 5 minutes). */
	public static function refresh_status() {
		if ( ! self::has_key() || get_transient( 'ds_hq_link_status_checked' ) ) {
			return;
		}
		set_transient( 'ds_hq_link_status_checked', 1, 5 * MINUTE_IN_SECONDS );
		// Runs while the settings page renders, so a slow HQ costs at most 3 seconds, once per 5 minutes.
		list( $code, $out ) = self::post( 'status', array( 'v' => 1, 'kind' => 'status' ), null, 3 );
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
		$next = wp_next_scheduled( self::CHECKIN_HOOK );
		$nxt  = $next ? ' Next check-in in ' . human_time_diff( time(), (int) $next ) . '.' : '';
		return array( 'ok', 'Connected to Design Shop HQ' . ( $since ? " since $since" : '' ) . ". Last report: $last." . $nxt );
	}
}
