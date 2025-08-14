<?php
/**
 * Form Integration Section Template
 * 
 * Displays available integrations for form-specific configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current form ID
$form_id = 0;
if (isset($_GET['id']) && $_GET['id']) {
    $form_id = intval($_GET['id']);
} elseif (isset($GLOBALS['mavlers_cf_current_form_id'])) {
    $form_id = intval($GLOBALS['mavlers_cf_current_form_id']);
}

// Get the integrations manager
use MavlersCF\Integrations\Core\Plugin;

$integrations = [];
if (class_exists('MavlersCF\Integrations\Core\Plugin')) {
    try {
        $plugin = Plugin::getInstance();
        $registry = $plugin->getRegistry();
        $integrations = $registry->getAll();
    } catch (Exception $e) {
        $integrations = [];
    }
}

// Get saved form integration settings
$form_integration_settings = [];
if ($form_id) {
    global $wpdb;
    $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
    
    // Create meta table if it doesn't exist
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
    } else {
        $form_integration_settings = [];
    }
}

// Enqueue integration assets
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
            
            // Add localization for Mailchimp
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
            
            // Auto-enable if settings exist
            if (!$is_enabled && isset($form_integration_settings[$integration_id]) && !empty($form_integration_settings[$integration_id])) {
                $is_enabled = true;
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
                    </div>

                    <div class="integration-form-settings" id="<?php echo esc_attr($integration_id); ?>-form-settings" <?php echo !$is_enabled ? 'style="display: none;"' : ''; ?>>
                        <?php 
                        // Load integration-specific form settings template
                        $integration_template = MAVLERS_CF_INTEGRATIONS_DIR . "src/Integrations/" . ucfirst($integration_id) . "/templates/{$integration_id}-form-settings.php";
                        
                        if (file_exists($integration_template)) {
                            // Pass the form ID to the template
                            $mailchimp_form_id = $form_id;
                            ${$integration_id . '_form_id'} = $form_id;
                            
                            include $integration_template;
                        } else {
                            echo '<p>' . sprintf(
                                esc_html__('Template not found: %s', 'mavlers-contact-forms'),
                                esc_html($integration_template)
                            ) . '</p>';
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
    $('.integration-enable-checkbox').on('change', function() {
        var integration = $(this).data('integration');
        var isEnabled = $(this).is(':checked');
        var settingsPanel = $('#' + integration + '-form-settings');
        
        if (isEnabled) {
            settingsPanel.slideDown();
            
            // Load saved settings for Mailchimp
            if (integration === 'mailchimp' && window.mailchimpFormSettings) {
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
    
    // Update integration data in the main form
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
        
        // Store in hidden field for the main form
        var $hiddenField = $('#form-integration-data');
        if ($hiddenField.length === 0) {
            $hiddenField = $('<input type="hidden" id="form-integration-data" name="integration_settings">');
            $('.integration-setting').first().append($hiddenField);
        }
        
        $hiddenField.val(JSON.stringify(integrationData));
    }
    
    // Update data on settings change
    $(document).on('change', '.integration-form-settings input, .integration-form-settings select, .integration-form-settings textarea', function() {
        updateFormIntegrationData();
    });
    
    // Initialize
    updateFormIntegrationData();
});
</script> 