<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * CTA (Styles 1 and 2) and Org Stats skip a row with nothing in it (1.14.1). Since 1.14.0 a new module
 * starts with blank rows, which rendered as empty "#" links with empty headings and empty stat boxes.
 * Renders modules in memory with Beaver Builder (nothing is saved):
 *   wp eval-file tests/blank-rows-hidden-test.php
 * Exit code 1 on any failure.
 */
if ( ! class_exists( 'FLBuilder' ) || ! class_exists( 'DS_CTA_Module' ) || ! class_exists( 'DS_OrgStats_Module' ) ) { echo "Beaver Builder or the DS modules are not active.\n"; exit( 1 ); }

$GLOBALS['brh_fail'] = 0; $GLOBALS['brh_n'] = 0;
function brh_is( $label, $got, $want ) {
	$GLOBALS['brh_n']++;
	$ok = $got === $want;
	if ( ! $ok ) { $GLOBALS['brh_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) . ' want=' . var_export( $want, true ) ) . "\n";
}
$render = function ( $type, array $settings ) {
	ob_start();
	FLBuilder::render_module_html( $type, $settings, null, 'brh' . substr( md5( $type . serialize( $settings ) ), 0, 8 ) );
	return ob_get_clean();
};
$count = function ( $html, $class ) { return preg_match_all( '/class="' . preg_quote( $class, '/' ) . '[" ]/', $html ); };
$blank = array( 'eyebrow' => '', 'title' => '' );

// A freshly dropped module (registered defaults only).
$h = $render( 'ds-cta', array() );
brh_is( 'CTA Style 1 defaults: no empty cards', $count( $h, 'ds-cta-card' ), 0 );
brh_is( 'CTA Style 1 defaults: no empty heading', preg_match( '#<h3[^>]*>\s*</h3>#', $h ), 0 );
brh_is( 'CTA Style 2 defaults: no empty tiles', $count( $render( 'ds-cta', array( 'cta_style' => 'style2' ) ), 'ds-cta-tile' ), 0 );
brh_is( 'Org Stats defaults: no empty stat boxes', $count( $render( 'ds-orgstats', array() ), 'ds-orgstats-item' ), 0 );

// Rows with something in them still render, one per row.
$cards = array( array( 'title' => 'Programs' ), $blank, array( 'eyebrow' => 'New' ), array( 'link' => 'https://example.org/' ), array( 'image' => 'https://example.org/a.jpg' ) );
brh_is( 'CTA Style 1: 4 filled rows render, the blank one does not', $count( $render( 'ds-cta', array( 'cards' => $cards ) ), 'ds-cta-card' ), 4 );
brh_is( 'CTA Style 2: same rule', $count( $render( 'ds-cta', array( 'cta_style' => 'style2', 'cards' => $cards ) ), 'ds-cta-tile' ), 4 );
brh_is( 'CTA: Link Text alone is not content', $count( $render( 'ds-cta', array( 'cards' => array( array( 'link_text' => 'Learn More' ) ) ) ), 'ds-cta-card' ), 0 );
brh_is( 'CTA: a "#" link alone is not content', $count( $render( 'ds-cta', array( 'cards' => array( array( 'link' => '#' ) ) ) ), 'ds-cta-card' ), 0 );
brh_is( 'CTA Style 3 (Bento) keeps every cell, blank ones too', $count( $render( 'ds-cta', array( 'cta_style' => 'style3', 'cards' => array( $blank, $blank ) ) ), 'ds-cta-bento-cell' ), 2 );

$stats = array( array( 'number' => '12' ), array( 'number' => '', 'label' => '' ), array( 'label' => 'Teams' ), array( 'suffix' => '+' ), array( 'prefix' => '$' ) );
brh_is( 'Org Stats: 4 filled rows render, the blank one does not', $count( $render( 'ds-orgstats', array( 'stats' => $stats ) ), 'ds-orgstats-item' ), 4 );
brh_is( 'Org Stats: a card image alone still renders', $count( $render( 'ds-orgstats', array( 'stat_layout' => 'cards', 'stats' => array( array( 'image' => 'https://example.org/a.jpg' ) ) ) ), 'ds-orgstats-item' ), 1 );

echo $GLOBALS['brh_n'] . ' checks, ' . $GLOBALS['brh_fail'] . " failed\n";
exit( $GLOBALS['brh_fail'] ? 1 : 0 );
