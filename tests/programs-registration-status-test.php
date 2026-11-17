<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: the Registration Status column. Stub-style, no WordPress:
 *
 *   php tests/programs-registration-status-test.php
 *
 * Why (districtsoccer.org, 2026-11-17, the day its winter leagues opened): the Registration Status
 * column showed "Open" on some rows and nothing on the rest. The feed was not blank there: LeagueApps
 * sent LIMITED on 24 of the site's 121 programs and ONLY_TEAM_PLAYERS_AND_FA on 43, and the module
 * only knew OPEN / OPENS_SOON / SOLD_OUT / CLOSED / WAITLIST / CANCELED, so every other code became an
 * empty cell. The codes and wording below are LeagueApps' own: the hosted listing widget
 * (widgets.leagueapps.com) labels the same field with this list.
 */
define('ABSPATH', '/tmp/fake-wp/');
define('MINUTE_IN_SECONDS', 60); define('DAY_IN_SECONDS', 86400);

$GLOBALS['tr'] = array();
$GLOBALS['http'] = array();
$GLOBALS['calls'] = 0;
$GLOBALS['opts'] = array();

function get_transient($k){ return array_key_exists($k,$GLOBALS['tr']) ? $GLOBALS['tr'][$k][0] : false; }
function set_transient($k,$v,$ttl=0){ $GLOBALS['tr'][$k]=array($v,$ttl); return true; }
function delete_transient($k){ unset($GLOBALS['tr'][$k]); return true; }
function get_option($k,$d=''){ return array_key_exists($k,$GLOBALS['opts'])?$GLOBALS['opts'][$k]:$d; }
function update_option($k,$v,$autoload=null){ $GLOBALS['opts'][$k]=$v; return true; }
function do_action($h,$a=null){}
function wp_cache_flush(){}
function is_wp_error($e){ return $e instanceof WP_Error; }
class WP_Error {
  private $c,$m,$d;
  function __construct($c='',$m='',$d=null){ $this->c=$c; $this->m=$m; $this->d=$d; }
  function get_error_message(){ return $this->m; }
  function get_error_data(){ return $this->d; }
}
function wp_remote_get($url,$args=array()){
  $GLOBALS['calls']++;
  $q=&$GLOBALS['http'];
  return count($q)>1 ? array_shift($q) : ($q[0] ?? array('code'=>200,'body'=>'[]','headers'=>array()));
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
class FakeWpdb { public $options='wp_options'; function query($q){ return 0; } }
$wpdb = new FakeWpdb();

require __DIR__ . '/../includes/class-ds-programs-data.php';

$fails = 0;
function chk($l,$g,$w){ global $fails; $ok=($g===$w); if(!$ok)$fails++; printf("  %-78s %s\n",$l,$ok?'ok':"FAIL got=".var_export($g,true)); }

// District Soccer's 2027 Winter 7s, as the feed sent them (status, team spots left / cap).
$future = ( time() + 50 * 86400 ) * 1000;
$id     = 100;
$p = function ( $name, $status, $extra = array() ) use ( $future, &$id ) {
	$id++;
	$row = array( 'programId' => $id, 'name' => $name, 'type' => 'LEAGUE', 'mode' => 'ADULT', 'state' => 'UPCOMING', 'sport' => 'Soccer (Outdoor)',
		'visibility' => 'Public', 'gender' => 'CO_ED', 'masterProgramId' => 0, 'isMaster' => false, 'startTime' => $future, 'endTime' => $future + 86400000 * 60,
		'publicRegistrationTime' => ( time() - 86400 ) * 1000, 'endRegistrationTime' => $future,
		'programUrlHtml' => '//districtsoccer.leagueapps.com/leagues/soccer-(outdoor)/' . $id, 'registerUrlHtml' => '//districtsoccer.leagueapps.com/registration/init?bid=' . $id );
	if ( null !== $status ) { $row['registrationStatus'] = $status; }
	return array_merge( $row, $extra );
};
$feed = array(
	'open'      => $p( 'Monday 7s - CoEd D2', 'OPEN', array( 'remainingTeamCount' => 5, 'maxTeamCount' => 9 ) ),
	'limited'   => $p( 'Monday 7s - CoEd D3', 'LIMITED', array( 'remainingTeamCount' => 5, 'maxTeamCount' => 18 ) ),
	'limited0'  => $p( 'Saturday 7s - Women\'s+ D3', 'LIMITED', array( 'remainingTeamCount' => 0, 'maxTeamCount' => 7 ) ),
	'teamfa'    => $p( 'Thursday 7s - CoEd D3 (Bundy)', 'ONLY_TEAM_PLAYERS_AND_FA', array( 'remainingTeamCount' => 0, 'maxTeamCount' => 14 ) ),
	'soldout'   => $p( 'DCPL 1', 'SOLD_OUT', array( 'remainingTeamCount' => -2, 'maxTeamCount' => 9 ) ),
	'canceled'  => $p( 'Sunday 9s - CoEd D2 (Tubman)', 'CANCELED' ),
	'cancelled' => $p( 'Spelled with two Ls', 'CANCELLED' ),
	'regclosed' => $p( 'Widget closed code', 'REGISTRATION_CLOSED' ),
	'lower'     => $p( 'Lower-case code', 'limited' ),
	'freeag'    => $p( 'Free agents only', 'ONLY_FREE_AGENTS' ),
	'maleag'    => $p( 'Male free agents only', 'ONLY_MALE_FREE_AGENTS' ),
	'femag'     => $p( 'Female free agents only', 'ONLY_FEMALE_FREE_AGENTS' ),
	'captains'  => $p( 'Captains only', 'ONLY_TEAM_CAPTAINS' ),
	'players'   => $p( 'Team players only', 'ONLY_TEAM_PLAYERS' ),
	'waitlist'  => $p( 'Waitlist', 'WAITLIST' ),
	'soon'      => $p( 'Opens soon', 'OPENS_SOON' ),
	'newcode'   => $p( 'A code LeagueApps adds later', 'ONLY_RETURNING_PLAYERS' ),
	// No registrationStatus at all (2 of District Soccer's rows; most rows on other sites):
	// the module's derivation is unchanged.
	'blank_full'=> $p( 'No status, no spots', null, array( 'remainingTeamCount' => 0 ) ),
	'blank_url' => $p( 'No status, register link', null ),
	'blank_none'=> $p( 'No status, nothing to go on', null, array( 'registerUrlHtml' => '', 'publicRegistrationTime' => null, 'endRegistrationTime' => null ) ),
);
$GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( array_values( $feed ) ), 'headers' => array() ) );
$site = array( 'site_id' => '2457', 'api_key' => 'k', 'label' => 'District Sports' );

$rows = array();
foreach ( DS_Programs_Data::get( array( $site ) )['programs'] as $r ) { $rows[ (int) $r['programId'] ] = $r; }
$r = function ( $k ) use ( $feed, $rows ) { return $rows[ $feed[ $k ]['programId'] ] ?? array(); };

echo "the codes District Soccer sent\n";
chk( 'OPEN reads Open', $r( 'open' )['status'], 'Open' );
chk( 'LIMITED reads Limited Spots (it was blank)', $r( 'limited' )['status'], 'Limited Spots' );
chk( 'LIMITED keeps its code for the filter and sort', $r( 'limited' )['statusRaw'], 'LIMITED' );
chk( 'LIMITED is still bookable: not sold out', $r( 'limited' )['soldOut'], false );
chk( 'LIMITED with 0 team spots trusts LeagueApps, not the count', $r( 'limited0' )['status'], 'Limited Spots' );
chk( 'ONLY_TEAM_PLAYERS_AND_FA reads its LeagueApps meaning (it was blank)', $r( 'teamfa' )['status'], 'Accepting Team Players and Free Agents Only' );
chk( 'ONLY_TEAM_PLAYERS_AND_FA is not sold out: players and free agents can still sign up', $r( 'teamfa' )['soldOut'], false );
chk( 'SOLD_OUT reads Sold Out', $r( 'soldout' )['status'], 'Sold Out' );
chk( 'SOLD_OUT turns the button off', $r( 'soldout' )['soldOut'], true );
chk( 'CANCELED reads Cancelled', $r( 'canceled' )['status'], 'Cancelled' );
chk( 'CANCELED is flagged', $r( 'canceled' )['canceled'], true );

echo "the rest of LeagueApps' list\n";
chk( 'CANCELLED (two Ls) is the same as CANCELED', array( $r( 'cancelled' )['status'], $r( 'cancelled' )['canceled'] ), array( 'Cancelled', true ) );
chk( 'REGISTRATION_CLOSED is Closed, so Hide closed and the in-season rule see it', array( $r( 'regclosed' )['status'], $r( 'regclosed' )['statusRaw'] ), array( 'Closed', 'CLOSED' ) );
chk( 'a lower-case code still maps', $r( 'lower' )['status'], 'Limited Spots' );
chk( 'ONLY_FREE_AGENTS', $r( 'freeag' )['status'], 'Accepting Free Agents Only' );
chk( 'ONLY_MALE_FREE_AGENTS', $r( 'maleag' )['status'], 'Accepting Male Free Agents Only' );
chk( 'ONLY_FEMALE_FREE_AGENTS', $r( 'femag' )['status'], 'Accepting Female Free Agents Only' );
chk( 'ONLY_TEAM_CAPTAINS', $r( 'captains' )['status'], 'Accepting Team Captains Only' );
chk( 'ONLY_TEAM_PLAYERS', $r( 'players' )['status'], 'Accepting Team Players Only' );
chk( 'WAITLIST reads Accepting Waitlist, as LeagueApps words it', $r( 'waitlist' )['status'], 'Accepting Waitlist' );
chk( 'OPENS_SOON', $r( 'soon' )['status'], 'Opens Soon' );
chk( 'a code nobody has mapped yet shows in words, never a blank cell', $r( 'newcode' )['status'], 'Only returning players' );

echo "no status from LeagueApps: derived as before\n";
chk( '0 spots left = Sold Out', array( $r( 'blank_full' )['status'], $r( 'blank_full' )['soldOut'] ), array( 'Sold Out', true ) );
chk( 'a register link = Open', $r( 'blank_url' )['status'], 'Open' );
chk( 'nothing to go on stays blank (the filter never claims what it does not know)', array( $r( 'blank_none' )['status'], $r( 'blank_none' )['statusRaw'] ), array( '', '' ) );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
