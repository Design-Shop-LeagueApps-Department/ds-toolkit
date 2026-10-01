<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: Schedule / Standings buttons for in-season programs.
 * Stub-style, no WordPress needed:
 *
 *   php tests/programs-season-links-test.php
 *
 * The rule mirrors the hosted LeagueApps listing widget it replaces
 * (showScheduleStandingLinks): a LIVE program gets Schedule and Standings
 * buttons, and keeps its register button only while registration is open.
 * Salt City 2026-09-30: nine in-season leagues showed only a dead Sign Up
 * button after the swap, so families could not reach their schedules.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'DAY_IN_SECONDS', 86400 );

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_attr__( $s, $d = null ) { return $s; }
function esc_html_e( $s, $d = null ) { echo $s; }
function esc_attr_e( $s, $d = null ) { echo $s; }
function date_i18n( $f, $t ) { return gmdate( $f, $t ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function is_user_logged_in() { return false; }
function current_user_can( $c ) { return false; }
function get_option( $k, $d = '' ) { return $d; }

require __DIR__ . '/../includes/class-ds-programs-data.php';

$fails = 0; $n = 0;
function ok( $cond, $msg ) { global $fails, $n; $n++; if ( ! $cond ) { $fails++; echo "FAIL: $msg\n"; } }

/* ---- data: the feed's protocol-relative links become https ---- */
$norm = new ReflectionMethod( 'DS_Programs_Data', 'normalize' );
$past   = ( time() - 86400 ) * 1000;
$future = ( time() + 86400 ) * 1000;
$base = array(
	'programId' => 1, 'name' => 'Fall League', 'type' => 'LEAGUE', 'sport' => 'Softball',
	'state' => 'LIVE', 'programUrlHtml' => '//x.leagueapps.com/leagues/1-fall',
	'scheduleUrlHtml' => '//x.leagueapps.com/leagues/1/schedule',
	'standingsUrlHtml' => '//x.leagueapps.com/leagues/1/standings',
	'endRegistrationTime' => $past, 'startTime' => $past, 'endTime' => $future,
);
$row = $norm->invoke( null, $base, array(), array(), array() );
ok( 'https://x.leagueapps.com/leagues/1/schedule' === $row['scheduleUrl'], 'scheduleUrl normalised to https' );
ok( 'https://x.leagueapps.com/leagues/1/standings' === $row['standingsUrl'], 'standingsUrl normalised to https' );
ok( 'CLOSED' === $row['statusRaw'], 'past endRegistrationTime derives CLOSED' );

/* ---- render: run the real table.php inside a stand-in module ---- */
class Fake_Programs_Module {
	public $settings; public $node = '.fl-node-test'; public $feed_stale = false; public $feed_errors = array();
	private $r;
	function __construct( $settings, $rows ) { $this->settings = (object) $settings; $this->r = $rows; }
	function rows() { return $this->r; }
	function chosen_columns() { return array( 'program' => array( 'label' => 'Program', 'align' => 'left', 'width' => '', 'nowrap' => false ), 'register' => array( 'label' => 'Register Button', 'align' => 'right', 'width' => '', 'nowrap' => false ) ); }
	function chosen_filters() { return array(); }
	function filter_values( $k, $rows ) { return array(); }
	function html() { ob_start(); include __DIR__ . '/../modules/ds-programs/includes/table.php'; return ob_get_clean(); }
}
function reg_cell( $settings, $raw ) {
	global $norm;
	$r = $norm->invoke( null, $raw, array(), array(), array() );
	$h = ( new Fake_Programs_Module( $settings + array( 'btn_text' => 'Sign Up' ), array( $r ) ) )->html();
	preg_match( '~ds-programs-td--register[^>]*>(.*?)</td>~s', $h, $m );
	return $m[1] ?? '';
}

// 1. LIVE + registration closed: Schedule + Standings, no Sign Up.
$c = reg_cell( array(), $base );
ok( false !== strpos( $c, '>Schedule<' ) && false !== strpos( $c, 'leagues/1/schedule' ), 'live+closed shows Schedule linked to the schedule page' );
ok( false !== strpos( $c, '>Standings<' ) && false !== strpos( $c, 'leagues/1/standings' ), 'live+closed shows Standings' );
ok( false === strpos( $c, 'Sign Up' ), 'live+closed drops the dead Sign Up button' );
ok( false !== strpos( $c, 'ds-programs-actions' ), 'buttons wrapped in .ds-programs-actions' );

// 2. LIVE + registration still open: Sign Up first, then Schedule + Standings.
$c = reg_cell( array(), array_merge( $base, array( 'endRegistrationTime' => $future, 'registerUrlHtml' => '' ) ) );
ok( false !== strpos( $c, 'Sign Up' ) && strpos( $c, 'Sign Up' ) < strpos( $c, 'Schedule' ), 'live+open keeps Sign Up, before Schedule' );
ok( false !== strpos( $c, 'Standings' ), 'live+open also shows Standings' );

// 3. UPCOMING: unchanged, a single Sign Up and no wrapper.
$c = reg_cell( array(), array_merge( $base, array( 'state' => 'UPCOMING', 'endRegistrationTime' => $future ) ) );
ok( false !== strpos( $c, 'Sign Up' ) && false === strpos( $c, 'Schedule' ) && false === strpos( $c, 'ds-programs-actions' ), 'upcoming renders exactly as before' );

// 4. Switched off: today's behaviour, Sign Up only.
$c = reg_cell( array( 'season_links' => 'no' ), $base );
ok( false !== strpos( $c, 'Sign Up' ) && false === strpos( $c, 'Schedule' ), 'season_links=no keeps the old single button' );

// 5. Custom labels.
$c = reg_cell( array( 'schedule_text' => 'Games', 'standings_text' => 'Table' ), $base );
ok( false !== strpos( $c, '>Games<' ) && false !== strpos( $c, '>Table<' ), 'custom button labels used' );

// 6. Cancelled outranks in-season: neutral chip only.
$c = reg_cell( array(), array_merge( $base, array( 'registrationStatus' => 'CANCELED' ) ) );
ok( false !== strpos( $c, 'Cancelled' ) && false === strpos( $c, 'Schedule' ), 'cancelled program shows only the Cancelled chip' );

// 7. LIVE without either URL: unchanged.
$c = reg_cell( array(), array_merge( $base, array( 'scheduleUrlHtml' => '', 'standingsUrlHtml' => '', 'endRegistrationTime' => $future ) ) );
ok( false !== strpos( $c, 'Sign Up' ) && false === strpos( $c, 'ds-programs-actions' ), 'live with no links falls back to the register button' );

// 8. Only one link published: only that button.
$c = reg_cell( array(), array_merge( $base, array( 'standingsUrlHtml' => '' ) ) );
ok( false !== strpos( $c, 'Schedule' ) && false === strpos( $c, 'Standings' ), 'only the published link renders' );

// 9. Sold out + live + still open window: Sold Out chip kept alongside the links.
$c = reg_cell( array(), array_merge( $base, array( 'endRegistrationTime' => $future, 'registrationStatus' => 'SOLD_OUT' ) ) );
ok( false !== strpos( $c, 'Sold Out' ) && false !== strpos( $c, 'Schedule' ), 'live sold-out keeps its chip and adds Schedule' );

echo ( $fails ? "$fails of $n FAILED\n" : "all $n passed\n" );
exit( $fails ? 1 : 0 );
