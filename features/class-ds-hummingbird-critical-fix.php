<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Keeps Hummingbird's Critical CSS in step with Beaver Builder edits.
 *
 * With Hummingbird's Critical CSS on (Asset Optimization > Critical CSS, "Load on User
 * Interaction"), every page is served with a saved snapshot of the CSS it used
 * (wp-content/wphb-cache/critical-css/<host>/page-<id>-used.css) and the real stylesheets
 * only load once the visitor moves the mouse, scrolls or types. Hummingbird builds that
 * snapshot once and never rebuilds it when the page changes: its Beaver Builder hook only
 * flushes the page cache. A module added after the snapshot has none of its CSS in it, so
 * logged-out visitors see it unstyled until they interact (logged-in editors never do).
 * Seen on Pipeline Soccer Club, 2026-10-28: a Post Loop staff grid showed one column, then
 * four after a mouse move, then one again on reload; the page's snapshot predated the module.
 *
 * Fix: when a layout is published, delete that page's snapshot files. With no snapshot
 * Hummingbird serves the full stylesheets as normal and queues a fresh one. A Themer layout
 * or saved template can appear on any page, so saving one clears every snapshot. Inert
 * unless Hummingbird's Critical CSS module is active.
 */
class DS_Hummingbird_Critical_Fix {

    public function __construct( $settings = array() ) {}

    public function init() {
        add_action( 'fl_builder_after_save_layout', array( $this, 'layout_saved' ), 20, 2 );
        add_action( 'save_post', array( $this, 'post_saved' ), 20, 2 );
    }

    /** Beaver Builder "Publish" (a draft save changes nothing visitors see). */
    public function layout_saved( $post_id, $publish = true ) {
        if ( $publish ) {
            self::clear_for_post( (int) $post_id );
        }
    }

    /** Edits made outside the builder (block editor, quick edit, WP-CLI) change the page too. */
    public function post_saved( $post_id, $post = null ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( 'publish' !== get_post_status( $post_id ) ) {
            return;
        }
        self::clear_for_post( (int) $post_id );
    }

    /**
     * Deletes the snapshot files Hummingbird would serve for this post. Returns the file names
     * removed (for the tests and for anyone checking by hand).
     */
    public static function clear_for_post( $post_id ) {
        $dir = self::snapshot_dir();
        if ( '' === $dir || $post_id <= 0 ) {
            return array();
        }
        $type = (string) get_post_type( $post_id );
        if ( in_array( $type, array( 'fl-theme-layout', 'fl-builder-template' ), true ) ) {
            return self::clear_all( $dir );
        }
        $names = array( 'page' === $type ? 'page-' . $post_id : $type . '-' . $post_id );
        if ( 'page' !== $type && '' !== $type ) {
            $names[] = $type; // single posts share one snapshot per post type
        }
        if ( (int) get_option( 'page_on_front' ) === $post_id ) {
            $names[] = 'frontpage';
        }
        $removed = array();
        foreach ( $names as $name ) {
            foreach ( array( $name, $name . '-mobile' ) as $n ) {
                if ( preg_match( '/^[a-z0-9_-]+$/', $n ) && is_file( $dir . $n . '-used.css' ) && self::delete( $dir . $n . '-used.css' ) ) {
                    $removed[] = $n . '-used.css';
                }
            }
        }
        if ( $removed ) {
            delete_post_meta( $post_id, '_wphb_singular_css_status' );
        }
        return $removed;
    }

    private static function clear_all( $dir ) {
        $removed = array();
        foreach ( (array) glob( $dir . '*-used.css' ) as $file ) {
            if ( is_string( $file ) && self::delete( $file ) ) {
                $removed[] = basename( $file );
            }
        }
        return $removed;
    }

    private static function delete( $file ) {
        wp_delete_file( $file );
        return ! file_exists( $file );
    }

    /** Hummingbird's snapshot folder (with a trailing slash), or '' when Critical CSS is off. */
    public static function snapshot_dir() {
        if ( ! class_exists( '\Hummingbird\Core\Utils' ) ) {
            return '';
        }
        try {
            $m = \Hummingbird\Core\Utils::get_module( 'critical_css' );
            if ( ! is_object( $m ) || ! method_exists( $m, 'is_active' ) || ! method_exists( $m, 'get_critical_css_path' ) || ! $m->is_active() ) {
                return '';
            }
            $dir = trailingslashit( (string) $m->get_critical_css_path() );
        } catch ( \Throwable $e ) {
            return '';
        }
        // Only ever inside Hummingbird's own cache folder.
        $base = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . 'wphb-cache/critical-css/';
        return ( 0 === strpos( wp_normalize_path( $dir ), $base ) && is_dir( $dir ) ) ? $dir : '';
    }
}
