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
$current_page = max(1, intval($_GET['paged'] ?? 1));
?>

<div class="wrap">
    <h1><?php esc_html_e('Integration Logs', 'mavlers-contact-forms'); ?></h1>
    
    <div class="mavlers-logs-header">
        <div class="log-filters">
            <form method="get" class="log-filter-form">
                <input type="hidden" name="page" value="mavlers-cf-integrations" />
                <input type="hidden" name="tab" value="logs" />
                
                <select name="integration" id="log-integration-filter">
                    <option value=""><?php esc_html_e('All Integrations', 'mavlers-contact-forms'); ?></option>
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
                    <option value=""><?php esc_html_e('All Status', 'mavlers-contact-forms'); ?></option>
                    <option value="success" <?php selected($_GET['status'] ?? '', 'success'); ?>><?php esc_html_e('Success', 'mavlers-contact-forms'); ?></option>
                    <option value="error" <?php selected($_GET['status'] ?? '', 'error'); ?>><?php esc_html_e('Error', 'mavlers-contact-forms'); ?></option>
                    <option value="warning" <?php selected($_GET['status'] ?? '', 'warning'); ?>><?php esc_html_e('Warning', 'mavlers-contact-forms'); ?></option>
                </select>
                
                <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" />
                <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" />
                
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'mavlers-contact-forms'); ?>" />
                <a href="<?php echo esc_url(admin_url('admin.php?page=mavlers-cf-integrations&tab=logs')); ?>" 
                   class="button">
                    <?php esc_html_e('Clear', 'mavlers-contact-forms'); ?>
                </a>
            </form>
        </div>
        
        <div class="log-actions">
            <button type="button" id="clear-all-logs" class="button button-secondary">
                <?php esc_html_e('Clear All Logs', 'mavlers-contact-forms'); ?>
            </button>
            <button type="button" id="export-logs" class="button">
                <?php esc_html_e('Export Logs', 'mavlers-contact-forms'); ?>
            </button>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('No logs found. Integration activity will appear here once forms are submitted.', 'mavlers-contact-forms'); ?></p>
        </div>
    <?php else: ?>
        <div class="mavlers-logs-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-date"><?php esc_html_e('Date', 'mavlers-contact-forms'); ?></th>
                        <th scope="col" class="column-integration"><?php esc_html_e('Integration', 'mavlers-contact-forms'); ?></th>
                        <th scope="col" class="column-form"><?php esc_html_e('Form', 'mavlers-contact-forms'); ?></th>
                        <th scope="col" class="column-status"><?php esc_html_e('Status', 'mavlers-contact-forms'); ?></th>
                        <th scope="col" class="column-message"><?php esc_html_e('Message', 'mavlers-contact-forms'); ?></th>
                        <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'mavlers-contact-forms'); ?></th>
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
                                        <?php echo $this->integration_icon($integration); ?>
                                        <span><?php echo esc_html($integration->getName()); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span><?php echo esc_html($log['integration_id']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-form">
                                <?php if (!empty($log['form_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $log['form_id'] . '&action=edit')); ?>">
                                        <?php echo esc_html(sprintf(__('Form #%d', 'mavlers-contact-forms'), $log['form_id'])); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="no-form"><?php esc_html_e('N/A', 'mavlers-contact-forms'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-status">
                                <?php echo $this->status_badge($log['status']); ?>
                            </td>
                            <td class="column-message">
                                <div class="log-message">
                                    <?php echo esc_html(wp_trim_words($log['message'], 15)); ?>
                                    <?php if (strlen($log['message']) > 100): ?>
                                        <button type="button" class="button-link toggle-full-message" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                            <?php esc_html_e('View Full', 'mavlers-contact-forms'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($log['data'])): ?>
                                    <button type="button" class="button-link toggle-log-data" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                        <?php esc_html_e('View Data', 'mavlers-contact-forms'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button-link delete-log" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <?php esc_html_e('Delete', 'mavlers-contact-forms'); ?>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Hidden full message row -->
                        <tr class="log-details" id="log-message-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                            <td colspan="6">
                                <div class="log-full-message">
                                    <strong><?php esc_html_e('Full Message:', 'mavlers-contact-forms'); ?></strong>
                                    <pre><?php echo esc_html($log['message']); ?></pre>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Hidden data row -->
                        <?php if (!empty($log['data'])): ?>
                            <tr class="log-details" id="log-data-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                                <td colspan="6">
                                    <div class="log-data">
                                        <strong><?php esc_html_e('Additional Data:', 'mavlers-contact-forms'); ?></strong>
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
                    echo paginate_links(array(
                        'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                        'format' => '?paged=%#%',
                        'current' => $current_page,
                        'total' => ceil(count($logs) / $per_page)
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.mavlers-logs-header {
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

.mavlers-logs-table-wrap {
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
    .mavlers-logs-header {
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
            $(this).text('<?php esc_html_e('View Full', 'mavlers-contact-forms'); ?>');
        } else {
            messageRow.show();
            $(this).text('<?php esc_html_e('Hide', 'mavlers-contact-forms'); ?>');
        }
    });
    
    // Toggle log data
    $('.toggle-log-data').on('click', function() {
        var logId = $(this).data('log-id');
        var dataRow = $('#log-data-' + logId);
        
        if (dataRow.is(':visible')) {
            dataRow.hide();
            $(this).text('<?php esc_html_e('View Data', 'mavlers-contact-forms'); ?>');
        } else {
            dataRow.show();
            $(this).text('<?php esc_html_e('Hide Data', 'mavlers-contact-forms'); ?>');
        }
    });
    
    // Delete individual log
    $('.delete-log').on('click', function() {
        if (!confirm('<?php esc_html_e('Are you sure you want to delete this log entry?', 'mavlers-contact-forms'); ?>')) {
            return;
        }
        
        var logId = $(this).data('log-id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_delete_log',
                log_id: logId,
                nonce: '<?php echo wp_create_nonce('mavlers_cf_delete_log'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to delete log entry', 'mavlers-contact-forms'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to delete log entry', 'mavlers-contact-forms'); ?>');
            }
        });
    });
    
    // Clear all logs
    $('#clear-all-logs').on('click', function() {
        if (!confirm('<?php esc_html_e('Are you sure you want to delete all log entries? This action cannot be undone.', 'mavlers-contact-forms'); ?>')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('<?php esc_html_e('Clearing...', 'mavlers-contact-forms'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_cf_clear_all_logs',
                nonce: '<?php echo wp_create_nonce('mavlers_cf_clear_logs'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to clear logs', 'mavlers-contact-forms'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Failed to clear logs', 'mavlers-contact-forms'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_html_e('Clear All Logs', 'mavlers-contact-forms'); ?>');
            }
        });
    });
    
    // Export logs
    $('#export-logs').on('click', function() {
        var params = new URLSearchParams(window.location.search);
        params.set('action', 'mavlers_cf_export_logs');
        params.set('nonce', '<?php echo wp_create_nonce('mavlers_cf_export_logs'); ?>');
        
        window.location.href = ajaxurl + '?' + params.toString();
    });
});
</script> 