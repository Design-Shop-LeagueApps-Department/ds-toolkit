<?php
namespace Hummingbird\Core {
	// Stand-in for Hummingbird's module registry: the Critical CSS module with a snapshot folder.
	class Utils {
		public static $module = null;
		public static function get_module( $key ) { return 'critical_css' === $key ? self::$module : null; }
	}
	class FakeCritical {
		public $active = true; public $path = '';
		public function is_active() { return $this->active; }
		public function get_critical_css_path() { return $this->path; }
	}
}

namespace {
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * Hummingbird Critical CSS refresh (features/class-ds-hummingbird-critical-fix.php): publishing a
 * layout deletes exactly that page's saved CSS snapshot, a Themer layout clears them all, drafts and
 * revisions touch nothing, and nothing outside Hummingbird's folder is ever deleted. No WordPress:
 *
 *   php tests/hummingbird-critical-fix-test.php
 */
$root = sys_get_temp_dir() . '/dshb-' . getmypid();
define( 'ABSPATH', $root . '/' );
define( 'WP_CONTENT_DIR', $root . '/wp-content' );

$GLOBALS['types'] = array(); $GLOBALS['status'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['meta_deleted'] = array(); $GLOBALS['hooks'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ] = $cb; }
function get_post_type( $id ) { return $GLOBALS['types'][ $id ] ?? false; }
function get_post_status( $id ) { return $GLOBALS['status'][ $id ] ?? 'publish'; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function delete_post_meta( $id, $k ) { $GLOBALS['meta_deleted'][] = array( $id, $k ); return true; }
function wp_is_post_revision( $id ) { return 'revision' === ( $GLOBALS['types'][ $id ] ?? '' ); }
function wp_is_post_autosave( $id ) { return false; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', $p ); }
function wp_delete_file( $f ) { @unlink( $f ); }

require __DIR__ . '/../features/class-ds-hummingbird-critical-fix.php';

$fails = 0;
function chk( $label, $got, $want ) { global $fails; $ok = $got === $want; if ( ! $ok ) { $fails++; } printf( "  %-66s %s\n", $label, $ok ? 'ok' : 'FAIL got ' . var_export( $got, true ) ); }

$dir = WP_CONTENT_DIR . '/wphb-cache/critical-css/example.org/';
$outside = WP_CONTENT_DIR . '/uploads/';
function reset_files() {
	global $dir, $outside;
	@mkdir( $dir, 0777, true ); @mkdir( $outside, 0777, true );
	foreach ( glob( $dir . '*' ) as $f ) { unlink( $f ); }
	foreach ( array( 'page-5', 'page-5-mobile', 'page-6', 'frontpage', 'staff-9', 'staff', 'post', 'category' ) as $n ) { file_put_contents( $dir . $n . '-used.css', 'x' ); }
	file_put_contents( $outside . 'page-5-used.css', 'keep' );
}
function left() { global $dir; $o = array_map( 'basename', glob( $dir . '*-used.css' ) ); sort( $o ); return $o; }

$m = new \Hummingbird\Core\FakeCritical(); $m->path = $dir; \Hummingbird\Core\Utils::$module = $m;
$GLOBALS['types'] = array( 5 => 'page', 6 => 'page', 7 => 'page', 9 => 'staff', 20 => 'fl-theme-layout', 21 => 'fl-builder-template', 30 => 'revision' );
$fx = new DS_Hummingbird_Critical_Fix(); $fx->init();

echo "publishing a page clears that page only\n";
reset_files();
chk( 'returns the files it removed', DS_Hummingbird_Critical_Fix::clear_for_post( 5 ), array( 'page-5-used.css', 'page-5-mobile-used.css' ) );
chk( 'other pages keep their snapshot', left(), array( 'category-used.css', 'frontpage-used.css', 'page-6-used.css', 'post-used.css', 'staff-9-used.css', 'staff-used.css' ) );
chk( 'the page\'s status meta is reset', $GLOBALS['meta_deleted'], array( array( 5, '_wphb_singular_css_status' ) ) );
chk( 'a same-named file outside Hummingbird\'s folder is untouched', file_exists( $outside . 'page-5-used.css' ), true );

echo "the front page also drops the frontpage snapshot\n";
reset_files(); $GLOBALS['opts']['page_on_front'] = 6;
DS_Hummingbird_Critical_Fix::clear_for_post( 6 );
chk( 'page-6 and frontpage gone', array_values( array_diff( array( 'page-6-used.css', 'frontpage-used.css' ), left() ) ), array( 'page-6-used.css', 'frontpage-used.css' ) );
$GLOBALS['opts'] = array();

echo "a single post clears its own and its post type's snapshot\n";
reset_files();
DS_Hummingbird_Critical_Fix::clear_for_post( 9 );
chk( 'staff-9 and staff gone, the rest kept', left(), array( 'category-used.css', 'frontpage-used.css', 'page-5-mobile-used.css', 'page-5-used.css', 'page-6-used.css', 'post-used.css' ) );

echo "a Themer layout or saved template clears every snapshot\n";
reset_files(); DS_Hummingbird_Critical_Fix::clear_for_post( 20 ); chk( 'Themer layout: none left', left(), array() );
reset_files(); DS_Hummingbird_Critical_Fix::clear_for_post( 21 ); chk( 'saved template: none left', left(), array() );
chk( 'still nothing outside the folder', file_exists( $outside . 'page-5-used.css' ), true );

echo "the hooks\n";
reset_files(); $GLOBALS['hooks']['fl_builder_after_save_layout']( 5, false ); chk( 'builder draft save: nothing cleared', count( left() ), 8 );
reset_files(); $GLOBALS['hooks']['fl_builder_after_save_layout']( 5, true ); chk( 'builder publish: page-5 cleared', in_array( 'page-5-used.css', left(), true ), false );
reset_files(); $GLOBALS['status'][6] = 'draft'; $GLOBALS['hooks']['save_post']( 6, null ); chk( 'save_post of a draft: nothing cleared', count( left() ), 8 );
reset_files(); $GLOBALS['hooks']['save_post']( 30, null ); chk( 'save_post of a revision: nothing cleared', count( left() ), 8 );
reset_files(); unset( $GLOBALS['status'][6] ); $GLOBALS['hooks']['save_post']( 6, null ); chk( 'save_post of a published page: cleared', in_array( 'page-6-used.css', left(), true ), false );

echo "inert unless Critical CSS is on, and only inside Hummingbird's folder\n";
reset_files(); $m->active = false; chk( 'module off: nothing removed', DS_Hummingbird_Critical_Fix::clear_for_post( 5 ), array() ); $m->active = true;
reset_files(); $m->path = $outside; chk( 'a path outside wphb-cache/critical-css is refused', DS_Hummingbird_Critical_Fix::snapshot_dir(), '' ); $m->path = $dir;
reset_files(); \Hummingbird\Core\Utils::$module = null; chk( 'no module: nothing removed', DS_Hummingbird_Critical_Fix::clear_for_post( 5 ), array() ); \Hummingbird\Core\Utils::$module = $m;
chk( 'post id 0 does nothing', DS_Hummingbird_Critical_Fix::clear_for_post( 0 ), array() );

exec( 'rm -rf ' . escapeshellarg( $root ) );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
}
