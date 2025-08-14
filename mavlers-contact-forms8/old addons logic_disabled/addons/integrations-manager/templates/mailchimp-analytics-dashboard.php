<?php
/**
 * Mailchimp Analytics Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Initialize analytics if not already done
if (!class_exists('Mavlers_CF_Mailchimp_Analytics')) {
    require_once dirname(__FILE__) . '/../includes/class-mailchimp-analytics.php';
}

$analytics = new Mavlers_CF_Mailchimp_Analytics();
$dashboard_data = $analytics->get_dashboard_data('7days');
?>

<div class="mailchimp-analytics-dashboard">
    <div class="dashboard-header">
        <h2><?php _e('📊 Mailchimp Analytics Dashboard', 'mavlers-cf'); ?></h2>
        <div class="period-selector">
            <label for="analytics-period"><?php _e('Time Period:', 'mavlers-cf'); ?></label>
            <select id="analytics-period" class="analytics-period-select">
                <option value="24hours"><?php _e('Last 24 Hours', 'mavlers-cf'); ?></option>
                <option value="7days" selected><?php _e('Last 7 Days', 'mavlers-cf'); ?></option>
                <option value="30days"><?php _e('Last 30 Days', 'mavlers-cf'); ?></option>
                <option value="90days"><?php _e('Last 90 Days', 'mavlers-cf'); ?></option>
            </select>
            <button type="button" id="refresh-analytics" class="button"><?php _e('🔄 Refresh', 'mavlers-cf'); ?></button>
            <button type="button" id="export-analytics" class="button button-secondary"><?php _e('📄 Export', 'mavlers-cf'); ?></button>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="analytics-overview">
        <div class="overview-card">
            <div class="card-icon">📥</div>
            <div class="card-content">
                <div class="card-value" id="total-subscriptions"><?php echo esc_html($dashboard_data['overview']['total_subscriptions']); ?></div>
                <div class="card-label"><?php _e('Total Subscriptions', 'mavlers-cf'); ?></div>
                <div class="card-change" id="subscriptions-change">
                    <span class="change-indicator">↗️</span>
                    <span class="change-value">+12%</span>
                </div>
            </div>
        </div>

        <div class="overview-card">
            <div class="card-icon">✅</div>
            <div class="card-content">
                <div class="card-value" id="success-rate"><?php echo esc_html($dashboard_data['overview']['success_rate']); ?>%</div>
                <div class="card-label"><?php _e('Success Rate', 'mavlers-cf'); ?></div>
                <div class="card-change" id="success-rate-change">
                    <span class="change-indicator">↗️</span>
                    <span class="change-value">+2.1%</span>
                </div>
            </div>
        </div>

        <div class="overview-card">
            <div class="card-icon">⚡</div>
            <div class="card-content">
                <div class="card-value" id="avg-response-time"><?php echo esc_html($dashboard_data['overview']['avg_response_time']); ?>ms</div>
                <div class="card-label"><?php _e('Avg Response Time', 'mavlers-cf'); ?></div>
                <div class="card-change" id="response-time-change">
                    <span class="change-indicator">↘️</span>
                    <span class="change-value">-15ms</span>
                </div>
            </div>
        </div>

        <div class="overview-card">
            <div class="card-icon">🔄</div>
            <div class="card-content">
                <div class="card-value" id="webhook-activity"><?php echo esc_html($dashboard_data['overview']['webhook_activity']); ?></div>
                <div class="card-label"><?php _e('Webhook Activity', 'mavlers-cf'); ?></div>
                <div class="card-change" id="webhook-change">
                    <span class="change-indicator">↗️</span>
                    <span class="change-value">+8</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="analytics-charts">
        <div class="chart-container">
            <h3><?php _e('📈 Subscription Trends', 'mavlers-cf'); ?></h3>
            <div class="chart-wrapper">
                <canvas id="subscription-trends-chart" width="400" height="200"></canvas>
            </div>
            <div class="chart-legend">
                <div class="legend-item">
                    <span class="legend-color success"></span>
                    <?php _e('Successful Subscriptions', 'mavlers-cf'); ?>
                </div>
                <div class="legend-item">
                    <span class="legend-color failed"></span>
                    <?php _e('Failed Subscriptions', 'mavlers-cf'); ?>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <h3><?php _e('🎯 Performance by Form', 'mavlers-cf'); ?></h3>
            <div class="chart-wrapper">
                <canvas id="forms-performance-chart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Data Tables -->
    <div class="analytics-tables">
        <div class="table-container">
            <h3><?php _e('📋 Top Performing Forms', 'mavlers-cf'); ?></h3>
            <div class="table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Form Name', 'mavlers-cf'); ?></th>
                            <th><?php _e('Submissions', 'mavlers-cf'); ?></th>
                            <th><?php _e('Success Rate', 'mavlers-cf'); ?></th>
                            <th><?php _e('Avg Response', 'mavlers-cf'); ?></th>
                            <th><?php _e('Status', 'mavlers-cf'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="forms-performance-table">
                        <?php if (!empty($dashboard_data['forms'])): ?>
                            <?php foreach ($dashboard_data['forms'] as $form): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($form['form_name'] ?: 'Form #' . $form['form_id']); ?></strong>
                                        <br>
                                        <small class="form-id"><?php echo sprintf(__('ID: %d', 'mavlers-cf'), $form['form_id']); ?></small>
                                    </td>
                                    <td>
                                        <span class="submissions-count"><?php echo esc_html($form['total_submissions']); ?></span>
                                        <div class="submissions-breakdown">
                                            <small>
                                                <?php echo sprintf(
                                                    __('%d success, %d failed', 'mavlers-cf'),
                                                    $form['successful_subscriptions'],
                                                    $form['failed_subscriptions']
                                                ); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="success-rate-cell">
                                            <span class="rate-value"><?php echo esc_html($form['success_rate']); ?>%</span>
                                            <div class="rate-bar">
                                                <div class="rate-fill" style="width: <?php echo esc_attr($form['success_rate']); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="response-time"><?php echo esc_html($form['avg_response_time']); ?>ms</span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = $form['success_rate'] >= 95 ? 'excellent' : ($form['success_rate'] >= 80 ? 'good' : 'needs-attention');
                                        $status_text = $form['success_rate'] >= 95 ? __('Excellent', 'mavlers-cf') : ($form['success_rate'] >= 80 ? __('Good', 'mavlers-cf') : __('Needs Attention', 'mavlers-cf'));
                                        ?>
                                        <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="no-data">
                                    <?php _e('No form data available for the selected period.', 'mavlers-cf'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-container">
            <h3><?php _e('📊 Audience Performance', 'mavlers-cf'); ?></h3>
            <div class="table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Audience ID', 'mavlers-cf'); ?></th>
                            <th><?php _e('Form Subscriptions', 'mavlers-cf'); ?></th>
                            <th><?php _e('Webhook Events', 'mavlers-cf'); ?></th>
                            <th><?php _e('Total Activity', 'mavlers-cf'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="audiences-performance-table">
                        <?php if (!empty($dashboard_data['audiences'])): ?>
                            <?php foreach ($dashboard_data['audiences'] as $audience): ?>
                                <tr>
                                    <td>
                                        <code><?php echo esc_html(substr($audience['audience_id'], 0, 10) . '...'); ?></code>
                                    </td>
                                    <td>
                                        <span class="subscription-count"><?php echo esc_html($audience['form_subscriptions']); ?></span>
                                    </td>
                                    <td>
                                        <div class="webhook-events">
                                            <span class="webhook-sub">+<?php echo esc_html($audience['webhook_subscriptions']); ?></span>
                                            <span class="webhook-unsub">-<?php echo esc_html($audience['webhook_unsubscriptions']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($audience['total_events']); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="no-data">
                                    <?php _e('No audience data available for the selected period.', 'mavlers-cf'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Error Analysis -->
    <?php if (!empty($dashboard_data['errors'])): ?>
        <div class="analytics-errors">
            <h3><?php _e('🚨 Error Analysis', 'mavlers-cf'); ?></h3>
            <div class="error-list">
                <?php foreach ($dashboard_data['errors'] as $error): ?>
                    <div class="error-item">
                        <div class="error-message">
                            <code><?php echo esc_html($error['error_message']); ?></code>
                        </div>
                        <div class="error-meta">
                            <span class="error-count"><?php echo sprintf(__('Occurred %d times', 'mavlers-cf'), $error['error_count']); ?></span>
                            <span class="error-last"><?php echo sprintf(__('Last: %s', 'mavlers-cf'), date('M j, H:i', strtotime($error['last_occurrence']))); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div id="analytics-loading" class="analytics-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <div class="loading-text"><?php _e('Loading analytics data...', 'mavlers-cf'); ?></div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    let chartsInitialized = false;
    let trendsChart = null;
    let formsChart = null;

    // Initialize dashboard
    initializeAnalyticsDashboard();

    function initializeAnalyticsDashboard() {
        // Period selector change
        $('#analytics-period').on('change', function() {
            refreshAnalytics();
        });

        // Refresh button
        $('#refresh-analytics').on('click', function() {
            refreshAnalytics();
        });

        // Export button
        $('#export-analytics').on('click', function() {
            exportAnalytics();
        });

        // Initialize charts
        initializeCharts();
    }

    function refreshAnalytics() {
        const period = $('#analytics-period').val();
        showLoading();

        $.post(ajaxurl, {
            action: 'mailchimp_get_analytics_dashboard',
            period: period,
            nonce: '<?php echo wp_create_nonce('mavlers_cf_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                updateDashboard(response.data);
            } else {
                showError('Failed to load analytics data.');
            }
        })
        .fail(function() {
            showError('Network error occurred.');
        })
        .always(function() {
            hideLoading();
        });
    }

    function updateDashboard(data) {
        // Update overview cards
        $('#total-subscriptions').text(data.overview.total_subscriptions);
        $('#success-rate').text(data.overview.success_rate + '%');
        $('#avg-response-time').text(data.overview.avg_response_time + 'ms');
        $('#webhook-activity').text(data.overview.webhook_activity);

        // Update charts
        updateCharts(data);

        // Update tables
        updateFormsTable(data.forms);
        updateAudiencesTable(data.audiences);
    }

    function initializeCharts() {
        if (typeof Chart === 'undefined') {
            // Load Chart.js if not available
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = function() {
                createCharts();
            };
            document.head.appendChild(script);
        } else {
            createCharts();
        }
    }

    function createCharts() {
        // Subscription trends chart
        const trendsCtx = document.getElementById('subscription-trends-chart').getContext('2d');
        trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($dashboard_data['trends'], 'date_label')); ?>,
                datasets: [
                    {
                        label: 'Successful',
                        data: <?php echo json_encode(array_column($dashboard_data['trends'], 'subscriptions')); ?>,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Failed',
                        data: <?php echo json_encode(array_column($dashboard_data['trends'], 'failures')); ?>,
                        borderColor: '#f44336',
                        backgroundColor: 'rgba(244, 67, 54, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        chartsInitialized = true;
    }

    function updateCharts(data) {
        if (!chartsInitialized) return;

        // Update trends chart
        if (trendsChart) {
            trendsChart.data.labels = data.trends.map(t => t.date_label);
            trendsChart.data.datasets[0].data = data.trends.map(t => t.subscriptions);
            trendsChart.data.datasets[1].data = data.trends.map(t => t.failures);
            trendsChart.update();
        }
    }

    function updateFormsTable(forms) {
        const tbody = $('#forms-performance-table');
        tbody.empty();

        if (forms.length === 0) {
            tbody.append('<tr><td colspan="5" class="no-data">No form data available for the selected period.</td></tr>');
            return;
        }

        forms.forEach(function(form) {
            const statusClass = form.success_rate >= 95 ? 'excellent' : (form.success_rate >= 80 ? 'good' : 'needs-attention');
            const statusText = form.success_rate >= 95 ? 'Excellent' : (form.success_rate >= 80 ? 'Good' : 'Needs Attention');

            const row = `
                <tr>
                    <td>
                        <strong>${form.form_name || 'Form #' + form.form_id}</strong><br>
                        <small class="form-id">ID: ${form.form_id}</small>
                    </td>
                    <td>
                        <span class="submissions-count">${form.total_submissions}</span>
                        <div class="submissions-breakdown">
                            <small>${form.successful_subscriptions} success, ${form.failed_subscriptions} failed</small>
                        </div>
                    </td>
                    <td>
                        <div class="success-rate-cell">
                            <span class="rate-value">${form.success_rate}%</span>
                            <div class="rate-bar">
                                <div class="rate-fill" style="width: ${form.success_rate}%"></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="response-time">${form.avg_response_time}ms</span></td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function updateAudiencesTable(audiences) {
        const tbody = $('#audiences-performance-table');
        tbody.empty();

        if (audiences.length === 0) {
            tbody.append('<tr><td colspan="4" class="no-data">No audience data available for the selected period.</td></tr>');
            return;
        }

        audiences.forEach(function(audience) {
            const row = `
                <tr>
                    <td><code>${audience.audience_id.substring(0, 10)}...</code></td>
                    <td><span class="subscription-count">${audience.form_subscriptions}</span></td>
                    <td>
                        <div class="webhook-events">
                            <span class="webhook-sub">+${audience.webhook_subscriptions}</span>
                            <span class="webhook-unsub">-${audience.webhook_unsubscriptions}</span>
                        </div>
                    </td>
                    <td><strong>${audience.total_events}</strong></td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function exportAnalytics() {
        const period = $('#analytics-period').val();
        window.open(`${ajaxurl}?action=mailchimp_export_analytics&period=${period}&nonce=<?php echo wp_create_nonce('mavlers_cf_nonce'); ?>`);
    }

    function showLoading() {
        $('#analytics-loading').show();
    }

    function hideLoading() {
        $('#analytics-loading').hide();
    }

    function showError(message) {
        // Show error notification
        if (typeof wp !== 'undefined' && wp.data) {
            wp.data.dispatch('core/notices').createNotice('error', message);
        } else {
            alert('Error: ' + message);
        }
    }
});
</script> 