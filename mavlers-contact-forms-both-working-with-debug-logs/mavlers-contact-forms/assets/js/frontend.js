jQuery(document).ready(function($) {
    // Initialize date pickers
    $('.mavlers-cf-datepicker').each(function() {
        var $input = $(this);
        var format = $input.data('format');
        
        $input.datepicker({
            dateFormat: format,
            changeMonth: true,
            changeYear: true
        });
    });

    // Handle form submission
    $('.mavlers-cf-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submit = $form.find('.mavlers-cf-submit');
        var $message = $form.find('.mavlers-cf-message');

        // Disable submit button
        $submit.prop('disabled', true);

        // Clear previous messages
        $message.removeClass('success error').hide();

        // Validate form
        if (!validateForm($form)) {
            $submit.prop('disabled', false);
            return;
        }

        // Prepare form data
        var formData = new FormData($form[0]);
        formData.append('action', 'mavlers_cf_submit');
        formData.append('nonce', mavlersCF.nonce);

        // Handle reCAPTCHA for v3
        var recaptchaType = mavlersCF.recaptchaType;
        
        if (recaptchaType === 'v3') {
            // reCAPTCHA v3 - execute before form submission
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(mavlersCF.recaptchaSiteKey, {action: 'submit'}).then(function(token) {
                        console.log('Mavlers CF: reCAPTCHA v3 token received:', token.substring(0, 20) + '...');
                        
                        // Add token to form data
                        formData.append('g-recaptcha-response', token);
                        
                        // Continue with form submission
                        submitForm(formData, $form, $message, $submit);
                    }).catch(function(error) {
                        console.error('Mavlers CF: reCAPTCHA v3 execution failed:', error);
                        showMessage($message, 'reCAPTCHA verification failed. Please try again.', 'error');
                        $submit.prop('disabled', false);
                    });
                });
            } else {
                console.error('Mavlers CF: reCAPTCHA v3 not loaded');
                showMessage($message, 'reCAPTCHA not loaded. Please refresh the page and try again.', 'error');
                $submit.prop('disabled', false);
            }
        } else {
            // reCAPTCHA v2 - check if completed
            var recaptchaResponse = grecaptcha.getResponse();
            if (!recaptchaResponse) {
                showMessage($message, 'Please complete the reCAPTCHA verification.', 'error');
                $submit.prop('disabled', false);
                return;
            }
            
            console.log('Mavlers CF: reCAPTCHA v2 response received:', recaptchaResponse.substring(0, 20) + '...');
            formData.append('g-recaptcha-response', recaptchaResponse);
            submitForm(formData, $form, $message, $submit);
        }
    });

    // Form validation
    function validateForm($form) {
        var isValid = true;
        var $message = $form.find('.mavlers-cf-message');

        // Clear previous messages
        $message.removeClass('success error').hide();

        // Validate required fields
        $form.find('[required]').each(function() {
            var $field = $(this);
            var value = $field.val();
            var type = $field.attr('type');

            if (!value) {
                isValid = false;
                showFieldError($field, 'This field is required.');
            } else {
                clearFieldError($field);

                // Validate field type
                switch (type) {
                    case 'email':
                        if (!isValidEmail(value)) {
                            isValid = false;
                            showFieldError($field, 'Please enter a valid email address.');
                        }
                        break;

                    case 'number':
                        var min = $field.attr('min');
                        var max = $field.attr('max');
                        var num = parseFloat(value);

                        if (isNaN(num)) {
                            isValid = false;
                            showFieldError($field, 'Please enter a valid number.');
                        } else if (min && num < parseFloat(min)) {
                            isValid = false;
                            showFieldError($field, 'Value must be at least ' + min + '.');
                        } else if (max && num > parseFloat(max)) {
                            isValid = false;
                            showFieldError($field, 'Value must be at most ' + max + '.');
                        }
                        break;

                    case 'file':
                        var files = $field[0].files;
                        var maxSize = $field.data('max-size');
                        var allowedTypes = $field.data('allowed-types').split(',');

                        if (files.length > 0) {
                            for (var i = 0; i < files.length; i++) {
                                var file = files[i];
                                var ext = file.name.split('.').pop().toLowerCase();

                                if (allowedTypes.indexOf(ext) === -1) {
                                    isValid = false;
                                    showFieldError($field, 'Invalid file type. Allowed types: ' + allowedTypes.join(', '));
                                    break;
                                }

                                if (maxSize && file.size > maxSize * 1024 * 1024) {
                                    isValid = false;
                                    showFieldError($field, 'File size must be less than ' + maxSize + 'MB.');
                                    break;
                                }
                            }
                        }
                        break;
                }
            }
        });

        // Validate CAPTCHA (only if required)
        var $captcha = $form.find('.g-recaptcha');
        if ($captcha.length) {
            // Check if the CAPTCHA field is required by looking at the field container
            var $captchaContainer = $captcha.closest('.mavlers-cf-field');
            var isCaptchaRequired = $captchaContainer.hasClass('required') || 
                                   $captchaContainer.find('.required').length > 0 ||
                                   $captchaContainer.data('required') === true;
            
            if (isCaptchaRequired) {
                if (typeof grecaptcha === 'undefined') {
                    isValid = false;
                    showMessage($message, 'reCAPTCHA is not loaded. Please refresh the page and try again.', 'error');
                } else {
                    var captchaResponse = grecaptcha.getResponse();
                    if (!captchaResponse) {
                        isValid = false;
                        showMessage($message, 'Please complete the CAPTCHA.', 'error');
                    }
                }
            }
        }

        return isValid;
    }

    // Helper functions
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function showFieldError($field, message) {
        $field.addClass('error');
        if (!$field.next('.field-error').length) {
            $field.after('<div class="field-error">' + message + '</div>');
        }
    }

    function clearFieldError($field) {
        $field.removeClass('error');
        $field.next('.field-error').remove();
    }

    function showMessage($message, text, type) {
        $message
            .removeClass('success error')
            .addClass(type)
            .html(text)
            .show();
    }

    // Handle file upload preview
    $('input[type="file"]').on('change', function() {
        var $input = $(this);
        var $preview = $input.siblings('.file-preview');
        
        if (!$preview.length) {
            $preview = $('<div class="file-preview"></div>').insertAfter($input);
        }

        $preview.empty();

        if (this.files && this.files.length > 0) {
            for (var i = 0; i < this.files.length; i++) {
                var file = this.files[i];
                var reader = new FileReader();

                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        $preview.append('<img src="' + e.target.result + '" alt="Preview">');
                    } else {
                        $preview.append('<div class="file-name">' + file.name + '</div>');
                    }
                };

                reader.readAsDataURL(file);
            }
        }
    });

    // Handle image choice selection
    $('.mavlers-cf-image-choice input[type="radio"]').on('change', function() {
        var $choice = $(this).closest('.mavlers-cf-image-choice');
        $choice.siblings().removeClass('selected');
        $choice.addClass('selected');
    });

    // Function to submit form data
    function submitForm(formData, $form, $message, $submit) {
        $.ajax({
            url: mavlersCF.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                console.log('Mavlers CF: Submitting form...');
            },
            success: function(response) {
                console.log('Mavlers CF: Form submission response:', response);
                if (response.success) {
                    showMessage($message, response.data.message, 'success');
                    $form[0].reset();
                    
                    // Handle redirect if specified
                    if (response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000); // 1 second delay to show the message
                    }
                } else {
                    showMessage($message, response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('Mavlers CF: Form submission error:', {xhr: xhr, status: status, error: error});
                showMessage($message, 'An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $submit.prop('disabled', false);
            }
        });
    }
}); 