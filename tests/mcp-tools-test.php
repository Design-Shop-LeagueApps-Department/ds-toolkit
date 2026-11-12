<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * MCP: one table of tools drives the list, the gate, the dispatch, the tab and the log. Stub-style:
 *
 *   php tests/mcp-tools-test.php
 *
 * Why (2026-11 audit, item 23): the tool list lived in four places in the server plus a hand-written copy on
 * the MCP tab, and they had drifted: bulk_create_posts and bulk_update_posts were offered by tools/list with
 * every group switched off, because the availability map did not know them and unknown meant "available".
 * Nothing recorded who changed what through MCP.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
$GLOBALS['opts'] = array(); $GLOBALS['autoload'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_get_current_user() { return (object) array( 'user_login' => 'zay' ); }
class WP_REST_Response { public $d; function __construct( $d = null, $s = 200 ) { $this->d = $d; } function get_data() { return $this->d; } }

$root = dirname( __DIR__ ) . '/';
require $root . 'admin/class-ds-mcp-server.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-82s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

$table  = DS_MCP_Tools::all();
$server = new DS_MCP_Server();
$schema = array_keys( $server->tool_descriptions() );
$rc     = new ReflectionClass( 'DS_MCP_Server' );
$methods = array();
foreach ( $rc->getMethods() as $m ) {
	if ( 0 === strpos( $m->getName(), 'tool_' ) && ! in_array( $m->getName(), array( 'tool_result', 'tool_descriptions' ), true ) ) { $methods[] = substr( $m->getName(), 5 ); }
}
$names = array_keys( $table );
$s1 = $names; $s2 = $schema; $s3 = $methods; sort( $s1 ); sort( $s2 ); sort( $s3 );

echo "one list\n";
chk( 'every tool in the table has a schema entry, and the other way round', array( array_values( array_diff( $s1, $s2 ) ), array_values( array_diff( $s2, $s1 ) ) ), array( array(), array() ) );
chk( 'every tool in the table has a handler method, and the other way round', array( array_values( array_diff( $s1, $s3 ) ), array_values( array_diff( $s3, $s1 ) ) ), array( array(), array() ) );
chk( 'the table is not empty (' . count( $names ) . ' tools)', count( $names ) >= 56, true );
$bad = array();
foreach ( $table as $n => $row ) {
	foreach ( $row['groups'] as $g ) { if ( ! isset( DS_MCP_Tools::GROUPS[ $g ] ) ) { $bad[] = "$n: $g"; } }
	if ( ! $row['groups'] || ! is_string( $row['cap'] ) || ! is_bool( $row['write'] ) ) { $bad[] = "$n: shape"; }
	if ( ! in_array( $row['cap'], array( '', 'manage_options', 'edit_theme_options', 'list_users', 'upload_files' ), true ) ) { $bad[] = "$n: cap " . $row['cap']; }
}
chk( 'every row names a real group and a known capability', $bad, array() );
$desc = $server->tool_descriptions();
chk( 'every tool has a description for tools/list and the tab', array_keys( array_filter( $desc, function ( $d ) { return strlen( $d ) < 10; } ) ), array() );
$write_names = array_keys( array_filter( $table, function ( $r ) { return $r['write']; } ) );
$readish = array_values( array_filter( $write_names, function ( $n ) { return (bool) preg_match( '/^(get_|list_|acf_list_|acf_get_|bb_list_)/', $n ); } ) );
$unmarked = array_values( array_filter( array_diff( $names, $write_names ), function ( $n ) { return (bool) preg_match( '/^(create_|update_|delete_|set_|assign_|bulk_|flush_|search_replace|regenerate_|acf_create_|acf_update_|acf_delete_|bb_apply_)/', $n ); } ) );
chk( 'reads are not marked as writes, and every tool that changes something is', array( $readish, $unmarked ), array( array(), array() ) );

echo "tools/list\n";
$avail = function ( array $settings ) use ( $names ) { return array_values( array_filter( $names, function ( $n ) use ( $settings ) { return DS_MCP_Tools::enabled( $n, $settings ); } ) ); };
chk( 'every group off: nothing is offered (the two bulk tools used to be)', $avail( array() ), array() );
chk( 'a broken settings option: nothing is offered', DS_MCP_Tools::enabled( 'list_posts', 'oops' ), false );
chk( 'Posts & Pages on: the post tools and both bulk tools', $avail( array( 'mcp_posts_pages_enabled' => 1 ) ), array( 'list_posts', 'get_post', 'create_post', 'update_post', 'delete_post', 'bulk_create_posts', 'bulk_update_posts' ) );
chk( 'Custom Post Types on: the same post tools plus list_post_types', count( $avail( array( 'mcp_cpt_enabled' => 1 ) ) ), 8 );
chk( 'Maintenance on: its four tools only', $avail( array( 'mcp_maintenance_enabled' => '1' ) ), array( 'flush_rewrite_rules', 'flush_cache', 'delete_transients', 'search_replace' ) );
chk( 'a name the table does not know is never offered', DS_MCP_Tools::enabled( 'drop_database', array( 'mcp_maintenance_enabled' => 1 ) ), false );
$all_on = array_fill_keys( array_keys( DS_MCP_Tools::GROUPS ), 1 );
chk( 'everything on: every tool', count( $avail( $all_on ) ), count( $names ) );
$by = DS_MCP_Tools::by_group();
chk( 'every tool appears once on the tab, under a real group', array( array_sum( array_map( 'count', $by ) ), array_keys( $by ) ), array( count( $names ), array_keys( DS_MCP_Tools::GROUPS ) ) );

echo "the server uses the table\n";
$src = file_get_contents( $root . 'admin/class-ds-mcp-server.php' );
chk( 'no second availability map, capability map or dispatch switch', array( (bool) preg_match( "/case 'list_posts'\s*:/", $src ), (bool) preg_match( '/\$needs\s*=\s*array\(/', $src ), (bool) preg_match( "/'list_post_types'\s*=>\s*'mcp_cpt_enabled'/", $src ) ), array( false, false, false ) );
chk( 'an unknown tool is refused before anything runs', (bool) preg_match( '/\$tool\s*=\s*DS_MCP_Tools::get\( \$name \);.*?if \( ! \$tool \|\| ! method_exists\( \$this, \$method \) \) \{\s*return \$this->rpc_error\( \$id, -32602, \'Unknown tool: \' \. \$name \);/s', $src ), true );
chk( 'the capability comes from the table and is checked before the handler', strpos( $src, "current_user_can( \$tool['cap'] )" ) < strpos( $src, '$this->$method( $id, $arguments )' ) && false !== strpos( $src, "current_user_can( \$tool['cap'] )" ), true );
chk( 'write tools are logged after they run', (bool) preg_match( "/if \( \\\$tool\['write'\] \) \{.*?DS_MCP_Tools::log\( \\\$name, \\\$arguments, '' === \\\$error, \\\$error \);/s", $src ), true );
$view = file_get_contents( $root . 'admin/views/page-mcp.php' );
chk( 'the tab draws the list from the table, with no hand-typed tool names', array( false !== strpos( $view, 'DS_MCP_Tools::by_group()' ), (bool) preg_match( '/<strong>(list_posts|update_option|acf_create_post_type)<\/strong>/', $view ) ), array( true, false ) );

echo "the log of changes\n";
DS_MCP_Tools::log( 'update_post', array( 'id' => 48198, 'title' => 'Secret new title', 'content' => '<p>Private body</p>' ), true );
DS_MCP_Tools::log( 'update_option', array( 'key' => 'blogname', 'value' => 'hunter2' ), false, 'Insufficient permissions' );
DS_MCP_Tools::log( 'bulk_create_posts', array( 'posts' => array( array( 'title' => 'a' ), array( 'title' => 'b' ) ) ), true );
$log = DS_MCP_Tools::recent( 10 );
chk( 'newest first, with who and which tool', array( $log[0]['tool'], $log[2]['tool'], $log[2]['user'], $log[2]['ok'], $log[1]['ok'], $log[1]['error'] ), array( 'bulk_create_posts', 'update_post', 'zay', true, false, 'Insufficient permissions' ) );
chk( 'what it was done to: ids and counts', array( $log[2]['target'], $log[1]['target'], $log[0]['target'] ), array( array( 'id' => '48198' ), array( 'key' => 'blogname' ), array( 'posts' => '2 item(s)' ) ) );
$flat = json_encode( $GLOBALS['opts'][ DS_MCP_Tools::LOG_OPTION ] );
chk( 'argument VALUES are never kept (titles, content, option values)', array( false === strpos( $flat, 'Secret' ), false === strpos( $flat, 'Private body' ), false === strpos( $flat, 'hunter2' ) ), array( true, true, true ) );
chk( 'the names of the arguments are', $log[2]['args'], array( 'id', 'title', 'content' ) );
chk( 'its own option, not autoloaded', $GLOBALS['autoload'][ DS_MCP_Tools::LOG_OPTION ], false );
for ( $i = 0; $i < 130; $i++ ) { DS_MCP_Tools::log( 'flush_cache', array(), true ); }
chk( 'it keeps the last 100 only', count( $GLOBALS['opts'][ DS_MCP_Tools::LOG_OPTION ] ), 100 );
chk( 'uninstall removes it', false !== strpos( file_get_contents( $root . 'includes/class-ds-lifecycle.php' ), "'ds_mcp_log'" ), true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
