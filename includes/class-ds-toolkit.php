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
        'overlay_nav_enabled' => array(
            'file'  => 'features/class-ds-overlay-nav.php',
            'class' => 'DS_Overlay_Nav',
        ),

        // --- Blueprint generation 6+ features --------------------------------
        // 'min_blueprint' gates a feature to a blueprint generation: it only
        // loads, defaults on, and shows a toggle on sites stamped at that
        // generation or higher (see blueprint_version()). Older / unstamped
        // DSLP installs never see these — no toggle, no auto-enable, the class
        // is never even instantiated.
        'disable_comments_enabled' => array(
            'file'          => 'features/class-ds-disable-comments.php',
            'class'         => 'DS_Disable_Comments',
            'min_blueprint' => 6,
        ),
        'copyright_shortcode_enabled' => array(
            'file'          => 'features/class-ds-copyright.php',
            'class'         => 'DS_Copyright',
            'min_blueprint' => 6,
        ),
        'theme_setting_enabled' => array(
            'file'          => 'features/class-ds-theme-setting.php',
            'class'         => 'DS_Theme_Setting',
            'min_blueprint' => 6,
        ),
        // Loads the Google font picked on Theme Setting -> Heading -> "All".
        // Beaver Builder renders that rule but never enqueues its font.
        'ds_global_heading_font_enabled' => array(
            'file'          => 'features/class-ds-global-heading-font.php',
            'class'         => 'DS_Global_Heading_Font',
            'min_blueprint' => 6,
        ),
        'admin_menu_tidy_enabled' => array(
            'file'          => 'features/class-ds-admin-menu.php',
            'class'         => 'DS_Admin_Menu',
            'min_blueprint' => 6,
        ),
        'ds_menu_module_enabled' => array(
            'file'          => 'features/class-ds-menu.php',
            'class'         => 'DS_Menu',
            'min_blueprint' => 6,
        ),
        'ds_social_module_enabled' => array(
            'file'          => 'features/class-ds-social.php',
            'class'         => 'DS_Social',
            'min_blueprint' => 6,
        ),
        'ds_social_card_enabled' => array(
            'file'          => 'features/class-ds-social-card.php',
            'class'         => 'DS_Social_Card',
            'min_blueprint' => 6,
        ),
        'ds_hero_module_enabled' => array(
            'file'          => 'features/class-ds-hero.php',
            'class'         => 'DS_Hero',
            'min_blueprint' => 6,
        ),
        'ds_cta_module_enabled' => array(
            'file'          => 'features/class-ds-cta.php',
            'class'         => 'DS_CTA',
            'min_blueprint' => 6,
        ),
        'ds_marquee_module_enabled' => array(
            'file'          => 'features/class-ds-marquee.php',
            'class'         => 'DS_Marquee',
            'min_blueprint' => 6,
        ),
        'ds_pathway_module_enabled' => array(
            'file'          => 'features/class-ds-pathway.php',
            'class'         => 'DS_Pathway',
            'min_blueprint' => 6,
        ),
        'ds_post_loop_module_enabled' => array(
            'file'          => 'features/class-ds-post-loop.php',
            'class'         => 'DS_Post_Loop',
            'min_blueprint' => 6,
        ),
        'ds_orgstats_module_enabled' => array(
            'file'          => 'features/class-ds-orgstats.php',
            'class'         => 'DS_OrgStats',
            'min_blueprint' => 6,
        ),
        'ds_carousel_module_enabled' => array(
            'file'          => 'features/class-ds-carousel.php',
            'class'         => 'DS_Carousel',
            'min_blueprint' => 6,
        ),
        'ds_heading_module_enabled' => array(
            'file'          => 'features/class-ds-heading.php',
            'class'         => 'DS_Heading',
            'min_blueprint' => 6,
        ),
        'ds_divider_module_enabled' => array(
            'file'          => 'features/class-ds-divider.php',
            'class'         => 'DS_Divider',
            'min_blueprint' => 6,
        ),
        'image_optimization_enabled' => array(
            'file'          => 'features/class-ds-image-optimization.php',
            'class'         => 'DS_Image_Optimization',
            'min_blueprint' => 6,
        ),
        'page_banner_sync_enabled' => array(
            'file'          => 'features/class-ds-page-banner.php',
            'class'         => 'DS_Page_Banner',
            'min_blueprint' => 6,
        ),
        'ds_content_router_module_enabled' => array(
            'file'          => 'features/class-ds-content-router.php',
            'class'         => 'DS_Content_Router',
            'min_blueprint' => 6,
        ),
        'ds_info_list_module_enabled' => array(
            'file'          => 'features/class-ds-info-list.php',
            'class'         => 'DS_Info_List',
            'min_blueprint' => 6,
        ),
        'ds_page_cards_module_enabled' => array(
            'file'          => 'features/class-ds-page-cards.php',
            'class'         => 'DS_Page_Cards',
            'min_blueprint' => 6,
        ),
        'ds_team_detail_module_enabled' => array(
            'file'          => 'features/class-ds-team-detail.php',
            'class'         => 'DS_Team_Detail',
            'min_blueprint' => 6,
        ),
        'ds_builder_defaults_enabled' => array(
            'file'          => 'features/class-ds-builder-defaults.php',
            'class'         => 'DS_Builder_Defaults',
            'min_blueprint' => 6,
        ),
        'ds_admin_bar_links_enabled' => array(
            'file'          => 'features/class-ds-admin-bar-links.php',
            'class'         => 'DS_Admin_Bar_Links',
            'min_blueprint' => 6,
        ),
        // Fleet-wide (no blueprint gate): partner-safe access control and the
        // Design Academy dashboard panel apply to every install, not just DSLP6.
        'user_roles_enabled' => array(
            'file'  => 'features/class-ds-user-roles.php',
            'class' => 'DS_User_Roles',
        ),
        // Cloudflare Turnstile on wp-login + centrally-managed keys for
        // Forminator's native Turnstile. Fleet-wide, but ships OFF: a captcha on
        // the login form with bad keys locks everyone out.
        'cf_turnstile_enabled' => array(
            'file'  => 'features/class-ds-cloudflare.php',
            'class' => 'DS_Cloudflare',
        ),
        'design_academy_enabled' => array(
            'file'  => 'features/class-ds-design-academy.php',
            'class' => 'DS_Design_Academy',
        ),
    );

    /**
     * True only for users whose email matches DS_TOOLKIT_ADMIN_DOMAIN.
     * Shared gate for LeagueApps-only UI (the admin settings page and the
     * internal Theme Setting options page).
     */
    public static function is_leagueapps_user() {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() || empty( $user->user_email ) ) {
            return false;
        }
        return (bool) preg_match( '/' . preg_quote( DS_TOOLKIT_ADMIN_DOMAIN, '/' ) . '$/i', $user->user_email );
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
        return array(
            'ds_hero_module_enabled'           => array( 'label' => 'Hero Banner',     'desc' => 'Full-width hero (image, video, or slideshow) with overlay, eyebrow, headline, and call-to-action buttons.' ),
            'ds_menu_module_enabled'           => array( 'label' => 'Menu',            'desc' => 'Responsive navigation bar with dropdowns, mega-menus, and a full-screen mobile overlay.' ),
            'ds_post_loop_module_enabled'      => array( 'label' => 'Post Loop',       'desc' => 'Lists of news, staff, teams, programs, sponsors, or tournaments in a range of card layouts.' ),
            'ds_page_cards_module_enabled'     => array( 'label' => 'Page Cards',      'desc' => 'A grid of linked cards built from child pages or a manual list.' ),
            'ds_carousel_module_enabled'       => array( 'label' => 'Images/Videos Carousel', 'desc' => 'A stacked-deck slider or a multi-column reels strip of images and videos.' ),
            'ds_orgstats_module_enabled'       => array( 'label' => 'Org Stats',       'desc' => 'Headline stat figures (the record) as plain figures or photo cards.' ),
            'ds_marquee_module_enabled'        => array( 'label' => 'Marquee',         'desc' => 'A scrolling announcement ticker with a pinned label.' ),
            'ds_pathway_module_enabled'        => array( 'label' => 'Pathway',         'desc' => 'Stage cards connected by a progress line: per-stage markers, a fading end, and a vertical timeline on small screens.' ),
            'ds_info_list_module_enabled'      => array( 'label' => 'Post Details',    'desc' => 'Icon-and-text rows from the current post\'s ACF fields, for Themer single templates (staff, events); empty rows hide.' ),
            'ds_cta_module_enabled'            => array( 'label' => 'CTA',             'desc' => 'A call-to-action band with a heading, text, and buttons.' ),
            'ds_heading_module_enabled'        => array( 'label' => 'Heading',         'desc' => 'A styled section heading with an eyebrow line and accent options.' ),
            'ds_divider_module_enabled'        => array( 'label' => 'Divider',         'desc' => 'A horizontal or vertical divider with gradient-fade, running-light, glow, and dashed effects.' ),
            'ds_team_detail_module_enabled'    => array( 'label' => 'Team Detail',     'desc' => 'A single-team layout: roster, schedule, and coaches.' ),
            'ds_content_router_module_enabled' => array( 'label' => 'Content Router',  'desc' => 'Renders the right body layout for each page type (single vs. archive) from one Themer template.' ),
            'ds_social_module_enabled'         => array( 'label' => 'Partner Social',  'desc' => "A row of the partner's social-media links." ),
        );
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

    private static function get_defaults() {
        // On fresh install only the core LeagueApps-branding features are on.
        // Everything else is opt-in so a new site doesn't get unexpected CSS/JS,
        // shortcodes, or MCP tools the moment the plugin is activated.
        $defaults = array(
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
            'overlay_nav_enabled'                => 0,
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
            // Compat patch — only does anything when UABB Advanced Posts fires its
            // hooks, so it's safe to default on. Sites without UABB are unaffected.
            'uabb_post_loop_fix_enabled'         => 1,
            // Partner-safe access control + the Design Academy dashboard panel:
            // on for the whole fleet regardless of blueprint generation.
            'user_roles_enabled'                 => 1,
            // Cloudflare Turnstile — OFF by default on purpose. Enabling it with
            // wrong keys would lock every user out of wp-login, so it must be a
            // deliberate per-site decision, and it refuses to enforce until both
            // keys are filled in.
            'cf_turnstile_enabled'               => 0,
            'cf_turnstile_site_key'              => '',
            'cf_turnstile_secret_key'            => '',
            'cf_turnstile_theme'                 => 'auto',
            'cf_turnstile_monitor'               => 1,
            'cf_turnstile_protect_login'         => 1,
            'cf_turnstile_protect_register'      => 1,
            'cf_turnstile_protect_lostpassword'  => 1,
            'cf_turnstile_sync_forminator'       => 1,
            'partner_plugin_access'              => 0,
            'design_academy_enabled'             => 1,
            'academy_pinned_url'                 => 'https://designacademy.leagueapps.com/course/how-to-edit-your-website-a-beginners-guide-to-wordpress-beaverbuilder/',
            'academy_pinned_label'               => 'How to Edit Your Website: A Beginner\'s Guide to WordPress & Beaver Builder',
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

        // Blueprint-gated defaults. These keys are added ONLY on sites stamped
        // at the required generation, so older / unstamped installs never gain
        // the option keys (and therefore never show the toggles). On qualifying
        // sites they default ON — that's the "auto-enable on DSLP6+" behavior.
        $bp = self::blueprint_version();
        if ( $bp >= 6 ) {
            $defaults['disable_comments_enabled']    = 1;
            $defaults['copyright_shortcode_enabled'] = 1;
            $defaults['footer_copyright_text']       = '&copy; {year} LeagueApps Design Shop';
            $defaults['theme_setting_enabled']        = 1;
            $defaults['ds_global_heading_font_enabled'] = 1;
            $defaults['admin_menu_tidy_enabled']      = 1;
            $defaults['ds_menu_module_enabled']       = 1;
            $defaults['ds_social_module_enabled']     = 1;
            $defaults['ds_social_card_enabled']       = 1;
            $defaults['ds_hero_module_enabled']       = 1;
            $defaults['ds_cta_module_enabled']        = 1;
            $defaults['ds_marquee_module_enabled']    = 1;
            $defaults['ds_pathway_module_enabled']    = 1;
            $defaults['ds_post_loop_module_enabled']  = 1;
            $defaults['ds_orgstats_module_enabled']   = 1;
            $defaults['ds_carousel_module_enabled']   = 1;
            $defaults['ds_heading_module_enabled']    = 1;
            $defaults['ds_divider_module_enabled']    = 1;
            $defaults['image_optimization_enabled']   = 1;
            $defaults['page_banner_sync_enabled']     = 1;
            $defaults['ds_content_router_module_enabled'] = 1;
            $defaults['ds_info_list_module_enabled']  = 1;
            $defaults['ds_page_cards_module_enabled'] = 1;
            $defaults['ds_team_detail_module_enabled'] = 1;
            $defaults['ds_builder_defaults_enabled']   = 1;
            $defaults['ds_admin_bar_links_enabled']    = 1;
        }

        return $defaults;
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
        }

        $blueprint    = self::blueprint_version();
        $module_keys  = self::module_features();

        foreach ( $this->features as $key => $feature ) {
            // Blueprint-gated features never load below their required generation,
            // EXCEPT the LeagueApps modules: those are additive builder blocks that
            // are opt-in on any blueprint (off by default below their generation),
            // so an existing site can enable individual blocks without a blueprint bump.
            $is_module = isset( $module_keys[ $key ] );
            if ( ! empty( $feature['min_blueprint'] ) && ! $is_module && $blueprint < (int) $feature['min_blueprint'] ) {
                continue;
            }
            if ( ! empty( $settings[ $key ] ) ) {
                require_once DS_TOOLKIT_PATH . $feature['file'];
                $instance = new $feature['class']( $settings );
                $instance->init();
            }
        }
    }
}
