<?php
/**
 * Form submission handler for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Form_Submission {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_mavlers_form_submit', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_mavlers_form_submit', array($this, 'handle_submission'));
    }

    public function handle_submission() {
        check_ajax_referer('mavlers_form_submit', 'mavlers_form_nonce');

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(array('message' => 'Invalid form ID'));
        }

        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
        $submissions_table = $wpdb->prefix . 'mavlers_form_submissions';

        // Get form
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$forms_table} WHERE id = %d AND status = 'active'",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error(array('message' => 'Form not found'));
        }

        // Get form fields
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$fields_table} WHERE form_id = %d ORDER BY field_order ASC",
            $form_id
        ));

        if (empty($fields)) {
            wp_send_json_error(array('message' => 'No fields found'));
        }

        // Validate submission
        $validation_result = $this->validate_submission($fields);
        if (is_wp_error($validation_result)) {
            wp_send_json_error(array('message' => $validation_result->get_error_message()));
        }

        // Process submission
        $submission_data = array();
        $uploaded_files = array();

        foreach ($fields as $field) {
            $field_value = $this->get_field_value($field);
            
            // Handle file upload
            if ($field->field_type === 'file' && !empty($_FILES['field_' . $field->id])) {
                $upload_result = $this->handle_file_upload($field, $_FILES['field_' . $field->id]);
                if (is_wp_error($upload_result)) {
                    wp_send_json_error(array('message' => $upload_result->get_error_message()));
                }
                $field_value = $upload_result;
                $uploaded_files[] = $upload_result;
            }

            $submission_data[$field->field_name] = $field_value;
        }

        // Save submission
        $submission_id = $wpdb->insert(
            $submissions_table,
            array(
                'form_id' => $form_id,
                'submission_data' => json_encode($submission_data),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if (!$submission_id) {
            // Clean up uploaded files if submission fails
            foreach ($uploaded_files as $file) {
                @unlink($file);
            }
            wp_send_json_error(array('message' => 'Failed to save submission'));
        }

        // Send notifications
        $this->send_notifications($form, $submission_data);

        wp_send_json_success(array(
            'message' => 'Form submitted successfully',
            'submission_id' => $submission_id
        ));
    }

    private function validate_submission($fields) {
        foreach ($fields as $field) {
            // Skip validation for hidden fields
            if ($field->field_type === 'hidden') {
                continue;
            }

            // Required field validation
            if ($field->field_required && empty($_POST['field_' . $field->id])) {
                return new WP_Error('required_field', sprintf(
                    'The field "%s" is required',
                    $field->field_label
                ));
            }

            // Field type specific validation
            switch ($field->field_type) {
                case 'email':
                    if (!empty($_POST['field_' . $field->id]) && !is_email($_POST['field_' . $field->id])) {
                        return new WP_Error('invalid_email', sprintf(
                            'The field "%s" must be a valid email address',
                            $field->field_label
                        ));
                    }
                    break;

                case 'number':
                    if (!empty($_POST['field_' . $field->id])) {
                        $value = floatval($_POST['field_' . $field->id]);
                        if ($field->field_min_value && $value < floatval($field->field_min_value)) {
                            return new WP_Error('invalid_number', sprintf(
                                'The field "%s" must be greater than or equal to %s',
                                $field->field_label,
                                $field->field_min_value
                            ));
                        }
                        if ($field->field_max_value && $value > floatval($field->field_max_value)) {
                            return new WP_Error('invalid_number', sprintf(
                                'The field "%s" must be less than or equal to %s',
                                $field->field_label,
                                $field->field_max_value
                            ));
                        }
                    }
                    break;

                case 'file':
                    if ($field->field_required && empty($_FILES['field_' . $field->id]['name'])) {
                        return new WP_Error('required_file', sprintf(
                            'The field "%s" is required',
                            $field->field_label
                        ));
                    }
                    break;

                case 'captcha':
                    if ($field->field_content === 'recaptcha') {
                        $recaptcha_response = $_POST['g-recaptcha-response'];
                        if (!$this->verify_recaptcha($recaptcha_response)) {
                            return new WP_Error('invalid_captcha', 'Please complete the reCAPTCHA verification');
                        }
                    } else {
                        $captcha_sum = intval($_POST['captcha_sum']);
                        $captcha_answer = intval($_POST['captcha_answer']);
                        if ($captcha_answer !== $captcha_sum) {
                            return new WP_Error('invalid_captcha', 'Incorrect answer to the math problem');
                        }
                    }
                    break;
            }
        }

        return true;
    }

    private function get_field_value($field) {
        $field_name = 'field_' . $field->id;
        
        switch ($field->field_type) {
            case 'checkbox':
                return isset($_POST[$field_name]) ? $_POST[$field_name] : array();
            
            case 'file':
                return isset($_FILES[$field_name]['name']) ? $_FILES[$field_name]['name'] : '';
            
            default:
                return isset($_POST[$field_name]) ? sanitize_text_field($_POST[$field_name]) : '';
        }
    }

    private function handle_file_upload($field, $file) {
        if (empty($file['name'])) {
            return '';
        }

        // Check file size
        if ($field->field_max_size && $file['size'] > $field->field_max_size) {
            return new WP_Error('file_too_large', sprintf(
                'The file "%s" is too large. Maximum size is %s',
                $file['name'],
                size_format($field->field_max_size)
            ));
        }

        // Check file type
        if ($field->field_allowed_types) {
            $allowed_types = explode(',', $field->field_allowed_types);
            $file_type = wp_check_filetype($file['name']);
            
            if (!in_array($file_type['type'], $allowed_types)) {
                return new WP_Error('invalid_file_type', sprintf(
                    'The file "%s" is not allowed. Allowed types are: %s',
                    $file['name'],
                    implode(', ', $allowed_types)
                ));
            }
        }

        // Create upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $form_upload_dir = $upload_dir['basedir'] . '/mavlers-forms/' . $field->form_id;
        
        if (!file_exists($form_upload_dir)) {
            wp_mkdir_p($form_upload_dir);
        }

        // Generate unique filename
        $filename = wp_unique_filename($form_upload_dir, $file['name']);
        $filepath = $form_upload_dir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('upload_failed', 'Failed to upload file');
        }

        return $filepath;
    }

    private function send_notifications($form, $submission_data) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        // Prepare email content
        $subject = sprintf('[%s] New Form Submission - %s', $site_name, $form->form_name);
        $message = "A new form submission has been received:\n\n";

        foreach ($submission_data as $field_name => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $message .= sprintf("%s: %s\n", ucfirst($field_name), $value);
        }

        $message .= sprintf("\n\nIP Address: %s\nUser Agent: %s", $this->get_client_ip(), $_SERVER['HTTP_USER_AGENT']);

        // Send email
        wp_mail($admin_email, $subject, $message);
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

    private function verify_recaptcha($response) {
        $secret_key = get_option('mavlers_recaptcha_secret_key');
        if (!$secret_key) {
            return false;
        }

        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $this->get_client_ip()
        );

        $response = wp_remote_post($verify_url, array('body' => $data));
        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        return isset($result['success']) && $result['success'];
    }
}

// Initialize the form submission handler
Mavlers_Form_Submission::get_instance(); 