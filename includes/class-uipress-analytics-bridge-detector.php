<?php
/**
 * The class responsible for detecting UIPress installation.
 *
 * @since      1.0.0
 * @package    UIPress_Analytics_Bridge
 * @subpackage UIPress_Analytics_Bridge/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class UIPress_Analytics_Bridge_Detector {

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
     * Check if UIPress is active and compatible.
     *
     * @since    1.0.0
     * @return   bool      Returns true if UIPress is active and compatible.
     */
    public function is_uipress_active() {
        // Check if UIPress Lite exists and is activated
        if (!defined('uip_plugin_version')) {
            $this->add_admin_notice(
                'error',
                __('UIPress Analytics Bridge requires UIPress Lite to be installed and activated.', 'uipress-analytics-bridge')
            );
            return false;
        }
        
        // Check if UIPress Pro exists and is activated
        if (!defined('uip_pro_plugin_version')) {
            $this->add_admin_notice(
                'error',
                __('UIPress Analytics Bridge requires UIPress Pro to be installed and activated.', 'uipress-analytics-bridge')
            );
            return false;
        }
        
        // Check UIPress Lite version compatibility
        if (version_compare(uip_plugin_version, '3.0.0', '<')) {
            $this->add_admin_notice(
                'error',
                sprintf(
                    __('UIPress Analytics Bridge requires UIPress Lite version 3.0.0 or higher. You are using version %s.', 'uipress-analytics-bridge'),
                    uip_plugin_version
                )
            );
            return false;
        }
        
        // Check UIPress Pro version compatibility
        if (version_compare(uip_pro_plugin_version, '3.0.0', '<')) {
            $this->add_admin_notice(
                'error',
                sprintf(
                    __('UIPress Analytics Bridge requires UIPress Pro version 3.0.0 or higher. You are using version %s.', 'uipress-analytics-bridge'),
                    uip_pro_plugin_version
                )
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if specific UIPress features are available.
     *
     * @since    1.0.0
     * @return   array     Returns an array of available UIPress features.
     */
    public function get_uipress_features() {
        $features = array(
            'google_analytics' => false,
            'admin_menus' => false,
        );
        
        // Check for Google Analytics integration
        if (class_exists('UipressPro\Classes\Blocks\GoogleAnalytics')) {
            $features['google_analytics'] = true;
        }
        
        // Check for Admin Menus feature
        if (class_exists('UipressPro\Classes\PostTypes\AdminMenus')) {
            $features['admin_menus'] = true;
        }
        
        return $features;
    }
    
    /**
     * Add admin notice
     *
     * @since    1.0.0
     * @param    string    $type              The type of notice (error, warning, success, info).
     * @param    string    $message           The message to display.
     * @param    bool      $is_dismissible    Whether the notice should be dismissible.
     */
    private function add_admin_notice($type, $message, $is_dismissible = true) {
        add_action('admin_notices', function() use ($type, $message, $is_dismissible) {
            $class = 'notice notice-' . $type;
            if ($is_dismissible) {
                $class .= ' is-dismissible';
            }
            
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        });
    }
    
    /**
     * Get UIPress class paths
     *
     * @since    1.0.0
     * @return   array     Returns an array of UIPress class paths.
     */
    public function get_uipress_class_paths() {
        $paths = array(
            'google_analytics' => false,
            'ajax_utils' => false,
            'sanitize_utils' => false,
            'user_preferences' => false,
            'uip_options' => false,
        );
        
        // Check for GoogleAnalytics class
        if (class_exists('UipressPro\Classes\Blocks\GoogleAnalytics')) {
            $paths['google_analytics'] = 'UipressPro\Classes\Blocks\GoogleAnalytics';
        }
        
        // Check for Ajax utils
        if (class_exists('UipressLite\Classes\Utils\Ajax')) {
            $paths['ajax_utils'] = 'UipressLite\Classes\Utils\Ajax';
        }
        
        // Check for Sanitize utils
        if (class_exists('UipressLite\Classes\Utils\Sanitize')) {
            $paths['sanitize_utils'] = 'UipressLite\Classes\Utils\Sanitize';
        }
        
        // Check for UserPreferences
        if (class_exists('UipressLite\Classes\App\UserPreferences')) {
            $paths['user_preferences'] = 'UipressLite\Classes\App\UserPreferences';
        }
        
        // Check for UipOptions
        if (class_exists('UipressLite\Classes\App\UipOptions')) {
            $paths['uip_options'] = 'UipressLite\Classes\App\UipOptions';
        }
        
        return $paths;
    }
}