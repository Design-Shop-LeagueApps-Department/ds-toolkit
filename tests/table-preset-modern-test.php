<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * Table + Programs: the "Modern" preset (1.10.44). Stub-style, no WordPress needed:
 *
 *   php tests/table-preset-modern-test.php
 *   php tests/table-preset-modern-test.php --dump <table|programs> <preset>   (prints the node CSS)
 *
 * Modern is the default for NEW modules only. Every module already on a page has its preset
 * saved (style_preset has been a field since each module's first release), so the rules here
 * must appear for "modern" and never for any other preset, and a Style field the editor sets
 * must still win over them.
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
$GLOBALS['ds_test_primary'] = '#007a10';
function get_option( $k, $d = false ) {
	if ( '_fl_builder_styles' === $k ) { return (object) array( 'colors' => array( array( 'label' => 'Primary', 'color' => $GLOBALS['ds_test_primary'] ) ) ); }
	return $d;
}
class FLBuilderModule { public $settings; public function __construct( $a = array() ) {} }
class FLBuilder { public static function register_module( $c, $f ) {} public static function register_settings_form( $k, $f ) {} public static function __callStatic( $n, $a ) {} }
class FLBuilderCSS { public static $typo = array(); public static function typography_field_rule( $a ) { self::$typo[] = $a['selector']; echo '/*typo ' . $a['selector'] . "*/\n"; } }

require DS_TOOLKIT_PATH . 'includes/class-ds-module-ui.php';
require DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
require DS_TOOLKIT_PATH . 'modules/ds-table/ds-table.php';
require DS_TOOLKIT_PATH . 'modules/ds-programs/ds-programs.php';

$fails = 0; $n = 0;
function ok( $cond, $msg ) { global $fails, $n; $n++; if ( ! $cond ) { $fails++; echo "FAIL: $msg\n"; } }

/** The node CSS a module would get, from the real generator. */
function css_for( $which, array $settings, $file = null ) {
	$class  = 'table' === $which ? 'DS_Table_Module' : 'DS_Programs_Module';
	$module = ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
	$module->settings = (object) $settings;
	$settings = $module->settings;
	$id = 'abc123';
	ob_start();
	include $file ?: DS_TOOLKIT_PATH . 'modules/ds-' . $which . '/includes/frontend.css.php';
	return ob_get_clean();
}

if ( isset( $argv[1] ) && '--dump' === $argv[1] ) {
	echo css_for( $argv[2], array( 'style_preset' => $argv[3] ), $argv[4] ?? null );
	exit( 0 );
}

$table_presets    = array_keys( DS_Table_Module::presets() );
$programs_presets = array_keys( DS_Programs_Module::presets() );
ok( in_array( 'modern', $table_presets, true ) && in_array( 'modern', $programs_presets, true ), 'both modules offer Modern' );

/* ---- the field default is Modern (new modules), the old presets are all still there ---- */
foreach ( array( 'table' => 'ds-table/ds-table.php', 'programs' => 'ds-programs/ds-programs.php' ) as $w => $f ) {
	$src = file_get_contents( DS_TOOLKIT_PATH . 'modules/' . $f );
	ok( 1 === preg_match( "/'style_preset' => array\(\s*'type'\s*=> 'select',\s*'label'\s*=> [^\n]+\n\s*'default' => 'modern'/", $src ), "$w: style_preset defaults to modern" );
	foreach ( 'table' === $w ? array( 'striped', 'brand', 'framed', 'bordered', 'minimal', 'custom' ) : array( 'striped', 'bordered', 'minimal', 'cards', 'custom' ) as $old ) {
		ok( false !== strpos( $src, "'$old'" ), "$w: preset $old still offered" );
	}
}

/* ---- Modern-only rules appear for Modern and for no other preset ---- */
$markers = array(
	'table'    => array( 'text-transform:uppercase;}', 'ds-table-page.is-current{background-color:', 'rgba(16,24,40,.04)' ),
	'programs' => array( 'text-transform:uppercase;}', 'max-width:1199px', 'min-width:220px', 'rgba(16,24,40,.04)', 'margin-bottom:13px' ),
);
foreach ( array( 'table' => $table_presets, 'programs' => $programs_presets ) as $w => $list ) {
	$list[] = 'custom';
	foreach ( $list as $p ) {
		$css = css_for( $w, array( 'style_preset' => $p ) );
		foreach ( $markers[ $w ] as $m ) {
			ok( ( 'modern' === $p ) === ( false !== strpos( $css, $m ) ), "$w/$p: '$m' " . ( 'modern' === $p ? 'present' : 'absent' ) );
		}
	}
}

/* ---- the brand colour: the site's Primary made readable on white, white text on it ---- */
$css = css_for( 'table', array( 'style_preset' => 'modern' ) );
ok( false !== strpos( $css, 'is-current{background-color:#007a10 !important;border-color:#007a10 !important;color:#ffffff !important;}' ), 'table: current page = site Primary (already readable), white text' );
$GLOBALS['ds_test_primary'] = '#ffd400'; // a pale yellow brand must be darkened before white text sits on it
$css = css_for( 'programs', array( 'style_preset' => 'modern' ) );
ok( 1 === preg_match( '/is-current\{background-color:(#[0-9a-f]{6});/', $css, $m ) && '#ffd400' !== $m[1], 'programs: pale Primary is darkened for the current page' );
$rgb = DS_LA_Colors::rgb( $m[1] ?? '#ffd400' );
ok( $rgb && DS_LA_Colors::ratio( array( 255, 255, 255 ), $rgb ) >= 4.5, 'programs: white on the darkened Primary reads (4.5:1)' );
$GLOBALS['ds_test_primary'] = '#007a10';

/* ---- a field the editor sets still wins (emitted after the Modern rule, same weight) ---- */
$css = css_for( 'table', array( 'style_preset' => 'modern', 'active_bg' => 'ff0000', 'head_typo' => array( 'font_size' => array( 'length' => 20 ) ) ) );
ok( strrpos( $css, 'is-current{background-color:#ff0000' ) > strpos( $css, 'is-current{background-color:#007a10' ), 'table: Current page background field comes after the Modern colour' );
ok( strpos( $css, '/*typo .fl-node-abc123 .ds-table .ds-table-t .ds-table-th*/' ) > strpos( $css, 'text-transform:uppercase;}' ), 'table: Header typography comes after the small caps' );
$css = css_for( 'programs', array( 'style_preset' => 'modern', 'cell_pad' => '20' ) );
ok( false === strpos( $css, 'padding:12px 10px' ), 'programs: an editor cell padding switches off the tighter Modern tablet padding' );
$css = css_for( 'programs', array( 'style_preset' => 'modern', 'columns' => array( array( 'col_key' => 'dateRange', 'col_nowrap' => 'yes' ), array( 'col_key' => 'program' ) ) ) );
ok( false === strpos( $css, 'ds-programs-td--dateRange{white-space:normal' ), 'programs: "Keep on one line" on the date column is respected' );
$css = css_for( 'programs', array( 'style_preset' => 'modern', 'mob_card_border' => '3' ) );
ok( false !== strpos( $css, 'border-width:0 0 3px' ), 'programs: an editor phone-card line keeps the old bottom-line card' );
$css = css_for( 'programs', array( 'style_preset' => 'modern' ) );
ok( false !== strpos( $css, 'border-width:1px;box-shadow' ), 'programs: Modern phone rows are framed cards' );
ok( strpos( $css, '.ds-programs-th{background-color:#f5f7fa;color:#4a5568' ) !== false, 'programs: soft header from the preset fields' );

echo ( $fails ? "$fails of $n FAILED\n" : "OK $n checks\n" );
exit( $fails ? 1 : 0 );
