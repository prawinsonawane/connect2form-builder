<?php

namespace MavlersCF\Integrations\Core;

use MavlersCF\Integrations\Admin\AdminManager;
use MavlersCF\Integrations\Core\Registry\IntegrationRegistry;
use MavlersCF\Integrations\Core\Assets\AssetManager;

/**
 * Main Plugin Class
 * 
 * Clean singleton pattern for plugin initialization
 */
class Plugin {

    private static $instance = null;
    private $admin_manager;
    private $integration_registry;
    private $asset_manager;

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor - singleton pattern
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin components
     */
    private function init() {
        // Initialize core components
        $this->integration_registry = new IntegrationRegistry();
        $this->asset_manager = new AssetManager();
        $this->asset_manager->init_default_assets();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            $this->admin_manager = new AdminManager($this->integration_registry, $this->asset_manager);
        }

        // Register hooks
        $this->register_hooks();
        
        // Load integrations after WordPress is fully loaded and text domain is loaded
        add_action('init', [$this, 'load_integrations'], 15);
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Form submission processing
        add_action('mavlers_cf_after_submission', [$this, 'process_form_submission'], 10, 2);
        
        // Integration discovery
        add_action('init', [$this, 'discover_integrations']);
    }

    /**
     * Load available integrations
     */
    public function load_integrations() {
        $integrations_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/';
        
        if (!is_dir($integrations_dir)) {
            return;
        }

        $integration_folders = array_filter(scandir($integrations_dir), function($item) use ($integrations_dir) {
            return $item !== '.' && $item !== '..' && is_dir($integrations_dir . $item);
        });

        foreach ($integration_folders as $folder) {
            $class_name = "MavlersCF\\Integrations\\{$folder}\\{$folder}Integration";
            $class_file = $integrations_dir . $folder . '/' . $folder . 'Integration.php';
            
            error_log("Plugin: Checking integration folder: {$folder}");
            error_log("Plugin: Class name: {$class_name}");
            error_log("Plugin: Class file: {$class_file}");
            
            // Make sure file exists
            if (!file_exists($class_file)) {
                error_log("Plugin: File does not exist: {$class_file}");
                continue;
            }
            
            error_log("Plugin: File exists, loading class");
            
            // Try to load the class if it doesn't exist
            if (!class_exists($class_name)) {
                require_once $class_file;
                error_log("Plugin: Required file: {$class_file}");
            }
            
            if (class_exists($class_name)) {
                try {
                    error_log("Plugin: Creating integration instance: {$class_name}");
                    $integration = new $class_name();
                    $this->integration_registry->register($integration);
                    error_log("Plugin: Successfully registered integration: {$folder}");
                    
                    // Test if AJAX handlers are registered
                    if (method_exists($integration, 'register_ajax_handlers')) {
                        error_log("Plugin: Integration has register_ajax_handlers method");
                        $integration->register_ajax_handlers();
                        error_log("Plugin: AJAX handlers registered for {$folder}");
                    } else {
                        error_log("Plugin: Integration does not have register_ajax_handlers method");
                    }
                } catch (\Exception $e) {
                    error_log("Integration {$class_name} error: " . $e->getMessage());
                }
            } else {
                error_log("Plugin: Class does not exist after loading: {$class_name}");
            }
        }
    }

    /**
     * Discover integrations action hook
     */
    public function discover_integrations() {
        do_action('mavlers_cf_integrations_loaded', $this->integration_registry);
    }

    /**
     * Process form submission through integrations
     */
    public function process_form_submission($submission_id, $form_data) {
        $enabled_integrations = $this->get_enabled_integrations_for_form($form_data['form_id'] ?? 0);
        
        foreach ($enabled_integrations as $integration_id => $settings) {
            $integration = $this->integration_registry->get($integration_id);
            
            if ($integration && $integration->isEnabled($settings)) {
                try {
                    $integration->processSubmission($submission_id, $form_data, $settings);
                } catch (\Exception $e) {
                    error_log("Integration {$integration_id} error: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get enabled integrations for a form
     */
    private function get_enabled_integrations_for_form($form_id) {
        // Get form settings and return enabled integrations
        $form_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        return is_array($form_settings) ? $form_settings : [];
    }

    /**
     * Get integration registry
     */
    public function getRegistry() {
        return $this->integration_registry;
    }

    /**
     * Get asset manager
     */
    public function getAssetManager() {
        return $this->asset_manager;
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables if needed
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('mavlers_cf_integrations_batch_process');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create necessary database tables
     */
    private static function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mavlers_cf_integration_logs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id mediumint(9) NOT NULL,
            submission_id mediumint(9) DEFAULT NULL,
            integration_id varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text DEFAULT NULL,
            data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY integration_id (integration_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        add_option('mavlers_cf_integrations_version', MAVLERS_CF_INTEGRATIONS_VERSION);
        add_option('mavlers_cf_integrations_settings', [
            'enable_logging' => true,
            'log_retention_days' => 30,
            'batch_processing' => false
        ]);
    }
} 