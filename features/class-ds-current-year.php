<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [current_year] Shortcode
 *
 * Outputs the current year as a number. Useful in footers or copyright notices.
 *
 * Usage:
 *   [current_year]   →   2026
 */
class DS_Current_Year {

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        add_shortcode( 'current_year', array( $this, 'render' ) );
    }

    public function render() {
        // wp_date honours the site's configured timezone instead of the server's.
        return function_exists( 'wp_date' ) ? wp_date( 'Y' ) : date( 'Y' );
    }
}
