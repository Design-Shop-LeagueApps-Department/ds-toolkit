<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * DS_Module_UI: shared field builders and one responsive CSS emitter. Stub-style:
 *
 *   php tests/module-ui-builders-test.php
 *
 * Why (2026-11 audit, items 28 and 29): the colour-field literal was typed 275 times with five private
 * factories of it, and of 82 responsive fields only 2 used the Large value an editor can type, while the
 * modules carried their own breakpoint widths (1024, 768, 767, 600, 480, 1199).
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
function __( $s, $d = '' ) { return $s; }
class FLBuilderModel {
	public static $g = null;
	public static function get_global_settings() { return self::$g; }
}
require dirname( __DIR__ ) . '/includes/class-ds-module-ui.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-80s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
$root = dirname( __DIR__ ) . '/';

echo "field builders\n";
chk( 'colour: blank default, reset, global colours (the literal Programs and Table used)', DS_Module_UI::color_field( 'Text' ),
	array( 'type' => 'color', 'label' => 'Text', 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ) ) );
chk( 'colour: extras are merged and win', DS_Module_UI::color_field( 'Line', array( 'show_alpha' => true, 'default' => 'ff0000' ) )['default'] . '|' . var_export( DS_Module_UI::color_field( 'Line', array( 'show_alpha' => true ) )['show_alpha'], true ), 'ff0000|true' );
chk( 'unit: px with a slider (the literal Programs and Table used)', DS_Module_UI::unit_field( 'Gap', 0, 60 ),
	array( 'type' => 'unit', 'label' => 'Gap', 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ) );
$yn = DS_Module_UI::yes_no_field( 'Show count' );
chk( 'yes/no: a select storing no / yes, No first, off by default', array( $yn['type'], array_keys( $yn['options'] ), $yn['default'] ), array( 'select', array( 'no', 'yes' ), 'no' ) );
chk( 'yes/no: can start on; anything else reads as off', array( DS_Module_UI::yes_no_field( 'x', 'yes' )['default'], DS_Module_UI::yes_no_field( 'x', 'maybe' )['default'] ), array( 'yes', 'no' ) );
$sh = DS_Module_UI::show_hide_field( 'Arrows' );
chk( 'show/hide: a select storing show / hide', array( $sh['type'], array_keys( $sh['options'] ), $sh['default'] ), array( 'select', array( 'show', 'hide' ), 'show' ) );
$w = DS_Module_UI::width_field( 'Width' );
chk( 'width: px, % and vw, different per screen size', array( $w['units'], $w['responsive'] ), array( array( 'px', '%', 'vw' ), true ) );
foreach ( array( 'ds-programs/ds-programs.php' => 'ds_prg', 'ds-table/ds-table.php' => 'ds_tbl' ) as $file => $pre ) {
	$src = file_get_contents( $root . 'modules/' . $file );
	chk( $file . ' uses the shared colour and unit builders', array( (bool) preg_match( '/\$' . $pre . "_colour\s*=\s*array\( 'DS_Module_UI', 'color_field' \);/", $src ), (bool) preg_match( '/\$' . $pre . "_unit\s*=\s*array\( 'DS_Module_UI', 'unit_field' \);/", $src ), (bool) preg_match( '/\$' . $pre . '_colour\s*=\s*function/', $src ) ), array( true, true, false ) );
}

echo "this site's breakpoints\n";
chk( 'Beaver Builder defaults when nothing is set', DS_Module_UI::breakpoint_map(), array( '_large' => 1200, '_medium' => 992, '_responsive' => 768 ) );
FLBuilderModel::$g = (object) array( 'large_breakpoint' => '1366', 'medium_breakpoint' => '1024', 'responsive_breakpoint' => '600' );
chk( 'the site\'s own values, Large included, widest first', DS_Module_UI::breakpoint_map(), array( '_large' => 1366, '_medium' => 1024, '_responsive' => 600 ) );
chk( 'the two-value helper older code uses still answers the same', DS_Module_UI::breakpoints(), array( 1024, 600 ) );

echo "one responsive emitter\n";
$s = (object) array( 'gap' => '24', 'gap_large' => '20', 'gap_medium' => '', 'gap_responsive' => '12' );
chk( 'base rule, then only the breakpoints the editor filled in (blank Medium cascades)', DS_Module_UI::responsive_css( '.n .g', 'gap', $s, 'gap' ),
	".n .g{gap:24px;}\n@media(max-width:1366px){.n .g{gap:20px;}}\n@media(max-width:600px){.n .g{gap:12px;}}\n" );
chk( 'nothing set: no CSS at all', DS_Module_UI::responsive_css( '.n .g', 'gap', (object) array(), 'gap' ), '' );
chk( 'a default fills the base only', DS_Module_UI::responsive_css( '.n .g', '--cols', (object) array( 'cols_responsive' => '1' ), 'cols', 'int', '3' ), ".n .g{--cols:3;}\n@media(max-width:600px){.n .g{--cols:1;}}\n" );
chk( 'several properties take the same value', DS_Module_UI::responsive_css( '.n', array( 'padding-left', 'padding-right' ), (object) array( 'pad' => '10' ), 'pad' ), ".n{padding-left:10px;padding-right:10px;}\n" );
chk( 'zero is a value, not "blank"', DS_Module_UI::responsive_css( '.n', 'gap', (object) array( 'gap' => '0', 'gap_responsive' => 0 ), 'gap' ), ".n{gap:0px;}\n@media(max-width:600px){.n{gap:0px;}}\n" );
chk( 'decimals are kept', DS_Module_UI::responsive_css( '.n', 'gap', (object) array( 'gap' => '1.5' ), 'gap' ), ".n{gap:1.5px;}\n" );
chk( 'the unit the editor picked, per breakpoint', DS_Module_UI::responsive_css( '.n', 'max-width', (object) array( 'w' => '900', 'w_unit' => 'px', 'w_responsive' => '90', 'w_responsive_unit' => '%' ), 'w', 'unit' ), ".n{max-width:900px;}\n@media(max-width:600px){.n{max-width:90%;}}\n" );
chk( 'an unknown unit falls back to px', DS_Module_UI::responsive_css( '.n', 'max-width', (object) array( 'w' => '5', 'w_unit' => 'px;}body{display:none' ), 'w', 'unit' ), ".n{max-width:5px;}\n" );
chk( 'a word in a number field is skipped', DS_Module_UI::responsive_css( '.n', 'gap', (object) array( 'gap' => 'wide', 'gap_medium' => '8' ), 'gap' ), "@media(max-width:1024px){.n{gap:8px;}}\n" );
chk( 'raw: a clean length or keyword passes', DS_Module_UI::responsive_css( '.n', 'width', (object) array( 'w' => 'calc(100% - 20px)', 'w_responsive' => 'auto' ), 'w', 'raw' ), ".n{width:calc(100% - 20px);}\n@media(max-width:600px){.n{width:auto;}}\n" );
chk( 'raw: a value that tries to close the rule is dropped', DS_Module_UI::responsive_css( '.n', 'width', (object) array( 'w' => '10px;}body{display:none' ), 'w', 'raw' ), '' );
chk( 'a callable decides the value', DS_Module_UI::responsive_css( '.n', 'grid-template-columns', (object) array( 'c' => '3', 'c_responsive' => '1' ), 'c', function ( $v ) { return 'repeat(' . (int) $v . ',1fr)'; } ), ".n{grid-template-columns:repeat(3,1fr);}\n@media(max-width:600px){.n{grid-template-columns:repeat(1,1fr);}}\n" );
chk( 'an array or object where a value should be is ignored', DS_Module_UI::responsive_css( '.n', 'gap', (object) array( 'gap' => array( 1 ) ), 'gap' ), '' );

echo "first adopter: the Programs card grid\n";
$css = file_get_contents( $root . 'modules/ds-programs/includes/frontend.css.php' );
chk( 'it reads Cards per row and Gap under Large, at the site\'s Large breakpoint', array( false !== strpos( $css, 'card_cols_large' ), false !== strpos( $css, 'card_gap_large' ), false !== strpos( $css, "DS_Module_UI::breakpoint_map()['_large']" ) ), array( true, true, true ) );
chk( 'and prints nothing new when Large was left blank', (bool) preg_match( "/if \( '' !== \\\$cols_lg \|\| '' !== \\\$gap_lg \) \{/", $css ), true );

echo "house spelling\n";
$left = array();
foreach ( glob( $root . 'modules/*/*.php' ) as $f ) {
	if ( preg_match_all( "/__\( '((?:[^'\\\\\\\\]|\\\\\\\\.)*)'/", file_get_contents( $f ), $m ) ) {
		foreach ( $m[1] as $t ) {
			if ( preg_match( '/\bColors?\b/', $t ) && ! preg_match( '/Dodge|Burn|duotone|Multiply|global Line Color/', $t ) ) { $left[] = basename( $f ) . ': ' . $t; }
		}
	}
}
chk( 'no label says "Color" (blend-mode names and Beaver Builder\'s Line Color keep theirs)', $left, array() );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
