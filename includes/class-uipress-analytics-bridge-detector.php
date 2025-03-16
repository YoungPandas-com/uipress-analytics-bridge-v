<?php
/**
 * Class responsible for detecting UIPress installation.
 *
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Detector {

    /**
     * UIPress Lite detection status.
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $uipress_lite_active    Whether UIPress Lite is active.
     */
    private $uipress_lite_active = false;

    /**
     * UIPress Pro detection status.
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $uipress_pro_active    Whether UIPress Pro is active.
     */
    private $uipress_pro_active = false;

    /**
     * UIPress Lite version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $uipress_lite_version    The UIPress Lite version.
     */
    private $uipress_lite_version = null;

    /**
     * UIPress Pro version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $uipress_pro_version    The UIPress Pro version.
     */
    private $uipress_pro_version = null;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Defer UIPress detection to admin_init hook
    }

    /**
     * Check for UIPress installation.
     *
     * @since    1.0.0
     */
    public function check_uipress() {
        // Check for UIPress Lite
        if (defined('uip_plugin_version')) {
            $this->uipress_lite_active = true;
            $this->uipress_lite_version = uip_plugin_version;
        }

        // Check for UIPress Pro
        if (defined('uip_pro_plugin_version')) {
            $this->uipress_pro_active = true;
            $this->uipress_pro_version = uip_pro_plugin_version;
        }

        // Store detection results in transient for quick access
        set_transient('uipress_analytics_bridge_detection', array(
            'lite_active' => $this->uipress_lite_active,
            'pro_active' => $this->uipress_pro_active,
            'lite_version' => $this->uipress_lite_version,
            'pro_version' => $this->uipress_pro_version,
        ), DAY_IN_SECONDS);

        // Add admin notice if UIPress Pro is not active
        if (!$this->uipress_pro_active) {
            add_action('admin_notices', array($this, 'uipress_pro_missing_notice'));
        }
    }

    /**
     * Display admin notice if UIPress Pro is not active.
     *
     * @since    1.0.0
     */
    public function uipress_pro_missing_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('UIPress Analytics Bridge requires UIPress Pro to be installed and activated for full functionality.', 'uipress-analytics-bridge'); ?></p>
        </div>
        <?php
    }

    /**
     * Check if UIPress Lite is active.
     *
     * @since    1.0.0
     * @return   bool    Whether UIPress Lite is active.
     */
    public function is_uipress_lite_active() {
        return $this->uipress_lite_active;
    }

    /**
     * Check if UIPress Pro is active.
     *
     * @since    1.0.0
     * @return   bool    Whether UIPress Pro is active.
     */
    public function is_uipress_pro_active() {
        return $this->uipress_pro_active;
    }

    /**
     * Get UIPress Lite version.
     *
     * @since    1.0.0
     * @return   string|null    The UIPress Lite version, or null if not active.
     */
    public function get_uipress_lite_version() {
        return $this->uipress_lite_version;
    }

    /**
     * Get UIPress Pro version.
     *
     * @since    1.0.0
     * @return   string|null    The UIPress Pro version, or null if not active.
     */
    public function get_uipress_pro_version() {
        return $this->uipress_pro_version;
    }

    /**
     * Check if UIPress integration is possible.
     *
     * @since    1.0.0
     * @return   bool    Whether UIPress integration is possible.
     */
    public function is_uipress_integration_possible() {
        return $this->uipress_lite_active && $this->uipress_pro_active;
    }

    /**
     * Verify UIPress has the required hooks.
     *
     * @since    1.0.0
     * @return   array    Status of required hooks.
     */
    public function verify_uipress_hooks() {
        $hooks_status = array(
            'wp_ajax_uip_build_google_analytics_query' => false,
            'wp_ajax_uip_save_google_analytics' => false,
            'wp_ajax_uip_save_access_token' => false,
            'wp_ajax_uip_remove_analytics_account' => false,
        );

        global $wp_filter;

        foreach (array_keys($hooks_status) as $hook) {
            $hook_name = str_replace('wp_ajax_', 'wp_ajax_nopriv_', $hook);
            if (isset($wp_filter[$hook]) || isset($wp_filter[$hook_name])) {
                $hooks_status[$hook] = true;
            }
        }

        return $hooks_status;
    }

    /**
     * Check if specific UIPress options exist.
     *
     * @since    1.0.0
     * @return   array    Status of required options.
     */
    public function check_uipress_options() {
        $options_status = array(
            'uip_google_analytics' => false,
            'uip_google_analytics_status' => false,
        );

        // Check if options exist in wp_options table
        global $wpdb;
        
        foreach (array_keys($options_status) as $option) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option
            ));
            
            $options_status[$option] = ($result !== null);
        }

        // Check if options exist as part of UIPress global settings
        $uip_settings = get_option('uip-global-settings');
        if (is_array($uip_settings)) {
            if (isset($uip_settings['google_analytics'])) {
                $options_status['uip_google_analytics'] = true;
            }
        }

        return $options_status;
    }

    /**
     * Get detailed diagnostic information for troubleshooting.
     *
     * @since    1.0.0
     * @return   array    Diagnostic information.
     */
    public function get_diagnostics() {
        return array(
            'uipress_lite_active' => $this->uipress_lite_active,
            'uipress_pro_active' => $this->uipress_pro_active,
            'uipress_lite_version' => $this->uipress_lite_version,
            'uipress_pro_version' => $this->uipress_pro_version,
            'hooks_status' => $this->verify_uipress_hooks(),
            'options_status' => $this->check_uipress_options(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
        );
    }
}