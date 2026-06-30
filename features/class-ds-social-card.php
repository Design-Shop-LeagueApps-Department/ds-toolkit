<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Social Sharing Card (blueprint 6+).
 *
 * One image, set in Theme Setting -> General -> Social Sharing, that acts as:
 *   1. the site-wide DEFAULT social card when a page/post has no image of its
 *      own — synced into Yoast's native default OG image so Yoast handles the
 *      whole og:/twitter: pipeline (dimensions, fallbacks). If Yoast is absent,
 *      this feature outputs the og:image / twitter:image tags itself.
 *   2. a Beaver Builder dynamic field ("Site -> Social Sharing Card") selectable
 *      in any module's dynamic/connection picker.
 *
 * Source of truth = the options ds_social_card_id / ds_social_card_url, written
 * by the Theme Setting save. On first run it seeds the bundled default image
 * (assets/img/social-card-default.jpg) into the media library.
 */
class DS_Social_Card {

	const OPT_ID  = 'ds_social_card_id';
	const OPT_URL = 'ds_social_card_url';
	const SEED_FLAG = 'ds_social_card_seeded';

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		// Seed the bundled default once (admin only — never sideload on a front-end hit).
		add_action( 'admin_init', array( __CLASS__, 'maybe_seed_default' ) );

		// Beaver Builder dynamic field.
		add_action( 'fl_page_data_add_properties', array( __CLASS__, 'register_page_data' ) );

		// If Yoast isn't running, emit the default card tags ourselves.
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			add_action( 'wp_head', array( __CLASS__, 'output_fallback_meta' ), 5 );
		}

		// Any empty image in a Beaver Builder module falls back to the social card.
		// The core Photo module (and anything using BB's no-image filter) shows the
		// card instead of a blank pixel when no image is set.
		add_filter( 'fl_builder_photo_noimage', array( __CLASS__, 'photo_noimage' ) );
	}

	/** [id,url] of the configured card (empty strings when unset). */
	public static function get_card() {
		$id  = (int) get_option( self::OPT_ID, 0 );
		$url = (string) get_option( self::OPT_URL, '' );
		if ( $id && '' === $url ) {
			$url = (string) wp_get_attachment_url( $id );
		}
		return array( 'id' => $id, 'url' => $url );
	}

	/** Convenience: the social card image URL (empty string when unset). */
	public static function url() {
		$card = self::get_card();
		return ! empty( $card['url'] ) ? (string) $card['url'] : '';
	}

	/** Fallback the BB Photo module (and others) use when no image is set. */
	public static function photo_noimage( $url ) {
		$card = self::get_card();
		return ! empty( $card['url'] ) ? (string) $card['url'] : $url;
	}

	/**
	 * Persist the card and mirror it into Yoast's default OG image so Yoast uses
	 * it natively as the site-wide sharing default. Called by the Theme Setting
	 * save and by the seeder.
	 */
	public static function set_card( $id, $url ) {
		$id  = (int) $id;
		$url = esc_url_raw( (string) $url );
		update_option( self::OPT_ID, $id );
		update_option( self::OPT_URL, $url );
		self::sync_yoast( $id, $url );
	}

	/** Push the card into Yoast's default OG image option (no-op without Yoast). */
	public static function sync_yoast( $id, $url ) {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			return;
		}
		$social = get_option( 'wpseo_social', array() );
		if ( ! is_array( $social ) ) {
			$social = array();
		}
		$social['og_default_image']    = esc_url_raw( (string) $url );
		$social['og_default_image_id'] = (int) $id;
		update_option( 'wpseo_social', $social );

		// The home page is the primary "share the site" target. On a static front
		// page Yoast derives a primary image from the first content image (e.g. the
		// header logo), which outranks the default OG image. Yoast's official
		// front-page social image (wpseo_titles, not the legacy wpseo_social key)
		// takes precedence over that, so point it at the card too.
		$titles = get_option( 'wpseo_titles', array() );
		if ( ! is_array( $titles ) ) {
			$titles = array();
		}
		$titles['open_graph_frontpage_image']    = esc_url_raw( (string) $url );
		$titles['open_graph_frontpage_image_id'] = (int) $id;
		update_option( 'wpseo_titles', $titles );

		// A STATIC front page ignores the "Front page" social setting above and is
		// treated as a normal page, where Yoast auto-derives an image from the first
		// content image (the header logo) ahead of the default. So set the card as
		// the front page's own Open Graph / Twitter image — the highest-priority
		// source — to guarantee "sharing the site" (the home page) shows the card.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$front = (int) get_option( 'page_on_front' );
			if ( $front ) {
				if ( $id && $url ) {
					update_post_meta( $front, '_yoast_wpseo_opengraph-image', esc_url_raw( $url ) );
					update_post_meta( $front, '_yoast_wpseo_opengraph-image-id', $id );
					update_post_meta( $front, '_yoast_wpseo_twitter-image', esc_url_raw( $url ) );
					update_post_meta( $front, '_yoast_wpseo_twitter-image-id', $id );
				} else {
					delete_post_meta( $front, '_yoast_wpseo_opengraph-image' );
					delete_post_meta( $front, '_yoast_wpseo_opengraph-image-id' );
					delete_post_meta( $front, '_yoast_wpseo_twitter-image' );
					delete_post_meta( $front, '_yoast_wpseo_twitter-image-id' );
				}
			}
		}

		// Invalidate Yoast's per-request option cache if the API is available.
		if ( method_exists( 'WPSEO_Options', 'clear_cache' ) ) {
			WPSEO_Options::clear_cache();
		}
	}

	/** One-shot: sideload the bundled default image and set it as the card. */
	public static function maybe_seed_default() {
		if ( get_option( self::SEED_FLAG ) ) {
			return;
		}
		// Already configured by hand — just mark seeded.
		if ( (int) get_option( self::OPT_ID, 0 ) ) {
			update_option( self::SEED_FLAG, 1 );
			return;
		}
		$file = DS_TOOLKIT_PATH . 'assets/img/social-card-default.jpg';
		if ( ! file_exists( $file ) ) {
			return;
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		// Copy to a temp path so media_handle_sideload can move it.
		$tmp = wp_tempnam( 'social-card-default.jpg' );
		if ( ! $tmp || ! @copy( $file, $tmp ) ) {
			return;
		}
		$id = media_handle_sideload( array(
			'name'     => 'social-card-default.jpg',
			'tmp_name' => $tmp,
		), 0, __( 'Default Social Sharing Card', 'ds-toolkit' ) );
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		if ( is_wp_error( $id ) ) {
			return;
		}
		self::set_card( $id, wp_get_attachment_url( $id ) );
		update_option( self::SEED_FLAG, 1 );
	}

	/** Fallback og:/twitter: image tags when Yoast is not active. */
	public static function output_fallback_meta() {
		$card = self::get_card();
		if ( empty( $card['url'] ) ) {
			return;
		}
		$url = esc_url( $card['url'] );
		echo "\n<!-- DS Toolkit social card -->\n";
		echo '<meta property="og:image" content="' . $url . '" />' . "\n";
		echo '<meta name="twitter:image" content="' . $url . '" />' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		if ( $card['id'] ) {
			$meta = wp_get_attachment_metadata( $card['id'] );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				echo '<meta property="og:image:width" content="' . (int) $meta['width'] . '" />' . "\n";
				echo '<meta property="og:image:height" content="' . (int) $meta['height'] . '" />' . "\n";
			}
		}
	}

	/** Register the "Social Sharing Card" Beaver Builder site dynamic field. */
	public static function register_page_data() {
		if ( ! class_exists( 'FLPageData' ) ) {
			return;
		}
		FLPageData::add_site_property( 'ds_social_card', array(
			'label'  => __( 'Social Sharing Card', 'ds-toolkit' ),
			'group'  => 'site',
			'type'   => 'photo',
			'getter' => array( __CLASS__, 'page_data_getter' ),
		) );
		FLPageData::add_site_property_settings_fields( 'ds_social_card', array(
			'size' => array(
				'type'    => 'photo-sizes',
				'label'   => __( 'Size', 'ds-toolkit' ),
				'default' => 'full',
			),
		) );
	}

	/** BB photo getter — returns [id,url] honouring the chosen image size. */
	public static function page_data_getter( $settings = null ) {
		$card = self::get_card();
		if ( empty( $card['id'] ) ) {
			return array( 'id' => '', 'url' => $card['url'] );
		}
		$size = ( $settings && isset( $settings->size ) && $settings->size ) ? $settings->size : 'full';
		$url  = wp_get_attachment_image_url( $card['id'], $size );
		return array(
			'id'  => $card['id'],
			'url' => $url ? $url : $card['url'],
		);
	}
}
