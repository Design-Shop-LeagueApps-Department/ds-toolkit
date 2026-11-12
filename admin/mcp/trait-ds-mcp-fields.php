<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MCP tools: ACF field values, Partner Settings and DS Toolkit settings.
 *
 * One part of DS_MCP_Server, which was a single 2,800-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the server (same visibility, same
 * $this). Which switch turns a tool on, which capability it needs and whether it is logged is in
 * DS_MCP_Tools; this file is only what each tool does.
 */
trait DS_MCP_Field_Tools {

    // -------------------------------------------------------------------------
    // ACF / Custom Fields Tools
    // -------------------------------------------------------------------------

    private function tool_get_post_fields( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_acf_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_acf_enabled' );
        }
        if ( empty( $args['id'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: id' );
        }
        $post_id = (int) $args['id'];
        $post    = get_post( $post_id );
        if ( ! $post ) {
            return $this->rpc_error( $id, -32602, 'Post not found: ID ' . $post_id );
        }
        if ( ! current_user_can( 'read_post', $post_id ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions to read post ' . $post_id );
        }
        if ( function_exists( 'get_fields' ) ) {
            $fields = get_fields( $post_id );
            $fields = $fields ?: array();
        } else {
            $meta   = get_post_meta( $post_id );
            $fields = array();
            foreach ( $meta as $key => $values ) {
                if ( substr( $key, 0, 1 ) === '_' ) continue; // skip internal WP meta
                $fields[ $key ] = count( $values ) === 1 ? $values[0] : $values;
            }
        }
        return $this->tool_result( $id, array(
            'post_id' => $post_id,
            'source'  => function_exists( 'get_fields' ) ? 'acf' : 'post_meta',
            'fields'  => $fields,
        ) );
    }

    private function tool_update_post_fields( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_acf_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_acf_enabled' );
        }
        if ( empty( $args['id'] ) || empty( $args['fields'] ) || ! is_array( $args['fields'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required arguments: id, fields (object)' );
        }
        $post_id = (int) $args['id'];
        $post    = get_post( $post_id );
        if ( ! $post ) {
            return $this->rpc_error( $id, -32602, 'Post not found: ID ' . $post_id );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions to edit post ' . $post_id );
        }
        // Underscore keys are WordPress's protected meta: Beaver Builder layouts (_fl_builder_data), Themer
        // locations, page templates. Writing them is writing code that runs on the page, so it takes an
        // administrator who may post unfiltered HTML, as the builder itself requires.
        $admin_html = current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
        foreach ( array_keys( $args['fields'] ) as $key ) {
            if ( ! $admin_html && is_protected_meta( sanitize_key( $key ), 'post' ) ) {
                return $this->rpc_error( $id, -32603, 'Insufficient permissions to write the protected field ' . sanitize_key( $key ) );
            }
        }
        $rule = $this->post_rule_error( $post->post_type, null, null, $post );
        if ( '' !== $rule ) {
            return $this->rpc_error( $id, -32603, $rule );
        }
        $updated = array();
        if ( function_exists( 'update_field' ) ) {
            foreach ( $args['fields'] as $key => $value ) {
                update_field( sanitize_key( $key ), $value, $post_id );
                $updated[] = $key;
            }
        } else {
            foreach ( $args['fields'] as $key => $value ) {
                update_post_meta( $post_id, sanitize_key( $key ), $value );
                $updated[] = $key;
            }
        }
        return $this->tool_result( $id, array(
            'post_id'      => $post_id,
            'updated_keys' => $updated,
            'source'       => function_exists( 'update_field' ) ? 'acf' : 'post_meta',
        ) );
    }

    // -------------------------------------------------------------------------
    // Partner Settings Tools
    // -------------------------------------------------------------------------

    /** All known partner settings fields and their sanitization type. */
    private function partner_fields_map() {
        return array(
            'partner_logo'        => 'image',
            'partner_email'       => 'email',
            'partner_phone'       => 'text',
            'partner_address'     => 'textarea',
            'partner_fb'          => 'url',
            'partner_instagram'   => 'url',
            'partner_x'           => 'url',
            'partner_youtube'     => 'url',
            'partner_linkedin'    => 'url',
            'partner_tiktok'      => 'url',
            'partner_leagueapps'  => 'url',
        );
    }

    private function tool_get_partner_settings( $id ) {
        if ( ! $this->is_group_enabled( 'mcp_acf_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_acf_enabled' );
        }
        if ( ! function_exists( 'get_field' ) ) {
            return $this->rpc_error( $id, -32603, 'ACF is required for partner settings.' );
        }

        $result = array();
        foreach ( $this->partner_fields_map() as $field => $type ) {
            $value = get_field( $field, 'option' );
            if ( $type === 'image' ) {
                // ACF image field returns an array or attachment ID depending on field settings
                if ( is_array( $value ) && isset( $value['url'] ) ) {
                    $result[ $field ] = array( 'id' => $value['ID'], 'url' => $value['url'] );
                } elseif ( is_numeric( $value ) && $value ) {
                    $result[ $field ] = array( 'id' => (int) $value, 'url' => wp_get_attachment_url( $value ) );
                } else {
                    $result[ $field ] = null;
                }
            } else {
                $result[ $field ] = $value;
            }
        }

        return $this->tool_result( $id, array( 'partner_settings' => $result ) );
    }

    private function tool_update_partner_settings( $id, $args ) {
        if ( ! $this->is_group_enabled( 'mcp_acf_enabled' ) ) {
            return $this->group_disabled_error( $id, 'mcp_acf_enabled' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return $this->rpc_error( $id, -32603, 'manage_options capability required.' );
        }
        if ( ! function_exists( 'update_field' ) ) {
            return $this->rpc_error( $id, -32603, 'ACF is required for partner settings.' );
        }
        if ( empty( $args['fields'] ) || ! is_array( $args['fields'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing required argument: fields (object of field_name => value)' );
        }

        $allowed  = $this->partner_fields_map();
        $updated  = array();
        $rejected = array();

        foreach ( $args['fields'] as $field => $value ) {
            if ( ! isset( $allowed[ $field ] ) ) {
                $rejected[] = $field;
                continue;
            }
            switch ( $allowed[ $field ] ) {
                case 'image':
                    $value = absint( $value ); // expect Media Library attachment ID
                    break;
                case 'email':
                    $value = sanitize_email( $value );
                    break;
                case 'url':
                    $value = esc_url_raw( $value );
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field( $value );
                    break;
                default:
                    $value = sanitize_text_field( $value );
            }
            update_field( $field, $value, 'option' );
            $updated[] = $field;
        }

        return $this->tool_result( $id, array(
            'updated'  => $updated,
            'rejected' => $rejected,
            'note'     => empty( $rejected ) ? '' : 'Unrecognised fields were skipped. Use get_partner_settings to see valid field names.',
        ) );
    }

    // -------------------------------------------------------------------------
    // Toolkit Settings Tools
    // -------------------------------------------------------------------------

    private function tool_get_toolkit_settings( $id ) {
        $err = $this->leagueapps_gate( $id, 'mcp_toolkit_settings_enabled' );
        if ( $err ) return $err;
        $settings = get_option( 'ds_toolkit_settings', array() );
        $summary  = $settings;
        unset( $summary['global_css_content'], $summary['global_js_content'] );
        return $this->tool_result( $id, array(
            'settings' => $summary,
            'note'     => 'global_css_content and global_js_content omitted for brevity.',
        ) );
    }

    private function tool_update_toolkit_settings( $id, $args ) {
        $err = $this->leagueapps_gate( $id, 'mcp_toolkit_settings_enabled' );
        if ( $err ) return $err;
        if ( empty( $args['settings'] ) || ! is_array( $args['settings'] ) ) {
            return $this->rpc_error( $id, -32602, 'Missing or invalid argument: settings (must be an object)' );
        }
        $allowed = array_flip( array(
            'enable_login_branding', 'hide_fl_assistant', 'acf_css_vars_enabled',
            'acf_css_vars_mappings', 'getsubmenu_enabled', 'current_year_enabled',
            'forminator_email_partner_enabled', 'forminator_email_partner_fallback',
            'global_css_enabled', 'global_css_content', 'global_js_enabled', 'global_js_content',
            'child_pages_enabled', 'child_pages_template_id', 'child_pages_columns',
            'child_pages_columns_tablet', 'child_pages_columns_mobile',
        ) );
        $current  = get_option( 'ds_toolkit_settings', array() );
        $updated  = array();
        $rejected = array();
        foreach ( $args['settings'] as $key => $value ) {
            if ( isset( $allowed[ $key ] ) ) {
                $current[ $key ] = $value;
                $updated[]       = $key;
            } else {
                $rejected[] = $key;
            }
        }
        update_option( 'ds_toolkit_settings', $current );
        return $this->tool_result( $id, array( 'updated_keys' => $updated, 'rejected_keys' => $rejected ) );
    }
}
