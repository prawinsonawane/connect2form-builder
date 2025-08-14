<?php
/**
 * Main Settings Template
 * 
 * Displays global integration settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_tab = sanitize_text_field($_GET['tab'] ?? 'global');
?>

<div class="wrap">
    <h1><?php esc_html_e('Integration Settings', 'mavlers-contact-forms'); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=mavlers-cf-integrations-settings&tab=global')); ?>" 
           class="nav-tab <?php echo $current_tab === 'global' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Global Settings', 'mavlers-contact-forms'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=mavlers-cf-integrations-settings&tab=advanced')); ?>" 
           class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Advanced', 'mavlers-contact-forms'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <?php if ($current_tab === 'global'): ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('mavlers_cf_integrations_settings');
                do_settings_sections('mavlers_cf_integrations_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_logging"><?php esc_html_e('Enable Logging', 'mavlers-contact-forms'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="enable_logging" 
                                   name="mavlers_cf_integrations_settings[enable_logging]" 
                                   value="1" 
                                   <?php checked(!empty($global_settings['enable_logging'])); ?> />
                            <p class="description">
                                <?php esc_html_e('Enable detailed logging for integration activities and troubleshooting.', 'mavlers-contact-forms'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="log_retention_days"><?php esc_html_e('Log Retention (Days)', 'mavlers-contact-forms'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="log_retention_days" 
                                   name="mavlers_cf_integrations_settings[log_retention_days]" 
                                   value="<?php echo esc_attr($global_settings['log_retention_days'] ?? 30); ?>" 
                                   min="1" 
                                   max="365" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e('Number of days to keep integration logs before automatic cleanup.', 'mavlers-contact-forms'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="batch_processing"><?php esc_html_e('Batch Processing', 'mavlers-contact-forms'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="batch_processing" 
                                   name="mavlers_cf_integrations_settings[batch_processing]" 
                                   value="1" 
                                   <?php checked(!empty($global_settings['batch_processing'])); ?> />
                            <p class="description">
                                <?php esc_html_e('Enable batch processing for improved performance with high-volume forms.', 'mavlers-contact-forms'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
        <?php elseif ($current_tab === 'advanced'): ?>
            <div class="mavlers-advanced-settings">
                <h2><?php esc_html_e('Advanced Configuration', 'mavlers-contact-forms'); ?></h2>
                
                <!-- Integration Settings Links -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h3><?php esc_html_e('Integration Settings', 'mavlers-contact-forms'); ?></h3>
                    </div>
                    <div class="inside">
                        <p><?php esc_html_e('Configure individual integrations:', 'mavlers-contact-forms'); ?></p>
                        <ul>
                            <?php if (!empty($integrations)): ?>
                                <?php foreach ($integrations as $integration): ?>
                                    <li>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=mavlers-cf-integration-settings&integration=' . $integration->getId())); ?>">
                                            <?php echo esc_html($integration->getName()); ?>
                                        </a>
                                        <?php if ($integration->isConfigured()): ?>
                                            <span class="status-badge configured"><?php esc_html_e('Configured', 'mavlers-contact-forms'); ?></span>
                                        <?php else: ?>
                                            <span class="status-badge unconfigured"><?php esc_html_e('Not Configured', 'mavlers-contact-forms'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><?php esc_html_e('No integrations available', 'mavlers-contact-forms'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h3><?php esc_html_e('System Information', 'mavlers-contact-forms'); ?></h3>
                    </div>
                    <div class="inside">
                        <table class="widefat">
                            <tr>
                                <td><strong><?php esc_html_e('Plugin Version:', 'mavlers-contact-forms'); ?></strong></td>
                                <td><?php echo esc_html(defined('MAVLERS_CF_INTEGRATIONS_VERSION') ? MAVLERS_CF_INTEGRATIONS_VERSION : '2.0.0'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Available Integrations:', 'mavlers-contact-forms'); ?></strong></td>
                                <td><?php echo esc_html(count($integrations ?? [])); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Configured Integrations:', 'mavlers-contact-forms'); ?></strong></td>
                                <td>
                                    <?php 
                                    $configured = 0;
                                    if (!empty($integrations)) {
                                        foreach ($integrations as $integration) {
                                            if ($integration->isConfigured()) {
                                                $configured++;
                                            }
                                        }
                                    }
                                    echo esc_html($configured);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('PHP Version:', 'mavlers-contact-forms'); ?></strong></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('WordPress Version:', 'mavlers-contact-forms'); ?></strong></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h3><?php esc_html_e('Debug Tools', 'mavlers-contact-forms'); ?></h3>
                    </div>
                    <div class="inside">
                        <p>
                            <button type="button" class="button" id="clear-logs">
                                <?php esc_html_e('Clear All Logs', 'mavlers-contact-forms'); ?>
                            </button>
                            <button type="button" class="button" id="test-integrations">
                                <?php esc_html_e('Test All Connections', 'mavlers-contact-forms'); ?>
                            </button>
                        </p>
                        <p class="description">
                            <?php esc_html_e('Use these tools to troubleshoot integration issues.', 'mavlers-contact-forms'); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.mavlers-advanced-settings .postbox {
    margin-top: 20px;
}

.mavlers-advanced-settings .widefat td {
    padding: 10px;
}

.nav-tab-wrapper {
    margin-bottom: 20px;
}
</style> 