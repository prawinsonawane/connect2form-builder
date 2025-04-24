<?php
/**
 * Form analytics handler for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Form_Analytics {
    private static $instance = null;
    private $db;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db = Mavlers_Form_Database::get_instance();
        
        // Register analytics tracking
        add_action('wp_ajax_mavlers_track_form_view', array($this, 'handle_form_view'));
        add_action('wp_ajax_nopriv_mavlers_track_form_view', array($this, 'handle_form_view'));
        
        // Register analytics data endpoints
        add_action('wp_ajax_mavlers_get_analytics_data', array($this, 'get_analytics_data'));
    }

    public function handle_form_view() {
        check_ajax_referer('mavlers_form_analytics', 'nonce');

        $form_id = intval($_POST['form_id']);
        if (!$form_id) {
            wp_send_json_error(__('Invalid form ID', 'mavlers-contact-form'));
        }

        // Validate form exists
        $form = $this->db->get_form($form_id);
        if (!$form) {
            wp_send_json_error(__('Form not found', 'mavlers-contact-form'));
        }

        $this->db->update_analytics($form_id, 'view');
        wp_send_json_success();
    }

    public function get_analytics_data() {
        check_ajax_referer('mavlers_form_analytics', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'mavlers-contact-form'));
        }

        $form_id = intval($_POST['form_id']);
        $period = sanitize_text_field($_POST['period']);
        $type = sanitize_text_field($_POST['type']);

        if (!$form_id) {
            wp_send_json_error(__('Invalid form ID', 'mavlers-contact-form'));
        }

        // Validate form exists
        $form = $this->db->get_form($form_id);
        if (!$form) {
            wp_send_json_error(__('Form not found', 'mavlers-contact-form'));
        }

        // Validate period
        $valid_periods = array('today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month');
        if (!in_array($period, $valid_periods)) {
            wp_send_json_error(__('Invalid period', 'mavlers-contact-form'));
        }

        // Validate type
        $valid_types = array('overview', 'submissions', 'conversion', 'field_analytics');
        if (!in_array($type, $valid_types)) {
            wp_send_json_error(__('Invalid analytics type', 'mavlers-contact-form'));
        }

        $data = array();
        switch ($type) {
            case 'overview':
                $data = $this->get_overview_data($form_id, $period);
                break;
            case 'submissions':
                $data = $this->get_submissions_data($form_id, $period);
                break;
            case 'conversion':
                $data = $this->get_conversion_data($form_id, $period);
                break;
            case 'field_analytics':
                $data = $this->get_field_analytics($form_id, $period);
                break;
        }

        wp_send_json_success($data);
    }

    private function get_overview_data($form_id, $period) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_analytics';
        
        // Try to get cached data
        $cache_key = 'mavlers_analytics_overview_' . $form_id . '_' . $period;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $date_range = $this->get_date_range($period);
        $analytics = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE form_id = %d",
            $form_id
        ));

        if (!$analytics) {
            $data = array(
                'views' => 0,
                'submissions' => 0,
                'conversion_rate' => 0,
                'trend' => 'stable'
            );
            set_transient($cache_key, $data, HOUR_IN_SECONDS);
            return $data;
        }

        // Calculate trend
        $previous_period = $this->get_previous_period_data($form_id, $period);
        $trend = $this->calculate_trend($analytics, $previous_period);

        $data = array(
            'views' => intval($analytics->view_count),
            'submissions' => intval($analytics->submission_count),
            'conversion_rate' => floatval($analytics->conversion_rate),
            'trend' => $trend
        );
        
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return $data;
    }

    private function get_submissions_data($form_id, $period) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_submissions';
        
        // Try to get cached data
        $cache_key = 'mavlers_analytics_submissions_' . $form_id . '_' . $period;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $date_range = $this->get_date_range($period);
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM $table_name 
            WHERE form_id = %d AND created_at BETWEEN %s AND %s 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC",
            $form_id,
            $date_range['start'],
            $date_range['end']
        ));

        if (!$submissions) {
            $data = array(
                'labels' => array(),
                'data' => array()
            );
            set_transient($cache_key, $data, HOUR_IN_SECONDS);
            return $data;
        }

        $data = array(
            'labels' => array_map(function($item) { return $item->date; }, $submissions),
            'data' => array_map(function($item) { return intval($item->count); }, $submissions)
        );
        
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return $data;
    }

    private function get_conversion_data($form_id, $period) {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'mavlers_analytics';
        $submissions_table = $wpdb->prefix . 'mavlers_submissions';
        
        // Try to get cached data
        $cache_key = 'mavlers_analytics_conversion_' . $form_id . '_' . $period;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $date_range = $this->get_date_range($period);
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(s.created_at) as date,
                COUNT(s.id) as submissions,
                (SELECT COUNT(*) FROM $analytics_table WHERE form_id = %d AND DATE(created_at) = DATE(s.created_at)) as views
            FROM $submissions_table s
            WHERE s.form_id = %d AND s.created_at BETWEEN %s AND %s
            GROUP BY DATE(s.created_at)
            ORDER BY date ASC",
            $form_id,
            $form_id,
            $date_range['start'],
            $date_range['end']
        ));

        if (!$data) {
            $result = array(
                'labels' => array(),
                'submissions' => array(),
                'views' => array(),
                'conversion_rates' => array()
            );
            set_transient($cache_key, $result, HOUR_IN_SECONDS);
            return $result;
        }

        $result = array(
            'labels' => array_map(function($item) { return $item->date; }, $data),
            'submissions' => array_map(function($item) { return intval($item->submissions); }, $data),
            'views' => array_map(function($item) { return intval($item->views); }, $data),
            'conversion_rates' => array_map(function($item) { 
                return $item->views > 0 ? round(($item->submissions / $item->views * 100), 2) : 0; 
            }, $data)
        );
        
        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        return $result;
    }

    private function get_field_analytics($form_id, $period) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_submissions';
        
        $date_range = $this->get_date_range($period);
        $form = $this->db->get_form($form_id);
        $fields = json_decode($form->form_fields, true);

        if (!is_array($fields)) {
            return array();
        }

        $analytics = array();
        foreach ($fields as $field) {
            if (!isset($field['name']) || !isset($field['type']) || !isset($field['label'])) {
                continue;
            }

            if (in_array($field['type'], array('text', 'textarea', 'email', 'select', 'radio', 'checkbox'))) {
                $field_name = sanitize_key($field['name']);
                $field_data = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        JSON_EXTRACT(form_data, '$.{$field_name}') as value,
                        COUNT(*) as count
                    FROM $table_name
                    WHERE form_id = %d AND created_at BETWEEN %s AND %s
                    GROUP BY JSON_EXTRACT(form_data, '$.{$field_name}')
                    ORDER BY count DESC
                    LIMIT 10",
                    $form_id,
                    $date_range['start'],
                    $date_range['end']
                ));

                if ($field_data) {
                    $analytics[$field_name] = array(
                        'label' => sanitize_text_field($field['label']),
                        'type' => sanitize_text_field($field['type']),
                        'data' => array_map(function($item) {
                            return array(
                                'value' => sanitize_text_field($item->value),
                                'count' => intval($item->count)
                            );
                        }, $field_data)
                    );
                }
            }
        }

        return $analytics;
    }

    private function get_date_range($period) {
        $end = current_time('mysql');
        switch ($period) {
            case 'today':
                $start = date('Y-m-d 00:00:00');
                break;
            case 'yesterday':
                $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
                $end = date('Y-m-d 23:59:59', strtotime('-1 day'));
                break;
            case 'last_7_days':
                $start = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'last_30_days':
                $start = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            case 'this_month':
                $start = date('Y-m-01 00:00:00');
                break;
            case 'last_month':
                $start = date('Y-m-01 00:00:00', strtotime('first day of last month'));
                $end = date('Y-m-t 23:59:59', strtotime('last day of last month'));
                break;
            default:
                $start = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }

        return array(
            'start' => $start,
            'end' => $end
        );
    }

    private function get_previous_period_data($form_id, $period) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_analytics';
        
        $date_range = $this->get_previous_date_range($period);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE form_id = %d AND last_updated BETWEEN %s AND %s",
            $form_id,
            $date_range['start'],
            $date_range['end']
        ));
    }

    private function get_previous_date_range($period) {
        $end = date('Y-m-d H:i:s', strtotime('-1 day'));
        switch ($period) {
            case 'today':
                $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
                break;
            case 'yesterday':
                $start = date('Y-m-d 00:00:00', strtotime('-2 days'));
                $end = date('Y-m-d 23:59:59', strtotime('-2 days'));
                break;
            case 'last_7_days':
                $start = date('Y-m-d 00:00:00', strtotime('-14 days'));
                $end = date('Y-m-d 23:59:59', strtotime('-8 days'));
                break;
            case 'last_30_days':
                $start = date('Y-m-d 00:00:00', strtotime('-60 days'));
                $end = date('Y-m-d 23:59:59', strtotime('-31 days'));
                break;
            case 'this_month':
                $start = date('Y-m-01 00:00:00', strtotime('first day of last month'));
                $end = date('Y-m-t 23:59:59', strtotime('last day of last month'));
                break;
            case 'last_month':
                $start = date('Y-m-01 00:00:00', strtotime('first day of -2 month'));
                $end = date('Y-m-t 23:59:59', strtotime('last day of -2 month'));
                break;
            default:
                $start = date('Y-m-d 00:00:00', strtotime('-60 days'));
                $end = date('Y-m-d 23:59:59', strtotime('-31 days'));
        }

        return array(
            'start' => $start,
            'end' => $end
        );
    }

    private function calculate_trend($current, $previous) {
        if (!$previous) {
            return 'stable';
        }

        $current_rate = floatval($current->conversion_rate);
        $previous_rate = floatval($previous->conversion_rate);
        $threshold = 5; // 5% change threshold

        if ($current_rate > $previous_rate + $threshold) {
            return 'up';
        } elseif ($current_rate < $previous_rate - $threshold) {
            return 'down';
        } else {
            return 'stable';
        }
    }
} 