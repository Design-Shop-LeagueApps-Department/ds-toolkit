<?php
/**
 * Design Shop HQ -> ds-toolkit updates. The site pulls; HQ can only say when.
 *
 * HQ can ask this site to update ds-toolkit, but it can never choose WHAT gets installed:
 *   1. The order rides in HQ's reply to one of this site's own signed reports:
 *      {"target": "1.10.27", "t": <time>, "sig": <base64>}, signed with HQ's key (DS_HQ_Link::HQ_PUBKEY) over
 *      "dshq-update\n<this install id>\n<target>\n<t>". An order for another install, an old one (ORDER_SKEW), a
 *      badly signed one, a pre-release, or one that is not newer than the installed copy is ignored.
 *   2. The site downloads that version itself, from the official GitHub repo only (REPO: a published, stable release
 *      tagged v<target>), together with ds-toolkit.zip.sig: an Ed25519 signature over
 *      "ds-toolkit-release\n<version>\n<sha256 of the zip>" by the release key (RELEASE_PUBKEYS), whose private half
 *      never lives on HQ. Both version lines inside the zip (header and DS_TOOLKIT_VERSION) must say <target>.
 *   3. WordPress's own automatic updater installs it, for ds-toolkit only (backup, maintenance mode for a few seconds,
 *      crash check and rollback on WordPress 6.6+), from a one-off cron event a few minutes later, holding WordPress's
 *      own auto-update lock so it never runs beside a normal auto-update.
 *   4. The result (done, or failed and why) reaches HQ with a check-in a minute after the install, so it is sent by
 *      the new code. A target that failed MAX_TRIES times is not tried again; HQ has to name a newer one.
 * A stolen HQ key can therefore at most make sites install a real, signed release sooner. Nothing else.
 *
 * Off switch: define( 'DS_HQ_UPDATE_DISABLED', true ) in wp-config.php makes this site ignore every order.
 * Testing: the DS_TOOLKIT_RELEASE_PUBKEY constant replaces the release key (only someone who can already edit
 * wp-config.php can set it, and they can run code anyway).
 *
 * @package DS_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DS_HQ_Update {

	const OPT             = 'ds_hq_update';
	const HOOK            = 'ds_hq_update_run';
	const LOCK_OPT        = 'ds_hq_update_lock';
	const REPO            = 'Design-Shop-LeagueApps-Department/ds-toolkit';
	const PLUGIN          = 'ds-toolkit/ds-toolkit.php';
	const RELEASE_PUBKEYS = array( '0D3wnvnMHv1MsMwK8eYvpNFJQN6p8q0vFtLYBmyFd1w=' ); // private half: never on HQ
	const ORDER_SKEW      = 900;      // an order is good for 15 minutes after HQ signed it
	const MAX_TRIES       = 3;        // per target version
	const STUCK_AFTER     = 1800;     // a "running" update older than this was killed (it is retried)
	const MAX_ZIP         = 52428800; // 50 MB

	/** Called from DS_HQ_Link::boot(). */
	public static function boot() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	public static function state() {
		$s = get_option( self::OPT, array() );
		return is_array( $s ) ? $s : array();
	}

	private static function save( array $s ) {
		update_option( self::OPT, $s, false );
	}

	/** The version in the plugin header on disk (DS_TOOLKIT_VERSION is stale in the request that installed an update). */
	public static function installed() {
		$file = ( defined( 'DS_TOOLKIT_PATH' ) ? DS_TOOLKIT_PATH : WP_PLUGIN_DIR . '/ds-toolkit/' ) . 'ds-toolkit.php';
		if ( function_exists( 'get_file_data' ) && is_readable( $file ) ) {
			$h = get_file_data( $file, array( 'Version' => 'Version' ) );
			if ( ! empty( $h['Version'] ) ) {
				return (string) $h['Version'];
			}
		}
		return defined( 'DS_TOOLKIT_VERSION' ) ? DS_TOOLKIT_VERSION : '';
	}

	private static function sodium() {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}
	}

	private static function verify( $sig_b64, $message, $pub_b64 ) {
		$sig = base64_decode( (string) $sig_b64, true );
		$pub = base64_decode( (string) $pub_b64, true );
		if ( ! $sig || 64 !== strlen( $sig ) || ! $pub || 32 !== strlen( $pub ) ) {
			return false;
		}
		self::sodium();
		try {
			return sodium_crypto_sign_verify_detached( $sig, $message, $pub );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Is this an order HQ signed for this install, still fresh, for a newer stable version?
	 * Returns [ true, target ] or [ false, reason ].
	 */
	public static function order_ok( $order, $sid, $installed, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		if ( ! is_array( $order ) ) {
			return array( false, 'no order' );
		}
		$target = (string) ( $order['target'] ?? '' );
		$t      = (int) ( $order['t'] ?? 0 );
		if ( ! preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,4}$/', $target ) ) {
			return array( false, 'not a stable version number' );
		}
		if ( abs( $now - $t ) > self::ORDER_SKEW ) {
			return array( false, 'order too old (or clock off)' );
		}
		$pub = defined( 'DS_HQ_LINK_PUBKEY' ) ? DS_HQ_LINK_PUBKEY : ( class_exists( 'DS_HQ_Link' ) ? DS_HQ_Link::HQ_PUBKEY : '' );
		if ( ! self::verify( $order['sig'] ?? '', "dshq-update\n$sid\n$target\n$t", $pub ) ) {
			return array( false, 'bad HQ signature' );
		}
		if ( ! version_compare( $target, (string) $installed, '>' ) ) {
			return array( false, 'not newer than the installed ' . $installed );
		}
		return array( true, $target );
	}

	/** Does $sig_b64 sign this release (version + zip sha256) with a release key? */
	public static function release_sig_ok( $version, $sha256, $sig_b64 ) {
		$keys = defined( 'DS_TOOLKIT_RELEASE_PUBKEY' ) ? array( DS_TOOLKIT_RELEASE_PUBKEY ) : self::RELEASE_PUBKEYS;
		foreach ( $keys as $k ) {
			if ( self::verify( trim( (string) $sig_b64 ), "ds-toolkit-release\n$version\n" . strtolower( (string) $sha256 ), $k ) ) {
				return true;
			}
		}
		return false;
	}

	/** Both version lines of a ds-toolkit.php source: [ header, define ]. */
	public static function versions_in( $php ) {
		$h = preg_match( '/^[ \t\/*#@]*Version:\s*(\S+)/mi', (string) $php, $m ) ? $m[1] : '';
		$d = preg_match( "/define\(\s*'DS_TOOLKIT_VERSION'\s*,\s*'([^']+)'/", (string) $php, $n ) ? $n[1] : '';
		return array( $h, $d );
	}

	/**
	 * HQ's reply to a report carried an update order (DS_HQ_Link::post()). Check it and queue the install.
	 * Never installs in this request: it runs from cron a few minutes later.
	 */
	public static function take_order( $order, $sid ) {
		if ( defined( 'DS_HQ_UPDATE_DISABLED' ) && DS_HQ_UPDATE_DISABLED ) {
			return;
		}
		$installed = self::installed();
		list( $ok, $target ) = self::order_ok( $order, $sid, $installed );
		$s = self::state();
		if ( ! $ok ) {
			$s['refused'] = array( 'reason' => $target, 'at' => time() );
			self::save( $s );
			return;
		}
		$same = ( $s['target'] ?? '' ) === $target;
		$st   = $s['status'] ?? '';
		if ( $same && 'queued' === $st && wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		if ( $same && 'running' === $st && time() - (int) ( $s['started'] ?? 0 ) < self::STUCK_AFTER ) {
			return;
		}
		if ( $same && (int) ( $s['tries'] ?? 0 ) >= self::MAX_TRIES ) {
			return; // gave up on this version; HQ sees why in the reports
		}
		$s = array(
			'target' => $target,
			'status' => 'queued',
			'from'   => $installed,
			'queued' => time(),
			'tries'  => $same ? (int) ( $s['tries'] ?? 0 ) : 0,
			'error'  => $same ? (string) ( $s['error'] ?? '' ) : '',
		);
		self::save( $s );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// A few minutes later, spread out so a wave of sites does not hit GitHub in the same second.
			wp_schedule_single_event( time() + ( function_exists( 'wp_rand' ) ? wp_rand( 60, 300 ) : 120 ), self::HOOK );
		}
	}

	/** One update at a time on this site (INSERT IGNORE is atomic; see DS_HQ_Link::lock()). */
	private static function lock() {
		global $wpdb;
		$take = function () use ( $wpdb ) {
			return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::LOCK_OPT, (string) time() ) );
		};
		if ( $take() ) {
			return true;
		}
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPT ) );
		if ( time() - $since > self::STUCK_AFTER ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK_OPT, (string) $since ) );
			return $take();
		}
		return false;
	}

	private static function unlock() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPT ) );
		wp_cache_delete( self::LOCK_OPT, 'options' );
	}

	/** The cron job: install the queued target. */
	public static function run() {
		$s = self::state();
		if ( 'queued' !== ( $s['status'] ?? '' ) || empty( $s['target'] ) || ( defined( 'DS_HQ_UPDATE_DISABLED' ) && DS_HQ_UPDATE_DISABLED ) ) {
			return;
		}
		if ( ! self::lock() ) {
			return;
		}
		try {
			$s['status']  = 'running';
			$s['started'] = time();
			$s['tries']   = (int) ( $s['tries'] ?? 0 ) + 1;
			self::save( $s );
			$res = self::install( (string) $s['target'] );
			$s   = self::state();
			$s['to']       = self::installed();
			$s['finished'] = time();
			if ( is_wp_error( $res ) ) {
				$s['status'] = 'failed';
				$s['error']  = substr( $res->get_error_code() . ': ' . $res->get_error_message(), 0, 300 );
				if ( 'busy' === $res->get_error_code() ) {
					$s['status'] = 'queued'; // WordPress was updating something else: try again shortly, not counted
					$s['tries']  = max( 0, (int) $s['tries'] - 1 );
					wp_schedule_single_event( time() + 600, self::HOOK );
				}
			} else {
				$s['status'] = 'done';
				$s['error']  = '';
			}
			self::save( $s );
		} finally {
			self::unlock();
		}
		// Tell HQ how it went, from a fresh request a minute from now (that one runs the new code).
		if ( class_exists( 'DS_HQ_Link' ) ) {
			wp_clear_scheduled_hook( DS_HQ_Link::CHECKIN_HOOK );
			wp_schedule_single_event( time() + 60, DS_HQ_Link::CHECKIN_HOOK );
		}
	}

	/** What HQ gets in every report. */
	public static function report() {
		$s = self::state();
		$o = array();
		foreach ( array( 'target', 'status', 'from', 'to', 'error', 'tries', 'queued', 'finished' ) as $k ) {
			if ( isset( $s[ $k ] ) ) {
				$o[ $k ] = $s[ $k ];
			}
		}
		if ( ! empty( $s['refused'] ) ) {
			$o['refused'] = $s['refused'];
		}
		return $o;
	}

	private static function github_get( $url, $timeout = 20 ) {
		return wp_remote_get( $url, array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'headers'     => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'ds-toolkit/' . self::installed() ),
		) );
	}

	/** Download, verify and install $target. Returns true or WP_Error (the installed plugin is untouched on any error before step 5). */
	public static function install( $target ) {
		if ( self::installed() === $target ) {
			return true; // already there (installed some other way)
		}
		if ( ! function_exists( 'plugin_basename' ) || plugin_basename( ( defined( 'DS_TOOLKIT_PATH' ) ? DS_TOOLKIT_PATH : '' ) . 'ds-toolkit.php' ) !== self::PLUGIN ) {
			return new WP_Error( 'folder', 'the plugin is not installed as wp-content/plugins/ds-toolkit/; update it by hand' );
		}

		// 1. The release, from the official repo only: published, stable, exactly this tag.
		$tag = 'v' . $target;
		$res = self::github_get( 'https://api.github.com/repos/' . self::REPO . '/releases/tags/' . $tag );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'release', 'GitHub release ' . $tag . ' not readable (' . ( is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) ) . ')' );
		}
		$rel = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $rel ) || ( $rel['tag_name'] ?? '' ) !== $tag || ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) {
			return new WP_Error( 'release', $tag . ' is not a published stable release' );
		}
		$base = 'https://github.com/' . self::REPO . '/releases/download/' . $tag . '/';
		$have = array();
		foreach ( (array) ( $rel['assets'] ?? array() ) as $a ) {
			$have[ (string) ( $a['name'] ?? '' ) ] = (string) ( $a['browser_download_url'] ?? '' );
		}
		if ( ( $have['ds-toolkit.zip'] ?? '' ) !== $base . 'ds-toolkit.zip' || ( $have['ds-toolkit.zip.sig'] ?? '' ) !== $base . 'ds-toolkit.zip.sig' ) {
			return new WP_Error( 'release', $tag . ' has no ds-toolkit.zip with a ds-toolkit.zip.sig signature' );
		}

		// 2. Signature, then the zip itself.
		$sr = self::github_get( $base . 'ds-toolkit.zip.sig' );
		if ( is_wp_error( $sr ) || 200 !== (int) wp_remote_retrieve_response_code( $sr ) ) {
			return new WP_Error( 'download', 'signature file not downloadable' );
		}
		$sig = trim( (string) wp_remote_retrieve_body( $sr ) );
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = download_url( $base . 'ds-toolkit.zip', 300 );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'download', 'zip not downloadable (' . $tmp->get_error_message() . ')' );
		}
		try {
			$size = (int) @filesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $size < 1000 || $size > self::MAX_ZIP ) {
				return new WP_Error( 'download', 'zip has an unexpected size (' . $size . ' bytes)' );
			}
			// 3. Signed by the release key, and the code inside says the same version on both lines.
			if ( ! self::release_sig_ok( $target, hash_file( 'sha256', $tmp ), $sig ) ) {
				return new WP_Error( 'signature', 'the zip does not match its release signature; nothing installed' );
			}
			$main = self::read_from_zip( $tmp, 'ds-toolkit/ds-toolkit.php' );
			if ( null === $main ) {
				return new WP_Error( 'zip', 'ds-toolkit/ds-toolkit.php not found in the zip' );
			}
			list( $hv, $dv ) = self::versions_in( $main );
			if ( $hv !== $target || $dv !== $target ) {
				return new WP_Error( 'zip', "the zip's version lines say $hv / $dv, not $target" );
			}
			// 4. Install through WordPress.
			return self::wp_install( $target, $tmp );
		} finally {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/** One file from a zip as a string, or null. ZipArchive, else WordPress's bundled PclZip. */
	private static function read_from_zip( $zip, $name ) {
		if ( class_exists( 'ZipArchive' ) ) {
			$z = new ZipArchive();
			if ( true === $z->open( $zip ) ) {
				$c = $z->getFromName( $name );
				$z->close();
				return false === $c ? null : $c;
			}
			return null;
		}
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		$p   = new PclZip( $zip );
		$out = $p->extract( PCLZIP_OPT_BY_NAME, $name, PCLZIP_OPT_EXTRACT_AS_STRING );
		return is_array( $out ) && isset( $out[0]['content'] ) ? (string) $out[0]['content'] : null;
	}

	/** WordPress's automatic updater, offered exactly this verified local zip, for ds-toolkit only. */
	private static function wp_install( $target, $zip ) {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! WP_Upgrader::create_lock( 'auto_updater' ) ) {
			return new WP_Error( 'busy', 'WordPress is running its own automatic updates; trying again in 10 minutes' );
		}
		$item = (object) array(
			'id'          => 'ds-toolkit',
			'slug'        => 'ds-toolkit',
			'plugin'      => self::PLUGIN,
			'new_version' => $target,
			'url'         => 'https://github.com/' . self::REPO,
			'package'     => $zip, // a local path: the upgrader installs it as is and does not download anything
			'autoupdate'  => true,
		);
		// The upgrader reads the package from the update list, where the toolkit's own updater puts GitHub's latest
		// release: offer this verified file instead, for this run only.
		$offer = function ( $t ) use ( $item ) {
			if ( ! is_object( $t ) ) {
				$t = (object) array( 'last_checked' => time(), 'checked' => array(), 'response' => array(), 'no_update' => array() );
			}
			$t->response                 = isset( $t->response ) && is_array( $t->response ) ? $t->response : array();
			$t->response[ self::PLUGIN ] = $item;
			if ( isset( $t->no_update ) && is_array( $t->no_update ) ) {
				unset( $t->no_update[ self::PLUGIN ] );
			}
			return $t;
		};
		$allow = function ( $update, $it ) {
			return ( isset( $it->plugin ) && self::PLUGIN === $it->plugin ) ? true : $update;
		};
		add_filter( 'site_transient_update_plugins', $offer, 999 );
		add_filter( 'auto_update_plugin', $allow, 999, 2 );          // WP Engine forces plugin auto-updates off
		add_filter( 'automatic_updater_disabled', '__return_false', 999 );
		add_filter( 'wp_doing_cron', '__return_true', 999 );          // keeps the plugin active through the swap
		$was_active = is_plugin_active( self::PLUGIN );
		try {
			$result = ( new WP_Automatic_Updater() )->update( 'plugin', $item );
		} finally {
			remove_filter( 'site_transient_update_plugins', $offer, 999 );
			remove_filter( 'auto_update_plugin', $allow, 999 );
			remove_filter( 'automatic_updater_disabled', '__return_false', 999 );
			remove_filter( 'wp_doing_cron', '__return_true', 999 );
			WP_Upgrader::release_lock( 'auto_updater' );
		}
		wp_clean_plugins_cache( false );
		if ( $was_active && ! is_plugin_active( self::PLUGIN ) ) {
			activate_plugin( self::PLUGIN, '', false, true );
		}
		$now = self::installed();
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'install', $result->get_error_message() . " (still on $now)" );
		}
		if ( false === $result ) {
			return new WP_Error( 'install', "WordPress declined the update: the files are not writable without FTP details, or the site is a version-control checkout (still on $now)" );
		}
		if ( $now !== $target ) {
			return new WP_Error( 'install', "the update ran but the plugin says $now, not $target" );
		}
		return true;
	}

	/** On uninstall and when reporting is switched off. */
	public static function unschedule() {
		if ( function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( self::HOOK ) ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}

	/** One line for the settings card, or ''. */
	public static function status_line() {
		$s = self::state();
		if ( empty( $s['target'] ) ) {
			return '';
		}
		$when = ! empty( $s['finished'] ) ? ' (' . human_time_diff( (int) $s['finished'] ) . ' ago)' : '';
		switch ( $s['status'] ?? '' ) {
			case 'done':
				return 'Updated to ' . $s['target'] . ' by Design Shop HQ' . $when . '.';
			case 'failed':
				return 'Design Shop HQ asked for ' . $s['target'] . ' but the update failed' . $when . ': ' . ( $s['error'] ?? '' );
			case 'queued':
			case 'running':
				return 'Design Shop HQ asked for ' . $s['target'] . '; it installs within a few minutes.';
		}
		return '';
	}
}
