jQuery(document).ready(function($) {
    console.log('Admin script loaded');
    console.log('mavlersForms object:', mavlersForms);

    // Form deletion handler
    $('.mavlers-delete-form').on('click', function(e) {
        console.log('Delete button clicked');
        e.preventDefault();
        
        if (!confirm(mavlersForms.strings.deleteConfirm)) {
            console.log('Delete cancelled by user');
            return;
        }

        var $button = $(this);
        var formId = $button.data('form-id');
        console.log('Attempting to delete form ID:', formId);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_delete_form',
                form_id: formId,
                nonce: mavlersForms.nonce
            },
            dataType: 'json',
            success: function(response) {
                console.log('Delete response:', response);
                if (response.success) {
                    $button.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                        if ($('.mavlers-form-row').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data || 'An error occurred while deleting the form.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('An error occurred while deleting the form. Please try again.');
            }
        });
    });
}); 