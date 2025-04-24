jQuery(document).ready(function($) {
    // Initialize analytics
    initFormAnalytics();

    function initFormAnalytics() {
        const $analyticsContainer = $('.mavlers-analytics-container');
        if (!$analyticsContainer.length) return;

        // Initialize date range picker
        initDateRangePicker();

        // Load initial analytics data
        loadAnalyticsData();

        // Handle tab switching
        $('.mavlers-analytics-tabs a').on('click', function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            switchTab(tab);
        });

        // Handle period change
        $('.mavlers-period-select').on('change', function() {
            loadAnalyticsData();
        });
    }

    function initDateRangePicker() {
        $('.mavlers-date-range').daterangepicker({
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear'
            }
        });

        $('.mavlers-date-range').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
            loadAnalyticsData();
        });

        $('.mavlers-date-range').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            loadAnalyticsData();
        });
    }

    function loadAnalyticsData() {
        const formId = $('.mavlers-analytics-container').data('form-id');
        const period = $('.mavlers-period-select').val();
        const activeTab = $('.mavlers-analytics-tabs .active').data('tab');

        // Show loading state
        $('.mavlers-analytics-content').addClass('loading');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mavlers_get_analytics_data',
                nonce: mavlersAnalytics.nonce,
                form_id: formId,
                period: period,
                type: activeTab
            },
            success: function(response) {
                if (response.success) {
                    updateAnalyticsDisplay(activeTab, response.data);
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', 'Failed to load analytics data');
            },
            complete: function() {
                $('.mavlers-analytics-content').removeClass('loading');
            }
        });
    }

    function updateAnalyticsDisplay(tab, data) {
        switch (tab) {
            case 'overview':
                updateOverviewData(data);
                break;
            case 'submissions':
                updateSubmissionsChart(data);
                break;
            case 'conversion':
                updateConversionChart(data);
                break;
            case 'field_analytics':
                updateFieldAnalytics(data);
                break;
        }
    }

    function updateOverviewData(data) {
        // Update metrics
        $('.mavlers-views-count').text(data.views);
        $('.mavlers-submissions-count').text(data.submissions);
        $('.mavlers-conversion-rate').text(data.conversion_rate.toFixed(1) + '%');

        // Update trend indicators
        const $trendIndicator = $('.mavlers-trend-indicator');
        $trendIndicator.removeClass('up down stable').addClass(data.trend);
    }

    function updateSubmissionsChart(data) {
        const ctx = document.getElementById('mavlers-submissions-chart');
        if (!ctx) return;

        if (window.mavlersSubmissionsChart) {
            window.mavlersSubmissionsChart.destroy();
        }

        window.mavlersSubmissionsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Submissions',
                    data: data.data,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
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
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    function updateConversionChart(data) {
        const ctx = document.getElementById('mavlers-conversion-chart');
        if (!ctx) return;

        if (window.mavlersConversionChart) {
            window.mavlersConversionChart.destroy();
        }

        window.mavlersConversionChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Views',
                        data: data.views,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        yAxisID: 'y',
                        tension: 0.4
                    },
                    {
                        label: 'Submissions',
                        data: data.submissions,
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        yAxisID: 'y',
                        tension: 0.4
                    },
                    {
                        label: 'Conversion Rate',
                        data: data.conversion_rates,
                        borderColor: '#d63638',
                        backgroundColor: 'rgba(214, 54, 56, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Conversion Rate (%)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }

    function updateFieldAnalytics(data) {
        const $container = $('.mavlers-field-analytics');
        $container.empty();

        Object.entries(data).forEach(([fieldName, fieldData]) => {
            const $fieldCard = $('<div class="mavlers-field-card"></div>');
            $fieldCard.append(`<h3>${fieldData.label}</h3>`);
            
            if (fieldData.data.length > 0) {
                const $chartContainer = $('<div class="mavlers-field-chart"></div>');
                const canvas = document.createElement('canvas');
                $chartContainer.append(canvas);
                $fieldCard.append($chartContainer);

                const ctx = canvas.getContext('2d');
                new Chart(ctx, {
                    type: ['select', 'radio', 'checkbox'].includes(fieldData.type) ? 'pie' : 'bar',
                    data: {
                        labels: fieldData.data.map(item => item.value),
                        datasets: [{
                            data: fieldData.data.map(item => item.count),
                            backgroundColor: [
                                '#2271b1',
                                '#00a32a',
                                '#d63638',
                                '#dba617',
                                '#3858e9',
                                '#a7aaad'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            } else {
                $fieldCard.append('<p>No data available</p>');
            }

            $container.append($fieldCard);
        });
    }

    function switchTab(tab) {
        $('.mavlers-analytics-tabs a').removeClass('active');
        $(`.mavlers-analytics-tabs a[data-tab="${tab}"]`).addClass('active');

        $('.mavlers-analytics-tab-content').hide();
        $(`.mavlers-analytics-tab-content[data-tab="${tab}"]`).show();

        loadAnalyticsData();
    }

    function showNotice(type, message) {
        const $notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
            </div>
        `);
        
        $('.mavlers-analytics-container').prepend($notice);
        
        setTimeout(() => {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
}); 