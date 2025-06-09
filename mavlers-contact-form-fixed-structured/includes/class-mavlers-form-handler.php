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
        check_ajax_referer('mavlers_form_submission', 'nonce');

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error('Invalid form ID');
        }

        // Start session if not already started
        if (!session_id()) {
            session_start();
        }

        // Validate captcha if present
        if (isset($_POST['captcha_answer'])) {
            $user_answer = intval($_POST['captcha_answer']);
            $correct_answer = isset($_SESSION['mavlers_captcha_answer']) ? intval($_SESSION['mavlers_captcha_answer']) : 0;
            
            if ($user_answer !== $correct_answer) {
                wp_send_json_error('Invalid captcha answer');
            }
            
            // Clear the captcha answer from session
            unset($_SESSION['mavlers_captcha_answer']);
        }

        // Get form data
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$forms_table} WHERE id = %d AND status = 'active'",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error('Form not found');
        }

        // Process form submission
        $form_fields = json_decode($form->form_fields, true);
        if (!is_array($form_fields)) {
            wp_send_json_error('Invalid form fields');
        }

        // Validate required fields
        foreach ($form_fields as $field) {
            if (!empty($field['field_required']) && empty($_POST[$field['field_name']])) {
                wp_send_json_error(sprintf('Field "%s" is required', $field['field_label']));
            }
        }

        // Save submission
        $submissions_table = $wpdb->prefix . 'mavlers_form_submissions';
        $submission_data = array(
            'form_id' => $form_id,
            'submission_data' => json_encode($_POST),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($submissions_table, $submission_data);
        if ($result === false) {
            wp_send_json_error('Failed to save submission');
        }

        // Send email notification if configured
        if (!empty($form->notification_email)) {
            $this->send_notification_email($form, $_POST);
        }

        wp_send_json_success('Form submitted successfully');
    }

    private function send_notification_email($form, $submission_data) {
        $settings = Mavlers_Settings::get_instance()->get_form_settings($form->id);
        $admin_email = $settings['admin_email'];
        $from_name = $settings['from_name'];
        $from_email = $settings['from_email'];
        $email_format = $settings['email_format'];
        $email_header = $settings['email_header'];
        $email_footer = $settings['email_footer'];

        $subject = sprintf(__('New form submission from %s', 'mavlers-contact-form'), get_bloginfo('name'));
        
        // Build email message
        $message = '';
        if ($email_format === 'html') {
            $message .= '<!DOCTYPE html><html><body>';
            $message .= wpautop($email_header);
            $message .= '<h2>' . __('New form submission details:', 'mavlers-contact-form') . '</h2>';
            $message .= '<table style="width: 100%; border-collapse: collapse;">';
            foreach ($submission_data as $label => $value) {
                if ($label !== 'form_id' && $label !== 'nonce' && $label !== 'captcha_answer') {
                    $message .= sprintf(
                        '<tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">%s</th>' .
                        '<td style="padding: 8px; border-bottom: 1px solid #ddd;">%s</td></tr>',
                        esc_html($label),
                        esc_html($value)
                    );
                }
            }
            $message .= '</table>';
            $message .= wpautop($email_footer);
            $message .= '</body></html>';
        } else {
            $message .= $email_header . "\n\n";
            $message .= __('New form submission details:', 'mavlers-contact-form') . "\n\n";
            foreach ($submission_data as $label => $value) {
                if ($label !== 'form_id' && $label !== 'nonce' && $label !== 'captcha_answer') {
                    $message .= sprintf("%s: %s\n", $label, $value);
                }
            }
            $message .= "\n" . $email_footer;
        }

        $headers = array();
        if ($email_format === 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

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

    private function send_auto_responder($form, $submission_data) {
        $settings = Mavlers_Settings::get_instance()->get_form_settings($form->id);
        
        // Check if auto-responder is enabled for this form
        if (!$settings['enable_auto_responder']) {
            return;
        }

        $from_name = $settings['from_name'];
        $from_email = $settings['from_email'];
        $email_format = $settings['email_format'];
        $email_header = $settings['email_header'];
        $email_footer = $settings['email_footer'];
        $subject = $settings['auto_responder_subject'];
        $content = $settings['auto_responder_content'];

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

        // Replace placeholders
        $content = str_replace('{name}', $submission_data['Name'] ?? '', $content);
        $content = str_replace('{site_name}', get_bloginfo('name'), $content);

        // Build email message
        $message = '';
        if ($email_format === 'html') {
            $message .= '<!DOCTYPE html><html><body>';
            $message .= wpautop($email_header);
            $message .= wpautop($content);
            $message .= wpautop($email_footer);
            $message .= '</body></html>';
        } else {
            $message .= $email_header . "\n\n";
            $message .= $content . "\n\n";
            $message .= $email_footer;
        }

        $headers = array();
        if ($email_format === 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

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
