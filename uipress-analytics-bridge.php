<?php
/**
 * UIPress Analytics Bridge
 *
 * @package           UIPress_Analytics_Bridge
 * @author            Young Pandas
 * @copyright         2025 Young Pandas
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       UIPress Analytics Bridge
 * Plugin URI:        https://yp.studio
 * Description:       Enhanced Google Analytics integration for UIPress Pro with improved authentication and reliability.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Young Pandas
 * Author URI:        https://yp.studio
 * Text Domain:       uipress-analytics-bridge
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UIPRESS_ANALYTICS_BRIDGE_VERSION', '1.0.0');
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_NAME', 'uipress-analytics-bridge');
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if debug mode is enabled before loading the plugin.
 */
function uipress_analytics_bridge_debug_mode() {
    $advanced_settings = get_option('uipress_analytics_bridge_advanced', array());
    
    // Only enable if debug mode is specifically set in options
    if (isset($advanced_settings['debug_mode']) && $advanced_settings['debug_mode']) {
        // Log errors but don't display them
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }
        
        // Set error reporting level
        error_reporting(E_ALL);
        
        // Custom error handler for plugin-specific errors
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // Only handle errors in our plugin files
            if (strpos($errfile, 'uipress-analytics-bridge') === false) {
                return false;
            }
            
            // Log specific error information
            error_log(sprintf(
                'UIPress Analytics Bridge Error [%s]: %s in %s on line %d',
                $errno,
                $errstr,
                $errfile,
                $errline
            ));
            
            return false; // Let default handler run
        }, E_ALL);
    }
}

/**
 * The code that runs during plugin activation.
 */
function activate_uipress_analytics_bridge() {
    require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-activator.php';
    UIPress_Analytics_Bridge_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_uipress_analytics_bridge() {
    require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-deactivator.php';
    UIPress_Analytics_Bridge_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_uipress_analytics_bridge');
register_deactivation_hook(__FILE__, 'deactivate_uipress_analytics_bridge');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge.php';

/**
 * Begins execution of the plugin.
 * 
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_uipress_analytics_bridge() {
    // Initialize debug mode if needed
    uipress_analytics_bridge_debug_mode();
    
    // Load and run the plugin
    $plugin = new UIPress_Analytics_Bridge();
    $plugin->run();
}

// Use plugins_loaded to ensure WordPress is properly initialized
add_action('plugins_loaded', 'run_uipress_analytics_bridge');