<?php
// Run: php tests/hq-mail-decision-test.php        (plain PHP 7.4+ with sodium, no WordPress, no network)
//
// Guards the repeat-alert email decision (DS_Tripwire::alert + DS_HQ_Link::ask_mail):
//   - only a repeat (every line a file already emailed, each with a full md5), from cron or WP-CLI, on an active link,
//     asks HQ; anything else emails at once and reports to HQ as before;
//   - the email is skipped ONLY on a 200 whose mail=skip is signed by HQ's key over this host and this run's nonce,
//     within 5 minutes; unsigned, wrong nonce, wrong host, wrong key, stale, HTTP error or no answer all email;
//   - HQ gets the alert exactly once either way; when the direct ask failed, it is queued as usual;
//   - every alert report carries each file's full md5.

error_reporting( E_ALL & ~E_DEPRECATED );
if ( 'cli' !== PHP_SAPI ) { exit; }
define( 'ABSPATH', sys_get_temp_dir() . '/dsmd-' . getmypid() . '/' );
define( 'WPINC', 'wp-includes' );
define( 'DB_NAME', 'db_test' );
define( 'DB_HOST', 'localhost' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
define( 'DS_TOOLKIT_VERSION', '1.10.27' );
$table_prefix = 'wp_';

$GLOBALS['o'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['mailed'] = array(); $GLOBALS['cron'] = array();
$GLOBALS['acts'] = array(); $GLOBALS['filters'] = array(); $GLOBALS['home'] = 'https://example-club.org'; $GLOBALS['ctx'] = 'cron';
class WP_Error { public $m; public function __construct( $c = '', $m = '' ) { $this->m = $m; } public function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o'] ) ? $GLOBALS['o'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['o'][ $k ] = $v; return true; }
function add_option( $k, $v, $d = '', $a = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['o'] ) ) { return false; } $GLOBALS['o'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['o'][ $k ] ); return true; }
function add_action( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['acts'][ $h ][] = $c; }
function do_action( $h, ...$a ) { foreach ( $GLOBALS['acts'][ $h ] ?? array() as $c ) { $c( ...$a ); } }
function add_filter( $h, $c, $p = 10, $n = 1 ) { $GLOBALS['filters'][ $h ][] = $c; }
function apply_filters( $h, $v, ...$a ) { foreach ( $GLOBALS['filters'][ $h ] ?? array() as $c ) { $v = $c( $v, ...$a ); } return $v; }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function home_url( $p = '' ) { return $GLOBALS['home'] . $p; }
function wp_login_url() { return $GLOBALS['home'] . '/wp-login.php'; }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_mail( $to, $s, $b ) { $GLOBALS['mailed'][] = array( $to, $s ); return $GLOBALS['mail_ok'] ?? true; }
function wp_remote_post( $url, $args ) {
	$body               = json_decode( $args['body'], true );
	$GLOBALS['sent'][]  = array( $url, $body );
	if ( isset( $GLOBALS['net_error'] ) ) { return new WP_Error( 'http', $GLOBALS['net_error'] ); }
	$reply = $GLOBALS['reply'] ?? array( 'ok' => true, 'status' => 'active' );
	if ( is_callable( $reply ) ) { $reply = $reply( $body ); }
	return array( 'code' => $GLOBALS['reply_code'] ?? 200, 'body' => json_encode( $reply ) );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $e = 0 ) { return true; }
function human_time_diff( $a, $b = 0 ) { return 'moments'; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['cron'][ $h ] ); }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $h ] ?? false; }
function wp_schedule_event() { return true; }
function wp_doing_cron() { return 'cron' === $GLOBALS['ctx']; }
function is_multisite() { return false; }
function get_current_blog_id() { return 1; }
function is_admin() { return false; }
function current_time( $t ) { return time(); }
function wp_rand( $a, $b ) { return $a; }
class WP_REST_Response { public $data; public function __construct( $d ) { $this->data = $d; } public function header( $k, $v ) {} }
function rest_ensure_response( $d ) { return new WP_REST_Response( $d ); }
$hqkp = sodium_crypto_sign_keypair();
define( 'DS_HQ_LINK_PUBKEY', base64_encode( sodium_crypto_sign_publickey( $hqkp ) ) );
$GLOBALS['hq_sk'] = sodium_crypto_sign_secretkey( $hqkp );

require dirname( __DIR__ ) . '/features/class-ds-tripwire.php';
$tw = new DS_Tripwire( array( 'tripwire_alert_email' => 'design@example.com' ) );
$tw->init();
$alert = new ReflectionMethod( 'DS_Tripwire', 'alert' );
$alert->setAccessible( true );

$fails = 0;
function ok( $c, $w ) { global $fails; echo ( $c ? 'ok   ' : 'FAIL ' ) . $w . "\n"; if ( ! $c ) { $fails++; } }
function reset_world() {
	$GLOBALS['sent'] = array(); $GLOBALS['mailed'] = array(); $GLOBALS['cron'] = array(); $GLOBALS['ctx'] = 'cron';
	unset( $GLOBALS['reply'], $GLOBALS['reply_code'], $GLOBALS['net_error'], $GLOBALS['mail_ok'], $GLOBALS['o']['ds_tripwire_last_skip'], $GLOBALS['o']['ds_hq_link_lock'] );
	$s = get_option( 'ds_hq_link' );
	$s['status'] = 'active'; $s['queue'] = array(); $s['error'] = '';
	update_option( 'ds_hq_link', $s );
}
/** HQ's skip reply, signed the way HQ signs it; each argument can be bent to make it wrong. */
function skip_reply( $nonce_override = null, $host = 'example-club.org', $t = null, $sk = null, $what = 'mail-skip' ) {
	return function ( $body ) use ( $nonce_override, $host, $t, $sk, $what ) {
		$n = null === $nonce_override ? (string) ( $body['hold'] ?? '' ) : $nonce_override;
		$t = null === $t ? time() : $t;
		$sig = sodium_crypto_sign_detached( "dshq-hq\n$what\n$host\n$t\n$n", $sk ?: $GLOBALS['hq_sk'] );
		return array( 'ok' => true, 'status' => 'active', 'mail' => 'skip', 'mail_why' => 'repeat of event 7', 'mail_t' => $t, 'mail_sig' => base64_encode( $sig ) );
	};
}
function reports() { return array_values( array_filter( $GLOBALS['sent'], function ( $x ) { return false !== strpos( $x[0], '/report' ); } ) ); }

// a connected site: key, active
$kp = sodium_crypto_sign_keypair();
update_option( 'ds_hq_link', array( 'sid' => DS_HQ_Link::install_id(), 'sk' => base64_encode( sodium_crypto_sign_secretkey( $kp ) ), 'pk' => base64_encode( sodium_crypto_sign_publickey( $kp ) ), 'status' => 'active', 'last_ok' => time() ) );
ok( DS_HQ_Link::has_key(), 'test site has a key' );

$md5a  = str_repeat( 'a', 32 );
$md5b  = str_repeat( 'b', 32 );
$lines = array( '[CRITICAL] Web shell by behaviour: /www/wp-content/plugins/x/a.php (100 bytes, md5 aaaaaaaa, score 9).', '[CRITICAL] Web shell by behaviour: /www/wp-content/plugins/x/b.php (200 bytes, md5 bbbbbbbb, score 9).' );
$files = array( array( 'path' => '/www/wp-content/plugins/x/a.php', 'size' => 100, 'md5' => $md5a ), array( 'path' => '/www/wp-content/plugins/x/b.php', 'size' => 200, 'md5' => $md5b ) );

// 1. not a repeat: email now, report queued and flushed as before, carrying full md5, no nonce
reset_world();
$alert->invoke( $tw, 'CRITICAL', $lines, $files, false );
$r = reports();
ok( 1 === count( $GLOBALS['mailed'] ), 'new file: emailed' );
ok( 1 === count( $r ) && ! isset( $r[0][1]['hold'] ), 'new file: one report, no nonce (HQ not asked)' );
ok( $md5a === ( $r[0][1]['findings'][0]['file']['md5'] ?? '' ) && $md5b === ( $r[0][1]['findings'][1]['file']['md5'] ?? '' ), 'report carries each file\'s full md5' );

// 2. repeat, HQ signs skip for this nonce: no email, HQ got the alert once
reset_world();
$GLOBALS['reply'] = skip_reply();
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
$r = reports();
ok( 0 === count( $GLOBALS['mailed'] ), 'repeat + signed skip: no email' );
ok( 1 === count( $r ) && preg_match( '/^[0-9a-f]{32}$/', (string) ( $r[0][1]['hold'] ?? '' ) ), 'repeat + signed skip: one report, with a fresh nonce' );
ok( 'repeat of event 7' === ( get_option( 'ds_tripwire_last_skip' )['why'] ?? '' ), 'skip recorded in ds_tripwire_last_skip' );
ok( empty( DS_HQ_Link::state()['queue'] ), 'nothing left queued after a skip' );

// 3. repeat, HQ answers send: one email, HQ got the alert once (no second report through the queue)
reset_world();
$GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'mail' => 'send' );
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 1 === count( $GLOBALS['mailed'] ) && 1 === count( reports() ), 'repeat + send: one email, one report' );

// 4-8. a skip that is not HQ's signed answer for THIS run sends
$bad = array(
	'signed over another nonce'   => skip_reply( str_repeat( 'c', 32 ) ),
	'signed for another host'     => skip_reply( null, 'evil.example' ),
	'signed 10 minutes ago'       => skip_reply( null, 'example-club.org', time() - 600 ),
	'signed by another key'       => skip_reply( null, 'example-club.org', null, sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() ) ),
	'unsigned'                    => array( 'ok' => true, 'status' => 'active', 'mail' => 'skip' ),
	'with a proof signature'      => skip_reply( null, 'example-club.org', null, null, 'proof' ),
	'with a checkin-now signature' => skip_reply( null, 'example-club.org', null, null, 'checkin-now' ),
);
foreach ( $bad as $what => $bad_reply ) {
	reset_world();
	$GLOBALS['reply'] = $bad_reply; // not $reply: in global scope that IS $GLOBALS['reply'], which reset_world() clears
	$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
	ok( 1 === count( $GLOBALS['mailed'] ) && 1 === count( reports() ) && ! get_option( 'ds_tripwire_last_skip' ), "skip $what: emailed, one report" );
}

// 9. HQ down (500): email now, the alert still reaches the queue for HQ
reset_world();
$GLOBALS['reply_code'] = 500;
$GLOBALS['reply']      = skip_reply();
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 1 === count( $GLOBALS['mailed'] ), 'HQ 500: emailed' );
ok( 1 === count( DS_HQ_Link::state()['queue'] ?? array() ), 'HQ 500: alert kept in the queue for HQ' );

// 9b. other refusals on the ask: email now, and the alert goes to the queue path (which handles each code)
foreach ( array( 409 => 'replay', 401 => 'unknown_site', 403 => 'retired' ) as $code => $what ) {
	reset_world();
	$GLOBALS['reply_code'] = $code;
	$GLOBALS['reply']      = array( 'code' => $what );
	$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
	ok( 1 === count( $GLOBALS['mailed'] ) && count( reports() ) >= 2, "HQ $code $what: emailed, alert handed to the queue path" );
}

// 9c. HQ took it but flagged the host (200, ok=false, no mail field): email, no second report
reset_world();
$GLOBALS['reply'] = array( 'ok' => false, 'flag' => 'Signed with this key but sent from another host' );
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 1 === count( $GLOBALS['mailed'] ) && 1 === count( reports() ), '200 ok=false: emailed, one report' );

// 10. network error / timeout: email now
reset_world();
$GLOBALS['net_error'] = 'cURL error 28: Operation timed out';
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 1 === count( $GLOBALS['mailed'] ), 'timeout: emailed' );

// 11. a web request (not cron, not CLI) never asks
reset_world();
$GLOBALS['ctx']   = 'web';
$GLOBALS['reply'] = skip_reply();
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 1 === count( $GLOBALS['mailed'] ) && 0 === count( array_filter( reports(), function ( $x ) { return isset( $x[1]['hold'] ); } ) ), 'web context: emailed, HQ not asked' );

// 12. a line without a full md5 (toolkit integrity) never asks
reset_world();
$GLOBALS['reply'] = skip_reply();
$alert->invoke( $tw, 'CRITICAL', array( $lines[0], '[CRITICAL] DS Toolkit file changed: x.php' ), array( $files[0], null ), true );
ok( 1 === count( $GLOBALS['mailed'] ) && ! isset( reports()[0][1]['hold'] ), 'line without a file: emailed, HQ not asked' );

// 13. waiting for approval on HQ: do not ask
reset_world();
$s = get_option( 'ds_hq_link' ); $s['status'] = 'pending'; update_option( 'ds_hq_link', $s );
$GLOBALS['reply'] = skip_reply();
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 1 === count( $GLOBALS['mailed'] ), 'pending link: emailed' );

// 14. another report is being sent (lock taken): do not wait, email
reset_world();
update_option( 'ds_hq_link_lock', time() );
$GLOBALS['reply'] = skip_reply();
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 1 === count( $GLOBALS['mailed'] ), 'lock busy: emailed' );
delete_option( 'ds_hq_link_lock' );

// 15. retired: email, nothing sent to HQ
reset_world();
$s = get_option( 'ds_hq_link' ); $s['status'] = 'retired'; update_option( 'ds_hq_link', $s );
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 1 === count( $GLOBALS['mailed'] ) && 0 === count( reports() ), 'retired: emailed, nothing sent to HQ' );

// 16. WP-CLI asks too (a skip is a decision not to mail, so no mail is lost there)
reset_world();
$GLOBALS['ctx'] = 'cli';
define( 'WP_CLI', true );
$GLOBALS['reply'] = skip_reply();
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( 0 === count( $GLOBALS['mailed'] ), 'WP-CLI + signed skip: no email' );

// 17. a failed wp_mail is still recorded honestly and HQ still has the alert
reset_world();
$GLOBALS['mail_ok'] = false;
$GLOBALS['reply']   = array( 'ok' => true, 'status' => 'active', 'mail' => 'send' );
$alert->invoke( $tw, 'CRITICAL', $lines, $files, true );
ok( false === ( get_option( 'ds_tripwire_last_notify' )['accepted'] ?? null ) && 1 === count( reports() ), 'mail failed: last_notify accepted=false, HQ has the alert' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
