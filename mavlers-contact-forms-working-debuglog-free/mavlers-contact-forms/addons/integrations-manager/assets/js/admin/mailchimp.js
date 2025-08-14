/**
 * Mailchimp Integration Admin JavaScript - Optimized Version
 * 
 * Handles the admin interface functionality for Mailchimp integration
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        AJAX_TIMEOUT: 30000,
        MESSAGE_DISPLAY_TIME: 5000,
        MIN_API_KEY_LENGTH: 10
    };

    // Global Mailchimp object
    window.MavlersMailchimp = {
        
        /**
         * Initialize the Mailchimp admin interface
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.checkConnectionStatus();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Test connection button
            $(document).on('click', '.mailchimp-test-connection', this.testConnection);
            
            // API key change
            $(document).on('change', '#mailchimp_api_key', this.onApiKeyChange);
            
            // Audience selection change
            $(document).on('change', '#mailchimp_audience_id', this.onAudienceChange);
            
            // Field mapping changes
            $(document).on('change', '.mailchimp-field-mapping select', this.onFieldMappingChange);
            
            // Auto-map fields button
            $(document).on('click', '.mailchimp-auto-map', this.autoMapFields);
            
            // Sync custom fields
            $(document).on('click', '.mailchimp-sync-fields', this.syncCustomFields);
            
            // Language switcher
            $(document).on('change', '#mailchimp_language', this.switchLanguage);
            
            // Webhook management
            $(document).on('click', '.mailchimp-register-webhook', this.registerWebhook);
            $(document).on('click', '.mailchimp-unregister-webhook', this.unregisterWebhook);
            
            // Analytics refresh
            $(document).on('click', '.mailchimp-refresh-analytics', this.refreshAnalytics);
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.mailchimp-tooltip').each(function() {
                $(this).tooltip();
            });
        },

        /**
         * Check connection status on page load
         */
        checkConnectionStatus: function() {
            const apiKey = $('#mailchimp_api_key').val();
            if (apiKey) {
                this.testConnection();
            }
        },

        /**
         * Test Mailchimp API connection
         */
        testConnection: function(e) {
            if (e) e.preventDefault();
            
            const $button = $(this).length ? $(this) : $('.mailchimp-test-connection');
            const $status = $('.mailchimp-connection-status');
            const apiKey = $('#mailchimp_api_key').val();
            
            if (!apiKey) {
                MavlersMailchimp.showMessage('Please enter an API key first.', 'error');
                return;
            }
            
            // Check if nonce is available
            if (!mavlersCFMailchimp?.nonce) {
                MavlersMailchimp.showMessage('Security token missing. Please refresh the page.', 'error');
                return;
            }
            
            $button.prop('disabled', true).text(mavlersCFMailchimp.strings.testing || 'Testing...');
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mailchimp_test_connection',
                    nonce: mavlersCFMailchimp.nonce,
                    api_key: apiKey
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('disconnected').addClass('connected')
                               .text(mavlersCFMailchimp.strings.connected || 'Connected');
                        
                        let message = response.message || 'Connection successful!';
                        if (response.data?.account_name) {
                            message += ' Account: ' + response.data.account_name;
                        }
                        
                        MavlersMailchimp.showMessage(message, 'success');
                        MavlersMailchimp.loadAudiences(apiKey);
                    } else {
                        $status.removeClass('connected').addClass('disconnected')
                               .text(mavlersCFMailchimp.strings.disconnected || 'Disconnected');
                        MavlersMailchimp.showMessage(response.error || 'Connection failed', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $status.removeClass('connected').addClass('disconnected')
                           .text(mavlersCFMailchimp.strings.disconnected || 'Disconnected');
                    
                    let errorMessage = 'Connection test failed';
                    
                    // Handle specific error codes
                    if (xhr.status === 403) {
                        errorMessage = 'Permission denied. Please make sure you have admin access and try refreshing the page.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'AJAX endpoint not found. Please check plugin installation.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error. Please check error logs for details.';
                    } else if (xhr.responseJSON?.data) {
                        errorMessage = xhr.responseJSON.data;
                    } else if (xhr.responseText) {
                        try {
                            const responseObj = JSON.parse(xhr.responseText);
                            if (responseObj.data) {
                                errorMessage = responseObj.data;
                            } else if (responseObj.error) {
                                errorMessage = responseObj.error;
                            }
                        } catch (parseError) {
                            // Keep default error message
                        }
                    }
                    
                    MavlersMailchimp.showMessage(errorMessage, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(mavlersCFMailchimp.strings.testConnection || 'Test Connection');
                }
            });
        },

        /**
         * Handle API key changes
         */
        onApiKeyChange: function() {
            const apiKey = $(this).val();
            if (apiKey.length > CONFIG.MIN_API_KEY_LENGTH) {
                MavlersMailchimp.loadAudiences(apiKey);
            }
        },

        /**
         * Load Mailchimp audiences
         */
        loadAudiences: function(apiKey) {
            const $select = $('#mailchimp_audience_id');
            
            $select.prop('disabled', true).html('<option>Loading...</option>');
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_get_audiences',
                    nonce: mavlersCFMailchimp.nonce,
                    api_key: apiKey
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success && response.data) {
                        let options = '<option value="">Select an audience...</option>';
                        $.each(response.data, function(id, audience) {
                            options += '<option value="' + id + '">' + audience.name + ' (' + audience.member_count + ' members)</option>';
                        });
                        $select.html(options);
                    } else {
                        $select.html('<option value="">No audiences found</option>');
                    }
                },
                error: function() {
                    $select.html('<option value="">Error loading audiences</option>');
                },
                complete: function() {
                    $select.prop('disabled', false);
                }
            });
        },

        /**
         * Handle audience selection change
         */
        onAudienceChange: function() {
            const audienceId = $(this).val();
            if (audienceId) {
                MavlersMailchimp.loadMergeFields(audienceId);
            }
        },

        /**
         * Load merge fields for selected audience
         */
        loadMergeFields: function(audienceId) {
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_get_audience_merge_fields',
                    nonce: mavlersCFMailchimp.nonce,
                    audience_id: audienceId
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success && response.data) {
                        MavlersMailchimp.updateFieldMappingOptions(response.data);
                    }
                }
            });
        },

        /**
         * Update field mapping options
         */
        updateFieldMappingOptions: function(mergeFields) {
            $('.mailchimp-merge-field-select').each(function() {
                const $select = $(this);
                const currentValue = $select.val();
                let options = '<option value="">Don\'t sync</option>';
                
                $.each(mergeFields, function(tag, field) {
                    const selected = (currentValue === tag) ? ' selected' : '';
                    options += '<option value="' + tag + '"' + selected + '>' + field.name + ' (' + tag + ')</option>';
                });
                
                $select.html(options);
            });
        },

        /**
         * Handle field mapping changes
         */
        onFieldMappingChange: function() {
            // Save field mapping automatically
            MavlersMailchimp.saveFieldMapping();
        },

        /**
         * Auto-map fields
         */
        autoMapFields: function(e) {
            e.preventDefault();
            
            const audienceId = $('#mailchimp_audience_id').val();
            const formId = $('#form_id').val();
            
            if (!audienceId || !formId) {
                MavlersMailchimp.showMessage('Please select an audience first.', 'error');
                return;
            }
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_auto_map_fields',
                    nonce: mavlersCFMailchimp.nonce,
                    audience_id: audienceId,
                    form_id: formId
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success) {
                        // Update field mapping with auto-mapped values
                        $.each(response.data, function(formField, mergeField) {
                            $('select[name="field_mapping[' + formField + ']"]').val(mergeField);
                        });
                        MavlersMailchimp.showMessage('Fields auto-mapped successfully!', 'success');
                    } else {
                        MavlersMailchimp.showMessage(response.error || 'Auto-mapping failed', 'error');
                    }
                }
            });
        },

        /**
         * Save field mapping
         */
        saveFieldMapping: function() {
            const mapping = {};
            $('.mailchimp-field-mapping select').each(function() {
                const formField = $(this).data('form-field');
                const mergeField = $(this).val();
                if (formField && mergeField) {
                    mapping[formField] = mergeField;
                }
            });
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_save_field_mapping',
                    nonce: mavlersCFMailchimp.nonce,
                    form_id: $('#form_id').val(),
                    mapping: mapping
                },
                timeout: CONFIG.AJAX_TIMEOUT
            });
        },

        /**
         * Sync custom fields
         */
        syncCustomFields: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.prop('disabled', true).text('Syncing...');
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_sync_custom_fields',
                    nonce: mavlersCFMailchimp.nonce,
                    audience_id: $('#mailchimp_audience_id').val()
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success) {
                        MavlersMailchimp.showMessage('Custom fields synced successfully!', 'success');
                        location.reload(); // Refresh to show new fields
                    } else {
                        MavlersMailchimp.showMessage(response.error || 'Sync failed', 'error');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text('Sync Fields');
                }
            });
        },

        /**
         * Switch language
         */
        switchLanguage: function() {
            const language = $(this).val();
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_switch_language',
                    nonce: mavlersCFMailchimp.nonce,
                    language: language
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success) {
                        location.reload(); // Refresh to apply new language
                    }
                }
            });
        },

        /**
         * Register webhook
         */
        registerWebhook: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.prop('disabled', true).text('Registering...');
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_register_webhook',
                    nonce: mavlersCFMailchimp.nonce,
                    audience_id: $('#mailchimp_audience_id').val()
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success) {
                        MavlersMailchimp.showMessage('Webhook registered successfully!', 'success');
                        $('.mailchimp-webhook-indicator').removeClass('inactive').addClass('active');
                    } else {
                        MavlersMailchimp.showMessage(response.error || 'Webhook registration failed', 'error');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text('Register Webhook');
                }
            });
        },

        /**
         * Unregister webhook
         */
        unregisterWebhook: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to unregister the webhook?')) {
                return;
            }
            
            const $button = $(this);
            $button.prop('disabled', true).text('Unregistering...');
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_unregister_webhook',
                    nonce: mavlersCFMailchimp.nonce,
                    audience_id: $('#mailchimp_audience_id').val()
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success) {
                        MavlersMailchimp.showMessage('Webhook unregistered successfully!', 'success');
                        $('.mailchimp-webhook-indicator').removeClass('active').addClass('inactive');
                    } else {
                        MavlersMailchimp.showMessage(response.error || 'Webhook unregistration failed', 'error');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text('Unregister Webhook');
                }
            });
        },

        /**
         * Refresh analytics
         */
        refreshAnalytics: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.prop('disabled', true).text('Refreshing...');
            
            $.ajax({
                url: mavlersCFMailchimp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mailchimp_load_analytics_dashboard',
                    nonce: mavlersCFMailchimp.nonce
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: function(response) {
                    if (response.success) {
                        // Update analytics display
                        $('.mailchimp-analytics-dashboard').html(response.data);
                        MavlersMailchimp.showMessage('Analytics refreshed!', 'success');
                    } else {
                        MavlersMailchimp.showMessage(response.error || 'Analytics refresh failed', 'error');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text('Refresh Analytics');
                }
            });
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            let $messageDiv = $('.mailchimp-messages');
            if (!$messageDiv.length) {
                $messageDiv = $('<div class="mailchimp-messages"></div>');
                $('.mavlers-mailchimp-settings').prepend($messageDiv);
            }
            
            $messageDiv.html('<div class="mailchimp-message ' + type + '">' + message + '</div>');
            
            // Auto-hide after delay
            setTimeout(() => {
                $messageDiv.fadeOut();
            }, CONFIG.MESSAGE_DISPLAY_TIME);
        },

        /**
         * Utility: Check if RTL
         */
        isRTL: function() {
            return mavlersCFMailchimp?.isRTL || false;
        },

        /**
         * Utility: Get current language
         */
        getCurrentLanguage: function() {
            return mavlersCFMailchimp?.currentLanguage || 'en_US';
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MavlersMailchimp.init();
    });

    // Expose functions for debugging (only in development)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        window.mailchimpDebug = {
            testConnection: MavlersMailchimp.testConnection,
            loadAudiences: MavlersMailchimp.loadAudiences,
            loadMergeFields: MavlersMailchimp.loadMergeFields,
            autoMapFields: MavlersMailchimp.autoMapFields,
            syncCustomFields: MavlersMailchimp.syncCustomFields
        };
    }

})(jQuery); 