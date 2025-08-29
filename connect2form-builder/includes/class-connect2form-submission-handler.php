<?php
/**
 * Connect2Form Submission Handler Class
 *
 * Handles form submission processing, validation, and email notifications
 *
 * @package Connect2Form
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Connect2Form_Submission_Handler {
    public function __construct() {
        add_action('wp_ajax_connect2form_submit', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_connect2form_submit', array($this, 'handle_submission'));
        add_action('wp_ajax_connect2form_clear_rate_limit', array($this, 'clear_rate_limit'));
    }

    public function handle_submission() {
        check_ajax_referer('connect2form_submit', 'nonce');

        // Rate limiting check
        if (!$this->check_rate_limit()) {
            wp_send_json_error($this->get_translated_message('Too many submissions. Please wait a moment before trying again.'));
        }

        // Honeypot validation
        if (!empty($_POST['website'])) {
            wp_send_json_error($this->get_translated_message('Invalid submission detected.'));
        }

        // Timestamp validation (prevent old submissions) - increased to 24 hours for better user experience
        if (isset($_POST['timestamp']) && !empty($_POST['timestamp'])) {
            $submission_time = absint( wp_unslash( $_POST['timestamp'] ) );
            $current_time = time();
            $time_diff = $current_time - $submission_time;
            
            if ($time_diff > 86400) {
                wp_send_json_error($this->get_translated_message('Submission expired. Please refresh the page and try again.'));
            }
        }

        $form_id = isset($_POST['form_id']) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
        if (!$form_id) {
            wp_send_json_error($this->get_translated_message('Invalid form ID'));
        }

        // Get form data using service class
        $form = null;
        if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $form = $service_manager->forms()->get_form($form_id);
        } else {
            // Fallback to direct database call if service not available
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form query for submission processing; service layer preferred but this is a fallback
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
                $form_id
            ));
        }

        if (!$form) {
            wp_send_json_error($this->get_translated_message('Form not found'));
        }

        // Ensure form object has required properties
        if (!isset($form->settings)) {
            $form->settings = '';
        }
        if (!isset($form->fields)) {
            $form->fields = '';
        }

        // Action hook before validation
        do_action('connect2form_before_validation', $form, $_POST, $_FILES);

        // Validate submission
        $validation = $this->validate_submission($form, $_POST);
        if (is_wp_error($validation)) {
            // Filter validation error message
            $error_message = apply_filters('connect2form_validation_error_message', $validation->get_error_message(), $validation, $form);
            wp_send_json_error($error_message);
        }

        // Action hook after validation
        do_action('connect2form_after_validation', $form, $_POST, $_FILES);

        // Process file uploads
        $files = $this->handle_file_uploads($form, $_FILES);
        if (is_wp_error($files)) {
            wp_send_json_error($files->get_error_message());
        }

        // Filter submission data before saving
        $submission_data = array_merge($_POST, $files);
        $submission_data = apply_filters('connect2form_submission_data', $submission_data, $form);

        // Prepare submission data for database
        $db_submission_data = array(
            'form_id' => $form_id,
            'data' => $submission_data, // Pass raw data for SubmissionService to encode
            'created_at' => current_time('mysql')
        );

        // Filter database submission data
        $db_submission_data = apply_filters('connect2form_db_submission_data', $db_submission_data, $submission_data, $form);

        // Action hook before saving submission
        do_action('connect2form_before_save_submission', $db_submission_data, $submission_data, $form);

        // Use direct database insertion due to schema mismatch with ServiceManager
        // The current database schema only has basic columns (id, form_id, data, created_at)
        // but ServiceManager expects extended schema with ip_address, user_agent, utm_data
        
        $submission_id = false;
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'connect2form_submissions';
        
        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for submission processing; no caching needed for INFORMATION_SCHEMA queries
        $table_exists = $wpdb->get_var($wpdb->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $submissions_table)) === $submissions_table;
        
        if (!$table_exists) {
            wp_send_json_error($this->get_translated_message('Database table not found. Please reactivate the plugin.'));
        }

        // Save submission directly with proper data encoding
        $insert_data = array(
            'form_id' => $form_id,
            'data' => wp_json_encode($submission_data),
            'created_at' => current_time('mysql')
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- submission save operation; no caching needed for form submission
        $result = $wpdb->insert(
            $submissions_table,
            $insert_data,
            array('%d', '%s', '%s')
        );
        
        $submission_id = $result ? $wpdb->insert_id : false;
        


        if ($submission_id === false) {
            wp_send_json_error($this->get_translated_message('Failed to save submission'));
        }

        // Action hook after saving submission
        do_action('connect2form_after_save_submission', $submission_id, $db_submission_data, $submission_data, $form);

        // Process legacy integrations (basic Mailchimp/HubSpot)
        if (class_exists('Connect2Form_Integrations')) {
            try {
                $integrations = new Connect2Form_Integrations();
                $form_data = array(
                    'form_id' => $form_id,
                    'fields' => $submission_data
                );
                $integrations->process_integrations($submission_id, $form_data, $form);
            } catch (Exception $e) {
                // Continue with form processing even if legacy integration fails
            }
        }

        // Trigger integrations plugin hook (if Connect2Form Integrations plugin is active)
        try {
            $addon_form_data = array(
                'form_id' => $form_id,
                'fields' => $submission_data
            );
            do_action('connect2form_after_submission', $submission_id, $addon_form_data);
        } catch (Exception $e) {
            // Continue with form processing even if integrations plugin fails
        }

        // Send notifications (new email feature)
        $form_settings = !empty($form->settings) ? (is_string($form->settings) ? json_decode($form->settings, true) : (is_array($form->settings) ? $form->settings : array())) : array();
        $form_settings = is_array($form_settings) ? $form_settings : array();
        



        // Email notifications (support for multiple notifications)
        if (!empty($form_settings['email_notifications']) && is_array($form_settings['email_notifications'])) {
            foreach ($form_settings['email_notifications'] as $index => $notification) {
                if (!empty($notification['enabled']) && !empty($notification['to'])) {
                    $this->send_email_notification($form, $submission_data, $notification);
                }
            }
        }
        // Fallback for old single notification system (backward compatibility)
        elseif (!empty($form_settings['email_notifications']) && !empty($form_settings['notification_email'])) {
            // Convert old format to new format
            $legacy_notification = array(
                'enabled' => true,
                'to' => $form_settings['notification_email'],
                'from' => isset($form_settings['from_email']) ? $form_settings['from_email'] : '',
                'subject' => isset($form_settings['notification_subject']) ? $form_settings['notification_subject'] : '',
                'message' => isset($form_settings['notification_message']) ? $form_settings['notification_message'] : ''
            );
            $this->send_email_notification($form, $submission_data, $legacy_notification);
        }

        // Auto-responder
        if (!empty($form_settings['auto_responder']) && !empty($submission_data['email'])) {
            $this->send_auto_responder($form, $submission_data, $form_settings);
        }

        // Action hook after all processing is complete
        do_action('connect2form_after_complete_submission', $submission_id, $submission_data, $form);

        // Success response
        $success_message = !empty($form_settings['success_message']) 
            ? $form_settings['success_message'] 
            : $this->get_translated_message('Form submitted successfully!');

        wp_send_json_success(array(
            'message' => $success_message,
            'submission_id' => $submission_id
        ));
    }

    /**
     * Validate form submission
     */
    private function validate_submission($form, $data) {
        if (empty($form->fields)) {
            return new WP_Error('no_fields', $this->get_translated_message('No form fields found'));
        }

        $fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
        if (!is_array($fields)) {
            return new WP_Error('invalid_fields', $this->get_translated_message('Invalid form configuration'));
        }

        foreach ($fields as $field) {
            if (empty($field['name'])) {
                continue;
            }

            $field_name = sanitize_key($field['name']);
            $field_value = isset($data[$field_name]) ? $data[$field_name] : '';

            // Required field validation
            if (!empty($field['required']) && empty($field_value)) {
                return new WP_Error(
                    'required_field',
                    sprintf(
                        $this->get_translated_message('The field "%s" is required.'),
                        esc_html($field['label'] ?? $field_name)
                    )
                );
            }

            // Email validation
            if ($field['type'] === 'email' && !empty($field_value) && !is_email($field_value)) {
                return new WP_Error(
                    'invalid_email',
                    sprintf(
                        $this->get_translated_message('Please enter a valid email address for "%s".'),
                        esc_html($field['label'] ?? $field_name)
                    )
                );
            }

            // URL validation
            if ($field['type'] === 'url' && !empty($field_value) && !filter_var($field_value, FILTER_VALIDATE_URL)) {
                return new WP_Error(
                    'invalid_url',
                    sprintf(
                        $this->get_translated_message('Please enter a valid URL for "%s".'),
                        esc_html($field['label'] ?? $field_name)
                    )
                );
            }

            // Number validation
            if ($field['type'] === 'number' && !empty($field_value) && !is_numeric($field_value)) {
                return new WP_Error(
                    'invalid_number',
                    sprintf(
                        $this->get_translated_message('Please enter a valid number for "%s".'),
                        esc_html($field['label'] ?? $field_name)
                    )
                );
            }

            // Date validation
            if ($field['type'] === 'date' && !empty($field_value)) {
                $date = DateTime::createFromFormat('Y-m-d', $field_value);
                if (!$date || $date->format('Y-m-d') !== $field_value) {
                    return new WP_Error(
                        'invalid_date',
                        sprintf(
                            $this->get_translated_message('Please enter a valid date for "%s".'),
                            esc_html($field['label'] ?? $field_name)
                        )
                    );
                }
            }

            // Apply custom validation filters
            $validation_result = apply_filters('connect2form_validate_field', true, $field, $field_value, $data);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }
        }

        // reCAPTCHA validation
        if ($this->has_recaptcha_field($fields)) {
            $recaptcha_validation = $this->validate_recaptcha($data);
            if (is_wp_error($recaptcha_validation)) {
                return $recaptcha_validation;
            }
        }

        return true;
    }

    /**
     * Check if form has reCAPTCHA field
     */
    private function has_recaptcha_field($fields) {
        foreach ($fields as $field) {
            if (!empty($field['type']) && $field['type'] === 'recaptcha') {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate reCAPTCHA
     */
    private function validate_recaptcha($data) {
        if (empty($data['g-recaptcha-response'])) {
            return new WP_Error('recaptcha_required', $this->get_translated_message('Please complete the CAPTCHA verification.'));
        }

        $recaptcha_settings = get_option('connect2form_recaptcha_settings', array());
        if (empty($recaptcha_settings['secret_key'])) {
            return new WP_Error('recaptcha_not_configured', $this->get_translated_message('reCAPTCHA is not properly configured.'));
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => $recaptcha_settings['secret_key'],
                'response' => sanitize_text_field($data['g-recaptcha-response']),
                'remoteip' => $this->get_client_ip()
            ),
            'timeout' => 10,
            'user-agent' => 'Connect2Form/' . CONNECT2FORM_VERSION
        ));

        if (is_wp_error($response)) {
            return new WP_Error('recaptcha_request_failed', $this->get_translated_message('CAPTCHA verification failed. Please try again.'));
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!$result || !$result['success']) {
            return new WP_Error('recaptcha_verification_failed', $this->get_translated_message('CAPTCHA verification failed. Please try again.'));
        }

        return true;
    }

    /**
     * Handle file uploads
     */
    private function handle_file_uploads($form, $files) {
        if (empty($files)) {
            return array();
        }

        $uploaded_files = array();
        $upload_dir = wp_upload_dir();
        $connect2form_dir = $upload_dir['basedir'] . '/connect2form';

        // Create upload directory if it doesn't exist
        if (!file_exists($connect2form_dir)) {
            wp_mkdir_p($connect2form_dir);
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\nDeny from all\n";
            file_put_contents($connect2form_dir . '/.htaccess', $htaccess_content);
        }

        foreach ($files as $field_name => $file) {
            if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Validate file type and size
            $validation = $this->validate_file_upload($file);
            if (is_wp_error($validation)) {
                return $validation;
            }

            // Generate secure filename via WP API (avoid move_uploaded_file)
            $safe_base = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

            $unique_cb = function ($dir, $name, $ext) use ($safe_base, $extension) {
                $ext = $ext ?: ( $extension ? '.' . $extension : '' );
                return $safe_base . '_' . uniqid('', true) . $ext;
            };

            $upload_filter = function ($dirs) use ($connect2form_dir, $upload_dir) {
                $dirs['path']   = $connect2form_dir;
                $dirs['url']    = trailingslashit($upload_dir['baseurl']) . 'connect2form-builder';
                $dirs['subdir'] = '/connect2form';
                return $dirs;
            };

            add_filter('upload_dir', $upload_filter);
            $handled = wp_handle_sideload(
                array(
                    'name'     => $file['name'],
                    'type'     => $file['type'],
                    'tmp_name' => $file['tmp_name'],
                    'error'    => $file['error'],
                    'size'     => $file['size'],
                ),
                array(
                    'test_form'               => false,
                    'unique_filename_callback'=> $unique_cb,
                )
            );
            remove_filter('upload_dir', $upload_filter);

            if (isset($handled['error'])) {
                return new WP_Error('upload_failed', $this->get_translated_message('File upload failed.'));
            }

            $uploaded_files[$field_name] = array(
                'name' => sanitize_file_name($file['name']),
                'path' => $handled['file'],
                'url'  => $handled['url'],
                'size' => (int) $file['size'],
                'type' => $handled['type'],
            );
        }

        return $uploaded_files;
    }

    /**
     * Validate file upload
     */
    private function validate_file_upload($file) {
        // Check file size
        $max_size = apply_filters('connect2form_max_file_size', 5 * 1024 * 1024); // 5MB default
        if ($file['size'] > $max_size) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    $this->get_translated_message('File size must be less than %s.'),
                    size_format($max_size)
                )
            );
        }

        // Check file type
        $allowed_types = apply_filters('connect2form_allowed_file_types', array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain'
        ));

        $file_type = wp_check_filetype($file['name'], $allowed_types);
        if (!$file_type['type']) {
            return new WP_Error('invalid_file_type', $this->get_translated_message('File type not allowed.'));
        }

        // Additional security checks
        if (function_exists('mime_content_type')) {
            $real_mime = mime_content_type($file['tmp_name']);
            if ($real_mime && !in_array($real_mime, $allowed_types)) {
                return new WP_Error('invalid_file_content', $this->get_translated_message('File content does not match extension.'));
            }
        }

        return true;
    }

    /**
     * Send email notification using admin-defined templates
     */
    private function send_email_notification($form, $submission_data, $notification) {
        $to = sanitize_email($notification['to']);
        

        
        // Process subject with merge tags (will be populated after merge_tags array is built)
        $subject_template = !empty($notification['subject']) ? $notification['subject'] : sprintf(
            $this->get_translated_message('New submission from %s'),
            esc_html($form->form_title)
        );

        // Get form fields for proper labeling and merge tag processing
        $form_fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
        $field_labels = array();
        $merge_tags = array();

        // Create a mapping of field IDs to labels and prepare merge tags
        if (is_array($form_fields)) {
            $field_type_counter = array(); // Count fields by type for smart naming
            
            foreach ($form_fields as $index => $field) {
                if (!empty($field['id'])) {
                    $field_type = isset($field['type']) ? $field['type'] : 'text';
                    
                    // Initialize counter for this field type
                    if (!isset($field_type_counter[$field_type])) {
                        $field_type_counter[$field_type] = 0;
                    }
                    $field_type_counter[$field_type]++;
                    
                    // Generate smart label if label is empty or generic
                    $original_label = isset($field['label']) ? trim($field['label']) : '';
                    if (empty($original_label) || $this->is_generic_field_label($original_label)) {
                        $smart_label = $this->get_smart_field_label($field_type, $field_type_counter[$field_type], $index);
                    } else {
                        $smart_label = $original_label;
                    }
                    
                    $field_labels[$field['id']] = $smart_label;
                    
                    // Get and format the field value based on type
                    $field_value = $this->get_formatted_field_value($field, $submission_data);
                    
                    // Create merge tags with multiple possible formats
                    $merge_tags['{' . $field['id'] . '}'] = $field_value;
                    $merge_tags['{' . $smart_label . '}'] = $field_value;
                    
                    // Also add original label if different
                    if (!empty($original_label) && $original_label !== $smart_label) {
                        $merge_tags['{' . $original_label . '}'] = $field_value;
                    }
                    
                    // Additional debug: also try field name if it exists
                    if (!empty($field['name']) && $field['name'] !== $field['id']) {
                        $merge_tags['{' . $field['name'] . '}'] = $field_value;
                    }
                }
            }
        }
        
        // Add system merge tags
        $merge_tags['{site_name}'] = get_bloginfo('name');
        $merge_tags['{site_url}'] = home_url();
        $merge_tags['{submission_date}'] = current_time('F j, Y \a\t g:i a');
        $merge_tags['{ip_address}'] = $this->get_client_ip();
        $merge_tags['{form_title}'] = esc_html($form->form_title);
        $merge_tags['{admin_email}'] = get_option('admin_email');

        // Process subject with merge tags
        $subject = $subject_template;
        foreach ($merge_tags as $tag => $value) {
            $subject = str_replace($tag, $value, $subject);
        }

        // Build email content based on template preference
        // Priority: Custom message first (acts as admin template), then admin template, then default
        if (!empty($notification['message'])) {
            // Use custom message template and replace merge tags
            $message = $notification['message'];
            
            // Replace merge tags
            foreach ($merge_tags as $tag => $value) {
                $message = str_replace($tag, $value, $message);
            }
            
            // Final fallback: if there are still unreplaced field tags, try direct submission data replacement
            foreach ($submission_data as $key => $value) {
                if (strpos($key, 'field_') === 0) {
                    $field_tag = '{' . $key . '}';
                    if (strpos($message, $field_tag) !== false) {
                        $formatted_value = is_array($value) ? implode(', ', $value) : esc_html($value);
                        $message = str_replace($field_tag, $formatted_value, $message);
                    }
                }
            }
            
            // Set Content-Type to HTML if the message contains HTML tags
            $is_html = (strpos($message, '<') !== false && strpos($message, '>') !== false);
        } else {
            // Use default plain text message
            $is_html = false;
            $message = sprintf(
                $this->get_translated_message("You have received a new form submission from %s:\n\n"),
                esc_html($form->form_title)
            );

            foreach ($submission_data as $key => $value) {
                if (in_array($key, array('nonce', 'action', 'form_id', 'timestamp', 'website'))) {
                    continue;
                }
                
                // Use field label if available, otherwise format the key
                $label = isset($field_labels[$key]) ? $field_labels[$key] : ucfirst(str_replace('_', ' ', $key));

                // Handle special fields
                if ($key === 'wp_http_referer') {
                    $label = 'Page URL';
                    // Convert relative URL to full URL
                    if (!empty($value) && strpos($value, 'http') !== 0) {
                        $value = home_url($value);
                    }
                }

                // Handle array values (checkboxes, multi-select)
                if (is_array($value)) {
                    $value = implode(', ', array_map('esc_html', $value));
                } else {
                    $value = esc_html($value);
                }

                $message .= sprintf("%s: %s\n", $label, $value);
            }

            $message .= sprintf(
                "\n" . $this->get_translated_message('Submitted on: %s'),
                current_time('F j, Y \a\t g:i a')
            );
        }

        // Send email
        $headers = array();
        
        // Set appropriate Content-Type
        if (isset($is_html) && $is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        // Set From header
        if (!empty($notification['from'])) {
            $from_email = sanitize_email($notification['from']);
            $from_name = get_bloginfo('name'); // Use site name as default
            $headers[] = sprintf('From: %s <%s>', $from_name, $from_email);
        } else {
            // Use admin email as default from address
            $admin_email = get_option('admin_email');
            $headers[] = sprintf('From: %s <%s>', get_bloginfo('name'), $admin_email);
        }
        
        // Set BCC header
        if (!empty($notification['bcc'])) {
            $headers[] = 'BCC: ' . sanitize_email($notification['bcc']);
        }
        
        // Set CC header
        if (!empty($notification['cc'])) {
            $headers[] = 'CC: ' . sanitize_email($notification['cc']);
        }
        
        // Set Reply-To header
        if (!empty($notification['reply_to'])) {
            $headers[] = 'Reply-To: ' . sanitize_email($notification['reply_to']);
        }

        $mail_sent = wp_mail($to, $subject, $message, $headers);
        
        // Log email sending results for debugging
        if (!$mail_sent && current_user_can('manage_options')) {
          //  error_log('Connect2Form Email Error: Failed to send notification email to ' . $to);
        } elseif ($mail_sent && current_user_can('manage_options')) {
           // error_log('Connect2Form Email Success: Notification email sent to ' . $to);
        }
    }



    /**
     * Check if field label is generic and should be replaced
     */
    private function is_generic_field_label($label) {
        $generic_patterns = array(
            'Text Field', 'Text Area', 'Email Address', 'Number', 'Date',
            'Phone Number', 'Dropdown', 'Radio Buttons', 'Checkboxes',
            'File Upload', 'Field', 'Input', 'Select', 'Option'
        );
        
        return in_array($label, $generic_patterns) || 
               preg_match('/^(Field|Input|Text)\s*\d*$/i', $label) ||
               strlen($label) < 2;
    }
    
    /**
     * Generate smart field label based on type and position
     */
    private function get_smart_field_label($field_type, $type_count, $position) {
        switch ($field_type) {
            case 'text':
                // Smart naming for text fields based on position
                if ($position === 0) return 'Full Name';
                if ($position === 1 && $type_count === 2) return 'Last Name';
                if ($position === 2 && $type_count === 3) return 'Company';
                if ($type_count === 1) return 'Name';
                return 'Text Field ' . $type_count;
                
            case 'email':
                return $type_count === 1 ? 'Email Address' : 'Email Address ' . $type_count;
                
            case 'textarea':
                return $type_count === 1 ? 'Message' : 'Message ' . $type_count;
                
            case 'phone':
                return $type_count === 1 ? 'Phone Number' : 'Phone Number ' . $type_count;
                
            case 'number':
                return $type_count === 1 ? 'Number' : 'Number ' . $type_count;
                
            case 'date':
                return $type_count === 1 ? 'Date' : 'Date ' . $type_count;
                
            case 'select':
                return $type_count === 1 ? 'Selection' : 'Selection ' . $type_count;
                
            case 'radio':
                return $type_count === 1 ? 'Choice' : 'Choice ' . $type_count;
                
            case 'checkbox':
                return $type_count === 1 ? 'Options' : 'Options ' . $type_count;
                
            case 'file':
                return $type_count === 1 ? 'File Upload' : 'File Upload ' . $type_count;
                
            case 'utm':
                return 'Campaign Tracking';
                
            case 'captcha':
                return 'Security Verification';
                
            default:
                return ucfirst($field_type) . ($type_count > 1 ? ' ' . $type_count : '');
        }
    }
    
    /**
     * Get formatted field value based on field type
     */
    private function get_formatted_field_value($field, $submission_data) {
        $field_id = $field['id'];
        $field_type = isset($field['type']) ? $field['type'] : 'text';
        
        // Try multiple ways to find the field value
        $raw_value = '';
        
        // First try with field ID
        if (isset($submission_data[$field_id])) {
            $raw_value = $submission_data[$field_id];
        }
        // Then try with field name if different
        elseif (!empty($field['name']) && isset($submission_data[$field['name']])) {
            $raw_value = $submission_data[$field['name']];
        }
        // Try with field label as last resort
        elseif (!empty($field['label']) && isset($submission_data[$field['label']])) {
            $raw_value = $submission_data[$field['label']];
        }
        
        // Handle empty values
        if (empty($raw_value) && $raw_value !== '0' && $raw_value !== 0) {
            return '(Not provided)';
        }
        
        // Format based on field type
        switch ($field_type) {
            case 'checkbox':
                if (is_array($raw_value)) {
                    return implode(', ', array_map('esc_html', $raw_value));
                }
                return esc_html($raw_value);
                
            case 'radio':
            case 'select':
                return esc_html($raw_value);
                
            case 'file':
                if (is_array($raw_value)) {
                    $files = array();
                    foreach ($raw_value as $file) {
                        if (is_string($file)) {
                            $files[] = esc_html(basename($file));
                        } elseif (is_array($file) && isset($file['name'])) {
                            $files[] = esc_html($file['name']);
                        }
                    }
                    return !empty($files) ? implode(', ', $files) : '(No files)';
                }
                return is_string($raw_value) ? esc_html(basename($raw_value)) : '(No file)';
                
            case 'email':
                return sanitize_email($raw_value);
                
            case 'phone':
                return esc_html($raw_value);
                
            case 'number':
                return is_numeric($raw_value) ? esc_html($raw_value) : esc_html($raw_value);
                
            case 'date':
                if (strtotime($raw_value)) {
                    return gmdate('F j, Y', strtotime($raw_value));
                }
                return esc_html($raw_value);
                
            case 'textarea':
                return esc_html($raw_value); // Keep simple for emails
                
            case 'utm':
                if (is_array($raw_value)) {
                    $utm_parts = array();
                    foreach ($raw_value as $key => $value) {
                        if (!empty($value)) {
                            $utm_parts[] = ucfirst(str_replace('utm_', '', $key)) . ': ' . esc_html($value);
                        }
                    }
                    return !empty($utm_parts) ? implode(', ', $utm_parts) : '(No tracking data)';
                }
                return esc_html($raw_value);
                
            default:
                // Handle arrays for any other field types
                if (is_array($raw_value)) {
                    return implode(', ', array_map('esc_html', $raw_value));
                }
                return esc_html($raw_value);
        }
    }

    /**
     * Send auto-responder email
     */
    private function send_auto_responder($form, $submission_data, $form_settings) {
        if (empty($submission_data['email']) || !is_email($submission_data['email'])) {
            return;
        }

        $to = sanitize_email($submission_data['email']);
        $subject = !empty($form_settings['auto_responder_subject']) 
            ? sanitize_text_field($form_settings['auto_responder_subject'])
            : $this->get_translated_message('Thank you for your submission');

        $message = !empty($form_settings['auto_responder_message']) 
            ? wp_kses_post($form_settings['auto_responder_message'])
            : $this->get_translated_message('Thank you for contacting us. We will get back to you soon.');

        // Create merge tags for auto-responder (similar to notification system)
        $form_fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
        $merge_tags = array();

        // Add form field merge tags
        if (is_array($form_fields)) {
            foreach ($form_fields as $field) {
                if (!empty($field['id'])) {
                    $field_value = $this->get_formatted_field_value($field, $submission_data);
                    $merge_tags['{' . $field['id'] . '}'] = $field_value;
                    
                    // Also add by label if available
                    if (!empty($field['label'])) {
                        $merge_tags['{' . $field['label'] . '}'] = $field_value;
                    }
                }
            }
        }

        // Add system merge tags
        $merge_tags['{form_title}'] = esc_html($form->form_title);
        $merge_tags['{site_name}'] = get_bloginfo('name');
        $merge_tags['{site_url}'] = home_url();
        $merge_tags['{date}'] = current_time('F j, Y');
        $merge_tags['{submission_date}'] = current_time('F j, Y \a\t g:i a');

        // Replace merge tags in subject and message
        foreach ($merge_tags as $tag => $value) {
            $subject = str_replace($tag, $value, $subject);
            $message = str_replace($tag, $value, $message);
        }

        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        if (!empty($form_settings['from_email'])) {
            $from_email = sanitize_email($form_settings['from_email']);
            $from_name = !empty($form_settings['from_name']) 
                ? sanitize_text_field($form_settings['from_name']) 
                : get_bloginfo('name');
            $headers[] = sprintf('From: %s <%s>', $from_name, $from_email);
        }

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Check rate limiting
     */
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $transient_key = 'connect2form_rate_limit_' . md5($ip);
        
        $attempts = get_transient($transient_key);
        if ($attempts === false) {
            $attempts = 0;
        }

        // Increase limit for testing/development
        $max_attempts = apply_filters('connect2form_max_attempts_per_hour', 50); // Increased from 10 to 50
        
        // Allow admins to bypass rate limiting for testing
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if ($attempts >= $max_attempts) {
            return false;
        }

        set_transient($transient_key, $attempts + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Clear rate limit for current IP (admin only)
     */
    public function clear_rate_limit() {
        // Check if user is admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        check_ajax_referer('connect2form_clear_rate_limit', 'nonce');

        $ip = $this->get_client_ip();
        $transient_key = 'connect2form_rate_limit_' . md5($ip);
        
        delete_transient($transient_key);
        
        wp_send_json_success('Rate limit cleared for IP: ' . $ip);
    }



    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );

        foreach ($ip_keys as $key) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_SERVER values are unslashed and sanitized below; validated with filter_var
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_SERVER value is unslashed and sanitized below; validated with filter_var
                $raw  = wp_unslash((string) $_SERVER[$key]);
                $ips  = explode(',', $raw);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return sanitize_text_field($ip);
                }
            }
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_SERVER value is unslashed and sanitized below; validated with filter_var
        $fallback = isset($_SERVER['REMOTE_ADDR']) ? wp_unslash((string) $_SERVER['REMOTE_ADDR']) : '127.0.0.1';
        return sanitize_text_field($fallback);
    }

    /**
     * Get email debug information
     */
    public function get_email_debug_info() {
        return array(
            'admin_email' => get_option('admin_email'),
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'wp_mail_function' => function_exists('wp_mail') ? 'Available' : 'Not Available',
            'mail_function' => function_exists('mail') ? 'Available' : 'Not Available',
            'smtp_configured' => defined('SMTP_HOST') ? 'Yes' : 'Probably No',
        );
    }

    /**
     * Test email functionality
     */
    public function test_email_functionality() {
        $admin_email = get_option('admin_email');
        $subject = 'Connect2Form Test Email';
        $message = 'This is a test email from Connect2Form plugin to verify email functionality is working correctly.';
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        return wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Get translated message
     */
    private function get_translated_message($message) {
        // Define translatable messages with their translations
        $messages = array(
            'form_not_found' => __('Form not found.', 'connect2form-builder'),
            'invalid_nonce' => __('Security check failed.', 'connect2form-builder'),
            'submission_failed' => __('Form submission failed.', 'connect2form-builder'),
            'required_field_missing' => __('Required field is missing.', 'connect2form-builder'),
            'invalid_email' => __('Invalid email address.', 'connect2form-builder'),
            'file_upload_error' => __('File upload failed.', 'connect2form-builder'),
            'submission_success' => __('Form submitted successfully.', 'connect2form-builder'),
        );
        
        // Return translated message if it exists, otherwise return the original message
        return isset($messages[$message]) ? $messages[$message] : $message;
    }
}

