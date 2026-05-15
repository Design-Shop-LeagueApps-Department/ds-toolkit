<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [ds_overlay_nav] and [ds_overlay_subs] Shortcodes
 *
 * Outputs a WordPress nav menu as a full-screen overlay navigation.
 * Designed to work with the DS Overlay Nav CSS/JS pattern.
 *
 * Usage:
 *   Left panel (numbered nav items):
 *     [ds_overlay_nav menu="primary"]
 *
 *   Right panel (sub-link blocks, must follow ds_overlay_nav on same page):
 *     [ds_overlay_subs]
 *
 * The menu attribute accepts a menu name, slug, or location slug.
 * Only top-level items are rendered as nav items.
 * Items with children get a sub-panel in [ds_overlay_subs].
 * Items with no children are skipped in [ds_overlay_subs].
 */
class DS_Overlay_Nav {

    private $settings;

    /**
     * Holds sub-panel data built by [ds_overlay_nav] for use by [ds_overlay_subs].
     * Keyed by sanitized item slug.
     *
     * @var array
     */
    private static $subs = array();

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        add_shortcode( 'ds_overlay_nav',  array( $this, 'render_nav'  ) );
        add_shortcode( 'ds_overlay_subs', array( $this, 'render_subs' ) );
    }

    /**
     * [ds_overlay_nav] — renders the numbered left-panel items.
     */
    public function render_nav( $atts ) {
        $atts = shortcode_atts(
            array(
                'menu' => 'primary',
            ),
            $atts,
            'ds_overlay_nav'
        );

        $menu_items = wp_get_nav_menu_items( $atts['menu'] );

        if ( ! $menu_items ) {
            return '';
        }

        // Reset subs for this render pass
        self::$subs = array();

        $output = '';
        $num    = 1;

        foreach ( $menu_items as $item ) {

            // Top-level items only
            if ( $item->menu_item_parent != 0 ) {
                continue;
            }

            $padded = str_pad( $num, 2, '0', STR_PAD_LEFT );
            $sub_id = 'sub-' . sanitize_title( $item->title );

            // Collect direct children for the right panel
            $children_html = '';
            foreach ( $menu_items as $child ) {
                if ( $child->menu_item_parent != $item->ID ) {
                    continue;
                }
                $children_html .= sprintf(
                    '<a href="%s">%s</a>',
                    esc_url( $child->url ),
                    esc_html( $child->title )
                );
            }

            // Store for [ds_overlay_subs] — only if there are children
            if ( $children_html ) {
                self::$subs[ $sub_id ] = array(
                    'label'    => $item->title,
                    'children' => $children_html,
                );
            }

            $output .= sprintf(
                '<div class="titans-nav-item" data-sub="%s" data-num="%s">
                    <span class="tni-num">%s</span>
                    <a class="tni-text" href="%s">%s</a>
                </div>',
                esc_attr( $sub_id ),
                esc_attr( $padded ),
                esc_html( $padded ),
                esc_url( $item->url ),
                esc_html( $item->title )
            );

            $num++;
        }

        return $output;
    }

    /**
     * [ds_overlay_subs] — renders the right-panel sub-link blocks.
     * Must appear after [ds_overlay_nav] on the same page.
     */
    public function render_subs( $atts ) {

        if ( empty( self::$subs ) ) {
            return '';
        }

        $output = '';

        foreach ( self::$subs as $id => $sub ) {
            $output .= sprintf(
                '<div class="titans-sub" id="%s">
                    <h4>%s</h4>
                    %s
                </div>',
                esc_attr( $id ),
                esc_html( $sub['label'] ),
                $sub['children']
            );
        }

        return $output;
    }
}
