<?php
/**
 * Plugin Name: Mavlers Contact Forms
 * Plugin URI: https://mavlers.com
 * Description: A comprehensive contact form builder for WordPress with advanced features including email notifications, file uploads, reCAPTCHA integration, and third-party service integrations.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: Mavlers
 * Author URI: https://mavlers.com
 * Text Domain: mavlers-contact-forms
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Network: false
 * 
 * @package MavlersContactForms
 * @version 1.0.0
 * @author Mavlers
 * @license GPL-2.0+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent direct file access
if (!defined('WPINC')) {
    die;
}

// Define plugin constants first
define('MAVLERS_CF_VERSION', '1.0.0');
define('MAVLERS_CF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAVLERS_CF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAVLERS_CF_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MAVLERS_CF_PLUGIN_FILE', __FILE__);
define('MAVLERS_CF_MIN_WP_VERSION', '5.0');
define('MAVLERS_CF_MIN_PHP_VERSION', '7.4');

// Check WordPress version
if (version_compare(get_bloginfo('version'), MAVLERS_CF_MIN_WP_VERSION, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             sprintf(__('Mavlers Contact Forms requires WordPress %s or higher. Please upgrade WordPress.', 'mavlers-contact-forms'), MAVLERS_CF_MIN_WP_VERSION) . 
             '</p></div>';
    });
    return;
}

// Check PHP version
if (version_compare(PHP_VERSION, MAVLERS_CF_MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             sprintf(__('Mavlers Contact Forms requires PHP %s or higher. Please contact your hosting provider.', 'mavlers-contact-forms'), MAVLERS_CF_MIN_PHP_VERSION) . 
             '</p></div>';
    });
    return;
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-mavlers-cf-activator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mavlers-cf-deactivator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mavlers-cf-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mavlers-cf-form-builder.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mavlers-cf-form-renderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mavlers-cf-submission-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mavlers-cf-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mavlers-cf-integrations.php';

// Activation and deactivation hooks
register_activation_hook(__FILE__, array('Mavlers_CF_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Mavlers_CF_Deactivator', 'deactivate'));

// Uninstall hook
register_uninstall_hook(__FILE__, 'mavlers_cf_uninstall');

/**
 * Plugin uninstall function
 */
function mavlers_cf_uninstall() {
    global $wpdb;
    
    // Drop plugin tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mavlers_cf_forms");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mavlers_cf_submissions");
    
    // Clean up options
    delete_option('mavlers_cf_recaptcha_settings');
    delete_option('mavlers_cf_antispam_settings');
    
    // Clean up transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mavlers_cf_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mavlers_cf_%'");
}

// Load text domain early
function mavlers_cf_load_textdomain() {
    load_plugin_textdomain('mavlers-contact-forms', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'mavlers_cf_load_textdomain', 1);

// Initialize the plugin
function mavlers_cf_init() {
    // Initialize admin
    if (is_admin()) {
        $admin = new Mavlers_CF_Admin('mavlers-contact-forms', MAVLERS_CF_VERSION);
    }

    // Initialize form renderer
    $form_renderer = new Mavlers_CF_Form_Renderer();

    // Initialize submission handler
    $submission_handler = new Mavlers_CF_Submission_Handler();
}
add_action('plugins_loaded', 'mavlers_cf_init');

// Add shortcode
function mavlers_cf_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => 0
    ), $atts, 'mavlers_contact_form');

    if (empty($atts['id'])) {
        return '';
    }

    $form_renderer = new Mavlers_CF_Form_Renderer();
    return $form_renderer->render_form($atts['id']);
}
add_shortcode('mavlers_contact_form', 'mavlers_cf_shortcode'); 
// Load addon system if available
do_action('mavlers_cf_load_addons');
if (file_exists(MAVLERS_CF_PLUGIN_DIR . 'addons/integrations-manager/integrations-manager.php')) {
    include_once MAVLERS_CF_PLUGIN_DIR . 'addons/integrations-manager/integrations-manager.php';
}