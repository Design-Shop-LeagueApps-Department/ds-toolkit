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
		add_filter( 'fl_builder_module_categories', array( $this, 'category_order' ) );
	}

	/**
	 * Put the LeagueApps modules directly under "Basic" in the module panel.
	 *
	 * Beaver Builder builds its category list as: everything returned by this
	 * filter FIRST, then its own defaults (Basic, Box, Media, …). A category that
	 * is never declared here is appended when a module claims it — which is why
	 * "LeagueApps" was landing dead last, below every UABB category, and editors
	 * had to scroll past ~80 third-party modules to reach ours.
	 *
	 * Returning "Basic" as well is the trick that lands us SECOND rather than
	 * first: re-assigning a key that already exists does not move it in a PHP
	 * array, so BB's later `$categories['Basic']` line is a no-op on position and
	 * Basic keeps slot 1 with LeagueApps immediately after it.
	 *
	 * Any category another plugin registered through this filter is preserved,
	 * just after ours.
	 *
	 * @param array $categories Custom category names.
	 * @return array
	 */
	public function category_order( $categories ) {
		$categories = is_array( $categories ) ? $categories : array();
		$ours       = array( __( 'Basic', 'fl-builder' ), 'LeagueApps' );

		return array_values( array_unique( array_merge( $ours, $categories ) ) );
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
