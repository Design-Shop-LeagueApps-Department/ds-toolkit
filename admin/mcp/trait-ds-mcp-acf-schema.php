<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MCP tools: ACF post types, taxonomies, field groups and options pages (staff only).
 *
 * One part of DS_MCP_Server, which was a single 2,800-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the server (same visibility, same
 * $this). Which switch turns a tool on, which capability it needs and whether it is logged is in
 * DS_MCP_Tools; this file is only what each tool does.
 */
trait DS_MCP_ACF_Schema_Tools {

    // -------------------------------------------------------------------------
    // ACF Schema Tools — Post Types
    // -------------------------------------------------------------------------

    private function acf_schema_gate( $id, $group_key ) {
        $err = $this->leagueapps_gate( $id, $group_key );
        if ( $err ) return $err;
        if ( ! function_exists( 'acf_get_acf_post_types' ) ) {
            return $this->rpc_error( $id, -32603, 'ACF Pro 6.1+ is required for this tool' );
        }
        return null;
    }

    private function tool_acf_list_post_types( $id ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        $post_types = acf_get_acf_post_types();
        $result     = array();
        foreach ( $post_types as $pt ) {
            $result[] = array(
                'key'            => $pt['key'],
                'post_type'      => $pt['post_type'],
                'label'          => $pt['label'],
                'singular_label' => $pt['singular_label'],
                'description'    => $pt['description'],
                'public'         => $pt['public'],
                'hierarchical'   => $pt['hierarchical'],
                'supports'       => $pt['supports'],
                'taxonomies'     => $pt['taxonomies'],
            );
        }
        return $this->tool_result( $id, array( 'post_types' => $result ) );
    }

    private function tool_acf_create_post_type( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['post_type'] ) || empty( $args['label'] ) || empty( $args['singular_label'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required arguments: post_type, label, singular_label' );
        }
        $data = array(
            'post_type'      => sanitize_key( $args['post_type'] ),
            'label'          => sanitize_text_field( $args['label'] ),
            'singular_label' => sanitize_text_field( $args['singular_label'] ),
            'description'    => isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : '',
            'public'         => isset( $args['public'] ) ? (bool) $args['public'] : true,
            'hierarchical'   => isset( $args['hierarchical'] ) ? (bool) $args['hierarchical'] : false,
            'supports'       => isset( $args['supports'] ) && is_array( $args['supports'] ) ? $args['supports'] : array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
            'taxonomies'     => isset( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ? $args['taxonomies'] : array(),
        );
        $result = acf_update_post_type( $data );
        if ( empty( $result['key'] ) ) {
            return $this->rpc_error( $id, -32603, 'Failed to create post type. The post_type slug may already be in use.' );
        }
        return $this->tool_result( $id, array( 'key' => $result['key'], 'post_type' => $result['post_type'], 'created' => true ) );
    }

    private function tool_acf_update_post_type( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: key (use acf_list_post_types to get it)' );
        }
        $existing = acf_get_post_type( sanitize_text_field( $args['key'] ) );
        if ( empty( $existing ) ) {
            return $this->rpc_error( $id, -32602, 'Post type not found for key: ' . $args['key'] );
        }
        $allowed = array( 'label', 'singular_label', 'description', 'public', 'hierarchical', 'supports', 'taxonomies' );
        foreach ( $allowed as $field ) {
            if ( isset( $args[ $field ] ) ) {
                $existing[ $field ] = $args[ $field ];
            }
        }
        $result = acf_update_post_type( $existing );
        return $this->tool_result( $id, array( 'key' => $result['key'], 'post_type' => $result['post_type'], 'updated' => true ) );
    }

    private function tool_acf_delete_post_type( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: key (use acf_list_post_types to get it)' );
        }
        $existing = acf_get_post_type( sanitize_text_field( $args['key'] ) );
        if ( empty( $existing ) ) {
            return $this->rpc_error( $id, -32602, 'Post type not found for key: ' . $args['key'] );
        }
        $post = acf_get_post_type_post( sanitize_text_field( $args['key'] ) );
        if ( empty( $post ) ) {
            return $this->rpc_error( $id, -32603, 'Could not locate the internal post for this ACF post type.' );
        }
        $deleted = acf_delete_post_type( $post->ID );
        return $this->tool_result( $id, array( 'key' => $args['key'], 'deleted' => (bool) $deleted ) );
    }

    // -------------------------------------------------------------------------
    // ACF Schema Tools — Taxonomies
    // -------------------------------------------------------------------------

    private function tool_acf_list_taxonomies( $id ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        $taxonomies = acf_get_acf_taxonomies();
        $result     = array();
        foreach ( $taxonomies as $tax ) {
            $result[] = array(
                'key'            => $tax['key'],
                'taxonomy'       => $tax['taxonomy'],
                'label'          => $tax['label'],
                'singular_label' => $tax['singular_label'],
                'description'    => $tax['description'],
                'public'         => $tax['public'],
                'hierarchical'   => $tax['hierarchical'],
                'object_type'    => $tax['object_type'],
            );
        }
        return $this->tool_result( $id, array( 'taxonomies' => $result ) );
    }

    private function tool_acf_create_taxonomy( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['taxonomy'] ) || empty( $args['label'] ) || empty( $args['singular_label'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required arguments: taxonomy, label, singular_label' );
        }
        $data = array(
            'taxonomy'       => sanitize_key( $args['taxonomy'] ),
            'label'          => sanitize_text_field( $args['label'] ),
            'singular_label' => sanitize_text_field( $args['singular_label'] ),
            'description'    => isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : '',
            'public'         => isset( $args['public'] ) ? (bool) $args['public'] : true,
            'hierarchical'   => isset( $args['hierarchical'] ) ? (bool) $args['hierarchical'] : false,
            'object_type'    => isset( $args['object_type'] ) && is_array( $args['object_type'] ) ? $args['object_type'] : array(),
        );
        $result = acf_update_taxonomy( $data );
        if ( empty( $result['key'] ) ) {
            return $this->rpc_error( $id, -32603, 'Failed to create taxonomy. The taxonomy slug may already be in use.' );
        }
        return $this->tool_result( $id, array( 'key' => $result['key'], 'taxonomy' => $result['taxonomy'], 'created' => true ) );
    }

    private function tool_acf_update_taxonomy( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: key (use acf_list_taxonomies to get it)' );
        }
        $existing = acf_get_taxonomy( sanitize_text_field( $args['key'] ) );
        if ( empty( $existing ) ) {
            return $this->rpc_error( $id, -32602, 'Taxonomy not found for key: ' . $args['key'] );
        }
        $allowed = array( 'label', 'singular_label', 'description', 'public', 'hierarchical', 'object_type' );
        foreach ( $allowed as $field ) {
            if ( isset( $args[ $field ] ) ) {
                $existing[ $field ] = $args[ $field ];
            }
        }
        $result = acf_update_taxonomy( $existing );
        return $this->tool_result( $id, array( 'key' => $result['key'], 'taxonomy' => $result['taxonomy'], 'updated' => true ) );
    }

    private function tool_acf_delete_taxonomy( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: key (use acf_list_taxonomies to get it)' );
        }
        $existing = acf_get_taxonomy( sanitize_text_field( $args['key'] ) );
        if ( empty( $existing ) ) {
            return $this->rpc_error( $id, -32602, 'Taxonomy not found for key: ' . $args['key'] );
        }
        $post = acf_get_taxonomy_post( sanitize_text_field( $args['key'] ) );
        if ( empty( $post ) ) {
            return $this->rpc_error( $id, -32603, 'Could not locate the internal post for this ACF taxonomy.' );
        }
        $deleted = acf_delete_taxonomy( $post->ID );
        return $this->tool_result( $id, array( 'key' => $args['key'], 'deleted' => (bool) $deleted ) );
    }

    // -------------------------------------------------------------------------
    // ACF Schema Tools — Field Groups
    // -------------------------------------------------------------------------

    private function tool_acf_list_field_groups( $id ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        $groups = acf_get_field_groups();
        $result = array();
        foreach ( $groups as $group ) {
            $result[] = array(
                'key'      => $group['key'],
                'title'    => $group['title'],
                'active'   => $group['active'],
                'location' => $group['location'],
                'position' => $group['position'],
            );
        }
        return $this->tool_result( $id, array( 'field_groups' => $result ) );
    }

    private function tool_acf_get_field_group( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: key' );
        }
        $group = acf_get_field_group( sanitize_text_field( $args['key'] ) );
        if ( empty( $group ) ) {
            return $this->rpc_error( $id, -32602, 'Field group not found for key: ' . $args['key'] );
        }
        $fields = acf_get_fields( $group );
        $fields = $fields ?: array();
        $simplified_fields = array();
        foreach ( $fields as $field ) {
            $simplified_fields[] = array(
                'key'      => $field['key'],
                'label'    => $field['label'],
                'name'     => $field['name'],
                'type'     => $field['type'],
                'required' => $field['required'],
            );
        }
        return $this->tool_result( $id, array(
            'key'      => $group['key'],
            'title'    => $group['title'],
            'active'   => $group['active'],
            'location' => $group['location'],
            'position' => $group['position'],
            'fields'   => $simplified_fields,
        ) );
    }

    private function tool_acf_create_field_group( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['title'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: title' );
        }
        // Default location: show on all posts if not provided
        $location = isset( $args['location'] ) && is_array( $args['location'] )
            ? $args['location']
            : array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) );

        $group = array(
            'title'           => sanitize_text_field( $args['title'] ),
            'location'        => $location,
            'position'        => isset( $args['position'] ) ? sanitize_key( $args['position'] ) : 'normal',
            'label_placement' => isset( $args['label_placement'] ) ? sanitize_key( $args['label_placement'] ) : 'top',
            'active'          => isset( $args['active'] ) ? (bool) $args['active'] : true,
        );
        $result = acf_update_field_group( $group );
        if ( empty( $result['key'] ) ) {
            return $this->rpc_error( $id, -32603, 'Failed to create field group.' );
        }
        // Add fields if provided
        $fields_created = array();
        if ( ! empty( $args['fields'] ) && is_array( $args['fields'] ) ) {
            foreach ( $args['fields'] as $field_def ) {
                if ( empty( $field_def['label'] ) || empty( $field_def['name'] ) || empty( $field_def['type'] ) ) {
                    continue;
                }
                $field = array(
                    'label'    => sanitize_text_field( $field_def['label'] ),
                    'name'     => sanitize_key( $field_def['name'] ),
                    'type'     => sanitize_key( $field_def['type'] ),
                    'required' => ! empty( $field_def['required'] ) ? 1 : 0,
                    'parent'   => $result['key'],
                );
                if ( isset( $field_def['instructions'] ) ) {
                    $field['instructions'] = sanitize_text_field( $field_def['instructions'] );
                }
                if ( isset( $field_def['default_value'] ) ) {
                    $field['default_value'] = $field_def['default_value'];
                }
                $saved = acf_update_field( $field );
                if ( ! empty( $saved['key'] ) ) {
                    $fields_created[] = array( 'key' => $saved['key'], 'name' => $saved['name'], 'type' => $saved['type'] );
                }
            }
        }
        return $this->tool_result( $id, array(
            'key'            => $result['key'],
            'title'          => $result['title'],
            'created'        => true,
            'fields_created' => $fields_created,
        ) );
    }

    private function tool_acf_update_field_group( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: key (use acf_list_field_groups to get it)' );
        }
        $existing = acf_get_field_group( sanitize_text_field( $args['key'] ) );
        if ( empty( $existing ) ) {
            return $this->rpc_error( $id, -32602, 'Field group not found for key: ' . $args['key'] );
        }
        $allowed = array( 'title', 'location', 'position', 'label_placement', 'active' );
        foreach ( $allowed as $field ) {
            if ( isset( $args[ $field ] ) ) {
                $existing[ $field ] = $args[ $field ];
            }
        }
        $result = acf_update_field_group( $existing );
        return $this->tool_result( $id, array( 'key' => $result['key'], 'title' => $result['title'], 'updated' => true ) );
    }

    private function tool_acf_delete_field_group( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: key (use acf_list_field_groups to get it)' );
        }
        $group = acf_get_field_group( sanitize_text_field( $args['key'] ) );
        if ( empty( $group ) ) {
            return $this->rpc_error( $id, -32602, 'Field group not found for key: ' . $args['key'] );
        }
        $post = acf_get_field_group_post( sanitize_text_field( $args['key'] ) );
        if ( empty( $post ) ) {
            return $this->rpc_error( $id, -32603, 'Could not locate internal post for this field group.' );
        }
        $deleted = acf_delete_field_group( $post->ID );
        return $this->tool_result( $id, array( 'key' => $args['key'], 'deleted' => (bool) $deleted ) );
    }

    // -------------------------------------------------------------------------
    // ACF Schema Tools — Options Pages
    // -------------------------------------------------------------------------

    private function tool_acf_list_options_pages( $id ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( ! function_exists( 'acf_get_ui_options_pages' ) ) {
            return $this->rpc_error( $id, -32603, 'ACF Pro 6.2+ is required for Options Pages' );
        }
        $pages  = acf_get_ui_options_pages();
        $result = array();
        foreach ( $pages as $page ) {
            $result[] = array(
                'key'         => $page['key'],
                'title'       => $page['title'],
                'menu_slug'   => $page['menu_slug'],
                'menu_title'  => $page['menu_title'],
                'parent_slug' => $page['parent_slug'],
                'capability'  => $page['capability'],
            );
        }
        return $this->tool_result( $id, array( 'options_pages' => $result ) );
    }

    private function tool_acf_create_options_page( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( ! function_exists( 'acf_update_ui_options_page' ) ) {
            return $this->rpc_error( $id, -32603, 'ACF Pro 6.2+ is required for Options Pages' );
        }
        if ( empty( $args['title'] ) || empty( $args['menu_slug'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required arguments: title, menu_slug' );
        }
        $data = array(
            'title'       => sanitize_text_field( $args['title'] ),
            'menu_slug'   => sanitize_key( $args['menu_slug'] ),
            'menu_title'  => isset( $args['menu_title'] ) ? sanitize_text_field( $args['menu_title'] ) : '',
            'parent_slug' => isset( $args['parent_slug'] ) ? sanitize_text_field( $args['parent_slug'] ) : '',
            'capability'  => isset( $args['capability'] ) ? sanitize_key( $args['capability'] ) : 'edit_posts',
            'redirect'    => isset( $args['redirect'] ) ? (bool) $args['redirect'] : false,
            'description' => isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : '',
        );
        $result = acf_update_ui_options_page( $data );
        if ( empty( $result['key'] ) ) {
            return $this->rpc_error( $id, -32603, 'Failed to create options page. The menu_slug may already be in use.' );
        }
        return $this->tool_result( $id, array( 'key' => $result['key'], 'menu_slug' => $result['menu_slug'], 'created' => true ) );
    }

    private function tool_acf_delete_options_page( $id, $args ) {
        $err = $this->acf_schema_gate( $id, 'mcp_acf_schema_enabled' );
        if ( $err ) return $err;

        if ( ! function_exists( 'acf_delete_ui_options_page' ) ) {
            return $this->rpc_error( $id, -32603, 'ACF Pro 6.2+ is required for Options Pages' );
        }
        if ( empty( $args['key'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: key (use acf_list_options_pages to get it)' );
        }
        $page = acf_get_ui_options_page( sanitize_text_field( $args['key'] ) );
        if ( empty( $page ) ) {
            return $this->rpc_error( $id, -32602, 'Options page not found for key: ' . $args['key'] );
        }
        $post = acf_get_ui_options_page_post( sanitize_text_field( $args['key'] ) );
        if ( empty( $post ) ) {
            return $this->rpc_error( $id, -32603, 'Could not locate internal post for this options page.' );
        }
        $deleted = acf_delete_ui_options_page( $post->ID );
        return $this->tool_result( $id, array( 'key' => $args['key'], 'deleted' => (bool) $deleted ) );
    }
}
