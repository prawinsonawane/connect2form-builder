<?php
if (!defined('WPINC')) {
    die;
}

class Mavlers_CF_Admin {
    private $plugin_name;
    private $version;
    private $settings;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Initialize settings
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mavlers-cf-settings.php';
        $this->settings = new Mavlers_CF_Settings();

        // Add admin menu
        add_action('admin_menu', array($this, 'add_menu_pages'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_mavlers_cf_save_form', array($this, 'ajax_save_form'));
        add_action('wp_ajax_mavlers_cf_delete_form', array($this, 'ajax_delete_form'));
        add_action('wp_ajax_mavlers_cf_duplicate_form', array($this, 'ajax_duplicate_form'));
        add_action('wp_ajax_mavlers_cf_get_form', array($this, 'ajax_get_form'));
        add_action('wp_ajax_mavlers_cf_preview_form', array($this, 'ajax_preview_form'));
        add_action('wp_ajax_mavlers_cf_test_email', array($this, 'ajax_test_email'));
        add_action('wp_ajax_mavlers_cf_test_recaptcha', array($this, 'ajax_test_recaptcha'));
    }

    public function add_menu_pages() {
        add_menu_page(
            __('Contact Forms', 'mavlers-contact-forms'),
            __('Contact Forms', 'mavlers-contact-forms'),
            'manage_options',
            'mavlers-contact-forms',
            array($this, 'render_forms_list_page'),
            'dashicons-email',
            30
        );

        add_submenu_page(
            'mavlers-contact-forms',
            __('All Forms', 'mavlers-contact-forms'),
            __('All Forms', 'mavlers-contact-forms'),
            'manage_options',
            'mavlers-contact-forms',
            array($this, 'render_forms_list_page')
        );

        add_submenu_page(
            'mavlers-contact-forms',
            __('Add New', 'mavlers-contact-forms'),
            __('Add New', 'mavlers-contact-forms'),
            'manage_options',
            'mavlers-cf-new-form',
            array($this, 'render_form_builder')
        );

        add_submenu_page(
            'mavlers-contact-forms',
            __('Submissions', 'mavlers-contact-forms'),
            __('Submissions', 'mavlers-contact-forms'),
            'manage_options',
            'mavlers-cf-submissions',
            array($this, 'render_submissions_page')
        );

        add_submenu_page(
            'mavlers-contact-forms',
            __('Settings', 'mavlers-contact-forms'),
            __('Settings', 'mavlers-contact-forms'),
            'manage_options',
            'mavlers-cf-settings',
            array($this->settings, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'mavlers-contact-forms') === false && strpos($hook, 'mavlers-cf-new-form') === false) {
            return;
        }

        // Enqueue WordPress media scripts
        wp_enqueue_media();

        // Enqueue dashicons
        wp_enqueue_style('dashicons');

        // Enqueue jQuery UI
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('jquery-ui-accordion');

        // Enqueue admin styles
        wp_enqueue_style(
            'mavlers-cf-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
            array('dashicons'),
            MAVLERS_CF_VERSION
        );

        // Enqueue form builder styles
        wp_enqueue_style(
            'mavlers-cf-form-builder',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/form-builder.css',
            array('dashicons'),
            MAVLERS_CF_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'mavlers-cf-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-core', 'jquery-ui-sortable'),
            MAVLERS_CF_VERSION,
            true
        );

        // Enqueue form builder scripts
        wp_enqueue_script(
            'mavlers-cf-form-builder',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/form-builder.js',
            array('jquery', 'jquery-ui-core', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            MAVLERS_CF_VERSION,
            true
        );
                // Load integration scripts if addon is active
                if (file_exists(plugin_dir_path(dirname(__FILE__)) . 'addons/integrations-manager/integrations-manager.php')) {
                    // Enqueue integration styles
                    wp_enqueue_style(
                        'mavlers-cf-integrations-admin',
                        plugin_dir_url(dirname(__FILE__)) . 'addons/integrations-manager/assets/css/admin/integrations-admin.css',
                        array('dashicons'),
                        MAVLERS_CF_VERSION
                    );
        
                    // Enqueue integration scripts
                    wp_enqueue_script(
                        'mavlers-cf-integrations-admin',
                        plugin_dir_url(dirname(__FILE__)) . 'addons/integrations-manager/assets/js/admin/integrations-admin.js',
                        array('jquery', 'wp-util'),
                        MAVLERS_CF_VERSION,
                        true
                    );
        
                    // Enqueue Mailchimp scripts for form edit pages
                    // Note: HubSpot handles its own script loading to avoid conflicts
                    wp_enqueue_script(
                        'mavlers-cf-mailchimp',
                        plugin_dir_url(dirname(__FILE__)) . 'addons/integrations-manager/assets/js/admin/mailchimp.js',
                        array('jquery', 'wp-util'),
                        MAVLERS_CF_VERSION,
                        true
                    );
        
                    wp_enqueue_script(
                        'mavlers-cf-mailchimp-form',
                        plugin_dir_url(dirname(__FILE__)) . 'addons/integrations-manager/assets/js/admin/mailchimp-form.js',
                        array('jquery', 'wp-util', 'mavlers-cf-mailchimp'),
                        MAVLERS_CF_VERSION,
                        true
                    );
        
                    // Note: Each integration handles its own localization to avoid conflicts
                }
    }

    /**
     * Debug function to check database table
     */
    private function debug_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_cf_forms';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        error_log('Table exists: ' . ($table_exists ? 'Yes' : 'No'));
        
        if ($table_exists) {
            // Get table structure
            $table_structure = $wpdb->get_results("DESCRIBE $table_name");
            error_log('Table structure: ' . print_r($table_structure, true));
            
            // Get all forms
            $forms = $wpdb->get_results("SELECT * FROM $table_name");
            error_log('Forms in table: ' . print_r($forms, true));
        }
    }

    /**
     * Render the forms list page
     */
    public function render_forms_list_page() {
        // Check if we're editing a form
        if (isset($_GET['action']) && $_GET['action'] === 'edit') {
            $form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $this->render_form_builder($form_id);
            return;
        }

        // Check if we're previewing a form
        if (isset($_GET['action']) && $_GET['action'] === 'preview') {
            $form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $this->render_form_preview($form_id);
            return;
        }

        // Include the list table class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mavlers-cf-forms-list-table.php';

        // Create an instance of our list table class
        $forms_table = new Mavlers_CF_Forms_List_Table();
        $forms_table->prepare_items();

        // Add new form button
        $add_new_url = admin_url('admin.php?page=mavlers-cf-new-form');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Contact Forms', 'mavlers-contact-forms'); ?></h1>

            <hr class="wp-header-end">

            <form method="post">
                <?php
                $forms_table->search_box(__('Search Forms', 'mavlers-contact-forms'), 'search_id');
                $forms_table->display();
                ?>
            </form>
        </div>
        <?php

        // Localize admin script for forms list page
        wp_localize_script('mavlers-cf-admin', 'mavlersCF', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'confirmDelete' => __('Are you sure you want to delete this form?', 'mavlers-contact-forms'),
            'copiedText' => __('Copied!', 'mavlers-contact-forms'),
            'previewUrl' => admin_url('admin.php?page=mavlers-contact-forms&action=preview')
        ));
    }

    /**
     * Render the form builder page
     */
    public function render_form_builder($form_id = null) {
        // If no form_id passed, check URL parameters
        if (!$form_id && isset($_GET['id'])) {
            $form_id = intval($_GET['id']);
        }
        
        $form = null;
        if ($form_id) {
            global $wpdb;
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
                $form_id
            ));
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo $form_id ? 'Edit Form' : 'Add New Form'; ?></h1>
            <hr class="wp-header-end">

            <div class="form-builder-header">
                <div class="form-title-section">
                    <input type="text" id="form-title" name="form_title" value="<?php echo esc_attr($form ? $form->form_title : ''); ?>" placeholder="Enter form title" class="regular-text">
                </div>
                <div class="form-actions">
                    <button type="button" class="button button-primary" id="save-form">Save Form</button>
                    <button type="button" class="button button-secondary" id="preview-form">Preview Form</button>
                    <a href="<?php echo admin_url('admin.php?page=mavlers-contact-forms'); ?>" class="button">Cancel</a>
                </div>
            </div>
            <div id="form-notification" class="mavlers-cf-notice" style="display: none;"></div>
            <div class="form-builder-vertical-layout" id="form-builder" data-form-id="<?php echo esc_attr($form_id ? $form_id : 0); ?>">
                <div class="form-builder-tabs">
                    <button type="button" class="tab-link active" data-tab="fields-tab">
                        <span class="dashicons dashicons-editor-table"></span> Fields
                    </button>
                    <button type="button" class="tab-link" data-tab="email-tab">
                        <span class="dashicons dashicons-email-alt"></span> Email Notifications
                    </button>
                    <button type="button" class="tab-link" data-tab="messages-tab">
                        <span class="dashicons dashicons-format-chat"></span> Messages
                    </button>
                    <button type="button" class="tab-link" data-tab="integration-tab">
                        <span class="dashicons dashicons-admin-plugins"></span> Integration
                    </button>
                </div>
                <div class="form-builder-tab-contents">
                    <div class="form-builder-tab-content fields-tab active">
                        <div class="form-builder-fields-layout">
                            <div class="form-fields-container">
                                <div class="form-fields" id="form-fields">
                                    <?php
                                    if ($form && !empty($form->fields)) {
                                        $fields = json_decode($form->fields, true);
                                        foreach ($fields as $field) {
                                            $this->render_field_preview($field);
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="form-fields-sidebar">
                                <div class="field-types">
                                    <h3><?php _e('Field Types', 'mavlers-contact-forms'); ?></h3>
                                    <div class="field-type-list">
                                        <?php
                                        $field_types = array(
                                            'text' => array(
                                                'label' => __('Textbox', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-editor-textcolor',
                                                'settings' => array('label', 'placeholder', 'required', 'validation')
                                            ),
                                            'textarea' => array(
                                                'label' => __('Textarea', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-editor-paragraph'
                                            ),
                                            'email' => array(
                                                'label' => __('Email', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-email'
                                            ),
                                            'number' => array(
                                                'label' => __('Number', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-calculator'
                                            ),
                                            'date' => array(
                                                'label' => __('Date', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-calendar-alt'
                                            ),
                                            'select' => array(
                                                'label' => __('Drop Down', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-arrow-down-alt2'
                                            ),
                                            'radio' => array(
                                                'label' => __('Radio Buttons', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-marker'
                                            ),
                                            'checkbox' => array(
                                                'label' => __('Checkboxes', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-yes'
                                            ),
                                            'file' => array(
                                                'label' => __('File Upload', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-upload'
                                            ),
                                            'captcha' => array(
                                                'label' => __('reCAPTCHA', 'mavlers-contact-forms'),
                                                'icon' => 'dashicons-shield'
                                            )
                                        );

                                        foreach ($field_types as $type => $field) {
                                            ?>
                                            <div class="field-type" data-type="<?php echo esc_attr($type); ?>">
                                                <span class="dashicons <?php echo esc_attr($field['icon']); ?>"></span>
                                                <span class="field-type-label"><?php echo esc_html($field['label']); ?></span>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                        </div>

                                        </div>
                        </div>
                    </div>
                    <div class="form-builder-tab-content email-tab">
                        <h3>Email Notifications</h3>
                        <div id="email-notifications-list"></div>
                        <button type="button" class="button" id="add-email-notification">Add Notification</button>
                        <div id="email-notification-editor" style="display:none;"></div>
                    </div>
                    <div class="form-builder-tab-content messages-tab">
                        <h3>Form Messages</h3>
                        <div class="messages-settings">
                            <div class="message-setting">
                                <label for="success-message">Success Message</label>
                                <textarea id="success-message" class="widefat" rows="3" placeholder="Thank you! Your form has been submitted successfully."><?php echo esc_textarea($form && isset($form->settings) ? json_decode($form->settings, true)['success_message'] ?? '' : ''); ?></textarea>
                                <p class="description">Message displayed after successful form submission.</p>
                            </div>
                            
                            <div class="message-setting">
                                <label for="error-message">Error Message</label>
                                <textarea id="error-message" class="widefat" rows="3" placeholder="Please fix the errors below and try again."><?php echo esc_textarea($form && isset($form->settings) ? json_decode($form->settings, true)['error_message'] ?? '' : ''); ?></textarea>
                                <p class="description">Message displayed when there are validation errors.</p>
                            </div>
                            
                            <div class="message-setting">
                                <label for="submit-button-text">Submit Button Text</label>
                                <input type="text" id="submit-button-text" class="widefat" value="<?php echo esc_attr($form && isset($form->settings) ? json_decode($form->settings, true)['submit_text'] ?? 'Submit' : 'Submit'); ?>" placeholder="Submit">
                                <p class="description">Text displayed on the submit button.</p>
                            </div>
                            
                            <div class="message-setting">
                                <label for="required-field-message">Required Field Message</label>
                                <input type="text" id="required-field-message" class="widefat" value="<?php echo esc_attr($form && isset($form->settings) ? json_decode($form->settings, true)['required_message'] ?? 'This field is required.' : 'This field is required.'); ?>" placeholder="This field is required.">
                                <p class="description">Message displayed for required fields that are not filled.</p>
                            </div>
                            
                            <div class="message-setting">
                                <label for="redirect-type">After Submission Action</label>
                                <select id="redirect-type" class="widefat">
                                    <option value="message" <?php selected($form && isset($form->settings) ? json_decode($form->settings, true)['redirect_type'] ?? 'message' : 'message', 'message'); ?>><?php _e('Show Thank You Message', 'mavlers-contact-forms'); ?></option>
                                    <option value="redirect" <?php selected($form && isset($form->settings) ? json_decode($form->settings, true)['redirect_type'] ?? 'message' : 'message', 'redirect'); ?>><?php _e('Redirect to URL', 'mavlers-contact-forms'); ?></option>
                                </select>
                                <p class="description">Choose what happens after successful form submission.</p>
                            </div>
                            
                            <div class="message-setting" id="thank-you-message-container">
                                <label for="thank-you-message">Thank You Message</label>
                                <textarea id="thank-you-message" class="widefat" rows="5" placeholder="Thank you for your submission! We'll get back to you soon."><?php echo esc_textarea($form && isset($form->settings) ? json_decode($form->settings, true)['thank_you_message'] ?? '' : ''); ?></textarea>
                                <p class="description">Message displayed after successful form submission (when "Show Thank You Message" is selected).</p>
                            </div>
                            
                            <div class="message-setting" id="redirect-url-container" style="display: none;">
                                <label for="redirect-url">Redirect URL</label>
                                <input type="url" id="redirect-url" class="widefat" value="<?php echo esc_attr($form && isset($form->settings) ? json_decode($form->settings, true)['redirect_url'] ?? '' : ''); ?>" placeholder="https://example.com/thank-you">
                                <p class="description">URL to redirect to after successful form submission (when "Redirect to URL" is selected).</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-builder-tab-content integration-tab">
                        <h3>Form Integration</h3>
                        <div class="integration-settings">
                            <div class="integration-setting">
                                <h4>Shortcode</h4>
                                <div class="shortcode-display">
                                    <code id="form-shortcode">[mavlers_contact_form id="<?php echo $form_id ? $form_id : 'FORM_ID'; ?>"]</code>
                                    <button type="button" class="button copy-shortcode" data-clipboard-target="#form-shortcode">
                                        <span class="dashicons dashicons-clipboard"></span> Copy
                                    </button>
                                </div>
                                <p class="description">Use this shortcode to display the form on any page or post.</p>
                            </div>
                            
                            <div class="integration-setting">
                                <h4>PHP Code</h4>
                                <div class="php-code-display">
                                    <code id="form-php-code">&lt;?php echo do_shortcode('[mavlers_contact_form id="<?php echo $form_id ? $form_id : 'FORM_ID'; ?>"]'); ?&gt;</code>
                                    <button type="button" class="button copy-php-code" data-clipboard-target="#form-php-code">
                                        <span class="dashicons dashicons-clipboard"></span> Copy
                                    </button>
                                </div>
                                <p class="description">Use this PHP code to display the form in your theme files.</p>
                            </div>
                            
                            <div class="integration-setting">
                                <h4>Form Settings</h4>
                                <div class="form-options">
                                    <label>
                                        <input type="checkbox" id="enable-ajax" <?php checked($form && isset($form->settings) ? json_decode($form->settings, true)['enable_ajax'] ?? true : true); ?>>
                                        Enable AJAX submission
                                    </label>
                                    <p class="description">Submit form without page reload.</p>
                                    
                                    <label>
                                        <input type="checkbox" id="enable-scroll-to-error" <?php checked($form && isset($form->settings) ? json_decode($form->settings, true)['scroll_to_error'] ?? true : true); ?>>
                                        Scroll to error on validation failure
                                    </label>
                                    <p class="description">Automatically scroll to the first error field.</p>
                                    
                                    <label>
                                        <input type="checkbox" id="enable-honeypot" <?php checked($form && isset($form->settings) ? json_decode($form->settings, true)['enable_honeypot'] ?? true : true); ?>>
                                        Enable honeypot protection
                                    </label>
                                    <p class="description">Add hidden field to prevent spam bots.</p>
                                </div>
                            </div>

                                                <?php
                            // Action hook for additional integrations
                            do_action('mavlers_cf_render_additional_integrations', $form_id, $form);
                            ?>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($){
            // Tab switching
            $('.tab-link').on('click', function(){
                var tab = $(this).data('tab');
                $('.tab-link').removeClass('active');
                $(this).addClass('active');
                $('.form-builder-tab-content').removeClass('active').hide();
                $('.' + tab).addClass('active').show();
            });
        });
        </script>
        <?php
        
        // Localize script with form-specific data
        wp_localize_script('mavlers-cf-form-builder', 'mavlersCF', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'formsListUrl' => admin_url('admin.php?page=mavlers-contact-forms'),
            'currentFormId' => $form_id ? $form_id : 0,
            'adminEmail' => get_option('admin_email'),
            'formData' => $form ? array(
                'id' => $form->id,
                'title' => $form->form_title,
                'fields' => json_decode($form->fields, true),
                'settings' => json_decode($form->settings, true)
            ) : null,
            'i18n' => array(
                'confirmDelete' => __('Are you sure you want to delete this field?', 'mavlers-contact-forms'),
                'fieldRequired' => __('This field is required', 'mavlers-contact-forms'),
                'saveSuccess' => __('Form saved successfully', 'mavlers-contact-forms'),
                'saveError' => __('Error saving form', 'mavlers-contact-forms')
            )
        ));

        // Localize admin script
        wp_localize_script('mavlers-cf-admin', 'mavlersCF', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'confirmDelete' => __('Are you sure you want to delete this form?', 'mavlers-contact-forms'),
            'copiedText' => __('Copied!', 'mavlers-contact-forms'),
            'previewUrl' => admin_url('admin.php?page=mavlers-contact-forms&action=preview')
        ));
    }

    /**
     * Render a field preview
     */
    private function render_field_preview($field) {
        $field_id = $field['id'] ?? uniqid('field_');
        $field_type = $field['type'] ?? 'text';
        $field_label = $field['label'] ?? '';
        $field_required = isset($field['required']) ? (bool)$field['required'] : false;
        $field_placeholder = $field['placeholder'] ?? '';
        $field_description = $field['description'] ?? '';
        $field_options = $field['options'] ?? [];
        $field_validation = $field['validation'] ?? [];
        $field_conditional = $field['conditional'] ?? null;
        $field_advanced = $field['advanced'] ?? [];
        ?>
        <div class="form-field" data-field-id="<?php echo esc_attr($field_id); ?>" data-field-type="<?php echo esc_attr($field_type); ?>" data-field-data='<?php echo esc_attr(json_encode($field)); ?>'>
            <div class="field-header">
                <span class="field-label"><?php echo esc_html($field_label); ?></span>
                <div class="field-actions">
                    <button type="button" class="edit-field" title="Edit Field">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="delete-field" title="Delete Field">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            <div class="field-preview">
                <?php
                switch ($field_type) {
                    case 'text':
                    case 'email':
                    case 'number':
                    case 'date':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="<?php echo esc_attr($field_type); ?>" 
                               placeholder="<?php echo esc_attr($field_placeholder); ?>"
                               <?php echo $field_required ? 'required' : ''; ?>>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'textarea':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <textarea placeholder="<?php echo esc_attr($field_placeholder); ?>"
                                  <?php echo $field_required ? 'required' : ''; ?>></textarea>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'select':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <select <?php echo $field_required ? 'required' : ''; ?>>
                            <option value=""><?php echo esc_html($field_placeholder); ?></option>
                            <?php foreach ($field_options as $option): ?>
                                <?php
                                // Handle both string options and object options
                                if (is_string($option)) {
                                    $option_value = $option;
                                    $option_label = $option;
                                } else {
                                    $option_value = $option['value'] ?? $option;
                                    $option_label = $option['label'] ?? $option_value;
                                }
                                ?>
                                <option value="<?php echo esc_attr($option_value); ?>">
                                    <?php echo esc_html($option_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'radio':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <?php foreach ($field_options as $option): ?>
                            <?php
                            // Handle both string options and object options
                            if (is_string($option)) {
                                $option_value = $option;
                                $option_label = $option;
                            } else {
                                $option_value = $option['value'] ?? $option;
                                $option_label = $option['label'] ?? $option_value;
                            }
                            ?>
                            <label class="radio-option">
                                <input type="radio" 
                                       name="<?php echo esc_attr($field_id); ?>"
                                       value="<?php echo esc_attr($option_value); ?>"
                                       <?php echo $field_required ? 'required' : ''; ?>>
                                <?php echo esc_html($option_label); ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'checkbox':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <?php foreach ($field_options as $option): ?>
                            <?php
                            // Handle both string options and object options
                            if (is_string($option)) {
                                $option_value = $option;
                                $option_label = $option;
                            } else {
                                $option_value = $option['value'] ?? $option;
                                $option_label = $option['label'] ?? $option_value;
                            }
                            ?>
                            <label class="checkbox-option">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr($field_id); ?>[]"
                                       value="<?php echo esc_attr($option_value); ?>"
                                       <?php echo $field_required ? 'required' : ''; ?>>
                                <?php echo esc_html($option_label); ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'file':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="file" <?php echo $field_required ? 'required' : ''; ?>>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function render_submissions_page() {
        // Check if we're viewing a specific submission
        if (isset($_GET['action']) && $_GET['action'] === 'view') {
            $submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $this->render_submission_view($submission_id);
            return;
        }

        // Include the list table class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mavlers-cf-submissions-list-table.php';

        // Get form ID from URL if specified
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        
        // If no form_id specified, try to get it from the first submission
        if (!$form_id) {
            global $wpdb;
            $first_submission = $wpdb->get_row("SELECT form_id FROM {$wpdb->prefix}mavlers_cf_submissions ORDER BY id DESC LIMIT 1");
            if ($first_submission) {
                $form_id = $first_submission->form_id;
            }
        }

        // Create an instance of our list table class with form ID
        $submissions_table = new Mavlers_CF_Submissions_List_Table($form_id);
        $submissions_table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php _e('Form Submissions', 'mavlers-contact-forms'); ?></h1>
            <hr class="wp-header-end">

            <form method="post">
                <?php
                $submissions_table->search_box(__('Search Submissions', 'mavlers-contact-forms'), 'search_id');
                $submissions_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render individual submission view
     */
    private function render_submission_view($submission_id) {
        global $wpdb;
        
        // Get submission data
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mavlers_cf_submissions WHERE id = %d",
            $submission_id
        ));

        if (!$submission) {
            wp_die(__('Submission not found.', 'mavlers-contact-forms'));
        }

        // Get form data
        $form = $this->get_form($submission->form_id);
        if (!$form) {
            wp_die(__('Form not found.', 'mavlers-contact-forms'));
        }

        // Parse submission data
        $submission_data = json_decode($submission->data, true);
        if (!$submission_data) {
            $submission_data = array();
        }

        // Debug information
        error_log('Mavlers CF: Submission view - Form ID: ' . $submission->form_id);
        error_log('Mavlers CF: Submission view - Form fields: ' . print_r($form->fields, true));
        error_log('Mavlers CF: Submission view - Submission data: ' . print_r($submission_data, true));

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php printf(__('Submission #%d', 'mavlers-contact-forms'), $submission_id); ?>
            </h1>
            <a href="<?php echo admin_url('admin.php?page=mavlers-cf-submissions'); ?>" class="page-title-action">
                <?php _e('← Back to Submissions', 'mavlers-contact-forms'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="submission-details">
                <div class="submission-meta">
                    <h2><?php _e('Submission Details', 'mavlers-contact-forms'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Form', 'mavlers-contact-forms'); ?></th>
                            <td><?php echo esc_html($form->form_title); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Submitted', 'mavlers-contact-forms'); ?></th>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('IP Address', 'mavlers-contact-forms'); ?></th>
                            <td><?php echo esc_html($submission->ip_address ?? __('N/A', 'mavlers-contact-forms')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('User Agent', 'mavlers-contact-forms'); ?></th>
                            <td><?php echo esc_html($submission->user_agent ?? __('N/A', 'mavlers-contact-forms')); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="submission-fields">
                    <h2><?php _e('Form Data', 'mavlers-contact-forms'); ?></h2>
                    <table class="form-table">
                        <?php
                        if (!empty($form->fields) && is_array($form->fields)) {
                            foreach ($form->fields as $field) {
                                $field_id = $field['id'];
                                $field_label = $field['label'] ?? $field_id;
                                $field_value = $submission_data[$field_id] ?? '';
                                
                                // Skip certain field types
                                if ($field['type'] === 'html' || $field['type'] === 'hidden' || $field['type'] === 'submit') {
                                    continue;
                                }

                                echo '<tr>';
                                echo '<th scope="row">' . esc_html($field_label) . '</th>';
                                echo '<td>' . $this->format_submission_field_value($field, $field_value) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="2">' . __('No form fields found or form fields not properly configured.', 'mavlers-contact-forms') . '</td></tr>';
                        }
                        ?>
                    </table>
                </div>

                <div class="submission-actions">
                    <h2><?php _e('Actions', 'mavlers-contact-forms'); ?></h2>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=mavlers-cf-submissions'); ?>" class="button">
                            <?php _e('← Back to Submissions', 'mavlers-contact-forms'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=mavlers-cf-submissions&action=delete&id=' . $submission_id); ?>" 
                           class="button button-link-delete" 
                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this submission?', 'mavlers-contact-forms'); ?>');">
                            <?php _e('Delete Submission', 'mavlers-contact-forms'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Format submission field value for display
     */
    private function format_submission_field_value($field, $value) {
        if (empty($value)) {
            return '<em>' . __('No value', 'mavlers-contact-forms') . '</em>';
        }

        switch ($field['type']) {
            case 'file_upload':
                if (is_array($value)) {
                    $links = array();
                    foreach ($value as $url) {
                        $links[] = sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            esc_url($url),
                            __('View File', 'mavlers-contact-forms')
                        );
                    }
                    return implode('<br>', $links);
                }
                return sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($value),
                    __('View File', 'mavlers-contact-forms')
                );

            case 'checkbox':
            case 'multiselect':
                if (is_array($value)) {
                    return implode(', ', array_map('esc_html', $value));
                }
                return esc_html($value);

            case 'radio':
            case 'select':
                return esc_html($value);

            case 'textarea':
                return '<pre>' . esc_html($value) . '</pre>';

            case 'date':
                return date_i18n(get_option('date_format'), strtotime($value));

            case 'email':
                return sprintf('<a href="mailto:%s">%s</a>', esc_attr($value), esc_html($value));

            default:
                return esc_html($value);
        }
    }

    private function get_form($form_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_cf_forms';
        $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $form_id));
        
        if ($form) {
            $form->fields = json_decode($form->fields, true);
            $form->settings = json_decode($form->settings, true);
        }
        
        return $form;
    }

    /**
     * AJAX handler for saving forms
     */
    public function ajax_save_form() {
        try {
            // Verify nonce
            if (!check_ajax_referer('mavlers_cf_nonce', 'nonce', false)) {
                throw new Exception('Invalid security token');
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception('Permission denied');
            }

            // Get and validate form data
            $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
            $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
            $fields = isset($_POST['fields']) ? stripslashes($_POST['fields']) : '';
            $settings = isset($_POST['settings']) ? stripslashes($_POST['settings']) : '';

            // Filter form data before validation
            $form_data = apply_filters('mavlers_cf_form_data_before_save', array(
                'form_id' => $form_id,
                'title' => $title,
                'fields' => $fields,
                'settings' => $settings
            ));

            $form_id = $form_data['form_id'];
            $title = $form_data['title'];
            $fields = $form_data['fields'];
            $settings = $form_data['settings'];

            if (empty($title)) {
                throw new Exception('Form title is required');
            }

            if (empty($fields)) {
                throw new Exception('Form fields are required');
            }

            // Validate JSON
            $fields_data = json_decode($fields, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid form fields data: ' . json_last_error_msg());
            }

            // Filter fields data
            $fields_data = apply_filters('mavlers_cf_fields_data_before_save', $fields_data, $form_id);
            $fields = json_encode($fields_data);

            if (empty($settings)) {
                $settings = json_encode(array(
                    'submit_text' => 'Submit',
                    'success_message' => 'Form submitted successfully',
                    'error_message' => 'Please fix the errors below',
                    'required_message' => 'This field is required.',
                    'redirect_type' => 'message',
                    'thank_you_message' => 'Thank you for your submission! We\'ll get back to you soon.',
                    'redirect_url' => ''
                ));
            }

            // Validate settings JSON
            $settings_data = json_decode($settings, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid form settings data: ' . json_last_error_msg());
            }

            // Filter settings data
            $settings_data = apply_filters('mavlers_cf_settings_data_before_save', $settings_data, $form_id);
            $settings = json_encode($settings_data);

            global $wpdb;
            $table_name = $wpdb->prefix . 'mavlers_cf_forms';

            $data = array(
                'form_title' => $title,
                'fields' => $fields,
                'settings' => $settings,
                'status' => 'active',
                'updated_at' => current_time('mysql')
            );

            // Filter database data before saving
            $data = apply_filters('mavlers_cf_db_data_before_save', $data, $form_id);

            $format = array(
                '%s', // form_title
                '%s', // fields
                '%s', // settings
                '%s', // status
                '%s'  // updated_at
            );

            // Action hook before saving form
            do_action('mavlers_cf_before_save_form', $data, $form_id);

            if ($form_id) {
                // Action hook before updating form
                do_action('mavlers_cf_before_update_form', $data, $form_id);
                
                $result = $wpdb->update(
                    $table_name,
                    $data,
                    array('id' => $form_id),
                    $format,
                    array('%d')
                );

                if ($result === false) {
                    throw new Exception('Failed to update form: ' . $wpdb->last_error);
                }

                // Action hook after updating form
                do_action('mavlers_cf_after_update_form', $form_id, $data);
            } else {
                $data['created_at'] = current_time('mysql');
                $format[] = '%s'; // created_at

                // Action hook before creating form
                do_action('mavlers_cf_before_create_form', $data);
                
                $result = $wpdb->insert($table_name, $data, $format);

                if ($result === false) {
                    throw new Exception('Failed to create form: ' . $wpdb->last_error);
                }

                $form_id = $wpdb->insert_id;

                // Action hook after creating form
                do_action('mavlers_cf_after_create_form', $form_id, $data);
            }

            // Action hook after saving form
            do_action('mavlers_cf_after_save_form', $form_id, $data);

            $response_data = array(
                'message' => 'Form saved successfully',
                'form_id' => $form_id
            );

            // Filter response data
            $response_data = apply_filters('mavlers_cf_save_form_response', $response_data, $form_id, $data);

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            $error_message = apply_filters('mavlers_cf_save_form_error', $e->getMessage(), $e);
            wp_send_json_error($error_message);
        }
    }

    public function ajax_delete_form() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error('Invalid form ID');
        }

        // Filter form ID before deletion
        $form_id = apply_filters('mavlers_cf_form_id_before_delete', $form_id);

        // Action hook before deleting form
        do_action('mavlers_cf_before_delete_form', $form_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_cf_forms';
        
        // Get form data before deletion for hooks
        $form_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $form_id));
        
        $result = $wpdb->delete($table_name, array('id' => $form_id));

        if ($result === false) {
            $error_message = apply_filters('mavlers_cf_delete_form_error', 'Failed to delete form', $form_id);
            wp_send_json_error($error_message);
        }

        // Action hook after deleting form
        do_action('mavlers_cf_after_delete_form', $form_id, $form_data);

        $response_data = array(
            'message' => 'Form deleted successfully',
            'form_id' => $form_id
        );

        // Filter response data
        $response_data = apply_filters('mavlers_cf_delete_form_response', $response_data, $form_id);

        wp_send_json_success($response_data);
    }

    public function ajax_duplicate_form() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error('Invalid form ID');
        }

        // Filter form ID before duplication
        $form_id = apply_filters('mavlers_cf_form_id_before_duplicate', $form_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_cf_forms';
        
        $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $form_id));
        
        if (!$form) {
            wp_send_json_error('Form not found');
        }

        // Filter original form data
        $form = apply_filters('mavlers_cf_form_before_duplicate', $form);

        $data = array(
            'form_title' => $form->form_title . ' (Copy)',
            'fields' => $form->fields,
            'settings' => $form->settings,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        // Filter duplicate data
        $data = apply_filters('mavlers_cf_duplicate_form_data', $data, $form);

        // Action hook before duplicating form
        do_action('mavlers_cf_before_duplicate_form', $data, $form);

        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            $error_message = apply_filters('mavlers_cf_duplicate_form_error', 'Failed to duplicate form: ' . $wpdb->last_error, $form_id);
            wp_send_json_error($error_message);
        }

        $new_form_id = $wpdb->insert_id;

        // Action hook after duplicating form
        do_action('mavlers_cf_after_duplicate_form', $new_form_id, $data, $form);

        $response_data = array(
            'message' => 'Form duplicated successfully',
            'form_id' => $new_form_id,
            'redirect_url' => admin_url('admin.php?page=mavlers-contact-forms&action=edit&id=' . $new_form_id)
        );

        // Filter response data
        $response_data = apply_filters('mavlers_cf_duplicate_form_response', $response_data, $new_form_id, $form);

        wp_send_json_success($response_data);
    }

    public function ajax_get_form() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error('Invalid form ID');
        }

        $form = $this->get_form($form_id);

        if (!$form) {
            wp_send_json_error('Form not found');
        }

        wp_send_json_success($form);
    }

    /**
     * AJAX handler for form preview
     */
    public function ajax_preview_form() {
        // Verify nonce
        if (!check_ajax_referer('mavlers_cf_nonce', 'nonce', false)) {
            wp_die('Invalid security token');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;

        if (!$form_id) {
            wp_die('Invalid form ID');
        }

        // Redirect to preview page
        $preview_url = admin_url('admin.php?page=mavlers-contact-forms&action=preview&id=' . $form_id);
        wp_redirect($preview_url);
        exit;
    }

    /**
     * Render form preview page
     */
    public function render_form_preview($form_id) {
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_die(__('Form not found', 'mavlers-contact-forms'));
        }

        // Initialize form renderer
        $form_renderer = new Mavlers_CF_Form_Renderer();
        $form_html = $form_renderer->render_form(array('id' => $form_id));

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($form->form_title); ?> - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    background: #f9f9f9;
                }
                .preview-header {
                    background: #fff;
                    padding: 20px;
                    margin-bottom: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .preview-header h1 {
                    margin: 0 0 10px 0;
                    color: #2271b1;
                }
                .preview-header p {
                    margin: 0;
                    color: #666;
                }
                .form-preview-container {
                    background: #fff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .preview-actions {
                    text-align: center;
                    margin-top: 20px;
                    padding: 20px;
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .preview-actions .button {
                    margin: 0 10px;
                }
                
                /* Form styling for preview */
                .mavlers-cf-form-wrapper {
                    max-width: 100%;
                }
                .mavlers-cf-form {
                    display: block;
                }
                .mavlers-cf-field {
                    margin-bottom: 20px;
                }
                .mavlers-cf-field label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 600;
                    color: #333;
                }
                .mavlers-cf-field input[type="text"],
                .mavlers-cf-field input[type="email"],
                .mavlers-cf-field input[type="number"],
                .mavlers-cf-field input[type="date"],
                .mavlers-cf-field textarea,
                .mavlers-cf-field select {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                    box-sizing: border-box;
                }
                .mavlers-cf-field input[type="file"] {
                    padding: 10px 0;
                }
                .mavlers-cf-field .mavlers-cf-radio-label,
                .mavlers-cf-field .mavlers-cf-checkbox-label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: normal;
                }
                .mavlers-cf-field .mavlers-cf-radio-label input,
                .mavlers-cf-field .mavlers-cf-checkbox-label input {
                    margin-right: 8px;
                }
                .mavlers-cf-submit-wrapper {
                    margin-top: 20px;
                }
                .mavlers-cf-submit {
                    background: #2271b1;
                    color: #fff;
                    padding: 12px 24px;
                    border: none;
                    border-radius: 4px;
                    font-size: 16px;
                    cursor: pointer;
                    transition: background-color 0.3s ease;
                }
                .mavlers-cf-submit:hover {
                    background: #135e96;
                }
                .mavlers-cf-message {
                    margin-top: 15px;
                    padding: 10px;
                    border-radius: 4px;
                    display: none;
                }
                .mavlers-cf-message.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                .mavlers-cf-message.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                .required {
                    color: #dc3545;
                }
                .mavlers-cf-field .description {
                    font-size: 12px;
                    color: #666;
                    margin-top: 5px;
                }
            </style>
        </head>
        <body>
            <div class="preview-header">
                <h1><?php echo esc_html($form->form_title); ?></h1>
                <p><?php _e('Form Preview', 'mavlers-contact-forms'); ?></p>
            </div>

            <div class="form-preview-container">
                <?php echo $form_html; ?>
            </div>

            <div class="preview-actions">
                <a href="<?php echo admin_url('admin.php?page=mavlers-contact-forms&action=edit&id=' . $form_id); ?>" class="button button-primary">
                    <?php _e('Edit Form', 'mavlers-contact-forms'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=mavlers-contact-forms'); ?>" class="button">
                    <?php _e('Back to Forms', 'mavlers-contact-forms'); ?>
                </a>
                <a href="<?php echo add_query_arg('preview', '1', esc_url($_SERVER['REQUEST_URI'])); ?>" class="button">
                    <?php _e('Debug Preview', 'mavlers-contact-forms'); ?>
                </a>
                <button type="button" class="button" onclick="window.close();">
                    <?php _e('Close Preview', 'mavlers-contact-forms'); ?>
                </button>
            </div>

            <script>
            // Ensure jQuery is loaded
            if (typeof jQuery === 'undefined') {
                document.write('<script src="<?php echo includes_url('js/jquery/jquery.min.js'); ?>"><\/script>');
            }
            
            // Load frontend scripts for form functionality
            jQuery(document).ready(function($) {
                // Initialize form submission handling
                $('.mavlers-cf-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var $form = $(this);
                    var $message = $form.find('.mavlers-cf-message');
                    var $submit = $form.find('.mavlers-cf-submit');
                    
                    // Show loading state
                    $submit.prop('disabled', true).text('<?php _e('Submitting...', 'mavlers-contact-forms'); ?>');
                    $message.removeClass('success error').hide();
                    
                    // Collect form data
                    var formData = new FormData($form[0]);
                    formData.append('action', 'mavlers_cf_submit');
                    formData.append('nonce', '<?php echo wp_create_nonce('mavlers_cf_submit'); ?>');
                    
                    // Submit form
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                $message.addClass('success').html(response.data.message).show();
                                if (response.data.redirect) {
                                    setTimeout(function() {
                                        window.location.href = response.data.redirect;
                                    }, 2000);
                                }
                            } else {
                                $message.addClass('error').html(response.data.message).show();
                            }
                        },
                        error: function() {
                            $message.addClass('error').html('<?php _e('An error occurred. Please try again.', 'mavlers-contact-forms'); ?>').show();
                        },
                        complete: function() {
                            $submit.prop('disabled', false).text('<?php echo esc_js($form->submit_text ?? __('Submit', 'mavlers-contact-forms')); ?>');
                        }
                    });
                });
            });
            </script>

            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * AJAX handler for testing email functionality
     */
    public function ajax_test_email() {
        // Verify nonce
        if (!check_ajax_referer('mavlers_cf_nonce', 'nonce', false)) {
            wp_die('Invalid security token');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        // Get submission handler instance for debugging
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mavlers-cf-submission-handler.php';
        $submission_handler = new Mavlers_CF_Submission_Handler();
        
        // Get debug information
        $debug_info = $submission_handler->get_email_debug_info();
        
        // Test email functionality
        $test_result = $submission_handler->test_email_functionality();
        
        if ($test_result) {
            $response = array(
                'success' => true,
                'message' => 'Test email sent successfully to ' . $debug_info['admin_email'],
                'debug_info' => $debug_info
            );
            wp_send_json_success($response);
        } else {
            $response = array(
                'success' => false,
                'message' => 'Failed to send test email. Please check your WordPress email configuration.',
                'debug_info' => $debug_info,
                'suggestions' => array(
                    'Check if your hosting provider allows outgoing emails',
                    'Consider installing an SMTP plugin like WP Mail SMTP',
                    'Verify that the admin email address is correct',
                    'Check your server\'s error logs for more details'
                )
            );
            wp_send_json_error($response);
        }
    }

    /**
     * AJAX handler for testing reCAPTCHA functionality
     */
    public function ajax_test_recaptcha() {
        // Verify nonce
        if (!check_ajax_referer('mavlers_cf_test', 'nonce', false)) {
            wp_send_json_error('Invalid security token');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Get reCAPTCHA response
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        
        if (empty($recaptcha_response)) {
            wp_send_json_error('No reCAPTCHA response received');
        }

        // Get reCAPTCHA settings
        $recaptcha_settings = get_option('mavlers_cf_recaptcha_settings', array());
        $secret_key = isset($recaptcha_settings['secret_key']) ? $recaptcha_settings['secret_key'] : '';
        
        if (empty($secret_key)) {
            wp_send_json_error('reCAPTCHA secret key not configured');
        }

        // Test reCAPTCHA verification
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mavlers-cf-submission-handler.php';
        $submission_handler = new Mavlers_CF_Submission_Handler();
        
        // Use reflection to access the private method
        $reflection = new ReflectionClass($submission_handler);
        $verify_method = $reflection->getMethod('verify_captcha');
        $verify_method->setAccessible(true);
        
        $verification_result = $verify_method->invoke($submission_handler, $recaptcha_response, $secret_key);
        
        if ($verification_result) {
            wp_send_json_success(array(
                'message' => 'reCAPTCHA verification successful!',
                'response_length' => strlen($recaptcha_response)
            ));
        } else {
            wp_send_json_error('reCAPTCHA verification failed. Please check your configuration.');
        }
    }
} 