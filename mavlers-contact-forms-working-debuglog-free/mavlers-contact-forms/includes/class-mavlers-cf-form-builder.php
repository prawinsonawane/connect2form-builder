<?php
if (!defined('WPINC')) {
    die;
}

class Mavlers_CF_Form_Builder {
    private $field_types = array();

    public function __construct() {
        $this->init_field_types();
    }

    private function init_field_types() {
        $this->field_types = array(
            'text' => array(
                'label' => __('Textbox', 'mavlers-contact-forms'),
                'icon' => 'dashicons-editor-textcolor',
                'settings' => array('label', 'required', 'placeholder', 'default_value', 'validation')
            ),
            'textarea' => array(
                'label' => __('Paragraph Text', 'mavlers-contact-forms'),
                'icon' => 'dashicons-editor-paragraph',
                'settings' => array('label', 'required', 'placeholder', 'default_value', 'rows')
            ),
            'email' => array(
                'label' => __('Email', 'mavlers-contact-forms'),
                'icon' => 'dashicons-email',
                'settings' => array('label', 'required', 'placeholder', 'default_value')
            ),
            'number' => array(
                'label' => __('Number', 'mavlers-contact-forms'),
                'icon' => 'dashicons-calculator',
                'settings' => array('label', 'required', 'min', 'max', 'step', 'default_value')
            ),
            'date' => array(
                'label' => __('Date', 'mavlers-contact-forms'),
                'icon' => 'dashicons-calendar-alt',
                'settings' => array('label', 'required', 'date_format', 'min_date', 'max_date')
            ),
            'select' => array(
                'label' => __('Drop Down', 'mavlers-contact-forms'),
                'icon' => 'dashicons-arrow-down-alt2',
                'settings' => array('label', 'required', 'choices', 'placeholder')
            ),
            'radio' => array(
                'label' => __('Radio Buttons', 'mavlers-contact-forms'),
                'icon' => 'dashicons-marker',
                'settings' => array('label', 'required', 'choices', 'layout')
            ),
            'checkbox' => array(
                'label' => __('Checkboxes', 'mavlers-contact-forms'),
                'icon' => 'dashicons-yes',
                'settings' => array('label', 'required', 'choices', 'layout')
            ),
            'multiselect' => array(
                'label' => __('Multiple Choice', 'mavlers-contact-forms'),
                'icon' => 'dashicons-list-view',
                'settings' => array('label', 'required', 'choices', 'layout')
            ),
            'image_choice' => array(
                'label' => __('Image Choice', 'mavlers-contact-forms'),
                'icon' => 'dashicons-format-image',
                'settings' => array('label', 'required', 'choices', 'layout', 'image_size')
            ),
            'file_upload' => array(
                'label' => __('File Upload', 'mavlers-contact-forms'),
                'icon' => 'dashicons-upload',
                'settings' => array('label', 'required', 'allowed_types', 'max_size', 'max_files')
            ),
            'hidden' => array(
                'label' => __('Hidden', 'mavlers-contact-forms'),
                'icon' => 'dashicons-hidden',
                'settings' => array('label', 'default_value')
            ),
            'html' => array(
                'label' => __('HTML', 'mavlers-contact-forms'),
                'icon' => 'dashicons-editor-code',
                'settings' => array('content')
            ),
            'captcha' => array(
                'label' => __('CAPTCHA', 'mavlers-contact-forms'),
                'icon' => 'dashicons-shield',
                'settings' => array('label', 'site_key', 'secret_key', 'theme')
            ),
            'submit' => array(
                'label' => __('Submit Button', 'mavlers-contact-forms'),
                'icon' => 'dashicons-saved',
                'settings' => array('label', 'button_type', 'css_class')
            )
        );
    }

    public function get_field_types() {
        return $this->field_types;
    }

    public function render_field_settings($field_type, $field_data = array()) {
        if (!isset($this->field_types[$field_type])) {
            return '';
        }

        $settings = $this->field_types[$field_type]['settings'];
        $output = '';

        foreach ($settings as $setting) {
            $value = isset($field_data[$setting]) ? $field_data[$setting] : '';
            $output .= $this->render_setting_field($setting, $value, $field_type);
        }

        return $output;
    }

    private function render_setting_field($setting, $value, $field_type) {
        $output = '<div class="field-setting field-setting-' . esc_attr($setting) . '">';
        $output .= '<label>' . esc_html($this->get_setting_label($setting)) . '</label>';

        switch ($setting) {
            case 'label':
                $output .= '<input type="text" name="settings[' . esc_attr($setting) . ']" value="' . esc_attr($value) . '" class="widefat">';
                break;

            case 'required':
                $output .= '<input type="checkbox" name="settings[' . esc_attr($setting) . ']" value="1" ' . checked($value, '1', false) . '>';
                break;

            case 'choices':
                $output .= $this->render_choices_setting($value);
                break;

            case 'placeholder':
            case 'default_value':
                $output .= '<input type="text" name="settings[' . esc_attr($setting) . ']" value="' . esc_attr($value) . '" class="widefat">';
                break;

            case 'content':
                $output .= '<textarea name="settings[' . esc_attr($setting) . ']" class="widefat" rows="5">' . esc_textarea($value) . '</textarea>';
                break;

            case 'rows':
            case 'min':
            case 'max':
            case 'step':
            case 'max_size':
            case 'max_files':
                $output .= '<input type="number" name="settings[' . esc_attr($setting) . ']" value="' . esc_attr($value) . '" class="small-text">';
                break;

            case 'layout':
                $output .= '<select name="settings[' . esc_attr($setting) . ']" class="widefat">';
                $output .= '<option value="vertical" ' . selected($value, 'vertical', false) . '>' . __('Vertical', 'mavlers-contact-forms') . '</option>';
                $output .= '<option value="horizontal" ' . selected($value, 'horizontal', false) . '>' . __('Horizontal', 'mavlers-contact-forms') . '</option>';
                $output .= '</select>';
                break;

            case 'date_format':
                $output .= '<select name="settings[' . esc_attr($setting) . ']" class="widefat">';
                $output .= '<option value="m/d/Y" ' . selected($value, 'm/d/Y', false) . '>MM/DD/YYYY</option>';
                $output .= '<option value="d/m/Y" ' . selected($value, 'd/m/Y', false) . '>DD/MM/YYYY</option>';
                $output .= '<option value="Y-m-d" ' . selected($value, 'Y-m-d', false) . '>YYYY-MM-DD</option>';
                $output .= '</select>';
                break;

            case 'allowed_types':
                $output .= '<input type="text" name="settings[' . esc_attr($setting) . ']" value="' . esc_attr($value) . '" class="widefat" placeholder="jpg,jpeg,png,pdf">';
                break;

            case 'site_key':
            case 'secret_key':
                $output .= '<input type="text" name="settings[' . esc_attr($setting) . ']" value="' . esc_attr($value) . '" class="widefat">';
                break;

            case 'theme':
                $output .= '<select name="settings[' . esc_attr($setting) . ']" class="widefat">';
                $output .= '<option value="light" ' . selected($value, 'light', false) . '>' . __('Light', 'mavlers-contact-forms') . '</option>';
                $output .= '<option value="dark" ' . selected($value, 'dark', false) . '>' . __('Dark', 'mavlers-contact-forms') . '</option>';
                $output .= '</select>';
                break;
        }

        $output .= '</div>';
        return $output;
    }

    private function render_choices_setting($choices) {
        $output = '<div class="choices-container">';
        if (!empty($choices)) {
            foreach ($choices as $index => $choice) {
                $output .= $this->render_choice_row($index, $choice);
            }
        }
        $output .= '<button type="button" class="button add-choice">' . __('Add Choice', 'mavlers-contact-forms') . '</button>';
        $output .= '</div>';
        return $output;
    }

    private function render_choice_row($index, $choice) {
        $output = '<div class="choice-row">';
        $output .= '<input type="text" name="settings[choices][' . esc_attr($index) . '][label]" value="' . esc_attr($choice['label']) . '" placeholder="' . __('Label', 'mavlers-contact-forms') . '" class="widefat">';
        $output .= '<input type="text" name="settings[choices][' . esc_attr($index) . '][value]" value="' . esc_attr($choice['value']) . '" placeholder="' . __('Value', 'mavlers-contact-forms') . '" class="widefat">';
        $output .= '<button type="button" class="button remove-choice">' . __('Remove', 'mavlers-contact-forms') . '</button>';
        $output .= '</div>';
        return $output;
    }

    private function get_setting_label($setting) {
        $labels = array(
            'label' => __('Field Label', 'mavlers-contact-forms'),
            'required' => __('Required', 'mavlers-contact-forms'),
            'placeholder' => __('Placeholder', 'mavlers-contact-forms'),
            'default_value' => __('Default Value', 'mavlers-contact-forms'),
            'choices' => __('Choices', 'mavlers-contact-forms'),
            'content' => __('HTML Content', 'mavlers-contact-forms'),
            'rows' => __('Number of Rows', 'mavlers-contact-forms'),
            'min' => __('Minimum Value', 'mavlers-contact-forms'),
            'max' => __('Maximum Value', 'mavlers-contact-forms'),
            'step' => __('Step', 'mavlers-contact-forms'),
            'layout' => __('Layout', 'mavlers-contact-forms'),
            'date_format' => __('Date Format', 'mavlers-contact-forms'),
            'allowed_types' => __('Allowed File Types', 'mavlers-contact-forms'),
            'max_size' => __('Maximum File Size (MB)', 'mavlers-contact-forms'),
            'max_files' => __('Maximum Number of Files', 'mavlers-contact-forms'),
            'site_key' => __('reCAPTCHA Site Key', 'mavlers-contact-forms'),
            'secret_key' => __('reCAPTCHA Secret Key', 'mavlers-contact-forms'),
            'theme' => __('reCAPTCHA Theme', 'mavlers-contact-forms')
        );

        return isset($labels[$setting]) ? $labels[$setting] : ucfirst(str_replace('_', ' ', $setting));
    }
} 