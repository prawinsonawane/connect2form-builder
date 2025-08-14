/**
 * Mavlers Contact Forms - Field Mapper JavaScript
 * 
 * JavaScript functionality specific to field mapping
 */

(function($) {
    'use strict';

    // Global field mapper object
    window.MavlersCFFieldMapper = {
        init: function() {
            this.bindEvents();
            this.initializeDragAndDrop();
            this.updateMappingStats();
        },

        // Event bindings
        bindEvents: function() {
            // Field mapping change handlers
            $(document).on('change', '.form-field-selector', this.handleFieldMappingChange.bind(this));
            $(document).on('click', '.remove-mapping', this.handleRemoveMapping.bind(this));
            
            // Auto mapping handlers
            $(document).on('click', '.auto-map-fields', this.handleAutoMapFields.bind(this));
            $(document).on('click', '.quick-map-btn', this.handleQuickMap.bind(this));
            
            // Field mapping save
            $(document).on('click', '.save-field-mapping', this.handleSaveFieldMapping.bind(this));
            
            // Clear all mappings
            $(document).on('click', '.clear-all-mappings', this.handleClearAllMappings.bind(this));
        },

        // Handle field mapping change
        handleFieldMappingChange: function(e) {
            var $select = $(e.target);
            var $fieldItem = $select.closest('.field-item');
            var formField = $select.val();
            
            // Update visual state
            if (formField) {
                $fieldItem.addClass('mapped');
                this.showMappedInfo($fieldItem, formField);
            } else {
                $fieldItem.removeClass('mapped');
                this.hideMappedInfo($fieldItem);
            }
            
            // Update mapping stats and connections
            this.updateMappingStats();
            this.updateConnectionLines();
            this.validateFieldMappings();
        },

        // Handle remove mapping
        handleRemoveMapping: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            var $fieldItem = $button.closest('.field-item');
            var $select = $fieldItem.find('.form-field-selector');
            
            // Clear the mapping
            $select.val('').trigger('change');
            
            // Add animation
            $fieldItem.addClass('just-unmapped');
            setTimeout(function() {
                $fieldItem.removeClass('just-unmapped');
            }, 300);
        },

        // Handle auto map fields
        handleAutoMapFields: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            var integrationId = $button.data('integration-id');
            
            $button.prop('disabled', true).text('Auto Mapping...');
            
            // Check if global integrations object exists
            if (typeof mavlersCFIntegrations === 'undefined') {
                this.showNotice('Integration system not properly initialized', 'error');
                $button.prop('disabled', false).text('Auto Map Fields');
                return;
            }
            
            $.ajax({
                url: mavlersCFIntegrations.ajaxurl,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_auto_map_fields',
                    nonce: mavlersCFIntegrations.nonce,
                    form_id: this.getCurrentFormId(),
                    integration_id: integrationId
                },
                success: function(response) {
                    if (response.success && response.data.mappings) {
                        MavlersCFFieldMapper.applyFieldMappings(response.data.mappings);
                        MavlersCFFieldMapper.showNotice('Fields auto-mapped successfully', 'success');
                    } else {
                        MavlersCFFieldMapper.showNotice(response.data.message || 'Auto mapping failed', 'error');
                    }
                },
                error: function() {
                    MavlersCFFieldMapper.showNotice('Connection error during auto mapping', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Auto Map Fields');
                }
            });
        },

        // Handle quick map
        handleQuickMap: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            var $fieldItem = $button.closest('.field-item');
            var integrationField = $fieldItem.data('field-id');
            var $select = $fieldItem.find('.form-field-selector');
            
            // Try to find a matching form field by name similarity
            var bestMatch = this.findBestFieldMatch(integrationField);
            if (bestMatch) {
                $select.val(bestMatch).trigger('change');
                $fieldItem.addClass('just-mapped');
                setTimeout(function() {
                    $fieldItem.removeClass('just-mapped');
                }, 300);
            } else {
                this.showNotice('No suitable match found for this field', 'warning');
            }
        },

        // Handle save field mapping
        handleSaveFieldMapping: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            var mappings = this.collectFieldMappings();
            
            // Validate mappings
            if (!this.validateFieldMappings(mappings)) {
                this.showNotice('Please check required field mappings', 'error');
                return;
            }
            
            $button.prop('disabled', true).text('Saving...');
            
            // Check if global integrations object exists
            if (typeof mavlersCFIntegrations === 'undefined') {
                this.showNotice('Integration system not properly initialized', 'error');
                $button.prop('disabled', false).text('Save Field Mapping');
                return;
            }
            
            $.ajax({
                url: mavlersCFIntegrations.ajaxurl,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_save_field_mapping',
                    nonce: mavlersCFIntegrations.nonce,
                    form_id: this.getCurrentFormId(),
                    integration_id: this.getCurrentIntegrationId(),
                    mappings: JSON.stringify(mappings)
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFFieldMapper.showNotice('Field mappings saved successfully', 'success');
                        // Optionally close modal or refresh view
                        if (typeof MavlersCFIntegrations !== 'undefined') {
                            MavlersCFIntegrations.closeModal();
                        }
                    } else {
                        MavlersCFFieldMapper.showNotice(response.data.message || 'Failed to save field mappings', 'error');
                    }
                },
                error: function() {
                    MavlersCFFieldMapper.showNotice('Connection error while saving', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Field Mapping');
                }
            });
        },

        // Handle clear all mappings
        handleClearAllMappings: function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to clear all field mappings?')) {
                $('.form-field-selector').val('').trigger('change');
                this.showNotice('All field mappings cleared', 'info');
            }
        },

        // Initialize drag and drop
        initializeDragAndDrop: function() {
            // Check if jQuery UI is available
            if (!$.fn.draggable || !$.fn.droppable) {
                console.warn('jQuery UI is required for drag and drop field mapping');
                return;
            }
            
            // Make form fields draggable
            $('.form-fields-column .field-item').draggable({
                helper: 'clone',
                cursor: 'move',
                revert: 'invalid'
            });
            
            // Make integration fields droppable
            $('.integration-fields-column .field-item').droppable({
                accept: '.form-fields-column .field-item',
                hoverClass: 'drag-over',
                drop: function(event, ui) {
                    var $droppedField = ui.draggable;
                    var $targetField = $(this);
                    var formFieldId = $droppedField.data('field-id');
                    var $select = $targetField.find('.form-field-selector');
                    
                    // Set the mapping
                    $select.val(formFieldId).trigger('change');
                }
            });
        },

        // Show mapped field info
        showMappedInfo: function($fieldItem, formField) {
            var $formFieldItem = $('.form-fields-column .field-item[data-field-id="' + formField + '"]');
            var formFieldLabel = $formFieldItem.find('.field-label').text() || formField;
            
            var $mappedInfo = $fieldItem.find('.mapped-field-info');
            if ($mappedInfo.length === 0) {
                $mappedInfo = $('<div class="mapped-field-info"></div>');
                $fieldItem.find('.mapping-controls').append($mappedInfo);
            }
            
            $mappedInfo.html('<div class="mapped-to">Mapped to: <strong>' + formFieldLabel + '</strong></div>');
        },

        // Hide mapped field info
        hideMappedInfo: function($fieldItem) {
            $fieldItem.find('.mapped-field-info').remove();
        },

        // Find best field match
        findBestFieldMatch: function(integrationField) {
            var bestMatch = null;
            var bestScore = 0;
            var integrationFieldLower = integrationField.toLowerCase();
            
            $('.form-field-selector option').each(function() {
                var $option = $(this);
                var optionValue = $option.val();
                var optionText = $option.text().toLowerCase();
                
                if (!optionValue) return;
                
                // Calculate similarity score
                var score = 0;
                
                // Exact match
                if (optionText === integrationFieldLower) {
                    score = 100;
                } 
                // Contains match
                else if (optionText.includes(integrationFieldLower) || integrationFieldLower.includes(optionText)) {
                    score = 80;
                }
                // Word similarity
                else {
                    var integrationWords = integrationFieldLower.split(/[\s_-]+/);
                    var optionWords = optionText.split(/[\s_-]+/);
                    var matchedWords = 0;
                    
                    integrationWords.forEach(function(word) {
                        if (optionWords.includes(word)) {
                            matchedWords++;
                        }
                    });
                    
                    if (matchedWords > 0) {
                        score = (matchedWords / Math.max(integrationWords.length, optionWords.length)) * 60;
                    }
                }
                
                // Prefer unmapped fields
                var isAlreadyMapped = $('.form-field-selector').filter(function() {
                    return $(this).val() === optionValue;
                }).length > 0;
                
                if (isAlreadyMapped) {
                    score *= 0.5;
                }
                
                if (score > bestScore) {
                    bestScore = score;
                    bestMatch = optionValue;
                }
            });
            
            return bestScore > 30 ? bestMatch : null;
        },

        // Collect field mappings
        collectFieldMappings: function() {
            var mappings = {};
            
            $('.integration-fields-column .field-item').each(function() {
                var $item = $(this);
                var integrationField = $item.data('field-id');
                var formField = $item.find('.form-field-selector').val();
                
                if (integrationField && formField) {
                    mappings[integrationField] = formField;
                }
            });
            
            return mappings;
        },

        // Validate field mappings
        validateFieldMappings: function(mappings) {
            if (!mappings) {
                mappings = this.collectFieldMappings();
            }
            
            var isValid = true;
            var requiredFields = [];
            
            // Check for required fields
            $('.integration-fields-column .field-item').each(function() {
                var $item = $(this);
                var isRequired = $item.find('.required-indicator').length > 0;
                var integrationField = $item.data('field-id');
                var formField = $item.find('.form-field-selector').val();
                
                if (isRequired) {
                    requiredFields.push(integrationField);
                    if (!formField) {
                        $item.addClass('validation-error');
                        isValid = false;
                    } else {
                        $item.removeClass('validation-error');
                    }
                }
            });
            
            // Update validation status
            var $validationStatus = $('.validation-status');
            if (isValid) {
                $validationStatus.removeClass('invalid').addClass('valid').text('All required fields mapped');
            } else {
                $validationStatus.removeClass('valid').addClass('invalid').text('Missing required field mappings');
            }
            
            return isValid;
        },

        // Apply field mappings
        applyFieldMappings: function(mappings) {
            Object.keys(mappings).forEach(function(integrationField) {
                var formField = mappings[integrationField];
                var $fieldItem = $('.integration-fields-column .field-item[data-field-id="' + integrationField + '"]');
                var $select = $fieldItem.find('.form-field-selector');
                
                $select.val(formField).trigger('change');
            });
            
            this.updateMappingStats();
            this.updateConnectionLines();
        },

        // Update connection lines
        updateConnectionLines: function() {
            var $container = $('.connection-lines');
            if ($container.length === 0) return;
            
            $container.empty();
            
            $('.integration-fields-column .field-item').each(function() {
                var $integrationField = $(this);
                var formFieldId = $integrationField.find('.form-field-selector').val();
                
                if (formFieldId) {
                    var $formField = $('.form-fields-column .field-item[data-field-id="' + formFieldId + '"]');
                    
                    if ($formField.length) {
                        // Calculate positions (simplified - would need more complex calculation for actual lines)
                        var $line = $('<div class="connection-line"></div>');
                        $container.append($line);
                    }
                }
            });
        },

        // Update mapping stats
        updateMappingStats: function() {
            var totalFields = $('.integration-fields-column .field-item').length;
            var mappedFields = $('.integration-fields-column .field-item.mapped').length;
            
            $('#mapped-count').text(mappedFields);
            $('#total-mappable').text(totalFields);
        },

        // Get current form ID
        getCurrentFormId: function() {
            return $('#post_ID').val() || $('[name="form_id"]').val() || 0;
        },

        // Get current integration ID
        getCurrentIntegrationId: function() {
            return $('.integration-card.active').data('integration-id') || $('#integration-id').val() || '';
        },

        // Show notice
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.field-mapping-interface').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Load field mappings
        loadFieldMappings: function(integrationId, formId) {
            if (typeof mavlersCFIntegrations === 'undefined') {
                console.warn('mavlersCFIntegrations object not found');
                return;
            }
            
            $.ajax({
                url: mavlersCFIntegrations.ajaxurl,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_get_field_mappings',
                    nonce: mavlersCFIntegrations.nonce,
                    form_id: formId,
                    integration_id: integrationId
                },
                success: function(response) {
                    if (response.success && response.data.mappings) {
                        MavlersCFFieldMapper.applyFieldMappings(response.data.mappings);
                    }
                },
                error: function() {
                    console.warn('Failed to load existing field mappings');
                }
            });
        },

        // Initialize field mapping interface
        initializeFieldMappingInterface: function(integrationId, formId) {
            this.init();
            this.loadFieldMappings(integrationId, formId);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.field-mapping-interface').length) {
            MavlersCFFieldMapper.init();
        }
    });

    // Auto-initialize when field mapping modal opens
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).hasClass('field-mapping-interface') || $(e.target).find('.field-mapping-interface').length) {
            setTimeout(function() {
                MavlersCFFieldMapper.init();
            }, 100);
        }
    });

})(jQuery); 