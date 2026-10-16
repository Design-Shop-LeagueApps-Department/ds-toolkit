<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Calendar: the main colour used as text (links, "13 teams", the outlined button, the unselected toggle)
 * must stay readable on white AND on the light grey sections calendars sit in. Renders the real frontend.css.php.
 *
 *   php tests/calendar-ink-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
class FLBuilderModule { public function __construct( $a = array() ) {} }
class FLBuilder { public static function register_module() {} }
class FLBuilderCSS { public static function typography_field_rule() {} }
class DS_Calendar_Data { public static function site_options() { return array(); } public static function known() { return array(); } }
class DS_Module_UI { public static function color( $v ) { $v = trim( (string) $v ); return '' === $v ? '' : ( preg_match( '/^[0-9a-f]{3,8}$/i', $v ) ? '#' . $v : $v ); } public static function u( $v, $d ) { return $d; } }
function __( $s, $d = '' ) { return $s; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/\s+/', '-', $s ) ); }
$GLOBALS['primary'] = '#069e33';
function get_option( $k ) { return '_fl_builder_styles' === $k ? (object) array( 'colors' => array( array( 'label' => 'Primary', 'color' => $GLOBALS['primary'] ), array( 'label' => 'Secondary', 'color' => '#f78d1e' ) ) ) : array(); }
require __DIR__ . '/../modules/ds-la-calendar/ds-la-calendar.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-70s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
function css_for( $settings ) { $id = 'x'; ob_start(); include __DIR__ . '/../modules/ds-la-calendar/includes/frontend.css.php'; return ob_get_clean(); }
function var_of( $css, $name ) { return preg_match( '/' . preg_quote( $name, '/' ) . ':(#[0-9a-f]{6})/i', $css, $m ) ? $m[1] : ''; }
function rgb( $h ) { return array( hexdec( substr( $h, 1, 2 ) ), hexdec( substr( $h, 3, 2 ) ), hexdec( substr( $h, 5, 2 ) ) ); }

foreach ( array( 'LP7 green (Global Styles)' => array( '#069e33', '' ), 'a light lime main colour' => array( '#069e33', '9be15d' ), 'orange main colour' => array( '#069e33', 'f78d1e' ), 'navy main colour' => array( '#069e33', '203f6c' ) ) as $label => $c ) {
	$GLOBALS['primary'] = $c[0];
	$css = css_for( (object) array( 'c_brand' => $c[1] ) );
	$ink = var_of( $css, '--c-brand-ink' );
	chk( "$label: text colour readable on #f4f4f4 (4.5:1)", '' !== $ink && DS_Calendar_Module::ratio( rgb( $ink ), array( 244, 244, 244 ) ) >= 4.5, true );
	chk( "$label: text colour readable on white (4.5:1)", '' !== $ink && DS_Calendar_Module::ratio( rgb( $ink ), array( 255, 255, 255 ) ) >= 4.5, true );
	$on  = var_of( $css, '--c-on-brand' );
	$bg  = '' !== $c[1] ? '#' . $c[1] : $c[0];
	chk( "$label: text ON the main colour picks the more readable of white/dark", '' !== $on && DS_Calendar_Module::ratio( rgb( $on ), rgb( $bg ) ) >= min( 4.5, max( DS_Calendar_Module::ratio( array( 255, 255, 255 ), rgb( $bg ) ), DS_Calendar_Module::ratio( array( 14, 27, 47 ), rgb( $bg ) ) ) ), true );
}
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
