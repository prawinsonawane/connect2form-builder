jQuery(document).ready(function($) {
    // Initialize all forms
    $('.mavlers-form').each(function() {
        initForm($(this));
    });
    
    function initForm($form) {
        const formId = $form.data('form-id');
        
        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm($form)) {
                return;
            }
            
            submitForm($form);
        });
        
        // Handle file upload preview
        $form.find('input[type="file"]').on('change', function() {
            const $field = $(this).closest('.mavlers-form-field');
            const $preview = $field.find('.mavlers-file-preview');
            const files = this.files;
            
            if (files.length > 0) {
                let previewHtml = '<div class="mavlers-file-list">';
                
                for (let i = 0; i < files.length; i++) {
                    previewHtml += `
                        <div class="mavlers-file-item">
                            <span class="mavlers-file-name">${files[i].name}</span>
                            <span class="mavlers-file-size">${formatFileSize(files[i].size)}</span>
                        </div>
                    `;
                }
                
                previewHtml += '</div>';
                $preview.html(previewHtml);
            } else {
                $preview.empty();
            }
        });
        
        // Handle dynamic field validation
        $form.find('.mavlers-field-input').on('blur', function() {
            validateField($(this));
        });
    }
    
    function validateForm($form) {
        let isValid = true;
        const $fields = $form.find('.mavlers-form-field');
        const $firstError = $form.find('.mavlers-field-error:visible').first();
        
        // Clear all errors first
        $fields.find('.mavlers-field-error').empty();
        $fields.find('.mavlers-field-input').removeClass('error');
        
        $fields.each(function() {
            const $field = $(this);
            const $input = $field.find('.mavlers-field-input');
            const isRequired = $field.hasClass('required');
            
            if (isRequired && !validateField($input)) {
                isValid = false;
                if (!$firstError.length) {
                    scrollToField($input);
                }
            }
        });
        
        if (!isValid) {
            showFormError($form, 'Please correct the errors below');
        }
        
        return isValid;
    }
    
    function validateField($input) {
        const $field = $input.closest('.mavlers-form-field');
        const $error = $field.find('.mavlers-field-error');
        const value = $input.val();
        const type = $input.attr('type');
        const validation = $input.data('validation');
        const pattern = $input.data('pattern');
        const minLength = $input.data('min-length');
        const maxLength = $input.data('max-length');
        const minValue = $input.data('min');
        const maxValue = $input.data('max');
        
        // Clear previous error
        $error.empty();
        $input.removeClass('error');
        
        // Required field validation
        if ($field.hasClass('required') && !value) {
            showError($input, $error, 'This field is required');
            return false;
        }
        
        if (!value) {
            return true; // Skip further validation if field is empty and not required
        }
        
        // Length validation
        if (minLength && value.length < minLength) {
            showError($input, $error, `Minimum length is ${minLength} characters`);
            return false;
        }
        if (maxLength && value.length > maxLength) {
            showError($input, $error, `Maximum length is ${maxLength} characters`);
            return false;
        }
        
        // Numeric value validation
        if (type === 'number' || type === 'range') {
            const numValue = parseFloat(value);
            if (isNaN(numValue)) {
                showError($input, $error, 'Please enter a valid number');
                return false;
            }
            if (minValue !== undefined && numValue < minValue) {
                showError($input, $error, `Minimum value is ${minValue}`);
                return false;
            }
            if (maxValue !== undefined && numValue > maxValue) {
                showError($input, $error, `Maximum value is ${maxValue}`);
                return false;
            }
        }
        
        // Type-specific validation
        switch (type) {
            case 'email':
                if (!isValidEmail(value)) {
                    showError($input, $error, 'Please enter a valid email address');
                    return false;
                }
                break;
                
            case 'url':
                if (!isValidUrl(value)) {
                    showError($input, $error, 'Please enter a valid URL');
                    return false;
                }
                break;
                
            case 'tel':
                if (!isValidPhone(value)) {
                    showError($input, $error, 'Please enter a valid phone number');
                    return false;
                }
                break;
        }
        
        // Custom validation
        if (validation === 'custom' && pattern) {
            const regex = new RegExp(pattern);
            if (!regex.test(value)) {
                showError($input, $error, 'Please enter a valid value');
                return false;
            }
        }
        
        // File validation
        if (type === 'file') {
            const maxSize = $input.data('max-size');
            const allowedTypes = $input.data('allowed-types').split(',');
            const files = $input[0].files;
            
            if (files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    
                    // Check file size
                    if (maxSize && file.size > maxSize * 1024 * 1024) {
                        showError($input, $error, `File size must be less than ${maxSize}MB`);
                        return false;
                    }
                    
                    // Check file type
                    const fileType = file.name.split('.').pop().toLowerCase();
                    if (!allowedTypes.includes(fileType)) {
                        showError($input, $error, `File type not allowed. Allowed types: ${allowedTypes.join(', ')}`);
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
    
    function showFormError($form, message) {
        const $error = $form.find('.mavlers-form-error');
        $error.html(message).show();
    }
    
    function scrollToField($input) {
        $('html, body').animate({
            scrollTop: $input.offset().top - 100
        }, 500);
    }
    
    function showError($input, $error, message) {
        $input.addClass('error');
        $error.html(message);
    }
    
    function submitForm($form) {
        const formId = $form.data('form-id');
        const $submit = $form.find('.mavlers-form-submit');
        const $success = $form.find('.mavlers-form-success');
        const $error = $form.find('.mavlers-form-error');
        
        // Disable submit button and show loading state
        $submit.prop('disabled', true);
        $form.addClass('mavlers-form-loading');
        
        // Clear previous messages
        $success.hide();
        $error.hide();
        
        // Prepare form data
        const formData = new FormData($form[0]);
        formData.append('action', 'mavlers_submit_form');
        formData.append('form_id', formId);
        formData.append('nonce', mavlersForms.nonce);
        
        // Submit form
        $.ajax({
            url: mavlersForms.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $success.html(response.data.message).show();
                    $form[0].reset();
                    
                    // Hide success message after 5 seconds
                    setTimeout(function() {
                        $success.fadeOut();
                    }, 5000);
                    
                    // Track form submission in analytics
                    if (typeof mavlersForms.trackSubmission === 'function') {
                        mavlersForms.trackSubmission(formId);
                    }
                } else {
                    // Show error message
                    showFormError($form, response.data);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Failed to submit form. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                showFormError($form, errorMessage);
            },
            complete: function() {
                // Re-enable submit button and remove loading state
                $submit.prop('disabled', false);
                $form.removeClass('mavlers-form-loading');
            }
        });
    }
    
    // Utility functions
    function isValidEmail(email) {
        const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return re.test(email);
    }
    
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }
    
    function isValidPhone(phone) {
        const re = /^\+?[\d\s-()]+$/;
        return re.test(phone);
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}); 