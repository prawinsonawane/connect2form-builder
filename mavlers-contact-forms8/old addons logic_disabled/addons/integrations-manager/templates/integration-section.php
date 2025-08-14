<?php
/**
 * Integration Section Template
 * 
 * Rendered in the form builder's integration tab
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $available_integrations, $form_integrations, $form_id, $form
?>

<div class="mavlers-cf-additional-integrations" data-form-id="<?php echo esc_attr($form_id); ?>">
    <div class="integration-section-header">
        <h4><?php _e('Advanced Integrations', 'mavlers-contact-forms'); ?></h4>
        <p class="description">
            <?php _e('Connect your form with powerful third-party services to automate your workflows.', 'mavlers-contact-forms'); ?>
        </p>
    </div>

    <?php if (empty($available_integrations)): ?>
        <div class="no-integrations-notice">
            <div class="integration-placeholder">
                <span class="dashicons dashicons-admin-plugins"></span>
                <h5><?php _e('No Additional Integrations Found', 'mavlers-contact-forms'); ?></h5>
                <p><?php _e('Install integration addons to connect with services like Zapier, ActiveCampaign, and more.', 'mavlers-contact-forms'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mavlers-cf-integrations'); ?>" class="button button-secondary">
                    <?php _e('Browse Integrations', 'mavlers-contact-forms'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="available-integrations">
            <?php foreach ($available_integrations as $integration_id => $integration): ?>
                <?php
                $form_integration_settings = null;
                
                // Check if this integration is already configured for this form
                foreach ($form_integrations as $form_integration) {
                    if ($form_integration['integration_id'] === $integration_id) {
                        $form_integration_settings = $form_integration;
                        break;
                    }
                }
                
                $is_configured = !empty($form_integration_settings);
                $is_enabled = $is_configured && !empty($form_integration_settings['is_active']);
                ?>
                
                <div class="integration-card" data-integration-id="<?php echo esc_attr($integration_id); ?>">
                    <div class="integration-card-header">
                        <div class="integration-info">
                            <div class="integration-icon" style="background-color: <?php echo esc_attr($integration->get_integration_color()); ?>;">
                                <?php if (strpos($integration->get_integration_icon(), 'dashicons-') === 0): ?>
                                    <span class="dashicons <?php echo esc_attr($integration->get_integration_icon()); ?>"></span>
                                <?php else: ?>
                                    <img src="<?php echo esc_url($integration->get_integration_icon()); ?>" alt="<?php echo esc_attr($integration->get_integration_name()); ?>" />
                                <?php endif; ?>
                            </div>
                            <div class="integration-details">
                                <h5 class="integration-name"><?php echo esc_html($integration->get_integration_name()); ?></h5>
                                <p class="integration-description"><?php echo esc_html($integration->get_integration_description()); ?></p>
                            </div>
                        </div>
                        <div class="integration-actions">
                            <label class="integration-toggle">
                                <input type="checkbox" 
                                       class="integration-enabled-checkbox" 
                                       data-integration-id="<?php echo esc_attr($integration_id); ?>"
                                       <?php checked($is_enabled); ?> />
                                <span class="toggle-slider"></span>
                            </label>
                            <button type="button" class="button button-secondary configure-integration" 
                                    data-integration-id="<?php echo esc_attr($integration_id); ?>">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php _e('Configure', 'mavlers-contact-forms'); ?>
                            </button>
                        </div>
                    </div>

                    <?php if ($is_configured): ?>
                        <div class="integration-status">
                            <div class="status-indicator status-<?php echo $is_enabled ? 'active' : 'inactive'; ?>">
                                <span class="status-dot"></span>
                                <?php echo $is_enabled ? __('Active', 'mavlers-contact-forms') : __('Inactive', 'mavlers-contact-forms'); ?>
                            </div>
                            <?php if ($is_enabled): ?>
                                <span class="last-sync">
                                    <?php _e('Last sync:', 'mavlers-contact-forms'); ?>
                                    <time datetime="<?php echo esc_attr($form_integration_settings['updated_at']); ?>">
                                        <?php echo human_time_diff(strtotime($form_integration_settings['updated_at'])); ?> <?php _e('ago', 'mavlers-contact-forms'); ?>
                                    </time>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Integration Configuration Panel (Hidden by default) -->
                    <div class="integration-config-panel" id="config-panel-<?php echo esc_attr($integration_id); ?>" style="display: none;">
                        <div class="config-panel-content">
                            <?php if ($integration_id === 'mailchimp'): ?>
                                <!-- Load Mailchimp form settings directly -->
                                <?php 
                                // Ensure the form_id is available for the Mailchimp template
                                $mailchimp_form_id = $form_id ?? $GLOBALS['mavlers_cf_current_form_id'] ?? 0;
                                include MAVLERS_CF_INTEGRATIONS_DIR . 'templates/mailchimp-form-settings.php'; 
                                ?>
                            <?php else: ?>
                                <!-- Configuration content will be loaded via AJAX for other integrations -->
                                <div class="loading-spinner">
                                    <span class="dashicons dashicons-update-alt"></span>
                                    <?php _e('Loading configuration...', 'mavlers-contact-forms'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Integration Setup Modal -->
        <div id="integration-setup-modal-form" class="mavlers-cf-modal" style="display: none;">
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
                        <!-- Dynamic content loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-secondary modal-cancel">
                        <?php _e('Cancel', 'mavlers-contact-forms'); ?>
                    </button>
                    <button type="button" class="button button-primary modal-save">
                        <?php _e('Save Integration', 'mavlers-contact-forms'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Field Mapping Modal -->
        <div id="field-mapping-modal" class="mavlers-cf-modal field-mapping-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Map Form Fields', 'mavlers-contact-forms'); ?></h3>
                    <button type="button" class="modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="field-mapping-container">
                        <!-- Field mapping interface loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-secondary auto-map-fields">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php _e('Auto Map', 'mavlers-contact-forms'); ?>
                    </button>
                    <button type="button" class="button button-secondary modal-cancel">
                        <?php _e('Cancel', 'mavlers-contact-forms'); ?>
                    </button>
                    <button type="button" class="button button-primary save-field-mapping">
                        <?php _e('Save Mapping', 'mavlers-contact-forms'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Test Connection Modal -->
        <div id="test-connection-modal" class="mavlers-cf-modal" style="display: none;">
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
                        <!-- Test results will be displayed here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-secondary modal-close">
                        <?php _e('Close', 'mavlers-contact-forms'); ?>
                    </button>
                    <button type="button" class="button button-primary test-connection-btn">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Test Connection', 'mavlers-contact-forms'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Integration Actions Bar -->
    <div class="integration-actions-bar" style="<?php echo empty($available_integrations) ? 'display:none;' : ''; ?>">
        <div class="actions-left">
            <button type="button" class="button button-secondary refresh-integrations">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'mavlers-contact-forms'); ?>
            </button>
        </div>
        <div class="actions-right">
            <a href="<?php echo admin_url('admin.php?page=mavlers-cf-integrations'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php _e('Manage Integrations', 'mavlers-contact-forms'); ?>
            </a>
        </div>
    </div>
</div>

<script type="text/template" id="integration-auth-template">
    <div class="integration-auth-section">
        <h4><?php _e('Authentication', 'mavlers-contact-forms'); ?></h4>
        <div class="auth-fields">
            <!-- Auth fields will be populated by JavaScript -->
        </div>
    </div>
</script>

<script type="text/template" id="integration-actions-template">
    <div class="integration-actions-section">
        <h4><?php _e('Actions', 'mavlers-contact-forms'); ?></h4>
        <div class="action-selection">
            <!-- Action fields will be populated by JavaScript -->
        </div>
    </div>
</script>

<script type="text/template" id="field-mapping-template">
    <div class="field-mapping-section">
        <h4><?php _e('Field Mapping', 'mavlers-contact-forms'); ?></h4>
        <p class="description">
            <?php _e('Map your form fields to the integration fields below. Drag and drop or use the dropdowns.', 'mavlers-contact-forms'); ?>
        </p>
        <div class="mapping-container">
            <div class="form-fields-column">
                <h5><?php _e('Form Fields', 'mavlers-contact-forms'); ?></h5>
                <div class="field-list" id="form-fields-list">
                    <!-- Form fields will be populated here -->
                </div>
            </div>
            <div class="mapping-arrows">
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </div>
            <div class="integration-fields-column">
                <h5><?php _e('Integration Fields', 'mavlers-contact-forms'); ?></h5>
                <div class="field-list" id="integration-fields-list">
                    <!-- Integration fields will be populated here -->
                </div>
            </div>
        </div>
    </div>
</script>

<script>
jQuery(document).ready(function($) {
    // Handle integration toggle
    $('.integration-enabled-checkbox').on('change', function() {
        const integrationId = $(this).data('integration-id');
        const isEnabled = $(this).is(':checked');
        const configPanel = $('#config-panel-' + integrationId);
        
        if (isEnabled) {
            configPanel.slideDown();
            // If it's Mailchimp, also enable the inner toggle
            if (integrationId === 'mailchimp') {
                configPanel.find('#mailchimp_enabled').prop('checked', true).trigger('change');
            }
        } else {
            configPanel.slideUp();
            // If it's Mailchimp, also disable the inner toggle
            if (integrationId === 'mailchimp') {
                configPanel.find('#mailchimp_enabled').prop('checked', false).trigger('change');
            }
        }
    });
    
    // Handle configure button
    $('.configure-integration').on('click', function() {
        const integrationId = $(this).data('integration-id');
        const configPanel = $('#config-panel-' + integrationId);
        
        if (configPanel.is(':visible')) {
            configPanel.slideUp();
        } else {
            configPanel.slideDown();
        }
    });
    
    // Sync outer toggle with inner Mailchimp toggle
    $(document).on('change', '#mailchimp_enabled', function() {
        const isEnabled = $(this).is(':checked');
        $('.integration-enabled-checkbox[data-integration-id="mailchimp"]').prop('checked', isEnabled);
    });
    
    // Show config panel if integration is already enabled
    $('.integration-enabled-checkbox').each(function() {
        const integrationId = $(this).data('integration-id');
        const configPanel = $('#config-panel-' + integrationId);
        
        // Check if Mailchimp form settings are already enabled
        if (integrationId === 'mailchimp') {
            const mailchimpEnabled = configPanel.find('#mailchimp_enabled').is(':checked');
            $(this).prop('checked', mailchimpEnabled);
            if (mailchimpEnabled) {
                configPanel.show();
            }
        }
    });
});
</script> 