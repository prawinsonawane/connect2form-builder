<?php
/**
 * Mailchimp Form Settings Template
 *
 * Form-specific Mailchimp integration settings for the form builder
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get form ID from the integration section context
$form_id = 0;

// Try multiple ways to get the form ID
if (isset($mailchimp_form_id) && $mailchimp_form_id) {
    // Passed from integration section
    $form_id = intval($mailchimp_form_id);
} elseif (isset($_GET['id']) && $_GET['id']) {
    // Form builder edit page: ?page=mavlers-contact-forms&action=edit&id=1
    $form_id = intval($_GET['id']);
} elseif (isset($_GET['form_id']) && $_GET['form_id']) {
    // Direct form_id parameter
    $form_id = intval($_GET['form_id']);
} elseif (isset($GLOBALS['mavlers_cf_current_form_id'])) {
    // Global context
    $form_id = intval($GLOBALS['mavlers_cf_current_form_id']);
}

// Debug form ID detection
error_log('Mailchimp Template: Form ID detection - $mailchimp_form_id: ' . (isset($mailchimp_form_id) ? $mailchimp_form_id : 'not set'));
error_log('Mailchimp Template: Form ID detection - $_GET[id]: ' . (isset($_GET['id']) ? $_GET['id'] : 'not set'));
error_log('Mailchimp Template: Form ID detection - $_GET[form_id]: ' . (isset($_GET['form_id']) ? $_GET['form_id'] : 'not set'));
error_log('Mailchimp Template: Form ID detection - $GLOBALS: ' . (isset($GLOBALS['mavlers_cf_current_form_id']) ? $GLOBALS['mavlers_cf_current_form_id'] : 'not set'));
error_log('Mailchimp Template: Form ID detection - Final form_id: ' . $form_id);

// Initialize Mailchimp integration
$mailchimp_integration = new Mavlers_CF_Mailchimp_Integration();
$global_settings = $mailchimp_integration->get_global_settings();
$form_settings = $mailchimp_integration->get_form_settings($form_id);
$is_globally_connected = $mailchimp_integration->is_globally_connected();

// Debug form settings loading
error_log('Mailchimp Form Settings Template - Form ID: ' . $form_id);
error_log('Mailchimp Form Settings Template - Loaded settings: ' . print_r($form_settings, true));
?>

<div class="mailchimp-form-settings" data-form-id="<?php echo $form_id; ?>">
    <?php if (!$is_globally_connected): ?>
        <!-- Not Connected Message -->
        <div class="integration-not-connected">
            <div class="not-connected-icon">
                <span class="dashicons dashicons-email-alt" style="color: #ccc; font-size: 24px;"></span>
            </div>
            <div class="not-connected-content">
                <h4><?php _e('Mailchimp Not Connected', 'mavlers-contact-forms'); ?></h4>
                <p><?php _e('Please configure your Mailchimp API key in the global settings first.', 'mavlers-contact-forms'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mavlers-cf-integrations&tab=mailchimp'); ?>" class="button button-primary">
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
                        <span class="dashicons dashicons-email-alt" style="color: #ffe01b;"></span>
                        <span><?php _e('Mailchimp Integration', 'mavlers-contact-forms'); ?></span>
                    </div>
                    <div class="integration-status connected">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Connected', 'mavlers-contact-forms'); ?>
                    </div>
                </div>
                
                <div class="integration-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               id="mailchimp_enabled" 
                               name="mailchimp_enabled" 
                               value="1" 
                               <?php checked($form_settings['enabled']); ?>
                        />
                        <span class="toggle-slider"></span>
                    </label>
                    <label for="mailchimp_enabled" class="toggle-label">
                        <?php _e('Enable for this form', 'mavlers-contact-forms'); ?>
                    </label>
                </div>
            </div>

            <div class="integration-settings" id="mailchimp_settings" <?php echo !$form_settings['enabled'] ? 'style="display: none;"' : ''; ?>>
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

                    <!-- Enhanced Field Mapping -->
                    <div class="setting-row">
                        <label class="setting-label">
                            <?php _e('Enhanced Field Mapping', 'mavlers-contact-forms'); ?>
                            <span class="setting-badge new">NEW</span>
                        </label>
                        <div class="setting-control">
                            <button type="button" class="button" id="open-enhanced-mapping" data-form-id="<?php echo $form_id; ?>">
                                <span class="dashicons dashicons-networking"></span>
                                <?php _e('Configure Field Mapping', 'mavlers-contact-forms'); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="auto-map-fields" data-form-id="<?php echo $form_id; ?>">
                                <span class="dashicons dashicons-randomize"></span>
                                <?php _e('Auto Map Fields', 'mavlers-contact-forms'); ?>
                            </button>
                        </div>
                        <p class="setting-help">
                            <?php _e('Use advanced field mapping for precise control over how form fields are mapped to Mailchimp fields.', 'mavlers-contact-forms'); ?>
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

            <!-- Field Mapping Info -->
            <div class="field-mapping-info" id="field_mapping_info" <?php echo !$form_settings['enabled'] ? 'style="display: none;"' : ''; ?>>
                <h4><?php _e('Automatic Field Mapping', 'mavlers-contact-forms'); ?></h4>
                <p class="info-text">
                    <?php _e('Form fields are automatically mapped to Mailchimp based on field names:', 'mavlers-contact-forms'); ?>
                </p>
                <ul class="mapping-list">
                    <li><strong><?php _e('Email fields', 'mavlers-contact-forms'); ?></strong> → <?php _e('Email Address', 'mavlers-contact-forms'); ?></li>
                    <li><strong><?php _e('First name fields', 'mavlers-contact-forms'); ?></strong> → <?php _e('First Name (FNAME)', 'mavlers-contact-forms'); ?></li>
                    <li><strong><?php _e('Last name fields', 'mavlers-contact-forms'); ?></strong> → <?php _e('Last Name (LNAME)', 'mavlers-contact-forms'); ?></li>
                    <li><strong><?php _e('Phone fields', 'mavlers-contact-forms'); ?></strong> → <?php _e('Phone Number (PHONE)', 'mavlers-contact-forms'); ?></li>
                </ul>
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

<style>
.mailchimp-form-settings {
    margin: 15px 0;
    position: relative;
}

/* Not Connected State */
.integration-not-connected {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    text-align: left;
}

.not-connected-icon {
    flex-shrink: 0;
}

.not-connected-content h4 {
    margin: 0 0 8px 0;
    color: #333;
    font-size: 16px;
}

.not-connected-content p {
    margin: 0 0 15px 0;
    color: #666;
}

/* Connected State */
.integration-form-settings {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.integration-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
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
    font-weight: 500;
    color: #333;
    font-size: 14px;
}

.integration-status {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
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

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
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

.toggle-slider:before {
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

input:checked + .toggle-slider {
    background-color: #0073aa;
}

input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

.toggle-label {
    font-size: 13px;
    color: #333;
    cursor: pointer;
}

/* Settings Form */
.integration-settings {
    padding: 20px;
}

.setting-row {
    margin-bottom: 20px;
}

.setting-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
    font-size: 13px;
}

.required {
    color: #d32f2f;
}

.setting-control {
    display: flex;
    gap: 5px;
    align-items: center;
}

.setting-select,
.setting-input {
    flex-grow: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

.setting-select:focus,
.setting-input:focus {
    border-color: #0073aa;
    box-shadow: 0 0 0 1px #0073aa;
    outline: none;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #333;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
}

.setting-help {
    margin: 5px 0 0 0;
    font-size: 12px;
    color: #666;
    line-height: 1.4;
}

.setting-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.save-status {
    font-size: 13px;
}

.save-status.success {
    color: #155724;
}

.save-status.error {
    color: #721c24;
}

/* Field Mapping Info */
.field-mapping-info {
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
}

.field-mapping-info h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
}

.info-text {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 13px;
}

.mapping-list {
    margin: 0;
    padding-left: 20px;
    color: #666;
    font-size: 12px;
}

.mapping-list li {
    margin-bottom: 5px;
}

/* Loading and Messages */
.integration-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    z-index: 10;
}

.loading-spinner {
    text-align: center;
    color: #666;
}

.loading-spinner .dashicons {
    font-size: 24px;
    margin-bottom: 10px;
}

.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.integration-messages {
    margin-top: 15px;
}

.integration-messages .notice {
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 10px;
    font-size: 13px;
}

.integration-messages .notice-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.integration-messages .notice-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

/* Responsive */
@media (max-width: 768px) {
    .integration-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .integration-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .setting-control {
        flex-direction: column;
        align-items: stretch;
    }
    
    .setting-actions {
        flex-direction: column;
        align-items: stretch;
    }
}

/* Enhanced Field Mapping Modal */
.enhanced-mapping-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 999999;
}

.enhanced-mapping-modal .modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.enhanced-mapping-modal .modal-content {
    position: relative;
    width: 90%;
    max-width: 1000px;
    height: 80%;
    margin: 5% auto;
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
}

.enhanced-mapping-modal .modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.enhanced-mapping-modal .modal-header h3 {
    margin: 0;
}

.enhanced-mapping-modal .close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.enhanced-mapping-modal .close-modal:hover {
    color: #dc3232;
}

.enhanced-mapping-modal .modal-body {
    flex: 1;
    padding: 20px;
    overflow: auto;
}

.enhanced-mapping-modal .modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
    background: #f9f9f9;
}

.enhanced-mapping-modal .modal-footer .button {
    margin-left: 10px;
}

.mapping-container {
    display: flex;
    gap: 20px;
    height: 400px;
}

.mapping-column {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
    background: #fff;
}

.mapping-column .column-header {
    background: #f9f9f9;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mapping-column .column-header h4 {
    margin: 0;
    color: #333;
}

.mapping-column .field-count {
    color: #666;
    font-size: 12px;
    background: #e8e8e8;
    padding: 2px 8px;
    border-radius: 10px;
}

.mapping-column .fields-list {
    height: 335px;
    overflow-y: auto;
    padding: 10px;
}

.mapping-column .field-item {
    padding: 12px;
    margin-bottom: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.mapping-column .field-item:hover {
    background: #f0f8ff;
    border-color: #0073aa;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.mapping-column .field-item.selected {
    background: #e7f3ff;
    border-color: #0073aa;
    box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.2);
}

.mapping-column .field-item.connected {
    background: #e8f5e8;
    border-color: #46b450;
    position: relative;
}

.mapping-column .field-item.connected::after {
    content: '✓';
    position: absolute;
    top: 5px;
    right: 8px;
    color: #46b450;
    font-weight: bold;
    font-size: 14px;
}

.mapping-column .field-item .field-label {
    font-weight: 500;
    display: block;
    color: #333;
    margin-bottom: 4px;
}

.mapping-column .field-item .field-type,
.mapping-column .field-item .field-id {
    color: #666;
    font-size: 11px;
    display: block;
    margin-top: 2px;
}

.mapping-column .field-item .required {
    color: #dc3232;
    font-weight: bold;
    margin-left: 4px;
}

.mapping-connections {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 5px;
    text-align: center;
    border: 1px solid #ddd;
}

.mapping-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #666;
    font-size: 14px;
}

.mapping-status.has-mappings {
    color: #46b450;
}

.mapping-status .dashicons {
    font-size: 16px;
}

.loading-placeholder {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.loading-placeholder .dashicons {
    font-size: 20px;
    margin-bottom: 10px;
    display: block;
}

.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Form fields specific styling */
.form-fields-column .field-item {
    border-left: 4px solid #0073aa;
}

.form-fields-column .field-item.connected {
    border-left: 4px solid #46b450;
}

/* Mailchimp fields specific styling */
.mailchimp-fields-column .field-item {
    border-left: 4px solid #ffe01b;
}

.mailchimp-fields-column .field-item.connected {
    border-left: 4px solid #46b450;
}

/* Instructions */
.mapping-instructions {
    background: #e7f3ff;
    border: 1px solid #b8deff;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #0073aa;
}

.mapping-instructions::before {
    content: '💡';
    margin-right: 8px;
}

/* No audience selected state */
.no-audience-selected {
    text-align: center;
    padding: 40px 20px;
    color: #0073aa;
    font-style: italic;
}

.no-audience-selected .dashicons {
    margin-right: 8px;
    font-size: 16px;
}

/* Error states */
.mapping-column .error {
    text-align: center;
    padding: 40px 20px;
    color: #dc3232;
    font-style: italic;
}

/* Field descriptions */
.field-description {
    color: #666;
    font-style: italic;
    display: block;
    margin-top: 4px;
    line-height: 1.3;
}

/* Enhanced field type display */
.mapping-column .field-type {
    background: #f0f0f0;
    color: #666;
    font-size: 10px;
    padding: 1px 4px;
    border-radius: 2px;
    text-transform: uppercase;
    margin-left: 4px;
}
</style>

<script>
jQuery(document).ready(function($) {
    const formId = $('.mailchimp-form-settings').data('form-id');
    const savedAudienceId = '<?php echo esc_js($form_settings['audience_id']); ?>';
    
    // Ensure ajaxurl is available
    if (typeof ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    
    // Load audiences on page load if enabled
    if ($('#mailchimp_enabled').is(':checked')) {
        loadAudiences(false, savedAudienceId);
    }

    // Toggle integration settings
    $('#mailchimp_enabled').on('change', function() {
        const isEnabled = $(this).is(':checked');
        
        if (isEnabled) {
            $('#mailchimp_settings').slideDown();
            $('#field_mapping_info').slideDown();
            loadAudiences(false, savedAudienceId);
        } else {
            $('#mailchimp_settings').slideUp();
            $('#field_mapping_info').slideUp();
        }
    });

    // Refresh audiences
    $('#refresh_audiences').on('click', function() {
        const currentValue = $('#mailchimp_audience').val();
        loadAudiences(true, currentValue);
    });

    // Save form settings  
    $('#mailchimp-form-settings').on('submit', function(e) {
        console.log('=== Manual Form Submit Triggered ===');
        e.preventDefault();
        console.log('Calling saveFormSettings() from manual submit');
        saveFormSettings();
    });

    // Load audiences from Mailchimp
    function loadAudiences(force = false, selectedValue = '') {
        const $select = $('#mailchimp_audience');
        const $button = $('#refresh_audiences');
        
        // Don't reload if already loaded and not forced
        if (!force && $select.find('option').length > 1) {
            return;
        }

        $button.prop('disabled', true);
        $button.find('.dashicons').addClass('spinning');
        
        // Keep current selection
        $select.html('<option value=""><?php _e('Loading audiences...', 'mavlers-contact-forms'); ?></option>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_audiences',
                nonce: $('#mailchimp_form_nonce').val()
            },
            success: function(response) {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('spinning');
                
                if (response.success && response.data) {
                    populateAudienceSelect(response.data, selectedValue);
                } else {
                    $select.html('<option value=""><?php _e('Failed to load audiences', 'mavlers-contact-forms'); ?></option>');
                    showMessage(response.message || '<?php _e('Failed to load audiences', 'mavlers-contact-forms'); ?>', 'error');
                }
            },
            error: function() {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('spinning');
                $select.html('<option value=""><?php _e('Error loading audiences', 'mavlers-contact-forms'); ?></option>');
                showMessage('<?php _e('Error loading audiences', 'mavlers-contact-forms'); ?>', 'error');
            }
        });
    }

    // Populate audience select
    function populateAudienceSelect(audiences, selectedValue) {
        const $select = $('#mailchimp_audience');
        let options = '<option value=""><?php _e('Select an audience...', 'mavlers-contact-forms'); ?></option>';
        
        audiences.forEach(function(audience) {
            const isSelected = selectedValue === audience.id ? 'selected' : '';
            options += `<option value="${audience.id}" ${isSelected}>${audience.name} (${audience.member_count} members)</option>`;
        });
        
        $select.html(options);
    }

    // Save form settings
    function saveFormSettings() {
        console.log('=== Mailchimp Save Form Settings Called ===');
        console.log('Form ID:', formId);
        console.log('Enabled:', $('#mailchimp_enabled').is(':checked'));
        console.log('Audience ID:', $('#mailchimp_audience').val());
        console.log('Nonce:', $('#mailchimp_form_nonce').val());
        console.log('Ajax URL:', ajaxurl);
        
        showLoading(true);
        clearMessages();

        const formData = {
            action: 'mailchimp_save_form_settings',
            form_id: formId,
            enabled: $('#mailchimp_enabled').is(':checked') ? 1 : 0,
            audience_id: $('#mailchimp_audience').val(),
            double_optin: $('#mailchimp_double_optin').is(':checked') ? 1 : 0,
            update_existing: $('#mailchimp_update_existing').is(':checked') ? 1 : 0,
            tags: $('#mailchimp_tags').val(),
            nonce: $('#mailchimp_form_nonce').val()
        };

        console.log('Form data being sent:', formData);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('AJAX Success Response:', response);
                showLoading(false);
                
                if (response.success) {
                    showSaveStatus('<?php _e('Settings saved successfully!', 'mavlers-contact-forms'); ?>', 'success');
                } else {
                    showSaveStatus(response.message || '<?php _e('Save failed', 'mavlers-contact-forms'); ?>', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', {xhr: xhr, status: status, error: error});
                showLoading(false);
                showSaveStatus('<?php _e('Save failed', 'mavlers-contact-forms'); ?>', 'error');
            }
        });
    }

    // Helper functions
    function showLoading(show) {
        if (show) {
            $('#mailchimp_loading').show();
        } else {
            $('#mailchimp_loading').hide();
        }
    }

    function showMessage(message, type) {
        const messageClass = 'notice notice-' + (type === 'error' ? 'error' : 'success');
        const messageHtml = `<div class="${messageClass}">${message}</div>`;
        $('#mailchimp_messages').append(messageHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $('#mailchimp_messages .notice').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    function showSaveStatus(message, type) {
        const $status = $('#save_status');
        $status.removeClass('success error').addClass(type).text(message);
        
        // Clear after 3 seconds
        setTimeout(function() {
            $status.text('').removeClass('success error');
        }, 3000);
    }

    function clearMessages() {
        $('#mailchimp_messages').empty();
    }

    // Auto-save on field changes (debounced)
    let saveTimeout;
    $('#mailchimp-form-settings input, #mailchimp-form-settings select').on('change', function() {
        console.log('=== Mailchimp Form Field Changed ===');
        console.log('Changed element:', this.id, this.name, this.value);
        console.log('Mailchimp enabled?', $('#mailchimp_enabled').is(':checked'));
        
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(function() {
            console.log('Auto-save timeout triggered. Mailchimp enabled?', $('#mailchimp_enabled').is(':checked'));
            if ($('#mailchimp_enabled').is(':checked')) {
                console.log('Calling saveFormSettings() from auto-save');
                saveFormSettings();
            } else {
                console.log('Skipping auto-save because Mailchimp is not enabled');
            }
        }, 1000);
    });
    
    // Initialize debugging
    console.log('=== Mailchimp Form Settings Initialized ===');
    console.log('Form ID from data attribute:', formId);
    console.log('Saved Audience ID:', savedAudienceId);
    console.log('Form exists?', $('.mailchimp-form-settings').length);
    console.log('Enable checkbox exists?', $('#mailchimp_enabled').length);
    console.log('Enable checkbox checked?', $('#mailchimp_enabled').is(':checked'));
    console.log('Form element exists?', $('#mailchimp-form-settings').length);
    console.log('Nonce field exists?', $('#mailchimp_form_nonce').length);
    console.log('Nonce field value:', $('#mailchimp_form_nonce').val());
    console.log('Ajax URL:', ajaxurl);
    
    // Enhanced Field Mapping functionality
    $('#open-enhanced-mapping').on('click', function() {
        const formId = $(this).data('form-id');
        openEnhancedMapping(formId);
    });
    
    $('#auto-map-fields').on('click', function() {
        const formId = $(this).data('form-id');
        autoMapFields(formId);
    });
    
    // Enhanced mapping modal functions
    function openEnhancedMapping(formId) {
        $('#enhanced-mapping-modal').show();
        loadFormFields(formId);
        loadMailchimpFields();
        loadExistingMapping(formId);
    }

    // Reload Mailchimp fields when audience changes
    $('#mailchimp_audience').on('change', function() {
        // If the enhanced mapping modal is open, reload the fields
        if ($('#enhanced-mapping-modal').is(':visible')) {
            loadMailchimpFields();
            // Clear existing connections since audience changed
            $('.field-item').removeClass('connected selected');
            fieldConnections = {};
            selectedFormField = null;
            selectedMailchimpField = null;
            updateMappingStatus(0);
        }
    });
    
    function loadFormFields(formId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_get_form_fields',
                form_id: formId,
                nonce: $('#mailchimp_form_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    populateFormFields(response.data);
                } else {
                    $('#form-fields-list').html('<div class="error">Failed to load form fields</div>');
                }
            }
        });
    }
    
    function loadMailchimpFields() {
        const audienceId = $('#mailchimp_audience').val();
        
        if (!audienceId) {
            $('#mailchimp-fields-list').html('<div class="no-audience-selected"><span class="dashicons dashicons-info"></span> Please select an audience first</div>');
            $('#mailchimp-field-count').text('0 fields');
            return;
        }

        // Show loading state
        $('#mailchimp-fields-list').html('<div class="loading-placeholder"><span class="dashicons dashicons-update spinning"></span> Loading audience fields...</div>');
        $('#mailchimp-field-count').text('Loading...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_audience_merge_fields',
                audience_id: audienceId,
                nonce: $('#mailchimp_form_nonce').val()
            },
            success: function(response) {
                if (response.success && response.data) {
                    populateMailchimpFields(response.data);
                } else {
                    $('#mailchimp-fields-list').html('<div class="error">Failed to load audience fields</div>');
                    $('#mailchimp-field-count').text('0 fields');
                }
            },
            error: function() {
                $('#mailchimp-fields-list').html('<div class="error">Network error loading audience fields</div>');
                $('#mailchimp-field-count').text('0 fields');
            }
        });
    }
    
    function populateFormFields(fields) {
        let html = '';
        fields.forEach(function(field) {
            html += `<div class="field-item" data-field-id="${field.id}">
                <span class="field-label">${field.label}</span>
                <span class="field-type">(${field.type})</span>
            </div>`;
        });
        $('#form-fields-list').html(html);
        $('#form-field-count').text(fields.length + ' fields');
    }
    
    function populateMailchimpFields(fields) {
        let html = '';
        fields.forEach(function(field) {
            const required = field.required ? '<span class="required">*</span>' : '';
            const fieldName = field.name || field.label;
            const fieldId = field.id || field.tag;
            const fieldType = field.type || 'text';
            const description = field.description ? `<br><small class="field-description">${field.description}</small>` : '';
            
            html += `<div class="field-item" data-field-id="${fieldId}">
                <span class="field-label">${fieldName}${required}</span>
                <span class="field-id">(${fieldId})</span>
                <span class="field-type">${fieldType}</span>
                ${description}
            </div>`;
        });
        $('#mailchimp-fields-list').html(html);
        $('#mailchimp-field-count').text(fields.length + ' fields');
    }
    
    function loadExistingMapping(formId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_field_mapping',
                form_id: formId,
                nonce: $('#mailchimp_form_nonce').val()
            },
            success: function(response) {
                if (response.success && response.data) {
                    applyExistingMapping(response.data);
                } else {
                    console.log('No existing mapping found for form ' + formId);
                }
            },
            error: function() {
                console.log('Failed to load existing mapping');
            }
        });
    }

    function applyExistingMapping(mapping) {
        // Clear any existing connections
        $('.field-item').removeClass('connected');
        fieldConnections = {};
        
        // Apply the mapping
        Object.keys(mapping).forEach(function(formFieldId) {
            const mailchimpField = mapping[formFieldId];
            
            // Store in our tracking object
            fieldConnections[formFieldId] = mailchimpField;
            
            // Mark fields as connected
            $(`.form-fields-column .field-item[data-field-id="${formFieldId}"]`).addClass('connected');
            $(`.mailchimp-fields-column .field-item[data-field-id="${mailchimpField}"]`).addClass('connected');
        });
        
        updateMappingStatus(Object.keys(mapping).length);
    }

    function updateMappingStatus(connectedCount) {
        const statusEl = $('#mapping-status');
        if (connectedCount > 0) {
            statusEl.html(`<span class="dashicons dashicons-yes-alt"></span> ${connectedCount} field(s) mapped`);
            statusEl.addClass('has-mappings');
        } else {
            statusEl.html(`<span class="dashicons dashicons-info"></span> Click and drag to connect form fields to Mailchimp fields`);
            statusEl.removeClass('has-mappings');
        }
    }

    function autoMapFields(formId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mailchimp_auto_map_fields',
                form_id: formId,
                nonce: $('#mailchimp_form_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showSaveStatus('Fields auto-mapped successfully!', 'success');
                    // Reload the mapping to show the auto-mapped fields
                    loadExistingMapping(formId);
                } else {
                    showSaveStatus(response.message || 'Auto-mapping failed', 'error');
                }
            }
        });
    }
    
    // Enhanced Field Mapping - Save functionality
    $('#save-field-mapping').on('click', function() {
        const formId = $('#open-enhanced-mapping').data('form-id');
        saveFieldMapping(formId);
    });

    // Enhanced Field Mapping - Reset functionality
    $('#reset-mapping').on('click', function() {
        if (confirm('Are you sure you want to reset all field mappings?')) {
            $('.field-item').removeClass('connected selected');
            fieldConnections = {};
            selectedFormField = null;
            selectedMailchimpField = null;
            updateMappingStatus(0);
        }
    });

    // Field connection functionality (click to connect)
    let selectedFormField = null;
    let selectedMailchimpField = null;

    $(document).on('click', '.form-fields-column .field-item', function() {
        $('.form-fields-column .field-item').removeClass('selected');
        $(this).addClass('selected');
        selectedFormField = $(this).data('field-id');
        
        if (selectedMailchimpField) {
            createConnection(selectedFormField, selectedMailchimpField);
        }
    });

    $(document).on('click', '.mailchimp-fields-column .field-item', function() {
        $('.mailchimp-fields-column .field-item').removeClass('selected');
        $(this).addClass('selected');
        selectedMailchimpField = $(this).data('field-id');
        
        if (selectedFormField) {
            createConnection(selectedFormField, selectedMailchimpField);
        }
    });



    // Store actual field connections
    let fieldConnections = {};

    function createConnection(formFieldId, mailchimpFieldId) {
        // Remove any existing connections for these fields
        $(`.field-item[data-field-id="${formFieldId}"]`).removeClass('connected selected');
        $(`.field-item[data-field-id="${mailchimpFieldId}"]`).removeClass('connected selected');
        
        // Remove existing connections that involve either field
        Object.keys(fieldConnections).forEach(key => {
            if (key === formFieldId || fieldConnections[key] === mailchimpFieldId) {
                delete fieldConnections[key];
            }
        });
        
        // Create new connection
        fieldConnections[formFieldId] = mailchimpFieldId;
        $(`.form-fields-column .field-item[data-field-id="${formFieldId}"]`).addClass('connected');
        $(`.mailchimp-fields-column .field-item[data-field-id="${mailchimpFieldId}"]`).addClass('connected');
        
        // Update mapping status
        updateMappingStatus(Object.keys(fieldConnections).length);
        
        // Reset selections
        selectedFormField = null;
        selectedMailchimpField = null;
        $('.field-item').removeClass('selected');
    }

    function saveFieldMapping(formId) {
        const mapping = fieldConnections;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mailchimp_save_field_mapping',
                form_id: formId,
                mapping: JSON.stringify(mapping),
                nonce: $('#mailchimp_form_nonce').val()
            },
            beforeSend: function() {
                $('#save-field-mapping').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    showSaveStatus('Field mapping saved successfully!', 'success');
                    setTimeout(() => {
                        $('#enhanced-mapping-modal').hide();
                    }, 1500);
                } else {
                    showSaveStatus(response.message || 'Failed to save field mapping', 'error');
                }
            },
            error: function() {
                showSaveStatus('Network error occurred while saving', 'error');
            },
            complete: function() {
                $('#save-field-mapping').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Mapping');
            }
        });
    }

    // Close modal
    $('.close-modal, #cancel-mapping').on('click', function() {
        $('#enhanced-mapping-modal').hide();
    });
    
    // Close modal on overlay click
    $('.modal-overlay').on('click', function() {
        $('#enhanced-mapping-modal').hide();
    });
});
</script>

<!-- Enhanced Field Mapping Modal -->
<div id="enhanced-mapping-modal" class="enhanced-mapping-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Enhanced Field Mapping', 'mavlers-contact-forms'); ?></h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="mapping-instructions">
                💡 The Mailchimp fields on the right are loaded from your selected audience. If you change the audience, the fields will update automatically. Click form fields on the left, then Mailchimp fields on the right to create connections.
            </div>
            <div class="mapping-container">
                <div class="mapping-column form-fields-column">
                    <div class="column-header">
                        <h4><?php _e('Form Fields', 'mavlers-contact-forms'); ?></h4>
                        <span class="field-count" id="form-field-count">0 fields</span>
                    </div>
                    <div class="fields-list" id="form-fields-list">
                        <div class="loading-placeholder">
                            <span class="dashicons dashicons-update spinning"></span>
                            <?php _e('Loading form fields...', 'mavlers-contact-forms'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="mapping-column mailchimp-fields-column">
                    <div class="column-header">
                        <h4><?php _e('Mailchimp Fields', 'mavlers-contact-forms'); ?></h4>
                        <span class="field-count" id="mailchimp-field-count">0 fields</span>
                    </div>
                    <div class="fields-list" id="mailchimp-fields-list">
                        <!-- Mailchimp fields will be populated here -->
                    </div>
                </div>
            </div>
            
            <div class="mapping-connections" id="mapping-connections">
                <!-- Connection lines will be drawn here -->
            </div>
            
            <div class="mapping-status" id="mapping-status">
                <span class="dashicons dashicons-info"></span>
                <?php _e('Click and drag to connect form fields to Mailchimp fields', 'mavlers-contact-forms'); ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-primary" id="save-field-mapping">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Save Mapping', 'mavlers-contact-forms'); ?>
            </button>
            <button type="button" class="button" id="cancel-mapping">
                <?php _e('Cancel', 'mavlers-contact-forms'); ?>
            </button>
            <button type="button" class="button button-secondary" id="reset-mapping">
                <span class="dashicons dashicons-undo"></span>
                <?php _e('Reset', 'mavlers-contact-forms'); ?>
            </button>
        </div>
    </div>
</div> 