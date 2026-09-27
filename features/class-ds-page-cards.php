<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Page Cards" Beaver Builder module
 * (modules/ds-page-cards/): the module version of the [child_pages] shortcode —
 * lists a page's child pages as cards, each rendered with a saved layout.
 * Blueprint generation 6+.
 *
 * Page Images (Alipio 2026-09-27: "option to replace the featured image in this page cards module, and it sync to the
 * page ... so that we wont ask partner to go edit the page again ... goal is we stick in beaver builder"): the module's
 * settings list the child pages it shows, each with its featured image and Change / Remove. A change is saved to that
 * page at once (like the Featured Image box in the editor) and, on a page, to its Page Banner photo too, which the
 * toolkit keeps equal to the featured image.
 */
class DS_Page_Cards {
	const AJAX_LIST = 'ds_pc_images';
	const AJAX_SET  = 'ds_pc_set_image';

	private $settings;
	public function __construct( $settings = array() ) { $this->settings = $settings; }
	public function init() {
		add_action( 'init', array( $this, 'register_module' ), 20 );
		add_action( 'fl_builder_ui_enqueue_scripts', array( $this, 'builder_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_LIST, array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_' . self::AJAX_SET, array( $this, 'ajax_set' ) );
	}
	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-page-cards/ds-page-cards.php';
		}
	}

	public function builder_assets() {
		wp_enqueue_media();
		wp_enqueue_style( 'ds-page-cards-images', DS_TOOLKIT_URL . 'modules/ds-page-cards/css/images.css', array(), DS_TOOLKIT_VERSION );
		wp_enqueue_script( 'ds-page-cards-images', DS_TOOLKIT_URL . 'modules/ds-page-cards/js/images.js', array( 'jquery' ), DS_TOOLKIT_VERSION, true );
		wp_localize_script( 'ds-page-cards-images', 'DSPageCardsImages', array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( self::AJAX_LIST ),
			'postId' => class_exists( 'FLBuilderModel' ) ? (int) FLBuilderModel::get_post_id() : 0,
		) );
	}

	/** The child pages the module shows, chosen exactly as DS_Page_Cards_Module::render() chooses them. */
	private static function children( $post_id, array $q ) {
		if ( 'manual' === ( $q['list_source'] ?? 'children' ) ) { return array(); }
		$parent = ( 'specific' === ( $q['source'] ?? 'current' ) && absint( $q['parent_page'] ?? 0 ) ) ? absint( $q['parent_page'] ) : (int) $post_id;
		if ( ! $parent ) { return array(); }
		$orderby = (string) ( $q['order_by'] ?? 'menu_order title' );
		if ( ! in_array( $orderby, array( 'menu_order title', 'menu_order', 'title', 'date', 'modified' ), true ) ) { $orderby = 'menu_order title'; }
		return get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_parent'    => $parent,
			'orderby'        => $orderby,
			'order'          => ( 'DESC' === strtoupper( (string) ( $q['order'] ?? 'ASC' ) ) ) ? 'DESC' : 'ASC',
			'posts_per_page' => max( 1, min( 200, absint( $q['limit'] ?? 50 ) ?: 50 ) ),
		) );
	}

	private static function row( WP_Post $p ) {
		$img = (int) get_post_thumbnail_id( $p->ID );
		return array(
			'id'      => $p->ID,
			'title'   => wp_strip_all_tags( get_the_title( $p ) ),
			'img'     => $img,
			'thumb'   => $img ? (string) wp_get_attachment_image_url( $img, 'medium' ) : '',
			'canEdit' => current_user_can( 'edit_post', $p->ID ),
		);
	}

	public function ajax_list() {
		check_ajax_referer( self::AJAX_LIST, 'nonce' );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { wp_send_json_error( array( 'message' => __( 'You cannot edit this page.', 'ds-toolkit' ) ), 403 ); }
		$q = array();
		foreach ( array( 'list_source', 'source', 'parent_page', 'limit', 'order_by', 'order' ) as $k ) { $q[ $k ] = sanitize_text_field( wp_unslash( $_POST['q'][ $k ] ?? '' ) ); }
		foreach ( array( 'list_source' => 'children', 'source' => 'current', 'limit' => '50', 'order_by' => 'menu_order title', 'order' => 'ASC' ) as $k => $d ) { if ( '' === $q[ $k ] ) { $q[ $k ] = $d; } }
		wp_send_json_success( array( 'pages' => array_map( array( __CLASS__, 'row' ), self::children( $post_id, $q ) ) ) );
	}

	public function ajax_set() {
		check_ajax_referer( self::AJAX_LIST, 'nonce' );
		$id  = absint( $_POST['page_id'] ?? 0 );
		$img = absint( $_POST['attachment_id'] ?? 0 );
		if ( ! $id || 'page' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) { wp_send_json_error( array( 'message' => __( 'You cannot edit that page.', 'ds-toolkit' ) ), 403 ); }
		if ( $img && ! wp_attachment_is_image( $img ) ) { wp_send_json_error( array( 'message' => __( 'That file is not an image.', 'ds-toolkit' ) ), 400 ); }
		$img ? set_post_thumbnail( $id, $img ) : delete_post_thumbnail( $id );
		// The page's Page Banner photo is the same image (DS_Page_Banner keeps the two equal); set it too, so the card, the
		// page's banner and anything else showing the page image agree right away.
		if ( function_exists( 'update_field' ) && function_exists( 'acf_get_field' ) && acf_get_field( 'field_dst_banner_image' ) ) {
			update_field( 'field_dst_banner_image', $img ? $img : '', $id );
			delete_post_meta( $id, '_ds_banner_featured_touched' );
		}
		clean_post_cache( $id );
		if ( class_exists( 'FLBuilderModel' ) ) { FLBuilderModel::delete_asset_cache( $id ); }
		$host = absint( $_POST['post_id'] ?? 0 ); // the page showing the cards
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache( $id );
			if ( $host && current_user_can( 'edit_post', $host ) ) { WpeCommon::purge_varnish_cache( $host ); }
		}
		wp_send_json_success( self::row( get_post( $id ) ) );
	}
}
