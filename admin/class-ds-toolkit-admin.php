<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DS_Toolkit_Admin {

    private $logo_finder;

    public function init() {
        add_action( 'admin_menu',            array( $this, 'add_menu' ) );
        add_action( 'admin_init',            array( $this, 'register_settings' ) );
        // Hiding the DS Toolkit page did not stop a direct form post to options.php: that only asked for
        // manage_options, which the Partner role has. Saving these settings now takes the same check as
        // opening the page. Staff are unchanged.
        add_filter( 'option_page_capability_ds_toolkit_options', array( $this, 'settings_capability' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'load-settings_page_ds-toolkit', array( $this, 'redirect_retired_tabs' ) );

        require_once DS_TOOLKIT_PATH . 'admin/class-ds-logo-finder.php';
        $this->logo_finder = new DS_Logo_Finder();
        $this->logo_finder->init();
    }

    /** Where the team's guides live. The reference folds of the Global CSS, Global JS and MCP tabs link here. */
    const HQ_DOCS_URL = 'https://designshophq.wpenginepowered.com/toolkit/';

    /**
     * The University Logo Finder was listed twice: as a tab here and under Media. The Media page is the one
     * partners can reach, so it is the one kept; an old bookmark of the tab lands there.
     */
    public function redirect_retired_tabs() {
        if ( isset( $_GET['tab'] ) && 'logos' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
            wp_safe_redirect( admin_url( 'upload.php?page=ds-logo-finder' ) );
            exit;
        }
    }

    private function get_mcp_url() {
        return rest_url( 'ds-toolkit/v1/mcp' );
    }

    /**
     * Design Shop staff (an administrator with a DS_TOOLKIT_ADMIN_DOMAIN address).
     * The DS Toolkit menu and settings page are restricted to these users.
     */
    private function is_leagueapps_user() {
        return DS_Toolkit::is_leagueapps_user(); // one rule: an administrator with a staff address (1.12.0)
    }

    public function settings_capability( $cap ) {
        return $this->is_leagueapps_user() ? $cap : 'do_not_allow';
    }

    public function add_menu() {
        // The University Logo Finder is a partner-facing tool (school crests for
        // athlete commitment pages), so it gets its own entry under Media for
        // anyone who can add to the library. Settings stays hidden from partners,
        // and the DS Toolkit page below stays LeagueApps-only; this is the same
        // finder reached from a menu they can actually see.
        add_media_page(
            'University Logo Finder',
            'University Logo Finder',
            'upload_files',
            'ds-logo-finder',
            array( $this, 'render_logo_finder_page' )
        );

        if ( ! $this->is_leagueapps_user() ) {
            return;
        }
        add_options_page( 'DS Toolkit', 'DS Toolkit', 'manage_options', 'ds-toolkit', array( $this, 'render_page' ) );
    }

    /**
     * Media -> University Logo Finder, the one place the finder lives (the DS Toolkit tab that repeated it
     * was retired in the 2026-11 audit).
     */
    public function render_logo_finder_page() {
        if ( ! current_user_can( 'upload_files' ) ) {
            return;
        }
        ?>
        <div class="wrap dst-wrap">
            <div class="dst-header">
                <div class="dst-header-icon"><span class="dashicons dashicons-format-image"></span></div>
                <div class="dst-header-text">
                    <h1>University Logo Finder</h1>
                    <p>Search a college or university and import its logo into the Media Library.</p>
                </div>
            </div>
            <?php require DS_TOOLKIT_PATH . 'admin/views/page-logo-finder.php'; ?>
        </div>
        <?php
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
        // Every setting the schema knows is cleaned by its type and range (a number box holding a word, a broken
        // email, a mode that does not exist keep the saved value). Until the 2026-11 audit only the LeagueApps
        // site rows below were cleaned and everything else was saved as posted.
        $new = DS_Features::sanitize( $new, $existing );
        if ( array_key_exists( 'leagueapps_sites', $new ) ) {
            // Site rows from the LeagueApps tab: digits for the id, the public
            // widget key as-is minus anything that is not a key character, a
            // plain-text label. Blank rows are dropped, duplicates collapse.
            $rows = array();
            foreach ( (array) $new['leagueapps_sites'] as $row ) {
                $row = (array) $row;
                $id  = preg_replace( '/\D+/', '', (string) ( $row['site_id'] ?? '' ) );
                $key = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $row['api_key'] ?? '' ) );
                if ( '' === $id || '' === $key ) { continue; }
                $rows[ $id ] = array( 'site_id' => $id, 'api_key' => $key, 'label' => sanitize_text_field( (string) ( $row['label'] ?? '' ) ) );
            }
            $new['leagueapps_sites'] = array_values( $rows );
            // Credentials changed: the next render must refetch, not serve a cache keyed on the old set.
            if ( class_exists( 'DS_Programs_Data' ) || file_exists( DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php' ) ) {
                require_once DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
                DS_Programs_Data::flush();
            }
        }
        return array_merge( $existing, $new );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook === 'media_page_ds-logo-finder' ) {
            wp_enqueue_style( 'ds-toolkit-admin', DS_TOOLKIT_URL . 'assets/css/admin.css', array(), DS_TOOLKIT_VERSION );
            $this->logo_finder->enqueue_assets();
            return;
        }
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

        if ( $active_tab === 'global-css' ) {
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
                <a href="<?php echo esc_url( $base_url . '&tab=global-css' ); ?>" class="dst-tab<?php echo $active_tab === 'global-css' ? ' is-active' : ''; ?>">
                    Global CSS
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=global-js' ); ?>" class="dst-tab<?php echo $active_tab === 'global-js' ? ' is-active' : ''; ?>">
                    Global JS
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=mcp' ); ?>" class="dst-tab<?php echo $active_tab === 'mcp' ? ' is-active' : ''; ?>">
                    MCP
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=leagueapps' ); ?>" class="dst-tab<?php echo $active_tab === 'leagueapps' ? ' is-active' : ''; ?>">
                    LeagueApps
                </a>
            </div>

            <?php
            $opts = get_option( 'ds_toolkit_settings', array() );

            if ( $active_tab === 'general' && DS_Toolkit::blueprint_version() >= 6 ) {
                $footer_copyright_text     = isset( $opts['footer_copyright_text'] ) ? $opts['footer_copyright_text'] : '';
                $copyright_shortcode_enabled = ! empty( $opts['copyright_shortcode_enabled'] );
                require DS_TOOLKIT_PATH . 'admin/views/page-general.php';

            } elseif ( $active_tab === 'leagueapps' ) {
                require_once DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
                $leagueapps_sites   = DS_Programs_Data::configured_sites();
                $programs_module_on = ! empty( $opts['ds_programs_module_enabled'] ) || ! empty( $opts['ds_la_calendar_module_enabled'] ) || ! empty( $opts['ds_la_roster_module_enabled'] );
                $roster_module_on   = ! empty( $opts['ds_la_roster_module_enabled'] );
                $la_sports          = DS_Programs_Data::known_sports();
                $la_activity        = DS_Programs_Data::ledger_summary();
                $la_ledger          = array_slice( DS_Programs_Data::ledger(), 0, 12 );
                require DS_TOOLKIT_PATH . 'admin/views/page-leagueapps.php';

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
                // Tool group toggles: a group is on only when its switch is saved on (a missing key is off)
                $mcp_posts_pages_enabled      = ! empty( $opts['mcp_posts_pages_enabled'] );
                $mcp_cpt_enabled              = ! empty( $opts['mcp_cpt_enabled'] );
                $mcp_taxonomies_enabled       = ! empty( $opts['mcp_taxonomies_enabled'] );
                $mcp_acf_enabled              = ! empty( $opts['mcp_acf_enabled'] );
                $mcp_toolkit_settings_enabled = ! empty( $opts['mcp_toolkit_settings_enabled'] );
                $mcp_bb_enabled               = ! empty( $opts['mcp_bb_enabled'] );
                $mcp_acf_schema_enabled       = ! empty( $opts['mcp_acf_schema_enabled'] );
                $mcp_menus_enabled            = ! empty( $opts['mcp_menus_enabled'] );
                $mcp_maintenance_enabled      = ! empty( $opts['mcp_maintenance_enabled'] );
                $mcp_options_enabled          = ! empty( $opts['mcp_options_enabled'] );
                $mcp_users_enabled            = ! empty( $opts['mcp_users_enabled'] );
                require DS_TOOLKIT_PATH . 'admin/views/page-mcp.php';

            } else {
                // The Features tab draws itself from the schema (DS_Features, admin/class-ds-settings-cards.php).
                // Only the live status lines are worked out here: key => html, shown under the card's summary.
                $opts       = is_array( $opts ) ? $opts : array();
                $dst_status = array();

                // Bot Shield: today's counters (cache reads only).
                if ( ! empty( $opts['bot_shield_enabled'] ) ) {
                    require_once DS_TOOLKIT_PATH . 'features/class-ds-bot-shield.php';
                    $shield  = new DS_Bot_Shield( $opts );
                    $monitor = ! ( isset( $opts['bot_shield_mode'] ) && 'block' === $opts['bot_shield_mode'] );
                    $parts   = array();
                    foreach ( array( 'rate-limit' => 'rate-limited', 'ua-block' => 'crawlers blocked', 'challenge' => 'browser checks', 'page-trap' => 'pagination traps' ) as $sk => $sl ) {
                        if ( $shield->stat( $sk ) ) {
                            $parts[] = $shield->stat( $sk ) . ' ' . $sl;
                        }
                    }
                    $line = implode( ', ', $parts );
                    if ( $line && $monitor ) {
                        $line = 'would have blocked: ' . $line;
                    }
                    $seen = $shield->stat( 'seen' );
                    if ( $seen ) {
                        $line = $seen . ' requests evaluated' . ( $line ? ', ' . $line : ', nothing to block' );
                    }
                    if ( '' === $shield->backend_name() ) {
                        // Nothing can be counted here, so an empty day would read the same as a quiet one.
                        $dst_status['bot_shield_enabled'] = '<strong class="dst-state dst-state--warn">Not counting:</strong> this host has no cache backend (no persistent object cache, no APCu), so rate limits cannot run. Only the crawler block list works.';
                    } elseif ( $line ) {
                        $dst_status['bot_shield_enabled'] = '<strong>Today:</strong> ' . esc_html( $line );
                    }
                }

                // Origin Guard: is the mu-plugin on disk right now? (The installer may have failed to write it.)
                $guard_on        = ! empty( $opts['origin_guard_enabled'] );
                $guard_installed = file_exists( ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' ) . '/ds-origin-guard.php' );
                if ( $guard_on && ! $guard_installed ) {
                    $dst_status['origin_guard_enabled'] = '<strong class="dst-state dst-state--bad">Not installed:</strong> the mu-plugins directory could not be written. Check filesystem permissions.';
                } elseif ( $guard_installed ) {
                    $dst_status['origin_guard_enabled'] = '<strong class="dst-state dst-state--ok">Active</strong>, running as <code>mu-plugins/ds-origin-guard.php</code>.';
                }

                // Report to Design Shop HQ: how HQ sees this site (asked at most every 5 minutes), and Reconnect.
                $tw_on = ! isset( $opts['tripwire_enabled'] ) || ! empty( $opts['tripwire_enabled'] );
                $hq_on = ! isset( $opts['tripwire_hq_report'] ) || ! empty( $opts['tripwire_hq_report'] );
                $hq    = array( 'warn', 'Reporting to Design Shop HQ is off.' . ( $tw_on ? ' Alerts still go by email.' : '' ) );
                if ( $hq_on ) {
                    require_once DS_TOOLKIT_PATH . 'features/class-ds-hq-link.php';
                    $hq = DS_HQ_Link::status_line();
                    if ( ! $tw_on ) {
                        $hq[1] .= ' Tripwire is off, so only check-ins are sent.';
                    }
                }
                $hq_class = in_array( $hq[0], array( 'ok', 'warn', 'bad' ), true ) ? $hq[0] : 'warn';
                $dst_status['tripwire_hq_report'] = '<strong class="dst-state dst-state--' . $hq_class . '">' . esc_html( $hq[1] ) . '</strong>';
                if ( $hq_on ) {
                    $dst_status['tripwire_hq_report'] .= ' <a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ds_hq_link_reconnect' ), 'ds_hq_link_reconnect' ) ) . '">Reconnect</a>';
                }

                // The Status card: is the background work healthy? Staff only (this page already is; the check
                // stays so the card never shows if the page is ever opened to partners).
                $dst_health = array();
                if ( $this->is_leagueapps_user() ) {
                    $dst_health   = DS_Status::rows( $opts );
                    $dst_health[] = array( 'Design Shop HQ', $hq_class, $hq[1] );
                }

                require_once DS_TOOLKIT_PATH . 'admin/class-ds-settings-cards.php';
                require DS_TOOLKIT_PATH . 'admin/views/page-settings.php';
            }
            ?>

        </div>
        <?php
    }
}
