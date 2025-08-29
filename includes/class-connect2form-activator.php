<?php
/**
 * Connect2Form Activator Class
 *
 * @package Connect2Form
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Connect2Form_Activator {
    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'connect2form_forms';
        $submissions_table = $wpdb->prefix . 'connect2form_submissions';

        // Check if tables already exist using service class
        $forms_exists = false;
        $submissions_exists = false;
        
        if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $forms_exists = $service_manager->forms()->table_exists();
            $submissions_exists = $service_manager->submissions()->table_exists();
        } else {
            // Fallback to direct database call if service not available
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for plugin activation; no caching needed for INFORMATION_SCHEMA queries
            $forms_exists = $wpdb->get_var($wpdb->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $forms_table)) === $forms_table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for plugin activation; no caching needed for INFORMATION_SCHEMA queries
            $submissions_exists = $wpdb->get_var($wpdb->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $submissions_table)) === $submissions_table;
        }

        // Only create tables if they don't exist
        if (!$forms_exists) {
            // Create forms table
            $sql = "CREATE TABLE $forms_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                form_title varchar(255) NOT NULL,
                fields longtext,
                settings longtext,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) " . $wpdb->get_charset_collate() . ";";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        if (!$submissions_exists) {
            // Create submissions table
            $sql = "CREATE TABLE $submissions_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                form_id mediumint(9) NOT NULL,
                data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY form_id (form_id)
            ) " . $wpdb->get_charset_collate() . ";";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // Create addon integration tables if they don't exist
        self::create_integration_tables();

        // Run lightweight migrations to align schema on existing installs
        self::migrate_tables();
    }

    /**
     * Create integration tables
     */
    private static function create_integration_tables() {
        // Check if integration tables exist using service class
        $forms_exists = false;
        $submissions_exists = false;
        
        if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $forms_exists = $service_manager->forms()->table_exists();
            $submissions_exists = $service_manager->submissions()->table_exists();
        } else {
            // Fallback to direct database call if service not available
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for plugin activation; no caching needed for INFORMATION_SCHEMA queries
            $forms_exists = $wpdb->get_var($wpdb->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $wpdb->prefix . 'connect2form_forms')) === $wpdb->prefix . 'connect2form_forms';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for plugin activation; no caching needed for INFORMATION_SCHEMA queries
            $submissions_exists = $wpdb->get_var($wpdb->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $wpdb->prefix . 'connect2form_submissions')) === $wpdb->prefix . 'connect2form_submissions';
        }

        // Integration-specific tables are handled by the Integrations Manager addon during its own activation.
    }

    /**
     * Ensure schema is up-to-date (idempotent migrations)
     */
    private static function migrate_tables() {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'connect2form_forms';

        // Add missing 'status' column to forms table if not present
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check during activation; no caching needed for one-time activation operation
            $has_status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
                    $forms_table
                )
            );
            if ($has_status !== 'status') {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- schema migration required to align versions; table name is built from trusted prefix; no caching needed for schema changes; ALTER TABLE cannot use prepared statements; schema changes are one-time operations during activation
                $wpdb->query("ALTER TABLE `{$forms_table}` ADD COLUMN status varchar(20) NOT NULL DEFAULT 'active' AFTER settings");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- adding index for performance; table name is built from trusted prefix; no caching needed for schema changes; ALTER TABLE cannot use prepared statements; schema changes are one-time operations during activation
                $wpdb->query("ALTER TABLE `{$forms_table}` ADD INDEX idx_status (status)");
            }
        } catch (\Throwable $e) {
            // Avoid fatal during activation if ALTER fails
        }
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        // Default settings
        $default_settings = array(
            'delete_data_on_uninstall' => false,
            'enable_logging' => true,
            'log_retention_days' => 30,
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'recaptcha_type' => 'v2',
            'email_from_name' => get_bloginfo('name'),
            'email_from_address' => get_bloginfo('admin_email')
        );

        // Add default settings if they don't exist
        foreach ($default_settings as $key => $value) {
            $option_name = 'connect2form_' . $key;
            if (get_option($option_name) === false) {
                add_option($option_name, $value);
            }
        }

        // Set activation flag
        add_option('connect2form_activated', true);
        add_option('connect2form_version', CONNECT2FORM_VERSION);
    }

    /**
     * Create default form
     */
    public static function create_default_form() {
        // Check if any forms exist using service class
        $existing_forms = 0;
        
        if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $existing_forms = $service_manager->forms()->get_forms_count();
        } else {
            // Fallback to direct database call if service not available
            global $wpdb;
            $forms_table = $wpdb->prefix . 'connect2form_forms';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- forms count check for default form creation; no caching needed for activation; table name is built from trusted prefix; COUNT(*) query doesn't need prepared statements; schema changes are one-time operations during activation
            $existing_forms = $wpdb->get_var("SELECT COUNT(*) FROM {$forms_table}");
        }
        
        if ($existing_forms == 0) {
            // Create a default contact form
            $default_fields = array(
                array(
                    'id' => 'name',
                    'type' => 'text',
                    			'label' => __('Name', 'connect2form-builder'),
			'placeholder' => __('Your Name', 'connect2form-builder'),
                    'required' => true,
                    'width' => 'full'
                ),
                array(
                    'id' => 'email',
                    'type' => 'email',
                    			'label' => __('Email', 'connect2form-builder'),
			'placeholder' => __('your@email.com', 'connect2form-builder'),
                    'required' => true,
                    'width' => 'full'
                ),
                array(
                    'id' => 'subject',
                    'type' => 'text',
                    			'label' => __('Subject', 'connect2form-builder'),
			'placeholder' => __('Subject', 'connect2form-builder'),
                    'required' => false,
                    'width' => 'full'
                ),
                array(
                    'id' => 'message',
                    'type' => 'textarea',
                    			'label' => __('Message', 'connect2form-builder'),
			'placeholder' => __('Your message...', 'connect2form-builder'),
                    'required' => true,
                    'width' => 'full',
                    'rows' => 5
                )
            );

            $default_settings = array(
                'email_notifications' => true,
                'notification_email' => get_bloginfo('admin_email'),
                		'success_message' => __('Thank you for your message! We will get back to you soon.', 'connect2form-builder'),
                'from_name' => get_bloginfo('name'),
                'from_email' => get_bloginfo('admin_email'),
                'auto_responder' => false
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- default form creation during activation; no caching needed for activation
            $wpdb->insert(
                $forms_table,
                array(
                    		'form_title' => __('Contact Form', 'connect2form-builder'),
                    'fields' => wp_json_encode($default_fields),
                    'settings' => wp_json_encode($default_settings),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }
}
