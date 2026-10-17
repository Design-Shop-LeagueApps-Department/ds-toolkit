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
	$tint = var_of( $css, '--c-brand-tint' );
	chk( "$label: event times / badges readable on the event tint (4.5:1)", '' !== $tint && DS_Calendar_Module::ratio( rgb( $ink ), rgb( $tint ) ) >= 4.5, true );
	chk( "$label: event names (#0e1b2f) readable on the event tint", '' !== $tint && DS_Calendar_Module::ratio( array( 14, 27, 47 ), rgb( $tint ) ) >= 4.5, true );
	chk( "$label: the phone's picked day (ink on tint) readable", '' !== $tint && DS_Calendar_Module::ratio( rgb( $ink ), rgb( $tint ) ) >= 4.5, true );
	chk( "$label: text ON the main colour picks the more readable of white/dark", '' !== $on && DS_Calendar_Module::ratio( rgb( $on ), rgb( $bg ) ) >= min( 4.5, max( DS_Calendar_Module::ratio( array( 255, 255, 255 ), rgb( $bg ) ), DS_Calendar_Module::ratio( array( 14, 27, 47 ), rgb( $bg ) ) ) ), true );
}
// Colour by event type: each type's label colour on its tint AND on light grey, for any colour a partner picks.
foreach ( array( '#f78d1e', '#ffd400', '#9be15d', '#203f6c', '#007694', '#c8102e', '#ffffff', '#000000' ) as $c ) {
	$p = DS_Calendar_Module::tint_pair( $c );
	chk( "type colour $c: label on its tint", DS_Calendar_Module::ratio( rgb( $p[1] ), rgb( $p[0] ) ) >= 4.5, true );
	chk( "type colour $c: label on light grey (bar style)", DS_Calendar_Module::ratio( rgb( $p[1] ), array( 244, 244, 244 ) ) >= 4.5, true );
}
// The rounding steps follow the Corner rounding setting.
$css = css_for( (object) array( 'radius' => '20' ) );
chk( 'radius 20 -> boxes 15px, events 12px', ( false !== strpos( $css, '--rs:15px' ) ) && ( false !== strpos( $css, '--rx:12px' ) ), true );
$css = css_for( (object) array( 'radius' => '0' ) );
chk( 'radius 0 -> square everywhere', ( false !== strpos( $css, '--rs:0px' ) ) && ( false !== strpos( $css, '--rx:0px' ) ), true );
// Colour mode: new calendars one colour; older ones that already picked type colours keep them.
chk( 'colour mode default = one colour', DS_Calendar_Module::color_mode( (object) array() ), 'one' );
chk( 'colour mode: an older calendar with a Tournaments colour keeps type colours', DS_Calendar_Module::color_mode( (object) array( 'c_tour' => 'ff0000' ) ), 'type' );
chk( 'colour mode: an explicit choice wins', DS_Calendar_Module::color_mode( (object) array( 'c_tour' => 'ff0000', 'color_mode' => 'one' ) ), 'one' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
