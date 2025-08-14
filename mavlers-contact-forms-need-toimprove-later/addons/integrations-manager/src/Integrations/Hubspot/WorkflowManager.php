<?php

namespace MavlersCF\Integrations\Hubspot;

/**
 * HubSpot Workflow Manager
 * 
 * Handles workflow enrollment, management, and automation
 */
class WorkflowManager {

    protected $version = '1.0.0';
    protected $language_manager;

    public function __construct() {
        $this->language_manager = new LanguageManager();
    }

    /**
     * Get available workflows from HubSpot
     */
    public function get_workflows(): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        // Get workflows
        $url = "https://api.hubapi.com/automation/v3/workflows";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to fetch workflows: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            $workflows = [];
            
            if (isset($data['results'])) {
                foreach ($data['results'] as $workflow) {
                    // Only include active workflows that can be enrolled
                    if ($workflow['enabled'] && $workflow['type'] === 'DRIP_DELAY') {
                        $workflows[] = [
                            'id' => $workflow['id'],
                            'name' => $workflow['name'],
                            'type' => $workflow['type'],
                            'enabled' => $workflow['enabled'],
                            'description' => $workflow['description'] ?? ''
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'data' => $workflows
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to fetch workflows ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Enroll contact in workflow
     */
    public function enroll_contact(string $workflow_id, string $email, array $properties = []): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        if (empty($email)) {
            return [
                'success' => false,
                'error' => 'Email address is required for workflow enrollment'
            ];
        }

        $enrollment_data = [
            'email' => $email
        ];

        // Add custom properties if provided
        if (!empty($properties)) {
            $enrollment_data['properties'] = $properties;
        }

        $url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments";
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($enrollment_data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to enroll in workflow: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'message' => 'Contact enrolled in workflow successfully',
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to enroll in workflow ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Unenroll contact from workflow
     */
    public function unenroll_contact(string $workflow_id, string $email): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        if (empty($email)) {
            return [
                'success' => false,
                'error' => 'Email address is required for workflow unenrollment'
            ];
        }

        $url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments/{$email}";
        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to unenroll from workflow: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 204) {
            return [
                'success' => true,
                'message' => 'Contact unenrolled from workflow successfully'
            ];
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $error_message = $data['message'] ?? 'Unknown error';
            
            return [
                'success' => false,
                'error' => "Failed to unenroll from workflow ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get workflow enrollment status
     */
    public function get_enrollment_status(string $workflow_id, string $email): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        if (empty($email)) {
            return [
                'success' => false,
                'error' => 'Email address is required'
            ];
        }

        $url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments/{$email}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to get enrollment status: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'enrolled' => true,
                'data' => $data
            ];
        } elseif ($status_code === 404) {
            return [
                'success' => true,
                'enrolled' => false,
                'data' => null
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to get enrollment status ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get workflow performance metrics
     */
    public function get_workflow_metrics(string $workflow_id): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/automation/v3/workflows/{$workflow_id}/metrics";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to fetch workflow metrics: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to fetch workflow metrics ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get workflow details
     */
    public function get_workflow_details(string $workflow_id): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/automation/v3/workflows/{$workflow_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to fetch workflow details: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to fetch workflow details ({$status_code}): {$error_message}"
            ];
        }
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