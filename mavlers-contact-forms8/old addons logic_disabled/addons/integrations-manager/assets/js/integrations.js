/**
 * Mavlers Contact Forms - Integration Manager JavaScript
 * 
 * Main JavaScript functionality for the integration system
 */

(function($) {
    'use strict';

    // Global integration manager object
    window.MavlersCFIntegrations = {
        init: function() {
            this.bindEvents();
            this.initializeExistingIntegrations();
        },

        // Event bindings
        bindEvents: function() {
            // Integration toggle handlers
            $(document).on('change', '.integration-enabled-checkbox', this.handleIntegrationToggle.bind(this));
            
            // Setup/Configure button handlers
            $(document).on('click', '.setup-integration', this.handleSetupIntegration.bind(this));
            $(document).on('click', '.configure-integration', this.handleConfigureIntegration.bind(this));
            
            // Modal handlers
            $(document).on('click', '.modal-close, .modal-cancel', this.closeModal.bind(this));
            $(document).on('click', '.modal-overlay', this.closeModal.bind(this));
            $(document).on('click', '.modal-save', this.handleSaveIntegration.bind(this));
            
            // Field mapping handlers
            $(document).on('click', '.save-field-mapping', this.handleSaveFieldMapping.bind(this));
            $(document).on('click', '.auto-map-fields', this.handleAutoMapFields.bind(this));
            $(document).on('change', '.form-field-selector', this.handleFieldMappingChange.bind(this));
            $(document).on('click', '.remove-mapping', this.handleRemoveMapping.bind(this));
            
            // Test connection handler
            $(document).on('click', '.test-connection-btn', this.handleTestConnection.bind(this));
            
            // Refresh integrations
            $(document).on('click', '.refresh-integrations', this.handleRefreshIntegrations.bind(this));
            
            // Drag and drop for field mapping
            this.initializeDragAndDrop();
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
        },

        // Initialize existing integrations
        initializeExistingIntegrations: function() {
            $('.integration-card').each(function() {
                var $card = $(this);
                var integrationId = $card.data('integration-id');
                
                // Update UI based on current state
                MavlersCFIntegrations.updateIntegrationCardState($card);
            });
        },

        // Handle integration toggle
        handleIntegrationToggle: function(e) {
            var $checkbox = $(e.target);
            var integrationId = $checkbox.data('integration-id');
            var isEnabled = $checkbox.is(':checked');
            var $card = $checkbox.closest('.integration-card');

            // Show loading state
            this.setLoadingState($card, true);

            // AJAX request to toggle integration
            $.ajax({
                url: mavlers_cf_integrations.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_toggle_integration',
                    nonce: mavlers_cf_integrations.nonce,
                    form_id: this.getCurrentFormId(),
                    integration_id: integrationId,
                    enabled: isEnabled ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFIntegrations.updateIntegrationCardState($card);
                        MavlersCFIntegrations.showNotice('Integration ' + (isEnabled ? 'enabled' : 'disabled') + ' successfully', 'success');
                    } else {
                        // Revert checkbox state
                        $checkbox.prop('checked', !isEnabled);
                        MavlersCFIntegrations.showNotice(response.data.message || 'Failed to toggle integration', 'error');
                    }
                },
                error: function() {
                    // Revert checkbox state
                    $checkbox.prop('checked', !isEnabled);
                    MavlersCFIntegrations.showNotice('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    MavlersCFIntegrations.setLoadingState($card, false);
                }
            });
        },

        // Handle setup integration
        handleSetupIntegration: function(e) {
            e.preventDefault();
            var $button = $(e.target).closest('button');
            var integrationId = $button.data('integration-id');
            
            this.openIntegrationModal(integrationId, 'setup');
        },

        // Handle configure integration
        handleConfigureIntegration: function(e) {
            e.preventDefault();
            var $button = $(e.target).closest('button');
            var integrationId = $button.data('integration-id');
            
            this.openIntegrationModal(integrationId, 'configure');
        },

        // Open integration modal
        openIntegrationModal: function(integrationId, mode) {
            var $modal = $('#integration-setup-modal');
            var $content = $('#modal-content-area');
            
            // Set modal title
            var title = mode === 'setup' ? 'Setup Integration' : 'Configure Integration';
            $('#modal-title').text(title);
            
            // Show loading state
            $content.html('<div class="loading-spinner"><span class="dashicons dashicons-update-alt"></span> Loading...</div>');
            
            // Show modal
            $modal.show();
            
            // Load integration configuration
            $.ajax({
                url: mavlers_cf_integrations.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_get_integration_config',
                    nonce: mavlers_cf_integrations.nonce,
                    form_id: this.getCurrentFormId(),
                    integration_id: integrationId,
                    mode: mode
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(response.data.html);
                        MavlersCFIntegrations.initializeIntegrationForm(integrationId);
                    } else {
                        $content.html('<div class="error-message">Failed to load integration configuration: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $content.html('<div class="error-message">Connection error. Please try again.</div>');
                }
            });
        },

        // Initialize integration form
        initializeIntegrationForm: function(integrationId) {
            var $modal = $('#integration-setup-modal');
            
            // Enable save button
            $('.modal-save', $modal).prop('disabled', false);
            
            // Initialize form validation
            this.initializeFormValidation($modal);
            
            // Initialize OAuth handlers if present
            this.initializeOAuthHandlers($modal);
            
            // Initialize field mapping if present
            if ($('.field-mapping-container', $modal).length) {
                this.initializeFieldMapping();
            }
        },

        // Handle save integration
        handleSaveIntegration: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            var $modal = $('#integration-setup-modal');
            var formData = this.serializeIntegrationForm($modal);
            
            // Validate form
            if (!this.validateIntegrationForm($modal)) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text('Saving...');
            
            // AJAX request to save integration
            $.ajax({
                url: mavlers_cf_integrations.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_save_integration',
                    nonce: mavlers_cf_integrations.nonce,
                    form_id: this.getCurrentFormId(),
                    ...formData
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFIntegrations.closeModal();
                        MavlersCFIntegrations.refreshIntegrationCards();
                        MavlersCFIntegrations.showNotice('Integration saved successfully', 'success');
                    } else {
                        MavlersCFIntegrations.showNotice(response.data.message || 'Failed to save integration', 'error');
                    }
                },
                error: function() {
                    MavlersCFIntegrations.showNotice('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Integration');
                }
            });
        },

        // Handle field mapping
        handleSaveFieldMapping: function(e) {
            e.preventDefault();
            var mappings = this.collectFieldMappings();
            
            // Validate mappings
            if (!this.validateFieldMappings(mappings)) {
                return;
            }
            
            // Save mappings (implementation depends on context)
            this.saveFieldMappings(mappings);
        },

        // Handle auto map fields
        handleAutoMapFields: function(e) {
            e.preventDefault();
            var integrationId = this.getCurrentIntegrationId();
            
            $.ajax({
                url: mavlers_cf_integrations.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_auto_map_fields',
                    nonce: mavlers_cf_integrations.nonce,
                    form_id: this.getCurrentFormId(),
                    integration_id: integrationId
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFIntegrations.applyFieldMappings(response.data.mappings);
                        MavlersCFIntegrations.showNotice('Fields auto-mapped successfully', 'success');
                    } else {
                        MavlersCFIntegrations.showNotice(response.data.message || 'Failed to auto-map fields', 'error');
                    }
                },
                error: function() {
                    MavlersCFIntegrations.showNotice('Connection error. Please try again.', 'error');
                }
            });
        },

        // Handle field mapping change
        handleFieldMappingChange: function(e) {
            var $select = $(e.target);
            var integrationField = $select.data('integration-field');
            var formField = $select.val();
            var $fieldItem = $select.closest('.field-item');
            
            if (formField) {
                $fieldItem.addClass('mapped');
                this.updateConnectionLines();
            } else {
                $fieldItem.removeClass('mapped');
                this.updateConnectionLines();
            }
            
            this.updateMappingStats();
        },

        // Handle remove mapping
        handleRemoveMapping: function(e) {
            e.preventDefault();
            var $button = $(e.target).closest('button');
            var $fieldItem = $button.closest('.field-item');
            var $select = $('.form-field-selector', $fieldItem);
            
            $select.val('');
            $fieldItem.removeClass('mapped');
            $button.remove();
            
            this.updateConnectionLines();
            this.updateMappingStats();
        },

        // Handle test connection
        handleTestConnection: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            var integrationId = this.getCurrentIntegrationId();
            var $results = $('#test-connection-results');
            
            // Show loading
            $button.prop('disabled', true).text('Testing...');
            $results.html('<div class="loading-spinner"><span class="dashicons dashicons-update-alt"></span> Testing connection...</div>');
            
            $.ajax({
                url: mavlers_cf_integrations.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_test_connection',
                    nonce: mavlers_cf_integrations.nonce,
                    integration_id: integrationId,
                    form_id: this.getCurrentFormId()
                },
                success: function(response) {
                    if (response.success) {
                        $results.html('<div class="test-success"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</div>');
                    } else {
                        $results.html('<div class="test-error"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $results.html('<div class="test-error"><span class="dashicons dashicons-warning"></span> Connection error. Please try again.</div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        // Handle refresh integrations
        handleRefreshIntegrations: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            
            $button.prop('disabled', true);
            $button.find('.dashicons').addClass('fa-spin');
            
            // Reload the integration section
            this.refreshIntegrationCards();
            
            setTimeout(function() {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('fa-spin');
            }, 1000);
        },

        // Initialize drag and drop
        initializeDragAndDrop: function() {
            if (!$.fn.sortable) return; // jQuery UI required
            
            // Make form fields draggable
            $('#form-fields-list .field-item').draggable({
                helper: 'clone',
                cursor: 'move',
                revert: 'invalid',
                start: function(event, ui) {
                    ui.helper.addClass('dragging');
                }
            });
            
            // Make integration fields droppable
            $('#integration-fields-list .field-item').droppable({
                accept: '#form-fields-list .field-item',
                hoverClass: 'drag-over',
                drop: function(event, ui) {
                    var formFieldId = ui.draggable.data('field-id');
                    var $integrationField = $(this);
                    var $select = $('.form-field-selector', $integrationField);
                    
                    $select.val(formFieldId).trigger('change');
                }
            });
        },

        // Initialize form validation
        initializeFormValidation: function($container) {
            $('input, select, textarea', $container).on('input change', function() {
                MavlersCFIntegrations.validateField($(this));
            });
        },

        // Initialize OAuth handlers
        initializeOAuthHandlers: function($container) {
            $('.oauth-authorize-btn', $container).on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var integrationId = $button.data('integration-id');
                
                // Open OAuth window
                MavlersCFIntegrations.initiateOAuth(integrationId);
            });
        },

        // Utility functions
        getCurrentFormId: function() {
            return $('#form_id').val() || $('#mavlers-cf-form-id').val() || 0;
        },

        getCurrentIntegrationId: function() {
            return $('.integration-card.active').data('integration-id') || '';
        },

        setLoadingState: function($element, loading) {
            if (loading) {
                $element.addClass('mavlers-loading');
            } else {
                $element.removeClass('mavlers-loading');
            }
        },

        updateIntegrationCardState: function($card) {
            var $checkbox = $('.integration-enabled-checkbox', $card);
            var $setupBtn = $('.setup-integration', $card);
            var $configureBtn = $('.configure-integration', $card);
            var isEnabled = $checkbox.is(':checked');
            var isConfigured = $configureBtn.is(':visible');
            
            if (isConfigured) {
                $setupBtn.hide();
                $configureBtn.show();
            } else {
                $setupBtn.show();
                $configureBtn.hide();
            }
        },

        closeModal: function(e) {
            if (e && $(e.target).closest('.modal-content').length && !$(e.target).hasClass('modal-overlay')) {
                return;
            }
            $('.mavlers-cf-modal').hide();
        },

        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.mavlers-cf-additional-integrations').before($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },

        refreshIntegrationCards: function() {
            // Reload the integration section via AJAX
            var formId = this.getCurrentFormId();
            
            $.ajax({
                url: mavlers_cf_integrations.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_refresh_integrations',
                    nonce: mavlers_cf_integrations.nonce,
                    form_id: formId
                },
                success: function(response) {
                    if (response.success) {
                        $('.mavlers-cf-additional-integrations').replaceWith(response.data.html);
                    }
                }
            });
        },

        validateField: function($field) {
            var value = $field.val();
            var isRequired = $field.prop('required') || $field.hasClass('required');
            var isValid = true;
            
            if (isRequired && (!value || value.trim() === '')) {
                isValid = false;
            }
            
            // Type-specific validation
            if (value && $field.attr('type') === 'email') {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                isValid = emailRegex.test(value);
            }
            
            if (value && $field.attr('type') === 'url') {
                var urlRegex = /^https?:\/\/.+/;
                isValid = urlRegex.test(value);
            }
            
            // Update field state
            $field.toggleClass('error', !isValid);
            
            return isValid;
        },

        validateIntegrationForm: function($modal) {
            var isValid = true;
            
            $('input, select, textarea', $modal).each(function() {
                if (!MavlersCFIntegrations.validateField($(this))) {
                    isValid = false;
                }
            });
            
            return isValid;
        },

        serializeIntegrationForm: function($modal) {
            var data = {};
            
            $('input, select, textarea', $modal).each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if (name && $field.attr('type') !== 'submit') {
                    if ($field.attr('type') === 'checkbox') {
                        data[name] = $field.is(':checked') ? 1 : 0;
                    } else {
                        data[name] = value;
                    }
                }
            });
            
            return data;
        },

        collectFieldMappings: function() {
            var mappings = [];
            
            $('.form-field-selector').each(function() {
                var $select = $(this);
                var integrationField = $select.data('integration-field');
                var formField = $select.val();
                
                if (formField) {
                    mappings.push({
                        integration_field: integrationField,
                        form_field: formField
                    });
                }
            });
            
            return mappings;
        },

        validateFieldMappings: function(mappings) {
            // Check for required field mappings
            var requiredFields = $('.field-item[data-required="true"]');
            var mappedRequiredFields = 0;
            
            requiredFields.each(function() {
                var fieldKey = $(this).data('field-key');
                var isMapped = mappings.some(function(mapping) {
                    return mapping.integration_field === fieldKey;
                });
                
                if (isMapped) {
                    mappedRequiredFields++;
                }
            });
            
            if (mappedRequiredFields < requiredFields.length) {
                this.showNotice('Please map all required fields', 'error');
                return false;
            }
            
            return true;
        },

        applyFieldMappings: function(mappings) {
            // Clear existing mappings
            $('.form-field-selector').val('');
            $('.field-item').removeClass('mapped');
            
            // Apply new mappings
            mappings.forEach(function(mapping) {
                var $select = $('.form-field-selector[data-integration-field="' + mapping.integration_field + '"]');
                $select.val(mapping.form_field);
                $select.closest('.field-item').addClass('mapped');
            });
            
            this.updateConnectionLines();
            this.updateMappingStats();
        },

        updateConnectionLines: function() {
            // Update visual connection lines between mapped fields
            var $container = $('.connection-lines');
            $container.empty();
            
            $('.form-field-selector').each(function() {
                var $select = $(this);
                var formField = $select.val();
                
                if (formField) {
                    // Draw connection line (simplified - would need more complex SVG drawing)
                    var $line = $('<div class="connection-line"></div>');
                    $container.append($line);
                }
            });
        },

        updateMappingStats: function() {
            var totalFields = $('.form-field-selector').length;
            var mappedFields = $('.form-field-selector').filter(function() {
                return $(this).val() !== '';
            }).length;
            
            $('#mapped-count').text(mappedFields);
            $('#total-mappable').text(totalFields);
        },

        handleKeyboardShortcuts: function(e) {
            // ESC to close modals
            if (e.keyCode === 27 && $('.mavlers-cf-modal:visible').length) {
                this.closeModal();
            }
        },

        // Initialize field mapping
        initializeFieldMapping: function() {
            this.updateMappingStats();
            this.updateConnectionLines();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MavlersCFIntegrations.init();
    });

})(jQuery); 