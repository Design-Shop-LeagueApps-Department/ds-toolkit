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
		// After the blueprint's CPTs have registered (they use the default init
		// priority), attach the Gender taxonomy to the events post type.
		add_action( 'init', array( $this, 'register_event_gender_taxonomy' ), 19 );
	}

	/**
	 * A REAL, partner-editable Gender taxonomy for the events CPT. Terms are
	 * managed in the normal WP UI (Events → Gender) and ticked on each event's
	 * edit screen — no code, no field-group editing. The Tournament Event Card
	 * filter tabs (and any future facet) can then point at it like any other
	 * taxonomy. Only registers where an `event` post type exists, and never
	 * fights a taxonomy another plugin already registered under this name.
	 */
	public function register_event_gender_taxonomy() {
		if ( ! post_type_exists( 'event' ) || taxonomy_exists( 'event_gender' ) ) {
			return;
		}
		register_taxonomy( 'event_gender', array( 'event' ), array(
			'labels'            => array(
				'name'          => __( 'Genders', 'ds-toolkit' ),
				'singular_name' => __( 'Gender', 'ds-toolkit' ),
				'menu_name'     => __( 'Gender', 'ds-toolkit' ),
				'add_new_item'  => __( 'Add New Gender', 'ds-toolkit' ),
			),
			'public'            => true,
			'publicly_queryable' => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => true, // checkbox metabox on the event edit screen
			'rewrite'           => false,
		) );
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-post-loop/ds-post-loop.php';
		}
	}
}
