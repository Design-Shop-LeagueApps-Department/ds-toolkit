<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MCP tools: posts, pages and custom post type entries (Posts & Pages, Custom Post Types).
 *
 * One part of DS_MCP_Server, which was a single 2,800-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the server (same visibility, same
 * $this). Which switch turns a tool on, which capability it needs and whether it is logged is in
 * DS_MCP_Tools; this file is only what each tool does.
 */
trait DS_MCP_Posts_Tools {

    // -------------------------------------------------------------------------
    // Posts & Pages / CPT Tools
    // -------------------------------------------------------------------------

    private function tool_list_posts( $id, $args ) {
        $post_type = ! empty( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
        if ( ! $this->is_group_enabled( $this->group_for_post_type( $post_type ) ) ) {
            return $this->group_disabled_error( $id, $this->group_for_post_type( $post_type ) );
        }
        $query_args = array(
            'post_type'      => $post_type,
            'posts_per_page' => ! empty( $args['per_page'] ) ? min( (int) $args['per_page'], 100 ) : 20,
            'post_status'    => ! empty( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any',
            'perm'           => 'readable', // drafts and private posts only for users who may read them
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        if ( ! empty( $args['search'] ) )      $query_args['s']           = sanitize_text_field( $args['search'] );
        if ( isset( $args['post_parent'] ) )   $query_args['post_parent'] = absint( $args['post_parent'] );
        if ( ! empty( $args['orderby'] ) )     $query_args['orderby']     = sanitize_key( $args['orderby'] );
        if ( ! empty( $args['order'] ) )       $query_args['order']       = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $query = new WP_Query( $query_args );
        $posts = array();
        foreach ( $query->posts as $post ) {
            $posts[] = array(
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'slug'        => $post->post_name,
                'status'      => $post->post_status,
                'type'        => $post->post_type,
                'post_parent'  => $post->post_parent,
                'menu_order'   => $post->menu_order,
                'thumbnail_id' => (int) get_post_thumbnail_id( $post->ID ) ?: null,
                'url'          => get_permalink( $post->ID ),
                'date'         => $post->post_date,
                'modified'     => $post->post_modified,
            );
        }
        return $this->tool_result( $id, array( 'total' => $query->found_posts, 'posts' => $posts ) );
    }

    private function tool_get_post( $id, $args ) {
        if ( empty( $args['id'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: id' );
        }
        $post = get_post( (int) $args['id'] );
        if ( ! $post ) {
            return $this->rpc_error( $id, -32602, 'Post not found: ID ' . (int) $args['id'] );
        }
        if ( ! $this->is_group_enabled( $this->group_for_post_type( $post->post_type ) ) ) {
            return $this->group_disabled_error( $id, $this->group_for_post_type( $post->post_type ) );
        }
        if ( ! current_user_can( 'read_post', $post->ID ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions to read post ' . $post->ID );
        }
        $taxonomies  = get_object_taxonomies( $post->post_type );
        $terms_data  = array();
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'all' ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $terms_data[ $taxonomy ] = array_map( function( $t ) {
                    return array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug );
                }, $terms );
            }
        }
        return $this->tool_result( $id, array(
            'id'             => $post->ID,
            'title'          => $post->post_title,
            'slug'           => $post->post_name,
            'content'        => $post->post_content,
            'excerpt'        => $post->post_excerpt,
            'status'         => $post->post_status,
            'type'           => $post->post_type,
            'post_parent'    => $post->post_parent,
            'menu_order'     => $post->menu_order,
            'page_template'  => get_page_template_slug( $post->ID ),
            'comment_status' => $post->comment_status,
            'thumbnail_id'   => (int) get_post_thumbnail_id( $post->ID ) ?: null,
            'author_id'      => (int) $post->post_author,
            'author'         => get_the_author_meta( 'display_name', $post->post_author ),
            'url'            => get_permalink( $post->ID ),
            'date'           => $post->post_date,
            'modified'       => $post->post_modified,
            'terms'          => $terms_data,
        ) );
    }

    private function tool_create_post( $id, $args ) {
        $post_type = ! empty( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
        if ( ! $this->is_group_enabled( $this->group_for_post_type( $post_type ) ) ) {
            return $this->group_disabled_error( $id, $this->group_for_post_type( $post_type ) );
        }
        if ( ! current_user_can( 'publish_posts' ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions — publish_posts required' );
        }
        $rule = $this->post_rule_error( $post_type, sanitize_key( isset( $args['status'] ) ? $args['status'] : 'draft' ), ! empty( $args['post_author'] ) ? absint( $args['post_author'] ) : null );
        if ( '' !== $rule ) {
            return $this->rpc_error( $id, -32603, $rule );
        }
        $post_data = array(
            'post_title'   => sanitize_text_field( isset( $args['title'] ) ? $args['title'] : 'Untitled' ),
            'post_content' => wp_kses_post( isset( $args['content'] ) ? $args['content'] : '' ),
            'post_status'  => sanitize_key( isset( $args['status'] ) ? $args['status'] : 'draft' ),
            'post_type'    => $post_type,
        );
        if ( ! empty( $args['excerpt'] ) )       $post_data['post_excerpt']   = sanitize_textarea_field( $args['excerpt'] );
        if ( isset( $args['post_parent'] ) )     $post_data['post_parent']    = absint( $args['post_parent'] );
        if ( ! empty( $args['slug'] ) )          $post_data['post_name']      = sanitize_title( $args['slug'] );
        if ( isset( $args['menu_order'] ) )      $post_data['menu_order']     = (int) $args['menu_order'];
        if ( ! empty( $args['page_template'] ) ) $post_data['page_template']  = sanitize_text_field( $args['page_template'] );
        if ( ! empty( $args['post_author'] ) )   $post_data['post_author']    = absint( $args['post_author'] );
        if ( isset( $args['comment_status'] ) )  $post_data['comment_status'] = in_array( $args['comment_status'], array( 'open', 'closed' ), true ) ? $args['comment_status'] : 'closed';
        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return $this->rpc_error( $id, -32603, $post_id->get_error_message() );
        }
        if ( isset( $args['thumbnail_id'] ) ) {
            $thumb_id = absint( $args['thumbnail_id'] );
            $thumb_id ? set_post_thumbnail( $post_id, $thumb_id ) : delete_post_thumbnail( $post_id );
        }
        $terms_assigned = array();
        if ( ! empty( $args['terms'] ) && is_array( $args['terms'] ) ) {
            foreach ( $args['terms'] as $taxonomy => $terms ) {
                $result = wp_set_object_terms( $post_id, $terms, sanitize_key( $taxonomy ) );
                if ( ! is_wp_error( $result ) ) {
                    $terms_assigned[] = $taxonomy;
                }
            }
        }
        return $this->tool_result( $id, array( 'id' => $post_id, 'url' => get_permalink( $post_id ), 'created' => true, 'terms_assigned' => $terms_assigned ) );
    }

    private function tool_update_post( $id, $args ) {
        if ( empty( $args['id'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: id' );
        }
        $post = get_post( (int) $args['id'] );
        if ( ! $post ) {
            return $this->rpc_error( $id, -32602, 'Post not found: ID ' . (int) $args['id'] );
        }
        if ( ! $this->is_group_enabled( $this->group_for_post_type( $post->post_type ) ) ) {
            return $this->group_disabled_error( $id, $this->group_for_post_type( $post->post_type ) );
        }
        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions to edit post ' . $post->ID );
        }
        $rule = $this->post_rule_error( $post->post_type, isset( $args['status'] ) ? sanitize_key( $args['status'] ) : null, isset( $args['post_author'] ) ? absint( $args['post_author'] ) : null, $post );
        if ( '' !== $rule ) {
            return $this->rpc_error( $id, -32603, $rule );
        }
        $post_data = array( 'ID' => $post->ID );
        if ( isset( $args['title'] ) )         $post_data['post_title']   = sanitize_text_field( $args['title'] );
        if ( isset( $args['content'] ) )       $post_data['post_content'] = wp_kses_post( $args['content'] );
        if ( isset( $args['excerpt'] ) )       $post_data['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
        if ( isset( $args['status'] ) )        $post_data['post_status']  = sanitize_key( $args['status'] );
        if ( isset( $args['post_parent'] ) )   $post_data['post_parent']    = absint( $args['post_parent'] );
        if ( isset( $args['slug'] ) )          $post_data['post_name']      = sanitize_title( $args['slug'] );
        if ( isset( $args['menu_order'] ) )    $post_data['menu_order']     = (int) $args['menu_order'];
        if ( isset( $args['page_template'] ) ) $post_data['page_template']  = sanitize_text_field( $args['page_template'] );
        if ( isset( $args['post_author'] ) )   $post_data['post_author']    = absint( $args['post_author'] );
        if ( isset( $args['comment_status'] ) ) $post_data['comment_status'] = in_array( $args['comment_status'], array( 'open', 'closed' ), true ) ? $args['comment_status'] : 'closed';
        $result = wp_update_post( $post_data, true );
        if ( is_wp_error( $result ) ) {
            return $this->rpc_error( $id, -32603, $result->get_error_message() );
        }
        if ( isset( $args['thumbnail_id'] ) ) {
            $thumb_id = absint( $args['thumbnail_id'] );
            $thumb_id ? set_post_thumbnail( $post->ID, $thumb_id ) : delete_post_thumbnail( $post->ID );
        }
        $terms_assigned = array();
        if ( ! empty( $args['terms'] ) && is_array( $args['terms'] ) ) {
            foreach ( $args['terms'] as $taxonomy => $terms ) {
                $r = wp_set_object_terms( $post->ID, $terms, sanitize_key( $taxonomy ) );
                if ( ! is_wp_error( $r ) ) {
                    $terms_assigned[] = $taxonomy;
                }
            }
        }
        return $this->tool_result( $id, array( 'id' => $post->ID, 'updated' => true, 'url' => get_permalink( $post->ID ), 'terms_assigned' => $terms_assigned ) );
    }

    private function tool_delete_post( $id, $args ) {
        if ( empty( $args['id'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: id' );
        }
        $post = get_post( (int) $args['id'] );
        if ( ! $post ) {
            return $this->rpc_error( $id, -32602, 'Post not found: ID ' . (int) $args['id'] );
        }
        if ( ! $this->is_group_enabled( $this->group_for_post_type( $post->post_type ) ) ) {
            return $this->group_disabled_error( $id, $this->group_for_post_type( $post->post_type ) );
        }
        if ( ! current_user_can( 'delete_post', $post->ID ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions to delete post ' . $post->ID );
        }
        $force  = ! empty( $args['force'] );
        $result = wp_delete_post( $post->ID, $force );
        if ( ! $result ) {
            return $this->rpc_error( $id, -32603, 'Failed to delete post ' . $post->ID );
        }
        return $this->tool_result( $id, array( 'id' => $post->ID, 'deleted' => true, 'trashed' => ! $force ) );
    }

    private function tool_bulk_create_posts( $id, $args ) {
        if ( empty( $args['posts'] ) || ! is_array( $args['posts'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: posts (array)' );
        }
        if ( ! current_user_can( 'publish_posts' ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions — publish_posts required' );
        }
        $results = array();
        foreach ( $args['posts'] as $index => $item ) {
            $post_type = ! empty( $item['post_type'] ) ? sanitize_key( $item['post_type'] ) : 'post';
            if ( ! $this->is_group_enabled( $this->group_for_post_type( $post_type ) ) ) {
                $results[] = array( 'index' => $index, 'error' => 'Group disabled for post type: ' . $post_type );
                continue;
            }
            $rule = $this->post_rule_error( $post_type, sanitize_key( isset( $item['status'] ) ? $item['status'] : 'draft' ), ! empty( $item['post_author'] ) ? absint( $item['post_author'] ) : null );
            if ( '' !== $rule ) {
                $results[] = array( 'index' => $index, 'error' => $rule );
                continue;
            }
            $post_data = array(
                'post_title'   => sanitize_text_field( isset( $item['title'] ) ? $item['title'] : 'Untitled' ),
                'post_content' => wp_kses_post( isset( $item['content'] ) ? $item['content'] : '' ),
                'post_status'  => sanitize_key( isset( $item['status'] ) ? $item['status'] : 'draft' ),
                'post_type'    => $post_type,
            );
            if ( ! empty( $item['excerpt'] ) )       $post_data['post_excerpt']   = sanitize_textarea_field( $item['excerpt'] );
            if ( isset( $item['post_parent'] ) )     $post_data['post_parent']    = absint( $item['post_parent'] );
            if ( ! empty( $item['slug'] ) )          $post_data['post_name']      = sanitize_title( $item['slug'] );
            if ( isset( $item['menu_order'] ) )      $post_data['menu_order']     = (int) $item['menu_order'];
            if ( ! empty( $item['page_template'] ) ) $post_data['page_template']  = sanitize_text_field( $item['page_template'] );
            if ( ! empty( $item['post_author'] ) )   $post_data['post_author']    = absint( $item['post_author'] );
            if ( isset( $item['comment_status'] ) )  $post_data['comment_status'] = in_array( $item['comment_status'], array( 'open', 'closed' ), true ) ? $item['comment_status'] : 'closed';
            $post_id = wp_insert_post( $post_data, true );
            if ( is_wp_error( $post_id ) ) {
                $results[] = array( 'index' => $index, 'title' => $post_data['post_title'], 'error' => $post_id->get_error_message() );
                continue;
            }
            if ( isset( $item['thumbnail_id'] ) ) {
                $thumb_id = absint( $item['thumbnail_id'] );
                $thumb_id ? set_post_thumbnail( $post_id, $thumb_id ) : delete_post_thumbnail( $post_id );
            }
            if ( ! empty( $item['terms'] ) && is_array( $item['terms'] ) ) {
                foreach ( $item['terms'] as $taxonomy => $terms ) {
                    wp_set_object_terms( $post_id, $terms, sanitize_key( $taxonomy ) );
                }
            }
            $results[] = array( 'index' => $index, 'id' => $post_id, 'title' => $post_data['post_title'], 'url' => get_permalink( $post_id ), 'created' => true );
        }
        $created = count( array_filter( $results, function( $r ) { return ! empty( $r['created'] ); } ) );
        return $this->tool_result( $id, array( 'total' => count( $args['posts'] ), 'created' => $created, 'results' => $results ) );
    }

    private function tool_bulk_update_posts( $id, $args ) {
        if ( empty( $args['posts'] ) || ! is_array( $args['posts'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: posts (array)' );
        }
        $results = array();
        foreach ( $args['posts'] as $index => $item ) {
            if ( empty( $item['id'] ) ) {
                $results[] = array( 'index' => $index, 'error' => 'Missing id' );
                continue;
            }
            $post = get_post( (int) $item['id'] );
            if ( ! $post ) {
                $results[] = array( 'index' => $index, 'id' => (int) $item['id'], 'error' => 'Post not found' );
                continue;
            }
            if ( ! $this->is_group_enabled( $this->group_for_post_type( $post->post_type ) ) ) {
                $results[] = array( 'index' => $index, 'id' => $post->ID, 'error' => 'Group disabled for post type: ' . $post->post_type );
                continue;
            }
            if ( ! current_user_can( 'edit_post', $post->ID ) ) {
                $results[] = array( 'index' => $index, 'id' => $post->ID, 'error' => 'Insufficient permissions' );
                continue;
            }
            $rule = $this->post_rule_error( $post->post_type, isset( $item['status'] ) ? sanitize_key( $item['status'] ) : null, isset( $item['post_author'] ) ? absint( $item['post_author'] ) : null, $post );
            if ( '' !== $rule ) {
                $results[] = array( 'index' => $index, 'id' => $post->ID, 'error' => $rule );
                continue;
            }
            $post_data = array( 'ID' => $post->ID );
            if ( isset( $item['title'] ) )          $post_data['post_title']     = sanitize_text_field( $item['title'] );
            if ( isset( $item['content'] ) )        $post_data['post_content']   = wp_kses_post( $item['content'] );
            if ( isset( $item['excerpt'] ) )        $post_data['post_excerpt']   = sanitize_textarea_field( $item['excerpt'] );
            if ( isset( $item['status'] ) )         $post_data['post_status']    = sanitize_key( $item['status'] );
            if ( isset( $item['post_parent'] ) )    $post_data['post_parent']    = absint( $item['post_parent'] );
            if ( isset( $item['slug'] ) )           $post_data['post_name']      = sanitize_title( $item['slug'] );
            if ( isset( $item['menu_order'] ) )     $post_data['menu_order']     = (int) $item['menu_order'];
            if ( isset( $item['page_template'] ) )  $post_data['page_template']  = sanitize_text_field( $item['page_template'] );
            if ( isset( $item['post_author'] ) )    $post_data['post_author']    = absint( $item['post_author'] );
            if ( isset( $item['comment_status'] ) ) $post_data['comment_status'] = in_array( $item['comment_status'], array( 'open', 'closed' ), true ) ? $item['comment_status'] : 'closed';
            $result = wp_update_post( $post_data, true );
            if ( is_wp_error( $result ) ) {
                $results[] = array( 'index' => $index, 'id' => $post->ID, 'error' => $result->get_error_message() );
                continue;
            }
            if ( isset( $item['thumbnail_id'] ) ) {
                $thumb_id = absint( $item['thumbnail_id'] );
                $thumb_id ? set_post_thumbnail( $post->ID, $thumb_id ) : delete_post_thumbnail( $post->ID );
            }
            if ( ! empty( $item['terms'] ) && is_array( $item['terms'] ) ) {
                foreach ( $item['terms'] as $taxonomy => $terms ) {
                    wp_set_object_terms( $post->ID, $terms, sanitize_key( $taxonomy ) );
                }
            }
            $results[] = array( 'index' => $index, 'id' => $post->ID, 'updated' => true, 'url' => get_permalink( $post->ID ) );
        }
        $updated = count( array_filter( $results, function( $r ) { return ! empty( $r['updated'] ); } ) );
        return $this->tool_result( $id, array( 'total' => count( $args['posts'] ), 'updated' => $updated, 'results' => $results ) );
    }

    // -------------------------------------------------------------------------
    // CPT Discovery Tools
    // -------------------------------------------------------------------------

    private function tool_list_post_types( $id ) {
        if ( ! $this->is_group_enabled( 'mcp_cpt_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_cpt_enabled' );
        }
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        $result = array();
        foreach ( $post_types as $pt ) {
            $result[] = array(
                'slug'         => $pt->name,
                'label'        => $pt->label,
                'singular'     => $pt->labels->singular_name,
                'hierarchical' => $pt->hierarchical,
                'supports'     => get_all_post_type_supports( $pt->name ),
                'taxonomies'   => get_object_taxonomies( $pt->name ),
            );
        }
        return $this->tool_result( $id, $result );
    }
}
