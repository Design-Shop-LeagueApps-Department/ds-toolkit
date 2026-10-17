<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Calendar Subscribe feed (DS_Calendar_ICS): times read from the calendar's own
 * time text, Eastern -> UTC across daylight saving, multi-day text, all-day weekends merged,
 * the team filter, text escaping and line folding, and the feed route's refusals.
 *
 *   php tests/calendar-ics-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
class DS_Calendar_Data { const TZ = 'America/New_York'; }
function wp_list_pluck( $l, $f ) { $o = array(); foreach ( $l as $i ) { $o[] = is_array( $i ) ? $i[ $f ] : $i->$f; } return $o; }
require __DIR__ . '/../includes/class-ds-calendar-ics.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-74s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
function utc( $r, $i ) { return $r ? ( clone $r[ $i ] )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i' ) : null; }
function ev( $d, $title, $over = array() ) { return array_merge( array( 'd' => $d, 'title' => $title, 'type' => 'Practice', 'label' => '', 'kind' => '', 'mark' => '', 'grp' => '', 'loc' => 'Main Gym', 'time' => '', 'times' => false, 'teams' => array(), 'url' => '', 'more' => '', 'reg' => '', 'note' => '' ), $over ); }

echo "Times\n";
$r = DS_Calendar_ICS::clock( '6 PM – 7:30 PM', '2026-10-16' );
chk( 'EDT evening: 6 PM Eastern = 22:00 UTC', utc( $r, 0 ), '2026-10-16 22:00' );
chk( 'EDT evening: ends 23:30 UTC', utc( $r, 1 ), '2026-10-16 23:30' );
$r = DS_Calendar_ICS::clock( '6 PM – 7:30 PM', '2026-11-10' );
chk( 'EST after 1 Nov: 6 PM Eastern = 23:00 UTC', utc( $r, 0 ), '2026-11-10 23:00' );
$r = DS_Calendar_ICS::clock( '9:30 AM', '2026-10-17' );
chk( 'start only lasts an hour', utc( $r, 1 ), '2026-10-17 14:30' );
$r = DS_Calendar_ICS::clock( '10 PM – 1 AM', '2026-10-17' );
chk( 'end before start = next day', utc( $r, 1 ), '2026-10-18 05:00' );
$sat = DS_Calendar_ICS::clock( 'Sat 9 AM – Sun 5 PM', '2026-10-17' );
$sun = DS_Calendar_ICS::clock( 'Sat 9 AM – Sun 5 PM', '2026-10-18' );
chk( 'cross-day text listed on Saturday starts Saturday', utc( $sat, 0 ), '2026-10-17 13:00' );
chk( 'the same text listed on Sunday still starts Saturday', utc( $sun, 0 ), '2026-10-17 13:00' );
chk( '... and ends Sunday 5 PM', utc( $sun, 1 ), '2026-10-18 21:00' );
chk( 'hyphen as separator works too', utc( DS_Calendar_ICS::clock( '5:00 PM - 6:00 PM', '2026-10-20' ), 0 ), '2026-10-20 21:00' );
chk( 'raw LeagueApps text that is not a time = no time', DS_Calendar_ICS::clock( 'Evenings, see email', '2026-10-20' ), null );
chk( 'empty = no time', DS_Calendar_ICS::clock( '', '2026-10-20' ), null );
chk( 'bad date = no time', DS_Calendar_ICS::clock( '6 PM', '2026-1-2' ), null );

echo "Entries\n";
$events = array(
	ev( '2026-10-17', 'Fall Classic', array( 'type' => 'Tournament', 'teams' => array( array( 'n' => '14 Sunrise', 't' => '' ), array( 'n' => '13 Twilight', 't' => '' ) ) ) ),
	ev( '2026-10-18', 'Fall Classic', array( 'type' => 'Tournament', 'teams' => array( array( 'n' => '14 Sunrise', 't' => '' ) ) ) ),
	ev( '2026-10-20', 'Practice', array( 'time' => '', 'times' => true, 'teams' => array( array( 'n' => '14 Sunrise', 't' => '6 PM – 7:30 PM' ), array( 'n' => '13 Twilight', 't' => '7:30 PM – 9 PM' ) ) ) ),
	ev( '2026-10-21', 'Fall Skills Clinic', array( 'kind' => 'prog', 'grp' => 'Fall Skills Clinic', 'type' => 'Clinic', 'time' => '5:30 PM – 7 PM', 'reg' => 'https://example.org/reg' ) ),
	ev( '2026-10-25', 'Winter League', array( 'kind' => 'deadline', 'grp' => 'Winter League', 'type' => 'Deadline', 'label' => 'Registration closes' ) ),
	ev( '2026-11-01', 'Winter League', array( 'kind' => 'prog', 'grp' => 'Winter League', 'type' => 'League', 'mark' => 'start', 'time' => '6 PM' ) ),
);
$all = DS_Calendar_ICS::entries( $events, '', 'example.org' );
chk( 'weekend tournament (2 all-day days) is ONE entry', count( array_filter( $all, function ( $r ) { return 'Fall Classic' === $r['summary']; } ) ), 1 );
$fc = array_values( array_filter( $all, function ( $r ) { return 'Fall Classic' === $r['summary']; } ) )[0];
chk( '... from Saturday', $fc['start'], '2026-10-17' );
chk( '... to Monday (end is exclusive)', $fc['end'], '2026-10-19' );
$pr = array_values( array_filter( $all, function ( $r ) { return 'Practice' === $r['summary']; } ) )[0];
chk( 'times that differ by team, no team picked = all day', $pr['all_day'], true );
chk( '... and the description lists each team\'s time', false !== strpos( $pr['desc'], '14 Sunrise 6 PM – 7:30 PM' ), true );
$cl = array_values( array_filter( $all, function ( $r ) { return 'Fall Skills Clinic' === $r['summary']; } ) )[0];
chk( 'program session keeps its time', utc( array( $cl['start'] ), 0 ), '2026-10-21 21:30' );
chk( 'program session links to registration', $cl['url'], 'https://example.org/reg' );
chk( 'deadline reads "Registration closes: <program>"', in_array( 'Registration closes: Winter League', wp_list_pluck( $all, 'summary' ), true ), true );
$st = array_values( array_filter( $all, function ( $r ) { return 'Winter League starts' === $r['summary']; } ) );
chk( 'season start marker is "<program> starts", all day', isset( $st[0] ) && $st[0]['all_day'], true );
chk( 'UIDs are unique', count( array_unique( wp_list_pluck( $all, 'uid' ) ) ), count( $all ) );
chk( 'UIDs are stable between two builds', wp_list_pluck( DS_Calendar_ICS::entries( $events, '', 'example.org' ), 'uid' ), wp_list_pluck( $all, 'uid' ) );

$one = DS_Calendar_ICS::entries( $events, 't:13 Twilight', 'example.org' );
chk( 'team filter: only that team\'s events (no programs)', wp_list_pluck( $one, 'summary' ), array( 'Fall Classic (13 Twilight)', 'Practice (13 Twilight)' ) );
chk( 'team filter: 13 Twilight played Saturday only', $one[0]['end'], '2026-10-18' );
chk( 'team filter: that team\'s own practice time', utc( array( $one[1]['start'] ), 0 ), '2026-10-20 23:30' );
$p = DS_Calendar_ICS::entries( $events, 'p:Winter League', 'example.org' );
chk( 'program filter: that program\'s dates and deadline', wp_list_pluck( $p, 'summary' ), array( 'Registration closes: Winter League', 'Winter League starts' ) );
chk( 'unknown filter shape = everything', count( DS_Calendar_ICS::entries( $events, 'x:1', 'example.org' ) ), count( $all ) );

$cross = array( ev( '2026-10-17', 'Overnight Camp', array( 'time' => 'Sat 9 AM – Sun 5 PM' ) ), ev( '2026-10-18', 'Overnight Camp', array( 'time' => 'Sat 9 AM – Sun 5 PM' ) ) );
chk( 'cross-day timed event listed on two days = one entry', count( DS_Calendar_ICS::entries( $cross, '', 'h' ) ), 1 );

echo "Feed text\n";
$evil = array( ev( '2026-10-20', "Semi; final, \\ \"Day\"\nTwo", array( 'loc' => 'Gym, Court 1', 'time' => '6 PM' ) ) );
$ics = DS_Calendar_ICS::build( $evil, '', 'Club, "Calendar"', 'exa mple.org<script>' );
chk( 'CRLF line endings', false !== strpos( $ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" ), true );
chk( 'summary escaped (; , \\ newline)', false !== strpos( $ics, 'SUMMARY:Semi\\; final\\, \\\\ "Day"\\nTwo' ), true );
chk( 'location escaped', false !== strpos( $ics, 'LOCATION:Gym\\, Court 1' ), true );
chk( 'no raw newline inside a value', 0 === preg_match( '/[^\r]\n/', $ics ), true );
chk( 'host cleaned for UIDs', 1 === preg_match( '/UID:[0-9a-f]{24}@examplescript\r\n/', str_replace( '.org', '', $ics ) ), true );
chk( 'calendar name escaped', false !== strpos( $ics, 'X-WR-CALNAME:Club\\, "Calendar"' ), true );
chk( 'timed event in UTC', false !== strpos( $ics, "DTSTART:20261020T220000Z\r\n" ), true );
$long = DS_Calendar_ICS::fold( 'DESCRIPTION:' . str_repeat( 'é', 80 ) );
$ok = true;
foreach ( explode( "\r\n", $long ) as $ln ) { if ( strlen( $ln ) > 75 || ! mb_check_encoding( $ln, 'UTF-8' ) ) { $ok = false; } }
chk( 'folded lines are at most 75 octets and never split a character', $ok, true );
chk( 'unfolding gives the line back', str_replace( "\r\n ", '', $long ), 'DESCRIPTION:' . str_repeat( 'é', 80 ) );
chk( 'javascript: URL never becomes the URL field', false === strpos( DS_Calendar_ICS::build( array( ev( '2026-10-20', 'X', array( 'url' => 'javascript:alert(1)' ) ) ), '', 'C', 'h' ), 'URL:' ), true );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
