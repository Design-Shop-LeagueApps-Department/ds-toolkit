<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once dirname( __DIR__ ) . '/includes/class-ds-env.php';

class DS_Hide_FL_Assistant {


    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        add_action( 'wp_head', array( $this, 'maybe_hide_fl_assistant' ) );
    }

    public function maybe_hide_fl_assistant() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $current_user = wp_get_current_user();

        if ( empty( $current_user->user_email ) ) {
            return;
        }

        // Do NOT hide for @leagueapps.com users
        if ( DS_Env::is_staff_email( $current_user->user_email ) ) {
            return;
        }

        echo '<style>.fl-builder-edit .fl-builder-bar-actions .fl-builder-fl-assistant-button { display: none !important; }</style>';
    }
}
