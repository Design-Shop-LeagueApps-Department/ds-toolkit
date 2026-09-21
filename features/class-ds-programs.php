<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "LeagueApps Programs" Beaver Builder module
 * (modules/ds-programs/) and its cache controls.
 *
 * Opt-in on every blueprint: it is a data integration, not a design block,
 * and it renders nothing for visitors until a LeagueApps site is configured
 * under Settings > DS Toolkit > LeagueApps.
 */
class DS_Programs {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
		add_action( 'init', array( $this, 'register_module' ), 20 );
		add_action( 'admin_post_ds_programs_flush', array( $this, 'flush' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-programs/ds-programs.php';
		}
	}

	/** "Refresh LeagueApps listings": drop the fresh cache without waiting ten minutes. */
	public function flush() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'ds_programs_flush' );
		DS_Programs_Data::flush();
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public function admin_bar( $bar ) {
		if ( ! current_user_can( 'edit_posts' ) || is_admin() ) { return; }
		if ( ! DS_Programs_Data::configured_sites() ) { return; }
		$bar->add_node( array(
			'id'    => 'ds-programs-flush',
			'title' => __( 'Refresh LeagueApps listings', 'ds-toolkit' ),
			'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=ds_programs_flush' ), 'ds_programs_flush' ),
		) );
	}
}
