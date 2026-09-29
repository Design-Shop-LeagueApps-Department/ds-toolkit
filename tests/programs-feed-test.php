<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs feed: rate-limiting and cache behaviour, plus the
 * Register button URL rule. Stub-style, no WordPress needed:
 *
 *   php tests/programs-feed-test.php
 *
 * Every assertion here is a request LeagueApps would otherwise have received.
 * The public API sits on the main LeagueApps platform, so the module must
 * never turn our traffic (or a bot's) into their traffic.
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

$fails=0;
function chk($l,$g,$w){ global $fails; $ok=($g===$w); if(!$ok)$fails++; printf("  %-62s %s\n",$l,$ok?'ok':"FAIL got=".var_export($g,true)); }

$site = array( 'site_id'=>'46287', 'api_key'=>'abc123', 'label'=>'Test' );
$GLOBALS['opts']['ds_toolkit_settings'] = array( 'leagueapps_sites' => array( $site ) );
$key = 'ds_programs_' . md5( json_encode( array('46287') ) );

$feed = json_encode(array(
  array('programId'=>1,'name'=>'U8','type'=>'CLUBTEAM','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1790000000000,'endTime'=>1791000000000,
        'registerUrlHtml'=>'//lamorugby.leagueapps.com/registration/init?bid=1',
        'programUrlHtml'=>'//lamorugby.leagueapps.com/clubteams/1-u8'),
  array('programId'=>2,'name'=>'Camp','type'=>'CAMP','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1790000000000,'endTime'=>1791000000000,
        'registerUrlHtml'=>'',
        'programUrlHtml'=>'//dsstormbasketball.leagueapps.com/camps/2-camp'),
));
$ok200 = array('code'=>200,'body'=>$feed,'headers'=>array());

echo "cold cache\n";
$GLOBALS['http']=array($ok200); $GLOBALS['calls']=0;
$r = DS_Programs_Data::get(array($site));
chk('one HTTP call on a miss', $GLOBALS['calls'], 1);
chk('two programs returned', count($r['programs']), 2);
chk('not stale', $r['stale'], false);
chk('request timeout is 8s', $GLOBALS['last_args']['timeout'] ?? null, 8);
chk('fresh cache written with 10-minute TTL', ttl_of($key), 600);
chk('stale copy written with 7-day TTL', ttl_of($key.'_stale'), 7*86400);
chk('refetch lock released', get_transient($key.'_lock'), false);
chk('key registered for flush()', in_array($key, get_transient('ds_programs_keys') ?: array(), true), true);

echo "fetch ledger (a meter, not a throttle)\n";
$led = DS_Programs_Data::ledger();
chk('one ledger entry after one fetch', count($led), 1);
chk('entry: site id', $led[0]['site'], '46287');
chk('entry: ok', $led[0]['ok'], true);
chk('entry: one HTTP request', $led[0]['tries'], 1);
chk('entry: raw row count', $led[0]['rows'], 2);
chk('entry: reason is first load (no stale copy existed)', $led[0]['why'], 'first');
chk('ledger option is NOT autoloaded', $GLOBALS['autoload']['ds_programs_ledger'] ?? null, false);
chk('ds_programs_fetch action fired with the entry', ($GLOBALS['actions']['ds_programs_fetch'][0]['site'] ?? null), '46287');
$sum = DS_Programs_Data::ledger_summary();
chk('summary: 1 fetch, 1 request, 0 failures', array($sum['fetches'],$sum['requests'],$sum['failures']), array(1,1,0));
chk('summary: budget is 144 a day for one site', $sum['budget'], 144);
chk('summary: not over budget', $sum['over'], false);

echo "register button URL\n";
$club = $r['programs'][0]; $camp = $r['programs'][1];
chk('club team: data layer keeps the checkout link as registerUrl', $club['registerUrl'], 'https://lamorugby.leagueapps.com/registration/init?bid=1');
chk('club team: programUrl is the program page', $club['programUrl'], 'https://lamorugby.leagueapps.com/clubteams/1-u8');
chk('club team: BUTTON goes to the program page', DS_Programs_Data::button_url($club), 'https://lamorugby.leagueapps.com/clubteams/1-u8');
chk('camp: button goes to the program page too', DS_Programs_Data::button_url($camp), 'https://dsstormbasketball.leagueapps.com/camps/2-camp');
chk('no page URL: falls back to the registration link', DS_Programs_Data::button_url(array('programUrl'=>'','registerUrl'=>'https://x/reg')), 'https://x/reg');
chk('neither: empty', DS_Programs_Data::button_url(array()), '');

echo "cancelled programs (dsstormbasketball, 2026-09-29)\n"
;
// A cancelled session keeps its programUrl, so button_url() still returns a link and
// the renderer MUST gate on the flag instead. That is the whole bug: the live site
// showed a blue Register button on a session the partner had called off.
$canc_feed = json_encode(array(
  array('programId'=>9,'name'=>'5th/6th grade girls (7pm-8pm)','type'=>'CAMP','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1790000000000,'endTime'=>1791000000000,'registrationStatus'=>'CANCELED',
        'registerUrlHtml'=>'',
        'programUrlHtml'=>'//dsstormbasketball.leagueapps.com/camps/5094563-5th6th-grade-girls-7pm-8pm'),
  array('programId'=>10,'name'=>'Double-L spelling','type'=>'CAMP','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1790000000000,'endTime'=>1791000000000,'registrationStatus'=>'cancelled',
        'registerUrlHtml'=>'','programUrlHtml'=>'//x.leagueapps.com/camps/10-x'),
  array('programId'=>11,'name'=>'Open one','type'=>'CAMP','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1790000000000,'endTime'=>1791000000000,'registrationStatus'=>'OPEN',
        'registerUrlHtml'=>'//x.leagueapps.com/registration/init?bid=11','programUrlHtml'=>'//x.leagueapps.com/camps/11-x'),
));
$GLOBALS['http']=array(array('code'=>200,'body'=>$canc_feed,'headers'=>array()));
delete_transient($key);
$cr = DS_Programs_Data::get(array($site))['programs'];
$by = array(); foreach ($cr as $row) { $by[$row['program']] = $row; }
$c = $by['5th/6th grade girls (7pm-8pm)'];
chk('CANCELED sets the canceled flag', $c['canceled'], true);
chk('CANCELED is NOT reported as sold out', $c['soldOut'], false);
chk('CANCELED gets a human label', $c['status'], 'Cancelled');
chk('button_url STILL returns a link (why the renderer must gate on the flag)', DS_Programs_Data::button_url($c) !== '', true);
chk('the double-L spelling is normalised too', $by['Double-L spelling']['canceled'], true);
chk('an OPEN program is not flagged cancelled', $by['Open one']['canceled'], false);
chk('an OPEN program is not flagged sold out', $by['Open one']['soldOut'], false);
// Put the two-program fixture back: the checks below share the fresh AND stale copies.
$GLOBALS['http']=array($ok200); delete_transient($key); DS_Programs_Data::get(array($site));

echo "warm cache\n";
$GLOBALS['calls']=0;
DS_Programs_Data::get(array($site));
DS_Programs_Data::get(array($site));
chk('two more renders, zero HTTP calls', $GLOBALS['calls'], 0);

echo "LeagueApps returns 500\n";
delete_transient($key); $GLOBALS['http']=array(array('code'=>500,'body'=>'','headers'=>array())); $GLOBALS['calls']=0;
$r = DS_Programs_Data::get(array($site));
chk('exactly one call, no retry into a 5xx', $GLOBALS['calls'], 1);
chk('served the stale copy', $r['stale'], true);
chk('stale copy still has both programs', count($r['programs']), 2);
chk('error surfaced for admins', count($r['errors']) > 0, true);
chk('failure remembered with 2-minute back-off', ttl_of($key), 120);
chk('failure entry is flagged, not mistaken for data', get_transient($key)['failed'] ?? null, true);
chk('lock released after failure', get_transient($key.'_lock'), false);
$GLOBALS['calls']=0;
$r = DS_Programs_Data::get(array($site));
chk('next render during back-off: zero HTTP calls', $GLOBALS['calls'], 0);
chk('still serving stale', $r['stale'], true);

echo "LeagueApps returns 429 with Retry-After\n";
delete_transient($key); $GLOBALS['http']=array(array('code'=>429,'body'=>'','headers'=>array('retry-after'=>'300'))); $GLOBALS['calls']=0;
DS_Programs_Data::get(array($site));
chk('exactly one call, no retry into a 429', $GLOBALS['calls'], 1);
chk('back-off honours Retry-After (300s)', ttl_of($key), 300);
delete_transient($key); $GLOBALS['http']=array(array('code'=>429,'body'=>'','headers'=>array('retry-after'=>'99999')));
DS_Programs_Data::get(array($site));
chk('Retry-After capped at 10 minutes', ttl_of($key), 600);
delete_transient($key); $GLOBALS['http']=array(array('code'=>429,'body'=>'','headers'=>array()));
DS_Programs_Data::get(array($site));
chk('429 without Retry-After: default 2-minute back-off', ttl_of($key), 120);

echo "connection errors (the only thing worth retrying)\n";
delete_transient($key); $GLOBALS['http']=array(new WP_Error('http','timeout')); $GLOBALS['calls']=0;
$r = DS_Programs_Data::get(array($site));
chk('three attempts on a connection-level failure', $GLOBALS['calls'], 3);
chk('then backs off like any other failure', ttl_of($key), 120);
chk('and serves stale', $r['stale'], true);
$led = DS_Programs_Data::ledger();
chk('ledger: failed entry records all 3 requests', array($led[0]['ok'],$led[0]['tries'],$led[0]['code']), array(false,3,0));
chk('ledger: reason is cache expired (stale copy existed)', $led[0]['why'], 'expired');

echo "other 4xx\n";
delete_transient($key); $GLOBALS['http']=array(array('code'=>400,'body'=>'','headers'=>array())); $GLOBALS['calls']=0;
DS_Programs_Data::get(array($site));
chk('one call, no retry into a deterministic 4xx', $GLOBALS['calls'], 1);


echo "refetch lock held AND a stale copy exists (no sleep, no HTTP)\n";
$GLOBALS['tr']=array(); $GLOBALS['calls']=0;
$k='ds_programs_'.md5(json_encode(array('46287')));
set_transient($k.'_stale', array('rows'=>array(array('programId'=>1,'name'=>'X','type'=>'LEAGUE','visibility'=>'Public','startTime'=>1700000000000,'endTime'=>1700000000000)),'fetched'=>1), 999);
set_transient($k.'_lock', time(), 30);
$t0=microtime(true); $r=DS_Programs_Data::get(array($site));
chk('zero HTTP calls', $GLOBALS['calls'], 0);
chk('stale copy served', $r['stale'], true);
chk('returned immediately, no 1.5s wait', microtime(true)-$t0 < 0.5, true);

echo "refetch lock held by another request (waits ~1.5s)\n";
delete_transient($key); set_transient($key.'_lock', time(), 30); $GLOBALS['http']=array($ok200); $GLOBALS['calls']=0;
$r = DS_Programs_Data::get(array($site));
chk('zero HTTP calls while another request holds the lock', $GLOBALS['calls'], 0);
chk('served the stale copy instead', $r['stale'], true);
delete_transient($key.'_lock');

echo "flush()\n";
$GLOBALS['http']=array($ok200); DS_Programs_Data::get(array($site));
set_transient($key.'_lock', time(), 30);
$GLOBALS['cache_flushed']=0;
DS_Programs_Data::flush();
chk('fresh copy gone', get_transient($key), false);
chk('refetch lock gone', get_transient($key.'_lock'), false);
chk('stale copy KEPT so a flush during an outage cannot blank the page', get_transient($key.'_stale') !== false, true);
chk('does NOT empty the whole object cache', $GLOBALS['cache_flushed'], 0);
chk('still runs the legacy row delete', count($wpdb->queries), 1);
$GLOBALS['http']=array($ok200); DS_Programs_Data::get(array($site));
chk('fetch after flush() is logged as a manual refresh', DS_Programs_Data::ledger()[0]['why'], 'flush');
$GLOBALS['http']=array($ok200); delete_transient($key); DS_Programs_Data::get(array($site));
chk('a plain expiry after that is logged as expired again', DS_Programs_Data::ledger()[0]['why'], 'expired');

echo "ledger bounds\n";
for ($i=0;$i<70;$i++){ delete_transient($key); DS_Programs_Data::get(array($site)); }
chk('recent list capped at 50 entries', count(DS_Programs_Data::ledger()), 50);
$sum = DS_Programs_Data::ledger_summary();
chk('daily counters are exact past the cap (requests == every HTTP call made)', $sum['requests'], $GLOBALS['calls_total']);
chk('failures counted', $sum['failures'] >= 4, true);
chk('over budget flagged when fetches exceed 1.25 x 144', $sum['over'], $sum['fetches'] > 180);
chk('two configured sites double the budget', (function() use ($site){ $GLOBALS['opts']['ds_toolkit_settings']=array('leagueapps_sites'=>array($site,array('site_id'=>'1','api_key'=>'k','label'=>''))); $b=DS_Programs_Data::ledger_summary()['budget']; $GLOBALS['opts']['ds_toolkit_settings']=array('leagueapps_sites'=>array($site)); return $b; })(), 288);
$book = get_option('ds_programs_ledger');
chk('hour buckets pruned to a day', max(array_keys($book['hours'])) >= gmdate('YmdH', time()-86400), true);

echo "main programs with sub-programs (grouping)\n";
$GLOBALS['tr']=array();
$grp = json_encode(array(
  array('programId'=>10,'isMaster'=>true,'name'=>'Winter College Camps','type'=>'EVENT','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1799000000000,'endTime'=>1799100000000,'endRegistrationTime'=>1799000000000,
        'registerUrlHtml'=>'//x.leagueapps.com/registration/init?bid=10','programUrlHtml'=>'//x.leagueapps.com/events/10-winter'),
  array('programId'=>11,'masterProgramId'=>10,'name'=>'D2 Impact Camp','type'=>'EVENT','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1799000000000,'endTime'=>1799000000000,'individualFee'=>30,'programUrlHtml'=>'//x.leagueapps.com/events/11-d2'),
  array('programId'=>12,'masterProgramId'=>10,'name'=>'D3 Elite Camp','type'=>'EVENT','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1799050000000,'endTime'=>1799050000000,'individualFee'=>60,'programUrlHtml'=>'//x.leagueapps.com/events/12-d3'),
  array('programId'=>21,'masterProgramId'=>20,'name'=>'Orphan Child','type'=>'CAMP','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1799000000000,'endTime'=>1799000000000,'individualFee'=>900,'registerUrlHtml'=>'//x.leagueapps.com/registration/init?bid=21','programUrlHtml'=>'//x.leagueapps.com/camps/21-orphan'),
  array('programId'=>30,'name'=>'Standalone Clinic','type'=>'CLINIC','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1799000000000,'endTime'=>1799000000000,'individualFee'=>50,'programUrlHtml'=>'//x.leagueapps.com/clinics/30-solo'),
  array('programId'=>40,'isMaster'=>true,'name'=>'Priced Master','type'=>'CAMP','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1799000000000,'endTime'=>1799000000000,'individualFee'=>200,'programUrlHtml'=>'//x.leagueapps.com/camps/40-priced'),
  array('programId'=>41,'masterProgramId'=>40,'name'=>'Session A','type'=>'CAMP','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1799000000000,'endTime'=>1799000000000,'individualFee'=>25,'programUrlHtml'=>'//x.leagueapps.com/camps/41-a'),
  array('programId'=>42,'masterProgramId'=>40,'name'=>'Session B','type'=>'CAMP','mode'=>'YOUTH','state'=>'UPCOMING','visibility'=>'Public',
        'startTime'=>1799000000000,'endTime'=>1799000000000,'individualFee'=>25,'programUrlHtml'=>'//x.leagueapps.com/camps/42-b'),
));
$GLOBALS['http']=array(array('code'=>200,'body'=>$grp,'headers'=>array()));
$ids = function($r){ return array_map(function($p){ return $p['programId']; }, $r['programs']); };
$by  = function($r,$id){ foreach($r['programs'] as $p){ if($p['programId']===$id) return $p; } return null; };

$r = DS_Programs_Data::get(array($site));
chk('default (no 4th arg): children, orphan, standalone; no master rows', $ids($r), array(11,12,21,30,41,42));
chk('default: child row carries the master name as Program', $by($r,11)['program'], 'Winter College Camps');
chk('default: child row carries its own name as Age Group', $by($r,11)['ageGroup'], 'D2 Impact Camp');
chk('default: no row is flagged isMaster', array_sum(array_map(function($p){ return (int)!empty($p['isMaster']); }, $r['programs'])), 0);
chk('"children" explicitly = the default', $ids(DS_Programs_Data::get(array($site), array(), array(), 'children')), array(11,12,21,30,41,42));
chk('unknown grouping value falls back to children', $ids(DS_Programs_Data::get(array($site), array(), array(), 'bogus')), array(11,12,21,30,41,42));

$r = DS_Programs_Data::get(array($site), array(), array(), 'master');
chk('master: the two masters, the orphan and the standalone; children folded', $ids($r), array(10,21,30,40));
$m = $by($r,10);
chk('master row: Program is its own name', $m['program'], 'Winter College Camps');
chk('master row: Age Group blank', $m['ageGroup'], '');
chk('master row: flagged isMaster with its child count', array($m['isMaster'],$m['children']), array(true,2));
chk('master row: button goes to the master program page', DS_Programs_Data::button_url($m), 'https://x.leagueapps.com/events/10-winter');
chk('master row: registerUrl is the master checkout link', $m['registerUrl'], 'https://x.leagueapps.com/registration/init?bid=10');
chk('master row: price is the spread of its children when its own fee is blank', $m['price'], '$30-$60');
chk('master row: derived status OPEN (register link, registration not ended)', $m['statusRaw'], 'OPEN');
chk('master with its own fee keeps it, children ignored', $by($r,40)['price'], '$200');
chk('orphan child (master not in feed) still lists as itself', array($by($r,21)['program'],$by($r,21)['isMaster']), array('Orphan Child',false));
chk('master mode: standalone unaffected', $by($r,30)['price'], '$50');

$r = DS_Programs_Data::get(array($site), array(), array(), 'both');
chk('both: masters and children all listed', $ids($r), array(10,11,12,21,30,40,41,42));
chk('both: master and its children share a groupKey', array($by($r,10)['groupKey'],$by($r,11)['groupKey'],$by($r,12)['groupKey']), array(10,10,10));
chk('both: a child listed under its master carries parentId', array($by($r,11)['parentId'],$by($r,12)['parentId']), array(10,10));
chk('both: the master itself, an orphan and a standalone have no parentId', array($by($r,10)['parentId'],$by($r,21)['parentId'],$by($r,30)['parentId']), array(0,0,0));
chk('children mode: parentId is never set (nothing to nest under)', array_sum(array_map(function($p){ return (int)!empty($p['parentId']); }, DS_Programs_Data::get(array($site), array(), array(), 'children')['programs'])), 0);
chk('both: equal child fees collapse to one price on the master', (function() use ($grp,$site){ return null; })() ?? $by($r,40)['price'], '$200');
$GLOBALS['http']=array(array('code'=>200,'body'=>json_encode(array(
  array('programId'=>50,'isMaster'=>true,'name'=>'Same Fee Master','type'=>'CAMP','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/50'),
  array('programId'=>51,'masterProgramId'=>50,'name'=>'A','type'=>'CAMP','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'individualFee'=>25,'programUrlHtml'=>'//x/51'),
  array('programId'=>52,'masterProgramId'=>50,'name'=>'B','type'=>'CAMP','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'individualFee'=>25,'programUrlHtml'=>'//x/52'),
  array('programId'=>60,'isMaster'=>true,'name'=>'No Fee Anywhere','type'=>'CAMP','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/60'),
  array('programId'=>61,'masterProgramId'=>60,'name'=>'C','type'=>'CAMP','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/61'),
)),'headers'=>array())); delete_transient($key);
$r = DS_Programs_Data::get(array($site), array(), array(), 'master');
chk('children with one identical fee: master shows that single price', $by($r,50)['price'], '$25');
chk('no fee on master or children: price blank', $by($r,60)['price'], '');

$GLOBALS['http']=array(array('code'=>200,'body'=>json_encode(array(
  array('programId'=>70,'isMaster'=>true,'name'=>'Girls Series','type'=>'CAMP','gender'=>'ANY','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/70'),
  array('programId'=>71,'masterProgramId'=>70,'name'=>'A','type'=>'CAMP','gender'=>'FEMALE','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/71'),
  array('programId'=>72,'masterProgramId'=>70,'name'=>'B','type'=>'CAMP','gender'=>'FEMALE','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/72'),
  array('programId'=>80,'isMaster'=>true,'name'=>'Mixed Series','type'=>'CAMP','gender'=>'ANY','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/80'),
  array('programId'=>81,'masterProgramId'=>80,'name'=>'A','type'=>'CAMP','gender'=>'FEMALE','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/81'),
  array('programId'=>82,'masterProgramId'=>80,'name'=>'B','type'=>'CAMP','gender'=>'MALE','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/82'),
  array('programId'=>90,'isMaster'=>true,'name'=>'Set On Master','type'=>'CAMP','gender'=>'MALE','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/90'),
  array('programId'=>91,'masterProgramId'=>90,'name'=>'A','type'=>'CAMP','gender'=>'FEMALE','visibility'=>'Public','startTime'=>1799000000000,'endTime'=>1799000000000,'programUrlHtml'=>'//x/91'),
)),'headers'=>array())); delete_transient($key);
$r = DS_Programs_Data::get(array($site), array(), array(), 'master');
chk('master gender ANY, every child FEMALE: row says Girls', $by($r,70)['gender'], 'Girls');
chk('master gender ANY, children disagree: row keeps Coed', $by($r,80)['gender'], 'Coed');
chk('master gender set: its own value wins over the children', $by($r,90)['gender'], 'Boys');
$r = DS_Programs_Data::get(array($site), array(), array(), 'children');
chk('children mode: a child keeps its own gender (unchanged path)', $by($r,71)['gender'], 'Girls');

echo "age rank: school grades\n";
chk('"3rd-6th Grade: Sept 25th @ Windsor" ranks 3', DS_Programs_Data::age_rank('3rd-6th Grade: Sept 25th @ Windsor High School'), 3);
chk('"7th & 8th Grade" ranks 7', DS_Programs_Data::age_rank('7th & 8th Grade: Oct 4th'), 7);
chk('"12th Grade" ranks 12', DS_Programs_Data::age_rank('12th Grade Boys'), 12);
chk('"1st-4th Grade" ranks 1', DS_Programs_Data::age_rank('1st-4th Grade'), 1);
chk('U-forms still win: "U9" is 9', DS_Programs_Data::age_rank('U9'), 9);
chk('"10u" still 10', DS_Programs_Data::age_rank('10u Boys'), 10);
chk('no age still last', DS_Programs_Data::age_rank('Adult Open'), 999);

echo "\n" . ($fails ? "$fails FAILED" : "all passed") . "\n";
exit($fails ? 1 : 0);
