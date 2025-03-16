<?php
/**
 * Data handling/formatting for UIPress compatibility.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Data {

    /**
     * The auth handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_Auth    $auth    Handles WordPress-side authentication.
     */
    private $auth;

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
     * @param    UIPress_Analytics_Bridge_Auth       $auth       The auth handler instance.
     * @param    UIPress_Analytics_Bridge_API_Data   $api_data   The API data handler instance.
     */
    public function __construct($auth, $api_data) {
        $this->auth = $auth;
        $this->api_data = $api_data;
    }

    /**
     * Intercept the UIPress Pro build_query AJAX action.
     *
     * @since    1.0.0
     */
    public function intercept_build_query() {
        // Verify nonce
        if (!check_ajax_referer('uip-security-nonce', 'security', false)) {
            wp_send_json(array(
                'error' => true,
                'message' => __('Security check failed', 'uipress-analytics-bridge'),
            ));
        }

        // Get required parameters
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        // Get analytics data
        $analytics_data = $this->auth->get_analytics_data($save_to_user);
        
        // Check if we have a valid configuration
        if (empty($analytics_data) || !isset($analytics_data['code']) || !isset($analytics_data['view'])) {
            $data = get_option('uip_pro', true);
            
            wp_send_json(array(
                'error' => true,
                'message' => __('You need to connect a Google Analytics account to display data', 'uipress-analytics-bridge'),
                'error_type' => 'no_google',
                'url' => false,
            ));
        }
        
        // Format the response to match what UIPress Pro expects
        $response = array(
            'success' => true,
            'url' => $this->api_data->build_query_url($analytics_data),
            'connected' => isset($analytics_data['connected']) ? $analytics_data['connected'] : true,
            'oauth' => true,
        );
        
        // Add measurement ID if available (for GA4)
        if (isset($analytics_data['measurement_id']) && !empty($analytics_data['measurement_id'])) {
            $response['measurement_id'] = $analytics_data['measurement_id'];
        }
        
        wp_send_json($response);
    }

    /**
     * Filter the analytics data for UIPress compatibility.
     *
     * @since    1.0.0
     * @param    array    $data    The analytics data.
     * @return   array    The filtered data.
     */
    public function filter_analytics_data($data) {
        // If data is already valid, return it
        if (is_array($data) && isset($data['success']) && $data['success']) {
            return $data;
        }
        
        // Get the plugin settings
        $settings = get_option('uipress_analytics_bridge_settings', array());
        
        // Get the analytics data
        $analytics_data = $this->auth->get_analytics_data();
        
        // Get the start and end dates
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // Override with settings if available
        if (isset($settings['date_range']) && !empty($settings['date_range'])) {
            $range = intval($settings['date_range']);
            $start_date = date('Y-m-d', strtotime("-{$range} days"));
        }
        
        // Check if we have valid authentication data
        if (!isset($analytics_data['token']) || empty($analytics_data['token']) || 
            !isset($analytics_data['view']) || empty($analytics_data['view'])) {
            return $this->api_data->get_default_analytics_data();
        }
        
        // Determine property type (GA4 or UA)
        $property_type = isset($analytics_data['measurement_id']) && !empty($analytics_data['measurement_id']) ? 'GA4' : 'UA';
        
        // Get the analytics data
        $metrics = 'totalUsers,screenPageViews,sessions';
        $dimensions = 'date';
        
        $result = $this->api_data->get_analytics_data(
            $analytics_data['view'],
            $analytics_data['token'],
            $start_date,
            $end_date,
            $metrics,
            $dimensions,
            $property_type
        );
        
        // Check for errors
        if (is_wp_error($result)) {
            $default_data = $this->api_data->get_default_analytics_data();
            $default_data['message'] = $result->get_error_message();
            return $default_data;
        }
        
        // Add top content data
        $top_content_result = $this->get_top_content_data($analytics_data, $start_date, $end_date, $property_type);
        if (!is_wp_error($top_content_result) && isset($top_content_result['topContent'])) {
            $result['topContent'] = $top_content_result['topContent'];
        }
        
        // Add top sources data
        $top_sources_result = $this->get_top_sources_data($analytics_data, $start_date, $end_date, $property_type);
        if (!is_wp_error($top_sources_result) && isset($top_sources_result['topSources'])) {
            $result['topSources'] = $top_sources_result['topSources'];
        }
        
        // Add Google account data
        $result['google_account'] = array(
            'view' => $analytics_data['view'],
            'code' => $analytics_data['code'],
            'token' => $analytics_data['token'],
        );
        
        // Add property ID and measurement ID
        $result['property'] = $analytics_data['view'];
        if (isset($analytics_data['measurement_id']) && !empty($analytics_data['measurement_id'])) {
            $result['measurement_id'] = $analytics_data['measurement_id'];
        }
        
        return $result;
    }

    /**
     * Get top content data.
     *
     * @since    1.0.0
     * @param    array     $analytics_data    The analytics data.
     * @param    string    $start_date        The start date.
     * @param    string    $end_date          The end date.
     * @param    string    $property_type     The property type.
     * @return   array     The top content data.
     */
    private function get_top_content_data($analytics_data, $start_date, $end_date, $property_type) {
        $metrics = 'totalUsers,screenPageViews,engagementRate';
        $dimensions = 'pagePath,pageTitle';
        
        return $this->api_data->get_analytics_data(
            $analytics_data['view'],
            $analytics_data['token'],
            $start_date,
            $end_date,
            $metrics,
            $dimensions,
            $property_type
        );
    }

    /**
     * Get top sources data.
     *
     * @since    1.0.0
     * @param    array     $analytics_data    The analytics data.
     * @param    string    $start_date        The start date.
     * @param    string    $end_date          The end date.
     * @param    string    $property_type     The property type.
     * @return   array     The top sources data.
     */
    private function get_top_sources_data($analytics_data, $start_date, $end_date, $property_type) {
        $metrics = 'sessions,conversions';
        $dimensions = 'source';
        
        return $this->api_data->get_analytics_data(
            $analytics_data['view'],
            $analytics_data['token'],
            $start_date,
            $end_date,
            $metrics,
            $dimensions,
            $property_type
        );
    }

    /**
     * Get analytics data via AJAX.
     *
     * @since    1.0.0
     */
    public function get_analytics_data_ajax() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'security');
        
        // Get parameters
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        $start_date = isset($_POST['startDate']) ? sanitize_text_field($_POST['startDate']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_POST['endDate']) ? sanitize_text_field($_POST['endDate']) : date('Y-m-d');
        
        // Get analytics data
        $analytics_data = $this->auth->get_analytics_data($save_to_user);
        
        // Check if we have valid authentication data
        if (!isset($analytics_data['token']) || empty($analytics_data['token']) || 
            !isset($analytics_data['view']) || empty($analytics_data['view'])) {
            wp_send_json_error(array(
                'message' => __('Authentication data is missing or invalid.', 'uipress-analytics-bridge'),
                'data' => $this->api_data->get_default_analytics_data(),
            ));
        }
        
        // Determine property type (GA4 or UA)
        $property_type = isset($analytics_data['measurement_id']) && !empty($analytics_data['measurement_id']) ? 'GA4' : 'UA';
        
        // Get the analytics data
        $metrics = 'totalUsers,screenPageViews,sessions';
        $dimensions = 'date';
        
        $result = $this->api_data->get_analytics_data(
            $analytics_data['view'],
            $analytics_data['token'],
            $start_date,
            $end_date,
            $metrics,
            $dimensions,
            $property_type
        );
        
        // Check for errors
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'data' => $this->api_data->get_default_analytics_data(),
            ));
        }
        
        // Add top content data
        $top_content_result = $this->get_top_content_data($analytics_data, $start_date, $end_date, $property_type);
        if (!is_wp_error($top_content_result) && isset($top_content_result['topContent'])) {
            $result['topContent'] = $top_content_result['topContent'];
        }
        
        // Add top sources data
        $top_sources_result = $this->get_top_sources_data($analytics_data, $start_date, $end_date, $property_type);
        if (!is_wp_error($top_sources_result) && isset($top_sources_result['topSources'])) {
            $result['topSources'] = $top_sources_result['topSources'];
        }
        
        wp_send_json_success($result);
    }

    /**
     * Test the analytics connection via AJAX.
     *
     * @since    1.0.0
     */
    public function test_connection_ajax() {
        // Verify nonce
        check_ajax_referer('uipress-analytics-bridge-nonce', 'security');
        
        // Get parameters
        $save_to_user = isset($_POST['saveAccountToUser']) ? sanitize_text_field($_POST['saveAccountToUser']) : 'false';
        
        // Get analytics data
        $analytics_data = $this->auth->get_analytics_data($save_to_user);
        
        // Check if we have valid authentication data
        if (!isset($analytics_data['token']) || empty($analytics_data['token']) || 
            !isset($analytics_data['view']) || empty($analytics_data['view'])) {
            wp_send_json_error(array(
                'message' => __('Authentication data is missing or invalid.', 'uipress-analytics-bridge'),
                'status' => 'disconnected',
            ));
        }
        
        // Determine property type (GA4 or UA)
        $property_type = isset($analytics_data['measurement_id']) && !empty($analytics_data['measurement_id']) ? 'GA4' : 'UA';
        
        // Test the connection by fetching minimal data
        $metrics = 'totalUsers';
        $dimensions = 'date';
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        
        $result = $this->api_data->get_analytics_data(
            $analytics_data['view'],
            $analytics_data['token'],
            $start_date,
            $end_date,
            $metrics,
            $dimensions,
            $property_type
        );
        
        // Check for errors
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'status' => 'error',
            ));
        }
        
        // Connection is valid
        wp_send_json_success(array(
            'message' => __('Successfully connected to Google Analytics.', 'uipress-analytics-bridge'),
            'status' => 'connected',
            'property_type' => $property_type,
            'property_id' => $analytics_data['view'],
            'measurement_id' => isset($analytics_data['measurement_id']) ? $analytics_data['measurement_id'] : '',
        ));
    }

    /**
     * Clear the analytics data cache.
     *
     * @since    1.0.0
     */
    public function clear_cache() {
        $this->api_data->clear_cache();
    }
}