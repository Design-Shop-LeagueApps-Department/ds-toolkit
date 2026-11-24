<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * LeagueApps Calendar past a long page cache (1.20.2). The calendar's data is part of the page HTML, so a page served
 * from WP Engine's cache (12 hours on speed-kit sites) showed a schedule up to 12 hours old. The page now carries when
 * its copy was read from LeagueApps (`fetched`) and a calendar-data route; a copy older than the server's own refresh
 * time (DS_Calendar_Data::TTL, one hour) asks the route and redraws with the newer one.
 *
 *   php tests/calendar-fresh-data-test.php     (the browser checks need node)
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
class FLBuilderModule { public function __construct( $a = array() ) {} }
class FLBuilder { public static function register_module() {} }
class FLBuilderCSS { public static function typography_field_rule() {} }
class DS_Calendar_Data { const TZ = 'America/New_York'; public static function club_tz() { return self::TZ; } const TTL = 3600; public static function site_options() { return array(); } public static function known() { return array(); } }
class DS_Module_UI { public static function color( $v ) { return $v; } public static function u( $v, $d ) { return $d; } public static function form_shown() { return true; } }
function __( $s, $d = '' ) { return $s; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/\s+/', '-', $s ) ); }
function get_option( $k ) { return array(); }
require __DIR__ . '/../modules/ds-la-calendar/ds-la-calendar.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-80s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
$root = dirname( __DIR__ );

echo "What the browser gets (DS_Calendar_Module::client_data)\n";
$has = method_exists( 'DS_Calendar_Module', 'client_data' );
$cd  = $has ? DS_Calendar_Module::client_data( array( 'events' => array( 3 => array( 'd' => '2026-11-23' ) ), 'teams' => array( '13 Allstars' ), 'programs' => array( 'x' => 'Fall' ), 'fetched' => 1795000000, 'checks' => array( 'secret' ), 'errors' => array( 'e' ) ) ) : array();
chk( 'exists', $has, true );
chk( 'events, teams, programs, fetched, today and nothing else', array_keys( $cd ), array( 'events', 'teams', 'programs', 'fetched', 'today' ) );
chk( 'lists are plain arrays (JSON arrays, not objects)', array( array_keys( $cd['events'] ?? array( 'x' => 1 ) ), array_keys( $cd['programs'] ?? array( 'x' => 1 ) ) ), array( array( 0 ), array( 0 ) ) );
chk( 'fetched is the LeagueApps read time', $cd['fetched'] ?? null, 1795000000 );
chk( 'today is the Eastern date', $cd['today'] ?? null, ( new DateTime( 'now', new DateTimeZone( 'America/New_York' ) ) )->format( 'Y-m-d' ) );

echo "The page and the route\n";
$tpl = file_get_contents( "$root/modules/ds-la-calendar/includes/frontend.php" );
chk( 'the page embeds client_data() (so it carries fetched)', false !== strpos( $tpl, 'DS_Calendar_Module::client_data( $data )' ), true );
chk( 'config.dataUrl = the calendar-data route, only for a readable saved module', (bool) preg_match( "/'dataUrl'\s*=>\s*\\\$readable \? esc_url_raw\( rest_url\( 'ds-toolkit\/v1\/calendar-data\//", $tpl ), true );
chk( 'config.maxAge = DS_Calendar_Data::TTL', false !== strpos( $tpl, "'maxAge'         => DS_Calendar_Data::TTL" ), true );
chk( 'Subscribe still needs its own switch', false !== strpos( $tpl, "\$feed     = \$readable && \$yes( 'sub_show' )" ), true );
$feat = file_get_contents( "$root/features/class-ds-la-calendar.php" );
chk( 'route registered: GET calendar-data/<post>/<node>, public', (bool) preg_match( "#'/calendar-data/\(\?P<post>.*?'permission_callback' => '__return_true',\s*'callback'\s*=> array\( \\\$this, 'data' \)#s", $feat ), true );
preg_match( '/public function data\( \$request \) \{(.*?)\n\t\}/s', $feat, $m );
$body = $m[1] ?? '';
chk( 'route reads only a published, public saved module (node_settings)', false !== strpos( $body, 'self::node_settings(' ), true );
chk( 'route answers the same data as the page', false !== strpos( $body, 'DS_Calendar_Module::client_data( DS_Calendar_Module::data_for( $settings ) )' ), true );
chk( 'route is cached 5 minutes (WP Engine + Cloudflare absorb bursts)', false !== strpos( $body, "'Cache-Control', 'public, max-age=300'" ), true );
chk( 'the ICS filter cannot grab it (/calendar/ prefix only)', false !== strpos( $feat, "0 !== strpos( (string) \$request->get_route(), '/ds-toolkit/v1/calendar/' )" ), true );

echo "In the browser (modules/ds-la-calendar/js/frontend.js)\n";
$js_file = "$root/modules/ds-la-calendar/js/frontend.js";
$js_src  = file_get_contents( $js_file );
chk( 'a swap redraws from the untouched markup and never asks twice', false !== strpos( $js_src, 'var pristine=swapped?null:root.cloneNode(true);' ) && false !== strpos( $js_src, 'init(n,true);' ), true );
chk( '... and not once the visitor has started using it', false !== strpos( $js_src, "if(used||!newer(data,fresh)||!root.parentNode){return;}" ), true );
chk( 'every calendar on a page gets the check (forEach index is not `swapped`)', false !== strpos( $js_src, "forEach(function(r){init(r);})" ), true );
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' === $node ) {
	echo "  node not found: stale()/newer() checks skipped\n";
} else {
	$js = <<<'JS'
const vm = require('vm'), fs = require('fs');
const ctx = { window: {}, document: { readyState: 'complete', querySelectorAll: () => ({ forEach() {} }) } };
vm.createContext(ctx); vm.runInContext(fs.readFileSync(process.argv[1], 'utf8'), ctx);
const a = ctx.window.DSLACalendar || {};
if (typeof a.stale !== 'function' || typeof a.newer !== 'function') { console.log('null'); process.exit(0); }
const now = 1795000000 * 1000, cfg = { dataUrl: '/wp-json/ds-toolkit/v1/calendar-data/1/x', maxAge: 3600 };
console.log(JSON.stringify([
  a.stale({ fetched: 1795000000 - 3601 }, cfg, now),
  a.stale({ fetched: 1795000000 - 3599 }, cfg, now),
  a.stale({ fetched: 1795000000 - 43200 }, { dataUrl: '' , maxAge: 3600 }, now),
  a.stale({ fetched: 0 }, cfg, now),
  a.stale({ fetched: 1795000000 - 43200 }, cfg, now),
  a.newer({ fetched: 100 }, { fetched: 200, events: [] }),
  a.newer({ fetched: 200 }, { fetched: 200, events: [] }),
  a.newer({ fetched: 200 }, { fetched: 300 }),
  a.newer({ fetched: 200 }, null),
]));
JS;
	$out = trim( (string) shell_exec( escapeshellarg( $node ) . ' -e ' . escapeshellarg( $js ) . ' ' . escapeshellarg( $js_file ) . ' 2>&1' ) );
	$r   = json_decode( $out, true );
	chk( 'frontend.js exposes stale() and newer() for this test', is_array( $r ), true );
	$r = is_array( $r ) ? $r : array_fill( 0, 9, null );
	chk( 'a copy just over an hour old asks for a newer one', $r[0], true );
	chk( 'a copy under an hour old does not', $r[1], false );
	chk( 'no route (module not saved on a public post) = no request', $r[2], false );
	chk( 'no fetched time (no LeagueApps site) = no request', $r[3], false );
	chk( 'a 12-hour-old copy from the page cache asks', $r[4], true );
	chk( 'a later read replaces the page copy', $r[5], true );
	chk( 'the same read does not (no pointless redraw)', $r[6], false );
	chk( 'an answer without events is ignored', $r[7], false );
	chk( 'a failed request is ignored', $r[8], false );
}

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
