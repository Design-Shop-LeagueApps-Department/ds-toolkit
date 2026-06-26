<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Disable Comments.
 *
 * Turns WordPress comments off site-wide without a separate plugin: closes
 * comments and pings everywhere, hides any existing comments, strips comment
 * support from every post type (so the editor metaboxes disappear), and cleans
 * the admin (Comments menu, dashboard widget, admin-bar node, discussion-page
 * redirect). Blueprint generation 6+ only — older DSLP installs keep using
 * their standalone disable-comments plugin and are untouched.
 */
class DS_Disable_Comments {

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        // Close comments / pingbacks on everything and report them closed.
        add_filter( 'comments_open', '__return_false', 20 );
        add_filter( 'pings_open',    '__return_false', 20 );

        // Hide any comments that already exist in the DB.
        add_filter( 'comments_array', array( $this, 'hide_existing_comments' ), 10, 2 );

        // Drop comment/trackback support from all post types so the editor
        // metaboxes and the front-end comment form never render.
        add_action( 'init', array( $this, 'remove_comment_support' ), 100 );

        // Remove the comment & ping feed links from <head>.
        add_action( 'init', array( $this, 'remove_feed_links' ) );

        // Admin cleanup.
        add_action( 'admin_menu',        array( $this, 'remove_admin_menu' ) );
        add_action( 'admin_init',        array( $this, 'redirect_comments_page' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widget' ) );

        // Remove the "Comments" node from the admin bar, front and back.
        add_action( 'admin_bar_menu',    array( $this, 'remove_admin_bar_node' ), 999 );
    }

    /**
     * @param array $comments
     * @param int   $post_id
     * @return array
     */
    public function hide_existing_comments( $comments, $post_id ) {
        return array();
    }

    public function remove_comment_support() {
        foreach ( get_post_types() as $post_type ) {
            if ( post_type_supports( $post_type, 'comments' ) ) {
                remove_post_type_support( $post_type, 'comments' );
                remove_post_type_support( $post_type, 'trackbacks' );
            }
        }
    }

    public function remove_feed_links() {
        // Suppress the per-post and site comment feeds.
        add_filter( 'feed_links_show_comments_feed', '__return_false' );
    }

    public function remove_admin_menu() {
        remove_menu_page( 'edit-comments.php' );
    }

    /**
     * Bounce any direct hit on the comments admin screen back to the dashboard,
     * since the menu is gone but the URL still resolves.
     */
    public function redirect_comments_page() {
        global $pagenow;
        if ( 'edit-comments.php' === $pagenow ) {
            wp_safe_redirect( admin_url() );
            exit;
        }
    }

    public function remove_dashboard_widget() {
        remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
    }

    /**
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function remove_admin_bar_node( $wp_admin_bar ) {
        if ( is_object( $wp_admin_bar ) && method_exists( $wp_admin_bar, 'remove_node' ) ) {
            $wp_admin_bar->remove_node( 'comments' );
        }
    }
}
