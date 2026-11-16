<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * WP Engine login fix (features/class-ds-wpe-login-error-fix.php): runs the wp_authenticate_user chain the
 * way thestjamescamps.com had it (Defender's CAPTCHA check at 8, WP Engine's wpe_refresh_user at 10) and
 * checks that a refused login reaches core as the WP_Error, not as `false` (the critical error), while a
 * login that passes still gets WP Engine's refresh. No WordPress:
 *
 *   php tests/wpe-login-error-fix-test.php
 */
define( 'ABSPATH', sys_get_temp_dir() . '/' );

class WP_Error { public $code; public function __construct( $c ) { $this->code = $c; } }
class WP_User { public $user_login; public function __construct( $l ) { $this->user_login = $l; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }

// A small stand-in for WP_Hook: priorities in order, and a callback removed mid-run is skipped,
// as WordPress does when a later priority is removed while an earlier one runs.
$GLOBALS['hooks'] = array();
function add_filter( $h, $cb, $p = 10 ) { $GLOBALS['hooks'][ $h ][ $p ][] = $cb; return true; }
function remove_filter( $h, $cb, $p = 10 ) {
	foreach ( (array) ( $GLOBALS['hooks'][ $h ][ $p ] ?? array() ) as $i => $c ) {
		if ( $c === $cb ) { unset( $GLOBALS['hooks'][ $h ][ $p ][ $i ] ); return true; }
	}
	return false;
}
function apply_filters( $h, $v ) {
	$done = array();
	while ( true ) {
		$ps = array_keys( $GLOBALS['hooks'][ $h ] ?? array() );
		sort( $ps );
		$next = null;
		foreach ( $ps as $p ) { if ( ! in_array( $p, $done, true ) ) { $next = $p; break; } }
		if ( null === $next ) { return $v; }
		$done[] = $next;
		foreach ( (array) $GLOBALS['hooks'][ $h ][ $next ] as $cb ) {
			if ( in_array( $cb, (array) ( $GLOBALS['hooks'][ $h ][ $next ] ?? array() ), true ) ) { $v = $cb( $v ); }
		}
	}
}

// WP Engine's function, as wpengine-common ships it (no WP_Error check).
$GLOBALS['refreshed'] = 0;
function wpe_refresh_user( $user ) {
	$GLOBALS['refreshed']++;
	$login = @$user->user_login; // undefined property on a WP_Error: null
	return $login ? new WP_User( $login ) : false;
}

require __DIR__ . '/../features/class-ds-wpe-login-error-fix.php';

$fails = 0;
function chk( $label, $ok ) { global $fails; if ( ! $ok ) { $fails++; } printf( "  %-66s %s\n", $label, $ok ? 'ok' : 'FAIL' ); }

function setup( $with_fix, $captcha_passes ) {
	$GLOBALS['hooks'] = array(); $GLOBALS['refreshed'] = 0;
	add_filter( 'wp_authenticate_user', function ( $u ) use ( $captcha_passes ) { return $captcha_passes ? $u : new WP_Error( 'turnstile_failed' ); }, 8 );
	add_filter( 'wp_authenticate_user', 'wpe_refresh_user' );
	if ( $with_fix ) { ( new DS_WPE_Login_Error_Fix() )->init(); }
}

echo "without the fix (proves the test sees the bug)\n";
setup( false, false );
chk( 'a failed CAPTCHA reaches core as false (the critical error)', false === apply_filters( 'wp_authenticate_user', new WP_User( 'ryan' ) ) );

echo "with the fix\n";
setup( true, false );
$r = apply_filters( 'wp_authenticate_user', new WP_User( 'ryan' ) );
chk( 'a failed CAPTCHA reaches core as the WP_Error', is_wp_error( $r ) && 'turnstile_failed' === $r->code );
chk( 'WP Engine refresh did not run on the refused login', 0 === $GLOBALS['refreshed'] );

setup( true, true );
$r = apply_filters( 'wp_authenticate_user', new WP_User( 'ryan' ) );
chk( 'a passed CAPTCHA still returns the user', $r instanceof WP_User && 'ryan' === $r->user_login );
chk( 'WP Engine refresh still runs on a login that can succeed', 1 === $GLOBALS['refreshed'] );

$GLOBALS['hooks'] = array();
( new DS_WPE_Login_Error_Fix() )->init();
chk( 'off WP Engine (no wpe_refresh_user hooked) an error passes untouched', is_wp_error( apply_filters( 'wp_authenticate_user', new WP_Error( 'x' ) ) ) );

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
