jQuery(document).ready(function($) {
    // Debug functionality
    if (typeof mavlersCF !== 'undefined') {
        $('#debug-mavlerscf').text(JSON.stringify(mavlersCF, null, 2));
        $('#debug-ajaxurl').text(ajaxurl);
    } else {
        $('#debug-mavlerscf').text('mavlersCF object is undefined!');
        $('#debug-ajaxurl').text('ajaxurl: ' + (typeof ajaxurl !== 'undefined' ? ajaxurl : 'undefined'));
    }

    // Test duplicate button
    $('#test-duplicate').on('click', function(e) {
        e.preventDefault();
        var formId = $(this).data('form-id');
        console.log('Test duplicate clicked for form ID:', formId);
        
        if (typeof mavlersCF === 'undefined') {
            alert('mavlersCF object is not defined! This is the problem.');
            return;
        }
        
        if (typeof ajaxurl === 'undefined') {
            alert('ajaxurl is not defined! This is the problem.');
            return;
        }
        
        // Test the duplicate functionality
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_duplicate_form',
                form_id: formId,
                nonce: mavlersCF.nonce
            },
            success: function(response) {
                console.log('Test duplicate response:', response);
                alert('Duplicate test response: ' + JSON.stringify(response));
            },
            error: function(xhr, status, error) {
                console.error('Test duplicate error:', status, error);
                alert('Duplicate test error: ' + status + ' - ' + error + '\nResponse: ' + xhr.responseText);
            }
        });
    });

    // Handle bulk actions
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).prev('select').val();
        if (action === 'delete') {
            if (!confirm(mavlersCF.confirmDelete)) {
                e.preventDefault();
            }
        }
    });

    // Handle form status toggle
    $('.status-toggle').on('click', function(e) {
        e.preventDefault();
        var $this = $(this);
        var formId = $this.data('form-id');
        var currentStatus = $this.data('status');
        var newStatus = currentStatus === 'active' ? 'inactive' : 'active';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_toggle_status',
                form_id: formId,
                status: newStatus,
                nonce: mavlersCF.nonce
            },
            success: function(response) {
                if (response.success) {
                    $this.data('status', newStatus);
                    $this.closest('tr').find('.column-status span')
                        .removeClass('status-active status-inactive')
                        .addClass('status-' + newStatus)
                        .text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                }
            }
        });
    });

    // Handle shortcode copy
    $('.copy-shortcode').on('click', function(e) {
        e.preventDefault();
        var shortcode = $(this).data('shortcode');
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(shortcode).select();
        document.execCommand('copy');
        $temp.remove();

        var $this = $(this);
        var originalText = $this.text();
        $this.text(mavlersCF.copiedText);
        setTimeout(function() {
            $this.text(originalText);
        }, 2000);
    });

    // Handle form preview
    $('.preview-form').on('click', function(e) {
        e.preventDefault();
        var formId = $(this).data('form-id');
        var previewUrl = mavlersCF.previewUrl + '&form_id=' + formId;
        window.open(previewUrl, 'mavlers_cf_preview', 'width=800,height=600');
    });

    // Handle form duplication
    $('.duplicate-form').on('click', function(e) {
        e.preventDefault();
        var formId = $(this).data('form-id');
        var $button = $(this);
        var originalText = $button.text();
        
        console.log('Duplicate form clicked, form ID:', formId);
        console.log('mavlersCF object:', mavlersCF);
        
        // Show loading state
        $button.text('Duplicating...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_duplicate_form',
                form_id: formId,
                nonce: mavlersCF.nonce
            },
            success: function(response) {
                console.log('Duplicate response:', response);
                if (response.success) {
                    // Show success message
                    $button.text('Duplicated!').addClass('success');
                    
                    // Redirect to edit the new form after a short delay
                    setTimeout(function() {
                        if (response.data && response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            window.location.reload();
                        }
                    }, 1000);
                } else {
                    // Show error message
                    console.error('Duplicate failed:', response.data);
                    $button.text('Error!').addClass('error');
                    setTimeout(function() {
                        $button.text(originalText).removeClass('error').prop('disabled', false);
                    }, 2000);
                }
            },
            error: function(xhr, status, error) {
                // Show error message
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                $button.text('Error!').addClass('error');
                setTimeout(function() {
                    $button.text(originalText).removeClass('error').prop('disabled', false);
                }, 2000);
            }
        });
    });

    // Handle form deletion
    $('.delete-form').on('click', function(e) {
        e.preventDefault();
        if (!confirm(mavlersCF.confirmDelete)) {
            return;
        }

        var formId = $(this).data('form-id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_delete_form',
                form_id: formId,
                nonce: mavlersCF.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                }
            }
        });
    });

    // Test email functionality
    $('#test-email-button').on('click', function() {
        if (confirm('Send a test email to verify email functionality?')) {
            var $button = $('#test-email-button');
            var originalText = $button.text();
            
            $.ajax({
                url: mavlersCF.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_test_email',
                    nonce: mavlersCF.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true).text('Sending...');
                },
                success: function(response) {
                    if (response.success) {
                        // Create a detailed success message with debug info
                        var message = 'Test email sent successfully!\n\n';
                        message += 'Debug Information:\n';
                        message += '- Admin Email: ' + response.data.debug_info.admin_email + '\n';
                        message += '- Blog Name: ' + response.data.debug_info.blogname + '\n';
                        message += '- Site URL: ' + response.data.debug_info.site_url + '\n';
                        message += '- wp_mail function: ' + (response.data.debug_info.wp_mail_exists ? 'Available' : 'Not available') + '\n';
                        message += '- SMTP Plugins: ';
                        
                        var smtpPlugins = [];
                        if (response.data.debug_info.wp_mail_smtp_active) smtpPlugins.push('WP Mail SMTP');
                        if (response.data.debug_info.easy_wp_smtp_active) smtpPlugins.push('Easy WP SMTP');
                        if (response.data.debug_info.post_smtp_active) smtpPlugins.push('Post SMTP');
                        
                        message += smtpPlugins.length > 0 ? smtpPlugins.join(', ') : 'None detected';
                        
                        alert(message);
                    } else {
                        // Create a detailed error message with suggestions
                        var message = 'Failed to send test email!\n\n';
                        message += 'Debug Information:\n';
                        message += '- Admin Email: ' + response.data.debug_info.admin_email + '\n';
                        message += '- Blog Name: ' + response.data.debug_info.blogname + '\n';
                        message += '- Site URL: ' + response.data.debug_info.site_url + '\n';
                        message += '- wp_mail function: ' + (response.data.debug_info.wp_mail_exists ? 'Available' : 'Not available') + '\n';
                        message += '- SMTP Plugins: ';
                        
                        var smtpPlugins = [];
                        if (response.data.debug_info.wp_mail_smtp_active) smtpPlugins.push('WP Mail SMTP');
                        if (response.data.debug_info.easy_wp_smtp_active) smtpPlugins.push('Easy WP SMTP');
                        if (response.data.debug_info.post_smtp_active) smtpPlugins.push('Post SMTP');
                        
                        message += smtpPlugins.length > 0 ? smtpPlugins.join(', ') : 'None detected';
                        
                        message += '\n\nSuggestions:\n';
                        response.data.suggestions.forEach(function(suggestion) {
                            message += '- ' + suggestion + '\n';
                        });
                        
                        alert(message);
                    }
                },
                error: function(xhr, status, error) {
                    var message = 'Error sending test email!\n\n';
                    message += 'Status: ' + status + '\n';
                    message += 'Error: ' + error + '\n';
                    message += 'Response: ' + xhr.responseText;
                    alert(message);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }
    });

    // Form submission handling
}); 