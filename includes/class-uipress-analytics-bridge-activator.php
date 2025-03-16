<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Activator {

    /**
     * Initialize default settings during activation.
     *
     * @since    1.0.0
     */
    public static function activate() {
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
        
        // Set up cron job for cache clearing
        if (!wp_next_scheduled('uipress_analytics_bridge_clear_cache')) {
            wp_schedule_event(time(), 'daily', 'uipress_analytics_bridge_clear_cache');
        }
        
        // Ensure transient option exists for UIPress detection
        set_transient('uipress_analytics_bridge_dependencies_met', false, HOUR_IN_SECONDS);
        
        // Create notice about configuration
        set_transient('uipress_analytics_bridge_activation_notice', true, 30 * DAY_IN_SECONDS);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}