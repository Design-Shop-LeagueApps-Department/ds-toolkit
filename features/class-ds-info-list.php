<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Info List" Beaver Builder module
 * (modules/ds-info-list/): icon + text + link rows bound to ACF fields on the
 * current post, with empty rows auto-hidden. Blueprint generation 6+.
 */
class DS_Info_List {
	private $settings;
	public function __construct( $settings = array() ) { $this->settings = $settings; }
	public function init() { add_action( 'init', array( $this, 'register_module' ), 20 ); }
	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-info-list/ds-info-list.php';
		}
	}
}
