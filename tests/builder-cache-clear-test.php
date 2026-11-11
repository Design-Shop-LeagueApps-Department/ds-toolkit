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
// A stand-in plugin folder, so the test can change a module file and see what that does.
define( 'DS_TOOLKIT_PATH', sys_get_temp_dir() . '/ds-cache-clear-test-' . getmypid() . '/' );
@mkdir( DS_TOOLKIT_PATH . 'modules/ds-x/css', 0755, true ); @mkdir( DS_TOOLKIT_PATH . 'includes', 0755, true ); @mkdir( DS_TOOLKIT_PATH . 'features', 0755, true );
file_put_contents( DS_TOOLKIT_PATH . 'modules/ds-x/css/frontend.css', '.a{color:red}' );
file_put_contents( DS_TOOLKIT_PATH . 'includes/class-ds-module-ui.php', '<?php // ui' );
file_put_contents( DS_TOOLKIT_PATH . 'includes/release-hashes.txt', 'changes every release' );
file_put_contents( DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php', '<?php // tripwire' );
$GLOBALS['opts'] = array(); $GLOBALS['cleared'] = 0; $GLOBALS['actions'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { if ( ! empty( $GLOBALS['ro'] ) ) { return false; } if ( ( $GLOBALS['opts'][ $k ] ?? null ) === $v ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function do_action( $h ) { $GLOBALS['actions'][] = $h; }
// Only the method under test: copy it out of the real class file so the test runs the shipped code.
$src = file_get_contents( __DIR__ . '/../includes/class-ds-toolkit.php' );
preg_match( '/public static function maybe_clear_builder_cache\(\) \{.*?\n    \}\n/s', $src, $m );
preg_match( '/public static function assets_fingerprint\(\) \{.*?\n    \}\n/s', $src, $m2 );
eval( 'class DS_Toolkit_Under_Test { ' . $m[0] . $m2[0] . ' }' );

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
// A database that refuses the write must not turn this into a clear on every request.
$GLOBALS['opts']['ds_toolkit_assets_version'] = '9.9.7'; $GLOBALS['ro'] = true; $GLOBALS['cleared'] = 0;
for ( $i = 0; $i < 3; $i++ ) { DS_Toolkit_Under_Test::maybe_clear_builder_cache(); }
chk( 'option cannot be saved: never clears (no clear-every-request loop)', $GLOBALS['cleared'], 0 );

// Since the 2026-11 audit: a new version whose module and shared files are unchanged clears nothing.
unset( $GLOBALS['ro'] );
$GLOBALS['cleared'] = 0; $GLOBALS['actions'] = array();
$GLOBALS['opts']['ds_toolkit_assets_version'] = '9.9.8'; unset( $GLOBALS['opts']['ds_toolkit_assets_print'] );
DS_Toolkit_Under_Test::maybe_clear_builder_cache();
chk( 'first update to a version that fingerprints: cleared once, fingerprint saved', array( $GLOBALS['cleared'], 32 === strlen( (string) ( $GLOBALS['opts']['ds_toolkit_assets_print'] ?? '' ) ) ), array( 1, true ) );
$GLOBALS['opts']['ds_toolkit_assets_version'] = '9.9.8';
file_put_contents( DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php', '<?php // tripwire, changed' );
file_put_contents( DS_TOOLKIT_PATH . 'includes/release-hashes.txt', 'changed too' );
DS_Toolkit_Under_Test::maybe_clear_builder_cache();
chk( 'next version changed only a feature file and the hash list: nothing cleared, no page-cache purge', array( $GLOBALS['cleared'], count( $GLOBALS['actions'] ), $GLOBALS['opts']['ds_toolkit_assets_version'] ), array( 1, 1, '9.9.9' ) );
$GLOBALS['opts']['ds_toolkit_assets_version'] = '9.9.8';
file_put_contents( DS_TOOLKIT_PATH . 'modules/ds-x/css/frontend.css', '.a{color:blue}' );
DS_Toolkit_Under_Test::maybe_clear_builder_cache();
chk( 'a version that changed a module stylesheet: cleared', $GLOBALS['cleared'], 2 );
$GLOBALS['opts']['ds_toolkit_assets_version'] = '9.9.8';
file_put_contents( DS_TOOLKIT_PATH . 'includes/class-ds-module-ui.php', '<?php // ui, changed' );
DS_Toolkit_Under_Test::maybe_clear_builder_cache();
chk( 'a version that changed a shared class the module CSS calls: cleared', $GLOBALS['cleared'], 3 );
$GLOBALS['opts']['ds_toolkit_assets_version'] = '9.9.8';
file_put_contents( DS_TOOLKIT_PATH . 'modules/ds-x/new.js', 'x()' );
DS_Toolkit_Under_Test::maybe_clear_builder_cache();
chk( 'a version that added a module script: cleared', $GLOBALS['cleared'], 4 );
$GLOBALS['opts']['ds_toolkit_assets_version'] = '9.9.8';
rename( DS_TOOLKIT_PATH . 'modules', DS_TOOLKIT_PATH . 'modules-gone' );
DS_Toolkit_Under_Test::maybe_clear_builder_cache();
chk( 'the folder cannot be read: cleared, as before (never skips on doubt)', $GLOBALS['cleared'], 5 );
rename( DS_TOOLKIT_PATH . 'modules-gone', DS_TOOLKIT_PATH . 'modules' );
foreach ( array( 'modules/ds-x/css/frontend.css', 'modules/ds-x/new.js', 'includes/class-ds-module-ui.php', 'includes/release-hashes.txt', 'features/class-ds-tripwire.php' ) as $f ) { @unlink( DS_TOOLKIT_PATH . $f ); }
foreach ( array( 'modules/ds-x/css', 'modules/ds-x', 'modules', 'includes', 'features', '' ) as $d ) { @rmdir( DS_TOOLKIT_PATH . $d ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
