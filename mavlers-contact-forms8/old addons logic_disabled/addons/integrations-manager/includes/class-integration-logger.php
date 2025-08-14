<?php
/**
 * Integration Logger Class
 * 
 * Handles logging of integration activities and errors
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Integration_Logger {
    
    private $log_levels = array(
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7
    );
    
    private $max_log_size = 10485760; // 10MB
    private $log_retention_days = 30;
    
    public function __construct() {
        // Set up logging hooks
        add_action('wp_loaded', array($this, 'maybe_rotate_logs'));
        add_action('mavlers_cf_daily_cleanup', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Log a message
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, $context = array()) {
        // Check if logging is enabled
        if (!$this->is_logging_enabled()) {
            return;
        }
        
        // Validate log level
        if (!isset($this->log_levels[$level])) {
            $level = 'info';
        }
        
        // Check minimum log level
        if (!$this->should_log($level)) {
            return;
        }
        
        // Prepare log entry
        $log_entry = $this->prepare_log_entry($level, $message, $context);
        
        // Write to file
        $this->write_to_file($log_entry);
        
        // Store in database for critical entries
        if (in_array($level, array('emergency', 'alert', 'critical', 'error'))) {
            $this->store_in_database($level, $message, $context);
        }
        
        // Send notification for critical errors
        if (in_array($level, array('emergency', 'alert', 'critical'))) {
            $this->send_notification($level, $message, $context);
        }
    }
    
    /**
     * Log emergency level
     * @param string $message
     * @param array $context
     */
    public function emergency($message, $context = array()) {
        $this->log('emergency', $message, $context);
    }
    
    /**
     * Log alert level
     * @param string $message
     * @param array $context
     */
    public function alert($message, $context = array()) {
        $this->log('alert', $message, $context);
    }
    
    /**
     * Log critical level
     * @param string $message
     * @param array $context
     */
    public function critical($message, $context = array()) {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Log error level
     * @param string $message
     * @param array $context
     */
    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
    }
    
    /**
     * Log warning level
     * @param string $message
     * @param array $context
     */
    public function warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log notice level
     * @param string $message
     * @param array $context
     */
    public function notice($message, $context = array()) {
        $this->log('notice', $message, $context);
    }
    
    /**
     * Log info level
     * @param string $message
     * @param array $context
     */
    public function info($message, $context = array()) {
        $this->log('info', $message, $context);
    }
    
    /**
     * Log debug level
     * @param string $message
     * @param array $context
     */
    public function debug($message, $context = array()) {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Check if logging is enabled
     * @return bool
     */
    private function is_logging_enabled() {
        return get_option('mavlers_cf_integrations_logging_enabled', true);
    }
    
    /**
     * Check if we should log this level
     * @param string $level
     * @return bool
     */
    private function should_log($level) {
        $min_level = get_option('mavlers_cf_integrations_min_log_level', 'info');
        
        if (!isset($this->log_levels[$min_level]) || !isset($this->log_levels[$level])) {
            return true;
        }
        
        return $this->log_levels[$level] <= $this->log_levels[$min_level];
    }
    
    /**
     * Prepare log entry
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    private function prepare_log_entry($level, $message, $context) {
        $timestamp = current_time('c');
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        // Sanitize context for logging
        $context = Mavlers_CF_Security_Manager::sanitize_for_logging($context);
        
        // Build log entry
        $log_data = array(
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'memory_usage' => memory_get_usage(true),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        );
        
        return json_encode($log_data) . "\n";
    }
    
    /**
     * Write log entry to file
     * @param string $log_entry
     */
    private function write_to_file($log_entry) {
        $log_file = $this->get_log_file_path();
        
        // Create log directory if it doesn't exist
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Write to file
        if (is_writable($log_dir)) {
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Store critical entries in database
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function store_in_database($level, $message, $context) {
        global $wpdb;
        
        // Create logs table if it doesn't exist
        $this->maybe_create_logs_table();
        
        $context = Mavlers_CF_Security_Manager::sanitize_for_logging($context);
        
        $wpdb->insert(
            $wpdb->prefix . 'mavlers_cf_error_logs',
            array(
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'integration_id' => $context['integration_id'] ?? '',
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Send notification for critical errors
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function send_notification($level, $message, $context) {
        $notification_email = get_option('mavlers_cf_integrations_notification_email');
        
        if (empty($notification_email) || !is_email($notification_email)) {
            return;
        }
        
        // Rate limit notifications (max 1 per hour for same error)
        $rate_limit_key = 'mavlers_cf_error_notification_' . md5($message);
        if (get_transient($rate_limit_key)) {
            return;
        }
        set_transient($rate_limit_key, true, HOUR_IN_SECONDS);
        
        $subject = sprintf(
            '[%s] %s Integration Error',
            get_bloginfo('name'),
            ucfirst($level)
        );
        
        $body = sprintf(
            "A %s level error occurred in Mavlers Contact Forms Integrations:\n\n" .
            "Message: %s\n\n" .
            "Context: %s\n\n" .
            "Time: %s\n" .
            "Site: %s\n",
            strtoupper($level),
            $message,
            json_encode($context, JSON_PRETTY_PRINT),
            current_time('Y-m-d H:i:s'),
            home_url()
        );
        
        wp_mail($notification_email, $subject, $body);
    }
    
    /**
     * Get log file path
     * @return string
     */
    private function get_log_file_path() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/mavlers-cf-logs';
        $log_file = $log_dir . '/integrations-' . date('Y-m-d') . '.log';
        
        return $log_file;
    }
    
    /**
     * Get client IP address
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Rotate logs if they get too large
     */
    public function maybe_rotate_logs() {
        $log_file = $this->get_log_file_path();
        
        if (!file_exists($log_file)) {
            return;
        }
        
        if (filesize($log_file) > $this->max_log_size) {
            $rotated_file = $log_file . '.' . time() . '.old';
            rename($log_file, $rotated_file);
            
            // Compress old log
            if (function_exists('gzopen')) {
                $this->compress_log_file($rotated_file);
            }
        }
    }
    
    /**
     * Compress log file
     * @param string $file_path
     */
    private function compress_log_file($file_path) {
        $content = file_get_contents($file_path);
        $compressed_file = $file_path . '.gz';
        
        $gz = gzopen($compressed_file, 'w9');
        if ($gz) {
            gzwrite($gz, $content);
            gzclose($gz);
            unlink($file_path);
        }
    }
    
    /**
     * Clean up old log files
     */
    public function cleanup_old_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/mavlers-cf-logs';
        
        if (!is_dir($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '/*');
        $cutoff_time = time() - ($this->log_retention_days * DAY_IN_SECONDS);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
        
        // Clean database logs
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}mavlers_cf_error_logs'")) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}mavlers_cf_error_logs WHERE created_at < %s",
                date('Y-m-d H:i:s', $cutoff_time)
            ));
        }
    }
    
    /**
     * Create error logs table if needed
     */
    private function maybe_create_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_error_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                level varchar(20) NOT NULL,
                message text NOT NULL,
                context longtext,
                integration_id varchar(100),
                user_id bigint(20),
                ip_address varchar(45),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY level (level),
                KEY integration_id (integration_id),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Get recent logs from database
     * @param int $limit
     * @param string $level
     * @param string $integration_id
     * @return array
     */
    public function get_recent_logs($limit = 100, $level = '', $integration_id = '') {
        global $wpdb;
        
        $this->maybe_create_logs_table();
        
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($level)) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $level;
        }
        
        if (!empty($integration_id)) {
            $where_conditions[] = 'integration_id = %s';
            $where_values[] = $integration_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT * FROM {$wpdb->prefix}mavlers_cf_error_logs 
                $where_clause 
                ORDER BY created_at DESC 
                LIMIT %d";
        
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($sql, $where_values), ARRAY_A);
    }
    
    /**
     * Get log statistics
     * @param int $days
     * @return array
     */
    public function get_log_statistics($days = 7) {
        global $wpdb;
        
        $this->maybe_create_logs_table();
        
        $since_date = date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT level, COUNT(*) as count 
             FROM {$wpdb->prefix}mavlers_cf_error_logs 
             WHERE created_at >= %s 
             GROUP BY level 
             ORDER BY count DESC",
            $since_date
        ), ARRAY_A);
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mavlers_cf_error_logs WHERE created_at >= %s",
            $since_date
        ));
        
        return array(
            'total' => $total,
            'by_level' => $stats,
            'period_days' => $days
        );
    }
    
    /**
     * Export logs to CSV
     * @param array $filters
     * @return string
     */
    public function export_logs_csv($filters = array()) {
        $logs = $this->get_recent_logs(
            $filters['limit'] ?? 1000,
            $filters['level'] ?? '',
            $filters['integration_id'] ?? ''
        );
        
        $csv_output = fopen('php://temp', 'w');
        
        // CSV headers
        fputcsv($csv_output, array(
            'ID', 'Level', 'Message', 'Integration ID', 'User ID', 'IP Address', 'Created At'
        ));
        
        // CSV data
        foreach ($logs as $log) {
            fputcsv($csv_output, array(
                $log['id'],
                $log['level'],
                $log['message'],
                $log['integration_id'],
                $log['user_id'],
                $log['ip_address'],
                $log['created_at']
            ));
        }
        
        rewind($csv_output);
        $csv_content = stream_get_contents($csv_output);
        fclose($csv_output);
        
        return $csv_content;
    }
} 