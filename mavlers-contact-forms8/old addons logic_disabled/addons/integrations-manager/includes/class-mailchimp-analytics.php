<?php
/**
 * Mailchimp Analytics & Reporting
 * 
 * Tracks and analyzes Mailchimp integration performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Mailchimp_Analytics {
    
    private $analytics_table;
    private $reports_table;
    
    public function __construct() {
        global $wpdb;
        $this->analytics_table = $wpdb->prefix . 'mavlers_cf_mailchimp_analytics';
        $this->reports_table = $wpdb->prefix . 'mavlers_cf_mailchimp_reports';
        
        $this->init_hooks();
        $this->maybe_create_tables();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Track events
        add_action('mavlers_cf_mailchimp_subscription_success', array($this, 'track_successful_subscription'), 10, 3);
        add_action('mavlers_cf_mailchimp_subscription_failed', array($this, 'track_failed_subscription'), 10, 3);
        
        // Webhook events
        add_action('mavlers_cf_mailchimp_subscriber_added', array($this, 'track_webhook_subscription'), 10, 3);
        add_action('mavlers_cf_mailchimp_subscriber_removed', array($this, 'track_webhook_unsubscription'), 10, 4);
        
        // AJAX handlers
        add_action('wp_ajax_mailchimp_get_analytics_dashboard', array($this, 'ajax_get_analytics_dashboard'));
        add_action('wp_ajax_mailchimp_get_analytics_report', array($this, 'ajax_get_analytics_report'));
        add_action('wp_ajax_mailchimp_export_analytics', array($this, 'ajax_export_analytics'));
        
        // Daily report generation
        add_action('mavlers_cf_mailchimp_generate_daily_report', array($this, 'generate_daily_report'));
        
        // Schedule daily reports
        if (!wp_next_scheduled('mavlers_cf_mailchimp_generate_daily_report')) {
            wp_schedule_event(time(), 'daily', 'mavlers_cf_mailchimp_generate_daily_report');
        }
    }
    
    /**
     * Create analytics tables
     */
    private function maybe_create_tables() {
        global $wpdb;
        
        // Analytics events table
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->analytics_table}'") !== $this->analytics_table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$this->analytics_table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                form_id mediumint(9) NOT NULL,
                audience_id varchar(255),
                email varchar(255),
                status varchar(20) NOT NULL,
                response_time int(11),
                error_message text,
                user_agent text,
                ip_address varchar(45),
                metadata longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY form_id (form_id),
                KEY status (status),
                KEY created_at (created_at),
                KEY audience_id (audience_id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        // Daily reports table
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->reports_table}'") !== $this->reports_table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$this->reports_table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                report_date date NOT NULL,
                report_type varchar(50) NOT NULL,
                form_id mediumint(9),
                audience_id varchar(255),
                metrics longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_daily_report (report_date, report_type, form_id, audience_id),
                KEY report_date (report_date),
                KEY report_type (report_type)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Track successful subscription
     */
    public function track_successful_subscription($email, $form_id, $audience_id) {
        $this->track_event('subscription', $form_id, array(
            'audience_id' => $audience_id,
            'email' => $email,
            'status' => 'success',
            'response_time' => $this->get_response_time()
        ));
    }
    
    /**
     * Track failed subscription
     */
    public function track_failed_subscription($email, $form_id, $error_message) {
        $this->track_event('subscription', $form_id, array(
            'email' => $email,
            'status' => 'failed',
            'error_message' => $error_message,
            'response_time' => $this->get_response_time()
        ));
    }
    
    /**
     * Track webhook subscription
     */
    public function track_webhook_subscription($email, $list_id, $data) {
        $this->track_event('webhook_subscribe', 0, array(
            'audience_id' => $list_id,
            'email' => $email,
            'status' => 'success',
            'metadata' => $data
        ));
    }
    
    /**
     * Track webhook unsubscription
     */
    public function track_webhook_unsubscription($email, $list_id, $reason, $data) {
        $this->track_event('webhook_unsubscribe', 0, array(
            'audience_id' => $list_id,
            'email' => $email,
            'status' => 'success',
            'error_message' => $reason,
            'metadata' => $data
        ));
    }
    
    /**
     * Track analytics event
     */
    private function track_event($event_type, $form_id, $data = array()) {
        global $wpdb;
        
        $insert_data = array(
            'event_type' => $event_type,
            'form_id' => $form_id,
            'audience_id' => $data['audience_id'] ?? '',
            'email' => $this->hash_email($data['email'] ?? ''),
            'status' => $data['status'] ?? 'unknown',
            'response_time' => $data['response_time'] ?? null,
            'error_message' => $data['error_message'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'metadata' => json_encode($data['metadata'] ?? array()),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($this->analytics_table, $insert_data);
        
        error_log("Mailchimp Analytics: Tracked {$event_type} event for form {$form_id}");
    }
    
    /**
     * Get response time (placeholder)
     */
    private function get_response_time() {
        // This would be set by the API call timing
        return isset($GLOBALS['mavlers_cf_mailchimp_response_time']) 
            ? $GLOBALS['mavlers_cf_mailchimp_response_time'] 
            : null;
    }
    
    /**
     * Hash email for privacy
     */
    private function hash_email($email) {
        if (empty($email)) {
            return '';
        }
        
        // Use SHA256 hash for privacy while maintaining uniqueness
        return hash('sha256', strtolower(trim($email)));
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Generate daily report
     */
    public function generate_daily_report() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Generate overall report
        $this->generate_daily_report_for_date($yesterday);
        
        // Generate form-specific reports
        $this->generate_form_reports_for_date($yesterday);
        
        // Generate audience-specific reports
        $this->generate_audience_reports_for_date($yesterday);
        
        error_log("Mailchimp Analytics: Generated daily reports for {$yesterday}");
    }
    
    /**
     * Generate daily report for specific date
     */
    private function generate_daily_report_for_date($date) {
        global $wpdb;
        
        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'success' THEN 1 ELSE 0 END) as successful_subscriptions,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'failed' THEN 1 ELSE 0 END) as failed_subscriptions,
                SUM(CASE WHEN event_type = 'webhook_subscribe' THEN 1 ELSE 0 END) as webhook_subscriptions,
                SUM(CASE WHEN event_type = 'webhook_unsubscribe' THEN 1 ELSE 0 END) as webhook_unsubscriptions,
                AVG(response_time) as avg_response_time,
                MAX(response_time) as max_response_time,
                MIN(response_time) as min_response_time
            FROM {$this->analytics_table} 
            WHERE DATE(created_at) = %s",
            $date
        ), ARRAY_A);
        
        if ($metrics && $metrics['total_events'] > 0) {
            // Calculate success rate
            $total_submissions = $metrics['successful_subscriptions'] + $metrics['failed_subscriptions'];
            $success_rate = $total_submissions > 0 
                ? round(($metrics['successful_subscriptions'] / $total_submissions) * 100, 2) 
                : 0;
            
            $metrics['success_rate'] = $success_rate;
            $metrics['total_submissions'] = $total_submissions;
            
            $this->save_daily_report($date, 'overall', null, null, $metrics);
        }
    }
    
    /**
     * Generate form-specific reports
     */
    private function generate_form_reports_for_date($date) {
        global $wpdb;
        
        $form_metrics = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                form_id,
                COUNT(*) as total_events,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'success' THEN 1 ELSE 0 END) as successful_subscriptions,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'failed' THEN 1 ELSE 0 END) as failed_subscriptions,
                AVG(response_time) as avg_response_time
            FROM {$this->analytics_table} 
            WHERE DATE(created_at) = %s AND form_id > 0
            GROUP BY form_id",
            $date
        ), ARRAY_A);
        
        foreach ($form_metrics as $metrics) {
            $form_id = $metrics['form_id'];
            $total_submissions = $metrics['successful_subscriptions'] + $metrics['failed_subscriptions'];
            $success_rate = $total_submissions > 0 
                ? round(($metrics['successful_subscriptions'] / $total_submissions) * 100, 2) 
                : 0;
            
            $metrics['success_rate'] = $success_rate;
            $metrics['total_submissions'] = $total_submissions;
            
            $this->save_daily_report($date, 'form', $form_id, null, $metrics);
        }
    }
    
    /**
     * Generate audience-specific reports
     */
    private function generate_audience_reports_for_date($date) {
        global $wpdb;
        
        $audience_metrics = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                audience_id,
                COUNT(*) as total_events,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'success' THEN 1 ELSE 0 END) as successful_subscriptions,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'failed' THEN 1 ELSE 0 END) as failed_subscriptions,
                SUM(CASE WHEN event_type = 'webhook_subscribe' THEN 1 ELSE 0 END) as webhook_subscriptions,
                SUM(CASE WHEN event_type = 'webhook_unsubscribe' THEN 1 ELSE 0 END) as webhook_unsubscriptions,
                AVG(response_time) as avg_response_time
            FROM {$this->analytics_table} 
            WHERE DATE(created_at) = %s AND audience_id != ''
            GROUP BY audience_id",
            $date
        ), ARRAY_A);
        
        foreach ($audience_metrics as $metrics) {
            $audience_id = $metrics['audience_id'];
            $total_submissions = $metrics['successful_subscriptions'] + $metrics['failed_subscriptions'];
            $success_rate = $total_submissions > 0 
                ? round(($metrics['successful_subscriptions'] / $total_submissions) * 100, 2) 
                : 0;
            
            $metrics['success_rate'] = $success_rate;
            $metrics['total_submissions'] = $total_submissions;
            
            $this->save_daily_report($date, 'audience', null, $audience_id, $metrics);
        }
    }
    
    /**
     * Save daily report
     */
    private function save_daily_report($date, $type, $form_id, $audience_id, $metrics) {
        global $wpdb;
        
        $wpdb->replace(
            $this->reports_table,
            array(
                'report_date' => $date,
                'report_type' => $type,
                'form_id' => $form_id,
                'audience_id' => $audience_id,
                'metrics' => json_encode($metrics),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * AJAX: Get analytics dashboard
     */
    public function ajax_get_analytics_dashboard() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '7days');
        $dashboard_data = $this->get_dashboard_data($period);
        
        wp_send_json(array('success' => true, 'data' => $dashboard_data));
    }
    
    /**
     * Get dashboard data
     */
    public function get_dashboard_data($period = '7days') {
        $data = array(
            'overview' => $this->get_overview_metrics($period),
            'trends' => $this->get_trend_data($period),
            'forms' => $this->get_form_performance($period),
            'audiences' => $this->get_audience_performance($period),
            'errors' => $this->get_error_analysis($period)
        );
        
        return $data;
    }
    
    /**
     * Get overview metrics
     */
    private function get_overview_metrics($period) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($period);
        
        $metrics = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'success' THEN 1 ELSE 0 END) as successful_subscriptions,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'failed' THEN 1 ELSE 0 END) as failed_subscriptions,
                SUM(CASE WHEN event_type = 'webhook_subscribe' THEN 1 ELSE 0 END) as webhook_subscriptions,
                SUM(CASE WHEN event_type = 'webhook_unsubscribe' THEN 1 ELSE 0 END) as webhook_unsubscriptions,
                AVG(response_time) as avg_response_time
            FROM {$this->analytics_table} 
            WHERE {$date_condition}",
            ARRAY_A
        );
        
        if (!$metrics) {
            return array(
                'total_subscriptions' => 0,
                'success_rate' => 0,
                'avg_response_time' => 0,
                'webhook_activity' => 0
            );
        }
        
        $total_submissions = $metrics['successful_subscriptions'] + $metrics['failed_subscriptions'];
        $success_rate = $total_submissions > 0 
            ? round(($metrics['successful_subscriptions'] / $total_submissions) * 100, 2) 
            : 0;
        
        return array(
            'total_subscriptions' => $total_submissions,
            'successful_subscriptions' => intval($metrics['successful_subscriptions']),
            'failed_subscriptions' => intval($metrics['failed_subscriptions']),
            'success_rate' => $success_rate,
            'avg_response_time' => round(floatval($metrics['avg_response_time'] ?? 0), 2),
            'webhook_subscriptions' => intval($metrics['webhook_subscriptions']),
            'webhook_unsubscriptions' => intval($metrics['webhook_unsubscriptions']),
            'webhook_activity' => intval($metrics['webhook_subscriptions']) + intval($metrics['webhook_unsubscriptions'])
        );
    }
    
    /**
     * Get date condition for SQL queries
     */
    private function get_date_condition($period) {
        switch ($period) {
            case '24hours':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            case '7days':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30days':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90days':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            default:
                return "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }
    }
    
    /**
     * Get trend data
     */
    private function get_trend_data($period) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($period);
        $date_format = $this->get_date_format($period);
        
        $trends = $wpdb->get_results(
            "SELECT 
                DATE_FORMAT(created_at, '{$date_format}') as date_label,
                DATE(created_at) as date_key,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'success' THEN 1 ELSE 0 END) as subscriptions,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'failed' THEN 1 ELSE 0 END) as failures
            FROM {$this->analytics_table} 
            WHERE {$date_condition}
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at)",
            ARRAY_A
        );
        
        return $trends ?: array();
    }
    
    /**
     * Get date format for trend queries
     */
    private function get_date_format($period) {
        switch ($period) {
            case '24hours':
                return '%H:00';
            case '7days':
            case '30days':
                return '%m/%d';
            case '90days':
                return '%m/%d';
            default:
                return '%m/%d';
        }
    }
    
    /**
     * Get form performance data
     */
    private function get_form_performance($period) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($period);
        
        $forms = $wpdb->get_results(
            "SELECT 
                f.id as form_id,
                f.form_title as form_name,
                COUNT(*) as total_submissions,
                SUM(CASE WHEN a.status = 'success' THEN 1 ELSE 0 END) as successful_subscriptions,
                SUM(CASE WHEN a.status = 'failed' THEN 1 ELSE 0 END) as failed_subscriptions,
                AVG(a.response_time) as avg_response_time
            FROM {$this->analytics_table} a
            LEFT JOIN {$wpdb->prefix}mavlers_cf_forms f ON a.form_id = f.id
            WHERE a.{$date_condition} AND a.form_id > 0 AND a.event_type = 'subscription'
            GROUP BY a.form_id
            ORDER BY total_submissions DESC
            LIMIT 10",
            ARRAY_A
        );
        
        // Calculate success rates
        foreach ($forms as &$form) {
            $total = $form['total_submissions'];
            $form['success_rate'] = $total > 0 
                ? round(($form['successful_subscriptions'] / $total) * 100, 2) 
                : 0;
            $form['avg_response_time'] = round(floatval($form['avg_response_time'] ?? 0), 2);
        }
        
        return $forms ?: array();
    }
    
    /**
     * Get audience performance data
     */
    private function get_audience_performance($period) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($period);
        
        $audiences = $wpdb->get_results(
            "SELECT 
                audience_id,
                COUNT(*) as total_events,
                SUM(CASE WHEN event_type = 'subscription' AND status = 'success' THEN 1 ELSE 0 END) as form_subscriptions,
                SUM(CASE WHEN event_type = 'webhook_subscribe' THEN 1 ELSE 0 END) as webhook_subscriptions,
                SUM(CASE WHEN event_type = 'webhook_unsubscribe' THEN 1 ELSE 0 END) as webhook_unsubscriptions
            FROM {$this->analytics_table} 
            WHERE {$date_condition} AND audience_id != ''
            GROUP BY audience_id
            ORDER BY total_events DESC
            LIMIT 10",
            ARRAY_A
        );
        
        return $audiences ?: array();
    }
    
    /**
     * Get error analysis
     */
    private function get_error_analysis($period) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($period);
        
        $errors = $wpdb->get_results(
            "SELECT 
                error_message,
                COUNT(*) as error_count,
                MAX(created_at) as last_occurrence
            FROM {$this->analytics_table} 
            WHERE {$date_condition} AND status = 'failed' AND error_message != ''
            GROUP BY error_message
            ORDER BY error_count DESC
            LIMIT 10",
            ARRAY_A
        );
        
        return $errors ?: array();
    }
} 