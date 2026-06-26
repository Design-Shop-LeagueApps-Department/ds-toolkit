<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Content Router" Beaver Builder module
 * (modules/ds-content-router/). Renders a saved template as the page body based
 * on the current post type / archive — one Themer template, no display logic.
 * Blueprint generation 6+.
 */
class DS_Content_Router {
	private $settings;
	public function __construct( $settings = array() ) { $this->settings = $settings; }
	public function init() {
		add_action( 'init', array( $this, 'register_module' ), 20 );
		// Runs after Beaver Themer's admin-bar menu (priority 1000).
		add_action( 'admin_bar_menu', array( __CLASS__, 'redirect_base_to_route' ), 1010 );
	}
	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-content-router/ds-content-router.php';
		}
	}

	/**
	 * On a routed single (a CPT single whose body is rendered by a mapped saved
	 * template), point the singular Themer layout's admin-bar link straight at that
	 * saved template, the body you actually want to edit, not the shared base frame.
	 * Pages (default -> "this page's own content") are left untouched.
	 */
	public static function redirect_base_to_route( $bar ) {
		if ( is_admin() || ! is_admin_bar_showing() || ! is_singular() ) { return; }
		if ( ! class_exists( 'FLThemeBuilderLayoutData' ) || ! class_exists( 'FLBuilderModel' ) ) { return; }

		$layouts = FLThemeBuilderLayoutData::get_current_page_layouts();
		if ( empty( $layouts['singular'][0]['id'] ) ) { return; }
		$singular = (int) $layouts['singular'][0]['id'];

		$target = self::route_target( $singular, get_post_type() );
		if ( ! $target ) { return; }

		$node = $bar->get_node( 'fl-theme-builder-edit-link-' . $singular );
		if ( ! $node ) { return; }

		$href  = add_query_arg( array( 'r' => base64_encode( get_permalink() ) ), FLBuilderModel::get_edit_url( $target ) );
		$title = '<span class="fl-builder-ab-title">' . esc_html( get_the_title( $target ) ) . '</span>'
			. '<span class="fl-builder-ab-tag">' . esc_html__( 'BODY', 'ds-toolkit' ) . '</span>'
			. sprintf( '<span class="fl-builder-ab-wrench ab-icon dashicons dashicons-admin-generic" title="%s" data-edit="%s"></span>', esc_attr__( 'Open in wp-admin', 'ds-toolkit' ), urlencode( (string) get_edit_post_link( $target ) ) );

		// Re-adding with the same id updates the existing node (href + title).
		$bar->add_node( array(
			'id'     => 'fl-theme-builder-edit-link-' . $singular,
			'parent' => 'fl-builder-frontend-edit-link',
			'title'  => $title,
			'href'   => $href,
		) );
	}

	/** The saved-template id the router maps $post_type to (0 if none / self / unset). */
	private static function route_target( $singular_id, $post_type ) {
		$data = get_post_meta( $singular_id, '_fl_builder_data', true );
		if ( ! is_array( $data ) ) { return 0; }
		foreach ( $data as $n ) {
			if ( ! is_object( $n ) || ( $n->settings->type ?? '' ) !== 'ds-content-router' ) { continue; }
			$routes = $n->settings->routes ?? array();
			if ( ! is_array( $routes ) ) { return 0; }
			foreach ( $routes as $r ) {
				$r = (object) $r;
				if ( ( $r->cr_when ?? '' ) === $post_type ) {
					$tpl = (string) ( $r->cr_template ?? '0' );
					if ( '' !== $tpl && 'self' !== $tpl && '0' !== $tpl ) { return (int) $tpl; }
				}
			}
		}
		return 0;
	}
}
