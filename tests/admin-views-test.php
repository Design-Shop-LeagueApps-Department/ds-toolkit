<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * Settings views: no inline style attributes, one home for the Logo Finder, reference text behind a fold.
 *
 *   php tests/admin-views-test.php
 *
 * Why (2026-11 audit, items 19 and 20): the views carried 126 inline style attributes, so the same spacing
 * and colours were typed again in every tab and a contrast fix had to be made in each place; the University
 * Logo Finder was listed twice (a DS Toolkit tab and Media); and three tabs opened on pages of reference
 * text with their few settings somewhere inside it.
 */
$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-74s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

$root  = dirname( __DIR__ ) . '/';
$views = glob( $root . 'admin/views/*.php' );
$css   = file_get_contents( $root . 'assets/css/admin.css' );
$admin = file_get_contents( $root . 'admin/class-ds-toolkit-admin.php' );

echo "inline styles\n";
$styled = array();
foreach ( $views as $v ) {
	$n = preg_match_all( '/\sstyle\s*=\s*["\']/', file_get_contents( $v ) );
	if ( $n ) { $styled[ basename( $v ) ] = $n; }
}
chk( 'no view carries a style attribute', $styled, array() );
chk( 'the settings card builder carries none either', preg_match_all( '/\sstyle\s*=\s*["\']/', file_get_contents( $root . 'admin/class-ds-settings-cards.php' ) ), 0 );

echo "every class the views use for layout is defined\n";
$missing = array();
foreach ( $views as $v ) {
	// Only the classes introduced to replace the inline styles: each must exist in admin.css.
	if ( preg_match_all( '/\b(dst-(?:mt-\d+|w-(?:15|full)|is-hidden|is-dim|form-inline|card--(?:lead|note)|note-(?:lead|pre|foot)|title-(?:note|aside)|readonly-code|line(?:--strong)?|ok|bad|warn|card-row--(?:block|end)|card-info--wide|actions(?:-form)?|history(?:-list)?|mcp-note--full|field-inline--wide|radio|badge-danger|staff-note|prompt-group|ref(?:-pointer)?))\b/', file_get_contents( $v ), $m ) ) {
		foreach ( array_unique( $m[1] ) as $c ) {
			if ( ! preg_match( '/\.' . preg_quote( $c, '/' ) . '(?![\w-])/', $css ) ) { $missing[] = $c; }
		}
	}
}
chk( 'none missing from admin.css', array_values( array_unique( $missing ) ), array() );

echo "one home for the Logo Finder\n";
chk( 'no Logo Finder tab on the DS Toolkit page', (bool) preg_match( '/tab=logos\' \); \?>" class="dst-tab/', $admin ), false );
chk( 'the Media page is still registered', (bool) preg_match( "/add_media_page\(\s*'University Logo Finder'/", $admin ), true );
chk( 'an old bookmark of the tab is sent to the Media page', (bool) preg_match( "/'logos' === sanitize_key\(.*?wp_safe_redirect\( admin_url\( 'upload\.php\?page=ds-logo-finder' \) \);\s*exit;/s", $admin ), true );

echo "reference text sits behind a fold, settings stay outside it\n";
foreach ( array( 'page-global-css.php' => 'global_css_overrides', 'page-global-js.php' => 'global_js_enabled', 'page-mcp.php' => 'mcp_posts_pages_enabled' ) as $file => $field ) {
	$src   = file_get_contents( $root . 'admin/views/' . $file );
	$first = strpos( $src, '<details class="dst-group dst-ref">' );
	chk( $file . ': has a reference fold', false !== $first, true );
	chk( $file . ': its setting comes before the fold', false !== $first && strpos( $src, 'ds_toolkit_settings[' . $field . ']' ) < $first, true );
	chk( $file . ': the fold is closed by default', (bool) preg_match( '/<details class="dst-group dst-ref"[^>]*\sopen/', $src ), false );
	chk( $file . ': no setting is inside a fold', (bool) preg_match( '/<details class="dst-group dst-ref">(?:(?!<\/details>).)*name="ds_toolkit_settings\[/s', $src ), false );
}
$mcp = file_get_contents( $root . 'admin/views/page-mcp.php' );
chk( 'MCP: the config generator stays open on the page', strpos( $mcp, 'id="dst-mcp-generate"' ) > 0 && ! preg_match( '/<details class="dst-group dst-ref">(?:(?!<\/details>).)*id="dst-mcp-generate"/s', $mcp ), true );
chk( 'MCP: details open and close in pairs', substr_count( $mcp, '<details' ), substr_count( $mcp, '</details>' ) );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
