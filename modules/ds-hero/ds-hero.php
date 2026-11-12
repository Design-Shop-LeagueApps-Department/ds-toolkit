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

	/** Elements the eyebrow and headline may render as (SEO Tags section). */
	public static function seo_tags() {
		return array(
			'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
			'p' => __( 'Paragraph', 'ds-toolkit' ), 'div' => 'DIV', 'span' => 'Span',
		);
	}

	/** A saved SEO tag, or $default when it is not one of seo_tags(). */
	public static function seo_tag( $v, $default ) {
		$v = strtolower( (string) $v );
		return isset( self::seo_tags()[ $v ] ) ? $v : $default;
	}

	/**
	 * What Global Styles gives an H1: the "All headings" typography (h_typography) with the
	 * H1 typography over it, per breakpoint, plus the H1 colour. Beaver prints these as
	 * `.fl-builder-content h1`, which outranks the headline's own class rules, so a headline
	 * rendered as another tag gets them re-applied by frontend.css.php to look the same.
	 * Returns false when there are no Global Styles.
	 */
	public static function h1_global_look() {
		static $look = null;
		if ( null !== $look ) { return $look; }
		$look = false;
		if ( ! class_exists( 'FLBuilderGlobalStyles' ) ) { return $look; }
		$gs = FLBuilderGlobalStyles::get_settings( false );
		if ( ! is_object( $gs ) && ! is_array( $gs ) ) { return $look; }
		$gs   = (object) $gs;
		$look = new stdClass();
		foreach ( array( '', '_large', '_medium', '_responsive' ) as $sfx ) {
			$look->{ 't' . $sfx } = self::merge_typo( $gs->{ 'h_typography' . $sfx } ?? array(), $gs->{ 'h1_typography' . $sfx } ?? array() );
		}
		$look->color = DS_Module_UI::color( ( $gs->h1_color ?? '' ) ?: ( $gs->h_color ?? '' ) );
		return $look;
	}

	/** $over's set values on top of $base (a blank length, '' or "Default" counts as unset). */
	private static function merge_typo( $base, $over ) {
		$m = (array) $base;
		foreach ( (array) $over as $k => $v ) {
			if ( is_array( $v ) || is_object( $v ) ) {
				$a = (array) $v;
				if ( array_key_exists( 'length', $a ) ? '' === (string) $a['length'] : '' === implode( '', array_map( 'strval', array_filter( $a, 'is_scalar' ) ) ) ) { continue; }
			} elseif ( '' === (string) $v || 'default' === strtolower( (string) $v ) ) {
				continue;
			}
			$m[ $k ] = $v;
		}
		return $m;
	}

	/**
	 * Heading markup: escape, {a}..{/a} -> accent span, {outline}..{/outline} -> outlined span,
	 * {g}..{/g} -> gradient span (GH #200), newlines -> <br>.
	 *
	 * Markers are swapped AFTER the text has been through inline(), so the only markup they
	 * add is a fixed span; the text between them is already sanitised. {g} is replaced as a
	 * matched pair only: an unmatched {g} or {/g} stays literal text rather than leaving an
	 * unclosed tag behind. Markers are not nestable; wrap separate ranges.
	 */
	private function heading_html( $raw ) {
		$h = DS_Module_UI::inline( (string) $raw ); // safe inline HTML (span/strong/em...) allowed
		$h = str_replace( array( '{a}', '{/a}' ), array( '<span class="ds-hero-accent">', '</span>' ), $h );
		$h = str_replace( array( '{outline}', '{/outline}' ), array( '<span class="ds-outline-text">', '</span>' ), $h );
		$h = preg_replace( '/\{g\}(.*?)\{\/g\}/s', '<span class="ds-hero-gradient">$1</span>', $h );
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
	/** Blend modes offered on the Style 3 background layers. */
	public static function blend_modes() {
		return array(
			'normal'     => __( 'Normal', 'ds-toolkit' ),
			'multiply'   => __( 'Multiply', 'ds-toolkit' ),
			'overlay'    => __( 'Overlay', 'ds-toolkit' ),
			'screen'     => __( 'Screen', 'ds-toolkit' ),
			'darken'     => __( 'Darken', 'ds-toolkit' ),
			'lighten'    => __( 'Lighten', 'ds-toolkit' ),
			'soft-light' => __( 'Soft Light', 'ds-toolkit' ),
		);
	}

	public static function styles() {
		return array(
			'style1' => __( 'Style 1: Classic', 'ds-toolkit' ),
			'style2' => __( 'Style 2: Page Banner (auto)', 'ds-toolkit' ),
			'style3' => __( 'Style 3: Slider', 'ds-toolkit' ),
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

		// Eyebrow image (GH #192). Emitted only when a photo resolves, so an unset
		// field leaves no empty markup or spacing. The block wrapper lets the hero's
		// responsive text-align place it; the <img> inside is inline-block so it never
		// stretches to the hero width. Above the fold, so it loads eagerly. The
		// library alt text rides along when the attachment has one.
		$eb_img = ! empty( $s->eyebrow_image ) ? $this->photo_url( $s->eyebrow_image, 'large' ) : '';
		if ( '' !== $eb_img ) {
			$eb_alt = is_numeric( $s->eyebrow_image ) ? (string) get_post_meta( (int) $s->eyebrow_image, '_wp_attachment_image_alt', true ) : '';
			echo '<div class="ds-hero-eyebrow-img"><img src="' . esc_url( $eb_img ) . '" alt="' . esc_attr( $eb_alt ) . '" loading="eager" decoding="async" /></div>';
		}
		// Tags from Content > SEO Tags; the look is the same whatever they are (see frontend.css).
		$eb_tag = self::seo_tag( $s->eyebrow_tag ?? 'span', 'span' );
		$ti_tag = self::seo_tag( $s->title_tag ?? 'h1', 'h1' );
		if ( ! empty( $s->eyebrow ) ) {
			echo '<' . $eb_tag . ' class="ds-hero-eyebrow">' . esc_html( $s->eyebrow ) . '</' . $eb_tag . '>';
		}
		if ( ! empty( $s->heading ) ) {
			echo '<' . $ti_tag . ' class="ds-hero-title">' . $this->heading_html( $s->heading ) . '</' . $ti_tag . '>';
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
		$own_media = '' === (string) $this->photo_url( $s->banner_image ?? '' ) && '' === trim( (string) ( $s->banner_video ?? '' ) );
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

		// Vertical title placement (GH #176). Center is the default and adds no
		// class, so an existing banner's markup does not change by a byte.
		$valign = (string) ( $s->banner_valign ?? 'center' );
		if ( ! in_array( $valign, array( 'top', 'center', 'bottom' ), true ) ) { $valign = 'center'; }
		$vmod = ( 'center' === $valign ) ? '' : ' ds-banner--v-' . $valign;

		$mods = 'ds-hero ds-hero--style2 ds-hero--banner ds-banner--' . $align . ( $has_bg ? ' ds-banner--has-bg' : ' ds-banner--no-bg' ) . ( $parallax ? ' ds-banner--parallax' : '' ) . $vmod;
		echo '<section class="' . esc_attr( $mods ) . '"' . ( $parallax ? ' data-parallax="1"' : '' ) . '>';
		// The page's focal point for its banner photo or video (the Page Banner Image panel in the builder, meta
		// _ds_banner_focal = "x y" in percent): the spot that stays in view however the banner crops it. Centre = none.
		$focal = '';
		if ( $own_media && ! $is_arch && $pid && preg_match( '/^(\d{1,3}) (\d{1,3})$/', (string) get_post_meta( $pid, '_ds_banner_focal', true ), $fm ) ) {
			$fx = min( 100, (int) $fm[1] ); $fy = min( 100, (int) $fm[2] );
			if ( 50 !== $fx || 50 !== $fy ) { $focal = $fx . '% ' . $fy . '%'; }
		}
		if ( '' !== $video ) {
			echo '<div class="ds-hero-bg"><video class="ds-hero-video" autoplay muted loop playsinline src="' . esc_url( $video ) . '"' . ( $focal ? ' style="object-position:' . esc_attr( $focal ) . '"' : '' ) . '></video></div>';
		} elseif ( '' !== $img ) {
			echo '<div class="ds-hero-bg" style="background-image:url(' . esc_url( $img ) . ')' . ( $focal ? ';background-position:' . esc_attr( $focal ) : '' ) . '"></div>';
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
			$cstyle = (string) ( $s->breadcrumbs_style ?? 'plain' );
			$pill   = in_array( $cstyle, array( 'pill', 'tab' ), true ) ? ' ds-banner-crumbs--' . $cstyle : '';
			$crumbs = yoast_breadcrumb( '<nav class="ds-banner-crumbs' . $pill . '" aria-label="' . esc_attr__( 'Breadcrumb', 'ds-toolkit' ) . '">', '</nav>', false );
		}
		$crumb_pos = in_array( $s->breadcrumbs_position ?? 'top', array( 'top', 'below', 'edge' ), true ) ? ( $s->breadcrumbs_position ?? 'top' ) : 'top';

		echo '<div class="ds-hero-wrap"><div class="ds-hero-inner">';
		if ( '' !== $crumbs && 'top' === $crumb_pos ) { echo $crumbs; }
		$eb_tag = self::seo_tag( $s->eyebrow_tag ?? 'span', 'span' );
		$ti_tag = self::seo_tag( $s->title_tag ?? 'h1', 'h1' );
		if ( '' !== $eyebrow ) { echo '<' . $eb_tag . ' class="ds-banner-eyebrow">' . esc_html( $eyebrow ) . '</' . $eb_tag . '>'; }
		if ( '' !== $heading ) { echo '<' . $ti_tag . ' class="ds-hero-title">' . $this->heading_html( $heading ) . '</' . $ti_tag . '>'; }
		if ( '' !== $sub )     { echo '<div class="ds-hero-sub">' . wpautop( wp_kses_post( $sub ) ) . '</div>'; }
		if ( '' !== $crumbs && 'below' === $crumb_pos ) { echo $crumbs; }
		echo '</div></div></section>';
		// On the bottom edge: drawn after the banner, so a shaped or clipped banner edge never cuts it; lifted back over
		// the edge by Breadcrumbs Lift (Style > Colours), and it takes no space of its own.
		if ( '' !== $crumbs && 'edge' === $crumb_pos ) {
			// Plain and Pill sit wholly on the photo (anchored by their bottom), so they take the banner's light-on-photo colour.
			echo '<div class="ds-banner-edge' . ( $has_bg ? ' ds-banner-edge--on-bg' : '' ) . '">' . $crumbs . '</div>';
		}
	}
	/* ------------------------------------------- Style 3 — Peek Slider (GH #160) */

	/** The nine focal-point choices, as an object-position value. */
	private static function focal_pos( $key ) {
		$ok = array(
			'left top', 'center top', 'right top',
			'left center', 'center center', 'right center',
			'left bottom', 'center bottom', 'right bottom',
		);
		$key = trim( (string) $key );
		return in_array( $key, $ok, true ) ? $key : 'center center';
	}

	/**
	 * Normalised Style 3 slides: [{image, mobile, alt, focal, head, tag, cta, link, target}].
	 * A slide with no image and no heading is dropped, so the track, the dots and the
	 * "n of N" labels always agree on how many slides there are.
	 */
	private function peek_slides() {
		$out = array();
		$raw = $this->settings->peek_slides ?? array();
		if ( ! is_array( $raw ) ) { return $out; }

		foreach ( $raw as $slide ) {
			$slide = (object) $slide;
			$img   = ! empty( $slide->image ) ? $this->photo_url( $slide->image, 'full' ) : '';
			$mob   = ! empty( $slide->image_mobile ) ? $this->photo_url( $slide->image_mobile, 'full' ) : '';
			$head  = trim( (string) ( $slide->heading ?? '' ) );
			if ( '' === $img && '' === $mob && '' === $head ) { continue; }

			$tag = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $slide->heading_tag ?? 'h2' ) ) );
			if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ), true ) ) { $tag = 'h2'; }

			$out[] = array(
				'image'  => '' !== $img ? $img : $mob,
				'mobile' => '' !== $mob ? $mob : $img,
				'alt'    => trim( (string) ( $slide->img_alt ?? '' ) ),
				'focal'  => self::focal_pos( $slide->focal ?? 'center center' ),
				'head'   => $head,
				'tag'    => $tag,
				'cta'    => trim( (string) ( $slide->cta_text ?? '' ) ),
				'link'   => trim( (string) ( $slide->cta_link ?? '' ) ),
				'target' => ( ( $slide->cta_target ?? '_self' ) === '_blank' ) ? '_blank' : '_self',
			);
		}
		return $out;
	}

	/** One Style 3 slide. */
	private function peek_slide( $sl, $i, $total, $sm ) {
		printf(
			'<div class="ds-peek-slide%s" role="group" aria-roledescription="%s" aria-label="%s">',
			0 === $i ? ' is-active' : '',
			esc_attr__( 'slide', 'ds-toolkit' ),
			esc_attr( sprintf( __( '%1$d of %2$d', 'ds-toolkit' ), $i + 1, $total ) )
		);

		echo '<div class="ds-peek-bg">';
		if ( '' !== $sl['image'] ) {
			echo '<picture class="ds-peek-pic">';
			if ( $sl['mobile'] !== $sl['image'] ) {
				printf( '<source media="(max-width:%dpx)" srcset="%s">', (int) $sm, esc_url( $sl['mobile'] ) );
			}
			printf(
				'<img class="ds-peek-img" src="%s" alt="%s" style="object-position:%s" loading="%s" decoding="async" draggable="false">',
				esc_url( $sl['image'] ),
				esc_attr( $sl['alt'] ),
				esc_attr( $sl['focal'] ),
				0 === $i ? 'eager' : 'lazy'
			);
			echo '</picture>';
		}
		echo '<span class="ds-peek-pattern" aria-hidden="true"></span><span class="ds-peek-overlay" aria-hidden="true"></span></div>';

		if ( '' !== $sl['head'] || '' !== $sl['cta'] ) {
			echo '<div class="ds-peek-content">';
			if ( '' !== $sl['head'] ) {
				printf( '<%1$s class="ds-peek-title">%2$s</%1$s>', $sl['tag'], DS_Module_UI::inline( $sl['head'] ) );
			}
			if ( '' !== $sl['cta'] ) {
				printf(
					'<a class="ds-peek-cta" href="%s"%s>%s<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>',
					esc_url( '' !== $sl['link'] ? $sl['link'] : '#' ),
					'_blank' === $sl['target'] ? ' target="_blank" rel="noopener noreferrer"' : '',
					esc_html( $sl['cta'] )
				);
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Style 3 — Peek Slider. A horizontal track whose active slide is centred and
	 * narrower than the viewport, so the neighbouring slides peek in at the edges.
	 * Behaviour lives in js/frontend.js; every dimension is a custom property the
	 * dynamic CSS re-declares per breakpoint.
	 */
	public function render_style3() {
		$s      = $this->settings;
		$slides = $this->peek_slides();

		if ( empty( $slides ) ) {
			if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
				echo '<section class="ds-hero ds-hero--style3"><div class="ds-peek-empty">'
					. esc_html__( 'Slider: add your slides under Content → Slides.', 'ds-toolkit' )
					. '</div></section>';
			}
			return;
		}

		$total    = count( $slides );
		$multi    = $total > 1;
		$loop     = $multi && ( $s->peek_loop ?? 'yes' ) === 'yes';
		$autoplay = $multi && ( $s->peek_autoplay ?? 'yes' ) === 'yes';
		$drag     = $multi && ( $s->peek_drag ?? 'yes' ) === 'yes';
		$arrows   = $multi && ( $s->peek_arrows ?? 'yes' ) === 'yes';
		$dots     = $multi && ( $s->peek_dots ?? 'yes' ) === 'yes';
		$playbtn  = $autoplay && ( $s->peek_playpause ?? 'yes' ) === 'yes';

		$vpos  = (string) ( $s->peek_content_v ?? 'bottom' );
		if ( ! in_array( $vpos, array( 'top', 'center', 'bottom' ), true ) ) { $vpos = 'bottom'; }
		$align = (string) ( $s->peek_content_align ?? 'split' );
		if ( ! in_array( $align, array( 'split', 'left', 'center', 'right' ), true ) ) { $align = 'split'; }

		$ease = (string) ( $s->peek_ease ?? 'settle' );
		if ( ! in_array( $ease, array( 'settle', 'snap', 'smooth' ), true ) ) { $ease = 'settle'; }

		$label = trim( (string) ( $s->peek_label ?? '' ) );
		if ( '' === $label ) { $label = __( 'Featured highlights', 'ds-toolkit' ); }

		list( , $sm ) = DS_Module_UI::breakpoints();

		printf(
			'<section class="ds-hero ds-hero--style3 ds-peek--v-%s ds-peek--a-%s">',
			esc_attr( $vpos ),
			esc_attr( $align )
		);
		printf(
			'<div class="ds-peek%s" role="region" aria-roledescription="%s" aria-label="%s">',
			$drag ? ' ds-peek--drag' : '',
			esc_attr__( 'carousel', 'ds-toolkit' ),
			esc_attr( $label )
		);

		printf(
			'<div class="ds-peek-viewport"><div class="ds-peek-track" data-loop="%s" data-autoplay="%s" data-interval="%s" data-pause="%s" data-resume="%s" data-drag="%s" data-speed="%d" data-ease="%s">',
			$loop ? 'yes' : 'no',
			$autoplay ? 'yes' : 'no',
			esc_attr( (string) max( 2, (int) DS_Module_UI::u( $s->peek_interval ?? '', 7 ) ) ),
			( $s->peek_pause_hover ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			( $s->peek_resume ?? 'yes' ) === 'yes' ? 'yes' : 'no',
			$drag ? 'yes' : 'no',
			max( 100, (int) DS_Module_UI::u( $s->peek_speed ?? '', 525 ) ),
			esc_attr( $ease )
		);
		foreach ( $slides as $i => $sl ) { $this->peek_slide( $sl, $i, $total, $sm ); }
		echo '</div></div>';

		if ( $arrows ) {
			echo '<button type="button" class="ds-peek-nav ds-peek-nav--prev" aria-label="' . esc_attr__( 'Previous slide', 'ds-toolkit' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>';
			echo '<button type="button" class="ds-peek-nav ds-peek-nav--next" aria-label="' . esc_attr__( 'Next slide', 'ds-toolkit' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>';
		}

		if ( $dots || $playbtn ) {
			echo '<div class="ds-peek-dots">';
			if ( $dots ) {
				for ( $d = 0; $d < $total; $d++ ) {
					printf(
						'<button type="button" class="ds-peek-dot%s" aria-current="%s" aria-label="%s"></button>',
						0 === $d ? ' is-active' : '',
						0 === $d ? 'true' : 'false',
						esc_attr( sprintf( __( 'Go to slide %d', 'ds-toolkit' ), $d + 1 ) )
					);
				}
			}
			if ( $playbtn ) {
				printf(
					'<button type="button" class="ds-peek-play" data-play="%s" data-pause="%s" aria-label="%s"><svg class="ds-peek-ico-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14"/><rect x="14" y="5" width="4" height="14"/></svg><svg class="ds-peek-ico-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg></button>',
					esc_attr__( 'Play slideshow', 'ds-toolkit' ),
					esc_attr__( 'Pause slideshow', 'ds-toolkit' ),
					esc_attr__( 'Pause slideshow', 'ds-toolkit' )
				);
			}
			echo '</div>';
		}

		// Announces the slide number on a manual move only — autoplay stays silent.
		echo '<span class="ds-peek-live" aria-live="polite" aria-atomic="true"></span>';
		echo '</div></section>';
	}

}

// The sub-forms, the settings form and the module registration.
require __DIR__ . '/includes/settings-form.php';
