<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Deactivator {

    /**
     * Clean up during deactivation.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear any scheduled cron jobs
        wp_clear_scheduled_hook('uipress_analytics_bridge_clear_cache');
        
        // Clear transients
        delete_transient('uipress_analytics_bridge_dependencies_met');
        delete_transient('uipress_analytics_bridge_activation_notice');
        
        // Clear all analytics caches
        self::clear_all_caches();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Clear all analytics caches.
     *
     * @since    1.0.0
     */
    private static function clear_all_caches() {
        global $wpdb;
        
        // Get all transients with our prefix
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_uipress_analytics_bridge_') . '%'
            )
        );
        
        // Delete each transient
        if (!empty($transients)) {
            foreach ($transients as $transient) {
                $transient_name = str_replace('_transient_', '', $transient);
                delete_transient($transient_name);
            }
        }
    }
}