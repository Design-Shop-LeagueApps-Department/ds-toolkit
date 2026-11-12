<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MCP tools: taxonomies and terms.
 *
 * One part of DS_MCP_Server, which was a single 2,800-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the server (same visibility, same
 * $this). Which switch turns a tool on, which capability it needs and whether it is logged is in
 * DS_MCP_Tools; this file is only what each tool does.
 */
trait DS_MCP_Taxonomy_Tools {

    // -------------------------------------------------------------------------
    // Taxonomy Tools
    // -------------------------------------------------------------------------

    private function tool_list_taxonomies( $id ) {
        if ( ! $this->is_group_enabled( 'mcp_taxonomies_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_taxonomies_enabled' );
        }
        $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
        $result = array();
        foreach ( $taxonomies as $tax ) {
            $result[] = array(
                'slug'        => $tax->name,
                'label'       => $tax->label,
                'singular'    => $tax->labels->singular_name,
                'hierarchical'=> $tax->hierarchical,
                'post_types'  => $tax->object_type,
            );
        }
        return $this->tool_result( $id, $result );
    }

    private function tool_list_terms( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_taxonomies_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_taxonomies_enabled' );
        }
        if ( empty( $args['taxonomy'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: taxonomy' );
        }
        $terms = get_terms( array(
            'taxonomy'   => sanitize_key( $args['taxonomy'] ),
            'hide_empty' => isset( $args['hide_empty'] ) ? (bool) $args['hide_empty'] : false,
            'number'     => ! empty( $args['per_page'] ) ? min( (int) $args['per_page'], 200 ) : 100,
            'search'     => ! empty( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
        ) );
        if ( is_wp_error( $terms ) ) {
            return $this->rpc_error( $id, -32602, $terms->get_error_message() );
        }
        $result = array();
        foreach ( $terms as $term ) {
            $result[] = array(
                'id'          => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'count'       => $term->count,
                'parent'      => $term->parent,
            );
        }
        return $this->tool_result( $id, array( 'taxonomy' => $args['taxonomy'], 'terms' => $result ) );
    }

    private function tool_get_term( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_taxonomies_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_taxonomies_enabled' );
        }
        if ( empty( $args['taxonomy'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: taxonomy' );
        }
        $by   = ! empty( $args['slug'] ) ? 'slug' : 'id';
        $val  = ! empty( $args['slug'] ) ? sanitize_text_field( $args['slug'] ) : (int) $args['id'];
        $term = get_term_by( $by, $val, sanitize_key( $args['taxonomy'] ) );
        if ( ! $term ) {
            return $this->rpc_error( $id, -32602, 'Term not found' );
        }
        return $this->tool_result( $id, array(
            'id'          => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'count'       => $term->count,
            'parent'      => $term->parent,
            'taxonomy'    => $term->taxonomy,
        ) );
    }

    private function tool_create_term( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_taxonomies_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_taxonomies_enabled' );
        }
        if ( empty( $args['taxonomy'] ) || empty( $args['name'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required arguments: taxonomy, name' );
        }
        if ( ! current_user_can( 'manage_categories' ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions — manage_categories required' );
        }
        $term_args = array();
        if ( ! empty( $args['slug'] ) )        $term_args['slug']        = sanitize_title( $args['slug'] );
        if ( ! empty( $args['description'] ) ) $term_args['description'] = sanitize_textarea_field( $args['description'] );
        if ( ! empty( $args['parent'] ) )      $term_args['parent']      = (int) $args['parent'];
        $result = wp_insert_term( sanitize_text_field( $args['name'] ), sanitize_key( $args['taxonomy'] ), $term_args );
        if ( is_wp_error( $result ) ) {
            return $this->rpc_error( $id, -32603, $result->get_error_message() );
        }
        return $this->tool_result( $id, array( 'id' => $result['term_id'], 'created' => true ) );
    }

    private function tool_update_term( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_taxonomies_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_taxonomies_enabled' );
        }
        if ( empty( $args['id'] ) || empty( $args['taxonomy'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required arguments: id, taxonomy' );
        }
        if ( ! current_user_can( 'manage_categories' ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions — manage_categories required' );
        }
        $term_args = array();
        if ( isset( $args['name'] ) )        $term_args['name']        = sanitize_text_field( $args['name'] );
        if ( isset( $args['slug'] ) )        $term_args['slug']        = sanitize_title( $args['slug'] );
        if ( isset( $args['description'] ) ) $term_args['description'] = sanitize_textarea_field( $args['description'] );
        $result = wp_update_term( (int) $args['id'], sanitize_key( $args['taxonomy'] ), $term_args );
        if ( is_wp_error( $result ) ) {
            return $this->rpc_error( $id, -32603, $result->get_error_message() );
        }
        return $this->tool_result( $id, array( 'id' => (int) $args['id'], 'updated' => true ) );
    }

    private function tool_delete_term( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_taxonomies_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_taxonomies_enabled' );
        }
        if ( empty( $args['id'] ) || empty( $args['taxonomy'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required arguments: id, taxonomy' );
        }
        if ( ! current_user_can( 'manage_categories' ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions — manage_categories required' );
        }
        $result = wp_delete_term( (int) $args['id'], sanitize_key( $args['taxonomy'] ) );
        if ( is_wp_error( $result ) || $result === false ) {
            return $this->rpc_error( $id, -32603, 'Failed to delete term' );
        }
        return $this->tool_result( $id, array( 'id' => (int) $args['id'], 'deleted' => true ) );
    }

    private function tool_set_post_terms( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_taxonomies_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_taxonomies_enabled' );
        }
        if ( empty( $args['id'] ) || empty( $args['taxonomy'] ) || ! isset( $args['terms'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required arguments: id, taxonomy, terms' );
        }
        $post = get_post( (int) $args['id'] );
        if ( ! $post ) {
            return $this->rpc_error( $id, -32602, 'Post not found: ID ' . (int) $args['id'] );
        }
        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions to edit post ' . $post->ID );
        }
        $taxonomy = sanitize_key( $args['taxonomy'] );
        $terms    = is_array( $args['terms'] ) ? $args['terms'] : array( $args['terms'] );
        $append   = ! empty( $args['append'] );
        $result   = wp_set_object_terms( $post->ID, $terms, $taxonomy, $append );
        if ( is_wp_error( $result ) ) {
            return $this->rpc_error( $id, -32603, $result->get_error_message() );
        }
        return $this->tool_result( $id, array(
            'post_id'  => $post->ID,
            'taxonomy' => $taxonomy,
            'term_ids' => $result,
            'append'   => $append,
            'updated'  => true,
        ) );
    }
}
