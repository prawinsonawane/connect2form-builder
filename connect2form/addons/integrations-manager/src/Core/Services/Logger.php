<?php

namespace Connect2Form\Integrations\Core\Services;

/**
 * Logger Service
 *
 * Handles logging for integrations with proper caching and database operations.
 *
 * @since    2.0.0
 * @access   public
 */
class Logger {

    /**
     * Table name.
     *
     * @since    2.0.0
     * @access   private
     * @var      string    $table_name    Table name.
     */
    private $table_name;

    /**
     * Cache manager instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      CacheManager    $cache_manager    Cache manager instance.
     */
    private $cache_manager;

    /**
     * Constructor
     *
     * @since    2.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'connect2form_integration_logs';
        $this->cache_manager = new CacheManager();
        $this->ensure_logs_table_exists();
    }

    /**
     * Log a message with context.
     *
     * @since    2.0.0
     * @param    string $level   Log level.
     * @param    string $message Log message.
     * @param    array  $context Log context.
     */
    public function log( string $level, string $message, array $context = array() ): void {
        $this->insert_log_entry( $level, $message, $context );
        
        // Clear related caches.
        $this->cache_manager->delete_pattern( 'logs_*' );
        $this->cache_manager->delete_pattern( 'log_stats_*' );
    }

    /**
     * Log info message.
     *
     * @since    2.0.0
     * @param    string $message Log message.
     * @param    array  $context Log context.
     */
    public function info( string $message, array $context = array() ): void {
        $this->log( 'info', $message, $context );
    }

    /**
     * Log error message.
     *
     * @since    2.0.0
     * @param    string $message Log message.
     * @param    array  $context Log context.
     */
    public function error( string $message, array $context = array() ): void {
        $this->log( 'error', $message, $context );
    }

    /**
     * Log warning message.
     *
     * @since    2.0.0
     * @param    string $message Log message.
     * @param    array  $context Log context.
     */
    public function warning( string $message, array $context = array() ): void {
        $this->log( 'warning', $message, $context );
    }

    /**
     * Log success message.
     *
     * @since    2.0.0
     * @param    string $message Log message.
     * @param    array  $context Log context.
     */
    public function success( string $message, array $context = array() ): void {
        $this->log( 'success', $message, $context );
    }

    /**
     * Log integration-specific entry.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @param    int    $form_id       Form ID.
     * @param    string $status        Status.
     * @param    string $message       Log message.
     * @param    array  $data          Log data.
     * @param    int    $submission_id Submission ID.
     */
    public function logIntegration( string $integration_id, int $form_id, string $status, string $message, array $data = array(), ?int $submission_id = null ): void {
        // Use the DatabaseManager for this operation.
        $database_manager = new DatabaseManager();
        $database_manager->insertLogEntry( array(
            'form_id' => $form_id,
            'submission_id' => $submission_id,
            'integration_id' => $integration_id,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ) );
        
        // Clear related caches.
        $this->cache_manager->delete_pattern( 'logs_*' );
        $this->cache_manager->delete_pattern( 'log_stats_*' );
    }

    /**
     * Get logs with filtering and pagination.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @param    int    $limit          Number of records to return.
     * @param    int    $offset         Number of records to skip.
     * @return   array
     */
    public function getLogs( string $integration_id = '', int $limit = 100, int $offset = 0 ): array {
        // Use the DatabaseManager for this operation.
        $database_manager = new DatabaseManager();
        
        $filters = array();
        if ( ! empty( $integration_id ) ) {
            $filters['integration_id'] = $integration_id;
        }
        
        return $database_manager->getLogs( $filters, $limit, $offset );
    }

    /**
     * Get log statistics.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @param    int    $days           Number of days.
     * @return   array
     */
    public function getStats( string $integration_id = '', int $days = 30 ): array {
        $cache_key = "log_stats_{$integration_id}_{$days}";
        $cached_stats = $this->cache_manager->get($cache_key);
        
        if ($cached_stats !== null) {
            return $cached_stats;
        }
        
        // Use the DatabaseManager for this operation
        $database_manager = new DatabaseManager();
        
        $filters = array();
        if (!empty($integration_id)) {
            $filters['integration_id'] = $integration_id;
        }
        if ( $days > 0 ) {
            $ts = current_time( 'timestamp', true ) - ( $days * DAY_IN_SECONDS ); // UTC
            $filters['date_from'] = gmdate( 'Y-m-d H:i:s', $ts );                // UTC formatted
        }
        
        $stats = $database_manager->getLogStats($filters);
        
        // Cache the results for 15 minutes
        $this->cache_manager->set($cache_key, $stats, 900);
        
        return $stats;
    }

    /**
     * Clear old logs
     */
    public function clearOldLogs(): int {
        // Use the DatabaseManager for this operation
        $database_manager = new DatabaseManager();
        $deleted = $database_manager->cleanOldLogs();
        
        // Clear all log-related caches
        $this->cache_manager->delete_pattern('logs_*');
        $this->cache_manager->delete_pattern('log_stats_*');
        
        return $deleted;
    }

    /**
     * Delete logs for specific integration
     */
    public function deleteLogs(string $integration_id): int {
        // Use the DatabaseManager for this operation
        $database_manager = new DatabaseManager();
        
        // Get logs for this integration first
        $logs = $database_manager->getLogs(['integration_id' => $integration_id], 1000, 0);
        
        $deleted = 0;
        foreach ($logs as $log) {
            // Delete individual log entries
            $database_manager->deleteLogEntry($log['id']);
            $deleted++;
        }
        
        // Clear related caches
        $this->cache_manager->delete_pattern('logs_*');
        $this->cache_manager->delete_pattern('log_stats_*');
        
        return $deleted;
    }

    /**
     * Ensure the logs table exists
     */
    private function ensure_logs_table_exists(): void {
        // Use the DatabaseManager for this operation
        $database_manager = new DatabaseManager();
        $database_manager->createTables();
    }

    /**
     * Insert log entry into database
     */
    private function insert_log_entry(string $level, string $message, array $context): void {
        // Use the DatabaseManager for this operation
        $database_manager = new DatabaseManager();
        $database_manager->insertLogEntry([
            'form_id' => $context['form_id'] ?? 0,
            'submission_id' => $context['submission_id'] ?? null,
            'integration_id' => $context['integration_id'] ?? '',
            'status' => $level,
            'message' => $message,
            'data' => $context
        ]);
    }
} 



