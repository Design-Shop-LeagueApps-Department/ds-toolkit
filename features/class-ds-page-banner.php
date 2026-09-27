<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Keeps a page's Banner "Background Photo" (ACF page_hero_banner_image) and its
 * Featured Image in sync, so a partner only ever sets the image in one place.
 *
 * Rule on save (pages only): whichever of the two the partner CHANGED wins and is
 * mirrored to the other, including a removal.
 *   - Background Photo changed in this save  -> Featured Image follows.
 *   - Else Featured Image changed since the last save -> Background Photo follows.
 *   - Else, one is empty -> fill it from the other (Background Photo first).
 * After saving, the two always match. Blueprint 6+.
 *
 * "Featured Image changed" is recorded when it happens (TOUCHED_META), because the
 * classic editor saves the featured image over AJAX the moment it is picked or
 * removed, one request before the Update click that fires acf/save_post; by then
 * the old value is gone. Before 1.9.159 the Background Photo always won, so
 * changing or removing the Featured Image was reverted on every save.
 *
 * Builder button (Alipio 2026-09-27: "add featured image page banner upload option in the beaver
 * topbar ... so that no more editing a page to add featured image"): an image icon in the Beaver
 * Builder top bar sets or removes the banner image without leaving the builder. On pages it writes
 * BOTH the Background Photo and the Featured Image (they must match, see above); on other post
 * types whose banner uses the Featured Image (Theme Setting -> Page Banner) it writes the Featured
 * Image. It saves straight away, like the Featured Image box in the editor, and repaints the banner
 * in the builder preview (the banner lives in the Themer layout, which the builder does not re-render).
 */
class DS_Page_Banner {

	const IMG_FIELD    = 'field_dst_banner_image'; // ACF key for page_hero_banner_image
	const IMG_NAME     = 'page_hero_banner_image';
	const TOUCHED_META = '_ds_banner_featured_touched';
	const VIDEO_NAME   = 'video_page_hero_banner';
	const AJAX         = 'ds_builder_banner_image';
	const FOCAL_META   = '_ds_banner_focal'; // "x y" in percent: the spot the banner keeps in view (Hero module reads it)

	private $settings;
	private $syncing = false;
	private $bg_before = array(); // post_id => Background Photo id before ACF saved

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'added_post_meta', array( $this, 'featured_touched' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'featured_touched' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'featured_touched' ), 10, 3 );
		// Priority 5: before ACF writes the fields (priority 10), to see the old value.
		add_action( 'acf/save_post', array( $this, 'remember' ), 5 );
		// Priority 20: after ACF has written the fields.
		add_action( 'acf/save_post', array( $this, 'sync' ), 20 );

		add_filter( 'fl_builder_ui_bar_buttons', array( $this, 'bar_button' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'builder_assets' ), 20 );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax_set' ) );
	}

	/* ------------------------------------------------------------------ builder button */

	/** The post being edited in the builder, when its banner can take an image; 0 otherwise. */
	private function builder_post_id() {
		if ( ! class_exists( 'FLBuilderModel' ) || ! FLBuilderModel::is_builder_active() ) { return 0; }
		$id = (int) FLBuilderModel::get_post_id();
		return $this->can_take_image( $id ) ? $id : 0;
	}

	/** A post whose Page Banner shows an image: a page (not the home page) or a post type allowed in Theme Setting. */
	private function can_take_image( $id ) {
		if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) { return false; }
		$type = get_post_type( $id );
		if ( ! $type || in_array( $type, array( 'fl-theme-layout', 'fl-builder-template', 'attachment' ), true ) ) { return false; }
		if ( 'page' === $type ) { return (int) get_option( 'page_on_front' ) !== $id; } // the home page has a hero, not a banner
		if ( ! post_type_supports( $type, 'thumbnail' ) ) { return false; }
		return class_exists( 'DS_Theme_Setting' ) ? (bool) DS_Theme_Setting::banner_featured_allowed( $type ) : true;
	}

	/** What the banner shows now: [id, preview url, full url, has video, title hidden on photo]. */
	private function state( $id ) {
		$img = 0;
		if ( 'page' === get_post_type( $id ) ) { $img = (int) get_post_meta( $id, self::IMG_NAME, true ); }
		if ( ! $img ) { $img = (int) get_post_thumbnail_id( $id ); }
		$video = get_post_meta( $id, self::VIDEO_NAME, true );
		$fx = 50; $fy = 50;
		if ( preg_match( '/^(\d{1,3}) (\d{1,3})$/', (string) get_post_meta( $id, self::FOCAL_META, true ), $m ) ) { $fx = min( 100, (int) $m[1] ); $fy = min( 100, (int) $m[2] ); }
		return array(
			'focal'     => array( $fx, $fy ),
			'full'      => $img ? (string) wp_get_attachment_image_url( $img, 'large' ) : '',
			'id'        => $img,
			'thumb'     => $img ? (string) wp_get_attachment_image_url( $img, 'medium_large' ) : '',
			'url'       => $img ? (string) wp_get_attachment_image_url( $img, 'full' ) : '',
			'hasVideo'  => ! empty( $video ),
			'hideTitle' => class_exists( 'DS_Theme_Setting' ) && DS_Theme_Setting::banner_photo_title_hidden(),
		);
	}

	public function bar_button( $buttons ) {
		if ( ! $this->builder_post_id() ) { return $buttons; }
		$icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="9.5" r="1.8"/><path d="M21 15.5l-5-5L5 20"/></svg>';
		$button = array( 'ds-banner-image' => array(
			'label'   => $icon,
			'title'   => esc_attr__( 'Page Banner Image', 'ds-toolkit' ),
			'class'   => 'fl-builder-button-silent',
			'onclick' => 'window.dsBannerImage && window.dsBannerImage.toggle(this); return false;',
		) );
		// Between the cloud (Assistant) and the + (Alipio). The bar lays its buttons out right to left, so
		// "after the + in the array" is "left of the + on screen".
		$out = array();
		foreach ( $buttons as $slug => $b ) {
			$out[ $slug ] = $b;
			if ( 'content-panel' === $slug ) { $out += $button; }
		}
		return isset( $out['ds-banner-image'] ) ? $out : $buttons + $button;
	}

	public function builder_assets() {
		$id = $this->builder_post_id();
		if ( ! $id ) { return; }
		wp_enqueue_media();
		wp_enqueue_style( 'ds-builder-banner-image', DS_TOOLKIT_URL . 'assets/css/builder-banner-image.css', array(), DS_TOOLKIT_VERSION );
		wp_enqueue_script( 'ds-builder-banner-image', DS_TOOLKIT_URL . 'assets/js/builder-banner-image.js', array( 'jquery', 'media-editor' ), DS_TOOLKIT_VERSION, true );
		wp_localize_script( 'ds-builder-banner-image', 'dsBannerImageData', array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'action' => self::AJAX,
			'nonce'  => wp_create_nonce( self::AJAX . $id ),
			'postId' => $id,
			'state'  => $this->state( $id ),
			'i18n'   => array(
				'title'    => __( 'Page Banner Image', 'ds-toolkit' ),
				'choose'   => __( 'Choose image', 'ds-toolkit' ),
				'change'   => __( 'Change image', 'ds-toolkit' ),
				'remove'   => __( 'Remove', 'ds-toolkit' ),
				'use'      => __( 'Use as banner image', 'ds-toolkit' ),
				'none'     => __( 'No banner image. The banner uses the plain background from Theme Setting.', 'ds-toolkit' ),
				'live'     => __( 'Saves straight away and is also this page\'s Featured Image.', 'ds-toolkit' ),
				'video'    => __( 'This page has a banner video, which shows instead of the image.', 'ds-toolkit' ),
				'saving'   => __( 'Saving...', 'ds-toolkit' ),
				'saved'    => __( 'Banner image saved', 'ds-toolkit' ),
				'removed'  => __( 'Banner image removed', 'ds-toolkit' ),
				'failed'   => __( 'Could not save the banner image. Please try again.', 'ds-toolkit' ),
				'focal'    => __( 'Focus point', 'ds-toolkit' ),
				'focalHint'=> __( 'Click the part of the photo that must stay in view. The banner keeps it visible on every screen size.', 'ds-toolkit' ),
				'horiz'    => __( 'Horizontal', 'ds-toolkit' ),
				'vert'     => __( 'Vertical', 'ds-toolkit' ),
				'centre'   => __( 'Centre', 'ds-toolkit' ),
				'posSaved' => __( 'Position saved', 'ds-toolkit' ),
			),
		) );
	}

	public function ajax_set() {
		$id  = (int) ( $_POST['post_id'] ?? 0 );
		$img = (int) ( $_POST['attachment_id'] ?? 0 );
		if ( ! check_ajax_referer( self::AJAX . $id, 'nonce', false ) || ! $this->can_take_image( $id ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		// Focal point only (Alipio 2026-09-27: "position control x and y ... so partner can adjust the cut off image").
		if ( isset( $_POST['focal_x'], $_POST['focal_y'] ) ) {
			$fx = max( 0, min( 100, (int) $_POST['focal_x'] ) );
			$fy = max( 0, min( 100, (int) $_POST['focal_y'] ) );
			if ( 50 === $fx && 50 === $fy ) { delete_post_meta( $id, self::FOCAL_META ); } else { update_post_meta( $id, self::FOCAL_META, $fx . ' ' . $fy ); }
			if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) { WpeCommon::purge_varnish_cache( $id ); }
			wp_send_json_success( $this->state( $id ) );
		}
		if ( $img && ! wp_attachment_is_image( $img ) ) {
			wp_send_json_error( array( 'message' => 'not an image' ), 400 );
		}
		$this->syncing = true; // our own write: not a partner edit of the Featured Image
		$img ? set_post_thumbnail( $id, $img ) : delete_post_thumbnail( $id );
		if ( ! $img ) { delete_post_meta( $id, self::FOCAL_META ); } // no image, no point to keep
		if ( 'page' === get_post_type( $id ) ) {
			// The Background Photo beats the Featured Image in the banner; keep the two equal.
			if ( function_exists( 'update_field' ) ) { update_field( self::IMG_FIELD, $img ? $img : '', $id ); }
			else { update_post_meta( $id, self::IMG_NAME, $img ? $img : '' ); update_post_meta( $id, '_' . self::IMG_NAME, self::IMG_FIELD ); }
			delete_post_meta( $id, self::TOUCHED_META );
		}
		$this->syncing = false;
		clean_post_cache( $id );
		if ( class_exists( 'FLBuilderModel' ) ) { FLBuilderModel::delete_asset_cache( $id ); }
		// A Featured Image write is not a post save, so WP Engine would keep serving the old page.
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) { WpeCommon::purge_varnish_cache( $id ); }
		wp_send_json_success( $this->state( $id ) );
	}

	public function featured_touched( $meta_ids, $post_id, $meta_key ) {
		if ( $this->syncing || '_thumbnail_id' !== $meta_key ) { return; }
		if ( 'page' !== get_post_type( $post_id ) ) { return; }
		update_post_meta( $post_id, self::TOUCHED_META, 1 );
	}

	public function remember( $post_id ) {
		if ( ! is_numeric( $post_id ) ) { return; }
		$this->bg_before[ (int) $post_id ] = (int) get_post_meta( (int) $post_id, self::IMG_NAME, true );
	}

	public function sync( $post_id ) {
		if ( $this->syncing ) { return; }
		if ( ! is_numeric( $post_id ) ) { return; }            // skip options pages / term meta
		$post_id = (int) $post_id;
		if ( 'page' !== get_post_type( $post_id ) ) { return; }
		if ( ! function_exists( 'get_field' ) ) { return; }

		$bg    = get_field( self::IMG_NAME, $post_id ); // return_format=array
		$bg_id = 0;
		if ( is_array( $bg ) )      { $bg_id = (int) ( $bg['ID'] ?? 0 ); }
		elseif ( is_numeric( $bg ) ) { $bg_id = (int) $bg; }

		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		$bg_changed    = $bg_id !== ( $this->bg_before[ $post_id ] ?? $bg_id );
		$thumb_touched = (bool) get_post_meta( $post_id, self::TOUCHED_META, true );

		if ( $bg_changed ) {
			$want = $bg_id;            // partner changed the Background Photo: it wins
		} elseif ( $thumb_touched ) {
			$want = $thumb_id;         // partner changed or removed the Featured Image
		} else {
			$want = $bg_id ? $bg_id : $thumb_id;
		}

		$this->syncing = true;

		if ( $want !== $thumb_id ) {
			$want ? set_post_thumbnail( $post_id, $want ) : delete_post_thumbnail( $post_id );
		}
		if ( $want !== $bg_id ) {
			update_field( self::IMG_FIELD, $want ? $want : '', $post_id );
		}
		delete_post_meta( $post_id, self::TOUCHED_META );

		$this->syncing = false;
	}
}
