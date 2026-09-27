<?php
// Run: wp eval-file tests/pattern-library-test.php (as a site with an @leagueapps.com admin; writes one pattern file to uploads/ds-patterns).
if ( ! defined( 'WP_CLI' ) ) { exit; }
$ok = 0; $bad = 0; $is = function ( $n, $a, $b ) use ( &$ok, &$bad ) { if ( $a === $b ) { $ok++; } else { $bad++; echo "FAIL $n: " . var_export( $a, 1 ) . "\n"; } };
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new Exception( 'die' ); }; } );
$data = json_decode( file_get_contents( DS_TOOLKIT_PATH . 'assets/patterns/patterns.json' ), true ); $slug = $data['patterns'][0]['s'] ?? ( $data[0]['s'] ?? '' );
$call = function ( $post ) { $_POST = $_REQUEST = wp_slash( array_merge( array( 'nonce' => wp_create_nonce( DS_Pattern_Library::AJAX ) ), $post ) ); ob_start(); try { do_action( 'wp_ajax_' . DS_Pattern_Library::AJAX ); } catch ( Exception $e ) {} return json_decode( ob_get_clean(), true ); };
$la = get_user_by( 'email', 'agabriel@leagueapps.com' ); wp_set_current_user( $la->ID );
$a = $call( array( 'slug' => $slug, 'color' => '#069e33', 'opacity' => '0.2', 'scale' => '1', 'stroke' => '1' ) );
$b = $call( array( 'slug' => $slug, 'color' => '#069e33', 'opacity' => '0.2004', 'scale' => '1.02', 'stroke' => '1.1' ) );
$is( 'LeagueApps admin makes a pattern', $a['success'] ?? null, true );
$is( 'near-identical values reuse the same file', $b['data']['file'] ?? 'x', $a['data']['file'] ?? 'y' );
$o = wp_insert_user( array( 'user_login' => 'pl_' . wp_generate_password( 5, false ), 'user_email' => 'pl-' . wp_generate_password( 4, false ) . '@partner-club.org', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
wp_set_current_user( $o );
$c = $call( array( 'slug' => $slug, 'color' => '#ff0000', 'opacity' => '0.5', 'scale' => '2', 'stroke' => '1' ) );
$is( 'a partner admin is refused', $c['success'] ?? null, false );
wp_set_current_user( $la->ID ); wp_delete_user( $o );
echo "slug=$slug file=" . ( $a['data']['file'] ?? '' ) . "\n";
echo $bad ? "FAILED $bad\n" : "ALL $ok PASS\n";
