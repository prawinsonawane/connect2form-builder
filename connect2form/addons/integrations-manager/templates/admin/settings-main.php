<?php
/**
 * Main Settings Template
 * 
 * Displays global integration settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin settings view; read-only tab selection
$current_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'global' ) );
?>

<div class="wrap">
    <h1><?php esc_html_e('Integration Settings', 'connect2form'); ?></h1>
    
    <h2><?php esc_html_e('Global Integration Settings', 'connect2form'); ?></h2>

    <form method="post" action="">
        <?php wp_nonce_field('connect2form_integrations_settings', 'connect2form_integrations_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="enable_logging"><?php esc_html_e('Enable Logging', 'connect2form'); ?></label>
                </th>
                <td>
                    <input type="checkbox" 
                           id="enable_logging" 
                           name="connect2form_integrations_settings[enable_logging]" 
                           value="1" 
                           <?php checked(!empty($global_settings['enable_logging'])); ?> />
                    <p class="description">
                        <?php esc_html_e('Enable detailed logging for integration activities and troubleshooting.', 'connect2form'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="log_retention_days"><?php esc_html_e('Log Retention (Days)', 'connect2form'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="log_retention_days" 
                           name="connect2form_integrations_settings[log_retention_days]" 
                           value="<?php echo esc_attr($global_settings['log_retention_days'] ?? 30); ?>" 
                           min="1" 
                           max="365" 
                           class="small-text" />
                    <p class="description">
                        <?php esc_html_e('Number of days to keep integration logs before automatic cleanup.', 'connect2form'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="batch_processing"><?php esc_html_e('Batch Processing', 'connect2form'); ?></label>
                </th>
                <td>
                    <input type="checkbox" 
                           id="batch_processing" 
                           name="connect2form_integrations_settings[batch_processing]" 
                           value="1" 
                           <?php checked(!empty($global_settings['batch_processing'])); ?> />
                    <p class="description">
                        <?php esc_html_e('Enable batch processing for improved performance with high-volume forms.', 'connect2form'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>
