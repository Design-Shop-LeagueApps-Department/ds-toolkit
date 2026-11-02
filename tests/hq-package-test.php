<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
// Run: php tests/hq-package-test.php        (plain PHP 7.4+ with sodium and zip, no WordPress, no network)
//
// Guards DS_HQ_Package (features/class-ds-hq-package.php), HQ's Plugin Store fetch from a blueprint:
//   - a partner site cloned from a blueprint (any other WP Engine install name) answers 404, even to HQ's signature;
//   - on a blueprint, unsigned calls, a signature by another key, an old signature, and a signature made for a
//     different slug, type or nonce all answer 404;
//   - slug and type are limited (no path, no "..", plugin|theme only);
//   - the listing is signed by the site's own key over its sha256 and verifies with the public half;
//   - the zip holds one top folder named after the slug and leaves out symlinks, .git, node_modules, logs, .DS_Store.

error_reporting( E_ALL & ~E_DEPRECATED );
$child = in_array( '--clone', $argv, true );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'WPINC', 'wp-includes' );
define( 'PWP_NAME', $child ? 'partnerclub' : 'dslaunchpad7' );
define( 'WP_PLUGIN_DIR', sys_get_temp_dir() . '/dshqpkg-none' );
$table_prefix = 'wp_';

$hq = sodium_crypto_sign_keypair();
define( 'DS_HQ_LINK_PUBKEY', base64_encode( sodium_crypto_sign_publickey( $hq ) ) );
$site = sodium_crypto_sign_keypair();

$GLOBALS['o'] = array();
$GLOBALS['plugins'] = array();
class WP_Error { public $c; public $d; public function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->d = $d; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o'] ) ? $GLOBALS['o'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['o'][ $k ] = $v; return true; }
function apply_filters( $h, $v ) { return $v; }
function add_action() {}
function home_url() { return 'https://dslaunchpad7.wpenginepowered.com'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_tempnam( $n ) { return tempnam( sys_get_temp_dir(), 'dshqpkg' ); }
function size_format( $b ) { return $b . ' B'; }
function get_plugins( $folder = '' ) { return $GLOBALS['plugins']; }
function wp_get_themes() { return array(); }
function register_rest_route() {}
class FakeRes { public $data; public $h = array(); public function header( $k, $v ) { $this->h[ $k ] = $v; } }
function rest_ensure_response( $d ) { $r = new FakeRes(); $r->data = $d; return $r; }
class WP_REST_Request {
	public $p; public $h;
	public function __construct( $p, $h ) { $this->p = $p; $this->h = $h; }
	public function get_param( $k ) { return $this->p[ $k ] ?? null; }
	public function get_header( $k ) { return $this->h[ $k ] ?? null; }
}
require dirname( __DIR__ ) . '/features/class-ds-hq-link.php';
require dirname( __DIR__ ) . '/features/class-ds-hq-package.php';
$GLOBALS['o']['ds_hq_link'] = array( 'sk' => base64_encode( sodium_crypto_sign_secretkey( $site ) ), 'sid' => DS_HQ_Link::install_id() );

$fails = 0;
function ok( $cond, $label ) { global $fails; echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . "\n"; if ( ! $cond ) { $fails++; } }
function call( $p, $what_nonce, $key = null, $t = null ) {
	global $hq;
	$t   = $t ?? time();
	$sk  = $key ?? sodium_crypto_sign_secretkey( $hq );
	$sig = sodium_crypto_sign_detached( "dshq-hq\npackage\ndslaunchpad7.wpenginepowered.com\n$t\n$what_nonce", $sk );
	return DS_HQ_Package::serve( new WP_REST_Request( $p, array( 'x_dshq_hq_time' => (string) $t, 'x_dshq_hq_sig' => base64_encode( $sig ) ) ) );
}
function is404( $r ) { return $r instanceof WP_Error && 'rest_no_route' === $r->c; } // the unsigned-caller 404, not 'not_here'
$n = bin2hex( random_bytes( 16 ) );

if ( $child ) {
	ok( ! DS_HQ_Package::is_blueprint(), 'clone: not a blueprint' );
	ok( is404( call( array( 'n' => $n ), "$n||" ) ), 'clone: HQ-signed listing is a 404' );
	exit( $fails ? 1 : 0 );
}

ok( DS_HQ_Package::is_blueprint(), 'dslaunchpad7 is a blueprint' );
ok( is404( DS_HQ_Package::serve( new WP_REST_Request( array( 'n' => $n ), array() ) ) ), 'unsigned call is a 404' );
$other = sodium_crypto_sign_keypair();
ok( is404( call( array( 'n' => $n ), "$n||", sodium_crypto_sign_secretkey( $other ) ) ), 'signature by another key is a 404' );
ok( is404( call( array( 'n' => $n ), "$n||", null, time() - 600 ) ), 'ten-minute-old signature is a 404' );
ok( is404( call( array( 'n' => $n, 'type' => 'plugin', 'slug' => 'bb-plugin' ), "$n|plugin|wp-defender" ) ), 'signature for another slug is a 404' );
ok( is404( call( array( 'n' => $n, 'type' => 'theme', 'slug' => 'bb-plugin' ), "$n|plugin|bb-plugin" ) ), 'signature for another type is a 404' );
$n2 = bin2hex( random_bytes( 16 ) );
ok( is404( call( array( 'n' => $n2, 'type' => 'plugin', 'slug' => 'bb-plugin' ), "$n|plugin|bb-plugin" ) ), 'signature for another nonce is a 404' );
$GLOBALS['plugins'] = array();
$r = call( array( 'n' => $n, 'type' => 'plugin', 'slug' => 'missing-plugin' ), "$n|plugin|missing-plugin" );
ok( $r instanceof WP_Error && 'not_here' === $r->c, 'a correctly signed zip call gets past the signature (answers not_here for a missing folder)' );
foreach ( array( '../wp-config', 'a/b', '.hidden', 'BB-Plugin' ) as $bad ) {
	ok( is404( call( array( 'n' => $n, 'type' => 'plugin', 'slug' => $bad ), "$n|plugin|$bad" ) ), "slug '$bad' is refused" );
}
ok( is404( call( array( 'n' => $n, 'type' => 'mu-plugin', 'slug' => 'x' ), "$n|mu-plugin|x" ) ), 'type other than plugin|theme is refused' );
ok( is404( call( array( 'n' => 'xyz', 'type' => '', 'slug' => '' ), 'xyz||' ) ), 'malformed nonce is refused' );

// Listing: signed by the site's key over the sha256 of the JSON it returns.
$GLOBALS['plugins'] = array( 'bb-plugin/fl-builder.php' => array( 'Version' => '2.11.2' ), 'hello.php' => array( 'Version' => '1.0' ) );
$r = call( array( 'n' => $n ), "$n||" );
ok( $r instanceof FakeRes && 'no-store' === ( $r->h['Cache-Control'] ?? '' ), 'listing answers, no-store' );
$items = json_decode( $r->data['items'], true );
ok( '2.11.2' === ( $items['plugin']['bb-plugin'] ?? '' ) && ! isset( $items['plugin']['hello.php'] ), 'listing names folder plugins with their version' );
ok( sodium_crypto_sign_verify_detached( base64_decode( $r->data['sig'] ), "dshq-package-list\n$n\n" . hash( 'sha256', $r->data['items'] ), sodium_crypto_sign_publickey( $site ) ), 'listing signature verifies with the site public key' );
ok( ! sodium_crypto_sign_verify_detached( base64_decode( $r->data['sig'] ), "dshq-package-list\n$n2\n" . hash( 'sha256', $r->data['items'] ), sodium_crypto_sign_publickey( $site ) ), 'listing signature is bound to the nonce' );

// Zip contents.
$src = sys_get_temp_dir() . '/dshqpkg-src-' . getmypid();
@mkdir( "$src/inc", 0777, true ); @mkdir( "$src/.git", 0777, true ); @mkdir( "$src/node_modules/x", 0777, true );
file_put_contents( "$src/plugin.php", "<?php\n// Plugin Name: T\n" );
file_put_contents( "$src/inc/a.php", "<?php\n" );
file_put_contents( "$src/.git/config", 'x' );
file_put_contents( "$src/node_modules/x/i.js", 'x' );
file_put_contents( "$src/debug.log", 'x' );
file_put_contents( "$src/.DS_Store", 'x' );
@symlink( '/etc/hosts', "$src/link.txt" );
$zip = DS_HQ_Package::build_zip( $src, 'tplug' );
$z   = new ZipArchive(); $z->open( $zip );
$names = array(); for ( $i = 0; $i < $z->numFiles; $i++ ) { $names[] = $z->getNameIndex( $i ); }
$z->close(); @unlink( $zip );
ok( in_array( 'tplug/plugin.php', $names, true ) && in_array( 'tplug/inc/a.php', $names, true ), 'zip holds the files under tplug/' );
ok( ! array_filter( $names, function ( $x ) { return 0 !== strpos( $x, 'tplug/' ); } ), 'zip has one top folder' );
ok( ! preg_grep( '#\.git|node_modules|\.log$|\.DS_Store|link\.txt#', $names ), 'zip leaves out .git, node_modules, logs, .DS_Store and symlinks' );
exec( 'rm -rf ' . escapeshellarg( $src ) );

// A clone carries the same code under its own install name: run that case in a child process (PWP_NAME is a constant).
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --clone', $out, $code );
echo implode( "\n", $out ) . "\n";
ok( 0 === $code, 'clone case passed' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
