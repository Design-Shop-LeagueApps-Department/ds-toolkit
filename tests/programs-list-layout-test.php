<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Programs: the List layout, the program logo and Per-site styles.
 * Stub-style, no WordPress needed:
 *
 *   php tests/programs-list-layout-test.php
 *
 * PLL Play, 2026-10-29: one page lists a tournaments site and a site per local league, each its
 * own brand. LeagueApps sends a logo per program (programLogo150) but leaves it blank on most
 * (10 of 169 on PLL Play Tournaments), so the List layout falls back to a placeholder set per site.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DS_TOOLKIT_URL', 'https://example.test/wp-content/plugins/ds-toolkit/' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'DAY_IN_SECONDS', 86400 );

function __( $s, $d = null ) { return $s; }
function _n( $one, $many, $n, $d = null ) { return 1 === (int) $n ? $one : $many; }
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
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $s ) ); }
function is_user_logged_in() { return false; }
function current_user_can( $c ) { return false; }
function get_option( $k, $d = '' ) { return $d; }
function get_transient( $k ) { return false; }
function wp_get_attachment_image_url( $id, $size ) { return 47896 === (int) $id ? 'https://example.test/uploads/tournaments-mark.png' : false; }

class FLBuilderModule { public $settings; public $node; function __construct( $a = array() ) {} }
class FLBuilder { static function register_settings_form( $id, $f ) {} static function register_module( $c, $f ) {} }
class DS_Module_UI {
	static function breakpoints() { return array( 992, 768 ); }
	static function color( $v ) { $v = trim( (string) $v ); if ( '' === $v ) { return ''; } return preg_match( '/^#?[0-9a-f]{3,8}$/i', $v ) ? ( '#' === $v[0] ? $v : '#' . $v ) : ''; }
}

require DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
require DS_TOOLKIT_PATH . 'modules/ds-programs/ds-programs.php';

$fails = 0; $n = 0;
function ok( $cond, $msg ) { global $fails, $n; $n++; if ( ! $cond ) { $fails++; echo "FAIL: $msg\n"; } }

/* ---- data: the program logo, and a sub-program inheriting its main program's ---- */
$norm   = new ReflectionMethod( 'DS_Programs_Data', 'normalize' );
$future = ( time() + 86400 * 30 ) * 1000;
$master = array( 'programId' => 10, 'name' => 'Zion Summit Classic', 'type' => 'TOURNAMENT', 'isMaster' => true, 'programLogo150' => '//svite.s3.amazonaws.com/program-10-s3.jpg', 'startTime' => $future, 'endTime' => $future, '_site' => '54052' );
$child  = array( 'programId' => 11, 'masterProgramId' => 10, 'name' => 'ZSC: 3-4th', 'type' => 'TOURNAMENT', 'gender' => 'MALE', 'location' => 'Utah', 'startTime' => $future, 'endTime' => $future, 'programUrlHtml' => '//x.leagueapps.com/t/11', 'maxTeamCount' => 8, 'remainingTeamCount' => 3, '_site' => '54052' );
$plain  = array( 'programId' => 20, 'name' => 'Denver Fall Sixes', 'type' => 'LEAGUE', 'gender' => 'ANY', 'startTime' => $future, 'endTime' => $future, 'programUrlHtml' => '//x.leagueapps.com/l/20', '_site' => '53236' );
$bare   = array( 'programId' => 30, 'name' => 'Other Site Camp', 'type' => 'CAMP', 'startTime' => $future, 'endTime' => $future, 'programUrlHtml' => '//x.leagueapps.com/c/30', '_site' => '99999' );

$m  = $norm->invoke( null, $master, array(), array(), array() );
$c  = $norm->invoke( null, $child, array( 10 => $master ), array(), array() );
$p  = $norm->invoke( null, $plain, array(), array(), array() );
$bb = $norm->invoke( null, $bare, array(), array(), array() );
ok( 'https://svite.s3.amazonaws.com/program-10-s3.jpg' === $m['logo'], 'programLogo150 normalised to https' );
ok( 'https://svite.s3.amazonaws.com/program-10-s3.jpg' === $c['logo'], 'a sub-program without a logo takes its main program\'s' );
ok( '' === $p['logo'], 'no logo in the feed stays blank' );
ok( '53236' === $p['siteId'], 'row keeps its LeagueApps site id' );

/* ---- module: Per-site styles and the logo fallback chain ---- */
$mod = new DS_Programs_Module();
$mod->settings = (object) array(
	'layout' => 'list',
	'site_styles' => array(
		(object) array( 'ss_site' => '54052', 'ss_logo' => 47896, 'ss_logo_src' => '', 'ss_accent' => 'ff5500', 'ss_row_bg' => '', 'ss_btn_bg' => 'ff5500', 'ss_btn_color' => '' ),
		(object) array( 'ss_site' => '53236', 'ss_logo' => '', 'ss_logo_src' => 'https://example.test/uploads/leagues-mark.png', 'ss_accent' => '', 'ss_row_bg' => '#fff8e1', 'ss_btn_bg' => 'ffcb06', 'ss_btn_color' => '' ),
		(object) array( 'ss_site' => '54052', 'ss_logo' => '', 'ss_logo_src' => 'https://example.test/second-row-ignored.png', 'ss_accent' => '000000' ),
		(object) array( 'ss_site' => '', 'ss_accent' => '00ff00' ),
		(object) array( 'ss_site' => '1;}body{x', 'ss_accent' => 'red;}' ),
	),
);
$ss = $mod->site_styles();
ok( array( '54052', '53236', '1' ) === array_map( 'strval', array_keys( $ss ) ), 'one entry per site, first row wins, blank site ignored, id reduced to digits' );
ok( '#ff5500' === $ss['54052']['accent'] && '' === $ss['1']['accent'], 'colours pass DS_Module_UI::color(), a crafted value is dropped' );
ok( 'https://example.test/uploads/tournaments-mark.png' === $ss['54052']['logo'], 'photo field read from the attachment id' );
ok( 'https://example.test/uploads/leagues-mark.png' === $ss['53236']['logo'], 'photo field falls back to the stored src' );

ok( array( $m['logo'], 'feed' ) === $mod->logo_for( $c ), 'feed logo outranks the site placeholder' );
ok( array( 'https://example.test/uploads/leagues-mark.png', 'placeholder' ) === $mod->logo_for( $p ), 'blank feed logo uses the site placeholder' );
ok( array( '', '' ) === $mod->logo_for( $bb ), 'no placeholder anywhere = no logo' );
$mod->settings->list_logo_default_src = 'https://example.test/default.png';
ok( array( 'https://example.test/default.png', 'placeholder' ) === $mod->logo_for( $bb ), 'module default placeholder for an unstyled site' );
unset( $mod->settings->list_logo_default_src );

ok( '#111111' === DS_Programs_Module::text_on( '#ff5500' ), 'dark text on PLL orange (white is 3.0:1)' );
ok( '#111111' === DS_Programs_Module::text_on( '#ffcb06' ), 'dark text on PLL yellow' );
ok( '#ffffff' === DS_Programs_Module::text_on( '#0b2a5b' ), 'white text on navy' );
ok( '' === DS_Programs_Module::text_on( 'var(--x)' ), 'an unreadable colour gives no guess' );
ok( '#e04b00' === DS_Programs_Module::darker( '#ff5500' ), 'hover is 12% darker' );

/* ---- render: the real table.php ---- */
class Fake_Programs_Module extends DS_Programs_Module {
	private $r; private $c;
	function __construct( $settings, $rows, $cols ) { $this->settings = (object) $settings; $this->r = $rows; $this->c = $cols; $this->node = 'test'; }
	function rows() { return $this->r; }
	function chosen_columns() { $o = array(); foreach ( $this->c as $k ) { $o[ $k ] = array( 'label' => DS_Programs_Data::catalog()[ $k ]['label'], 'align' => 'left', 'width' => '', 'nowrap' => false ); } return $o; }
	function chosen_filters() { return array(); }
	function filter_values( $k, $rows ) { return array(); }
	function html() { ob_start(); include DS_TOOLKIT_PATH . 'modules/ds-programs/includes/table.php'; return ob_get_clean(); }
}
$cols = array( 'dateRange', 'program', 'ageGroup', 'type', 'gender', 'location', 'price', 'spots', 'register' );
$rows = array( $c, $p, $bb );
$f = new Fake_Programs_Module( (array) $mod->settings, $rows, $cols );
$h = $f->html();

ok( false !== strpos( $h, 'ds-programs ds-programs--list' ), 'list wrapper class' );
ok( 3 === substr_count( $h, '<article class="ds-programs-row ds-programs-item has-logo' ), 'one article per row, with the logo slot' );
ok( 1 === substr_count( $h, 'data-site="54052"' ) && 1 === substr_count( $h, 'data-site="53236"' ), 'each row carries its site' );
ok( false !== strpos( $h, 'ds-programs-item-logo is-feed' ) && false !== strpos( $h, 'program-10-s3.jpg' ), 'feed logo rendered on the white tile' );
ok( false !== strpos( $h, 'leagues-mark.png' ), 'site placeholder rendered' );
ok( false !== strpos( $h, '<span class="ds-programs-item-initial" aria-hidden="true">O</span>' ), 'no logo at all: the initial keeps the row aligned' );
ok( false !== strpos( $h, '<p class="ds-programs-item-sub">ZSC: 3-4th</p>' ), 'sub-program name under the main program title' );
ok( false !== strpos( $h, 'Zion Summit Classic</h3>' ), 'title is the main program name' );
ok( preg_match( '~ds-programs-item-meta-location"><svg[^>]*>.*?</svg><span>Utah</span>~s', $h ), 'location with the pin icon' );
ok( preg_match( '~chip--type">Tournament</li><li class="ds-programs-chip ds-programs-chip--gender">Boys</li><li class="ds-programs-chip ds-programs-chip--spots">3 spots left</li>~', $h ), 'chips follow the column order, spots as a count' );
ok( 1 === substr_count( $h, 'chip--spots' ), 'no spots chip where LeagueApps sets no cap' );
ok( 0 === substr_count( $h, 'chip--location' ) && 0 === substr_count( $h, 'chip--dateRange' ), 'where and when are never chips' );
ok( 3 === substr_count( $h, 'class="ds-programs-btn"' ), 'every open row has its register button' );

/* the table layout is untouched apart from the new data-site attribute */
$t = ( new Fake_Programs_Module( array( 'layout' => 'table' ), $rows, $cols ) )->html();
ok( false !== strpos( $t, 'ds-programs--table' ) && false !== strpos( $t, '<tr class="ds-programs-row" data-i="0"' ), 'table layout still renders rows' );
ok( false === strpos( $t, 'ds-programs-item' ), 'table layout has no list markup' );
ok( false !== strpos( $t, 'data-site="53236"' ), 'table rows carry data-site too, so Per-site styles work there' );

echo $fails ? "$fails of $n failed\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
