<?php
/**
 * Public-facing functionality of the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Public {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add shortcode
        add_shortcode('mavlers_form', array($this, 'render_form'));
        
        // Enqueue public scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Handle form submissions
        add_action('wp_ajax_mavlers_submit_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_mavlers_submit_form', array($this, 'handle_form_submission'));
    }

    public function enqueue_scripts() {
        // Only enqueue if shortcode is present
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mavlers_form')) {
            wp_enqueue_style(
                'mavlers-public',
                MAVLERS_FORM_PLUGIN_URL . 'assets/css/public.css',
                array(),
                MAVLERS_FORM_VERSION
            );

            wp_enqueue_script(
                'mavlers-public',
                MAVLERS_FORM_PLUGIN_URL . 'assets/js/public.js',
                array('jquery'),
                MAVLERS_FORM_VERSION,
                true
            );

            wp_localize_script('mavlers-public', 'mavlersForm', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mavlers_form_submission_nonce')
            ));
        }
    }

    public function render_form($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts, 'mavlers_form');

        $form_id = intval($atts['id']);
        if (!$form_id) {
            return '';
        }

        // Get form data
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$forms_table} WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            return '';
        }

        // Get form fields
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$fields_table} WHERE form_id = %d ORDER BY field_order ASC",
            $form_id
        ));

        // Start output buffering
        ob_start();

        // Include form template
        include MAVLERS_FORM_PLUGIN_DIR . 'public/views/form-template.php';

        // Return the buffered content
        return ob_get_clean();
    }

    public function handle_form_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mavlers_form_submission_nonce')) {
            wp_send_json_error(__('Invalid security token.', 'mavlers-contact-form'));
        }

        // Get form data
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(__('Invalid form ID.', 'mavlers-contact-form'));
        }

        // Get form fields
        global $wpdb;
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$fields_table} WHERE form_id = %d ORDER BY field_order ASC",
            $form_id
        ));

        // Validate form submission
        $errors = array();
        foreach ($fields as $field) {
            $field_meta = maybe_unserialize($field->field_meta);
            if (isset($field_meta['required']) && $field_meta['required'] && empty($_POST[$field->field_name])) {
                $errors[$field->field_name] = sprintf(
                    __('%s is required.', 'mavlers-contact-form'),
                    $field->field_label
                );
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => __('Please fill in all required fields.', 'mavlers-contact-form'),
                'errors' => $errors
            ));
        }

        // Save form submission
        $entries_table = $wpdb->prefix . 'mavlers_form_entries';
        $entry_data = array(
            'form_id' => $form_id,
            'entry_data' => json_encode($_POST),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($entries_table, $entry_data);
        if ($result === false) {
            wp_send_json_error(__('Failed to save form submission.', 'mavlers-contact-form'));
        }

        // Send email notification
        $this->send_email_notification($form_id, $_POST);

        wp_send_json_success(array(
            'message' => __('Form submitted successfully.', 'mavlers-contact-form')
        ));
    }

    private function send_email_notification($form_id, $form_data) {
        // Get form settings
        $settings = Mavlers_Settings::get_instance()->get_form_settings($form_id);
        
        if (!isset($settings['enable_email']) || !$settings['enable_email']) {
            return;
        }

        $to = $settings['email_to'];
        $subject = $settings['email_subject'];
        $message = $settings['email_content'];

        // Replace placeholders in message
        $message = $this->replace_placeholders($message, $form_data);

        $headers = array(
            'From: ' . $settings['email_from_name'] . ' <' . $settings['email_from'] . '>',
            'Content-Type: text/html; charset=UTF-8'
        );

        wp_mail($to, $subject, $message, $headers);
    }

    private function replace_placeholders($content, $form_data) {
        // Replace {all_fields} placeholder
        if (strpos($content, '{all_fields}') !== false) {
            $all_fields = '';
            foreach ($form_data as $key => $value) {
                if ($key !== 'nonce' && $key !== 'action' && $key !== 'form_id') {
                    $all_fields .= sprintf(
                        '<strong>%s:</strong> %s<br>',
                        ucfirst(str_replace('_', ' ', $key)),
                        esc_html($value)
                    );
                }
            }
            $content = str_replace('{all_fields}', $all_fields, $content);
        }

        // Replace individual field placeholders
        foreach ($form_data as $key => $value) {
            if ($key !== 'nonce' && $key !== 'action' && $key !== 'form_id') {
                $content = str_replace(
                    '{' . $key . '}',
                    esc_html($value),
                    $content
                );
            }
        }

        return $content;
    }
} 