<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Hero Banner — an in-house Beaver Builder hero module.
 *
 * A full-bleed hero (eyebrow, big headline with an accent word, subtext, two
 * CTA buttons, and a stats/proof row) over a configurable background:
 *   - a single image, an image SLIDESHOW (cross-fade), a looping VIDEO, or a
 *     MIXED MEDIA slideshow (any mix of image and video slides — GH #78),
 * with a configurable colour OVERLAY (solid or gradient) on top.
 *
 * Modeled on the Style 1 hero in the DS blueprint (nhbfutbolclub design).
 * Fully owned / SSH-editable (markup here, styles in css/ + includes/, behavior
 * in js/). No UABB dependency.
 *
 * @class DS_Hero_Module
 */
class DS_Hero_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Hero Banner', 'ds-toolkit' ),
			'description'     => __( 'Full-bleed hero with image/slideshow/video/mixed-media background and overlay.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-hero/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-hero/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/** Heading markup: escape, turn {a}..{/a} into an accent span, newlines into <br>. */
	private function heading_html( $raw ) {
		$h = DS_Module_UI::inline( (string) $raw ); // safe inline HTML (span/strong/em...) allowed
		$h = str_replace( array( '{a}', '{/a}' ), array( '<span class="ds-hero-accent">', '</span>' ), $h );
		$h = str_replace( array( '{outline}', '{/outline}' ), array( '<span class="ds-outline-text">', '</span>' ), $h );
		return nl2br( $h );
	}

	/** Resolve a photo id / url / {id|url} array|object to a usable URL. */
	private function photo_url( $val, $size = 'full' ) {
		if ( is_array( $val ) ) {
			$val = $val['id'] ?? ( $val['url'] ?? '' );
		} elseif ( is_object( $val ) ) {
			$val = $val->id ?? ( $val->url ?? '' );
		}
		if ( is_numeric( $val ) ) {
			$u = wp_get_attachment_image_url( (int) $val, $size );
			return $u ? $u : '';
		}
		return $val ? esc_url( (string) $val ) : '';
	}

	/** Resolve a Media Library video (attachment id / {id} array|object) with a URL fallback. */
	private function video_src( $media, $url ) {
		if ( ! empty( $media ) ) {
			$id = is_object( $media ) ? ( $media->id ?? 0 ) : ( is_array( $media ) ? ( $media['id'] ?? 0 ) : $media );
			if ( is_numeric( $id ) ) {
				$u = (string) wp_get_attachment_url( (int) $id );
				if ( '' !== $u ) { return $u; }
			}
		}
		return trim( (string) $url );
	}

	/**
	 * Normalized Mixed Media slides: [{type, url, poster, advance}].
	 * Slides without usable media are skipped, so render and nav always agree.
	 */
	private function mixed_slides() {
		$out = array();
		$raw = $this->settings->mixed_slides ?? array();
		if ( ! is_array( $raw ) ) { return $out; }
		foreach ( $raw as $slide ) {
			$slide = (object) $slide;
			if ( ( $slide->slide_type ?? 'image' ) === 'video' ) {
				$url = $this->video_src( $slide->video_media ?? '', $slide->video_url ?? '' );
				if ( '' === $url ) { continue; }
				$out[] = array(
					'type'    => 'video',
					'url'     => $url,
					'poster'  => ! empty( $slide->video_poster ) ? $this->photo_url( $slide->video_poster ) : '',
					'advance' => ( $slide->video_advance ?? 'end' ) === 'interval' ? 'interval' : 'end',
				);
			} else {
				$url = ! empty( $slide->photo ) ? $this->photo_url( $slide->photo, 'full' ) : '';
				if ( '' === $url ) { continue; }
				$out[] = array( 'type' => 'image', 'url' => $url, 'poster' => '', 'advance' => 'interval' );
			}
		}
		return $out;
	}

	/** Background markup (image / slideshow / video / mixed media). */
	private function render_bg() {
		$s    = $this->settings;
		$type = $s->bg_type ?? 'image';
		$vurl = 'video' === $type ? $this->video_src( $s->video_media ?? '', $s->video_url ?? '' ) : '';

		echo '<div class="ds-hero-bg">';

		if ( 'video' === $type && '' !== $vurl ) {
			$poster = ! empty( $s->video_poster ) ? $this->photo_url( $s->video_poster ) : '';
			printf(
				'<video class="ds-hero-video" autoplay muted loop playsinline%s><source src="%s" type="video/mp4"></video>',
				$poster ? ' poster="' . esc_url( $poster ) . '"' : '',
				esc_url( $vurl )
			);
		} elseif ( 'mixed' === $type ) {
			$i = 0;
			foreach ( $this->mixed_slides() as $slide ) {
				if ( 'video' === $slide['type'] ) {
					// Poster paints the slide div behind the video while it loads.
					// Only the first slide autoplays / preloads fully; later videos
					// load metadata and are played by JS when their slide activates.
					printf(
						'<div class="ds-hero-slide ds-hero-slide--video%s" data-advance="%s"%s><video class="ds-hero-video" muted playsinline preload="%s"%s%s%s><source src="%s" type="video/mp4"></video></div>',
						0 === $i ? ' is-active' : '',
						esc_attr( $slide['advance'] ),
						$slide['poster'] ? ' style="background-image:url(' . esc_url( $slide['poster'] ) . ')"' : '',
						0 === $i ? 'auto' : 'metadata',
						'interval' === $slide['advance'] ? ' loop' : '',
						0 === $i ? ' autoplay' : '',
						$slide['poster'] ? ' poster="' . esc_url( $slide['poster'] ) . '"' : '',
						esc_url( $slide['url'] )
					);
				} else {
					printf(
						'<div class="ds-hero-slide%s" style="background-image:url(%s)"></div>',
						0 === $i ? ' is-active' : '',
						esc_url( $slide['url'] )
					);
				}
				$i++;
			}
		} elseif ( 'slideshow' === $type && ! empty( $s->bg_photos ) ) {
			$raw = $s->bg_photos;
			$ids = is_array( $raw ) ? $raw : array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) );
			$i   = 0;
			foreach ( $ids as $id ) {
				$url = $this->photo_url( $id, 'full' );
				if ( ! $url ) { continue; }
				printf(
					'<div class="ds-hero-slide%s" style="background-image:url(%s)"></div>',
					0 === $i ? ' is-active' : '',
					esc_url( $url )
				);
				$i++;
			}
		} else { // image
			$url = ! empty( $s->bg_photo ) ? $this->photo_url( $s->bg_photo, 'full' ) : '';
			if ( $url ) {
				printf( '<img class="ds-hero-img" src="%s" alt="" loading="eager">', esc_url( $url ) );
			}
		}

		echo '</div><div class="ds-hero-overlay" aria-hidden="true"></div>';
	}

	/** Slideshow navigation (arrows / dots). Renders only for a 2+ slide slideshow (image or mixed). */
	private function render_slide_nav() {
		$s    = $this->settings;
		$type = $s->bg_type ?? 'image';
		if ( ! in_array( $type, array( 'slideshow', 'mixed' ), true ) ) { return; }
		$nav = $s->slide_nav ?? 'lines';
		if ( 'none' === $nav ) { return; }
		if ( 'mixed' === $type ) {
			$count = count( $this->mixed_slides() );
		} else {
			$raw = $s->bg_photos ?? '';
			$ids = is_array( $raw ) ? $raw : array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) );
			$count = 0;
			foreach ( $ids as $id ) { if ( $this->photo_url( $id, 'full' ) ) { $count++; } }
		}
		if ( $count < 2 ) { return; }

		$arrows = in_array( $nav, array( 'arrows', 'both', 'lines_arrows' ), true );
		$lines  = in_array( $nav, array( 'lines', 'lines_arrows' ), true );
		$dots   = in_array( $nav, array( 'dots', 'both' ), true );

		if ( $arrows ) {
			echo '<button type="button" class="ds-hero-nav ds-hero-nav--prev" aria-label="' . esc_attr__( 'Previous slide', 'ds-toolkit' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>';
			echo '<button type="button" class="ds-hero-nav ds-hero-nav--next" aria-label="' . esc_attr__( 'Next slide', 'ds-toolkit' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>';
		}
		if ( $lines ) {
			echo '<div class="ds-hero-bars">';
			for ( $d = 0; $d < $count; $d++ ) {
				echo '<button type="button" class="ds-hero-bar' . ( 0 === $d ? ' is-active' : '' ) . '" aria-label="' . esc_attr( sprintf( __( 'Go to slide %d', 'ds-toolkit' ), $d + 1 ) ) . '"><span class="ds-hero-bar-fill"></span></button>';
			}
			echo '</div>';
		}
		if ( $dots ) {
			echo '<div class="ds-hero-dots">';
			for ( $d = 0; $d < $count; $d++ ) {
				echo '<button type="button" class="ds-hero-dot' . ( 0 === $d ? ' is-active' : '' ) . '" aria-label="' . esc_attr( sprintf( __( 'Go to slide %d', 'ds-toolkit' ), $d + 1 ) ) . '"></button>';
			}
			echo '</div>';
		}
	}

	/** A CTA button (returns '' when no text). */
	private function button( $text, $link, $style, $arrow ) {
		$text = trim( (string) $text );
		if ( '' === $text ) { return ''; }
		$cls   = 'primary' === $style ? 'ds-hero-btn ds-hero-btn--primary' : 'ds-hero-btn ds-hero-btn--ghost';
		$arrow_svg = $arrow ? ' <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>' : '';
		return sprintf(
			'<a class="%s" href="%s">%s%s</a>',
			esc_attr( $cls ),
			esc_url( $link ? $link : '#' ),
			esc_html( $text ),
			$arrow_svg
		);
	}

	/** Stats / proof row. */
	private function render_proof() {
		$s     = $this->settings;
		$items = array();
		for ( $n = 1; $n <= 3; $n++ ) {
			$num = trim( (string) ( $s->{"stat{$n}_number"} ?? '' ) );
			$lbl = trim( (string) ( $s->{"stat{$n}_label"} ?? '' ) );
			if ( '' !== $num || '' !== $lbl ) {
				$items[] = '<div class="ds-hero-stat"><span class="ds-hero-stat-n">' . esc_html( $num ) . '</span><span class="ds-hero-stat-l">' . esc_html( $lbl ) . '</span></div>';
			}
		}
		if ( empty( $items ) ) { return; }
		echo '<div class="ds-hero-proof">' . implode( '<span class="ds-hero-sep" aria-hidden="true"></span>', $items ) . '</div>';
	}

	/**
	 * Entry point — dispatches to the selected hero style.
	 *
	 * HOW TO ADD A NEW STYLE:
	 *   1. Add it to self::styles() (key + label).
	 *   2. Add a `render_<key>()` method (reuse the shared helpers: render_bg(),
	 *      button(), render_proof(), heading_html()).
	 *   3. Add its option sections to the form below and list those section keys
	 *      under the `hero_style` select's `toggle` for that key so they only show
	 *      when the style is selected.
	 *   4. Scope any style-specific CSS under `.ds-hero--<key>` (the modifier class
	 *      is printed on the section automatically).
	 */
	public static function styles() {
		return array(
			'style1' => __( 'Style 1 — Classic', 'ds-toolkit' ),
			'style2' => __( 'Style 2 — Page Banner (auto)', 'ds-toolkit' ),
		);
	}

	public function render_hero() {
		$style   = isset( $this->settings->hero_style ) ? preg_replace( '/[^a-z0-9_]/', '', $this->settings->hero_style ) : 'style1';
		$method  = 'render_' . $style;
		if ( ! array_key_exists( $style, self::styles() ) || ! method_exists( $this, $method ) ) {
			$method = 'render_style1';
		}
		$this->$method();
	}

	/** Style 1 — Classic full-bleed hero. */
	public function render_style1() {
		$s        = $this->settings;
		$align    = ( isset( $s->align ) && 'center' === $s->align ) ? 'center' : 'left';
		$interval = isset( $s->slide_interval ) && '' !== $s->slide_interval ? (int) $s->slide_interval : 6;

		$kb = ( ( $s->kenburns ?? 'no' ) === 'yes' ) ? ' ds-hero--kenburns' : '';
		echo '<section class="ds-hero ds-hero--style1 ds-hero--' . esc_attr( $align ) . $kb . '" data-interval="' . esc_attr( $interval ) . '">';
		$this->render_bg();
		echo '<div class="ds-hero-wrap"><div class="ds-hero-inner">';

		if ( ! empty( $s->eyebrow ) ) {
			echo '<span class="ds-hero-eyebrow">' . esc_html( $s->eyebrow ) . '</span>';
		}
		if ( ! empty( $s->heading ) ) {
			echo '<h1 class="ds-hero-title">' . $this->heading_html( $s->heading ) . '</h1>';
		}
		if ( ! empty( $s->subtext ) ) {
			echo '<div class="ds-hero-sub">' . wpautop( wp_kses_post( $s->subtext ) ) . '</div>';
		}

		$b1 = $this->button( $s->btn1_text ?? '', $s->btn1_link ?? '', $s->btn1_style ?? 'primary', true );
		$b2 = $this->button( $s->btn2_text ?? '', $s->btn2_link ?? '', $s->btn2_style ?? 'ghost', false );
		if ( $b1 || $b2 ) {
			echo '<div class="ds-hero-cta">' . $b1 . $b2 . '</div>';
		}

		$this->render_proof();

		echo '</div></div>';
		$this->render_slide_nav();
		echo '</section>';
	}

	/**
	 * Style 2 — Page Banner. Auto-fills from the current page: title (or the
	 * Page Banner 'lead heading' override), the lead description, and the Page
	 * Banner photo/video. Taller when a background exists, compact (with an
	 * optional pattern/colour) when it doesn't. Built for the inner-page Themer template.
	 */
	public function render_style2() {
		$s     = $this->settings;
		$pid   = get_the_ID();
		$align = in_array( $s->banner_align ?? 'center', array( 'left', 'center', 'right' ), true ) ? ( $s->banner_align ?? 'center' ) : 'center';

		// Start from the page data, then let the module fields (typed or
		// dynamic-field-connected) override anything that's set.
		$heading = ''; $sub = ''; $img = ''; $video = '';
		$is_arch = ! is_singular() && ( is_archive() || is_home() || is_search() );
		if ( $is_arch ) {
			// Archive / blog / search: the banner title is the archive title (clean — no
			// "Archive:" / "Category:" prefix), so this same banner powers every archive
			// (taxonomy terms like team-category/u16, category, tag, CPT archive, search).
			if ( is_tax() || is_category() || is_tag() ) { $heading = single_term_title( '', false ); }
			elseif ( is_post_type_archive() )            { $heading = post_type_archive_title( '', false ); }
			elseif ( is_search() )                       { $heading = sprintf( esc_html__( 'Search: %s', 'ds-toolkit' ), get_search_query() ); }
			else                                         { $heading = wp_strip_all_tags( get_the_archive_title() ); }
		} elseif ( $pid ) {
			$heading = (string) get_the_title( $pid );
			if ( function_exists( 'get_field' ) ) {
				$lead = trim( (string) get_field( 'hero_banner_lead_heading', $pid ) );
				if ( '' !== $lead ) { $heading = $lead; }
				$sub = trim( (string) get_field( 'hero_banner_lead_description', $pid ) );
				$imv = get_field( 'page_hero_banner_image', $pid ); if ( is_array( $imv ) ) { $img = $imv['url'] ?? ''; }
				$vid = get_field( 'video_page_hero_banner', $pid ); if ( is_array( $vid ) ) { $video = $vid['url'] ?? ''; }
			}
			// Fallback: a post's Featured Image becomes the banner background — but only
			// for post types allowed in Theme Setting -> Page Banner (Featured Image
			// Banner). Staff is off by default: portrait headshots crop badly here, so
			// staff singles get the text-only banner over the No-Image background.
			$feat_ok = class_exists( 'DS_Theme_Setting' ) ? DS_Theme_Setting::banner_featured_allowed( get_post_type( $pid ) ) : true;
			if ( '' === $img && '' === $video && $feat_ok && has_post_thumbnail( $pid ) ) {
				$img = (string) get_the_post_thumbnail_url( $pid, 'full' );
			}
		}

		// Module overrides (connectable to dynamic fields).
		if ( '' !== trim( (string) ( $s->banner_heading ?? '' ) ) )  { $heading = trim( (string) $s->banner_heading ); }
		if ( '' !== trim( (string) ( $s->banner_subtitle ?? '' ) ) ) { $sub = trim( (string) $s->banner_subtitle ); }
		$ov_img = $this->photo_url( $s->banner_image ?? '' );
		if ( '' !== $ov_img )                                        { $img = $ov_img; }
		if ( '' !== trim( (string) ( $s->banner_video ?? '' ) ) )    { $video = esc_url( trim( (string) $s->banner_video ) ); }

		$has_bg = ( '' !== $img || '' !== $video );

		// Hide the auto heading/subtitle when there's a background image/video, for a
		// clean image-only banner.
		// Title on photo banners: the Theme Setting (Page Banner -> Title on Photo
		// Banners) is the SITE-WIDE authority. Only the module's new explicit
		// per-page values (force_show / force_hide) override it. Legacy baked
		// values ('yes'/'no' from older blueprints) follow the Theme Setting too —
		// they were build-time defaults, not per-page choices, and previously made
		// the site-wide control impossible to honour.
		$ts_hide = class_exists( 'DS_Theme_Setting' ) && DS_Theme_Setting::banner_photo_title_hidden();
		$mode    = (string) ( $s->banner_hide_text ?? '' );
		if ( 'force_show' === $mode )     { $hide = false; }
		elseif ( 'force_hide' === $mode ) { $hide = true; }
		else                              { $hide = $ts_hide; }
		$hide_text = $hide && $has_bg;
		if ( $hide_text ) {
			$heading = ''; $sub = '';
		} elseif ( '' === $heading && FLBuilderModel::is_builder_active() ) {
			$heading = __( 'Page Title (fills from each page)', 'ds-toolkit' );
		}

		// Parallax: only for a photo banner (a fixed video can't parallax cleanly).
		$parallax = ( ( $s->banner_parallax ?? 'no' ) === 'yes' ) && ( '' !== $img ) && ( '' === $video );

		$mods = 'ds-hero ds-hero--style2 ds-hero--banner ds-banner--' . $align . ( $has_bg ? ' ds-banner--has-bg' : ' ds-banner--no-bg' ) . ( $parallax ? ' ds-banner--parallax' : '' );
		echo '<section class="' . esc_attr( $mods ) . '"' . ( $parallax ? ' data-parallax="1"' : '' ) . '>';
		if ( '' !== $video ) {
			echo '<div class="ds-hero-bg"><video class="ds-hero-video" autoplay muted loop playsinline src="' . esc_url( $video ) . '"></video></div>';
		} elseif ( '' !== $img ) {
			echo '<div class="ds-hero-bg" style="background-image:url(' . esc_url( $img ) . ')"></div>';
		}
		// Designed darkening preset over the photo, for legibility (Image Scrim).
		$scrim = preg_replace( '/[^a-z]/', '', (string) ( $s->scrim_preset ?? 'none' ) );
		if ( 'none' !== $scrim && $has_bg ) {
			echo '<div class="ds-banner-scrim ds-banner-scrim--' . esc_attr( $scrim ) . '" aria-hidden="true"></div>';
		}
		// Overlay always present so the Overlay setting works on photo, video, AND plain
		// (colour / pattern) banners. Set Overlay = None to disable it.
		if ( ( $s->overlay_style ?? 'gradient' ) !== 'none' ) {
			echo '<div class="ds-hero-overlay" aria-hidden="true"></div>';
		}
		$eyebrow = $hide_text ? '' : trim( (string) ( $s->banner_eyebrow ?? '' ) );

		// Yoast breadcrumbs (optional, top or below the text).
		$crumbs = '';
		if ( ( $s->show_breadcrumbs ?? 'no' ) === 'yes' && function_exists( 'yoast_breadcrumb' ) ) {
			$crumbs = yoast_breadcrumb( '<nav class="ds-banner-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'ds-toolkit' ) . '">', '</nav>', false );
		}
		$crumb_pos = in_array( $s->breadcrumbs_position ?? 'top', array( 'top', 'below' ), true ) ? ( $s->breadcrumbs_position ?? 'top' ) : 'top';

		echo '<div class="ds-hero-wrap"><div class="ds-hero-inner">';
		if ( '' !== $crumbs && 'top' === $crumb_pos ) { echo $crumbs; }
		if ( '' !== $eyebrow ) { echo '<span class="ds-banner-eyebrow">' . esc_html( $eyebrow ) . '</span>'; }
		if ( '' !== $heading ) { echo '<h1 class="ds-hero-title">' . $this->heading_html( $heading ) . '</h1>'; }
		if ( '' !== $sub )     { echo '<div class="ds-hero-sub">' . wpautop( wp_kses_post( $sub ) ) . '</div>'; }
		if ( '' !== $crumbs && 'below' === $crumb_pos ) { echo $crumbs; }
		echo '</div></div></section>';
	}
}

/* ---------------------------------------------------- Mixed Media slide sub-form */

FLBuilder::register_settings_form( 'ds_hero_slide_form', array(
	'title' => __( 'Slide', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Slide', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'slide_type' => array(
							'type'    => 'select',
							'label'   => __( 'Slide Type', 'ds-toolkit' ),
							'default' => 'image',
							'options' => array(
								'image' => __( 'Image', 'ds-toolkit' ),
								'video' => __( 'Video', 'ds-toolkit' ),
							),
							'toggle'  => array(
								'image' => array( 'fields' => array( 'photo' ) ),
								'video' => array( 'fields' => array( 'video_media', 'video_url', 'video_poster', 'video_advance' ) ),
							),
						),
						'photo' => array(
							'type'        => 'photo',
							'label'       => __( 'Image', 'ds-toolkit' ),
							'show_remove' => true,
							'connections' => array( 'photo' ),
						),
						'video_media' => array(
							'type'  => 'video',
							'label' => __( 'Video (Media Library)', 'ds-toolkit' ),
							'help'  => __( 'Upload / pick an MP4 from the Media Library. Takes priority over the Video URL below.', 'ds-toolkit' ),
						),
						'video_url' => array(
							'type'        => 'text',
							'label'       => __( 'Video URL (MP4)', 'ds-toolkit' ),
							'connections' => array( 'url' ),
							'help'        => __( 'A direct .mp4 URL if the video is hosted elsewhere.', 'ds-toolkit' ),
						),
						'video_poster' => array(
							'type'        => 'photo',
							'label'       => __( 'Poster Image', 'ds-toolkit' ),
							'show_remove' => true,
							'connections' => array( 'photo' ),
							'help'        => __( 'Optional. Shown while the video loads.', 'ds-toolkit' ),
						),
						'video_advance' => array(
							'type'    => 'select',
							'label'   => __( 'Next Slide', 'ds-toolkit' ),
							'default' => 'end',
							'options' => array(
								'end'      => __( 'When the video ends', 'ds-toolkit' ),
								'interval' => __( 'After the Slide Interval (video loops)', 'ds-toolkit' ),
							),
							'help'    => __( 'Videos autoplay muted while their slide is showing (browsers require muted autoplay). Choose when the slideshow moves on.', 'ds-toolkit' ),
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_Hero_Module', array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'hero_style_sec' => array(
				'title'  => __( 'Hero Style', 'ds-toolkit' ),
				'fields' => array(
					'hero_style' => array(
						'type'    => 'select',
						'label'   => __( 'Hero Style', 'ds-toolkit' ),
						'default' => 'style1',
						'options' => DS_Hero_Module::styles(),
						'help'    => __( 'Choose a hero layout. More styles will be added; the options below adapt to the selected style.', 'ds-toolkit' ),
						// Each style shows only its own sections. Add a key per style.
						'toggle'  => array(
							'style1' => array(
								'sections' => array( 'text', 'buttons', 'stats', 'bg', 'overlay', 'layout', 'spacing', 'colors', 'typography' ),
							),
							'style2' => array(
								'sections' => array( 'banner', 'banner_design', 'banner_height', 'banner_nobg', 'overlay', 'banner_scrim', 'typography', 'spacing' ),
							),
						),
					),
				),
			),
			'banner' => array(
				'title'       => __( 'Page Banner', 'ds-toolkit' ),
				'description' => __( 'Leave everything blank and the banner fills in automatically from each page (the page name + its Page Banner box). To override, type a value or click the dynamic-field (database) icon on a field to connect it to any field.', 'ds-toolkit' ),
				'fields'      => array(
					'banner_eyebrow'  => array( 'type' => 'text', 'label' => __( 'Sub-heading (eyebrow)', 'ds-toolkit' ), 'default' => '', 'connections' => array( 'string' ), 'help' => __( 'Small line above the heading (optional).', 'ds-toolkit' ) ),
					'banner_heading'  => array( 'type' => 'text', 'label' => __( 'Heading', 'ds-toolkit' ), 'default' => '', 'connections' => array( 'string' ), 'help' => __( 'Blank = the page’s Banner Title, or the page name.', 'ds-toolkit' ) ),
					'banner_subtitle' => array( 'type' => 'editor', 'media_buttons' => false, 'wpautop' => false, 'label' => __( 'Subtitle', 'ds-toolkit' ), 'default' => '', 'rows' => 2, 'connections' => array( 'string' ), 'help' => __( 'Blank = the page’s Banner Subtitle.', 'ds-toolkit' ) ),
					'banner_image'    => array( 'type' => 'photo', 'label' => __( 'Background Photo', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Blank = the page’s Banner photo.', 'ds-toolkit' ) ),
					'banner_video'    => array( 'type' => 'text', 'label' => __( 'Background Video URL', 'ds-toolkit' ), 'default' => '', 'connections' => array( 'string' ), 'help' => __( 'Optional MP4 URL. Blank = the page’s Banner video.', 'ds-toolkit' ) ),
					'banner_hide_text' => array(
						'type'    => 'select',
						'label'   => __( 'Text When Image Present', 'ds-toolkit' ),
						'default' => '',
						'options' => array(
							''           => __( 'Theme Setting default (recommended)', 'ds-toolkit' ),
							'force_show' => __( 'Always show (this instance)', 'ds-toolkit' ),
							'force_hide' => __( 'Always hide — image only (this instance)', 'ds-toolkit' ),
						),
						'help'    => __( 'When the banner has a background image or video, hide the auto heading / subtitle for a clean image-only banner.', 'ds-toolkit' ),
					),
					'show_breadcrumbs'     => array(
						'type'    => 'select',
						'label'   => __( 'Breadcrumbs', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'no' => __( 'Hide', 'ds-toolkit' ), 'yes' => __( 'Show (Yoast)', 'ds-toolkit' ) ),
						'help'    => __( 'Shows the Yoast SEO breadcrumb trail. Enable Yoast breadcrumbs under SEO → Settings → Breadcrumbs.', 'ds-toolkit' ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'breadcrumbs_position' ) ) ),
					),
					'breadcrumbs_position' => array( 'type' => 'select', 'label' => __( 'Breadcrumbs Position', 'ds-toolkit' ), 'default' => 'top', 'options' => array( 'top' => __( 'Top (above heading)', 'ds-toolkit' ), 'below' => __( 'Below (under description)', 'ds-toolkit' ) ) ),
				),
			),
			'text' => array(
				'title'  => __( 'Text', 'ds-toolkit' ),
				'fields' => array(
					'eyebrow' => array(
						'type'    => 'text',
						'label'   => __( 'Eyebrow', 'ds-toolkit' ),
						'default' => 'Lorem Ipsum Dolor',
						'help'    => __( 'Small label above the headline (optional).', 'ds-toolkit' ),
					),
					'heading' => array(
						'type'    => 'textarea',
						'label'   => __( 'Headline', 'ds-toolkit' ),
						'rows'    => 3,
						'default' => "Lorem Ipsum\nDolor {a}Sit{/a}",
						'help'    => __( 'Line breaks are kept. Wrap a word in {a}…{/a} to colour it with the accent colour. {outline}…{/outline} renders outlined (transparent, stroked) text — default style in Theme Setting.', 'ds-toolkit' ),
					),
					'subtext' => array(
						'type'    => 'editor', 'media_buttons' => false, 'wpautop' => false,
						'label'   => __( 'Subtext', 'ds-toolkit' ),
						'rows'    => 3,
						'default' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt.',
					),
				),
			),
			'buttons' => array(
				'title'  => __( 'Buttons', 'ds-toolkit' ),
				'fields' => array(
					'btn_global' => array(
						'type'    => 'select',
						'label'   => __( 'Button Style', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array(
							'yes' => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ),
							'no'  => __( 'Accent colour', 'ds-toolkit' ),
						),
						'help'    => __( 'Primary buttons inherit the global Button style (background, hover, radius, typography) from Theme Setting → Elements → Button by default. Both buttons pick up its corner radius and typography.', 'ds-toolkit' ),
					),
					'btn_align' => array(
						'type'       => 'select',
						'label'      => __( 'Buttons Alignment', 'ds-toolkit' ),
						'default'    => '',
						'responsive' => true,
						'options'    => array(
							''       => __( 'Follow Alignment (Layout tab)', 'ds-toolkit' ),
							'left'   => __( 'Left', 'ds-toolkit' ),
							'center' => __( 'Center', 'ds-toolkit' ),
							'right'  => __( 'Right', 'ds-toolkit' ),
						),
						'help'       => __( 'Horizontal position of the button group only — text and stats keep following the module Alignment. Blank = follow Alignment; use the device icon for per-device values. (On phones the buttons go full-width by design, so alignment has no visible effect there.)', 'ds-toolkit' ),
						'preview'    => array( 'type' => 'css', 'selector' => '.ds-hero-cta', 'property' => 'justify-content' ),
					),
					'btn1_text'  => array( 'type' => 'text', 'label' => __( 'Button 1 Text', 'ds-toolkit' ), 'default' => 'Lorem Ipsum' ),
					'btn1_link'  => array( 'type' => 'link', 'label' => __( 'Button 1 Link', 'ds-toolkit' ), 'default' => '' ),
					'btn1_style' => array( 'type' => 'select', 'label' => __( 'Button 1 Style', 'ds-toolkit' ), 'default' => 'primary', 'options' => array( 'primary' => __( 'Primary (filled)', 'ds-toolkit' ), 'ghost' => __( 'Ghost (outline)', 'ds-toolkit' ) ) ),
					'btn2_text'  => array( 'type' => 'text', 'label' => __( 'Button 2 Text', 'ds-toolkit' ), 'default' => 'Dolor Sit' ),
					'btn2_link'  => array( 'type' => 'link', 'label' => __( 'Button 2 Link', 'ds-toolkit' ), 'default' => '' ),
					'btn2_style' => array( 'type' => 'select', 'label' => __( 'Button 2 Style', 'ds-toolkit' ), 'default' => 'ghost', 'options' => array( 'primary' => __( 'Primary (filled)', 'ds-toolkit' ), 'ghost' => __( 'Ghost (outline)', 'ds-toolkit' ) ) ),
				),
			),
			'stats' => array(
				'title'       => __( 'Stats Row', 'ds-toolkit' ),
				'description' => __( 'Up to three proof points. Leave a pair blank to hide it.', 'ds-toolkit' ),
				'fields'      => array(
					'stat1_number' => array( 'type' => 'text', 'label' => __( 'Stat 1 — Number', 'ds-toolkit' ), 'default' => '00' ),
					'stat1_label'  => array( 'type' => 'text', 'label' => __( 'Stat 1 — Label', 'ds-toolkit' ), 'default' => 'Lorem Ipsum' ),
					'stat2_number' => array( 'type' => 'text', 'label' => __( 'Stat 2 — Number', 'ds-toolkit' ), 'default' => '00' ),
					'stat2_label'  => array( 'type' => 'text', 'label' => __( 'Stat 2 — Label', 'ds-toolkit' ), 'default' => 'Dolor Sit Amet' ),
					'stat3_number' => array( 'type' => 'text', 'label' => __( 'Stat 3 — Number', 'ds-toolkit' ), 'default' => 'Lorem' ),
					'stat3_label'  => array( 'type' => 'text', 'label' => __( 'Stat 3 — Label', 'ds-toolkit' ), 'default' => 'Consectetur Elit' ),
				),
			),
			'bg' => array(
				'title'  => __( 'Background', 'ds-toolkit' ),
				'fields' => array(
					'bg_type' => array(
						'type'    => 'select',
						'label'   => __( 'Background Type', 'ds-toolkit' ),
						'default' => 'image',
						'options' => array(
							'image'     => __( 'Single Image', 'ds-toolkit' ),
							'slideshow' => __( 'Image Slideshow', 'ds-toolkit' ),
							'video'     => __( 'Video', 'ds-toolkit' ),
							'mixed'     => __( 'Mixed Media (Images + Videos)', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'image'     => array( 'fields' => array( 'bg_photo', 'kenburns' ) ),
							'slideshow' => array( 'fields' => array( 'bg_photos', 'slide_interval', 'kenburns', 'slide_nav' ) ),
							'video'     => array( 'fields' => array( 'video_media', 'video_url', 'video_poster' ) ),
							'mixed'     => array( 'fields' => array( 'mixed_slides', 'slide_interval', 'kenburns', 'slide_nav' ) ),
						),
					),
					'bg_photo'       => array( 'type' => 'photo', 'label' => __( 'Image', 'ds-toolkit' ), 'show_remove' => true ),
					'bg_photos'      => array( 'type' => 'multiple-photos', 'label' => __( 'Slideshow Images', 'ds-toolkit' ) ),
					'mixed_slides'   => array(
						'type'         => 'form',
						'label'        => __( 'Slide', 'ds-toolkit' ),
						'form'         => 'ds_hero_slide_form',
						'preview_text' => 'slide_type',
						'multiple'     => true,
						'help'         => __( 'Build the slideshow from any mix of image and video slides. Drag to reorder.', 'ds-toolkit' ),
					),
					'slide_interval' => array( 'type' => 'unit', 'label' => __( 'Slide Interval', 'ds-toolkit' ), 'default' => '6', 'description' => 's', 'slider' => array( 'min' => 2, 'max' => 15, 'step' => 1 ) ),
					'kenburns'       => array( 'type' => 'select', 'label' => __( 'Ken Burns Effect', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes (slow zoom)', 'ds-toolkit' ) ), 'help' => __( 'Slowly zooms the background image(s). Disabled for reduced-motion visitors.', 'ds-toolkit' ) ),
					'slide_nav'      => array( 'type' => 'select', 'label' => __( 'Slide Navigation', 'ds-toolkit' ), 'default' => 'lines', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'lines' => __( 'Progress Lines (cooldown)', 'ds-toolkit' ), 'dots' => __( 'Dots', 'ds-toolkit' ), 'arrows' => __( 'Arrows', 'ds-toolkit' ), 'lines_arrows' => __( 'Lines + Arrows', 'ds-toolkit' ), 'both' => __( 'Dots + Arrows', 'ds-toolkit' ) ), 'help' => __( 'Navigation for the slideshow (2+ slides). Progress Lines are thin bars that fill over the slide interval (a cooldown to the next slide; on a play-until-end video slide they fill over the video). The active indicator and arrow hover use the accent colour.', 'ds-toolkit' ) ),
					'video_media'    => array( 'type' => 'video', 'label' => __( 'Video (Media Library)', 'ds-toolkit' ), 'help' => __( 'Upload / pick an MP4 from the Media Library. Takes priority over the Video URL below. Autoplays muted and loops.', 'ds-toolkit' ) ),
					'video_url'      => array( 'type' => 'text', 'label' => __( 'Video URL (MP4)', 'ds-toolkit' ), 'connections' => array( 'url' ), 'help' => __( 'Direct .mp4 URL if the video is hosted elsewhere. Autoplays muted and loops.', 'ds-toolkit' ) ),
					'video_poster'   => array( 'type' => 'photo', 'label' => __( 'Video Poster', 'ds-toolkit' ), 'show_remove' => true ),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'banner_design' => array(
				'title'  => __( 'Banner', 'ds-toolkit' ),
				'fields' => array(
					'banner_align'       => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'center', 'responsive' => true, 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ) ),
					'banner_parallax' => array(
						'type'    => 'select',
						'label'   => __( 'Parallax', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes (photo scrolls slower)', 'ds-toolkit' ) ),
						'help'    => __( 'Background photo moves slower than the page on scroll. Photo banners only; disabled for reduced-motion visitors.', 'ds-toolkit' ),
					),
					'banner_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = automatic: light on a photo, dark on a plain background.', 'ds-toolkit' ) , 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero--banner .ds-hero-title', 'property' => 'color' ) ),
					'banner_sub_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Subtitle Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true , 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero--banner .ds-hero-sub', 'property' => 'color' ) ),
					'breadcrumbs_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Breadcrumbs Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = automatic (light on a photo, muted on a plain background).', 'ds-toolkit' ) , 'preview' => array( 'type' => 'css', 'selector' => '.ds-banner-crumbs, .ds-banner-crumbs a, .ds-banner-crumbs .breadcrumb_last', 'property' => 'color' ) ),
					'breadcrumbs_typography' => array( 'type' => 'typography', 'label' => __( 'Breadcrumbs Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-banner-crumbs' ) ),
				),
			),
			'banner_height' => array(
				'title'  => __( 'Height', 'ds-toolkit' ),
					'description' => __( 'The banner is taller when a page has a photo or video, and shorter when it has none.', 'ds-toolkit' ),
				'fields' => array(
					'banner_h_bg'   => array( 'type' => 'unit', 'label' => __( 'Height — with photo / video', 'ds-toolkit' ), 'default' => '52', 'description' => 'vh', 'responsive' => true, 'slider' => array( 'min' => 20, 'max' => 100, 'step' => 1 ) ),
					'banner_h_nobg' => array( 'type' => 'unit', 'label' => __( 'Height — no photo / video', 'ds-toolkit' ), 'default' => '28', 'description' => 'vh', 'responsive' => true, 'slider' => array( 'min' => 12, 'max' => 80, 'step' => 1 ) ),
				),
			),
			'banner_nobg' => array(
				'title'  => __( 'No-Image Background', 'ds-toolkit' ),
					'description' => __( 'Used only when a page has no banner photo or video. Give plain pages a brand colour and/or a subtle, tileable pattern.', 'ds-toolkit' ),
				'fields' => array(
					'nobg_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the site default from Theme Setting (General > Page Banner). Set a colour to override it for this page.', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-banner--no-bg', 'property' => 'background-color' ) ),
					'nobg_pattern' => array( 'type' => 'photo', 'label' => __( 'Pattern / Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Optional. A small, tileable pattern (PNG/SVG) looks best with Repeat = Tile.', 'ds-toolkit' ) ),
					'nobg_repeat'  => array( 'type' => 'select', 'label' => __( 'Repeat', 'ds-toolkit' ), 'default' => 'repeat', 'options' => array( 'repeat' => __( 'Tile', 'ds-toolkit' ), 'repeat-x' => __( 'Tile horizontally', 'ds-toolkit' ), 'repeat-y' => __( 'Tile vertically', 'ds-toolkit' ), 'no-repeat' => __( 'No repeat', 'ds-toolkit' ) ) ),
					'nobg_size'    => array( 'type' => 'select', 'label' => __( 'Size', 'ds-toolkit' ), 'default' => 'auto', 'options' => array( 'auto' => __( 'Auto (real size — best for patterns)', 'ds-toolkit' ), 'contain' => __( 'Contain', 'ds-toolkit' ), 'cover' => __( 'Cover (fill)', 'ds-toolkit' ) ) ),
					'nobg_blend'   => array( 'type' => 'select', 'label' => __( 'Blend with Colour', 'ds-toolkit' ), 'default' => 'normal', 'options' => array( 'normal' => __( 'Normal (pattern over colour)', 'ds-toolkit' ), 'multiply' => __( 'Multiply', 'ds-toolkit' ), 'overlay' => __( 'Overlay', 'ds-toolkit' ), 'screen' => __( 'Screen', 'ds-toolkit' ), 'soft-light' => __( 'Soft Light', 'ds-toolkit' ) ), 'help' => __( 'How the pattern mixes with the Background Colour beneath it. Multiply/Overlay tint the pattern with the colour.', 'ds-toolkit' ) ),
				),
			),
			'banner_scrim' => array(
				'title'       => __( 'Image Scrim', 'ds-toolkit' ),
				'description' => __( 'A designed darkening over the banner photo so the heading stays readable (Page Banner with an image).', 'ds-toolkit' ),
				'fields'      => array(
					'scrim_preset' => array(
						'type'    => 'select',
						'label'   => __( 'Scrim', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array(
							'none'     => __( 'None', 'ds-toolkit' ),
							'bottom'   => __( 'Bottom fade', 'ds-toolkit' ),
							'top'      => __( 'Top fade', 'ds-toolkit' ),
							'vignette' => __( 'Vignette (edges)', 'ds-toolkit' ),
							'full'     => __( 'Full (even darken)', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'bottom'   => array( 'fields' => array( 'scrim_color', 'scrim_strength' ) ),
							'top'      => array( 'fields' => array( 'scrim_color', 'scrim_strength' ) ),
							'vignette' => array( 'fields' => array( 'scrim_color', 'scrim_strength' ) ),
							'full'     => array( 'fields' => array( 'scrim_color', 'scrim_strength' ) ),
						),
					),
					'scrim_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Scrim Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'scrim_strength' => array( 'type' => 'unit', 'label' => __( 'Strength', 'ds-toolkit' ), 'default' => '55', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
				),
			),
			'overlay' => array(
				'title'  => __( 'Overlay', 'ds-toolkit' ),
				'fields' => array(
					'overlay_style' => array(
						'type'    => 'select',
						'label'   => __( 'Overlay', 'ds-toolkit' ),
						'default' => 'gradient',
						'options' => array(
							'none'     => __( 'None', 'ds-toolkit' ),
							'solid'    => __( 'Solid Colour', 'ds-toolkit' ),
							'gradient' => __( 'Gradient', 'ds-toolkit' ),
							'multiple' => __( 'Multiple Backgrounds', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'solid'    => array( 'fields' => array( 'overlay_color', 'overlay_opacity' ) ),
							'gradient' => array( 'fields' => array( 'grad_color1', 'grad_opacity1', 'grad_color2', 'grad_opacity2', 'grad_angle' ) ),
							'multiple' => array( 'fields' => array( 'overlay_bg' ) ),
						),
					),
					'overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'overlay_opacity' => array( 'type' => 'unit', 'label' => __( 'Opacity', 'ds-toolkit' ), 'default' => '70', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'grad_color1'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Gradient Start', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'grad_opacity1' => array( 'type' => 'unit', 'label' => __( 'Start Opacity', 'ds-toolkit' ), 'default' => '92', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'grad_color2'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Gradient End', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true ),
					'grad_opacity2' => array( 'type' => 'unit', 'label' => __( 'End Opacity', 'ds-toolkit' ), 'default' => '35', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'grad_angle'    => array( 'type' => 'unit', 'label' => __( 'Gradient Angle', 'ds-toolkit' ), 'default' => '105', 'description' => 'deg', 'slider' => array( 'min' => 0, 'max' => 360, 'step' => 1 ) ),
					// Multiple Backgrounds — Beaver Builder's native layered "background" control.
					// Stack colours / gradients / images; BB's auto-css engine composites them into
					// one `background:` shorthand (persisted under ->css) and emits it onto
					// .ds-hero-overlay automatically. The preview `enabled` guard scopes that emission
					// to overlay_style = 'multiple', so switching styles never leaves a stray paint.
					'overlay_bg' => array(
						'type'       => 'background',
						'label'      => __( 'Background Layers', 'ds-toolkit' ),
						'responsive' => array(
							'default' => array(
								'default' => array(
									array(
										'id'    => 1,
										'type'  => 'color',
										'state' => array( 'color' => 'rgba(10, 14, 21, 0.45)' ),
									),
								),
							),
						),
						'preview'    => array(
							'type'      => 'css',
							'auto'      => true,
							'selector'  => '.ds-hero-overlay',
							'property'  => 'background',
							'sub_value' => array( 'setting_name' => 'css' ),
							'enabled'   => array( 'overlay_style' => 'multiple' ),
						),
						'help'       => __( 'Stack multiple background layers (colours, gradients, images) like a Beaver Builder row. They composite top to bottom into the hero overlay.', 'ds-toolkit' ),
					),
				),
			),
			'layout' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'min_height'    => array( 'type' => 'unit', 'label' => __( 'Min Height', 'ds-toolkit' ), 'default' => '92', 'description' => 'vh', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'content_width' => array( 'type' => 'unit', 'label' => __( 'Content Width', 'ds-toolkit' ), 'default' => '760', 'description' => 'px', 'slider' => array( 'min' => 300, 'max' => 1280, 'step' => 10 ), 'help' => __( 'Max width of the text column (eyebrow, headline, subtext, buttons, stats).', 'ds-toolkit' ) ),
					'align'         => array(
						'type'       => 'select',
						'label'      => __( 'Alignment', 'ds-toolkit' ),
						'default'    => 'left',
						'responsive' => true,
						'options'    => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ) ),
					),
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'container_width'   => array(
						'type'    => 'select',
						'label'   => __( 'Container Width', 'ds-toolkit' ),
						'default' => 'boxed',
						'options' => array(
							'boxed'  => __( 'Boxed (max 1280px)', 'ds-toolkit' ),
							'full'   => __( 'Full width (fill container)', 'ds-toolkit' ),
							'custom' => __( 'Custom max-width', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'container_max_width' ) ) ),
						'help'    => __( 'Full width lets this fill a full-width row/column; use the row/column padding for side spacing.', 'ds-toolkit' ),
					),
					'container_max_width' => array( 'type' => 'unit', 'label' => __( 'Container Max Width', 'ds-toolkit' ), 'default' => '1280', 'description' => 'px', 'slider' => array( 'min' => 480, 'max' => 1920, 'step' => 10 ) ),
					'padding' => array(
						'type'       => 'dimension',
						'label'      => __( 'Padding', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Inner spacing around the content. 0 = compact / flush.', 'ds-toolkit' ),
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
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'eyebrow_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'title_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Headline', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'accent_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Accent Word', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'help' => __( 'Colour of {a}…{/a} text and the primary button.', 'ds-toolkit' ) ),
					'outline_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Outline Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Stroke colour for {outline}…{/outline} text in this module. Blank = the Theme Setting default.', 'ds-toolkit' ) ),
					'outline_width' => array( 'type' => 'unit', 'label' => __( 'Outline Text Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Blank = the Theme Setting default.', 'ds-toolkit' ), 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
					'sub_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Subtext', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'stat_num_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Stat Number', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true ),
					'stat_label_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Stat Label', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
				),
			),
			'typography' => array(
				'title'  => __( 'Typography', 'ds-toolkit' ),
				'fields' => array(
					'title_typography' => array( 'type' => 'typography', 'label' => __( 'Headline', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero-title' ) ),
					'sub_typography'   => array( 'type' => 'typography', 'label' => __( 'Subtext', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero-sub' ) ),
					'eyebrow_typography' => array( 'type' => 'typography', 'label' => __( 'Eyebrow', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-hero-eyebrow' ) ),
				),
			),
		),
	),
) );
