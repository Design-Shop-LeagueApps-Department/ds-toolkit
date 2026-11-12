<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * HQ Link is its own feature (audit 2026-11, item 25): the check-in timer, the alert copy and the update
 * channel start under "Report to Design Shop HQ", with or without Tripwire. Before, only Tripwire started
 * them, so a site with Tripwire off went Silent on HQ and stopped taking toolkit updates. Stub-style:
 *
 *   php tests/hq-link-own-feature-test.php
 */
error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DS_TOOLKIT_ADMIN_DOMAIN', '@leagueapps.com' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['hooks'] = array(); $GLOBALS['o'] = array(); $GLOBALS['sched'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function apply_filters( $h, $v ) { return $v; }
function is_admin() { return true; }
function wp_doing_cron() { return false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['o'] ) ? $GLOBALS['o'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['o'][ $k ] = $v; return true; }
function wp_next_scheduled( $h ) { return $GLOBALS['sched'][ $h ] ?? false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['sched'][ $h ] = $t; return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['sched'][ $h ] ); return 0; }
function wp_rand( $a = 0, $b = 0 ) { return (int) $a; }
require DS_TOOLKIT_PATH . 'includes/class-ds-features.php';
require DS_TOOLKIT_PATH . 'features/class-ds-hq-link.php';

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };

echo "the loader starts it under its own setting\n";
$reg = DS_Features::registry();
$is( 'tripwire_hq_report is a feature: DS_HQ_Link in features/class-ds-hq-link.php', $reg['tripwire_hq_report'] ?? null, array( 'file' => 'features/class-ds-hq-link.php', 'class' => 'DS_HQ_Link' ) );
$is( 'on by default on every blueprint', array( DS_Features::defaults( 0 )['tripwire_hq_report'] ?? null, DS_Features::defaults( 7 )['tripwire_hq_report'] ?? null ), array( 1, 1 ) );
$keys = array_keys( $reg );
$is( 'loaded after Tripwire, as when Tripwire started it', array_search( 'tripwire_hq_report', $keys, true ) > array_search( 'tripwire_enabled', $keys, true ), true );
$is( 'it has the constructor and init() the loader calls', array( method_exists( 'DS_HQ_Link', '__construct' ), method_exists( 'DS_HQ_Link', 'init' ) ), array( true, true ) );

echo "reporting off: nothing starts\n";
( new DS_HQ_Link( array( 'tripwire_enabled' => 1, 'tripwire_hq_report' => 0 ) ) )->init();
$is( 'no hook, no timer', array( count( $GLOBALS['hooks'] ), count( $GLOBALS['sched'] ) ), array( 0, 0 ) );

echo "Tripwire OFF, reporting on: it still starts\n";
( new DS_HQ_Link( array( 'tripwire_enabled' => 0, 'tripwire_hq_report' => 1 ) ) )->init();
$h = $GLOBALS['hooks'];
$is( 'the check-in timer is handled', isset( $h[ DS_HQ_Link::CHECKIN_HOOK ] ), true );
$is( 'queued reports are sent', isset( $h[ DS_HQ_Link::FLUSH_HOOK ] ), true );
$is( 'a site with no key enrolls (handler bound, and scheduled a minute from now)', array( isset( $h[ DS_HQ_Link::ENROLL_HOOK ] ), isset( $GLOBALS['sched'][ DS_HQ_Link::ENROLL_HOOK ] ) ), array( true, true ) );
$is( 'the toolkit update channel is up (DS_HQ_Update hooks its run)', class_exists( 'DS_HQ_Update' ) && isset( $h[ DS_HQ_Update::HOOK ] ), true );
$is( 'HQ can still reach the site (REST routes for proof and check-in-now)', isset( $h['rest_api_init'] ), true );
$is( 'it listens for Tripwire events, for when Tripwire is on', array( isset( $h['ds_tripwire_checked'] ), isset( $h['ds_tripwire_alert'] ) ), array( true, true ) );
$count = array_sum( array_map( 'count', $GLOBALS['hooks'] ) );
( new DS_HQ_Link( array( 'tripwire_hq_report' => 1 ) ) )->init();
$is( 'starting it twice adds nothing', array_sum( array_map( 'count', $GLOBALS['hooks'] ) ), $count );

echo "Tripwire no longer starts it\n";
$tw = file_get_contents( DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php' ) . implode( '', array_map( 'file_get_contents', glob( DS_TOOLKIT_PATH . 'features/tripwire/*.php' ) ) ); // the class and its parts
$is( 'DS_Tripwire::init() does not boot HQ Link', false === strpos( $tw, 'DS_HQ_Link::boot' ), true );
$core = file_get_contents( DS_TOOLKIT_PATH . 'includes/class-ds-toolkit.php' );
preg_match( '/Reporting to Design Shop HQ switched off.*?DS_HQ_Link::unschedule\(\);/s', $core, $m );
$is( 'the loader clears HQ timers only when reporting itself is off, not when Tripwire is', isset( $m[0] ) && false === strpos( $m[0], "empty( \$settings['tripwire_enabled'] )" ), true );

echo $fails ? "$fails of $n FAILED\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
