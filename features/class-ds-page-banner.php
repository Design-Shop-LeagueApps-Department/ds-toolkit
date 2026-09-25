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
 */
class DS_Page_Banner {

	const IMG_FIELD    = 'field_dst_banner_image'; // ACF key for page_hero_banner_image
	const IMG_NAME     = 'page_hero_banner_image';
	const TOUCHED_META = '_ds_banner_featured_touched';

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
