<?php
/**
 * Logs Table Template
 * 
 * Template for displaying integration logs in the admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $logs, $total_count, $current_page, $per_page
?>

<div class="logs-table-wrapper">
    <?php if (empty($logs)): ?>
        <div class="no-logs-message">
            <p><?php _e('No logs found matching your criteria.', 'mavlers-contact-forms'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped logs-table">
            <thead>
                <tr>
                    <th scope="col" class="column-timestamp"><?php _e('Timestamp', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-level"><?php _e('Level', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-integration"><?php _e('Integration', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-message"><?php _e('Message', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-context"><?php _e('Details', 'mavlers-contact-forms'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr class="log-entry log-level-<?php echo esc_attr($log['level']); ?>">
                        <td class="column-timestamp">
                            <time datetime="<?php echo esc_attr($log['created_at']); ?>" title="<?php echo esc_attr($log['created_at']); ?>">
                                <?php echo human_time_diff(strtotime($log['created_at'])); ?> <?php _e('ago', 'mavlers-contact-forms'); ?>
                            </time>
                        </td>
                        <td class="column-level">
                            <span class="log-level-badge level-<?php echo esc_attr($log['level']); ?>">
                                <?php
                                $level_icons = array(
                                    'emergency' => 'dashicons-warning',
                                    'alert' => 'dashicons-flag',
                                    'critical' => 'dashicons-dismiss',
                                    'error' => 'dashicons-no-alt',
                                    'warning' => 'dashicons-warning',
                                    'notice' => 'dashicons-info',
                                    'info' => 'dashicons-info',
                                    'debug' => 'dashicons-admin-tools'
                                );
                                $icon = isset($level_icons[$log['level']]) ? $level_icons[$log['level']] : 'dashicons-admin-generic';
                                ?>
                                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                                <?php echo esc_html(ucfirst($log['level'])); ?>
                            </span>
                        </td>
                        <td class="column-integration">
                            <?php if (!empty($log['integration_id'])): ?>
                                <span class="integration-name"><?php echo esc_html($log['integration_id']); ?></span>
                            <?php else: ?>
                                <span class="integration-name system"><?php _e('System', 'mavlers-contact-forms'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-message">
                            <div class="log-message">
                                <?php echo esc_html($log['message']); ?>
                            </div>
                            <?php if (!empty($log['form_id'])): ?>
                                <div class="log-meta">
                                    <small>
                                        <?php _e('Form ID:', 'mavlers-contact-forms'); ?> 
                                        <a href="<?php echo admin_url('admin.php?page=mavlers-cf-forms&action=edit&form_id=' . $log['form_id']); ?>">
                                            #<?php echo esc_html($log['form_id']); ?>
                                        </a>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="column-context">
                            <?php if (!empty($log['context'])): ?>
                                <button type="button" class="button button-small view-log-details" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('View Details', 'mavlers-contact-forms'); ?>
                                </button>
                            <?php else: ?>
                                <span class="no-details"><?php _e('—', 'mavlers-contact-forms'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <?php if (!empty($log['context'])): ?>
                        <tr class="log-details" id="log-details-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                            <td colspan="5">
                                <div class="log-context">
                                    <h4><?php _e('Log Details', 'mavlers-contact-forms'); ?></h4>
                                    <pre class="log-context-data"><?php echo esc_html(wp_json_encode(json_decode($log['context']), JSON_PRETTY_PRINT)); ?></pre>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_count > $per_page): ?>
            <div class="logs-pagination">
                <?php
                $total_pages = ceil($total_count / $per_page);
                echo paginate_links(array(
                    'base' => add_query_arg('log_page', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo; Previous', 'mavlers-contact-forms'),
                    'next_text' => __('Next &raquo;', 'mavlers-contact-forms'),
                    'total' => $total_pages,
                    'current' => $current_page
                ));
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.logs-table-wrapper {
    margin-top: 20px;
}

.logs-table .column-timestamp {
    width: 15%;
}

.logs-table .column-level {
    width: 12%;
}

.logs-table .column-integration {
    width: 15%;
}

.logs-table .column-message {
    width: 45%;
}

.logs-table .column-context {
    width: 13%;
}

.log-level-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.log-level-badge.level-emergency,
.log-level-badge.level-alert,
.log-level-badge.level-critical,
.log-level-badge.level-error {
    background-color: #dc3232;
    color: #ffffff;
}

.log-level-badge.level-warning {
    background-color: #ffb900;
    color: #ffffff;
}

.log-level-badge.level-notice,
.log-level-badge.level-info {
    background-color: #007cba;
    color: #ffffff;
}

.log-level-badge.level-debug {
    background-color: #646970;
    color: #ffffff;
}

.log-entry.log-level-error {
    background-color: #fef7f7;
}

.log-entry.log-level-warning {
    background-color: #fff8e5;
}

.log-message {
    font-weight: 500;
    margin-bottom: 4px;
}

.log-meta {
    color: #646970;
}

.integration-name.system {
    font-style: italic;
    color: #646970;
}

.log-context {
    padding: 20px;
    background-color: #f6f7f7;
    border-radius: 4px;
    margin: 10px 0;
}

.log-context h4 {
    margin: 0 0 12px 0;
    color: #1d2327;
}

.log-context-data {
    background-color: #ffffff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 12px;
    font-size: 12px;
    line-height: 1.4;
    overflow-x: auto;
    margin: 0;
}

.no-logs-message {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.logs-pagination {
    margin-top: 20px;
    text-align: center;
}

.view-log-details {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.no-details {
    color: #646970;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle log details
    $(document).on('click', '.view-log-details', function(e) {
        e.preventDefault();
        var logId = $(this).data('log-id');
        var $details = $('#log-details-' + logId);
        
        if ($details.is(':visible')) {
            $details.hide();
            $(this).find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            $(this).find('span:not(.dashicons)').text('<?php _e('View Details', 'mavlers-contact-forms'); ?>');
        } else {
            $details.show();
            $(this).find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            $(this).find('span:not(.dashicons)').text('<?php _e('Hide Details', 'mavlers-contact-forms'); ?>');
        }
    });
});
</script> 