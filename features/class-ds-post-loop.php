<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Post Loop" Beaver Builder module
 * (modules/ds-post-loop/) — a query-driven loop (News today; Staff / Team /
 * Athletes presets to follow). Blueprint generation 6+.
 *
 * Formerly "Leagueapps News" (module slug ds-news); renamed 2026-06-21.
 */
class DS_Post_Loop {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'init', array( $this, 'register_module' ), 20 );
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-post-loop/ds-post-loop.php';
		}
	}
}
