<?php
/**
 * Google API authentication handler.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes/api
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_API_Auth {

    /**
     * Google API client ID.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $client_id    The Google API client ID.
     */
    private $client_id;

    /**
     * Google API client secret.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $client_secret    The Google API client secret.
     */
    private $client_secret;

    /**
     * Google API redirect URI.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $redirect_uri    The Google API redirect URI.
     */
    private $redirect_uri;

    /**
     * Google API scopes.
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
     */
    public function __construct() {
        // Get the plugin settings
        $settings = get_option('uipress_analytics_bridge_settings', array());
        
        // Set the client ID and secret
        $this->client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        $this->client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
        
        // Set the redirect URI
        $this->redirect_uri = admin_url('admin-ajax.php?action=uipress_analytics_bridge_auth_callback');
        
        // Set the scopes
        $this->scopes = array(
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/analytics',
        );
        
        // If no client ID/secret is set, use the default proxy service
        if (empty($this->client_id) || empty($this->client_secret)) {
            $this->use_proxy_service();
        }
    }

    /**
     * Use the proxy service for authentication.
     *
     * @since    1.0.0
     */
    private function use_proxy_service() {
        // Default to using the proxy service
        $this->client_id = '123456789012-abcdefghijklmnopqrstuvwxyz123456.apps.googleusercontent.com';
        $this->client_secret = 'PROXY_SERVICE_SECRET';
        
        // The proxy service uses a different redirect URI
        $this->redirect_uri = 'https://uipress-analytics-bridge.example.com/oauth/callback';
    }

    /**
     * Get the authorization URL for Google OAuth.
     *
     * @since    1.0.0
     * @return   string    The authorization URL.
     */
    public function get_auth_url() {
        // Base URL for Google's OAuth 2.0 server
        $auth_url = 'https://accounts.google.com/o/oauth2/auth';
        
        // Convert scopes array to space-delimited string
        $scopes_string = implode(' ', $this->scopes);
        
        // Build the query parameters
        $query_params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => $scopes_string,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => wp_create_nonce('uipress-analytics-bridge-auth'),
        );
        
        // Construct the full URL
        $auth_url .= '?' . http_build_query($query_params);
        
        return $auth_url;
    }

    /**
     * Exchange an authorization code for an access token.
     *
     * @since    1.0.0
     * @param    string    $code    The authorization code.
     * @return   array|WP_Error    The token data or an error.
     */
    public function exchange_code_for_token($code) {
        // Token endpoint URL
        $token_url = 'https://oauth2.googleapis.com/token';
        
        // Request parameters
        $params = array(
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
        );
        
        // Make the request
        $response = wp_remote_post($token_url, array(
            'body' => $params,
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
            return new WP_Error('token_exchange_error', $data['error_description'] ?? $data['error']);
        }
        
        // Validate the response data
        if (!isset($data['access_token']) || !isset($data['expires_in'])) {
            return new WP_Error('invalid_token_response', __('Invalid token response from Google.', 'uipress-analytics-bridge'));
        }
        
        // Return the token data
        return array(
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_in' => $data['expires_in'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => $data['scope'] ?? implode(' ', $this->scopes),
        );
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * @since    1.0.0
     * @param    string    $refresh_token    The refresh token.
     * @return   array|WP_Error    The token data or an error.
     */
    public function refresh_access_token($refresh_token) {
        // Token endpoint URL
        $token_url = 'https://oauth2.googleapis.com/token';
        
        // Request parameters
        $params = array(
            'refresh_token' => $refresh_token,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'refresh_token',
        );
        
        // Make the request
        $response = wp_remote_post($token_url, array(
            'body' => $params,
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
            return new WP_Error('token_refresh_error', $data['error_description'] ?? $data['error']);
        }
        
        // Validate the response data
        if (!isset($data['access_token']) || !isset($data['expires_in'])) {
            return new WP_Error('invalid_token_response', __('Invalid token response from Google.', 'uipress-analytics-bridge'));
        }
        
        // Return the token data (note: refresh token is typically not included in refresh responses)
        return array(
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => $data['scope'] ?? implode(' ', $this->scopes),
        );
    }

    /**
     * Validate an access token.
     *
     * @since    1.0.0
     * @param    string    $access_token    The access token to validate.
     * @return   bool|WP_Error    True if valid, or an error.
     */
    public function validate_access_token($access_token) {
        // Token info endpoint URL
        $token_info_url = 'https://oauth2.googleapis.com/tokeninfo';
        
        // Build the query parameters
        $query_params = array(
            'access_token' => $access_token,
        );
        
        // Construct the full URL
        $url = $token_info_url . '?' . http_build_query($query_params);
        
        // Make the request
        $response = wp_remote_get($url, array(
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
            return new WP_Error('token_validation_error', $data['error_description'] ?? $data['error']);
        }
        
        // Check if the token is valid for the client ID
        if (isset($data['aud']) && $data['aud'] !== $this->client_id) {
            return new WP_Error('invalid_client', __('Token is not valid for this client.', 'uipress-analytics-bridge'));
        }
        
        // Check if the token has the required scopes
        if (isset($data['scope'])) {
            $token_scopes = explode(' ', $data['scope']);
            $required_scopes = $this->scopes;
            
            foreach ($required_scopes as $required_scope) {
                if (!in_array($required_scope, $token_scopes)) {
                    return new WP_Error('insufficient_scope', __('Token does not have the required scopes.', 'uipress-analytics-bridge'));
                }
            }
        }
        
        // Token is valid
        return true;
    }

    /**
     * Revoke an access token.
     *
     * @since    1.0.0
     * @param    string    $token    The token to revoke.
     * @return   bool|WP_Error    True if revoked, or an error.
     */
    public function revoke_token($token) {
        // Revoke endpoint URL
        $revoke_url = 'https://oauth2.googleapis.com/revoke';
        
        // Request parameters
        $params = array(
            'token' => $token,
        );
        
        // Make the request
        $response = wp_remote_post($revoke_url, array(
            'body' => $params,
            'timeout' => 15,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Check the response code
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return true;
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return new WP_Error('token_revocation_error', $data['error_description'] ?? __('Failed to revoke token.', 'uipress-analytics-bridge'));
        }
    }
}