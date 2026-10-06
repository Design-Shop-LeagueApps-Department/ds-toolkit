<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: a main program's sub-programs stay together under it,
 * whatever the sort order. Stub-style, no WordPress needed:
 *
 *   php tests/programs-group-sort-test.php
 *
 * The bug this guards (Absolute VB, 2026-10-06, reported by the partner): the
 * comparator ordered groups by EACH ROW's own startTs. A six-session boys series
 * starting Oct 16 therefore kept its first three rows, and the four sessions
 * dated Oct 30 to Nov 20 sorted below a different program starting Oct 24 — so
 * they rendered as if they were that program's sub-programs. "Program name A to
 * Z" hid it only because `program` is the master's name on every row in a group,
 * so that key is constant within a group; the date key was not.
 */

$fails = 0; $checks = 0;
function ok( $cond, $msg ) {
	global $fails, $checks; $checks++;
	if ( $cond ) { echo "  ok   $msg\n"; return; }
	$fails++; echo "  FAIL $msg\n";
}

/** age_rank stub: these fixtures carry no age groups, so ranking is flat. */
class DS_Programs_Data { public static function age_rank( $a ) { return 0; } }

/**
 * The comparator under test, lifted verbatim from DS_Programs_Module::rows().
 * Keep in sync with modules/ds-programs/ds-programs.php.
 */
function sort_rows( array $rows, $sort ) {
	$group_ts = array();
	foreach ( $rows as $r ) {
		$ts = (int) $r['startTs'];
		if ( ! $ts ) { continue; }
		$gk = (string) $r['groupKey'];
		if ( ! isset( $group_ts[ $gk ] ) || $ts < $group_ts[ $gk ] ) { $group_ts[ $gk ] = $ts; }
	}
	usort( $rows, function ( $a, $b ) use ( $sort, $group_ts ) {
		if ( ( $a['groupKey'] === $b['groupKey'] ) && ( ! empty( $a['isMaster'] ) !== ! empty( $b['isMaster'] ) ) ) {
			return ! empty( $a['isMaster'] ) ? -1 : 1;
		}
		if ( $a['groupKey'] !== $b['groupKey'] ) {
			$ta = $group_ts[ (string) $a['groupKey'] ] ?? 0;
			$tb = $group_ts[ (string) $b['groupKey'] ] ?? 0;
			if ( 'name' === $sort ) { return strcasecmp( $a['program'], $b['program'] ) ?: ( $ta <=> $tb ); }
			$c = $ta <=> $tb;
			if ( 'date_desc' === $sort ) { $c = -$c; }
			return $c ?: strcasecmp( $a['program'], $b['program'] );
		}
		$ra = DS_Programs_Data::age_rank( $a['ageGroup'] ); $rb = DS_Programs_Data::age_rank( $b['ageGroup'] );
		if ( $ra !== $rb ) { return $ra <=> $rb; }
		return ( $a['startTs'] <=> $b['startTs'] ) ?: strcasecmp( $a['ageGroup'], $b['ageGroup'] );
	} );
	return $rows;
}

function row( $group, $program, $age, $date, $master = false ) {
	return array( 'groupKey' => $group, 'program' => $program, 'ageGroup' => $age,
	              'startTs' => strtotime( $date . ' 00:00:00 UTC' ), 'isMaster' => $master );
}

/** How many times the list leaves a group and later comes back to it. */
function splits( array $rows ) {
	$cur = null; $seen = array(); $n = 0;
	foreach ( $rows as $r ) {
		$gk = (string) $r['groupKey'];
		if ( $gk === $cur ) { continue; }
		if ( isset( $seen[ $gk ] ) ) { $n++; }
		$seen[ $gk ] = 1; $cur = $gk;
	}
	return $n;
}

/** The real Absolute VB feed shape that produced the partner's report. */
$feed = array(
	row( 'boys',   '2026 AVC Boys Fall Advantage', '',                   '2026-10-16', true ),
	row( 'boys',   '2026 AVC Boys Fall Advantage', 'All Sessions',       '2026-10-16' ),
	row( 'boys',   '2026 AVC Boys Fall Advantage', 'Oct 16th, 2026',     '2026-10-16' ),
	row( 'boys',   '2026 AVC Boys Fall Advantage', 'Oct 23rd, 2026',     '2026-10-23' ),
	row( 'boys',   '2026 AVC Boys Fall Advantage', 'Oct 30th, 2026',     '2026-10-30' ),
	row( 'boys',   '2026 AVC Boys Fall Advantage', 'Nov 6th, 2026',      '2026-11-06' ),
	row( 'boys',   '2026 AVC Boys Fall Advantage', 'Nov 13th, 2026',     '2026-11-13' ),
	row( 'boys',   '2026 AVC Boys Fall Advantage', 'Nov 20th, 2026',     '2026-11-20' ),
	row( 'winter', '2026 LL/JJ/AA Winter Program', '',                   '2026-10-24', true ),
	row( 'winter', '2026 LL/JJ/AA Winter Program', 'Absolute Advantage', '2026-10-24' ),
	row( 'winter', '2026 LL/JJ/AA Winter Program', 'Junior Jumpers',     '2026-10-24' ),
	row( 'winter', '2026 LL/JJ/AA Winter Program', 'Little Leapers',     '2026-10-24' ),
	row( 'camps',  'Winter 2027 College Camps',    '',                   '2027-01-02', true ),
	row( 'camps',  'Winter 2027 College Camps',    'Open Gym Extra',     '2027-01-02' ),
	row( 'camps',  'Winter 2027 College Camps',    'D2 Impact Camp',     '2027-01-03' ),
);

echo "A group is never interrupted, whatever the order\n";
foreach ( array( 'date_asc', 'date_desc', 'name' ) as $sort ) {
	$out = sort_rows( $feed, $sort );
	ok( 0 === splits( $out ), "$sort: no group is split" );
	ok( count( $out ) === count( $feed ), "$sort: every row survives the sort" );
}

echo "\nThe master still leads its own group\n";
foreach ( array( 'date_asc', 'date_desc', 'name' ) as $sort ) {
	$out = sort_rows( $feed, $sort );
	$lead = array(); $cur = null;
	foreach ( $out as $r ) { if ( $r['groupKey'] !== $cur ) { $cur = $r['groupKey']; $lead[ $cur ] = ! empty( $r['isMaster'] ); } }
	ok( ! in_array( false, $lead, true ), "$sort: each group opens with its main program" );
}

echo "\nGroups are ordered by the GROUP's start, not by a stray sub-program's\n";
$asc = sort_rows( $feed, 'date_asc' );
$order = array(); foreach ( $asc as $r ) { if ( ! in_array( $r['groupKey'], $order, true ) ) { $order[] = $r['groupKey']; } }
ok( array( 'boys', 'winter', 'camps' ) === $order, 'date_asc: boys (Oct 16) before winter (Oct 24) before camps (Jan 2)' );

$desc = sort_rows( $feed, 'date_desc' );
$order = array(); foreach ( $desc as $r ) { if ( ! in_array( $r['groupKey'], $order, true ) ) { $order[] = $r['groupKey']; } }
ok( array( 'camps', 'winter', 'boys' ) === $order, 'date_desc: the exact reverse' );

echo "\nThe partner's exact symptom is gone\n";
$asc = sort_rows( $feed, 'date_asc' );
$boys = 0; $started = false; $ended = false; $stranded = 0;
foreach ( $asc as $r ) {
	if ( 'boys' === $r['groupKey'] ) { if ( $ended ) { $stranded++; } $started = true; $boys++; }
	elseif ( $started ) { $ended = true; }
}
ok( 8 === $boys, 'all 8 boys rows are present (1 main + 7 sub-programs)' );
ok( 0 === $stranded, 'none of them is stranded below the winter program' );

echo "\nA group whose rows carry no date still sorts without warnings\n";
$undated = array(
	row( 'x', 'No Dates Program', '', '@0', true ),
	row( 'x', 'No Dates Program', 'Session A', '@0' ),
	row( 'y', 'Dated Program',    '', '2026-10-16', true ),
);
foreach ( $undated as $i => $r ) { if ( ! $r['startTs'] ) { $undated[ $i ]['startTs'] = 0; } }
$out = sort_rows( $undated, 'date_asc' );
ok( 0 === splits( $out ), 'undated group is not split' );
ok( 3 === count( $out ), 'undated rows all survive' );

echo "\n$checks checks, $fails failed\n";
exit( $fails ? 1 : 0 );
