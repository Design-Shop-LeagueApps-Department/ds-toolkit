<?php
// Run: wp eval-file tests/table-security-test.php (needs internet: fetches a public Google Sheet and an httpbin redirect).
if ( ! defined( 'WP_CLI' ) ) { exit; }
$ok = 0; $bad = 0; $is = function ( $n, $a, $b ) use ( &$ok, &$bad ) { if ( $a === $b ) { $ok++; } else { $bad++; echo "FAIL $n: " . var_export( $a, 1 ) . "\n"; } };

// Addresses
foreach ( array( '127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fe80::1', 'fd00::1', '::ffff:127.0.0.1', '64:ff9b::7f00:1' ) as $ip ) {
	$is( "internal address refused: $ip", DS_Table_Data::is_public_ip( $ip ), false );
}
foreach ( array( '8.8.8.8', '142.250.72.14', '2607:f8b0:4005:80a::200e' ) as $ip ) { $is( "public address allowed: $ip", DS_Table_Data::is_public_ip( $ip ), true ); }
$is( 'a name that resolves to loopback is refused', DS_Table_Data::public_ips( 'localhost' ), array() );

// Fetch
foreach ( array( 'http://127.0.0.1/', 'http://169.254.169.254/latest/meta-data/', 'http://localhost/', 'https://user:pw@docs.google.com/', 'https://docs.google.com:22/', 'ftp://example.com/x.csv' ) as $u ) {
	$r = DS_Table_Data::fetch( $u );
	$is( "blocked: $u", is_wp_error( $r ) ? $r->get_error_code() : 'fetched', 'ds_table_blocked' );
}
$r = DS_Table_Data::fetch( 'https://httpbin.org/redirect-to?url=http%3A%2F%2F127.0.0.1%2F&status_code=302' );
$is( 'a public link that redirects to an internal address is blocked at the redirect', is_wp_error( $r ) ? $r->get_error_code() : 'fetched (' . wp_remote_retrieve_response_code( $r ) . ')', 'ds_table_blocked' );
$pub = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQ8uskmgID2potRGsONj59u5GBB9MCMLgkEL26KlL6wdza7DH8lFArowtKM4bWNNx8OrLwcZ6QfZwp8/pub?output=csv';
$g   = DS_Table_Data::url_rows( $pub, 60, true );
$is( 'a published Google Sheet still loads through its redirect (5 rows)', array( count( $g['rows'] ?? array() ), $g['error'] ?? '' ), array( 5, '' ) );
$e   = DS_Table_Data::url_rows( 'http://127.0.0.1/secret.csv', 60, true );
$is( 'a private address is refused before any request', $e['error'] ?? '', 'Enter a full, public link starting with https://.' );
$e   = DS_Table_Data::url_rows( 'https://httpbin.org/redirect-to?url=http%3A%2F%2F169.254.169.254%2F&status_code=302', 60, true );
$is( 'a redirect to an internal address: a plain message, no network detail', $e['error'] ?? '', 'That address is not allowed. Use a public https:// link to a CSV file or a Google Sheet.' );

// Visitors never wait once a copy exists
$c = DS_Table_Data::url_rows( $pub, 60, false, false );
$is( 'a visitor render with a kept copy returns it without fetching', array( count( $c['rows'] ?? array() ), ! empty( $c['stale'] ) || isset( $c['fetched'] ) ), array( 5, true ) );

// CSV export: formulas made inert, numbers untouched
$csv = DS_Table_Data::to_csv( array( 'cols' => array( array( 'label' => 'A' ), array( 'label' => 'B' ) ), 'rows' => array( array( '=HYPERLINK("http://x")', '-3' ), array( '@SUM(A1)', '+1' ) ) ) );
$is( 'export: formula cells start with an apostrophe, numbers stay numbers', $csv, "A,B\n\"'=HYPERLINK(\"\"http://x\"\")\",-3\n'@SUM(A1),+1\n" );

// Upload: a .csv carrying PHP is refused
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new Exception( 'die' ); }; } );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]; wp_set_current_user( $admin->ID );
$tmp = wp_tempnam( 'x.csv' ); file_put_contents( $tmp, "Name,Age\n<?php system(\$_GET['c']); ?>,1\n" );
$_FILES = array( 'file' => array( 'name' => 'roster.csv', 'type' => 'text/csv', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize( $tmp ) ) );
$_POST = $_REQUEST = array( 'nonce' => wp_create_nonce( 'ds_table' ), 'store' => '0' );
ob_start(); try { do_action( 'wp_ajax_ds_table_upload' ); } catch ( Exception $x ) {} $j = json_decode( ob_get_clean(), true );
$is( 'upload: a .csv containing PHP is refused', array( $j['success'] ?? null, $j['data']['message'] ?? '' ), array( false, 'That file contains code, not table data.' ) );
@unlink( $tmp ); $_FILES = array();
delete_transient( 'ds_table_rate_' . $admin->ID );

echo $bad ? "FAILED $bad of " . ( $ok + $bad ) . "\n" : "ALL $ok PASS\n";
