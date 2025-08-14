/**
 * Mavlers Contact Forms - Integrations Admin JavaScript
 *
 * Handles all admin interface interactions for the integrations system
 */

(function($) {
    'use strict';

    // Global variables
    let currentFormId = 0;
    let isFormBuilderContext = false;

    /**
     * Initialize the integrations admin interface
     */
    function init() {
        // Check if we're in form builder context
        isFormBuilderContext = typeof mavlersCFIntegrations !== 'undefined' && mavlersCFIntegrations.isFormBuilder;
        
        // Get form ID if in form builder
        if (isFormBuilderContext) {
            currentFormId = getFormIdFromContext();
        }

        // Initialize components
        initIntegrationsOverview();
        initFormBuilderIntegrations();
        
        console.log('Mavlers CF Integrations Admin initialized', {
            isFormBuilder: isFormBuilderContext,
            formId: currentFormId
        });
    }

    /**
     * Initialize integrations overview page
     */
    function initIntegrationsOverview() {
        // Integration card interactions
        $('.integration-card').on('click', '.button-primary', function(e) {
            e.preventDefault();
            const integrationId = $(this).closest('.integration-card').data('integration');
            if (integrationId) {
                window.location.href = $(this).attr('href');
            }
        });

        // Global settings form handling
        if ($('#mailchimp-global-settings-form').length) {
            initMailchimpGlobalSettings();
        }
    }

    /**
     * Initialize form builder integrations
     */
    function initFormBuilderIntegrations() {
        if (!isFormBuilderContext) {
            return;
        }

        // Inject integration section into form builder
        injectFormBuilderIntegrations();
        
        // Initialize form-specific settings
        initFormIntegrationSettings();
    }

    /**
     * Inject integrations section into form builder
     */
    function injectFormBuilderIntegrations() {
        // Look for the integrations tab in form builder
        const $integrationsTab = $('#integration-settings, .integration-section, [data-tab="integrations"]');
        
        if ($integrationsTab.length) {
            // Load integration settings into existing tab
            loadFormIntegrationSettings($integrationsTab);
        } else {
            // Try to inject into a known form builder structure
            const $formBuilderContainer = $('.form-builder-content, .form-settings, .form-tabs-content');
            
            if ($formBuilderContainer.length) {
                // Create integrations section
                const integrationsHtml = `
                    <div class="integration-section" id="mavlers-cf-form-integrations">
                        <h3>${translations.integrations || 'Integrations'}</h3>
                        <div class="integration-loading">
                            <div class="loading-spinner">
                                <span class="dashicons dashicons-update-alt spinning"></span>
                                <p>${translations.loading || 'Loading integrations...'}</p>
                            </div>
                        </div>
                    </div>
                `;
                
                $formBuilderContainer.append(integrationsHtml);
                
                // Load settings
                setTimeout(() => {
                    loadFormIntegrationSettings($('#mavlers-cf-form-integrations'));
                }, 100);
            }
        }
    }

    /**
     * Load form integration settings
     */
    function loadFormIntegrationSettings($container) {
        if (!currentFormId) {
            $container.html(`
                <div class="integration-notice">
                    <p>${translations.saveFormFirst || 'Please save the form first to configure integrations.'}</p>
                </div>
            `);
            return;
        }

        // Load Mailchimp settings
        $.ajax({
            url: mavlersCFIntegrations.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_get_integration_config',
                integration_id: 'mailchimp',
                form_id: currentFormId,
                mode: 'configure',
                nonce: mavlersCFIntegrations.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Load the Mailchimp form settings template
                    loadMailchimpFormSettings($container, response.data);
                } else {
                    showError($container, response.data || 'Failed to load integration settings');
                }
            },
            error: function() {
                showError($container, 'Network error while loading integration settings');
            }
        });
    }

    /**
     * Load Mailchimp form settings template
     */
    function loadMailchimpFormSettings($container, data) {
        // This would typically load via AJAX, but for now we'll trigger the PHP template
        // The template is already loaded by the PHP hook, so we just need to initialize it
        if ($('.mailchimp-form-settings').length) {
            initMailchimpFormSettings();
        }
    }

    /**
     * Initialize Mailchimp global settings
     */
    function initMailchimpGlobalSettings() {
        const $form = $('#mailchimp-global-settings-form');
        const $testButton = $('#test_connection');
        const $saveButton = $('#save_settings');
        const $apiKeyInput = $('#mailchimp_api_key');
        const $toggleButton = $('#toggle_api_key');

        // Toggle API key visibility
        $toggleButton.on('click', function() {
            const $input = $apiKeyInput;
            const $icon = $(this).find('.dashicons');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Test connection
        $testButton.on('click', function(e) {
            e.preventDefault();
            
            const apiKey = $apiKeyInput.val().trim();
            
            if (!apiKey) {
                showMessage(translations.enterApiKey || 'Please enter your API key first.', 'error');
                return;
            }

            testMailchimpConnection(apiKey);
        });

        // Save settings
        $form.on('submit', function(e) {
            e.preventDefault();
            
            const apiKey = $apiKeyInput.val().trim();
            
            if (!apiKey) {
                showMessage(translations.enterApiKey || 'Please enter your API key.', 'error');
                return;
            }

            saveMailchimpGlobalSettings(apiKey);
        });
    }

    /**
     * Initialize Mailchimp form settings
     */
    function initMailchimpFormSettings() {
        const $container = $('.mailchimp-form-settings');
        
        if (!$container.length) {
            return;
        }

        const $enableToggle = $('#mailchimp_enabled');
        const $settingsPanel = $('#mailchimp_settings');
        const $form = $('#mailchimp-form-settings');
        const $audienceSelect = $('#mailchimp_audience');
        const $refreshButton = $('#refresh_audiences');

        // Toggle integration settings
        $enableToggle.on('change', function() {
            const isEnabled = $(this).is(':checked');
            
            if (isEnabled) {
                $settingsPanel.slideDown(300);
                $('#field_mapping_info').slideDown(300);
                loadMailchimpAudiences();
            } else {
                $settingsPanel.slideUp(300);
                $('#field_mapping_info').slideUp(300);
            }
            
            // Auto-save on toggle
            setTimeout(() => saveMailchimpFormSettings(), 500);
        });

        // Refresh audiences
        $refreshButton.on('click', function() {
            loadMailchimpAudiences(true);
        });

        // Form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            saveMailchimpFormSettings();
        });

        // Auto-save on field changes (debounced)
        let saveTimeout;
        $form.find('input, select').on('change', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                if ($enableToggle.is(':checked')) {
                    saveMailchimpFormSettings();
                }
            }, 1000);
        });

        // Load audiences if enabled
        if ($enableToggle.is(':checked')) {
            loadMailchimpAudiences();
        }
    }

    /**
     * Test Mailchimp connection
     */
    function testMailchimpConnection(apiKey) {
        showLoading(true);
        clearMessages();

        $.ajax({
            url: mavlersCFIntegrations.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_test_connection',
                api_key: apiKey,
                nonce: mavlersCFIntegrations.nonce
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showMessage(response.message, 'success');
                    updateConnectionInfo(response.data);
                    updateConnectionStatus(true);
                } else {
                    showMessage(response.message, 'error');
                    updateConnectionStatus(false);
                }
            },
            error: function() {
                showLoading(false);
                showMessage(translations.connectionTestFailed || 'Connection test failed. Please try again.', 'error');
                updateConnectionStatus(false);
            }
        });
    }

    /**
     * Save Mailchimp global settings
     */
    function saveMailchimpGlobalSettings(apiKey) {
        showLoading(true);
        clearMessages();

        $.ajax({
            url: mavlersCFIntegrations.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_save_global_settings',
                api_key: apiKey,
                nonce: mavlersCFIntegrations.nonce
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showMessage(response.message, 'success');
                } else {
                    showMessage(response.message || (translations.saveFailed || 'Save failed. Please try again.'), 'error');
                }
            },
            error: function() {
                showLoading(false);
                showMessage(translations.saveFailed || 'Save failed. Please try again.', 'error');
            }
        });
    }

    /**
     * Load Mailchimp audiences
     */
    function loadMailchimpAudiences(force = false) {
        const $select = $('#mailchimp_audience');
        const $button = $('#refresh_audiences');
        
        if (!$select.length) {
            return;
        }

        const currentValue = $select.val();
        
        // Don't reload if already loaded and not forced
        if (!force && $select.find('option').length > 1) {
            return;
        }

        $button.prop('disabled', true);
        $button.find('.dashicons').addClass('spinning');
        
        // Keep current selection
        $select.html(`<option value="">${translations.loadingAudiences || 'Loading audiences...'}</option>`);

        $.ajax({
            url: mavlersCFIntegrations.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mailchimp_get_audiences',
                nonce: mavlersCFIntegrations.nonce
            },
            success: function(response) {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('spinning');
                
                if (response.success && response.data) {
                    populateAudienceSelect(response.data, currentValue);
                } else {
                    $select.html(`<option value="">${translations.failedLoadAudiences || 'Failed to load audiences'}</option>`);
                    showMessage(response.message || (translations.failedLoadAudiences || 'Failed to load audiences'), 'error');
                }
            },
            error: function() {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('spinning');
                $select.html(`<option value="">${translations.errorLoadingAudiences || 'Error loading audiences'}</option>`);
                showMessage(translations.errorLoadingAudiences || 'Error loading audiences', 'error');
            }
        });
    }

    /**
     * Populate audience select options
     */
    function populateAudienceSelect(audiences, selectedValue) {
        const $select = $('#mailchimp_audience');
        let options = `<option value="">${translations.selectAudience || 'Select an audience...'}</option>`;
        
        audiences.forEach(function(audience) {
            const isSelected = selectedValue === audience.id ? 'selected' : '';
            const memberText = audience.member_count === 1 ? 'member' : 'members';
            options += `<option value="${audience.id}" ${isSelected}>${audience.name} (${audience.member_count.toLocaleString()} ${memberText})</option>`;
        });
        
        $select.html(options);
    }

    /**
     * Save Mailchimp form settings
     */
    function saveMailchimpFormSettings() {
        if (!currentFormId) {
            return;
        }

        showMailchimpLoading(true);
        clearMailchimpMessages();

        const formData = {
            action: 'mailchimp_save_form_settings',
            form_id: currentFormId,
            enabled: $('#mailchimp_enabled').is(':checked') ? 1 : 0,
            audience_id: $('#mailchimp_audience').val(),
            double_optin: $('#mailchimp_double_optin').is(':checked') ? 1 : 0,
            update_existing: $('#mailchimp_update_existing').is(':checked') ? 1 : 0,
            tags: $('#mailchimp_tags').val(),
            nonce: mavlersCFIntegrations.nonce
        };

        $.ajax({
            url: mavlersCFIntegrations.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                showMailchimpLoading(false);
                
                if (response.success) {
                    showSaveStatus(translations.settingsSaved || 'Settings saved successfully!', 'success');
                } else {
                    showSaveStatus(response.message || (translations.saveFailed || 'Save failed'), 'error');
                }
            },
            error: function() {
                showMailchimpLoading(false);
                showSaveStatus(translations.saveFailed || 'Save failed', 'error');
            }
        });
    }

    /**
     * Get form ID from current context
     */
    function getFormIdFromContext() {
        // Try various methods to get form ID
        
        // Check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const formIdFromUrl = urlParams.get('form_id');
        if (formIdFromUrl) {
            return parseInt(formIdFromUrl);
        }

        // Check for form ID in page elements
        const $formIdInput = $('input[name="form_id"], #form_id, [data-form-id]');
        if ($formIdInput.length) {
            return parseInt($formIdInput.val() || $formIdInput.data('form-id'));
        }

        // Check for global form variable
        if (typeof currentFormID !== 'undefined') {
            return parseInt(currentFormID);
        }

        // Check for Mavlers CF specific elements
        const $formBuilder = $('.mavlers-cf-form-builder');
        if ($formBuilder.length && $formBuilder.data('form-id')) {
            return parseInt($formBuilder.data('form-id'));
        }

        return 0;
    }

    /**
     * Show/hide loading state
     */
    function showLoading(show) {
        const $overlay = $('#loading_overlay');
        if (show) {
            $overlay.show();
        } else {
            $overlay.hide();
        }
    }

    /**
     * Show/hide Mailchimp specific loading
     */
    function showMailchimpLoading(show) {
        const $overlay = $('#mailchimp_loading');
        if (show) {
            $overlay.show();
        } else {
            $overlay.hide();
        }
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        const messageClass = 'notice notice-' + (type === 'error' ? 'error' : type === 'success' ? 'success' : 'info');
        const messageHtml = `<div class="${messageClass}">${message}</div>`;
        $('#message_area').append(messageHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $('#message_area .notice').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Show Mailchimp specific messages
     */
    function showMailchimpMessage(message, type) {
        const messageClass = 'notice notice-' + (type === 'error' ? 'error' : 'success');
        const messageHtml = `<div class="${messageClass}">${message}</div>`;
        $('#mailchimp_messages').append(messageHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $('#mailchimp_messages .notice').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Show save status
     */
    function showSaveStatus(message, type) {
        const $status = $('#save_status');
        $status.removeClass('success error').addClass(type).text(message);
        
        // Clear after 3 seconds
        setTimeout(function() {
            $status.text('').removeClass('success error');
        }, 3000);
    }

    /**
     * Clear messages
     */
    function clearMessages() {
        $('#message_area').empty();
    }

    /**
     * Clear Mailchimp messages
     */
    function clearMailchimpMessages() {
        $('#mailchimp_messages').empty();
    }

    /**
     * Update connection info display
     */
    function updateConnectionInfo(data) {
        if (data) {
            $('#account_name').text(data.account_name || 'N/A');
            $('#account_email').text(data.email || 'N/A');
            $('#total_subscribers').text(data.total_subscribers ? data.total_subscribers.toLocaleString() : '0');
            $('#last_tested').text(translations.justNow || 'Just now');
            $('#connection_info').show();
        }
    }

    /**
     * Update connection status indicator
     */
    function updateConnectionStatus(connected) {
        const $statusIndicator = $('.status-indicator');
        const $statusIcon = $statusIndicator.find('.dashicons');
        
        if (connected) {
            $statusIndicator.removeClass('disconnected').addClass('connected');
            $statusIndicator.find('span:last-child').text(translations.connected || 'Connected');
            $statusIcon.removeClass('dashicons-dismiss').addClass('dashicons-yes-alt');
        } else {
            $statusIndicator.removeClass('connected').addClass('disconnected');
            $statusIndicator.find('span:last-child').text(translations.notConnected || 'Not Connected');
            $statusIcon.removeClass('dashicons-yes-alt').addClass('dashicons-dismiss');
            $('#connection_info').hide();
        }
    }

    /**
     * Show error in container
     */
    function showError($container, message) {
        $container.html(`
            <div class="integration-error">
                <p class="error">${message}</p>
            </div>
        `);
    }

    /**
     * Default translations (can be overridden by localization)
     */
    const translations = {
        integrations: 'Integrations',
        loading: 'Loading...',
        saveFormFirst: 'Please save the form first to configure integrations.',
        enterApiKey: 'Please enter your API key.',
        connectionTestFailed: 'Connection test failed. Please try again.',
        saveFailed: 'Save failed. Please try again.',
        loadingAudiences: 'Loading audiences...',
        failedLoadAudiences: 'Failed to load audiences',
        errorLoadingAudiences: 'Error loading audiences',
        selectAudience: 'Select an audience...',
        settingsSaved: 'Settings saved successfully!',
        justNow: 'Just now',
        connected: 'Connected',
        notConnected: 'Not Connected'
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        init();
    });

    // Public API for external access
    window.MavlersCFIntegrations = {
        init: init,
        testMailchimpConnection: testMailchimpConnection,
        saveMailchimpGlobalSettings: saveMailchimpGlobalSettings,
        loadMailchimpAudiences: loadMailchimpAudiences,
        getFormId: () => currentFormId,
        setFormId: (id) => { currentFormId = parseInt(id); }
    };

})(jQuery); 