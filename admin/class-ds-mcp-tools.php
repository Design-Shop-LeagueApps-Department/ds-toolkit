<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The one table of MCP tools: which switch turns a tool on, which capability it needs, whether it changes
 * the site. The server lists, gates, dispatches and logs from it, and the MCP tab draws its reference from it.
 *
 * Before the 2026-11 audit the same list lived in four places in the server (the schema, an availability
 * map, a capability map, a 70-line switch) plus a hand-written copy on the MCP tab, and they had drifted:
 * bulk_create_posts and bulk_update_posts were in the schema and the switch but not in the availability map,
 * so tools/list offered them with every group switched off.
 *
 * A new tool needs three things, and tests/mcp-tools-test.php fails when one is missing: a row here, an entry
 * in DS_MCP_Server::get_tools_schema() (its description and arguments), and a method tool_<name>( $id, $args ).
 */
if ( ! class_exists( 'DS_MCP_Tools' ) ) :
class DS_MCP_Tools {

	/** Tool groups in display order: settings key => array( title, staff only? ). */
	const GROUPS = array(
		'mcp_posts_pages_enabled'      => array( 'Posts & Pages', false ),
		'mcp_cpt_enabled'              => array( 'Custom Post Types', false ),
		'mcp_taxonomies_enabled'       => array( 'Taxonomies', false ),
		'mcp_acf_enabled'              => array( 'ACF / Custom Fields and Partner Settings', false ),
		'mcp_toolkit_settings_enabled' => array( 'DS Toolkit Settings', false ),
		'mcp_acf_schema_enabled'       => array( 'ACF Schema', true ),
		'mcp_bb_enabled'               => array( 'Beaver Builder', false ),
		'mcp_menus_enabled'            => array( 'Menus', false ),
		'mcp_maintenance_enabled'      => array( 'Maintenance', false ),
		'mcp_options_enabled'          => array( 'Options', true ),
		'mcp_users_enabled'            => array( 'Users & Media', false ),
	);

	/** How many entries the log of changes keeps. */
	const LOG_OPTION = 'ds_mcp_log';
	const LOG_MAX    = 100;

	/**
	 * name => array(
	 *   'groups' the switches that turn it on: listed and callable when ANY of them is on (the post tools serve
	 *            both Posts & Pages and Custom Post Types; which one applies is decided per post type inside).
	 *   'cap'    the capability asked for before the tool runs, on top of the route's edit_posts. '' = the
	 *            tool checks per item itself (a post's own capabilities, the staff gate).
	 *   'write'  true = it changes the site, so the call is recorded in the log.
	 * )
	 */
	public static function all() {
		$posts = array( 'mcp_posts_pages_enabled', 'mcp_cpt_enabled' );
		$t     = function ( $group, $cap = '', $write = false ) {
			return array( 'groups' => (array) $group, 'cap' => $cap, 'write' => $write );
		};
		return array(
			'list_posts'              => $t( $posts ),
			'get_post'                => $t( $posts ),
			'create_post'             => $t( $posts, '', true ),
			'update_post'             => $t( $posts, '', true ),
			'delete_post'             => $t( $posts, '', true ),
			'bulk_create_posts'       => $t( $posts, '', true ),
			'bulk_update_posts'       => $t( $posts, '', true ),
			'list_post_types'         => $t( 'mcp_cpt_enabled' ),

			'list_taxonomies'         => $t( 'mcp_taxonomies_enabled' ),
			'list_terms'              => $t( 'mcp_taxonomies_enabled' ),
			'get_term'                => $t( 'mcp_taxonomies_enabled' ),
			'create_term'             => $t( 'mcp_taxonomies_enabled', '', true ),
			'update_term'             => $t( 'mcp_taxonomies_enabled', '', true ),
			'set_post_terms'          => $t( 'mcp_taxonomies_enabled', '', true ),
			'delete_term'             => $t( 'mcp_taxonomies_enabled', '', true ),

			'get_post_fields'         => $t( 'mcp_acf_enabled' ),
			'update_post_fields'      => $t( 'mcp_acf_enabled', '', true ),
			'get_partner_settings'    => $t( 'mcp_acf_enabled' ),
			'update_partner_settings' => $t( 'mcp_acf_enabled', '', true ),

			'get_toolkit_settings'    => $t( 'mcp_toolkit_settings_enabled' ),
			'update_toolkit_settings' => $t( 'mcp_toolkit_settings_enabled', '', true ),

			'acf_list_post_types'     => $t( 'mcp_acf_schema_enabled' ),
			'acf_create_post_type'    => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_update_post_type'    => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_delete_post_type'    => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_list_taxonomies'     => $t( 'mcp_acf_schema_enabled' ),
			'acf_create_taxonomy'     => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_update_taxonomy'     => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_delete_taxonomy'     => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_list_field_groups'   => $t( 'mcp_acf_schema_enabled' ),
			'acf_get_field_group'     => $t( 'mcp_acf_schema_enabled' ),
			'acf_create_field_group'  => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_update_field_group'  => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_delete_field_group'  => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_list_options_pages'  => $t( 'mcp_acf_schema_enabled' ),
			'acf_create_options_page' => $t( 'mcp_acf_schema_enabled', '', true ),
			'acf_delete_options_page' => $t( 'mcp_acf_schema_enabled', '', true ),

			'get_bb_global_colors'     => $t( 'mcp_bb_enabled' ),
			'update_bb_global_colors'  => $t( 'mcp_bb_enabled', '', true ),
			'bb_list_layout_templates' => $t( 'mcp_bb_enabled' ),
			'bb_apply_layout_template' => $t( 'mcp_bb_enabled', '', true ),

			'list_menus'              => $t( 'mcp_menus_enabled', 'edit_theme_options' ),
			'get_menu'                => $t( 'mcp_menus_enabled', 'edit_theme_options' ),
			'set_menu_items'          => $t( 'mcp_menus_enabled', 'edit_theme_options', true ),
			'assign_menu_to_location' => $t( 'mcp_menus_enabled', 'edit_theme_options', true ),

			'flush_rewrite_rules'     => $t( 'mcp_maintenance_enabled', 'manage_options', true ),
			'flush_cache'             => $t( 'mcp_maintenance_enabled', 'manage_options', true ),
			'delete_transients'       => $t( 'mcp_maintenance_enabled', 'manage_options', true ),
			'search_replace'          => $t( 'mcp_maintenance_enabled', '', true ),

			'get_option'              => $t( 'mcp_options_enabled' ),
			'update_option'           => $t( 'mcp_options_enabled', '', true ),

			'list_users'              => $t( 'mcp_users_enabled', 'list_users' ),
			'get_user'                => $t( 'mcp_users_enabled', 'list_users' ),
			'list_media'              => $t( 'mcp_users_enabled', 'upload_files' ),
			'get_media'               => $t( 'mcp_users_enabled', 'upload_files' ),
			'regenerate_thumbnails'   => $t( 'mcp_users_enabled', 'manage_options', true ),
		);
	}

	/** One tool's row, or null for a name the table does not know. */
	public static function get( $name ) {
		$all = self::all();
		return ( is_string( $name ) && isset( $all[ $name ] ) ) ? $all[ $name ] : null;
	}

	/** Is any of the tool's switches on? Fail closed: a missing or broken settings option means off. */
	public static function enabled( $name, $settings = null ) {
		$row = self::get( $name );
		if ( ! $row ) {
			return false;
		}
		if ( null === $settings ) {
			$settings = get_option( 'ds_toolkit_settings', array() );
		}
		if ( ! is_array( $settings ) ) {
			return false;
		}
		foreach ( $row['groups'] as $g ) {
			if ( ! empty( $settings[ $g ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Tool names by group, for the MCP tab: group key => names in table order (a post tool sits in its first group). */
	public static function by_group() {
		$out = array_fill_keys( array_keys( self::GROUPS ), array() );
		foreach ( self::all() as $name => $row ) {
			$out[ $row['groups'][0] ][] = $name;
		}
		return $out;
	}

	/**
	 * Record a call of a write tool: who, which tool, on what, and whether it went through. The last LOG_MAX
	 * entries, in one option that is not autoloaded. Argument VALUES are never kept (they hold page content,
	 * option values, field data): only the names of the arguments and the few that identify the target.
	 */
	public static function log( $name, array $args, $ok, $error = '' ) {
		$user   = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$target = array();
		foreach ( array( 'id', 'post_id', 'post_type', 'taxonomy', 'term_id', 'key', 'menu_id', 'location', 'template', 'target', 'name', 'slug' ) as $k ) {
			if ( isset( $args[ $k ] ) && is_scalar( $args[ $k ] ) ) {
				$target[ $k ] = substr( sanitize_text_field( (string) $args[ $k ] ), 0, 80 );
			}
		}
		foreach ( array( 'posts', 'items', 'fields', 'settings', 'colors' ) as $k ) {
			if ( isset( $args[ $k ] ) && is_array( $args[ $k ] ) ) {
				$target[ $k ] = count( $args[ $k ] ) . ' item(s)';
			}
		}
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, array(
			't'      => time(),
			'user'   => ( $user && ! empty( $user->user_login ) ) ? (string) $user->user_login : '',
			'tool'   => (string) $name,
			'target' => $target,
			'args'   => array_slice( array_map( 'strval', array_keys( $args ) ), 0, 12 ),
			'ok'     => (bool) $ok,
			'error'  => substr( (string) $error, 0, 200 ),
		) );
		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_MAX ), false );
	}

	/** The log of changes, newest first. */
	public static function recent( $limit = 20 ) {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? array_slice( $log, 0, max( 1, (int) $limit ) ) : array();
	}
}
endif;
