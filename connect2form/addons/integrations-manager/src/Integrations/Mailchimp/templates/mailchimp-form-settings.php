<?php
/**
 * Mailchimp Form Settings Template
 *
 * Comprehensive form-specific Mailchimp integration settings for the form builder
 */

if (!defined('ABSPATH')) {
    exit;
}

// Note: Do not import built-in WordPress functions in template scope; they are global. Removing use-function imports to avoid runtime warnings.

// Initialize variables
$form_settings = [];
$form_id = 0;
$is_configured = false;
$global_settings = [];

// Get form ID from the integration section context
$form_id = 0;

// Read id from query in a read-only context; unslash before sanitization
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$c2f_mailchimp_id_raw = isset( $_GET['id'] ) ? wp_unslash( $_GET['id'] ) : '';
if ( $c2f_mailchimp_id_raw !== '' ) {
    // Form builder edit page: ?page=connect2form&action=edit&id=1
    $form_id = (int) absint( $c2f_mailchimp_id_raw );
} elseif (isset($mailchimp_form_id) && $mailchimp_form_id) {
    // Passed from integration section
    $form_id = intval($mailchimp_form_id);
} elseif (isset($_GET['form_id']) && $_GET['form_id']) {
    // Direct form_id parameter
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context; no state change
    $c2f_mailchimp_form_id_raw = wp_unslash( $_GET['form_id'] );
    $form_id = (int) absint( $c2f_mailchimp_form_id_raw );
} elseif (isset($GLOBALS['connect2form_current_form_id'])) {
    // Global context
    $form_id = (int) absint( $GLOBALS['connect2form_current_form_id'] );
}

// Get the Mailchimp integration instance - Updated for new system
try {
    // Try to get the integration from the new system
    $mailchimp_integration = null;
    
    // Method 1: Try to get from the new registry
    if (class_exists('Connect2Form\Integrations\Core\Plugin')) {
        $plugin = Connect2Form\Integrations\Core\Plugin::getInstance();
        if ($plugin && method_exists($plugin, 'getRegistry')) {
            $registry = $plugin->getRegistry();
            if ($registry && method_exists($registry, 'get')) {
                $mailchimp_integration = $registry->get('mailchimp');
            }
        }
    }
    
    // Method 2: Try to get from the old system
    if (!$mailchimp_integration && class_exists('Connect2Form\Integrations\Core\Plugin')) {
        $plugin = Connect2Form\Integrations\Core\Plugin::getInstance();
        $registry = $plugin->getRegistry();
        $mailchimp_integration = $registry->get('mailchimp');
    }
    
    if (!$mailchimp_integration) {
        throw new Exception( esc_html__( 'Mailchimp integration not found.', 'connect2form' ) );
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
        $global_settings = get_option('connect2form_integrations_global', []);
        $global_settings = $global_settings['mailchimp'] ?? [];
    }
    
   
    
    // Get form-specific settings from multiple possible sources
    $form_settings = [];
    if ($form_id) {
        // Try multiple sources for form settings
        $found_settings = false;
        
        // Method 1: Try post meta FIRST (where complete settings are stored)
        $post_meta = get_post_meta($form_id, '_connect2form_integrations', true);
        if ($post_meta && isset($post_meta['mailchimp'])) {
            $form_settings = $post_meta['mailchimp'];
            $found_settings = true;
        }
        
        // Method 2: Try custom meta table as fallback
        if (!$found_settings) {
            // Use service class instead of direct database call
            if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
                $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
                $meta_value = $service_manager->database()->getFormMeta($form_id, '_connect2form_integrations');
                
                if ($meta_value) {
                    $integration_settings = json_decode($meta_value, true);
                    if ($integration_settings && isset($integration_settings['mailchimp'])) {
                        $form_settings = $integration_settings['mailchimp'];
                        $found_settings = true;
                    }
                }
            } else {
                // Fallback to direct database call if service not available
                global $wpdb;
                $meta_table = $wpdb->prefix . 'connect2form_form_meta';
                // Validate identifier before interpolation
                if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! \c2f_is_valid_prefixed_table( $meta_table ) ) {
                    $meta_value = '';
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- template read-only query
                    $meta_value = $wpdb->get_var($wpdb->prepare(
                        "SELECT meta_value FROM `{$meta_table}` WHERE form_id = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated identifier
                        $form_id,
                        '_connect2form_integrations'
                    ));
                }
                
                if ($meta_value) {
                    $integration_settings = json_decode($meta_value, true);
                    if ($integration_settings && isset($integration_settings['mailchimp'])) {
                        $form_settings = $integration_settings['mailchimp'];
                        $found_settings = true;
                    }
                }
            }
        }
        
        // Method 3: Try options table as final fallback
        if (!$found_settings) {
            $option_key = "connect2form_mailchimp_form_{$form_id}";
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

<div class="mailchimp-form-settings" data-form-id="<?php echo esc_attr($form_id); ?>">
    <?php if (!$is_configured): ?>
        <!-- Not Connected Message -->
        <div class="integration-not-connected">
            <div class="not-connected-icon">
                <span class="dashicons dashicons-email-alt"></span>
            </div>
            <div class="not-connected-content">
                <h4><?php echo esc_html__('Mailchimp Not Connected', 'connect2form'); ?></h4>
                <p><?php echo esc_html__('Please configure your Mailchimp API key in the global settings first.', 'connect2form'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-integrations&tab=settings&integration=mailchimp')); ?>" class="button button-primary">
                    <?php echo esc_html__('Configure Mailchimp', 'connect2form'); ?>
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
                        <span><?php echo esc_html__('Mailchimp Integration', 'connect2form'); ?></span>
                    </div>
                    <div class="integration-status connected">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo esc_html__('Connected', 'connect2form'); ?>
                    </div>
                </div>
                
                <div class="integration-toggle">
                    <span class="toggle-label">
                        <?php echo esc_html__('Configured and Ready', 'connect2form'); ?>
                    </span>
                </div>
            </div>

            <div class="integration-settings" id="mailchimp_settings">
                <form id="mailchimp-form-settings">
                    <?php wp_nonce_field('connect2form_nonce', 'mailchimp_form_nonce'); ?>
                    <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>" />

                    <!-- Audience Selection -->
                    <div class="setting-row">
                        <label for="mailchimp_audience" class="setting-label">
                            <?php echo esc_html__('Mailchimp Audience', 'connect2form'); ?>
                            <span class="required">*</span>
                        </label>
                        <div class="setting-control">
                            <select id="mailchimp_audience" name="audience_id" class="setting-select" required>
                                <option value=""><?php echo esc_html__('Select an audience...', 'connect2form'); ?></option>
                                <!-- Options loaded via AJAX -->
                            </select>
                            <button type="button" id="refresh_audiences" class="button button-secondary" title="<?php echo esc_attr__('Refresh audiences', 'connect2form'); ?>">
                                <span class="dashicons dashicons-update-alt"></span>
                            </button>
                        </div>
                        <p class="setting-help">
                            <?php echo esc_html__('Choose which Mailchimp audience to add subscribers to.', 'connect2form'); ?>
                        </p>
                    </div>

                    <!-- Double Opt-in -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <?php echo esc_html__('Double Opt-in', 'connect2form'); ?>
                        </label>
                        <div class="setting-control">
                            <label class="checkbox-label">
                                <input type="checkbox" 
                                       id="mailchimp_double_optin" 
                                       name="double_optin" 
                                       value="1" 
                                       <?php checked($form_settings['double_optin']); ?>
                                />
                                <?php echo esc_html__('Require email confirmation', 'connect2form'); ?>
                            </label>
                        </div>
                        <p class="setting-help">
                            <?php echo esc_html__('When enabled, subscribers will receive a confirmation email before being added to the audience.', 'connect2form'); ?>
                        </p>
                    </div>

                    <!-- Update Existing -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <?php echo esc_html__('Update Existing', 'connect2form'); ?>
                        </label>
                        <div class="setting-control">
                            <label class="checkbox-label">
                                <input type="checkbox" 
                                       id="mailchimp_update_existing" 
                                       name="update_existing" 
                                       value="1" 
                                       <?php checked($form_settings['update_existing']); ?>
                                />
                                <?php echo esc_html__('Update existing subscribers', 'connect2form'); ?>
                            </label>
                        </div>
                        <p class="setting-help">
                            <?php echo esc_html__('When enabled, existing subscribers will be updated instead of showing an error.', 'connect2form'); ?>
                        </p>
                    </div>

                    <!-- Tags -->
                    <div class="setting-row">
                        <label for="mailchimp_tags" class="setting-label">
                            <?php echo esc_html__('Tags', 'connect2form'); ?>
                        </label>
                        <div class="setting-control">
                            <input type="text" 
                                   id="mailchimp_tags" 
                                   name="tags" 
                                   value="<?php echo esc_attr($form_settings['tags']); ?>"
                                   placeholder="<?php echo esc_attr__('contact-form, website-signup', 'connect2form'); ?>"
                                   class="setting-input"
                            />
                        </div>
                        <p class="setting-help">
                            <?php echo esc_html__('Add tags to subscribers (comma-separated). Tags help organize your audience.', 'connect2form'); ?>
                        </p>
                    </div>

                    <!-- Mailchimp Field Mapping Section -->
                    <div class="setting-row mailchimp-field-mapping-section" id="mailchimp-field-mapping-section" style="display: none;">
                        <label class="setting-label">
                            <?php echo esc_html__('Mailchimp Field Mapping', 'connect2form'); ?>
                            <span class="setting-badge">Easy Mapping</span>
                        </label>
                        <div class="setting-control">
                            <div class="mailchimp-field-mapping-instructions">
                                <p><?php echo esc_html__('Map your form fields to Mailchimp fields below. Email mapping is required.', 'connect2form'); ?></p>
                            </div>
                            
                            <div class="mailchimp-field-mapping-table-container">
                                <table class="mailchimp-field-mapping-table">
                                    <thead>
                                        <tr>
                                            <th class="mailchimp-form-field-column"><?php echo esc_html__('Your Form Fields', 'connect2form'); ?></th>
                                            <th class="mailchimp-arrow-column"></th>
                                            <th class="mailchimp-field-column"><?php echo esc_html__('Mailchimp Fields', 'connect2form'); ?></th>
                                            <th class="mailchimp-status-column"><?php echo esc_html__('Status', 'connect2form'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="mailchimp-field-mapping-tbody">
                                        <!-- Populated by JavaScript -->
                                        <tr class="mailchimp-no-fields-row">
                                            <td colspan="4" style="text-align: center; padding: 20px; color: #666;">
                                                <?php echo esc_html__('Please select an audience to see field mapping options.', 'connect2form'); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mailchimp-field-mapping-actions">
                                <button type="button" id="mailchimp-auto-map-fields" class="button button-secondary">
                                    <span class="dashicons dashicons-randomize"></span>
                                    <?php echo esc_html__('Auto-Map Common Fields', 'connect2form'); ?>
                                </button>
                                <button type="button" id="mailchimp-clear-mappings" class="button button-secondary">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php echo esc_html__('Clear All Mappings', 'connect2form'); ?>
                                </button>
                                <div class="mailchimp-mapping-status" id="mailchimp-mapping-status">
                                    <span class="mailchimp-mapping-count">0 fields mapped</span>
                                </div>
                            </div>
                        </div>
                        <p class="setting-help">
                            <?php echo esc_html__('Map form fields to Mailchimp merge fields. Email mapping is required for the integration to work.', 'connect2form'); ?>
                        </p>
                    </div>

                    <!-- Save Button -->
                    <div class="setting-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php echo esc_html__('Save Mailchimp Settings', 'connect2form'); ?>
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
            <p><?php echo esc_html__('Loading...', 'connect2form'); ?></p>
        </div>
    </div>

    <!-- Message Area -->
    <div id="mailchimp_messages" class="integration-messages"></div>
</div>

<script>
// Store current form settings and form ID
window.mailchimpFormSettings = <?php echo wp_json_encode($form_settings ?: []); ?>;
window.mailchimpFormId = <?php echo (int)($form_id ?: 0); ?>;

// Add form-specific identifier to prevent conflicts
window.mailchimpFormSettings.form_id = <?php echo (int)($form_id ?: 0); ?>;
window.mailchimpFormSettings.timestamp = Date.now();

// Store global settings for JavaScript access
window.mailchimpGlobalSettings = <?php echo wp_json_encode($global_settings ?: []); ?>;

// Add form-specific data to the existing connect2formCFMailchimp object
if (window.connect2formCFMailchimp) {
    window.connect2formCFMailchimp.formId = <?php echo (int)($form_id ?: 0); ?>;
    window.connect2formCFMailchimp.globalSettings = <?php echo wp_json_encode($global_settings ?: []); ?>;
    window.connect2formCFMailchimp.strings = {
        testing: '<?php echo esc_js(__("Testing...", 'connect2form')); ?>',
        connected: '<?php echo esc_js(__("Connected", 'connect2form')); ?>',
        disconnected: '<?php echo esc_js(__("Disconnected", 'connect2form')); ?>',
        testConnection: '<?php echo esc_js(__("Test Connection", 'connect2form')); ?>',
        savingSettings: '<?php echo esc_js(__("Saving...", 'connect2form')); ?>',
        settingsSaved: '<?php echo esc_js(__("Settings saved successfully!", 'connect2form')); ?>',
        connectionFailed: '<?php echo esc_js(__("Connection failed", 'connect2form')); ?>',
        selectAudience: '<?php echo esc_js(__("Select an audience...", 'connect2form')); ?>',
        loadingFields: '<?php echo esc_js(__("Loading fields...", 'connect2form')); ?>',
        fieldsLoaded: '<?php echo esc_js(__("Fields loaded successfully", 'connect2form')); ?>',
        noFieldsFound: '<?php echo esc_js(__("No fields found", 'connect2form')); ?>',
        networkError: '<?php echo esc_js(__("Network error", 'connect2form')); ?>',
        mappingSaved: '<?php echo esc_js(__("Field mapping saved successfully", 'connect2form')); ?>',
        mappingFailed: '<?php echo esc_js(__("Failed to save field mapping", 'connect2form')); ?>',
        autoMappingComplete: '<?php echo esc_js(__("Auto-mapping completed", 'connect2form')); ?>',
        clearMappingsConfirm: '<?php echo esc_js(__("Are you sure you want to clear all mappings?", 'connect2form')); ?>'
    };
}
</script> 

