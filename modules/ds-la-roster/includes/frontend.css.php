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
$pal = DS_LA_Colors::palette( $vars['--r-brand'], $vars['--r-card'] );
if ( $pal ) {
	$vars['--r-tint']     = $pal['tint'];
	$vars['--r-ink']      = $pal['ink'];
	$vars['--r-on-brand'] = $pal['on_brand'];
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
