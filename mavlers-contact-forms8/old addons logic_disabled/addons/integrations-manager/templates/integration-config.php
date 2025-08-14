<?php
/**
 * Integration Configuration Template
 * 
 * Template for displaying integration configuration forms
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$integration_data = $integration ? array(
    'id' => $integration->get_integration_id(),
    'name' => $integration->get_integration_name(),
    'description' => $integration->get_integration_description(),
    'auth_fields' => $integration->get_auth_fields(),
    'available_actions' => $integration->get_available_actions(),
    'available_fields' => $integration->get_available_fields()
) : array();

// Determine context: global (API setup) vs form-specific (field mapping, etc.)
$is_global_setup = ($form_id == 0 && ($mode ?? 'configure') == 'setup') || ($mode ?? 'configure') == 'global';
$is_form_specific = !$is_global_setup;
?>

<div class="integration-config-form">
    <?php if (empty($integration_data)): ?>
        <div class="error-message">
            <p><?php _e('Integration configuration could not be loaded.', 'mavlers-contact-forms'); ?></p>
        </div>
    <?php else: ?>
        
        <div class="integration-header">
            <h4><?php echo esc_html($integration_data['name']); ?></h4>
            <p class="integration-description"><?php echo esc_html($integration_data['description']); ?></p>
            
            <?php if ($is_global_setup): ?>
                <div class="context-notice global">
                    <p><strong><?php _e('Global Settings:', 'mavlers-contact-forms'); ?></strong> <?php _e('Configure API credentials and test connection. Form-specific settings like field mapping are configured when editing individual forms.', 'mavlers-contact-forms'); ?></p>
                </div>
            <?php else: ?>
                <div class="context-notice form-specific">
                    <p><strong><?php _e('Form Settings:', 'mavlers-contact-forms'); ?></strong> <?php _e('Configure integration behavior, field mapping, and form-specific options.', 'mavlers-contact-forms'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($integration_data['auth_fields'])): ?>
        <div class="auth-section">
            <h5><?php _e('Authentication', 'mavlers-contact-forms'); ?></h5>
            
            <?php foreach ($integration_data['auth_fields'] as $field_id => $field_config): ?>
                <div class="integration-form-row">
                    <label for="auth_<?php echo esc_attr($field_id); ?>">
                        <?php echo esc_html($field_config['label']); ?>
                        <?php if (!empty($field_config['required'])): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                    
                    <?php 
                    $field_type = $field_config['type'] ?? 'text';
                    $field_value = $auth_data[$field_id] ?? $settings[$field_id] ?? '';
                    ?>
                    
                    <?php if ($field_type === 'password'): ?>
                        <input type="password" id="auth_<?php echo esc_attr($field_id); ?>" name="auth_<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($field_value); ?>" class="integration-field" <?php echo !empty($field_config['required']) ? 'required' : ''; ?> />
                    <?php elseif ($field_type === 'textarea'): ?>
                        <textarea id="auth_<?php echo esc_attr($field_id); ?>" name="auth_<?php echo esc_attr($field_id); ?>" class="integration-field" <?php echo !empty($field_config['required']) ? 'required' : ''; ?>><?php echo esc_textarea($field_value); ?></textarea>
                    <?php else: ?>
                        <input type="<?php echo esc_attr($field_type); ?>" id="auth_<?php echo esc_attr($field_id); ?>" name="auth_<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($field_value); ?>" class="integration-field" <?php echo !empty($field_config['required']) ? 'required' : ''; ?> />
                    <?php endif; ?>
                    
                    <?php if (!empty($field_config['description'])): ?>
                        <p class="field-description"><?php echo esc_html($field_config['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($is_form_specific && !empty($integration_data['available_actions'])): ?>
        <div class="actions-section">
            <h5><?php _e('Integration Actions', 'mavlers-contact-forms'); ?></h5>
            
            <div class="integration-form-row">
                <label for="setting_action">
                    <?php _e('Action to Perform', 'mavlers-contact-forms'); ?>
                </label>
                <select id="setting_action" name="setting_action" class="integration-field">
                    <?php foreach ($integration_data['available_actions'] as $action_id => $action_config): ?>
                        <option value="<?php echo esc_attr($action_id); ?>" <?php selected($settings['action'] ?? '', $action_id); ?>>
                            <?php echo esc_html($action_config['label'] ?? $action_id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-description"><?php _e('Select what action to perform when a form is submitted.', 'mavlers-contact-forms'); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php
        // General Settings section removed per user request
        // User doesn't need "Enable this integration" and "Enable conditional logic" options
        ?>
        
        <?php if ($is_form_specific && !empty($integration_data['available_fields'])): ?>
        <div class="field-mapping-section">
            <h5><?php _e('Field Mapping', 'mavlers-contact-forms'); ?></h5>
            <p class="section-description"><?php _e('Map your form fields to the integration fields below.', 'mavlers-contact-forms'); ?></p>
            
            <div class="field-mapping-container">
                <div class="mapping-preview">
                    <p><?php _e('Field mapping will be configured in the next step.', 'mavlers-contact-forms'); ?></p>
                    <button type="button" class="button button-secondary open-field-mapping" data-form-id="<?php echo esc_attr($form_id); ?>" data-integration-id="<?php echo esc_attr($integration_data['id']); ?>">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php _e('Configure Field Mapping', 'mavlers-contact-forms'); ?>
                    </button>
                </div>
                
                <div class="current-mappings" style="margin-top: 15px;">
                    <?php if (!empty($field_mappings)): ?>
                        <h6><?php _e('Current Mappings:', 'mavlers-contact-forms'); ?></h6>
                        <ul class="mapping-list">
                            <?php foreach ($field_mappings as $mapping): ?>
                                <li>
                                    <strong><?php echo esc_html($mapping['integration_field']); ?></strong> 
                                    ← <?php echo esc_html($mapping['form_field']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-mappings"><?php _e('No field mappings configured yet.', 'mavlers-contact-forms'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="connection-test-section">
            <h5><?php _e('Test Connection', 'mavlers-contact-forms'); ?></h5>
            <p><?php _e('Test your integration settings to make sure everything is configured correctly.', 'mavlers-contact-forms'); ?></p>
            
            <button type="button" class="test-connection-btn button-secondary" data-integration-id="<?php echo esc_attr($integration_data['id']); ?>">
                <?php _e('Test Connection', 'mavlers-contact-forms'); ?>
            </button>
            
            <div class="connection-result" style="display: none;"></div>
        </div>
        
    <?php endif; ?>
</div>

<?php
// Helper method to render field attributes (this would typically be in a helper class)
if (!function_exists('render_field_attributes')) {
    function render_field_attributes($attributes) {
        $output = '';
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                $output .= sprintf('%s="%s" ', esc_attr($key), esc_attr($value));
            }
        }
        return trim($output);
    }
}
?> 