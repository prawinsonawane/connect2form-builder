jQuery(document).ready(function($) {
    // Check if datepicker is available
    if (typeof $.fn.datepicker === 'undefined') {
        console.error('Datepicker library not loaded!');
        return;
    }
    
    // Initialize date pickers for DD-MM-YYYY format
    $('.connect2form-datepicker').each(function() {
        var $input = $(this);
        var format = $input.data('format') || 'dd-mm-yyyy'; // Default to DD-MM-YYYY format
        
        // Initialize datepicker
        try {
            $input.datepicker({
                format: format,
                autoHide: true,
                autoShow: false, // Don't show calendar automatically on load
                weekStart: 1, // Monday start
                pick: function(e) {
                    // console.log('Datepicker pick function triggered');
                    // Let autoHide handle the closing
                    // console.log('Letting autoHide handle the closing');
                    // Also try manual hide as fallback
                    setTimeout(function() {
                        $('.datepicker-dropdown').hide();
                        $('.datepicker-container').hide();
                        $('.datepicker-panel').addClass('datepicker-hidden');
                    }, 50);
                },
                hide: function(e) {
                    // console.log('Datepicker hide function triggered');
                }
            });
            
            // Ensure datepicker shows when input is clicked/focused
            $input.on('focus click', function(e) {
                // console.log('Date input clicked/focused, showing datepicker');
                // Remove any hidden classes first
                $('.datepicker-panel').removeClass('datepicker-hidden');
                $('.datepicker-dropdown').show();
                $('.datepicker-container').show();
                $input.datepicker('show');
            });
            
        } catch (error) {
            console.error('Error initializing datepicker:', error);
        }
        
        // Close datepicker when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.datepicker-dropdown, .connect2form-datepicker').length) {
                $input.datepicker('hide');
            }
        });
        
        // Add manual input formatting for typing (fallback)
        $input.on('input', function() {
            var value = $input.val().replace(/\D/g, ''); // Remove non-digits
            
            if (value.length > 0) {
                // Format as DD-MM-YYYY
                if (value.length <= 2) {
                    value = value;
                } else if (value.length <= 4) {
                    value = value.substring(0, 2) + '-' + value.substring(2);
                } else if (value.length <= 8) {
                    value = value.substring(0, 2) + '-' + value.substring(2, 4) + '-' + value.substring(4);
                } else {
                    value = value.substring(0, 2) + '-' + value.substring(2, 4) + '-' + value.substring(4, 8);
                }
            }
            
            $input.val(value);
        });
    });



    //Custom selectBox
    $('.connect2form-field-select select').each(function() {
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
    $('.connect2form-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submit = $form.find('.connect2form-submit');
        var $message = $form.find('.connect2form-message');

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

      formData.append('action', 'connect2form_submit');

      formData.append('nonce', connect2formCF.nonce);



      // Check if form has captcha field before processing reCAPTCHA

      var $captchaField = $form.find('.g-recaptcha, [data-type="captcha"]');

      var hasCaptcha = $captchaField.length > 0;

      

      // Handle reCAPTCHA only if form has captcha field

      var recaptchaType = connect2formCF.recaptchaType;

      

      if (hasCaptcha && recaptchaType === 'v3') {

          // reCAPTCHA v3 - execute before form submission

          if (typeof grecaptcha !== 'undefined') {

              grecaptcha.ready(function() {

                  grecaptcha.execute(connect2formCF.recaptchaSiteKey, {action: 'submit'}).then(function(token) {



                      

                      // Add token to form data

                      formData.append('g-recaptcha-response', token);

                      

                      // Continue with form submission

                      submitForm(formData, $form, $message, $submit);

                  }).catch(function(error) {

                      console.error('Connect2Form: reCAPTCHA v3 execution failed:', error);

                      showMessage($message, 'reCAPTCHA verification failed. Please try again.', 'error');

                      $submit.prop('disabled', false);

                  });

              });

          } else {

              console.error('Connect2Form: reCAPTCHA v3 not loaded');

              showMessage($message, 'reCAPTCHA not loaded. Please refresh the page and try again.', 'error');

              $submit.prop('disabled', false);

          }

      } else if (hasCaptcha && (recaptchaType === 'v2_checkbox' || recaptchaType === 'v2_invisible')) {

          // reCAPTCHA v2 - check if completed

          if (typeof grecaptcha !== 'undefined') {

              var recaptchaResponse = grecaptcha.getResponse();

              if (!recaptchaResponse) {

                  showMessage($message, 'Please complete the reCAPTCHA verification.', 'error');

                  $submit.prop('disabled', false);

                  return;

              }

              



              formData.append('g-recaptcha-response', recaptchaResponse);

              submitForm(formData, $form, $message, $submit);

          } else {

              console.error('Connect2Form: reCAPTCHA v2 not loaded');

              showMessage($message, 'reCAPTCHA not loaded. Please refresh the page and try again.', 'error');

              $submit.prop('disabled', false);

          }

      } else {

          // No captcha field or unsupported type - proceed with normal submission

          submitForm(formData, $form, $message, $submit);

      }

  });

    // Form validation
    function validateForm($form) {
        var isValid = true;
        var $message = $form.find('.connect2form-message');

        // Clear previous messages
        $message.removeClass('success error').hide();

        // Validate required fields
        $form.find('[required]').each(function() {
            var $field = $(this);
            var value = $field.val();
            var type = $field.attr('type');
            var fieldName = $field.attr('name');

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

                    case 'date':
                    case 'text':
                        // Check if this is a date field (has date pattern or datepicker class)
                        if ($field.hasClass('connect2form-datepicker') || $field.attr('pattern') === '\\d{2}-\\d{2}-\\d{4}') {
                            // Validate date format DD-MM-YYYY
                            if (value) {
                                var dateRegex = /^\d{2}-\d{2}-\d{4}$/;
                                if (!dateRegex.test(value)) {
                                    isValid = false;
                                    showFieldError($field, 'Please enter a valid date in DD-MM-YYYY format.');
                                } else {
                                    // Parse DD-MM-YYYY format
                                    var parts = value.split('-');
                                    var day = parseInt(parts[0], 10);
                                    var month = parseInt(parts[1], 10) - 1; // Month is 0-indexed in JavaScript
                                    var year = parseInt(parts[2], 10);
                                    
                                    var selectedDate = new Date(year, month, day);
                                    
                                    // Check if the parsed date is valid (handles leap years, etc.)
                                    if (selectedDate.getDate() !== day || selectedDate.getMonth() !== month || selectedDate.getFullYear() !== year) {
                                        isValid = false;
                                        showFieldError($field, 'Please enter a valid date.');
                                    } else {
                                        var today = new Date();
                                        today.setHours(0, 0, 0, 0);
                                        
                                        // Check if date is in the past (optional - you can remove this if you want to allow past dates)
                                        if (selectedDate < today) {
                                            isValid = false;
                                            showFieldError($field, 'Please select a future date.');
                                        }
                                    }
                                }
                            }
                        }
                        break;

                    case 'file':
                        var files = $field[0].files;
                        var maxSize = $field.data('max-size');
                        var sizeUnit = $field.data('size-unit') || 'MB';
                        var allowedTypes = $field.data('allowed-types') ? $field.data('allowed-types').split(',') : [];

                        if (files.length > 0) {
                            for (var i = 0; i < files.length; i++) {
                                var file = files[i];
                                var ext = file.name.split('.').pop().toLowerCase();

                                // Check file type if allowed types are specified
                                if (allowedTypes.length > 0) {
                                    var isValidType = false;
                                    for (var j = 0; j < allowedTypes.length; j++) {
                                        if (ext === allowedTypes[j].trim().toLowerCase()) {
                                            isValidType = true;
                                            break;
                                        }
                                    }
                                    if (!isValidType) {
                                        isValid = false;
                                        showFieldError($field, 'Invalid file type. Allowed types: ' + allowedTypes.join(', '));
                                        break;
                                    }
                                }

                                // Check file size
                                if (maxSize) {
                                    var maxSizeBytes;
                                    switch (sizeUnit) {
                                        case 'KB':
                                            maxSizeBytes = maxSize * 1024;
                                            break;
                                        case 'MB':
                                            maxSizeBytes = maxSize * 1024 * 1024;
                                            break;
                                        default:
                                            maxSizeBytes = maxSize * 1024 * 1024; // Default to MB
                                            break;
                                    }
                                    
                                    if (file.size > maxSizeBytes) {
                                        isValid = false;
                                        showFieldError($field, 'File size must be less than ' + maxSize + ' ' + sizeUnit + '.');
                                        break;
                                    }
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
            var $captchaContainer = $captcha.closest('.connect2form-field');
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
    $('.connect2form-image-choice input[type="radio"]').on('change', function() {
        var $choice = $(this).closest('.connect2form-image-choice');
        $choice.siblings().removeClass('selected');
        $choice.addClass('selected');
    });

    // Phone number formatting and validation
    $('.connect2form-field input[type="tel"]').on('input', function() {
        var $input = $(this);
        var value = $input.val();
        
        // Remove all non-digit characters except +, -, (, ), and spaces
        var cleaned = value.replace(/[^0-9\+\-\(\)\s]/g, '');
        
        // Update the input value with cleaned version
        if (cleaned !== value) {
            $input.val(cleaned);
        }
        
        // Auto-format phone numbers (optional enhancement)
        var digitsOnly = cleaned.replace(/[^0-9]/g, '');
        if (digitsOnly.length >= 10 && digitsOnly.length <= 15) {
            // Format US phone numbers
            if (digitsOnly.length === 10) {
                var formatted = '(' + digitsOnly.substring(0, 3) + ') ' + 
                               digitsOnly.substring(3, 6) + '-' + 
                               digitsOnly.substring(6);
                $input.val(formatted);
            }
        }
    });
    
    // Phone number validation on blur
    $('.connect2form-field input[type="tel"]').on('blur', function() {
        var $field = $(this);
        var value = $field.val();
        var digitsOnly = value.replace(/[^0-9]/g, '');
        
        if (value && digitsOnly.length < 7) {
            $field.addClass('invalid-phone');
            if (!$field.siblings('.phone-error').length) {
                $field.after('<div class="phone-error" style="color: #dc3232; font-size: 12px; margin-top: 5px;">Please enter a valid phone number with at least 7 digits.</div>');
            }
        } else {
            $field.removeClass('invalid-phone');
            $field.siblings('.phone-error').remove();
        }
    });

    // Conditional field logic
    function initConditionalFields() {
        // Handle field changes that might affect conditional fields
        $('.connect2form-field input, .connect2form-field select, .connect2form-field textarea').on('change input', function() {
            var $changedField = $(this);
            var fieldId = $changedField.attr('id') || $changedField.attr('name');
            
            // Find all conditional fields that depend on this field
            $('.connect2form-field[data-conditional="true"]').each(function() {
                var $conditionalField = $(this);
                var dependentFieldId = $conditionalField.data('conditional-field');
                
                if (dependentFieldId === fieldId) {
                    checkConditionalField($conditionalField, $changedField);
                }
            });
        });
    }

    function checkConditionalField($conditionalField, $dependentField) {
        var condition = $conditionalField.data('conditional-condition');
        var expectedValue = $conditionalField.data('conditional-value');
        var dependentValue = $dependentField.val();
        
        var shouldShow = false;
        
        switch (condition) {
            case 'is_not_empty':
                shouldShow = dependentValue && dependentValue.trim() !== '';
                break;
            case 'is_empty':
                shouldShow = !dependentValue || dependentValue.trim() === '';
                break;
            case 'equals':
                shouldShow = dependentValue === expectedValue;
                break;
            case 'not_equals':
                shouldShow = dependentValue !== expectedValue;
                break;
            case 'contains':
                shouldShow = dependentValue && dependentValue.toLowerCase().indexOf(expectedValue.toLowerCase()) !== -1;
                break;
            case 'not_contains':
                shouldShow = !dependentValue || dependentValue.toLowerCase().indexOf(expectedValue.toLowerCase()) === -1;
                break;
            default:
                shouldShow = false;
        }
        
        if (shouldShow) {
            $conditionalField.slideDown(300);
            // Re-enable required fields when shown
            $conditionalField.find('input[required], select[required], textarea[required]').prop('disabled', false);
        } else {
            $conditionalField.slideUp(300);
            // Disable required fields when hidden to prevent validation errors
            $conditionalField.find('input[required], select[required], textarea[required]').prop('disabled', true);
        }
    }

    // Initialize conditional fields on page load
    initConditionalFields();
    
    // Initialize UTM tracking
    initUTMTracking();
    
    // Function to capture UTM parameters from URL
    function initUTMTracking() {
        // Get UTM parameters from URL
        var urlParams = new URLSearchParams(window.location.search);
        var utmParams = {
            'utm_source': urlParams.get('utm_source') || '',
            'utm_medium': urlParams.get('utm_medium') || '',
            'utm_campaign': urlParams.get('utm_campaign') || '',
            'utm_term': urlParams.get('utm_term') || '',
            'utm_content': urlParams.get('utm_content') || ''
        };
        
        // Populate UTM hidden fields
        $('input[data-utm-param]').each(function() {
            var $input = $(this);
            var param = $input.data('utm-param');
            if (utmParams[param]) {
                $input.val(utmParams[param]);
            }
        });
        
        // Store UTM parameters in session storage for future use
        if (Object.values(utmParams).some(function(val) { return val !== ''; })) {
            sessionStorage.setItem('connect2form_utm_params', JSON.stringify(utmParams));
        }
    }

    // Function to submit form data
    function submitForm(formData, $form, $message, $submit) {
        $.ajax({
            url: connect2formCF.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
            },
            success: function(response) {
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
                showMessage($message, 'An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $submit.prop('disabled', false);
            }
        });
    }
}); 