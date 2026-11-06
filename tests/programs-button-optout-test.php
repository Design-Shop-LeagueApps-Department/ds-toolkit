<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * LeagueApps Programs: every <button> the module prints carries fl-content-ui-button.
 *
 *   php tests/programs-button-optout-test.php
 *
 * Why (2026-11-04, designshopdemo): Beaver Builder's Global Styles button rule
 * `.fl-page .fl-builder-content button:not(.fl-content-ui-button)` (0-3-1) outranks the module's
 * three-class resets and its per-node Style colours. "Clear filters" rendered in the global button
 * text colour, white on the light filter bar, and the block's Label colour could not change it;
 * every pager button rendered white on the brand green (3.53:1) with the current page
 * indistinguishable. fl-content-ui-button is Beaver Builder's own opt-out from that rule.
 */
$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-70s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

$dir   = dirname( __DIR__ ) . '/modules/ds-programs/';
$table = file_get_contents( $dir . 'includes/table.php' );
$js    = file_get_contents( $dir . 'js/frontend.js' );

echo "buttons the module prints\n";
chk( 'Clear filters carries the opt-out', (bool) preg_match( '/<button type="button" class="fl-content-ui-button ds-programs-clear"/', $table ), true );
chk( 'sort headings carry the opt-out', (bool) preg_match( '/<button type="button" class="fl-content-ui-button ds-programs-sortbtn"/', $table ), true );
chk( 'pager buttons (built in JS) carry the opt-out', (bool) preg_match( '/<button type="button" class="fl-content-ui-button ds-programs-page/', $js ), true );

preg_match_all( '/<button\b[^>]*>/', $table . "\n" . $js, $m );
$missing = array_values( array_filter( $m[0], function ( $b ) { return false === strpos( $b, 'fl-content-ui-button' ); } ) );
chk( 'no <button> without it (a new one must opt out too)', $missing, array() );
chk( 'and there are buttons to check', count( $m[0] ) >= 3, true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
