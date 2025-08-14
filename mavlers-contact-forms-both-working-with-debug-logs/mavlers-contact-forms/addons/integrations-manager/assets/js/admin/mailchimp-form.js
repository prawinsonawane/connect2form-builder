/**
 * Mailchimp Form Settings JavaScript - Simple & User-Friendly
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
    let lastLoadMailchimpFieldsCall = 0; // Track last call time
    let loadMailchimpFieldsTimeout = null; // Debounce timeout

    // Initialize when document is ready
    $(document).ready(function() {
        console.log('=== Document Ready ===');
        console.log('jQuery available:', typeof $ !== 'undefined');
        console.log('mavlersCFMailchimp object:', window.mavlersCFMailchimp);
        console.log('Mailchimp form settings object:', window.mailchimpFormSettings);
        console.log('Form ID from settings:', window.mailchimpFormId);
        
        // Debug saved settings
        if (window.mailchimpFormSettings) {
            console.log('Saved settings details:');
            console.log('- Enabled:', window.mailchimpFormSettings.enabled);
            console.log('- Audience ID:', window.mailchimpFormSettings.audience_id);
            console.log('- Double Opt-in:', window.mailchimpFormSettings.double_optin);
            console.log('- Update Existing:', window.mailchimpFormSettings.update_existing);
            console.log('- Tags:', window.mailchimpFormSettings.tags);
            console.log('- Field Mappings:', window.mailchimpFormSettings.field_mapping);
        }
        
        initializeMailchimpFormSettings();
    });

    /**
     * Initialize the Mailchimp form settings
     */
    function initializeMailchimpFormSettings() {
        console.log('=== Initializing Mailchimp Form Settings ===');
        
        // Get form ID from global or data attribute
        currentFormId = window.mailchimpFormId || $('.mailchimp-form-settings').data('form-id') || 0;
        
        console.log('Form ID:', currentFormId);
        console.log('Mailchimp settings object:', window.mailchimpFormSettings);
        console.log('mavlersCFMailchimp object:', window.mavlersCFMailchimp);
        console.log('Global settings object:', window.mailchimpGlobalSettings);
        
        // Verify we're working with the correct form
        if (window.mailchimpFormSettings && window.mailchimpFormSettings.form_id && window.mailchimpFormSettings.form_id !== currentFormId) {
            console.warn('⚠️ Form ID mismatch! Settings are for form ID:', window.mailchimpFormSettings.form_id, 'but current form is:', currentFormId);
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
            console.log('Integration is enabled or has saved settings, loading audiences...');
            console.log('Integration checkbox checked:', $('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked'));
            console.log('Saved settings audience_id:', window.mailchimpFormSettings ? window.mailchimpFormSettings.audience_id : 'no settings');
            // Add a small delay to ensure the DOM is ready
            setTimeout(function() {
                loadAudiences();
            }, 100);
        } else {
            console.log('Integration is disabled and no saved settings');
        }
        
        console.log('=== Mailchimp Form Settings Initialization Complete ===');
    }

    /**
     * Test AJAX connection
     */
    function testAjaxConnection() {
        console.log('=== Testing AJAX Connection ===');
        
        if (!window.mavlersCFMailchimp || !window.mavlersCFMailchimp.ajaxUrl) {
            console.error('mavlersCFMailchimp object not available');
            return;
        }
        
        console.log('Making test AJAX request...');
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_test_ajax',
                nonce: window.mavlersCFMailchimp.nonce
            },
            success: function(response) {
                console.log('Test AJAX response:', response);
                if (response.success) {
                    console.log('✅ AJAX connection is working');
                } else {
                    console.error('❌ AJAX test failed:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX test error:', error);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
            }
        });
    }

    /**
     * Load initial settings from server
     */
    function loadInitialSettings() {
        if (window.mailchimpFormSettings) {
            const settings = window.mailchimpFormSettings;
            
            console.log('=== LOADING INITIAL SETTINGS ===');
            console.log('Settings object:', settings);
            console.log('Settings enabled:', settings.enabled);
            console.log('Settings audience_id:', settings.audience_id);
            console.log('Settings field_mapping:', settings.field_mapping);
            
            // Set form values with proper boolean handling
            $('#mailchimp_double_optin').prop('checked', Boolean(settings.double_optin));
            $('#mailchimp_update_existing').prop('checked', Boolean(settings.update_existing));
            $('#mailchimp_tags').val(settings.tags || '');
            
            console.log('Set double_optin:', Boolean(settings.double_optin));
            console.log('Set update_existing:', Boolean(settings.update_existing));
            console.log('Set tags:', settings.tags || '');
            
            // Set audience
            if (settings.audience_id) {
                currentAudienceId = settings.audience_id;
                console.log('Set currentAudienceId:', currentAudienceId);
                
                // Try to set the audience dropdown immediately if it exists
                const $audienceSelect = $('#mailchimp_audience');
                if ($audienceSelect.length) {
                    console.log('Setting audience dropdown value immediately');
                    $audienceSelect.val(settings.audience_id);
                    console.log('Audience dropdown value after setting:', $audienceSelect.val());
                }
            }
            
            // Set mappings
            currentMappings = settings.field_mapping || {};
            console.log('Set currentMappings:', currentMappings);
            
            // Set integration enabled state
            if (settings.enabled) {
                console.log('Setting integration checkbox to checked');
                $('.integration-enable-checkbox[data-integration="mailchimp"]').prop('checked', true);
                // Show the settings panel
                $('#mailchimp-form-settings').slideDown();
                // Also show the field mapping section
                $('#mailchimp-field-mapping-section').slideDown();
                console.log('Integration enabled from saved settings');
                
                // If we have a saved audience, load audiences to populate the dropdown
                if (settings.audience_id) {
                    console.log('Loading audiences to restore saved selection');
                    loadAudiences();
                }
                
                // Apply mappings after a delay to ensure everything is loaded
                setTimeout(function() {
                    console.log('=== APPLYING MAPPINGS AFTER INITIAL LOAD ===');
                    console.log('Current mappings to apply:', currentMappings);
                    applyExistingMappings();
                }, 2000);
            }
            
            console.log('=== INITIAL SETTINGS LOADED ===');
        } else {
            console.log('No initial settings found');
        }
    }
    
    /**
     * Load saved settings into the form (called when integration is enabled)
     */
    function loadSavedSettings() {
        console.log('=== Loading Saved Settings ===');
        
        if (!window.mailchimpFormSettings) {
            console.log('No saved settings available');
            return;
        }
        
        var settings = window.mailchimpFormSettings;
        console.log('Loading settings:', settings);
        
        // Load audience selection
        if (settings.audience_id) {
            $('#mailchimp_audience').val(settings.audience_id);
            currentAudienceId = settings.audience_id;
            console.log('Set audience ID:', settings.audience_id);
        }
        
        // Load checkboxes
        if (settings.double_optin !== undefined) {
            $('#mailchimp_double_optin').prop('checked', Boolean(settings.double_optin));
            console.log('Set double optin:', settings.double_optin);
        }
        
        if (settings.update_existing !== undefined) {
            $('#mailchimp_update_existing').prop('checked', Boolean(settings.update_existing));
            console.log('Set update existing:', settings.update_existing);
        }
        
        // Load tags
        if (settings.tags) {
            $('#mailchimp_tags').val(settings.tags);
            console.log('Set tags:', settings.tags);
        }
        
        // Load field mappings
        if (settings.field_mapping && Object.keys(settings.field_mapping).length > 0) {
            currentMappings = settings.field_mapping;
            console.log('Loaded field mappings:', currentMappings);
            
            // Apply mappings after a short delay to ensure dropdowns are populated
            setTimeout(function() {
                applyExistingMappings();
            }, 500);
        }
        
        console.log('=== Saved Settings Loaded ===');
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Integration enable/disable (handled by main integration toggle)
        $(document).on('change', '.integration-enable-checkbox[data-integration="mailchimp"]', function() {
            const isEnabled = $(this).is(':checked');
            
            if (isEnabled && !currentAudienceId) {
                loadAudiences();
            }
            
            // Show/hide field mapping section based on integration status
            if (isEnabled) {
                $('#mailchimp-field-mapping-section').slideDown();
            } else {
                $('#mailchimp-field-mapping-section').slideUp();
            }
        });

        // Audience selection
        $(document).on('change', '#mailchimp_audience', function() {
            const selectedValue = $(this).val();
            currentAudienceId = selectedValue;
            
            console.log('=== Audience Selection Event ===');
            console.log('Raw element value:', $(this).val());
            console.log('Selected value:', selectedValue);
            console.log('Current audience ID:', currentAudienceId);
            console.log('Element exists:', $(this).length > 0);
            console.log('All options:', $(this).find('option').map(function() { return {value: $(this).val(), text: $(this).text()}; }).get());
            
            if (currentAudienceId && currentAudienceId !== '0' && currentAudienceId !== '') {
                console.log('Loading Mailchimp fields for audience:', currentAudienceId);
                loadMailchimpFields(currentAudienceId);
                
                // Only auto-save if not loading initial data
                if (!isLoadingInitialData) {
                    autoSaveAudienceSelection(currentAudienceId);
                }
                
                // Show field mapping section
                $('#mailchimp-field-mapping-section').slideDown();
            } else {
                console.log('No valid audience selected, hiding field mapping section');
                $('#mailchimp-field-mapping-section').slideUp();
            }
        });

        // Refresh audiences
        $(document).on('click', '#refresh_audiences', function() {
            loadAudiences();
        });

        // Auto map fields
        $(document).on('click', '#mailchimp-auto-map-fields', function() {
            autoMapFields();
        });

        // Clear mappings
        $(document).on('click', '#mailchimp-clear-mappings', function() {
            clearAllMappings();
        });

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
            console.log('Mapping updated:', currentMappings);
        });

        // Form submission
        $(document).on('submit', '#mailchimp-form-settings', function(e) {
            e.preventDefault();
            saveFormSettings();
        });
    }

    /**
     * Load form fields from current form (REAL FIELDS from database)
     */
    function loadFormFields() {
        if (!currentFormId) {
            console.error('No form ID available');
            return;
        }

        console.log('Loading form fields for form ID:', currentFormId);

        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_form_fields',
                form_id: currentFormId,
                nonce: mavlersCFMailchimp.nonce
            },
            success: function(response) {
                console.log('Form fields response:', response);
                
                if (response.success && response.data) {
                    formFields = response.data;
                    console.log('Form fields loaded:', formFields);
                    
                    // Update the field mapping table
                    updateFieldMappingTable();
                } else {
                    console.error('Failed to load form fields:', response.message);
                    showMessage('Failed to load form fields: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading form fields:', error);
                showMessage('Network error loading form fields', 'error');
            }
        });
    }

    /**
     * Load audiences from Mailchimp API
     */
    function loadAudiences() {
        console.log('=== LOADING AUDIENCES ===');
        
        const $audienceSelect = $('#mailchimp_audience');
        const $loadingIndicator = $('#mailchimp_loading');
        
        if (!$audienceSelect.length) {
            console.error('Audience select element not found');
            return;
        }
        
        // Show loading state
        $loadingIndicator.show();
        $audienceSelect.prop('disabled', true);
        
        // Get API key from global settings or form
        let apiKey = '';
        
        // First try to get from global settings
        if (window.mailchimpGlobalSettings && window.mailchimpGlobalSettings.api_key) {
            apiKey = window.mailchimpGlobalSettings.api_key;
            console.log('Using API key from global settings');
        } else if (window.mavlersCFMailchimp && window.mavlersCFMailchimp.globalSettings && window.mavlersCFMailchimp.globalSettings.api_key) {
            apiKey = window.mavlersCFMailchimp.globalSettings.api_key;
            console.log('Using API key from mavlersCFMailchimp global settings');
        } else if (window.mailchimpFormSettings && window.mailchimpFormSettings.api_key) {
            apiKey = window.mailchimpFormSettings.api_key;
            console.log('Using API key from form settings');
        } else {
            // Try to get from the form
            const $apiKeyField = $('input[name="api_key"], #mailchimp_api_key');
            if ($apiKeyField.length) {
                apiKey = $apiKeyField.val();
                console.log('Using API key from form field');
            }
        }
        
        console.log('Using API key:', apiKey ? 'Present' : 'Not found');
        console.log('Global settings available:', window.mailchimpGlobalSettings);
        console.log('mavlersCFMailchimp global settings:', window.mavlersCFMailchimp ? window.mavlersCFMailchimp.globalSettings : 'Not available');
        
        if (!apiKey) {
            console.error('No API key available for audience loading');
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
            success: function(response) {
                console.log('=== AUDIENCES AJAX SUCCESS ===');
                console.log('Response:', response);
                
                if (response.success && response.data) {
                    const audiences = response.data;
                    console.log('Audiences loaded:', audiences);
                    
                    populateAudienceSelect(audiences);
                    
                    // Auto-select saved audience if available
                    if (window.mailchimpFormSettings && window.mailchimpFormSettings.audience_id) {
                        const savedAudienceId = window.mailchimpFormSettings.audience_id;
                        console.log('Auto-selecting saved audience:', savedAudienceId);
                        
                        if ($audienceSelect.find(`option[value="${savedAudienceId}"]`).length) {
                            $audienceSelect.val(savedAudienceId);
                            console.log('✓ Saved audience selected');
                            
                            // Trigger change event to load fields
                            $audienceSelect.trigger('change');
                        } else {
                            console.warn('Saved audience not found in available audiences');
                        }
                    }
                    
                    showMessage('Audiences loaded successfully', 'success');
                } else {
                    console.error('Audience loading failed:', response);
                    showMessage('Failed to load audiences: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('=== AUDIENCES AJAX ERROR ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                
                let errorMessage = 'Network error occurred while loading audiences';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    console.warn('Could not parse error response');
                }
                
                showMessage(errorMessage, 'error');
            },
            complete: function() {
                console.log('=== AUDIENCES AJAX COMPLETE ===');
                $loadingIndicator.hide();
                $audienceSelect.prop('disabled', false);
            }
        });
    }

    /**
     * Populate audience select dropdown
     */
    function populateAudienceSelect(audiences) {
        console.log('=== Populating Audience Select ===');
        console.log('Audiences:', audiences);
        console.log('Audiences type:', typeof audiences);
        console.log('Is array:', Array.isArray(audiences));
        
        const $select = $('#mailchimp_audience');
        console.log('Select element found:', $select.length > 0);
        
        $select.empty().append('<option value="">' + (mavlersCFMailchimp.strings.selectAudience || 'Select an audience...') + '</option>');

        // Handle both array and object formats
        if (Array.isArray(audiences)) {
            console.log('Processing audiences as array');
            $.each(audiences, function(index, audience) {
                console.log('Adding audience option from array:', index, audience);
                const option = $('<option></option>')
                    .val(audience.id) // Use audience.id instead of index
                    .text(audience.name + ' (' + audience.member_count + ' members)');
                
                console.log('Created option with value:', option.val(), 'and text:', option.text());
                $select.append(option);
            });
        } else {
            console.log('Processing audiences as object');
            $.each(audiences, function(id, audience) {
                console.log('Adding audience option from object:', id, audience);
                const option = $('<option></option>')
                    .val(id)
                    .text(audience.name + ' (' + audience.member_count + ' members)');
                
                console.log('Created option with value:', option.val(), 'and text:', option.text());
                $select.append(option);
            });
        }
        
        console.log('Audience select populated with', $select.find('option').length, 'options');
        console.log('All options after population:', $select.find('option').map(function() { 
            return {value: $(this).val(), text: $(this).text()}; 
        }).get());
        
        // If we have a saved audience ID, select it
        if (window.mailchimpFormSettings && window.mailchimpFormSettings.audience_id) {
            const savedAudienceId = window.mailchimpFormSettings.audience_id;
            console.log('=== ATTEMPTING TO SELECT SAVED AUDIENCE ===');
            console.log('Saved audience ID:', savedAudienceId);
            console.log('Available options:', $select.find('option').map(function() { 
                return {value: $(this).val(), text: $(this).text()}; 
            }).get());
            
            $select.val(savedAudienceId);
            currentAudienceId = savedAudienceId;
            console.log('Selected saved audience ID:', currentAudienceId);
            console.log('Current select value after setting:', $select.val());
            console.log('Select element found:', $select.length > 0);
            console.log('Select disabled:', $select.prop('disabled'));
            
            // Load Mailchimp fields for this audience after a short delay to ensure table is ready
            setTimeout(function() {
                console.log('Loading Mailchimp fields for audience:', currentAudienceId);
                loadMailchimpFields(currentAudienceId);
            }, 500);
            
            // Double-check that the value was set correctly after a longer delay
            // (Removed to prevent duplicate calls)
            // setTimeout(function() {
            //     console.log('=== AUDIENCE SELECTION VERIFICATION ===');
            //     console.log('Current select value:', $select.val());
            //     console.log('Expected value:', savedAudienceId);
            //     console.log('Values match:', $select.val() === savedAudienceId);
                
            //     if ($select.val() !== savedAudienceId) {
            //         console.log('Retrying audience selection - current value:', $select.val(), 'expected:', savedAudienceId);
            //         $select.val(savedAudienceId);
            //         if ($select.val() === savedAudienceId) {
            //             console.log('Audience selection retry successful');
            //             loadMailchimpFields(savedAudienceId);
            //         } else {
            //             console.log('Audience selection retry failed');
            //         }
            //     } else {
            //         console.log('Audience selection successful on first try');
            //     }
            // }, 1000);
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
            success: function(response) {
                if (response.success) {
                    console.log('Audience selection auto-saved');
                } else {
                    console.error('Failed to auto-save audience selection:', response.message);
                }
            },
            error: function() {
                console.error('Failed to auto-save audience selection');
            }
        });
    }

    /**
     * Load Mailchimp fields for selected audience (REAL FIELDS from Mailchimp API)
     */
    function loadMailchimpFields(audienceId) {
        // Prevent duplicate calls within 2 seconds
        const now = Date.now();
        if (now - lastLoadMailchimpFieldsCall < 2000) {
            console.log('Skipping loadMailchimpFields call - too soon after last call');
            return;
        }
        
        // Clear any existing timeout
        if (loadMailchimpFieldsTimeout) {
            clearTimeout(loadMailchimpFieldsTimeout);
        }
        
        // Debounce the call
        loadMailchimpFieldsTimeout = setTimeout(function() {
            loadMailchimpFieldsInternal(audienceId);
        }, 100);
    }
    
    /**
     * Internal function to actually load Mailchimp fields
     */
    function loadMailchimpFieldsInternal(audienceId) {
        console.log('=== Loading Mailchimp Fields ===');
        console.log('Audience ID:', audienceId);
        console.log('Current form ID:', currentFormId);
        console.log('mavlersCFMailchimp object:', window.mavlersCFMailchimp);
        console.log('jQuery available:', typeof $ !== 'undefined');
        console.log('AJAX URL:', window.mavlersCFMailchimp ? window.mavlersCFMailchimp.ajaxUrl : 'Not available');
        console.log('Nonce available:', window.mavlersCFMailchimp ? !!window.mavlersCFMailchimp.nonce : false);
        
        // Update last call time
        lastLoadMailchimpFieldsCall = Date.now();
        
        if (!audienceId) {
            console.error('No audience ID provided');
            showMessage('No audience selected', 'error');
            return;
        }
        
        if (!window.mavlersCFMailchimp || !window.mavlersCFMailchimp.ajaxUrl) {
            console.error('mavlersCFMailchimp object not available or missing ajaxUrl');
            showMessage('Configuration error: AJAX URL not available', 'error');
            return;
        }
        
        // Show loading state
        $('#mailchimp_loading').show();
        
        const requestData = {
            action: 'mailchimp_get_merge_fields',
            audience_id: audienceId,
            nonce: window.mavlersCFMailchimp.nonce
        };
        
        console.log('Making AJAX request with data:', requestData);
        console.log('Request URL:', window.mavlersCFMailchimp.ajaxUrl);
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: requestData,
            timeout: 30000, // 30 second timeout
            success: function(response) {
                console.log('=== AJAX Response Received ===');
                console.log('Response:', response);
                console.log('Response type:', typeof response);
                console.log('Response success:', response.success);
                console.log('Response data:', response.data);
                
                if (response.success && response.data && Object.keys(response.data).length > 0) {
                    mailchimpFields = response.data;
                    console.log('Mailchimp fields loaded successfully:', mailchimpFields);
                    console.log('Number of fields loaded:', Object.keys(mailchimpFields).length);
                    
                    // Update the field mapping table
                    updateFieldMappingTable();
                    
                    showMessage('Mailchimp fields loaded successfully (' + Object.keys(mailchimpFields).length + ' fields)', 'success');
                } else {
                    console.error('Failed to load Mailchimp fields');
                    console.error('Response success:', response.success);
                    console.error('Response message:', response.message);
                    console.error('Response data:', response.data);
                    
                    let errorMessage = 'Failed to load Mailchimp fields';
                    if (response.message) {
                        errorMessage += ': ' + response.message;
                    } else if (!response.data || Object.keys(response.data).length === 0) {
                        errorMessage += ': No fields found for this audience';
                    } else {
                        errorMessage += ': Unknown error';
                    }
                    
                    showMessage(errorMessage, 'error');
                    
                    // Still update table to show form fields even if Mailchimp fields fail
                    updateFieldMappingTable();
                }
            },
            error: function(xhr, status, error) {
                console.error('=== AJAX Error ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                console.error('Status Text:', xhr.statusText);
                
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
                
                // Still update table to show form fields even if Mailchimp fields fail
                updateFieldMappingTable();
            },
            complete: function() {
                // Hide loading state
                $('#mailchimp_loading').hide();
                console.log('=== AJAX Request Complete ===');
            }
        });
    }

    /**
     * Update the field mapping table with real form fields and Mailchimp fields
     */
    function updateFieldMappingTable() {
        const $tbody = $('#mailchimp-field-mapping-tbody');
        $tbody.empty();
        
        console.log('Updating field mapping table with:', {formFields, mailchimpFields});
        
        if (!formFields || Object.keys(formFields).length === 0) {
            console.warn('No form fields available for mapping');
            $tbody.append(`
                <tr class="no-fields-row">
                    <td colspan="4" style="text-align: center; padding: 20px; color: #666;">
                        No form fields found. Please check your form configuration.
                    </td>
                </tr>
            `);
            return;
        }
        
        console.log('Adding form fields to mapping table:', formFields);
        
        // Add each form field as a row
        $.each(formFields, function(fieldId, field) {
            const isRequired = field.required || false;
            const currentMapping = currentMappings[fieldId] || '';
            
            console.log('Creating mapping row for field:', fieldId, field);
            
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
        // Use a longer delay to ensure all elements are ready
        setTimeout(function() {
            console.log('=== APPLYING MAPPINGS AFTER TABLE UPDATE ===');
            applyExistingMappings();
            updateMappingStatus();
        }, 500);
        
        console.log('Field mapping table updated successfully');
    }

    /**
     * Populate Mailchimp field select dropdowns
     */
    function populateMailchimpFieldSelects() {
        console.log('Populating Mailchimp field selects with fields:', mailchimpFields);
        
        $('.mailchimp-field-select').each(function() {
            const $select = $(this);
            
            // Clear existing options except the first one
            $select.find('option:not(:first)').remove();
            
            // Always add the Email field first (required by Mailchimp)
            $select.append('<option value="email_address">📧 Email Address (Required)</option>');
            
            // Add fields from Mailchimp API
            if (mailchimpFields && Object.keys(mailchimpFields).length > 0) {
                console.log('Adding Mailchimp fields to dropdown:', mailchimpFields);
                
                $.each(mailchimpFields, function(tag, field) {
                    if (tag !== 'EMAIL') { // Skip EMAIL as we already added email_address
                        const icon = getFieldIcon(field.type);
                        const requiredText = field.required ? ' (Required)' : '';
                        const optionText = `${icon} ${field.name}${requiredText}`;
                        
                        console.log('Adding field option:', tag, optionText);
                        
                        $select.append(`<option value="${tag}">${optionText}</option>`);
                    }
                });
                
                console.log('Successfully populated Mailchimp field dropdowns');
            } else {
                console.warn('No Mailchimp fields available, adding common fields as fallback');
                
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
        console.log('=== APPLYING EXISTING MAPPINGS ===');
        console.log('Current mappings:', currentMappings);
        console.log('Available form fields:', formFields);
        console.log('Available Mailchimp fields:', mailchimpFields);
        
        let appliedCount = 0;
        
        $.each(currentMappings, function(formField, mailchimpField) {
            const $select = $(`.mailchimp-field-select[data-form-field="${formField}"]`);
            const $row = $(`.field-mapping-row[data-form-field="${formField}"]`);
            
            console.log(`Processing mapping: ${formField} -> ${mailchimpField}`);
            console.log(`Select element found:`, $select.length > 0);
            console.log(`Row element found:`, $row.length > 0);
            
            if ($select.length) {
                $select.val(mailchimpField);
                console.log(`✓ Applied mapping: ${formField} -> ${mailchimpField}`);
                appliedCount++;
                
                // Update status badge
                const $badge = $row.find('.mapping-status-badge');
                if ($badge.length) {
                    $badge.removeClass('unmapped').addClass('mapped').text('Mapped');
                    console.log(`✓ Updated status badge for: ${formField}`);
                }
            } else {
                console.log(`✗ Field mapping row not found for: ${formField}`);
            }
        });
        
        console.log(`Applied ${appliedCount} out of ${Object.keys(currentMappings).length} mappings`);
        
        // Update mapping status display
        updateMappingStatus();
    }
    
    // Make function globally available
    window.applyExistingMappings = applyExistingMappings;
    
    // Make other functions globally available for debugging
    window.loadFormFields = loadFormFields;
    window.loadAudiences = loadAudiences;
    window.loadMailchimpFields = loadMailchimpFields;
    window.updateFieldMappingTable = updateFieldMappingTable;
    window.applyExistingMappings = applyExistingMappings;
    window.currentMappings = currentMappings;
    window.formFields = formFields;
    window.mailchimpFields = mailchimpFields;
    
    // Add a comprehensive load all data function
    window.loadAllMailchimpData = function() {
        console.log('=== LOAD ALL MAILCHIMP DATA TRIGGERED ===');
        
        // Step 1: Load form fields
        console.log('Step 1: Loading form fields...');
        loadFormFields();
        
        // Step 2: Load audiences after a short delay
        setTimeout(function() {
            console.log('Step 2: Loading audiences...');
            loadAudiences();
            
            // Step 3: Set audience and load Mailchimp fields after audiences are loaded
            setTimeout(function() {
                if (window.mailchimpFormSettings && window.mailchimpFormSettings.audience_id) {
                    console.log('Step 3: Setting audience to:', window.mailchimpFormSettings.audience_id);
                    $('#mailchimp_audience').val(window.mailchimpFormSettings.audience_id);
                    
                    // Trigger audience change event to load Mailchimp fields
                    $('#mailchimp_audience').trigger('change');
                }
                
                // Step 4: Apply mappings after everything is loaded
                setTimeout(function() {
                    console.log('Step 4: Applying field mappings...');
                    if (window.mailchimpFormSettings && window.mailchimpFormSettings.field_mapping) {
                        currentMappings = window.mailchimpFormSettings.field_mapping;
                        console.log('Applied mappings from saved settings:', currentMappings);
                        applyExistingMappings();
                    }
                }, 2000);
            }, 1500);
        }, 500);
    };
    
    // Add debug function to check current form settings
    window.debugFormSettings = function() {
        console.log('=== FORM SETTINGS DEBUG ===');
        console.log('Current Form ID:', currentFormId);
        console.log('Window mailchimpFormId:', window.mailchimpFormId);
        console.log('Window mailchimpFormSettings:', window.mailchimpFormSettings);
        console.log('Settings form_id:', window.mailchimpFormSettings ? window.mailchimpFormSettings.form_id : 'undefined');
        console.log('Settings timestamp:', window.mailchimpFormSettings ? window.mailchimpFormSettings.timestamp : 'undefined');
        console.log('Current mappings:', currentMappings);
        console.log('Form fields:', formFields);
        console.log('Mailchimp fields:', mailchimpFields);
        console.log('=== END DEBUG ===');
    };
    
    // Add debug function to test global settings save
    window.testGlobalSettingsSave = function() {
        console.log('=== TESTING GLOBAL SETTINGS SAVE ===');
        
        const testData = {
            action: 'mailchimp_save_global_settings',
            nonce: mavlersCFMailchimp.nonce,
            settings: {
                api_key: 'test-api-key-' + Date.now(),
                enable_analytics: true,
                enable_webhooks: false,
                batch_processing: true
            }
        };
        
        console.log('Test data:', testData);
        
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: testData,
            success: function(response) {
                console.log('Global settings save test response:', response);
                if (response.success) {
                    showMessage('Global settings save test successful!', 'success');
                } else {
                    showMessage('Global settings save test failed: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Global settings save test error:', {xhr, status, error});
                showMessage('Global settings save test failed: ' + error, 'error');
            }
        });
    };
    
    // Add function to manually apply mappings from saved settings
    window.applyMappingsFromSettings = function() {
        console.log('=== MANUALLY APPLYING MAPPINGS FROM SETTINGS ===');
        
        if (window.mailchimpFormSettings && window.mailchimpFormSettings.field_mapping) {
            currentMappings = window.mailchimpFormSettings.field_mapping;
            console.log('Set currentMappings from settings:', currentMappings);
            applyExistingMappings();
        } else {
            console.log('No field mappings found in settings');
        }
    };
    
    // Add function to collect and save mappings
    window.saveCurrentMappings = function() {
        console.log('=== MANUALLY SAVING CURRENT MAPPINGS ===');
        
        const uiMappings = collectCurrentMappings();
        console.log('Collected UI mappings:', uiMappings);
        
        if (Object.keys(uiMappings).length > 0) {
            currentMappings = uiMappings;
            console.log('Updated currentMappings:', currentMappings);
            return true;
        } else {
            console.log('No mappings found in UI');
            return false;
        }
    };

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
                        console.log(`Auto-mapped: ${fieldId} -> ${rule.target}`);
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
        const hasEmailMapping = Object.values(currentMappings).includes('email_address');
        
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
        
        console.log('Mapping status updated:', {mappingCount, totalFields, hasEmailMapping});
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
                console.log(`Collected mapping: ${formField} -> ${selectedValue}`);
            }
        });
        
        console.log('Collected mappings from UI:', mappings);
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
            field_mapping: fieldMappings
        };
        
        // Add form-specific identifier
        formData.form_specific_id = currentFormId;
        formData.timestamp = Date.now();
        
        console.log('=== SAVE FORM SETTINGS DEBUG ===');
        console.log('Current Form ID:', currentFormId);
        console.log('Form ID type:', typeof currentFormId);
        console.log('Nonce:', mavlersCFMailchimp.nonce);
        console.log('AJAX URL:', mavlersCFMailchimp.ajaxUrl);
        console.log('Integration enabled:', $('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked'));
        console.log('Audience ID:', $('#mailchimp_audience').val());
        console.log('Double optin:', $('#mailchimp_double_optin').is(':checked'));
        console.log('Update existing:', $('#mailchimp_update_existing').is(':checked'));
        console.log('Tags:', $('#mailchimp_tags').val());
        console.log('Field mappings:', currentMappings);
        console.log('Full form data:', formData);
        console.log('=== END DEBUG ===');
        
        $.ajax({
            url: mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Save response:', response);
                console.log('Response type:', typeof response);
                console.log('Response keys:', Object.keys(response || {}));
                
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
                    const errorMessage = response && response.data ? response.data : (response && response.message ? response.message : 'Failed to save settings');
                    console.error('Save failed:', errorMessage);
                    $status.addClass('show error').text(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr, status, error});
                console.error('Response text:', xhr.responseText);
                
                let errorMessage = 'Network error occurred';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.data) {
                        errorMessage = response.data;
                    } else if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    console.error('Failed to parse error response:', e);
                }
                
                $status.addClass('show error').text(errorMessage);
            },
            complete: function() {
                $submit.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Mailchimp Settings');
                
                setTimeout(function() {
                    $status.removeClass('show');
                }, 3000);
            }
        });
    }

    /**
     * Show message to user
     */
    function showMessage(message, type = 'info') {
        console.log(`Mailchimp Message [${type}]:`, message);
        
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
        
        // Auto-remove success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                $message.fadeOut();
            }, 5000);
        }
        
        // Auto-remove info messages after 3 seconds
        if (type === 'info') {
            setTimeout(() => {
                $message.fadeOut();
            }, 3000);
        }
    }

})(jQuery); 