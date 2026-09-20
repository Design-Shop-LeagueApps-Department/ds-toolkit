<?php
/**
 * Plugin Name:       DS Toolkit
 * Plugin URI:        https://github.com/agabriel1590/ds-toolkit
 * Description:       Design Shop custom features and build toolkit.
 * Version:           1.9.140
 * Author:            Alipio Gabriel
 * Author URI:        https://github.com/agabriel1590
 * Text Domain:       ds-toolkit
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Tested up to:      6.7
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// A second copy of this plugin is already loaded (a GitHub "Download ZIP" install lands in
// plugins/ds-toolkit-main/ and sorts before this folder). Loading twice would inherit the
// other copy's DS_TOOLKIT_PATH and require files through the wrong tree, which fataled
// every request on leadingee for ten days (2026-09-05). Bail with a notice instead.
if ( defined( 'DS_TOOLKIT_VERSION' ) ) {
    add_action( 'admin_notices', function () {
        if ( ! current_user_can( 'activate_plugins' ) ) { return; }
        echo '<div class="notice notice-error"><p><strong>DS Toolkit:</strong> two copies are active ('
            . esc_html( basename( rtrim( DS_TOOLKIT_PATH, '/\\' ) ) ) . ' and ' . esc_html( basename( __DIR__ ) )
            . '). Only the first one is running. Deactivate and delete the stale copy.</p></div>';
    } );
    return;
}

define( 'DS_TOOLKIT_VERSION', '1.9.140' );
define( 'DS_TOOLKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'DS_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );

/**
 * The email domain that gates access to destructive / schema-level MCP tools
 * and the DS Toolkit admin menu. Override in wp-config.php if needed:
 *   define( 'DS_TOOLKIT_ADMIN_DOMAIN', '@yourdomain.com' );
 */
if ( ! defined( 'DS_TOOLKIT_ADMIN_DOMAIN' ) ) {
    define( 'DS_TOOLKIT_ADMIN_DOMAIN', '@leagueapps.com' );
}

// Shared module helpers (CSS/colour/unit/button helpers + card/people markup
// + SVG icon registry), loaded once so every in-house module can reuse them.
require_once DS_TOOLKIT_PATH . 'includes/class-ds-module-ui.php';
require_once DS_TOOLKIT_PATH . 'includes/class-ds-card.php';
require_once DS_TOOLKIT_PATH . 'includes/class-ds-program-cards.php';
if ( ( defined( 'WP_CLI' ) && WP_CLI ) || is_admin() ) {
	require_once DS_TOOLKIT_PATH . 'includes/class-ds-program-cards-migrator.php';
}

require_once DS_TOOLKIT_PATH . 'includes/class-ds-toolkit.php';

register_activation_hook( __FILE__, array( 'DS_Toolkit', 'activate' ) );

add_action( 'plugins_loaded', function() {
    $plugin = new DS_Toolkit();
    $plugin->run();
} );
