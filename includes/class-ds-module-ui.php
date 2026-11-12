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

	/**
	 * True where a module's settings form can be shown: the builder, its settings scripts, its AJAX calls, and
	 * WP-CLI. Module files are loaded on every request, so a picker that reads the database for its choices (saved
	 * templates, menus, ACF fields, the teams or seasons LeagueApps sent) builds them only when this is true. On a
	 * visitor's page view the list stays at its placeholder, which nothing reads: rendering uses the saved value,
	 * not the choices.
	 *
	 * Modules register on init, before the main query exists, and FLBuilderModel::is_builder_active() answers "no"
	 * until the queried post is known. The Roster team picker asked it there and so was always empty in the builder
	 * (1.10.41 to 1.17.4). This reads what Beaver Builder itself will read: ?fl_builder on the builder page and on
	 * the settings scripts it loads (also from the block editor), fl_builder_data on its AJAX posts. Signed-in only,
	 * so a visitor adding ?fl_builder to an address gets no lookups.
	 */
	public static function form_shown() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification -- only tells a builder request from a page view
		$asked = isset( $_GET['fl_builder'] ) || isset( $_GET['fl_builder_load_settings_config'] ) || isset( $_POST['fl_builder_data'] );
		return $asked && function_exists( 'is_user_logged_in' ) && is_user_logged_in();
	}

	/**
	 * Offer a module in the builder's module list only while editing a Beaver Themer layout
	 * or a saved template, the only places a context-driven module makes sense. Beaver keeps
	 * a disabled module registered and still builds its settings form, so an instance already
	 * placed anywhere keeps rendering and still opens its settings; only the picker hides it.
	 * The list is printed with the builder page, after the query is known, hence 'wp'.
	 */
	public static function offer_only_in_templates( $slug ) {
		add_action( 'wp', function () use ( $slug ) {
			if ( ! isset( $_GET['fl_builder'] ) || ! class_exists( 'FLBuilderModel' ) || ! isset( FLBuilderModel::$modules[ $slug ] ) ) { return; }
			if ( in_array( get_post_type( get_queried_object_id() ), array( 'fl-theme-layout', 'fl-builder-template' ), true ) ) { return; }
			FLBuilderModel::$modules[ $slug ]->enabled = false;
		} );
	}

	/**
	 * Normalise a colour for a CSS declaration: #hex (a bare hex gets its #), rgb[a]() /
	 * hsl[a](), var(--name[, fallback]) or a colour keyword. Anything else returns '', so a
	 * crafted setting ("#fff;}body{display:none") can never close the rule it is printed in.
	 */
	public static function color( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) { return ''; }
		$fn  = '(?:rgba?|hsla?)\(\s*[-+0-9.,%\s\/a-z]*\)';
		$hex = '#?[0-9a-f]{3,8}';
		if ( preg_match( '/^' . $hex . '$/i', $v ) ) { return '#' === $v[0] ? $v : '#' . $v; }
		if ( preg_match( '/^' . $fn . '$/i', $v ) ) { return $v; }
		if ( preg_match( '/^var\(\s*--[\w-]+\s*(?:,\s*(?:#[0-9a-f]{3,8}|' . $fn . '|[a-z]+)\s*)?\)$/i', $v ) ) { return $v; }
		if ( preg_match( '/^[a-z]{3,20}$/i', $v ) ) { return $v; } // transparent, currentColor, white
		return '';
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
		$pct = max( 0, min( 100, (int) $opacity_pct ) ); // printed into CSS: a number only
		return $c ? "color-mix(in srgb, {$c} {$pct}%, transparent)" : '';
	}

	/**
	 * Emit the FULL Theme Setting global Button style onto a selector:
	 * background, text colour, hover colours, complete border (style/width/
	 * colour + radius + box-shadow via FLBuilderCSS::border_field_rule), and
	 * typography. One call per button surface keeps every module's "match the
	 * site Button" mode honest — including borders and shadow.
	 *
	 * @param string      $selector       Full selector (already node-scoped).
	 * @param string|null $hover_selector Hover selector; null derives ":hover".
	 * @param bool        $base           Also emit the theme's base button size/weight/line height (button_base_css).
	 *                                    False for callers that size the button themselves on the same selector
	 *                                    (the Hero slider's Button Size, the Menu's CTA, which inherits the bar typography).
	 * @return bool True when global styles existed and something was emitted.
	 */
	public static function global_button_css( $selector, $hover_selector = null, $base = true ) {
		if ( ! class_exists( 'FLBuilderGlobalStyles' ) ) { return false; }
		$gs = FLBuilderGlobalStyles::get_settings( false );
		if ( ! $gs ) { return false; }
		if ( null === $hover_selector ) { $hover_selector = $selector . ':hover'; }

		if ( $base ) { self::button_base_css( $selector ); }

		$bg    = self::color( $gs->button_background ?? '' );
		$text  = self::color( $gs->button_color ?? '' );
		$bgh   = self::color( $gs->button_hover_background ?? '' ) ?: $bg;
		$texth = self::color( $gs->button_hover_color ?? '' ) ?: $text;

		$p = '';
		if ( $bg )   { $p .= "background:{$bg} !important;"; }
		if ( $text ) { $p .= "color:{$text} !important;"; }
		if ( '' !== $p ) { echo "{$selector} { {$p} }\n"; }
		if ( $bgh || $texth ) {
			echo "{$hover_selector} { " . ( $bgh ? "background:{$bgh} !important;" : '' ) . ( $texth ? "color:{$texth} !important;" : '' ) . "filter:none; }\n";
		}

		// Full border: style/width/colour + corner radius + box-shadow. Deferred
		// via FLBuilderCSS (the module css.php runner flushes it).
		if ( class_exists( 'FLBuilderCSS' ) && ! empty( $gs->button_border ) ) {
			// Deep-cast: the compound holds NESTED objects (radius / shadow / width);
			// a top-level (array) cast would leave them stdClass and border_field_rule
			// would silently skip them (is_array checks) — the shadow especially.
			$border = json_decode( wp_json_encode( $gs->button_border ), true );
			FLBuilderCSS::border_field_rule( array(
				'settings'     => (object) array( 'gbborder' => is_array( $border ) ? $border : array() ),
				'setting_name' => 'gbborder',
				'selector'     => $selector,
			) );
			$bh = self::color( $gs->button_border_hover_color ?? '' );
			if ( $bh ) { echo "{$hover_selector} { border-color:{$bh}; }\n"; }
		}

		if ( class_exists( 'FLBuilderCSS' ) && ! empty( $gs->button_typography ) ) {
			$gt = (object) array(
				'gbtypo'            => $gs->button_typography,
				'gbtypo_large'      => $gs->button_typography_large ?? '',
				'gbtypo_medium'     => $gs->button_typography_medium ?? '',
				'gbtypo_responsive' => $gs->button_typography_responsive ?? '',
			);
			FLBuilderCSS::typography_field_rule( array( 'settings' => $gt, 'setting_name' => 'gbtypo', 'selector' => $selector ) );
		}
		return true;
	}

	/**
	 * What a site Button looks like wherever the global Button settings are blank: the theme's own button size, weight and
	 * line height (Customizer, per breakpoint), plus the Theme Setting button padding where it is set. Without this a module
	 * "matching the site Button" kept its own hard-coded size where Theme Setting leaves the size blank: the Hero Banner's
	 * buttons were 13px bold beside the site's 16px regular buttons. Echoed BEFORE the deferred typography rules, so the
	 * global Button typography and any module's own button typography field still win wherever they set a value.
	 *
	 * @param string $selector Full selector (already node-scoped).
	 */
	public static function button_base_css( $selector ) {
		$num  = function ( $v, $d ) { return ( is_numeric( $v ) && (float) $v > 0 ) ? (float) $v : $d; };
		$size = $num( get_theme_mod( 'fl-button-font-size', '' ), 16 );
		$w    = (string) get_theme_mod( 'fl-button-font-weight', '' );
		$w    = preg_match( '/^([1-9]00|normal|bold)$/', $w ) ? $w : '400';
		$lh   = $num( get_theme_mod( 'fl-button-line-height', '' ), 1.2 );
		echo "{$selector} { font-size:{$size}px; font-weight:{$w}; line-height:{$lh}; }\n";
		list( $md, $sm ) = self::breakpoints();
		foreach ( array( '_medium' => $md, '_mobile' => $sm ) as $suffix => $bp ) {
			$s = get_theme_mod( 'fl-button-font-size' . $suffix, '' );
			if ( is_numeric( $s ) && (float) $s > 0 && (float) $s !== $size ) { echo "@media (max-width:{$bp}px){ {$selector} { font-size:" . (float) $s . "px; } }\n"; }
		}
		if ( ! class_exists( 'FLBuilderGlobalStyles' ) ) { return; }
		$gs = FLBuilderGlobalStyles::get_settings( false );
		if ( ! $gs ) { return; }
		$g  = class_exists( 'FLBuilderModel' ) ? FLBuilderModel::get_global_settings() : null;
		$lg = ( $g && ! empty( $g->large_breakpoint ) ) ? (int) $g->large_breakpoint : 1200;
		foreach ( array( '' => 0, '_large' => $lg, '_medium' => $md, '_responsive' => $sm ) as $suffix => $bp ) {
			$css = '';
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$v = $gs->{ "button_padding_{$side}{$suffix}" } ?? '';
				if ( '' !== $v && null !== $v && is_numeric( $v ) ) { $css .= "padding-{$side}:" . (float) $v . 'px;'; }
			}
			if ( '' === $css ) { continue; }
			echo $bp ? "@media (max-width:{$bp}px){ {$selector} { {$css} } }\n" : "{$selector} { {$css} }\n";
		}
	}

	/** [ medium, responsive ] global breakpoints in px, with Beaver Builder defaults. */
	public static function breakpoints() {
		$g  = class_exists( 'FLBuilderModel' ) ? FLBuilderModel::get_global_settings() : null;
		$md = ( $g && ! empty( $g->medium_breakpoint ) ) ? (int) $g->medium_breakpoint : 992;
		$sm = ( $g && ! empty( $g->responsive_breakpoint ) ) ? (int) $g->responsive_breakpoint : 768;
		return array( $md, $sm );
	}

	/**
	 * Every breakpoint of THIS site, widest first: setting suffix => max-width in px. Read from Beaver
	 * Builder's global settings, Large included, so a block follows the site instead of a width typed into
	 * its stylesheet (the modules carried 1024, 768, 767, 600, 480 and 1199, and only 2 of 82 responsive
	 * fields read the Large value an editor can type).
	 */
	public static function breakpoint_map() {
		$g = class_exists( 'FLBuilderModel' ) ? FLBuilderModel::get_global_settings() : null;
		list( $md, $sm ) = self::breakpoints();
		$lg = ( $g && ! empty( $g->large_breakpoint ) ) ? (int) $g->large_breakpoint : 1200;
		return array( '_large' => $lg, '_medium' => $md, '_responsive' => $sm );
	}

	/**
	 * The CSS for ONE responsive setting: the base rule, then one media rule per breakpoint where the editor
	 * typed a value (the base value is not repeated at a breakpoint that was left blank: it cascades).
	 *
	 *   echo DS_Module_UI::responsive_css( ".fl-node-$id .ds-x-grid", 'gap', $settings, 'grid_gap' );
	 *   echo DS_Module_UI::responsive_css( $sel, '--ds-cols', $settings, 'cols', 'int', '3' );
	 *
	 * @param string          $selector full selector, already node-scoped
	 * @param string|string[] $prop     one property, or several that take the same value
	 * @param object          $settings the module settings
	 * @param string          $key      the setting name; `<key>_large`, `<key>_medium` and `<key>_responsive` are read too
	 * @param string|callable $format   'px' (a number becomes Npx), 'int', 'unit' (a number plus the unit the
	 *                                  editor picked, read from `<key>_unit` per breakpoint), 'raw' (a clean
	 *                                  CSS length or keyword), or a callable( $raw ) returning the CSS value
	 *                                  or '' to skip
	 * @param string          $default  base value when the setting is blank ('' = no base rule)
	 * @return string CSS, one rule per line, or ''
	 */
	public static function responsive_css( $selector, $prop, $settings, $key, $format = 'px', $default = '' ) {
		$value = function ( $raw, $suffix = '' ) use ( $format, $settings, $key ) {
			if ( null === $raw || '' === $raw || is_array( $raw ) || is_object( $raw ) ) {
				return '';
			}
			if ( 'unit' === $format ) {
				$unit = (string) ( $settings->{ $key . $suffix . '_unit' } ?? ( $settings->{ $key . '_unit' } ?? 'px' ) );
				$unit = in_array( $unit, array( 'px', '%', 'vw', 'vh', 'em', 'rem' ), true ) ? $unit : 'px';
				return is_numeric( $raw ) ? ( 0 + $raw ) . $unit : '';
			}
			if ( is_callable( $format ) ) {
				return (string) call_user_func( $format, $raw );
			}
			$raw = trim( (string) $raw );
			if ( 'int' === $format ) {
				return is_numeric( $raw ) ? (string) (int) $raw : '';
			}
			if ( 'raw' === $format ) {
				// A length or a keyword only: nothing that could close the rule and start another.
				return preg_match( '/^[a-zA-Z0-9.%() ,+\-\/*]+$/', $raw ) ? $raw : '';
			}
			return is_numeric( $raw ) ? ( 0 + $raw ) . 'px' : '';
		};
		$rule = function ( $v ) use ( $prop ) {
			$out = '';
			foreach ( (array) $prop as $p ) {
				$out .= $p . ':' . $v . ';';
			}
			return $out;
		};
		$base = $value( $settings->{$key} ?? '' );
		if ( '' === $base ) {
			$base = (string) $default;
		}
		$css = '' !== $base ? "{$selector}{" . $rule( $base ) . "}\n" : '';
		foreach ( self::breakpoint_map() as $suffix => $bp ) {
			$v = $value( $settings->{ $key . $suffix } ?? '', $suffix );
			if ( '' !== $v ) {
				$css .= "@media(max-width:{$bp}px){{$selector}{" . $rule( $v ) . "}}\n";
			}
		}
		return $css;
	}

	/* ------------------------------------------------------------------
	 * Field builders (the house style in docs/BUILDER-FORMS.md).
	 *
	 * The same literal was typed again in every form: the colour field 275 times, with five private
	 * factories of it. New fields use these; an existing field moves over when its module is next edited.
	 * A builder only shapes the control: the setting KEY is the caller's and never changes.
	 * ---------------------------------------------------------------- */

	/** A colour: blank by default (so the preset or the theme shows through), with reset and global colours. */
	public static function color_field( $label, array $extra = array() ) {
		return array_merge( array( 'type' => 'color', 'label' => $label, 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ) ), $extra );
	}

	/** A size in px with a slider. Pass 'responsive' => true in $extra and emit it with responsive_css(). */
	public static function unit_field( $label, $min, $max, array $extra = array() ) {
		return array_merge( array( 'type' => 'unit', 'label' => $label, 'default' => '', 'description' => 'px', 'slider' => array( 'min' => $min, 'max' => $max, 'step' => 1 ) ), $extra );
	}

	/** On or off: a select storing 'yes' or 'no', listed No then Yes. */
	public static function yes_no_field( $label, $default = 'no', array $extra = array() ) {
		return array_merge( array( 'type' => 'select', 'label' => $label, 'default' => 'yes' === $default ? 'yes' : 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ) ), $extra );
	}

	/** Show or hide one part: a select storing 'show' or 'hide'. */
	public static function show_hide_field( $label, $default = 'show', array $extra = array() ) {
		return array_merge( array( 'type' => 'select', 'label' => $label, 'default' => 'hide' === $default ? 'hide' : 'show', 'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ) ), $extra );
	}

	/**
	 * A block's own width: a unit with px, % and vw, different per screen size. Emit it with
	 * responsive_css( $selector, 'max-width', $settings, $key, 'unit' ).
	 */
	public static function width_field( $label, array $extra = array() ) {
		return array_merge( array( 'type' => 'unit', 'label' => $label, 'default' => '', 'units' => array( 'px', '%', 'vw' ), 'default_unit' => 'px', 'responsive' => true, 'slider' => array( 'px' => array( 'min' => 0, 'max' => 1600, 'step' => 10 ), '%' => array( 'min' => 0, 'max' => 100, 'step' => 1 ), 'vw' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ) ), $extra );
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
