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
		add_action( 'init', array( $this, 'register_module' ), 20 );
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
