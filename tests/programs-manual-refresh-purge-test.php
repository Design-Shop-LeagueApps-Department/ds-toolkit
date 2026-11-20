<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * "Refresh LeagueApps listings" also clears the page cache (1.20.2). The listings and the calendar are part of the
 * page HTML, so before this a refresh reached logged-in editors only: visitors kept the cached page, 12 hours on
 * speed-kit sites (Supernova All-Stars, 2026-11-21: Zay asked whether the calendar bypasses WP Engine's cache; it did not).
 *
 *   php tests/programs-manual-refresh-purge-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['tr'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['actions'] = array(); $GLOBALS['purges'] = array();
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['tr'] ) ? $GLOBALS['tr'][ $k ][0] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['tr'][ $k ] = array( $v, $ttl ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['tr'][ $k ] ); return true; }
function get_option( $k, $d = '' ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function do_action( $h, $a = null ) { $GLOBALS['actions'][] = $h; }
function has_action( $h, $cb = false ) { return false; }
function add_action( $h, $cb ) {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_list_pluck( $l, $f ) { $o = array(); foreach ( $l as $i ) { $o[] = is_array( $i ) ? $i[ $f ] : $i->$f; } return $o; }
function __( $s, $d = '' ) { return $s; }
class FakeWpdb { public $options = 'wp_options'; function query( $q ) { return 0; } }
$wpdb = new FakeWpdb();
require dirname( __DIR__ ) . '/includes/class-ds-programs-data.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-80s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

echo "Page cache\n";
chk( 'not on WP Engine: purge is a no-op', method_exists( 'DS_Programs_Data', 'purge_page_cache' ) ? DS_Programs_Data::purge_page_cache() : 'missing', false );
eval( 'class WpeCommon { public static function purge_varnish_cache( $post_id = null, $force = false ) { $GLOBALS["purges"][] = $post_id; return true; } public static function purge_memcached() { $GLOBALS["purges"][] = "memcached"; } }' );

echo "Refresh LeagueApps listings\n";
$first = method_exists( 'DS_Programs_Data', 'manual_refresh' ) ? DS_Programs_Data::manual_refresh() : 'missing';
chk( 'first press runs', $first, true );
chk( '... clears the WHOLE page cache once (no post id)', $GLOBALS['purges'], array( null ) );
chk( '... never the object cache (logins live there)', in_array( 'memcached', $GLOBALS['purges'], true ), false );
chk( '... flushes the data first (Programs, then the Calendar via the hook)', in_array( 'ds_programs_flushed', $GLOBALS['actions'], true ), true );
chk( '... and marks the data flushed so the next view refetches', false !== get_transient( 'ds_programs_flushed' ), true );
chk( '... throttle set for a minute', $GLOBALS['tr']['ds_programs_flush_throttle'][1] ?? null, 60 );
$second = method_exists( 'DS_Programs_Data', 'manual_refresh' ) ? DS_Programs_Data::manual_refresh() : 'missing';
chk( 'a second press inside the minute does nothing', array( $second, count( $GLOBALS['purges'] ) ), array( false, 1 ) );

echo "Both Refresh handlers use it\n";
foreach ( array( 'features/class-ds-programs.php', 'features/class-ds-la-calendar.php' ) as $f ) {
	$src = file_get_contents( dirname( __DIR__ ) . '/' . $f );
	preg_match( '/public function flush\(\) \{(.*?)\n\t\}/s', $src, $m );
	$body = $m[1] ?? '';
	chk( "$f flush() calls manual_refresh()", false !== strpos( $body, 'DS_Programs_Data::manual_refresh()' ), true );
	chk( "$f flush() no longer flushes without purging", false !== strpos( $body, 'DS_Programs_Data::flush()' ), false );
}

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
