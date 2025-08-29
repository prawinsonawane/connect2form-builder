<?php
/**
 * Class Connect2Form_Settings
 * Handles the plugin's global settings
 *
 * @package Connect2Form
 * @since    1.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Connect2Form Settings Class
 *
 * Handles the plugin's global settings
 *
 * @since    1.0.0
 */
class Connect2Form_Settings {
	/**
	 * Initialize the settings
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register plugin settings
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting( 'connect2form_settings', 'connect2form_antispam_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_antispam_settings' ),
		) );

		register_setting( 'connect2form_settings', 'connect2form_recaptcha_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_recaptcha_settings' ),
		) );



		add_settings_section(
			'connect2form_antispam_section',
			__('Anti-Spam Protection', 'connect2form-builder'),
			array( $this, 'render_antispam_section' ),
			'connect2form_settings'
		);

		add_settings_section(
			'connect2form_recaptcha_section',
			__('Google reCAPTCHA Settings', 'connect2form-builder'),
			array( $this, 'render_recaptcha_section' ),
			'connect2form_settings'
		);



		// Honeypot settings.
		add_settings_field(
			'enable_honeypot',
			__('Enable Anti-spam Honeypot', 'connect2form-builder'),
			array( $this, 'render_honeypot_field' ),
			'connect2form_settings',
			'connect2form_antispam_section'
		);

		add_settings_field(
			'honeypot_action',
			__('Honeypot Action', 'connect2form-builder'),
			array( $this, 'render_honeypot_action_field' ),
			'connect2form_settings',
			'connect2form_antispam_section'
		);

		// reCAPTCHA settings.
		add_settings_field(
			'recaptcha_type',
			__('reCAPTCHA Type', 'connect2form-builder'),
			array( $this, 'render_recaptcha_type_field' ),
			'connect2form_settings',
			'connect2form_recaptcha_section'
		);

		add_settings_field(
			'recaptcha_site_key',
			__('Site Key', 'connect2form-builder'),
			array( $this, 'render_recaptcha_site_key_field' ),
			'connect2form_settings',
			'connect2form_recaptcha_section'
		);

		add_settings_field(
			'recaptcha_secret_key',
			__('Secret Key', 'connect2form-builder'),
			array( $this, 'render_recaptcha_secret_key_field' ),
			'connect2form_settings',
			'connect2form_recaptcha_section'
		);


	}

	/**
	 * Render the anti-spam section description
	 *
	 * @since    1.0.0
	 */
	public function render_antispam_section() {
		printf( '<p>%s</p>', esc_html__('Configure anti-spam protection settings for your forms.', 'connect2form-builder') );
	}

	/**
	 * Render the reCAPTCHA section description
	 *
	 * @since    1.0.0
	 */
	public function render_recaptcha_section() {
		printf( '<p>%s</p>', esc_html__('Configure Google reCAPTCHA settings. You can get your Site Key and Secret Key from the Google reCAPTCHA admin console.', 'connect2form-builder') );
		printf( '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_url( 'https://www.google.com/recaptcha/admin' ), esc_html__('Get reCAPTCHA Keys', 'connect2form-builder') );
	}



	/**
	 * Render the honeypot enable field
	 *
	 * @since    1.0.0
	 */
	public function render_honeypot_field() {
		$options = get_option( 'connect2form_antispam_settings', array() );
		$enabled = isset( $options['enable_honeypot'] ) ? $options['enable_honeypot'] : false;
		?>
		<label>
			<input type="checkbox" name="connect2form_antispam_settings[enable_honeypot]" value="1" <?php checked( $enabled, true ); ?>>
			<?php esc_html_e('Enable honeypot spam protection', 'connect2form-builder'); ?>
		</label>
		<p class="description">
			<?php esc_html_e('The honeypot technique adds an invisible field to your forms that should be left empty. Since automated bots typically fill all fields, this helps identify and block spam submissions.', 'connect2form-builder'); ?>
		</p>
		<?php
	}

	/**
	 * Render the honeypot action field
	 *
	 * @since    1.0.0
	 */
	public function render_honeypot_action_field() {
		$options = get_option( 'connect2form_antispam_settings', array() );
		$action   = isset( $options['honeypot_action'] ) ? $options['honeypot_action'] : 'mark_spam';
		?>
		<select name="connect2form_antispam_settings[honeypot_action]">
			<option value="mark_spam" <?php selected( $action, 'mark_spam' ); ?>>
				<?php esc_html_e('Create an entry and mark it as spam', 'connect2form-builder'); ?>
			</option>
			<option value="discard" <?php selected( $action, 'discard' ); ?>>
				<?php esc_html_e('Do not create an entry', 'connect2form-builder'); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render the reCAPTCHA type field
	 *
	 * @since    1.0.0
	 */
	public function render_recaptcha_type_field() {
		$options = get_option( 'connect2form_recaptcha_settings', array() );
		$type    = isset( $options['recaptcha_type'] ) ? $options['recaptcha_type'] : 'v2_checkbox';
		?>
		<select name="connect2form_recaptcha_settings[recaptcha_type]">
			<option value="v2_checkbox" <?php selected( $type, 'v2_checkbox' ); ?>>
				<?php esc_html_e('reCAPTCHA v2 Checkbox', 'connect2form-builder'); ?>
			</option>
			<option value="v2_invisible" <?php selected( $type, 'v2_invisible' ); ?>>
				<?php esc_html_e('reCAPTCHA v2 Invisible', 'connect2form-builder'); ?>
			</option>
			<option value="v3" <?php selected( $type, 'v3' ); ?>>
				<?php esc_html_e('reCAPTCHA v3', 'connect2form-builder'); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render the reCAPTCHA site key field
	 *
	 * @since    1.0.0
	 */
	public function render_recaptcha_site_key_field() {
		$options  = get_option( 'connect2form_recaptcha_settings', array() );
		$site_key = isset( $options['site_key'] ) ? $options['site_key'] : '';
		?>
		<input type="text" name="connect2form_recaptcha_settings[site_key]" value="<?php echo esc_attr( $site_key ); ?>" class="regular-text">
		<?php
	}

	/**
	 * Render the reCAPTCHA secret key field
	 *
	 * @since    1.0.0
	 */
	public function render_recaptcha_secret_key_field() {
		$options   = get_option( 'connect2form_recaptcha_settings', array() );
		$secret_key = isset( $options['secret_key'] ) ? $options['secret_key'] : '';
		?>
		<input type="password" name="connect2form_recaptcha_settings[secret_key]" value="<?php echo esc_attr( $secret_key ); ?>" class="regular-text">
		<?php
	}

	/**
	 * Sanitize the anti-spam settings
	 *
	 * @since    1.0.0
	 * @param    array $input Input data.
	 * @return   array
	 */
	public function sanitize_antispam_settings( $input ) {
		$sanitized = array();
		$sanitized['enable_honeypot'] = isset( $input['enable_honeypot'] ) ? (bool) $input['enable_honeypot'] : false;
		$sanitized['honeypot_action'] = isset( $input['honeypot_action'] ) && in_array( $input['honeypot_action'], array( 'mark_spam', 'discard' ), true )
			? $input['honeypot_action']
			: 'mark_spam';
		return $sanitized;
	}

	/**
	 * Sanitize the reCAPTCHA settings
	 *
	 * @since    1.0.0
	 * @param    array $input Input data.
	 * @return   array
	 */
	public function sanitize_recaptcha_settings( $input ) {
		$sanitized = array();
		$sanitized['recaptcha_type'] = isset( $input['recaptcha_type'] ) && in_array( $input['recaptcha_type'], array( 'v2_checkbox', 'v2_invisible', 'v3' ), true )
			? $input['recaptcha_type']
			: 'v2_checkbox';
		$sanitized['site_key']   = isset( $input['site_key'] ) ? sanitize_text_field( $input['site_key'] ) : '';
		$sanitized['secret_key'] = isset( $input['secret_key'] ) ? sanitize_text_field( $input['secret_key'] ) : '';
		return $sanitized;
	}



	/**
	 * Render the settings page
	 *
	 * @since    1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'connect2form_settings' );
				do_settings_sections( 'connect2form_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
} 
