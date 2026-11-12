<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * The retired LeagueApps Schedule block no longer ships. Stub-style:  php tests/retired-schedule-test.php
 *
 * Why (2026-11 audit, item 33): the block was retired in 1.10.44 (team pages use the Table block) but its
 * 1,100 lines, its data class and a 266-line test still went into every release zip, hashed and scanned on
 * every site. git keeps the code: `git show 51d0cb6:modules/ds-la-schedule/ds-la-schedule.php`.
 */
$root  = dirname( __DIR__ ) . '/';
$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-72s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

chk( 'the block folder is gone', is_dir( $root . 'modules/ds-la-schedule' ), false );
chk( 'its feature class is gone', is_file( $root . 'features/class-ds-la-schedule.php' ), false );
chk( 'its data class is gone', is_file( $root . 'includes/class-ds-schedule-data.php' ), false );

$schema = file_get_contents( $root . 'includes/class-ds-features.php' );
chk( 'no schema entry loads it, whatever a site saved', (bool) preg_match( "/'ds_la_schedule_module_enabled'\s*=>/", $schema ), false );

$left = array();
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	$p = str_replace( '\\', '/', substr( (string) $f, strlen( $root ) ) );
	if ( 'php' !== pathinfo( $p, PATHINFO_EXTENSION ) || 0 === strpos( $p, 'tests/' ) || 0 === strpos( $p, '.git/' ) ) { continue; }
	if ( preg_match( '/DS_Schedule_Data|DS_LA_Schedule\b|class-ds-la-schedule\.php|class-ds-schedule-data\.php/', file_get_contents( (string) $f ) ) ) { $left[] = $p; }
}
chk( 'nothing still refers to its classes or files', $left, array() );

// A site that ran the block before 1.10.44 may still hold its queued refresh job: deactivate and uninstall
// must keep clearing it, so the hook stays in the lifecycle list although nothing schedules it any more.
chk( 'its old refresh job is still cleared on deactivate and uninstall', (bool) preg_match( "/const RETIRED_CRON_HOOKS = array\( 'ds_la_schedule_refresh' \);/", file_get_contents( $root . 'includes/class-ds-lifecycle.php' ) ), true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
