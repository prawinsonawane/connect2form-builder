jQuery(document).ready(function($) {
    console.log('Mavlers CF: Frontend JavaScript loaded');
    console.log('Mavlers CF: mavlersCF object:', mavlersCF);
    
    // Check if forms exist
    var $forms = $('.mavlers-cf-form');
    console.log('Mavlers CF: Found ' + $forms.length + ' forms on page');
    
    $forms.each(function(index) {
        console.log('Mavlers CF: Form ' + index + ':', this);
    });

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

    //Custom selectBox
    $('.mavlers-cf-field-select select').each(function() {
        var optionsLength = $(this).children('option').length;
        
        $(this).addClass('hidden');
        $(this).wrap('<div class="custom-select"> </div');
        $(this).after('<div class="selected-option"></div>')
        
        var selectedOption = $(this).next('div.selected-option');
        selectedOption.text($(this).children('option').eq(0).text());
        
        var optionsContainer = $('<ul></ul>', {'class': 'options-list'}).insertAfter(selectedOption);
        
        for(var i = 0; i < optionsLength; i++) {
            var optionTemp = $(this).children('option').eq(i);
            
            $('<li />', {
                        'class': 'option-item', 
                        text: optionTemp.text(), 
                        rel: optionTemp.val() 
            }).appendTo(optionsContainer);
        }
        
        var optionsList = optionsContainer.children('li');
        
        selectedOption.click(function(e) {
            e.stopPropagation();
            
            $('div.selected-option.active').not(this).each(function(){
            $(this).removeClass('active').next('ul.options-list').hide();
            });
            
            $(this).toggleClass('active').next('ul.options-list').toggle();
        });
        
        optionsList.click(function(e) {
            e.stopPropagation();
            
            selectedOption.text($(this).text()).removeClass('active');
            $(this).val($(this).attr('rel'));
            optionsContainer.hide();
        })
        
        $(document).click(function(){
            selectedOption.removeClass('active');
            optionsContainer.hide();
        })
    })

    // Handle form submission
    $('.mavlers-cf-form').on('submit', function(e) {
        console.log('Mavlers CF: Form submit event triggered');
        e.preventDefault();
        var $form = $(this);
        var $submit = $form.find('.mavlers-cf-submit');
        var $message = $form.find('.mavlers-cf-message');

        console.log('Mavlers CF: Form elements found - Submit button:', $submit.length, 'Message container:', $message.length);

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

        console.log('Mavlers CF: Form data prepared, nonce:', mavlersCF.nonce);

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
        } else if (recaptchaType === 'v2_checkbox' || recaptchaType === 'v2_invisible') {
            // reCAPTCHA v2 - check if completed
            if (typeof grecaptcha !== 'undefined') {
                var recaptchaResponse = grecaptcha.getResponse();
                if (!recaptchaResponse) {
                    showMessage($message, 'Please complete the reCAPTCHA verification.', 'error');
                    $submit.prop('disabled', false);
                    return;
                }
                
                console.log('Mavlers CF: reCAPTCHA v2 response received:', recaptchaResponse.substring(0, 20) + '...');
                formData.append('g-recaptcha-response', recaptchaResponse);
                submitForm(formData, $form, $message, $submit);
            } else {
                console.log('Mavlers CF: reCAPTCHA v2 not loaded, proceeding without captcha');
                submitForm(formData, $form, $message, $submit);
            }
        } else {
            // No reCAPTCHA configured, proceed with form submission
            console.log('Mavlers CF: No reCAPTCHA configured, proceeding with form submission');
            submitForm(formData, $form, $message, $submit);
        }
    });

    // Form validation
    function validateForm($form) {
        console.log('Mavlers CF: Starting form validation');
        var isValid = true;
        var $message = $form.find('.mavlers-cf-message');

        // Clear previous messages
        $message.removeClass('success error').hide();

        // Validate required fields
        $form.find('[required]').each(function() {
            var $field = $(this);
            var value = $field.val();
            var type = $field.attr('type');

            console.log('Mavlers CF: Validating field:', $field.attr('name'), 'Value:', value, 'Type:', type);

            if (!value) {
                isValid = false;
                showFieldError($field, 'This field is required.');
                console.log('Mavlers CF: Field validation failed - required field empty');
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

    // Test submit button click
    $('.mavlers-cf-submit').on('click', function(e) {
        console.log('Mavlers CF: Submit button clicked');
    });

    // Function to submit form data
    function submitForm(formData, $form, $message, $submit) {
        console.log('Mavlers CF: Starting AJAX submission');
        console.log('Mavlers CF: Form data entries:', formData.entries ? formData.entries().length : 'No entries method');
        console.log('Mavlers CF: AJAX URL:', mavlersCF.ajaxurl);
        
        // Log form data contents
        if (formData.entries) {
            for (var pair of formData.entries()) {
                console.log('Mavlers CF: Form data -', pair[0] + ':', pair[1]);
            }
        }
        
        $.ajax({
            url: mavlersCF.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                console.log('Mavlers CF: AJAX request starting...');
                console.log('Mavlers CF: Request headers:', xhr.getAllResponseHeaders());
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
                console.log('Mavlers CF: Response text:', xhr.responseText);
                console.log('Mavlers CF: Response status:', xhr.status);
                console.log('Mavlers CF: Response headers:', xhr.getAllResponseHeaders());
                showMessage($message, 'An error occurred. Please try again.', 'error');
            },
            complete: function() {
                console.log('Mavlers CF: AJAX request completed');
                $submit.prop('disabled', false);
            }
        });
    }
}); 