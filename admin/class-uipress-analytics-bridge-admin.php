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
        
        // Add admin menu - using a direct approach for reliability
        add_action('admin_menu', array($this, 'add_settings_page'));
        
        // Register plugin settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME, array($this, 'add_action_links'));
        
        // Add AJAX handlers for Google Analytics connections
        add_action('wp_ajax_uipress_analytics_bridge_connect', array($this, 'connect_to_google_analytics'));
        add_action('wp_ajax_uipress_analytics_bridge_get_properties', array($this, 'get_analytics_properties'));
        add_action('wp_ajax_uipress_analytics_bridge_save_property', array($this, 'save_analytics_property'));
        
        // Process form submissions
        add_action('admin_init', array($this, 'process_form_submissions'));
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
            '<a href="' . admin_url('options-general.php?page=uipress-analytics-bridge') . '">' . __('Settings', 'uipress-analytics-bridge') . '</a>',
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
        add_options_page(
            __('UIPress Analytics Bridge', 'uipress-analytics-bridge'),
            __('UIPress Analytics', 'uipress-analytics-bridge'),
            'manage_options',
            'uipress-analytics-bridge',
            array($this, 'display_settings_page')
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
            'uipress_analytics_bridge_settings',
            array($this, 'validate_settings')
        );
        
        register_setting(
            'uipress_analytics_bridge_advanced',
            'uipress_analytics_bridge_advanced',
            array($this, 'validate_advanced_settings')
        );
        
        register_setting(
            'uipress_analytics_bridge_connection',
            'uipress_analytics_bridge_connection',
            array($this, 'validate_connection_settings')
        );
        
        // Add settings sections
        add_settings_section(
            'uipress_analytics_bridge_connection_section',
            __('Google Analytics Connection', 'uipress-analytics-bridge'),
            array($this, 'connection_section_callback'),
            'uipress_analytics_bridge_connection'
        );
        
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
        
        // Add connection fields
        add_settings_field(
            'analytics_property',
            __('Google Analytics Property', 'uipress-analytics-bridge'),
            array($this, 'analytics_property_callback'),
            'uipress_analytics_bridge_connection',
            'uipress_analytics_bridge_connection_section'
        );
        
        add_settings_field(
            'connection_scope',
            __('Connection Scope', 'uipress-analytics-bridge'),
            array($this, 'connection_scope_callback'),
            'uipress_analytics_bridge_connection',
            'uipress_analytics_bridge_connection_section'
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
     * Validate connection settings.
     *
     * @since    1.0.0
     * @param    array    $input    The input to validate.
     * @return   array              The validated input.
     */
    public function validate_connection_settings($input) {
        $validated = array();
        
        // Validate property ID
        if (isset($input['property_id'])) {
            $validated['property_id'] = sanitize_text_field($input['property_id']);
        }
        
        // Validate property name
        if (isset($input['property_name'])) {
            $validated['property_name'] = sanitize_text_field($input['property_name']);
        }
        
        // Validate measurement ID (for GA4)
        if (isset($input['measurement_id'])) {
            $validated['measurement_id'] = sanitize_text_field($input['measurement_id']);
        }
        
        // Validate account scope
        if (isset($input['scope'])) {
            $validated['scope'] = ($input['scope'] === 'user') ? 'user' : 'global';
        } else {
            $validated['scope'] = 'global';
        }
        
        // Store in UIPress format if UIPress is active
        if (defined('uip_plugin_version') && defined('uip_pro_plugin_version')) {
            $this->update_uipress_settings($validated);
        }
        
        return $validated;
    }

    /**
     * Connection section callback.
     *
     * @since    1.0.0
     * @param    array    $args    The section arguments.
     */
    public function connection_section_callback($args) {
        $settings = get_option('uipress_analytics_bridge_settings');
        $connection = get_option('uipress_analytics_bridge_connection');
        
        // Check if API credentials are set
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            ?>
            <div class="notice notice-warning inline">
                <p><?php _e('You need to configure your Google API credentials before connecting to Google Analytics.', 'uipress-analytics-bridge'); ?></p>
                <p><a href="?page=uipress-analytics-bridge&tab=general" class="button button-primary"><?php _e('Configure API Credentials', 'uipress-analytics-bridge'); ?></a></p>
            </div>
            <?php
            return;
        }
        
        // Check if already connected
        if (!empty($connection['property_id']) && !empty($connection['property_name'])) {
            $property_type = isset($connection['measurement_id']) ? 'GA4' : 'Universal Analytics';
            ?>
            <div class="notice notice-success inline">
                <p><?php printf(__('Connected to %1$s (%2$s)', 'uipress-analytics-bridge'), esc_html($connection['property_name']), esc_html($property_type)); ?></p>
            </div>
            <p><?php _e('You can change your connected Google Analytics property using the options below.', 'uipress-analytics-bridge'); ?></p>
            <?php
        } else {
            ?>
            <p><?php _e('Connect your Google Analytics account to start bridging data to UIPress.', 'uipress-analytics-bridge'); ?></p>
            <?php
        }
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
        <ol>
            <li><?php _e('Go to the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and create a new project', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('Enable the Google Analytics API for your project', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('Create OAuth 2.0 credentials (Client ID and Client Secret)', 'uipress-analytics-bridge'); ?></li>
            <li><?php _e('Set the authorized redirect URI to:', 'uipress-analytics-bridge'); ?> <code><?php echo admin_url('admin-ajax.php?action=uipress_analytics_bridge_oauth_callback'); ?></code></li>
        </ol>
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
     * Analytics property field callback.
     *
     * @since    1.0.0
     */
    public function analytics_property_callback() {
        $connection = get_option('uipress_analytics_bridge_connection', array());
        $is_connected = (!empty($connection['property_id']) && !empty($connection['property_name']));
        
        // Get settings for API credentials check
        $settings = get_option('uipress_analytics_bridge_settings', array());
        $has_credentials = (!empty($settings['client_id']) && !empty($settings['client_secret']));
        
        if (!$has_credentials) {
            ?>
            <p class="description"><?php _e('Please configure your Google API credentials first.', 'uipress-analytics-bridge'); ?></p>
            <?php
            return;
        }
        
        if ($is_connected) {
            $property_type = isset($connection['measurement_id']) && !empty($connection['measurement_id']) ? 'GA4' : 'Universal Analytics';
            $property_id = isset($connection['measurement_id']) && !empty($connection['measurement_id']) ? 
                $connection['measurement_id'] : $connection['property_id'];
            ?>
            <div class="uipress-analytics-property-info">
                <p><strong><?php _e('Connected Property:', 'uipress-analytics-bridge'); ?></strong> <?php echo esc_html($connection['property_name']); ?></p>
                <p><strong><?php _e('Property ID:', 'uipress-analytics-bridge'); ?></strong> <?php echo esc_html($property_id); ?></p>
                <p><strong><?php _e('Property Type:', 'uipress-analytics-bridge'); ?></strong> <?php echo esc_html($property_type); ?></p>
                <button id="uipress-analytics-change-property" class="button button-secondary"><?php _e('Change Property', 'uipress-analytics-bridge'); ?></button>
                <button id="uipress-analytics-disconnect" class="button button-secondary"><?php _e('Disconnect', 'uipress-analytics-bridge'); ?></button>
            </div>
            <div id="uipress-analytics-property-selection" style="display: none; margin-top: 15px;">
                <p><?php _e('Select a different Google Analytics property:', 'uipress-analytics-bridge'); ?></p>
                <select id="uipress-analytics-property-select" style="min-width: 300px;">
                    <option value=""><?php _e('-- Select Property --', 'uipress-analytics-bridge'); ?></option>
                </select>
                <button id="uipress-analytics-save-property" class="button button-primary"><?php _e('Save Property', 'uipress-analytics-bridge'); ?></button>
                <button id="uipress-analytics-cancel" class="button button-secondary"><?php _e('Cancel', 'uipress-analytics-bridge'); ?></button>
                <p class="description"><?php _e('Note: Changing properties will update both global and user-specific settings.', 'uipress-analytics-bridge'); ?></p>
            </div>
            <?php
        } else {
            ?>
            <button id="uipress-analytics-connect" class="button button-primary"><?php _e('Connect Google Analytics', 'uipress-analytics-bridge'); ?></button>
            <p class="description"><?php _e('Click to authorize and select a Google Analytics property.', 'uipress-analytics-bridge'); ?></p>
            <div id="uipress-analytics-property-selection" style="display: none; margin-top: 15px;">
                <p><?php _e('Select a Google Analytics property:', 'uipress-analytics-bridge'); ?></p>
                <select id="uipress-analytics-property-select" style="min-width: 300px;">
                    <option value=""><?php _e('-- Select Property --', 'uipress-analytics-bridge'); ?></option>
                </select>
                <button id="uipress-analytics-save-property" class="button button-primary"><?php _e('Save Property', 'uipress-analytics-bridge'); ?></button>
                <p class="description"><?php _e('Select the property you want to connect to UIPress.', 'uipress-analytics-bridge'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Connection scope field callback.
     *
     * @since    1.0.0
     */
    public function connection_scope_callback() {
        $connection = get_option('uipress_analytics_bridge_connection', array());
        $scope = isset($connection['scope']) ? $connection['scope'] : 'global';
        
        ?>
        <label>
            <input type="radio" name="uipress_analytics_bridge_connection[scope]" value="global" <?php checked($scope, 'global'); ?>>
            <?php _e('Global (all users)', 'uipress-analytics-bridge'); ?>
        </label>
        <br>
        <label>
            <input type="radio" name="uipress_analytics_bridge_connection[scope]" value="user" <?php checked($scope, 'user'); ?>>
            <?php _e('User-specific (current user only)', 'uipress-analytics-bridge'); ?>
        </label>
        <p class="description"><?php _e('Determines whether this connection applies to all users or just to your account.', 'uipress-analytics-bridge'); ?></p>
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
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'connection';
        
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
            if (!defined('uip_plugin_version') || !defined('uip_pro_plugin_version')) {
                ?>
                <div class="notice notice-warning">
                    <p><?php _e('UIPress Lite and Pro are recommended for full functionality of this plugin.', 'uipress-analytics-bridge'); ?></p>
                </div>
                <?php
            }
            ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=uipress-analytics-bridge&tab=connection" class="nav-tab <?php echo $active_tab === 'connection' ? 'nav-tab-active' : ''; ?>"><?php _e('Connection', 'uipress-analytics-bridge'); ?></a>
                <a href="?page=uipress-analytics-bridge&tab=select-property" class="nav-tab <?php echo $active_tab === 'select-property' ? 'nav-tab-active' : ''; ?>"><?php _e('Select Property', 'uipress-analytics-bridge'); ?></a>
                <a href="?page=uipress-analytics-bridge&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('API Settings', 'uipress-analytics-bridge'); ?></a>
                <a href="?page=uipress-analytics-bridge&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>"><?php _e('Advanced', 'uipress-analytics-bridge'); ?></a>
            </h2>
            
            <?php
            // Display any saved settings errors/notices
            settings_errors('uipress_analytics_bridge');
            ?>
            
            <div class="uipress-analytics-bridge-content">
                <?php if ($active_tab === 'connection') : ?>
                    <?php
                    // Direct Connection Form
                    // Check if we have API credentials
                    $settings = get_option('uipress_analytics_bridge_settings', array());
                    $client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
                    $client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';

                    // Get the redirect URI for our callback
                    $redirect_uri = admin_url('admin-ajax.php') . '?action=uipress_analytics_bridge_oauth_callback';

                    // Check if we already have a connection
                    $connection = get_option('uipress_analytics_bridge_connection', array());
                    $is_connected = !empty($connection['property_id']);

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
                                    <!-- Direct connection link that will open the Google auth page -->
                                    <?php
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
                                
                                <p class="description"><?php _e('This will open a popup window to authenticate with Google.', 'uipress-analytics-bridge'); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    ?>
                <?php elseif ($active_tab === 'select-property') : ?>
                    <?php $this->display_property_selection(); ?>
                <?php elseif ($active_tab === 'general') : ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('uipress_analytics_bridge_settings');
                        do_settings_sections('uipress_analytics_bridge_settings');
                        submit_button();
                        ?>
                    </form>
                    
                <?php elseif ($active_tab === 'advanced') : ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('uipress_analytics_bridge_advanced');
                        do_settings_sections('uipress_analytics_bridge_advanced');
                        submit_button();
                        ?>
                    </form>
                
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display property selection interface
     */
    public function display_property_selection() {
        // Check if we have temporary tokens
        $tokens = get_option('uipress_analytics_bridge_temp_tokens', array());
        
        if (empty($tokens) || empty($tokens['access_token'])) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('No authentication data found. Please connect to Google Analytics again.', 'uipress-analytics-bridge'); ?></p>
            </div>
            <p><a href="?page=uipress-analytics-bridge&tab=connection" class="button button-primary"><?php _e('Connect Google Analytics', 'uipress-analytics-bridge'); ?></a></p>
            <?php
            return;
        }
        
        // Get Google Analytics accounts
        $accounts = $this->get_google_analytics_accounts($tokens['access_token']);
        
        if (empty($accounts) || isset($accounts['error'])) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Error fetching Google Analytics accounts:', 'uipress-analytics-bridge'); ?> 
                <?php echo isset($accounts['error']) ? esc_html($accounts['error']) : __('No accounts found', 'uipress-analytics-bridge'); ?></p>
            </div>
            <?php
            return;
        }
        
        // Display account and property selection form
        ?>
        <form method="post" action="<?php echo admin_url('options-general.php?page=uipress-analytics-bridge&tab=connection'); ?>">
            <?php wp_nonce_field('uipress-analytics-bridge-admin-nonce', 'security'); ?>
            <input type="hidden" name="uipress_analytics_bridge_save_property" value="1">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Google Analytics Account', 'uipress-analytics-bridge'); ?></th>
                    <td>
                        <select id="ga-account" name="ga_account" required>
                            <option value=""><?php _e('-- Select Account --', 'uipress-analytics-bridge'); ?></option>
                            <?php foreach ($accounts as $account) : ?>
                                <option value="<?php echo esc_attr($account['id']); ?>"><?php echo esc_html($account['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Google Analytics Property', 'uipress-analytics-bridge'); ?></th>
                    <td>
                        <select id="ga-property" name="ga_property" required disabled>
                            <option value=""><?php _e('-- Select Account First --', 'uipress-analytics-bridge'); ?></option>
                        </select>
                        <p class="description"><?php _e('Properties will load after selecting an account.', 'uipress-analytics-bridge'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Connection Scope', 'uipress-analytics-bridge'); ?></th>
                    <td>
                        <label>
                            <input type="radio" name="scope" value="global" checked>
                            <?php _e('Global (all users)', 'uipress-analytics-bridge'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="scope" value="user">
                            <?php _e('User-specific (current user only)', 'uipress-analytics-bridge'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e('Save Property', 'uipress-analytics-bridge'); ?>">
                <a href="?page=uipress-analytics-bridge&tab=connection" class="button button-secondary"><?php _e('Cancel', 'uipress-analytics-bridge'); ?></a>
            </p>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ga-account').on('change', function() {
                var accountId = $(this).val();
                
                if (accountId) {
                    // Enable and show loading state for property dropdown
                    $('#ga-property').prop('disabled', true)
                        .html('<option value=""><?php _e('Loading properties...', 'uipress-analytics-bridge'); ?></option>');
                    
                    // Fetch properties for the selected account
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uipress_analytics_bridge_get_properties',
                            security: '<?php echo wp_create_nonce('uipress-analytics-bridge-admin-nonce'); ?>',
                            account_id: accountId
                        },
                        success: function(response) {
                            $('#ga-property').prop('disabled', false);
                            
                            if (response.success && response.data.properties.length > 0) {
                                var options = '<option value=""><?php _e('-- Select Property --', 'uipress-analytics-bridge'); ?></option>';
                                
                                $.each(response.data.properties, function(i, property) {
                                    // Don't add type to display name - it's already included in data-type
                                    options += '<option value="' + property.id + '" ' + 
                                              'data-type="' + property.type + '" ' + 
                                              'data-name="' + property.name.replace(/"/g, '&quot;') + '" ' + 
                                              'data-measurement-id="' + (property.measurement_id || '') + '">' + 
                                              property.name + '</option>';
                                });
                                
                                $('#ga-property').html(options);
                            } else {
                                $('#ga-property').html('<option value=""><?php _e('No properties found', 'uipress-analytics-bridge'); ?></option>');
                            }
                        },
                        error: function() {
                            $('#ga-property').prop('disabled', false)
                                .html('<option value=""><?php _e('Error loading properties', 'uipress-analytics-bridge'); ?></option>');
                        }
                    });
                } else {
                    // Reset property dropdown
                    $('#ga-property').prop('disabled', true)
                        .html('<option value=""><?php _e('-- Select Account First --', 'uipress-analytics-bridge'); ?></option>');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Process form submissions
     */
    public function process_form_submissions() {
        // Check if we're processing a property save
        if (isset($_POST['uipress_analytics_bridge_save_property']) && 
            isset($_POST['security']) && 
            isset($_POST['ga_property']) && 
            isset($_POST['ga_account'])) {
            
            // Verify nonce
            check_admin_referer('uipress-analytics-bridge-admin-nonce', 'security');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions.', 'uipress-analytics-bridge'));
            }
            
            $property_id = sanitize_text_field($_POST['ga_property']);
            $account_id = sanitize_text_field($_POST['ga_account']);
            $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'global';
            
            if (empty($property_id) || empty($account_id)) {
                add_settings_error(
                    'uipress_analytics_bridge',
                    'missing_property',
                    __('Please select both an account and a property.', 'uipress-analytics-bridge'),
                    'error'
                );
                return;
            }
            
            // Get tokens
            $tokens = get_option('uipress_analytics_bridge_temp_tokens', array());
            
            if (empty($tokens) || empty($tokens['access_token'])) {
                add_settings_error(
                    'uipress_analytics_bridge',
                    'missing_auth',
                    __('Authentication data is missing. Please reconnect to Google Analytics.', 'uipress-analytics-bridge'),
                    'error'
                );
                return;
            }
            
            // Fetch properties to get details for the selected property
            $response = wp_remote_get('https://www.googleapis.com/analytics/v3/management/accounts/' . $account_id . '/webproperties', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $tokens['access_token']
                )
            ));
            
            if (is_wp_error($response)) {
                add_settings_error(
                    'uipress_analytics_bridge',
                    'api_error',
                    __('Error fetching property details: ', 'uipress-analytics-bridge') . $response->get_error_message(),
                    'error'
                );
                return;
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            // Find the selected property
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
                add_settings_error(
                    'uipress_analytics_bridge',
                    'property_not_found',
                    __('The selected property could not be found.', 'uipress-analytics-bridge'),
                    'error'
                );
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
            if (function_exists('uipress_analytics_bridge_update_uipress_settings')) {
                uipress_analytics_bridge_update_uipress_settings($connection);
            } else {
                $this->update_uipress_settings($connection);
            }
            
            // Clean up temporary tokens
            delete_option('uipress_analytics_bridge_temp_tokens');
            
            // Add success message
            add_settings_error(
                'uipress_analytics_bridge',
                'property_saved',
                __('Google Analytics property connected successfully!', 'uipress-analytics-bridge'),
                'success'
            );
            
            // Force redirect to prevent form resubmission
            wp_safe_redirect(admin_url('options-general.php?page=uipress-analytics-bridge&tab=connection&connected=1'));
            exit;
        }
    }

    /**
     * AJAX handler for connecting to Google Analytics.
     *
     * @since    1.0.0
     */
    public function connect_to_google_analytics() {
        // Check for valid request
        check_ajax_referer('uipress-analytics-bridge-admin-nonce', 'security');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'uipress-analytics-bridge')));
            return;
        }
        
        // Get the settings
        $settings = get_option('uipress_analytics_bridge_settings', array());
        
        // Check for client ID and secret
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            wp_send_json_error(array('message' => __('Google API credentials not configured.', 'uipress-analytics-bridge')));
            return;
        }
        
        // For demonstration purposes, let's say we have an auth URL
        // In a real implementation, we would initiate the OAuth flow here
        $auth_url = $this->get_oauth_url($settings['client_id']);
        
        wp_send_json_success(array(
            'auth_url' => $auth_url
        ));
    }

    /**
     * AJAX handler for getting analytics properties.
     *
     * @since    1.0.0
     */
    public function get_analytics_properties() {
        // Check for valid request
        check_ajax_referer('uipress-analytics-bridge-admin-nonce', 'security');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'uipress-analytics-bridge')));
            return;
        }
        
        // In a real implementation, we would fetch properties from the Google Analytics API
        // For demonstration, we'll return some sample properties
        $properties = array(
            array(
                'id' => '123456789',
                'name' => 'Example Website (UA)',
                'type' => 'UA'
            ),
            array(
                'id' => 'G-ABCDEFGHIJ',
                'name' => 'Example Website (GA4)',
                'type' => 'GA4',
                'measurement_id' => 'G-ABCDEFGHIJ'
            )
        );
        
        wp_send_json_success(array(
            'properties' => $properties
        ));
    }

    /**
     * AJAX handler for saving analytics property.
     *
     * @since    1.0.0
     */
    public function save_analytics_property() {
        // Check for valid request
        check_ajax_referer('uipress-analytics-bridge-admin-nonce', 'security');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'uipress-analytics-bridge')));
            return;
        }
        
        // Get and validate the property data
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';
        $property_name = isset($_POST['property_name']) ? sanitize_text_field($_POST['property_name']) : '';
        $property_type = isset($_POST['property_type']) ? sanitize_text_field($_POST['property_type']) : '';
        $measurement_id = isset($_POST['measurement_id']) ? sanitize_text_field($_POST['measurement_id']) : '';
        $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'global';
        
        if (empty($property_id) || empty($property_name)) {
            wp_send_json_error(array('message' => __('Invalid property data.', 'uipress-analytics-bridge')));
            return;
        }
        
        // Save the connection details
        $connection = array(
            'property_id' => $property_id,
            'property_name' => $property_name,
            'scope' => $scope
        );
        
        // Add measurement ID for GA4 properties
        if ($property_type === 'GA4' && !empty($measurement_id)) {
            $connection['measurement_id'] = $measurement_id;
        }
        
        // Update the connection
        update_option('uipress_analytics_bridge_connection', $connection);
        
        // Update UIPress settings if UIPress is active
        if (defined('uip_plugin_version') && defined('uip_pro_plugin_version')) {
            $this->update_uipress_settings($connection);
        }
        
        wp_send_json_success(array(
            'message' => __('Google Analytics property saved successfully.', 'uipress-analytics-bridge'),
            'connection' => $connection
        ));
    }

    /**
     * Update UIPress settings with our connection data.
     *
     * @since    1.0.0
     * @param    array    $connection    The connection data.
     */
    private function update_uipress_settings($connection) {
        // Only proceed if UIPress is active
        if (!defined('uip_plugin_version') || !defined('uip_pro_plugin_version')) {
            return;
        }
        
        // Prepare the Google Analytics data in UIPress format
        $ga_data = array(
            'view' => $connection['property_id'],
            'code' => 'bridge_connection', // Placeholder code
            'token' => 'bridge_token', // Placeholder token
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
     * Get OAuth URL.
     *
     * @since    1.0.0
     * @param    string    $client_id    The Google API client ID.
     * @return   string                 The OAuth URL.
     */
    private function get_oauth_url($client_id) {
        $redirect_uri = admin_url('admin-ajax.php?action=uipress_analytics_bridge_oauth_callback');
        $scope = 'https://www.googleapis.com/auth/analytics.readonly';
        $state = wp_create_nonce('uipress-analytics-bridge-oauth');
        
        $url = 'https://accounts.google.com/o/oauth2/v2/auth';
        $url .= '?client_id=' . urlencode($client_id);
        $url .= '&redirect_uri=' . urlencode($redirect_uri);
        $url .= '&scope=' . urlencode($scope);
        $url .= '&access_type=offline';
        $url .= '&response_type=code';
        $url .= '&state=' . urlencode($state);
        $url .= '&prompt=consent';
        
        return $url;
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
}