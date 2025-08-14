/**
 * HubSpot Integration Accessibility Enhancements
 * 
 * Provides comprehensive accessibility improvements for the HubSpot integration
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
        // HubSpot settings section
        $('#hubspot_enabled').attr('aria-label', 'Enable HubSpot integration');
        $('#hubspot_object_type').attr('aria-label', 'Select HubSpot object type');
        $('#hubspot_custom_object').attr('aria-label', 'Select custom object');
        $('#hubspot_action_type').attr('aria-label', 'Select action type');
        $('#hubspot_workflow_enabled').attr('aria-label', 'Enable workflow enrollment');

        // Field mapping table
        $('#hubspot-field-mapping-table').attr('aria-label', 'Field mapping table');
        $('.hubspot-field-mapping-row').each(function(index) {
            $(this).attr('aria-label', `Field mapping row ${index + 1}`);
        });

        // Buttons
        $('#hubspot-test-connection').attr('aria-label', 'Test HubSpot connection');
        $('#hubspot-save-settings').attr('aria-label', 'Save HubSpot settings');
        $('#hubspot-auto-map').attr('aria-label', 'Auto map fields');
        $('#hubspot-clear-mappings').attr('aria-label', 'Clear all field mappings');
    }

    /**
     * Add keyboard navigation support
     */
    function addKeyboardNavigation() {
        // Tab navigation for field mapping
        $('#hubspot-field-mapping-table').on('keydown', function(e) {
            const $current = $(e.target);
            const $rows = $('.hubspot-field-mapping-row');
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
                $('#hubspot-save-settings').trigger('click');
            }

            // Ctrl/Cmd + T to test connection
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 84) {
                e.preventDefault();
                $('#hubspot-test-connection').trigger('click');
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
        $('body').append('<div id="hubspot-live-region" aria-live="polite" aria-atomic="true" class="sr-only"></div>');

        // Announce loading states
        $(document).on('hubspot:loading', function() {
            announceToScreenReader('Loading HubSpot data...');
        });

        // Announce success states
        $(document).on('hubspot:success', function(e, message) {
            announceToScreenReader(message || 'Operation completed successfully');
        });

        // Announce error states
        $(document).on('hubspot:error', function(e, message) {
            announceToScreenReader('Error: ' + (message || 'An error occurred'));
        });
    }

    /**
     * Announce message to screen readers
     */
    function announceToScreenReader(message) {
        const $liveRegion = $('#hubspot-live-region');
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
        $(document).on('hubspot:form-loaded', function() {
            $('#hubspot_enabled').focus();
        });

        // Focus management for modals/dialogs
        $(document).on('hubspot:modal-opened', function() {
            const $modal = $('.hubspot-modal');
            const $firstFocusable = $modal.find('input, select, button, a').first();
            $firstFocusable.focus();
        });

        // Trap focus in modals
        $(document).on('keydown', '.hubspot-modal', function(e) {
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
    }

    /**
     * Add error announcements
     */
    function addErrorAnnouncements() {
        // Announce validation errors
        $(document).on('hubspot:validation-error', function(e, field, message) {
            announceToScreenReader(`Validation error in ${field}: ${message}`);
        });

        // Announce connection errors
        $(document).on('hubspot:connection-error', function(e, message) {
            announceToScreenReader(`Connection error: ${message}`);
        });

        // Announce save errors
        $(document).on('hubspot:save-error', function(e, message) {
            announceToScreenReader(`Save error: ${message}`);
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
     * Initialize all accessibility features
     */
    $(document).ready(function() {
        initializeAccessibility();
        enhanceFormValidation();
        addHighContrastSupport();
        addReducedMotionSupport();
    });

    // Expose functions for external use
    window.HubSpotAccessibility = {
        announceToScreenReader: announceToScreenReader,
        addAriaLabels: addAriaLabels,
        enhanceFormValidation: enhanceFormValidation
    };

})(jQuery); 