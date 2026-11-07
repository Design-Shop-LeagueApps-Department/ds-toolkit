<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * Hide Beaver Builder's Text Editor / Beaver Builder tabs for non-LeagueApps users (1.18.0, ClickUp 868mtnn1a).
 *
 *   php tests/hide-bb-editor-tabs-test.php
 *
 * A partner clicking "Text Editor" on a Beaver Builder page switches the layout off (Moss Park, 2026-09-29).
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
$GLOBALS['t_logged'] = true; $GLOBALS['t_email'] = ''; $GLOBALS['t_screen'] = 'post';
function is_user_logged_in() { return $GLOBALS['t_logged']; }
function wp_get_current_user() { return (object) array( 'user_email' => $GLOBALS['t_email'] ); }
function get_current_screen() { return $GLOBALS['t_screen'] ? (object) array( 'base' => $GLOBALS['t_screen'] ) : null; }
$GLOBALS['t_actions'] = array();
function add_action( $h, $cb ) { $GLOBALS['t_actions'][] = $h; }
require DS_TOOLKIT_PATH . 'features/class-ds-hide-bb-editor-tabs.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-72s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
function out( $email, $logged = true, $screen = 'post' ) {
	$GLOBALS['t_email'] = $email; $GLOBALS['t_logged'] = $logged; $GLOBALS['t_screen'] = $screen;
	ob_start(); ( new DS_Hide_BB_Editor_Tabs() )->maybe_hide_tabs(); return ob_get_clean();
}
$rule = 'body.fl-builder-enabled .fl-builder-admin-tabs{display:none !important;}';

echo "who loses the tabs\n";
chk( 'a partner on a page edit screen', false !== strpos( out( 'coach@mossparkfc.ca' ), $rule ), true );
chk( '@leagueapps.com keeps them', out( 'agabriel@leagueapps.com' ), '' );
chk( 'any letter case of the domain keeps them', out( 'Lou@LeagueApps.COM' ), '' );
chk( 'a look-alike domain does not (leagueapps.com.evil.io)', false !== strpos( out( 'x@leagueapps.com.evil.io' ), $rule ), true );
chk( 'a look-alike domain does not (notleagueapps.com)', false !== strpos( out( 'x@notleagueapps.com' ), $rule ), true );
chk( 'logged out: nothing printed', out( 'coach@mossparkfc.ca', false ), '' );

echo "\nwhere\n";
chk( 'only edit screens (not the dashboard)', out( 'coach@mossparkfc.ca', true, 'dashboard' ), '' );
chk( 'no screen yet: nothing printed', out( 'coach@mossparkfc.ca', true, '' ), '' );
chk( 'only pages with Beaver Builder ON (body.fl-builder-enabled), so Launch and plain pages stay', false !== strpos( out( 'coach@mossparkfc.ca' ), 'body.fl-builder-enabled .fl-builder-admin-tabs' ), true );
chk( 'hooked on admin_head', ( function () { $GLOBALS['t_actions'] = array(); ( new DS_Hide_BB_Editor_Tabs() )->init(); return $GLOBALS['t_actions']; } )(), array( 'admin_head' ) );

echo "\nwiring\n";
$core = file_get_contents( DS_TOOLKIT_PATH . 'includes/class-ds-toolkit.php' );
chk( 'registered as a feature', (bool) preg_match( "/'hide_bb_editor_tabs' => array\(\s*'file'\s*=> 'features\/class-ds-hide-bb-editor-tabs\.php',\s*'class' => 'DS_Hide_BB_Editor_Tabs',/", $core ), true );
chk( 'on by default (existing sites get it on update)', (bool) preg_match( "/'hide_bb_editor_tabs'\s*=> 1,/", $core ), true );
$view = file_get_contents( DS_TOOLKIT_PATH . 'admin/views/page-settings.php' );
chk( 'settings toggle present', false !== strpos( $view, 'name="ds_toolkit_settings[hide_bb_editor_tabs]" value="1"' ), true );
chk( 'toggle can be switched off (hidden 0 field)', false !== strpos( $view, 'name="ds_toolkit_settings[hide_bb_editor_tabs]" value="0"' ), true );
chk( 'MCP settings tool may change it', false !== strpos( file_get_contents( DS_TOOLKIT_PATH . 'admin/class-ds-mcp-server.php' ), "'hide_bb_editor_tabs'" ), true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
