<?php
/**
 * Settings -> CSS custom properties on this node. Every colour passes DS_Module_UI::color()
 * (hex, rgb/hsl, var(--x), a keyword), so a value can never close the rule. The brand tint and
 * the readable "ink" are plain hex from DS_LA_Colors, never CSS color-mix().
 *
 * @var object $settings
 * @var string $id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$c    = function ( $k ) use ( $settings ) { return DS_Module_UI::color( $settings->$k ?? '' ); };
$vars = array(
	'--s-brand' => $c( 'c_brand' ),
	'--s-text'  => $c( 'c_text' ),
	'--s-muted' => $c( 'c_muted' ),
	'--s-card'  => $c( 'c_card' ),
	'--s-line'  => $c( 'c_line' ),
);
$pal = DS_LA_Colors::palette( $vars['--s-brand'], $vars['--s-card'] );
if ( $pal ) {
	$vars['--s-tint']     = $pal['tint'];
	$vars['--s-ink']      = $pal['ink'];
	$vars['--s-on-brand'] = $pal['on_brand'];
}
if ( isset( $settings->radius ) && '' !== $settings->radius ) {
	$vars['--s-radius'] = max( 0, min( 40, (int) $settings->radius ) ) . 'px';
}
$decl = '';
foreach ( $vars as $k => $v ) {
	if ( '' !== $v ) {
		$decl .= $k . ':' . $v . ';';
	}
}
if ( '' !== $decl ) {
	echo '.fl-node-' . $id . ' .dss{' . $decl . '}'; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized colours and ints only
}
FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'title_typo', 'selector' => ".fl-node-$id .dss .dss-title" ) );
