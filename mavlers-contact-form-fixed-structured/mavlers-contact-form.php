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

// AJAX handlers for form actions
add_action('wp_ajax_mavlers_delete_form', 'mavlers_delete_form_handler');
add_action('wp_ajax_mavlers_duplicate_form', 'mavlers_duplicate_form_handler');

function mavlers_delete_form_handler() {
    check_ajax_referer('mavlers_forms_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $form_id = intval($_POST['form_id']);
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'mavlers_forms';
    
    $result = $wpdb->delete($forms_table, array('id' => $form_id), array('%d'));
    
    if ($result !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete form');
    }
}

function mavlers_duplicate_form_handler() {
    check_ajax_referer('mavlers_forms_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $form_id = intval($_POST['form_id']);
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'mavlers_forms';
    
    // Get the original form
    $original_form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE id = %d",
        $form_id
    ));
    
    if (!$original_form) {
        wp_send_json_error('Form not found');
    }
    
    // Create a copy of the form
    $new_form_data = array(
        'form_name' => $original_form->form_name . ' (Copy)',
        'form_data' => $original_form->form_data,
        'status' => 'draft',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );
    
    $result = $wpdb->insert($forms_table, $new_form_data);
    
    if ($result !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to duplicate form');
    }
}
