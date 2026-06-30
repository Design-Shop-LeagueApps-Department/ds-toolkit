<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Heading" Beaver Builder module
 * (modules/ds-heading/). Blueprint generation 6+.
 */
class DS_Heading {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'init', array( $this, 'register_module' ), 20 );
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			$module = DS_TOOLKIT_PATH . 'modules/ds-heading/ds-heading.php';
			if ( file_exists( $module ) ) {
				require_once $module;
			}
		}
	}
}
