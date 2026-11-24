<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: the Gender value on adult programs. Stub-style, no WordPress:
 *
 *   php tests/programs-gender-labels-test.php
 *
 * Why (districtsoccer.org, 2026-11-17): every program there is ADULT, yet the Gender value read "Boys" on
 * the men's leagues and "Girls" on the women's (35 of 87 rows), because MALE / FEMALE were always worded
 * for youth. Adult programs now read Men / Women; youth programs, and a program with no mode, are unchanged.
 * The Gender codes the Programs tab scope matches on (`genders`) do not change.
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

$future = ( time() + 50 * 86400 ) * 1000;
$p = function ( $id, $gender, $mode, $master = 0, $extra = array() ) use ( $future ) {
	$row = array( 'programId' => $id, 'name' => 'Program ' . $id, 'type' => 'LEAGUE', 'state' => 'UPCOMING', 'sport' => 'Soccer', 'visibility' => 'Public',
		'gender' => $gender, 'masterProgramId' => $master, 'isMaster' => false, 'startTime' => $future + $id * 1000, 'endTime' => $future + 86400000,
		'registrationStatus' => 'OPEN', 'programUrlHtml' => '//x.leagueapps.com/leagues/soccer/' . $id );
	if ( null !== $mode ) { $row['mode'] = $mode; }
	return array_merge( $row, $extra );
};
$feed = array(
	$p( 1, 'MALE', 'ADULT' ),            // District Soccer: "Sunday 9s - Men's+ D2"
	$p( 2, 'FEMALE', 'ADULT' ),          // "Thursday 7s - Women's+ D1"
	$p( 3, 'CO_ED', 'ADULT' ),
	$p( 4, 'ANY', 'ADULT' ),
	$p( 5, 'MALE', 'YOUTH' ),
	$p( 6, 'FEMALE', 'YOUTH' ),
	$p( 7, 'MALE', null ),               // no mode at all: worded as before
	$p( 8, 'male', 'adult' ),            // case
	$p( 9, 'NONBINARY', 'ADULT' ),       // a code nobody has mapped yet
	$p( 10, '', 'ADULT' ),               // no gender: stays blank
	$p( 20, 'ANY', 'ADULT', 0, array( 'isMaster' => true ) ),  // an adult league with women's divisions
	$p( 21, 'FEMALE', null, 20 ),        // division with no mode of its own: takes the league's
	$p( 22, 'FEMALE', '', 20 ),
);
$site = array( 'site_id' => '2457', 'api_key' => 'k', 'label' => 'District Sports' );
$get = function ( $grouping ) use ( $feed, $site ) {
	$GLOBALS['tr'] = array();
	$GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( $feed ), 'headers' => array() ) );
	$by = array();
	foreach ( DS_Programs_Data::get( array( $site ), array(), array(), $grouping )['programs'] as $r ) { $by[ (int) $r['programId'] ] = $r; }
	return $by;
};
$kids = $get( 'children' ); $both = $get( 'both' );

echo "adult programs\n";
chk( 'MALE reads Men (was Boys)', $kids[1]['gender'], 'Men' );
chk( 'FEMALE reads Women (was Girls)', $kids[2]['gender'], 'Women' );
chk( 'CO_ED reads Coed', $kids[3]['gender'], 'Coed' );
chk( 'ANY reads Coed', $kids[4]['gender'], 'Coed' );
chk( 'lower-case codes and mode still map', $kids[8]['gender'], 'Men' );
chk( 'a division with no mode takes its league\'s: Women', array( $kids[21]['gender'], $kids[22]['gender'] ), array( 'Women', 'Women' ) );
chk( 'a league listed over its women\'s divisions reads Women', $both[20]['gender'], 'Women' );

echo "youth and unknown\n";
chk( 'youth MALE still reads Boys', $kids[5]['gender'], 'Boys' );
chk( 'youth FEMALE still reads Girls', $kids[6]['gender'], 'Girls' );
chk( 'no mode at all: worded as before', $kids[7]['gender'], 'Boys' );
chk( 'an unmapped code shows in words, never blank', $kids[9]['gender'], 'Nonbinary' );
chk( 'no gender stays blank', $kids[10]['gender'], '' );

echo "the scope codes do not change\n";
chk( 'Men row matches the MALE scope', $kids[1]['genders'], array( 'MALE' ) );
chk( 'Women row matches the FEMALE scope', $kids[2]['genders'], array( 'FEMALE' ) );
chk( 'Coed rows match the ANY scope', array( $kids[3]['genders'], $kids[4]['genders'] ), array( array( 'ANY' ), array( 'ANY' ) ) );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
