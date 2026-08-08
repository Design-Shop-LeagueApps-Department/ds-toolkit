<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin Menu Tidy (blueprint generation 6+).
 *
 * Declutters the wp-admin menu for partner sites.
 *
 *  - Moved under Tools (everyone who can already see them): Defender Pro,
 *    Yoast SEO, Media Library Organizer.
 *  - Left in place but hidden from non-LeagueApps users: ACF, Beaver Builder.
 *  - Appearance stays visible to every user whose role allows it (partners
 *    manage menus/customizer themselves; the risky surfaces stay off because
 *    the Partner role never gets the file-editor capabilities).
 *  - Left untouched (relocating is not clean): Site Kit (custom menu/screen
 *    system makes a moved entry vanish) and WPMU DEV (whitelabel removes its
 *    top-level menu and its page hook isn't access-registered, so a link errors).
 *
 * It performs a "label move" — only the menu entry relocates; the plugin's page
 * registration is untouched, so pages keep working. Capabilities are preserved,
 * so it only relocates what a user could already access — it never grants access.
 */
class DS_Admin_Menu {

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        // Run after all plugins have registered their menus (very late so we
        // win over plugins that reorganize their own menu, e.g. Site Kit).
        add_action( 'admin_menu', array( $this, 'reorganize' ), 999999 );
        // Appearance sub-screens partners must not reach, blocked by URL too.
        add_action( 'admin_init', array( $this, 'block_theme_screens' ) );
    }

    /**
     * Appearance sub-screens hidden from (and blocked for) non-LeagueApps users.
     *
     * Appearance itself stays visible — partners own their navigation, and
     * Menus / Customize live under it. These four are the Design Shop's:
     *
     *   themes.php        Themes      — switching or deleting the frozen child theme
     *   site-editor.php   Patterns    — block-editor surface the fleet does not use
     *   widgets.php       Widgets     — the blueprint has no widget areas in play
     *   font-library.php  Fonts       — brand fonts come from Theme Setting / Typekit
     *
     * Themes is additionally unreachable because DS_User_Roles drops
     * `switch_themes` from the Partner role. The other three ride on
     * `edit_theme_options`, which Menus and Customize also need, so they cannot be
     * blocked by capability without breaking those — hence the explicit URL guard.
     */
    private static function blocked_theme_screens() {
        return array( 'themes.php', 'site-editor.php', 'widgets.php', 'font-library.php' );
    }

    /**
     * Refuse a direct request to a blocked Appearance screen.
     *
     * Hiding a submenu is cosmetic — the screen stays reachable by URL — so the
     * hide in reorganize() is paired with this. `themes.php?page=…` is allowed
     * through: plugins (e.g. Adobe Fonts) register their own pages under the
     * Appearance parent and those are not the Themes screen.
     */
    public function block_theme_screens() {
        if ( DS_Toolkit::is_leagueapps_user() ) {
            return;
        }
        if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return;
        }

        $current = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
        if ( ! in_array( $current, self::blocked_theme_screens(), true ) ) {
            return;
        }
        // A plugin page parented to Appearance, not the Themes screen itself.
        if ( 'themes.php' === $current && ! empty( $_GET['page'] ) ) {
            return;
        }

        wp_die(
            esc_html__( 'That screen is managed by the LeagueApps Design Shop. If you need a change here, contact your Design Shop team.', 'ds-toolkit' ),
            esc_html__( 'Not available', 'ds-toolkit' ),
            array( 'response' => 403, 'back_link' => true )
        );
    }

    /**
     * Drop a submenu entry by its base file, ignoring any query string.
     *
     * Core registers Patterns as `site-editor.php?p=/pattern`, so an exact slug
     * match silently misses it and the link survives while the screen 403s. The
     * comparison is on the part before "?".
     */
    private function remove_submenu( $parent, $slug ) {
        global $submenu;
        if ( empty( $submenu[ $parent ] ) ) {
            return;
        }
        foreach ( $submenu[ $parent ] as $i => $item ) {
            if ( ! isset( $item[2] ) ) {
                continue;
            }
            $base = strtok( $item[2], '?' );
            if ( $base === $slug ) {
                unset( $submenu[ $parent ][ $i ] );
            }
        }
    }

    public function reorganize() {
        $is_la = DS_Toolkit::is_leagueapps_user();

        // Relocated under Tools for anyone who can already see them. These move
        // cleanly and their pages still load under tools.php?page=…
        //
        // Excluded on purpose (relocating them is not clean, so we never break
        // them): Site Kit (custom menu/screen system makes a moved entry vanish)
        // and WPMU DEV (its top-level menu is removed by whitelabel and its page
        // hook isn't registered for access, so a Tools link errors out).
        $move = array( 'wp-defender', 'wpseo_dashboard', 'media-library-organizer' );

        // Left in their original spot, but visible to LeagueApps users only.
        // Appearance (themes.php) is deliberately NOT in this list: partner /
        // non-LeagueApps users need it in the dashboard menu (Menus, Customize,
        // Widgets). Their role's capabilities still decide what loads there.
        $la_only = array(
            'edit.php?post_type=acf-field-group',     // ACF
            'edit.php?post_type=fl-builder-template', // Beaver Builder (top-level menu slug)
        );
        if ( ! $is_la ) {
            foreach ( $la_only as $slug ) {
                remove_menu_page( $slug );
            }
            // Appearance stays, but its Design-Shop-owned screens go. Paired with
            // block_theme_screens(), which refuses the URL as well — hiding a
            // submenu on its own leaves the screen reachable by address bar.
            foreach ( self::blocked_theme_screens() as $slug ) {
                $this->remove_submenu( 'themes.php', $slug );
            }
        }

        foreach ( $move as $slug ) {
            $this->move_to_tools( $slug );
        }
    }

    /**
     * Relocate a top-level menu to a submenu under Tools (a "label move" — the
     * plugin's page registration is untouched, so the page still resolves).
     *
     * If the top-level entry is absent but the page is still registered (e.g.
     * a menu hidden by whitelabel), add a fresh Tools link to it instead. All
     * entries carry the full 4-element shape (incl. page_title at index 3) that
     * WP's submenu renderer requires — a 3-element entry triggers "Undefined
     * array key 3" warnings on every Tools page.
     */
    private function move_to_tools( $slug ) {
        global $menu, $submenu;
        if ( ! is_array( $menu ) ) { return; }
        foreach ( $menu as $i => $item ) {
            if ( isset( $item[2] ) && $item[2] === $slug ) {
                // Strip notification/count bubbles for a clean submenu label.
                $title = trim( preg_replace( '/\s*<span\b.*$/is', '', $item[0] ) );
                if ( '' === $title ) { $title = wp_strip_all_tags( $item[0] ); }
                if ( ! isset( $submenu['tools.php'] ) ) { $submenu['tools.php'] = array(); }
                // 4-element entry incl. page_title at index 3, which WP's submenu
                // renderer requires (a 3-element entry warns "Undefined array key 3").
                $submenu['tools.php'][] = array( $title, $item[1], $item[2], $title );
                unset( $menu[ $i ] );
                return;
            }
        }
    }
}
