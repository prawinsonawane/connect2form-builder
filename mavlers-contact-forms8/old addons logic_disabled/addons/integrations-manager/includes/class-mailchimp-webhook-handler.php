<?php
/**
 * Mailchimp Webhook Handler
 * 
 * Handles incoming webhooks from Mailchimp for bi-directional sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Mailchimp_Webhook_Handler {
    
    private $webhook_endpoint = 'mavlers-cf-mailchimp-webhook';
    private $webhook_secret;
    
    public function __construct() {
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
        
        // Admin hooks for webhook management
        add_action('wp_ajax_mailchimp_register_webhook', array($this, 'ajax_register_webhook'));
        add_action('wp_ajax_mailchimp_unregister_webhook', array($this, 'ajax_unregister_webhook'));
        add_action('wp_ajax_mailchimp_test_webhook', array($this, 'ajax_test_webhook'));
    }
    
    /**
     * Add webhook rewrite endpoint
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule(
            '^' . $this->webhook_endpoint . '/?$',
            'index.php?' . $this->webhook_endpoint . '=1',
            'top'
        );
        add_rewrite_tag('%' . $this->webhook_endpoint . '%', '([^&]+)');
        
        // Flush rewrite rules if needed
        if (!get_option('mavlers_cf_mailchimp_webhook_endpoint_added')) {
            flush_rewrite_rules();
            update_option('mavlers_cf_mailchimp_webhook_endpoint_added', true);
        }
    }
    
    /**
     * Handle incoming webhook requests
     */
    public function handle_webhook_request() {
        if (!get_query_var($this->webhook_endpoint)) {
            return;
        }
        
        // Get raw POST data
        $raw_data = file_get_contents('php://input');
        $headers = $this->get_request_headers();
        
        // Log webhook request for debugging
        error_log('Mailchimp Webhook: Received request');
        error_log('Headers: ' . print_r($headers, true));
        error_log('Raw data: ' . $raw_data);
        
        // Verify webhook signature
        if (!$this->verify_webhook_signature($raw_data, $headers)) {
            error_log('Mailchimp Webhook: Invalid signature');
            http_response_code(401);
            exit('Unauthorized');
        }
        
        // Parse webhook data
        $webhook_data = json_decode($raw_data, true);
        if (!$webhook_data) {
            error_log('Mailchimp Webhook: Invalid JSON data');
            http_response_code(400);
            exit('Invalid data');
        }
        
        // Process webhook
        $result = $this->process_webhook($webhook_data);
        
        if ($result) {
            http_response_code(200);
            exit('OK');
        } else {
            http_response_code(500);
            exit('Processing failed');
        }
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($raw_data, $headers) {
        // For testing, allow webhooks without signature verification
        if (defined('MAVLERS_CF_WEBHOOK_TESTING') && MAVLERS_CF_WEBHOOK_TESTING) {
            return true;
        }
        
        $signature = $headers['X-Mailchimp-Signature'] ?? '';
        if (empty($signature) || empty($this->webhook_secret)) {
            return false;
        }
        
        $expected_signature = base64_encode(hash_hmac('sha1', $raw_data, $this->webhook_secret, true));
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Process webhook data
     */
    private function process_webhook($data) {
        $type = $data['type'] ?? '';
        $fired_at = $data['fired_at'] ?? '';
        $data_payload = $data['data'] ?? array();
        
        error_log("Mailchimp Webhook: Processing {$type} event");
        
        switch ($type) {
            case 'subscribe':
                return $this->handle_subscribe_event($data_payload);
                
            case 'unsubscribe':
                return $this->handle_unsubscribe_event($data_payload);
                
            case 'profile':
                return $this->handle_profile_update_event($data_payload);
                
            case 'email':
                return $this->handle_email_change_event($data_payload);
                
            case 'cleaned':
                return $this->handle_cleaned_event($data_payload);
                
            case 'upemail':
                return $this->handle_email_change_event($data_payload);
                
            default:
                error_log("Mailchimp Webhook: Unknown event type: {$type}");
                return true; // Return true to acknowledge receipt
        }
    }
    
    /**
     * Handle subscribe event
     */
    private function handle_subscribe_event($data) {
        $email = $data['email'] ?? '';
        $list_id = $data['list_id'] ?? '';
        
        if (empty($email) || empty($list_id)) {
            return false;
        }
        
        // Log the subscription
        $this->log_webhook_event('subscribe', $email, $list_id, $data);
        
        // Trigger action for other plugins to use
        do_action('mavlers_cf_mailchimp_subscriber_added', $email, $list_id, $data);
        
        return true;
    }
    
    /**
     * Handle unsubscribe event
     */
    private function handle_unsubscribe_event($data) {
        $email = $data['email'] ?? '';
        $list_id = $data['list_id'] ?? '';
        $reason = $data['reason'] ?? '';
        
        if (empty($email) || empty($list_id)) {
            return false;
        }
        
        // Log the unsubscription
        $this->log_webhook_event('unsubscribe', $email, $list_id, array(
            'reason' => $reason,
            'data' => $data
        ));
        
        // Trigger action for other plugins to use
        do_action('mavlers_cf_mailchimp_subscriber_removed', $email, $list_id, $reason, $data);
        
        return true;
    }
    
    /**
     * Handle profile update event
     */
    private function handle_profile_update_event($data) {
        $email = $data['email'] ?? '';
        $list_id = $data['list_id'] ?? '';
        $merges = $data['merges'] ?? array();
        
        if (empty($email) || empty($list_id)) {
            return false;
        }
        
        // Log the profile update
        $this->log_webhook_event('profile_update', $email, $list_id, array(
            'merges' => $merges,
            'data' => $data
        ));
        
        // Trigger action for other plugins to use
        do_action('mavlers_cf_mailchimp_profile_updated', $email, $list_id, $merges, $data);
        
        return true;
    }
    
    /**
     * Handle email change event
     */
    private function handle_email_change_event($data) {
        $old_email = $data['old_email'] ?? '';
        $new_email = $data['new_email'] ?? '';
        $list_id = $data['list_id'] ?? '';
        
        if (empty($old_email) || empty($new_email) || empty($list_id)) {
            return false;
        }
        
        // Log the email change
        $this->log_webhook_event('email_change', $old_email, $list_id, array(
            'new_email' => $new_email,
            'data' => $data
        ));
        
        // Trigger action for other plugins to use
        do_action('mavlers_cf_mailchimp_email_changed', $old_email, $new_email, $list_id, $data);
        
        return true;
    }
    
    /**
     * Handle cleaned event
     */
    private function handle_cleaned_event($data) {
        $email = $data['email'] ?? '';
        $list_id = $data['list_id'] ?? '';
        $reason = $data['reason'] ?? '';
        
        if (empty($email) || empty($list_id)) {
            return false;
        }
        
        // Log the cleaned event
        $this->log_webhook_event('cleaned', $email, $list_id, array(
            'reason' => $reason,
            'data' => $data
        ));
        
        // Trigger action for other plugins to use
        do_action('mavlers_cf_mailchimp_subscriber_cleaned', $email, $list_id, $reason, $data);
        
        return true;
    }
    
    /**
     * Log webhook event
     */
    private function log_webhook_event($event_type, $email, $list_id, $data = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_webhook_log';
        
        // Create table if it doesn't exist
        $this->maybe_create_webhook_log_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'event_type' => $event_type,
                'email' => $email,
                'list_id' => $list_id,
                'event_data' => json_encode($data),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        error_log("Mailchimp Webhook: Logged {$event_type} event for {$email}");
    }
    
    /**
     * Create webhook log table
     */
    private function maybe_create_webhook_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_webhook_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                email varchar(255) NOT NULL,
                list_id varchar(255) NOT NULL,
                event_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY email (email),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Get webhook secret
     */
    private function get_webhook_secret() {
        $secret = get_option('mavlers_cf_mailchimp_webhook_secret');
        
        if (empty($secret)) {
            $secret = wp_generate_password(32, false);
            update_option('mavlers_cf_mailchimp_webhook_secret', $secret);
        }
        
        return $secret;
    }
    
    /**
     * Get webhook URL
     */
    public function get_webhook_url() {
        return home_url('/' . $this->webhook_endpoint . '/');
    }
    
    /**
     * Get request headers
     */
    private function get_request_headers() {
        $headers = array();
        
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Fallback for servers that don't support getallheaders
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $header = str_replace('_', '-', substr($key, 5));
                    $headers[$header] = $value;
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * AJAX: Register webhook with Mailchimp
     */
    public function ajax_register_webhook() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $list_id = sanitize_text_field($_POST['list_id'] ?? '');
        
        if (empty($list_id)) {
            wp_send_json(array('success' => false, 'message' => 'List ID is required'));
            return;
        }
        
        $result = $this->register_webhook_with_mailchimp($list_id);
        wp_send_json($result);
    }
    
    /**
     * Register webhook with Mailchimp API
     */
    private function register_webhook_with_mailchimp($list_id) {
        $mailchimp = new Mavlers_CF_Mailchimp_Integration();
        $global_settings = $mailchimp->get_global_settings();
        
        $api_key = $global_settings['api_key'] ?? '';
        if (empty($api_key)) {
            return array('success' => false, 'message' => 'Mailchimp API key not configured');
        }
        
        $datacenter = substr($api_key, strpos($api_key, '-') + 1);
        $url = "https://{$datacenter}.api.mailchimp.com/3.0/lists/{$list_id}/webhooks";
        
        $webhook_data = array(
            'url' => $this->get_webhook_url(),
            'events' => array(
                'subscribe' => true,
                'unsubscribe' => true,
                'profile' => true,
                'cleaned' => true,
                'upemail' => true,
                'campaign' => false
            ),
            'sources' => array(
                'user' => true,
                'admin' => true,
                'api' => false
            )
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($webhook_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200 || $status_code === 201) {
            // Save webhook ID for later management
            update_option("mavlers_cf_mailchimp_webhook_{$list_id}", $data['id']);
            
            return array(
                'success' => true, 
                'message' => 'Webhook registered successfully',
                'data' => $data
            );
        } else {
            return array(
                'success' => false, 
                'message' => $data['detail'] ?? 'Failed to register webhook'
            );
        }
    }
} 