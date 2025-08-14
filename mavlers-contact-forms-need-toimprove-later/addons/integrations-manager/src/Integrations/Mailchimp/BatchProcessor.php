<?php
/**
 * Mailchimp Batch Processor
 * 
 * Handles batch operations and queue management for performance optimization
 * 
 * @package MavlersCF\Integrations\Mailchimp
 * @since 1.0.0
 */

namespace MavlersCF\Integrations\Mailchimp;

if (!defined('ABSPATH')) {
    exit;
}

class BatchProcessor {
    
    /**
     * Batch queue table name
     */
    private $batch_table;
    
    /**
     * Maximum batch size
     */
    private $max_batch_size = 500;
    
    /**
     * Maximum retry attempts
     */
    private $max_retries = 3;
    
    /**
     * Batch statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRYING = 'retrying';
    
    /**
     * API client instance
     */
    private $api_client;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct($api_client = null, $logger = null) {
        global $wpdb;
        $this->batch_table = $wpdb->prefix . 'mavlers_cf_mailchimp_batch_queue';
        $this->api_client = $api_client;
        $this->logger = $logger;
        
        $this->init_hooks();
        $this->maybe_create_batch_table();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Cron events for batch processing
        add_action('mavlers_cf_mailchimp_process_batch_queue', array($this, 'process_batch_queue'));
        add_action('mavlers_cf_mailchimp_cleanup_batch_queue', array($this, 'cleanup_old_batches'));
        
        // Schedule cron jobs if not already scheduled
        if (!wp_next_scheduled('mavlers_cf_mailchimp_process_batch_queue')) {
            wp_schedule_event(time(), 'mavlers_cf_every_5_minutes', 'mavlers_cf_mailchimp_process_batch_queue');
        }
        
        if (!wp_next_scheduled('mavlers_cf_mailchimp_cleanup_batch_queue')) {
            wp_schedule_event(time(), 'daily', 'mavlers_cf_mailchimp_cleanup_batch_queue');
        }
        
        // Custom cron intervals
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // AJAX handlers for batch management
        add_action('wp_ajax_mailchimp_get_batch_status', array($this, 'ajax_get_batch_status'));
        add_action('wp_ajax_mailchimp_retry_failed_batches', array($this, 'ajax_retry_failed_batches'));
        add_action('wp_ajax_mailchimp_clear_batch_queue', array($this, 'ajax_clear_batch_queue'));
        add_action('wp_ajax_mailchimp_process_batch_now', array($this, 'ajax_process_batch_now'));
        
        // Form submission hook
        add_action('mavlers_cf_mailchimp_add_to_batch', array($this, 'add_to_batch_queue'), 10, 3);
        
        // Admin notices for batch status
        add_action('admin_notices', array($this, 'show_batch_admin_notices'));
    }
    
    /**
     * Create batch queue table if it doesn't exist
     */
    private function maybe_create_batch_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->batch_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            batch_id varchar(50) NOT NULL,
            form_id bigint(20) NOT NULL,
            audience_id varchar(20) NOT NULL,
            operation_type varchar(50) NOT NULL DEFAULT 'subscribe',
            email varchar(255) NOT NULL,
            data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 0,
            retry_count int(11) NOT NULL DEFAULT 0,
            error_message text,
            mailchimp_operation_id varchar(100),
            created_at datetime NOT NULL,
            processed_at datetime,
            PRIMARY KEY (id),
            KEY batch_id (batch_id),
            KEY status (status),
            KEY audience_id (audience_id),
            KEY priority (priority),
            KEY created_at (created_at),
            KEY form_id (form_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['mavlers_cf_every_5_minutes'] = array(
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 Minutes', 'mavlers-contact-forms')
        );
        
        $schedules['mavlers_cf_every_15_minutes'] = array(
            'interval' => 900, // 15 minutes
            'display' => __('Every 15 Minutes', 'mavlers-contact-forms')
        );
        
        return $schedules;
    }
    
    /**
     * Add item to batch queue
     */
    public function add_to_batch_queue($form_data, $form_settings, $form_id) {
        global $wpdb;
        
        // Check if batch processing is enabled
        if (!$this->is_batch_processing_enabled()) {
            return false;
        }
        
        $audience_id = $form_settings['audience_id'] ?? '';
        if (!$audience_id) {
            return false;
        }
        
        // Generate batch ID for grouping related operations
        $batch_id = $this->generate_batch_id();
        
        // Prepare queue item data
        $queue_data = array(
            'batch_id' => $batch_id,
            'form_id' => $form_id,
            'audience_id' => $audience_id,
            'operation_type' => 'subscribe', // Default operation
            'email' => $form_data['email'] ?? '',
            'data' => wp_json_encode(array(
                'form_data' => $form_data,
                'form_settings' => $form_settings
            )),
            'status' => self::STATUS_PENDING,
            'priority' => $this->calculate_priority($form_settings),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($this->batch_table, $queue_data);
        
        if ($result) {
            $queue_item_id = $wpdb->insert_id;
            
            // Log the addition
            if ($this->logger) {
                $this->logger->info("Added item to batch queue", array(
                    'queue_item_id' => $queue_item_id,
                    'batch_id' => $batch_id,
                    'email' => $queue_data['email'],
                    'audience_id' => $audience_id
                ));
            }
            
            // Trigger immediate processing if queue is small
            $this->maybe_trigger_immediate_processing();
            
            return $queue_item_id;
        }
        
        return false;
    }
    
    /**
     * Process batch queue
     */
    public function process_batch_queue() {
        if (!$this->can_process_batches()) {
            return;
        }
        
        $this->log_batch_processing_start();
        
        try {
            // Get pending batches grouped by audience
            $batches = $this->get_pending_batches();
            
            foreach ($batches as $audience_id => $items) {
                $this->process_audience_batch($audience_id, $items);
                
                // Add delay between batches to avoid rate limiting
                sleep(1);
            }
            
            $this->log_batch_processing_complete();
            
        } catch (Exception $e) {
            $this->log_batch_processing_error($e->getMessage());
        }
    }
    
    /**
     * Get pending batches grouped by audience
     */
    private function get_pending_batches() {
        global $wpdb;
        
        // Get pending items ordered by priority and creation time
        $items = $wpdb->get_results("
            SELECT *
            FROM {$this->batch_table}
            WHERE status IN ('" . self::STATUS_PENDING . "', '" . self::STATUS_RETRYING . "')
            ORDER BY priority DESC, created_at ASC
            LIMIT {$this->max_batch_size}
        ");
        
        // Group by audience ID
        $batches = array();
        foreach ($items as $item) {
            $batches[$item->audience_id][] = $item;
        }
        
        return $batches;
    }
    
    /**
     * Process batch for specific audience
     */
    private function process_audience_batch($audience_id, $items) {
        if (empty($items)) {
            return;
        }
        
        // Mark items as processing
        $this->update_batch_status($items, self::STATUS_PROCESSING);
        
        try {
            // Group operations by type
            $operations = $this->group_operations_by_type($items);
            
            foreach ($operations as $operation_type => $operation_items) {
                $this->process_operation_batch($audience_id, $operation_type, $operation_items);
            }
            
        } catch (Exception $e) {
            $this->handle_batch_error($items, $e->getMessage());
        }
    }
    
    /**
     * Group operations by type
     */
    private function group_operations_by_type($items) {
        $operations = array();
        
        foreach ($items as $item) {
            $operations[$item->operation_type][] = $item;
        }
        
        return $operations;
    }
    
    /**
     * Process operation batch
     */
    private function process_operation_batch($audience_id, $operation_type, $items) {
        switch ($operation_type) {
            case 'subscribe':
                $this->process_subscribe_batch($audience_id, $items);
                break;
                
            case 'unsubscribe':
                $this->process_unsubscribe_batch($audience_id, $items);
                break;
                
            case 'update':
                $this->process_update_batch($audience_id, $items);
                break;
                
            default:
                $this->handle_batch_error($items, "Unsupported operation type: {$operation_type}");
        }
    }
    
    /**
     * Process subscribe batch
     */
    private function process_subscribe_batch($audience_id, $items) {
        // Prepare batch operations for Mailchimp
        $operations = array();
        
        foreach ($items as $item) {
            $data = json_decode($item->data, true);
            $form_data = $data['form_data'] ?? array();
            $form_settings = $data['form_settings'] ?? array();
            
            // Prepare member data
            $member_data = array(
                'email_address' => $item->email,
                'status' => $form_settings['double_optin'] ? 'pending' : 'subscribed'
            );
            
            // Add merge fields
            if (!empty($form_data)) {
                $member_data['merge_fields'] = $this->map_merge_fields($form_data, $form_settings);
            }
            
            // Add tags if specified
            if (!empty($form_settings['tags'])) {
                $tags = array_map('trim', explode(',', $form_settings['tags']));
                $member_data['tags'] = $tags;
            }
            
            $operations[] = array(
                'method' => 'PUT',
                'path' => "/lists/{$audience_id}/members/" . md5(strtolower($item->email)),
                'operation_id' => "subscribe_{$item->id}",
                'body' => wp_json_encode($member_data)
            );
        }
        
        // Execute batch operations
        $this->execute_batch_operations($audience_id, $operations, $items);
    }
    
    /**
     * Process unsubscribe batch
     */
    private function process_unsubscribe_batch($audience_id, $items) {
        $operations = array();
        
        foreach ($items as $item) {
            $operations[] = array(
                'method' => 'PATCH',
                'path' => "/lists/{$audience_id}/members/" . md5(strtolower($item->email)),
                'operation_id' => "unsubscribe_{$item->id}",
                'body' => wp_json_encode(array('status' => 'unsubscribed'))
            );
        }
        
        $this->execute_batch_operations($audience_id, $operations, $items);
    }
    
    /**
     * Process update batch
     */
    private function process_update_batch($audience_id, $items) {
        $operations = array();
        
        foreach ($items as $item) {
            $data = json_decode($item->data, true);
            $form_data = $data['form_data'] ?? array();
            $form_settings = $data['form_settings'] ?? array();
            
            $member_data = array();
            
            // Add merge fields
            if (!empty($form_data)) {
                $member_data['merge_fields'] = $this->map_merge_fields($form_data, $form_settings);
            }
            
            $operations[] = array(
                'method' => 'PATCH',
                'path' => "/lists/{$audience_id}/members/" . md5(strtolower($item->email)),
                'operation_id' => "update_{$item->id}",
                'body' => wp_json_encode($member_data)
            );
        }
        
        $this->execute_batch_operations($audience_id, $operations, $items);
    }
    
    /**
     * Execute batch operations via Mailchimp API
     */
    private function execute_batch_operations($audience_id, $operations, $items) {
        if (empty($operations)) {
            return;
        }
        
        // Prepare batch request
        $batch_data = array('operations' => $operations);
        
        // Make batch API request
        $response = $this->make_batch_api_request($batch_data);
        
        if (isset($response['id'])) {
            // Batch submitted successfully
            $batch_id = $response['id'];
            
            // Update items with Mailchimp batch ID
            foreach ($items as $item) {
                $this->update_item_mailchimp_batch_id($item->id, $batch_id);
            }
            
            // Monitor batch completion
            $this->schedule_batch_completion_check($batch_id, $items);
            
        } else {
            // Batch submission failed
            $error_message = $response['detail'] ?? 'Batch submission failed';
            $this->handle_batch_error($items, $error_message);
        }
    }
    
    /**
     * Make batch API request
     */
    private function make_batch_api_request($batch_data) {
        if (!$this->api_client) {
            throw new Exception('API client not available');
        }
        
        // Use the correct API client method
        return $this->api_client->request('POST', '/batches', [
            'body' => json_encode($batch_data),
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);
    }
    
    /**
     * Map form data to Mailchimp merge fields
     */
    private function map_merge_fields($form_data, $form_settings) {
        $merge_fields = array();
        
        // Get field mapping
        $field_mapping = $form_settings['field_mapping'] ?? array();
        
        foreach ($field_mapping as $form_field => $mailchimp_field) {
            if (isset($form_data[$form_field]) && !empty($form_data[$form_field])) {
                $merge_fields[$mailchimp_field] = $form_data[$form_field];
            }
        }
        
        // Add default email mapping
        if (isset($form_data['email'])) {
            $merge_fields['EMAIL'] = $form_data['email'];
        }
        
        return $merge_fields;
    }
    
    /**
     * Schedule batch completion check
     */
    private function schedule_batch_completion_check($mailchimp_batch_id, $items) {
        // Schedule a single event to check batch completion
        wp_schedule_single_event(
            time() + 60, // Check in 1 minute
            'mavlers_cf_mailchimp_check_batch_completion',
            array($mailchimp_batch_id, wp_list_pluck($items, 'id'))
        );
    }
    
    /**
     * Check batch completion status
     */
    public function check_batch_completion($mailchimp_batch_id, $item_ids) {
        if (!$this->api_client) {
            return;
        }
        
        // Get batch status from Mailchimp
        $response = $this->api_client->request('GET', "/batches/{$mailchimp_batch_id}");
        
        if (isset($response['status'])) {
            switch ($response['status']) {
                case 'finished':
                    $this->handle_batch_completion($mailchimp_batch_id, $item_ids, $response);
                    break;
                    
                case 'failed':
                    $this->handle_batch_failure($item_ids, $response['errors'] ?? 'Batch failed');
                    break;
                    
                default:
                    // Still processing, check again later
                    $this->schedule_batch_completion_check($mailchimp_batch_id, $item_ids);
                    break;
            }
        }
    }
    
    /**
     * Handle batch completion
     */
    private function handle_batch_completion($mailchimp_batch_id, $item_ids, $response) {
        global $wpdb;
        
        // Get detailed results
        $results = $this->get_batch_results($mailchimp_batch_id);
        
        // Update item statuses based on results
        foreach ($item_ids as $item_id) {
            $operation_id = $this->get_operation_id_for_item($item_id);
            
            if (isset($results[$operation_id])) {
                $result = $results[$operation_id];
                
                if ($result['status_code'] < 400) {
                    // Success
                    $this->update_item_status($item_id, self::STATUS_COMPLETED);
                } else {
                    // Error
                    $error_message = $result['response'] ?? 'Unknown error';
                    $this->handle_item_error($item_id, $error_message);
                }
            } else {
                // No result found, mark as completed (shouldn't happen)
                $this->update_item_status($item_id, self::STATUS_COMPLETED);
            }
        }
        
        // Log completion
        if ($this->logger) {
            $this->logger->info("Batch completed", array(
                'mailchimp_batch_id' => $mailchimp_batch_id,
                'item_count' => count($item_ids),
                'response' => $response
            ));
        }
    }
    
    /**
     * Get batch results from Mailchimp
     */
    private function get_batch_results($mailchimp_batch_id) {
        if (!$this->api_client) {
            return array();
        }
        
        // Get batch results URL
        $batch_info = $this->api_client->request('GET', "/batches/{$mailchimp_batch_id}");
        
        if (!isset($batch_info['response_body_url'])) {
            return array();
        }
        
        // Download results
        $results_response = wp_remote_get($batch_info['response_body_url']);
        
        if (is_wp_error($results_response)) {
            return array();
        }
        
        $results_body = wp_remote_retrieve_body($results_response);
        $results = json_decode($results_body, true);
        
        // Index results by operation ID
        $indexed_results = array();
        if (is_array($results)) {
            foreach ($results as $result) {
                if (isset($result['operation_id'])) {
                    $indexed_results[$result['operation_id']] = $result;
                }
            }
        }
        
        return $indexed_results;
    }
    
    /**
     * Handle batch failure
     */
    private function handle_batch_failure($item_ids, $error_message) {
        foreach ($item_ids as $item_id) {
            $this->handle_item_error($item_id, $error_message);
        }
    }
    
    /**
     * Handle item error with retry logic
     */
    private function handle_item_error($item_id, $error_message) {
        global $wpdb;
        
        // Get current retry count
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT retry_count FROM {$this->batch_table} WHERE id = %d",
            $item_id
        ));
        
        if (!$item) {
            return;
        }
        
        $retry_count = intval($item->retry_count) + 1;
        
        if ($retry_count <= $this->max_retries) {
            // Retry the item
            $wpdb->update(
                $this->batch_table,
                array(
                    'status' => self::STATUS_RETRYING,
                    'retry_count' => $retry_count,
                    'error_message' => $error_message
                ),
                array('id' => $item_id)
            );
        } else {
            // Max retries reached, mark as failed
            $this->update_item_status($item_id, self::STATUS_FAILED, $error_message);
        }
    }
    
    /**
     * Update batch status for multiple items
     */
    private function update_batch_status($items, $status) {
        global $wpdb;
        
        $item_ids = wp_list_pluck($items, 'id');
        $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->batch_table} SET status = %s, processed_at = %s WHERE id IN ({$placeholders})",
            array_merge(array($status, current_time('mysql')), $item_ids)
        ));
    }
    
    /**
     * Update item status
     */
    private function update_item_status($item_id, $status, $error_message = null) {
        global $wpdb;
        
        $update_data = array(
            'status' => $status,
            'processed_at' => current_time('mysql')
        );
        
        if ($error_message) {
            $update_data['error_message'] = $error_message;
        }
        
        $wpdb->update(
            $this->batch_table,
            $update_data,
            array('id' => $item_id)
        );
    }
    
    /**
     * Update item with Mailchimp batch ID
     */
    private function update_item_mailchimp_batch_id($item_id, $mailchimp_batch_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->batch_table,
            array('mailchimp_operation_id' => $mailchimp_batch_id),
            array('id' => $item_id)
        );
    }
    
    /**
     * Get operation ID for item
     */
    private function get_operation_id_for_item($item_id) {
        global $wpdb;
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT operation_type FROM {$this->batch_table} WHERE id = %d",
            $item_id
        ));
        
        return $item ? "{$item->operation_type}_{$item_id}" : null;
    }
    
    /**
     * Generate unique batch ID
     */
    private function generate_batch_id() {
        return 'batch_' . time() . '_' . wp_generate_password(8, false);
    }
    
    /**
     * Calculate priority based on form settings
     */
    private function calculate_priority($form_settings) {
        $priority = 0;
        
        // Higher priority for double opt-in (time-sensitive)
        if ($form_settings['double_optin'] ?? false) {
            $priority += 10;
        }
        
        // Higher priority for VIP forms (if marked)
        if ($form_settings['vip_form'] ?? false) {
            $priority += 20;
        }
        
        return $priority;
    }
    
    /**
     * Check if batch processing is enabled
     */
    private function is_batch_processing_enabled() {
        return get_option('mavlers_cf_mailchimp_batch_processing', true);
    }
    
    /**
     * Check if batches can be processed now
     */
    private function can_process_batches() {
        // Check if another batch process is running
        $processing_flag = get_transient('mavlers_cf_mailchimp_batch_processing');
        
        if ($processing_flag) {
            return false;
        }
        
        // Set processing flag
        set_transient('mavlers_cf_mailchimp_batch_processing', true, 300); // 5 minutes
        
        return true;
    }
    
    /**
     * Maybe trigger immediate processing for small queues
     */
    private function maybe_trigger_immediate_processing() {
        global $wpdb;
        
        $pending_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->batch_table} 
            WHERE status = '" . self::STATUS_PENDING . "'
        ");
        
        // Trigger immediate processing if we have a small queue
        if ($pending_count <= 10) {
            wp_schedule_single_event(time() + 30, 'mavlers_cf_mailchimp_process_batch_queue');
        }
    }
    
    /**
     * Cleanup old completed/failed batches
     */
    public function cleanup_old_batches() {
        global $wpdb;
        
        $retention_days = apply_filters('mavlers_cf_mailchimp_batch_retention_days', 30);
        
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->batch_table} 
            WHERE status IN ('" . self::STATUS_COMPLETED . "', '" . self::STATUS_FAILED . "')
            AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $retention_days));
        
        if ($deleted && $this->logger) {
            $this->logger->info("Cleaned up {$deleted} old batch queue items");
        }
    }
    
    /**
     * Get batch queue statistics
     */
    public function get_batch_statistics() {
        global $wpdb;
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = '" . self::STATUS_PENDING . "' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = '" . self::STATUS_PROCESSING . "' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = '" . self::STATUS_COMPLETED . "' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = '" . self::STATUS_FAILED . "' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = '" . self::STATUS_RETRYING . "' THEN 1 ELSE 0 END) as retrying
            FROM {$this->batch_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        return array(
            'total' => intval($stats->total),
            'pending' => intval($stats->pending),
            'processing' => intval($stats->processing),
            'completed' => intval($stats->completed),
            'failed' => intval($stats->failed),
            'retrying' => intval($stats->retrying)
        );
    }
    
    /**
     * Show admin notices for batch status
     */
    public function show_batch_admin_notices() {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, 'mavlers-cf') === false) {
            return;
        }
        
        $stats = $this->get_batch_statistics();
        
        if ($stats['failed'] > 10) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Mailchimp Batch Queue:</strong> ' . $stats['failed'] . ' items have failed processing. ';
            echo '<a href="#" id="retry-failed-batches">Retry failed items</a></p>';
            echo '</div>';
        }
        
        if ($stats['pending'] > 100) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>Mailchimp Batch Queue:</strong> ' . $stats['pending'] . ' items are pending processing.</p>';
            echo '</div>';
        }
    }
    
    /**
     * Log batch processing events
     */
    private function log_batch_processing_start() {
        if ($this->logger) {
            $this->logger->info("Batch processing started");
        }
    }
    
    private function log_batch_processing_complete() {
        if ($this->logger) {
            $this->logger->info("Batch processing completed");
        }
        
        // Clear processing flag
        delete_transient('mavlers_cf_mailchimp_batch_processing');
    }
    
    private function log_batch_processing_error($error_message) {
        if ($this->logger) {
            $this->logger->error("Batch processing error: {$error_message}");
        }
        
        // Clear processing flag
        delete_transient('mavlers_cf_mailchimp_batch_processing');
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_get_batch_status() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $stats = $this->get_batch_statistics();
        wp_send_json_success($stats);
    }
    
    public function ajax_retry_failed_batches() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $updated = $wpdb->update(
            $this->batch_table,
            array(
                'status' => self::STATUS_PENDING,
                'retry_count' => 0,
                'error_message' => null
            ),
            array('status' => self::STATUS_FAILED)
        );
        
        wp_send_json_success("Retrying {$updated} failed items");
    }
    
    public function ajax_clear_batch_queue() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $deleted = $wpdb->query("
            DELETE FROM {$this->batch_table} 
            WHERE status IN ('" . self::STATUS_COMPLETED . "', '" . self::STATUS_FAILED . "')
        ");
        
        wp_send_json_success("Cleared {$deleted} completed/failed items");
    }
    
    public function ajax_process_batch_now() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Trigger immediate batch processing
        wp_schedule_single_event(time(), 'mavlers_cf_mailchimp_process_batch_queue');
        
        wp_send_json_success('Batch processing triggered');
    }
} 