<?php
/**
 * Integrations Overview Template
 * 
 * Clean HTML template with no inline styles or JavaScript
 * 
 * @var $current_tab string
 * @var $integrations array
 * @var $stats array
 * @var $configured_count int
 * @var $total_count int
 * @var $view IntegrationsView
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap connect2form-integrations-page">
    <h1 class="page-title">
        <span class="dashicons dashicons-admin-plugins"></span>
        <?php esc_html_e('Integrations', 'connect2form'); ?>
    </h1>

    <div class="page-header">
        <p class="page-description">
            <?php esc_html_e('Connect your contact forms with popular email marketing and CRM platforms.', 'connect2form'); ?>
        </p>

        <div class="stats-summary">
            <div class="stat-item">
                <span class="stat-number"><?php echo esc_html($configured_count); ?></span>
                <span class="stat-label"><?php esc_html_e('Configured', 'connect2form'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo esc_html($total_count); ?></span>
                <span class="stat-label"><?php esc_html_e('Available', 'connect2form'); ?></span>
            </div>
        </div>
    </div>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-integrations&tab=overview')); ?>" 
           class="nav-tab nav-tab-active">
            <span class="dashicons dashicons-dashboard"></span>
            <?php esc_html_e('Overview', 'connect2form'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-integrations&tab=settings')); ?>" 
           class="nav-tab">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e('Settings', 'connect2form'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-integrations&tab=logs')); ?>" 
           class="nav-tab">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e('Logs', 'connect2form'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <div class="integrations-grid">
            <?php if (!empty($integrations)): ?>
                <?php foreach ($integrations as $integration): ?>
                    <div class="integration-card" data-integration="<?php $view->attr($integration->getId()); ?>">
                        <div class="card-header">
                            <div class="integration-icon-wrapper" style="color: <?php $view->attr($integration->getColor()); ?>">
                                <?php echo wp_kses_post($view->integration_icon($integration)); ?>
                            </div>
                            <div class="integration-info">
                                <h3 class="integration-name"><?php $view->e($integration->getName()); ?></h3>
                                <p class="integration-description"><?php $view->e($integration->getDescription()); ?></p>
                            </div>
                            <div class="integration-status">
                                <?php if ($view->is_configured($integration)): ?>
                                    <span class="status-indicator configured">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e('Configured', 'connect2form'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-indicator not-configured">
                                        <span class="dashicons dashicons-warning"></span>
                                        <?php esc_html_e('Not Configured', 'connect2form'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="integration-actions">
                                <a href="<?php $view->url($view->integration_url($integration->getId())); ?>" 
                                   class="button button-primary">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                    <?php esc_html_e('Configure', 'connect2form'); ?>
                                </a>
                                
                                <?php if ($view->is_configured($integration)): ?>
                                    <button type="button" 
                                            class="button button-secondary test-connection-btn"
                                            data-integration="<?php $view->attr($integration->getId()); ?>">
                                        <span class="dashicons dashicons-admin-links"></span>
                                        <?php esc_html_e('Test Connection', 'connect2form'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="integration-meta">
                                <div class="meta-item">
                                    <span class="meta-label"><?php esc_html_e('Version:', 'connect2form'); ?></span>
                                    <span class="meta-value"><?php $view->e($integration->getVersion()); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label"><?php esc_html_e('Actions:', 'connect2form'); ?></span>
                                    <span class="meta-value">
                                        <?php 
                                        $actions = $integration->getAvailableActions();
                                        echo esc_html(count($actions)) . ' ' . esc_html(_n('action', 'actions', count($actions), 'connect2form'));
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-integrations">
                    <div class="no-integrations-icon">
                        <span class="dashicons dashicons-admin-plugins"></span>
                    </div>
                    <h3><?php esc_html_e('No Integrations Available', 'connect2form'); ?></h3>
                    <p><?php esc_html_e('No integrations are currently available. Please check your plugin installation.', 'connect2form'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Connection test modal placeholder -->
<div id="connection-test-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4><?php esc_html_e('Testing Connection', 'connect2form'); ?></h4>
            <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'connect2form'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="modal-body">
            <div class="test-status">
                <div class="loading">
                    <span class="spinner"></span>
                    <span><?php esc_html_e('Testing connection...', 'connect2form'); ?></span>
                </div>
                <div class="result" style="display: none;"></div>
            </div>
        </div>
    </div>
</div> 
