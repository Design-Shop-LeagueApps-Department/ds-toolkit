<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Post Loop: related people avatars and the [ds_team_coach_avatars] shortcode.
 *
 * One part of DS_Post_Loop_Module, which was a single 1,640-line class until the 2026-11 audit. The
 * methods were moved here as they were: a trait, so they are still methods of the module (same
 * visibility, same $this, same settings). The query, the defaults and the layout list stay in
 * ds-post-loop.php; the settings form is includes/settings-form.php.
 */
trait DS_Post_Loop_Avatars {

	/**
	 * Resolve an ACF relationship / post-object field to an ordered list of post
	 * IDs, keeping the order the editor selected them in.
	 *
	 * ACF returns post objects or IDs depending on the field's Return Format,
	 * and raw postmeta is a serialized array of numeric strings — accept all
	 * three so this works with or without ACF, and fall back to postmeta when
	 * the field group is not registered for the queried post type.
	 */
	private static function related_ids( $field, $pid ) {
		$rel = function_exists( 'get_field' ) ? get_field( $field, $pid ) : null;
		if ( empty( $rel ) ) { $rel = get_post_meta( $pid, $field, true ); }
		if ( empty( $rel ) ) { return array(); }
		if ( ! is_array( $rel ) ) { $rel = array( $rel ); }

		$ids = array();
		foreach ( $rel as $r ) {
			if ( is_object( $r ) && isset( $r->ID ) )      { $ids[] = (int) $r->ID; }
			elseif ( is_array( $r ) && isset( $r['ID'] ) ) { $ids[] = (int) $r['ID']; }
			elseif ( is_numeric( $r ) )                    { $ids[] = (int) $r; }
		}
		// array_unique keeps the FIRST occurrence, so the selected order survives.
		return array_values( array_filter( array_unique( $ids ) ) );
	}

	/** Only a registered image size; anything else falls back to the thumbnail. */
	private static function avatar_img_size( $size ) {
		$size = trim( (string) $size );
		if ( '' === $size ) { return 'thumbnail'; }
		$known = array_merge( get_intermediate_image_sizes(), array( 'full' ) );
		return in_array( $size, $known, true ) ? $size : 'thumbnail';
	}

	/**
	 * Avatar CSS, printed once per request the first time the shortcode draws
	 * something. Inline rather than added to css/frontend.css because the
	 * shortcode is reusable outside this module, and BB serves layout CSS from
	 * a per-node cache that would not pick up a new rule until every affected
	 * layout is re-saved.
	 */
	private static function avatar_css() {
		static $printed = false;
		if ( $printed ) { return ''; }
		$printed = true;
		return '<style id="ds-coach-avatars-css">'
			. '.ds-coach-avatars{display:inline-flex;align-items:center;line-height:0;font-size:0;}'
			. '.ds-coach-avatars>*{margin-left:calc(var(--ds-ca-overlap,14px) * -1);line-height:0;}'
			. '.ds-coach-avatars>*:first-child{margin-left:0;}'
			. '.ds-coach-avatar{display:block;box-sizing:border-box;'
			. 'width:var(--ds-ca-size,46px);height:var(--ds-ca-size,46px);max-width:none;'
			. 'border-radius:50%;object-fit:cover;background:#e9eef4;'
			. 'border:var(--ds-ca-ring-w,3px) solid var(--ds-ca-ring,#fff);'
			. 'box-shadow:0 1px 3px rgba(10,30,60,.18);}'
			. '</style>';
	}

	/**
	 * [ds_team_coach_avatars] — small overlapping circular coach photos.
	 *
	 * Inside a Custom Loop Layout this resolves against the card's own post, so
	 * each Team card shows its own coaches:
	 *
	 *   [ds_team_coach_avatars]
	 *   [ds_team_coach_avatars field="team_coach" size="46" overlap="14"]
	 *
	 * Related posts without a featured image are skipped. When the field is
	 * empty — or nothing in it has a featured image — this returns an empty
	 * string, wrapper included, so a card with no coaches keeps no gap.
	 *
	 * @param array $atts field, post_id, size, overlap, ring, ring_width,
	 *                    limit, link, image_size, class.
	 */
	public static function coach_avatars_shortcode( $atts ) {
		$a = shortcode_atts( array(
			'field'      => 'team_coach',
			'post_id'    => 0,          // 0 = the current (loop) post
			'size'       => 46,         // px, outer diameter including the ring
			'overlap'    => 14,         // px each avatar slides under the previous one
			'ring'       => '#ffffff',
			'ring_width' => 3,          // px
			'limit'      => 0,          // 0 = every coach
			'link'       => 'no',       // yes = wrap each avatar in its staff permalink
			'image_size' => 'thumbnail',
			'class'      => '',
		), $atts, 'ds_team_coach_avatars' );

		$pid = (int) $a['post_id'] ?: (int) get_the_ID();
		if ( ! $pid ) { return ''; }

		$field = trim( (string) $a['field'] );
		if ( '' === $field ) { $field = 'team_coach'; }

		$ids = self::related_ids( $field, $pid );
		if ( empty( $ids ) ) { return ''; }

		$limit = max( 0, (int) $a['limit'] );
		if ( $limit > 0 ) { $ids = array_slice( $ids, 0, $limit ); }

		$link = 'yes' === strtolower( trim( (string) $a['link'] ) );
		$size = self::avatar_img_size( $a['image_size'] );

		$items = '';
		foreach ( $ids as $cid ) {
			if ( 'publish' !== get_post_status( $cid ) ) { continue; }
			$src = get_the_post_thumbnail_url( $cid, $size );
			if ( ! $src ) { continue; }
			$name = get_the_title( $cid );
			// skip-lazy: these are ~46px, so a lazy-load placeholder costs a visible
			// pop-in and buys nothing.
			$img  = '<img class="ds-coach-avatar skip-lazy" src="' . esc_url( $src ) . '"'
				. ' alt="' . esc_attr( $name ) . '" decoding="async" />';
			$items .= $link
				? '<a class="ds-coach-avatar-link" href="' . esc_url( get_permalink( $cid ) ) . '">' . $img . '</a>'
				: $img;
		}
		if ( '' === $items ) { return ''; }

		// The ring colour lands in a style attribute, so only let a colour through.
		$ring = trim( (string) $a['ring'] );
		if ( ! preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s.,%]+\)|var\(--[\w-]+\)|[a-zA-Z]+)$/', $ring ) ) {
			$ring = '#ffffff';
		}
		$vars = '--ds-ca-size:' . max( 12, min( 200, (int) $a['size'] ) ) . 'px;'
			. '--ds-ca-overlap:' . max( 0, min( 100, (int) $a['overlap'] ) ) . 'px;'
			. '--ds-ca-ring-w:' . max( 0, min( 12, (int) $a['ring_width'] ) ) . 'px;'
			. '--ds-ca-ring:' . $ring . ';';

		$extra = implode( ' ', array_filter( array_map(
			'sanitize_html_class',
			preg_split( '/\s+/', trim( (string) $a['class'] ) )
		) ) );

		return self::avatar_css()
			. '<div class="' . esc_attr( trim( 'ds-coach-avatars ' . $extra ) ) . '"'
			. ' style="' . esc_attr( $vars ) . '">' . $items . '</div>';
	}
}
