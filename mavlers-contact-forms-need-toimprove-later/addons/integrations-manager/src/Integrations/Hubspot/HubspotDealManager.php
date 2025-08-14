<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\Logger;

/**
 * HubSpot Deal Manager
 * 
 * Handles all deal-related operations
 */
class HubspotDealManager {

    private $api_client;
    private $logger;

    public function __construct(HubspotApiClient $api_client) {
        $this->api_client = $api_client;
        $this->logger = new Logger();
    }

    /**
     * Create deal from form data
     */
    public function createDeal(array $form_data, array $settings, string $access_token, int $submission_id): array {
        try {
            // Map form data to HubSpot deal properties
            $deal_data = $this->mapFormDataToDeal($form_data, $settings);
            
            if (empty($deal_data['properties'])) {
                return [
                    'success' => false,
                    'error' => 'No valid deal data found'
                ];
            }

            // Create deal
            $result = $this->api_client->createDeal($deal_data, $access_token);
            
            if ($result['success']) {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'success', 
                    'Deal created successfully', [
                        'submission_id' => $submission_id,
                        'deal_id' => $result['deal_id'] ?? null,
                        'properties' => array_keys($deal_data['properties'])
                    ], $submission_id
                );
            } else {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                    'Failed to create deal: ' . ($result['error'] ?? 'Unknown error'), [
                        'submission_id' => $submission_id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ], $submission_id
                );
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                'Exception in deal creation: ' . $e->getMessage(), [
                    'submission_id' => $submission_id,
                    'exception' => $e->getMessage()
                ], $submission_id
            );

            return [
                'success' => false,
                'error' => 'Deal creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update existing deal
     */
    public function updateDeal(string $deal_id, array $form_data, array $settings, string $access_token, int $submission_id): array {
        try {
            // Map form data to HubSpot deal properties
            $deal_data = $this->mapFormDataToDeal($form_data, $settings);
            
            if (empty($deal_data['properties'])) {
                return [
                    'success' => false,
                    'error' => 'No valid deal data found'
                ];
            }

            // Update deal
            $result = $this->api_client->updateDeal($deal_id, $deal_data, $access_token);
            
            if ($result['success']) {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'success', 
                    'Deal updated successfully', [
                        'submission_id' => $submission_id,
                        'deal_id' => $deal_id,
                        'properties' => array_keys($deal_data['properties'])
                    ], $submission_id
                );
            } else {
                $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                    'Failed to update deal: ' . ($result['error'] ?? 'Unknown error'), [
                        'submission_id' => $submission_id,
                        'deal_id' => $deal_id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ], $submission_id
                );
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                'Exception in deal update: ' . $e->getMessage(), [
                    'submission_id' => $submission_id,
                    'deal_id' => $deal_id,
                    'exception' => $e->getMessage()
                ], $submission_id
            );

            return [
                'success' => false,
                'error' => 'Deal update failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Map form data to HubSpot deal properties
     */
    private function mapFormDataToDeal(array $form_data, array $settings): array {
        $deal_data = [
            'properties' => []
        ];

        // Get field mapping
        $field_mapping = $settings['field_mapping'] ?? [];
        
        // Map standard deal fields
        $this->mapStandardDealFields($form_data, $field_mapping, $deal_data['properties']);
        
        // Map custom deal fields
        $this->mapCustomDealFields($form_data, $field_mapping, $deal_data['properties']);
        
        // Add default properties
        if (!empty($deal_data['properties']['dealname'])) {
            $deal_data['properties']['dealstage'] = $deal_data['properties']['dealstage'] ?? 'appointmentscheduled';
            $deal_data['properties']['pipeline'] = $deal_data['properties']['pipeline'] ?? 'default';
        }

        return $deal_data;
    }

    /**
     * Map standard deal fields
     */
    private function mapStandardDealFields(array $form_data, array $field_mapping, array &$properties): void {
        $standard_fields = [
            'dealname' => 'dealname',
            'amount' => 'amount',
            'dealstage' => 'dealstage',
            'pipeline' => 'pipeline',
            'closedate' => 'closedate',
            'description' => 'description',
            'dealtype' => 'dealtype'
        ];

        foreach ($standard_fields as $hubspot_field => $form_field) {
            if (isset($field_mapping[$hubspot_field]) && isset($form_data[$field_mapping[$hubspot_field]])) {
                $properties[$hubspot_field] = $form_data[$field_mapping[$hubspot_field]];
            } elseif (isset($form_data[$form_field])) {
                $properties[$hubspot_field] = $form_data[$form_field];
            }
        }
    }

    /**
     * Map custom deal fields
     */
    private function mapCustomDealFields(array $form_data, array $field_mapping, array &$properties): void {
        foreach ($field_mapping as $hubspot_field => $form_field) {
            // Skip standard fields already mapped
            $standard_fields = ['dealname', 'amount', 'dealstage', 'pipeline', 'closedate', 'description', 'dealtype'];
            if (in_array($hubspot_field, $standard_fields)) {
                continue;
            }

            if (isset($form_data[$form_field])) {
                $properties[$hubspot_field] = $form_data[$form_field];
            }
        }
    }

    /**
     * Get deal by ID
     */
    public function getDeal(string $deal_id, string $access_token): array {
        try {
            $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
            
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
                'deal' => $response['data']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get deal: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get deals by contact email
     */
    public function getDealsByContactEmail(string $email, string $access_token): array {
        try {
            $params = [
                'filter' => "associations.contact.email={$email}",
                'limit' => 10
            ];

            $result = $this->api_client->getDeals($access_token, $params);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'deals' => $result['data']
                ];
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get deals: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get deal properties for field mapping
     */
    public function getDealProperties(string $access_token): array {
        try {
            $result = $this->api_client->getDealProperties($access_token);
            
            if ($result['success']) {
                // Format properties for field mapping
                $formatted_properties = [];
                foreach ($result['data'] as $property) {
                    if (isset($property['name']) && isset($property['label'])) {
                        $formatted_properties[] = [
                            'name' => $property['name'],
                            'label' => $property['label'],
                            'type' => $property['type'] ?? 'string',
                            'required' => $property['required'] ?? false
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
                'error' => 'Failed to get deal properties: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate deal data
     */
    public function validateDealData(array $deal_data): array {
        $errors = [];

        // Check if deal name is present
        if (empty($deal_data['properties']['dealname'])) {
            $errors[] = 'Deal name is required for deal creation';
        }

        // Check if we have at least one property
        if (empty($deal_data['properties'])) {
            $errors[] = 'At least one deal property is required';
        }

        // Validate amount if present
        if (!empty($deal_data['properties']['amount']) && !is_numeric($deal_data['properties']['amount'])) {
            $errors[] = 'Deal amount must be numeric';
        }

        return $errors;
    }

    /**
     * Associate deal with contact
     */
    public function associateWithContact(string $deal_id, string $contact_id, string $access_token): array {
        try {
            $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}/associations/contacts/{$contact_id}/deal_to_contact";
            
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
                'Deal associated with contact', [
                    'deal_id' => $deal_id,
                    'contact_id' => $contact_id
                ]
            );

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', 0, 'error', 
                'Failed to associate deal with contact: ' . $e->getMessage(), [
                    'deal_id' => $deal_id,
                    'contact_id' => $contact_id,
                    'exception' => $e->getMessage()
                ]
            );

            return [
                'success' => false,
                'error' => 'Failed to associate deal with contact: ' . $e->getMessage()
            ];
        }
    }
} 