<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * The WordPress update path verifies release signatures (1.11.0). Stub-style, no WordPress, no network:
 *
 *   php tests/updater-signature-test.php
 *
 * The real path was also proven end to end on a throwaway site (signed 1.10.49 installs; a wrong key is refused on
 * both `wp plugin update` and `wp plugin install <url>`, files unchanged); this keeps the rules from drifting.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DS_TOOLKIT_VERSION', '1.11.0' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 );
define( 'WP_ENVIRONMENT_TYPE', 'production' );
class WP_Error { public $c; public $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } }
function is_wp_error( $e ) { return $e instanceof WP_Error; }
function get_site_url() { return 'https://club.example.org'; }
function wp_remote_get( $u, $a = array() ) { $GLOBALS['got'][] = $u; return $GLOBALS['answer'] ?? new WP_Error( 'x', 'offline' ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function download_url( $u, $t = 300 ) { return $GLOBALS['zip'] ?? new WP_Error( 'x', 'no zip' ); }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t = 0 ) { return true; }
require DS_TOOLKIT_PATH . 'includes/class-ds-toolkit-updater.php';

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };
$u   = new DS_Toolkit_Updater();
$dl  = new ReflectionMethod( $u, 'get_download_url' ); if ( PHP_VERSION_ID < 80100 ) { $dl->setAccessible( true ); }
$rel = 'https://github.com/Design-Shop-LeagueApps-Department/ds-toolkit/releases/download/v1.11.0/';
$zip = array( 'name' => 'ds-toolkit.zip', 'browser_download_url' => $rel . 'ds-toolkit.zip' );
$sig = array( 'name' => 'ds-toolkit.zip.sig', 'browser_download_url' => $rel . 'ds-toolkit.zip.sig' );

$is( 'a signed release is offered', $dl->invoke( $u, array( 'tag_name' => 'v1.11.0', 'assets' => array( $zip, $sig ) ) ), $rel . 'ds-toolkit.zip' );
$is( 'a release not signed yet is not offered (production)', $dl->invoke( $u, array( 'tag_name' => 'v1.11.0', 'assets' => array( $zip ) ) ), '' );
$is( 'a zip at any other address is never offered', $dl->invoke( $u, array( 'tag_name' => 'v1.11.0', 'assets' => array( array( 'name' => 'ds-toolkit.zip', 'browser_download_url' => 'https://evil.example/ds-toolkit.zip' ), $sig ) ) ), '' );

$vp = array( $u, 'verify_package' );
$is( 'a local zip (the HQ path, a manual upload) passes through untouched', call_user_func( $vp, false, '/tmp/x.zip' ), false );
$is( 'another plugin\'s package passes through untouched', call_user_func( $vp, false, 'https://downloads.wordpress.org/plugin/akismet.zip' ), false );
$is( 'a reply another filter already made is kept', call_user_func( $vp, '/tmp/other.zip', $rel . 'ds-toolkit.zip' ), '/tmp/other.zip' );
$GLOBALS['answer'] = array( 'code' => 404, 'body' => '' );
$r = call_user_func( $vp, false, $rel . 'ds-toolkit.zip' );
$is( 'our release without a signature is refused before any download', array( is_wp_error( $r ), $r instanceof WP_Error ? $r->get_error_code() : '', count( $GLOBALS['got'] ) ), array( true, 'ds_toolkit_unsigned', 1 ) );

echo $fails ? "$fails of $n FAILED\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
