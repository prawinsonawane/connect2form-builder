<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\ApiClient;
use MavlersCF\Integrations\Core\Services\SecurityManager;
use MavlersCF\Integrations\Core\Services\ErrorHandler;

/**
 * HubSpot API Client
 * 
 * Handles all API communications with HubSpot
 */
class HubspotApiClient {

    private $api_client;
    private $error_handler;
    private $base_url = 'https://api.hubapi.com';
    private $api_version = 'v3';

    public function __construct() {
        $this->api_client = new ApiClient();
        $this->error_handler = new ErrorHandler();
    }

    /**
     * Test API connection
     */
    public function testConnection(string $access_token, string $portal_id): array {
        try {
            // Validate inputs
            if (empty($access_token) || empty($portal_id)) {
                return [
                    'success' => false,
                    'error' => 'Access token and Portal ID are required'
                ];
            }

            // Test basic connectivity
            $test_url = "{$this->base_url}/crm/{$this->api_version}/objects/contacts";
            $response = $this->makeRequest('GET', $test_url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            // Test portal-specific endpoint
            $portal_url = "{$this->base_url}/crm/{$this->api_version}/objects/contacts?limit=1";
            $portal_response = $this->makeRequest('GET', $portal_url, $access_token);

            if (!$portal_response['success']) {
                return $portal_response;
            }

            return [
                'success' => true,
                'data' => [
                    'portal_id' => $portal_id,
                    'access_token' => substr($access_token, 0, 10) . '...',
                    'api_version' => $this->api_version
                ]
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'test_connection',
                'portal_id' => $portal_id
            ]);
        }
    }

    /**
     * Get contacts from HubSpot
     */
    public function getContacts(string $access_token, array $params = []): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/contacts";
            
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_contacts',
                'params' => $params
            ]);
        }
    }

    /**
     * Get companies from HubSpot
     */
    public function getCompanies(string $access_token, array $params = []): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/companies";
            
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_companies',
                'params' => $params
            ]);
        }
    }

    /**
     * Get deals from HubSpot
     */
    public function getDeals(string $access_token, array $params = []): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/deals";
            
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_deals',
                'params' => $params
            ]);
        }
    }

    /**
     * Get contact properties
     */
    public function getContactProperties(string $access_token): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/properties/contacts";
            
            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            error_log("HubSpot: Exception in getContactProperties: " . $e->getMessage());
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_contact_properties'
            ]);
        }
    }

    /**
     * Get deal properties
     */
    public function getDealProperties(string $access_token): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/properties/deals";
            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_deal_properties'
            ]);
        }
    }

    /**
     * Get company properties
     */
    public function getCompanyProperties(string $access_token): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/properties/companies";
            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_company_properties'
            ]);
        }
    }

    /**
     * Get custom objects
     */
    public function getCustomObjects(string $access_token): array {
        try {
            // Try multiple endpoints to get custom objects
            $endpoints = [
                "{$this->base_url}/crm/{$this->api_version}/schemas",
                "{$this->base_url}/crm/{$this->api_version}/objects",
                "{$this->base_url}/crm/{$this->api_version}/schemas?archived=false"
            ];

            $custom_objects = [];

            foreach ($endpoints as $url) {
            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                    continue;
            }

                // Process results based on endpoint
                if (strpos($url, '/schemas') !== false) {
                    // Schema endpoint
            if (isset($response['data']['results'])) {
                        error_log("HubSpot: Processing " . count($response['data']['results']) . " schemas");
                foreach ($response['data']['results'] as $schema) {
                            error_log("HubSpot: Schema - name: " . ($schema['name'] ?? 'unknown') . ", objectType: " . ($schema['objectType'] ?? 'unknown') . ", fullyQualifiedName: " . ($schema['fullyQualifiedName'] ?? 'unknown'));
                            
                            // Check for custom objects - try multiple conditions
                            $is_custom = false;
                    if (isset($schema['objectType']) && $schema['objectType'] === 'CUSTOM') {
                                $is_custom = true;
                            } elseif (isset($schema['fullyQualifiedName']) && strpos($schema['fullyQualifiedName'], 'p_') === 0) {
                                $is_custom = true;
                            } elseif (isset($schema['name']) && !in_array($schema['name'], ['contacts', 'companies', 'deals', 'tickets'])) {
                                $is_custom = true;
                            }
                            
                            if ($is_custom) {
                        $custom_objects[] = $schema;
                                error_log("HubSpot: Found custom object from schemas: " . ($schema['name'] ?? 'unknown'));
                    }
                }
            }
                } else {
                    // Objects endpoint - might return different format
                    if (isset($response['data']['results'])) {
                        foreach ($response['data']['results'] as $object) {
                            // Check if this is a custom object
                            if (isset($object['objectType']) && $object['objectType'] !== 'CONTACT' && $object['objectType'] !== 'COMPANY' && $object['objectType'] !== 'DEAL') {
                                $custom_objects[] = $object;
                                error_log("HubSpot: Found custom object from objects: " . ($object['objectType'] ?? 'unknown'));
                            }
                        }
                    }
                }

                // If we found objects, break
                if (!empty($custom_objects)) {
                    error_log("HubSpot: Found custom objects, stopping search");
                    break;
                }
            }

            error_log("HubSpot: Total custom objects found: " . count($custom_objects));

            return [
                'success' => true,
                'data' => $custom_objects
            ];

        } catch (\Exception $e) {
            error_log("HubSpot: Exception in getCustomObjects: " . $e->getMessage());
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_custom_objects'
            ]);
        }
    }

    /**
     * Get custom object properties
     */
    public function getCustomObjectProperties(string $access_token, string $object_name): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/properties/{$object_name}";
            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_custom_object_properties',
                'object_name' => $object_name
            ]);
        }
    }

    /**
     * Get workflows
     */
    public function getWorkflows(string $access_token): array {
        try {
            $url = "{$this->base_url}/automation/v3/workflows";
            $response = $this->makeRequest('GET', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'get_workflows'
            ]);
        }
    }

    /**
     * Create or update contact
     */
    public function createOrUpdateContact(array $contact_data, string $access_token): array {
        try {
            // First try to create the contact
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/contacts";
            $response = $this->makeRequest('POST', $url, $access_token, $contact_data);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data'],
                    'contact_id' => $response['data']['id'] ?? null
                ];
            }

            // If creation failed with 409 (conflict), extract the existing contact ID and update it
            if (isset($response['status_code']) && $response['status_code'] === 409) {
                $error_message = $response['error'] ?? '';
                if (preg_match('/Existing ID: (\d+)/', $error_message, $matches)) {
                    $existing_contact_id = $matches[1];
                    
                    // Update the existing contact
                    $update_url = "{$this->base_url}/crm/{$this->api_version}/objects/contacts/{$existing_contact_id}";
                    $update_response = $this->makeRequest('PATCH', $update_url, $access_token, $contact_data);
                    
                    if ($update_response['success']) {
                        return [
                            'success' => true,
                            'data' => $update_response['data'],
                            'contact_id' => $existing_contact_id,
                            'updated' => true
                        ];
                    } else {
                        return $update_response;
                    }
                }
            }

            return $response;

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'create_or_update_contact',
                'contact_data' => $contact_data
            ]);
        }
    }

    /**
     * Create deal
     */
    public function createDeal(array $deal_data, string $access_token): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/deals";
            
            $response = $this->makeRequest('POST', $url, $access_token, $deal_data);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data'],
                'deal_id' => $response['data']['id'] ?? null
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'create_deal',
                'deal_data' => $deal_data
            ]);
        }
    }

    /**
     * Update deal
     */
    public function updateDeal(string $deal_id, array $deal_data, string $access_token): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/deals/{$deal_id}";
            
            $response = $this->makeRequest('PATCH', $url, $access_token, $deal_data);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'update_deal',
                'deal_id' => $deal_id,
                'deal_data' => $deal_data
            ]);
        }
    }

    /**
     * Enroll in workflow
     */
    public function enrollInWorkflow(string $workflow_id, array $enrollment_data, string $access_token): array {
        try {
            $url = "{$this->base_url}/automation/v3/workflows/{$workflow_id}/enrollments";
            
            $response = $this->makeRequest('POST', $url, $access_token, $enrollment_data);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'enroll_in_workflow',
                'workflow_id' => $workflow_id,
                'enrollment_data' => $enrollment_data
            ]);
        }
    }

    /**
     * Create custom object
     */
    public function createCustomObject(string $object_name, array $object_data, string $access_token): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/{$object_name}";
            
            $response = $this->makeRequest('POST', $url, $access_token, $object_data);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data'],
                'object_id' => $response['data']['id'] ?? null
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'create_custom_object',
                'object_name' => $object_name,
                'object_data' => $object_data
            ]);
        }
    }

    /**
     * Update custom object
     */
    public function updateCustomObject(string $object_name, string $object_id, array $object_data, string $access_token): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/{$object_name}/{$object_id}";
            
            $response = $this->makeRequest('PATCH', $url, $access_token, $object_data);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'update_custom_object',
                'object_name' => $object_name,
                'object_id' => $object_id,
                'object_data' => $object_data
            ]);
        }
    }

    /**
     * Associate company with contact
     */
    public function associateCompany(string $contact_id, string $company_id, string $access_token): array {
        try {
            $url = "{$this->base_url}/crm/{$this->api_version}/objects/contacts/{$contact_id}/associations/companies/{$company_id}/deal_to_contact";
            
            $response = $this->makeRequest('PUT', $url, $access_token);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
            return $this->error_handler->handleIntegrationError($e, 'hubspot', [
                'action' => 'associate_company',
                'contact_id' => $contact_id,
                'company_id' => $company_id
            ]);
        }
    }

    /**
     * Make API request with proper authentication
     */
    private function makeRequest(string $method, string $url, string $access_token, array $data = []): array {
        // Check rate limiting
        $rate_limit_key = 'hubspot_api_' . md5($url);
        if (!SecurityManager::checkRateLimit($rate_limit_key, 100, 3600)) {
            return [
                'success' => false,
                'error' => 'Rate limit exceeded for HubSpot API',
                'error_code' => 'RATE_LIMIT_EXCEEDED'
            ];
        }

        $headers = [
            'Authorization' => "Bearer {$access_token}",
            'Content-Type' => 'application/json'
        ];

        $args = [
            'headers' => $headers,
            'timeout' => 30
        ];

        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = $this->api_client->request($method, $url, $args);
        


        return $response;
    }
} 