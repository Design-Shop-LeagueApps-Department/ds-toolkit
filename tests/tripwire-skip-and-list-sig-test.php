<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * Tripwire 1.12.0: what the content scan skips, and the signed remote allow list. Stub-style, no WordPress, no network:
 *
 *   php tests/tripwire-skip-and-list-sig-test.php
 *
 * The list signature is checked with a throwaway key (DS_TOOLKIT_RELEASE_PUBKEY replaces the release key in tests).
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DS_TOOLKIT_VERSION', 'test' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );
$kp = sodium_crypto_sign_keypair();
define( 'DS_TOOLKIT_RELEASE_PUBKEY', base64_encode( sodium_crypto_sign_publickey( $kp ) ) );
$sk = sodium_crypto_sign_secretkey( $kp );
$GLOBALS['tr'] = array(); $GLOBALS['web'] = array(); $GLOBALS['asked'] = array();
function get_transient( $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['tr'][ $k ] = $v; return true; }
function home_url() { return 'https://club.example.org'; }
function is_wp_error( $e ) { return false; }
function wp_remote_get( $u, $a = array() ) { $GLOBALS['asked'][] = $u; return $GLOBALS['web'][ basename( $u ) ] ?? array( 'code' => 404, 'body' => '' ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_header( $r, $h ) { return 'text/plain; charset=utf-8'; }
function add_action() {} function add_filter() {}
require DS_TOOLKIT_PATH . 'features/class-ds-hq-update.php';
require DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php';

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };

// ---- what the content walk skips
$R = '/sites/club';
$skip = function ( $rel ) use ( $R ) { return DS_Tripwire::content_skip( $R . '/' . $rel, $R ); };
$is( 'our cleanup quarantine at the web root is skipped', $skip( '.quarantine-20261028/wp-content/x.php' ), true );
$is( 'Sucuri\'s quarantine at the web root is skipped', $skip( '.sucuriquarantine/x.php' ), true );
$is( 'a ".quarantine-" folder anywhere else is READ (a hiding place before)', $skip( 'wp-content/uploads/.quarantine-1/x.php' ), false );
$is( '".quarantine" without the date pattern at the root is read', $skip( '.quarantined/x.php' ), false );
$is( 'node_modules inside a plugin is skipped', $skip( 'wp-content/plugins/acme/node_modules/lib/a.php' ), true );
$is( 'node_modules inside a theme is skipped', $skip( 'wp-content/themes/acme/assets/node_modules/a.php' ), true );
$is( 'node_modules in uploads is READ', $skip( 'wp-content/uploads/node_modules/a.php' ), false );
$is( 'our own rollback copy is skipped (its tests ship shell fixtures)', $skip( 'wp-content/upgrade-temp-backup/plugins/ds-toolkit/tests/x.php' ), true );
$is( 'another plugin\'s rollback copy is READ (cleared by hash like any vendor file)', $skip( 'wp-content/upgrade-temp-backup/plugins/forminator/a.php' ), false );
$is( 'a file dropped straight into wp-content/upgrade/ is READ', $skip( 'wp-content/upgrade/shell.php' ), false );
$is( 'our installer\'s staging folder is skipped', $skip( 'wp-content/upgrade/.dstk-new.4182/ds-toolkit/x.php' ), true );
$is( 'WordPress\'s extract folder holding ds-toolkit is skipped', $skip( 'wp-content/upgrade/ds-toolkit-1.11.0-abc/ds-toolkit/x.php' ), true );
$is( 'an ordinary plugin file is read', $skip( 'wp-content/plugins/acme/acme.php' ), false );

// ---- list signatures
$body = "0123456789abcdef0123456789abcdef  example 1.0 a.php - evidence\n";
$sig  = function ( $label, $name, $b ) use ( $sk ) { return base64_encode( sodium_crypto_sign_detached( "$label\n$name\n" . hash( 'sha256', $b ), $sk ) ); };
$is( 'a list signed for its name and bytes verifies', DS_HQ_Update::list_sig_ok( 'tripwire-allow', hash( 'sha256', $body ), $sig( 'ds-toolkit-list', 'tripwire-allow', $body ) ), true );
$is( 'one changed byte does not', DS_HQ_Update::list_sig_ok( 'tripwire-allow', hash( 'sha256', $body . ' ' ), $sig( 'ds-toolkit-list', 'tripwire-allow', $body ) ), false );
$is( 'a signature made for another list does not', DS_HQ_Update::list_sig_ok( 'tripwire-allow', hash( 'sha256', $body ), $sig( 'ds-toolkit-list', 'tripwire-deny', $body ) ), false );
$is( 'a release signature never passes as a list signature', DS_HQ_Update::list_sig_ok( 'tripwire-allow', hash( 'sha256', $body ), $sig( 'ds-toolkit-release', 'tripwire-allow', $body ) ), false );

// ---- the allow list as a site fetches it
$rl = new ReflectionMethod( 'DS_Tripwire', 'remote_list' );
if ( PHP_VERSION_ID < 80100 ) { $rl->setAccessible( true ); }
$GLOBALS['web'] = array( 'tripwire-allow.md5' => array( 'code' => 200, 'body' => $body ) );
$is( 'an unsigned allow list is not used', count( $rl->invoke( null, 'allow' ) ), 0 );
$GLOBALS['tr'] = array();
$GLOBALS['web']['tripwire-allow.md5.sig'] = array( 'code' => 200, 'body' => $sig( 'ds-toolkit-list', 'tripwire-allow', $body ) . "\n" );
$is( 'a signed allow list is used', count( $rl->invoke( null, 'allow' ) ), 1 );
$GLOBALS['tr'] = array( 'ds_tripwire_rl_allow' => array( 'ffffffffffffffffffffffffffffffff' => true ) );
$GLOBALS['web']['tripwire-allow.md5.sig']['body'] = 'AAAA';
$is( 'a copy an older version cached unsigned is never read; a bad signature leaves the bundle only', count( $rl->invoke( null, 'allow' ) ), 0 );
$GLOBALS['tr'] = array();
$GLOBALS['web'] = array( 'tripwire-deny.md5' => array( 'code' => 200, 'body' => "0123456789abcdef0123456789abcdef  some shell\n" ) );
$is( 'the deny list (labels only) needs no signature', count( $rl->invoke( null, 'deny' ) ), 1 );

echo $fails ? "$fails of $n FAILED\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
