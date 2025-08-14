<?php
/**
 * Base Integration Class
 * 
 * Abstract class that all integrations must extend
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Mavlers_CF_Base_Integration {
    
    protected $integration_id;
    protected $integration_name;
    protected $integration_description;
    protected $integration_version;
    protected $integration_icon;
    protected $integration_color;
    protected $auth_data;
    protected $api_client;
    protected $logger;
    
    public function __construct() {
        if (class_exists('Mavlers_CF_API_Client')) {
            $this->api_client = new Mavlers_CF_API_Client();
        } else {
            $this->api_client = null;
        }
        
        if (class_exists('Mavlers_CF_Integration_Logger')) {
            $this->logger = new Mavlers_CF_Integration_Logger();
        } else {
            $this->logger = null;
        }
        
        $this->init();
    }
    
    /**
     * Initialize the integration
     * Override this method in child classes for custom initialization
     */
    protected function init() {
        // Override in child classes
    }
    
    /**
     * Get unique integration identifier
     * @return string
     */
    abstract public function get_integration_id();
    
    /**
     * Get integration display name
     * @return string
     */
    abstract public function get_integration_name();
    
    /**
     * Get integration description
     * @return string
     */
    abstract public function get_integration_description();
    
    /**
     * Get integration version
     * @return string
     */
    abstract public function get_integration_version();
    
    /**
     * Get authentication fields required for this integration
     * @return array
     */
    abstract public function get_auth_fields();
    
    /**
     * Get available actions for this integration
     * @return array
     */
    abstract public function get_available_actions();
    
    /**
     * Handle form submission
     * @param string $action
     * @param array $form_fields
     * @param array $settings
     * @param array $field_mappings
     * @return array
     */
    abstract public function handle_submission($action, $form_fields, $settings, $field_mappings);
    
    /**
     * Test connection with provided auth data
     * @param array $auth_data
     * @return array
     */
    abstract public function test_connection($auth_data);
    
    /**
     * Get available fields for mapping
     * @return array
     */
    abstract public function get_available_fields();
    
    /**
     * Get integration icon (Dashicon class or URL)
     * @return string
     */
    public function get_integration_icon() {
        return $this->integration_icon ?? 'dashicons-admin-plugins';
    }
    
    /**
     * Get integration color (hex code)
     * @return string
     */
    public function get_integration_color() {
        return $this->integration_color ?? '#0073aa';
    }
    
    /**
     * Whether this integration supports OAuth
     * @return bool
     */
    public function supports_oauth() {
        return false;
    }
    
    /**
     * Get OAuth authorization URL
     * @param array $params
     * @return string
     */
    public function get_oauth_url($params = array()) {
        if (!$this->supports_oauth()) {
            throw new Exception('OAuth not supported by this integration');
        }
        return '';
    }
    
    /**
     * Handle OAuth callback
     * @param array $params
     * @return array
     */
    public function handle_oauth_callback($params = array()) {
        if (!$this->supports_oauth()) {
            throw new Exception('OAuth not supported by this integration');
        }
        return array('success' => false, 'message' => 'OAuth not implemented');
    }
    
    /**
     * Set authentication data
     * @param array $auth_data
     */
    public function set_auth_data($auth_data) {
        $this->auth_data = $auth_data;
    }
    
    /**
     * Get authentication data
     * @return array
     */
    public function get_auth_data() {
        if (!$this->auth_data) {
            // Try to load from database
            if (class_exists('Mavlers_CF_Addon_Registry')) {
                $registry = new Mavlers_CF_Addon_Registry();
                $this->auth_data = $registry->get_integration_auth_data($this->get_integration_id());
            } else {
                $this->auth_data = array();
            }
        }
        return $this->auth_data ?? array();
    }
    
    /**
     * Validate authentication data
     * @param array $auth_data
     * @return array
     */
    public function validate_auth_data($auth_data) {
        $errors = array();
        $auth_fields = $this->get_auth_fields();
        
        foreach ($auth_fields as $field) {
            if ($field['required'] && empty($auth_data[$field['id']])) {
                $errors[] = sprintf(__('%s is required', 'mavlers-contact-forms'), $field['label']);
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Get form field value by ID
     * @param array $form_fields
     * @param string $field_id
     * @return mixed
     */
    protected function get_field_value($form_fields, $field_id) {
        return isset($form_fields[$field_id]) ? $form_fields[$field_id] : null;
    }
    
    /**
     * Map form fields to integration fields
     * @param array $form_fields
     * @param array $field_mappings
     * @return array
     */
    protected function map_fields($form_fields, $field_mappings) {
        $mapped_data = array();
        
        foreach ($field_mappings as $integration_field => $form_field_id) {
            if (!empty($form_field_id) && isset($form_fields[$form_field_id])) {
                $mapped_data[$integration_field] = $form_fields[$form_field_id];
            }
        }
        
        return $mapped_data;
    }
    
    /**
     * Make API request
     * @param string $url
     * @param string $method
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return array
     */
    protected function make_api_request($url, $method = 'GET', $data = array(), $headers = array(), $options = array()) {
        if (!$this->api_client) {
            return array(
                'success' => false,
                'message' => 'API client not initialized',
                'code' => 0
            );
        }
        return $this->api_client->request($method, $url, $data, $headers, $options);
    }
    
    /**
     * Log integration activity
     * @param string $level
     * @param string $message
     * @param array $context
     */
    protected function log($level, $message, $context = array()) {
        if ($this->logger) {
            $context['integration_id'] = $this->get_integration_id();
            $this->logger->log($level, $message, $context);
        } else {
            // Fallback to error_log if logger is not available
            error_log(sprintf(
                '[Mavlers CF %s] %s: %s - %s',
                $this->get_integration_id(),
                strtoupper($level),
                $message,
                json_encode($context)
            ));
        }
    }
    
    /**
     * Sanitize field value based on field type
     * @param mixed $value
     * @param string $field_type
     * @return mixed
     */
    protected function sanitize_field_value($value, $field_type = 'text') {
        switch ($field_type) {
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;
            case 'phone':
                return preg_replace('/[^0-9+\-\s\(\)]/', '', $value);
            case 'date':
                $timestamp = strtotime($value);
                return $timestamp ? date('Y-m-d', $timestamp) : '';
            case 'datetime':
                $timestamp = strtotime($value);
                return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Format error message for display
     * @param string|array $error
     * @return string
     */
    protected function format_error($error) {
        if (is_array($error)) {
            return implode(', ', $error);
        }
        return $error;
    }
    
    /**
     * Check rate limiting
     * @return bool
     */
    protected function check_rate_limit() {
        $transient_key = 'mavlers_cf_rate_limit_' . $this->get_integration_id();
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            set_transient($transient_key, 1, MINUTE_IN_SECONDS);
            return true;
        }
        
        if ($requests >= $this->get_rate_limit()) {
            return false;
        }
        
        set_transient($transient_key, $requests + 1, MINUTE_IN_SECONDS);
        return true;
    }
    
    /**
     * Get rate limit per minute
     * @return int
     */
    protected function get_rate_limit() {
        return 60; // Default 60 requests per minute
    }
    
    /**
     * Handle API errors
     * @param array $response
     * @return array
     */
    protected function handle_api_error($response) {
        $error_message = 'Unknown API error';
        
        if (isset($response['body'])) {
            $body = json_decode($response['body'], true);
            if (isset($body['error'])) {
                $error_message = is_array($body['error']) 
                    ? $body['error']['message'] ?? $error_message
                    : $body['error'];
            } elseif (isset($body['message'])) {
                $error_message = $body['message'];
            }
        }
        
        if (isset($response['response']['code'])) {
            $error_message = sprintf(
                'API Error %d: %s',
                $response['response']['code'],
                $error_message
            );
        }
        
        return array(
            'success' => false,
            'message' => $error_message,
            'code' => $response['response']['code'] ?? 0
        );
    }
    
    /**
     * Prepare data for API request
     * @param array $data
     * @return array
     */
    protected function prepare_api_data($data) {
        // Remove empty values and sanitize
        $prepared = array();
        foreach ($data as $key => $value) {
            if (!empty($value) || $value === 0 || $value === '0') {
                $prepared[$key] = $value;
            }
        }
        return $prepared;
    }
    
    /**
     * Get webhook URL for this integration
     * @return string
     */
    public function get_webhook_url() {
        return add_query_arg(
            array(
                'mavlers_cf_webhook' => $this->get_integration_id(),
                'action' => 'webhook'
            ),
            home_url('/')
        );
    }
    
    /**
     * Handle webhook requests
     * @param array $data
     * @return array
     */
    public function handle_webhook($data) {
        return array(
            'success' => true,
            'message' => 'Webhook received'
        );
    }
    
    /**
     * Get default settings for this integration
     * @return array
     */
    public function get_default_settings() {
        return array(
            'enabled' => false,
            'action' => 'default'
        );
    }
    
    /**
     * Validate settings
     * @param array $settings
     * @return array
     */
    public function validate_settings($settings) {
        return array(
            'valid' => true,
            'errors' => array()
        );
    }
} 