<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * Bot Shield: when the crawler DNS check runs once a memory backend is there (audit 2026-11, item 6).
 * A real crawler passes on its first request and is never refused; a flood of spoofed crawler requests
 * on rotating addresses pays for at most SE_VERIFY_BURST checks per 10 seconds. Stub-style:
 *
 *   php tests/bot-shield-crawler-check-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['F'] = array(); $GLOBALS['C'] = array(); $GLOBALS['dns'] = 0; $GLOBALS['opts'] = array();
function wp_doing_cron() { return false; }
function is_admin() { return false; }
function add_action() {}
function add_filter( $h, $cb ) { $GLOBALS['F'][ $h ][] = $cb; }
function apply_filters( $h, $v, ...$a ) { foreach ( $GLOBALS['F'][ $h ] ?? array() as $cb ) { $v = $cb( $v, ...$a ); } return $v; }
function wp_using_ext_object_cache() { return false; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
require DS_TOOLKIT_PATH . 'features/class-ds-bot-shield.php';

// The class's own test backend: an array standing in for memcached / APCu.
add_filter( 'ds_bot_shield_backend', function () { return 'array'; } );
add_filter( 'ds_bot_shield_test_incr', function ( $n, $k ) { $GLOBALS['C'][ $k ] = ( $GLOBALS['C'][ $k ] ?? 0 ) + 1; return $GLOBALS['C'][ $k ]; } );
add_filter( 'ds_bot_shield_test_get', function ( $v, $k ) { return array_key_exists( $k, $GLOBALS['C'] ) ? $GLOBALS['C'][ $k ] : false; } );
add_filter( 'ds_bot_shield_test_set', function ( $v, $k, $val ) { $GLOBALS['C'][ $k ] = $val; return null; } );
// DNS: 66.249.66.x is Google's (PTR into googlebot.com, forward-confirmed); everything else is somebody's VPS.
add_filter( 'ds_bot_shield_dns', function ( $pre, $kind, $value ) {
	$GLOBALS['dns']++;
	if ( 'ptr' === $kind ) { return 0 === strpos( $value, '66.249.66.' ) ? 'crawl-' . str_replace( '.', '-', $value ) . '.googlebot.com' : 'vps-' . md5( $value ) . '.example.net'; }
	return preg_match( '/^crawl-(\d+)-(\d+)-(\d+)-(\d+)\.googlebot\.com$/', $value, $m ) ? array( "$m[1].$m[2].$m[3].$m[4]" ) : array();
} );

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };
$GB  = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
$req = function ( $ip, $ua, $settings = array(), $uri = '/news/' ) { return ( new DS_Bot_Shield( $settings ) )->evaluate( 'GET', $uri, $ua, $ip, array() ); }; // one object = one request
$reset = function () { $GLOBALS['C'] = array(); $GLOBALS['dns'] = 0; };
$burst = DS_Bot_Shield::SE_VERIFY_BURST;

echo "a real crawler\n";
$reset();
$is( 'first request from a new Google address: verified at once, passes', array( $req( '66.249.66.1', $GB ), $GLOBALS['dns'] ), array( 'pass', 2 ) );
$is( '... and stays out of the per-address and site-wide counters', count( array_filter( array_keys( $GLOBALS['C'] ), function ( $k ) { return 0 === strpos( $k, 'ds_bs_ip_' ) || 0 === strpos( $k, 'ds_bs_glob_' ); } ) ), 0 );
$req( '66.249.66.1', $GB ); $req( '66.249.66.1', $GB );
$is( 'later requests from it: no further lookup (remembered for a day)', $GLOBALS['dns'], 2 );

echo "a spoofed-crawler flood on rotating addresses\n";
$reset();
$verdicts = array();
for ( $i = 1; $i <= 200; $i++ ) { $verdicts[] = $req( '203.0.' . intdiv( $i, 250 ) . '.' . ( $i % 250 + 1 ), $GB ); }
$is( '200 spoofed requests from 200 addresses cost ' . ( 2 * $burst ) . ' DNS lookups at most, not 400', $GLOBALS['dns'] <= 2 * $burst, true );
$is( '... none of them is waved through as a crawler: all counted like visitors', array( array_unique( $verdicts ), ( $GLOBALS['C'][ 'ds_bs_glob_' . (int) floor( time() / 10 ) ] ?? 0 ) >= 190 ), array( array( 'pass' ), true ) );
$before = $GLOBALS['dns'];
$req( '203.0.0.2', $GB );
$is( 'a spoofer already checked today is answered from the cache', $GLOBALS['dns'], $before );

echo "never refuse a real crawler, even when it was not checked on arrival\n";
$reset();
for ( $i = 1; $i <= 50; $i++ ) { $req( '198.51.100.' . $i, $GB ); } // the burst is used up by spoofers
$dns0 = $GLOBALS['dns'];
$is( 'during the flood a new Google address is not looked up on arrival, and passes like any visitor', array( $req( '66.249.66.7', $GB ), $GLOBALS['dns'] ), array( 'pass', $dns0 ) );
$low = array( 'bot_shield_ip_limit' => 30 );
$v = array(); for ( $i = 0; $i < 40; $i++ ) { $v[] = $req( '66.249.66.7', $GB, $low ); }
$is( 'over the per-address limit it is verified then, passes, and gets no penalty', array( array_unique( $v ), isset( $GLOBALS['C'][ 'ds_bs_pen_' . md5( '66.249.66.7' ) ] ), $GLOBALS['dns'] ), array( array( 'pass' ), false, $dns0 + 2 ) );
$v = array(); for ( $i = 0; $i < 40; $i++ ) { $v[] = $req( '198.51.100.200', $GB, $low ); }
$is( 'a spoofer over the limit is verified once, fails, and is rate limited', array( end( $v ), ! empty( $GLOBALS['C'][ 'ds_bs_pen_' . md5( '198.51.100.200' ) ] ) ), array( 'rate-limit', true ) );
$is( '... and stays limited without another lookup', array( $req( '198.51.100.200', $GB, $low ), $GLOBALS['dns'] ), array( 'rate-limit', $dns0 + 3 ) ); // Google 2 lookups (PTR + forward), the spoofer 1 (its PTR is not Google's)

echo "blocked user agents\n";
$reset();
$bl = array( 'bot_shield_ua_blocklist' => "badbot\ngooglebot" ); // a site that blocklisted the word by mistake
$is( 'a real Google address still passes a UA blocklist hit', $req( '66.249.66.9', $GB, $bl ), 'pass' );
$is( 'a spoofer with the same UA is blocked', $req( '203.0.113.50', $GB, $bl ), 'ua-block' );
$dns1 = $GLOBALS['dns'];
$is( 'a plain bad bot is blocked with no DNS lookup at all', array( $req( '203.0.113.51', 'BadBot/1.0', $bl ), $GLOBALS['dns'] ), array( 'ua-block', $dns1 ) );
$is( 'an ordinary visitor never triggers a lookup', array( $req( '203.0.113.52', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130' ), $GLOBALS['dns'] ), array( 'pass', $dns1 ) );

echo $fails ? "$fails of $n FAILED\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
