<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * LeagueApps Programs feed: serve the last copy, refresh in the background. Stub-style, no WordPress:
 *
 *   php tests/programs-refresh-test.php
 *
 * Why (2026-11 audit, item 26): every 10 minutes the visitor who arrived as the copy ran out waited for
 * LeagueApps inside their page load (up to about 25 s per site in the worst case), while the Calendar and
 * Table blocks served their copy and refreshed behind the scenes. And when LeagueApps was down with no copy,
 * the block told visitors "No programs are open right now", which was not true.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['tr'] = array(); $GLOBALS['http'] = array(); $GLOBALS['calls'] = 0; $GLOBALS['opts'] = array(); $GLOBALS['cron'] = array(); $GLOBALS['hooks'] = array();

function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['tr'] ) ? $GLOBALS['tr'][ $k ][0] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['tr'][ $k ] = array( $v, $ttl ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['tr'][ $k ] ); return true; }
function get_option( $k, $d = '' ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function do_action( $h, $a = null ) {}
function has_action( $h, $cb = false ) { return isset( $GLOBALS['hooks'][ $h ] ); }
function add_action( $h, $cb ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function wp_cache_flush() {}
function is_wp_error( $e ) { return $e instanceof WP_Error; }
class WP_Error {
	private $c, $m, $d;
	function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; $this->d = $d; }
	function get_error_message() { return $this->m; }
	function get_error_data() { return $this->d; }
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['calls']++;
	$q = &$GLOBALS['http'];
	return count( $q ) > 1 ? array_shift( $q ) : ( $q[0] ?? array( 'code' => 200, 'body' => '[]', 'headers' => array() ) );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function wp_remote_retrieve_header( $r, $h ) { return $r['headers'][ strtolower( $h ) ] ?? ''; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_list_pluck( $l, $f ) { $o = array(); foreach ( $l as $i ) { $o[] = is_array( $i ) ? $i[ $f ] : $i->$f; } return $o; }
function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
function esc_url_raw( $u ) { return $u; }
function __( $s, $d = '' ) { return $s; }
function home_url( $p = '' ) { return 'https://example.org' . $p; }
function date_i18n( $f, $t ) { return date( $f, $t ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_next_scheduled( $h, $args = array() ) { foreach ( $GLOBALS['cron'] as $e ) { if ( $e[0] === $h && $e[1] === $args ) { return 1; } } return false; }
function wp_schedule_single_event( $t, $h, $args = array() ) { $GLOBALS['cron'][] = array( $h, $args ); return true; }
class FakeWpdb { public $options = 'wp_options'; function query( $q ) { return 0; } }
$wpdb = new FakeWpdb();

require dirname( __DIR__ ) . '/includes/class-ds-programs-data.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-84s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

$site = array( 'site_id' => '47635', 'api_key' => 'k', 'label' => 'Core 5' );
$GLOBALS['opts']['ds_toolkit_settings'] = array( 'leagueapps_sites' => array( $site ) );
$key  = 'ds_programs_' . md5( json_encode( array( '47635' ) ) );
$good = 'ds_programs_good_' . substr( $key, 12 );
$prog = function ( $id, $name ) { return array( 'programId' => $id, 'name' => $name, 'type' => 'LEAGUE', 'mode' => 'YOUTH', 'state' => 'UPCOMING', 'visibility' => 'Public', 'startTime' => ( time() + 86400 ) * 1000, 'endTime' => ( time() + 172800 ) * 1000 ); };
$ok   = function ( array $rows ) { return array( 'code' => 200, 'body' => json_encode( $rows ), 'headers' => array() ); };
$names = function ( $r ) { return array_map( function ( $p ) { return $p['program']; }, $r['programs'] ); };

echo "first load: nothing to show yet, so this one request fetches\n";
$GLOBALS['http'] = array( $ok( array( $prog( 1, 'Fall League' ) ) ) );
$r = DS_Programs_Data::get( array( $site ) );
chk( 'fetched inside the request', array( $GLOBALS['calls'], $names( $r ) ), array( 1, array( 'Fall League' ) ) );
chk( 'nothing queued', $GLOBALS['cron'], array() );
chk( 'last good copy stored, not autoloaded', array( isset( $GLOBALS['opts'][ $good ]['rows'] ), $GLOBALS['autoload'][ $good ] ), array( true, false ) );

echo "the 10-minute copy runs out\n";
delete_transient( $key );
$GLOBALS['http'] = array( $ok( array( $prog( 1, 'Fall League' ), $prog( 2, 'Winter League' ) ) ) );
$GLOBALS['calls'] = 0;
$r = DS_Programs_Data::get( array( $site ) );
chk( 'the visitor gets the last copy at once: no request to LeagueApps', array( $GLOBALS['calls'], $names( $r ) ), array( 0, array( 'Fall League' ) ) );
chk( 'it is not flagged as an outage (no warning for editors)', array( $r['stale'], $r['errors'], $r['unavailable'] ), array( false, array(), false ) );
chk( 'one background refresh is queued, by site id only (no key in the cron table)', $GLOBALS['cron'], array( array( 'ds_programs_refresh', array( array( '47635' ) ) ) ) );
DS_Programs_Data::get( array( $site ) ); DS_Programs_Data::get( array( $site ) );
chk( 'more visitors before it runs: still one job, still no request', array( count( $GLOBALS['cron'] ), $GLOBALS['calls'] ), array( 1, 0 ) );

echo "the background job runs\n";
DS_Programs_Data::hooks(); DS_Programs_Data::hooks();
chk( 'the handler is registered once', count( $GLOBALS['hooks']['ds_programs_refresh'] ), 1 );
DS_Programs_Data::refresh( array( '47635' ) ); $GLOBALS['cron'] = array();
chk( 'it asks LeagueApps once', $GLOBALS['calls'], 1 );
$r = DS_Programs_Data::get( array( $site ) );
chk( 'the next visitor sees the new list, from cache', array( $GLOBALS['calls'], $names( $r ) ), array( 1, array( 'Fall League', 'Winter League' ) ) );
chk( 'the fetch is logged as a background refresh', DS_Programs_Data::ledger()[0]['why'], 'refresh' );
DS_Programs_Data::refresh( array( '47635' ) );
chk( 'a second job while the copy is fresh does nothing', $GLOBALS['calls'], 1 );
chk( 'a job for a site no longer in the settings does nothing', array( DS_Programs_Data::refresh( array( '99999' ) ), $GLOBALS['calls'] ), array( null, 1 ) );

echo "limits\n";
delete_transient( $key ); $GLOBALS['calls'] = 0; $GLOBALS['cron'] = array();
set_transient( 'ds_programs_flushed', time(), 60 );
DS_Programs_Data::get( array( $site ) );
chk( 'after a manual refresh the request fetches: the person asked for new data', array( $GLOBALS['calls'], $GLOBALS['cron'] ), array( 1, array() ) );

delete_transient( $key ); $GLOBALS['calls'] = 0;
$GLOBALS['opts'][ $good ]['fetched'] = time() - 2 * 3600;
DS_Programs_Data::get( array( $site ) );
chk( 'a copy more than an hour old is not shown unchecked: that request fetches', array( $GLOBALS['calls'], $GLOBALS['cron'] ), array( 1, array() ) );

delete_transient( $key ); $GLOBALS['calls'] = 0;
DS_Programs_Data::get( array( $site ) );
chk( 'queued once more', array( $GLOBALS['calls'], count( $GLOBALS['cron'] ) ), array( 0, 1 ) );
set_transient( $key . '_queued', time() - 180, 3600 ); // three minutes on, the job has not run
DS_Programs_Data::get( array( $site ) );
chk( 'WP-Cron not delivering: the request fetches, as every expiry did before', $GLOBALS['calls'], 1 );
chk( 'and the marker is cleared', get_transient( $key . '_queued' ), false );

echo "LeagueApps down\n";
delete_transient( $key ); $GLOBALS['cron'] = array(); $GLOBALS['calls'] = 0;
$GLOBALS['http'] = array( array( 'code' => 500, 'body' => '', 'headers' => array() ) );
$r = DS_Programs_Data::get( array( $site ) );
chk( 'visitors still get the last list while the job is queued', array( $GLOBALS['calls'], count( $r['programs'] ) ), array( 0, 2 ) );
DS_Programs_Data::refresh( array( '47635' ) ); $GLOBALS['cron'] = array();
$r = DS_Programs_Data::get( array( $site ) );
chk( 'after the failed refresh: last list kept, now flagged for editors, not "unavailable"', array( count( $r['programs'] ), $r['stale'], $r['unavailable'] ), array( 2, true, false ) );
$calls = $GLOBALS['calls'];
DS_Programs_Data::get( array( $site ) ); DS_Programs_Data::refresh( array( '47635' ) );
chk( 'during the back-off neither a visitor nor a job calls LeagueApps', $GLOBALS['calls'] - $calls, 0 );
chk( 'and no job is queued while backing off', $GLOBALS['cron'], array() );

$GLOBALS['tr'] = array(); unset( $GLOBALS['opts'][ $good ] );
$r = DS_Programs_Data::get( array( $site ) );
chk( 'down with NO copy: the result says unavailable (not "no programs")', array( $r['programs'], $r['unavailable'], (bool) $r['errors'] ), array( array(), true, true ) );
$GLOBALS['tr'] = array(); $GLOBALS['http'] = array( $ok( array() ) );
$r = DS_Programs_Data::get( array( $site ) );
chk( 'a club that really has nothing listed is NOT "unavailable"', array( $r['programs'], $r['unavailable'] ), array( array(), false ) );

echo "old copies\n";
$GLOBALS['tr'] = array(); unset( $GLOBALS['opts'][ $good ] ); $GLOBALS['calls'] = 0; $GLOBALS['cron'] = array();
set_transient( $key . '_stale', array( 'rows' => array( $prog( 7, 'Legacy' ) ), 'fetched' => time() - 600 ), 86400 );
$r = DS_Programs_Data::get( array( $site ) );
chk( 'a site that just updated: the pre-audit 7-day transient is served the same way', array( $GLOBALS['calls'], count( $r['programs'] ), count( $GLOBALS['cron'] ) ), array( 0, 1, 1 ) );
$GLOBALS['tr'] = array(); $GLOBALS['cron'] = array();
$GLOBALS['opts'][ $good ] = array( 'rows' => array( $prog( 7, 'Ancient' ) ), 'fetched' => time() - 8 * 86400 );
$GLOBALS['http'] = array( array( 'code' => 500, 'body' => '', 'headers' => array() ) );
$r = DS_Programs_Data::get( array( $site ) );
chk( 'a copy older than 7 days is not shown at all', array( $r['programs'], $r['unavailable'] ), array( array(), true ) );

echo "a caller that is itself a refresh (the Calendar rebuilding its copy)\n";
$GLOBALS['tr'] = array(); $GLOBALS['cron'] = array(); $GLOBALS['calls'] = 0;
$GLOBALS['opts'][ $good ] = array( 'rows' => array( $prog( 1, 'Old list' ) ), 'fetched' => time() - 700 );
$GLOBALS['http'] = array( $ok( array( $prog( 1, 'New list' ) ) ) );
$r = DS_Programs_Data::raw( array( $site ), true );
chk( 'gets the current list now, and queues nothing', array( $GLOBALS['calls'], $r['rows'][0]['name'], $GLOBALS['cron'] ), array( 1, 'New list', array() ) );

echo "the block\n";
$table = file_get_contents( dirname( __DIR__ ) . '/modules/ds-programs/includes/table.php' );
$mod   = file_get_contents( dirname( __DIR__ ) . '/modules/ds-programs/ds-programs.php' );
chk( 'the block has its own message for it, editable', (bool) preg_match( "/'unavailable_text'\s*=> array\( 'type' => 'text'/", $mod ) && false !== strpos( $table, 'unavailable_text' ), true );
chk( 'the page is marked not to be cached while it says so', (bool) preg_match( "/feed_unavailable \) \{.*?DONOTCACHEPAGE.*?nocache_headers\(\);/s", $mod ), true );
foreach ( array( 'class-ds-programs.php', 'class-ds-la-calendar.php', 'class-ds-la-roster.php' ) as $f ) {
	chk( $f . ' registers the refresh handler', false !== strpos( file_get_contents( dirname( __DIR__ ) . '/features/' . $f ), 'DS_Programs_Data::hooks();' ), true );
}

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
