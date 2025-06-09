<?php
/**
 * Database handler for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Form_Database {
    private static $instance = null;
    private $table_prefix;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'mavlers_';
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Forms table
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $sql = "CREATE TABLE IF NOT EXISTS $forms_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_name varchar(255) NOT NULL,
            form_fields longtext NOT NULL,
            form_settings longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Form fields table
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
        $sql = "CREATE TABLE IF NOT EXISTS $fields_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            field_type varchar(50) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_name varchar(255),
            field_meta longtext,
            field_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        dbDelta($sql);

        // Form entries table
        $entries_table = $wpdb->prefix . 'mavlers_form_entries';
        $sql = "CREATE TABLE IF NOT EXISTS $entries_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            entry_data longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /*public function save_form($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_forms';

        $form_data = array(
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description']),
            'form_fields' => wp_json_encode($data['fields']),
            'email_settings' => wp_json_encode($data['email_settings']),
            'updated_at' => current_time('mysql')
        );
        error_log('Mavlers Form Data: ' . print_r($form_data, true));
        if (isset($data['id']) && $data['id']) {
            $wpdb->update($table_name, $form_data, array('id' => $data['id']));
            return $data['id'];
        } else {
            $form_data['created_at'] = current_time('mysql');
            $wpdb->insert($table_name, $form_data);
            return $wpdb->insert_id;
        }
    }*/

    public function get_form($form_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_forms';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $form_id
        ));
    }

    public function get_forms($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_forms';

        $defaults = array(
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $where = "WHERE status = '" . esc_sql($args['status']) . "'";
        $order = "ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        $limit = "LIMIT " . intval($args['offset']) . ", " . intval($args['limit']);

        return $wpdb->get_results("SELECT * FROM $table_name $where $order $limit");
    }

    public function save_submission($form_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_submissions';

        $submission_data = array(
            'form_id' => $form_id,
            'form_data' => wp_json_encode($data),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($table_name, $submission_data);
        return $wpdb->insert_id;
    }

    public function get_submissions($form_id, $args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_submissions';

        $defaults = array(
            'status' => 'new',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $where = "WHERE form_id = " . intval($form_id);
        if ($args['status']) {
            $where .= " AND status = '" . esc_sql($args['status']) . "'";
        }
        $order = "ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        $limit = "LIMIT " . intval($args['offset']) . ", " . intval($args['limit']);

        return $wpdb->get_results("SELECT * FROM $table_name $where $order $limit");
    }

    public function update_analytics($form_id, $type = 'view') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_analytics';

        $analytics = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE form_id = %d",
            $form_id
        ));

        if (!$analytics) {
            $wpdb->insert($table_name, array(
                'form_id' => $form_id,
                'view_count' => 1,
                'submission_count' => 0,
                'last_updated' => current_time('mysql')
            ));
        } else {
            $data = array(
                'last_updated' => current_time('mysql')
            );

            if ($type === 'view') {
                $data['view_count'] = $analytics->view_count + 1;
            } else {
                $data['submission_count'] = $analytics->submission_count + 1;
            }

            $data['conversion_rate'] = $data['submission_count'] / $data['view_count'] * 100;

            $wpdb->update($table_name, $data, array('id' => $analytics->id));
        }
    }

    private function get_client_ip() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
} 