<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * ds-menu: a sticky header keeps its submenus clickable, and a closed panel never widens the page.
 *
 *   php tests/ds-menu-sticky-overflow-test.php
 *
 * The bugs this guards (2026-10-30, reventsport.com, denverjets, steelkidsfirst): closed dropdowns
 * keep their layout (visibility:hidden), so one past the right edge made every desktop page scroll
 * sideways. Sites hid that with `header.fl-builder-content { overflow-x: clip }`, and once the
 * Themer header turned sticky (position:fixed) Chrome still painted the dropdowns but hit-tested
 * through them: 0/19 and 0/15 submenu links clickable while scrolled. The module now releases the
 * clip on a sticky header and takes any closed panel that still overflows out of layout. Behaviour
 * was proven in a real browser (Playwright, elementFromPoint at scroll 0 and sticky); this test
 * keeps the rules from being dropped or weakened.
 */

$fails = 0; $checks = 0;
function ok( $cond, $msg ) {
	global $fails, $checks; $checks++;
	if ( $cond ) { echo "  ok   $msg\n"; return; }
	$fails++; echo "  FAIL $msg\n";
}
function flat( $s ) { return preg_replace( '/\s+/', ' ', $s ); }
/** Strip comments so a rule mentioned only in a comment never counts as present. */
function code( $s ) { return preg_replace( '#/\*.*?\*/#s', '', $s ); }

$css = flat( code( file_get_contents( __DIR__ . '/../modules/ds-menu/css/frontend.css' ) ) );
$js  = code( file_get_contents( __DIR__ . '/../modules/ds-menu/js/frontend.js' ) );

echo "Sticky header releases a site's header clip\n";
ok( false !== strpos( $css, 'header.fl-builder-content.fl-theme-builder-header-sticky { overflow-x: visible; }' ),
	'rule present: header.fl-builder-content.fl-theme-builder-header-sticky { overflow-x: visible }' );
// It must out-rank the per-site workaround `header.fl-builder-content` (0,1,1) without !important,
// and it must not sit inside a width query (a sticky phone header is fixed too).
$before = substr( $css, 0, strpos( $css, 'header.fl-builder-content.fl-theme-builder-header-sticky {' ) );
ok( substr_count( $before, '{' ) === substr_count( $before, '}' ), 'rule is top level, not inside a media query' );
ok( false === stripos( $css, 'overflow-x: clip' ), 'the module itself never clips the header' );

echo "A closed panel that still overflows is taken out of layout\n";
$guard = '.ds-menu-wrap:not(.ds-menu-open) .ds-menu-item:not(:hover):not(.ds-hover-open):not(:focus-within) > .ds-rest-out { display: none; }';
ok( false !== strpos( $css, $guard ), 'rest-guard rule present with every open state excluded' );
ok( 1 === preg_match( '/initRestGuard\( w \);\s*\} \);\s*\}/', $js ), 'boot() starts the rest guard, after the flip and the clamp' );

echo "The guard measures only after the flip and clamp have run\n";
$g = substr( $js, strpos( $js, 'function guardRest' ), 1600 );
$p_remove = strpos( $g, "classList.remove( 'ds-rest-out' )" );
$p_flip   = strpos( $g, 'flipAllIn( wrap )' );
$p_clamp  = strpos( $g, 'clampAllMegas( wrap )' );
$p_rect   = strpos( $g, 'getBoundingClientRect' );
ok( false !== $p_remove && false !== $p_flip && false !== $p_clamp && false !== $p_rect, 'guardRest removes, flips, clamps and measures' );
ok( $p_remove < $p_flip && $p_flip < $p_clamp && $p_clamp < $p_rect, 'in that order (a hidden panel has no box to place)' );
ok( false !== strpos( $g, "li.matches( ':hover, .ds-hover-open, :focus-within' )" ), 'an open panel is never hidden' );
ok( false !== strpos( $g, "'ds-menu-open'" ), 'the mobile overlay is left alone' );
ok( false !== strpos( $g, 'document.documentElement.clientWidth' ), 'measured against the page width, not innerWidth (scrollbar)' );
ok( false !== strpos( $js, "window.addEventListener( 'load', run )" ), 'runs again on load (fonts and images move the bar)' );

echo "\n$checks checks, $fails failed\n";
exit( $fails ? 1 : 0 );
