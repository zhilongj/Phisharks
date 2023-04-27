<?php
/**
 * Enable CORS
 *
 * @package           EnableCORS
 * @author            Dev Kabir
 * @copyright         2023 Dev Kabir
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Enable CORS
 * Plugin URI:        https://www.fiverr.com/share/7kXeLW
 * Description:       Enable Cross-Origin Resource Sharing for any or specific origin.
 * Version:           1.0.2
 * Requires at least: 4.7
 * Requires PHP:      7.1
 * Author:            Dev Kabir
 * Author URI:        https://www.fiverr.com/developerkabir
 * Text Domain:       enable-cors
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * This is a security measure to prevent direct access to the plugin PHP files.
 */

use DevKabir\EnableCors\Plugin;

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Loading the classes from the vendor folder.
 */
require_once plugin_dir_path(__FILE__) . '/src/Plugin.php';
/**
 * Codes to run when the plugin is activated.
 */
register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

/**
 * Codes to run when the plugin is deactivated.
 */
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

/**
 * Codes to run when the plugin is uninstalled.
 */
register_uninstall_hook( __FILE__, array( Plugin::class, 'uninstall' ) );
/**
 * Kick-start the plugin.
 */
Plugin::init();

