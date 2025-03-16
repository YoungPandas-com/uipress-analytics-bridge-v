<?php
/**
 * The class responsible for Google API data retrieval.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes/api
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_API_Data {

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
     * Get analytics data from the Google Analytics API.
     *
     * @since    1.0.0
     * @param    array     $auth_data     The authentication data.
     * @param    array     $date_range    The date range.
     * @param    array     $metrics       The metrics to fetch.
     * @param    array     $dimensions    The dimensions to fetch.
     * @param    int       $max_results   The maximum number of results.
     * @return   array                    The analytics data.
     */
    public function get_analytics_data($auth_data, $date_range = array(), $metrics = array(), $dimensions = array(), $max_results = 10) {
        // Check if authentication data is valid
        if (!is_array($auth_data) || !isset($auth_data['view']) || !isset($auth_data['token'])) {
            return $this->generate_error_response(
                'No valid authentication data found.',
                'no_auth_data'
            );
        }

        // Determine if this is GA4 or Universal Analytics
        $is_ga4 = isset($auth_data['gafour']) && $auth_data['gafour'];
        
        // Set default metrics and dimensions if not provided
        if (empty($metrics)) {
            $metrics = $is_ga4 ? 
                array('activeUsers', 'screenPageViews', 'sessions') : 
                array('ga:users', 'ga:pageviews', 'ga:sessions');
        }
        
        if (empty($dimensions)) {
            $dimensions = $is_ga4 ? 
                array('date') : 
                array('ga:date');
        }
        
        // Set default date range if not provided
        if (empty($date_range) || !isset($date_range['start']) || !isset($date_range['end'])) {
            $end_date = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime('-30 days'));
            
            $date_range = array(
                'start' => $start_date,
                'end' => $end_date
            );
        }
        
        // Get analytics data based on API version
        if ($is_ga4) {
            $data = $this->get_ga4_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
        } else {
            $data = $this->get_universal_analytics_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
        }
        
        // Check for error in response
        if (isset($data['error'])) {
            // Try to refresh the token and try again
            if ($data['error_type'] === 'token_expired' && isset($auth_data['refresh_token'])) {
                $tokens = $this->api_auth->refresh_access_token($auth_data['refresh_token']);
                
                if (!isset($tokens['error'])) {
                    // Update auth data with new token
                    $auth_data['token'] = $tokens['access_token'];
                    $auth_data['expires'] = time() + $tokens['expires_in'];
                    
                    // Save updated auth data
                    $this->save_auth_data($auth_data);
                    
                    // Try again with new token
                    if ($is_ga4) {
                        $data = $this->get_ga4_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
                    } else {
                        $data = $this->get_universal_analytics_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
                    }
                }
            }
            
            // If still error, return error response
            if (isset($data['error'])) {
                return $this->generate_error_response(
                    $data['message'],
                    $data['error_type']
                );
            }
        }
        
        // Get additional data for a complete response
        $top_content = $this->get_top_content($auth_data, $date_range, $max_results);
        $top_sources = $this->get_top_sources($auth_data, $date_range, $max_results);
        
        // Calculate period comparison for metrics
        $previous_period = $this->get_previous_period_data($auth_data, $date_range, $metrics, $dimensions);
        
        // Format the response to match UIPress Pro expectations
        $response = $this->format_analytics_response($data, $top_content, $top_sources, $previous_period, $auth_data);
        
        // Cache the response
        $this->cache_analytics_data($response, $auth_data['view'], $date_range);
        
        return $response;
    }

    /**
     * Get GA4 data.
     *
     * @since    1.0.0
     * @param    array     $auth_data     The authentication data.
     * @param    array     $date_range    The date range.
     * @param    array     $metrics       The metrics to fetch.
     * @param    array     $dimensions    The dimensions to fetch.
     * @param    int       $max_results   The maximum number of results.
     * @return   array                    The GA4 data.
     */
    private function get_ga4_data($auth_data, $date_range, $metrics, $dimensions, $max_results) {
        // Check for cached data
        $cached_data = $this->get_cached_analytics_data($auth_data['view'], $date_range);
        
        if ($cached_data) {
            return $cached_data;
        }
        
        // Format metrics and dimensions for GA4
        $formatted_metrics = array();
        $formatted_dimensions = array();
        
        foreach ($metrics as $metric) {
            $formatted_metrics[] = array('name' => $metric);
        }
        
        foreach ($dimensions as $dimension) {
            $formatted_dimensions[] = array('name' => $dimension);
        }
        
        // Build request body for GA4 API
        $request_body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $date_range['start'],
                    'endDate' => $date_range['end']
                )
            ),
            'metrics' => $formatted_metrics,
            'dimensions' => $formatted_dimensions,
            'limit' => $max_results
        );
        
        // Make API request to GA4
        $response = wp_remote_post(
            'https://analyticsdata.googleapis.com/v1beta/properties/' . $this->extract_property_id($auth_data['view']) . ':runReport',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $auth_data['token'],
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($request_body),
                'timeout' => 15
            )
        );
        
        // Check for WP error
        if (is_wp_error($response)) {
            return array(
                'error' => true,
                'error_type' => 'wp_error',
                'message' => $response->get_error_message()
            );
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for API error
        if (isset($data['error'])) {
            $error_type = 'api_error';
            
            // Check for token expired error
            if (isset($data['error']['status']) && $data['error']['status'] === 'UNAUTHENTICATED') {
                $error_type = 'token_expired';
            }
            
            return array(
                'error' => true,
                'error_type' => $error_type,
                'message' => isset($data['error']['message']) ? $data['error']['message'] : 'Unknown API error'
            );
        }
        
        return $data;
    }

    /**
     * Get Universal Analytics data.
     *
     * @since    1.0.0
     * @param    array     $auth_data     The authentication data.
     * @param    array     $date_range    The date range.
     * @param    array     $metrics       The metrics to fetch.
     * @param    array     $dimensions    The dimensions to fetch.
     * @param    int       $max_results   The maximum number of results.
     * @return   array                    The Universal Analytics data.
     */
    private function get_universal_analytics_data($auth_data, $date_range, $metrics, $dimensions, $max_results) {
        // Check for cached data
        $cached_data = $this->get_cached_analytics_data($auth_data['view'], $date_range);
        
        if ($cached_data) {
            return $cached_data;
        }
        
        // Ensure metrics have ga: prefix
        $formatted_metrics = array();
        foreach ($metrics as $metric) {
            if (strpos($metric, 'ga:') !== 0) {
                $metric = 'ga:' . $metric;
            }
            $formatted_metrics[] = $metric;
        }
        
        // Ensure dimensions have ga: prefix
        $formatted_dimensions = array();
        foreach ($dimensions as $dimension) {
            if (strpos($dimension, 'ga:') !== 0) {
                $dimension = 'ga:' . $dimension;
            }
            $formatted_dimensions[] = $dimension;
        }
        
        // Build the API URL
        $api_url = 'https://www.googleapis.com/analytics/v3/data/ga';
        $api_url = add_query_arg(
            array(
                'ids' => 'ga:' . $auth_data['view'],
                'start-date' => $date_range['start'],
                'end-date' => $date_range['end'],
                'metrics' => implode(',', $formatted_metrics),
                'dimensions' => implode(',', $formatted_dimensions),
                'max-results' => $max_results
            ),
            $api_url
        );
        
        // Make API request
        $response = wp_remote_get(
            $api_url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $auth_data['token']
                ),
                'timeout' => 15
            )
        );
        
        // Check for WP error
        if (is_wp_error($response)) {
            return array(
                'error' => true,
                'error_type' => 'wp_error',
                'message' => $response->get_error_message()
            );
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for API error
        if (isset($data['error'])) {
            $error_type = 'api_error';
            
            // Check for token expired error
            if (isset($data['error']['code']) && $data['error']['code'] === 401) {
                $error_type = 'token_expired';
            }
            
            return array(
                'error' => true,
                'error_type' => $error_type,
                'message' => isset($data['error']['message']) ? $data['error']['message'] : 'Unknown API error'
            );
        }
        
        return $data;
    }

    /**
     * Get top content.
     *
     * @since    1.0.0
     * @param    array     $auth_data     The authentication data.
     * @param    array     $date_range    The date range.
     * @param    int       $max_results   The maximum number of results.
     * @return   array                    The top content data.
     */
    private function get_top_content($auth_data, $date_range, $max_results = 10) {
        // Determine if this is GA4 or Universal Analytics
        $is_ga4 = isset($auth_data['gafour']) && $auth_data['gafour'];
        
        if ($is_ga4) {
            // Define metrics and dimensions for GA4
            $metrics = array('screenPageViews', 'activeUsers', 'engagementRate');
            $dimensions = array('pagePath', 'pageTitle');
            
            // Get GA4 data
            $data = $this->get_ga4_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
            
            // Check for error
            if (isset($data['error'])) {
                return array();
            }
            
            // Format the data
            $top_content = array();
            
            if (isset($data['rows']) && is_array($data['rows'])) {
                foreach ($data['rows'] as $row) {
                    $page_path = '';
                    $page_title = '';
                    $pageviews = 0;
                    $users = 0;
                    $engagement = 0;
                    
                    // Extract dimension values
                    foreach ($row['dimensionValues'] as $index => $dimension_value) {
                        if ($dimensions[$index] === 'pagePath') {
                            $page_path = $dimension_value['value'];
                        } elseif ($dimensions[$index] === 'pageTitle') {
                            $page_title = $dimension_value['value'];
                        }
                    }
                    
                    // Extract metric values
                    foreach ($row['metricValues'] as $index => $metric_value) {
                        if ($metrics[$index] === 'screenPageViews') {
                            $pageviews = intval($metric_value['value']);
                        } elseif ($metrics[$index] === 'activeUsers') {
                            $users = intval($metric_value['value']);
                        } elseif ($metrics[$index] === 'engagementRate') {
                            $engagement = floatval($metric_value['value']) * 100;
                        }
                    }
                    
                    $top_content[] = array(
                        'path' => $page_path,
                        'title' => $page_title,
                        'pageviews' => $pageviews,
                        'engagement' => $engagement,
                        'users' => $users
                    );
                }
            }
            
            return $top_content;
        } else {
            // Define metrics and dimensions for Universal Analytics
            $metrics = array('ga:pageviews', 'ga:users', 'ga:avgTimeOnPage');
            $dimensions = array('ga:pagePath', 'ga:pageTitle');
            
            // Get Universal Analytics data
            $data = $this->get_universal_analytics_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
            
            // Check for error
            if (isset($data['error'])) {
                return array();
            }
            
            // Format the data
            $top_content = array();
            
            if (isset($data['rows']) && is_array($data['rows'])) {
                foreach ($data['rows'] as $row) {
                    $top_content[] = array(
                        'path' => $row[0],
                        'title' => $row[1],
                        'pageviews' => intval($row[2]),
                        'users' => intval($row[3]),
                        'engagement' => round(floatval($row[4]), 2)
                    );
                }
            }
            
            return $top_content;
        }
    }

    /**
     * Get top sources.
     *
     * @since    1.0.0
     * @param    array     $auth_data     The authentication data.
     * @param    array     $date_range    The date range.
     * @param    int       $max_results   The maximum number of results.
     * @return   array                    The top sources data.
     */
    private function get_top_sources($auth_data, $date_range, $max_results = 10) {
        // Determine if this is GA4 or Universal Analytics
        $is_ga4 = isset($auth_data['gafour']) && $auth_data['gafour'];
        
        if ($is_ga4) {
            // Define metrics and dimensions for GA4
            $metrics = array('sessions', 'conversions');
            $dimensions = array('sessionSource');
            
            // Get GA4 data
            $data = $this->get_ga4_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
            
            // Check for error
            if (isset($data['error'])) {
                return array();
            }
            
            // Format the data
            $top_sources = array();
            
            if (isset($data['rows']) && is_array($data['rows'])) {
                foreach ($data['rows'] as $row) {
                    $source = '';
                    $sessions = 0;
                    $conversions = 0;
                    
                    // Extract dimension values
                    foreach ($row['dimensionValues'] as $index => $dimension_value) {
                        if ($dimensions[$index] === 'sessionSource') {
                            $source = $dimension_value['value'];
                        }
                    }
                    
                    // Extract metric values
                    foreach ($row['metricValues'] as $index => $metric_value) {
                        if ($metrics[$index] === 'sessions') {
                            $sessions = intval($metric_value['value']);
                        } elseif ($metrics[$index] === 'conversions') {
                            $conversions = intval($metric_value['value']);
                        }
                    }
                    
                    // Calculate conversion rate
                    $conversion_rate = $sessions > 0 ? ($conversions / $sessions) * 100 : 0;
                    
                    $top_sources[] = array(
                        'source' => $source,
                        'sessions' => $sessions,
                        'conversion' => round($conversion_rate, 2)
                    );
                }
            }
            
            return $top_sources;
        } else {
            // Define metrics and dimensions for Universal Analytics
            $metrics = array('ga:sessions', 'ga:goalCompletionsAll');
            $dimensions = array('ga:source');
            
            // Get Universal Analytics data
            $data = $this->get_universal_analytics_data($auth_data, $date_range, $metrics, $dimensions, $max_results);
            
            // Check for error
            if (isset($data['error'])) {
                return array();
            }
            
            // Format the data
            $top_sources = array();
            
            if (isset($data['rows']) && is_array($data['rows'])) {
                foreach ($data['rows'] as $row) {
                    $sessions = intval($row[1]);
                    $conversions = intval($row[2]);
                    
                    // Calculate conversion rate
                    $conversion_rate = $sessions > 0 ? ($conversions / $sessions) * 100 : 0;
                    
                    $top_sources[] = array(
                        'source' => $row[0],
                        'sessions' => $sessions,
                        'conversion' => round($conversion_rate, 2)
                    );
                }
            }
            
            return $top_sources;
        }
    }

    /**
     * Get previous period data.
     *
     * @since    1.0.0
     * @param    array     $auth_data     The authentication data.
     * @param    array     $date_range    The date range.
     * @param    array     $metrics       The metrics to fetch.
     * @param    array     $dimensions    The dimensions to fetch.
     * @return   array                    The previous period data.
     */
    private function get_previous_period_data($auth_data, $date_range, $metrics, $dimensions) {
        // Calculate previous period date range
        $current_start = strtotime($date_range['start']);
        $current_end = strtotime($date_range['end']);
        $date_diff = $current_end - $current_start;
        
        $previous_end_date = date('Y-m-d', $current_start - 86400); // Day before current start
        $previous_start_date = date('Y-m-d', $current_start - 86400 - $date_diff); // Same number of days before
        
        $previous_date_range = array(
            'start' => $previous_start_date,
            'end' => $previous_end_date
        );
        
        // Determine if this is GA4 or Universal Analytics
        $is_ga4 = isset($auth_data['gafour']) && $auth_data['gafour'];
        
        if ($is_ga4) {
            $data = $this->get_ga4_data($auth_data, $previous_date_range, $metrics, $dimensions, 1000);
        } else {
            $data = $this->get_universal_analytics_data($auth_data, $previous_date_range, $metrics, $dimensions, 1000);
        }
        
        // Check for error
        if (isset($data['error'])) {
            return array();
        }
        
        return $data;
    }

    /**
     * Format analytics response.
     *
     * @since    1.0.0
     * @param    array     $data              The raw analytics data.
     * @param    array     $top_content       The top content data.
     * @param    array     $top_sources       The top sources data.
     * @param    array     $previous_period   The previous period data.
     * @param    array     $auth_data         The authentication data.
     * @return   array                        The formatted response.
     */
    private function format_analytics_response($data, $top_content, $top_sources, $previous_period, $auth_data) {
        // Determine if this is GA4 or Universal Analytics
        $is_ga4 = isset($auth_data['gafour']) && $auth_data['gafour'];
        
        // Initialize response
        $response = array(
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
                    'sessions' => 0
                )
            ),
            'topContent' => $top_content,
            'topSources' => $top_sources,
            'google_account' => array(
                'view' => $auth_data['view'],
                'code' => $auth_data['code'],
                'token' => $auth_data['token']
            ),
            'gafour' => $is_ga4,
            'property' => $auth_data['view'],
            'measurement_id' => isset($auth_data['measurement_id']) ? $auth_data['measurement_id'] : ''
        );
        
        // Format time series data
        if ($is_ga4) {
            // Process GA4 data
            if (isset($data['rows']) && is_array($data['rows'])) {
                $total_users = 0;
                $total_pageviews = 0;
                $total_sessions = 0;
                
                foreach ($data['rows'] as $row) {
                    $date = '';
                    $users = 0;
                    $pageviews = 0;
                    $sessions = 0;
                    
                    // Extract dimension values (date)
                    foreach ($row['dimensionValues'] as $index => $dimension_value) {
                        if ($data['dimensionHeaders'][$index]['name'] === 'date') {
                            $date = $dimension_value['value'];
                            // Format date from YYYYMMDD to YYYY-MM-DD
                            $date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
                        }
                    }
                    
                    // Extract metric values
                    foreach ($row['metricValues'] as $index => $metric_value) {
                        if ($data['metricHeaders'][$index]['name'] === 'activeUsers') {
                            $users = intval($metric_value['value']);
                            $total_users += $users;
                        } elseif ($data['metricHeaders'][$index]['name'] === 'screenPageViews') {
                            $pageviews = intval($metric_value['value']);
                            $total_pageviews += $pageviews;
                        } elseif ($data['metricHeaders'][$index]['name'] === 'sessions') {
                            $sessions = intval($metric_value['value']);
                            $total_sessions += $sessions;
                        }
                    }
                    
                    $response['data'][] = array(
                        'name' => $date,
                        'value' => $users,
                        'pageviews' => $pageviews,
                        'sessions' => $sessions
                    );
                }
                
                // Update total stats
                $response['totalStats']['users'] = $total_users;
                $response['totalStats']['pageviews'] = $total_pageviews;
                $response['totalStats']['sessions'] = $total_sessions;
                
                // Calculate period-over-period change
                if (isset($previous_period['rows']) && is_array($previous_period['rows'])) {
                    $prev_total_users = 0;
                    $prev_total_pageviews = 0;
                    $prev_total_sessions = 0;
                    
                    foreach ($previous_period['rows'] as $row) {
                        // Extract metric values
                        foreach ($row['metricValues'] as $index => $metric_value) {
                            if ($previous_period['metricHeaders'][$index]['name'] === 'activeUsers') {
                                $prev_total_users += intval($metric_value['value']);
                            } elseif ($previous_period['metricHeaders'][$index]['name'] === 'screenPageViews') {
                                $prev_total_pageviews += intval($metric_value['value']);
                            } elseif ($previous_period['metricHeaders'][$index]['name'] === 'sessions') {
                                $prev_total_sessions += intval($metric_value['value']);
                            }
                        }
                    }
                    
                    // Calculate percentage change
                    $response['totalStats']['change']['users'] = $this->calculate_percentage_change($prev_total_users, $total_users);
                    $response['totalStats']['change']['pageviews'] = $this->calculate_percentage_change($prev_total_pageviews, $total_pageviews);
                    $response['totalStats']['change']['sessions'] = $this->calculate_percentage_change($prev_total_sessions, $total_sessions);
                }
            }
        } else {
            // Process Universal Analytics data
            if (isset($data['rows']) && is_array($data['rows'])) {
                $total_users = 0;
                $total_pageviews = 0;
                $total_sessions = 0;
                
                foreach ($data['rows'] as $row) {
                    $date = $row[0]; // ga:date in YYYYMMDD format
                    // Format date from YYYYMMDD to YYYY-MM-DD
                    $date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
                    
                    $users = intval($row[1]); // ga:users
                    $pageviews = intval($row[2]); // ga:pageviews
                    $sessions = intval($row[3]); // ga:sessions
                    
                    $total_users += $users;
                    $total_pageviews += $pageviews;
                    $total_sessions += $sessions;
                    
                    $response['data'][] = array(
                        'name' => $date,
                        'value' => $users,
                        'pageviews' => $pageviews,
                        'sessions' => $sessions
                    );
                }
                
                // Update total stats
                $response['totalStats']['users'] = $total_users;
                $response['totalStats']['pageviews'] = $total_pageviews;
                $response['totalStats']['sessions'] = $total_sessions;
                
                // Calculate period-over-period change
                if (isset($previous_period['rows']) && is_array($previous_period['rows'])) {
                    $prev_total_users = 0;
                    $prev_total_pageviews = 0;
                    $prev_total_sessions = 0;
                    
                    foreach ($previous_period['rows'] as $row) {
                        $prev_total_users += intval($row[1]); // ga:users
                        $prev_total_pageviews += intval($row[2]); // ga:pageviews
                        $prev_total_sessions += intval($row[3]); // ga:sessions
                    }
                    
                    // Calculate percentage change
                    $response['totalStats']['change']['users'] = $this->calculate_percentage_change($prev_total_users, $total_users);
                    $response['totalStats']['change']['pageviews'] = $this->calculate_percentage_change($prev_total_pageviews, $total_pageviews);
                    $response['totalStats']['change']['sessions'] = $this->calculate_percentage_change($prev_total_sessions, $total_sessions);
                }
            }
        }
        
        return $response;
    }

    /**
     * Calculate percentage change.
     *
     * @since    1.0.0
     * @param    int       $old_value      The old value.
     * @param    int       $new_value      The new value.
     * @return   float                     The percentage change.
     */
    private function calculate_percentage_change($old_value, $new_value) {
        if ($old_value == 0) {
            return $new_value > 0 ? 100 : 0;
        }
        
        $change = (($new_value - $old_value) / $old_value) * 100;
        return round($change, 2);
    }

    /**
     * Extract property ID from view.
     *
     * @since    1.0.0
     * @param    string    $view      The view/property ID.
     * @return   string               The clean property ID.
     */
    private function extract_property_id($view) {
        // GA4 properties start with G-
        if (strpos($view, 'G-') === 0) {
            return $view;
        }
        
        // For numeric IDs, assume UA and return as is
        if (is_numeric($view)) {
            return $view;
        }
        
        // For other formats, do some cleaning
        return preg_replace('/[^\w\-]/', '', $view);
    }

    /**
     * Get cached analytics data.
     *
     * @since    1.0.0
     * @param    string    $view_id      The view/property ID.
     * @param    array     $date_range   The date range.
     * @return   array                   The cached data or false if not cached.
     */
    private function get_cached_analytics_data($view_id, $date_range) {
        $cache_key = 'uipress_analytics_bridge_' . md5($view_id . json_encode($date_range));
        $cached_data = get_transient($cache_key);
        
        if ($cached_data) {
            return $cached_data;
        }
        
        return false;
    }

    /**
     * Cache analytics data.
     *
     * @since    1.0.0
     * @param    array     $data         The data to cache.
     * @param    string    $view_id      The view/property ID.
     * @param    array     $date_range   The date range.
     * @return   bool                    Whether the data was cached successfully.
     */
    private function cache_analytics_data($data, $view_id, $date_range) {
        $cache_key = 'uipress_analytics_bridge_' . md5($view_id . json_encode($date_range));
        $cache_time = 3600; // 1 hour
        
        // Get settings to override cache time if needed
        $settings = get_option('uipress_analytics_bridge_settings', array());
        if (isset($settings['cache_duration']) && is_numeric($settings['cache_duration'])) {
            $cache_time = intval($settings['cache_duration']) * 60; // Convert minutes to seconds
        }
        
        return set_transient($cache_key, $data, $cache_time);
    }

    /**
     * Generate error response.
     *
     * @since    1.0.0
     * @param    string    $message      The error message.
     * @param    string    $error_type   The error type.
     * @return   array                   The error response.
     */
    private function generate_error_response($message, $error_type = 'api_error') {
        return array(
            'success' => false,
            'error' => true,
            'message' => $message,
            'error_type' => $error_type,
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
            'topSources' => array()
        );
    }

    /**
     * Save authentication data.
     *
     * @since    1.0.0
     * @param    array     $auth_data            The authentication data.
     * @param    bool      $use_user_preferences Whether to use user preferences.
     * @return   bool                           Whether the save was successful.
     */
    private function save_auth_data($auth_data, $use_user_preferences = false) {
        // Use WordPress user meta or options based on preference
        if ($use_user_preferences) {
            // Use UserPreferences class if available
            if (class_exists('UipressLite\Classes\App\UserPreferences')) {
                \UipressLite\Classes\App\UserPreferences::update('google_analytics', $auth_data);
                return true;
            } else {
                // Fallback to direct user meta
                $user_id = get_current_user_id();
                $user_prefs = get_user_meta($user_id, 'uip-prefs', true);
                
                if (!is_array($user_prefs)) {
                    $user_prefs = array();
                }
                
                $user_prefs['google_analytics'] = $auth_data;
                return update_user_meta($user_id, 'uip-prefs', $user_prefs);
            }
        } else {
            // Use UipOptions class if available
            if (class_exists('UipressLite\Classes\App\UipOptions')) {
                \UipressLite\Classes\App\UipOptions::update('google_analytics', $auth_data);
                return true;
            } else {
                // Fallback to direct option
                $options = get_option('uip-global-settings', array());
                
                if (!is_array($options)) {
                    $options = array();
                }
                
                $options['google_analytics'] = $auth_data;
                return update_option('uip-global-settings', $options);
            }
        }
    }
}