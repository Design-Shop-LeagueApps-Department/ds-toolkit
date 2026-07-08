<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shared Beaver Builder UI helpers for the in-house LeagueApps modules.
 *
 * Provides a reusable "Section Border & Header Divider" controls group plus the
 * matching CSS emitter, so every module exposes the same border/divider options
 * with one section in the form + one call in includes/frontend.css.php.
 *
 * Usage in a module's register_module() Style tab 'sections':
 *     'sections' => array_merge( array( ...module sections... ),
 *         DS_Module_UI::border_section( true ) )   // true = include header divider
 * and add 'ds_borders' to each style's toggle sections so it shows.
 *
 * Usage in includes/frontend.css.php:
 *     DS_Module_UI::emit_css( $node, $settings, '.ds-news', '.ds-news-head' );
 */
class DS_Module_UI {

	/** Normalise a colour: pass through rgb()/#hex/var(); else prefix #. Blank -> ''. */
	public static function color( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) { return ''; }
		return ( 0 === strpos( $v, 'rgb' ) || 0 === strpos( $v, '#' ) || 0 === strpos( $v, 'var' ) ) ? $v : '#' . $v;
	}

	/** Unit int-or-default: unset/blank -> $d, else (int) $v. */
	public static function u( $v, $d ) {
		return ( isset( $v ) && '' !== $v ) ? (int) $v : $d;
	}

	/** color() with a fallback used when the field is blank. */
	public static function colf( $v, $fallback ) {
		$c = self::color( $v );
		return '' === $c ? $fallback : $c;
	}

	/**
	 * Editor-authored title/label text: allow safe INLINE HTML (span/strong/em/
	 * b/i/u/small/mark/br, class + style attrs where sensible) and escape all
	 * else. Lets an editor write e.g. Lorem <span style="color:#069e33">Ipsum</span>
	 * in a Title field. style attributes are sanitised by WP's safecss filter.
	 * Use for MODULE FIELDS only — keep esc_html() for WP post titles.
	 */
	public static function inline( $text ) {
		return wp_kses( (string) $text, array(
			'span'   => array( 'class' => true, 'style' => true ),
			'strong' => array( 'class' => true, 'style' => true ),
			'b'      => array(),
			'em'     => array( 'class' => true, 'style' => true ),
			'i'      => array( 'class' => true ),
			'u'      => array(),
			'small'  => array(),
			'mark'   => array( 'class' => true, 'style' => true ),
			'br'     => array(),
			'sup'    => array(),
			'sub'    => array(),
		) );
	}

	/** color() wrapped in color-mix() at $opacity_pct toward transparent; '' if blank. Works on hex AND var(). */
	public static function mix( $v, $opacity_pct ) {
		$c = self::color( $v );
		return $c ? "color-mix(in srgb, {$c} {$opacity_pct}%, transparent)" : '';
	}

	/** [ medium, responsive ] global breakpoints in px, with Beaver Builder defaults. */
	public static function breakpoints() {
		$g  = class_exists( 'FLBuilderModel' ) ? FLBuilderModel::get_global_settings() : null;
		$md = ( $g && ! empty( $g->medium_breakpoint ) ) ? (int) $g->medium_breakpoint : 992;
		$sm = ( $g && ! empty( $g->responsive_breakpoint ) ) ? (int) $g->responsive_breakpoint : 768;
		return array( $md, $sm );
	}

	/**
	 * Normalised global Button values, so an in-house button can ride the theme button
	 * with one call instead of re-reading FLBuilderGlobalStyles in each module.
	 * Returns null when global styles are unavailable; else array:
	 *   bg, color, hover_bg, hover_color (colours via color()), radius (CSS "TL TR BR BL" or ''),
	 *   typography (object ready for FLBuilderCSS::typography_field_rule, setting_name 'gbtypo', or null).
	 */
	public static function global_button() {
		if ( ! class_exists( 'FLBuilderGlobalStyles' ) ) { return null; }
		$gs = FLBuilderGlobalStyles::get_settings( false );
		if ( ! $gs ) { return null; }
		$bg    = self::color( $gs->button_background ?? '' );
		$color = self::color( $gs->button_color ?? '' );
		$hbg   = self::color( $gs->button_hover_background ?? '' ) ?: $bg;
		$hcol  = self::color( $gs->button_hover_color ?? '' ) ?: $color;
		// Border radius compound (CSS order TL TR BR BL).
		$radius = '';
		$bb = isset( $gs->button_border ) ? (array) $gs->button_border : array();
		$r  = isset( $bb['radius'] ) ? (array) $bb['radius'] : array();
		$tl = $r['top_left'] ?? ''; $tr = $r['top_right'] ?? ''; $bl = $r['bottom_left'] ?? ''; $brr = $r['bottom_right'] ?? '';
		if ( '' !== ( $tl . $tr . $bl . $brr ) ) {
			$ru = function( $v ) { return ( '' === $v ? '0' : (int) $v ) . 'px'; };
			$radius = $ru( $tl ) . ' ' . $ru( $tr ) . ' ' . $ru( $brr ) . ' ' . $ru( $bl );
		}
		$typo = ! empty( $gs->button_typography ) ? (object) array(
			'gbtypo'            => $gs->button_typography,
			'gbtypo_large'      => $gs->button_typography_large ?? '',
			'gbtypo_medium'     => $gs->button_typography_medium ?? '',
			'gbtypo_responsive' => $gs->button_typography_responsive ?? '',
		) : null;
		return array( 'bg' => $bg, 'color' => $color, 'hover_bg' => $hbg, 'hover_color' => $hcol, 'radius' => $radius, 'typography' => $typo );
	}

	/**
	 * The reusable "Section Border & Divider" section.
	 *
	 * @param bool $include_divider Whether to include the header-divider controls.
	 * @return array  array( 'ds_borders' => array( … section … ) )
	 */
	public static function border_section( $include_divider = true ) {
		$border = array(
			'ds_border_style' => array(
				'type'    => 'select',
				'label'   => __( 'Section Border', 'ds-toolkit' ),
				'default' => 'none',
				'options' => array(
					'none'   => __( 'None', 'ds-toolkit' ),
					'solid'  => __( 'Solid', 'ds-toolkit' ),
					'dashed' => __( 'Dashed', 'ds-toolkit' ),
					'dotted' => __( 'Dotted', 'ds-toolkit' ),
				),
				'help'    => __( 'A border around the whole module section.', 'ds-toolkit' ),
				'toggle'  => array(
					'solid'  => array( 'fields' => array( 'ds_border_sides', 'ds_border_width', 'ds_border_color', 'ds_border_radius' ) ),
					'dashed' => array( 'fields' => array( 'ds_border_sides', 'ds_border_width', 'ds_border_color', 'ds_border_radius' ) ),
					'dotted' => array( 'fields' => array( 'ds_border_sides', 'ds_border_width', 'ds_border_color', 'ds_border_radius' ) ),
				),
			),
			'ds_border_sides'  => array(
				'type'    => 'select',
				'label'   => __( 'Border Sides', 'ds-toolkit' ),
				'default' => 'all',
				'options' => array(
					'all'        => __( 'All sides', 'ds-toolkit' ),
					'top'        => __( 'Top', 'ds-toolkit' ),
					'bottom'     => __( 'Bottom', 'ds-toolkit' ),
					'top_bottom' => __( 'Top + Bottom', 'ds-toolkit' ),
					'left'       => __( 'Left', 'ds-toolkit' ),
					'right'      => __( 'Right', 'ds-toolkit' ),
				),
			),
			'ds_border_width'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
			'ds_border_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-line-color)', 'show_reset' => true ),
			'ds_border_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
		);

		$divider = array();
		if ( $include_divider ) {
			$divider = array(
				'ds_divider_style' => array(
					'type'    => 'select',
					'label'   => __( 'Header Divider', 'ds-toolkit' ),
					'default' => 'none',
					'options' => array(
						'none'   => __( 'None', 'ds-toolkit' ),
						'solid'  => __( 'Solid', 'ds-toolkit' ),
						'dashed' => __( 'Dashed', 'ds-toolkit' ),
						'dotted' => __( 'Dotted', 'ds-toolkit' ),
					),
					'help'    => __( 'A divider rule under the header (heading row).', 'ds-toolkit' ),
					'toggle'  => array(
						'solid'  => array( 'fields' => array( 'ds_divider_width', 'ds_divider_color', 'ds_divider_gap' ) ),
						'dashed' => array( 'fields' => array( 'ds_divider_width', 'ds_divider_color', 'ds_divider_gap' ) ),
						'dotted' => array( 'fields' => array( 'ds_divider_width', 'ds_divider_color', 'ds_divider_gap' ) ),
					),
				),
				'ds_divider_width' => array( 'type' => 'unit', 'label' => __( 'Divider Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 10, 'step' => 1 ) ),
				'ds_divider_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Divider Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-line-color)', 'show_reset' => true ),
				'ds_divider_gap'   => array( 'type' => 'unit', 'label' => __( 'Divider Spacing', 'ds-toolkit' ), 'default' => '18', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ), 'help' => __( 'Space between the divider and the content below.', 'ds-toolkit' ) ),
			);
		}

		return array(
			'ds_borders' => array(
				'title'  => __( 'Border & Divider', 'ds-toolkit' ),
				'fields' => array_merge( $border, $divider ),
			),
		);
	}

	/**
	 * Emit the border + divider CSS.
	 *
	 * @param string $node        The node selector (".fl-node-<id>").
	 * @param object $settings    Module settings.
	 * @param string $section_sel Selector (relative to $node) for the section wrapper.
	 * @param string $header_sel  Optional selector for the header row (divider target).
	 */
	public static function emit_css( $node, $settings, $section_sel, $header_sel = '' ) {
		// ---- Section border ----
		$bstyle = $settings->ds_border_style ?? 'none';
		if ( in_array( $bstyle, array( 'solid', 'dashed', 'dotted' ), true ) ) {
			$bw    = isset( $settings->ds_border_width ) && '' !== $settings->ds_border_width ? (int) $settings->ds_border_width : 1;
			$bc    = self::color( $settings->ds_border_color ?? '' ) ?: 'var(--fl-global-line-color)';
			$sides = $settings->ds_border_sides ?? 'all';
			$rad   = isset( $settings->ds_border_radius ) && '' !== $settings->ds_border_radius ? (int) $settings->ds_border_radius : 0;
			$rule  = "{$bw}px {$bstyle} {$bc}";

			$props = array();
			switch ( $sides ) {
				case 'top':        $props[] = "border-top: {$rule}"; break;
				case 'bottom':     $props[] = "border-bottom: {$rule}"; break;
				case 'top_bottom': $props[] = "border-top: {$rule}"; $props[] = "border-bottom: {$rule}"; break;
				case 'left':       $props[] = "border-left: {$rule}"; break;
				case 'right':      $props[] = "border-right: {$rule}"; break;
				default:           $props[] = "border: {$rule}";
			}
			if ( $rad > 0 ) { $props[] = "border-radius: {$rad}px"; $props[] = 'overflow: hidden'; }
			echo "$node $section_sel { " . implode( '; ', $props ) . "; }\n";
		}

		// ---- Header divider ----
		$dstyle = $settings->ds_divider_style ?? 'none';
		if ( '' !== $header_sel && in_array( $dstyle, array( 'solid', 'dashed', 'dotted' ), true ) ) {
			$dw  = isset( $settings->ds_divider_width ) && '' !== $settings->ds_divider_width ? (int) $settings->ds_divider_width : 1;
			$dc  = self::color( $settings->ds_divider_color ?? '' ) ?: 'var(--fl-global-line-color)';
			$gap = isset( $settings->ds_divider_gap ) && '' !== $settings->ds_divider_gap ? (int) $settings->ds_divider_gap : 18;
			echo "$node $header_sel { border-bottom: {$dw}px {$dstyle} {$dc}; padding-bottom: {$gap}px; }\n";
		}
	}
}
