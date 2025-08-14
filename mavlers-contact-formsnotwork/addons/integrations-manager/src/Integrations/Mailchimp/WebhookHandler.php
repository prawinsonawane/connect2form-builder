<?php
/**
 * Mailchimp Webhook Handler
 * 
 * Handles real-time webhook events from Mailchimp for synchronization and analytics
 * 
 * @package MavlersCF\Integrations\Mailchimp
 * @since 1.0.0
 */

namespace MavlersCF\Integrations\Mailchimp;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookHandler {
    
    /**
     * Webhook endpoint URL
     */
    private $webhook_endpoint = 'mavlers-cf-mailchimp-webhook';
    
    /**
     * Supported webhook events
     */
    private $supported_events = array(
        'subscribe',
        'unsubscribe',
        'profile',
        'cleaned',
        'upemail',
        'campaign'
    );
    
    /**
     * Security secret for webhook verification
     */
    private $webhook_secret;
    
    /**
     * Analytics manager instance
     */
    private $analytics_manager;
    
    /**
     * Constructor
     */
    public function __construct($analytics_manager = null) {
        $this->analytics_manager = $analytics_manager;
        $this->webhook_secret = $this->get_webhook_secret();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register webhook endpoint
        add_action('init', array($this, 'add_webhook_endpoint'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));
        
        // AJAX handlers for webhook management
        add_action('wp_ajax_mailchimp_register_webhook', array($this, 'ajax_register_webhook'));
        add_action('wp_ajax_mailchimp_unregister_webhook', array($this, 'ajax_unregister_webhook'));
        add_action('wp_ajax_mailchimp_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_mailchimp_get_webhook_status', array($this, 'ajax_get_webhook_status'));
        add_action('wp_ajax_mailchimp_get_webhook_logs', array($this, 'ajax_get_webhook_logs'));
        
        // Webhook processing actions
        add_action('mavlers_cf_mailchimp_webhook_subscribe', array($this, 'process_subscribe_event'), 10, 2);
        add_action('mavlers_cf_mailchimp_webhook_unsubscribe', array($this, 'process_unsubscribe_event'), 10, 2);
        add_action('mavlers_cf_mailchimp_webhook_profile', array($this, 'process_profile_event'), 10, 2);
        add_action('mavlers_cf_mailchimp_webhook_cleaned', array($this, 'process_cleaned_event'), 10, 2);
        add_action('mavlers_cf_mailchimp_webhook_upemail', array($this, 'process_upemail_event'), 10, 2);
        add_action('mavlers_cf_mailchimp_webhook_campaign', array($this, 'process_campaign_event'), 10, 2);
    }
    
    /**
     * Add webhook endpoint
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule(
            '^' . $this->webhook_endpoint . '/?$',
            'index.php?mavlers_cf_mailchimp_webhook=1',
            'top'
        );
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'mavlers_cf_mailchimp_webhook';
            return $vars;
        });
    }
    
    /**
     * Handle webhook request
     */
    public function handle_webhook_request() {
        if (!get_query_var('mavlers_cf_mailchimp_webhook')) {
            return;
        }
        
        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        
        // Get raw post data
        $raw_data = file_get_contents('php://input');
        $data = json_decode($raw_data, true);
        
        // Log webhook request for debugging
        $this->log_webhook_request($raw_data, $_SERVER);
        
        // Verify webhook authenticity
        if (!$this->verify_webhook_signature($raw_data)) {
            http_response_code(401);
            exit('Unauthorized');
        }
        
        // Process webhook data
        if ($data && isset($data['type']) && isset($data['data'])) {
            $this->process_webhook_event($data['type'], $data['data']);
        }
        
        // Return 200 OK to acknowledge receipt
        http_response_code(200);
        exit('OK');
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($raw_data) {
        // Check if signature verification is enabled
        if (!$this->webhook_secret) {
            return true; // Skip verification if no secret is set
        }
        
        $signature = $_SERVER['HTTP_X_MC_SIGNATURE'] ?? '';
        if (!$signature) {
            return false;
        }
        
        // Calculate expected signature
        $expected_signature = base64_encode(hash_hmac('sha1', $raw_data, $this->webhook_secret, true));
        
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Process webhook event
     */
    private function process_webhook_event($event_type, $event_data) {
        // Validate event type
        if (!in_array($event_type, $this->supported_events)) {
            $this->log_webhook_error("Unsupported event type: {$event_type}", $event_data);
            return;
        }
        
        try {
            // Trigger specific event action
            do_action("mavlers_cf_mailchimp_webhook_{$event_type}", $event_data, $event_type);
            
            // Track analytics if manager is available
            if ($this->analytics_manager) {
                $this->analytics_manager->track_webhook_event($event_type, $event_data);
            }
            
            // Log successful processing
            $this->log_webhook_success($event_type, $event_data);
            
        } catch (Exception $e) {
            $this->log_webhook_error("Error processing {$event_type} event: " . $e->getMessage(), $event_data);
        }
    }
    
    /**
     * Process subscribe event
     */
    public function process_subscribe_event($event_data, $event_type) {
        $email = $event_data['email'] ?? '';
        $list_id = $event_data['list_id'] ?? '';
        
        if (!$email || !$list_id) {
            return;
        }
        
        // Update local subscriber status
        $this->update_subscriber_status($email, $list_id, 'subscribed', $event_data);
        
        // Trigger custom actions
        do_action('mavlers_cf_mailchimp_subscriber_added', $email, $list_id, $event_data);
    }
    
    /**
     * Process unsubscribe event
     */
    public function process_unsubscribe_event($event_data, $event_type) {
        $email = $event_data['email'] ?? '';
        $list_id = $event_data['list_id'] ?? '';
        $reason = $event_data['reason'] ?? 'unknown';
        
        if (!$email || !$list_id) {
            return;
        }
        
        // Update local subscriber status
        $this->update_subscriber_status($email, $list_id, 'unsubscribed', $event_data);
        
        // Trigger custom actions
        do_action('mavlers_cf_mailchimp_subscriber_removed', $email, $list_id, $reason, $event_data);
    }
    
    /**
     * Process profile update event
     */
    public function process_profile_event($event_data, $event_type) {
        $email = $event_data['email'] ?? '';
        $list_id = $event_data['list_id'] ?? '';
        
        if (!$email || !$list_id) {
            return;
        }
        
        // Update local subscriber data
        $this->update_subscriber_profile($email, $list_id, $event_data);
        
        // Trigger custom actions
        do_action('mavlers_cf_mailchimp_subscriber_updated', $email, $list_id, $event_data);
    }
    
    /**
     * Process cleaned email event
     */
    public function process_cleaned_event($event_data, $event_type) {
        $email = $event_data['email'] ?? '';
        $list_id = $event_data['list_id'] ?? '';
        $reason = $event_data['reason'] ?? 'unknown';
        
        if (!$email || !$list_id) {
            return;
        }
        
        // Update local subscriber status
        $this->update_subscriber_status($email, $list_id, 'cleaned', $event_data);
        
        // Trigger custom actions
        do_action('mavlers_cf_mailchimp_subscriber_cleaned', $email, $list_id, $reason, $event_data);
    }
    
    /**
     * Process email change event
     */
    public function process_upemail_event($event_data, $event_type) {
        $old_email = $event_data['old_email'] ?? '';
        $new_email = $event_data['new_email'] ?? '';
        $list_id = $event_data['list_id'] ?? '';
        
        if (!$old_email || !$new_email || !$list_id) {
            return;
        }
        
        // Update local records
        $this->update_subscriber_email($old_email, $new_email, $list_id, $event_data);
        
        // Trigger custom actions
        do_action('mavlers_cf_mailchimp_subscriber_email_changed', $old_email, $new_email, $list_id, $event_data);
    }
    
    /**
     * Process campaign event
     */
    public function process_campaign_event($event_data, $event_type) {
        $campaign_id = $event_data['id'] ?? '';
        $list_id = $event_data['list_id'] ?? '';
        
        if (!$campaign_id || !$list_id) {
            return;
        }
        
        // Store campaign activity
        $this->store_campaign_activity($campaign_id, $list_id, $event_data);
        
        // Trigger custom actions
        do_action('mavlers_cf_mailchimp_campaign_activity', $campaign_id, $list_id, $event_data);
    }
    
    /**
     * Update subscriber status
     */
    private function update_subscriber_status($email, $list_id, $status, $event_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_subscribers';
        
        // Check if subscriber record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE email = %s AND list_id = %s",
            $email,
            $list_id
        ));
        
        $data = array(
            'email' => $email,
            'list_id' => $list_id,
            'status' => $status,
            'last_updated' => current_time('mysql'),
            'webhook_data' => wp_json_encode($event_data)
        );
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing->id)
            );
        } else {
            // Insert new record
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table_name, $data);
        }
    }
    
    /**
     * Update subscriber profile
     */
    private function update_subscriber_profile($email, $list_id, $event_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_subscribers';
        
        $merge_fields = wp_json_encode($event_data['merges'] ?? array());
        
        $wpdb->update(
            $table_name,
            array(
                'merge_fields' => $merge_fields,
                'last_updated' => current_time('mysql'),
                'webhook_data' => wp_json_encode($event_data)
            ),
            array(
                'email' => $email,
                'list_id' => $list_id
            )
        );
    }
    
    /**
     * Update subscriber email
     */
    private function update_subscriber_email($old_email, $new_email, $list_id, $event_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_subscribers';
        
        $wpdb->update(
            $table_name,
            array(
                'email' => $new_email,
                'last_updated' => current_time('mysql'),
                'webhook_data' => wp_json_encode($event_data)
            ),
            array(
                'email' => $old_email,
                'list_id' => $list_id
            )
        );
    }
    
    /**
     * Store campaign activity
     */
    private function store_campaign_activity($campaign_id, $list_id, $event_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_campaigns';
        
        $data = array(
            'campaign_id' => $campaign_id,
            'list_id' => $list_id,
            'activity_type' => $event_data['type'] ?? 'unknown',
            'activity_data' => wp_json_encode($event_data),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_name, $data);
    }
    
    /**
     * Register webhook with Mailchimp
     */
    public function register_webhook($api_key, $list_id, $events = null) {
        if (!$events) {
            $events = $this->supported_events;
        }
        
        $dc = $this->extract_datacenter($api_key);
        $webhook_url = home_url($this->webhook_endpoint);
        
        // Prepare webhook data
        $webhook_data = array(
            'url' => $webhook_url,
            'events' => array_fill_keys($events, true),
            'sources' => array(
                'user' => true,
                'admin' => true,
                'api' => true
            )
        );
        
        // Add secret if available
        if ($this->webhook_secret) {
            $webhook_data['secret'] = $this->webhook_secret;
        }
        
        // Make API request
        $response = $this->make_mailchimp_request(
            'POST',
            "/lists/{$list_id}/webhooks",
            $api_key,
            $dc,
            $webhook_data
        );
        
        if (isset($response['id'])) {
            // Store webhook ID for later reference
            $this->save_webhook_id($list_id, $response['id']);
            return array('success' => true, 'webhook_id' => $response['id']);
        } else {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to register webhook'
            );
        }
    }
    
    /**
     * Unregister webhook from Mailchimp
     */
    public function unregister_webhook($api_key, $list_id) {
        $webhook_id = $this->get_webhook_id($list_id);
        
        if (!$webhook_id) {
            return array('success' => false, 'message' => 'No webhook found for this list');
        }
        
        $dc = $this->extract_datacenter($api_key);
        
        // Make API request
        $response = $this->make_mailchimp_request(
            'DELETE',
            "/lists/{$list_id}/webhooks/{$webhook_id}",
            $api_key,
            $dc
        );
        
        if (!isset($response['status']) || $response['status'] < 400) {
            // Remove stored webhook ID
            $this->remove_webhook_id($list_id);
            return array('success' => true);
        } else {
            return array(
                'success' => false,
                'message' => $response['detail'] ?? 'Failed to unregister webhook'
            );
        }
    }
    
    /**
     * Get webhook status
     */
    public function get_webhook_status($api_key, $list_id) {
        $webhook_id = $this->get_webhook_id($list_id);
        
        if (!$webhook_id) {
            return array('registered' => false);
        }
        
        $dc = $this->extract_datacenter($api_key);
        
        // Get webhook details from Mailchimp
        $response = $this->make_mailchimp_request(
            'GET',
            "/lists/{$list_id}/webhooks/{$webhook_id}",
            $api_key,
            $dc
        );
        
        if (isset($response['id'])) {
            return array(
                'registered' => true,
                'webhook_id' => $response['id'],
                'url' => $response['url'],
                'events' => $response['events'],
                'sources' => $response['sources']
            );
        } else {
            // Webhook not found, clean up stored ID
            $this->remove_webhook_id($list_id);
            return array('registered' => false);
        }
    }
    
    /**
     * Get webhook secret
     */
    private function get_webhook_secret() {
        $secret = get_option('mavlers_cf_mailchimp_webhook_secret');
        
        if (!$secret) {
            // Generate new secret
            $secret = wp_generate_password(32, false);
            update_option('mavlers_cf_mailchimp_webhook_secret', $secret);
        }
        
        return $secret;
    }
    
    /**
     * Save webhook ID
     */
    private function save_webhook_id($list_id, $webhook_id) {
        $webhook_ids = get_option('mavlers_cf_mailchimp_webhook_ids', array());
        $webhook_ids[$list_id] = $webhook_id;
        update_option('mavlers_cf_mailchimp_webhook_ids', $webhook_ids);
    }
    
    /**
     * Get webhook ID
     */
    private function get_webhook_id($list_id) {
        $webhook_ids = get_option('mavlers_cf_mailchimp_webhook_ids', array());
        return $webhook_ids[$list_id] ?? null;
    }
    
    /**
     * Remove webhook ID
     */
    private function remove_webhook_id($list_id) {
        $webhook_ids = get_option('mavlers_cf_mailchimp_webhook_ids', array());
        unset($webhook_ids[$list_id]);
        update_option('mavlers_cf_mailchimp_webhook_ids', $webhook_ids);
    }
    
    /**
     * Extract datacenter from API key
     */
    private function extract_datacenter($api_key) {
        $parts = explode('-', $api_key);
        return end($parts);
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
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array('status' => 500, 'detail' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Log webhook request
     */
    private function log_webhook_request($raw_data, $server_data) {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'method' => $server_data['REQUEST_METHOD'] ?? '',
            'headers' => $this->get_relevant_headers($server_data),
            'body' => $raw_data,
            'ip' => $server_data['REMOTE_ADDR'] ?? ''
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Mailchimp Webhook Request: ' . wp_json_encode($log_data));
        }
    }
    
    /**
     * Log webhook success
     */
    private function log_webhook_success($event_type, $event_data) {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'status' => 'success',
            'email' => $event_data['email'] ?? '',
            'list_id' => $event_data['list_id'] ?? ''
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Mailchimp Webhook Success: ' . wp_json_encode($log_data));
        }
    }
    
    /**
     * Log webhook error
     */
    private function log_webhook_error($message, $event_data = array()) {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'status' => 'error',
            'message' => $message,
            'event_data' => $event_data
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Mailchimp Webhook Error: ' . wp_json_encode($log_data));
        }
    }
    
    /**
     * Get relevant headers for logging
     */
    private function get_relevant_headers($server_data) {
        $relevant_headers = array();
        $header_keys = array('HTTP_X_MC_SIGNATURE', 'HTTP_USER_AGENT', 'HTTP_X_FORWARDED_FOR');
        
        foreach ($header_keys as $key) {
            if (isset($server_data[$key])) {
                $relevant_headers[$key] = $server_data[$key];
            }
        }
        
        return $relevant_headers;
    }
    
    /**
     * AJAX: Register webhook
     */
    public function ajax_register_webhook() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $list_id = sanitize_text_field($_POST['list_id'] ?? '');
        $events = $_POST['events'] ?? $this->supported_events;
        
        if (!$api_key || !$list_id) {
            wp_send_json_error('API key and list ID are required');
        }
        
        $result = $this->register_webhook($api_key, $list_id, $events);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Unregister webhook
     */
    public function ajax_unregister_webhook() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $list_id = sanitize_text_field($_POST['list_id'] ?? '');
        
        if (!$api_key || !$list_id) {
            wp_send_json_error('API key and list ID are required');
        }
        
        $result = $this->unregister_webhook($api_key, $list_id);
        
        if ($result['success']) {
            wp_send_json_success('Webhook unregistered successfully');
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Simulate a webhook event for testing
        $test_data = array(
            'type' => 'subscribe',
            'data' => array(
                'email' => 'test@example.com',
                'list_id' => 'test123',
                'merges' => array('FNAME' => 'Test', 'LNAME' => 'User')
            )
        );
        
        $this->process_webhook_event($test_data['type'], $test_data['data']);
        
        wp_send_json_success('Test webhook processed successfully');
    }
    
    /**
     * AJAX: Get webhook status
     */
    public function ajax_get_webhook_status() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $list_id = sanitize_text_field($_POST['list_id'] ?? '');
        
        if (!$api_key || !$list_id) {
            wp_send_json_error('API key and list ID are required');
        }
        
        $status = $this->get_webhook_status($api_key, $list_id);
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX: Get webhook logs
     */
    public function ajax_get_webhook_logs() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // This would fetch logs from a log table or file
        // For now, return sample data
        $logs = array(
            array(
                'timestamp' => current_time('mysql'),
                'event_type' => 'subscribe',
                'status' => 'success',
                'email' => 'user@example.com',
                'list_id' => 'abc123'
            )
        );
        
        wp_send_json_success($logs);
    }
} 