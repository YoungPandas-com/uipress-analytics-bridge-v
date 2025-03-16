<?php
/**
 * Fired during plugin activation.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Activator {

    /**
     * Initialize default settings on plugin activation.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Set default settings if they don't exist
        if (!get_option('uipress_analytics_bridge_settings')) {
            update_option('uipress_analytics_bridge_settings', array(
                'client_id' => '',
                'client_secret' => '',
                'date_range' => '30',
            ));
        }
        
        // Set default advanced settings if they don't exist
        if (!get_option('uipress_analytics_bridge_advanced')) {
            update_option('uipress_analytics_bridge_advanced', array(
                'cache_expiration' => '3600',
                'debug_mode' => false,
            ));
        }
        
        // Clear any existing cache on activation
        self::clear_cache();
        
        // Check for UIPress Lite and Pro
        self::check_uipress();
    }

    /**
     * Clear the analytics data cache.
     *
     * @since    1.0.0
     */
    private static function clear_cache() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_uipress_analytics_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_uipress_analytics_%'");
    }

    /**
     * Check for UIPress Lite and Pro on activation.
     *
     * @since    1.0.0
     */
    private static function check_uipress() {
        $uipress_lite_active = defined('uip_plugin_version');
        $uipress_pro_active = defined('uip_pro_plugin_version');
        
        // Store the status in a transient for quick access
        set_transient('uipress_analytics_bridge_detection', array(
            'lite_active' => $uipress_lite_active,
            'pro_active' => $uipress_pro_active,
            'lite_version' => $uipress_lite_active ? uip_plugin_version : null,
            'pro_version' => $uipress_pro_active ? uip_pro_plugin_version : null,
        ), DAY_IN_SECONDS);
    }
}