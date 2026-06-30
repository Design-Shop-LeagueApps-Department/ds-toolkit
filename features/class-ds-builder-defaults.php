<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Builder UX default: open a node's settings on its FIRST (Content) tab instead
 * of letting Beaver Builder re-select the last-used tab. A partner editing a
 * module should land on Content, not Style. Builder UI only (no front-end cost).
 */
class DS_Builder_Defaults {
	private $settings;
	public function __construct( $settings = array() ) { $this->settings = $settings; }
	public function init() {
		add_action( 'fl_builder_ui_enqueue_scripts', array( $this, 'enqueue' ) );
	}
	public function enqueue() {
		wp_enqueue_script(
			'ds-builder-defaults',
			DS_TOOLKIT_URL . 'assets/builder-defaults.js',
			array( 'jquery' ),
			DS_TOOLKIT_VERSION,
			true
		);
	}
}
