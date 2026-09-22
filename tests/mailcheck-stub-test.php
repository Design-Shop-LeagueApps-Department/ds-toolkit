<?php
define('ABSPATH', '/tmp/fake-wp/');
define('HOUR_IN_SECONDS',3600); define('DAY_IN_SECONDS',86400);
$GLOBALS['opts']=array(); $GLOBALS['mailed']=array(); $GLOBALS['acts']=array(); $GLOBALS['fail']=false;
function get_option($k,$d=''){ return array_key_exists($k,$GLOBALS['opts'])?$GLOBALS['opts'][$k]:$d; }
function update_option($k,$v,$a=true){ $GLOBALS['opts'][$k]=$v; return true; }
function add_option($k,$v,$d='',$a=true){ if(array_key_exists($k,$GLOBALS['opts'])) return false; $GLOBALS['opts'][$k]=$v; return true; }
function is_email($e){ return (bool)filter_var($e,FILTER_VALIDATE_EMAIL); }
function home_url($p=''){ return 'https://example.org'.$p; }
function wp_parse_url($u,$c=-1){ return $c===-1?parse_url($u):parse_url($u,$c); }
function add_action($h,$c,$p=10,$n=1){ $GLOBALS['acts'][$h][]=$c; }
function remove_action($h,$c,$p=10){ unset($GLOBALS['acts'][$h]); }
function add_filter($h,$c,$p=10,$n=1){}
function apply_filters($h,$v){ return $v; }
function wp_doing_cron(){ return true; }
function is_admin(){ return false; }
function wp_next_scheduled($h){ return false; }
function wp_schedule_event(){ return true; }
function current_time($t){ return time(); }
function wp_rand($a,$b){ return $a; }
function is_wp_error($e){ return $e instanceof WP_Error; }
class WP_Error { private $m; function __construct($m=''){$this->m=$m;} function get_error_message(){return $this->m;} }
function wp_mail($to,$s,$b,$h=''){
  $GLOBALS['mailed'][]=array($to,$s,$b);
  if ($GLOBALS['fail']) {
    foreach (($GLOBALS['acts']['wp_mail_failed'] ?? array()) as $cb) { $cb(new WP_Error('Could not instantiate mail function.')); }
    return false;
  }
  return true;
}
require __DIR__ . '/../features/class-ds-tripwire.php';
$t = new DS_Tripwire(array());

function chk($l,$g,$w){ printf("  %-50s %s\n",$l,$g===$w?'ok':"FAIL got=".var_export($g,true)); }

$t->run_mailcheck();
chk('no options -> no mail', count($GLOBALS['mailed']), 0);
chk('no options -> no result row', isset($GLOBALS['opts']['ds_mailcheck_result']), false);

$GLOBALS['opts']=array('ds_mailcheck_to'=>'bad','ds_mailcheck_token'=>'MT1');
$t->run_mailcheck();
chk('invalid recipient -> no mail', count($GLOBALS['mailed']), 0);

$GLOBALS['opts']=array('ds_mailcheck_to'=>'x@y.com','ds_mailcheck_token'=>'MT9');
$t->run_mailcheck();
chk('valid -> exactly one mail', count($GLOBALS['mailed']), 1);
chk('subject = marker host token', $GLOBALS['mailed'][0][1], '[DS MAILTEST] example.org MT9');
$r=$GLOBALS['opts']['ds_mailcheck_result'];
chk('accepted=true', $r['accepted'], true);
chk('error empty', $r['error'], '');
chk('token recorded', $r['token'], 'MT9');
chk('context=cron', $r['context'], 'cron');

$GLOBALS['fail']=true; $GLOBALS['mailed']=array();
$GLOBALS['opts']=array('ds_mailcheck_to'=>'x@y.com','ds_mailcheck_token'=>'MT10');
$t->run_mailcheck();
$r=$GLOBALS['opts']['ds_mailcheck_result'];
chk('failure -> accepted=false', $r['accepted'], false);
chk('failure -> reason captured', $r['error'], 'Could not instantiate mail function.');
chk('hook constant', DS_Tripwire::MAILCHECK_HOOK, 'ds_tripwire_mailcheck');

// the cron race: a second run with the same token must send nothing
$GLOBALS['fail']=false; $GLOBALS['mailed']=array();
$GLOBALS['opts']=array('ds_mailcheck_to'=>'x@y.com','ds_mailcheck_token'=>'MT77');
$t->run_mailcheck();
$first=count($GLOBALS['mailed']);
$t->run_mailcheck();
chk('same token twice -> only one send', count($GLOBALS['mailed']), $first);
chk('first run did send', $first, 1);
