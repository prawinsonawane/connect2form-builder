<?php
/**
 * Mailchimp Global Settings Template
 *
 * Clean, user-friendly interface for global Mailchimp configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$mailchimp_integration = new Mavlers_CF_Mailchimp_Integration();
$global_settings = $mailchimp_integration->get_global_settings();
$is_connected = $mailchimp_integration->is_globally_connected();
$status_message = $mailchimp_integration->get_connection_status_message();
?>

<div class="mailchimp-global-settings">
    <div class="integration-header">
        <div class="integration-icon">
            <span class="dashicons dashicons-email-alt" style="color: #ffe01b; font-size: 24px;"></span>
        </div>
        <div class="integration-info">
            <h2><?php _e('Mailchimp Global Settings', 'mavlers-contact-forms'); ?></h2>
            <p class="description">
                <?php _e('Configure your Mailchimp API connection. Once connected, you can enable Mailchimp integration for individual forms.', 'mavlers-contact-forms'); ?>
            </p>
        </div>
        <div class="connection-status">
            <div class="status-indicator <?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
                <span class="dashicons <?php echo $is_connected ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                <?php echo $is_connected ? __('Connected', 'mavlers-contact-forms') : __('Not Connected', 'mavlers-contact-forms'); ?>
            </div>
        </div>
    </div>

    <div class="settings-container">
        <form id="mailchimp-global-settings-form" class="integration-form">
            <?php wp_nonce_field('mavlers_cf_nonce', 'mailchimp_global_nonce'); ?>
            
            <div class="form-section">
                <h3><?php _e('API Configuration', 'mavlers-contact-forms'); ?></h3>
                
                <div class="form-row">
                    <label for="mailchimp_api_key" class="form-label">
                        <?php _e('Mailchimp API Key', 'mavlers-contact-forms'); ?>
                        <span class="required">*</span>
                    </label>
                    <div class="input-group">
                        <input 
                            type="password" 
                            id="mailchimp_api_key" 
                            name="api_key" 
                            value="<?php echo esc_attr($global_settings['api_key']); ?>"
                            placeholder="<?php _e('Enter your Mailchimp API key...', 'mavlers-contact-forms'); ?>"
                            class="form-input large-text"
                        />
                        <button type="button" id="toggle_api_key" class="button button-secondary">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <p class="form-help">
                        <?php 
                        printf(
                            __('Find your API key in your %sMailchimp account%s under Account > Extras > API keys.', 'mavlers-contact-forms'),
                            '<a href="https://mailchimp.com/help/about-api-keys/" target="_blank">',
                            '</a>'
                        ); 
                        ?>
                    </p>
                </div>

                <div class="form-actions">
                    <button type="button" id="test_connection" class="button button-primary">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php _e('Test Connection', 'mavlers-contact-forms'); ?>
                    </button>
                    
                    <button type="submit" id="save_settings" class="button button-secondary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Settings', 'mavlers-contact-forms'); ?>
                    </button>
                </div>
            </div>
            
            <div class="form-section">
                <h3>
                    <?php _e('Performance Settings', 'mavlers-contact-forms'); ?>
                    <span class="setting-badge new">NEW</span>
                </h3>
                
                <div class="form-row">
                    <label class="form-label">
                        <?php _e('Batch Processing', 'mavlers-contact-forms'); ?>
                    </label>
                    <div class="checkbox-group">
                        <label for="enable_batch_processing" class="checkbox-label">
                            <input type="checkbox" 
                                   id="enable_batch_processing" 
                                   name="enable_batch_processing" 
                                   value="1" 
                                   <?php checked($global_settings['enable_batch_processing'] ?? false); ?> />
                            <span class="checkbox-text">
                                <?php _e('Enable batch processing for high-volume submissions', 'mavlers-contact-forms'); ?>
                            </span>
                        </label>
                    </div>
                    <p class="form-help">
                        <?php _e('When enabled, form submissions are queued and processed in batches every 5 minutes. This improves performance for high-traffic sites but adds a slight delay before subscribers appear in Mailchimp.', 'mavlers-contact-forms'); ?>
                    </p>
                    
                    <div class="batch-info" id="batch_processing_info" style="<?php echo ($global_settings['enable_batch_processing'] ?? false) ? '' : 'display: none;'; ?>">
                        <div class="info-box">
                            <h4><?php _e('Batch Processing Benefits:', 'mavlers-contact-forms'); ?></h4>
                            <ul class="feature-list">
                                <li>
                                    <span class="dashicons dashicons-performance"></span>
                                    <strong><?php _e('Better Performance:', 'mavlers-contact-forms'); ?></strong>
                                    <?php _e('Handles high-volume submissions without timeouts', 'mavlers-contact-forms'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-shield"></span>
                                    <strong><?php _e('Rate Limit Protection:', 'mavlers-contact-forms'); ?></strong>
                                    <?php _e('Prevents API rate limit issues', 'mavlers-contact-forms'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <strong><?php _e('Bulk Operations:', 'mavlers-contact-forms'); ?></strong>
                                    <?php _e('Uses Mailchimp\'s efficient batch API', 'mavlers-contact-forms'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-backup"></span>
                                    <strong><?php _e('Automatic Retry:', 'mavlers-contact-forms'); ?></strong>
                                    <?php _e('Failed submissions are automatically retried', 'mavlers-contact-forms'); ?>
                                </li>
                            </ul>
                        </div>
                                        </div>
                </div>
            </div>
            
            <div class="form-section" id="webhook_section" <?php echo !$is_connected ? 'style="display: none;"' : ''; ?>>
                <h3>
                    <?php _e('Webhook Configuration', 'mavlers-contact-forms'); ?>
                    <span class="setting-badge new">NEW</span>
                </h3>
                
                <div class="form-row">
                    <label class="form-label">
                        <?php _e('Real-time Sync', 'mavlers-contact-forms'); ?>
                    </label>
                    <p class="form-help">
                        <?php _e('Webhooks enable real-time synchronization between Mailchimp and your WordPress site. When someone subscribes, unsubscribes, or updates their profile in Mailchimp, your site will be notified instantly.', 'mavlers-contact-forms'); ?>
                    </p>
                    
                    <div class="webhook-info">
                        <div class="webhook-url-display">
                            <label for="webhook_url"><?php _e('Webhook URL:', 'mavlers-contact-forms'); ?></label>
                            <input type="text" 
                                   id="webhook_url" 
                                   value="<?php echo home_url('/mavlers-cf-mailchimp-webhook/'); ?>" 
                                   readonly 
                                   class="form-input" 
                                   onclick="this.select()" />
                            <button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">
                                <span class="dashicons dashicons-admin-page"></span>
                                <?php _e('Copy', 'mavlers-contact-forms'); ?>
                            </button>
                        </div>
                        
                        <div class="webhook-benefits">
                            <h4><?php _e('Webhook Benefits:', 'mavlers-contact-forms'); ?></h4>
                            <ul class="feature-list">
                                <li>
                                    <span class="dashicons dashicons-update"></span>
                                    <strong><?php _e('Real-time Updates:', 'mavlers-contact-forms'); ?></strong>
                                    <?php _e('Instant notifications of subscriber changes', 'mavlers-contact-forms'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <strong><?php _e('Subscriber Tracking:', 'mavlers-contact-forms'); ?></strong>
                                    <?php _e('Monitor subscription and unsubscription events', 'mavlers-contact-forms'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <strong><?php _e('Data Integrity:', 'mavlers-contact-forms'); ?></strong>
                                    <?php _e('Keep your data in sync automatically', 'mavlers-contact-forms'); ?>
                                </li>
                                <li>
                                    <span class="dashicons dashicons-chart-line"></span>
                                    <strong><?php _e('Analytics:', 'mavlers-contact-forms'); ?></strong>
                                    <?php _e('Track engagement and list health', 'mavlers-contact-forms'); ?>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="webhook-note">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php _e('Note: Webhooks will be automatically registered for each Mailchimp audience when you enable the integration for forms. You can also manually register webhooks using the audience management section below.', 'mavlers-contact-forms'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="connection-info" id="connection_info" <?php echo !$is_connected ? 'style="display: none;"' : ''; ?>>
                <h3><?php _e('Connection Information', 'mavlers-contact-forms'); ?></h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label><?php _e('Account Name', 'mavlers-contact-forms'); ?></label>
                        <span id="account_name"><?php echo esc_html($global_settings['account_info']['account_name'] ?? 'N/A'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label><?php _e('Email', 'mavlers-contact-forms'); ?></label>
                        <span id="account_email"><?php echo esc_html($global_settings['account_info']['email'] ?? 'N/A'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label><?php _e('Total Subscribers', 'mavlers-contact-forms'); ?></label>
                        <span id="total_subscribers"><?php echo number_format($global_settings['account_info']['total_subscribers'] ?? 0); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label><?php _e('Last Tested', 'mavlers-contact-forms'); ?></label>
                        <span id="last_tested">
                            <?php 
                            if ($global_settings['last_tested']) {
                                echo human_time_diff(strtotime($global_settings['last_tested']), current_time('timestamp')) . ' ' . __('ago', 'mavlers-contact-forms');
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </form>

        <div class="help-section">
            <h3><?php _e('Getting Started', 'mavlers-contact-forms'); ?></h3>
            <div class="help-steps">
                <div class="help-step">
                    <span class="step-number">1</span>
                    <div class="step-content">
                        <h4><?php _e('Get Your API Key', 'mavlers-contact-forms'); ?></h4>
                        <p><?php _e('Log into your Mailchimp account and navigate to Account > Extras > API keys to generate a new API key.', 'mavlers-contact-forms'); ?></p>
                    </div>
                </div>
                
                <div class="help-step">
                    <span class="step-number">2</span>
                    <div class="step-content">
                        <h4><?php _e('Test Connection', 'mavlers-contact-forms'); ?></h4>
                        <p><?php _e('Enter your API key above and click "Test Connection" to verify it\'s working correctly.', 'mavlers-contact-forms'); ?></p>
                    </div>
                </div>
                
                <div class="help-step">
                    <span class="step-number">3</span>
                    <div class="step-content">
                        <h4><?php _e('Configure Forms', 'mavlers-contact-forms'); ?></h4>
                        <p><?php _e('Once connected, go to your form builder and enable Mailchimp integration for individual forms.', 'mavlers-contact-forms'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading_overlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <span class="dashicons dashicons-update-alt spinning"></span>
            <p><?php _e('Testing connection...', 'mavlers-contact-forms'); ?></p>
        </div>
    </div>

    <!-- Message Area -->
    <div id="message_area" class="message-area"></div>
    
    <!-- Analytics Dashboard Section -->
    <div class="mailchimp-analytics-section" <?php echo !$is_connected ? 'style="display: none;"' : ''; ?>>
        <div class="section-header">
            <h3>
                <?php _e('📊 Analytics Dashboard', 'mavlers-contact-forms'); ?>
                <span class="setting-badge new">NEW</span>
            </h3>
            <p class="section-description">
                <?php _e('Monitor your Mailchimp integration performance and view detailed analytics.', 'mavlers-contact-forms'); ?>
            </p>
        </div>
        
        <div class="analytics-toggle">
            <button type="button" id="toggle-analytics-dashboard" class="button button-primary analytics-toggle-btn">
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Show Analytics Dashboard', 'mavlers-contact-forms'); ?>
            </button>
        </div>
        
        <div id="analytics-dashboard-container" class="analytics-dashboard-container" style="display: none;">
            <div class="analytics-loading-placeholder">
                <div class="loading-spinner">
                    <span class="dashicons dashicons-update-alt spinning"></span>
                    <p><?php _e('Loading analytics dashboard...', 'mavlers-contact-forms'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.mailchimp-global-settings {
    max-width: 800px;
    margin: 20px 0;
    position: relative;
}

.integration-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.integration-icon {
    flex-shrink: 0;
}

.integration-info {
    flex-grow: 1;
}

.integration-info h2 {
    margin: 0 0 5px 0;
    font-size: 20px;
    color: #333;
}

.integration-info .description {
    margin: 0;
    color: #666;
}

.connection-status {
    flex-shrink: 0;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 13px;
}

.status-indicator.connected {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-indicator.disconnected {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.settings-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-section {
    padding: 25px;
    border-bottom: 1px solid #eee;
}

.form-section:last-child {
    border-bottom: none;
}

.form-section h3 {
    margin: 0 0 20px 0;
    color: #333;
    font-size: 16px;
}

.form-row {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.required {
    color: #d32f2f;
}

.input-group {
    display: flex;
    gap: 5px;
}

.form-input {
    flex-grow: 1;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-input:focus {
    border-color: #0073aa;
    box-shadow: 0 0 0 1px #0073aa;
    outline: none;
}

.form-help {
    margin: 8px 0 0 0;
    font-size: 13px;
    color: #666;
    line-height: 1.4;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.connection-info {
    padding: 25px;
    background: #f8f9fa;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-item label {
    font-weight: 500;
    color: #555;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item span {
    font-size: 14px;
    color: #333;
}

.help-section {
    padding: 25px;
    background: #f8f9fa;
}

.help-section h3 {
    margin: 0 0 20px 0;
    color: #333;
}

.help-steps {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.help-step {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background: #0073aa;
    color: white;
    border-radius: 50%;
    font-weight: 500;
    font-size: 14px;
    flex-shrink: 0;
}

.step-content h4 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 14px;
}

.step-content p {
    margin: 0;
    color: #666;
    font-size: 13px;
    line-height: 1.4;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    z-index: 10;
}

.loading-spinner {
    text-align: center;
    color: #666;
}

.loading-spinner .dashicons {
    font-size: 24px;
    margin-bottom: 10px;
}

.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.message-area {
    margin-top: 15px;
}

.notice {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.notice.notice-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.notice.notice-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.notice.notice-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}

/* Setting Badge */
.setting-badge {
    display: inline-block;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    border-radius: 3px;
    margin-left: 8px;
}

.setting-badge.new {
    background: #e67e22;
    color: #fff;
}

/* Checkbox Group */
.checkbox-group {
    margin-bottom: 8px;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    cursor: pointer;
    padding: 8px 0;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
    flex-shrink: 0;
}

.checkbox-text {
    color: #333;
    font-weight: 500;
}

/* Batch Info */
.batch-info {
    margin-top: 15px;
    padding: 15px;
    background: #f0f8ff;
    border: 1px solid #d0e7ff;
    border-radius: 6px;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-box h4 {
    margin: 0 0 12px 0;
    color: #2c3e50;
    font-size: 14px;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.feature-list li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 10px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.feature-list li:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.feature-list .dashicons {
    color: #3498db;
    flex-shrink: 0;
    margin-top: 2px;
}

.feature-list strong {
    color: #2c3e50;
    margin-right: 4px;
}

/* Webhook Styles */
.webhook-info {
    margin-top: 15px;
}

.webhook-url-display {
    display: flex;
    gap: 8px;
    align-items: flex-end;
    margin-bottom: 20px;
}

.webhook-url-display label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
    white-space: nowrap;
}

.webhook-url-display input {
    flex-grow: 1;
    font-family: monospace;
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    color: #666;
}

.webhook-url-display button {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.webhook-benefits {
    background: #f0f8ff;
    border: 1px solid #d0e7ff;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
}

.webhook-benefits h4 {
    margin: 0 0 12px 0;
    color: #2c3e50;
    font-size: 14px;
}

.webhook-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 12px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    color: #856404;
}

.webhook-note .dashicons {
    color: #f39c12;
    flex-shrink: 0;
    margin-top: 2px;
}

.webhook-note p {
    margin: 0;
    font-size: 13px;
    line-height: 1.4;
}

/* Analytics Dashboard Styles */
.mailchimp-analytics-section {
    margin-top: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mailchimp-analytics-section .section-header h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mailchimp-analytics-section .section-description {
    margin: 0 0 20px 0;
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

.analytics-toggle {
    margin-bottom: 20px;
}

.analytics-toggle-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.analytics-toggle-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.analytics-toggle-btn .dashicons {
    font-size: 16px;
    height: 16px;
    width: 16px;
}

.analytics-dashboard-container {
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    background: #f8f9fa;
    min-height: 200px;
    position: relative;
}

.analytics-loading-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    color: #666;
}

.analytics-loading-placeholder .loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.analytics-loading-placeholder .dashicons {
    font-size: 32px;
    height: 32px;
    width: 32px;
    animation: spin 1s linear infinite;
}

.analytics-loading-placeholder p {
    margin: 0;
    font-size: 14px;
    color: #888;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .integration-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .help-step {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle API key visibility
    $('#toggle_api_key').on('click', function() {
        const input = $('#mailchimp_api_key');
        const icon = $(this).find('.dashicons');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // Test connection
    $('#test_connection').on('click', function() {
        const apiKey = $('#mailchimp_api_key').val().trim();
        
        if (!apiKey) {
            showMessage('<?php _e('Please enter your API key first.', 'mavlers-contact-forms'); ?>', 'error');
            return;
        }

        showLoading(true);
        clearMessages();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mailchimp_test_connection',
                api_key: apiKey,
                nonce: $('#mailchimp_global_nonce').val()
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
                showMessage('<?php _e('Connection test failed. Please try again.', 'mavlers-contact-forms'); ?>', 'error');
                updateConnectionStatus(false);
            }
        });
    });

    // Toggle batch processing info
    $('#enable_batch_processing').on('change', function() {
        const isChecked = $(this).is(':checked');
        const $info = $('#batch_processing_info');
        
        if (isChecked) {
            $info.slideDown(300);
        } else {
            $info.slideUp(300);
        }
    });

    // Save settings
    $('#mailchimp-global-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const apiKey = $('#mailchimp_api_key').val().trim();
        
        if (!apiKey) {
            showMessage('<?php _e('Please enter your API key.', 'mavlers-contact-forms'); ?>', 'error');
            return;
        }

        showLoading(true);
        clearMessages();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mailchimp_save_global_settings',
                api_key: apiKey,
                enable_batch_processing: $('#enable_batch_processing').is(':checked') ? 1 : 0,
                nonce: $('#mailchimp_global_nonce').val()
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showMessage(response.message, 'success');
                } else {
                    showMessage(response.message || '<?php _e('Save failed. Please try again.', 'mavlers-contact-forms'); ?>', 'error');
                }
            },
            error: function() {
                showLoading(false);
                showMessage('<?php _e('Save failed. Please try again.', 'mavlers-contact-forms'); ?>', 'error');
            }
        });
    });

    // Helper functions
    function showLoading(show) {
        if (show) {
            $('#loading_overlay').show();
        } else {
            $('#loading_overlay').hide();
        }
    }

    function showMessage(message, type) {
        const messageClass = 'notice notice-' + (type === 'error' ? 'error' : type === 'success' ? 'success' : 'info');
        const messageHtml = '<div class="' + messageClass + '">' + message + '</div>';
        $('#message_area').append(messageHtml);
    }

    function clearMessages() {
        $('#message_area').empty();
    }

    function updateConnectionInfo(data) {
        if (data) {
            $('#account_name').text(data.account_name || 'N/A');
            $('#account_email').text(data.email || 'N/A');
            $('#total_subscribers').text(data.total_subscribers ? data.total_subscribers.toLocaleString() : '0');
            $('#last_tested').text('<?php _e('Just now', 'mavlers-contact-forms'); ?>');
            $('#connection_info').show();
        }
    }

    function updateConnectionStatus(connected) {
        const statusIndicator = $('.status-indicator');
        const statusIcon = statusIndicator.find('.dashicons');
        
        if (connected) {
            statusIndicator.removeClass('disconnected').addClass('connected');
            statusIndicator.find('span:last-child').text('<?php _e('Connected', 'mavlers-contact-forms'); ?>');
            statusIcon.removeClass('dashicons-dismiss').addClass('dashicons-yes-alt');
            $('#webhook_section').show();
        } else {
            statusIndicator.removeClass('connected').addClass('disconnected');
            statusIndicator.find('span:last-child').text('<?php _e('Not Connected', 'mavlers-contact-forms'); ?>');
            statusIcon.removeClass('dashicons-yes-alt').addClass('dashicons-dismiss');
            $('#connection_info').hide();
            $('#webhook_section').hide();
        }
    }
    
    // Analytics Dashboard Toggle
    $('#toggle-analytics-dashboard').on('click', function() {
        const $container = $('#analytics-dashboard-container');
        const $button = $(this);
        const $buttonText = $button.find('span:last-child');
        
        if ($container.is(':visible')) {
            // Hide dashboard
            $container.slideUp(400);
            $buttonText.text('<?php _e('Show Analytics Dashboard', 'mavlers-contact-forms'); ?>');
            $button.find('.dashicons').removeClass('dashicons-chart-area').addClass('dashicons-chart-line');
        } else {
            // Show dashboard
            $container.slideDown(400);
            $buttonText.text('<?php _e('Hide Analytics Dashboard', 'mavlers-contact-forms'); ?>');
            $button.find('.dashicons').removeClass('dashicons-chart-line').addClass('dashicons-chart-area');
            
            // Load analytics content if not already loaded
            if (!$container.data('loaded')) {
                loadAnalyticsDashboard();
            }
        }
    });
    
    // Load Analytics Dashboard
    function loadAnalyticsDashboard() {
        const $container = $('#analytics-dashboard-container');
        
        // Show loading state
        $container.find('.analytics-loading-placeholder p').text('<?php _e('Loading analytics dashboard...', 'mavlers-contact-forms'); ?>');
        
        // Load analytics dashboard content
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'load_mailchimp_analytics_dashboard',
                nonce: $('#mailchimp_global_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    // Replace content with actual dashboard
                    $container.html(response.data.html);
                    $container.data('loaded', true);
                    
                    // Initialize analytics dashboard if loaded
                    if (typeof window.initializeAnalyticsDashboard === 'function') {
                        window.initializeAnalyticsDashboard();
                    }
                } else {
                    // Show error message
                    $container.html('<div class="analytics-error"><p>' + (response.message || '<?php _e('Failed to load analytics dashboard.', 'mavlers-contact-forms'); ?>') + '</p></div>');
                }
            },
            error: function() {
                // Show error state
                $container.html('<div class="analytics-error"><p><?php _e('Failed to load analytics dashboard. Please try again.', 'mavlers-contact-forms'); ?></p></div>');
            }
        });
    }
});
</script> 