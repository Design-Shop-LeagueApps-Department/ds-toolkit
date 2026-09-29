<?php
// Run: php tests/tripwire-stale-staging-test.php      (plain PHP 7.4+, no WordPress, no network)
//
// Guards DS_Tripwire::check_stale_staging() and its wiring into run_checks().
//
// Why the rule exists: the WP Engine installer stages a release in
// wp-content/upgrade/.dstk-new.<pid> and sets the outgoing copy aside as .dstk-prev.<pid>. A run
// killed mid-install never fires its EXIT trap, so the folder stays. After the two 2026-09-28/29
// fixes NOTHING reported that: the content scan skips /wp-content/upgrade/, and 1.10.19 ignores
// byte-identical copies of our own engine wherever they sit. A silent leftover is the one outcome
// we were told not to have.
//
// The tests that matter most are the NEGATIVE ones. A rule that fires on ds-toolkit itself, on a
// fresh staging folder, or on a name that merely starts the same way would page the team during
// every normal install, and an alert that cries wolf is worse than no alert.

error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
$ROOT = sys_get_temp_dir() . '/tw-stale-staging-' . getmypid();
define( 'WP_CONTENT_DIR', $ROOT . '/wp-content' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['tw_opts'] = array();
$GLOBALS['tw_mail'] = array();
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
function get_users( $a = array() ) { return array(); }
function get_plugins() { return array(); }
function get_stylesheet_directory() { return WP_CONTENT_DIR . '/themes/child'; }
function get_template_directory() { return WP_CONTENT_DIR . '/themes/parent'; }
function get_stylesheet() { return 'child'; }
function get_template() { return 'parent'; }
// scan_root() reads published page slugs to tell a real page from a doorway directory.
class TW_FakeDB { public $prefix = 'wp_'; public $posts = 'wp_posts'; public function get_col() { return array(); } public function prepare( $q ) { return $q; } }
$GLOBALS['wpdb'] = new TW_FakeDB();

require DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php';

$ok = 0; $bad = 0;
$is = function ( $n, $a, $b ) use ( &$ok, &$bad ) {
	if ( $a === $b ) { $ok++; echo "  ok   $n\n"; }
	else { $bad++; echo "  FAIL $n: got " . var_export( $a, true ) . ' want ' . var_export( $b, true ) . "\n"; }
};

// ---- a throwaway web root ------------------------------------------------------------------------
$PL = WP_CONTENT_DIR . '/plugins';
$UP = WP_CONTENT_DIR . '/upgrade';
@mkdir( $PL, 0777, true );
@mkdir( $UP, 0777, true );
@mkdir( WP_CONTENT_DIR . '/uploads', 0777, true );
@mkdir( WP_CONTENT_DIR . '/mu-plugins', 0777, true );

$H = 3600;
$mk = function ( $path, $age_secs ) {
	@mkdir( $path, 0777, true );
	touch( $path, time() - $age_secs );
};

// MUST be reported
$mk( $UP . '/.dstk-new.33',   2 * $H );          // the real case from the 1.10.16 push
$mk( $PL . '/.dstk-prev.98',  50 * $H );         // days old, in plugins/
// MUST be silent
$mk( $UP . '/.dstk-new.77',   10 * 60 );         // 10 min: an install running right now
$mk( $PL . '/ds-toolkit',     99 * $H );         // the plugin itself
$mk( $PL . '/.dstk-newer',    99 * $H );         // name starts the same, is not staging
$mk( $UP . '/.dstk-new.abc',  99 * $H );         // no pid: not a shape the installer makes or deletes
$mk( $PL . '/akismet',        99 * $H );         // an ordinary plugin
touch( $PL . '/.dstk-new.55', time() - 99 * $H );   // a FILE wearing a staging name

$tw  = new DS_Tripwire( array() );
$m   = new ReflectionMethod( 'DS_Tripwire', 'check_stale_staging' );
$m->setAccessible( true );

$state = array();
$out   = $m->invokeArgs( $tw, array( &$state ) );
$txt   = implode( ' || ', array_map( function ( $p ) { return $p[0] . ': ' . $p[1]; }, $out ) );
$hit   = function ( $needle ) use ( $txt ) { return false !== strpos( $txt, $needle ); };

echo "-- what it reported --\n";
foreach ( $out as $p ) { echo "     {$p[0]}: {$p[1]}\n"; }

echo "-- positive: a stale folder in each of the two locations --\n";
$is( 'reports exactly the two stale folders', count( $out ), 2 );
$is( 'reports upgrade/.dstk-new.33',  $hit( 'wp-content/upgrade/.dstk-new.33' ), true );
$is( 'reports plugins/.dstk-prev.98', $hit( 'wp-content/plugins/.dstk-prev.98' ), true );
$is( 'tier is HIGH, never CRITICAL',  $hit( 'CRITICAL' ), false );
$is( 'both rows are HIGH on a first sighting', count( array_filter( $out, function ( $p ) { return 'HIGH' === $p[0]; } ) ), 2 );
$is( 'message names the age', $hit( '(2 h old)' ), true );
$is( 'age past two days reads in days', $hit( '(2 days old)' ), true );
$is( 'message says what to do', $hit( 'An update was interrupted; delete the folder.' ), true );

echo "-- negative: every near miss stays silent --\n";
$is( 'a 10-minute-old folder is silent (install in progress)', $hit( '.dstk-new.77' ), false );
$is( 'ds-toolkit itself is never reported',                    $hit( 'ds-toolkit' ), false );
$is( 'an ordinary plugin is never reported',                   $hit( 'akismet' ), false );
$is( '.dstk-newer is not a staging folder',                    $hit( '.dstk-newer' ), false );
$is( '.dstk-new.abc has no pid, so it is not ours',            $hit( '.dstk-new.abc' ), false );
$is( 'a FILE wearing a staging name is not a leftover',        $hit( '.dstk-new.55' ), false );

echo "-- the once-a-day throttle --\n";
$is( 'first run recorded both folders in state', count( (array) $state['staging_seen'] ), 2 );
$out2 = $m->invokeArgs( $tw, array( &$state ) );
$is( 'same day: still found', count( $out2 ), 2 );
$is( 'same day: downgraded to REVIEW, so no second email', count( array_filter( $out2, function ( $p ) { return 'REVIEW' === $p[0]; } ) ), 2 );
foreach ( $state['staging_seen'] as $k => $v ) { $state['staging_seen'][ $k ] = time() - 2 * 86400; }
$out3 = $m->invokeArgs( $tw, array( &$state ) );
$is( 'a day later: HIGH again', count( array_filter( $out3, function ( $p ) { return 'HIGH' === $p[0]; } ) ), 2 );

echo "-- state cannot grow without bound --\n";
$state['staging_seen']['wp-content/upgrade/.dstk-new.4242'] = time();
$m->invokeArgs( $tw, array( &$state ) );
$is( 'a folder that is gone is dropped from state', isset( $state['staging_seen']['wp-content/upgrade/.dstk-new.4242'] ), false );

echo "-- wired into run_checks, and it reaches the mail --\n";
$GLOBALS['tw_opts'] = array( 'ds_tripwire_state' => array( 'seeded' => 1 ) );
$GLOBALS['tw_mail'] = array();
$f = $tw->run_checks();
$all = implode( ' || ', array_map( function ( $p ) { return $p[0] . ': ' . $p[1]; }, $f ) );
$is( 'run_checks includes the leftover finding', false !== strpos( $all, '.dstk-new.33' ), true );
$is( 'one alert email was sent', count( $GLOBALS['tw_mail'] ), 1 );
$subject = $GLOBALS['tw_mail'][0]['subject'] ?? '';
$body    = $GLOBALS['tw_mail'][0]['body'] ?? '';
$is( 'the email is HIGH, not CRITICAL', false !== strpos( $subject, 'HIGH' ), true );
$is( 'the email body names the folder', false !== strpos( $body, 'wp-content/upgrade/.dstk-new.33' ), true );

// ---- tidy up -------------------------------------------------------------------------------------
$rm = function ( $d ) use ( &$rm ) {
	foreach ( (array) glob( $d . '/{,.}[!.,..]*', GLOB_BRACE ) as $f ) { is_dir( $f ) ? $rm( $f ) : @unlink( $f ); }
	@rmdir( $d );
};
$rm( $ROOT );

echo "\ntripwire-stale-staging: {$ok} passed, {$bad} failed\n";
exit( $bad ? 1 : 0 );
