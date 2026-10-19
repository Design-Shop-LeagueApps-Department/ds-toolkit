<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: "Only some seasons or levels" (Programs tab). Stub-style, no WordPress:
 *
 *   php tests/programs-season-level-test.php
 *
 * Why (Alipio, 2026-10-19): Core 5 Flag Football keeps one LeagueApps site for every location and
 * types the location into Season ("Anthem Fall", "N. Peoria Paloma Winter") and the division into
 * Experience Level ("Girls Flag Football"). Their hosted widget "Anthem Friday Nights" shows the
 * Anthem seasons only; the module could only scope by site, type, sport or location text.
 * The values below are Core 5's real ones (site 47635, 2026-10-19). The module helpers are read out
 * of modules/ds-programs/ds-programs.php itself, so this tests the shipped code.
 */
define('ABSPATH', '/tmp/fake-wp/');
define('MINUTE_IN_SECONDS', 60); define('DAY_IN_SECONDS', 86400);

$GLOBALS['tr'] = array();      // transient store: key => array(value, ttl)
$GLOBALS['http'] = array();    // scripted responses, consumed in order (last one repeats)
$GLOBALS['calls'] = 0;         // wp_remote_get invocations
$GLOBALS['last_args'] = null;
$GLOBALS['cache_flushed'] = 0;
$GLOBALS['opts'] = array();

function get_transient($k){ return array_key_exists($k,$GLOBALS['tr']) ? $GLOBALS['tr'][$k][0] : false; }
function set_transient($k,$v,$ttl=0){ $GLOBALS['tr'][$k]=array($v,$ttl); return true; }
function delete_transient($k){ unset($GLOBALS['tr'][$k]); return true; }
function ttl_of($k){ return $GLOBALS['tr'][$k][1] ?? null; }
function get_option($k,$d=''){ return array_key_exists($k,$GLOBALS['opts'])?$GLOBALS['opts'][$k]:$d; }
function update_option($k,$v,$autoload=null){ $GLOBALS['opts'][$k]=$v; $GLOBALS['autoload'][$k]=$autoload; return true; }
function do_action($h,$a=null){ $GLOBALS['actions'][$h][]=$a; }
function wp_cache_flush(){ $GLOBALS['cache_flushed']++; }
function is_wp_error($e){ return $e instanceof WP_Error; }
class WP_Error {
  private $c,$m,$d;
  function __construct($c='',$m='',$d=null){ $this->c=$c; $this->m=$m; $this->d=$d; }
  function get_error_message(){ return $this->m; }
  function get_error_data(){ return $this->d; }
}
function wp_remote_get($url,$args=array()){
  $GLOBALS['calls']++; $GLOBALS['calls_total']=($GLOBALS['calls_total']??0)+1; $GLOBALS['last_args']=$args;
  $q=&$GLOBALS['http'];
  $r = count($q)>1 ? array_shift($q) : ($q[0] ?? array('code'=>200,'body'=>'[]','headers'=>array()));
  return $r;
}
function wp_remote_retrieve_response_code($r){ return $r['code'] ?? 0; }
function wp_remote_retrieve_body($r){ return $r['body'] ?? ''; }
function wp_remote_retrieve_header($r,$h){ return $r['headers'][strtolower($h)] ?? ''; }
function wp_json_encode($v){ return json_encode($v); }
function wp_list_pluck($l,$f){ $o=array(); foreach($l as $i){ $o[]=is_array($i)?$i[$f]:$i->$f; } return $o; }
function sanitize_text_field($s){ return trim(strip_tags($s)); }
function esc_url_raw($u){ return $u; }
function __($s,$d=''){ return $s; }
function home_url($p=''){ return 'https://example.org'.$p; }
function date_i18n($f,$t){ return date($f,$t); }
function wp_strip_all_tags($s){ return strip_tags($s); }
class FakeWpdb { public $options='wp_options'; public $queries=array(); function query($q){ $this->queries[]=$q; return 0; } }
$wpdb = new FakeWpdb();

require __DIR__ . '/../includes/class-ds-programs-data.php';

$fails = 0;
function chk($l,$g,$w){ global $fails; $ok=($g===$w); if(!$ok)$fails++; printf("  %-72s %s\n",$l,$ok?'ok':"FAIL got=".var_export($g,true)); }

$msrc = file_get_contents( dirname( __DIR__ ) . '/modules/ds-programs/ds-programs.php' );
$body = '';
foreach ( array( 'season_level_scope', 'in_season_level', 'type_list_plain' ) as $fn ) {
	if ( ! preg_match( '/\tpublic static function ' . $fn . '\(.*?\n\t\}\n/s', $msrc, $m ) ) { echo "  FAIL could not find $fn()\n"; exit( 1 ); }
	$body .= $m[0];
}
eval( 'class DS_SL_Under_Test {' . str_replace( 'self::', 'DS_SL_Under_Test::', $body ) . '}' );

$future = ( time() + 30 * 86400 ) * 1000;
$p = function ( $id, $name, $season, $level, $master = 0 ) use ( $future ) {
	return array( 'programId' => $id, 'name' => $name, 'type' => 'LEAGUE', 'mode' => 'YOUTH', 'state' => 'UPCOMING', 'sport' => 'Flag Football', 'visibility' => 'Public',
		'season' => $season, 'experienceLevel' => $level, 'masterProgramId' => $master, 'isMaster' => false, 'startTime' => $future, 'endTime' => $future + 86400000,
		'registerUrlHtml' => 'https://core5.leagueapps.com/leagues/' . $id );
};
$rows = array(
	$p( 1, 'ANTHEM Flag Football- FALL 2026 Friday Night', 'Anthem Fall', 'Flag Football (Co-ed)' ),
	$p( 2, 'Anthem ALL GIRLS FLAG FOOTBALL - FALL 2026', 'Anthem Fall', 'Girls Flag Football' ),
	$p( 3, '13U Girls Flag', 'Anthem Winter', 'Girls Flag Football' ),
	$p( 4, 'PEORIA Flag Football (Paloma Park)- WINTER 2026', 'N. Peoria Paloma Winter', 'Flag Football (Co-ed)' ),
	$p( 5, '10u Open Division', 'Cave Creek / N. Scottsdale Fall', 'Flag Football (Co-ed)' ),
	$p( 6, 'Anthem drop-in', 'Anthem Fall', '' ),
	array_merge( $p( 7, 'PALOMA Main', 'N. Peoria Paloma Fall', 'Flag Football (Co-ed)' ), array( 'isMaster' => true ) ),
	$p( 8, '8U Open Division', '', '', 7 ), // a division that left both fields to its parent
);
$GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( $rows ), 'headers' => array() ) );
$site = array( 'site_id' => '47635', 'api_key' => 'k', 'label' => 'Core 5 Flag Football' );

echo "remembered values (the builder pickers)\n";
$out = DS_Programs_Data::get( array( $site ) );
chk( 'seasons learned from the feed, sorted', DS_Programs_Data::known_values( 'season' ), array( 'Anthem Fall', 'Anthem Winter', 'Cave Creek / N. Scottsdale Fall', 'N. Peoria Paloma Fall', 'N. Peoria Paloma Winter' ) );
chk( 'levels learned (empty values left out)', DS_Programs_Data::known_values( 'level' ), array( 'Flag Football (Co-ed)', 'Girls Flag Football' ) );
set_transient( 'ds_programs_seasons', array( 'Fall' ), 3600 ); // another LeagueApps site (Inspire) seen before
delete_transient( 'ds_programs_' . md5( json_encode( array( '47635' ) ) ) );
foreach ( array_keys( $GLOBALS['tr'] ) as $k ) { if ( 0 === strpos( $k, 'ds_programs_' ) && false === strpos( $k, 'seasons' ) && false === strpos( $k, 'levels' ) ) { delete_transient( $k ); } }
DS_Programs_Data::get( array( $site ) );
chk( 'values from two sites are added together, not replaced', in_array( 'Fall', DS_Programs_Data::known_values( 'season' ), true ) && in_array( 'Anthem Fall', DS_Programs_Data::known_values( 'season' ), true ), true );
delete_transient( 'ds_programs_seasons' ); delete_transient( 'ds_programs_levels' ); $calls = $GLOBALS['calls'];
DS_Programs_Data::get( array( $site ) );
chk( 'a copy cached before this version still teaches the pickers, no new request', array( count( DS_Programs_Data::known_values( 'season' ) ), $GLOBALS['calls'] - $calls ), array( 5, 0 ) );

echo "rows carry LeagueApps' own values\n";
$by = array(); foreach ( $out['programs'] as $r ) { $by[ $r['programId'] ] = $r; }
chk( 'program row: seasonRaw / levelRaw as typed', array( $by['2']['seasonRaw'] ?? null, $by['2']['levelRaw'] ?? null ), array( 'Anthem Fall', 'Girls Flag Football' ) );
chk( 'a division with blank fields inherits its parent\'s', array( $by['8']['seasonRaw'] ?? null, $by['8']['levelRaw'] ?? null ), array( 'N. Peoria Paloma Fall', 'Flag Football (Co-ed)' ) );
$mapped = DS_Programs_Data::get( array( $site ), array( 'season' => 'name' ) )['programs'];
$m2 = null; foreach ( $mapped as $r ) { if ( '2' === (string) $r['programId'] ) { $m2 = $r; } }
chk( 'a field mapping rewrites the SHOWN season only', array( $m2['season'], $m2['seasonRaw'] ), array( 'Anthem ALL GIRLS FLAG FOOTBALL - FALL 2026', 'Anthem Fall' ) );

echo "the Programs tab scope\n";
$scope = function ( $settings ) use ( $out ) {
	list( $se, $le ) = DS_SL_Under_Test::season_level_scope( (object) $settings );
	$ids = array(); foreach ( $out['programs'] as $r ) { if ( DS_SL_Under_Test::in_season_level( $r, $se, $le ) ) { $ids[] = (int) $r['programId']; } }
	sort( $ids ); return $ids;
};
$all = $scope( array() ); 
chk( 'off (default): every program', count( $all ), count( $out['programs'] ) );
chk( 'off with picks saved: picks ignored (switching off shows all again)', $scope( array( 'season_level' => 'no', 'seasons_pick' => array( 'Anthem Fall' ) ) ), $all );
chk( 'Anthem Friday Nights: Anthem Fall + Anthem Winter', $scope( array( 'season_level' => 'yes', 'seasons_pick' => array( 'Anthem Fall', 'Anthem Winter' ) ) ), array( 1, 2, 3, 6 ) );
chk( 'girls page: level Girls Flag Football', $scope( array( 'season_level' => 'yes', 'levels_pick' => array( 'Girls Flag Football' ) ) ), array( 2, 3 ) );
chk( 'both: Anthem seasons AND girls', $scope( array( 'season_level' => 'yes', 'seasons_pick' => array( 'Anthem Fall', 'Anthem Winter' ), 'levels_pick' => array( 'Girls Flag Football' ) ) ), array( 2, 3 ) );
chk( 'a program with no level is not in a picked level', in_array( 6, $scope( array( 'season_level' => 'yes', 'levels_pick' => array( 'Flag Football (Co-ed)' ) ) ), true ), false );
chk( 'a division follows its parent\'s season (listed as the sub-program)', $scope( array( 'season_level' => 'yes', 'seasons_pick' => array( 'N. Peoria Paloma Fall' ) ) ), array( 8 ) );
chk( 'yes with nothing picked = every program', $scope( array( 'season_level' => 'yes' ) ), $all );
chk( 'a comma string (BB button-group shape) works too', $scope( array( 'season_level' => 'yes', 'seasons_pick' => 'Anthem Winter' ) ), array( 3 ) );
chk( 'exact match, so "Anthem Fall" does not catch "Anthem Fall 2"-style lookalikes', DS_SL_Under_Test::in_season_level( array( 'seasonRaw' => 'Anthem Fall 2' ), array( 'Anthem Fall' ), array() ), false );
chk( 'a row from an old cache (no seasonRaw) falls back to the shown season', DS_SL_Under_Test::in_season_level( array( 'season' => 'Anthem Fall' ), array( 'Anthem Fall' ), array() ), true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
