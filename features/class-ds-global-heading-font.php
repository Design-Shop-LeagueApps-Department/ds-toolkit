<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Load the Google font chosen in Theme Setting typography (notably Heading → "All").
 *
 * THE BUG THIS FIXES
 * ------------------
 * The "All" heading tab saves to the `h_typography` global style. Beaver Builder
 * happily RENDERS it — the layout CSS gets
 * `.fl-builder-content h1..h6 { font-family: Oswald, sans-serif; }` — but it never
 * requests the webfont, because its enqueue list
 * (FLBuilderFonts::add_fonts_for_global_css, class-fl-builder-fonts.php) covers
 *
 *     text_typography, h1..h6_typography, link_typography, button_typography
 *
 * and omits `h_typography`. The result is a font-family pointing at a font the
 * browser was never given, so every heading silently falls back to sans-serif.
 * The Google Fonts request came out as `family=Barlow:400` with no Oswald in it.
 *
 * Setting the font on H1-H6 instead would load it, but that defeats the point of
 * the "All" tab and means six edits per site. So we register the missing font
 * ourselves and the request becomes `family=Barlow:400|Oswald:400`.
 *
 * Only Google fonts need this. System fonts have nothing to fetch, and Typekit
 * families are loaded by the custom-typekit-fonts plugin — FLBuilderFonts::add_font()
 * already filters both out, so we can hand it anything.
 */
class DS_Global_Heading_Font {

	/** BB enqueues at wp_enqueue_scripts:9999, so register before that. */
	const PRIORITY = 9000;

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ), self::PRIORITY );
		// The builder UI renders its own preview head; cover that too.
		add_action( 'wp_head', array( $this, 'register' ), 1 );
	}

	/**
	 * Push every Global Styles typography font into Beaver Builder's font list.
	 *
	 * Today `h_typography` is the only key BB's own list misses, but this walks
	 * EVERY `*_typography` setting rather than hardcoding that one — so a font
	 * chosen in any Theme Setting group is requested, and a future BB or Theme
	 * Setting key cannot silently reintroduce the same bug.
	 *
	 * Re-registering keys BB already handles is free: add_font() de-duplicates by
	 * family/weight and ignores "Default" and system families.
	 *
	 * Responsive variants (`*_typography_large|medium|responsive`) are skipped —
	 * they only carry size/line-height overrides, never a family.
	 */
	public function register() {
		if ( ! class_exists( 'FLBuilderFonts' ) || ! class_exists( 'FLBuilderGlobalStyles' ) ) {
			return;
		}

		$gs = FLBuilderGlobalStyles::get_settings( false );
		if ( ! is_object( $gs ) && ! is_array( $gs ) ) {
			return;
		}

		foreach ( (array) $gs as $key => $value ) {
			if ( ! preg_match( '/_typography$/', $key ) ) {
				continue;
			}
			if ( preg_match( '/_(large|medium|responsive)$/', $key ) ) {
				continue;
			}

			$typo   = (array) $value;
			$family = isset( $typo['font_family'] ) ? $typo['font_family'] : '';
			if ( '' === $family || 'Default' === $family ) {
				continue;
			}

			$weight = ( isset( $typo['font_weight'] ) && '' !== $typo['font_weight'] && 'default' !== $typo['font_weight'] )
				? $typo['font_weight']
				: '400';

			FLBuilderFonts::add_font( array(
				'family' => $family,
				'weight' => $weight,
			) );
		}
	}
}
