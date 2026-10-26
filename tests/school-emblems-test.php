<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * DS_School_Emblems decisions that must never drift (features/class-ds-school-emblems.php):
 *  - may_replace(): School Logo is only replaced when empty, still the toolkit's own, or a placeholder;
 *    a logo the partner chose stays.
 *  - valid_logo(): only a library file name (a-z 0-9 - .png) and a 32-hex md5 ever reach download_url(),
 *    so an index entry can never point the site at another path or host.
 *  - same_name(): an edited name box turns a pick into a typed name.
 * Runs without WordPress: php tests/school-emblems-test.php
 */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ ); }
if ( ! function_exists( 'remove_accents' ) ) { function remove_accents( $s ) { return strtr( (string) $s, array( 'é' => 'e', 'ñ' => 'n', 'ü' => 'u' ) ); } }
if ( ! function_exists( 'wp_specialchars_decode' ) ) { function wp_specialchars_decode( $s, $q = 0 ) { return html_entity_decode( (string) $s, ENT_QUOTES ); } }
require_once dirname( __DIR__ ) . '/features/class-ds-school-emblems.php';

$fail  = 0;
$total = 0;
$check = function ( $ok, $msg ) use ( &$fail, &$total ) {
	$total++;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $msg . "\n";
};
$R = array( 'DS_School_Emblems', 'may_replace' );

// may_replace( current, auto, placeholder )
$check( true === call_user_func( $R, '', '' ), 'empty School Logo is filled' );
$check( true === call_user_func( $R, 0, 0 ), 'zero School Logo is filled' );
$check( true === call_user_func( $R, '57738', 57738 ), 'the toolkit\'s own logo is replaced (school changed)' );
$check( false === call_user_func( $R, '56526', 57738 ), 'a logo the partner chose stays' );
$check( false === call_user_func( $R, '56526', '' ), 'a partner logo with no toolkit history stays' );
$check( true === call_user_func( $R, '56470', '', true ), 'a placeholder (blueprint LeagueApps Logo) is replaced' );
$check( false === call_user_func( $R, '56470', '', false ), 'the same image is kept when it is not a placeholder' );

// valid_logo()
$ok = array( 'id' => 'tusculum', 'f' => 'tusculum-a034c564.png', 'm' => 'a034c5640ff70179be7e7dc5bca47d62' );
$check( true === DS_School_Emblems::valid_logo( $ok ), 'a library entry is fetchable' );
foreach ( array(
	'path traversal'   => '../../wp-config.php',
	'subfolder'        => 'a/b.png',
	'other host'       => 'https://evil.test/x.png',
	'query string'     => 'x.png?y=1',
	'svg'              => 'x.svg',
	'php'              => 'x.php',
	'upper case'       => 'X.PNG',
	'empty'            => '',
) as $why => $f ) {
	$check( false === DS_School_Emblems::valid_logo( array_merge( $ok, array( 'f' => $f ) ) ), "refused file name: $why" );
}
$check( false === DS_School_Emblems::valid_logo( array_merge( $ok, array( 'm' => 'not-an-md5' ) ) ), 'refused: bad md5' );
$check( false === DS_School_Emblems::valid_logo( array( 'id' => 'x' ) ), 'refused: no file (school without a logo)' );
$check( false === DS_School_Emblems::valid_logo( 'tusculum' ), 'refused: not an array' );

// same_name()
$check( true === DS_School_Emblems::same_name( "St. Mary's University (Texas)", "St. Mary's University (Texas)" ), 'same name' );
$check( true === DS_School_Emblems::same_name( 'St Marys University Texas', "St. Mary's University (Texas)" ), 'punctuation and case ignored' );
$check( true === DS_School_Emblems::same_name( 'Texas A&amp;M University', 'Texas A&M University' ), 'stored entity = the character' );
$check( false === DS_School_Emblems::same_name( 'Boston University', 'Boston College' ), 'a different school' );
$check( false === DS_School_Emblems::same_name( '', 'Boston College' ), 'an emptied box is not the school' );
$check( false === DS_School_Emblems::same_name( 'Boston College Eagles', 'Boston College' ), 'an edited name is a typed name' );

echo ( $total - $fail ) . ' passed, ' . $fail . " failed\n";
exit( $fail ? 1 : 0 );
