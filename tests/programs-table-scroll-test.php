<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * LeagueApps Programs: a table wider than its box scrolls sideways inside it, at every width.
 *
 *   php tests/programs-table-scroll-test.php
 *
 * Why (2026-11-07, Zay on ethosvolleyball /register/): between the phone breakpoint and ~1100px the
 * table was cut off at the box edge with no way to reach the Register column. The Style tab's corner
 * radius emitted `overflow:hidden` on .ds-programs-scroll, cancelling its overflow-x:auto (856px table
 * in a 594-818px box). At 800-900px the whole page also scrolled sideways: the visually hidden
 * "Register" heading is position:absolute and escaped the clip because the box was not positioned.
 * And at 320px the phone card's "View schedule / View standings" pair ran 13px past the card.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DS_TOOLKIT_URL', 'https://example.test/wp-content/plugins/ds-toolkit/' );
define( 'DS_TOOLKIT_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 );

function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( $s ) ) ); }
function get_theme_mod( $k, $d = '' ) { return $d; }
function add_action() {} function add_filter() {}
function get_transient( $k ) { return false; } function set_transient() { return true; } function apply_filters( $h, $v ) { return $v; }
function is_admin() { return false; } function wp_list_pluck( $l, $f ) { return array_column( array_map( 'get_object_vars', array_map( function ( $x ) { return (object) $x; }, (array) $l ) ), $f ); }
function get_option( $k, $d = false ) {
	if ( '_fl_builder_styles' === $k ) { return (object) array( 'colors' => array( array( 'label' => 'Primary', 'color' => '#007a10' ) ) ); }
	return $d;
}
class FLBuilderModule { public $settings; public function __construct( $a = array() ) {} }
class FLBuilder { public static function register_module( $c, $f ) {} public static function register_settings_form( $k, $f ) {} public static function __callStatic( $n, $a ) {} }
class FLBuilderCSS { public static function typography_field_rule( $a ) {} }

require DS_TOOLKIT_PATH . 'includes/class-ds-module-ui.php';
require DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
require DS_TOOLKIT_PATH . 'modules/ds-programs/ds-programs.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-74s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

/** The node CSS a module would get, from the real generator. */
function css_for( array $settings ) {
	$module = ( new ReflectionClass( 'DS_Programs_Module' ) )->newInstanceWithoutConstructor();
	$module->settings = (object) $settings;
	$settings = $module->settings;
	$id = 'abc123';
	ob_start();
	include DS_TOOLKIT_PATH . 'modules/ds-programs/includes/frontend.css.php';
	return ob_get_clean();
}
/** Every declaration block whose selector list mentions $sel, outside and inside media queries. */
function blocks_for( $css, $sel ) {
	preg_match_all( '/([^{}]*' . preg_quote( $sel, '/' ) . '[^{}]*)\{([^{}]*)\}/', $css, $m );
	return $m[2];
}

$dir    = DS_TOOLKIT_PATH . 'modules/ds-programs/';
$static = file_get_contents( $dir . 'css/frontend.css' );
$table  = file_get_contents( $dir . 'includes/table.php' );
$js     = file_get_contents( $dir . 'js/frontend.js' );

echo "node CSS never clips the scroll box\n";
$presets = array_keys( DS_Programs_Module::presets() );
foreach ( $presets as $p ) {
	foreach ( array( '', '12' ) as $radius ) {
		$css  = css_for( array( 'style_preset' => $p, 'table_radius' => $radius, 'table_shadow' => 'soft', 'table_border' => 'outer' ) );
		$bad  = array_filter( blocks_for( $css, '.ds-programs-scroll' ), function ( $b ) { return (bool) preg_match( '/overflow(-x)?\s*:\s*hidden/', $b ); } );
		chk( "preset $p, radius " . ( $radius ?: 'none' ) . ': no overflow:hidden on .ds-programs-scroll', array_values( $bad ), array() );
	}
}
$css = css_for( array( 'style_preset' => 'modern', 'table_radius' => '12' ) );
chk( 'radius still rounds the box', (bool) preg_match( '/\.fl-node-abc123 \.ds-programs-scroll\{[^}]*border-radius:12px/', $css ), true );
chk( 'edge shadows follow the radius', false !== strpos( $css, '.ds-programs-frame::after{border-radius:0 12px 12px 0;}' ), true );

echo "\nstatic CSS: the box scrolls, contains its children, shows that it scrolls\n";
preg_match( '/\n\.ds-programs-scroll \{([^}]*)\}/', $static, $sb );
$sb = $sb[1] ?? '';
chk( '.ds-programs-scroll overflow-x:auto', (bool) preg_match( '/overflow-x:\s*auto/', $sb ), true );
chk( '.ds-programs-scroll position:relative (abs-pos children stay inside)', (bool) preg_match( '/position:\s*relative/', $sb ), true );
chk( '.ds-programs-scroll overscroll-behavior-x:contain', (bool) preg_match( '/overscroll-behavior-x:\s*contain/', $sb ), true );
chk( 'scrollbar stays visible where styleable', (bool) preg_match( '/\.ds-programs-scroll::-webkit-scrollbar \{ height: 8px; \}/', $static ), true );
chk( 'module can shrink inside a flex row or grid cell', (bool) preg_match( '/\.fl-module-ds-programs,\s*\.ds-programs \{ min-width: 0; \}/', $static ), true );
preg_match( '/\n\.ds-programs-field \{([^}]*)\}/', $static, $fb );
$fb = $fb[1] ?? '';
chk( 'filter fields widen with large text (em term in flex-basis)', (bool) preg_match( '/flex-basis:\s*max\(150px,\s*8\.5em\)/', $fb ), true );
chk( 'filter fields widen with large text (em term in max-width)', (bool) preg_match( '/max-width:\s*max\(230px,\s*13em\)/', $fb ), true );
chk( 'px fallbacks kept before the max() lines', (bool) preg_match( '/flex:\s*1 1 150px;[^}]*max-width:\s*230px;\s*max-width:\s*max\(/', $fb ), true );
// 8.5em and 13em reach the px values at ~17.6px: normal theme text must render exactly as before.
chk( 'em terms stay under the px values at 17.5px text', 8.5 * 17.5 <= 150 && 13 * 17.5 <= 230, true );
chk( 'edge shadows only when the frame says so', (bool) preg_match( '/\.ds-programs-frame\.can-scroll-left::before,\s*\.ds-programs-frame\.can-scroll-right::after \{ opacity: 1; \}/', $static ), true );

echo "\nphone cards: nothing runs past the card\n";
$css   = css_for( array( 'style_preset' => 'modern', 'table_radius' => '12', 'season_links' => 'yes' ) );
$phone = '';
if ( preg_match( '/@media\(max-width:\d+px\)\{\n(.*?)\n\}/s', $css, $pm ) ) { $phone = $pm[1]; }
chk( 'phone media block found', '' !== $phone, true );
chk( 'phone: the box stops scrolling (overflow:visible)', (bool) preg_match( '/\.ds-programs--table \.ds-programs-scroll\{overflow:visible;/', $phone ), true );
chk( 'phone: no scroll shadows', (bool) preg_match( '/\.ds-programs--table \.ds-programs-frame::after\{display:none;\}/', $phone ), true );
chk( 'phone: season links wrap', (bool) preg_match( '/\.ds-programs-seasonlinks\{[^}]*flex-wrap:wrap/', $phone ), true );
chk( 'phone: season link text may wrap', (bool) preg_match( '/a\.ds-programs-seasonlink\{[^}]*white-space:normal/', $phone ), true );
chk( 'phone: long values break', (bool) preg_match( '/\.ds-programs-table \.ds-programs-td\{[^}]*overflow-wrap:anywhere/', $phone ), true );
// Every phone rule is scoped to this node: an unscoped comma selector would restyle other modules on the page.
$unscoped = array();
foreach ( explode( "\n", $phone ) as $line ) {
	if ( '' === trim( $line ) ) { continue; }
	$sel = substr( $line, 0, strpos( $line, '{' ) );
	foreach ( explode( ',', $sel ) as $s ) { if ( false === strpos( $s, '.fl-node-abc123' ) && false !== strpos( $s, 'frame' ) ) { $unscoped[] = trim( $s ); } }
}
chk( 'phone: frame rules are node-scoped', $unscoped, array() );
chk( 'cards layout card values break too', (bool) preg_match( '/\.ds-programs-card-field dd \{[^}]*overflow-wrap: anywhere/', $static ), true );

echo "\nmarkup + JS\n";
chk( 'table sits in a frame the JS watches', (bool) preg_match( '/<div class="ds-programs-frame" data-ds-programs-frame data-label="[^"]+">\s*<div class="ds-programs-scroll">/', $table ), true );
chk( 'frame closes after the scroll box', (bool) preg_match( '#</table>\s*</div>\s*</div>#', $table ), true );
chk( 'JS toggles can-scroll-left / can-scroll-right', false !== strpos( $js, "'can-scroll-left'" ) && false !== strpos( $js, "'can-scroll-right'" ), true );
chk( 'JS re-measures on resize (ResizeObserver on box and table)', (bool) preg_match( '/new ResizeObserver\(update\);\s*ro\.observe\(box\);/', $js ), true );
chk( 'JS makes an overflowing box a focusable region', false !== strpos( $js, "box.setAttribute('tabindex', '0')" ) && false !== strpos( $js, "box.setAttribute('role', 'region')" ), true );
chk( 'JS watches frames on every boot (builder re-render)', (bool) preg_match( "/function boot\(\) \{\s*Array\.prototype\.forEach\.call\(document\.querySelectorAll\('\[data-ds-programs-frame\]'\), watchFrame\);/", $js ), true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
