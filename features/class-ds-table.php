<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Table" Beaver Builder module (modules/ds-table/):
 * a table typed in the builder, uploaded as a CSV, or synced from a CSV file or a
 * Google Sheet link. Blueprint 6+, on by default there.
 */
class DS_Table {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'init', array( $this, 'register_module' ), 20 );
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-table/ds-table.php';
		}
	}
}
