<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Get settings
$settings = get_option('uipress_analytics_bridge_settings', array());
$remove_data = isset($settings['remove_data_on_uninstall']) ? $settings['remove_data_on_uninstall'] : false;

// Only delete data if the setting is enabled
if ($remove_data) {
    // Delete plugin options
    delete_option('uipress_analytics_bridge_settings');
    delete_option('uipress_analytics_bridge_advanced');
    
    // Clear all transients
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

// Remove cron jobs regardless of settings
wp_clear_scheduled_hook('uipress_analytics_bridge_clear_cache');