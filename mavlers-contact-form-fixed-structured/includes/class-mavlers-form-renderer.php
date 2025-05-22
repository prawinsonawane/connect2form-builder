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
        wp_enqueue_style('mavlers-form', MAVLERS_FORM_PLUGIN_URL . 'assets/css/form.css', array(), MAVLERS_FORM_VERSION);
        wp_enqueue_script('mavlers-form', MAVLERS_FORM_PLUGIN_URL . 'assets/js/form.js', array('jquery'), MAVLERS_FORM_VERSION, true);
        
        // Add reCAPTCHA if enabled
        if (get_option('mavlers_recaptcha_enabled')) {
            wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        }
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
        $fields_table = $wpdb->prefix . 'mavlers_form_fields';

        // Get form
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$forms_table} WHERE id = %d AND status = 'active'",
            $form_id
        ));

        if (!$form) {
            return '';
        }

        // Get form fields
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$fields_table} WHERE form_id = %d ORDER BY field_order ASC",
            $form_id
        ));

        ob_start();
        ?>
        <div class="mavlers-form-wrapper">
            <form class="mavlers-form" id="mavlers-form-<?php echo esc_attr($form_id); ?>" method="post">
                <?php wp_nonce_field('mavlers_form_submit', 'mavlers_form_nonce'); ?>
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">

                <?php foreach ($fields as $field) : ?>
                    <?php echo $this->render_field($field); ?>
                <?php endforeach; ?>

                <div class="mavlers-form-messages"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_field($field) {
        $required = $field->field_required ? 'required' : '';
        $css_class = $field->field_css_class ? ' ' . esc_attr($field->field_css_class) : '';
        $field_size = $field->field_size ? ' ' . esc_attr($field->field_size) : '';
        $validation = $field->field_validation ? json_decode($field->field_validation, true) : array();

        $html = '<div class="mavlers-field-wrapper' . $css_class . '">';
        
        // Field label
        if ($field->field_type !== 'hidden' && $field->field_type !== 'html' && $field->field_type !== 'divider') {
            $html .= sprintf(
                '<label for="field_%s">%s%s</label>',
                esc_attr($field->id),
                esc_html($field->field_label),
                $required ? ' <span class="required">*</span>' : ''
            );
        }

        // Field description
        if ($field->field_description) {
            $html .= sprintf(
                '<p class="field-description">%s</p>',
                esc_html($field->field_description)
            );
        }

        // Field input
        switch ($field->field_type) {
            case 'text':
                $html .= sprintf(
                    '<input type="text" id="field_%s" name="field_%s" %s class="widefat%s" %s %s %s>',
                    esc_attr($field->id),
                    esc_attr($field->id),
                    $required,
                    $field_size,
                    $field->field_placeholder ? 'placeholder="' . esc_attr($field->field_placeholder) . '"' : '',
                    $field->field_max_chars ? 'maxlength="' . esc_attr($field->field_max_chars) . '"' : '',
                    $validation ? 'data-validation=\'' . esc_attr(json_encode($validation)) . '\'' : ''
                );
                break;

            case 'paragraph':
                $html .= sprintf(
                    '<textarea id="field_%s" name="field_%s" %s class="widefat%s" rows="%d" %s %s>%s</textarea>',
                    esc_attr($field->id),
                    esc_attr($field->id),
                    $required,
                    $field_size,
                    $field->field_rows ? intval($field->field_rows) : 4,
                    $field->field_placeholder ? 'placeholder="' . esc_attr($field->field_placeholder) . '"' : '',
                    $field->field_max_chars ? 'maxlength="' . esc_attr($field->field_max_chars) . '"' : '',
                    esc_textarea($field->field_content)
                );
                break;

            case 'checkbox':
                $options = json_decode($field->field_options, true);
                if (is_array($options)) {
                    foreach ($options as $option) {
                        $html .= sprintf(
                            '<label class="checkbox-label"><input type="checkbox" name="field_%s[]" value="%s" %s> %s</label>',
                            esc_attr($field->id),
                            esc_attr($option),
                            $required,
                            esc_html($option)
                        );
                    }
                }
                break;

            case 'dropdown':
                $options = json_decode($field->field_options, true);
                $html .= sprintf(
                    '<select id="field_%s" name="field_%s" %s class="widefat%s">',
                    esc_attr($field->id),
                    esc_attr($field->id),
                    $required,
                    $field_size
                );
                $html .= '<option value="">' . __('Select an option', 'mavlers-contact-form') . '</option>';
                if (is_array($options)) {
                    foreach ($options as $option) {
                        $html .= sprintf(
                            '<option value="%s">%s</option>',
                            esc_attr($option),
                            esc_html($option)
                        );
                    }
                }
                $html .= '</select>';
                break;

            case 'number':
                $html .= sprintf(
                    '<input type="number" id="field_%s" name="field_%s" %s class="widefat%s" %s %s %s %s>',
                    esc_attr($field->id),
                    esc_attr($field->id),
                    $required,
                    $field_size,
                    $field->field_min_value ? 'min="' . esc_attr($field->field_min_value) . '"' : '',
                    $field->field_max_value ? 'max="' . esc_attr($field->field_max_value) . '"' : '',
                    $field->field_step ? 'step="' . esc_attr($field->field_step) . '"' : '',
                    $validation ? 'data-validation=\'' . esc_attr(json_encode($validation)) . '\'' : ''
                );
                break;

            case 'radio':
                $options = json_decode($field->field_options, true);
                if (is_array($options)) {
                    foreach ($options as $option) {
                        $html .= sprintf(
                            '<label class="radio-label"><input type="radio" name="field_%s" value="%s" %s> %s</label>',
                            esc_attr($field->id),
                            esc_attr($option),
                            $required,
                            esc_html($option)
                        );
                    }
                }
                break;

            case 'hidden':
                $html .= sprintf(
                    '<input type="hidden" name="field_%s" value="%s">',
                    esc_attr($field->id),
                    esc_attr($field->field_content)
                );
                break;

            case 'file':
                $html .= sprintf(
                    '<input type="file" id="field_%s" name="field_%s" %s class="widefat%s" %s %s>',
                    esc_attr($field->id),
                    esc_attr($field->id),
                    $required,
                    $field_size,
                    $field->field_allowed_types ? 'accept="' . esc_attr($field->field_allowed_types) . '"' : '',
                    $field->field_max_size ? 'data-max-size="' . esc_attr($field->field_max_size) . '"' : ''
                );
                break;

            case 'html':
                $html .= wp_kses_post($field->field_content);
                break;

            case 'divider':
                if ($field->field_content === 'line') {
                    $html .= '<hr>';
                } else if ($field->field_content === 'space') {
                    $html .= '<div style="height: 20px;"></div>';
                } else {
                    $html .= '<div class="divider-text">' . esc_html($field->field_content) . '</div>';
                }
                break;

            case 'section':
                $html .= sprintf(
                    '<div class="form-section"><h3>%s</h3>%s</div>',
                    esc_html($field->field_label),
                    $field->field_description ? '<p>' . esc_html($field->field_description) . '</p>' : ''
                );
                break;

            case 'captcha':
                if ($field->field_content === 'recaptcha') {
                    $site_key = get_option('mavlers_recaptcha_site_key');
                    if ($site_key) {
                        $html .= '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
                    }
                } else {
                    $html .= $this->render_simple_captcha();
                }
                break;

            case 'submit':
                $html .= sprintf(
                    '<button type="submit" class="button button-primary%s">%s</button>',
                    $field->field_css_class ? ' ' . esc_attr($field->field_css_class) : '',
                    esc_html($field->field_content ?: __('Submit', 'mavlers-contact-form'))
                );
                break;
        }

        $html .= '</div>';
        return $html;
    }

    private function render_simple_captcha() {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $sum = $num1 + $num2;
        
        return sprintf(
            '<div class="simple-captcha">
                <p>%s</p>
                <input type="hidden" name="captcha_sum" value="%d">
                <input type="number" name="captcha_answer" required>
            </div>',
            sprintf(__('Please solve this simple math problem: %d + %d = ?', 'mavlers-contact-form'), $num1, $num2),
            $sum
        );
    }
}

// Initialize the form renderer
Mavlers_Form_Renderer::get_instance(); 