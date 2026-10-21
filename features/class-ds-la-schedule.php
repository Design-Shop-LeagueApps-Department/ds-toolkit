<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * DEPRECATED since 1.10.44: not loaded (see the note in DS_Toolkit's feature list). Kept so the
 * module can be brought back without rebuilding it.
 *
 * Registers the in-house "LeagueApps Team Schedule" Beaver Builder module
 * (modules/ds-la-schedule/): one team's or program's LeagueApps schedule as a table
 * or a compact "next up" list, for team pages.
 *
 * Opt-in on every blueprint like the other LeagueApps modules. Public data: it uses the
 * site ID and API key on the LeagueApps tab, and asks LeagueApps about the one program a
 * module shows (DS_Schedule_Data), never the whole club.
 */
class DS_LA_Schedule {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-schedule-data.php';
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-la-colors.php';
		add_action( 'init', array( $this, 'register_module' ), 20 );
		add_action( 'ds_programs_flushed', array( 'DS_Schedule_Data', 'flush' ) );
		add_action( 'ds_la_schedule_refresh', array( 'DS_Schedule_Data', 'refresh' ) ); // background refresh queued by items()
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-la-schedule/ds-la-schedule.php';
		}
	}
}
