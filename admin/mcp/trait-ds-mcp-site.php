<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MCP tools: menus, maintenance, options, users and media.
 *
 * One part of DS_MCP_Server, which was a single 2,800-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the server (same visibility, same
 * $this). Which switch turns a tool on, which capability it needs and whether it is logged is in
 * DS_MCP_Tools; this file is only what each tool does.
 */
trait DS_MCP_Site_Tools {

    // -------------------------------------------------------------------------
    // Menu Tools
    // -------------------------------------------------------------------------

    private function tool_list_menus( $id ) {
        $gate = $this->is_group_enabled( 'mcp_menus_enabled' );
        if ( ! $gate ) {
            return $this->group_disabled_error( $id, 'mcp_menus_enabled' );
        }
        $menus = wp_get_nav_menus();
        $locations = get_nav_menu_locations();
        $location_map = array();
        foreach ( $locations as $loc => $menu_id ) {
            $location_map[ $menu_id ][] = $loc;
        }
        $result = array();
        foreach ( $menus as $menu ) {
            $result[] = array(
                'id'        => $menu->term_id,
                'name'      => $menu->name,
                'slug'      => $menu->slug,
                'count'     => $menu->count,
                'locations' => isset( $location_map[ $menu->term_id ] ) ? $location_map[ $menu->term_id ] : array(),
            );
        }
        return $this->tool_result( $id, array( 'menus' => $result, 'registered_locations' => array_keys( get_registered_nav_menus() ) ) );
    }

    private function tool_get_menu( $id, $args ) {
        $gate = $this->is_group_enabled( 'mcp_menus_enabled' );
        if ( ! $gate ) {
            return $this->group_disabled_error( $id, 'mcp_menus_enabled' );
        }
        if ( empty( $args['id'] ) && empty( $args['slug'] ) ) {
            return $this->rpc_error( $id, -32602, 'Provide id or slug' );
        }
        $menu = ! empty( $args['id'] ) ? wp_get_nav_menu_object( (int) $args['id'] ) : wp_get_nav_menu_object( sanitize_text_field( $args['slug'] ) );
        if ( ! $menu ) {
            return $this->rpc_error( $id, -32602, 'Menu not found' );
        }
        $items = wp_get_nav_menu_items( $menu->term_id );
        $formatted = array();
        if ( $items ) {
            foreach ( $items as $item ) {
                $formatted[] = array(
                    'id'          => $item->ID,
                    'title'       => $item->title,
                    'url'         => $item->url,
                    'order'       => $item->menu_order,
                    'parent'      => (int) $item->menu_item_parent,
                    'object_type' => $item->object,
                    'object_id'   => (int) $item->object_id,
                    'target'      => $item->target,
                    'classes'     => $item->classes,
                );
            }
        }
        return $this->tool_result( $id, array(
            'id'    => $menu->term_id,
            'name'  => $menu->name,
            'slug'  => $menu->slug,
            'items' => $formatted,
        ) );
    }

    private function tool_set_menu_items( $id, $args ) {
        $gate = $this->is_group_enabled( 'mcp_menus_enabled' );
        if ( ! $gate ) {
            return $this->group_disabled_error( $id, 'mcp_menus_enabled' );
        }
        if ( empty( $args['id'] ) && empty( $args['slug'] ) ) {
            return $this->rpc_error( $id, -32602, 'Provide id or slug' );
        }
        if ( empty( $args['confirm'] ) ) {
            return $this->rpc_error( $id, -32602, 'Set confirm: true to replace the menu structure. This removes all existing items.' );
        }
        if ( empty( $args['items'] ) || ! is_array( $args['items'] ) ) {
            return $this->rpc_error( $id, -32602, 'items array is required' );
        }
        $menu = ! empty( $args['id'] ) ? wp_get_nav_menu_object( (int) $args['id'] ) : wp_get_nav_menu_object( sanitize_text_field( $args['slug'] ) );
        if ( ! $menu ) {
            return $this->rpc_error( $id, -32602, 'Menu not found' );
        }
        // Delete existing items
        $existing = wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) );
        if ( $existing ) {
            foreach ( $existing as $item ) {
                wp_delete_post( $item->ID, true );
            }
        }
        // Insert new items — two passes to handle parent references
        $id_map    = array();
        $new_items = array();
        foreach ( $args['items'] as $index => $item ) {
            $item_args = array(
                'menu-item-title'   => sanitize_text_field( isset( $item['title'] ) ? $item['title'] : '' ),
                'menu-item-url'     => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
                'menu-item-status'  => 'publish',
                'menu-item-target'  => isset( $item['target'] ) ? sanitize_text_field( $item['target'] ) : '',
                'menu-item-classes' => isset( $item['classes'] ) ? sanitize_text_field( $item['classes'] ) : '',
            );
            if ( ! empty( $item['object_id'] ) && ! empty( $item['object_type'] ) ) {
                $item_args['menu-item-object']    = sanitize_text_field( $item['object_type'] );
                $item_args['menu-item-object-id'] = (int) $item['object_id'];
                $item_args['menu-item-type']      = 'post_type';
            } else {
                $item_args['menu-item-type'] = 'custom';
            }
            $new_items[] = array( 'args' => $item_args, 'temp_parent' => isset( $item['parent_index'] ) ? (int) $item['parent_index'] : 0 );
        }
        foreach ( $new_items as $index => $data ) {
            $parent_id = 0;
            if ( $data['temp_parent'] > 0 && isset( $id_map[ $data['temp_parent'] ] ) ) {
                $parent_id = $id_map[ $data['temp_parent'] ];
            }
            $data['args']['menu-item-parent-id'] = $parent_id;
            $new_id = wp_update_nav_menu_item( $menu->term_id, 0, $data['args'] );
            if ( ! is_wp_error( $new_id ) ) {
                $id_map[ $index ] = $new_id;
            }
        }
        return $this->tool_result( $id, array( 'success' => true, 'menu_id' => $menu->term_id, 'items_created' => count( $id_map ) ) );
    }

    private function tool_assign_menu_to_location( $id, $args ) {
        $gate = $this->is_group_enabled( 'mcp_menus_enabled' );
        if ( ! $gate ) {
            return $this->group_disabled_error( $id, 'mcp_menus_enabled' );
        }
        if ( empty( $args['menu_id'] ) || empty( $args['location'] ) ) {
            return $this->rpc_error( $id, -32602, 'menu_id and location are required' );
        }
        $menu = wp_get_nav_menu_object( (int) $args['menu_id'] );
        if ( ! $menu ) {
            return $this->rpc_error( $id, -32602, 'Menu not found' );
        }
        $registered = get_registered_nav_menus();
        $location   = sanitize_text_field( $args['location'] );
        if ( ! isset( $registered[ $location ] ) ) {
            return $this->rpc_error( $id, -32602, 'Location "' . $location . '" is not registered. Use list_menus to see registered_locations.' );
        }
        $locations              = get_nav_menu_locations();
        $locations[ $location ] = $menu->term_id;
        set_theme_mod( 'nav_menu_locations', $locations );
        return $this->tool_result( $id, array( 'success' => true, 'menu' => $menu->name, 'location' => $location ) );
    }

    // -------------------------------------------------------------------------
    // Maintenance Tools
    // -------------------------------------------------------------------------

    private function tool_flush_rewrite_rules( $id ) {
        if ( ! $this->is_group_enabled( 'mcp_maintenance_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_maintenance_enabled' );
        }
        flush_rewrite_rules( true );
        return $this->tool_result( $id, array( 'success' => true, 'message' => 'Rewrite rules flushed.' ) );
    }

    private function tool_flush_cache( $id ) {
        if ( ! $this->is_group_enabled( 'mcp_maintenance_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_maintenance_enabled' );
        }
        wp_cache_flush();
        return $this->tool_result( $id, array( 'success' => true, 'message' => 'Object cache flushed.' ) );
    }

    private function tool_delete_transients( $id ) {
        if ( ! $this->is_group_enabled( 'mcp_maintenance_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_maintenance_enabled' );
        }
        global $wpdb;
        $deleted = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
        // Persistent object caches (Redis/Memcached) hold transients in memory, not in wp_options.
        // Flush them too so the tool actually clears transients on those hosts.
        $object_cache_flushed = wp_using_ext_object_cache() ? wp_cache_flush() : false;
        return $this->tool_result( $id, array(
            'success'              => true,
            'deleted_rows'         => (int) $deleted,
            'object_cache_flushed' => (bool) $object_cache_flushed,
        ) );
    }

    private function tool_search_replace( $id, $args ) {
        $gate = $this->leagueapps_gate( $id, 'mcp_maintenance_enabled' );
        if ( $gate ) return $gate;
        if ( empty( $args['search'] ) || ! isset( $args['replace'] ) ) {
            return $this->rpc_error( $id, -32602, 'search and replace are required' );
        }
        if ( empty( $args['confirm'] ) ) {
            return $this->rpc_error( $id, -32602, 'Set confirm: true. search_replace modifies the database directly and cannot be undone.' );
        }
        global $wpdb;
        $search  = $args['search'];
        $replace = $args['replace'];
        $tables  = ! empty( $args['tables'] ) ? array_map( 'sanitize_text_field', (array) $args['tables'] ) : array( $wpdb->posts, $wpdb->postmeta, $wpdb->options );
        // Only this site's own tables, by exact name (the name is interpolated into the queries below).
        $own    = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
        $tables = array_values( array_intersect( $tables, $own ) );
        if ( ! $tables ) {
            return $this->rpc_error( $id, -32602, 'No table of this site matched (tables must start with ' . $wpdb->prefix . ')' );
        }
        $total   = 0;
        $report  = array();
        foreach ( $tables as $table ) {
            // Only allow tables in this database
            $cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
            if ( ! $cols ) continue;
            $text_cols = array();
            foreach ( $cols as $col ) {
                if ( preg_match( '/char|text|mediumtext|longtext/i', $col['Type'] ) ) {
                    $text_cols[] = $col['Field'];
                }
            }
            $updated = 0;
            foreach ( $text_cols as $col ) {
                $result = $wpdb->query( $wpdb->prepare(
                    "UPDATE `{$table}` SET `{$col}` = REPLACE(`{$col}`, %s, %s) WHERE `{$col}` LIKE %s",
                    $search, $replace, '%' . $wpdb->esc_like( $search ) . '%'
                ) );
                if ( $result ) $updated += $result;
            }
            if ( $updated ) {
                $report[] = array( 'table' => $table, 'rows_updated' => $updated );
                $total   += $updated;
            }
        }
        return $this->tool_result( $id, array( 'success' => true, 'total_rows_updated' => $total, 'report' => $report ) );
    }

    // -------------------------------------------------------------------------
    // Options Tools
    // -------------------------------------------------------------------------

    private function tool_get_option( $id, $args ) {
        $gate = $this->leagueapps_gate( $id, 'mcp_options_enabled' );
        if ( $gate ) return $gate;
        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'key is required' );
        }
        $key   = sanitize_text_field( $args['key'] );
        if ( $this->secret_option( $key ) ) {
            return $this->rpc_error( $id, -32603, 'Option "' . $key . '" holds keys or private data and is not available through MCP.' );
        }
        $value = get_option( $key );
        if ( false === $value ) {
            return $this->rpc_error( $id, -32602, 'Option "' . $key . '" not found.' );
        }
        return $this->tool_result( $id, array( 'key' => $key, 'value' => $value ) );
    }

    private function tool_update_option( $id, $args ) {
        $gate = $this->leagueapps_gate( $id, 'mcp_options_enabled' );
        if ( $gate ) return $gate;
        if ( empty( $args['key'] ) || ! isset( $args['value'] ) ) {
            return $this->rpc_error( $id, -32602, 'key and value are required' );
        }
        $key     = sanitize_text_field( $args['key'] );
        if ( $this->secret_option( $key ) ) {
            return $this->rpc_error( $id, -32603, 'Option "' . $key . '" holds keys or private data and is not available through MCP.' );
        }
        $updated = update_option( $key, $args['value'] );
        return $this->tool_result( $id, array( 'success' => true, 'key' => $key, 'updated' => $updated ) );
    }

    /**
     * Options that hold key material, signed-link identities or rosters of minors: never read or written over
     * MCP (they have their own screens with their own checks and audit trail).
     */
    private function secret_option( $key ) {
        return in_array( $key, array( 'ds_la_private_keys', 'ds_la_key_audit', 'ds_hq_link', 'ds_hq_update', 'auth_key', 'secure_auth_key' ), true )
            || 0 === strpos( $key, 'ds_la_roster' )
            || 0 === strpos( $key, '_transient_ds_la_roster' );
    }

    // -------------------------------------------------------------------------
    // User Tools
    // -------------------------------------------------------------------------

    private function tool_list_users( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_users_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_users_enabled' );
        }
        $query_args = array(
            'number'  => min( absint( isset( $args['per_page'] ) ? $args['per_page'] : 50 ), 200 ),
            'offset'  => isset( $args['offset'] ) ? absint( $args['offset'] ) : 0,
            'orderby' => 'registered',
            'order'   => 'DESC',
        );
        if ( ! empty( $args['role'] ) )   $query_args['role']   = sanitize_text_field( $args['role'] );
        if ( ! empty( $args['search'] ) ) $query_args['search'] = '*' . sanitize_text_field( $args['search'] ) . '*';
        $users  = get_users( $query_args );
        $result = array();
        foreach ( $users as $user ) {
            $result[] = array(
                'id'           => $user->ID,
                'login'        => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'roles'        => $user->roles,
                'registered'   => $user->user_registered,
            );
        }
        return $this->tool_result( $id, array( 'users' => $result, 'count' => count( $result ) ) );
    }

    private function tool_get_user( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_users_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_users_enabled' );
        }
        if ( empty( $args['id'] ) && empty( $args['email'] ) ) {
            return $this->rpc_error( $id, -32602, 'Provide id or email' );
        }
        $user = ! empty( $args['id'] ) ? get_user_by( 'id', (int) $args['id'] ) : get_user_by( 'email', sanitize_email( $args['email'] ) );
        if ( ! $user ) {
            return $this->rpc_error( $id, -32602, 'User not found' );
        }
        return $this->tool_result( $id, array(
            'id'           => $user->ID,
            'login'        => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'roles'        => $user->roles,
            'registered'   => $user->user_registered,
            'url'          => $user->user_url,
            'description'  => $user->description,
            'capabilities' => array_keys( array_filter( (array) $user->wp_capabilities ) ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Media Tools
    // -------------------------------------------------------------------------

    private function tool_list_media( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_users_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_users_enabled' );
        }
        $per_page = min( absint( isset( $args['per_page'] ) ? $args['per_page'] : 20 ), 100 );
        $query_args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = sanitize_text_field( $args['search'] );
        }
        if ( ! empty( $args['mime_type'] ) ) {
            $query_args['post_mime_type'] = sanitize_text_field( $args['mime_type'] );
        }
        if ( ! empty( $args['uploaded_to'] ) ) {
            $query_args['post_parent'] = absint( $args['uploaded_to'] );
        }
        $attachments = get_posts( $query_args );
        $result      = array();
        foreach ( $attachments as $att ) {
            $meta = wp_get_attachment_metadata( $att->ID );
            $result[] = array(
                'id'        => $att->ID,
                'title'     => $att->post_title,
                'filename'  => basename( get_attached_file( $att->ID ) ),
                'url'       => wp_get_attachment_url( $att->ID ),
                'mime_type' => $att->post_mime_type,
                'alt'       => get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
                'date'      => $att->post_date,
                'filesize'  => isset( $meta['filesize'] ) ? $meta['filesize'] : null,
                'width'     => isset( $meta['width'] )    ? $meta['width']    : null,
                'height'    => isset( $meta['height'] )   ? $meta['height']   : null,
            );
        }
        return $this->tool_result( $id, array( 'items' => $result, 'count' => count( $result ) ) );
    }

    private function tool_get_media( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_users_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_users_enabled' );
        }
        if ( empty( $args['id'] ) ) {
            return $this->rpc_error( $id, -32602, 'id is required' );
        }
        $att = get_post( absint( $args['id'] ) );
        if ( ! $att || $att->post_type !== 'attachment' ) {
            return $this->rpc_error( $id, -32602, 'Attachment not found' );
        }
        $meta  = wp_get_attachment_metadata( $att->ID );
        $sizes = array();
        if ( ! empty( $meta['sizes'] ) ) {
            $upload_dir = wp_upload_dir();
            $base_url   = $upload_dir['baseurl'] . '/' . dirname( $meta['file'] ) . '/';
            foreach ( $meta['sizes'] as $size_name => $size_data ) {
                $sizes[ $size_name ] = array(
                    'url'    => $base_url . $size_data['file'],
                    'width'  => $size_data['width'],
                    'height' => $size_data['height'],
                );
            }
        }
        return $this->tool_result( $id, array(
            'id'          => $att->ID,
            'title'       => $att->post_title,
            'caption'     => $att->post_excerpt,
            'description' => $att->post_content,
            'filename'    => basename( get_attached_file( $att->ID ) ),
            'url'         => wp_get_attachment_url( $att->ID ),
            'mime_type'   => $att->post_mime_type,
            'alt'         => get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
            'date'        => $att->post_date,
            'uploaded_to' => (int) $att->post_parent,
            'filesize'    => isset( $meta['filesize'] ) ? $meta['filesize'] : null,
            'width'       => isset( $meta['width'] )    ? $meta['width']    : null,
            'height'      => isset( $meta['height'] )   ? $meta['height']   : null,
            'sizes'       => $sizes,
        ) );
    }

    private function tool_regenerate_thumbnails( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_users_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_users_enabled' );
        }
        $limit     = min( absint( isset( $args['limit'] ) ? $args['limit'] : 20 ), 100 );
        $post__in  = ! empty( $args['ids'] ) && is_array( $args['ids'] ) ? array_map( 'absint', $args['ids'] ) : array();
        $query_args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
        );
        if ( $post__in ) $query_args['post__in'] = $post__in;
        $ids       = get_posts( $query_args );
        $succeeded = 0;
        $failed    = 0;
        foreach ( $ids as $attachment_id ) {
            $file = get_attached_file( $attachment_id );
            if ( $file && file_exists( $file ) ) {
                $metadata = wp_generate_attachment_metadata( $attachment_id, $file );
                if ( ! is_wp_error( $metadata ) && $metadata ) {
                    wp_update_attachment_metadata( $attachment_id, $metadata );
                    $succeeded++;
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
        }
        return $this->tool_result( $id, array(
            'success'   => true,
            'processed' => count( $ids ),
            'succeeded' => $succeeded,
            'failed'    => $failed,
        ) );
    }
}
