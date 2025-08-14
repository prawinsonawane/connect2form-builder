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
    let isGlobalSettings = false;

    // Configuration
    const CONFIG = {
        AJAX_TIMEOUT: 30000,
        MESSAGE_DISPLAY_TIME: 5000,
        LOADING_DELAY: 100
    };

        /**
     * Check if we're on the global settings page
     */
    function isGlobalSettingsPage() {
        return window.location.href.includes('page=mavlers-contact-forms-integrations') && 
               window.location.href.includes('tab=hubspot');
    }

    /**
     * Check if we're on a form-specific page
     */
    function isOnFormSpecificPage() {
        return window.location.href.includes('post.php') || 
               window.location.href.includes('post-new.php') ||
               $('.hubspot-form-settings').length > 0;
    }

    /**
     * Initialize HubSpot settings
     */
    function initializeHubSpotSettings() {
        try {
            // Determine page type
            isGlobalSettings = isGlobalSettingsPage();
            
            if (!isGlobalSettingsPage() && !isOnFormSpecificPage()) {
                return; // Not on a relevant page
            }
            
            // Get form ID for form-specific pages
            if (!isGlobalSettings) {
                currentFormId = getFormIdFromUrl() || $('.hubspot-form-settings').data('form-id') || 0;
                
                if (!currentFormId) {
                    showMessage('Form ID not found. Please refresh the page.', 'error');
                    return;
                }
            }
            
            // Ensure required variables are available
            if (typeof mavlersCFHubspot === 'undefined') {
                showMessage('HubSpot integration not properly loaded. Please refresh the page.', 'error');
                return;
            }
            
            // Support both nonce formats
            if (!mavlersCFHubspot.nonce) {
                // Try to get nonce from form or data attribute
                mavlersCFHubspot.nonce = $('.hubspot-form-settings').data('nonce') || 
                                        $('#mavlers_cf_nonce').val() || 
                                        $('input[name="mavlers_cf_nonce"]').val() ||
                                        mavlersCFHubspot.formNonce;
                }
                
                            // Initialize based on page type
            if (isGlobalSettings) {
                initializeGlobalSettings();
            } else {
                initializeFormSpecificSettings();
            }
            
            isInitialized = true;
        } catch (error) {
            console.error('Error initializing HubSpot settings:', error);
            showMessage('Failed to initialize HubSpot settings. Please refresh the page.', 'error');
        }
    }

    /**
     * Initialize global settings page
     */
    function initializeGlobalSettings() {
        // Initialize event handlers for global settings
        initializeGlobalEventHandlers();
        
        // Load initial data for global settings
        loadSavedGlobalSettings();
    }

    /**
     * Load global settings
     */
    function loadGlobalSettings() {
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_get_global_settings',
                nonce: mavlersCFHubspot.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    mavlersCFHubspot.globalSettings = response.data;
                }
            },
            error: function() {
                // Silently fail - this is expected on global settings page
            }
        });
    }

    /**
     * Initialize form-specific settings
     */
    function initializeFormSpecificSettings() {
        // Load global settings first, then proceed with data loading
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_get_global_settings',
                nonce: mavlersCFHubspot.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    mavlersCFHubspot.globalSettings = response.data;
                }
                
                // Initialize event handlers
                initializeEventHandlers();
                
                // Load initial data
                loadFormFields();
                
                // Test AJAX handler
                testAjaxHandler();
                
                // Load saved settings first
                loadSavedSettings();
                
                // Show field mapping section if object type is selected
                toggleFieldMappingSection();
                
                // Force update field mapping table after a short delay to ensure both form fields and properties are loaded
                setTimeout(function() {
                    if (formFields && formFields.length > 0) {
                        // If we have an object type selected, load the properties
                        const objectType = $('#hubspot_object_type').val();
                        if (objectType && ['contacts', 'deals', 'companies'].includes(objectType)) {
                            loadObjectProperties(objectType);
                        }
                        
                        // Update field mapping table after properties are loaded
                        setTimeout(function() {
                            if (hubspotProperties && hubspotProperties.length > 0) {
                                updateFieldMappingTable();
                            }
                        }, 2000); // Wait 2 seconds for properties to load
                    }
                }, 1000);
            },
            error: function() {
                // If global settings fail, still try to load data
                initializeEventHandlers();
                loadFormFields();
                testAjaxHandler();
                loadSavedSettings();
                toggleFieldMappingSection();
            }
        });
    }

    /**
     * Initialize event handlers for global settings
     */
    function initializeGlobalEventHandlers() {
        // Test connection
        $('#hubspot-test-connection').on('click', handleTestConnection);
        
        // Save global settings
        $('#hubspot-save-global-settings').on('click', handleSaveGlobalSettings);
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
     * Handle save global settings
     */
    function handleSaveGlobalSettings() {
        const $button = $('#hubspot-save-global-settings');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Saving...');
        
        const accessToken = $('#access_token').val();
        const portalId = $('#portal_id').val();
        
        if (!accessToken || !portalId) {
            showMessage('Please enter both Access Token and Portal ID', 'error');
            $button.prop('disabled', false).text(originalText);
            return;
        }
        
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_save_global_settings',
                nonce: mavlersCFHubspot.nonce,
                access_token: accessToken,
                portal_id: portalId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Global settings saved successfully!', 'success');
                } else {
                    showMessage('Failed to save global settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Failed to save global settings: ' + error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Load saved global settings
     */
    function loadSavedGlobalSettings() {
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_get_global_settings',
                nonce: mavlersCFHubspot.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Populate form fields with saved settings
                    if (response.data.access_token) {
                        $('#access_token').val(response.data.access_token);
                    }
                    if (response.data.portal_id) {
                        $('#portal_id').val(response.data.portal_id);
                    }
                }
            },
            error: function() {
                // Silently fail - settings might not exist yet
            }
        });
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
     * Get access token from global settings
     */
    function getGlobalAccessToken() {
        // First try to get from form field (for global settings page)
        let accessToken = $('#access_token').val();
        
        // If no access token in form field, try to get from global settings
        if (!accessToken) {
            // For form-specific pages, we need to get from global settings
            accessToken = mavlersCFHubspot.globalSettings?.access_token || '';
        }
        
        return accessToken;
    }

    /**
     * Load custom objects
     */
    function loadCustomObjects() {
        const customObjectSelect = $('#hubspot_custom_object');
        customObjectSelect.html('<option value="">Loading custom objects...</option>');
        
        // Get access token from global settings
        const accessToken = getGlobalAccessToken();
        
        if (!accessToken) {
            showMessage('Access token not found. Please configure HubSpot global settings first.', 'error');
            customObjectSelect.html('<option value="">Access token required</option>');
            return;
        }
        
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_form_get_custom_objects',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId,
                access_token: accessToken
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    updateCustomObjectOptions(response.data.objects || response.data);
                    showMessage(response.data.message || 'Custom objects loaded successfully', 'success');
                } else {
                    const errorMessage = response.data?.message || 'No custom objects found';
                    customObjectSelect.html('<option value="">No custom objects found</option>');
                    showMessage(errorMessage, 'warning');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                customObjectSelect.html('<option value="">Error loading custom objects</option>');
                showMessage('Failed to load custom objects: ' + errorMessage, 'error');
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
            'contacts': 'hubspot_form_get_contact_properties',
            'deals': 'hubspot_form_get_deal_properties',
            'companies': 'hubspot_form_get_company_properties'
        };
        
        const action = actionMap[objectType];
        if (!action) {
            showMessage('Invalid object type: ' + objectType, 'error');
            return;
        }
        
        showMessage('Loading ' + objectType + ' properties...', 'info');
        
        // Get access token from global settings
        const accessToken = getGlobalAccessToken();
        
        if (!accessToken) {
            showMessage('Access token not found. Please configure HubSpot global settings first.', 'error');
            return;
        }
        
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId,
                access_token: accessToken
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    hubspotProperties = response.data.properties || response.data;
                    
                    // Only update field mapping table if form fields are also loaded
                    if (formFields && formFields.length > 0) {
                        updateFieldMappingTable();
                    }
                    
                    showMessage(response.data.message || objectType + ' properties loaded successfully', 'success');
                } else {
                    const errorMessage = response.data?.message || 'Failed to load properties';
                    showMessage('HubSpot properties error: ' + errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                showMessage('Failed to load ' + objectType + ' properties: ' + errorMessage, 'error');
            }
        });
    }

    /**
     * Load custom object properties
     */
    function loadCustomObjectProperties(objectName) {
        // Get access token from global settings
        const accessToken = getGlobalAccessToken();
        
        if (!accessToken) {
            showMessage('Access token not found. Please configure HubSpot global settings first.', 'error');
            return;
        }
        
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_form_get_custom_object_properties',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId,
                object_name: objectName,
                access_token: accessToken
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    hubspotProperties = response.data.properties || response.data;
                    
                    // Only update field mapping table if form fields are also loaded
                    if (formFields && formFields.length > 0) {
                        updateFieldMappingTable();
                    }
                    
                    showMessage(response.data.message || 'Custom object properties loaded successfully', 'success');
                } else {
                    const errorMessage = response.data?.message || 'Failed to load custom object properties';
                    showMessage('Custom object properties error: ' + errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                showMessage('Failed to load custom object properties: ' + errorMessage, 'error');
            }
        });
    }

    /**
     * Test AJAX handler
     */
    function testAjaxHandler() {

        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_form_test',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {

            },
            error: function(xhr, status, error) {

            }
        });
        
        // Test the form fields handler specifically

        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_form_get_fields_test',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {

            },
            error: function(xhr, status, error) {

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
                action: 'hubspot_form_get_fields',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data && response.data.fields) {
                    formFields = response.data.fields;
                    
                    // Apply saved field mappings to handle field ID changes
                    applySavedFieldMappings();
                    
                    // Update field mapping table if HubSpot properties are also loaded
                    if (hubspotProperties && hubspotProperties.length > 0) {
                        updateFieldMappingTable();
                    }
                    
                    showMessage('Form fields loaded successfully', 'success');
                } else {
                    const errorMessage = response.data?.message || 'Failed to load form fields';
                    showMessage('Form fields error: ' + errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                showMessage('Failed to load form fields: ' + errorMessage, 'error');
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
        console.log('DEBUG: loadSavedSettings - window.hubspotFormSettings:', window.hubspotFormSettings);
        
        if (window.hubspotFormSettings) {
            // Set form values
            $('#hubspot_enabled').prop('checked', window.hubspotFormSettings.enabled || false);
            $('#hubspot_object_type').val(window.hubspotFormSettings.object_type || '');
            $('#hubspot_custom_object').val(window.hubspotFormSettings.custom_object_name || '');
            $('#hubspot_action_type').val(window.hubspotFormSettings.action_type || 'create_or_update');
            $('#hubspot_workflow_enabled').prop('checked', window.hubspotFormSettings.workflow_enabled || false);
            
            // Load field mapping
            if (window.hubspotFormSettings.field_mapping) {
                console.log('DEBUG: loadSavedSettings - Field mapping found:', window.hubspotFormSettings.field_mapping);
                console.log('DEBUG: loadSavedSettings - Field mapping type:', typeof window.hubspotFormSettings.field_mapping);
                console.log('DEBUG: loadSavedSettings - Field mapping keys:', Object.keys(window.hubspotFormSettings.field_mapping));
                console.log('DEBUG: loadSavedSettings - Field mapping values:', Object.values(window.hubspotFormSettings.field_mapping));
                
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
                
                console.log('DEBUG: loadSavedSettings - Current mapping set to:', currentMapping);
                console.log('DEBUG: loadSavedSettings - Current mapping entries:');
                Object.entries(currentMapping).forEach(([fieldId, propertyName]) => {
                    console.log('  - Field ID:', fieldId, 'Property:', propertyName);
                });
            } else {
                currentMapping = {};
                console.log('DEBUG: loadSavedSettings - No field mapping found, setting empty object');
            }
            
            // Show/hide sections based on settings
            $('#hubspot_settings').toggle(window.hubspotFormSettings.enabled || false);
            toggleFieldMappingSection();
            
            // If we have an object type selected, load the properties
            const objectType = window.hubspotFormSettings.object_type;
            if (objectType && ['contacts', 'deals', 'companies'].includes(objectType)) {
                console.log('DEBUG: loadSavedSettings - Loading properties for object type:', objectType);
                setTimeout(function() {
                    loadObjectProperties(objectType);
                }, 500);
            }
        } else {
            console.log('DEBUG: loadSavedSettings - No hubspotFormSettings found');
        }
    }

    /**
     * Apply saved field mappings to current form fields
     * This preserves the exact property mappings regardless of field ID changes
     */
    function applySavedFieldMappings() {
        if (!formFields || !formFields.length || !currentMapping || Object.keys(currentMapping).length === 0) {
            console.log('DEBUG: applySavedFieldMappings - No data to process');
            return;
        }

        console.log('DEBUG: applySavedFieldMappings - Form fields:', formFields);
        console.log('DEBUG: applySavedFieldMappings - Current mapping:', currentMapping);

        // Create a new mapping that preserves the exact property assignments
        const newMapping = {};
        let mappingsApplied = 0;

        // Get the saved property assignments (what HubSpot properties were mapped)
        const savedProperties = Object.values(currentMapping);
        console.log('DEBUG: Saved properties to apply:', savedProperties);

        // Apply the saved properties to current fields in order
        formFields.forEach((field, index) => {
            if (index < savedProperties.length) {
                const propertyName = savedProperties[index];
                newMapping[field.id] = propertyName;
                mappingsApplied++;
                console.log('DEBUG: Applied property', propertyName, 'to field:', field.label, 'ID:', field.id);
            }
        });

        console.log('DEBUG: New mapping created:', newMapping);
        console.log('DEBUG: Mappings applied:', mappingsApplied);

        // Update currentMapping with the new mappings
        currentMapping = newMapping;
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
        
        console.log('DEBUG: updateFieldMappingTable - Form fields:', formFields);
        console.log('DEBUG: updateFieldMappingTable - HubSpot properties:', hubspotProperties);
        console.log('DEBUG: updateFieldMappingTable - Current mapping:', currentMapping);
        
        // Debug: Show first few HubSpot properties
        if (hubspotProperties && hubspotProperties.length > 0) {
            console.log('DEBUG: First 5 HubSpot properties:');
            hubspotProperties.slice(0, 5).forEach(prop => {
                console.log('  - Name:', prop.name, 'Label:', prop.label);
            });
        }
        
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
            
            console.log('DEBUG: updateFieldMappingTable - Field:', field.label, 'ID:', field.id, 'Mapped property:', mappedProperty);
            
            // Debug: Show available properties for this field
            if (field.label.toLowerCase().includes('subject')) {
                console.log('DEBUG: Available HubSpot properties for Subject field:');
                hubspotProperties.forEach(prop => {
                    if (prop.name.toLowerCase().includes('message') || prop.name.toLowerCase().includes('subject') || prop.name.toLowerCase().includes('content')) {
                        console.log('  - Property:', prop.name, 'Label:', prop.label);
                    }
                });
            }
            
            const dropdownHtml = `
                <select class="hubspot-property-select" data-field-id="${field.id}">
                    <option value="">Select property...</option>
                    ${hubspotProperties.map(prop => {
                        const readonlyIndicator = prop.readonly ? ' (Read-only)' : '';
                        const isSelected = mappedProperty === prop.name;
                        console.log('DEBUG: Dropdown option - Field:', field.label, 'Property:', prop.name, 'Mapped property:', mappedProperty, 'Is selected:', isSelected);
                        return `<option value="${prop.name}" ${isSelected ? 'selected' : ''}>${prop.label}${readonlyIndicator}</option>`;
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
        
        // Force update dropdown selections after a short delay
        setTimeout(function() {
            console.log('DEBUG: Force updating dropdown selections');
            formFields.forEach(function(field) {
                const mappedProperty = currentMapping[field.id] || '';
                const dropdown = $(`select[data-field-id="${field.id}"]`);
                if (dropdown.length && mappedProperty) {
                    console.log('DEBUG: Setting dropdown value for field:', field.label, 'Property:', mappedProperty);
                    dropdown.val(mappedProperty);
                }
            });
        }, 100);
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
        
        // Auto-save the field mapping change
        autoSaveFieldMappings();
    }
    
    // Debounce timer for auto-save
    let autoSaveTimer = null;
    
    /**
     * Auto-save field mappings
     */
    function autoSaveFieldMappings() {
        // Clear existing timer
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
        }
        
        // Set new timer for 1 second delay
        autoSaveTimer = setTimeout(function() {
            console.log('DEBUG: autoSaveFieldMappings - Current mapping to save:', currentMapping);
            console.log('DEBUG: autoSaveFieldMappings - Current mapping keys:', Object.keys(currentMapping));
            console.log('DEBUG: autoSaveFieldMappings - Current mapping values:', Object.values(currentMapping));
            
            // Collect form data for auto-save
            const formData = {
                action: 'hubspot_form_save_settings',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId,
                enabled: $('#hubspot_enabled').is(':checked') ? '1' : '',
                object_type: $('#hubspot_object_type').val() || '',
                custom_object_name: $('#hubspot_custom_object').val() || '',
                action_type: $('#hubspot_action_type').val() || 'create_or_update',
                workflow_enabled: $('#hubspot_workflow_enabled').is(':checked') ? '1' : '',
                field_mapping: currentMapping
            };
            
            console.log('DEBUG: autoSaveFieldMappings - Form data to send:', formData);
            
            // Send AJAX request silently
            $.ajax({
                url: mavlersCFHubspot.ajaxUrl,
                type: 'POST',
                data: formData,
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    console.log('DEBUG: autoSaveFieldMappings - Save response:', response);
                    if (response.success) {
                        // Update global settings silently
                        if (window.hubspotFormSettings) {
                            window.hubspotFormSettings = {
                                ...window.hubspotFormSettings,
                                field_mapping: currentMapping
                            };
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.log('DEBUG: autoSaveFieldMappings - Save error:', error);
                    // Silently fail for auto-save - user can manually save if needed
                }
            });
        }, 1000); // 1 second delay
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
        
        if (!objectType) {
            showMessage('Please select an object type first', 'error');
            return;
        }
        
        // Use AJAX to get automatic mapping from server
        $.ajax({
            url: mavlersCFHubspot.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hubspot_form_auto_map_fields',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success && response.data) {
                    currentMapping = response.data.mapping || {};
                    updateFieldMappingTable();
                    showMessage(response.data.message || 'Auto-mapping completed', 'success');
                } else {
                    const errorMessage = response.data?.message || 'Failed to auto-map fields';
                    showMessage('Auto-mapping error: ' + errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                showMessage('Failed to auto-map fields: ' + errorMessage, 'error');
            }
        });
    }

    /**
     * Handle clear mappings
     */
    function handleClearMappings() {
        if (confirm(mavlersCFHubspot.strings.clearMappingsConfirm)) {
            currentMapping = {};
            updateFieldMappingTable();
            
            // Save the empty mappings to the database
            const formData = {
                action: 'hubspot_form_save_settings',
                nonce: mavlersCFHubspot.nonce,
                form_id: currentFormId,
                enabled: $('#hubspot_enabled').is(':checked') ? '1' : '',
                object_type: $('#hubspot_object_type').val() || '',
                custom_object_name: $('#hubspot_custom_object').val() || '',
                action_type: $('#hubspot_action_type').val() || 'create_or_update',
                workflow_enabled: $('#hubspot_workflow_enabled').is(':checked') ? '1' : '',
                field_mapping: currentMapping
            };
            
            // Send AJAX request to save empty mappings
            $.ajax({
                url: mavlersCFHubspot.ajaxUrl,
                type: 'POST',
                data: formData,
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success) {
                        // Update global settings
                        if (window.hubspotFormSettings) {
                            window.hubspotFormSettings = {
                                ...window.hubspotFormSettings,
                                field_mapping: currentMapping
                            };
                        }
                        showMessage('All mappings cleared and saved', 'success');
                    } else {
                        showMessage('Failed to save cleared mappings', 'error');
                    }
                },
                error: function() {
                    showMessage('Failed to save cleared mappings', 'error');
                }
            });
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
            action: 'hubspot_form_save_settings',
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
                    const message = response.data?.message || 'Settings saved successfully!';
                    saveStatus.text(message).addClass('success');
                    showMessage(message, 'success');
                    
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
                    const errorMessage = response.data?.message || 'Unknown error';
                    saveStatus.text('Failed to save settings: ' + errorMessage).addClass('error');
                    showMessage('Failed to save settings: ' + errorMessage, 'error');
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