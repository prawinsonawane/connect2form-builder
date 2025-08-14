<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\Logger;

/**
 * HubSpot Custom Object Manager
 * 
 * Handles all custom object-related operations
 */
class HubspotCustomObjectManager {

    private $api_client;
    private $logger;

    public function __construct(HubspotApiClient $api_client) {
        $this->api_client = $api_client;
        $this->logger = new Logger();
    }

    /**
     * Create custom object from form data
     */
    public function createCustomObject(array $form_data, array $settings, string $access_token, int $submission_id): array {
        try {
            // Check if custom object is configured
            if (empty($settings['custom_object_name'])) {
                return [
                    'success' => false,
                    'error' => 'Custom object not configured'
                ];
            }

            // Map form data to custom object properties
            $object_data = $this->mapFormDataToCustomObject($form_data, $settings);
            
            if (empty($object_data['properties'])) {
                return [
                    'success' => false,
                    'error' => 'No valid custom object data found'
                ];
            }

            // Create custom object
            $result = $this->api_client->createCustomObject($settings['custom_object_name'], $object_data, $access_token);
            
            if ($result['success']) {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'success', 
                    'Custom object created successfully', [
                        'submission_id' => $submission_id,
                        'object_name' => $settings['custom_object_name'],
                        'object_id' => $result['object_id'] ?? null,
                        'properties' => array_keys($object_data['properties'])
                    ], $submission_id
                );
            } else {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                    'Failed to create custom object: ' . ($result['error'] ?? 'Unknown error'), [
                        'submission_id' => $submission_id,
                        'object_name' => $settings['custom_object_name'],
                        'error' => $result['error'] ?? 'Unknown error'
                    ], $submission_id
                );
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                'Exception in custom object creation: ' . $e->getMessage(), [
                    'submission_id' => $submission_id,
                    'object_name' => $settings['custom_object_name'] ?? null,
                    'exception' => $e->getMessage()
                ], $submission_id
            );

            return [
                'success' => false,
                'error' => 'Custom object creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update existing custom object
     */
    public function updateCustomObject(string $object_id, array $form_data, array $settings, string $access_token, int $submission_id): array {
        try {
            // Check if custom object is configured
            if (empty($settings['custom_object_name'])) {
                return [
                    'success' => false,
                    'error' => 'Custom object not configured'
                ];
            }

            // Map form data to custom object properties
            $object_data = $this->mapFormDataToCustomObject($form_data, $settings);
            
            if (empty($object_data['properties'])) {
                return [
                    'success' => false,
                    'error' => 'No valid custom object data found'
                ];
            }

            // Update custom object
            $result = $this->api_client->updateCustomObject($settings['custom_object_name'], $object_id, $object_data, $access_token);
            
            if ($result['success']) {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'success', 
                    'Custom object updated successfully', [
                        'submission_id' => $submission_id,
                        'object_name' => $settings['custom_object_name'],
                        'object_id' => $object_id,
                        'properties' => array_keys($object_data['properties'])
                    ], $submission_id
                );
            } else {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                    'Failed to update custom object: ' . ($result['error'] ?? 'Unknown error'), [
                        'submission_id' => $submission_id,
                        'object_name' => $settings['custom_object_name'],
                        'object_id' => $object_id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ], $submission_id
                );
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                'Exception in custom object update: ' . $e->getMessage(), [
                    'submission_id' => $submission_id,
                    'object_name' => $settings['custom_object_name'] ?? null,
                    'object_id' => $object_id,
                    'exception' => $e->getMessage()
                ], $submission_id
            );

            return [
                'success' => false,
                'error' => 'Custom object update failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Map form data to custom object properties
     */
    private function mapFormDataToCustomObject(array $form_data, array $settings): array {
        $object_data = [
            'properties' => []
        ];

        // Get field mapping
        $field_mapping = $settings['field_mapping'] ?? [];
        
        // Map all custom fields
        foreach ($field_mapping as $hubspot_field => $form_field) {
            if (isset($form_data[$form_field])) {
                $object_data['properties'][$hubspot_field] = $form_data[$form_field];
            }
        }

        return $object_data;
    }

    /**
     * Get custom objects
     */
    public function getCustomObjects(string $access_token): array {
        try {
            $result = $this->api_client->getCustomObjects($access_token);
            
            if ($result['success']) {
                // Format custom objects for display
                $formatted_objects = [];
                
                foreach ($result['data'] as $object) {
                    // Check for different possible property names
                    $name = $object['name'] ?? $object['fullyQualifiedName'] ?? '';
                    $label = $object['label'] ?? $object['name'] ?? $object['fullyQualifiedName'] ?? '';
                    
                    if (!empty($name) && !empty($label)) {
                        $formatted_objects[] = [
                            'name' => $name,
                            'label' => $label,
                            'fullyQualifiedName' => $object['fullyQualifiedName'] ?? $name,
                            'description' => $object['description'] ?? '',
                            'enabled' => $object['enabled'] ?? false
                        ];
                    }
                }
                
                return [
                    'success' => true,
                    'data' => $formatted_objects
                ];
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get custom objects: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get custom object properties
     */
    public function getCustomObjectProperties(string $access_token, string $object_name): array {
        try {
            $result = $this->api_client->getCustomObjectProperties($access_token, $object_name);
            
            if ($result['success']) {
                // Format properties for field mapping
                $formatted_properties = [];
                foreach ($result['data'] as $property) {
                    if (isset($property['name']) && isset($property['label'])) {
                        $formatted_properties[] = [
                            'name' => $property['name'],
                            'label' => $property['label'],
                            'type' => $property['type'] ?? 'string',
                            'required' => $property['required'] ?? false,
                            'description' => $property['description'] ?? ''
                        ];
                    }
                }
                
                return [
                    'success' => true,
                    'data' => $formatted_properties
                ];
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get custom object properties: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get custom object by ID
     */
    public function getCustomObject(string $object_name, string $object_id, string $access_token): array {
        try {
            $url = "https://api.hubapi.com/crm/v3/objects/{$object_name}/{$object_id}";
            
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
                'object' => $response['data']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get custom object: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Search custom objects
     */
    public function searchCustomObjects(string $object_name, array $search_criteria, string $access_token): array {
        try {
            $url = "https://api.hubapi.com/crm/v3/objects/{$object_name}/search";
            
            $headers = [
                'Authorization' => "Bearer {$access_token}",
                'Content-Type' => 'application/json'
            ];

            $args = [
                'headers' => $headers,
                'body' => json_encode($search_criteria),
                'timeout' => 30
            ];

            $api_client = new \MavlersCF\Integrations\Core\Services\ApiClient();
            $response = $api_client->request('POST', $url, $args);

            if (!$response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data']['results'] ?? []
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to search custom objects: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate custom object settings
     */
    public function validateCustomObjectSettings(array $settings): array {
        $errors = [];

        // Check if custom object name is provided
        if (!empty($settings['custom_object_enabled']) && empty($settings['custom_object_name'])) {
            $errors[] = 'Custom object name is required when custom object creation is enabled';
        }

        // Check if custom object name is valid format
        if (!empty($settings['custom_object_name']) && !preg_match('/^[a-zA-Z0-9_-]+$/', $settings['custom_object_name'])) {
            $errors[] = 'Custom object name must contain only letters, numbers, hyphens, and underscores';
        }

        return $errors;
    }

    /**
     * Check if custom object is configured
     */
    public function isCustomObjectConfigured(array $settings): bool {
        return !empty($settings['custom_object_enabled']) && !empty($settings['custom_object_name']);
    }

    /**
     * Associate custom object with contact
     */
    public function associateWithContact(string $object_name, string $object_id, string $contact_id, string $access_token): array {
        try {
            $url = "https://api.hubapi.com/crm/v3/objects/{$object_name}/{$object_id}/associations/contacts/{$contact_id}/{$object_name}_to_contact";
            
            $headers = [
                'Authorization' => "Bearer {$access_token}",
                'Content-Type' => 'application/json'
            ];

            $args = [
                'headers' => $headers,
                'timeout' => 30
            ];

            $api_client = new \MavlersCF\Integrations\Core\Services\ApiClient();
            $response = $api_client->request('PUT', $url, $args);

            if (!$response['success']) {
                return $response;
            }

            $this->logger->logIntegration('hubspot', 0, 'success', 
                'Custom object associated with contact', [
                    'object_name' => $object_name,
                    'object_id' => $object_id,
                    'contact_id' => $contact_id
                ]
            );

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', 0, 'error', 
                'Failed to associate custom object with contact: ' . $e->getMessage(), [
                    'object_name' => $object_name,
                    'object_id' => $object_id,
                    'contact_id' => $contact_id,
                    'exception' => $e->getMessage()
                ]
            );

            return [
                'success' => false,
                'error' => 'Failed to associate custom object with contact: ' . $e->getMessage()
            ];
        }
    }
} 