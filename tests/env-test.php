<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * DS_Env: one place for hosts, addresses and the development-site rule. Stub-style, no WordPress:
 *
 *   php tests/env-test.php
 *
 * Why (2026-11 audit, item 31): three lists of development hostnames disagreed (Tripwire knew two platform
 * temp domains, the Home Templates picker four, the updater none), the Design Shop HQ host and the GitHub
 * repository were each typed in two files, and Hide FL Assistant compared against its own copy of the staff
 * domain, so a site that overrides DS_TOOLKIT_ADMIN_DOMAIN was ignored there.
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
$GLOBALS['home'] = 'https://example.org';
function home_url( $p = '' ) { return $GLOBALS['home'] . $p; }
function get_site_url() { return $GLOBALS['home']; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function apply_filters( $h, $v ) { return $v; }

require dirname( __DIR__ ) . '/includes/class-ds-env.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-78s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
$root = dirname( __DIR__ ) . '/';

echo "the development-site rule\n";
$dev = function ( $h ) { return DS_Env::is_dev_host( $h ); };
chk( 'a partner domain is live', $dev( 'www.core5ff.com' ), false );
chk( 'WP Engine temp address', $dev( 'corevball.wpenginepowered.com' ), true );
chk( 'old WP Engine temp address', $dev( 'corevball.wpengine.com' ), true );
chk( 'Flywheel temp address', $dev( 'core-5.flywheelsites.com' ), true );
chk( 'Flywheel staging address', $dev( 'core-5.flywheelstaging.com' ), true );
chk( 'Local site', $dev( 'design-shop-demo.local' ), true );
chk( '.test site and localhost', array( $dev( 'site.test' ), $dev( 'localhost' ) ), array( true, true ) );
chk( 'a domain that only CONTAINS a temp name is live', $dev( 'wpenginepowered.com.evil.example' ), false );
chk( 'a look-alike ending is live (no leading dot match)', $dev( 'notwpenginepowered.com' ), false );
chk( 'a live .dev domain is live', $dev( 'club.dev' ), false );
chk( 'case does not matter', $dev( 'CoreVball.WPEnginePowered.com' ), true );
chk( 'an unreadable hostname is treated as live (alerts still mail)', $dev( '' ), false );
chk( 'is_launched is the opposite', array( DS_Env::is_launched( 'www.core5ff.com' ), DS_Env::is_launched( 'x.wpenginepowered.com' ) ), array( true, false ) );
$GLOBALS['home'] = 'https://Demo.WPEnginePowered.com/sub';
chk( 'with no argument it reads this site\'s home address', array( DS_Env::host(), DS_Env::is_dev_host() ), array( 'demo.wpenginepowered.com', true ) );

echo "release candidates\n";
chk( 'a temp address is NOT a developer machine', DS_Env::is_local( 'demo.wpenginepowered.com' ), false );
chk( 'a partner build on a temp address stays on stable releases', DS_Env::is_beta_channel(), false );
$GLOBALS['home'] = 'https://design-shop-demo.local';
chk( 'a Local site may take a release candidate', DS_Env::is_beta_channel(), true );
chk( 'home-network and loopback addresses are local', array( DS_Env::is_local( '192.168.1.20' ), DS_Env::is_local( '127.0.0.1' ), DS_Env::is_local( '192.169.1.1' ) ), array( true, true, false ) );

echo "names\n";
chk( 'staff domain defaults to @leagueapps.com', DS_Env::staff_domain(), '@leagueapps.com' );
chk( 'staff address', array( DS_Env::is_staff_email( 'Zay@LeagueApps.com' ), DS_Env::is_staff_email( 'x@leagueapps.com.evil.org' ), DS_Env::is_staff_email( 'x@notleagueapps.com' ), DS_Env::is_staff_email( '' ) ), array( true, false, false, false ) );
define( 'DS_TOOLKIT_ADMIN_DOMAIN', '@example.org' );
chk( 'a site that overrides the constant is honoured', array( DS_Env::staff_domain(), DS_Env::is_staff_email( 'a@example.org' ), DS_Env::is_staff_email( 'a@leagueapps.com' ) ), array( '@example.org', true, false ) );
chk( 'HQ address', array( DS_Env::HQ_URL, DS_Env::hq_url( '/toolkit/' ) ), array( 'https://designshophq.wpenginepowered.com/', 'https://designshophq.wpenginepowered.com/toolkit/' ) );
chk( 'repository', array( DS_Env::REPO, DS_Env::REPO_URL ), array( 'Design-Shop-LeagueApps-Department/ds-toolkit', 'https://github.com/Design-Shop-LeagueApps-Department/ds-toolkit' ) );
chk( 'alert inbox', DS_Env::ALERT_EMAIL, 'design@leagueapps.com' );

echo "no second copy in the code\n";
$copies = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	$p = str_replace( '\\', '/', substr( (string) $f, strlen( $root ) ) );
	if ( 'php' !== pathinfo( $p, PATHINFO_EXTENSION ) || 0 === strpos( $p, 'tests/' ) || 0 === strpos( $p, '.git/' ) || 'includes/class-ds-env.php' === $p ) { continue; }
	// Code only: comments may name a host when they explain something.
	$code = '';
	foreach ( token_get_all( file_get_contents( (string) $f ) ) as $t ) {
		if ( is_array( $t ) ) { if ( T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] || T_INLINE_HTML === $t[0] ) { continue; } $code .= $t[1]; } else { $code .= $t; }
	}
	foreach ( array( 'HQ host' => "'https://designshophq.wpenginepowered.com", 'repository' => "'Design-Shop-LeagueApps-Department/ds-toolkit'", 'temp host list' => "'.flywheelsites.com'", 'alert inbox' => "'design@leagueapps.com'" ) as $what => $needle ) {
		if ( false !== strpos( $code, $needle ) ) { $copies[] = $what . ' in ' . $p; }
	}
}
chk( 'hosts, repository and alert inbox are typed once', $copies, array() );
$tw = file_get_contents( $root . 'features/class-ds-tripwire.php' );
chk( 'Tripwire\'s pre-launch rule is the shared one', (bool) preg_match( '/function prelaunch\(\)\s*\{(?:(?!\n    \}).)*return DS_Env::is_dev_host\(\);/s', $tw ), true );
chk( 'the Home Templates picker uses it and keeps its filter', (bool) preg_match( "/apply_filters\( 'ds_home_templates_dev_host', DS_Env::is_dev_host\( \\\$host \), \\\$host \)/", file_get_contents( $root . 'features/class-ds-home-templates.php' ) ), true );
chk( 'the updater asks DS_Env for the channel', (bool) preg_match( '/function is_beta_channel\(\)\s*\{\s*return DS_Env::is_beta_channel\(\);/', file_get_contents( $root . 'includes/class-ds-toolkit-updater.php' ) ), true );
chk( 'the main file loads it before the feature schema', strpos( $main = file_get_contents( $root . 'ds-toolkit.php' ), 'class-ds-env.php' ) < strpos( $main, 'class-ds-features.php' ), true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
