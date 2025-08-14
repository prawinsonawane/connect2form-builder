/**
 * Mailchimp Integration Accessibility Enhancements
 * 
 * Provides comprehensive accessibility improvements for the Mailchimp integration
 */

(function($) {
    'use strict';

    /**
     * Initialize accessibility features
     */
    function initializeAccessibility() {
        addAriaLabels();
        addKeyboardNavigation();
        addScreenReaderSupport();
        addFocusManagement();
        addErrorAnnouncements();
    }

    /**
     * Add ARIA labels to form elements
     */
    function addAriaLabels() {
        // Mailchimp settings section
        $('#mailchimp_enabled').attr('aria-label', 'Enable Mailchimp integration');
        $('#mailchimp_api_key').attr('aria-label', 'Enter your Mailchimp API key');
        $('#mailchimp_audience_id').attr('aria-label', 'Select Mailchimp audience');
        $('#mailchimp_double_optin').attr('aria-label', 'Enable double opt-in');
        $('#mailchimp_update_existing').attr('aria-label', 'Update existing subscribers');
        $('#mailchimp_tags').attr('aria-label', 'Add tags for subscribers');

        // Field mapping table
        $('#mailchimp-field-mapping-table').attr('aria-label', 'Field mapping table');
        $('.mailchimp-field-mapping-row').each(function(index) {
            $(this).attr('aria-label', `Field mapping row ${index + 1}`);
        });

        // Buttons
        $('#mailchimp-test-connection').attr('aria-label', 'Test Mailchimp connection');
        $('#mailchimp-save-settings').attr('aria-label', 'Save Mailchimp settings');
        $('#mailchimp-auto-map').attr('aria-label', 'Auto map fields');
        $('#mailchimp-clear-mappings').attr('aria-label', 'Clear all field mappings');
        $('#mailchimp-refresh-audiences').attr('aria-label', 'Refresh audiences list');
    }

    /**
     * Add keyboard navigation support
     */
    function addKeyboardNavigation() {
        // Tab navigation for field mapping
        $('#mailchimp-field-mapping-table').on('keydown', function(e) {
            const $current = $(e.target);
            const $rows = $('.mailchimp-field-mapping-row');
            const currentIndex = $rows.index($current.closest('tr'));

            switch (e.keyCode) {
                case 9: // Tab
                    // Allow default tab behavior
                    break;
                case 13: // Enter
                    e.preventDefault();
                    $current.trigger('click');
                    break;
                case 32: // Space
                    e.preventDefault();
                    $current.trigger('click');
                    break;
                case 38: // Up arrow
                    e.preventDefault();
                    navigateFieldMapping(currentIndex - 1, $rows);
                    break;
                case 40: // Down arrow
                    e.preventDefault();
                    navigateFieldMapping(currentIndex + 1, $rows);
                    break;
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + S to save settings
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                e.preventDefault();
                $('#mailchimp-save-settings').trigger('click');
            }

            // Ctrl/Cmd + T to test connection
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 84) {
                e.preventDefault();
                $('#mailchimp-test-connection').trigger('click');
            }

            // Ctrl/Cmd + R to refresh audiences
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 82) {
                e.preventDefault();
                $('#mailchimp-refresh-audiences').trigger('click');
            }
        });
    }

    /**
     * Navigate field mapping with keyboard
     */
    function navigateFieldMapping(index, $rows) {
        if (index >= 0 && index < $rows.length) {
            $rows.eq(index).find('select, input').first().focus();
        }
    }

    /**
     * Add screen reader support
     */
    function addScreenReaderSupport() {
        // Live regions for dynamic content
        $('body').append('<div id="mailchimp-live-region" aria-live="polite" aria-atomic="true" class="sr-only"></div>');

        // Announce loading states
        $(document).on('mailchimp:loading', function() {
            announceToScreenReader('Loading Mailchimp data...');
        });

        // Announce success states
        $(document).on('mailchimp:success', function(e, message) {
            announceToScreenReader(message || 'Operation completed successfully');
        });

        // Announce error states
        $(document).on('mailchimp:error', function(e, message) {
            announceToScreenReader('Error: ' + (message || 'An error occurred'));
        });

        // Announce audience selection
        $(document).on('mailchimp:audience-selected', function(e, audienceName) {
            announceToScreenReader(`Selected audience: ${audienceName}`);
        });
    }

    /**
     * Announce message to screen readers
     */
    function announceToScreenReader(message) {
        const $liveRegion = $('#mailchimp-live-region');
        $liveRegion.text(message);
        
        // Clear after a delay
        setTimeout(function() {
            $liveRegion.text('');
        }, 3000);
    }

    /**
     * Add focus management
     */
    function addFocusManagement() {
        // Focus first field when form loads
        $(document).on('mailchimp:form-loaded', function() {
            $('#mailchimp_enabled').focus();
        });

        // Focus management for modals/dialogs
        $(document).on('mailchimp:modal-opened', function() {
            const $modal = $('.mailchimp-modal');
            const $firstFocusable = $modal.find('input, select, button, a').first();
            $firstFocusable.focus();
        });

        // Trap focus in modals
        $(document).on('keydown', '.mailchimp-modal', function(e) {
            if (e.keyCode === 9) { // Tab
                const $focusable = $(this).find('input, select, button, a');
                const $first = $focusable.first();
                const $last = $focusable.last();

                if (e.shiftKey) {
                    if (document.activeElement === $first[0]) {
                        e.preventDefault();
                        $last.focus();
                    }
                } else {
                    if (document.activeElement === $last[0]) {
                        e.preventDefault();
                        $first.focus();
                    }
                }
            }
        });

        // Focus management for audience selection
        $(document).on('mailchimp:audience-loaded', function() {
            $('#mailchimp_audience_id').focus();
        });
    }

    /**
     * Add error announcements
     */
    function addErrorAnnouncements() {
        // Announce validation errors
        $(document).on('mailchimp:validation-error', function(e, field, message) {
            announceToScreenReader(`Validation error in ${field}: ${message}`);
        });

        // Announce connection errors
        $(document).on('mailchimp:connection-error', function(e, message) {
            announceToScreenReader(`Connection error: ${message}`);
        });

        // Announce save errors
        $(document).on('mailchimp:save-error', function(e, message) {
            announceToScreenReader(`Save error: ${message}`);
        });

        // Announce API errors
        $(document).on('mailchimp:api-error', function(e, message) {
            announceToScreenReader(`API error: ${message}`);
        });
    }

    /**
     * Enhance form validation for accessibility
     */
    function enhanceFormValidation() {
        // Add aria-invalid to invalid fields
        $('input, select, textarea').on('invalid', function() {
            $(this).attr('aria-invalid', 'true');
        });

        // Remove aria-invalid when field becomes valid
        $('input, select, textarea').on('input', function() {
            if (this.checkValidity()) {
                $(this).attr('aria-invalid', 'false');
            }
        });

        // Add error descriptions
        $('input, select, textarea').each(function() {
            const $field = $(this);
            const $errorContainer = $('<div class="field-error" aria-live="polite"></div>');
            $field.after($errorContainer);
        });

        // Add required field indicators
        $('input[required], select[required], textarea[required]').each(function() {
            const $field = $(this);
            const $label = $field.closest('label');
            if ($label.length) {
                $label.append('<span class="required-indicator" aria-label="required"> *</span>');
            }
        });
    }

    /**
     * Add high contrast mode support
     */
    function addHighContrastSupport() {
        // Check for high contrast mode
        const prefersHighContrast = window.matchMedia('(prefers-contrast: high)').matches;
        
        if (prefersHighContrast) {
            $('body').addClass('high-contrast-mode');
        }

        // Listen for changes
        window.matchMedia('(prefers-contrast: high)').addEventListener('change', function(e) {
            if (e.matches) {
                $('body').addClass('high-contrast-mode');
            } else {
                $('body').removeClass('high-contrast-mode');
            }
        });
    }

    /**
     * Add reduced motion support
     */
    function addReducedMotionSupport() {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        
        if (prefersReducedMotion) {
            $('body').addClass('reduced-motion');
        }

        // Listen for changes
        window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', function(e) {
            if (e.matches) {
                $('body').addClass('reduced-motion');
            } else {
                $('body').removeClass('reduced-motion');
            }
        });
    }

    /**
     * Add audience selection accessibility
     */
    function addAudienceSelectionAccessibility() {
        // Announce audience count
        $(document).on('mailchimp:audiences-loaded', function(e, count) {
            announceToScreenReader(`${count} audiences loaded`);
        });

        // Announce audience selection
        $('#mailchimp_audience_id').on('change', function() {
            const selectedText = $(this).find('option:selected').text();
            announceToScreenReader(`Selected: ${selectedText}`);
        });

        // Add descriptive labels for audience options
        $('#mailchimp_audience_id option').each(function() {
            const $option = $(this);
            const audienceName = $option.text();
            const audienceId = $option.val();
            $option.attr('aria-label', `${audienceName} (ID: ${audienceId})`);
        });
    }

    /**
     * Add field mapping accessibility
     */
    function addFieldMappingAccessibility() {
        // Announce field mapping updates
        $(document).on('mailchimp:field-mapped', function(e, formField, mailchimpField) {
            announceToScreenReader(`Mapped ${formField} to ${mailchimpField}`);
        });

        // Announce auto-mapping completion
        $(document).on('mailchimp:auto-mapping-complete', function(e, count) {
            announceToScreenReader(`Auto-mapped ${count} fields`);
        });

        // Add descriptive labels for merge fields
        $('.mailchimp-merge-field-option').each(function() {
            const $option = $(this);
            const fieldName = $option.text();
            const fieldType = $option.data('type');
            $option.attr('aria-label', `${fieldName} (${fieldType})`);
        });
    }

    /**
     * Initialize all accessibility features
     */
    $(document).ready(function() {
        initializeAccessibility();
        enhanceFormValidation();
        addHighContrastSupport();
        addReducedMotionSupport();
        addAudienceSelectionAccessibility();
        addFieldMappingAccessibility();
    });

    // Expose functions for external use
    window.MailchimpAccessibility = {
        announceToScreenReader: announceToScreenReader,
        addAriaLabels: addAriaLabels,
        enhanceFormValidation: enhanceFormValidation
    };

})(jQuery); 