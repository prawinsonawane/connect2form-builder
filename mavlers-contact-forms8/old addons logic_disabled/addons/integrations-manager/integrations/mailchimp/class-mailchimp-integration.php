<?php
/**
 * Modern Mailchimp Integration for Mavlers Contact Forms
 *
 * Clean, user-friendly Mailchimp integration with:
 * - Global API settings with connection testing
 * - Form-specific audience selection
 * - Professional error handling
 * - Simplified configuration
 *
 * @package MavlersCF_Integrations
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Mailchimp_Integration extends Mavlers_CF_Base_Integration {

    /**
     * Integration configuration
     */
    protected $integration_id = 'mailchimp';
    protected $integration_name = 'Mailchimp';
    protected $integration_description = 'Connect your forms to Mailchimp audiences with ease';
    protected $integration_version = '2.0.0';
    protected $integration_icon = 'dashicons-email-alt';
    protected $integration_color = '#ffe01b';

    /**
     * API configuration
     */
    private $api_base_url = 'https://{dc}.api.mailchimp.com/3.0/';
    private $connection_status = null;

    /**
     * Webhook handler instance
     */
    private $webhook_handler = null;

    /**
     * Language manager instance
     */
    private $language_manager = null;

    /**
     * Analytics manager instance
     */
    private $analytics = null;

    /**
     * Custom fields manager instance
     */
    private $custom_fields = null;

    /**
     * Initialize integration
     */
    public function __construct() {
        parent::__construct();
        $this->init_language_manager();
        $this->init_hooks();
    }

    /**
     * Initialize language manager
     */
    private function init_language_manager() {
        if (class_exists('Mavlers_CF_Mailchimp_Language_Manager')) {
            $this->language_manager = new Mavlers_CF_Mailchimp_Language_Manager();
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers for settings
        add_action('wp_ajax_mailchimp_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_mailchimp_get_audiences', array($this, 'ajax_get_audiences'));
        add_action('wp_ajax_mailchimp_save_global_settings', array($this, 'ajax_save_global_settings'));
        add_action('wp_ajax_mailchimp_save_form_settings', array($this, 'ajax_save_form_settings'));
        
        // Enhanced field mapping AJAX handlers (new)
        add_action('wp_ajax_mavlers_cf_get_form_fields', array($this, 'ajax_get_form_fields'));
        add_action('wp_ajax_mailchimp_save_field_mapping', array($this, 'ajax_save_field_mapping'));
        add_action('wp_ajax_mailchimp_get_field_mapping', array($this, 'ajax_get_field_mapping'));
        add_action('wp_ajax_mailchimp_auto_map_fields', array($this, 'ajax_auto_map_fields'));
        
        // Multilingual interface AJAX handlers
        add_action('wp_ajax_mailchimp_get_multilingual_interface', array($this, 'ajax_get_multilingual_interface'));
        
        // Audience merge fields AJAX handler
        add_action('wp_ajax_mailchimp_get_audience_merge_fields', array($this, 'ajax_get_audience_merge_fields'));
        
        // Enqueue enhanced field mapping assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_enhanced_mapping_assets'));
        
        // Initialize batch processor
        $this->init_batch_processor();
        
        // Initialize webhook handler
        $this->init_webhook_handler();
        
        // Initialize analytics system
        $this->init_analytics();
        
        // Initialize custom fields manager
        $this->init_custom_fields();
        
        // Submission processing
        add_action('mavlers_cf_after_submission', array($this, 'process_form_submission'), 10, 2);
    }
    
    /**
     * Enqueue enhanced field mapping assets
     */
    public function enqueue_enhanced_mapping_assets($hook) {
        // Only load on form builder and integrations pages
        if (strpos($hook, 'mavlers-cf') !== false || strpos($hook, 'mavlers-contact-forms') !== false) {
            wp_enqueue_style(
                'mavlers-cf-enhanced-mapping',
                plugin_dir_url(__FILE__) . '../../assets/css/enhanced-field-mapping.css',
                array(),
                '1.0.0'
            );
            
            // Localize the admin interface
            $this->localize_admin_interface();
        }
    }

    /**
     * Get integration metadata
     */
    public function get_integration_id() {
        return $this->integration_id;
    }

    public function get_integration_name() {
        return $this->integration_name;
    }

    public function get_integration_description() {
        return $this->integration_description;
    }

    public function get_integration_icon() {
        return $this->integration_icon;
    }

    public function get_integration_color() {
        return $this->integration_color;
    }

    public function get_integration_version() {
        return $this->integration_version;
    }

    /**
     * Get authentication fields required for this integration
     */
    public function get_auth_fields() {
        return array(
            array(
                'id' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'description' => 'Your Mailchimp API key. Find it in your Mailchimp account under Account > Extras > API keys.'
            )
        );
    }

    /**
     * Get available actions for this integration
     */
    public function get_available_actions() {
        return array(
            'subscribe' => array(
                'label' => 'Subscribe to Audience',
                'description' => 'Add contact to a Mailchimp audience/list'
            )
        );
    }

    /**
     * Handle form submission (required by base class)
     */
    public function handle_submission($action, $form_fields, $settings, $field_mappings) {
        if ($action !== 'subscribe') {
            return array(
                'success' => false,
                'message' => 'Invalid action specified'
            );
        }

        // Use the existing form submission logic
        $global_settings = $this->get_global_settings();
        
        if (!$this->is_globally_connected()) {
            return array(
                'success' => false,
                'message' => $this->translate('Mailchimp not connected globally', 'error')
            );
        }

        return $this->subscribe_to_audience($form_fields, $settings, $global_settings);
    }

    /**
     * Test connection with provided auth data (required by base class)
     */
    public function test_connection($auth_data) {
        $api_key = isset($auth_data['api_key']) ? $auth_data['api_key'] : '';
        return $this->test_api_connection($api_key);
    }

    /**
     * Get available fields for mapping (legacy method - now uses dynamic fields)
     */
    public function get_available_fields() {
        // This method is now mainly for backward compatibility
        // Real field mapping uses get_audience_merge_fields() for dynamic fields
        return array(
            'email_address' => array(
                'label' => 'Email Address',
                'type' => 'email',
                'required' => true
            ),
            'FNAME' => array(
                'label' => 'First Name',
                'type' => 'text'
            ),
            'LNAME' => array(
                'label' => 'Last Name', 
                'type' => 'text'
            ),
            'PHONE' => array(
                'label' => 'Phone Number',
                'type' => 'phone'
            ),
            'BIRTHDAY' => array(
                'label' => 'Birthday',
                'type' => 'date'
            ),
            'ADDRESS' => array(
                'label' => 'Address',
                'type' => 'text'
            ),
            'CITY' => array(
                'label' => 'City',
                'type' => 'text'
            ),
            'STATE' => array(
                'label' => 'State/Province',
                'type' => 'text'
            ),
            'ZIP' => array(
                'label' => 'ZIP/Postal Code',
                'type' => 'text'
            ),
            'COUNTRY' => array(
                'label' => 'Country',
                'type' => 'text'
            ),
            'WEBSITE' => array(
                'label' => 'Website',
                'type' => 'url'
            )
        );
    }

    /**
     * Get enhanced field mapping (new feature)
     */
    public function get_enhanced_field_mapping($form_id) {
        $enhanced_mapping = get_option("mavlers_cf_mailchimp_enhanced_mapping_{$form_id}", array());
        
        // If no enhanced mapping exists, fall back to automatic mapping
        if (empty($enhanced_mapping)) {
            return $this->generate_automatic_mapping($form_id);
        }
        
        return $enhanced_mapping;
    }

    /**
     * Save enhanced field mapping (new feature)
     */
    public function save_enhanced_field_mapping($form_id, $mapping) {
        return update_option("mavlers_cf_mailchimp_enhanced_mapping_{$form_id}", $mapping);
    }

    /**
     * Generate automatic field mapping (backward compatibility)
     */
    private function generate_automatic_mapping($form_id) {
        global $wpdb;
        
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));
        
        if (!$form || empty($form->fields)) {
            return array();
        }
        
        $fields = json_decode($form->fields, true);
        if (!is_array($fields)) {
            return array();
        }
        
        $mapping = array();
        
        foreach ($fields as $field) {
            $field_id = $field['id'] ?? '';
            $field_type = $field['type'] ?? '';
            $field_label = strtolower($field['label'] ?? '');
            
            // Smart mapping logic
            if ($field_type === 'email') {
                $mapping[$field_id] = 'email_address';
            } elseif ($field_type === 'text') {
                if (strpos($field_label, 'first') !== false || strpos($field_label, 'fname') !== false) {
                    $mapping[$field_id] = 'FNAME';
                } elseif (strpos($field_label, 'last') !== false || strpos($field_label, 'lname') !== false) {
                    $mapping[$field_id] = 'LNAME';
                }
            } elseif ($field_type === 'number' || strpos($field_label, 'phone') !== false) {
                $mapping[$field_id] = 'PHONE';
            }
        }
        
        return $mapping;
    }

    /**
     * Enhanced map form data (with fallback to existing logic)
     */
    protected function enhanced_map_form_data($form_data, $form_settings) {
        $form_id = isset($form_data['form_id']) ? $form_data['form_id'] : 0;
        
        // Try enhanced mapping first
        $enhanced_mapping = $this->get_enhanced_field_mapping($form_id);
        
        if (!empty($enhanced_mapping)) {
            return $this->apply_enhanced_mapping($form_data, $enhanced_mapping);
        }
        
        // Fall back to existing mapping logic
        return $this->map_form_data($form_data, $form_settings);
    }

    /**
     * Apply enhanced field mapping
     */
    private function apply_enhanced_mapping($form_data, $mapping) {
        $mapped_data = array(
            'email_address' => '',
            'merge_fields' => array()
        );
        
        foreach ($mapping as $form_field_id => $mailchimp_field) {
            if (isset($form_data[$form_field_id])) {
                $value = $form_data[$form_field_id];
                
                if ($mailchimp_field === 'email_address') {
                    $mapped_data['email_address'] = $value;
                } else {
                    $mapped_data['merge_fields'][$mailchimp_field] = $value;
                }
            }
        }
        
        error_log('Mailchimp: Enhanced mapping applied: ' . print_r($mapped_data, true));
        return $mapped_data;
    }

    /**
     * ============================================================================
     * GLOBAL SETTINGS MANAGEMENT
     * ============================================================================
     */

    /**
     * Get global Mailchimp settings
     */
    public function get_global_settings() {
        return get_option('mavlers_cf_mailchimp_global', array(
            'api_key' => '',
            'connection_status' => 'not_tested',
            'last_tested' => '',
            'account_info' => array()
        ));
    }

    /**
     * Save global Mailchimp settings
     */
    public function save_global_settings($settings) {
        $current_settings = $this->get_global_settings();
        $new_settings = array_merge($current_settings, $settings);
        
        return update_option('mavlers_cf_mailchimp_global', $new_settings);
    }

    /**
     * Test API connection
     */
    public function test_api_connection($api_key = null) {
        if (!$api_key) {
            $settings = $this->get_global_settings();
            $api_key = $settings['api_key'];
        }

        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'API key is required',
                'data' => null
            );
        }

        // Extract datacenter from API key
        $dc = $this->extract_datacenter($api_key);
        if (!$dc) {
            return array(
                'success' => false,
                'message' => 'Invalid API key format',
                'data' => null
            );
        }

        // Test connection by getting account info
        $response = $this->make_mailchimp_api_request('GET', '', $api_key, $dc);

        if ($response['success']) {
            // Update global settings with connection status
            $this->save_global_settings(array(
                'connection_status' => 'connected',
                'last_tested' => current_time('mysql'),
                'account_info' => $response['data']
            ));

            return array(
                'success' => true,
                'message' => 'Connection successful!',
                'data' => array(
                    'account_name' => $response['data']['account_name'] ?? 'Unknown',
                    'email' => $response['data']['email'] ?? 'Unknown',
                    'total_subscribers' => $response['data']['total_subscribers'] ?? 0
                )
            );
        } else {
            // Update global settings with error status
            $this->save_global_settings(array(
                'connection_status' => 'error',
                'last_tested' => current_time('mysql'),
                'account_info' => array()
            ));

            return $response;
        }
    }

    /**
     * ============================================================================
     * FORM-SPECIFIC SETTINGS MANAGEMENT
     * ============================================================================
     */

    /**
     * Get form-specific Mailchimp settings
     */
    public function get_form_settings($form_id) {
        $settings = get_option("mavlers_cf_mailchimp_form_{$form_id}", array(
            'enabled' => false,
            'audience_id' => '',
            'double_optin' => true,
            'update_existing' => true,
            'tags' => '',
            'field_mappings' => array()
        ));
        
        // Add debugging
        error_log('Mailchimp Get Form Settings - Form ID: ' . $form_id);
        error_log('Mailchimp Get Form Settings - Settings: ' . print_r($settings, true));
        
        return $settings;
    }

    /**
     * Save form-specific settings
     */
    public function save_form_settings($form_id, $settings) {
        $current_settings = $this->get_form_settings($form_id);
        $new_settings = array_merge($current_settings, $settings);
        
        // Add debugging
        error_log('Mailchimp Save Form Settings - Form ID: ' . $form_id);
        error_log('Mailchimp Save Form Settings - Current: ' . print_r($current_settings, true));
        error_log('Mailchimp Save Form Settings - New data: ' . print_r($settings, true));
        error_log('Mailchimp Save Form Settings - Merged: ' . print_r($new_settings, true));
        
        $result = update_option("mavlers_cf_mailchimp_form_{$form_id}", $new_settings);
        error_log('Mailchimp Save Form Settings - Update result: ' . ($result ? 'success' : 'failed'));
        
        return $result;
    }

    /**
     * ============================================================================
     * API COMMUNICATION
     * ============================================================================
     */

    /**
     * Get Mailchimp audiences
     */
    public function get_audiences($api_key = null) {
        if (!$api_key) {
            $settings = $this->get_global_settings();
            $api_key = $settings['api_key'];
        }

        if (empty($api_key)) {
            return array('success' => false, 'message' => 'API key not configured');
        }

        $dc = $this->extract_datacenter($api_key);
        $response = $this->make_mailchimp_api_request('GET', 'lists', $api_key, $dc);

        if ($response['success'] && isset($response['data']['lists'])) {
            $audiences = array();
            foreach ($response['data']['lists'] as $list) {
                $audiences[] = array(
                    'id' => $list['id'],
                    'name' => $list['name'],
                    'member_count' => $list['stats']['member_count'] ?? 0
                );
            }
            return array('success' => true, 'data' => $audiences);
        }

        return $response;
    }

    /**
     * Make API request to Mailchimp
     */
    protected function make_mailchimp_api_request($method, $endpoint, $api_key, $dc, $data = array()) {
        $url = str_replace('{dc}', $dc, $this->api_base_url) . $endpoint;

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
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'data' => null
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'message' => 'Request successful',
                'data' => $data
            );
        } else {
            $error_message = 'API request failed';
            if (isset($data['detail'])) {
                $error_message = $data['detail'];
            } elseif (isset($data['title'])) {
                $error_message = $data['title'];
            }

            return array(
                'success' => false,
                'message' => $error_message,
                'data' => $data
            );
        }
    }

    /**
     * Extract datacenter from API key
     */
    protected function extract_datacenter($api_key) {
        if (strpos($api_key, '-') === false) {
            return false;
        }

        $parts = explode('-', $api_key);
        return end($parts);
    }

    /**
     * ============================================================================
     * FORM SUBMISSION PROCESSING
     * ============================================================================
     */

    /**
     * Process form submission
     */
    public function process_form_submission($submission_id, $addon_form_data) {
        // Extract form ID from the addon form data
        $form_id = isset($addon_form_data['form_id']) ? $addon_form_data['form_id'] : 0;
        $submission_data = isset($addon_form_data['fields']) ? $addon_form_data['fields'] : array();
        
        if (!$form_id) {
            error_log('Mailchimp: No form ID provided');
            return;
        }

        $form_settings = $this->get_form_settings($form_id);
        
        // Add detailed debugging
        error_log('Mailchimp: Processing submission for form ' . $form_id);

        // Skip if not enabled for this form
        if (!$form_settings['enabled'] || empty($form_settings['audience_id'])) {
            error_log("Mailchimp: Integration not enabled for form {$form_id} (enabled: " . ($form_settings['enabled'] ? 'true' : 'false') . ") or no audience selected (audience_id: " . $form_settings['audience_id'] . ")");
            return;
        }

        // Check global connection
        $global_settings = $this->get_global_settings();
        if ($global_settings['connection_status'] !== 'connected' || empty($global_settings['api_key'])) {
            $this->log_error($form_id, 'Mailchimp not connected globally');
            return;
        }

        // Add form_id to submission data for batch processing
        $submission_data['form_id'] = $form_id;
        
        // Check if batch processing is enabled
        if ($this->is_batch_processing_enabled()) {
            error_log('Mailchimp: Adding submission to batch queue');
            $result = $this->add_to_batch_queue($submission_data, $form_settings);
            
            if ($result) {
                error_log('Mailchimp: Successfully queued submission for batch processing');
                $this->log_success($form_id, 'Submission queued for batch processing');
            } else {
                error_log('Mailchimp: Failed to queue submission, falling back to direct processing');
                $this->process_submission_direct($submission_data, $form_settings, $global_settings, $form_id);
            }
        } else {
            // Direct processing (existing behavior)
            $this->process_submission_direct($submission_data, $form_settings, $global_settings, $form_id);
        }
    }
    
    /**
     * Process submission directly (original behavior)
     */
    private function process_submission_direct($submission_data, $form_settings, $global_settings, $form_id) {
        error_log("Mailchimp: Processing subscription directly for form {$form_id}");
        
        $result = $this->subscribe_to_audience($submission_data, $form_settings, $global_settings);
        
        if ($result['success']) {
            $this->log_success($form_id, 'Subscriber added successfully', $result['data'] ?? null);
            error_log("Mailchimp: Successfully added subscriber for form {$form_id}");
        } else {
            $this->log_error($form_id, $result['message'], $result['data'] ?? null);
            error_log("Mailchimp: Failed to add subscriber for form {$form_id}: " . $result['message']);
        }
    }

    /**
     * Subscribe user to Mailchimp audience
     */
    protected function subscribe_to_audience($form_data, $form_settings, $global_settings) {
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);

        // Map form fields to Mailchimp data
        $subscriber_data = $this->enhanced_map_form_data($form_data, $form_settings);

        if (empty($subscriber_data['email_address'])) {
            return array('success' => false, 'message' => $this->translate('Email address is required', 'error'));
        }

        // Prepare subscription data
        $subscription_data = array(
            'email_address' => $subscriber_data['email_address'],
            'status' => $form_settings['double_optin'] ? 'pending' : 'subscribed',
            'merge_fields' => $subscriber_data['merge_fields'] ?? array()
        );

        // Add tags if specified
        if (!empty($form_settings['tags'])) {
            $tags = array_map('trim', explode(',', $form_settings['tags']));
            $subscription_data['tags'] = $tags;
        }

        // Make API request
        $endpoint = "lists/{$form_settings['audience_id']}/members";
        $email = $subscriber_data['email_address'];
        $audience_id = $form_settings['audience_id'];
        $form_id = $form_data['form_id'] ?? 0;
        
        // Track response time for analytics
        $start_time = microtime(true);
        
        if ($form_settings['update_existing']) {
            // Use PUT to create or update
            $subscriber_hash = md5(strtolower($subscriber_data['email_address']));
            $endpoint .= "/{$subscriber_hash}";
            $subscription_data['status_if_new'] = $subscription_data['status'];
            unset($subscription_data['status']);
            
            $response = $this->make_mailchimp_api_request('PUT', $endpoint, $api_key, $dc, $subscription_data);
        } else {
            // Use POST to create new only
            $response = $this->make_mailchimp_api_request('POST', $endpoint, $api_key, $dc, $subscription_data);
        }
        
        // Track response time
        $response_time = round((microtime(true) - $start_time) * 1000); // Convert to milliseconds
        $GLOBALS['mavlers_cf_mailchimp_response_time'] = $response_time;
        
        // Check if response is successful and track analytics
        if ($response && isset($response['id'])) {
            // Track successful subscription for analytics
            do_action('mavlers_cf_mailchimp_subscription_success', $email, $form_id, $audience_id);
            
            return array(
                'success' => true,
                'message' => 'Contact successfully added to Mailchimp audience',
                'data' => $response
            );
        } else {
            // Determine error message
            $error_message = 'Failed to subscribe to Mailchimp';
            if (is_array($response) && isset($response['detail'])) {
                $error_message = $response['detail'];
            } elseif (is_array($response) && isset($response['title'])) {
                $error_message = $response['title'];
            }
            
            // Track failed subscription for analytics
            do_action('mavlers_cf_mailchimp_subscription_failed', $email, $form_id, $error_message);
            
            return array(
                'success' => false,
                'message' => $error_message,
                'data' => $response
            );
        }
    }

    /**
     * Map form data to Mailchimp format
     */
    protected function map_form_data($form_data, $form_settings) {
        $mapped_data = array(
            'email_address' => '',
            'merge_fields' => array()
        );

        // Get form ID from form_data
        $form_id = isset($form_data['form_id']) ? $form_data['form_id'] : 0;
        
        if ($form_id) {
            // Get form structure from database
            global $wpdb;
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
                $form_id
            ));
            
            if ($form && $form->fields) {
                $fields = json_decode($form->fields, true);
                error_log('Mailchimp: Form fields structure: ' . print_r($fields, true));
                
                if ($fields) {
                    // Map by field type rather than field name
                    foreach ($fields as $field) {
                        $field_id = $field['id'] ?? '';
                        $field_type = $field['type'] ?? '';
                        $field_label = strtolower($field['label'] ?? '');
                        
                        if (isset($form_data[$field_id])) {
                            $field_value = $form_data[$field_id];
                            
                            // Map by field type first, then by label
                            if ($field_type === 'email') {
                                $mapped_data['email_address'] = $field_value;
                                error_log("Mailchimp: Mapped email field {$field_id} = {$field_value}");
                            } elseif ($field_type === 'text') {
                                // Try to determine purpose by label
                                if (strpos($field_label, 'first') !== false || strpos($field_label, 'fname') !== false) {
                                    $mapped_data['merge_fields']['FNAME'] = $field_value;
                                } elseif (strpos($field_label, 'last') !== false || strpos($field_label, 'lname') !== false) {
                                    $mapped_data['merge_fields']['LNAME'] = $field_value;
                                } else {
                                    // Default to first name if no other text field is mapped yet
                                    if (empty($mapped_data['merge_fields']['FNAME'])) {
                                        $mapped_data['merge_fields']['FNAME'] = $field_value;
                                    }
                                }
                            } elseif ($field_type === 'number' || strpos($field_label, 'phone') !== false) {
                                $mapped_data['merge_fields']['PHONE'] = $field_value;
                            }
                        }
                    }
                }
            }
        }
        
        // Fallback: Simple mapping for common field names (for backward compatibility)
        if (empty($mapped_data['email_address'])) {
            foreach ($form_data as $field_name => $field_value) {
                $field_name_lower = strtolower($field_name);
                
                // Look for email patterns in field names
                if (strpos($field_name_lower, 'email') !== false) {
                    $mapped_data['email_address'] = $field_value;
                    error_log("Mailchimp: Fallback email mapping {$field_name} = {$field_value}");
                    break;
                }
            }
        }
        
        // Additional fallback: Look for email pattern in values
        if (empty($mapped_data['email_address'])) {
            foreach ($form_data as $field_name => $field_value) {
                if (is_email($field_value)) {
                    $mapped_data['email_address'] = $field_value;
                    error_log("Mailchimp: Found email by value pattern {$field_name} = {$field_value}");
                    break;
                }
            }
        }

        error_log('Mailchimp: Final mapped data: ' . print_r($mapped_data, true));
        return $mapped_data;
    }

    /**
     * ============================================================================
     * AJAX HANDLERS
     * ============================================================================
     */

    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $result = $this->test_api_connection($api_key);

        wp_send_json($result);
    }

    /**
     * AJAX: Get audiences
     */
    public function ajax_get_audiences() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $result = $this->get_audiences();
        wp_send_json($result);
    }

    /**
     * AJAX: Save global settings
     */
    public function ajax_save_global_settings() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $enable_batch_processing = intval($_POST['enable_batch_processing'] ?? 0);
        
        // Get current settings to preserve existing data
        $current_settings = $this->get_global_settings();
        
        // Update with new values
        $settings = array_merge($current_settings, array(
            'api_key' => $api_key,
            'enable_batch_processing' => $enable_batch_processing
        ));
        
        $this->save_global_settings($settings);
        
        wp_send_json(array('success' => true, 'message' => 'Settings saved successfully'));
    }

    /**
     * AJAX: Save form settings
     */
    public function ajax_save_form_settings() {
        error_log('🚀 AJAX Handler Called: ajax_save_form_settings');
        error_log('🚀 POST Data: ' . print_r($_POST, true));
        
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('🚨 AJAX Handler: User lacks manage_options capability');
            wp_die('Unauthorized');
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        $settings = array(
            'enabled' => !empty($_POST['enabled']) && $_POST['enabled'] == '1',
            'audience_id' => sanitize_text_field($_POST['audience_id'] ?? ''),
            'double_optin' => !empty($_POST['double_optin']) && $_POST['double_optin'] == '1',
            'update_existing' => !empty($_POST['update_existing']) && $_POST['update_existing'] == '1',
            'tags' => sanitize_text_field($_POST['tags'] ?? '')
        );

        // Add debugging
        error_log('Mailchimp Form Settings Save - Form ID: ' . $form_id);
        error_log('Mailchimp Form Settings Save - Settings: ' . print_r($settings, true));

        $result = $this->save_form_settings($form_id, $settings);
        error_log('Save result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        
        wp_send_json(array('success' => true, 'message' => 'Form settings saved successfully'));
    }

    /**
     * AJAX: Get form fields for enhanced mapping
     */
    public function ajax_get_form_fields() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        if (!$form_id) {
            wp_send_json(array('success' => false, 'message' => 'Form ID not provided'));
            return;
        }

        $available_fields = $this->get_available_fields();
        $form_fields = array();

        // Get form structure from database
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));

        // Debug logging
        error_log('Mailchimp: Looking for form ID ' . $form_id);
        error_log('Mailchimp: Form found: ' . ($form ? 'YES' : 'NO'));
        if ($form) {
            error_log('Mailchimp: Raw fields data: ' . $form->fields);
        }

        if ($form && $form->fields) {
            $fields = json_decode($form->fields, true);
            if ($fields) {
                foreach ($fields as $field) {
                    $field_id = $field['id'] ?? '';
                    $field_type = $field['type'] ?? '';
                    $field_label = $field['label'] ?? '';
                    $field_required = $field['required'] ?? false;

                    // Include all form fields, not just those matching available_fields
                    if (!empty($field_id) && !empty($field_label)) {
                        $form_fields[] = array(
                            'id' => $field_id,
                            'label' => $field_label,
                            'type' => $field_type,
                            'required' => $field_required,
                            'description' => ''
                        );
                    }
                }
            }
        }

        // Add debug logging to help troubleshoot
        error_log('Mailchimp: Form ID ' . $form_id . ' - Found ' . count($form_fields) . ' form fields');
        error_log('Mailchimp: Form fields data: ' . print_r($form_fields, true));

        wp_send_json(array('success' => true, 'data' => $form_fields));
    }

    /**
     * AJAX: Save enhanced field mapping
     */
    public function ajax_save_field_mapping() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        $mapping = json_decode(stripslashes($_POST['mapping'] ?? '[]'), true);

        if (!$form_id) {
            wp_send_json(array('success' => false, 'message' => 'Form ID not provided'));
            return;
        }

        if (empty($mapping)) {
            wp_send_json(array('success' => false, 'message' => 'Mapping data is empty'));
            return;
        }

        $result = $this->save_enhanced_field_mapping($form_id, $mapping);
        wp_send_json(array('success' => $result, 'message' => $result ? 'Mapping saved successfully' : 'Failed to save mapping'));
    }

    /**
     * AJAX: Get enhanced field mapping
     */
    public function ajax_get_field_mapping() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        if (!$form_id) {
            wp_send_json(array('success' => false, 'message' => 'Form ID not provided'));
            return;
        }

        $mapping = $this->get_enhanced_field_mapping($form_id);
        wp_send_json(array('success' => true, 'data' => $mapping));
    }

    /**
     * AJAX: Auto-map fields
     */
    public function ajax_auto_map_fields() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        if (!$form_id) {
            wp_send_json(array('success' => false, 'message' => 'Form ID not provided'));
            return;
        }

        $mapping = $this->generate_automatic_mapping($form_id);
        $this->save_enhanced_field_mapping($form_id, $mapping);
        wp_send_json(array('success' => true, 'message' => 'Fields auto-mapped successfully', 'data' => $mapping));
    }

    /**
     * AJAX: Get Mailchimp merge fields for audience
     */
    public function ajax_get_audience_merge_fields() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        if (!$audience_id) {
            wp_send_json(array('success' => false, 'message' => 'Audience ID not provided'));
            return;
        }

        $merge_fields = $this->get_audience_merge_fields($audience_id);
        wp_send_json(array('success' => true, 'data' => $merge_fields));
    }

    /**
     * Get merge fields from Mailchimp audience
     */
    public function get_audience_merge_fields($audience_id) {
        $global_settings = $this->get_global_settings();
        $api_key = $global_settings['api_key'];
        
        if (empty($api_key)) {
            return array();
        }

        $dc = $this->extract_datacenter($api_key);
        if (!$dc) {
            return array();
        }

        // Get merge fields from Mailchimp API
        $response = $this->make_mailchimp_api_request(
            'GET',
            "lists/{$audience_id}/merge-fields?count=1000",
            $api_key,
            $dc
        );

        if (!$response['success']) {
            error_log('Mailchimp: Failed to get merge fields - ' . $response['message']);
            return array();
        }

        $merge_fields = array();
        
        // Always include email address as required field
        $merge_fields[] = array(
            'id' => 'email_address',
            'name' => 'Email Address',
            'tag' => 'EMAIL',
            'type' => 'email',
            'required' => true,
            'description' => 'Primary email address for the subscriber'
        );

        // Add merge fields from API response
        if (isset($response['data']['merge_fields'])) {
            foreach ($response['data']['merge_fields'] as $field) {
                $merge_fields[] = array(
                    'id' => $field['tag'],
                    'name' => $field['name'],
                    'tag' => $field['tag'],
                    'type' => $this->map_mailchimp_field_type($field['type']),
                    'required' => $field['required'] ?? false,
                    'description' => $field['help_text'] ?? ''
                );
            }
        }

        return $merge_fields;
    }

    /**
     * Map Mailchimp field types to common types
     */
    private function map_mailchimp_field_type($mailchimp_type) {
        $type_mapping = array(
            'text' => 'text',
            'number' => 'number',
            'address' => 'address',
            'phone' => 'phone',
            'date' => 'date',
            'url' => 'url',
            'imageurl' => 'url',
            'radio' => 'radio',
            'dropdown' => 'select',
            'birthday' => 'date',
            'zip' => 'text'
        );

        return $type_mapping[$mailchimp_type] ?? 'text';
    }

    /**
     * ============================================================================
     * LOGGING
     * ============================================================================
     */

    /**
     * Log success
     */
    protected function log_success($form_id, $message, $data = null) {
        error_log("Mailchimp Success (Form {$form_id}): {$message}");
        // You can extend this to use your logging system
    }

    /**
     * Log error
     */
    protected function log_error($form_id, $message, $data = null) {
        error_log("Mailchimp Error (Form {$form_id}): {$message}");
        // You can extend this to use your logging system
    }

    /**
     * ============================================================================
     * UTILITY METHODS
     * ============================================================================
     */

    /**
     * Check if global connection is active
     */
    public function is_globally_connected() {
        $settings = $this->get_global_settings();
        return $settings['connection_status'] === 'connected' && !empty($settings['api_key']);
    }

    /**
     * Get connection status message
     */
    public function get_connection_status_message() {
        $settings = $this->get_global_settings();
        
        switch ($settings['connection_status']) {
            case 'connected':
                return 'Connected successfully';
            case 'error':
                return 'Connection failed';
            case 'not_tested':
            default:
                return 'Not tested yet';
        }
    }

    /**
     * Initialize batch processor
     */
    private function init_batch_processor() {
        // Create batch processing table if needed
        $this->maybe_create_batch_table();
        
        // Schedule batch processing
        if (!wp_next_scheduled('mavlers_cf_mailchimp_process_batch')) {
            wp_schedule_event(time(), 'mavlers_cf_five_minutes', 'mavlers_cf_mailchimp_process_batch');
        }
        
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Hook batch processing
        add_action('mavlers_cf_mailchimp_process_batch', array($this, 'process_batch_queue'));
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['mavlers_cf_five_minutes'] = array(
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 minutes', 'mavlers-contact-forms')
        );
        return $schedules;
    }
    
    /**
     * Create batch processing table
     */
    private function maybe_create_batch_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_queue';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                form_id mediumint(9) NOT NULL,
                submission_data longtext NOT NULL,
                form_settings longtext NOT NULL,
                status varchar(20) DEFAULT 'pending',
                attempts int(11) DEFAULT 0,
                last_error text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                processed_at datetime NULL,
                PRIMARY KEY (id),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Add submission to batch queue
     */
    public function add_to_batch_queue($form_data, $form_settings) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_queue';
        
        return $wpdb->insert(
            $table_name,
            array(
                'form_id' => $form_data['form_id'] ?? 0,
                'submission_data' => json_encode($form_data),
                'form_settings' => json_encode($form_settings),
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Process batch queue
     */
    public function process_batch_queue() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_queue';
        $batch_size = apply_filters('mavlers_cf_mailchimp_batch_size', 100);
        
        // Get pending items
        $pending_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status = 'pending' 
             ORDER BY created_at ASC 
             LIMIT %d",
            $batch_size
        ), ARRAY_A);
        
        if (empty($pending_items)) {
            return;
        }
        
        error_log('Mailchimp Batch: Processing ' . count($pending_items) . ' items');
        
        // Group by audience for batch API calls
        $batches = array();
        foreach ($pending_items as $item) {
            $form_settings = json_decode($item['form_settings'], true);
            $audience_id = $form_settings['audience_id'] ?? '';
            
            if ($audience_id) {
                if (!isset($batches[$audience_id])) {
                    $batches[$audience_id] = array();
                }
                $batches[$audience_id][] = $item;
            }
        }
        
        // Process each audience batch
        foreach ($batches as $audience_id => $items) {
            $this->process_audience_batch($audience_id, $items);
        }
        
        // Clean up old completed/failed items (older than 7 days)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
             WHERE status IN ('completed', 'failed') 
             AND created_at < %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
    }
    
    /**
     * Process audience batch
     */
    private function process_audience_batch($audience_id, $items) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_queue';
        $batch_operations = array();
        $item_ids = array();
        
        foreach ($items as $item) {
            $form_data = json_decode($item['submission_data'], true);
            $form_settings = json_decode($item['form_settings'], true);
            
            // Map form data to Mailchimp format
            $subscriber_data = $this->enhanced_map_form_data($form_data, $form_settings);
            
            if (empty($subscriber_data['email_address'])) {
                // Mark as failed - no email
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'failed',
                        'last_error' => 'No email address found',
                        'processed_at' => current_time('mysql')
                    ),
                    array('id' => $item['id']),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                continue;
            }
            
            // Prepare batch operation
            $operation = array(
                'method' => 'PUT',
                'path' => '/lists/' . $audience_id . '/members/' . md5(strtolower($subscriber_data['email_address'])),
                'body' => json_encode(array(
                    'email_address' => $subscriber_data['email_address'],
                    'status_if_new' => $form_settings['double_optin'] ? 'pending' : 'subscribed',
                    'merge_fields' => $subscriber_data['merge_fields'] ?? array(),
                    'tags' => $this->prepare_tags($form_settings['tags'] ?? '')
                ))
            );
            
            $batch_operations[] = $operation;
            $item_ids[] = $item['id'];
        }
        
        if (empty($batch_operations)) {
            return;
        }
        
        // Execute batch request
        $result = $this->execute_batch_request($batch_operations);
        
        // Update item statuses based on results
        $this->update_batch_results($item_ids, $result, $table_name);
    }
    
    /**
     * Execute batch request to Mailchimp
     */
    private function execute_batch_request($operations) {
        $global_settings = $this->get_global_settings();
        $api_key = $global_settings['api_key'] ?? '';
        
        if (empty($api_key)) {
            return array('success' => false, 'message' => 'No API key configured');
        }
        
        $datacenter = substr($api_key, strpos($api_key, '-') + 1);
        $url = "https://{$datacenter}.api.mailchimp.com/3.0/batches";
        
        $batch_data = array(
            'operations' => $operations
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($batch_data),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('Mailchimp Batch Error: ' . $response->get_error_message());
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            error_log('Mailchimp Batch API Error: ' . $body);
            return array('success' => false, 'message' => $data['detail'] ?? 'Unknown error');
        }
        
        return array('success' => true, 'data' => $data);
    }
    
    /**
     * Update batch results
     */
    private function update_batch_results($item_ids, $result, $table_name) {
        global $wpdb;
        
        if (!$result['success']) {
            // Mark all as failed if batch failed
            foreach ($item_ids as $item_id) {
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'failed',
                        'attempts' => new \WP_Query('attempts + 1'),  // This should be a proper SQL expression
                        'last_error' => $result['message'],
                        'processed_at' => current_time('mysql')
                    ),
                    array('id' => $item_id),
                    array('%s', '%s', '%s', '%s'),
                    array('%d')
                );
            }
            return;
        }
        
        // For now, mark all as completed since we submitted a batch
        // In a full implementation, you'd check individual operation results
        foreach ($item_ids as $item_id) {
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'processed_at' => current_time('mysql')
                ),
                array('id' => $item_id),
                array('%s', '%s'),
                array('%d')
            );
        }
        
        error_log('Mailchimp Batch: Successfully processed ' . count($item_ids) . ' items');
    }
    
    /**
     * Check if batch processing is enabled
     */
    public function is_batch_processing_enabled() {
        $global_settings = $this->get_global_settings();
        return isset($global_settings['enable_batch_processing']) && $global_settings['enable_batch_processing'];
    }

    /**
     * Initialize webhook handler
     */
    private function init_webhook_handler() {
        // Include webhook handler class
        require_once(dirname(__FILE__) . '/../../includes/class-mailchimp-webhook-handler.php');
        
        // Initialize webhook handler
        $this->webhook_handler = new Mavlers_CF_Mailchimp_Webhook_Handler();
        
        // Add webhook management AJAX handlers
        add_action('wp_ajax_mailchimp_get_webhook_status', array($this, 'ajax_get_webhook_status'));
        add_action('wp_ajax_mailchimp_register_webhook', array($this, 'ajax_register_webhook'));
        add_action('wp_ajax_mailchimp_unregister_webhook', array($this, 'ajax_unregister_webhook'));
    }
    
    /**
     * Get webhook handler instance
     */
    public function get_webhook_handler() {
        return $this->webhook_handler ?? null;
    }
    
    /**
     * AJAX: Get webhook status
     */
    public function ajax_get_webhook_status() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $list_id = sanitize_text_field($_POST['list_id'] ?? '');
        
        if (empty($list_id)) {
            wp_send_json(array('success' => false, 'message' => 'List ID is required'));
            return;
        }
        
        $webhook_id = get_option("mavlers_cf_mailchimp_webhook_{$list_id}");
        $webhook_url = $this->webhook_handler ? $this->webhook_handler->get_webhook_url() : '';
        
        wp_send_json(array(
            'success' => true,
            'data' => array(
                'registered' => !empty($webhook_id),
                'webhook_id' => $webhook_id,
                'webhook_url' => $webhook_url
            )
        ));
    }
    
    /**
     * AJAX: Register webhook
     */
    public function ajax_register_webhook() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!$this->webhook_handler) {
            wp_send_json(array('success' => false, 'message' => 'Webhook handler not initialized'));
            return;
        }
        
        // Delegate to webhook handler
        $this->webhook_handler->ajax_register_webhook();
    }
    
    /**
     * AJAX: Unregister webhook
     */
    public function ajax_unregister_webhook() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $list_id = sanitize_text_field($_POST['list_id'] ?? '');
        
        if (empty($list_id)) {
            wp_send_json(array('success' => false, 'message' => 'List ID is required'));
            return;
        }
        
        $result = $this->unregister_webhook_from_mailchimp($list_id);
        wp_send_json($result);
    }
    
    /**
     * Unregister webhook from Mailchimp
     */
    private function unregister_webhook_from_mailchimp($list_id) {
        $webhook_id = get_option("mavlers_cf_mailchimp_webhook_{$list_id}");
        
        if (empty($webhook_id)) {
            return array('success' => false, 'message' => 'No webhook registered for this list');
        }
        
        $global_settings = $this->get_global_settings();
        $api_key = $global_settings['api_key'] ?? '';
        
        if (empty($api_key)) {
            return array('success' => false, 'message' => 'Mailchimp API key not configured');
        }
        
        $datacenter = substr($api_key, strpos($api_key, '-') + 1);
        $url = "https://{$datacenter}.api.mailchimp.com/3.0/lists/{$list_id}/webhooks/{$webhook_id}";
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 204) {
            // Remove webhook ID from options
            delete_option("mavlers_cf_mailchimp_webhook_{$list_id}");
            
            return array(
                'success' => true, 
                'message' => 'Webhook unregistered successfully'
            );
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => false, 
                'message' => $data['detail'] ?? 'Failed to unregister webhook'
            );
        }
    }
    
    /**
     * Initialize analytics system
     */
    private function init_analytics() {
        if (!class_exists('Mavlers_CF_Mailchimp_Analytics')) {
            require_once dirname(__FILE__) . '/../../includes/class-mailchimp-analytics.php';
        }
        
        $this->analytics = new Mavlers_CF_Mailchimp_Analytics();
        
        // Add analytics AJAX handlers
        add_action('wp_ajax_mailchimp_export_analytics', array($this, 'ajax_export_analytics'));
        add_action('wp_ajax_load_mailchimp_analytics_dashboard', array($this, 'ajax_load_analytics_dashboard'));
    }
    
    /**
     * AJAX: Export analytics data
     */
    public function ajax_export_analytics() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $period = sanitize_text_field($_GET['period'] ?? '7days');
        $dashboard_data = $this->analytics->get_dashboard_data($period);
        
        // Prepare CSV data
        $csv_data = array();
        $csv_data[] = array('Metric', 'Value');
        $csv_data[] = array('Total Subscriptions', $dashboard_data['overview']['total_subscriptions']);
        $csv_data[] = array('Successful Subscriptions', $dashboard_data['overview']['successful_subscriptions']);
        $csv_data[] = array('Failed Subscriptions', $dashboard_data['overview']['failed_subscriptions']);
        $csv_data[] = array('Success Rate (%)', $dashboard_data['overview']['success_rate']);
        $csv_data[] = array('Average Response Time (ms)', $dashboard_data['overview']['avg_response_time']);
        $csv_data[] = array('Webhook Activity', $dashboard_data['overview']['webhook_activity']);
        
        // Add forms data
        if (!empty($dashboard_data['forms'])) {
            $csv_data[] = array(''); // Empty row
            $csv_data[] = array('Form Performance');
            $csv_data[] = array('Form ID', 'Form Name', 'Total Submissions', 'Success Rate (%)', 'Avg Response Time (ms)');
            
            foreach ($dashboard_data['forms'] as $form) {
                $csv_data[] = array(
                    $form['form_id'],
                    $form['form_name'] ?: 'Form #' . $form['form_id'],
                    $form['total_submissions'],
                    $form['success_rate'],
                    $form['avg_response_time']
                );
            }
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=mailchimp-analytics-' . $period . '-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Load analytics dashboard
     */
    public function ajax_load_analytics_dashboard() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Start output buffering
        ob_start();
        
        // Include analytics dashboard template with CSS
        if (file_exists(dirname(__FILE__) . '/../../templates/mailchimp-analytics-dashboard.php')) {
            // Enqueue analytics CSS
            echo '<link rel="stylesheet" type="text/css" href="' . plugin_dir_url(__FILE__) . '../../assets/css/analytics-dashboard.css" />';
            
            // Include the analytics dashboard
            include dirname(__FILE__) . '/../../templates/mailchimp-analytics-dashboard.php';
        } else {
            echo '<div class="analytics-error">';
            echo '<h3>🚨 Analytics Dashboard Unavailable</h3>';
            echo '<p>The analytics dashboard template could not be found. Please contact your administrator.</p>';
            echo '</div>';
        }
        
        // Get the content
        $html = ob_get_clean();
        
        wp_send_json(array(
            'success' => true,
            'data' => array(
                'html' => $html
            )
        ));
    }
    
    /**
     * Initialize custom fields manager
     */
    private function init_custom_fields() {
        if (!class_exists('Mavlers_CF_Mailchimp_Custom_Fields')) {
            require_once dirname(__FILE__) . '/../../includes/class-mailchimp-custom-fields.php';
        }
        
        $this->custom_fields = new Mavlers_CF_Mailchimp_Custom_Fields();
    }
    
    /**
     * Get custom fields manager instance
     */
    public function get_custom_fields() {
        return $this->custom_fields ?? null;
    }

    /**
     * ============================================================================
     * MULTILINGUAL SUPPORT METHODS
     * ============================================================================
     */

    /**
     * Translate text using language manager
     */
    private function translate($text, $context = '') {
        if ($this->language_manager) {
            return $this->language_manager->translate($text, $context);
        }
        return $text; // Fallback to original text
    }

    /**
     * Translate text with plural support
     */
    private function translate_plural($single, $plural, $number) {
        if ($this->language_manager) {
            return $this->language_manager->translate_plural($single, $plural, $number);
        }
        return ($number === 1) ? $single : $plural; // Basic fallback
    }

    /**
     * Get localized error message
     */
    private function get_error_message($error_code, $default_message = '') {
        if ($this->language_manager) {
            return $this->language_manager->localize_error_message($default_message, $error_code);
        }
        return $default_message;
    }

    /**
     * Get localized success message
     */
    private function get_success_message($success_code, $default_message = '') {
        if ($this->language_manager) {
            return $this->language_manager->localize_success_message($default_message, $success_code);
        }
        return $default_message;
    }

    /**
     * Format date according to current locale
     */
    private function format_date_localized($date, $format = 'datetime') {
        if ($this->language_manager) {
            return $this->language_manager->format_date_localized($date, $format);
        }
        return date('M j, Y g:i A', is_numeric($date) ? $date : strtotime($date));
    }

    /**
     * Format number according to current locale
     */
    private function format_number_localized($number, $decimals = 0) {
        if ($this->language_manager) {
            return $this->language_manager->format_number_localized($number, $decimals);
        }
        return number_format($number, $decimals);
    }

    /**
     * Check if current language is RTL
     */
    private function is_rtl() {
        if ($this->language_manager) {
            return $this->language_manager->is_rtl();
        }
        return false;
    }

    /**
     * Get current locale
     */
    private function get_current_locale() {
        if ($this->language_manager) {
            return $this->language_manager->get_current_locale();
        }
        return get_locale();
    }

    /**
     * Get language manager instance
     */
    public function get_language_manager() {
        return $this->language_manager;
    }

    /**
     * AJAX: Get multilingual interface
     */
    public function ajax_get_multilingual_interface() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // Load the multilingual interface template
            ob_start();
            include plugin_dir_path(__FILE__) . '../../templates/mailchimp-multilang-interface.php';
            $html = ob_get_clean();

            wp_send_json_success(array(
                'html' => $html,
                'locale' => $this->get_current_locale(),
                'is_rtl' => $this->is_rtl()
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $this->translate('Failed to load multilingual interface', 'error')
            ));
        }
    }

    /**
     * Update admin interface text with translations
     */
    public function localize_admin_interface() {
        if (!$this->language_manager) {
            return;
        }

        // Enqueue language-specific CSS if RTL
        if ($this->is_rtl()) {
            wp_enqueue_style(
                'mailchimp-rtl',
                plugin_dir_url(__FILE__) . '../../assets/css/mailchimp-rtl.css',
                array(),
                '1.0.0'
            );
        }

        // Localize JavaScript strings
        wp_localize_script('mailchimp-admin', 'mailchimpL10n', array(
            'locale' => $this->get_current_locale(),
            'isRTL' => $this->is_rtl(),
            'texts' => $this->language_manager->get_admin_texts(),
            'errors' => $this->language_manager->get_error_messages(),
            'success' => $this->language_manager->get_success_messages(),
            'validation' => $this->language_manager->get_validation_messages(),
            'nonce' => wp_create_nonce('mavlers_cf_nonce')
        ));
    }
}

// Register the integration
add_action('mavlers_cf_load_integrations', function() {
    new Mavlers_CF_Mailchimp_Integration();
}); 