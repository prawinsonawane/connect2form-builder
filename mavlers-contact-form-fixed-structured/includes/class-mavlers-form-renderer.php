<?php
/**
 * Form renderer for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Form_Renderer {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('mavlers_form', array($this, 'render_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts() {
        // Enqueue form styles
        wp_enqueue_style(
            'mavlers-form-styles',
            MAVLERS_FORM_PLUGIN_URL . 'assets/css/form.css',
            array(),
            MAVLERS_FORM_VERSION
        );

        // Enqueue form scripts
        wp_enqueue_script(
            'mavlers-form-scripts',
            MAVLERS_FORM_PLUGIN_URL . 'assets/js/form.js',
            array('jquery'),
            MAVLERS_FORM_VERSION,
            true
        );

        // Check if we need to load reCAPTCHA
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        $forms = $wpdb->get_results("SELECT form_fields FROM {$forms_table} WHERE status = 'active'");
        
        $load_recaptcha = false;
        foreach ($forms as $form) {
            $fields = json_decode($form->form_fields, true);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (isset($field['field_type']) && $field['field_type'] === 'captcha' && 
                        isset($field['captcha_type']) && $field['captcha_type'] === 'recaptcha') {
                        $load_recaptcha = true;
                        break 2;
                    }
                }
            }
        }

        if ($load_recaptcha) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js',
                array(),
                null,
                true
            );
        }

        // Localize script
        wp_localize_script('mavlers-form-scripts', 'mavlersForm', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_form_submission'),
            'messages' => array(
                'required' => __('This field is required', 'mavlers-contact-form'),
                'email' => __('Please enter a valid email address', 'mavlers-contact-form'),
                'success' => __('Form submitted successfully', 'mavlers-contact-form'),
                'error' => __('An error occurred. Please try again.', 'mavlers-contact-form')
            )
        ));
    }

    public function render_form($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);

        $form_id = intval($atts['id']);
        if (!$form_id) {
            return '';
        }

        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_forms';
        
        // Get form
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$forms_table} WHERE id = %d AND status = 'active'",
            $form_id
        ));

        if (!$form) {
            return '';
        }

        // Decode form fields and ensure it's an array
        $form_fields = json_decode($form->form_fields, true);
        if (!is_array($form_fields)) {
            $form_fields = array();
        }
        
        ob_start();
        ?>
        <div class="mavlers-form-wrapper" id="mavlers-form-<?php echo esc_attr($form_id); ?>">
            <form class="mavlers-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
                <?php wp_nonce_field('mavlers_form_submission', 'mavlers_form_nonce'); ?>

                <div class="mavlers-form-fields">
                    <?php
                    $row_open = false;
                    foreach ($form_fields as $index => $field) {
                        // Skip if field is not an array
                        if (!is_array($field)) {
                            continue;
                        }

                        // Check if we need to start a new row
                        if (isset($field['column_layout']) && $field['column_layout'] === 'half' && !$row_open) {
                            echo '<div class="mavlers-form-row">';
                            $row_open = true;
                        }

                        // Get field class based on layout
                        $field_class = 'mavlers-field-wrapper';
                        if (isset($field['column_layout']) && $field['column_layout'] === 'half') {
                            $field_class .= ' mavlers-field-half';
                        } else {
                            $field_class .= ' mavlers-field-full';
                        }

                        // Add custom CSS class if specified
                        if (!empty($field['css_class'])) {
                            $field_class .= ' ' . esc_attr($field['css_class']);
                        }
                        ?>
                        <div class="<?php echo $field_class; ?>">
                            <?php $this->render_field($field); ?>
                        </div>
                        <?php
                        // Check if we need to close the row
                        if (isset($field['column_layout']) && $field['column_layout'] === 'half' && 
                            ($index === count($form_fields) - 1 || 
                             !isset($form_fields[$index + 1]['column_layout']) || 
                             $form_fields[$index + 1]['column_layout'] !== 'half')) {
                            echo '</div>';
                            $row_open = false;
                        }
                    }
                    ?>
                </div>

                <div class="mavlers-form-submit">
                    <button type="submit" class="mavlers-submit-button">
                        <?php echo esc_html__('Submit', 'mavlers-contact-form'); ?>
                    </button>
                </div>

                <div class="mavlers-form-messages"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_field($field) {
        $field_id = 'mavlers-field-' . uniqid();
        $required = !empty($field['required']) ? 'required' : '';
        $field_class = 'mavlers-field mavlers-field-' . esc_attr($field['field_type']);
        
        if (!empty($field['field_size'])) {
            $field_class .= ' mavlers-field-' . esc_attr($field['field_size']);
        }
        ?>
        <div class="<?php echo $field_class; ?>">
            <?php if (!empty($field['field_label']) && $field['field_type'] !== 'submit') : ?>
                <label for="<?php echo $field_id; ?>" class="mavlers-field-label">
                    <?php echo esc_html($field['field_label']); ?>
                    <?php if ($required) : ?>
                        <span class="mavlers-required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>

            <?php if (!empty($field['field_description'])) : ?>
                <div class="mavlers-field-description">
                    <?php echo esc_html($field['field_description']); ?>
                </div>
            <?php endif; ?>

            <div class="mavlers-field-input">
                <?php
                switch ($field['field_type']) {
                    case 'text':
                    case 'email':
                    case 'number':
                    case 'tel':
                    case 'url':
                        printf(
                            '<input type="%s" id="%s" name="%s" class="mavlers-input" %s %s %s>',
                            esc_attr($field['field_type']),
                            $field_id,
                            esc_attr($field['field_name']),
                            $required,
                            !empty($field['field_placeholder']) ? 'placeholder="' . esc_attr($field['field_placeholder']) . '"' : '',
                            !empty($field['field_validation']) ? 'data-validation="' . esc_attr($field['field_validation']) . '"' : ''
                        );
                        break;
                    
                    case 'textarea':
                        printf(
                            '<textarea id="%s" name="%s" class="mavlers-textarea" %s %s>%s</textarea>',
                            $field_id,
                            esc_attr($field['field_name']),
                            $required,
                            !empty($field['field_placeholder']) ? 'placeholder="' . esc_attr($field['field_placeholder']) . '"' : '',
                            esc_textarea($field['field_default'] ?? '')
                        );
                        break;

                    case 'select':
                        echo '<select id="' . $field_id . '" name="' . esc_attr($field['field_name']) . '" class="mavlers-select" ' . $required . '>';
                        if (!empty($field['field_placeholder'])) {
                            echo '<option value="">' . esc_html($field['field_placeholder']) . '</option>';
                        }
                        if (!empty($field['field_options'])) {
                            foreach ($field['field_options'] as $option) {
                                printf(
                                    '<option value="%s">%s</option>',
                                    esc_attr($option),
                                    esc_html($option)
                                );
                            }
                        }
                        echo '</select>';
                        break;

                    case 'checkbox':
                    case 'radio':
                        if (!empty($field['field_options'])) {
                            $options = is_array($field['field_options']) ? $field['field_options'] : explode("\n", $field['field_options']);
                            foreach ($options as $option) {
                                $option = trim($option);
                                if (empty($option)) continue;
                                
                                printf(
                                    '<label class="mavlers-%s-label"><input type="%s" name="%s" value="%s" %s> %s</label>',
                                    $field['field_type'],
                                    $field['field_type'],
                                    esc_attr($field['field_name']),
                                    esc_attr($option),
                                    $required,
                                    esc_html($option)
                                );
                            }
                        }
                        break;
                    
                    case 'file':
                        printf(
                            '<input type="file" id="%s" name="%s" class="mavlers-file" %s %s>',
                            $field_id,
                            esc_attr($field['field_name']),
                            $required,
                            !empty($field['field_accept']) ? 'accept="' . esc_attr($field['field_accept']) . '"' : ''
                        );
                        break;
                    
                    case 'html':
                        echo wp_kses_post($field['field_content'] ?? '');
                        break;
                    
                    case 'divider':
                        $divider_style = !empty($field['divider_style']) ? $field['divider_style'] : 'solid';
                        $divider_color = !empty($field['divider_color']) ? $field['divider_color'] : '#ddd';
                        $divider_width = !empty($field['divider_width']) ? $field['divider_width'] : 'full';
                        $divider_margin = !empty($field['divider_margin']) ? $field['divider_margin'] : 20;
                        $divider_text = !empty($field['divider_text']) ? $field['divider_text'] : '';
                        
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

                        echo '<div class="mavlers-divider ' . esc_attr($width_class) . '" style="margin: ' . esc_attr($divider_margin) . 'px 0;">';
                        if (!empty($divider_text)) {
                            echo '<span class="mavlers-divider-text">' . esc_html($divider_text) . '</span>';
                        }
                        echo '<hr style="border-style: ' . esc_attr($divider_style) . '; border-color: ' . esc_attr($divider_color) . ';">';
                        echo '</div>';
                        break;

                    case 'captcha':
                        $captcha_type = !empty($field['captcha_type']) ? $field['captcha_type'] : 'simple';
                        if ($captcha_type === 'recaptcha') {
                            $site_key = !empty($field['site_key']) ? $field['site_key'] : '';
                            if (empty($site_key)) {
                                echo '<div class="mavlers-error">reCAPTCHA site key is not configured</div>';
                            } else {
                                echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
                            }
                        } else {
                            // Generate random numbers for math captcha
                            $num1 = rand(1, 10);
                            $num2 = rand(1, 10);
                            $answer = $num1 + $num2;
                            
                            // Store the answer in a session
                            if (!session_id()) {
                                session_start();
                            }
                            $_SESSION['mavlers_captcha_answer'] = $answer;
                            
                            echo '<div class="mavlers-simple-captcha">';
                            echo '<div class="mavlers-captcha-question">What is ' . esc_html($num1) . ' + ' . esc_html($num2) . '?</div>';
                            echo '<input type="text" name="captcha_answer" class="widefat" required>';
                            echo '</div>';
                        }
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
}

// Initialize the form renderer
Mavlers_Form_Renderer::get_instance(); 