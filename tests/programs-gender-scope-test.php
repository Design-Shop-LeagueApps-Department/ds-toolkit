<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: Programs tab > What to show > Gender. Stub-style, no WordPress:
 *
 *   php tests/programs-gender-scope-test.php
 *
 * Why (Alipio, 2026-11-05): the hosted registration widget can be set to one gender, so a girls
 * page lists girls programs only; the module could only get there through Experience Level, which
 * works only where the club typed the gender into it. The feed shapes below are real:
 *   - Core 5 Flag Football (site 47635, 51 programs): 18 FEMALE, 26 CO_ED (a spelling the module
 *     never mapped, so they showed no Gender at all), 7 ANY main programs over CO_ED sessions.
 *   - Salt City (2026-11-05, 267 rows): 26 main programs are ANY with FEMALE and MALE divisions; a
 *     division always carries its own Gender there, but every other field falls back to the main
 *     program when blank, so Gender does too.
 * The module helpers are read out of modules/ds-programs/ds-programs.php itself, so this tests the
 * shipped code.
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
  $GLOBALS['calls']++; $GLOBALS['last_args']=$args;
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
function chk($l,$g,$w){ global $fails; $ok=($g===$w); if(!$ok)$fails++; printf("  %-78s %s\n",$l,$ok?'ok':"FAIL got=".var_export($g,true)); }

$msrc = file_get_contents( dirname( __DIR__ ) . '/modules/ds-programs/ds-programs.php' );
$body = '';
foreach ( array( 'in_genders', 'type_list' ) as $fn ) {
	if ( ! preg_match( '/\tpublic static function ' . $fn . '\(.*?\n\t\}\n/s', $msrc, $m ) ) { echo "  FAIL could not find $fn()\n"; exit( 1 ); }
	$body .= $m[0];
}
eval( 'class DS_G_Under_Test {' . str_replace( 'self::', 'DS_G_Under_Test::', $body ) . '}' );

echo "the setting\n";
chk( 'Gender sits in What to show, right after Audience', (bool) preg_match( "/'program_mode' => array\(.*?\n\t\t\t\t\t\),\n\t\t\t\t\t'gender_scope' => array\(/s", $msrc ), true );
chk( 'its options are LeagueApps\' own codes', (bool) preg_match( "/'gender_scope' => array\(.*?'options'\s*=> array\( 'MALE' => __\( 'Boys'.*?'FEMALE' => __\( 'Girls'.*?'ANY' => __\( 'Coed'/s", $msrc ), true );
chk( 'rows() applies it', (bool) preg_match( '/\$gends = self::type_list\( \$s->gender_scope \?\? array\(\) \);.*?if \( ! self::in_genders\( \$r, \$gends \) \) \{ return false; \}/s', $msrc ), true );

$future = ( time() + 30 * 86400 ) * 1000;
$p = function ( $id, $name, $gender, $master = 0, $extra = array() ) use ( $future ) {
	return array_merge( array( 'programId' => $id, 'name' => $name, 'type' => 'LEAGUE', 'mode' => 'YOUTH', 'state' => 'UPCOMING', 'sport' => 'Flag Football', 'visibility' => 'Public',
		'gender' => $gender, 'masterProgramId' => $master, 'isMaster' => false, 'startTime' => $future + $id * 1000, 'endTime' => $future + 86400000,
		'registerUrlHtml' => 'https://core5.leagueapps.com/leagues/' . $id ), $extra );
};
$feed = array(
	$p( 1, 'Anthem ALL GIRLS FLAG FOOTBALL - FALL 2026', 'FEMALE' ),
	$p( 2, 'Drop-in', '' ),                                              // Gender never set
	$p( 3, 'Flag for TOTs', 'ANY' ),
	$p( 4, 'Boys 7v7', 'MALE' ),
	$p( 5, 'ANTHEM Flag Football- FALL 2026 Friday Night', 'CO_ED' ),    // Core 5 co-ed
	$p( 40, 'Desert Ridge Winter', 'ANY', 0, array( 'isMaster' => true ) ), // Core 5: ANY over CO_ED sessions
	$p( 41, 'Desert Ridge 8U', 'CO_ED', 40 ),
	$p( 42, 'Desert Ridge 10U', 'co_ed', 40 ),
	$p( 10, 'Fall Classic', 'ANY', 0, array( 'isMaster' => true, 'type' => 'TOURNAMENT' ) ), // Salt City: ANY over mixed divisions
	$p( 11, '12U Girls', 'FEMALE', 10 ),
	$p( 12, '12U Boys', 'MALE', 10 ),
	$p( 20, 'Girls Winter Clinic', 'FEMALE', 0, array( 'isMaster' => true, 'type' => 'CLINIC' ) ),
	$p( 21, 'Session 1', '', 20 ),                                       // blank division under a girls main program
	$p( 30, 'Open Showcase', 'ANY', 0, array( 'isMaster' => true, 'type' => 'TOURNAMENT' ) ),
	$p( 31, 'Open 14U', 'ANY', 30 ),
);
$GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( $feed ), 'headers' => array() ) );
$site = array( 'site_id' => '47635', 'api_key' => 'k', 'label' => 'Core 5 Flag Football' );

$get = function ( $grouping ) use ( $site ) {
	$by = array();
	foreach ( DS_Programs_Data::get( array( $site ), array(), array(), $grouping )['programs'] as $r ) { $by[ (int) $r['programId'] ] = $r; }
	return $by;
};
$kids = $get( 'children' ); $mast = $get( 'master' ); $both = $get( 'both' );

echo "rows carry LeagueApps' own Gender codes\n";
chk( 'a girls program', $kids[1]['genders'], array( 'FEMALE' ) );
chk( 'a co-ed program with Gender blank', $kids[2]['genders'], array( '' ) );
chk( 'a division with its own Gender', $kids[12]['genders'], array( 'MALE' ) );
chk( 'a blank division takes its main program\'s (codes and shown label)', array( $kids[21]['genders'], $kids[21]['gender'] ), array( array( 'FEMALE' ), 'Girls' ) );
chk( 'a main program covers every division\'s code', $mast[10]['genders'], array( 'FEMALE', 'MALE' ) );
chk( 'a main program\'s blank division counts as the main program\'s code', $mast[20]['genders'], array( 'FEMALE' ) );
chk( 'shown labels unchanged: Girls, blank, Coed, Boys, mixed main = Coed', array( $kids[1]['gender'], $kids[2]['gender'], $kids[3]['gender'], $kids[4]['gender'], $mast[10]['gender'] ), array( 'Girls', '', 'Coed', 'Boys', 'Coed' ) );
chk( 'CO_ED is co-ed: code ANY, shown Coed (was blank)', array( $kids[5]['genders'], $kids[5]['gender'] ), array( array( 'ANY' ), 'Coed' ) );
chk( 'CO_ED sessions, any case', array( $kids[41]['gender'], $kids[42]['gender'], $kids[42]['genders'] ), array( 'Coed', 'Coed', array( 'ANY' ) ) );
chk( 'an ANY main program over CO_ED sessions: Coed, one code', array( $mast[40]['gender'], $mast[40]['genders'] ), array( 'Coed', array( 'ANY' ) ) );
chk( 'a field mapping cannot rewrite the codes', isset( DS_Programs_Data::catalog()['genders'] ), false );

echo "the scope\n";
$scope = function ( $rows, $ticked ) {
	$g   = DS_G_Under_Test::type_list( $ticked );
	$ids = array();
	foreach ( $rows as $id => $r ) { if ( DS_G_Under_Test::in_genders( $r, $g ) ) { $ids[] = $id; } }
	sort( $ids );
	return $ids;
};
$all = array_keys( $kids ); sort( $all );
chk( 'nothing ticked (every block saved before this version): every program', $scope( $kids, array() ), $all );
chk( 'nothing ticked, BB empty string shape', $scope( $kids, '' ), $all );
chk( 'Girls', $scope( $kids, array( 'FEMALE' ) ), array( 1, 11, 21 ) );
chk( 'Boys', $scope( $kids, array( 'MALE' ) ), array( 4, 12 ) );
chk( 'Coed: ANY, CO_ED and blank', $scope( $kids, array( 'ANY' ) ), array( 2, 3, 5, 31, 41, 42 ) );
chk( 'Girls + Coed', $scope( $kids, array( 'FEMALE', 'ANY' ) ), array( 1, 2, 3, 5, 11, 21, 31, 41, 42 ) );
chk( 'a comma string (BB button-group shape) works too', $scope( $kids, 'FEMALE,ANY' ), array( 1, 2, 3, 5, 11, 21, 31, 41, 42 ) );
chk( 'case does not matter', $scope( $kids, array( 'female' ) ), array( 1, 11, 21 ) );
chk( 'every option ticked = every program', $scope( $kids, array( 'MALE', 'FEMALE', 'ANY' ) ), $all );
chk( 'main program only: a tournament with girls divisions lists on a girls page', $scope( $mast, array( 'FEMALE' ) ), array( 1, 10, 20 ) );
chk( 'main program only: Coed lists the CO_ED series', in_array( 40, $scope( $mast, array( 'ANY' ) ), true ), true );
chk( 'main program only: that tournament is not coed (its divisions are not)', in_array( 10, $scope( $mast, array( 'ANY' ) ), true ), false );
chk( 'main + sub-programs: the heading stays, the boys division goes', $scope( $both, array( 'FEMALE' ) ), array( 1, 10, 11, 20, 21 ) );
chk( 'a row without codes is not guessed into a ticked gender', DS_G_Under_Test::in_genders( array( 'gender' => 'Girls' ), array( 'FEMALE' ) ), false );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
