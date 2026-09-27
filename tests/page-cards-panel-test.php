<?php
// Run: wp eval-file tests/page-cards-panel-test.php (creates temporary pages and a user, deletes them at the end).
if ( ! defined( 'WP_CLI' ) ) { exit; }
// Page Cards AJAX: drafts listed only to editors, titles plain, backslash kept, publish only drafts.
$ok = 0; $bad = 0; $is = function ( $n, $a, $b ) use ( &$ok, &$bad ) { if ( $a === $b ) { $ok++; } else { $bad++; echo "FAIL $n: " . var_export( $a, 1 ) . "\n"; } };
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new Exception( 'die' ); }; } );
$call = function ( $action, $post ) { $_POST = $_REQUEST = wp_slash( array_merge( array( 'nonce' => wp_create_nonce( 'ds_pc_images' ) ), $post ) ); /* as wp_magic_quotes() leaves it */ ob_start(); try { do_action( 'wp_ajax_' . $action ); } catch ( Exception $e ) {} return json_decode( ob_get_clean(), true ); };
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]; wp_set_current_user( $admin->ID );
$tmp = array();
$par = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'PC test parent (temporary)' ) ); $tmp[] = $par;
$r = $call( 'ds_pc_create_page', array( 'post_id' => $par, 'title' => 'Boys & Girls AC\\DC' ) );
$new = (int) ( $r['data']['id'] ?? 0 ); if ( $new ) { $tmp[] = $new; }
$is( 'create: backslash and ampersand kept in the saved title', get_post_field( 'post_title', $new ), 'Boys & Girls AC\\DC' );
$is( 'create: panel title is plain text', $r['data']['title'] ?? null, 'Boys & Girls AC\\DC' );
$author = wp_insert_user( array( 'user_login' => 'pc_' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$own = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'PC author post', 'post_author' => $author ) ); $tmp[] = $own;
wp_set_current_user( $author );
$r = $call( 'ds_pc_images', array( 'post_id' => $own, 'q' => array( 'source' => 'specific', 'parent_page' => $par ) ) );
$is( 'list: an author does not see a draft child page of another page', array_map( function ( $p ) { return $p['id']; }, $r['data']['pages'] ?? array( 'x' ) ), array() );
wp_set_current_user( $admin->ID );
$r = $call( 'ds_pc_images', array( 'post_id' => $par ) );
$is( 'list: the admin does', array_map( function ( $p ) { return $p['id']; }, $r['data']['pages'] ?? array() ), array( $new ) );
$r = $call( 'ds_pc_publish_page', array( 'page_id' => $new, 'post_id' => $par ) );
$d1 = get_post_field( 'post_date', $new );
$is( 'publish: a draft goes live with a slug', array( get_post_status( $new ), '' !== get_post_field( 'post_name', $new ) ), array( 'publish', true ) );
sleep( 1 );
$r = $call( 'ds_pc_publish_page', array( 'page_id' => $new, 'post_id' => $par ) );
$is( 'publish: an already published page is refused and keeps its date', array( $r['success'] ?? null, get_post_field( 'post_date', $new ) ), array( false, $d1 ) );
wp_trash_post( $new );
$r = $call( 'ds_pc_publish_page', array( 'page_id' => $new, 'post_id' => $par ) );
$is( 'publish: a trashed page is not forced live', get_post_status( $new ), 'trash' );
foreach ( $tmp as $t ) { wp_delete_post( $t, true ); } wp_delete_user( $author );
echo $bad ? "FAILED $bad\n" : "ALL $ok PASS\n";
