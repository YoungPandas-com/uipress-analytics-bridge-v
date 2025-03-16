<?php
/**
 * UIPress Analytics Bridge
 *
 * @package     UIPress_Analytics_Bridge
 * @author      Young Pandas
 * @copyright   2025 Young Pandas
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: UIPress Analytics Bridge
 * Plugin URI:  https://yp.studio
 * Description: Enhanced Google Analytics integration for UIPress Pro with improved authentication and data retrieval.
 * Version:     1.0.0
 * Author:      Young Pandas
 * Author URI:  https://yp.studio
 * Text Domain: uipress-analytics-bridge
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UIPRESS_ANALYTICS_BRIDGE_VERSION', '1.0.0');
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_NAME', 'UIPress Analytics Bridge');
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the admin class
require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'admin/class-uipress-analytics-bridge-admin.php';

/**
 * Direct registration of admin page - guaranteed to work
 */
function uipress_analytics_bridge_admin_init() {
    // Initialize admin class
    $admin = new UIPress_Analytics_Bridge_Admin('uipress-analytics-bridge', UIPRESS_ANALYTICS_BRIDGE_VERSION);
    
    // Load admin styles and scripts
    add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
    add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));
    
    // Add settings link in plugins list
    add_filter('plugin_action_links_' . UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME, 
        array($admin, 'add_action_links')
    );
    
    // Register AJAX handlers
    add_action('wp_ajax_uipress_analytics_bridge_connect', array($admin, 'connect_to_google_analytics'));
    add_action('wp_ajax_uipress_analytics_bridge_get_properties', 'uipress_analytics_bridge_get_properties');
    add_action('wp_ajax_uipress_analytics_bridge_save_property', 'uipress_analytics_bridge_save_selected_property');
    add_action('wp_ajax_uipress_analytics_bridge_disconnect', 'uipress_analytics_bridge_simple_disconnect');
    add_action('wp_ajax_uipress_analytics_bridge_clear_cache', 'uipress_analytics_bridge_clear_cache');
    add_action('wp_ajax_uipress_analytics_bridge_oauth_callback', 'uipress_analytics_bridge_simple_oauth_callback');
}

/**
 * Handle OAuth callback from Google
 */
function uipress_analytics_bridge_simple_oauth_callback() {
    // Check if we got a code
    if (isset($_GET['code']) && !empty($_GET['code'])) {
        $auth_code = $_GET['code'];
        
        // Get settings for API credentials
        $settings = get_option('uipress_analytics_bridge_settings', array());
        $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
        
        if (empty($client_id) || empty($client_secret)) {
            wp_die('Error: Missing API credentials. Please configure your Google API settings.');
        }
        
        // Exchange auth code for tokens
        $redirect_uri = admin_url('admin-ajax.php?action=uipress_analytics_bridge_oauth_callback');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => $auth_code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_die('Error exchanging authorization code: ' . $response->get_error_message());
        }
        
        $tokens = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($tokens['access_token'])) {
            wp_die('Error: Failed to receive access token from Google.');
        }
        
        // Store tokens temporarily
        update_option('uipress_analytics_bridge_temp_tokens', array(
            'access_token' => $tokens['access_token'],
            'refresh_token' => isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '',
            'expires' => time() + $tokens['expires_in']
        ));
        
        // Redirect to property selection page
        wp_redirect(admin_url('options-general.php?page=uipress-analytics-bridge&tab=select-property'));
        exit;
    } else {
        wp_die('Error: No authorization code received from Google.');
    }
}

/**
 * Get Google Analytics properties for an account
 */
function uipress_analytics_bridge_get_properties() {
    check_ajax_referer('uipress-analytics-bridge-admin-nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'uipress-analytics-bridge')));
        return;
    }
    
    $account_id = isset($_POST['account_id']) ? sanitize_text_field($_POST['account_id']) : '';
    
    if (empty($account_id)) {
        wp_send_json_error(array('message' => __('No account ID provided.', 'uipress-analytics-bridge')));
        return;
    }
    
    // Get tokens
    $tokens = get_option('uipress_analytics_bridge_temp_tokens', array());
    
    if (empty($tokens) || empty($tokens['access_token'])) {
        wp_send_json_error(array('message' => __('No authentication data found.', 'uipress-analytics-bridge')));
        return;
    }
    
    // Fetch properties for the account
    $response = wp_remote_get('https://www.googleapis.com/analytics/v3/management/accounts/' . $account_id . '/webproperties', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $tokens['access_token']
        )
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
        return;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($data['error'])) {
        wp_send_json_error(array('message' => $data['error']['message']));
        return;
    }
    
    $properties = array();
    
    if (isset($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $item) {
            // Determine if this is GA4 or Universal Analytics
            $is_ga4 = (isset($item['id']) && strpos($item['id'], 'G-') === 0);
            
            $properties[] = array(
                'id' => $item['id'],
                'name' => $item['name'], // Just the name without type
                'type' => $is_ga4 ? 'GA4' : 'UA',
                'measurement_id' => $is_ga4 ? $item['id'] : ''
            );
        }
    }
    
    wp_send_json_success(array('properties' => $properties));
}

/**
 * Save selected Google Analytics property
 */
function uipress_analytics_bridge_save_selected_property() {
    check_ajax_referer('uipress-analytics-bridge-admin-nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'uipress-analytics-bridge')));
        return;
    }
    
    $property_id = isset($_POST['ga_property']) ? sanitize_text_field($_POST['ga_property']) : '';
    $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'global';
    
    if (empty($property_id)) {
        wp_send_json_error(array('message' => __('No property selected.', 'uipress-analytics-bridge')));
        return;
    }
    
    // Get tokens
    $tokens = get_option('uipress_analytics_bridge_temp_tokens', array());
    
    if (empty($tokens) || empty($tokens['access_token'])) {
        wp_send_json_error(array('message' => __('No authentication data found.', 'uipress-analytics-bridge')));
        return;
    }
    
    // Get property details from API to ensure accuracy
    $access_token = $tokens['access_token'];
    
    // Find the account ID from the form submission
    $account_id = isset($_POST['ga_account']) ? sanitize_text_field($_POST['ga_account']) : '';
    
    if (empty($account_id)) {
        wp_send_json_error(array('message' => __('No account selected.', 'uipress-analytics-bridge')));
        return;
    }
    
    // Fetch properties for the account to get details for the selected property
    $response = wp_remote_get('https://www.googleapis.com/analytics/v3/management/accounts/' . $account_id . '/webproperties', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token
        )
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
        return;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Find the selected property in the response
    $selected_property = null;
    if (isset($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $item) {
            if ($item['id'] === $property_id) {
                $selected_property = $item;
                break;
            }
        }
    }
    
    if ($selected_property === null) {
        wp_send_json_error(array('message' => __('Selected property not found in account.', 'uipress-analytics-bridge')));
        return;
    }
    
    // Determine if this is GA4 or Universal Analytics
    $is_ga4 = (strpos($property_id, 'G-') === 0);
    
    // Save connection data
    $connection = array(
        'property_id' => $property_id,
        'property_name' => $selected_property['name'],
        'property_type' => $is_ga4 ? 'GA4' : 'Universal Analytics',
        'scope' => $scope,
        'access_token' => $tokens['access_token'],
        'refresh_token' => isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '',
        'expires' => isset($tokens['expires']) ? $tokens['expires'] : 0,
        'timestamp' => time()
    );
    
    // Add measurement ID for GA4
    if ($is_ga4) {
        $connection['measurement_id'] = $property_id;
    }
    
    update_option('uipress_analytics_bridge_connection', $connection);
    
    // Update UIPress settings
    uipress_analytics_bridge_update_uipress_settings($connection);
    
    // Clean up temporary tokens
    delete_option('uipress_analytics_bridge_temp_tokens');
    
    // Redirect to connection tab with success message
    wp_redirect(admin_url('options-general.php?page=uipress-analytics-bridge&tab=connection&connected=1'));
    exit;
}

/**
 * Update UIPress settings with connection data
 * 
 * @param array $connection The connection data
 */
function uipress_analytics_bridge_update_uipress_settings($connection) {
    // Only proceed if UIPress is active
    if (!defined('uip_plugin_version') || !defined('uip_pro_plugin_version')) {
        return;
    }
    
    // Prepare Google Analytics data in UIPress format
    $ga_data = array(
        'view' => $connection['property_id'],
        'code' => 'bridge_code',
        'token' => $connection['access_token']
    );
    
    // Add measurement ID for GA4
    if (isset($connection['measurement_id']) && !empty($connection['measurement_id'])) {
        $ga_data['measurement_id'] = $connection['measurement_id'];
        $ga_data['gafour'] = true;
    }
    
    // Update based on scope
    if ($connection['scope'] === 'user') {
        // User preferences
        if (class_exists('UipressLite\Classes\App\UserPreferences')) {
            \UipressLite\Classes\App\UserPreferences::update('google_analytics', $ga_data);
        } else {
            // Fallback
            $user_id = get_current_user_id();
            $prefs = get_user_meta($user_id, 'uip-prefs', true);
            if (!is_array($prefs)) {
                $prefs = array();
            }
            $prefs['google_analytics'] = $ga_data;
            update_user_meta($user_id, 'uip-prefs', $prefs);
        }
    } else {
        // Global options
        if (class_exists('UipressLite\Classes\App\UipOptions')) {
            \UipressLite\Classes\App\UipOptions::update('google_analytics', $ga_data);
        } else {
            // Fallback
            $options = get_option('uip-global-settings', array());
            if (!is_array($options)) {
                $options = array();
            }
            $options['google_analytics'] = $ga_data;
            update_option('uip-global-settings', $options);
        }
    }
}

/**
 * Disconnect from Google Analytics
 */
function uipress_analytics_bridge_simple_disconnect() {
    // Verify nonce
    check_ajax_referer('uipress-analytics-bridge-admin-nonce', 'security');
    
    // Delete the connection
    delete_option('uipress_analytics_bridge_connection');
    
    // Clear UIPress settings if available
    if (class_exists('UipressLite\Classes\App\UipOptions')) {
        \UipressLite\Classes\App\UipOptions::update('google_analytics', false);
    }
    
    // Redirect back to the settings page
    wp_redirect(admin_url('options-general.php?page=uipress-analytics-bridge&disconnected=1'));
    exit;
}

/**
 * Handle OAuth callback from Google
 */
function uipress_analytics_bridge_oauth_callback() {
    // This would be the page that loads in the popup after Google auth
    // For demonstration we'll create a simple page that closes itself and signals the parent window
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Google Authentication</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding: 50px;
                background: #f8f9fa;
            }
            .success {
                color: #28a745;
                font-size: 24px;
                margin-bottom: 20px;
            }
            .loading {
                display: inline-block;
                width: 50px;
                height: 50px;
                border: 3px solid rgba(0,0,0,.3);
                border-radius: 50%;
                border-top-color: #007bff;
                animation: spin 1s ease-in-out infinite;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="success">Authentication Successful!</div>
        <div class="loading"></div>
        <p>This window will close automatically...</p>
        
        <script>
            // Send message to parent window
            window.opener.postMessage({
                type: 'uipress_analytics_bridge_auth',
                success: true,
                code: '<?php echo isset($_GET['code']) ? esc_js($_GET['code']) : ''; ?>',
                state: '<?php echo isset($_GET['state']) ? esc_js($_GET['state']) : ''; ?>'
            }, '*');
            
            // Close this window after a short delay
            setTimeout(function() {
                window.close();
            }, 3000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Disconnect from Google Analytics
 */
function uipress_analytics_bridge_disconnect() {
    // Verify nonce
    check_ajax_referer('uipress-analytics-bridge-admin-nonce', 'security');
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'uipress-analytics-bridge')));
        return;
    }
    
    // Delete the connection data
    delete_option('uipress_analytics_bridge_connection');
    
    // Update UIPress settings if UIPress is active
    if (defined('uip_plugin_version') && defined('uip_pro_plugin_version')) {
        // Clear global connection
        if (class_exists('UipressLite\Classes\App\UipOptions')) {
            \UipressLite\Classes\App\UipOptions::update('google_analytics', false);
        } else {
            $options = get_option('uip-global-settings', array());
            if (isset($options['google_analytics'])) {
                $options['google_analytics'] = false;
                update_option('uip-global-settings', $options);
            }
        }
        
        // Clear user connection
        if (class_exists('UipressLite\Classes\App\UserPreferences')) {
            \UipressLite\Classes\App\UserPreferences::update('google_analytics', false);
        } else {
            $user_id = get_current_user_id();
            $prefs = get_user_meta($user_id, 'uip-prefs', true);
            if (isset($prefs['google_analytics'])) {
                $prefs['google_analytics'] = false;
                update_user_meta($user_id, 'uip-prefs', $prefs);
            }
        }
    }
    
    // Clear cache
    uipress_analytics_bridge_clear_cache_data();
    
    wp_send_json_success(array(
        'message' => __('Successfully disconnected from Google Analytics.', 'uipress-analytics-bridge')
    ));
}

/**
 * Clear analytics cache
 */
function uipress_analytics_bridge_clear_cache() {
    // Verify nonce
    check_ajax_referer('uipress-analytics-bridge-admin-nonce', 'security');
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'uipress-analytics-bridge')));
        return;
    }
    
    // Clear the cache
    uipress_analytics_bridge_clear_cache_data();
    
    wp_send_json_success(array(
        'message' => __('Cache cleared successfully.', 'uipress-analytics-bridge')
    ));
}

/**
 * Clear all cache data
 */
function uipress_analytics_bridge_clear_cache_data() {
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

/**
 * Add activation notice
 */
function uipress_analytics_bridge_activation_notice() {
    if (get_transient('uipress_analytics_bridge_activation_notice')) {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php _e('Thank you for installing UIPress Analytics Bridge!', 'uipress-analytics-bridge'); ?> 
                <a href="<?php echo admin_url('options-general.php?page=uipress-analytics-bridge'); ?>"><?php _e('Click here to configure the plugin settings', 'uipress-analytics-bridge'); ?></a>
            </p>
        </div>
        <?php
        delete_transient('uipress_analytics_bridge_activation_notice');
    }
}
add_action('admin_notices', 'uipress_analytics_bridge_activation_notice');

/**
 * The code that runs during plugin activation.
 */
function activate_uipress_analytics_bridge() {
    // Create default settings if they don't exist
    if (!get_option('uipress_analytics_bridge_settings')) {
        $default_settings = array(
            'client_id' => '',
            'client_secret' => '',
            'cache_duration' => 60, // 60 minutes default cache
            'remove_data_on_uninstall' => false,
        );
        
        update_option('uipress_analytics_bridge_settings', $default_settings);
    }
    
    // Create default advanced settings if they don't exist
    if (!get_option('uipress_analytics_bridge_advanced')) {
        $default_advanced = array(
            'debug_mode' => false,
            'compatibility_mode' => false,
            'clear_cache_cron' => true,
        );
        
        update_option('uipress_analytics_bridge_advanced', $default_advanced);
    }
    
    // Set transient for activation notice
    set_transient('uipress_analytics_bridge_activation_notice', true, DAY_IN_SECONDS * 3);
}

register_activation_hook(__FILE__, 'activate_uipress_analytics_bridge');

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_uipress_analytics_bridge() {
    // Clear any cron jobs
    wp_clear_scheduled_hook('uipress_analytics_bridge_clear_cache');
    
    // Clear transients
    delete_transient('uipress_analytics_bridge_activation_notice');
    
    // Clear caches
    uipress_analytics_bridge_clear_cache_data();
}

register_deactivation_hook(__FILE__, 'deactivate_uipress_analytics_bridge');

// Initialize admin functionality
uipress_analytics_bridge_admin_init();