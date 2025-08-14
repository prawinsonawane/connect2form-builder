<?php
/**
 * HubSpot Form Settings Template
 *
 * Comprehensive form-specific HubSpot integration settings for the form builder
 */

if (!defined('ABSPATH')) {
    exit;
}

// Debug output
echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffc107;">';
echo '<strong>Debug: HubSpot Template Started</strong><br>';
echo 'Template file: ' . __FILE__ . '<br>';
echo 'Current time: ' . date('Y-m-d H:i:s') . '<br>';
echo '</div>';

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
} elseif (isset($hubspot_form_id) && $hubspot_form_id) {
    // Passed from integration section
    $form_id = intval($hubspot_form_id);
} elseif (isset($mailchimp_form_id) && $mailchimp_form_id) {
    // Fallback to mailchimp variable name
    $form_id = intval($mailchimp_form_id);
} elseif (isset($_GET['form_id']) && $_GET['form_id']) {
    // Direct form_id parameter
    $form_id = intval($_GET['form_id']);
} elseif (isset($GLOBALS['mavlers_cf_current_form_id'])) {
    // Global context
    $form_id = intval($GLOBALS['mavlers_cf_current_form_id']);
}

// Get the HubSpot integration instance
try {
    // Try to get the integration from the new system
    $hubspot_integration = null;
    
    // Method 1: Try to get from the new registry
    if (class_exists('MavlersCF\Integrations\Core\Plugin')) {
        $plugin = MavlersCF\Integrations\Core\Plugin::getInstance();
        if ($plugin && method_exists($plugin, 'getRegistry')) {
            $registry = $plugin->getRegistry();
            if ($registry && method_exists($registry, 'get')) {
                $hubspot_integration = $registry->get('hubspot');
            }
        }
    }
    
    if (!$hubspot_integration) {
        throw new Exception('HubSpot integration not found');
    }
    
    // Check if integration is configured
    $is_configured = false;
    if (method_exists($hubspot_integration, 'is_globally_connected')) {
        $is_configured = $hubspot_integration->is_globally_connected();
    } elseif (method_exists($hubspot_integration, 'isConfigured')) {
        $is_configured = $hubspot_integration->isConfigured();
    }
    
    // Get global settings
    $global_settings = [];
    if (method_exists($hubspot_integration, 'get_global_settings')) {
        $global_settings = $hubspot_integration->get_global_settings();
    } else {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        $global_settings = $global_settings['hubspot'] ?? [];
    }
    
    // Get form-specific settings from multiple possible sources
    $form_settings = [];
    if ($form_id) {
        // Try multiple sources for form settings
        $found_settings = false;
        
        // Method 1: Try post meta FIRST (where complete settings are stored)
        $post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        if ($post_meta && isset($post_meta['hubspot'])) {
            $form_settings = $post_meta['hubspot'];
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
                if ($integration_settings && isset($integration_settings['hubspot'])) {
                    $form_settings = $integration_settings['hubspot'];
                    $found_settings = true;
                }
            }
        }
        
        // Method 3: Try options table as final fallback
        if (!$found_settings) {
            $option_key = "mavlers_cf_hubspot_form_{$form_id}";
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
        'contact_enabled' => true,
        'deal_enabled' => false,
        'workflow_enabled' => false,
        'company_enabled' => false,
        'field_mapping' => []
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

<div class="hubspot-form-settings" data-form-id="<?php echo $form_id; ?>">
    <?php if (!$is_configured): ?>
        <!-- Not Connected Message -->
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
        <!-- Form Settings -->
        <div class="integration-form-settings">
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
                    <span class="toggle-label">
                        <?php _e('Configured and Ready', 'mavlers-contact-forms'); ?>
                    </span>
                </div>
            </div>

            <div class="integration-settings" id="hubspot_settings">
                <form id="hubspot-form-settings">
                    <?php wp_nonce_field('mavlers_cf_nonce', 'hubspot_form_nonce'); ?>
                    <input type="hidden" name="form_id" value="<?php echo $form_id; ?>" />

                    <!-- Contact Creation -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <input type="checkbox" id="hubspot_contact_enabled" name="contact_enabled" value="1" <?php checked($form_settings['contact_enabled']); ?>>
                            <?php _e('Create/Update Contact', 'mavlers-contact-forms'); ?>
                        </label>
                        <p class="setting-help"><?php _e('Create or update contact in HubSpot CRM', 'mavlers-contact-forms'); ?></p>
                    </div>

                    <!-- Deal Creation -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <input type="checkbox" id="hubspot_deal_enabled" name="deal_enabled" value="1" <?php checked($form_settings['deal_enabled']); ?>>
                            <?php _e('Create Deal', 'mavlers-contact-forms'); ?>
                        </label>
                        <p class="setting-help"><?php _e('Create a new deal for this submission', 'mavlers-contact-forms'); ?></p>
                    </div>

                    <!-- Workflow Enrollment -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <input type="checkbox" id="hubspot_workflow_enabled" name="workflow_enabled" value="1" <?php checked($form_settings['workflow_enabled']); ?>>
                            <?php _e('Enroll in Workflow', 'mavlers-contact-forms'); ?>
                        </label>
                        <p class="setting-help"><?php _e('Enroll contact in HubSpot workflow', 'mavlers-contact-forms'); ?></p>
                    </div>

                    <!-- Company Association -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <input type="checkbox" id="hubspot_company_enabled" name="company_enabled" value="1" <?php checked($form_settings['company_enabled']); ?>>
                            <?php _e('Associate Company', 'mavlers-contact-forms'); ?>
                        </label>
                        <p class="setting-help"><?php _e('Associate contact with company', 'mavlers-contact-forms'); ?></p>
                    </div>

                    <!-- Field Mapping -->
                    <div class="setting-row">
                        <h4><?php _e('Field Mapping', 'mavlers-contact-forms'); ?></h4>
                        <div id="hubspot-field-mapping">
                            <!-- Field mapping will be loaded via AJAX -->
                        </div>
                        <button type="button" id="auto_map_fields" class="button button-secondary">
                            <?php _e('Auto Map Fields', 'mavlers-contact-forms'); ?>
                        </button>
                    </div>

                    <!-- Debug Test Buttons -->
                    <div class="setting-row">
                        <h4><?php _e('Debug Tests', 'mavlers-contact-forms'); ?></h4>
                        <button type="button" class="button button-secondary hubspot-debug-test">
                            <?php _e('Test Debug Endpoint', 'mavlers-contact-forms'); ?>
                        </button>
                        <button type="button" class="button button-secondary hubspot-test-basic">
                            <?php _e('Test Basic Endpoint', 'mavlers-contact-forms'); ?>
                        </button>
                        <button type="button" class="button button-secondary hubspot-test-simple-v2">
                            <?php _e('Test Simple V2', 'mavlers-contact-forms'); ?>
                        </button>
                        <div id="debug_status"></div>
                    </div>

                    <!-- Save Button -->
                    <div class="setting-row">
                        <button type="submit" class="button button-primary">
                            <?php _e('Save HubSpot Settings', 'mavlers-contact-forms'); ?>
                        </button>
                        <span id="save_status"></span>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Pass settings to JavaScript
window.hubspotFormSettings = <?php echo json_encode($form_settings); ?>;
window.hubspotGlobalSettings = <?php echo json_encode($global_settings); ?>;

// Debug information
console.log('HubSpot: Form ID:', <?php echo $form_id; ?>);
console.log('HubSpot: Form settings:', <?php echo json_encode($form_settings); ?>);
console.log('HubSpot: Global settings:', <?php echo json_encode($global_settings); ?>);
</script>

<?php
// Debug output at end
echo '<div style="background: #d1ecf1; padding: 10px; margin: 10px 0; border: 1px solid #bee5eb;">';
echo '<strong>Debug: HubSpot Template Completed</strong><br>';
echo 'Form ID: ' . $form_id . '<br>';
echo 'Is configured: ' . ($is_configured ? 'true' : 'false') . '<br>';
echo 'Form settings count: ' . count($form_settings) . '<br>';
echo '</div>';
?> 