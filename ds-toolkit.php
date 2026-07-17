<?php
/**
 * Plugin Name:       DS Toolkit
 * Plugin URI:        https://github.com/agabriel1590/ds-toolkit
 * Description:       Design Shop custom features and build toolkit.
 * Version:           1.9.36
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

define( 'DS_TOOLKIT_VERSION', '1.9.36' );
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

require_once DS_TOOLKIT_PATH . 'includes/class-ds-toolkit.php';

register_activation_hook( __FILE__, array( 'DS_Toolkit', 'activate' ) );

add_action( 'plugins_loaded', function() {
    $plugin = new DS_Toolkit();
    $plugin->run();
} );
