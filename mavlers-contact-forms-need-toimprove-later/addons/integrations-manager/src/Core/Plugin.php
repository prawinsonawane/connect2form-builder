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
    private $integration_registry;
    private $asset_manager;
    private $admin_manager;

    /**
     * Get plugin instance (singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Prevent multiple initializations
        if (did_action('mavlers_cf_integrations_initialized')) {
            return;
        }
        
        $this->init();
        
        // Mark as initialized
        do_action('mavlers_cf_integrations_initialized');
    }

    /**
     * Initialize plugin components
     */
    private function init() {
        // Fix table structure if needed
        self::fix_table_structure();
        
        // Initialize asset manager
        $this->asset_manager = new AssetManager();
        
        // Initialize integration registry
        $this->integration_registry = new IntegrationRegistry();
        
        // Initialize admin manager if in admin area
        if (is_admin()) {
            if (current_user_can('manage_options')) {
                error_log('DEBUG: Initializing AdminManager for integrations');
            }
            $this->admin_manager = new \MavlersCF\Integrations\Admin\AdminManager($this->integration_registry, $this->asset_manager);
            if (current_user_can('manage_options')) {
                error_log('DEBUG: AdminManager initialized successfully');
            }
        }

        // Register WordPress hooks
        $this->register_hooks();
        
        // Load integrations
        $this->load_integrations();
        
        // Initialize assets
        $this->init_assets();
        
        // Initialize integrations
        $this->init_integrations();
    }
    
    /**
     * Initialize assets after WordPress is loaded
     */
    public function init_assets() {
        $this->asset_manager->init_default_assets();
    }
    
    /**
     * Initialize integrations after WordPress is fully loaded
     */
    public function init_integrations() {
        // This ensures all WordPress functions are available
        if (function_exists('wp_create_nonce')) {
            // Integrations are already loaded in load_integrations()
            // This is just to ensure WordPress is fully loaded
        }
    }

    /**
     * Render integration settings in form builder
     */
    public function render_form_integrations($form_id, $form) {
        if (current_user_can('manage_options')) {
            error_log('DEBUG: render_form_integrations called - Form ID: ' . $form_id);
        }
        
        if (!$this->integration_registry) {
            if (current_user_can('manage_options')) {
                error_log('DEBUG: Integration registry not available');
            }
            return;
        }

        $integrations = $this->integration_registry->getAll();
        
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Found ' . count($integrations) . ' integrations to render');
        }
        
        if (empty($integrations)) {
            if (current_user_can('manage_options')) {
                error_log('DEBUG: No integrations found to render');
            }
            return;
        }

        try {
            echo '<div class="integration-setting">';
            echo '<h4>' . __('Third-Party Integrations', 'mavlers-contact-forms') . '</h4>';
            echo '<div class="integration-list">';
            
            foreach ($integrations as $integration) {
                $integration_id = $integration->getId();
                $integration_name = $integration->getName();
                $integration_description = $integration->getDescription();
                $integration_icon = $integration->getIcon();
                $integration_color = $integration->getColor();
                
                // Check if integration is configured globally
                $is_configured = $integration->isConfigured();
                
                echo '<div class="integration-item" data-integration="' . esc_attr($integration_id) . '">';
                echo '<div class="integration-header">';
                echo '<div class="integration-info">';
                echo '<span class="integration-icon dashicons ' . esc_attr($integration_icon) . '" style="color: ' . esc_attr($integration_color) . ';"></span>';
                echo '<h5>' . esc_html($integration_name) . '</h5>';
                echo '<p class="integration-description">' . esc_html($integration_description) . '</p>';
                echo '</div>';
                echo '<div class="integration-status">';
                if ($is_configured) {
                    echo '<span class="status-badge status-configured">' . __('Configured', 'mavlers-contact-forms') . '</span>';
                } else {
                    echo '<span class="status-badge status-not-configured">' . __('Not Configured', 'mavlers-contact-forms') . '</span>';
                }
                echo '</div>';
                echo '</div>';
                
                // Integration settings form
                echo '<div class="integration-settings-form" style="display: none;">';
                echo '<div class="integration-settings-content">';
                
                // Add enable/disable toggle
                echo '<div class="integration-toggle-section">';
                echo '<label class="integration-toggle-label">';
                echo '<input type="checkbox" class="integration-enable-toggle" data-integration="' . esc_attr($integration_id) . '" ' . ($is_configured ? 'checked' : '') . '>';
                echo '<span class="toggle-slider"></span>';
                echo '<span class="toggle-text">' . __('Enable Integration', 'mavlers-contact-forms') . '</span>';
                echo '</label>';
                echo '</div>';
                
                // Settings container that shows/hides based on toggle
                echo '<div class="integration-settings-container" style="display: ' . ($is_configured ? 'block' : 'none') . ';">';
                
                // Load integration-specific settings template
                $template_file = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/' . ucfirst($integration_id) . '/templates/' . $integration_id . '-form-settings.php';
                if (file_exists($template_file)) {
                    include $template_file;
                } else {
                    echo '<p>' . __('Integration settings template not found.', 'mavlers-contact-forms') . '</p>';
                }
                
                echo '</div>'; // .integration-settings-container
                echo '</div>'; // .integration-settings-content
                echo '</div>'; // .integration-settings-form
                
                echo '</div>'; // .integration-item
            }
            
            echo '</div>';
            echo '</div>';
            
            // Add JavaScript for integration tab functionality
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Toggle integration settings when header is clicked
                $('.integration-header').on('click', function() {
                    var $item = $(this).closest('.integration-item');
                    var $settings = $item.find('.integration-settings-form');
                    
                    // Close other open settings
                    $('.integration-settings-form').not($settings).slideUp();
                    
                    // Toggle current settings
                    $settings.slideToggle();
                });
                
                // Handle enable/disable toggle
                $('.integration-enable-toggle').on('change', function() {
                    var $toggle = $(this);
                    var $container = $toggle.closest('.integration-settings-content').find('.integration-settings-container');
                    var integrationId = $toggle.data('integration');
                    var isEnabled = $toggle.is(':checked');
                    
                    // Show/hide settings container
                    if (isEnabled) {
                        $container.slideDown();
                    } else {
                        $container.slideUp();
                    }
                    
                    // Update status badge
                    var $statusBadge = $toggle.closest('.integration-item').find('.status-badge');
                    if (isEnabled) {
                        $statusBadge.removeClass('status-not-configured').addClass('status-configured').text('Configured');
                    } else {
                        $statusBadge.removeClass('status-configured').addClass('status-not-configured').text('Not Configured');
                    }
                    
                    // Save toggle state
                    saveIntegrationToggleState(integrationId, isEnabled);
                });
                
                // Prevent settings from closing when clicking inside settings
                $('.integration-settings-content').on('click', function(e) {
                    e.stopPropagation();
                });
                
                // Initialize integration settings if form has saved settings
                <?php if ($form && !empty($form->settings)): ?>
                var formSettings = <?php echo json_encode(json_decode($form->settings, true)); ?>;
                if (formSettings.integrations) {
                    // Load saved integration settings
                    Object.keys(formSettings.integrations).forEach(function(integrationId) {
                        var $integration = $('[data-integration="' + integrationId + '"]');
                        if ($integration.length) {
                            // Load saved settings into form fields
                            var settings = formSettings.integrations[integrationId];
                            Object.keys(settings).forEach(function(fieldName) {
                                var $field = $integration.find('[name="' + fieldName + '"]');
                                if ($field.length) {
                                    $field.val(settings[fieldName]);
                                }
                            });
                        }
                    });
                }
                <?php endif; ?>
                
                // Function to save toggle state
                function saveIntegrationToggleState(integrationId, isEnabled) {
                    // You can add AJAX call here to save the toggle state
                    console.log('Integration ' + integrationId + ' toggled to: ' + (isEnabled ? 'enabled' : 'disabled'));
                }
                
                // Auto-save settings when form fields change
                $('.integration-settings-container input, .integration-settings-container select, .integration-settings-container textarea').on('change', function() {
                    var $field = $(this);
                    var $integration = $field.closest('.integration-item');
                    var integrationId = $integration.data('integration');
                    
                    // Auto-save after a short delay
                    clearTimeout(window.integrationSaveTimeout);
                    window.integrationSaveTimeout = setTimeout(function() {
                        saveIntegrationSettings(integrationId);
                    }, 1000);
                });
                
                // Function to save integration settings
                function saveIntegrationSettings(integrationId) {
                    var $integration = $('[data-integration="' + integrationId + '"]');
                    var settings = {};
                    
                    // Collect all form field values
                    $integration.find('input, select, textarea').each(function() {
                        var $field = $(this);
                        var fieldName = $field.attr('name');
                        if (fieldName) {
                            if ($field.attr('type') === 'checkbox') {
                                settings[fieldName] = $field.is(':checked');
                            } else {
                                settings[fieldName] = $field.val();
                            }
                        }
                    });
                    
                    // Add toggle state
                    var $toggle = $integration.find('.integration-enable-toggle');
                    settings.enabled = $toggle.is(':checked');
                    
                    console.log('Saving settings for integration: ' + integrationId, settings);
                    
                    // You can add AJAX call here to save the settings
                    // saveIntegrationSettingsAjax(integrationId, settings);
                }
            });
            </script>
            <?php
            
        } catch (\Exception $e) {
            echo '<p>' . __('Error loading integrations.', 'mavlers-contact-forms') . '</p>';
        }
    }

    /**
     * Capture integration settings when saving forms
     */
    public function capture_integration_settings($settings_data, $form_id) {
        if (!$this->integration_registry) {
            return $settings_data;
        }

        $integrations = $this->integration_registry->getAll();
        $integration_settings = array();

        foreach ($integrations as $integration) {
            $integration_id = $integration->getId();
            
            // Check if integration settings were submitted
            if (isset($_POST['integrations'][$integration_id])) {
                $integration_settings[$integration_id] = $_POST['integrations'][$integration_id];
            }
        }

        // Add integration settings to form settings
        if (!empty($integration_settings)) {
            $settings_data['integrations'] = $integration_settings;
        }

        return $settings_data;
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Form submission processing
        add_action('mavlers_cf_after_submission', [$this, 'process_form_submission'], 10, 2);
        
        // Integration discovery
        add_action('init', [$this, 'discover_integrations']);
        
        // Add form builder integration hook
        add_action('mavlers_cf_render_additional_integrations', [$this, 'render_form_integrations'], 10, 2);
        
        // Add filter to capture integration settings when saving forms
        add_filter('mavlers_cf_settings_data_before_save', [$this, 'capture_integration_settings'], 10, 2);
        
        // Enqueue admin scripts at the proper WordPress hook
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue admin scripts at the proper WordPress hook
     */
    public function enqueue_admin_scripts() {
        // Only enqueue on our plugin pages
        $screen = get_current_screen();
        if (!$screen || !strpos($screen->id, 'mavlers-contact-forms')) {
            return;
        }
        
        $this->asset_manager->enqueue_admin_scripts();
    }

    /**
     * Load available integrations
     */
    public function load_integrations() {
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Loading integrations...');
        }
        
        $integrations_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/';
        
        if (!is_dir($integrations_dir)) {
            if (current_user_can('manage_options')) {
                error_log('DEBUG: Integrations directory not found: ' . $integrations_dir);
            }
            return;
        }

        $integration_folders = array_filter(scandir($integrations_dir), function($item) use ($integrations_dir) {
            return $item !== '.' && $item !== '..' && is_dir($integrations_dir . $item);
        });

        if (current_user_can('manage_options')) {
            error_log('DEBUG: Found integration folders: ' . print_r($integration_folders, true));
        }

        foreach ($integration_folders as $folder) {
            $class_name = "MavlersCF\\Integrations\\{$folder}\\{$folder}Integration";
            $class_file = $integrations_dir . $folder . '/' . $folder . 'Integration.php';
            
            if (current_user_can('manage_options')) {
                error_log('DEBUG: Loading integration: ' . $folder . ' - Class: ' . $class_name);
            }
            
            // Make sure file exists
            if (!file_exists($class_file)) {
                if (current_user_can('manage_options')) {
                    error_log('DEBUG: Integration file not found: ' . $class_file);
                }
                continue;
            }
            
            // Try to load the class if it doesn't exist
            if (!class_exists($class_name)) {
                require_once $class_file;
            }
            
            if (class_exists($class_name)) {
                try {
                    $integration = new $class_name();
                    $this->integration_registry->register($integration);
                    
                    if (current_user_can('manage_options')) {
                        error_log('DEBUG: Successfully loaded integration: ' . $folder);
                    }
                    
                    // AJAX handlers are already registered in the constructor
                    // No need to call register_ajax_handlers() again
                } catch (\Exception $e) {
                    if (current_user_can('manage_options')) {
                        error_log('DEBUG: Failed to load integration ' . $folder . ': ' . $e->getMessage());
                    }
                    // Integration loading failed silently
                }
            } else {
                if (current_user_can('manage_options')) {
                    error_log('DEBUG: Integration class not found: ' . $class_name);
                }
                // Integration loading failed silently
            }
        }
        
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Integration loading completed. Total integrations: ' . count($this->integration_registry->getAll()));
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
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Processing form submission - ID: ' . $submission_id . ', Form ID: ' . ($form_data['form_id'] ?? 'unknown'));
        }
        
        $enabled_integrations = $this->get_enabled_integrations_for_form($form_data['form_id'] ?? 0);
        
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Found enabled integrations: ' . print_r($enabled_integrations, true));
        }
        
        foreach ($enabled_integrations as $integration_id => $settings) {
            $integration = $this->integration_registry->get($integration_id);
            
            if (current_user_can('manage_options')) {
                error_log('DEBUG: Processing integration: ' . $integration_id . ' - Integration found: ' . ($integration ? 'yes' : 'no'));
            }
            
            if ($integration && $integration->isEnabled($settings)) {
                try {
                    // Merge global settings with form-specific settings
                    $global_settings = $integration->getGlobalSettings();
                    $merged_settings = array_merge($global_settings, $settings);
                    
                    if (current_user_can('manage_options')) {
                        error_log('DEBUG: Merged settings for ' . $integration_id . ': ' . print_r($merged_settings, true));
                    }
                    
                    if (current_user_can('manage_options')) {
                        error_log('DEBUG: Calling processSubmission for integration: ' . $integration_id);
                    }
                    $integration->processSubmission($submission_id, $form_data, $merged_settings);
                    if (current_user_can('manage_options')) {
                        error_log('DEBUG: Successfully processed integration: ' . $integration_id);
                    }
                            } catch (\Exception $e) {
                    if (current_user_can('manage_options')) {
                        error_log('DEBUG: Failed to process integration ' . $integration_id . ': ' . $e->getMessage());
                    }
                    // Integration processing failed silently - completely disabled logging for now
                    // $this->log_integration_error($form_data['form_id'], $submission_id, $integration_id, $e->getMessage());
                }
            } else {
                if (current_user_can('manage_options')) {
                    error_log('DEBUG: Integration ' . $integration_id . ' not enabled or not found');
            }
            }
        }
    }

    /**
     * Get enabled integrations for a form
     */
    private function get_enabled_integrations_for_form($form_id) {
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Getting enabled integrations for form ID: ' . $form_id);
        }
        
        // Get integration settings from post meta
        $integration_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Integration settings from post meta: ' . print_r($integration_settings, true));
        }
        
        if (!$integration_settings || !is_array($integration_settings)) {
            if (current_user_can('manage_options')) {
                error_log('DEBUG: No integration settings found');
            }
            return [];
        }
        
        // Filter only enabled integrations
        $enabled_integrations = [];
        foreach ($integration_settings as $integration_id => $settings) {
            if (isset($settings['enabled']) && $settings['enabled']) {
                $enabled_integrations[$integration_id] = $settings;
                if (current_user_can('manage_options')) {
                    error_log('DEBUG: Found enabled integration: ' . $integration_id);
                }
            }
        }
        
        if (current_user_can('manage_options')) {
            error_log('DEBUG: Enabled integrations: ' . print_r($enabled_integrations, true));
        }
        
        return $enabled_integrations;
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
     * Get admin manager
     */
    public function getAdminManager() {
        return $this->admin_manager;
    }

    /**
     * Fix database table structure if needed
     */
    public static function fix_table_structure() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_integration_logs';
        $analytics_table = $wpdb->prefix . 'mavlers_cf_mailchimp_analytics';
        
        // Check if integration logs table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            // Check and add missing columns to integration logs table
            $columns_to_check = [
                'message' => "ALTER TABLE {$table_name} ADD COLUMN message text DEFAULT NULL AFTER status",
                'data' => "ALTER TABLE {$table_name} ADD COLUMN data longtext DEFAULT NULL AFTER message"
            ];
            
            foreach ($columns_to_check as $column => $sql) {
                $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE '{$column}'");
                if (empty($column_exists)) {
                    $wpdb->query($sql);
                    error_log('DEBUG: Added ' . $column . ' column to integration logs table');
                }
            }
        } else {
            // Create table if it doesn't exist
            self::create_tables();
        }
        
        // Check if Mailchimp analytics table exists
        $analytics_exists = $wpdb->get_var("SHOW TABLES LIKE '{$analytics_table}'") === $analytics_table;
        
        if ($analytics_exists) {
            // Check and add missing columns to analytics table
            $analytics_columns = [
                'event_data' => "ALTER TABLE {$analytics_table} ADD COLUMN event_data longtext DEFAULT NULL AFTER event_type"
            ];
            
            foreach ($analytics_columns as $column => $sql) {
                $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$analytics_table} LIKE '{$column}'");
                if (empty($column_exists)) {
                    $wpdb->query($sql);
                    error_log('DEBUG: Added ' . $column . ' column to Mailchimp analytics table');
                }
            }
        } else {
            // Create analytics table if it doesn't exist
            self::create_analytics_table();
        }
    }
    
    /**
     * Create Mailchimp analytics table
     */
    private static function create_analytics_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_mailchimp_analytics';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id mediumint(9) NOT NULL,
            audience_id varchar(50) NOT NULL,
            email varchar(255) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            user_agent text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            referrer text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY audience_id (audience_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        error_log('DEBUG: Created Mailchimp analytics table');
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
        
        // Check if message column exists, if not add it
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'message'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN message text DEFAULT NULL AFTER status");
        }
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