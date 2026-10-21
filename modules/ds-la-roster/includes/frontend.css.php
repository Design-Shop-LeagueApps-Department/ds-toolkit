<?php
/**
 * Settings -> CSS custom properties on this node. Every colour passes DS_Module_UI::color()
 * (hex, rgb/hsl, var(--x), a keyword), so a value can never close the rule. The brand tint and
 * the readable "ink" are plain hex worked out here, never CSS color-mix().
 *
 * @var object $settings
 * @var string $id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$c    = function ( $k ) use ( $settings ) { return DS_Module_UI::color( $settings->$k ?? '' ); };
$vars = array(
	'--r-brand' => $c( 'c_brand' ),
	'--r-text'  => $c( 'c_text' ),
	'--r-muted' => $c( 'c_muted' ),
	'--r-card'  => $c( 'c_card' ),
	'--r-line'  => $c( 'c_line' ),
);
$brand = DS_Roster_Module::rgb( '' !== $vars['--r-brand'] ? $vars['--r-brand'] : 'var(--fl-global-primary)' );
if ( $brand ) {
	$card = DS_Roster_Module::rgb( '' !== $vars['--r-card'] ? $vars['--r-card'] : '#ffffff' ) ?: array( 255, 255, 255 );
	$tint = DS_Roster_Module::mix( $brand, $card, 0.12 );
	$ink  = DS_Roster_Module::ink( $brand, array( $tint, $card, array( 236, 236, 236 ) ) );
	$vars['--r-tint'] = DS_Roster_Module::hex( $tint );
	$vars['--r-ink']  = DS_Roster_Module::hex( $ink );
	// Text on the solid brand (solid medallion, active filter): white or dark, whichever reads.
	$w = DS_Roster_Module::ratio( array( 255, 255, 255 ), $brand );
	$d = DS_Roster_Module::ratio( array( 14, 27, 47 ), $brand );
	$vars['--r-on-brand'] = ( $w >= 4.5 || $w >= $d ) ? '#ffffff' : '#0e1b2f';
}
foreach ( array( '' => 'columns', '_medium' => 'columns_medium', '_responsive' => 'columns_responsive' ) as $sfx => $k ) {
	if ( isset( $settings->$k ) && '' !== $settings->$k ) {
		$vars[ '--r-cols' . $sfx ] = (string) max( 1, min( 6, (int) $settings->$k ) );
	}
}
if ( isset( $settings->radius ) && '' !== $settings->radius ) {
	$vars['--r-radius'] = max( 0, min( 40, (int) $settings->radius ) ) . 'px';
}
$decl = '';
foreach ( $vars as $k => $v ) {
	if ( '' !== $v ) {
		$decl .= $k . ':' . $v . ';';
	}
}
if ( '' !== $decl ) {
	echo '.fl-node-' . $id . ' .dsr{' . $decl . '}'; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized colours and ints only
}
FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'title_typo', 'selector' => ".fl-node-$id .dsr .dsr-name" ) );
FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'name_typo', 'selector' => ".fl-node-$id .dsr .dsr-pname" ) );
