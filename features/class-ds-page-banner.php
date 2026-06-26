<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Keeps a page's Banner "Background Photo" (ACF page_hero_banner_image) and its
 * Featured Image in sync, so a partner only ever sets the image in one place.
 *
 * Rule on save (pages only):
 *   - If a Background Photo is set  -> mirror it to the Featured Image.
 *   - Else if a Featured Image is set -> pull it into the Background Photo.
 * After saving, the two always match (when at least one is set). Blueprint 6+.
 */
class DS_Page_Banner {

	const IMG_FIELD = 'field_dst_banner_image'; // ACF key for page_hero_banner_image

	private $settings;
	private $syncing = false;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		// Priority 20: after ACF has written the fields (priority ~10).
		add_action( 'acf/save_post', array( $this, 'sync' ), 20 );
	}

	public function sync( $post_id ) {
		if ( $this->syncing ) { return; }
		if ( ! is_numeric( $post_id ) ) { return; }            // skip options pages / term meta
		$post_id = (int) $post_id;
		if ( 'page' !== get_post_type( $post_id ) ) { return; }
		if ( ! function_exists( 'get_field' ) ) { return; }

		$bg    = get_field( 'page_hero_banner_image', $post_id ); // return_format=array
		$bg_id = 0;
		if ( is_array( $bg ) )      { $bg_id = (int) ( $bg['ID'] ?? 0 ); }
		elseif ( is_numeric( $bg ) ) { $bg_id = (int) $bg; }

		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		$this->syncing = true;

		if ( $bg_id ) {
			// Background Photo is the one the partner edits in the banner box: it wins.
			if ( $bg_id !== $thumb_id ) {
				set_post_thumbnail( $post_id, $bg_id );
			}
		} elseif ( $thumb_id ) {
			// No banner photo set, but a Featured Image exists -> use it for the banner.
			update_field( self::IMG_FIELD, $thumb_id, $post_id );
		}

		$this->syncing = false;
	}
}
