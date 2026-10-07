<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: the Locations scope. Stub-style, no WordPress needed:
 *
 *   php tests/programs-location-scope-test.php
 *
 * Why it exists (Tiny Troops Soccer, 2026-10-06): one regional LeagueApps site
 * holds many bases (Eastern alone: Fort Bragg, Camp Lejeune, Quantico, Shaw AFB…)
 * and each base has its own page. The hosted widget scopes a page with a hidden
 * Location filter; the module could only scope by site, so a base page listed the
 * whole region.
 *
 * The two helpers are read out of modules/ds-programs/ds-programs.php itself, so
 * this tests the shipped code, not a copy of it.
 */

$fails = 0; $checks = 0;
function ok( $cond, $msg ) {
	global $fails, $checks; $checks++;
	if ( $cond ) { echo "  ok   $msg\n"; return; }
	$fails++; echo "  FAIL $msg\n";
}

$src = file_get_contents( dirname( __DIR__ ) . '/modules/ds-programs/ds-programs.php' );
$body = '';
foreach ( array( 'location_list', 'location_matches' ) as $fn ) {
	if ( ! preg_match( '/\tpublic static function ' . $fn . '\(.*?\n\t\}\n/s', $src, $m ) ) {
		echo "  FAIL could not find $fn() in ds-programs.php\n"; exit( 1 );
	}
	$body .= $m[0];
}
eval( 'class DS_Loc_Under_Test {' . $body . '}' );

// Real location names from the eight Tiny Troops LeagueApps feeds, 2026-10-08.
$eastern = array(
	'Camp Lejeune/Jacksonville - NE Creek Park',
	'Ft Belvoir/Alexandria - Muddy Hole Farm Park',
	'JB Langley-Eustis/Hampton - Bethel Manor Elementary',
	'JB Anacostia-Bolling/Alexandria - Jefferson Manor Park',
	'MCB Quantico/ Brittany Neighborhood Park',
	'Fort Bragg/Fayetteville - Honeycutt Park',
	'Fort Bragg/Fayetteville - Winter Indoor classes - PTS Foundation',
	'Fort Liberty/Fayetteville - Honeycutt Park - Formerly Ft Bragg',
	'Shaw AFB/Sumter - Sumter Skate Park',
	'Ft Benning/Columbus - Benning Park',
);
$keep = function ( $scope ) use ( $eastern ) {
	$locs = DS_Loc_Under_Test::location_list( $scope );
	if ( ! $locs ) { return $eastern; }
	return array_values( array_filter( $eastern, function ( $l ) use ( $locs ) { return DS_Loc_Under_Test::location_matches( $l, $locs ); } ) );
};

echo "location_list\n";
ok( array() === DS_Loc_Under_Test::location_list( '' ), 'blank scope is an empty list' );
ok( array() === DS_Loc_Under_Test::location_list( "  \n\n \r\n" ), 'whitespace-only lines are dropped' );
ok( array( 'Fort Bragg', 'Shaw AFB' ) === DS_Loc_Under_Test::location_list( " Fort Bragg \r\nShaw AFB\n" ), 'CRLF and LF both split, entries trimmed' );
ok( array( 'Fort Bragg, NC' ) === DS_Loc_Under_Test::location_list( 'Fort Bragg, NC' ), 'a comma inside a name is NOT a separator' );
ok( array( 'Fort Bragg' ) === DS_Loc_Under_Test::location_list( "Fort Bragg\nFort Bragg" ), 'duplicates collapse' );

echo "scoping one base out of the Eastern region\n";
ok( count( $eastern ) === count( $keep( '' ) ), 'blank scope keeps every location (unchanged behaviour)' );
$b = $keep( 'Fort Bragg' );
ok( 2 === count( $b ), 'Fort Bragg keeps its 2 Fort Bragg fields' );
ok( ! in_array( 'Fort Liberty/Fayetteville - Honeycutt Park - Formerly Ft Bragg', $b, true ), 'a field renamed away from the base name (Fort Liberty) is NOT caught by it: it needs its own line' );
ok( 3 === count( $keep( "Fort Bragg\nFort Liberty" ) ), 'Fort Bragg plus Fort Liberty keeps all 3' );
ok( ! in_array( 'Shaw AFB/Sumter - Sumter Skate Park', $b, true ), 'Fort Bragg drops Shaw AFB' );
ok( 2 === count( $keep( 'fort bragg' ) ), 'match is case-insensitive' );
ok( 2 === count( $keep( "Shaw AFB\nCamp Lejeune" ) ), 'several lines combine as OR' );
ok( 0 === count( $keep( 'Fort Rucker' ) ), 'a base with nothing current lists nothing (the module then shows its empty text)' );
ok( false === DS_Loc_Under_Test::location_matches( '', array( 'Fort Bragg' ) ), 'a program with no location is out when a scope is set' );

// The hosted Fort Bragg widget's own selectedOptions, typed in verbatim, keep the same rows.
$widget = "Fort Liberty/Fayetteville - Honeycutt Park - Formerly Ft Bragg\nFort Bragg/Fayetteville - Honeycutt Park\nFort Bragg/Fayetteville - Winter Indoor classes - PTS Foundation";
ok( $keep( "Fort Bragg\nFort Liberty" ) === $keep( $widget ), "pasting the hosted widget's exact location names keeps the same 3 rows" );

echo "\n$checks checks, $fails failed\n";
exit( $fails ? 1 : 0 );
