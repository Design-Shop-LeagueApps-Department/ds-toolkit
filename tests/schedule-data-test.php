<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Team Schedule data: how many requests LeagueApps receives (cold, warm, expired,
 * 404, failure, 429, lock, flush), and the row rules (one row per item, date ranges, title
 * clean-up, duplicates, team filter, past, maps, nothing unsafe reaching the page).
 * Stub-style, no WordPress needed:
 *
 *   php tests/schedule-data-test.php
 */
define('ABSPATH', '/tmp/fake-wp/');
define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600); define('DAY_IN_SECONDS', 86400);

$GLOBALS['tr'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['cron'] = array(); $GLOBALS['urls'] = array(); $GLOBALS['ledger'] = array();
$GLOBALS['answer'] = array('code'=>200,'body'=>'{"games":[]}','retry_after'=>0);

function get_transient($k){ return array_key_exists($k,$GLOBALS['tr']) ? $GLOBALS['tr'][$k][0] : false; }
function set_transient($k,$v,$ttl=0){ $GLOBALS['tr'][$k]=array($v,$ttl); return true; }
function delete_transient($k){ unset($GLOBALS['tr'][$k]); return true; }
function get_option($k,$d=''){ return array_key_exists($k,$GLOBALS['opts'])?$GLOBALS['opts'][$k]:$d; }
function update_option($k,$v,$a=null){ $GLOBALS['opts'][$k]=$v; return true; }
function do_action($h,$a=null){}
function has_filter($h){ return false; }
function is_wp_error($e){ return false; }
function wp_json_encode($v,$f=0){ return json_encode($v,$f); }
function wp_list_pluck($l,$f){ $o=array(); foreach($l as $i){ $o[]=is_array($i)?$i[$f]:$i->$f; } return $o; }
function sanitize_text_field($s){ return trim(strip_tags($s)); }
function esc_url_raw($u,$p=null){ $p=$p?:array('http','https'); $s=strtolower((string)parse_url($u,PHP_URL_SCHEME)); return in_array($s,$p,true)?$u:''; }
function __($s,$d=''){ return $s; }
function home_url($p=''){ return 'https://example.org'.$p; }
function wp_strip_all_tags($s){ return trim(strip_tags($s)); }
function wp_next_scheduled($h,$a=array()){ foreach($GLOBALS['cron'] as $c){ if($c[0]===$h && $c[1]===$a) return 1; } return false; }
function wp_schedule_single_event($t,$h,$a=array()){ $GLOBALS['cron'][]=array($h,$a); return true; }
$GLOBALS['list_calls']=0; $GLOBALS['list_body']='[]';
function wp_remote_get($url,$args=array()){ $GLOBALS['list_calls']++; return array('code'=>200,'body'=>$GLOBALS['list_body'],'headers'=>array()); }
function wp_remote_retrieve_response_code($r){ return $r['code'] ?? 0; }
function wp_remote_retrieve_body($r){ return $r['body'] ?? ''; }
function wp_remote_retrieve_header($r,$h){ return $r['headers'][strtolower($h)] ?? ''; }
function apply_filters($h,$v,...$a){
  if ('ds_la_schedule_http'!==$h) return $v;
  $GLOBALS['urls'][]=$a[0];
  return $GLOBALS['answer'];
}
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
require __DIR__ . '/../includes/class-ds-schedule-data.php';

$fails=0;
function chk($l,$g,$w){ global $fails; $ok=($g===$w); if(!$ok)$fails++; printf("  %-74s %s\n",$l,$ok?'ok':"FAIL got=".var_export($g,true)); }
function reqs(){ $n=count($GLOBALS['urls']); $GLOBALS['urls']=array(); $GLOBALS['cron']=array(); return $n; }
function store(){ return $GLOBALS['opts']['ds_la_sched_52078_5079447'] ?? null; }
function age($sec){ $s=store(); $s['t']-=$sec; $GLOBALS['opts']['ds_la_sched_52078_5079447']=$s; }

$GLOBALS['opts']['ds_toolkit_settings'] = array( 'leagueapps_sites' => array( array( 'site_id'=>'52078', 'api_key'=>'secretkey123', 'label'=>'Surfside' ) ) );
$K = '52078:5079447';
$ny = function($s){ return (new DateTime($s, new DateTimeZone('America/New_York')))->getTimestamp()*1000; };
$Y  = (int) date('Y') + 1;
$items = array(
  array('scheduleItemType'=>'EVENT','eventId'=>1,'teamName'=>'17 Legends','type'=>'Practice','title'=>'17L - Practice - ECC North','startDate'=>$ny("$Y-01-05 18:00"),'endDate'=>$ny("$Y-01-05 20:00"),'location'=>'El Camino College, North Gym','latitude'=>33.88439,'longitude'=>-118.33016),
  array('scheduleItemType'=>'EVENT','eventId'=>2,'teamName'=>'17 Legends','type'=>'Tournament','title'=>'17L SCSN Entry Tournament','startDate'=>$ny("$Y-01-10 00:00"),'endDate'=>$ny("$Y-01-12 00:00"),'location'=>'TBD'),
  array('scheduleItemType'=>'EVENT','eventId'=>3,'teamName'=>'17 Legends','type'=>'Tournament','title'=>'17L SCSN Entry Tournament','startDate'=>$ny("$Y-01-10 08:00"),'endDate'=>$ny("$Y-01-10 17:00"),'location'=>'TBD'),
  array('scheduleItemType'=>'EVENT','eventId'=>4,'teamName'=>'17 Legends','type'=>'Tournament','title'=>'17u Tournament #1','startDate'=>$ny("$Y-01-30 00:00"),'endDate'=>$ny("$Y-02-01 23:59"),'location'=>'Long Beach Convention Center'),
  array('scheduleItemType'=>'EVENT','eventId'=>5,'teamName'=>'17 Legends','type'=>'Practice','title'=>'17L - Practice','startDate'=>$ny("$Y-01-06 18:00"),'endDate'=>$ny("$Y-01-06 20:00"),'location'=>''),
  array('scheduleItemType'=>'EVENT','eventId'=>6,'teamName'=>'17 Legends','type'=>'Practice','title'=>'17L - Practice - ECC South','startDate'=>$ny("$Y-01-07 18:00"),'endDate'=>$ny("$Y-01-07 20:00"),'location'=>'TBD'),
  array('scheduleItemType'=>'EVENT','eventId'=>7,'teamName'=>'17 Legends','type'=>'Tournament','title'=>'<img src=x onerror=alert(1)>Spring Classic','startDate'=>$ny("2020-03-01 00:00"),'endDate'=>$ny("2020-03-02 00:00"),'location'=>'Old Gym'),
  array('scheduleItemType'=>'STRUCTURED_GAME','gameId'=>9,'typeLabel'=>'League','startTime'=>$ny("$Y-02-06 18:30"),'endTime'=>$ny("$Y-02-06 20:00"),'team1'=>'17 Legends','team2'=>'Rivals','locationName'=>'Gym','subLocationName'=>'Court 2'),
  array('scheduleItemType'=>'STRUCTURED_GAME','gameId'=>10,'startTime'=>$ny("$Y-02-07 18:30"),'endTime'=>$ny("$Y-02-07 20:00"),'team1'=>'Others','team2'=>'Rivals','locationName'=>'Gym'),
  array('scheduleItemType'=>'EVENT','eventId'=>1,'teamName'=>'17 Legends','type'=>'Practice','title'=>'17L - Practice - ECC North','startDate'=>$ny("$Y-01-05 18:00"),'endDate'=>$ny("$Y-01-05 20:00"),'location'=>'El Camino College, North Gym'),
);
$GLOBALS['answer'] = array('code'=>200,'body'=>json_encode(array('games'=>$items)),'retry_after'=>0);

echo "requests\n";
$r = DS_Schedule_Data::items($K);
$asked = $GLOBALS['urls'];
chk('cold load: one request for this one program', reqs(), 1);
chk('asks only for /programs/5079447/schedule', (bool) preg_match('~/sites/52078/programs/5079447/schedule\?~', $asked[0] ?? ''), true);
chk('copy stored without autoload', isset($GLOBALS['opts']['ds_la_sched_52078_5079447']), true);
chk('the 2020 item is pruned before storing', count(store()['items']), count($items)-1);
DS_Schedule_Data::items($K);
chk('warm: no request', reqs(), 0);
age(HOUR_IN_SECONDS+5);
$r = DS_Schedule_Data::items($K);
chk('expired under 6 h: served stale, no request inline', reqs(), 0);
$GLOBALS['cron'][] = array('x',array()); // reqs() cleared cron; check the queue directly next time
age(0); $GLOBALS['cron']=array(); age(HOUR_IN_SECONDS+5);
DS_Schedule_Data::items($K);
chk('... and a background refresh queued for this program', $GLOBALS['cron'][0] ?? null, array('ds_la_schedule_refresh', array($K)));
DS_Schedule_Data::refresh($K);
chk('cron refresh: one request', reqs(), 1);
age(7*HOUR_IN_SECONDS);
DS_Schedule_Data::items($K);
chk('older than 6 h: refreshed inline', reqs(), 1);

echo "failures\n";
$good = count(store()['items']);
age(HOUR_IN_SECONDS+5); age(6*HOUR_IN_SECONDS);
$GLOBALS['answer'] = array('code'=>500,'body'=>'','retry_after'=>0);
$r = DS_Schedule_Data::items($K);
chk('500: one request', reqs(), 1);
chk('500: last good schedule still served', count($r['items']), $good);
DS_Schedule_Data::items($K);
chk('500: no retry before the timer', reqs(), 0);
chk('500: retry in 5 minutes', store()['retry'] - time() <= 300 && store()['retry'] - time() >= 295, true);
$s=store(); $s['retry']=time()-1; $GLOBALS['opts']['ds_la_sched_52078_5079447']=$s;
DS_Schedule_Data::items($K); reqs();
chk('second failure doubles the timer (10 min)', store()['retry'] - time() >= 595, true);
$s=store(); $s['retry']=time()-1; $GLOBALS['opts']['ds_la_sched_52078_5079447']=$s;
$GLOBALS['answer'] = array('code'=>429,'body'=>'','retry_after'=>1800);
DS_Schedule_Data::items($K);
chk('429: one request', reqs(), 1);
chk('429: holds for Retry-After (30 min)', store()['retry'] - time() >= 1795, true);
DS_Schedule_Data::items($K);
chk('429: nothing more until then', reqs(), 0);
chk('every request is in the ledger', count(DS_Programs_Data::ledger()) >= 6, true);

echo "a 200 that is not a schedule\n";
$GLOBALS['answer'] = array('code'=>200,'body'=>json_encode(array('games'=>$items)),'retry_after'=>0);
unset($GLOBALS['opts']['ds_la_sched_52078_5079447']); DS_Schedule_Data::items($K); reqs();
$good = count(store()['items']);
age(7*HOUR_IN_SECONDS);
$GLOBALS['answer'] = array('code'=>200,'body'=>'<html>maintenance</html>','retry_after'=>0);
$r = DS_Schedule_Data::items($K); reqs();
chk('a maintenance page with HTTP 200: the last good schedule keeps showing', count($r['items']), $good);
chk('... and it is retried on the failure timer, not trusted for an hour', store()['retry'] > time(), true);

echo "404\n";
$GLOBALS['answer'] = array('code'=>404,'body'=>'{"errorCode":40003}','retry_after'=>0);
unset($GLOBALS['opts']['ds_la_sched_52078_5079447']);
$r = DS_Schedule_Data::items($K);
chk('404: one request', reqs(), 1);
chk('404: empty, marked not found', array(count($r['items']), $r['nf']), array(0, true));
age(2*HOUR_IN_SECONDS);
DS_Schedule_Data::items($K);
chk('404 remembered for 6 hours (no request after 2 h)', reqs(), 0);

echo "lock\n";
unset($GLOBALS['opts']['ds_la_sched_52078_5079447']);
$wpdb->rows['ds_la_sched_lock_52078_5079447'] = (string) time();
DS_Schedule_Data::items($K);
chk('someone else fetching: no second request', reqs(), 0);
$wpdb->rows['ds_la_sched_lock_52078_5079447'] = (string) (time()-120);
$GLOBALS['answer'] = array('code'=>200,'body'=>json_encode(array('games'=>$items)),'retry_after'=>0);
DS_Schedule_Data::items($K);
chk('a dead lock (2 min old) is taken over', reqs(), 1);
chk('lock released', isset($wpdb->rows['ds_la_sched_lock_52078_5079447']), false);

echo "flush\n";
age(20*MINUTE_IN_SECONDS);
DS_Schedule_Data::flush();
DS_Schedule_Data::items($K);
chk('Refresh LeagueApps listings: a 20-minute-old copy refetches', reqs(), 1);
DS_Schedule_Data::items($K);
chk('... but a fresh one does not', reqs(), 0);

echo "keys\n";
chk('unknown site: nothing, no request', array(DS_Schedule_Data::items('99999:1')['ok'], reqs()), array(false, 0));
chk('garbage key refused', DS_Schedule_Data::parse_key('52078:1;DROP'), null);
chk('the key never appears in a stored copy', strpos(json_encode($GLOBALS['opts']['ds_la_sched_52078_5079447']), 'secretkey123'), false);

echo "rows\n";
$now  = (new DateTime("$Y-01-01 09:00", new DateTimeZone('America/New_York')))->getTimestamp();
$rows = DS_Schedule_Data::rows($items, '', $now);
$by   = array(); foreach ($rows as $x) { $by[$x['day'].' '.$x['title']] = $x; }
chk('one row per item, duplicates and the repeated event dropped', count($rows), 8);
chk('a 3-day tournament is one row ending on its last day', $by["$Y-01-10 SCSN Entry Tournament"]['last'] ?? null, "$Y-01-11");
chk('... the timed duplicate of that day collapses into it', count(array_filter($rows, function($x){ return 'SCSN Entry Tournament'===$x['title']; })), 1);
chk('team code dropped from titles ("17L SCSN..." -> "SCSN...")', isset($by["$Y-01-10 SCSN Entry Tournament"]), true);
chk('"17L - Practice - ECC North" shows as Practice', $by["$Y-01-05 Practice"]['title'] ?? null, 'Practice');
chk('... at its real location', $by["$Y-01-05 Practice"]['loc'] ?? null, 'El Camino College, North Gym');
chk('a practice with location TBD uses the gym from its title', $by["$Y-01-07 Practice"]['loc'] ?? null, 'ECC South');
chk('no location and nothing in the title: empty (shown as TBD)', $by["$Y-01-06 Practice"]['loc'] ?? null, '');
chk('"TBD" never gets a map link', $by["$Y-01-10 SCSN Entry Tournament"]['map'] ?? null, '');
chk('map link from coordinates', $by["$Y-01-05 Practice"]['map'] ?? null, 'https://www.google.com/maps/search/?api=1&query=33.88439%2C-118.33016');
chk('timed: "6 PM – 8 PM" (Eastern wall-clock)', $by["$Y-01-05 Practice"]['time'] ?? null, '6 PM – 8 PM');
chk('all day: no time', $by["$Y-01-10 SCSN Entry Tournament"]['time'] ?? null, '');
chk('a 23:59 end counts as the end of that day', $by["$Y-01-30 17u Tournament #1"]['last'] ?? null, "$Y-02-01");
chk('game title, type and label', array($by["$Y-02-06 17 Legends vs Rivals"]['type'] ?? null, $by["$Y-02-06 17 Legends vs Rivals"]['label'] ?? null), array('Game', 'League'));
chk('game location includes the court', $by["$Y-02-06 17 Legends vs Rivals"]['loc'] ?? null, 'Gym · Court 2');
chk('rows sorted by date then time', array_column($rows, 'day') === array_values(array_map(null, (function($d){ sort($d); return $d; })(array_column($rows,'day')))), true);
$past = array_values(array_filter($rows, function($x){ return $x['past']; }));
chk('the 2020 item is past, everything else upcoming', array(count($past), $past[0]['day'] ?? null), array(1, '2020-03-01'));
chk('HTML in a title is stripped', $past[0]['title'], 'Spring Classic');
$only = DS_Schedule_Data::rows($items, '17 legends', $now);
chk('team filter keeps that team\'s items, drops the other game', count($only), 7);
chk('team filter matches team2 too (case-insensitive)', count(DS_Schedule_Data::rows($items, 'RIVALS', $now)), 2);

echo "a tournament entered one item per day\n";
$day = function ($id, $d, $from, $to, $title = 'Winter Warm-Up (Saturday-Sunday)', $loc = 'Cedar Point Sports Center') use ($ny) { return array('scheduleItemType'=>'EVENT','eventId'=>$id,'teamName'=>'14 Sunrise','type'=>'Tournament','title'=>$title,'startDate'=>$ny("$d $from"),'endDate'=>$ny("$d $to"),'location'=>$loc,'latitude'=>41.48,'longitude'=>-82.68); };
$rr = DS_Schedule_Data::rows(array( $day(1,"$Y-01-02",'08:00','22:00'), $day(2,"$Y-01-03",'08:00','22:00'), $day(3,"$Y-01-09",'08:00','22:00','Rock N Rumble'), $day(4,"$Y-01-10",'09:00','18:00','Rock N Rumble') ), '', $now);
chk('Saturday + Sunday entries of one tournament are ONE row with a range', array(count($rr), $rr[0]['day'], $rr[0]['last']), array(2, "$Y-01-02", "$Y-01-03"));
chk('... the hours stay when both days have the same ones', $rr[0]['time'], '8 AM – 10 PM');
chk('... and are left out when the days differ', array($rr[1]['last'], $rr[1]['time']), array("$Y-01-10", ''));
$rr = DS_Schedule_Data::rows(array( $day(1,"$Y-01-02",'08:00','22:00'), $day(2,"$Y-01-09",'08:00','22:00') ), '', $now);
chk('the same tournament a week apart stays two rows', count($rr), 2);
$rr = DS_Schedule_Data::rows(array( $day(1,"$Y-01-02",'08:00','22:00'), $day(2,"$Y-01-03",'08:00','22:00','Winter Warm-Up (Saturday-Sunday)','Other Gym') ), '', $now);
chk('the same name at another place stays two rows', count($rr), 2);
$g = function ($id, $d) use ($ny) { return array('scheduleItemType'=>'STRUCTURED_GAME','gameId'=>$id,'startTime'=>$ny("$d 18:00"),'endTime'=>$ny("$d 20:00"),'team1'=>'14 Sunrise','team2'=>'Rivals','locationName'=>'Gym'); };
$pr = function ($id, $d) use ($ny) { return array('scheduleItemType'=>'EVENT','eventId'=>$id,'teamName'=>'17 Legends','type'=>'Practice','title'=>'17L - Practice - ECC North','startDate'=>$ny("$d 19:00"),'endDate'=>$ny("$d 21:00"),'location'=>'El Camino College, North Gym'); };
chk('practices on back-to-back days stay separate sessions', count(DS_Schedule_Data::rows(array($pr(1,"$Y-01-04"), $pr(2,"$Y-01-05")), '', $now)), 2);
chk('two games on back-to-back days (a series) stay two games', count(DS_Schedule_Data::rows(array($g(1,"$Y-02-06"), $g(2,"$Y-02-07")), '', $now)), 2);
$d = DS_Schedule_Data::date_parts(array('day'=>"$Y-01-02",'last'=>"$Y-01-03"));
chk('the merged row reads "Sat–Sun 2–3"', array($d['dow'], $d['num']), array('Sat–Sun', '2–3'));

echo "labels\n";
chk('team code "17 Legends" -> 17L', DS_Schedule_Data::team_code('17 Legends'), '17L');
chk('team code "12U Black Diamond" -> 12BD', DS_Schedule_Data::team_code('12U Black Diamond'), '12BD');
chk('no number, no code', DS_Schedule_Data::team_code('Sunrise'), '');
chk('"17u Tournament #1" keeps its "17u" (not the team code)', DS_Schedule_Data::clean_title('17u Tournament #1', 'Tournament', '17 Legends'), array('17u Tournament #1', ''));
chk('"17E - SLC Showdown" for 17 Elite', DS_Schedule_Data::clean_title('17E - SLC Showdown tournament', 'Tournament', '17 Elite'), array('SLC Showdown tournament', ''));
chk('date range in one month', DS_Schedule_Data::date_parts(array('day'=>"$Y-01-10",'last'=>"$Y-01-11"))['num'], '10–11');
chk('date range across months', array_intersect_key(DS_Schedule_Data::date_parts(array('day'=>"$Y-01-30",'last'=>"$Y-02-01")), array('num'=>1,'mon'=>1)), array('num'=>'30–1','mon'=>'Jan–Feb'));

echo "picker\n";
set_transient('ds_programs_keys', array('ds_programs_k1'), 1);
set_transient('ds_programs_k1_stale', array('rows'=>array(
  array('_site'=>'52078','programId'=>'5079447','name'=>'2026/2027 Surfside Volleyball: 17 Legends','type'=>'CLUBTEAM','isMaster'=>false,'scheduleUrlHtml'=>'//surfsidevolleyball.leagueapps.com/clubteams/5079447/schedule'),
  array('_site'=>'52078','programId'=>'5079444','name'=>'2026/2027 Surfside Volleyball','type'=>'CLUBTEAM','isMaster'=>true),
  array('_site'=>'52078','programId'=>'4992241','name'=>'17s Tryouts','type'=>'EVENT','isMaster'=>false,'scheduleUrlHtml'=>'javascript:alert(1)'),
  array('_site'=>'11111','programId'=>'1','name'=>'Someone else','type'=>'CLUBTEAM'),
)), 1);
$opt = DS_Schedule_Data::program_options();
chk('options: club teams first, master programs and unsaved sites left out', array_keys($opt), array('52078:5079447', '52078:4992241'));
chk('option label', $opt['52078:5079447'], '2026/2027 Surfside Volleyball: 17 Legends · Club team');
chk('LeagueApps schedule link, https', DS_Schedule_Data::schedule_url(DS_Schedule_Data::program_row('52078','5079447')), 'https://surfsidevolleyball.leagueapps.com/clubteams/5079447/schedule');
chk('a javascript: link is refused', DS_Schedule_Data::schedule_url(DS_Schedule_Data::program_row('52078','4992241')), '');
chk('a plain http:// link is refused (https only)', DS_Schedule_Data::schedule_url(array('scheduleUrlHtml'=>'http://surfsidevolleyball.leagueapps.com/clubteams/5079447/schedule')), '');

echo "link lookup\n";
$GLOBALS['tr']=array(); $GLOBALS['list_calls']=0;
$GLOBALS['list_body']=json_encode(array(array('programId'=>5079447,'name'=>'17 Legends','type'=>'CLUBTEAM','state'=>'LIVE','scheduleUrlHtml'=>'//surfsidevolleyball.leagueapps.com/clubteams/5079447/schedule')));
$site=DS_Programs_Data::configured_sites()[0];
chk('cache only (no site passed): nothing, no request', array(DS_Schedule_Data::program_row('52078','5079447'), $GLOBALS['list_calls']), array(array(), 0));
$row=DS_Schedule_Data::program_row('52078','5079447',$site);
chk('site never listed: one listing request finds the link', array(DS_Schedule_Data::schedule_url($row), $GLOBALS['list_calls']), array('https://surfsidevolleyball.leagueapps.com/clubteams/5079447/schedule', 1));
DS_Schedule_Data::program_row('52078','5079447',$site);
chk('... and the next page view reads the shared cache (no request)', $GLOBALS['list_calls'], 1);
chk('a program the listing does not have: no second request', array(DS_Schedule_Data::program_row('52078','42',$site), $GLOBALS['list_calls']), array(array(), 1));

// The real Surfside feed, if it is on this machine (raw pulls stay local).
$real = getenv('DS_SCHED_SAMPLE');
if ($real && is_readable($real)) {
  echo "real feed (" . basename($real) . ")\n";
  $g = json_decode(file_get_contents($real), true)['games'];
  $rr = DS_Schedule_Data::rows($g, '', strtotime('2026-10-21'));
  $types = array_count_values(array_column($rr, 'type'));
  chk('17 Legends: 9 tournaments', $types['Tournament'] ?? 0, 9);
  chk('every row has a title and a day', count(array_filter($rr, function($x){ return ''!==$x['title'] && ''!==$x['day']; })), count($rr));
  printf("  (%d rows: %s; first: %s %s | %s | %s)\n", count($rr), json_encode($types), $rr[0]['day'], $rr[0]['title'], $rr[0]['time'], $rr[0]['loc']);
  foreach ($rr as $x) { if ('Tournament'===$x['type']) printf("    %s..%s  %-34s %s\n", $x['day'], $x['last'], $x['title'], $x['loc'] ?: '(TBD)'); }
}

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit($fails ? 1 : 0);
