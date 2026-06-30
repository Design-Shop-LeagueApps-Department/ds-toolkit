<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Team Detail" Beaver Builder module
 * (modules/ds-team-detail/): the single-team body, built from the team post's
 * ACF fields, with the featured-image column dropped when there's no image and
 * each section hidden when its field is empty. Blueprint generation 6+.
 */
class DS_Team_Detail {
	private $settings;
	public function __construct( $settings = array() ) { $this->settings = $settings; }
	public function init() { add_action( 'init', array( $this, 'register_module' ), 20 ); }
	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-team-detail/ds-team-detail.php';
		}
	}
}
