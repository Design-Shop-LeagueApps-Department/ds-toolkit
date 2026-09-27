<?php
// Run: wp eval-file tests/tripwire-vendor-manifest-test.php   (needs internet: reads
// downloads.wordpress.org/plugin-checksums). Guards DS_Tripwire::vendor_verified().
//
// The whole safety of that method is in its NEGATIVE answers, so most of these assert false. Two of
// them are real shells from our own signature table that lived INSIDE real, active plugins:
// bb-plugin's mailerlite vendor tree and wpforms-lite/assets/images/entry-importer/. If either ever
// returns true, the method has become "trust a registered plugin" and must not ship.
if ( ! defined( 'WP_CLI' ) ) { exit; }
$ok = 0; $bad = 0;
$is = function ( $n, $a, $b ) use ( &$ok, &$bad ) {
	if ( $a === $b ) { $ok++; echo "  ok   $n\n"; }
	else { $bad++; echo "  FAIL $n: got " . var_export( $a, 1 ) . " want " . var_export( $b, 1 ) . "\n"; }
};

$m = new ReflectionMethod( 'DS_Tripwire', 'vendor_verified' );
$m->setAccessible( true );
$v = function ( $path, $md5 ) use ( $m ) { return $m->invoke( null, $path, $md5 ); };

// A fresh set each case: vendor_ok is a per-request cache and would otherwise mask a bad answer.
$prop = new ReflectionProperty( 'DS_Tripwire', 'vendor_ok' );
$prop->setAccessible( true );
$tried = new ReflectionProperty( 'DS_Tripwire', 'vendor_tried' );
$tried->setAccessible( true );
$fetch = new ReflectionProperty( 'DS_Tripwire', 'vendor_fetches' );
$fetch->setAccessible( true );
$reset = function () use ( $prop, $tried, $fetch ) {
	$prop->setValue( null, array() ); $tried->setValue( null, array() ); $fetch->setValue( null, 0 );
};

// ---- negatives that must NEVER be cleared -------------------------------------------------------
$reset();
$is( 'a real shell inside wpforms-lite is NOT cleared (path absent from the official manifest)',
	$v( '/www/wp-content/plugins/wpforms-lite/assets/images/entry-importer/entry-importer/index.php',
		'23c716f776d529d34413327a385cd1cf' ), false );
$reset();
$is( 'a real shell inside bb-plugin is NOT cleared (premium, no manifest exists)',
	$v( '/www/wp-content/plugins/bb-plugin/includes/vendor/mailerlite/psr/http-message/http-message/index.php',
		'036994c2ea537e979c610cc9eb4b16ab' ), false );
$reset();
$is( 'a genuine vendor PATH with attacker content is NOT cleared (md5 mismatch)',
	$v( '/www/wp-content/plugins/wp-file-manager/lib/php/elFinder.class.php',
		'00000000000000000000000000000000' ), false );
$reset();
$is( 'our own plugin is never asked about',
	$v( '/www/wp-content/plugins/ds-toolkit/features/class-ds-tripwire.php',
		'deadbeefdeadbeefdeadbeefdeadbeef' ), false );
$reset();
$is( 'a file outside any plugin folder is not cleared',
	$v( '/www/wp-content/uploads/2024/10/10/cache.php', 'd41d8cd98f00b204e9800998ecf8427e' ), false );
$reset();
$is( 'a mu-plugin is not cleared by this route',
	$v( '/www/wp-content/mu-plugins/ds-origin-guard.php', 'd41d8cd98f00b204e9800998ecf8427e' ), false );
$reset();
$is( 'an empty md5 is not cleared', $v( '/www/wp-content/plugins/wp-file-manager/lib/php/elFinder.class.php', '' ), false );
$reset();
$is( 'a slug that cannot be a wordpress.org slug is refused',
	$v( '/www/wp-content/plugins/../../evil/x.php', 'd41d8cd98f00b204e9800998ecf8427e' ), false );

// ---- the positive, only if that plugin is actually installed here at that version ---------------
// Hard-coding a positive would need the plugin present, so this asserts the real mechanism only
// when it is. On a site without wp-file-manager the negatives above are still the meaningful half.
$reset();
$dir = ( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/wp-file-manager';
$target = $dir . '/lib/php/elFinder.class.php';
if ( is_file( $target ) ) {
	$real = md5_file( $target );
	$is( 'the installed wp-file-manager elFinder IS cleared against its own manifest', $v( $target, $real ), true );
	$reset();
	$is( 'and one byte different is NOT cleared', $v( $target, substr( $real, 0, 31 ) . ( '0' === substr( $real, 31 ) ? '1' : '0' ) ), false );
} else {
	echo "  skip wp-file-manager not installed here; positive path covered by fleet-audit/bin/tw-verify-vendor.py --selftest\n";
}

// ---- plugin_version() ---------------------------------------------------------------------------
$pv = new ReflectionMethod( 'DS_Tripwire', 'plugin_version' );
$pv->setAccessible( true );
$is( 'plugin_version reads a real installed plugin header', (bool) preg_match( '/^[0-9]/', (string) $pv->invoke( null, 'ds-toolkit' ) ), true );
$is( 'plugin_version on a folder that does not exist is empty', $pv->invoke( null, 'no-such-plugin-xyz' ), '' );

echo "\ntripwire vendor-manifest test: $ok passed, $bad failed\n";
if ( $bad ) { exit( 1 ); }
