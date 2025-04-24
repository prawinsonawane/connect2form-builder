<?php
/**
 * Plugin Name: Mavlers Contact Form Builder
 * Plugin URI: https://mavlers.com/contact-form-builder
 * Description: A powerful drag-and-drop form builder with email notifications, form analytics, and advanced features.
 * Version: 1.0.0
 * Author: Mavlers
 * Author URI: https://mavlers.com
 * Text Domain: mavlers-contact-form
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAVLERS_FORM_VERSION', '1.0.0');
define('MAVLERS_FORM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAVLERS_FORM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAVLERS_FORM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once MAVLERS_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-builder.php';
require_once MAVLERS_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-database.php';
require_once MAVLERS_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-submissions.php';
require_once MAVLERS_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-analytics.php';
require_once MAVLERS_FORM_PLUGIN_DIR . 'includes/class-mavlers-admin.php';

// Initialize the plugin
function mavlers_contact_form_init() {
    // Load text domain
    load_plugin_textdomain('mavlers-contact-form', false, dirname(MAVLERS_FORM_PLUGIN_BASENAME) . '/languages');

    // Initialize main classes
    Mavlers_Form_Builder::get_instance();
    Mavlers_Form_Database::get_instance();
    Mavlers_Form_Submissions::get_instance();
    Mavlers_Form_Analytics::get_instance();
    
    // Initialize admin class
    if (is_admin()) {
        Mavlers_Admin::get_instance();
    }
}

// Hook initialization
add_action('plugins_loaded', 'mavlers_contact_form_init');

// Activation hook
register_activation_hook(__FILE__, 'mavlers_contact_form_activate');
function mavlers_contact_form_activate() {
    // Create necessary database tables
    Mavlers_Form_Database::create_tables();
    
    // Set default options
    add_option('mavlers_form_version', MAVLERS_FORM_VERSION);
    add_option('mavlers_form_settings', array(
        'enable_analytics' => true,
        'save_submissions' => true,
        'default_email_template' => 'default',
        'recaptcha_site_key' => '',
        'recaptcha_secret_key' => '',
        'enable_spam_protection' => true,
        'max_submissions_per_hour' => 100
    ));
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'mavlers_contact_form_deactivate');
function mavlers_contact_form_deactivate() {
    // Clean up if needed
}

// Add settings link to plugins page
add_filter('plugin_action_links_' . MAVLERS_FORM_PLUGIN_BASENAME, 'mavlers_contact_form_settings_link');
function mavlers_contact_form_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=mavlers-forms') . '">' . __('Settings', 'mavlers-contact-form') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
