<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DS_Toolkit_Admin {

    private $logo_finder;

    public function init() {
        add_action( 'admin_menu',            array( $this, 'add_menu' ) );
        add_action( 'admin_init',            array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        require_once DS_TOOLKIT_PATH . 'admin/class-ds-logo-finder.php';
        $this->logo_finder = new DS_Logo_Finder();
        $this->logo_finder->init();
    }

    private function get_mcp_url() {
        return rest_url( 'ds-toolkit/v1/mcp' );
    }

    /**
     * Returns true only for users whose email matches DS_TOOLKIT_ADMIN_DOMAIN.
     * The DS Toolkit menu and settings page are restricted to these users.
     */
    private function is_leagueapps_user() {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() ) {
            return false;
        }
        $domain = preg_quote( DS_TOOLKIT_ADMIN_DOMAIN, '/' );
        return (bool) preg_match( '/' . $domain . '$/i', $user->user_email );
    }

    public function add_menu() {
        if ( ! $this->is_leagueapps_user() ) {
            return;
        }
        add_options_page( 'DS Toolkit', 'DS Toolkit', 'manage_options', 'ds-toolkit', array( $this, 'render_page' ) );
    }

    public function register_settings() {
        register_setting( 'ds_toolkit_options', 'ds_toolkit_settings', array(
            'sanitize_callback' => array( $this, 'merge_with_existing_settings' ),
        ) );
    }

    /**
     * WP's Settings API replaces the entire option on save. Each tab's form
     * only POSTs ITS fields, so without merging, saving one tab wipes every
     * other tab's keys (Features wipes MCP toggles, MCP wipes Features, etc.).
     * Always merge incoming form values on top of the existing option so
     * unrelated keys survive.
     *
     * Also defends against the fatal-error case: when every checkbox in a
     * form is unchecked, $_POST['ds_toolkit_settings'] can come through as
     * a non-array (null/empty string), which would otherwise be saved as ''
     * and crash maybe_set_defaults() on the next page load.
     */
    public function merge_with_existing_settings( $new ) {
        $existing = get_option( 'ds_toolkit_settings', array() );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }
        if ( ! is_array( $new ) ) {
            return $existing;
        }
        return array_merge( $existing, $new );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'settings_page_ds-toolkit' ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'features';

        // Shared admin styles (header, tabs, cards, toggles)
        wp_enqueue_style(
            'ds-toolkit-admin',
            DS_TOOLKIT_URL . 'assets/css/admin.css',
            array(),
            DS_TOOLKIT_VERSION
        );

        if ( $active_tab === 'logos' ) {
            $this->logo_finder->enqueue_assets();

        } elseif ( $active_tab === 'global-css' ) {
            $settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
            if ( $settings ) {
                wp_add_inline_script(
                    'code-editor',
                    sprintf(
                        'jQuery( function() { wp.codeEditor.initialize( "global_css_overrides", %s ); } );',
                        wp_json_encode( $settings )
                    )
                );
            }

        } elseif ( $active_tab === 'global-js' ) {
            // JS is plugin-managed and read-only — no editor needed.

        } else {
            // Features tab
            wp_enqueue_media();
            wp_enqueue_script(
                'ds-toolkit-admin',
                DS_TOOLKIT_URL . 'assets/js/admin.js',
                array( 'jquery', 'media-upload' ),
                DS_TOOLKIT_VERSION,
                true
            );
            wp_localize_script( 'ds-toolkit-admin', 'dstAdmin', array(
                'defaultLogoUrl' => DS_TOOLKIT_URL . 'assets/images/cropped-LA-circle-logo-1.png',
            ) );
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) || ! $this->is_leagueapps_user() ) {
            return;
        }

        $active_tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'features';
        $base_url      = admin_url( 'options-general.php?page=ds-toolkit' );
        ?>
        <div class="wrap dst-wrap">

            <div class="dst-header">
                <div class="dst-header-icon"><span class="dashicons dashicons-hammer"></span></div>
                <div class="dst-header-text">
                    <h1>DS Toolkit</h1>
                    <p>Design Shop custom features and build toolkit</p>
                </div>
                <span class="dst-badge">v<?php echo esc_html( DS_TOOLKIT_VERSION ); ?></span>
            </div>

            <div class="dst-tabs">
                <a href="<?php echo esc_url( $base_url ); ?>" class="dst-tab<?php echo $active_tab === 'features' ? ' is-active' : ''; ?>">
                    Features
                </a>
                <?php if ( DS_Toolkit::blueprint_version() >= 6 ) : ?>
                <a href="<?php echo esc_url( $base_url . '&tab=general' ); ?>" class="dst-tab<?php echo $active_tab === 'general' ? ' is-active' : ''; ?>">
                    General
                </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( $base_url . '&tab=logos' ); ?>" class="dst-tab<?php echo $active_tab === 'logos' ? ' is-active' : ''; ?>">
                    University Logo Finder
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=global-css' ); ?>" class="dst-tab<?php echo $active_tab === 'global-css' ? ' is-active' : ''; ?>">
                    Global CSS
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=global-js' ); ?>" class="dst-tab<?php echo $active_tab === 'global-js' ? ' is-active' : ''; ?>">
                    Global JS
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=mcp' ); ?>" class="dst-tab<?php echo $active_tab === 'mcp' ? ' is-active' : ''; ?>">
                    MCP
                </a>
            </div>

            <?php
            $opts = get_option( 'ds_toolkit_settings', array() );

            if ( $active_tab === 'general' && DS_Toolkit::blueprint_version() >= 6 ) {
                $footer_copyright_text     = isset( $opts['footer_copyright_text'] ) ? $opts['footer_copyright_text'] : '';
                $copyright_shortcode_enabled = ! empty( $opts['copyright_shortcode_enabled'] );
                require DS_TOOLKIT_PATH . 'admin/views/page-general.php';

            } elseif ( $active_tab === 'logos' ) {
                require DS_TOOLKIT_PATH . 'admin/views/page-logo-finder.php';

            } elseif ( $active_tab === 'global-css' ) {
                $global_css_enabled   = ! empty( $opts['global_css_enabled'] );
                $global_css_overrides = isset( $opts['global_css_overrides'] ) ? $opts['global_css_overrides'] : '';
                require DS_TOOLKIT_PATH . 'admin/views/page-global-css.php';

            } elseif ( $active_tab === 'global-js' ) {
                $global_js_enabled = ! empty( $opts['global_js_enabled'] );
                require DS_TOOLKIT_PATH . 'admin/views/page-global-js.php';

            } elseif ( $active_tab === 'mcp' ) {
                $is_local         = ( 'local' === wp_get_environment_type() );
                $mcp_url          = $is_local
                    ? preg_replace( '/^https:/i', 'http:', $this->get_mcp_url() )
                    : $this->get_mcp_url();
                $wp_version_ok    = version_compare( get_bloginfo( 'version' ), '5.6', '>=' );
                $app_passwords_ok = $wp_version_ok && wp_is_application_passwords_available();
                // Tool group toggles — default to enabled (true) if key not yet set
                $mcp_posts_pages_enabled      = ! isset( $opts['mcp_posts_pages_enabled'] )      || ! empty( $opts['mcp_posts_pages_enabled'] );
                $mcp_cpt_enabled              = ! isset( $opts['mcp_cpt_enabled'] )              || ! empty( $opts['mcp_cpt_enabled'] );
                $mcp_taxonomies_enabled       = ! isset( $opts['mcp_taxonomies_enabled'] )       || ! empty( $opts['mcp_taxonomies_enabled'] );
                $mcp_acf_enabled              = ! isset( $opts['mcp_acf_enabled'] )              || ! empty( $opts['mcp_acf_enabled'] );
                $mcp_toolkit_settings_enabled = ! isset( $opts['mcp_toolkit_settings_enabled'] ) || ! empty( $opts['mcp_toolkit_settings_enabled'] );
                $mcp_bb_enabled               = ! isset( $opts['mcp_bb_enabled'] )               || ! empty( $opts['mcp_bb_enabled'] );
                $mcp_acf_schema_enabled       = ! isset( $opts['mcp_acf_schema_enabled'] )       || ! empty( $opts['mcp_acf_schema_enabled'] );
                $mcp_menus_enabled            = ! isset( $opts['mcp_menus_enabled'] )            || ! empty( $opts['mcp_menus_enabled'] );
                $mcp_maintenance_enabled      = ! isset( $opts['mcp_maintenance_enabled'] )      || ! empty( $opts['mcp_maintenance_enabled'] );
                $mcp_options_enabled          = ! isset( $opts['mcp_options_enabled'] )          || ! empty( $opts['mcp_options_enabled'] );
                $mcp_users_enabled            = ! isset( $opts['mcp_users_enabled'] )            || ! empty( $opts['mcp_users_enabled'] );
                require DS_TOOLKIT_PATH . 'admin/views/page-mcp.php';

            } else {
                $enabled                            = ! empty( $opts['enable_login_branding'] );
                $logo_id                            = ! empty( $opts['login_logo_id'] ) ? absint( $opts['login_logo_id'] ) : 0;
                $logo_url                           = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
                $default_url                        = DS_TOOLKIT_URL . 'assets/images/cropped-LA-circle-logo-1.png';
                $hide_fl_assistant                  = ! empty( $opts['hide_fl_assistant'] );
                $acf_css_vars_enabled               = ! empty( $opts['acf_css_vars_enabled'] );
                $acf_css_vars_mappings              = ! empty( $opts['acf_css_vars_mappings'] ) ? $opts['acf_css_vars_mappings'] : array();
                $getsubmenu_enabled                 = ! empty( $opts['getsubmenu_enabled'] );
                $current_year_enabled               = ! empty( $opts['current_year_enabled'] );
                $overlay_nav_enabled                = ! empty( $opts['overlay_nav_enabled'] );
                $forminator_email_partner_enabled   = ! empty( $opts['forminator_email_partner_enabled'] );
                $forminator_email_partner_fallback  = ! empty( $opts['forminator_email_partner_fallback'] )
                    ? $opts['forminator_email_partner_fallback']
                    : 'designshop@leagueapps.com';
                $child_pages_enabled         = ! empty( $opts['child_pages_enabled'] );
                $child_pages_template_id     = ! empty( $opts['child_pages_template_id'] ) ? $opts['child_pages_template_id'] : '56369';
                $child_pages_columns         = ! empty( $opts['child_pages_columns'] )        ? (int) $opts['child_pages_columns']        : 3;
                $child_pages_columns_tablet  = ! empty( $opts['child_pages_columns_tablet'] ) ? (int) $opts['child_pages_columns_tablet'] : 2;
                $child_pages_columns_mobile  = ! empty( $opts['child_pages_columns_mobile'] ) ? (int) $opts['child_pages_columns_mobile'] : 1;
                $uabb_post_loop_fix_enabled  = ! empty( $opts['uabb_post_loop_fix_enabled'] );
                // Blueprint-gated feature toggles — only surfaced on qualifying installs.
                $blueprint_version        = DS_Toolkit::blueprint_version();
                $disable_comments_enabled = ! empty( $opts['disable_comments_enabled'] );
                $theme_setting_enabled    = ! empty( $opts['theme_setting_enabled'] );
                $admin_menu_tidy_enabled  = ! empty( $opts['admin_menu_tidy_enabled'] );
                $user_roles_enabled       = ! empty( $opts['user_roles_enabled'] );
                $partner_plugin_access    = ! empty( $opts['partner_plugin_access'] );
                $design_academy_enabled   = ! empty( $opts['design_academy_enabled'] );
                $academy_pinned_url       = ! empty( $opts['academy_pinned_url'] ) ? $opts['academy_pinned_url'] : 'https://designacademy.leagueapps.com/course/how-to-edit-your-website-a-beginners-guide-to-wordpress-beaverbuilder/';
                $academy_pinned_label     = ! empty( $opts['academy_pinned_label'] ) ? $opts['academy_pinned_label'] : "How to Edit Your Website: A Beginner's Guide to WordPress & Beaver Builder";
                $ds_menu_module_enabled   = ! empty( $opts['ds_menu_module_enabled'] );
                $image_optimization_enabled = ! empty( $opts['image_optimization_enabled'] );
                $bot_shield_enabled           = ! empty( $opts['bot_shield_enabled'] );
                $bot_shield_mode              = ( isset( $opts['bot_shield_mode'] ) && 'block' === $opts['bot_shield_mode'] ) ? 'block' : 'monitor';
                $bot_shield_page_cap          = ! empty( $opts['bot_shield_page_cap'] ) ? (int) $opts['bot_shield_page_cap'] : 20;
                $bot_shield_ip_limit          = ! empty( $opts['bot_shield_ip_limit'] ) ? (int) $opts['bot_shield_ip_limit'] : 180;
                $bot_shield_penalty_mins      = ! empty( $opts['bot_shield_penalty_mins'] ) ? (int) $opts['bot_shield_penalty_mins'] : 10;
                $bot_shield_global_limit      = ! empty( $opts['bot_shield_global_limit'] ) ? (int) $opts['bot_shield_global_limit'] : 150;
                $bot_shield_attack_mins       = ! empty( $opts['bot_shield_attack_mins'] ) ? (int) $opts['bot_shield_attack_mins'] : 10;
                $bot_shield_challenge_enabled = ! empty( $opts['bot_shield_challenge_enabled'] );
                $bot_shield_ua_blocklist      = isset( $opts['bot_shield_ua_blocklist'] ) ? $opts['bot_shield_ua_blocklist'] : '';
                $bot_shield_ip_allowlist      = isset( $opts['bot_shield_ip_allowlist'] ) ? $opts['bot_shield_ip_allowlist'] : '';
                // Today's block counters (cache reads only; empty string hides the line).
                $bot_shield_stats = '';
                if ( $bot_shield_enabled ) {
                    require_once DS_TOOLKIT_PATH . 'features/class-ds-bot-shield.php';
                    $shield = new DS_Bot_Shield( $opts );
                    $parts  = array();
                    foreach ( array( 'rate-limit' => 'rate-limited', 'ua-block' => 'crawlers blocked', 'challenge' => 'browser checks', 'page-trap' => 'pagination traps' ) as $sk => $sl ) {
                        if ( $shield->stat( $sk ) ) {
                            $parts[] = $shield->stat( $sk ) . ' ' . $sl;
                        }
                    }
                    $bot_shield_stats = implode( ', ', $parts );
                    if ( $bot_shield_stats && 'monitor' === $bot_shield_mode ) {
                        $bot_shield_stats = 'would have blocked: ' . $bot_shield_stats;
                    }
                }
                // Per-module on/off states for the always-visible "LeagueApps Modules" section.
                $ds_module_states = array();
                foreach ( DS_Toolkit::module_features() as $mod_key => $mod_meta ) {
                    $ds_module_states[ $mod_key ] = ! empty( $opts[ $mod_key ] );
                }
                require DS_TOOLKIT_PATH . 'admin/views/page-settings.php';
            }
            ?>

        </div>
        <?php
    }
}
