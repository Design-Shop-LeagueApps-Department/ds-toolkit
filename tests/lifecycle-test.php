<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * Deactivate and uninstall (audit 2026-11, item 10): one list of what the toolkit leaves on a site
 * (includes/class-ds-lifecycle.php), and the code cannot drift away from it. Stub-style:
 *
 *   php tests/lifecycle-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
$ROOT = dirname( __DIR__ );
$TMP  = sys_get_temp_dir() . '/ds-lifecycle-test-' . getmypid();
define( 'WPMU_PLUGIN_DIR', $TMP . '/mu-plugins' );
mkdir( WPMU_PLUGIN_DIR, 0755, true ); mkdir( $TMP . '/uploads/ds-la-roster/33682', 0755, true );
$GLOBALS['opts'] = array(); $GLOBALS['unscheduled'] = array(); $GLOBALS['sql'] = array();
function delete_option( $k ) { $had = array_key_exists( $k, $GLOBALS['opts'] ); unset( $GLOBALS['opts'][ $k ] ); return $had; }
function wp_unschedule_hook( $h ) { $GLOBALS['unscheduled'][] = $h; return 0; }
function wp_cache_flush() { return true; }
function wp_upload_dir( $a = null, $b = true ) { global $TMP; return array( 'basedir' => $TMP . '/uploads' ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
class FakeWpdb { public $options = 'wp_options';
	function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	function prepare( $q, $v ) { return str_replace( '%s', "'" . $v . "'", $q ); }
	function query( $q ) { $GLOBALS['sql'][] = $q; return 1; } }
$wpdb = new FakeWpdb();
require $ROOT . '/includes/class-ds-lifecycle.php';

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };

// ---- the code and the list agree
$src = '';
$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $ROOT, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	$p = str_replace( '\\', '/', $f->getPathname() );
	if ( 'php' !== $f->getExtension() || false !== strpos( $p, '/tests/' ) || false !== strpos( $p, '/.git/' ) || false !== strpos( $p, 'class-ds-lifecycle.php' ) ) { continue; }
	$src .= "\n" . file_get_contents( $p );
}
echo "the list covers what the code does\n";
$hooks = array();
// Every 'ds_...' name inside a wp_schedule_*() call, minus the names of custom intervals (ds_table_5min).
preg_match_all( "/wp_schedule_(?:single_)?event\\([^;]*;/", $src, $calls );
foreach ( $calls[0] as $call ) { preg_match_all( "/'(ds_[a-z0-9_]+)'/", $call, $m ); $hooks = array_merge( $hooks, $m[1] ); }
preg_match_all( "/'(ds_[a-z0-9_]+)'\\s*\\]\\s*=\\s*array\\(\\s*'interval'/", $src, $m );
$hooks = array_diff( $hooks, $m[1] );
preg_match_all( "/const [A-Z_]*HOOK[A-Z_]*\\s*=\\s*'(ds_[a-z0-9_]+)'/", $src, $m ); $hooks = array_merge( $hooks, $m[1] );
$hooks = array_values( array_unique( $hooks ) ); sort( $hooks );
$is( 'the scan found the scheduled jobs (' . count( $hooks ) . ')', count( $hooks ) >= 12, true );
$is( 'every scheduled job in the code is in CRON_HOOKS', array_values( array_diff( $hooks, DS_Toolkit_Lifecycle::CRON_HOOKS ) ), array() );
$is( 'CRON_HOOKS names no job the code no longer has', array_values( array_diff( DS_Toolkit_Lifecycle::CRON_HOOKS, $hooks ) ), array() );

// Options the toolkit writes: removed by name or prefix, or on the short list of site content that stays.
$kept = array( 'ds_button_style', 'ds_social_card_id', 'ds_social_card_url', 'ds_blueprint_version', 'ds_site_launched' );
$opts = array();
preg_match_all( "/(?:update|add)_option\\(\\s*'(ds_[a-z0-9_]+)'/", $src, $m ); $opts = array_merge( $opts, $m[1] );
preg_match_all( "/const [A-Z_]*(?:OPT|OPTION)[A-Z_]*\\s*=\\s*'(ds_[a-z0-9_]+)'/", $src, $m ); $opts = array_merge( $opts, $m[1] );
$opts = array_values( array_unique( $opts ) );
$loose = array();
foreach ( $opts as $o ) {
	if ( in_array( $o, DS_Toolkit_Lifecycle::OPTIONS, true ) || in_array( $o, $kept, true ) ) { continue; }
	$hit = false;
	foreach ( DS_Toolkit_Lifecycle::OPTION_PREFIXES as $pre ) { if ( 0 === strpos( $o, $pre ) || 0 === strpos( $pre, $o ) ) { $hit = true; } }
	if ( ! $hit ) { $loose[] = $o; }
}
$is( 'the scan found the options (' . count( $opts ) . ')', count( $opts ) >= 20, true );
$is( 'every option the code writes is removed on uninstall, or is site content that stays', $loose, array() );
$trans = array();
preg_match_all( "/set_transient\\(\\s*'(ds_[a-z0-9_]+)'/", $src, $m );
foreach ( array_unique( $m[1] ) as $t ) { $hit = false; foreach ( DS_Toolkit_Lifecycle::TRANSIENT_PREFIXES as $pre ) { if ( 0 === strpos( $t, $pre ) ) { $hit = true; } } if ( ! $hit ) { $trans[] = $t; } }
$is( 'every transient the code sets by name matches a removed prefix', $trans, array() );

// ---- deactivate
echo "deactivate\n";
$ours = WPMU_PLUGIN_DIR . '/ds-origin-guard.php';
file_put_contents( $ours, "<?php\n/**\n * Plugin Name: DS Origin Guard\n */\n" );
file_put_contents( WPMU_PLUGIN_DIR . '/someone-else.php', "<?php // not ours\n" );
$GLOBALS['opts'] = array( 'ds_origin_guard_state' => 'abc', 'ds_toolkit_settings' => array( 'x' => 1 ), 'ds_tripwire_state' => array( 'y' => 1 ) );
DS_Toolkit_Lifecycle::deactivate();
$is( 'every scheduled job is cleared, and the jobs of code that no longer ships', $GLOBALS['unscheduled'], array_merge( DS_Toolkit_Lifecycle::CRON_HOOKS, DS_Toolkit_Lifecycle::RETIRED_CRON_HOOKS ) );
$is( 'the Origin Guard mu-plugin is removed, a stranger\'s file is not', array( file_exists( $ours ), file_exists( WPMU_PLUGIN_DIR . '/someone-else.php' ) ), array( false, true ) );
$is( 'its fingerprint is forgotten, so reactivating writes it again', isset( $GLOBALS['opts']['ds_origin_guard_state'] ), false );
$is( 'settings and Tripwire state stay (reactivating restores the site)', array( isset( $GLOBALS['opts']['ds_toolkit_settings'] ), isset( $GLOBALS['opts']['ds_tripwire_state'] ) ), array( true, true ) );
file_put_contents( $ours, "<?php // a different plugin that happens to use the same file name\n" );
DS_Toolkit_Lifecycle::deactivate();
$is( 'a file with our name but without our marker is left alone', file_exists( $ours ), true );
unlink( $ours );

// ---- uninstall
echo "uninstall\n";
$GLOBALS['unscheduled'] = array();
foreach ( DS_Toolkit_Lifecycle::OPTIONS as $o ) { $GLOBALS['opts'][ $o ] = 1; }
foreach ( $kept as $o ) { $GLOBALS['opts'][ $o ] = 1; }
file_put_contents( $TMP . '/uploads/ds-la-roster/33682/a.jpg', 'x' ); file_put_contents( $TMP . '/uploads/ds-la-roster/33682/index.html', '' );
DS_Toolkit_Lifecycle::uninstall();
$is( 'every listed option is deleted', array_values( array_intersect( array_keys( $GLOBALS['opts'] ), DS_Toolkit_Lifecycle::OPTIONS ) ), array() );
$is( 'site content stays (' . implode( ', ', $kept ) . ')', array_keys( $GLOBALS['opts'] ), $kept );
$q = $GLOBALS['sql'][0] ?? '';
$is( 'one DELETE covers every option prefix and both rows of every transient prefix', array( count( $GLOBALS['sql'] ), substr_count( $q, 'option_name LIKE' ) ), array( 1, count( DS_Toolkit_Lifecycle::OPTION_PREFIXES ) + 2 * count( DS_Toolkit_Lifecycle::TRANSIENT_PREFIXES ) ) );
$is( '... with the underscores escaped (ds_table_ must not match "dsXtableY")', false !== strpos( $q, "'ds\\_table\\_last\\_%'" ) && false !== strpos( $q, "'\\_transient\\_timeout\\_ds\\_programs\\_%'" ), true );
$is( 'the jobs are cleared on uninstall too', $GLOBALS['unscheduled'], array_merge( DS_Toolkit_Lifecycle::CRON_HOOKS, DS_Toolkit_Lifecycle::RETIRED_CRON_HOOKS ) );
$is( 'downloaded roster photos and their folder are removed', is_dir( $TMP . '/uploads/ds-la-roster' ), false );

@unlink( WPMU_PLUGIN_DIR . '/someone-else.php' ); @rmdir( WPMU_PLUGIN_DIR ); @rmdir( $TMP . '/uploads' ); @rmdir( $TMP );
echo $fails ? "$fails of $n FAILED\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
