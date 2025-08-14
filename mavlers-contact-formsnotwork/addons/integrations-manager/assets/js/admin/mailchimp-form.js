/**
 * Mailchimp Form Settings JavaScript - Simple & User-Friendly
 * 
 * Handles easy field mapping with real form fields and Mailchimp audience fields
 */

(function($) {
    'use strict';

    // Debug logging
    console.log('Mailchimp Form JS: Script loaded');
    console.log('Mailchimp Form JS: mavlersCFMailchimp object:', typeof mavlersCFMailchimp !== 'undefined' ? 'available' : 'undefined');
    if (typeof mavlersCFMailchimp !== 'undefined') {
        console.log('Mailchimp Form JS: ajaxUrl:', mavlersCFMailchimp.ajaxUrl);
        console.log('Mailchimp Form JS: nonce:', mavlersCFMailchimp.nonce);
    }

    // Global variables
    let currentFormId = 0;
    let currentAudienceId = '';
    let formFields = {};
    let mailchimpFields = {};
    let currentMappings = {};
    let isLoadingInitialData = false;
    let lastLoadMailchimpFieldsCall = 0; // Track last call time
    let loadMailchimpFieldsTimeout = null; // Debounce timeout

    // Initialize when document is ready
    $(document).ready(function() {
        initializeMailchimpFormSettings();
    });

    /**
     * Initialize the Mailchimp form settings
     */
    function initializeMailchimpFormSettings() {
        // Get form ID from global or data attribute
        currentFormId = window.mailchimpFormId || $('.mailchimp-form-settings').data('form-id') || 0;
        
        // Verify we're working with the correct form
        if (window.mailchimpFormSettings && window.mailchimpFormSettings.form_id && window.mailchimpFormSettings.form_id !== currentFormId) {
            // Form ID mismatch warning
        }
        
        // Test AJAX functionality
        testAjaxConnection();
        
        // Load initial settings
        loadInitialSettings();
        
        // Bind events
        bindEvents();
        
        // Load form fields immediately
        loadFormFields();
        
        // Load audiences if integration is enabled or if we have saved settings
        if ($('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked') || (window.mailchimpFormSettings && window.mailchimpFormSettings.audience_id)) {
            // Add a small delay to ensure the DOM is ready
            setTimeout(function() {
                loadAudiences();
            }, 100);
        }
    }

    /**
     * Test AJAX connection
     */
    function testAjaxConnection() {
        if (!window.mavlersCFMailchimp || !window.mavlersCFMailchimp.ajaxUrl) {
            return;
        }
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_test_ajax',
                nonce: window.mavlersCFMailchimp.nonce
            },
            success: function(response) {
                // AJAX connection is working
            },
            error: function() {
                showMessage('AJAX connection test failed', 'error');
            }
        });
    }

    /**
     * Load initial settings
     */
    function loadInitialSettings() {
        const settings = window.mailchimpFormSettings || {};
        
        if (settings.enabled) {
            $('.integration-enable-checkbox[data-integration="mailchimp"]').prop('checked', true);
            $('#mailchimp-settings-section').show();
            
            if (settings.audience_id) {
                currentAudienceId = settings.audience_id;
            }
            
            if (settings.double_optin !== undefined) {
                $('#mailchimp-double-optin').prop('checked', Boolean(settings.double_optin));
            }
            
            if (settings.update_existing !== undefined) {
                $('#mailchimp-update-existing').prop('checked', Boolean(settings.update_existing));
            }
            
            if (settings.tags) {
                $('#mailchimp-tags').val(settings.tags);
            }
            
            if (settings.field_mapping) {
                currentMappings = { ...settings.field_mapping };
            }
        }
    }

    /**
     * Load saved settings
     */
    function loadSavedSettings() {
        if (!window.mailchimpFormSettings) {
            return;
        }
        
        const settings = window.mailchimpFormSettings;
        
        if (settings.audience_id) {
            currentAudienceId = settings.audience_id;
        }
        
        if (settings.double_optin !== undefined) {
            $('#mailchimp-double-optin').prop('checked', Boolean(settings.double_optin));
        }
        
        if (settings.update_existing !== undefined) {
            $('#mailchimp-update-existing').prop('checked', Boolean(settings.update_existing));
        }
        
        if (settings.tags) {
            $('#mailchimp-tags').val(settings.tags);
        }
        
        if (settings.field_mapping) {
            currentMappings = { ...settings.field_mapping };
        }
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Integration enable/disable
        $('.integration-enable-checkbox[data-integration="mailchimp"]').on('change', function() {
            const isEnabled = $(this).is(':checked');
            $('#mailchimp-settings-section').toggle(isEnabled);
            
            if (isEnabled) {
                loadAudiences();
            }
        });

        // Audience selection
        $('#mailchimp-audience-select').on('change', function() {
            const selectedValue = $(this).val();
            
            if (selectedValue && selectedValue !== currentAudienceId) {
                currentAudienceId = selectedValue;
                loadMailchimpFields(currentAudienceId);
            }
        });

        // Form settings save
        $('#mailchimp-form-settings').on('submit', function(e) {
            e.preventDefault();
            saveFormSettings();
        });

        // Auto-map fields
        $('#mailchimp-auto-map').on('click', function() {
            autoMapFields();
        });

        // Clear mappings
        $('#mailchimp-clear-mappings').on('click', function() {
            clearAllMappings();
        });

        // Field mapping changes
        $(document).on('change', '.mailchimp-field-select', function() {
            updateMappingStatus();
        });
    }

    /**
     * Load form fields
     */
    function loadFormFields() {
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_get_form_fields',
                nonce: window.mavlersCFMailchimp.nonce,
                form_id: currentFormId
            },
            success: function(response) {
                if (response.success && response.data) {
                    formFields = response.data;
                    updateFieldMappingTable();
                }
            },
            error: function() {
                showMessage('Failed to load form fields', 'error');
            }
        });
    }

    /**
     * Load Mailchimp audiences
     */
    function loadAudiences() {
        const apiKey = $('#mailchimp-api-key').val();
        
        if (!apiKey) {
            return;
        }
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_audiences',
                nonce: window.mavlersCFMailchimp.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success && response.data) {
                    populateAudienceSelect(response.data);
                } else {
                    showMessage('Failed to load audiences', 'error');
                }
            },
            error: function() {
                showMessage('Failed to load audiences', 'error');
            }
        });
    }

    /**
     * Populate audience select dropdown
     */
    function populateAudienceSelect(audiences) {
        const select = $('#mailchimp-audience-select');
        let options = '<option value="">Select an audience...</option>';
        
        audiences.forEach(function(audience) {
            const selected = audience.id === currentAudienceId ? 'selected' : '';
            options += `<option value="${audience.id}" ${selected}>${audience.name}</option>`;
        });
        
        select.html(options);
        
        // If we have a current audience ID, load its fields
        if (currentAudienceId) {
            loadMailchimpFields(currentAudienceId);
        }
    }

    /**
     * Auto-save audience selection
     */
    function autoSaveAudienceSelection(audienceId) {
        if (!audienceId) {
            return;
        }
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_save_audience_selection',
                nonce: window.mavlersCFMailchimp.nonce,
                form_id: currentFormId,
                audience_id: audienceId
            },
            success: function(response) {
                // Audience selection saved
            }
        });
    }

    /**
     * Load Mailchimp fields for an audience
     */
    function loadMailchimpFields(audienceId) {
        if (!audienceId) {
            return;
        }
        
        // Debounce the call
        if (loadMailchimpFieldsTimeout) {
            clearTimeout(loadMailchimpFieldsTimeout);
        }
        
        loadMailchimpFieldsTimeout = setTimeout(function() {
            loadMailchimpFieldsInternal(audienceId);
        }, 300);
    }

    /**
     * Internal function to load Mailchimp fields
     */
    function loadMailchimpFieldsInternal(audienceId) {
        const apiKey = $('#mailchimp-api-key').val();
        
        if (!apiKey) {
            return;
        }
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_audience_fields',
                nonce: window.mavlersCFMailchimp.nonce,
                api_key: apiKey,
                audience_id: audienceId
            },
            success: function(response) {
                if (response.success && response.data) {
                    mailchimpFields = response.data;
                    updateFieldMappingTable();
                    populateMailchimpFieldSelects();
                    applyExistingMappings();
                } else {
                    showMessage('Failed to load audience fields', 'error');
                }
            },
            error: function() {
                showMessage('Failed to load audience fields', 'error');
            }
        });
    }

    /**
     * Update field mapping table
     */
    function updateFieldMappingTable() {
        const tableBody = $('#mailchimp-field-mapping-table tbody');
        let html = '';
        
        if (Object.keys(formFields).length === 0) {
            html = '<tr><td colspan="3">No form fields available</td></tr>';
        } else {
            Object.keys(formFields).forEach(function(fieldId) {
                const field = formFields[fieldId];
                const currentValue = currentMappings[fieldId] || '';
                
                html += `
                    <tr>
                        <td>
                            <span class="field-icon">${getFieldIcon(field.type)}</span>
                            ${field.label}
                        </td>
                        <td>
                            <select class="mailchimp-field-select" data-field-id="${fieldId}">
                                <option value="">-- Select Mailchimp Field --</option>
                                ${Object.keys(mailchimpFields).map(mcFieldId => {
                                    const mcField = mailchimpFields[mcFieldId];
                                    const selected = currentValue === mcFieldId ? 'selected' : '';
                                    return `<option value="${mcFieldId}" ${selected}>${mcField.name}</option>`;
                                }).join('')}
                            </select>
                        </td>
                        <td>${field.type}</td>
                    </tr>
                `;
            });
        }
        
        tableBody.html(html);
        updateMappingStatus();
    }

    /**
     * Populate Mailchimp field selects
     */
    function populateMailchimpFieldSelects() {
        $('.mailchimp-field-select').each(function() {
            const fieldId = $(this).data('field-id');
            const currentValue = currentMappings[fieldId] || '';
            
            if (currentValue) {
                $(this).val(currentValue);
            }
        });
    }

    /**
     * Get field icon based on type
     */
    function getFieldIcon(type) {
        const icons = {
            'text': '📝',
            'email': '📧',
            'textarea': '📄',
            'select': '📋',
            'checkbox': '☑️',
            'radio': '🔘',
            'number': '🔢',
            'date': '📅',
            'tel': '📞',
            'url': '🔗'
        };
        
        return icons[type] || '📝';
    }

    /**
     * Apply existing mappings
     */
    function applyExistingMappings() {
        Object.keys(currentMappings).forEach(function(fieldId) {
            const mailchimpFieldId = currentMappings[fieldId];
            const select = $(`.mailchimp-field-select[data-field-id="${fieldId}"]`);
            
            if (select.length && mailchimpFieldId) {
                select.val(mailchimpFieldId);
            }
        });
        
        updateMappingStatus();
    }

    /**
     * Auto-map fields based on name similarity
     */
    function autoMapFields() {
        const mappings = {};
        
        Object.keys(formFields).forEach(function(fieldId) {
            const field = formFields[fieldId];
            const fieldName = field.name.toLowerCase();
            const fieldLabel = field.label.toLowerCase();
            
            // Find matching Mailchimp field
            Object.keys(mailchimpFields).forEach(function(mcFieldId) {
                const mcField = mailchimpFields[mcFieldId];
                const mcFieldName = mcField.name.toLowerCase();
                
                if (fieldName.includes(mcFieldName) || 
                    mcFieldName.includes(fieldName) ||
                    fieldLabel.includes(mcFieldName) ||
                    mcFieldName.includes(fieldLabel)) {
                    mappings[fieldId] = mcFieldId;
                }
            });
        });
        
        currentMappings = mappings;
        updateFieldMappingTable();
        showMessage('Fields auto-mapped successfully!', 'success');
    }

    /**
     * Clear all mappings
     */
    function clearAllMappings() {
        currentMappings = {};
        updateFieldMappingTable();
        showMessage('All mappings cleared!', 'success');
    }

    /**
     * Update mapping status
     */
    function updateMappingStatus() {
        const mappedCount = Object.keys(currentMappings).length;
        const totalCount = Object.keys(formFields).length;
        
        $('#mailchimp-mapping-count').text(`${mappedCount}/${totalCount} fields mapped`);
        
        // Update progress bar
        const percentage = totalCount > 0 ? (mappedCount / totalCount) * 100 : 0;
        $('#mailchimp-mapping-progress').css('width', percentage + '%');
    }

    /**
     * Collect current mappings
     */
    function collectCurrentMappings() {
        const mappings = {};
        
        $('.mailchimp-field-select').each(function() {
            const fieldId = $(this).data('field-id');
            const value = $(this).val();
            
            if (value) {
                mappings[fieldId] = value;
            }
        });
        
        return mappings;
    }

    /**
     * Save form settings
     */
    function saveFormSettings() {
        const settings = {
            enabled: $('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked'),
            audience_id: $('#mailchimp-audience-select').val(),
            double_optin: $('#mailchimp-double-optin').is(':checked'),
            update_existing: $('#mailchimp-update-existing').is(':checked'),
            tags: $('#mailchimp-tags').val(),
            field_mapping: collectCurrentMappings()
        };
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_save_form_settings',
                nonce: window.mavlersCFMailchimp.nonce,
                form_id: currentFormId,
                integration: 'mailchimp',
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Settings saved successfully!', 'success');
                } else {
                    showMessage(response.data || 'Failed to save settings', 'error');
                }
            },
            error: function() {
                showMessage('Failed to save settings', 'error');
            }
        });
    }

    /**
     * Show message
     */
    function showMessage(message, type = 'info') {
        const messageDiv = $('#mailchimp-message');
        messageDiv.removeClass('notice-success notice-error notice-info')
                .addClass(`notice-${type}`)
                .html(`<p>${message}</p>`)
                .show();
        
        setTimeout(function() {
            messageDiv.fadeOut();
        }, 5000);
    }

})(jQuery); 