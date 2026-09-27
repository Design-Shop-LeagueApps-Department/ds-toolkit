<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * LeagueApps Device: per-node CSS. $module, $settings, $id in scope.
 * Width / alignment / tilt are emitted for every breakpoint that has a value, node-scoped so they beat the static base.
 */
$node = ".fl-node-$id";
$col  = array( 'DS_Module_UI', 'color' );
list( $bp_md, $bp_sm ) = DS_Module_UI::breakpoints();

$vars = array();
if ( '' !== ( $c = $col( $settings->frame_color ?? '' ) ) )      { $vars[] = "--dsd-frame:$c"; }
if ( '' !== ( $c = $col( $settings->screen_color ?? '' ) ) )     { $vars[] = "--dsd-screen:$c"; }
if ( '' !== ( $c = $col( $settings->dot_color ?? '' ) ) )        { $vars[] = "--dsd-dot:$c"; }
if ( '' !== ( $c = $col( $settings->dot_active_color ?? '' ) ) ) { $vars[] = "--dsd-dot-on:$c"; }
$focus = array( 'center' => '50% 50%', 'top' => '50% 0%', 'bottom' => '50% 100%', 'left' => '0% 50%', 'right' => '100% 50%' );
$f = $settings->focus ?? 'center';
if ( isset( $focus[ $f ] ) && 'center' !== $f ) { $vars[] = '--dsd-focus:' . $focus[ $f ]; }
if ( isset( $settings->bezel ) && '' !== $settings->bezel ) { $vars[] = '--dsd-bezel:' . max( 0, (int) $settings->bezel ) . 'px'; }
if ( $vars ) { echo "$node .ds-device{" . implode( ';', $vars ) . "}\n"; }

// Width, alignment and tilt, per breakpoint. A blank width keeps the device's own default.
$emit = function ( $suffix ) use ( $settings, $node ) {
	$out = array();
	$w = $settings->{ 'width' . $suffix } ?? '';
	if ( '' !== $w && null !== $w ) { $out[] = "$node .ds-device-stage{max-width:" . max( 120, (int) $w ) . 'px}'; }
	$a = $settings->{ 'align' . $suffix } ?? '';
	if ( in_array( $a, array( 'left', 'center', 'right' ), true ) ) {
		$m = array( 'left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto' )[ $a ];
		$out[] = "$node .ds-device-stage{margin:$m}";
	}
	$t = $settings->{ 'tilt' . $suffix } ?? '';
	if ( '' !== $t && null !== $t && is_numeric( $t ) ) { $out[] = "$node .ds-device{--dsd-tilt:" . max( -30, min( 30, (float) $t ) ) . 'deg}'; }
	return implode( "\n", $out );
};
echo $emit( '' ) . "\n";
if ( $css = $emit( '_medium' ) )     { echo "@media (max-width:{$bp_md}px){\n$css\n}\n"; }
if ( $css = $emit( '_responsive' ) ) { echo "@media (max-width:{$bp_sm}px){\n$css\n}\n"; }
