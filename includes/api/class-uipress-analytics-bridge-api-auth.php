<?php
/**
 * The class responsible for Google API authentication.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes/api
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_API_Auth {

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
     * The Google API client ID.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $client_id    The Google API client ID.
     */
    private $client_id;

    /**
     * The Google API client secret.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $client_secret    The Google API client secret.
     */
    private $client_secret;

    /**
     * The Google API redirect URI.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $redirect_uri    The Google API redirect URI.
     */
    private $redirect_uri;

    /**
     * The Google API scopes.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $scopes    The Google API scopes.
     */
    private $scopes;

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
        
        // Get settings
        $settings = get_option('uipress_analytics_bridge_settings', array());
        
        // Set API credentials
        $this->client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        $this->client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
        
        // Set redirect URI
        $this->redirect_uri = admin_url('admin-ajax.php?action=uipress_analytics_bridge_auth_callback');
        
        // Set scopes
        $this->scopes = array(
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/userinfo.email'
        );
    }

    /**
     * Get authorization URL.
     *
     * @since    1.0.0
     * @return   string    The authorization URL.
     */
    public function get_auth_url() {
        // Check if API credentials are set
        if (empty($this->client_id)) {
            return false;
        }
        
        // Create state parameter for security
        $state = wp_create_nonce('uipress_analytics_bridge_auth');
        update_option('uipress_analytics_bridge_auth_state', $state);
        
        // Build scopes
        $scopes_url = implode(' ', $this->scopes);
        
        // Build authorization URL
        $auth_url = 'https://accounts.google.com/o/oauth2/auth';
        $auth_url .= '?client_id=' . urlencode($this->client_id);
        $auth_url .= '&redirect_uri=' . urlencode($this->redirect_uri);
        $auth_url .= '&scope=' . urlencode($scopes_url);
        $auth_url .= '&access_type=offline';
        $auth_url .= '&response_type=code';
        $auth_url .= '&prompt=consent';
        $auth_url .= '&state=' . urlencode($state);
        
        return $auth_url;
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @since    1.0.0
     * @param    string    $code    The authorization code.
     * @return   array              The tokens.
     */
    public function exchange_code_for_tokens($code) {
        // Check if API credentials are set
        if (empty($this->client_id) || empty($this->client_secret)) {
            return array('error' => 'missing_credentials', 'error_description' => 'API credentials are not configured');
        }
        
        // Exchange code for tokens
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            )
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('UIPress Analytics Bridge - Token Exchange Error: ' . $response->get_error_message());
            return array('error' => 'wp_error', 'error_description' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for errors in response
        if (isset($data['error'])) {
            error_log('UIPress Analytics Bridge - Token Exchange Error: ' . $data['error_description']);
            return $data;
        }
        
        // Return tokens
        return $data;
    }

    /**
     * Refresh access token.
     *
     * @since    1.0.0
     * @param    string    $refresh_token    The refresh token.
     * @return   array                       The tokens.
     */
    public function refresh_access_token($refresh_token) {
        // Check if API credentials are set
        if (empty($this->client_id) || empty($this->client_secret)) {
            return array('error' => 'missing_credentials', 'error_description' => 'API credentials are not configured');
        }
        
        // Refresh token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'refresh_token' => $refresh_token,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token'
            )
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('UIPress Analytics Bridge - Token Refresh Error: ' . $response->get_error_message());
            return array('error' => 'wp_error', 'error_description' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for errors in response
        if (isset($data['error'])) {
            error_log('UIPress Analytics Bridge - Token Refresh Error: ' . $data['error_description']);
            return $data;
        }
        
        // Return tokens
        return $data;
    }

    /**
     * Get user information from Google.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token.
     * @return   array                      The user information.
     */
    public function get_user_info($access_token) {
        // Make request to Google API
        $response = wp_remote_get('https://www.googleapis.com/oauth2/v1/userinfo?alt=json', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('UIPress Analytics Bridge - User Info Error: ' . $response->get_error_message());
            return array('error' => 'wp_error', 'error_description' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for errors in response
        if (isset($data['error'])) {
            error_log('UIPress Analytics Bridge - User Info Error: ' . $data['error']['message']);
            return array('error' => $data['error']['code'], 'error_description' => $data['error']['message']);
        }
        
        // Return user info
        return $data;
    }

    /**
     * List Google Analytics accounts.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token.
     * @return   array                      The accounts list.
     */
    public function list_analytics_accounts($access_token) {
        // Make request to Google Analytics API
        $response = wp_remote_get('https://www.googleapis.com/analytics/v3/management/accounts', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('UIPress Analytics Bridge - List Accounts Error: ' . $response->get_error_message());
            return array('error' => 'wp_error', 'error_description' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for errors in response
        if (isset($data['error'])) {
            error_log('UIPress Analytics Bridge - List Accounts Error: ' . $data['error']['message']);
            return array('error' => $data['error']['code'], 'error_description' => $data['error']['message']);
        }
        
        // Return accounts
        return $data;
    }

    /**
     * List Google Analytics properties for an account.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token.
     * @param    string    $account_id      The account ID.
     * @return   array                      The properties list.
     */
    public function list_analytics_properties($access_token, $account_id) {
        // Make request to Google Analytics API
        $response = wp_remote_get('https://www.googleapis.com/analytics/v3/management/accounts/' . $account_id . '/webproperties', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('UIPress Analytics Bridge - List Properties Error: ' . $response->get_error_message());
            return array('error' => 'wp_error', 'error_description' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for errors in response
        if (isset($data['error'])) {
            error_log('UIPress Analytics Bridge - List Properties Error: ' . $data['error']['message']);
            return array('error' => $data['error']['code'], 'error_description' => $data['error']['message']);
        }
        
        // Return properties
        return $data;
    }

    /**
     * List Google Analytics views for a property.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token.
     * @param    string    $account_id      The account ID.
     * @param    string    $property_id     The property ID.
     * @return   array                      The views list.
     */
    public function list_analytics_views($access_token, $account_id, $property_id) {
        // Make request to Google Analytics API
        $response = wp_remote_get('https://www.googleapis.com/analytics/v3/management/accounts/' . $account_id . '/webproperties/' . $property_id . '/profiles', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('UIPress Analytics Bridge - List Views Error: ' . $response->get_error_message());
            return array('error' => 'wp_error', 'error_description' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for errors in response
        if (isset($data['error'])) {
            error_log('UIPress Analytics Bridge - List Views Error: ' . $data['error']['message']);
            return array('error' => $data['error']['code'], 'error_description' => $data['error']['message']);
        }
        
        // Return views
        return $data;
    }

    /**
     * Check if an access token is valid.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token.
     * @return   bool                       Whether the token is valid.
     */
    public function is_token_valid($access_token) {
        // Make request to Google API
        $response = wp_remote_get('https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . urlencode($access_token));
        
        // Check for errors
        if (is_wp_error($response)) {
            return false;
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for errors in response
        if (isset($data['error'])) {
            return false;
        }
        
        // Check if token is for our client ID
        if (isset($data['audience']) && $data['audience'] !== $this->client_id) {
            return false;
        }
        
        return true;
    }

    /**
     * Get access token from account data.
     *
     * @since    1.0.0
     * @param    array     $account_data    The account data.
     * @return   string                     The access token.
     */
    public function get_access_token($account_data) {
        // Check if account data is valid
        if (!is_array($account_data) || !isset($account_data['token'])) {
            return false;
        }
        
        $access_token = $account_data['token'];
        
        // Check if token is expired
        if (isset($account_data['expires']) && $account_data['expires'] < time()) {
            // Check if we have a refresh token
            if (!isset($account_data['refresh_token'])) {
                return false;
            }
            
            // Refresh the token
            $tokens = $this->refresh_access_token($account_data['refresh_token']);
            
            if (!$tokens || isset($tokens['error'])) {
                return false;
            }
            
            // Update the account data
            $account_data['token'] = $tokens['access_token'];
            $account_data['expires'] = time() + $tokens['expires_in'];
            
            // Save the account data
            $this->save_account_data($account_data);
            
            $access_token = $tokens['access_token'];
        }
        
        return $access_token;
    }

    /**
     * Save account data.
     *
     * @since    1.0.0
     * @param    array     $account_data    The account data.
     * @param    bool      $use_user_preferences    Whether to use user preferences.
     * @return   bool                       Whether the save was successful.
     */
    private function save_account_data($account_data, $use_user_preferences = false) {
        // Use WordPress user meta or options based on preference
        if ($use_user_preferences) {
            // Use UserPreferences class if available
            if (class_exists('UipressLite\Classes\App\UserPreferences')) {
                \UipressLite\Classes\App\UserPreferences::update('google_analytics', $account_data);
                return true;
            } else {
                // Fallback to direct user meta
                $user_id = get_current_user_id();
                $user_prefs = get_user_meta($user_id, 'uip-prefs', true);
                
                if (!is_array($user_prefs)) {
                    $user_prefs = array();
                }
                
                $user_prefs['google_analytics'] = $account_data;
                return update_user_meta($user_id, 'uip-prefs', $user_prefs);
            }
        } else {
            // Use UipOptions class if available
            if (class_exists('UipressLite\Classes\App\UipOptions')) {
                \UipressLite\Classes\App\UipOptions::update('google_analytics', $account_data);
                return true;
            } else {
                // Fallback to direct option
                $options = get_option('uip-global-settings', array());
                
                if (!is_array($options)) {
                    $options = array();
                }
                
                $options['google_analytics'] = $account_data;
                return update_option('uip-global-settings', $options);
            }
        }
    }
}