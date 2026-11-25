<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: "Hide programs that have already ended". Stub-style, no WordPress needed:
 *
 *   php tests/programs-hide-ended-test.php
 *
 * Why it exists (Lou Fusz Lacrosse, Zendesk #475146, 2026-11-26): LeagueApps site 18153 still listed 17
 * programs from 2017-2019 as LIVE and OPEN. The hosted widget hid them; the module showed them, so the five
 * lacrosse pages could not move off the widget.
 *
 * has_ended() is read out of modules/ds-programs/ds-programs.php itself, so this tests the shipped code.
 */

$fails = 0; $checks = 0;
function ok( $cond, $msg ) {
	global $fails, $checks; $checks++;
	if ( $cond ) { echo "  ok   $msg\n"; return; }
	$fails++; echo "  FAIL $msg\n";
}

$src = file_get_contents( dirname( __DIR__ ) . '/modules/ds-programs/ds-programs.php' );
if ( ! preg_match( '/\tpublic static function has_ended\(.*?\n\t\}\n/s', $src, $m ) ) {
	echo "  FAIL could not find has_ended() in ds-programs.php\n"; exit( 1 );
}
eval( 'class DS_Ended_Under_Test {' . $m[0] . '}' );

$now = 1795651200; // 2026-11-26 00:00 UTC
$day = 86400;

echo "has_ended\n";
ok( DS_Ended_Under_Test::has_ended( array( 'endTs' => 1513728000 ), $now ), 'a program that ended 2017-12-20 has ended' );
ok( ! DS_Ended_Under_Test::has_ended( array( 'endTs' => 1801526400 ), $now ), 'a program ending 2027-02-02 has not' );
ok( ! DS_Ended_Under_Test::has_ended( array( 'endTs' => 0 ), $now ), 'no end date = not ended' );
ok( ! DS_Ended_Under_Test::has_ended( array(), $now ), 'a row without endTs = not ended' );
ok( ! DS_Ended_Under_Test::has_ended( array( 'endTs' => $now - $day + 60 ), $now ), 'ended less than a day ago: still listed (its last day, any time zone)' );
ok( DS_Ended_Under_Test::has_ended( array( 'endTs' => $now - $day - 60 ), $now ), 'ended more than a day ago: hidden' );

echo "wiring\n";
ok( false !== strpos( $src, "'hide_ended'    => array( 'type' => 'select'" ), 'the setting is in the module form' );
ok( (bool) preg_match( "/'hide_ended'.*?'default' => 'no'/", $src ), 'default is No, so existing modules keep listing what they list today' );
ok( false !== strpos( $src, 'if ( $hide_ended && self::has_ended( $r, $now ) ) { return false; }' ), 'the row filter applies it' );

echo "\n$checks checks, $fails failed\n";
exit( $fails ? 1 : 0 );
