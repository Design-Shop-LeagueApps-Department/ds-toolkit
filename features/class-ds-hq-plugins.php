<?php
/**
 * Design Shop HQ -> wordpress.org plugin updates. HQ says WHICH plugin and version; the site checks everything.
 *
 * Built after Nested Pages 3.3.5 (24 Nov 2026) broke wp-admin on 50 fleet sites and the fix (3.3.6) had to go out by
 * hand, site by site. With this, HQ can send "update <slug> to <version>" to the sites that need it:
 *   1. The order rides in HQ's reply to one of this site's own signed reports, as a list under "plugins":
 *      {"slug": "wp-nested-pages", "version": "3.3.6", "t": <time>, "sig": <base64>}, signed with HQ's key
 *      (DS_HQ_Link::HQ_PUBKEY) over "dshq-plugin\n<this install id>\n<slug>\n<version>\n<t>". The message starts
 *      "dshq-plugin", so it can never pass for a ds-toolkit order ("dshq-update") or the other way round.
 *      Ignored: another install's order, an old one (ORDER_SKEW), a bad signature, a slug that is not installed here,
 *      a version that is not newer than the installed one.
 *   2. The installed copy must be the wordpress.org plugin: its main file must match wordpress.org's published
 *      checksums for the installed version. A custom or premium plugin whose folder happens to share a wordpress.org
 *      slug (or a tampered copy) is refused, never replaced.
 *   3. The new version comes only from downloads.wordpress.org (fixed addresses, no other host), and every file in the
 *      zip must match wordpress.org's checksums for that version, with nothing missing and nothing extra.
 *   4. WordPress's own automatic updater installs exactly that verified local zip (backup, maintenance for a few
 *      seconds, crash check and rollback on WordPress 6.6+), from a one-off cron event, holding WordPress's own
 *      auto-update lock. An active plugin stays active. The result goes back to HQ with the next check-in.
 * So a stolen HQ key can at most make a site install an official wordpress.org release of a plugin it already runs.
 * Never: on a multisite subsite, with DISALLOW_FILE_MODS, on WordPress older than 6.6, or as a downgrade.
 *
 * Off switch: define( 'DS_HQ_PLUGINS_DISABLED', true ) in wp-config.php. (DS_HQ_UPDATE_DISABLED, which holds
 * ds-toolkit itself on a test build, does not stop plugin fixes.)
 *
 * @package DS_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/includes/class-ds-lock.php';

class DS_HQ_Plugins {

	const OPT         = 'ds_hq_plugin_updates';
	const HOOK        = 'ds_hq_plugin_update_run';
	const LOCK_OPT    = 'ds_hq_plugins_lock';
	const ORDER_SKEW  = 900;       // an order is good for 15 minutes after HQ signed it
	const MAX_TRIES   = 3;         // per slug + version
	const MAX_WAITS   = 8;         // wordpress.org or WordPress busy: not a try
	const STUCK_AFTER = 1800;
	const MAX_ORDERS  = 10;        // per reply
	const MAX_STATE   = 25;        // slugs remembered
	const PER_RUN     = 3;         // installs per cron run
	const MAX_ZIP     = 104857600; // 100 MB
	const MIN_WP      = '6.6';
	const DL          = 'https://downloads.wordpress.org/plugin/';
	const SUMS        = 'https://downloads.wordpress.org/plugin-checksums/';

	public static function boot() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	public static function unschedule() {
		if ( function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( self::HOOK ) ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}

	public static function state() {
		$s = get_option( self::OPT, array() );
		return is_array( $s ) ? $s : array();
	}

	private static function save( array $s ) {
		if ( count( $s ) > self::MAX_STATE ) {
			uasort( $s, function ( $a, $b ) { return (int) ( $b['queued'] ?? 0 ) <=> (int) ( $a['queued'] ?? 0 ); } );
			$s = array_slice( $s, 0, self::MAX_STATE, true );
		}
		update_option( self::OPT, $s, false );
	}

	public static function valid_slug( $slug ) {
		return is_string( $slug ) && (bool) preg_match( '/^[a-z0-9][a-z0-9-]{0,99}\z/', $slug );
	}

	public static function valid_version( $v ) {
		return is_string( $v ) && (bool) preg_match( '/^\d{1,9}(\.\d{1,9}){0,3}\z/', $v );
	}

	private static function plugins() {
		if ( ! function_exists( 'get_plugins' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return function_exists( 'get_plugins' ) ? (array) get_plugins() : array(); // a report must never fail over this
	}

	/** The main file of the plugin in folder $slug ('slug/file.php'), or ''. */
	public static function plugin_file( $slug, $list = null ) {
		foreach ( (array) ( null === $list ? self::plugins() : $list ) as $file => $data ) {
			if ( dirname( $file ) === $slug ) {
				return (string) $file;
			}
		}
		return '';
	}

	/** The version on disk now (read fresh; the plugins cache is stale right after an update). */
	public static function installed( $slug ) {
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		$list = self::plugins();
		$file = self::plugin_file( $slug, $list );
		return '' === $file ? '' : (string) ( $list[ $file ]['Version'] ?? '' );
	}

	/** What HQ gets in every report: every plugin in a folder, its version and whether it is active. */
	public static function inventory() {
		$list = self::plugins();
		if ( ! function_exists( 'is_plugin_active' ) ) {
			return array();
		}
		$out = array();
		foreach ( $list as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug || ! self::valid_slug( $slug ) || isset( $out[ $slug ] ) ) {
				continue;
			}
			$out[ $slug ] = array( 'v' => substr( (string) ( $data['Version'] ?? '' ), 0, 30 ), 'a' => is_plugin_active( $file ) ? 1 : 0 );
			if ( count( $out ) >= 300 ) {
				break;
			}
		}
		ksort( $out );
		return $out;
	}

	/** The state of every order, for HQ. */
	public static function report() {
		$o = array();
		foreach ( self::state() as $slug => $s ) {
			$r = array();
			foreach ( array( 'target', 'status', 'from', 'to', 'error', 'tries', 'waits', 'queued', 'finished', 'refused' ) as $k ) {
				if ( isset( $s[ $k ] ) ) {
					$r[ $k ] = $s[ $k ];
				}
			}
			$o[ $slug ] = $r;
		}
		return $o;
	}

	/** Why this site must not update plugins for HQ, or '' when it may. */
	public static function cannot() {
		if ( defined( 'DS_HQ_PLUGINS_DISABLED' ) && DS_HQ_PLUGINS_DISABLED ) {
			return 'plugin updates from HQ are switched off on this site (DS_HQ_PLUGINS_DISABLED)';
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() && ! ( is_main_site() && ( ! function_exists( 'is_main_network' ) || is_main_network() ) ) ) {
			return 'multisite: only the main site updates shared plugins';
		}
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return 'file changes are disabled on this site (DISALLOW_FILE_MODS)';
		}
		$wp = (string) ( $GLOBALS['wp_version'] ?? '' );
		if ( '' === $wp || version_compare( $wp, self::MIN_WP, '<' ) ) {
			return 'WordPress ' . ( $wp ?: '?' ) . ' has no crash check to roll a bad update back; update WordPress to ' . self::MIN_WP . '+ first';
		}
		return '';
	}

	/** [ true, slug, version ] for an order HQ signed for this install, still fresh, for an installed plugin; else [ false, slug, reason ]. */
	public static function order_ok( $order, $sid, $now = null ) {
		$now  = null === $now ? time() : (int) $now;
		$slug = is_array( $order ) ? (string) ( $order['slug'] ?? '' ) : '';
		$ver  = is_array( $order ) ? (string) ( $order['version'] ?? '' ) : '';
		$t    = is_array( $order ) ? (int) ( $order['t'] ?? 0 ) : 0;
		if ( ! self::valid_slug( $slug ) ) {
			return array( false, '', 'not a plugin slug' );
		}
		if ( ! self::valid_version( $ver ) ) {
			return array( false, $slug, 'not a plain version number' );
		}
		if ( abs( $now - $t ) > self::ORDER_SKEW ) {
			return array( false, $slug, 'order too old (or clock off)' );
		}
		$pub = defined( 'DS_HQ_LINK_PUBKEY' ) ? DS_HQ_LINK_PUBKEY : ( class_exists( 'DS_HQ_Link' ) ? DS_HQ_Link::HQ_PUBKEY : '' );
		if ( ! DS_HQ_Update::verify( $order['sig'] ?? '', "dshq-plugin\n$sid\n$slug\n$ver\n$t", $pub ) ) {
			return array( false, $slug, 'bad HQ signature' );
		}
		$have = self::installed( $slug );
		if ( '' === $have ) {
			return array( false, $slug, 'not installed on this site' );
		}
		if ( ! version_compare( $ver, $have, '>' ) ) {
			return array( false, $slug, 'not newer than the installed ' . $have );
		}
		return array( true, $slug, $ver );
	}

	/** HQ's reply carried plugin orders (DS_HQ_Link::post()). Check them and queue; cron installs a few minutes later. */
	public static function take_orders( $orders, $sid ) {
		if ( defined( 'DS_HQ_PLUGINS_DISABLED' ) && DS_HQ_PLUGINS_DISABLED ) {
			return;
		}
		if ( ! is_array( $orders ) ) {
			return;
		}
		$s      = self::state();
		$queued = false;
		foreach ( array_slice( array_values( $orders ), 0, self::MAX_ORDERS ) as $order ) {
			list( $ok, $slug, $ver ) = self::order_ok( $order, $sid );
			if ( '' === $slug ) {
				continue;
			}
			$why = $ok ? self::cannot() : $ver;
			if ( ! $ok || $why ) {
				$s[ $slug ]            = $s[ $slug ] ?? array();
				$s[ $slug ]['refused'] = array( 'reason' => $why, 'at' => time() );
				continue;
			}
			$cur  = $s[ $slug ] ?? array();
			$same = ( $cur['target'] ?? '' ) === $ver;
			$st   = $cur['status'] ?? '';
			if ( $same && ( 'queued' === $st || ( 'running' === $st && time() - (int) ( $cur['started'] ?? 0 ) < self::STUCK_AFTER ) ) ) {
				continue;
			}
			if ( $same && (int) ( $cur['tries'] ?? 0 ) >= self::MAX_TRIES ) {
				continue; // gave up on this version; HQ sees why in the reports
			}
			$s[ $slug ] = array(
				'target' => $ver,
				'status' => 'queued',
				'from'   => self::installed( $slug ),
				'queued' => time(),
				'tries'  => $same ? (int) ( $cur['tries'] ?? 0 ) : 0,
				'waits'  => $same ? (int) ( $cur['waits'] ?? 0 ) : 0,
				'error'  => $same ? (string) ( $cur['error'] ?? '' ) : '',
			);
			$queued = true;
		}
		self::save( $s );
		if ( $queued && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + ( function_exists( 'wp_rand' ) ? wp_rand( 60, 300 ) : 120 ), self::HOOK );
		}
	}

	/** The cron job: install what is queued, a few per run. */
	public static function run() {
		if ( ! DS_Lock::acquire( self::LOCK_OPT, self::STUCK_AFTER ) ) {
			return;
		}
		$done = 0;
		$more = false;
		try {
			foreach ( self::state() as $slug => $s ) {
				if ( 'queued' !== ( $s['status'] ?? '' ) || empty( $s['target'] ) ) {
					continue;
				}
				if ( $done >= self::PER_RUN ) {
					$more = true;
					break;
				}
				$done++;
				self::run_one( (string) $slug, (string) $s['target'] );
			}
		} finally {
			DS_Lock::release( self::LOCK_OPT );
		}
		if ( $more && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::HOOK );
		}
		if ( $done && class_exists( 'DS_HQ_Link' ) ) {
			// Tell HQ from a fresh request a minute from now.
			wp_clear_scheduled_hook( DS_HQ_Link::CHECKIN_HOOK );
			wp_schedule_single_event( time() + 60, DS_HQ_Link::CHECKIN_HOOK );
		}
	}

	private static function run_one( $slug, $target ) {
		$why = self::cannot();
		if ( $why ) {
			self::finish( $slug, $target, 'failed', $why, self::MAX_TRIES );
			return;
		}
		$have = self::installed( $slug );
		if ( '' === $have ) {
			self::finish( $slug, $target, 'failed', 'no longer installed on this site', self::MAX_TRIES );
			return;
		}
		if ( ! version_compare( $target, $have, '>' ) ) {
			self::finish( $slug, $target, $have === $target ? 'done' : 'skipped', '' );
			return;
		}
		$s                      = self::state();
		$s[ $slug ]['status']   = 'running';
		$s[ $slug ]['started']  = time();
		$s[ $slug ]['tries']    = (int) ( $s[ $slug ]['tries'] ?? 0 ) + 1;
		self::save( $s );
		$res = self::install( $slug, $target );
		if ( ! is_wp_error( $res ) ) {
			self::finish( $slug, $target, 'done', '' );
		} elseif ( 'skipped' === $res->get_error_code() ) {
			self::finish( $slug, $target, 'skipped', '' );
		} elseif ( in_array( $res->get_error_code(), array( 'busy', 'unreachable' ), true ) && (int) ( $s[ $slug ]['waits'] ?? 0 ) < self::MAX_WAITS ) {
			$w = self::state();
			if ( ( $w[ $slug ]['target'] ?? '' ) === $target ) {
				$w[ $slug ]['status'] = 'queued';
				$w[ $slug ]['tries']  = max( 0, (int) $w[ $slug ]['tries'] - 1 );
				$w[ $slug ]['waits']  = (int) ( $w[ $slug ]['waits'] ?? 0 ) + 1;
				$w[ $slug ]['error']  = substr( $res->get_error_code() . ': ' . $res->get_error_message(), 0, 300 );
				self::save( $w );
				wp_schedule_single_event( time() + ( function_exists( 'wp_rand' ) ? wp_rand( 900, 3600 ) : 1800 ), self::HOOK );
			}
		} else {
			$fatal = in_array( $res->get_error_code(), array( 'not-wporg', 'modified', 'release', 'checksums' ), true );
			self::finish( $slug, $target, 'failed', substr( $res->get_error_code() . ': ' . $res->get_error_message(), 0, 300 ), $fatal ? self::MAX_TRIES : null );
		}
	}

	private static function finish( $slug, $target, $status, $error, $tries = null ) {
		$s = self::state();
		if ( ( $s[ $slug ]['target'] ?? '' ) !== $target ) {
			return; // a newer order replaced this one meanwhile
		}
		$s[ $slug ]['status']   = $status;
		$s[ $slug ]['error']    = $error;
		$s[ $slug ]['to']       = self::installed( $slug );
		$s[ $slug ]['finished'] = time();
		if ( null !== $tries ) {
			$s[ $slug ]['tries'] = $tries;
		}
		unset( $s[ $slug ]['refused'] );
		self::save( $s );
	}

	/** wordpress.org's checksums for one plugin version: [ 'path/in/plugin.php' => [ sha256, ... ] ], or WP_Error. */
	public static function checksums( $slug, $version ) {
		$r    = wp_remote_get( self::SUMS . rawurlencode( $slug ) . '/' . rawurlencode( $version ) . '.json', array( 'timeout' => 30, 'redirection' => 2 ) );
		$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
		if ( 404 === $code ) {
			return new WP_Error( 'release', "wordpress.org has no checksums for $slug $version" );
		}
		if ( 200 !== $code ) {
			return new WP_Error( 'unreachable', 'wordpress.org did not answer for the checksums (' . ( is_wp_error( $r ) ? $r->get_error_message() : 'HTTP ' . $code ) . ')' );
		}
		return self::parse_checksums( (string) wp_remote_retrieve_body( $r ) );
	}

	public static function parse_checksums( $json ) {
		$d = json_decode( (string) $json, true );
		if ( ! is_array( $d ) || empty( $d['files'] ) || ! is_array( $d['files'] ) ) {
			return new WP_Error( 'checksums', 'the checksum list from wordpress.org is empty or unreadable' );
		}
		$out = array();
		foreach ( $d['files'] as $path => $h ) {
			$sha = is_array( $h ) ? ( $h['sha256'] ?? array() ) : array();
			$sha = array_values( array_filter( array_map( 'strtolower', (array) $sha ), function ( $x ) { return (bool) preg_match( '/^[0-9a-f]{64}$/', $x ); } ) );
			$p   = (string) $path;
			if ( ! $sha || '' === $p || false !== strpos( $p, '..' ) || '/' === $p[0] ) {
				return new WP_Error( 'checksums', 'the checksum list from wordpress.org has an unusable entry' );
			}
			$out[ $p ] = $sha;
		}
		return $out;
	}

	/** '' when the zip holds exactly the files wordpress.org lists for this version, each with a matching sha256; else why not. */
	public static function verify_zip( $zip, $slug, array $sums ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return 'this server has no ZipArchive to check the files';
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $zip ) ) {
			return 'the zip does not open';
		}
		$seen = array();
		$bad  = '';
		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			$name = (string) $z->getNameIndex( $i );
			if ( '/' === substr( $name, -1 ) ) {
				continue; // folders carry no content
			}
			if ( 0 !== strpos( $name, $slug . '/' ) || false !== strpos( $name, '..' ) ) {
				$bad = 'the zip has a file outside ' . $slug . '/: ' . substr( $name, 0, 80 );
				break;
			}
			$rel = substr( $name, strlen( $slug ) + 1 );
			if ( ! isset( $sums[ $rel ] ) ) {
				$bad = 'the zip has a file wordpress.org does not list: ' . substr( $rel, 0, 80 );
				break;
			}
			$data = $z->getFromIndex( $i );
			if ( false === $data || ! in_array( hash( 'sha256', $data ), $sums[ $rel ], true ) ) {
				$bad = 'a file does not match wordpress.org: ' . substr( $rel, 0, 80 );
				break;
			}
			$seen[ $rel ] = true;
		}
		$z->close();
		if ( '' === $bad && count( $seen ) !== count( $sums ) ) {
			$bad = 'the zip is missing ' . ( count( $sums ) - count( $seen ) ) . ' file(s) wordpress.org lists';
		}
		return $bad;
	}

	/** Download, verify and install. Returns true or WP_Error; the installed plugin is untouched on any error before the install step. */
	public static function install( $slug, $target ) {
		$file = self::plugin_file( $slug );
		$have = self::installed( $slug );
		if ( '' === $file || '' === $have ) {
			return new WP_Error( 'missing', 'not installed on this site' );
		}
		if ( $have === $target ) {
			return true;
		}
		// 1. The installed copy is the wordpress.org plugin (its main file matches wordpress.org for its version).
		$now = self::checksums( $slug, $have );
		if ( is_wp_error( $now ) ) {
			return 'release' === $now->get_error_code() ? new WP_Error( 'not-wporg', "the installed $slug $have is not a wordpress.org release; update it by hand" ) : $now;
		}
		$main = substr( $file, strlen( $slug ) + 1 );
		$path = WP_PLUGIN_DIR . '/' . $file;
		if ( ! isset( $now[ $main ] ) || ! is_readable( $path ) || ! in_array( hash_file( 'sha256', $path ), $now[ $main ], true ) ) {
			return new WP_Error( 'modified', "the installed $slug does not match wordpress.org's $have; update it by hand" );
		}
		// 2. The new version's file list.
		$sums = self::checksums( $slug, $target );
		if ( is_wp_error( $sums ) ) {
			return $sums;
		}
		if ( ! isset( $sums[ $main ] ) ) {
			return new WP_Error( 'release', "$slug $target has no $main; the plugin's main file changed, update it by hand" );
		}
		// 3. The zip, from wordpress.org only.
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = download_url( self::DL . rawurlencode( $slug ) . '.' . rawurlencode( $target ) . '.zip', 300 );
		if ( is_wp_error( $tmp ) ) {
			$d = $tmp->get_error_data();
			if ( 404 === (int) ( is_array( $d ) ? ( $d['code'] ?? 0 ) : 0 ) ) {
				return new WP_Error( 'release', "wordpress.org has no $slug $target zip" );
			}
			return new WP_Error( 'unreachable', 'the zip did not download (' . $tmp->get_error_message() . ')' );
		}
		try {
			$size = (int) @filesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $size < 100 || $size > self::MAX_ZIP ) {
				return new WP_Error( 'download', 'zip has an unexpected size (' . $size . ' bytes)' );
			}
			$bad = self::verify_zip( $tmp, $slug, $sums );
			if ( '' !== $bad ) {
				return new WP_Error( 'checksums', $bad . '; nothing installed' );
			}
			return self::wp_install( $slug, $file, $target, $tmp );
		} finally {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/** WordPress's automatic updater, offered exactly this verified local zip, for this one plugin. */
	private static function wp_install( $slug, $file, $target, $zip ) {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( ! method_exists( 'WP_Automatic_Updater', 'has_fatal_error' ) ) {
			return new WP_Error( 'old-wp', 'this WordPress has no crash check to roll a bad update back; nothing installed' );
		}
		DS_HQ_Update::clear_stale_lock();
		if ( ! WP_Upgrader::create_lock( 'auto_updater' ) ) {
			return new WP_Error( 'busy', 'WordPress is running its own automatic updates; trying again later' );
		}
		if ( ! version_compare( $target, self::installed( $slug ), '>' ) ) {
			WP_Upgrader::release_lock( 'auto_updater' );
			return new WP_Error( 'skipped', 'already on ' . self::installed( $slug ) );
		}
		$item  = (object) array(
			'id'          => 'w.org/plugins/' . $slug,
			'slug'        => $slug,
			'plugin'      => $file,
			'new_version' => $target,
			'url'         => 'https://wordpress.org/plugins/' . $slug . '/',
			'package'     => $zip, // a local, verified path: the upgrader installs it as is
			'autoupdate'  => true,
		);
		$offer = function ( $t ) use ( $item, $file ) {
			if ( ! is_object( $t ) ) {
				$t = (object) array( 'last_checked' => time(), 'checked' => array(), 'response' => array(), 'no_update' => array() );
			}
			$t->response          = isset( $t->response ) && is_array( $t->response ) ? $t->response : array();
			$t->response[ $file ] = $item;
			if ( isset( $t->no_update ) && is_array( $t->no_update ) ) {
				unset( $t->no_update[ $file ] );
			}
			return $t;
		};
		$allow = function ( $update, $it ) use ( $file ) {
			return ( isset( $it->plugin ) && $file === $it->plugin ) ? true : $update;
		};
		add_filter( 'site_transient_update_plugins', $offer, 999 );
		add_filter( 'auto_update_plugin', $allow, 999, 2 );
		add_filter( 'automatic_updater_disabled', '__return_false', 999 );
		add_filter( 'wp_doing_cron', '__return_true', 999 );
		$crash = '';
		$probe = function ( $response, $context = '', $class = '', $args = array(), $url = '' ) use ( &$crash ) {
			if ( false !== strpos( (string) $url, 'wp_scrape_key=' ) ) {
				$crash = DS_HQ_Update::crash_detail( $response );
			}
		};
		add_action( 'http_api_debug', $probe, 10, 5 );
		$was_active = is_plugin_active( $file );
		try {
			$result = ( new WP_Automatic_Updater() )->update( 'plugin', $item );
		} finally {
			remove_filter( 'site_transient_update_plugins', $offer, 999 );
			remove_filter( 'auto_update_plugin', $allow, 999 );
			remove_filter( 'automatic_updater_disabled', '__return_false', 999 );
			remove_filter( 'wp_doing_cron', '__return_true', 999 );
			remove_action( 'http_api_debug', $probe, 10 );
			WP_Upgrader::release_lock( 'auto_updater' );
		}
		wp_clean_plugins_cache( false );
		if ( $was_active && ! is_plugin_active( $file ) ) {
			activate_plugin( $file, '', false, true );
		}
		$now = self::installed( $slug );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'install', $result->get_error_message() . " (still on $now)" . ( '' !== $crash ? " Check: $crash" : '' ) );
		}
		if ( false === $result ) {
			return new WP_Error( 'install', "WordPress declined the update (files not writable, a version-control checkout, or a host setting); still on $now" );
		}
		if ( $now !== $target ) {
			return new WP_Error( 'install', "the update ran but the plugin says $now, not $target" );
		}
		return true;
	}
}
