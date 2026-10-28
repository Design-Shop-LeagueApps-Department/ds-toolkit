<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
// Run: php tests/tripwire-prelaunch-test.php        (plain PHP, no WordPress, no network)
//
// Guards pre-launch quiet mode: a site still being BUILT must not page anyone for our own build
// work, and must still page for malware.
//
// Measured 2026-10-16 over 90 days of HQ findings: temp-URL hosts produced 99 alerts across 63 sites
// and NOT ONE was ever verdict `real`, while all 204 real compromises were on live custom domains.
// So the suppression is keyed on lifecycle (home_url) and limited to four build-shaped findings.
//
// The assertions below are the safety line. If any malware string ever starts matching
// is_build_noise(), or prelaunch() ever returns true for a live domain, this stops being a
// notification policy and becomes a blind spot.

error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['tw_home'] = 'https://example.com';
class WP_Error { public function get_error_message() { return 'offline'; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['tw_opts'][ $k ] ) ? $GLOBALS['tw_opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['tw_opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['tw_opts'][ $k ] ); return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function apply_filters( $h, $v ) { return $v; }
function do_action() {}
function add_action() {}
function add_filter() {}
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function trailingslashit( $s ) { return untrailingslashit( $s ) . '/'; }
function wp_get_upload_dir() { return array( 'basedir' => WP_CONTENT_DIR . '/uploads' ); }
function wp_upload_dir() { return wp_get_upload_dir(); }
function wp_remote_get() { return new WP_Error(); }
function wp_safe_remote_get() { return new WP_Error(); }
function wp_remote_retrieve_response_code() { return 0; }
function wp_remote_retrieve_body() { return ''; }
function wp_mail() { return true; }
function get_bloginfo() { return 'test'; }
function home_url( $p = '' ) { return $GLOBALS['tw_home'] . $p; }
function site_url( $p = '' ) { return home_url( $p ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
$GLOBALS['tw_opts'] = array();

require_once dirname( __DIR__ ) . '/features/class-ds-tripwire.php';

$PASS = 0; $FAIL = 0;
function ok( $cond, $label, $got = '' ) {
    global $PASS, $FAIL;
    if ( $cond ) { $PASS++; } else { $FAIL++; echo "  FAIL $label" . ( '' !== $got ? " (got $got)" : '' ) . "\n"; }
}

// 1. prelaunch(): a site still on a platform hostname has not launched.
foreach ( array(
    'https://goldstarlax.wpenginepowered.com',
    'https://steelkidsfirst.flywheelsites.com',
    'http://ds-launchpad-7.local',
    'http://localhost',
) as $u ) {
    $GLOBALS['tw_home'] = $u;
    ok( DS_Tripwire::prelaunch(), "prelaunch() must be true for $u", 'false' );
}

// 2. A LIVE domain must never be treated as pre-launch - this is the blind-spot guard.
foreach ( array(
    'https://surfsidevolleyball.com',
    'https://thepaohio.com',
    'https://bigtimehoops.net',
    'https://notwpenginepowered.com',          // merely contains the string
    'https://wpenginepowered.com.evil.net',    // suffix spoof
) as $u ) {
    $GLOBALS['tw_home'] = $u;
    ok( ! DS_Tripwire::prelaunch(), "prelaunch() must be FALSE for $u", 'true' );
}

// 3. The four build-shaped findings are recognised.
foreach ( array(
    'A new PHP file appeared in mu-plugins since yesterday: ds-cmsa-admin-menus.php (1645 bytes). Nobody should be adding files there.',
    'Unexpected executable file in the web root: redo-about-page.php (3555 bytes).',
    'Unexpected folder _dsmig_4da6d25a3b1c/ in the web root holds 1 executable file(s) (PHP by extension or header). Nothing outside WordPress should be here; list it.',
    'A new administrator just appeared on this site: kigoe <kigoe@goldstarlax.com> (was granted the administrator role).',
) as $t ) {
    ok( DS_Tripwire::is_build_noise( $t ), 'build-shaped finding must match: ' . substr( $t, 0, 46 ) );
}

// 4. THE SAFETY LINE: real malware we have actually found must NEVER match. Every string below is
//    taken from a genuine finding in this fleet.
foreach ( array(
    'Web shell by behaviour: /www/wp-content/dzno6n6/k3yif9n/l99gtww/admin.php (3693 bytes, md5 127b6255, score 142). eval() present',
    'Web shell by behaviour: /www/bootstrap.inc.php (281671 bytes, md5 7f41998d, score 130).',
    'KNOWN MALWARE: nowa-* ZISI backdoor',
    'The Play Store doorway kit sits in apps/ (5/7 kit files, 4 bulk sitemaps).',
    'Known attacker Google Search Console token in the web root: google6313bd76ab531865.html.',
    'Attacker toolbox folder nz/ in the web root (/nz). This is the web shell that re-infected rattlers.',
    'polyglot: real PHP open tag inside a .jpg binary (extension is camouflage; stageable via include())',
    'wp-confih.php in the web root is NOT a WordPress core file (5356 bytes).',
) as $t ) {
    ok( ! DS_Tripwire::is_build_noise( $t ), 'MALWARE must still mail: ' . substr( $t, 0, 46 ), 'suppressed' );
}

// 5. The list itself must stay tiny and contain nothing malware-shaped.
ok( count( DS_Tripwire::BUILD_NOISE ) === 4, 'BUILD_NOISE must hold exactly 4 entries', (string) count( DS_Tripwire::BUILD_NOISE ) );
foreach ( DS_Tripwire::BUILD_NOISE as $lead ) {
    ok( false === stripos( $lead, 'shell' ) && false === stripos( $lead, 'malware' ) && false === stripos( $lead, 'polyglot' ),
        'BUILD_NOISE entry must not be malware-shaped: ' . $lead );
}

echo "prelaunch test: $PASS passed, $FAIL failed\n";
exit( $FAIL ? 1 : 0 );
