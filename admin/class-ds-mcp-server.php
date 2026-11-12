<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once dirname( __DIR__ ) . '/includes/class-ds-env.php';
require_once __DIR__ . '/class-ds-mcp-tools.php';
require_once __DIR__ . '/mcp/trait-ds-mcp-posts.php';
require_once __DIR__ . '/mcp/trait-ds-mcp-taxonomies.php';
require_once __DIR__ . '/mcp/trait-ds-mcp-fields.php';
require_once __DIR__ . '/mcp/trait-ds-mcp-builder.php';
require_once __DIR__ . '/mcp/trait-ds-mcp-acf-schema.php';
require_once __DIR__ . '/mcp/trait-ds-mcp-schema.php';
require_once __DIR__ . '/mcp/trait-ds-mcp-site.php';

/**
 * DS Toolkit MCP Server
 *
 * Registers /wp-json/ds-toolkit/v1/mcp — a Model Context Protocol endpoint
 * (JSON-RPC 2.0, protocol 2024-11-05). Each tool group can be independently
 * enabled/disabled from the MCP tab in DS Toolkit settings.
 *
 * Auth: HTTP Basic Auth + WordPress Application Passwords (WP 5.6+)
 */
class DS_MCP_Server {

    // What each tool does lives in admin/mcp/, one trait per group of tools. This file is the transport:
    // the route, the permission check, the group switches, tools/list, tools/call and the reply helpers.
    use DS_MCP_Posts_Tools,
        DS_MCP_Taxonomy_Tools,
        DS_MCP_Field_Tools,
        DS_MCP_Builder_Tools,
        DS_MCP_ACF_Schema_Tools,
        DS_MCP_Tool_Schema,
        DS_MCP_Site_Tools;

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'ds-toolkit/v1', '/mcp', array(
            'methods'             => array( 'POST', 'GET' ),
            'callback'            => array( $this, 'handle_request' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission( $request ) {
        if ( $request->get_method() === 'GET' ) {
            return true;
        }
        return is_user_logged_in() && current_user_can( 'edit_posts' );
    }

    public function handle_request( WP_REST_Request $request ) {
        if ( $request->get_method() === 'GET' ) {
            return new WP_REST_Response( array(
                'server'   => 'DS Toolkit MCP',
                'version'  => DS_TOOLKIT_VERSION,
                'protocol' => '2024-11-05',
                'endpoint' => rest_url( 'ds-toolkit/v1/mcp' ),
            ), 200 );
        }

        $body = $request->get_json_params();

        if ( ! is_array( $body ) || ! isset( $body['jsonrpc'] ) || $body['jsonrpc'] !== '2.0' ) {
            return $this->rpc_error( null, -32600, 'Invalid Request — expected JSON-RPC 2.0' );
        }

        $method = isset( $body['method'] ) ? $body['method'] : '';
        $id     = isset( $body['id'] ) ? $body['id'] : null;
        $params = isset( $body['params'] ) ? $body['params'] : array();

        switch ( $method ) {
            case 'initialize':
                return $this->handle_initialize( $id, $params );
            case 'notifications/initialized':
                return new WP_REST_Response( null, 204 );
            case 'tools/list':
                return $this->handle_tools_list( $id );
            case 'tools/call':
                return $this->handle_tools_call( $id, $params );
            case 'ping':
                return $this->rpc_result( $id, new stdClass() );
            default:
                return $this->rpc_error( $id, -32601, 'Method not found: ' . $method );
        }
    }

    // -------------------------------------------------------------------------
    // Group Access
    // -------------------------------------------------------------------------

    /**
     * Check if a tool group is enabled. A missing key counts as OFF (it used to count as on; every site saves
     * all eleven switches, off by default, so nothing that works today changes).
     */
    private function is_group_enabled( $key ) {
        $settings = get_option( 'ds_toolkit_settings', array() ); // WordPress keeps options in memory already
        return is_array( $settings ) && ! empty( $settings[ $key ] ); // fail closed: a group is on only when its switch is saved on
    }

    /**
     * Returns true only for users whose email matches DS_TOOLKIT_ADMIN_DOMAIN.
     * Required for all destructive / schema-level MCP tools.
     */
    private function is_leagueapps_user() {
        return DS_Toolkit::is_leagueapps_user(); // one rule: an administrator with a staff address (1.12.0)
    }

    /**
     * Gate helper for tools that require manage_options + DS_TOOLKIT_ADMIN_DOMAIN email.
     * Also enforces the group toggle. Returns an error response or null on pass.
     */
    private function leagueapps_gate( $id, $group_key ) {
        if ( ! $this->is_group_enabled( $group_key ) ) {
            return $this->group_disabled_error( $id, $group_key );
        }
        if ( ! current_user_can( 'manage_options' ) || ! $this->is_leagueapps_user() ) {
            return $this->rpc_error( $id, -32603, 'Insufficient permissions — manage_options + ' . DS_Env::staff_domain() . ' account required' );
        }
        return null;
    }

    /**
     * Why the current user may not create (or, with $post, change) a post with these values, or '' when they
     * may. The WordPress rules for the post type itself: its create/publish/edit-others capabilities, a
     * known status, and, for anyone who is not an administrator, only public post types (an Author could
     * otherwise create a Themer layout, which runs on every page).
     */
    private function post_rule_error( $post_type, $status = null, $author = null, $post = null ) {
        $pto = get_post_type_object( $post_type );
        if ( ! $pto ) {
            return 'Unknown post type: ' . $post_type;
        }
        if ( ! current_user_can( 'manage_options' ) && empty( $pto->public ) ) {
            return 'Only public post types can be written through MCP without manage_options';
        }
        if ( ! $post && ! current_user_can( $pto->cap->create_posts ) ) {
            return 'Insufficient permissions to create ' . $post_type;
        }
        if ( null !== $status ) {
            if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private', 'future' ), true ) ) {
                return 'Unsupported status: ' . $status . ' (draft, pending, publish, private or future)';
            }
            $was = $post ? $post->post_status : 'draft';
            if ( in_array( $status, array( 'publish', 'private', 'future' ), true ) && $status !== $was && ! current_user_can( $pto->cap->publish_posts ) ) {
                return 'Insufficient permissions to publish ' . $post_type;
            }
        }
        if ( null !== $author && (int) $author !== get_current_user_id() && ! current_user_can( $pto->cap->edit_others_posts ) ) {
            return 'Insufficient permissions to set another author';
        }
        return '';
    }

    /**
     * Returns the group key for a given post type slug.
     */
    private function group_for_post_type( $post_type ) {
        return in_array( $post_type, array( 'post', 'page' ), true )
            ? 'mcp_posts_pages_enabled'
            : 'mcp_cpt_enabled';
    }

    // -------------------------------------------------------------------------
    // MCP Methods
    // -------------------------------------------------------------------------

    private function handle_initialize( $id, $params ) {
        return $this->rpc_result( $id, array(
            'protocolVersion' => '2024-11-05',
            'capabilities'    => array( 'tools' => new stdClass() ),
            'serverInfo'      => array(
                'name'    => 'DS Toolkit',
                'version' => DS_TOOLKIT_VERSION,
            ),
            'instructions' => 'DS Toolkit MCP server for WordPress. Tools are grouped by type — Posts & Pages, Custom Post Types, Taxonomies, ACF Fields, and Toolkit Settings. Each group can be enabled/disabled in DS Toolkit > MCP tab.',
        ) );
    }

    private function handle_tools_list( $id ) {
        $all_tools = $this->get_tools_schema();
        $available = array_values( array_filter( $all_tools, array( $this, 'is_tool_available' ) ) );
        return $this->rpc_result( $id, array( 'tools' => $available ) );
    }

    /**
     * Should a tool appear in tools/list? Yes when one of its switches is on (DS_MCP_Tools). A tool the table
     * does not know is not offered: before the 2026-11 audit an unlisted name defaulted to "available", which
     * is how the two bulk post tools were offered with every group switched off.
     */
    private function is_tool_available( $tool ) {
        return DS_MCP_Tools::enabled( isset( $tool['name'] ) ? $tool['name'] : '' );
    }

    private function handle_tools_call( $id, $params ) {
        $name      = isset( $params['name'] ) ? $params['name'] : '';
        $arguments = isset( $params['arguments'] ) ? $params['arguments'] : array();
        if ( ! is_string( $name ) || ! is_array( $arguments ) ) {
            return $this->rpc_error( $id, -32602, 'Invalid params: name (string) and arguments (object)' );
        }

        // One table (DS_MCP_Tools) says which capability a tool needs and whether it changes the site; the
        // handler is the method tool_<name>. A name outside the table is refused before anything runs.
        $tool   = DS_MCP_Tools::get( $name );
        $method = 'tool_' . $name;
        if ( ! $tool || ! method_exists( $this, $method ) ) {
            return $this->rpc_error( $id, -32602, 'Unknown tool: ' . $name );
        }

        // The route only asks for edit_posts, so a tool that changes or reads site-wide things also needs the
        // capability WordPress itself asks for on the matching admin screen. Administrators have all of these,
        // so nothing changes for them; an Author or Editor no longer reaches users, menus or maintenance.
        if ( '' !== $tool['cap'] && ! current_user_can( $tool['cap'] ) ) {
            if ( $tool['write'] ) {
                DS_MCP_Tools::log( $name, $arguments, false, 'Insufficient permissions: ' . $tool['cap'] . ' required' );
            }
            return $this->rpc_error( $id, -32603, 'Insufficient permissions: ' . $tool['cap'] . ' required' );
        }

        // Each handler still checks its own group switch and its per-item rules (a post's own capabilities,
        // the staff gate for schema and options tools).
        $response = $this->$method( $id, $arguments );

        if ( $tool['write'] ) {
            // Who changed what through MCP: the last 100 calls of tools that write, successful or refused.
            $data  = ( $response instanceof WP_REST_Response ) ? (array) $response->get_data() : array();
            $error = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : '';
            DS_MCP_Tools::log( $name, $arguments, '' === $error, $error );
        }
        return $response;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function group_disabled_error( $id, $group_key ) {
        $labels = array(
            'mcp_posts_pages_enabled'      => 'Posts & Pages',
            'mcp_cpt_enabled'              => 'Custom Post Types',
            'mcp_taxonomies_enabled'       => 'Taxonomies',
            'mcp_acf_enabled'              => 'ACF / Custom Fields',
            'mcp_toolkit_settings_enabled' => 'Toolkit Settings',
            'mcp_bb_enabled'               => 'Beaver Builder',
            'mcp_acf_schema_enabled'       => 'ACF Schema (Post Types & Taxonomies)',
            'mcp_menus_enabled'            => 'Menus',
            'mcp_maintenance_enabled'      => 'Maintenance',
            'mcp_options_enabled'          => 'Options',
            'mcp_users_enabled'            => 'Users & Media',
        );
        $label = isset( $labels[ $group_key ] ) ? $labels[ $group_key ] : $group_key;
        return $this->rpc_error( $id, -32603, '"' . $label . '" tools are disabled. Enable them in DS Toolkit → Settings → MCP tab.' );
    }

    private function rpc_result( $id, $result ) {
        return new WP_REST_Response( array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ), 200 );
    }

    private function tool_result( $id, $data ) {
        return $this->rpc_result( $id, array(
            'content' => array(
                array(
                    'type' => 'text',
                    'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
                ),
            ),
        ) );
    }

    private function rpc_error( $id, $code, $message ) {
        return new WP_REST_Response( array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => array( 'code' => $code, 'message' => $message ),
        ), 200 );
    }
}
