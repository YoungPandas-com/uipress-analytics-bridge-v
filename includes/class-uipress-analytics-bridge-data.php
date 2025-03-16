<?php
/**
 * The class responsible for data handling and formatting.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Data {

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
     * Auth instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_Auth    $auth    The auth instance.
     */
    private $auth;

    /**
     * API Data instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_API_Data    $api_data    The API data instance.
     */
    private $api_data;

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
        $this->auth = new UIPress_Analytics_Bridge_Auth($plugin_name, $version);
        $this->api_data = new UIPress_Analytics_Bridge_API_Data($plugin_name, $version);
    }

    /**
     * Intercept build query AJAX request.
     *
     * @since    1.0.0
     */
    public function intercept_build_query() {
        // Check security nonce and 'DOING_AJAX' global
        if (!$this->verify_ajax_security()) {
            return;
        }

        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        // Get analytics data
        $analytics_data = $this->auth->get_analytics_data(($save_to_user === 'true'));
        
        // Check if we have valid analytics data
        if (!$analytics_data || !isset($analytics_data['view']) || !isset($analytics_data['code'])) {
            $this->send_error_response(
                __('You need to connect a Google Analytics account to display data', 'uipress-analytics-bridge'),
                'no_google'
            );
        }
        
        // Check if we have a license key (UIPress Pro requirement)
        $uip_pro_data = $this->get_uip_pro_data();
        
        if (!$uip_pro_data || !isset($uip_pro_data['key'])) {
            $this->send_error_response(
                __('You need a licence key to use analytics blocks', 'uipress-analytics-bridge'),
                'no_licence'
            );
        }
        
        // Build the query URL
        $query_url = $this->build_query_url($analytics_data, $uip_pro_data);
        
        // Return the query URL
        wp_send_json(array(
            'success' => true,
            'url' => $query_url,
            'connected' => true,
            'oauth' => true,
            'measurement_id' => isset($analytics_data['measurement_id']) ? $analytics_data['measurement_id'] : ''
        ));
    }

    /**
     * Get analytics data via AJAX.
     *
     * @since    1.0.0
     */
    public function get_analytics_data() {
        // Check security nonce and 'DOING_AJAX' global
        if (!$this->verify_ajax_security()) {
            return;
        }

        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        $date_range = isset($_POST['dateRange']) ? $this->clean_input_with_code($_POST['dateRange']) : array();
        $metrics = isset($_POST['metrics']) ? $this->clean_input_with_code($_POST['metrics']) : array();
        $dimensions = isset($_POST['dimensions']) ? $this->clean_input_with_code($_POST['dimensions']) : array();
        $max_results = isset($_POST['maxResults']) ? intval($_POST['maxResults']) : 10;
        
        // Get auth data
        $auth_data = $this->auth->get_analytics_data(($save_to_user === 'true'));
        
        // Fetch data from Google Analytics API
        $analytics_data = $this->api_data->get_analytics_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
        
        // Apply filter for customizations
        $analytics_data = apply_filters('uip_filter_google_analytics_data', $analytics_data);
        
        // Return data
        wp_send_json($analytics_data);
    }

    /**
     * Filter analytics data.
     *
     * @since    1.0.0
     * @param    array    $data    The data to filter.
     * @return   array             The filtered data.
     */
    public function filter_analytics_data($data) {
        // Add any custom data filtering here
        return $data;
    }

    /**
     * Build query URL.
     *
     * @since    1.0.0
     * @param    array    $analytics_data    The analytics data.
     * @param    array    $uip_pro_data      The UIPress Pro data.
     * @return   string                      The query URL.
     */
    private function build_query_url($analytics_data, $uip_pro_data = null) {
        // If UIPress Pro data is not provided, try to get it
        if (!$uip_pro_data) {
            $uip_pro_data = $this->get_uip_pro_data();
        }
        
        // Get required values
        $key = isset($uip_pro_data['key']) ? $uip_pro_data['key'] : '';
        $instance = isset($uip_pro_data['instance']) ? $uip_pro_data['instance'] : '';
        $code = isset($analytics_data['code']) ? $analytics_data['code'] : '';
        $view = isset($analytics_data['view']) ? $analytics_data['view'] : '';
        $token = isset($analytics_data['token']) ? $analytics_data['token'] : '';
        $domain = get_home_url();
        
        // Check if we have all required values
        if (empty($key) || empty($code) || empty($view)) {
            return false;
        }
        
        // Build the query URL
        $query_url = add_query_arg(
            array(
                'code' => $code,
                'view' => $view,
                'key' => $key,
                'instance' => $instance,
                'uip3' => 1,
                'gafour' => isset($analytics_data['gafour']) && $analytics_data['gafour'] ? 'true' : 'false',
                'd' => $domain,
                'uip_token' => $token,
                'bridge' => 1,
                'v' => $this->version
            ),
            'https://analytics.uipress.co/view.php'
        );
        
        return sanitize_url($query_url);
    }

    /**
     * Get UIPress Pro data.
     *
     * @since    1.0.0
     * @return   array    The UIPress Pro data.
     */
    private function get_uip_pro_data() {
        // Try using UipOptions class
        if (class_exists('UipressLite\Classes\App\UipOptions')) {
            return \UipressLite\Classes\App\UipOptions::get('uip_pro', true);
        }
        
        // Fallback to direct option
        $options = get_option('uip-global-settings', array());
        return isset($options['uip_pro']) ? $options['uip_pro'] : array();
    }

    /**
     * Generate default analytics data.
     *
     * @since    1.0.0
     * @return   array    The default analytics data.
     */
    public function generate_default_data() {
        return array(
            'success' => true,
            'connected' => false,
            'data' => array(),
            'totalStats' => array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'change' => array(
                    'users' => 0,
                    'pageviews' => 0,
                    'sessions' => 0
                )
            ),
            'topContent' => array(),
            'topSources' => array(),
            'gafour' => true,
            'message' => __('No data available', 'uipress-analytics-bridge')
        );
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
     * @param    string    $message     The error message.
     * @param    string    $error_type  The error type.
     */
    private function send_error_response($message, $error_type = '') {
        $returndata = array(
            'error' => true,
            'message' => $message,
            'url' => false
        );
        
        if (!empty($error_type)) {
            $returndata['error_type'] = $error_type;
        }
        
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