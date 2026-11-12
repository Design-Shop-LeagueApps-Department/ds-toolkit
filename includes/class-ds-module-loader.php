<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers one in-house Beaver Builder block: modules/<slug>/<slug>.php, once Beaver Builder is loaded.
 *
 * Fourteen blocks each had a feature class of their own that did exactly this and nothing else (18 to 28
 * lines apiece, the same but for the folder name). A block that needs more than registering (Programs,
 * Calendar, Roster, Hero, Page Cards, Content Router) keeps its own feature class. Which blocks use this
 * loader is DS_Features::GENERIC_BLOCKS.
 */
if ( ! class_exists( 'DS_Module_Loader' ) ) :
class DS_Module_Loader {

	/** Folder and file name of the block, e.g. "ds-heading". */
	private $slug;

	public function __construct( $slug ) {
		// A folder name only: the value comes from the schema, and this keeps it from ever being a path.
		$this->slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $slug ) );
	}

	public function init() {
		// Priority 20: after Beaver Builder has loaded its own modules on init.
		add_action( 'init', array( $this, 'register_module' ), 20 );
	}

	public function register_module() {
		if ( '' === $this->slug || ! class_exists( 'FLBuilder' ) || ! class_exists( 'FLBuilderModule' ) ) {
			return;
		}
		$file = DS_TOOLKIT_PATH . 'modules/' . $this->slug . '/' . $this->slug . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
}
endif;
