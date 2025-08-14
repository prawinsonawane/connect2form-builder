<?php
/**
 * Settings List Template
 * 
 * Displays list of integrations for settings configuration
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Integration Settings', 'mavlers-contact-forms'); ?></h1>
    
    <p class="description">
        <?php esc_html_e('Configure your integrations by clicking on each integration below. Global settings apply to all forms unless overridden at the form level.', 'mavlers-contact-forms'); ?>
    </p>

    <?php if (empty($integrations)): ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('No integrations are available. Please check your plugin installation.', 'mavlers-contact-forms'); ?></p>
        </div>
    <?php else: ?>
        <div class="mavlers-integrations-grid">
            <?php foreach ($integrations as $integration): ?>
                <div class="integration-card <?php echo $integration->isConfigured() ? 'configured' : 'unconfigured'; ?>">
                    <div class="integration-icon">
                        <?php echo $this->integration_icon($integration); ?>
                    </div>
                    
                    <div class="integration-info">
                        <h3><?php echo esc_html($integration->getName()); ?></h3>
                        <p class="description"><?php echo esc_html($integration->getDescription()); ?></p>
                        
                        <div class="integration-status">
                            <?php if ($integration->isConfigured()): ?>
                                <span class="status-badge configured">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('Configured', 'mavlers-contact-forms'); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge unconfigured">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php esc_html_e('Not Configured', 'mavlers-contact-forms'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="integration-actions">
                        <a href="<?php echo esc_url($this->integration_url($integration->getId(), 'settings')); ?>" 
                           class="button button-primary">
                            <?php esc_html_e('Configure', 'mavlers-contact-forms'); ?>
                        </a>
                        
                        <?php if ($integration->isConfigured()): ?>
                            <a href="<?php echo esc_url($this->integration_url($integration->getId(), 'test')); ?>" 
                               class="button button-secondary">
                                <?php esc_html_e('Test Connection', 'mavlers-contact-forms'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.mavlers-integrations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.integration-card {
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    background: #fff;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    transition: box-shadow 0.3s ease;
}

.integration-card:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.integration-card.configured {
    border-left: 4px solid #00a32a;
}

.integration-card.unconfigured {
    border-left: 4px solid #dba617;
}

.integration-icon {
    text-align: center;
    margin-bottom: 15px;
}

.integration-icon .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #666;
}

.integration-icon img {
    max-width: 48px;
    max-height: 48px;
}

.integration-info h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
}

.integration-info .description {
    margin-bottom: 15px;
    color: #666;
    font-size: 14px;
    line-height: 1.4;
}

.integration-status {
    margin-bottom: 20px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.configured {
    background: #d4f3d4;
    color: #155724;
}

.status-badge.unconfigured {
    background: #fff3cd;
    color: #856404;
}

.status-badge .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.integration-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.integration-actions .button {
    flex: 1;
    text-align: center;
    min-width: 120px;
}

@media (max-width: 768px) {
    .mavlers-integrations-grid {
        grid-template-columns: 1fr;
    }
    
    .integration-actions {
        flex-direction: column;
    }
    
    .integration-actions .button {
        flex: none;
    }
}
</style> 