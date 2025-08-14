/**
 * HubSpot Global Settings JavaScript
 * 
 * Handles global HubSpot integration settings
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        AJAX_TIMEOUT: 30000,
        MESSAGE_DISPLAY_TIME: 5000
    };

    /**
     * Initialize HubSpot global settings
     */
    function initializeHubSpotGlobalSettings() {
        try {
            // Initialize event handlers
            initializeGlobalEventHandlers();
            
            // Load saved settings
            loadSavedGlobalSettings();
            
        } catch (error) {
            console.error('Error initializing HubSpot global settings:', error);
            showMessage('Failed to initialize HubSpot global settings. Please refresh the page.', 'error');
        }
    }

    /**
     * Initialize global event handlers
     */
    function initializeGlobalEventHandlers() {
        // Test connection button - use the specific ID from template
        $('#test-connection').on('click', handleTestConnection);

        // Note: Save settings is handled by the template's generic JavaScript
        // We don't need to handle it here to avoid conflicts

        // Enable/disable toggle - look for any enabled field
        $('[id*="enabled"]').on('change', function() {
            const isEnabled = $(this).is(':checked');
            $('.integration-settings-section').toggle(isEnabled);
        });
    }

    /**
     * Handle test connection
     */
    function handleTestConnection() {
        const button = $(this);
        const originalText = button.text();
        
        // Show loading state
        button.prop('disabled', true).text('Testing...');
        
        // Get values from form fields - use generic field names
        const accessToken = $('#access_token').val();
        const portalId = $('#portal_id').val();
        
        if (!accessToken || !portalId) {
            showMessage('Please enter both Access Token and Portal ID', 'error');
            button.prop('disabled', false).text(originalText);
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hubspot_test_connection',
                nonce: $('[name="mavlers_cf_nonce"]').val(),
                access_token: accessToken,
                portal_id: portalId
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success) {
                    showMessage('Connection successful! HubSpot integration is working.', 'success');
                    // Update connection status
                    $('.status-badge').removeClass('unconfigured').addClass('configured')
                        .html('<span class="dashicons dashicons-yes-alt"></span> Connected');
                } else {
                    const errorMessage = response.data?.message || 'Connection failed';
                    showMessage('Connection failed: ' + errorMessage, 'error');
                    // Update connection status
                    $('.status-badge').removeClass('configured').addClass('unconfigured')
                        .html('<span class="dashicons dashicons-warning"></span> Not Connected');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                showMessage('Connection failed: ' + errorMessage, 'error');
                // Update connection status
                $('.status-badge').removeClass('configured').addClass('unconfigured')
                    .html('<span class="dashicons dashicons-warning"></span> Not Connected');
            },
            complete: function() {
                // Hide loading state
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Handle save global settings
     */
    function handleSaveGlobalSettings() {
        const button = $(this);
        const originalText = button.text();
        
        // Show loading state
        button.prop('disabled', true).text('Saving...');
        
        // Get the integration ID from the button
        const integrationId = button.data('integration');
        
        // Collect all form data from the form
        const form = $('#' + integrationId + '-settings-form');
        const settings = {};
        
        // Get all form fields
        form.find('input, textarea, select').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            const type = $field.attr('type');
            const value = $field.val();
            const isChecked = $field.is(':checked');
            
            if (name) {
                if (type === 'checkbox') {
                    settings[name] = isChecked ? '1' : '0';
                } else {
                    settings[name] = value;
                }
            }
        });
        
        console.log('Collected settings:', settings);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: integrationId + '_save_global_settings',
                nonce: $('[name="mavlers_cf_nonce"]').val(),
                integration_id: integrationId,
                settings: settings
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response.success) {
                    const message = response.data?.message || 'Settings saved successfully!';
                    showMessage(message, 'success');
                    
                    // Update status if needed
                    if (response.data?.configured) {
                        $('.status-badge').removeClass('unconfigured').addClass('configured')
                            .html('<span class="dashicons dashicons-yes-alt"></span> Connected');
                    }
                } else {
                    const errorMessage = response.data?.message || 'Failed to save settings';
                    showMessage('Failed to save settings: ' + errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Network error occurred';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                showMessage('Failed to save settings: ' + errorMessage, 'error');
            },
            complete: function() {
                // Hide loading state
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Load saved global settings
     */
    function loadSavedGlobalSettings() {
        // Settings will be loaded by PHP template
        // This function can be used for any additional JavaScript initialization
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        // Create or update message element
        let messageElement = $('#hubspot-message');
        if (messageElement.length === 0) {
            messageElement = $('<div id="hubspot-message" style="position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 15px; border-radius: 4px; color: white; font-weight: 500;"></div>');
            $('body').append(messageElement);
        }
        
        // Set message content and style
        messageElement.text(message);
        messageElement.removeClass('success error warning info');
        messageElement.addClass(type);
        
        // Set background color based on type
        if (type === 'success') {
            messageElement.css('background-color', '#28a745');
        } else if (type === 'error') {
            messageElement.css('background-color', '#dc3545');
        } else if (type === 'warning') {
            messageElement.css('background-color', '#ffc107');
            messageElement.css('color', '#000');
        } else if (type === 'info') {
            messageElement.css('background-color', '#17a2b8');
        }
        
        // Show message
        messageElement.fadeIn();
        
        // Hide after delay
        setTimeout(() => {
            messageElement.fadeOut();
        }, CONFIG.MESSAGE_DISPLAY_TIME);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we're on the HubSpot global settings page
        if ($('#hubspot-settings-form').length > 0 || $('.mavlers-integration-settings').length > 0) {
            initializeHubSpotGlobalSettings();
        }
    });

    // Expose functions for debugging (only in development)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        window.hubspotGlobalDebug = {
            handleTestConnection,
            handleSaveGlobalSettings,
            showMessage
        };
    }

})(jQuery); 