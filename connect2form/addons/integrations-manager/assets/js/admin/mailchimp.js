/**
 * Mailchimp Integration Admin JavaScript
 * 
 * Handles global settings and connection testing for Mailchimp integration
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
        
        initializeMailchimpSettings();
        isInitialized = true;
    });

    /**
     * Initialize Mailchimp settings functionality
     */
    function initializeMailchimpSettings() {
        // Check if we're on the right page
        if (!isMailchimpSettingsPage()) {
            return;
        }
        
        // Bind events
        bindEvents();
        
        // Load initial state
        loadInitialState();
    }

    /**
     * Check if we're on a Mailchimp settings page
     */
    function isMailchimpSettingsPage() {
        return window.location.href.includes('connect2form-integrations') || 
               window.location.href.includes('mailchimp') ||
               $('.mailchimp-settings').length > 0 ||
               $('input[name="api_key"]').length > 0;
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Test connection button
        $(document).on('click', '#test-mailchimp-connection, .test-connection-btn', testConnection);
        
        // Save settings form
        $(document).on('submit', '#mailchimp-global-settings, .mailchimp-settings-form', handleSettingsSubmit);
        
        // Auto-detect datacenter when API key changes
        $(document).on('input', 'input[name="api_key"], #api_key', debounce(autoDetectDatacenter, CONFIG.DEBOUNCE_DELAY));
        
        // Save settings button
        $(document).on('click', '#save-mailchimp-settings, .save-settings-btn', saveSettings);
    }

    /**
     * Load initial state
     */
    function loadInitialState() {
        // Auto-detect datacenter if API key is present
        const apiKey = getApiKey();
        if (apiKey) {
            autoDetectDatacenter();
        }
    }

    /**
     * Test Mailchimp connection
     */
    function testConnection(e) {
        if (e) e.preventDefault();
        
        const $button = $(this);
        const $status = $('#connection-status, .connection-status');
        const originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).text('Testing...');
        $status.removeClass('connected disconnected').addClass('testing').text('Testing connection...');
        
        const apiKey = getApiKey();
        if (!apiKey) {
            showConnectionError('Please enter an API key first.');
            resetTestButton($button, originalText);
            return;
        }
        
        $.ajax({
            url: window.connect2formCFMailchimp?.ajaxUrl || window.connect2formMailchimp?.ajaxUrl || ajaxurl,
            type: 'POST',
            data: {
                action: 'connect2form_test_mailchimp_connection',
                api_key: apiKey,
                nonce: window.connect2formCFMailchimp?.nonce || window.connect2formMailchimp?.nonce || $('#connect2form_nonce').val()
            },
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                if (response && response.success) {
                    showConnectionSuccess('Connection successful!');
                } else {
                    const errorMessage = response?.data || response?.message || 'Connection failed';
                    showConnectionError(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                // console.log('Test connection error:', {xhr, status, error});
                let errorMessage = 'Connection test failed';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response?.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    // Use generic error handling
                    if (xhr.status === 400) {
                        errorMessage = 'Bad request - please check your API key';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Access denied - please check your permissions';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network error - please check your connection';
                    }
                }
                
                showConnectionError(errorMessage);
            },
            complete: function() {
                resetTestButton($button, originalText);
            }
        });
    }

    /**
     * Show connection success
     */
    function showConnectionSuccess(message) {
        const $status = $('#connection-status, .connection-status');
        $status.removeClass('testing disconnected').addClass('connected').text(message);
        showMessage(message, 'success');
    }

    /**
     * Show connection error
     */
    function showConnectionError(message) {
        const $status = $('#connection-status, .connection-status');
        $status.removeClass('testing connected').addClass('disconnected').text(message);
        showMessage(message, 'error');
    }

    /**
     * Reset test button
     */
    function resetTestButton($button, originalText) {
        $button.prop('disabled', false).text(originalText);
    }

    /**
     * Handle settings form submission
     */
    function handleSettingsSubmit(e) {
        e.preventDefault();
        // console.log('Settings form submitted');
        saveSettings();
    }

    /**
     * Save Mailchimp settings
     */
    function saveSettings() {
        // console.log('Saving settings for integration: mailchimp');
        
        const $form = $('#mailchimp-global-settings, .mailchimp-settings-form').first();
        const $saveButton = $('#save-mailchimp-settings, .save-settings-btn').first();
        
        if (!$form.length) {
            // console.log('No settings form found');
            return;
        }
        
        // Get all form fields
        const formFields = {};
        $form.find('input, select, textarea').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            if (name) {
                if ($field.attr('type') === 'checkbox') {
                    formFields[name] = $field.is(':checked') ? '1' : '0';
                } else {
                    formFields[name] = $field.val();
                }
            }
        });
        
        // console.log('Found form fields:', Object.keys(formFields).length);
        Object.keys(formFields).forEach(key => {
            // console.log(`Field: ${key} Type: ${$form.find(`[name="${key}"]`).attr('type')} Value: ${formFields[key]} Checked: ${$form.find(`[name="${key}"]`).is(':checked')}`);
        });
        
        // Show loading state
        const originalText = $saveButton.text();
        $saveButton.prop('disabled', true).text('Saving...');
        
        // console.log('Saving settings for integration: mailchimp');
        // console.log('Settings data:', formFields);
        
        const ajaxData = {
            action: 'mailchimp_save_global_settings',
            nonce: formFields.connect2form_nonce || window.connect2formCFMailchimp?.nonce || window.connect2formMailchimp?.nonce,
            integration_id: 'mailchimp',
            settings: formFields
        };
        
        // console.log('Sending AJAX request with data:', ajaxData);
        
        $.ajax({
            url: window.connect2formCFMailchimp?.ajaxUrl || window.connect2formMailchimp?.ajaxUrl || ajaxurl,
            type: 'POST',
            data: ajaxData,
            timeout: CONFIG.AJAX_TIMEOUT,
            success: function(response) {
                // console.log('Settings saved successfully:', response);
                showMessage('Settings saved successfully!', 'success');
            },
            error: function(xhr, status, error) {
                // console.log('Save settings error:', {xhr, status, error});
                let errorMessage = 'Failed to save settings';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response?.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    errorMessage = `Error ${xhr.status}: ${error}`;
                }
                
                showMessage(errorMessage, 'error');
            },
            complete: function() {
                $saveButton.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Auto-detect datacenter from API key
     */
    function autoDetectDatacenter() {
        const apiKey = getApiKey();
        if (!apiKey) return;
        
        const parts = apiKey.split('-');
        if (parts.length === 2) {
            const datacenter = parts[1];
            const $datacenterField = $('input[name="datacenter"], #datacenter');
            if ($datacenterField.length) {
                $datacenterField.val(datacenter);
            }
        }
    }

    /**
     * Get API key from form
     */
    function getApiKey() {
        const $apiKeyField = $('input[name="api_key"], #api_key');
        return $apiKeyField.length ? $apiKeyField.val() : '';
    }

    /**
     * Show message to user
     */
    function showMessage(message, type = 'info') {
        // Try to find existing message container
        let $container = $('#mailchimp-messages, .integration-messages').first();
        
        // If no container exists, create one
        if (!$container.length) {
            $container = $('<div id="mailchimp-messages" class="integration-messages"></div>');
            $('.mailchimp-settings, .wrap').first().prepend($container);
        }
        
        const messageClass = `notice notice-${type}`;
        const $message = $(`
            <div class="${messageClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $container.append($message);
        
        // Auto-remove success messages
        if (type === 'success') {
            setTimeout(() => {
                $message.fadeOut();
            }, 5000);
        }
        
        // Handle dismiss button
        $message.find('.notice-dismiss').on('click', function() {
            $message.fadeOut();
        });
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Expose functions globally for potential external use
    window.Connect2FormMailchimp = {
        testConnection: testConnection,
        saveSettings: saveSettings,
        showMessage: showMessage
    };

})(jQuery);
