<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      UIPress_Analytics_Bridge_Loader    $loader    Maintains and registers all hooks.
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
     * Tracks whether UIPress is active.
     *
     * @since    1.0.0
     * @access   protected
     * @var      bool    $uipress_active    Whether UIPress is active.
     */
    protected $uipress_active;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = UIPRESS_ANALYTICS_BRIDGE_VERSION;
        $this->plugin_name = 'uipress-analytics-bridge';

        $this->load_dependencies();
        $this->set_locale();
        
        // Initialize components only when WordPress is ready (init hook)
        add_action('init', array($this, 'init_components'), 0);
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - UIPress_Analytics_Bridge_Loader. Orchestrates the hooks of the plugin.
     * - UIPress_Analytics_Bridge_i18n. Defines internationalization functionality.
     * - UIPress_Analytics_Bridge_Detector. Handles UIPress detection.
     *
     * Create an instance of the loader which will be used to register the hooks with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-loader.php';

        /**
         * The class responsible for defining internationalization functionality.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-i18n.php';

        /**
         * The class responsible for detecting UIPress installation.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-detector.php';

        /**
         * The class responsible for activation functionality.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-activator.php';
        
        /**
         * The class responsible for deactivation functionality.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-deactivator.php';

        // Create the loader that will be used to register all hooks
        $this->loader = new UIPress_Analytics_Bridge_Loader();
    }

    /**
     * Initialize components when WordPress is fully initialized
     * 
     * @since    1.0.0
     * @access   public
     */
    public function init_components() {
        /**
         * The class responsible for authentication handling.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-auth.php';

        /**
         * The class responsible for data formatting.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/class-uipress-analytics-bridge-data.php';

        /**
         * The class responsible for Google API authentication.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/api/class-uipress-analytics-bridge-api-auth.php';

        /**
         * The class responsible for Google API data retrieval.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'includes/api/class-uipress-analytics-bridge-api-data.php';

        /**
         * The class responsible for admin-specific functionality.
         */
        require_once UIPRESS_ANALYTICS_BRIDGE_PLUGIN_PATH . 'admin/class-uipress-analytics-bridge-admin.php';

        // Initialize and register hooks for each component
        $this->define_admin_hooks();
        $this->define_auth_hooks();
        $this->define_data_hooks();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the UIPress_Analytics_Bridge_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new UIPress_Analytics_Bridge_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new UIPress_Analytics_Bridge_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Use priority 10 to ensure our menu item is registered properly
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_settings_page', 10);
        
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        $this->loader->add_filter('plugin_action_links_' . UIPRESS_ANALYTICS_BRIDGE_PLUGIN_BASENAME, $plugin_admin, 'add_action_links');
        
        // Add an admin notice after activation to guide users to the settings page
        if (get_transient('uipress_analytics_bridge_activation_notice')) {
            $this->loader->add_action('admin_notices', $plugin_admin, 'activation_notice');
        }
    }

    /**
     * Register all of the hooks related to authentication.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_auth_hooks() {
        $plugin_auth = new UIPress_Analytics_Bridge_Auth($this->get_plugin_name(), $this->get_version());
        
        // Register AJAX handlers for authentication
        $this->loader->add_action('wp_ajax_uip_save_google_analytics', $plugin_auth, 'intercept_save_account');
        $this->loader->add_action('wp_ajax_uip_save_access_token', $plugin_auth, 'intercept_save_access_token');
        $this->loader->add_action('wp_ajax_uip_remove_analytics_account', $plugin_auth, 'intercept_remove_account');
        
        // Add our own AJAX actions
        $this->loader->add_action('wp_ajax_uipress_analytics_bridge_auth', $plugin_auth, 'handle_auth_callback');
        $this->loader->add_action('wp_ajax_uipress_analytics_bridge_oauth_url', $plugin_auth, 'get_oauth_url');
        
        // Custom filter for authentication status
        $this->loader->add_filter('pre_option_uip_google_analytics_status', $plugin_auth, 'filter_analytics_status', 10, 1);
    }

    /**
     * Register all of the hooks related to data formatting and retrieval.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_data_hooks() {
        $plugin_data = new UIPress_Analytics_Bridge_Data($this->get_plugin_name(), $this->get_version());
        
        // Intercept UIPress analytics query building
        $this->loader->add_action('wp_ajax_uip_build_google_analytics_query', $plugin_data, 'intercept_build_query');
        
        // Add our own AJAX actions for data retrieval
        $this->loader->add_action('wp_ajax_uipress_analytics_bridge_get_data', $plugin_data, 'get_analytics_data');
        
        // Add filter for modifying analytics data
        $this->loader->add_filter('uip_filter_google_analytics_data', $plugin_data, 'filter_analytics_data', 10, 1);
    }

    /**
     * Run the loader to execute all the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        // Initialize the detector
        $detector = new UIPress_Analytics_Bridge_Detector($this->get_plugin_name(), $this->get_version());
        
        // Always run the loader to ensure admin settings are available
        // This ensures settings page shows up even if UIPress is not active
        $this->loader->run();
        
        // Store the UIPress status for reference throughout the plugin
        $this->uipress_active = $detector->is_uipress_active();
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
}