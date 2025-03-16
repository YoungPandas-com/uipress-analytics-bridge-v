<?php
/**
 * The class responsible for WordPress-side authentication handling.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Auth {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * API Auth instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_API_Auth    $api_auth    The API auth instance.
     */
    private $api_auth;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->api_auth = new UIPress_Analytics_Bridge_API_Auth($plugin_name, $version);
    }

    /**
     * Intercept save account AJAX request.
     *
     * @since    1.0.0
     */
    public function intercept_save_account() {
        // Check security nonce and 'DOING_AJAX' global
        if (!$this->verify_ajax_security()) {
            return;
        }

        $data = $this->clean_input_with_code(json_decode(stripslashes($_POST['analytics'])));
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';

        if (!is_object($data)) {
            $this->send_error_response(__('Incorrect data passed to server', 'uipress-analytics-bridge'));
        }

        if (!isset($data->view) || !isset($data->code)) {
            $this->send_error_response(__('Incorrect data passed to server', 'uipress-analytics-bridge'));
        }

        // Determine if this is a GA4 property (view will be a string starting with G-)
        $is_ga4 = (isset($data->view) && is_string($data->view) && strpos($data->view, 'G-') === 0);
        
        // Build auth data
        $auth_data = array(
            'view' => sanitize_text_field($data->view),
            'code' => sanitize_text_field($data->code),
            'gafour' => $is_ga4,
        );
        
        // If GA4, add measurement_id
        if ($is_ga4) {
            $auth_data['measurement_id'] = sanitize_text_field($data->view);
        }

        // Save account data
        $result = $this->save_account_data($auth_data, ($save_to_user === 'true'));

        if (!$result) {
            $this->send_error_response(__('Failed to save account data', 'uipress-analytics-bridge'));
        }

        // Return success response
        wp_send_json(array('success' => true));
    }

    /**
     * Intercept save access token AJAX request.
     *
     * @since    1.0.0
     */
    public function intercept_save_access_token() {
        // Check security nonce and 'DOING_AJAX' global
        if (!$this->verify_ajax_security()) {
            return;
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';

        if (!$token || $token == '') {
            $this->send_error_response(__('Incorrect token sent to server', 'uipress-analytics-bridge'));
        }

        // Get existing account data
        $auth_data = $this->get_account_data(($save_to_user === 'true'));
        
        if (!is_array($auth_data)) {
            $auth_data = array();
        }

        // Update with new token
        $auth_data['token'] = $token;

        // Save account data
        $result = $this->save_account_data($auth_data, ($save_to_user === 'true'));

        if (!$result) {
            $this->send_error_response(__('Failed to save access token', 'uipress-analytics-bridge'));
        }

        // Return success response
        wp_send_json(array('success' => true));
    }

    /**
     * Intercept remove account AJAX request.
     *
     * @since    1.0.0
     */
    public function intercept_remove_account() {
        // Check security nonce and 'DOING_AJAX' global
        if (!$this->verify_ajax_security()) {
            return;
        }

        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';

        // Get existing account data
        $auth_data = $this->get_account_data(($save_to_user === 'true'));
        
        if (!is_array($auth_data)) {
            $auth_data = array();
        }

        // Clear account data
        $auth_data['view'] = false;
        $auth_data['code'] = false;
        $auth_data['token'] = false;
        $auth_data['measurement_id'] = false;

        // Save account data
        $result = $this->save_account_data($auth_data, ($save_to_user === 'true'));

        if (!$result) {
            $this->send_error_response(__('Failed to remove account', 'uipress-analytics-bridge'));
        }

        // Return success response
        wp_send_json(array('success' => true));
    }

    /**
     * Handle OAuth callback.
     *
     * @since    1.0.0
     */
    public function handle_auth_callback() {
        // Check security nonce and 'DOING_AJAX' global
        if (!$this->verify_ajax_security()) {
            return;
        }

        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';

        if (!$code || !$property_id) {
            $this->send_error_response(__('Missing required authorization data', 'uipress-analytics-bridge'));
        }

        // Exchange code for tokens
        $tokens = $this->api_auth->exchange_code_for_tokens($code);

        if (!$tokens || isset($tokens['error'])) {
            $error_message = isset($tokens['error_description']) ? $tokens['error_description'] : __('Failed to authenticate with Google', 'uipress-analytics-bridge');
            $this->send_error_response($error_message);
        }

        // Determine if this is a GA4 property
        $is_ga4 = (strpos($property_id, 'G-') === 0);
        
        // Build auth data
        $auth_data = array(
            'view' => $property_id,
            'code' => $code,
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires' => time() + $tokens['expires_in'],
            'gafour' => $is_ga4,
        );
        
        // If GA4, add measurement_id
        if ($is_ga4) {
            $auth_data['measurement_id'] = $property_id;
        }

        // Save account data
        $result = $this->save_account_data($auth_data, ($save_to_user === 'true'));

        if (!$result) {
            $this->send_error_response(__('Failed to save authentication data', 'uipress-analytics-bridge'));
        }

        // Return success response
        wp_send_json(array(
            'success' => true,
            'message' => __('Successfully authenticated with Google Analytics', 'uipress-analytics-bridge'),
            'data' => array(
                'is_ga4' => $is_ga4,
                'property_id' => $property_id
            )
        ));
    }

    /**
     * Get OAuth URL.
     *
     * @since    1.0.0
     */
    public function get_oauth_url() {
        // Check security nonce and 'DOING_AJAX' global
        if (!$this->verify_ajax_security()) {
            return;
        }

        $url = $this->api_auth->get_auth_url();
        
        if (!$url) {
            $this->send_error_response(__('Failed to generate OAuth URL', 'uipress-analytics-bridge'));
        }

        // Return success response
        wp_send_json(array(
            'success' => true,
            'url' => $url
        ));
    }

    /**
     * Filter analytics status.
     *
     * @since    1.0.0
     * @param    mixed    $value    The value to filter.
     * @return   mixed              The filtered value.
     */
    public function filter_analytics_status($value) {
        // Check if we have valid authentication
        $user_auth = $this->get_account_data(true);
        $global_auth = $this->get_account_data(false);
        
        $has_user_auth = is_array($user_auth) && isset($user_auth['view']) && $user_auth['view'];
        $has_global_auth = is_array($global_auth) && isset($global_auth['view']) && $global_auth['view'];
        
        // If we have a valid auth, return 'active'
        if ($has_user_auth || $has_global_auth) {
            return 'active';
        }
        
        // Otherwise, return the original value
        return $value;
    }

    /**
     * Get analytics data.
     *
     * @since    1.0.0
     * @param    bool      $use_user_preferences    Whether to use user preferences or global settings.
     * @return   array                              The analytics data.
     */
    public function get_analytics_data($use_user_preferences = false) {
        return $this->get_account_data($use_user_preferences);
    }

    /**
     * Get account data.
     *
     * @since    1.0.0
     * @param    bool      $use_user_preferences    Whether to use user preferences or global settings.
     * @return   array                              The account data.
     */
    private function get_account_data($use_user_preferences = false) {
        if ($use_user_preferences) {
            // Use UserPreferences class if available
            if (class_exists('UipressLite\Classes\App\UserPreferences')) {
                return \UipressLite\Classes\App\UserPreferences::get('google_analytics');
            } else {
                // Fallback to direct user meta
                $user_id = get_current_user_id();
                $user_prefs = get_user_meta($user_id, 'uip-prefs', true);
                return isset($user_prefs['google_analytics']) ? $user_prefs['google_analytics'] : array();
            }
        } else {
            // Use UipOptions class if available
            if (class_exists('UipressLite\Classes\App\UipOptions')) {
                return \UipressLite\Classes\App\UipOptions::get('google_analytics');
            } else {
                // Fallback to direct option
                $options = get_option('uip-global-settings', array());
                return isset($options['google_analytics']) ? $options['google_analytics'] : array();
            }
        }
    }

    /**
     * Save account data.
     *
     * @since    1.0.0
     * @param    array     $data                    The data to save.
     * @param    bool      $use_user_preferences    Whether to use user preferences or global settings.
     * @return   bool                               Whether the save was successful.
     */
    private function save_account_data($data, $use_user_preferences = false) {
        if ($use_user_preferences) {
            // Use UserPreferences class if available
            if (class_exists('UipressLite\Classes\App\UserPreferences')) {
                \UipressLite\Classes\App\UserPreferences::update('google_analytics', $data);
                return true;
            } else {
                // Fallback to direct user meta
                $user_id = get_current_user_id();
                $user_prefs = get_user_meta($user_id, 'uip-prefs', true);
                
                if (!is_array($user_prefs)) {
                    $user_prefs = array();
                }
                
                $user_prefs['google_analytics'] = $data;
                return update_user_meta($user_id, 'uip-prefs', $user_prefs);
            }
        } else {
            // Use UipOptions class if available
            if (class_exists('UipressLite\Classes\App\UipOptions')) {
                \UipressLite\Classes\App\UipOptions::update('google_analytics', $data);
                return true;
            } else {
                // Fallback to direct option
                $options = get_option('uip-global-settings', array());
                
                if (!is_array($options)) {
                    $options = array();
                }
                
                $options['google_analytics'] = $data;
                return update_option('uip-global-settings', $options);
            }
        }
    }

    /**
     * Verify Ajax security
     *
     * @since    1.0.0
     * @return   bool    Whether security check passed
     */
    private function verify_ajax_security() {
        // Check if Ajax class exists and use it
        if (class_exists('UipressLite\Classes\Utils\Ajax')) {
            try {
                \UipressLite\Classes\Utils\Ajax::check_referer();
                return true;
            } catch (\Exception $e) {
                $this->send_error_response(__('Security check failed', 'uipress-analytics-bridge'));
                return false;
            }
        } else {
            // Manual check
            $doing_ajax = defined('DOING_AJAX') && DOING_AJAX ? true : false;
            $referer = check_ajax_referer('uip-security-nonce', 'security', false);
            
            if (!$doing_ajax || !$referer) {
                $this->send_error_response(__('Security check failed', 'uipress-analytics-bridge'));
                return false;
            }
            
            return true;
        }
    }

    /**
     * Send error response
     *
     * @since    1.0.0
     * @param    string    $message    The error message.
     */
    private function send_error_response($message) {
        $returndata = array(
            'error' => true,
            'message' => $message
        );
        wp_send_json($returndata);
    }

    /**
     * Clean input with code
     *
     * This is a simplified version of Sanitize::clean_input_with_code()
     * 
     * @since    1.0.0
     * @param    mixed     $input    The input to clean.
     * @return   mixed               The cleaned input.
     */
    private function clean_input_with_code($input) {
        // If Sanitize class exists, use it
        if (class_exists('UipressLite\Classes\Utils\Sanitize')) {
            return \UipressLite\Classes\Utils\Sanitize::clean_input_with_code($input);
        }
        
        // Simple fallback for objects
        if (is_object($input)) {
            foreach ($input as $key => $value) {
                $input->$key = $this->clean_input_with_code($value);
            }
            return $input;
        }
        
        // Simple fallback for arrays
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = $this->clean_input_with_code($value);
            }
            return $input;
        }
        
        // Simple fallback for strings
        if (is_string($input)) {
            return wp_kses_post($input);
        }
        
        return $input;
    }
}