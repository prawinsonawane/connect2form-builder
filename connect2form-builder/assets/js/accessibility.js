/**
 * Connect2Form Accessibility Enhancement Script
 * 
 * Provides enhanced accessibility features for WCAG 2.1 AA compliance
 * 
 * @package Connect2Form
 * @since 2.0.0
 */

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Only run on Connect2Form admin pages
        if (!document.body.classList.contains('connect2form-admin')) {
            return;
        }
        
        enhanceKeyboardNavigation();
        improveFocusManagement();
        addLiveRegions();
        enhanceFormBuilder();
    }
    
    /**
     * Enhance keyboard navigation
     */
    function enhanceKeyboardNavigation() {
        // Add keyboard support to drag-drop interface
        var draggableItems = document.querySelectorAll('.form-field-type');
        draggableItems.forEach(function(item) {
            if (!item.hasAttribute('tabindex')) {
                item.setAttribute('tabindex', '0');
            }
            
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    simulateClick(item);
                }
                
                // Arrow key navigation
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    navigateToSibling(item, e.key === 'ArrowDown');
                }
            });
        });
        
        // Enhance modal keyboard trapping
        var modals = document.querySelectorAll('.connect2form-modal');
        modals.forEach(function(modal) {
            modal.addEventListener('keydown', trapFocusInModal);
        });
    }
    
    /**
     * Improve focus management
     */
    function improveFocusManagement() {
        // Store and restore focus when dynamic content changes
        var lastFocusedElement = null;
        
        document.addEventListener('focusin', function(e) {
            lastFocusedElement = e.target;
        });
        
        // Restore focus after AJAX operations
        document.addEventListener('connect2form:ajax-complete', function() {
            if (lastFocusedElement && document.body.contains(lastFocusedElement)) {
                lastFocusedElement.focus();
            }
        });
        
        // Manage focus for error states
        document.addEventListener('connect2form:validation-error', function(e) {
            var firstError = document.querySelector('.connect2form-error');
            if (firstError) {
                firstError.focus();
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
    
    /**
     * Add live regions for dynamic content
     */
    function addLiveRegions() {
        // Create status live region if it doesn't exist
        if (!document.getElementById('connect2form-status')) {
            var statusRegion = document.createElement('div');
            statusRegion.id = 'connect2form-status';
            statusRegion.className = 'sr-only';
            statusRegion.setAttribute('aria-live', 'polite');
            statusRegion.setAttribute('aria-atomic', 'true');
            document.body.appendChild(statusRegion);
        }
        
        // Create alert live region for errors
        if (!document.getElementById('connect2form-alerts')) {
            var alertRegion = document.createElement('div');
            alertRegion.id = 'connect2form-alerts';
            alertRegion.className = 'sr-only';
            alertRegion.setAttribute('aria-live', 'assertive');
            alertRegion.setAttribute('aria-atomic', 'true');
            document.body.appendChild(alertRegion);
        }
    }
    
    /**
     * Enhance form builder accessibility
     */
    function enhanceFormBuilder() {
        var formBuilder = document.getElementById('form-builder');
        if (!formBuilder) {
            return;
        }
        
        // Add role and label to form builder
        formBuilder.setAttribute('role', 'application');
        formBuilder.setAttribute('aria-label', 'Form Builder Interface');
        
        // Add instructions for screen readers
        var instructions = document.createElement('div');
        instructions.className = 'sr-only';
        instructions.id = 'form-builder-instructions';
        instructions.textContent = 'Use arrow keys to navigate between form elements. Press Enter or Space to select an element. Press Tab to exit the form builder.';
        formBuilder.insertBefore(instructions, formBuilder.firstChild);
        
        formBuilder.setAttribute('aria-describedby', 'form-builder-instructions');
        
        // Enhance field properties panel
        var propertiesPanel = document.querySelector('.field-properties');
        if (propertiesPanel) {
            propertiesPanel.setAttribute('role', 'region');
            propertiesPanel.setAttribute('aria-label', 'Field Properties');
        }
    }
    
    /**
     * Simulate click event
     */
    function simulateClick(element) {
        var event = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            view: window
        });
        element.dispatchEvent(event);
    }
    
    /**
     * Navigate to next/previous sibling
     */
    function navigateToSibling(element, isNext) {
        var siblings = Array.from(element.parentNode.children);
        var currentIndex = siblings.indexOf(element);
        var targetIndex = isNext ? currentIndex + 1 : currentIndex - 1;
        
        if (targetIndex >= 0 && targetIndex < siblings.length) {
            siblings[targetIndex].focus();
        }
    }
    
    /**
     * Trap focus within modal
     */
    function trapFocusInModal(e) {
        var modal = e.currentTarget;
        var focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length === 0) {
            return;
        }
        
        var firstElement = focusableElements[0];
        var lastElement = focusableElements[focusableElements.length - 1];
        
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                }
            } else {
                if (document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        }
        
        if (e.key === 'Escape') {
            var closeButton = modal.querySelector('.modal-close, .close-modal');
            if (closeButton) {
                closeButton.click();
            }
        }
    }
    
    /**
     * Announce status to screen readers
     */
    function announceStatus(message, isError) {
        var region = document.getElementById(isError ? 'connect2form-alerts' : 'connect2form-status');
        if (region) {
            region.textContent = message;
            
            // Clear after a delay to allow re-announcement of same message
            setTimeout(function() {
                region.textContent = '';
            }, 1000);
        }
    }
    
    // Export functions for use by other scripts
    window.connect2formA11y = {
        announceStatus: announceStatus,
        trapFocusInModal: trapFocusInModal
    };
    
})();
