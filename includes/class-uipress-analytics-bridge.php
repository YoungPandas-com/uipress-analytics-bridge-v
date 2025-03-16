<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      UIPress_Analytics_Bridge_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * The UIPress detector instance.
     *
     * @since    1.0.0
     * @access   protected
     * @var      UIPress_Analytics_Bridge_Detector    $detector    Detects UIPress installation.
     */
    protected $detector;

    /**
     * The auth handler instance.
     *
     * @since    1.0.0
     * @access   protected
     * @var      UIPress_Analytics_Bridge_Auth    $auth    Handles WordPress-side authentication.
     */
    protected $auth;

    /**
     * The data handler instance.
     *
     * @since    1.0.0
     * @access   protected
     * @var      UIPress_Analytics_Bridge_Data    $data    Handles data formatting.
     */
    protected $data;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = UIPRESS_ANALYTICS_BRIDGE_VERSION;
        $this->plugin_name = UIPRESS_ANALYTICS_BRIDGE_PLUGIN_NAME;
        
        $this->load_dependencies();
        $this->set_locale();
        $this->initialize_components();
        $this->define_admin_hooks();
        $this->define_ajax_hooks();
        $this->define_filter_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // The class responsible for orchestrating the actions and filters of the core plugin.
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-loader.php';

        // The class responsible for defining internationalization functionality.
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-i18n.php';

        // The class responsible for detecting UIPress installation.
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-detector.php';

        // The class responsible for WordPress-side authentication handling.
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-auth.php';

        // The class responsible for data handling/formatting.
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-data.php';

        // The class responsible for Google API authentication.
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/api/class-uipress-analytics-bridge-api-auth.php';

        // The class responsible for Google API data retrieval.
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/api/class-uipress-analytics-bridge-api-data.php';

        // The class responsible for admin-specific functionality.
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'admin/class-uipress-analytics-bridge-admin.php';

        // Create the loader that will be used to register hooks with WordPress.
        $this->loader = new UIPress_Analytics_Bridge_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new UIPress_Analytics_Bridge_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Initialize plugin components.
     *
     * @since    1.0.0
     * @access   private
     */
    private function initialize_components() {
        // Initialize the UIPress detector.
        $this->detector = new UIPress_Analytics_Bridge_Detector();
        
        // Initialize the API auth handler
        $api_auth = new UIPress_Analytics_Bridge_API_Auth();
        
        // Initialize the API data handler
        $api_data = new UIPress_Analytics_Bridge_API_Data($api_auth);
        
        // Initialize the auth handler.
        $this->auth = new UIPress_Analytics_Bridge_Auth($api_auth, $api_data);
        
        // Initialize the data handler.
        $this->data = new UIPress_Analytics_Bridge_Data($this->auth, $api_data);
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new UIPress_Analytics_Bridge_Admin($this->get_plugin_name(), $this->get_version(), $this->auth, $this->data, $this->detector);

        // Admin menu and settings page
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_options_page');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        
        // Admin assets
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Admin notices
        $this->loader->add_action('admin_notices', $plugin_admin, 'admin_notices');
        
        // Check UIPress existence
        $this->loader->add_action('admin_init', $this->detector, 'check_uipress');
    }

    /**
     * Register all of the AJAX hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_ajax_hooks() {
        // Intercept UIPress Pro AJAX actions
        $this->loader->add_action('wp_ajax_uip_build_google_analytics_query', $this->data, 'intercept_build_query');
        $this->loader->add_action('wp_ajax_uip_save_google_analytics', $this->auth, 'intercept_save_account');
        $this->loader->add_action('wp_ajax_uip_save_access_token', $this->auth, 'intercept_save_access_token');
        $this->loader->add_action('wp_ajax_uip_remove_analytics_account', $this->auth, 'intercept_remove_account');
        
        // Plugin's own AJAX actions
        $this->loader->add_action('wp_ajax_uipress_analytics_bridge_auth', $this->auth, 'handle_auth_callback');
        $this->loader->add_action('wp_ajax_uipress_analytics_bridge_get_analytics', $this->data, 'get_analytics_data_ajax');
        $this->loader->add_action('wp_ajax_uipress_analytics_bridge_test_connection', $this->data, 'test_connection_ajax');
    }

    /**
     * Register all of the filter hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_filter_hooks() {
        // Apply filter to UIPress analytics data
        $this->loader->add_filter('uip_filter_google_analytics_data', $this->data, 'filter_analytics_data', 10, 1);
        
        // Override UIPress analytics status option
        $this->loader->add_filter('pre_option_uip_google_analytics_status', $this->auth, 'filter_analytics_status', 10, 1);
        
        // Plugin settings link
        $plugin_admin = new UIPress_Analytics_Bridge_Admin($this->get_plugin_name(), $this->get_version(), $this->auth, $this->data, $this->detector);
        $this->loader->add_filter('plugin_action_links_' . UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME, $plugin_admin, 'plugin_action_links');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    UIPress_Analytics_Bridge_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get the auth handler instance.
     *
     * @since     1.0.0
     * @return    UIPress_Analytics_Bridge_Auth    The auth handler instance.
     */
    public function get_auth() {
        return $this->auth;
    }

    /**
     * Get the data handler instance.
     *
     * @since     1.0.0
     * @return    UIPress_Analytics_Bridge_Data    The data handler instance.
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Get the UIPress detector instance.
     *
     * @since     1.0.0
     * @return    UIPress_Analytics_Bridge_Detector    The UIPress detector instance.
     */
    public function get_detector() {
        return $this->detector;
    }
}