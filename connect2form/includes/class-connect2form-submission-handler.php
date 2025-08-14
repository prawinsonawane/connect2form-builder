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

        // Process integrations
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

        // Trigger addon integrations hook (new addon system)
        try {
            $addon_form_data = array(
                'form_id' => $form_id,
                'fields' => $submission_data
            );
            do_action('connect2form_after_submission', $submission_id, $addon_form_data);
        } catch (Exception $e) {
            // Continue with form processing even if addon fails
        }

        // Send notifications (new email feature)
        $form_settings = !empty($form->settings) ? (is_string($form->settings) ? json_decode($form->settings, true) : (is_array($form->settings) ? $form->settings : array())) : array();
        $form_settings = is_array($form_settings) ? $form_settings : array();

        // Email notification
        if (!empty($form_settings['email_notifications']) && !empty($form_settings['notification_email'])) {
            $this->send_email_notification($form, $submission_data, $form_settings);
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
                $dirs['url']    = trailingslashit($upload_dir['baseurl']) . 'connect2form';
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
     * Send email notification
     */
    private function send_email_notification($form, $submission_data, $form_settings) {
        $to = sanitize_email($form_settings['notification_email']);
        $subject = sprintf(
            $this->get_translated_message('New submission from %s'),
            esc_html($form->form_title)
        );

        // Build email content
        $message = sprintf(
            $this->get_translated_message("You have received a new form submission from %s:\n\n"),
            esc_html($form->form_title)
        );

        foreach ($submission_data as $key => $value) {
            if (in_array($key, array('nonce', 'action', 'form_id', 'timestamp', 'website'))) {
                continue;
            }

            $message .= sprintf("%s: %s\n", ucfirst(str_replace('_', ' ', $key)), esc_html($value));
        }

        $message .= sprintf(
            "\n" . $this->get_translated_message('Submitted on: %s'),
            current_time('F j, Y \a\t g:i a')
        );

        // Send email
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
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

        // Replace placeholders
        $message = str_replace(
            array('{form_title}', '{site_name}', '{date}'),
            array(
                esc_html($form->form_title),
                get_bloginfo('name'),
                current_time('F j, Y')
            ),
            $message
        );

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

        $max_attempts = apply_filters('connect2form_max_attempts_per_hour', 10);
        
        if ($attempts >= $max_attempts) {
            return false;
        }

        set_transient($transient_key, $attempts + 1, HOUR_IN_SECONDS);
        return true;
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
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- server values are read-only; sanitized/validated below
                $raw  = (string) $_SERVER[$key];
                $ips  = explode(',', $raw);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only server value; validated below
        $fallback = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        return $fallback;
    }

    /**
     * Get translated message
     */
    private function get_translated_message($message) {
        // Define translatable messages with their translations
        $messages = array(
            'form_not_found' => __('Form not found.', 'connect2form'),
            'invalid_nonce' => __('Security check failed.', 'connect2form'),
            'submission_failed' => __('Form submission failed.', 'connect2form'),
            'required_field_missing' => __('Required field is missing.', 'connect2form'),
            'invalid_email' => __('Invalid email address.', 'connect2form'),
            'file_upload_error' => __('File upload failed.', 'connect2form'),
            'submission_success' => __('Form submitted successfully.', 'connect2form'),
        );
        
        // Return translated message if it exists, otherwise return the original message
        return isset($messages[$message]) ? $messages[$message] : $message;
    }
}

