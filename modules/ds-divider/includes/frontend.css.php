<?php
/**
 * LeagueApps Divider — dynamic CSS from settings. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";
$col  = array( 'DS_Module_UI', 'color' );
$u    = array( 'DS_Module_UI', 'u' );
$line = "$node .ds-divider-line";

$o   = ( ( $settings->orientation ?? 'horizontal' ) === 'vertical' ) ? 'v' : 'h';
$e   = $settings->effect ?? 'gradient';

$c1  = $col( $settings->color1 ?? 'var(--fl-global-primary)' );
if ( '' === $c1 ) { $c1 = 'var(--fl-global-primary)'; }
$c2     = $col( $settings->color2 ?? '' );
$bright = ( '' !== $c2 ) ? $c2 : $c1;
$track  = "color-mix(in srgb, {$c1} 16%, transparent)";

$th  = $u( $settings->thickness ?? '', 3 );
$sp  = ( isset( $settings->speed ) && '' !== $settings->speed ) ? (float) $settings->speed : 2;
if ( $sp <= 0 ) { $sp = 2; }
$revcss = ( ( $settings->reverse ?? 'no' ) === 'yes' ) ? ' animation-direction: reverse;' : '';

/* ---- Spacing (margin) ---- */
if ( isset( $settings->spacing ) && '' !== $settings->spacing ) {
	$m = (int) $settings->spacing;
	if ( 'h' === $o ) { echo "$node .ds-divider { margin-top: {$m}px; margin-bottom: {$m}px; }\n"; }
	else             { echo "$node .ds-divider { margin-left: {$m}px; margin-right: {$m}px; }\n"; }
}

/* ---- Size ---- */
if ( 'h' === $o ) {
	$hw  = is_numeric( $settings->h_width ?? '' ) ? (float) $settings->h_width : 100;
	$hwu = ( ( $settings->h_width_unit ?? '%' ) === 'px' ) ? 'px' : '%';
	echo "$line { width: {$hw}{$hwu}; height: {$th}px; }\n";
} else {
	$vh = $u( $settings->v_height ?? '', 80 );
	echo "$line { width: {$th}px; height: {$vh}px; }\n";
}
if ( ( $settings->rounded ?? 'yes' ) === 'yes' ) { echo "$line { border-radius: 999px; }\n"; }

/* ---- Effect ---- */
if ( 'solid' === $e ) {

	echo "$line { background: {$c1}; }\n";

} elseif ( 'gradient' === $e ) {

	$dir  = ( 'h' === $o ) ? '90deg' : '180deg';
	$mid2 = ( '' !== $c2 ) ? $c2 : $c1;
	echo "$line { background: linear-gradient({$dir}, transparent 0%, {$c1} 35%, {$mid2} 65%, transparent 100%); }\n";

} elseif ( 'running' === $e ) {

	echo "$line { background: {$track}; }\n";
	if ( 'h' === $o ) {
		echo "$line::after { content:''; position:absolute; top:0; bottom:0; left:0; width:45%; background: linear-gradient(90deg, transparent, {$bright}, transparent); animation: dsDivRunH {$sp}s linear infinite;{$revcss} }\n";
	} else {
		echo "$line::after { content:''; position:absolute; left:0; right:0; top:0; height:45%; background: linear-gradient(180deg, transparent, {$bright}, transparent); animation: dsDivRunV {$sp}s linear infinite;{$revcss} }\n";
	}

} elseif ( 'glow' === $e ) {

	$g = $u( $settings->glow_size ?? '', 12 );
	echo "$line { background: {$c1}; box-shadow: 0 0 {$g}px {$c1}; animation: dsDivGlow {$sp}s ease-in-out infinite; }\n";

} elseif ( 'dashed' === $e ) {

	$dl   = $u( $settings->dash_len ?? '', 12 );
	$dg   = $u( $settings->dash_gap ?? '', 10 );
	$cell = $dl + $dg;
	if ( 'h' === $o ) {
		echo "$line { --ds-dash: {$cell}px; background: repeating-linear-gradient(90deg, {$c1} 0 {$dl}px, transparent {$dl}px {$cell}px); background-size: {$cell}px 100%; animation: dsDivDashH {$sp}s linear infinite;{$revcss} }\n";
	} else {
		echo "$line { --ds-dash: {$cell}px; background: repeating-linear-gradient(180deg, {$c1} 0 {$dl}px, transparent {$dl}px {$cell}px); background-size: 100% {$cell}px; animation: dsDivDashV {$sp}s linear infinite;{$revcss} }\n";
	}
}
