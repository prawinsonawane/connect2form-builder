<?php

namespace MavlersCF\Integrations\Hubspot;

/**
 * HubSpot Analytics Manager
 * 
 * Handles analytics, reporting, and performance tracking
 */
class AnalyticsManager {

    protected $version = '1.0.0';
    protected $language_manager;

    public function __construct() {
        $this->language_manager = new LanguageManager();
    }

    /**
     * Get analytics data for HubSpot integration
     */
    public function get_analytics_data(array $filters = []): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        // Get basic analytics data
        $analytics = [
            'contacts_created' => $this->get_contacts_created_count($filters),
            'deals_created' => $this->get_deals_created_count($filters),
            'workflow_enrollments' => $this->get_workflow_enrollments_count($filters),
            'recent_activities' => $this->get_recent_activities($filters),
            'performance_metrics' => $this->get_performance_metrics($filters)
        ];

        return [
            'success' => true,
            'data' => $analytics
        ];
    }

    /**
     * Get contacts created count
     */
    private function get_contacts_created_count(array $filters = []): int {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return 0;
        }

        // Get contacts created in the last 30 days
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');

        $url = "https://api.hubapi.com/crm/v3/objects/contacts?limit=1&filter=" . urlencode(json_encode([
            'propertyName' => 'createdate',
            'operator' => 'BETWEEN',
            'values' => [$start_date, $end_date]
        ]));

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return 0;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['total'])) {
            return $data['total'];
        }

        return 0;
    }

    /**
     * Get deals created count
     */
    private function get_deals_created_count(array $filters = []): int {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return 0;
        }

        // Get deals created in the last 30 days
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');

        $url = "https://api.hubapi.com/crm/v3/objects/deals?limit=1&filter=" . urlencode(json_encode([
            'propertyName' => 'createdate',
            'operator' => 'BETWEEN',
            'values' => [$start_date, $end_date]
        ]));

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return 0;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['total'])) {
            return $data['total'];
        }

        return 0;
    }

    /**
     * Get workflow enrollments count
     */
    private function get_workflow_enrollments_count(array $filters = []): int {
        // This would require tracking workflow enrollments
        // For now, return a placeholder
        return 0;
    }

    /**
     * Get recent activities
     */
    private function get_recent_activities(array $filters = []): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [];
        }

        // Get recent contacts
        $url = "https://api.hubapi.com/crm/v3/objects/contacts?limit=10&properties=firstname,lastname,email,createdate";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['results'])) {
            $activities = [];
            
            foreach ($data['results'] as $contact) {
                $activities[] = [
                    'type' => 'contact_created',
                    'id' => $contact['id'],
                    'name' => ($contact['properties']['firstname']['value'] ?? '') . ' ' . ($contact['properties']['lastname']['value'] ?? ''),
                    'email' => $contact['properties']['email']['value'] ?? '',
                    'date' => $contact['properties']['createdate']['value'] ?? '',
                    'description' => 'New contact created'
                ];
            }

            return $activities;
        }

        return [];
    }

    /**
     * Get performance metrics
     */
    private function get_performance_metrics(array $filters = []): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [];
        }

        // Get basic metrics
        $metrics = [
            'total_contacts' => $this->get_total_contacts_count(),
            'total_deals' => $this->get_total_deals_count(),
            'active_workflows' => $this->get_active_workflows_count(),
            'conversion_rate' => $this->calculate_conversion_rate()
        ];

        return $metrics;
    }

    /**
     * Get total contacts count
     */
    private function get_total_contacts_count(): int {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return 0;
        }

        $url = "https://api.hubapi.com/crm/v3/objects/contacts?limit=1";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return 0;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['total'])) {
            return $data['total'];
        }

        return 0;
    }

    /**
     * Get total deals count
     */
    private function get_total_deals_count(): int {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return 0;
        }

        $url = "https://api.hubapi.com/crm/v3/objects/deals?limit=1";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return 0;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['total'])) {
            return $data['total'];
        }

        return 0;
    }

    /**
     * Get active workflows count
     */
    private function get_active_workflows_count(): int {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return 0;
        }

        $url = "https://api.hubapi.com/automation/v3/workflows";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return 0;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 && isset($data['results'])) {
            $active_count = 0;
            foreach ($data['results'] as $workflow) {
                if ($workflow['enabled']) {
                    $active_count++;
                }
            }
            return $active_count;
        }

        return 0;
    }

    /**
     * Calculate conversion rate
     */
    private function calculate_conversion_rate(): float {
        $contacts = $this->get_total_contacts_count();
        $deals = $this->get_total_deals_count();

        if ($contacts === 0) {
            return 0.0;
        }

        return round(($deals / $contacts) * 100, 2);
    }

    /**
     * Export analytics data
     */
    public function export_analytics_data(array $filters = []): array {
        $analytics_data = $this->get_analytics_data($filters);
        
        if (!$analytics_data['success']) {
            return $analytics_data;
        }

        // Format data for export
        $export_data = [
            'export_date' => date('Y-m-d H:i:s'),
            'period' => 'Last 30 days',
            'metrics' => $analytics_data['data']['performance_metrics'],
            'activities' => $analytics_data['data']['recent_activities']
        ];

        return [
            'success' => true,
            'data' => $export_data,
            'filename' => 'hubspot_analytics_' . date('Y-m-d') . '.json'
        ];
    }

    /**
     * Get analytics dashboard data
     */
    public function get_dashboard_data(): array {
        $analytics_data = $this->get_analytics_data();
        
        if (!$analytics_data['success']) {
            return $analytics_data;
        }

        // Format for dashboard display
        $dashboard_data = [
            'summary' => [
                'contacts_created' => $analytics_data['data']['contacts_created'],
                'deals_created' => $analytics_data['data']['deals_created'],
                'workflow_enrollments' => $analytics_data['data']['workflow_enrollments']
            ],
            'metrics' => $analytics_data['data']['performance_metrics'],
            'recent_activities' => array_slice($analytics_data['data']['recent_activities'], 0, 5)
        ];

        return [
            'success' => true,
            'data' => $dashboard_data
        ];
    }

    /**
     * AJAX handler for analytics data
     */
    public function ajax_get_analytics_data(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $filters = $_POST['filters'] ?? [];
        $result = $this->get_analytics_data($filters);
        
        wp_send_json($result);
    }

    /**
     * AJAX handler for dashboard data
     */
    public function ajax_get_dashboard_data(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $result = $this->get_dashboard_data();
        
        wp_send_json($result);
    }

    /**
     * AJAX handler for export
     */
    public function ajax_export_analytics(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $filters = $_POST['filters'] ?? [];
        $result = $this->export_analytics_data($filters);
        
        wp_send_json($result);
    }

    /**
     * Get global settings for HubSpot
     */
    private function get_global_settings() {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        return $global_settings['hubspot'] ?? [];
    }

    /**
     * Translation helper
     */
    private function __($text, $fallback = null) {
        if ($this->language_manager) {
            return $this->language_manager->translate($text);
        }
        return $fallback ?: $text;
    }
} 