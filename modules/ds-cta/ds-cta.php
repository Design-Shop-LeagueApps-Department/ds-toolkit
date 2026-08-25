<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps CTA — an in-house Beaver Builder call-to-action module.
 *
 * A multi-STYLE module (same pattern as ds-hero). Style 1 is the "Explore"
 * clip-card grid (modeled on the threehl design): a row of angled-corner cards,
 * each a background image with a dark gradient, an accent eyebrow, a big title,
 * a chevron, an animated top bar, and a hover lift. Cards can stagger.
 *
 * HOW TO ADD A NEW STYLE:
 *   1. Add it to self::styles() (key + label).
 *   2. Add a render_<key>() method (reuse the shared helpers).
 *   3. Add its option sections to the form and list those keys under the
 *      `cta_style` select's `toggle`.
 *   4. Scope style CSS under the auto-printed `.ds-cta--<key>` modifier class.
 *
 * No UABB dependency; fully SSH-editable.
 *
 * @class DS_CTA_Module
 */
class DS_CTA_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'CTA', 'ds-toolkit' ),
			'description'     => __( 'Angled clip-card CTA grid (multi-style).', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-cta/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-cta/',
			// Full preview re-render (not partial). The repeater + per-card "Cell Type"
			// toggle made the partial-refresh preview blank the module when a card's
			// settings changed shape; a full refresh re-renders it correctly every time.
			'partial_refresh' => false,
			'editor_export'   => false,
		) );
	}

	public static function styles() {
		return array(
			'style1' => __( 'Style 1 — Clip Cards', 'ds-toolkit' ),
			'style2' => __( 'Style 2 — Bordered Tiles', 'ds-toolkit' ),
			'style3' => __( 'Style 3 — Bento Grid', 'ds-toolkit' ),
			'style4' => __( 'Style 4 — Big Hero (Contact)', 'ds-toolkit' ),
			'style5' => __( 'Style 5 — Motion Cards (image + logo overlay)', 'ds-toolkit' ),
			'style6' => __( 'Style 6 — Program Cards (manual list)', 'ds-toolkit' ),
		);
	}

	/**
	 * Style 6 — Program Cards: a hand-built list of cards (date, sub-heading,
	 * title, description, link/button, icon or image).
	 *
	 * This card used to live in the Post Loop module as `card_layout = program`,
	 * which was wrong: it never queries a post type. It renders through the shared
	 * DS_Program_Cards so legacy Post Loop instances and this style are identical.
	 */
	public function render_style6() {
		DS_Program_Cards::render(
			$this->settings,
			__( 'No program cards yet. Add them in the Program Cards list.', 'ds-toolkit' )
		);
	}

	/** Style 5 — Motion Cards: elevated image cards with bg effects + animated logo overlay. */
	public function render_style5() {
		$s = $this->settings;

		$fx_base  = in_array( $s->mcfx_base ?? 'none', array( 'none', 'blur', 'brightness', 'grayscale', 'opacity', 'overlay' ), true ) ? ( $s->mcfx_base ?? 'none' ) : 'none';
		$fx_hover = in_array( $s->mcfx_hover ?? 'zoom-in', array( 'none', 'clear', 'brighten', 'zoom-in', 'zoom-out' ), true ) ? ( $s->mcfx_hover ?? 'zoom-in' ) : 'zoom-in';
		$logo_in  = preg_replace( '/[^a-z-]/', '', (string) ( $s->mcl_in ?? 'fade-up' ) );
		$logo_hov = preg_replace( '/[^a-z-]/', '', (string) ( $s->mcl_hover ?? 'float' ) );
		$logo_pos = in_array( $s->mcl_pos ?? 'center', array( 'center', 'top-left', 'top-right', 'bottom-left', 'bottom-right' ), true ) ? ( $s->mcl_pos ?? 'center' ) : 'center';
		$logo_display = in_array( $s->mcl_display ?? 'always', array( 'always', 'hover' ), true ) ? ( $s->mcl_display ?? 'always' ) : 'always';
		$gradient = ( $s->mcgrad_enable ?? 'yes' ) === 'yes';

		$mods = 'ds-cta ds-cta--style5 ds-mcards ds-mcards--fx-' . $fx_base . ' ds-mcards--hov-' . $fx_hover . ' ds-mcards--logo-' . $logo_display;
		echo '<section class="' . esc_attr( $mods ) . '"><div class="ds-cta-wrap">';

		if ( ( $s->show_header ?? 'yes' ) === 'yes' && ( ! empty( $s->heading ) || ! empty( $s->header_label ) ) ) {
			echo '<div class="ds-cta-head">';
			if ( ! empty( $s->heading ) ) { echo '<h2 class="ds-cta-heading">' . $this->heading_html( $s->heading ) . '</h2>'; }
			if ( ! empty( $s->header_label ) ) { echo '<span class="ds-cta-head-label">' . esc_html( $s->header_label ) . '</span>'; }
			echo '</div>';
		}

		$cards = isset( $s->cards ) && is_array( $s->cards ) ? $s->cards : array();
		if ( empty( $cards ) ) {
			if ( FLBuilderModel::is_builder_active() ) { echo '<p style="padding:14px;opacity:.7">' . esc_html__( 'Add cards in the module settings.', 'ds-toolkit' ) . '</p>'; }
			echo '</div></section>';
			return;
		}

		echo '<div class="ds-mcard-grid">';
		foreach ( $cards as $card ) {
			$card = (object) $card;
			$img  = ! empty( $card->image ) ? $this->photo_url( $card->image ) : '';
			$logo = ! empty( $card->logo ) ? $this->photo_url( $card->logo, 'medium' ) : '';
			list( $url, $target ) = $this->link_parts( $card->link ?? '' );
			$rel  = '_blank' === $target ? ' rel="noopener noreferrer"' : '';

			// Skip stray blank repeater rows: they rendered as full-size empty cards,
			// pushing phantom rows / empty space below the real grid (GH #89).
			// link_parts() maps an empty link to '#', so '#' counts as no-link here.
			if ( '' === $img && '' === $logo && '' === trim( (string) ( $card->title ?? '' ) ) && '' === trim( (string) ( $card->eyebrow ?? '' ) ) && ( '' === $url || '#' === $url ) ) {
				continue;
			}

			echo '<a class="ds-mcard" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>';
			echo '<span class="ds-mcard-bg"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . ' aria-hidden="true"></span>';
			echo '<span class="ds-mcard-tint" aria-hidden="true"></span>';
			if ( $gradient ) {
				echo '<span class="ds-mcard-gradient" aria-hidden="true"></span>';
			}
			if ( '' !== $logo ) {
				echo '<span class="ds-mcard-logo ds-mcard-logo--' . esc_attr( $logo_pos ) . ' ds-mcin--' . esc_attr( $logo_in ) . ' ds-mchov--' . esc_attr( $logo_hov ) . '"><img src="' . esc_url( $logo ) . '" alt="" loading="lazy" /></span>';
			}
			echo '<span class="ds-mcard-body">';
			if ( ! empty( $card->eyebrow ) ) { echo '<span class="ds-mcard-eyebrow">' . DS_Module_UI::inline( $card->eyebrow ) . '</span>'; }
			if ( ! empty( $card->title ) ) { echo '<span class="ds-mcard-title">' . DS_Module_UI::inline( $card->title ) . '</span>'; }
			echo '</span></a>';
		}
		echo '</div></div></section>';
	}

	/** Heading markup: escape, {a}..{/a} -> accent span, newlines -> <br>. */
	private function heading_html( $raw ) {
		$h = DS_Module_UI::inline( (string) $raw ); // safe inline HTML (span/strong/em...) allowed
		$h = str_replace( array( '{a}', '{/a}' ), array( '<span class="ds-cta-accent">', '</span>' ), $h );
		$h = str_replace( array( '{outline}', '{/outline}' ), array( '<span class="ds-outline-text">', '</span>' ), $h );
		return nl2br( $h );
	}

	private function photo_url( $val, $size = 'large' ) {
		if ( is_array( $val ) )  { $val = $val['id'] ?? ( $val['url'] ?? '' ); }
		elseif ( is_object( $val ) ) { $val = $val->id ?? ( $val->url ?? '' ); }
		if ( is_numeric( $val ) ) { $u = wp_get_attachment_image_url( (int) $val, $size ); return $u ? $u : ''; }
		return $val ? esc_url( (string) $val ) : '';
	}

	/** Normalise a BB link field (string URL or {url,target} object/array). */
	private function link_parts( $link ) {
		$url = ''; $target = '_self';
		if ( is_array( $link ) )      { $url = $link['url'] ?? ''; $target = $link['target'] ?? '_self'; }
		elseif ( is_object( $link ) ) { $url = $link->url ?? '';  $target = $link->target ?? '_self'; }
		else { $url = (string) $link; }
		return array( esc_url( $url ?: '#' ), $target === '_blank' ? '_blank' : '_self' );
	}

	/** Entry point — dispatch to the selected style. */
	public function render_cta() {
		$style  = isset( $this->settings->cta_style ) ? preg_replace( '/[^a-z0-9_]/', '', $this->settings->cta_style ) : 'style1';
		$method = 'render_' . $style;
		if ( ! array_key_exists( $style, self::styles() ) || ! method_exists( $this, $method ) ) {
			$method = 'render_style1';
		}
		$this->$method();
	}

	/** Style 1 — Explore clip-card grid. */
	public function render_style1() {
		$s = $this->settings;

		$mods = 'ds-cta ds-cta--style1';
		if ( ( $s->stagger ?? 'yes' ) === 'yes' ) { $mods .= ' ds-cta--stagger'; }
		if ( ( $s->hover_lift ?? 'yes' ) === 'yes' ) { $mods .= ' ds-cta--lift'; }

		echo '<section class="' . esc_attr( $mods ) . '"><div class="ds-cta-wrap">';

		// Optional header row.
		if ( ( $s->show_header ?? 'yes' ) === 'yes' && ( ! empty( $s->heading ) || ! empty( $s->header_label ) ) ) {
			echo '<div class="ds-cta-head">';
			if ( ! empty( $s->heading ) ) {
				echo '<h2 class="ds-cta-heading">' . $this->heading_html( $s->heading ) . '</h2>';
			}
			if ( ! empty( $s->header_label ) ) {
				echo '<span class="ds-cta-head-label">' . esc_html( $s->header_label ) . '</span>';
			}
			echo '</div>';
		}

		$cards = isset( $s->cards ) && is_array( $s->cards ) ? $s->cards : array();
		if ( empty( $cards ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:14px;opacity:.7">' . esc_html__( 'Add cards in the module settings.', 'ds-toolkit' ) . '</p>';
			}
			echo '</div></section>';
			return;
		}

		echo '<div class="ds-cta-grid">';
		foreach ( $cards as $card ) {
			$card  = (object) $card;
			$img   = ! empty( $card->image ) ? $this->photo_url( $card->image ) : '';
			list( $url, $target ) = $this->link_parts( $card->link ?? '' );
			$rel   = '_blank' === $target ? ' rel="noopener noreferrer"' : '';

			echo '<a class="ds-cta-card" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>';
			echo '<div class="ds-cta-card-bg"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
			echo '<div class="ds-cta-card-overlay" aria-hidden="true"></div>';
			echo '<div class="ds-cta-card-body">';
			if ( ! empty( $card->eyebrow ) ) {
				echo '<div class="ds-cta-card-eyebrow">' . DS_Module_UI::inline( $card->eyebrow ) . '</div>';
			}
			echo '<div class="ds-cta-card-row"><h3 class="ds-cta-card-title">' . DS_Module_UI::inline( $card->title ?? '' ) . '</h3>';
			echo '<span class="ds-cta-card-chevron" aria-hidden="true">&rsaquo;</span></div>';
			echo '</div>';
			echo '<span class="ds-cta-card-bar" aria-hidden="true"></span>';
			echo '</a>';
		}
		echo '</div></div></section>';
	}

	/** Style 2 — Bordered tiles (numbered eyebrow, title, "Express Interest" link). */
	public function render_style2() {
		$s = $this->settings;

		$mods = 'ds-cta ds-cta--style2';
		if ( ( $s->img_zoom ?? 'yes' ) === 'yes' ) { $mods .= ' ds-cta--zoom'; }

		echo '<section class="' . esc_attr( $mods ) . '"><div class="ds-cta-wrap">';

		if ( ( $s->show_header ?? 'yes' ) === 'yes' && ( ! empty( $s->heading ) || ! empty( $s->header_label ) ) ) {
			echo '<div class="ds-cta-head">';
			if ( ! empty( $s->heading ) ) { echo '<h2 class="ds-cta-heading">' . $this->heading_html( $s->heading ) . '</h2>'; }
			if ( ! empty( $s->header_label ) ) { echo '<span class="ds-cta-head-label">' . esc_html( $s->header_label ) . '</span>'; }
			echo '</div>';
		}

		$cards = isset( $s->cards ) && is_array( $s->cards ) ? $s->cards : array();
		if ( empty( $cards ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:14px;opacity:.7">' . esc_html__( 'Add cards in the module settings.', 'ds-toolkit' ) . '</p>';
			}
			echo '</div></section>';
			return;
		}

		$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

		echo '<div class="ds-cta-grid">';
		foreach ( $cards as $card ) {
			$card = (object) $card;
			$img  = ! empty( $card->image ) ? $this->photo_url( $card->image ) : '';
			list( $url, $target ) = $this->link_parts( $card->link ?? '' );
			$rel = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
			$lt  = trim( (string) ( $card->link_text ?? '' ) );

			echo '<a class="ds-cta-tile" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>';
			echo '<div class="ds-cta-tile-bg"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
			echo '<div class="ds-cta-tile-overlay" aria-hidden="true"></div>';
			echo '<div class="ds-cta-tile-body">';
			if ( ! empty( $card->eyebrow ) ) { echo '<div class="ds-cta-tile-num">' . DS_Module_UI::inline( $card->eyebrow ) . '</div>'; }
			echo '<h3 class="ds-cta-tile-title">' . DS_Module_UI::inline( $card->title ?? '' ) . '</h3>';
			if ( '' !== $lt ) { echo '<span class="ds-cta-tile-go">' . esc_html( $lt ) . ' ' . $arrow . '</span>'; }
			echo '</div>';
			echo '</a>';
		}
		echo '</div></div></section>';
	}

	/** Style 3 — Bento grid (mixed image + text/CTA cells with per-cell spans). */
	public function render_style3() {
		$s = $this->settings;

		echo '<section class="ds-cta ds-cta--style3"><div class="ds-cta-wrap">';

		if ( ( $s->show_header ?? 'yes' ) === 'yes' && ( ! empty( $s->heading ) || ! empty( $s->header_desc ) ) ) {
			echo '<div class="ds-cta-bento-head">';
			if ( ! empty( $s->heading ) ) { echo '<h2 class="ds-cta-heading">' . $this->heading_html( $s->heading ) . '</h2>'; }
			if ( ! empty( $s->header_desc ) ) { echo '<div class="ds-cta-bento-desc">' . wpautop( wp_kses_post( $s->header_desc ) ) . '</div>'; }
			echo '</div>';
		}

		$cards = isset( $s->cards ) && is_array( $s->cards ) ? $s->cards : array();
		if ( empty( $cards ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:14px;opacity:.7">' . esc_html__( 'Add cells in the module settings.', 'ds-toolkit' ) . '</p>';
			}
			echo '</div></section>';
			return;
		}

		$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

		// Hover behaviour is module-wide; every cell responds to its own hover.
		$hover = in_array( $s->bento_hover ?? 'lift', array( 'lift', 'zoom', 'none' ), true ) ? ( $s->bento_hover ?? 'lift' ) : 'lift';

		echo '<div class="ds-cta-bento ds-cta-bento--hover-' . esc_attr( $hover ) . '">';
		foreach ( $cards as $cell ) {
			$cell = (object) $cell;
			$cs   = max( 1, (int) ( $cell->col_span ?? 1 ) );
			$rs   = max( 1, (int) ( $cell->row_span ?? 1 ) );
			$type = ( ( $cell->type ?? 'image' ) === 'text' ) ? 'text' : 'image';
			list( $url, $target ) = $this->link_parts( $cell->link ?? '' );
			$rel = '_blank' === $target ? ' rel="noopener noreferrer"' : '';

			$eyebrow = trim( (string) ( $cell->eyebrow ?? '' ) );
			$title   = trim( (string) ( $cell->title ?? '' ) );
			$desc    = trim( (string) ( $cell->desc ?? '' ) );
			$num     = trim( (string) ( $cell->number ?? '' ) );
			$badge   = trim( (string) ( $cell->badge ?? '' ) );
			$lt      = trim( (string) ( $cell->link_text ?? '' ) );

			// Colour scheme for this cell. 'auto' is the historical look, so a card
			// saved before this release renders exactly as it did.
			$tr  = in_array( $cell->treatment ?? 'auto', array( 'auto', 'dark', 'light', 'accent' ), true ) ? ( $cell->treatment ?? 'auto' ) : 'auto';
			$cls = 'ds-cta-bento-cell ds-cta-bento-' . ( 'text' === $type ? 'text' : 'img' );
			if ( 'auto' !== $tr ) { $cls .= ' ds-cta-bento-cell--' . $tr; }

			$style = 'grid-column:span ' . $cs . ';grid-row:span ' . $rs . ';';
			$cbg   = DS_Module_UI::color( $cell->cell_bg ?? '' );
			if ( '' !== $cbg ) { $style .= 'background:' . $cbg . ';'; }
			$ctx = DS_Module_UI::color( $cell->cell_text ?? '' );
			if ( '' !== $ctx ) { $style .= 'color:' . $ctx . ';'; }

			if ( 'text' === $type ) {
				// Markup order and nesting kept identical to before — the existing
				// .ds-cta-bento-text flex rules lay out these DIRECT children, so a
				// wrapper here would silently re-flow every Bento already in the fleet.
				echo '<div class="' . esc_attr( $cls ) . '" style="' . esc_attr( $style ) . '">';
				if ( '' !== $num )     { echo '<span class="ds-cta-bento-num">' . esc_html( $num ) . '</span>'; }
				if ( '' !== $badge )   { echo '<span class="ds-cta-bento-badge">' . esc_html( $badge ) . '</span>'; }
				if ( '' !== $eyebrow ) { echo '<div class="ds-cta-bento-eyebrow">' . DS_Module_UI::inline( $eyebrow ) . '</div>'; }
				if ( '' !== $title )   { echo '<h3 class="ds-cta-bento-title">' . DS_Module_UI::inline( $title ) . '</h3>'; }
				if ( '' !== $desc )    { echo '<div class="ds-cta-bento-celldesc">' . wpautop( wp_kses_post( $desc ) ) . '</div>'; }
				if ( '' !== $lt ) {
					echo '<a class="ds-cta-bento-btn" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>' . esc_html( $lt ) . '</a>';
				}
				echo '</div>';
			} else {
				$img  = ! empty( $cell->image ) ? $this->photo_url( $cell->image ) : '';
				$has  = ( $url && '#' !== $url );
				$tag  = $has ? 'a' : 'div';
				$attr = $has ? ' href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel : '';

				// GH #123: image cells used to render the eyebrow and nothing else, so a
				// Title / Description / Link Text typed on one silently vanished. They
				// render now, in a content block over the image.
				$block = ( '' !== $title || '' !== $desc || '' !== $num || '' !== $badge || '' !== $lt );

				// Blank overlay means "decide from the content": a bare eyebrow is a pill
				// with its own backing, so it needs none and stays pixel-identical to
				// before; a title over a photo is unreadable without one.
				$ovr = $cell->cell_overlay ?? '';
				$ov  = ( '' === $ovr || null === $ovr ) ? ( $block ? 45 : 0 ) : max( 0, min( 100, (int) $ovr ) );
				if ( $ov > 0 ) { $style .= '--ds-bento-ov:' . round( $ov / 100, 2 ) . ';'; }

				echo '<' . $tag . ' class="' . esc_attr( $cls ) . '" style="' . esc_attr( $style ) . '"' . $attr . '>';
				echo '<div class="ds-cta-bento-bg"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
				if ( $ov > 0 ) { echo '<div class="ds-cta-bento-ov"></div>'; }

				if ( $block ) {
					echo '<div class="ds-cta-bento-content">';
					if ( '' !== $num )     { echo '<span class="ds-cta-bento-num">' . esc_html( $num ) . '</span>'; }
					if ( '' !== $badge )   { echo '<span class="ds-cta-bento-badge">' . esc_html( $badge ) . '</span>'; }
					if ( '' !== $eyebrow ) { echo '<div class="ds-cta-bento-eyebrow">' . DS_Module_UI::inline( $eyebrow ) . '</div>'; }
					if ( '' !== $title )   { echo '<h3 class="ds-cta-bento-title">' . DS_Module_UI::inline( $title ) . '</h3>'; }
					if ( '' !== $desc )    { echo '<div class="ds-cta-bento-celldesc">' . wpautop( wp_kses_post( $desc ) ) . '</div>'; }
					if ( '' !== $lt ) {
						// The whole cell is already the anchor when it has a link, and an
						// anchor inside an anchor is invalid HTML that browsers unnest.
						echo 'a' === $tag
							? '<span class="ds-cta-bento-btn">' . esc_html( $lt ) . '</span>'
							: '<a class="ds-cta-bento-btn" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>' . esc_html( $lt ) . '</a>';
					}
					echo '</div>';
				} elseif ( '' !== $eyebrow ) {
					// Unchanged: the standalone corner pill, for image cells that carry
					// nothing but an eyebrow.
					echo '<div class="ds-cta-bento-eyebrow">' . esc_html( $eyebrow ) . '</div>';
				}
				echo '</' . $tag . '>';
			}
		}
		echo '</div></div></section>';
	}

	/* --------------------------------------------- Style 4 helpers (partner data) */

	/** Read a Partner Settings text/URL value (ACF option, fallback to options_ row). */
	private function partner_field( $field ) {
		if ( function_exists( 'get_field' ) ) {
			$v = get_field( $field, 'option' );
		} else {
			$v = get_option( 'options_' . $field );
		}
		return is_string( $v ) ? trim( $v ) : '';
	}

	/** Small inline UI glyphs (contact rows + button arrow). Stroke style, currentColor. */
	public static function ui_svg( $key ) {
		$icons = array(
			'location' => '<path d="M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
			'email'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/>',
			'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92Z"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		);
		if ( empty( $icons[ $key ] ) ) { return ''; }
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[ $key ] . '</svg>';
	}

	/**
	 * Social icons row built from Partner Settings, reusing the LeagueApps Social
	 * module's network registry + brand SVGs (same plugin). Empty networks are
	 * skipped; returns '' when nothing is set (a builder hint shows in the editor).
	 */
	private function partner_social_html( $target ) {
		$target = '_self' === $target ? '_self' : '_blank';
		$rel    = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
		$out    = '';

		if ( class_exists( 'DS_Social_Module' ) ) {
			foreach ( DS_Social_Module::networks() as $key => $net ) {
				$url = $this->partner_field( $net['field'] );
				if ( '' === $url ) { continue; }
				$out .= sprintf(
					'<a class="ds-cta-hero-social-link ds-cta-hero-%1$s" href="%2$s" target="%3$s"%4$s aria-label="%5$s">%6$s</a>',
					esc_attr( $key ), esc_url( $url ), esc_attr( $target ), $rel,
					esc_attr( $net['label'] ), DS_Social_Module::svg( $key )
				);
			}
		}

		if ( '' === $out && FLBuilderModel::is_builder_active() ) {
			return '<span class="ds-cta-hero-social-hint">' . esc_html__( 'No Partner Settings social links set — this row is empty on the live site.', 'ds-toolkit' ) . '</span>';
		}
		return $out;
	}

	/**
	 * Style 4 — Big Hero contact CTA. A full-bleed section with a background image
	 * + dark overlay and a centred (or left-aligned) framed box: eyebrow / sub-heading,
	 * big heading, contact details + social icons pulled from Partner Settings (each
	 * toggleable), and a big button. Designed to sit before the footer.
	 */
	public function render_style4() {
		$s = $this->settings;

		$align = in_array( $s->hero_align ?? 'center', array( 'left', 'center' ), true ) ? ( $s->hero_align ?? 'center' ) : 'center';
		$box   = ( $s->hero_box ?? 'yes' ) === 'yes';
		$mods  = 'ds-cta ds-cta--style4 ds-cta--style4-' . $align;
		if ( $box ) { $mods .= ' ds-cta--style4-box'; }

		$bg = ! empty( $s->hero_image ) ? $this->photo_url( $s->hero_image, 'full' ) : '';

		echo '<section class="' . esc_attr( $mods ) . '">';
		echo '<div class="ds-cta-hero-bg"' . ( $bg ? ' style="background-image:url(' . esc_url( $bg ) . ')"' : '' ) . ' aria-hidden="true"></div>';
		echo '<div class="ds-cta-hero-overlay" aria-hidden="true"></div>';
		echo '<div class="ds-cta-wrap"><div class="ds-cta-hero-inner">';

		if ( $box && ( $s->box_top_bar ?? 'yes' ) === 'yes' ) {
			echo '<span class="ds-cta-hero-bar" aria-hidden="true"></span>';
		}

		// Eyebrow / sub-heading.
		if ( ( $s->hero_show_eyebrow ?? 'yes' ) === 'yes' && ! empty( $s->hero_eyebrow ) ) {
			echo '<div class="ds-cta-hero-eyebrow">';
			if ( ( $s->hero_eyebrow_line ?? 'yes' ) === 'yes' ) { echo '<span class="ds-cta-hero-eyebrow-line" aria-hidden="true"></span>'; }
			echo '<span>' . DS_Module_UI::inline( $s->hero_eyebrow ) . '</span></div>';
		}

		// Heading.
		if ( ! empty( $s->hero_heading ) ) {
			echo '<h2 class="ds-cta-hero-heading">' . $this->heading_html( $s->hero_heading ) . '</h2>';
		}

		// Contact details — pulled from Partner Settings, each toggleable.
		$icons   = ( $s->contact_icons ?? 'yes' ) === 'yes';
		$contact = array();
		if ( ( $s->show_location ?? 'yes' ) === 'yes' ) {
			$v = $this->partner_field( 'partner_address' );
			if ( '' !== $v ) {
				$contact[] = '<span class="ds-cta-hero-citem ds-cta-hero-loc">' . ( $icons ? self::ui_svg( 'location' ) : '' ) . '<span>' . esc_html( $v ) . '</span></span>';
			}
		}
		if ( ( $s->show_email ?? 'yes' ) === 'yes' ) {
			$v = $this->partner_field( 'partner_email' );
			if ( '' !== $v && is_email( $v ) ) {
				$contact[] = '<a class="ds-cta-hero-citem ds-cta-hero-email" href="mailto:' . esc_attr( antispambot( $v ) ) . '">' . ( $icons ? self::ui_svg( 'email' ) : '' ) . '<span>' . esc_html( $v ) . '</span></a>';
			}
		}
		if ( ( $s->show_phone ?? 'no' ) === 'yes' ) {
			$v = $this->partner_field( 'partner_phone' );
			if ( '' !== $v ) {
				$tel = preg_replace( '/[^0-9+]/', '', $v );
				$contact[] = '<a class="ds-cta-hero-citem ds-cta-hero-phone" href="tel:' . esc_attr( $tel ) . '">' . ( $icons ? self::ui_svg( 'phone' ) : '' ) . '<span>' . esc_html( $v ) . '</span></a>';
			}
		}
		if ( $contact ) {
			echo '<div class="ds-cta-hero-contact">' . implode( '', $contact ) . '</div>';
		}

		// Big button.
		if ( ( $s->hero_btn_show ?? 'yes' ) === 'yes' && '' !== trim( (string) ( $s->hero_btn_text ?? '' ) ) ) {
			list( $url, $target ) = $this->link_parts( $s->hero_btn_link ?? '' );
			$rel   = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
			$arrow = ( $s->hero_btn_arrow ?? 'yes' ) === 'yes' ? '<span class="ds-cta-hero-btn-arrow">' . self::ui_svg( 'arrow' ) . '</span>' : '';
			echo '<div class="ds-cta-hero-btnwrap"><a class="ds-cta-hero-btn" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>'
				. '<span>' . esc_html( $s->hero_btn_text ) . '</span>' . $arrow . '</a></div>';
		}

		// Social icons — pulled from Partner Settings.
		if ( ( $s->hero_show_social ?? 'yes' ) === 'yes' ) {
			$html = $this->partner_social_html( $s->hero_social_target ?? '_blank' );
			if ( '' !== $html ) {
				echo '<nav class="ds-cta-hero-social" aria-label="' . esc_attr__( 'Social media', 'ds-toolkit' ) . '">' . $html . '</nav>';
			}
		}

		echo '</div></div></section>';
	}
}

/* Style 6 per-card sub-form. Owned by the shared class so this module never
   depends on the Post Loop module being enabled. */
DS_Program_Cards::register_form();

/* ----------------------------------------------------------- Card sub-form */
FLBuilder::register_settings_form( 'ds_cta_card_form', array(
	'title' => __( 'Card', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Card', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'image'   => array( 'type' => 'photo', 'label' => __( 'Background Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ) ),
						'logo'    => array( 'type' => 'photo', 'label' => __( 'Logo / Badge (Style 5)', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Optional secondary image overlaid on the card — a club logo, badge, or graphic. Position and animation are set in the module\'s Logo Overlay section.', 'ds-toolkit' ) ),
						'eyebrow' => array( 'type' => 'text', 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'title'     => array( 'type' => 'text', 'label' => __( 'Title', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'link'      => array( 'type' => 'link', 'label' => __( 'Link', 'ds-toolkit' ), 'show_target' => true, 'connections' => array( 'url' ) ),
						'link_text' => array( 'type' => 'text', 'label' => __( 'Link Text', 'ds-toolkit' ), 'default' => 'Lorem Ipsum', 'connections' => array( 'string' ), 'help' => __( 'Style 2 button / link text; Style 3 text-cell button. (Style 1 uses a chevron only.)', 'ds-toolkit' ) ),
					),
				),
				'bento' => array(
					'title'  => __( 'Bento (Style 3)', 'ds-toolkit' ),
					'fields' => array(
						'type'      => array(
							'type'    => 'select',
							'label'   => __( 'Cell Type', 'ds-toolkit' ),
							'default' => 'image',
							'options' => array( 'image' => __( 'Image', 'ds-toolkit' ), 'text' => __( 'Text / CTA', 'ds-toolkit' ) ),
							// 'desc' is no longer toggled off for image cells: they render
							// Title / Description / Link Text now too (GH #123).
							'toggle'  => array( 'image' => array( 'fields' => array( 'cell_overlay' ) ) ),
							'help'    => __( 'Bento (Style 3) only. Image cells sit on the Background Image with an overlay; Text cells sit on the Treatment background. Both render Eyebrow, Title, Description and the Link Text button.', 'ds-toolkit' ),
						),
						'desc'      => array( 'type' => 'editor', 'media_buttons' => false, 'rows' => 6, 'wpautop' => false, 'label' => __( 'Description', 'ds-toolkit' ) ),
						'number'    => array( 'type' => 'text', 'label' => __( 'Number', 'ds-toolkit' ), 'connections' => array( 'string' ), 'help' => __( 'Optional index shown above the eyebrow, e.g. 01. Free text, so 01 / A / 1 of 4 all work.', 'ds-toolkit' ) ),
						'badge'     => array( 'type' => 'text', 'label' => __( 'Badge', 'ds-toolkit' ), 'connections' => array( 'string' ), 'help' => __( 'Optional pill label, e.g. NEW or SOLD OUT.', 'ds-toolkit' ) ),
						'treatment' => array(
							'type'    => 'select',
							'label'   => __( 'Treatment', 'ds-toolkit' ),
							'default' => 'auto',
							'options' => array(
								'auto'   => __( 'Automatic (module colours)', 'ds-toolkit' ),
								'dark'   => __( 'Dark background, light text', 'ds-toolkit' ),
								'light'  => __( 'Light background, dark text', 'ds-toolkit' ),
								'accent' => __( 'Brand accent, light text', 'ds-toolkit' ),
							),
							'help'    => __( 'Lets one grid mix an image card, a light card and a brand-colour feature card without needing separate modules. Automatic keeps the module-wide colours, so existing layouts are unchanged.', 'ds-toolkit' ),
						),
						'cell_bg'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Overrides the Treatment background for this card only. On an image card it shows wherever the image does not cover.', 'ds-toolkit' ) ),
						'cell_text' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Overrides the Treatment text colour for this card only. Set this if you pick a Background Colour the Treatment was not designed for.', 'ds-toolkit' ) ),
						'cell_overlay' => array( 'type' => 'unit', 'label' => __( 'Image Overlay', 'ds-toolkit' ), 'default' => '', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 5 ), 'help' => __( 'Darkens the image so text stays readable. Blank is automatic: none for a card with only an eyebrow pill, 45% once it carries a title or description.', 'ds-toolkit' ) ),
						'col_span'  => array( 'type' => 'unit', 'label' => __( 'Column Span', 'ds-toolkit' ), 'default' => '1', 'slider' => array( 'min' => 1, 'max' => 4, 'step' => 1 ), 'help' => __( 'How many grid columns this card occupies. Combine with Row Span for a 2x2 feature card, a 2x1 wide card, or a 1x2 tall card. Every card drops to 1x1 below the tablet breakpoint so nothing overflows on a phone.', 'ds-toolkit' ) ),
						'row_span'  => array( 'type' => 'unit', 'label' => __( 'Row Span', 'ds-toolkit' ), 'default' => '1', 'slider' => array( 'min' => 1, 'max' => 3, 'step' => 1 ) ),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_CTA_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'cta_style_sec' => array(
				'title'  => __( 'CTA Style', 'ds-toolkit' ),
				'fields' => array(
					'cta_style' => array(
						'type'    => 'select',
						'label'   => __( 'CTA Style', 'ds-toolkit' ),
						'default' => 'style1',
						'options' => DS_CTA_Module::styles(),
						'help'    => __( 'Choose a CTA layout. More styles will be added; the options below adapt to the selected style.', 'ds-toolkit' ),
						'toggle'  => array(
							'style1' => array(
								'sections' => array( 'header', 'cards', 'grid', 'clip', 'overlay', 'colors', 'typography', 'spacing' ),
							),
							'style2' => array(
								'sections' => array( 'header', 'cards', 'grid', 'card2', 'overlay', 'colors', 'typography', 'spacing' ),
							),
							'style3' => array(
								'sections' => array( 'header', 'cards', 'grid', 'bento', 'colors', 'typography', 'spacing' ),
							),
							'style4' => array(
								'sections' => array( 'hero', 'hero_contact', 'hero_button', 'hero_social', 'hero_bg', 'hero_box', 'hero_colors', 'hero_social_style', 'hero_typography', 'spacing' ),
							),
							'style5' => array(
								'sections' => array( 'header', 'cards', 'mcard', 'mcard_fx', 'mcard_logo', 'typography', 'spacing' ),
							),
							'style6' => array(
								'sections' => array( 'header', 'programs_sec', 'program_opts', 'program_chrome', 'spacing' ),
							),
						),
					),
				),
			),
			'cards' => array(
				'title'  => __( 'Cards', 'ds-toolkit' ),
				'fields' => array(
					'cards' => array(
						'type'         => 'form',
						'label'        => __( 'Card', 'ds-toolkit' ),
						'form'         => 'ds_cta_card_form',
						'preview_text' => 'title',
						'multiple'     => true,
						'default'      => array(
							array( 'eyebrow' => 'Lorem Ipsum',   'title' => 'Dolor Sit' ),
							array( 'eyebrow' => 'Consectetur',   'title' => 'Adipiscing' ),
							array( 'eyebrow' => 'Sed Eiusmod',   'title' => 'Tempor' ),
							array( 'eyebrow' => 'Incididunt',    'title' => 'Ut Labore' ),
						),
					),
				),
			),
			// ---- Style 4 only: Big Hero content ----
			'hero' => array(
				'title'  => __( 'Hero Content', 'ds-toolkit' ),
				'fields' => array(
					'hero_show_eyebrow' => array(
						'type'    => 'select',
						'label'   => __( 'Show Sub-heading', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'hero_eyebrow', 'hero_eyebrow_line' ) ) ),
					),
					'hero_eyebrow'      => array( 'type' => 'text', 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'default' => 'Get In Touch', 'connections' => array( 'string' ) ),
					'hero_eyebrow_line' => array(
						'type'    => 'select',
						'label'   => __( 'Sub-heading Accent Line', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
						'help'    => __( 'Small accent dash beside the sub-heading.', 'ds-toolkit' ),
					),
					'hero_heading'      => array(
						'type'    => 'textarea',
						'label'   => __( 'Heading', 'ds-toolkit' ),
						'rows'    => 2,
						'default' => 'Questions? {a}Contact Us.{/a}',
						'help'    => __( 'Wrap a word in {a}…{/a} to colour it with the accent. Line breaks are kept. {outline}…{/outline} renders outlined (transparent, stroked) text — default style in Theme Setting.', 'ds-toolkit' ),
					),
					'hero_align'        => array(
						'type'    => 'select',
						'label'   => __( 'Alignment', 'ds-toolkit' ),
						'default' => 'center',
						'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ),
					),
					'hero_box'          => array(
						'type'    => 'select',
						'label'   => __( 'Framed Box', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes — bordered panel', 'ds-toolkit' ), 'no' => __( 'No — plain', 'ds-toolkit' ) ),
						'help'    => __( 'Wrap the content in a bordered panel (with an optional top accent bar). Style it under “Hero Box”.', 'ds-toolkit' ),
					),
				),
			),
			'hero_contact' => array(
				'title'  => __( 'Contact Details', 'ds-toolkit' ),
				'fields' => array(
					'show_location' => array( 'type' => 'select', 'label' => __( 'Show Location', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'show_email'    => array( 'type' => 'select', 'label' => __( 'Show Email', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'show_phone'    => array( 'type' => 'select', 'label' => __( 'Show Phone', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'contact_icons' => array( 'type' => 'select', 'label' => __( 'Show Icons', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Location, Email and Phone pull from Partner Settings (Address / Email / Phone). Empty values are skipped automatically.', 'ds-toolkit' ) ),
				),
			),
			'hero_button' => array(
				'title'  => __( 'Button', 'ds-toolkit' ),
				'fields' => array(
					'hero_btn_show'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Button', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'hero_btn_text', 'hero_btn_link', 'hero_btn_arrow', 'hero_btn_global' ) ) ),
					),
					'hero_btn_text'   => array( 'type' => 'text', 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => 'Express Tryout Interest', 'connections' => array( 'string' ) ),
					'hero_btn_link'   => array( 'type' => 'link', 'label' => __( 'Button Link', 'ds-toolkit' ), 'show_target' => true, 'connections' => array( 'url' ), 'help' => __( 'Tip: connect to a Partner Setting (e.g. the LeagueApps link) or pick the Register page.', 'ds-toolkit' ) ),
					'hero_btn_arrow'  => array( 'type' => 'select', 'label' => __( 'Trailing Arrow', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ) ),
					'hero_btn_global' => array(
						'type'    => 'select',
						'label'   => __( 'Button Style', 'ds-toolkit' ),
						'default' => 'global',
						'options' => array(
							'global' => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ),
							'accent' => __( 'Accent colour', 'ds-toolkit' ),
							'custom' => __( 'Custom colours', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'hero_btn_bg', 'hero_btn_text_color', 'hero_btn_hover_bg', 'hero_btn_hover_text' ) ) ),
						'help'    => __( 'By default the button inherits the global Button (background, hover, radius, shape, typography) from Theme Setting.', 'ds-toolkit' ),
					),
					'hero_btn_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'hero_btn_text_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text', 'ds-toolkit' ), 'show_reset' => true ),
					'hero_btn_hover_bg'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'hero_btn_hover_text' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Text', 'ds-toolkit' ), 'show_reset' => true ),
				),
			),
			'hero_social' => array(
				'title'  => __( 'Social Icons', 'ds-toolkit' ),
				'fields' => array(
					'hero_show_social'   => array(
						'type'    => 'select',
						'label'   => __( 'Show Social Icons', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'hero_social_target' ) ) ),
						'help'    => __( 'Pulled from Partner Settings (Facebook, Instagram, X, YouTube, LinkedIn, TikTok). Networks with no URL are skipped.', 'ds-toolkit' ),
					),
					'hero_social_target' => array( 'type' => 'select', 'label' => __( 'Open Links In', 'ds-toolkit' ), 'default' => '_blank', 'options' => array( '_blank' => __( 'New Tab', 'ds-toolkit' ), '_self' => __( 'Same Tab', 'ds-toolkit' ) ) ),
				),
			),
			// ---- Style 6 only: manual Program Cards list ----
			// Key stays `programs` (NOT `cards`) so a node migrated from the Post
			// Loop module carries its list verbatim and never collides with the
			// styles 1-5 `cards` repeater.
			'programs_sec' => array(
				'title'       => __( 'Program Cards (manual list)', 'ds-toolkit' ),
				'description' => __( 'Build each card by hand: date, sub-heading, title, description, a link/button, and an icon or image.', 'ds-toolkit' ),
				'fields'      => array(
					'programs' => array(
						'type'         => 'form',
						'label'        => __( 'Program', 'ds-toolkit' ),
						'form'         => 'ds_program_form',
						'preview_text' => 'prog_title',
						'multiple'     => true,
					),
				),
			),
			'header' => array(
				'title'  => __( 'Header', 'ds-toolkit' ),
				'fields' => array(
					'show_header'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Header', 'ds-toolkit' ),
						// Off for NEW modules (GH #147). The Heading default is the
						// placeholder "{a}Lorem{/a} Ipsum Dolor", so the old default meant
						// every freshly dropped CTA rendered Lorem copy on the page until
						// someone edited or unticked it — and Lorem reaching a live page is
						// a worse outcome than a section without a heading. Turning it on is
						// one click, and the heading placeholder is waiting when you do.
						// Modules already saved are untouched: they carry their own value,
						// and the render paths still fall back to 'yes' for any module whose
						// settings predate this field entirely.
						'default' => 'no',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'heading', 'header_label' ) ) ),
						'help'    => __( 'Off by default so a new module never ships placeholder heading text. Switch to Yes to add a heading above the cards; existing modules keep whatever they were set to.', 'ds-toolkit' ),
					),
					'heading'      => array(
						'type'    => 'textarea',
						'label'   => __( 'Heading', 'ds-toolkit' ),
						'rows'    => 2,
						'default' => '{a}Lorem{/a} Ipsum Dolor',
						'help'    => __( 'Wrap a word in {a}…{/a} to colour it with the header accent. {outline}…{/outline} renders outlined (transparent, stroked) text — default style in Theme Setting.', 'ds-toolkit' ),
					),
					'header_label' => array( 'type' => 'text', 'label' => __( 'Right-side Label', 'ds-toolkit' ), 'default' => 'Lorem ipsum →' ),
					'header_desc'  => array( 'type' => 'editor', 'media_buttons' => false, 'rows' => 6, 'wpautop' => false, 'label' => __( 'Header Description', 'ds-toolkit' ), 'help' => __( 'Paragraph beside the heading (Bento / Style 3).', 'ds-toolkit' ) ),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'grid' => array(
				'title'  => __( 'Grid', 'ds-toolkit' ),
				'fields' => array(
					'content_width'     => array(
						'type'    => 'select',
						'label'   => __( 'Content Width', 'ds-toolkit' ),
						'default' => 'boxed',
						'options' => array(
							'boxed'  => __( 'Boxed (max 1280px)', 'ds-toolkit' ),
							'full'   => __( 'Full width (fill container)', 'ds-toolkit' ),
							'custom' => __( 'Custom max-width', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'content_max_width' ) ) ),
						'help'    => __( 'Full width lets the CTA fill a full-width row/column. Use the row/column padding for side spacing.', 'ds-toolkit' ),
					),
					'content_max_width' => array( 'type' => 'unit', 'label' => __( 'Max Width', 'ds-toolkit' ), 'default' => '1280', 'description' => 'px', 'slider' => array( 'min' => 480, 'max' => 1920, 'step' => 10 ) ),
					'columns'    => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'section_bg' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Section Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-light-background)', 'show_reset' => true ),
				),
			),
			// --- Style 1 only: clip-card shape ---
			'clip' => array(
				'title'  => __( 'Card Shape', 'ds-toolkit' ),
				'fields' => array(
					'ratio'          => array( 'type' => 'text', 'label' => __( 'Card Ratio', 'ds-toolkit' ), 'default' => '3/4', 'help' => __( 'CSS aspect-ratio, e.g. 3/4, 1/1, 4/5.', 'ds-toolkit' ) ),
					'clip_tl'        => array( 'type' => 'unit', 'label' => __( 'Top-Left Cut', 'ds-toolkit' ), 'default' => '18', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'clip_br'        => array( 'type' => 'unit', 'label' => __( 'Bottom-Right Cut', 'ds-toolkit' ), 'default' => '26', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'stagger'        => array(
						'type'    => 'select',
						'label'   => __( 'Stagger Cards', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'stagger_offset' ) ) ),
					),
					'stagger_offset' => array( 'type' => 'unit', 'label' => __( 'Stagger Offset', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 120, 'step' => 2 ) ),
				),
			),
			// --- Style 2 only: bordered tile ---
			// --- Style 3 only: bento grid ---
			'bento' => array(
				'title'  => __( 'Bento', 'ds-toolkit' ),
				'fields' => array(
					'row_height' => array( 'type' => 'unit', 'label' => __( 'Row Height', 'ds-toolkit' ), 'default' => '220', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 120, 'max' => 400, 'step' => 10 ) ),
					'bento_hover' => array(
						'type'    => 'select',
						'label'   => __( 'Card Hover', 'ds-toolkit' ),
						'default' => 'lift',
						'options' => array(
							'lift' => __( 'Lift + shadow (image cards also zoom)', 'ds-toolkit' ),
							'zoom' => __( 'Image zoom only', 'ds-toolkit' ),
							'none' => __( 'None', 'ds-toolkit' ),
						),
						'help'    => __( 'Applies to every card, image or solid, and responds to hovering anywhere on the card rather than only on a link. Skipped for visitors who ask for reduced motion.', 'ds-toolkit' ),
					),
					'bento_radius' => array( 'type' => 'unit', 'label' => __( 'Cell Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Rounds every bento cell (image + text cells).', 'ds-toolkit' ) ),
					'bento_border_width' => array( 'type' => 'unit', 'label' => __( 'Cell Border Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 10, 'step' => 1 ), 'help' => __( 'Border around every bento cell. Blank or 0 = no border.', 'ds-toolkit' ) ),
					'bento_border_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Cell Border Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-line-color)', 'show_reset' => true, 'show_alpha' => true ),
					'bento_shadow' => array(
						'type'    => 'select',
						'label'   => __( 'Cell Shadow', 'ds-toolkit' ),
						'default' => '',
						'options' => array(
							''       => __( 'None', 'ds-toolkit' ),
							'soft'   => __( 'Soft', 'ds-toolkit' ),
							'medium' => __( 'Medium', 'ds-toolkit' ),
							'strong' => __( 'Strong', 'ds-toolkit' ),
						),
						'help'    => __( 'Drop shadow on every bento cell.', 'ds-toolkit' ),
					),
					'bento_eyebrow_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Eyebrow Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Colour of the cell eyebrow label. Blank = accent on text cells, white on image cells.', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-bento-eyebrow', 'property' => 'color' ) ),
					'bento_eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Eyebrow Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-bento-eyebrow' ) ),
					'cell_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Cell Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'img_cell_bg' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Image Cell Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Shows behind image cells (visible with transparent logos or while the image loads).', 'ds-toolkit' ) ),
					'btn_global' => array( 'type' => 'select', 'label' => __( 'Button Style', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ), 'no' => __( 'Accent colour', 'ds-toolkit' ) ), 'help' => __( 'Text-cell buttons inherit the global Button (background, hover, radius, typography) from Theme Setting by default.', 'ds-toolkit' ) ),
				),
			),
			'card2' => array(
				'title'  => __( 'Tile', 'ds-toolkit' ),
				'fields' => array(
					'min_height'         => array( 'type' => 'unit', 'label' => __( 'Min Height', 'ds-toolkit' ), 'default' => '430', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 200, 'max' => 700, 'step' => 10 ) ),
					'card_radius'        => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'border_width'       => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '3', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 10, 'step' => 1 ) ),
					'border_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'border_hover_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Hover Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'img_zoom'           => array( 'type' => 'select', 'label' => __( 'Zoom Image on Hover', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
				),
			),
			'overlay' => array(
				'title'  => __( 'Overlay', 'ds-toolkit' ),
				'fields' => array(
					'overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'overlay_top'     => array( 'type' => 'unit', 'label' => __( 'Top Opacity', 'ds-toolkit' ), 'default' => '10', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_bottom'  => array( 'type' => 'unit', 'label' => __( 'Bottom Opacity', 'ds-toolkit' ), 'default' => '92', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_hover_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Overlay Colour', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Overlay colour the card/tile fades to on hover. Blank = no colour change.', 'ds-toolkit' ) ),
					'overlay_hover_opacity' => array( 'type' => 'unit', 'label' => __( 'Hover Overlay Opacity', 'ds-toolkit' ), 'default' => '70', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_hover_blend'   => array(
						'type'    => 'select',
						'label'   => __( 'Hover Blend Mode', 'ds-toolkit' ),
						'default' => 'normal',
						'options' => array(
							'normal'      => __( 'Normal', 'ds-toolkit' ),
							'multiply'    => __( 'Multiply (darken / rich)', 'ds-toolkit' ),
							'screen'      => __( 'Screen (lighten)', 'ds-toolkit' ),
							'overlay'     => __( 'Overlay (contrast)', 'ds-toolkit' ),
							'soft-light'  => __( 'Soft Light', 'ds-toolkit' ),
							'hard-light'  => __( 'Hard Light', 'ds-toolkit' ),
							'color'       => __( 'Color (duotone)', 'ds-toolkit' ),
							'hue'         => __( 'Hue', 'ds-toolkit' ),
							'saturation'  => __( 'Saturation', 'ds-toolkit' ),
							'luminosity'  => __( 'Luminosity', 'ds-toolkit' ),
							'difference'  => __( 'Difference (invert)', 'ds-toolkit' ),
							'exclusion'   => __( 'Exclusion', 'ds-toolkit' ),
							'darken'      => __( 'Darken', 'ds-toolkit' ),
							'lighten'     => __( 'Lighten', 'ds-toolkit' ),
							'color-dodge' => __( 'Color Dodge', 'ds-toolkit' ),
							'color-burn'  => __( 'Color Burn', 'ds-toolkit' ),
						),
						'help'    => __( 'Blends the hover overlay INTO the image for a colour-shift on hover. Pair with a Hover Overlay Colour.', 'ds-toolkit' ),
					),
				),
			),
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'accent_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent (eyebrow, chevron, bar)', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'outline_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Outline Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Stroke colour for {outline}…{/outline} text in this module. Blank = the Theme Setting default.', 'ds-toolkit' ) ),
					'outline_width' => array( 'type' => 'unit', 'label' => __( 'Outline Text Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Blank = the Theme Setting default.', 'ds-toolkit' ), 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
					'cta_shadow' => array(
						'type'    => 'select',
						'label'   => __( 'Card Shadow', 'ds-toolkit' ),
						'default' => '',
						'options' => array(
							''       => __( 'None', 'ds-toolkit' ),
							'soft'   => __( 'Soft', 'ds-toolkit' ),
							'medium' => __( 'Medium', 'ds-toolkit' ),
							'strong' => __( 'Strong', 'ds-toolkit' ),
						),
						'help'    => __( 'Drop shadow on the cards / tiles / cells of the selected style.', 'ds-toolkit' ),
					),
					'title_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Title', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'card_bg'             => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'header_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Header Text', 'ds-toolkit' ), 'default' => 'var(--fl-global-headings)', 'show_reset' => true ),
					'header_accent_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Header Accent', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'header_label_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Header Label', 'ds-toolkit' ), 'default' => 'var(--fl-global-body)', 'show_reset' => true ),
				),
			),
			'typography' => array(
				'title'  => __( 'Typography', 'ds-toolkit' ),
				'fields' => array(
					'heading_typography' => array( 'type' => 'typography', 'label' => __( 'Header Heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-heading' ) ),
					'title_typography'   => array( 'type' => 'typography', 'label' => __( 'Card Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-card-title' ) ),
					'eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Card Eyebrow', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-card-eyebrow' ) ),
				),
			),
			// ---- Style 4 only: Big Hero background ----
			'hero_bg' => array(
				'title'  => __( 'Hero Background', 'ds-toolkit' ),
				'fields' => array(
					'hero_image'           => array( 'type' => 'photo', 'label' => __( 'Background Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Full-bleed image behind the CTA. A dark overlay sits on top for legibility.', 'ds-toolkit' ) ),
					'hero_bg_position'     => array(
						'type'    => 'select',
						'label'   => __( 'Image Position', 'ds-toolkit' ),
						'default' => 'center center',
						'options' => array(
							'left top'      => __( 'Top Left', 'ds-toolkit' ),
							'center top'    => __( 'Top Center', 'ds-toolkit' ),
							'right top'     => __( 'Top Right', 'ds-toolkit' ),
							'left center'   => __( 'Center Left', 'ds-toolkit' ),
							'center center' => __( 'Center', 'ds-toolkit' ),
							'right center'  => __( 'Center Right', 'ds-toolkit' ),
							'left bottom'   => __( 'Bottom Left', 'ds-toolkit' ),
							'center bottom' => __( 'Bottom Center', 'ds-toolkit' ),
							'right bottom'  => __( 'Bottom Right', 'ds-toolkit' ),
						),
						'help'    => __( 'Which part of the image stays in view as the section scales.', 'ds-toolkit' ),
					),
					'hero_bg_size'         => array(
						'type'    => 'select',
						'label'   => __( 'Image Size', 'ds-toolkit' ),
						'default' => 'cover',
						'options' => array(
							'cover'   => __( 'Cover (fill the area)', 'ds-toolkit' ),
							'contain' => __( 'Contain (fit, no crop)', 'ds-toolkit' ),
							'auto'    => __( 'Auto (original size)', 'ds-toolkit' ),
							'custom'  => __( 'Custom…', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'hero_bg_size_custom' ) ) ),
					),
					'hero_bg_size_custom'  => array( 'type' => 'text', 'label' => __( 'Custom Size', 'ds-toolkit' ), 'default' => '100% auto', 'help' => __( 'Any CSS background-size, e.g. “100% auto”, “80%”, “600px 400px”.', 'ds-toolkit' ) ),
					'hero_bg_repeat'       => array(
						'type'    => 'select',
						'label'   => __( 'Image Repeat', 'ds-toolkit' ),
						'default' => 'no-repeat',
						'options' => array(
							'no-repeat' => __( 'No Repeat', 'ds-toolkit' ),
							'repeat'    => __( 'Tile (repeat)', 'ds-toolkit' ),
							'repeat-x'  => __( 'Repeat Horizontally', 'ds-toolkit' ),
							'repeat-y'  => __( 'Repeat Vertically', 'ds-toolkit' ),
						),
						'help'    => __( 'Use Tile with a small/seamless texture; keep No Repeat for a photo.', 'ds-toolkit' ),
					),
					'hero_bg_attachment'   => array(
						'type'    => 'select',
						'label'   => __( 'Image Attachment', 'ds-toolkit' ),
						'default' => 'scroll',
						'options' => array(
							'scroll' => __( 'Scroll (normal)', 'ds-toolkit' ),
							'fixed'  => __( 'Fixed (parallax)', 'ds-toolkit' ),
						),
						'help'    => __( 'Fixed pins the image while the page scrolls (some mobile browsers ignore it).', 'ds-toolkit' ),
					),
					'hero_overlay_type'    => array(
						'type'    => 'select',
						'label'   => __( 'Overlay Style', 'ds-toolkit' ),
						'default' => 'solid',
						'options' => array(
							'solid'  => __( 'Solid tint', 'ds-toolkit' ),
							'linear' => __( 'Linear gradient', 'ds-toolkit' ),
							'radial' => __( 'Radial gradient', 'ds-toolkit' ),
							'none'   => __( 'None', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'hero_overlay_color', 'hero_overlay_opacity', 'hero_overlay_blend' ) ),
							'linear' => array( 'fields' => array( 'hero_overlay_color', 'hero_overlay_opacity', 'hero_overlay_color2', 'hero_overlay_opacity2', 'hero_overlay_angle', 'hero_overlay_blend' ) ),
							'radial' => array( 'fields' => array( 'hero_overlay_color', 'hero_overlay_opacity', 'hero_overlay_color2', 'hero_overlay_opacity2', 'hero_overlay_radial', 'hero_overlay_blend' ) ),
						),
						'help'    => __( 'Darkens the image for legible text. Gradients give a more creative fade — set two colours and their opacities.', 'ds-toolkit' ),
					),
					'hero_overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'hero_overlay_opacity' => array( 'type' => 'unit', 'label' => __( 'Overlay Opacity', 'ds-toolkit' ), 'default' => '82', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'hero_overlay_color2'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour 2', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'hero_overlay_opacity2'=> array( 'type' => 'unit', 'label' => __( 'Overlay Opacity 2', 'ds-toolkit' ), 'default' => '20', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'hero_overlay_angle'   => array( 'type' => 'unit', 'label' => __( 'Gradient Angle', 'ds-toolkit' ), 'default' => '180', 'description' => 'deg', 'slider' => array( 'min' => 0, 'max' => 360, 'step' => 5 ) ),
					'hero_overlay_radial'  => array(
						'type'    => 'select',
						'label'   => __( 'Radial Centre', 'ds-toolkit' ),
						'default' => 'center',
						'options' => array(
							'center'       => __( 'Center', 'ds-toolkit' ),
							'top'          => __( 'Top', 'ds-toolkit' ),
							'bottom'       => __( 'Bottom', 'ds-toolkit' ),
							'left'         => __( 'Left', 'ds-toolkit' ),
							'right'        => __( 'Right', 'ds-toolkit' ),
							'left top'     => __( 'Top Left', 'ds-toolkit' ),
							'right top'    => __( 'Top Right', 'ds-toolkit' ),
							'left bottom'  => __( 'Bottom Left', 'ds-toolkit' ),
							'right bottom' => __( 'Bottom Right', 'ds-toolkit' ),
						),
					),
					'hero_overlay_blend'   => array(
						'type'    => 'select',
						'label'   => __( 'Overlay Blend Mode', 'ds-toolkit' ),
						'default' => 'normal',
						'options' => array(
							'normal'      => __( 'Normal (tint)', 'ds-toolkit' ),
							'multiply'    => __( 'Multiply (darken / rich)', 'ds-toolkit' ),
							'screen'      => __( 'Screen (lighten)', 'ds-toolkit' ),
							'overlay'     => __( 'Overlay (contrast)', 'ds-toolkit' ),
							'soft-light'  => __( 'Soft Light', 'ds-toolkit' ),
							'hard-light'  => __( 'Hard Light', 'ds-toolkit' ),
							'color'       => __( 'Color (duotone)', 'ds-toolkit' ),
							'hue'         => __( 'Hue', 'ds-toolkit' ),
							'saturation'  => __( 'Saturation', 'ds-toolkit' ),
							'luminosity'  => __( 'Luminosity', 'ds-toolkit' ),
							'difference'  => __( 'Difference (invert)', 'ds-toolkit' ),
							'exclusion'   => __( 'Exclusion', 'ds-toolkit' ),
							'darken'      => __( 'Darken', 'ds-toolkit' ),
							'lighten'     => __( 'Lighten', 'ds-toolkit' ),
							'color-dodge' => __( 'Color Dodge', 'ds-toolkit' ),
							'color-burn'  => __( 'Color Burn', 'ds-toolkit' ),
						),
						'help'    => __( 'Blends the overlay colour INTO the image for colour-shift effects: try Multiply or Color with a brand colour for a duotone, or Screen to lighten. Text stays unaffected.', 'ds-toolkit' ),
					),
					'hero_min_height'      => array( 'type' => 'unit', 'label' => __( 'Min Height', 'ds-toolkit' ), 'default' => '520', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 240, 'max' => 900, 'step' => 10 ) ),
				),
			),
			'hero_box' => array(
				'title'  => __( 'Hero Box', 'ds-toolkit' ),
				'fields' => array(
					'box_max_width'     => array( 'type' => 'unit', 'label' => __( 'Content Max Width', 'ds-toolkit' ), 'default' => '860', 'description' => 'px', 'slider' => array( 'min' => 360, 'max' => 1200, 'step' => 10 ) ),
					'box_padding'       => array( 'type' => 'dimension', 'label' => __( 'Box Padding', 'ds-toolkit' ), 'default' => '60', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true ),
					'box_bg'            => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Box Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Optional tint behind the content (only when Framed Box is on). Blank = transparent.', 'ds-toolkit' ) ),
					'box_border_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => 'rgba(255,255,255,0.22)', 'show_reset' => true, 'show_alpha' => true ),
					'box_border_width'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 8, 'step' => 1 ) ),
					'box_radius'        => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'box_top_bar'       => array(
						'type'    => 'select',
						'label'   => __( 'Top Accent Bar', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'box_top_bar_color', 'box_top_bar_height' ) ) ),
					),
					'box_top_bar_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Top Bar Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'box_top_bar_height' => array( 'type' => 'unit', 'label' => __( 'Top Bar Height', 'ds-toolkit' ), 'default' => '4', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
				),
			),
			'hero_colors' => array(
				'title'  => __( 'Hero Colours', 'ds-toolkit' ),
				'fields' => array(
					'hero_accent_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent (sub-heading, line, icons)', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'hero_heading_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'hero_heading_accent_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading Accent {a}…{/a}', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'hero_contact_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Contact Text', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'hero_contact_icon_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Contact Icons', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
				),
			),
			'hero_social_style' => array(
				'title'  => __( 'Social Style', 'ds-toolkit' ),
				'fields' => array(
					'social_icon_size'   => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 10, 'max' => 48, 'step' => 1 ) ),
					'social_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap Between Icons', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 48, 'step' => 1 ) ),
					'social_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true, 'show_alpha' => true ),
					'social_hover_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Hover Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'show_alpha' => true ),
				),
			),
			'hero_typography' => array(
				'title'  => __( 'Hero Typography', 'ds-toolkit' ),
				'fields' => array(
					'hero_eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-hero-eyebrow' ) ),
					'hero_heading_typography' => array( 'type' => 'typography', 'label' => __( 'Heading', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-hero-heading' ) ),
					'hero_contact_typography' => array( 'type' => 'typography', 'label' => __( 'Contact Details', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-hero-citem' ) ),
					'hero_btn_typography'     => array( 'type' => 'typography', 'label' => __( 'Button (Custom/Accent only)', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-cta-hero-btn' ) ),
				),
			),
			'mcard' => array(
				'title'  => __( 'Motion Card', 'ds-toolkit' ),
				'fields' => array(
					'mc_cols'   => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 5, 'step' => 1 ), 'help' => __( 'Responsive: set per-device values with the device icon. Blank smaller sizes keep the automatic fallback (max 2 columns on tablet, 1 on phone).', 'ds-toolkit' ) ),
					'mc_gap'    => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'mc_ratio'  => array( 'type' => 'select', 'label' => __( 'Card Ratio', 'ds-toolkit' ), 'default' => '4 / 5', 'options' => array( '4 / 5' => '4:5', '3 / 4' => '3:4', '1 / 1' => '1:1', '4 / 3' => '4:3', '16 / 10' => '16:10' ) ),
					'mc_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-mcard', 'property' => 'border-radius', 'unit' => 'px' ) ),
					'mc_shadow' => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => 'soft', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'medium' => __( 'Medium', 'ds-toolkit' ), 'strong' => __( 'Strong', 'ds-toolkit' ) ) ),
					'mc_lift'   => array( 'type' => 'unit', 'label' => __( 'Hover Lift', 'ds-toolkit' ), 'default' => '6', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 20, 'step' => 1 ), 'help' => __( 'How far the card rises on hover; the shadow deepens with it.', 'ds-toolkit' ) ),
					'mc_speed'  => array( 'type' => 'unit', 'label' => __( 'Hover Transition Speed', 'ds-toolkit' ), 'default' => '250', 'description' => 'ms', 'slider' => array( 'min' => 100, 'max' => 800, 'step' => 10 ) ),
				),
			),
			'mcard_fx' => array(
				'title'  => __( 'Background Effects', 'ds-toolkit' ),
				'fields' => array(
					'mcgrad_enable' => array(
						'type'    => 'select',
						'label'   => __( 'Default Gradient', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Enabled', 'ds-toolkit' ), 'no' => __( 'Disabled', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'mcgrad_color', 'mcgrad_opacity', 'mcgrad_direction', 'mcgrad_coverage' ) ) ),
						'help'    => __( 'Adds a persistent gradient above the image for consistent text readability. It works independently from the default and hover effects below.', 'ds-toolkit' ),
					),
					'mcgrad_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Gradient Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'mcgrad_opacity' => array( 'type' => 'unit', 'label' => __( 'Gradient Opacity', 'ds-toolkit' ), 'default' => '60', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'mcgrad_direction' => array(
						'type'    => 'select',
						'label'   => __( 'Gradient Direction', 'ds-toolkit' ),
						'default' => 'bottom-top',
						'options' => array(
							'top-bottom' => __( 'Top to Bottom', 'ds-toolkit' ),
							'bottom-top' => __( 'Bottom to Top', 'ds-toolkit' ),
							'left-right' => __( 'Left to Right', 'ds-toolkit' ),
							'right-left' => __( 'Right to Left', 'ds-toolkit' ),
							'radial'     => __( 'Radial', 'ds-toolkit' ),
						),
					),
					'mcgrad_coverage' => array( 'type' => 'unit', 'label' => __( 'Gradient Coverage', 'ds-toolkit' ), 'default' => '75', 'description' => '%', 'slider' => array( 'min' => 10, 'max' => 100, 'step' => 1 ), 'help' => __( 'Controls how far the gradient extends from its solid edge. Radial mode uses it as the width of the dark outer fade.', 'ds-toolkit' ) ),
					'mcfx_base'    => array(
						'type'    => 'select',
						'label'   => __( 'Default Effect', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'blur' => __( 'Blur', 'ds-toolkit' ), 'brightness' => __( 'Darken (brightness)', 'ds-toolkit' ), 'grayscale' => __( 'Grayscale', 'ds-toolkit' ), 'opacity' => __( 'Opacity', 'ds-toolkit' ), 'overlay' => __( 'Colour Overlay', 'ds-toolkit' ) ),
						'toggle'  => array(
							'blur'       => array( 'fields' => array( 'mcfx_amount' ) ),
							'brightness' => array( 'fields' => array( 'mcfx_amount' ) ),
							'opacity'    => array( 'fields' => array( 'mcfx_amount' ) ),
							'overlay'    => array( 'fields' => array( 'mcfx_overlay', 'mcfx_blend' ) ),
						),
					),
					'mcfx_amount'  => array( 'type' => 'unit', 'label' => __( 'Effect Amount', 'ds-toolkit' ), 'default' => '60', 'description' => '% (px for blur)', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'mcfx_overlay' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-primary)', 'show_reset' => true, 'show_alpha' => true ),
					'mcfx_blend'   => array( 'type' => 'select', 'label' => __( 'Blend Mode', 'ds-toolkit' ), 'default' => 'multiply', 'options' => array( 'normal' => 'Normal', 'multiply' => 'Multiply', 'screen' => 'Screen', 'overlay' => 'Overlay', 'soft-light' => 'Soft Light', 'hard-light' => 'Hard Light', 'darken' => 'Darken', 'lighten' => 'Lighten' ) ),
					'mcfx_hover'   => array(
						'type'    => 'select',
						'label'   => __( 'Hover Effect', 'ds-toolkit' ),
						'default' => 'zoom-in',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'clear' => __( 'Remove Effect', 'ds-toolkit' ), 'brighten' => __( 'Brighten', 'ds-toolkit' ), 'zoom-in' => __( 'Zoom In', 'ds-toolkit' ), 'zoom-out' => __( 'Zoom Out', 'ds-toolkit' ) ),
						'toggle'  => array( 'zoom-in' => array( 'fields' => array( 'mcfx_zoom' ) ), 'zoom-out' => array( 'fields' => array( 'mcfx_zoom' ) ) ),
					),
					'mcfx_zoom'    => array( 'type' => 'unit', 'label' => __( 'Zoom Amount', 'ds-toolkit' ), 'default' => '6', 'description' => '%', 'slider' => array( 'min' => 1, 'max' => 25, 'step' => 1 ) ),
				),
			),
			'mcard_logo' => array(
				'title'       => __( 'Logo Overlay', 'ds-toolkit' ),
				'description' => __( 'Styling + animation for each card\'s Logo / Badge image (set per card).', 'ds-toolkit' ),
				'fields'      => array(
					'mcl_display' => array(
						'type'    => 'select',
						'label'   => __( 'Logo Display', 'ds-toolkit' ),
						'default' => 'always',
						'options' => array(
							'always' => __( 'Always Visible', 'ds-toolkit' ),
							'hover'  => __( 'Show On Card Hover', 'ds-toolkit' ),
						),
						'help'    => __( 'The whole card triggers the logo animation. Hover mode hides the logo until the card is hovered or keyboard-focused.', 'ds-toolkit' ),
					),
					'mcl_pos'    => array( 'type' => 'select', 'label' => __( 'Position', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'top-left' => __( 'Top Left', 'ds-toolkit' ), 'top-right' => __( 'Top Right', 'ds-toolkit' ), 'bottom-left' => __( 'Bottom Left', 'ds-toolkit' ), 'bottom-right' => __( 'Bottom Right', 'ds-toolkit' ) ) ),
					'mcl_size'   => array( 'type' => 'unit', 'label' => __( 'Size', 'ds-toolkit' ), 'default' => '84', 'description' => 'px', 'slider' => array( 'min' => 32, 'max' => 200, 'step' => 2 ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-mcard-logo img', 'property' => 'width', 'unit' => 'px' ) ),
					'mcl_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Backing Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Optional plate behind the logo. Blank = none.', 'ds-toolkit' ) ),
					'mcl_pad'    => array( 'type' => 'unit', 'label' => __( 'Padding', 'ds-toolkit' ), 'default' => '10', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'mcl_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '999', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 999, 'step' => 1 ) ),
					'mcl_shadow' => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => 'soft', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'medium' => __( 'Medium', 'ds-toolkit' ), 'strong' => __( 'Strong', 'ds-toolkit' ) ) ),
					'mcl_in'     => array( 'type' => 'select', 'label' => __( 'Card Hover Entrance', 'ds-toolkit' ), 'default' => 'fade-up', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'fade' => __( 'Fade In', 'ds-toolkit' ), 'fade-up' => __( 'Fade Up', 'ds-toolkit' ), 'fade-down' => __( 'Fade Down', 'ds-toolkit' ), 'fade-left' => __( 'Fade Left', 'ds-toolkit' ), 'fade-right' => __( 'Fade Right', 'ds-toolkit' ), 'slide-up' => __( 'Slide Up', 'ds-toolkit' ), 'slide-down' => __( 'Slide Down', 'ds-toolkit' ), 'slide-left' => __( 'Slide Left', 'ds-toolkit' ), 'slide-right' => __( 'Slide Right', 'ds-toolkit' ), 'zoom-in' => __( 'Zoom In', 'ds-toolkit' ), 'zoom-out' => __( 'Zoom Out', 'ds-toolkit' ) ), 'help' => __( 'Plays whenever the whole card is hovered or keyboard-focused. It can replay after the pointer leaves and returns. Skipped for reduced-motion visitors.', 'ds-toolkit' ) ),
					'mcl_dur'    => array( 'type' => 'unit', 'label' => __( 'Animation Duration', 'ds-toolkit' ), 'default' => '600', 'description' => 'ms', 'slider' => array( 'min' => 100, 'max' => 2000, 'step' => 50 ) ),
					'mcl_delay'  => array( 'type' => 'unit', 'label' => __( 'Animation Delay', 'ds-toolkit' ), 'default' => '0', 'description' => 'ms', 'slider' => array( 'min' => 0, 'max' => 2000, 'step' => 50 ) ),
					'mcl_ease'   => array( 'type' => 'select', 'label' => __( 'Animation Easing', 'ds-toolkit' ), 'default' => 'ease-out', 'options' => array( 'ease' => 'Ease', 'ease-out' => 'Ease Out', 'ease-in-out' => 'Ease In-Out', 'cubic-bezier(.34,1.56,.64,1)' => __( 'Spring (overshoot)', 'ds-toolkit' ) ) ),
					'mcl_hover'  => array( 'type' => 'select', 'label' => __( 'Hover Animation', 'ds-toolkit' ), 'default' => 'float', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'scale-up' => __( 'Scale Up', 'ds-toolkit' ), 'scale-down' => __( 'Scale Down', 'ds-toolkit' ), 'float' => __( 'Float', 'ds-toolkit' ), 'bounce' => __( 'Bounce', 'ds-toolkit' ), 'rotate' => __( 'Rotate', 'ds-toolkit' ), 'pulse' => __( 'Pulse', 'ds-toolkit' ), 'fade' => __( 'Fade', 'ds-toolkit' ), 'glow' => __( 'Glow', 'ds-toolkit' ) ) ),
				),
			),
			// ---- Style 6 only: Program Card options. Keys mirror the Post Loop
			// module's `program_opts` exactly so migration is a verbatim carry. ----
			'program_opts' => array(
				'title'  => __( 'Program Cards', 'ds-toolkit' ),
				'fields' => array(
					'pg_cols'        => array(
						'type'       => 'unit',
						'label'      => __( 'Columns', 'ds-toolkit' ),
						'default'    => '3',
						'responsive' => true,
						'slider'     => array( 'min' => 1, 'max' => 6, 'step' => 1 ),
						'help'       => __( 'Cards per row. Use the responsive icon on this field for tablet and mobile counts; leaving one blank keeps the size above it. The saved value stays the desktop count, so existing layouts are unchanged.', 'ds-toolkit' ),
					),
					'pg_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'pg_align'       => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'left', 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ) ) ),
					'pg_same_height' => array( 'type' => 'select', 'label' => __( 'Card Height', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Equal (match tallest in row)', 'ds-toolkit' ), 'no' => __( 'Natural (fit content)', 'ds-toolkit' ) ), 'help' => __( 'Equal makes every card in a row the same height (buttons line up at the bottom).', 'ds-toolkit' ) ),
					'pg_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_pad'         => array( 'type' => 'unit', 'label' => __( 'Card Padding', 'ds-toolkit' ), 'default' => '28', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'pg_img_h'       => array( 'type' => 'unit', 'label' => __( 'Image Height', 'ds-toolkit' ), 'default' => '180', 'description' => 'px', 'slider' => array( 'min' => 80, 'max' => 360, 'step' => 1 ), 'help' => __( 'Image height for items that use an image (not an icon).', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-media', 'property' => 'height', 'unit' => 'px' ) ),
					'pg_icon_size'   => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'slider' => array( 'min' => 16, 'max' => 96, 'step' => 1 ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-ico', 'property' => 'font-size', 'unit' => 'px' ) ),
					'pg_icon_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-ico', 'property' => 'color' ) ),
					'pg_date_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'pg_sub_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Sub-heading Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_desc_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Description Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_style'      => array( 'type' => 'select', 'label' => __( 'Button Style', 'ds-toolkit' ), 'default' => 'theme', 'options' => array( 'theme' => __( 'Theme button (default)', 'ds-toolkit' ), 'custom' => __( 'Custom', 'ds-toolkit' ) ), 'toggle' => array( 'custom' => array( 'fields' => array( 'pg_btn_bg', 'pg_btn_color', 'pg_btn_hover_bg', 'pg_btn_hover_color', 'pg_btn_radius' ) ) ), 'help' => __( 'Theme button follows Theme Setting (colour + shape). Choose Custom to style this card\'s button below.', 'ds-toolkit' ) ),
					'pg_btn_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Button colour.', 'ds-toolkit' ) ),
					'pg_btn_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_hover_bg'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_hover_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_radius'     => array( 'type' => 'unit', 'label' => __( 'Button Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank = follow the theme Button Shape. Set a value for a custom rounded button (overrides the theme shape for this card).', 'ds-toolkit' ) ),
					'pg_date_typo'      => array( 'type' => 'typography', 'label' => __( 'Date Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-date' ) ),
					'pg_sub_typo'       => array( 'type' => 'typography', 'label' => __( 'Sub-heading Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-sub' ) ),
					'pg_title_typo'     => array( 'type' => 'typography', 'label' => __( 'Title Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-title' ) ),
					'pg_desc_typo'      => array( 'type' => 'typography', 'label' => __( 'Description Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-desc' ) ),
					'pg_btn_typo'       => array( 'type' => 'typography', 'label' => __( 'Button Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-btn' ) ),
				),
			),
			// Card border + hover. Same keys as the Post Loop module's shared
			// `card_border` / `hover` sections so migrated values keep applying.
			'program_chrome' => array(
				'title'  => __( 'Card Border & Hover', 'ds-toolkit' ),
				'fields' => array(
					'card_bd_style'  => array( 'type' => 'select', 'label' => __( 'Border Style', 'ds-toolkit' ), 'default' => 'default', 'options' => array( 'default' => __( 'Default (layout look)', 'ds-toolkit' ), 'none' => __( 'None', 'ds-toolkit' ), 'solid' => __( 'Solid', 'ds-toolkit' ), 'dashed' => __( 'Dashed', 'ds-toolkit' ), 'dotted' => __( 'Dotted', 'ds-toolkit' ) ), 'toggle' => array( 'solid' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ), 'dashed' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ), 'dotted' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ) ) ),
					'card_bd_width'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 12, 'step' => 1 ) ),
					'card_bd_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Line Color.', 'ds-toolkit' ) ),
					'card_bd_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank = the theme corner radius.', 'ds-toolkit' ) ),
					'hover_effect'   => array( 'type' => 'select', 'label' => __( 'Hover Effect', 'ds-toolkit' ), 'default' => 'lift', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'lift' => __( 'Lift', 'ds-toolkit' ), 'grow' => __( 'Grow', 'ds-toolkit' ), 'shadow' => __( 'Shadow', 'ds-toolkit' ), 'border' => __( 'Border', 'ds-toolkit' ), 'zoom' => __( 'Zoom Image', 'ds-toolkit' ) ) ),
					'hover_speed'    => array( 'type' => 'unit', 'label' => __( 'Hover Speed', 'ds-toolkit' ), 'default' => '300', 'description' => 'ms', 'slider' => array( 'min' => 0, 'max' => 900, 'step' => 10 ) ),
					'hover_bg'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					// NOTE: `section_bg` is deliberately NOT redefined here — the module
					// already declares it (Colors section). A second definition of the
					// same key would render the control twice and fight over the default.
					// A migrated node's stored value still applies via chrome_css().
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'padding' => array(
						'type'       => 'dimension',
						'label'      => __( 'Padding', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Inner spacing around the grid. 0 = compact / flush.', 'ds-toolkit' ),
					),
					'margin'  => array(
						'type'       => 'dimension',
						'label'      => __( 'Margin', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Outer spacing around the whole section. 0 = compact / flush.', 'ds-toolkit' ),
					),
				),
			),
		),
	),
) );
