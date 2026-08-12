<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Origin Guard installer.
 *
 * Bot Shield ({@see DS_Bot_Shield}) is the best a normal plugin can do, but it
 * runs at plugins_loaded — AFTER WordPress has loaded every plugin (ACF, Beaver
 * Builder, Forminator, Defender…). During the 2026-08-10/11 fleet flood (WPE
 * ticket #8578620) that was still too expensive: 21k POSTs to /wp-login.php and
 * 7.4k hits to /wp-json/wp/v2/users/me each paid a full bootstrap before any
 * plugin could refuse them, saturating the shared PHP pool cluster-wide.
 *
 * The only in-WordPress layer that runs BEFORE plugins is an mu-plugin. This
 * class doesn't block anything itself — it writes and maintains a small
 * must-use plugin (wp-content/mu-plugins/ds-origin-guard.php) that refuses the
 * flood's exact request shapes in ~20ms instead of ~1s, a 50-100x capacity gain
 * on precisely the attacked endpoints. The toolkit owns that file: it installs
 * it, refreshes it when this payload version changes, and removes it when the
 * feature is switched off.
 *
 * Why a generator and not a static asset copy: the xmlrpc rule must be dropped
 * on sites that legitimately use XML-RPC (Jetpack, the WP mobile app), which we
 * can only detect here, after plugins have loaded. The decision is baked into
 * the written file so the mu-plugin stays dependency-free at run time.
 *
 * Lifecycle is driven from DS_Toolkit::run() in admin/cron/CLI context only, so
 * frontend requests never pay for the sync. A stored state stamp
 * (ds_origin_guard_state) makes the steady-state sync a single option read —
 * the filesystem is touched only when the desired state actually changes.
 */
class DS_Origin_Guard_Installer {

	/** Bump when the generated mu-plugin payload changes. Drives auto-refresh. */
	const PAYLOAD_VERSION = '1.0.0';

	const MU_FILENAME = 'ds-origin-guard.php';
	const STATE_OPT   = 'ds_origin_guard_state';
	const MARKER      = 'DS Origin Guard';

	/** Active plugins that need XML-RPC; presence drops the xmlrpc rule. */
	const XMLRPC_PLUGINS = array(
		'jetpack/jetpack.php',
	);

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	private function opt( $key, $default = '' ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	private function mu_dir() {
		return defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
	}

	private function mu_path() {
		return $this->mu_dir() . '/' . self::MU_FILENAME;
	}

	/**
	 * Should the xmlrpc rule be included? Only when the toggle is on and no
	 * active plugin depends on XML-RPC.
	 */
	private function block_xmlrpc() {
		if ( empty( $this->opt( 'origin_guard_block_xmlrpc', 1 ) ) ) {
			return false;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( self::XMLRPC_PLUGINS as $plugin ) {
			if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin ) ) {
				return false;
			}
		}
		return true;
	}

	/** A compact fingerprint of everything that affects the written file. */
	private function desired_state() {
		if ( empty( $this->opt( 'origin_guard_enabled', 1 ) ) ) {
			return 'off';
		}
		return implode( ':', array(
			self::PAYLOAD_VERSION,
			'login=' . ( empty( $this->opt( 'origin_guard_block_login', 1 ) ) ? '0' : '1' ),
			'xmlrpc=' . ( $this->block_xmlrpc() ? '1' : '0' ),
		) );
	}

	/**
	 * Reconcile the on-disk mu-plugin with the desired state. Cheap: returns
	 * after one option read unless the fingerprint changed.
	 */
	public function sync() {
		$desired = $this->desired_state();
		$current = get_option( self::STATE_OPT, '' );
		if ( $desired === $current && ( 'off' === $desired || file_exists( $this->mu_path() ) ) ) {
			return; // already in sync (and the file exists when it should)
		}

		if ( 'off' === $desired ) {
			$this->remove();
			update_option( self::STATE_OPT, 'off', false );
			return;
		}

		if ( $this->write() ) {
			update_option( self::STATE_OPT, $desired, false );
		}
	}

	private function write() {
		$dir = $this->mu_dir();
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
		}
		if ( ! is_writable( $dir ) ) {
			return false;
		}
		$payload = $this->render(
			! empty( $this->opt( 'origin_guard_block_login', 1 ) ),
			$this->block_xmlrpc()
		);
		return (bool) file_put_contents( $this->mu_path(), $payload );
	}

	/** Delete the file only if it carries our marker — never touch a stranger. */
	private function remove() {
		$path = $this->mu_path();
		if ( ! file_exists( $path ) ) {
			return;
		}
		$head = (string) file_get_contents( $path, false, null, 0, 512 );
		if ( false !== strpos( $head, self::MARKER ) ) {
			@unlink( $path );
		}
	}

	/**
	 * Build the mu-plugin source. Kept dependency-free and self-contained so it
	 * runs correctly before the rest of WordPress exists.
	 */
	private function render( $block_login, $block_xmlrpc ) {
		$v = self::PAYLOAD_VERSION;

		$login_rule = $block_login ? <<<'PHP'

// 2) Cold login POSTs (no test cookie = the login form was never rendered).
if ( 'POST' === $dsog_method
	&& false !== strpos( $dsog_uri, 'wp-login.php' )
	&& empty( $_COOKIE['wordpress_test_cookie'] ) ) {
	$dsog_deny( 'cold-login-post' );
}
PHP : '';

		$xmlrpc_rule = $block_xmlrpc ? <<<'PHP'

// 3) xmlrpc.php outright (no XML-RPC-dependent plugin active on this site).
if ( false !== strpos( $dsog_uri, 'xmlrpc.php' ) ) {
	$dsog_deny( 'xmlrpc' );
}
PHP : '';

		return <<<PHP
<?php
/**
 * Plugin Name: DS Origin Guard
 * Description: Refuses known bot-flood request patterns before WordPress loads plugins, so each attack request costs ~20ms instead of a full render. Managed by ds-toolkit — do not edit; changes are overwritten on update. Payload v{$v}.
 * Version: {$v}
 *
 * {$this->marker_line()}
 * Generated by ds-toolkit. To disable, turn off Origin Guard in DS Toolkit
 * settings (which deletes this file) rather than editing it here.
 */

if ( PHP_SAPI === 'cli' ) {
	return; // never interfere with WP-CLI
}

\$dsog_uri    = isset( \$_SERVER['REQUEST_URI'] ) ? \$_SERVER['REQUEST_URI'] : '';
\$dsog_method = isset( \$_SERVER['REQUEST_METHOD'] ) ? \$_SERVER['REQUEST_METHOD'] : 'GET';

\$dsog_logged_in = false;
foreach ( array_keys( \$_COOKIE ) as \$dsog_k ) {
	if ( 0 === strpos( \$dsog_k, 'wordpress_logged_in_' ) ) {
		\$dsog_logged_in = true;
		break;
	}
}

\$dsog_deny = function ( \$reason ) {
	header( 'X-DS-Origin-Guard: ' . \$reason );
	header( 'Cache-Control: no-store' );
	http_response_code( 403 );
	echo 'Forbidden.';
	exit;
};

// 1) Anonymous REST user enumeration (/wp-json/wp/v2/users, /users/me, /blog/...).
if ( ! \$dsog_logged_in
	&& empty( \$_SERVER['HTTP_AUTHORIZATION'] )
	&& preg_match( '#/wp-json/wp/v2/users(/|\\?|\$)#', \$dsog_uri ) ) {
	\$dsog_deny( 'users-enum' );
}
{$login_rule}{$xmlrpc_rule}

unset( \$dsog_uri, \$dsog_method, \$dsog_logged_in, \$dsog_k, \$dsog_deny );

PHP;
	}

	private function marker_line() {
		return self::MARKER . ' (LeagueApps Design Shop)';
	}
}
