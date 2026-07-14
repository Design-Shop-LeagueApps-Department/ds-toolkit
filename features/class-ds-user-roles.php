<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * User Roles (fleet-wide — every install, any blueprint generation).
 *
 * Partner-safe access control in two layers:
 *
 *  1. A "Partner" role — administrator-equivalent minus plugin management and
 *     the in-admin file editors. Devs often leave partner accounts as full
 *     Administrators because switching roles is friction; Partner makes the
 *     safe setup a one-click choice on the standard Users screen (that's also
 *     where admins "select the appropriate user role" — no custom UI needed).
 *  2. The Plugins menu is hidden from non-LeagueApps users. This is a label
 *     hide only — capabilities are untouched (same philosophy as Admin Menu
 *     Tidy) — so a legacy full-admin partner account stops browsing plugins
 *     without anything breaking.
 *
 * Both layers honour one escape hatch: DS Toolkit → Features → User Roles →
 * "Allow plugin access". When a LeagueApps dev switches it on, the Partner
 * role regains the plugin-management capabilities and the Plugins menu stops
 * being hidden. The DS Toolkit settings page itself is admin + LeagueApps
 * gated, so partners can never flip the switch themselves.
 *
 * Disabling the feature stops the menu hiding and the role sync, but the
 * Partner role itself stays registered so existing users keep working.
 */
class DS_User_Roles {

	/** Bump when the Partner capability set changes so existing installs re-sync. */
	const ROLE_VERSION = 1;
	const ROLE_SLUG    = 'partner';

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'init', array( $this, 'sync_role' ), 5 );
		// Very late, after every plugin has registered its menus.
		add_action( 'admin_menu', array( $this, 'hide_plugins_menu' ), 999999 );
	}

	private function plugin_access_allowed() {
		return ! empty( $this->settings['partner_plugin_access'] );
	}

	/** Capabilities the Partner role gives up (the hard boundary). */
	private static function plugin_caps() {
		return array( 'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins' );
	}

	/** In-admin file editors — never granted to Partner, even with plugin access open. */
	private static function editor_caps() {
		return array( 'edit_plugins', 'edit_themes', 'edit_files' );
	}

	/**
	 * Create/refresh the Partner role. Cheap on every load: only writes when the
	 * stored state (cap-set version + escape-hatch position) differs from the
	 * target, or the role is missing entirely.
	 */
	public function sync_role() {
		$want = self::ROLE_VERSION . ':' . ( $this->plugin_access_allowed() ? 'open' : 'locked' );
		if ( get_option( 'ds_partner_role_state' ) === $want && get_role( self::ROLE_SLUG ) ) {
			return;
		}
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		$caps = $admin->capabilities;
		$drop = $this->plugin_access_allowed()
			? self::editor_caps()
			: array_merge( self::plugin_caps(), self::editor_caps() );
		foreach ( $drop as $cap ) {
			unset( $caps[ $cap ] );
		}
		// remove + add is the only reliable way to REDUCE an existing role's caps.
		remove_role( self::ROLE_SLUG );
		add_role( self::ROLE_SLUG, __( 'Partner', 'ds-toolkit' ), $caps );
		update_option( 'ds_partner_role_state', $want );
	}

	/**
	 * Hide the Plugins menu from non-LeagueApps users (label hide; capabilities
	 * untouched). Partner-role users already lack activate_plugins, so WordPress
	 * hides the menu for them natively — this catches legacy full-admin partner
	 * accounts.
	 */
	public function hide_plugins_menu() {
		if ( $this->plugin_access_allowed() || DS_Toolkit::is_leagueapps_user() ) {
			return;
		}
		remove_menu_page( 'plugins.php' );
	}
}
