<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * Site layouts (1.20.0): a block style from the theme's ds-layouts folder. Stub-style:  php tests/site-layouts-test.php
 *
 * Why (Alipio 2026-11-19): "update the leagueapps hero banner module to have custom template and it will adapt in options
 * below". Pins discovery (child before parent, bad slugs skipped), the header, the JSON field filter (types and keys),
 * the namespaced field names, the form extension (option, toggle, hide, sections) and the fallback when a layout throws.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
$root = dirname( __DIR__ ) . '/';
$tmp  = sys_get_temp_dir() . '/ds-sl-' . getmypid() . '/';
$child = $tmp . 'child/'; $parent = $tmp . 'parent/';
foreach ( array( $child . 'ds-layouts/ds-hero', $parent . 'ds-layouts/ds-hero' ) as $d ) { @mkdir( $d, 0777, true ); }

function __( $s, $d = '' ) { return $s; }
function apply_filters( $h, $v ) { return $v; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['filters'][ $h ] = $cb; }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function get_stylesheet_directory() { return rtrim( $GLOBALS['child'], '/' ); }
function get_template_directory() { return rtrim( $GLOBALS['parent'], '/' ); }
function get_stylesheet_directory_uri() { return 'https://x.test/child'; }
function get_template_directory_uri() { return 'https://x.test/parent'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s ); }
function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }
function current_user_can( $c ) { return true; }
function wp_enqueue_style() { $GLOBALS['enq'][] = func_get_args()[0]; }
function wp_enqueue_script() { $GLOBALS['enq'][] = func_get_args()[0]; }
function get_file_data( $file, $headers ) {   // WordPress's header reader, reduced to what the class uses
	$data = file_get_contents( $file, false, null, 0, 8192 ); $out = array();
	foreach ( $headers as $k => $label ) { $out[ $k ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $data, $m ) ? trim( $m[1] ) : ''; }
	return $out;
}
require $root . 'includes/class-ds-site-layouts.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-78s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

// fixtures
file_put_contents( $child . 'ds-layouts/ds-hero/numeral.php', "<?php\n/**\n * Layout Name: Numeral Window\n * Description: Photos inside a number.\n * Uses: text, buttons, bg, nonsense\n * Hide: kenburns, Slide_Nav\n */\necho '<b>' . esc_html( \$fields['number'] ) . '|' . esc_html( \$settings->heading ) . '</b>';\n" );
file_put_contents( $child . 'ds-layouts/ds-hero/numeral.json', json_encode( array( 'sections' => array( array( 'key' => 'Num', 'title' => 'Numeral', 'fields' => array(
	'number' => array( 'type' => 'text', 'label' => 'Number', 'default' => '91', 'onclick' => 'x' ),
	'code'   => array( 'type' => 'code', 'label' => 'PHP' ),
	'pick'   => array( 'type' => 'select', 'label' => 'Empty select' ),
) ) ) ) ) );
file_put_contents( $child . 'ds-layouts/ds-hero/numeral.css', '.x{}' );
file_put_contents( $child . 'ds-layouts/ds-hero/Bad Name.php', "<?php\n/* Layout Name: Bad */" );
file_put_contents( $child . 'ds-layouts/ds-hero/boom.php', "<?php\n/**\n * Layout Name: Boom\n */\nthrow new RuntimeException( 'broken' );\n" );
file_put_contents( $parent . 'ds-layouts/ds-hero/numeral.php', "<?php\n/* Layout Name: Parent copy */" );
file_put_contents( $parent . 'ds-layouts/ds-hero/plain.php', "<?php\necho 'plain';\n" );

echo "discovery\n";
$all = DS_Site_Layouts::all( 'ds-hero' );
chk( 'finds child + parent layouts, skips a bad file name', array_keys( $all ), array( 'boom', 'numeral', 'plain' ) );
chk( 'the child theme copy wins over the parent copy', $all['numeral']['label'], 'Numeral Window' );
chk( 'no Layout Name = the slug in words', $all['plain']['label'], 'Plain' );
chk( 'Uses and Hide are lists, lowercased', array( $all['numeral']['uses'], $all['numeral']['hide'] ), array( array( 'text', 'buttons', 'bg', 'nonsense' ), array( 'kenburns', 'slide_nav' ) ) );
chk( 'sibling CSS found, JS absent', array( $all['numeral']['css'], $all['numeral']['js'] ), array( 'https://x.test/child/ds-layouts/ds-hero/numeral.css', '' ) );
chk( 'style options are "site-<slug>" => "Site: <name>"', DS_Site_Layouts::style_options( 'ds-hero' )['site-numeral'] ?? '', 'Site: Numeral Window' );
chk( 'get() resolves a saved key, unknown is null', array( DS_Site_Layouts::get( 'ds-hero', 'site-numeral' )['slug'], DS_Site_Layouts::get( 'ds-hero', 'site-gone' ), DS_Site_Layouts::get( 'ds-hero', 'style1' ) ), array( 'numeral', null, null ) );

echo "fields from the JSON\n";
$secs = $all['numeral']['sections'];
chk( 'section key is namespaced to the layout', array_keys( $secs ), array( 'sl_numeral_num' ) );
$f = $secs['sl_numeral_num']['fields'];
chk( 'only allowed types pass (no "code"), an empty select is dropped', array_keys( $f ), array( 'sl_numeral__number' ) );
chk( 'unknown keys are stripped (onclick)', isset( $f['sl_numeral__number']['onclick'] ), false );
chk( 'the first section carries the Description', $secs['sl_numeral_num']['description'] ?? '', 'Photos inside a number.' );
chk( 'values(): default when nothing saved, saved value otherwise', array( DS_Site_Layouts::values( $all['numeral'], (object) array() )['number'], DS_Site_Layouts::values( $all['numeral'], (object) array( 'sl_numeral__number' => '12' ) )['number'] ), array( '91', '12' ) );

echo "form extension\n";
$form = array( 'content' => array( 'sections' => array(
	'hero_style_sec' => array( 'fields' => array( 'hero_style' => array( 'options' => array( 'style1' => 'Classic' ), 'toggle' => array(), 'hide' => array() ) ) ),
	'text' => array(), 'buttons' => array(), 'bg' => array(), 'spacing' => array() ) ), 'style' => array( 'sections' => array( 'colors' => array() ) ) );
$x = DS_Site_Layouts::extend_form( $form, 'ds-hero', 'content', 'hero_style_sec', 'hero_style', array( 'text', 'spacing' ) );
$sel = $x['content']['sections']['hero_style_sec']['fields']['hero_style'];
chk( 'the built-in option stays first, layouts are added', array_keys( $sel['options'] ), array( 'style1', 'site-boom', 'site-numeral', 'site-plain' ) );
chk( 'toggle = the Uses sections that exist + the layout\'s own', $sel['toggle']['site-numeral']['sections'], array( 'text', 'buttons', 'bg', 'sl_numeral_num' ) );
chk( 'no Uses line = the block default', $sel['toggle']['site-plain']['sections'], array( 'text', 'spacing' ) );
chk( 'Hide becomes the select\'s hide', $sel['hide']['site-numeral']['fields'], array( 'kenburns', 'slide_nav' ) );
chk( 'the layout section is added to the tab', isset( $x['content']['sections']['sl_numeral_num'] ), true );
chk( 'no layouts = form untouched', DS_Site_Layouts::extend_form( $form, 'ds-marquee', 'content', 'hero_style_sec', 'hero_style', array() ), $form );

echo "render\n";
class FLBuilderModel { public static function is_builder_active() { return false; } }
ob_start(); $ok = DS_Site_Layouts::render( $all['numeral'], null, (object) array( 'heading' => 'Play <Ball>' ) ); $html = ob_get_clean();
chk( 'renders with $fields and $settings in scope', array( $ok, $html ), array( true, '<b>91|Play &lt;Ball&gt;</b>' ) );
chk( 'and enqueues its CSS', $GLOBALS['enq'] ?? array(), array( 'ds-site-layout-ds-hero-numeral' ) );
ob_start(); $ok = DS_Site_Layouts::render( $all['boom'], null, (object) array() ); $html = ob_get_clean();
chk( 'a layout that throws returns false (caller falls back)', $ok, false );
chk( 'and leaves only an admin comment', 0 === strpos( $html, '<!-- DS site layout "boom" failed: broken' ), true );
unlink( $parent . 'ds-layouts/ds-hero/plain.php' );   // plain lives in the parent theme only
chk( 'a file removed after discovery returns false', DS_Site_Layouts::render( $all['plain'], null, (object) array() ), false );

echo "the Hero Banner is wired\n";
$hero = file_get_contents( $root . 'modules/ds-hero/ds-hero.php' );
chk( 'render_hero() checks site keys before its own styles', false !== strpos( $hero, 'DS_Site_Layouts::is_site_key( $raw )' ), true );
chk( 'and falls back to Style 1', false !== strpos( $hero, "if ( \$layout && DS_Site_Layouts::render( \$layout, \$this, \$this->settings ) ) { return; }\n\t\t\t\$this->render_style1();" ), true );
chk( 'the feature hooks the form before the module registers', false !== strpos( file_get_contents( $root . 'features/class-ds-hero.php' ), "DS_Site_Layouts::hook_form( 'ds-hero'" ), true );
chk( 'the plugin loads the class', false !== strpos( file_get_contents( $root . 'ds-toolkit.php' ), "includes/class-ds-site-layouts.php" ), true );

array_map( 'unlink', glob( $tmp . '*/ds-layouts/ds-hero/*' ) );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
