<?php
if (!defined('WPINC')) {
    die;
}

class Mavlers_CF_Submission_Handler {
    public function __construct() {
        error_log('DEBUG: Mavlers CF Submission Handler constructor called');
        
        add_action('wp_ajax_mavlers_cf_submit', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_mavlers_cf_submit', array($this, 'handle_submission'));
        
        // Add a test AJAX handler
        add_action('wp_ajax_mavlers_cf_test', array($this, 'test_ajax'));
        add_action('wp_ajax_nopriv_mavlers_cf_test', array($this, 'test_ajax'));
        
        error_log('DEBUG: Mavlers CF Submission Handler AJAX actions registered');
    }
    
    public function test_ajax() {
        error_log('DEBUG: Test AJAX handler called');
        wp_send_json_success(array('message' => 'Test AJAX working'));
    }

    public function handle_submission() {
        // Simple test to see if handler is called
        error_log('DEBUG: AJAX handler called - action: mavlers_cf_submit');
        
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Form submission started');
            error_log('DEBUG: POST data: ' . print_r($_POST, true));
        }
        
        // Check if this is an AJAX request
        if (!wp_doing_ajax()) {
            if (current_user_can('manage_options')) {
                error_log('DEBUG: Not an AJAX request');
            }
            wp_die('Direct access not allowed');
        }
        
        if (current_user_can('manage_options')) {
            error_log('DEBUG: AJAX request confirmed');
        }
        
        check_ajax_referer('mavlers_cf_submit', 'nonce');

        // Rate limiting check
        if (!$this->check_rate_limit()) {
            wp_send_json_error(__('Too many submissions. Please wait a moment before trying again.', 'mavlers-contact-forms'));
        }

        // Honeypot validation
        if (!empty($_POST['website'])) {
            wp_send_json_error(__('Invalid submission detected.', 'mavlers-contact-forms'));
        }

        // Timestamp validation (prevent old submissions)
        if (isset($_POST['timestamp']) && (time() - intval($_POST['timestamp'])) > 3600) {
            wp_send_json_error(__('Submission expired. Please refresh the page and try again.', 'mavlers-contact-forms'));
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(__('Invalid form ID', 'mavlers-contact-forms'));
        }

        if (current_user_can('manage_options')) {
            error_log('DEBUG: Form ID: ' . $form_id);
        }

        // Get form data
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error(__('Form not found', 'mavlers-contact-forms'));
        }

        if (current_user_can('manage_options')) {
            error_log('DEBUG: Form found, processing submission');
        }

        // Action hook before validation
        do_action('mavlers_cf_before_validation', $form, $_POST, $_FILES);

        // Validate submission
        $validation = $this->validate_submission($form, $_POST);
        if (is_wp_error($validation)) {
            // Filter validation error message
            $error_message = apply_filters('mavlers_cf_validation_error_message', $validation->get_error_message(), $validation, $form);
            wp_send_json_error($error_message);
        }

        // Action hook after validation
        do_action('mavlers_cf_after_validation', $form, $_POST, $_FILES);

        // Process file uploads
        $files = $this->handle_file_uploads($form, $_FILES);
        if (is_wp_error($files)) {
            wp_send_json_error($files->get_error_message());
        }

        // Filter submission data before saving
        $submission_data = array_merge($_POST, $files);
        $submission_data = apply_filters('mavlers_cf_submission_data', $submission_data, $form);

        // Prepare submission data for database
        $db_submission_data = array(
            'form_id' => $form_id,
            'data' => json_encode($submission_data),
            'created_at' => current_time('mysql')
        );

        // Filter database submission data
        $db_submission_data = apply_filters('mavlers_cf_db_submission_data', $db_submission_data, $submission_data, $form);

        // Action hook before saving submission
        do_action('mavlers_cf_before_save_submission', $db_submission_data, $submission_data, $form);

        // Save submission
        $result = $wpdb->insert(
            $wpdb->prefix . 'mavlers_cf_submissions',
            $db_submission_data,
            array('%d', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to save submission', 'mavlers-contact-forms'));
        }

        $submission_id = $wpdb->insert_id;

        // Process integrations using the new addon system
        $form_data = array(
            'form_id' => $form_id,
            'fields' => $submission_data
        );
        
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Triggering integration processing for submission ID: ' . $submission_id);
            error_log('DEBUG: Form data for integrations: ' . print_r($form_data, true));
        }
        
        // Trigger integration processing through the addon system
        do_action('mavlers_cf_after_submission', $submission_id, $form_data);

        // Send notifications (new email feature)
        $form_settings = json_decode($form->settings, true);
        $email_notifications = isset($form_settings['email_notifications']) ? $form_settings['email_notifications'] : array();
        $fields = json_decode($form->fields, true);

        // Debug logging
        error_log('Mavlers CF: Form settings: ' . print_r($form_settings, true));
        error_log('Mavlers CF: Email notifications: ' . print_r($email_notifications, true));
        error_log('Mavlers CF: Submission data: ' . print_r($submission_data, true));

        // Filter email notifications
        $email_notifications = apply_filters('mavlers_cf_email_notifications', $email_notifications, $form, $submission_data);

        $emails_sent = 0;
        
        // Send configured email notifications
        if (!empty($email_notifications) && is_array($email_notifications)) {
            error_log('Mavlers CF: Processing configured email notifications');
            foreach ($email_notifications as $index => $notif) {
                if (empty($notif['enabled'])) {
                    error_log('Mavlers CF: Email notification ' . $index . ' is disabled');
                    continue;
                }
                
                error_log('Mavlers CF: Processing email notification ' . $index);
                
                // Process email addresses and content
                $to = $this->replace_merge_tags($notif['to'], $fields, $submission_data);
                $from = $this->replace_merge_tags($notif['from'], $fields, $submission_data);
                $subject = $this->replace_merge_tags($notif['subject'], $fields, $submission_data);
                $message = $this->replace_merge_tags($notif['message'], $fields, $submission_data);
                
                // Set default subject and message if empty
                if (empty($subject)) {
                    $subject = 'New form submission from ' . get_bloginfo('name');
                }
                
                if (empty($message)) {
                    $message = $this->format_email_content($form, $submission_data);
                }
                
                // Set default from address if empty
                if (empty($from)) {
                    $from = get_option('admin_email');
                }
                
                error_log('Mavlers CF: Email data - To: ' . $to . ', Subject: ' . $subject . ', Message length: ' . strlen($message));
                
                // Filter email data
                $email_data = apply_filters('mavlers_cf_email_data', array(
                    'to' => $to,
                    'from' => $from,
                    'subject' => $subject,
                    'message' => $message,
                    'notification' => $notif,
                    'form' => $form,
                    'submission' => $submission_data
                ), $index);
                
                $to = $email_data['to'];
                $from = $email_data['from'];
                $subject = $email_data['subject'];
                $message = $email_data['message'];
                
                // Validate email addresses
                if (empty($to) || !$this->is_valid_email_list($to)) {
                    error_log('Mavlers CF: Invalid email address: ' . $to);
                    continue;
                }
                
                // Set up email headers
                $headers = array('Content-Type: text/html; charset=UTF-8');
                
                if (!empty($from)) {
                    $headers[] = 'From: ' . $this->sanitize_email_header($from);
                }
                
                if (!empty($notif['bcc'])) {
                    $bcc = $this->replace_merge_tags($notif['bcc'], $fields, $submission_data);
                    if ($this->is_valid_email_list($bcc)) {
                        $headers[] = 'Bcc: ' . $this->sanitize_email_header($bcc);
                    }
                }
                
                if (!empty($notif['cc'])) {
                    $cc = $this->replace_merge_tags($notif['cc'], $fields, $submission_data);
                    if ($this->is_valid_email_list($cc)) {
                        $headers[] = 'Cc: ' . $this->sanitize_email_header($cc);
                    }
                }
                
                if (!empty($notif['reply_to'])) {
                    $reply_to = $this->replace_merge_tags($notif['reply_to'], $fields, $submission_data);
                    if (is_email($reply_to)) {
                        $headers[] = 'Reply-To: ' . $this->sanitize_email_header($reply_to);
                    }
                }
                
                // Filter email headers
                $headers = apply_filters('mavlers_cf_email_headers', $headers, $email_data, $index);
                
                // Handle attachments
                $attachments = array();
                if (!empty($notif['attachments']) && !empty($files)) {
                    foreach ($files as $file_url) {
                        $file_path = $this->get_file_path_from_url($file_url);
                        if ($file_path && file_exists($file_path)) {
                            $attachments[] = $file_path;
                        }
                    }
                }
                
                // Filter attachments
                $attachments = apply_filters('mavlers_cf_email_attachments', $attachments, $email_data, $index);
                
                // Action hook before sending email
                do_action('mavlers_cf_before_send_email', $email_data, $headers, $attachments, $index);
                
                // Send email with error handling
                try {
                    // Additional validation before sending
                    if (empty($to) || empty($subject) || empty($message)) {
                        error_log('Mavlers CF: Email validation failed - To: ' . $to . ', Subject: ' . $subject . ', Message empty: ' . (empty($message) ? 'yes' : 'no'));
                        continue;
                    }
                    
                    // Ensure headers are properly formatted
                    $final_headers = array();
                    foreach ($headers as $header) {
                        if (!empty($header) && is_string($header)) {
                            $final_headers[] = $header;
                        }
                    }
                    
                    // Add default content type if not present
                    $has_content_type = false;
                    foreach ($final_headers as $header) {
                        if (stripos($header, 'Content-Type:') !== false) {
                            $has_content_type = true;
                            break;
                        }
                    }
                    if (!$has_content_type) {
                        $final_headers[] = 'Content-Type: text/html; charset=UTF-8';
                    }
                    
                    error_log('Mavlers CF: Sending email to: ' . $to . ' with subject: ' . $subject);
                    $email_sent = wp_mail($to, $subject, $message, $final_headers, $attachments);
                    
                    if ($email_sent) {
                        $emails_sent++;
                        error_log('Mavlers CF: Email sent successfully');
                        // Action hook after successful email
                        do_action('mavlers_cf_email_sent_success', $email_data, $index);
                    } else {
                        error_log('Mavlers CF: Email sending failed');
                        // Action hook after failed email
                        do_action('mavlers_cf_email_sent_failed', $email_data, $index);
                    }
                } catch (Exception $e) {
                    error_log('Mavlers CF: Email exception: ' . $e->getMessage());
                    // Action hook for email exception
                    do_action('mavlers_cf_email_exception', $e, $email_data, $index);
                    continue;
                }
            }
        } else {
            error_log('Mavlers CF: No configured email notifications found');
        }
        
        // Always send a default notification to admin if no emails were sent
        if ($emails_sent === 0) {
            error_log('Mavlers CF: Sending fallback email to admin');
            $admin_email = get_option('admin_email');
            $fallback_subject = 'New form submission from ' . get_bloginfo('name');
            $fallback_message = $this->format_email_content($form, $submission_data);
            
            error_log('Mavlers CF: Fallback email - To: ' . $admin_email . ', Subject: ' . $fallback_subject . ', Message length: ' . strlen($fallback_message));
            
            // Filter fallback email data
            $fallback_data = apply_filters('mavlers_cf_fallback_email_data', array(
                'to' => $admin_email,
                'subject' => $fallback_subject,
                'message' => $fallback_message,
                'form' => $form,
                'submission' => $submission_data
            ));
            
            if (is_email($fallback_data['to'])) {
                try {
                    $fallback_sent = wp_mail($fallback_data['to'], $fallback_data['subject'], $fallback_data['message'], array('Content-Type: text/html; charset=UTF-8'));
                    error_log('Mavlers CF: Fallback email sent: ' . ($fallback_sent ? 'success' : 'failed'));
                    do_action('mavlers_cf_fallback_email_sent', $fallback_data);
                } catch (Exception $e) {
                    error_log('Mavlers CF: Fallback email exception: ' . $e->getMessage());
                }
            } else {
                error_log('Mavlers CF: Invalid admin email address: ' . $fallback_data['to']);
            }
        } else {
            error_log('Mavlers CF: ' . $emails_sent . ' emails sent successfully, skipping fallback');
        }

        // Prepare success response
        $form_settings = json_decode($form->settings, true);
        $success_message = !empty($form_settings['success_message']) ? $form_settings['success_message'] : __('Form submitted successfully', 'mavlers-contact-forms');
        
        $response_data = array(
            'message' => $success_message,
            'submission_id' => $submission_id,
            'form_id' => $form_id
        );

        // Filter success response
        $response_data = apply_filters('mavlers_cf_success_response', $response_data, $submission_id, $submission_data, $form);

        // Action hook before sending success response
        do_action('mavlers_cf_before_success_response', $response_data, $submission_id, $submission_data, $form);

        if (current_user_can('manage_options')) {
            error_log('DEBUG: Form submission completed successfully - ID: ' . $submission_id);
            error_log('DEBUG: Sending JSON response: ' . json_encode($response_data));
        }

        wp_send_json_success($response_data);
    }

    private function validate_submission($form, $data) {
        $form_fields = json_decode($form->fields, true);
        $errors = new WP_Error();

        foreach ($form_fields as $field) {
            $field_id = $field['id'];
            $value = isset($data[$field_id]) ? $data[$field_id] : '';

            // Check required fields (exclude captcha fields)
            if (!empty($field['required']) && empty($value) && $field['type'] !== 'captcha') {
                $errors->add('required_field', sprintf(
                    __('%s is required', 'mavlers-contact-forms'),
                    $field['label']
                ));
                continue;
            }

            // Validate field type
            switch ($field['type']) {
                case 'email':
                    if (!empty($value) && !is_email($value)) {
                        $errors->add('invalid_email', sprintf(
                            __('%s is not a valid email address', 'mavlers-contact-forms'),
                            $field['label']
                        ));
                    }
                    break;

                case 'number':
                    if (!empty($value) && !is_numeric($value)) {
                        $errors->add('invalid_number', sprintf(
                            __('%s must be a number', 'mavlers-contact-forms'),
                            $field['label']
                        ));
                    } elseif (!empty($field['min']) && $value < $field['min']) {
                        $errors->add('number_too_small', sprintf(
                            __('%s must be at least %s', 'mavlers-contact-forms'),
                            $field['label'],
                            $field['min']
                        ));
                    } elseif (!empty($field['max']) && $value > $field['max']) {
                        $errors->add('number_too_large', sprintf(
                            __('%s must be at most %s', 'mavlers-contact-forms'),
                            $field['label'],
                            $field['max']
                        ));
                    }
                    break;

                case 'date':
                    if (!empty($value) && !$this->is_valid_date($value, $field['date_format'])) {
                        $errors->add('invalid_date', sprintf(
                            __('%s is not a valid date', 'mavlers-contact-forms'),
                            $field['label']
                        ));
                    }
                    break;

                case 'captcha':
                    // For reCAPTCHA, we need to check the global reCAPTCHA response
                    // The response comes in 'g-recaptcha-response' field, not the field ID
                    $recaptcha_response = isset($data['g-recaptcha-response']) ? $data['g-recaptcha-response'] : '';
                    
                    // Debug logging
                    error_log('Mavlers CF: Captcha field validation - Field ID: ' . $field_id);
                    error_log('Mavlers CF: Captcha field validation - Field required: ' . ($field['required'] ? 'YES' : 'NO'));
                    error_log('Mavlers CF: Captcha field validation - Response received: ' . (!empty($recaptcha_response) ? 'YES' : 'NO'));
                    error_log('Mavlers CF: Captcha field validation - Response length: ' . strlen($recaptcha_response));
                    
                    // Only validate CAPTCHA if the field is required
                    if (!empty($field['required'])) {
                        if (empty($recaptcha_response)) {
                            error_log('Mavlers CF: Captcha validation failed - No response received');
                            $errors->add('captcha_required', __('Please complete the CAPTCHA', 'mavlers-contact-forms'));
                        } else {
                            // Get global reCAPTCHA settings
                            $recaptcha_settings = get_option('mavlers_cf_recaptcha_settings', array());
                            $secret_key = isset($recaptcha_settings['secret_key']) ? $recaptcha_settings['secret_key'] : '';
                            
                            error_log('Mavlers CF: Captcha validation - Global settings: ' . print_r($recaptcha_settings, true));
                            error_log('Mavlers CF: Captcha validation - Secret key length: ' . strlen($secret_key));
                            
                            if (empty($secret_key)) {
                                error_log('Mavlers CF: Captcha validation failed - No secret key configured');
                                $errors->add('captcha_config_error', __('reCAPTCHA is not properly configured', 'mavlers-contact-forms'));
                            } elseif (!$this->verify_captcha($recaptcha_response, $secret_key)) {
                                error_log('Mavlers CF: Captcha validation failed - Verification failed');
                                $errors->add('invalid_captcha', __('CAPTCHA verification failed', 'mavlers-contact-forms'));
                            } else {
                                error_log('Mavlers CF: Captcha validation successful');
                            }
                        }
                    } else {
                        error_log('Mavlers CF: Captcha field not required, skipping validation');
                    }
                    break;
            }
        }

        return $errors->has_errors() ? $errors : true;
    }

    private function handle_file_uploads($form, $files) {
        $uploaded_files = array();
        $form_fields = json_decode($form->fields, true);

        foreach ($form_fields as $field) {
            if ($field['type'] !== 'file_upload') {
                continue;
            }

            $field_id = $field['id'];
            if (!isset($files[$field_id])) {
                continue;
            }

            $file = $files[$field_id];
            
            // Basic file validation
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return new WP_Error('invalid_file', __('Invalid file upload', 'mavlers-contact-forms'));
            }

            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return new WP_Error('upload_error', __('File upload failed', 'mavlers-contact-forms'));
            }

            $allowed_types = !empty($field['allowed_types']) ? explode(',', $field['allowed_types']) : array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx');
            $max_size = isset($field['max_size']) ? $field['max_size'] * 1024 * 1024 : 5 * 1024 * 1024; // Default 5MB

            // Validate file size
            if ($file['size'] > $max_size) {
                return new WP_Error('file_too_large', sprintf(
                    __('File too large for %s. Maximum size: %dMB', 'mavlers-contact-forms'),
                    $field['label'],
                    $max_size / (1024 * 1024)
                ));
            }

            // Validate file type using WordPress function
            $file_type = wp_check_filetype($file['name']);
            if (!$file_type['type'] || !in_array(strtolower($file_type['ext']), array_map('strtolower', $allowed_types))) {
                return new WP_Error('invalid_file_type', sprintf(
                    __('Invalid file type for %s. Allowed types: %s', 'mavlers-contact-forms'),
                    $field['label'],
                    implode(', ', $allowed_types)
                ));
            }

            // Additional security: Check MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            // Define safe MIME types
            $safe_mime_types = array(
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                'application/pdf', 'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            );

            if (!in_array($mime_type, $safe_mime_types)) {
                return new WP_Error('invalid_mime_type', __('File type not allowed for security reasons', 'mavlers-contact-forms'));
            }

            // Additional security: Check file content for executable code
            if ($this->contains_executable_code($file['tmp_name'])) {
                return new WP_Error('executable_content', __('File contains executable content and is not allowed', 'mavlers-contact-forms'));
            }

            // Upload file using WordPress function
            $upload = wp_handle_upload($file, array('test_form' => false));
            if (isset($upload['error'])) {
                return new WP_Error('upload_error', $upload['error']);
            }

            $uploaded_files[$field_id] = $upload['url'];
        }

        return $uploaded_files;
    }

    /**
     * Check if file contains executable code
     */
    private function contains_executable_code($file_path) {
        $content = file_get_contents($file_path, false, null, 0, 8192); // Read first 8KB
        if ($content === false) {
            return true; // Assume malicious if we can't read
        }

        // Check for PHP tags
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return true;
        }

        // Check for other executable patterns
        $executable_patterns = array(
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i'
        );

        foreach ($executable_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    private function send_notifications($form, $submission_data) {
        $form_settings = json_decode($form->settings, true);
        $submission = json_decode($submission_data['data'], true);

        // Send admin notification
        if (!empty($form_settings['admin_email'])) {
            $this->send_email(
                $form_settings['admin_email'],
                sprintf(__('New form submission from %s', 'mavlers-contact-forms'), get_bloginfo('name')),
                $this->format_email_content($form, $submission)
            );
        }

        // Send user notification
        if (!empty($form_settings['user_email_field']) && !empty($submission[$form_settings['user_email_field']])) {
            $this->send_email(
                $submission[$form_settings['user_email_field']],
                $form_settings['user_email_subject'],
                $form_settings['user_email_message']
            );
        }
    }

    private function send_email($to, $subject, $message) {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }

    private function format_email_content($form, $submission) {
        $form_fields = json_decode($form->fields, true);
        $content = '<h2>New Form Submission</h2>';
        $content .= '<p><strong>Form:</strong> ' . esc_html($form->form_title) . '</p>';
        $content .= '<p><strong>Submitted:</strong> ' . current_time('F j, Y g:i a') . '</p>';
        $content .= '<p><strong>IP Address:</strong> ' . $this->get_client_ip() . '</p>';
        $content .= '<hr>';
        $content .= '<h3>Form Data:</h3>';
        $content .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        $content .= '<tr style="background-color: #f8f9fa;"><th style="text-align: left; padding: 12px; border: 1px solid #ddd; font-weight: bold;">Field</th><th style="text-align: left; padding: 12px; border: 1px solid #ddd; font-weight: bold;">Value</th></tr>';
        
        if (!empty($form_fields) && is_array($form_fields)) {
            foreach ($form_fields as $field) {
                $field_id = $field['id'];
                $field_label = $field['label'] ?? $field_id;
                $field_value = isset($submission[$field_id]) ? $submission[$field_id] : '';
                
                // Skip certain field types
                if ($field['type'] === 'html' || $field['type'] === 'hidden' || $field['type'] === 'submit') {
                    continue;
                }
                
                // Format field value
                if (is_array($field_value)) {
                    $field_value = implode(', ', $field_value);
                }
                
                if (empty($field_value)) {
                    $field_value = '<em>No value provided</em>';
                }
                
                $content .= sprintf(
                    '<tr><td style="padding: 12px; border: 1px solid #ddd; font-weight: bold;">%s</td><td style="padding: 12px; border: 1px solid #ddd;">%s</td></tr>',
                    esc_html($field_label),
                    esc_html($field_value)
                );
            }
        } else {
            $content .= '<tr><td colspan="2" style="padding: 12px; border: 1px solid #ddd; text-align: center;">No form fields found</td></tr>';
        }
        
        $content .= '</table>';
        $content .= '<hr>';
        $content .= '<p style="color: #666; font-size: 12px;">This email was sent from your contact form at ' . get_bloginfo('name') . ' (' . get_bloginfo('url') . ')</p>';
        
        return $content;
    }

    private function is_valid_date($date, $format) {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private function verify_captcha($response, $secret_key) {
        // Debug logging
        error_log('Mavlers CF: reCAPTCHA verification started');
        error_log('Mavlers CF: Response length: ' . strlen($response));
        error_log('Mavlers CF: Secret key length: ' . strlen($secret_key));
        
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array(
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        );

        error_log('Mavlers CF: Verification data: ' . print_r($data, true));

        $api_response = wp_remote_post($verify_url, array('body' => $data));
        if (is_wp_error($api_response)) {
            error_log('Mavlers CF: reCAPTCHA verification failed - WP Error: ' . $api_response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($api_response), true);
        error_log('Mavlers CF: reCAPTCHA verification response: ' . print_r($body, true));
        
        $success = isset($body['success']) && $body['success'] === true;
        error_log('Mavlers CF: reCAPTCHA verification result: ' . ($success ? 'SUCCESS' : 'FAILED'));
        
        if (!$success && isset($body['error-codes'])) {
            error_log('Mavlers CF: reCAPTCHA error codes: ' . print_r($body['error-codes'], true));
        }
        
        return $success;
    }

    // Helper: Replace merge tags like {field_label} with submitted values
    private function replace_merge_tags($text, $fields, $submission) {
        if (!$text || !is_string($text)) {
            return '';
        }
        
        // Replace field-specific merge tags
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (!isset($field['label']) || !isset($field['id'])) {
                    continue;
                }
                
                $label = $field['label'];
                $id = $field['id'];
                
                if (isset($submission[$id])) {
                    $value = $submission[$id];
                    if (is_array($value)) {
                        $value = implode(', ', array_filter($value, 'is_string'));
                    } elseif (!is_string($value)) {
                        $value = (string) $value;
                    }
                    
                    // Sanitize the value for email use
                    $value = wp_strip_all_tags($value);
                    
                    $text = str_replace('{' . $label . '}', $value, $text);
                    $text = str_replace('{' . $id . '}', $value, $text);
                }
            }
        }
        
        // Replace system merge tags
        $text = str_replace('{submission_date}', current_time('F j, Y g:i a'), $text);
        $text = str_replace('{ip_address}', $this->get_client_ip(), $text);
        $text = str_replace('{site_name}', get_bloginfo('name'), $text);
        $text = str_replace('{site_url}', get_bloginfo('url'), $text);
        
        return $text;
    }

    // Helper: Get client IP address
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }

    // Helper: Get file path from URL
    private function get_file_path_from_url($url) {
        $upload_dir = wp_upload_dir();
        if (strpos($url, $upload_dir['baseurl']) !== false) {
            return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        }
        return false;
    }

    /**
     * Sanitize email header to prevent header injection
     */
    private function sanitize_email_header($header) {
        // Remove any newlines or carriage returns that could be used for header injection
        $header = str_replace(array("\r", "\n"), '', $header);
        $header = sanitize_email(trim($header));
        
        // Additional validation for email format
        if (!is_email($header)) {
            return '';
        }
        
        return $header;
    }

    /**
     * Validate email list (comma-separated emails)
     */
    private function is_valid_email_list($email_list) {
        if (empty($email_list)) {
            return false;
        }
        
        $emails = array_map('trim', explode(',', $email_list));
        
        foreach ($emails as $email) {
            if (!is_email($email)) {
                return false;
            }
        }
        
        return true;
    }
    
    // Helper: Test email functionality
    public function test_email_functionality() {
        $admin_email = get_option('admin_email');
        $test_subject = 'Mavlers CF: Email Test';
        $test_message = 'This is a test email from Mavlers Contact Forms plugin to verify email functionality.';
        $test_headers = array('Content-Type: text/html; charset=UTF-8');
        
        try {
            $result = wp_mail($admin_email, $test_subject, $test_message, $test_headers);
            if ($result) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Helper: Get email debugging information
    public function get_email_debug_info() {
        $info = array();
        
        // Check WordPress mail settings
        $info['admin_email'] = get_option('admin_email');
        $info['blogname'] = get_option('blogname');
        $info['site_url'] = get_option('siteurl');
        
        // Check if wp_mail function exists
        $info['wp_mail_exists'] = function_exists('wp_mail');
        
        // Check for common email plugins
        $info['wp_mail_smtp_active'] = is_plugin_active('wp-mail-smtp/wp-mail-smtp.php');
        $info['easy_wp_smtp_active'] = is_plugin_active('easy-wp-smtp/easy-wp-smtp.php');
        $info['post_smtp_active'] = is_plugin_active('post-smtp/postman-smtp.php');
        
        return $info;
    }

    /**
     * Check rate limiting for form submissions
     */
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $rate_limit_key = 'mavlers_cf_rate_limit_' . md5($ip);
        $rate_limit_window = 300; // 5 minutes
        $max_submissions = 5; // Max 5 submissions per 5 minutes
        
        $current_time = time();
        $submissions = get_transient($rate_limit_key);
        
        if ($submissions === false) {
            $submissions = array();
        }
        
        // Remove old submissions outside the window
        $submissions = array_filter($submissions, function($timestamp) use ($current_time, $rate_limit_window) {
            return ($current_time - $timestamp) < $rate_limit_window;
        });
        
        // Check if limit exceeded
        if (count($submissions) >= $max_submissions) {
            return false;
        }
        
        // Add current submission
        $submissions[] = $current_time;
        set_transient($rate_limit_key, $submissions, $rate_limit_window);
        
        return true;
    }
} 