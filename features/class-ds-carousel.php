<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Image Carousel" Beaver Builder module
 * (modules/ds-carousel/). Blueprint generation 6+.
 */
class DS_Carousel {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'init', array( $this, 'register_module' ), 20 );
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-carousel/ds-carousel.php';
		}
	}
}
