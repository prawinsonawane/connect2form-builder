<?php
/**
 * Admin menu and interface management for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Admin {
    private static $instance = null;
    private $settings;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize settings
        $this->settings = Mavlers_Settings::get_instance();
        
        // Register AJAX handlers
        add_action('wp_ajax_mavlers_delete_form', array($this, 'handle_form_delete'));
        add_action('wp_ajax_mavlers_save_form', array($this, 'save_form'));
        add_action('wp_ajax_nopriv_mavlers_save_form', [$this, 'mavlers_save_form']);
        add_action('wp_ajax_mavlers_save_field', array($this, 'handle_field_save'));
        add_action('wp_ajax_mavlers_delete_field', array($this, 'handle_field_delete'));
        add_action('wp_ajax_mavlers_get_form_fields', array($this, 'handle_get_form_fields'));
        add_action('wp_ajax_mavlers_duplicate_form', array($this, 'duplicate_form'));
        
        // Register admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'mavlers-forms') === false) {
            return;
        }

        // Enqueue WordPress core scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');

        // Enqueue admin styles
        wp_enqueue_style(
            'mavlers-admin',
            MAVLERS_CONTACT_FORM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MAVLERS_CONTACT_FORM_VERSION
        );

        // Enqueue form builder styles
        wp_enqueue_style(
            'mavlers-form-builder',
            MAVLERS_CONTACT_FORM_PLUGIN_URL . 'assets/css/form-builder.css',
            array(),
            MAVLERS_CONTACT_FORM_VERSION
        );

        // Enqueue form builder scripts
        wp_enqueue_script(
            'mavlers-form-builder',
            MAVLERS_CONTACT_FORM_PLUGIN_URL . 'assets/js/form-builder.js',
            array('jquery', 'jquery-ui-sortable'),
            MAVLERS_CONTACT_FORM_VERSION,
            true
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'mavlers-admin',
            MAVLERS_CONTACT_FORM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MAVLERS_CONTACT_FORM_VERSION,
            true
        );

        // Enqueue form analytics scripts
        wp_enqueue_script(
            'mavlers-form-analytics',
            MAVLERS_CONTACT_FORM_PLUGIN_URL . 'assets/js/form-analytics.js',
            array('jquery'),
            MAVLERS_CONTACT_FORM_VERSION,
            true
        );

        // Localize scripts
        $form_builder_nonce = wp_create_nonce('mavlers_form_builder_nonce');
        $form_analytics_nonce = wp_create_nonce('mavlers_form_analytics_nonce');

        wp_localize_script('mavlers-form-builder', 'mavlersFormBuilder', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => $form_builder_nonce,
            'adminUrl' => admin_url('admin.php'),
            'previewUrl' => home_url('?mavlers_form_preview=1'),
            'strings' => array(
                'deleteConfirm' => __('Are you sure you want to delete this field?', 'mavlers-contact-form'),
                'saveFormFirst' => __('Please save the form first', 'mavlers-contact-form'),
                'errorLoadingFields' => __('Error loading form fields', 'mavlers-contact-form'),
                'errorSavingForm' => __('Error saving form', 'mavlers-contact-form'),
                'errorSavingField' => __('Error saving field', 'mavlers-contact-form'),
                'errorDeletingField' => __('Error deleting field', 'mavlers-contact-form')
            ),
            'fieldTypes' => Mavlers_Form_Builder::get_instance()->get_field_types()
        ));

        wp_localize_script('mavlers-form-analytics', 'mavlersFormAnalytics', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => $form_analytics_nonce,
            'strings' => array(
                'errorLoadingData' => __('Error loading analytics data', 'mavlers-contact-form')
            )
        ));
    }

    public function render_forms_page() {
        // Check if we're on the settings action
        if (isset($_GET['action']) && $_GET['action'] === 'settings') {
            $this->render_settings_page();
            return;
        }

        // Otherwise render the forms list page
        require_once MAVLERS_FORM_PLUGIN_DIR . 'admin/views/forms-list.php';
    }

    public function render_form_builder() {
        require_once MAVLERS_FORM_PLUGIN_DIR . 'admin/views/form-builder.php';
    }

    public function render_submissions_page() {
        require_once MAVLERS_FORM_PLUGIN_DIR . 'admin/views/submissions.php';
    }

    public function render_analytics_page() {
        require_once MAVLERS_FORM_PLUGIN_DIR . 'admin/views/analytics.php';
    }

    public function render_settings_page() {
        require_once MAVLERS_FORM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Handle form save
     */

    public function handle_form_delete() {
        check_ajax_referer('mavlers_forms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'mavlers-contact-form'));
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(__('Invalid form ID.', 'mavlers-contact-form'));
        }

        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
        $entries_table = $wpdb->prefix . 'mavlers_form_entries';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete form entries
            $wpdb->delete($entries_table, array('form_id' => $form_id), array('%d'));

            // Delete form fields
            $wpdb->delete($fields_table, array('form_id' => $form_id), array('%d'));

            // Delete the form
            $result = $wpdb->delete($forms_table, array('id' => $form_id), array('%d'));
            if ($result === false) {
                throw new Exception('Failed to delete form');
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            wp_send_json_success(array(
                'message' => __('Form deleted successfully.', 'mavlers-contact-form')
            ));

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_field_save() {
        check_ajax_referer('mavlers_forms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'mavlers-contact-form'));
        }

        $field = [
            'type'     => $_POST['field_type'] ?? '',
            'label'    => $_POST['field_label'] ?? '',
            'required' => isset($_POST['field_required']) ? 1 : 0,
            'options'  => $_POST['field_options'] ?? []
        ];

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (empty($field['type']) || empty($field['label'])) {
            wp_send_json_error(__('Field type and label are required.', 'mavlers-contact-form'));
        }

        $result = $this->save_single_field($field, $form_id);

        if ($result === false) {
            wp_send_json_error(__('Failed to save field.', 'mavlers-contact-form'));
        }

        global $wpdb;
        wp_send_json_success(array(
            'field_id' => $wpdb->insert_id,
            'message'  => __('Field saved successfully.', 'mavlers-contact-form')
        ));
    }

    public function handle_field_delete() {
        check_ajax_referer('mavlers_forms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'mavlers-contact-form'));
        }

        $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$field_id || !$form_id) {
            wp_send_json_error(__('Invalid field or form ID.', 'mavlers-contact-form'));
        }

        global $wpdb;
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';

        $result = $wpdb->delete(
            $fields_table,
            array(
                'id' => $field_id,
                'form_id' => $form_id
            ),
            array('%d', '%d')
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to delete field.', 'mavlers-contact-form'));
        }

        wp_send_json_success(array(
            'message' => __('Field deleted successfully.', 'mavlers-contact-form')
        ));
    }

    public function handle_get_form_fields() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mavlers_form_builder_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
    
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
      
        // Get form ID
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error('Invalid form ID');
            return;
        }
    
        global $wpdb;
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
    
        // Get fields for the form
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $fields_table WHERE form_id = %d ORDER BY field_order ASC",
            $form_id
        ));
    
        if ($fields === null) {
            wp_send_json_error('Error retrieving fields');
            return;
        }
    
        // Format fields for response
        $formatted_fields = array();
        foreach ($fields as $field) {
            // Deserialize meta if possible
            $meta = maybe_unserialize($field->field_meta);
            if (!is_array($meta)) {
                $meta = [];
            }
    
            $formatted_fields[] = array(
                'id'          => $field->id,
                'type'        => $field->field_type,
                'label'       => $field->field_label,
                'name'        => isset($field->field_name) ? $field->field_name : '', // optional
                'required'    => isset($meta['required']) ? (bool)$meta['required'] : false,
                'placeholder' => isset($meta['placeholder']) ? $meta['placeholder'] : '',
                'options'     => isset($meta['options']) ? $meta['options'] : [],
                'meta'        => $meta,
            );
        }
       // error_log('handle_get_form_fields Error: ' . $formatted_fields);
        wp_send_json_success($formatted_fields);
    }

    public function handle_update_field_order() {
        check_ajax_referer('mavlers_forms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'mavlers-contact-form'));
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $field_order = isset($_POST['field_order']) ? array_map('intval', $_POST['field_order']) : array();

        if (!$form_id || empty($field_order)) {
            wp_send_json_error(__('Invalid form ID or field order.', 'mavlers-contact-form'));
        }

        global $wpdb;
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            foreach ($field_order as $index => $field_id) {
                $result = $wpdb->update(
                    $fields_table,
                    array('field_order' => $index),
                    array(
                        'id' => $field_id,
                        'form_id' => $form_id
                    ),
                    array('%d'),
                    array('%d', '%d')
                );

                if ($result === false) {
                    throw new Exception('Failed to update field order');
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            wp_send_json_success(array(
                'message' => __('Field order updated successfully.', 'mavlers-contact-form')
            ));

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            wp_send_json_error($e->getMessage());
        }
    }

    public function save_form() {
        check_ajax_referer('mavlers_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $form_name = sanitize_text_field($_POST['form_name']);
        $form_fields = json_decode(stripslashes($_POST['form_fields']), true);

        if (empty($form_name) || empty($form_fields)) {
            wp_send_json_error('Invalid form data');
        }

        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        
        $form_data = array(
            'form_name' => $form_name,
            'form_fields' => json_encode($form_fields),
            'updated_at' => current_time('mysql')
        );

        if ($form_id) {
            // Update existing form
            $result = $wpdb->update(
                $forms_table,
                $form_data,
                array('id' => $form_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new form
            $form_data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $forms_table,
                $form_data,
                array('%s', '%s', '%s', '%s')
            );
            $form_id = $wpdb->insert_id;
        }

        if ($result === false) {
            wp_send_json_error('Failed to save form');
        }

        wp_send_json_success(array(
            'form_id' => $form_id,
            'message' => $form_id ? 'Form updated successfully' : 'Form created successfully'
        ));
    }

    public function duplicate_form() {
        check_ajax_referer('mavlers_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $form_id = intval($_POST['form_id']);
        
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        
        // Get the original form
        $original_form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $forms_table WHERE id = %d",
            $form_id
        ));
        
        if (!$original_form) {
            wp_send_json_error('Form not found');
        }
        
        // Create a copy of the form
        $new_form_data = array(
            'form_name' => $original_form->form_name . ' (Copy)',
            'form_fields' => $original_form->form_fields,
            'form_settings' => $original_form->form_settings,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($forms_table, $new_form_data);
        
        if ($result === false) {
            wp_send_json_error('Failed to duplicate form');
        }

        wp_send_json_success(array(
            'form_id' => $wpdb->insert_id,
            'message' => 'Form duplicated successfully'
        ));
    }
} 