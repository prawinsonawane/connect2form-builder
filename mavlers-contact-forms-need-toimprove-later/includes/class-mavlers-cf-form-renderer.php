<?php
if (!defined('WPINC')) {
    die;
}

class Mavlers_CF_Form_Renderer {
    public function __construct() {
        add_shortcode('mavlers_contact_form', array($this, 'render_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($form_id = null) {
        // Enqueue frontend styles
        wp_enqueue_style(
            'mavlers-cf-frontend',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            MAVLERS_CF_VERSION
        );

        // Enqueue frontend scripts
        wp_enqueue_script(
            'mavlers-cf-frontend',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/frontend.js',
            array('jquery'),
            MAVLERS_CF_VERSION,
            true
        );

        // Localize script with form data
        $recaptcha_settings = get_option('mavlers_cf_recaptcha_settings', array());
        wp_localize_script('mavlers-cf-frontend', 'mavlersCF', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_cf_submit'),
            'formId' => $form_id,
            'recaptchaType' => isset($recaptcha_settings['recaptcha_type']) ? $recaptcha_settings['recaptcha_type'] : 'v2_checkbox',
            'recaptchaSiteKey' => isset($recaptcha_settings['site_key']) ? $recaptcha_settings['site_key'] : '',
            'messages' => array(
                'required' => __('This field is required.', 'mavlers-contact-forms'),
                'email' => __('Please enter a valid email address.', 'mavlers-contact-forms'),
                'success' => __('Form submitted successfully!', 'mavlers-contact-forms'),
                'error' => __('An error occurred. Please try again.', 'mavlers-contact-forms')
            )
        ));
    }

    public function render_form($form_id) {
        if (is_array($form_id)) {
            $form_id = $form_id['id'];
        }

        // Filter form ID before processing
        $form_id = apply_filters('mavlers_cf_form_id', $form_id);

        // Enqueue scripts with form ID
        $this->enqueue_scripts($form_id);

        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d AND status = 'active'",
            $form_id
        ));

        if (!$form) {
            // Try to get form without status check for preview
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
                $form_id
            ));
        }

        if (!$form) {
            return '<p>' . __('Form not found.', 'mavlers-contact-forms') . '</p>';
        }

        // Filter form object
        $form = apply_filters('mavlers_cf_form_object', $form, $form_id);

        $form_fields = json_decode($form->fields, true);
        $form_settings = json_decode($form->settings, true);

        // Filter form fields and settings
        $form_fields = apply_filters('mavlers_cf_form_fields', $form_fields, $form);
        $form_settings = apply_filters('mavlers_cf_form_settings', $form_settings, $form);

        // Action hook before form rendering
        do_action('mavlers_cf_before_form_render', $form, $form_fields, $form_settings);

        // Start building form HTML
        $form_html = '<div class="mavlers-cf-form-wrapper">';
        
        // Filter form wrapper classes
        $wrapper_classes = apply_filters('mavlers_cf_form_wrapper_classes', 'mavlers-cf-form-wrapper', $form);
        $form_html = '<div class="' . esc_attr($wrapper_classes) . '">';

        // Add form title if enabled
        if (!empty($form_settings['show_form_title'])) {
            $form_title = apply_filters('mavlers_cf_form_title', $form->form_title, $form);
            $form_html .= '<h3 class="mavlers-cf-form-title">' . esc_html($form_title) . '</h3>';
        }

        // Add form description if exists
        if (!empty($form_settings['form_description'])) {
            $form_description = apply_filters('mavlers_cf_form_description', $form_settings['form_description'], $form);
            $form_html .= '<div class="mavlers-cf-form-description">' . wp_kses_post($form_description) . '</div>';
        }

        // Filter form attributes
        $form_attributes = apply_filters('mavlers_cf_form_attributes', array(
            'method' => 'post',
            'class' => 'mavlers-cf-form',
            'enctype' => 'multipart/form-data'
        ), $form);

        $form_html .= '<form';
        foreach ($form_attributes as $attr => $value) {
            $form_html .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
        }
        $form_html .= '>';

        $form_html .= '<input type="hidden" name="form_id" value="' . esc_attr($form->id) . '">';
        $form_html .= '<input type="hidden" name="action" value="mavlers_cf_submit">';
        $form_html .= wp_nonce_field('mavlers_cf_submit', 'nonce', true, false);
        
        // Add honeypot protection
        $form_html .= '<div class="mavlers-cf-honeypot" style="position: absolute; left: -9999px; top: -9999px;">';
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
        do_action('mavlers_cf_before_form_fields', $form, $form_fields);

        if (!empty($form_fields) && is_array($form_fields)) {
            foreach ($form_fields as $field) {
                // Filter individual field before rendering
                $field = apply_filters('mavlers_cf_field_before_render', $field, $form);
                
                // Skip certain field types in preview mode
                if (isset($_GET['preview']) && current_user_can('manage_options')) {
                    // Show debug info for preview
                    $form_html .= '<!-- Field Debug: ' . esc_html(print_r($field, true)) . ' -->';
                }

                $field_html = $this->render_field($field);
                
                // Filter field HTML after rendering
                $field_html = apply_filters('mavlers_cf_field_after_render', $field_html, $field, $form);
                
                $form_html .= $field_html;
            }
        } else {
            $form_html .= '<p>' . __('No form fields found.', 'mavlers-contact-forms') . '</p>';
        }

        // Action hook after form fields
        do_action('mavlers_cf_after_form_fields', $form, $form_fields);

        // Add submit button
        $submit_text = !empty($form_settings['submit_text']) ? $form_settings['submit_text'] : __('Submit', 'mavlers-contact-forms');
        $submit_text = apply_filters('mavlers_cf_submit_button_text', $submit_text, $form);
        
        $submit_classes = apply_filters('mavlers_cf_submit_button_classes', 'mavlers-cf-submit', $form);
        
        $form_html .= '<div class="mavlers-cf-submit-wrapper">';
        $form_html .= '<button type="submit" class="' . esc_attr($submit_classes) . '">' . esc_html($submit_text) . '</button>';
        $form_html .= '</div>';

        // Add message container
        $form_html .= '<div class="mavlers-cf-message" style="display: none;"></div>';

        $form_html .= '</form>';
        $form_html .= '</div>';

        // Filter final form HTML
        $form_html = apply_filters('mavlers_cf_form_html', $form_html, $form, $form_fields, $form_settings);

        // Action hook after form rendering
        do_action('mavlers_cf_after_form_render', $form_html, $form, $form_fields, $form_settings);

        return $form_html;
    }

    private function render_field($field) {
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
        $field = apply_filters('mavlers_cf_field_data', $field, $field_type);
        
        // Filter field attributes
        $field_attributes = apply_filters('mavlers_cf_field_attributes', array(
            'id' => $field_id,
            'name' => $field_id,
            'class' => 'mavlers-cf-field-input',
            'required' => $required
        ), $field);

        // Get global reCAPTCHA settings
        if ($field_type === 'captcha') {
            $recaptcha_settings = get_option('mavlers_cf_recaptcha_settings', array());
            $site_key = isset($recaptcha_settings['site_key']) ? $recaptcha_settings['site_key'] : '';
            
            // Filter reCAPTCHA settings
            $recaptcha_settings = apply_filters('mavlers_cf_recaptcha_settings', $recaptcha_settings, $field);
            $site_key = apply_filters('mavlers_cf_recaptcha_site_key', $site_key, $field);
        }

        // Action hook before field rendering
        do_action('mavlers_cf_before_field_render', $field, $field_type);

        ob_start();
        ?>
        <div class="mavlers-cf-field mavlers-cf-field-<?php echo esc_attr($field_type); ?> <?php if (!empty($field['css_class'])): echo esc_attr($field['css_class']); endif; ?> <?php echo $field_required ? ' required' : ''; ?>">
            <?php if ($field_type !== 'hidden' && $field_type !== 'html' && $field_type !== 'submit'): ?>
                <label for="<?php echo esc_attr($field_id); ?>">
                    <?php echo esc_html($field_label); ?>
                    <?php if ($field_required): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>

            <?php
            // Filter field content before rendering
            $field_content = apply_filters('mavlers_cf_field_content_before', '', $field, $field_type);
            if (!empty($field_content)) {
                echo $field_content;
            }

            switch ($field_type) {
                case 'text':
                case 'email':
                case 'number':
                case 'date':
                    $input_type = $field_type;
                    $input_type = apply_filters('mavlers_cf_input_type', $input_type, $field);
                    ?>
                    <input type="<?php echo esc_attr($input_type); ?>" 
                           id="<?php echo esc_attr($field_id); ?>"
                           name="<?php echo esc_attr($field_id); ?>"
                           value="<?php echo esc_attr($field['default_value'] ?? ''); ?>"
                           placeholder="<?php echo esc_attr($placeholder); ?>"
                           <?php echo $required; ?>
                           <?php if (!empty($field['maxlength'])): ?>maxlength="<?php echo esc_attr($field['maxlength']); ?>"<?php endif; ?>
                           >
                    <?php
                    break;

                case 'textarea':
                    ?>
                    <textarea id="<?php echo esc_attr($field_id); ?>"
                              name="<?php echo esc_attr($field_id); ?>"
                              placeholder="<?php echo esc_attr($placeholder); ?>"
                              <?php echo $required; ?>
                              <?php if (!empty($field['maxlength'])): ?>maxlength="<?php echo esc_attr($field['maxlength']); ?>"<?php endif; ?>
                              <?php if (!empty($field['css_class'])): ?>class="<?php echo esc_attr($field['css_class']); ?>"<?php endif; ?>
                              rows="4"><?php echo esc_textarea($field['default_value'] ?? ''); ?></textarea>
                    <?php
                    break;

                case 'select':
                    ?>
                    <select id="<?php echo esc_attr($field_id); ?>"
                            name="<?php echo esc_attr($field_id); ?>"
                            <?php echo $required; ?>
                            <?php if (!empty($field['css_class'])): ?>class="<?php echo esc_attr($field['css_class']); ?>"<?php endif; ?>>
                        <option value=""><?php echo esc_html($placeholder ?: __('Select an option', 'mavlers-contact-forms')); ?></option>
                        <?php
                        if (!empty($field['options'])) {
                            foreach ($field['options'] as $option) {
                                $option_value = is_array($option) ? ($option['value'] ?? '') : $option;
                                $option_label = is_array($option) ? ($option['label'] ?? $option_value) : $option;
                                $selected = ($field['default_value'] ?? '') === $option_value ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr($option_value); ?>" <?php echo $selected; ?>>
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
                            <label class="mavlers-cf-radio-label">
                                <input type="radio" 
                                       name="<?php echo esc_attr($field_id); ?>"
                                       value="<?php echo esc_attr($option_value); ?>"
                                       <?php echo $checked; ?>
                                       <?php echo $required; ?>>
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
                            <label class="mavlers-cf-checkbox-label">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr($field_id); ?>[]"
                                       value="<?php echo esc_attr($option_value); ?>"
                                       <?php echo $checked; ?>
                                       <?php echo $required; ?>>
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
                           <?php echo $required; ?>
                           <?php if (!empty($field['css_class'])): ?>class="<?php echo esc_attr($field['css_class']); ?>"<?php endif; ?>>
                    <?php
                    break;

                case 'captcha':
                    if (!empty($site_key)) {
                        // Get reCAPTCHA type from global settings
                        $recaptcha_settings = get_option('mavlers_cf_recaptcha_settings', array());
                        $recaptcha_type = isset($recaptcha_settings['recaptcha_type']) ? $recaptcha_settings['recaptcha_type'] : 'v2_checkbox';
                        
                        if ($recaptcha_type === 'v3') {
                            // reCAPTCHA v3 - invisible
                            ?>
                            <div class="g-recaptcha" 
                                 data-sitekey="<?php echo esc_attr($site_key); ?>"
                                 data-size="invisible">
                            </div>
                            <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr($site_key); ?>"></script>
                            <script>
                            // reCAPTCHA v3 implementation
                            window.addEventListener('load', function() {
                                if (typeof grecaptcha !== 'undefined') {
                                    console.log('Mavlers CF: reCAPTCHA v3 loaded successfully');
                                } else {
                                    console.error('Mavlers CF: reCAPTCHA v3 failed to load');
                                }
                            });
                            </script>
                            <?php
                        } else {
                            // reCAPTCHA v2 - checkbox or invisible
                            ?>
                            <div class="g-recaptcha" 
                                 data-sitekey="<?php echo esc_attr($site_key); ?>"
                                 data-theme="<?php echo esc_attr($field['captcha_theme'] ?? 'light'); ?>"
                                 data-size="<?php echo esc_attr($field['captcha_size'] ?? 'normal'); ?>">
                            </div>
                            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                            <script>
                            // Debug reCAPTCHA loading
                            window.addEventListener('load', function() {
                                if (typeof grecaptcha === 'undefined') {
                                    console.error('Mavlers CF: reCAPTCHA v2 failed to load');
                                } else {
                                    console.log('Mavlers CF: reCAPTCHA v2 loaded successfully');
                                }
                            });
                            </script>
                            <?php
                        }
                    } else {
                        echo '<p>' . __('reCAPTCHA not configured. Please configure reCAPTCHA settings.', 'mavlers-contact-forms') . '</p>';
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
                    $custom_field_html = apply_filters('mavlers_cf_custom_field_type', '', $field, $field_type);
                    if (!empty($custom_field_html)) {
                        echo $custom_field_html;
                    } else {
                        echo '<p>' . sprintf(__('Unknown field type: %s', 'mavlers-contact-forms'), esc_html($field_type)) . '</p>';
                    }
                    break;
            }

            // Filter field content after rendering
            $field_content = apply_filters('mavlers_cf_field_content_after', '', $field, $field_type);
            if (!empty($field_content)) {
                echo $field_content;
            }
            ?>

            <?php if (!empty($field['description'])): ?>
                <p class="description"><?php echo esc_html($field['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php
        $field_html = ob_get_clean();

        // Filter final field HTML
        $field_html = apply_filters('mavlers_cf_field_html', $field_html, $field, $field_type);

        // Action hook after field rendering
        do_action('mavlers_cf_after_field_render', $field_html, $field, $field_type);

        return $field_html;
    }
} 