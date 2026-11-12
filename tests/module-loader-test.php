<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * One loader for the blocks that only need registering. Stub-style:  php tests/module-loader-test.php
 *
 * Why (2026-11 audit, item 32): fourteen feature classes were the same 18 to 28 lines but for a folder name.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
$root = dirname( __DIR__ ) . '/';
define( 'DS_TOOLKIT_PATH', $root );
$GLOBALS['hooks'] = array();
function add_action( $h, $cb, $p = 10 ) { $GLOBALS['hooks'][] = array( $h, $p ); }
function __( $s, $d = '' ) { return $s; }
require $root . 'includes/class-ds-env.php';
require $root . 'includes/class-ds-features.php';
require $root . 'includes/class-ds-module-loader.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-78s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

$reg     = DS_Features::registry();
$generic = array_filter( $reg, function ( $f ) { return isset( $f['block'] ); } );
chk( 'fourteen blocks use the shared loader', count( $generic ), 14 );
$bad = array();
foreach ( $generic as $key => $f ) {
	if ( 'DS_Module_Loader' !== $f['class'] || 'includes/class-ds-module-loader.php' !== $f['file'] ) { $bad[] = "$key: class or file"; }
	if ( ! is_file( $root . 'modules/' . $f['block'] . '/' . $f['block'] . '.php' ) ) { $bad[] = "$key: no block file for " . $f['block']; }
	if ( is_file( $root . 'features/class-' . $f['block'] . '.php' ) ) { $bad[] = "$key: its old loader class still ships"; }
	if ( 6 !== ( $f['min_blueprint'] ?? null ) ) { $bad[] = "$key: min_blueprint"; }
}
chk( 'each names a block folder that exists, and its old class is gone', $bad, array() );
$own = array_filter( $reg, function ( $f ) { return ! isset( $f['block'] ); } );
$miss = array();
foreach ( $own as $key => $f ) { if ( ! is_file( $root . $f['file'] ) ) { $miss[] = $key . ': ' . $f['file']; } }
chk( 'every other feature still has its own class file', $miss, array() );

( new DS_Module_Loader( 'ds-heading' ) )->init();
chk( 'it registers on init at priority 20, after Beaver Builder\'s own modules', $GLOBALS['hooks'], array( array( 'init', 20 ) ) );
( new DS_Module_Loader( 'ds-heading' ) )->register_module();
chk( 'without Beaver Builder it loads nothing', class_exists( 'DS_Heading_Module', false ), false );
$l = new DS_Module_Loader( '../../wp-config' ); $p = new ReflectionProperty( 'DS_Module_Loader', 'slug' ); $p->setAccessible( true );
chk( 'a slug can never be a path', $p->getValue( $l ), 'wp-config' );
$run = file_get_contents( $root . 'includes/class-ds-toolkit.php' );
chk( 'the start loop hands the loader its block name', false !== strpos( $run, 'isset( $feature[\'block\'] ) ? new $feature[\'class\']( $feature[\'block\'] ) : new $feature[\'class\']( $settings )' ), true );

echo "features that only act in wp-admin\n";
$admin_only = array_keys( array_filter( $reg, function ( $f ) { return ! empty( $f['admin_only'] ); } ) );
chk( 'three features are marked', $admin_only, array( 'admin_menu_tidy_enabled', 'ds_pattern_library_enabled', 'design_academy_enabled' ) );
$leaks = array();
foreach ( $admin_only as $key ) {
	$src = file_get_contents( $root . $reg[ $key ]['file'] );
	// Every hook the feature adds must be a wp-admin one, and it must not register a shortcode, a REST route,
	// a cron job or a WP-CLI command: any of those would silently stop working outside wp-admin.
	preg_match_all( "/add_(?:action|filter)\(\s*([^,]+),/", $src, $m );
	foreach ( $m[1] as $h ) {
		if ( ! preg_match( "/^'(admin_menu|admin_init|admin_enqueue_scripts|admin_notices|wp_dashboard_setup)'$|^'wp_ajax_' \. self::[A-Z_]+$/", trim( $h ) ) ) { $leaks[] = "$key hooks " . trim( $h ); }
	}
	if ( preg_match( '/add_shortcode|register_rest_route|wp_schedule_|WP_CLI::add_command|wp_ajax_nopriv/', $src ) ) { $leaks[] = "$key registers something that runs outside wp-admin"; }
}
chk( 'each of them hooks into wp-admin only', $leaks, array() );
chk( 'the start loop skips them outside wp-admin', false !== strpos( $run, 'if ( ! empty( $feature[\'admin_only\'] ) && ! is_admin() ) {' ), true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
