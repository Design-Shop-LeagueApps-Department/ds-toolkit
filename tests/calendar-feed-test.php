<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Calendar feed: how many requests LeagueApps receives in every situation
 * (cold, warm, expiry, partial failure, nobody answering, 429, flush, lock), the
 * normalising rules, and the security rules on what reaches the page.
 * Stub-style, no WordPress needed:
 *
 *   php tests/calendar-feed-test.php
 */
define('ABSPATH', '/tmp/fake-wp/');
define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600); define('DAY_IN_SECONDS', 86400);

$GLOBALS['tr'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['cron'] = array();
$GLOBALS['list_calls'] = 0;       // /programs/current requests
$GLOBALS['sched_urls'] = array(); // schedule URLs requested, per batch
$GLOBALS['sched'] = array();      // programId => array(code, body)

function get_transient($k){ return array_key_exists($k,$GLOBALS['tr']) ? $GLOBALS['tr'][$k][0] : false; }
function set_transient($k,$v,$ttl=0){ $GLOBALS['tr'][$k]=array($v,$ttl); return true; }
function delete_transient($k){ unset($GLOBALS['tr'][$k]); return true; }
function ttl_of($k){ return $GLOBALS['tr'][$k][1] ?? null; }
function get_option($k,$d=''){ return array_key_exists($k,$GLOBALS['opts'])?$GLOBALS['opts'][$k]:$d; }
function update_option($k,$v,$a=null){ $GLOBALS['opts'][$k]=$v; return true; }
function do_action($h,$a=null){ $GLOBALS['actions'][$h][]=$a; if('ds_programs_flushed'===$h){ DS_Calendar_Data::flush(); } } // mirrors the feature's add_action
function has_filter($h){ return false; }
function is_wp_error($e){ return $e instanceof WP_Error; }
class WP_Error { private $m,$d; function __construct($c='',$m='',$d=null){ $this->m=$m; $this->d=$d; } function get_error_message(){ return $this->m; } function get_error_data(){ return $this->d; } }
function wp_remote_get($url,$args=array()){ $GLOBALS['list_calls']++; return array('code'=>200,'body'=>$GLOBALS['list_body'],'headers'=>array()); }
function wp_remote_retrieve_response_code($r){ return $r['code'] ?? 0; }
function wp_remote_retrieve_body($r){ return $r['body'] ?? ''; }
function wp_remote_retrieve_header($r,$h){ return $r['headers'][strtolower($h)] ?? ''; }
function wp_json_encode($v,$f=0){ return json_encode($v,$f); }
function wp_list_pluck($l,$f){ $o=array(); foreach($l as $i){ $o[]=is_array($i)?$i[$f]:$i->$f; } return $o; }
function sanitize_text_field($s){ return trim(strip_tags($s)); }
function esc_url_raw($u,$p=null){ $p=$p?:array('http','https'); $s=strtolower((string)parse_url($u,PHP_URL_SCHEME)); return in_array($s,$p,true)?$u:''; }
function __($s,$d=''){ return $s; }
function home_url($p=''){ return 'https://example.org'.$p; }
function wp_strip_all_tags($s){ return trim(strip_tags($s)); }
function wp_next_scheduled($h,$a=array()){ foreach($GLOBALS['cron'] as $c){ if($c[0]===$h && $c[1]===$a) return 1; } return false; }
function wp_schedule_single_event($t,$h,$a=array()){ $GLOBALS['cron'][]=array($h,$a); return true; }
function apply_filters($h,$v,...$a){
  if ('ds_calendar_http'!==$h) return $v;
  $urls=$a[0]; $GLOBALS['sched_urls'][]=array_keys($urls); $out=array();
  foreach($urls as $k=>$u){ preg_match('~/programs/(\d+)/schedule~',$u,$m); $out[$k]=$GLOBALS['sched'][$m[1]] ?? array('code'=>404,'body'=>'{"errorCode":40003}'); }
  return $out;
}
// A tiny options table for the atomic lock (INSERT IGNORE / compare-and-swap UPDATE / DELETE).
class FakeWpdb {
  public $options='wp_options'; public $rows=array();
  function prepare($q,...$a){ return array($q,$a); }
  function query($q){
    if(!is_array($q)) return 0; list($sql,$a)=$q;
    if(0===strpos($sql,'INSERT IGNORE')){ if(isset($this->rows[$a[0]])) return 0; $this->rows[$a[0]]=$a[1]; return 1; }
    if(0===strpos($sql,'UPDATE')){ if(($this->rows[$a[1]]??null)===$a[2]){ $this->rows[$a[1]]=$a[0]; return 1; } return 0; }
    if(0===strpos($sql,'DELETE')){ unset($this->rows[$a[0]]); return 1; }
    return 0;
  }
  function get_var($q){ return $this->rows[$q[1][0]] ?? null; }
}
$wpdb = new FakeWpdb();

require __DIR__ . '/../includes/class-ds-programs-data.php';
require __DIR__ . '/../includes/class-ds-calendar-data.php';

$fails=0;
function chk($l,$g,$w){ global $fails; $ok=($g===$w); if(!$ok)$fails++; printf("  %-72s %s\n",$l,$ok?'ok':"FAIL got=".var_export($g,true)); }
function reqs(){ $n=0; foreach($GLOBALS['sched_urls'] as $b){ $n+=count($b); } return $n; }
function reset_reqs(){ $GLOBALS['sched_urls']=array(); $GLOBALS['list_calls']=0; $GLOBALS['cron']=array(); }
function age_store($sec,$only=null){ $k='ds_calendar_store_57065'; $s=get_transient($k); foreach($s['p'] as $pid=>$e){ if(null===$only||in_array($pid,(array)$only)){ $s['p'][$pid]['t']=$e['t']-$sec; } } set_transient($k,$s,1); }
function expire(){ delete_transient('ds_calendar_'.md5(json_encode(array('57065')))); }

$GLOBALS['opts']['ds_toolkit_settings'] = array( 'leagueapps_sites' => array( array( 'site_id'=>'57065', 'api_key'=>'secretkey123', 'label'=>'Club' ) ) );
$progs=array(); for($i=1;$i<=20;$i++){ $progs[]=array('programId'=>$i,'name'=>"P$i",'type'=>'CLUBTEAM','state'=>'LIVE','startTime'=>1790000000000,'endTime'=>1791000000000); }
$GLOBALS['list_body']=json_encode($progs);
$Y = (int) date('Y') + 1; // always in the future
$ny = function($s){ return (new DateTime($s, new DateTimeZone('America/New_York')))->getTimestamp()*1000; };
$ev = function($id,$team,$title,$s,$e,$desc='https://events.example.com/1',$loc='Hall') { return array('scheduleItemType'=>'EVENT','eventId'=>$id,'teamName'=>$team,'type'=>'Tournament','title'=>$title,'startDate'=>$s,'endDate'=>$e,'description'=>$desc,'location'=>$loc,'latitude'=>41.4,'longitude'=>-82.6); };
$GLOBALS['sched'][1]=array('code'=>200,'body'=>json_encode(array('games'=>array(
  $ev(1,'14 Sunrise','Winter Warm-Up',$ny("$Y-01-02 00:00"),$ny("$Y-01-03 00:00")),
  $ev(2,'14 Sunrise','Winter Warm-Up*',$ny("$Y-01-02 08:00"),$ny("$Y-01-02 22:00")),
  $ev(3,'15 Twilight','Winter Warm-Up (Saturday-Sunday)',$ny("$Y-01-02 08:00"),$ny("$Y-01-02 22:00")),
  $ev(4,'13 Firefly','Rock N Rumble',$ny("$Y-01-16 00:00"),$ny("$Y-01-18 00:00")),
  $ev(7,'13 Firefly','HOLD - Showcase',$ny("$Y-03-06 00:00"),$ny("$Y-03-07 23:59")),
  $ev(8,'13 Firefly','Overnight Camp',$ny("$Y-04-10 18:00"),$ny("$Y-04-11 10:00")),
  $ev(9,'13 Firefly','Last Year Event',$ny(($Y-2)."-05-01 00:00"),$ny(($Y-2)."-05-02 00:00")),
),'notes'=>array())));
$GLOBALS['sched'][2]=array('code'=>200,'body'=>json_encode(array('games'=>array(
  array('scheduleItemType'=>'STRUCTURED_GAME','gameId'=>9,'typeLabel'=>'League','startTime'=>$ny("$Y-02-06 18:30"),'endTime'=>$ny("$Y-02-06 20:00"),'team1'=>'14 Sunrise','team2'=>'Rivals','locationName'=>'Gym'),
  $ev(5,'14 Sunrise','<script>alert(1)</script>Kids America',$ny("$Y-02-07 00:00"),null,'javascript:alert(1)'),
  $ev(6,'14 Sunrise','Data URL',$ny("$Y-02-08 00:00"),null,'see data:text/html,x'),
))));
$GLOBALS['sched'][3]=array('code'=>200,'body'=>json_encode(array('games'=>array())));

echo "cold load\n";
$r = DS_Calendar_Data::get();
chk('one program-list request (shared Programs cache)', $GLOBALS['list_calls'], 1);
chk('20 schedule requests on the first load', reqs(), 20);
chk('in batches of 8 (8, 8, 4)', array_map('count',$GLOBALS['sched_urls']), array(8,8,4));
chk('merged calendar cached for an hour', ttl_of('ds_calendar_'.md5(json_encode(array('57065')))), HOUR_IN_SECONDS);
$led=$GLOBALS['opts']['ds_programs_ledger'];
chk('ledger line carries the real request count', $led['recent'][0]['tries'], 20);
$h=end($led['hours']);
chk('calendar batch is not counted as a Programs listing fetch', $h[0], 1);
chk('...but its 20 requests are counted', $h[1], 21);
chk('lock released after the refresh', count($wpdb->rows), 0);

echo "normalise\n";
$byd=array(); foreach($r['events'] as $e){ $byd[$e['d'].' '.$e['title']]=$e; }
$ww=$byd["$Y-01-02 Winter Warm-Up"] ?? null;
chk('"(Saturday-Sunday)" and "*" entries merge into one event', $ww ? count($ww['teams']) : -1, 2);
chk('timed entry beats all-day for the same team', $ww ? $ww['time'] : null, '8 AM – 10 PM');
chk('midnight-to-midnight over 2 days = 2 days', isset($byd["$Y-01-16 Rock N Rumble"],$byd["$Y-01-17 Rock N Rumble"]), true);
chk('midnight to 23:59 = all day, both days', array(isset($byd["$Y-03-06 HOLD - Showcase"],$byd["$Y-03-07 HOLD - Showcase"]), $byd["$Y-03-07 HOLD - Showcase"]['time'] ?? 'x'), array(true,''));
$oc=$byd["$Y-04-10 Overnight Camp"] ?? array();
chk('a timed event across midnight shows on both days with day names', array(isset($byd["$Y-04-11 Overnight Camp"]), $oc['time'] ?? ''), array(true, date('D',strtotime("$Y-04-10")).' 6 PM – '.date('D',strtotime("$Y-04-11")).' 10 AM'));
chk('a game uses startTime and reads Eastern wall-clock', ($byd["$Y-02-06 14 Sunrise vs Rivals"]['time'] ?? ''), '6:30 PM – 8 PM');
chk('events before this month are pruned', isset($byd[($Y-2)."-05-01 Last Year Event"]), false);
chk('teams sorted naturally', $r['teams'], array('13 Firefly','14 Sunrise','15 Twilight','Rivals'));

echo "security\n";
$ka=null; $du=null; foreach($r['events'] as $e){ if("$Y-02-07"===$e['d']) $ka=$e; if("$Y-02-08"===$e['d']) $du=$e; }
chk('tags stripped from LeagueApps titles', $ka['title'] ?? null, 'alert(1)Kids America');
chk('javascript: description never becomes a link', $ka['url'] ?? null, '');
chk('data: URL never becomes a link', $du['url'] ?? null, '');
chk('the API key never appears in the calendar data', false !== strpos(json_encode($r),'secretkey123'), false);
$json = json_encode(array('t'=>'</script><script>x</script>'), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
chk('JSON in the page cannot close its <script> tag', false !== strpos($json,'</'), false);

echo "warm\n";
reset_reqs(); DS_Calendar_Data::get(); DS_Calendar_Data::get();
chk('no requests at all while cached', array($GLOBALS['list_calls'], reqs()), array(0,0));

echo "expiry: the visitor never waits\n";
expire(); reset_reqs();
$rx=DS_Calendar_Data::get(); DS_Calendar_Data::get();
chk('expired calendar served from its copy with no requests', array(reqs(), count($rx['events'])>0), array(0,true));
chk('one ds_calendar_refresh job queued, not two', count($GLOBALS['cron']), 1);
DS_Calendar_Data::refresh(array('57065'));
chk('the job asks nobody: every schedule is under an hour old', reqs(), 0);
age_store(HOUR_IN_SECONDS+5); expire(); reset_reqs();
DS_Calendar_Data::refresh(array('57065'));
chk('an hour later only the 3 programs with schedules are asked', reqs(), 3);
expire(); age_store(7*HOUR_IN_SECONDS); reset_reqs();
DS_Calendar_Data::refresh(array('57065'));
chk('"Schedule Not Found" programs are re-asked after 6 hours', reqs(), 20);

echo "partial failure: only the failing program is retried, on an escalating timer\n";
$GLOBALS['sched'][2]=array('code'=>500,'body'=>'');
age_store(HOUR_IN_SECONDS+5,array(1,2,3)); expire(); reset_reqs();
DS_Calendar_Data::refresh(array('57065')); $rp=DS_Calendar_Data::get();
chk('only the 3 due programs asked', reqs(), 3);
$hasGame=false; foreach($rp['events'] as $e){ if('Game'===$e['type']) $hasGame=true; }
chk('the failing program keeps its last good schedule', $hasGame, true);
chk('merged copy retried soon (5 min)', ttl_of('ds_calendar_'.md5(json_encode(array('57065')))), 300);
$st=get_transient('ds_calendar_store_57065'); $d=$st['p'][2]['retry']-time();
chk('first failure: retry in 5 minutes', $d>=299 && $d<=300, true);
expire(); reset_reqs(); DS_Calendar_Data::refresh(array('57065'));
chk('during its back-off nothing is asked (the others are fresh)', reqs(), 0);
$st['p'][2]['retry']=time()-1; set_transient('ds_calendar_store_57065',$st,1); expire(); reset_reqs();
DS_Calendar_Data::refresh(array('57065'));
chk('after the back-off only the failing program is asked', reqs(), 1);
$st=get_transient('ds_calendar_store_57065'); $d=$st['p'][2]['retry']-time();
chk('second failure: retry in 10 minutes', $d>=599 && $d<=600, true);

echo "nobody answering\n";
for($i=1;$i<=20;$i++){ $GLOBALS['sched'][$i]=array('code'=>0,'body'=>''); }
$st=get_transient('ds_calendar_store_57065'); foreach($st['p'] as $pid=>$e){ $st['p'][$pid]=array('t'=>0,'items'=>$e['items'],'nf'=>0,'fails'=>0,'retry'=>0); } set_transient('ds_calendar_store_57065',$st,1);
expire(); reset_reqs(); DS_Calendar_Data::refresh(array('57065'));
chk('stops after the first batch nobody answered (8 of 20)', reqs(), 8);

echo "429\n";
for($i=1;$i<=20;$i++){ $GLOBALS['sched'][$i]=array('code'=>429,'body'=>'','retry_after'=>900); }
$st=get_transient('ds_calendar_store_57065'); foreach($st['p'] as $pid=>$e){ $st['p'][$pid]['retry']=0; $st['p'][$pid]['fails']=0; $st['p'][$pid]['t']=0; } set_transient('ds_calendar_store_57065',$st,1);
expire(); reset_reqs(); DS_Calendar_Data::refresh(array('57065'));
chk('stops after the throttled batch', count($GLOBALS['sched_urls']), 1);
$st=get_transient('ds_calendar_store_57065'); $d=$st['hold']-time();
chk('site holds off for Retry-After (900s)', $d>=899 && $d<=900, true);
expire(); reset_reqs(); DS_Calendar_Data::refresh(array('57065'));
chk('no requests during the hold', reqs(), 0);
$st['hold']=0; set_transient('ds_calendar_store_57065',$st,1);
for($i=1;$i<=20;$i++){ $GLOBALS['sched'][$i]=array('code'=>404,'body'=>''); }
$GLOBALS['sched'][1]=array('code'=>200,'body'=>json_encode(array('games'=>array())));
expire(); reset_reqs(); DS_Calendar_Data::refresh(array('57065'));

echo "flush\n";
reset_reqs(); DS_Programs_Data::flush();
DS_Calendar_Data::get();
chk('flush right after a refresh refetches nothing (under 10 minutes old)', reqs(), 0);
age_store(11*MINUTE_IN_SECONDS); reset_reqs(); DS_Programs_Data::flush();
DS_Calendar_Data::get();
chk('flush refetches schedules over 10 minutes old, incl. "not found" ones', reqs(), 20);

echo "lock\n";
delete_transient('ds_calendar_flushed'); expire(); age_store(2*HOUR_IN_SECONDS);
$lk='ds_calendar_lock_'.md5(json_encode(array('57065')));
$wpdb->rows[$lk]=(string)time(); reset_reqs();
DS_Calendar_Data::refresh(array('57065'));
chk('a held lock: no requests', reqs(), 0);
$wpdb->rows[$lk]=(string)(time()-120); reset_reqs();
DS_Calendar_Data::refresh(array('57065'));
chk('a dead lock (over 60s) is taken over and released', array(reqs()>0, isset($wpdb->rows[$lk])), array(true,false));

echo "no saved site\n";
$GLOBALS['opts']['ds_toolkit_settings']=array(); reset_reqs();
$r4=DS_Calendar_Data::get();
chk('no site configured -> no_sites, no requests', array($r4['no_sites'], count($r4['events']), reqs()), array(true,0,0));

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
