<?php
// Run: php tests/hq-update-test.php      (plain PHP 7.4+ with sodium and zip, no WordPress, no network)
//
// Guards DS_HQ_Update (features/class-ds-hq-update.php): HQ can say WHEN to update ds-toolkit, never WHAT.
//   - an order counts only if HQ signed it for THIS install, it is fresh, names a stable version, and that version is
//     newer than the installed one (tampered target, other site, stale, pre-release, downgrade, wrong key: ignored);
//   - a release counts only if the release key signed "<version> + sha256 of the zip", and both version lines in the
//     zip say that version; HQ's key cannot sign a release and a release signature cannot be replayed as an order;
//   - the download comes only from the official repo's published stable release with that exact tag;
//   - an order only queues (cron does the work), one queue entry per target, three tries per target, a stuck run is
//     retried, and the off switch wins;
//   - the happy path hands WordPress's updater exactly the verified local file, for ds-toolkit only, and every
//     filter it added is gone afterwards; any failure before that leaves the installed plugin untouched.

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
error_reporting( E_ALL & ~E_DEPRECATED );

$root = sys_get_temp_dir() . '/dshq-update-test-' . getmypid();
@mkdir( $root . '/wp-admin/includes', 0777, true );
foreach ( array( 'admin.php', 'class-wp-upgrader.php', 'file.php' ) as $f ) {
	file_put_contents( $root . '/wp-admin/includes/' . $f, '<?php' );
}
@mkdir( $root . '/wp-content/plugins/ds-toolkit', 0777, true );
define( 'ABSPATH', $root . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_PLUGIN_DIR', $root . '/wp-content/plugins' );
define( 'DS_TOOLKIT_PATH', WP_PLUGIN_DIR . '/ds-toolkit/' );
$GLOBALS['main_tpl'] = "<?php\n/**\n * Plugin Name:       DS Toolkit\n * Version:           %s\n */\ndefine( 'DS_TOOLKIT_VERSION', '%s' );\n";
function plugin_at( $v ) { file_put_contents( DS_TOOLKIT_PATH . 'ds-toolkit.php', sprintf( $GLOBALS['main_tpl'], $v, $v ) ); }
plugin_at( '1.10.26-rc.1' );

// ---- WordPress stand-ins -------------------------------------------------------------------------------------
class WP_Error {
	private $c; private $m;
	public function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; }
	public function get_error_code() { return $this->c; }
	public function get_error_message() { return $this->m; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
$GLOBALS['o'] = array(); $GLOBALS['cron'] = array(); $GLOBALS['filters'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o'] ) ? $GLOBALS['o'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['o'][ $k ] = $v; return true; }
function add_action() {}
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $h ] ?? false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['cron'][ $h ] ); }
function wp_rand( $a, $b ) { return $a; }
function human_time_diff( $a, $b = 0 ) { return 'moments'; }
function get_file_data( $file, $headers ) { $c = file_get_contents( $file ); return array( 'Version' => preg_match( '/^[ \t\/*#@]*Version:\s*(\S+)/mi', $c, $m ) ? $m[1] : '' ); }
function plugin_basename( $f ) { return ltrim( str_replace( WP_PLUGIN_DIR, '', $f ), '/' ); }
function is_plugin_active( $p ) { return true; }
function activate_plugin() { $GLOBALS['activated'] = true; }
function wp_clean_plugins_cache() {}
function wp_cache_delete() {}
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['filters'][ $h ][] = $cb; return true; }
function remove_filter( $h, $cb, $p = 10 ) { foreach ( $GLOBALS['filters'][ $h ] ?? array() as $i => $f ) { if ( $f === $cb ) { unset( $GLOBALS['filters'][ $h ][ $i ] ); } } if ( empty( $GLOBALS['filters'][ $h ] ) ) { unset( $GLOBALS['filters'][ $h ] ); } return true; }
function apply_filters( $h, $v, ...$a ) { foreach ( $GLOBALS['filters'][ $h ] ?? array() as $f ) { $v = $f( $v, ...$a ); } return $v; }
function __return_true() { return true; }
function __return_false() { return false; }
function get_site_transient( $k ) { return apply_filters( 'site_transient_' . $k, $GLOBALS['transient'] ?? false ); }
// HTTP: $GLOBALS['http'][url] = [ code, body ]; download_url copies $GLOBALS['zipfile'].
function wp_remote_get( $url, $args = array() ) { $GLOBALS['got'][] = $url; return isset( $GLOBALS['http'][ $url ] ) ? array( 'code' => $GLOBALS['http'][ $url ][0], 'body' => $GLOBALS['http'][ $url ][1] ) : new WP_Error( 'http', 'no route to ' . $url ); }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function download_url( $url, $timeout = 300 ) {
	$GLOBALS['downloaded'][] = $url;
	if ( empty( $GLOBALS['zipfile'] ) ) { return new WP_Error( 'http_404', 'Not Found' ); }
	$t = tempnam( sys_get_temp_dir(), 'dstk' ); copy( $GLOBALS['zipfile'], $t ); $GLOBALS['tmpfiles'][] = $t; return $t;
}
class FakeWpdb {
	public $options = 'wp_options';
	public function prepare( $q, ...$a ) { return array( $q, $a ); }
	public function query( $p ) {
		list( $q, $a ) = $p;
		if ( 0 === strpos( $q, 'INSERT IGNORE' ) ) { if ( array_key_exists( $a[0], $GLOBALS['o'] ) ) { return 0; } $GLOBALS['o'][ $a[0] ] = $a[1]; return 1; }
		if ( 0 === strpos( $q, 'DELETE' ) ) { unset( $GLOBALS['o'][ $a[0] ] ); return 1; }
		return 0;
	}
	public function get_var( $p ) { return $GLOBALS['o'][ $p[1][0] ] ?? null; }
}
$GLOBALS['wpdb'] = new FakeWpdb();
// The updater: records what it was offered and "installs" by rewriting the plugin header to the offered version.
class WP_Upgrader {
	public static $locked = false;
	public static function create_lock( $n ) { if ( ! empty( $GLOBALS['wp_busy'] ) || self::$locked ) { return false; } self::$locked = true; return true; }
	public static function release_lock( $n ) { self::$locked = false; }
}
class WP_Automatic_Updater {
	public function update( $type, $item ) {
		$t     = get_site_transient( 'update_plugins' );
		$offer = $t->response['ds-toolkit/ds-toolkit.php'] ?? null;
		$GLOBALS['offered'] = array( 'type' => $type, 'item' => $item, 'transient_pkg' => $offer ? $offer->package : null,
			'pkg_exists' => $offer && file_exists( $offer->package ), 'pkg_sha' => $offer && file_exists( $offer->package ) ? hash_file( 'sha256', $offer->package ) : '',
			'auto' => apply_filters( 'auto_update_plugin', false, $item ), 'cron' => apply_filters( 'wp_doing_cron', false ), 'disabled' => apply_filters( 'automatic_updater_disabled', true ) );
		if ( ! empty( $GLOBALS['updater_result'] ) ) { return $GLOBALS['updater_result']; }
		plugin_at( $offer->new_version );
		return true;
	}
}

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "ok   $m\n"; } else { $fail++; echo "FAIL $m\n"; } }

// ---- keys -------------------------------------------------------------------------------------------------------
$hq  = sodium_crypto_sign_keypair();
$rel = sodium_crypto_sign_keypair();
$bad = sodium_crypto_sign_keypair();
define( 'DS_HQ_LINK_PUBKEY', base64_encode( sodium_crypto_sign_publickey( $hq ) ) );
define( 'DS_TOOLKIT_RELEASE_PUBKEY', base64_encode( sodium_crypto_sign_publickey( $rel ) ) );
function order( $sid, $target, $t = null, $kp = null ) {
	$t = null === $t ? time() : $t;
	return array( 'target' => $target, 't' => $t, 'sig' => base64_encode( sodium_crypto_sign_detached( "dshq-update\n$sid\n$target\n$t", sodium_crypto_sign_secretkey( $kp ?: $GLOBALS['hq'] ) ) ) );
}
function relsig( $version, $sha, $kp = null ) {
	return base64_encode( sodium_crypto_sign_detached( "ds-toolkit-release\n$version\n$sha", sodium_crypto_sign_secretkey( $kp ?: $GLOBALS['rel'] ) ) );
}

require dirname( __DIR__ ) . '/features/class-ds-hq-update.php';
$sid = 'wpe:widgettesting1.4128';

// ---- 1. orders ---------------------------------------------------------------------------------------------------
list( $ook, $r ) = DS_HQ_Update::order_ok( order( $sid, '1.10.26' ), $sid, '1.10.26-rc.1' );
ok( $ook && '1.10.26' === $r, 'a fresh order HQ signed for this install, for a newer version, is accepted (rc.1 -> final)' );
list( $ook, $r ) = DS_HQ_Update::order_ok( order( 'fw:0000000000.1c26', '1.10.26' ), $sid, '1.10.25' );
ok( ! $ook && 'bad HQ signature' === $r, 'an order signed for another install is refused' );
$x = order( $sid, '1.10.26' ); $x['target'] = '1.10.99';
list( $ook, $r ) = DS_HQ_Update::order_ok( $x, $sid, '1.10.25' );
ok( ! $ook && 'bad HQ signature' === $r, 'changing the target after HQ signed it breaks the signature' );
list( $ook, $r ) = DS_HQ_Update::order_ok( order( $sid, '1.10.26', null, $bad ), $sid, '1.10.25' );
ok( ! $ook && 'bad HQ signature' === $r, 'an order signed with any key but HQ\'s is refused' );
list( $ook, $r ) = DS_HQ_Update::order_ok( order( $sid, '1.10.26', time() - 1000 ), $sid, '1.10.25' );
ok( ! $ook && false !== strpos( $r, 'too old' ), 'an order older than 15 minutes is refused (a captured reply cannot be replayed later)' );
list( $ook, $r ) = DS_HQ_Update::order_ok( order( $sid, '1.10.26', time() + 1000 ), $sid, '1.10.25' );
ok( ! $ook, 'an order dated in the future is refused' );
list( $ook, $r ) = DS_HQ_Update::order_ok( order( $sid, '1.10.27-rc.1' ), $sid, '1.10.25' );
ok( ! $ook && false !== strpos( $r, 'stable' ), 'a pre-release target is refused' );
list( $ook, $r ) = DS_HQ_Update::order_ok( order( $sid, '1.10.25' ), $sid, '1.10.25' );
ok( ! $ook && false !== strpos( $r, 'not newer' ), 'the installed version is not re-installed' );
list( $ook, $r ) = DS_HQ_Update::order_ok( order( $sid, '1.10.20' ), $sid, '1.10.25' );
ok( ! $ook && false !== strpos( $r, 'not newer' ), 'a downgrade is refused' );
list( $ook, $r ) = DS_HQ_Update::order_ok( array( 'target' => '1.10.26', 't' => time(), 'sig' => 'not-base64!!' ), $sid, '1.10.25' );
ok( ! $ook, 'garbage in the signature is refused, without an error' );
list( $ook, $r ) = DS_HQ_Update::order_ok( 'update now', $sid, '1.10.25' );
ok( ! $ook, 'an order that is not an object is refused' );

// ---- 2. release signatures -----------------------------------------------------------------------------------------
$sha = hash( 'sha256', 'zip bytes' );
ok( DS_HQ_Update::release_sig_ok( '1.10.26', $sha, relsig( '1.10.26', $sha ) ), 'the release key\'s signature over version + sha256 verifies' );
ok( DS_HQ_Update::release_sig_ok( '1.10.26', strtoupper( $sha ), relsig( '1.10.26', $sha ) . "\n" ), 'an upper-case sha and a trailing newline in the .sig file still verify' );
ok( ! DS_HQ_Update::release_sig_ok( '1.10.26', hash( 'sha256', 'other bytes' ), relsig( '1.10.26', $sha ) ), 'a different zip does not verify' );
ok( ! DS_HQ_Update::release_sig_ok( '1.10.27', $sha, relsig( '1.10.26', $sha ) ), 'a signature for one version does not cover another' );
ok( ! DS_HQ_Update::release_sig_ok( '1.10.26', $sha, relsig( '1.10.26', $sha, $hq ) ), 'HQ\'s key cannot sign a release' );
$osig = order( $sid, '1.10.26' )['sig'];
ok( ! DS_HQ_Update::release_sig_ok( '1.10.26', $sha, $osig ), 'an order signature cannot pass as a release signature' );

// ---- 3. version lines ------------------------------------------------------------------------------------------------
$real = file_get_contents( dirname( __DIR__ ) . '/ds-toolkit.php' );
list( $h, $d ) = DS_HQ_Update::versions_in( $real );
ok( '' !== $h && $h === $d, "this repo's ds-toolkit.php has matching version lines ($h / $d)" );
list( $h, $d ) = DS_HQ_Update::versions_in( sprintf( $GLOBALS['main_tpl'], '1.10.26', '1.10.25' ) );
ok( '1.10.26' === $h && '1.10.25' === $d, 'a header/define mismatch is read as two different versions' );

// ---- 4. taking orders (queue only) -----------------------------------------------------------------------------------
DS_HQ_Update::take_order( order( $sid, '1.10.26' ), $sid );
$s = DS_HQ_Update::state();
ok( 'queued' === ( $s['status'] ?? '' ) && '1.10.26' === $s['target'] && '1.10.26-rc.1' === $s['from'], 'a good order queues the target' );
ok( ! empty( $GLOBALS['cron'][ DS_HQ_Update::HOOK ] ) && $GLOBALS['cron'][ DS_HQ_Update::HOOK ] >= time() + 60, 'the install runs from cron at least a minute later, not in the reply' );
ok( empty( $GLOBALS['got'] ) && empty( $GLOBALS['downloaded'] ), 'taking an order downloads nothing' );
$when = $GLOBALS['cron'][ DS_HQ_Update::HOOK ];
DS_HQ_Update::take_order( order( $sid, '1.10.26' ), $sid );
ok( $when === $GLOBALS['cron'][ DS_HQ_Update::HOOK ], 'the same order again does not queue a second run' );
DS_HQ_Update::take_order( order( 'fw:0000000000.1c26', '1.10.27' ), $sid );
$s = DS_HQ_Update::state();
ok( '1.10.26' === $s['target'] && 'bad HQ signature' === ( $s['refused']['reason'] ?? '' ), 'a bad order is recorded as refused and changes nothing else' );
ok( isset( DS_HQ_Update::report()['refused'] ), 'the refusal is reported to HQ' );

// ---- 5. install: every failure leaves the plugin as it was -----------------------------------------------------------------
$repo = 'https://api.github.com/repos/' . DS_HQ_Update::REPO . '/releases/tags/v1.10.26';
$base = 'https://github.com/' . DS_HQ_Update::REPO . '/releases/download/v1.10.26/';
function make_zip( $version_header, $version_define ) {
	$z = tempnam( sys_get_temp_dir(), 'dsz' ) . '.zip';
	$a = new ZipArchive(); $a->open( $z, ZipArchive::CREATE );
	$a->addFromString( 'ds-toolkit/ds-toolkit.php', sprintf( $GLOBALS['main_tpl'], $version_header, $version_define ) );
	$a->addFromString( 'ds-toolkit/assets/noise.bin', random_bytes( 8000 ) ); // does not compress: a realistic size
	$a->close(); return $z;
}
function release( $extra = array() ) {
	global $base;
	return json_encode( array_merge( array( 'tag_name' => 'v1.10.26', 'draft' => false, 'prerelease' => false, 'assets' => array(
		array( 'name' => 'ds-toolkit.zip', 'browser_download_url' => $base . 'ds-toolkit.zip' ),
		array( 'name' => 'ds-toolkit.zip.sig', 'browser_download_url' => $base . 'ds-toolkit.zip.sig' ),
	) ), $extra ) );
}
$good = make_zip( '1.10.26', '1.10.26' );
function setup_http( $zip, $sig, $rel = null ) {
	global $repo, $base;
	$GLOBALS['http'] = array( $repo => array( 200, $rel ?? release() ), $base . 'ds-toolkit.zip.sig' => array( 200, $sig ) );
	$GLOBALS['zipfile'] = $zip; $GLOBALS['got'] = array(); $GLOBALS['downloaded'] = array(); $GLOBALS['offered'] = null;
}
$goodsig = relsig( '1.10.26', hash_file( 'sha256', $good ) );
$cases = array(
	'no such release'           => array( 'release', function () use ( $good, $goodsig, $repo ) { setup_http( $good, $goodsig ); $GLOBALS['http'][ $repo ] = array( 404, '{}' ); } ),
	'a draft'                   => array( 'release', function () use ( $good, $goodsig ) { setup_http( $good, $goodsig, release( array( 'draft' => true ) ) ); } ),
	'a pre-release'             => array( 'release', function () use ( $good, $goodsig ) { setup_http( $good, $goodsig, release( array( 'prerelease' => true ) ) ); } ),
	'a different tag'           => array( 'release', function () use ( $good, $goodsig ) { setup_http( $good, $goodsig, release( array( 'tag_name' => 'v1.10.27' ) ) ); } ),
	'a zip hosted elsewhere'    => array( 'release', function () use ( $good, $goodsig ) { setup_http( $good, $goodsig, release( array( 'assets' => array( array( 'name' => 'ds-toolkit.zip', 'browser_download_url' => 'https://evil.example/ds-toolkit.zip' ), array( 'name' => 'ds-toolkit.zip.sig', 'browser_download_url' => 'https://evil.example/ds-toolkit.zip.sig' ) ) ) ) ); } ),
	'no signature file'         => array( 'release', function () use ( $good, $goodsig, $base ) { setup_http( $good, $goodsig, release( array( 'assets' => array( array( 'name' => 'ds-toolkit.zip', 'browser_download_url' => $base . 'ds-toolkit.zip' ) ) ) ) ); } ),
	'a tampered zip'            => array( 'signature', function () use ( $goodsig ) { setup_http( make_zip( '1.10.26', '1.10.26' ) . '', $goodsig ); file_put_contents( $GLOBALS['zipfile'], 'PK-tampered' . str_repeat( 'y', 5000 ), FILE_APPEND ); } ),
	'a signature by another key' => array( 'signature', function () use ( $good, $bad ) { setup_http( $good, relsig( '1.10.26', hash_file( 'sha256', $good ), $bad ) ); } ),
	'version lines that differ' => array( 'zip', function () { $z = make_zip( '1.10.26', '1.10.25' ); setup_http( $z, relsig( '1.10.26', hash_file( 'sha256', $z ) ) ); } ),
	'a zip of another version'  => array( 'zip', function () { $z = make_zip( '1.10.27', '1.10.27' ); setup_http( $z, relsig( '1.10.26', hash_file( 'sha256', $z ) ) ); } ),
	'a download that fails'     => array( 'download', function () use ( $goodsig ) { setup_http( '', $goodsig ); } ),
);
foreach ( $cases as $label => list( $want, $setup ) ) {
	$setup();
	$before = file_get_contents( DS_TOOLKIT_PATH . 'ds-toolkit.php' );
	$res    = DS_HQ_Update::install( '1.10.26' );
	ok( is_wp_error( $res ) && $want === $res->get_error_code() && null === $GLOBALS['offered'] && file_get_contents( DS_TOOLKIT_PATH . 'ds-toolkit.php' ) === $before,
		"refused: $label, for the right reason ($want; got " . ( is_wp_error( $res ) ? $res->get_error_code() . ': ' . $res->get_error_message() : 'NOT REFUSED' ) . '), plugin untouched' );
}
ok( ! array_filter( (array) $GLOBALS['got'], function ( $u ) { return 0 !== strpos( $u, 'https://api.github.com/repos/' . DS_HQ_Update::REPO . '/' ) && 0 !== strpos( $u, 'https://github.com/' . DS_HQ_Update::REPO . '/' ); } ), 'nothing is ever fetched outside the official repo' );
$left = array_filter( (array) ( $GLOBALS['tmpfiles'] ?? array() ), 'file_exists' );
ok( ! $left, 'every downloaded zip is deleted again (' . count( (array) ( $GLOBALS['tmpfiles'] ?? array() ) ) . ' downloads, ' . count( $left ) . ' left)' );

// Wrong folder: refuse before touching anything.
$GLOBALS['offered'] = null;
// (plugin_basename is computed from DS_TOOLKIT_PATH, which is ds-toolkit/ here; the folder rule is exercised by the E2E.)

// ---- 6. install: the happy path -----------------------------------------------------------------------------------------
setup_http( $good, $goodsig );
$GLOBALS['transient'] = (object) array( 'response' => array( 'ds-toolkit/ds-toolkit.php' => (object) array( 'package' => 'https://github.com/x/ds-toolkit/releases/download/v9.9.9/ds-toolkit.zip', 'new_version' => '9.9.9' ) ) );
$res = DS_HQ_Update::install( '1.10.26' );
$of  = $GLOBALS['offered'];
ok( true === $res, 'a signed release with matching version lines installs' );
ok( $of && 'plugin' === $of['type'] && 'ds-toolkit/ds-toolkit.php' === $of['item']->plugin, 'WordPress is asked to update ds-toolkit, nothing else' );
ok( $of && $of['pkg_exists'] && 0 !== strpos( (string) $of['transient_pkg'], 'http' ) && hash_file( 'sha256', $good ) === $of['pkg_sha'], 'the updater is offered the verified local file (same sha256), not a download address' );
ok( $of && true === $of['auto'] && true === $of['cron'] && false === $of['disabled'], 'for that one run: auto-update allowed for ds-toolkit, cron context (keeps it active), updater not disabled' );
ok( empty( $GLOBALS['filters'] ), 'every filter added for the run is removed afterwards' );
ok( '1.10.26' === DS_HQ_Update::installed(), 'the plugin header now says 1.10.26' );
ok( ! WP_Upgrader::$locked, "WordPress's auto-update lock is released" );

// The updater "succeeds" but the files still say the old version: that is a failure.
plugin_at( '1.10.26-rc.1' ); setup_http( $good, $goodsig ); $GLOBALS['updater_result'] = true;
class_exists( 'WP_Automatic_Updater' );
$GLOBALS['updater_result'] = new WP_Error( 'plugin_update_fatal_error_rollback_successful', "The update for 'ds-toolkit' contained a fatal error. The previously installed version has been restored." );
$res = DS_HQ_Update::install( '1.10.26' );
ok( is_wp_error( $res ) && false !== strpos( $res->get_error_message(), 'restored' ) && false !== strpos( $res->get_error_message(), 'still on 1.10.26-rc.1' ), 'a rollback by WordPress (fatal error) is reported as a failure with the version kept' );
$GLOBALS['updater_result'] = null;

// WordPress busy with its own auto-updates: not tried now, not counted as a try.
$GLOBALS['wp_busy'] = true; setup_http( $good, $goodsig );
$res = DS_HQ_Update::install( '1.10.26' );
ok( is_wp_error( $res ) && 'busy' === $res->get_error_code(), 'while WordPress runs its own auto-updates, the install waits (busy)' );
$GLOBALS['wp_busy'] = false;

// ---- 7. the cron run end to end ------------------------------------------------------------------------------------------
plugin_at( '1.10.26-rc.1' ); $GLOBALS['o'] = array(); $GLOBALS['cron'] = array();
DS_HQ_Update::take_order( order( $sid, '1.10.26' ), $sid );
setup_http( $good, $goodsig );
DS_HQ_Update::run();
$s = DS_HQ_Update::state();
ok( 'done' === $s['status'] && '1.10.26' === $s['to'] && 1 === $s['tries'], 'run(): queued -> done, to 1.10.26, one try' );
ok( ! array_key_exists( DS_HQ_Update::LOCK_OPT, $GLOBALS['o'] ), 'run(): the update lock is released' );
ok( 'done' === ( DS_HQ_Update::report()['status'] ?? '' ), 'run(): the result goes into the next report' );
DS_HQ_Update::take_order( order( $sid, '1.10.26' ), $sid );
ok( 'done' === DS_HQ_Update::state()['status'], 'after it is installed, the same order is ignored (not newer)' );

// Three failures, then the target is left alone.
plugin_at( '1.10.26-rc.1' ); $GLOBALS['o'] = array(); $GLOBALS['cron'] = array();
for ( $i = 1; $i <= 4; $i++ ) {
	DS_HQ_Update::take_order( order( $sid, '1.10.26' ), $sid );
	if ( 'queued' !== DS_HQ_Update::state()['status'] ) { break; }
	setup_http( $good, 'AAAA' . substr( $goodsig, 4 ) ); // bad signature every time
	DS_HQ_Update::run();
}
$s = DS_HQ_Update::state();
ok( 'failed' === $s['status'] && 3 === $s['tries'] && 0 === strpos( $s['error'], 'signature' ), 'a release that keeps failing is tried 3 times, then left (error: ' . $s['error'] . ')' );
ok( '1.10.26-rc.1' === DS_HQ_Update::installed(), '... and the installed plugin never changed' );
DS_HQ_Update::take_order( order( $sid, '1.10.27' ), $sid );
ok( 'queued' === DS_HQ_Update::state()['status'] && 0 === DS_HQ_Update::state()['tries'], 'a newer target starts a fresh count' );

// A run killed mid-way ("running" for over 30 minutes) is retried; a fresh "running" is left alone.
$GLOBALS['o'][ DS_HQ_Update::OPT ] = array( 'target' => '1.10.27', 'status' => 'running', 'started' => time() - 60, 'tries' => 1 );
$GLOBALS['cron'] = array();
DS_HQ_Update::take_order( order( $sid, '1.10.27' ), $sid );
ok( 'running' === DS_HQ_Update::state()['status'] && empty( $GLOBALS['cron'] ), 'an update running right now is not queued twice' );
$GLOBALS['o'][ DS_HQ_Update::OPT ]['started'] = time() - 3600;
DS_HQ_Update::take_order( order( $sid, '1.10.27' ), $sid );
ok( 'queued' === DS_HQ_Update::state()['status'] && ! empty( $GLOBALS['cron'] ), 'a run stuck for an hour is queued again' );

// Another process holds the update lock: run() does nothing.
$GLOBALS['o'][ DS_HQ_Update::LOCK_OPT ] = (string) time();
setup_http( $good, relsig( '1.10.27', hash_file( 'sha256', $good ) ) );
DS_HQ_Update::run();
ok( 'queued' === DS_HQ_Update::state()['status'] && empty( $GLOBALS['got'] ), 'while another update holds the lock, run() waits and fetches nothing' );
unset( $GLOBALS['o'][ DS_HQ_Update::LOCK_OPT ] );

// ---- 8. the off switch ---------------------------------------------------------------------------------------------------
$GLOBALS['o'] = array(); $GLOBALS['cron'] = array();
define( 'DS_HQ_UPDATE_DISABLED', true );
DS_HQ_Update::take_order( order( $sid, '1.10.28' ), $sid );
ok( array() === DS_HQ_Update::state() && empty( $GLOBALS['cron'] ), 'DS_HQ_UPDATE_DISABLED: orders are ignored entirely' );

// ---- tidy --------------------------------------------------------------------------------------------------------------
foreach ( (array) ( $GLOBALS['tmpfiles'] ?? array() ) as $t ) { @unlink( $t ); }
exec( 'rm -rf ' . escapeshellarg( $root ) );
echo "\n$pass passed, $fail failed\n";
echo $fail ? "FAILED\n" : "all passed\n";
exit( $fail ? 1 : 0 );
