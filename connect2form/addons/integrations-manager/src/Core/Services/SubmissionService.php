<?php

namespace Connect2Form\Integrations\Core\Services;

// Import common WordPress global functions/constants into the current namespace
use function \esc_html__;
use function \wp_json_encode;
use function \current_time;
use function \wp_strip_all_tags;
use function \absint;

/**
 * Submission Service
 * 
 * Handles all submission-related database operations with proper caching and optimization
 */
class SubmissionService {

    private $wpdb;
    private $cache_manager;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'connect2form_submissions';
        $this->cache_manager = new CacheManager();
    }

    /**
     * Get submission by ID with caching
     */
    public function get_submission(int $submission_id): ?object {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return null;
        }
        $cache_key = "submission_{$submission_id}";
        $cached_submission = $this->cache_manager->get($cache_key);
        
        if ($cached_submission !== null) {
            return $cached_submission;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
        $submission = $this->wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $submission_id), \ARRAY_A);

        if ($submission) {
            $submission->data = json_decode($submission->data, true) ?: [];
            $submission->utm_data = json_decode($submission->utm_data, true) ?: [];
            $this->cache_manager->set($cache_key, $submission, 1800); // Cache for 30 minutes
        } else {
            // Cache null to prevent repeated database queries for non-existent submissions
            $this->cache_manager->set($cache_key, null, 900); // Cache null for 15 minutes
        }

        return $submission;
    }

    /**
     * Save submission with proper validation
     */
    public function save_submission(array $submission_data): int {
        // Validate required fields
        if (empty($submission_data['form_id'])) {
            throw new \Exception( esc_html__( 'Form ID is required.', 'connect2form' ) );
        }

        if (empty($submission_data['data'])) {
            throw new \Exception( esc_html__( 'Submission data is required.', 'connect2form' ) );
        }

        // Prepare data
        $data = [
            'form_id' => (int) $submission_data['form_id'],
            'data' => wp_json_encode($submission_data['data']),
            'ip_address' => $submission_data['ip_address'] ?? '',
            'user_agent' => $submission_data['user_agent'] ?? '',
            'utm_data' => wp_json_encode($submission_data['utm_data'] ?? []),
            'created_at' => current_time('mysql')
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s'];

        $result = $this->wpdb->insert($this->table_name, $data, $format);

        if ($result === false) {
            $this->log_error(
                'DB insert failed: ' . wp_strip_all_tags($this->wpdb->last_error),
                [
                    'operation' => 'save_submission',
                    'form_id' => (int) $submission_data['form_id'],
                ]
            );
            throw new \Exception( esc_html__( 'Failed to save submission.', 'connect2form' ) );
        }

        $submission_id = $this->wpdb->insert_id;
        
        // Clear related caches
        $this->clear_submission_cache($submission_id);
        $this->clear_form_submissions_cache($submission_data['form_id']);

        return $submission_id;
    }

    /**
     * Create submission (alias for save_submission)
     */
    public function create_submission(array $submission_data): int {
        return $this->save_submission($submission_data);
    }

    /**
     * Delete submission with proper cleanup
     */
    public function delete_submission(int $submission_id): bool {
        // Get form_id before deletion for cache clearing
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return false;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
        $form_id = $this->wpdb->get_var($wpdb->prepare("SELECT form_id FROM `{$table}` WHERE id = %d", $submission_id));

        $result = $this->wpdb->delete(
            $table,
            ['id' => $submission_id],
            ['%d']
        );

        if ($result !== false) {
            $this->clear_submission_cache($submission_id);
            if ($form_id) {
                $this->clear_form_submissions_cache($form_id);
            }
            return true;
        }

        return false;
    }

    /**
     * Get submissions list with pagination and filters
     */
    public function get_submissions_list(array $args = []): array {
        $per_page = $args['per_page'] ?? 20;
        $current_page = $args['current_page'] ?? 1;
        $form_id = $args['form_id'] ?? 0;
        $search = $args['search'] ?? '';
        // Whitelist columns for ORDER BY
        $allowed_orderby = ['id', 'form_id', 'created_at'];
        $orderby = in_array($args['orderby'] ?? 'id', $allowed_orderby, true) ? ($args['orderby'] ?? 'id') : 'id';
        $order = strtoupper($args['order'] ?? 'DESC');
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [
                'items' => [],
                'total_items' => 0,
                'total_pages' => 0,
                'current_page' => 1,
                'per_page' => (int) $per_page,
            ];
        }

        // Normalized filters to avoid dynamic placeholders inside variables
        $search_like = $search !== '' ? '%' . $this->wpdb->esc_like($search) . '%' : '';

        // Get total count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
        global $wpdb;
        $total_items = (int) $this->wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE (%d = 0 OR form_id = %d) AND (%s = '' OR data LIKE %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
                $form_id, $form_id, $search_like, $search_like
            )
        );

        // Get items
        $offset = ($current_page - 1) * $per_page;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier; ORDER BY components are whitelisted
        global $wpdb;
        $items = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE (%d = 0 OR form_id = %d) AND (%s = '' OR data LIKE %s) ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table and whitelisted ORDER BY components
                $form_id,
                $form_id,
                $search_like,
                $search_like,
                absint($per_page),
                absint($offset)
            ),
            \ARRAY_A
        );

        return [
            'items' => $items,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $per_page),
            'current_page' => $current_page,
            'per_page' => $per_page
        ];
    }

    /**
     * Get total submissions count
     */
    public function get_submissions_count(): int {
        $cache_key = 'total_submissions_count';
        $cached_count = $this->cache_manager->get($cache_key);
        
        if ($cached_count !== null) {
            return (int) $cached_count;
        }

        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return 0;
        }
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier; constant query
        
        // Ensure we always return an integer, even if the query fails
        $count = $count !== null ? (int) $count : 0;
        
        $this->cache_manager->set($cache_key, $count, 1800); // Cache for 30 minutes

        return $count;
    }

    /**
     * Get submissions count for a form
     */
    public function get_form_submissions_count(int $form_id): int {
        $cache_key = "form_submissions_count_{$form_id}";
        $cached_count = $this->cache_manager->get($cache_key);
        
        if ($cached_count !== null) {
            return (int) $cached_count;
        }

        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return 0;
        }
        global $wpdb;
        $count = $this->wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE form_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
                $form_id
            )
        );

        // Ensure we always return an integer, even if the query fails
        $count = $count !== null ? (int) $count : 0;

        $this->cache_manager->set($cache_key, $count, 1800); // Cache for 30 minutes

        return $count;
    }

    /**
     * Get submissions count by form (alias for get_form_submissions_count)
     */
    public function get_submissions_count_by_form(int $form_id): int {
        return $this->get_form_submissions_count($form_id);
    }

    /**
     * Get submissions with pagination
     */
    public function get_submissions(int $per_page = 20, int $offset = 0): array {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
        global $wpdb;
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
                absint($per_page),
                absint($offset)
            ),
            \ARRAY_A
        );
    }

    /**
     * Get submissions by form with pagination
     */
    public function get_submissions_by_form(int $form_id, int $per_page = 20, int $offset = 0): array {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
        global $wpdb;
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE form_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
                absint($form_id),
                absint($per_page),
                absint($offset)
            ),
            \ARRAY_A
        );
    }

    /**
     * Get recent submissions
     */
    public function get_recent_submissions(int $limit = 10): array {
        $cache_key = "recent_submissions_{$limit}";
        $cached_submissions = $this->cache_manager->get($cache_key);
        
        if ($cached_submissions !== null) {
            return $cached_submissions;
        }

        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
        global $wpdb;
        $submissions = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
                absint($limit)
            ),
            \ARRAY_A
        );

        $this->cache_manager->set($cache_key, $submissions, 900); // Cache for 15 minutes

        return $submissions;
    }

    /**
     * Get recent submissions for export
     */
    public function get_recent_submissions_for_export(int $limit = 1000): array {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
        global $wpdb;
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
                absint($limit)
            ),
            \ARRAY_A
        );
    }

    /**
     * Cleanup old submissions
     */
    public function cleanup_old_submissions(int $retention_days): int {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) { return 0; }
        global $wpdb;
        $result = $this->wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
                $retention_days
            )
        );

        if ($result !== false) {
            // Clear all submission caches since we don't know which ones were deleted
            $this->cache_manager->delete_pattern('submission_*');
            $this->cache_manager->delete_pattern('form_submissions_*');
            $this->cache_manager->delete_pattern('submission_stats*');
            $this->cache_manager->delete_pattern('recent_submissions_*');
        }

        return $result ?: 0;
    }

    /**
     * Get submissions by date range
     */
    public function get_submissions_by_date_range(string $start_date, string $end_date, int $form_id = 0): array {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table(); identifiers cannot be parameterized
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE created_at BETWEEN %s AND %s AND (%d = 0 OR form_id = %d) ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
                $start_date,
                $end_date,
                $form_id,
                $form_id
            ),
            \ARRAY_A
        );
    }

    /**
     * Get submission statistics
     */
    public function get_submission_stats(int $form_id = 0): array {
        $cache_key = "submission_stats" . ($form_id > 0 ? "_{$form_id}" : '');
        $cached_stats = $this->cache_manager->get($cache_key);
        
        if ($cached_stats !== null) {
            return $cached_stats;
        }

        $where = '';
        $where_values = [];

        if ($form_id > 0) {
            $where = ' WHERE form_id = %d';
            $where_values[] = $form_id;
        }

        $stats = [
            'total' => 0,
            'today' => 0,
            'this_week' => 0,
            'this_month' => 0
        ];

        // Total submissions
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return $stats;
        }
        global $wpdb;
        $total_sql = "SELECT COUNT(*) FROM `{$table}` WHERE (%d = 0 OR form_id = %d)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $total_sql is prepared via $wpdb->prepare immediately below
        $stats['total'] = (int) $this->wpdb->get_var($wpdb->prepare($total_sql, $form_id, $form_id));

        // Today's submissions
        $today_sql = "SELECT COUNT(*) FROM `{$table}` WHERE (%d = 0 OR form_id = %d) AND DATE(created_at) = CURDATE()"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $today_sql is prepared via $wpdb->prepare immediately below
        $stats['today'] = (int) $this->wpdb->get_var($wpdb->prepare($today_sql, $form_id, $form_id));

        // This week's submissions
        $week_sql = "SELECT COUNT(*) FROM `{$table}` WHERE (%d = 0 OR form_id = %d) AND YEARWEEK(created_at) = YEARWEEK(NOW())"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $week_sql is prepared via $wpdb->prepare immediately below
        $stats['this_week'] = (int) $this->wpdb->get_var($wpdb->prepare($week_sql, $form_id, $form_id));

        // This month's submissions
        $month_sql = "SELECT COUNT(*) FROM `{$table}` WHERE (%d = 0 OR form_id = %d) AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $month_sql is prepared via $wpdb->prepare immediately below
        $stats['this_month'] = (int) $this->wpdb->get_var($wpdb->prepare($month_sql, $form_id, $form_id));

        $this->cache_manager->set($cache_key, $stats, 1800); // Cache for 30 minutes

        return $stats;
    }

    /**
     * Bulk delete submissions
     */
    public function bulk_delete_submissions(array $submission_ids): array {
        $results = [
            'deleted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($submission_ids as $submission_id) {
            if ($this->delete_submission($submission_id)) {
                $results['deleted']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to delete submission ID: {$submission_id}";
            }
        }

        return $results;
    }

    /**
     * Delete old submissions
     */
    public function delete_old_submissions(int $days_to_keep = 30): int {
        global $wpdb;
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) { return 0; }
        // cleanup operation for old submissions; no caching needed
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $days_to_keep
            )
        );

        if ($result !== false) {
            // Clear all submission caches since we don't know which ones were deleted
            $this->cache_manager->delete_pattern('submission_*');
            $this->cache_manager->delete_pattern('form_submissions_*');
            $this->cache_manager->delete_pattern('submission_stats*');
            $this->cache_manager->delete_pattern('recent_submissions_*');
        }

        return $result ?: 0;
    }

    /**
     * Export submissions to CSV
     */
    public function export_submissions_csv(array $submission_ids): string {
        if (empty($submission_ids)) {
            return '';
        }

        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) { return ''; }
        $ids = array_map('absint', $submission_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        global $wpdb;
        $submissions = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE id IN ({$placeholders}) ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- validated table identifier; dynamic placeholders constructed above
                $ids
            ),
            \ARRAY_A
        );

        if (empty($submissions)) {
            return '';
        }

        // Generate CSV
        $csv_data = [];
        $headers = ['ID', 'Form ID', 'Data', 'IP Address', 'User Agent', 'UTM Data', 'Created At'];
        $csv_data[] = $headers;

        foreach ($submissions as $submission) {
            $data = json_decode($submission['data'], true) ?: [];
            $utm_data = json_decode($submission['utm_data'], true) ?: [];
            
            $csv_data[] = [
                $submission['id'],
                $submission['form_id'],
                json_encode($data),
                $submission['ip_address'],
                $submission['user_agent'],
                json_encode($utm_data),
                $submission['created_at']
            ];
        }

        // Convert to CSV string
        $csv_string = '';
        foreach ($csv_data as $row) {
            $csv_string .= '"' . implode('","', array_map('str_replace', array_fill(0, count($row), '"'), array_fill(0, count($row), '""'), $row)) . '"' . "\n";
        }

        return $csv_string;
    }

    /**
     * Clear submission cache
     */
    private function clear_submission_cache(int $submission_id): void {
        $this->cache_manager->delete("submission_{$submission_id}");
        $this->cache_manager->delete_pattern('recent_submissions_*');
        $this->cache_manager->delete_pattern('submission_stats*');
    }

    /**
     * Clear form submissions cache
     */
    private function clear_form_submissions_cache(int $form_id): void {
        $this->cache_manager->delete("form_submissions_count_{$form_id}");
        $this->cache_manager->delete_pattern('submission_stats*');
    }

    /**
     * Log an internal error using the Logger service, with safe fallback
     */
    private function log_error(string $message, array $context = []): void {
        try {
            $logger = new Logger();
            $logger->error($message, $context);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Connect2Form SubmissionService: ' . $message . ' | Logger failure: ' . $e->getMessage());
            }
        }
    }

    /**
     * Clear all submission caches
     */
    public function clear_all_caches(): void {
        $this->cache_manager->delete_pattern('submission_*');
        $this->cache_manager->delete_pattern('form_submissions_*');
        $this->cache_manager->delete_pattern('submission_stats*');
        $this->cache_manager->delete_pattern('recent_submissions_*');
        $this->cache_manager->delete_pattern('total_submissions_*');
    }

    /**
     * Check if table exists
     */
    public function table_exists(): bool {
        global $wpdb;
        $result = $this->wpdb->get_var(
            $wpdb->prepare(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
                $this->table_name
            )
        );
        return $result === $this->table_name;
    }
}
