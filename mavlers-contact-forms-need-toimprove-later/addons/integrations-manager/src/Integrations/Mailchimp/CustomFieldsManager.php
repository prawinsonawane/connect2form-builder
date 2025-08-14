<?php
/**
 * Mailchimp Custom Fields Manager
 * 
 * Handles custom fields, merge tags, validation, and advanced field mapping
 * 
 * @package MavlersCF\Integrations\Mailchimp
 * @since 1.0.0
 */

namespace MavlersCF\Integrations\Mailchimp;

if (!defined('ABSPATH')) {
    exit;
}

class CustomFieldsManager {
    
    /**
     * Cache configuration
     */
    private $cache_expiry = 3600; // 1 hour
    private $cache_group = 'mavlers_cf_mailchimp_fields';
    
    /**
     * Mailchimp integration instance
     */
    private $mailchimp_integration;
    
    /**
     * Constructor
     */
    public function __construct($mailchimp_integration = null) {
        $this->mailchimp_integration = $mailchimp_integration;
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers for merge fields - REMOVED: mailchimp_get_merge_fields to avoid conflict
        add_action('wp_ajax_mailchimp_create_merge_field', array($this, 'ajax_create_merge_field'));
        add_action('wp_ajax_mailchimp_update_merge_field', array($this, 'ajax_update_merge_field'));
        add_action('wp_ajax_mailchimp_delete_merge_field', array($this, 'ajax_delete_merge_field'));
        add_action('wp_ajax_mailchimp_sync_custom_fields', array($this, 'ajax_sync_custom_fields'));
        
        // AJAX handlers for interest categories
        add_action('wp_ajax_mailchimp_get_interest_categories', array($this, 'ajax_get_interest_categories'));
        add_action('wp_ajax_mailchimp_get_interests', array($this, 'ajax_get_interests'));
        add_action('wp_ajax_mailchimp_map_interests', array($this, 'ajax_map_interests'));
        
        // Field validation
        add_filter('mavlers_cf_mailchimp_validate_field_data', array($this, 'validate_custom_field_data'), 10, 3);
        
        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_custom_fields_assets'));
    }
    
    /**
     * Enqueue custom fields assets
     */
    public function enqueue_custom_fields_assets($hook) {
        if (strpos($hook, 'mavlers-cf') !== false || strpos($hook, 'mavlers-contact-forms') !== false) {
            wp_enqueue_style(
                'mavlers-cf-custom-fields',
                MAVLERS_CF_INTEGRATIONS_URL . 'assets/css/admin/custom-fields.css',
                array(),
                '1.0.0'
            );
            
            // Note: Script enqueuing is now handled by MailchimpIntegration to avoid conflicts
            // The main integration will enqueue both mailchimp.js and mailchimp-form.js
        }
    }
    
    /**
     * Get merge fields for a specific audience
     */
    public function get_merge_fields($audience_id, $force_refresh = false) {
        if (empty($audience_id)) {
            return array('success' => false, 'message' => 'Audience ID is required');
        }
        
        $cache_key = "merge_fields_{$audience_id}";
        
        if (!$force_refresh) {
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached !== false) {
                return array('success' => true, 'data' => $cached);
            }
        }
        
        if (!$this->mailchimp_integration) {
            return array('success' => false, 'message' => 'Mailchimp integration not available');
        }
        
        if (!$this->mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $this->mailchimp_integration->get_global_settings();
        
        if (empty($global_settings['api_key'])) {
            return array('success' => false, 'message' => 'Mailchimp API key not configured');
        }
        
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        if (empty($dc)) {
            return array('success' => false, 'message' => 'Invalid API key format');
        }
        
        // Get merge fields from Mailchimp API - this should include EMAIL field
        // Try the standard merge fields endpoint first
        $response = $this->make_mailchimp_request(
            'GET',
            "/lists/{$audience_id}/merge-fields?count=100",
            $api_key,
            $dc
        );
        
        if (!$response) {
            return array('success' => false, 'message' => 'No response from Mailchimp API');
        }
        
        if (isset($response['status']) && $response['status'] >= 400) {
            $error_message = $response['detail'] ?? $response['title'] ?? 'Failed to fetch merge fields';
            return array(
                'success' => false, 
                'message' => $error_message
            );
        }
        
        if (!isset($response['merge_fields'])) {
            return array('success' => false, 'message' => 'Invalid response format from Mailchimp API');
        }
        
        $merge_fields = $response['merge_fields'] ?? array();
        
        // EMAIL is a system field that's always required in Mailchimp
        // We need to ensure it's always available for mapping
        $system_fields = array(
            array(
                'merge_id' => 0,
                'tag' => 'EMAIL',
                'name' => 'Email Address',
                'type' => 'email',
                'required' => true,
                'default_value' => '',
                'public' => true,
                'display_order' => 0,
                'options' => array(),
                'help_text' => 'Email address is required',
                'list_id' => $audience_id
            )
        );
        
        // Combine system fields with merge fields
        $all_fields = array_merge($system_fields, $merge_fields);
        
        if (empty($all_fields)) {
            return array('success' => false, 'message' => 'No fields found for this audience');
        }
        
        // Process and enhance merge fields
        $processed_fields = $this->process_merge_fields($all_fields);
        
        // Cache the result
        wp_cache_set($cache_key, $processed_fields, $this->cache_group, $this->cache_expiry);
        
        return array('success' => true, 'data' => $processed_fields);
    }
    
    /**
     * Process merge fields for enhanced functionality
     */
    private function process_merge_fields($merge_fields) {
        $processed = array();
        
        foreach ($merge_fields as $field) {
            $processed_field = array(
                'merge_id' => $field['merge_id'],
                'tag' => $field['tag'],
                'name' => $field['name'],
                'type' => $field['type'],
                'required' => $field['required'],
                'default_value' => $field['default_value'] ?? '',
                'public' => $field['public'],
                'display_order' => $field['display_order'],
                'options' => $field['options'] ?? array(),
                'help_text' => $field['help_text'] ?? '',
                'list_id' => $field['list_id']
            );
            
            // Add validation rules based on type
            $processed_field['validation_rules'] = $this->get_field_validation_rules($field['type'], $field['options'] ?? array());
            
            // Add suggested form field types for mapping
            $processed_field['suggested_form_types'] = $this->get_suggested_form_field_types($field['type']);
            
            // Add formatting rules for display
            $processed_field['formatting'] = $this->get_field_formatting_rules($field['type']);
            
            $processed[$field['tag']] = $processed_field;
        }
        
        return $processed;
    }
    
    /**
     * Get validation rules for field type
     */
    private function get_field_validation_rules($type, $options = array()) {
        $rules = array();
        
        switch ($type) {
            case 'email':
                $rules['pattern'] = '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$';
                $rules['message'] = __('Please enter a valid email address', 'mavlers-contact-forms');
                break;
                
            case 'number':
                $rules['pattern'] = '^-?\d*\.?\d+$';
                $rules['message'] = __('Please enter a valid number', 'mavlers-contact-forms');
                break;
                
            case 'phone':
                $rules['pattern'] = '^[\+]?[1-9][\d]{0,15}$';
                $rules['message'] = __('Please enter a valid phone number', 'mavlers-contact-forms');
                break;
                
            case 'date':
                $rules['pattern'] = '^\d{4}-\d{2}-\d{2}$';
                $rules['format'] = 'YYYY-MM-DD';
                $rules['message'] = __('Please enter a valid date (YYYY-MM-DD)', 'mavlers-contact-forms');
                break;
                
            case 'birthday':
                $rules['pattern'] = '^(0[1-9]|1[0-2])/(0[1-9]|[12]\d|3[01])$';
                $rules['format'] = 'MM/DD';
                $rules['message'] = __('Please enter a valid birthday (MM/DD)', 'mavlers-contact-forms');
                break;
                
            case 'address':
                $rules['required_subfields'] = array('addr1', 'city', 'state', 'zip');
                $rules['message'] = __('Please enter a complete address', 'mavlers-contact-forms');
                break;
                
            case 'dropdown':
                if (!empty($options['choices'])) {
                    $rules['allowed_values'] = wp_list_pluck($options['choices'], 'label');
                    $rules['message'] = __('Please select a valid option', 'mavlers-contact-forms');
                }
                break;
                
            case 'radio':
                if (!empty($options['choices'])) {
                    $rules['allowed_values'] = wp_list_pluck($options['choices'], 'label');
                    $rules['message'] = __('Please select a valid option', 'mavlers-contact-forms');
                }
                break;
                
            case 'text':
            default:
                if (!empty($options['size'])) {
                    $rules['max_length'] = intval($options['size']);
                    $rules['message'] = sprintf(__('Text must be %d characters or less', 'mavlers-contact-forms'), $rules['max_length']);
                }
                break;
        }
        
        return $rules;
    }
    
    /**
     * Get suggested form field types for mapping
     */
    private function get_suggested_form_field_types($mailchimp_type) {
        $mapping = array(
            'email' => array('email'),
            'text' => array('text', 'textarea'),
            'number' => array('number'),
            'phone' => array('tel', 'text'),
            'date' => array('date'),
            'birthday' => array('date', 'text'),
            'address' => array('text', 'textarea'),
            'dropdown' => array('select', 'radio'),
            'radio' => array('radio', 'select'),
            'url' => array('url', 'text'),
            'imageurl' => array('url', 'text', 'file'),
            'zip' => array('text')
        );
        
        return $mapping[$mailchimp_type] ?? array('text');
    }
    
    /**
     * Get formatting rules for field type
     */
    private function get_field_formatting_rules($type) {
        $formatting = array();
        
        switch ($type) {
            case 'phone':
                $formatting['display_format'] = 'phone';
                $formatting['input_mask'] = '+1 (999) 999-9999';
                break;
                
            case 'date':
                $formatting['display_format'] = 'date';
                $formatting['date_format'] = 'Y-m-d';
                break;
                
            case 'birthday':
                $formatting['display_format'] = 'birthday';
                $formatting['date_format'] = 'm/d';
                break;
                
            case 'number':
                $formatting['display_format'] = 'number';
                $formatting['decimal_places'] = 2;
                break;
                
            case 'address':
                $formatting['display_format'] = 'address';
                $formatting['subfields'] = array('addr1', 'addr2', 'city', 'state', 'zip', 'country');
                break;
        }
        
        return $formatting;
    }
    
    /**
     * Create a new merge field
     */
    public function create_merge_field($audience_id, $field_data) {
        if (empty($audience_id)) {
            return array('success' => false, 'message' => 'Audience ID is required');
        }
        
        // Validate field data
        $validation = $this->validate_field_data($field_data);
        if (!$validation['valid']) {
            return array('success' => false, 'message' => $validation['message']);
        }
        
        if (!$this->mailchimp_integration || !$this->mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $this->mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Prepare field data for API
        $api_data = $this->prepare_field_data_for_api($field_data);
        
        // Create merge field via Mailchimp API
        $response = $this->make_mailchimp_request(
            'POST',
            "/lists/{$audience_id}/merge-fields",
            $api_key,
            $dc,
            $api_data
        );
        
        if (!$response || (isset($response['status']) && $response['status'] >= 400)) {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to create merge field'
            );
        }
        
        // Clear cache
        $cache_key = "merge_fields_{$audience_id}";
        wp_cache_delete($cache_key, $this->cache_group);
        
        return array(
            'success' => true,
            'data' => $response,
            'message' => 'Merge field created successfully'
        );
    }
    
    /**
     * Update an existing merge field
     */
    public function update_merge_field($audience_id, $merge_id, $field_data) {
        if (empty($audience_id) || empty($merge_id)) {
            return array('success' => false, 'message' => 'Audience ID and Merge ID are required');
        }
        
        // Validate field data
        $validation = $this->validate_field_data($field_data, $merge_id);
        if (!$validation['valid']) {
            return array('success' => false, 'message' => $validation['message']);
        }
        
        if (!$this->mailchimp_integration || !$this->mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $this->mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Prepare field data for API
        $api_data = $this->prepare_field_data_for_api($field_data, true);
        
        // Update merge field via Mailchimp API
        $response = $this->make_mailchimp_request(
            'PATCH',
            "/lists/{$audience_id}/merge-fields/{$merge_id}",
            $api_key,
            $dc,
            $api_data
        );
        
        if (!$response || (isset($response['status']) && $response['status'] >= 400)) {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to update merge field'
            );
        }
        
        // Clear cache
        $cache_key = "merge_fields_{$audience_id}";
        wp_cache_delete($cache_key, $this->cache_group);
        
        return array(
            'success' => true,
            'data' => $response,
            'message' => 'Merge field updated successfully'
        );
    }
    
    /**
     * Delete a merge field
     */
    public function delete_merge_field($audience_id, $merge_id) {
        if (empty($audience_id) || empty($merge_id)) {
            return array('success' => false, 'message' => 'Audience ID and Merge ID are required');
        }
        
        if (!$this->mailchimp_integration || !$this->mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $this->mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Delete merge field via Mailchimp API
        $response = $this->make_mailchimp_request(
            'DELETE',
            "/lists/{$audience_id}/merge-fields/{$merge_id}",
            $api_key,
            $dc
        );
        
        if (isset($response['status']) && $response['status'] >= 400) {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to delete merge field'
            );
        }
        
        // Clear cache
        $cache_key = "merge_fields_{$audience_id}";
        wp_cache_delete($cache_key, $this->cache_group);
        
        return array(
            'success' => true,
            'message' => 'Merge field deleted successfully'
        );
    }
    
    /**
     * Get interest categories for an audience
     */
    public function get_interest_categories($audience_id, $force_refresh = false) {
        if (empty($audience_id)) {
            return array('success' => false, 'message' => 'Audience ID is required');
        }
        
        $cache_key = "interest_categories_{$audience_id}";
        
        if (!$force_refresh) {
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached !== false) {
                return array('success' => true, 'data' => $cached);
            }
        }
        
        if (!$this->mailchimp_integration || !$this->mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $this->mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Get interest categories from Mailchimp API
        $response = $this->make_mailchimp_request(
            'GET',
            "/lists/{$audience_id}/interest-categories?count=100",
            $api_key,
            $dc
        );
        
        if (!$response || (isset($response['status']) && $response['status'] >= 400)) {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to fetch interest categories'
            );
        }
        
        $categories = $response['categories'] ?? array();
        
        // Get interests for each category
        foreach ($categories as &$category) {
            $interests_response = $this->make_mailchimp_request(
                'GET',
                "/lists/{$audience_id}/interest-categories/{$category['id']}/interests?count=100",
                $api_key,
                $dc
            );
            
            $category['interests'] = $interests_response['interests'] ?? array();
        }
        
        // Cache the result
        wp_cache_set($cache_key, $categories, $this->cache_group, $this->cache_expiry);
        
        return array('success' => true, 'data' => $categories);
    }
    
    /**
     * Validate field data
     */
    private function validate_field_data($field_data, $merge_id = null) {
        $errors = array();
        
        // Required fields
        if (empty($field_data['name'])) {
            $errors[] = 'Field name is required';
        }
        
        if (empty($field_data['tag'])) {
            $errors[] = 'Field tag is required';
        } elseif (!preg_match('/^[A-Z0-9_]+$/', $field_data['tag'])) {
            $errors[] = 'Field tag can only contain uppercase letters, numbers, and underscores';
        }
        
        if (empty($field_data['type'])) {
            $errors[] = 'Field type is required';
        }
        
        // Type-specific validation
        if (!empty($field_data['type'])) {
            switch ($field_data['type']) {
                case 'dropdown':
                case 'radio':
                    if (empty($field_data['options']['choices'])) {
                        $errors[] = 'Choices are required for dropdown and radio fields';
                    }
                    break;
                    
                case 'number':
                    if (isset($field_data['default_value']) && !is_numeric($field_data['default_value'])) {
                        $errors[] = 'Default value must be a number for number fields';
                    }
                    break;
                    
                case 'email':
                    if (!empty($field_data['default_value']) && !is_email($field_data['default_value'])) {
                        $errors[] = 'Default value must be a valid email for email fields';
                    }
                    break;
            }
        }
        
        return array(
            'valid' => empty($errors),
            'message' => implode(', ', $errors)
        );
    }
    
    /**
     * Prepare field data for API submission
     */
    private function prepare_field_data_for_api($field_data, $is_update = false) {
        $api_data = array(
            'name' => sanitize_text_field($field_data['name']),
            'type' => sanitize_text_field($field_data['type'])
        );
        
        if (!$is_update) {
            $api_data['tag'] = strtoupper(sanitize_text_field($field_data['tag']));
        }
        
        // Optional fields
        if (isset($field_data['required'])) {
            $api_data['required'] = (bool) $field_data['required'];
        }
        
        if (isset($field_data['default_value'])) {
            $api_data['default_value'] = sanitize_text_field($field_data['default_value']);
        }
        
        if (isset($field_data['public'])) {
            $api_data['public'] = (bool) $field_data['public'];
        }
        
        if (isset($field_data['help_text'])) {
            $api_data['help_text'] = sanitize_textarea_field($field_data['help_text']);
        }
        
        // Type-specific options
        if (!empty($field_data['options'])) {
            $api_data['options'] = array();
            
            switch ($field_data['type']) {
                case 'dropdown':
                case 'radio':
                    if (!empty($field_data['options']['choices'])) {
                        $api_data['options']['choices'] = array();
                        foreach ($field_data['options']['choices'] as $choice) {
                            $api_data['options']['choices'][] = sanitize_text_field($choice);
                        }
                    }
                    break;
                    
                case 'text':
                    if (!empty($field_data['options']['size'])) {
                        $api_data['options']['size'] = intval($field_data['options']['size']);
                    }
                    break;
            }
        }
        
        return $api_data;
    }
    
    /**
     * Apply field formatting to a value
     */
    public function apply_field_formatting($value, $field_type, $formatting_rules) {
        if (empty($value) || empty($formatting_rules)) {
            return $value;
        }
        
        switch ($formatting_rules['display_format']) {
            case 'phone':
                // Format phone number
                $cleaned = preg_replace('/[^0-9]/', '', $value);
                if (strlen($cleaned) === 10) {
                    return sprintf('(%s) %s-%s', 
                        substr($cleaned, 0, 3),
                        substr($cleaned, 3, 3),
                        substr($cleaned, 6)
                    );
                }
                break;
                
            case 'date':
                // Format date
                $date = date_create($value);
                if ($date) {
                    return date_format($date, $formatting_rules['date_format']);
                }
                break;
                
            case 'birthday':
                // Format birthday
                $date = date_create($value);
                if ($date) {
                    return date_format($date, $formatting_rules['date_format']);
                }
                break;
                
            case 'number':
                // Format number
                if (is_numeric($value)) {
                    return number_format(floatval($value), $formatting_rules['decimal_places']);
                }
                break;
        }
        
        return $value;
    }
    
    /**
     * Validate custom field data
     */
    public function validate_custom_field_data($is_valid, $field_value, $field_config) {
        if (!$is_valid || empty($field_config['validation_rules'])) {
            return $is_valid;
        }
        
        $rules = $field_config['validation_rules'];
        
        // Pattern validation
        if (!empty($rules['pattern']) && !preg_match('/' . $rules['pattern'] . '/', $field_value)) {
            return false;
        }
        
        // Allowed values validation
        if (!empty($rules['allowed_values']) && !in_array($field_value, $rules['allowed_values'])) {
            return false;
        }
        
        // Max length validation
        if (!empty($rules['max_length']) && strlen($field_value) > $rules['max_length']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Extract datacenter from API key
     */
    private function extract_datacenter($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // Check if API key has the expected format (32 chars + hyphen + datacenter)
        if (!preg_match('/^[a-f0-9]{32}-[a-z0-9]+$/', $api_key)) {
            return '';
        }
        
        $dc = '';
        if (strpos($api_key, '-') !== false) {
            $dc = substr($api_key, strpos($api_key, '-') + 1);
        }
        
        return $dc;
    }
    
    /**
     * Make Mailchimp API request
     */
    private function make_mailchimp_request($method, $endpoint, $api_key, $dc, $data = array()) {
        $url = "https://{$dc}.api.mailchimp.com/3.0" . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array('status' => 500, 'detail' => $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $decoded = json_decode($body, true);
        
        return $decoded;
    }
    
    /**
     * AJAX: Get merge fields
     */
    public function ajax_get_merge_fields() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $force_refresh = (bool) ($_POST['force_refresh'] ?? false);
        
        $result = $this->get_merge_fields($audience_id, $force_refresh);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Create merge field
     */
    public function ajax_create_merge_field() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $field_data = $_POST['field_data'] ?? array();
        
        // Sanitize field data
        $field_data = $this->sanitize_field_data($field_data);
        
        $result = $this->create_merge_field($audience_id, $field_data);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Update merge field
     */
    public function ajax_update_merge_field() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $merge_id = sanitize_text_field($_POST['merge_id'] ?? '');
        $field_data = $_POST['field_data'] ?? array();
        
        // Sanitize field data
        $field_data = $this->sanitize_field_data($field_data);
        
        $result = $this->update_merge_field($audience_id, $merge_id, $field_data);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Delete merge field
     */
    public function ajax_delete_merge_field() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $merge_id = sanitize_text_field($_POST['merge_id'] ?? '');
        
        $result = $this->delete_merge_field($audience_id, $merge_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Sync custom fields
     */
    public function ajax_sync_custom_fields() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        
        // Force refresh from API
        $result = $this->get_merge_fields($audience_id, true);
        
        if ($result['success']) {
            $result['message'] = 'Custom fields synchronized successfully';
        }
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Get interest categories
     */
    public function ajax_get_interest_categories() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $force_refresh = (bool) ($_POST['force_refresh'] ?? false);
        
        $result = $this->get_interest_categories($audience_id, $force_refresh);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Get interests for category
     */
    public function ajax_get_interests() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $category_id = sanitize_text_field($_POST['category_id'] ?? '');
        
        if (empty($audience_id) || empty($category_id)) {
            wp_send_json(array('success' => false, 'message' => 'Audience ID and Category ID are required'));
        }
        
        if (!$this->mailchimp_integration || !$this->mailchimp_integration->is_globally_connected()) {
            wp_send_json(array('success' => false, 'message' => 'Mailchimp not connected'));
        }
        
        $global_settings = $this->mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        $response = $this->make_mailchimp_request(
            'GET',
            "/lists/{$audience_id}/interest-categories/{$category_id}/interests?count=100",
            $api_key,
            $dc
        );
        
        if (!$response || (isset($response['status']) && $response['status'] >= 400)) {
            wp_send_json(array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to fetch interests'
            ));
        }
        
        wp_send_json(array(
            'success' => true,
            'data' => $response['interests'] ?? array()
        ));
    }
    
    /**
     * AJAX: Map interests to form fields
     */
    public function ajax_map_interests() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $mapping = $_POST['mapping'] ?? array();
        
        if (empty($form_id) || empty($audience_id)) {
            wp_send_json(array('success' => false, 'message' => 'Form ID and Audience ID are required'));
        }
        
        // Sanitize mapping data
        $sanitized_mapping = array();
        foreach ($mapping as $form_field => $interests) {
            $sanitized_mapping[sanitize_text_field($form_field)] = array_map('sanitize_text_field', (array) $interests);
        }
        
        // Save interest mapping
        $option_key = "mavlers_cf_mailchimp_interest_mapping_{$form_id}_{$audience_id}";
        update_option($option_key, $sanitized_mapping);
        
        wp_send_json(array(
            'success' => true,
            'message' => 'Interest mapping saved successfully'
        ));
    }
    
    /**
     * Sanitize field data
     */
    private function sanitize_field_data($field_data) {
        $sanitized = array();
        
        if (isset($field_data['name'])) {
            $sanitized['name'] = sanitize_text_field($field_data['name']);
        }
        
        if (isset($field_data['tag'])) {
            $sanitized['tag'] = strtoupper(sanitize_text_field($field_data['tag']));
        }
        
        if (isset($field_data['type'])) {
            $sanitized['type'] = sanitize_text_field($field_data['type']);
        }
        
        if (isset($field_data['required'])) {
            $sanitized['required'] = (bool) $field_data['required'];
        }
        
        if (isset($field_data['default_value'])) {
            $sanitized['default_value'] = sanitize_text_field($field_data['default_value']);
        }
        
        if (isset($field_data['public'])) {
            $sanitized['public'] = (bool) $field_data['public'];
        }
        
        if (isset($field_data['help_text'])) {
            $sanitized['help_text'] = sanitize_textarea_field($field_data['help_text']);
        }
        
        if (isset($field_data['options']) && is_array($field_data['options'])) {
            $sanitized['options'] = array();
            
            if (isset($field_data['options']['choices']) && is_array($field_data['options']['choices'])) {
                $sanitized['options']['choices'] = array_map('sanitize_text_field', $field_data['options']['choices']);
            }
            
            if (isset($field_data['options']['size'])) {
                $sanitized['options']['size'] = intval($field_data['options']['size']);
            }
        }
        
        return $sanitized;
    }
} 