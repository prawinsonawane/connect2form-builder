<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\Logger;

/**
 * HubSpot Settings Manager
 * 
 * Handles all settings and configuration operations
 */
class HubspotSettingsManager {

    private $logger;

    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Get authentication fields for HubSpot
     */
    public function getAuthFields(): array {
        return [
            [
                'id' => 'access_token',
                'label' => 'Access Token',
                'type' => 'password',
                'required' => true,
                'description' => 'Your HubSpot private app access token'
            ],
            [
                'id' => 'portal_id',
                'label' => 'Portal ID',
                'type' => 'text',
                'required' => true,
                'description' => 'Your HubSpot portal ID'
            ]
        ];
    }

    /**
     * Get settings fields for HubSpot
     */
    public function getSettingsFields(): array {
        return [
            [
                'id' => 'object_type',
                'label' => 'Object Type',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'contacts' => 'Contacts',
                    'deals' => 'Deals',
                    'companies' => 'Companies',
                    'custom' => 'Custom Objects'
                ],
                'description' => 'Select the HubSpot object type to create'
            ],
            [
                'id' => 'custom_object_name',
                'label' => 'Custom Object Name',
                'type' => 'text',
                'required' => false,
                'description' => 'Name of the custom object (required if Object Type is Custom Objects)',
                'conditional' => [
                    'field' => 'object_type',
                    'value' => 'custom'
                ]
            ],
            [
                'id' => 'action_type',
                'label' => 'Action Type',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'create_or_update' => 'Create or Update',
                    'create_only' => 'Create Only',
                    'update_only' => 'Update Only'
                ],
                'description' => 'Select how to handle existing records'
            ],
            [
                'id' => 'workflow_enabled',
                'label' => 'Enable Workflow Enrollment',
                'type' => 'checkbox',
                'required' => false,
                'description' => 'Enroll contacts in HubSpot workflows'
            ],
            [
                'id' => 'workflow_id',
                'label' => 'Workflow ID',
                'type' => 'text',
                'required' => false,
                'description' => 'ID of the workflow to enroll contacts in',
                'conditional' => [
                    'field' => 'workflow_enabled',
                    'value' => '1'
                ]
            ]
        ];
    }

    /**
     * Get available actions for HubSpot
     */
    public function getAvailableActions(): array {
        return [
            'create_contact' => [
                'label' => 'Create Contact',
                'description' => 'Create a new contact in HubSpot',
                'object_type' => 'contacts'
            ],
            'update_contact' => [
                'label' => 'Update Contact',
                'description' => 'Update an existing contact in HubSpot',
                'object_type' => 'contacts'
            ],
            'create_deal' => [
                'label' => 'Create Deal',
                'description' => 'Create a new deal in HubSpot',
                'object_type' => 'deals'
            ],
            'update_deal' => [
                'label' => 'Update Deal',
                'description' => 'Update an existing deal in HubSpot',
                'object_type' => 'deals'
            ],
            'create_company' => [
                'label' => 'Create Company',
                'description' => 'Create a new company in HubSpot',
                'object_type' => 'companies'
            ],
            'update_company' => [
                'label' => 'Update Company',
                'description' => 'Update an existing company in HubSpot',
                'object_type' => 'companies'
            ],
            'create_custom_object' => [
                'label' => 'Create Custom Object',
                'description' => 'Create a new custom object in HubSpot',
                'object_type' => 'custom'
            ],
            'update_custom_object' => [
                'label' => 'Update Custom Object',
                'description' => 'Update an existing custom object in HubSpot',
                'object_type' => 'custom'
            ],
            'enroll_workflow' => [
                'label' => 'Enroll in Workflow',
                'description' => 'Enroll contact in HubSpot workflow',
                'object_type' => 'workflow'
            ]
        ];
    }

    /**
     * Get field mapping for HubSpot
     */
    public function getFieldMapping(string $action): array {
        $mappings = [];

        switch ($action) {
            case 'create_contact':
            case 'update_contact':
                $mappings = [
                    'email' => 'Email',
                    'firstname' => 'First Name',
                    'lastname' => 'Last Name',
                    'phone' => 'Phone',
                    'company' => 'Company',
                    'jobtitle' => 'Job Title',
                    'website' => 'Website',
                    'address' => 'Address',
                    'city' => 'City',
                    'state' => 'State',
                    'zip' => 'ZIP Code',
                    'country' => 'Country'
                ];
                break;

            case 'create_deal':
            case 'update_deal':
                $mappings = [
                    'dealname' => 'Deal Name',
                    'amount' => 'Amount',
                    'dealstage' => 'Deal Stage',
                    'pipeline' => 'Pipeline',
                    'closedate' => 'Close Date',
                    'description' => 'Description',
                    'dealtype' => 'Deal Type'
                ];
                break;

            case 'create_company':
            case 'update_company':
                $mappings = [
                    'name' => 'Company Name',
                    'domain' => 'Domain',
                    'phone' => 'Phone',
                    'address' => 'Address',
                    'city' => 'City',
                    'state' => 'State',
                    'zip' => 'ZIP Code',
                    'country' => 'Country',
                    'industry' => 'Industry',
                    'description' => 'Description'
                ];
                break;

            case 'create_custom_object':
            case 'update_custom_object':
                // Custom object mappings will be populated dynamically
                $mappings = [];
                break;

            case 'enroll_workflow':
                $mappings = [
                    'email' => 'Email (Required)',
                    'firstname' => 'First Name',
                    'lastname' => 'Last Name'
                ];
                break;
        }

        return $mappings;
    }

    /**
     * Validate HubSpot settings
     */
    public function validateSettings(array $settings): array {
        $errors = [];

        // Validate required fields
        if (empty($settings['access_token'])) {
            $errors[] = 'Access token is required';
        }

        if (empty($settings['portal_id'])) {
            $errors[] = 'Portal ID is required';
        }

        // Validate portal ID format
        if (!empty($settings['portal_id']) && !is_numeric($settings['portal_id'])) {
            $errors[] = 'Portal ID must be numeric';
        }

        // Validate object type
        $valid_object_types = ['contacts', 'deals', 'companies', 'custom'];
        if (!empty($settings['object_type']) && !in_array($settings['object_type'], $valid_object_types)) {
            $errors[] = 'Invalid object type';
        }

        // Validate custom object name when object type is custom
        if (!empty($settings['object_type']) && $settings['object_type'] === 'custom' && empty($settings['custom_object_name'])) {
            $errors[] = 'Custom object name is required when object type is custom';
        }

        // Validate action type
        $valid_action_types = ['create_or_update', 'create_only', 'update_only'];
        if (!empty($settings['action_type']) && !in_array($settings['action_type'], $valid_action_types)) {
            $errors[] = 'Invalid action type';
        }

        // Validate workflow settings
        if (!empty($settings['workflow_enabled']) && empty($settings['workflow_id'])) {
            $errors[] = 'Workflow ID is required when workflow enrollment is enabled';
        }

        if (!empty($settings['workflow_id']) && !is_numeric($settings['workflow_id'])) {
            $errors[] = 'Workflow ID must be numeric';
        }

        return $errors;
    }

    /**
     * Test HubSpot connection
     */
    public function testConnection(array $credentials): array {
        try {
            // Validate credentials
            if (empty($credentials['access_token']) || empty($credentials['portal_id'])) {
                return [
                    'success' => false,
                    'error' => 'Access token and Portal ID are required'
                ];
            }

            // Test API connection
            $api_client = new HubspotApiClient();
            $result = $api_client->testConnection($credentials['access_token'], $credentials['portal_id']);

            if ($result['success']) {
                // Temporarily disable logging to avoid database errors
                // $this->logger->logIntegration('hubspot', 0, 'success', 
                //     'HubSpot connection test successful', [
                //         'portal_id' => $credentials['portal_id']
                //     ]
                // );
            } else {
                // Temporarily disable logging to avoid database errors
                // $this->logger->logIntegration('hubspot', 0, 'error', 
                //     'HubSpot connection test failed: ' . ($result['error'] ?? 'Unknown error'), [
                //         'portal_id' => $credentials['portal_id'],
                //         'error' => $result['error'] ?? 'Unknown error'
                //     ]
                // );
            }

            return $result;

        } catch (\Exception $e) {
            // Temporarily disable logging to avoid database errors
            // $this->logger->logIntegration('hubspot', 0, 'error', 
            //     'Exception in HubSpot connection test: ' . $e->getMessage(), [
            //         'portal_id' => $credentials['portal_id'] ?? null,
            //         'exception' => $e->getMessage()
            //     ]
            // );

            return [
                'success' => false,
                'error' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get HubSpot properties for field mapping
     */
    public function getProperties(string $object_type, string $access_token, string $custom_object_name = ''): array {
        try {
            $api_client = new HubspotApiClient();

            switch ($object_type) {
                case 'contacts':
                    return $api_client->getContactProperties($access_token);

                case 'deals':
                    return $api_client->getDealProperties($access_token);

                case 'companies':
                    return $api_client->getCompanyProperties($access_token);

                case 'custom':
                    if (empty($custom_object_name)) {
                        return [
                            'success' => false,
                            'error' => 'Custom object name is required'
                        ];
                    }
                    return $api_client->getCustomObjectProperties($access_token, $custom_object_name);

                default:
                    return [
                        'success' => false,
                        'error' => 'Invalid object type'
                    ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get properties: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get HubSpot workflows
     */
    public function getWorkflows(string $access_token): array {
        try {
            $api_client = new HubspotApiClient();
            return $api_client->getWorkflows($access_token);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get workflows: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get HubSpot custom objects
     */
    public function getCustomObjects(string $access_token): array {
        try {
            $api_client = new HubspotApiClient();
            return $api_client->getCustomObjects($access_token);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get custom objects: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format settings for display
     */
    public function formatSettings(array $settings): array {
        $formatted = [];

        // Format authentication settings
        if (!empty($settings['access_token'])) {
            $formatted['access_token'] = substr($settings['access_token'], 0, 10) . '...';
        }

        if (!empty($settings['portal_id'])) {
            $formatted['portal_id'] = $settings['portal_id'];
        }

        // Format object settings
        if (!empty($settings['object_type'])) {
            $object_types = [
                'contacts' => 'Contacts',
                'deals' => 'Deals',
                'companies' => 'Companies',
                'custom' => 'Custom Objects'
            ];
            $formatted['object_type'] = $object_types[$settings['object_type']] ?? $settings['object_type'];
        }

        if (!empty($settings['custom_object_name'])) {
            $formatted['custom_object_name'] = $settings['custom_object_name'];
        }

        // Format action settings
        if (!empty($settings['action_type'])) {
            $action_types = [
                'create_or_update' => 'Create or Update',
                'create_only' => 'Create Only',
                'update_only' => 'Update Only'
            ];
            $formatted['action_type'] = $action_types[$settings['action_type']] ?? $settings['action_type'];
        }

        // Format workflow settings
        if (!empty($settings['workflow_enabled'])) {
            $formatted['workflow_enabled'] = 'Yes';
        }

        if (!empty($settings['workflow_id'])) {
            $formatted['workflow_id'] = $settings['workflow_id'];
        }

        return $formatted;
    }
} 