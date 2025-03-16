<?php
/**
 * WordPress-side authentication handling.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Auth {

    /**
     * The API auth handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_API_Auth    $api_auth    Handles Google API authentication.
     */
    private $api_auth;

    /**
     * The API data handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_API_Data    $api_data    Handles Google API data retrieval.
     */
    private $api_data;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    UIPress_Analytics_Bridge_API_Auth    $api_auth    The API auth handler instance.
     * @param    UIPress_Analytics_Bridge_API_Data    $api_data    The API data handler instance.
     */
    public function __construct($api_auth, $api_data) {
        $this->api_auth = $api_auth;
        $this->api_data = $api_data;
    }

    /**
     * Get the analytics authentication data.
     *
     * @since    1.0.0
     * @param    string    $save_to_user    Whether to save to user preferences.
     * @return   array     The analytics authentication data.
     */
    public function get_analytics_data($save_to_user = 'false') {
        // Determine where to get the data from based on the saveAccountToUser parameter
        $use_user_preferences = ($save_to_user === 'true');
        
        // Get authentication data from the appropriate location
        if ($use_user_preferences) {
            $analytics_data = $this->get_user_analytics_data();
        } else {
            $analytics_data = $this->get_site_analytics_data();
        }
        
        // If no data exists, return empty default structure
        if (empty($analytics_data)) {
            return array(
                'view' => '',
                'code' => '',
                'token' => '',
                'measurement_id' => '',
                'connected' => false,
            );
        }
        
        // Ensure the connected flag is set
        if (!isset($analytics_data['connected'])) {
            $analytics_data['connected'] = !empty($analytics_data['code']) && !empty($analytics_data['view']);
        }
        
        return $analytics_data;
    }

    /**
     * Get analytics data from user preferences.
     *
     * @since    1.0.0
     * @return   array    The analytics data from user preferences.
     */
    private function get_user_analytics_data() {
        $user_id = get_current_user_id();
        $user_prefs = get_user_meta($user_id, 'uip-prefs', true);
        
        if (!is_array($user_prefs) || !isset($user_prefs['google_analytics'])) {
            return $this->get_plugin_auth_data('user');
        }
        
        return $user_prefs['google_analytics'];
    }

    /**
     * Get analytics data from site options.
     *
     * @since    1.0.0
     * @return   array    The analytics data from site options.
     */
    private function get_site_analytics_data() {
        $uip_settings = get_option('uip-global-settings');
        
        if (!is_array($uip_settings) || !isset($uip_settings['google_analytics'])) {
            return $this->get_plugin_auth_data('site');
        }
        
        return $uip_settings['google_analytics'];
    }

    /**
     * Get authentication data stored by this plugin.
     *
     * @since    1.0.0
     * @param    string    $context    The context ('user' or 'site').
     * @return   array     The authentication data.
     */
    private function get_plugin_auth_data($context = 'site') {
        if ($context === 'user') {
            $auth_data = get_user_meta(get_current_user_id(), 'uipress_analytics_bridge_auth', true);
        } else {
            $auth_data = get_option('uipress_analytics_bridge_auth');
        }
        
        if (!is_array($auth_data)) {
            return array();
        }
        
        return $auth_data;
    }

    /**
     * Update analytics authentication data.
     *
     * @since    1.0.0
     * @param    array     $data          The authentication data to update.
     * @param    string    $save_to_user  Whether to save to user preferences.
     * @return   bool      Whether the update was successful.
     */
    public function update_analytics_data($data, $save_to_user = 'false') {
        // Ensure data is an array
        if (!is_array($data)) {
            return false;
        }
        
        // Add connected flag
        $data['connected'] = !empty($data['code']) && !empty($data['view']);
        
        // Determine where to save the data
        $use_user_preferences = ($save_to_user === 'true');
        
        // Save to the appropriate location
        if ($use_user_preferences) {
            return $this->update_user_analytics_data($data);
        } else {
            return $this->update_site_analytics_data($data);
        }
    }

    /**
     * Update analytics data in user preferences.
     *
     * @since    1.0.0
     * @param    array    $data    The analytics data to update.
     * @return   bool     Whether the update was successful.
     */
    private function update_user_analytics_data($data) {
        $user_id = get_current_user_id();
        $user_prefs = get_user_meta($user_id, 'uip-prefs', true);
        
        if (!is_array($user_prefs)) {
            $user_prefs = array();
        }
        
        $user_prefs['google_analytics'] = $data;
        
        // Also store in our plugin's user meta for backup
        update_user_meta($user_id, 'uipress_analytics_bridge_auth', $data);
        
        return update_user_meta($user_id, 'uip-prefs', $user_prefs);
    }

    /**
     * Update analytics data in site options.
     *
     * @since    1.0.0
     * @param    array    $data    The analytics data to update.
     * @return   bool     Whether the update was successful.
     */
    private function update_site_analytics_data($data) {
        $uip_settings = get_option('uip-global-settings');
        
        if (!is_array($uip_settings)) {
            $uip_settings = array();
        }
        
        $uip_settings['google_analytics'] = $data;
        
        // Also store in our plugin's option for backup
        update_option('uipress_analytics_bridge_auth', $data);
        
        return update_option('uip-global-settings', $uip_settings);
    }

    /**
     * Handle the authentication callback from Google OAuth.
     *
     * @since    1.0.0
     */
    public function handle_auth_callback() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'security');
        
        // Get the authorization code from the request
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        if (empty($code)) {
            wp_send_json_error(array(
                'message' => __('Authorization code is missing.', 'uipress-analytics-bridge'),
            ));
        }
        
        // Exchange the authorization code for access token
        $token_data = $this->api_auth->exchange_code_for_token($code);
        
        if (is_wp_error($token_data)) {
            wp_send_json_error(array(
                'message' => $token_data->get_error_message(),
            ));
        }
        
        // Get the user's analytics properties
        $properties = $this->api_data->get_analytics_properties($token_data['access_token']);
        
        if (is_wp_error($properties)) {
            wp_send_json_error(array(
                'message' => $properties->get_error_message(),
            ));
        }
        
        // Update the authentication data
        $auth_data = array(
            'code' => $code,
            'token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'],
            'token_expires' => time() + $token_data['expires_in'],
            'properties' => $properties,
            'view' => isset($properties[0]['id']) ? $properties[0]['id'] : '',
            'measurement_id' => isset($properties[0]['measurement_id']) ? $properties[0]['measurement_id'] : '',
            'connected' => true,
        );
        
        $this->update_analytics_data($auth_data, $save_to_user);
        
        // Return success response
        wp_send_json_success(array(
            'message' => __('Successfully authenticated with Google Analytics.', 'uipress-analytics-bridge'),
            'properties' => $properties,
        ));
    }

    /**
     * Filter the analytics status option.
     *
     * @since    1.0.0
     * @param    mixed    $value    The value of the option.
     * @return   mixed    The filtered value.
     */
    public function filter_analytics_status($value) {
        // Check if we have valid analytics data
        $analytics_data = $this->get_site_analytics_data();
        
        if (!empty($analytics_data) && isset($analytics_data['connected']) && $analytics_data['connected']) {
            return 'connected';
        }
        
        return $value;
    }

    /**
     * Intercept the UIPress Pro save_account AJAX action.
     *
     * @since    1.0.0
     */
    public function intercept_save_account() {
        // Verify nonce
        if (!check_ajax_referer('uip-security-nonce', 'security', false)) {
            wp_send_json(array(
                'error' => true,
                'message' => __('Security check failed', 'uipress-analytics-bridge'),
            ));
        }
        
        // Get the analytics data from the request
        $analytics_data = isset($_POST['analytics']) ? json_decode(stripslashes($_POST['analytics']), true) : array();
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        if (!is_array($analytics_data)) {
            wp_send_json(array(
                'error' => true,
                'message' => __('Invalid analytics data', 'uipress-analytics-bridge'),
            ));
        }
        
        // Clean the analytics data
        $cleaned_data = array();
        
        if (isset($analytics_data['view'])) {
            $cleaned_data['view'] = sanitize_text_field($analytics_data['view']);
        }
        
        if (isset($analytics_data['code'])) {
            $cleaned_data['code'] = sanitize_text_field($analytics_data['code']);
        }
        
        if (isset($analytics_data['measurement_id'])) {
            $cleaned_data['measurement_id'] = sanitize_text_field($analytics_data['measurement_id']);
        }
        
        // Update the analytics data
        $this->update_analytics_data($cleaned_data, $save_to_user);
        
        // Return success response
        wp_send_json(array(
            'success' => true,
        ));
    }

    /**
     * Intercept the UIPress Pro save_access_token AJAX action.
     *
     * @since    1.0.0
     */
    public function intercept_save_access_token() {
        // Verify nonce
        if (!check_ajax_referer('uip-security-nonce', 'security', false)) {
            wp_send_json(array(
                'error' => true,
                'message' => __('Security check failed', 'uipress-analytics-bridge'),
            ));
        }
        
        // Get the token from the request
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        if (empty($token)) {
            wp_send_json(array(
                'error' => true,
                'message' => __('Token is missing', 'uipress-analytics-bridge'),
            ));
        }
        
        // Get the current analytics data
        $analytics_data = $this->get_analytics_data($save_to_user);
        
        // Update the token
        $analytics_data['token'] = $token;
        
        // Update the analytics data
        $this->update_analytics_data($analytics_data, $save_to_user);
        
        // Return success response
        wp_send_json(array(
            'success' => true,
        ));
    }

    /**
     * Intercept the UIPress Pro remove_account AJAX action.
     *
     * @since    1.0.0
     */
    public function intercept_remove_account() {
        // Verify nonce
        if (!check_ajax_referer('uip-security-nonce', 'security', false)) {
            wp_send_json(array(
                'error' => true,
                'message' => __('Security check failed', 'uipress-analytics-bridge'),
            ));
        }
        
        // Get the save_to_user parameter
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        // Get the current analytics data
        $analytics_data = $this->get_analytics_data($save_to_user);
        
        // Remove the authentication data
        $analytics_data['view'] = false;
        $analytics_data['code'] = false;
        $analytics_data['token'] = false;
        $analytics_data['measurement_id'] = false;
        $analytics_data['connected'] = false;
        
        // Update the analytics data
        $this->update_analytics_data($analytics_data, $save_to_user);
        
        // Return success response
        wp_send_json(array(
            'success' => true,
        ));
    }

    /**
     * Generate the authorization URL for Google OAuth.
     *
     * @since    1.0.0
     * @return   string    The authorization URL.
     */
    public function get_auth_url() {
        return $this->api_auth->get_auth_url();
    }

    /**
     * Check if the user is authenticated with Google Analytics.
     *
     * @since    1.0.0
     * @param    string    $save_to_user    Whether to check user preferences.
     * @return   bool      Whether the user is authenticated.
     */
    public function is_authenticated($save_to_user = 'false') {
        $analytics_data = $this->get_analytics_data($save_to_user);
        
        return isset($analytics_data['connected']) && $analytics_data['connected'];
    }

    /**
     * Get the analytics properties for the authenticated user.
     *
     * @since    1.0.0
     * @param    string    $save_to_user    Whether to check user preferences.
     * @return   array     The analytics properties.
     */
    public function get_analytics_properties($save_to_user = 'false') {
        $analytics_data = $this->get_analytics_data($save_to_user);
        
        if (!isset($analytics_data['token']) || empty($analytics_data['token'])) {
            return array();
        }
        
        // Check if we need to refresh the token
        if (isset($analytics_data['token_expires']) && $analytics_data['token_expires'] < time()) {
            // Refresh the token
            if (isset($analytics_data['refresh_token'])) {
                $token_data = $this->api_auth->refresh_access_token($analytics_data['refresh_token']);
                
                if (!is_wp_error($token_data)) {
                    $analytics_data['token'] = $token_data['access_token'];
                    $analytics_data['token_expires'] = time() + $token_data['expires_in'];
                    
                    $this->update_analytics_data($analytics_data, $save_to_user);
                }
            }
        }
        
        // Get the properties from the API
        $properties = $this->api_data->get_analytics_properties($analytics_data['token']);
        
        if (is_wp_error($properties)) {
            return array();
        }
        
        return $properties;
    }
}