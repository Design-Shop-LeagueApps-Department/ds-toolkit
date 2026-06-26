<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Content Router — renders a different saved Beaver Builder template
 * for the body, chosen by the CURRENT context (post type / archive).
 *
 * The point: ONE inner-page Themer template can serve every post type without
 * Beaver Themer "display logic" spaghetti. Drop this module below the banner,
 * map each post type to a saved template, and it renders the right body.
 *   - Frame / banner fix  -> edit once (the template / banner module).
 *   - A body fix          -> edit that saved template in Beaver Builder.
 *   - Routing             -> a clean list here, not per-row conditional logic.
 *
 * @class DS_Content_Router_Module
 */
class DS_Content_Router_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Content Router', 'ds-toolkit' ),
			'description'     => __( 'Renders a saved template for the page body based on the current post type / archive.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-content-router/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-content-router/',
			'partial_refresh' => false,
			'editor_export'   => false,
		) );
	}

	/** Context options for the route "When" select: default + every public post type + their archives. */
	public static function context_options() {
		$out = array( 'default' => __( 'Single: everything else (default)', 'ds-toolkit' ) );
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' === $pt->name ) { continue; }
			$out[ $pt->name ] = sprintf( __( 'Single: %s', 'ds-toolkit' ), $pt->label );
		}
		// Archives (the router also runs on archive views when the Themer layout covers them).
		$out['archive:default']  = __( 'Archive: everything else', 'ds-toolkit' );
		$out['archive:post']     = __( 'Archive: Blog (posts index)', 'ds-toolkit' );
		$out['archive:category'] = __( 'Archive: Category', 'ds-toolkit' );
		$out['archive:tag']      = __( 'Archive: Tag', 'ds-toolkit' );
		foreach ( get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects' ) as $pt ) {
			$out[ 'archive:' . $pt->name ] = sprintf( __( 'Archive: %s (post type)', 'ds-toolkit' ), $pt->label );
		}
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tx ) {
			if ( in_array( $tx->name, array( 'category', 'post_tag', 'post_format' ), true ) ) { continue; }
			$out[ 'archive:tax:' . $tx->name ] = sprintf( __( 'Archive: %s (taxonomy)', 'ds-toolkit' ), $tx->label );
		}
		$out['archive:author'] = __( 'Archive: Author', 'ds-toolkit' );
		$out['archive:date']   = __( 'Archive: Date', 'ds-toolkit' );
		$out['archive:search'] = __( 'Archive: Search results', 'ds-toolkit' );
		return $out;
	}

	/** Saved Beaver Builder templates for the "Use template" select. */
	public static function template_options() {
		$out = array(
			'0'    => __( '— Select a saved template —', 'ds-toolkit' ),
			'self' => __( '📄 This page’s own content (edit on the page itself)', 'ds-toolkit' ),
		);
		$tpls = get_posts( array(
			'post_type'      => 'fl-builder-template',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy' => 'fl-builder-template-type',
					'field'    => 'slug',
					'terms'    => array( 'layout', 'row' ),
					'operator' => 'IN',
				),
			),
		) );
		// Fallback: if the taxonomy query returns nothing (older saves), list all.
		if ( empty( $tpls ) ) {
			$tpls = get_posts( array( 'post_type' => 'fl-builder-template', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids' ) );
		}
		foreach ( $tpls as $id ) { $out[ (string) $id ] = get_the_title( $id ) . " (#{$id})"; }
		return $out;
	}

	/** The context key for whatever is being viewed right now. */
	private function current_context() {
		if ( is_archive() || is_post_type_archive() || is_home() || is_search() ) {
			// Custom taxonomy term archive (e.g. team-category/u16) -> archive:tax:<taxonomy>,
			// so it can route distinctly from the blog/category archive.
			if ( is_tax() ) {
				$o = get_queried_object();
				if ( $o && ! empty( $o->taxonomy ) ) { return 'archive:tax:' . $o->taxonomy; }
			}
			if ( is_category() )          { return 'archive:category'; }
			if ( is_tag() )               { return 'archive:tag'; }
			if ( is_post_type_archive() ) { $pt = get_query_var( 'post_type' ); if ( is_array( $pt ) ) { $pt = reset( $pt ); } return $pt ? 'archive:' . $pt : 'archive:default'; }
			if ( is_author() )            { return 'archive:author'; }
			if ( is_date() )              { return 'archive:date'; }
			if ( is_search() )            { return 'archive:search'; }
			if ( is_home() )              { return 'archive:post'; }
			return 'archive:default';
		}
		$id = get_the_ID();
		return $id ? (string) get_post_type( $id ) : 'default';
	}

	/**
	 * Resolve the body target for the current context.
	 * Returns a saved-template id (int), or the string 'self' meaning "render the
	 * page's own content", or 0 for nothing mapped.
	 */
	private function resolve_target() {
		$routes = ( isset( $this->settings->routes ) && is_array( $this->settings->routes ) ) ? $this->settings->routes : array();
		$ctx          = $this->current_context();
		$default      = 0;
		$arch_default = 0;
		foreach ( $routes as $r ) {
			$r    = (object) $r;
			$cond = (string) ( $r->cr_when ?? '' );
			$tpl  = (string) ( $r->cr_template ?? '0' );
			$val  = ( 'self' === $tpl ) ? 'self' : (int) $tpl;
			if ( 'default' === $cond )         { $default = $val; continue; }
			if ( 'archive:default' === $cond ) { $arch_default = $val; continue; }
			if ( $cond === $ctx && ( 'self' === $val || $val ) ) { return $val; }
		}
		// Any archive with no specific route falls back to the archive default, then the global default.
		if ( 0 === strpos( $ctx, 'archive:' ) && ( 'self' === $arch_default || $arch_default ) ) { return $arch_default; }
		return $default;
	}

	/**
	 * True only while we are rendering the page's OWN content (the 'self' route).
	 * Used to break a self-referential "Post Content" field connection: if a module
	 * inside the page content is connected to the post's content, rendering it calls
	 * get_content() again → infinite recursion → memory exhaustion. See gate_nested_content().
	 */
	public static $rendering_self = false;
	public static $self_seen      = false;

	/**
	 * Filter on 'fl_themer_is_content_building_enabled'. While we render the page's own
	 * content, allow the FIRST get_content() (the real render) but force any NESTED
	 * get_content() to fall back to raw content (return a non-'content' mode), which
	 * stops the recursion a self-referencing connection would otherwise cause.
	 */
	public static function gate_nested_content( $mode, $post_id = null ) {
		if ( self::$rendering_self ) {
			if ( self::$self_seen ) {
				return 'layout';
			}
			self::$self_seen = true;
		}
		return $mode;
	}

	public function render_router() {
		// Hard re-entry guard. A body template (or the post's own content) could
		// transitively contain this router again; without this the render recurses
		// forever and exhausts memory (set_post_id stack blows up).
		static $depth = 0;
		if ( $depth > 0 ) {
			return;
		}

		$target = $this->resolve_target();

		if ( 'self' === $target ) {
			// Render THIS page's own content the SAME way Beaver Themer's native
			// "Post Content" module does — that's what makes "edit the content" work.
			// CRITICAL: never render content while the current post IS the Themer
			// layout (get_post_type() === 'fl-theme-layout'); doing so renders the
			// layout, which contains this router, which renders again → infinite
			// recursion. The native fl-post-content module guards the same way.
			if ( 'fl-theme-layout' === get_post_type() ) {
				if ( FLBuilderModel::is_builder_active() ) {
					echo '<div style="padding:60px 20px;text-align:center;opacity:.5">' . esc_html__( 'Page Content Area', 'ds-toolkit' ) . '</div>';
				}
				return;
			}
			$depth++;
			if ( class_exists( 'FLPageDataPost' ) ) {
				self::$rendering_self = true;
				self::$self_seen      = false;
				echo FLPageDataPost::get_content();
				self::$rendering_self = false;
			} else {
				echo apply_filters( 'the_content', get_the_content() );
			}
			$depth--;
			return;
		}

		if ( $target ) {
			$depth++;
			echo do_shortcode( '[fl_builder_insert_layout id="' . (int) $target . '"]' );
			$depth--;
		} elseif ( FLBuilderModel::is_builder_active() ) {
			echo '<p style="padding:18px;opacity:.7;text-align:center">' . esc_html__( 'Content Router: no template mapped for this context yet. Add a route in the module settings.', 'ds-toolkit' ) . '</p>';
		}
	}
}

/* Breaks self-referential "Post Content" connection recursion while the router
   renders a page's own content (see DS_Content_Router_Module::gate_nested_content). */
add_filter( 'fl_themer_is_content_building_enabled', array( 'DS_Content_Router_Module', 'gate_nested_content' ), 99, 2 );

/* Builder-UI script: powers the "Edit template" button inside each Route popup. */
add_action( 'fl_builder_ui_enqueue_scripts', function () {
	wp_enqueue_script(
		'ds-cr-admin',
		DS_TOOLKIT_URL . 'modules/ds-content-router/js/admin.js',
		array( 'jquery' ),
		DS_TOOLKIT_VERSION,
		true
	);
} );

/* --------------------------------------------------------------- Route sub-form */
FLBuilder::register_settings_form( 'ds_cr_route_form', array(
	'title' => __( 'Route', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Route', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'cr_when'     => array(
							'type'    => 'select',
							'label'   => __( 'When viewing', 'ds-toolkit' ),
							'default' => 'default',
							'options' => DS_Content_Router_Module::context_options(),
						),
						'cr_template' => array(
							'type'    => 'select',
							'label'   => __( 'Use saved template', 'ds-toolkit' ),
							'default' => '0',
							'options' => DS_Content_Router_Module::template_options(),
							'help'    => __( 'Build each body once as a Beaver Builder saved template, then map it here.', 'ds-toolkit' ),
						),
						'cr_edit_btn' => array(
							'type'    => 'raw',
							'content' => '<button type="button" class="fl-builder-button ds-cr-edit-tpl" style="width:100%;text-align:center">' . esc_html__( 'Edit template ↗', 'ds-toolkit' ) . '</button>'
								. '<p class="fl-field-description" style="margin-top:6px">' . esc_html__( 'Opens the selected saved template in a new tab.', 'ds-toolkit' ) . '</p>',
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_Content_Router_Module', array(
	'routing' => array(
		'title'    => __( 'Routing', 'ds-toolkit' ),
		'sections' => array(
			'routes_sec' => array(
				'title'       => __( 'Body by Context', 'ds-toolkit' ),
				'description' => __( 'Map each post type / archive to the saved template that should render as the body. Add a “default” route for everything else.', 'ds-toolkit' ),
				'fields'      => array(
					'routes' => array(
						'type'         => 'form',
						'label'        => __( 'Route', 'ds-toolkit' ),
						'form'         => 'ds_cr_route_form',
						'preview_text' => 'cr_when',
						'multiple'     => true,
					),
				),
			),
		),
	),
) );
