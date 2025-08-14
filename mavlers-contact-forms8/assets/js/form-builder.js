jQuery(document).ready(function($) {
    // Initialize form builder
    initFormBuilder();

    function initFormBuilder() {
        // Add field buttons
        $('.field-type').on('click', function() {
            var type = $(this).data('type');
            addField(type);
        });

        // Field selection
        $(document).on('click', '.form-field', function() {
            $('.form-field').removeClass('selected');
            $(this).addClass('selected');
            showFieldSettings($(this));
        });

        // Initialize field actions
        initFieldActions();

        // Initialize save button handler
        $('#save-form').on('click', function() {
            saveForm();
        });

        // Initialize preview button handler
        $('#preview-form').on('click', function() {
            previewForm();
        });

        // Initialize sortable fields
        initSortableFields();

        // Integration tab functionality
        $('#enable-ajax, #enable-scroll-to-error, #enable-honeypot').on('change', function() {
            updateFormSettings();
        });

        // Mailchimp integration
        $('#enable-mailchimp').on('change', function() {
            if ($(this).is(':checked')) {
                $('#mailchimp-settings').slideDown();
            } else {
                $('#mailchimp-settings').slideUp();
            }
            updateFormSettings();
        });

        // Mailchimp settings change handlers
        $('#mailchimp-api-key, #mailchimp-list-id, #mailchimp-email-field, #mailchimp-name-field, #mailchimp-double-optin').on('change input', function() {
            // Debug: Log API key changes immediately
            if ($(this).attr('id') === 'mailchimp-api-key') {
                console.log('🔧 IMMEDIATE DEBUG: API key field changed to:', $(this).val());
                console.log('🔧 IMMEDIATE DEBUG: Field element:', this);
            }
            updateFormSettings();
        });

        // HubSpot integration
        $('#enable-hubspot').on('change', function() {
            if ($(this).is(':checked')) {
                $('#hubspot-settings').slideDown();
            } else {
                $('#hubspot-settings').slideUp();
            }
            updateFormSettings();
        });

        // HubSpot settings change handlers
        $('#hubspot-api-key, #hubspot-portal-id, #hubspot-email-field, #hubspot-firstname-field, #hubspot-lastname-field, #hubspot-phone-field, #hubspot-company-field').on('change input', function() {
            updateFormSettings();
        });

        // Redirect type toggle
        $('#redirect-type').on('change', function() {
            var redirectType = $(this).val();
            if (redirectType === 'redirect') {
                $('#thank-you-message-container').hide();
                $('#redirect-url-container').show();
            } else {
                $('#thank-you-message-container').show();
                $('#redirect-url-container').hide();
            }
            updateFormSettings();
        });

        // Redirect settings change handlers
        $('#thank-you-message, #redirect-url').on('change input', function() {
            updateFormSettings();
        });

        // Message settings change handlers
        $('#success-message, #error-message, #submit-button-text, #required-field-message').on('change input', function() {
            updateFormSettings();
        });

        // Initialize redirect containers based on current value
        var currentRedirectType = $('#redirect-type').val();
        if (currentRedirectType === 'redirect') {
            $('#thank-you-message-container').hide();
            $('#redirect-url-container').show();
        } else {
            $('#thank-you-message-container').show();
            $('#redirect-url-container').hide();
        }

        // Initialize integration panels
        if ($('#enable-mailchimp').is(':checked')) {
            $('#mailchimp-settings').show();
        }
        if ($('#enable-hubspot').is(':checked')) {
            $('#hubspot-settings').show();
        }

        // Update field mappings when fields change
        function updateFieldMappings() {
            const emailFields = [];
            const textFields = [];
            const phoneFields = [];

            $('.form-field').each(function() {
                const fieldType = $(this).data('field-type');
                const fieldId = $(this).data('field-id');
                const fieldLabel = $(this).find('.field-label').text();

                if (fieldType === 'email') {
                    emailFields.push({ id: fieldId, label: fieldLabel });
                } else if (fieldType === 'text') {
                    textFields.push({ id: fieldId, label: fieldLabel });
                } else if (fieldType === 'text' || fieldType === 'number') {
                    phoneFields.push({ id: fieldId, label: fieldLabel });
                }
            });

            // Update Mailchimp field mappings
            updateSelectOptions('#mailchimp-email-field', emailFields);
            updateSelectOptions('#mailchimp-name-field', textFields);

            // Update HubSpot field mappings
            updateSelectOptions('#hubspot-email-field', emailFields);
            updateSelectOptions('#hubspot-firstname-field', textFields);
            updateSelectOptions('#hubspot-lastname-field', textFields);
            updateSelectOptions('#hubspot-phone-field', phoneFields);
            updateSelectOptions('#hubspot-company-field', textFields);
        }

        function updateSelectOptions(selector, fields) {
            const $select = $(selector);
            const currentValue = $select.val();
            
            $select.find('option:not(:first)').remove();
            
            fields.forEach(field => {
                $select.append(`<option value="${field.id}">${field.label}</option>`);
            });
            
            if (currentValue && fields.some(f => f.id === currentValue)) {
                $select.val(currentValue);
            }
        }

        // Call updateFieldMappings when fields are added/removed
        $(document).on('fieldAdded fieldRemoved', function() {
            setTimeout(updateFieldMappings, 100);
        });

        // Initial field mapping update
        updateFieldMappings();
    }

    /**
     * Initialize sortable fields
     */
    function initSortableFields() {
        $('#form-fields').sortable({
            handle: '.field-header',
            placeholder: 'field-placeholder',
            start: function(e, ui) {
                ui.placeholder.height(ui.item.height());
                ui.placeholder.addClass('field-placeholder');
            },
            stop: function(e, ui) {
                // Update field order in the preview
                updateFieldOrder();
            }
        });
    }

    /**
     * Update field order in the preview
     */
    function updateFieldOrder() {
        var $fields = $('#form-fields .form-field');
        $fields.each(function(index) {
            $(this).attr('data-field-order', index);
        });
    }

    /**
     * Add field to form
     */
    function addField(type) {
        var fieldId = 'field_' + Date.now();
        var isCaptcha = type === 'captcha';
        var $field = $('<div class="form-field" data-field-type="' + type + '" data-field-id="' + fieldId + '"></div>');
        
        // Add field header with label and actions
        var $header = $('<div class="field-header"></div>');
        var $label = $('<span class="field-label"></span>');
        var $actions = $('<div class="field-actions"></div>');
        
        // Add drag handle
        $header.prepend('<span class="dashicons dashicons-menu field-drag-handle"></span>');
        
        // Add edit and delete buttons with dashicons
        $actions.append('<button type="button" class="edit-field" title="Edit Field"><span class="dashicons dashicons-edit"></span></button>');
        $actions.append('<button type="button" class="delete-field" title="Delete Field"><span class="dashicons dashicons-trash"></span></button>');
        
        $header.append($label);
        $header.append($actions);
        $field.append($header);
        
        // Add field preview
        var $preview = $('<div class="field-preview"></div>');
        $field.append($preview);
        
        // Add to form
        $('#form-fields').append($field);
        
        // Select the field
        $('.form-field').removeClass('selected');
        $field.addClass('selected');
        
        // Show field settings
        showFieldSettings($field);
        
        // Initialize field preview with empty data
        var data = {
            type: type,
            id: fieldId,
            label: '',
            description: '',
            required: false,
            placeholder: '',
            maxlength: '',
            css_class: '',
            size: 'medium',
            default_value: ''
        };
        
        // Store field data in the field element
        $field.data('field-data', data);
        
        updateFieldPreview($field, data);

        // Update field order
        updateFieldOrder();
    }

    /**
     * Initialize field actions
     */
    function initFieldActions() {
        // Edit field
        $(document).on('click', '.field-actions .edit-field', function(e) {
            e.stopPropagation();
            var $field = $(this).closest('.form-field');
            $('.form-field').removeClass('selected');
            $field.addClass('selected');
            showFieldSettings($field);
        });

        // Delete field
        $(document).on('click', '.field-actions .delete-field', function(e) {
            e.stopPropagation();
            if (confirm('Are you sure you want to delete this field?')) {
                $(this).closest('.form-field').remove();
                $('#field-settings').hide();
            }
        });
    }

    /**
     * Show field settings
     */
    function showFieldSettings($field) {
        // Get basic field data from the field element
        var fieldType = $field.data('field-type');
        var fieldId = $field.data('field-id');
        var fieldData = $field.data('field-data') || {};
        
        // If fieldData is a string (JSON), parse it
        if (typeof fieldData === 'string') {
            try {
                fieldData = JSON.parse(fieldData);
            } catch (e) {
                fieldData = {};
            }
        }
        
        $('#field-settings').show();
        $('.no-field-selected').hide();
        
        // Update settings panel
        $('#field-type').val(fieldType);
        $('#field-id').val(fieldId);
        $('#field-label').val(fieldData.label || '');
        $('#field-description').val(fieldData.description || '');
        $('#field-required').prop('checked', fieldData.required || false);
        $('#field-placeholder').val(fieldData.placeholder || '');
        $('#field-maxlength').val(fieldData.maxlength || '');
        $('#field-css-class').val(fieldData.css_class || '');
        $('#field-size').val(fieldData.size || 'medium');
        $('#field-default-value').val(fieldData.default_value || '');
        
        // Show/hide type-specific settings
        $('.type-specific').hide();
        if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox' || fieldType === 'multiselect') {
            $('.' + fieldType + '-settings').show();
            var options = fieldData.options || [];
            var $optionsList = $('#field-options');
            $optionsList.empty();
            options.forEach(function(option) {
                $optionsList.append('<div class="option-item"><input type="text" value="' + option + '"><button type="button" class="remove-option">Remove</button></div>');
            });
        } else if (fieldType === 'submit') {
            $('.submit-settings').show();
            $('#field-button-type').val(fieldData.button_type || 'submit');
        } else if (fieldType === 'captcha') {
            $('.captcha-settings').show();
            $('#field-captcha-type').val(fieldData.captcha_type || 'v2_checkbox');
            $('#field-captcha-theme').val(fieldData.captcha_theme || 'light');
            $('#field-captcha-size').val(fieldData.captcha_size || 'normal');
            // Allow CAPTCHA to be optional
            $('#field-required').prop('disabled', false);
        } else {
            // Enable required checkbox for other fields
            $('#field-required').prop('disabled', false);
        }
    }

    /**
     * Get field data
     */
    function getFieldData($field) {
        // Get the stored field data from the field element
        var data = $field.data('field-data') || {};
        
        // Ensure we have the basic field properties
        data.type = data.type || $field.data('field-type');
        data.id = data.id || $field.data('field-id');
        
        // Get current values from the settings panel if the field is selected
        if ($field.hasClass('selected')) {
            data.label = $('#field-label').val() || data.label || '';
            data.description = $('#field-description').val() || data.description || '';
            data.required = $('#field-required').is(':checked');
            data.placeholder = $('#field-placeholder').val() || data.placeholder || '';
            data.maxlength = $('#field-maxlength').val() || data.maxlength || '';
            data.css_class = $('#field-css-class').val() || data.css_class || '';
            data.size = $('#field-size').val() || data.size || 'medium';
            data.default_value = $('#field-default-value').val() || data.default_value || '';
            
            // Get type-specific data from settings panel
            if (data.type === 'select' || data.type === 'radio' || data.type === 'checkbox' || data.type === 'multiselect') {
                data.options = [];
                $('#field-options .option-item input').each(function() {
                    data.options.push($(this).val());
                });
            } else if (data.type === 'submit') {
                data.button_type = $('#field-button-type').val() || data.button_type || 'submit';
            } else if (data.type === 'captcha') {
                data.captcha_type = $('#field-captcha-type').val() || data.captcha_type || 'v2_checkbox';
                data.captcha_theme = $('#field-captcha-theme').val() || data.captcha_theme || 'light';
                data.captcha_size = $('#field-captcha-size').val() || data.captcha_size || 'normal';
            }
        }
        
        return data;
    }

    /**
     * Update field preview
     */
    function updateFieldPreview($field, data) {
        var $preview = $field.find('.field-preview');
        $preview.empty();

        var type = data.type;
        var label = data.label || '';
        var required = data.required ? '<span class="required">*</span>' : '';
        var description = data.description ? '<div class="field-description">' + data.description + '</div>' : '';

        // Add label if it exists
        if (label) {
            $preview.append('<label class="field-label">' + label + required + '</label>');
        }

        switch (type) {
            case 'text':
                var $input = $('<input type="text" class="' + data.css_class + '" placeholder="' + (data.placeholder || '') + '">');
                if (data.maxlength) {
                    $input.attr('maxlength', data.maxlength);
                }
                $preview.append($input);
                break;

            case 'textarea':
                var $textarea = $('<textarea class="' + data.css_class + '" placeholder="' + (data.placeholder || '') + '"></textarea>');
                if (data.maxlength) {
                    $textarea.attr('maxlength', data.maxlength);
                }
                $preview.append($textarea);
                break;

            case 'email':
                var $input = $('<input type="email" class="' + data.css_class + '" placeholder="' + (data.placeholder || '') + '">');
                if (data.maxlength) {
                    $input.attr('maxlength', data.maxlength);
                }
                $preview.append($input);
                break;

            case 'number':
                var $input = $('<input type="number" class="' + data.css_class + '" placeholder="' + (data.placeholder || '') + '">');
                if (data.min !== undefined) {
                    $input.attr('min', data.min);
                }
                if (data.max !== undefined) {
                    $input.attr('max', data.max);
                }
                $preview.append($input);
                break;

            case 'date':
                $preview.append('<input type="date" class="' + data.css_class + '">');
                break;

            case 'select':
                var $select = $('<select class="' + data.css_class + '"></select>');
                if (data.options) {
                    data.options.forEach(function(option) {
                        $select.append('<option value="' + option.value + '">' + option.label + '</option>');
                    });
                }
                $preview.append($select);
                break;

            case 'radio':
                if (data.options) {
                    var $container = $('<div class="radio-options"></div>');
                    data.options.forEach(function(option) {
                        var $option = $(
                            '<label><input type="radio" name="radio_' + data.id + '" value="' + option.value + '"> ' +
                            option.label + '</label>'
                        );
                        $container.append($option);
                    });
                    $preview.append($container);
                }
                break;

            case 'checkbox':
                if (data.options) {
                    var $container = $('<div class="checkbox-options"></div>');
                    data.options.forEach(function(option) {
                        var $option = $(
                            '<label><input type="checkbox" name="checkbox_' + data.id + '[]" value="' + option.value + '"> ' +
                            option.label + '</label>'
                        );
                        $container.append($option);
                    });
                    $preview.append($container);
                }
                break;

            case 'file':
                $preview.append('<input type="file" class="' + data.css_class + '">');
                break;

            case 'captcha':
                var captchaType = data.captcha_type || 'v2_checkbox';
                var captchaTheme = data.captcha_theme || 'light';
                var captchaSize = data.captcha_size || 'normal';
                
                var $captchaPreview = $('<div class="recaptcha-preview"></div>');
                
                // Show different preview based on CAPTCHA type
                switch (captchaType) {
                    case 'v2_checkbox':
                        $captchaPreview.append(
                            '<div class="g-recaptcha-preview checkbox">' +
                            '<div class="recaptcha-box ' + captchaTheme + '">' +
                            '<div class="recaptcha-checkbox"></div>' +
                            '<div class="recaptcha-text">I\'m not a robot</div>' +
                            '</div>' +
                            '</div>'
                        );
                        break;
                    case 'v2_invisible':
                        $captchaPreview.append(
                            '<div class="g-recaptcha-preview invisible">' +
                            '<div class="recaptcha-badge ' + captchaTheme + '">Protected by reCAPTCHA</div>' +
                            '</div>'
                        );
                        break;
                    case 'v3':
                        $captchaPreview.append(
                            '<div class="g-recaptcha-preview v3">' +
                            '<div class="recaptcha-badge ' + captchaTheme + '">Protected by reCAPTCHA v3</div>' +
                            '</div>'
                        );
                        break;
                }
                
                $preview.append($captchaPreview);
                break;

            case 'submit':
                var buttonType = data.button_type || 'submit';
                var $button = $('<button type="' + buttonType + '" class="' + data.css_class + '">' + (data.label || 'Submit') + '</button>');
                $preview.append($button);
                break;
        }

        // Add description if it exists
        if (description) {
            $preview.append(description);
        }
    }

    /**
     * Initialize field settings
     */
    function initFieldSettings() {
        // Handle settings changes
        $('#field-settings input, #field-settings select, #field-settings textarea').on('change', function() {
            var $field = $('.form-field.selected');
            if ($field.length) {
                var data = {
                    type: $('#field-type').val(),
                    id: $('#field-id').val(),
                    label: $('#field-label').val(),
                    description: $('#field-description').val(),
                    required: $('#field-required').is(':checked'),
                    placeholder: $('#field-placeholder').val(),
                    maxlength: $('#field-maxlength').val(),
                    css_class: $('#field-css-class').val(),
                    size: $('#field-size').val(),
                    default_value: $('#field-default-value').val()
                };

                // Get type-specific data
                if (data.type === 'select' || data.type === 'radio' || data.type === 'checkbox' || data.type === 'multiselect') {
                    data.options = [];
                    $('#field-options .option-item input').each(function() {
                        data.options.push($(this).val());
                    });
                } else if (data.type === 'submit') {
                    data.button_type = $('#field-button-type').val() || 'submit';
                } else if (data.type === 'captcha') {
                    data.captcha_type = $('#field-captcha-type').val() || 'v2_checkbox';
                    data.captcha_theme = $('#field-captcha-theme').val() || 'light';
                    data.captcha_size = $('#field-captcha-size').val() || 'normal';
                }

                // Store updated data back to the field element
                $field.data('field-data', data);
                
                updateFieldPreview($field, data);
            }
        });

        // Add option
        $('#add-option').on('click', function() {
            var $optionsList = $('#field-options');
            $optionsList.append('<div class="option-item"><input type="text" value=""><button type="button" class="remove-option">Remove</button></div>');
        });

        // Remove option
        $(document).on('click', '.remove-option', function() {
            $(this).closest('.option-item').remove();
        });
    }

    /**
     * Ensure email notifications have proper content
     */
    function ensureEmailNotificationContent() {
        emailNotifications.forEach(function(notif, index) {
            if (notif.enabled) {
                // Set default subject if empty
                if (!notif.subject || notif.subject.trim() === '') {
                    notif.subject = 'New form submission from {site_name}';
                }
                
                // Set default message if empty
                if (!notif.message || notif.message.trim() === '') {
                    notif.message = generateDefaultEmailTemplate();
                }
                
                // Set default from if empty
                if (!notif.from || notif.from.trim() === '') {
                    notif.from = getAdminEmail();
                }
            }
        });
    }

    /**
     * Save form
     */
    function saveForm() {
        var formId = mavlersCF.currentFormId || 0;
        var title = $('#form-title').val();
        
        if (!title) {
            showNotification('Please enter a form title', 'error');
            return;
        }

        // Get all fields data
        var fields = [];
        $('.form-field').each(function() {
            var $field = $(this);
            var fieldData = getFieldData($field);
            fields.push(fieldData);
        });

        // Get form settings (add email notifications and new settings)
        var settings = {
            email_notifications: emailNotifications,
            // Messages settings
            success_message: $('#success-message').val() || 'Thank you! Your form has been submitted successfully.',
            error_message: $('#error-message').val() || 'Please fix the errors below and try again.',
            submit_text: $('#submit-button-text').val() || 'Submit',
            required_field_message: $('#required-field-message').val() || 'This field is required.',
            redirect_type: $('#redirect-type').val() || 'message',
            thank_you_message: $('#thank-you-message').val() || 'Thank you! Your form has been submitted successfully. We will get back to you soon.',
            redirect_url: $('#redirect-url').val(),
            // Integration settings
            enable_ajax: $('#enable-ajax').is(':checked'),
            scroll_to_error: $('#enable-scroll-to-error').is(':checked'),
            enable_honeypot: $('#enable-honeypot').is(':checked'),
            // Mailchimp integration
            enable_mailchimp: $('#enable-mailchimp').is(':checked'),
            mailchimp_api_key: $('#mailchimp-api-key').val(),
            mailchimp_list_id: $('#mailchimp-list-id').val(),
            mailchimp_email_field: $('#mailchimp-email-field').val(),
            mailchimp_name_field: $('#mailchimp-name-field').val(),
            mailchimp_double_optin: $('#mailchimp-double-optin').is(':checked'),
            // HubSpot integration
            enable_hubspot: $('#enable-hubspot').is(':checked'),
            hubspot_api_key: $('#hubspot-api-key').val(),
            hubspot_portal_id: $('#hubspot-portal-id').val(),
            hubspot_email_field: $('#hubspot-email-field').val(),
            hubspot_firstname_field: $('#hubspot-firstname-field').val(),
            hubspot_lastname_field: $('#hubspot-lastname-field').val(),
            hubspot_phone_field: $('#hubspot-phone-field').val(),
            hubspot_company_field: $('#hubspot-company-field').val()
        };

        // Debug: Log the Mailchimp API key being saved
        console.log('🔧 DEBUG: Saving form with Mailchimp API key:', settings.mailchimp_api_key);
        console.log('🔧 DEBUG: Full settings object:', settings);
        
        // Debug: Double-check the API key field value directly
        var directApiKeyValue = document.getElementById('mailchimp-api-key').value;
        console.log('🔧 DEBUG: Direct API key field value:', directApiKeyValue);
        console.log('🔧 DEBUG: Form ID being saved:', formId);
        
        // Debug: Check if values match
        if (settings.mailchimp_api_key !== directApiKeyValue) {
            console.error('❌ MISMATCH: Settings object has different value than form field!');
        }

        // Ensure email notifications have proper content
        ensureEmailNotificationContent();

        // Send AJAX request
        $.ajax({
            url: mavlersCF.ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_save_form',
                nonce: mavlersCF.nonce,
                form_id: formId,
                title: title,
                fields: JSON.stringify(fields),
                settings: JSON.stringify(settings)
            },
            beforeSend: function() {
                $('#save-form').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showNotification(mavlersCF.i18n.saveSuccess, 'success');
                    
                    // Update shortcode and PHP code with actual form ID
                    if (response.data && response.data.form_id) {
                        updateIntegrationCodes(response.data.form_id);
                    }
                    
                    // Redirect to forms list if this is a new form
                    if (!formId) {
                        setTimeout(function() {
                            window.location.href = mavlersCF.formsListUrl;
                        }, 1500);
                    }
                } else {
                    showNotification(response.data || mavlersCF.i18n.saveError, 'error');
                }
            },
            error: function() {
                showNotification(mavlersCF.i18n.saveError, 'error');
            },
            complete: function() {
                $('#save-form').prop('disabled', false).text('Save Form');
            }
        });
    }

    /**
     * Update integration codes with actual form ID
     */
    function updateIntegrationCodes(formId) {
        $('#form-shortcode').text('[mavlers_contact_form id="' + formId + '"]');
        $('#form-php-code').text('<?php echo do_shortcode(\'[mavlers_contact_form id="' + formId + '"]\'); ?>');
    }

    /**
     * Initialize copy buttons
     */
    function initCopyButtons() {
        $('.copy-shortcode, .copy-php-code').on('click', function() {
            var target = $(this).data('clipboard-target');
            var text = $(target).text();
            
            // Create temporary textarea to copy text
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            // Show feedback
            var $button = $(this);
            var originalText = $button.html();
            $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
            setTimeout(function() {
                $button.html(originalText);
            }, 2000);
        });
    }

    /**
     * Show notification message
     */
    function showNotification(message, type) {
        var $notification = $('#form-notification');
        $notification
            .removeClass('success error')
            .addClass(type)
            .html(message)
            .slideDown();

        // Auto hide after 3 seconds
        setTimeout(function() {
            $notification.slideUp();
        }, 3000);
    }

    // Initialize field settings
    initFieldSettings();

    // Initialize default message values if fields are empty
    initializeDefaultMessages();

    // Add CSS for CAPTCHA preview
    var style = document.createElement('style');
    style.textContent = `
        .recaptcha-preview {
            margin: 10px 0;
        }
        .g-recaptcha-preview {
            border: 1px solid #d3d3d3;
            border-radius: 3px;
            padding: 10px;
            display: inline-block;
        }
        .recaptcha-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px;
        }
        .recaptcha-checkbox {
            width: 24px;
            height: 24px;
            border: 2px solid #c1c1c1;
            border-radius: 2px;
        }
        .recaptcha-text {
            color: #000;
            font-size: 14px;
        }
        .recaptcha-badge {
            padding: 5px 10px;
            font-size: 12px;
            color: #555;
            text-align: center;
        }
        .g-recaptcha-preview.dark {
            background: #f9f9f9;
            border-color: #555;
        }
        .g-recaptcha-preview.dark .recaptcha-text {
            color: #fff;
        }
    `;

    // Email Notifications Tab Logic
    var emailNotifications = [];

    // Initialize with a default email notification if none exist
    function initializeDefaultEmailNotification() {
        if (emailNotifications.length === 0) {
            var defaultNotification = {
                enabled: true,
                name: 'Admin Notification',
                to: getAdminEmail(),
                from: getAdminEmail(),
                subject: 'New form submission from {site_name}',
                message: generateDefaultEmailTemplate(),
                bcc: '',
                cc: '',
                reply_to: '',
                attachments: false
            };
            emailNotifications.push(defaultNotification);
            renderEmailNotificationsList();
        }
    }

    // Call initialization when form builder loads
    initializeDefaultEmailNotification();

    function renderEmailNotificationsList() {
        var $list = $('#email-notifications-list');
        $list.empty();
        if (emailNotifications.length === 0) {
            $list.append('<p>No notifications configured.</p>');
        } else {
            emailNotifications.forEach(function(notif, idx) {
                $list.append('<div class="email-notification-item">'
                    + '<strong>' + notif.name + '</strong> '
                    + (notif.enabled ? '<span class="enabled">(Enabled)</span>' : '<span class="disabled">(Disabled)</span>')
                    + ' <button type="button" class="button edit-email-notification" data-idx="' + idx + '">Edit</button>'
                    + ' <button type="button" class="button delete-email-notification" data-idx="' + idx + '">Delete</button>'
                    + '</div>');
            });
        }
    }

    function showEmailNotificationEditor(idx) {
        var notif = idx !== undefined ? emailNotifications[idx] : {
            enabled: true,
            name: '',
            to: '',
            from: '',
            subject: '',
            message: '',
            bcc: '',
            cc: '',
            reply_to: '',
            attachments: false
        };
        var html = '<div class="email-notification-editor-form">'
            + '<label><input type="checkbox" id="notif-enabled" ' + (notif.enabled ? 'checked' : '') + '> Enable</label>'
            + '<label>Name: <input type="text" id="notif-name" value="' + (notif.name || '') + '"></label>'
            + '<div class="row">'
            + '<div><label>To: <input type="text" id="notif-to" value="' + (notif.to || '') + '"></label><div class="help">Comma-separated. Use merge tags like {Email}.</div></div>'
            + '<div><label>From: <input type="text" id="notif-from" value="' + (notif.from || '') + '"></label><div class="help">E.g. admin@yourdomain.com</div></div>'
            + '</div>'
            + '<div class="row">'
            + '<div><label>BCC: <input type="text" id="notif-bcc" value="' + (notif.bcc || '') + '"></label></div>'
            + '<div><label>CC: <input type="text" id="notif-cc" value="' + (notif.cc || '') + '"></label></div>'
            + '<div><label>Reply-To: <input type="text" id="notif-reply-to" value="' + (notif.reply_to || '') + '"></label></div>'
            + '</div>'
            + '<div class="email-actions">'
            + '<button type="button" class="button" id="insert-admin-email">Insert Admin Email</button>'
            + '<div class="help">Quickly add the site admin email address.</div>'
            + '</div>'
            + '<label>Subject: <input type="text" id="notif-subject" value="' + (notif.subject || '') + '"></label><div class="help">You can use merge tags like {Text} or {Email}.</div>'
            + '<label>Message:<br><textarea id="notif-message">' + (notif.message || '') + '</textarea></label>'
            + '<div class="message-actions">'
            + '<button type="button" class="button" id="insert-default-template">Insert Default Template</button>'
            + '<button type="button" class="button" id="insert-default-subject">Insert Default Subject</button>'
            + '<div class="help">These buttons will add default templates with all form fields automatically.</div>'
            + '</div>'
            + '<label><input type="checkbox" id="notif-attachments" ' + (notif.attachments ? 'checked' : '') + '> Attach uploaded files</label>'
            + '<button type="button" class="button button-primary" id="save-email-notification">Save</button>'
            + '<button type="button" class="button" id="cancel-email-notification">Cancel</button>'
            + '</div>';
        $('#email-notification-editor').html(html).show();
        $('#email-notifications-list').hide();
        $('#add-email-notification').hide();

        // Add event handler for insert default template button
        $('#insert-default-template').off('click').on('click', function() {
            var template = generateDefaultEmailTemplate();
            $('#notif-message').val(template);
        });

        // Add event handler for insert default subject button
        $('#insert-default-subject').off('click').on('click', function() {
            var subject = generateDefaultSubjectTemplate();
            $('#notif-subject').val(subject);
        });

        // Add event handler for insert admin email button
        $('#insert-admin-email').off('click').on('click', function() {
            var adminEmail = getAdminEmail();
            var currentTo = $('#notif-to').val();
            if (currentTo) {
                $('#notif-to').val(currentTo + ', ' + adminEmail);
            } else {
                $('#notif-to').val(adminEmail);
            }
        });

        $('#save-email-notification').off('click').on('click', function() {
            var newNotif = {
                enabled: $('#notif-enabled').is(':checked'),
                name: $('#notif-name').val(),
                to: $('#notif-to').val(),
                from: $('#notif-from').val(),
                subject: $('#notif-subject').val(),
                message: $('#notif-message').val(),
                bcc: $('#notif-bcc').val(),
                cc: $('#notif-cc').val(),
                reply_to: $('#notif-reply-to').val(),
                attachments: $('#notif-attachments').is(':checked')
            };
            if (idx !== undefined) {
                emailNotifications[idx] = newNotif;
            } else {
                emailNotifications.push(newNotif);
            }
            $('#email-notification-editor').hide();
            $('#email-notifications-list').show();
            $('#add-email-notification').show();
            renderEmailNotificationsList();
        });
        $('#cancel-email-notification').off('click').on('click', function() {
            $('#email-notification-editor').hide();
            $('#email-notifications-list').show();
            $('#add-email-notification').show();
        });
    }

    /**
     * Generate default email template with all form fields
     */
    function generateDefaultEmailTemplate() {
        var template = '<h2>New Form Submission</h2>\n\n';
        var fields = [];
        
        // Get all form fields
        $('.form-field').each(function() {
            var $field = $(this);
            var fieldData = $field.data('field-data');
            
            if (fieldData && fieldData.label) {
                var fieldId = fieldData.id;
                var fieldLabel = fieldData.label;
                var fieldType = fieldData.type;
                
                // Skip submit buttons and other non-input fields
                if (fieldType !== 'submit' && fieldType !== 'html' && fieldType !== 'captcha') {
                    fields.push({
                        id: fieldId,
                        label: fieldLabel,
                        type: fieldType
                    });
                }
            }
        });
        
        // Create HTML table
        template += '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">\n';
        template += '<tr style="background-color: #f8f9fa;"><th style="text-align: left; padding: 12px; border: 1px solid #ddd; font-weight: bold;">Field</th><th style="text-align: left; padding: 12px; border: 1px solid #ddd; font-weight: bold;">Value</th></tr>\n';
        
        // Add each field to the template
        fields.forEach(function(field) {
            template += '<tr><td style="padding: 12px; border: 1px solid #ddd; font-weight: bold;">' + field.label + '</td><td style="padding: 12px; border: 1px solid #ddd;">{' + field.id + '}</td></tr>\n';
        });
        
        template += '</table>\n\n';
        
        // Add footer
        template += '<hr>\n';
        template += '<p style="color: #666; font-size: 12px;">This email was sent from your contact form at {site_name} ({site_url})</p>\n';
        template += '<p style="color: #666; font-size: 12px;">Submitted on: {submission_date}</p>\n';
        template += '<p style="color: #666; font-size: 12px;">IP Address: {ip_address}</p>';
        
        return template;
    }

    /**
     * Generate default subject template
     */
    function generateDefaultSubjectTemplate() {
        var subject = 'New form submission from {site_name}';
        
        // Try to find an email field to include in the subject
        $('.form-field').each(function() {
            var $field = $(this);
            var fieldData = $field.data('field-data');
            
            if (fieldData && fieldData.type === 'email' && fieldData.label) {
                subject = 'New form submission from {' + fieldData.id + '}';
                return false; // Break the loop
            }
        });
        
        return subject;
    }

    /**
     * Initialize default message values if fields are empty
     */
    function initializeDefaultMessages() {
        // Set default success message if empty
        if (!$('#success-message').val()) {
            $('#success-message').val('Thank you! Your form has been submitted successfully.');
        }
        
        // Set default error message if empty
        if (!$('#error-message').val()) {
            $('#error-message').val('Please fix the errors below and try again.');
        }
        
        // Set default submit button text if empty
        if (!$('#submit-button-text').val()) {
            $('#submit-button-text').val('Submit');
        }
        
        // Set default required field message if empty
        if (!$('#required-field-message').val()) {
            $('#required-field-message').val('This field is required.');
        }
        
        // Set default thank you message if empty
        if (!$('#thank-you-message').val()) {
            $('#thank-you-message').val('Thank you! Your form has been submitted successfully. We will get back to you soon.');
        }
    }

    /**
     * Get admin email address
     */
    function getAdminEmail() {
        return mavlersCF.adminEmail || 'admin@' + window.location.hostname;
    }

    $(document).on('click', '#add-email-notification', function() {
        showEmailNotificationEditor();
    });
    $(document).on('click', '.edit-email-notification', function() {
        var idx = $(this).data('idx');
        showEmailNotificationEditor(idx);
    });
    $(document).on('click', '.delete-email-notification', function() {
        var idx = $(this).data('idx');
        if (confirm('Delete this notification?')) {
            emailNotifications.splice(idx, 1);
            renderEmailNotificationsList();
        }
    });

    // Always render the notifications list on page load
    $(document).ready(function(){
        if (typeof emailNotifications === 'undefined' || !Array.isArray(emailNotifications)) {
            emailNotifications = [];
        }
        
        // Load form data if editing an existing form
        if (mavlersCF.formData && mavlersCF.formData.settings && mavlersCF.formData.settings.email_notifications) {
            emailNotifications = mavlersCF.formData.settings.email_notifications;
        }
        
        renderEmailNotificationsList();
        
        // Initialize copy buttons
        initCopyButtons();

        // Global debug function for testing API key issues
        window.debugMailchimpApiKey = function() {
            console.log('🔍 === MAILCHIMP API KEY DEBUG ===');
            console.log('📋 Current form ID:', mavlersCF.currentFormId);
            console.log('📋 API key field value:', $('#mailchimp-api-key').val());
            console.log('📋 API key field exists:', $('#mailchimp-api-key').length > 0);
            console.log('📋 Enable checkbox:', $('#enable-mailchimp').is(':checked'));
            console.log('📋 List ID field:', $('#mailchimp-list-id').val());
            
            // Test the settings collection
            var testSettings = {
                enable_mailchimp: $('#enable-mailchimp').is(':checked'),
                mailchimp_api_key: $('#mailchimp-api-key').val(),
                mailchimp_list_id: $('#mailchimp-list-id').val()
            };
            console.log('📋 Test settings object:', testSettings);
            
            // Try to manually call the database check
            if (mavlersCF.currentFormId) {
                $.post(mavlersCF.ajaxurl, {
                    action: 'mavlers_cf_debug_form_settings',
                    form_id: mavlersCF.currentFormId,
                    nonce: mavlersCF.nonce
                }, function(response) {
                    console.log('📋 Database contains:', response);
                }).fail(function(xhr, status, error) {
                    console.error('❌ Database check failed:', status, error);
                });
            }
            
            console.log('🔍 === END DEBUG ===');
        };
        
        console.log('🔧 Debug function loaded. Type debugMailchimpApiKey() in console to test.');
    });

    // When loading a form for editing, load emailNotifications from settings
    function loadForm(form) {
        if (form.settings && form.settings.email_notifications) {
            emailNotifications = form.settings.email_notifications;
        } else {
            emailNotifications = [];
        }
        renderEmailNotificationsList();
    }

    function updateFormSettings() {
        formSettings = {
            // Messages
            success_message: $('#success-message').val(),
            error_message: $('#error-message').val(),
            submit_button_text: $('#submit-button-text').val(),
            required_field_message: $('#required-field-message').val(),
            redirect_type: $('#redirect-type').val(),
            thank_you_message: $('#thank-you-message').val(),
            redirect_url: $('#redirect-url').val(),
            
            // Email notifications
            enable_notifications: $('#enable-notifications').is(':checked'),
            notification_emails: $('#notification-emails').val(),
            notification_subject: $('#notification-subject').val(),
            
            // Form behavior
            enable_ajax: $('#enable-ajax').is(':checked'),
            scroll_to_error: $('#enable-scroll-to-error').is(':checked'),
            enable_honeypot: $('#enable-honeypot').is(':checked'),
            
            // Mailchimp integration
            enable_mailchimp: $('#enable-mailchimp').is(':checked'),
            mailchimp_api_key: $('#mailchimp-api-key').val(),
            mailchimp_list_id: $('#mailchimp-list-id').val(),
            mailchimp_email_field: $('#mailchimp-email-field').val(),
            mailchimp_name_field: $('#mailchimp-name-field').val(),
            mailchimp_double_optin: $('#mailchimp-double-optin').is(':checked'),
            
            // HubSpot integration
            enable_hubspot: $('#enable-hubspot').is(':checked'),
            hubspot_api_key: $('#hubspot-api-key').val(),
            hubspot_portal_id: $('#hubspot-portal-id').val(),
            hubspot_email_field: $('#hubspot-email-field').val(),
            hubspot_firstname_field: $('#hubspot-firstname-field').val(),
            hubspot_lastname_field: $('#hubspot-lastname-field').val(),
            hubspot_phone_field: $('#hubspot-phone-field').val(),
            hubspot_company_field: $('#hubspot-company-field').val()
        };
    }

    /**
     * Preview form
     */
    function previewForm() {
        var formId = mavlersCF.currentFormId || 0;
        var title = $('#form-title').val();
        
        if (!title) {
            showNotification('Please enter a form title before previewing', 'warning');
            return;
        }
        
        // If form hasn't been saved yet, save it first
        if (!formId) {
            showNotification('Saving form before preview...', 'info');
            
            // Save form first, then preview
            saveFormAndPreview();
            return;
        }
        
        // Open preview in new window/tab
        var previewUrl = mavlersCF.ajaxurl + '?action=mavlers_cf_preview_form&form_id=' + formId + '&nonce=' + mavlersCF.nonce;
        window.open(previewUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    }

    /**
     * Save form and then preview
     */
    function saveFormAndPreview() {
        var title = $('#form-title').val();
        
        // Get all fields data
        var fields = [];
        $('.form-field').each(function() {
            var $field = $(this);
            var fieldData = getFieldData($field);
            fields.push(fieldData);
        });

        // Get form settings
        var settings = {
            email_notifications: emailNotifications,
            success_message: $('#success-message').val() || 'Thank you! Your form has been submitted successfully.',
            error_message: $('#error-message').val() || 'Please fix the errors below and try again.',
            submit_text: $('#submit-button-text').val() || 'Submit',
            required_message: $('#required-field-message').val() || 'This field is required.',
            redirect_type: $('#redirect-type').val() || 'message',
            thank_you_message: $('#thank-you-message').val() || 'Thank you! Your form has been submitted successfully. We will get back to you soon.',
            redirect_url: $('#redirect-url').val(),
            enable_ajax: $('#enable-ajax').is(':checked'),
            scroll_to_error: $('#enable-scroll-to-error').is(':checked'),
            enable_honeypot: $('#enable-honeypot').is(':checked'),
            enable_mailchimp: $('#enable-mailchimp').is(':checked'),
            mailchimp_api_key: $('#mailchimp-api-key').val(),
            mailchimp_list_id: $('#mailchimp-list-id').val(),
            mailchimp_email_field: $('#mailchimp-email-field').val(),
            mailchimp_name_field: $('#mailchimp-name-field').val(),
            mailchimp_double_optin: $('#mailchimp-double-optin').is(':checked'),
            enable_hubspot: $('#enable-hubspot').is(':checked'),
            hubspot_api_key: $('#hubspot-api-key').val(),
            hubspot_portal_id: $('#hubspot-portal-id').val(),
            hubspot_email_field: $('#hubspot-email-field').val(),
            hubspot_firstname_field: $('#hubspot-firstname-field').val(),
            hubspot_lastname_field: $('#hubspot-lastname-field').val(),
            hubspot_phone_field: $('#hubspot-phone-field').val(),
            hubspot_company_field: $('#hubspot-company-field').val()
        };

        // Ensure email notifications have proper content
        ensureEmailNotificationContent();

        // Send AJAX request
        $.ajax({
            url: mavlersCF.ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_save_form',
                nonce: mavlersCF.nonce,
                form_id: 0,
                title: title,
                fields: JSON.stringify(fields),
                settings: JSON.stringify(settings)
            },
            beforeSend: function() {
                $('#preview-form').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    // Update form ID and open preview
                    mavlersCF.currentFormId = response.data.form_id;
                    $('#form-builder').attr('data-form-id', response.data.form_id);
                    
                    // Open preview
                    var previewUrl = mavlersCF.ajaxurl + '?action=mavlers_cf_preview_form&form_id=' + response.data.form_id + '&nonce=' + mavlersCF.nonce;
                    window.open(previewUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
                    
                    showNotification('Form saved and preview opened', 'success');
                } else {
                    showNotification(response.data || 'Failed to save form', 'error');
                }
            },
            error: function() {
                showNotification('Failed to save form', 'error');
            },
            complete: function() {
                $('#preview-form').prop('disabled', false).text('Preview Form');
            }
        });
    }
});