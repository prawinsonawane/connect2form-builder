<?php
/**
 * Connect2Form Form Renderer Class
 *
 * Handles frontend form rendering and shortcode functionality
 *
 * @package Connect2Form
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Connect2Form_Form_Renderer {
    public function __construct() {
        // Register both shortcode variations for compatibility
        add_shortcode('connect2form', array($this, 'render_form'));
        add_shortcode('connect2form-builder', array($this, 'render_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($form_id = null) {
        // Enqueue frontend styles
        wp_enqueue_style(
            'connect2form-frontend',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            CONNECT2FORM_VERSION
        );

        // Register and enqueue datepicker
        wp_register_script(
            'connect2form-datepicker',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/datepicker.min.js',
            array('jquery'),
            CONNECT2FORM_VERSION,
            true
        );
        wp_enqueue_script('connect2form-datepicker');

        // Register and enqueue frontend scripts
        wp_register_script(
            'connect2form-frontend',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/frontend.js',
            array('jquery', 'connect2form-datepicker'),
            CONNECT2FORM_VERSION,
            true
        );
        wp_enqueue_script('connect2form-frontend');

        // Localize script with form data
        $recaptcha_settings = get_option('connect2form_recaptcha_settings', array());
        wp_localize_script('connect2form-frontend', 'connect2formCF', array(
            'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('connect2form_submit'),
            'formId' => $form_id,
            'recaptchaType' => isset($recaptcha_settings['recaptcha_type']) ? $recaptcha_settings['recaptcha_type'] : 'v2_checkbox',
            'recaptchaSiteKey' => isset($recaptcha_settings['site_key']) ? $recaptcha_settings['site_key'] : '',
            'messages' => array(
                'required' => __('This field is required.', 'connect2form-builder'),
                'email' => __('Please enter a valid email address.', 'connect2form-builder'),
                'success' => __('Form submitted successfully!', 'connect2form-builder'),
                'error' => __('An error occurred. Please try again.', 'connect2form-builder')
            )
        ));
    }

    public function render_form($form_id) {
        if (is_array($form_id)) {
            $form_id = $form_id['id'];
        }

        // Filter form ID before processing
        $form_id = apply_filters('connect2form_form_id', $form_id);

        // Enqueue scripts with form ID
        $this->enqueue_scripts($form_id);

        // Use service class instead of direct database call
        $form = null;
        if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $form = $service_manager->forms()->get_active_form($form_id);
            
            if (!$form) {
                // Try to get form without status check for preview
                $form = $service_manager->forms()->get_form($form_id);
            }
        } else {
            // Fallback to direct database call if service not available
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback active form query for rendering; service layer preferred but this is a fallback
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}connect2form_forms WHERE id = %d AND status = 'active'",
                $form_id
            ));

            if (!$form) {
                // Try to get form without status check for preview
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form query for preview; service layer preferred but this is a fallback
                $form = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
                    $form_id
                ));
            }
            
            if ($form) {
                // Decode JSON for direct database calls - only if they are strings
                $form->fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
                $form->settings = is_string($form->settings) ? json_decode($form->settings, true) : (is_array($form->settings) ? $form->settings : array());
            }
        }

        if (!$form) {
            // Add debug information for administrators
            $debug_info = '';
            if (current_user_can('manage_options')) {
                $debug_info = sprintf(
                    ' (Form ID: %d, Service Manager: %s)', 
                    $form_id,
                    class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager') ? 'Available' : 'Not Available'
                );
            }
            return '<div class="connect2form-error"><p>' . __('Form not found.', 'connect2form-builder') . $debug_info . '</p></div>';
        }

        // Filter form object
        $form = apply_filters('connect2form_form_object', $form, $form_id);

        $form_fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
        $form_settings = is_string($form->settings) ? json_decode($form->settings, true) : (is_array($form->settings) ? $form->settings : array());

        // Filter form fields and settings
        $form_fields = apply_filters('connect2form_form_fields', $form_fields, $form);
        $form_settings = apply_filters('connect2form_form_settings', $form_settings, $form);

        // Action hook before form rendering
        do_action('connect2form_before_form_render', $form, $form_fields, $form_settings);

        // Start building form HTML
        $form_html = '<div class="connect2form-form-wrapper">';
        
        // Check if any fields have multi-column layout
        $has_multi_column = false;
        if (!empty($form_fields) && is_array($form_fields)) {
            foreach ($form_fields as $field) {
                if (!empty($field['layout']) && ($field['layout'] === 'two-column' || $field['layout'] === 'three-column')) {
                    $has_multi_column = true;
                    break;
                }
            }
        }
        
        // Filter form wrapper classes
        $wrapper_classes = apply_filters('connect2form_form_wrapper_classes', 'connect2form-form-wrapper', $form);
        if ($has_multi_column) {
            $wrapper_classes .= ' multi-column';
        }
        $form_html = '<div class="' . esc_attr($wrapper_classes) . '">';

        // Add form title if enabled
        if (!empty($form_settings['show_form_title'])) {
            $form_title = apply_filters('connect2form_form_title', $form->form_title, $form);
            $form_html .= '<h3 class="connect2form-form-title">' . esc_html($form_title) . '</h3>';
        }

        // Add form description if exists
        if (!empty($form_settings['form_description'])) {
            $form_description = apply_filters('connect2form_form_description', $form_settings['form_description'], $form);
            $form_html .= '<div class="connect2form-form-description">' . wp_kses_post($form_description) . '</div>';
        }

        // Filter form attributes
        $form_attributes = apply_filters('connect2form_form_attributes', array(
            'method' => 'post',
            'class' => 'connect2form-form',
            'enctype' => 'multipart/form-data',
            'novalidate' => 'novalidate'
        ), $form);

        $form_html .= '<form';
        foreach ($form_attributes as $attr => $value) {
            $form_html .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
        }
        $form_html .= '>';

        $form_html .= '<input type="hidden" name="form_id" value="' . esc_attr($form->id) . '">';
        $form_html .= '<input type="hidden" name="action" value="connect2form_submit">';
        $form_html .= wp_nonce_field('connect2form_submit', 'nonce', true, false);
        
        // Add honeypot protection
        $form_html .= '<div class="connect2form-honeypot">';
        $form_html .= '<input type="text" name="website" value="" tabindex="-1" autocomplete="off">';
        $form_html .= '</div>';
        
        // Add timestamp for additional protection
        $form_html .= '<input type="hidden" name="timestamp" value="' . esc_attr(time()) . '">';

        // Add hidden field for reCAPTCHA response if captcha field exists
        $has_captcha = false;
        if (!empty($form_fields) && is_array($form_fields)) {
            foreach ($form_fields as $field) {
                if ($field['type'] === 'captcha') {
                    $has_captcha = true;
                    break;
                }
            }
        }
        
        if ($has_captcha) {
            $form_html .= '<input type="hidden" name="g-recaptcha-response" value="">';
        }

        // Action hook before form fields
        do_action('connect2form_before_form_fields', $form, $form_fields);

        if (!empty($form_fields) && is_array($form_fields)) {
            foreach ($form_fields as $field) {
                // Filter individual field before rendering
                $field = apply_filters('connect2form_field_before_render', $field, $form);
                
                // Skip certain field types in preview mode
                if (isset($_GET['preview']) && current_user_can('manage_options') && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['preview'])), 'connect2form_preview')) {
                    
                }

                $field_html = $this->render_field($field, $form);
                
                // Filter field HTML after rendering
                $field_html = apply_filters('connect2form_field_after_render', $field_html, $field, $form);
                
                $form_html .= $field_html;
            }
        } else {
            // Add debug information for administrators
            $debug_fields = '';
            if (current_user_can('manage_options')) {
                $debug_fields = sprintf(
                    ' (Fields: %s, Count: %d)', 
                    is_array($form_fields) ? 'Array' : gettype($form_fields),
                    is_array($form_fields) ? count($form_fields) : 0
                );
            }
            $form_html .= '<p>' . __('No form fields found.', 'connect2form-builder') . $debug_fields . '</p>';
        }

        // Action hook after form fields
        do_action('connect2form_after_form_fields', $form, $form_fields);

        // Add submit button
        $submit_text = !empty($form_settings['submit_text']) ? $form_settings['submit_text'] : __('Submit', 'connect2form-builder');
        $submit_text = apply_filters('connect2form_submit_button_text', $submit_text, $form);
        
        $submit_classes = apply_filters('connect2form_submit_button_classes', 'connect2form-submit', $form);
        
        $form_html .= '<div class="connect2form-submit-wrapper">';
        $form_html .= '<button type="submit" class="' . esc_attr($submit_classes) . '">' . esc_html($submit_text) . '</button>';
        $form_html .= '</div>';

        // Add message container
        $form_html .= '<div class="connect2form-message" style="display: none;"></div>';

        $form_html .= '</form>';
        $form_html .= '</div>';

        // Filter final form HTML
        $form_html = apply_filters('connect2form_form_html', $form_html, $form, $form_fields, $form_settings);

        // Action hook after form rendering
        do_action('connect2form_after_form_render', $form_html, $form, $form_fields, $form_settings);

        return $form_html;
    }

    private function render_field($field, $form = null) {
        // Handle both old and new field property names
        $field_id = $field['id'] ?? '';
        $field_type = $field['type'] ?? '';
        $field_label = $field['label'] ?? '';
        $field_required = !empty($field['required']); // Store original required status
        $required = $field_required ? 'required' : '';
        $placeholder = !empty($field['placeholder']) ? $field['placeholder'] : '';

        // Remove required attribute for captcha fields (but keep the original status for container class)
        if ($field_type === 'captcha') {
            $required = '';
        }

        // Filter field data before rendering
        $field = apply_filters('connect2form_field_data', $field, $field_type);
        
        // Filter field attributes
        $field_attributes = apply_filters('connect2form_field_attributes', array(
            'id' => $field_id,
            'name' => $field_id,
            'class' => 'connect2form-field-input',
            'required' => $required
        ), $field, $form);

        // Get global reCAPTCHA settings
        if ($field_type === 'captcha') {
            $recaptcha_settings = get_option('connect2form_recaptcha_settings', array());
            $site_key = isset($recaptcha_settings['site_key']) ? $recaptcha_settings['site_key'] : '';
            
            // Filter reCAPTCHA settings
            $recaptcha_settings = apply_filters('connect2form_recaptcha_settings', $recaptcha_settings, $field);
            $site_key = apply_filters('connect2form_recaptcha_site_key', $site_key, $field);
        }

        // Action hook before field rendering
        do_action('connect2form_before_field_render', $field, $field_type);

        ob_start();
        ?>
        <div class="connect2form-field connect2form-field-<?php echo esc_attr($field_type); ?> <?php if (!empty($field['css_class'])): echo esc_attr($field['css_class']); endif; ?><?php if ($field_required): ?> required<?php endif; ?> <?php if (!empty($field['layout'])): echo 'layout-' . esc_attr($field['layout']); endif; ?>" 
             <?php if (!empty($field['layout'])): echo 'data-layout="' . esc_attr($field['layout']) . '"'; endif; ?>
             <?php if (!empty($field['conditional_enabled']) && !empty($field['conditional_dependent_field'])): ?>
                 data-conditional="true"
                 data-conditional-field="<?php echo esc_attr($field['conditional_dependent_field']); ?>"
                 data-conditional-condition="<?php echo esc_attr($field['conditional_condition']); ?>"
                 <?php if (!empty($field['conditional_value'])): ?>data-conditional-value="<?php echo esc_attr($field['conditional_value']); ?>"<?php endif; ?>
                 style="display: none;"
             <?php endif; ?>>
            <?php if ($field_type !== 'hidden' && $field_type !== 'html' && $field_type !== 'submit' && $field_type !== 'captcha' && $field_type !== 'utm'): ?>
                <label for="<?php echo esc_attr($field_id); ?>">
                    <?php echo esc_html($field_label); ?>
                    <?php if ($field_required): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>

            <?php
            // Filter field content before rendering
            $field_content = apply_filters('connect2form_field_content_before', '', $field, $field_type);
            if (!empty($field_content)) {
                echo wp_kses_post($field_content);
            }

            switch ($field_type) {
                case 'text':
                case 'email':
                case 'number':
                case 'phone':
                    $input_type = $field_type;
                    $input_type = apply_filters('connect2form_input_type', $input_type, $field);
                    ?>
                    <input type="<?php echo esc_attr($input_type); ?>" 
                           id="<?php echo esc_attr($field_id); ?>"
                           name="<?php echo esc_attr($field_id); ?>"
                           value="<?php echo esc_attr($field['default_value'] ?? ''); ?>"
                           placeholder="<?php echo esc_attr($placeholder); ?>"
                           <?php if ($required): ?>required<?php endif; ?>
                           <?php if (!empty($field['maxlength'])): ?>maxlength="<?php echo esc_attr($field['maxlength']); ?>"<?php endif; ?>
                           <?php if ($field_type === 'phone'): ?>pattern="[0-9\+\-\(\)\s]+" title="Please enter a valid phone number (e.g., +1-555-123-4567)"<?php endif; ?>
                           >
                    <?php
                    break;

                case 'date':
                    $input_type = 'text'; // Use text input for DD-MM-YYYY format
                    $input_type = apply_filters('connect2form_input_type', $input_type, $field);
                    $date_placeholder = !empty($placeholder) ? $placeholder : 'DD-MM-YYYY';
                    ?>
                    <input type="<?php echo esc_attr($input_type); ?>" 
                           id="<?php echo esc_attr($field_id); ?>"
                           name="<?php echo esc_attr($field_id); ?>"
                           value="<?php echo esc_attr($field['default_value'] ?? ''); ?>"
                           placeholder="<?php echo esc_attr($date_placeholder); ?>"
                           class="connect2form-datepicker"
                           data-format="dd-mm-yyyy"
                           <?php if ($required): ?>required<?php endif; ?>
                           <?php if (!empty($field['maxlength'])): ?>maxlength="<?php echo esc_attr($field['maxlength']); ?>"<?php endif; ?>
                           pattern="\d{2}-\d{2}-\d{4}" 
                           title="Please enter date in DD-MM-YYYY format"
                           >
                    <?php
                    break;

                case 'textarea':
                    ?>
                    <textarea id="<?php echo esc_attr($field_id); ?>"
                              name="<?php echo esc_attr($field_id); ?>"
                              placeholder="<?php echo esc_attr($placeholder); ?>"
                              <?php if ($required): ?>required<?php endif; ?>
                              <?php if (!empty($field['maxlength'])): ?>maxlength="<?php echo esc_attr($field['maxlength']); ?>"<?php endif; ?>
                              <?php if (!empty($field['css_class'])): ?>class="<?php echo esc_attr($field['css_class']); ?>"<?php endif; ?>
                              rows="4"><?php echo esc_textarea($field['default_value'] ?? ''); ?></textarea>
                    <?php
                    break;

                case 'select':
                    ?>
                    <select id="<?php echo esc_attr($field_id); ?>"
                            name="<?php echo esc_attr($field_id); ?>"
                            <?php if ($required): ?>required<?php endif; ?>
                            <?php if (!empty($field['css_class'])): ?>class="<?php echo esc_attr($field['css_class']); ?>"<?php endif; ?>>
                        <option value=""><?php echo esc_html($placeholder ?: __('Select an option', 'connect2form-builder')); ?></option>
                        <?php
                        if (!empty($field['options'])) {
                            foreach ($field['options'] as $option) {
                                $option_value = is_array($option) ? ($option['value'] ?? '') : $option;
                                $option_label = is_array($option) ? ($option['label'] ?? $option_value) : $option;
                                $selected = ($field['default_value'] ?? '') === $option_value ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr($option_value); ?>" <?php if ($selected): ?>selected<?php endif; ?>>
                                    <?php echo esc_html($option_label); ?>
                                </option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                    <?php
                    break;

                case 'radio':
                    if (!empty($field['options'])) {
                        foreach ($field['options'] as $option) {
                            $option_value = is_array($option) ? ($option['value'] ?? '') : $option;
                            $option_label = is_array($option) ? ($option['label'] ?? $option_value) : $option;
                            $checked = ($field['default_value'] ?? '') === $option_value ? 'checked' : '';
                            ?>
                            <label class="connect2form-radio-label">
                                <input type="radio" 
                                       name="<?php echo esc_attr($field_id); ?>"
                                       value="<?php echo esc_attr($option_value); ?>"
                                       <?php if ($checked): ?>checked<?php endif; ?>
                                       <?php if ($required): ?>required<?php endif; ?>>
                                <span><?php echo esc_html($option_label); ?></span>
                            </label>
                            <?php
                        }
                    }
                    break;

                case 'checkbox':
                    if (!empty($field['options'])) {
                        foreach ($field['options'] as $option) {
                            $option_value = is_array($option) ? ($option['value'] ?? '') : $option;
                            $option_label = is_array($option) ? ($option['label'] ?? $option_value) : $option;
                            $checked = in_array($option_value, (array)($field['default_value'] ?? array())) ? 'checked' : '';
                            ?>
                            <label class="connect2form-checkbox-label">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr($field_id); ?>[]"
                                       value="<?php echo esc_attr($option_value); ?>"
                                       <?php if ($checked): ?>checked<?php endif; ?>
                                       <?php if ($required): ?>required<?php endif; ?>>
                                <span><?php echo esc_html($option_label); ?></span>
                            </label>
                            <?php
                        }
                    }
                    break;

                case 'file':
                    ?>
                    <input type="file" 
                           id="<?php echo esc_attr($field_id); ?>"
                           name="<?php echo esc_attr($field_id); ?>"
                           <?php if ($required): ?>required<?php endif; ?>
                           <?php if (!empty($field['allowed_types'])): ?>accept="<?php echo esc_attr($field['allowed_types']); ?>"<?php endif; ?>
                           <?php if (!empty($field['max_files']) && intval($field['max_files']) > 1): ?>multiple<?php endif; ?>
                           <?php if (!empty($field['css_class'])): ?>class="<?php echo esc_attr($field['css_class']); ?>"<?php endif; ?>
                           <?php if (!empty($field['max_size'])): ?>data-max-size="<?php echo esc_attr($field['max_size']); ?>"<?php endif; ?>
                           <?php if (!empty($field['size_unit'])): ?>data-size-unit="<?php echo esc_attr($field['size_unit']); ?>"<?php endif; ?>
                           <?php if (!empty($field['allowed_types'])): ?>data-allowed-types="<?php echo esc_attr($field['allowed_types']); ?>"<?php endif; ?>>
                    
                    <?php if (!empty($field['max_size']) || !empty($field['allowed_types'])): ?>
                        <small style="color: #666; font-size: 11px; margin-top: 5px; display: block;">
                            <?php 
                            $info_parts = array();
                            if (!empty($field['max_size'])) {
                                $unit = !empty($field['size_unit']) ? $field['size_unit'] : 'MB';
                                $info_parts[] = 'Max: ' . esc_html($field['max_size']) . ' ' . esc_html($unit);
                            }
                            if (!empty($field['allowed_types'])) {
                                $info_parts[] = 'Allowed: ' . esc_html($field['allowed_types']);
                            }
                            echo esc_html(implode(' - ', $info_parts));
                            ?>
                        </small>
                    <?php endif; ?>
                    <?php
                    break;

                case 'utm':
                    // UTM fields are hidden by default and capture URL parameters
                    $utm_params = array();
                    if (!empty($field['utm_source'])) $utm_params[] = 'utm_source';
                    if (!empty($field['utm_medium'])) $utm_params[] = 'utm_medium';
                    if (!empty($field['utm_campaign'])) $utm_params[] = 'utm_campaign';
                    if (!empty($field['utm_term'])) $utm_params[] = 'utm_term';
                    if (!empty($field['utm_content'])) $utm_params[] = 'utm_content';
                    
                    foreach ($utm_params as $param) {
                        // UTM parameters are read-only tracking parameters that don't require nonce verification
                        // These are standard marketing analytics parameters (utm_source, utm_medium, etc.)
                        // passed in URLs for campaign tracking - they pose no security risk
                        $param_value = '';
                        if (isset($_GET[$param])) {
                            $raw_value = wp_unslash($_GET[$param]);
                            // Additional validation for UTM parameters
                            if (is_string($raw_value) && strlen($raw_value) <= 255) {
                                $param_value = sanitize_text_field($raw_value);
                            }
                        }
                        ?>
                        <input type="hidden" 
                               id="<?php echo esc_attr($field_id . '_' . $param); ?>"
                               name="<?php echo esc_attr($field_id . '[' . $param . ']'); ?>"
                               value="<?php echo esc_attr($param_value); ?>"
                               data-utm-param="<?php echo esc_attr($param); ?>">
                        <?php
                    }
                    break;

                case 'captcha':
                    if (!empty($site_key)) {
                        // Get reCAPTCHA type from global settings
                        $recaptcha_settings = get_option('connect2form_recaptcha_settings', array());
                        $recaptcha_type = isset($recaptcha_settings['recaptcha_type']) ? $recaptcha_settings['recaptcha_type'] : 'v2_checkbox';
                        
                        if ($recaptcha_type === 'v3') {
                            // reCAPTCHA v3 - invisible
                            ?>
                            <div class="g-recaptcha" 
                                 data-sitekey="<?php echo esc_attr($site_key); ?>"
                                 data-size="invisible">
                            </div>
                            <?php
                            // Enqueue reCAPTCHA v3 script properly
                            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- reCAPTCHA API version is managed by Google
                            wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key), array(), null, true);
                        } else {
                            // reCAPTCHA v2 - checkbox or invisible
                            ?>
                            <div class="g-recaptcha" 
                                 data-sitekey="<?php echo esc_attr($site_key); ?>"
                                 data-theme="<?php echo esc_attr($field['captcha_theme'] ?? 'light'); ?>"
                                 data-size="<?php echo esc_attr($field['captcha_size'] ?? 'normal'); ?>">
                            </div>
                            <?php
                            // Enqueue reCAPTCHA v2 script properly
                            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- reCAPTCHA API version is managed by Google
                            wp_enqueue_script('google-recaptcha-v2', 'https://www.google.com/recaptcha/api.js', array(), null, true);
                        }
                    } else {
                        echo '<p>' . esc_html__('reCAPTCHA not configured. Please configure reCAPTCHA settings.', 'connect2form-builder') . '</p>';
                    }
                    break;

                case 'hidden':
                    ?>
                    <input type="hidden" 
                           id="<?php echo esc_attr($field_id); ?>"
                           name="<?php echo esc_attr($field_id); ?>"
                           value="<?php echo esc_attr($field['default_value'] ?? ''); ?>">
                    <?php
                    break;

                case 'html':
                    echo wp_kses_post($field['default_value'] ?? '');
                    break;

                default:
                    // Allow custom field types through filter
                    $custom_field_html = apply_filters('connect2form_custom_field_type', '', $field, $field_type);
                    if (!empty($custom_field_html)) {
                        echo wp_kses_post($custom_field_html);
                    } else {
                        /* translators: %s: Field type name */
                        echo '<p>' . esc_html(sprintf(__('Unknown field type: %s', 'connect2form-builder'), $field_type)) . '</p>';
                    }
                    break;
            }

            // Filter field content after rendering
            $field_content = apply_filters('connect2form_field_content_after', '', $field, $field_type);
            if (!empty($field_content)) {
                echo wp_kses_post($field_content);
            }
            ?>

            <?php if (!empty($field['description'])): ?>
                <p class="description"><?php echo esc_html($field['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php
        $field_html = ob_get_clean();

        // Filter final field HTML
        $field_html = apply_filters('connect2form_field_html', $field_html, $field, $field_type);

        // Action hook after field rendering
        do_action('connect2form_after_field_render', $field_html, $field, $field_type);

        return $field_html;
    }
}
