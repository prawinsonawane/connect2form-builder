<?php
/**
 * Enhanced form builder for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Form_Builder {
    private static $instance = null;
    private $email_templates = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_builder_scripts'));
        add_action('wp_ajax_mavlers_save_field', array($this, 'handle_field_save'));
        add_action('wp_ajax_mavlers_delete_field', array($this, 'handle_field_delete'));
        add_action('wp_ajax_mavlers_reorder_fields', array($this, 'handle_field_reorder'));
        add_action('wp_ajax_mavlers_save_email_settings', array($this, 'handle_email_settings_save'));
        add_action('wp_ajax_mavlers_save_email_template', array($this, 'handle_email_template_save'));
        add_action('wp_ajax_mavlers_save_form', array($this, 'handle_form_save'));
        add_action('wp_ajax_mavlers_get_form_fields', array($this, 'handle_get_form_fields'));
        add_action('mavlers_form_submitted', array($this, 'handle_form_submission'), 10, 2);
        
        $this->init_email_templates();
        $this->check_database_tables();
    }

    private function init_email_templates() {
        $this->email_templates = array(
            'default' => array(
                'name' => __('Default Template', 'mavlers-contact-form'),
                'subject' => __('New Form Submission - {form_title}', 'mavlers-contact-form'),
                'body' => $this->get_default_email_template()
            ),
            'minimal' => array(
                'name' => __('Minimal Template', 'mavlers-contact-form'),
                'subject' => __('New Submission: {form_title}', 'mavlers-contact-form'),
                'body' => $this->get_minimal_email_template()
            ),
            'detailed' => array(
                'name' => __('Detailed Template', 'mavlers-contact-form'),
                'subject' => __('Detailed Form Submission - {form_title}', 'mavlers-contact-form'),
                'body' => $this->get_detailed_email_template()
            )
        );
    }

    private function get_default_email_template() {
        return '<h2>{form_title}</h2>
<p>A new form submission has been received:</p>
{form_data}
<p>Submitted on: {submission_date}</p>
<p>IP Address: {ip_address}</p>';
    }

    private function get_minimal_email_template() {
        return '<p>New submission received:</p>
{form_data}';
    }

    private function get_detailed_email_template() {
        return '<h2>{form_title}</h2>
<p>Detailed submission information:</p>
{form_data}
<p><strong>Submission Details:</strong></p>
<ul>
    <li>Date: {submission_date}</li>
    <li>Time: {submission_time}</li>
    <li>IP Address: {ip_address}</li>
    <li>User Agent: {user_agent}</li>
</ul>';
    }

    public function enqueue_builder_scripts($hook) {
        if (strpos($hook, 'mavlers-forms-new') === false && strpos($hook, 'mavlers-forms') === false) {
            return;
        }

        // Enqueue required scripts and styles
        wp_enqueue_style('mavlers-form-builder', MAVLERS_FORM_PLUGIN_URL . 'assets/css/form-builder.css', array(), MAVLERS_FORM_VERSION);
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('mavlers-form-builder', MAVLERS_FORM_PLUGIN_URL . 'assets/js/form-builder.js', array('jquery', 'jquery-ui-sortable'), MAVLERS_FORM_VERSION, true);
    }

    /**
     * Get available field types with their settings
     *
     * @return array Field types configuration
     */
    public function get_field_types() {
        return array(
            'text' => array(
                'label' => __('Text Field', 'mavlers-contact-form'),
                'icon' => 'dashicons-editor-textcolor',
                'settings' => array(
                    'label' => array(
                        'type' => 'text',
                        'label' => __('Field Label', 'mavlers-contact-form'),
                        'required' => true
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __('Description', 'mavlers-contact-form')
                    ),
                    'placeholder' => array(
                        'type' => 'text',
                        'label' => __('Placeholder', 'mavlers-contact-form')
                    ),
                    'max_chars' => array(
                        'type' => 'number',
                        'label' => __('Maximum Characters', 'mavlers-contact-form')
                    ),
                    'required' => array(
                        'type' => 'checkbox',
                        'label' => __('Required Field', 'mavlers-contact-form')
                    ),
                    'css_class' => array(
                        'type' => 'text',
                        'label' => __('Custom CSS Class', 'mavlers-contact-form')
                    ),
                    'validation_message' => array(
                        'type' => 'text',
                        'label' => __('Custom Validation Message', 'mavlers-contact-form')
                    ),
                    'field_size' => array(
                        'type' => 'select',
                        'label' => __('Field Size', 'mavlers-contact-form'),
                        'options' => array(
                            'small' => __('Small', 'mavlers-contact-form'),
                            'medium' => __('Medium', 'mavlers-contact-form'),
                            'large' => __('Large', 'mavlers-contact-form')
                        )
                    ),
                    'column_layout' => array(
                        'type' => 'select',
                        'label' => __('Column Layout', 'mavlers-contact-form'),
                        'options' => array(
                            'full' => __('Full Width', 'mavlers-contact-form'),
                            'half' => __('Half Width', 'mavlers-contact-form')
                        ),
                        'default' => 'full'
                    )
                )
            ),
            'textarea' => array(
                'label' => __('Text Area', 'mavlers-contact-form'),
                'icon' => 'dashicons-editor-paragraph',
                'settings' => array(
                    'label' => array(
                        'type' => 'text',
                        'label' => __('Field Label', 'mavlers-contact-form'),
                        'required' => true
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __('Description', 'mavlers-contact-form')
                    ),
                    'placeholder' => array(
                        'type' => 'text',
                        'label' => __('Placeholder', 'mavlers-contact-form')
                    ),
                    'rows' => array(
                        'type' => 'number',
                        'label' => __('Number of Rows', 'mavlers-contact-form'),
                        'default' => 4
                    ),
                    'max_chars' => array(
                        'type' => 'number',
                        'label' => __('Maximum Characters', 'mavlers-contact-form')
                    ),
                    'required' => array(
                        'type' => 'checkbox',
                        'label' => __('Required Field', 'mavlers-contact-form')
                    ),
                    'css_class' => array(
                        'type' => 'text',
                        'label' => __('Custom CSS Class', 'mavlers-contact-form')
                    ),
                    'validation_message' => array(
                        'type' => 'text',
                        'label' => __('Custom Validation Message', 'mavlers-contact-form')
                    ),
                    'column_layout' => array(
                        'type' => 'select',
                        'label' => __('Column Layout', 'mavlers-contact-form'),
                        'options' => array(
                            'full' => __('Full Width', 'mavlers-contact-form'),
                            'half' => __('Half Width', 'mavlers-contact-form')
                        ),
                        'default' => 'full'
                    )
                )
            ),
            'checkbox' => array(
                'label' => __('Checkboxes', 'mavlers-contact-form'),
                'icon' => 'dashicons-yes',
                'settings' => array(
                    'label' => array(
                        'type' => 'text',
                        'label' => __('Field Label', 'mavlers-contact-form'),
                        'required' => true
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __('Description', 'mavlers-contact-form')
                    ),
                    'options' => array(
                        'type' => 'textarea',
                        'label' => __('Options', 'mavlers-contact-form'),
                        'description' => __('One option per line', 'mavlers-contact-form')
                    ),
                    'required' => array(
                        'type' => 'checkbox',
                        'label' => __('Required Field', 'mavlers-contact-form')
                    ),
                    'css_class' => array(
                        'type' => 'text',
                        'label' => __('Custom CSS Class', 'mavlers-contact-form')
                    ),
                    'validation_message' => array(
                        'type' => 'text',
                        'label' => __('Custom Validation Message', 'mavlers-contact-form')
                    )
                )
            ),
            'dropdown' => array(
                'label' => __('Dropdown', 'mavlers-contact-form'),
                'icon' => 'dashicons-arrow-down-alt2',
                'settings' => array(
                    'label' => array(
                        'type' => 'text',
                        'label' => __('Field Label', 'mavlers-contact-form'),
                        'required' => true
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __('Description', 'mavlers-contact-form')
                    ),
                    'options' => array(
                        'type' => 'textarea',
                        'label' => __('Options', 'mavlers-contact-form'),
                        'description' => __('One option per line', 'mavlers-contact-form')
                    ),
                    'required' => array(
                        'type' => 'checkbox',
                        'label' => __('Required Field', 'mavlers-contact-form')
                    ),
                    'css_class' => array(
                        'type' => 'text',
                        'label' => __('Custom CSS Class', 'mavlers-contact-form')
                    ),
                    'validation_message' => array(
                        'type' => 'text',
                        'label' => __('Custom Validation Message', 'mavlers-contact-form')
                    )
                )
            ),
            'number' => array(
                'label' => __('Number', 'mavlers-contact-form'),
                'icon' => 'dashicons-calculator',
                'settings' => array(
                    'label' => array(
                        'type' => 'text',
                        'label' => __('Field Label', 'mavlers-contact-form'),
                        'required' => true
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __('Description', 'mavlers-contact-form')
                    ),
                    'min' => array(
                        'type' => 'number',
                        'label' => __('Minimum Value', 'mavlers-contact-form')
                    ),
                    'max' => array(
                        'type' => 'number',
                        'label' => __('Maximum Value', 'mavlers-contact-form')
                    ),
                    'step' => array(
                        'type' => 'number',
                        'label' => __('Step', 'mavlers-contact-form'),
                        'default' => 1
                    ),
                    'required' => array(
                        'type' => 'checkbox',
                        'label' => __('Required Field', 'mavlers-contact-form')
                    ),
                    'css_class' => array(
                        'type' => 'text',
                        'label' => __('Custom CSS Class', 'mavlers-contact-form')
                    ),
                    'validation_message' => array(
                        'type' => 'text',
                        'label' => __('Custom Validation Message', 'mavlers-contact-form')
                    )
                )
            ),
            'radio' => array(
                'label' => __('Radio Buttons', 'mavlers-contact-form'),
                'icon' => 'dashicons-marker',
                'settings' => array(
                    'label' => array(
                        'type' => 'text',
                        'label' => __('Field Label', 'mavlers-contact-form'),
                        'required' => true
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __('Description', 'mavlers-contact-form')
                    ),
                    'options' => array(
                        'type' => 'textarea',
                        'label' => __('Options', 'mavlers-contact-form'),
                        'description' => __('One option per line', 'mavlers-contact-form')
                    ),
                    'required' => array(
                        'type' => 'checkbox',
                        'label' => __('Required Field', 'mavlers-contact-form')
                    ),
                    'css_class' => array(
                        'type' => 'text',
                        'label' => __('Custom CSS Class', 'mavlers-contact-form')
                    ),
                    'validation_message' => array(
                        'type' => 'text',
                        'label' => __('Custom Validation Message', 'mavlers-contact-form')
                    )
                )
            ),
            'hidden' => array(
                'label' => __('Hidden Field', 'mavlers-contact-form'),
                'icon' => 'dashicons-hidden',
                'settings' => array(
                    'name' => array(
                        'type' => 'text',
                        'label' => __('Field Name', 'mavlers-contact-form'),
                        'required' => true
                    ),
                    'value' => array(
                        'type' => 'text',
                        'label' => __('Default Value', 'mavlers-contact-form')
                    )
                )
            ),
            'file' => array(
                'label' => __('File Upload', 'mavlers-contact-form'),
                'icon' => 'dashicons-upload',
                'settings' => array(
                    'label' => array(
                        'type' => 'text',
                        'label' => __('Field Label', 'mavlers-contact-form'),
                        'required' => true
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __('Description', 'mavlers-contact-form')
                    ),
                    'allowed_types' => array(
                        'type' => 'text',
                        'label' => __('Allowed File Types', 'mavlers-contact-form'),
                        'description' => __('Comma-separated list of file extensions', 'mavlers-contact-form')
                    ),
                    'max_size' => array(
                        'type' => 'number',
                        'label' => __('Maximum File Size (MB)', 'mavlers-contact-form')
                    ),
                    'required' => array(
                        'type' => 'checkbox',
                        'label' => __('Required Field', 'mavlers-contact-form')
                    ),
                    'css_class' => array(
                        'type' => 'text',
                        'label' => __('Custom CSS Class', 'mavlers-contact-form')
                    ),
                    'validation_message' => array(
                        'type' => 'text',
                        'label' => __('Custom Validation Message', 'mavlers-contact-form')
                    )
                )
            ),
            'html' => array(
                'label' => __('HTML', 'mavlers-contact-form'),
                'icon' => 'dashicons-editor-code',
                'settings' => array(
                    'content' => array(
                        'type' => 'textarea',
                        'label' => __('HTML Content', 'mavlers-contact-form')
                    )
                )
            ),
            'divider' => array(
                'label' => __('Divider', 'mavlers-contact-form'),
                'icon' => 'dashicons-minus',
                'settings' => array(
                    'type' => array(
                        'type' => 'select',
                        'label' => __('Type', 'mavlers-contact-form'),
                        'options' => array(
                            'line' => __('Line', 'mavlers-contact-form'),
                            'space' => __('Space', 'mavlers-contact-form'),
                            'text' => __('Text', 'mavlers-contact-form')
                        )
                    ),
                    'text' => array(
                        'type' => 'text',
                        'label' => __('Text', 'mavlers-contact-form'),
                        'show_if' => array('type' => 'text')
                    )
                )
            ),
            'captcha' => array(
                'label' => __('CAPTCHA', 'mavlers-contact-form'),
                'icon' => 'dashicons-shield',
                'settings' => array(
                    'type' => array(
                        'type' => 'select',
                        'label' => __('CAPTCHA Type', 'mavlers-contact-form'),
                        'options' => array(
                            'recaptcha' => __('reCAPTCHA', 'mavlers-contact-form'),
                            'simple' => __('Simple Math', 'mavlers-contact-form')
                        )
                    ),
                    'site_key' => array(
                        'type' => 'text',
                        'label' => __('reCAPTCHA Site Key', 'mavlers-contact-form'),
                        'show_if' => array('type' => 'recaptcha')
                    ),
                    'secret_key' => array(
                        'type' => 'text',
                        'label' => __('reCAPTCHA Secret Key', 'mavlers-contact-form'),
                        'show_if' => array('type' => 'recaptcha')
                    )
                )
            ),
            'submit' => array(
                'label' => __('Submit Button', 'mavlers-contact-form'),
                'icon' => 'dashicons-saved',
                'settings' => array(
                    'text' => array(
                        'type' => 'text',
                        'label' => __('Button Text', 'mavlers-contact-form'),
                        'default' => __('Submit', 'mavlers-contact-form')
                    ),
                    'css_class' => array(
                        'type' => 'text',
                        'label' => __('Custom CSS Class', 'mavlers-contact-form')
                    )
                )
            )
        );
    }

    private function check_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Forms table
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $forms_sql = "CREATE TABLE IF NOT EXISTS $forms_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_name varchar(255) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Fields table
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
        $fields_sql = "CREATE TABLE IF NOT EXISTS $fields_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            field_type varchar(50) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_name varchar(255) NOT NULL,
            field_required tinyint(1) NOT NULL DEFAULT 0,
            field_placeholder text,
            field_description text,
            field_options text,
            field_meta text,
            field_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($forms_sql);
        dbDelta($fields_sql);

        // Debug log table creation
        error_log('Mavlers Form Builder: Checking database tables');
        error_log('Forms table exists: ' . ($wpdb->get_var("SHOW TABLES LIKE '$forms_table'") ? 'Yes' : 'No'));
        error_log('Fields table exists: ' . ($wpdb->get_var("SHOW TABLES LIKE '$fields_table'") ? 'Yes' : 'No'));
    }

    public function handle_field_delete() {
        check_ajax_referer('mavlers_form_builder_nonce', 'nonce');

        $field_id = $_POST['field_id'];
        $form_id = intval($_POST['form_id']);

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'mavlers-contact-form'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_forms';
        
        // Get existing form data
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error(__('Form not found', 'mavlers-contact-form'));
        }

        $form_fields = json_decode($form->form_fields, true);
        if (!is_array($form_fields)) {
            $form_fields = array();
        }

        // Remove field
        foreach ($form_fields as $key => $field) {
            if ($field['id'] == $field_id) {
                unset($form_fields[$key]);
                break;
            }
        }

        // Reindex array
        $form_fields = array_values($form_fields);

        // Save updated form
        $wpdb->update(
            $table_name,
            array('form_fields' => json_encode($form_fields)),
            array('id' => $form_id)
        );

        wp_send_json_success(array(
            'message' => __('Field deleted successfully', 'mavlers-contact-form')
        ));
    }

    public function handle_field_reorder() {
        check_ajax_referer('mavlers_form_builder_nonce', 'nonce');

        $field_ids = $_POST['field_ids'];
        $form_id = intval($_POST['form_id']);

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'mavlers-contact-form'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_forms';
        
        // Get existing form data
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error(__('Form not found', 'mavlers-contact-form'));
        }

        $form_fields = json_decode($form->form_fields, true);
        if (!is_array($form_fields)) {
            $form_fields = array();
        }

        // Reorder fields
        $reordered_fields = array();
        foreach ($field_ids as $field_id) {
            foreach ($form_fields as $field) {
                if ($field['id'] == $field_id) {
                    $reordered_fields[] = $field;
                    break;
                }
            }
        }

        // Save updated form
        $wpdb->update(
            $table_name,
            array('form_fields' => json_encode($reordered_fields)),
            array('id' => $form_id)
        );

        wp_send_json_success(array(
            'message' => __('Fields reordered successfully', 'mavlers-contact-form')
        ));
    }

    public function handle_email_settings_save() {
        check_ajax_referer('mavlers_form_builder_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'mavlers-contact-form'));
        }

        $form_id = intval($_POST['form_id']);
        $settings = array(
            'notification_enabled' => isset($_POST['notification_enabled']),
            'recipient_email' => sanitize_email($_POST['recipient_email']),
            'email_template' => sanitize_text_field($_POST['email_template']),
            'custom_subject' => sanitize_text_field($_POST['custom_subject']),
            'custom_message' => wp_kses_post($_POST['custom_message']),
            'reply_to_field' => sanitize_text_field($_POST['reply_to_field']),
            'cc_emails' => array_map('sanitize_email', explode(',', $_POST['cc_emails'])),
            'bcc_emails' => array_map('sanitize_email', explode(',', $_POST['bcc_emails']))
        );

        update_post_meta($form_id, '_mavlers_email_settings', $settings);
        wp_send_json_success(array('message' => __('Email settings saved successfully', 'mavlers-contact-form')));
    }

    public function handle_email_template_save() {
        check_ajax_referer('mavlers_form_builder_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'mavlers-contact-form'));
        }

        $template_name = sanitize_text_field($_POST['template_name']);
        $template_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'subject' => sanitize_text_field($_POST['subject']),
            'body' => wp_kses_post($_POST['body'])
        );

        $this->email_templates[$template_name] = $template_data;
        update_option('mavlers_email_templates', $this->email_templates);
        wp_send_json_success(array('message' => __('Email template saved successfully', 'mavlers-contact-form')));
    }

    private function save_single_field($field, $form_id, $order) {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
        $field_type  = isset($field['type']) ? sanitize_text_field($field['type']) : '';
        $field_label = isset($field['label']) ? sanitize_text_field($field['label']) : '';
        $field_name  = isset($field['name']) ? sanitize_text_field($field['name']) : '';
        $field_meta  = maybe_serialize($field['meta'] ?? []);
        $field_data = array(
            'form_id'     => $form_id,
            'field_type'  => $field_type,
            'field_label' => $field_label,
            'field_meta'  => $field_meta,
            'field_order' => intval($order),
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        );
        
    
        $wpdb->insert($fields_table, $field_data);
    
        if ($wpdb->insert_id) {
            return $wpdb->insert_id;
        }
    
        return false;
    }
    
    public function handle_get_form_fields() {
        try {
            // Verify nonce
            if (!check_ajax_referer('mavlers_form_builder_nonce', 'nonce', false)) {
                error_log('Mavlers Form Builder: Nonce verification failed in get_form_fields');
                wp_send_json_error(__('Security check failed', 'mavlers-contact-form'));
                return;
            }

            if (!isset($_POST['form_id'])) {
                error_log('Mavlers Form Builder: Missing form ID in get_form_fields');
                wp_send_json_error(__('Missing form ID', 'mavlers-contact-form'));
                return;
            }

            $form_id = intval($_POST['form_id']);

            if (!current_user_can('manage_options')) {
                error_log('Mavlers Form Builder: Permission denied for user ID ' . get_current_user_id());
                wp_send_json_error(__('Permission denied', 'mavlers-contact-form'));
                return;
            }

            global $wpdb;
            $forms_table = $wpdb->prefix . 'mavlers_forms';

            // Get form data
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT form_fields FROM {$forms_table} WHERE id = %d",
                $form_id
            ));

            if (!$form) {
                error_log('Mavlers Form Builder: Form not found. Form ID: ' . $form_id);
                wp_send_json_error(__('Form not found', 'mavlers-contact-form'));
                return;
            }

            // Get fields from form_fields column
            $fields = json_decode($form->form_fields, true);
            
            // Debug log the fields
            error_log('Mavlers Form Builder: Retrieved fields: ' . print_r($fields, true));

            if (!is_array($fields)) {
                error_log('Mavlers Form Builder: Invalid fields data');
                wp_send_json_success(array());
                return;
            }

            wp_send_json_success($fields);
        } catch (Exception $e) {
            error_log('Mavlers Form Builder: Exception in handle_get_form_fields: ' . $e->getMessage());
            wp_send_json_error(__('An error occurred while loading fields', 'mavlers-contact-form'));
        }
    }
    
    public function handle_form_save() {
        try {
            // Verify nonce
            if (!check_ajax_referer('mavlers_form_builder_nonce', 'nonce', false)) {
                error_log('Mavlers Form Builder: Nonce verification failed');
                wp_send_json_error(__('Security check failed', 'mavlers-contact-form'));
                return;
            }

            if (!isset($_POST['form_data']) || !is_array($_POST['form_data'])) {
                error_log('Mavlers Form Builder: Missing form data. POST data: ' . print_r($_POST, true));
                wp_send_json_error(__('Missing form data', 'mavlers-contact-form'));
                return;
            }

            $form_data = $_POST['form_data'];
            $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

            if (!current_user_can('manage_options')) {
                error_log('Mavlers Form Builder: Permission denied for user ID ' . get_current_user_id());
                wp_send_json_error(__('Permission denied', 'mavlers-contact-form'));
                return;
            }

            // Validate fields
            if (!empty($form_data['fields']) && is_array($form_data['fields'])) {
                foreach ($form_data['fields'] as $field) {
                    // Skip validation for submit and HTML field types
                    if (in_array($field['field_type'], ['submit', 'html'])) {
                        continue;
                    }

                    // Validate required fields
                    if (empty($field['field_label'])) {
                        error_log('Mavlers Form Builder: Field label is required for field type: ' . $field['field_type']);
                        wp_send_json_error(__('Field label is required', 'mavlers-contact-form'));
                        return;
                    }
                }
            }

            global $wpdb;
            $forms_table = $wpdb->prefix . 'mavlers_forms';

            // Debug log the incoming form data
            error_log('Mavlers Form Builder: Saving form data: ' . print_r($form_data, true));

            // Prepare form data
            $form_data_to_save = array(
                'form_name' => sanitize_text_field($form_data['title']),
                'form_fields' => json_encode($form_data['fields']),
                'updated_at' => current_time('mysql')
            );

            if ($form_id) {
                // Update existing form
                $result = $wpdb->update(
                    $forms_table,
                    $form_data_to_save,
                    array('id' => $form_id)
                );

                if ($result === false) {
                    error_log('Mavlers Form Builder: Failed to update form. Error: ' . $wpdb->last_error);
                    wp_send_json_error(__('Failed to update form', 'mavlers-contact-form'));
                    return;
                }
            } else {
                // Create new form
                $form_data_to_save['created_at'] = current_time('mysql');
                $form_data_to_save['status'] = 'active';

                $result = $wpdb->insert($forms_table, $form_data_to_save);

                if ($result === false) {
                    error_log('Mavlers Form Builder: Failed to create form. Error: ' . $wpdb->last_error);
                    wp_send_json_error(__('Failed to create form', 'mavlers-contact-form'));
                    return;
                }

                $form_id = $wpdb->insert_id;
            }

            // Verify saved form
            $saved_form = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$forms_table} WHERE id = %d",
                $form_id
            ));
            error_log('Mavlers Form Builder: Saved form verification: ' . print_r($saved_form, true));

            wp_send_json_success(array(
                'form_id' => $form_id,
                'message' => __('Form saved successfully', 'mavlers-contact-form')
            ));
        } catch (Exception $e) {
            error_log('Mavlers Form Builder: Exception in handle_form_save: ' . $e->getMessage());
            wp_send_json_error(__('An error occurred while saving the form', 'mavlers-contact-form'));
        }
    }
    

    public function handle_form_submission($form_id, $form_data) {
        $email_settings = get_post_meta($form_id, '_mavlers_email_settings', true);
        
        if (empty($email_settings) || !$email_settings['notification_enabled']) {
            return;
        }

        $to = $email_settings['recipient_email'];
        $subject = $this->process_email_template($email_settings['custom_subject'], $form_data);
        $message = $this->process_email_template($email_settings['custom_message'], $form_data);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Add CC and BCC
        if (!empty($email_settings['cc_emails'])) {
            $headers[] = 'Cc: ' . implode(',', $email_settings['cc_emails']);
        }
        if (!empty($email_settings['bcc_emails'])) {
            $headers[] = 'Bcc: ' . implode(',', $email_settings['bcc_emails']);
        }

        // Add Reply-To header if specified
        if (!empty($email_settings['reply_to_field']) && isset($form_data[$email_settings['reply_to_field']])) {
            $headers[] = 'Reply-To: ' . $form_data[$email_settings['reply_to_field']];
        }

        wp_mail($to, $subject, $message, $headers);
    }

    private function process_email_template($template, $form_data) {
        $replacements = array(
            '{form_title}' => get_the_title($form_data['form_id']),
            '{form_data}' => $this->format_form_data($form_data),
            '{submission_date}' => current_time('Y-m-d'),
            '{submission_time}' => current_time('H:i:s'),
            '{ip_address}' => $_SERVER['REMOTE_ADDR'],
            '{user_agent}' => $_SERVER['HTTP_USER_AGENT']
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function format_form_data($form_data) {
        $output = '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($form_data as $key => $value) {
            if ($key === 'form_id') continue;
            $output .= sprintf(
                '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>',
                esc_html($key),
                esc_html($value)
            );
        }
        $output .= '</table>';
        return $output;
    }

    public function render_field_preview($field) {
        $html = '';
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $placeholder = isset($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : '';

        switch ($field['type']) {
            case 'text':
                $html = sprintf(
                    '<input type="text" %s %s class="widefat" %s>',
                    $required,
                    $placeholder,
                    $required ? 'required' : ''
                );
                break;

            case 'email':
                $html = sprintf(
                    '<input type="email" %s %s class="widefat" %s>',
                    $required,
                    $placeholder,
                    $required ? 'required' : ''
                );
                break;

            case 'textarea':
                $html = sprintf(
                    '<textarea %s %s class="widefat" %s></textarea>',
                    $required,
                    $placeholder,
                    $required ? 'required' : ''
                );
                break;
        }

        return $html;
    }
}

// Initialize the form builder
Mavlers_Form_Builder::get_instance();

