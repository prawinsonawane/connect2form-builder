<?php
/**
 * Mailchimp Batch Processor
 * 
 * Handles batch processing of Mailchimp subscriptions for better performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Mailchimp_Batch_Processor {
    
    private $batch_size = 500; // Mailchimp API limit
    private $queue_table = 'mavlers_cf_mailchimp_queue';
    private $batch_interval = 300; // 5 minutes
    
    public function __construct() {
        $this->init_hooks();
        $this->maybe_create_queue_table();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule batch processing
        add_action('wp', array($this, 'schedule_batch_processing'));
        add_action('mavlers_cf_mailchimp_process_batch', array($this, 'process_batch_queue'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'handle_batch_actions'));
        add_action('wp_ajax_mailchimp_force_batch_process', array($this, 'ajax_force_batch_process'));
    }
    
    /**
     * Add subscription to batch queue
     */
    public function add_to_queue($form_id, $submission_data, $form_settings, $priority = 'normal') {
        global $wpdb;
        
        $queue_data = array(
            'form_id' => $form_id,
            'submission_data' => json_encode($submission_data),
            'form_settings' => json_encode($form_settings),
            'priority' => $priority,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'attempts' => 0
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . $this->queue_table,
            $queue_data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            error_log("Mailchimp: Added submission to batch queue (Form {$form_id})");
            
            // If high priority, process immediately
            if ($priority === 'high') {
                $this->process_single_item($wpdb->insert_id);
            }
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Process batch queue
     */
    public function process_batch_queue() {
        global $wpdb;
        
        // Get pending items grouped by audience
        $pending_items = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}{$this->queue_table} 
            WHERE status = 'pending' 
            AND attempts < 3
            ORDER BY priority = 'high' DESC, created_at ASC
            LIMIT {$this->batch_size}
        ");
        
        if (empty($pending_items)) {
            return;
        }
        
        // Group by audience for batch operations
        $batches = $this->group_by_audience($pending_items);
        
        foreach ($batches as $audience_id => $items) {
            $this->process_audience_batch($audience_id, $items);
        }
        
        // Clean up old processed items (older than 30 days)
        $this->cleanup_old_queue_items();
    }
    
    /**
     * Group queue items by audience
     */
    private function group_by_audience($items) {
        $batches = array();
        
        foreach ($items as $item) {
            $form_settings = json_decode($item->form_settings, true);
            $audience_id = $form_settings['audience_id'] ?? '';
            
            if ($audience_id) {
                if (!isset($batches[$audience_id])) {
                    $batches[$audience_id] = array();
                }
                $batches[$audience_id][] = $item;
            }
        }
        
        return $batches;
    }
    
    /**
     * Process batch for specific audience
     */
    private function process_audience_batch($audience_id, $items) {
        global $wpdb;
        
        // Get API settings
        $mailchimp = new Mavlers_CF_Mailchimp_Integration();
        $global_settings = $mailchimp->get_global_settings();
        
        if (!$mailchimp->is_globally_connected()) {
            $this->mark_items_failed($items, 'Mailchimp not connected globally');
            return;
        }
        
        // Prepare batch operations
        $batch_operations = array();
        
        foreach ($items as $item) {
            $submission_data = json_decode($item->submission_data, true);
            $form_settings = json_decode($item->form_settings, true);
            
            // Map form data
            $subscriber_data = $mailchimp->map_form_data($submission_data, $form_settings);
            
            if (empty($subscriber_data['email_address'])) {
                $this->mark_item_failed($item->id, 'Email address required');
                continue;
            }
            
            // Prepare batch operation
            $operation = array(
                'method' => $form_settings['update_existing'] ? 'PUT' : 'POST',
                'path' => '/lists/' . $audience_id . '/members',
                'body' => json_encode(array(
                    'email_address' => $subscriber_data['email_address'],
                    'status' => $form_settings['double_optin'] ? 'pending' : 'subscribed',
                    'merge_fields' => $subscriber_data['merge_fields'] ?? array(),
                    'tags' => !empty($form_settings['tags']) ? explode(',', $form_settings['tags']) : array()
                ))
            );
            
            if ($form_settings['update_existing']) {
                $subscriber_hash = md5(strtolower($subscriber_data['email_address']));
                $operation['path'] = '/lists/' . $audience_id . '/members/' . $subscriber_hash;
                $body = json_decode($operation['body'], true);
                $body['status_if_new'] = $body['status'];
                unset($body['status']);
                $operation['body'] = json_encode($body);
            }
            
            $batch_operations[] = array(
                'operation' => $operation,
                'queue_item' => $item
            );
        }
        
        if (empty($batch_operations)) {
            return;
        }
        
        // Execute batch request
        $this->execute_batch_request($batch_operations, $global_settings['api_key']);
    }
    
    /**
     * Execute Mailchimp batch request
     */
    private function execute_batch_request($batch_operations, $api_key) {
        $dc = $this->extract_datacenter($api_key);
        $url = "https://{$dc}.api.mailchimp.com/3.0/batches";
        
        // Prepare batch payload
        $operations = array_column($batch_operations, 'operation');
        $batch_payload = array('operations' => $operations);
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($batch_payload),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            $this->mark_operations_failed($batch_operations, $response->get_error_message());
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['id'])) {
            // Batch submitted successfully, schedule status check
            $this->schedule_batch_status_check($data['id'], $batch_operations);
            
            // Mark items as processing
            $this->mark_operations_processing($batch_operations, $data['id']);
            
            error_log("Mailchimp: Batch {$data['id']} submitted with " . count($operations) . " operations");
        } else {
            $error_message = isset($data['detail']) ? $data['detail'] : 'Batch submission failed';
            $this->mark_operations_failed($batch_operations, $error_message);
        }
    }
    
    /**
     * Schedule batch status check
     */
    private function schedule_batch_status_check($batch_id, $batch_operations) {
        wp_schedule_single_event(
            time() + 60, // Check in 1 minute
            'mavlers_cf_mailchimp_check_batch_status',
            array($batch_id, $batch_operations)
        );
    }
    
    /**
     * Check batch status and update queue items
     */
    public function check_batch_status($batch_id, $batch_operations) {
        $mailchimp = new Mavlers_CF_Mailchimp_Integration();
        $global_settings = $mailchimp->get_global_settings();
        $api_key = $global_settings['api_key'];
        $dc = $this->extract_datacenter($api_key);
        
        $url = "https://{$dc}.api.mailchimp.com/3.0/batches/{$batch_id}";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key)
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            // Reschedule check
            wp_schedule_single_event(time() + 120, 'mavlers_cf_mailchimp_check_batch_status', array($batch_id, $batch_operations));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['status'])) {
            switch ($data['status']) {
                case 'finished':
                    $this->process_batch_results($batch_id, $batch_operations, $api_key, $dc);
                    break;
                    
                case 'failed':
                    $this->mark_operations_failed($batch_operations, 'Batch processing failed');
                    break;
                    
                default:
                    // Still processing, check again later
                    wp_schedule_single_event(time() + 60, 'mavlers_cf_mailchimp_check_batch_status', array($batch_id, $batch_operations));
                    break;
            }
        }
    }
    
    /**
     * Process batch results
     */
    private function process_batch_results($batch_id, $batch_operations, $api_key, $dc) {
        // Get batch results
        $url = "https://{$dc}.api.mailchimp.com/3.0/batches/{$batch_id}";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key)
            )
        ));
        
        if (is_wp_error($response)) {
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['response_body_url'])) {
            // Download results
            $results_response = wp_remote_get($data['response_body_url']);
            $results_body = wp_remote_retrieve_body($results_response);
            $results = json_decode($results_body, true);
            
            // Process individual results
            foreach ($results as $index => $result) {
                if (isset($batch_operations[$index])) {
                    $queue_item = $batch_operations[$index]['queue_item'];
                    
                    if ($result['status_code'] >= 200 && $result['status_code'] < 300) {
                        $this->mark_item_completed($queue_item->id);
                    } else {
                        $error_data = json_decode($result['response'], true);
                        $error_message = isset($error_data['detail']) ? $error_data['detail'] : 'Subscription failed';
                        $this->mark_item_failed($queue_item->id, $error_message);
                    }
                }
            }
        }
    }
    
    /**
     * Mark queue item as completed
     */
    private function mark_item_completed($item_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . $this->queue_table,
            array('status' => 'completed', 'completed_at' => current_time('mysql')),
            array('id' => $item_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Mark queue item as failed
     */
    private function mark_item_failed($item_id, $error_message) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . $this->queue_table,
            array(
                'status' => 'failed',
                'error_message' => $error_message,
                'attempts' => new WP_Query("attempts + 1"),
                'failed_at' => current_time('mysql')
            ),
            array('id' => $item_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Mark multiple operations as processing
     */
    private function mark_operations_processing($batch_operations, $batch_id) {
        global $wpdb;
        
        foreach ($batch_operations as $operation) {
            $wpdb->update(
                $wpdb->prefix . $this->queue_table,
                array(
                    'status' => 'processing',
                    'batch_id' => $batch_id,
                    'processing_at' => current_time('mysql')
                ),
                array('id' => $operation['queue_item']->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Mark multiple operations as failed
     */
    private function mark_operations_failed($batch_operations, $error_message) {
        foreach ($batch_operations as $operation) {
            $this->mark_item_failed($operation['queue_item']->id, $error_message);
        }
    }
    
    /**
     * Extract datacenter from API key
     */
    private function extract_datacenter($api_key) {
        $parts = explode('-', $api_key);
        return end($parts);
    }
    
    /**
     * Create queue table
     */
    private function maybe_create_queue_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->queue_table;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            submission_data longtext NOT NULL,
            form_settings text NOT NULL,
            priority varchar(20) DEFAULT 'normal',
            status varchar(20) DEFAULT 'pending',
            batch_id varchar(100) DEFAULT NULL,
            error_message text DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            created_at datetime NOT NULL,
            processing_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Schedule batch processing
     */
    public function schedule_batch_processing() {
        if (!wp_next_scheduled('mavlers_cf_mailchimp_process_batch')) {
            wp_schedule_event(time(), 'mavlers_cf_mailchimp_batch_interval', 'mavlers_cf_mailchimp_process_batch');
        }
    }
    
    /**
     * Get queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$wpdb->prefix}{$this->queue_table}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        return $stats;
    }
    
    /**
     * Clean up old queue items
     */
    private function cleanup_old_queue_items() {
        global $wpdb;
        
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}{$this->queue_table}
            WHERE status IN ('completed', 'failed') 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
    
    /**
     * AJAX: Force batch processing
     */
    public function ajax_force_batch_process() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $this->process_batch_queue();
        
        wp_send_json_success(array(
            'message' => 'Batch processing completed',
            'stats' => $this->get_queue_stats()
        ));
    }
}

// Add custom cron interval
add_filter('cron_schedules', function($schedules) {
    $schedules['mavlers_cf_mailchimp_batch_interval'] = array(
        'interval' => 300, // 5 minutes
        'display' => __('Every 5 Minutes (Mailchimp Batch)')
    );
    return $schedules;
});

// Initialize batch processor
add_action('plugins_loaded', function() {
    if (class_exists('Mavlers_CF_Mailchimp_Integration')) {
        new Mavlers_CF_Mailchimp_Batch_Processor();
    }
}); 