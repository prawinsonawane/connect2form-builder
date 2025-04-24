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

        bindEvents: function() {
            console.log('Binding events');
            
            // Field type buttons
            $('.mavlers-field-button').on('click', (e) => {
                console.log('Field button clicked');
                const type = $(e.currentTarget).data('type');
                console.log('Field type:', type);
                this.openFieldSettings(type);
            });

            // Edit field
            $(document).on('click', '.mavlers-edit-field', (e) => {
                console.log('Edit field clicked');
                const $field = $(e.currentTarget).closest('.mavlers-field');
                const fieldData = $field.data('field-data');
                console.log('Field data:', fieldData);
                this.openFieldSettings(fieldData.type, fieldData);
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
        },

        initSortable: function() {
            $('#form-fields').sortable({
                handle: '.mavlers-field-move',
                placeholder: 'mavlers-field-placeholder',
                update: () => this.updateFieldOrder()
            });
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
                            this.tempFields.push(field);
                            this.addFieldToUI(field);
                        });
                        
                        // Show empty state if no fields
                        if (this.tempFields.length === 0) {
                            console.log('No fields found, showing empty state');
                            $('.mavlers-empty-form').addClass('show');
                        }
                    } else {
                        console.error('Failed to load form fields:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                }
            });
        },

        openFieldSettings: function(type, fieldData = null) {
            console.log('Opening field settings for type:', type);
            const title = fieldData ? 'Edit Field' : 'Add Field';
            const settings = this.getFieldSettings(type, fieldData);
            
            // Remove any existing modal
            $('.mavlers-modal').remove();
            
            const modal = `
                <div class="mavlers-modal show">
                    <div class="mavlers-modal-content">
                        <div class="mavlers-modal-header">
                            <h3>${title}</h3>
                            <button type="button" class="mavlers-modal-close">&times;</button>
                        </div>
                        <div class="mavlers-modal-body">
                            <form class="mavlers-field-settings-form">
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

            $('body').append(modal);
            this.bindModalEvents(fieldData);
        },

        getFieldSettings: function(type, fieldData = null) {
            const settings = {
                text: `
                    <div class="mavlers-field-setting">
                        <label for="field-label">Label</label>
                        <input type="text" id="field-label" name="label" value="${fieldData?.label || ''}" required>
                    </div>
                    <div class="mavlers-field-setting">
                        <label for="field-required">
                            <input type="checkbox" id="field-required" name="required" ${fieldData?.required ? 'checked' : ''}>
                            Required
                        </label>
                    </div>
                    <div class="mavlers-field-setting">
                        <label for="field-placeholder">Placeholder</label>
                        <input type="text" id="field-placeholder" name="placeholder" value="${fieldData?.placeholder || ''}">
                    </div>
                `,
                email: `
                    <div class="mavlers-field-setting">
                        <label for="field-label">Label</label>
                        <input type="text" id="field-label" name="label" value="${fieldData?.label || ''}" required>
                    </div>
                    <div class="mavlers-field-setting">
                        <label for="field-required">
                            <input type="checkbox" id="field-required" name="required" ${fieldData?.required ? 'checked' : ''}>
                            Required
                        </label>
                    </div>
                    <div class="mavlers-field-setting">
                        <label for="field-placeholder">Placeholder</label>
                        <input type="text" id="field-placeholder" name="placeholder" value="${fieldData?.placeholder || ''}">
                    </div>
                `,
                textarea: `
                    <div class="mavlers-field-setting">
                        <label for="field-label">Label</label>
                        <input type="text" id="field-label" name="label" value="${fieldData?.label || ''}" required>
                    </div>
                    <div class="mavlers-field-setting">
                        <label for="field-required">
                            <input type="checkbox" id="field-required" name="required" ${fieldData?.required ? 'checked' : ''}>
                            Required
                        </label>
                    </div>
                    <div class="mavlers-field-setting">
                        <label for="field-placeholder">Placeholder</label>
                        <input type="text" id="field-placeholder" name="placeholder" value="${fieldData?.placeholder || ''}">
                    </div>
                    <div class="mavlers-field-setting">
                        <label for="field-rows">Rows</label>
                        <input type="number" id="field-rows" name="rows" value="${fieldData?.rows || 4}" min="1">
                    </div>
                `
            };

            return settings[type] || settings.text;
        },

        bindModalEvents: function(fieldData = null) {
            console.log('Binding modal events');
            const $modal = $('.mavlers-modal');
            const $form = $('.mavlers-field-settings-form');

            $('.mavlers-modal-close, .mavlers-modal-cancel').on('click', () => {
                console.log('Modal close clicked');
                $modal.remove();
            });

            $('#save-field-settings').on('click', () => {
                console.log('Save field settings clicked');
                const formData = {};
                $form.find('input, select, textarea').each(function() {
                    const $input = $(this);
                    const name = $input.attr('name');
                    const value = $input.is(':checkbox') ? $input.is(':checked') : $input.val();
                    formData[name] = value;
                });

                formData.type = fieldData?.type || $('.mavlers-field-button.active').data('type');
                formData.id = fieldData?.id;

                console.log('Saving field data:', formData);
                this.saveField(formData);
                $modal.remove();
            });
        },

        saveField: function(fieldData) {
            if (fieldData.id) {
                // Update existing field
                const index = this.tempFields.findIndex(f => f.id === fieldData.id);
                if (index !== -1) {
                    this.tempFields[index] = fieldData;
                    this.updateFieldInUI(fieldData);
                }
            } else {
                // Add new field
                fieldData.id = 'temp_' + Date.now();
                this.tempFields.push(fieldData);
                this.addFieldToUI(fieldData);
            }
        },

        addFieldToUI: function(fieldData) {
            console.log('Adding field to UI:', fieldData);
            
            const $field = $(`
                <div class="mavlers-field" data-field-id="${fieldData.id}" data-field-data='${JSON.stringify(fieldData)}'>
                    <div class="mavlers-field-header">
                        <span class="mavlers-field-title">${fieldData.label}</span>
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

            $('#form-fields').append($field);
            $('.mavlers-empty-form').removeClass('show');
        },

        updateFieldInUI: function(fieldData) {
            const $field = $(`#form-fields .mavlers-field[data-field-id="${fieldData.id}"]`);
            if ($field.length) {
                $field
                    .data('field-data', fieldData)
                    .find('.mavlers-field-title')
                    .text(fieldData.label);
                
                $field.find('.mavlers-field-content').html(this.getFieldPreview(fieldData));
            }
        },

        getFieldPreview: function(fieldData) {
            const required = fieldData.required ? 'required' : '';
            const placeholder = fieldData.placeholder ? `placeholder="${fieldData.placeholder}"` : '';

            switch (fieldData.type) {
                case 'text':
                    return `<input type="text" ${required} ${placeholder} class="widefat">`;
                case 'email':
                    return `<input type="email" ${required} ${placeholder} class="widefat">`;
                case 'textarea':
                    const rows = fieldData.rows || 4;
                    return `<textarea ${required} ${placeholder} class="widefat" rows="${rows}"></textarea>`;
                default:
                    return `<input type="text" ${required} ${placeholder} class="widefat">`;
            }
        },

        deleteField: function($field) {
            if (confirm('Are you sure you want to delete this field?')) {
                const fieldId = $field.data('field-id');
                this.tempFields = this.tempFields.filter(f => f.id !== fieldId);
                $field.fadeOut(300, function() {
                    $(this).remove();
                    if ($('#form-fields .mavlers-field').length === 0) {
                        $('.mavlers-empty-form').addClass('show');
                    }
                });
            }
        },

        updateFieldOrder: function() {
            const fieldOrder = [];
            $('#form-fields .mavlers-field').each(function() {
                fieldOrder.push($(this).data('field-id'));
            });

            $.ajax({
                url: ajaxurl,
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
            const formId = $('#form-id').val();
            const formTitle = $('#form-title').val();
            const fields = [];

            // Collect all fields from the preview area
         // Collect all fields from the preview area
         $('#form-fields .mavlers-field').each(function(index) {
            const field = $(this).data('field-data');
            if (field) {
                fields.push({
                    id: field.id,
                    type: field.type,
                    label: field.label,
                    required: field.required || false,
                    placeholder: field.placeholder || '',
                    options: field.options || []
                });
            }
        });
        console.log('Collected fields before saving:', fields);
        

            const formData = {
                action: 'mavlers_save_form',
                nonce: mavlersFormBuilder.nonce,
                form_data: {
                    id: formId,
                    title: formTitle,
                    fields: fields
                }
            };

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        if (!formId) {
                            $('#form-id').val(response.data.form_id);
                        }
                        alert('Form saved successfully!');
                        console.log(formData);
                    } else {
                        alert('Error saving form: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error saving form: ' + error);
                }
            });
        },

        previewForm: function() {
            if (!this.formId) {
                alert('Please save the form first');
                return;
            }
            window.open(`${mavlersFormBuilder.previewUrl}?form_id=${this.formId}`, '_blank');
        }
    };

    // Initialize the form builder
    FormBuilder.init();
}); 