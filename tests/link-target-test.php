<?php
/**
 * DS_Card::link_parts() honours the "Open in new window" tick.
 *
 * A BB `link` field with show_target saves the tick in a sibling key
 * (<field>_target), never inside the URL string, so every card module ignored
 * it until 1.10.9. Runs without WordPress: php tests/link-target-test.php
 */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ ); }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { return (string) $u; } }
require_once dirname( __DIR__ ) . '/includes/class-ds-card.php';

$cases = array(
	array( 'string + sibling _blank',  array( 'https://x.test/a', '_blank' ), array( 'https://x.test/a', '_blank' ) ),
	array( 'string + sibling _self',   array( 'https://x.test/a', '_self' ),  array( 'https://x.test/a', '_self' ) ),
	array( 'string, no sibling',       array( 'https://x.test/a' ),           array( 'https://x.test/a', '_self' ) ),
	array( 'empty link -> #',          array( '', '_blank' ),                 array( '#', '_blank' ) ),
	array( 'junk target -> _self',     array( 'https://x.test/a', 'evil' ),   array( 'https://x.test/a', '_self' ) ),
	array( 'array form wins',          array( array( 'url' => 'https://x.test/b', 'target' => '_self' ), '_blank' ), array( 'https://x.test/b', '_self' ) ),
	array( 'array w/o target uses sibling', array( array( 'url' => 'https://x.test/b' ), '_blank' ), array( 'https://x.test/b', '_blank' ) ),
	array( 'object form',              array( (object) array( 'url' => 'https://x.test/c', 'target' => '_blank' ) ), array( 'https://x.test/c', '_blank' ) ),
);
$fail = 0;
foreach ( $cases as $c ) {
	$got = call_user_func_array( array( 'DS_Card', 'link_parts' ), $c[1] );
	$ok  = $got === $c[2];
	$fail += $ok ? 0 : 1;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $c[0] . ( $ok ? '' : ' got ' . json_encode( $got ) ) . "\n";
}
echo ( count( $cases ) - $fail ) . ' passed, ' . $fail . " failed\n";
exit( $fail ? 1 : 0 );
