<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * The one schema of features and settings (includes/class-ds-features.php, audit 2026-11 items 21 and 22):
 * it loads the same features in the same order, with the same defaults, as the hand-written registry and
 * defaults of 1.17.4 did; every card has one place on the settings page; every settings field an admin page
 * posts is known; and a saved value is cleaned by its type. Stub-style:
 *
 *   php tests/features-schema-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_ADMIN_DOMAIN', '@leagueapps.com' );
$ROOT = dirname( __DIR__ );
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return preg_replace( '/[^A-Za-z0-9@._+\-]/', '', (string) $s ); }
function is_email( $s ) { return (bool) preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', (string) $s ); }
function esc_url_raw( $u, $p = null ) { $u = trim( (string) $u ); return preg_match( '#^https?://[^\s<>"]+$#i', $u ) ? $u : ''; }
function wp_kses_post( $s ) { return preg_replace( '#<script\b.*?</script>#is', '', (string) $s ); }
require $ROOT . '/includes/class-ds-features.php';

$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };
$pin = json_decode( file_get_contents( __DIR__ . '/fixtures/features-1.17.4.json' ), true );

echo "same features, modules and defaults as 1.17.4\n";
$is( 'the registry: same ' . count( $pin['features'] ) . ' features, same files and classes, same load order', DS_Features::registry(), $pin['features'] );
$is( 'the Beaver Builder blocks: same ' . count( $pin['modules'] ) . ', same display order', array_keys( DS_Features::modules() ), $pin['modules'] );
foreach ( $pin['defaults'] as $bp => $want ) {
	$got = DS_Features::defaults( (int) substr( $bp, 2 ) );
	ksort( $got ); ksort( $want );
	$is( 'defaults on blueprint ' . substr( $bp, 2 ) . ' (' . count( $want ) . ' settings)', $got, $want );
}
$is( 'a LeagueApps data block has no default on any blueprint (opt-in everywhere)', array_intersect( array( 'ds_programs_module_enabled', 'ds_la_calendar_module_enabled', 'ds_la_roster_module_enabled' ), array_keys( DS_Features::defaults( 7 ) ) ), array() );

echo "every card has one place\n";
$all    = DS_Features::all();
$placed = array();
foreach ( DS_Features::layout() as $group => $keys ) { foreach ( $keys as $k ) { $placed[] = $k; } }
$is( 'every group in the layout exists', array_values( array_diff( array_keys( DS_Features::layout() ), array_keys( DS_Features::GROUPS ) ) ), array() );
$is( 'no card is placed twice', array_values( array_diff_key( $placed, array_unique( $placed ) ) ), array() );
$is( 'every placed card is in the schema', array_values( array_diff( $placed, array_keys( $all ) ) ), array() );
$cards = array();
foreach ( $all as $k => $d ) { if ( empty( $d['module'] ) && empty( $d['tab'] ) && ! in_array( $k, DS_Features::OWN_TAB, true ) ) { $cards[] = $k; } }
$is( 'every feature that is not a block and has no tab of its own has a card (nothing is reachable only by WP-CLI)', array_values( array_diff( $cards, $placed ) ), array() );
$bare = array();
foreach ( $placed as $k ) { if ( empty( $all[ $k ]['label'] ) || empty( $all[ $k ]['summary'] ) || empty( $all[ $k ]['icon'] ) ) { $bare[] = $k; } }
$is( 'every card has a name, an icon and a one-sentence summary', $bare, array() );
$long = array();
foreach ( $placed as $k ) { if ( strlen( $all[ $k ]['summary'] ) > 190 || substr_count( rtrim( $all[ $k ]['summary'], '.' ), '. ' ) > 1 ) { $long[] = $k; } }
$is( 'a summary stays short (the detail goes behind More)', $long, array() );
$scopes = array();
foreach ( $all as $k => $d ) { if ( ! empty( $d['scope'] ) && ! isset( DS_Features::SCOPES[ $d['scope'] ] ) ) { $scopes[] = $k; } }
$is( 'every scope chip is a known one', $scopes, array() );
$gated = array();
foreach ( $all as $k => $d ) { if ( ! empty( $d['min_blueprint'] ) && empty( $d['module'] ) && isset( $d['default'] ) && (int) ( $d['default_bp'] ?? 0 ) < (int) $d['min_blueprint'] ) { $gated[] = $k; } }
$is( 'a blueprint-gated feature gets its default only from that generation', $gated, array() );

echo "every field an admin page posts is known\n";
$known  = DS_Features::settings();
$posted = array();
foreach ( glob( $ROOT . '/admin/views/*.php' ) as $view ) {
	preg_match_all( '/ds_toolkit_settings\[([a-z0-9_]+)\]/', file_get_contents( $view ), $m );
	$posted = array_merge( $posted, $m[1] );
}
$posted = array_values( array_unique( $posted ) );
$extra  = array( 'leagueapps_sites' ); // the LeagueApps tab's rows: cleaned by DS_Toolkit_Admin, they are not a typed field
// The Features tab draws its fields from the schema, so only the hand-written tabs and extras are found here.
$is( 'the scan found the hand-written fields (' . count( $posted ) . ')', count( $posted ) >= 10, true );
$is( 'none is unknown to the schema', array_values( array_diff( $posted, array_keys( $known ), $extra ) ), array() );

echo "saving cleans by type\n";
$saved = array( 'bot_shield_ip_limit' => 180, 'tripwire_alert_email' => 'design@leagueapps.com', 'bot_shield_mode' => 'monitor', 'child_pages_template_id' => '56369' );
$s = function ( array $new ) use ( $saved ) { return DS_Features::sanitize( $new, $saved ); };
$is( 'a toggle is 1 or 0', $s( array( 'bot_shield_enabled' => '1', 'tripwire_enabled' => '0', 'nested_order_enabled' => 'on' ) ), array( 'bot_shield_enabled' => 1, 'tripwire_enabled' => 0, 'nested_order_enabled' => 1 ) );
$is( 'a number below its minimum is raised to it, above its maximum lowered', $s( array( 'bot_shield_ip_limit' => '5', 'child_pages_columns' => '40' ) ), array( 'bot_shield_ip_limit' => 30, 'child_pages_columns' => 6 ) );
$is( 'a word in a number box keeps the saved value', $s( array( 'bot_shield_ip_limit' => 'fast' ) ), array( 'bot_shield_ip_limit' => 180 ) );
$is( 'an empty number box keeps the saved value (never 0 requests a minute)', $s( array( 'bot_shield_ip_limit' => '' ) ), array( 'bot_shield_ip_limit' => 180 ) );
$is( 'a number with no saved value falls back to its default', DS_Features::sanitize( array( 'bot_shield_page_cap' => 'x' ), array() ), array( 'bot_shield_page_cap' => 20 ) );
$is( 'a broken email keeps the saved one (alerts never go nowhere)', $s( array( 'tripwire_alert_email' => 'design@' ) ), array( 'tripwire_alert_email' => 'design@leagueapps.com' ) );
$is( 'a good email is saved', $s( array( 'tripwire_alert_email' => ' ops@leagueapps.com ' ) ), array( 'tripwire_alert_email' => 'ops@leagueapps.com' ) );
$is( 'an alert list keeps every address (lvlelitetravel: two addresses)', $s( array( 'tripwire_alert_email' => 'design@leagueapps.com, agabriel@leagueapps.com' ) ), array( 'tripwire_alert_email' => 'design@leagueapps.com,agabriel@leagueapps.com' ) );
$is( 'a bad address in the list is dropped, the good ones kept', $s( array( 'tripwire_alert_email' => 'ops@leagueapps.com,nope@, OPS@leagueapps.com' ) ), array( 'tripwire_alert_email' => 'ops@leagueapps.com' ) );
$is( 'a single-address field still refuses a list', DS_Features::sanitize( array( 'forminator_email_partner_fallback' => 'a@leagueapps.com,b@leagueapps.com' ), array( 'forminator_email_partner_fallback' => 'designshop@leagueapps.com' ) ), array( 'forminator_email_partner_fallback' => 'designshop@leagueapps.com' ) );
$is( 'a mode that does not exist keeps the saved one', $s( array( 'bot_shield_mode' => 'nuke' ) ), array( 'bot_shield_mode' => 'monitor' ) );
$is( 'a real mode is saved', $s( array( 'bot_shield_mode' => 'block' ) ), array( 'bot_shield_mode' => 'block' ) );
$is( 'a javascript: link is refused', $s( array( 'academy_pinned_url' => 'javascript:alert(1)' ) )['academy_pinned_url'], '' === ( $saved['academy_pinned_url'] ?? '' ) ? DS_Features::settings()['academy_pinned_url']['default'] : '' );
$is( 'the template id stays digits, as text', $s( array( 'child_pages_template_id' => ' 5 12a ' ) ), array( 'child_pages_template_id' => '512' ) );
$is( 'global CSS cannot close the style element it is printed in', $s( array( 'global_css_overrides' => 'a{color:red}</style><script>x</script>' ) )['global_css_overrides'], 'a{color:red}><script>x</script>' );
$is( 'a child selector in global CSS survives', $s( array( 'global_css_overrides' => '.a > .b{margin:0}' ) )['global_css_overrides'], '.a > .b{margin:0}' );
$rows = $s( array( 'acf_css_vars_mappings' => array( array( 'acf_field' => 'header_Bar color', 'css_var' => '--bar;}body{', 'fallback' => 'var(--fl-global-accent);}' ), array( 'acf_field' => '', 'css_var' => '', 'fallback' => '' ) ) ) )['acf_css_vars_mappings'];
$is( 'ACF mapping rows: names cleaned, the empty row dropped, a fallback cannot close the rule', $rows, array( array( 'acf_field' => 'header_Barcolor', 'css_var' => '--barbody', 'fallback' => 'var(--fl-global-accent)' ) ) );
$is( 'a name the schema does not know passes through untouched', $s( array( '_dst_migrated_125_uabb_on' => 1, 'leagueapps_sites' => array( array( 'site_id' => '1' ) ) ) ), array( '_dst_migrated_125_uabb_on' => 1, 'leagueapps_sites' => array( array( 'site_id' => '1' ) ) ) );
$once = $s( array( 'bot_shield_ua_blocklist' => "mj12bot\ndotbot", 'bot_shield_ip_limit' => 200, 'bot_shield_enabled' => 1 ) );
$is( 'cleaning twice changes nothing (a programmatic save is safe)', DS_Features::sanitize( $once, $saved ), $once );

echo $fails ? "$fails of $n FAILED\n" : "all $n passed\n";
exit( $fails ? 1 : 0 );
