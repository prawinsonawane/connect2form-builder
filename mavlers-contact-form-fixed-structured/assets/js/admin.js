jQuery(document).ready(function($) {
    console.log('Admin script loaded');
    console.log('mavlersForms object:', mavlersForms);

    // Copy shortcode functionality
    $('.copy-shortcode').on('click', function(e) {
        e.preventDefault();
        const shortcode = $(this).data('shortcode');
        const tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(shortcode).select();
        document.execCommand('copy');
        tempInput.remove();

        // Show copied feedback
        const $button = $(this);
        const originalText = $button.text();
        $button.text('Copied!');
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });

    // Delete form functionality
    $('.mavlers-delete-form').on('click', function(e) {
        e.preventDefault();
        const formId = $(this).data('form-id');
        
        if (confirm(mavlersForms.strings.deleteConfirm)) {
            $.ajax({
                url: mavlersForms.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_delete_form',
                    form_id: formId,
                    nonce: mavlersForms.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row from the table
                        $(`tr[data-form-id="${formId}"]`).fadeOut(400, function() {
                            $(this).remove();
                            // If no forms left, show the "no forms" message
                            if ($('.mavlers-forms-list tbody tr').length === 0) {
                                $('.mavlers-forms-list').replaceWith(
                                    '<div class="notice notice-info"><p>' + 
                                    mavlersForms.strings.noForms + 
                                    '</p></div>'
                                );
                            }
                        });
                    } else {
                        alert(mavlersForms.strings.error);
                    }
                },
                error: function() {
                    alert(mavlersForms.strings.error);
                }
            });
        }
    });

    // Duplicate form functionality
    $('.duplicate a').on('click', function(e) {
        e.preventDefault();
        const formId = $(this).closest('tr').find('.mavlers-delete-form').data('form-id');
        
        $.ajax({
            url: mavlersForms.ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_duplicate_form',
                form_id: formId,
                nonce: mavlersForms.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(mavlersForms.strings.error);
                }
            },
            error: function() {
                alert(mavlersForms.strings.error);
            }
        });
    });
}); 