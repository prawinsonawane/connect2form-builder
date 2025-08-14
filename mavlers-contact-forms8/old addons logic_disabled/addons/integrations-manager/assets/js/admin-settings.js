/**
 * Mavlers Contact Forms - Integration Manager Admin Settings JavaScript
 * 
 * JavaScript functionality for the admin settings page
 */

(function($) {
    'use strict';

    // Admin settings object
    window.MavlersCFIntegrationsAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initializeTabs();
            this.loadLogs();
        },

        // Event bindings
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.nav-tab', this.handleTabClick.bind(this));
            
            // Settings form
            $(document).on('submit', '#integration-settings-form', this.handleSettingsSave.bind(this));
            
            // Global integration configuration
            $(document).on('click', '.configure-global-integration', this.handleGlobalConfiguration.bind(this));
            $(document).on('click', '.test-global-connection', this.handleGlobalTestConnection.bind(this));
            
            // Logs functionality
            $(document).on('change', '#log-level-filter, #integration-filter', this.handleLogFilter.bind(this));
            $(document).on('click', '.refresh-logs', this.handleRefreshLogs.bind(this));
            $(document).on('click', '.export-logs', this.handleExportLogs.bind(this));
            
            // Maintenance actions
            $(document).on('click', '.clear-logs', this.handleClearLogs.bind(this));
            $(document).on('click', '.reset-integrations', this.handleResetIntegrations.bind(this));
            
            // Modal handlers
            $(document).on('click', '.modal-close, .modal-cancel', this.closeModal.bind(this));
            $(document).on('click', '.modal-overlay', this.closeModal.bind(this));
            $(document).on('click', '.global-modal-save', this.handleGlobalSave.bind(this));
        },

        // Initialize tabs
        initializeTabs: function() {
            // Set active tab based on URL hash
            var hash = window.location.hash;
            if (hash) {
                var $tab = $('.nav-tab[href="' + hash + '"]');
                if ($tab.length) {
                    this.switchTab($tab);
                }
            }
            
            // Update URL when tab changes
            $(window).on('hashchange', function() {
                var hash = window.location.hash;
                if (hash) {
                    var $tab = $('.nav-tab[href="' + hash + '"]');
                    if ($tab.length) {
                        MavlersCFIntegrationsAdmin.switchTab($tab);
                    }
                }
            });
        },

        // Handle tab click
        handleTabClick: function(e) {
            e.preventDefault();
            var $tab = $(e.target);
            this.switchTab($tab);
            
            // Update URL
            var hash = $tab.attr('href');
            if (history.pushState) {
                history.pushState(null, null, hash);
            } else {
                window.location.hash = hash;
            }
        },

        // Switch tab
        switchTab: function($tab) {
            var tabId = $tab.data('tab');
            
            // Update tab navigation
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide tab content
            $('.tab-content').removeClass('active');
            $('#' + tabId + '-tab').addClass('active');
            
            // Load tab-specific content
            if (tabId === 'logs' && !this.logsLoaded) {
                this.loadLogs();
            }
        },

        // Handle settings save
        handleSettingsSave: function(e) {
            e.preventDefault();
            var $form = $(e.target);
            var $submitBtn = $form.find('button[type="submit"]');
            var formData = $form.serialize();
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: mavlers_cf_integrations_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_save_integration_settings',
                    nonce: mavlers_cf_integrations_admin.nonce,
                    ...this.serializeForm($form)
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFIntegrationsAdmin.showNotice('Settings saved successfully', 'success');
                    } else {
                        MavlersCFIntegrationsAdmin.showNotice(response.data.message || 'Failed to save settings', 'error');
                    }
                },
                error: function() {
                    MavlersCFIntegrationsAdmin.showNotice('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text('Save Settings');
                }
            });
        },

        // Handle global integration configuration
        handleGlobalConfiguration: function(e) {
            e.preventDefault();
            var $button = $(e.target).closest('button');
            var integrationId = $button.data('integration-id');
            
            this.openGlobalModal(integrationId);
        },

        // Open global configuration modal
        openGlobalModal: function(integrationId) {
            var $modal = $('#global-integration-modal');
            var $content = $('#global-modal-content');
            
            // Show loading state
            $content.html('<div class="loading-spinner"><span class="dashicons dashicons-update-alt"></span> Loading...</div>');
            
            // Show modal
            $modal.show();
            
            // Load global configuration
            $.ajax({
                url: mavlers_cf_integrations_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_get_global_integration_config',
                    nonce: mavlers_cf_integrations_admin.nonce,
                    integration_id: integrationId
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(response.data.html);
                        $('#global-modal-title').text('Configure ' + response.data.integration_name);
                    } else {
                        $content.html('<div class="error-message">Failed to load configuration: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $content.html('<div class="error-message">Connection error. Please try again.</div>');
                }
            });
        },

        // Handle global test connection
        handleGlobalTestConnection: function(e) {
            e.preventDefault();
            var $button = $(e.target).closest('button');
            var integrationId = $button.data('integration-id');
            
            $button.prop('disabled', true).text('Testing...');
            
            $.ajax({
                url: mavlers_cf_integrations_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_test_global_connection',
                    nonce: mavlers_cf_integrations_admin.nonce,
                    integration_id: integrationId
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFIntegrationsAdmin.showNotice('Connection test successful', 'success');
                    } else {
                        MavlersCFIntegrationsAdmin.showNotice('Connection test failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    MavlersCFIntegrationsAdmin.showNotice('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test');
                }
            });
        },

        // Handle global save
        handleGlobalSave: function(e) {
            e.preventDefault();
            var $button = $(e.target);
            var $modal = $('#global-integration-modal');
            var formData = this.serializeModalForm($modal);
            
            $button.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: mavlers_cf_integrations_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_save_global_integration',
                    nonce: mavlers_cf_integrations_admin.nonce,
                    ...formData
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFIntegrationsAdmin.closeModal();
                        MavlersCFIntegrationsAdmin.showNotice('Configuration saved successfully', 'success');
                        // Refresh the integrations grid
                        location.reload();
                    } else {
                        MavlersCFIntegrationsAdmin.showNotice(response.data.message || 'Failed to save configuration', 'error');
                    }
                },
                error: function() {
                    MavlersCFIntegrationsAdmin.showNotice('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Configuration');
                }
            });
        },

        // Load logs
        loadLogs: function() {
            var $container = $('#logs-table-container');
            var $loading = $('.logs-loading');
            
            $loading.show();
            
            $.ajax({
                url: mavlers_cf_integrations_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_get_integration_logs',
                    nonce: mavlers_cf_integrations_admin.nonce,
                    level: $('#log-level-filter').val(),
                    integration: $('#integration-filter').val(),
                    limit: 50,
                    offset: 0
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data.html);
                        MavlersCFIntegrationsAdmin.logsLoaded = true;
                    } else {
                        $container.html('<div class="error-message">Failed to load logs: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $container.html('<div class="error-message">Connection error. Please try again.</div>');
                },
                complete: function() {
                    $loading.hide();
                }
            });
        },

        // Handle log filter
        handleLogFilter: function(e) {
            this.loadLogs();
        },

        // Handle refresh logs
        handleRefreshLogs: function(e) {
            e.preventDefault();
            this.loadLogs();
        },

        // Handle export logs
        handleExportLogs: function(e) {
            e.preventDefault();
            
            var params = new URLSearchParams({
                action: 'mavlers_cf_export_integration_logs',
                nonce: mavlers_cf_integrations_admin.nonce,
                level: $('#log-level-filter').val(),
                integration: $('#integration-filter').val()
            });
            
            var url = mavlers_cf_integrations_admin.ajax_url + '?' + params.toString();
            window.open(url, '_blank');
        },

        // Handle clear logs
        handleClearLogs: function(e) {
            e.preventDefault();
            var $button = $(e.target).closest('button');
            var confirmMsg = $button.data('confirm');
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: mavlers_cf_integrations_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_clear_integration_logs',
                    nonce: mavlers_cf_integrations_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFIntegrationsAdmin.showNotice('Logs cleared successfully', 'success');
                        MavlersCFIntegrationsAdmin.loadLogs();
                    } else {
                        MavlersCFIntegrationsAdmin.showNotice(response.data.message || 'Failed to clear logs', 'error');
                    }
                },
                error: function() {
                    MavlersCFIntegrationsAdmin.showNotice('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear All Logs');
                }
            });
        },

        // Handle reset integrations
        handleResetIntegrations: function(e) {
            e.preventDefault();
            var $button = $(e.target).closest('button');
            var confirmMsg = $button.data('confirm');
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            $button.prop('disabled', true).text('Resetting...');
            
            $.ajax({
                url: mavlers_cf_integrations_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'mavlers_cf_reset_integrations',
                    nonce: mavlers_cf_integrations_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MavlersCFIntegrationsAdmin.showNotice('Integrations reset successfully', 'success');
                        location.reload();
                    } else {
                        MavlersCFIntegrationsAdmin.showNotice(response.data.message || 'Failed to reset integrations', 'error');
                    }
                },
                error: function() {
                    MavlersCFIntegrationsAdmin.showNotice('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Reset All Configurations');
                }
            });
        },

        // Close modal
        closeModal: function(e) {
            if (e && $(e.target).closest('.modal-content').length && !$(e.target).hasClass('modal-overlay')) {
                return;
            }
            $('.mavlers-cf-modal').hide();
        },

        // Show notice
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut();
            });
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },

        // Serialize form data
        serializeForm: function($form) {
            var data = {};
            
            $form.serializeArray().forEach(function(field) {
                data[field.name] = field.value;
            });
            
            // Handle checkboxes
            $form.find('input[type="checkbox"]').each(function() {
                var $checkbox = $(this);
                if ($checkbox.attr('name')) {
                    data[$checkbox.attr('name')] = $checkbox.is(':checked') ? 1 : 0;
                }
            });
            
            return data;
        },

        // Serialize modal form data
        serializeModalForm: function($modal) {
            var data = {};
            
            $modal.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                
                if (name && $field.attr('type') !== 'submit') {
                    if ($field.attr('type') === 'checkbox') {
                        data[name] = $field.is(':checked') ? 1 : 0;
                    } else {
                        data[name] = $field.val();
                    }
                }
            });
            
            return data;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.mavlers-cf-integrations-page').length) {
            MavlersCFIntegrationsAdmin.init();
        }
    });

})(jQuery); 