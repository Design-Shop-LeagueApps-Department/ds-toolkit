<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Calendar: consecutive days of the same event merge into one entry only when every day has the
 * same times (1.20.1). Before, Supernova's "practice" on Sunday 22 Nov (four teams 12 PM - 2 PM) and Monday
 * 23 Nov (5-7 PM and 7-9 PM) became one entry, and Monday's pop-up listed Sunday's 12 PM - 2 PM for four
 * teams; the Subscribe feed merged Sunday 29 + Monday 30 Nov the same way under Sunday's description.
 * The events below are the real ones from supernovaallstars.com/calendar/ (2026-11-21).
 *
 *   php tests/calendar-span-times-test.php     (the pop-up checks need node; the feed checks are plain PHP)
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
class DS_Calendar_Data { const TZ = 'America/New_York'; }
function wp_list_pluck( $l, $f ) { $o = array(); foreach ( $l as $i ) { $o[] = is_array( $i ) ? $i[ $f ] : $i->$f; } return $o; }
require __DIR__ . '/../includes/class-ds-calendar-ics.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-74s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
function ev( $d, $title, $over = array() ) { return array_merge( array( 'd' => $d, 'title' => $title, 'type' => 'Practice', 'label' => '', 'kind' => '', 'mark' => '', 'grp' => '', 'loc' => 'Harvard Westlake High School', 'time' => '', 'times' => false, 'teams' => array(), 'url' => '', 'more' => '', 'reg' => '', 'note' => '' ), $over ); }
/** "13 Allstars=5 PM – 7 PM; ..." -> teams. */
function tm( $s ) { $o = array(); foreach ( explode( '; ', $s ) as $p ) { list( $n, $t ) = array_pad( explode( '=', $p, 2 ), 2, '' ); $o[] = array( 'n' => $n, 't' => $t ); } return $o; }

$events = array(
	ev( '2026-11-15', 'practice', array( 'times' => true, 'teams' => tm( '15 Allstars=9 AM – 10:30 AM; 15 Gold=9 AM – 11 AM' ) ) ),
	ev( '2026-11-16', 'practice', array( 'time' => '7 PM – 9 PM', 'teams' => tm( '13 Allstars=7 PM – 9 PM; 15 Gold=7 PM – 9 PM' ) ) ),
	ev( '2026-11-22', 'practice', array( 'time' => '12 PM – 2 PM', 'teams' => tm( '14 Allstars=12 PM – 2 PM; 14 Gold=12 PM – 2 PM; 15 Allstars=12 PM – 2 PM; 15 Gold=12 PM – 2 PM' ) ) ),
	ev( '2026-11-23', 'practice', array( 'times' => true, 'teams' => tm( '13 Allstars=5 PM – 7 PM; 14 Allstars=7 PM – 9 PM; 14 Gold=7 PM – 9 PM; 15 Allstars=7 PM – 9 PM; 15 Gold=5 PM – 7 PM' ) ) ),
	ev( '2026-11-29', 'practice', array( 'times' => true, 'teams' => tm( '13 Allstars=2 PM – 4 PM; 14 Allstars=12 PM – 2 PM; 14 Gold=12 PM – 2 PM; 15 Allstars=12 PM – 2 PM; 15 Gold=4 PM – 6 PM; 16 Allstars=2 PM – 4 PM; 16 Gold=4 PM – 6 PM; 17 Gold=4 PM – 6 PM' ) ) ),
	ev( '2026-11-30', 'practice', array( 'times' => true, 'teams' => tm( '13 Allstars=5:30 PM – 7:30 PM; 14 Allstars=5:30 PM – 7:30 PM; 16 Allstars=7:30 PM – 9:30 PM; 16 Barbarik=7:30 PM – 9:30 PM; 17 Barbarik=7:30 PM – 9:30 PM; 17 Gold=5:30 PM – 7:30 PM; 18 Barbarik=7:30 PM – 9:30 PM' ) ) ),
	// Still one entry: a weekend tournament (all day, no times) and two days with the same team times.
	ev( '2026-10-17', 'Fall Classic', array( 'type' => 'Tournament', 'loc' => 'Expo Center', 'teams' => tm( '14 Allstars; 15 Gold' ) ) ),
	ev( '2026-10-18', 'Fall Classic', array( 'type' => 'Tournament', 'loc' => 'Expo Center', 'teams' => tm( '14 Allstars; 16 Gold' ) ) ),
	ev( '2026-12-05', 'Skills camp', array( 'type' => 'Training', 'times' => true, 'teams' => tm( '12 Allstars=9 AM – 11 AM; 13 Allstars=11 AM – 1 PM' ) ) ),
	ev( '2026-12-06', 'Skills camp', array( 'type' => 'Training', 'times' => true, 'teams' => tm( '13 Allstars=11 AM – 1 PM; 12 Allstars=9 AM – 11 AM' ) ) ),
);

echo "Subscribe feed (DS_Calendar_ICS::entries)\n";
$by = array();
foreach ( DS_Calendar_ICS::entries( $events, '', 'example.com' ) as $r ) {
	$by[ ( $r['all_day'] ? $r['start'] : $r['start']->format( 'Y-m-d' ) ) . ' ' . $r['summary'] ][] = $r;
}
$sun = $by['2026-11-29 practice'][0] ?? null;
chk( 'Sun 29 Nov (times vary) is its own all-day entry', $sun ? array( $sun['start'], $sun['end'] ) : null, array( '2026-11-29', '2026-11-30' ) );
chk( '... with Sunday\'s times', $sun && false !== strpos( $sun['desc'], '13 Allstars 2 PM – 4 PM' ), true );
$mon = $by['2026-11-30 practice'][0] ?? null;
chk( 'Mon 30 Nov is a separate entry (was merged into Sunday\'s)', $mon ? array( $mon['start'], $mon['end'] ) : null, array( '2026-11-30', '2026-12-01' ) );
chk( '... with Monday\'s times, not Sunday\'s', $mon && false !== strpos( $mon['desc'], '13 Allstars 5:30 PM – 7:30 PM' ) && false === strpos( $mon['desc'], '2 PM – 4 PM' ), true );
$sun23 = $by['2026-11-23 practice'][0] ?? null;
chk( 'Mon 23 Nov keeps its own teams and times', $sun23 && false !== strpos( $sun23['desc'], '13 Allstars 5 PM – 7 PM, 14 Allstars 7 PM – 9 PM' ), true );
chk( 'Sun 22 Nov (12 PM - 2 PM) stays a timed entry', isset( $by['2026-11-22 practice'] ) && ! $by['2026-11-22 practice'][0]['all_day'], true );
$t = $by['2026-10-17 Fall Classic'][0] ?? null;
chk( 'weekend tournament (all day) is still one entry, Sat-Sun', $t ? array( $t['start'], $t['end'] ) : null, array( '2026-10-17', '2026-10-19' ) );
chk( '... and no separate Sunday entry', isset( $by['2026-10-18 Fall Classic'] ), false );
$c = $by['2026-12-05 Skills camp'][0] ?? null;
chk( 'two days with the same team times are still one entry', $c ? array( $c['start'], $c['end'] ) : null, array( '2026-12-05', '2026-12-07' ) );
$dk = method_exists( 'DS_Calendar_ICS', 'day_key' );
chk( 'day_key ignores team order', $dk && DS_Calendar_ICS::day_key( $events[8] ) === DS_Calendar_ICS::day_key( $events[9] ), true );
chk( 'day_key tells Sunday 22 from Monday 23', $dk && DS_Calendar_ICS::day_key( $events[2] ) !== DS_Calendar_ICS::day_key( $events[3] ), true );

echo "Pop-up, list and Coming up (spans() in modules/ds-la-calendar/js/frontend.js)\n";
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' === $node ) {
	echo "  node not found: pop-up checks skipped (the feed checks above still ran)\n";
} else {
	$js = <<<'JS'
const vm = require('vm'), fs = require('fs');
const ctx = { window: {}, document: { readyState: 'complete', querySelectorAll: () => ({ forEach() {} }) } };
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(process.argv[1], 'utf8'), ctx);
const api = ctx.window.DSLACalendar || {};
if (typeof api.spans !== 'function') { console.log('null'); process.exit(0); }
const list = JSON.parse(fs.readFileSync(0, 'utf8'));
console.log(JSON.stringify(api.spans(list).map(s => ({ d: s.e.d, end: s.end, title: s.e.title, time: s.time, teams: s.teams.map(t => t.n + (t.t ? '=' + t.t : '')) }))));
JS;
	$sorted = $events;
	usort( $sorted, function ( $a, $b ) { return strcmp( $a['d'], $b['d'] ); } );
	$proc = proc_open( array( $node, '-e', $js, __DIR__ . '/../modules/ds-la-calendar/js/frontend.js' ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
	fwrite( $pipes[0], json_encode( $sorted ) );
	fclose( $pipes[0] );
	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	proc_close( $proc );
	$spans = json_decode( trim( $out ), true );
	chk( 'frontend.js exposes spans() for this test', is_array( $spans ), true );
	if ( ! is_array( $spans ) && '' !== trim( $err ) ) {
		echo '  node: ' . substr( trim( $err ), 0, 300 ) . "\n";
	}
	$s = array();
	foreach ( (array) $spans as $x ) {
		$s[ $x['d'] . ' ' . $x['title'] ] = $x;
	}
	chk( 'Mon 23 Nov is its own entry (was the tail of Sunday 22)', isset( $s['2026-11-23 practice'] ) ? $s['2026-11-23 practice']['end'] : null, '2026-11-23' );
	chk( '... listing Monday\'s times (14 Allstars 7-9 PM, not 12-2 PM)', $s['2026-11-23 practice']['teams'] ?? null, array( '13 Allstars=5 PM – 7 PM', '14 Allstars=7 PM – 9 PM', '14 Gold=7 PM – 9 PM', '15 Allstars=7 PM – 9 PM', '15 Gold=5 PM – 7 PM' ) );
	chk( 'Sun 22 Nov ends on Sunday', $s['2026-11-22 practice']['end'] ?? null, '2026-11-22' );
	chk( '... with its own four teams at 12 PM - 2 PM', $s['2026-11-22 practice']['teams'] ?? null, array( '14 Allstars=12 PM – 2 PM', '14 Gold=12 PM – 2 PM', '15 Allstars=12 PM – 2 PM', '15 Gold=12 PM – 2 PM' ) );
	chk( 'Mon 16 Nov is its own entry', isset( $s['2026-11-16 practice'] ) && '2026-11-16' === $s['2026-11-16 practice']['end'], true );
	chk( 'Mon 30 Nov is its own entry', isset( $s['2026-11-30 practice'] ) && '2026-11-30' === $s['2026-11-30 practice']['end'], true );
	chk( 'weekend tournament is still one entry, Sat-Sun', $s['2026-10-17 Fall Classic']['end'] ?? null, '2026-10-18' );
	chk( '... naming every team that played', $s['2026-10-17 Fall Classic']['teams'] ?? null, array( '14 Allstars', '15 Gold', '16 Gold' ) );
	chk( 'two days with the same team times are still one entry', $s['2026-12-05 Skills camp']['end'] ?? null, '2026-12-06' );
}

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
