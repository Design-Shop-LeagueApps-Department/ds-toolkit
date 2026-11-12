<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/class-ds-env.php';

class DS_Toolkit {

    /**
     * The features the toolkit can load: key (the setting that switches it on) => file, class and, for a
     * blueprint-gated one, min_blueprint. Declared once, with every default, label and settings card, in
     * includes/class-ds-features.php. To add a feature: drop its class file in features/ and add ONE entry there.
     */
    public static function feature_registry() {
        return DS_Features::registry();
    }

    /**
     * True only for users whose email matches DS_TOOLKIT_ADMIN_DOMAIN.
     * Shared gate for LeagueApps-only UI (the admin settings page and the
     * internal Theme Setting options page).
     */
    public static function is_leagueapps_user() {
        return self::is_leagueapps_staff( wp_get_current_user() );
    }

    /**
     * Design Shop staff: an ADMINISTRATOR whose email ends in DS_TOOLKIT_ADMIN_DOMAIN. The email alone was the
     * test before 1.12.0, but anyone who can manage users (the Partner role can) can type any address on an
     * account, so the address only counts on an administrator account (or a network super admin).
     */
    public static function is_leagueapps_staff( $user ) {
        if ( ! ( $user instanceof WP_User ) || ! $user->exists() || empty( $user->user_email ) ) {
            return false;
        }
        if ( ! DS_Env::is_staff_email( $user->user_email ) ) {
            return false;
        }
        return self::is_administrator( $user );
    }

    /** An administrator account (single site), or a super admin on multisite. Never "can delete users". */
    public static function is_administrator( $user ) {
        if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
            return false;
        }
        return in_array( 'administrator', (array) $user->roles, true ) || ( is_multisite() && is_super_admin( $user->ID ) );
    }

    /**
     * Administrator accounts are managed by administrators only (1.12.0). The Partner role keeps
     * create/edit/promote/delete users so partners run their own staff accounts, but it could also create an
     * Administrator (who can install plugins), take over an existing one (change its email or password), or
     * delete one. Applies to any non-administrator holding those capabilities; on a standard site only
     * administrators have them. WP-CLI and scripts that call wp_insert_user() directly are not affected.
     */
    public static function editable_roles( $roles ) {
        if ( is_array( $roles ) && ! self::is_administrator( wp_get_current_user() ) ) {
            unset( $roles['administrator'] );
        }
        return $roles;
    }

    public static function protect_administrators( $caps, $cap, $user_id, $args ) {
        if ( empty( $args[0] ) || ! in_array( $cap, array( 'edit_user', 'delete_user', 'remove_user', 'promote_user' ), true ) ) {
            return $caps;
        }
        $target = get_userdata( (int) $args[0] );
        if ( ! $target || ! in_array( 'administrator', (array) $target->roles, true ) ) {
            return $caps;
        }
        return self::is_administrator( get_userdata( (int) $user_id ) ) ? $caps : array( 'do_not_allow' );
    }

    /**
     * "New User Default Role" can only be set to Administrator by an administrator, or by a script running with no
     * user (WP-CLI without --user): with the Partner role's manage_options it was one form post from making every
     * new sign-up an administrator.
     */
    public static function guard_default_role( $value, $old ) {
        if ( 'administrator' === $value && 'administrator' !== $old && get_current_user_id()
            && ! self::is_administrator( wp_get_current_user() ) ) {
            return $old;
        }
        return $value;
    }

    /**
     * The blueprint generation this site was built from.
     *
     * Carried as a DB option so it travels when an install is cloned from the
     * blueprint (fleet sites are provisioned by copying the blueprint's
     * database). A wp-config constant override wins if defined. The toolkit
     * only ever READS this — it must never write it on activation, or every
     * site that activates the plugin would stamp itself as the latest
     * generation. Returns 0 for legacy / unstamped installs (DSLP4, DSLP5,
     * ad-hoc sites).
     */
    public static function blueprint_version() {
        if ( defined( 'DS_BLUEPRINT_VERSION' ) ) {
            return (int) DS_BLUEPRINT_VERSION;
        }
        return (int) get_option( 'ds_blueprint_version', 0 );
    }

    /**
     * The in-house "LeagueApps" Beaver Builder module features (settings key => label).
     * These are purely additive builder blocks, so they are opt-in on ANY blueprint:
     * their toggles are shown on the Settings page regardless of blueprint, they load
     * whenever their toggle is on (the blueprint gate is bypassed for modules in run()),
     * and they still default ON for blueprint 6+ builds via get_defaults(). Existing
     * blueprint-5 sites see them OFF until an admin turns one on.
     */
    public static function module_features() {
        return DS_Features::modules();
    }

    /**
     * Beaver Builder caches every page's module CSS and JS in uploads/bb-plugin/cache and rebuilds
     * it only when ITS cache is cleared (its own updates do that), never when another plugin
     * updates. A toolkit release that changes a module's markup together with its script or
     * styles would pair the new markup with the old cached assets until someone clicked Clear
     * cache: designshopdemo, 1.10.38-rc.1, the calendar's old script threw on the new markup and
     * the calendar stayed empty. So clear it once per toolkit version, exactly as BB does after
     * its own update (delete the asset files, then fl_builder_cache_cleared, which also purges
     * the host's page cache through BB's cache helper). One autoloaded option read per request.
     */
    /**
     * Backstop for every layout's CSS: a "</style" inside it can never end the <style> element it is printed in
     * when Beaver Builder renders assets inline (the Theme Setting and Home Templates previews do). Module CSS
     * is built from settings; a setting value that reached it unchecked could otherwise turn into script.
     * Real CSS never contains "</style", so nothing renders differently.
     */
    public static function css_cannot_close_style( $css ) {
        return ( is_string( $css ) && false !== stripos( $css, '</style' ) ) ? preg_replace( '#</(style)#i', '<\\/$1', $css ) : $css;
    }

    public static function maybe_clear_builder_cache() {
        if ( DS_TOOLKIT_VERSION === get_option( 'ds_toolkit_assets_version' ) ) {
            return;
        }
        // Only clear once the new version is saved: if the option cannot be written (read-only DB),
        // clearing anyway would wipe the builder cache on every request.
        if ( ! update_option( 'ds_toolkit_assets_version', DS_TOOLKIT_VERSION, true ) ) {
            return;
        }
        // A release that changed nothing a cached layout is built from (a Tripwire list, a settings card, the
        // updater) used to clear every page's assets and purge the host's page cache all the same: 80 releases
        // in 46 days, each a fleet-wide cold cache. The fingerprint is taken once per version change, never
        // on an ordinary request. No fingerprint (unreadable folder) = clear, as before.
        $print = self::assets_fingerprint();
        if ( '' !== $print && get_option( 'ds_toolkit_assets_print' ) === $print ) {
            return;
        }
        if ( class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'delete_asset_cache_for_all_posts' ) ) {
            FLBuilderModel::delete_asset_cache_for_all_posts();
            do_action( 'fl_builder_cache_cleared' );
            // Saved only once a clear has run: with Beaver Builder switched off nothing was cleared, so the
            // next version change still clears whatever cache it finds.
            update_option( 'ds_toolkit_assets_print', $print, false );
        }
    }

    /**
     * One hash over every PHP, CSS and JS file in modules/ and includes/: the module templates, styles and
     * scripts, and the shared classes their frontend.css.php / frontend.js.php files call (DS_Module_UI,
     * DS_LA_Colors, the data classes). Deliberately wide: a release that touches any of them clears the cache.
     */
    public static function assets_fingerprint() {
        $rows = array();
        foreach ( array( 'modules', 'includes' ) as $dir ) {
            $root = DS_TOOLKIT_PATH . $dir;
            if ( ! is_dir( $root ) ) {
                return '';
            }
            try {
                $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
                foreach ( $it as $file ) {
                    if ( $file->isFile() && in_array( strtolower( $file->getExtension() ), array( 'php', 'css', 'js' ), true ) ) {
                        $hash = md5_file( $file->getPathname() );
                        if ( false === $hash ) {
                            return '';
                        }
                        $rows[] = str_replace( '\\', '/', substr( $file->getPathname(), strlen( DS_TOOLKIT_PATH ) ) ) . ':' . $hash;
                    }
                }
            } catch ( Exception $e ) {
                return '';
            }
        }
        sort( $rows, SORT_STRING );
        return $rows ? md5( implode( "\n", $rows ) ) : '';
    }

    public static function activate() {
        $settings = get_option( 'ds_toolkit_settings', array() );

        foreach ( self::get_defaults() as $key => $value ) {
            if ( ! isset( $settings[ $key ] ) ) {
                $settings[ $key ] = $value;
            }
        }

        update_option( 'ds_toolkit_settings', $settings );
    }

    /**
     * The default of every setting on this site's blueprint generation (see DS_Features::defaults()). On a fresh
     * install only the core LeagueApps-branding and protection features are on; everything else is opt-in, so a
     * new site does not get unexpected CSS, JS, shortcodes or MCP tools the moment the plugin is activated.
     */
    private static function get_defaults() {
        return DS_Features::defaults( self::blueprint_version() );
    }

    /**
     * Fills in any missing settings keys with their defaults.
     * Runs on every load so existing installs pick up new feature defaults automatically.
     * Returns the (possibly updated) settings array so run() can reuse it without a second get_option().
     */
    private function maybe_set_defaults() {
        $settings = get_option( 'ds_toolkit_settings', array() );

        // Recover from a corrupted option value (e.g. a save that wrote an
        // empty string instead of an array). Without this guard, the foreach
        // below throws "Cannot access offset of type string on string" and
        // takes the whole site down.
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $changed = false;
        foreach ( self::get_defaults() as $key => $value ) {
            if ( ! isset( $settings[ $key ] ) ) {
                $settings[ $key ] = $value;
                $changed          = true;
            }
        }

        // One-shot migration: 1.2.2 / 1.2.3 shipped uabb_post_loop_fix_enabled
        // defaulting to 0, and any site that hit the v1.2.1 fatal got the option
        // rebuilt with that value by the is_array() recovery guard. The patch is
        // a UABB compat fix — its hooks only fire when UABB Advanced Posts is
        // rendering, so leaving it on is safe regardless. Flip it back to 1 once
        // for affected installs, then mark the migration done so the user can
        // still toggle it off later without us re-enabling on the next load.
        if ( empty( $settings['_dst_migrated_125_uabb_on'] ) ) {
            if ( isset( $settings['uabb_post_loop_fix_enabled'] ) && empty( $settings['uabb_post_loop_fix_enabled'] ) ) {
                $settings['uabb_post_loop_fix_enabled'] = 1;
            }
            $settings['_dst_migrated_125_uabb_on'] = 1;
            $changed                               = true;
        }

        if ( $changed ) {
            update_option( 'ds_toolkit_settings', $settings );
        }

        return $settings;
    }

    public function run() {
        $settings = $this->maybe_set_defaults();

        // Bot Shield goes first: a request it refuses ends inside init(), so nothing below (the admin and
        // updater classes, 50 other feature files) is read or hooked for a request that gets a 403 or 429.
        $features = self::feature_registry();
        $shield   = 'bot_shield_enabled';
        if ( ! empty( $settings[ $shield ] ) && isset( $features[ $shield ] ) ) {
            require_once DS_TOOLKIT_PATH . $features[ $shield ]['file'];
            ( new $features[ $shield ]['class']( $settings ) )->init();
        }

        add_action( 'init', array( __CLASS__, 'maybe_clear_builder_cache' ), 99 );
        add_filter( 'fl_builder_render_css', array( __CLASS__, 'css_cannot_close_style' ), 99999 );
        add_filter( 'editable_roles', array( __CLASS__, 'editable_roles' ) );
        add_filter( 'map_meta_cap', array( __CLASS__, 'protect_administrators' ), 10, 4 );
        add_filter( 'pre_update_option_default_role', array( __CLASS__, 'guard_default_role' ), 10, 2 );

        // The MCP server only needs to be alive for REST requests. Defer loading the
        // 2.8k-line class file until rest_api_init fires so non-REST page loads
        // (frontend, wp-admin) skip parsing it entirely.
        add_action( 'rest_api_init', static function() {
            require_once DS_TOOLKIT_PATH . 'admin/class-ds-mcp-server.php';
            ( new DS_MCP_Server() )->register_routes();
        } );

        if ( is_admin() ) {
            require_once DS_TOOLKIT_PATH . 'admin/class-ds-toolkit-admin.php';
            $admin = new DS_Toolkit_Admin();
            $admin->init();
        }

        // The updater must also load outside wp-admin: WP Engine Smart Plugin
        // Manager, wp-cron auto-updates, and `wp plugin update` all run in
        // CLI/cron context where is_admin() is false. Without it the
        // upgrader_source_selection filter never registers, so a GitHub
        // zipball fallback installs into a long owner-repo-hash folder and
        // WordPress deactivates the plugin (active_plugins path no longer
        // resolves). Loading it here keeps fix_source_dir on the hook in
        // every update path.
        if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            require_once DS_TOOLKIT_PATH . 'includes/class-ds-toolkit-updater.php';
            $updater = new DS_Toolkit_Updater();
            $updater->init();

            // Origin Guard is an mu-plugin the toolkit writes and maintains, not
            // a request-time feature — it must run before plugins load, which
            // the features[] loop below (plugins_loaded) is too late for. Sync
            // the file here in admin/cron/CLI only, so frontend hits never pay
            // for it. sync() is a single option read unless state changed, and
            // it handles install, version refresh, and removal-on-disable.
            require_once DS_TOOLKIT_PATH . 'includes/class-ds-origin-guard-installer.php';
            ( new DS_Origin_Guard_Installer( $settings ) )->sync();

            // The Table module's 5-minute sync job stops with the module (its handler only loads while it is on).
            if ( empty( $settings['ds_table_module_enabled'] ) && wp_next_scheduled( 'ds_table_sync' ) ) { wp_clear_scheduled_hook( 'ds_table_sync' ); }
            // Reporting to Design Shop HQ switched off: leave no HQ cron events behind. (Tripwire being off no
            // longer stops it: HQ Link is its own feature.)
            if ( isset( $settings['tripwire_hq_report'] ) && ! $settings['tripwire_hq_report']
                && ( wp_next_scheduled( 'ds_hq_link_checkin' ) || wp_next_scheduled( 'ds_hq_link_flush' ) || wp_next_scheduled( 'ds_hq_link_enroll' ) || wp_next_scheduled( 'ds_hq_update_run' ) ) ) {
                require_once DS_TOOLKIT_PATH . 'features/class-ds-hq-link.php';
                DS_HQ_Link::unschedule();
            }
        }

        $blueprint    = self::blueprint_version();
        $module_keys  = self::module_features();

        foreach ( $features as $key => $feature ) {
            // Blueprint-gated features never load below their required generation,
            // EXCEPT the LeagueApps modules: those are additive builder blocks that
            // are opt-in on any blueprint (off by default below their generation),
            // so an existing site can enable individual blocks without a blueprint bump.
            if ( $shield === $key ) {
                continue; // started at the top of run()
            }
            $is_module = isset( $module_keys[ $key ] );
            if ( ! empty( $feature['min_blueprint'] ) && ! $is_module && $blueprint < (int) $feature['min_blueprint'] ) {
                continue;
            }
            if ( ! empty( $feature['admin_only'] ) && ! is_admin() ) {
                continue; // a wp-admin-only feature: nothing for it to do in a front-end, REST or cron request
            }
            if ( ! empty( $settings[ $key ] ) ) {
                require_once DS_TOOLKIT_PATH . $feature['file'];
                // A block with nothing to do but register itself goes through the shared loader, which
                // takes the block's folder name; every other feature takes the settings.
                $instance = isset( $feature['block'] ) ? new $feature['class']( $feature['block'] ) : new $feature['class']( $settings );
                $instance->init();
            }
        }
    }
}
