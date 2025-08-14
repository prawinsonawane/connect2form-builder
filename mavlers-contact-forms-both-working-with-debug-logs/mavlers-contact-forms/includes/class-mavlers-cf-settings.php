<?php
/**
 * Class Mavlers_CF_Settings
 * Handles the plugin's global settings
 */
class Mavlers_CF_Settings {
    /**
     * Initialize the settings
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('mavlers_cf_settings', 'mavlers_cf_antispam_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_antispam_settings')
        ));

        register_setting('mavlers_cf_settings', 'mavlers_cf_recaptcha_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_recaptcha_settings')
        ));

        add_settings_section(
            'mavlers_cf_antispam_section',
            __('Anti-Spam Protection', 'mavlers-contact-forms'),
            array($this, 'render_antispam_section'),
            'mavlers_cf_settings'
        );

        add_settings_section(
            'mavlers_cf_recaptcha_section',
            __('Google reCAPTCHA Settings', 'mavlers-contact-forms'),
            array($this, 'render_recaptcha_section'),
            'mavlers_cf_settings'
        );

        // Honeypot settings
        add_settings_field(
            'enable_honeypot',
            __('Enable Anti-spam Honeypot', 'mavlers-contact-forms'),
            array($this, 'render_honeypot_field'),
            'mavlers_cf_settings',
            'mavlers_cf_antispam_section'
        );

        add_settings_field(
            'honeypot_action',
            __('Honeypot Action', 'mavlers-contact-forms'),
            array($this, 'render_honeypot_action_field'),
            'mavlers_cf_settings',
            'mavlers_cf_antispam_section'
        );

        // reCAPTCHA settings
        add_settings_field(
            'recaptcha_type',
            __('reCAPTCHA Type', 'mavlers-contact-forms'),
            array($this, 'render_recaptcha_type_field'),
            'mavlers_cf_settings',
            'mavlers_cf_recaptcha_section'
        );

        add_settings_field(
            'recaptcha_site_key',
            __('Site Key', 'mavlers-contact-forms'),
            array($this, 'render_recaptcha_site_key_field'),
            'mavlers_cf_settings',
            'mavlers_cf_recaptcha_section'
        );

        add_settings_field(
            'recaptcha_secret_key',
            __('Secret Key', 'mavlers-contact-forms'),
            array($this, 'render_recaptcha_secret_key_field'),
            'mavlers_cf_settings',
            'mavlers_cf_recaptcha_section'
        );
    }

    /**
     * Render the anti-spam section description
     */
    public function render_antispam_section() {
        echo '<p>' . __('Configure anti-spam protection settings for your forms.', 'mavlers-contact-forms') . '</p>';
    }

    /**
     * Render the reCAPTCHA section description
     */
    public function render_recaptcha_section() {
        echo '<p>' . __('Configure Google reCAPTCHA settings. You can get your Site Key and Secret Key from the Google reCAPTCHA admin console.', 'mavlers-contact-forms') . '</p>';
        echo '<p><a href="https://www.google.com/recaptcha/admin" target="_blank">' . __('Get reCAPTCHA Keys', 'mavlers-contact-forms') . '</a></p>';
    }

    /**
     * Render the honeypot enable field
     */
    public function render_honeypot_field() {
        $options = get_option('mavlers_cf_antispam_settings', array());
        $enabled = isset($options['enable_honeypot']) ? $options['enable_honeypot'] : false;
        ?>
        <label>
            <input type="checkbox" name="mavlers_cf_antispam_settings[enable_honeypot]" value="1" <?php checked($enabled, true); ?>>
            <?php _e('Enable honeypot spam protection', 'mavlers-contact-forms'); ?>
        </label>
        <p class="description">
            <?php _e('The honeypot technique adds an invisible field to your forms that should be left empty. Since automated bots typically fill all fields, this helps identify and block spam submissions.', 'mavlers-contact-forms'); ?>
        </p>
        <?php
    }

    /**
     * Render the honeypot action field
     */
    public function render_honeypot_action_field() {
        $options = get_option('mavlers_cf_antispam_settings', array());
        $action = isset($options['honeypot_action']) ? $options['honeypot_action'] : 'mark_spam';
        ?>
        <select name="mavlers_cf_antispam_settings[honeypot_action]">
            <option value="mark_spam" <?php selected($action, 'mark_spam'); ?>>
                <?php _e('Create an entry and mark it as spam', 'mavlers-contact-forms'); ?>
            </option>
            <option value="discard" <?php selected($action, 'discard'); ?>>
                <?php _e('Do not create an entry', 'mavlers-contact-forms'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Render the reCAPTCHA type field
     */
    public function render_recaptcha_type_field() {
        $options = get_option('mavlers_cf_recaptcha_settings', array());
        $type = isset($options['recaptcha_type']) ? $options['recaptcha_type'] : 'v2_checkbox';
        ?>
        <select name="mavlers_cf_recaptcha_settings[recaptcha_type]">
            <option value="v2_checkbox" <?php selected($type, 'v2_checkbox'); ?>>
                <?php _e('reCAPTCHA v2 Checkbox', 'mavlers-contact-forms'); ?>
            </option>
            <option value="v2_invisible" <?php selected($type, 'v2_invisible'); ?>>
                <?php _e('reCAPTCHA v2 Invisible', 'mavlers-contact-forms'); ?>
            </option>
            <option value="v3" <?php selected($type, 'v3'); ?>>
                <?php _e('reCAPTCHA v3', 'mavlers-contact-forms'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Render the reCAPTCHA site key field
     */
    public function render_recaptcha_site_key_field() {
        $options = get_option('mavlers_cf_recaptcha_settings', array());
        $site_key = isset($options['site_key']) ? $options['site_key'] : '';
        ?>
        <input type="text" name="mavlers_cf_recaptcha_settings[site_key]" value="<?php echo esc_attr($site_key); ?>" class="regular-text">
        <?php
    }

    /**
     * Render the reCAPTCHA secret key field
     */
    public function render_recaptcha_secret_key_field() {
        $options = get_option('mavlers_cf_recaptcha_settings', array());
        $secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';
        ?>
        <input type="password" name="mavlers_cf_recaptcha_settings[secret_key]" value="<?php echo esc_attr($secret_key); ?>" class="regular-text">
        <?php
    }

    /**
     * Sanitize the anti-spam settings
     */
    public function sanitize_antispam_settings($input) {
        $sanitized = array();
        $sanitized['enable_honeypot'] = isset($input['enable_honeypot']) ? (bool) $input['enable_honeypot'] : false;
        $sanitized['honeypot_action'] = isset($input['honeypot_action']) && in_array($input['honeypot_action'], array('mark_spam', 'discard')) 
            ? $input['honeypot_action'] 
            : 'mark_spam';
        return $sanitized;
    }

    /**
     * Sanitize the reCAPTCHA settings
     */
    public function sanitize_recaptcha_settings($input) {
        $sanitized = array();
        $sanitized['recaptcha_type'] = isset($input['recaptcha_type']) && in_array($input['recaptcha_type'], array('v2_checkbox', 'v2_invisible', 'v3')) 
            ? $input['recaptcha_type'] 
            : 'v2_checkbox';
        $sanitized['site_key'] = isset($input['site_key']) ? sanitize_text_field($input['site_key']) : '';
        $sanitized['secret_key'] = isset($input['secret_key']) ? sanitize_text_field($input['secret_key']) : '';
        return $sanitized;
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('mavlers_cf_settings');
                do_settings_sections('mavlers_cf_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
} 