<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [getsubmenu] Shortcode
 *
 * Outputs child pages or nav menu children of a named parent as a list of links.
 *
 * Usage:
 *   [getsubmenu listfrom="Programs" mode="pages"]
 *     — finds the page titled "Programs" and lists its published child pages.
 *
 *   [getsubmenu listfrom="Programs" mode="menus"]
 *     — scans all nav menus for a top-level item titled "Programs"
 *       and lists its direct children.
 *
 * The listfrom value can be a page title, slug, path, or numeric ID (pages mode only).
 */
class DS_Getsubmenu {

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        add_shortcode( 'getsubmenu', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'listfrom' => '',
                'mode'     => 'pages', // pages or menus
            ),
            $atts,
            'getsubmenu'
        );

        $label = trim( $atts['listfrom'] );
        if ( $label === '' ) {
            return '';
        }

        $links = array();

        if ( strtolower( $atts['mode'] ) === 'pages' ) {
            $parent = null;

            // Try numeric ID first
            if ( ctype_digit( $label ) ) {
                $parent = get_post( (int) $label );
            }

            // Try exact path, then sanitized slug
            if ( ! $parent ) {
                $by_path = get_page_by_path( $label );
                if ( ! $by_path ) {
                    $maybe_slug = sanitize_title( $label );
                    if ( $maybe_slug && $maybe_slug !== $label ) {
                        $by_path = get_page_by_path( $maybe_slug );
                    }
                }
                if ( $by_path ) {
                    $parent = $by_path;
                }
            }

            // Fall back to page title (get_page_by_title was deprecated in WP 6.2)
            if ( ! $parent ) {
                $title_query = new WP_Query( array(
                    'post_type'              => 'page',
                    'post_status'            => 'publish',
                    'title'                  => $label,
                    'posts_per_page'         => 1,
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ) );
                if ( ! empty( $title_query->posts ) ) {
                    $parent = $title_query->posts[0];
                }
            }

            if ( ! $parent || 'page' !== $parent->post_type ) {
                return '';
            }

            $children = get_posts( array(
                'post_type'              => 'page',
                'post_status'            => 'publish',
                'post_parent'            => (int) $parent->ID,
                'orderby'                => 'menu_order title',
                'order'                  => 'ASC',
                'posts_per_page'         => 100,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ) );

            foreach ( $children as $child ) {
                $title = $child->post_title;
                $url   = get_permalink( $child );
                if ( $title && $url ) {
                    $links[] = sprintf(
                        '<a href="%s">%s</a>',
                        esc_url( $url ),
                        esc_html( $title )
                    );
                }
            }
        } else {
            // mode = menus — search all nav menus for a parent item matching the label
            $menus = wp_get_nav_menus();
            if ( ! empty( $menus ) ) {
                foreach ( $menus as $menu ) {
                    $items = wp_get_nav_menu_items( $menu->term_id );
                    if ( empty( $items ) ) {
                        continue;
                    }

                    $parent_id = 0;
                    foreach ( $items as $it ) {
                        if ( strcasecmp( $it->title, $label ) === 0 ) {
                            $parent_id = (int) $it->ID;
                            break;
                        }
                    }

                    if ( $parent_id ) {
                        foreach ( $items as $it ) {
                            if ( (int) $it->menu_item_parent === $parent_id ) {
                                $links[] = sprintf(
                                    '<a href="%s">%s</a>',
                                    esc_url( $it->url ),
                                    esc_html( $it->title )
                                );
                            }
                        }
                        if ( $links ) {
                            break; // stop after first matching menu
                        }
                    }
                }
            }
        }

        if ( empty( $links ) ) {
            return '';
        }

        return '<div class="submenu-text">' . implode( '<br />', $links ) . '</div>';
    }
}
