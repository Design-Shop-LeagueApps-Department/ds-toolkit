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
			if ( $cond === $ctx && ( 'self' === $val || $val ) ) {
				// "Each post can be built in Beaver Builder": a post that has been built (or is open in
				// the builder right now) renders its own layout; every other post keeps this template.
				if ( 'build' === (string) ( $r->cr_own_layout ?? 'no' ) && ( self::editing_this_post() || self::is_built( (int) get_queried_object_id() ) ) ) { return 'self'; }
				return $val;
			}
		}
		// Any archive with no specific route falls back to the archive default, then the global default.
		if ( 0 === strpos( $ctx, 'archive:' ) && ( 'self' === $arch_default || $arch_default ) ) { return $arch_default; }
		return $default;
	}

	/** True while the single post being viewed is the one open in Beaver Builder. */
	private static function editing_this_post() {
		// The builder always edits the queried post. FLBuilderModel::get_post_id() is not usable
		// here: while the router renders inside a Themer layout it returns the layout's id.
		return is_singular() && class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active()
			&& current_user_can( 'edit_post', (int) get_queried_object_id() );
	}

	/** The post open in Beaver Builder: the queried post on a page load, BB's post id on builder AJAX. */
	public static function builder_post_id() {
		if ( ! class_exists( 'FLBuilderModel' ) ) { return 0; }
		if ( ! wp_doing_ajax() && did_action( 'wp' ) && is_singular() ) { return (int) get_queried_object_id(); }
		return (int) FLBuilderModel::get_post_id();
	}

	/**
	 * True when a post has been built in Beaver Builder: builder on AND a published layout. BB sets
	 * "builder on" the moment a post is opened, before anything exists, so that alone is not "built"
	 * (a post opened and then discarded keeps the template).
	 */
	public static function is_built( $pid ) {
		return $pid > 0 && class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_enabled( $pid )
			&& ! empty( get_post_meta( $pid, '_fl_builder_data', true ) );
	}

	/**
	 * The build-mode route for a post type ("Each post can be built in Beaver Builder"), read from
	 * the Content Router modules in the Themer layouts: array( 'template' => fallback id, 'starter' =>
	 * starter id ), or null.
	 */
	public static function build_route( $post_type ) {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			$layouts = get_posts( array( 'post_type' => 'fl-theme-layout', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids' ) );
			foreach ( $layouts as $lid ) {
				foreach ( (array) get_post_meta( $lid, '_fl_builder_data', true ) as $node ) {
					if ( ! is_object( $node ) || 'ds-content-router' !== ( $node->settings->type ?? '' ) ) { continue; }
					foreach ( (array) ( $node->settings->routes ?? array() ) as $r ) {
						$r = (object) $r;
						if ( 'build' !== (string) ( $r->cr_own_layout ?? 'no' ) ) { continue; }
						$starter = (int) ( $r->cr_starter ?? 0 );
						$map[ (string) ( $r->cr_when ?? '' ) ] = array( 'template' => (int) ( $r->cr_template ?? 0 ), 'starter' => $starter ?: (int) ( $r->cr_template ?? 0 ) );
					}
				}
			}
		}
		return $map[ (string) $post_type ] ?? null;
	}

	/**
	 * First time a build-mode post opens in Beaver Builder (no draft, never built): keep the Themer
	 * layout around it ("Edit Content Only", so no Override prompt) and seed the draft with the
	 * starter template, copying the post's current field content in above each Drop Area that
	 * names a Seed Field, so switching the post to the builder loses nothing.
	 *
	 * Hooked to fl_builder_pre_editing_enabled: Beaver Builder fires it in the request where it
	 * decides what a first-time layout contains, just before it would turn the post's raw
	 * post_content into a Text Editor module. Seeding here (update_layout_data refreshes BB's
	 * per-request cache) means BB finds our draft and keeps it.
	 */
	public static function seed_build() {
		global $wp_the_query;
		if ( ! class_exists( 'FLBuilderModel' ) || empty( $wp_the_query->post ) ) { return; }
		$pid = (int) $wp_the_query->post->ID;
		if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) { return; }
		$route = self::build_route( get_post_type( $pid ) );
		if ( ! $route || ! $route['starter'] ) { return; }
		if ( 'content' !== get_post_meta( $pid, '_fl_theme_builder_edit_mode', true ) ) {
			update_post_meta( $pid, '_fl_theme_builder_edit_mode', 'content' );
		}
		if ( self::is_built( $pid ) || ! empty( get_post_meta( $pid, '_fl_builder_draft', true ) ) || ! empty( get_post_meta( $pid, '_fl_builder_data', true ) ) ) { return; }
		$data = get_post_meta( $route['starter'], '_fl_builder_data', true );
		if ( ! is_array( $data ) || ! $data ) { return; }
		$data = self::seed_fields( unserialize( serialize( $data ) ), $pid );
		FLBuilderModel::update_layout_data( $data, 'draft', $pid );
		$ls = get_post_meta( $route['starter'], '_fl_builder_data_settings', true );
		if ( $ls ) { update_post_meta( $pid, '_fl_builder_draft_settings', $ls ); }
	}

	/** Copies each Drop Area's Seed Field value in as a Text Editor module just above the guide. */
	private static function seed_fields( $data, $pid ) {
		foreach ( $data as $id => $node ) {
			if ( ! is_object( $node ) || 'ds-drop-area' !== ( $node->settings->type ?? '' ) ) { continue; }
			$field = trim( (string) ( $node->settings->seed_field ?? '' ) );
			if ( '' === $field || '_thumbnail' === $field ) { continue; }
			$val = function_exists( 'get_field' ) ? get_field( $field, $pid, false ) : get_post_meta( $pid, $field, true );
			if ( ! is_string( $val ) || '' === trim( wp_strip_all_tags( $val, true ) ) && ! preg_match( '/\[[a-z_-]+|<(img|iframe|table)/i', (string) $val ) ) { continue; }
			foreach ( $data as $sib ) { if ( is_object( $sib ) && $sib->parent === $node->parent && $sib->position >= $node->position ) { $sib->position++; } }
			$t = FLBuilderModel::get_module_defaults( 'rich-text' ); $t->type = 'rich-text'; $t->text = $val;
			$nid = FLBuilderModel::generate_node_id();
			$data[ $nid ] = (object) array( 'node' => $nid, 'type' => 'module', 'parent' => $node->parent, 'position' => $node->position - 1, 'settings' => $t, 'global' => false );
		}
		return $data;
	}

	/**
	 * Fields a build-mode post type seeds into its starter (the Drop Areas' Seed Fields), for the
	 * dashboard notice on built posts.
	 */
	public static function seeded_fields( $post_type ) {
		$route = self::build_route( $post_type );
		if ( ! $route || ! $route['starter'] ) { return array(); }
		$out = array();
		foreach ( (array) get_post_meta( $route['starter'], '_fl_builder_data', true ) as $node ) {
			$f = is_object( $node ) && 'ds-drop-area' === ( $node->settings->type ?? '' ) ? trim( (string) ( $node->settings->seed_field ?? '' ) ) : '';
			if ( '' !== $f && '_thumbnail' !== $f ) { $out[] = $f; }
		}
		return $out;
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

add_action( 'fl_builder_pre_editing_enabled', array( 'DS_Content_Router_Module', 'seed_build' ) );

/* Areas (a Box module with the class ds-page-area): on the live page an area holding nothing but its
   heading and its Drop Area guide, or a loop with no items, is hidden with its heading. In the
   builder every area stays visible so editors can see where to drop. */
add_action( 'wp_enqueue_scripts', function () {
	if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) { return; }
	$css = '.fl-module-ds-drop-area{display:none!important;}'
		. '.ds-page-area:not(:has(.fl-module:not(.fl-module-heading):not(.fl-module-ds-heading):not(.fl-module-ds-drop-area):not(.fl-module-ds-post-loop))):not(:has(.ds-people-card,.ds-news-card,.ds-news-card2,.ds-news-feature,.ds-teamcard,.ds-team-row,.ds-tourn-card)){display:none!important;}';
	wp_register_style( 'ds-page-areas', false, array(), DS_TOOLKIT_VERSION );
	wp_enqueue_style( 'ds-page-areas' );
	wp_add_inline_style( 'ds-page-areas', $css );
}, 20 );

/* Dashboard: on a post built in Beaver Builder, the fields its starter seeds (e.g. Roster, Schedule)
   no longer drive the page, so show where to edit instead of an editor that changes nothing. */
add_filter( 'acf/prepare_field', function ( $field ) {
	global $post;
	// By prepare_field time ACF has turned 'name' into the input name (acf[field_...]); the field name is '_name'.
	$name = (string) ( $field['_name'] ?? $field['name'] ?? '' );
	if ( ! is_admin() || empty( $post->ID ) || '' === $name ) { return $field; }
	if ( ! in_array( $name, DS_Content_Router_Module::seeded_fields( $post->post_type ), true ) ) { return $field; }
	if ( ! DS_Content_Router_Module::is_built( (int) $post->ID ) ) { return $field; }
	$url = class_exists( 'FLBuilderModel' ) ? FLBuilderModel::get_edit_url( $post->ID ) : get_permalink( $post->ID );
	$field['type']    = 'message';
	$field['message'] = sprintf(
		/* translators: 1: post type label, 2: link */
		__( 'This %1$s is built in Beaver Builder, so this content is edited on the page. %2$s', 'ds-toolkit' ),
		esc_html( strtolower( (string) get_post_type_object( $post->post_type )->labels->singular_name ) ),
		'<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Open in Beaver Builder', 'ds-toolkit' ) . '</a>'
	);
	$field['new_lines'] = '';
	$field['esc_html']  = 0;
	return $field;
} );

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
						'cr_own_layout' => array(
							'type'    => 'select',
							'label'   => __( 'Posts built in Beaver Builder', 'ds-toolkit' ),
							'default' => 'no',
							'options' => array(
								'no'    => __( 'Always use this template', 'ds-toolkit' ),
								'build' => __( 'Each post can be built in Beaver Builder', 'ds-toolkit' ),
							),
							'toggle'  => array( 'build' => array( 'fields' => array( 'cr_starter' ) ) ),
							'help'    => __( 'With "can be built", opening a post of this type in Beaver Builder starts it from the Starter Template below (its current content copied in) and editors drag modules into its Drop Areas. Once published, that post shows its own layout; posts nobody has built keep this template. Needs the post type ticked in Settings > Beaver Builder > Post Types.', 'ds-toolkit' ),
						),
						'cr_starter' => array(
							'type'    => 'select',
							'label'   => __( 'Starter Template', 'ds-toolkit' ),
							'default' => '0',
							'options' => DS_Content_Router_Module::template_options(),
							'help'    => __( 'The layout a post starts from the first time it is opened in Beaver Builder. Put a Drop Area (guide) in each place editors should drag modules.', 'ds-toolkit' ),
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

// Routes by the current post type, so it only belongs in a Themer layout (or a saved template
// those layouts insert). Hidden from the module list on ordinary pages; see offer_only_in_templates().
DS_Module_UI::offer_only_in_templates( 'ds-content-router' );

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
