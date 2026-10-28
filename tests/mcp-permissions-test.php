<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * MCP endpoint permissions (1.11.0): what an Author can and cannot reach once a tool group is switched on,
 * and that an administrator keeps every tool. Calls the real REST route in-process.
 *
 *   wp eval-file tests/mcp-permissions-test.php
 *
 * Creates two temporary users (an Author and an @leagueapps.com Administrator), switches every MCP group on
 * for the run and puts the saved settings back at the end, deletes the users and every post it made.
 */
$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) {
	$n++;
	$ok = $got === $want;
	if ( ! $ok ) { $fails++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n";
};
if ( ! class_exists( 'DS_MCP_Server' ) && is_file( DS_TOOLKIT_PATH . 'admin/class-ds-mcp-server.php' ) ) {
	require_once DS_TOOLKIT_PATH . 'admin/class-ds-mcp-server.php';
}
$saved = get_option( 'ds_toolkit_settings' );
$on    = is_array( $saved ) ? $saved : array();
foreach ( array( 'mcp_posts_pages_enabled', 'mcp_cpt_enabled', 'mcp_taxonomies_enabled', 'mcp_acf_enabled', 'mcp_toolkit_settings_enabled', 'mcp_bb_enabled', 'mcp_acf_schema_enabled', 'mcp_menus_enabled', 'mcp_maintenance_enabled', 'mcp_options_enabled', 'mcp_users_enabled' ) as $k ) { $on[ $k ] = 1; }
update_option( 'ds_toolkit_settings', $on );

$author = wp_insert_user( array( 'user_login' => 'mcp_t_author_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'mcp-author-' . wp_rand() . '@example.com', 'role' => 'author' ) );
$admin  = wp_insert_user( array( 'user_login' => 'mcp_t_admin_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'mcp-admin-' . wp_rand() . '@leagueapps.com', 'role' => 'administrator' ) );
$made   = array();
$call   = function ( $user, $tool, $args = array() ) use ( &$made ) {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( 'POST', '/ds-toolkit/v1/mcp' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => array( 'name' => $tool, 'arguments' => (object) $args ) ) ) );
	$res  = rest_do_request( $req );
	$data = $res->get_data();
	$ok   = is_array( $data ) && isset( $data['result'] ) && empty( $data['result']['isError'] );
	if ( $ok && isset( $data['result']['content'][0]['text'] ) ) {
		$out = json_decode( $data['result']['content'][0]['text'], true );
		if ( ! empty( $out['id'] ) && 'create_post' === $tool ) { $made[] = (int) $out['id']; }
	}
	return $ok ? 'ok' : 'refused';
};

// Author: content they may write stays open.
$is( 'author: create a draft post', $call( $author, 'create_post', array( 'title' => 'mcp test', 'status' => 'draft' ) ), 'ok' );
$is( 'author: publish their own post (authors may publish)', $call( $author, 'create_post', array( 'title' => 'mcp test 2', 'status' => 'publish' ) ), 'ok' );
// Author: what the route alone used to allow.
$is( 'author: create a Themer layout (non-public type) is refused', $call( $author, 'create_post', array( 'title' => 'x', 'post_type' => 'fl-theme-layout' ) ), 'refused' );
$is( 'author: create a page (no publish_pages) is refused', $call( $author, 'create_post', array( 'title' => 'x', 'post_type' => 'page', 'status' => 'publish' ) ), 'refused' );
$is( 'author: create a post as someone else is refused', $call( $author, 'create_post', array( 'title' => 'x', 'post_author' => $admin ) ), 'refused' );
$is( 'author: an odd status is refused', $call( $author, 'create_post', array( 'title' => 'x', 'status' => 'inherit' ) ), 'refused' );
$own = $made[0] ?? 0;
$is( 'author: write a Beaver Builder layout into own post is refused', $call( $author, 'update_post_fields', array( 'id' => $own, 'fields' => array( '_fl_builder_data' => 'x' ) ) ), 'refused' );
$is( 'author: list users is refused', $call( $author, 'list_users' ), 'refused' );
$is( 'author: change menus is refused', $call( $author, 'list_menus' ), 'refused' );
$is( 'author: delete transients is refused', $call( $author, 'delete_transients' ), 'refused' );
$priv = wp_insert_post( array( 'post_title' => 'mcp private', 'post_status' => 'private', 'post_author' => $admin ) );
$is( 'author: read another user\'s private post is refused', $call( $author, 'get_post', array( 'id' => $priv ) ), 'refused' );

// Administrator (staff): every tool still works.
$is( 'admin: create a Themer layout', $call( $admin, 'create_post', array( 'title' => 'mcp layout', 'post_type' => 'fl-theme-layout', 'status' => 'draft' ) ), post_type_exists( 'fl-theme-layout' ) ? 'ok' : 'refused' );
$is( 'admin: write a protected field', $call( $admin, 'update_post_fields', array( 'id' => $own, 'fields' => array( '_ds_mcp_test' => 'x' ) ) ), 'ok' );
$is( 'admin: list users', $call( $admin, 'list_users' ), 'ok' );
$is( 'admin: read the private post', $call( $admin, 'get_post', array( 'id' => $priv ) ), 'ok' );
$is( 'admin: search_replace outside this site\'s tables is refused', $call( $admin, 'search_replace', array( 'search' => 'zz-none-zz', 'replace' => 'y', 'confirm' => true, 'tables' => array( 'mysql.user' ) ) ), 'refused' );
$is( 'admin: search_replace on its own table works', $call( $admin, 'search_replace', array( 'search' => 'zz-none-zz', 'replace' => 'y', 'confirm' => true, 'tables' => array( $GLOBALS['wpdb']->options ) ) ), 'ok' );
$is( 'admin: the private key store is not readable over MCP', $call( $admin, 'get_option', array( 'key' => 'ds_la_private_keys' ) ), 'refused' );
$is( 'admin: an ordinary option is', $call( $admin, 'get_option', array( 'key' => 'blogname' ) ), 'ok' );

// Groups fail closed.
$off = $on; unset( $off['mcp_users_enabled'] ); update_option( 'ds_toolkit_settings', $off );
$is( 'a missing group switch counts as off', $call( $admin, 'list_users' ), 'refused' );

// Clean up.
update_option( 'ds_toolkit_settings', $saved );
foreach ( array_merge( $made, array( $priv ) ) as $p ) { wp_delete_post( $p, true ); }
foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'author__in' => array( $author, $admin ), 'numberposts' => -1, 'fields' => 'ids' ) ) as $p ) { wp_delete_post( $p, true ); }
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $author ); wp_delete_user( $admin );
wp_set_current_user( 0 );
echo $fails ? "FAILED $fails of $n\n" : "ALL $n PASS\n";
