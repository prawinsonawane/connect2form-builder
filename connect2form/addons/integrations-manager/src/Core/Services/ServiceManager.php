<?php

namespace Connect2Form\Integrations\Core\Services;

/**
 * Service Manager
 *
 * Centralized manager for all database services
 */
class ServiceManager {

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Form service instance
     *
     * @var FormService
     */
    private $form_service;

    /**
     * Submission service instance
     *
     * @var SubmissionService
     */
    private $submission_service;

    /**
     * Database manager instance
     *
     * @var DatabaseManager
     */
    private $database_manager;

    /**
     * Cache manager instance
     *
     * @var CacheManager
     */
    private $cache_manager;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->cache_manager = new CacheManager();
        $this->database_manager = new DatabaseManager();
        $this->form_service = new FormService();
        $this->submission_service = new SubmissionService();
    }

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get form service
     *
     * @return FormService
     */
    public function forms(): FormService {
        return $this->form_service;
    }

    /**
     * Get submission service
     *
     * @return SubmissionService
     */
    public function submissions(): SubmissionService {
        return $this->submission_service;
    }

    /**
     * Get database manager
     *
     * @return DatabaseManager
     */
    public function database(): DatabaseManager {
        return $this->database_manager;
    }

    /**
     * Get cache manager
     *
     * @return CacheManager
     */
    public function cache(): CacheManager {
        return $this->cache_manager;
    }

    /**
     * Initialize all services
     */
    public function init(): void {
        // Ensure database tables exist.
        $this->database_manager->createTables();

        // Initialize cache.
        $this->cache_manager->init();
    }

    /**
     * Clear all caches
     */
    public function clear_all_caches(): void {
        $this->cache_manager->clear_all();

        // Also clear form and submission specific caches.
        $this->form_service->clear_all_caches();
        $this->submission_service->clear_all_caches();
    }

    /**
     * Optimize database tables
     */
    public function optimize_tables(): void {
        $this->database_manager->optimizeTables();
    }

    /**
     * Get database statistics
     *
     * @return array
     */
    public function get_database_stats(): array {
        global $wpdb;

        $stats = array(
            'forms' => 0,
            'submissions' => 0,
            'integration_logs' => 0,
            'form_meta' => 0,
            'integration_settings' => 0,
            'field_mappings' => 0,
        );

        // Count forms.
        $stats['forms'] = $this->form_service->get_forms_count();

        // Count submissions.
        $stats['submissions'] = $this->submission_service->get_submission_stats()['total'];

        // Count integration logs.
        $stats['integration_logs'] = $this->database_manager->getLogsCount();

        // Count form meta.
        $stats['form_meta'] = $this->database_manager->getFormMetaCount();

        // Count integration settings.
        $stats['integration_settings'] = $this->database_manager->getIntegrationSettingsCount();

        // Count field mappings.
        $stats['field_mappings'] = $this->database_manager->getFieldMappingsCount();

        return $stats;
    }

    /**
     * Clean up old data
     *
     * @param int $days_to_keep Number of days to keep data.
     * @return array
     */
    public function cleanup_old_data( int $days_to_keep = 30 ): array {
        $results = array(
            'submissions_deleted' => 0,
            'logs_deleted' => 0,
            'errors' => array(),
        );

        try {
            // Clean old submissions.
            $results['submissions_deleted'] = $this->submission_service->delete_old_submissions( $days_to_keep );
        } catch ( \Exception $e ) {
            $results['errors'][] = 'Failed to clean old submissions: ' . $e->getMessage();
        }

        try {
            // Clean old integration logs.
            $results['logs_deleted'] = $this->database_manager->cleanOldLogs( $days_to_keep );
        } catch ( \Exception $e ) {
            $results['errors'][] = 'Failed to clean old logs: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Export data for backup
     *
     * @return array
     */
    public function export_data(): array {
        $export = array(
            'forms' => array(),
            'submissions' => array(),
            'integration_logs' => array(),
            'form_meta' => array(),
            'integration_settings' => array(),
            'field_mappings' => array(),
            'export_date' => current_time( 'mysql' ),
            'version' => '1.0',
        );

        // Export forms using service classes.
        $export['forms'] = $this->form_service->get_all_forms_for_export();

        // Export submissions (limit to last 1000 to avoid memory issues).
        $export['submissions'] = $this->submission_service->get_recent_submissions_for_export( 1000 );

        // Export integration logs.
        $export['integration_logs'] = $this->database_manager->getAllLogs();

        // Export form meta.
        $export['form_meta'] = $this->database_manager->getAllFormMeta();

        // Export integration settings.
        $export['integration_settings'] = $this->database_manager->getAllIntegrationSettings();

        // Export field mappings.
        $export['field_mappings'] = $this->database_manager->getAllFieldMappings();

        return $export;
    }

    /**
     * Import data from backup
     *
     * @param array $data Data to import.
     * @return array
     */
    public function import_data( array $data ): array {
        $results = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        global $wpdb;

        try {
            // Import forms using service classes.
            if ( ! empty( $data['forms'] ) ) {
                foreach ( $data['forms'] as $form ) {
                    $existing = $this->form_service->get_form( $form['id'] );

                    if ( ! $existing ) {
                        $this->form_service->create_form( $form );
                        $results['imported']++;
                    } else {
                        $results['skipped']++;
                    }
                }
            }

            // Import submissions using service classes.
            if ( ! empty( $data['submissions'] ) ) {
                foreach ( $data['submissions'] as $submission ) {
                    $existing = $this->submission_service->get_submission( $submission['id'] );

                    if ( ! $existing ) {
                        $this->submission_service->create_submission( $submission );
                        $results['imported']++;
                    } else {
                        $results['skipped']++;
                    }
                }
            }

            // Import other data using database manager.
            if ( ! empty( $data['integration_logs'] ) ) {
                foreach ( $data['integration_logs'] as $log ) {
                    $this->database_manager->insertLogEntry( $log );
                    $results['imported']++;
                }
            }

            if ( ! empty( $data['form_meta'] ) ) {
                foreach ( $data['form_meta'] as $meta ) {
                    $this->database_manager->saveFormMeta( $meta['form_id'], $meta['meta_key'], $meta['meta_value'] );
                    $results['imported']++;
                }
            }

            if ( ! empty( $data['integration_settings'] ) ) {
                foreach ( $data['integration_settings'] as $setting ) {
                    $this->database_manager->saveIntegrationSettings( $setting['integration_id'], json_decode( $setting['settings'], true ) );
                    $results['imported']++;
                }
            }

            if ( ! empty( $data['field_mappings'] ) ) {
                foreach ( $data['field_mappings'] as $mapping ) {
                    $this->database_manager->saveFieldMappings( $mapping['form_id'], $mapping['integration_id'], json_decode( $mapping['mappings'], true ) );
                    $results['imported']++;
                }
            }
        } catch ( \Exception $e ) {
            $results['errors'][] = 'Import failed: ' . $e->getMessage();
        }

        // Clear all caches after import.
        $this->clear_all_caches();

        return $results;
    }

    /**
     * Get service health status
     *
     * @return array
     */
    public function get_health_status(): array {
        $status = array(
            'database' => 'healthy',
            'cache' => 'healthy',
            'services' => 'healthy',
            'issues' => array(),
        );

        try {
            // Test database connection using service.
            $this->database_manager->testConnection();
        } catch ( \Exception $e ) {
            $status['database'] = 'error';
            $status['issues'][] = 'Database connection failed: ' . $e->getMessage();
        }

        try {
            // Test cache.
            $this->cache_manager->set( 'health_check', 'ok', 60 );
            $test_value = $this->cache_manager->get( 'health_check' );
            if ( $test_value !== 'ok' ) {
                $status['cache'] = 'warning';
                $status['issues'][] = 'Cache may not be working properly';
            }
        } catch ( \Exception $e ) {
            $status['cache'] = 'error';
            $status['issues'][] = 'Cache failed: ' . $e->getMessage();
        }

        try {
            // Test services.
            $this->form_service->get_forms_count();
            $this->submission_service->get_submission_stats();
        } catch ( \Exception $e ) {
            $status['services'] = 'error';
            $status['issues'][] = 'Services failed: ' . $e->getMessage();
        }

        return $status;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception( esc_html__( 'Cannot unserialize singleton.', 'connect2form' ) );
    }
}
