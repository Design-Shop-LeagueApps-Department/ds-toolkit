<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MCP tools: Beaver Builder global colours and layout templates.
 *
 * One part of DS_MCP_Server, which was a single 2,800-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the server (same visibility, same
 * $this). Which switch turns a tool on, which capability it needs and whether it is logged is in
 * DS_MCP_Tools; this file is only what each tool does.
 */
trait DS_MCP_Builder_Tools {

    // -------------------------------------------------------------------------
    // Beaver Builder Tools
    // -------------------------------------------------------------------------

    private function tool_get_bb_global_colors( $id ) {
        $err = $this->leagueapps_gate( $id, 'mcp_bb_enabled' );
        if ( $err ) return $err;
        $settings = get_option( '_fl_builder_styles' );
        if ( empty( $settings ) || empty( $settings->colors ) ) {
            return $this->tool_result( $id, array( 'colors' => new stdClass() ) );
        }
        $colors = array();
        foreach ( $settings->colors as $color ) {
            if ( ! empty( $color['label'] ) && isset( $color['color'] ) ) {
                $colors[ $color['label'] ] = $color['color'];
            }
        }
        return $this->tool_result( $id, array( 'colors' => $colors ) );
    }

    private function tool_update_bb_global_colors( $id, $args ) {
        $err = $this->leagueapps_gate( $id, 'mcp_bb_enabled' );
        if ( $err ) return $err;
        if ( empty( $args['colors'] ) || ! is_array( $args['colors'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: colors (object of label => hex)' );
        }
        if ( ! class_exists( 'FLBuilderModel' ) ) {
            return $this->rpc_error( $id, -32603, 'Beaver Builder is not active on this site' );
        }
        $settings = get_option( '_fl_builder_styles' );
        if ( empty( $settings ) || ! isset( $settings->colors ) ) {
            return $this->rpc_error( $id, -32603, 'No Beaver Builder global colors found. Set them up in BB > Global Styles first.' );
        }
        $updated  = array();
        $rejected = array();
        foreach ( $args['colors'] as $label => $hex ) {
            $matched = false;
            foreach ( $settings->colors as &$color ) {
                if ( isset( $color['label'] ) && $color['label'] === $label ) {
                    $color['color'] = sanitize_hex_color_no_hash( ltrim( $hex, '#' ) );
                    $updated[]      = $label;
                    $matched        = true;
                    break;
                }
            }
            unset( $color );
            if ( ! $matched ) {
                $rejected[] = $label;
            }
        }
        if ( ! empty( $updated ) ) {
            update_option( '_fl_builder_styles', $settings );
            FLBuilderModel::delete_asset_cache_for_all_posts();
        }
        return $this->tool_result( $id, array(
            'updated_colors' => $updated,
            'rejected_labels' => $rejected,
            'note' => empty( $rejected ) ? '' : 'Rejected labels did not match any existing color name. Use get_bb_global_colors to see available names.',
        ) );
    }

    // -------------------------------------------------------------------------
    // Beaver Builder — Layout Template Tools
    // -------------------------------------------------------------------------

    private function tool_bb_list_layout_templates( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_bb_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_bb_enabled' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->rpc_error( $id, -32603, 'manage_options capability required.' );
        }

        $filter_type = ! empty( $args['type'] ) ? sanitize_key( $args['type'] ) : '';

        $templates = get_posts( array(
            'post_type'              => 'fl-builder-template',
            'posts_per_page'         => 200,
            'post_status'            => 'publish',
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ) );

        $patterns = array(
            'header' => '/^Header Style (\d+)$/i',
            'footer' => '/^Footer Style (\d+)$/i',
            'home'   => '/^Home Page Layout (\d+)$/i',
        );

        $result = array( 'header' => array(), 'footer' => array(), 'home' => array() );

        foreach ( $templates as $post ) {
            foreach ( $patterns as $type => $pattern ) {
                if ( preg_match( $pattern, $post->post_title, $m ) ) {
                    $result[ $type ][] = array(
                        'id'           => $post->ID,
                        'title'        => $post->post_title,
                        'style_number' => (int) $m[1],
                    );
                }
            }
        }

        if ( $filter_type && isset( $result[ $filter_type ] ) ) {
            return $this->tool_result( $id, array( 'type' => $filter_type, 'templates' => $result[ $filter_type ] ) );
        }

        return $this->tool_result( $id, array( 'templates' => $result ) );
    }

    private function tool_bb_apply_layout_template( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_bb_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_bb_enabled' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->rpc_error( $id, -32603, 'manage_options capability required.' );
        }
        if ( empty( $args['type'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: type (header, footer, or home)' );
        }
        if ( empty( $args['style_number'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: style_number' );
        }
        if ( empty( $args['confirm'] ) ) {
            return $this->rpc_error( $id, -32602, 'WARNING: This will permanently replace the current layout content. Pass confirm: true to proceed.' );
        }

        $type         = sanitize_key( $args['type'] );
        $style_number = absint( $args['style_number'] );

        $title_map = array(
            'header' => 'Header Style ' . $style_number,
            'footer' => 'Footer Style ' . $style_number,
            'home'   => 'Home Page Layout ' . $style_number,
        );

        if ( ! isset( $title_map[ $type ] ) ) {
            return $this->rpc_error( $id, -32602, 'Invalid type. Must be: header, footer, or home.' );
        }

        $template_title = $title_map[ $type ];

        // Find source template by exact title
        global $wpdb;
        $template_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'fl-builder-template' AND post_status = 'publish' LIMIT 1",
            $template_title
        ) );

        if ( ! $template_id ) {
            return $this->rpc_error( $id, -32602, 'Template not found: "' . $template_title . '". Use bb_list_layout_templates to see available templates.' );
        }

        // Find target layout/page
        $target_id          = 0;
        $target_description = '';

        if ( $type === 'header' || $type === 'footer' ) {
            $target_title = ( $type === 'header' ) ? 'Header Main' : 'Footer Main';
            $target_id    = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'fl-theme-layout' AND post_status != 'trash' LIMIT 1",
                $target_title
            ) );
            $target_description = '"' . $target_title . '" (BB Themer Layout)';
        } else {
            $target_id = (int) get_option( 'page_on_front' );
            if ( ! $target_id ) {
                return $this->rpc_error( $id, -32603, 'No static front page is set. Go to Settings > Reading and assign a static front page first.' );
            }
            $front_page         = get_post( $target_id );
            $target_description = '"' . ( $front_page ? $front_page->post_title : 'Front Page' ) . '" (Front Page, ID ' . $target_id . ')';
        }

        if ( ! $target_id ) {
            $missing = ( $type === 'header' ) ? 'Header Main' : 'Footer Main';
            return $this->rpc_error( $id, -32603, '"' . $missing . '" Themer layout not found on this site.' );
        }

        // Get BB layout data from template
        $builder_data = get_post_meta( $template_id, '_fl_builder_data', true );
        if ( empty( $builder_data ) ) {
            return $this->rpc_error( $id, -32603, '"' . $template_title . '" has no Beaver Builder content to apply.' );
        }

        // Write to target — both the published and draft copies
        update_post_meta( $target_id, '_fl_builder_data',  $builder_data );
        update_post_meta( $target_id, '_fl_builder_draft', $builder_data );

        $data_settings = get_post_meta( $template_id, '_fl_builder_data_settings', true );
        if ( $data_settings ) {
            update_post_meta( $target_id, '_fl_builder_data_settings',  $data_settings );
            update_post_meta( $target_id, '_fl_builder_draft_settings', $data_settings );
        }

        // Touch post modified date so BB picks up the change
        wp_update_post( array( 'ID' => $target_id ) );

        // Flush BB CSS cache
        if ( class_exists( 'FLBuilderModel' ) ) {
            FLBuilderModel::delete_asset_cache_for_all_posts();
        }

        return $this->tool_result( $id, array(
            'applied'   => true,
            'template'  => $template_title,
            'target'    => $target_description,
            'message'   => 'Layout replaced successfully. BB CSS cache flushed — changes are live.',
        ) );
    }
}
