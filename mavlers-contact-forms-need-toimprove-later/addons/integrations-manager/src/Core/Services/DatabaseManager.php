<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * Database Manager
 * 
 * Handles database operations with optimization and proper indexing
 */
class DatabaseManager {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Create optimized database tables
     */
    public function createTables(): void {
        $this->createIntegrationLogsTable();
        $this->createFormMetaTable();
        $this->createIntegrationSettingsTable();
        $this->createFieldMappingsTable();
    }

    /**
     * Create integration logs table with proper indexing
     */
    private function createIntegrationLogsTable(): void {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_integration_logs';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            submission_id bigint(20) unsigned DEFAULT NULL,
            integration_id varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text DEFAULT NULL,
            data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_form_id (form_id),
            KEY idx_integration_id (integration_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            KEY idx_form_integration (form_id, integration_id),
            KEY idx_status_created (status, created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create form meta table for integration settings
     */
    private function createFormMetaTable(): void {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_form_meta';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_form_meta (form_id, meta_key),
            KEY idx_form_id (form_id),
            KEY idx_meta_key (meta_key)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create integration settings table
     */
    private function createIntegrationSettingsTable(): void {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_integration_settings';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id varchar(50) NOT NULL,
            setting_key varchar(255) NOT NULL,
            setting_value longtext DEFAULT NULL,
            setting_type varchar(20) DEFAULT 'string',
            is_encrypted tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_integration_setting (integration_id, setting_key),
            KEY idx_integration_id (integration_id),
            KEY idx_setting_key (setting_key)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create field mappings table
     */
    private function createFieldMappingsTable(): void {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_field_mappings';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            integration_id varchar(50) NOT NULL,
            form_field varchar(255) NOT NULL,
            integration_field varchar(255) NOT NULL,
            field_type varchar(50) DEFAULT 'text',
            is_required tinyint(1) DEFAULT 0,
            mapping_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_field_mapping (form_id, integration_id, form_field),
            KEY idx_form_integration (form_id, integration_id),
            KEY idx_integration_field (integration_id, integration_field)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Insert log entry with proper data handling
     */
    public function insertLogEntry(array $data): int {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_integration_logs';
        
        $insert_data = [
            'form_id' => intval($data['form_id'] ?? 0),
            'submission_id' => intval($data['submission_id'] ?? null),
            'integration_id' => sanitize_text_field($data['integration_id'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'info'),
            'message' => sanitize_textarea_field($data['message'] ?? ''),
            'data' => json_encode($data['data'] ?? [])
        ];

        $result = $this->wpdb->insert($table_name, $insert_data, [
            '%d', '%d', '%s', '%s', '%s', '%s'
        ]);

        return $result ? $this->wpdb->insert_id : 0;
    }

    /**
     * Get logs with pagination and filtering
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_integration_logs';
        
        $where_conditions = [];
        $where_values = [];

        // Apply filters
        if (!empty($filters['integration_id'])) {
            $where_conditions[] = 'integration_id = %s';
            $where_values[] = $filters['integration_id'];
        }

        if (!empty($filters['form_id'])) {
            $where_conditions[] = 'form_id = %d';
            $where_values[] = intval($filters['form_id']);
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($where_values, [$limit, $offset])
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get log statistics
     */
    public function getLogStats(array $filters = []): array {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_integration_logs';
        
        $where_conditions = [];
        $where_values = [];

        // Apply filters
        if (!empty($filters['integration_id'])) {
            $where_conditions[] = 'integration_id = %s';
            $where_values[] = $filters['integration_id'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $sql = $this->wpdb->prepare(
            "SELECT 
                status,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM {$table_name} 
            {$where_clause}
            GROUP BY status, DATE(created_at)
            ORDER BY date DESC, count DESC",
            $where_values
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        $stats = [
            'total' => 0,
            'by_status' => [],
            'by_date' => []
        ];

        foreach ($results as $row) {
            $status = $row['status'];
            $count = intval($row['count']);
            $date = $row['date'];

            $stats['total'] += $count;
            $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + $count;
            $stats['by_date'][$date] = ($stats['by_date'][$date] ?? 0) + $count;
        }

        return $stats;
    }

    /**
     * Save integration settings with encryption support
     */
    public function saveIntegrationSettings(string $integration_id, array $settings): bool {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_integration_settings';
        
        foreach ($settings as $key => $value) {
            $is_encrypted = $this->shouldEncryptSetting($key);
            $setting_value = $is_encrypted ? $this->encryptValue($value) : $value;
            $setting_type = $this->getSettingType($value);

            $data = [
                'integration_id' => $integration_id,
                'setting_key' => $key,
                'setting_value' => $setting_value,
                'setting_type' => $setting_type,
                'is_encrypted' => $is_encrypted ? 1 : 0
            ];

            $this->wpdb->replace($table_name, $data, [
                '%s', '%s', '%s', '%s', '%d'
            ]);
        }

        return true;
    }

    /**
     * Get integration settings
     */
    public function getIntegrationSettings(string $integration_id): array {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_integration_settings';
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT setting_key, setting_value, setting_type, is_encrypted 
             FROM {$table_name} 
             WHERE integration_id = %s",
            $integration_id
        ), ARRAY_A);

        $settings = [];
        foreach ($results as $row) {
            $value = $row['setting_value'];
            
            if ($row['is_encrypted']) {
                $value = $this->decryptValue($value);
            }

            // Convert type if needed
            $value = $this->convertSettingType($value, $row['setting_type']);
            
            $settings[$row['setting_key']] = $value;
        }

        return $settings;
    }

    /**
     * Save field mappings
     */
    public function saveFieldMappings(int $form_id, string $integration_id, array $mappings): bool {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_field_mappings';
        
        // Delete existing mappings
        $this->wpdb->delete($table_name, [
            'form_id' => $form_id,
            'integration_id' => $integration_id
        ], ['%d', '%s']);

        // Insert new mappings
        $order = 0;
        foreach ($mappings as $form_field => $integration_field) {
            $data = [
                'form_id' => $form_id,
                'integration_id' => $integration_id,
                'form_field' => $form_field,
                'integration_field' => $integration_field,
                'mapping_order' => $order++
            ];

            $this->wpdb->insert($table_name, $data, [
                '%d', '%s', '%s', '%s', '%d'
            ]);
        }

        return true;
    }

    /**
     * Get field mappings
     */
    public function getFieldMappings(int $form_id, string $integration_id): array {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_field_mappings';
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT form_field, integration_field 
             FROM {$table_name} 
             WHERE form_id = %d AND integration_id = %s 
             ORDER BY mapping_order ASC",
            $form_id, $integration_id
        ), ARRAY_A);

        $mappings = [];
        foreach ($results as $row) {
            $mappings[$row['form_field']] = $row['integration_field'];
        }

        return $mappings;
    }

    /**
     * Clean old logs
     */
    public function cleanOldLogs(int $days_to_keep = 30): int {
        $table_name = $this->wpdb->prefix . 'mavlers_cf_integration_logs';
        
        $deleted = $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_to_keep
        ));

        return $deleted ?: 0;
    }

    /**
     * Optimize tables
     */
    public function optimizeTables(): void {
        $tables = [
            $this->wpdb->prefix . 'mavlers_cf_integration_logs',
            $this->wpdb->prefix . 'mavlers_cf_form_meta',
            $this->wpdb->prefix . 'mavlers_cf_integration_settings',
            $this->wpdb->prefix . 'mavlers_cf_field_mappings'
        ];

        foreach ($tables as $table) {
            $this->wpdb->query("OPTIMIZE TABLE {$table}");
        }
    }

    /**
     * Check if setting should be encrypted
     */
    private function shouldEncryptSetting(string $key): bool {
        $encrypted_keys = ['api_key', 'access_token', 'password', 'secret'];
        
        foreach ($encrypted_keys as $encrypted_key) {
            if (strpos(strtolower($key), $encrypted_key) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Encrypt sensitive value
     */
    private function encryptValue(string $value): string {
        // Use WordPress wp_encrypt_data if available, otherwise simple base64
        if (function_exists('wp_encrypt_data')) {
            return wp_encrypt_data($value);
        }
        
        return base64_encode($value);
    }

    /**
     * Decrypt sensitive value
     */
    private function decryptValue(string $value): string {
        // Use WordPress wp_decrypt_data if available, otherwise simple base64
        if (function_exists('wp_decrypt_data')) {
            return wp_decrypt_data($value);
        }
        
        return base64_decode($value);
    }

    /**
     * Get setting type
     */
    private function getSettingType($value): string {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_array($value)) {
            return 'array';
        } else {
            return 'string';
        }
    }

    /**
     * Convert setting type
     */
    private function convertSettingType($value, string $type) {
        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'array':
                return is_string($value) ? json_decode($value, true) : $value;
            default:
                return $value;
        }
    }
} 