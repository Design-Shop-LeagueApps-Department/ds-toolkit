<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "LeagueApps Calendar" Beaver Builder module
 * (modules/ds-la-calendar/): a club's LeagueApps team schedules as a month
 * calendar and an upcoming list.
 *
 * Opt-in on every blueprint, like LeagueApps Programs: it is a data
 * integration and shows nothing until a LeagueApps site is saved under
 * Settings > DS Toolkit > LeagueApps. It reads the program list through the
 * Programs feed cache, so it needs that class but not that module.
 */
class DS_LA_Calendar {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-calendar-data.php';
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-calendar-ics.php';
		add_action( 'init', array( $this, 'register_module' ), 20 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'ds_programs_flushed', array( 'DS_Calendar_Data', 'flush' ) );
		add_action( 'ds_calendar_refresh', array( 'DS_Calendar_Data', 'refresh' ) ); // background refresh queued by get()
		// Without the Programs module there is no "Refresh LeagueApps listings" link; offer the same one.
		if ( empty( $this->settings['ds_programs_module_enabled'] ) ) {
			add_action( 'admin_post_ds_programs_flush', array( $this, 'flush' ) );
			add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		}
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-la-calendar/ds-la-calendar.php';
		}
	}

	/**
	 * Subscribe feed: GET /wp-json/ds-toolkit/v1/calendar/<post>/<node>[?team=t:Name|p:Name]
	 * answers text/calendar for one placed calendar module, with that module's own settings
	 * (sites, types, teams, program dates). Read-only and public like the page itself: it
	 * serves the cached schedule (a stale copy queues the usual background refresh), so a
	 * calendar app polling it never adds LeagueApps requests beyond the hourly refresh.
	 */
	public function routes() {
		register_rest_route( 'ds-toolkit/v1', '/calendar/(?P<post>\\d{1,20})/(?P<node>[a-z0-9]{1,32})', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( $this, 'feed' ),
			'args'                => array( 'team' => array( 'type' => 'string', 'default' => '' ) ),
		) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_ics' ), 10, 3 );
	}

	/** The saved settings of a calendar module on a published, public post; null otherwise. */
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
		if ( ! is_object( $node ) || 'module' !== ( $node->type ?? '' ) || ! isset( $node->settings ) || ! is_object( $node->settings ) || 'ds-la-calendar' !== ( $node->settings->type ?? '' ) ) {
			return null;
		}
		return $node->settings;
	}

	public function feed( $request ) {
		$settings = self::node_settings( (int) $request['post'], (string) $request['node'] );
		if ( ! $settings || 'no' === ( $settings->sub_show ?? 'yes' ) || ! class_exists( 'DS_Calendar_Module' ) ) {
			return new WP_Error( 'ds_calendar_not_found', 'No calendar here.', array( 'status' => 404 ) );
		}
		$filter = (string) $request['team'];
		$filter = preg_match( '/^[tp]:.{1,200}$/s', $filter ) ? sanitize_text_field( $filter ) : '';
		$data   = DS_Calendar_Module::data_for( $settings );
		$name   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$name   = trim( $name . ( '' !== $filter ? ' (' . substr( $filter, 2 ) . ')' : '' ) );
		$ics    = DS_Calendar_ICS::build( (array) $data['events'], $filter, '' !== $name ? $name : 'Calendar', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$res    = new WP_REST_Response( $ics, 200 );
		$res->header( 'Content-Type', 'text/calendar; charset=utf-8' );
		$res->header( 'Content-Disposition', 'inline; filename="calendar.ics"' );
		$res->header( 'Cache-Control', 'public, max-age=1800' );
		$res->header( 'X-Robots-Tag', 'noindex' );
		return $res;
	}

	/** Send the feed as plain iCalendar text instead of a JSON string. */
	public function serve_ics( $served, $result, $request ) {
		if ( $served || ! ( $result instanceof WP_REST_Response ) || 200 !== $result->get_status() || ! is_string( $result->get_data() ) ) {
			return $served;
		}
		if ( 0 !== strpos( (string) $request->get_route(), '/ds-toolkit/v1/calendar/' ) ) {
			return $served;
		}
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput -- iCalendar text built and escaped by DS_Calendar_ICS
		return true;
	}

	public function flush() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'ds_programs_flush' );
		if ( false === get_transient( 'ds_programs_flush_throttle' ) ) {
			set_transient( 'ds_programs_flush_throttle', time(), MINUTE_IN_SECONDS );
			DS_Programs_Data::flush(); // fires ds_programs_flushed -> DS_Calendar_Data::flush
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public function admin_bar( $bar ) {
		if ( ! current_user_can( 'edit_posts' ) || is_admin() ) { return; }
		if ( ! DS_Programs_Data::configured_sites() ) { return; }
		$bar->add_node( array(
			'id'    => 'ds-programs-flush',
			'title' => __( 'Refresh LeagueApps listings', 'ds-toolkit' ),
			'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=ds_programs_flush' ), 'ds_programs_flush' ),
		) );
	}
}
