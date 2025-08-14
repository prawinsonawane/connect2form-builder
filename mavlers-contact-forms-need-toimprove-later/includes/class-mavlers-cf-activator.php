<?php
class Mavlers_CF_Activator {
    public static function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        $submissions_table = $wpdb->prefix . 'mavlers_cf_submissions';

        // Check if tables exist first
        $forms_exists = $wpdb->get_var("SHOW TABLES LIKE '$forms_table'") === $forms_table;
        $submissions_exists = $wpdb->get_var("SHOW TABLES LIKE '$submissions_table'") === $submissions_table;

        // Only create tables if they don't exist
        if (!$forms_exists) {
            // Create forms table
            $forms_sql = "CREATE TABLE $forms_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                form_title varchar(255) NOT NULL,
                fields longtext NOT NULL,
                settings longtext DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($forms_sql);
        }

        if (!$submissions_exists) {
            // Create submissions table
            $submissions_sql = "CREATE TABLE $submissions_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                form_id bigint(20) NOT NULL,
                data longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY form_id (form_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($submissions_sql);
        }

        // Verify tables exist after creation
        $forms_exists = $wpdb->get_var("SHOW TABLES LIKE '$forms_table'") === $forms_table;
        $submissions_exists = $wpdb->get_var("SHOW TABLES LIKE '$submissions_table'") === $submissions_table;

        if (!$forms_exists || !$submissions_exists) {
            wp_die('Failed to create required database tables. Please deactivate and reactivate the plugin.');
        }

        // Verify columns exist
        $forms_columns = $wpdb->get_col("SHOW COLUMNS FROM $forms_table");
        $required_columns = array('id', 'form_title', 'fields', 'settings', 'status', 'created_at', 'updated_at');
        
        foreach ($required_columns as $column) {
            if (!in_array($column, $forms_columns)) {
                wp_die("Required column '$column' is missing from the forms table. Please deactivate and reactivate the plugin.");
            }
        }
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Forms table
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        $forms_sql = "CREATE TABLE IF NOT EXISTS $forms_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_title varchar(255) NOT NULL,
            fields longtext NOT NULL,
            settings longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Submissions table
        $submissions_table = $wpdb->prefix . 'mavlers_cf_submissions';
        $submissions_sql = "CREATE TABLE IF NOT EXISTS $submissions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'unread',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($forms_sql);
        dbDelta($submissions_sql);
    }
} 