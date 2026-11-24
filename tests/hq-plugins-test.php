<?php
// Run: php tests/hq-plugins-test.php      (plain PHP 7.4+ with sodium and zip, no WordPress, no network)
//
// Guards DS_HQ_Plugins (features/class-ds-hq-plugins.php): HQ can ask for "<wordpress.org plugin> to <version>", and
// the site installs only an official wordpress.org release of a plugin it already runs:
//   - an order counts only if HQ signed it for THIS install ("dshq-plugin" message), it is fresh, the slug and version
//     are plain, the plugin is installed here and the version is newer (tampered, other site, stale, a toolkit-order
//     signature replayed, downgrade, unknown slug: refused);
//   - the installed copy must be the wordpress.org plugin (main file matches wordpress.org's checksums for its version),
//     so a custom plugin sharing a slug, or a tampered copy, is never replaced;
//   - the zip comes only from downloads.wordpress.org, and must hold exactly the files wordpress.org lists for the new
//     version with matching sha256 (extra, missing, changed, or outside the plugin folder: nothing installed);
//   - orders only queue (cron installs), the off switch / multisite subsite / WordPress < 6.6 win, busy is a wait;
//   - the happy path hands WordPress's updater exactly the verified local zip, for that one plugin, keeps it active,
//     and removes every filter it added;
//   - DS_HQ_Update::clear_stale_lock() deletes an update lock older than an hour, even when the cache hides it, and
//     leaves a fresh one alone.

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
error_reporting( E_ALL & ~E_DEPRECATED );

$root = sys_get_temp_dir() . '/dshq-plugins-test-' . getmypid();
@mkdir( $root . '/wp-admin/includes', 0777, true );
foreach ( array( 'admin.php', 'class-wp-upgrader.php', 'file.php', 'plugin.php' ) as $f ) {
	file_put_contents( $root . '/wp-admin/includes/' . $f, '<?php' );
}
@mkdir( $root . '/wp-content/plugins', 0777, true );
define( 'ABSPATH', $root . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_PLUGIN_DIR', $root . '/wp-content/plugins' );
define( 'HOUR_IN_SECONDS', 3600 );

// ---- WordPress stand-ins -------------------------------------------------------------------------------------
class WP_Error {
	private $c; private $m; private $d;
	public function __construct( $c = '', $m = '', $d = '' ) { $this->c = $c; $this->m = $m; $this->d = $d; }
	public function get_error_code() { return $this->c; }
	public function get_error_message() { return $this->m; }
	public function get_error_data() { return $this->d; }
}
$GLOBALS['wp_version'] = '7.1.2';
function is_multisite() { return ! empty( $GLOBALS['ms'] ); }
function is_main_site() { return empty( $GLOBALS['subsite'] ); }
function is_main_network() { return true; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
$GLOBALS['o'] = array(); $GLOBALS['cron'] = array(); $GLOBALS['filters'] = array(); $GLOBALS['cache'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o'] ) ? $GLOBALS['o'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['o'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['o'][ $k ] ); return true; }
function wp_cache_get( $k, $g = '' ) { return $GLOBALS['cache'][ "$g:$k" ] ?? false; }
function wp_cache_set( $k, $v, $g = '' ) { $GLOBALS['cache'][ "$g:$k" ] = $v; return true; }
function wp_cache_delete( $k, $g = '' ) { unset( $GLOBALS['cache'][ "$g:$k" ] ); return true; }
function add_action( $h = '', $cb = null, $p = 10, $n = 1 ) { if ( 'http_api_debug' === $h ) { add_filter( $h, $cb, $p, $n ); } }
function remove_action( $h, $cb, $p = 10 ) { return remove_filter( $h, $cb, $p ); }
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $h ] ?? false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['cron'][ $h ] ); }
function wp_rand( $a, $b ) { return $a; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['filters'][ $h ][] = $cb; return true; }
function remove_filter( $h, $cb, $p = 10 ) { foreach ( $GLOBALS['filters'][ $h ] ?? array() as $i => $f ) { if ( $f === $cb ) { unset( $GLOBALS['filters'][ $h ][ $i ] ); } } if ( empty( $GLOBALS['filters'][ $h ] ) ) { unset( $GLOBALS['filters'][ $h ] ); } return true; }
function apply_filters( $h, $v, ...$a ) { foreach ( $GLOBALS['filters'][ $h ] ?? array() as $f ) { $v = $f( $v, ...$a ); } return $v; }
function __return_true() { return true; }
function __return_false() { return false; }
function get_site_transient( $k ) { return apply_filters( 'site_transient_' . $k, false ); }

// Installed plugins: folder => [ main file name, version, content of the main file, active ].
$GLOBALS['installed'] = array();
function put_plugin( $slug, $ver, $main = 'plugin.php', $body = null, $active = true ) {
	@mkdir( WP_PLUGIN_DIR . "/$slug", 0777, true );
	$body = null === $body ? "<?php\n/* Plugin Name: $slug\n * Version: $ver */\n" : $body;
	file_put_contents( WP_PLUGIN_DIR . "/$slug/$main", $body );
	$GLOBALS['installed'][ $slug ] = array( $main, $ver, $active );
}
function get_plugins() { $o = array(); foreach ( $GLOBALS['installed'] as $s => $p ) { $o[ "$s/{$p[0]}" ] = array( 'Version' => $p[1], 'Name' => $s ); } return $o; }
function is_plugin_active( $f ) { $s = dirname( $f ); return ! empty( $GLOBALS['installed'][ $s ][2] ); }
function activate_plugin( $f ) { $GLOBALS['installed'][ dirname( $f ) ][2] = true; $GLOBALS['activated'] = $f; }
function wp_clean_plugins_cache() {}

$GLOBALS['http'] = array(); $GLOBALS['got'] = array();
function wp_remote_get( $url, $args = array() ) { $GLOBALS['got'][] = $url; return isset( $GLOBALS['http'][ $url ] ) ? array( 'code' => $GLOBALS['http'][ $url ][0], 'body' => $GLOBALS['http'][ $url ][1] ) : new WP_Error( 'http', 'no route' ); }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
$GLOBALS['zips'] = array();
function download_url( $url, $timeout = 300 ) {
	$GLOBALS['got'][] = $url;
	if ( ! isset( $GLOBALS['zips'][ $url ] ) ) { return new WP_Error( 'http_404', 'Not Found', array( 'code' => 404 ) ); }
	$t = tempnam( sys_get_temp_dir(), 'dz' ); copy( $GLOBALS['zips'][ $url ], $t ); return $t;
}

class FakeWpdb {
	public $options = 'wp_options';
	public function prepare( $q, ...$a ) { return array( $q, $a ); }
	public function query( $p ) { return 1; }
	public function get_var( $p ) { return $GLOBALS['o'][ $p[1][0] ] ?? null; }
}
$GLOBALS['wpdb'] = null; // DS_Lock: no database = no race

class WP_Upgrader {
	public static $locked = false;
	public static function create_lock( $n ) {
		if ( ! empty( $GLOBALS['wp_busy'] ) || self::$locked ) { return false; }
		// Like core: a row that exists blocks, unless get_option() (which the cache may hide) says it is older than an hour.
		if ( array_key_exists( $n . '.lock', $GLOBALS['o'] ) ) { return false; }
		self::$locked = true; return true;
	}
	public static function release_lock( $n ) { self::$locked = false; }
}
class WP_Automatic_Updater {
	public function has_fatal_error() { return false; }
	public function update( $type, $item ) {
		$GLOBALS['updated'] = $item;
		$t = get_site_transient( 'update_plugins' );
		$GLOBALS['package_seen'] = $t->response[ $item->plugin ]->package ?? '';
		$GLOBALS['allowed'] = apply_filters( 'auto_update_plugin', false, $item );
		if ( ! empty( $GLOBALS['fail_install'] ) ) { return new WP_Error( 'x', 'The update contained a fatal error.' ); }
		// "Install": unpack the verified zip over the plugin folder and take its version.
		$z = new ZipArchive(); $z->open( $GLOBALS['package_seen'] ); $z->extractTo( WP_PLUGIN_DIR ); $z->close();
		$GLOBALS['installed'][ $item->slug ][1] = $item->new_version;
		$GLOBALS['installed'][ $item->slug ][2] = false; // WordPress deactivates during the swap; the code must reactivate
		return true;
	}
}

require dirname( __DIR__ ) . '/includes/class-ds-lock.php';
require dirname( __DIR__ ) . '/features/class-ds-hq-update.php';
require dirname( __DIR__ ) . '/features/class-ds-hq-plugins.php';

// ---- keys, helpers -------------------------------------------------------------------------------------------
$hq  = sodium_crypto_sign_keypair();
$bad = sodium_crypto_sign_keypair();
define( 'DS_HQ_LINK_PUBKEY', base64_encode( sodium_crypto_sign_publickey( $hq ) ) );
$SID = 'wpe:testsite.4128';
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "ok   $m\n"; } else { $fail++; echo "FAIL $m\n"; } }
function porder( $sid, $slug, $ver, $t = null, $kp = null, $prefix = 'dshq-plugin' ) {
	global $hq; $t = null === $t ? time() : $t; $kp = $kp ?: $hq;
	$msg = 'dshq-update' === $prefix ? "dshq-update\n$sid\n$ver\n$t" : "$prefix\n$sid\n$slug\n$ver\n$t";
	return array( 'slug' => $slug, 'version' => $ver, 't' => $t, 'sig' => base64_encode( sodium_crypto_sign_detached( $msg, sodium_crypto_sign_secretkey( $kp ) ) ) );
}
// A wordpress.org-style release: zip + checksum JSON, registered at the fixed addresses.
function release( $slug, $ver, array $files, $extra = array(), $drop = '', $change = '' ) {
	$sums = array();
	foreach ( $files as $rel => $c ) { $sums[ $rel ] = array( 'md5' => md5( $c ), 'sha256' => hash( 'sha256', $c ) ); }
	$GLOBALS['http'][ DS_HQ_Plugins::SUMS . "$slug/$ver.json" ] = array( 200, json_encode( array( 'plugin' => $slug, 'version' => $ver, 'files' => $sums ) ) );
	$zp = sys_get_temp_dir() . "/rel-$slug-$ver-" . getmypid() . '.zip';
	@unlink( $zp ); $z = new ZipArchive(); $z->open( $zp, ZipArchive::CREATE );
	$z->addEmptyDir( $slug );
	foreach ( $files + $extra as $rel => $c ) {
		if ( $rel === $drop ) { continue; }
		$z->addFromString( ( 0 === strpos( $rel, '../' ) ? '' : "$slug/" ) . ltrim( $rel, './' ), $rel === $change ? $c . 'x' : $c );
	}
	$z->close();
	$GLOBALS['zips'][ DS_HQ_Plugins::DL . "$slug.$ver.zip" ] = $zp;
	return $zp;
}
function main_body( $slug, $ver ) { return "<?php\n/* Plugin Name: $slug\n * Version: $ver */\n"; }
function reset_site() {
	$GLOBALS['o'] = array(); $GLOBALS['cron'] = array(); $GLOBALS['filters'] = array(); $GLOBALS['cache'] = array();
	$GLOBALS['http'] = array(); $GLOBALS['zips'] = array(); $GLOBALS['got'] = array(); $GLOBALS['installed'] = array();
	unset( $GLOBALS['updated'], $GLOBALS['package_seen'], $GLOBALS['allowed'], $GLOBALS['activated'], $GLOBALS['fail_install'], $GLOBALS['wp_busy'], $GLOBALS['ms'], $GLOBALS['subsite'] );
	$GLOBALS['wp_version'] = '7.1.2'; WP_Upgrader::$locked = false;
}

// ---- 1. order_ok --------------------------------------------------------------------------------------------
reset_site();
put_plugin( 'wp-nested-pages', '3.3.5', 'nestedpages.php' );
list( $o, $s, $v ) = DS_HQ_Plugins::order_ok( porder( $SID, 'wp-nested-pages', '3.3.6' ), $SID );
ok( $o && 'wp-nested-pages' === $s && '3.3.6' === $v, 'a fresh HQ-signed order for an installed plugin, newer version: accepted' );
ok( ! DS_HQ_Plugins::order_ok( porder( 'wpe:other.4128', 'wp-nested-pages', '3.3.6' ), $SID )[0], 'an order signed for another install: refused' );
ok( ! DS_HQ_Plugins::order_ok( porder( $SID, 'wp-nested-pages', '3.3.6', time() - 1000 ), $SID )[0], 'an order older than 15 minutes: refused' );
ok( ! DS_HQ_Plugins::order_ok( porder( $SID, 'wp-nested-pages', '3.3.6', null, $bad ), $SID )[0], 'signed with another key: refused' );
ok( ! DS_HQ_Plugins::order_ok( porder( $SID, 'wp-nested-pages', '3.3.6', null, null, 'dshq-update' ), $SID )[0], 'a ds-toolkit order signature replayed as a plugin order: refused' );
$t = porder( $SID, 'wp-nested-pages', '3.3.6' ); $t['version'] = '3.3.7';
ok( ! DS_HQ_Plugins::order_ok( $t, $SID )[0], 'version changed after signing: refused' );
$t = porder( $SID, 'wp-nested-pages', '3.3.6' ); $t['slug'] = 'akismet';
ok( ! DS_HQ_Plugins::order_ok( $t, $SID )[0], 'slug changed after signing: refused' );
ok( 'not installed on this site' === DS_HQ_Plugins::order_ok( porder( $SID, 'akismet', '5.0' ), $SID )[2], 'a plugin this site does not have: refused' );
ok( ! DS_HQ_Plugins::order_ok( porder( $SID, 'wp-nested-pages', '3.3.5' ), $SID )[0], 'same version as installed: refused' );
ok( ! DS_HQ_Plugins::order_ok( porder( $SID, 'wp-nested-pages', '3.3.4' ), $SID )[0], 'a downgrade: refused' );
ok( ! DS_HQ_Plugins::order_ok( porder( $SID, '../ds-toolkit', '9.0' ), $SID )[0], 'a slug with a path: refused' );
ok( ! DS_HQ_Plugins::order_ok( porder( $SID, 'wp-nested-pages', '3.3.6-beta' ), $SID )[0], 'a pre-release version string: refused' );
ok( ! DS_HQ_Plugins::order_ok( porder( $SID, 'wp-nested-pages', '3.3.6; rm' ), $SID )[0], 'a version with other text: refused' );

// ---- 2. take_orders ------------------------------------------------------------------------------------------
reset_site();
put_plugin( 'wp-nested-pages', '3.3.5', 'nestedpages.php' );
put_plugin( 'redirection', '5.4.2', 'redirection.php' );
DS_HQ_Plugins::take_orders( array( porder( $SID, 'wp-nested-pages', '3.3.6' ), porder( $SID, 'redirection', '5.4.2' ) ), $SID );
$st = DS_HQ_Plugins::state();
ok( 'queued' === ( $st['wp-nested-pages']['status'] ?? '' ) && '3.3.5' === $st['wp-nested-pages']['from'], 'a good order only queues (from = installed)' );
ok( ! empty( $st['redirection']['refused'] ) && empty( $st['redirection']['status'] ), 'a bad order in the same reply is recorded as refused, nothing queued' );
ok( ! empty( $GLOBALS['cron'][ DS_HQ_Plugins::HOOK ] ), 'the install is scheduled on cron, not run in the request' );
ok( empty( $GLOBALS['updated'] ), 'nothing installed while taking the order' );
$GLOBALS['subsite'] = true; $GLOBALS['ms'] = true; reset_site(); put_plugin( 'wp-nested-pages', '3.3.5', 'nestedpages.php' ); $GLOBALS['ms'] = true; $GLOBALS['subsite'] = true;
DS_HQ_Plugins::take_orders( array( porder( $SID, 'wp-nested-pages', '3.3.6' ) ), $SID );
ok( false !== strpos( DS_HQ_Plugins::state()['wp-nested-pages']['refused']['reason'] ?? '', 'multisite' ), 'multisite subsite: refused' );
reset_site(); put_plugin( 'wp-nested-pages', '3.3.5', 'nestedpages.php' ); $GLOBALS['wp_version'] = '5.9.21';
DS_HQ_Plugins::take_orders( array( porder( $SID, 'wp-nested-pages', '3.3.6' ) ), $SID );
ok( false !== strpos( DS_HQ_Plugins::state()['wp-nested-pages']['refused']['reason'] ?? '', 'crash check' ), 'WordPress older than 6.6: refused' );
reset_site(); put_plugin( 'wp-nested-pages', '3.3.5', 'nestedpages.php' );
$GLOBALS['o'][ DS_HQ_Plugins::OPT ] = array( 'wp-nested-pages' => array( 'target' => '3.3.6', 'status' => 'failed', 'tries' => 3 ) );
DS_HQ_Plugins::take_orders( array( porder( $SID, 'wp-nested-pages', '3.3.6' ) ), $SID );
ok( 'failed' === DS_HQ_Plugins::state()['wp-nested-pages']['status'], 'a version that failed 3 times is not queued again' );
reset_site(); $many = array();
for ( $i = 1; $i <= 12; $i++ ) { put_plugin( "plug-$i", '1.0', 'p.php' ); $many[] = porder( $SID, "plug-$i", '1.1' ); }
DS_HQ_Plugins::take_orders( $many, $SID );
ok( 10 === count( array_filter( DS_HQ_Plugins::state(), function ( $x ) { return 'queued' === ( $x['status'] ?? '' ); } ) ), 'at most 10 orders taken from one reply' );

// ---- 3. checksums parsing and zip verification ---------------------------------------------------------------
$p = DS_HQ_Plugins::parse_checksums( json_encode( array( 'files' => array( 'a.php' => array( 'sha256' => str_repeat( 'a', 64 ) ), 'b/c.js' => array( 'sha256' => array( str_repeat( 'b', 64 ), str_repeat( 'c', 64 ) ) ) ) ) ) );
ok( is_array( $p ) && 2 === count( $p['b/c.js'] ), 'checksums: a single hash and a list of hashes both read' );
ok( is_wp_error( DS_HQ_Plugins::parse_checksums( json_encode( array( 'files' => array( '../x.php' => array( 'sha256' => str_repeat( 'a', 64 ) ) ) ) ) ) ), 'checksums: a path climbing out: refused' );
ok( is_wp_error( DS_HQ_Plugins::parse_checksums( '{"files":[]}' ) ), 'checksums: empty list: refused' );
$files = array( 'nestedpages.php' => main_body( 'wp-nested-pages', '3.3.6' ), 'app/a.php' => '<?php // a', 'assets/x.js' => 'x()' );
$sums  = array(); foreach ( $files as $r => $c ) { $sums[ $r ] = array( hash( 'sha256', $c ) ); }
reset_site();
ok( '' === DS_HQ_Plugins::verify_zip( release( 'wp-nested-pages', '3.3.6', $files ), 'wp-nested-pages', $sums ), 'zip with exactly the listed files: passes' );
ok( false !== strpos( DS_HQ_Plugins::verify_zip( release( 'wp-nested-pages', '3.3.6', $files, array( 'evil.php' => '<?php system($_GET[1]);' ) ), 'wp-nested-pages', $sums ), 'does not list' ), 'an extra file: fails' );
ok( false !== strpos( DS_HQ_Plugins::verify_zip( release( 'wp-nested-pages', '3.3.6', $files, array(), 'app/a.php' ), 'wp-nested-pages', $sums ), 'missing' ), 'a missing file: fails' );
ok( false !== strpos( DS_HQ_Plugins::verify_zip( release( 'wp-nested-pages', '3.3.6', $files, array(), '', 'app/a.php' ), 'wp-nested-pages', $sums ), 'does not match' ), 'a changed file: fails' );
ok( false !== strpos( DS_HQ_Plugins::verify_zip( release( 'wp-nested-pages', '3.3.6', $files, array( '../ds-toolkit/x.php' => '<?php' ) ), 'wp-nested-pages', $sums ), 'outside' ), 'a file outside the plugin folder: fails' );

// ---- 4. install ----------------------------------------------------------------------------------------------
function setup_happy() {
	reset_site();
	$old = array( 'nestedpages.php' => main_body( 'wp-nested-pages', '3.3.5' ), 'app/a.php' => '<?php // old' );
	put_plugin( 'wp-nested-pages', '3.3.5', 'nestedpages.php', $old['nestedpages.php'], true );
	file_put_contents( WP_PLUGIN_DIR . '/wp-nested-pages/app.php', '' );
	release( 'wp-nested-pages', '3.3.5', $old );
	release( 'wp-nested-pages', '3.3.6', array( 'nestedpages.php' => main_body( 'wp-nested-pages', '3.3.6' ), 'app/a.php' => '<?php // new', 'assets/x.js' => 'x()' ) );
	$GLOBALS['o'][ DS_HQ_Plugins::OPT ] = array( 'wp-nested-pages' => array( 'target' => '3.3.6', 'status' => 'queued', 'from' => '3.3.5', 'queued' => time(), 'tries' => 0 ) );
}
setup_happy();
DS_HQ_Plugins::run();
$st = DS_HQ_Plugins::state()['wp-nested-pages'];
ok( 'done' === $st['status'] && '3.3.6' === $st['to'], 'happy path: installed, state done, to = 3.3.6' );
ok( isset( $GLOBALS['package_seen'] ) && 0 !== strpos( $GLOBALS['package_seen'], 'http' ) && 'wp-nested-pages/nestedpages.php' === $GLOBALS['updated']->plugin, "WordPress's updater got the verified local zip, for this plugin only" );
ok( true === $GLOBALS['allowed'], 'the auto-update filter allows exactly this plugin' );
ok( 'wp-nested-pages/nestedpages.php' === ( $GLOBALS['activated'] ?? '' ), 'an active plugin is active again after the swap' );
ok( empty( $GLOBALS['filters'] ), 'every filter it added is gone afterwards' );
ok( 0 === count( array_filter( $GLOBALS['got'], function ( $u ) { return 0 !== strpos( $u, 'https://downloads.wordpress.org/' ); } ) ), 'every download came from downloads.wordpress.org' );
ok( ! WP_Upgrader::$locked, "WordPress's update lock is released" );

setup_happy();
file_put_contents( WP_PLUGIN_DIR . '/wp-nested-pages/nestedpages.php', main_body( 'wp-nested-pages', '3.3.5' ) . "// changed\n" );
DS_HQ_Plugins::run();
$st = DS_HQ_Plugins::state()['wp-nested-pages'];
ok( 'failed' === $st['status'] && 0 === strpos( $st['error'], 'modified' ) && 3 === $st['tries'] && empty( $GLOBALS['updated'] ), 'installed copy differs from wordpress.org: refused for good, nothing installed' );

setup_happy();
unset( $GLOBALS['http'][ DS_HQ_Plugins::SUMS . 'wp-nested-pages/3.3.5.json' ] );
$GLOBALS['http'][ DS_HQ_Plugins::SUMS . 'wp-nested-pages/3.3.5.json' ] = array( 404, '' );
DS_HQ_Plugins::run();
$st = DS_HQ_Plugins::state()['wp-nested-pages'];
ok( 'failed' === $st['status'] && 0 === strpos( $st['error'], 'not-wporg' ) && empty( $GLOBALS['updated'] ), 'no wordpress.org checksums for the installed version (custom plugin): refused, nothing installed' );

setup_happy();
release( 'wp-nested-pages', '3.3.6', array( 'nestedpages.php' => main_body( 'wp-nested-pages', '3.3.6' ), 'app/a.php' => '<?php // new', 'assets/x.js' => 'x()' ), array( 'shell.php' => '<?php' ) );
DS_HQ_Plugins::run();
$st = DS_HQ_Plugins::state()['wp-nested-pages'];
ok( 'failed' === $st['status'] && 0 === strpos( $st['error'], 'checksums' ) && empty( $GLOBALS['updated'] ), 'zip with a file wordpress.org does not list: nothing installed' );

setup_happy();
$GLOBALS['wp_busy'] = true;
DS_HQ_Plugins::run();
$st = DS_HQ_Plugins::state()['wp-nested-pages'];
ok( 'queued' === $st['status'] && 0 === (int) $st['tries'] && 1 === (int) $st['waits'], 'WordPress busy: back in the queue, not counted as a try' );

setup_happy();
$GLOBALS['fail_install'] = true;
DS_HQ_Plugins::run();
$st = DS_HQ_Plugins::state()['wp-nested-pages'];
ok( 'failed' === $st['status'] && 1 === (int) $st['tries'] && false !== strpos( $st['error'], 'still on 3.3.5' ), 'a crash rollback: failed, one try used, says the version it is still on' );

setup_happy();
$GLOBALS['installed']['wp-nested-pages'][1] = '3.3.6';
DS_HQ_Plugins::run();
ok( 'done' === DS_HQ_Plugins::state()['wp-nested-pages']['status'] && empty( $GLOBALS['updated'] ), 'already updated some other way: done, nothing installed' );

// ---- 5. the stale lock ---------------------------------------------------------------------------------------
reset_site();
$GLOBALS['wpdb'] = new FakeWpdb();
$GLOBALS['o']['auto_updater.lock'] = time() - 5 * 86400;
$GLOBALS['cache']['options:notoptions'] = array( 'auto_updater.lock' => true );
ok( true === DS_HQ_Update::clear_stale_lock() && ! isset( $GLOBALS['o']['auto_updater.lock'] ) && empty( $GLOBALS['cache']['options:notoptions']['auto_updater.lock'] ), 'a 5-day-old lock hidden by the cache: deleted, cache cleaned' );
$GLOBALS['o']['auto_updater.lock'] = time() - 120;
ok( false === DS_HQ_Update::clear_stale_lock() && isset( $GLOBALS['o']['auto_updater.lock'] ), 'a lock 2 minutes old: left alone' );
$GLOBALS['wpdb'] = null;

setup_happy();
$GLOBALS['wpdb'] = new FakeWpdb();
$GLOBALS['o']['auto_updater.lock'] = time() - 2 * 86400;
DS_HQ_Plugins::run();
ok( 'done' === DS_HQ_Plugins::state()['wp-nested-pages']['status'], 'a stale lock no longer blocks the install' );
$GLOBALS['wpdb'] = null;

// ---- 6. inventory ------------------------------------------------------------------------------------------
reset_site();
put_plugin( 'wp-nested-pages', '3.3.6', 'nestedpages.php', null, true );
put_plugin( 'redirection', '5.4.2', 'redirection.php', null, false );
$inv = DS_HQ_Plugins::inventory();
ok( array( 'v' => '3.3.6', 'a' => 1 ) === $inv['wp-nested-pages'] && 0 === $inv['redirection']['a'], 'inventory: slug, version, active' );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
