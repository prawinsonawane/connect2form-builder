<?php
/**
 * HubSpot Form Settings Template - User-Friendly Version
 *
 * Clean, modern HubSpot integration settings with proper loading and success messages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get form ID
$form_id = 0;
if (isset($_GET['id']) && $_GET['id']) {
    $form_id = intval($_GET['id']);
} elseif (isset($hubspot_form_id) && $hubspot_form_id) {
    $form_id = intval($hubspot_form_id);
} elseif (isset($GLOBALS['mavlers_cf_current_form_id'])) {
    $form_id = intval($GLOBALS['mavlers_cf_current_form_id']);
}

// Get HubSpot integration instance
$hubspot_integration = null;
$is_configured = false;
$global_settings = [];

try {
    if (class_exists('MavlersCF\Integrations\Core\Plugin')) {
        $plugin = MavlersCF\Integrations\Core\Plugin::getInstance();
        if ($plugin && method_exists($plugin, 'getRegistry')) {
            $registry = $plugin->getRegistry();
            if ($registry && method_exists($registry, 'get')) {
                $hubspot_integration = $registry->get('hubspot');
            }
        }
    }
    
    if ($hubspot_integration) {
        $is_configured = method_exists($hubspot_integration, 'is_globally_connected') 
            ? $hubspot_integration->is_globally_connected() 
            : (method_exists($hubspot_integration, 'isConfigured') ? $hubspot_integration->isConfigured() : false);
        
        $global_settings = method_exists($hubspot_integration, 'get_global_settings') 
            ? $hubspot_integration->get_global_settings() 
            : (get_option('mavlers_cf_integrations_global', [])['hubspot'] ?? []);
    }
} catch (Exception $e) {
    $is_configured = false;
}

// Load form settings from custom meta table
$form_settings = [];
if ($form_id) {
    global $wpdb;
    $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
    
    // Check if meta table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$meta_table'") == $meta_table) {
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
            $form_id,
            '_mavlers_cf_integrations'
        ));
        
        if ($meta_value) {
            $integration_settings = json_decode($meta_value, true);
            if (is_array($integration_settings) && isset($integration_settings['hubspot'])) {
                $form_settings = $integration_settings['hubspot'];
            }
        }
    }
    
    // Fallback: Try post meta (for backward compatibility)
    if (empty($form_settings)) {
        $post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        if ($post_meta && isset($post_meta['hubspot'])) {
            $form_settings = $post_meta['hubspot'];
        }
    }
    
    // Fallback: Try options table
    if (empty($form_settings)) {
        $option_key = "mavlers_cf_hubspot_form_{$form_id}";
        $form_settings = get_option($option_key, []);
    }
}

// Default settings
$default_settings = [
    'enabled' => false,
    'object_type' => 'contacts',
    'custom_object_name' => '',
    'action_type' => 'create_or_update',
    'workflow_enabled' => false,
    'field_mapping' => []
];

$form_settings = array_merge($default_settings, $form_settings);

// Auto-enable if settings exist
if (!isset($form_settings['enabled']) && !empty($form_settings)) {
    $form_settings['enabled'] = true;
}
?>

<div class="hubspot-form-settings" data-form-id="<?php echo $form_id; ?>">
    <?php if (!$is_configured): ?>
        <!-- Not Connected State -->
        <div class="integration-not-connected">
            <div class="not-connected-icon">
                <span class="dashicons dashicons-businessman"></span>
            </div>
            <div class="not-connected-content">
                <h4><?php _e('HubSpot Not Connected', 'mavlers-contact-forms'); ?></h4>
                <p><?php _e('Please configure your HubSpot access token in the global settings first.', 'mavlers-contact-forms'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mavlers-cf-integrations&tab=settings&integration=hubspot'); ?>" class="button button-primary">
                    <?php _e('Configure HubSpot', 'mavlers-contact-forms'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Connected State -->
        <div class="integration-form-settings">
            <!-- Header -->
            <div class="integration-header">
                <div class="integration-info">
                    <div class="integration-title">
                        <span class="dashicons dashicons-businessman"></span>
                        <span><?php _e('HubSpot Integration', 'mavlers-contact-forms'); ?></span>
                    </div>
                    <div class="integration-status connected">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Connected', 'mavlers-contact-forms'); ?>
                    </div>
                </div>
                
                <div class="integration-toggle">
                    <label class="switch">
                        <input type="checkbox" id="hubspot_enabled" name="enabled" value="1" <?php checked($form_settings['enabled']); ?>>
                        <span class="slider round"></span>
                    </label>
                    <span class="toggle-label">
                        <?php _e('Enable HubSpot Integration', 'mavlers-contact-forms'); ?>
                    </span>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="integration-settings" id="hubspot_settings" style="<?php echo $form_settings['enabled'] ? 'display: block;' : 'display: none;'; ?>">
                <form id="hubspot-form-settings">
                    <?php wp_nonce_field('mavlers_cf_nonce', 'hubspot_form_nonce'); ?>
                    <input type="hidden" name="form_id" value="<?php echo $form_id; ?>" />

                    <!-- Object Type Selection -->
                    <div class="setting-group">
                        <h4><?php _e('HubSpot Object Type', 'mavlers-contact-forms'); ?></h4>
                        <div class="setting-row">
                            <label for="hubspot_object_type" class="setting-label">
                                <?php _e('Select Object Type', 'mavlers-contact-forms'); ?>
                                <span class="required">*</span>
                            </label>
                            <div class="setting-control">
                                <select id="hubspot_object_type" name="object_type" class="setting-select" required>
                                    <option value=""><?php _e('Choose an object type...', 'mavlers-contact-forms'); ?></option>
                                    <option value="contacts" <?php selected($form_settings['object_type'], 'contacts'); ?>><?php _e('Contacts', 'mavlers-contact-forms'); ?></option>
                                    <option value="deals" <?php selected($form_settings['object_type'], 'deals'); ?>><?php _e('Deals', 'mavlers-contact-forms'); ?></option>
                                    <option value="companies" <?php selected($form_settings['object_type'], 'companies'); ?>><?php _e('Companies', 'mavlers-contact-forms'); ?></option>
                                    <option value="custom" <?php selected($form_settings['object_type'], 'custom'); ?>><?php _e('Custom Objects', 'mavlers-contact-forms'); ?></option>
                                </select>
                            </div>
                            <p class="setting-help">
                                <?php _e('Choose which HubSpot object type to save form data to.', 'mavlers-contact-forms'); ?>
                            </p>
                        </div>

                        <!-- Custom Object Selection -->
                        <div class="setting-row" id="hubspot-custom-object-row" style="display: <?php echo $form_settings['object_type'] === 'custom' ? 'block' : 'none'; ?>;">
                            <label for="hubspot_custom_object" class="setting-label">
                                <?php _e('Custom Object', 'mavlers-contact-forms'); ?>
                                <span class="required">*</span>
                            </label>
                            <div class="setting-control">
                                <select id="hubspot_custom_object" name="custom_object_name" class="setting-select">
                                    <option value=""><?php _e('Loading custom objects...', 'mavlers-contact-forms'); ?></option>
                                </select>
                            </div>
                            <p class="setting-help">
                                <?php _e('Choose which custom object to save data to.', 'mavlers-contact-forms'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Action Type -->
                    <div class="setting-group">
                        <h4><?php _e('Data Action', 'mavlers-contact-forms'); ?></h4>
                        <div class="setting-row">
                            <label for="hubspot_action_type" class="setting-label">
                                <?php _e('Action Type', 'mavlers-contact-forms'); ?>
                            </label>
                            <div class="setting-control">
                                <select id="hubspot_action_type" name="action_type" class="setting-select">
                                    <option value="create" <?php selected($form_settings['action_type'], 'create'); ?>><?php _e('Create New', 'mavlers-contact-forms'); ?></option>
                                    <option value="update" <?php selected($form_settings['action_type'], 'update'); ?>><?php _e('Update Existing', 'mavlers-contact-forms'); ?></option>
                                    <option value="create_or_update" <?php selected($form_settings['action_type'], 'create_or_update'); ?>><?php _e('Create or Update', 'mavlers-contact-forms'); ?></option>
                                </select>
                            </div>
                            <p class="setting-help">
                                <?php _e('Choose whether to create new records, update existing ones, or both.', 'mavlers-contact-forms'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Additional Options -->
                    <div class="setting-group">
                        <h4><?php _e('Additional Options', 'mavlers-contact-forms'); ?></h4>
                        <div class="setting-row">
                            <label class="setting-label">
                                <input type="checkbox" id="hubspot_workflow_enabled" name="workflow_enabled" value="1" <?php checked($form_settings['workflow_enabled']); ?>>
                                <?php _e('Enroll in Workflow', 'mavlers-contact-forms'); ?>
                            </label>
                            <p class="setting-help">
                                <?php _e('Enroll contact in HubSpot workflow after form submission.', 'mavlers-contact-forms'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Field Mapping Section -->
                    <div class="setting-group" id="hubspot-field-mapping-section" style="display: <?php echo in_array($form_settings['object_type'], ['contacts', 'deals', 'companies']) ? 'block' : 'none'; ?>;">
                        <h4><?php _e('Field Mapping', 'mavlers-contact-forms'); ?></h4>
                        <div class="setting-row">
                            <div class="field-mapping-container">
                                <div class="field-mapping-header">
                                    <div class="mapping-instructions">
                                        <p><?php _e('Map your form fields to HubSpot properties. Email mapping is recommended for contacts.', 'mavlers-contact-forms'); ?></p>
                                    </div>
                                    <div class="mapping-actions">
                                        <button type="button" id="hubspot-auto-map-fields" class="button button-secondary">
                                            <span class="dashicons dashicons-randomize"></span>
                                            <?php _e('Auto-Map', 'mavlers-contact-forms'); ?>
                                        </button>
                                        <button type="button" id="hubspot-clear-mappings" class="button button-secondary">
                                            <span class="dashicons dashicons-dismiss"></span>
                                            <?php _e('Clear All', 'mavlers-contact-forms'); ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="field-mapping-table-container">
                                    <table class="field-mapping-table">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Form Fields', 'mavlers-contact-forms'); ?></th>
                                                <th class="mapping-arrow">→</th>
                                                <th><?php _e('HubSpot Properties', 'mavlers-contact-forms'); ?></th>
                                                <th><?php _e('Status', 'mavlers-contact-forms'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="hubspot-field-mapping-tbody">
                                            <tr class="no-fields-row">
                                                <td colspan="4" class="text-center">
                                                    <?php _e('Select an object type to see field mapping options.', 'mavlers-contact-forms'); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mapping-status">
                                    <span id="hubspot-mapping-count">0 fields mapped</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="setting-actions">
                        <button type="submit" class="button button-primary" id="hubspot-save-settings">
                            <span class="button-text"><?php _e('Save HubSpot Settings', 'mavlers-contact-forms'); ?></span>
                            <span class="button-loading" style="display: none;">
                                <span class="spinner is-active"></span>
                                <?php _e('Saving...', 'mavlers-contact-forms'); ?>
                            </span>
                        </button>
                        <div id="hubspot-save-status" class="save-status"></div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* HubSpot Integration Styles */
.hubspot-form-settings {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin: 20px 0;
}

.integration-not-connected {
    padding: 30px;
    text-align: center;
    background: #f8f9fa;
    border-radius: 8px;
}

.not-connected-icon {
    font-size: 48px;
    color: #6c757d;
    margin-bottom: 20px;
}

.not-connected-content h4 {
    margin: 0 0 10px 0;
    color: #495057;
}

.integration-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.integration-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.integration-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 16px;
}

.integration-status {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.integration-status.connected {
    background: #d4edda;
    color: #155724;
}

.integration-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #0073aa;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.integration-settings {
    padding: 20px;
}

.setting-group {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
}

.setting-group h4 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.setting-row {
    margin-bottom: 20px;
}

.setting-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #495057;
}

.setting-control {
    margin-bottom: 8px;
}

.setting-select {
    width: 100%;
    max-width: 400px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.setting-help {
    margin: 5px 0 0 0;
    font-size: 12px;
    color: #6c757d;
    font-style: italic;
}

.field-mapping-container {
    width: 100%;
}

.field-mapping-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 15px;
    background: #e9ecef;
    border-radius: 4px;
}

.mapping-instructions p {
    margin: 0;
    font-size: 13px;
    color: #495057;
}

.mapping-actions {
    display: flex;
    gap: 10px;
}

.field-mapping-table-container {
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
}

.field-mapping-table {
    width: 100%;
    border-collapse: collapse;
}

.field-mapping-table th,
.field-mapping-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.field-mapping-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mapping-arrow {
    text-align: center;
    font-weight: bold;
    color: #0073aa;
}

.text-center {
    text-align: center;
    color: #6c757d;
    font-style: italic;
}

.mapping-status {
    text-align: right;
    font-size: 12px;
    color: #6c757d;
}

.setting-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.save-status {
    font-size: 13px;
    font-weight: 500;
}

.save-status.success {
    color: #155724;
}

.save-status.error {
    color: #721c24;
}

.button-loading {
    display: flex;
    align-items: center;
    gap: 5px;
}

.spinner.is-active {
    width: 12px;
    height: 12px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #0073aa;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.required {
    color: #dc3545;
}

/* Responsive */
@media (max-width: 768px) {
    .integration-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .field-mapping-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .mapping-actions {
        justify-content: center;
    }
}
</style>

<script>
// Initialize HubSpot settings
window.hubspotFormSettings = <?php echo json_encode($form_settings); ?>;
window.hubspotFormId = <?php echo $form_id; ?>;
window.hubspotGlobalSettings = <?php echo json_encode($global_settings); ?>;

// Create mavlersCFHubspot object
window.mavlersCFHubspot = {
    nonce: '<?php echo wp_create_nonce("mavlers_cf_nonce"); ?>',
    ajaxUrl: '<?php echo admin_url("admin-ajax.php"); ?>',
    formId: <?php echo $form_id; ?>,
    globalSettings: <?php echo json_encode($global_settings); ?>,
    strings: {
        testing: '<?php echo esc_js(__("Testing...", "mavlers-contact-forms")); ?>',
        connected: '<?php echo esc_js(__("Connected", "mavlers-contact-forms")); ?>',
        disconnected: '<?php echo esc_js(__("Disconnected", "mavlers-contact-forms")); ?>',
        testConnection: '<?php echo esc_js(__("Test Connection", "mavlers-contact-forms")); ?>',
        savingSettings: '<?php echo esc_js(__("Saving...", "mavlers-contact-forms")); ?>',
        settingsSaved: '<?php echo esc_js(__("Settings saved successfully!", "mavlers-contact-forms")); ?>',
        connectionFailed: '<?php echo esc_js(__("Connection failed", "mavlers-contact-forms")); ?>',
        selectContact: '<?php echo esc_js(__("Select contact properties...", "mavlers-contact-forms")); ?>',
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