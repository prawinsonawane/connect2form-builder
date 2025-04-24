<?php
/**
 * Admin menu and interface management for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register AJAX handlers
        add_action('wp_ajax_mavlers_delete_form', array($this, 'handle_form_delete'));
        add_action('wp_ajax_mavlers_save_form', array($this, '"mavlers_save_form"'));
        add_action('wp_ajax_nopriv_mavlers_save_form', [$this, 'mavlers_save_form']);
        add_action('wp_ajax_mavlers_save_field', array($this, 'handle_field_save'));
        add_action('wp_ajax_mavlers_delete_field', array($this, 'handle_field_delete'));
        add_action('wp_ajax_mavlers_get_form_fields', array($this, 'handle_get_form_fields'));
        
        // Register admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Mavlers Forms', 'mavlers-contact-form'),
            __('Mavlers Forms', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms',
            array($this, 'render_forms_page'),
            'dashicons-feedback',
            30
        );

        // Submenu items
        add_submenu_page(
            'mavlers-forms',
            __('All Forms', 'mavlers-contact-form'),
            __('All Forms', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms',
            array($this, 'render_forms_page')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Add New', 'mavlers-contact-form'),
            __('Add New', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms-new',
            array($this, 'render_new_form_page')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Submissions', 'mavlers-contact-form'),
            __('Submissions', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms-submissions',
            array($this, 'render_submissions_page')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Analytics', 'mavlers-contact-form'),
            __('Analytics', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms-analytics',
            array($this, 'render_analytics_page')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Settings', 'mavlers-contact-form'),
            __('Settings', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-forms-settings',
            array($this, 'render_settings_page')
        );
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

        // Enqueue styles
        wp_enqueue_style(
            'mavlers-admin',
            MAVLERS_FORM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MAVLERS_FORM_VERSION
        );

        wp_enqueue_style(
            'mavlers-form-builder',
            MAVLERS_FORM_PLUGIN_URL . 'assets/css/form-builder.css',
            array(),
            MAVLERS_FORM_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'mavlers-form-builder',
            MAVLERS_FORM_PLUGIN_URL . 'assets/js/form-builder.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            MAVLERS_FORM_VERSION,
            true
        );

        wp_enqueue_script(
            'mavlers-admin',
            MAVLERS_FORM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MAVLERS_FORM_VERSION,
            true
        );

        if ($hook === 'mavlers-forms_page_mavlers-forms-analytics') {
            wp_enqueue_script(
                'mavlers-form-analytics',
                MAVLERS_FORM_PLUGIN_URL . 'assets/js/form-analytics.js',
                array('jquery'),
                MAVLERS_FORM_VERSION,
                true
            );
        }

        // Create nonce for form builder
        $form_builder_nonce = wp_create_nonce('mavlers_form_builder_nonce');

        // Localize scripts
        wp_localize_script('mavlers-form-builder', 'mavlersFormBuilder', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => $form_builder_nonce,
            'adminUrl' => admin_url('admin.php'),
            'previewUrl' => home_url('?mavlers_form_preview=1'),
            'strings' => array(
                'deleteConfirm' => __('Are you sure you want to delete this field?', 'mavlers-contact-form'),
                'error' => __('An error occurred. Please try again.', 'mavlers-contact-form'),
                'success' => __('Operation completed successfully.', 'mavlers-contact-form'),
                'cancel' => __('Cancel', 'mavlers-contact-form'),
                'save' => __('Save Changes', 'mavlers-contact-form'),
                'saveField' => __('Save Field', 'mavlers-contact-form'),
                'addField' => __('Add Field', 'mavlers-contact-form'),
                'editField' => __('Edit Field', 'mavlers-contact-form'),
                'deleteField' => __('Delete Field', 'mavlers-contact-form'),
                'required' => __('Required', 'mavlers-contact-form'),
                'optional' => __('Optional', 'mavlers-contact-form'),
                'saveForm' => __('Save Form', 'mavlers-contact-form'),
                'preview' => __('Preview', 'mavlers-contact-form'),
                'saveFormFirst' => __('Please save the form first', 'mavlers-contact-form'),
                'formSaved' => __('Form saved successfully', 'mavlers-contact-form'),
                'fieldSaved' => __('Field saved successfully', 'mavlers-contact-form'),
                'fieldDeleted' => __('Field deleted successfully', 'mavlers-contact-form'),
                'fieldOrderUpdated' => __('Field order updated successfully', 'mavlers-contact-form')
            ),
            'fieldTypes' => Mavlers_Form_Builder::get_instance()->get_field_types()
        ));

        wp_localize_script('mavlers-admin', 'mavlersForms', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => $form_builder_nonce,
            'strings' => array(
                'deleteConfirm' => __('Are you sure you want to delete this form? This action cannot be undone.', 'mavlers-contact-form'),
                'error' => __('An error occurred. Please try again.', 'mavlers-contact-form')
            )
        ));

        if ($hook === 'mavlers-forms_page_mavlers-forms-analytics') {
            wp_localize_script('mavlers-form-analytics', 'mavlersAnalytics', array(
                'nonce' => wp_create_nonce('mavlers_form_analytics_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php')
            ));
        }
    }

    public function render_forms_page() {
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['form_id'])) {
            require_once MAVLERS_FORM_PLUGIN_DIR . 'admin/views/form-builder.php';
        } else {
            require_once MAVLERS_FORM_PLUGIN_DIR . 'admin/views/forms-list.php';
        }
    }

    public function render_new_form_page() {
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

    /*public function handle_get_form_fields() {
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
            $options = maybe_unserialize($field->field_options);
            $formatted_fields[] = array(
                'id' => $field->id,
                'type' => $field->field_type,
                'label' => $field->field_label,
                'required' => (bool)$field->field_required,
                'placeholder' => isset($options['placeholder']) ? $options['placeholder'] : '',
                'options' => $options
            );
        }

        wp_send_json_success($formatted_fields);
    }*/
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
} 