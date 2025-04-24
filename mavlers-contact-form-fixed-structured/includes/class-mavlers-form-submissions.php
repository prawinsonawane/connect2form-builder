<?php
/**
 * Form submissions handler for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Form_Submissions {
    private static $instance = null;
    private $db;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db = Mavlers_Form_Database::get_instance();
        
        // Register form submission handler
        add_action('wp_ajax_mavlers_submit_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_mavlers_submit_form', array($this, 'handle_form_submission'));
        
        // Register submission status update handler
        add_action('wp_ajax_mavlers_update_submission_status', array($this, 'handle_submission_status_update'));
    }

    public function handle_form_submission() {
        check_ajax_referer('mavlers_form_submission', 'nonce');

        $form_id = intval($_POST['form_id']);
        $form_data = $_POST['form_data'];

        if (!$form_id || empty($form_data)) {
            wp_send_json_error(__('Invalid form data', 'mavlers-contact-form'));
        }

        // Validate form data
        $validation_result = $this->validate_form_data($form_id, $form_data);
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }

        // Check spam protection
        if ($this->is_spam($form_data)) {
            wp_send_json_error(__('Submission rejected as potential spam', 'mavlers-contact-form'));
        }

        // Save submission
        $submission_id = $this->db->save_submission($form_id, $form_data);
        if (!$submission_id) {
            wp_send_json_error(__('Failed to save submission', 'mavlers-contact-form'));
        }

        // Update analytics
        $this->db->update_analytics($form_id, 'submission');

        // Send email notifications
        $this->send_email_notifications($form_id, $form_data);

        // Trigger action for other plugins
        do_action('mavlers_form_submitted', $form_id, $form_data);

        wp_send_json_success(array(
            'message' => __('Form submitted successfully', 'mavlers-contact-form'),
            'submission_id' => $submission_id
        ));
    }

    public function handle_submission_status_update() {
        check_ajax_referer('mavlers_form_submission', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'mavlers-contact-form'));
        }

        $submission_id = intval($_POST['submission_id']);
        $status = sanitize_text_field($_POST['status']);

        if (!$submission_id || !in_array($status, array('new', 'read', 'replied', 'spam'))) {
            wp_send_json_error(__('Invalid submission data', 'mavlers-contact-form'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_submissions';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $submission_id)
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to update submission status', 'mavlers-contact-form'));
        }

        wp_send_json_success(array(
            'message' => __('Submission status updated successfully', 'mavlers-contact-form')
        ));
    }

    private function validate_form_data($form_id, $data) {
        $form = $this->db->get_form($form_id);
        if (!$form) {
            return new WP_Error('invalid_form', __('Form not found', 'mavlers-contact-form'));
        }

        $fields = json_decode($form->form_fields, true);
        if (!is_array($fields)) {
            return new WP_Error('invalid_fields', __('Invalid form fields', 'mavlers-contact-form'));
        }

        foreach ($fields as $field) {
            if (isset($field['required']) && $field['required'] && empty($data[$field['name']])) {
                return new WP_Error('required_field', sprintf(__('Field "%s" is required', 'mavlers-contact-form'), $field['label']));
            }

            if (!empty($data[$field['name']])) {
                switch ($field['type']) {
                    case 'email':
                        if (!is_email($data[$field['name']])) {
                            return new WP_Error('invalid_email', sprintf(__('Invalid email address in field "%s"', 'mavlers-contact-form'), $field['label']));
                        }
                        break;
                    case 'number':
                        if (!is_numeric($data[$field['name']])) {
                            return new WP_Error('invalid_number', sprintf(__('Invalid number in field "%s"', 'mavlers-contact-form'), $field['label']));
                        }
                        break;
                    case 'url':
                        if (!filter_var($data[$field['name']], FILTER_VALIDATE_URL)) {
                            return new WP_Error('invalid_url', sprintf(__('Invalid URL in field "%s"', 'mavlers-contact-form'), $field['label']));
                        }
                        break;
                }
            }
        }

        return true;
    }

    private function is_spam($data) {
        $settings = get_option('mavlers_form_settings', array());
        
        if (empty($settings['enable_spam_protection'])) {
            return false;
        }

        // Check for common spam patterns
        if (isset($data['website']) && !empty($data['website'])) {
            return true;
        }

        if (isset($data['message']) && preg_match('/http|www|\.com|\.net|\.org/i', $data['message'])) {
            return true;
        }

        // Check submission rate
        if ($this->is_submission_rate_exceeded()) {
            return true;
        }

        // Check reCAPTCHA if enabled
        if (!empty($settings['recaptcha_site_key']) && !empty($settings['recaptcha_secret_key'])) {
            if (!$this->verify_recaptcha()) {
                return true;
            }
        }

        return false;
    }

    private function is_submission_rate_exceeded() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_submissions';
        
        $settings = get_option('mavlers_form_settings', array());
        $max_submissions = isset($settings['max_submissions_per_hour']) ? intval($settings['max_submissions_per_hour']) : 100;
        
        $ip = $this->get_client_ip();
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE ip_address = %s AND created_at > %s",
            $ip,
            $one_hour_ago
        ));

        return $count >= $max_submissions;
    }

    private function verify_recaptcha() {
        $settings = get_option('mavlers_form_settings', array());
        
        if (empty($_POST['g-recaptcha-response'])) {
            return false;
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => $settings['recaptcha_secret_key'],
                'response' => $_POST['g-recaptcha-response'],
                'remoteip' => $this->get_client_ip()
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['success']) && $body['success'] === true;
    }

    private function send_email_notifications($form_id, $form_data) {
        $form = $this->db->get_form($form_id);
        if (!$form) {
            return;
        }

        $email_settings = json_decode($form->email_settings, true);
        if (empty($email_settings) || !$email_settings['notification_enabled']) {
            return;
        }

        $to = $email_settings['recipient_email'];
        $subject = $this->process_email_template($email_settings['custom_subject'], $form_data);
        $message = $this->process_email_template($email_settings['custom_message'], $form_data);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Add CC and BCC
        if (!empty($email_settings['cc_emails'])) {
            $headers[] = 'Cc: ' . implode(',', $email_settings['cc_emails']);
        }
        if (!empty($email_settings['bcc_emails'])) {
            $headers[] = 'Bcc: ' . implode(',', $email_settings['bcc_emails']);
        }

        // Add Reply-To header if specified
        if (!empty($email_settings['reply_to_field']) && isset($form_data[$email_settings['reply_to_field']])) {
            $headers[] = 'Reply-To: ' . $form_data[$email_settings['reply_to_field']];
        }

        wp_mail($to, $subject, $message, $headers);
    }

    private function process_email_template($template, $form_data) {
        $replacements = array(
            '{form_title}' => get_the_title($form_data['form_id']),
            '{form_data}' => $this->format_form_data($form_data),
            '{submission_date}' => current_time('Y-m-d'),
            '{submission_time}' => current_time('H:i:s'),
            '{ip_address}' => $this->get_client_ip(),
            '{user_agent}' => $_SERVER['HTTP_USER_AGENT']
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function format_form_data($form_data) {
        $output = '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($form_data as $key => $value) {
            if ($key === 'form_id') continue;
            $output .= sprintf(
                '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>',
                esc_html($key),
                esc_html($value)
            );
        }
        $output .= '</table>';
        return $output;
    }

    private function get_client_ip() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
} 