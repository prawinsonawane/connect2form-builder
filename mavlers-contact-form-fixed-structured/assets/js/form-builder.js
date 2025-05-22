jQuery(document).ready(function($) {
    // Check if mavlersFormBuilder is defined
    if (typeof mavlersFormBuilder === 'undefined') {
        console.error('mavlersFormBuilder is not defined. Please make sure the script is properly localized.');
        return;
    }

    const FormBuilder = {
        init: function() {
            console.log('FormBuilder initialized');
            
            // Get form ID from URL or hidden input
            const urlParams = new URLSearchParams(window.location.search);
            this.formId = urlParams.get('form_id') || $('#form-id').val();
            console.log('Form ID:', this.formId);
            
            // Initialize field types
            this.fieldTypes = mavlersFormBuilder.fieldTypes || {};
            console.log('Available field types:', this.fieldTypes);
            
            if (Object.keys(this.fieldTypes).length === 0) {
                console.error('No field types available');
                this.showNotification('Error: No field types available. Please refresh the page.', 'error');
                return;
            }
            
            // Debug field types
            Object.keys(this.fieldTypes).forEach(type => {
                console.log(`Field type ${type}:`, this.fieldTypes[type]);
            });
            
            this.tempFields = [];
            this.bindEvents();
            this.initSortable();
            
            // Load existing fields if we're editing a form
            if (this.formId) {
                console.log('Loading existing fields for form ID:', this.formId);
                this.loadExistingFields();
            } else {
                console.log('No form ID found, starting new form');
            }
        },

        showNotification: function(message, type = 'success') {
            const $notification = $('#mavlers-notification');
            $notification
                .removeClass('success error')
                .addClass(type)
                .find('.mavlers-notification-message')
                .text(message)
                .end()
                .fadeIn();

            // Auto hide after 5 seconds
            setTimeout(() => {
                $notification.fadeOut();
            }, 5000);
        },

        bindEvents: function() {
            console.log('Binding events');
            
            // Field type buttons
            $('.mavlers-field-button').on('click', (e) => {
                console.log('Field button clicked');
                const $button = $(e.currentTarget);
                const fieldType = $button.data('type');
                console.log('Field type from button:', fieldType);
                
                if (!fieldType) {
                    console.error('No field type found on button');
                    return;
                }
                
                if (!this.fieldTypes[fieldType]) {
                    console.error('Field type not found in available types:', fieldType);
                    return;
                }
                
                console.log('Opening field settings for type:', fieldType);
                this.openFieldSettings(fieldType);
            });

            // Edit field
            $(document).on('click', '.mavlers-edit-field', (e) => {
                console.log('Edit field clicked');
                const $field = $(e.currentTarget).closest('.mavlers-field');
                const fieldData = $field.data('field-data');
                console.log('Field data for edit:', fieldData);
                
                if (!fieldData || !fieldData.field_type) {
                    console.error('Invalid field data for editing');
                    return;
                }
                
                console.log('Opening field settings for editing:', fieldData.field_type);
                this.openFieldSettings(fieldData.field_type, fieldData);
            });

            // Delete field
            $(document).on('click', '.mavlers-delete-field', (e) => {
                console.log('Delete field clicked');
                const $field = $(e.currentTarget).closest('.mavlers-field');
                this.deleteField($field);
            });

            // Save form
            $('#save-form').on('click', () => {
                console.log('Save form clicked');
                this.saveForm();
            });

            // Preview form
            $('#preview-form').on('click', () => {
                console.log('Preview form clicked');
                this.previewForm();
            });

            // Form title change
            $('#form-title').on('change', () => {
                console.log('Form title changed');
                this.generateFormTitle();
            });

            // Modal events - using event delegation
            $(document).on('click', '.mavlers-modal-close, .mavlers-modal-cancel', function() {
                $(this).closest('.mavlers-modal').remove();
            });

            $(document).on('click', '#save-field-settings', () => {
                console.log('Save field settings clicked');
                const $modal = $('.mavlers-modal');
                if ($modal.length) {
                    this.saveFieldSettings($modal);
                } else {
                    console.error('Modal not found');
                }
            });

            // Add notification close button handler
            $(document).on('click', '.mavlers-notification-close', function() {
                $(this).closest('.mavlers-notification').fadeOut();
            });
        },

        initSortable: function() {
            $('#form-fields').sortable({
                handle: '.mavlers-field-move',
                placeholder: 'mavlers-field-placeholder',
                update: () => this.updateFieldOrder()
            }).disableSelection();
        },

        initFieldTypes: function() {
            this.fieldTypes = mavlersFormBuilder.fieldTypes;
        },

        loadExistingFields: function() {
            console.log('Starting loadExistingFields for form:', this.formId);
           
            $.ajax({
                url: mavlersFormBuilder.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_get_form_fields',
                    nonce: mavlersFormBuilder.nonce,
                    form_id: this.formId
                },
                success: (response) => {
                    console.log('AJAX Response:', response);
                    if (response.success && response.data) {
                        console.log('Fields found:', response.data);
                        // Clear any existing fields
                        $('#form-fields').empty();
                        this.tempFields = [];
                        
                        // Add each field to the form
                        response.data.forEach(field => {
                            console.log('Processing field:', field);
                            // Validate field data before adding
                            if (field && field.field_type && field.field_label) {
                                const fieldData = {
                                    id: field.id || 'field_' + Date.now(),
                                    field_type: field.field_type,
                                    field_label: field.field_label,
                                    field_name: field.field_name || field.field_label.toLowerCase().replace(/\s+/g, '_'),
                                    field_required: field.field_required || false,
                                    field_placeholder: field.field_placeholder || '',
                                    field_description: field.field_description || '',
                                    field_options: field.field_options || '',
                                    field_meta: field.field_meta || {},
                                    field_order: this.tempFields.length,
                                    column_layout: field.column_layout || 'full'
                                };
                                
                                console.log('Processed field data for preview:', fieldData);
                                this.tempFields.push(fieldData);
                                this.addFieldToPreview(fieldData);
                            } else {
                                console.warn('Skipping invalid field:', field);
                            }
                        });
                        
                        // Show empty state if no fields
                        if (this.tempFields.length === 0) {
                            console.log('No valid fields found, showing empty state');
                            $('#form-fields').html('<div class="mavlers-empty-form"><p>No fields added yet. Add fields from the sidebar.</p></div>');
                        }
                    } else {
                        console.error('Failed to load form fields:', response.data);
                        $('#form-fields').html('<div class="mavlers-empty-form"><p>Error loading fields. Please try again.</p></div>');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    $('#form-fields').html('<div class="mavlers-empty-form"><p>Error loading fields. Please try again.</p></div>');
                }
            });
        },

        openFieldSettings: function(type, fieldData = null) {
            console.log('Opening field settings for type:', type, 'Field data:', fieldData);
            
            if (!type || !this.fieldTypes[type]) {
                console.error('Invalid field type:', type);
                alert('Error: Invalid field type. Please try again.');
                return;
            }
            
            const title = fieldData ? 'Edit Field' : 'Add Field';
            const settings = this.getFieldSettings(type, fieldData);
            
            // Remove any existing modal
            $('.mavlers-modal').remove();
            
            // Debug the field type before creating modal
            console.log('Creating modal with field type:', type);
            
            // Create modal HTML
            const modalHtml = `
                <div class="mavlers-modal show" data-field-type="${type}">
                    <div class="mavlers-modal-content">
                        <div class="mavlers-modal-header">
                            <h3>${title}</h3>
                            <button type="button" class="mavlers-modal-close">&times;</button>
                        </div>
                        <div class="mavlers-modal-body">
                            <form class="mavlers-field-settings-form">
                                <input type="hidden" name="field_type" value="${type}">
                                ${settings}
                            </form>
                        </div>
                        <div class="mavlers-modal-footer">
                            <button type="button" class="button mavlers-modal-cancel">Cancel</button>
                            <button type="button" class="button button-primary" id="save-field-settings">Save</button>
                        </div>
                    </div>
                </div>
            `;

            // Append modal to body
            const $modal = $(modalHtml);
            $('body').append($modal);
            
            // Debug the field type input after modal is created
            const $fieldTypeInput = $modal.find('input[name="field_type"]');
            console.log('Modal created:', $modal.length > 0);
            console.log('Field type input found:', $fieldTypeInput.length > 0);
            console.log('Field type input value:', $fieldTypeInput.val());
            
            // Handle conditional fields
            this.handleConditionalFields($modal);
        },

        handleConditionalFields: function($modal) {
            const $form = $modal.find('.mavlers-field-settings-form');
            $form.find('.mavlers-field-setting[data-show-if]').each(function() {
                const $setting = $(this);
                const showIf = $setting.data('show-if');
                const showIfValue = $setting.data('show-if-value');
                const $trigger = $form.find(`#field-${showIf}`);

                const toggleVisibility = () => {
                    if ($trigger.val() === showIfValue) {
                        $setting.show();
                    } else {
                        $setting.hide();
                    }
                };

                $trigger.on('change', toggleVisibility);
                toggleVisibility();
            });
        },

        getFieldSettings: function(type, fieldData = null) {
            console.log('Getting settings for field type:', type);
            const fieldType = this.fieldTypes[type];
            
            if (!fieldType || !fieldType.settings) {
                console.error('Invalid field type or missing settings:', type);
                return '';
            }

            let settings = '';
            for (const [key, setting] of Object.entries(fieldType.settings)) {
                // Get the value from fieldData if it exists, otherwise use empty string
                let value = '';
                if (fieldData) {
                    // Handle different field data structures
                    if (key === 'label') {
                        value = fieldData.field_label || '';
                    } else if (key === 'name') {
                        value = fieldData.field_name || '';
                    } else if (key === 'required') {
                        value = fieldData.field_required || false;
                    } else if (key === 'placeholder') {
                        value = fieldData.field_placeholder || '';
                    } else if (key === 'description') {
                        value = fieldData.field_description || '';
                    } else if (key === 'options') {
                        value = fieldData.field_options || '';
                    } else if (key === 'column_layout') {
                        value = fieldData.column_layout || 'full';
                    } else {
                        value = fieldData[key] || '';
                    }
                }

                const showIf = setting.show_if ? `data-show-if="${setting.show_if.type}" data-show-if-value="${setting.show_if.value}"` : '';
                
                switch (setting.type) {
                    case 'text':
                        settings += `
                            <div class="mavlers-field-setting" ${showIf}>
                                <label for="field-${key}">${setting.label}</label>
                                <input type="text" id="field-${key}" name="${key}" value="${value}" ${setting.required ? 'required' : ''}>
                                ${setting.description ? `<p class="description">${setting.description}</p>` : ''}
                            </div>
                        `;
                        break;
                    case 'textarea':
                        settings += `
                            <div class="mavlers-field-setting" ${showIf}>
                                <label for="field-${key}">${setting.label}</label>
                                <textarea id="field-${key}" name="${key}" ${setting.required ? 'required' : ''}>${value}</textarea>
                                ${setting.description ? `<p class="description">${setting.description}</p>` : ''}
                            </div>
                        `;
                        break;
                    case 'checkbox':
                        settings += `
                            <div class="mavlers-field-setting" ${showIf}>
                                <label>
                                    <input type="checkbox" id="field-${key}" name="${key}" ${value ? 'checked' : ''}>
                                    ${setting.label}
                                </label>
                                ${setting.description ? `<p class="description">${setting.description}</p>` : ''}
                            </div>
                        `;
                        break;
                    case 'select':
                        let options = '';
                        for (const [optValue, optLabel] of Object.entries(setting.options)) {
                            options += `<option value="${optValue}" ${value === optValue ? 'selected' : ''}>${optLabel}</option>`;
                        }
                        settings += `
                            <div class="mavlers-field-setting" ${showIf}>
                                <label for="field-${key}">${setting.label}</label>
                                <select id="field-${key}" name="${key}" ${setting.required ? 'required' : ''}>
                                    ${options}
                                </select>
                                ${setting.description ? `<p class="description">${setting.description}</p>` : ''}
                            </div>
                        `;
                        break;
                    case 'number':
                        settings += `
                            <div class="mavlers-field-setting" ${showIf}>
                                <label for="field-${key}">${setting.label}</label>
                                <input type="number" id="field-${key}" name="${key}" value="${value}" ${setting.required ? 'required' : ''}>
                                ${setting.description ? `<p class="description">${setting.description}</p>` : ''}
                            </div>
                        `;
                        break;
                }
            }

            return settings;
        },

        saveFieldSettings: function($modal) {
            console.log('Saving field settings');
            
            if (!$modal || !$modal.length) {
                console.error('Modal not found');
                return;
            }
            
            // Get form and field type
            const $form = $modal.find('.mavlers-field-settings-form');
            const fieldType = $modal.data('field-type');
            
            console.log('Modal found in saveFieldSettings:', $modal.length > 0);
            console.log('Form found in saveFieldSettings:', $form.length > 0);
            console.log('Field type from modal data:', fieldType);
            
            if (!fieldType || !this.fieldTypes[fieldType]) {
                console.error('Invalid field type:', fieldType);
                alert('Error: Invalid field type. Please try again.');
                return;
            }

            const formData = {};
            
            // Get all form fields
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const type = $field.attr('type');
                
                if (name && name !== 'field_type') {
                    if (type === 'checkbox') {
                        formData[name] = $field.is(':checked');
                    } else {
                        formData[name] = $field.val();
                    }
                }
            });

            // Skip label validation for submit and HTML fields
            if (fieldType !== 'submit' && fieldType !== 'html') {
                const label = formData.label;
                if (!label) {
                    this.showNotification('Field label is required', 'error');
                    return;
                }
            }

            // Generate field name based on field type
            let fieldName = '';
            if (fieldType === 'submit') {
                fieldName = 'submit_button';
            } else if (fieldType === 'html') {
                fieldName = 'html_content';
            } else {
                fieldName = formData.name || (formData.label ? formData.label.toLowerCase().replace(/\s+/g, '_') : '');
            }

            // Create a new field object with all required properties
            const fieldData = {
                id: 'field_' + Date.now(),
                field_type: fieldType,
                field_label: fieldType === 'submit' ? formData.text || 'Submit' : (fieldType === 'html' ? 'HTML Content' : formData.label),
                field_name: fieldName,
                field_required: formData.required || false,
                field_placeholder: formData.placeholder || '',
                field_description: formData.description || '',
                field_options: formData.options || '',
                field_meta: formData.meta || {},
                field_order: this.tempFields.length,
                column_layout: formData.column_layout || 'full'
            };

            // Add specific properties based on field type
            if (fieldType === 'submit') {
                fieldData.text = formData.text || 'Submit';
            } else if (fieldType === 'html') {
                fieldData.content = formData.content || '';
                fieldData.field_content = formData.content || ''; // Add both for compatibility
            }

            console.log('Created field data:', fieldData);

            // Add to temp fields array
            this.tempFields.push(fieldData);

            // Add to preview
            this.addFieldToPreview(fieldData);

            // Close modal
            $modal.remove();
        },

        addFieldToPreview: function(fieldData) {
            console.log('Adding field to preview:', fieldData);
            const columnClass = fieldData.column_layout === 'half' ? 'mavlers-field-half' : 'mavlers-field-full';
            
            // For HTML fields, store the content in data-field-data
            const fieldDataToStore = fieldData.field_type === 'html' 
                ? { ...fieldData, content: fieldData.content || fieldData.field_content }
                : fieldData;

            const $field = $(`
                <div class="mavlers-field ${columnClass}" 
                     data-field-id="${fieldData.id}"
                     data-field-type="${fieldData.field_type}"
                     data-field-data='${JSON.stringify(fieldDataToStore)}'>
                    <div class="mavlers-field-header">
                        <span class="mavlers-field-title">${fieldData.field_label}</span>
                        <div class="mavlers-field-actions">
                            <button type="button" class="mavlers-edit-field">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="mavlers-delete-field">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                            <span class="mavlers-field-move">
                                <span class="dashicons dashicons-move"></span>
                            </span>
                        </div>
                    </div>
                    <div class="mavlers-field-content">
                        ${this.getFieldPreview(fieldData)}
                    </div>
                </div>
            `);

            // Check if we need to create a new row
            const $lastField = $('#form-fields .mavlers-field:last');
            if ($lastField.length && $lastField.hasClass('mavlers-field-half') && columnClass === 'mavlers-field-half') {
                // Add to the same row
                $lastField.after($field);
            } else {
                // Add to a new row
                $('#form-fields').append($field);
            }

            $('.mavlers-empty-form').hide();
        },

        getFieldPreview: function(fieldData) {
            const required = fieldData.field_required ? 'required' : '';
            const placeholder = fieldData.field_placeholder ? `placeholder="${fieldData.field_placeholder}"` : '';
            const cssClass = fieldData.field_css_class ? `class="${fieldData.field_css_class}"` : 'class="widefat"';

            switch (fieldData.field_type) {
                case 'text':
                    return `<input type="text" ${required} ${placeholder} ${cssClass}>`;
                case 'textarea':
                    const rows = fieldData.field_rows || 4;
                    return `<textarea ${required} ${placeholder} ${cssClass} rows="${rows}"></textarea>`;
                case 'checkbox':
                    let checkboxes = '';
                    const options = fieldData.field_options ? fieldData.field_options.split('\n') : [];
                    options.forEach(option => {
                        checkboxes += `
                            <label>
                                <input type="checkbox" name="${fieldData.field_name}[]" value="${option.trim()}" ${required}>
                                ${option.trim()}
                            </label>
                        `;
                    });
                    return checkboxes;
                case 'dropdown':
                    let dropdown = `<select ${required} ${cssClass}>`;
                    const selectOptions = fieldData.field_options ? fieldData.field_options.split('\n') : [];
                    selectOptions.forEach(option => {
                        dropdown += `<option value="${option.trim()}">${option.trim()}</option>`;
                    });
                    dropdown += '</select>';
                    return dropdown;
                case 'number':
                    const min = fieldData.field_min ? `min="${fieldData.field_min}"` : '';
                    const max = fieldData.field_max ? `max="${fieldData.field_max}"` : '';
                    const step = fieldData.field_step ? `step="${fieldData.field_step}"` : '';
                    return `<input type="number" ${required} ${min} ${max} ${step} ${cssClass}>`;
                case 'radio':
                    let radios = '';
                    const radioOptions = fieldData.field_options ? fieldData.field_options.split('\n') : [];
                    radioOptions.forEach(option => {
                        radios += `
                            <label>
                                <input type="radio" name="${fieldData.field_name}" value="${option.trim()}" ${required}>
                                ${option.trim()}
                            </label>
                        `;
                    });
                    return radios;
                case 'hidden':
                    return `<input type="hidden" name="${fieldData.field_name}" value="${fieldData.field_value}">`;
                case 'file':
                    return `<input type="file" ${required} ${cssClass}>`;
                case 'html':
                    return fieldData.content || fieldData.field_content || '';
                case 'divider':
                    if (fieldData.field_type === 'line') {
                        return '<hr>';
                    } else if (fieldData.field_type === 'space') {
                        return '<div style="height: 20px;"></div>';
                    } else {
                        return `<div class="divider-text">${fieldData.field_text}</div>`;
                    }
                case 'section':
                    return `
                        <div class="form-section">
                            <h3>${fieldData.field_title}</h3>
                            ${fieldData.field_description ? `<p>${fieldData.field_description}</p>` : ''}
                        </div>
                    `;
                case 'captcha':
                    if (fieldData.field_type === 'recaptcha') {
                        return '<div class="g-recaptcha" data-sitekey="' + fieldData.field_site_key + '"></div>';
                    } else {
                        return '<div class="simple-captcha">Simple Math CAPTCHA</div>';
                    }
                case 'submit':
                    return `<button type="submit" ${cssClass}>${fieldData.text || fieldData.field_text || 'Submit'}</button>`;
                default:
                    return `<input type="text" ${required} ${placeholder} ${cssClass}>`;
            }
        },

        deleteField: function($field) {
            if (confirm(mavlersFormBuilder.strings.deleteConfirm)) {
                const fieldId = $field.data('field-id');
                
                $.ajax({
                    url: mavlersFormBuilder.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mavlers_delete_field',
                        nonce: mavlersFormBuilder.nonce,
                        form_id: this.formId,
                        field_id: fieldId
                    },
                    success: (response) => {
                        if (response.success) {
                            $field.fadeOut(300, function() {
                                $(this).remove();
                                if ($('#form-fields .mavlers-field').length === 0) {
                                    $('.mavlers-empty-form').show();
                                }
                            });
                            this.showNotification('Field deleted successfully');
                        } else {
                            this.showNotification(response.data || 'Error deleting field', 'error');
                        }
                    }
                });
            }
        },

        updateFieldOrder: function() {
            const fieldOrder = [];
            $('#form-fields .mavlers-field').each(function() {
                fieldOrder.push($(this).data('field-id'));
            });

            // Update temp fields order
            this.tempFields.sort((a, b) => {
                return fieldOrder.indexOf(a.id) - fieldOrder.indexOf(b.id);
            });

            // Update field_order in temp fields
            this.tempFields.forEach((field, index) => {
                field.field_order = index;
            });

            $.ajax({
                url: mavlersFormBuilder.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_update_field_order',
                    nonce: mavlersFormBuilder.nonce,
                    form_id: this.formId,
                    field_order: fieldOrder
                }
            });
        },

        generateFormTitle: function() {
            const $titleInput = $('#form-title');
            if (!$titleInput.val()) {
                const defaultTitle = `Form ${this.tempFields.length + 1}`;
                $titleInput.val(defaultTitle);
            }
        },

        saveForm: function() {
            const formName = $('#form-title').val();
            if (!formName) {
                this.showNotification('Please enter a form title', 'error');
                return;
            }

            // Get all fields from the preview
            const fields = [];
            $('#form-fields .mavlers-field').each((index, element) => {
                const $field = $(element);
                const fieldData = $field.data('field-data');
                console.log('Field data before saving:', fieldData);
                
                if (fieldData) {
                    // Ensure all required field properties are present
                    const processedField = {
                        id: fieldData.id || 'field_' + Date.now(),
                        field_type: fieldData.field_type,
                        field_label: fieldData.field_label,
                        field_name: fieldData.field_name || fieldData.field_label.toLowerCase().replace(/\s+/g, '_'),
                        field_required: fieldData.field_required || false,
                        field_placeholder: fieldData.field_placeholder || '',
                        field_description: fieldData.field_description || '',
                        field_options: fieldData.field_options || '',
                        field_meta: fieldData.field_meta || {},
                        field_order: index,
                        column_layout: fieldData.column_layout || 'full'
                    };
                    console.log('Processed field data:', processedField);
                    fields.push(processedField);
                }
            });

            const formData = {
                title: formName,
                fields: fields
            };

            console.log('Saving form data:', formData);
            console.log('Using nonce:', mavlersFormBuilder.nonce);

            $.ajax({
                url: mavlersFormBuilder.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_save_form',
                    nonce: mavlersFormBuilder.nonce,
                    form_id: this.formId,
                    form_data: formData
                },
                success: (response) => {
                    console.log('Form save response:', response);
                    if (response.success) {
                        // Update form ID if it's a new form
                        if (!this.formId) {
                            this.formId = response.data.form_id;
                            window.location.href = mavlersFormBuilder.adminUrl + '?page=mavlers-forms&form_id=' + response.data.form_id;
                        } else {
                            // Reload the form fields
                            this.loadExistingFields();
                            this.showNotification('Form saved successfully');
                        }
                    } else {
                        console.error('Error saving form:', response.data);
                        this.showNotification(response.data || 'Error saving form', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error saving form:', error);
                    console.error('Response:', xhr.responseText);
                    this.showNotification('Error saving form. Please try again.', 'error');
                }
            });
        },

        previewForm: function() {
            if (!this.formId) {
                alert(mavlersFormBuilder.strings.saveFormFirst);
                return;
            }
            
            window.open(mavlersFormBuilder.previewUrl + '&form_id=' + this.formId, '_blank');
        }
    };

    // Initialize the form builder
    FormBuilder.init();
}); 