<?php
/**
 * Integration Logs Template
 * 
 * Displays integration activity logs and debugging information
 */

if (!defined('ABSPATH')) {
    exit;
}

$per_page = 50;
// Read-only filters from URL; sanitize and unslash.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- viewing/admin filter UI only, no state change
$paged_raw    = $_GET['paged'] ?? '1';
$current_page = max( 1, absint( wp_unslash( $paged_raw ) ) );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- viewing/admin filter UI only, no state change
$status    = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- viewing/admin filter UI only, no state change
$date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- viewing/admin filter UI only, no state change
$date_to   = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) );
?>

<div class="wrap">
    <h1><?php esc_html_e('Integration Logs', 'connect2form'); ?></h1>
    
    <div class="connect2form-logs-header">
        <div class="log-filters">
            <form method="get" class="log-filter-form">
                <input type="hidden" name="page" value="connect2form-integrations" />
                <input type="hidden" name="tab" value="logs" />
                
                <select name="integration" id="log-integration-filter">
                    <option value=""><?php esc_html_e('All Integrations', 'connect2form'); ?></option>
                    <?php if (!empty($integrations)): ?>
                        <?php foreach ($integrations as $integration): ?>
                            <option value="<?php echo esc_attr($integration->getId()); ?>" 
                                    <?php selected($selected_integration, $integration->getId()); ?>>
                                <?php echo esc_html($integration->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                
                <select name="status" id="log-status-filter">
                    <option value=""><?php esc_html_e('All Status', 'connect2form'); ?></option>
                    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter UI only
                    $status_sel = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ); ?>
                    <option value="success" <?php selected( $status_sel, 'success' ); ?>><?php esc_html_e('Success', 'connect2form'); ?></option>
                    <option value="error" <?php selected( $status_sel, 'error' ); ?>><?php esc_html_e('Error', 'connect2form'); ?></option>
                    <option value="warning" <?php selected( $status_sel, 'warning' ); ?>><?php esc_html_e('Warning', 'connect2form'); ?></option>
                </select>
                
                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter UI only ?>
                <input type="date" name="date_from" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ) ); ?>" />
                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter UI only ?>
                <input type="date" name="date_to" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ) ); ?>" />
                
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'connect2form'); ?>" />
                <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-integrations&tab=logs')); ?>" 
                   class="button">
                    <?php esc_html_e('Clear', 'connect2form'); ?>
                </a>
            </form>
        </div>
        
        <div class="log-actions">
            <button type="button" id="clear-all-logs" class="button button-secondary">
                <?php esc_html_e('Clear All Logs', 'connect2form'); ?>
            </button>
            <button type="button" id="export-logs" class="button">
                <?php esc_html_e('Export Logs', 'connect2form'); ?>
            </button>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('No logs found. Integration activity will appear here once forms are submitted.', 'connect2form'); ?></p>
        </div>
    <?php else: ?>
        <div class="connect2form-logs-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-date"><?php esc_html_e('Date', 'connect2form'); ?></th>
                        <th scope="col" class="column-integration"><?php esc_html_e('Integration', 'connect2form'); ?></th>
                        <th scope="col" class="column-form"><?php esc_html_e('Form', 'connect2form'); ?></th>
                        <th scope="col" class="column-status"><?php esc_html_e('Status', 'connect2form'); ?></th>
                        <th scope="col" class="column-message"><?php esc_html_e('Message', 'connect2form'); ?></th>
                        <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'connect2form'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr class="log-entry log-<?php echo esc_attr($log['status']); ?>">
                            <td class="column-date">
                                <strong><?php echo esc_html($this->format_date($log['created_at'])); ?></strong>
                            </td>
                            <td class="column-integration">
                                <?php 
                                $integration = null;
                                if (!empty($integrations)) {
                                    foreach ($integrations as $int) {
                                        if ($int->getId() === $log['integration_id']) {
                                            $integration = $int;
                                            break;
                                        }
                                    }
                                }
                                if ($integration): 
                                ?>
                                    <div class="integration-info">
                                        <?php echo wp_kses_post($this->integration_icon($integration)); ?>
                                        <span><?php echo esc_html($integration->getName()); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span><?php echo esc_html($log['integration_id']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-form">
                                <?php if (!empty($log['form_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $log['form_id'] . '&action=edit')); ?>">
                                        <?php /* translators: %d: Form ID number */ ?>
                                        <?php echo esc_html(sprintf(__('Form #%d', 'connect2form'), $log['form_id'])); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="no-form"><?php esc_html_e('N/A', 'connect2form'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-status">
                                <?php echo wp_kses_post($this->status_badge($log['status'])); ?>
                            </td>
                            <td class="column-message">
                                <div class="log-message">
                                    <?php echo esc_html(wp_trim_words($log['message'], 15)); ?>
                                    <?php if (strlen($log['message']) > 100): ?>
                                        <button type="button" class="button-link toggle-full-message" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                            <?php esc_html_e('View Full', 'connect2form'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($log['data'])): ?>
                                    <button type="button" class="button-link toggle-log-data" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                        <?php esc_html_e('View Data', 'connect2form'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button-link delete-log" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <?php esc_html_e('Delete', 'connect2form'); ?>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Hidden full message row -->
                        <tr class="log-details" id="log-message-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                            <td colspan="6">
                                <div class="log-full-message">
                                    <strong><?php esc_html_e('Full Message:', 'connect2form'); ?></strong>
                                    <pre><?php echo esc_html($log['message']); ?></pre>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Hidden data row -->
                        <?php if (!empty($log['data'])): ?>
                            <tr class="log-details" id="log-data-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                                <td colspan="6">
                                    <div class="log-data">
                                        <strong><?php esc_html_e('Additional Data:', 'connect2form'); ?></strong>
                                        <pre><?php echo esc_html(wp_json_encode(json_decode($log['data']), JSON_PRETTY_PRINT)); ?></pre>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if (count($logs) >= $per_page): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $big = 999999999;
                    echo wp_kses_post(paginate_links(array(
                        'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                        'format' => '?paged=%#%',
                        'current' => $current_page,
                        'total' => ceil(count($logs) / $per_page)
                    )));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.connect2form-logs-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.log-filter-form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.log-filter-form select,
.log-filter-form input[type="date"] {
    min-width: 120px;
}

.log-actions {
    display: flex;
    gap: 10px;
}

.connect2form-logs-table-wrap {
    margin-top: 20px;
}

.column-date { width: 15%; }
.column-integration { width: 15%; }
.column-form { width: 10%; }
.column-status { width: 10%; }
.column-message { width: 40%; }
.column-actions { width: 10%; }

.integration-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.integration-info .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.integration-info img {
    max-width: 16px;
    max-height: 16px;
}

.log-entry.log-success {
    border-left: 3px solid #00a32a;
}

.log-entry.log-error {
    border-left: 3px solid #d63638;
}

.log-entry.log-warning {
    border-left: 3px solid #dba617;
}

.log-message {
    line-height: 1.4;
}

.toggle-full-message,
.toggle-log-data {
    font-size: 12px;
    color: #0073aa;
}

.log-details td {
    background: #f9f9f9;
    border-top: 1px solid #ddd;
}

.log-full-message pre,
.log-data pre {
    background: #fff;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 3px;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 300px;
    overflow-y: auto;
    font-size: 12px;
    margin: 10px 0 0 0;
}

.delete-log {
    color: #d63638;
}

.no-form {
    color: #666;
    font-style: italic;
}

@media (max-width: 768px) {
    .connect2form-logs-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .log-filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .log-filter-form select,
    .log-filter-form input {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle full message
    $('.toggle-full-message').on('click', function() {
        var logId = $(this).data('log-id');
        var messageRow = $('#log-message-' + logId);
        
        if (messageRow.is(':visible')) {
            messageRow.hide();
            $(this).text('<?php esc_html_e('View Full', 'connect2form'); ?>');
        } else {
            messageRow.show();
            $(this).text('<?php esc_html_e('Hide', 'connect2form'); ?>');
        }
    });
    
    // Toggle log data
    $('.toggle-log-data').on('click', function() {
        var logId = $(this).data('log-id');
        var dataRow = $('#log-data-' + logId);
        
        if (dataRow.is(':visible')) {
            dataRow.hide();
            $(this).text('<?php esc_html_e('View Data', 'connect2form'); ?>');
        } else {
            dataRow.show();
            $(this).text('<?php esc_html_e('Hide Data', 'connect2form'); ?>');
        }
    });
    
    // Delete individual log
    $('.delete-log').on('click', function() {
        if (!confirm('<?php esc_html_e('Are you sure you want to delete this log entry?', 'connect2form'); ?>')) {
            return;
        }
        
        var logId = $(this).data('log-id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'connect2form_delete_log',
                log_id: logId,
                nonce: '<?php echo esc_js(wp_create_nonce('connect2form_delete_log')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to delete log entry', 'connect2form'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to delete log entry', 'connect2form'); ?>');
            }
        });
    });
    
    // Clear all logs
    $('#clear-all-logs').on('click', function() {
        if (!confirm('<?php esc_html_e('Are you sure you want to delete all log entries? This action cannot be undone.', 'connect2form'); ?>')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('<?php esc_html_e('Clearing...', 'connect2form'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'connect2form_clear_all_logs',
                nonce: '<?php echo esc_js(wp_create_nonce('connect2form_clear_logs')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to clear logs', 'connect2form'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to clear logs', 'connect2form'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Clear All Logs', 'connect2form'); ?>');
            }
        });
    });
    
    // Export logs
    $('#export-logs').on('click', function() {
        var params = new URLSearchParams(window.location.search);
        params.set('action', 'connect2form_export_logs');
        params.set('nonce', '<?php echo esc_js(wp_create_nonce('connect2form_export_logs')); ?>');
        
        window.location.href = ajaxurl + '?' + params.toString();
    });
});
</script> 
