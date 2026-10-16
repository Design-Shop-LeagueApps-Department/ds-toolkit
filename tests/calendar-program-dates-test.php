<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Calendar, program dates: camps, clinics, classes, leagues, tournaments and events
 * placed from their LeagueApps program dates. Every fixture copies a shape seen on a real fleet
 * site on 2026-10-17 (Inspire CLE, Absolute VB, Salt City, LSW, DS Storm, Lamorinda).
 *
 *   php tests/calendar-program-dates-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 );
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $u, $p = null ) { $p = $p ?: array( 'http', 'https' ); $s = strtolower( (string) parse_url( $u, PHP_URL_SCHEME ) ); return in_array( $s, $p, true ) ? $u : ''; }
function __( $s, $d = '' ) { return $s; }
require __DIR__ . '/../includes/class-ds-calendar-data.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-74s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

$Y  = (int) date( 'Y' ) + 1;
$ms = function ( $d ) { return ( new DateTime( $d . ' 00:00', new DateTimeZone( 'America/New_York' ) ) )->getTimestamp() * 1000; };
$future = $ms( ( $Y + 1 ) . '-01-01' );
$p = function ( $id, $type, $name, $start, $end, $extra = array() ) use ( $ms, $future ) {
	return array_merge( array( 'programId' => $id, '_site' => '1', 'type' => $type, 'name' => $name, 'visibility' => 'Public', 'state' => 'UPCOMING',
		'startTime' => $ms( $start ), 'endTime' => $end ? $ms( $end ) : null, 'scheduleDays' => null, 'scheduleTimes' => null,
		'registerUrlHtml' => "//club.leagueapps.com/registration/init?bid=$id", 'programUrlHtml' => "//club.leagueapps.com/events/$id",
		'publicRegistrationTime' => 1, 'endRegistrationTime' => $future ), $extra );
};
$days = function ( $r, $d ) { $o = array(); foreach ( $r['events'] as $e ) { if ( $d === $e['title'] && 'prog' === ( $e['kind'] ?? '' ) ) { $o[] = $e['d']; } } sort( $o ); return $o; };
$first_of = function ( $r, $title ) { foreach ( $r['events'] as $e ) { if ( $title === $e['title'] && 'prog' === $e['kind'] ) { return $e; } } return null; };
$find = function ( $r, $title, $d ) { foreach ( $r['events'] as $e ) { if ( $title === $e['title'] && $d === $e['d'] && 'prog' === $e['kind'] ) { return $e; } } return null; };

$rows = array(
	// Inspire: tryouts, a parent whose children carry the parent's END date but start on different nights.
	$p( 100, 'EVENT', 'Fall Tryouts', "$Y-10-16", "$Y-12-01", array( 'isMaster' => true ) ),
	$p( 101, 'EVENT', '10U-12U October 16th', "$Y-10-16", "$Y-12-01", array( 'masterProgramId' => 100, 'scheduleDays' => 'Fri', 'scheduleTimes' => '6:00PM to 8:00PM' ) ),
	$p( 102, 'EVENT', '13U-14U October 23rd', "$Y-10-23", "$Y-12-01", array( 'masterProgramId' => 100, 'scheduleDays' => 'Fri', 'scheduleTimes' => '6:00PM to 8:00PM' ) ),
	$p( 103, 'EVENT', '15U-18U 11/22/' . substr( $Y, 2 ), "$Y-11-15", "$Y-12-01", array( 'masterProgramId' => 100, 'scheduleDays' => 'Sun', 'scheduleTimes' => '8:30AM to 10:00AM' ) ),
	// Inspire: a weekly clinic.
	$p( 200, 'CAMP', 'Fall Skills Clinic', "$Y-09-13", "$Y-10-11", array( 'scheduleDays' => 'Sun', 'scheduleTimes' => '5:30PM to 7:00PM' ) ),
	// Salt City: league nights whose children share the parent's end AND start: weekly, not single.
	$p( 300, 'LEAGUE', 'Adult Softball Leagues', "$Y-09-09", "$Y-11-06", array( 'isMaster' => true ) ),
	$p( 301, 'LEAGUE', "Men's Sr Fall Monday", "$Y-09-09", "$Y-11-06", array( 'masterProgramId' => 300, 'scheduleDays' => 'Mon' ) ),
	$p( 302, 'LEAGUE', "Men's Rec Tuesday", "$Y-09-09", "$Y-11-06", array( 'masterProgramId' => 300, 'scheduleDays' => 'Tue' ) ),
	// Salt City: a tournament weekend with age-group children.
	$p( 400, 'TOURNAMENT', 'Slugfest', "$Y-04-03", "$Y-04-04", array( 'isMaster' => true, 'scheduleDays' => 'Sat,Sun' ) ),
	$p( 401, 'TOURNAMENT', '12u', "$Y-04-03", "$Y-04-04", array( 'masterProgramId' => 400, 'scheduleDays' => 'Sun,Sat' ) ),
	$p( 402, 'TOURNAMENT', '10u', "$Y-04-03", "$Y-04-04", array( 'masterProgramId' => 400, 'scheduleDays' => 'Sat,Sun' ) ),
	// LSW: season-long travel league (every day / no days over months): start and end only.
	$p( 500, 'LEAGUE', 'Astros 7U Travel', "$Y-06-02", "$Y-11-01", array( 'scheduleDays' => 'Sun,Mon,Tue,Wed,Thu,Fri,Sat' ) ),
	$p( 501, 'LEAGUE', 'Astros 8U Travel', "$Y-06-02", "$Y-11-01" ),
	// DS Storm: weekly camp children, each with its own night and time, under one parent.
	$p( 600, 'CAMP', 'Fall Girls Training', "$Y-09-01", "$Y-10-22", array( 'isMaster' => true, 'scheduleDays' => 'Tue,Thu' ) ),
	$p( 601, 'CAMP', '5th/6th girls', "$Y-09-01", "$Y-10-20", array( 'masterProgramId' => 600, 'scheduleDays' => 'Tue', 'scheduleTimes' => '7:00PM to 8:00PM' ) ),
	$p( 602, 'CAMP', '9th/10th girls', "$Y-09-01", "$Y-10-20", array( 'masterProgramId' => 600, 'scheduleDays' => 'Tue', 'scheduleTimes' => '6:00PM to 7:00PM' ) ),
	// Absolute: single-date sessions named by date plus an "All Sessions" bundle.
	$p( 700, 'CAMP', 'Boys Fall Advantage', "$Y-10-16", "$Y-10-30", array( 'isMaster' => true, 'scheduleDays' => 'Fri' ) ),
	$p( 701, 'CAMP', "All Sessions - Oct 16th, 23rd, 30th", "$Y-10-16", "$Y-10-30", array( 'masterProgramId' => 700, 'scheduleDays' => 'Fri', 'scheduleTimes' => '4:00PM to 5:45PM' ) ),
	$p( 702, 'CAMP', "Oct 16th, $Y", "$Y-10-16", "$Y-10-16", array( 'masterProgramId' => 700, 'scheduleDays' => 'Fri', 'scheduleTimes' => '4:00PM to 5:45PM' ) ),
	$p( 703, 'CAMP', "Oct 23rd, $Y", "$Y-10-23", "$Y-10-23", array( 'masterProgramId' => 700, 'scheduleDays' => 'Fri', 'scheduleTimes' => '4:00PM to 5:45PM' ) ),
	$p( 704, 'CAMP', "Oct 30th, $Y", "$Y-10-30", "$Y-10-30", array( 'masterProgramId' => 700, 'scheduleDays' => 'Fri', 'scheduleTimes' => '4:00PM to 5:45PM' ) ),
	// Exclusions: a club team, a private program, a cancelled one, a program with a real schedule.
	$p( 800, 'CLUBTEAM', 'National', "$Y-09-01", "$Y-12-01" ),
	$p( 801, 'CAMP', 'Private camp', "$Y-10-10", "$Y-10-10", array( 'visibility' => 'Private' ) ),
	$p( 802, 'CAMP', 'Cancelled camp', "$Y-10-11", "$Y-10-11", array( 'registrationStatus' => 'CANCELED' ) ),
	$p( 803, 'LEAGUE', 'Scheduled league', "$Y-09-01", "$Y-11-01", array( 'scheduleDays' => 'Wed' ) ),
	// Registration closed / opens later.
	$p( 900, 'CLINIC', 'Closed clinic', "$Y-11-05", "$Y-11-05", array( 'endRegistrationTime' => 1000 ) ),
	$p( 901, 'CLINIC', 'Later clinic', "$Y-11-06", "$Y-11-06", array( 'publicRegistrationTime' => $future ) ),
	// Script in a title, javascript: register URL.
	$p( 950, 'EVENT', '<b>Open</b> Day', "$Y-11-08", "$Y-11-08", array( 'registerUrlHtml' => 'javascript:alert(1)', 'programUrlHtml' => 'javascript:alert(2)' ) ),
);
$r = DS_Calendar_Data::program_events( $rows, array( '1:803' => true ) );

echo "date shapes\n";
chk( 'tryout sessions: one night each, not every Friday to December', $days( $r, 'Fall Tryouts' ), array( "$Y-10-16", "$Y-10-23", "$Y-11-15" ) );
$sundays = 0; for ( $t = strtotime( "$Y-09-13" ); $t <= strtotime( "$Y-10-11" ); $t += 86400 ) { if ( '0' === date( 'w', $t ) ) { $sundays++; } }
chk( 'weekly clinic: every Sunday in range, only Sundays', array( count( $days( $r, 'Fall Skills Clinic' ) ), count( array_filter( $days( $r, 'Fall Skills Clinic' ), function ( $d ) { return '0' !== date( 'w', strtotime( $d ) ); } ) ) ), array( $sundays, 0 ) );
chk( 'league nights sharing the parent end and start stay weekly', count( $days( $r, 'Adult Softball Leagues' ) ) > 10, true );
chk( 'tournament weekend: Sat and Sun', $days( $r, 'Slugfest' ), array( "$Y-04-03", "$Y-04-04" ) );
chk( 'season-long (every day) = start + end only', $days( $r, 'Astros 7U Travel' ), array( "$Y-06-02", "$Y-11-01" ) );
chk( 'season-long (no days) = start + end only', $days( $r, 'Astros 8U Travel' ), array( "$Y-06-02", "$Y-11-01" ) );
$a7 = $find( $r, 'Astros 7U Travel', "$Y-06-02" ); $a7e = $find( $r, 'Astros 7U Travel', "$Y-11-01" );
chk( 'season markers say start and end', array( $a7['mark'] ?? '', $a7e['mark'] ?? '' ), array( 'start', 'end' ) );
chk( 'Absolute bundle + date-named sessions: one entry per Friday', $days( $r, 'Boys Fall Advantage' ), array( "$Y-10-16", "$Y-10-23", "$Y-10-30" ) );

echo "grouping\n";
$sl = $find( $r, 'Slugfest', "$Y-04-03" );
chk( 'tournament children listed under the parent, naturally sorted', wp_list_pluck_t( $sl['teams'] ), array( '10u', '12u' ) );
$ds = null; foreach ( $r['events'] as $e ) { if ( 'Fall Girls Training' === $e['title'] && 2 === count( $e['teams'] ) ) { $ds = $e; break; } }
chk( 'two camp groups on one night keep their own times', $ds ? array( $ds['time'], $ds['times'] ) : null, array( '', true ) );
$ab = $find( $r, 'Boys Fall Advantage', "$Y-10-16" );
chk( 'date-only child names are dropped, the bundle name stays', wp_list_pluck_t( $ab['teams'] ), array( 'All Sessions - Oct 16th, 23rd, 30th' ) );
chk( 'time text is tidied ("4 PM – 5:45 PM")', $ab['time'], '4 PM – 5:45 PM' );
chk( 'program list for the visitor dropdown', in_array( 'Fall Tryouts', $r['groups'], true ) && in_array( 'Slugfest', $r['groups'], true ), true );

echo "exclusions\n";
$titles = array_unique( array_map( function ( $e ) { return $e['title']; }, $r['events'] ) );
chk( 'club teams never placed from program dates', in_array( 'National', $titles, true ), false );
chk( 'private programs never shown', in_array( 'Private camp', $titles, true ), false );
chk( 'cancelled programs never shown', in_array( 'Cancelled camp', $titles, true ), false );
chk( 'a program with a real schedule is not doubled', in_array( 'Scheduled league', $titles, true ), false );

echo "registration\n";
$fc = $first_of( $r, 'Fall Skills Clinic' );
chk( 'open registration -> Register link (https)', $fc['reg'] ?? '', 'https://club.leagueapps.com/registration/init?bid=200' );
chk( 'parent programs register through the parent page', $sl['reg'] ?? '', 'https://club.leagueapps.com/events/400' );
chk( 'closed registration -> no Register link', $find( $r, 'Closed clinic', "$Y-11-05" )['reg'] ?? 'x', '' );
chk( 'not yet open -> no Register link', $find( $r, 'Later clinic', "$Y-11-06" )['reg'] ?? 'x', '' );
$od = $find( $r, 'Open Day', "$Y-11-08" );
chk( 'title tags stripped', $od['title'] ?? '', 'Open Day' );
chk( 'javascript: register/program URLs never become links', array( $od['reg'] ?? 'x', $od['more'] ?? 'x' ), array( '', '' ) );

echo "data checks\n";
chk( 'title date that disagrees with the start date is flagged for editors', count( array_filter( $r['checks'], function ( $c ) { return false !== strpos( $c, '15U-18U' ); } ) ), 1 );
chk( 'matching titles are not flagged', count( array_filter( $r['checks'], function ( $c ) { return false !== strpos( $c, '10U-12U' ); } ) ), 0 );

echo "module settings\n";
$data = array( 'events' => array_merge( array( array( 'd' => "$Y-01-02", 'title' => 'Winter Warm-Up', 'type' => 'Tournament', 'teams' => array( array( 'n' => '14 Sunrise', 't' => '' ) ), 'reg' => '' ) ), $r['events'] ), 'programs' => $r['groups'] );
$off = DS_Calendar_Data::apply_program_settings( $data, array( 'show' => false ) );
chk( 'off: only team schedules remain', array( count( $off['events'] ), $off['events'][0]['title'], $off['programs'] ), array( 1, 'Winter Warm-Up', array() ) );
$camps = DS_Calendar_Data::apply_program_settings( $data, array( 'show' => true, 'types' => array( 'Camp' ), 'register' => true ) );
chk( 'type filter: Camps only (+ the schedule)', array_values( array_unique( array_map( function ( $e ) { return $e['type']; }, $camps['events'] ) ) ), array( 'Tournament', 'Camp' ) );
$first = DS_Calendar_Data::apply_program_settings( $data, array( 'show' => true, 'mode' => 'first', 'register' => true ) );
chk( 'first-day mode: the weekly clinic shows once', count( $days( $first, 'Fall Skills Clinic' ) ), 1 );
$se = DS_Calendar_Data::apply_program_settings( $data, array( 'show' => true, 'mode' => 'startend', 'register' => true ) );
$all = $days( $r, 'Fall Skills Clinic' );
chk( 'start-and-end mode: the weekly clinic shows its first and last session', $days( $se, 'Fall Skills Clinic' ), array( $all[0], end( $all ) ) );
$dl = DS_Calendar_Data::apply_program_settings( $data, array( 'show' => true, 'deadlines' => true, 'register' => true ) );
$ndl = DS_Calendar_Data::apply_program_settings( $data, array( 'show' => true, 'deadlines' => false, 'register' => true ) );
$count_dl = function ( $x ) { return count( array_filter( $x['events'], function ( $e ) { return 'deadline' === ( $e['kind'] ?? '' ); } ) ); };
chk( 'deadlines only when switched on', array( $count_dl( $dl ) > 0, $count_dl( $ndl ) ), array( true, 0 ) );
$nr = DS_Calendar_Data::apply_program_settings( $data, array( 'show' => true, 'register' => false ) );
$old = $data; $old['events'][] = array( 'd' => '2000-01-01', 'title' => 'Gone', 'type' => 'Camp', 'kind' => 'prog', 'grp' => 'Gone', 'teams' => array(), 'reg' => '', 'first' => true, 'last' => true, 'mark' => '' );
$cut = DS_Calendar_Data::apply_program_settings( array( 'events' => array_values( array_filter( $old['events'], function ( $e ) { return 'Gone' !== $e['title']; } ) ), 'programs' => array_merge( $r['groups'], array( 'Gone' ) ) ), array( 'show' => true, 'register' => true ) );
chk( 'dropdown lists only programs that still have an entry', in_array( 'Gone', $cut['programs'], true ), false );
chk( 'Register button off: no register links at all', count( array_filter( $nr['events'], function ( $e ) { return '' !== ( $e['reg'] ?? '' ); } ) ), 0 );

echo "review findings (2026-10-17)\n";
$warn = 0; set_error_handler( function () use ( &$warn ) { $warn++; return true; } );
$tz   = new DateTimeZone( 'America/New_York' );
$ym   = new DateTime( 'first day of this month', $tz );
$d0   = ( clone $ym )->modify( '-10 months' )->format( 'Y-m-d' );
$d1   = ( clone $ym )->modify( '+1 month' )->modify( 'last day of this month' )->format( 'Y-m-d' );
$n1   = ( clone $ym )->modify( '+1 month' )->format( 'Y-m-d' );
$n6   = ( clone $ym )->modify( '+7 months' )->format( 'Y-m-d' );
$rv = array(
	// 1. a year-long Tue/Thu class still shows this month; a Mon-Thu league too long to list becomes start/end
	$p( 1001, 'CLASS', 'Year Class', $d0, $d1, array( 'scheduleDays' => 'Tue,Thu' ) ),
	$p( 1002, 'LEAGUE', 'Long League', $n1, $n6, array( 'scheduleDays' => 'Mon,Tue,Wed,Thu' ) ),
	// 2. same name, two venues
	$p( 1010, 'TOURNAMENT', 'Twin Cup', "$Y-05-01", "$Y-05-01", array( 'location' => 'North Park' ) ),
	$p( 1011, 'TOURNAMENT', 'Twin Cup', "$Y-05-01", "$Y-05-01", array( 'location' => 'South Park' ) ),
	// 3. same name, same venue, two separate weekends (two programs)
	$p( 1020, 'TOURNAMENT', 'Showcase', "$Y-06-05", "$Y-06-06", array( 'location' => 'Hall', 'scheduleDays' => 'Sat,Sun' ) ),
	$p( 1021, 'TOURNAMENT', 'Showcase', "$Y-06-12", "$Y-06-13", array( 'location' => 'Hall', 'scheduleDays' => 'Sat,Sun' ) ),
	// 4. "All Sessions" + a dated drop-in ending the same day: the bundle stays weekly
	$p( 1030, 'CAMP', 'Friday Camp', "$Y-11-05", "$Y-12-17", array( 'isMaster' => true ) ),
	$p( 1031, 'CAMP', 'All Sessions', "$Y-11-05", "$Y-12-17", array( 'masterProgramId' => 1030, 'scheduleDays' => 'Fri' ) ),
	$p( 1032, 'CAMP', 'Drop-in Dec 17th', "$Y-12-17", "$Y-12-17", array( 'masterProgramId' => 1030, 'scheduleDays' => 'Fri' ) ),
	// 5. registration closes at midnight after the last day
	$p( 1040, 'CLINIC', 'Midnight Clinic', "$Y-07-10", "$Y-07-10", array( 'endRegistrationTime' => $ms( "$Y-07-11" ) ) ),
	// 6. a no-days child's season end on a weekly sibling's session day
	$p( 1050, 'LEAGUE', 'Fall Intramural', "$Y-09-04", "$Y-11-06", array( 'isMaster' => true ) ),
	$p( 1051, 'LEAGUE', 'Rookies', "$Y-09-04", "$Y-11-06", array( 'masterProgramId' => 1050, 'scheduleDays' => 'Sat', 'scheduleTimes' => '9:00AM to 2:00PM' ) ),
	$p( 1052, 'LEAGUE', 'Softball', "$Y-09-04", "$Y-11-06", array( 'masterProgramId' => 1050 ) ),
	// 7. a session that is over, registration window never closed
	$p( 1060, 'EVENT', 'Past Session', ( clone $ym )->modify( '-3 days' )->format( 'Y-m-d' ), null, array( 'endRegistrationTime' => null ) ),
	// 10. missing name / non-numeric registration time
	array( 'programId' => 1070, '_site' => '1', 'type' => 'CAMP', 'startTime' => $ms( "$Y-08-01" ), 'endRegistrationTime' => 'soon', 'publicRegistrationTime' => 'x' ),
	// 11. the parent has a schedule: its children are not doubled from program dates
	$p( 1080, 'LEAGUE', 'Scheduled Parent', "$Y-09-01", "$Y-11-01", array( 'isMaster' => true ) ),
	$p( 1081, 'LEAGUE', 'Division A', "$Y-09-01", "$Y-11-01", array( 'masterProgramId' => 1080, 'scheduleDays' => 'Wed' ) ),
);
$rr = DS_Calendar_Data::program_events( $rv, array( '1:1080' => true ) );
restore_error_handler();
$thism = $ym->format( 'Y-m' );
chk( '1. a year-long class still shows this month', count( array_filter( $days( $rr, 'Year Class' ), function ( $d ) use ( $thism ) { return 0 === strpos( $d, $thism ); } ) ) > 0, true );
$ll = array_values( array_filter( $rr['events'], function ( $e ) { return 'Long League' === $e['title'] && 'prog' === $e['kind']; } ) );
chk( '1. too many sessions to list -> start and end markers', array_map( function ( $e ) { return $e['mark']; }, $ll ), array( 'start', 'end' ) );
$tw = array_values( array_filter( $rr['events'], function ( $e ) { return 'Twin Cup' === $e['title'] && 'prog' === $e['kind']; } ) );
chk( '2. same name at two venues stays two entries', array( count( $tw ), $tw[0]['loc'] ?? '', $tw[1]['loc'] ?? '' ), array( 2, 'North Park', 'South Park' ) );
$fm = DS_Calendar_Data::apply_program_settings( $rr, array( 'show' => true, 'mode' => 'first', 'register' => true ) );
chk( '3. first-day mode keeps each real program\'s first day', $days( $fm, 'Showcase' ), array( "$Y-06-05", "$Y-06-12" ) );
chk( '4. "All Sessions" bundle stays weekly beside a dated drop-in', count( $days( $rr, 'Friday Camp' ) ) > 2, true );
$dl = array_values( array_filter( $rr['events'], function ( $e ) { return 'deadline' === $e['kind'] && 'Midnight Clinic' === $e['title']; } ) );
chk( '5. a midnight close shows on the last day you can register', $dl[0]['d'] ?? '', "$Y-07-10" );
$fi = array_filter( $rr['events'], function ( $e ) use ( $Y ) { return 'Fall Intramural' === $e['title'] && 'prog' === $e['kind'] && "$Y-11-06" === $e['d']; } );
chk( '6. no season-end marker on a day that already has a session', count( $fi ), 1 );
$ps = array_values( array_filter( $rr['events'], function ( $e ) { return 'Past Session' === $e['title'] && 'prog' === $e['kind']; } ) );
chk( '7. a session that is over has no Register link', $ps[0]['reg'] ?? 'missing', '' );
chk( '10. missing name / bad registration times: no PHP warnings', $warn, 0 );
chk( '11. a parent with a schedule: its children are not doubled', count( $days( $rr, 'Scheduled Parent' ) ) + count( $days( $rr, 'Division A' ) ), 0 );

function wp_list_pluck_t( $l ) { return array_map( function ( $x ) { return $x['n']; }, (array) $l ); }

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
