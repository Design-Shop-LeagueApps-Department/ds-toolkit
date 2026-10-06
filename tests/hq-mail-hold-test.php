<?php
// Run: php tests/hq-mail-hold-test.php        (plain PHP 7.4+ with sodium, no WordPress, no network)
//
// Guards the alert email that waits for Design Shop HQ's decision (DS_Tripwire::alert + DS_HQ_Link):
//   - mail is held ONLY while the HQ link is healthy (key, active, a report accepted recently); otherwise it goes at once;
//   - a held alert is reported with its hold id; HQ's "skip" drops it, "send" or NO decision sends it;
//   - fail-safe: HQ unreachable / overloaded keeps the report queued and the mail held, and the deadline (and every daily
//     run) sends anything held for 30 minutes; a refused report (bad signature) or a retired link sends it at once;
//   - nothing is ever mailed twice (deadline after a decision, decision after the deadline);
//   - findings that name a file carry its FULL md5 (only when size and prefix still match), and a plugin's folder + version.

error_reporting( E_ALL & ~E_DEPRECATED );
if ( 'cli' !== PHP_SAPI ) { exit; }
define( 'ABSPATH', sys_get_temp_dir() . '/dsmh-' . getmypid() . '/' );
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
$GLOBALS['acts'] = array(); $GLOBALS['filters'] = array(); $GLOBALS['home'] = 'https://example-club.org'; $GLOBALS['now'] = 0;
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
function wp_mail( $to, $s, $b ) { $GLOBALS['mailed'][] = array( $to, $s ); return true; }
function wp_remote_post( $url, $args ) {
	$GLOBALS['sent'][] = array( $url, json_decode( $args['body'], true ) );
	return array( 'code' => $GLOBALS['reply_code'] ?? 200, 'body' => json_encode( $GLOBALS['reply'] ?? array( 'ok' => true, 'status' => 'active' ) ) );
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
function wp_doing_cron() { return true; } // the scans run in cron: reports go out in the same request
function is_multisite() { return false; }
function get_current_blog_id() { return 1; }
function is_admin() { return false; }
function current_time( $t ) { return time(); }
function wp_rand( $a, $b ) { return $a; }
function get_plugins() { return array( 'fancy-forms/fancy-forms.php' => array( 'Version' => '4.2.1' ), 'other/other.php' => array( 'Version' => '1.0' ) ); }
class WP_REST_Response { public $data; public function __construct( $d ) { $this->data = $d; } public function header( $k, $v ) {} }
function rest_ensure_response( $d ) { return new WP_REST_Response( $d ); }
$hqkp = sodium_crypto_sign_keypair();
define( 'DS_HQ_LINK_PUBKEY', base64_encode( sodium_crypto_sign_publickey( $hqkp ) ) );

require dirname( __DIR__ ) . '/features/class-ds-tripwire.php';
$tw = new DS_Tripwire( array( 'tripwire_alert_email' => 'design@example.com' ) );
$tw->init();
$alert = new ReflectionMethod( 'DS_Tripwire', 'alert' );
$alert->setAccessible( true );

$fails = 0;
function ok( $c, $w ) { global $fails; echo ( $c ? 'ok   ' : 'FAIL ' ) . $w . "\n"; if ( ! $c ) { $fails++; } }
function reset_world() { $GLOBALS['sent'] = array(); $GLOBALS['mailed'] = array(); unset( $GLOBALS['reply'], $GLOBALS['reply_code'] ); $GLOBALS['cron'] = array(); }
function link_state( array $s ) { $GLOBALS['o']['ds_hq_link'] = $s; }
function held() { return (array) get_option( 'ds_tripwire_held_mail', array() ); }
function last_report() { $r = end( $GLOBALS['sent'] ); return $r ? $r[1] : null; }

// A real keyed site (enroll is exercised in hq-link-test.php; here the key just has to exist).
$kp = sodium_crypto_sign_keypair();
$base = array( 'sid' => DS_HQ_Link::install_id(), 'pk' => base64_encode( sodium_crypto_sign_publickey( $kp ) ), 'sk' => base64_encode( sodium_crypto_sign_secretkey( $kp ) ), 'status' => 'active', 'last_ok' => time() - 600, 'every' => 21600 );
$fire = function ( $tier = 'CRITICAL', $lines = array( '[CRITICAL] Web shell by behaviour: /www/x.php (10 bytes, md5 abcdef12, score 120).' ) ) use ( $alert, $tw ) {
	$alert->invoke( $tw, $tier, $lines );
};

// ---- when NOT to hold
reset_world(); link_state( array() );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'no HQ key: the email goes at once, nothing held' );
reset_world(); link_state( array_merge( $base, array( 'status' => 'pending' ) ) );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'link waiting for approval: the email goes at once' );
reset_world(); link_state( array_merge( $base, array( 'last_ok' => time() - 2 * 21600 - 3601 ) ) );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'no accepted report for 2 intervals + 1 h: the email goes at once' );
reset_world(); link_state( array_merge( $base, array( 'status' => 'retired' ) ) );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'retired link: the email goes at once' );

// ---- healthy link: HQ decides
reset_world(); link_state( $base ); $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'mail' => 'skip', 'mail_why' => 'same file as event 520' );
$fire();
$r = last_report();
ok( $r && 'alert' === $r['kind'] && 12 === strlen( (string) ( $r['hold'] ?? '' ) ), 'healthy link: the alert is reported with a hold id' );
ok( 0 === count( $GLOBALS['mailed'] ) && ! held(), 'HQ says skip: no email, nothing left held' );
$ln = get_option( 'ds_tripwire_last_notify' );
ok( false !== strpos( (string) ( $ln['skipped'] ?? '' ), 'event 520' ), 'the skip and HQ\'s reason are recorded in last_notify' );

reset_world(); link_state( $base ); $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'mail' => 'send' );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'HQ says send: one email' );

reset_world(); link_state( $base ); $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active' );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'HQ gives no decision (an older HQ): the email goes' );

reset_world(); link_state( $base ); $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'mail' => 'anything-else' );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ), 'an unknown decision counts as send' );

// ---- fail-safe paths
reset_world(); link_state( $base ); $GLOBALS['reply_code'] = 503; $GLOBALS['reply'] = array( 'code' => 'busy' );
$fire();
ok( 0 === count( $GLOBALS['mailed'] ) && 1 === count( held() ), 'HQ unreachable: the email is held and the report stays queued' );
ok( isset( $GLOBALS['cron'][ DS_Tripwire::HELD_HOOK ] ), 'the deadline is scheduled' );
DS_Tripwire::send_overdue_held();
ok( 0 === count( $GLOBALS['mailed'] ), 'before 30 minutes the deadline does not send' );
$h = held(); foreach ( $h as $id => $m ) { $h[ $id ]['time'] = time() - DS_Tripwire::HELD_MAX_AGE; } update_option( DS_Tripwire::HELD_OPT, $h );
DS_Tripwire::send_overdue_held();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'after 30 minutes without an answer the email is sent' );
ok( false !== strpos( (string) ( get_option( 'ds_tripwire_last_notify' )['note'] ?? '' ), 'without an answer from HQ' ), 'last_notify says it went on the deadline' );
// HQ comes back later and says skip for that same report: nothing is mailed twice, nothing breaks
$GLOBALS['reply_code'] = 200; $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'mail' => 'skip' );
$GLOBALS['o']['ds_hq_link']['queue'] = $GLOBALS['o']['ds_hq_link']['queue'] ?? array();
DS_HQ_Link::flush();
ok( 1 === count( $GLOBALS['mailed'] ), 'a late decision after the deadline never sends a second email' );

reset_world(); link_state( $base ); $GLOBALS['reply_code'] = 400; $GLOBALS['reply'] = array( 'code' => 'bad_body' );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'report refused for good (400): the held email goes at once' );

reset_world(); link_state( $base ); $GLOBALS['reply_code'] = 403; $GLOBALS['reply'] = array( 'code' => 'retired' );
$fire();
ok( 1 === count( $GLOBALS['mailed'] ) && ! held(), 'link retired on HQ while holding: the email goes at once' );

reset_world(); link_state( $base ); $GLOBALS['reply_code'] = 503;
$fire();
$h = held(); foreach ( $h as $id => $m ) { $h[ $id ]['time'] = time() - DS_Tripwire::HELD_MAX_AGE - 5; } update_option( DS_Tripwire::HELD_OPT, $h );
$GLOBALS['o']['ds_tripwire_state'] = array( 'seeded' => 0 ); // run_checks() is the daily safety net
try { $tw->run_checks(); } catch ( \Throwable $e ) { /* the IOC checks may not find a web root here; the held mail goes first */ }
ok( 1 <= count( $GLOBALS['mailed'] ) && ! held(), 'the daily run sends anything held past the deadline even if the cron event was lost' );

// ---- file details
@mkdir( ABSPATH . 'wp-content/plugins/fancy-forms/lib', 0777, true );
$f = ABSPATH . 'wp-content/plugins/fancy-forms/lib/x.php';
file_put_contents( $f, '<?php // vendor file' );
$md5 = md5_file( $f ); $size = filesize( $f );
$d = DS_HQ_Link::file_details( "Web shell by behaviour: $f ($size bytes, md5 " . substr( $md5, 0, 8 ) . ', score 102).' );
ok( $md5 === ( $d['md5'] ?? '' ) && $size === $d['size'] && 'fancy-forms' === ( $d['plugin'] ?? '' ) && '4.2.1' === ( $d['version'] ?? '' ), 'a plugin file: full md5, size, plugin folder and version' );
$d = DS_HQ_Link::file_details( "Web shell by behaviour: $f (" . ( $size + 1 ) . ' bytes, md5 ' . substr( $md5, 0, 8 ) . ', score 102).' );
ok( ! isset( $d['md5'] ), 'size changed since the scan: no full md5 is claimed' );
$d = DS_HQ_Link::file_details( "Web shell by behaviour: $f ($size bytes, md5 00000000, score 102)." );
ok( ! isset( $d['md5'] ), 'md5 prefix changed since the scan: no full md5 is claimed' );
ok( array() === DS_HQ_Link::file_details( 'A new administrator account appeared since yesterday: x.' ), 'a finding without a file has no details' );
reset_world(); link_state( $base ); $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'mail' => 'send' );
$fire( 'CRITICAL', array( "[CRITICAL] Web shell by behaviour: $f ($size bytes, md5 " . substr( $md5, 0, 8 ) . ', score 102).' ) );
$r = last_report();
ok( $md5 === ( $r['findings'][0]['file']['md5'] ?? '' ), 'the report to HQ carries the full md5' );
@unlink( $f );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
