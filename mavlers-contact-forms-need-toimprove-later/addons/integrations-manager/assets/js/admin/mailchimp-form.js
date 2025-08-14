/**
 * Mailchimp Form Settings JavaScript
 * 
 * Handles Mailchimp integration form settings with top-right corner messaging
 */

(function($) {
    'use strict';

    // Global variables
    let currentFormId = 0;
    let formFields = [];
    let mailchimpAudiences = [];
    let mergeFields = [];
    let currentMapping = {};
    let isGlobalSettings = false;
    let isFormSpecific = false;

    /**
     * Check if we're on the global settings page
     */
    function isGlobalSettingsPage() {
        return window.location.href.includes('page=mavlers-contact-forms') && 
               !window.location.href.includes('action=edit');
    }

    /**
     * Check if we're on a form-specific settings page
     */
    function isOnFormSpecificPage() {
        return window.location.href.includes('page=mavlers-contact-forms') && 
               window.location.href.includes('action=edit');
    }

    /**
     * Initialize Mailchimp settings
     */
    function initializeMailchimpSettings() {
        // Check if we're on the right page
        if (!isGlobalSettingsPage() && !isOnFormSpecificPage()) {
            return;
        }

        // Check if required dependencies are available
        if (typeof mavlersCFMailchimp === 'undefined') {
                return;
            }
            
        if (typeof jQuery === 'undefined') {
            return;
        }

        // Check if container exists
        if (!$('.mailchimp-form-settings').length) {
            return;
        }

        // Initialize based on page type
        if (isGlobalSettingsPage()) {
            initializeGlobalSettings();
        } else if (isOnFormSpecificPage()) {
            initializeFormSpecificSettings();
        }
    }

    /**
     * Initialize global settings page
     */
    function initializeGlobalSettings() {
        // Initialize event handlers for global settings
        initializeGlobalEventHandlers();
        
        // Load initial data for global settings
        loadMailchimpAudiences();
    }

    function loadGlobalSettings() {
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_global_settings',
                nonce: mavlersCFMailchimp.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.api_key) {
                    // Store the global API key for use in form-specific pages
                    window.mavlersCFMailchimp.globalApiKey = response.data.api_key;
                }
            },
            error: function(xhr, status, error) {
                // Silently fail - this is expected on global settings page
            }
        });
    }

    function initializeFormSpecificSettings() {
        // Set current form ID
        currentFormId = getFormIdFromUrl();
        
        if (!currentFormId) {
            showMessage('Form ID not found. Please refresh the page.', 'error');
            return;
        }

        // Initialize event handlers
        initializeFormSpecificEventHandlers();
        
        // Load initial data
        loadInitialData();
    }

    /**
     * Load initial data in proper sequence
     */
    function loadInitialData() {
        // For form-specific pages, ensure we have the API key first
        if (isOnFormSpecificPage()) {
            // Load global settings first, then proceed with data loading
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_get_global_settings',
                    nonce: mavlersCFMailchimp.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.api_key) {
                        window.mavlersCFMailchimp.globalApiKey = response.data.api_key;
                    }
                    // Now load audiences and form fields
                    loadMailchimpAudiences(function() {
                        loadFormFields(function() {
                            loadSavedSettings();
                        });
                    });
                },
                error: function() {
                    // If global settings fail, still try to load data
                    loadMailchimpAudiences(function() {
                        loadFormFields(function() {
                            loadSavedSettings();
                        });
                    });
                }
            });
        } else {
            // For global settings page, just load audiences
            loadMailchimpAudiences();
        }
    }

    /**
     * Initialize event handlers for global settings
     */
    function initializeGlobalEventHandlers() {
        // Test connection
        $('#mailchimp-test-connection').on('click', handleTestConnection);

        // Save global settings
        $('#mailchimp-save-global-settings').on('click', handleSaveGlobalSettings);
    }

    /**
     * Initialize event handlers for form-specific settings
     */
    function initializeFormSpecificEventHandlers() {
        // Enable/disable toggle
        $('#mailchimp_enabled').on('change', function() {
            const isEnabled = $(this).is(':checked');
            $('#mailchimp_settings').toggle(isEnabled);
            if (isEnabled) {
                toggleFieldMappingSection();
            }
        });

        // Test connection
        $('#mailchimp-test-connection').on('click', handleTestConnection);

        // Save settings
        $('#mailchimp-save-settings').on('click', function(e) {
            e.preventDefault();
            handleSaveFormSettings();
        });

        // Audience selection
        $('#mailchimp_audience').on('change', function() {
            const audienceId = $(this).val();
            if (audienceId) {
                loadMergeFields(audienceId);
                toggleFieldMappingSection();
            } else {
                $('#mailchimp-field-mapping-section').hide();
            }
        });

        // Also handle the alternative audience selector
        $('#mailchimp_audience_id').on('change', function() {
            const audienceId = $(this).val();
            if (audienceId) {
                loadMergeFields(audienceId);
                toggleFieldMappingSection();
            } else {
                $('#mailchimp-field-mapping-section').hide();
            }
        });

        // Auto map fields
        $('#mailchimp-auto-map').on('click', handleAutoMapFields);

        // Clear mappings
        $('#mailchimp-clear-mappings').on('click', handleClearMappings);
    }

    /**
     * Get form ID from URL
     */
    function getFormIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const formId = urlParams.get('form_id') || urlParams.get('id') || 0;
        
        // If we're on a form edit page, the ID should be available
        if (formId) {
            return parseInt(formId);
        }
        
        // Fallback: try to get from data attribute
        const $formSettings = $('.mailchimp-form-settings');
        if ($formSettings.length) {
            const dataFormId = $formSettings.data('form-id');
            return dataFormId || 0;
        }
        
        return 0;
    }

    /**
     * Handle test connection
     */
    function handleTestConnection() {
        const $button = $('#mailchimp-test-connection');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Testing...');
        
        const apiKey = $('#mailchimp_api_key').val();
        
        if (!apiKey) {
            showMessage('Please enter your Mailchimp API key first.', 'error');
            $button.prop('disabled', false).text(originalText);
            return;
        }

        // First test if AJAX handler is registered
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_test_simple',
                nonce: mavlersCFMailchimp.nonce
            },
            success: function(response) {
                if (response.success) {
                    testMailchimpConnection(apiKey, $button, originalText);
                } else {
                    showMessage('Test connection failed: ' + (response.data || 'Unknown error'), 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                showMessage('Test connection failed: ' + error, 'error');
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Test Mailchimp connection
     */
    function testMailchimpConnection(apiKey, $button, originalText) {
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_test_connection',
                nonce: mavlersCFMailchimp.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Connection successful!', 'success');
                    // Enable audience selection if on form-specific page
                    if (!isGlobalSettings) {
                        $('#mailchimp_audience_id').prop('disabled', false);
                    }
                } else {
                    showMessage('Connection failed: ' + (response.error || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Connection test failed: ' + error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Handle save global settings
     */
    function handleSaveGlobalSettings() {
        const $button = $('#mailchimp-save-global-settings');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Saving...');
        
        const settings = {
            api_key: $('#mailchimp_api_key').val(),
            enable_analytics: $('#mailchimp_enable_analytics').is(':checked'),
            enable_webhooks: $('#mailchimp_enable_webhooks').is(':checked'),
            batch_processing: $('#mailchimp_batch_processing').is(':checked')
        };
        
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_save_global_settings',
                nonce: mavlersCFMailchimp.nonce,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Global settings saved successfully!', 'success');
                } else {
                    showMessage('Failed to save global settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Save failed: ' + error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Handle save form settings
     */
    function handleSaveFormSettings() {
        const $button = $('#mailchimp-save-settings');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Saving...');
        
        // Validate field mappings first
        if (!validateFieldMappings()) {
            $button.prop('disabled', false).text(originalText);
            return;
        }
        
        const fieldMapping = getFieldMapping();
        const audienceId = $('#mailchimp_audience').val() || $('#mailchimp_audience_id').val();
        
        const settings = {
            enabled: $('#mailchimp_enabled').length ? $('#mailchimp_enabled').is(':checked') : true,
            api_key: $('#mailchimp_api_key').val() || '',
            audience_id: audienceId,
            double_optin: $('#mailchimp_double_optin').is(':checked'),
            update_existing: $('#mailchimp_update_existing').is(':checked'),
            tags: $('#mailchimp_tags').val() || '',
            field_mapping: fieldMapping
        };

        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_save_form_settings',
                nonce: mavlersCFMailchimp.nonce,
                form_id: currentFormId,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Settings saved successfully!', 'success');
                } else {
                    showMessage('Failed to save settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Save failed: ' + error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Handle audience change
     */
    function handleAudienceChange() {
        const audienceId = $(this).val();
        if (audienceId) {
            loadMergeFields(audienceId);
        }
    }

    /**
     * Load form fields
     */
    function loadFormFields(callback) {
        if (isGlobalSettings) {
            if (callback) callback();
            return; // Don't load form fields on global settings page
        }
        
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_form_fields',
                nonce: mavlersCFMailchimp.nonce,
                form_id: currentFormId
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Handle different response formats
                    let fields = [];
                    
                    if (response.data.fields) {
                        // Object format with fields property
                        fields = response.data.fields;
                    } else if (Array.isArray(response.data)) {
                        // Direct array format
                        fields = response.data;
                    } else if (typeof response.data === 'object') {
                        // Associative array format - convert to array
                        fields = Object.values(response.data);
                    }
                    
                    formFields = fields;
                    
                    // Update field mapping table if merge fields are also loaded
                    if (mergeFields.length > 0) {
                        updateFieldMappingTable();
                    }
                    
                    if (callback) callback();
        } else {
                    showMessage('Failed to load form fields: ' + (response.data || 'Unknown error'), 'error');
                    if (callback) callback();
                }
            },
            error: function(xhr, status, error) {
                showMessage('Failed to load form fields: ' + error, 'error');
                if (callback) callback();
            }
        });
    }

    /**
     * Load Mailchimp audiences
     */
    function loadMailchimpAudiences(callback) {
        const apiKey = getGlobalApiKey();
        
        if (!apiKey) {
            showMessage('Please configure your Mailchimp API key in global settings first.', 'error');
            if (callback) callback();
            return;
        }
        
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_audiences',
                nonce: mavlersCFMailchimp.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Handle different response formats
                    let audiences = [];
                    
                    if (response.data.audiences) {
                        // Object format with audiences property
                        audiences = response.data.audiences;
                    } else if (Array.isArray(response.data)) {
                        // Direct array format
                        audiences = response.data;
                    } else if (typeof response.data === 'object') {
                        // Associative array format - convert to array
                        audiences = Object.values(response.data);
                    }
                    
                    updateAudienceOptions(audiences);
                    
                    if (callback) callback();
                } else {
                    showMessage('Failed to load audiences: ' + (response.data || 'Unknown error'), 'error');
                    if (callback) callback();
                }
            },
            error: function(xhr, status, error) {
                showMessage('Failed to load audiences: ' + error, 'error');
                if (callback) callback();
            }
        });
    }

    /**
     * Get API key from global settings
     */
    function getGlobalApiKey() {
        // First try to get from form field (for global settings page)
        let apiKey = $('#mailchimp_api_key').val();
        
        // If no API key in form field, try to get from global settings
        if (!apiKey) {
            // For form-specific pages, we need to get from global settings
            // This is a simplified approach - in production you might want to cache this
            apiKey = window.mavlersCFMailchimp?.globalApiKey || '';
        }
        
        return apiKey;
    }

    /**
     * Load merge fields
     */
    function loadMergeFields(audienceId) {
        const apiKey = getGlobalApiKey();
        
        if (!apiKey) {
            showMessage('Please configure your Mailchimp API key in global settings first.', 'error');
            return;
        }
        
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_audience_merge_fields',
                nonce: mavlersCFMailchimp.nonce,
                api_key: apiKey,
                audience_id: audienceId
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Handle different response formats
                    let fields = [];
                    
                    if (response.data.merge_fields) {
                        // Object format with merge_fields property
                        fields = response.data.merge_fields;
                    } else if (Array.isArray(response.data)) {
                        // Direct array format
                        fields = response.data;
                    } else if (typeof response.data === 'object') {
                        // Associative array format - convert to array
                        fields = Object.values(response.data);
                    }
                    
                    mergeFields = fields;
                    
                    // Update field mapping table if form fields are also loaded
                    if (formFields.length > 0) {
                    updateFieldMappingTable();
                    }
                    
                    // Show the field mapping section
                    toggleFieldMappingSection();
                } else {
                    showMessage('Failed to load merge fields: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Failed to load merge fields: ' + error, 'error');
            }
        });
    }

    /**
     * Update audience options
     */
    function updateAudienceOptions(audiences) {
        const $select = $('#mailchimp_audience');
        if (!$select.length) {
            return;
        }
        
        $select.empty().append('<option value="">Select an audience...</option>');
        
        audiences.forEach(function(audience) {
            $select.append(`<option value="${audience.id}">${audience.name} (${audience.member_count} members)</option>`);
        });
    }

    /**
     * Update field mapping table
     */
    function updateFieldMappingTable() {
        if (formFields.length === 0 || mergeFields.length === 0) {
            return;
        }

        const $table = $('#mailchimp-field-mapping-tbody');
        
        if (!$table.length) {
            return;
        }
        
        $table.empty();
            
        formFields.forEach(function(field) {
            const $row = $(`
                <tr class="mailchimp-field-mapping-row">
                    <td>${field.label}</td>
                    <td>
                        <select class="mailchimp-merge-field-select" data-form-field="${field.id}">
                            <option value="">No mapping</option>
                            ${mergeFields.map(function(mergeField) {
                                return `<option value="${mergeField.tag}">${mergeField.name}</option>`;
                            }).join('')}
                        </select>
                    </td>
                    <td class="mapping-status">
                        <span class="status-indicator status-unmapped">Unmapped</span>
                    </td>
                </tr>
            `);
            $table.append($row);
        });
        
        // Add change event handlers for status updates
        $('.mailchimp-merge-field-select').on('change', function() {
            updateMappingStatus($(this));
        });
        
        // Apply any pending field mappings after table is updated
        if (window.pendingFieldMappings) {
            applyFieldMappings(window.pendingFieldMappings);
            window.pendingFieldMappings = null;
        }
    }

    /**
     * Validate field mappings
     */
    function validateFieldMappings() {
        const mappings = getFieldMapping();
        const hasEmailMapping = Object.values(mappings).includes('EMAIL');
        
        if (!hasEmailMapping) {
            showMessage('Email mapping is required for Mailchimp integration to work!', 'warning');
            return false;
        }
        
        return true;
    }

    /**
     * Update mapping status for a field
     */
    function updateMappingStatus($select) {
        const $row = $select.closest('tr');
        const $status = $row.find('.mapping-status .status-indicator');
        const selectedValue = $select.val();
        
        if (selectedValue) {
            $status.removeClass('status-unmapped').addClass('status-mapped').text('Mapped');
            } else {
            $status.removeClass('status-mapped').addClass('status-unmapped').text('Unmapped');
        }
    }

    /**
     * Get field mapping
     */
    function getFieldMapping() {
        const mapping = {};
        $('.mailchimp-merge-field-select').each(function() {
            const formField = $(this).data('form-field');
            const mergeField = $(this).val();
            if (mergeField) {
                mapping[formField] = mergeField;
            }
        });
        return mapping;
    }

    /**
     * Handle auto map fields
     */
    function handleAutoMapFields() {
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_auto_map_fields',
                nonce: mavlersCFMailchimp.nonce,
                form_id: currentFormId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Fields auto-mapped successfully!', 'success');
                    // Update the mapping table with auto-mapped fields
                    if (response.data.mapping) {
                        applyAutoMapping(response.data.mapping);
                    }
                } else {
                    showMessage('Auto-mapping failed: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Auto-mapping failed: ' + error, 'error');
            }
        });
    }

    /**
     * Apply auto mapping
     */
    function applyAutoMapping(mapping) {
        Object.keys(mapping).forEach(function(formField) {
            const mergeField = mapping[formField];
            $(`.mailchimp-merge-field-select[data-form-field="${formField}"]`).val(mergeField);
        });
    }

    /**
     * Handle clear mappings
     */
    function handleClearMappings() {
        if (confirm('Are you sure you want to clear all field mappings?')) {
            $('.mailchimp-merge-field-select').val('');
            showMessage('Field mappings cleared!', 'success');
        }
    }

    /**
     * Toggle field mapping section
     */
    function toggleFieldMappingSection() {
        // Check if we have an audience selected
        const audienceId = $('#mailchimp_audience').val() || $('#mailchimp_audience_id').val();
        
        if (audienceId) {
            $('#mailchimp-field-mapping-section').show();
            } else {
            $('#mailchimp-field-mapping-section').hide();
        }
    }

    /**
     * Load saved settings
     */
    function loadSavedSettings() {
        if (!currentFormId) {
            return;
        }
        
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_form_settings',
                nonce: mavlersCFMailchimp.nonce,
                form_id: currentFormId
            },
            success: function(response) {
                if (response.success && response.data) {
                    applySavedSettings(response.data);
                }
            },
            error: function(xhr, status, error) {
                showMessage('Failed to load saved settings: ' + error, 'error');
            }
        });
    }

    function applySavedSettings(settings) {
        // Apply basic settings
        if (settings.enabled !== undefined) {
            $('#mailchimp_enabled').prop('checked', settings.enabled === 'true' || settings.enabled === true);
        }
        
        if (settings.audience_id) {
            $('#mailchimp_audience').val(settings.audience_id);
            $('#mailchimp_audience_id').val(settings.audience_id);
            
            // Load merge fields for this audience
            loadMergeFields(settings.audience_id);
            
            // Show field mapping section immediately
            toggleFieldMappingSection();
        }
        
        if (settings.double_optin !== undefined) {
            $('#mailchimp_double_optin').prop('checked', settings.double_optin === 'true' || settings.double_optin === true);
        }
        
        if (settings.update_existing !== undefined) {
            $('#mailchimp_update_existing').prop('checked', settings.update_existing === 'true' || settings.update_existing === true);
        }
        
        if (settings.tags) {
            $('#mailchimp_tags').val(settings.tags);
        }
        
        // Apply field mappings if available
        if (settings.field_mapping && typeof settings.field_mapping === 'object' && Object.keys(settings.field_mapping).length > 0) {
            // Store pending field mappings to be applied after table is updated
            window.pendingFieldMappings = settings.field_mapping;
        }
    }

    /**
     * Apply field mappings to the form
     */
    function applyFieldMappings(mappings) {
        if (!mappings || typeof mappings !== 'object') {
            return;
        }
        
        Object.keys(mappings).forEach(function(formField) {
            const mergeField = mappings[formField];
            const $select = $(`.mailchimp-merge-field-select[data-form-field="${formField}"]`);
            if ($select.length) {
                $select.val(mergeField);
                updateMappingStatus($select);
            }
        });
    }

    /**
     * Show message in top-right corner
     */
    function showMessage(message, type) {
        // Remove existing messages
        $('.mavlers-cf-message').remove();
        
        // Create message element
        const $message = $(`
            <div class="mavlers-cf-message mavlers-cf-message-${type}">
                <span class="mavlers-cf-message-text">${message}</span>
                <button class="mavlers-cf-message-close">&times;</button>
            </div>
        `);
        
        // Add to page
        $('body').append($message);
        
        // Show message
        setTimeout(function() {
            $message.addClass('mavlers-cf-message-show');
        }, 100);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            hideMessage($message);
        }, 5000);
        
        // Close button
        $message.find('.mavlers-cf-message-close').on('click', function() {
            hideMessage($message);
        });
    }

    /**
     * Hide message
     */
    function hideMessage($message) {
        $message.removeClass('mavlers-cf-message-show');
        setTimeout(function() {
            $message.remove();
        }, 300);
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        initializeMailchimpSettings();
    });

})(jQuery); 