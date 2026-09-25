<?php
/**
 * ds-table upload endpoint (wp_ajax_ds_table_upload): importing rows into the editor
 * keeps no file, and who may read, store or replace a CSV. Drives the real handler
 * as an administrator, with wp_die captured:
 *   wp eval-file tests/table-ajax-test.php
 * Exit code 1 on any failure. Writes nothing: storing a file needs a real HTTP upload,
 * so that path is covered by the builder end-to-end test instead.
 */
if ( ! has_action( 'wp_ajax_ds_table_upload' ) ) { echo "The Table module is not loaded (needs Beaver Builder and ds_table_module_enabled).\n"; exit( 1 ); }

$GLOBALS['dsa_fail'] = 0; $GLOBALS['dsa_n'] = 0;
function dsa_is( $label, $ok, $info = '' ) {
	$GLOBALS['dsa_n']++;
	if ( ! $ok ) { $GLOBALS['dsa_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok || '' === $info ? '' : "\n     " . $info ) . "\n";
}

class DSA_Die extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () {
	return function ( $message ) { throw new DSA_Die( is_scalar( $message ) ? (string) $message : '' ); };
} );

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admin[0] );

$csv_count = function () {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('text/csv','text/plain','text/tab-separated-values')" );
};

/** Posts a file to the endpoint; returns the JSON reply, or what wp_die was called with. */
$call = function ( $body, $post = array(), $name = 'teams.csv', $nonce = null ) {
	$tmp = tempnam( sys_get_temp_dir(), 'dsa' );
	file_put_contents( $tmp, $body );
	$_FILES = array( 'file' => array( 'name' => $name, 'type' => 'text/csv', 'tmp_name' => $tmp, 'error' => 0, 'size' => strlen( $body ) ) );
	$post['nonce'] = null === $nonce ? wp_create_nonce( 'ds_table' ) : $nonce;
	$_POST = $post; $_REQUEST = $post;
	$died = null;
	ob_start();
	try { do_action( 'wp_ajax_ds_table_upload' ); } catch ( DSA_Die $e ) { $died = $e->getMessage(); }
	$out = ob_get_clean();
	@unlink( $tmp );
	$json = json_decode( $out, true );
	return is_array( $json ) ? $json : array( 'died' => $died, 'raw' => $out );
};
$refused = function ( $r, $needle ) { return isset( $r['success'] ) && false === $r['success'] && false !== stripos( $r['data']['message'] ?? '', $needle ); };

$body   = "\xEF\xBB\xBFTeam,Coach\n\"U10, Blue\",Smith\nU12 Red,Jones\n";
$before = $csv_count();

/* ---- importing rows: read only ---- */
$r = $call( $body, array( 'store' => '0' ) );
dsa_is( 'import (store=0) reads the rows', ! empty( $r['success'] ) && 3 === count( $r['data']['rows'] ) && 'U10, Blue' === $r['data']['rows'][1][0], wp_json_encode( $r ) );
dsa_is( 'import replies with no attachment, only the file name', isset( $r['data'] ) && 0 === $r['data']['id'] && '' === $r['data']['url'] && 'teams.csv' === $r['data']['name'], wp_json_encode( $r['data'] ?? $r ) );
dsa_is( 'import keeps no file (Media Library count unchanged)', $csv_count() === $before, $before . ' -> ' . $csv_count() );
$r = $call( $body, array( 'store' => '0' ), '<img src=x onerror=alert(1)>.csv' );
dsa_is( 'the file name in the reply is sanitised', ! empty( $r['success'] ) && false === strpbrk( $r['data']['name'], '<>"\'' ), $r['data']['name'] ?? wp_json_encode( $r ) );

/* ---- refusals ---- */
dsa_is( 'a non-CSV file is refused', $refused( $call( '<?php echo 1;', array( 'store' => '0' ), 'shell.php' ), '.csv' ) );
dsa_is( 'a file with no rows is refused', $refused( $call( "  \n", array( 'store' => '0' ) ), 'no rows' ) );
$r = $call( $body, array( 'store' => '0' ), 'teams.csv', 'not-a-nonce' );
dsa_is( 'a bad nonce is refused before the file is read', isset( $r['died'] ) && '-1' === $r['died'], wp_json_encode( $r ) );
$page = get_posts( array( 'post_type' => 'page', 'numberposts' => 1, 'fields' => 'ids' ) );
dsa_is( 'replace_id must be a CSV in the Media Library (a page is refused)', $refused( $call( $body, array( 'replace_id' => (string) $page[0] ) ), 'cannot be replaced' ) );

/* ---- capabilities ---- */
$no_upload = function ( $caps ) { $caps['upload_files'] = false; return $caps; };
add_filter( 'user_has_cap', $no_upload );
dsa_is( 'an account without upload rights can still import rows', ! empty( $call( $body, array( 'store' => '0' ) )['success'] ) );
dsa_is( '... but cannot store a file', $refused( $call( $body ), 'cannot upload' ) );
dsa_is( '... or replace one (replace_id outranks store=0)', $refused( $call( $body, array( 'store' => '0', 'replace_id' => '1' ) ), 'cannot upload' ) );
remove_filter( 'user_has_cap', $no_upload );
$no_edit = function ( $caps ) { $caps['edit_posts'] = false; return $caps; };
add_filter( 'user_has_cap', $no_edit );
dsa_is( 'an account that cannot edit posts is refused', $refused( $call( $body, array( 'store' => '0' ) ), 'not allowed' ) );
remove_filter( 'user_has_cap', $no_edit );

dsa_is( 'nothing was written to the Media Library by this test', $csv_count() === $before );
$_FILES = array(); $_POST = array(); $_REQUEST = array();

echo $GLOBALS['dsa_fail'] ? "FAILURES: {$GLOBALS['dsa_fail']} of {$GLOBALS['dsa_n']}\n" : "ALL {$GLOBALS['dsa_n']} PASS\n";
if ( $GLOBALS['dsa_fail'] ) { exit( 1 ); }
