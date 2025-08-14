<?php
class Mavlers_CF_Activator {
    public static function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        $submissions_table = $wpdb->prefix . 'mavlers_cf_submissions';
        $form_meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';

        // Drop existing tables to ensure clean recreation
        $wpdb->query("DROP TABLE IF EXISTS $forms_table");
        $wpdb->query("DROP TABLE IF EXISTS $submissions_table");
        $wpdb->query("DROP TABLE IF EXISTS $form_meta_table");

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

        // Create submissions table
        $submissions_sql = "CREATE TABLE $submissions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        // Create form meta table for integration settings
        $form_meta_sql = "CREATE TABLE $form_meta_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        $wpdb->query($forms_sql);
        $wpdb->query($submissions_sql);
        $wpdb->query($form_meta_sql);

        // Verify tables were created
        $forms_exists = $wpdb->get_var("SHOW TABLES LIKE '$forms_table'") === $forms_table;
        $submissions_exists = $wpdb->get_var("SHOW TABLES LIKE '$submissions_table'") === $submissions_table;
        $form_meta_exists = $wpdb->get_var("SHOW TABLES LIKE '$form_meta_table'") === $form_meta_table;

        if (!$forms_exists || !$submissions_exists || !$form_meta_exists) {
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

        // Form meta table for integration settings
        $form_meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        $form_meta_sql = "CREATE TABLE IF NOT EXISTS $form_meta_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($forms_sql);
        dbDelta($submissions_sql);
        dbDelta($form_meta_sql);
    }
} 