<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Schedule is deprecated (1.10.44): it must not load, and its switch must not be
 * offered, whatever a site saved. Stub-style:  php tests/la-schedule-deprecated-test.php
 */
$src   = file_get_contents( dirname( __DIR__ ) . '/includes/class-ds-toolkit.php' );
$fails = 0;
$check = function ( $ok, $msg ) use ( &$fails ) { if ( ! $ok ) { $fails++; echo "FAIL: $msg\n"; } };
$check( 0 === preg_match( "/'ds_la_schedule_module_enabled'\s*=>\s*array\(\s*'file'/", $src ), 'no feature entry loads class-ds-la-schedule.php' );
$check( false === strpos( $src, "'file'          => 'features/class-ds-la-schedule.php'" ), 'the feature file is not registered' );
$check( 0 === preg_match( "/'ds_la_schedule_module_enabled'\s*=>\s*array\(\s*'label'/", $src ), 'no Features screen switch' );
$check( is_file( dirname( __DIR__ ) . '/modules/ds-la-schedule/ds-la-schedule.php' ), 'the module code is kept for later' );
echo $fails ? "$fails FAILED\n" : "OK 4 checks\n";
exit( $fails ? 1 : 0 );
