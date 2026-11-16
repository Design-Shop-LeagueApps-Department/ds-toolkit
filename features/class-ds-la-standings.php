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
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-la-standings/ds-la-standings.php';
		}
	}
}
