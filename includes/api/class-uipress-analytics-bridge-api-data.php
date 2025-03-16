<?php
/**
 * Google API data retrieval handler.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes/api
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_API_Data {

    /**
     * The API auth handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_API_Auth    $api_auth    Handles Google API authentication.
     */
    private $api_auth;

    /**
     * Cache expiration time (in seconds).
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $cache_expiration    The cache expiration time.
     */
    private $cache_expiration = 3600; // 1 hour

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    UIPress_Analytics_Bridge_API_Auth    $api_auth    The API auth handler instance.
     */
    public function __construct($api_auth) {
        $this->api_auth = $api_auth;
    }

    /**
     * Get the Google Analytics properties for the authenticated user.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token.
     * @return   array|WP_Error    The properties or an error.
     */
    public function get_analytics_properties($access_token) {
        // First, try to get GA4 properties
        $ga4_properties = $this->get_ga4_properties($access_token);
        
        if (!is_wp_error($ga4_properties) && !empty($ga4_properties)) {
            return $ga4_properties;
        }
        
        // If no GA4 properties or error, try Universal Analytics properties
        $ua_properties = $this->get_universal_analytics_properties($access_token);
        
        if (!is_wp_error($ua_properties) && !empty($ua_properties)) {
            return $ua_properties;
        }
        
        // If both failed, return the GA4 error (it's likely more relevant)
        if (is_wp_error($ga4_properties)) {
            return $ga4_properties;
        }
        
        // If no properties found
        return new WP_Error('no_properties', __('No Google Analytics properties found.', 'uipress-analytics-bridge'));
    }

    /**
     * Get Google Analytics 4 properties.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token.
     * @return   array|WP_Error    The properties or an error.
     */
    private function get_ga4_properties($access_token) {
        // GA4 API endpoint
        $url = 'https://analyticsadmin.googleapis.com/v1beta/properties';
        
        // Make the request
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse the response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if the response contains an error
        if (isset($data['error'])) {
            return new WP_Error('ga4_properties_error', $data['error']['message'] ?? __('Failed to get GA4 properties.', 'uipress-analytics-bridge'));
        }
        
        // Process the properties
        $properties = array();
        
        if (isset($data['properties']) && is_array($data['properties'])) {
            foreach ($data['properties'] as $property) {
                if (isset($property['name'])) {
                    $property_id = str_replace('properties/', '', $property['name']);
                    
                    // Get the data streams for this property to find the measurement ID
                    $measurement_id = $this->get_property_measurement_id($property_id, $access_token);
                    
                    $properties[] = array(
                        'id' => $property_id,
                        'name' => $property['displayName'] ?? $property_id,
                        'type' => 'GA4',
                        'measurement_id' => $measurement_id,
                    );
                }
            }
        }
        
        return $properties;
    }

    /**
     * Get the measurement ID for a GA4 property.
     *
     * @since    1.0.0
     * @param    string    $property_id     The property ID.
     * @param    string    $access_token    The access token.
     * @return   string    The measurement ID.
     */
    private function get_property_measurement_id($property_id, $access_token) {
        // Data streams API endpoint
        $url = "https://analyticsadmin.googleapis.com/v1beta/properties/{$property_id}/dataStreams";
        
        // Make the request
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return '';
        }
        
        // Parse the response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if the response contains data streams
        if (isset($data['dataStreams']) && is_array($data['dataStreams'])) {
            foreach ($data['dataStreams'] as $stream) {
                if (isset($stream['webStreamData']['measurementId'])) {
                    return $stream['webStreamData']['measurementId'];
                }
            }
        }
        
        return '';
    }

    /**
     * Get Universal Analytics properties.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token.
     * @return   array|WP_Error    The properties or an error.
     */
    private function get_universal_analytics_properties($access_token) {
        // UA Management API endpoint
        $url = 'https://www.googleapis.com/analytics/v3/management/accounts/~all/webproperties/~all/profiles';
        
        // Make the request
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse the response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if the response contains an error
        if (isset($data['error'])) {
            return new WP_Error('ua_properties_error', $data['error']['message'] ?? __('Failed to get Universal Analytics properties.', 'uipress-analytics-bridge'));
        }
        
        // Process the properties
        $properties = array();
        
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $profile) {
                if (isset($profile['id'])) {
                    $properties[] = array(
                        'id' => $profile['id'],
                        'name' => $profile['name'] ?? $profile['id'],
                        'type' => 'UA',
                        'websiteUrl' => $profile['websiteUrl'] ?? '',
                        'accountId' => $profile['accountId'] ?? '',
                        'webPropertyId' => $profile['webPropertyId'] ?? '',
                    );
                }
            }
        }
        
        return $properties;
    }

    /**
     * Get Analytics data for a specific property.
     *
     * @since    1.0.0
     * @param    string    $property_id     The property ID.
     * @param    string    $access_token    The access token.
     * @param    string    $start_date      The start date (YYYY-MM-DD).
     * @param    string    $end_date        The end date (YYYY-MM-DD).
     * @param    string    $metrics         The metrics to retrieve.
     * @param    string    $dimensions      The dimensions to retrieve.
     * @param    string    $property_type   The property type ('GA4' or 'UA').
     * @return   array|WP_Error    The analytics data or an error.
     */
    public function get_analytics_data($property_id, $access_token, $start_date, $end_date, $metrics, $dimensions, $property_type = 'GA4') {
        // Generate a cache key
        $cache_key = md5("uipress_analytics_{$property_id}_{$start_date}_{$end_date}_{$metrics}_{$dimensions}_{$property_type}");
        
        // Check if we have cached data
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Get the data based on the property type
        if ($property_type === 'GA4') {
            $data = $this->get_ga4_data($property_id, $access_token, $start_date, $end_date, $metrics, $dimensions);
        } else {
            $data = $this->get_universal_analytics_data($property_id, $access_token, $start_date, $end_date, $metrics, $dimensions);
        }
        
        // Cache the data if it's not an error
        if (!is_wp_error($data)) {
            set_transient($cache_key, $data, $this->cache_expiration);
        }
        
        return $data;
    }

    /**
     * Get GA4 data.
     *
     * @since    1.0.0
     * @param    string    $property_id     The property ID.
     * @param    string    $access_token    The access token.
     * @param    string    $start_date      The start date (YYYY-MM-DD).
     * @param    string    $end_date        The end date (YYYY-MM-DD).
     * @param    string    $metrics         The metrics to retrieve.
     * @param    string    $dimensions      The dimensions to retrieve.
     * @return   array|WP_Error    The analytics data or an error.
     */
    private function get_ga4_data($property_id, $access_token, $start_date, $end_date, $metrics, $dimensions) {
        // GA4 Data API endpoint
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport";
        
        // Parse metrics and dimensions
        $metrics_array = explode(',', $metrics);
        $dimensions_array = explode(',', $dimensions);
        
        // Prepare metrics objects
        $metrics_objects = array();
        foreach ($metrics_array as $metric) {
            $metrics_objects[] = array('name' => trim($metric));
        }
        
        // Prepare dimensions objects
        $dimensions_objects = array();
        foreach ($dimensions_array as $dimension) {
            if (!empty(trim($dimension))) {
                $dimensions_objects[] = array('name' => trim($dimension));
            }
        }
        
        // Prepare the request body
        $body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $start_date,
                    'endDate' => $end_date,
                ),
            ),
            'metrics' => $metrics_objects,
        );
        
        // Add dimensions if available
        if (!empty($dimensions_objects)) {
            $body['dimensions'] = $dimensions_objects;
        }
        
        // Make the request
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 15,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse the response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if the response contains an error
        if (isset($data['error'])) {
            return new WP_Error('ga4_data_error', $data['error']['message'] ?? __('Failed to get GA4 data.', 'uipress-analytics-bridge'));
        }
        
        // Process the response into a format compatible with UIPress
        return $this->format_ga4_data_for_uipress($data, $metrics_array, $dimensions_array);
    }

    /**
     * Format GA4 data for UIPress compatibility.
     *
     * @since    1.0.0
     * @param    array    $data              The GA4 data.
     * @param    array    $metrics_array     The metrics array.
     * @param    array    $dimensions_array  The dimensions array.
     * @return   array    The formatted data.
     */
    private function format_ga4_data_for_uipress($data, $metrics_array, $dimensions_array) {
        $formatted_data = array(
            'success' => true,
            'connected' => true,
            'data' => array(),
            'totalStats' => array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'change' => array(
                    'users' => 0,
                    'pageviews' => 0,
                    'sessions' => 0,
                ),
            ),
            'topContent' => array(),
            'topSources' => array(),
            'gafour' => true,
        );
        
        // Check if we have row data
        if (!isset($data['rows']) || empty($data['rows'])) {
            return $formatted_data;
        }
        
        // Get dimension headers
        $dimension_headers = array();
        if (isset($data['dimensionHeaders'])) {
            foreach ($data['dimensionHeaders'] as $header) {
                $dimension_headers[] = $header['name'];
            }
        }
        
        // Get metric headers
        $metric_headers = array();
        if (isset($data['metricHeaders'])) {
            foreach ($data['metricHeaders'] as $header) {
                $metric_headers[] = $header['name'];
            }
        }
        
        // Process rows
        foreach ($data['rows'] as $row) {
            $row_data = array();
            
            // Process dimensions
            if (isset($row['dimensionValues'])) {
                foreach ($row['dimensionValues'] as $index => $dimension) {
                    $key = isset($dimension_headers[$index]) ? $dimension_headers[$index] : "dimension{$index}";
                    $row_data[$key] = $dimension['value'];
                }
            }
            
            // Process metrics
            if (isset($row['metricValues'])) {
                foreach ($row['metricValues'] as $index => $metric) {
                    $key = isset($metric_headers[$index]) ? $metric_headers[$index] : "metric{$index}";
                    $row_data[$key] = $metric['value'];
                }
            }
            
            // Add to appropriate array based on dimension
            if (in_array('date', $dimension_headers)) {
                // Time series data
                $formatted_data['data'][] = array(
                    'name' => isset($row_data['date']) ? $row_data['date'] : '',
                    'value' => isset($row_data['totalUsers']) ? intval($row_data['totalUsers']) : 0,
                    'pageviews' => isset($row_data['screenPageViews']) ? intval($row_data['screenPageViews']) : 0,
                    'sessions' => isset($row_data['sessions']) ? intval($row_data['sessions']) : 0,
                );
                
                // Add to totals
                $formatted_data['totalStats']['users'] += isset($row_data['totalUsers']) ? intval($row_data['totalUsers']) : 0;
                $formatted_data['totalStats']['pageviews'] += isset($row_data['screenPageViews']) ? intval($row_data['screenPageViews']) : 0;
                $formatted_data['totalStats']['sessions'] += isset($row_data['sessions']) ? intval($row_data['sessions']) : 0;
            } elseif (in_array('pageTitle', $dimension_headers) || in_array('pagePath', $dimension_headers)) {
                // Top content
                $formatted_data['topContent'][] = array(
                    'path' => isset($row_data['pagePath']) ? $row_data['pagePath'] : '',
                    'title' => isset($row_data['pageTitle']) ? $row_data['pageTitle'] : '',
                    'pageviews' => isset($row_data['screenPageViews']) ? intval($row_data['screenPageViews']) : 0,
                    'engagement' => isset($row_data['engagementRate']) ? floatval($row_data['engagementRate']) : 0,
                    'users' => isset($row_data['totalUsers']) ? intval($row_data['totalUsers']) : 0,
                );
            } elseif (in_array('source', $dimension_headers)) {
                // Top sources
                $formatted_data['topSources'][] = array(
                    'source' => isset($row_data['source']) ? $row_data['source'] : '',
                    'sessions' => isset($row_data['sessions']) ? intval($row_data['sessions']) : 0,
                    'conversion' => isset($row_data['conversions']) ? floatval($row_data['conversions']) : 0,
                );
            }
        }
        
        // Calculate change percentages (assuming the last row is the most recent period)
        if (count($formatted_data['data']) > 1) {
            $current_period_index = count($formatted_data['data']) - 1;
            $previous_period_index = $current_period_index - 1;
            
            if (isset($formatted_data['data'][$current_period_index]) && isset($formatted_data['data'][$previous_period_index])) {
                $current = $formatted_data['data'][$current_period_index];
                $previous = $formatted_data['data'][$previous_period_index];
                
                // Calculate user change
                if (isset($previous['value']) && $previous['value'] > 0) {
                    $formatted_data['totalStats']['change']['users'] = (($current['value'] - $previous['value']) / $previous['value']) * 100;
                }
                
                // Calculate pageview change
                if (isset($previous['pageviews']) && $previous['pageviews'] > 0) {
                    $formatted_data['totalStats']['change']['pageviews'] = (($current['pageviews'] - $previous['pageviews']) / $previous['pageviews']) * 100;
                }
                
                // Calculate session change
                if (isset($previous['sessions']) && $previous['sessions'] > 0) {
                    $formatted_data['totalStats']['change']['sessions'] = (($current['sessions'] - $previous['sessions']) / $previous['sessions']) * 100;
                }
            }
        }
        
        return $formatted_data;
    }

    /**
     * Get Universal Analytics data.
     *
     * @since    1.0.0
     * @param    string    $property_id     The property ID.
     * @param    string    $access_token    The access token.
     * @param    string    $start_date      The start date (YYYY-MM-DD).
     * @param    string    $end_date        The end date (YYYY-MM-DD).
     * @param    string    $metrics         The metrics to retrieve.
     * @param    string    $dimensions      The dimensions to retrieve.
     * @return   array|WP_Error    The analytics data or an error.
     */
    private function get_universal_analytics_data($property_id, $access_token, $start_date, $end_date, $metrics, $dimensions) {
        // UA Reporting API endpoint
        $url = 'https://www.googleapis.com/analytics/v3/data/ga';
        
        // Map GA4 metrics to UA metrics
        $metrics = str_replace('totalUsers', 'ga:users', $metrics);
        $metrics = str_replace('screenPageViews', 'ga:pageviews', $metrics);
        $metrics = str_replace('sessions', 'ga:sessions', $metrics);
        
        // Map GA4 dimensions to UA dimensions
        $dimensions = str_replace('date', 'ga:date', $dimensions);
        $dimensions = str_replace('pageTitle', 'ga:pageTitle', $dimensions);
        $dimensions = str_replace('pagePath', 'ga:pagePath', $dimensions);
        $dimensions = str_replace('source', 'ga:source', $dimensions);
        
        // Ensure metrics and dimensions are properly formatted
        $metrics = preg_replace('/[^ga:,a-zA-Z0-9]/', '', $metrics);
        $dimensions = preg_replace('/[^ga:,a-zA-Z0-9]/', '', $dimensions);
        
        // Build the query parameters
        $query_params = array(
            'ids' => 'ga:' . $property_id,
            'start-date' => $start_date,
            'end-date' => $end_date,
            'metrics' => $metrics,
        );
        
        // Add dimensions if available
        if (!empty($dimensions)) {
            $query_params['dimensions'] = $dimensions;
        }
        
        // Construct the full URL
        $url .= '?' . http_build_query($query_params);
        
        // Make the request
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse the response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if the response contains an error
        if (isset($data['error'])) {
            return new WP_Error('ua_data_error', $data['error']['message'] ?? __('Failed to get Universal Analytics data.', 'uipress-analytics-bridge'));
        }
        
        // Process the response into a format compatible with UIPress
        return $this->format_ua_data_for_uipress($data);
    }

    /**
     * Format Universal Analytics data for UIPress compatibility.
     *
     * @since    1.0.0
     * @param    array    $data    The UA data.
     * @return   array    The formatted data.
     */
    private function format_ua_data_for_uipress($data) {
        $formatted_data = array(
            'success' => true,
            'connected' => true,
            'data' => array(),
            'totalStats' => array(
                'users' => 0,
                'pageviews' => 0,
                'sessions' => 0,
                'change' => array(
                    'users' => 0,
                    'pageviews' => 0,
                    'sessions' => 0,
                ),
            ),
            'topContent' => array(),
            'topSources' => array(),
            'gafour' => false,
        );
        
        // Check if we have row data
        if (!isset($data['rows']) || empty($data['rows'])) {
            return $formatted_data;
        }
        
        // Get column headers
        $headers = array();
        if (isset($data['columnHeaders'])) {
            foreach ($data['columnHeaders'] as $header) {
                $headers[] = $header['name'];
            }
        }
        
        // Process rows
        foreach ($data['rows'] as $row) {
            $row_data = array();
            
            // Associate values with headers
            foreach ($row as $index => $value) {
                $key = isset($headers[$index]) ? str_replace('ga:', '', $headers[$index]) : "value{$index}";
                $row_data[$key] = $value;
            }
            
            // Add to appropriate array based on dimensions
            if (isset($row_data['date'])) {
                // Format date from YYYYMMDD to YYYY-MM-DD
                $date = $row_data['date'];
                $formatted_date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
                
                // Time series data
                $formatted_data['data'][] = array(
                    'name' => $formatted_date,
                    'value' => isset($row_data['users']) ? intval($row_data['users']) : 0,
                    'pageviews' => isset($row_data['pageviews']) ? intval($row_data['pageviews']) : 0,
                    'sessions' => isset($row_data['sessions']) ? intval($row_data['sessions']) : 0,
                );
                
                // Add to totals
                $formatted_data['totalStats']['users'] += isset($row_data['users']) ? intval($row_data['users']) : 0;
                $formatted_data['totalStats']['pageviews'] += isset($row_data['pageviews']) ? intval($row_data['pageviews']) : 0;
                $formatted_data['totalStats']['sessions'] += isset($row_data['sessions']) ? intval($row_data['sessions']) : 0;
            } elseif (isset($row_data['pageTitle']) || isset($row_data['pagePath'])) {
                // Top content
                $formatted_data['topContent'][] = array(
                    'path' => isset($row_data['pagePath']) ? $row_data['pagePath'] : '',
                    'title' => isset($row_data['pageTitle']) ? $row_data['pageTitle'] : '',
                    'pageviews' => isset($row_data['pageviews']) ? intval($row_data['pageviews']) : 0,
                    'engagement' => isset($row_data['avgTimeOnPage']) ? floatval($row_data['avgTimeOnPage']) : 0,
                    'users' => isset($row_data['users']) ? intval($row_data['users']) : 0,
                );
            } elseif (isset($row_data['source'])) {
                // Top sources
                $formatted_data['topSources'][] = array(
                    'source' => isset($row_data['source']) ? $row_data['source'] : '',
                    'sessions' => isset($row_data['sessions']) ? intval($row_data['sessions']) : 0,
                    'conversion' => isset($row_data['goalCompletionsAll']) ? floatval($row_data['goalCompletionsAll']) : 0,
                );
            }
        }
        
        // Calculate change percentages (assuming the last row is the most recent period)
        if (count($formatted_data['data']) > 1) {
            $current_period_index = count($formatted_data['data']) - 1;
            $previous_period_index = $current_period_index - 1;
            
            if (isset($formatted_data['data'][$current_period_index]) && isset($formatted_data['data'][$previous_period_index])) {
                $current = $formatted_data['data'][$current_period_index];
                $previous = $formatted_data['data'][$previous_period_index];
                
                // Calculate user change
                if (isset($previous['value']) && $previous['value'] > 0) {
                    $formatted_data['totalStats']['change']['users'] = (($current['value'] - $previous['value']) / $previous['value']) * 100;
                }
                
                // Calculate pageview change
                if (isset($previous['pageviews']) && $previous['pageviews'] > 0) {
                    $formatted_data['totalStats']['change']['pageviews'] = (($current['pageviews'] - $previous['pageviews']) / $previous['pageviews']) * 100;
                }
                
                // Calculate session change
                if (isset($previous['sessions']) && $previous['sessions'] > 0) {
                    $formatted_data['totalStats']['change']['sessions'] = (($current['sessions'] - $previous['sessions']) / $previous['sessions']) * 100;
                }
            }
        }
        
        return $formatted_data;
    }

    /**
     * Get default analytics data structure.
     *
     * @since    1.0.0
     * @return   array    The default analytics data.
     */
    public function get_default_analytics_data() {
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
                    'sessions' => 0,
                ),
            ),
            'topContent' => array(),
            'topSources' => array(),
            'gafour' => true,
            'message' => __('No data available', 'uipress-analytics-bridge'),
        );
    }

    /**
     * Build the query URL for UIPress compatibility.
     *
     * @since    1.0.0
     * @param    array     $analytics_data    The analytics data.
     * @return   string    The query URL.
     */
    public function build_query_url($analytics_data) {
        // Build a dummy URL for UIPress compatibility
        $domain = get_home_url();
        $code = isset($analytics_data['code']) ? urlencode($analytics_data['code']) : '';
        $view = isset($analytics_data['view']) ? urlencode($analytics_data['view']) : '';
        $token = isset($analytics_data['token']) ? urlencode($analytics_data['token']) : '';
        $key = 'bridge-key';
        $instance = 'bridge-instance';
        
        return "https://analytics.uipress.co/view.php?code={$code}&view={$view}&key={$key}&instance={$instance}&uip3=1&gafour=true&d={$domain}&uip_token={$token}";
    }

    /**
     * Clear the analytics data cache.
     *
     * @since    1.0.0
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_uipress_analytics_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_uipress_analytics_%'");
    }
}