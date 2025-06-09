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
        if ('toplevel_page_mavlers-forms' !== $hook && 'mavlers-contact-form_page_mavlers-forms' !== $hook) {
            return;
        }

        // Enqueue jQuery UI
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');

        // Enqueue form builder scripts
        wp_enqueue_script(
            'mavlers-form-builder',
            MAVLERS_CONTACT_FORM_PLUGIN_URL . 'assets/js/form-builder.js',
            array('jquery', 'jquery-ui-sortable'),
            MAVLERS_CONTACT_FORM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('mavlers-form-builder', 'mavlersFormBuilder', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_form_builder_nonce'),
            'adminUrl' => admin_url('admin.php'),
            'previewUrl' => home_url('?mavlers_preview=1'),
            'strings' => array(
                'deleteConfirm' => __('Are you sure you want to delete this field?', 'mavlers-contact-form'),
                'saveFormFirst' => __('Please save the form first', 'mavlers-contact-form'),
                'errorLoadingFields' => __('Error loading form fields', 'mavlers-contact-form'),
                'errorSavingForm' => __('Error saving form', 'mavlers-contact-form'),
                'errorSavingField' => __('Error saving field', 'mavlers-contact-form'),
                'errorDeletingField' => __('Error deleting field', 'mavlers-contact-form')
            ),
            'fieldTypes' => $this->get_field_types()
        ));

        // Enqueue styles
        wp_enqueue_style(
            'mavlers-form-builder',
            MAVLERS_CONTACT_FORM_PLUGIN_URL . 'assets/css/form-builder.css',
            array(),
            MAVLERS_CONTACT_FORM_VERSION
        );
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
                    'divider_text' => array(
                        'type' => 'text',
                        'label' => __('Divider Text', 'mavlers-contact-form'),
                        'description' => __('Optional text to display in the divider', 'mavlers-contact-form')
                    ),
                    'divider_style' => array(
                        'type' => 'select',
                        'label' => __('Divider Style', 'mavlers-contact-form'),
                        'options' => array(
                            'solid' => __('Solid Line', 'mavlers-contact-form'),
                            'dashed' => __('Dashed Line', 'mavlers-contact-form'),
                            'dotted' => __('Dotted Line', 'mavlers-contact-form')
                        ),
                        'default' => 'solid'
                    ),
                    'divider_color' => array(
                        'type' => 'text',
                        'label' => __('Divider Color', 'mavlers-contact-form'),
                        'default' => '#ddd'
                    ),
                    'divider_width' => array(
                        'type' => 'select',
                        'label' => __('Divider Width', 'mavlers-contact-form'),
                        'options' => array(
                            'full' => __('Full Width', 'mavlers-contact-form'),
                            'half' => __('Half Width', 'mavlers-contact-form'),
                            'third' => __('One Third', 'mavlers-contact-form')
                        ),
                        'default' => 'full'
                    ),
                    'divider_margin' => array(
                        'type' => 'number',
                        'label' => __('Margin (px)', 'mavlers-contact-form'),
                        'default' => 20
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
        check_ajax_referer('mavlers_form_builder_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $form_id = intval($_POST['form_id']);
        
        if (!$form_id) {
            wp_send_json_error('Invalid form ID');
        }

        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$forms_table} WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error('Form not found');
        }

        $fields = json_decode($form->form_fields, true) ?? array();
        
        // Debug information
        error_log('Loading fields for form ' . $form_id);
        error_log('Form data: ' . print_r($form, true));
        error_log('Fields: ' . print_r($fields, true));

        wp_send_json_success($fields);
    }
    
    public function handle_form_save() {
        check_ajax_referer('mavlers_form_builder_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $form_data = json_decode(stripslashes($_POST['form_data']), true);

        if (!$form_data || !isset($form_data['title'])) {
            wp_send_json_error('Invalid form data');
        }

        // Debug information
        error_log('Saving form ' . $form_id);
        error_log('Form data: ' . print_r($form_data, true));

        $result = $this->save_form($form_id, array(
            'form_name' => sanitize_text_field($form_data['title']),
            'form_fields' => $form_data['fields']
        ));

        if ($result) {
            wp_send_json_success(array('form_id' => $result));
        } else {
            wp_send_json_error('Failed to save form');
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
        $field_type = isset($field['field_type']) ? $field['field_type'] : '';
        $field_label = isset($field['field_label']) ? $field['field_label'] : '';
        $field_name = isset($field['field_name']) ? $field['field_name'] : '';
        $field_required = isset($field['field_required']) ? $field['field_required'] : false;
        $field_placeholder = isset($field['field_placeholder']) ? $field['field_placeholder'] : '';
        $field_description = isset($field['field_description']) ? $field['field_description'] : '';
        $field_options = isset($field['field_options']) ? $field['field_options'] : array();
        $field_content = isset($field['field_content']) ? $field['field_content'] : '';

        // Common attributes
        $required = $field_required ? 'required' : '';
        $placeholder = $field_placeholder ? 'placeholder="' . esc_attr($field_placeholder) . '"' : '';

        switch ($field_type) {
            case 'text':
            case 'email':
            case 'number':
            case 'tel':
            case 'url':
                $html = sprintf(
                    '<input type="%s" name="%s" class="widefat" %s %s>',
                    esc_attr($field_type),
                    esc_attr($field_name),
                    $required,
                    $placeholder
                );
                break;

            case 'textarea':
                $html = sprintf(
                    '<textarea name="%s" class="widefat" %s %s></textarea>',
                    esc_attr($field_name),
                    $required,
                    $placeholder
                );
                break;

            case 'select':
                $html = '<select name="' . esc_attr($field_name) . '" class="widefat" ' . $required . '>';
                if ($field_placeholder) {
                    $html .= '<option value="">' . esc_html($field_placeholder) . '</option>';
                }
                if (is_array($field_options)) {
                    foreach ($field_options as $option) {
                        $html .= sprintf(
                            '<option value="%s">%s</option>',
                            esc_attr($option),
                            esc_html($option)
                        );
                    }
                }
                $html .= '</select>';
                break;

            case 'checkbox':
            case 'radio':
                if (is_array($field_options)) {
                    foreach ($field_options as $option) {
                        $html .= sprintf(
                            '<label class="mavlers-%s-label"><input type="%s" name="%s" value="%s" %s> %s</label>',
                            $field_type,
                            $field_type,
                            esc_attr($field_name),
                            esc_attr($option),
                            $required,
                            esc_html($option)
                        );
                    }
                }
                break;

            case 'file':
                $html = sprintf(
                    '<input type="file" name="%s" class="widefat" %s>',
                    esc_attr($field_name),
                    $required
                );
                break;

            case 'html':
                $html = wp_kses_post($field_content);
                break;

            case 'divider':
                $divider_style = isset($field['divider_style']) ? $field['divider_style'] : 'solid';
                $divider_color = isset($field['divider_color']) ? $field['divider_color'] : '#ddd';
                $divider_width = isset($field['divider_width']) ? $field['divider_width'] : 'full';
                $divider_text = isset($field['divider_text']) ? $field['divider_text'] : '';

                $width_class = '';
                switch ($divider_width) {
                    case 'half':
                        $width_class = 'mavlers-divider-half';
                        break;
                    case 'third':
                        $width_class = 'mavlers-divider-third';
                        break;
                    default:
                        $width_class = 'mavlers-divider-full';
                }

                $html = '<div class="mavlers-divider ' . esc_attr($width_class) . '">';
                if ($divider_text) {
                    $html .= '<span class="mavlers-divider-text">' . esc_html($divider_text) . '</span>';
                }
                $html .= '<hr style="border-style: ' . esc_attr($divider_style) . '; border-color: ' . esc_attr($divider_color) . ';">';
                $html .= '</div>';
                break;

            case 'submit':
                $button_text = isset($field['text']) ? $field['text'] : __('Submit', 'mavlers-contact-form');
                $html = sprintf(
                    '<button type="submit" class="mavlers-submit-button">%s</button>',
                    esc_html($button_text)
                );
                break;

            default:
                $html = '<p class="mavlers-field-error">' . __('Unknown field type', 'mavlers-contact-form') . '</p>';
                break;
        }

        return $html;
    }

    public function save_form($form_id, $form_data) {
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';

        $data = array(
            'form_name' => sanitize_text_field($form_data['form_name']),
            'form_fields' => wp_json_encode($form_data['form_fields']),
            'updated_at' => current_time('mysql')
        );

        if ($form_id) {
            $wpdb->update(
                $forms_table,
                $data,
                array('id' => $form_id)
            );
            return $form_id;
        } else {
            $data['created_at'] = current_time('mysql');
            $data['status'] = 'active';
            $wpdb->insert($forms_table, $data);
            return $wpdb->insert_id;
        }
    }

    public function save_field($form_id, $field_data) {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';

        $data = array(
            'form_id' => $form_id,
            'field_type' => sanitize_text_field($field_data['field_type']),
            'field_label' => sanitize_text_field($field_data['field_label']),
            'field_name' => sanitize_text_field($field_data['field_name']),
            'field_meta' => wp_json_encode($field_data),
            'field_order' => intval($field_data['field_order']),
            'updated_at' => current_time('mysql')
        );

        if (isset($field_data['id']) && $field_data['id']) {
            $wpdb->update(
                $fields_table,
                $data,
                array('id' => $field_data['id']),
                array('%d', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            return $field_data['id'];
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($fields_table, $data);
            return $wpdb->insert_id;
        }
    }

    public function delete_field($field_id) {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';
        
        return $wpdb->delete(
            $fields_table,
            array('id' => $field_id),
            array('%d')
        );
    }

    public function update_field_order($form_id, $fields) {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';

        foreach ($fields as $field) {
            $wpdb->update(
                $fields_table,
                array('field_order' => intval($field['field_order'])),
                array('id' => $field['id']),
                array('%d'),
                array('%d')
            );
        }

        return true;
    }
}

// Initialize the form builder
Mavlers_Form_Builder::get_instance();

