<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Page Cards" Beaver Builder module
 * (modules/ds-page-cards/): the module version of the [child_pages] shortcode —
 * lists a page's child pages as cards, each rendered with a saved layout.
 * Blueprint generation 6+.
 */
class DS_Page_Cards {
	private $settings;
	public function __construct( $settings = array() ) { $this->settings = $settings; }
	public function init() { add_action( 'init', array( $this, 'register_module' ), 20 ); }
	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-page-cards/ds-page-cards.php';
		}
	}
}
