<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\Logger;

/**
 * HubSpot Workflow Manager
 * 
 * Handles all workflow-related operations
 */
class HubspotWorkflowManager {

    private $api_client;
    private $logger;

    public function __construct(HubspotApiClient $api_client) {
        $this->api_client = $api_client;
        $this->logger = new Logger();
    }

    /**
     * Enroll contact in workflow
     */
    public function enrollInWorkflow(array $form_data, array $settings, string $access_token, int $submission_id): array {
        try {
            // Check if workflow enrollment is enabled
            if (empty($settings['workflow_enabled']) || empty($settings['workflow_id'])) {
                return [
                    'success' => false,
                    'error' => 'Workflow enrollment not configured'
                ];
            }

            // Get contact email for enrollment
            $contact_email = $this->getContactEmailFromFormData($form_data, $settings);
            
            if (empty($contact_email)) {
                return [
                    'success' => false,
                    'error' => 'Contact email not found for workflow enrollment'
                ];
            }

            // Prepare enrollment data
            $enrollment_data = [
                'email' => $contact_email,
                'workflowId' => $settings['workflow_id']
            ];

            // Add additional data if available
            if (!empty($form_data['firstname'])) {
                $enrollment_data['firstName'] = $form_data['firstname'];
            }
            if (!empty($form_data['lastname'])) {
                $enrollment_data['lastName'] = $form_data['lastname'];
            }

            // Enroll in workflow
            $result = $this->api_client->enrollInWorkflow($settings['workflow_id'], $enrollment_data, $access_token);
            
            if ($result['success']) {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'success', 
                    'Contact enrolled in workflow successfully', [
                        'submission_id' => $submission_id,
                        'workflow_id' => $settings['workflow_id'],
                        'contact_email' => $contact_email
                    ], $submission_id
                );
            } else {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                    'Failed to enroll in workflow: ' . ($result['error'] ?? 'Unknown error'), [
                        'submission_id' => $submission_id,
                        'workflow_id' => $settings['workflow_id'],
                        'contact_email' => $contact_email,
                        'error' => $result['error'] ?? 'Unknown error'
                    ], $submission_id
                );
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                'Exception in workflow enrollment: ' . $e->getMessage(), [
                    'submission_id' => $submission_id,
                    'workflow_id' => $settings['workflow_id'] ?? null,
                    'exception' => $e->getMessage()
                ], $submission_id
            );

            return [
                'success' => false,
                'error' => 'Workflow enrollment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get contact email from form data
     */
    private function getContactEmailFromFormData(array $form_data, array $settings): string {
        $field_mapping = $settings['field_mapping'] ?? [];
        
        // Look for email in field mapping
        if (isset($field_mapping['email']) && isset($form_data[$field_mapping['email']])) {
            return $form_data[$field_mapping['email']];
        }
        
        // Look for email in standard fields
        if (isset($form_data['email'])) {
            return $form_data['email'];
        }
        
        // Look for email in common field names
        $email_fields = ['email', 'e-mail', 'mail', 'email_address', 'emailaddress'];
        foreach ($email_fields as $field) {
            if (isset($form_data[$field])) {
                return $form_data[$field];
            }
        }
        
        return '';
    }

    /**
     * Get available workflows
     */
    public function getWorkflows(string $access_token): array {
        try {
            $result = $this->api_client->getWorkflows($access_token);
            
            if ($result['success']) {
                // Filter and format workflows
                $formatted_workflows = [];
                foreach ($result['data'] as $workflow) {
                    if (isset($workflow['id']) && isset($workflow['name'])) {
                        $formatted_workflows[] = [
                            'id' => $workflow['id'],
                            'name' => $workflow['name'],
                            'type' => $workflow['type'] ?? 'unknown',
                            'enabled' => $workflow['enabled'] ?? false
                        ];
                    }
                }
                
                return [
                    'success' => true,
                    'data' => $formatted_workflows
                ];
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get workflows: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get workflow by ID
     */
    public function getWorkflow(string $workflow_id, string $access_token): array {
        try {
            $url = "https://api.hubapi.com/automation/v3/workflows/{$workflow_id}";
            
            $headers = [
                'Authorization' => "Bearer {$access_token}",
                'Content-Type' => 'application/json'
            ];

            $args = [
                'headers' => $headers,
                'timeout' => 30
            ];

            $api_client = new \MavlersCF\Integrations\Core\Services\ApiClient();
            $response = $api_client->request('GET', $url, $args);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'workflow' => $response['data']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get workflow: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate workflow settings
     */
    public function validateWorkflowSettings(array $settings): array {
        $errors = [];

        // Check if workflow is enabled
        if (!empty($settings['workflow_enabled']) && empty($settings['workflow_id'])) {
            $errors[] = 'Workflow ID is required when workflow enrollment is enabled';
        }

        // Check if workflow ID is valid format
        if (!empty($settings['workflow_id']) && !is_numeric($settings['workflow_id'])) {
            $errors[] = 'Workflow ID must be numeric';
        }

        return $errors;
    }

    /**
     * Check if workflow enrollment is configured
     */
    public function isWorkflowConfigured(array $settings): bool {
        return !empty($settings['workflow_enabled']) && !empty($settings['workflow_id']);
    }

    /**
     * Get workflow enrollment status
     */
    public function getEnrollmentStatus(string $workflow_id, string $contact_email, string $access_token): array {
        try {
            $url = "https://api.hubapi.com/automation/v3/workflows/{$workflow_id}/enrollments";
            
            $params = [
                'email' => $contact_email
            ];
            
            $url .= '?' . http_build_query($params);
            
            $headers = [
                'Authorization' => "Bearer {$access_token}",
                'Content-Type' => 'application/json'
            ];

            $args = [
                'headers' => $headers,
                'timeout' => 30
            ];

            $api_client = new \MavlersCF\Integrations\Core\Services\ApiClient();
            $response = $api_client->request('GET', $url, $args);

            if (!$response['success']) {
                return $response;
            }

            $is_enrolled = !empty($response['data']['results']);

            return [
                'success' => true,
                'is_enrolled' => $is_enrolled,
                'enrollment_data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get enrollment status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove contact from workflow
     */
    public function removeFromWorkflow(string $workflow_id, string $contact_email, string $access_token): array {
        try {
            $url = "https://api.hubapi.com/automation/v3/workflows/{$workflow_id}/enrollments";
            
            $data = [
                'email' => $contact_email,
                'action' => 'remove'
            ];
            
            $headers = [
                'Authorization' => "Bearer {$access_token}",
                'Content-Type' => 'application/json'
            ];

            $args = [
                'headers' => $headers,
                'body' => json_encode($data),
                'timeout' => 30
            ];

            $api_client = new \MavlersCF\Integrations\Core\Services\ApiClient();
            $response = $api_client->request('DELETE', $url, $args);

            if (!$response['success']) {
                return $response;
            }

            $this->logger->logIntegration('hubspot', 0, 'success', 
                'Contact removed from workflow', [
                    'workflow_id' => $workflow_id,
                    'contact_email' => $contact_email
                ]
            );

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', 0, 'error', 
                'Failed to remove contact from workflow: ' . $e->getMessage(), [
                    'workflow_id' => $workflow_id,
                    'contact_email' => $contact_email,
                    'exception' => $e->getMessage()
                ]
            );

            return [
                'success' => false,
                'error' => 'Failed to remove contact from workflow: ' . $e->getMessage()
            ];
        }
    }
} 