<?php
/**
 * Plugin Name: Mavlers Contact Form
 * Plugin URI: https://mavlers.com
 * Description: A custom contact form plugin for Mavlers
 * Version: 1.0.0
 * Author: Mavlers
 * Author URI: https://mavlers.com
 * Text Domain: mavlers-contact-form
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('MAVLERS_CONTACT_FORM_VERSION', '1.0.0');
define('MAVLERS_CONTACT_FORM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAVLERS_CONTACT_FORM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-builder.php';
require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-database.php';
require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-submissions.php';
require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-analytics.php';
require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'includes/class-mavlers-admin.php';
require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'includes/class-mavlers-form-renderer.php';
require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'includes/class-mavlers-settings.php';
require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'includes/class-mavlers-public.php';

// Initialize the plugin
function mavlers_contact_form_init() {
    // Load text domain
    load_plugin_textdomain('mavlers-contact-form', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialize main classes
    Mavlers_Form_Builder::get_instance();
    Mavlers_Form_Database::get_instance();
    Mavlers_Form_Submissions::get_instance();
    Mavlers_Form_Analytics::get_instance();
    Mavlers_Form_Renderer::get_instance();
    
    // Initialize admin class
    if (is_admin()) {
        Mavlers_Admin::get_instance();
    }

    // Initialize public class
    Mavlers_Public::get_instance();
}

// Hook initialization
add_action('plugins_loaded', 'mavlers_contact_form_init');

// Activation hook
register_activation_hook(__FILE__, 'mavlers_contact_form_activate');
function mavlers_contact_form_activate() {
    // Create database tables
    Mavlers_Form_Database::get_instance()->create_tables();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'mavlers_contact_form_deactivate');
function mavlers_contact_form_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'mavlers_contact_form_uninstall');
function mavlers_contact_form_uninstall() {
    // Clean up database tables
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mavlers_forms");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mavlers_form_fields");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mavlers_form_entries");
}

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mavlers_contact_form_settings_link');
function mavlers_contact_form_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=mavlers-forms') . '">' . __('Settings', 'mavlers-contact-form') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// AJAX handlers for form actions
add_action('wp_ajax_mavlers_delete_form', 'mavlers_delete_form_handler');
add_action('wp_ajax_mavlers_duplicate_form', 'mavlers_duplicate_form_handler');
add_action('wp_ajax_mavlers_save_form', 'mavlers_save_form_handler');
add_action('wp_ajax_mavlers_save_field', 'mavlers_save_field_handler');
add_action('wp_ajax_mavlers_delete_field', 'mavlers_delete_field_handler');
add_action('wp_ajax_mavlers_update_field_order', 'mavlers_update_field_order_handler');

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

function mavlers_save_form_handler() {
    check_ajax_referer('mavlers_form_builder_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    $form_data = array(
        'form_name' => sanitize_text_field($_POST['form_name']),
        'form_fields' => json_decode(stripslashes($_POST['form_fields']), true)
    );

    $form_builder = Mavlers_Form_Builder::get_instance();
    $new_form_id = $form_builder->save_form($form_id, $form_data);

    if ($new_form_id) {
        wp_send_json_success(array('form_id' => $new_form_id));
    } else {
        wp_send_json_error('Failed to save form');
    }
}

function mavlers_save_field_handler() {
    check_ajax_referer('mavlers_form_builder_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $form_id = intval($_POST['form_id']);
    $field_data = json_decode(stripslashes($_POST['field_data']), true);

    $form_builder = Mavlers_Form_Builder::get_instance();
    $field_id = $form_builder->save_field($form_id, $field_data);

    if ($field_id) {
        $field_data['id'] = $field_id;
        wp_send_json_success(array('field_data' => $field_data));
    } else {
        wp_send_json_error('Failed to save field');
    }
}

function mavlers_delete_field_handler() {
    check_ajax_referer('mavlers_form_builder_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $field_id = intval($_POST['field_id']);
    
    $form_builder = Mavlers_Form_Builder::get_instance();
    $result = $form_builder->delete_field($field_id);
    
    if ($result !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete field');
    }
}

function mavlers_update_field_order_handler() {
    check_ajax_referer('mavlers_form_builder_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $form_id = intval($_POST['form_id']);
    $fields = json_decode(stripslashes($_POST['fields']), true);
    
    $form_builder = Mavlers_Form_Builder::get_instance();
    $result = $form_builder->update_field_order($form_id, $fields);
    
    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to update field order');
    }
}
