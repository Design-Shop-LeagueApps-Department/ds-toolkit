<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * Bot Shield: which address is the visitor, and when the crawler DNS check runs (1.12.0). Stub-style:
 *
 *   php tests/bot-shield-client-ip-test.php
 *
 * WP Engine measured 2026-10-28: REMOTE_ADDR is the visitor and CF-Connecting-IP arrives as "ip, ip, ip".
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );
function wp_doing_cron() { return false; }
function is_admin() { return false; }
function apply_filters( $h, $v ) { return $v; }
function wp_using_ext_object_cache() { return false; }
function get_option( $k, $d = false ) { return $d; }
function add_action() {} function add_filter() {}
require DS_TOOLKIT_PATH . 'features/class-ds-bot-shield.php';

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };
$bs  = ( new ReflectionClass( 'DS_Bot_Shield' ) )->newInstanceWithoutConstructor();
$ip  = new ReflectionMethod( $bs, 'client_ip' );
if ( PHP_VERSION_ID < 80100 ) { $ip->setAccessible( true ); }
$at = function ( $remote, $cf = null ) use ( $bs, $ip ) {
	$_SERVER['REMOTE_ADDR'] = $remote;
	if ( null === $cf ) { unset( $_SERVER['HTTP_CF_CONNECTING_IP'] ); } else { $_SERVER['HTTP_CF_CONNECTING_IP'] = $cf; }
	return $ip->invoke( $bs );
};
$is( 'WP Engine as measured: REMOTE_ADDR is the visitor, the "ip, ip, ip" header changes nothing', $at( '138.84.198.105', '138.84.198.105, 138.84.198.105, 138.84.198.105' ), '138.84.198.105' );
$is( 'no header: REMOTE_ADDR', $at( '203.0.113.9' ), '203.0.113.9' );
$is( 'a visitor sending the header straight to PHP (no Cloudflare) is ignored', $at( '203.0.113.9', '10.0.0.1' ), '203.0.113.9' );
$is( '... including someone else\'s address', $at( '203.0.113.9', '198.51.100.7' ), '203.0.113.9' );
$is( 'from a Cloudflare edge (172.64.0.0/13) the header is the visitor', $at( '172.68.1.1', '198.51.100.7' ), '198.51.100.7' );
$is( '... first address of a list', $at( '172.68.1.1', '198.51.100.7, 172.68.1.1' ), '198.51.100.7' );
$is( 'from a Cloudflare IPv6 edge', $at( '2606:4700::6810:1', '2001:db8::5' ), '2001:db8::5' );
$is( 'from a private proxy in front of Cloudflare', $at( '10.1.2.3', '198.51.100.7' ), '198.51.100.7' );
$is( 'just outside a Cloudflare range (104.15.255.255) is not Cloudflare', $at( '104.15.255.255', '198.51.100.7' ), '104.15.255.255' );
$is( 'last address of a range (104.23.255.255 in 104.16.0.0/13) is Cloudflare', $at( '104.23.255.255', '198.51.100.7' ), '198.51.100.7' );
$is( 'a junk header from Cloudflare falls back to REMOTE_ADDR', $at( '172.68.1.1', 'not-an-ip' ), '172.68.1.1' );

// Without a memory backend nothing can be refused except a UA block, so no DNS lookup for a crawler UA that passes.
$se = new ReflectionProperty( $bs, 'se_checked' );
if ( PHP_VERSION_ID < 80100 ) { $se->setAccessible( true ); }
$se->setValue( $bs, null );
$v = $bs->evaluate( 'GET', '/news/', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '203.0.113.9', array() );
$is( 'no backend: a crawler UA that would pass anyway costs no DNS lookup', array( $v, $se->getValue( $bs ) ), array( 'pass', null ) );

echo $fails ? "$fails of $n FAILED\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
