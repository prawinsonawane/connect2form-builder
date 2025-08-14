/**
 * Mavlers Contact Forms - Integrations Admin JavaScript - Optimized Version
 * 
 * Clean JavaScript with proper separation of concerns
 * No JavaScript mixed into PHP files
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        AJAX_TIMEOUT: 30000,
        MESSAGE_DISPLAY_TIME: 5000,
        MODAL_AUTO_CLOSE_DELAY: 3000,
        PAGE_REFRESH_DELAY: 1000,
        DEBOUNCE_DELAY: 300
    };

    /**
     * Main IntegrationsAdmin object
     */
    window.MavlersCFIntegrationsAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initModals();
            this.initTooltips();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // Test connection buttons
            $(document).on('click', '.test-connection-btn', this.handleTestConnection.bind(this));
            
            // Modal close buttons
            $(document).on('click', '.modal-close, .modal-overlay', this.closeModal.bind(this));
            
            // AJAX form handlers
            $(document).on('click', '.ajax-save-btn', this.handleAjaxSave.bind(this));
            
            // Connection status refresh
            $(document).on('click', '.refresh-status-btn', this.handleStatusRefresh.bind(this));
            
            // Integration card interactions
            $(document).on('click', '.integration-card', this.handleCardClick.bind(this));
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboard.bind(this));
        },

        /**
         * Handle test connection button click
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const integrationId = $button.data('integration');
            
            if (!integrationId) {
                this.showNotice('error', mavlersCFIntegrations.strings.error);
                return;
            }

            this.testConnection(integrationId, $button);
        },

        /**
         * Test integration connection
         */
        testConnection: function(integrationId, $button) {
            const originalText = $button.html();
            
            // Show loading state
            $button.html('<span class="spinner"></span> ' + mavlersCFIntegrations.strings.testing_connection)
                   .prop('disabled', true);

            // Get credentials from form if available
            const credentials = this.getCredentialsFromForm(integrationId);

            $.ajax({
                url: mavlersCFIntegrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_test_integration',
                    nonce: mavlersCFIntegrations.nonce,
                    integration_id: integrationId,
                    credentials: credentials
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: (response) => {
                    if (response.success) {
                        this.showConnectionResult('success', response.data?.message || mavlersCFIntegrations.strings.connection_successful);
                        $button.removeClass('button-secondary').addClass('button-primary');
                    } else {
                        this.showConnectionResult('error', response.data || mavlersCFIntegrations.strings.connection_failed);
                    }
                },
                error: () => {
                    this.showConnectionResult('error', mavlersCFIntegrations.strings.connection_failed);
                },
                complete: () => {
                    // Restore button state
                    $button.html(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Get credentials from form
         */
        getCredentialsFromForm: function(integrationId) {
            const credentials = {};
            const $form = $('.integration-settings-form[data-integration="' + integrationId + '"]');
            
            if ($form.length) {
                $form.find('[data-credential]').each(function() {
                    const $field = $(this);
                    const credentialName = $field.data('credential');
                    credentials[credentialName] = $field.val();
                });
            }
            
            return credentials;
        },

        /**
         * Show connection test result
         */
        showConnectionResult: function(type, message) {
            const $modal = $('#connection-test-modal');
            const $result = $modal.find('.result');
            
            $modal.find('.loading').hide();
            
            $result.removeClass('success error')
                   .addClass(type)
                   .html('<div class="notice notice-' + type + '"><p>' + message + '</p></div>')
                   .show();
                   
            // Auto-close success messages
            if (type === 'success') {
                setTimeout(() => {
                    this.closeModal();
                }, CONFIG.MODAL_AUTO_CLOSE_DELAY);
            }
        },

        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.html();
            
            // Show loading state
            $submitBtn.html('<span class="spinner"></span> ' + mavlersCFIntegrations.strings.saving)
                     .prop('disabled', true);

            const formData = new FormData(e.target);
            formData.append('action', 'mavlers_cf_save_integration_settings');
            formData.append('nonce', mavlersCFIntegrations.nonce);

            $.ajax({
                url: mavlersCFIntegrations.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: CONFIG.AJAX_TIMEOUT,
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data || mavlersCFIntegrations.strings.saved);
                        this.refreshPageData();
                    } else {
                        this.showNotice('error', response.data || mavlersCFIntegrations.strings.error);
                    }
                },
                error: () => {
                    this.showNotice('error', mavlersCFIntegrations.strings.error);
                },
                complete: () => {
                    $submitBtn.html(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle AJAX save button click
         */
        handleAjaxSave: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $form = $button.closest('form');
            
            if ($form.length) {
                $form.trigger('submit');
            }
        },

        /**
         * Handle status refresh
         */
        handleStatusRefresh: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const integrationId = $button.data('integration');
            
            // Refresh integration status
            this.refreshIntegrationStatus(integrationId);
        },

        /**
         * Handle integration card click
         */
        handleCardClick: function(e) {
            // Don't handle if clicking on buttons or links
            if ($(e.target).is('button, a, .button') || $(e.target).closest('button, a, .button').length) {
                return;
            }
            
            const $card = $(e.currentTarget);
            const integrationId = $card.data('integration');
            
            if (integrationId) {
                // Navigate to integration settings
                window.location.href = mavlersCFIntegrations.pluginUrl + 'admin.php?page=mavlers-cf-integrations&tab=settings&integration=' + integrationId;
            }
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboard: function(e) {
            // ESC key closes modals
            if (e.keyCode === 27) {
                this.closeModal();
            }
            
            // Ctrl+S saves forms
            if (e.ctrlKey && e.keyCode === 83) {
                e.preventDefault();
                $('.integration-settings-form:visible').trigger('submit');
            }
        },

        /**
         * Initialize modals
         */
        initModals: function() {
            // Create modal overlay if it doesn't exist
            if (!$('.modal-overlay').length) {
                $('body').append('<div class="modal-overlay"></div>');
            }
        },

        /**
         * Show modal
         */
        showModal: function(modalId) {
            const $modal = $('#' + modalId);
            
            if ($modal.length) {
                $modal.show();
                $('body').addClass('modal-open');
                
                // Reset modal content
                $modal.find('.loading').show();
                $modal.find('.result').hide();
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.modal').hide();
            $('body').removeClass('modal-open');
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after page title
            $('.page-title').after($notice);
            
            // Auto-remove success notices
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, CONFIG.MESSAGE_DISPLAY_TIME);
            }
            
            // Make dismissible
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(() => $notice.remove());
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to elements with title attributes
            $('[title]').each(function() {
                const $element = $(this);
                const title = $element.attr('title');
                
                if (title) {
                    $element.removeAttr('title').attr('data-tooltip', title);
                }
            });
            
            // Show tooltip on hover
            $(document).on('mouseenter', '[data-tooltip]', function(e) {
                const $element = $(this);
                const tooltip = $element.data('tooltip');
                
                if (tooltip) {
                    const $tooltip = $('<div class="cf-tooltip">' + tooltip + '</div>');
                    $('body').append($tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    $tooltip.css({
                        top: rect.top - $tooltip.outerHeight() - 5,
                        left: rect.left + (rect.width / 2) - ($tooltip.outerWidth() / 2)
                    });
                }
            });
            
            // Hide tooltip on leave
            $(document).on('mouseleave', '[data-tooltip]', function() {
                $('.cf-tooltip').remove();
            });
        },

        /**
         * Refresh page data
         */
        refreshPageData: function() {
            // Refresh integration cards or current page content
            setTimeout(() => {
                window.location.reload();
            }, CONFIG.PAGE_REFRESH_DELAY);
        },

        /**
         * Refresh integration status
         */
        refreshIntegrationStatus: function(integrationId) {
            $.ajax({
                url: mavlersCFIntegrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_get_integration_data',
                    nonce: mavlersCFIntegrations.nonce,
                    integration_id: integrationId,
                    data_type: 'status'
                },
                timeout: CONFIG.AJAX_TIMEOUT,
                success: (response) => {
                    if (response.success) {
                        this.updateIntegrationStatus(integrationId, response.data);
                    }
                }
            });
        },

        /**
         * Update integration status display
         */
        updateIntegrationStatus: function(integrationId, status) {
            const $card = $('.integration-card[data-integration="' + integrationId + '"]');
            const $statusIndicator = $card.find('.status-indicator');
            
            if (status.configured) {
                $statusIndicator.removeClass('not-configured').addClass('configured')
                              .html('<span class="dashicons dashicons-yes-alt"></span> Configured');
            } else {
                $statusIndicator.removeClass('configured').addClass('not-configured')
                              .html('<span class="dashicons dashicons-warning"></span> Not Configured');
            }
        },

        /**
         * Utility: Debounce function
         */
        debounce: function(func, wait) {
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
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        MavlersCFIntegrationsAdmin.init();
    });

    // Expose functions for debugging (only in development)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        window.integrationsDebug = {
            testConnection: MavlersCFIntegrationsAdmin.testConnection,
            showModal: MavlersCFIntegrationsAdmin.showModal,
            closeModal: MavlersCFIntegrationsAdmin.closeModal,
            showNotice: MavlersCFIntegrationsAdmin.showNotice,
            refreshIntegrationStatus: MavlersCFIntegrationsAdmin.refreshIntegrationStatus
        };
    }

})(jQuery); 