/**
 * HubSpot Form Settings JavaScript
 * 
 * Handles HubSpot integration settings, field mapping, and AJAX operations
 */

(function($) {
    'use strict';

    // Global variables
    let currentFormId = 0;
    let currentSettings = {};
    let isLoadingData = false;

    // Initialize when document is ready
    $(document).ready(function() {
        initializeHubSpotSettings();
    });

    /**
     * Initialize HubSpot settings
     */
    function initializeHubSpotSettings() {
        // Get form ID from URL or data attribute
        currentFormId = getFormIdFromUrl() || $('.hubspot-form-settings').data('form-id') || 0;
        
        // Ensure required variables are available
        if (typeof ajaxurl === 'undefined') {
            window.ajaxurl = '/wp-admin/admin-ajax.php';
        }
        
        if (typeof mavlers_cf_nonce === 'undefined') {
            window.mavlers_cf_nonce = 'fallback-nonce';
        }
        
        // Check if mavlers_cf_hubspot_ajax is available
        if (typeof mavlers_cf_hubspot_ajax === 'undefined') {
            window.mavlers_cf_hubspot_ajax = {
                ajax_url: window.ajaxurl || '/wp-admin/admin-ajax.php',
                nonce: window.mavlers_cf_nonce || 'fallback-nonce',
                form_id: currentFormId || 0
            };
        }
        
        // Bind events
        bindHubSpotEvents();
        
        // Load initial settings
        loadHubSpotSettings();
    }

    /**
     * Get form ID from URL
     */
    function getFormIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('post') || urlParams.get('form_id') || 0;
    }

    /**
     * Bind HubSpot events
     */
    function bindHubSpotEvents() {
        // Toggle sections based on checkboxes
        $('#mavlers-cf-hubspot-deal-enabled').on('change', function() {
            $('#mavlers-cf-hubspot-deal-fields').toggle(this.checked);
        });
        
        $('#mavlers-cf-hubspot-workflow-enabled').on('change', function() {
            $('#mavlers-cf-hubspot-workflow-fields').toggle(this.checked);
        });
        
        $('#mavlers-cf-hubspot-company-enabled').on('change', function() {
            $('#mavlers-cf-hubspot-company-fields').toggle(this.checked);
        });

        // Test connection button
        $('.hubspot-test-connection').on('click', function(e) {
            e.preventDefault();
            testHubSpotConnection();
        });

        // Save global settings button
        $('.hubspot-save-global-settings').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            saveHubSpotGlobalSettings();
        });
    }

    /**
     * Load HubSpot settings
     */
    function loadHubSpotSettings() {
        // Load global settings
        const globalSettings = {
            access_token: $('#hubspot-access-token').val(),
            portal_id: $('#hubspot-portal-id').val(),
            enable_analytics: $('#hubspot-enable-analytics').is(':checked'),
            enable_webhooks: $('#hubspot-enable-webhooks').is(':checked'),
            batch_processing: $('#hubspot-batch-processing').is(':checked')
        };
        
        // Load form-specific settings
        const formSettings = {
            enabled: $('#mavlers-cf-hubspot-enable').is(':checked'),
            contact_enabled: $('#mavlers-cf-hubspot-contact-enabled').is(':checked'),
            deal_enabled: $('#mavlers-cf-hubspot-deal-enabled').is(':checked'),
            workflow_enabled: $('#mavlers-cf-hubspot-workflow-enabled').is(':checked'),
            company_enabled: $('#mavlers-cf-hubspot-company-enabled').is(':checked'),
            deal_pipeline: $('#mavlers-cf-hubspot-deal-pipeline').val(),
            deal_stage: $('#mavlers-cf-hubspot-deal-stage').val(),
            workflow_id: $('#mavlers-cf-hubspot-workflow-id').val(),
            company_id: $('#mavlers-cf-hubspot-company-id').val()
        };
        
        currentSettings = {
            global: globalSettings,
            form: formSettings
        };
    }

    /**
     * Test HubSpot connection
     */
    function testHubSpotConnection() {
        const accessToken = $('#hubspot-access-token').val();
        const portalId = $('#hubspot-portal-id').val();
        
        if (!accessToken || !portalId) {
            showHubSpotMessage('Please enter both Access Token and Portal ID', 'error');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hubspot_test_connection',
                nonce: mavlers_cf_nonce,
                access_token: accessToken,
                portal_id: portalId
            },
            success: function(response) {
                if (response.success) {
                    showHubSpotMessage('Connection successful!', 'success');
                } else {
                    showHubSpotMessage('Connection failed: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showHubSpotMessage('Connection error: ' + error, 'error');
            }
        });
    }

    /**
     * Save HubSpot global settings
     */
    function saveHubSpotGlobalSettings() {
        const settings = {
            access_token: $('#hubspot-access-token').val(),
            portal_id: $('#hubspot-portal-id').val(),
            enable_analytics: $('#hubspot-enable-analytics').is(':checked'),
            enable_webhooks: $('#hubspot-enable-webhooks').is(':checked'),
            batch_processing: $('#hubspot-batch-processing').is(':checked')
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hubspot_save_global_settings',
                nonce: mavlers_cf_nonce,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    showHubSpotMessage('Settings saved successfully!', 'success');
                } else {
                    showHubSpotMessage('Failed to save settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showHubSpotMessage('Error saving settings: ' + error, 'error');
            }
        });
    }

    /**
     * Show a message to the user
     */
    function showHubSpotMessage(message, type = 'info') {
        // Create message element
        const $message = $('<div class="hubspot-message hubspot-message-' + type + '">' + message + '</div>');
        
        // Add to page
        $('.hubspot-form-settings').prepend($message);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

})(jQuery); 