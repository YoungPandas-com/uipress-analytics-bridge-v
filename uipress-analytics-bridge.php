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

// Include the admin class
require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'admin/class-uipress-analytics-bridge-admin.php';

/**
 * Direct registration of admin page - guaranteed to work
 */
function uipress_analytics_bridge_admin_init() {
    // Initialize admin class
    $admin = new UIPress_Analytics_Bridge_Admin('uipress-analytics-bridge', UIPRESS_ANALYTICS_BRIDGE_VERSION);
    
    // Load admin styles and scripts
    add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
    add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));
    
    // Add settings link in plugins list
    add_filter('plugin_action_links_' . UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME, 
        array($admin, 'add_action_links')
    );
}

// Add activation notice
function uipress_analytics_bridge_activation_notice() {
    if (get_transient('uipress_analytics_bridge_activation_notice')) {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php _e('Thank you for installing UIPress Analytics Bridge!', 'uipress-analytics-bridge'); ?> 
                <a href="<?php echo admin_url('options-general.php?page=uipress-analytics-bridge'); ?>"><?php _e('Click here to configure the plugin settings', 'uipress-analytics-bridge'); ?></a>
            </p>
        </div>
        <?php
        delete_transient('uipress_analytics_bridge_activation_notice');
    }
}
add_action('admin_notices', 'uipress_analytics_bridge_activation_notice');

/**
 * The code that runs during plugin activation.
 */
function activate_uipress_analytics_bridge() {
    // Create default settings if they don't exist
    if (!get_option('uipress_analytics_bridge_settings')) {
        $default_settings = array(
            'client_id' => '',
            'client_secret' => '',
            'cache_duration' => 60, // 60 minutes default cache
            'remove_data_on_uninstall' => false,
        );
        
        update_option('uipress_analytics_bridge_settings', $default_settings);
    }
    
    // Create default advanced settings if they don't exist
    if (!get_option('uipress_analytics_bridge_advanced')) {
        $default_advanced = array(
            'debug_mode' => false,
            'compatibility_mode' => false,
            'clear_cache_cron' => true,
        );
        
        update_option('uipress_analytics_bridge_advanced', $default_advanced);
    }
    
    // Set transient for activation notice
    set_transient('uipress_analytics_bridge_activation_notice', true, DAY_IN_SECONDS * 3);
}

register_activation_hook(__FILE__, 'activate_uipress_analytics_bridge');

// Initialize admin functionality
uipress_analytics_bridge_admin_init();