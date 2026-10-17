<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * After a toolkit update, Beaver Builder's cached page CSS/JS is cleared exactly once
 * (so new module markup never runs with old cached scripts), and never again until the
 * next version.
 *
 *   php tests/builder-cache-clear-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_VERSION', '9.9.9' );
$GLOBALS['opts'] = array(); $GLOBALS['cleared'] = 0; $GLOBALS['actions'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function do_action( $h ) { $GLOBALS['actions'][] = $h; }
// Only the method under test: copy it out of the real class file so the test runs the shipped code.
$src = file_get_contents( __DIR__ . '/../includes/class-ds-toolkit.php' );
preg_match( '/public static function maybe_clear_builder_cache\(\) \{.*?\n    \}\n/s', $src, $m );
eval( 'class DS_Toolkit_Under_Test { ' . $m[0] . ' }' );

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-66s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

DS_Toolkit_Under_Test::maybe_clear_builder_cache();
chk( 'no Beaver Builder: nothing cleared', $GLOBALS['cleared'], 0 );
chk( 'no Beaver Builder: version still recorded', $GLOBALS['opts']['ds_toolkit_assets_version'] ?? null, '9.9.9' );
eval( 'class FLBuilderModel { public static function delete_asset_cache_for_all_posts() { $GLOBALS["cleared"]++; } }' ); // declared now, not at compile time
$GLOBALS['opts']['ds_toolkit_assets_version'] = '9.9.8';
DS_Toolkit_Under_Test::maybe_clear_builder_cache();
chk( 'version changed: BB asset cache cleared', $GLOBALS['cleared'], 1 );
chk( '... and fl_builder_cache_cleared fired (host page cache purge)', $GLOBALS['actions'], array( 'fl_builder_cache_cleared' ) );
chk( '... and the new version recorded', $GLOBALS['opts']['ds_toolkit_assets_version'], '9.9.9' );
for ( $i = 0; $i < 5; $i++ ) { DS_Toolkit_Under_Test::maybe_clear_builder_cache(); }
chk( 'same version on later requests: never cleared again', $GLOBALS['cleared'], 1 );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
