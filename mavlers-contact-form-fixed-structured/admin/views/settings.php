<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = get_option('mavlers_contact_form_settings', array());

// Initialize default settings if not set
$default_settings = array(
    'form_settings' => array(
        'success_message' => __('Thank you for your submission!', 'mavlers-contact-form'),
        'error_message' => __('There was an error submitting the form. Please try again.', 'mavlers-contact-form'),
        'email_notification' => 'yes',
        'admin_email' => get_option('admin_email'),
        'email_subject' => __('New Form Submission', 'mavlers-contact-form'),
        'email_template' => __('You have received a new form submission.', 'mavlers-contact-form'),
        'store_submissions' => 'yes',
        'redirect_url' => '',
        'redirect_delay' => '0'
    ),
    'recaptcha_settings' => array(
        'enable_recaptcha' => 'no',
        'site_key' => '',
        'secret_key' => '',
        'recaptcha_version' => 'v2'
    ),
    'file_upload_settings' => array(
        'max_file_size' => '2',
        'allowed_file_types' => array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'),
        'upload_directory' => 'mavlers-uploads'
    )
);

// Merge default settings with saved settings
$settings = wp_parse_args($settings, $default_settings);

// Create nonce for settings form
$settings_nonce = wp_create_nonce('mavlers_settings_nonce');
?>

<div class="wrap">
    <h1><?php _e('Mavlers Contact Form Settings', 'mavlers-contact-form'); ?></h1>

    <form method="post" action="options.php" id="mavlers-settings-form">
        <?php settings_fields('mavlers_contact_form_settings'); ?>
        <input type="hidden" name="mavlers_nonce" value="<?php echo esc_attr($settings_nonce); ?>">

        <div class="mavlers-settings-container">
            <!-- Form Settings -->
            <div class="mavlers-settings-section">
                <h2><?php _e('Form Settings', 'mavlers-contact-form'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Success Message', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="text" name="mavlers_contact_form_settings[form_settings][success_message]" 
                                   value="<?php echo esc_attr($settings['form_settings']['success_message']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Error Message', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="text" name="mavlers_contact_form_settings[form_settings][error_message]" 
                                   value="<?php echo esc_attr($settings['form_settings']['error_message']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email Notification', 'mavlers-contact-form'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mavlers_contact_form_settings[form_settings][email_notification]" 
                                       value="yes" <?php checked($settings['form_settings']['email_notification'], 'yes'); ?>>
                                <?php _e('Enable email notifications', 'mavlers-contact-form'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Admin Email', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="email" name="mavlers_contact_form_settings[form_settings][admin_email]" 
                                   value="<?php echo esc_attr($settings['form_settings']['admin_email']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email Subject', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="text" name="mavlers_contact_form_settings[form_settings][email_subject]" 
                                   value="<?php echo esc_attr($settings['form_settings']['email_subject']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email Template', 'mavlers-contact-form'); ?></th>
                        <td>
                            <textarea name="mavlers_contact_form_settings[form_settings][email_template]" 
                                      class="large-text" rows="5"><?php echo esc_textarea($settings['form_settings']['email_template']); ?></textarea>
                            <p class="description">
                                <?php _e('Available variables: {form_name}, {submission_date}, {all_fields}', 'mavlers-contact-form'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Store Submissions', 'mavlers-contact-form'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mavlers_contact_form_settings[form_settings][store_submissions]" 
                                       value="yes" <?php checked($settings['form_settings']['store_submissions'], 'yes'); ?>>
                                <?php _e('Store form submissions in database', 'mavlers-contact-form'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Redirect After Submission', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="url" name="mavlers_contact_form_settings[form_settings][redirect_url]" 
                                   value="<?php echo esc_url($settings['form_settings']['redirect_url']); ?>" 
                                   class="regular-text" placeholder="https://">
                            <p class="description">
                                <?php _e('Leave empty to stay on the same page', 'mavlers-contact-form'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Redirect Delay', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="number" name="mavlers_contact_form_settings[form_settings][redirect_delay]" 
                                   value="<?php echo esc_attr($settings['form_settings']['redirect_delay']); ?>" 
                                   class="small-text" min="0" step="1">
                            <p class="description">
                                <?php _e('Delay in seconds before redirecting (0 for immediate redirect)', 'mavlers-contact-form'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- reCAPTCHA Settings -->
            <div class="mavlers-settings-section">
                <h2><?php _e('reCAPTCHA Settings', 'mavlers-contact-form'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable reCAPTCHA', 'mavlers-contact-form'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mavlers_contact_form_settings[recaptcha_settings][enable_recaptcha]" 
                                       value="yes" <?php checked($settings['recaptcha_settings']['enable_recaptcha'], 'yes'); ?>>
                                <?php _e('Enable reCAPTCHA protection', 'mavlers-contact-form'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Site Key', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="text" name="mavlers_contact_form_settings[recaptcha_settings][site_key]" 
                                   value="<?php echo esc_attr($settings['recaptcha_settings']['site_key']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Secret Key', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="text" name="mavlers_contact_form_settings[recaptcha_settings][secret_key]" 
                                   value="<?php echo esc_attr($settings['recaptcha_settings']['secret_key']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('reCAPTCHA Version', 'mavlers-contact-form'); ?></th>
                        <td>
                            <select name="mavlers_contact_form_settings[recaptcha_settings][recaptcha_version]">
                                <option value="v2" <?php selected($settings['recaptcha_settings']['recaptcha_version'], 'v2'); ?>>
                                    <?php _e('reCAPTCHA v2', 'mavlers-contact-form'); ?>
                                </option>
                                <option value="v3" <?php selected($settings['recaptcha_settings']['recaptcha_version'], 'v3'); ?>>
                                    <?php _e('reCAPTCHA v3', 'mavlers-contact-form'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- File Upload Settings -->
            <div class="mavlers-settings-section">
                <h2><?php _e('File Upload Settings', 'mavlers-contact-form'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Max File Size (MB)', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="number" name="mavlers_contact_form_settings[file_upload_settings][max_file_size]" 
                                   value="<?php echo esc_attr($settings['file_upload_settings']['max_file_size']); ?>" 
                                   class="small-text" min="1" max="10">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Allowed File Types', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="text" name="mavlers_contact_form_settings[file_upload_settings][allowed_file_types]" 
                                   value="<?php echo esc_attr(implode(',', $settings['file_upload_settings']['allowed_file_types'])); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Comma-separated list of file extensions (e.g., jpg, png, pdf)', 'mavlers-contact-form'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Upload Directory', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="text" name="mavlers_contact_form_settings[file_upload_settings][upload_directory]" 
                                   value="<?php echo esc_attr($settings['file_upload_settings']['upload_directory']); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Directory name inside wp-content/uploads/ where files will be stored', 'mavlers-contact-form'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle form submission
    $('#mavlers-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_save_settings',
                nonce: $('#mavlers_nonce').val(),
                settings: formData
            },
            success: function(response) {
                if (response.success) {
                    alert('Settings saved successfully!');
                } else {
                    alert('Error saving settings: ' + response.data);
                }
            },
            error: function() {
                alert('Error saving settings. Please try again.');
            }
        });
    });
});
</script> 