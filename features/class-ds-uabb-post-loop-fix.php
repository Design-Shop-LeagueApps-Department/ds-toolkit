<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fixes UABB Advanced Posts module showing the same featured image
 * for all posts when using a Custom/Beaver Builder post layout.
 *
 * Root cause: Beaver Builder's field connection cache keys all resolve to
 * `{node_id}_{page_post_id}` unless FLThemeBuilderFieldConnections::$in_post_grid_loop
 * is true, which makes each key unique per loop post (`{node_id}_{loop_post_id}`).
 * BB's own Post Grid module fires fl_builder_posts_module_before/after_posts to toggle
 * this flag. UABB never fires those actions, so the cache hits on the first post's
 * resolved featured image for every subsequent post.
 *
 * Fix: use UABB's own before/after hooks to toggle the flag around each post render.
 * We only reset the flag if we were the ones who set it, so a future UABB fix that
 * sets the flag at the loop level won't conflict with our per-post reset.
 */
class DS_UABB_Post_Loop_Fix {

    private $flag_owner = false;

    public function __construct( $settings = array() ) {}

    public function init() {
        add_action( 'uabb_blog_posts_before_post', array( $this, 'enter_post_loop' ), 1 );
        add_action( 'uabb_blog_posts_after_post',  array( $this, 'leave_post_loop' ), 1 );
    }

    public function enter_post_loop() {
        if ( class_exists( 'FLThemeBuilderFieldConnections' )
            && ! FLThemeBuilderFieldConnections::$in_post_grid_loop ) {
            FLThemeBuilderFieldConnections::$in_post_grid_loop = true;
            $this->flag_owner = true;
        }
    }

    public function leave_post_loop() {
        if ( $this->flag_owner ) {
            FLThemeBuilderFieldConnections::$in_post_grid_loop = false;
            $this->flag_owner = false;
        }
    }
}
