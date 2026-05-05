<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DS_Logo_Finder {

    public function init() {
        // Only register the AJAX handler — menu and assets are managed by DS_Toolkit_Admin
        add_action( 'wp_ajax_ds_import_logo', array( $this, 'ajax_import_logo' ) );
    }

    /**
     * Enqueues logo finder assets. Called by DS_Toolkit_Admin when the logos tab is active.
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'ds-logo-finder',
            DS_TOOLKIT_URL . 'assets/css/logo-finder.css',
            array(),
            DS_TOOLKIT_VERSION
        );
        wp_enqueue_script(
            'ds-logo-finder',
            DS_TOOLKIT_URL . 'assets/js/logo-finder.js',
            array( 'jquery' ),
            DS_TOOLKIT_VERSION,
            true
        );
        wp_localize_script( 'ds-logo-finder', 'dsLogoFinder', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ds_import_logo' ),
            'logos'   => $this->get_logo_list(),
        ) );
    }

    /**
     * Returns sorted array of logos with name and pre-built URL.
     * URL built in PHP with rawurlencode so spaces in filenames work correctly.
     */
    public function get_logo_list() {
        $dir   = DS_TOOLKIT_PATH . 'assets/images/team_logos/';
        $files = glob( $dir . '*.webp' );
        if ( ! $files ) {
            return array();
        }
        $logos = array();
        foreach ( $files as $file ) {
            $name    = basename( $file, '.webp' );
            $logos[] = array(
                'name' => $name,
                'url'  => DS_TOOLKIT_URL . 'assets/images/team_logos/' . rawurlencode( $name ) . '.webp',
            );
        }
        usort( $logos, function( $a, $b ) {
            return strcmp( $a['name'], $b['name'] );
        } );
        return $logos;
    }

    /**
     * AJAX handler — imports a single logo into the WP Media Library.
     * Called once per logo so JS can show real-time progress.
     */
    public function ajax_import_logo() {
        check_ajax_referer( 'ds_import_logo', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        if ( ! $name ) {
            wp_send_json_error( array( 'message' => 'No logo name provided.' ) );
        }

        $filename = $name . '.webp';
        $source   = DS_TOOLKIT_PATH . 'assets/images/team_logos/' . $filename;

        if ( ! file_exists( $source ) ) {
            wp_send_json_error( array( 'message' => 'File not found: ' . $filename ) );
        }

        // Check if already imported. Direct $wpdb query with an anchored trailing match
        // (`%/<filename>`) is more selective than WP_Query's auto-wrapped `%<filename>%`
        // and avoids the JOIN against wp_posts that meta_query would build.
        global $wpdb;
        $existing_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'
               AND meta_value LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like( '/' . $filename )
        ) );

        if ( $existing_id ) {
            wp_send_json_success( array(
                'status'  => 'exists',
                'message' => $name . ' — already in Media Library',
            ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $tmp = wp_tempnam( $filename );
        if ( ! copy( $source, $tmp ) ) {
            wp_send_json_error( array( 'message' => 'Could not copy file to temp location.' ) );
        }

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp,
            'type'     => 'image/png',
            'error'    => 0,
            'size'     => filesize( $tmp ),
        );

        $attachment_id = media_handle_sideload( $file_array, 0, $name );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        wp_send_json_success( array(
            'status'        => 'imported',
            'message'       => $name . ' — imported',
            'attachment_id' => $attachment_id,
        ) );
    }
}
