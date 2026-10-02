<?php
// Run: php tests/hq-link-test.php        (plain PHP 7.4+ with sodium, no WordPress, no network)
//
// Guards DS_HQ_Link (features/class-ds-hq-link.php), the Tripwire -> Design Shop HQ reporter:
//   - install ids: WP Engine / Flywheel / other shapes, table prefix included, no raw DB name leaked;
//   - a COPY (stored id != this install's id) makes a NEW key and remembers what it was copied from, and a
//     site that already has its own key keeps it;
//   - every report is signed over "<id>\n<time>\n<sha256 body>" and verifies with the public key only;
//   - times only go forward, so HQ's replay check never drops two reports sent in the same second;
//   - a proof signature can never be replayed as a report signature;
//   - alert lines "[TIER] text" and stored findings "TIER: text" parse into {tier, text}.

error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'WPINC', 'wp-includes' );
define( 'DB_NAME', 'db6326666310' );
define( 'DB_HOST', '192.168.10.240' );
$table_prefix = 'wp_l86n47ms22_';

$GLOBALS['o']    = array();
$GLOBALS['sent'] = array();
$GLOBALS['home'] = 'https://example-club.org';
class WP_Error { public $m; public function __construct( $c = '', $m = '' ) { $this->m = $m; } public function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o'] ) ? $GLOBALS['o'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['o'][ $k ] = $v; return true; }
function apply_filters( $h, $v ) { return $v; }
function add_action() {}
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function home_url() { return $GLOBALS['home']; }
function wp_login_url() { return $GLOBALS['home'] . '/wp-login.php'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_remote_post( $url, $args ) {
	$GLOBALS['sent'][] = array( $url, $args );
	if ( ! empty( $GLOBALS['retire_once'] ) && '/report' === substr( $url, -7 ) ) { // HQ retired the key: refuse once
		$GLOBALS['retire_once'] = false;
		return array( 'code' => 401, 'body' => json_encode( array( 'code' => 'unknown_site' ) ) );
	}
	return array( 'code' => $GLOBALS['reply_code'] ?? 200, 'body' => json_encode( $GLOBALS['reply'] ?? array( 'ok' => true, 'status' => 'active' ) ) );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
define( 'MINUTE_IN_SECONDS', 60 );
function get_transient( $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['tr'][ $k ] = $v; return true; }
function human_time_diff( $a, $b = 0 ) { return 'moments'; }
$GLOBALS['cron'] = array();
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['cron'][ $h ] ); }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $h ] ?? false; }
class WP_REST_Response { public $data; public function __construct( $d ) { $this->data = $d; } public function header( $k, $v ) {} }
function rest_ensure_response( $d ) { return new WP_REST_Response( $d ); }

require dirname( __DIR__ ) . '/features/class-ds-hq-link.php';

$fails = 0;
function ok( $cond, $what ) { global $fails; echo ( $cond ? 'ok   ' : 'FAIL ' ) . $what . "\n"; if ( ! $cond ) { $fails++; } }
function sent_last() { return end( $GLOBALS['sent'] ); }
function verify_sent( $pk_b64 ) {
	list( , $args ) = sent_last();
	$h = $args['headers'];
	return sodium_crypto_sign_verify_detached( base64_decode( $h['X-DSHQ-Sig'] ), $h['X-DSHQ-Site'] . "\n" . $h['X-DSHQ-Time'] . "\n" . hash( 'sha256', $args['body'] ), base64_decode( $pk_b64 ) );
}

// 1. Install id shape (no FLYWHEEL_CONFIG_DIR / PWP_NAME defined yet: generic platform).
$id = DS_HQ_Link::install_id();
ok( (bool) preg_match( '/^site:[a-f0-9]{10}\.[a-f0-9]{4}$/', $id ), "generic install id shape ($id)" );
ok( false === strpos( $id, DB_NAME ), 'install id does not contain the raw DB name' );
$GLOBALS['table_prefix'] = 'wp_staging_';
ok( DS_HQ_Link::install_id() !== $id, 'a different table prefix (WP Staging copy) gives a different id' );
$GLOBALS['table_prefix'] = 'wp_l86n47ms22_';

// 2. Enroll makes a key, signs with it, and sends only the public half.
$GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active' );
$s = DS_HQ_Link::enroll();
ok( DS_HQ_Link::has_key(), 'enroll creates a key for this install' );
list( $url, $args ) = sent_last();
$body = json_decode( $args['body'], true );
ok( '/enroll' === substr( $url, -7 ), 'enroll posts to /enroll' );
ok( $body['pubkey'] === $s['pk'] && false === strpos( $args['body'], $s['sk'] ), 'enroll sends the public key and never the secret' );
ok( verify_sent( $s['pk'] ), 'enroll is signed with the new key' );
ok( 'active' === DS_HQ_Link::state()['status'], 'status follows HQ (active)' );
$pk1 = $s['pk'];

// 3. A report verifies with the public key; times only go forward.
DS_HQ_Link::send( 'checkin', array( array( 'tier' => 'REVIEW', 'text' => 'x' ) ) );
ok( verify_sent( $pk1 ), 'report signature verifies with the public key' );
$t1 = (int) sent_last()[1]['headers']['X-DSHQ-Time'];
DS_HQ_Link::send( 'alert', array( array( 'tier' => 'HIGH', 'text' => 'y' ) ) );
$t2 = (int) sent_last()[1]['headers']['X-DSHQ-Time'];
ok( $t2 > $t1, "two reports in one second still get increasing times ($t1 < $t2)" );
$b = json_decode( sent_last()[1]['body'], true );
ok( 'alert' === $b['kind'] && 'https://example-club.org/wp-login.php' === $b['login_url'], 'alert payload carries kind and login URL' );

// 4. The same install keeps its key.
DS_HQ_Link::enroll();
ok( DS_HQ_Link::state()['pk'] === $pk1, 'same install: key is kept' );

// 5. A copy (stored id belongs to another install) makes a new key and records where it came from.
$copy = DS_HQ_Link::state();
$copy['sid'] = 'wpe:originalsite.ab12';
update_option( DS_HQ_Link::OPT, $copy );
ok( ! DS_HQ_Link::has_key(), 'a copied option is not this install\'s key' );
DS_HQ_Link::enroll();
$st = DS_HQ_Link::state();
ok( $st['pk'] !== $pk1 && $st['sid'] === DS_HQ_Link::install_id(), 'copy: new key under this install\'s own id' );
ok( 'wpe:originalsite.ab12' === $st['previous'], 'copy: remembers which install it was copied from' );

// 6. HQ retired the key -> the next report re-enrolls with a fresh key.
$before = DS_HQ_Link::state()['pk'];
$st = DS_HQ_Link::state(); $st['tried'] = 0; update_option( DS_HQ_Link::OPT, $st );
$GLOBALS['retire_once'] = true;
DS_HQ_Link::send( 'checkin', array() );
ok( DS_HQ_Link::state()['pk'] !== $before, 'retired key: a fresh key is enrolled' );
ok( 0 === count( (array) DS_HQ_Link::state()['queue'] ), 'the refused report is resent at once with the new key' );

// 7. HQ unreachable -> queued, capped.
$GLOBALS['reply_code'] = 503;
for ( $i = 0; $i < 15; $i++ ) { DS_HQ_Link::send( 'checkin', array() ); }
ok( count( DS_HQ_Link::state()['queue'] ) === DS_HQ_Link::QUEUE_MAX, 'queue is capped at ' . DS_HQ_Link::QUEUE_MAX );
$GLOBALS['reply_code'] = 200;
DS_HQ_Link::send( 'checkin', array() );
ok( 0 === count( DS_HQ_Link::state()['queue'] ), 'queue drains once HQ answers' );

// 8. Proof signatures cannot pass as report signatures.
$st    = DS_HQ_Link::state();
$nonce = 'aabbccddeeff00112233445566778899';
$proof = sodium_crypto_sign_detached( "dshq-proof\n" . $nonce . "\nexample-club.org", base64_decode( $st['sk'] ) );
ok( ! sodium_crypto_sign_verify_detached( $proof, $st['sid'] . "\n" . time() . "\n" . hash( 'sha256', '{}' ), base64_decode( $st['pk'] ) ), 'a proof signature never verifies as a report' );
ok( false === strpos( 'dshq-proof', ':' ) && false !== strpos( $st['sid'], ':' ), 'proof prefix has no ":" while every install id does (domain separation)' );

// 9. Parsing.
$GLOBALS['o']['ds_tripwire_state'] = array( 'last_findings' => array( 'CRITICAL: Web shell by behaviour: /www/x.php', 'REVIEW: llms.txt' ) );
DS_HQ_Link::on_checked();
$b = json_decode( sent_last()[1]['body'], true );
ok( 2 === count( $b['findings'] ) && 'CRITICAL' === $b['findings'][0]['tier'] && 'llms.txt' === $b['findings'][1]['text'], 'stored findings parse into tier + text' );
DS_HQ_Link::on_alert( 'CRITICAL', array( '[CRITICAL] shell at /www/y.php', '[HIGH] new admin' ) );
$b = json_decode( sent_last()[1]['body'], true );
ok( 'HIGH' === $b['findings'][1]['tier'] && 'shell at /www/y.php' === $b['findings'][0]['text'], 'alert lines parse into tier + text' );

// 10. Retired on HQ: the site stops sending, never re-enrolls by itself, and the card says so.
$pk_before = DS_HQ_Link::state()['pk'];
$GLOBALS['reply_code'] = 403; $GLOBALS['reply'] = array( 'code' => 'retired' );
DS_HQ_Link::send( 'checkin', array() );
ok( 'retired' === DS_HQ_Link::state()['status'] && DS_HQ_Link::state()['pk'] === $pk_before, 'retired: status set, key NOT replaced (no self re-enroll)' );
$n = count( $GLOBALS['sent'] );
DS_HQ_Link::send( 'checkin', array() );
ok( count( $GLOBALS['sent'] ) === $n, 'retired: nothing more is sent' );
$GLOBALS['reply_code'] = 200; $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'retired' );
$GLOBALS['tr'] = array();
$line = DS_HQ_Link::status_line();
ok( 'bad' === $line[0] && false !== strpos( $line[1], 'Retired' ), 'settings card shows Retired' );
ok( '/status' === substr( sent_last()[0], -7 ), 'the card asked HQ /status' );
$n = count( $GLOBALS['sent'] );
DS_HQ_Link::status_line();
ok( count( $GLOBALS['sent'] ) === $n, 'status is asked at most once per 5 minutes' );
$GLOBALS['tr'] = array(); $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active' );
ok( 'ok' === DS_HQ_Link::status_line()[0], 'once HQ says active again, the card says Connected' );

// 11. HQ sets the check-in interval; the site re-arms its own timer.
$GLOBALS['tr'] = array();
$st = DS_HQ_Link::state(); $st['status'] = 'active'; update_option( DS_HQ_Link::OPT, $st );
$GLOBALS['reply_code'] = 200; $GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'checkin_every' => 3600 );
DS_HQ_Link::send( 'checkin', array() );
ok( 3600 === DS_HQ_Link::every(), 'interval from HQ is remembered (1 hour)' );
$next = $GLOBALS['cron'][ DS_HQ_Link::CHECKIN_HOOK ] ?? 0;
ok( $next > time() + 3500 && $next <= time() + 3600, 'next check-in armed one interval ahead' );
$GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'checkin_every' => 30 );
DS_HQ_Link::send( 'checkin', array() );
ok( DS_HQ_Link::EVERY_MIN === DS_HQ_Link::every(), 'an interval below 15 minutes is clamped up' );
$GLOBALS['reply'] = array( 'ok' => true, 'status' => 'active', 'checkin_every' => 999999 );
DS_HQ_Link::send( 'checkin', array() );
ok( DS_HQ_Link::EVERY_MAX === DS_HQ_Link::every(), 'an interval above 24 hours is clamped down' );

// 12. "Check in now" sends at once, then refuses for a minute; a retired site refuses.
$GLOBALS['tr'] = array();
$n = count( $GLOBALS['sent'] );
$r = DS_HQ_Link::checkin_now();
ok( $r instanceof WP_REST_Response && count( $GLOBALS['sent'] ) === $n + 1, 'check in now: one report sent' );
ok( DS_HQ_Link::checkin_now() instanceof WP_Error, 'check in now: refused again within a minute' );
$GLOBALS['tr'] = array();
$st = DS_HQ_Link::state(); $st['status'] = 'retired'; update_option( DS_HQ_Link::OPT, $st );
ok( DS_HQ_Link::checkin_now() instanceof WP_Error, 'check in now: refused when retired' );
$st['status'] = 'active'; update_option( DS_HQ_Link::OPT, $st );

// 13. Flywheel and WP Engine ids (constants can only be defined once, so these run last).
define( 'FLYWHEEL_CONFIG_DIR', '/www/flywheel-config' );
ok( (bool) preg_match( '/^fw:[a-f0-9]{10}\.[a-f0-9]{4}$/', DS_HQ_Link::install_id() ) && 'Flywheel' === DS_HQ_Link::platform(), 'Flywheel id shape: fw:<hash>.<prefix>' );
define( 'PWP_NAME', 'WidgetTesting1' );
ok( 0 === strpos( DS_HQ_Link::install_id(), 'wpe:widgettesting1.' ) && 'WP Engine' === DS_HQ_Link::platform(), 'WP Engine id: wpe:<install>.<prefix>' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
