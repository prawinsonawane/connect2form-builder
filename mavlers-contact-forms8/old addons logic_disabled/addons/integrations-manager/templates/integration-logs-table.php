<?php
/**
 * Integration Activity Logs Table Template
 * 
 * Template for displaying detailed integration logs in the admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $logs, $total_count, $current_page, $per_page, $filters
?>

<div class="integration-logs-wrapper">
    <!-- Log Filters -->
    <div class="logs-filters">
        <div class="filter-row">
            <div class="filter-item">
                <label for="status-filter"><?php _e('Status:', 'mavlers-contact-forms'); ?></label>
                <select id="status-filter" name="status">
                    <option value=""><?php _e('All Statuses', 'mavlers-contact-forms'); ?></option>
                    <option value="success" <?php selected($filters['status'] ?? '', 'success'); ?>><?php _e('Success', 'mavlers-contact-forms'); ?></option>
                    <option value="error" <?php selected($filters['status'] ?? '', 'error'); ?>><?php _e('Error', 'mavlers-contact-forms'); ?></option>
                    <option value="pending" <?php selected($filters['status'] ?? '', 'pending'); ?>><?php _e('Pending', 'mavlers-contact-forms'); ?></option>
                </select>
            </div>

            <div class="filter-item">
                <label for="integration-filter"><?php _e('Integration:', 'mavlers-contact-forms'); ?></label>
                <select id="integration-filter" name="integration_id">
                    <option value=""><?php _e('All Integrations', 'mavlers-contact-forms'); ?></option>
                    <?php
                    // Get available integrations for filter
                    global $wpdb;
                    $integrations = $wpdb->get_results(
                        "SELECT DISTINCT integration_id, integration_name 
                         FROM {$wpdb->prefix}mavlers_cf_integrations 
                         ORDER BY integration_name"
                    );
                    foreach ($integrations as $integration): ?>
                        <option value="<?php echo esc_attr($integration->integration_id); ?>" 
                                <?php selected($filters['integration_id'] ?? '', $integration->integration_id); ?>>
                            <?php echo esc_html($integration->integration_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label for="form-filter"><?php _e('Form:', 'mavlers-contact-forms'); ?></label>
                <select id="form-filter" name="form_id">
                    <option value=""><?php _e('All Forms', 'mavlers-contact-forms'); ?></option>
                    <?php
                    // Get forms that have integration logs
                    $forms = $wpdb->get_results(
                        "SELECT DISTINCT l.form_id, f.form_title as name 
                         FROM {$wpdb->prefix}mavlers_cf_integration_logs l
                         LEFT JOIN {$wpdb->prefix}mavlers_cf_forms f ON l.form_id = f.id
                         WHERE f.form_title IS NOT NULL
                         ORDER BY f.form_title"
                    );
                    foreach ($forms as $form): ?>
                        <option value="<?php echo esc_attr($form->form_id); ?>"
                                <?php selected($filters['form_id'] ?? '', $form->form_id); ?>>
                            <?php echo esc_html($form->name . ' (#' . $form->form_id . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label for="date-range"><?php _e('Date Range:', 'mavlers-contact-forms'); ?></label>
                <select id="date-range" name="date_range">
                    <option value="" <?php selected($filters['date_range'] ?? '', ''); ?>><?php _e('All Time', 'mavlers-contact-forms'); ?></option>
                    <option value="today" <?php selected($filters['date_range'] ?? '', 'today'); ?>><?php _e('Today', 'mavlers-contact-forms'); ?></option>
                    <option value="yesterday" <?php selected($filters['date_range'] ?? '', 'yesterday'); ?>><?php _e('Yesterday', 'mavlers-contact-forms'); ?></option>
                    <option value="week" <?php selected($filters['date_range'] ?? '', 'week'); ?>><?php _e('This Week', 'mavlers-contact-forms'); ?></option>
                    <option value="month" <?php selected($filters['date_range'] ?? '', 'month'); ?>><?php _e('This Month', 'mavlers-contact-forms'); ?></option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="button" class="button apply-filters">
                    <span class="dashicons dashicons-filter"></span>
                    <?php _e('Apply Filters', 'mavlers-contact-forms'); ?>
                </button>
                <button type="button" class="button clear-filters">
                    <?php _e('Clear', 'mavlers-contact-forms'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Logs Stats Summary -->
    <?php if (!empty($logs)): ?>
        <div class="logs-stats">
            <?php
            $stats = array(
                'total' => count($logs),
                'success' => 0,
                'error' => 0,
                'pending' => 0
            );
            foreach ($logs as $log) {
                $stats[$log->status]++;
            }
            ?>
            <div class="stat-item success">
                <span class="stat-number"><?php echo $stats['success']; ?></span>
                <span class="stat-label"><?php _e('Successful', 'mavlers-contact-forms'); ?></span>
            </div>
            <div class="stat-item error">
                <span class="stat-number"><?php echo $stats['error']; ?></span>
                <span class="stat-label"><?php _e('Failed', 'mavlers-contact-forms'); ?></span>
            </div>
            <div class="stat-item pending">
                <span class="stat-number"><?php echo $stats['pending']; ?></span>
                <span class="stat-label"><?php _e('Pending', 'mavlers-contact-forms'); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Logs Table -->
    <?php if (empty($logs)): ?>
        <div class="no-logs-message">
            <div class="no-logs-content">
                <span class="dashicons dashicons-admin-post"></span>
                <h3><?php _e('No Integration Logs Found', 'mavlers-contact-forms'); ?></h3>
                <p><?php _e('There are no integration activity logs matching your criteria. Try adjusting your filters or check back after some form submissions.', 'mavlers-contact-forms'); ?></p>
            </div>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped integration-logs-table">
            <thead>
                <tr>
                    <th scope="col" class="column-status"><?php _e('Status', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-timestamp"><?php _e('Date & Time', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-integration"><?php _e('Integration', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-form"><?php _e('Form', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-action"><?php _e('Action', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-execution-time"><?php _e('Time', 'mavlers-contact-forms'); ?></th>
                    <th scope="col" class="column-details"><?php _e('Details', 'mavlers-contact-forms'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr class="log-entry status-<?php echo esc_attr($log->status); ?>">
                        <td class="column-status">
                            <span class="status-indicator status-<?php echo esc_attr($log->status); ?>">
                                <?php if ($log->status === 'success'): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Success', 'mavlers-contact-forms'); ?>
                                <?php elseif ($log->status === 'error'): ?>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Error', 'mavlers-contact-forms'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php _e('Pending', 'mavlers-contact-forms'); ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        
                        <td class="column-timestamp">
                            <div class="timestamp-info">
                                <time datetime="<?php echo esc_attr($log->created_at); ?>">
                                    <?php echo wp_date('M j, Y', strtotime($log->created_at)); ?>
                                </time>
                                <small><?php echo wp_date('g:i A', strtotime($log->created_at)); ?></small>
                            </div>
                        </td>
                        
                        <td class="column-integration">
                            <div class="integration-info">
                                <strong><?php echo esc_html(ucfirst($log->integration_id)); ?></strong>
                            </div>
                        </td>
                        
                        <td class="column-form">
                            <?php if ($log->form_id): ?>
                                <a href="<?php echo admin_url('admin.php?page=mavlers-cf-forms&action=edit&form_id=' . $log->form_id); ?>" 
                                   class="form-link">
                                    <?php 
                                    global $wpdb;
                                    $form_name = $wpdb->get_var($wpdb->prepare(
                                        "SELECT form_title FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d", 
                                        $log->form_id
                                    ));
                                    echo esc_html($form_name ?: 'Form #' . $log->form_id);
                                    ?>
                                </a>
                            <?php else: ?>
                                <span class="no-form"><?php _e('—', 'mavlers-contact-forms'); ?></span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="column-action">
                            <span class="action-type"><?php echo esc_html(ucfirst($log->action)); ?></span>
                        </td>
                        
                        <td class="column-execution-time">
                            <?php if ($log->execution_time): ?>
                                <span class="execution-time">
                                    <?php echo number_format($log->execution_time, 3); ?>s
                                </span>
                            <?php else: ?>
                                <span class="no-time"><?php _e('—', 'mavlers-contact-forms'); ?></span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="column-details">
                            <button type="button" class="button button-small view-log-details" 
                                    data-log-id="<?php echo esc_attr($log->id); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('View', 'mavlers-contact-forms'); ?>
                            </button>
                        </td>
                    </tr>
                    
                    <!-- Expandable details row -->
                    <tr class="log-details-row" id="log-details-<?php echo esc_attr($log->id); ?>" style="display: none;">
                        <td colspan="7">
                            <div class="log-details-content">
                                <div class="details-section">
                                    <h4><?php _e('Error Message', 'mavlers-contact-forms'); ?></h4>
                                    <?php if ($log->error_message): ?>
                                        <div class="error-message">
                                            <pre><?php echo esc_html($log->error_message); ?></pre>
                                        </div>
                                    <?php else: ?>
                                        <p class="no-error"><?php _e('No error message', 'mavlers-contact-forms'); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="details-section">
                                    <h4><?php _e('Request Data', 'mavlers-contact-forms'); ?></h4>
                                    <?php if ($log->request_data): ?>
                                        <div class="request-data">
                                            <pre><?php echo esc_html(wp_json_encode(json_decode($log->request_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                        </div>
                                    <?php else: ?>
                                        <p class="no-data"><?php _e('No request data', 'mavlers-contact-forms'); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($log->response_data): ?>
                                    <div class="details-section">
                                        <h4><?php _e('Response Data', 'mavlers-contact-forms'); ?></h4>
                                        <div class="response-data">
                                            <pre><?php echo esc_html(wp_json_encode(json_decode($log->response_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="details-actions">
                                    <?php if ($log->status === 'error' && $log->form_id): ?>
                                        <button type="button" class="button button-secondary retry-integration" 
                                                data-log-id="<?php echo esc_attr($log->id); ?>"
                                                data-form-id="<?php echo esc_attr($log->form_id); ?>"
                                                data-integration-id="<?php echo esc_attr($log->integration_id); ?>">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php _e('Retry Integration', 'mavlers-contact-forms'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="button button-secondary copy-log-data" 
                                            data-log-id="<?php echo esc_attr($log->id); ?>">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        <?php _e('Copy Log Data', 'mavlers-contact-forms'); ?>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_count > $per_page): ?>
            <div class="logs-pagination">
                <?php
                $total_pages = ceil($total_count / $per_page);
                $base_url = add_query_arg(array_filter($filters));
                echo paginate_links(array(
                    'base' => add_query_arg('log_page', '%#%', $base_url),
                    'format' => '',
                    'prev_text' => __('&laquo; Previous', 'mavlers-contact-forms'),
                    'next_text' => __('Next &raquo;', 'mavlers-contact-forms'),
                    'total' => $total_pages,
                    'current' => $current_page
                ));
                ?>
                
                <div class="pagination-info">
                    <?php
                    $start = (($current_page - 1) * $per_page) + 1;
                    $end = min($current_page * $per_page, $total_count);
                    printf(
                        __('Showing %d-%d of %d logs', 'mavlers-contact-forms'),
                        $start,
                        $end,
                        $total_count
                    );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.integration-logs-wrapper {
    margin-top: 20px;
}

.logs-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-item label {
    font-weight: 600;
    font-size: 12px;
    color: #646970;
}

.filter-item select {
    min-width: 140px;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.logs-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
}

.stat-item {
    text-align: center;
    padding: 10px 15px;
    border-radius: 4px;
}

.stat-item.success {
    background: #d1e7dd;
    color: #0f5132;
}

.stat-item.error {
    background: #f8d7da;
    color: #721c24;
}

.stat-item.pending {
    background: #fff3cd;
    color: #856404;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 12px;
    text-transform: uppercase;
    font-weight: 600;
    margin-top: 5px;
}

.integration-logs-table .column-status { width: 10%; }
.integration-logs-table .column-timestamp { width: 15%; }
.integration-logs-table .column-integration { width: 15%; }
.integration-logs-table .column-form { width: 15%; }
.integration-logs-table .column-action { width: 10%; }
.integration-logs-table .column-execution-time { width: 10%; }
.integration-logs-table .column-details { width: 10%; }

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-indicator.status-success {
    background: #d1e7dd;
    color: #0f5132;
}

.status-indicator.status-error {
    background: #f8d7da;
    color: #721c24;
}

.status-indicator.status-pending {
    background: #fff3cd;
    color: #856404;
}

.log-entry.status-success {
    background-color: #f8fff9;
}

.log-entry.status-error {
    background-color: #fff5f5;
}

.timestamp-info small {
    display: block;
    color: #646970;
}

.execution-time {
    font-family: monospace;
    font-size: 13px;
    color: #0073aa;
}

.log-details-content {
    padding: 20px;
    background: #f8f9fa;
    border-left: 4px solid #0073aa;
}

.details-section {
    margin-bottom: 20px;
}

.details-section h4 {
    margin: 0 0 10px 0;
    color: #1d2327;
    font-size: 14px;
}

.details-section pre {
    background: #fff;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    max-height: 200px;
    font-size: 12px;
    line-height: 1.4;
}

.error-message pre {
    background: #fff5f5;
    border-color: #f8d7da;
    color: #721c24;
}

.details-actions {
    padding-top: 15px;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
}

.no-logs-message {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
}

.no-logs-content .dashicons {
    font-size: 48px;
    color: #c3c4c7;
    margin-bottom: 15px;
}

.no-logs-content h3 {
    margin: 0 0 10px 0;
    color: #646970;
}

.logs-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
}

.pagination-info {
    color: #646970;
    font-size: 13px;
}
</style> 