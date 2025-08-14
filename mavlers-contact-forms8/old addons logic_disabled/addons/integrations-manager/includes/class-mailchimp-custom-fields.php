<?php
/**
 * Mailchimp Custom Fields Manager
 * 
 * Handles custom fields, merge tags, and advanced field mapping
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Mailchimp_Custom_Fields {
    
    private $cache_expiry = 3600; // 1 hour
    private $cache_group = 'mavlers_cf_mailchimp_fields';
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_mailchimp_get_merge_fields', array($this, 'ajax_get_merge_fields'));
        add_action('wp_ajax_mailchimp_create_merge_field', array($this, 'ajax_create_merge_field'));
        add_action('wp_ajax_mailchimp_update_merge_field', array($this, 'ajax_update_merge_field'));
        add_action('wp_ajax_mailchimp_delete_merge_field', array($this, 'ajax_delete_merge_field'));
        add_action('wp_ajax_mailchimp_sync_custom_fields', array($this, 'ajax_sync_custom_fields'));
        add_action('wp_ajax_mailchimp_get_interest_categories', array($this, 'ajax_get_interest_categories'));
        add_action('wp_ajax_mailchimp_map_interests', array($this, 'ajax_map_interests'));
        
        // Custom field validation
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
                plugin_dir_url(__FILE__) . '../assets/css/custom-fields.css',
                array(),
                '1.0.0'
            );
            
            wp_enqueue_script(
                'mavlers-cf-custom-fields',
                plugin_dir_url(__FILE__) . '../assets/js/custom-fields.js',
                array('jquery'),
                '1.0.0',
                true
            );
            
            wp_localize_script('mavlers-cf-custom-fields', 'mailchimpCustomFields', array(
                'nonce' => wp_create_nonce('mavlers_cf_nonce'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'strings' => array(
                    'createField' => __('Create Field', 'mavlers-cf'),
                    'updateField' => __('Update Field', 'mavlers-cf'),
                    'deleteField' => __('Delete Field', 'mavlers-cf'),
                    'confirmDelete' => __('Are you sure you want to delete this field?', 'mavlers-cf'),
                    'syncSuccess' => __('Custom fields synchronized successfully', 'mavlers-cf'),
                    'syncError' => __('Failed to sync custom fields', 'mavlers-cf')
                )
            ));
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
        
        $mailchimp_integration = $this->get_mailchimp_integration();
        if (!$mailchimp_integration || !$mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Get merge fields from Mailchimp API
        $response = $this->make_mailchimp_request(
            'GET',
            "/lists/{$audience_id}/merge-fields?count=100",
            $api_key,
            $dc
        );
        
        if (!$response || isset($response['status']) && $response['status'] >= 400) {
            return array(
                'success' => false, 
                'message' => $response['detail'] ?? 'Failed to fetch merge fields'
            );
        }
        
        $merge_fields = $response['merge_fields'] ?? array();
        
        // Process and enhance merge fields
        $processed_fields = $this->process_merge_fields($merge_fields);
        
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
            
            // Add custom validation rules based on type
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
                $rules['message'] = __('Please enter a valid email address', 'mavlers-cf');
                break;
                
            case 'number':
                $rules['pattern'] = '^-?\d*\.?\d+$';
                $rules['message'] = __('Please enter a valid number', 'mavlers-cf');
                break;
                
            case 'phone':
                $rules['pattern'] = '^[\+]?[1-9][\d]{0,15}$';
                $rules['message'] = __('Please enter a valid phone number', 'mavlers-cf');
                break;
                
            case 'date':
                $rules['pattern'] = '^\d{4}-\d{2}-\d{2}$';
                $rules['format'] = 'YYYY-MM-DD';
                $rules['message'] = __('Please enter a valid date (YYYY-MM-DD)', 'mavlers-cf');
                break;
                
            case 'birthday':
                $rules['pattern'] = '^(0[1-9]|1[0-2])/(0[1-9]|[12]\d|3[01])$';
                $rules['format'] = 'MM/DD';
                $rules['message'] = __('Please enter a valid birthday (MM/DD)', 'mavlers-cf');
                break;
                
            case 'address':
                $rules['required_subfields'] = array('addr1', 'city', 'state', 'zip');
                $rules['message'] = __('Please enter a complete address', 'mavlers-cf');
                break;
                
            case 'url':
                $rules['pattern'] = '^https?:\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&:/~\+#]*[\w\-\@?^=%&/~\+#])?$';
                $rules['message'] = __('Please enter a valid URL', 'mavlers-cf');
                break;
                
            case 'dropdown':
                if (!empty($options['choices'])) {
                    $rules['allowed_values'] = array_keys($options['choices']);
                    $rules['message'] = __('Please select a valid option', 'mavlers-cf');
                }
                break;
                
            case 'radio':
                if (!empty($options['choices'])) {
                    $rules['allowed_values'] = array_keys($options['choices']);
                    $rules['message'] = __('Please select a valid option', 'mavlers-cf');
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
            'url' => array('url', 'text'),
            'dropdown' => array('select'),
            'radio' => array('radio'),
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
                $formatting['strip_non_numeric'] = true;
                $formatting['allow_international'] = true;
                break;
                
            case 'date':
                $formatting['input_format'] = 'Y-m-d';
                $formatting['output_format'] = 'Y-m-d';
                break;
                
            case 'birthday':
                $formatting['input_format'] = 'm/d';
                $formatting['output_format'] = 'm/d';
                break;
                
            case 'url':
                $formatting['add_protocol'] = true;
                $formatting['default_protocol'] = 'https';
                break;
                
            case 'text':
                $formatting['trim_whitespace'] = true;
                break;
        }
        
        return $formatting;
    }
    
    /**
     * Create a new merge field
     */
    public function create_merge_field($audience_id, $field_data) {
        if (empty($audience_id) || empty($field_data)) {
            return array('success' => false, 'message' => 'Missing required data');
        }
        
        $mailchimp_integration = $this->get_mailchimp_integration();
        if (!$mailchimp_integration || !$mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Validate field data
        $validation_result = $this->validate_field_data($field_data);
        if (!$validation_result['valid']) {
            return array('success' => false, 'message' => $validation_result['message']);
        }
        
        // Prepare API data
        $api_data = $this->prepare_field_data_for_api($field_data);
        
        // Create field via API
        $response = $this->make_mailchimp_request(
            'POST',
            "/lists/{$audience_id}/merge-fields",
            $api_key,
            $dc,
            $api_data
        );
        
        if (!$response || isset($response['status']) && $response['status'] >= 400) {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to create merge field'
            );
        }
        
        // Clear cache
        wp_cache_delete("merge_fields_{$audience_id}", $this->cache_group);
        
        return array(
            'success' => true,
            'message' => 'Merge field created successfully',
            'data' => $response
        );
    }
    
    /**
     * Update an existing merge field
     */
    public function update_merge_field($audience_id, $merge_id, $field_data) {
        if (empty($audience_id) || empty($merge_id) || empty($field_data)) {
            return array('success' => false, 'message' => 'Missing required data');
        }
        
        $mailchimp_integration = $this->get_mailchimp_integration();
        if (!$mailchimp_integration || !$mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Validate field data
        $validation_result = $this->validate_field_data($field_data, $merge_id);
        if (!$validation_result['valid']) {
            return array('success' => false, 'message' => $validation_result['message']);
        }
        
        // Prepare API data
        $api_data = $this->prepare_field_data_for_api($field_data, true);
        
        // Update field via API
        $response = $this->make_mailchimp_request(
            'PATCH',
            "/lists/{$audience_id}/merge-fields/{$merge_id}",
            $api_key,
            $dc,
            $api_data
        );
        
        if (!$response || isset($response['status']) && $response['status'] >= 400) {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to update merge field'
            );
        }
        
        // Clear cache
        wp_cache_delete("merge_fields_{$audience_id}", $this->cache_group);
        
        return array(
            'success' => true,
            'message' => 'Merge field updated successfully',
            'data' => $response
        );
    }
    
    /**
     * Delete a merge field
     */
    public function delete_merge_field($audience_id, $merge_id) {
        if (empty($audience_id) || empty($merge_id)) {
            return array('success' => false, 'message' => 'Missing required data');
        }
        
        $mailchimp_integration = $this->get_mailchimp_integration();
        if (!$mailchimp_integration || !$mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Delete field via API
        $response = $this->make_mailchimp_request(
            'DELETE',
            "/lists/{$audience_id}/merge-fields/{$merge_id}",
            $api_key,
            $dc
        );
        
        if ($response !== '' && isset($response['status']) && $response['status'] >= 400) {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to delete merge field'
            );
        }
        
        // Clear cache
        wp_cache_delete("merge_fields_{$audience_id}", $this->cache_group);
        
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
        
        $mailchimp_integration = $this->get_mailchimp_integration();
        if (!$mailchimp_integration || !$mailchimp_integration->is_globally_connected()) {
            return array('success' => false, 'message' => 'Mailchimp not connected');
        }
        
        $global_settings = $mailchimp_integration->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        // Get interest categories
        $response = $this->make_mailchimp_request(
            'GET',
            "/lists/{$audience_id}/interest-categories?count=50",
            $api_key,
            $dc
        );
        
        if (!$response || isset($response['status']) && $response['status'] >= 400) {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to fetch interest categories'
            );
        }
        
        $categories = $response['categories'] ?? array();
        $processed_categories = array();
        
        // Get interests for each category
        foreach ($categories as $category) {
            $interests_response = $this->make_mailchimp_request(
                'GET',
                "/lists/{$audience_id}/interest-categories/{$category['id']}/interests?count=100",
                $api_key,
                $dc
            );
            
            $interests = array();
            if ($interests_response && !isset($interests_response['status'])) {
                $interests = $interests_response['interests'] ?? array();
            }
            
            $processed_categories[] = array(
                'id' => $category['id'],
                'title' => $category['title'],
                'display_order' => $category['display_order'],
                'type' => $category['type'],
                'interests' => $interests
            );
        }
        
        // Cache the result
        wp_cache_set($cache_key, $processed_categories, $this->cache_group, $this->cache_expiry);
        
        return array('success' => true, 'data' => $processed_categories);
    }
    
    /**
     * Validate field data
     */
    private function validate_field_data($field_data, $merge_id = null) {
        $required_fields = array('name', 'type');
        
        foreach ($required_fields as $field) {
            if (empty($field_data[$field])) {
                return array(
                    'valid' => false,
                    'message' => sprintf(__('Field "%s" is required', 'mavlers-cf'), $field)
                );
            }
        }
        
        // Validate field type
        $allowed_types = array('text', 'number', 'address', 'phone', 'date', 'url', 'imageurl', 'radio', 'dropdown', 'birthday', 'zip');
        if (!in_array($field_data['type'], $allowed_types)) {
            return array(
                'valid' => false,
                'message' => __('Invalid field type', 'mavlers-cf')
            );
        }
        
        // Validate tag for new fields
        if (!$merge_id && !empty($field_data['tag'])) {
            if (!preg_match('/^[A-Z0-9_]+$/', $field_data['tag'])) {
                return array(
                    'valid' => false,
                    'message' => __('Tag must contain only uppercase letters, numbers, and underscores', 'mavlers-cf')
                );
            }
        }
        
        return array('valid' => true);
    }
    
    /**
     * Prepare field data for API
     */
    private function prepare_field_data_for_api($field_data, $is_update = false) {
        $api_data = array(
            'name' => $field_data['name'],
            'type' => $field_data['type']
        );
        
        // Add tag for new fields only
        if (!$is_update && !empty($field_data['tag'])) {
            $api_data['tag'] = strtoupper($field_data['tag']);
        }
        
        // Optional fields
        if (isset($field_data['required'])) {
            $api_data['required'] = (bool) $field_data['required'];
        }
        
        if (!empty($field_data['default_value'])) {
            $api_data['default_value'] = $field_data['default_value'];
        }
        
        if (isset($field_data['public'])) {
            $api_data['public'] = (bool) $field_data['public'];
        }
        
        if (!empty($field_data['help_text'])) {
            $api_data['help_text'] = $field_data['help_text'];
        }
        
        if (isset($field_data['display_order'])) {
            $api_data['display_order'] = (int) $field_data['display_order'];
        }
        
        // Handle options for dropdown and radio fields
        if (in_array($field_data['type'], array('dropdown', 'radio')) && !empty($field_data['choices'])) {
            $api_data['options'] = array(
                'choices' => $field_data['choices']
            );
        }
        
        return $api_data;
    }
    
    /**
     * Apply custom field formatting
     */
    public function apply_field_formatting($value, $field_type, $formatting_rules) {
        if (empty($value) || empty($formatting_rules)) {
            return $value;
        }
        
        switch ($field_type) {
            case 'phone':
                if (!empty($formatting_rules['strip_non_numeric'])) {
                    $value = preg_replace('/[^+0-9]/', '', $value);
                }
                break;
                
            case 'url':
                if (!empty($formatting_rules['add_protocol']) && !preg_match('/^https?:\/\//', $value)) {
                    $protocol = $formatting_rules['default_protocol'] ?? 'https';
                    $value = $protocol . '://' . $value;
                }
                break;
                
            case 'text':
                if (!empty($formatting_rules['trim_whitespace'])) {
                    $value = trim($value);
                }
                break;
                
            case 'date':
                if (!empty($formatting_rules['input_format']) && !empty($formatting_rules['output_format'])) {
                    $date = DateTime::createFromFormat($formatting_rules['input_format'], $value);
                    if ($date) {
                        $value = $date->format($formatting_rules['output_format']);
                    }
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
        
        return true;
    }
    
    /**
     * Get Mailchimp integration instance
     */
    private function get_mailchimp_integration() {
        global $mavlers_cf_integrations;
        return $mavlers_cf_integrations['mailchimp'] ?? null;
    }
    
    /**
     * Extract datacenter from API key
     */
    private function extract_datacenter($api_key) {
        return substr($api_key, strpos($api_key, '-') + 1);
    }
    
    /**
     * Make Mailchimp API request
     */
    private function make_mailchimp_request($method, $endpoint, $api_key, $dc, $data = array()) {
        $url = "https://{$dc}.api.mailchimp.com/3.0{$endpoint}";
        
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
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * AJAX: Get merge fields
     */
    public function ajax_get_merge_fields() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $force_refresh = !empty($_POST['force_refresh']);
        
        $result = $this->get_merge_fields($audience_id, $force_refresh);
        wp_send_json($result);
    }
    
    /**
     * AJAX: Create merge field
     */
    public function ajax_create_merge_field() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
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
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
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
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
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
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        
        // Force refresh cache
        $merge_fields_result = $this->get_merge_fields($audience_id, true);
        $interests_result = $this->get_interest_categories($audience_id, true);
        
        if ($merge_fields_result['success'] && $interests_result['success']) {
            wp_send_json(array(
                'success' => true,
                'message' => __('Custom fields synchronized successfully', 'mavlers-cf'),
                'data' => array(
                    'merge_fields' => $merge_fields_result['data'],
                    'interests' => $interests_result['data']
                )
            ));
        } else {
            wp_send_json(array(
                'success' => false,
                'message' => __('Failed to sync custom fields', 'mavlers-cf')
            ));
        }
    }
    
    /**
     * AJAX: Get interest categories
     */
    public function ajax_get_interest_categories() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        $force_refresh = !empty($_POST['force_refresh']);
        
        $result = $this->get_interest_categories($audience_id, $force_refresh);
        wp_send_json($result);
    }
    
    /**
     * AJAX: Map interests
     */
    public function ajax_map_interests() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $interest_mapping = $_POST['interest_mapping'] ?? array();
        
        // Sanitize mapping data
        $sanitized_mapping = array();
        foreach ($interest_mapping as $form_field => $interests) {
            $form_field = sanitize_text_field($form_field);
            if (is_array($interests)) {
                $sanitized_mapping[$form_field] = array_map('sanitize_text_field', $interests);
            }
        }
        
        // Save interest mapping
        update_option("mavlers_cf_mailchimp_interests_{$form_id}", $sanitized_mapping);
        
        wp_send_json(array(
            'success' => true,
            'message' => __('Interest mapping saved successfully', 'mavlers-cf')
        ));
    }
    
    /**
     * Sanitize field data
     */
    private function sanitize_field_data($field_data) {
        $sanitized = array();
        
        if (!empty($field_data['name'])) {
            $sanitized['name'] = sanitize_text_field($field_data['name']);
        }
        
        if (!empty($field_data['tag'])) {
            $sanitized['tag'] = sanitize_text_field($field_data['tag']);
        }
        
        if (!empty($field_data['type'])) {
            $sanitized['type'] = sanitize_text_field($field_data['type']);
        }
        
        if (isset($field_data['required'])) {
            $sanitized['required'] = (bool) $field_data['required'];
        }
        
        if (!empty($field_data['default_value'])) {
            $sanitized['default_value'] = sanitize_text_field($field_data['default_value']);
        }
        
        if (isset($field_data['public'])) {
            $sanitized['public'] = (bool) $field_data['public'];
        }
        
        if (!empty($field_data['help_text'])) {
            $sanitized['help_text'] = sanitize_textarea_field($field_data['help_text']);
        }
        
        if (isset($field_data['display_order'])) {
            $sanitized['display_order'] = intval($field_data['display_order']);
        }
        
        if (!empty($field_data['choices']) && is_array($field_data['choices'])) {
            $sanitized['choices'] = array();
            foreach ($field_data['choices'] as $value => $label) {
                $sanitized['choices'][sanitize_text_field($value)] = sanitize_text_field($label);
            }
        }
        
        return $sanitized;
    }
} 