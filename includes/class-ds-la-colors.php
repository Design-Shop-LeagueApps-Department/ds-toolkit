<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Colour maths for the LeagueApps modules (Roster, Team Schedule): a brand colour in, plain hex
 * out. Tints and readable "ink" versions are worked out here, never with CSS color-mix(), which
 * the contrast tools misread as black.
 */
class DS_LA_Colors {

	/**
	 * The module palette from a brand and a card colour (any value DS_Module_UI::color() allows;
	 * empty brand = Global Styles Primary, empty card = white).
	 *
	 * @return array{tint:string,ink:string,on_brand:string}|null  null when the brand cannot be read
	 */
	public static function palette( $brand_value, $card_value ) {
		$brand = self::rgb( '' !== (string) $brand_value ? $brand_value : 'var(--fl-global-primary)' );
		if ( ! $brand ) {
			return null;
		}
		$card = self::rgb( '' !== (string) $card_value ? $card_value : '#ffffff' ) ?: array( 255, 255, 255 );
		$tint = self::mix( $brand, $card, 0.12 );
		$ink  = self::ink( $brand, array( $tint, $card, array( 236, 236, 236 ) ) );
		// Text on the solid brand (active filter, solid badges): white or dark, whichever reads.
		$w = self::ratio( array( 255, 255, 255 ), $brand );
		$d = self::ratio( array( 14, 27, 47 ), $brand );
		return array(
			'tint'     => self::hex( $tint ),
			'ink'      => self::hex( $ink ),
			'on_brand' => ( $w >= 4.5 || $w >= $d ) ? '#ffffff' : '#0e1b2f',
		);
	}

	private static function globals() {
		static $map = null;
		if ( null === $map ) {
			$map    = array();
			$styles = get_option( '_fl_builder_styles' );
			$colors = is_object( $styles ) ? ( $styles->colors ?? array() ) : ( is_array( $styles ) ? ( $styles['colors'] ?? array() ) : array() );
			foreach ( (array) $colors as $c ) {
				$c = (array) $c;
				if ( ! empty( $c['label'] ) && ! empty( $c['color'] ) ) {
					$map[ sanitize_title( $c['label'] ) ] = $c['color'];
				}
			}
		}
		return $map;
	}

	public static function rgb( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^var\(\s*--fl-global-([a-z0-9-]+)/i', $v, $m ) ) {
			$g = self::globals();
			return isset( $g[ $m[1] ] ) && 0 !== strpos( (string) $g[ $m[1] ], 'var(' ) ? self::rgb( $g[ $m[1] ] ) : null;
		}
		$v = ltrim( $v, '#' );
		if ( preg_match( '/^[0-9a-f]{3}$/i', $v ) ) {
			$v = $v[0] . $v[0] . $v[1] . $v[1] . $v[2] . $v[2];
		}
		if ( preg_match( '/^([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i', $v, $m ) ) {
			return array( hexdec( $m[1] ), hexdec( $m[2] ), hexdec( $m[3] ) );
		}
		if ( preg_match( '/rgba?\(\s*(\d+)\D+(\d+)\D+(\d+)/i', $v, $m ) ) {
			return array( (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		return null;
	}

	public static function hex( array $c ) {
		return sprintf( '#%02x%02x%02x', max( 0, min( 255, (int) round( $c[0] ) ) ), max( 0, min( 255, (int) round( $c[1] ) ) ), max( 0, min( 255, (int) round( $c[2] ) ) ) );
	}

	public static function mix( array $a, array $b, $t ) {
		return array( $a[0] * $t + $b[0] * ( 1 - $t ), $a[1] * $t + $b[1] * ( 1 - $t ), $a[2] * $t + $b[2] * ( 1 - $t ) );
	}

	public static function ratio( array $a, array $b ) {
		$lum = function ( $c ) {
			$f = function ( $x ) { $x /= 255; return $x <= 0.03928 ? $x / 12.92 : pow( ( $x + 0.055 ) / 1.055, 2.4 ); };
			return 0.2126 * $f( $c[0] ) + 0.7152 * $f( $c[1] ) + 0.0722 * $f( $c[2] );
		};
		$l1 = $lum( $a ); $l2 = $lum( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	/** Darken $c until it reaches $target against every background in $bgs. */
	public static function ink( array $c, array $bgs, $target = 4.6 ) {
		for ( $i = 0; $i < 14; $i++ ) {
			$min = 99;
			foreach ( $bgs as $bg ) {
				$min = min( $min, self::ratio( $c, $bg ) );
			}
			if ( $min >= $target ) {
				break;
			}
			$c = self::mix( $c, array( 0, 0, 0 ), 0.85 );
		}
		return $c;
	}
}
