<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Adds quick links to the WP admin toolbar for LeagueApps users:
 * "Theme Setting" (the DS Theme Setting page) and, beside it, "DS Toolkit"
 * (the plugin settings page). Shown in wp-admin AND on the front end, so
 * either page is one click away while reviewing a site. Each link only
 * renders for users who can actually open its page (capability + the
 * LeagueApps email gate), so partners never see dead links.
 */
class DS_Admin_Bar_Links {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		// HARD LOCK: every toolbar customisation in this class (links, declutter,
		// reorder) is Launchpad 6+ ONLY. The feature registry already gates this
		// via min_blueprint => 6; this guard makes the lock explicit even if the
		// settings key were ever set on an older install.
		if ( DS_Toolkit::blueprint_version() < 6 ) {
			return;
		}
		add_action( 'admin_bar_menu', array( $this, 'add_links' ), 82 );
		add_action( 'admin_bar_menu', array( $this, 'remove_noise' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'reorder' ), 2000 );
	}

	/**
	 * Groups the content actions side by side: + New, Edit Page, Beaver Builder,
	 * then Theme Setting and DS Toolkit (other nodes, e.g. Yoast, move after).
	 * Works by removing and re-adding nodes in the desired order — the admin bar
	 * renders root nodes in insertion order.
	 *
	 * @param WP_Admin_Bar $bar
	 */
	public function reorder( $bar ) {
		// Final order after the site name: Theme Setting, DS Toolkit, (Yoast…),
		// then the CONTENT ACTIONS grouped at the very end of the bar:
		// + New · Edit Page · Beaver Builder.
		$sequence = array( 'ds-theme-setting', 'ds-toolkit-settings', 'wpseo-menu', 'new-content', 'edit', 'fl-builder-frontend-edit-link' );
		$nodes    = array();
		foreach ( $sequence as $nid ) {
			$n = $bar->get_node( $nid );
			if ( $n ) { $nodes[ $nid ] = $n; $bar->remove_node( $nid ); }
		}
		foreach ( $sequence as $nid ) {
			if ( isset( $nodes[ $nid ] ) ) { $bar->add_node( (array) $nodes[ $nid ] ); }
		}
	}

	/**
	 * Declutters the toolbar for everyone: the theme Customizer link (the fleet
	 * styles through Theme Setting / Beaver Builder, not the Customizer), the
	 * plugin-updates counter (updates are managed centrally / auto-update), and
	 * the WP Engine Quick Links host menu. Capabilities are untouched — the
	 * screens themselves stay reachable directly.
	 *
	 * @param WP_Admin_Bar $bar
	 */
	public function remove_noise( $bar ) {
		$bar->remove_node( 'customize' );
		$bar->remove_node( 'updates' );
		$bar->remove_node( 'wpengine_adminbar' ); // WP Engine "Quick Links" (mu-plugin)
	}

	/** @param WP_Admin_Bar $bar */
	public function add_links( $bar ) {
		if ( ! DS_Toolkit::is_leagueapps_user() ) {
			return;
		}

		// Theme Setting — only when the feature is on (the page exists) and the
		// user clears its gate (edit_posts).
		if ( ! empty( $this->settings['theme_setting_enabled'] ) && current_user_can( 'edit_posts' ) ) {
			$slug = class_exists( 'DS_Theme_Setting' ) ? DS_Theme_Setting::PAGE_SLUG : 'ds-theme-setting';
			$bar->add_node( array(
				'id'    => 'ds-theme-setting',
				'title' => 'Theme Setting',
				'href'  => admin_url( 'admin.php?page=' . $slug ),
			) );
		}

		// DS Toolkit settings — admins only (matches the page's own gate).
		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_node( array(
				'id'    => 'ds-toolkit-settings',
				'title' => 'DS Toolkit',
				'href'  => admin_url( 'options-general.php?page=ds-toolkit' ),
			) );
		}
	}
}
