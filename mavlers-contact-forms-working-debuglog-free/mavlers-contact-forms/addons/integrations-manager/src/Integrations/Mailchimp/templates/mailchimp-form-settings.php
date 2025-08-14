<?php
/**
 * Mailchimp Form Settings Template
 *
 * Comprehensive form-specific Mailchimp integration settings for the form builder
 */

if (!defined('ABSPATH')) {
    exit;
}

// Initialize variables
$form_settings = [];
$form_id = 0;
$is_configured = false;
$global_settings = [];

// Get form ID from the integration section context
$form_id = 0;

if (isset($_GET['id']) && $_GET['id']) {
    // Form builder edit page: ?page=mavlers-contact-forms&action=edit&id=1
    $form_id = intval($_GET['id']);
} elseif (isset($mailchimp_form_id) && $mailchimp_form_id) {
    // Passed from integration section
    $form_id = intval($mailchimp_form_id);
} elseif (isset($_GET['form_id']) && $_GET['form_id']) {
    // Direct form_id parameter
    $form_id = intval($_GET['form_id']);
} elseif (isset($GLOBALS['mavlers_cf_current_form_id'])) {
    // Global context
    $form_id = intval($GLOBALS['mavlers_cf_current_form_id']);
}

// Get the Mailchimp integration instance - Updated for new system
try {
    // Try to get the integration from the new system
    $mailchimp_integration = null;
    
    // Method 1: Try to get from the new registry
    if (class_exists('MavlersCF\Integrations\Core\Plugin')) {
        $plugin = MavlersCF\Integrations\Core\Plugin::getInstance();
        if ($plugin && method_exists($plugin, 'getRegistry')) {
            $registry = $plugin->getRegistry();
            if ($registry && method_exists($registry, 'get')) {
                $mailchimp_integration = $registry->get('mailchimp');
            }
        }
    }
    
    // Method 2: Try to get from the old system
    if (!$mailchimp_integration && class_exists('MavlersCF\Integrations\Core\Plugin')) {
        $plugin = MavlersCF\Integrations\Core\Plugin::getInstance();
        $registry = $plugin->getRegistry();
        $mailchimp_integration = $registry->get('mailchimp');
    }
    
    if (!$mailchimp_integration) {
        throw new Exception('Mailchimp integration not found');
    }
    
    // Check if integration is configured using the new method
    $is_configured = false;
    if (method_exists($mailchimp_integration, 'is_globally_connected')) {
        $is_configured = $mailchimp_integration->is_globally_connected();
    } elseif (method_exists($mailchimp_integration, 'isConfigured')) {
        $is_configured = $mailchimp_integration->isConfigured();
    }
    
    // Get global settings using the new method
    $global_settings = [];
    if (method_exists($mailchimp_integration, 'get_global_settings')) {
        $global_settings = $mailchimp_integration->get_global_settings();
    } else {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        $global_settings = $global_settings['mailchimp'] ?? [];
    }
    
    // Debug global settings
   // error_log('Mailchimp Form Settings: Global settings: ' . print_r($global_settings, true));
   // error_log('Mailchimp Form Settings: Has API key: ' . (!empty($global_settings['api_key']) ? 'Yes' : 'No'));
    
    // Add visible debug output
    if (current_user_can('manage_options')) {
        echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffeaa7; font-size: 12px;">';
        echo '<strong>Debug: Global Settings</strong><br>';
        echo 'Has API key: ' . (!empty($global_settings['api_key']) ? 'Yes' : 'No') . '<br>';
        echo 'Global settings: ' . esc_html(print_r($global_settings, true)) . '<br>';
        echo 'Is configured: ' . ($is_configured ? 'Yes' : 'No') . '<br>';
        echo '</div>';
    }
    
    // Get form-specific settings from multiple possible sources
    $form_settings = [];
    if ($form_id) {
        // Try multiple sources for form settings
        $found_settings = false;
        
        // Method 1: Try post meta FIRST (where complete settings are stored)
        $post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        if ($post_meta && isset($post_meta['mailchimp'])) {
            $form_settings = $post_meta['mailchimp'];
            $found_settings = true;
        }
        
        // Method 2: Try custom meta table as fallback
        if (!$found_settings) {
            global $wpdb;
            $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
            $meta_value = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
                $form_id,
                '_mavlers_cf_integrations'
            ));
            
            if ($meta_value) {
                $integration_settings = json_decode($meta_value, true);
                if ($integration_settings && isset($integration_settings['mailchimp'])) {
                    $form_settings = $integration_settings['mailchimp'];
                    $found_settings = true;
                }
            }
        }
        
        // Method 3: Try options table as final fallback
        if (!$found_settings) {
            $option_key = "mavlers_cf_mailchimp_form_{$form_id}";
            $option_settings = get_option($option_key, []);
            if (!empty($option_settings)) {
                $form_settings = $option_settings;
                $found_settings = true;
            }
        }
    }
    
    // Default form settings
    $default_settings = [
        'enabled' => false,
        'audience_id' => '',
        'double_optin' => true,
        'update_existing' => false,
        'tags' => '',
        'field_mapping' => [],
        'custom_fields' => []
    ];
    
    // Auto-enable if settings exist but enabled field is missing
    if (!isset($form_settings['enabled']) && !empty($form_settings)) {
        $form_settings['enabled'] = true;
    }
    
    $form_settings = array_merge($default_settings, $form_settings);
    
} catch (Exception $e) {
    $is_configured = false;
    $form_settings = ['enabled' => false];
}
?>

<div class="mailchimp-form-settings" data-form-id="<?php echo $form_id; ?>">
    <?php if (!$is_configured): ?>
        <!-- Not Connected Message -->
        <div class="integration-not-connected">
            <div class="not-connected-icon">
                <span class="dashicons dashicons-email-alt"></span>
            </div>
            <div class="not-connected-content">
                <h4><?php _e('Mailchimp Not Connected', 'mavlers-contact-forms'); ?></h4>
                <p><?php _e('Please configure your Mailchimp API key in the global settings first.', 'mavlers-contact-forms'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mavlers-cf-integrations&tab=settings&integration=mailchimp'); ?>" class="button button-primary">
                    <?php _e('Configure Mailchimp', 'mavlers-contact-forms'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Form Settings -->
        <div class="integration-form-settings">
            <div class="integration-header">
                <div class="integration-info">
                    <div class="integration-title">
                        <span class="dashicons dashicons-email-alt"></span>
                        <span><?php _e('Mailchimp Integration', 'mavlers-contact-forms'); ?></span>
                    </div>
                    <div class="integration-status connected">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Connected', 'mavlers-contact-forms'); ?>
                    </div>
                </div>
                
                <div class="integration-toggle">
                    <span class="toggle-label">
                        <?php _e('Configured and Ready', 'mavlers-contact-forms'); ?>
                    </span>
                </div>
            </div>

            <div class="integration-settings" id="mailchimp_settings">
                <form id="mailchimp-form-settings">
                    <?php wp_nonce_field('mavlers_cf_nonce', 'mailchimp_form_nonce'); ?>
                    <input type="hidden" name="form_id" value="<?php echo $form_id; ?>" />

                    <!-- Audience Selection -->
                    <div class="setting-row">
                        <label for="mailchimp_audience" class="setting-label">
                            <?php _e('Mailchimp Audience', 'mavlers-contact-forms'); ?>
                            <span class="required">*</span>
                        </label>
                        <div class="setting-control">
                            <select id="mailchimp_audience" name="audience_id" class="setting-select" required>
                                <option value=""><?php _e('Select an audience...', 'mavlers-contact-forms'); ?></option>
                                <!-- Options loaded via AJAX -->
                            </select>
                            <button type="button" id="refresh_audiences" class="button button-secondary" title="<?php _e('Refresh audiences', 'mavlers-contact-forms'); ?>">
                                <span class="dashicons dashicons-update-alt"></span>
                            </button>
                        </div>
                        <p class="setting-help">
                            <?php _e('Choose which Mailchimp audience to add subscribers to.', 'mavlers-contact-forms'); ?>
                        </p>
                    </div>

                    <!-- Double Opt-in -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <?php _e('Double Opt-in', 'mavlers-contact-forms'); ?>
                        </label>
                        <div class="setting-control">
                            <label class="checkbox-label">
                                <input type="checkbox" 
                                       id="mailchimp_double_optin" 
                                       name="double_optin" 
                                       value="1" 
                                       <?php checked($form_settings['double_optin']); ?>
                                />
                                <?php _e('Require email confirmation', 'mavlers-contact-forms'); ?>
                            </label>
                        </div>
                        <p class="setting-help">
                            <?php _e('When enabled, subscribers will receive a confirmation email before being added to the audience.', 'mavlers-contact-forms'); ?>
                        </p>
                    </div>

                    <!-- Update Existing -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <?php _e('Update Existing', 'mavlers-contact-forms'); ?>
                        </label>
                        <div class="setting-control">
                            <label class="checkbox-label">
                                <input type="checkbox" 
                                       id="mailchimp_update_existing" 
                                       name="update_existing" 
                                       value="1" 
                                       <?php checked($form_settings['update_existing']); ?>
                                />
                                <?php _e('Update existing subscribers', 'mavlers-contact-forms'); ?>
                            </label>
                        </div>
                        <p class="setting-help">
                            <?php _e('When enabled, existing subscribers will be updated instead of showing an error.', 'mavlers-contact-forms'); ?>
                        </p>
                    </div>

                    <!-- Tags -->
                    <div class="setting-row">
                        <label for="mailchimp_tags" class="setting-label">
                            <?php _e('Tags', 'mavlers-contact-forms'); ?>
                        </label>
                        <div class="setting-control">
                            <input type="text" 
                                   id="mailchimp_tags" 
                                   name="tags" 
                                   value="<?php echo esc_attr($form_settings['tags']); ?>"
                                   placeholder="<?php _e('contact-form, website-signup', 'mavlers-contact-forms'); ?>"
                                   class="setting-input"
                            />
                        </div>
                        <p class="setting-help">
                            <?php _e('Add tags to subscribers (comma-separated). Tags help organize your audience.', 'mavlers-contact-forms'); ?>
                        </p>
                    </div>

                    <!-- Mailchimp Field Mapping Section -->
                    <div class="setting-row mailchimp-field-mapping-section" id="mailchimp-field-mapping-section" style="display: none;">
                        <label class="setting-label">
                            <?php _e('Mailchimp Field Mapping', 'mavlers-contact-forms'); ?>
                            <span class="setting-badge">Easy Mapping</span>
                        </label>
                        <div class="setting-control">
                            <div class="mailchimp-field-mapping-instructions">
                                <p><?php _e('Map your form fields to Mailchimp fields below. Email mapping is required.', 'mavlers-contact-forms'); ?></p>
                            </div>
                            
                            <div class="mailchimp-field-mapping-table-container">
                                <table class="mailchimp-field-mapping-table">
                                    <thead>
                                        <tr>
                                            <th class="mailchimp-form-field-column"><?php _e('Your Form Fields', 'mavlers-contact-forms'); ?></th>
                                            <th class="mailchimp-arrow-column"></th>
                                            <th class="mailchimp-field-column"><?php _e('Mailchimp Fields', 'mavlers-contact-forms'); ?></th>
                                            <th class="mailchimp-status-column"><?php _e('Status', 'mavlers-contact-forms'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="mailchimp-field-mapping-tbody">
                                        <!-- Populated by JavaScript -->
                                        <tr class="mailchimp-no-fields-row">
                                            <td colspan="4" style="text-align: center; padding: 20px; color: #666;">
                                                <?php _e('Please select an audience to see field mapping options.', 'mavlers-contact-forms'); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mailchimp-field-mapping-actions">
                                <button type="button" id="mailchimp-auto-map-fields" class="button button-secondary">
                                    <span class="dashicons dashicons-randomize"></span>
                                    <?php _e('Auto-Map Common Fields', 'mavlers-contact-forms'); ?>
                                </button>
                                <button type="button" id="mailchimp-clear-mappings" class="button button-secondary">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Clear All Mappings', 'mavlers-contact-forms'); ?>
                                </button>
                                <div class="mailchimp-mapping-status" id="mailchimp-mapping-status">
                                    <span class="mailchimp-mapping-count">0 fields mapped</span>
                                </div>
                            </div>
                        </div>
                        <p class="setting-help">
                            <?php _e('Map form fields to Mailchimp merge fields. Email mapping is required for the integration to work.', 'mavlers-contact-forms'); ?>
                        </p>
                    </div>

                    <!-- Save Button -->
                    <div class="setting-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Mailchimp Settings', 'mavlers-contact-forms'); ?>
                        </button>
                        
                        <div class="save-status" id="save_status"></div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div id="mailchimp_loading" class="integration-loading" style="display: none;">
        <div class="loading-spinner">
            <span class="dashicons dashicons-update-alt spinning"></span>
            <p><?php _e('Loading...', 'mavlers-contact-forms'); ?></p>
        </div>
    </div>

    <!-- Message Area -->
    <div id="mailchimp_messages" class="integration-messages"></div>
</div>

<script>
// Store current form settings and form ID
window.mailchimpFormSettings = <?php echo json_encode($form_settings ?: []); ?>;
window.mailchimpFormId = <?php echo $form_id ?: 0; ?>;

// Add form-specific identifier to prevent conflicts
window.mailchimpFormSettings.form_id = <?php echo $form_id ?: 0; ?>;
window.mailchimpFormSettings.timestamp = Date.now();

// Store global settings for JavaScript access
window.mailchimpGlobalSettings = <?php echo json_encode($global_settings ?: []); ?>;

// Create mavlersCFMailchimp object manually since wp_localize_script is not available in this context
window.mavlersCFMailchimp = {
    nonce: '<?php echo wp_create_nonce("mavlers_cf_nonce"); ?>',
    ajaxUrl: '<?php echo admin_url("admin-ajax.php"); ?>',
    formId: <?php echo $form_id ?: 0; ?>,
    globalSettings: <?php echo json_encode($global_settings ?: []); ?>,
    strings: {
        testing: '<?php echo esc_js(__("Testing...", "mavlers-contact-forms")); ?>',
        connected: '<?php echo esc_js(__("Connected", "mavlers-contact-forms")); ?>',
        disconnected: '<?php echo esc_js(__("Disconnected", "mavlers-contact-forms")); ?>',
        testConnection: '<?php echo esc_js(__("Test Connection", "mavlers-contact-forms")); ?>',
        savingSettings: '<?php echo esc_js(__("Saving...", "mavlers-contact-forms")); ?>',
        settingsSaved: '<?php echo esc_js(__("Settings saved successfully!", "mavlers-contact-forms")); ?>',
        connectionFailed: '<?php echo esc_js(__("Connection failed", "mavlers-contact-forms")); ?>',
        selectAudience: '<?php echo esc_js(__("Select an audience...", "mavlers-contact-forms")); ?>',
        loadingFields: '<?php echo esc_js(__("Loading fields...", "mavlers-contact-forms")); ?>',
        fieldsLoaded: '<?php echo esc_js(__("Fields loaded successfully", "mavlers-contact-forms")); ?>',
        noFieldsFound: '<?php echo esc_js(__("No fields found", "mavlers-contact-forms")); ?>',
        networkError: '<?php echo esc_js(__("Network error", "mavlers-contact-forms")); ?>',
        mappingSaved: '<?php echo esc_js(__("Field mapping saved successfully", "mavlers-contact-forms")); ?>',
        mappingFailed: '<?php echo esc_js(__("Failed to save field mapping", "mavlers-contact-forms")); ?>',
        autoMappingComplete: '<?php echo esc_js(__("Auto-mapping completed", "mavlers-contact-forms")); ?>',
        clearMappingsConfirm: '<?php echo esc_js(__("Are you sure you want to clear all mappings?", "mavlers-contact-forms")); ?>'
    }
};
</script> 