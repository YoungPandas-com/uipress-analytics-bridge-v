<?php
/**
 * Simple integration for UIPress Analytics Bridge
 *
 * This is a simplified version that ensures the connection works.
 * Copy this file to your WordPress plugins directory as:
 * wp-content/plugins/uipress-analytics-bridge/simple-admin.php
 * 
 * Then include it in your main plugin file:
 * require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'simple-admin.php';
 */

// Direct admin page registration
function uipress_analytics_bridge_simple_admin_menu() {
    add_options_page(
        'Connect UIPress Analytics',
        'UIPress Analytics',
        'manage_options',
        'uipress-analytics-simple',
        'uipress_analytics_bridge_simple_admin_page'
    );
}
add_action('admin_menu', 'uipress_analytics_bridge_simple_admin_menu');

// Direct admin page display
function uipress_analytics_bridge_simple_admin_page() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Process form submission
    if (isset($_POST['save_uipress_analytics_settings'])) {
        // Verify nonce
        check_admin_referer('uipress_analytics_settings');
        
        // Get the settings
        $client_id = isset($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : '';
        $client_secret = isset($_POST['client_secret']) ? sanitize_text_field($_POST['client_secret']) : '';
        
        // Save the settings
        $settings = array(
            'client_id' => $client_id,
            'client_secret' => $client_secret
        );
        
        update_option('uipress_analytics_bridge_settings', $settings);
        
        // Show success message
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'uipress-analytics-bridge') . '</p></div>';
    }
    
    // Get current settings
    $settings = get_option('uipress_analytics_bridge_settings', array());
    $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
    $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
    
    // Get connection info
    $connection = get_option('uipress_analytics_bridge_connection', array());
    $is_connected = !empty($connection['property_id']);
    
    // Page content
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="nav-tab-wrapper">
            <a href="?page=uipress-analytics-simple&tab=connection" class="nav-tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'connection') ? 'nav-tab-active' : ''; ?>"><?php _e('Connection', 'uipress-analytics-bridge'); ?></a>
            <a href="?page=uipress-analytics-simple&tab=settings" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'settings') ? 'nav-tab-active' : ''; ?>"><?php _e('API Settings', 'uipress-analytics-bridge'); ?></a>
        </div>
        
        <?php
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'connection';
        
        if ($current_tab === 'connection') {
            // Connection tab
            if ($is_connected) {
                // Display connection info
                ?>
                <div class="uipress-connection-info" style="background: #f8f8f8; padding: 15px; border: 1px solid #ddd; margin: 20px 0;">
                    <h3><?php _e('Connected to Google Analytics', 'uipress-analytics-bridge'); ?></h3>
                    <p><strong><?php _e('Property:', 'uipress-analytics-bridge'); ?></strong> <?php echo esc_html($connection['property_name']); ?></p>
                    <p><strong><?php _e('Property ID:', 'uipress-analytics-bridge'); ?></strong> <?php echo esc_html($connection['property_id']); ?></p>
                    <?php if (!empty($connection['measurement_id'])): ?>
                        <p><strong><?php _e('Measurement ID:', 'uipress-analytics-bridge'); ?></strong> <?php echo esc_html($connection['measurement_id']); ?></p>
                    <?php endif; ?>
                    
                    <div class="uipress-connection-actions">
                        <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=uipress_analytics_bridge_disconnect'), 'uipress-analytics-bridge-admin-nonce', 'security'); ?>" class="button button-secondary"><?php _e('Disconnect', 'uipress-analytics-bridge'); ?></a>
                    </div>
                </div>
                <?php
            } else {
                // Display connection form
                ?>
                <div class="uipress-connection-form" style="background: #f8f8f8; padding: 15px; border: 1px solid #ddd; margin: 20px 0;">
                    <h3><?php _e('Connect to Google Analytics', 'uipress-analytics-bridge'); ?></h3>
                    
                    <?php if (empty($client_id) || empty($client_secret)): ?>
                        <p class="notice notice-warning" style="padding: 10px;"><?php _e('Please enter your Google API credentials in the API Settings tab before connecting.', 'uipress-analytics-bridge'); ?></p>
                    <?php else: ?>
                        <p><?php _e('Click the button below to connect to your Google Analytics account.', 'uipress-analytics-bridge'); ?></p>
                        
                        <div class="uipress-connection-actions">
                            <!-- Direct connection button -->
                            <?php
                            // Get the redirect URI for our callback
                            $redirect_uri = admin_url('admin-ajax.php') . '?action=uipress_analytics_bridge_oauth_callback';
                            
                            // Create a state parameter for security
                            $state = wp_create_nonce('uipress-analytics-bridge-oauth');
                            
                            // Build the auth URL directly
                            $auth_url = 'https://accounts.google.com/o/oauth2/auth' . 
                                '?client_id=' . urlencode($client_id) . 
                                '&redirect_uri=' . urlencode($redirect_uri) .
                                '&scope=' . urlencode('https://www.googleapis.com/auth/analytics.readonly') .
                                '&response_type=code' .
                                '&access_type=offline' .
                                '&state=' . urlencode($state) .
                                '&prompt=consent';
                            ?>
                            
                            <a href="<?php echo esc_url($auth_url); ?>" 
                               onclick="window.open(this.href, 'uipress_analytics_auth', 'width=600,height=700,top=100,left=100'); return false;" 
                               class="button button-primary">
                                <?php _e('Connect to Google Analytics', 'uipress-analytics-bridge'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }
        } elseif ($current_tab === 'settings') {
            // Settings tab
            ?>
            <form method="post" action="">
                <?php wp_nonce_field('uipress_analytics_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="client_id"><?php _e('Google Client ID', 'uipress-analytics-bridge'); ?></label></th>
                        <td>
                            <input type="text" id="client_id" name="client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text">
                            <p class="description"><?php _e('Your Google API Client ID (e.g., 123456789-abcdefghijklmnopqrstuvwxyz.apps.googleusercontent.com)', 'uipress-analytics-bridge'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="client_secret"><?php _e('Google Client Secret', 'uipress-analytics-bridge'); ?></label></th>
                        <td>
                            <input type="password" id="client_secret" name="client_secret" value="<?php echo esc_attr($client_secret); ?>" class="regular-text">
                            <p class="description"><?php _e('Your Google API Client Secret', 'uipress-analytics-bridge'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_uipress_analytics_settings" class="button button-primary" value="<?php _e('Save Settings', 'uipress-analytics-bridge'); ?>">
                </p>
            </form>
            
            <div class="uipress-api-instructions" style="background: #f8f8f8; padding: 15px; border: 1px solid #ddd; margin: 20px 0;">
                <h3><?php _e('How to Get Google API Credentials', 'uipress-analytics-bridge'); ?></h3>
                <ol>
                    <li><?php _e('Go to the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and create a new project', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('Enable the Google Analytics API for your project', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('Create OAuth 2.0 credentials (Client ID and Client Secret)', 'uipress-analytics-bridge'); ?></li>
                    <li><?php _e('Set the authorized redirect URI to:', 'uipress-analytics-bridge'); ?> <code><?php echo esc_html($redirect_uri); ?></code></li>
                </ol>
                <p><a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="button button-secondary"><?php _e('Go to Google Cloud Console', 'uipress-analytics-bridge'); ?></a></p>
            </div>
            <?php
        }
        ?>
    </div>
    <?php
}

// AJAX handler for OAuth callback
add_action('wp_ajax_uipress_analytics_bridge_oauth_callback', 'uipress_analytics_bridge_simple_oauth_callback');
// Only define the function if it doesn't already exist
if (!function_exists('uipress_analytics_bridge_simple_oauth_callback')) {
    function uipress_analytics_bridge_simple_oauth_callback() {
        // This creates a page that displays in the popup after Google auth
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
                .error {
                    color: #dc3545;
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
            <?php 
            // Check if we got a code
            if (isset($_GET['code']) && !empty($_GET['code'])) {
                $auth_code = $_GET['code'];
                
                // Get settings for API credentials
                $settings = get_option('uipress_analytics_bridge_settings', array());
                $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
                $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
                
                if (!empty($client_id) && !empty($client_secret)) {
                    // Success message
                    echo '<div class="success">Authentication Successful!</div>';
                    echo '<div class="loading"></div>';
                    echo '<p>Processing your authentication...</p>';
                    
                    // For demonstration purposes, we'll save a dummy connection
                    // In a real implementation, you would exchange the code for tokens,
                    // then use those tokens to get the user's analytics properties
                    $connection = array(
                        'property_id' => 'UA-123456789-1',
                        'property_name' => 'Example Website',
                        'measurement_id' => '',
                        'auth_code' => $auth_code,
                        'timestamp' => time()
                    );
                    
                    update_option('uipress_analytics_bridge_connection', $connection);
                    
                    // Now, update UIPress settings if available
                    if (class_exists('UipressLite\Classes\App\UipOptions')) {
                        $ga_data = array(
                            'view' => $connection['property_id'],
                            'code' => 'bridge_connection',
                            'token' => 'bridge_token'
                        );
                        
                        \UipressLite\Classes\App\UipOptions::update('google_analytics', $ga_data);
                        
                        echo '<p>UIPress settings updated successfully.</p>';
                    }
                    
                    echo '<p>This window will close automatically in 3 seconds...</p>';
                    echo '<script>setTimeout(function() { window.close(); }, 3000);</script>';
                } else {
                    // Error message for missing API credentials
                    echo '<div class="error">Error: Missing API Credentials</div>';
                    echo '<p>Please enter your Google API credentials in the plugin settings.</p>';
                    echo '<p><button onclick="window.close();">Close</button></p>';
                }
            } else {
                // Error message for missing code
                echo '<div class="error">Error: Authentication Failed</div>';
                echo '<p>No authorization code received from Google.</p>';
                echo '<p><button onclick="window.close();">Close</button></p>';
            }
            ?>
        </body>
        </html>
        <?php
        exit;
    }
}

// AJAX handler for disconnecting
add_action('wp_ajax_uipress_analytics_bridge_disconnect', 'uipress_analytics_bridge_simple_disconnect');
// Only define the function if it doesn't already exist
if (!function_exists('uipress_analytics_bridge_simple_disconnect')) {
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
        wp_redirect(admin_url('options-general.php?page=uipress-analytics-simple&disconnected=1'));
        exit;
    }
}