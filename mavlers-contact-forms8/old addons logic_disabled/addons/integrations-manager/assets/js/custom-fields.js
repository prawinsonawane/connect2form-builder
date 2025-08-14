/**
 * Mailchimp Custom Fields Manager JavaScript
 */

(function($) {
    'use strict';

    // Global variables
    let currentAudienceId = '';
    let mergeFields = {};
    let interestCategories = {};
    let currentEditingField = null;

    // Initialize when document is ready
    $(document).ready(function() {
        initializeCustomFieldsManager();
    });

    /**
     * Initialize the custom fields manager
     */
    function initializeCustomFieldsManager() {
        // Audience selector change
        $(document).on('change', '#audience-selector', function() {
            const audienceId = $(this).val();
            if (audienceId) {
                currentAudienceId = audienceId;
                loadCustomFields(audienceId);
            } else {
                clearFieldsDisplay();
            }
        });

        // Sync fields button
        $(document).on('click', '#sync-fields', function() {
            if (currentAudienceId) {
                syncCustomFields(currentAudienceId);
            }
        });

        // Create new field button
        $(document).on('click', '#create-field', function() {
            openFieldModal();
        });

        // Edit field buttons
        $(document).on('click', '.edit-field', function() {
            const fieldTag = $(this).data('field-tag');
            if (mergeFields[fieldTag]) {
                openFieldModal(mergeFields[fieldTag]);
            }
        });

        // Delete field buttons
        $(document).on('click', '.delete-field', function() {
            const fieldTag = $(this).data('field-tag');
            const fieldName = $(this).data('field-name');
            if (confirm(mailchimpCustomFields.strings.confirmDelete.replace('%s', fieldName))) {
                deleteField(fieldTag);
            }
        });

        // Modal events
        $(document).on('click', '.field-modal-overlay', function(e) {
            if (e.target === this) {
                closeFieldModal();
            }
        });

        $(document).on('click', '.field-modal-close', function() {
            closeFieldModal();
        });

        // Form submission
        $(document).on('submit', '#field-form', function(e) {
            e.preventDefault();
            saveField();
        });

        // Field type change
        $(document).on('change', '#field-type', function() {
            toggleChoicesManager();
        });

        // Add choice button
        $(document).on('click', '#add-choice', function() {
            addChoice();
        });

        // Remove choice buttons
        $(document).on('click', '.remove-choice', function() {
            $(this).closest('.choice-item').remove();
        });

        // Initialize if audience is already selected
        const selectedAudience = $('#audience-selector').val();
        if (selectedAudience) {
            currentAudienceId = selectedAudience;
            loadCustomFields(selectedAudience);
        }
    }

    /**
     * Load custom fields for audience
     */
    function loadCustomFields(audienceId) {
        showLoading('#merge-fields-list');
        showLoading('#interest-categories-list');

        // Load merge fields
        $.ajax({
            url: mailchimpCustomFields.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_merge_fields',
                audience_id: audienceId,
                nonce: mailchimpCustomFields.nonce
            },
            success: function(response) {
                if (response.success) {
                    mergeFields = response.data;
                    displayMergeFields(response.data);
                } else {
                    showError('#merge-fields-list', response.message || 'Failed to load merge fields');
                }
            },
            error: function() {
                showError('#merge-fields-list', 'Network error occurred');
            }
        });

        // Load interest categories
        $.ajax({
            url: mailchimpCustomFields.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_interest_categories',
                audience_id: audienceId,
                nonce: mailchimpCustomFields.nonce
            },
            success: function(response) {
                if (response.success) {
                    interestCategories = response.data;
                    displayInterestCategories(response.data);
                } else {
                    showError('#interest-categories-list', response.message || 'Failed to load interest categories');
                }
            },
            error: function() {
                showError('#interest-categories-list', 'Network error occurred');
            }
        });
    }

    /**
     * Display merge fields
     */
    function displayMergeFields(fields) {
        const container = $('#merge-fields-list');
        container.empty();

        if (Object.keys(fields).length === 0) {
            container.html(getEmptyState('No merge fields found', 'Create your first custom merge field to get started.'));
            return;
        }

        $.each(fields, function(tag, field) {
            const fieldHtml = createFieldItem(field);
            container.append(fieldHtml);
        });
    }

    /**
     * Create field item HTML
     */
    function createFieldItem(field) {
        const badges = [];
        
        if (field.required) {
            badges.push('<span class="field-badge required">Required</span>');
        }
        
        if (field.public) {
            badges.push('<span class="field-badge public">Public</span>');
        } else {
            badges.push('<span class="field-badge private">Private</span>');
        }

        const typeIcon = getFieldTypeIcon(field.type);

        return `
            <div class="field-item" data-field-tag="${field.tag}">
                <div class="field-info">
                    <div class="field-name">
                        ${field.name}
                        <span class="field-tag">${field.tag}</span>
                    </div>
                    <div class="field-meta">
                        <span class="field-type">
                            ${typeIcon} ${field.type}
                        </span>
                        ${field.help_text ? `<span class="field-help">Help: ${field.help_text}</span>` : ''}
                    </div>
                    <div class="field-badges">
                        ${badges.join('')}
                    </div>
                </div>
                <div class="field-actions">
                    <button type="button" class="button button-small edit-field" 
                            data-field-tag="${field.tag}" 
                            data-field-name="${field.name}">
                        ✏️ Edit
                    </button>
                    ${field.tag !== 'EMAIL' ? `
                    <button type="button" class="button button-small button-link-delete delete-field" 
                            data-field-tag="${field.tag}" 
                            data-field-name="${field.name}">
                        🗑️ Delete
                    </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Get field type icon
     */
    function getFieldTypeIcon(type) {
        const icons = {
            'text': '📝',
            'email': '📧',
            'number': '🔢',
            'phone': '📞',
            'date': '📅',
            'birthday': '🎂',
            'address': '🏠',
            'url': '🔗',
            'dropdown': '📋',
            'radio': '⚪',
            'imageurl': '🖼️',
            'zip': '📮'
        };
        
        return icons[type] || '📄';
    }

    /**
     * Display interest categories
     */
    function displayInterestCategories(categories) {
        const container = $('#interest-categories-list');
        container.empty();

        if (categories.length === 0) {
            container.html(getEmptyState('No interest categories found', 'Interest categories allow subscribers to choose their preferences.'));
            return;
        }

        $.each(categories, function(index, category) {
            const categoryHtml = createInterestCategoryItem(category);
            container.append(categoryHtml);
        });
    }

    /**
     * Create interest category item HTML
     */
    function createInterestCategoryItem(category) {
        let interestsHtml = '';
        
        if (category.interests && category.interests.length > 0) {
            $.each(category.interests, function(index, interest) {
                interestsHtml += `
                    <div class="interest-item">
                        <span class="interest-name">${interest.name}</span>
                        <span class="interest-mapping">${interest.subscriber_count || 0} subscribers</span>
                    </div>
                `;
            });
        } else {
            interestsHtml = '<div class="empty-state">No interests defined</div>';
        }

        return `
            <div class="interest-category">
                <div class="category-header">
                    <span>${category.title}</span>
                    <span class="category-type">${category.type}</span>
                </div>
                <div class="interest-list">
                    ${interestsHtml}
                </div>
            </div>
        `;
    }

    /**
     * Sync custom fields
     */
    function syncCustomFields(audienceId) {
        showMessage('Syncing custom fields...', 'info');
        
        $('#sync-fields').prop('disabled', true).text('Syncing...');

        $.ajax({
            url: mailchimpCustomFields.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_sync_custom_fields',
                audience_id: audienceId,
                nonce: mailchimpCustomFields.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(mailchimpCustomFields.strings.syncSuccess, 'success');
                    
                    // Update displays
                    mergeFields = response.data.merge_fields;
                    interestCategories = response.data.interests;
                    displayMergeFields(response.data.merge_fields);
                    displayInterestCategories(response.data.interests);
                } else {
                    showMessage(response.message || mailchimpCustomFields.strings.syncError, 'error');
                }
            },
            error: function() {
                showMessage('Network error occurred during sync', 'error');
            },
            complete: function() {
                $('#sync-fields').prop('disabled', false).text('🔄 Sync Fields');
            }
        });
    }

    /**
     * Open field modal
     */
    function openFieldModal(field = null) {
        currentEditingField = field;
        
        // Reset form
        $('#field-form')[0].reset();
        $('#choices-manager').hide();
        
        if (field) {
            // Edit mode
            $('#modal-title').text('Edit Merge Field');
            $('#field-name').val(field.name);
            $('#field-tag').val(field.tag).prop('readonly', true);
            $('#field-type').val(field.type).prop('disabled', true);
            $('#field-required').prop('checked', field.required);
            $('#field-public').prop('checked', field.public);
            $('#field-default-value').val(field.default_value);
            $('#field-help-text').val(field.help_text);
            $('#field-display-order').val(field.display_order);
            
            // Handle choices for dropdown/radio fields
            if (field.type === 'dropdown' || field.type === 'radio') {
                populateChoices(field.options?.choices || {});
                $('#choices-manager').show();
            }
        } else {
            // Create mode
            $('#modal-title').text('Create Merge Field');
            $('#field-tag').prop('readonly', false);
            $('#field-type').prop('disabled', false);
        }
        
        // Show modal
        $('.field-modal-overlay').addClass('active');
    }

    /**
     * Close field modal
     */
    function closeFieldModal() {
        $('.field-modal-overlay').removeClass('active');
        currentEditingField = null;
    }

    /**
     * Toggle choices manager based on field type
     */
    function toggleChoicesManager() {
        const fieldType = $('#field-type').val();
        
        if (fieldType === 'dropdown' || fieldType === 'radio') {
            $('#choices-manager').show();
            
            // Add default choice if none exist
            if ($('#choices-container .choice-item').length === 0) {
                addChoice();
            }
        } else {
            $('#choices-manager').hide();
        }
    }

    /**
     * Add choice item
     */
    function addChoice(value = '', label = '') {
        const choiceHtml = `
            <div class="choice-item">
                <input type="text" placeholder="Choice Value" class="choice-value" value="${value}">
                <input type="text" placeholder="Choice Label" class="choice-label" value="${label}">
                <button type="button" class="remove-choice">Remove</button>
            </div>
        `;
        
        $('#choices-container').append(choiceHtml);
    }

    /**
     * Populate choices from field data
     */
    function populateChoices(choices) {
        $('#choices-container').empty();
        
        $.each(choices, function(value, label) {
            addChoice(value, label);
        });
    }

    /**
     * Save field
     */
    function saveField() {
        const formData = {
            name: $('#field-name').val(),
            tag: $('#field-tag').val(),
            type: $('#field-type').val(),
            required: $('#field-required').is(':checked'),
            public: $('#field-public').is(':checked'),
            default_value: $('#field-default-value').val(),
            help_text: $('#field-help-text').val(),
            display_order: parseInt($('#field-display-order').val()) || 0
        };

        // Collect choices for dropdown/radio fields
        if (formData.type === 'dropdown' || formData.type === 'radio') {
            const choices = {};
            $('#choices-container .choice-item').each(function() {
                const value = $(this).find('.choice-value').val();
                const label = $(this).find('.choice-label').val();
                if (value && label) {
                    choices[value] = label;
                }
            });
            formData.choices = choices;
        }

        // Validate required fields
        if (!formData.name) {
            showMessage('Field name is required', 'error');
            return;
        }

        if (!currentEditingField && !formData.tag) {
            showMessage('Field tag is required', 'error');
            return;
        }

        const action = currentEditingField ? 'mailchimp_update_merge_field' : 'mailchimp_create_merge_field';
        const ajaxData = {
            action: action,
            audience_id: currentAudienceId,
            field_data: formData,
            nonce: mailchimpCustomFields.nonce
        };

        if (currentEditingField) {
            ajaxData.merge_id = currentEditingField.merge_id;
        }

        // Disable submit button
        $('#save-field').prop('disabled', true).text('Saving...');

        $.ajax({
            url: mailchimpCustomFields.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showMessage(response.message, 'success');
                    closeFieldModal();
                    loadCustomFields(currentAudienceId); // Reload fields
                } else {
                    showMessage(response.message || 'Failed to save field', 'error');
                }
            },
            error: function() {
                showMessage('Network error occurred', 'error');
            },
            complete: function() {
                $('#save-field').prop('disabled', false).text(currentEditingField ? 'Update Field' : 'Create Field');
            }
        });
    }

    /**
     * Delete field
     */
    function deleteField(fieldTag) {
        const field = mergeFields[fieldTag];
        if (!field) return;

        $.ajax({
            url: mailchimpCustomFields.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_delete_merge_field',
                audience_id: currentAudienceId,
                merge_id: field.merge_id,
                nonce: mailchimpCustomFields.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.message, 'success');
                    $(`.field-item[data-field-tag="${fieldTag}"]`).fadeOut(function() {
                        $(this).remove();
                    });
                    delete mergeFields[fieldTag];
                } else {
                    showMessage(response.message || 'Failed to delete field', 'error');
                }
            },
            error: function() {
                showMessage('Network error occurred', 'error');
            }
        });
    }

    /**
     * Show loading state
     */
    function showLoading(container) {
        $(container).html(`
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p class="loading-text">Loading...</p>
            </div>
        `);
    }

    /**
     * Show error state
     */
    function showError(container, message) {
        $(container).html(`
            <div class="empty-state">
                <div class="empty-state-icon">⚠️</div>
                <h4>Error</h4>
                <p>${message}</p>
            </div>
        `);
    }

    /**
     * Get empty state HTML
     */
    function getEmptyState(title, description) {
        return `
            <div class="empty-state">
                <div class="empty-state-icon">📄</div>
                <h4>${title}</h4>
                <p>${description}</p>
            </div>
        `;
    }

    /**
     * Clear fields display
     */
    function clearFieldsDisplay() {
        $('#merge-fields-list').html(getEmptyState('Select an audience', 'Choose a Mailchimp audience to view and manage custom fields.'));
        $('#interest-categories-list').html(getEmptyState('Select an audience', 'Choose a Mailchimp audience to view interest categories.'));
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        const messageHtml = `<div class="message ${type}">${message}</div>`;
        
        // Remove existing messages
        $('.message').remove();
        
        // Add new message
        $('.custom-fields-manager').prepend(messageHtml);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $('.message.success').fadeOut();
            }, 3000);
        }
    }

    // Export functions for external use
    window.MailchimpCustomFields = {
        loadCustomFields: loadCustomFields,
        syncCustomFields: syncCustomFields,
        openFieldModal: openFieldModal,
        closeFieldModal: closeFieldModal
    };

})(jQuery); 