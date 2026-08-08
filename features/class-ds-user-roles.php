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
 *  2. The Plugins, Tools, and Settings menus are hidden from non-LeagueApps
 *     users. This is a label hide only — capabilities are untouched (same
 *     philosophy as Admin Menu Tidy) — so a legacy full-admin partner account
 *     stops browsing those screens without anything breaking. Note that on
 *     DSLP6 installs Admin Menu Tidy relocates Defender / Yoast / Media
 *     Library Organizer under Tools, so those relocated entries disappear
 *     for partners too.
 *
 * The plugin layers honour one escape hatch: DS Toolkit → Features → User
 * Roles → "Allow plugin access". When a LeagueApps dev switches it on, the
 * Partner role regains the plugin-management capabilities and the Plugins
 * menu stops being hidden (Tools/Settings stay hidden — they're not plugin
 * management). The DS Toolkit settings page itself is admin + LeagueApps
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

	/**
	 * Beaver Builder capabilities the Partner role needs to actually build.
	 *
	 * `builder_access` alone only opens the builder; without `unrestricted_editing`
	 * a partner gets the limited/locked editing mode. `global_styles` is left OUT
	 * on purpose — the palette and typography are a Design Shop concern, edited
	 * from Theme Setting, and BB's Global Styles menu is hidden from partners.
	 */
	private static function bb_caps() {
		return array( 'builder_access', 'unrestricted_editing' );
	}

	public function init() {
		add_action( 'init', array( $this, 'sync_role' ), 5 );
		// After Beaver Builder has registered its own settings.
		add_action( 'init', array( $this, 'sync_builder_access' ), 20 );
		// Very late, after every plugin has registered its menus.
		add_action( 'admin_menu', array( $this, 'hide_admin_menus' ), 999999 );
	}

	/**
	 * Grant the Partner role access to Beaver Builder.
	 *
	 * BB stores editing permission PER ROLE in its own `_fl_builder_user_access`
	 * option, and its defaults are `administrator` + `editor` only. Partner is a
	 * role we invent afterwards, so it is never in that list — which meant a
	 * partner account had every WordPress capability it needed and still saw no
	 * "Edit with Beaver Builder" anywhere. Granting caps on the role is not
	 * enough; BB has to be told separately.
	 *
	 * Writes only when a value actually changes, so this is safe on every load,
	 * and it never touches any other role's entry.
	 */
	public function sync_builder_access() {
		if ( ! class_exists( 'FLBuilderUserAccess' ) ) {
			return;
		}
		if ( ! get_role( self::ROLE_SLUG ) ) {
			return;
		}

		// get_saved_settings() returns defaults merged with anything stored, so
		// reading it first preserves choices a dev made on BB's own settings screen.
		$settings = FLBuilderUserAccess::get_saved_settings();
		if ( ! is_array( $settings ) ) {
			return;
		}

		$changed = false;
		foreach ( self::bb_caps() as $cap ) {
			if ( ! isset( $settings[ $cap ] ) || ! is_array( $settings[ $cap ] ) ) {
				continue;
			}
			if ( empty( $settings[ $cap ][ self::ROLE_SLUG ] ) ) {
				$settings[ $cap ][ self::ROLE_SLUG ] = true;
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( '_fl_builder_user_access', $settings );
		}
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
	 * Hide admin menus from non-LeagueApps users (label hide; capabilities
	 * untouched). Plugins honours the "Allow plugin access" escape hatch;
	 * Tools and Settings are always hidden for partners — those screens hold
	 * host/SEO/security surfaces partners shouldn't wander into. Partner-role
	 * users already lack activate_plugins, so WordPress hides Plugins for them
	 * natively — the explicit remove catches legacy full-admin partner accounts.
	 */
	public function hide_admin_menus() {
		if ( DS_Toolkit::is_leagueapps_user() ) {
			return;
		}
		if ( ! $this->plugin_access_allowed() ) {
			remove_menu_page( 'plugins.php' );
		}
		remove_menu_page( 'tools.php' );
		remove_menu_page( 'options-general.php' );
	}
}
