<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * When sites scan and report (audit 2026-11, item 11): every site has its own fixed minute for the daily
 * Tripwire run instead of 03:10 for all, and a report HQ could not take is retried after a growing wait
 * instead of every 5 minutes. Stub-style:
 *
 *   php tests/hq-spread-test.php
 */
error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );
require DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php';
require DS_TOOLKIT_PATH . 'features/class-ds-hq-link.php';

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };

echo "the daily run\n";
$hosts = array(); for ( $i = 0; $i < 1200; $i++ ) { $hosts[] = 'club' . $i . '.example.org'; }
$offs  = array_map( array( 'DS_Tripwire', 'daily_offset' ), $hosts );
$is( 'a site keeps the same offset every day', DS_Tripwire::daily_offset( 'surfsidevolleyball.com' ), DS_Tripwire::daily_offset( 'SurfsideVolleyball.com' ) );
$is( 'every offset is inside the window (0 to ' . ( DS_Tripwire::DAILY_SPREAD / 60 ) . ' min) and never a whole hour', array( min( $offs ) >= 0, max( $offs ) <= DS_Tripwire::DAILY_SPREAD + 60, count( array_filter( $offs, function ( $o ) { return 0 === $o % 3600; } ) ) ), array( true, true, 0 ) );
$per_min = array_count_values( array_map( function ( $o ) { return intdiv( $o, 60 ); }, $offs ) );
$is( '1,200 sites: no minute holds more than 3% of them (it was 100% at 03:10)', max( $per_min ) <= 36, true );
$is( '... and they use most of the window', count( $per_min ) >= 80, true );

// New York, 9 Nov 2026 14:00 local (UTC-5): first run = 10 Nov 03:10 local + offset = 08:10 UTC + offset.
$tz    = -5 * 3600;
$local = gmmktime( 14, 0, 0, 11, 9, 2026 ); // the site's clock reading, as current_time( 'timestamp' ) gives it
$first = DS_Tripwire::daily_first_run( $local, $tz, 'example-club.org' );
$off   = DS_Tripwire::daily_offset( 'example-club.org' );
$is( 'first run: tomorrow 03:10 site time plus the site offset, as a UTC time', $first, gmmktime( 8, 10, 0, 11, 10, 2026 ) + $off );
$is( 'a slot at exactly ten past (the old fixed time) is recognised', DS_Tripwire::is_legacy_daily_slot( gmmktime( 8, 10, 0, 11, 10, 2026 ), $tz ), true );
$is( '... also after a clock change moved it by an hour', DS_Tripwire::is_legacy_daily_slot( gmmktime( 9, 10, 0, 11, 10, 2026 ), $tz ), true );
$is( '... and in a half-hour time zone (India, +5:30)', DS_Tripwire::is_legacy_daily_slot( gmmktime( 21, 40, 0, 11, 9, 2026 ), (int) ( 5.5 * 3600 ) ), true );
$is( 'a site already on its own minute is not moved again', DS_Tripwire::is_legacy_daily_slot( $first, $tz ), false );
$moved = 0; foreach ( $hosts as $h ) { if ( DS_Tripwire::is_legacy_daily_slot( DS_Tripwire::daily_first_run( $local, $tz, $h ), $tz ) ) { $moved++; } }
$is( 'none of 1,200 new slots can be mistaken for the old one (no endless re-scheduling)', $moved, 0 );

echo "retry after HQ could not take a report\n";
$is( 'first retry after 5 minutes', DS_HQ_Link::retry_delay( 1 ), 300 );
$is( 'then 10, 20, 40 minutes', array( DS_HQ_Link::retry_delay( 2 ), DS_HQ_Link::retry_delay( 3 ), DS_HQ_Link::retry_delay( 4 ) ), array( 600, 1200, 2400 ) );
$is( 'never longer than 6 hours', array( DS_HQ_Link::retry_delay( 8 ), DS_HQ_Link::retry_delay( 12 ), DS_HQ_Link::retry_delay( 500 ) ), array( 21600, 21600, 21600 ) );
$is( 'a missing or zero count behaves like the first failure', array( DS_HQ_Link::retry_delay( 0 ), DS_HQ_Link::retry_delay( -3 ) ), array( 300, 300 ) );

echo $fails ? "$fails of $n FAILED\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
