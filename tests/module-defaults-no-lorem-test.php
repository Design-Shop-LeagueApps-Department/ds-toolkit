<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * No module field default is placeholder copy (1.14.0). Source scan, no WordPress needed:
 *
 *   php tests/module-defaults-no-lorem-test.php [plugin-dir]
 *
 * A module dropped onto a page renders its defaults, so a Lorem default reaches the partner's site
 * (QA rows B1/B10, code LOR): dslaunchpad7 had it in 12 saved templates and both Themer base layouts
 * on 2026-10-31. Text fields default to '' (an empty field reads as "fill me in"), link and button
 * labels to a real label. Every string inside a 'default' value is checked, repeater rows included.
 */
$root  = rtrim( $argv[1] ?? dirname( __DIR__ ), '/' ) . '/';
$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };

// Every string literal inside each `'default' => ...` value: [ file, line, string, field ].
$defaults = function ( $file ) {
	$t = token_get_all( file_get_contents( $file ) );
	$out = array(); $c = count( $t );
	for ( $i = 0; $i < $c; $i++ ) {
		if ( ! is_array( $t[ $i ] ) || T_CONSTANT_ENCAPSED_STRING !== $t[ $i ][0] || "'default'" !== $t[ $i ][1] ) { continue; }
		$j = $i + 1;
		while ( $j < $c && is_array( $t[ $j ] ) && T_WHITESPACE === $t[ $j ][0] ) { $j++; }
		if ( ! is_array( $t[ $j ] ) || T_DOUBLE_ARROW !== $t[ $j ][0] ) { continue; }
		// The field this default belongs to: the nearest `'key' => array(` before it.
		$field = '?';
		for ( $k = $i - 1; $k > 0; $k-- ) {
			if ( is_array( $t[ $k ] ) && T_ARRAY === $t[ $k ][0] ) {
				$m = $k - 1;
				while ( $m > 0 && is_array( $t[ $m ] ) && T_WHITESPACE === $t[ $m ][0] ) { $m--; }
				if ( is_array( $t[ $m ] ) && T_DOUBLE_ARROW === $t[ $m ][0] ) {
					$m--;
					while ( $m > 0 && is_array( $t[ $m ] ) && T_WHITESPACE === $t[ $m ][0] ) { $m--; }
					if ( is_array( $t[ $m ] ) && T_CONSTANT_ENCAPSED_STRING === $t[ $m ][0] && "'default'" !== $t[ $m ][1] ) { $field = trim( $t[ $m ][1], "'" ); break; }
				}
			}
		}
		// Walk the value to the comma or closing bracket at depth 0.
		$depth = 0;
		for ( $j++; $j < $c; $j++ ) {
			$tok = $t[ $j ];
			$s   = is_array( $tok ) ? $tok[1] : $tok;
			if ( '(' === $s || '[' === $s ) { $depth++; }
			elseif ( ')' === $s || ']' === $s ) { if ( 0 === $depth ) { break; } $depth--; }
			elseif ( ',' === $s && 0 === $depth ) { break; }
			elseif ( is_array( $tok ) && T_CONSTANT_ENCAPSED_STRING === $tok[0] ) { $out[] = array( $file, $tok[2], stripcslashes( substr( $tok[1], 1, -1 ) ), $field ); }
		}
	}
	return $out;
};

$files = glob( $root . 'modules/*/*.php' );
$is( 'module files found', count( $files ) > 10, true );
$all = array();
foreach ( $files as $f ) { $all = array_merge( $all, $defaults( $f ) ); }
$is( 'defaults found (scanner works)', count( $all ) > 500, true );

$lorem = '/\b(lorem|ipsum|dolor|amet|consectetur|adipiscing|eiusmod|tempor|incididunt|labore|aliqua)\b/i';
$hits  = array();
foreach ( $all as $d ) {
	if ( preg_match( $lorem, $d[2] ) ) { $hits[] = str_replace( $root, '', $d[0] ) . ':' . $d[1] . ' ' . $d[3] . ' = ' . $d[2]; }
}
foreach ( $hits as $h ) { echo "       lorem default: $h\n"; }
$is( 'no module default is lorem copy (repeater rows included)', count( $hits ), 0 );

// The labels that keep a real word, so a fresh module's button reads as a button.
$find = function ( $module, $field ) use ( $all, $root ) {
	foreach ( $all as $d ) { if ( $d[0] === $root . "modules/$module/$module.php" && $d[3] === $field ) { return $d[2]; } }
	return null;
};
$is( 'Hero Button 1 Text = Learn More', $find( 'ds-hero', 'btn1_text' ), 'Learn More' );
$is( 'Hero Button 2 Text is empty (one button on a fresh hero)', $find( 'ds-hero', 'btn2_text' ), '' );
$is( 'Hero subtext is empty', $find( 'ds-hero', 'subtext' ), '' );
$is( 'Hero eyebrow is empty', $find( 'ds-hero', 'eyebrow' ), '' );
$is( 'Hero Stat 1 Number is empty (the stats row hides)', $find( 'ds-hero', 'stat1_number' ), '' );
$is( 'CTA card Link Text = Learn More', $find( 'ds-cta', 'link_text' ), 'Learn More' );
$is( 'CTA Right-side Label is empty', $find( 'ds-cta', 'header_label' ), '' );
$is( 'Post Loop header Button Text = View All', $find( 'ds-post-loop', 'button_text' ), 'View All' );
$is( 'Marquee Label Text is empty', $find( 'ds-marquee', 'label_text' ), '' );

echo "\n$n checks, $fails failed\n";
exit( $fails ? 1 : 0 );
