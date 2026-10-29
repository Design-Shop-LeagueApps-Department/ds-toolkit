<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
// Run: php tests/tripwire-staff-admin-test.php        (plain PHP, no WordPress, no network)
//
// Guards 1.13.1: a new administrator created by Design Shop staff (an administrator with an @leagueapps.com
// address, DS_Toolkit::is_leagueapps_staff) is recorded for HQ and NOT mailed, on a live site too. Everything
// else that mailed before still mails: an unknown actor, a non-staff creator, a partner role holding an
// @leagueapps.com address, a lookalike domain, and a throwaway address on the new account.

error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DS_TOOLKIT_ADMIN_DOMAIN', '@leagueapps.com' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

class WP_User {
    public $ID; public $user_login; public $user_email; public $roles; public $user_registered;
    public function __construct( $id, $login, $email, $roles = array( 'administrator' ), $age = 3600 ) {
        $this->ID = $id; $this->user_login = $login; $this->user_email = $email; $this->roles = $roles;
        $this->user_registered = gmdate( 'Y-m-d H:i:s', time() - $age );
    }
    public function exists() { return $this->ID > 0; }
}
class WP_Error { public function get_error_message() { return 'offline'; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function is_multisite() { return false; }
function is_super_admin() { return false; }
function wp_get_current_user() { return $GLOBALS['tw_me']; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['tw_opts'][ $k ] ) ? $GLOBALS['tw_opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['tw_opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['tw_opts'][ $k ] ); return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function apply_filters( $h, $v ) { return $v; }
function do_action( $h, ...$a ) { if ( 'ds_tripwire_alert' === $h ) { $GLOBALS['tw_hq'][] = $a[1]; } }
function add_action() {}
function add_filter() {}
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_mail( $to, $subject, $body ) { $GLOBALS['tw_mail'][] = $body; return true; }
function wp_doing_cron() { return false; }
function get_bloginfo() { return 'test'; }
function home_url( $p = '' ) { return $GLOBALS['tw_home'] . $p; }
function site_url( $p = '' ) { return home_url( $p ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_remote_post() { return new WP_Error(); }
function admin_url( $p = '' ) { return home_url( '/wp-admin/' . $p ); }
function esc_url_raw( $u ) { return $u; }

require_once dirname( __DIR__ ) . '/includes/class-ds-toolkit.php';
require_once dirname( __DIR__ ) . '/features/class-ds-tripwire.php';

$PASS = 0; $FAIL = 0;
function ok( $cond, $label, $got = '' ) {
    global $PASS, $FAIL;
    if ( $cond ) { $PASS++; } else { $FAIL++; echo "  FAIL $label" . ( '' !== $got ? " (got $got)" : '' ) . "\n"; }
}
$m  = new ReflectionMethod( 'DS_Tripwire', 'alert_new_admin' );
$m->setAccessible( true );
$id = 100;
/** Create one new admin as $me on $home; returns [ mailed, recorded for HQ, the "Created by" line ]. */
function run( $me, $email, $home = 'https://futureswpl.com' ) {
    global $m, $id;
    $GLOBALS['tw_home'] = $home; $GLOBALS['tw_me'] = $me; $GLOBALS['tw_opts'] = array();
    $GLOBALS['tw_mail'] = array(); $GLOBALS['tw_hq'] = array();
    $m->invoke( new DS_Tripwire( array() ), new WP_User( ++$id, 'new' . $id, $email ), 'was granted the administrator role' );
    $lines = $GLOBALS['tw_hq'] ? $GLOBALS['tw_hq'][0] : array();
    return array( count( $GLOBALS['tw_mail'] ), count( $GLOBALS['tw_hq'] ), $lines ? $lines[1] : '' );
}
$staff  = new WP_User( 1, 'lpacheco@leagueapps.com', 'lpacheco@leagueapps.com' );
$staff2 = new WP_User( 2, 'agabriel', 'agabriel@leagueapps.com' );
$nobody = new WP_User( 0, '', '' );

// 1. Staff on a LIVE site: recorded for HQ, no email. HQ closes it as Our own files.
list( $mail, $hq, $by ) = run( $staff, 'dustin.litvak@gmail.com' );
ok( 0 === $mail, 'staff-created admin on a live site: no email', (string) $mail );
ok( 1 === $hq, 'staff-created admin: still recorded for HQ', (string) $hq );
ok( 'Created by: lpacheco@leagueapps.com.' === $by, 'creator line names the email (what HQ matches)', $by );
list( $mail, $hq, $by ) = run( $staff2, 'coach@club.org' );
ok( 0 === $mail && 'Created by: agabriel@leagueapps.com.' === $by, 'staff whose login is not an email: named by email', $by );

// 2. Everything that mailed before still mails on a live site.
list( $mail, $hq, $by ) = run( $nobody, 'rogue@club.org' );
ok( 1 === $mail && 1 === $hq, 'no logged-in creator (the campaign signature): mails', (string) $mail );
ok( 'Created by: unknown actor.' === $by, 'unknown actor line unchanged', $by );
list( $mail ) = run( new WP_User( 3, 'partner', 'boss@club.org' ), 'x@club.org' );
ok( 1 === $mail, 'new administrator creator (not staff): mails', (string) $mail );
list( $mail ) = run( new WP_User( 4, 'p', 'p@leagueapps.com', array( 'partner' ) ), 'x@club.org' );
ok( 1 === $mail, 'partner role carrying an @leagueapps.com address: mails', (string) $mail );
list( $mail ) = run( new WP_User( 5, 'e', 'e@leagueapps.com.evil.io' ), 'x@club.org' );
ok( 1 === $mail, 'lookalike suffix domain: mails', (string) $mail );
list( $mail ) = run( new WP_User( 6, 'e', 'e@notleagueapps.com' ), 'x@club.org' );
ok( 1 === $mail, 'lookalike prefix domain: mails', (string) $mail );
list( $mail, $hq, $by ) = run( $staff, 'admin_x1@example.com' );
ok( 1 === $mail, 'staff creating a throwaway-address admin: still mails', (string) $mail );
ok( 'Created by: lpacheco@leagueapps.com.' === $by, 'and the creator line is the login there (unchanged path)', $by );

// 3. Pre-launch behaviour is unchanged: recorded, never mailed, whoever created it.
list( $mail, $hq ) = run( $nobody, 'rogue@club.org', 'https://teamhoustonbb.wpenginepowered.com' );
ok( 0 === $mail && 1 === $hq, 'pre-launch: recorded, not mailed (unchanged)', "$mail/$hq" );

// 4. A new @leagueapps.com account is still recorded silently (1.9.130), and HQ gets nothing for it.
list( $mail, $hq ) = run( $nobody, 'new@leagueapps.com' );
ok( 0 === $mail && 0 === $hq, '@leagueapps.com new account: silent (unchanged)', "$mail/$hq" );

echo "staff admin test: $PASS passed, $FAIL failed\n";
exit( $FAIL ? 1 : 0 );
