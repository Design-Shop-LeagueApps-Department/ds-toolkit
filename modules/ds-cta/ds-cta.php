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
		);
	}

	/** Heading markup: escape, {a}..{/a} -> accent span, newlines -> <br>. */
	private function heading_html( $raw ) {
		$h = DS_Module_UI::inline( (string) $raw ); // safe inline HTML (span/strong/em...) allowed
		$h = str_replace( array( '{a}', '{/a}' ), array( '<span class="ds-cta-accent">', '</span>' ), $h );
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
			if ( ! empty( $s->header_desc ) ) { echo '<p class="ds-cta-bento-desc">' . nl2br( esc_html( $s->header_desc ) ) . '</p>'; }
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

		echo '<div class="ds-cta-bento">';
		foreach ( $cards as $cell ) {
			$cell = (object) $cell;
			$cs   = max( 1, (int) ( $cell->col_span ?? 1 ) );
			$rs   = max( 1, (int) ( $cell->row_span ?? 1 ) );
			$span = ' style="grid-column:span ' . $cs . ';grid-row:span ' . $rs . ';"';
			$type = ( ( $cell->type ?? 'image' ) === 'text' ) ? 'text' : 'image';
			list( $url, $target ) = $this->link_parts( $cell->link ?? '' );
			$rel = '_blank' === $target ? ' rel="noopener noreferrer"' : '';

			if ( 'text' === $type ) {
				echo '<div class="ds-cta-bento-cell ds-cta-bento-text"' . $span . '>';
				if ( ! empty( $cell->eyebrow ) ) { echo '<div class="ds-cta-bento-eyebrow">' . DS_Module_UI::inline( $cell->eyebrow ) . '</div>'; }
				if ( ! empty( $cell->title ) ) { echo '<h3 class="ds-cta-bento-title">' . DS_Module_UI::inline( $cell->title ) . '</h3>'; }
				if ( ! empty( $cell->desc ) ) { echo '<div class="ds-cta-bento-celldesc">' . wpautop( wp_kses_post( $cell->desc ) ) . '</div>'; }
				$lt = trim( (string) ( $cell->link_text ?? '' ) );
				if ( '' !== $lt ) {
					echo '<a class="ds-cta-bento-btn" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>' . esc_html( $lt ) . '</a>';
				}
				echo '</div>';
			} else {
				$img  = ! empty( $cell->image ) ? $this->photo_url( $cell->image ) : '';
				$has  = ( $url && '#' !== $url );
				$tag  = $has ? 'a' : 'div';
				$attr = $has ? ' href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel : '';
				echo '<' . $tag . ' class="ds-cta-bento-cell ds-cta-bento-img"' . $span . $attr . '>';
				echo '<div class="ds-cta-bento-bg"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
				if ( ! empty( $cell->eyebrow ) ) { echo '<div class="ds-cta-bento-eyebrow">' . esc_html( $cell->eyebrow ) . '</div>'; }
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
						'eyebrow' => array( 'type' => 'text', 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'title'     => array( 'type' => 'text', 'label' => __( 'Title', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'link'      => array( 'type' => 'link', 'label' => __( 'Link', 'ds-toolkit' ), 'show_target' => true, 'connections' => array( 'url' ) ),
						'link_text' => array( 'type' => 'text', 'label' => __( 'Link Text', 'ds-toolkit' ), 'default' => 'Lorem Ipsum', 'connections' => array( 'string' ), 'help' => __( 'Style 2 button / link text; Style 3 text-cell button. (Style 1 uses a chevron only.)', 'ds-toolkit' ) ),
					),
				),
				'bento' => array(
					'title'  => __( 'Bento (Style 3)', 'ds-toolkit' ),
					'fields' => array(
						'type'     => array(
							'type'    => 'select',
							'label'   => __( 'Cell Type', 'ds-toolkit' ),
							'default' => 'image',
							'options' => array( 'image' => __( 'Image', 'ds-toolkit' ), 'text' => __( 'Text / CTA', 'ds-toolkit' ) ),
							'toggle'  => array( 'text' => array( 'fields' => array( 'desc' ) ) ),
							'help'    => __( 'Bento (Style 3) only. Image cells use the Background Image; Text cells use Title + Description + the Link Text button.', 'ds-toolkit' ),
						),
						'desc'     => array( 'type' => 'textarea', 'label' => __( 'Description', 'ds-toolkit' ), 'rows' => 3, 'help' => __( 'Plain text; line breaks kept. Basic inline HTML (e.g. <strong>, <a>) is allowed.', 'ds-toolkit' ) ),
						'col_span' => array( 'type' => 'unit', 'label' => __( 'Column Span', 'ds-toolkit' ), 'default' => '1', 'slider' => array( 'min' => 1, 'max' => 4, 'step' => 1 ) ),
						'row_span' => array( 'type' => 'unit', 'label' => __( 'Row Span', 'ds-toolkit' ), 'default' => '1', 'slider' => array( 'min' => 1, 'max' => 3, 'step' => 1 ) ),
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
						),
					),
				),
			),
			'header' => array(
				'title'  => __( 'Header', 'ds-toolkit' ),
				'fields' => array(
					'show_header'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Header', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'heading', 'header_label' ) ) ),
					),
					'heading'      => array(
						'type'    => 'textarea',
						'label'   => __( 'Heading', 'ds-toolkit' ),
						'rows'    => 2,
						'default' => '{a}Lorem{/a} Ipsum Dolor',
						'help'    => __( 'Wrap a word in {a}…{/a} to colour it with the header accent.', 'ds-toolkit' ),
					),
					'header_label' => array( 'type' => 'text', 'label' => __( 'Right-side Label', 'ds-toolkit' ), 'default' => 'Lorem ipsum →' ),
					'header_desc'  => array( 'type' => 'textarea', 'label' => __( 'Header Description', 'ds-toolkit' ), 'rows' => 3, 'help' => __( 'Paragraph beside the heading (Bento / Style 3).', 'ds-toolkit' ) ),
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
						'help'    => __( 'Wrap a word in {a}…{/a} to colour it with the accent. Line breaks are kept.', 'ds-toolkit' ),
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
