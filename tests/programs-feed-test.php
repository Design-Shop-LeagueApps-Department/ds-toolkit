<?php
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

echo "register button URL\n";
$club = $r['programs'][0]; $camp = $r['programs'][1];
chk('club team: data layer keeps the checkout link as registerUrl', $club['registerUrl'], 'https://lamorugby.leagueapps.com/registration/init?bid=1');
chk('club team: programUrl is the program page', $club['programUrl'], 'https://lamorugby.leagueapps.com/clubteams/1-u8');
chk('club team: BUTTON goes to the program page', DS_Programs_Data::button_url($club), 'https://lamorugby.leagueapps.com/clubteams/1-u8');
chk('camp: button goes to the program page too', DS_Programs_Data::button_url($camp), 'https://dsstormbasketball.leagueapps.com/camps/2-camp');
chk('no page URL: falls back to the registration link', DS_Programs_Data::button_url(array('programUrl'=>'','registerUrl'=>'https://x/reg')), 'https://x/reg');
chk('neither: empty', DS_Programs_Data::button_url(array()), '');

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

echo "other 4xx\n";
delete_transient($key); $GLOBALS['http']=array(array('code'=>400,'body'=>'','headers'=>array())); $GLOBALS['calls']=0;
DS_Programs_Data::get(array($site));
chk('one call, no retry into a deterministic 4xx', $GLOBALS['calls'], 1);

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

echo "\n" . ($fails ? "$fails FAILED" : "all passed") . "\n";
exit($fails ? 1 : 0);
