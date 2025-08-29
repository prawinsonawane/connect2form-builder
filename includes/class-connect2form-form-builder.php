<?php
/**
 * Connect2Form Form Builder Class
 *
 * Handles form field types and settings rendering
 *
 * @package Connect2Form
 * @since 1.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Connect2Form Form Builder Class
 *
 * Handles form field types and settings rendering
 *
 * @since    1.0.0
 */
class Connect2Form_Form_Builder {
	/**
	 * Field types configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $field_types    Field types configuration.
	 */
	private $field_types = array();

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		// Initialize field types lazily when needed.
	}

	/**
	 * Initialize field types.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function init_field_types() {
		if ( isset( $this->field_types ) ) {
			return;
		}

		$this->field_types = array(
			'text' => array(
				'label'    => __('Textbox', 'connect2form-builder'),
				'icon'     => 'dashicons-editor-textcolor',
				'settings' => array( 'label', 'required', 'placeholder', 'default_value', 'validation' ),
			),
			'textarea' => array(
				'label'    => __('Paragraph Text', 'connect2form-builder'),
				'icon'     => 'dashicons-editor-paragraph',
				'settings' => array( 'label', 'required', 'placeholder', 'default_value', 'rows' ),
			),
			'email' => array(
				'label'    => __('Email', 'connect2form-builder'),
				'icon'     => 'dashicons-email',
				'settings' => array( 'label', 'required', 'placeholder', 'default_value' ),
			),
			'number' => array(
				'label'    => __('Number', 'connect2form-builder'),
				'icon'     => 'dashicons-calculator',
				'settings' => array( 'label', 'required', 'min', 'max', 'step', 'default_value' ),
			),
			'date' => array(
				'label'    => __('Date', 'connect2form-builder'),
				'icon'     => 'dashicons-calendar-alt',
				'settings' => array( 'label', 'required', 'date_format', 'min_date', 'max_date' ),
			),
			'select' => array(
				'label'    => __('Drop Down', 'connect2form-builder'),
				'icon'     => 'dashicons-arrow-down-alt2',
				'settings' => array( 'label', 'required', 'choices', 'placeholder' ),
			),
			'radio' => array(
				'label'    => __('Radio Buttons', 'connect2form-builder'),
				'icon'     => 'dashicons-marker',
				'settings' => array( 'label', 'required', 'choices', 'layout' ),
			),
			'checkbox' => array(
				'label'    => __('Checkboxes', 'connect2form-builder'),
				'icon'     => 'dashicons-yes',
				'settings' => array( 'label', 'required', 'choices', 'layout' ),
			),
			'multiselect' => array(
				'label'    => __('Multiple Choice', 'connect2form-builder'),
				'icon'     => 'dashicons-list-view',
				'settings' => array( 'label', 'required', 'choices', 'layout' ),
			),
			'image_choice' => array(
				'label'    => __('Image Choice', 'connect2form-builder'),
				'icon'     => 'dashicons-format-image',
				'settings' => array( 'label', 'required', 'choices', 'layout', 'image_size' ),
			),
			'file_upload' => array(
				'label'    => __('File Upload', 'connect2form-builder'),
				'icon'     => 'dashicons-upload',
				'settings' => array( 'label', 'required', 'allowed_types', 'max_size', 'max_files' ),
			),
			'hidden' => array(
				'label'    => __('Hidden', 'connect2form-builder'),
				'icon'     => 'dashicons-hidden',
				'settings' => array( 'label', 'default_value' ),
			),
			'html' => array(
				'label'    => __('HTML', 'connect2form-builder'),
				'icon'     => 'dashicons-editor-code',
				'settings' => array( 'content' ),
			),
			'captcha' => array(
				'label'    => __('CAPTCHA', 'connect2form-builder'),
				'icon'     => 'dashicons-shield',
				'settings' => array( 'label', 'site_key', 'secret_key', 'theme' ),
			),
			'submit' => array(
				'label'    => __('Submit Button', 'connect2form-builder'),
				'icon'     => 'dashicons-saved',
				'settings' => array( 'label', 'button_type', 'css_class' ),
			),
		);
	}

	/**
	 * Get field types.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	public function get_field_types() {
		$this->init_field_types();
		return $this->field_types;
	}

	/**
	 * Render field settings.
	 *
	 * @since    1.0.0
	 * @param    string $field_type Field type.
	 * @param    array  $field_data Field data.
	 * @return   string
	 */
	public function render_field_settings( $field_type, $field_data = array() ) {
		if ( ! isset( $this->field_types[ $field_type ] ) ) {
			return '';
		}

		$settings = $this->field_types[ $field_type ]['settings'];
		$output   = '';

		foreach ( $settings as $setting ) {
			$value  = isset( $field_data[ $setting ] ) ? $field_data[ $setting ] : '';
			$output .= $this->render_setting_field( $setting, $value, $field_type );
		}

		return $output;
	}

	/**
	 * Render setting field.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $setting    Setting name.
	 * @param    mixed  $value      Setting value.
	 * @param    string $field_type Field type.
	 * @return   string
	 */
	private function render_setting_field( $setting, $value, $field_type ) {
		$output = '<div class="field-setting field-setting-' . esc_attr( $setting ) . '">';
		$output .= '<label>' . esc_html( $this->get_setting_label( $setting ) ) . '</label>';

		switch ( $setting ) {
			case 'label':
				$output .= '<input type="text" name="settings[' . esc_attr( $setting ) . ']" value="' . esc_attr( $value ) . '" class="widefat">';
				break;

			case 'required':
				$output .= '<input type="checkbox" name="settings[' . esc_attr( $setting ) . ']" value="1" ' . checked( $value, '1', false ) . '>';
				break;

			case 'choices':
				$output .= $this->render_choices_setting( $value );
				break;

			case 'placeholder':
			case 'default_value':
				$output .= '<input type="text" name="settings[' . esc_attr( $setting ) . ']" value="' . esc_attr( $value ) . '" class="widefat">';
				break;

			case 'content':
				$output .= '<textarea name="settings[' . esc_attr( $setting ) . ']" class="widefat" rows="5">' . esc_textarea( $value ) . '</textarea>';
				break;

			case 'rows':
			case 'min':
			case 'max':
			case 'step':
			case 'max_size':
			case 'max_files':
				$output .= '<input type="number" name="settings[' . esc_attr( $setting ) . ']" value="' . esc_attr( $value ) . '" class="small-text">';
				break;

			case 'layout':
				$output .= '<select name="settings[' . esc_attr( $setting ) . ']" class="widefat">';
				$output .= '<option value="vertical" ' . selected( $value, 'vertical', false ) . '>' . __('Vertical', 'connect2form-builder') . '</option>';
				$output .= '<option value="horizontal" ' . selected( $value, 'horizontal', false ) . '>' . __('Horizontal', 'connect2form-builder') . '</option>';
				$output .= '</select>';
				break;

			case 'date_format':
				$output .= '<select name="settings[' . esc_attr( $setting ) . ']" class="widefat">';
				$output .= '<option value="m/d/Y" ' . selected( $value, 'm/d/Y', false ) . '>MM/DD/YYYY</option>';
				$output .= '<option value="d/m/Y" ' . selected( $value, 'd/m/Y', false ) . '>DD/MM/YYYY</option>';
				$output .= '<option value="Y-m-d" ' . selected( $value, 'Y-m-d', false ) . '>YYYY-MM-DD</option>';
				$output .= '</select>';
				break;

			case 'allowed_types':
				$output .= '<input type="text" name="settings[' . esc_attr( $setting ) . ']" value="' . esc_attr( $value ) . '" class="widefat" placeholder="jpg,jpeg,png,pdf">';
				break;

			case 'site_key':
			case 'secret_key':
				$output .= '<input type="text" name="settings[' . esc_attr( $setting ) . ']" value="' . esc_attr( $value ) . '" class="widefat">';
				break;

			case 'theme':
				$output .= '<select name="settings[' . esc_attr( $setting ) . ']" class="widefat">';
				$output .= '<option value="light" ' . selected( $value, 'light', false ) . '>' . __('Light', 'connect2form-builder') . '</option>';
				$output .= '<option value="dark" ' . selected( $value, 'dark', false ) . '>' . __('Dark', 'connect2form-builder') . '</option>';
				$output .= '</select>';
				break;
		}

		$output .= '</div>';
		return $output;
	}

	/**
	 * Render choices setting.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $choices Choices array.
	 * @return   string
	 */
	private function render_choices_setting( $choices ) {
		$output = '<div class="choices-container">';
		if ( ! empty( $choices ) ) {
			foreach ( $choices as $index => $choice ) {
				$output .= $this->render_choice_row( $index, $choice );
			}
		}
		$output .= '<button type="button" class="button add-choice">' . __('Add Choice', 'connect2form-builder') . '</button>';
		$output .= '</div>';
		return $output;
	}

	/**
	 * Render choice row.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $index  Choice index.
	 * @param    array $choice Choice data.
	 * @return   string
	 */
	private function render_choice_row( $index, $choice ) {
		$output = '<div class="choice-row">';
		$output .= '<input type="text" name="settings[choices][' . esc_attr( $index ) . '][label]" value="' . esc_attr( $choice['label'] ) . '" placeholder="' . __('Label', 'connect2form-builder') . '" class="widefat">';
		$output .= '<input type="text" name="settings[choices][' . esc_attr( $index ) . '][value]" value="' . esc_attr( $choice['value'] ) . '" placeholder="' . __('Value', 'connect2form-builder') . '" class="widefat">';
		$output .= '<button type="button" class="button remove-choice">' . __('Remove', 'connect2form-builder') . '</button>';
		$output .= '</div>';
		return $output;
	}

	/**
	 * Get setting label.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $setting Setting name.
	 * @return   string
	 */
	private function get_setting_label( $setting ) {
		$labels = array(
			'label'         => __('Field Label', 'connect2form-builder'),
			'required'      => __('Required', 'connect2form-builder'),
			'placeholder'   => __('Placeholder', 'connect2form-builder'),
			'default_value' => __('Default Value', 'connect2form-builder'),
			'choices'       => __('Choices', 'connect2form-builder'),
			'content'       => __('HTML Content', 'connect2form-builder'),
			'rows'          => __('Number of Rows', 'connect2form-builder'),
			'min'           => __('Minimum Value', 'connect2form-builder'),
			'max'           => __('Maximum Value', 'connect2form-builder'),
			'step'          => __('Step', 'connect2form-builder'),
			'layout'        => __('Layout', 'connect2form-builder'),
			'date_format'   => __('Date Format', 'connect2form-builder'),
			'allowed_types' => __('Allowed File Types', 'connect2form-builder'),
			'max_size'      => __('Maximum File Size (MB)', 'connect2form-builder'),
			'max_files'     => __('Maximum Number of Files', 'connect2form-builder'),
			'site_key'      => __('reCAPTCHA Site Key', 'connect2form-builder'),
			'secret_key'    => __('reCAPTCHA Secret Key', 'connect2form-builder'),
			'theme'         => __('reCAPTCHA Theme', 'connect2form-builder'),
		);

		return isset( $labels[ $setting ] ) ? $labels[ $setting ] : ucfirst( str_replace( '_', ' ', $setting ) );
	}
}
