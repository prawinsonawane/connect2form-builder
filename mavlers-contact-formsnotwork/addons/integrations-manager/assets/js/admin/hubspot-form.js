/**
 * HubSpot Form Settings JavaScript - User-Friendly Version
 * 
 * Clean, modern HubSpot integration with proper settings loading and success messages
 */

(function($) {
    'use strict';

    // Debug logging
    console.log('HubSpot Form JS: Script loaded');
    console.log('HubSpot Form JS: mavlersCFHubspot object:', typeof mavlersCFHubspot !== 'undefined' ? 'available' : 'undefined');
    if (typeof mavlersCFHubspot !== 'undefined') {
        console.log('HubSpot Form JS: ajaxUrl:', mavlersCFHubspot.ajaxUrl);
        console.log('HubSpot Form JS: nonce:', mavlersCFHubspot.nonce);
    }

    // Global variables
    let currentFormId = 0;
    let formFields = [];
    let hubspotProperties = [];
    let currentMapping = {};
    let isInitialized = false;

    /**
     * Initialize HubSpot settings
     */
    function initializeHubSpotSettings() {
        console.log('HubSpot Form JS: initializeHubSpotSettings called');
        
        // Get form ID
        currentFormId = getFormIdFromUrl() || $('.hubspot-form-settings').data('form-id') || 0;
        console.log('HubSpot Form JS: currentFormId:', currentFormId);
        
        // Ensure required variables are available
        if (typeof mavlersCFHubspot === 'undefined') {
            console.error('HubSpot Form JS: mavlersCFHubspot object not available');
            return;
        }
        
        console.log('HubSpot Form JS: mavlersCFHubspot object available, proceeding with initialization');
        
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
        console.log('HubSpot Form JS: Initialization complete');
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
        $('#hubspot_object_type').on('change', function() {
            handleObjectTypeChange();
        });

        // Custom object change
        $('#hubspot_custom_object').on('change', function() {
            handleCustomObjectChange();
        });

        // Form settings save
        $('#hubspot-form-settings').on('submit', function(e) {
            e.preventDefault();
            handleFormSettingsSave();
        });

        // Auto-map fields
        $('#hubspot-auto-map-fields').on('click', function() {
            handleAutoMapFields();
        });

        // Clear mappings
        $('#hubspot-clear-mappings').on('click', function() {
            handleClearMappings();
        });

        // Field mapping changes
        $(document).on('change', '.hubspot-property-select', function() {
            handleFieldMappingChange.call(this);
        });
    }

    /**
     * Get form ID from URL
     */
    function getFormIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('post') || urlParams.get('form_id') || 0;
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
            fieldMappingSection.hide();
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
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_get_custom_objects',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateCustomObjectOptions(response.data);
                }
            },
            error: function() {
                showMessage('Failed to load custom objects', 'error');
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
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_get_object_properties',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId,
                object_type: objectType
            },
            success: function(response) {
                if (response.success && response.data) {
                    hubspotProperties = Array.isArray(response.data) ? response.data : [];
                    updateFieldMappingTable();
                } else {
                    hubspotProperties = [];
                    showMessage('Failed to load object properties', 'error');
                }
            },
            error: function() {
                hubspotProperties = [];
                showMessage('Failed to load object properties', 'error');
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
            success: function(response) {
                if (response.success && response.data) {
                    hubspotProperties = Array.isArray(response.data) ? response.data : [];
                    updateFieldMappingTable();
                } else {
                    hubspotProperties = [];
                    showMessage('Failed to load custom object properties', 'error');
                }
            },
            error: function() {
                hubspotProperties = [];
                showMessage('Failed to load custom object properties', 'error');
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
                action: 'mavlers_cf_get_form_fields',
                nonce: mavlersCFHubspot.nonce,
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
     * Load HubSpot properties
     */
    function loadHubSpotProperties() {
        // This will be loaded when object type is selected
    }

    /**
     * Load saved settings
     */
    function loadSavedSettings() {
        const savedSettings = window.hubspotFormSettings || {};
        
        if (savedSettings.enabled) {
            $('#hubspot_enabled').prop('checked', true);
            $('#hubspot_settings').show();
            
            if (savedSettings.object_type) {
                $('#hubspot_object_type').val(savedSettings.object_type).trigger('change');
            }
            
            if (savedSettings.custom_object) {
                $('#hubspot_custom_object').val(savedSettings.custom_object).trigger('change');
            }
            
            if (savedSettings.field_mapping) {
                currentMapping = savedSettings.field_mapping;
                updateFieldMappingTable();
            }
        }
    }

    /**
     * Toggle field mapping section
     */
    function toggleFieldMappingSection() {
        const objectType = $('#hubspot_object_type').val();
        const customObject = $('#hubspot_custom_object').val();
        const fieldMappingSection = $('#hubspot-field-mapping-section');
        
        if ((objectType && objectType !== 'custom') || customObject) {
            fieldMappingSection.show();
        } else {
            fieldMappingSection.hide();
        }
    }

    /**
     * Update field mapping table
     */
    function updateFieldMappingTable() {
        const tableBody = $('#hubspot-field-mapping-table tbody');
        let html = '';
        
        // Ensure hubspotProperties is always an array
        if (!Array.isArray(hubspotProperties)) {
            hubspotProperties = [];
        }
        
        if (formFields.length === 0 || hubspotProperties.length === 0) {
            html = '<tr><td colspan="3">No fields available for mapping</td></tr>';
        } else {
            formFields.forEach(function(field) {
                const currentValue = currentMapping[field.id] || '';
                html += `
                    <tr>
                        <td>${field.label}</td>
                        <td>
                            <select class="hubspot-property-select" data-field-id="${field.id}">
                                <option value="">-- Select HubSpot Property --</option>
                                ${hubspotProperties.map(prop => 
                                    `<option value="${prop.name}" ${currentValue === prop.name ? 'selected' : ''}>
                                        ${prop.label}
                                    </option>`
                                ).join('')}
                            </select>
                        </td>
                        <td>${field.type}</td>
                    </tr>
                `;
            });
        }
        
        tableBody.html(html);
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
        
        updateMappingCount();
    }

    /**
     * Update mapping count
     */
    function updateMappingCount() {
        const count = Object.keys(currentMapping).length;
        $('#hubspot-mapping-count').text(count);
    }

    /**
     * Handle auto-map fields
     */
    function handleAutoMapFields() {
        const mapping = {};
        
        formFields.forEach(function(field) {
            const fieldName = field.name.toLowerCase();
            const fieldLabel = field.label.toLowerCase();
            
            // Try to find matching HubSpot property
            const matchingProperty = hubspotProperties.find(function(prop) {
                const propName = prop.name.toLowerCase();
                const propLabel = prop.label.toLowerCase();
                
                return propName.includes(fieldName) || 
                       propLabel.includes(fieldLabel) ||
                       fieldName.includes(propName) ||
                       fieldLabel.includes(propLabel);
            });
            
            if (matchingProperty) {
                mapping[field.id] = matchingProperty.name;
            }
        });
        
        currentMapping = mapping;
        updateFieldMappingTable();
        showMessage('Fields auto-mapped successfully!', 'success');
    }

    /**
     * Handle clear mappings
     */
    function handleClearMappings() {
        currentMapping = {};
        updateFieldMappingTable();
        showMessage('Field mappings cleared!', 'success');
    }

    /**
     * Handle form settings save
     */
    function handleFormSettingsSave() {
        const settings = {
            enabled: $('#hubspot_enabled').is(':checked'),
            object_type: $('#hubspot_object_type').val(),
            custom_object: $('#hubspot_custom_object').val(),
            field_mapping: currentMapping
        };
        
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_save_form_settings',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId,
                integration: 'hubspot',
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
    function showMessage(message, type) {
        const messageDiv = $('#hubspot-message');
        messageDiv.removeClass('notice-success notice-error')
                .addClass(`notice-${type === 'success' ? 'success' : 'error'}`)
                .html(`<p>${message}</p>`)
                .show();
        
        setTimeout(function() {
            messageDiv.fadeOut();
        }, 5000);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        console.log('HubSpot Form JS: Document ready');
        console.log('HubSpot Form JS: .hubspot-form-settings elements found:', $('.hubspot-form-settings').length);
        
        if ($('.hubspot-form-settings').length > 0) {
            console.log('HubSpot Form JS: Found hubspot-form-settings element, initializing');
            initializeHubSpotSettings();
        } else {
            console.log('HubSpot Form JS: No hubspot-form-settings element found');
        }
    });

})(jQuery); 