<?php
/**
 * DS_Global_Heading_Font: the body text font also gets its bold (700) face in the
 * Google Fonts request, only when the family has one, and nothing else changes.
 * Swaps Beaver Builder's cached Global Styles in memory (nothing is saved):
 *   wp eval-file tests/global-font-bold-test.php
 * Exit code 1 on any failure.
 */
if ( ! class_exists( 'FLBuilderFonts' ) || ! class_exists( 'FLBuilderGlobalStyles' ) ) { echo "Beaver Builder is not active.\n"; exit( 1 ); }
if ( ! class_exists( 'DS_Global_Heading_Font' ) ) { require_once dirname( __DIR__ ) . '/features/class-ds-global-heading-font.php'; }

$GLOBALS['gfb_fail'] = 0; $GLOBALS['gfb_n'] = 0;
function gfb_is( $label, $got, $want ) {
	$GLOBALS['gfb_n']++;
	$ok = $got === $want;
	if ( ! $ok ) { $GLOBALS['gfb_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : "\n     got:  " . wp_json_encode( $got ) . "\n     want: " . wp_json_encode( $want ) ) . "\n";
}

$fonts = new ReflectionProperty( 'FLBuilderFonts', 'fonts' );
$fonts->setAccessible( true );
$gs = new ReflectionProperty( 'FLBuilderGlobalStyles', 'settings' );
$gs->setAccessible( true );
FLBuilderGlobalStyles::get_settings( false ); // fill the cache so it can be put back
$saved_fonts = $fonts->getValue();
$saved_gs    = $gs->getValue();

/** Runs register() against these Global Styles; returns the families and weights BB would request. */
$run = function ( array $styles ) use ( $fonts, $gs ) {
	$gs->setValue( null, (object) $styles );
	$fonts->setValue( null, array() );
	( new DS_Global_Heading_Font() )->register();
	$out = array();
	foreach ( (array) $fonts->getValue() as $family => $weights ) {
		$w = array_values( array_map( 'strval', (array) $weights ) );
		sort( $w );
		$out[ $family ] = $w;
	}
	ksort( $out );
	return $out;
};

gfb_is( 'body font at the default weight gets its bold face',
	$run( array( 'text_typography' => array( 'font_family' => 'Barlow' ) ) ), array( 'Barlow' => array( '400', '700' ) ) );
gfb_is( 'a light body weight keeps it and adds bold',
	$run( array( 'text_typography' => array( 'font_family' => 'Barlow', 'font_weight' => '300' ) ) ), array( 'Barlow' => array( '300', '700' ) ) );
gfb_is( 'a body font already set in 700 is requested once',
	$run( array( 'text_typography' => array( 'font_family' => 'Barlow', 'font_weight' => '700' ) ) ), array( 'Barlow' => array( '700' ) ) );
gfb_is( 'a family with no bold face gets no 700 (Bebas Neue)',
	$run( array( 'text_typography' => array( 'font_family' => 'Bebas Neue' ) ) ), array( 'Bebas Neue' => array( '400' ) ) );
gfb_is( 'a system font requests nothing',
	$run( array( 'text_typography' => array( 'font_family' => 'Helvetica' ) ) ), array() );
gfb_is( 'headings keep only their own weight; the body font gets bold',
	$run( array( 'text_typography' => array( 'font_family' => 'Barlow' ), 'h_typography' => array( 'font_family' => 'Oswald', 'font_weight' => '500' ) ) ),
	array( 'Barlow' => array( '400', '700' ), 'Oswald' => array( '500' ) ) );
gfb_is( '"Default" body font requests nothing',
	$run( array( 'text_typography' => array( 'font_family' => 'Default' ) ) ), array() );

$gs->setValue( null, $saved_gs );
$fonts->setValue( null, $saved_fonts );

echo $GLOBALS['gfb_fail'] ? "FAILURES: {$GLOBALS['gfb_fail']} of {$GLOBALS['gfb_n']}\n" : "ALL {$GLOBALS['gfb_n']} PASS\n";
if ( $GLOBALS['gfb_fail'] ) { exit( 1 ); }
