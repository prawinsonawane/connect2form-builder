jQuery(document).ready(function($) {
    
    
    // Set flag to indicate script is loaded
    window.connect2formFormBuilderLoaded = true;
    
    // Global function to initialize tab functionality
    function initTabFunctionality($) {
        // Ensure $ is available
        if (typeof $ === 'undefined') {
            $ = jQuery;
        }
        

        
        // Check if tab elements exist
        var $tabLinks = $('.tab-link');
        var $tabContents = $('.form-builder-tab-content');
        
        if ($tabLinks.length === 0 || $tabContents.length === 0) {
            return false;
        }
        
        // Add click handler using event delegation for reliability
        $(document).off('click.tabswitch', '.tab-link').on('click.tabswitch', '.tab-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $this = $(this);
            var tab = $this.data('tab');
            

            
            if (!tab) {
                console.error('No tab data attribute found on clicked element');
                return false;
            }
            
            try {
                // Remove active state from all tabs
                $('.tab-link').removeClass('active');
                $this.addClass('active');
                
                // Hide all tab content panels
                $('.form-builder-tab-content').removeClass('active').hide();
                
                // Show the target tab
                var $targetTab = $('.form-builder-tab-content.' + tab);
    
                
                if ($targetTab.length > 0) {
                    $targetTab.addClass('active').show();
    
                } else {
                    console.error('Target tab element not found:', '.' + tab);
                    // Try alternative selector
                    var $altTarget = $('.' + tab);
                    if ($altTarget.length > 0) {
                        $altTarget.addClass('active').show();
        
                    }
                }
            } catch (error) {
                console.error('Error in tab switching:', error);
            }
            
            return false;
        });
        
        // Initialize default tab state
        var $activeTab = $('.tab-link.active');
        if ($activeTab.length > 0) {
            var activeTabName = $activeTab.data('tab');
            if (activeTabName) {
                $('.form-builder-tab-content').hide().removeClass('active');
                $('.form-builder-tab-content.' + activeTabName).show().addClass('active');
            }
        } else {
            // Default to first tab if none is active
            var $firstTab = $('.tab-link').first();
            if ($firstTab.length > 0) {
                $firstTab.addClass('active');
                var firstTabName = $firstTab.data('tab');
                if (firstTabName) {
                    $('.form-builder-tab-content').hide().removeClass('active');
                    $('.form-builder-tab-content.' + firstTabName).show().addClass('active');
                }
            }
        }
        

        return true;
    }

    // Function to initialize form builder
    function initFormBuilder($) {
        // Add field buttons - unbind first to prevent duplicates
        $('.field-type').off('click.addField').on('click.addField', function() {
            var type = $(this).data('type');
            addField(type);
        });

        // Field selection
        $(document).on('click', '.form-field', function(e) {
            // Don't trigger field selection if clicking on form elements within the field
            if ($(e.target).closest('.field-settings-accordion, .field-actions, .option-item').length > 0) {
                return;
            }
            
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
        $('#error-message, #submit-button-text, #required-field-message, #thank-you-message').on('change input', function() {
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

    // Main initialization function
    function initializeConnect2Form() {
        var $ = jQuery;
        
        // Check if we're on a page with tabs before trying to initialize them
        var $tabLinks = $('.tab-link');
        var $tabContents = $('.form-builder-tab-content');
        
        // Only initialize tabs if tab elements are present
        if ($tabLinks.length > 0 && $tabContents.length > 0) {
            // Try to initialize tab functionality
            var tabInitSuccess = false;
            var attempts = 0;
            var maxAttempts = 10;
            
            function tryInitTabs() {
                attempts++;
                
                tabInitSuccess = initTabFunctionality($);
                
                if (!tabInitSuccess && attempts < maxAttempts) {
                    setTimeout(tryInitTabs, 100);
                } else if (attempts >= maxAttempts) {
                    console.error('Failed to initialize tabs after', maxAttempts, 'attempts');
                }
            }
            
            // Start trying to initialize tabs
            tryInitTabs();
        }
        
        // Initialize form builder
        initFormBuilder($);
        
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
        document.head.appendChild(style);

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
            return connect2formCF.adminEmail || 'admin@' + window.location.hostname;
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
        if (typeof emailNotifications === 'undefined' || !Array.isArray(emailNotifications)) {
            emailNotifications = [];
        }
        
        // Load form data if editing an existing form
        if (connect2formCF.formData && connect2formCF.formData.settings && connect2formCF.formData.settings.email_notifications) {
            emailNotifications = connect2formCF.formData.settings.email_notifications;
        }
        
        renderEmailNotificationsList();
        
        // Initialize copy buttons
        initCopyButtons();

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
                success_message: $('#thank-you-message').val(),
                error_message: $('#error-message').val(),
                submit_button_text: $('#submit-button-text').val(),
                required_field_message: $('#required-field-message').val(),
                redirect_type: $('#redirect-type').val(),
                thank_you_message: $('#thank-you-message').val(),
                redirect_url: $('#redirect-url').val(),
                
                // Email notifications
                email_notifications: emailNotifications,
                
                // Form behavior
                enable_ajax: $('#enable-ajax').is(':checked'),
                scroll_to_error: $('#enable-scroll-to-error').is(':checked'),
                enable_honeypot: $('#enable-honeypot').is(':checked'),
            };
        }

        // Initialize integration settings handlers
        function initIntegrationHandlers() {
            // Handle Mailchimp integration toggle
            $('#enable-mailchimp').on('change', function() {
                var $panel = $('#mailchimp-settings');
                if ($(this).is(':checked')) {
                    $panel.slideDown();
                } else {
                    $panel.slideUp();
                }
            });

            // Handle HubSpot integration toggle  
            $('#enable-hubspot').on('change', function() {
                var $panel = $('#hubspot-settings');
                if ($(this).is(':checked')) {
                    $panel.slideDown();
                } else {
                    $panel.slideUp();
                }
            });

            // Initialize panel visibility on page load
            if ($('#enable-mailchimp').is(':checked')) {
                $('#mailchimp-settings').show();
            }
            if ($('#enable-hubspot').is(':checked')) {
                $('#hubspot-settings').show();
            }
        }

        // Initialize integration handlers
        initIntegrationHandlers();
    }

    // Wait for DOM to be ready and then initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeConnect2Form);
    } else {
        // DOM is already ready
        initializeConnect2Form();
    }

    // Initialize form builder (removed duplicate call)
    // initFormBuilder(); // REMOVED - already called in initializeConnect2Form()

    function initFormBuilder() {
        // Add field buttons - unbind first to prevent duplicates
        $('.field-type').off('click.addField').on('click.addField', function() {
            var type = $(this).data('type');
            addField(type);
        });

        // Field selection
        $(document).on('click', '.form-field', function(e) {
            // Don't trigger field selection if clicking on form elements within the field
            if ($(e.target).closest('.field-settings-accordion, .field-actions, .option-item').length > 0) {
                return;
            }
            
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

        // Tab switching functionality now handled by initTabFunctionality

        // Integration tab functionality
        $('#enable-ajax, #enable-scroll-to-error, #enable-honeypot').on('change', function() {
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
        $('#error-message, #submit-button-text, #required-field-message, #thank-you-message').on('change input', function() {
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
        
        // Generate default label based on field type
        var defaultLabel = getDefaultFieldLabel(type);
        
        // Add field header with label and actions
        var $header = $('<div class="field-header"></div>');
        var $label = $('<span class="field-label">' + defaultLabel + '</span>');
        var $actions = $('<div class="field-actions"></div>');
        
        // Add drag handle
        $header.prepend('<span class="dashicons dashicons-menu field-drag-handle"></span>');
        
        // Add edit and delete buttons with dashicons
        $actions.append('<button type="button" class="edit-field" title="Edit Field"><span class="dashicons dashicons-edit"></span></button>');
        $actions.append('<button type="button" class="delete-field" title="Delete Field"><span class="dashicons dashicons-trash"></span></button>');
        
        // Add layout indicator
        var $layoutIndicator = $('<span class="layout-indicator" title="Layout: Single Column">1</span>');
        $header.append($layoutIndicator);
        
        $header.append($label);
        $header.append($actions);
        $field.append($header);
        
        // Add field preview
        var $preview = $('<div class="field-preview"></div>');
        $field.append($preview);
        
        // Add to form
        $('#form-fields').append($field);
        
        // Initialize field preview with default data
        var data = {
            type: type,
            id: fieldId,
            label: defaultLabel,
            description: '',
            required: false,
            placeholder: '',
            minlength: '',
            maxlength: '',
            css_class: '',
            size: 'medium',
            default_value: '',
            validation_message: getDefaultValidationMessage(type),
            layout: 'single'
        };
        
        // Set UTM-specific defaults
        if (type === 'utm') {
            data.utm_source = true;
            data.utm_medium = true;
            data.utm_campaign = true;
            data.utm_term = false;
            data.utm_content = false;
            data.utm_label = 'Campaign Tracking';
            data.label = 'Campaign Tracking';
        }
        
        // Set CAPTCHA-specific defaults
        if (type === 'captcha') {
            data.captcha_type = 'v2_checkbox';
            data.captcha_theme = 'light';
            data.captcha_size = 'normal';
            data.label = 'reCAPTCHA';
        }
        
        // Store field data in the field element
        $field.data('field-data', data);
        
        updateFieldPreview($field, data);

        // Update field order
        updateFieldOrder();
        
        // Update all dependent field dropdowns
        updateAllDependentFieldDropdowns();
    }

    /**
     * Get default field label based on field type
     */
    function getDefaultFieldLabel(type) {
        var labels = {
            'text': 'Text Field',
            'textarea': 'Text Area',
            'email': 'Email Address',
            'number': 'Number',
            'date': 'Date',
            'phone': 'Phone Number',
            'select': 'Dropdown',
            'radio': 'Radio Buttons',
            'checkbox': 'Checkboxes',
            'file': 'File Upload',
            'utm': 'UTM Tracking',
            'captcha': 'reCAPTCHA',
            'submit': 'Submit Button'
        };
        return labels[type] || 'Field';
    }

    /**
     * Get default validation message based on field type
     */
    function getDefaultValidationMessage(type) {
        var messages = {
            'text': 'This field is required.',
            'textarea': 'This field is required.',
            'email': 'Please enter a valid email address.',
            'number': 'Please enter a valid number.',
            'date': 'Please enter a valid date.',
            'phone': 'Please enter a valid phone number.',
            'select': 'Please select an option.',
            'radio': 'Please select an option.',
            'checkbox': 'Please select at least one option.',
            'file': 'Please select a file.',
            'utm': 'UTM tracking data will be captured automatically.',
            'captcha': 'Please complete the reCAPTCHA.',
            'submit': ''
        };
        return messages[type] || 'This field is required.';
    }

    /**
     * Initialize field actions
     */
    function initFieldActions() {
        // Edit field
        $(document).on('click', '.field-actions .edit-field', function(e) {
            e.stopPropagation();
            var $field = $(this).closest('.form-field');
            
            // console.log('Edit button clicked for field:', $field.data('field-id'));
            
            // Close all other field accordions
            $('.form-field').not($field).removeClass('selected').find('.field-settings-accordion').slideUp();
            
            // Check if this field is already selected
            var wasSelected = $field.hasClass('selected');
            // console.log('Field was selected:', wasSelected);
            
            // Remove selected class from all fields
            $('.form-field').removeClass('selected');
            
            if (!wasSelected) {
                // Open this field's accordion
                $field.addClass('selected');
                var $accordion = $field.find('.field-settings-accordion');
                // console.log('Accordion exists:', $accordion.length > 0);
                
                // Create accordion if it doesn't exist
                if ($accordion.length === 0) {
                    // console.log('Creating new accordion');
                    showFieldSettings($field);
                    $accordion = $field.find('.field-settings-accordion');
                }
                
                // Show the accordion with a small delay to ensure DOM is ready
                setTimeout(function() {
                    // console.log('Sliding down accordion');
                    $accordion.slideDown();
                }, 10);
            }
        });

        // Delete field
        $(document).on('click', '.field-actions .delete-field', function(e) {
            e.stopPropagation();
            if (confirm('Are you sure you want to delete this field?')) {
                $(this).closest('.form-field').remove();
                updateAllDependentFieldDropdowns();
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
        
        // Create the accordion settings container
        var $accordion = createFieldSettingsAccordion($field, fieldType);
        
        // Update settings in the accordion
        updateAccordionSettings($accordion, fieldData, fieldType);
        
        return $accordion;
    }

    /**
     * Create field settings accordion
     */
    function createFieldSettingsAccordion($field, fieldType) {
        var fieldId = $field.data('field-id');
        var accordionHtml = '<div class="field-settings-accordion">' +
            '<div class="field-settings-content">';
        
        // Only show basic settings for non-UTM and non-CAPTCHA fields
        if (fieldType !== 'utm' && fieldType !== 'captcha') {
            accordionHtml +=
                '<div class="field-setting">' +
                    '<label for="field-label-' + fieldId + '">Label</label>' +
                    '<input type="text" id="field-label-' + fieldId + '" class="field-label-input widefat">' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-description-' + fieldId + '">Description</label>' +
                    '<textarea id="field-description-' + fieldId + '" class="field-description-input widefat" rows="3"></textarea>' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-required-' + fieldId + '"><input type="checkbox" id="field-required-' + fieldId + '" class="field-required-input"> Required</label>' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-placeholder-' + fieldId + '">Placeholder</label>' +
                    '<input type="text" id="field-placeholder-' + fieldId + '" class="field-placeholder-input widefat">' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-default-value-' + fieldId + '">Default Value</label>' +
                    '<input type="text" id="field-default-value-' + fieldId + '" class="field-default-value-input widefat">' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-css-class-' + fieldId + '">CSS Class</label>' +
                    '<input type="text" id="field-css-class-' + fieldId + '" class="field-css-class-input widefat">' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-size-' + fieldId + '">Size</label>' +
                    '<select id="field-size-' + fieldId + '" class="field-size-input">' +
                        '<option value="small">Small</option>' +
                        '<option value="medium" selected>Medium</option>' +
                        '<option value="large">Large</option>' +
                    '</select>' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-minlength-' + fieldId + '">Min Length</label>' +
                    '<input type="number" id="field-minlength-' + fieldId + '" class="field-minlength-input widefat" min="0">' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-maxlength-' + fieldId + '">Max Length</label>' +
                    '<input type="number" id="field-maxlength-' + fieldId + '" class="field-maxlength-input widefat" min="0">' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-validation-message-' + fieldId + '">Validation Message</label>' +
                    '<input type="text" id="field-validation-message-' + fieldId + '" class="field-validation-message-input widefat" placeholder="Enter custom validation message">' +
                    '<small style="color: #666; font-size: 11px; margin-top: 3px; display: block;">Message shown when validation fails</small>' +
                '</div>' +
                '<div class="field-setting">' +
                    '<label for="field-layout-' + fieldId + '">Field Layout</label>' +
                    '<select id="field-layout-' + fieldId + '" class="field-layout-input">' +
                        '<option value="single">Single Column</option>' +
                        '<option value="two-column">Two Columns</option>' +
                        '<option value="three-column">Three Columns</option>' +
                    '</select>' +
                    '<small style="color: #666; font-size: 11px; margin-top: 3px; display: block;">Choose how this field should be displayed</small>' +
                '</div>' +
                '<div class="field-setting conditional-logic-setting">' +
                    '<label for="field-conditional-enabled-' + fieldId + '"><input type="checkbox" id="field-conditional-enabled-' + fieldId + '" class="field-conditional-enabled-input"> Enable Conditional Logic</label>' +
                    '<small style="color: #666; font-size: 11px; margin-top: 3px; display: block;">Show this field only when another field meets certain conditions</small>' +
                    '<div id="conditional-settings-' + fieldId + '" class="conditional-settings" style="display: none; margin-top: 15px; padding: 15px; background: #f0f0f0; border-radius: 4px;">' +
                        '<div class="conditional-row">' +
                            '<label for="field-dependent-field-' + fieldId + '">Show when field:</label>' +
                            '<select id="field-dependent-field-' + fieldId + '" class="field-dependent-field-input widefat">' +
                                '<option value="">Select a field...</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="conditional-row">' +
                            '<label for="field-condition-' + fieldId + '">Condition:</label>' +
                            '<select id="field-condition-' + fieldId + '" class="field-condition-input widefat">' +
                                '<option value="is_not_empty">is not empty</option>' +
                                '<option value="is_empty">is empty</option>' +
                                '<option value="equals">equals</option>' +
                                '<option value="not_equals">does not equal</option>' +
                                '<option value="contains">contains</option>' +
                                '<option value="not_contains">does not contain</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="conditional-row" id="condition-value-row-' + fieldId + '" style="display: none;">' +
                            '<label for="field-condition-value-' + fieldId + '">Value:</label>' +
                            '<input type="text" id="field-condition-value-' + fieldId + '" class="field-condition-value-input widefat" placeholder="Enter value to compare">' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }
        
        // Add type-specific settings
        if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
            accordionHtml += '<div class="field-setting type-specific ' + fieldType + '-settings">' +
                '<label>Options</label>' +
                '<div id="field-options-' + fieldId + '" class="field-options-list"></div>' +
                '<button type="button" class="button add-option-btn" data-field-id="' + fieldId + '">Add Option</button>' +
            '</div>';
        } else if (fieldType === 'number') {
            accordionHtml += '<div class="field-setting type-specific number-settings">' +
                '<label for="field-min-' + fieldId + '">Minimum Value</label>' +
                '<input type="number" id="field-min-' + fieldId + '" class="field-min-input widefat">' +
                '<label for="field-max-' + fieldId + '">Maximum Value</label>' +
                '<input type="number" id="field-max-' + fieldId + '" class="field-max-input widefat">' +
            '</div>';
        } else if (fieldType === 'captcha') {
            accordionHtml += '<div class="field-setting type-specific captcha-settings">' +
                '<label for="field-captcha-type-' + fieldId + '">reCAPTCHA Type</label>' +
                '<select id="field-captcha-type-' + fieldId + '" class="field-captcha-type-input">' +
                    '<option value="v2_checkbox">v2 Checkbox</option>' +
                    '<option value="v2_invisible">v2 Invisible</option>' +
                    '<option value="v3">v3</option>' +
                '</select>' +
                '<label for="field-captcha-theme-' + fieldId + '">Theme</label>' +
                '<select id="field-captcha-theme-' + fieldId + '" class="field-captcha-theme-input">' +
                    '<option value="light">Light</option>' +
                    '<option value="dark">Dark</option>' +
                '</select>' +
                '<label for="field-captcha-size-' + fieldId + '">Size</label>' +
                '<select id="field-captcha-size-' + fieldId + '" class="field-captcha-size-input">' +
                    '<option value="normal">Normal</option>' +
                    '<option value="compact">Compact</option>' +
                '</select>' +
            '</div>';
        } else if (fieldType === 'file') {
            accordionHtml += '<div class="field-setting type-specific file-settings">' +
                '<label for="field-allowed-types-' + fieldId + '">Allowed File Types</label>' +
                '<input type="text" id="field-allowed-types-' + fieldId + '" class="field-allowed-types-input widefat" placeholder="jpg,jpeg,png,pdf,doc,docx,xls,xlsx">' +
                '<small style="color: #666; font-size: 11px; margin-top: 3px; display: block;">Comma-separated file extensions (e.g., jpg,pdf,doc)</small>' +
                '<label for="field-max-size-' + fieldId + '">Maximum File Size</label>' +
                '<div style="display: flex; gap: 10px; align-items: center;">' +
                    '<input type="number" id="field-max-size-' + fieldId + '" class="field-max-size-input" style="flex: 1;" min="1" value="5">' +
                    '<select id="field-size-unit-' + fieldId + '" class="field-size-unit-input">' +
                        '<option value="KB">KB</option>' +
                        '<option value="MB" selected>MB</option>' +
                    '</select>' +
                '</div>' +
                '<small style="color: #666; font-size: 11px; margin-top: 3px; display: block;">Maximum file size allowed for upload</small>' +
                '<label for="field-max-files-' + fieldId + '">Maximum Number of Files</label>' +
                '<input type="number" id="field-max-files-' + fieldId + '" class="field-max-files-input widefat" min="1" max="10" value="1">' +
                '<small style="color: #666; font-size: 11px; margin-top: 3px; display: block;">Maximum number of files that can be uploaded (1-10)</small>' +
            '</div>';
        } else if (fieldType === 'utm') {
            accordionHtml += '<div class="field-setting type-specific utm-settings">' +
                '<label>UTM Parameters to Track</label>' +
                '<div class="utm-parameters">' +
                    '<label><input type="checkbox" id="field-utm-source-' + fieldId + '" class="field-utm-source-input" checked> UTM Source (utm_source)</label>' +
                    '<label><input type="checkbox" id="field-utm-medium-' + fieldId + '" class="field-utm-medium-input" checked> UTM Medium (utm_medium)</label>' +
                    '<label><input type="checkbox" id="field-utm-campaign-' + fieldId + '" class="field-utm-campaign-input" checked> UTM Campaign (utm_campaign)</label>' +
                    '<label><input type="checkbox" id="field-utm-term-' + fieldId + '" class="field-utm-term-input"> UTM Term (utm_term)</label>' +
                    '<label><input type="checkbox" id="field-utm-content-' + fieldId + '" class="field-utm-content-input"> UTM Content (utm_content)</label>' +
                '</div>' +
                '<small style="color: #666; font-size: 11px; margin-top: 3px; display: block;">Select which UTM parameters to automatically capture from the URL</small>' +
                '<label for="field-utm-label-' + fieldId + '">Field Label</label>' +
                '<input type="text" id="field-utm-label-' + fieldId + '" class="field-utm-label-input widefat" value="Campaign Tracking">' +
                '<small style="color: #666; font-size: 11px; margin-top: 3px; display: block;">Label for the UTM tracking field (hidden by default)</small>' +
            '</div>';
        }
        
        accordionHtml += '</div></div>';
        
        // Remove any existing accordion before appending a new one
        $field.find('.field-settings-accordion').remove();
        var $accordion = $(accordionHtml);
        $field.append($accordion);
        
        // Initialize option buttons
        if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
            initOptionButtons($accordion, fieldId);
        }
        
        // Populate dependent field dropdown
        populateDependentFieldDropdown(fieldId);
        
        return $accordion;
    }
    
    /**
     * Populate dependent field dropdown with available fields
     */
    function populateDependentFieldDropdown(currentFieldId) {
        var $dropdown = $('#field-dependent-field-' + currentFieldId);
        $dropdown.empty();
        $dropdown.append('<option value="">Select a field...</option>');
        
        $('.form-field').each(function() {
            var fieldId = $(this).data('field-id');
            var fieldType = $(this).data('field-type');
            var fieldLabel = $(this).find('.field-label').text();
            
            // Don't include the current field or submit/hidden/html fields
            if (fieldId !== currentFieldId && 
                fieldType !== 'submit' && 
                fieldType !== 'hidden' && 
                fieldType !== 'html' &&
                fieldType !== 'captcha') {
                $dropdown.append('<option value="' + fieldId + '">' + fieldLabel + '</option>');
            }
        });
    }
    
    /**
     * Update all dependent field dropdowns
     */
    function updateAllDependentFieldDropdowns() {
        $('.form-field').each(function() {
            var fieldId = $(this).data('field-id');
            populateDependentFieldDropdown(fieldId);
        });
    }
    
    /**
     * Update accordion settings with field data
     */
    function updateAccordionSettings($accordion, fieldData, fieldType) {
        var fieldId = $accordion.closest('.form-field').data('field-id');
        
        // Only update basic settings for non-UTM and non-CAPTCHA fields
        if (fieldType !== 'utm' && fieldType !== 'captcha') {
            // Update basic settings
            $('#field-label-' + fieldId).val(fieldData.label || '');
            $('#field-description-' + fieldId).val(fieldData.description || '');
            $('#field-required-' + fieldId).prop('checked', fieldData.required || false);
            $('#field-placeholder-' + fieldId).val(fieldData.placeholder || '');
            $('#field-default-value-' + fieldId).val(fieldData.default_value || '');
            $('#field-css-class-' + fieldId).val(fieldData.css_class || '');
            $('#field-size-' + fieldId).val(fieldData.size || 'medium');
            $('#field-minlength-' + fieldId).val(fieldData.minlength || '');
            $('#field-maxlength-' + fieldId).val(fieldData.maxlength || '');
            $('#field-validation-message-' + fieldId).val(fieldData.validation_message || getDefaultValidationMessage(fieldType));
            $('#field-layout-' + fieldId).val(fieldData.layout || 'single');
            
            // Update conditional logic settings
            $('#field-conditional-enabled-' + fieldId).prop('checked', fieldData.conditional_enabled || false);
            $('#conditional-settings-' + fieldId).toggle(fieldData.conditional_enabled);

            if (fieldData.conditional_enabled) {
                $('#field-dependent-field-' + fieldId).val(fieldData.conditional_dependent_field || '');
                $('#field-condition-' + fieldId).val(fieldData.conditional_condition || 'is_not_empty');
                $('#condition-value-row-' + fieldId).toggle(fieldData.conditional_condition === 'equals' || fieldData.conditional_condition === 'not_equals' || fieldData.conditional_condition === 'contains' || fieldData.conditional_condition === 'not_contains');
                $('#field-condition-value-' + fieldId).val(fieldData.conditional_value || '');
            }
        }
        
        // Update type-specific settings
        if (fieldType === 'select' || fieldType === 'radio' || fieldType === 'checkbox') {
            var options = fieldData.options || [];
            var $optionsList = $('#field-options-' + fieldId);
            $optionsList.empty();
            options.forEach(function(option) {
                $optionsList.append('<div class="option-item"><input type="text" value="' + option + '"><button type="button" class="remove-option">Remove</button></div>');
            });
        } else if (fieldType === 'number') {
            $('#field-min-' + fieldId).val(fieldData.min || '');
            $('#field-max-' + fieldId).val(fieldData.max || '');
        } else if (fieldType === 'captcha') {
            $('#field-captcha-type-' + fieldId).val(fieldData.captcha_type || 'v2_checkbox');
            $('#field-captcha-theme-' + fieldId).val(fieldData.captcha_theme || 'light');
            $('#field-captcha-size-' + fieldId).val(fieldData.captcha_size || 'normal');
        } else if (fieldType === 'file') {
            $('#field-allowed-types-' + fieldId).val(fieldData.allowed_types || '');
            $('#field-max-size-' + fieldId).val(fieldData.max_size || '5');
            $('#field-size-unit-' + fieldId).val(fieldData.size_unit || 'MB');
            $('#field-max-files-' + fieldId).val(fieldData.max_files || '1');
        } else if (fieldType === 'utm') {
            $('#field-utm-source-' + fieldId).prop('checked', fieldData.utm_source !== false);
            $('#field-utm-medium-' + fieldId).prop('checked', fieldData.utm_medium !== false);
            $('#field-utm-campaign-' + fieldId).prop('checked', fieldData.utm_campaign !== false);
            $('#field-utm-term-' + fieldId).prop('checked', fieldData.utm_term || false);
            $('#field-utm-content-' + fieldId).prop('checked', fieldData.utm_content || false);
            $('#field-utm-label-' + fieldId).val(fieldData.utm_label || 'Campaign Tracking');
        }
        
        // Bind change events
        bindAccordionChangeEvents($accordion, fieldId);
    }
    
    /**
     * Initialize option buttons for select/radio/checkbox fields
     */
    function initOptionButtons($accordion, fieldId) {
        // Add option button click handler
        $accordion.find('.add-option-btn').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // console.log('Add option button clicked for field:', fieldId);
            var $optionsList = $('#field-options-' + fieldId);
            var newOptionHtml = '<div class="option-item">' +
                '<input type="text" value="" placeholder="Enter option text">' +
                '<button type="button" class="remove-option">Remove</button>' +
                '</div>';
            $optionsList.append(newOptionHtml);
            
            // Focus on the new input for better UX
            $optionsList.find('.option-item:last-child input').focus();
        });
        
        // Remove option button click handler
        $accordion.off('click', '.remove-option').on('click', '.remove-option', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // console.log('Remove option button clicked');
            $(this).closest('.option-item').remove();
            
            // Update preview after removing option
            setTimeout(function() {
                var $field = $accordion.closest('.form-field');
                var fieldData = getFieldData($field);
                updateFieldPreview($field, fieldData);
            }, 100);
        });
        
        // Handle option input events to prevent interference
        $accordion.off('click focus blur', '.option-item input').on('click focus blur', '.option-item input', function(e) {
            e.stopPropagation();
        });
    }
    
    /**
     * Bind change events for accordion inputs
     */
    function bindAccordionChangeEvents($accordion, fieldId) {
        // Remove existing event handlers to prevent duplicates
        $accordion.off('change input', 'input, select, textarea');
        
        // Bind events using event delegation for better performance
        $accordion.on('change input', 'input, select, textarea', function(e) {
            // Skip if this is a newly added option input (prevent immediate triggering)
            if ($(this).closest('.option-item').length > 0 && !$(this).data('initialized')) {
                $(this).data('initialized', true);
                return;
            }
            
            // Skip option inputs to prevent interference with editing
            if ($(this).closest('.option-item').length > 0) {
                return;
            }
            
            var $field = $accordion.closest('.form-field');
            var fieldData = getFieldData($field);
            
            // Update field preview
            updateFieldPreview($field, fieldData);
            
            // Update field header label if label input changed
            if ($(this).hasClass('field-label-input')) {
                var newLabel = $(this).val() || getDefaultFieldLabel(fieldData.type);
                $field.find('.field-header .field-label').text(newLabel);
            }
            
            // Update validation message if validation message input changed
            if ($(this).hasClass('field-validation-message-input')) {
                var newValidationMessage = $(this).val() || getDefaultValidationMessage(fieldData.type);
                // You can add visual feedback here if needed
            }
            
            // Store updated data back to the field element
            $field.data('field-data', fieldData);
        });
        
        // Special handling for required checkbox - use direct binding instead of delegation
        var $requiredCheckbox = $accordion.find('.field-required-input');
        $requiredCheckbox.off('change click').on('change click', function(e) {
            // console.log('Required checkbox event:', e.type, 'ID:', $(this).attr('id'), 'Checked:', $(this).is(':checked'));
            
            var $field = $accordion.closest('.form-field');
            var fieldData = getFieldData($field);
            
            // console.log('Field data after checkbox change:', fieldData);
            
            updateFieldPreview($field, fieldData);
            $field.data('field-data', fieldData);
        });
        
        // Special handling for conditional logic enabled checkbox
        var $conditionalEnabledCheckbox = $accordion.find('.field-conditional-enabled-input');
        $conditionalEnabledCheckbox.off('change').on('change', function(e) {
            // console.log('Conditional logic enabled checkbox event:', e.type, 'ID:', $(this).attr('id'), 'Checked:', $(this).is(':checked'));
            
            var $field = $accordion.closest('.form-field');
            var fieldData = getFieldData($field);
            
            fieldData.conditional_enabled = $(this).is(':checked');
            $('#conditional-settings-' + fieldId).toggle(fieldData.conditional_enabled);
            
            updateFieldPreview($field, fieldData);
            $field.data('field-data', fieldData);
        });

        // Special handling for conditional dependent field
        var $conditionalDependentFieldSelect = $accordion.find('.field-dependent-field-input');
        $conditionalDependentFieldSelect.off('change').on('change', function(e) {
            // console.log('Conditional dependent field select event:', e.type, 'ID:', $(this).attr('id'), 'Value:', $(this).val());
            
            var $field = $accordion.closest('.form-field');
            var fieldData = getFieldData($field);
            
            fieldData.conditional_dependent_field = $(this).val();
            $('#condition-value-row-' + fieldId).toggle(fieldData.conditional_condition === 'equals' || fieldData.conditional_condition === 'not_equals' || fieldData.conditional_condition === 'contains' || fieldData.conditional_condition === 'not_contains');
            
            updateFieldPreview($field, fieldData);
            $field.data('field-data', fieldData);
        });

        // Special handling for conditional condition
        var $conditionalConditionSelect = $accordion.find('.field-condition-input');
        $conditionalConditionSelect.off('change').on('change', function(e) {
            // console.log('Conditional condition select event:', e.type, 'ID:', $(this).attr('id'), 'Value:', $(this).val());
            
            var $field = $accordion.closest('.form-field');
            var fieldData = getFieldData($field);
            
            fieldData.conditional_condition = $(this).val();
            $('#condition-value-row-' + fieldId).toggle(fieldData.conditional_condition === 'equals' || fieldData.conditional_condition === 'not_equals' || fieldData.conditional_condition === 'contains' || fieldData.conditional_condition === 'not_contains');
            
            updateFieldPreview($field, fieldData);
            $field.data('field-data', fieldData);
        });

        // Special handling for conditional value input
        var $conditionalValueInput = $accordion.find('.field-condition-value-input');
        $conditionalValueInput.off('change').on('change', function(e) {
            // console.log('Conditional value input event:', e.type, 'ID:', $(this).attr('id'), 'Value:', $(this).val());
            
            var $field = $accordion.closest('.form-field');
            var fieldData = getFieldData($field);
            
            fieldData.conditional_value = $(this).val();
            updateFieldPreview($field, fieldData);
            $field.data('field-data', fieldData);
        });
        
        // Special handling for option inputs using event delegation
        $accordion.on('input', '.option-item input', function(e) {
            // Prevent event bubbling to avoid conflicts
            e.stopPropagation();
            
            // Add a small delay to prevent excessive updates
            clearTimeout($(this).data('timeout'));
            var timeout = setTimeout(function() {
                var $field = $accordion.closest('.form-field');
                var fieldData = getFieldData($field);
                updateFieldPreview($field, fieldData);
            }, 300);
            $(this).data('timeout', timeout);
        });
        
        // Handle option input focus to prevent accordion closing
        $accordion.on('focus', '.option-item input', function(e) {
            e.stopPropagation();
        });
        
        // Handle option input click to prevent accordion closing
        $accordion.on('click', '.option-item input', function(e) {
            e.stopPropagation();
        });
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
        
        // Get current values from the accordion settings if the field has an accordion
        var $accordion = $field.find('.field-settings-accordion');
        if ($accordion.length > 0) {
            var fieldId = $field.data('field-id');
            
            // Only collect basic settings for non-UTM and non-CAPTCHA fields
            if (data.type !== 'utm' && data.type !== 'captcha') {
                data.label = $('#field-label-' + fieldId).val() || data.label || '';
                data.description = $('#field-description-' + fieldId).val() || data.description || '';
                data.required = $('#field-required-' + fieldId).is(':checked');
                data.placeholder = $('#field-placeholder-' + fieldId).val() || data.placeholder || '';
                data.minlength = $('#field-minlength-' + fieldId).val() || data.minlength || '';
                data.maxlength = $('#field-maxlength-' + fieldId).val() || data.maxlength || '';
                data.css_class = $('#field-css-class-' + fieldId).val() || data.css_class || '';
                data.size = $('#field-size-' + fieldId).val() || data.size || 'medium';
                data.default_value = $('#field-default-value-' + fieldId).val() || data.default_value || '';
                data.validation_message = $('#field-validation-message-' + fieldId).val() || data.validation_message || getDefaultValidationMessage(data.type);
                data.layout = $('#field-layout-' + fieldId).val() || data.layout || 'single';
                data.conditional_enabled = $('#field-conditional-enabled-' + fieldId).is(':checked');
                data.conditional_dependent_field = $('#field-dependent-field-' + fieldId).val();
                data.conditional_condition = $('#field-condition-' + fieldId).val();
                data.conditional_value = $('#field-condition-value-' + fieldId).val();
            }
            
            // Get type-specific data from accordion settings
            if (data.type === 'select' || data.type === 'radio' || data.type === 'checkbox') {
                data.options = [];
                $('#field-options-' + fieldId + ' .option-item input').each(function() {
                    var value = $(this).val();
                    if (value.trim() !== '') {
                        data.options.push(value);
                    }
                });
            } else if (data.type === 'number') {
                data.min = $('#field-min-' + fieldId).val() || data.min || '';
                data.max = $('#field-max-' + fieldId).val() || data.max || '';
            } else if (data.type === 'captcha') {
                data.captcha_type = $('#field-captcha-type-' + fieldId).val() || data.captcha_type || 'v2_checkbox';
                data.captcha_theme = $('#field-captcha-theme-' + fieldId).val() || data.captcha_theme || 'light';
                data.captcha_size = $('#field-captcha-size-' + fieldId).val() || data.captcha_size || 'normal';
                // Set default label for CAPTCHA fields
                data.label = 'reCAPTCHA';
            } else if (data.type === 'file') {
                data.allowed_types = $('#field-allowed-types-' + fieldId).val() || data.allowed_types || '';
                data.max_size = $('#field-max-size-' + fieldId).val() || data.max_size || '5';
                data.size_unit = $('#field-size-unit-' + fieldId).val() || data.size_unit || 'MB';
                data.max_files = $('#field-max-files-' + fieldId).val() || data.max_files || '1';
            } else if (data.type === 'utm') {
                data.utm_source = $('#field-utm-source-' + fieldId).is(':checked');
                data.utm_medium = $('#field-utm-medium-' + fieldId).is(':checked');
                data.utm_campaign = $('#field-utm-campaign-' + fieldId).is(':checked');
                data.utm_term = $('#field-utm-term-' + fieldId).is(':checked');
                data.utm_content = $('#field-utm-content-' + fieldId).is(':checked');
                data.utm_label = $('#field-utm-label-' + fieldId).val() || 'Campaign Tracking';
                // Set default label for UTM fields
                data.label = data.utm_label || 'Campaign Tracking';
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
        
        // Store layout setting but don't apply it in the builder
        // Layout will only be applied on the frontend
        $field.attr('data-layout', data.layout || 'single');
        
        // Update layout indicator
        var layoutText = '1';
        var layoutTitle = 'Layout: Single Column';
        if (data.layout === 'two-column') {
            layoutText = '2';
            layoutTitle = 'Layout: Two Columns';
        } else if (data.layout === 'three-column') {
            layoutText = '3';
            layoutTitle = 'Layout: Three Columns';
        }
        // Update field header
        var $header = $field.find('.field-header');
        var $label = $header.find('.field-label');
        var $type = $header.find('.field-type');
        var $layoutIndicator = $header.find('.layout-indicator');
        
        $label.text(data.label || getDefaultFieldLabel(data.type));
        $type.text(data.type);
        
        // Update layout indicator
        if ($layoutIndicator.length === 0) {
            $layoutIndicator = $('<span class="layout-indicator" title="Layout: ' + (data.layout || 'single') + '">' + (data.layout === 'two-column' ? '2' : data.layout === 'three-column' ? '3' : '1') + '</span>');
            $label.before($layoutIndicator);
        } else {
            $layoutIndicator.text(data.layout === 'two-column' ? '2' : data.layout === 'three-column' ? '3' : '1');
            $layoutIndicator.attr('title', 'Layout: ' + (data.layout || 'single'));
        }
        
        // Add conditional logic indicator
        var $conditionalIndicator = $header.find('.conditional-indicator');
        if (data.conditional_enabled && data.conditional_dependent_field) {
            if ($conditionalIndicator.length === 0) {
                $conditionalIndicator = $('<span class="conditional-indicator" title="Conditional: Shows when ' + data.conditional_dependent_field + ' ' + data.conditional_condition + '">⚡</span>');
                $label.after($conditionalIndicator);
            } else {
                $conditionalIndicator.attr('title', 'Conditional: Shows when ' + data.conditional_dependent_field + ' ' + data.conditional_condition);
            }
        } else {
            $conditionalIndicator.remove();
        }
        
        // Don't update form container layout in the builder
        // updateFormContainerLayout();
        
        // Create field preview based on type
        switch (data.type) {
            case 'text':
                var $input = $('<input type="text" class="' + data.css_class + '" placeholder="' + (data.placeholder || '') + '">');
                if (data.minlength) {
                    $input.attr('minlength', data.minlength);
                }
                if (data.maxlength) {
                    $input.attr('maxlength', data.maxlength);
                }
                if (data.required) {
                    $input.attr('required', 'required');
                }
                $preview.append($input);
                break;

            case 'textarea':
                var $textarea = $('<textarea class="' + data.css_class + '" placeholder="' + (data.placeholder || '') + '"></textarea>');
                if (data.minlength) {
                    $textarea.attr('minlength', data.minlength);
                }
                if (data.maxlength) {
                    $textarea.attr('maxlength', data.maxlength);
                }
                if (data.required) {
                    $textarea.attr('required', 'required');
                }
                $preview.append($textarea);
                break;

            case 'email':
                var $input = $('<input type="email" class="' + data.css_class + '" placeholder="' + (data.placeholder || '') + '">');
                if (data.minlength) {
                    $input.attr('minlength', data.minlength);
                }
                if (data.maxlength) {
                    $input.attr('maxlength', data.maxlength);
                }
                if (data.required) {
                    $input.attr('required', 'required');
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
                if (data.minlength) {
                    $input.attr('minlength', data.minlength);
                }
                if (data.maxlength) {
                    $input.attr('maxlength', data.maxlength);
                }
                if (data.required) {
                    $input.attr('required', 'required');
                }
                $preview.append($input);
                break;

            case 'date':
                var $dateInput = $('<input type="date" class="' + data.css_class + '">');
                if (data.required) {
                    $dateInput.attr('required', 'required');
                }
                // Add max attribute to restrict year to 4 digits
                $dateInput.attr('max', '9999-12-31');
                $preview.append($dateInput);
                break;

            case 'phone':
                var $phoneInput = $('<input type="tel" class="' + data.css_class + '" placeholder="' + (data.placeholder || '') + '">');
                if (data.required) {
                    $phoneInput.attr('required', 'required');
                }
                if (data.maxlength) {
                    $phoneInput.attr('maxlength', data.maxlength);
                }
                if (data.minlength) {
                    $phoneInput.attr('minlength', data.minlength);
                }
                // Add pattern for phone validation - allows digits, spaces, dashes, parentheses, and plus sign
                $phoneInput.attr('pattern', '[0-9\\+\\-\\(\\)\\s]+');
                // Add title for better user experience
                $phoneInput.attr('title', 'Please enter a valid phone number (e.g., +1-555-123-4567)');
                $preview.append($phoneInput);
                break;

            case 'select':
                var $select = $('<select class="' + data.css_class + '"></select>');
                if (data.options && data.options.length > 0) {
                    data.options.forEach(function(option) {
                        $select.append('<option value="' + option + '">' + option + '</option>');
                    });
                } else {
                    $select.append('<option value="">Select an option</option>');
                }
                if (data.required) {
                    $select.attr('required', 'required');
                }
                $preview.append($select);
                break;

            case 'radio':
                if (data.options && data.options.length > 0) {
                    var $container = $('<div class="radio-options"></div>');
                    data.options.forEach(function(option, index) {
                        var $option = $(
                            '<label><input type="radio" name="radio_' + data.id + '" value="' + option + '"' + 
                            (data.required && index === 0 ? ' required' : '') + '> ' +
                            option + '</label>'
                        );
                        $container.append($option);
                    });
                    $preview.append($container);
                } else {
                    $preview.append('<div class="radio-options"><small style="color: #999;">No options added yet</small></div>');
                }
                break;

            case 'checkbox':
                if (data.options && data.options.length > 0) {
                    var $container = $('<div class="checkbox-options"></div>');
                    data.options.forEach(function(option, index) {
                        var $option = $(
                            '<label><input type="checkbox" name="checkbox_' + data.id + '[]" value="' + option + '"' + 
                            (data.required && index === 0 ? ' required' : '') + '> ' +
                            option + '</label>'
                        );
                        $container.append($option);
                    });
                    $preview.append($container);
                } else {
                    $preview.append('<div class="checkbox-options"><small style="color: #999;">No options added yet</small></div>');
                }
                break;

            case 'file':
                var $fileInput = $('<input type="file" class="' + data.css_class + '">');
                if (data.required) {
                    $fileInput.attr('required', 'required');
                }
                
                // Add file type restrictions
                if (data.allowed_types && data.allowed_types.trim() !== '') {
                    var allowedTypes = data.allowed_types.split(',').map(function(type) {
                        return type.trim();
                    }).join(',');
                    $fileInput.attr('accept', allowedTypes);
                }
                
                // Add multiple file support
                if (data.max_files && parseInt(data.max_files) > 1) {
                    $fileInput.attr('multiple', 'multiple');
                }
                
                // Add file size info
                var sizeInfo = '';
                if (data.max_size) {
                    sizeInfo = ' (Max: ' + data.max_size + ' ' + (data.size_unit || 'MB') + ')';
                }
                if (data.allowed_types) {
                    sizeInfo += ' - Allowed: ' + data.allowed_types;
                }
                
                if (sizeInfo) {
                    $fileInput.after('<small style="color: #666; font-size: 11px; margin-top: 5px; display: block;">' + sizeInfo + '</small>');
                }
                
                $preview.append($fileInput);
                break;

            case 'utm':
                var utmParams = [];
                if (data.utm_source) utmParams.push('utm_source');
                if (data.utm_medium) utmParams.push('utm_medium');
                if (data.utm_campaign) utmParams.push('utm_campaign');
                if (data.utm_term) utmParams.push('utm_term');
                if (data.utm_content) utmParams.push('utm_content');
                
                var $utmPreview = $('<div class="utm-preview" style="background: #f0f8ff; padding: 10px; border-radius: 4px; border: 1px solid #d1e7dd;">' +
                    '<strong>UTM Tracking Field</strong><br>' +
                    '<small>Will automatically capture: ' + (utmParams.length > 0 ? utmParams.join(', ') : 'No parameters selected') + '</small><br>' +
                    '<small>Field will be hidden on frontend</small>' +
                '</div>');
                $preview.append($utmPreview);
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
        if (data.description) {
            var $description = $('<p class="field-description">' + data.description + '</p>');
            $preview.append($description);
        }
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
        var formId = connect2formCF.currentFormId || 0;
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
            success_message: $('#thank-you-message').val() || 'Thank you! Your form has been submitted successfully.',
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

        // Ensure email notifications have proper content
        ensureEmailNotificationContent();

        // Send AJAX request
        $.ajax({
            url: connect2formCF.ajaxurl,
            type: 'POST',
            data: {
                action: 'connect2form_save_form',
                nonce: connect2formCF.nonce,
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
                    showNotification(connect2formCF.i18n.saveSuccess, 'success');
                    
                    // Update shortcode and PHP code with actual form ID
                    if (response.data && response.data.form_id) {
                        updateIntegrationCodes(response.data.form_id);
                    }
                    
                    // Redirect to forms list if this is a new form
                    if (!formId) {
                        setTimeout(function() {
                            window.location.href = connect2formCF.formsListUrl;
                        }, 1500);
                    }
                } else {
                    showNotification(response.data || connect2formCF.i18n.saveError, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(connect2formCF.i18n.saveError, 'error');
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
        $('#form-shortcode').text('[connect2form id="' + formId + '"]');
        $('#form-php-code').text('<?php echo do_shortcode(\'[connect2form id="' + formId + '"]\'); ?>');
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
        return connect2formCF.adminEmail || 'admin@' + window.location.hostname;
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
        if (connect2formCF.formData && connect2formCF.formData.settings && connect2formCF.formData.settings.email_notifications) {
            emailNotifications = connect2formCF.formData.settings.email_notifications;
        }
        
        renderEmailNotificationsList();
        
        // Initialize copy buttons
        initCopyButtons();
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
            success_message: $('#thank-you-message').val(),
            error_message: $('#error-message').val(),
            submit_button_text: $('#submit-button-text').val(),
            required_field_message: $('#required-field-message').val(),
            redirect_type: $('#redirect-type').val(),
            thank_you_message: $('#thank-you-message').val(),
            redirect_url: $('#redirect-url').val(),
            
            // Email notifications
            email_notifications: emailNotifications,
            
            // Form behavior
            enable_ajax: $('#enable-ajax').is(':checked'),
            scroll_to_error: $('#enable-scroll-to-error').is(':checked'),
            enable_honeypot: $('#enable-honeypot').is(':checked'),
            

        };
    }

    /**
     * Preview form
     */
    function previewForm() {
        var formId = connect2formCF.currentFormId || 0;
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
        var previewUrl = connect2formCF.ajaxurl + '?action=connect2form_preview_form&form_id=' + formId + '&nonce=' + connect2formCF.nonce;
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
            success_message: $('#thank-you-message').val() || 'Thank you! Your form has been submitted successfully.',
            error_message: $('#error-message').val() || 'Please fix the errors below and try again.',
            submit_text: $('#submit-button-text').val() || 'Submit',
            required_message: $('#required-field-message').val() || 'This field is required.',
            redirect_type: $('#redirect-type').val() || 'message',
            thank_you_message: $('#thank-you-message').val() || 'Thank you! Your form has been submitted successfully. We will get back to you soon.',
            redirect_url: $('#redirect-url').val(),
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

        // Ensure email notifications have proper content
        ensureEmailNotificationContent();

        // Send AJAX request
        $.ajax({
            url: connect2formCF.ajaxurl,
            type: 'POST',
            data: {
                action: 'connect2form_save_form',
                nonce: connect2formCF.nonce,
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
                    connect2formCF.currentFormId = response.data.form_id;
                    $('#form-builder').attr('data-form-id', response.data.form_id);
                    
                    // Open preview
                    var previewUrl = connect2formCF.ajaxurl + '?action=connect2form_preview_form&form_id=' + response.data.form_id + '&nonce=' + connect2formCF.nonce;
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

      // Initialize integration settings handlers
      function initIntegrationHandlers() {
        // Handle Mailchimp integration toggle
        $('#enable-mailchimp').on('change', function() {
            var $panel = $('#mailchimp-settings');
            if ($(this).is(':checked')) {
                $panel.slideDown();
            } else {
                $panel.slideUp();
            }
        });

        // Handle HubSpot integration toggle  
        $('#enable-hubspot').on('change', function() {
            var $panel = $('#hubspot-settings');
            if ($(this).is(':checked')) {
                $panel.slideDown();
            } else {
                $panel.slideUp();
            }
        });

        // Initialize panel visibility on page load
        if ($('#enable-mailchimp').is(':checked')) {
            $('#mailchimp-settings').show();
        }
        if ($('#enable-hubspot').is(':checked')) {
            $('#hubspot-settings').show();
        }
    }

    // Initialize integration handlers when document is ready
    $(document).ready(function() {
        initIntegrationHandlers();
    });
});