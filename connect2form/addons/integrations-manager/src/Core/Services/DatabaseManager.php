<?php

namespace Connect2Form\Integrations\Core\Services;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// Justification: MySQL requires backticked identifiers for table/index names which cannot be parameterized.
// In this class we ONLY interpolate validated identifiers (see is_valid_identifier() and c2f_is_valid_prefixed_table()).
// All dynamic values remain safely parameterized via $wpdb->prepare().

/**
 * Database Manager
 *
 * Handles database operations with optimization and proper indexing.
 *
 * @since    2.0.0
 * @access   public
 */
class DatabaseManager {

    /**
     * WordPress database instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      \wpdb    $wpdb    WordPress database instance.
     */
    private $wpdb;

    /**
     * Constructor
     *
     * @since    2.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        try {
            $this->ensureTablesExist();
        } catch ( \Exception $e ) {
            $this->log_error( 'Error ensuring tables exist in DatabaseManager: ' . $e->getMessage() );
        }
    }

    /**
     * Simple logger (avoids surfacing raw DB errors to users).
     *
     * @since    2.0.0
     * @access   private
     * @param    string $message Error message to log.
     */
    private function log_error( string $message ): void {
        if ( function_exists( 'error_log' ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Connect2Form DatabaseManager: ' . $message );
        }
    }

    /**
     * Create tables.
     *
     * @since    2.0.0
     */
    public function createTables(): void {
        $this->ensureTablesExist();
    }

    /**
     * Ensure all required tables exist.
     *
     * @since    2.0.0
     * @access   private
     */
    private function ensureTablesExist(): void {
        $tables = array(
            'connect2form_integration_logs'   => 'createIntegrationLogsTable',
            'connect2form_form_meta'          => 'createFormMetaTable',
            'connect2form_integration_settings' => 'createIntegrationSettingsTable',
            'connect2form_field_mappings'     => 'createFieldMappingsTable',
            'connect2form_mailchimp_subscribers' => 'createMailchimpSubscribersTable',
            'connect2form_mailchimp_campaigns' => 'createMailchimpCampaignsTable',
            'connect2form_mailchimp_batch_queue' => 'createMailchimpBatchQueueTable',
            'connect2form_mailchimp_analytics' => 'createMailchimpAnalyticsTable',
        );

        foreach ( $tables as $table_name => $create_method ) {
            global $wpdb;
        
            $full_table_name = $wpdb->prefix . $table_name;
        
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for schema validation; no caching needed for INFORMATION_SCHEMA queries
            $result = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT TABLE_NAME
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                    $full_table_name
                )
            );
        
            if ( $result !== $full_table_name ) {
                $this->{$create_method}();
            }
        }
        
    }

    /**
     * Create integration logs table.
     *
     * @since    2.0.0
     * @access   private
     */
    private function createIntegrationLogsTable(): void {
        $table_name     = $this->wpdb->prefix . 'connect2form_integration_logs';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
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
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Create form meta table.
     *
     * @since    2.0.0
     * @access   private
     */
    private function createFormMetaTable(): void {
        $table_name     = $this->wpdb->prefix . 'connect2form_form_meta';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_form_id (form_id),
            KEY idx_meta_key (meta_key)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $this->addUniqueKeyIfNotExists( $table_name, 'unique_form_meta', 'form_id,meta_key' );
        
        // Add composite index for better performance on meta_key + meta_value queries.
        $this->addIndexIfNotExists( $table_name, 'idx_meta_key_value', 'meta_key,meta_value(100)' );
    }

    /**
     * Create integration settings table.
     *
     * @since    2.0.0
     * @access   private
     */
    private function createIntegrationSettingsTable(): void {
        $table_name     = $this->wpdb->prefix . 'connect2form_integration_settings';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id varchar(50) NOT NULL,
            setting_key varchar(255) NOT NULL,
            setting_value longtext DEFAULT NULL,
            setting_type varchar(20) DEFAULT 'string',
            is_encrypted tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_integration_id (integration_id),
            KEY idx_setting_key (setting_key)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $this->addUniqueKeyIfNotExists( $table_name, 'unique_integration_setting', 'integration_id,setting_key' );
    }

    /**
     * Create field mappings table.
     *
     * @since    2.0.0
     * @access   private
     */
    private function createFieldMappingsTable(): void {
        $table_name     = $this->wpdb->prefix . 'connect2form_field_mappings';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
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
            KEY idx_form_integration (form_id, integration_id),
            KEY idx_integration_field (integration_id, integration_field)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $this->addUniqueKeyIfNotExists( $table_name, 'unique_field_mapping', 'form_id,integration_id,form_field' );
    }

    /**
     * Create Mailchimp subscribers table.
     *
     * @since    2.0.0
     * @access   private
     */
    private function createMailchimpSubscribersTable(): void {
        $table_name     = $this->wpdb->prefix . 'connect2form_mailchimp_subscribers';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            list_id varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'subscribed',
            merge_fields longtext DEFAULT NULL,
            webhook_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_email_list (email, list_id),
            KEY idx_email (email),
            KEY idx_list_id (list_id),
            KEY idx_status (status)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Create Mailchimp campaigns table.
     *
     * @since    2.0.0
     * @access   private
     */
    private function createMailchimpCampaignsTable(): void {
        $table_name     = $this->wpdb->prefix . 'connect2form_mailchimp_campaigns';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id varchar(50) NOT NULL,
            list_id varchar(50) NOT NULL,
            activity_type varchar(50) NOT NULL,
            activity_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign_id (campaign_id),
            KEY idx_list_id (list_id),
            KEY idx_activity_type (activity_type)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Create Mailchimp batch queue table.
     *
     * @since    2.0.0
     * @access   private
     */
    private function createMailchimpBatchQueueTable(): void {
        $table_name     = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            submission_id bigint(20) unsigned DEFAULT NULL,
            list_id varchar(50) NOT NULL,
            subscriber_data longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            priority int(11) DEFAULT 0,
            retry_count int(11) DEFAULT 0,
            error_message text DEFAULT NULL,
            mailchimp_batch_id varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_priority (priority),
            KEY idx_form_id (form_id),
            KEY idx_list_id (list_id),
            KEY idx_created_at (created_at)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Create Mailchimp analytics table.
     *
     * @since    2.0.0
     * @access   private
     */
    private function createMailchimpAnalyticsTable(): void {
        $table_name     = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            audience_id varchar(50) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_form_id (form_id),
            KEY idx_audience_id (audience_id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Add unique key if it doesn't exist.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $table_name Table name.
     * @param    string $key_name   Key name.
     * @param    string $columns    Columns.
     */
    private function addUniqueKeyIfNotExists( string $table_name, string $key_name, string $columns ): void {
        try {
            // Validate identifiers before concatenating.
            if ( ! $this->is_valid_identifier( $key_name ) || ! $this->are_valid_columns_list( $columns ) ) {
                $this->log_error( "Invalid identifier(s) for unique key: {$key_name} / {$columns}" );
                return;
            }

            // Validate table identifier as well since we're interpolating it.
            if ( ! $this->is_valid_identifier( $table_name, true ) ) {
                $this->log_error( "Invalid table identifier for SHOW INDEX: {$table_name}" );
                return;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier; value is safely prepared
            global $wpdb;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier; SHOW INDEX requires identifier interpolation
            $existing = $this->wpdb->get_results(
                $wpdb->prepare(
                    "SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
                    $key_name
                )
            );

            if ( empty( $existing ) ) {
                // Identifiers cannot be parameterized; we validate above and justify ignore.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- adding validated key/column identifiers.
                $result = $this->wpdb->query( "ALTER TABLE `{$table_name}` ADD UNIQUE KEY `{$key_name}` ({$columns})" );

                if ( false === $result ) {
                    $this->log_error( 'Failed to add unique key ' . $key_name . ' to table ' . $table_name . ': ' . wp_strip_all_tags( (string) $this->wpdb->last_error ) );
                }
            }
        } catch ( \Exception $e ) {
            $this->log_error( 'Error adding unique key ' . $key_name . ' to table ' . $table_name . ': ' . $e->getMessage() );
        }
    }

    /**
     * Add index if it doesn't exist.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $table_name Table name.
     * @param    string $key_name   Key name.
     * @param    string $columns    Columns.
     */
    private function addIndexIfNotExists( string $table_name, string $key_name, string $columns ): void {
        try {
            // Validate identifiers before concatenating.
            if ( ! $this->is_valid_identifier( $key_name ) || ! $this->are_valid_columns_list( $columns ) ) {
                $this->log_error( "Invalid identifier(s) for index: {$key_name} / {$columns}" );
                return;
            }

            // Validate table identifier as well since we're interpolating it.
            if ( ! $this->is_valid_identifier( $table_name, true ) ) {
                $this->log_error( "Invalid table identifier for SHOW INDEX: {$table_name}" );
                return;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier; value is safely prepared
            global $wpdb;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier; SHOW INDEX requires identifier interpolation
            $existing = $this->wpdb->get_results(
                $wpdb->prepare(
                    "SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
                    $key_name
                )
            );

            if ( empty( $existing ) ) {
                // Identifiers cannot be parameterized; we validate above and justify ignore.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- adding validated key/column identifiers.
                $result = $this->wpdb->query( "ALTER TABLE `{$table_name}` ADD INDEX `{$key_name}` ({$columns})" );

                if ( false === $result ) {
                    $this->log_error( 'Failed to add index ' . $key_name . ' to table ' . $table_name . ': ' . wp_strip_all_tags( (string) $this->wpdb->last_error ) );
                }
            }
        } catch ( \Exception $e ) {
            $this->log_error( 'Error adding index ' . $key_name . ' to table ' . $table_name . ': ' . $e->getMessage() );
        }
    }

    /**
     * Insert log entry.
     *
     * @since    2.0.0
     * @param    array $data Log data.
     * @return   int
     * @throws   \RuntimeException If integration ID is missing or insert fails.
     */
    public function insertLogEntry( array $data ): int {
        $table_name = $this->wpdb->prefix . 'connect2form_integration_logs';

        if ( empty( $data['integration_id'] ) ) {
            throw new \RuntimeException( esc_html__( 'Integration ID is required for log entry.', 'connect2form' ) );
        }

        $log_data = array(
            'form_id'        => (int) ( $data['form_id'] ?? 0 ),
            'submission_id'  => ! empty( $data['submission_id'] ) ? (int) $data['submission_id'] : null,
            'integration_id' => sanitize_text_field( $data['integration_id'] ),
            'status'         => sanitize_text_field( $data['status'] ?? 'info' ),
            'message'        => sanitize_textarea_field( $data['message'] ?? '' ),
            'data'           => wp_json_encode( $data['data'] ?? array() ),
            'created_at'     => current_time( 'mysql' ),
        );

        $format  = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' );
        $result  = $this->wpdb->insert( $table_name, $log_data, $format );

        if ( false === $result ) {
            $db_error = isset( $this->wpdb->last_error ) ? wp_strip_all_tags( (string) $this->wpdb->last_error ) : '';
            $this->log_error( 'DB insert failed in DatabaseManager: ' . $db_error );
            throw new \RuntimeException( esc_html__( 'Failed to insert log entry.', 'connect2form' ) );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Delete log entry.
     *
     * @since    2.0.0
     * @param    int $log_id Log ID to delete.
     * @return   bool
     */
    public function deleteLogEntry( int $log_id ): bool {
        $table_name = $this->wpdb->prefix . 'connect2form_integration_logs';

        $result = $this->wpdb->delete(
            $table_name,
            array( 'id' => $log_id ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Get logs with filtering and pagination.
     *
     * @since    2.0.0
     * @param    array $filters Filter criteria.
     * @param    int   $limit   Number of records to return.
     * @param    int   $offset  Number of records to skip.
     * @return   array
     */
    public function getLogs( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
    
        $table  = $this->wpdb->prefix . 'connect2form_integration_logs';
        $limit  = absint( $limit );
        $offset = absint( $offset );
    
        // Normalize filters.
        $integration_id = isset( $filters['integration_id'] ) ? (string) $filters['integration_id'] : '';
        $form_id        = isset( $filters['form_id'] )        ? (int)    $filters['form_id']        : 0;
        $status         = isset( $filters['status'] )         ? (string) $filters['status']         : '';
        $date_from      = isset( $filters['date_from'] )      ? (string) $filters['date_from']      : '';
        $date_to        = isset( $filters['date_to'] )        ? (string) $filters['date_to']        : '';
    
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- logs query with validated table identifier; no caching needed for admin log viewing
        $query = $wpdb->prepare(
            "SELECT *
               FROM `{$table}`
              WHERE
                    (%s = '' OR integration_id = %s)
                AND (%d = 0  OR form_id        = %d)
                AND (%s = '' OR status         = %s)
                AND created_at >= COALESCE(NULLIF(%s, ''), '1000-01-01 00:00:00')
                AND created_at <= COALESCE(NULLIF(%s, ''), '9999-12-31 23:59:59')
              ORDER BY created_at DESC
              LIMIT %d OFFSET %d",
            $integration_id, $integration_id,
            $form_id,        $form_id,
            $status,         $status,
            $date_from,
            $date_to,
            $limit,          $offset
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $query is prepared above; no caching needed for admin log viewing
        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    

    /**
     * Get log statistics (grouped).
     *
     * @since    2.0.0
     * @param    array $filters Filter criteria.
     * @return   array
     */
    public function getLogStats( array $filters = array() ): array {
        global $wpdb;
        $table = $this->wpdb->prefix . 'connect2form_integration_logs';
        if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! c2f_is_valid_prefixed_table( $table ) ) {
            return array( 'total' => 0, 'by_status' => array(), 'by_date' => array() );
        }

        // Normalize filters to avoid building dynamic SQL.
        $integration_id = isset( $filters['integration_id'] ) ? (string) $filters['integration_id'] : '';
        $date_from = isset( $filters['date_from'] ) ? (string) $filters['date_from'] : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- log statistics query with validated table identifier; no caching needed for analytics
        $rows = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count, DATE(created_at) as date 
                 FROM `{$table}`
                 WHERE (%s = '' OR integration_id = %s)
                   AND created_at >= COALESCE(NULLIF(%s, ''), '1000-01-01 00:00:00')
                 GROUP BY status, DATE(created_at) 
                 ORDER BY date DESC, count DESC",
                $integration_id, $integration_id,
                $date_from
            ),
            ARRAY_A
        );

        $out = ['total'=>0,'by_status'=>[],'by_date'=>[]];
        foreach ($rows as $r) {
            $c = (int) $r['count'];
            $out['total'] += $c;
            $out['by_status'][$r['status']] = ($out['by_status'][$r['status']] ?? 0) + $c;
            $out['by_date'][$r['date']] = ($out['by_date'][$r['date']] ?? 0) + $c;
        }
        return $out;
    }

    /**
     * Save integration settings.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @param    array  $settings      Settings to save.
     * @return   bool
     */
    public function saveIntegrationSettings( string $integration_id, array $settings ): bool {
        $table = $this->wpdb->prefix . 'connect2form_integration_settings';

        foreach ( $settings as $key => $value ) {
            $is_encrypted  = $this->shouldEncryptSetting( $key );
            $setting_value = $is_encrypted ? $this->encryptValue( (string) $value ) : $value;
            $setting_type  = $this->getSettingType( $value );

            $data = array(
                'integration_id' => $integration_id,
                'setting_key'    => $key,
                'setting_value'  => $setting_value,
                'setting_type'   => $setting_type,
                'is_encrypted'   => $is_encrypted ? 1 : 0,
            );

            $this->wpdb->replace(
                $table,
                $data,
                array( '%s', '%s', '%s', '%s', '%d' )
            );
        }

        return true;
    }

    /**
     * Get integration settings.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @return   array
     */
    public function getIntegrationSettings( string $integration_id ): array {
        $table   = $this->wpdb->prefix . 'connect2form_integration_settings';
        if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! c2f_is_valid_prefixed_table( $table ) ) {
            return array();
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integration settings query with validated table identifier; no caching needed for settings retrieval
        $results = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT setting_key, setting_value, setting_type, is_encrypted FROM `{$table}` WHERE integration_id = %s",
                $integration_id
            ),
            ARRAY_A
        );

        $settings = [];
        foreach ( $results as $row ) {
            $value = $row['setting_value'];
            if ( (int) $row['is_encrypted'] === 1 ) {
                $value = $this->decryptValue( (string) $value );
            }
            $settings[ $row['setting_key'] ] = $this->convertSettingType( $value, $row['setting_type'] );
        }

        return $settings;
    }

    public function saveFieldMappings( int $form_id, string $integration_id, array $mappings ): bool {
        $table = $this->wpdb->prefix . 'connect2form_field_mappings';

        $this->wpdb->delete(
            $table,
            [
                'form_id'        => $form_id,
                'integration_id' => $integration_id,
            ],
            [ '%d', '%s' ]
        );

        $order = 0;
        foreach ( $mappings as $form_field => $integration_field ) {
            $this->wpdb->insert(
                $table,
                [
                    'form_id'           => $form_id,
                    'integration_id'    => $integration_id,
                    'form_field'        => $form_field,
                    'integration_field' => $integration_field,
                    'mapping_order'     => $order++,
                ],
                [ '%d', '%s', '%s', '%s', '%d' ]
            );
        }

        return true;
    }

    public function getFieldMappings( int $form_id, string $integration_id ): array {
        global $wpdb;
        $table = $this->wpdb->prefix . 'connect2form_field_mappings';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) {
            return [];
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- field mappings query with validated table identifier; no caching needed for mapping retrieval
        $rows = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT form_field, integration_field FROM `{$table}` WHERE form_id = %d AND integration_id = %s ORDER BY mapping_order ASC",
                $form_id, $integration_id
            ),
            ARRAY_A
        );
        $map = [];
        foreach ($rows as $r) { $map[$r['form_field']] = $r['integration_field']; }
        return $map;
    }

    public function cleanOldLogs( int $days_to_keep = 30 ): int {
        global $wpdb;
        $table = $this->wpdb->prefix . 'connect2form_integration_logs';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return 0; }
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- cleanup operation with validated table identifier; no caching needed for maintenance operations
            $deleted = $this->wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    absint($days_to_keep)
                )
            );
            return $deleted ?: 0;
        } catch ( \Exception $e ) {
            $this->log_error( 'Error cleaning old logs: ' . $e->getMessage() );
            return 0;
        }
    }

    /**
     * Optimize tables (identifiers validated + justified ignore)
     */
    public function optimizeTables(): void {
        $tables = [
            'connect2form_integration_logs',
            'connect2form_form_meta',
            'connect2form_integration_settings',
            'connect2form_field_mappings',
            'connect2form_mailchimp_subscribers',
            'connect2form_mailchimp_campaigns',
            'connect2form_mailchimp_batch_queue',
            'connect2form_mailchimp_analytics',
        ];

        foreach ( $tables as $t ) {
            try {
                if ( $this->tableExists( $t ) && $this->is_valid_identifier( $t, true ) ) {
                    $table = $this->wpdb->prefix . $t;
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table optimization with validated identifier; no caching needed for maintenance operations
        $this->wpdb->query( "OPTIMIZE TABLE `{$table}`" );
                }
            } catch ( \Exception $e ) {
                $this->log_error( 'Error optimizing table ' . $t . ': ' . $e->getMessage() );
            }
        }
    }

    private function shouldEncryptSetting( string $key ): bool {
        try {
            foreach ( [ 'api_key', 'access_token', 'password', 'secret' ] as $needle ) {
                if ( strpos( strtolower( $key ), $needle ) !== false ) {
                    return true;
                }
            }
            return false;
        } catch ( \Exception $e ) {
            $this->log_error( 'Error checking if setting should be encrypted: ' . $e->getMessage() );
            return false;
        }
    }

    private function encryptValue( string $value ): string {
        try {
            if ( function_exists( 'wp_encrypt_data' ) ) {
                return (string) wp_encrypt_data( $value );
            }
            return base64_encode( $value );
        } catch ( \Exception $e ) {
            $this->log_error( 'Error encrypting value: ' . $e->getMessage() );
            return base64_encode( $value );
        }
    }

    private function decryptValue( string $value ): string {
        try {
            if ( function_exists( 'wp_decrypt_data' ) ) {
                return (string) wp_decrypt_data( $value );
            }
            return (string) base64_decode( $value );
        } catch ( \Exception $e ) {
            $this->log_error( 'Error decrypting value: ' . $e->getMessage() );
            return (string) base64_decode( $value );
        }
    }

    private function getSettingType( $value ): string {
        try {
            if ( is_bool( $value ) ) {
                return 'boolean';
            } elseif ( is_int( $value ) ) {
                return 'integer';
            } elseif ( is_float( $value ) ) {
                return 'float';
            } elseif ( is_array( $value ) ) {
                return 'array';
            }
            return 'string';
        } catch ( \Exception $e ) {
            $this->log_error( 'Error getting setting type: ' . $e->getMessage() );
            return 'string';
        }
    }

    private function convertSettingType( $value, string $type ) {
        try {
            switch ( $type ) {
                case 'boolean':
                    return (bool) $value;
                case 'integer':
                    return (int) $value;
                case 'float':
                    return (float) $value;
                case 'array':
                    if ( is_string( $value ) ) {
                        $decoded = json_decode( $value, true );
                        return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : [];
                    }
                    return is_array( $value ) ? $value : [];
                default:
                    return $value;
            }
        } catch ( \Exception $e ) {
            $this->log_error( 'Error converting setting type: ' . $e->getMessage() );
            return $value;
        }
    }

    public function getFormMeta( int $form_id, string $meta_key ): ?string {
        $table = $this->wpdb->prefix . 'connect2form_form_meta';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) {
            return null;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- form meta query with validated table identifier; no caching needed for meta retrieval
        return $this->wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM `{$table}` WHERE form_id = %d AND meta_key = %s",
                $form_id,
                $meta_key
            )
        );
    }

    public function saveFormMeta( int $form_id, string $meta_key, string $meta_value ): bool {
        $table = $this->wpdb->prefix . 'connect2form_form_meta';

        try {
            $result = $this->wpdb->replace(
                $table,
                [
                    'form_id'    => $form_id,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- meta_key is indexed for performance
                    'meta_key'   => $meta_key,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- meta_value is necessary for form metadata storage
                    'meta_value' => $meta_value,
                ],
                [ '%d', '%s', '%s' ]
            );
            return false !== $result;
        } catch ( \Exception $e ) {
            $this->log_error( 'Error saving form meta: ' . $e->getMessage() );
            return false;
        }
    }

    public function getLogsCount(): int {
        $table = $this->wpdb->prefix . 'connect2form_integration_logs';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return 0; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- count query with validated table identifier; no caching needed for real-time counts
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    public function getFormMetaCount(): int {
        $table = $this->wpdb->prefix . 'connect2form_form_meta';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return 0; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- count query with validated table identifier; no caching needed for real-time counts
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    public function getIntegrationSettingsCount(): int {
        $table = $this->wpdb->prefix . 'connect2form_integration_settings';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return 0; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- count query with validated table identifier; no caching needed for real-time counts
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    public function getFieldMappingsCount(): int {
        $table = $this->wpdb->prefix . 'connect2form_field_mappings';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return 0; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- count query with validated table identifier; no caching needed for real-time counts
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    public function getAllLogs(): array {
        $table = $this->wpdb->prefix . 'connect2form_integration_logs';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return []; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- data retrieval query with validated table identifier; no caching needed for admin operations
        return $this->wpdb->get_results("SELECT * FROM `{$table}` ORDER BY created_at ASC", ARRAY_A);
    }

    public function getAllFormMeta(): array {
        $table = $this->wpdb->prefix . 'connect2form_form_meta';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return []; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- data retrieval query with validated table identifier; no caching needed for admin operations
        return $this->wpdb->get_results("SELECT * FROM `{$table}` ORDER BY created_at ASC", ARRAY_A);
    }

    public function getAllIntegrationSettings(): array {
        $table = $this->wpdb->prefix . 'connect2form_integration_settings';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return []; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- data retrieval query with validated table identifier; no caching needed for admin operations
        return $this->wpdb->get_results("SELECT * FROM `{$table}` ORDER BY created_at ASC", ARRAY_A);
    }

    public function getAllFieldMappings(): array {
        $table = $this->wpdb->prefix . 'connect2form_field_mappings';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return []; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- data retrieval query with validated table identifier; no caching needed for admin operations
        return $this->wpdb->get_results("SELECT * FROM `{$table}` ORDER BY created_at ASC", ARRAY_A);
    }

    public function testConnection(): void {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- constant expression.
        $this->wpdb->get_var( 'SELECT 1' );
    }

    public function tableExists( string $table_name ): bool {
        global $wpdb;
        try {
            $full = $this->wpdb->prefix . $table_name;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for schema validation; no caching needed for INFORMATION_SCHEMA queries
            $res  = $wpdb->get_var( $wpdb->prepare( 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $full ) );
            return $res === $full;
        } catch ( \Exception $e ) {
            $this->log_error( 'Error checking if table exists: ' . $e->getMessage() );
            return false;
        }
    }

    public function getSubscriberRecord( string $email, string $list_id ): ?object {
        global $wpdb;
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_subscribers';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return null; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- subscriber record query with validated table identifier; no caching needed for real-time subscriber data
        return $this->wpdb->get_row(
            $wpdb->prepare("SELECT id FROM `{$table}` WHERE email = %s AND list_id = %s", $email, $list_id)
        );
    }

    public function updateSubscriberStatus( string $email, string $list_id, string $status, array $event_data ): bool {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_subscribers';

        $data = [
            'email'        => $email,
            'list_id'      => $list_id,
            'status'       => $status,
            'last_updated' => current_time( 'mysql' ),
            'webhook_data' => wp_json_encode( $event_data ),
        ];

        $existing = $this->getSubscriberRecord( $email, $list_id );

        if ( $existing ) {
            return false !== $this->wpdb->update( $table, $data, [ 'id' => $existing->id ] );
        }

        $data['created_at'] = current_time( 'mysql' );
        return false !== $this->wpdb->insert( $table, $data );
    }

    public function updateSubscriberProfile( string $email, string $list_id, array $event_data ): bool {
        $table        = $this->wpdb->prefix . 'connect2form_mailchimp_subscribers';
        $merge_fields = wp_json_encode( $event_data['merges'] ?? [] );

        return false !== $this->wpdb->update(
            $table,
            [
                'merge_fields' => $merge_fields,
                'last_updated' => current_time( 'mysql' ),
                'webhook_data' => wp_json_encode( $event_data ),
            ],
            [
                'email'   => $email,
                'list_id' => $list_id,
            ]
        );
    }

    public function updateSubscriberEmail( string $old_email, string $new_email, string $list_id, array $event_data ): bool {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_subscribers';

        return false !== $this->wpdb->update(
            $table,
            [
                'email'        => $new_email,
                'last_updated' => current_time( 'mysql' ),
                'webhook_data' => wp_json_encode( $event_data ),
            ],
            [
                'email'   => $old_email,
                'list_id' => $list_id,
            ]
        );
    }

    public function storeCampaignActivity( string $campaign_id, string $list_id, array $event_data ): bool {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_campaigns';

        $data = [
            'campaign_id'   => $campaign_id,
            'list_id'       => $list_id,
            'activity_type' => $event_data['type'] ?? 'unknown',
            'activity_data' => wp_json_encode( $event_data ),
            'created_at'    => current_time( 'mysql' ),
        ];

        return false !== $this->wpdb->insert( $table, $data );
    }

    public function insertBatchQueueItem( array $queue_data ): int {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';

        try {
            $ok = $this->wpdb->insert( $table, $queue_data );
            if ( false !== $ok ) {
                return (int) $this->wpdb->insert_id;
            }
            $this->log_error( 'Failed to insert batch queue item: ' . wp_strip_all_tags( (string) $this->wpdb->last_error ) );
            return 0;
        } catch ( \Exception $e ) {
            $this->log_error( 'Error inserting batch queue item: ' . $e->getMessage() );
            return 0;
        }
    }

    public function getPendingBatchItems( int $max_batch_size ): array {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) {
            return [];
        }
        $max   = absint( $max_batch_size );

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- batch items query with validated table identifier; no caching needed for real-time batch processing
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE status IN ('pending', 'retrying') ORDER BY priority DESC, created_at ASC LIMIT %d",
                $max
            )
        );
    }

    public function getBatchItem( int $item_id ): ?object {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) {
            return null;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- batch item query with validated table identifier; no caching needed for real-time batch processing
        return $this->wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE id = %d",
                $item_id
            )
        );
    }

    public function updateBatchItemStatus( int $item_id, string $status, ?string $error_message = null ): bool {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';

        $data = [
            'status'     => $status,
            'updated_at' => current_time( 'mysql' ),
        ];
        if ( null !== $error_message ) {
            $data['error_message'] = $error_message;
        }

        return false !== $this->wpdb->update( $table, $data, [ 'id' => $item_id ] );
    }

    public function updateBatchItemMailchimpBatchId( int $item_id, string $mailchimp_batch_id ): bool {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';

        return false !== $this->wpdb->update(
            $table,
            [
                'mailchimp_batch_id' => $mailchimp_batch_id,
                'updated_at'         => current_time( 'mysql' ),
            ],
            [ 'id' => $item_id ]
        );
    }

    public function updateBatchItemsStatus( array $item_ids, string $status ): bool {
        if ( empty( $item_ids ) ) { return true; }
    
        global $wpdb;
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return false; }
    
        try {
            // Sanitize and prepare the item IDs
            $ids = array_map('absint', $item_ids);
    
            // Generate the correct number of placeholders for the IN clause
            $ph = implode(',', array_fill(0, count($ids), '%d'));
    
            // Merge the status, updated_at, and IDs into the arguments for the query
            $args = array_merge([ $status, current_time('mysql') ], $ids);
    
            // Prepare and execute the query
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE `{$table}` SET status = %s, updated_at = %s WHERE id IN ({$ph})",
                    ...$args // Spread the arguments correctly
                )
            );
    
            // Log error if query fails
            if ( false === $result ) {
                $this->log_error('Failed to update batch items status: ' . wp_strip_all_tags((string) $wpdb->last_error));
            }
    
            return false !== $result;
        } catch ( \Exception $e ) {
            $this->log_error('Error updating batch items status: ' . $e->getMessage());
            return false;
        }
    }
    

    public function getPendingBatchCount(): int {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return 0; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- batch count query with validated table identifier; no caching needed for real-time batch counts
        $count = $this->wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE status IN ('pending','retrying')" );
        return (int) ( $count ?? 0 );
    }

    public function cleanupOldBatchItems( int $days_to_keep = 30 ): int {
        global $wpdb;
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return 0; }

        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- batch cleanup operation with validated table identifier; no caching needed for maintenance operations
            $deleted = $this->wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) AND status IN ('completed','failed')",
                    absint($days_to_keep)
                )
            );
            return $deleted ?: 0;
        } catch ( \Exception $e ) {
            $this->log_error('Error cleaning up old batch items: ' . $e->getMessage());
            return 0;
        }
    }

    public function getBatchStatistics(): array {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) {
            return ['total'=>0,'pending'=>0,'processing'=>0,'completed'=>0,'failed'=>0,'retrying'=>0];
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- batch statistics query with validated table identifier; no caching needed for real-time statistics
        $s = $this->wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status='pending'    THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status='completed'  THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status='failed'     THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status='retrying'   THEN 1 ELSE 0 END) as retrying
             FROM `{$table}`"
        );
        if ( ! $s ) { return ['total'=>0,'pending'=>0,'processing'=>0,'completed'=>0,'failed'=>0,'retrying'=>0]; }
        return [
            'total'      => (int) ($s->total ?? 0),
            'pending'    => (int) ($s->pending ?? 0),
            'processing' => (int) ($s->processing ?? 0),
            'completed'  => (int) ($s->completed ?? 0),
            'failed'     => (int) ($s->failed ?? 0),
            'retrying'   => (int) ($s->retrying ?? 0),
        ];
    }

    public function updateBatchItemRetryCount( int $item_id, int $retry_count ): bool {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';

        return false !== $this->wpdb->update(
            $table,
            [
                'retry_count' => $retry_count,
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ 'id' => $item_id ]
        );
    }

    public function retryFailedBatchItems(): int {
        $table   = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        $updated = $this->wpdb->update(
            $table,
            [
                'status'       => 'pending',
                'retry_count'  => 0,
                'error_message'=> null,
                'updated_at'   => current_time( 'mysql' ),
            ],
            [ 'status' => 'failed' ]
        );
        return $updated ?: 0;
    }

    public function clearCompletedFailedBatchItems(): int {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_batch_queue';
        if ( function_exists('c2f_is_valid_prefixed_table') && ! c2f_is_valid_prefixed_table($table) ) { return 0; }

        try {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- batch cleanup operation with validated table identifier; no caching needed for maintenance operations
            $deleted = $this->wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `{$table}` WHERE status IN (%s, %s)",
                    'completed',
                    'failed'
                )
            );
            return $deleted ?: 0;
        } catch ( \Exception $e ) {
            $this->log_error( 'Error clearing completed and failed batch items: ' . $e->getMessage() );
            return 0;
        }
    }

    public function insertAnalyticsEvent( array $event_data ): int {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';

        try {
            $ok = $this->wpdb->insert( $table, $event_data );
            if ( false !== $ok ) {
                return (int) $this->wpdb->insert_id;
            }
            $this->log_error( 'Failed to insert analytics event: ' . wp_strip_all_tags( (string) $this->wpdb->last_error ) );
            return 0;
        } catch ( \Exception $e ) {
            $this->log_error( 'Error inserting analytics event: ' . $e->getMessage() );
            return 0;
        }
    }

    public function getAnalyticsStats( array $filters = [] ): array {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [
                'total_events'   => 0,
                'subscriptions'  => 0,
                'unsubscriptions'=> 0,
                'errors'         => 0,
            ];
        }

        // Normalize filters to avoid building dynamic SQL
        $form_id = isset($filters['form_id']) ? (int) $filters['form_id'] : 0;
        $audience_id = isset($filters['audience_id']) ? (string) $filters['audience_id'] : '';

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- analytics statistics query with validated table identifier; no caching needed for analytics reports
        $row = $this->wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_events,
                    SUM(CASE WHEN event_type = 'subscribe' THEN 1 ELSE 0 END) as subscriptions,
                    SUM(CASE WHEN event_type = 'unsubscribe' THEN 1 ELSE 0 END) as unsubscriptions,
                    SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors
                  FROM `{$table}`
                  WHERE (%d = 0 OR form_id = %d)
                    AND (%s = '' OR audience_id = %s)",
                $form_id, $form_id,
                $audience_id, $audience_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return [
                'total_events'   => 0,
                'subscriptions'  => 0,
                'unsubscriptions'=> 0,
                'errors'         => 0,
            ];
        }

        return [
            'total_events'    => (int) ( $row['total_events'] ?? 0 ),
            'subscriptions'   => (int) ( $row['subscriptions'] ?? 0 ),
            'unsubscriptions' => (int) ( $row['unsubscriptions'] ?? 0 ),
            'errors'          => (int) ( $row['errors'] ?? 0 ),
        ];
    }

    public function getTopForms( int $limit = 10 ): array {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        $limit = absint( $limit );

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- analytics query with validated table identifier; no caching needed for analytics reports
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT form_id, COUNT(*) as event_count
                 FROM `{$table}`
                 WHERE event_type = 'subscribe'
                 GROUP BY form_id
                 ORDER BY event_count DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    public function getRecentActivity( int $limit = 20 ): array {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }
        $limit = absint( $limit );

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- analytics query with validated table identifier; no caching needed for analytics reports
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    public function getAnalyticsData( array $filters = [], string $group_by = 'date' ): array {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [];
        }

        // Normalize filters to avoid building dynamic SQL
        $form_id = isset($filters['form_id']) ? (int) $filters['form_id'] : 0;
        $audience_id = isset($filters['audience_id']) ? (string) $filters['audience_id'] : '';

        // Build the group clause based on the parameter
        $group_clause = '';
        switch ( $group_by ) {
            case 'hour':
                $group_clause = 'GROUP BY DATE(created_at), HOUR(created_at)';
                break;
            case 'form':
                $group_clause = 'GROUP BY form_id';
                break;
            default:
                $group_clause = 'GROUP BY DATE(created_at)';
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- analytics data query with validated table identifier; no caching needed for analytics reports
        return $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    DATE(created_at) as date,
                    COUNT(*) as total_events,
                    SUM(CASE WHEN event_type = 'subscribe' THEN 1 ELSE 0 END) as subscriptions,
                    SUM(CASE WHEN event_type = 'unsubscribe' THEN 1 ELSE 0 END) as unsubscriptions,
                    SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors
                  FROM `{$table}`
                  WHERE (%d = 0 OR form_id = %d)
                    AND (%s = '' OR audience_id = %s)
                  {$group_clause} 
                  ORDER BY date DESC",
                $form_id, $form_id,
                $audience_id, $audience_id
            ),
            ARRAY_A
        );
    }

    public function getFormAnalytics( int $form_id ): array {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [ 'analytics' => [], 'errors' => [], 'hourly_pattern' => [] ];
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- form analytics query with validated table identifier; no caching needed for analytics reports
        $analytics = $this->wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_events,
                    SUM(CASE WHEN event_type = 'subscribe' THEN 1 ELSE 0 END) as subscriptions,
                    SUM(CASE WHEN event_type = 'unsubscribe' THEN 1 ELSE 0 END) as unsubscriptions,
                    SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors
                 FROM `{$table}` WHERE form_id = %d",
                $form_id
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- analytics errors query with validated table identifier; no caching needed for analytics reports
        $errors = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE form_id = %d AND event_type = 'error' ORDER BY created_at DESC LIMIT 10",
                $form_id
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- analytics hourly pattern query with validated table identifier; no caching needed for analytics reports
        $hourly = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT HOUR(created_at) as hour, COUNT(*) as event_count
                 FROM `{$table}` WHERE form_id = %d
                 GROUP BY HOUR(created_at) ORDER BY hour",
                $form_id
            ),
            ARRAY_A
        );

        return [
            'analytics'      => $analytics,
            'errors'         => $errors,
            'hourly_pattern' => $hourly,
        ];
    }

    public function getAudienceAnalytics( string $audience_id ): array {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return [ 'analytics' => [], 'top_forms' => [] ];
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- form analytics query with validated table identifier; no caching needed for analytics reports
        $analytics = $this->wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_events,
                    SUM(CASE WHEN event_type = 'subscribe' THEN 1 ELSE 0 END) as subscriptions,
                    SUM(CASE WHEN event_type = 'unsubscribe' THEN 1 ELSE 0 END) as unsubscriptions,
                    SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors
                 FROM `{$table}` WHERE audience_id = %s",
                $audience_id
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- analytics top forms query with validated table identifier; no caching needed for analytics reports
        $top_forms = $this->wpdb->get_results(
            $wpdb->prepare(
                "SELECT form_id, COUNT(*) as event_count
                 FROM `{$table}` WHERE audience_id = %s AND event_type = 'subscribe'
                 GROUP BY form_id ORDER BY event_count DESC LIMIT 10",
                $audience_id
            ),
            ARRAY_A
        );

        return [
            'analytics' => $analytics,
            'top_forms' => $top_forms,
        ];
    }

    public function cleanupOldAnalytics( int $days_to_keep = 90 ): int {
        $table = $this->wpdb->prefix . 'connect2form_mailchimp_analytics';
        if (function_exists('c2f_is_valid_prefixed_table') && !c2f_is_valid_prefixed_table($table)) {
            return 0;
        }
        try {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- analytics cleanup operation with validated table identifier; no caching needed for maintenance operations
            $deleted = $this->wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    absint($days_to_keep)
                )
            );
            return $deleted ?: 0;
        } catch ( \Exception $e ) {
            $this->log_error( 'Error cleaning up old analytics data: ' . $e->getMessage() );
            return 0;
        }
    }

    /**
     * --- Helpers to validate identifiers for safe concatenation ---
     */

    private function is_valid_identifier( string $identifier, bool $allow_prefix = false ): bool {
        // allow wpdb->prefix + table_name when $allow_prefix is true (prefix may contain digits/underscores)
        if ( $allow_prefix && strpos( $identifier, $this->wpdb->prefix ) === 0 ) {
            $identifier = substr( $identifier, strlen( $this->wpdb->prefix ) );
        }
        return (bool) preg_match( '/^[A-Za-z0-9_]+$/', $identifier );
    }

    private function are_valid_columns_list( string $columns ): bool {
        // e.g., "form_id,meta_key" or "form_id, meta_key"
        return (bool) preg_match( '/^[A-Za-z0-9_]+(\s*,\s*[A-Za-z0-9_]+)*$/', $columns );
    }
}
