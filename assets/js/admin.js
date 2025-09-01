jQuery(document).ready(function($) {
    // Debug functionality
    if (typeof connect2formCF !== 'undefined') {
        $('#debug-connect2formcf').text(JSON.stringify(connect2formCF, null, 2));
        $('#debug-ajaxurl').text(ajaxurl);
    } else {
        $('#debug-connect2formcf').text('connect2formCF object is undefined!');
        $('#debug-ajaxurl').text('ajaxurl: ' + (typeof ajaxurl !== 'undefined' ? ajaxurl : 'undefined'));
    }

    // Handle delete submission confirmation
    $('.delete-submission-btn').on('click', function(e) {
        var confirmMessage = $(this).data('confirm-message');
        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });

    // Handle close preview window
    $('.close-preview-btn').on('click', function(e) {
        e.preventDefault();
        window.close();
    });

    // Test duplicate button
    $('#test-duplicate').on('click', function(e) {
        e.preventDefault();
        var formId = $(this).data('form-id');

        
        if (typeof connect2formCF === 'undefined') {
            alert('connect2formCF object is not defined! This is the problem.');
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
                action: 'connect2form_duplicate_form',
                form_id: formId,
                nonce: connect2formCF.nonce
            },
            success: function(response) {

                alert('Duplicate test response: ' + JSON.stringify(response));
            },
            error: function(xhr, status, error) {
                console.error('Test duplicate error:', status, error);
                alert('Duplicate test error: ' + status + ' - ' + error + '\nResponse: ' + xhr.responseText);
            }
        });
    });

    // Handle individual form deletion

    // Handle individual submission deletion
    $(document).on('click', '.delete-submission-ajax', function(e) {
        e.preventDefault();
        
        var submissionId = $(this).data('submission-id');
        var nonce = $(this).data('nonce');
        
        if (confirm('Are you sure you want to delete this submission?')) {
            var $row = $(this).closest('tr');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'connect2form_delete_submission',
                    submission_id: submissionId,
                    nonce: nonce
                },
                beforeSend: function() {
                    $row.css('opacity', '0.5');
                },
                success: function(response) {

                    if (response.success) {
                        $row.fadeOut(400, function() {
                            $(this).remove();
                        });
                        
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>')
                            .insertAfter('.wp-header-end')
                            .delay(3000)
                            .fadeOut();
                    } else {
                        alert('Error: ' + response.data);
                        $row.css('opacity', '1');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete submission error:', status, error);
                    alert('Error deleting submission: ' + error);
                    $row.css('opacity', '1');
                }
            });
        }
    });

    // Handle bulk actions (for both forms and submissions)
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).prev('select').val();

        
        if (action === 'delete') {
            e.preventDefault();
            
            // Check if we're on the submissions page or forms page
            var checkedForms = $('input[name="form[]"]:checked');
            var checkedSubmissions = $('input[name="submission[]"]:checked');
            
            if (checkedForms.length > 0) {
                // Handle form deletion
                if (confirm('Are you sure you want to delete ' + checkedForms.length + ' form(s)?')) {
                    var formIds = [];
                    checkedForms.each(function() {
                        formIds.push($(this).val());
                    });
                    

                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'connect2form_bulk_delete_forms',
                            form_ids: formIds,
                            nonce: connect2formCF.formBulkDeleteNonce
                        },
                        beforeSend: function() {
                            $('.wp-list-table').css('opacity', '0.5');
                        },
                        success: function(response) {

                            if (response.success) {
                                // Remove all checked rows
                                checkedForms.closest('tr').fadeOut(400, function() {
                                    $(this).remove();
                                });
                                
                                // Show success message
                                var message = response.data.message || response.data;
                                $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>')
                                    .insertAfter('.wp-header-end')
                                    .delay(3000)
                                    .fadeOut();
                            } else {
                                alert('Error: ' + response.data);
                            }
                            $('.wp-list-table').css('opacity', '1');
                        },
                        error: function(xhr, status, error) {
                            console.error('Bulk delete forms error:', status, error);
                            alert('Error deleting forms: ' + error);
                            $('.wp-list-table').css('opacity', '1');
                        }
                    });
                }
            } else if (checkedSubmissions.length > 0) {
                // Handle submission deletion
                if (confirm('Are you sure you want to delete ' + checkedSubmissions.length + ' submission(s)?')) {
                    var submissionIds = [];
                    checkedSubmissions.each(function() {
                        submissionIds.push($(this).val());
                    });
                    

                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'connect2form_bulk_delete_submissions',
                            submission_ids: submissionIds,
                            nonce: connect2formCF.submissionBulkDeleteNonce
                        },
                        beforeSend: function() {
                            $('.wp-list-table').css('opacity', '0.5');
                        },
                        success: function(response) {

                            if (response.success) {
                                // Remove all checked rows
                                checkedSubmissions.closest('tr').fadeOut(400, function() {
                                    $(this).remove();
                                });
                                
                                // Show success message
                                $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>')
                                    .insertAfter('.wp-header-end')
                                    .delay(3000)
                                    .fadeOut();
                            } else {
                                alert('Error: ' + response.data);
                            }
                            $('.wp-list-table').css('opacity', '1');
                        },
                        error: function(xhr, status, error) {
                            console.error('Bulk delete submissions error:', status, error);
                            alert('Error deleting submissions: ' + error);
                            $('.wp-list-table').css('opacity', '1');
                        }
                    });
                }
            } else {
                alert('Please select at least one item to delete.');
            }
        } else if (action === 'export') {
            e.preventDefault();
            
            var checkedSubmissions = $('input[name="submission[]"]:checked');
            if (checkedSubmissions.length === 0) {
                alert('Please select at least one submission to export.');
                return;
            }
            
            if (confirm('Are you sure you want to export ' + checkedSubmissions.length + ' submission(s)?')) {
                var submissionIds = [];
                checkedSubmissions.each(function() {
                    submissionIds.push($(this).val());
                });
                

                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'connect2form_bulk_export_submissions',
                        submission_ids: submissionIds,
                        nonce: connect2formCF.submissionBulkExportNonce
                    },
                    beforeSend: function() {
                        $('.wp-list-table').css('opacity', '0.5');
                    },
                    success: function(response) {

                        if (response.success) {
                            // Create and download CSV file
                            var csvContent = response.data.csv_data;
                            var filename = response.data.filename;
                            
                            // Create blob and download
                            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                            var link = document.createElement('a');
                            if (link.download !== undefined) {
                                var url = URL.createObjectURL(blob);
                                link.setAttribute('href', url);
                                link.setAttribute('download', filename);
                                link.style.visibility = 'hidden';
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                            }
                            
                            // Show success message
                            $('<div class="notice notice-success is-dismissible"><p>Export completed successfully. File downloaded: ' + filename + '</p></div>')
                                .insertAfter('.wp-header-end')
                                .delay(3000)
                                .fadeOut();
                        } else {
                            alert('Error: ' + response.data);
                        }
                        $('.wp-list-table').css('opacity', '1');
                    },
                    error: function(xhr, status, error) {
                        console.error('Bulk export submissions error:', status, error);
                        alert('Error exporting submissions: ' + error);
                        $('.wp-list-table').css('opacity', '1');
                    }
                });
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
                action: 'connect2form_toggle_status',
                form_id: formId,
                status: newStatus,
                nonce: connect2formCF.nonce
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
        $this.text(connect2formCF.copiedText);
        setTimeout(function() {
            $this.text(originalText);
        }, 2000);
    });

    // Handle form preview
    $('.preview-form').on('click', function(e) {
        e.preventDefault();
        var formId = $(this).data('form-id');
        var previewUrl = connect2formCF.previewUrl + '&form_id=' + formId;
        window.open(previewUrl, 'connect2form_preview', 'width=800,height=600');
    });

    // Handle form deletion via AJAX
    $(document).on('click', '.delete-form-ajax', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var formId = $(this).data('form-id');
        var $row = $(this).closest('tr');
        

        
        if (confirm(connect2formCF.confirmDelete)) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'connect2form_delete_form',
                    form_id: formId,
                    nonce: connect2formCF.formDeleteNonce
                },
                beforeSend: function() {

                    $row.css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message immediately
                        var message = response.data.message || 'Form deleted successfully';
                        $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>')
                            .insertAfter('.wp-header-end')
                            .delay(3000)
                            .fadeOut();
                        
                        // Then remove the row
                        $row.fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        $row.css('opacity', '1');
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {

                    $row.css('opacity', '1');
                    alert('Error deleting form. Please try again.');
                }
            });
        }
    });

    // Handle form duplication
    $('.duplicate-form').on('click', function(e) {
        e.preventDefault();
        var formId = $(this).data('form-id');
        var $button = $(this);
        var originalText = $button.text();
        

        
        // Show loading state
        $button.text('Duplicating...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'connect2form_duplicate_form',
                form_id: formId,
                nonce: connect2formCF.nonce
            },
            success: function(response) {

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
        e.stopPropagation();
        if (!confirm(connect2formCF.confirmDelete)) {
            return;
        }

        var formId = $(this).data('form-id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'connect2form_delete_form',
                form_id: formId,
                nonce: connect2formCF.formDeleteNonce
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
                url: connect2formCF.ajaxurl,
                type: 'POST',
                data: {
                    action: 'connect2form_test_email',
                    nonce: connect2formCF.nonce
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