<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * DS_Theme_Setting::less_safe_theme_mods(): every colour format Theme Setting can save into
 * an fl-* mod (synced var(--fl-global-*), rgb(), rgba(), #rgba, #rrggbbaa) reaches the BB
 * theme as a hex its LESS can compile, so uploads/bb-theme/skin-*.css is still written.
 * Swaps Beaver Builder's cached Global Styles in memory and compiles the skin in memory
 * (nothing is saved, no file is written):
 *   wp eval-file tests/theme-skin-color-test.php
 * To test a working copy on a site that runs an older ds-toolkit, add --skip-plugins=ds-toolkit.
 * Exit code 1 on any failure.
 */
if ( ! class_exists( 'FLBuilderGlobalStyles' ) ) { echo "Beaver Builder is not active.\n"; exit( 1 ); }
if ( ! class_exists( 'DS_Theme_Setting' ) ) { require_once dirname( __DIR__ ) . '/features/class-ds-theme-setting.php'; }

$GLOBALS['tsc_fail'] = 0; $GLOBALS['tsc_n'] = 0;
function tsc_is( $label, $got, $want ) {
	$GLOBALS['tsc_n']++;
	$ok = $got === $want;
	if ( ! $ok ) { $GLOBALS['tsc_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : "\n     got:  " . wp_json_encode( $got ) . "\n     want: " . wp_json_encode( $want ) ) . "\n";
}

// less_hex(): what the theme's LESS is given for each stored value.
$globals = array( 'var(--fl-global-base)' => 'f4f4f4', 'var(--fl-global-rgb)' => 'rgb(255, 255, 255)', 'var(--fl-global-loop)' => 'var(--fl-global-loop)' );
$cases   = array(
	'#f4f4f4'                => '#f4f4f4',
	'F4F4F4'                 => '#f4f4f4',
	'#fff'                   => '#fff',
	'#FFFFFF80'              => '#ffffff',
	'#ffffff00'              => '',
	'#0008'                  => '#000',
	'#fff0'                  => '',
	'rgb(244, 244, 244)'     => '#f4f4f4',
	'rgba(0,131,71,0.5)'     => '#008347',
	'rgba(0, 0, 0, 0)'       => '',
	'rgb(100% 0% 0% / 50%)'  => '#ff0000',
	'var(--fl-global-base)'  => '#f4f4f4',
	'var( --fl-global-rgb )' => '#ffffff',
	'var(--fl-global-gone)'  => '',
	'var(--fl-global-loop)'  => '',
	'not a colour'           => '',
);
foreach ( $cases as $in => $want ) {
	tsc_is( "less_hex('$in')", DS_Theme_Setting::less_hex( $in, $globals ), $want );
}

// less_safe_theme_mods(): resolves through the live palette, leaves everything else alone.
$gs = new ReflectionProperty( 'FLBuilderGlobalStyles', 'settings' );
$gs->setAccessible( true );
FLBuilderGlobalStyles::get_settings( false ); // fill the cache so it can be put back
$saved_gs = $gs->getValue();
$styles   = (array) $saved_gs;
$styles['prefix'] = '';
$styles['colors'] = array( array( 'label' => 'Base Page Background Color', 'color' => 'f4f4f4', 'uid' => 'tsc1' ) );
$gs->setValue( null, (object) $styles );

$ts   = new DS_Theme_Setting();
$in   = array(
	'fl-body-bg-color'    => 'var(--fl-global-base-page-background-color)',
	'fl-content-bg-color' => 'rgba(255,255,255,0.9)',
	'fl-accent'           => '2b7bb9',
	'fl-body-bg-image'    => 'https://example.com/a.png',
	'fl-css-code'         => 'var(--x)',
	'ds-banner-nobg-color' => 'var(--fl-global-base-page-background-color)',
	'fl-heading-font-size' => 30,
);
$want = array_merge( $in, array( 'fl-body-bg-color' => '#f4f4f4', 'fl-content-bg-color' => '#ffffff' ) );
tsc_is( 'mods: var() and rgba() become hex; other fl-*, code and ds-* mods are untouched', $ts->less_safe_theme_mods( $in ), $want );
tsc_is( 'mods: a non-array passes through', $ts->less_safe_theme_mods( false ), false );

// The real compile: the skin LESS the BB theme builds from these mods, compiled in memory.
if ( class_exists( 'FLCustomizer' ) && class_exists( 'FLCSS' ) ) {
	$rc      = new ReflectionClass( 'FLCustomizer' );
	$private = function ( $name ) use ( $rc ) { $m = $rc->getMethod( $name ); $m->setAccessible( true ); return $m; };
	$compile = function ( $key, $value, $with_fix ) use ( $private, $ts ) {
		$raw = function ( $mods ) use ( $key, $value ) { $mods[ $key ] = $value; return $mods; };
		add_filter( 'fl_theme_mods', $raw, 1 );
		if ( $with_fix ) { add_filter( 'fl_theme_mods', array( $ts, 'less_safe_theme_mods' ), 10 ); }
		$less = FLCSS::replace_tokens( apply_filters( 'fl_theme_compile_less', FLCSS::paths_get_contents( $private( '_get_less_paths' )->invoke( null ) ) ) );
		ob_start();
		$out = $private( '_compile_less' )->invoke( null, $less );
		ob_end_clean();
		remove_filter( 'fl_theme_mods', $raw, 1 );
		remove_filter( 'fl_theme_mods', array( $ts, 'less_safe_theme_mods' ), 10 );
		return is_wp_error( $out ) ? 'LESS error' : 'compiled';
	};
	// Control: without the filter the synced value breaks the compile, so the checks below can fail.
	tsc_is( 'control: without the filter var() fails the skin compile', $compile( 'fl-body-bg-color', 'var(--fl-global-base-page-background-color)', false ), 'LESS error' );
	foreach ( array( 'fl-body-bg-color', 'fl-content-bg-color' ) as $key ) {
		foreach ( array( 'var(--fl-global-base-page-background-color)', 'var(--fl-global-gone)', 'rgb(244, 244, 244)', 'rgba(244,244,244,0.5)', '#ffffff00', '#fff8', '#f4f4f4' ) as $value ) {
			tsc_is( "compile: $key = $value", $compile( $key, $value, true ), 'compiled' );
		}
	}
} else {
	echo "SKIP compile checks: the Beaver Builder theme is not active.\n";
}

$gs->setValue( null, $saved_gs );

echo "\n" . ( $GLOBALS['tsc_n'] - $GLOBALS['tsc_fail'] ) . '/' . $GLOBALS['tsc_n'] . " passed\n";
if ( $GLOBALS['tsc_fail'] ) { exit( 1 ); }
