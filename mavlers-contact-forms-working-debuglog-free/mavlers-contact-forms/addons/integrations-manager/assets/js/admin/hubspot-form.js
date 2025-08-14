/**
 * HubSpot Form Settings JavaScript - Optimized Version
 * 
 * Clean, modern HubSpot integration with proper settings loading and success messages
 */

(function($) {
    'use strict';

    // Global variables
    let currentFormId = 0;
    let formFields = [];
    let hubspotProperties = [];
    let currentMapping = {};
    let isInitialized = false;

    // Configuration
    const CONFIG = {
        AJAX_TIMEOUT: 30000,
        MESSAGE_DISPLAY_TIME: 5000,
        LOADING_DELAY: 100
    };

    /**
     * Initialize HubSpot settings
     */
    function initializeHubSpotSettings() {
        // Get form ID
        currentFormId = getFormIdFromUrl() || $('.hubspot-form-settings').data('form-id') || 0;
        
        // Ensure required variables are available
        if (typeof mavlersCFHubspot === 'undefined') {
            console.error('mavlersCFHubspot is not defined');
            return;
        }
        
        // Initialize event handlers
        initializeEventHandlers();
        
        // Load initial data
        loadFormFields();
        loadHubSpotProperties();
        
        // Show field mapping section if object type is selected
        toggleFieldMappingSection();
        
        // Load saved settings
        loadSavedSettings();
        
        isInitialized = true;
    }

    /**
     * Initialize event handlers
     */
    function initializeEventHandlers() {
        // Enable/disable toggle
        $('#hubspot_enabled').on('change', function() {
            const isEnabled = $(this).is(':checked');
            $('#hubspot_settings').toggle(isEnabled);
            if (isEnabled) {
                toggleFieldMappingSection();
            }
        });

        // Object type change
        $('#hubspot_object_type').on('change', handleObjectTypeChange);

        // Custom object change
        $('#hubspot_custom_object').on('change', handleCustomObjectChange);

        // Form settings save
        $('#hubspot-form-settings').on('submit', function(e) {
            e.preventDefault();
            handleFormSettingsSave();
        });

        // Auto-map fields
        $('#hubspot-auto-map-fields').on('click', handleAutoMapFields);

        // Clear mappings
        $('#hubspot-clear-mappings').on('click', handleClearMappings);

        // Field mapping changes
        $(document).on('change', '.hubspot-property-select', handleFieldMappingChange);
    }

    /**
     * Get form ID from URL
     */
    function getFormIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id') || 0;
    }

    /**
     * Handle object type change
     */
    function handleObjectTypeChange() {
        const objectType = $('#hubspot_object_type').val();
        const customObjectRow = $('#hubspot-custom-object-row');
        const fieldMappingSection = $('#hubspot-field-mapping-section');
        
        // Show/hide custom object selection
        if (objectType === 'custom') {
            customObjectRow.show();
            loadCustomObjects();
            // Don't hide field mapping section for custom objects - it will be shown when custom object is selected
        } else {
            customObjectRow.hide();
            if (objectType && objectType !== 'custom') {
                fieldMappingSection.show();
                loadObjectProperties(objectType);
            } else {
                fieldMappingSection.hide();
            }
        }
        
        // Update field mapping table
        updateFieldMappingTable();
    }

    /**
     * Handle custom object change
     */
    function handleCustomObjectChange() {
        const customObject = $('#hubspot_custom_object').val();
        const fieldMappingSection = $('#hubspot-field-mapping-section');
        
        if (customObject) {
            fieldMappingSection.show();
            loadCustomObjectProperties(customObject);
        } else {
            fieldMappingSection.hide();
        }
    }

    /**
     * Load custom objects
     */
    function loadCustomObjects() {
        const customObjectSelect = $('#hubspot_custom_object');
        customObjectSelect.html('<option value="">Loading custom objects...</option>');
        
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_get_custom_objects',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    updateCustomObjectOptions(response.data);
                } else {
                    customObjectSelect.html('<option value="">No custom objects found</option>');
                }
            },
            error: function() {
                customObjectSelect.html('<option value="">Error loading custom objects</option>');
            }
        });
    }

    /**
     * Update custom object options
     */
    function updateCustomObjectOptions(customObjects) {
        const customObjectSelect = $('#hubspot_custom_object');
        let options = '<option value="">Select a custom object...</option>';
        
        customObjects.forEach(function(obj) {
            // Use fullyQualifiedName if available, otherwise fall back to name
            const objectValue = obj.fullyQualifiedName || obj.name;
            options += `<option value="${objectValue}">${obj.label}</option>`;
        });
        
        customObjectSelect.html(options);
    }

    /**
     * Load object properties
     */
    function loadObjectProperties(objectType) {
        const actionMap = {
            'contacts': 'hubspot_get_contact_properties',
            'deals': 'hubspot_get_deal_properties',
            'companies': 'hubspot_get_company_properties'
        };
        
        const action = actionMap[objectType];
        if (!action) return;
        
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    hubspotProperties = response.data;
                    updateFieldMappingTable();
                } else {
                    console.error('HubSpot properties error:', response.data);
                }
            },
            error: function() {
                console.error('Failed to load properties for:', objectType);
            }
        });
    }

    /**
     * Load custom object properties
     */
    function loadCustomObjectProperties(objectName) {
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_get_custom_object_properties',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId,
                custom_object_name: objectName
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    hubspotProperties = response.data;
                    updateFieldMappingTable();
                } else {
                    console.error('Custom object properties error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load custom object properties:', error);
            }
        });
    }

    /**
     * Load form fields
     */
    function loadFormFields() {
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_get_form_fields',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    // Handle both array and object formats
                    const fields = response.data.fields || response.data;
                    formFields = Array.isArray(fields) ? fields : Object.values(fields);
                    updateFieldMappingTable();
                }
            },
            error: function() {
                console.error('Failed to load form fields');
            }
        });
    }

    /**
     * Load HubSpot properties
     */
    function loadHubSpotProperties() {
        // Properties will be loaded when object type is selected
    }

    /**
     * Load saved settings
     */
    function loadSavedSettings() {
        if (window.hubspotFormSettings) {
            // Set form values
            $('#hubspot_enabled').prop('checked', window.hubspotFormSettings.enabled || false);
            $('#hubspot_object_type').val(window.hubspotFormSettings.object_type || '');
            $('#hubspot_custom_object').val(window.hubspotFormSettings.custom_object_name || '');
            $('#hubspot_action_type').val(window.hubspotFormSettings.action_type || 'create_or_update');
            $('#hubspot_workflow_enabled').prop('checked', window.hubspotFormSettings.workflow_enabled || false);
            
            // Load field mapping
            if (window.hubspotFormSettings.field_mapping) {
                // Ensure currentMapping stays as an object
                if (Array.isArray(window.hubspotFormSettings.field_mapping)) {
                    // Convert array to object if needed
                    currentMapping = {};
                    window.hubspotFormSettings.field_mapping.forEach((mapping, index) => {
                        if (mapping && mapping.field_id) {
                            currentMapping[mapping.field_id] = mapping.property_name;
                        }
                    });
                } else {
                    currentMapping = { ...window.hubspotFormSettings.field_mapping };
                }
            } else {
                currentMapping = {};
            }
            
            // Show/hide sections based on settings
            $('#hubspot_settings').toggle(window.hubspotFormSettings.enabled || false);
            toggleFieldMappingSection();
        }
    }

    /**
     * Toggle field mapping section visibility
     */
    function toggleFieldMappingSection() {
        const objectType = $('#hubspot_object_type').val();
        const fieldMappingSection = $('#hubspot-field-mapping-section');
        const customObjectRow = $('#hubspot-custom-object-row');
        
        if (objectType === 'custom') {
            customObjectRow.show();
            fieldMappingSection.hide();
        } else if (objectType && ['contacts', 'deals', 'companies'].includes(objectType)) {
            customObjectRow.hide();
            fieldMappingSection.show();
            loadObjectProperties(objectType);
        } else {
            customObjectRow.hide();
            fieldMappingSection.hide();
        }
    }

    /**
     * Update field mapping table
     */
    function updateFieldMappingTable() {
        const tbody = $('#hubspot-field-mapping-tbody');
        
        if (!formFields || !formFields.length) {
            tbody.html('<tr class="no-fields-row"><td colspan="4" class="text-center">No form fields found</td></tr>');
            return;
        }
        
        if (!hubspotProperties || !hubspotProperties.length) {
            tbody.html('<tr class="no-fields-row"><td colspan="4" class="text-center">Select an object type to see properties</td></tr>');
            return;
        }
        
        let tableHtml = '';
        formFields.forEach(function(field) {
            const mappedProperty = currentMapping[field.id] || '';
            const status = mappedProperty ? 'Mapped' : 'Unmapped';
            const statusClass = mappedProperty ? 'mapped' : 'unmapped';
            
            const dropdownHtml = `
                <select class="hubspot-property-select" data-field-id="${field.id}">
                    <option value="">Select property...</option>
                    ${hubspotProperties.map(prop => {
                        const readonlyIndicator = prop.readonly ? ' (Read-only)' : '';
                        return `<option value="${prop.name}" ${mappedProperty === prop.name ? 'selected' : ''}>${prop.label}${readonlyIndicator}</option>`;
                    }).join('')}
                </select>
            `;
            
            tableHtml += `
                <tr class="field-mapping-row">
                    <td>${field.label}</td>
                    <td class="mapping-arrow">→</td>
                    <td>
                        ${dropdownHtml}
                    </td>
                    <td class="mapping-status ${statusClass}">${status}</td>
                </tr>
            `;
        });
        
        tbody.html(tableHtml);
        updateMappingCount();
    }

    /**
     * Handle field mapping change
     */
    function handleFieldMappingChange() {
        const fieldId = $(this).data('field-id');
        const propertyName = $(this).val();
        
        if (propertyName) {
            currentMapping[fieldId] = propertyName;
        } else {
            delete currentMapping[fieldId];
        }
        
        // Update status
        const row = $(this).closest('tr');
        const statusCell = row.find('.mapping-status');
        if (propertyName) {
            statusCell.text('Mapped').removeClass('unmapped').addClass('mapped');
        } else {
            statusCell.text('Unmapped').removeClass('mapped').addClass('unmapped');
        }
        
        updateMappingCount();
    }

    /**
     * Update mapping count
     */
    function updateMappingCount() {
        const mappedCount = Object.keys(currentMapping).length;
        $('#hubspot-mapping-count').text(`${mappedCount} fields mapped`);
    }

    /**
     * Handle auto-map fields
     */
    function handleAutoMapFields() {
        const objectType = $('#hubspot_object_type').val();
        
        // Common field mappings
        const commonMappings = {
            'contacts': {
                'email': 'email',
                'firstname': 'firstname',
                'lastname': 'lastname',
                'phone': 'phone',
                'company': 'company'
            },
            'deals': {
                'dealname': 'dealname',
                'amount': 'amount',
                'closedate': 'closedate'
            },
            'companies': {
                'name': 'name',
                'domain': 'domain',
                'phone': 'phone'
            }
        };
        
        const mappings = commonMappings[objectType] || {};
        
        // Auto-map based on field names and labels
        formFields.forEach(function(field) {
            const fieldName = field.name.toLowerCase();
            const fieldLabel = field.label.toLowerCase();
            
            for (const [hubspotProp, commonName] of Object.entries(mappings)) {
                if (fieldName.includes(commonName) || fieldLabel.includes(commonName)) {
                    currentMapping[field.id] = hubspotProp;
                    break;
                }
            }
        });
        
        updateFieldMappingTable();
        showMessage('Auto-mapping completed', 'success');
    }

    /**
     * Handle clear mappings
     */
    function handleClearMappings() {
        if (confirm(mavlersCFHubspot.strings.clearMappingsConfirm)) {
            currentMapping = {};
            updateFieldMappingTable();
            showMessage('All mappings cleared', 'success');
        }
    }

    /**
     * Handle form settings save
     */
    function handleFormSettingsSave() {
        const saveButton = $('#hubspot-save-settings');
        const buttonText = saveButton.find('.button-text');
        const buttonLoading = saveButton.find('.button-loading');
        const saveStatus = $('#hubspot-save-status');
        
        // Show loading state
        buttonText.hide();
        buttonLoading.show();
        saveStatus.removeClass('success error').text('');
        
        // Collect form data
        const formData = {
            action: 'hubspot_save_form_settings',
            nonce: mavlersCFHubspot.nonce,
            form_id: currentFormId,
            enabled: $('#hubspot_enabled').is(':checked') ? '1' : '',
            object_type: $('#hubspot_object_type').val() || '',
            custom_object_name: $('#hubspot_custom_object').val() || '',
            action_type: $('#hubspot_action_type').val() || 'create_or_update',
            workflow_enabled: $('#hubspot_workflow_enabled').is(':checked') ? '1' : '',
            field_mapping: currentMapping
        };
        
        // Send AJAX request
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: formData,
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success) {
                    saveStatus.text('Settings saved successfully!').addClass('success');
                    showMessage('HubSpot settings saved successfully!', 'success');
                    
                    // Update global settings
                    if (window.hubspotFormSettings) {
                        window.hubspotFormSettings = {
                            ...window.hubspotFormSettings,
                            enabled: formData.enabled === '1',
                            object_type: formData.object_type,
                            custom_object_name: formData.custom_object_name,
                            action_type: formData.action_type,
                            workflow_enabled: formData.workflow_enabled === '1',
                            field_mapping: currentMapping
                        };
                    }
                } else {
                    saveStatus.text('Failed to save settings: ' + (response.data || 'Unknown error')).addClass('error');
                    showMessage('Failed to save settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                saveStatus.text('Network error: ' + error).addClass('error');
                showMessage('Network error: ' + error, 'error');
            },
            complete: function() {
                // Hide loading state
                buttonLoading.hide();
                buttonText.show();
                
                // Clear status after delay
                setTimeout(() => {
                    saveStatus.removeClass('success error').text('');
                }, CONFIG.MESSAGE_DISPLAY_TIME);
            }
        });
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        // Create or update message element
        let messageElement = $('#hubspot-message');
        if (messageElement.length === 0) {
            messageElement = $('<div id="hubspot-message" style="position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 15px; border-radius: 4px; color: white; font-weight: 500;"></div>');
            $('body').append(messageElement);
        }
        
        // Set message content and style
        messageElement.text(message);
        messageElement.removeClass('success error');
        messageElement.addClass(type);
        
        // Set background color based on type
        if (type === 'success') {
            messageElement.css('background-color', '#28a745');
        } else if (type === 'error') {
            messageElement.css('background-color', '#dc3545');
        }
        
        // Show message
        messageElement.fadeIn();
        
        // Hide after delay
        setTimeout(() => {
            messageElement.fadeOut();
        }, CONFIG.MESSAGE_DISPLAY_TIME);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.hubspot-form-settings').length > 0) {
            initializeHubSpotSettings();
        }
    });

    // Expose functions for debugging (only in development)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        window.hubspotDebug = {
            loadFormFields,
            loadObjectProperties,
            loadCustomObjectProperties,
            updateFieldMappingTable,
            currentMapping,
            formFields,
            hubspotProperties
        };
    }

})(jQuery); 