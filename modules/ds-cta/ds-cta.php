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
			'style7' => __( 'Style 7 — Program Pathway', 'ds-toolkit' ),
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

	/**
	 * Style 7 — Program Pathway (GH #288): numbered program cards. A large image, an
	 * oversized outlined number (01, 02 ...) overlapping its lower-left edge, a bold title
	 * with a category label beside it, and a description.
	 *
	 * Reads the SAME `programs` repeater as Style 6 (ds_program_form): Image, Sub-heading
	 * (the label), Title, Description and Link. Date, Icon and Button Text belong to Style 6
	 * and are not shown. The number is the card's position among the cards shown.
	 *
	 * A card with a link is ONE <a> wrapping image, number and text, so the whole card is
	 * the target; the description is rendered without links so no <a> nests inside it.
	 * A row with no image and no text is skipped rather than drawn as an empty card.
	 */
	public function render_style7() {
		$s     = $this->settings;
		$items = ( isset( $s->programs ) && is_array( $s->programs ) ) ? $s->programs : array();
		$mods  = 'ds-cta ds-cta--style7';
		if ( ( $s->pp_lines ?? 'yes' ) === 'yes' ) { $mods .= ' ds-pp--lines'; }
		$hover = in_array( $s->pp_hover ?? 'lift', array( 'lift', 'zoom', 'none' ), true ) ? ( $s->pp_hover ?? 'lift' ) : 'lift';
		$mods .= ' ds-pp--hover-' . $hover;

		echo '<section class="' . esc_attr( $mods ) . '"><div class="ds-cta-wrap">';

		if ( ( $s->show_header ?? 'no' ) === 'yes' && ( ! empty( $s->heading ) || ! empty( $s->header_label ) ) ) {
			echo '<div class="ds-cta-head">';
			if ( ! empty( $s->heading ) ) {
				echo '<h2 class="ds-cta-heading">' . $this->heading_html( $s->heading ) . '</h2>';
			}
			if ( ! empty( $s->header_label ) ) {
				echo '<span class="ds-cta-head-label">' . esc_html( $s->header_label ) . '</span>';
			}
			echo '</div>';
		}

		// Description markup minus anything interactive: the card itself is the link.
		$allowed = wp_kses_allowed_html( 'post' );
		foreach ( array( 'a', 'button', 'input', 'select', 'textarea', 'form', 'label', 'iframe', 'video', 'audio', 'details' ) as $tag ) { unset( $allowed[ $tag ] ); }

		$cards = '';
		$n     = 0;
		foreach ( $items as $it ) {
			$it    = (object) $it;
			$img   = DS_Card::photo_url( $it->prog_image ?? '' );
			$sub   = trim( (string) ( $it->prog_subheading ?? '' ) );
			$title = trim( (string) ( $it->prog_title ?? '' ) );
			$desc  = trim( (string) ( $it->prog_desc ?? '' ) );
			if ( '' === $img && '' === $sub && '' === $title && '' === trim( wp_strip_all_tags( $desc ) ) ) {
				continue; // an empty repeater row: no phantom card, no number used
			}
			$n++;
			list( $url, $target ) = DS_Card::link_parts( $it->prog_url ?? '', $it->prog_url_target ?? '' );
			$hasurl = ( '' !== $url && '#' !== $url );
			$tag    = $hasurl ? 'a' : 'div';
			$attr   = $hasurl ? ' href="' . esc_url( $url ) . '"' . ( '_blank' === $target ? ' target="_blank" rel="noopener noreferrer"' : '' ) : '';

			$cards .= '<' . $tag . ' class="ds-pp-card' . ( $hasurl ? ' is-link' : '' ) . '"' . $attr . '>';
			$cards .= '<div class="ds-pp-media' . ( '' === $img ? ' is-empty' : '' ) . '">';
			// The title names the link, so the picture is decorative here (no doubled announcement).
			if ( '' !== $img ) { $cards .= '<span class="ds-pp-frame"><img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async" /></span>'; }
			$cards .= '<span class="ds-pp-num" aria-hidden="true">' . esc_html( sprintf( '%02d', $n ) ) . '</span>';
			$cards .= '</div><div class="ds-pp-body">';
			if ( '' !== $title || '' !== $sub ) {
				$cards .= '<div class="ds-pp-titlerow">';
				if ( '' !== $title ) { $cards .= '<h3 class="ds-pp-title">' . DS_Module_UI::inline( $title ) . '</h3>'; }
				if ( '' !== $sub ) { $cards .= '<span class="ds-pp-sub">' . esc_html( $sub ) . '</span>'; }
				$cards .= '</div>';
			}
			if ( '' !== $desc ) { $cards .= '<div class="ds-pp-desc">' . wpautop( wp_kses( $desc, $allowed ) ) . '</div>'; }
			$cards .= '</div></' . $tag . '>';
		}

		if ( '' === $cards ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:14px;opacity:.7">' . esc_html__( 'No program cards yet. Add them in the Program Cards list.', 'ds-toolkit' ) . '</p>';
			}
		} else {
			echo '<div class="ds-pp-grid">' . $cards . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
		echo '</div></section>';
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
			list( $url, $target ) = $this->link_parts( $card->link ?? '', $card->link_target ?? '' );
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

	/**
	 * The card rows that carry something to show (Styles 1 and 2; a Bento cell can be a deliberate colour block, so Style 3 keeps every cell). Since 1.14 a new module starts with
	 * blank rows, and a blank row rendered as an empty "#" link with an empty heading: a box with no
	 * text that screen readers announce as an unnamed link. A row with an image, an eyebrow, a title,
	 * a description, a number, a badge or a real link still renders exactly as before. Link Text alone
	 * does not count: it defaults to "Learn More" and names nothing without a link.
	 */
	private function filled_cards() {
		$cards = isset( $this->settings->cards ) && is_array( $this->settings->cards ) ? $this->settings->cards : array();
		$out   = array();
		foreach ( $cards as $card ) {
			$c = (object) $card;
			list( $url ) = $this->link_parts( $c->link ?? '', '' );
			$text = '';
			foreach ( array( 'eyebrow', 'title', 'desc', 'number', 'badge' ) as $k ) { $text .= trim( (string) ( $c->$k ?? '' ) ); }
			if ( empty( $c->image ) && '' === $text && ( '' === $url || '#' === $url ) ) { continue; }
			$out[] = $card;
		}
		return $out;
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
	private function link_parts( $link, $target_field = '' ) {
		// A BB link field with show_target stores the tick in a SIBLING key
		// (<field>_target), not inside the URL string; callers pass it in.
		$url = ''; $target = (string) $target_field;
		if ( is_array( $link ) )      { $url = $link['url'] ?? ''; $target = $link['target'] ?? $target; }
		elseif ( is_object( $link ) ) { $url = $link->url ?? '';  $target = $link->target ?? $target; }
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

		$cards = $this->filled_cards();
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
			list( $url, $target ) = $this->link_parts( $card->link ?? '', $card->link_target ?? '' );
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

		$cards = $this->filled_cards();
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
			list( $url, $target ) = $this->link_parts( $card->link ?? '', $card->link_target ?? '' );
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
			list( $url, $target ) = $this->link_parts( $cell->link ?? '', $cell->link_target ?? '' );
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
			// The title and description carry their own colours, and only inherit the card's colour under a Treatment; the
			// --ctext class lets a card's Text Colour reach them on Automatic too.
			if ( '' !== $ctx ) { $style .= 'color:' . $ctx . ';'; $cls .= ' ds-cta-bento-cell--ctext'; }

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
			// A rule on BOTH sides when centred (GH #158), matching the Heading module.
			// Both are always emitted and the trailing one is hidden by CSS unless the
			// active alignment is centred — same reason as there: it keeps the rule tied
			// to whatever alignment is in force rather than to a PHP-time decision.
			$eyeline = ( $s->hero_eyebrow_line ?? 'yes' ) === 'yes';
			if ( $eyeline ) { echo '<span class="ds-cta-hero-eyebrow-line ds-cta-hero-eyebrow-line--before" aria-hidden="true"></span>'; }
			echo '<span class="ds-cta-hero-eyebrow-text">' . DS_Module_UI::inline( $s->hero_eyebrow ) . '</span>';
			if ( $eyeline ) { echo '<span class="ds-cta-hero-eyebrow-line ds-cta-hero-eyebrow-line--after" aria-hidden="true"></span>'; }
			echo '</div>';
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
			list( $url, $target ) = $this->link_parts( $s->hero_btn_link ?? '', $s->hero_btn_link_target ?? '' );
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

// The sub-forms, the settings form and the module registration.
require __DIR__ . '/includes/settings-form.php';
