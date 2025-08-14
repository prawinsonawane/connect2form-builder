<?php

namespace Connect2Form\Integrations\Core\Services;

// Import common WordPress globals for static analysis
use function \esc_html__;
use function \wp_json_encode;
use function \current_time;
use function \wp_strip_all_tags;
use function \absint;
use function \sanitize_text_field;

/**
 * Form Service
 * 
 * Handles all form-related database operations with proper caching and optimization.
 *
 * @since    2.0.0
 * @access   public
 */
class FormService {

    /**
     * WordPress database instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      \wpdb    $wpdb    WordPress database instance.
     */
    private $wpdb;

    /**
     * Cache manager instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      CacheManager    $cache_manager    Cache manager instance.
     */
    private $cache_manager;

    /**
     * Table name.
     *
     * @since    2.0.0
     * @access   private
     * @var      string    $table_name    Table name.
     */
    private $table_name;

    /**
     * Constructor
     *
     * @since    2.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'connect2form_forms';
        $this->cache_manager = new CacheManager();
    }

    /**
     * Get a form by ID with caching.
     *
     * @since    2.0.0
     * @param    int $form_id Form ID.
     * @return   object|null
     */
    public function get_form( int $form_id ): ?object {
        $table = $this->table_name;
        if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! c2f_is_valid_prefixed_table( $table ) ) {
            return null;
        }
        $cache_key = "form_{$form_id}";
        $cached_form = $this->cache_manager->get( $cache_key );
        
        if ( $cached_form !== null ) {
            return $cached_form;
        }

        global $wpdb;
        $form = $this->wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table(); identifiers cannot be parameterized
                "SELECT * FROM `{$table}` WHERE id = %d",
                $form_id
            )
        );
        
        if ( $form ) {
            // Ensure fields and settings are properly decoded.
            $form->fields = is_string( $form->fields ) ? json_decode( $form->fields, true ) : $form->fields;
            $form->fields = $form->fields ?: array();
            
            $form->settings = is_string( $form->settings ) ? json_decode( $form->settings, true ) : $form->settings;
            $form->settings = $form->settings ?: array();
            
            $this->cache_manager->set( $cache_key, $form, 3600 ); // Cache for 1 hour.
        } else {
            // Cache null to prevent repeated database queries for non-existent forms.
            $this->cache_manager->set( $cache_key, null, 1800 ); // Cache null for 30 minutes.
        }

        return $form;
    }

    /**
     * Get active form by ID.
     *
     * @since    2.0.0
     * @param    int $form_id Form ID.
     * @return   object|null
     */
    public function get_active_form( int $form_id ): ?object {
        $table = $this->table_name;
        if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! c2f_is_valid_prefixed_table( $table ) ) {
            return null;
        }
        $cache_key = "active_form_{$form_id}";
        $cached_form = $this->cache_manager->get( $cache_key );
        
        if ( $cached_form !== null ) {
            return $cached_form;
        }

        global $wpdb;
        $form = $this->wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table(); identifiers cannot be parameterized
                "SELECT * FROM `{$table}` WHERE id = %d AND status = %s",
                $form_id,
                'active'
            )
        );

        if ( $form ) {
            // Ensure fields and settings are properly decoded.
            $form->fields = is_string( $form->fields ) ? json_decode( $form->fields, true ) : $form->fields;
            $form->fields = $form->fields ?: array();
            
            $form->settings = is_string( $form->settings ) ? json_decode( $form->settings, true ) : $form->settings;
            $form->settings = $form->settings ?: array();
            
            $this->cache_manager->set( $cache_key, $form, 3600 ); // Cache for 1 hour.
        } else {
            // Cache null to prevent repeated database queries for non-existent forms.
            $this->cache_manager->set( $cache_key, null, 1800 ); // Cache null for 30 minutes.
        }

        return $form;
    }

    /**
     * Get form title by ID
     */
    public function get_form_title(int $form_id): ?string {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return null;
        }
        $cache_key = "form_title_{$form_id}";
        $cached_title = $this->cache_manager->get($cache_key);
        
        if ($cached_title !== null) {
            return $cached_title;
        }

        global $wpdb;
        $title = $this->wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table(); identifiers cannot be parameterized
                "SELECT form_title FROM `{$table}` WHERE id = %d",
                $form_id
            )
        );

        if ($title) {
            $this->cache_manager->set($cache_key, $title, 3600);
        } else {
            // Cache null to prevent repeated database queries for non-existent form titles
            $this->cache_manager->set($cache_key, null, 1800); // Cache null for 30 minutes
        }

        return $title;
    }

    /**
     * Get form fields by ID
     */
    public function get_form_fields(int $form_id): array {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        $cache_key = "form_fields_{$form_id}";
        $cached_fields = $this->cache_manager->get($cache_key);
        
        if ($cached_fields !== null) {
            return $cached_fields;
        }

        global $wpdb;
        $fields_json = $this->wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table(); identifiers cannot be parameterized
                "SELECT fields FROM `{$table}` WHERE id = %d",
                $form_id
            )
        );

        $fields = json_decode($fields_json, true) ?: [];
        $this->cache_manager->set($cache_key, $fields, 3600);

        return $fields;
    }

    /**
     * Save form with proper validation and caching
     */
    public function save_form(array $form_data): int {
        $form_id = $form_data['form_id'] ?? 0;
        
        // Validate required fields
        if (empty($form_data['title'])) {
            throw new \Exception( esc_html__( 'Form title is required.', 'connect2form' ) );
        }

        if (empty($form_data['fields'])) {
            throw new \Exception( esc_html__( 'Form fields are required.', 'connect2form' ) );
        }

        // Prepare data
        $data = [
            'form_title' => sanitize_text_field($form_data['title']),
            'fields' => wp_json_encode($form_data['fields']),
            'settings' => wp_json_encode($form_data['settings'] ?? []),
            'status' => 'active',
            'updated_at' => current_time('mysql')
        ];

        $format = ['%s', '%s', '%s', '%s', '%s'];

        if ($form_id) {
            // Update existing form
            $result = $this->wpdb->update(
                $this->table_name,
                $data,
                ['id' => $form_id],
                $format,
                ['%d']
            );

            if ($result === false) {
                $this->log_error(
                    'DB update failed: ' . wp_strip_all_tags($this->wpdb->last_error),
                    [
                        'operation' => 'update_form',
                        'form_id' => $form_id,
                    ]
                );
                throw new \Exception( esc_html__( 'Failed to update form.', 'connect2form' ) );
            }

            $this->clear_form_cache($form_id);
        } else {
            // Create new form
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';

            $result = $this->wpdb->insert($this->table_name, $data, $format);

            if ($result === false) {
                $this->log_error(
                    'DB insert failed: ' . wp_strip_all_tags($this->wpdb->last_error),
                    [
                        'operation' => 'create_form',
                    ]
                );
                throw new \Exception( esc_html__( 'Failed to create form.', 'connect2form' ) );
            }

            $form_id = $this->wpdb->insert_id;
        }

        return $form_id;
    }

    /**
     * Create form (alias for save_form)
     */
    public function create_form(array $form_data): int {
        return $this->save_form($form_data);
    }

    /**
     * Delete form with proper cleanup
     */
    public function delete_form(int $form_id): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['id' => $form_id],
            ['%d']
        );

        if ($result !== false) {
            $this->clear_form_cache($form_id);
            return true;
        }

        return false;
    }

    /**
     * Duplicate form
     */
    public function duplicate_form(int $form_id): int {
        $original_form = $this->get_form($form_id);
        
        if (!$original_form) {
            throw new \Exception( esc_html__( 'Form not found.', 'connect2form' ) );
        }

        $data = [
            'form_title' => $original_form->form_title . ' (Copy)',
            'fields' => $original_form->fields,
            'settings' => $original_form->settings,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $format = ['%s', '%s', '%s', '%s', '%s', '%s'];

        $result = $this->wpdb->insert($this->table_name, $data, $format);

        if ($result === false) {
            $this->log_error(
                'DB duplicate failed: ' . wp_strip_all_tags($this->wpdb->last_error),
                [
                    'operation' => 'duplicate_form',
                    'source_form_id' => $form_id,
                ]
            );
            throw new \Exception( esc_html__( 'Failed to duplicate form.', 'connect2form' ) );
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get forms list with pagination and search
     */
    public function get_forms_list(array $args = []): array {
        $per_page = $args['per_page'] ?? 20;
        $current_page = $args['current_page'] ?? 1;
        $search = $args['search'] ?? '';
        // Whitelist allowed ordering inputs to avoid SQLi
        $allowed_orderby = ['id', 'form_title', 'updated_at', 'created_at', 'status'];
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

        // Normalize filters and avoid dynamic SQL fragments
        global $wpdb;
        $search_like = $search !== '' ? '%' . $this->wpdb->esc_like($search) . '%' : '';

        // Get total count
        $total_items = (int) $this->wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE (%s = '' OR form_title LIKE %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
                $search_like,
                $search_like
            )
        );

        // Get items
        $offset = ($current_page - 1) * $per_page;
        $items = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE (%s = '' OR form_title LIKE %s) ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier; ORDER BY components are whitelisted
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
     * Get forms with pagination and filters (alias for get_forms_list)
     */
    public function get_forms(int $per_page = 20, int $offset = 0, string $orderby = 'id', string $order = 'DESC', string $search = ''): array {
        $args = [
            'per_page' => $per_page,
            'current_page' => ($offset / $per_page) + 1,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order
        ];
        
        $result = $this->get_forms_list($args);
        return $result['items'];
    }

    /**
     * Get all forms for dropdown
     */
    public function get_all_forms(): array {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        $cache_key = 'all_forms_dropdown';
        $cached_forms = $this->cache_manager->get($cache_key);
        
        if ($cached_forms !== null) {
            return $cached_forms;
        }

        $forms = $this->wpdb->get_results(
            "SELECT id, form_title FROM `{$table}` ORDER BY form_title", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
            \ARRAY_A
        );

        $this->cache_manager->set($cache_key, $forms, 1800); // Cache for 30 minutes

        return $forms;
    }

    /**
     * Get all forms for export
     */
    public function get_all_forms_for_export(): array {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        return $this->wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id ASC", \ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
    }

    /**
     * Check if form exists
     */
    public function form_exists(int $form_id): bool {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return false;
        }
        $cache_key = "form_exists_{$form_id}";
        $cached_result = $this->cache_manager->get($cache_key);
        
        if ($cached_result !== null) {
            return $cached_result;
        }

        global $wpdb;
        $exists = $this->wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table(); identifiers cannot be parameterized
                "SELECT id FROM `{$table}` WHERE id = %d",
                $form_id
            )
        ) !== null;

        $this->cache_manager->set($cache_key, $exists, 3600);

        return $exists;
    }

    /**
     * Check if table exists
     */
    public function table_exists(): bool {
        global $wpdb;
        $result = $this->wpdb->get_var(
            $wpdb->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $this->table_name)
        );
        return $result === $this->table_name;
    }

    /**
     * Get forms count
     */
    public function get_forms_count(string $search = ''): int {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return 0;
        }
        $cache_key = 'forms_count' . (!empty($search) ? '_' . md5($search) : '');
        $cached_count = $this->cache_manager->get($cache_key);
        
        if ($cached_count !== null) {
            return (int) $cached_count;
        }

        $search_like = $search !== '' ? '%' . $this->wpdb->esc_like($search) . '%' : '';
        global $wpdb;
        $count = $this->wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE (%s = '' OR form_title LIKE %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
                $search_like,
                $search_like
            )
        );
        
        // Ensure we always return an integer, even if the query fails
        $count = $count !== null ? (int) $count : 0;
        
        $this->cache_manager->set($cache_key, $count, 1800);

        return $count;
    }

    /**
     * Get recent forms
     */
    public function get_recent_forms(int $limit = 10): array {
        $table = $this->table_name;
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        global $wpdb;
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` ORDER BY updated_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
                absint($limit)
            )
        );
    }

    /**
     * Clear form cache
     */
    private function clear_form_cache(int $form_id): void {
        $this->cache_manager->delete("form_{$form_id}");
        $this->cache_manager->delete("active_form_{$form_id}");
        $this->cache_manager->delete("form_title_{$form_id}");
        $this->cache_manager->delete("form_fields_{$form_id}");
        $this->cache_manager->delete("form_exists_{$form_id}");
        $this->cache_manager->delete('all_forms_dropdown');
        $this->cache_manager->delete('forms_count');
    }

    /**
     * Log an internal error using the Logger service, falling back to error_log
     */
    private function log_error(string $message, array $context = []): void {
        try {
            $logger = new Logger();
            $logger->error($message, $context);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Connect2Form FormService: ' . $message . ' | Logger failure: ' . $e->getMessage());
            }
        }
    }

    /**
     * Clear all form caches
     */
    public function clear_all_caches(): void {
        $this->cache_manager->delete_pattern('form_*');
        $this->cache_manager->delete_pattern('active_form_*');
        $this->cache_manager->delete_pattern('form_title_*');
        $this->cache_manager->delete_pattern('form_fields_*');
        $this->cache_manager->delete_pattern('form_exists_*');
        $this->cache_manager->delete('all_forms_dropdown');
        $this->cache_manager->delete_pattern('forms_count*');
    }

    /**
     * Bulk delete forms
     */
    public function bulk_delete_forms(array $form_ids): array {
        $results = [
            'deleted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($form_ids as $form_id) {
            if ($this->delete_form($form_id)) {
                $results['deleted']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to delete form ID: {$form_id}";
            }
        }

        return $results;
    }
}
