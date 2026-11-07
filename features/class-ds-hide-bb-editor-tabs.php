<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hide Beaver Builder's "Text Editor" / "Beaver Builder" tabs on the edit screen for non-LeagueApps users.
 *
 * Why (ClickUp 868mtnn1a, Lou): a partner clicked "Text Editor" on a page built in Beaver Builder, which switches
 * the layout off and the page falls back to the plain WordPress content (Moss Park's Ice Rentals page, 2026-09-29).
 * Only pages where Beaver Builder is ON lose the tabs (Beaver Builder adds `fl-builder-enabled` to the admin body
 * there), so the "Launch Beaver Builder" button stays, and a page not built in Beaver Builder keeps its normal
 * editor and tabs. @leagueapps.com accounts always see the tabs. On by default; turn it off on a site when a
 * partner genuinely needs the text editor.
 */
class DS_Hide_BB_Editor_Tabs {

    const LEAGUEAPPS_DOMAIN = '@leagueapps.com';

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        add_action( 'admin_head', array( $this, 'maybe_hide_tabs' ) );
    }

    /** True for a logged-in account whose email ends in @leagueapps.com (any letter case). */
    public static function is_leagueapps_user() {
        $user = wp_get_current_user();
        $email = strtolower( trim( (string) ( $user->user_email ?? '' ) ) );
        return '' !== $email && substr( $email, -strlen( self::LEAGUEAPPS_DOMAIN ) ) === self::LEAGUEAPPS_DOMAIN;
    }

    public function maybe_hide_tabs() {
        if ( ! is_user_logged_in() || self::is_leagueapps_user() ) {
            return;
        }
        // Post / page / CPT edit screens only (where Beaver Builder prints the tabs).
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'post' !== $screen->base ) {
            return;
        }
        echo '<style id="ds-hide-bb-editor-tabs">body.fl-builder-enabled .fl-builder-admin-tabs{display:none !important;}</style>' . "\n";
    }
}
