<?php
/**
 * Settings -> CSS custom properties on this node. The static stylesheet reads only the
 * variables, so an empty field falls back to the site's Global Styles colours.
 *
 * Every colour passes DS_Module_UI::color() (hex, rgb/hsl, var(--x), a keyword; nothing
 * else), so a value can never close the rule. Event tints and their label colours are
 * plain hex with a 4.5:1 guarantee (DS_Calendar_Module::tint_pair()).
 *
 * @var object $settings
 * @var string $id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$c    = function ( $k ) use ( $settings ) { return DS_Calendar_Module::color( $settings->$k ?? '' ); };
$vars = array(
	'--c-brand'    => $c( 'c_brand' ),
	'--c-on-brand' => $c( 'c_brand_text' ),
	'--c-line'     => $c( 'c_line' ),
	'--c-text'     => $c( 'c_text' ),
	'--c-muted'    => $c( 'c_muted' ),
	'--c-tour'     => $c( 'c_tour' ),
	'--c-game'     => $c( 'c_game' ),
	'--c-other'    => $c( 'c_other' ),
	'--c-grid'     => $c( 'c_grid' ),
	'--c-cell'     => $c( 'c_cell' ),
	'--c-head'     => $c( 'c_head' ),
);
// Text on the main colour: the editor's pick, else white or dark, whichever reaches 4.5:1 on it.
if ( '' === $vars['--c-on-brand'] ) {
	$brand = DS_Calendar_Module::rgb( '' !== $vars['--c-brand'] ? $vars['--c-brand'] : 'var(--fl-global-primary)' );
	if ( $brand ) {
		$white = DS_Calendar_Module::ratio( array( 255, 255, 255 ), $brand );
		$dark  = DS_Calendar_Module::ratio( array( 14, 27, 47 ), $brand );
		$vars['--c-on-brand'] = ( $white >= 4.5 || $white >= $dark ) ? '#ffffff' : '#0e1b2f';
	}
}
// The main colour as TEXT on white (outlined button, unselected toggle, arrows, links): darkened until 4.5:1.
$brand_rgb = DS_Calendar_Module::rgb( '' !== $vars['--c-brand'] ? $vars['--c-brand'] : 'var(--fl-global-primary)' );
if ( $brand_rgb ) {
	$ink = $brand_rgb;
	for ( $i = 0; $i < 12 && DS_Calendar_Module::ratio( $ink, array( 255, 255, 255 ) ) < 4.6; $i++ ) {
		$ink = DS_Calendar_Module::mix( $ink, array( 0, 0, 0 ), 0.85 );
	}
	$vars['--c-brand-ink'] = DS_Calendar_Module::hex( $ink );
}
foreach ( array(
	'tour'  => array( $c( 'c_tour' ), 'var(--fl-global-secondary)', '#f78d1e' ),
	'game'  => array( $c( 'c_game' ), 'var(--fl-global-primary)', '#203f6c' ),
	'other' => array( $c( 'c_other' ), '', '#007694' ),
) as $k => $t ) {
	$pair = DS_Calendar_Module::tint_pair( '' !== $t[0] ? $t[0] : $t[1] );
	$pair = $pair ? $pair : DS_Calendar_Module::tint_pair( $t[2] );
	if ( $pair ) {
		$vars[ '--c-' . $k . '-tint' ] = $pair[0];
		$vars[ '--c-' . $k . '-ink' ]  = $pair[1];
	}
}
if ( isset( $settings->cell_height ) && '' !== $settings->cell_height ) {
	$vars['--cell-h'] = max( 40, min( 400, (int) $settings->cell_height ) ) . 'px';
}
if ( isset( $settings->radius ) && '' !== $settings->radius ) {
	$vars['--radius'] = max( 0, min( 40, (int) $settings->radius ) ) . 'px';
}

$decl = '';
foreach ( $vars as $k => $v ) {
	if ( '' !== $v ) {
		$decl .= $k . ':' . $v . ';';
	}
}
if ( '' !== $decl ) {
	echo '.fl-node-' . $id . ' .dslc{' . $decl . '}'; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized colours and ints only
}

FLBuilderCSS::typography_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'title_typo',
	'selector'     => ".fl-node-$id .dslc .dslc-month",
) );
FLBuilderCSS::typography_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'event_typo',
	'selector'     => ".fl-node-$id .dslc .dslc-row h3",
) );
