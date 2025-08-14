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
        console.log('🚀 Mailchimp Form Settings: Document ready, initializing...');
        console.log('🔍 Checking for integration checkbox:', $('.integration-enable-checkbox[data-integration="mailchimp"]').length);
        console.log('🔍 Checking for form settings:', $('.mailchimp-form-settings').length);
        console.log('🔍 Window mailchimpFormSettings:', window.mailchimpFormSettings);
        
        initializeMailchimpFormSettings();
    });

    /**
     * Initialize the Mailchimp form settings
     */
    function initializeMailchimpFormSettings() {
        console.log('📋 Initializing Mailchimp form settings...');
        
        // Get form ID from global or data attribute
        currentFormId = window.mailchimpFormId || $('.mailchimp-form-settings').data('form-id') || 0;
        console.log('📋 Current form ID:', currentFormId);
        
        // Verify we're working with the correct form
        if (window.mailchimpFormSettings && window.mailchimpFormSettings.form_id && window.mailchimpFormSettings.form_id !== currentFormId) {
            console.warn('⚠️ Form ID mismatch! Settings are for form ID:', window.mailchimpFormSettings.form_id, 'but current form is:', currentFormId);
        }
        
        // Load initial settings
        loadInitialSettings();
        
        // Bind events
        bindEvents();
        
        // Load form fields immediately
        loadFormFields();
        
        // Load audiences if integration is enabled or if we have saved settings
        if ($('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked') || (window.mailchimpFormSettings && window.mailchimpFormSettings.audience_id)) {
            console.log('📋 Integration is enabled or has saved settings, loading audiences...');
            // Add a small delay to ensure the DOM is ready
            setTimeout(function() {
                loadAudiences();
            }, 100);
        } else {
            console.log('📋 Integration not enabled and no saved settings');
        }
        
        // Manual trigger to ensure everything loads properly
        setTimeout(function() {
            console.log('📋 Manual trigger: Checking if everything loaded properly');
            if (window.mailchimpFormSettings && window.mailchimpFormSettings.enabled) {
                console.log('📋 Manual trigger: Integration should be enabled, ensuring proper loading');
                
                // Ensure checkbox is checked
                const $checkbox = $('.integration-enable-checkbox[data-integration="mailchimp"]');
                if ($checkbox.length && !$checkbox.is(':checked')) {
                    $checkbox.prop('checked', true);
                    console.log('📋 Manual trigger: Set checkbox to checked');
                }
                
                // Ensure settings panel is visible
                if ($('#mailchimp-form-settings').is(':hidden')) {
                    $('#mailchimp-form-settings').slideDown();
                    $('#field-mapping-section').slideDown();
                    console.log('📋 Manual trigger: Showed settings panels');
                }
                
                // Load audiences if not loaded
                if (!$('#mailchimp_audience option').length || $('#mailchimp_audience option').length === 1) {
                    console.log('📋 Manual trigger: Loading audiences');
                    loadAudiences();
                }
                
                // Apply mappings again
                setTimeout(function() {
                    console.log('📋 Manual trigger: Applying mappings');
                    applyExistingMappings();
                }, 2000);
            }
        }, 1000);
        
        // Add test button for debugging
        addTestButton();
    }
    
    /**
     * Add test button for debugging
     */
    function addTestButton() {
        const $testButton = $('<button type="button" id="test-merge-fields" class="button button-secondary" style="margin: 10px 0;">Test Merge Fields</button>');
        const $simpleTestButton = $('<button type="button" id="test-simple-ajax" class="button button-secondary" style="margin: 10px 0;">Test Simple AJAX</button>');
        const $debugButton = $('<button type="button" id="debug-settings" class="button button-secondary" style="margin: 10px 0;">Debug Settings</button>');
        $('.field-mapping-actions').prepend($debugButton);
        $('.field-mapping-actions').prepend($simpleTestButton);
        $('.field-mapping-actions').prepend($testButton);
        
        $testButton.on('click', function() {
            const audienceId = $('#mailchimp_audience').val();
            if (audienceId) {
                console.log('📋 Test button clicked, testing merge fields for audience:', audienceId);
                loadMailchimpFieldsInternal(audienceId);
            } else {
                alert('Please select an audience first');
            }
        });
        
        $simpleTestButton.on('click', function() {
            console.log('📋 Simple test button clicked');
            $.ajax({
                url: window.mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_simple_test',
                    nonce: window.mavlersCFMailchimp.nonce
                },
                success: function(response) {
                    console.log('📋 Simple test success:', response);
                    alert('Simple test successful! Check console for details.');
                },
                error: function(xhr, status, error) {
                    console.log('📋 Simple test error:', {xhr: xhr, status: status, error: error});
                    alert('Simple test failed: ' + error);
                }
            });
        });
        
        $debugButton.on('click', function() {
            console.log('📋 Debug settings button clicked');
            $.ajax({
                url: window.mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'check_mailchimp_database',
                    form_id: currentFormId,
                    nonce: window.mavlersCFMailchimp.nonce
                },
                success: function(response) {
                    console.log('📋 Debug settings success:', response);
                    alert('Debug data loaded! Check console for details.');
                },
                error: function(xhr, status, error) {
                    console.log('📋 Debug settings error:', {xhr: xhr, status: status, error: error});
                    alert('Debug failed: ' + error);
                }
            });
        });
    }

    /**
     * Load initial settings from server
     */
    function loadInitialSettings() {
        console.log('📋 Loading initial settings...');
        console.log('📋 Window mailchimpFormSettings:', window.mailchimpFormSettings);
        
        if (window.mailchimpFormSettings) {
            const settings = window.mailchimpFormSettings;
            console.log('📋 Settings found:', settings);
            
            // Set form values with proper boolean handling
            $('#mailchimp_double_optin').prop('checked', Boolean(settings.double_optin));
            $('#mailchimp_update_existing').prop('checked', Boolean(settings.update_existing));
            $('#mailchimp_tags').val(settings.tags || '');
            
            // Set audience
            if (settings.audience_id) {
                currentAudienceId = settings.audience_id;
                console.log('📋 Setting audience ID:', currentAudienceId);
                
                // Try to set the audience dropdown immediately if it exists
                const $audienceSelect = $('#mailchimp_audience');
                if ($audienceSelect.length) {
                    $audienceSelect.val(settings.audience_id);
                    console.log('📋 Audience dropdown set to:', settings.audience_id);
                } else {
                    console.log('📋 Audience dropdown not found');
                }
            }
            
            // Set mappings
            currentMappings = settings.field_mapping || {};
            console.log('📋 Current mappings:', currentMappings);
            
            // Check if integration should be enabled based on various indicators
            const shouldBeEnabled = settings.enabled || 
                                  settings.audience_id || 
                                  (settings.field_mapping && Object.keys(settings.field_mapping).length > 0) ||
                                  $('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked');
            
            console.log('📋 Should integration be enabled?', shouldBeEnabled);
            console.log('📋 Settings enabled:', settings.enabled);
            console.log('📋 Has audience ID:', !!settings.audience_id);
            console.log('📋 Has field mappings:', !!(settings.field_mapping && Object.keys(settings.field_mapping).length > 0));
            console.log('📋 Checkbox checked:', $('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked'));
            
            // Set integration enabled state
            if (shouldBeEnabled) {
                console.log('📋 Settings show integration should be enabled');
                const $checkbox = $('.integration-enable-checkbox[data-integration="mailchimp"]');
                if ($checkbox.length) {
                    $checkbox.prop('checked', true);
                    console.log('📋 Integration checkbox set to checked');
                    
                    // Show the settings panel
                    $('#mailchimp-form-settings').slideDown();
                    // Also show the field mapping section
                    $('#field-mapping-section').slideDown();
                    
                    // If we have a saved audience, load audiences to populate the dropdown
                    if (settings.audience_id) {
                        console.log('📋 Loading audiences for saved audience ID');
                        loadAudiences();
                    }
                    
                    // Apply mappings after a delay to ensure everything is loaded
                    setTimeout(function() {
                        console.log('📋 Applying existing mappings after delay');
                        applyExistingMappings();
                    }, 3000); // Increased delay to ensure everything is loaded
                } else {
                    console.log('📋 Integration checkbox not found');
                }
            } else {
                console.log('📋 Settings show integration should be disabled');
            }
        } else {
            console.log('📋 No mailchimpFormSettings found in window object');
        }
    }
    
    /**
     * Load saved settings into the form (called when integration is enabled)
     */
    function loadSavedSettings() {
        console.log('📋 Loading saved settings...');
        if (window.mailchimpFormSettings) {
            const settings = window.mailchimpFormSettings;
            
            // Set form values
            $('#mailchimp_double_optin').prop('checked', Boolean(settings.double_optin));
            $('#mailchimp_update_existing').prop('checked', Boolean(settings.update_existing));
            $('#mailchimp_tags').val(settings.tags || '');
            
            // Set audience if available
            if (settings.audience_id) {
                currentAudienceId = settings.audience_id;
                $('#mailchimp_audience').val(settings.audience_id);
            }
            
            // Set mappings
            currentMappings = settings.field_mapping || {};
            
            // Apply mappings after a delay
            setTimeout(function() {
                applyExistingMappings();
            }, 1000);
        }
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        console.log('📋 Binding events...');
        
        // Integration enable/disable toggle
        $(document).on('change', '.integration-enable-checkbox[data-integration="mailchimp"]', function() {
            const isEnabled = $(this).is(':checked');
            console.log('📋 Integration checkbox changed, enabled:', isEnabled);
            
            if (isEnabled) {
                $('#mailchimp-form-settings').slideDown();
                $('#field-mapping-section').slideDown();
                
                // Load saved settings when enabled
                loadSavedSettings();
                
                // Load audiences if not already loaded
                if (!$('#mailchimp_audience option').length || $('#mailchimp_audience option').length === 1) {
                    loadAudiences();
                }
            } else {
                $('#mailchimp-form-settings').slideUp();
                $('#field-mapping-section').slideUp();
            }
        });

        // Audience selection change
        $(document).on('change', '#mailchimp_audience', function() {
            const audienceId = $(this).val();
            console.log('📋 Audience selection changed:', audienceId);
            if (audienceId) {
                currentAudienceId = audienceId;
                // Show field mapping section
                $('#field-mapping-section').slideDown();
                // Load Mailchimp fields for this audience
                loadMailchimpFields(audienceId);
                autoSaveAudienceSelection(audienceId);
            } else {
                // Hide field mapping section if no audience selected
                $('#field-mapping-section').slideUp();
            }
        });

        // Refresh audiences button
        $(document).on('click', '#refresh_audiences', function() {
            console.log('📋 Refresh audiences button clicked');
            loadAudiences();
        });

        // Auto-save on form changes
        $(document).on('change', '#mailchimp_double_optin, #mailchimp_update_existing, #mailchimp_tags', function() {
            saveFormSettings();
        });

        // Field mapping changes
        $(document).on('change', '.mailchimp-field-select', function() {
            updateMappingStatus();
            saveFormSettings();
        });

        // Auto-map button
        $(document).on('click', '#auto-map-fields', function() {
            autoMapFields();
        });

        // Clear mappings button
        $(document).on('click', '#clear-mappings', function() {
            clearAllMappings();
        });
        
        console.log('📋 Events bound successfully');
    }

    /**
     * Load form fields from the server
     */
    function loadFormFields() {
        if (!currentFormId) return;
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_form_fields',
                form_id: currentFormId,
                nonce: window.mavlersCFMailchimp.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    formFields = response.data;
                    updateFieldMappingTable();
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error loading form fields: ' + error, 'error');
            }
        });
    }

    /**
     * Load Mailchimp audiences
     */
    function loadAudiences() {
        if (!window.mavlersCFMailchimp || !window.mavlersCFMailchimp.ajaxUrl) {
            showMessage('Mailchimp configuration not available', 'error');
            return;
        }

        const $audienceSelect = $('#mailchimp_audience');
        if (!$audienceSelect.length) {
            showMessage('Audience select element not found', 'error');
            return;
        }

        // Show loading state
        $audienceSelect.prop('disabled', true);
        $audienceSelect.html('<option value="">Loading audiences...</option>');

        // Get API key from global settings
        let apiKey = '';
        if (window.mailchimpGlobalSettings && window.mailchimpGlobalSettings.api_key) {
            apiKey = window.mailchimpGlobalSettings.api_key;
        } else if (window.mavlersCFMailchimp && window.mavlersCFMailchimp.apiKey) {
            apiKey = window.mavlersCFMailchimp.apiKey;
        }

        if (!apiKey) {
            showMessage('Mailchimp API key not configured', 'error');
            $audienceSelect.prop('disabled', false);
            return;
        }

        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_audiences',
                nonce: window.mavlersCFMailchimp.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    populateAudienceSelect(response.data);
                } else {
                    showMessage('Error loading audiences: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error loading audiences: ' + error, 'error');
            },
            complete: function() {
                $audienceSelect.prop('disabled', false);
            }
        });
    }

    /**
     * Populate the audience select dropdown
     */
    function populateAudienceSelect(audiences) {
        const $audienceSelect = $('#mailchimp_audience');
        
        // Clear existing options
        $audienceSelect.empty();
        $audienceSelect.append('<option value="">Select an audience</option>');
        
        // Add audience options
        if (Array.isArray(audiences)) {
            audiences.forEach(function(audience) {
                const optionText = audience.name + ' (' + audience.member_count + ' members)';
                $audienceSelect.append('<option value="' + audience.id + '">' + optionText + '</option>');
            });
        }
        
        // Auto-select saved audience if available
        if (currentAudienceId) {
            console.log('📋 Setting saved audience ID:', currentAudienceId);
            $audienceSelect.val(currentAudienceId);
            
            // Trigger change event to load fields
            setTimeout(function() {
                $audienceSelect.trigger('change');
            }, 100);
        }
        
        showMessage('Audiences loaded successfully', 'success');
    }

    /**
     * Auto-save audience selection
     */
    function autoSaveAudienceSelection(audienceId) {
        if (!audienceId) return;
        
        // Update current audience ID
        currentAudienceId = audienceId;
        console.log('📋 Auto-saving audience selection:', audienceId);
        
        // Save to server
        saveFormSettings();
    }

    /**
     * Load Mailchimp merge fields for the selected audience
     */
    function loadMailchimpFields(audienceId) {
        if (!audienceId) return;
        
        console.log('📋 Loading Mailchimp fields for audience:', audienceId);
        
        // Debounce to prevent multiple rapid calls
        const now = Date.now();
        if (now - lastLoadMailchimpFieldsCall < 1000) {
            return;
        }
        lastLoadMailchimpFieldsCall = now;
        
        loadMailchimpFieldsInternal(audienceId);
    }

    /**
     * Internal function to load Mailchimp fields
     */
    function loadMailchimpFieldsInternal(audienceId) {
        if (!window.mavlersCFMailchimp || !window.mavlersCFMailchimp.ajaxUrl) {
            showMessage('Mailchimp configuration not available', 'error');
            return;
        }

        // Show loading state
        const $table = $('#field-mapping-tbody');
        if ($table.length) {
            $table.html('<tr><td colspan="4" style="text-align: center; padding: 20px;"><span class="dashicons dashicons-update-alt spinning"></span> Loading Mailchimp fields...</td></tr>');
        }

        console.log('📋 Making AJAX request for merge fields');
        console.log('📋 Audience ID:', audienceId);
        console.log('📋 AJAX URL:', window.mavlersCFMailchimp.ajaxUrl);
        console.log('📋 Nonce:', window.mavlersCFMailchimp.nonce);

        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_merge_fields',
                audience_id: audienceId,
                nonce: window.mavlersCFMailchimp.nonce
            },
            success: function(response) {
                console.log('📋 AJAX success response:', response);
                if (response.success && response.data) {
                    mailchimpFields = response.data;
                    console.log('📋 Mailchimp fields loaded:', Object.keys(mailchimpFields).length, 'fields');
                    console.log('📋 Mailchimp fields data:', mailchimpFields);
                    updateFieldMappingTable();
                    showMessage('Mailchimp fields loaded successfully (' + Object.keys(mailchimpFields).length + ' fields)', 'success');
                } else {
                    console.log('📋 AJAX response indicates failure:', response);
                    showMessage('Error loading Mailchimp fields: ' + (response.data || response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('📋 AJAX error:', {xhr: xhr, status: status, error: error});
                console.log('📋 Response text:', xhr.responseText);
                showMessage('Error loading Mailchimp fields: ' + error, 'error');
            }
        });
    }

    /**
     * Update the field mapping table
     */
    function updateFieldMappingTable() {
        const $table = $('#field-mapping-tbody');
        if (!$table.length) return;
        
        // Clear existing rows
        $table.empty();
        
        // Add form fields to mapping table
        if (formFields) {
            Object.keys(formFields).forEach(function(fieldId) {
                const field = formFields[fieldId];
                const row = createMappingRow(field);
                $table.append(row);
            });
        } else {
            // Show message if no form fields
            $table.append('<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">No form fields found</td></tr>');
        }
        
        // Populate Mailchimp field selects
        populateMailchimpFieldSelects();
        
        // Apply existing mappings
        applyExistingMappings();
        
        // Update mapping status
        updateMappingStatus();
    }

    /**
     * Create a mapping row for a form field
     */
    function createMappingRow(field) {
        const icon = getFieldIcon(field.type);
        const required = field.required ? ' *' : '';
        
        return `
            <tr data-field-id="${field.id}">
                <td class="form-field-column">
                    <span class="field-icon">${icon}</span>
                    <strong>${field.label}${required}</strong>
                    <small class="field-type">(${field.type})</small>
                </td>
                <td class="arrow-column">
                    <span class="mapping-arrow">→</span>
                </td>
                <td class="mailchimp-field-column">
                    <select class="mailchimp-field-select" data-form-field="${field.id}">
                        <option value="">No mapping</option>
                    </select>
                </td>
                <td class="status-column">
                    <span class="mapping-status" data-field="${field.id}">
                        <span class="status-badge status-unmapped">Unmapped</span>
                    </span>
                </td>
            </tr>
        `;
    }

    /**
     * Populate Mailchimp field select dropdowns
     */
    function populateMailchimpFieldSelects() {
        const $selects = $('.mailchimp-field-select');
        
        $selects.each(function() {
            const $select = $(this);
            const formFieldId = $select.data('form-field');
            
            // Clear existing options
            $select.empty();
            $select.append('<option value="">No mapping</option>');
            
            // Add Mailchimp fields
            if (mailchimpFields && Object.keys(mailchimpFields).length > 0) {
                Object.keys(mailchimpFields).forEach(function(fieldKey) {
                    const field = mailchimpFields[fieldKey];
                    const optionText = fieldKey + ' ' + field.name;
                    $select.append('<option value="' + fieldKey + '">' + optionText + '</option>');
                });
            } else {
                // Add common fields as fallback
                const commonFields = {
                    'FNAME': 'First Name',
                    'LNAME': 'Last Name',
                    'email_address': 'Email Address',
                    'PHONE': 'Phone Number',
                    'ADDRESS': 'Address',
                    'COMPANY': 'Company'
                };
                
                Object.keys(commonFields).forEach(function(fieldKey) {
                    $select.append('<option value="' + fieldKey + '">' + fieldKey + ' ' + commonFields[fieldKey] + '</option>');
                });
            }
        });
    }

    /**
     * Get field icon based on field type
     */
    function getFieldIcon(type) {
        const icons = {
            'text': '📝',
            'email': '📧',
            'tel': '📞',
            'textarea': '📄',
            'select': '📋',
            'checkbox': '☑️',
            'radio': '🔘',
            'number': '🔢',
            'date': '📅',
            'url': '🔗'
        };
        return icons[type] || '📝';
    }

    /**
     * Apply existing mappings to the form
     */
    function applyExistingMappings() {
        console.log('📋 Applying existing mappings:', currentMappings);
        console.log('📋 Window mailchimpFormSettings:', window.mailchimpFormSettings);
        
        if (!currentMappings || Object.keys(currentMappings).length === 0) {
            console.log('📋 No existing mappings to apply');
            return;
        }
        
        let appliedCount = 0;
        const totalMappings = Object.keys(currentMappings).length;
        
        Object.keys(currentMappings).forEach(function(formFieldId) {
            const mailchimpField = currentMappings[formFieldId];
            const $select = $('.mailchimp-field-select[data-form-field="' + formFieldId + '"]');
            const $row = $select.closest('tr');
            
            console.log('📋 Applying mapping:', formFieldId, '->', mailchimpField);
            console.log('📋 Select element found:', $select.length);
            console.log('📋 Row element found:', $row.length);
            
            if ($select.length && $row.length) {
                $select.val(mailchimpField);
                updateMappingStatusForField(formFieldId);
                appliedCount++;
                console.log('📋 Successfully applied mapping for field:', formFieldId);
            } else {
                console.log('📋 Could not find select or row for field:', formFieldId);
            }
        });
        
        updateMappingStatus();
        console.log('📋 Applied', appliedCount, 'of', totalMappings, 'mappings');
    }

    /**
     * Update mapping status for a specific field
     */
    function updateMappingStatusForField(fieldId) {
        const $select = $('.mailchimp-field-select[data-form-field="' + fieldId + '"]');
        const $status = $('.mapping-status[data-field="' + fieldId + '"]');
        
        if (!$select.length || !$status.length) {
            console.log('📋 Could not update status for field:', fieldId, '- select or status not found');
            return;
        }
        
        const selectedValue = $select.val();
        const $statusBadge = $status.find('.status-badge');
        
        if (selectedValue) {
            $statusBadge.removeClass('status-unmapped status-error').addClass('status-mapped');
            $statusBadge.text('Mapped');
            console.log('📋 Field', fieldId, 'is mapped to:', selectedValue);
        } else {
            $statusBadge.removeClass('status-mapped status-error').addClass('status-unmapped');
            $statusBadge.text('Unmapped');
            console.log('📋 Field', fieldId, 'is unmapped');
        }
    }

    /**
     * Auto-map fields based on common patterns
     */
    function autoMapFields() {
        if (!formFields || !mailchimpFields) {
            showMessage('Please load form fields and Mailchimp fields first', 'warning');
            return;
        }
        
        let mappedCount = 0;
        
        Object.keys(formFields).forEach(function(fieldId) {
            const field = formFields[fieldId];
            const fieldLabel = field.label.toLowerCase();
            const fieldName = field.name.toLowerCase();
            
            // Common mapping patterns
            const mappings = {
                'first name': 'FNAME',
                'firstname': 'FNAME',
                'fname': 'FNAME',
                'last name': 'LNAME',
                'lastname': 'LNAME',
                'lname': 'LNAME',
                'email': 'email_address',
                'email address': 'email_address',
                'phone': 'PHONE',
                'telephone': 'PHONE',
                'company': 'COMPANY',
                'organization': 'COMPANY',
                'address': 'ADDRESS'
            };
            
            // Try to find a match
            let mailchimpField = null;
            
            // Check label first
            if (mappings[fieldLabel]) {
                mailchimpField = mappings[fieldLabel];
            } else if (mappings[fieldName]) {
                mailchimpField = mappings[fieldName];
            }
            
            // Apply mapping if found
            if (mailchimpField && mailchimpFields[mailchimpField]) {
                const $select = $('.mailchimp-field-select[data-form-field="' + fieldId + '"]');
                if ($select.length) {
                    $select.val(mailchimpField);
                    updateMappingStatusForField(fieldId);
                    mappedCount++;
                }
            }
        });
        
        updateMappingStatus();
        saveFormSettings();
        
        if (mappedCount > 0) {
            showMessage('Auto-mapped ' + mappedCount + ' fields', 'success');
        } else {
            showMessage('No fields could be auto-mapped', 'info');
        }
    }

    /**
     * Clear all field mappings
     */
    function clearAllMappings() {
        $('.mailchimp-field-select').val('');
        $('.mapping-status .status-badge').removeClass('status-mapped status-error').addClass('status-unmapped').text('Unmapped');
        updateMappingStatus();
        saveFormSettings();
        showMessage('All mappings cleared', 'info');
    }

    /**
     * Update the overall mapping status
     */
    function updateMappingStatus() {
        const totalFields = $('.mailchimp-field-select').length;
        const mappedFields = $('.mailchimp-field-select').filter(function() {
            return $(this).val() !== '';
        }).length;
        
        const hasEmailMapping = $('.mailchimp-field-select').filter(function() {
            return $(this).val() === 'email_address';
        }).length > 0;
        
        // Update status display
        $('#mapping-status').text(mappedFields + ' of ' + totalFields + ' fields mapped');
        
        if (hasEmailMapping) {
            $('#mapping-status').addClass('has-email').removeClass('no-email');
        } else {
            $('#mapping-status').addClass('no-email').removeClass('has-email');
        }
    }

    /**
     * Collect current mappings from the UI
     */
    function collectCurrentMappings() {
        const mappings = {};
        
        $('.mailchimp-field-select').each(function() {
            const $select = $(this);
            const formFieldId = $select.data('form-field');
            const mailchimpField = $select.val();
            
            if (mailchimpField) {
                mappings[formFieldId] = mailchimpField;
            }
        });
        
        return mappings;
    }

    /**
     * Save form settings to the server
     */
    function saveFormSettings() {
        if (!currentFormId) return;
        
        const settings = {
            enabled: $('.integration-enable-checkbox[data-integration="mailchimp"]').is(':checked'),
            audience_id: $('#mailchimp_audience').val(),
            double_optin: $('#mailchimp_double_optin').is(':checked'),
            update_existing: $('#mailchimp_update_existing').is(':checked'),
            tags: $('#mailchimp_tags').val(),
            field_mapping: collectCurrentMappings()
        };
        
        $.ajax({
            url: window.mavlersCFMailchimp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_save_form_settings',
                form_id: currentFormId,
                settings: settings,
                nonce: window.mavlersCFMailchimp.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Settings saved successfully
                } else {
                    showMessage('Error saving settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error saving settings: ' + error, 'error');
            }
        });
    }

    /**
     * Show a message to the user
     */
    function showMessage(message, type = 'info') {
        // Create message element
        const $message = $('<div class="mailchimp-message mailchimp-message-' + type + '">' + message + '</div>');
        
        // Add to page
        $('.mailchimp-form-settings').prepend($message);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

})(jQuery); 