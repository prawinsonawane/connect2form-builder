/**
 * Mailchimp Form Settings JavaScript - Optimized Version
 * 
 * Handles easy field mapping with real form fields and Mailchimp audience fields
 */

(function($) {
    'use strict';

    // Global variables
    let currentFormId = 0;
    let currentAudienceId = '';
    let formFields = {};
    let mailchimpFields = {};
    let currentMappings = {};
    let isLoadingInitialData = false;
    let lastLoadMailchimpFieldsCall = 0;
    let loadMailchimpFieldsTimeout = null;
    let isInitialized = false;

    // Configuration
    const CONFIG = {
        DEBOUNCE_DELAY: 300,
        FIELD_LOAD_DELAY: 2000,
        MAPPING_APPLY_DELAY: 500,
        AJAX_TIMEOUT: 30000,
        MIN_CALL_INTERVAL: 2000
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (isInitialized) return;
        
        initializeMailchimpFormSettings();
        isInitialized = true;
    });

    /**
     * Initialize the Mailchimp form settings
     */
    function initializeMailchimpFormSettings() {
        // Get form ID from global or data attribute
        currentFormId = window.mailchimpFormId || $('.mailchimp-form-settings').data('form-id') || 0;
        
        if (!currentFormId) {
            return;
        }
        
        // Load initial settings
        loadInitialSettings();
        
        // Bind events
        bindEvents();
        
        // Load form fields immediately
        loadFormFields();
        
        // Load audiences if integration is enabled or if we have saved settings
        if (shouldLoadAudiences()) {
            setTimeout(() => loadAudiences(), 100);
        }
    }

    /**
     * Check if audiences should be loaded
     */
    function shouldLoadAudiences() {
        const isEnabled = $('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked');
        const hasSavedSettings = window.mailchimpFormSettings && window.mailchimpFormSettings.audience_id;
        return isEnabled || hasSavedSettings;
    }



    /**
     * Load initial settings from server
     */
    function loadInitialSettings() {
        if (!window.mailchimpFormSettings) return;
        
        const settings = window.mailchimpFormSettings;
        
        // Set form values with proper boolean handling
        $('#mailchimp_double_optin').prop('checked', Boolean(settings.double_optin));
        $('#mailchimp_update_existing').prop('checked', Boolean(settings.update_existing));
        $('#mailchimp_tags').val(settings.tags || '');
        
        // Set audience
        if (settings.audience_id) {
            currentAudienceId = settings.audience_id;
            
            // Try to set the audience dropdown immediately if it exists
            const $audienceSelect = $('#mailchimp_audience');
            if ($audienceSelect.length) {
                $audienceSelect.val(settings.audience_id);
            }
        }
        
        // Set mappings
        currentMappings = settings.field_mapping || {};
        
        // Set integration enabled state
        if (settings.enabled) {
            $('.integration-enable-checkbox[data-integration="mailchimp"]').prop('checked', true);
            $('#mailchimp-form-settings').slideDown();
            $('#mailchimp-field-mapping-section').slideDown();
            
            // If we have a saved audience, load audiences to populate the dropdown
            if (settings.audience_id) {
                loadAudiences();
            }
            
            // Apply mappings after a delay to ensure everything is loaded
            setTimeout(() => applyExistingMappings(), CONFIG.MAPPING_APPLY_DELAY);
        }
    }
    
    /**
     * Load saved settings into the form (called when integration is enabled)
     */
    function loadSavedSettings() {
        if (!window.mailchimpFormSettings) return;
        
        const settings = window.mailchimpFormSettings;
        
        // Load audience selection
        if (settings.audience_id) {
            $('#mailchimp_audience').val(settings.audience_id);
            currentAudienceId = settings.audience_id;
        }
        
        // Load checkboxes
        if (settings.double_optin !== undefined) {
            $('#mailchimp_double_optin').prop('checked', Boolean(settings.double_optin));
        }
        
        if (settings.update_existing !== undefined) {
            $('#mailchimp_update_existing').prop('checked', Boolean(settings.update_existing));
        }
        
        // Load tags
        if (settings.tags) {
            $('#mailchimp_tags').val(settings.tags);
        }
        
        // Load field mappings
        if (settings.field_mapping && Object.keys(settings.field_mapping).length > 0) {
            currentMappings = settings.field_mapping;
            
            // Apply mappings after a short delay to ensure dropdowns are populated
            setTimeout(() => applyExistingMappings(), CONFIG.MAPPING_APPLY_DELAY);
        }
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Integration enable/disable
        $(document).on('change', '.integration-enable-checkbox[data-integration="mailchimp"]', function() {
            const isEnabled = $(this).is(':checked');
            
            if (isEnabled && !currentAudienceId) {
                loadAudiences();
            }
            
            // Show/hide field mapping section based on integration status
            $('#mailchimp-field-mapping-section')[isEnabled ? 'slideDown' : 'slideUp']();
        });

        // Audience selection
        $(document).on('change', '#mailchimp_audience', function() {
            const selectedValue = $(this).val();
            currentAudienceId = selectedValue;
            
            if (currentAudienceId && currentAudienceId !== '0' && currentAudienceId !== '') {
                loadMailchimpFields(currentAudienceId);
                
                // Only auto-save if not loading initial data
                if (!isLoadingInitialData) {
                    autoSaveAudienceSelection(currentAudienceId);
                }
                
                $('#mailchimp-field-mapping-section').slideDown();
            } else {
                $('#mailchimp-field-mapping-section').slideUp();
            }
        });

        // Refresh audiences
        $(document).on('click', '#refresh_audiences', loadAudiences);

        // Auto map fields
        $(document).on('click', '#mailchimp-auto-map-fields', autoMapFields);

        // Clear mappings
        $(document).on('click', '#mailchimp-clear-mappings', clearAllMappings);

        // Field mapping dropdowns
        $(document).on('change', '.mailchimp-field-select', function() {
            const formFieldId = $(this).data('form-field');
            const mailchimpField = $(this).val();
            
            if (mailchimpField) {
                currentMappings[formFieldId] = mailchimpField;
            } else {
                delete currentMappings[formFieldId];
            }
            
            updateMappingStatus();
        });

        // Form submission
        $(document).on('submit', '#mailchimp-form-settings', function(e) {
            e.preventDefault();
            saveFormSettings();
        });
    }

    /**
     * Load form fields from current form
     */
    function loadFormFields() {
        if (!currentFormId) {
            return;
        }

        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_form_fields',
                form_id: currentFormId,
                nonce: mavlersCFMailchimp.nonce
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    formFields = response.data;
                    updateFieldMappingTable();
                } else {
                    showMessage('Failed to load form fields: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Network error loading form fields', 'error');
            }
        });
    }

    /**
     * Load audiences from Mailchimp API
     */
    function loadAudiences() {
        const $audienceSelect = $('#mailchimp_audience');
        const $loadingIndicator = $('#mailchimp_loading');
        
        if (!$audienceSelect.length) {
            return;
        }
        
        // Show loading state
        $loadingIndicator.show();
        $audienceSelect.prop('disabled', true);
        
        // Get API key
        const apiKey = getApiKey();
        
        if (!apiKey) {
            showMessage('No API key found. Please configure Mailchimp settings first.', 'error');
            $loadingIndicator.hide();
            $audienceSelect.prop('disabled', false);
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
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    const audiences = response.data;
                    populateAudienceSelect(audiences);
                    
                    // Auto-select saved audience if available
                    if (window.mailchimpFormSettings?.audience_id) {
                        const savedAudienceId = window.mailchimpFormSettings.audience_id;
                        
                        if ($audienceSelect.find(`option[value="${savedAudienceId}"]`).length) {
                            $audienceSelect.val(savedAudienceId);
                            $audienceSelect.trigger('change');
                        }
                    }
                    
                    showMessage('Audiences loaded successfully', 'success');
                } else {
                    showMessage('Failed to load audiences: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred while loading audiences';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    // Ignore parsing errors
                }
                
                showMessage(errorMessage, 'error');
            },
            complete: function() {
                $loadingIndicator.hide();
                $audienceSelect.prop('disabled', false);
            }
        });
    }

    /**
     * Get API key from various sources
     */
    function getApiKey() {
        // Try global settings first
        if (window.mailchimpGlobalSettings?.api_key) {
            return window.mailchimpGlobalSettings.api_key;
        }
        
        // Try mavlersCFMailchimp global settings
        if (window.mavlersCFMailchimp?.globalSettings?.api_key) {
            return window.mavlersCFMailchimp.globalSettings.api_key;
        }
        
        // Try form settings
        if (window.mailchimpFormSettings?.api_key) {
            return window.mailchimpFormSettings.api_key;
        }
        
        // Try form field
        const $apiKeyField = $('input[name="api_key"], #mailchimp_api_key');
        if ($apiKeyField.length) {
            return $apiKeyField.val();
        }
        
        return '';
    }

    /**
     * Populate audience select dropdown
     */
    function populateAudienceSelect(audiences) {
        const $select = $('#mailchimp_audience');
        
        $select.empty().append('<option value="">' + (mavlersCFMailchimp.strings.selectAudience || 'Select an audience...') + '</option>');

        // Handle both array and object formats
        if (Array.isArray(audiences)) {
            $.each(audiences, function(index, audience) {
                const option = $('<option></option>')
                    .val(audience.id)
                    .text(audience.name + ' (' + audience.member_count + ' members)');
                $select.append(option);
            });
        } else {
            $.each(audiences, function(id, audience) {
                const option = $('<option></option>')
                    .val(id)
                    .text(audience.name + ' (' + audience.member_count + ' members)');
                $select.append(option);
            });
        }
        
        // If we have a saved audience ID, select it
        if (window.mailchimpFormSettings?.audience_id) {
            const savedAudienceId = window.mailchimpFormSettings.audience_id;
            $select.val(savedAudienceId);
            currentAudienceId = savedAudienceId;
            
            // Load Mailchimp fields for this audience after a short delay
            setTimeout(() => loadMailchimpFields(currentAudienceId), CONFIG.MAPPING_APPLY_DELAY);
        }
    }

    /**
     * Auto-save audience selection
     */
    function autoSaveAudienceSelection(audienceId) {
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'save_mailchimp_audience_selection',
                nonce: mavlersCFMailchimp.nonce,
                form_id: currentFormId,
                audience_id: audienceId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            error: function() {
                // Auto-save failed silently
            }
        });
    }

    /**
     * Load Mailchimp fields for selected audience
     */
    function loadMailchimpFields(audienceId) {
        // Prevent duplicate calls within minimum interval
        const now = Date.now();
        if (now - lastLoadMailchimpFieldsCall < CONFIG.MIN_CALL_INTERVAL) {
            return;
        }
        
        // Clear any existing timeout
        if (loadMailchimpFieldsTimeout) {
            clearTimeout(loadMailchimpFieldsTimeout);
        }
        
        // Debounce the call
        loadMailchimpFieldsTimeout = setTimeout(() => {
            loadMailchimpFieldsInternal(audienceId);
        }, CONFIG.DEBOUNCE_DELAY);
    }
    
    /**
     * Internal function to actually load Mailchimp fields
     */
    function loadMailchimpFieldsInternal(audienceId) {
        if (!audienceId) {
            showMessage('No audience selected', 'error');
            return;
        }
        
        if (!window.mavlersCFMailchimp?.ajaxUrl) {
            showMessage('Configuration error: AJAX URL not available', 'error');
            return;
        }
        
        // Update last call time
        lastLoadMailchimpFieldsCall = Date.now();
        
        // Show loading state
        $('#mailchimp_loading').show();
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_merge_fields',
                audience_id: audienceId,
                nonce: window.mavlersCFMailchimp.nonce
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data && Object.keys(response.data).length > 0) {
                    mailchimpFields = response.data;
                    updateFieldMappingTable();
                    showMessage('Mailchimp fields loaded successfully (' + Object.keys(mailchimpFields).length + ' fields)', 'success');
                } else {
                    let errorMessage = 'Failed to load Mailchimp fields';
                    if (response.message) {
                        errorMessage += ': ' + response.message;
                    } else if (!response.data || Object.keys(response.data).length === 0) {
                        errorMessage += ': No fields found for this audience';
                    } else {
                        errorMessage += ': Unknown error';
                    }
                    
                    showMessage(errorMessage, 'error');
                    updateFieldMappingTable();
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error loading Mailchimp fields';
                
                if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 404) {
                    errorMessage = 'API endpoint not found. Please check configuration.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please check WordPress error logs.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Connection failed. Please check your internet connection.';
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (status === 'parsererror') {
                    errorMessage = 'Invalid response format from server.';
                }
                
                showMessage(errorMessage + ' (Status: ' + xhr.status + ')', 'error');
                updateFieldMappingTable();
            },
            complete: function() {
                $('#mailchimp_loading').hide();
            }
        });
    }

    /**
     * Update the field mapping table with real form fields and Mailchimp fields
     */
    function updateFieldMappingTable() {
        const $tbody = $('#mailchimp-field-mapping-tbody');
        $tbody.empty();
        
        if (!formFields || Object.keys(formFields).length === 0) {
            $tbody.append(`
                <tr class="no-fields-row">
                    <td colspan="4" style="text-align: center; padding: 20px; color: #666;">
                        No form fields found. Please check your form configuration.
                    </td>
                </tr>
            `);
            return;
        }
        
        // Add each form field as a row
        $.each(formFields, function(fieldId, field) {
            const isRequired = field.required || false;
            const currentMapping = currentMappings[fieldId] || '';
            
            const $row = $(`
                <tr class="field-mapping-row" data-form-field="${fieldId}">
                    <td class="form-field-column">
                        <div class="form-field-info">
                            <div class="form-field-name">
                                ${field.label || fieldId}
                                ${isRequired ? '<span class="form-field-required">*</span>' : ''}
                            </div>
                            <div class="form-field-type">${field.type}</div>
                        </div>
                    </td>
                    <td class="arrow-column">
                        <span class="mapping-arrow">→</span>
                    </td>
                    <td class="mailchimp-field-column">
                        <select class="mailchimp-field-select" data-form-field="${fieldId}">
                            <option value="">-- Select Mailchimp Field --</option>
                        </select>
                    </td>
                    <td class="status-column">
                        <span class="mapping-status-badge unmapped">Unmapped</span>
                    </td>
                </tr>
            `);
            
            $tbody.append($row);
        });
        
        // Populate Mailchimp field dropdowns
        populateMailchimpFieldSelects();
        
        // Apply existing mappings after dropdowns are populated
        setTimeout(() => {
            applyExistingMappings();
            updateMappingStatus();
        }, CONFIG.MAPPING_APPLY_DELAY);
    }

    /**
     * Populate Mailchimp field select dropdowns
     */
    function populateMailchimpFieldSelects() {
        $('.mailchimp-field-select').each(function() {
            const $select = $(this);
            
            // Clear existing options except the first one
            $select.find('option:not(:first)').remove();
            
            // Always add the Email field first (required by Mailchimp)
            $select.append('<option value="email_address">📧 Email Address (Required)</option>');
            
            // Add fields from Mailchimp API
            if (mailchimpFields && Object.keys(mailchimpFields).length > 0) {
                $.each(mailchimpFields, function(tag, field) {
                    if (tag !== 'EMAIL') { // Skip EMAIL as we already added email_address
                        const icon = getFieldIcon(field.type);
                        const requiredText = field.required ? ' (Required)' : '';
                        const optionText = `${icon} ${field.name}${requiredText}`;
                        
                        $select.append(`<option value="${tag}">${optionText}</option>`);
                    }
                });
            } else {
                // Add common fields if API fields not available
                $select.append('<option value="FNAME">👤 First Name</option>');
                $select.append('<option value="LNAME">👤 Last Name</option>');
                $select.append('<option value="PHONE">📞 Phone Number</option>');
                $select.append('<option value="ADDRESS">🏠 Address</option>');
                $select.append('<option value="CITY">🏙️ City</option>');
                $select.append('<option value="STATE">🗺️ State</option>');
                $select.append('<option value="ZIP">📮 ZIP Code</option>');
                $select.append('<option value="COUNTRY">🌍 Country</option>');
            }
        });
    }

    /**
     * Get icon for field type
     */
    function getFieldIcon(type) {
        const icons = {
            'text': '📝',
            'email': '📧',
            'number': '🔢',
            'phone': '📞',
            'date': '📅',
            'url': '🔗',
            'address': '🏠',
            'dropdown': '📋',
            'radio': '⚪'
        };
        return icons[type] || '📝';
    }

    /**
     * Apply existing mappings to the table
     */
    function applyExistingMappings() {
        let appliedCount = 0;
        
        $.each(currentMappings, function(formField, mailchimpField) {
            const $select = $(`.mailchimp-field-select[data-form-field="${formField}"]`);
            const $row = $(`.field-mapping-row[data-form-field="${formField}"]`);
            
            if ($select.length) {
                $select.val(mailchimpField);
                appliedCount++;
                
                // Update status badge
                const $badge = $row.find('.mapping-status-badge');
                if ($badge.length) {
                    $badge.removeClass('unmapped').addClass('mapped').text('Mapped');
                }
            }
        });
        
        // Update mapping status display
        updateMappingStatus();
    }

    /**
     * Auto-map fields based on common naming patterns
     */
    function autoMapFields() {
        // Clear existing mappings first
        currentMappings = {};
        
        // Auto-mapping rules
        const mappingRules = [
            { patterns: ['email', 'email_address', 'user_email', 'e_mail'], target: 'email_address' },
            { patterns: ['first_name', 'firstname', 'fname', 'first'], target: 'FNAME' },
            { patterns: ['last_name', 'lastname', 'lname', 'last'], target: 'LNAME' },
            { patterns: ['phone', 'phone_number', 'telephone', 'tel'], target: 'PHONE' }
        ];
        
        // Apply auto-mapping
        $.each(formFields, function(fieldId, field) {
            const fieldName = (field.name || field.label || fieldId).toLowerCase().replace(/[^a-z0-9]/g, '_');
            
            for (let rule of mappingRules) {
                for (let pattern of rule.patterns) {
                    if (fieldName.includes(pattern)) {
                        currentMappings[fieldId] = rule.target;
                        break;
                    }
                }
                if (currentMappings[fieldId]) break;
            }
        });
        
        // Update the UI
        applyExistingMappings();
        updateMappingStatus();
        
        showMessage('Fields auto-mapped based on naming patterns!', 'success');
    }

    /**
     * Clear all field mappings
     */
    function clearAllMappings() {
        currentMappings = {};
        
        // Clear all dropdowns
        $('.mailchimp-field-select').val('');
        
        // Update all status badges
        $('.mapping-status-badge').removeClass('mapped').addClass('unmapped').text('Unmapped');
        
        updateMappingStatus();
        
        showMessage('All mappings cleared.', 'info');
    }

    /**
     * Update mapping status display
     */
    function updateMappingStatus() {
        const mappingCount = Object.keys(currentMappings).length;
        const totalFields = Object.keys(formFields).length;
        
        // Update count
        $('.mailchimp-mapping-count').text(`${mappingCount} of ${totalFields} fields mapped`);
        
        // Update status badges
        $('.field-mapping-row').each(function() {
            const formField = $(this).data('form-field');
            const $badge = $(this).find('.mapping-status-badge');
            
            if (currentMappings[formField]) {
                $badge.removeClass('unmapped required').addClass('mapped').text('Mapped');
            } else {
                const isEmailField = formFields[formField] && formFields[formField].type === 'email';
                if (isEmailField) {
                    $badge.removeClass('unmapped mapped').addClass('required').text('Required');
                } else {
                    $badge.removeClass('mapped required').addClass('unmapped').text('Unmapped');
                }
            }
        });
    }

    /**
     * Collect current field mappings from the UI
     */
    function collectCurrentMappings() {
        const mappings = {};
        
        $('.mailchimp-field-select').each(function() {
            const $select = $(this);
            const formField = $select.data('form-field');
            const selectedValue = $select.val();
            
            if (selectedValue && selectedValue !== '') {
                mappings[formField] = selectedValue;
            }
        });
        
        return mappings;
    }
    
    /**
     * Save form settings with field mappings
     */
    function saveFormSettings() {
        const $form = $('#mailchimp-form-settings');
        const $submit = $form.find('button[type="submit"]');
        const $status = $('#save_status');
        
        // Show loading
        $submit.prop('disabled', true).text('Saving...');
        $status.removeClass('show success error');
        
        // Collect current mappings from UI
        const uiMappings = collectCurrentMappings();
        
        // Use UI mappings if available, otherwise use currentMappings
        const fieldMappings = Object.keys(uiMappings).length > 0 ? uiMappings : currentMappings;
        
        // Collect form data
        const formData = {
            action: 'mailchimp_save_form_settings',
            nonce: mavlersCFMailchimp.nonce,
            form_id: currentFormId,
            enabled: $('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked'),
            audience_id: $('#mailchimp_audience').val(),
            double_optin: $('#mailchimp_double_optin').is(':checked'),
            update_existing: $('#mailchimp_update_existing').is(':checked'),
            tags: $('#mailchimp_tags').val(),
            field_mapping: fieldMappings,
            form_specific_id: currentFormId,
            timestamp: Date.now()
        };
        
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: formData,
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response && response.success) {
                    $status.addClass('show success').text('Settings saved successfully!');
                    
                    // Update global settings
                    window.mailchimpFormSettings = {
                        enabled: formData.enabled,
                        audience_id: formData.audience_id,
                        double_optin: formData.double_optin,
                        update_existing: formData.update_existing,
                        tags: formData.tags,
                        field_mapping: formData.field_mapping
                    };
                } else {
                    const errorMessage = response?.data || response?.message || 'Failed to save settings';
                    $status.addClass('show error').text(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response?.data) {
                        errorMessage = response.data;
                    } else if (response?.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // Ignore parsing errors
                }
                
                $status.addClass('show error').text(errorMessage);
            },
            complete: function() {
                $submit.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Mailchimp Settings');
                
                setTimeout(() => {
                    $status.removeClass('show');
                }, 3000);
            }
        });
    }

    /**
     * Show message to user
     */
    function showMessage(message, type = 'info') {
        const $messages = $('#mailchimp_messages');
        const messageClass = `message-${type}`;
        
        const $message = $(`
            <div class="integration-message ${messageClass}">
                <span class="message-text">${message}</span>
                <button type="button" class="message-close" onclick="$(this).parent().fadeOut()">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        `);
        
        $messages.append($message);
        
        // Auto-remove messages based on type
        const autoRemoveDelay = type === 'success' ? 5000 : type === 'info' ? 3000 : 0;
        if (autoRemoveDelay > 0) {
            setTimeout(() => {
                $message.fadeOut();
            }, autoRemoveDelay);
        }
    }



})(jQuery); 