<?php
/**
 * Multilingual Admin Interface for Mailchimp Integration
 * 
 * Includes language selection, RTL support, and localized content
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get the language manager instance
$language_manager = new Mavlers_CF_Mailchimp_Language_Manager();
$current_locale = $language_manager->get_current_locale();
$supported_locales = $language_manager->get_supported_locales();
$is_rtl = $language_manager->is_rtl();
$admin_texts = $language_manager->get_admin_texts();
$error_messages = $language_manager->get_error_messages();
$success_messages = $language_manager->get_success_messages();

// Get current global settings
$mailchimp_integration = new Mavlers_CF_Mailchimp_Integration();
$global_settings = $mailchimp_integration->get_global_settings();
?>

<div class="mailchimp-multilang-interface <?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" data-locale="<?php echo esc_attr($current_locale); ?>">
    
    <!-- Language Selector Header -->
    <div class="mailchimp-header">
        <div class="mailchimp-header-content">
            <h1 class="mailchimp-title">
                <span class="dashicons dashicons-email-alt"></span>
                <?php echo esc_html($admin_texts['mailchimp_integration']); ?>
            </h1>
            
            <div class="mailchimp-language-controls">
                <label for="mailchimp-language-selector" class="language-label">
                    <?php echo esc_html($admin_texts['language']); ?>
                </label>
                <select id="mailchimp-language-selector" class="language-selector">
                    <?php foreach ($supported_locales as $locale => $locale_info): ?>
                        <option value="<?php echo esc_attr($locale); ?>" 
                                <?php selected($locale, $current_locale); ?>
                                data-rtl="<?php echo $locale_info['rtl'] ? 'true' : 'false'; ?>">
                            <?php echo esc_html($locale_info['native'] . ' (' . $locale_info['name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="button" class="button button-secondary language-apply" id="apply-language">
                    <?php echo esc_html($admin_texts['confirm']); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Connection Status Banner -->
    <div class="mailchimp-status-banner" id="connection-status-banner">
        <div class="status-content">
            <span class="status-icon"></span>
            <span class="status-text"></span>
            <button type="button" class="button button-link status-action" style="display: none;"></button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="mailchimp-nav-tabs">
        <ul class="nav-tab-wrapper">
            <li class="nav-tab nav-tab-active" data-tab="global-settings">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php echo esc_html($admin_texts['global_settings']); ?>
            </li>
            <li class="nav-tab" data-tab="analytics">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php echo esc_html($admin_texts['analytics']); ?>
            </li>
            <li class="nav-tab" data-tab="custom-fields">
                <span class="dashicons dashicons-admin-customizer"></span>
                <?php echo esc_html($admin_texts['custom_fields']); ?>
            </li>
        </ul>
    </div>

    <!-- Global Settings Tab -->
    <div class="mailchimp-tab-content" id="global-settings-content">
        <div class="mailchimp-section">
            <h2 class="section-title">
                <?php echo esc_html($admin_texts['global_settings']); ?>
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mailchimp-api-key">
                            <?php echo esc_html($admin_texts['api_key']); ?>
                            <span class="required">*</span>
                        </label>
                    </th>
                    <td>
                        <div class="api-key-wrapper">
                            <input type="password" 
                                   id="mailchimp-api-key" 
                                   name="mailchimp_api_key" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($global_settings['api_key'] ?? ''); ?>"
                                   placeholder="abc123def456ghi789-us10">
                            <button type="button" class="button button-secondary toggle-visibility" data-target="mailchimp-api-key">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button type="button" class="button button-primary test-connection" id="test-mailchimp-connection">
                                <span class="dashicons dashicons-admin-links"></span>
                                <?php echo esc_html($admin_texts['test_connection']); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php echo esc_html($admin_texts['api_key_description']); ?>
                        </p>
                        <div class="connection-result" id="connection-result" style="display: none;">
                            <div class="connection-message"></div>
                            <div class="account-info" style="display: none;">
                                <h4><?php echo esc_html($language_manager->translate('Account Information', 'admin')); ?></h4>
                                <ul class="account-details"></ul>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="form-actions">
                <button type="button" class="button button-primary save-global-settings" id="save-global-settings">
                    <span class="dashicons dashicons-yes"></span>
                    <?php echo esc_html($admin_texts['save_settings']); ?>
                </button>
                <span class="spinner" id="global-settings-spinner"></span>
                <div class="save-result" id="global-save-result"></div>
            </div>
        </div>

        <!-- Advanced Options -->
        <div class="mailchimp-section advanced-options" style="display: none;" id="advanced-options">
            <h3 class="section-title">
                <?php echo esc_html($admin_texts['advanced_options']); ?>
            </h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable-batch-processing">
                            <?php echo esc_html($admin_texts['batch_processing']); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="enable-batch-processing" name="enable_batch_processing" value="1">
                        <p class="description">
                            <?php echo esc_html($language_manager->translate('Enable batch processing for better performance with high-volume forms.', 'help')); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="enable-webhooks">
                            <?php echo esc_html($admin_texts['webhook_settings']); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" id="enable-webhooks" name="enable_webhooks" value="1">
                        <p class="description">
                            <?php echo esc_html($language_manager->translate('Enable webhook support for bi-directional synchronization.', 'help')); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Analytics Tab -->
    <div class="mailchimp-tab-content" id="analytics-content" style="display: none;">
        <div class="mailchimp-section">
            <h2 class="section-title">
                <?php echo esc_html($admin_texts['analytics']); ?>
            </h2>
            
            <!-- Analytics Dashboard will be loaded here -->
            <div id="analytics-dashboard-container" class="analytics-container">
                <div class="loading-placeholder">
                    <span class="spinner is-active"></span>
                    <p><?php echo esc_html($admin_texts['loading']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Fields Tab -->
    <div class="mailchimp-tab-content" id="custom-fields-content" style="display: none;">
        <div class="mailchimp-section">
            <h2 class="section-title">
                <?php echo esc_html($admin_texts['custom_fields']); ?>
            </h2>
            
            <!-- Custom Fields Manager will be loaded here -->
            <div id="custom-fields-container" class="custom-fields-container">
                <div class="loading-placeholder">
                    <span class="spinner is-active"></span>
                    <p><?php echo esc_html($admin_texts['loading']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="mailchimp-quick-actions" id="quick-actions-panel">
        <h3 class="panel-title">
            <?php echo esc_html($admin_texts['quick_actions']); ?>
        </h3>
        
        <div class="quick-action-buttons">
            <button type="button" class="button button-secondary quick-action" data-action="test-connection">
                <span class="dashicons dashicons-admin-links"></span>
                <?php echo esc_html($admin_texts['test_connection']); ?>
            </button>
            
            <button type="button" class="button button-secondary quick-action" data-action="load-audiences">
                <span class="dashicons dashicons-groups"></span>
                <?php echo esc_html($admin_texts['load_audiences']); ?>
            </button>
            
            <button type="button" class="button button-secondary quick-action" data-action="export-data">
                <span class="dashicons dashicons-download"></span>
                <?php echo esc_html($admin_texts['export_data']); ?>
            </button>
            
            <button type="button" class="button button-secondary quick-action" data-action="view-logs">
                <span class="dashicons dashicons-media-text"></span>
                <?php echo esc_html($admin_texts['error_log']); ?>
            </button>
        </div>
        
        <div class="integration-health">
            <h4><?php echo esc_html($admin_texts['integration_health']); ?></h4>
            <div class="health-indicators">
                <div class="health-item" id="connection-health">
                    <span class="indicator"></span>
                    <span class="label"><?php echo esc_html($language_manager->translate('Connection', 'status')); ?></span>
                    <span class="status"></span>
                </div>
                <div class="health-item" id="performance-health">
                    <span class="indicator"></span>
                    <span class="label"><?php echo esc_html($admin_texts['performance_monitoring']); ?></span>
                    <span class="status"></span>
                </div>
                <div class="health-item" id="webhook-health">
                    <span class="indicator"></span>
                    <span class="label"><?php echo esc_html($admin_texts['webhooks_active']); ?></span>
                    <span class="status"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Confirmations -->
    <div class="mailchimp-modal" id="confirmation-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"></h3>
                <button type="button" class="modal-close">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <p class="modal-message"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-cancel">
                    <?php echo esc_html($admin_texts['cancel']); ?>
                </button>
                <button type="button" class="button button-primary modal-confirm">
                    <?php echo esc_html($admin_texts['confirm']); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="mailchimp-toast-container" id="toast-container"></div>

</div>

<style>
/* Multilingual Interface Styles */
.mailchimp-multilang-interface {
    max-width: 1200px;
    margin: 20px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.mailchimp-multilang-interface.rtl {
    direction: rtl;
}

.mailchimp-header {
    background: linear-gradient(135deg, #ffe01b 0%, #ffcc02 100%);
    color: #333;
    padding: 20px 30px;
    border-bottom: 1px solid #eee;
}

.mailchimp-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.mailchimp-title {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.mailchimp-language-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.9);
    padding: 8px 15px;
    border-radius: 6px;
}

.language-label {
    font-weight: 500;
    margin: 0;
}

.language-selector {
    min-width: 200px;
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.mailchimp-status-banner {
    padding: 15px 30px;
    border-bottom: 1px solid #eee;
    display: none;
}

.mailchimp-status-banner.connected {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
    display: block;
}

.mailchimp-status-banner.disconnected {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
    display: block;
}

.status-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-icon::before {
    font-family: 'dashicons';
    font-size: 16px;
}

.connected .status-icon::before {
    content: '\f147'; /* dashicons-yes */
}

.disconnected .status-icon::before {
    content: '\f534'; /* dashicons-warning */
}

.mailchimp-nav-tabs {
    border-bottom: 1px solid #eee;
}

.nav-tab-wrapper {
    margin: 0;
    padding: 0 30px;
    border-bottom: none;
}

.nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 15px 20px;
    margin: 0;
    border: none;
    border-bottom: 3px solid transparent;
    background: none;
    color: #666;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nav-tab:hover {
    color: #333;
    background: #f8f9fa;
}

.nav-tab.nav-tab-active {
    color: #ffe01b;
    border-bottom-color: #ffe01b;
    background: #fff;
}

.mailchimp-tab-content {
    padding: 30px;
}

.mailchimp-section {
    margin-bottom: 30px;
}

.section-title {
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #ffe01b;
    color: #333;
}

.api-key-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.toggle-visibility {
    padding: 6px 10px;
}

.connection-result {
    margin-top: 15px;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.connection-result.success {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.connection-result.error {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.account-details {
    list-style: none;
    padding: 0;
    margin: 10px 0 0 0;
}

.account-details li {
    padding: 5px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.form-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.save-result {
    font-weight: 500;
}

.save-result.success {
    color: #28a745;
}

.save-result.error {
    color: #dc3545;
}

.mailchimp-quick-actions {
    position: fixed;
    top: 50%;
    right: 20px;
    transform: translateY(-50%);
    width: 280px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 20px;
    z-index: 1000;
}

.mailchimp-multilang-interface.rtl .mailchimp-quick-actions {
    right: auto;
    left: 20px;
}

.panel-title {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #333;
}

.quick-action-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

.quick-action {
    justify-content: flex-start;
    text-align: left;
    gap: 8px;
}

.mailchimp-multilang-interface.rtl .quick-action {
    text-align: right;
}

.integration-health {
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.health-indicators {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.health-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.health-item .indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ddd;
}

.health-item.healthy .indicator {
    background: #28a745;
}

.health-item.warning .indicator {
    background: #ffc107;
}

.health-item.error .indicator {
    background: #dc3545;
}

.mailchimp-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
}

.modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 8px;
    min-width: 400px;
    max-width: 600px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.modal-title {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid #eee;
}

.mailchimp-toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10001;
}

.mailchimp-multilang-interface.rtl .mailchimp-toast-container {
    right: auto;
    left: 20px;
}

.toast {
    background: #333;
    color: #fff;
    padding: 12px 20px;
    border-radius: 6px;
    margin-bottom: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
}

.mailchimp-multilang-interface.rtl .toast {
    transform: translateX(-100%);
}

.toast.show {
    opacity: 1;
    transform: translateX(0);
}

.toast.success {
    background: #28a745;
}

.toast.error {
    background: #dc3545;
}

.toast.warning {
    background: #ffc107;
    color: #333;
}

/* Responsive Design */
@media (max-width: 768px) {
    .mailchimp-header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .mailchimp-language-controls {
        justify-content: space-between;
    }
    
    .api-key-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .mailchimp-quick-actions {
        position: relative;
        right: auto;
        left: auto;
        top: auto;
        transform: none;
        width: 100%;
        margin-top: 20px;
    }
}

/* Loading States */
.loading-placeholder {
    text-align: center;
    padding: 40px;
    color: #666;
}

.loading-placeholder .spinner {
    float: none;
    margin: 0 auto 15px;
}

/* Form Enhancements */
.required {
    color: #dc3545;
}

.form-table th {
    width: 200px;
    padding: 15px 10px 15px 0;
}

.form-table td {
    padding: 15px 10px;
}

/* Animation Classes */
.fade-in {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.slide-in {
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<script>
// Multilingual Interface JavaScript
jQuery(document).ready(function($) {
    const MailchimpMultilang = {
        init: function() {
            this.bindEvents();
            this.loadInitialData();
            this.updateInterface();
        },
        
        bindEvents: function() {
            // Language switching
            $('#apply-language').on('click', this.changeLanguage.bind(this));
            
            // Tab navigation
            $('.nav-tab').on('click', this.switchTab.bind(this));
            
            // Quick actions
            $('.quick-action').on('click', this.handleQuickAction.bind(this));
            
            // Global settings
            $('#test-mailchimp-connection').on('click', this.testConnection.bind(this));
            $('#save-global-settings').on('click', this.saveGlobalSettings.bind(this));
            
            // Password visibility toggle
            $('.toggle-visibility').on('click', this.togglePasswordVisibility.bind(this));
            
            // Modal handling
            $('.modal-close, .modal-cancel').on('click', this.closeModal.bind(this));
            $('.modal-backdrop').on('click', this.closeModal.bind(this));
        },
        
        changeLanguage: function() {
            const newLocale = $('#mailchimp-language-selector').val();
            const isRTL = $('#mailchimp-language-selector option:selected').data('rtl');
            
            this.showToast(mailchimpL10n.texts.loading, 'info');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mailchimp_switch_language',
                    locale: newLocale,
                    nonce: mailchimpL10n.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(response.data.message, 'success');
                        if (response.data.reload_required) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    } else {
                        this.showToast(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showToast(mailchimpL10n.errors.network_error, 'error');
                }
            });
        },
        
        switchTab: function(e) {
            e.preventDefault();
            const tab = $(e.currentTarget).data('tab');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(e.currentTarget).addClass('nav-tab-active');
            
            // Show corresponding content
            $('.mailchimp-tab-content').hide();
            $(`#${tab}-content`).show().addClass('fade-in');
            
            // Load tab content if needed
            this.loadTabContent(tab);
        },
        
        loadTabContent: function(tab) {
            switch(tab) {
                case 'analytics':
                    this.loadAnalytics();
                    break;
                case 'custom-fields':
                    this.loadCustomFields();
                    break;
            }
        },
        
        loadAnalytics: function() {
            const container = $('#analytics-dashboard-container');
            if (container.find('.loading-placeholder').length) {
                // Load analytics dashboard via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mailchimp_get_analytics_dashboard',
                        nonce: mailchimpL10n.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            container.html(response.data.html);
                        } else {
                            container.html(`<div class="error"><p>${response.data.message}</p></div>`);
                        }
                    },
                    error: () => {
                        container.html(`<div class="error"><p>${mailchimpL10n.errors.network_error}</p></div>`);
                    }
                });
            }
        },
        
        loadCustomFields: function() {
            const container = $('#custom-fields-container');
            if (container.find('.loading-placeholder').length) {
                // Load custom fields manager via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mailchimp_get_custom_fields_manager',
                        nonce: mailchimpL10n.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            container.html(response.data.html);
                        } else {
                            container.html(`<div class="error"><p>${response.data.message}</p></div>`);
                        }
                    },
                    error: () => {
                        container.html(`<div class="error"><p>${mailchimpL10n.errors.network_error}</p></div>`);
                    }
                });
            }
        },
        
        testConnection: function() {
            const apiKey = $('#mailchimp-api-key').val();
            const button = $('#test-mailchimp-connection');
            const result = $('#connection-result');
            
            if (!apiKey) {
                this.showToast(mailchimpL10n.errors.api_key_required, 'error');
                return;
            }
            
            button.prop('disabled', true).text(mailchimpL10n.texts.testing);
            result.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mailchimp_test_connection',
                    api_key: apiKey,
                    nonce: mailchimpL10n.nonce
                },
                success: (response) => {
                    this.displayConnectionResult(response);
                    this.updateConnectionStatus(response.success);
                },
                error: () => {
                    this.displayConnectionResult({
                        success: false,
                        data: { message: mailchimpL10n.errors.network_error }
                    });
                },
                complete: () => {
                    button.prop('disabled', false).text(mailchimpL10n.texts.test_connection);
                }
            });
        },
        
        displayConnectionResult: function(response) {
            const result = $('#connection-result');
            const message = result.find('.connection-message');
            const accountInfo = result.find('.account-info');
            
            result.removeClass('success error').show();
            
            if (response.success) {
                result.addClass('success');
                message.html(`<strong>${mailchimpL10n.success.connection_successful}</strong>`);
                
                if (response.data.account_info) {
                    const info = response.data.account_info;
                    const details = result.find('.account-details');
                    details.empty();
                    
                    Object.entries(info).forEach(([key, value]) => {
                        details.append(`<li><strong>${key}:</strong> ${value}</li>`);
                    });
                    
                    accountInfo.show();
                }
                
                this.showToast(mailchimpL10n.success.connection_successful, 'success');
            } else {
                result.addClass('error');
                message.text(response.data.message);
                accountInfo.hide();
                
                this.showToast(response.data.message, 'error');
            }
        },
        
        updateConnectionStatus: function(isConnected) {
            const banner = $('#connection-status-banner');
            const statusText = banner.find('.status-text');
            
            banner.removeClass('connected disconnected');
            
            if (isConnected) {
                banner.addClass('connected');
                statusText.text(mailchimpL10n.success.connection_successful);
            } else {
                banner.addClass('disconnected');
                statusText.text(mailchimpL10n.errors.connection_failed);
            }
        },
        
        saveGlobalSettings: function() {
            const button = $('#save-global-settings');
            const spinner = $('#global-settings-spinner');
            const result = $('#global-save-result');
            
            const settings = {
                api_key: $('#mailchimp-api-key').val(),
                enable_batch_processing: $('#enable-batch-processing').is(':checked'),
                enable_webhooks: $('#enable-webhooks').is(':checked')
            };
            
            button.prop('disabled', true);
            spinner.addClass('is-active');
            result.empty();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mailchimp_save_global_settings',
                    settings: settings,
                    nonce: mailchimpL10n.nonce
                },
                success: (response) => {
                    if (response.success) {
                        result.addClass('success').text(mailchimpL10n.success.settings_saved);
                        this.showToast(mailchimpL10n.success.settings_saved, 'success');
                    } else {
                        result.addClass('error').text(response.data.message);
                        this.showToast(response.data.message, 'error');
                    }
                },
                error: () => {
                    result.addClass('error').text(mailchimpL10n.errors.network_error);
                    this.showToast(mailchimpL10n.errors.network_error, 'error');
                },
                complete: () => {
                    button.prop('disabled', false);
                    spinner.removeClass('is-active');
                    setTimeout(() => result.empty(), 3000);
                }
            });
        },
        
        togglePasswordVisibility: function(e) {
            const button = $(e.currentTarget);
            const target = $(`#${button.data('target')}`);
            const icon = button.find('.dashicons');
            
            if (target.attr('type') === 'password') {
                target.attr('type', 'text');
                icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                target.attr('type', 'password');
                icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },
        
        handleQuickAction: function(e) {
            const action = $(e.currentTarget).data('action');
            
            switch(action) {
                case 'test-connection':
                    this.testConnection();
                    break;
                case 'load-audiences':
                    this.loadAudiences();
                    break;
                case 'export-data':
                    this.exportData();
                    break;
                case 'view-logs':
                    this.viewLogs();
                    break;
            }
        },
        
        loadAudiences: function() {
            this.showToast(mailchimpL10n.texts.loading, 'info');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mailchimp_get_audiences',
                    nonce: mailchimpL10n.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(mailchimpL10n.success.audiences_loaded, 'success');
                        // Update audience dropdowns if visible
                        this.updateAudienceDropdowns(response.data.audiences);
                    } else {
                        this.showToast(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showToast(mailchimpL10n.errors.network_error, 'error');
                }
            });
        },
        
        updateAudienceDropdowns: function(audiences) {
            $('.mailchimp-audience-select').each(function() {
                const select = $(this);
                const currentValue = select.val();
                
                select.empty().append('<option value="">' + mailchimpL10n.texts.select_audience + '</option>');
                
                audiences.forEach(audience => {
                    select.append(`<option value="${audience.id}">${audience.name} (${audience.member_count} members)</option>`);
                });
                
                if (currentValue) {
                    select.val(currentValue);
                }
            });
        },
        
        exportData: function() {
            this.showModal(
                mailchimpL10n.texts.export_data,
                mailchimpL10n.texts.export_confirmation,
                () => {
                    window.open(ajaxurl + '?action=mailchimp_export_analytics&nonce=' + mailchimpL10n.nonce);
                }
            );
        },
        
        viewLogs: function() {
            // Open logs in new window
            window.open(
                admin_url + 'admin.php?page=mailchimp-logs',
                '_blank',
                'width=800,height=600,scrollbars=yes'
            );
        },
        
        showModal: function(title, message, onConfirm) {
            const modal = $('#confirmation-modal');
            
            modal.find('.modal-title').text(title);
            modal.find('.modal-message').text(message);
            modal.find('.modal-confirm').off('click').on('click', () => {
                this.closeModal();
                if (onConfirm) onConfirm();
            });
            
            modal.show();
        },
        
        closeModal: function() {
            $('#confirmation-modal').hide();
        },
        
        showToast: function(message, type = 'info') {
            const container = $('#toast-container');
            const toast = $(`<div class="toast ${type}">${message}</div>`);
            
            container.append(toast);
            
            setTimeout(() => toast.addClass('show'), 100);
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },
        
        loadInitialData: function() {
            // Load connection status
            this.checkConnectionStatus();
            
            // Load health indicators
            this.updateHealthIndicators();
        },
        
        checkConnectionStatus: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mailchimp_check_connection_status',
                    nonce: mailchimpL10n.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateConnectionStatus(response.data.connected);
                    }
                }
            });
        },
        
        updateHealthIndicators: function() {
            // Connection health
            $('#connection-health').removeClass('healthy warning error').addClass('warning');
            $('#connection-health .status').text(mailchimpL10n.texts.checking);
            
            // Performance health
            $('#performance-health').removeClass('healthy warning error').addClass('healthy');
            $('#performance-health .status').text(mailchimpL10n.texts.good);
            
            // Webhook health
            $('#webhook-health').removeClass('healthy warning error').addClass('warning');
            $('#webhook-health .status').text(mailchimpL10n.texts.checking);
        },
        
        updateInterface: function() {
            // Update RTL class if needed
            if (mailchimpL10n.isRTL) {
                $('.mailchimp-multilang-interface').addClass('rtl');
            }
            
            // Initialize tooltips if available
            if (typeof $.fn.tooltip === 'function') {
                $('[data-tooltip]').tooltip();
            }
            
            // Set focus to first input
            $('#mailchimp-api-key').focus();
        }
    };
    
    // Initialize the interface
    MailchimpMultilang.init();
});
</script> 