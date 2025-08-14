/**
 * HubSpot Integration Admin JavaScript
 * 
 * Handles global settings and connection testing for HubSpot integration
 */

(function($) {
    'use strict';

    // Global variables
    let isInitialized = false;
    const CONFIG = {
        AJAX_TIMEOUT: 30000,
        DEBOUNCE_DELAY: 300
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (isInitialized) return;
        
        initializeHubSpotSettings();
        isInitialized = true;
    });

    /**
     * Initialize HubSpot settings functionality
     */
    function initializeHubSpotSettings() {
        // Check if we're on the right page
        if (!isHubSpotSettingsPage()) {
            return;
        }
        
        // Bind events
        bindEvents();
        
        // Load initial state
        loadInitialState();
    }

    /**
     * Check if we're on a HubSpot settings page
     */
    function isHubSpotSettingsPage() {
        return window.location.href.includes('connect2form-integrations') || 
               window.location.href.includes('hubspot') ||
               $('.hubspot-settings').length > 0 ||
               $('input[name="access_token"]').length > 0 ||
               $('input[name="portal_id"]').length > 0;
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Test connection button
        $(document).on('click', '#test-hubspot-connection, .test-connection-btn', testConnection);
        
        // Save settings form
        $(document).on('submit', '#hubspot-global-settings, .hubspot-settings-form', handleSettingsSubmit);
        
        // Save settings button
        $(document).on('click', '#save-hubspot-settings, .save-settings-btn', saveSettings);
    }

    /**
     * Load initial state
     */
    function loadInitialState() {
        // Check if access token is present
        const accessToken = getAccessToken();
        if (accessToken) {
            // Could add auto-validation here if needed
        }
    }

    /**
     * Test HubSpot connection
     */
    function testConnection(e) {
        if (e) e.preventDefault();
        
        const $button = $(this);
        const $status = $('#connection-status, .connection-status');
        const originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).text('Testing...');
        $status.removeClass('connected disconnected').addClass('testing').text('Testing connection...');
        
        const accessToken = getAccessToken();
        const portalId = getPortalId();
        
        if (!accessToken) {
            showConnectionError('Please enter an access token first.');
            resetTestButton($button, originalText);
            return;
        }

        // Make AJAX request
        $.ajax({
            url: window.connect2formCFHubspot?.ajaxUrl || window.connect2formHubspot?.ajaxUrl || ajaxurl,
            type: 'POST',
            data: {
                action: 'hubspot_test_connection',
                nonce: window.connect2formCFHubspot?.nonce || window.connect2formHubspot?.nonce,
                access_token: accessToken,
                portal_id: portalId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success) {
                    showConnectionSuccess('Connection successful!');
                } else {
                    showConnectionError(response.data || 'Connection failed');
                }
            },
            error: function(xhr, status, error) {
                console.error('Test connection error:', xhr, status, error);
                showConnectionError('Connection test failed: ' + error);
            },
            complete: function() {
                resetTestButton($button, originalText);
            }
        });
    }

    /**
     * Handle settings form submit
     */
    function handleSettingsSubmit(e) {
        e.preventDefault();
        saveSettings();
    }

    /**
     * Save HubSpot settings
     */
    function saveSettings(e) {
        if (e) e.preventDefault();
        
        // console.log('Saving HubSpot settings...');
        
        const $button = $(this);
        const $form = $('#hubspot-global-settings, .hubspot-settings-form').first();
        const originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).text('Saving...');
        
        // Collect form data
        const formData = {};
        $form.find('input, select, textarea').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            if (name) {
                if ($field.attr('type') === 'checkbox') {
                    formData[name] = $field.is(':checked') ? '1' : '0';
                } else {
                    formData[name] = $field.val();
                }
            }
        });
        
        // console.log('Settings data:', formData);
        
        const ajaxData = {
            action: 'hubspot_save_global_settings', // Match the registered action
            nonce: window.connect2formCFHubspot?.nonce || window.connect2formHubspot?.nonce,
            integration_id: 'hubspot',
            settings: formData
        };
        
        // console.log('Sending AJAX request with data:', ajaxData);
        
        $.ajax({
            url: window.connect2formCFHubspot?.ajaxUrl || window.connect2formHubspot?.ajaxUrl || ajaxurl,
            type: 'POST',
            data: ajaxData,
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                // console.log('Save settings response:', response);
                
                if (response.success) {
                    showSuccess('Settings saved successfully!');
                    
                    // Update connection status if provided
                    if (response.data && response.data.connection_status) {
                        updateConnectionStatus(response.data.connection_status);
                    }
                } else {
                    showError('Error saving settings: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Save settings error:', xhr, status, error);
                showError('Error saving settings: ' + error);
            },
            complete: function() {
                // Reset button
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Get access token from form
     */
    function getAccessToken() {
        return $('input[name="access_token"], #access_token').val() || '';
    }

    /**
     * Get portal ID from form
     */
    function getPortalId() {
        return $('input[name="portal_id"], #portal_id').val() || '';
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        showMessage(message, 'success');
    }

    /**
     * Show error message
     */
    function showError(message) {
        showMessage(message, 'error');
    }

    /**
     * Show connection success
     */
    function showConnectionSuccess(message) {
        const $status = $('#connection-status, .connection-status');
        $status.removeClass('testing disconnected').addClass('connected').text(message);
    }

    /**
     * Show connection error
     */
    function showConnectionError(message) {
        const $status = $('#connection-status, .connection-status');
        $status.removeClass('testing connected').addClass('disconnected').text(message);
    }

    /**
     * Reset test button to original state
     */
    function resetTestButton($button, originalText) {
        $button.prop('disabled', false).text(originalText);
    }

    /**
     * Update connection status display
     */
    function updateConnectionStatus(status) {
        const $status = $('#connection-status, .connection-status');
        $status.removeClass('testing connected disconnected').addClass(status.toLowerCase());
        
        if (status === 'connected') {
            $status.text('Connected');
        } else {
            $status.text('Disconnected');
        }
    }

    /**
     * Show message to user
     */
    function showMessage(message, type) {
        // Try to find existing message container
        let $messageContainer = $('.hubspot-message, .notice, #setting-error-settings_updated').first();
        
        // Create message container if it doesn't exist
        if (!$messageContainer.length) {
            $messageContainer = $('<div class="notice"></div>');
            $('.hubspot-settings, .wrap').first().prepend($messageContainer);
        }
        
        // Set message content and type
        $messageContainer
            .removeClass('notice-success notice-error updated error')
            .addClass('notice-' + (type === 'success' ? 'success' : 'error'))
            .html('<p>' + message + '</p>')
            .show();
        
        // Auto-hide after delay
        setTimeout(function() {
            $messageContainer.fadeOut();
        }, 4000);
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $messageContainer.offset().top - 50
        }, 300);
    }

    /**
     * Debounce function
     */
    function debounce(func, delay) {
        let timeoutId;
        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    }

})(jQuery);
