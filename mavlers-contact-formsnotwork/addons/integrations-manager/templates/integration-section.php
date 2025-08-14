<?php
/**
 * Form Integration Section Template
 * 
 * Displays available integrations for form-specific configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current form ID (prioritize URL parameter)
$form_id = 0;
if (isset($_GET['id']) && $_GET['id']) {
    // Form builder edit page: ?page=mavlers-contact-forms&action=edit&id=1
    $form_id = intval($_GET['id']);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Integration Section: Form ID from $_GET[id]: ' . $form_id);
    }
} elseif (isset($GLOBALS['mavlers_cf_current_form_id'])) {
    // Global context
    $form_id = intval($GLOBALS['mavlers_cf_current_form_id']);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Integration Section: Form ID from global: ' . $form_id);
    }
} else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Integration Section: No form ID found');
    }
}

// Get the integrations manager
use MavlersCF\Integrations\Core\Plugin;

try {
    $plugin = Plugin::getInstance();
    $registry = $plugin->getRegistry();
    $integrations = $registry->getAll();
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Integration Section Error: ' . $e->getMessage());
    }
    $integrations = [];
}

// Get saved form integration settings from custom meta table
$form_integration_settings = [];
if ($form_id) {
    global $wpdb;
    $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
    
    // Check if meta table exists, create if not
    if ($wpdb->get_var("SHOW TABLES LIKE '$meta_table'") != $meta_table) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $meta_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    $meta_value = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
        $form_id,
        '_mavlers_cf_integrations'
    ));
    
    if ($meta_value) {
        $form_integration_settings = json_decode($meta_value, true);
        if (!is_array($form_integration_settings)) {
            $form_integration_settings = [];
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Integration Section: Loaded settings from meta table: ' . print_r($form_integration_settings, true));
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Integration Section: No meta value found for form ID: ' . $form_id);
        }
        $form_integration_settings = [];
    }
    
    // Debug: Check if Mailchimp settings exist
    if (isset($form_integration_settings['mailchimp'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Integration Section: Mailchimp settings found: ' . print_r($form_integration_settings['mailchimp'], true));
            error_log('Integration Section: Mailchimp enabled: ' . (isset($form_integration_settings['mailchimp']['enabled']) ? ($form_integration_settings['mailchimp']['enabled'] ? 'true' : 'false') : 'not set'));
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Integration Section: No Mailchimp settings found in form_integration_settings');
        }
    }
}

// Enqueue integration-specific CSS and JS
if (!empty($integrations)) {
    foreach ($integrations as $integration) {
        $integration_id = $integration->getId();
        
        // Enqueue CSS
        $css_file = MAVLERS_CF_INTEGRATIONS_URL . "assets/css/admin/{$integration_id}-form.css";
        $css_path = MAVLERS_CF_INTEGRATIONS_DIR . "assets/css/admin/{$integration_id}-form.css";
        
        if (file_exists($css_path)) {
            wp_enqueue_style(
                "mavlers-cf-{$integration_id}-form",
                $css_file,
                [],
                filemtime($css_path)
            );
        }
        
        // Enqueue JS
        $js_file = MAVLERS_CF_INTEGRATIONS_URL . "assets/js/admin/{$integration_id}-form.js";
        $js_path = MAVLERS_CF_INTEGRATIONS_DIR . "assets/js/admin/{$integration_id}-form.js";
        
        if (file_exists($js_path)) {
            wp_enqueue_script(
                "mavlers-cf-{$integration_id}-form",
                $js_file,
                ['jquery'],
                filemtime($js_path),
                true
            );
            
            // Add localization data for Mailchimp
            if ($integration_id === 'mailchimp') {
                wp_localize_script("mavlers-cf-{$integration_id}-form", 'mavlersCFMailchimp', [
                    'nonce' => wp_create_nonce('mavlers_cf_nonce'),
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'strings' => [
                        'testing' => __('Testing...', 'mavlers-contact-forms'),
                        'connected' => __('Connected', 'mavlers-contact-forms'),
                        'disconnected' => __('Disconnected', 'mavlers-contact-forms'),
                        'testConnection' => __('Test Connection', 'mavlers-contact-forms'),
                        'savingSettings' => __('Saving...', 'mavlers-contact-forms'),
                        'settingsSaved' => __('Settings saved successfully!', 'mavlers-contact-forms'),
                        'connectionFailed' => __('Connection failed', 'mavlers-contact-forms'),
                        'selectAudience' => __('Select an audience...', 'mavlers-contact-forms'),
                        'noAudiences' => __('No audiences found', 'mavlers-contact-forms'),
                        'errorLoadingAudiences' => __('Error loading audiences', 'mavlers-contact-forms')
                    ]
                ]);
            }
        }
    }
}
?>

<div class="form-integrations-section">
    <?php if (!empty($integrations)): ?>
        
        <div class="integration-setting">
            <h4><?php esc_html_e('Available Integrations', 'mavlers-contact-forms'); ?></h4>
            <p class="description"><?php esc_html_e('Configure third-party integrations for this form.', 'mavlers-contact-forms'); ?></p>
        </div>

        <?php foreach ($integrations as $integration): 
            $integration_id = $integration->getId();
            $integration_name = $integration->getName();
            $is_configured = $integration->isConfigured();
            $is_enabled = isset($form_integration_settings[$integration_id]['enabled']) ? $form_integration_settings[$integration_id]['enabled'] : false;
            
            // If there are any settings for this integration, consider it enabled
            if (!$is_enabled && isset($form_integration_settings[$integration_id]) && !empty($form_integration_settings[$integration_id])) {
                $is_enabled = true;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Integration Section: Auto-enabling ' . $integration_id . ' because settings exist');
                }
            }
            
            // Debug integration status
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Integration Section: Processing integration: ' . $integration_id);
                error_log('Integration Section: Is configured: ' . ($is_configured ? 'true' : 'false'));
                error_log('Integration Section: Is enabled: ' . ($is_enabled ? 'true' : 'false'));
                error_log('Integration Section: Settings for this integration: ' . print_r($form_integration_settings[$integration_id] ?? [], true));
            }
            ?>
            
            <div class="integration-setting integration-<?php echo esc_attr($integration_id); ?>">
                <div class="integration-header">
                    <div class="integration-icon">
                        <?php 
                        $icon = $integration->getIcon();
                        if (strpos($icon, 'dashicons-') === 0): ?>
                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-admin-plugins"></span>
                        <?php endif; ?>
                    </div>
                    <div class="integration-info">
                        <h4><?php echo esc_html($integration_name); ?> <?php esc_html_e('Integration', 'mavlers-contact-forms'); ?></h4>
                        <p class="description"><?php echo esc_html($integration->getDescription()); ?></p>
                    </div>
                    <div class="integration-status">
                        <?php if ($is_configured): ?>
                            <span class="status-badge configured">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('Connected', 'mavlers-contact-forms'); ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge unconfigured">
                                <span class="dashicons dashicons-warning"></span>
                                <?php esc_html_e('Not Configured', 'mavlers-contact-forms'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_configured): ?>
                    <div class="integration-controls">
                        <label class="integration-toggle">
                            <input type="checkbox" 
                                   class="integration-enable-checkbox" 
                                   data-integration="<?php echo esc_attr($integration_id); ?>"
                                   <?php checked($is_enabled); ?>>
                            <?php printf(esc_html__('Enable %s for this form', 'mavlers-contact-forms'), $integration_name); ?>
                        </label>
                        <!-- Debug info -->
                        <div style="display:none;" class="debug-info">
                            <p>Integration ID: <?php echo esc_attr($integration_id); ?></p>
                            <p>Is Enabled: <?php echo $is_enabled ? 'true' : 'false'; ?></p>
                            <p>Settings: <?php echo esc_html(print_r($form_integration_settings[$integration_id] ?? [], true)); ?></p>
                        </div>
                    </div>

                    <div class="integration-form-settings" id="<?php echo esc_attr($integration_id); ?>-form-settings" <?php echo !$is_enabled ? 'style="display: none;"' : ''; ?>>
                        <?php 
                        // Load integration-specific form settings template
                        $integration_template = MAVLERS_CF_INTEGRATIONS_DIR . "src/Integrations/" . ucfirst($integration_id) . "/templates/{$integration_id}-form-settings.php";
                        
                        // Debug template loading
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Integration Section: Loading template for ' . $integration_id);
                            error_log('Integration Section: Template path: ' . $integration_template);
                            error_log('Integration Section: MAVLERS_CF_INTEGRATIONS_DIR: ' . MAVLERS_CF_INTEGRATIONS_DIR);
                            error_log('Integration Section: File exists: ' . (file_exists($integration_template) ? 'yes' : 'no'));
                        }
                        
                        if (file_exists($integration_template)) {
                            // Pass the form ID to the template
                            $mailchimp_form_id = $form_id;
                            ${$integration_id . '_form_id'} = $form_id; // Also pass with integration-specific name
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('Integration Section: Including template: ' . $integration_template);
                            }
                            
                            // Add debug output for HubSpot
                            if ($integration_id === 'hubspot') {
                                echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
                                echo '<strong>Debug: HubSpot Template Loading</strong><br>';
                                echo 'Template path: ' . esc_html($integration_template) . '<br>';
                                echo 'Form ID: ' . esc_html($form_id) . '<br>';
                                echo 'Integration ID: ' . esc_html($integration_id) . '<br>';
                                echo '</div>';
                            }
                            
                            include $integration_template;
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('Integration Section: Template included successfully');
                            }
                            
                            // Add debug output for HubSpot after inclusion
                            if ($integration_id === 'hubspot') {
                                echo '<div style="background: #e8f5e8; padding: 10px; margin: 10px 0; border: 1px solid #4caf50;">';
                                echo '<strong>Debug: HubSpot Template Loaded Successfully</strong><br>';
                                echo 'Template was included without errors.<br>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p>' . sprintf(
                                esc_html__('Template not found: %s', 'mavlers-contact-forms'),
                                esc_html($integration_template)
                            ) . '</p>';
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('Integration Section: Template not found: ' . $integration_template);
                            }
                        }
                        ?>
                    </div>

                <?php else: ?>
                    <div class="integration-not-configured">
                        <p><?php printf(
                            esc_html__('%s is not configured. Please configure it in the %s before enabling it for this form.', 'mavlers-contact-forms'),
                            $integration_name,
                            '<a href="' . esc_url(admin_url('admin.php?page=mavlers-cf-integrations&tab=settings&integration=' . $integration_id)) . '">' . esc_html__('Integration Settings', 'mavlers-contact-forms') . '</a>'
                        ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php endforeach; ?>

    <?php else: ?>
        
        <div class="integration-setting">
            <h4><?php esc_html_e('No Integrations Available', 'mavlers-contact-forms'); ?></h4>
            <p class="description"><?php esc_html_e('No integrations are currently available. Please check your integrations manager configuration.', 'mavlers-contact-forms'); ?></p>
        </div>
        
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle integration enable/disable
    $('.integration-enable-checkbox').each(function() {
        var integration = $(this).data('integration');
        var isChecked = $(this).is(':checked');
    });
    
    // Handle integration enable/disable
    $('.integration-enable-checkbox').on('change', function() {
        var integration = $(this).data('integration');
        var isEnabled = $(this).is(':checked');
        var settingsPanel = $('#' + integration + '-form-settings');
        
        if (isEnabled) {
            settingsPanel.slideDown();
            
            // If this is Mailchimp and we have saved settings, load them
            if (integration === 'mailchimp' && window.mailchimpFormSettings) {
                // Trigger the Mailchimp form settings to load saved data
                if (typeof loadSavedSettings === 'function') {
                    loadSavedSettings();
                }
            }
        } else {
            settingsPanel.slideUp();
        }
        
        // Update form data for saving
        updateFormIntegrationData();
    });
    
    // Function to update integration data in the main form
    function updateFormIntegrationData() {
        var integrationData = {};
        
        $('.integration-enable-checkbox').each(function() {
            var integration = $(this).data('integration');
            var isEnabled = $(this).is(':checked');
            
            integrationData[integration] = {
                enabled: isEnabled
            };
            
            // Collect integration-specific settings
            var settingsPanel = $('#' + integration + '-form-settings');
            if (settingsPanel.length && isEnabled) {
                var integrationSettings = {};
                
                settingsPanel.find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    var value = $(this).val();
                    
                    if (name && name.indexOf(integration + '_') === 0) {
                        var fieldName = name.replace(integration + '_', '');
                        
                        if ($(this).is(':checkbox')) {
                            integrationSettings[fieldName] = $(this).is(':checked');
                        } else {
                            integrationSettings[fieldName] = value;
                        }
                    }
                });
                
                integrationData[integration] = Object.assign(integrationData[integration], integrationSettings);
            }
        });
        
        // Store in a hidden field for the main form to pick up
        var $hiddenField = $('#form-integration-data');
        if ($hiddenField.length === 0) {
            $hiddenField = $('<input type="hidden" id="form-integration-data" name="integration_settings">');
            $('.integration-setting').first().append($hiddenField);
        }
        
        $hiddenField.val(JSON.stringify(integrationData));
    }
    
    // Update data on any settings change
    $(document).on('change', '.integration-form-settings input, .integration-form-settings select, .integration-form-settings textarea', function() {
        updateFormIntegrationData();
    });
    
    // Initialize
    updateFormIntegrationData();
});
</script> 