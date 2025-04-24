<?php
/**
 * Form submission handling for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Form_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'handle_form_submission'));
        add_action('mavlers_cleanup_logs', array($this, 'cleanup_old_logs'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('mavlers_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'mavlers_cleanup_logs');
        }
    }

    public function handle_form_submission() {
        if (!isset($_POST['mavlers_form_submit']) || !isset($_POST['form_id'])) {
            return;
        }

        $form_id = intval($_POST['form_id']);
        if (!$form_id) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['mavlers_form_nonce']) || !wp_verify_nonce($_POST['mavlers_form_nonce'], 'mavlers_form_' . $form_id)) {
            wp_die(__('Invalid form submission', 'mavlers-contact-form'));
        }

        // Get form data
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_forms';
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_die(__('Form not found', 'mavlers-contact-form'));
        }

        $form_fields = json_decode($form->form_fields, true);
        $submission_data = array();
        $errors = array();

        // Validate and sanitize form data
        foreach ($form_fields as $field) {
            $field_name = sanitize_title($field['label']);
            $field_value = isset($_POST[$field_name]) ? $_POST[$field_name] : '';

            // Basic validation
            if ($field['required'] && empty($field_value)) {
                $errors[] = sprintf(__('%s is required', 'mavlers-contact-form'), $field['label']);
                continue;
            }

            // Type-specific validation
            switch ($field['type']) {
                case 'email':
                    if (!empty($field_value) && !is_email($field_value)) {
                        $errors[] = sprintf(__('%s must be a valid email address', 'mavlers-contact-form'), $field['label']);
                    }
                    break;
            }

            $submission_data[$field['label']] = sanitize_text_field($field_value);
        }

        // If there are errors, display them
        if (!empty($errors)) {
            set_transient('mavlers_form_errors_' . $form_id, $errors, 45);
            return;
        }

        // Save the form entry
        $db = Mavlers_Database::get_instance();
        $db->save_form_entry($form_id, $submission_data);

        // Send notifications
        $this->send_notification_email($form_id, $submission_data);
        $this->send_auto_responder($submission_data);

        // Set success message
        set_transient('mavlers_form_success_' . $form_id, true, 45);

        // Redirect back to the form
        wp_safe_redirect(add_query_arg('submitted', '1', wp_get_referer()));
        exit;
    }

    private function send_notification_email($form_id, $submission_data) {
        $admin_email = get_option('mavlers_admin_email', get_option('admin_email'));
        $site_name = get_bloginfo('name');

        // Get email templates
        $header = get_option('mavlers_email_header', '');
        $footer = get_option('mavlers_email_footer', '');

        $subject = sprintf(__('New form submission from %s', 'mavlers-contact-form'), $site_name);
        
        $message = $header . "\n\n";
        $message .= __('New form submission details:', 'mavlers-contact-form') . "\n\n";
        foreach ($submission_data as $label => $value) {
            $message .= sprintf("%s: %s\n", $label, $value);
        }
        $message .= "\n" . $footer;

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>'
        );

        $sent = wp_mail($admin_email, $subject, $message, $headers);

        // Log the email
        $db = Mavlers_Database::get_instance();
        $db->log_email(
            $admin_email,
            $subject,
            $message,
            $sent ? 'sent' : 'failed'
        );
    }

    private function send_auto_responder($submission_data) {
        // Find email field
        $user_email = '';
        foreach ($submission_data as $label => $value) {
            if (is_email($value)) {
                $user_email = $value;
                break;
            }
        }

        if (empty($user_email)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = get_option('mavlers_auto_responder_subject', 'Thank you for contacting us');
        $content = get_option('mavlers_auto_responder_content', '');

        // Replace placeholders
        $content = str_replace('{name}', $submission_data['Name'] ?? '', $content);
        $content = str_replace('{site_name}', $site_name, $content);

        // Get email templates
        $header = get_option('mavlers_email_header', '');
        $footer = get_option('mavlers_email_footer', '');

        $message = $header . "\n\n" . $content . "\n\n" . $footer;

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
        );

        $sent = wp_mail($user_email, $subject, $message, $headers);

        // Log the email
        $db = Mavlers_Database::get_instance();
        $db->log_email(
            $user_email,
            $subject,
            $message,
            $sent ? 'sent' : 'failed'
        );
    }

    public function cleanup_old_logs() {
        Mavlers_Database::cleanup_old_logs();
    }
}

// Initialize the form handler
Mavlers_Form_Handler::get_instance();
