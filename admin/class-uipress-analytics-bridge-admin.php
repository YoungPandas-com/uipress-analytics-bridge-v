<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
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
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        // Only load on our settings page
        if (isset($_GET['page']) && $_GET['page'] === 'uipress-analytics-bridge') {
            wp_enqueue_style(
                $this->plugin_name,
                UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL . 'admin/css/uipress-analytics-bridge-admin.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        // Only load on our settings page
        if (isset($_GET['page']) && $_GET['page'] === 'uipress-analytics-bridge') {
            wp_enqueue_script(
                $this->plugin_name,
                UIPRESS_ANALYTICS_BRIDGE_PLUGIN_URL . 'admin/js/uipress-analytics-bridge-admin.js',
                array('jquery'),
                $this->version,
                false
            );
            
            // Localize script with our data
            wp_localize_script(
                $this->plugin_name,
                'uipress_analytics_bridge_admin',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'security' => wp_create_nonce('uipress-analytics-bridge-admin-nonce'),
                    'loading_text' => __('Loading...', 'uipress-analytics-bridge'),
                    'success_text' => __('Success!', 'uipress-analytics-bridge'),
                    'error_text' => __('Error:', 'uipress-analytics-bridge')
                )
            );
        }
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     * @param    array    $links    Plugin action links.
     * @return   array              Modified action links.
     */
    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('admin.php?page=uipress-analytics-bridge') . '">' . __('Settings', 'uipress-analytics-bridge') . '</a>',
        );
        return array_merge($settings_link, $links);
    }

    /**
     * Add settings page to admin menu.
     *
     * @since    1.0.0
     */
    public function add_settings_page() {
        // Add to Settings menu
        add_submenu_page(
            'options-general.php', 
            __('UIPress Analytics Bridge', 'uipress-analytics-bridge'),
            __('UIPress Analytics', 'uipress-analytics-bridge'),
            'manage_options',
            'uipress-analytics-bridge',
            array($this, 'display_settings_page')
        );
        
        // Optionally, also add as a top-level menu for better visibility
        /* Uncomment this if you want a top-level menu
        add_menu_page(
            __('UIPress Analytics Bridge', 'uipress-analytics-bridge'),
            __('UIPress Analytics', 'uipress-analytics-bridge'),
            'manage_options',
            'uipress-analytics-bridge',
            array($this, 'display_settings_page'),
            'dashicons-chart-area',
            81
        );
        */
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        register_setting(
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_settings',
            array($this, 'validate_settings')
        );
        
        register_setting(
            'uipress_analytics_bridge_advanced',
            'uipress_analytics_bridge_advanced',
            array($this, 'validate_advanced_settings')
        );
        
        // Add settings sections
        add_settings_section(
            'uipress_analytics_bridge_main',
            __('Google API Settings', 'uipress-analytics-bridge'),
            array($this, 'settings_section_callback'),
            'uipress_analytics_bridge_settings'
        );
        
        add_settings_section(
            'uipress_analytics_bridge_cache',
            __('Cache Settings', 'uipress-analytics-bridge'),
            array($this, 'cache_section_callback'),
            'uipress_analytics_bridge_settings'
        );
        
        add_settings_section(
            'uipress_analytics_bridge_advanced_section',
            __('Advanced Settings', 'uipress-analytics-bridge'),
            array($this, 'advanced_section_callback'),
            'uipress_analytics_bridge_advanced'
        );
        
        // Add settings fields
        add_settings_field(
            'client_id',
            __('Google Client ID', 'uipress-analytics-bridge'),
            array($this, 'client_id_callback'),
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_main'
        );
        
        add_settings_field(
            'client_secret',
            __('Google Client Secret', 'uipress-analytics-bridge'),
            array($this, 'client_secret_callback'),
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_main'
        );
        
        add_settings_field(
            'cache_duration',
            __('Cache Duration (minutes)', 'uipress-analytics-bridge'),
            array($this, 'cache_duration_callback'),
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_cache'
        );
        
        add_settings_field(
            'remove_data_on_uninstall',
            __('Remove Data on Uninstall', 'uipress-analytics-bridge'),
            array($this, 'remove_data_callback'),
            'uipress_analytics_bridge_settings',
            'uipress_analytics_bridge_cache'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'uipress-analytics-bridge'),
            array($this, 'debug_mode_callback'),
            'uipress_analytics_bridge_advanced',
            'uipress_analytics_bridge_advanced_section'
        );
        
        add_settings_field(
            'compatibility_mode',
            __('Compatibility Mode', 'uipress-analytics-bridge'),
            array($this, 'compatibility_mode_callback'),
            'uipress_analytics_bridge_advanced',
            'uipress_analytics_bridge_advanced_section'
        );
        
        add_settings_field(
            'clear_cache_cron',
            __('Auto-Clear Cache', 'uipress-analytics-bridge'),
            array($this, 'clear_cache_cron_callback'),
            'uipress_analytics_bridge_advanced',
            'uipress_analytics_bridge_advanced_section'
        );
    }

    /**
     * Validate plugin settings.
     *
     * @since    1.0.0
     * @param    array    $input    The input to validate.
     * @return   array              The validated input.
     */
    public function validate_settings($input) {
        $validated = array();
        
        // Validate client ID
        $validated['client_id'] = sanitize_text_field($input['client_id']);
        
        // Validate client secret
        $validated['client_secret'] = sanitize_text_field($input['client_secret']);
        
        // Validate cache duration
        $validated['cache_duration'] = absint($input['cache_duration']);
        if ($validated['cache_duration'] < 5) {
            $validated['cache_duration'] = 5; // Minimum 5 minutes
        }
        
        // Validate remove data on uninstall checkbox
        $validated['remove_data_on_uninstall'] = isset($input['remove_data_on_uninstall']) ? true : false;
        
        // Clear cache if settings changed
        $this->clear_analytics_cache();
        
        return $validated;
    }

    /**
     * Validate advanced settings.
     *
     * @since    1.0.0
     * @param    array    $input    The input to validate.
     * @return   array              The validated input.
     */
    public function validate_advanced_settings($input) {
        $validated = array();
        
        // Validate debug mode checkbox
        $validated['debug_mode'] = isset($input['debug_mode']) ? true : false;
        
        // Validate compatibility mode checkbox
        $validated['compatibility_mode'] = isset($input['compatibility_mode']) ? true : false;
        
        // Validate clear cache cron checkbox
        $validated['clear_cache_cron'] = isset($input['clear_cache_cron']) ? true : false;
        
        // Update cron job status
        if ($validated['clear_cache_cron']) {
            if (!wp_next_scheduled('uipress_analytics_bridge_clear_cache')) {
                wp_schedule_event(time(), 'daily', 'uipress_analytics_bridge_clear_cache');
            }
        } else {
            wp_clear_scheduled_hook('uipress_analytics_bridge_clear_cache');
        }
        
        return $validated;
    }

    /**
     * Settings section callback.
     *
     * @since    1.0.0
     * @param    array    $args    The section arguments.
     */
    public function settings_section_callback($args) {
        ?>
        <p><?php _e('Enter your Google API credentials to enable the analytics integration. You can create these in the Google Cloud Console.', 'uipress-analytics-bridge'); ?></p>
        <p><a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="button button-secondary"><?php _e('Create Google API Credentials', 'uipress-analytics-bridge'); ?></a></p>
        <?php
    }

    /**
     * Cache section callback.
     *
     * @since    1.0.0
     * @param    array    $args    The section arguments.
     */
    public function cache_section_callback($args) {
        ?>
        <p><?php _e('Configure cache settings for analytics data to improve performance and reduce API calls.', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Advanced section callback.
     *
     * @since    1.0.0
     * @param    array    $args    The section arguments.
     */
    public function advanced_section_callback($args) {
        ?>
        <p><?php _e('Advanced settings for troubleshooting and special configurations.', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Client ID field callback.
     *
     * @since    1.0.0
     */
    public function client_id_callback() {
        $settings = get_option('uipress_analytics_bridge_settings');
        $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        ?>
        <input type="text" id="client_id" name="uipress_analytics_bridge_settings[client_id]" value="<?php echo esc_attr($client_id); ?>" class="regular-text">
        <p class="description"><?php _e('Your Google API Client ID (e.g., 123456789-abcdefghijklmnopqrstuvwxyz.apps.googleusercontent.com)', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Client Secret field callback.
     *
     * @since    1.0.0
     */
    public function client_secret_callback() {
        $settings = get_option('uipress_analytics_bridge_settings');
        $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
        ?>
        <input type="password" id="client_secret" name="uipress_analytics_bridge_settings[client_secret]" value="<?php echo esc_attr($client_secret); ?>" class="regular-text">
        <p class="description"><?php _e('Your Google API Client Secret', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Cache duration field callback.
     *
     * @since    1.0.0
     */
    public function cache_duration_callback() {
        $settings = get_option('uipress_analytics_bridge_settings');
        $cache_duration = isset($settings['cache_duration']) ? $settings['cache_duration'] : 60;
        ?>
        <input type="number" id="cache_duration" name="uipress_analytics_bridge_settings[cache_duration]" value="<?php echo esc_attr($cache_duration); ?>" class="small-text" min="5">
        <p class="description"><?php _e('Duration in minutes to cache analytics data (minimum 5 minutes)', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Remove data checkbox callback.
     *
     * @since    1.0.0
     */
    public function remove_data_callback() {
        $settings = get_option('uipress_analytics_bridge_settings');
        $remove_data = isset($settings['remove_data_on_uninstall']) ? $settings['remove_data_on_uninstall'] : false;
        ?>
        <label for="remove_data_on_uninstall">
            <input type="checkbox" id="remove_data_on_uninstall" name="uipress_analytics_bridge_settings[remove_data_on_uninstall]" <?php checked($remove_data, true); ?>>
            <?php _e('Delete all plugin data when uninstalling the plugin', 'uipress-analytics-bridge'); ?>
        </label>
        <?php
    }

    /**
     * Debug mode checkbox callback.
     *
     * @since    1.0.0
     */
    public function debug_mode_callback() {
        $settings = get_option('uipress_analytics_bridge_advanced');
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : false;
        ?>
        <label for="debug_mode">
            <input type="checkbox" id="debug_mode" name="uipress_analytics_bridge_advanced[debug_mode]" <?php checked($debug_mode, true); ?>>
            <?php _e('Enable debug mode for verbose logging', 'uipress-analytics-bridge'); ?>
        </label>
        <p class="description"><?php _e('Logs will be stored in the WordPress debug.log file', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Compatibility mode checkbox callback.
     *
     * @since    1.0.0
     */
    public function compatibility_mode_callback() {
        $settings = get_option('uipress_analytics_bridge_advanced');
        $compatibility_mode = isset($settings['compatibility_mode']) ? $settings['compatibility_mode'] : false;
        ?>
        <label for="compatibility_mode">
            <input type="checkbox" id="compatibility_mode" name="uipress_analytics_bridge_advanced[compatibility_mode]" <?php checked($compatibility_mode, true); ?>>
            <?php _e('Enable compatibility mode for older UIPress versions', 'uipress-analytics-bridge'); ?>
        </label>
        <p class="description"><?php _e('Use this if you experience issues with UIPress versions below 3.0.0', 'uipress-analytics-bridge'); ?></p>
        <?php
    }

    /**
     * Clear cache cron checkbox callback.
     *
     * @since    1.0.0
     */
    public function clear_cache_cron_callback() {
        $settings = get_option('uipress_analytics_bridge_advanced');
        $clear_cache_cron = isset($settings['clear_cache_cron']) ? $settings['clear_cache_cron'] : true;
        ?>
        <label for="clear_cache_cron">
            <input type="checkbox" id="clear_cache_cron" name="uipress_analytics_bridge_advanced[clear_cache_cron]" <?php checked($clear_cache_cron, true); ?>>
            <?php _e('Automatically clear cache daily', 'uipress-analytics-bridge'); ?>
        </label>
        <p><a href="#" id="uip-analytics-clear-cache" class="button button-secondary"><?php _e('Clear Cache Now', 'uipress-analytics-bridge'); ?></a></p>
        <?php
    }

    /**
     * Display settings page.
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get active tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Create instance of detector to check UIPress
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-detector.php';
        $detector = new UIPress_Analytics_Bridge_Detector($this->plugin_name, $this->version);
        $uipress_active = $detector->is_uipress_active();
        $uipress_features = $detector->get_uipress_features();
        $uipress_paths = $detector->get_uipress_class_paths();
        
        // Check for saved settings notice
        $settings_updated = isset($_GET['settings-updated']) ? true : false;
        
        ?>
        <div class="wrap uipress-analytics-bridge-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php
            // Show notice if settings were updated
            if ($settings_updated) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully.', 'uipress-analytics-bridge'); ?></p>
                </div>
                <?php
            }
            
            // Show notice if UIPress is not active
            if (!$uipress_active) {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('UIPress Lite and Pro are required for this plugin to function properly.', 'uipress-analytics-bridge'); ?></p>
                </div>
                <?php
            } else {
                if (!$uipress_features['google_analytics']) {
                    ?>
                    <div class="notice notice-warning">
                        <p><?php _e('UIPress Google Analytics functionality was not detected. This plugin may not work correctly.', 'uipress-analytics-bridge'); ?></p>
                    </div>
                    <?php
                }
            }
            ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=uipress-analytics-bridge&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General Settings', 'uipress-analytics-bridge'); ?></a>
                <a href="?page=uipress-analytics-bridge&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>"><?php _e('Advanced Settings', 'uipress-analytics-bridge'); ?></a>
                <a href="?page=uipress-analytics-bridge&tab=status" class="nav-tab <?php echo $active_tab === 'status' ? 'nav-tab-active' : ''; ?>"><?php _e('Status', 'uipress-analytics-bridge'); ?></a>
            </h2>
            
            <div class="uipress-analytics-bridge-content">
                <?php if ($active_tab === 'general') : ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('uipress_analytics_bridge_settings');
                        do_settings_sections('uipress_analytics_bridge_settings');
                        submit_button();
                        ?>
                    </form>
                    
                    <?php if ($uipress_active && !empty(get_option('uipress_analytics_bridge_settings')['client_id'])) : ?>
                        <div class="uipress-analytics-card">
                            <h3><?php _e('Authentication Status', 'uipress-analytics-bridge'); ?></h3>
                            <p><?php _e('Check your authentication status with Google Analytics.', 'uipress-analytics-bridge'); ?></p>
                            
                            <div class="uipress-analytics-auth-status">
                                <p><strong><?php _e('Global Authentication:', 'uipress-analytics-bridge'); ?></strong> 
                                    <span id="uipress-global-auth-status">
                                        <?php $this->display_auth_status(false); ?>
                                    </span>
                                </p>
                                
                                <p><strong><?php _e('User Authentication:', 'uipress-analytics-bridge'); ?></strong> 
                                    <span id="uipress-user-auth-status">
                                        <?php $this->display_auth_status(true); ?>
                                    </span>
                                </p>
                                
                                <div class="uipress-analytics-auth-buttons">
                                    <button id="uipress-global-auth-button" class="button button-primary"><?php _e('Authenticate Globally', 'uipress-analytics-bridge'); ?></button>
                                    <button id="uipress-user-auth-button" class="button button-secondary"><?php _e('Authenticate User', 'uipress-analytics-bridge'); ?></button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($active_tab === 'advanced') : ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('uipress_analytics_bridge_advanced');
                        do_settings_sections('uipress_analytics_bridge_advanced');
                        submit_button();
                        ?>
                    </form>
                
                <?php elseif ($active_tab === 'status') : ?>
                    <div class="uipress-analytics-card">
                        <h3><?php _e('System Status', 'uipress-analytics-bridge'); ?></h3>
                        
                        <table class="widefat" cellspacing="0">
                            <tbody>
                                <tr>
                                    <th><?php _e('UIPress Lite Status:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo defined('uip_plugin_version') ? '<span class="uipress-status-active">Active (' . esc_html(uip_plugin_version) . ')</span>' : '<span class="uipress-status-inactive">Inactive</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('UIPress Pro Status:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo defined('uip_pro_plugin_version') ? '<span class="uipress-status-active">Active (' . esc_html(uip_pro_plugin_version) . ')</span>' : '<span class="uipress-status-inactive">Inactive</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Google Analytics Support:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo $uipress_features['google_analytics'] ? '<span class="uipress-status-active">Detected</span>' : '<span class="uipress-status-inactive">Not Detected</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('API Credentials:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo !empty(get_option('uipress_analytics_bridge_settings')['client_id']) ? '<span class="uipress-status-active">Configured</span>' : '<span class="uipress-status-inactive">Not Configured</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Debug Mode:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo !empty(get_option('uipress_analytics_bridge_advanced')['debug_mode']) ? '<span class="uipress-status-warning">Enabled</span>' : '<span class="uipress-status-active">Disabled</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Cache Status:', 'uipress-analytics-bridge'); ?></th>
                                    <td>
                                        <?php 
                                        $cache_count = $this->get_cache_count();
                                        echo $cache_count > 0 ? 
                                            '<span class="uipress-status-active">' . sprintf(_n('%d item cached', '%d items cached', $cache_count, 'uipress-analytics-bridge'), $cache_count) . '</span>' : 
                                            '<span class="uipress-status-inactive">No cache</span>'; 
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('PHP Version:', 'uipress-analytics-bridge'); ?></th>
                                    <td>
                                        <?php 
                                        $php_version = phpversion();
                                        $is_compatible = version_compare($php_version, '7.2', '>=');
                                        echo $is_compatible ? 
                                            '<span class="uipress-status-active">' . esc_html($php_version) . '</span>' : 
                                            '<span class="uipress-status-inactive">' . esc_html($php_version) . ' (PHP 7.2+ recommended)</span>'; 
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('WordPress Version:', 'uipress-analytics-bridge'); ?></th>
                                    <td>
                                        <?php 
                                        global $wp_version;
                                        $is_wp_compatible = version_compare($wp_version, '5.0', '>=');
                                        echo $is_wp_compatible ? 
                                            '<span class="uipress-status-active">' . esc_html($wp_version) . '</span>' : 
                                            '<span class="uipress-status-inactive">' . esc_html($wp_version) . ' (WordPress 5.0+ recommended)</span>'; 
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="uipress-analytics-card">
                        <h3><?php _e('UIPress Class Paths', 'uipress-analytics-bridge'); ?></h3>
                        <p><?php _e('These paths are used by the bridge to communicate with UIPress.', 'uipress-analytics-bridge'); ?></p>
                        
                        <table class="widefat" cellspacing="0">
                            <tbody>
                                <tr>
                                    <th><?php _e('Google Analytics Class:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo $uipress_paths['google_analytics'] ? '<span class="uipress-status-active">' . esc_html($uipress_paths['google_analytics']) . '</span>' : '<span class="uipress-status-inactive">Not Found</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('AJAX Utils Class:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo $uipress_paths['ajax_utils'] ? '<span class="uipress-status-active">' . esc_html($uipress_paths['ajax_utils']) . '</span>' : '<span class="uipress-status-inactive">Not Found</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Sanitize Utils Class:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo $uipress_paths['sanitize_utils'] ? '<span class="uipress-status-active">' . esc_html($uipress_paths['sanitize_utils']) . '</span>' : '<span class="uipress-status-inactive">Not Found</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('User Preferences Class:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo $uipress_paths['user_preferences'] ? '<span class="uipress-status-active">' . esc_html($uipress_paths['user_preferences']) . '</span>' : '<span class="uipress-status-inactive">Not Found</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('UIPress Options Class:', 'uipress-analytics-bridge'); ?></th>
                                    <td><?php echo $uipress_paths['uip_options'] ? '<span class="uipress-status-active">' . esc_html($uipress_paths['uip_options']) . '</span>' : '<span class="uipress-status-inactive">Not Found</span>'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="uipress-analytics-card">
                        <h3><?php _e('Tools', 'uipress-analytics-bridge'); ?></h3>
                        
                        <p><a href="#" id="uip-analytics-refresh-status" class="button button-secondary"><?php _e('Refresh Status', 'uipress-analytics-bridge'); ?></a>
                        <a href="#" id="uip-analytics-clear-cache-all" class="button button-secondary"><?php _e('Clear All Caches', 'uipress-analytics-bridge'); ?></a>
                        <a href="#" id="uip-analytics-test-connection" class="button button-primary"><?php _e('Test API Connection', 'uipress-analytics-bridge'); ?></a></p>
                        
                        <div id="uip-analytics-test-result" class="uipress-analytics-test-result" style="display: none;"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display authentication status.
     *
     * @since    1.0.0
     * @param    bool      $use_user_preferences    Whether to use user preferences.
     */
    private function display_auth_status($use_user_preferences = false) {
        $auth_data = $this->get_auth_data($use_user_preferences);
        
        if (is_array($auth_data) && isset($auth_data['view']) && $auth_data['view'] && isset($auth_data['token']) && $auth_data['token']) {
            $is_ga4 = isset($auth_data['gafour']) && $auth_data['gafour'];
            $property_type = $is_ga4 ? 'GA4' : 'Universal Analytics';
            $property_id = sanitize_text_field($auth_data['view']);
            
            echo '<span class="uipress-status-active">' . __('Connected', 'uipress-analytics-bridge') . ' (' . esc_html($property_type) . ': ' . esc_html($property_id) . ')</span>';
        } else {
            echo '<span class="uipress-status-inactive">' . __('Not Connected', 'uipress-analytics-bridge') . '</span>';
        }
    }

    /**
     * Get authentication data.
     *
     * @since    1.0.0
     * @param    bool      $use_user_preferences    Whether to use user preferences.
     * @return   array                              The authentication data.
     */
    private function get_auth_data($use_user_preferences = false) {
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
     * Clear analytics cache.
     *
     * @since    1.0.0
     */
    private function clear_analytics_cache() {
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
     * Get count of cached items.
     *
     * @since    1.0.0
     * @return   int      Number of cached items.
     */
    private function get_cache_count() {
        global $wpdb;
        
        // Count transients with our prefix
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_uipress_analytics_bridge_') . '%'
            )
        );
        
        return intval($count);
    }
    
    /**
     * Display activation notice with link to settings.
     *
     * @since    1.0.0
     */
    public function activation_notice() {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php _e('Thank you for installing UIPress Analytics Bridge!', 'uipress-analytics-bridge'); ?> 
                <a href="<?php echo admin_url('options-general.php?page=uipress-analytics-bridge'); ?>"><?php _e('Click here to configure the plugin settings', 'uipress-analytics-bridge'); ?></a>
            </p>
        </div>
        <?php
        
        // Delete the transient so the notice doesn't keep showing
        delete_transient('uipress_analytics_bridge_activation_notice');
    }
}