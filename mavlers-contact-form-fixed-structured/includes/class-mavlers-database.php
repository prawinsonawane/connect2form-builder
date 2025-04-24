<?php
/**
 * Database management for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Database {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize if needed
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Forms table
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $forms_sql = "CREATE TABLE IF NOT EXISTS $forms_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_name varchar(255) NOT NULL,
            form_fields longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Form entries table
        $entries_table = $wpdb->prefix . 'mavlers_form_entries';
        $entries_sql = "CREATE TABLE IF NOT EXISTS $entries_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            entry_data longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        // Email logs table
        $logs_table = $wpdb->prefix . 'mavlers_email_logs';
        $logs_sql = "CREATE TABLE IF NOT EXISTS $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($forms_sql);
        dbDelta($entries_sql);
        dbDelta($logs_sql);

        // Clean up old logs
        self::cleanup_old_logs();
    }

    public function save_form_entry($form_id, $entry_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_form_entries';

        return $wpdb->insert(
            $table_name,
            array(
                'form_id' => $form_id,
                'entry_data' => json_encode($entry_data),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s')
        );
    }

    public function get_form_entries($form_id, $limit = 10, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_form_entries';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE form_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $form_id,
            $limit,
            $offset
        ));
    }

    public function get_form_entry_count($form_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_form_entries';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE form_id = %d",
            $form_id
        ));
    }

    public function log_email($recipient, $subject, $content, $status = 'sent') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_email_logs';

        return $wpdb->insert(
            $table_name,
            array(
                'recipient' => $recipient,
                'subject' => $subject,
                'content' => $content,
                'status' => $status,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    public static function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_email_logs';
        $retention_days = get_option('mavlers_log_retention_days', 90);

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));
    }

    public function get_email_logs($limit = 100, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_email_logs';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
}
