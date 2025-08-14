<?php
/**
 * Individual Integration Settings Template
 * 
 * Displays settings form for a specific integration
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($integration)) {
    return;
}

$integration_id = $integration->getId();
$integration_name = $integration->getName();
$auth_fields = $integration->getAuthFields();
$settings_fields = $integration->getSettingsFields();

// Get current global settings for this integration
$global_settings = get_option('connect2form_integrations_global', []);
$global_settings = $global_settings[$integration_id] ?? [];

// If this is Mailchimp, ensure its assets are loaded
if ($integration_id === 'mailchimp') {
    // Enqueue Mailchimp specific assets
    wp_enqueue_style(
        'connect2form-mailchimp',
        CONNECT2FORM_INTEGRATIONS_URL . 'assets/css/admin/mailchimp.css',
        [],
        '2.0.0'
    );

    wp_enqueue_script(
        'connect2form-mailchimp',
        CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/mailchimp.js',
        ['jquery', 'wp-util'],
        '2.0.0',
        true
    );

    // Localize script with necessary data
    wp_localize_script('connect2form-mailchimp', 'connect2formCFMailchimp', [
        'nonce' => wp_create_nonce('connect2form_nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'strings' => [
            'testing' => __('Testing...', 'connect2form'),
            'connected' => __('Connected', 'connect2form'),
            'disconnected' => __('Disconnected', 'connect2form'),
            'testConnection' => __('Test Connection', 'connect2form'),
            'savingSettings' => __('Saving...', 'connect2form'),
            'settingsSaved' => __('Settings saved successfully!', 'connect2form'),
            'connectionFailed' => __('Connection failed', 'connect2form')
        ],
        'globalSettings' => $global_settings
    ]);
}

// If this is HubSpot, ensure its assets are loaded
if ($integration_id === 'hubspot') {
    // Enqueue HubSpot specific assets
    wp_enqueue_style(
        'connect2form-hubspot',
        CONNECT2FORM_INTEGRATIONS_URL . 'assets/css/admin/hubspot.css',
        [],
        '2.0.0'
    );

    wp_enqueue_script(
        'connect2form-hubspot',
        CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/hubspot-form.js',
        ['jquery', 'wp-util'],
        '2.0.0',
        true
    );

    // Localize script with necessary data
    wp_localize_script('connect2form-hubspot', 'connect2form_hubspot_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('connect2form_nonce'),
        'form_id' => get_the_ID()
    ]);
    
    // Also localize with standard nonce for compatibility
    wp_localize_script('connect2form-hubspot', 'connect2formHubspotCompat', array(
        'nonce' => wp_create_nonce('connect2form_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
?>

<div class="wrap">
    <h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-integrations&tab=settings')); ?>" 
           class="page-title-action">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
        </a>
        <?php /* translators: %s: Integration name (e.g. Mailchimp, HubSpot) */ ?>
        <?php echo esc_html(sprintf(__('%s Settings', 'connect2form'), $integration_name)); ?>
    </h1>
    
    <div class="connect2form-integration-settings">
        <div class="integration-header">
            <div class="integration-icon">
                <?php echo wp_kses_post($this->integration_icon($integration)); ?>
            </div>
            <div class="integration-meta">
                <h2><?php echo esc_html($integration_name); ?></h2>
                <p><?php echo esc_html($integration->getDescription()); ?></p>
                <div class="integration-status">
                    <?php if ($integration->isConfigured()): ?>
                        <span class="status-badge configured">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Connected', 'connect2form'); ?>
                        </span>
                    <?php else: ?>
                        <span class="status-badge unconfigured">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e('Not Connected', 'connect2form'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="settings-messages"></div>

        <!-- Integration-specific settings form -->
        <div id="<?php echo esc_attr($integration_id); ?>-settings-form" data-integration="<?php echo esc_attr($integration_id); ?>">
            <?php wp_nonce_field('connect2form_nonce', 'connect2form_nonce'); ?>
            
            <!-- Authentication Section -->
            <?php if (!empty($auth_fields)): ?>
                <div class="postbox">
                    <div class="postbox-header">
                        <h3><?php esc_html_e('Authentication', 'connect2form'); ?></h3>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <?php foreach ($auth_fields as $field): ?>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($field['id']); ?>">
                                            <?php echo esc_html($field['label']); ?>
                                            <?php if (!empty($field['required'])): ?>
                                                <span class="required">*</span>
                                            <?php endif; ?>
                                        </label>
                                    </th>
                                    <td>
                                        <?php 
                                        $field_value = $global_settings[$field['id']] ?? '';
                                        $field_type = $field['type'] ?? 'text';
                                        ?>
                                        
                                        <?php if ($field_type === 'password'): ?>
                                            <input type="password" 
                                                   id="<?php echo esc_attr($field['id']); ?>" 
                                                   name="<?php echo esc_attr($field['id']); ?>" 
                                                   value="<?php echo esc_attr($field_value); ?>" 
                                                   class="regular-text"
                                                   placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" />
                                        <?php elseif ($field_type === 'textarea'): ?>
                                            <textarea id="<?php echo esc_attr($field['id']); ?>" 
                                                      name="<?php echo esc_attr($field['id']); ?>" 
                                                      rows="4" 
                                                      class="large-text"
                                                      placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"><?php echo esc_textarea($field_value); ?></textarea>
                                        <?php else: ?>
                                            <input type="text" 
                                                   id="<?php echo esc_attr($field['id']); ?>" 
                                                   name="<?php echo esc_attr($field['id']); ?>" 
                                                   value="<?php echo esc_attr($field_value); ?>" 
                                                   class="regular-text"
                                                   placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" />
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($field['description'])): ?>
                                            <p class="description"><?php echo esc_html($field['description']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        
                        <p class="submit">
                            <button type="button" id="test-connection" class="button button-secondary">
                                <span class="dashicons dashicons-admin-links"></span>
                                <?php esc_html_e('Test Connection', 'connect2form'); ?>
                            </button>
                            <span id="connection-status"></span>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Integration Settings Section -->
            <?php if (!empty($settings_fields)): ?>
                <div class="postbox">
                    <div class="postbox-header">
                        <h3><?php esc_html_e('Integration Settings', 'connect2form'); ?></h3>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <?php foreach ($settings_fields as $field): ?>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($field['id']); ?>">
                                            <?php echo esc_html($field['label']); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <?php 
                                        $field_value = $global_settings[$field['id']] ?? $field['default'] ?? '';
                                        $field_type = $field['type'] ?? 'text';
                                        ?>
                                        
                                        <?php if ($field_type === 'checkbox'): ?>
                                            <input type="checkbox" 
                                                   id="<?php echo esc_attr($field['id']); ?>" 
                                                   name="<?php echo esc_attr($field['id']); ?>" 
                                                   value="1" 
                                                   <?php checked($field_value); ?> />
                                        <?php elseif ($field_type === 'select'): ?>
                                            <select id="<?php echo esc_attr($field['id']); ?>" 
                                                    name="<?php echo esc_attr($field['id']); ?>">
                                                <?php foreach ($field['options'] as $value => $label): ?>
                                                    <option value="<?php echo esc_attr($value); ?>" 
                                                            <?php selected($field_value, $value); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" 
                                                   id="<?php echo esc_attr($field['id']); ?>" 
                                                   name="<?php echo esc_attr($field['id']); ?>" 
                                                   value="<?php echo esc_attr($field_value); ?>" 
                                                   class="regular-text" />
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($field['description'])): ?>
                                            <p class="description"><?php echo esc_html($field['description']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Global Settings Only - Form-specific settings are handled in the form builder -->

            <p class="submit">
                <button type="button" 
                        id="save-<?php echo esc_attr($integration_id); ?>-settings" 
                        class="button button-primary save-integration-settings" 
                        data-integration="<?php echo esc_attr($integration_id); ?>">
                    <?php esc_html_e('Save Settings', 'connect2form'); ?>
                </button>
                
          
            </p>
        </div>
    </div>
</div>

<style>
.connect2form-integration-settings .integration-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    padding: 20px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.integration-header .integration-icon .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #666;
}

.integration-header .integration-icon img {
    max-width: 48px;
    max-height: 48px;
}

.integration-meta h2 {
    margin: 0 0 5px 0;
}

.integration-meta p {
    margin: 0 0 10px 0;
    color: #666;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.configured {
    background: #d4f3d4;
    color: #155724;
}

.status-badge.unconfigured {
    background: #fff3cd;
    color: #856404;
}

.status-badge .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.required {
    color: #d63638;
}

#connection-status {
    margin-left: 10px;
}

#connection-status.success {
    color: #00a32a;
}

#connection-status.error {
    color: #d63638;
}

.page-title-action {
    text-decoration: none;
    margin-right: 10px;
}

.page-title-action .dashicons {
    vertical-align: middle;
}

#settings-messages {
    margin-bottom: 20px;
}

.notice {
    background: #fff;
    border-left: 4px solid #00a32a;
    padding: 10px 15px;
    margin: 10px 0;
    border-radius: 3px;
}

.notice.notice-error {
    border-left-color: #d63638;
}

.notice.notice-warning {
    border-left-color: #dba617;
}
</style>

<script>
jQuery(document).ready(function($) {
    var integrationId = $('#<?php echo esc_js($integration_id); ?>-settings-form').data('integration');
    
    // Test connection functionality
    $('#test-connection').on('click', function() {
        var button = $(this);
        var status = $('#connection-status');
        var form = $('#<?php echo esc_js($integration_id); ?>-settings-form');
        
        button.prop('disabled', true).text('<?php esc_html_e('Testing...', 'connect2form'); ?>');
        status.removeClass('success error').text('');
        
        // Get credentials from form
        var credentials = {};
        form.find('input, textarea, select').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            if (name) {
                if ($field.attr('type') === 'checkbox') {
                    credentials[name] = $field.is(':checked') ? '1' : '0';
                } else {
                    credentials[name] = $field.val();
                }
            }
        });
        

        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'connect2form_test_<?php echo esc_js($integration_id); ?>_connection',
                nonce: $('[name="connect2form_nonce"]').val(),
                integration_id: integrationId,
                credentials: credentials
            },
            success: function(response) {

                
                if (response.success) {
                    status.addClass('success').text('<?php esc_html_e('Connection successful!', 'connect2form'); ?>');
                    
                    // Show connection details if available
                    if (response.data && response.data.account_name) {
                        status.text('Connected to: ' + response.data.account_name);
                    }
                    
                    // Show additional connection info if available
                    if (response.data && response.data.total_subscribers) {
                        status.text('Connected to: ' + response.data.account_name + ' (' + response.data.total_subscribers + ' subscribers)');
                    }
                } else {
                    status.addClass('error').text(response.data || '<?php esc_html_e('Connection failed', 'connect2form'); ?>');
                }
            },
            error: function(xhr, status, error) {

                $('#connection-status').addClass('error').text('<?php esc_html_e('Connection test failed', 'connect2form'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Test Connection', 'connect2form'); ?>');
            }
        });
    });
    
    // Save settings functionality
    $('.save-integration-settings').on('click', function() {
        var button = $(this);
        var integrationId = button.data('integration');
        var form = $('#<?php echo esc_js($integration_id); ?>-settings-form');
        
        button.prop('disabled', true).text('<?php esc_html_e('Saving...', 'connect2form'); ?>');
        
        // Get all form data
        var settings = {};
        var formFields = form.find('input, textarea, select');

        
        formFields.each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            var type = $field.attr('type');
            var value = $field.val();
            var isChecked = $field.is(':checked');
            

            
            if (name) {
                if (type === 'checkbox') {
                    settings[name] = isChecked ? '1' : '0';
                } else {
                    settings[name] = value;
                }
            }
        });
        


        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: '<?php echo esc_js($integration_id); ?>_save_global_settings',
                nonce: $('[name="connect2form_nonce"]').val(),
                integration_id: integrationId,
                settings: settings
            },
            beforeSend: function() {
                // Optional: Add loading state here
            },
            success: function(response) {

                
                if (response.success) {
                    // Show success message
                    var successMessage = '<?php esc_html_e('Settings saved successfully!', 'connect2form'); ?>';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            successMessage = response.data;
                        } else if (response.data.message) {
                            successMessage = response.data.message;
                        }
                    }
                    var message = '<div class="notice notice-success"><p>' + successMessage + '</p></div>';
                    $('#settings-messages').html(message);
                    
                    // Update connection status if needed
                    if (response.data && response.data.configured) {
                        $('.status-badge').removeClass('unconfigured').addClass('configured')
                            .html('<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Connected', 'connect2form'); ?>');
                    }
                } else {
                    // Show error message
                    var errorMessage = '<?php esc_html_e('Failed to save settings', 'connect2form'); ?>';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    var message = '<div class="notice notice-error"><p>' + errorMessage + '</p></div>';
                    $('#settings-messages').html(message);
                }
            },
            error: function(xhr, status, error) {

                var message = '<div class="notice notice-error"><p><?php esc_html_e('Failed to save settings', 'connect2form'); ?></p></div>';
                $('#settings-messages').html(message);
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Save Settings', 'connect2form'); ?>');
            }
        });
    });
    

});
</script> 
