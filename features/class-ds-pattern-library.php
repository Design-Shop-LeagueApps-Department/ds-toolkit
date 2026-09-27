<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pattern library for Theme Setting (Alipio 2026-09-27: "in the theme setting pattern browse, could we connect to
 * bigger options of patterns?").
 *
 * Every "Pattern / image" field on the Theme Setting page gets a "Browse patterns" button beside its Media Library
 * button. It opens a gallery of the 330 tileable SVG patterns from pattern.monster (MIT, see
 * assets/patterns/LICENSE-pattern-monster.md), previewed in the site's palette. Choosing one writes a small SVG file to
 * uploads/ds-patterns/ and puts its URL in the field, exactly where an uploaded image URL goes today.
 *
 * Why a URL in the existing field: nothing that reads these settings changes (the theme paints url(...) as before, an
 * older toolkit renders it, a site that never opens the gallery is byte-identical), and nothing is added to the Media
 * Library. The SVG is always built here, from the bundled data, with a validated colour and numeric sizes; the browser
 * only sends the pattern's slug and those values, never markup.
 */
class DS_Pattern_Library {

	const AJAX = 'ds_pattern_make';
	const DIR  = 'ds-patterns';

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 20 );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax_make' ) );
	}

	/** Only on the Theme Setting page. */
	public function assets( $hook ) {
		if ( 'toplevel_page_ds-theme-setting' !== $hook ) { return; }
		wp_enqueue_style( 'ds-pattern-library', DS_TOOLKIT_URL . 'assets/css/pattern-library.css', array(), DS_TOOLKIT_VERSION );
		wp_enqueue_script( 'ds-pattern-library', DS_TOOLKIT_URL . 'assets/js/pattern-library.js', array(), DS_TOOLKIT_VERSION, true );
		wp_localize_script( 'ds-pattern-library', 'dsPatternLib', array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'action'  => self::AJAX,
			'nonce'   => wp_create_nonce( self::AJAX ),
			'data'    => DS_TOOLKIT_URL . 'assets/patterns/patterns.json?ver=' . DS_TOOLKIT_VERSION,
			'palette' => self::palette(),
		) );
	}

	/** The site palette: [{label, slug, color}] from Global Styles, so previews and swatches are the brand's own colours. */
	public static function palette() {
		$out = array();
		if ( class_exists( 'FLBuilderGlobalStyles' ) ) {
			$g = FLBuilderGlobalStyles::get_settings( false );
			foreach ( (array) ( $g->colors ?? array() ) as $c ) {
				$c = (array) $c;
				if ( empty( $c['label'] ) || empty( $c['color'] ) ) { continue; }
				$out[] = array( 'label' => (string) $c['label'], 'slug' => sanitize_title( $c['label'] ), 'color' => self::hex( (string) $c['color'] ) );
			}
		}
		// One swatch per colour: palettes repeat a colour under several names (Primary / Button).
		$seen = array();
		foreach ( $out as $c ) {
			if ( '' === $c['color'] ) { continue; }
			if ( isset( $seen[ $c['color'] ] ) ) { $seen[ $c['color'] ]['label'] .= ' / ' . $c['label']; $seen[ $c['color'] ]['alias'][] = $c['slug']; continue; }
			$c['alias'] = array( $c['slug'] ); $seen[ $c['color'] ] = $c;
		}
		return array_values( $seen );
	}

	/** #rgb / #rrggbb / rgb(a) -> #rrggbb, or '' when it is not a plain colour. */
	public static function hex( $c ) {
		$c = strtolower( trim( $c ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $c, $m ) ) { return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2]; }
		if ( preg_match( '/^#[0-9a-f]{6}$/', $c ) ) { return $c; }
		if ( preg_match( '/^#([0-9a-f]{6})[0-9a-f]{2}$/', $c, $m ) ) { return '#' . $m[1]; }
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/', $c, $m ) ) {
			return sprintf( '#%02x%02x%02x', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
		}
		return '';
	}

	/** One pattern from the bundled data, by slug. */
	private static function pattern( $slug ) {
		static $all = null;
		if ( null === $all ) {
			$json = file_get_contents( DS_TOOLKIT_PATH . 'assets/patterns/patterns.json' );
			$all  = array();
			foreach ( (array) json_decode( (string) $json, true ) as $p ) { $all[ $p['s'] ] = $p; }
		}
		return $all[ $slug ] ?? null;
	}

	/**
	 * The tile, as pattern.monster's download builds it with one foreground colour: every layer of a multi-layer
	 * pattern (vHeight 0) in that colour, or only the first row of a stacked one (vHeight > 0, tile shortened to match).
	 * Transparent background, so the field's own Background colour shows through; scale enlarges the tile itself.
	 */
	public static function svg( $p, $color, $opacity, $scale, $stroke ) {
		$layers = count( $p['p'] );
		$w = (float) $p['w'];
		$h = (float) $p['h'];
		if ( (float) $p['v'] > 0 ) {
			$h     = $h - (float) $p['v'] * ( $layers + 1 - 2 ); // maxColors = layers + 1; one colour => colorCounts = 2
			$paths = array( $p['p'][0] );
		} else {
			$paths = $p['p'];
		}
		$paint = ( 'fill' === $p['m'] )
			? " stroke='none' fill='" . $color . "'"
			: " stroke='" . $color . "' fill='none'" . ( 'stroke-join' === $p['m'] ? " stroke-linejoin='round' stroke-linecap='round'" : '' ) . " stroke-width='" . $stroke . "'";
		$body = '';
		foreach ( $paths as $d ) { $body .= preg_replace( '#\s*/>$#', $paint . '/>', trim( $d ) ); }
		$fmt = function ( $n ) { return rtrim( rtrim( number_format( $n, 3, '.', '' ), '0' ), '.' ); };
		return "<svg xmlns='http://www.w3.org/2000/svg' width='" . $fmt( $w * $scale ) . "' height='" . $fmt( $h * $scale ) . "' viewBox='0 0 " . $fmt( $w ) . ' ' . $fmt( $h ) . "'>"
			. "<g opacity='" . $fmt( $opacity ) . "'>" . $body . '</g></svg>';
	}

	public function ajax_make() {
		// Same gate as the Theme Setting page it serves.
		if ( ! check_ajax_referer( self::AJAX, 'nonce', false ) || ! class_exists( 'DS_Theme_Setting' ) || ! DS_Theme_Setting::can_access() ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$p = self::pattern( sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) ) );
		$color = self::hex( (string) wp_unslash( $_POST['color'] ?? '' ) );
		if ( ! $p || '' === $color ) { wp_send_json_error( array( 'message' => 'bad pattern or colour' ), 400 ); }
		// Rounded to the panel's slider steps, so the same look always names the same file (no near-duplicates).
		$step    = function ( $v, $s ) { return round( round( $v / $s ) * $s, 3 ); };
		$opacity = $step( max( 0.05, min( 1, (float) wp_unslash( $_POST['opacity'] ?? 0.2 ) ) ), 0.01 );
		$scale   = $step( max( 0.5, min( 8, (float) wp_unslash( $_POST['scale'] ?? 1 ) ) ), 0.1 );
		$stroke  = $step( max( 0.25, min( max( 0.5, (float) $p['ms'] ), (float) wp_unslash( $_POST['stroke'] ?? 1 ) ) ), 0.25 );
		$svg     = self::svg( $p, $color, round( $opacity, 3 ), round( $scale, 3 ), round( $stroke, 3 ) );

		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) { wp_send_json_error( array( 'message' => $up['error'] ), 500 ); }
		$dir  = trailingslashit( $up['basedir'] ) . self::DIR;
		$name = $p['s'] . '-' . substr( md5( $svg ), 0, 8 ) . '.svg'; // same choices -> same file
		if ( ! wp_mkdir_p( $dir ) ) { wp_send_json_error( array( 'message' => 'uploads not writable' ), 500 ); }
		if ( ! file_exists( "$dir/$name" ) && false === file_put_contents( "$dir/$name", $svg ) ) {
			wp_send_json_error( array( 'message' => 'could not write the pattern file' ), 500 );
		}
		wp_send_json_success( array( 'url' => trailingslashit( $up['baseurl'] ) . self::DIR . '/' . $name, 'file' => $name ) );
	}
}
