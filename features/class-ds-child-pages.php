<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [child_pages] Shortcode
 *
 * Loops through child pages of the current page and renders each one
 * using a Beaver Builder saved layout template.
 *
 * Usage:
 *   [child_pages]
 *   [child_pages template="56369" columns="3" columns_tablet="2" columns_mobile="1" limit="50"]
 *
 * Also registers:
 *   [get_parent_page_title] — outputs the title of the page where [child_pages] is placed.
 *                             Use this inside your BB card template.
 */
class DS_Child_Pages {

    private $settings;

    /**
     * Stores the parent page ID during the loop so [get_parent_page_title] can read it.
     */
    private static $parent_page_id = 0;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        add_shortcode( 'child_pages',            array( $this, 'render_child_pages' ) );
        add_shortcode( 'get_parent_page_title',  array( $this, 'render_parent_page_title' ) );
        add_action( 'wp_enqueue_scripts',        array( $this, 'enqueue_styles' ) );
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'ds-child-pages',
            DS_TOOLKIT_URL . 'assets/css/child-pages.css',
            array(),
            DS_TOOLKIT_VERSION
        );
    }

    public function render_child_pages( $atts ) {
        global $post;

        // Resolve defaults from settings, with hard fallbacks
        $default_template = ! empty( $this->settings['child_pages_template_id'] )
            ? $this->settings['child_pages_template_id'] : '56369';
        $default_cols        = ! empty( $this->settings['child_pages_columns'] )        ? (int) $this->settings['child_pages_columns']        : 3;
        $default_cols_tablet = ! empty( $this->settings['child_pages_columns_tablet'] ) ? (int) $this->settings['child_pages_columns_tablet'] : 2;
        $default_cols_mobile = ! empty( $this->settings['child_pages_columns_mobile'] ) ? (int) $this->settings['child_pages_columns_mobile'] : 1;

        $atts = shortcode_atts(
            array(
                'template'       => $default_template,
                'columns'        => $default_cols,
                'columns_tablet' => $default_cols_tablet,
                'columns_mobile' => $default_cols_mobile,
                'limit'          => 50,
            ),
            $atts,
            'child_pages'
        );

        $template_id = absint( $atts['template'] );
        $cols        = max( 1, absint( $atts['columns'] ) );
        $cols_tablet = max( 1, absint( $atts['columns_tablet'] ) );
        $cols_mobile = max( 1, absint( $atts['columns_mobile'] ) );
        // Hard cap to keep an unbounded query + per-child BB render loop from blowing memory
        $limit       = max( 1, min( 200, absint( $atts['limit'] ) ?: 50 ) );

        if ( ! $template_id ) {
            return '<!-- [child_pages] error: no template ID set -->';
        }

        $parent_id = get_the_ID();
        if ( ! $parent_id ) {
            return '';
        }

        // Store parent page ID so [get_parent_page_title] can access it during the loop
        self::$parent_page_id = $parent_id;

        $children = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'post_parent'    => $parent_id,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'posts_per_page' => $limit,
        ) );

        if ( empty( $children ) ) {
            return '';
        }

        // Save original post so we can restore it after the loop
        $original_post = $post;
        $output        = '';

        foreach ( $children as $child ) {
            // Set $post to the child page so BB reads its title, permalink, etc. correctly
            $post = $child;
            setup_postdata( $post );

            $output .= '<div class="dst-child-page-item">';
            $output .= do_shortcode( '[fl_builder_insert_layout id="' . $template_id . '"]' );
            $output .= '</div>';
        }

        // Restore original post context
        wp_reset_postdata();
        $post = $original_post;

        $inline_style = sprintf(
            '--dst-cols:%d; --dst-cols-tablet:%d; --dst-cols-mobile:%d;',
            $cols,
            $cols_tablet,
            $cols_mobile
        );

        return '<div class="dst-child-pages" style="' . esc_attr( $inline_style ) . '">'
            . $output
            . '</div>';
    }

    /**
     * [get_parent_page_title]
     * Outputs the title of the page where [child_pages] is placed.
     * Place this inside your Beaver Builder card template.
     */
    public function render_parent_page_title() {
        if ( ! self::$parent_page_id ) {
            return '';
        }
        return esc_html( get_the_title( self::$parent_page_id ) );
    }
}
