<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Image Carousel — an in-house Beaver Builder image carousel module.
 *
 * A "stacked deck" carousel: the active image sits in front and the upcoming
 * images peek behind it (offset + scale + fade) for depth, cycling with optional
 * autoplay, loop, pause-on-hover and drag/swipe. Each slide can carry a caption
 * pill and a link. An optional offset frame bracket (à la the editorial
 * "LOCKED IN" reference) frames the front card.
 *
 * Multi-STYLE scaffold (same pattern as ds-hero / ds-cta / ds-news / ds-orgstats):
 *   1. Add the style to self::styles() (key + label).
 *   2. Add a render_<key>() method (reuse the shared helpers below).
 *   3. Add its option sections to the form and list those keys under the
 *      `carousel_style` select's `toggle` (keep section keys globally unique).
 *   4. Scope style CSS under the auto-printed `.ds-carousel--<style>` class.
 *
 * No UABB dependency; fully SSH-editable (markup here, styles in css/ + includes/,
 * behaviour in js/).
 *
 * @class DS_Carousel_Module
 */
class DS_Carousel_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Image Carousel', 'ds-toolkit' ),
			'description'     => __( 'Stacked-deck image carousel with autoplay, loop, captions and links.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-carousel/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-carousel/',
			'partial_refresh' => false,
			'editor_export'   => false,
		) );
	}

	public static function styles() {
		return array(
			'style1' => __( 'Style 1 — Stacked Deck', 'ds-toolkit' ),
			'style2' => __( 'Style 2 — Sponsors / Logos', 'ds-toolkit' ),
		);
	}

	/** Resolve a BB photo value (id, url, or {id|url} array/object) -> URL string. */
	private function photo_url( $val, $size = 'large' ) {
		if ( is_array( $val ) )      { $val = $val['id'] ?? ( $val['url'] ?? '' ); }
		elseif ( is_object( $val ) ) { $val = $val->id ?? ( $val->url ?? '' ); }
		if ( is_numeric( $val ) ) { $u = wp_get_attachment_image_url( (int) $val, $size ); return $u ? $u : ''; }
		return $val ? esc_url( (string) $val ) : '';
	}

	/** Fallback image (the Theme Setting Social Card) for slides with no image. */
	private static function placeholder_image() {
		if ( class_exists( 'DS_Social_Card' ) ) {
			$card = DS_Social_Card::get_card();
			if ( ! empty( $card['url'] ) ) { return (string) $card['url']; }
		}
		return '';
	}

	/** Normalise a BB link field (string URL or {url,target} object/array). */
	private function link_parts( $link ) {
		$url = ''; $target = '_self';
		if ( is_array( $link ) )      { $url = $link['url'] ?? ''; $target = $link['target'] ?? '_self'; }
		elseif ( is_object( $link ) ) { $url = $link->url ?? '';  $target = $link->target ?? '_self'; }
		else { $url = (string) $link; }
		return array( esc_url( $url ), '_blank' === $target ? '_blank' : '_self' );
	}

	/** Entry point — dispatch to the selected style. */
	public function render_carousel() {
		$style  = isset( $this->settings->carousel_style ) ? preg_replace( '/[^a-z0-9_]/', '', $this->settings->carousel_style ) : 'style1';
		$method = 'render_' . $style;
		if ( ! array_key_exists( $style, self::styles() ) || ! method_exists( $this, $method ) ) {
			$method = 'render_style1';
		}
		$this->$method();
	}

	/** Normalised slide list (image, caption, url, target). */
	private function collect_slides() {
		$s      = $this->settings;
		$raw    = ( isset( $s->slides ) && is_array( $s->slides ) ) ? $s->slides : array();
		$ph     = self::placeholder_image();
		$slides = array();
		foreach ( $raw as $row ) {
			$row = (object) $row;
			$img = $this->photo_url( $row->image ?? '' );
			if ( '' === $img ) { $img = $ph; }
			list( $url, $target ) = $this->link_parts( $row->link ?? '' );
			$slides[] = array(
				'image'   => $img,
				'caption' => trim( (string) ( $row->caption ?? '' ) ),
				'url'     => $url,
				'target'  => $target,
			);
		}
		return $slides;
	}

	/** Style 1 — Stacked Deck. */
	public function render_style1() {
		$s      = $this->settings;
		$slides = $this->collect_slides();

		$cap_pos = in_array( $s->caption_position ?? 'bl', array( 'bl', 'bc', 'br' ), true ) ? ( $s->caption_position ?? 'bl' ) : 'bl';
		$navpos  = in_array( $s->arrow_position ?? 'overlay', array( 'overlay', 'outside' ), true ) ? ( $s->arrow_position ?? 'overlay' ) : 'overlay';
		$aicon   = in_array( $s->arrow_icon ?? 'chevron', array( 'chevron', 'angle', 'arrow', 'caret' ), true ) ? ( $s->arrow_icon ?? 'chevron' ) : 'chevron';

		$mods = 'ds-carousel ds-carousel--style1 ds-carousel--nav-' . $navpos . ' ds-carousel--cap-' . $cap_pos;

		if ( empty( $slides ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<div class="' . esc_attr( $mods ) . '"><p style="padding:18px;opacity:.7;text-align:center">' . esc_html__( 'Add slides in the module settings.', 'ds-toolkit' ) . '</p></div>';
			}
			return;
		}

		// Behaviour values travel on the deck as data-* (read by js/frontend.js).
		$data = array(
			'data-autoplay' => ( $s->autoplay ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			'data-interval' => (string) max( 1, (int) ( $s->interval ?? 5 ) ),
			'data-loop'     => ( $s->loop ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			'data-pause'    => ( $s->pause_hover ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			'data-drag'     => ( $s->drag ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			'data-visible'  => (string) max( 1, min( 4, (int) ( $s->visible_behind ?? 2 ) ) ),
			'data-offx'     => (string) (int) ( $s->offset_x ?? 40 ),
			'data-offy'     => (string) (int) ( $s->offset_y ?? 0 ),
			'data-scale'    => (string) (int) ( $s->scale_step ?? 8 ),
			'data-rot'      => (string) (int) ( $s->rotate_step ?? 0 ),
			'data-dir'      => in_array( $s->direction ?? 'right', array( 'right', 'left', 'center' ), true ) ? ( $s->direction ?? 'right' ) : 'right',
		);
		$attr = '';
		foreach ( $data as $k => $v ) { $attr .= ' ' . $k . '="' . esc_attr( $v ) . '"'; }

		$arrows = ( $s->show_arrows ?? 'yes' ) === 'yes';
		$dots   = ( $s->show_dots ?? 'yes' ) === 'yes';
		$frame  = ( $s->frame_show ?? 'yes' ) === 'yes';

		echo '<div class="' . esc_attr( $mods ) . '">';
		echo '<div class="ds-carousel-wrap">';
		echo '<div class="ds-carousel-stage">';

		if ( $arrows && count( $slides ) > 1 ) {
			echo '<button type="button" class="ds-carousel-nav ds-carousel-nav--prev" aria-label="' . esc_attr__( 'Previous', 'ds-toolkit' ) . '">' . self::arrow_svg( 'prev', $aicon ) . '</button>';
			echo '<button type="button" class="ds-carousel-nav ds-carousel-nav--next" aria-label="' . esc_attr__( 'Next', 'ds-toolkit' ) . '">' . self::arrow_svg( 'next', $aicon ) . '</button>';
		}

		echo '<div class="ds-carousel-framewrap">';
		if ( $frame ) { echo '<span class="ds-carousel-bracket" aria-hidden="true"></span>'; }
		echo '<div class="ds-carousel-deck"' . $attr . '>';

		foreach ( $slides as $i => $slide ) {
			$has_link = '' !== $slide['url'];
			$tag      = $has_link ? 'a' : 'div';
			$href     = $has_link ? ' href="' . esc_url( $slide['url'] ) . '" target="' . esc_attr( $slide['target'] ) . '"' . ( '_blank' === $slide['target'] ? ' rel="noopener noreferrer"' : '' ) : '';
			$front    = 0 === $i ? ' is-front' : '';

			echo '<' . $tag . ' class="ds-carousel-slide' . $front . '"' . $href . '>';
			if ( '' !== $slide['image'] ) {
				echo '<span class="ds-carousel-img" style="background-image:url(' . esc_url( $slide['image'] ) . ')"></span>';
			}
			if ( '' !== $slide['caption'] ) {
				echo '<span class="ds-carousel-caption">' . esc_html( $slide['caption'] ) . '</span>';
			}
			echo '</' . $tag . '>';
		}

		echo '</div>'; // deck
		echo '</div>'; // framewrap

		if ( $dots && count( $slides ) > 1 ) {
			echo '<div class="ds-carousel-dots">';
			foreach ( $slides as $i => $slide ) {
				echo '<button type="button" class="ds-carousel-dot' . ( 0 === $i ? ' is-active' : '' ) . '" aria-label="' . esc_attr( sprintf( __( 'Go to slide %d', 'ds-toolkit' ), $i + 1 ) ) . '"></button>';
			}
			echo '</div>';
		}

		echo '</div>'; // stage
		echo '</div>'; // wrap
		echo '</div>'; // carousel
	}

	/** Arrow SVG (shared by both styles). $icon: chevron | angle | arrow | caret. */
	private static function arrow_svg( $dir, $icon = 'chevron' ) {
		$prev = 'prev' === $dir;
		switch ( $icon ) {
			case 'arrow':
				$d = $prev ? 'M19 12H5M11 18l-6-6 6-6' : 'M5 12h14M13 6l6 6-6 6';
				return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
			case 'caret':
				$d = $prev ? 'M15 5l-7 7 7 7z' : 'M9 5l7 7-7 7z';
				return '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="' . $d . '"/></svg>';
			case 'angle':
				$d = $prev ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6';
				return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
			case 'chevron':
			default:
				$d = $prev ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6';
				return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
		}
	}

	/**
	 * Style 2 — Sponsors / Logos. A multi-up carousel of logo tiles. Logos of any
	 * size are normalised inside fixed-height tiles (object-fit:contain, centred),
	 * with a controllable column count (responsive), gap, border, radius, and an
	 * optional grayscale→colour-on-hover treatment. Seamless infinite scroll.
	 */
	public function render_style2() {
		$s      = $this->settings;
		$slides = $this->collect_slides();

		$navpos = in_array( $s->arrow_position ?? 'overlay', array( 'overlay', 'outside' ), true ) ? ( $s->arrow_position ?? 'overlay' ) : 'overlay';
		$aicon  = in_array( $s->arrow_icon ?? 'chevron', array( 'chevron', 'angle', 'arrow', 'caret' ), true ) ? ( $s->arrow_icon ?? 'chevron' ) : 'chevron';

		$mods = 'ds-carousel ds-carousel--style2 ds-carousel--nav-' . $navpos;
		if ( ( $s->logo_treatment ?? 'grayscale' ) === 'grayscale' ) { $mods .= ' is-grayscale'; }

		if ( empty( $slides ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<div class="' . esc_attr( $mods ) . '"><p style="padding:18px;opacity:.7;text-align:center">' . esc_html__( 'Add sponsor logos in the module settings.', 'ds-toolkit' ) . '</p></div>';
			}
			return;
		}

		// Behaviour values travel on the track as data-* (read by js/frontend.js).
		$data = array(
			'data-autoplay' => ( $s->autoplay ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			'data-interval' => (string) max( 1, (int) ( $s->interval ?? 4 ) ),
			'data-loop'     => ( $s->loop ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			'data-pause'    => ( $s->pause_hover ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			'data-drag'     => ( $s->drag ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			'data-speed'    => (string) max( 100, (int) ( $s->speed ?? 600 ) ),
		);
		$attr = '';
		foreach ( $data as $k => $v ) { $attr .= ' ' . $k . '="' . esc_attr( $v ) . '"'; }

		$arrows = ( $s->show_arrows ?? 'yes' ) === 'yes';
		$dots   = ( $s->show_dots ?? 'yes' ) === 'yes';
		$head   = ( $s->sl_head_show ?? 'yes' ) === 'yes' && '' !== trim( (string) ( $s->sl_head_label ?? '' ) );

		echo '<div class="' . esc_attr( $mods ) . '">';
		echo '<div class="ds-carousel-wrap">';

		if ( $head ) {
			echo '<div class="ds-logos-head"><span class="ds-logos-head-label">' . esc_html( $s->sl_head_label ) . '</span><span class="ds-logos-head-line" aria-hidden="true"></span></div>';
		}

		echo '<div class="ds-logos-stage">';

		if ( $arrows ) {
			echo '<button type="button" class="ds-carousel-nav ds-carousel-nav--prev" aria-label="' . esc_attr__( 'Previous', 'ds-toolkit' ) . '">' . self::arrow_svg( 'prev', $aicon ) . '</button>';
			echo '<button type="button" class="ds-carousel-nav ds-carousel-nav--next" aria-label="' . esc_attr__( 'Next', 'ds-toolkit' ) . '">' . self::arrow_svg( 'next', $aicon ) . '</button>';
		}

		echo '<div class="ds-logos-viewport"><div class="ds-logos-track"' . $attr . '>';
		foreach ( $slides as $slide ) {
			$name     = $slide['caption'];
			$has_link = '' !== $slide['url'];
			$tag      = $has_link ? 'a' : 'div';
			$href     = $has_link ? ' href="' . esc_url( $slide['url'] ) . '" target="' . esc_attr( $slide['target'] ) . '"' . ( '_blank' === $slide['target'] ? ' rel="noopener noreferrer"' : '' ) : '';
			$title    = '' !== $name ? ' title="' . esc_attr( $name ) . '"' : '';

			echo '<' . $tag . ' class="ds-logos-item"' . $href . $title . '>';
			if ( '' !== $slide['image'] ) {
				echo '<img class="ds-logos-logo" src="' . esc_url( $slide['image'] ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />';
			}
			echo '</' . $tag . '>';
		}
		echo '</div></div>'; // track + viewport

		echo '</div>'; // stage

		if ( $dots && count( $slides ) > 1 ) {
			echo '<div class="ds-carousel-dots">';
			foreach ( $slides as $i => $slide ) {
				echo '<button type="button" class="ds-carousel-dot' . ( 0 === $i ? ' is-active' : '' ) . '" aria-label="' . esc_attr( sprintf( __( 'Go to slide %d', 'ds-toolkit' ), $i + 1 ) ) . '"></button>';
			}
			echo '</div>';
		}

		echo '</div>'; // wrap
		echo '</div>'; // carousel
	}
}

/* --------------------------------------------------------------- Slide sub-form */
FLBuilder::register_settings_form( 'ds_carousel_slide_form', array(
	'title' => __( 'Slide', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Slide', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'image'   => array(
							'type'        => 'photo',
							'label'       => __( 'Image', 'ds-toolkit' ),
							'show_remove' => true,
							'connections' => array( 'photo' ),
							'help'        => __( 'No image falls back to the Theme Setting Social Card.', 'ds-toolkit' ),
						),
						'caption' => array(
							'type'        => 'text',
							'label'       => __( 'Caption', 'ds-toolkit' ),
							'connections' => array( 'string' ),
							'help'        => __( 'Optional. Shown in the caption pill over the image.', 'ds-toolkit' ),
						),
						'link'    => array(
							'type'        => 'link',
							'label'       => __( 'Link', 'ds-toolkit' ),
							'connections' => array( 'url' ),
							'help'        => __( 'Optional. The front slide becomes clickable; click a slide behind to bring it forward.', 'ds-toolkit' ),
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_Carousel_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'carousel_style_sec' => array(
				'title'  => __( 'Carousel Style', 'ds-toolkit' ),
				'fields' => array(
					'carousel_style' => array(
						'type'    => 'select',
						'label'   => __( 'Carousel Style', 'ds-toolkit' ),
						'default' => 'style1',
						'options' => DS_Carousel_Module::styles(),
						'help'    => __( 'Choose a carousel layout. More styles will be added; the options below adapt to the selected style.', 'ds-toolkit' ),
						'toggle'  => array(
							'style1' => array(
								'sections' => array( 'slides', 'behavior', 'stack', 'sizing', 'frame', 'caption', 'navigation', 'colors', 'spacing' ),
							),
							'style2' => array(
								'sections' => array( 'slides', 'sl_header', 'sl_grid', 'sl_tile', 'sl_logo', 'behavior', 'navigation', 'colors', 'spacing' ),
							),
						),
					),
				),
			),
			'slides' => array(
				'title'       => __( 'Slides', 'ds-toolkit' ),
				'description' => __( 'The images in the deck. The first slide starts in front.', 'ds-toolkit' ),
				'fields'      => array(
					'slides' => array(
						'type'         => 'form',
						'label'        => __( 'Slide', 'ds-toolkit' ),
						'form'         => 'ds_carousel_slide_form',
						'preview_text' => 'caption',
						'multiple'     => true,
						'default'      => array(
							array( 'caption' => 'Lorem Ipsum' ),
							array( 'caption' => 'Dolor Sit Amet' ),
							array( 'caption' => 'Consectetur' ),
						),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'behavior' => array(
				'title'  => __( 'Behaviour', 'ds-toolkit' ),
				'fields' => array(
					'autoplay'    => array(
						'type'    => 'select',
						'label'   => __( 'Autoplay', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'interval', 'pause_hover' ) ) ),
						'help'    => __( 'Always paused for reduced-motion visitors.', 'ds-toolkit' ),
					),
					'interval'    => array( 'type' => 'unit', 'label' => __( 'Interval', 'ds-toolkit' ), 'default' => '5', 'description' => 's', 'slider' => array( 'min' => 2, 'max' => 15, 'step' => 1 ) ),
					'pause_hover' => array( 'type' => 'select', 'label' => __( 'Pause on Hover', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'loop'        => array( 'type' => 'select', 'label' => __( 'Loop', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'When off, the carousel stops at the last slide.', 'ds-toolkit' ) ),
					'drag'        => array( 'type' => 'select', 'label' => __( 'Drag / Swipe', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'speed'       => array( 'type' => 'unit', 'label' => __( 'Transition Speed', 'ds-toolkit' ), 'default' => '600', 'description' => 'ms', 'slider' => array( 'min' => 150, 'max' => 1500, 'step' => 50 ) ),
				),
			),
			'stack' => array(
				'title'  => __( 'Stack', 'ds-toolkit' ),
				'fields' => array(
					'direction'      => array( 'type' => 'select', 'label' => __( 'Stack Direction', 'ds-toolkit' ), 'default' => 'right', 'options' => array( 'right' => __( 'Peek Right', 'ds-toolkit' ), 'left' => __( 'Peek Left', 'ds-toolkit' ), 'center' => __( 'Straight Behind', 'ds-toolkit' ) ) ),
					'visible_behind' => array( 'type' => 'unit', 'label' => __( 'Cards Behind', 'ds-toolkit' ), 'default' => '2', 'slider' => array( 'min' => 1, 'max' => 4, 'step' => 1 ), 'help' => __( 'How many stacked cards peek behind the front one.', 'ds-toolkit' ) ),
					'offset_x'       => array( 'type' => 'unit', 'label' => __( 'Horizontal Offset', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 120, 'step' => 2 ) ),
					'offset_y'       => array( 'type' => 'unit', 'label' => __( 'Vertical Offset', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'slider' => array( 'min' => -60, 'max' => 60, 'step' => 2 ) ),
					'scale_step'     => array( 'type' => 'unit', 'label' => __( 'Scale Step', 'ds-toolkit' ), 'default' => '8', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ), 'help' => __( 'How much smaller each card behind gets.', 'ds-toolkit' ) ),
					'rotate_step'    => array( 'type' => 'unit', 'label' => __( 'Rotation Step', 'ds-toolkit' ), 'default' => '0', 'description' => 'deg', 'slider' => array( 'min' => 0, 'max' => 12, 'step' => 1 ) ),
				),
			),
			'sizing' => array(
				'title'  => __( 'Sizing', 'ds-toolkit' ),
				'fields' => array(
					'max_width'   => array( 'type' => 'unit', 'label' => __( 'Card Width', 'ds-toolkit' ), 'default' => '540', 'description' => 'px', 'slider' => array( 'min' => 240, 'max' => 900, 'step' => 10 ) ),
					'aspect'      => array(
						'type'    => 'select',
						'label'   => __( 'Aspect Ratio', 'ds-toolkit' ),
						'default' => '4 / 5',
						'options' => array(
							'4 / 5'   => __( 'Portrait 4:5', 'ds-toolkit' ),
							'3 / 4'   => __( 'Portrait 3:4', 'ds-toolkit' ),
							'1 / 1'   => __( 'Square 1:1', 'ds-toolkit' ),
							'4 / 3'   => __( 'Landscape 4:3', 'ds-toolkit' ),
							'16 / 9'  => __( 'Landscape 16:9', 'ds-toolkit' ),
						),
					),
					'card_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
				),
			),
			'frame' => array(
				'title'  => __( 'Frame Bracket', 'ds-toolkit' ),
				'fields' => array(
					'frame_show'   => array(
						'type'    => 'select',
						'label'   => __( 'Show Frame', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'frame_offset', 'frame_width', 'frame_color' ) ) ),
						'help'    => __( 'An offset outline that frames the front card (editorial look).', 'ds-toolkit' ),
					),
					'frame_offset' => array( 'type' => 'unit', 'label' => __( 'Frame Offset', 'ds-toolkit' ), 'default' => '18', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'frame_width'  => array( 'type' => 'unit', 'label' => __( 'Frame Thickness', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
					'frame_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Frame Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-line-color)', 'show_reset' => true ),
				),
			),
			'caption' => array(
				'title'  => __( 'Caption', 'ds-toolkit' ),
				'fields' => array(
					'caption_position'   => array( 'type' => 'select', 'label' => __( 'Position', 'ds-toolkit' ), 'default' => 'bl', 'options' => array( 'bl' => __( 'Bottom Left', 'ds-toolkit' ), 'bc' => __( 'Bottom Center', 'ds-toolkit' ), 'br' => __( 'Bottom Right', 'ds-toolkit' ) ) ),
					'caption_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Pill Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'caption_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Pill Text', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'caption_radius'     => array( 'type' => 'unit', 'label' => __( 'Pill Radius', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'caption_typography' => array( 'type' => 'typography', 'label' => __( 'Pill Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-carousel-caption' ) ),
				),
			),
			'navigation' => array(
				'title'  => __( 'Navigation', 'ds-toolkit' ),
				'fields' => array(
					'nav_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent (arrow hover / active dot)', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'help' => __( 'Base accent. The per-element colours below override it when set.', 'ds-toolkit' ) ),
					'show_arrows'       => array(
						'type'    => 'select',
						'label'   => __( 'Arrows', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'arrow_icon', 'arrow_position', 'arrow_offset', 'arrow_size', 'arrow_icon_size', 'arrow_radius', 'arrow_bg', 'arrow_color', 'arrow_hover_bg', 'arrow_hover_color', 'arrow_border_width', 'arrow_border_color' ) ) ),
					),
					'arrow_icon'        => array(
						'type'    => 'select',
						'label'   => __( 'Arrow Icon', 'ds-toolkit' ),
						'default' => 'chevron',
						'options' => array(
							'chevron' => __( 'Chevron', 'ds-toolkit' ),
							'angle'   => __( 'Angle (thin)', 'ds-toolkit' ),
							'arrow'   => __( 'Arrow (line)', 'ds-toolkit' ),
							'caret'   => __( 'Caret (solid)', 'ds-toolkit' ),
						),
					),
					'arrow_position'    => array(
						'type'    => 'select',
						'label'   => __( 'Arrow Position', 'ds-toolkit' ),
						'default' => 'overlay',
						'options' => array(
							'overlay' => __( 'Over the edges', 'ds-toolkit' ),
							'outside' => __( 'Outside the edges', 'ds-toolkit' ),
						),
						'help'    => __( 'Overlay sits on top of the content; Outside pushes the arrows beyond it (needs side room).', 'ds-toolkit' ),
					),
					'arrow_offset'      => array( 'type' => 'unit', 'label' => __( 'Edge Offset', 'ds-toolkit' ), 'default' => '6', 'description' => 'px', 'slider' => array( 'min' => -20, 'max' => 60, 'step' => 1 ), 'help' => __( 'Nudge arrows in (+) or out (-) horizontally.', 'ds-toolkit' ) ),
					'arrow_size'        => array( 'type' => 'unit', 'label' => __( 'Button Size', 'ds-toolkit' ), 'default' => '44', 'description' => 'px', 'slider' => array( 'min' => 24, 'max' => 80, 'step' => 1 ) ),
					'arrow_icon_size'   => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'slider' => array( 'min' => 8, 'max' => 48, 'step' => 1 ) ),
					'arrow_radius'      => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '50', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 50, 'step' => 1 ), 'help' => __( '50 = full circle (on a square button). 0 = square.', 'ds-toolkit' ) ),
					'arrow_bg'          => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => 'rgba(15,18,24,0.5)', 'show_reset' => true, 'show_alpha' => true ),
					'arrow_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true, 'show_alpha' => true ),
					'arrow_hover_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank uses the Accent colour above.', 'ds-toolkit' ) ),
					'arrow_hover_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Icon Colour', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'arrow_border_width'=> array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ) ),
					'arrow_border_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'show_dots'         => array(
						'type'    => 'select',
						'label'   => __( 'Dots', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'dot_size', 'dot_gap', 'dot_color', 'dot_active_color' ) ) ),
					),
					'dot_size'          => array( 'type' => 'unit', 'label' => __( 'Dot Size', 'ds-toolkit' ), 'default' => '9', 'description' => 'px', 'slider' => array( 'min' => 4, 'max' => 24, 'step' => 1 ) ),
					'dot_gap'           => array( 'type' => 'unit', 'label' => __( 'Dot Gap', 'ds-toolkit' ), 'default' => '9', 'description' => 'px', 'slider' => array( 'min' => 2, 'max' => 30, 'step' => 1 ) ),
					'dot_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Dot Colour', 'ds-toolkit' ), 'default' => 'rgba(20,20,20,0.25)', 'show_reset' => true, 'show_alpha' => true ),
					'dot_active_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active Dot Colour', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank uses the Accent colour above.', 'ds-toolkit' ) ),
				),
			),
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'section_bg' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Section Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Optional. Blank = transparent.', 'ds-toolkit' ) ),
				),
			),
			// ---- Style 2 only: Sponsors / logos ----
			'sl_header' => array(
				'title'  => __( 'Header', 'ds-toolkit' ),
				'fields' => array(
					'sl_head_show'       => array(
						'type'    => 'select',
						'label'   => __( 'Show Header', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'sl_head_label', 'sl_head_color', 'sl_head_line_color', 'sl_head_typography' ) ) ),
					),
					'sl_head_label'      => array( 'type' => 'text', 'label' => __( 'Header Label', 'ds-toolkit' ), 'default' => 'Sponsors and Partners', 'connections' => array( 'string' ) ),
					'sl_head_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Label Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-headings)', 'show_reset' => true ),
					'sl_head_line_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent Line Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'sl_head_typography' => array( 'type' => 'typography', 'label' => __( 'Label Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-logos-head-label' ) ),
				),
			),
			'sl_grid' => array(
				'title'  => __( 'Grid', 'ds-toolkit' ),
				'fields' => array(
					'columns'      => array( 'type' => 'unit', 'label' => __( 'Columns (logos in view)', 'ds-toolkit' ), 'default' => '6', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ), 'help' => __( 'How many logos show at once. Defaults step down on tablet (3) and mobile (2).', 'ds-toolkit' ) ),
					'gap'          => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'logo_height'  => array( 'type' => 'unit', 'label' => __( 'Tile Height', 'ds-toolkit' ), 'default' => '120', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 60, 'max' => 240, 'step' => 4 ), 'help' => __( 'Every tile is the same height, so mixed logo sizes line up cleanly.', 'ds-toolkit' ) ),
					'tile_padding' => array( 'type' => 'unit', 'label' => __( 'Tile Padding', 'ds-toolkit' ), 'default' => '22', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				),
			),
			'sl_tile' => array(
				'title'  => __( 'Tile', 'ds-toolkit' ),
				'fields' => array(
					'tile_bg'                 => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Tile Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'A solid (often white) tile keeps mixed-colour logos legible on any section background.', 'ds-toolkit' ) ),
					'tile_hover_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Tile Hover Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'tile_border_width'       => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 8, 'step' => 1 ) ),
					'tile_border_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => 'rgba(0,0,0,0.08)', 'show_reset' => true, 'show_alpha' => true ),
					'tile_border_hover_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Hover Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'show_alpha' => true ),
					'tile_radius'             => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'tile_hover_lift'         => array( 'type' => 'select', 'label' => __( 'Lift on Hover', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Subtle raise + shadow on hover (linked tiles only).', 'ds-toolkit' ) ),
				),
			),
			'sl_logo' => array(
				'title'  => __( 'Logos', 'ds-toolkit' ),
				'fields' => array(
					'logo_treatment'  => array(
						'type'    => 'select',
						'label'   => __( 'Logo Treatment', 'ds-toolkit' ),
						'default' => 'grayscale',
						'options' => array(
							'grayscale' => __( 'Grayscale → colour on hover', 'ds-toolkit' ),
							'color'     => __( 'Full colour', 'ds-toolkit' ),
						),
						'help'    => __( 'Grayscale gives a tidy, uniform sponsor wall that pops to colour on hover.', 'ds-toolkit' ),
					),
					'logo_max_height' => array( 'type' => 'unit', 'label' => __( 'Max Logo Height', 'ds-toolkit' ), 'default' => '64', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 24, 'max' => 160, 'step' => 2 ), 'help' => __( 'Caps how tall any single logo can get inside its tile, so a tall logo never dwarfs a wide one. Logos always keep their aspect ratio (never stretched).', 'ds-toolkit' ) ),
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'padding' => array( 'type' => 'dimension', 'label' => __( 'Padding', 'ds-toolkit' ), 'default' => '0', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true ),
					'margin'  => array( 'type' => 'dimension', 'label' => __( 'Margin', 'ds-toolkit' ), 'default' => '0', 'units' => array( 'px' ), 'slider' => true, 'responsive' => true ),
				),
			),
		),
	),
) );
