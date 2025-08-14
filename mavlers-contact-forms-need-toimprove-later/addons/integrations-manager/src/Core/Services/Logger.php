<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * Logger Service
 * 
 * Handles logging of integration activities
 */
class Logger {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mavlers_cf_integration_logs';
    }

    /**
     * Log a message
     */
    public function log(string $level, string $message, array $context = []): void {
        // Check if logging is enabled
        $settings = get_option('mavlers_cf_integrations_settings', []);
        if (empty($settings['enable_logging'])) {
            return;
        }

        // Insert log entry into database
        $this->insert_log_entry($level, $message, $context);

        // Also log to WordPress error log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Debug logging disabled for production
        }
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }

    /**
     * Log success message
     */
    public function success(string $message, array $context = []): void {
        $this->log('success', $message, $context);
    }

    /**
     * Log integration activity
     */
    public function logIntegration(string $integration_id, int $form_id, string $status, string $message, array $data = [], ?int $submission_id = null): void {
        global $wpdb;

        $wpdb->insert(
            $this->table_name,
            [
                'form_id' => $form_id,
                'submission_id' => $submission_id,
                'integration_id' => $integration_id,
                'status' => $status,
                'message' => $message,
                'data' => json_encode($data),
                'created_at' => current_time('mysql')
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ]
        );
    }

    /**
     * Get logs for integration
     */
    public function getLogs(string $integration_id = '', int $limit = 100, int $offset = 0): array {
        global $wpdb;

        $where = '';
        $params = [];

        if (!empty($integration_id)) {
            $where = 'WHERE integration_id = %s';
            $params[] = $integration_id;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($params, [$limit, $offset])
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get log statistics
     */
    public function getStats(string $integration_id = '', int $days = 30): array {
        global $wpdb;

        $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        $params = [];

        if (!empty($integration_id)) {
            $where .= ' AND integration_id = %s';
            $params[] = $integration_id;
        }

        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM {$this->table_name} 
                {$where}
                GROUP BY status";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        $stats = [
            'success' => 0,
            'error' => 0,
            'warning' => 0,
            'total' => 0
        ];

        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Clear old logs
     */
    public function clearOldLogs(): int {
        global $wpdb;

        $settings = get_option('mavlers_cf_integrations_settings', []);
        $retention_days = $settings['log_retention_days'] ?? 30;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );

        return $deleted ?: 0;
    }

    /**
     * Delete logs for specific integration
     */
    public function deleteLogs(string $integration_id): int {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->table_name,
            ['integration_id' => $integration_id],
            ['%s']
        );

        return $deleted ?: 0;
    }

    /**
     * Insert log entry into database
     */
    private function insert_log_entry(string $level, string $message, array $context): void {
        global $wpdb;

        // Extract integration and form info from context
        $integration_id = $context['integration_id'] ?? '';
        $form_id = $context['form_id'] ?? 0;
        $submission_id = $context['submission_id'] ?? null;

        $wpdb->insert(
            $this->table_name,
            [
                'form_id' => $form_id,
                'submission_id' => $submission_id,
                'integration_id' => $integration_id,
                'status' => $level,
                'message' => $message,
                'data' => json_encode($context),
                'created_at' => current_time('mysql')
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s'
            ]
        );
    }
} 