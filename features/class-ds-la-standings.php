<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "LeagueApps Standings" Beaver Builder module (modules/ds-la-standings/): a league's
 * standings, every division, worked out from the LeagueApps results.
 *
 * Opt-in on every blueprint, like Programs and Calendar: a data integration that shows nothing until a
 * LeagueApps site is saved under Settings > DS Toolkit > LeagueApps. It reads the league list through the
 * Programs feed cache, so it needs that class but not that module.
 */
class DS_LA_Standings {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
		DS_Programs_Data::hooks(); // the feed's background refresh needs its handler in the cron request
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-la-colors.php';
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-standings-data.php';
		DS_Standings_Data::hooks();
		add_action( 'init', array( $this, 'register_module' ), 20 );
		add_action( 'ds_programs_flushed', array( 'DS_Standings_Data', 'flush' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'fl_builder_ui_enqueue_scripts', array( $this, 'builder_multiselect' ) );
	}

	/** Builder only: the "Leagues in the Dropdown" list ticks and unticks with a plain click (as the Calendar's lists). */
	public function builder_multiselect() {
		wp_enqueue_script( 'ds-builder-multiselect', DS_TOOLKIT_URL . 'assets/builder-multiselect.js', array( 'jquery' ), DS_TOOLKIT_VERSION, true );
	}

	/**
	 * League dropdown: GET /wp-json/ds-toolkit/v1/standings/<post>/<node>?league=<site:id> answers
	 * {"html": ...}, the placed block rendered for that league with its own saved settings. Read-only and public
	 * like the page; only a league that block's dropdown lists is served, and the data comes from the same cache
	 * and the same per-site lock and budget as a page view, so switching leagues adds no extra LeagueApps load.
	 */
	public function routes() {
		register_rest_route( 'ds-toolkit/v1', '/standings/(?P<post>\\d{1,20})/(?P<node>[a-z0-9]{1,32})', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( $this, 'league_html' ),
			'args'                => array( 'league' => array( 'type' => 'string', 'required' => true, 'pattern' => '^[0-9]{1,12}:[0-9]{1,12}$' ) ),
		) );
	}

	/** The saved settings of a Standings block on a published, public post; null otherwise. */
	public static function node_settings( $post_id, $node_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return null;
		}
		// Themer layouts and saved rows are not public post types, but render on public pages.
		if ( ! in_array( $post->post_type, array( 'fl-theme-layout', 'fl-builder-template' ), true ) && ! is_post_publicly_viewable( $post ) ) {
			return null;
		}
		$data = get_post_meta( $post->ID, '_fl_builder_data', true );
		$node = is_array( $data ) && isset( $data[ $node_id ] ) ? $data[ $node_id ] : null;
		if ( ! is_object( $node ) || 'module' !== ( $node->type ?? '' ) || ! isset( $node->settings ) || ! is_object( $node->settings ) || 'ds-la-standings' !== ( $node->settings->type ?? '' ) ) {
			return null;
		}
		return $node->settings;
	}

	public function league_html( $request ) {
		$post     = (int) $request['post'];
		$node     = (string) $request['node'];
		$key      = (string) $request['league'];
		$settings = self::node_settings( $post, $node );
		if ( ! $settings || ! class_exists( 'FLBuilder' ) || ! class_exists( 'DS_Standings_Module' ) ) {
			return new WP_Error( 'ds_standings_not_found', 'No standings here.', array( 'status' => 404 ) );
		}
		$module           = FLBuilderModel::create_module_instance( 'ds-la-standings' );
		$module->settings = (object) array_merge( (array) FLBuilderModel::get_module_defaults( 'ds-la-standings' ), (array) $settings );
		if ( ! isset( $module->picker()[ $key ] ) ) {
			return new WP_Error( 'ds_standings_league', 'That league is not in this block.', array( 'status' => 404 ) );
		}
		DS_Standings_Module::$rest = array( 'post' => $post, 'node' => $node, 'league' => $key );
		$html                      = FLBuilder::render_module_html( 'ds-la-standings', $settings, null, $node, false );
		DS_Standings_Module::$rest = null;
		$res = new WP_REST_Response( array( 'html' => (string) $html ), 200 );
		$res->header( 'Cache-Control', 'public, max-age=300' );
		$res->header( 'X-Robots-Tag', 'noindex' );
		return $res;
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-la-standings/ds-la-standings.php';
		}
	}
}
