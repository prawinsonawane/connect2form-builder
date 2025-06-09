<?php
/**
 * Settings handler for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Settings {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_post_mavlers_save_form_settings', array($this, 'save_form_settings'));
    }

    public function add_settings_page() {
        // Add main menu page if it doesn't exist
        if (!menu_page_url('mavlers-forms', false)) {
            add_menu_page(
                __('Mavlers Forms', 'mavlers-contact-form'),
                __('Mavlers Forms', 'mavlers-contact-form'),
                'manage_options',
                'mavlers-forms',
                array($this, 'render_forms_page'),
                'dashicons-feedback',
                30
            );
        }

        // Add submenu items
        add_submenu_page(
            'mavlers-forms',
            __('All Forms', 'mavlers-contact-form'),
            __('All Forms', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms',
            array($this, 'render_forms_page')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Add New', 'mavlers-contact-form'),
            __('Add New', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms&action=new',
            array($this, 'render_form_builder')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Submissions', 'mavlers-contact-form'),
            __('Submissions', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms-submissions',
            array($this, 'render_submissions_page')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Analytics', 'mavlers-contact-form'),
            __('Analytics', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms-analytics',
            array($this, 'render_analytics_page')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Settings', 'mavlers-contact-form'),
            __('Settings', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_forms_page() {
        // If we're on the settings action, render settings page
        if (isset($_GET['action']) && $_GET['action'] === 'settings') {
            $this->render_settings_page();
            return;
        }

        // If we're on the new form action or edit action, render form builder
        if (isset($_GET['action']) && ($_GET['action'] === 'new' || $_GET['action'] === 'edit')) {
            $this->render_form_builder();
            return;
        }

        // Otherwise render the forms list page
        require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'admin/views/forms-list.php';
    }

    public function render_form_builder() {
        require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'admin/views/form-builder.php';
    }

    public function render_submissions_page() {
        require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'admin/views/submissions-list.php';
    }

    public function render_analytics_page() {
        require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'admin/views/analytics.php';
    }

    public function render_settings_page() {
        // Check if we're on the settings action
        if (!isset($_GET['action']) || $_GET['action'] !== 'settings') {
            return;
        }

        // Get form ID from URL
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        
        if (!$form_id) {
            wp_redirect(admin_url('admin.php?page=mavlers-forms'));
            exit;
        }

        // Get form data
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$forms_table} WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_redirect(admin_url('admin.php?page=mavlers-forms'));
            exit;
        }

        // Include the settings template
        require_once MAVLERS_CONTACT_FORM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function save_form_settings() {
        // Verify nonce
        if (!isset($_POST['mavlers_settings_nonce']) || 
            !wp_verify_nonce($_POST['mavlers_settings_nonce'], 'mavlers_form_settings_' . $_POST['form_id'])) {
            wp_die(__('Security check failed', 'mavlers-contact-form'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mavlers-contact-form'));
        }

        // Get form ID
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_redirect(admin_url('admin.php?page=mavlers-forms'));
            exit;
        }

        // Get current form data
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$forms_table} WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_redirect(admin_url('admin.php?page=mavlers-forms'));
            exit;
        }

        // Get current settings
        $current_settings = json_decode($form->form_settings, true) ?: array();

        // Update settings
        $settings = array(
            'form_title' => sanitize_text_field($_POST['form_title']),
            'form_description' => wp_kses_post($_POST['form_description']),
            'success_message' => wp_kses_post($_POST['success_message']),
            'error_message' => wp_kses_post($_POST['error_message']),
            'email_to' => sanitize_email($_POST['email_to']),
            'email_from' => sanitize_email($_POST['email_from']),
            'email_from_name' => sanitize_text_field($_POST['email_from_name']),
            'email_subject' => sanitize_text_field($_POST['email_subject']),
            'email_content' => wp_kses_post($_POST['email_content']),
            'enable_email' => isset($_POST['enable_email'])
        );

        // Update form settings
        $wpdb->update(
            $forms_table,
            array('form_settings' => json_encode($settings)),
            array('id' => $form_id),
            array('%s'),
            array('%d')
        );

        // Redirect back to settings page with success message
        wp_redirect(add_query_arg(
            array(
                'page' => 'mavlers-forms',
                'action' => 'settings',
                'form_id' => $form_id,
                'settings-updated' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function get_form_settings($form_id) {
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT form_settings FROM {$forms_table} WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            return array();
        }

        $settings = json_decode($form->form_settings, true) ?: array();

        // Set defaults if not set
        $defaults = array(
            'form_title' => '',
            'form_description' => '',
            'success_message' => __('Thank you for your submission!', 'mavlers-contact-form'),
            'error_message' => __('There was an error submitting the form. Please try again.', 'mavlers-contact-form'),
            'email_to' => get_option('admin_email'),
            'email_from' => get_option('admin_email'),
            'email_from_name' => get_bloginfo('name'),
            'email_subject' => __('New Form Submission', 'mavlers-contact-form'),
            'email_content' => '',
            'enable_email' => false
        );

        return wp_parse_args($settings, $defaults);
    }
}

// Initialize the settings handler
Mavlers_Settings::get_instance(); 