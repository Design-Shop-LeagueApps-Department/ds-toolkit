<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * ds-menu: the mobile drill drawer's ROOT rows must carry their own colour.
 *
 *   php tests/ds-menu-drill-root-colour-test.php
 *
 * The bug this guards (absolutevb.com 2026-10-08, dsstormbball 2026-09-22): the template
 * emitted a colour for `.ds-drill-panel:not(.is-root)` and for `.is-root ...:hover`, but
 * never a resting colour for `.is-root`. The label is a <span> inside a <button>, so with no
 * rule it inherits Beaver Builder's global button TEXT colour. On a site whose global button
 * text is dark (set for AA on a light brand button) the whole mobile menu renders dark on a
 * dark drawer: measured 1.14:1 and 1.12:1 — an invisible main navigation on phones.
 */

define( 'ABSPATH', '/tmp/fake-wp/' );

class DS_Module_UI {
	/** The real one normalises a colour field; a hex gains its '#', a var() passes through. */
	public static function color( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) { return ''; }
		if ( preg_match( '/^[0-9a-fA-F]{3,8}$/', $v ) ) { return '#' . $v; }
		return $v;
	}
	public static function breakpoints() { return array( 992, 768 ); }
	public static function global_button_css( $a = null, $b = null, $c = true ) { return ''; }
}
class FLBuilderModel {
	public static function get_global_settings() { return (object) array( 'large_breakpoint' => 1200 ); }
}

$fails = 0; $checks = 0;
function ok( $cond, $msg ) {
	global $fails, $checks; $checks++;
	if ( $cond ) { echo "  ok   $msg\n"; return; }
	$fails++; echo "  FAIL $msg\n";
}

/** Render the module CSS for one settings object. */
function render( array $over = array() ) {
	$id       = 'testnode1';
	$defaults = array( 'mobile_menu_style' => 'drill' );
	$settings = (object) array_merge( $defaults, $over );
	$module   = new stdClass();
	ob_start();
	include __DIR__ . '/../modules/ds-menu/includes/frontend.css.php';
	return (string) ob_get_clean();
}

/** Collapse whitespace so selector lists can be matched across the template's newlines. */
function flat( $css ) { return preg_replace( '/\s+/', ' ', $css ); }

echo "Root drill rows get a resting colour\n";
$css = flat( render() );
ok( false !== strpos( $css, '.ds-drill-panel.is-root .ds-drill-row:not(.ds-drill-cta) .ds-drill-label { color:' ),
	'default settings: the root LABEL is given a colour' );
ok( false !== strpos( $css, '.ds-drill-panel.is-root .ds-drill-row:not(.ds-drill-cta), ' ),
	'default settings: the root ROW is in the same rule' );
ok( false !== strpos( $css, 'var(--fl-global-white)' ),
	'default settings: that colour is the overlay_text default (white)' );

echo "\nIt follows the Mobile Overlay Text setting, not a hardcoded value\n";
$css = flat( render( array( 'overlay_text' => '#ff0088' ) ) );
ok( preg_match( '/\.ds-drill-panel\.is-root \.ds-drill-row:not\(\.ds-drill-cta\) \.ds-drill-label \{ color: #ff0088/', $css ) === 1,
	'overlay_text #ff0088 reaches the root label' );

echo "\nThe deeper panels still follow Mobile Overlay Subtext, label included\n";
$css = flat( render( array( 'overlay_text' => '#ffffff', 'overlay_subtext' => '#cccccc' ) ) );
ok( preg_match( '/\.ds-drill-panel:not\(\.is-root\) \.ds-drill-row:not\(\.ds-drill-cta\) \.ds-drill-label \{ color: #cccccc/', $css ) === 1,
	'overlay_subtext reaches the sub-panel label' );

echo "\nThe CTA pill is never repainted by these rules\n";
foreach ( array( array(), array( 'overlay_text' => '#ffffff' ) ) as $i => $o ) {
	$css = flat( render( $o ) );
	ok( false === strpos( $css, '.is-root .ds-drill-row .ds-drill-label {' ),
		"case $i: no rule targets the row without excluding .ds-drill-cta" );
}

echo "\nThe hover rule that already existed is untouched\n";
$css = flat( render( array( 'overlay_text_hover' => '#00ff00' ) ) );
ok( false !== strpos( $css, '.ds-drill-panel.is-root .ds-drill-row:not(.ds-drill-cta):hover { color: #00ff00' ),
	'overlay_text_hover still colours the root hover state' );

echo "\nThe overlay style is unaffected (it has its own rules)\n";
$css = flat( render( array( 'mobile_menu_style' => 'overlay' ) ) );
ok( false === strpos( $css, 'ds-drill-panel.is-root' ),
	'mobile_menu_style=overlay emits no drill rules at all' );

echo "\n$checks checks, $fails failed\n";
exit( $fails ? 1 : 0 );
