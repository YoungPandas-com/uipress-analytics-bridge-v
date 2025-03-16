<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Deactivator {

    /**
     * Clean up on plugin deactivation.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear the analytics data cache
        self::clear_cache();
        
        // Clear the detection transient
        delete_transient('uipress_analytics_bridge_detection');
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
}