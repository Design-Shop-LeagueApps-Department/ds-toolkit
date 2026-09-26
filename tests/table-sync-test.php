<?php
/**
 * LeagueApps Table: keeping synced tables current behind a page cache. Publishing a layout
 * records its synced link; the ds_table_sync job clears the post's page cache once per data
 * change and never on a failed fetch. HTTP is faked; one temporary page is created and
 * deleted again:
 *   wp eval-file tests/table-sync-test.php
 */
$GLOBALS['sy_fail'] = 0; $GLOBALS['sy_n'] = 0;
function sy_is( $label, $got, $want ) {
	$GLOBALS['sy_n']++;
	$ok = $got === $want;
	if ( ! $ok ) { $GLOBALS['sy_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : "\n     got:  " . var_export( $got, true ) . "\n     want: " . var_export( $want, true ) ) . "\n";
}
if ( ! function_exists( 'ds_table_sources_in' ) ) { echo "SKIP: the Table module is not loaded\n"; return; }

$link = 'https://example.com/ds-table-sync-' . wp_generate_password( 8, false ) . '.csv';
$h    = md5( $link );
$body = "A\n1\n"; $fail = false; $calls = 0;
$fake = function ( $pre, $args, $url ) use ( &$body, &$fail, &$calls, $link ) {
	if ( $url !== $link ) { return $pre; }
	$calls++;
	return $fail ? new WP_Error( 'down', 'down' ) : array( 'headers' => array( 'content-type' => 'text/csv' ), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $fake, 10, 3 );
$purged = array();
$spy    = function ( $id ) use ( &$purged ) { $purged[] = $id; };
add_action( 'ds_table_purge_post', $spy );

$pid  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'ds-table sync test (temporary)' ) );
$node = function ( $src, $extra ) { return (object) array( 'node' => 'n1', 'type' => 'module', 'settings' => (object) array_merge( array( 'type' => 'ds-table', 'source' => $src ), $extra ) ); };
$data = array( 'n1' => $node( 'url', array( 'csv_url' => $link, 'sync_every' => '5' ) ), 'n2' => $node( 'file', array( 'csv_id' => '123' ) ), 'n3' => $node( 'manual', array() ) );

do_action( 'fl_builder_after_save_layout', $pid, false, $data, array() );
sy_is( 'a draft save records nothing', get_post_meta( $pid, '_ds_table_sources', true ), '' );
do_action( 'fl_builder_after_save_layout', $pid, true, $data, array() );
sy_is( 'publish records the synced link and file, not the manual table', get_post_meta( $pid, '_ds_table_sources', true ), array( array( 'url', $link, 300 ), array( 'file', '123', 0 ) ) );
sy_is( 'the sync job is scheduled', (bool) wp_next_scheduled( 'ds_table_sync' ), true );
sy_is( 'the post is found by its file', ds_table_posts_using( 'file', '123' ), array( $pid ) );

do_action( 'ds_table_sync' );
sy_is( 'first run: fetched, page cache cleared once', array( $calls, $purged ), array( 1, array( $pid ) ) );
do_action( 'ds_table_sync' );
sy_is( 'same data: nothing cleared', array( $calls, count( $purged ) ), array( 1, 1 ) );
$body = "A\n2\n";
delete_transient( 'ds_table_u_' . $h );
DS_Table_Data::url_rows( $link, 300 ); // another request picks up the change first
do_action( 'ds_table_sync' );
sy_is( 'changed data picked up elsewhere first: still cleared once', count( $purged ), 2 );
delete_transient( 'ds_table_u_' . $h ); $fail = true;
do_action( 'ds_table_sync' );
sy_is( 'a failed fetch clears nothing', count( $purged ), 2 );

do_action( 'fl_builder_after_save_layout', $pid, true, array( 'n3' => $node( 'manual', array() ) ), array() );
sy_is( 'republishing without synced tables forgets the sources', get_post_meta( $pid, '_ds_table_sources', true ), '' );

remove_filter( 'pre_http_request', $fake, 10 );
remove_action( 'ds_table_purge_post', $spy );
wp_delete_post( $pid, true );
foreach ( array( 'ds_table_u_', 'ds_table_ux_', 'ds_table_ul_' ) as $t ) { delete_transient( $t . $h ); }
delete_option( 'ds_table_last_' . $h );
if ( ! ds_table_posts_using( 'url', $link ) && ! get_posts( array( 'post_type' => 'any', 'meta_key' => '_ds_table_sources', 'fields' => 'ids', 'posts_per_page' => 1 ) ) ) { wp_clear_scheduled_hook( 'ds_table_sync' ); }
sy_is( 'temporary page removed', get_post( $pid ), null );

echo $GLOBALS['sy_fail'] ? "FAILURES: {$GLOBALS['sy_fail']} of {$GLOBALS['sy_n']}\n" : "ALL {$GLOBALS['sy_n']} PASS\n";
if ( $GLOBALS['sy_fail'] ) { exit( 1 ); }
