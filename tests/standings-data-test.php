<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * LeagueApps Standings: the table worked out from schedule results, and the cache / request rules.
 * Stub-style, no WordPress needed:
 *
 *   php tests/standings-data-test.php
 *
 * The table half replays five real divisions (tests/fixtures/standings-bigcat-2026-11.json) and expects the
 * LeagueApps standings page, value for value and in its order. The 4th Grade has a three-way tie whose
 * head-to-head results go round in a circle; LeagueApps orders it by placing the team the next rule separates
 * and starting the rules again for the other two, and so must we.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_VERSION', '9.9.9' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts'] = array(); $GLOBALS['tr'] = array(); $GLOBALS['http'] = array(); $GLOBALS['urls'] = array();
$GLOBALS['cron'] = array(); $GLOBALS['ledger'] = array(); $GLOBALS['now_shift'] = 0;
class WP_Error { private $m; function __construct( $c = '', $m = '' ) { $this->m = $m; } function get_error_message() { return $this->m; } }
function is_wp_error( $e ) { return $e instanceof WP_Error; }
function __( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $u ) { return $u; }
function home_url( $p = '' ) { return 'https://example.org' . $p; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_transient( $k ) { return ( isset( $GLOBALS['tr'][ $k ] ) && $GLOBALS['tr'][ $k ][1] > time() ) ? $GLOBALS['tr'][ $k ][0] : false; }
function set_transient( $k, $v, $ttl ) { $GLOBALS['tr'][ $k ] = array( $v, time() + $ttl ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['tr'][ $k ] ); }
function wp_remote_get( $url, $args = array() ) { $GLOBALS['urls'][] = $url; $q = &$GLOBALS['http']; return count( $q ) > 1 ? array_shift( $q ) : $q[0]; }
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function wp_remote_retrieve_header( $r, $h ) { return $r['headers'][ strtolower( $h ) ] ?? ''; }
function wp_next_scheduled( $h, $a = array() ) { return isset( $GLOBALS['cron'][ $h . json_encode( $a ) ] ) ? 1 : false; }
function wp_schedule_single_event( $t, $h, $a = array() ) { $GLOBALS['cron'][ $h . json_encode( $a ) ] = $t; return true; }
function has_action() { return false; }
function add_action() {}

// The Programs feed, as far as standings use it.
class DS_Programs_Data {
	const API_BASE = 'https://public.leagueapps.io/v1/sites/';
	static function configured_sites() { return array( array( 'site_id' => '51914', 'api_key' => 'k123', 'label' => 'Demo' ) ); }
	static function log_fetch( array $e ) { $GLOBALS['ledger'][] = $e; }
	static function raw( $sites ) { return array( 'rows' => $GLOBALS['rows'] ?? array() ); }
}

require dirname( __DIR__ ) . '/includes/class-ds-standings-data.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-84s %s\n", $l, $ok ? 'ok' : "FAIL\n      got:  " . json_encode( $g ) . "\n      want: " . json_encode( $w ) ); }

/* ------------------------------------------------------------------ the table */
echo "the table matches LeagueApps\n";
$fx = json_decode( file_get_contents( __DIR__ . '/fixtures/standings-bigcat-2026-11.json' ), true );
$teams = 0;
foreach ( $fx['divisions'] as $d ) {
	$rows = DS_Standings_Data::table( DS_Standings_Data::slim( $d['items'] ) );
	$got  = array();
	foreach ( $rows as $r ) {
		$got[] = array( $r['team'], $r['gp'], $r['w'], $r['l'], $r['t'], (float) $r['pct'], $r['ps'], $r['psa'], $r['psd'], $r['dnp'], (float) $r['pf'], (float) $r['pa'], (float) $r['pm'] );
	}
	$want = array();
	foreach ( $d['expected'] as $e ) {
		$want[] = array( $e[0], (int) $e[1], (int) $e[2], (int) $e[3], (int) $e[4], (float) $e[5], (int) $e[6], (int) $e[7], (int) $e[8], (int) $e[9], (float) $e[10], (float) $e[11], (float) $e[12] );
	}
	$teams += count( $want );
	chk( $d['name'] . ': every value and the order, ' . count( $want ) . ' teams', $got, $want );
}
chk( 'five divisions, 47 teams', array( count( $fx['divisions'] ), $teams ), array( 5, 47 ) );

$four = DS_Standings_Data::table( DS_Standings_Data::slim( $fx['divisions'][0]['items'] ) );
$last3 = array_slice( array_column( $four, 'team' ), -3 );
chk( 'circular three-way tie: placed one at a time, rules restarted (4th Grade bottom three)', $last3, array_slice( array_column( $fx['divisions'][0]['expected'], 0 ), -3 ) );
$psd_only = DS_Standings_Data::table( DS_Standings_Data::slim( $fx['divisions'][0]['items'] ), array( 'breakers' => array( 'psd', 'psa' ) ) );
chk( 'without head to head the same tie goes by point differential', array_slice( array_column( $psd_only, 'psd' ), -3 ), array( -40, -55, -65 ) );

echo "rules an editor can set\n";
$g = function ( $a, $b, $as, $bs, $state = 'PLAYED_REGULAR_TIME' ) {
	$ra = $as > $bs ? 'WIN' : ( $as < $bs ? 'LOSS' : 'TIE' );
	$rb = $as < $bs ? 'WIN' : ( $as > $bs ? 'LOSS' : 'TIE' );
	return array( 'scheduleItemType' => 'STRUCTURED_GAME', 'state' => $state, 'startTime' => 1794772800000, 'team1Id' => $a, 'team1' => "T$a", 'team1Score' => $as, 'team1Result' => $ra, 'team2Id' => $b, 'team2' => "T$b", 'team2Score' => $bs, 'team2Result' => $rb );
};
$div = DS_Standings_Data::slim( array( $g( 1, 2, 3, 1 ), $g( 1, 3, 2, 2 ), $g( 2, 3, 0, 1, 'FORFEIT' ),
	array( 'scheduleItemType' => 'STRUCTURED_GAME', 'state' => 'SCHEDULED', 'startTime' => 1795363200000, 'team1Id' => 4, 'team1' => 'T4', 'team2Id' => 1, 'team2' => 'T1', 'team1Result' => null, 'team2Result' => null ),
	array( 'scheduleItemType' => 'EVENT', 'teamName' => 'T1' ) ) );
$t = DS_Standings_Data::table( $div, array( 'points' => array( 3, 1, 0 ), 'forfeit' => 2 ) );
$by = array_column( $t, null, 'team' );
chk( 'a tie counts half a win in PCT and the tie points in PF (3-1-0)', array( $by['T1']['t'], $by['T1']['pct'], $by['T1']['pf'] ), array( 1, 75.0, 4.0 ) );
chk( 'a forfeit loss counts in DNP and takes the forfeit points off (PA, +/-)', array( $by['T2']['dnp'], $by['T2']['pa'], $by['T2']['pm'] ), array( 1, 2.0, -2.0 ) );
chk( 'a team with only scheduled games still has a row, last', array( end( $t )['team'], end( $t )['gp'] ), array( 'T4', 0 ) );
chk( 'scheduled games counted, next date kept; events ignored', array( $div['left'], $div['next'], count( $div['games'] ) ), array( 1, 1795363200000, 3 ) );
chk( 'results date is the last played game', $div['last'], 1794772800000 );

/* ------------------------------------------------------------------ leagues */
echo "leagues from the program list\n";
$GLOBALS['rows'] = array(
	array( '_site' => '51914', 'programId' => 10, 'name' => 'Winter League', 'isMaster' => true, 'masterProgramId' => 0, 'standingsUrlHtml' => '//club.leagueapps.com/leagues/10/standings' ),
	array( '_site' => '51914', 'programId' => 12, 'name' => '5th Grade', 'masterProgramId' => 10, 'sortOrder' => 2, 'standingsUrlHtml' => '//club.leagueapps.com/leagues/12/standings' ),
	array( '_site' => '51914', 'programId' => 11, 'name' => '4th Grade', 'masterProgramId' => 10, 'sortOrder' => 1, 'standingsUrlHtml' => '//club.leagueapps.com/leagues/11/standings' ),
	array( '_site' => '51914', 'programId' => 20, 'name' => 'Adult Rec', 'masterProgramId' => 0, 'standingsUrlHtml' => '//club.leagueapps.com/leagues/20/standings' ),
	array( '_site' => '51914', 'programId' => 30, 'name' => 'Summer Camp', 'masterProgramId' => 0 ),
);
$L = DS_Standings_Data::leagues();
chk( 'a league with divisions lists them in LeagueApps order; a single league is its own division; a camp is not listed', array( array_keys( $L ), array_map( 'strval', array_keys( $L['51914:10']['divisions'] ) ), array_map( 'strval', array_keys( $L['51914:20']['divisions'] ) ) ), array( array( '51914:20', '51914:10' ), array( '11', '12' ), array( '20' ) ) );
chk( 'LeagueApps links become https', $L['51914:10']['divisions']['11']['url'], 'https://club.leagueapps.com/leagues/11/standings' );
chk( 'anything that is not a LeagueApps-style link is dropped', DS_Standings_Data::link( 'javascript:alert(1)' ), '' );

/* ------------------------------------------------------------------ requests */
echo "requests to LeagueApps\n";
$body = json_encode( array( 'games' => $fx['divisions'][2]['items'] ) );
$ok   = array( 'code' => 200, 'body' => $body, 'headers' => array() );
$reset = function ( $script ) { $GLOBALS['http'] = $script; $GLOBALS['urls'] = array(); $GLOBALS['ledger'] = array(); };

$GLOBALS['opts'] = array(); $GLOBALS['tr'] = array(); $GLOBALS['cron'] = array();
$reset( array( $ok ) );
$r = DS_Standings_Data::get( '51914', array( '5115698' ) );
chk( 'first view: one request, the division is kept, nothing stale', array( count( $GLOBALS['urls'] ), count( $r['divisions']['5115698']['games'] ), $r['stale'] ), array( 1, 32, false ) );
chk( 'the key is sent but never ends up in the ledger', array( false !== strpos( $GLOBALS['urls'][0], 'x-api-key=k123' ), false !== strpos( json_encode( $GLOBALS['ledger'] ), 'k123' ) ), array( true, false ) );
chk( 'the ledger line says standings and is not counted as a listing fetch', $GLOBALS['ledger'][0]['why'], 'standings page' );

$reset( array( $ok ) );
DS_Standings_Data::get( '51914', array( '5115698' ) );
chk( 'within the hour: no request', count( $GLOBALS['urls'] ), 0 );

$GLOBALS['opts']['ds_standings_good_51914_5115698']['fetched'] = time() - 2 * HOUR_IN_SECONDS;
$reset( array( $ok ) );
$r = DS_Standings_Data::get( '51914', array( '5115698' ) );
chk( 'two hours old: served at once, one background job queued, no request', array( count( $GLOBALS['urls'] ), count( $GLOBALS['cron'] ), $r['stale'], count( $r['divisions']['5115698']['games'] ) ), array( 0, 1, true, 32 ) );
DS_Standings_Data::get( '51914', array( '5115698' ) );
chk( 'a second view does not queue a second job', count( $GLOBALS['cron'] ), 1 );
DS_Standings_Data::refresh( '51914', array( '5115698' ) );
chk( 'the job asks LeagueApps once and the copy is fresh again', array( count( $GLOBALS['urls'] ), time() - $GLOBALS['opts']['ds_standings_good_51914_5115698']['fetched'] < 5, $GLOBALS['ledger'][0]['why'] ), array( 1, true, 'standings background' ) );

$GLOBALS['opts']['ds_standings_good_51914_5115698']['fetched'] = time() - 7 * HOUR_IN_SECONDS;
$reset( array( array( 'code' => 500, 'body' => '', 'headers' => array() ) ) );
$r = DS_Standings_Data::get( '51914', array( '5115698' ) );
chk( 'over six hours old and LeagueApps fails: one request, last good games still shown', array( count( $GLOBALS['urls'] ), count( $r['divisions']['5115698']['games'] ), count( $r['errors'] ) ), array( 1, 32, 1 ) );
$reset( array( $ok ) );
DS_Standings_Data::get( '51914', array( '5115698' ) );
chk( 'after a failure the division is left alone for five minutes', count( $GLOBALS['urls'] ), 0 );

$GLOBALS['opts'] = array(); $GLOBALS['tr'] = array(); $GLOBALS['cron'] = array();
$reset( array( array( 'code' => 404, 'body' => '{"error":"Schedule Not Found"}', 'headers' => array() ) ) );
$r = DS_Standings_Data::get( '51914', array( '5115695' ) );
chk( 'a program without a schedule (404) is remembered as missing, not an error', array( ! empty( $r['divisions']['5115695']['missing'] ), $r['errors'] ), array( true, array() ) );
$GLOBALS['opts']['ds_standings_good_51914_5115695']['fetched'] = time() - 3 * HOUR_IN_SECONDS;
$reset( array( $ok ) );
DS_Standings_Data::get( '51914', array( '5115695' ) );
chk( '... and not asked again for six hours', array( count( $GLOBALS['urls'] ), count( $GLOBALS['cron'] ) ), array( 0, 0 ) );

$GLOBALS['opts'] = array(); $GLOBALS['tr'] = array();
$reset( array( array( 'code' => 429, 'body' => '', 'headers' => array( 'retry-after' => '30' ) ), $ok ) );
$r = DS_Standings_Data::get( '51914', array( '5115696', '5115697', '5115698' ) );
chk( 'a 429 stops at once: one request for three divisions', count( $GLOBALS['urls'] ), 1 );
chk( '... and holds the site off at least five minutes', (int) get_transient( 'ds_standings_hold_51914' ) - time() >= 299, true );
$reset( array( $ok ) );
DS_Standings_Data::get( '51914', array( '5115696' ) );
chk( 'while held: no request', count( $GLOBALS['urls'] ), 0 );

$GLOBALS['opts'] = array(); $GLOBALS['tr'] = array();
$reset( array( $ok ) );
$r = DS_Standings_Data::get( '99999', array( '1' ) );
chk( 'a site that is not saved: no request, a message', array( count( $GLOBALS['urls'] ), count( $r['errors'] ) ), array( 0, 1 ) );
$r = DS_Standings_Data::get( '51914', range( 1, 40 ) );
chk( 'never more than 24 divisions per block', count( $GLOBALS['urls'] ) <= 24, true );

echo "\n" . ( $fails ? "$fails FAILED\n" : "all passed\n" );
exit( $fails ? 1 : 0 );
