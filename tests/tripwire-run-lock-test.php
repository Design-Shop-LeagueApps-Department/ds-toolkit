<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
// Run: php tests/tripwire-run-lock-test.php        (plain PHP, no WordPress, no network)
//
// Guards the Tripwire run rules from the 2026-11 audit (item 24):
//   - one run at a time: a second hourly scan leaves without touching the state, the daily checks come back
//     in five minutes instead of running into a scan, a run that died frees its lock after LOCK_TTL;
//   - no lost updates: the hourly scan saves ONLY its own part of the state, and the daily checks keep what
//     the scan and the new-administrator note saved while they ran;
//   - the whole hourly run stays inside a ceiling and the host's time limit, and records what it cost;
//   - where the scan got to is saved right after the scan loop, not only at the very end.

error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
$ROOT = sys_get_temp_dir() . '/tw-run-lock-' . getmypid();
define( 'WP_CONTENT_DIR', $ROOT . '/wp-content' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DS_TOOLKIT_VERSION', '9.9.9' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['tw_opts'] = array(); $GLOBALS['tw_mail'] = array(); $GLOBALS['tw_cron'] = array(); $GLOBALS['tw_on_users'] = null;
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
function wp_remote_retrieve_header() { return ''; }
function wp_mail( $to, $s, $b ) { $GLOBALS['tw_mail'][] = array( 'subject' => $s, 'body' => $b ); return true; }
function get_bloginfo() { return 'test'; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function site_url( $p = '' ) { return home_url( $p ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function esc_html( $s ) { return $s; }
function __( $s ) { return $s; }
function current_time( $t ) { return time(); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function is_email( $e ) { return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
// The daily checks list the administrators: the hook lets a test change the saved state in the MIDDLE of a run.
function get_users( $a = array() ) { if ( $GLOBALS['tw_on_users'] ) { $f = $GLOBALS['tw_on_users']; $GLOBALS['tw_on_users'] = null; $f(); } return array(); }
function get_plugins() { return array(); }
function get_stylesheet_directory() { return WP_CONTENT_DIR . '/themes/child'; }
function get_template_directory() { return WP_CONTENT_DIR . '/themes/parent'; }
function get_stylesheet() { return 'child'; }
function get_template() { return 'parent'; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['tw_cron'][] = array( $t, $h ); return true; }
// An options table good enough for the lock: INSERT IGNORE, the compare-and-swap UPDATE, DELETE, one SELECT.
class TW_LockDB {
	public $prefix = 'wp_'; public $posts = 'wp_posts'; public $options = 'wp_options'; public $rows = array();
	public function get_col() { return array(); }
	public function prepare( $q, ...$a ) { return array( $q, $a ); }
	public function query( $q ) { list( $sql, $a ) = $q;
		if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) { if ( isset( $this->rows[ $a[0] ] ) ) { return 0; } $this->rows[ $a[0] ] = $a[1]; return 1; }
		if ( 0 === strpos( $sql, 'UPDATE' ) ) { if ( ( $this->rows[ $a[1] ] ?? null ) === $a[2] ) { $this->rows[ $a[1] ] = $a[0]; return 1; } return 0; }
		if ( 0 === strpos( $sql, 'DELETE' ) ) { unset( $this->rows[ $a[0] ] ); return 1; }
		return 0; }
	public function get_var( $q ) { return is_array( $q ) ? ( $this->rows[ $q[1][0] ] ?? null ) : null; }
}
$wpdb = new TW_LockDB();
$GLOBALS['wpdb'] = $wpdb;

require DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php';

$ok = 0; $bad = 0;
$is = function ( $n, $a, $b ) use ( &$ok, &$bad ) {
	if ( $a === $b ) { $ok++; echo "  ok   $n\n"; }
	else { $bad++; echo "  FAIL $n: got " . var_export( $a, true ) . ' want ' . var_export( $b, true ) . "\n"; }
};
$LOCK = DS_Tripwire::LOCK_OPT;

// a small web root with ordinary files
foreach ( array( '/wp-content/plugins/akismet', '/wp-content/themes/child', '/wp-content/mu-plugins', '/wp-content/uploads', '/wp-content/upgrade' ) as $d ) { mkdir( $ROOT . $d, 0755, true ); }
for ( $i = 0; $i < 12; $i++ ) { file_put_contents( $ROOT . "/wp-content/plugins/akismet/file-$i.php", "<?php\nfunction akismet_part_$i() { return $i; }\n" ); }
file_put_contents( $ROOT . '/wp-content/themes/child/functions.php', "<?php\nadd_action( 'init', 'x' );\n" );

$tw = new DS_Tripwire( array() );

echo "one run at a time\n";
$wpdb->rows[ $LOCK ] = (string) time(); // another run holds the lock
$GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ] = array( 'seeded' => 1, 'marker' => 'untouched', 'content' => array( 'cursor' => 7 ) );
$before = $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ];
$is( 'a second hourly scan returns at once', $tw->run_content_scan(), array() );
$is( '... and leaves the state exactly as it was', $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ], $before );
$is( 'the daily checks do not run into it either', $tw->run_checks(), array() );
$is( '... they come back in five minutes', array( count( $GLOBALS['tw_cron'] ), $GLOBALS['tw_cron'][0][1] ?? '', ( $GLOBALS['tw_cron'][0][0] ?? 0 ) - time() ), array( 1, DS_Tripwire::CRON_HOOK, 300 ) );
$is( '... and the state is still untouched', $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ], $before );
$is( 'the other run\'s lock is left alone', isset( $wpdb->rows[ $LOCK ] ), true );

echo "a run that died\n";
$wpdb->rows[ $LOCK ] = (string) ( time() - DS_Tripwire::LOCK_TTL - 5 );
$GLOBALS['tw_cron'] = array();
$found = $tw->run_content_scan();
$c     = $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ]['content'] ?? array();
$is( 'its lock is taken over after LOCK_TTL and the scan runs', array( is_array( $found ), ! empty( $c['last_run'] ), (int) ( $c['in_progress'] ?? 1 ) ), array( true, true, 0 ) );
$is( 'the lock is released when the run ends', isset( $wpdb->rows[ $LOCK ] ), false );

echo "the hourly scan saves only its own part\n";
$is( 'everything else in the state is still there', array( $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ]['seeded'] ?? null, $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ]['marker'] ?? null ), array( 1, 'untouched' ) );
$save = new ReflectionMethod( 'DS_Tripwire', 'save_content' );
if ( PHP_VERSION_ID < 80100 ) { $save->setAccessible( true ); }
$GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ]['admin_log'] = array( 'written by another request meanwhile' );
$save->invoke( null, array( 'cursor' => 99 ) );
$st = $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ];
$is( 'a note saved by another request while the scan ran survives the scan\'s save', array( $st['admin_log'], $st['content'], $st['marker'] ), array( array( 'written by another request meanwhile' ), array( 'cursor' => 99 ), 'untouched' ) );

echo "what a run costs is recorded, and it stays inside its ceiling\n";
$tw->run_content_scan();
$stats = $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ]['content']['stats'] ?? array();
$is( 'stats carry the prep time, the scan allowance, the integrity time, the whole run and the ceiling', array_values( array_diff( array( 'prep_s', 'scan_for', 'integrity_s', 'run_s', 'ceiling' ), array_keys( $stats ) ) ), array() );
$is( 'with quick pre-work the scan still gets its full ' . DS_Tripwire::CONTENT_BUDGET_S . ' s', $stats['scan_for'] ?? null, DS_Tripwire::CONTENT_BUDGET_S );
$is( 'all ' . ( $stats['total'] ?? 0 ) . ' files were scanned and the cursor is back at the start', array( ( $stats['scanned'] ?? 0 ) >= 13, $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ]['content']['cursor'] ?? null ), array( true, 0 ) );
$ceil = new ReflectionMethod( 'DS_Tripwire', 'run_ceiling' );
if ( PHP_VERSION_ID < 80100 ) { $ceil->setAccessible( true ); }
$t0 = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
ini_set( 'max_execution_time', '0' );
$is( 'no host limit (CLI): the ceiling is RUN_CEILING_S', $ceil->invoke( null ), DS_Tripwire::RUN_CEILING_S );
ini_set( 'max_execution_time', '30' ); $_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
$is( 'a 30 s host: 5 s are kept in hand, so the ceiling is 25 or just under', in_array( $ceil->invoke( null ), array( 24, 25 ), true ), true );
ini_set( 'max_execution_time', '20' ); $_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 4;
$is( 'a 20 s host with 4 s already used: about 11 s left for the run', in_array( $ceil->invoke( null ), array( 10, 11 ), true ), true );
ini_set( 'max_execution_time', '8' ); $_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 6;
$is( 'never less than 6 s (the scan always moves on)', $ceil->invoke( null ), 6 );
ini_set( 'max_execution_time', '0' ); if ( null !== $t0 ) { $_SERVER['REQUEST_TIME_FLOAT'] = $t0; }

echo "the daily checks keep what was saved while they ran\n";
$GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ] = array( 'seeded' => 1, 'content' => array( 'cursor' => 1 ), 'admin_log' => array( 'old' ) );
$GLOBALS['tw_on_users'] = function () { // in the middle of the daily run: the hourly scan and a new-admin note save their parts
	$GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ]['content']   = array( 'cursor' => 500, 'findings' => array( 'CRIT: something the scan just found' ) );
	$GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ]['admin_log'] = array( 'old', 'new admin added mid-run' );
};
$tw->run_checks();
$st = $GLOBALS['tw_opts'][ DS_Tripwire::STATE_OPT ];
$is( 'the daily run saved its own result', array( ! empty( $st['last_run'] ), isset( $st['last_findings'] ) ), array( true, true ) );
$is( '... and did not put the old scan result back over the new one', $st['content'], array( 'cursor' => 500, 'findings' => array( 'CRIT: something the scan just found' ) ) );
$is( '... nor lose the new-administrator note', $st['admin_log'], array( 'old', 'new admin added mid-run' ) );
$is( 'the lock is free again', isset( $wpdb->rows[ $LOCK ] ), false );

// clean up the throwaway tree
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $ROOT, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $it as $f ) { $f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); }
@rmdir( $ROOT );

echo "\ntripwire-run-lock: $ok passed, $bad failed\n";
exit( $bad ? 1 : 0 );
