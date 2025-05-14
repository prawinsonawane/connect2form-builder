jQuery(document).ready(function($) {
    'use strict';

    const FormHandler = {
        init: function() {
            this.bindEvents();
            this.initValidation();
        },

        bindEvents: function() {
            $('.mavlers-form').on('submit', this.handleSubmit.bind(this));
            $('.mavlers-form input, .mavlers-form textarea, .mavlers-form select').on('change', this.validateField.bind(this));
        },

        initValidation: function() {
            // Add validation rules
            $.validator.addMethod('maxChars', function(value, element, param) {
                return value.length <= param;
            }, 'Maximum characters exceeded');

            $.validator.addMethod('fileSize', function(value, element, param) {
                if (element.files.length === 0) return true;
                return element.files[0].size <= param;
            }, 'File size exceeds the limit');

            $.validator.addMethod('fileType', function(value, element, param) {
                if (element.files.length === 0) return true;
                const file = element.files[0];
                const allowedTypes = param.split(',');
                return allowedTypes.includes(file.type);
            }, 'File type not allowed');
        },

        handleSubmit: function(e) {
            e.preventDefault();
            const $form = $(e.target);
            const $submitButton = $form.find('button[type="submit"]');
            const $messages = $form.find('.mavlers-form-messages');

            // Disable submit button
            $submitButton.prop('disabled', true);

            // Validate form
            if (!this.validateForm($form)) {
                $submitButton.prop('disabled', false);
                return;
            }

            // Handle file uploads
            const formData = new FormData($form[0]);

            // Add AJAX action
            formData.append('action', 'mavlers_form_submit');

            // Send AJAX request
            $.ajax({
                url: mavlersForm.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $messages
                            .removeClass('error')
                            .addClass('success')
                            .html(response.data.message)
                            .show();
                        $form[0].reset();
                    } else {
                        $messages
                            .removeClass('success')
                            .addClass('error')
                            .html(response.data.message)
                            .show();
                    }
                },
                error: function() {
                    $messages
                        .removeClass('success')
                        .addClass('error')
                        .html('An error occurred. Please try again.')
                        .show();
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                }
            });
        },

        validateForm: function($form) {
            let isValid = true;
            const $fields = $form.find('input, textarea, select');

            $fields.each(function() {
                if (!FormHandler.validateField.call(FormHandler, { target: this })) {
                    isValid = false;
                }
            });

            return isValid;
        },

        validateField: function(e) {
            const $field = $(e.target);
            const $wrapper = $field.closest('.mavlers-field-wrapper');
            const validation = $field.data('validation');
            let isValid = true;
            let errorMessage = '';

            // Remove existing error
            $wrapper.find('.field-error').remove();
            $field.removeClass('error');

            // Skip validation for hidden fields
            if ($field.is(':hidden')) {
                return true;
            }

            // Required field validation
            if ($field.prop('required') && !$field.val()) {
                isValid = false;
                errorMessage = 'This field is required';
            }

            // Custom validation rules
            if (validation && isValid) {
                if (validation.maxChars && $field.val().length > validation.maxChars) {
                    isValid = false;
                    errorMessage = `Maximum ${validation.maxChars} characters allowed`;
                }

                if (validation.min && parseFloat($field.val()) < validation.min) {
                    isValid = false;
                    errorMessage = `Minimum value is ${validation.min}`;
                }

                if (validation.max && parseFloat($field.val()) > validation.max) {
                    isValid = false;
                    errorMessage = `Maximum value is ${validation.max}`;
                }

                if (validation.pattern && !new RegExp(validation.pattern).test($field.val())) {
                    isValid = false;
                    errorMessage = validation.message || 'Invalid format';
                }
            }

            // File validation
            if ($field.attr('type') === 'file' && $field[0].files.length > 0) {
                const file = $field[0].files[0];
                const maxSize = $field.data('max-size');
                const allowedTypes = $field.attr('accept');

                if (maxSize && file.size > maxSize) {
                    isValid = false;
                    errorMessage = `File size must be less than ${this.formatFileSize(maxSize)}`;
                }

                if (allowedTypes && !this.isValidFileType(file, allowedTypes)) {
                    isValid = false;
                    errorMessage = 'Invalid file type';
                }
            }

            // Show error if invalid
            if (!isValid) {
                $field.addClass('error');
                $wrapper.append(`<div class="field-error">${errorMessage}</div>`);
            }

            return isValid;
        },

        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        isValidFileType: function(file, allowedTypes) {
            const types = allowedTypes.split(',').map(type => type.trim());
            return types.some(type => {
                if (type.startsWith('.')) {
                    return file.name.toLowerCase().endsWith(type.toLowerCase());
                }
                return file.type === type;
            });
        }
    };

    // Initialize form handler
    FormHandler.init();
}); 