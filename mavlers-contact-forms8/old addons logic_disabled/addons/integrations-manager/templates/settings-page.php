<?php
/**
 * Integration Settings Page Template
 * 
 * Main integrations management page in WordPress admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $available_integrations, $integration_stats
?>

<div class="wrap mavlers-cf-integrations-page">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-plugins"></span>
        <?php _e('Integrations', 'mavlers-contact-forms'); ?>
    </h1>
    
    <a href="#" class="page-title-action add-new-integration">
        <?php _e('Add Integration', 'mavlers-contact-forms'); ?>
    </a>
    
    <hr class="wp-header-end">

    <!-- Integration Statistics Dashboard -->
    <div class="integration-stats-dashboard">
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-admin-plugins"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($available_integrations); ?></h3>
                    <p><?php _e('Active Integrations', 'mavlers-contact-forms'); ?></p>
                </div>
            </div>
            
            <?php if (!empty($integration_stats)): ?>
                <?php 
                $total_submissions = array_sum(array_column($integration_stats, 'total_submissions'));
                $total_successful = array_sum(array_column($integration_stats, 'successful'));
                $success_rate = $total_submissions > 0 ? round(($total_successful / $total_submissions) * 100, 1) : 0;
                ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($total_submissions); ?></h3>
                        <p><?php _e('Total Submissions (30 days)', 'mavlers-contact-forms'); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $success_rate; ?>%</h3>
                        <p><?php _e('Success Rate', 'mavlers-contact-forms'); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($total_submissions - $total_successful); ?></h3>
                        <p><?php _e('Failed Submissions', 'mavlers-contact-forms'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Integration Management Tabs -->
    <div class="integration-management-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#installed" class="nav-tab nav-tab-active" data-tab="installed">
                <?php _e('Installed', 'mavlers-contact-forms'); ?>
                <span class="count">(<?php echo count($available_integrations); ?>)</span>
            </a>
            <a href="#logs" class="nav-tab" data-tab="logs">
                <?php _e('Activity Logs', 'mavlers-contact-forms'); ?>
            </a>
            <a href="#settings" class="nav-tab" data-tab="settings">
                <?php _e('Settings', 'mavlers-contact-forms'); ?>
            </a>
        </nav>

        <!-- Installed Integrations Tab -->
        <div id="installed-tab" class="tab-content active">
            <?php if (empty($available_integrations)): ?>
                <div class="no-integrations-state">
                    <div class="empty-state-icon">
                        <span class="dashicons dashicons-admin-plugins"></span>
                    </div>
                    <h3><?php _e('No Integrations Installed', 'mavlers-contact-forms'); ?></h3>
                    <p><?php _e('Get started by installing your first integration to connect your forms with external services.', 'mavlers-contact-forms'); ?></p>
                    <div class="empty-state-actions">
                        <button type="button" class="button button-primary add-new-integration">
                            <?php _e('Install Integration', 'mavlers-contact-forms'); ?>
                        </button>
                        <a href="https://docs.mavlers.com/contact-forms/integrations" target="_blank" class="button button-secondary">
                            <?php _e('View Documentation', 'mavlers-contact-forms'); ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="integrations-grid">
                    <?php foreach ($available_integrations as $integration_id => $integration): ?>
                        <?php
                        $integration_config = $this->addon_registry->get_integration_config($integration_id);
                        $integration_stats_data = null;
                        
                        foreach ($integration_stats as $stats) {
                            if ($stats['integration_id'] === $integration_id) {
                                $integration_stats_data = $stats;
                                break;
                            }
                        }
                        ?>
                        <div class="integration-item" data-integration-id="<?php echo esc_attr($integration_id); ?>">
                            <div class="integration-item-header">
                                <div class="integration-icon-large" style="background-color: <?php echo esc_attr($integration->get_integration_color()); ?>;">
                                    <?php if (strpos($integration->get_integration_icon(), 'dashicons-') === 0): ?>
                                        <span class="dashicons <?php echo esc_attr($integration->get_integration_icon()); ?>"></span>
                                    <?php else: ?>
                                        <img src="<?php echo esc_url($integration->get_integration_icon()); ?>" alt="<?php echo esc_attr($integration->get_integration_name()); ?>" />
                                    <?php endif; ?>
                                </div>
                                <div class="integration-info">
                                    <h4><?php echo esc_html($integration->get_integration_name()); ?></h4>
                                    <p class="integration-description"><?php echo esc_html($integration->get_integration_description()); ?></p>
                                    <p class="integration-version">
                                        <?php _e('Version:', 'mavlers-contact-forms'); ?> 
                                        <?php echo esc_html($integration->get_integration_version()); ?>
                                    </p>
                                </div>
                                <div class="integration-actions">
                                    <button type="button" class="button configure-global-integration" data-integration-id="<?php echo esc_attr($integration_id); ?>">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                        <?php _e('Configure', 'mavlers-contact-forms'); ?>
                                    </button>
                                    <button type="button" class="button button-secondary test-global-connection" data-integration-id="<?php echo esc_attr($integration_id); ?>">
                                        <span class="dashicons dashicons-admin-tools"></span>
                                        <?php _e('Test', 'mavlers-contact-forms'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($integration_stats_data): ?>
                                <div class="integration-stats">
                                    <div class="stat-item">
                                        <span class="stat-label"><?php _e('Submissions', 'mavlers-contact-forms'); ?></span>
                                        <span class="stat-value"><?php echo number_format($integration_stats_data['total_submissions']); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label"><?php _e('Success Rate', 'mavlers-contact-forms'); ?></span>
                                        <span class="stat-value">
                                            <?php 
                                            $success_rate = $integration_stats_data['total_submissions'] > 0 
                                                ? round(($integration_stats_data['successful'] / $integration_stats_data['total_submissions']) * 100, 1)
                                                : 0;
                                            echo $success_rate . '%';
                                            ?>
                                        </span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label"><?php _e('Failed', 'mavlers-contact-forms'); ?></span>
                                        <span class="stat-value error-count"><?php echo number_format($integration_stats_data['failed']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Activity Logs Tab -->
        <div id="logs-tab" class="tab-content">
            <div class="logs-section">
                <div class="logs-header">
                    <h3><?php _e('Integration Activity Logs', 'mavlers-contact-forms'); ?></h3>
                    <div class="logs-actions">
                        <select id="log-level-filter">
                            <option value=""><?php _e('All Levels', 'mavlers-contact-forms'); ?></option>
                            <option value="error"><?php _e('Errors Only', 'mavlers-contact-forms'); ?></option>
                            <option value="warning"><?php _e('Warnings', 'mavlers-contact-forms'); ?></option>
                            <option value="info"><?php _e('Info', 'mavlers-contact-forms'); ?></option>
                        </select>
                        <select id="integration-filter">
                            <option value=""><?php _e('All Integrations', 'mavlers-contact-forms'); ?></option>
                            <?php foreach ($available_integrations as $integration_id => $integration): ?>
                                <option value="<?php echo esc_attr($integration_id); ?>">
                                    <?php echo esc_html($integration->get_integration_name()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button refresh-logs">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Refresh', 'mavlers-contact-forms'); ?>
                        </button>
                        <button type="button" class="button export-logs">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export', 'mavlers-contact-forms'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="logs-container">
                    <div class="logs-loading" style="display: none;">
                        <span class="dashicons dashicons-update-alt"></span>
                        <?php _e('Loading logs...', 'mavlers-contact-forms'); ?>
                    </div>
                    <div id="logs-table-container">
                        <?php
                        // Load initial logs data
                        $filters = array(
                            'status' => '',
                            'integration_id' => '',
                            'form_id' => '',
                            'date_range' => '',
                            'search' => ''
                        );
                        $per_page = 20;
                        $current_page = 1;
                        $offset = 0;
                        
                        global $wpdb;
                        $logs_query = "SELECT * FROM {$wpdb->prefix}mavlers_cf_integration_logs 
                                       ORDER BY created_at DESC 
                                       LIMIT %d OFFSET %d";
                        $logs = $wpdb->get_results($wpdb->prepare($logs_query, $per_page, $offset));
                        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mavlers_cf_integration_logs");
                        
                        // Include the logs table template
                        include MAVLERS_CF_INTEGRATIONS_DIR . 'templates/integration-logs-table.php';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content">
            <div class="settings-section">
                <h3><?php _e('Integration Settings', 'mavlers-contact-forms'); ?></h3>
                
                <form id="integration-settings-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="logging_enabled"><?php _e('Enable Logging', 'mavlers-contact-forms'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="logging_enabled" name="logging_enabled" value="1" 
                                           <?php checked(get_option('mavlers_cf_integrations_logging_enabled', true)); ?> />
                                    <?php _e('Log integration activities and errors', 'mavlers-contact-forms'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="min_log_level"><?php _e('Minimum Log Level', 'mavlers-contact-forms'); ?></label>
                            </th>
                            <td>
                                <select id="min_log_level" name="min_log_level">
                                    <option value="debug" <?php selected(get_option('mavlers_cf_integrations_min_log_level', 'info'), 'debug'); ?>><?php _e('Debug', 'mavlers-contact-forms'); ?></option>
                                    <option value="info" <?php selected(get_option('mavlers_cf_integrations_min_log_level', 'info'), 'info'); ?>><?php _e('Info', 'mavlers-contact-forms'); ?></option>
                                    <option value="warning" <?php selected(get_option('mavlers_cf_integrations_min_log_level', 'info'), 'warning'); ?>><?php _e('Warning', 'mavlers-contact-forms'); ?></option>
                                    <option value="error" <?php selected(get_option('mavlers_cf_integrations_min_log_level', 'info'), 'error'); ?>><?php _e('Error', 'mavlers-contact-forms'); ?></option>
                                </select>
                                <p class="description"><?php _e('Only log messages at this level or higher.', 'mavlers-contact-forms'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="notification_email"><?php _e('Notification Email', 'mavlers-contact-forms'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="notification_email" name="notification_email" class="regular-text"
                                       value="<?php echo esc_attr(get_option('mavlers_cf_integrations_notification_email', get_option('admin_email'))); ?>" />
                                <p class="description"><?php _e('Email address to receive critical error notifications.', 'mavlers-contact-forms'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="background_processing"><?php _e('Background Processing', 'mavlers-contact-forms'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="background_processing" name="background_processing" value="1" 
                                           <?php checked(get_option('mavlers_cf_integrations_background_processing', true)); ?> />
                                    <?php _e('Process integrations in the background to improve form submission speed', 'mavlers-contact-forms'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="retry_failed"><?php _e('Retry Failed Submissions', 'mavlers-contact-forms'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="retry_failed" name="retry_failed" value="1" 
                                           <?php checked(get_option('mavlers_cf_integrations_retry_failed', true)); ?> />
                                    <?php _e('Automatically retry failed integration submissions', 'mavlers-contact-forms'); ?>
                                </label>
                                <p class="description"><?php _e('Failed submissions will be retried up to 3 times with exponential backoff.', 'mavlers-contact-forms'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Save Settings', 'mavlers-contact-forms'); ?>
                        </button>
                    </p>
                </form>
                
                <hr />
                
                <h3><?php _e('Maintenance', 'mavlers-contact-forms'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Clear Logs', 'mavlers-contact-forms'); ?></th>
                        <td>
                            <button type="button" class="button button-secondary clear-logs" data-confirm="<?php esc_attr_e('Are you sure you want to clear all integration logs?', 'mavlers-contact-forms'); ?>">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Clear All Logs', 'mavlers-contact-forms'); ?>
                            </button>
                            <p class="description"><?php _e('Remove all integration logs from the database. This action cannot be undone.', 'mavlers-contact-forms'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Reset Integrations', 'mavlers-contact-forms'); ?></th>
                        <td>
                            <button type="button" class="button button-secondary reset-integrations" data-confirm="<?php esc_attr_e('Are you sure you want to reset all integration configurations?', 'mavlers-contact-forms'); ?>">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Reset All Configurations', 'mavlers-contact-forms'); ?>
                            </button>
                            <p class="description"><?php _e('Reset all integration configurations and mappings. This will not affect the integration addons themselves.', 'mavlers-contact-forms'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Integration Setup Modal -->
<div id="integration-setup-modal" class="integration-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title"><?php _e('Setup Integration', 'mavlers-contact-forms'); ?></h3>
            <button type="button" class="modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="modal-body">
            <div id="modal-content-area">
                <!-- Content will be loaded here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-secondary modal-cancel">
                <?php _e('Cancel', 'mavlers-contact-forms'); ?>
            </button>
            <button type="button" class="button button-primary modal-save">
                <?php _e('Save Settings', 'mavlers-contact-forms'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Test Connection Modal -->
<div id="test-connection-modal" class="integration-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Test Connection', 'mavlers-contact-forms'); ?></h3>
            <button type="button" class="modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="modal-body">
            <div id="test-connection-results">
                <!-- Results will be loaded here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-secondary modal-close">
                <?php _e('Close', 'mavlers-contact-forms'); ?>
            </button>
        </div>
    </div>
</div> 

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize integration management
    if (typeof MavlersCFIntegrations !== 'undefined') {
        MavlersCFIntegrations.init();
    } else {
        console.error('MavlersCFIntegrations object not found');
    }
});
</script>

<style>
.integration-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #666;
}

.modal-close:hover {
    color: #333;
}

.modal-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid #eee;
}

.integration-config-form {
    max-width: 100%;
}

.integration-form-row {
    margin-bottom: 15px;
}

.integration-form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.integration-field {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.field-description {
    margin-top: 5px;
    color: #666;
    font-size: 13px;
}

.connection-result {
    padding: 12px;
    border-radius: 4px;
    margin-top: 10px;
}

.connection-result.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.connection-result.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.loading-spinner {
    text-align: center;
    padding: 40px;
}

.loading-spinner .dashicons {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style> 