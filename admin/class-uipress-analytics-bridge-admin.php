<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/admin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Admin {

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
     * The auth handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_Auth    $auth    Handles WordPress-side authentication.
     */
    private $auth;

    /**
     * The data handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_Data    $data    Handles data formatting.
     */
    private $data;

    /**
     * The UIPress detector instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      UIPress_Analytics_Bridge_Detector    $detector    Detects UIPress installation.
     */
    private $detector;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     * @param    object    $auth              The auth handler instance.
     * @param    object    $data              The data handler instance.
     * @param    object    $detector          The UIPress detector instance.
     */
    public function __construct($plugin_name, $version, $auth, $data, $detector) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->auth = $auth;
        $this->data = $data;
        $this->detector = $detector;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        // Only load on our plugin's settings page
        $screen = get_current_screen();
        if ($screen && $screen->id == 'settings_page_uipress-analytics-bridge') {
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/uipress-analytics-bridge-admin.css', array(), $this->version, 'all');
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        // Only load on our plugin's settings page
        $screen = get_current_screen();
        if ($screen && $screen->id == 'settings_page_uipress-analytics-bridge') {
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/uipress-analytics-bridge-admin.js', array('jquery'), $this->version, false);
            
            // Add the nonce, ajaxurl and other settings to the script
            wp_localize_script($this->plugin_name, 'uipAnalyticsBridge', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('uipress-analytics-bridge-nonce'),
                'authUrl' => $this->auth->get_auth_url(),
                'isAuthenticated' => $this->auth->is_authenticated(),
                'detectionStatus' => array(
                    'uipressLite' => $this->detector->is_uipress_lite_active(),
                    'uipressPro' => $this->detector->is_uipress_pro_active(),
                    'integrationPossible' => $this->detector->is_uipress_integration_possible(),
                ),
            ));
        }
    }

    /**
     * Add options page to the WordPress admin menu.
     *
     * @since    1.0.0
     */
    public function add_options_page() {
        add_options_page(
            __('UIPress Analytics Bridge', 'uipress-analytics-bridge'),
            __('UIPress Analytics', 'uipress-analytics-bridge'),
            'manage_options',
            'uipress-analytics-bridge',
            array($this, 'display_options_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        register_setting(
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_settings'
        );
        
        // General settings section
        add_settings_section(
            'uipress_analytics_bridge_general',
            __('General Settings', 'uipress-analytics-bridge'),
            array($this, 'general_settings_section_callback'),
            'uipress-analytics-bridge'
        );
        
        // Add settings fields
        add_settings_field(
            'client_id',
            __('Google Client ID', 'uipress-analytics-bridge'),
            array($this, 'client_id_field_callback'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_general'
        );
        
        add_settings_field(
            'client_secret',
            __('Google Client Secret', 'uipress-analytics-bridge'),
            array($this, 'client_secret_field_callback'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_general'
        );
        
        add_settings_field(
            'date_range',
            __('Default Date Range (days)', 'uipress-analytics-bridge'),
            array($this, 'date_range_field_callback'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_general'
        );
        
        // Advanced settings section
        add_settings_section(
            'uipress_analytics_bridge_advanced',
            __('Advanced Settings', 'uipress-analytics-bridge'),
            array($this, 'advanced_settings_section_callback'),
            'uipress-analytics-bridge'
        );
        
        add_settings_field(
            'cache_expiration',
            __('Cache Expiration (seconds)', 'uipress-analytics-bridge'),
            array($this, 'cache_expiration_field_callback'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_advanced'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'uipress-analytics-bridge'),
            array($this, 'debug_mode_field_callback'),
            'uipress-analytics-bridge',
            'uipress_analytics_bridge_advanced'
        );
        
        // Register advanced settings
        register_setting(
            'uipress_analytics_bridge_advanced',
            'uipress_analytics_bridge_advanced'
        );
    }

    /**
     * Render the options page.
     *
     * @since    1.0.0
     */
    public function display_options_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="uipress-analytics-bridge-header">
                <p class="description">
                    <?php _e('UIPress Analytics Bridge provides enhanced Google Analytics integration for UIPress Pro.', 'uipress-analytics-bridge'); ?>
                </p>
            </div>
            
            <div class="uipress-analytics-bridge-tabs">
                <div class="uipress-analytics-bridge-tab-nav">
                    <a href="#settings" class="active"><?php _e('Settings', 'uipress-analytics-bridge'); ?></a>
                    <a href="#authentication"><?php _e('Authentication', 'uipress-analytics-bridge'); ?></a>
                    <a href="#diagnostic"><?php _e('Diagnostic', 'uipress-analytics-bridge'); ?></a>
                    <a href="#help"><?php _e('Help', 'uipress-analytics-bridge'); ?></a>
                </div>
                
                <div class="uipress-analytics-bridge-tab-content">
                    <div id="settings" class="uipress-analytics-bridge-tab-pane active">
                        <form method="post" action="options.php">
                            <?php
                            settings_fields('uipress_analytics_bridge_settings');
                            do_settings_sections('uipress-analytics-bridge');
                            submit_button();
                            ?>
                        </form>
                    </div>
                    
                    <div id="authentication" class="uipress-analytics-bridge-tab-pane">
                        <h2><?php _e('Google Analytics Authentication', 'uipress-analytics-bridge'); ?></h2>
                        
                        <div class="uipress-analytics-bridge-auth-status">
                            <h3><?php _e('Authentication Status', 'uipress-analytics-bridge'); ?></h3>
                            
                            <div id="auth-status-indicator">
                                <?php if ($this->auth->is_authenticated()): ?>
                                    <div class="uipress-analytics-bridge-status-connected">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Connected to Google Analytics', 'uipress-analytics-bridge'); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="uipress-analytics-bridge-status-disconnected">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php _e('Not connected to Google Analytics', 'uipress-analytics-bridge'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="uipress-analytics-bridge-auth-actions">
                                <button id="authenticate-button" class="button button-primary">
                                    <?php _e('Authenticate with Google Analytics', 'uipress-analytics-bridge'); ?>
                                </button>
                                
                                <button id="revoke-button" class="button" <?php echo $this->auth->is_authenticated() ? '' : 'disabled'; ?>>
                                    <?php _e('Revoke Authentication', 'uipress-analytics-bridge'); ?>
                                </button>
                                
                                <button id="test-connection-button" class="button" <?php echo $this->auth->is_authenticated() ? '' : 'disabled'; ?>>
                                    <?php _e('Test Connection', 'uipress-analytics-bridge'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="uipress-analytics-bridge-properties">
                            <h3><?php _e('Analytics Properties', 'uipress-analytics-bridge'); ?></h3>
                            
                            <div id="properties-list">
                                <?php
                                $properties = $this->auth->get_analytics_properties();
                                if (!empty($properties)): ?>
                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th><?php _e('ID', 'uipress-analytics-bridge'); ?></th>
                                                <th><?php _e('Name', 'uipress-analytics-bridge'); ?></th>
                                                <th><?php _e('Type', 'uipress-analytics-bridge'); ?></th>
                                                <th><?php _e('Measurement ID', 'uipress-analytics-bridge'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($properties as $property): ?>
                                                <tr>
                                                    <td><?php echo esc_html($property['id']); ?></td>
                                                    <td><?php echo esc_html($property['name']); ?></td>
                                                    <td><?php echo esc_html($property['type']); ?></td>
                                                    <td><?php echo isset($property['measurement_id']) ? esc_html($property['measurement_id']) : ''; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p><?php _e('No properties found or not authenticated.', 'uipress-analytics-bridge'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="diagnostic" class="uipress-analytics-bridge-tab-pane">
                        <h2><?php _e('Diagnostic Information', 'uipress-analytics-bridge'); ?></h2>
                        
                        <div class="uipress-analytics-bridge-diagnostic">
                            <h3><?php _e('UIPress Detection', 'uipress-analytics-bridge'); ?></h3>
                            
                            <table class="widefat">
                                <tbody>
                                    <tr>
                                        <td><?php _e('UIPress Lite Active', 'uipress-analytics-bridge'); ?></td>
                                        <td>
                                            <?php if ($this->detector->is_uipress_lite_active()): ?>
                                                <span class="dashicons dashicons-yes" style="color: green;"></span>
                                                <?php _e('Yes', 'uipress-analytics-bridge'); ?>
                                                (<?php echo esc_html($this->detector->get_uipress_lite_version()); ?>)
                                            <?php else: ?>
                                                <span class="dashicons dashicons-no" style="color: red;"></span>
                                                <?php _e('No', 'uipress-analytics-bridge'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('UIPress Pro Active', 'uipress-analytics-bridge'); ?></td>
                                        <td>
                                            <?php if ($this->detector->is_uipress_pro_active()): ?>
                                                <span class="dashicons dashicons-yes" style="color: green;"></span>
                                                <?php _e('Yes', 'uipress-analytics-bridge'); ?>
                                                (<?php echo esc_html($this->detector->get_uipress_pro_version()); ?>)
                                            <?php else: ?>
                                                <span class="dashicons dashicons-no" style="color: red;"></span>
                                                <?php _e('No', 'uipress-analytics-bridge'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Integration Possible', 'uipress-analytics-bridge'); ?></td>
                                        <td>
                                            <?php if ($this->detector->is_uipress_integration_possible()): ?>
                                                <span class="dashicons dashicons-yes" style="color: green;"></span>
                                                <?php _e('Yes', 'uipress-analytics-bridge'); ?>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-no" style="color: red;"></span>
                                                <?php _e('No', 'uipress-analytics-bridge'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <h3><?php _e('Required Hooks Status', 'uipress-analytics-bridge'); ?></h3>
                            
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Hook', 'uipress-analytics-bridge'); ?></th>
                                        <th><?php _e('Status', 'uipress-analytics-bridge'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hooks_status = $this->detector->verify_uipress_hooks();
                                    foreach ($hooks_status as $hook => $status): ?>
                                        <tr>
                                            <td><?php echo esc_html($hook); ?></td>
                                            <td>
                                                <?php if ($status): ?>
                                                    <span class="dashicons dashicons-yes" style="color: green;"></span>
                                                    <?php _e('Active', 'uipress-analytics-bridge'); ?>
                                                <?php else: ?>
                                                    <span class="dashicons dashicons-no" style="color: red;"></span>
                                                    <?php _e('Not Found', 'uipress-analytics-bridge'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <h3><?php _e('Required Options Status', 'uipress-analytics-bridge'); ?></h3>
                            
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Option', 'uipress-analytics-bridge'); ?></th>
                                        <th><?php _e('Status', 'uipress-analytics-bridge'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $options_status = $this->detector->check_uipress_options();
                                    foreach ($options_status as $option => $status): ?>
                                        <tr>
                                            <td><?php echo esc_html($option); ?></td>
                                            <td>
                                                <?php if ($status): ?>
                                                    <span class="dashicons dashicons-yes" style="color: green;"></span>
                                                    <?php _e('Exists', 'uipress-analytics-bridge'); ?>
                                                <?php else: ?>
                                                    <span class="dashicons dashicons-no" style="color: red;"></span>
                                                    <?php _e('Not Found', 'uipress-analytics-bridge'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <h3><?php _e('System Information', 'uipress-analytics-bridge'); ?></h3>
                            
                            <table class="widefat">
                                <tbody>
                                    <tr>
                                        <td><?php _e('WordPress Version', 'uipress-analytics-bridge'); ?></td>
                                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('PHP Version', 'uipress-analytics-bridge'); ?></td>
                                        <td><?php echo esc_html(phpversion()); ?></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Plugin Version', 'uipress-analytics-bridge'); ?></td>
                                        <td><?php echo esc_html($this->version); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <p>
                                <button id="clear-cache-button" class="button">
                                    <?php _e('Clear Cache', 'uipress-analytics-bridge'); ?>
                                </button>
                            </p>
                        </div>
                    </div>
                    
                    <div id="help" class="uipress-analytics-bridge-tab-pane">
                        <h2><?php _e('Help and Documentation', 'uipress-analytics-bridge'); ?></h2>
                        
                        <div class="uipress-analytics-bridge-help">
                            <h3><?php _e('Quick Start Guide', 'uipress-analytics-bridge'); ?></h3>
                            
                            <ol>
                                <li><?php _e('Configure your Google API credentials in the "Settings" tab (optional).', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('Click "Authenticate with Google Analytics" in the "Authentication" tab.', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('Grant the requested permissions in the Google authorization window.', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('UIPress Pro will now use the enhanced analytics integration.', 'uipress-analytics-bridge'); ?></li>
                            </ol>
                            
                            <h3><?php _e('Troubleshooting', 'uipress-analytics-bridge'); ?></h3>
                            
                            <h4><?php _e('Authentication Issues', 'uipress-analytics-bridge'); ?></h4>
                            <ul>
                                <li><?php _e('Make sure you have granted all requested permissions during the Google authentication process.', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('Try revoking authentication and authenticate again.', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('Check if your Google account has access to Google Analytics properties.', 'uipress-analytics-bridge'); ?></li>
                            </ul>
                            
                            <h4><?php _e('Integration Issues', 'uipress-analytics-bridge'); ?></h4>
                            <ul>
                                <li><?php _e('Ensure both UIPress Lite and UIPress Pro are activated.', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('Check the "Diagnostic" tab for hook and option status.', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('Try clearing the cache if data is not refreshing.', 'uipress-analytics-bridge'); ?></li>
                            </ul>
                            
                            <h4><?php _e('Data Issues', 'uipress-analytics-bridge'); ?></h4>
                            <ul>
                                <li><?php _e('Enable debug mode to log detailed API requests and responses.', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('Check if your Google Analytics property has data for the selected date range.', 'uipress-analytics-bridge'); ?></li>
                                <li><?php _e('Verify that you have selected the correct Google Analytics property.', 'uipress-analytics-bridge'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * General settings section callback.
     *
     * @since    1.0.0
     */
    public function general_settings_section_callback() {
        echo '<p>' . __('Configure the general settings for Google Analytics integration.', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Advanced settings section callback.
     *
     * @since    1.0.0
     */
    public function advanced_settings_section_callback() {
        echo '<p>' . __('Advanced settings for performance and debugging.', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Client ID field callback.
     *
     * @since    1.0.0
     */
    public function client_id_field_callback() {
        $settings = get_option('uipress_analytics_bridge_settings', array());
        $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        
        echo '<input type="text" id="client_id" name="uipress_analytics_bridge_settings[client_id]" value="' . esc_attr($client_id) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Google API Client ID. Leave blank to use the default service.', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Client Secret field callback.
     *
     * @since    1.0.0
     */
    public function client_secret_field_callback() {
        $settings = get_option('uipress_analytics_bridge_settings', array());
        $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
        
        echo '<input type="password" id="client_secret" name="uipress_analytics_bridge_settings[client_secret]" value="' . esc_attr($client_secret) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Google API Client Secret. Leave blank to use the default service.', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Date Range field callback.
     *
     * @since    1.0.0
     */
    public function date_range_field_callback() {
        $settings = get_option('uipress_analytics_bridge_settings', array());
        $date_range = isset($settings['date_range']) ? $settings['date_range'] : '30';
        
        echo '<input type="number" id="date_range" name="uipress_analytics_bridge_settings[date_range]" value="' . esc_attr($date_range) . '" class="small-text">';
        echo '<p class="description">' . __('Default number of days to fetch analytics data for.', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Cache Expiration field callback.
     *
     * @since    1.0.0
     */
    public function cache_expiration_field_callback() {
        $settings = get_option('uipress_analytics_bridge_advanced', array());
        $cache_expiration = isset($settings['cache_expiration']) ? $settings['cache_expiration'] : '3600';
        
        echo '<input type="number" id="cache_expiration" name="uipress_analytics_bridge_advanced[cache_expiration]" value="' . esc_attr($cache_expiration) . '" class="small-text">';
        echo '<p class="description">' . __('Time in seconds to cache analytics data (default: 3600 - 1 hour).', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Debug Mode field callback.
     *
     * @since    1.0.0
     */
    public function debug_mode_field_callback() {
        $settings = get_option('uipress_analytics_bridge_advanced', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        
        echo '<input type="checkbox" id="debug_mode" name="uipress_analytics_bridge_advanced[debug_mode]" value="1" ' . checked($debug_mode, true, false) . '>';
        echo '<p class="description">' . __('Enable debug mode to log detailed information to the WordPress debug log.', 'uipress-analytics-bridge') . '</p>';
    }

    /**
     * Add settings link to the plugins page.
     *
     * @since    1.0.0
     * @param    array    $links    Plugin action links.
     * @return   array    Modified action links.
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=uipress-analytics-bridge') . '">' . __('Settings', 'uipress-analytics-bridge') . '</a>';
        array_unshift($links, $settings_link);
        
        return $links;
    }

    /**
     * Display admin notices.
     *
     * @since    1.0.0
     */
    public function admin_notices() {
        // Check if the current user can manage options
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if we should display the UIPress integration notice
        if (!$this->detector->is_uipress_integration_possible()) {
            // Only show on the plugins page and our settings page
            $screen = get_current_screen();
            if ($screen && ($screen->id == 'plugins' || $screen->id == 'settings_page_uipress-analytics-bridge')) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <?php _e('UIPress Analytics Bridge requires both UIPress Lite and UIPress Pro plugins to be active for full functionality.', 'uipress-analytics-bridge'); ?>
                        <a href="<?php echo admin_url('options-general.php?page=uipress-analytics-bridge'); ?>"><?php _e('View Details', 'uipress-analytics-bridge'); ?></a>
                    </p>
                </div>
                <?php
            }
        }
        
        // Check if we should display the authentication notice
        if ($this->detector->is_uipress_integration_possible() && !$this->auth->is_authenticated()) {
            // Only show on the plugins page and our settings page
            $screen = get_current_screen();
            if ($screen && ($screen->id == 'plugins' || $screen->id == 'settings_page_uipress-analytics-bridge')) {
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <?php _e('UIPress Analytics Bridge needs to be authenticated with Google Analytics to work properly.', 'uipress-analytics-bridge'); ?>
                        <a href="<?php echo admin_url('options-general.php?page=uipress-analytics-bridge#authentication'); ?>"><?php _e('Authenticate Now', 'uipress-analytics-bridge'); ?></a>
                    </p>
                </div>
                <?php
            }
        }
    }
}