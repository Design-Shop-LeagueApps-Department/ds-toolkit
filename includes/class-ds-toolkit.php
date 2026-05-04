<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DS_Toolkit {

    /**
     * Feature registry.
     * Key = settings option that enables the feature.
     * Value = file path (relative to DS_TOOLKIT_PATH) and class name to instantiate.
     *
     * To add a new feature: drop a class file in features/ and add one entry here.
     */
    private $features = array(
        'enable_login_branding' => array(
            'file'  => 'features/class-ds-login-branding.php',
            'class' => 'DS_Login_Branding',
        ),
        'hide_fl_assistant' => array(
            'file'  => 'features/class-ds-hide-fl-assistant.php',
            'class' => 'DS_Hide_FL_Assistant',
        ),
        'acf_css_vars_enabled' => array(
            'file'  => 'features/class-ds-acf-css-vars.php',
            'class' => 'DS_ACF_CSS_Vars',
        ),
        'getsubmenu_enabled' => array(
            'file'  => 'features/class-ds-getsubmenu.php',
            'class' => 'DS_Getsubmenu',
        ),
        'current_year_enabled' => array(
            'file'  => 'features/class-ds-current-year.php',
            'class' => 'DS_Current_Year',
        ),
        'forminator_email_partner_enabled' => array(
            'file'  => 'features/class-ds-forminator-email-partner.php',
            'class' => 'DS_Forminator_Email_Partner',
        ),
        'global_css_enabled' => array(
            'file'  => 'features/class-ds-global-css.php',
            'class' => 'DS_Global_CSS',
        ),
        'global_js_enabled' => array(
            'file'  => 'features/class-ds-global-js.php',
            'class' => 'DS_Global_JS',
        ),
        'child_pages_enabled' => array(
            'file'  => 'features/class-ds-child-pages.php',
            'class' => 'DS_Child_Pages',
        ),
        'uabb_post_loop_fix_enabled' => array(
            'file'  => 'features/class-ds-uabb-post-loop-fix.php',
            'class' => 'DS_UABB_Post_Loop_Fix',
        ),
    );

    public static function activate() {
        $settings = get_option( 'ds_toolkit_settings', array() );

        foreach ( self::get_defaults() as $key => $value ) {
            if ( ! isset( $settings[ $key ] ) ) {
                $settings[ $key ] = $value;
            }
        }

        update_option( 'ds_toolkit_settings', $settings );
    }

    private static function get_defaults() {
        // On fresh install only the core LeagueApps-branding features are on.
        // Everything else is opt-in so a new site doesn't get unexpected CSS/JS,
        // shortcodes, or MCP tools the moment the plugin is activated.
        return array(
            'enable_login_branding' => 1,
            'hide_fl_assistant'     => 1,
            'acf_css_vars_enabled'  => 0,
            'acf_css_vars_mappings' => array(
                array(
                    'acf_field' => 'header_scrolled_bar_color',
                    'css_var'   => '--header-scrolled-bar-color',
                    'fallback'  => 'var(--fl-global-accent)',
                ),
            ),
            'getsubmenu_enabled'                  => 0,
            'current_year_enabled'               => 0,
            'forminator_email_partner_enabled'   => 0,
            'forminator_email_partner_fallback'  => 'designshop' . DS_TOOLKIT_ADMIN_DOMAIN,
            'global_css_enabled'                 => 0,
            'global_css_overrides'               => '',
            'global_js_enabled'                  => 0,
            'child_pages_enabled'                => 0,
            'child_pages_template_id'            => '56369',
            'child_pages_columns'                => 3,
            'child_pages_columns_tablet'         => 2,
            'child_pages_columns_mobile'         => 1,
            'uabb_post_loop_fix_enabled'         => 0,
            // MCP tool group access controls — off by default
            'mcp_posts_pages_enabled'            => 0,
            'mcp_cpt_enabled'                    => 0,
            'mcp_taxonomies_enabled'             => 0,
            'mcp_acf_enabled'                    => 0,
            'mcp_toolkit_settings_enabled'       => 0,
            'mcp_bb_enabled'                     => 0,
            'mcp_acf_schema_enabled'             => 0,
            'mcp_menus_enabled'                  => 0,
            'mcp_maintenance_enabled'            => 0,
            'mcp_options_enabled'                => 0,
            'mcp_users_enabled'                  => 0,
        );
    }

    /**
     * Fills in any missing settings keys with their defaults.
     * Runs on every load so existing installs pick up new feature defaults automatically.
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

        if ( $changed ) {
            update_option( 'ds_toolkit_settings', $settings );
        }
    }

    public function run() {
        $this->maybe_set_defaults();

        // MCP server must be loaded on every request (registers REST routes)
        require_once DS_TOOLKIT_PATH . 'admin/class-ds-mcp-server.php';
        $mcp_server = new DS_MCP_Server();
        $mcp_server->init();

        if ( is_admin() ) {
            require_once DS_TOOLKIT_PATH . 'admin/class-ds-toolkit-admin.php';
            $admin = new DS_Toolkit_Admin();
            $admin->init();

            require_once DS_TOOLKIT_PATH . 'includes/class-ds-toolkit-updater.php';
            $updater = new DS_Toolkit_Updater();
            $updater->init();
        }

        $settings = get_option( 'ds_toolkit_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        foreach ( $this->features as $key => $feature ) {
            if ( ! empty( $settings[ $key ] ) ) {
                require_once DS_TOOLKIT_PATH . $feature['file'];
                $instance = new $feature['class']( $settings );
                $instance->init();
            }
        }
    }
}
