<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\Logger;

/**
 * HubSpot Contact Manager
 * 
 * Handles all contact-related operations
 */
class HubspotContactManager {

    private $api_client;
    private $logger;

    public function __construct(HubspotApiClient $api_client) {
        $this->api_client = $api_client;
        $this->logger = new Logger();
    }

    /**
     * Create or update contact from form data
     */
    public function createOrUpdateContact(array $form_data, array $settings, string $access_token, int $submission_id): array {
        try {
            error_log('DEBUG: HubSpot createOrUpdateContact called');
            error_log('DEBUG: HubSpot form_data: ' . print_r($form_data, true));
            error_log('DEBUG: HubSpot settings: ' . print_r($settings, true));
            error_log('DEBUG: HubSpot access_token present: ' . (!empty($access_token) ? 'yes' : 'no'));
            
            // Map form data to HubSpot properties
            $mapped_data = $this->mapFormDataToContact($form_data, $settings);
            error_log('DEBUG: HubSpot mapped_data: ' . print_r($mapped_data, true));
            
            // Get email from mapped data
            $email = $mapped_data['properties']['email'] ?? '';
            if (empty($email)) {
                error_log('DEBUG: HubSpot no email found in mapped data');
                return ['success' => false, 'error' => 'Email is required'];
            }
            
            error_log('DEBUG: HubSpot email: ' . $email);
            
            // Use the API client's createOrUpdateContact method which handles both creation and updates
            $result = $this->api_client->createOrUpdateContact($mapped_data, $access_token);
            error_log('DEBUG: HubSpot result: ' . print_r($result, true));
            
            if ($result['success']) {
                error_log('DEBUG: HubSpot contact operation successful');
                return $result;
            } else {
                error_log('DEBUG: HubSpot contact operation failed: ' . ($result['error'] ?? 'Unknown error'));
                return $result;
            }
            
        } catch (\Exception $e) {
            error_log('DEBUG: HubSpot exception in createOrUpdateContact: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Map form data to HubSpot contact properties
     */
    private function mapFormDataToContact(array $form_data, array $settings): array {
        $contact_data = [
            'properties' => []
        ];

        // Get field mapping
        $field_mapping = $settings['field_mapping'] ?? [];
        
        // Extract the actual field data from the nested structure
        $field_data = $form_data;
        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            $field_data = $form_data['fields'];
        }
        
        error_log('DEBUG: HubSpot field_data: ' . print_r($field_data, true));
        error_log('DEBUG: HubSpot field_mapping: ' . print_r($field_mapping, true));
        
        // Map standard fields
        $this->mapStandardFields($field_data, $field_mapping, $contact_data['properties']);
        
        // Map custom fields
        $this->mapCustomFields($field_data, $field_mapping, $contact_data['properties']);
        
        // Add default properties if email is present
        if (!empty($contact_data['properties']['email'])) {
            $contact_data['properties']['hs_lead_status'] = 'NEW';
        }

        error_log('DEBUG: HubSpot contact_data: ' . print_r($contact_data, true));
        return $contact_data;
    }

    /**
     * Map standard contact fields
     */
    private function mapStandardFields(array $form_data, array $field_mapping, array &$properties): void {
        $standard_fields = [
            'email' => 'email',
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'phone' => 'phone',
            'company' => 'company',
            'jobtitle' => 'jobtitle',
            'website' => 'website',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'zip',
            'country' => 'country'
        ];

        // First try to map using the field_mapping (form_field_id => hubspot_property)
        foreach ($field_mapping as $form_field_id => $hubspot_property) {
            if (isset($form_data[$form_field_id]) && !empty($form_data[$form_field_id])) {
                $properties[$hubspot_property] = $form_data[$form_field_id];
            }
        }

        // Then try direct field mapping for unmapped fields
        foreach ($standard_fields as $hubspot_field => $form_field) {
            if (!isset($properties[$hubspot_field]) && isset($form_data[$form_field])) {
                $properties[$hubspot_field] = $form_data[$form_field];
            }
        }
    }

    /**
     * Map custom contact fields
     */
    private function mapCustomFields(array $form_data, array $field_mapping, array &$properties): void {
        $standard_fields = ['email', 'firstname', 'lastname', 'phone', 'company', 'jobtitle', 'website', 'address', 'city', 'state', 'zip', 'country'];
        
        // Map custom fields using field_mapping (form_field_id => hubspot_property)
        foreach ($field_mapping as $form_field_id => $hubspot_property) {
            // Skip standard fields already mapped
            if (in_array($hubspot_property, $standard_fields)) {
                continue;
            }

            if (isset($form_data[$form_field_id]) && !empty($form_data[$form_field_id])) {
                $properties[$hubspot_property] = $form_data[$form_field_id];
            }
        }
    }

    /**
     * Associate contact with company
     */
    public function associateWithCompany(string $contact_id, string $company_id, string $access_token): array {
        try {
            $result = $this->api_client->associateCompany($contact_id, $company_id, $access_token);
            
            if ($result['success']) {
                $this->logger->logIntegration('hubspot', 0, 'success', 
                    'Contact associated with company', [
                        'contact_id' => $contact_id,
                        'company_id' => $company_id
                    ]
                );
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', 0, 'error', 
                'Failed to associate contact with company: ' . $e->getMessage(), [
                    'contact_id' => $contact_id,
                    'company_id' => $company_id,
                    'exception' => $e->getMessage()
                ]
            );

            return [
                'success' => false,
                'error' => 'Failed to associate contact with company: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get contact properties for field mapping
     */
    public function getContactProperties(string $access_token): array {
        try {
            $result = $this->api_client->getContactProperties($access_token);
            
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
                'error' => 'Failed to get contact properties: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate contact data
     */
    public function validateContactData(array $contact_data): array {
        $errors = [];

        // Check if email is present
        if (empty($contact_data['properties']['email'])) {
            $errors[] = 'Email is required for contact creation';
        }

        // Validate email format
        if (!empty($contact_data['properties']['email']) && !is_email($contact_data['properties']['email'])) {
            $errors[] = 'Invalid email format';
        }

        // Check if we have at least one property
        if (empty($contact_data['properties'])) {
            $errors[] = 'At least one contact property is required';
        }

        return $errors;
    }
} 