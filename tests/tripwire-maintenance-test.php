<?php
// Run: php tests/tripwire-maintenance-test.php      (plain PHP 7.4+, no WordPress, no network)
//
// Guards the .maintenance carve-out in DS_Tripwire::scan_root().
//
// WordPress writes .maintenance when an update starts and deletes it when the update ends, so any
// scan landing in that window sees it legitimately. 3hlhockey.com, 2026-10-07: HIGH on a 33-byte
// .maintenance, which is exactly the length of core's own open-tag + $upgrading + epoch + close-tag.
// includes/ds-scan-engine.php already recognised the shape; scan_root() did not.
//
// The carve-out is by SHAPE, never by name, and these assertions are what keep it that way. If the
// malicious cases ever go quiet, "allow .maintenance" has become "allow anything called
// .maintenance" and must not ship.

error_reporting( E_ALL & ~E_DEPRECATED );
$ROOT = sys_get_temp_dir() . '/tw-maint-' . getmypid();
define( 'ABSPATH', $ROOT . '/' );
define( 'WP_CONTENT_DIR', $ROOT . '/wp-content' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

class WP_Error { public function get_error_message() { return 'offline test'; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['tw_opts'][ $k ] ) ? $GLOBALS['tw_opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['tw_opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['tw_opts'][ $k ] ); return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function apply_filters( $h, $v ) { return $v; }
function do_action() {}
function add_action() {}
function add_filter() {}
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function trailingslashit( $s ) { return untrailingslashit( $s ) . '/'; }
function wp_get_upload_dir() { return array( 'basedir' => WP_CONTENT_DIR . '/uploads' ); }
function wp_upload_dir() { return wp_get_upload_dir(); }
function wp_remote_get() { return new WP_Error(); }
function wp_safe_remote_get() { return new WP_Error(); }
function wp_remote_retrieve_response_code() { return 0; }
function wp_remote_retrieve_body() { return ''; }
function wp_mail() { return true; }
function get_bloginfo() { return 'test'; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function site_url( $p = '' ) { return home_url( $p ); }
$GLOBALS['tw_opts'] = array();

require_once dirname( __DIR__ ) . '/features/class-ds-tripwire.php';

@mkdir( $ROOT . '/wp-content', 0777, true );
$PASS = 0; $FAIL = 0;

/** Write one .maintenance body, scan the root, return the findings that name .maintenance. */
function maint( $body ) {
    global $ROOT;
    file_put_contents( $ROOT . '/.maintenance', $body );
    $hits = array();
    foreach ( (array) DS_Tripwire::scan_root( $ROOT, array() ) as $f ) {
        if ( false !== strpos( (string) $f[1], '.maintenance' ) ) { $hits[] = $f[0] . ': ' . $f[1]; }
    }
    unlink( $ROOT . '/.maintenance' );
    return $hits;
}
function ok( $cond, $label, $detail = '' ) {
    global $PASS, $FAIL;
    if ( $cond ) { $PASS++; } else { $FAIL++; echo "  FAIL $label" . ( $detail ? " -- $detail" : '' ) . "\n"; }
}

// Literal PHP tags are assembled, never written inline. This is not fussiness: a close-tag typed
// anywhere in this file, INCLUDING inside a // comment, ends PHP mode and the rest of the test is
// echoed as HTML while php -l still reports "no syntax errors". That exact trap cost a run here.
$O = '<' . '?php';
$C = '?' . '>';

// 1. Core's exact marker, the 33-byte form seen on 3hlhockey.com. Must be silent.
$marker = $O . ' $upgrading = 1790000000; ' . $C;
ok( 33 === strlen( $marker ), 'the marker under test is the 33 bytes the alert reported', strlen( $marker ) );
$h = maint( $marker );
ok( ! $h, "core's own .maintenance must not be reported", implode( ' | ', $h ) );

// 2. The variants core and its forks actually emit. All silent.
foreach ( array(
    $O . ' $upgrading = 1790000000; ' . $C . "\n",
    $O . ' $upgrading = 1790000000;',
    $O . "\t" . '$upgrading   =  1790000000 ;  ' . $C . '  ',
) as $i => $v ) {
    $h = maint( $v );
    ok( ! $h, "core .maintenance variant #$i must not be reported", implode( ' | ', $h ) );
}

// 3. THE CONTROL, and the reason this file exists: a shell wearing the name must STILL be reported.
//    Each of these is long enough to clear the is_executable_file() size floor.
foreach ( array(
    'shell'            => $O . ' eval($_POST["x"]); /* padding to clear the size floor ...... */',
    'appended payload' => $O . ' $upgrading = 1790000000; ' . $C . $O . ' system($_GET["c"]); /* */',
    'base64 loader'    => $O . ' eval(base64_decode($_REQUEST["z"])); /* padding ............. */',
) as $label => $v ) {
    $h = maint( $v );
    ok( (bool) $h, "a malicious .maintenance ($label) MUST still be reported", 'went silent' );
}

echo "tripwire .maintenance test: $PASS passed, $FAIL failed\n";
@rmdir( $ROOT . '/wp-content' ); @rmdir( $ROOT );
exit( $FAIL ? 1 : 0 );
