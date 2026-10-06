<?php
// Run: php tests/tripwire-repeat-test.php        (plain PHP 7.4+, no WordPress, no network)
//
// Guards WHEN the real content scan lets Design Shop HQ decide on an alert email (1.10.27). HQ may be asked only when
// EVERY mailed file was already emailed by this site before, with its full md5; never for a first sighting, a changed
// file, a mixed alert, a deny-listed (named) malware hash, or a path whose name could be misread in the alert line.
// DS_HQ_Link is a stand-in that records the ask and answers skip or send; everything else is the shipped scan.

error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
$ROOT = sys_get_temp_dir() . '/tw-repeat-' . getmypid();
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
function get_transient( $k ) { return $GLOBALS['tw_tr'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['tw_tr'][ $k ] = $v; return true; }
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
function wp_mail( $to, $s, $b ) { $GLOBALS['tw_mail'][] = $s; return true; }
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

function wp_doing_cron() { return true; }

/** Stand-in for the HQ link: records each ask, answers with $answer ( [ HQ took it, skip, why ] ). */
class DS_HQ_Link {
	public static $asks = array();
	public static $answer = array( true, true, 'repeat of event 7' );
	public static function ask_mail( $tier, array $lines, array $files ) { self::$asks[] = $files; return self::$answer; }
}

$deny_body = '<?php if(isset($_POST["k"])){ eval(base64_decode($_POST["k"])); } // named' . "\n";
$GLOBALS['tw_tr']['ds_tripwire_rl_deny'] = array( md5( $deny_body ) => 'Test family' );

require DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php';

$ok = 0; $bad = 0;
$is = function ( $n, $a, $b ) use ( &$ok, &$bad ) {
	if ( $a === $b ) { $ok++; echo "  ok   $n\n"; }
	else { $bad++; echo "  FAIL $n: got " . var_export( $a, true ) . ' want ' . var_export( $b, true ) . "\n"; }
};
@mkdir( WP_CONTENT_DIR . '/uploads', 0777, true );
$up   = WP_CONTENT_DIR . '/uploads/';
$tw   = new DS_Tripwire( array() );
$scan = function () use ( $tw ) {
	$GLOBALS['tw_mail'] = array();
	DS_HQ_Link::$asks   = array();
	$tw->run_content_scan( 60 );
	return array( count( $GLOBALS['tw_mail'] ), count( DS_HQ_Link::$asks ) );
};
$age = function () { // pretend the last emails went out two days ago, so today's scan emails again
	$s = get_option( 'ds_tripwire_state', array() );
	foreach ( $s['content']['alerted'] as $k => $t ) { $s['content']['alerted'][ $k ] = time() - 2 * DAY_IN_SECONDS; }
	update_option( 'ds_tripwire_state', $s );
};
$shell = function ( $tag ) { return '<?php if(isset($_POST["c"])){ eval(base64_decode($_POST["c"])); } // ' . $tag . "\n"; };

file_put_contents( $up . 'a.php', $shell( 'a' ) );
$is( 'first sighting: emailed, HQ not asked', $scan(), array( 1, 0 ) );
$is( 'same day again: no email, not asked', $scan(), array( 0, 0 ) );
$age();
$r = $scan();
$is( 'next day, repeat: HQ asked once, its signed skip honoured (no email)', $r, array( 0, 1 ) );
$f = DS_HQ_Link::$asks[0][0] ?? array();
$is( 'the ask carries the full md5', $f['md5'] ?? '', md5_file( $up . 'a.php' ) );
$is( 'and the path and size', array( $f['path'] ?? '', $f['size'] ?? -1 ), array( $up . 'a.php', filesize( $up . 'a.php' ) ) );
$age();
DS_HQ_Link::$answer = array( true, false, '' );
$is( 'repeat, HQ says send: emailed', $scan(), array( 1, 1 ) );
DS_HQ_Link::$answer = array( true, true, 'x' );

file_put_contents( $up . 'a.php', $shell( 'a-changed' ) );
$is( 'same path, new content: first sighting, not asked', $scan(), array( 1, 0 ) );
$age();
file_put_contents( $up . 'b.php', $shell( 'b' ) );
$is( 'repeat plus a new file: emailed, not asked', $scan(), array( 1, 0 ) );
$age();
$is( 'both repeats: asked', $scan()[1], 1 );
unlink( $up . 'a.php' ); unlink( $up . 'b.php' );

file_put_contents( $up . 'named.php', $deny_body );
$is( 'deny-listed file, first sighting: emailed', $scan(), array( 1, 0 ) );
$age();
$is( 'deny-listed file, repeat: emailed, HQ never asked', $scan(), array( 1, 0 ) );
unlink( $up . 'named.php' );

file_put_contents( $up . 'my shell (1).php', $shell( 'odd' ) );
$scan();
$age();
$is( 'path with space/parens, repeat: emailed, HQ never asked', $scan(), array( 1, 0 ) );
unlink( $up . 'my shell (1).php' );

$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $ROOT, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $it as $x ) { $x->isDir() ? rmdir( $x->getPathname() ) : unlink( $x->getPathname() ); }
rmdir( $ROOT );
echo "\n$ok passed, $bad failed\n";
exit( $bad ? 1 : 0 );
