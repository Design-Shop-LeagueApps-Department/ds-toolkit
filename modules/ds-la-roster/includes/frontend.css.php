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
// Large: the stylesheet reads --r-cols down to its tablet width, so a Large value is that variable set again
// inside the site's Large breakpoint. A blank tablet or phone value then steps down from it (3 and 2 at most,
// the stylesheet's own fallbacks), so a narrower screen never shows more columns than a wider one.
$cols_lg = ( isset( $settings->columns_large ) && '' !== $settings->columns_large ) ? max( 1, min( 6, (int) $settings->columns_large ) ) : 0;
if ( $cols_lg ) {
	if ( ! isset( $vars['--r-cols_medium'] ) && $cols_lg < 3 ) { $vars['--r-cols_medium'] = (string) $cols_lg; }
	if ( ! isset( $vars['--r-cols_responsive'] ) && (int) ( $vars['--r-cols_medium'] ?? 3 ) < 2 ) { $vars['--r-cols_responsive'] = '1'; }
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
if ( $cols_lg ) {
	echo '@media (max-width:' . (int) DS_Module_UI::breakpoint_map()['_large'] . 'px){.fl-node-' . $id . ' .dsr{--r-cols:' . $cols_lg . ';}}'; // phpcs:ignore WordPress.Security.EscapeOutput -- ints only
}
FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'title_typo', 'selector' => ".fl-node-$id .dsr .dsr-name" ) );
FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'name_typo', 'selector' => ".fl-node-$id .dsr .dsr-pname" ) );
