<?php
/**
 * UIPress Analytics Bridge
 *
 * @package     UIPress_Analytics_Bridge
 * @author      Young Pandas
 * @copyright   2025 Young Pandas
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: UIPress Analytics Bridge
 * Plugin URI:  https://yp.studio
 * Description: Enhanced Google Analytics integration for UIPress Pro with improved authentication and data retrieval.
 * Version:     1.0.0
 * Author:      Young Pandas
 * Author URI:  https://yp.studio
 * Text Domain: uipress-analytics-bridge
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UIPRESS_ANALYTICS_BRIDGE_VERSION', '1.0.0');
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_NAME', 'UIPress Analytics Bridge');
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
 * Debug mode setup function
 */
function uipress_analytics_bridge_debug_mode() {
    $advanced_settings = get_option('uip_analytics_bridge_advanced', array());
    
    // Only enable if debug mode is specifically set in options
    if (isset($advanced_settings['debug_mode']) && $advanced_settings['debug_mode']) {
        // Enable WordPress debug mode
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        
        // Log errors but don't display them
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }
        
        if (!defined('WP_DEBUG_DISPLAY')) {
            define('WP_DEBUG_DISPLAY', false);
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
 * Check if UIPress Lite and Pro are active before loading our plugin
 */
function uipress_analytics_bridge_check_dependencies() {
    // Using transient for better performance
    $dependencies_met = get_transient('uipress_analytics_bridge_dependencies_met');
    
    if (false === $dependencies_met) {
        $plugin_messages = array();
        $plugin_dependencies_met = true;
        
        // Check for UIPress Lite
        if (!defined('uip_plugin_version')) {
            $plugin_dependencies_met = false;
            $plugin_messages[] = __('UIPress Lite is required for full functionality of UIPress Analytics Bridge.', 'uipress-analytics-bridge');
        }
        
        // Check for UIPress Pro
        if (!defined('uip_pro_plugin_version')) {
            $plugin_dependencies_met = false;
            $plugin_messages[] = __('UIPress Pro is required for full functionality of UIPress Analytics Bridge.', 'uipress-analytics-bridge');
        }
        
        // Store the result in a transient (cache for 1 hour)
        set_transient('uipress_analytics_bridge_dependencies_met', $plugin_dependencies_met, HOUR_IN_SECONDS);
        update_option('uipress_analytics_bridge_dependency_messages', $plugin_messages);
        
        return $plugin_dependencies_met;
    }
    
    return $dependencies_met;
}

/**
 * Display admin notice for missing dependencies
 */
function uipress_analytics_bridge_dependency_notice() {
    $dependency_messages = get_option('uipress_analytics_bridge_dependency_messages', array());
    
    if (!empty($dependency_messages)) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . __('UIPress Analytics Bridge - Missing Dependencies', 'uipress-analytics-bridge') . '</strong></p>';
        echo '<ul>';
        
        foreach ($dependency_messages as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
}

/**
 * Begin execution of the plugin.
 *
 * This function is called using 'plugins_loaded' hook to ensure
 * it executes at the correct time within WordPress loading sequence.
 */
function run_uipress_analytics_bridge() {
    // Set up debug mode if needed
    uipress_analytics_bridge_debug_mode();
    
    // Check dependencies (but continue loading for admin settings)
    $dependencies_met = uipress_analytics_bridge_check_dependencies();
    if (!$dependencies_met) {
        add_action('admin_notices', 'uipress_analytics_bridge_dependency_notice');
    }
    
    // Required plugin class file
    require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge.php';
    
    // Execute the plugin (always run for admin settings)
    $plugin = new UIPress_Analytics_Bridge();
    $plugin->run();
}

// Hook into WordPress to run our plugin
add_action('plugins_loaded', 'run_uipress_analytics_bridge');