<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * The Status card and the HQ check-in fields (audit 2026-11, item 18): DS_Status reads what the features already
 * record and says whether the background work is healthy. Stub-style:
 *
 *   php tests/status-test.php
 */
error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DS_TOOLKIT_ADMIN_DOMAIN', '@leagueapps.com' );
define( 'DS_TOOLKIT_VERSION', '9.9.9' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['o'] = array(); $GLOBALS['backend'] = '';
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o'] ) ? $GLOBALS['o'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['o'][ $k ] = $v; return true; }
function human_time_diff( $from, $to = 0 ) { $m = (int) round( abs( $to - $from ) / 60 ); return $m >= 2880 ? round( $m / 1440 ) . ' days' : ( $m >= 120 ? round( $m / 60 ) . ' hours' : $m . ' mins' ); }
function number_format_i18n( $n ) { return number_format( $n ); }
function home_url() { return 'https://example.test'; }
function wp_login_url() { return 'https://example.test/wp-login.php'; }
function add_action() {} function add_filter() {}
function apply_filters( $h, $v ) { return $v; }
class DS_Bot_Shield { public function __construct( $o = array() ) {} public function backend_name() { return $GLOBALS['backend']; } }
require DS_TOOLKIT_PATH . 'includes/class-ds-status.php';

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };
$row = function ( array $rows, $label ) { foreach ( $rows as $r ) { if ( $r[0] === $label ) { return $r; } } return null; };
$now = 1800000000;

echo "a site where nothing has run yet\n";
$rows = DS_Status::rows( array(), $now );
$is( 'daily check: has not run, to check', $row( $rows, 'Tripwire daily check' )[1], 'warn' );
$is( 'hourly scan: has not run, to check', $row( $rows, 'Tripwire hourly scan' )[1], 'warn' );
$is( 'remote lists: bundled lists in use', $row( $rows, 'Remote lists' )[1], 'warn' );
$is( 'no alert email is not a problem', $row( $rows, 'Last alert email' )[1], 'ok' );
$is( 'update check: none recorded', $row( $rows, 'Update check' )[1], 'warn' );
$is( 'Bot Shield off reads as off', $row( $rows, 'Bot Shield' )[1], 'off' );

echo "a healthy site\n";
$GLOBALS['o']['ds_tripwire_state'] = array(
	'last_run' => $now - 7200,
	'content'  => array(
		'last_run' => $now - 600,
		'error'    => '',
		'remote'   => array( 'allow' => array( 'status' => 'fetched', 'count' => 12 ), 'deny' => array( 'status' => 'cached', 'count' => 3 ) ),
		'stats'    => array( 'scanned' => 1234, 'total' => 13770, 'recent' => 40, 'run_s' => 18.9, 'ceiling' => 25, 'peak_mb' => 61.5 ),
	),
);
$GLOBALS['o']['ds_tripwire_last_notify'] = array( 'time' => gmdate( 'c', $now - 86400 ), 'tier' => 'HIGH', 'accepted' => true );
DS_Status::note_update_check( true, 'v1.17.4' );
$GLOBALS['o'][ DS_Status::UPDATE_CHECK_OPT ]['time'] = $now - 3600;
$GLOBALS['backend'] = 'object-cache';
$opts = array( 'bot_shield_enabled' => 1 );
$rows = DS_Status::rows( $opts, $now );
$is( 'every row is ok', array_values( array_unique( array_column( $rows, 1 ) ) ), array( 'ok' ) );
$is( 'the hourly row carries the scan numbers', $row( $rows, 'Tripwire hourly scan' )[2], 'Last ran 10 mins ago: 1,234 of 13,770 files read in 18.9 s (limit 25 s), 61.5 MB peak.' );
$is( 'the lists row names both lists', $row( $rows, 'Remote lists' )[2], 'allow: fetched (12); deny: cached (3).' );
$is( 'accepted mail says accepted is not delivered', false !== strpos( $row( $rows, 'Last alert email' )[2], 'Accepted is not the same as delivered' ), true );
$is( 'the update row names the release', $row( $rows, 'Update check' )[2], 'Last checked 60 mins ago: latest release v1.17.4.' );
$is( 'no row holds markup (the view escapes plain text)', (bool) preg_grep( '/[<>]/', array_column( $rows, 2 ) ), false );

echo "things that are wrong are said\n";
$GLOBALS['backend'] = '';
$r = $row( DS_Status::rows( $opts, $now ), 'Bot Shield' );
$is( 'Bot Shield with no cache backend is not shown as a quiet day', array( $r[1], false !== strpos( $r[2], 'No cache backend' ) ), array( 'warn', true ) );
$GLOBALS['o']['ds_tripwire_last_notify']['accepted'] = false;
$is( 'a refused alert mail is a problem', $row( DS_Status::rows( $opts, $now ), 'Last alert email' )[1], 'bad' );
$GLOBALS['o']['ds_tripwire_last_skip'] = array( 'time' => gmdate( 'c', $now - 60 ), 'why' => 'HQ took it' );
$is( 'a later alert that was not emailed is mentioned', false !== strpos( $row( DS_Status::rows( $opts, $now ), 'Last alert email' )[2], 'was not emailed' ), true );
$GLOBALS['o']['ds_tripwire_state']['content']['remote']['allow']['status'] = 'fail (http 503), using last good';
$is( 'a failed list fetch is flagged', $row( DS_Status::rows( $opts, $now ), 'Remote lists' )[1], 'warn' );
$GLOBALS['o']['ds_tripwire_state']['last_run'] = $now - 3 * 86400;
$is( 'a daily run three days old is a problem', $row( DS_Status::rows( $opts, $now ), 'Tripwire daily check' )[1], 'bad' );
$GLOBALS['o']['ds_tripwire_state']['content']['last_run'] = $now - 5 * 3600;
$is( 'an hourly scan five hours old is a problem', $row( DS_Status::rows( $opts, $now ), 'Tripwire hourly scan' )[1], 'bad' );
$GLOBALS['o']['ds_tripwire_state']['content']['last_run'] = $now - 600;
$GLOBALS['o']['ds_tripwire_state']['content']['error']    = 'time ran out';
$is( 'a scan error is a problem and is quoted', array( $row( DS_Status::rows( $opts, $now ), 'Tripwire hourly scan' )[1], false !== strpos( $row( DS_Status::rows( $opts, $now ), 'Tripwire hourly scan' )[2], 'time ran out' ) ), array( 'bad', true ) );
DS_Status::note_update_check( false );
$is( 'a failed update check is a problem', $row( DS_Status::rows( $opts, $now ), 'Update check' )[1], 'bad' );
$off = DS_Status::rows( array( 'tripwire_enabled' => 0 ), $now );
$is( 'Tripwire off: both runs read off, no list or mail rows', array( $row( $off, 'Tripwire daily check' )[1], $row( $off, 'Tripwire hourly scan' )[1], $row( $off, 'Remote lists' ), $row( $off, 'Last alert email' ) ), array( 'off', 'off', null, null ) );

echo "the HQ check-in\n";
$hq = DS_Status::hq_fields( $opts );
$is( 'every value is a flat scalar (HQ drops arrays)', array_filter( $hq, function ( $v ) { return ! is_scalar( $v ); } ), array() );
$is( 'no value is longer than the 200 characters HQ keeps', max( array_map( function ( $v ) { return strlen( (string) $v ); }, $hq ) ) <= 200, true );
$is( 'it carries list health, mail, update check and Bot Shield', array( $hq['lists_ok'], $hq['last_mail_accepted'], $hq['update_check_ok'], $hq['bot_shield'] ), array( 0, 0, 0, 'none' ) );
$is( 'and the scan numbers', array( $hq['content_recent'], $hq['content_run_s'], $hq['content_peak_mb'] ), array( 40, 18.9, 61.5 ) );
require DS_TOOLKIT_PATH . 'features/class-ds-hq-link.php';
$m = new ReflectionMethod( 'DS_HQ_Link', 'payload' );
$m->setAccessible( true );
$GLOBALS['o']['ds_toolkit_settings'] = $opts;
$p   = $m->invoke( null, 'checkin', array() );
$old = array( 'enabled', 'content_enabled', 'last_run', 'content_last_run', 'content_scanned', 'content_total', 'content_error', 'last_mail', 'last_mail_tier' );
$is( 'the payload keeps the top-level keys HQ reads', array_keys( $p ), array( 'v', 'kind', 'site_url', 'login_url', 'platform', 'toolkit', 'update', 'tripwire', 'findings' ) );
$is( 'every tripwire key HQ already reads is still there', array_values( array_diff( $old, array_keys( $p['tripwire'] ) ) ), array() );
$is( 'the old keys keep their own values', array( $p['tripwire']['enabled'], $p['tripwire']['content_scanned'], $p['tripwire']['content_total'], $p['tripwire']['last_mail_tier'] ), array( 1, 1234, 13770, 'HIGH' ) );
$is( 'the new fields ride in the same flat map', array_values( array_diff( array_keys( $hq ), array_keys( $p['tripwire'] ) ) ), array() );
$is( 'still all scalars', array_filter( $p['tripwire'], function ( $v ) { return ! is_scalar( $v ); } ), array() );

echo "the pieces are wired\n";
$up = file_get_contents( DS_TOOLKIT_PATH . 'includes/class-ds-toolkit-updater.php' );
$is( 'the updater records a failed and a good check', array( substr_count( $up, 'DS_Status::note_update_check( false )' ), substr_count( $up, 'DS_Status::note_update_check( true' ) ), array( 1, 1 ) );
$view = file_get_contents( DS_TOOLKIT_PATH . 'admin/views/page-settings.php' );
$is( 'the card is drawn only when there are rows, with no inline style', array( false !== strpos( $view, "! empty( \$dst_health )" ), substr_count( $view, 'style=' ) ), array( true, 0 ) );
$adm = file_get_contents( DS_TOOLKIT_PATH . 'admin/class-ds-toolkit-admin.php' );
$is( 'rows are built for staff only', (bool) preg_match( '/if \( \$this->is_leagueapps_user\(\) \) \{\s+\$dst_health\s+= DS_Status::rows/', $adm ), true );
$bs = file_get_contents( DS_TOOLKIT_PATH . 'features/class-ds-bot-shield.php' );
$is( 'Bot Shield can name its backend', false !== strpos( $bs, 'public function backend_name()' ), true );

echo "\n" . ( $fails ? "$fails of $n FAILED" : "all $n passed" ) . "\n";
exit( $fails ? 1 : 0 );
