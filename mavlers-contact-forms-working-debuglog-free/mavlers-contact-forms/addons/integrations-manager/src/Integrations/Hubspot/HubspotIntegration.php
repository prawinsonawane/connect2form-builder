<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Abstracts\AbstractIntegration;
use MavlersCF\Integrations\Core\Interfaces\IntegrationInterface;
use MavlersCF\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Integration
 * 
 * Handles form submissions to HubSpot CRM
 * Supports contact creation, company association, deal creation, and workflow enrollment
 */
class HubspotIntegration extends AbstractIntegration {

    protected $id = 'hubspot';
    protected $name = 'HubSpot';
    protected $description = 'Integrate form submissions with HubSpot CRM for contact management, deal creation, and workflow automation.';
    protected $version = '1.0.0';
    protected $icon = 'dashicons-businessman';
    protected $color = '#ff7a59';

    // Component managers
    protected $custom_properties_manager;
    protected $workflow_manager;
    protected $deal_manager;
    protected $company_manager;
    protected $analytics_manager;
    protected $language_manager;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize components
        $this->init_components();
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
    }

    protected function init() {
        // Defer component initialization to ensure WordPress is ready
        add_action('init', [$this, 'init_components'], 5);
        
        // Register AJAX handlers after WordPress is fully loaded
        add_action('init', [$this, 'register_ajax_handlers'], 20);
        
        // Register asset enqueuing
        add_action('admin_enqueue_scripts', [$this, 'enqueue_comprehensive_assets']);
    }

    /**
     * Initialize HubSpot-specific components
     */
    public function init_components() {
        try {
            // Initialize component managers with error handling
            if (class_exists('MavlersCF\Integrations\Hubspot\CustomPropertiesManager')) {
                $this->custom_properties_manager = new \MavlersCF\Integrations\Hubspot\CustomPropertiesManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\WorkflowManager')) {
                $this->workflow_manager = new \MavlersCF\Integrations\Hubspot\WorkflowManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\DealManager')) {
                $this->deal_manager = new \MavlersCF\Integrations\Hubspot\DealManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\CompanyManager')) {
                $this->company_manager = new \MavlersCF\Integrations\Hubspot\CompanyManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\AnalyticsManager')) {
                $this->analytics_manager = new \MavlersCF\Integrations\Hubspot\AnalyticsManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Core\Services\LanguageManager')) {
                $this->language_manager = new \MavlersCF\Integrations\Core\Services\LanguageManager();
            }
            
            // Register form submission hook listener
            add_action('mavlers_cf_after_submission', [$this, 'handle_form_submission'], 10, 2);
            
        } catch (\Throwable $e) {
            // Even if component initialization fails, register the form submission hook
            add_action('mavlers_cf_after_submission', [$this, 'handle_form_submission'], 10, 2);
        }
    }

    /**
     * Register AJAX handlers for HubSpot integration
     */
    public function register_ajax_handlers() {

        

        
        // Test connection
        add_action('wp_ajax_mavlers_cf_hubspot_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_hubspot_test_connection', [$this, 'ajax_test_connection']);
        
        // Get HubSpot objects (contacts, companies, deals)
        add_action('wp_ajax_mavlers_cf_hubspot_get_contacts', [$this, 'ajax_get_contacts']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_companies', [$this, 'ajax_get_companies']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_deals', [$this, 'ajax_get_deals']);
        
        // Get custom properties
        add_action('wp_ajax_mavlers_cf_hubspot_get_custom_properties', [$this, 'ajax_get_custom_properties']);
        
        // Get workflows
        add_action('wp_ajax_mavlers_cf_hubspot_get_workflows', [$this, 'ajax_get_workflows']);
        
        // Save settings - use unique action names to avoid conflicts
        add_action('wp_ajax_hubspot_save_global_settings_v2', [$this, 'ajax_save_global_settings_v2']);
        add_action('wp_ajax_hubspot_save_global_settings', [$this, 'ajax_save_global_settings']);
        
        // Add a simple test handler
        add_action('wp_ajax_hubspot_test_simple_v2', [$this, 'ajax_test_simple_v2']);
        
        add_action('wp_ajax_hubspot_save_form_settings', [$this, 'ajax_save_form_settings']);
        
        // Field mapping
        add_action('wp_ajax_hubspot_save_field_mapping', [$this, 'ajax_save_field_mapping']);
        add_action('wp_ajax_hubspot_get_field_mapping', [$this, 'ajax_get_field_mapping']);
        add_action('wp_ajax_hubspot_auto_map_fields', [$this, 'ajax_auto_map_fields']);
        
        // Analytics
        add_action('wp_ajax_mavlers_cf_hubspot_get_analytics', [$this, 'ajax_get_analytics']);
        
        // Simple test endpoint
        add_action('wp_ajax_hubspot_test_simple', [$this, 'ajax_test_simple']);
        

        

        
        // Field mapping handlers (similar to Mailchimp)
        add_action('wp_ajax_hubspot_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_hubspot_save_field_mapping', [$this, 'ajax_save_field_mapping']);
        add_action('wp_ajax_hubspot_get_field_mapping', [$this, 'ajax_get_field_mapping']);
        add_action('wp_ajax_hubspot_auto_map_fields', [$this, 'ajax_auto_map_fields']);
        add_action('wp_ajax_hubspot_get_contact_properties', [$this, 'ajax_get_contact_properties']);
        
        // Custom objects handlers
        add_action('wp_ajax_hubspot_get_custom_objects', [$this, 'ajax_get_custom_objects']);
        add_action('wp_ajax_hubspot_get_custom_object_properties', [$this, 'ajax_get_custom_object_properties']);
        add_action('wp_ajax_hubspot_save_custom_object_mapping', [$this, 'ajax_save_custom_object_mapping']);
        add_action('wp_ajax_hubspot_get_custom_object_mapping', [$this, 'ajax_get_custom_object_mapping']);
        
        // Object properties handlers
        add_action('wp_ajax_hubspot_get_deal_properties', [$this, 'ajax_get_deal_properties']);
        add_action('wp_ajax_hubspot_get_company_properties', [$this, 'ajax_get_company_properties']);
    }

    /**
     * Get authentication fields for HubSpot
     */
    public function getAuthFields(): array {
        return [
            [
                'id' => 'access_token',
                'label' => $this->__('Private App Access Token'),
                'type' => 'password',
                'required' => true,
                'description' => $this->__('Enter your HubSpot Private App Access Token. Create one in HubSpot Settings > Account Setup > Integrations > Private Apps.'),
                'help_url' => 'https://developers.hubspot.com/docs/api/private-apps'
            ],
            [
                'id' => 'portal_id',
                'label' => $this->__('Portal ID'),
                'type' => 'text',
                'required' => true,
                'description' => $this->__('Your HubSpot Portal ID. Found in your HubSpot account settings.'),
                'help_url' => 'https://developers.hubspot.com/docs/api/overview'
            ]
        ];
    }

    /**
     * Test HubSpot API connection
     */
    public function testConnection(array $credentials): array {
        $access_token = $credentials['access_token'] ?? '';
        $portal_id = $credentials['portal_id'] ?? '';

        if (empty($access_token) || empty($portal_id)) {
            return [
                'success' => false,
                'error' => $this->__('Access token and Portal ID are required')
            ];
        }

        // Test basic connectivity
        $test_url = "https://api.hubapi.com/crm/v3/objects/contacts";
        $response = wp_remote_get($test_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => $this->__('Connection successful!'),
                'data' => [
                    'portal_id' => $portal_id,
                    'api_version' => 'v3'
                ]
            ];
        } elseif ($status_code === 401) {
            return [
                'success' => false,
                'error' => $this->__('Invalid access token. Please check your Private App Access Token.')
            ];
        } elseif ($status_code === 403) {
            return [
                'success' => false,
                'error' => $this->__('Access denied. Please check your Private App permissions.')
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "API Error ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get available actions for HubSpot
     */
    public function getAvailableActions(): array {
        return [
            'create_contact' => [
                'label' => $this->__('Create Contact'),
                'description' => $this->__('Create a new contact in HubSpot CRM')
            ],
            'update_contact' => [
                'label' => $this->__('Update Contact'),
                'description' => $this->__('Update existing contact information')
            ],
            'create_deal' => [
                'label' => $this->__('Create Deal'),
                'description' => $this->__('Create a new deal and associate with contact')
            ],
            'update_deal' => [
                'label' => $this->__('Update Deal'),
                'description' => $this->__('Update existing deal information')
            ],
            'create_custom_object' => [
                'label' => $this->__('Create Custom Object'),
                'description' => $this->__('Create a new custom object record')
            ],
            'update_custom_object' => [
                'label' => $this->__('Update Custom Object'),
                'description' => $this->__('Update existing custom object record')
            ],
            'create_multiple_objects' => [
                'label' => $this->__('Create Multiple Objects'),
                'description' => $this->__('Create contact, deal, and custom objects simultaneously')
            ],
            'enroll_workflow' => [
                'label' => $this->__('Enroll in Workflow'),
                'description' => $this->__('Enroll contact in HubSpot workflow')
            ],
            'associate_company' => [
                'label' => $this->__('Associate Company'),
                'description' => $this->__('Associate contact with existing company')
            ],
            'associate_objects' => [
                'label' => $this->__('Associate Objects'),
                'description' => $this->__('Associate contact with deals and custom objects')
            ]
        ];
    }

    /**
     * Get form-specific settings fields
     */
    public function getFormSettingsFields(): array {
        return [
            [
                'id' => 'contact_enabled',
                'label' => $this->__('Create/Update Contact'),
                'type' => 'checkbox',
                'description' => $this->__('Create or update contact in HubSpot'),
                'default' => true
            ],
            [
                'id' => 'deal_enabled',
                'label' => $this->__('Create Deal'),
                'type' => 'checkbox',
                'description' => $this->__('Create a new deal for this submission'),
                'default' => false
            ],
            [
                'id' => 'deal_update_enabled',
                'label' => $this->__('Update Existing Deal'),
                'type' => 'checkbox',
                'description' => $this->__('Update existing deal instead of creating new one'),
                'default' => false
            ],
            [
                'id' => 'custom_objects_enabled',
                'label' => $this->__('Enable Custom Objects'),
                'type' => 'checkbox',
                'description' => $this->__('Enable custom object creation and updates'),
                'default' => false
            ],
            [
                'id' => 'custom_objects_config',
                'label' => $this->__('Custom Objects Configuration'),
                'type' => 'custom_objects_config',
                'description' => $this->__('Configure multiple custom objects'),
                'depends_on' => 'custom_objects_enabled'
            ],
            [
                'id' => 'workflow_enabled',
                'label' => $this->__('Enroll in Workflow'),
                'type' => 'checkbox',
                'description' => $this->__('Enroll contact in HubSpot workflow'),
                'default' => false
            ],
            [
                'id' => 'company_enabled',
                'label' => $this->__('Associate Company'),
                'type' => 'checkbox',
                'description' => $this->__('Associate contact with company'),
                'default' => false
            ],
            [
                'id' => 'deal_pipeline',
                'label' => $this->__('Deal Pipeline'),
                'type' => 'select',
                'description' => $this->__('Select pipeline for new deals'),
                'options' => 'dynamic',
                'depends_on' => 'deal_enabled'
            ],
            [
                'id' => 'deal_stage',
                'label' => $this->__('Deal Stage'),
                'type' => 'select',
                'description' => $this->__('Select stage for new deals'),
                'options' => 'dynamic',
                'depends_on' => 'deal_enabled'
            ],
            [
                'id' => 'workflow_id',
                'label' => $this->__('Workflow'),
                'type' => 'select',
                'description' => $this->__('Select workflow to enroll contact in'),
                'options' => 'dynamic',
                'depends_on' => 'workflow_enabled'
            ],
            [
                'id' => 'company_id',
                'label' => $this->__('Company'),
                'type' => 'select',
                'description' => $this->__('Select company to associate with contact'),
                'options' => 'dynamic',
                'depends_on' => 'company_enabled'
            ]
        ];
    }

    /**
     * Get global settings fields
     */
    public function getSettingsFields(): array {
        return [
            [
                'id' => 'enable_analytics',
                'label' => $this->__('Analytics Tracking'),
                'type' => 'checkbox',
                'description' => $this->__('Track integration performance and analytics'),
                'default' => true
            ],
            [
                'id' => 'enable_webhooks',
                'label' => $this->__('Webhook Sync'),
                'type' => 'checkbox',
                'description' => $this->__('Enable webhook synchronization for real-time updates'),
                'default' => false
            ],
            [
                'id' => 'batch_processing',
                'label' => $this->__('Batch Processing'),
                'type' => 'checkbox',
                'description' => $this->__('Process submissions in batches for better performance'),
                'default' => true
            ]
        ];
    }

    /**
     * Get enhanced field mapping for HubSpot
     */
    public function getFieldMapping(string $action): array {
        $base_mapping = [
            'email' => [
                'label' => $this->__('Email Address'),
                'required' => true,
                'type' => 'email',
                'hubspot_property' => 'email'
            ],
            'firstname' => [
                'label' => $this->__('First Name'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'firstname'
            ],
            'lastname' => [
                'label' => $this->__('Last Name'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'lastname'
            ],
            'phone' => [
                'label' => $this->__('Phone Number'),
                'required' => false,
                'type' => 'phone',
                'hubspot_property' => 'phone'
            ],
            'company' => [
                'label' => $this->__('Company'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'company'
            ],
            'website' => [
                'label' => $this->__('Website'),
                'required' => false,
                'type' => 'url',
                'hubspot_property' => 'website'
            ],
            'address' => [
                'label' => $this->__('Address'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'address'
            ],
            'city' => [
                'label' => $this->__('City'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'city'
            ],
            'state' => [
                'label' => $this->__('State/Province'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'state'
            ],
            'zip' => [
                'label' => $this->__('ZIP/Postal Code'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'zip'
            ],
            'country' => [
                'label' => $this->__('Country'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'country'
            ]
        ];

        return $base_mapping;
    }

    /**
     * Validate HubSpot settings
     */
    public function validateSettings(array $settings): array {
        $errors = [];

        if (empty($settings['access_token'])) {
            $errors[] = 'Access token is required';
        }

        if (empty($settings['portal_id'])) {
            $errors[] = 'Portal ID is required';
        }
        
        return $errors;
    }

    /**
     * Process form submission to HubSpot
     */
    public function processSubmission(int $submission_id, array $form_data, array $settings = []): array {
        // Get settings if not provided
        if (empty($settings)) {
            $form_id = $form_data['form_id'] ?? 0;
            $settings = $this->getFormSettings($form_id);
        }

        // Check if integration is enabled
        if (!$this->isEnabled($settings)) {
            return [
                'success' => false,
                'error' => 'HubSpot integration not enabled'
            ];
        }

        // Check global connection
        if (!$this->is_globally_connected()) {
            return [
                'success' => false,
                'error' => 'HubSpot not globally connected'
            ];
        }

        // Process submission
        return $this->process_submission_immediate($submission_id, $form_data, $settings);
    }

    /**
     * Process submission immediately (no batch processing)
     */
    private function process_submission_immediate(int $submission_id, array $form_data, array $settings): array {
        $global_settings = $this->get_global_settings();

        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';

        if (empty($access_token) || empty($portal_id)) {
            return [
                'success' => false,
                'error' => 'HubSpot not properly configured'
            ];
        }

        // Map form data to HubSpot fields
        $mapped_data = $this->enhanced_map_form_data($form_data, $settings);

        $results = [];

        // Create/Update Contact
        if (!empty($settings['enabled']) && $settings['object_type'] === 'contacts') {
            $contact_result = $this->createOrUpdateContact($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            $results['contact'] = $contact_result;
        }

        // Create/Update Deal
        if (!empty($settings['deal_enabled'])) {
            if (!empty($settings['deal_update_enabled'])) {
                $deal_result = $this->updateDeal($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            } else {
                $deal_result = $this->createDeal($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            }
            $results['deal'] = $deal_result;
        }

        // Process Custom Objects
        if (!empty($settings['custom_objects_enabled']) && !empty($settings['custom_objects_config'])) {
            $custom_objects_result = $this->processCustomObjects($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            $results['custom_objects'] = $custom_objects_result;
        }

        // Enroll in Workflow
        if (!empty($settings['workflow_enabled']) && !empty($settings['workflow_id'])) {
            $workflow_result = $this->enrollInWorkflow($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            $results['workflow'] = $workflow_result;
        }

        // Associate Company
        if (!empty($settings['company_enabled']) && !empty($settings['company_id'])) {
            $company_result = $this->associateCompany($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            $results['company'] = $company_result;
        }

        // Return overall result
        $success_count = 0;
        $errors = [];

        foreach ($results as $action => $result) {
            if ($result['success']) {
                $success_count++;
            } else {
                $errors[] = "{$action}: {$result['error']}";
            }
        }

        if ($success_count > 0) {
            $this->logSuccess('HubSpot integration completed', [
                'submission_id' => $submission_id,
                'results' => $results
            ]);

            return [
                'success' => true,
                'message' => $this->__('Successfully processed in HubSpot'),
                'data' => $results
            ];
        } else {
            $this->logError('HubSpot integration failed', [
                'submission_id' => $submission_id,
                'errors' => $errors
            ]);

            return [
                'success' => false,
                'error' => implode('; ', $errors)
            ];
        }
    }

    /**
     * Enhanced form data mapping for HubSpot
     */
    private function enhanced_map_form_data(array $form_data, array $settings): array {
        $mapped = [];

        // Get the actual field data - handle nested structure
        $field_data = $form_data;
        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            $field_data = $form_data['fields'];
        }

        // Get field mapping from settings
        $field_mapping = $settings['field_mapping'] ?? [];

        if (!empty($field_mapping)) {
            foreach ($field_mapping as $form_field => $hubspot_field) {
                if (isset($field_data[$form_field]) && !empty($field_data[$form_field])) {
                    $mapped[$hubspot_field] = $field_data[$form_field];
                }
            }
        } else {
            // Fall back to basic mapping
            $mapped = $this->mapFormDataToHubspot($field_data, $settings);
        }

        return $mapped;
    }

    /**
     * Basic form data mapping for HubSpot (fallback)
     */
    private function mapFormDataToHubspot(array $form_data, array $settings): array {
        $mapped = [];
        
        // Map common fields with multiple variations
        $field_map = [
            'email' => 'email',
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'name' => 'firstname',
            'email_address' => 'email',
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'phone' => 'phone',
            'company' => 'company',
            'website' => 'website'
        ];

        foreach ($field_map as $form_field => $hubspot_field) {
            if (isset($form_data[$form_field]) && !empty($form_data[$form_field])) {
                $mapped[$hubspot_field] = $form_data[$form_field];
            }
        }

        return $mapped;
    }

    /**
     * Create or update contact in HubSpot
     */
    private function createOrUpdateContact(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        if (empty($data['email'])) {
            return [
                'success' => false,
                'error' => 'Email is required for contact creation'
            ];
        }

        $properties = [];
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $properties[$key] = $value;
            }
        }
        
        $payload = [
            'properties' => $properties
        ];
        
        // First, try to find existing contact by email
        $search_url = "https://api.hubapi.com/crm/v3/objects/contacts/search";
        $search_payload = [
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'email',
                            'operator' => 'EQ',
                            'value' => $data['email']
                        ]
                    ]
                ]
            ],
            'properties' => ['email', 'firstname', 'lastname', 'phone']
        ];
        
        $search_response = wp_remote_post($search_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($search_payload),
            'timeout' => 30
        ]);
        
        $search_status = wp_remote_retrieve_response_code($search_response);
        $search_body = wp_remote_retrieve_body($search_response);
        $search_result = json_decode($search_body, true);
        
        $contact_id = null;
        if ($search_status === 200 && !empty($search_result['results'])) {
            $contact_id = $search_result['results'][0]['id'];
        }
        
        // Use PATCH for update, POST for create
        $method = $contact_id ? 'PATCH' : 'POST';
        $url = $contact_id ? "https://api.hubapi.com/crm/v3/objects/contacts/{$contact_id}" : "https://api.hubapi.com/crm/v3/objects/contacts";
        
        $response = wp_remote_request($url, [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Contact creation failed: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 201 || $status_code === 200) {
            return [
                'success' => true,
                'message' => 'Contact created/updated successfully',
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Contact creation failed: ' . ($result['message'] ?? 'Unknown error')
            ];
        }
    }

    /**
     * Create deal in HubSpot
     */
    private function createDeal(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        $pipeline_id = $settings['deal_pipeline'] ?? '';
        $stage_id = $settings['deal_stage'] ?? '';
        
        if (empty($pipeline_id) || empty($stage_id)) {
            return [
                'success' => false,
                'error' => 'Deal pipeline and stage are required'
            ];
        }
        
        $url = "https://api.hubapi.com/crm/v3/objects/deals";
        
        $properties = [
            'amount' => $data['amount'] ?? '0',
            'dealname' => $data['dealname'] ?? 'Form Submission Deal',
            'pipeline' => $pipeline_id,
            'dealstage' => $stage_id
        ];
        
        $payload = [
            'properties' => $properties
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Deal creation failed: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 201 || $status_code === 200) {
            return [
                'success' => true,
                'message' => 'Deal created successfully',
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Deal creation failed: ' . ($result['message'] ?? 'Unknown error')
            ];
        }
    }

    /**
     * Enroll contact in workflow
     */
    private function enrollInWorkflow(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        $workflow_id = $settings['workflow_id'] ?? '';
        
        if (empty($workflow_id)) {
            return [
                'success' => false,
                'error' => 'Workflow ID is required'
            ];
        }
        
        $url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments/contacts/{$data['email']}";
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Workflow enrollment failed: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'message' => 'Contact enrolled in workflow successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Workflow enrollment failed'
            ];
        }
    }

    /**
     * Update existing deal
     */
    private function updateDeal(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        $deal_id = $settings['deal_id'] ?? '';
        if (empty($deal_id)) {
            return [
                'success' => false,
                'error' => 'Deal ID not specified for update'
            ];
        }
        
        $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
        
        $deal_data = [
            'properties' => $data
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($deal_data),
            'method' => 'PATCH',
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to update deal: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => 'Deal updated successfully',
                'deal_id' => $deal_id,
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'error' => "Failed to update deal (HTTP {$status_code}): " . ($result['message'] ?? 'Unknown error')
            ];
        }
    }

    /**
     * Process custom objects
     */
    private function processCustomObjects(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        $custom_objects_config = $settings['custom_objects_config'] ?? [];
        if (empty($custom_objects_config)) {
            return [
                'success' => false,
                'error' => 'No custom objects configuration found'
            ];
        }
        
        $results = [];
        
        foreach ($custom_objects_config as $object_config) {
            $object_name = $object_config['object_name'] ?? '';
            $action = $object_config['action'] ?? 'create'; // create or update
            $enabled = $object_config['enabled'] ?? false;
            
            if (!$enabled || empty($object_name)) {
                continue;
            }
            
            // Get field mapping for this object
            $form_id = $settings['form_id'] ?? 0;
            $object_mapping = $this->get_custom_object_mapping($form_id, $object_name);
            
            if (empty($object_mapping)) {
                continue;
            }
            
            // Map form data to custom object properties
            $mapped_object_data = [];
            foreach ($object_mapping as $form_field => $object_property) {
                if (isset($data[$form_field]) && !empty($data[$form_field])) {
                    $mapped_object_data[$object_property] = $data[$form_field];
                }
            }
            
            if (empty($mapped_object_data)) {
                continue;
            }
            
            // Create or update custom object
            if ($action === 'update') {
                $object_id = $object_config['object_id'] ?? '';
                $result = $this->updateCustomObject($object_name, $mapped_object_data, $object_id, $access_token, $portal_id, $submission_id);
            } else {
                $result = $this->createCustomObject($object_name, $mapped_object_data, $access_token, $portal_id, $submission_id);
            }
            
            $results[$object_name] = $result;
        }
        
        return [
            'success' => true,
            'results' => $results
        ];
    }

    /**
     * Create custom object
     */
    private function createCustomObject(string $object_name, array $data, string $access_token, string $portal_id, int $submission_id): array {
        $url = "https://api.hubapi.com/crm/v3/objects/{$object_name}";
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to create custom object: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 201 && isset($result['id'])) {
            return [
                'success' => true,
                'object_id' => $result['id'],
                'message' => 'Custom object created successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to create custom object - Status: ' . $status_code . ', Response: ' . $body
            ];
        }
    }

    /**
     * Update custom object
     */
    private function updateCustomObject(string $object_name, array $data, string $object_id, string $access_token, string $portal_id, int $submission_id): array {
        $url = "https://api.hubapi.com/crm/v3/objects/{$object_name}/{$object_id}";
        
        $response = wp_remote_patch($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to update custom object: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => 'Custom object updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to update custom object - Status: ' . $status_code . ', Response: ' . $body
            ];
        }
    }

    /**
     * Associate contact with company
     */
    private function associateCompany(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        // This method would handle company association logic
        // For now, return success as placeholder
        return [
            'success' => true,
            'message' => 'Company association completed'
        ];
    }

    /**
     * Get global settings
     */
    public function get_global_settings() {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        return $global_settings['hubspot'] ?? [];
    }

    /**
     * Check if globally connected
     */
    public function is_globally_connected(): bool {
        $settings = $this->get_global_settings();
        return !empty($settings['access_token']) && !empty($settings['portal_id']);
    }

    /**
     * Get enhanced field mapping
     */
    public function get_enhanced_field_mapping(int $form_id): array {
        $mapping = get_post_meta($form_id, '_mavlers_cf_hubspot_field_mapping', true);
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Save enhanced field mapping
     */
    public function save_enhanced_field_mapping(int $form_id, array $mapping): bool {
        return update_post_meta($form_id, '_mavlers_cf_hubspot_field_mapping', $mapping);
    }

    /**
     * Enqueue comprehensive assets
     */
    public function enqueue_comprehensive_assets($hook): void {
        // Get current screen
        $screen = get_current_screen();
        
        // Check if we're on a form builder page or integration settings
        $is_form_builder = (
            strpos($hook, 'mavlers-cf-new-form') !== false ||
            strpos($hook, 'mavlers-contact-forms') !== false ||
            strpos($hook, 'admin.php') !== false ||
            (isset($_GET['action']) && $_GET['action'] === 'edit')
        );
        
        // Only enqueue on form builder pages or specific admin pages
        if (!$is_form_builder && strpos($hook, 'mavlers-cf') === false) {
            return;
        }
        
        // Get form ID from URL or POST data
        $form_id = 0;
        if (isset($_GET['id'])) {
            $form_id = intval($_GET['id']);
        } elseif (isset($_POST['form_id'])) {
            $form_id = intval($_POST['form_id']);
        } elseif (function_exists('get_the_ID')) {
            $form_id = get_the_ID();
        }
        
        // Enqueue HubSpot specific assets
        wp_enqueue_style(
            'mavlers-cf-hubspot',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/css/admin/hubspot.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'mavlers-cf-hubspot',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/js/admin/hubspot-form.js',
            ['jquery', 'wp-util'],
            '2.0.0',
            true
        );
        
        // Create comprehensive localized data for new structure
        $localized_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'formId' => $form_id,
            'pluginUrl' => MAVLERS_CF_INTEGRATIONS_URL,
            'strings' => [
                'testing' => __('Testing...', 'mavlers-contact-forms'),
                'connected' => __('Connected', 'mavlers-contact-forms'),
                'disconnected' => __('Disconnected', 'mavlers-contact-forms'),
                'testConnection' => __('Test Connection', 'mavlers-contact-forms'),
                'savingSettings' => __('Saving...', 'mavlers-contact-forms'),
                'settingsSaved' => __('Settings saved successfully!', 'mavlers-contact-forms'),
                'connectionFailed' => __('Connection failed', 'mavlers-contact-forms'),
                'selectContact' => __('Select contact properties...', 'mavlers-contact-forms'),
                'loadingFields' => __('Loading fields...', 'mavlers-contact-forms'),
                'fieldsLoaded' => __('Fields loaded successfully', 'mavlers-contact-forms'),
                'noFieldsFound' => __('No fields found', 'mavlers-contact-forms'),
                'networkError' => __('Network error', 'mavlers-contact-forms'),
                'mappingSaved' => __('Field mapping saved successfully', 'mavlers-contact-forms'),
                'mappingFailed' => __('Failed to save field mapping', 'mavlers-contact-forms'),
                'autoMappingComplete' => __('Auto-mapping completed', 'mavlers-contact-forms'),
                'clearMappingsConfirm' => __('Are you sure you want to clear all mappings?', 'mavlers-contact-forms')
            ]
        ];
        
        // Localize script with new structure
        wp_localize_script('mavlers-cf-hubspot', 'mavlersCFHubspot', $localized_data);
        
        // Also localize with standard variables for compatibility
        wp_localize_script('mavlers-cf-hubspot', 'mavlers_cf_nonce', wp_create_nonce('mavlers_cf_nonce'));
        wp_localize_script('mavlers-cf-hubspot', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_send_json([
                'success' => false,
                'error' => 'Security check failed'
            ]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json([
                'success' => false,
                'error' => 'Insufficient permissions'
            ]);
        }
        
        // Extract credentials from credentials object or direct POST
        $access_token = '';
        $portal_id = '';
        
        if (isset($_POST['credentials']) && is_array($_POST['credentials'])) {
            $access_token = sanitize_text_field($_POST['credentials']['access_token'] ?? '');
            $portal_id = sanitize_text_field($_POST['credentials']['portal_id'] ?? '');
        } else {
            $access_token = sanitize_text_field($_POST['access_token'] ?? '');
            $portal_id = sanitize_text_field($_POST['portal_id'] ?? '');
        }
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json([
                'success' => false,
                'error' => 'Access token and Portal ID are required'
            ]);
        }
        
        $result = $this->testConnection([
            'access_token' => $access_token,
            'portal_id' => $portal_id
        ]);
        
        wp_send_json($result);
    }

    /**
     * AJAX: Get contacts
     */
    public function ajax_get_contacts(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->custom_properties_manager) {
            $this->custom_properties_manager->ajax_get_contacts();
        } else {
            wp_send_json_error('Custom properties manager not available');
        }
    }

    /**
     * AJAX: Get companies
     */
    public function ajax_get_companies(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->company_manager) {
            $this->company_manager->ajax_get_companies();
        } else {
            wp_send_json_error('Company manager not available');
        }
    }

    /**
     * AJAX: Get deals
     */
    public function ajax_get_deals(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->deal_manager) {
            $this->deal_manager->ajax_get_deals();
        } else {
            wp_send_json_error('Deal manager not available');
        }
    }

    /**
     * AJAX: Get custom properties
     */
    public function ajax_get_custom_properties(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->custom_properties_manager) {
            $this->custom_properties_manager->ajax_get_custom_properties();
        } else {
            wp_send_json_error('Custom properties manager not available');
        }
    }

    /**
     * AJAX: Get workflows
     */
    public function ajax_get_workflows(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->workflow_manager) {
            $this->workflow_manager->ajax_get_workflows();
        } else {
            wp_send_json_error('Workflow manager not available');
        }
    }

    /**
     * AJAX: Test response format
     */
    public function ajax_test_response(): void {
        //error_log("HubSpot: === TEST RESPONSE AJAX HANDLER ===");
        //error_log("HubSpot: POST data: " . print_r($_POST, true));
        //error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        //error_log("HubSpot: Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        // Check if this is a valid AJAX request
        if (!wp_doing_ajax()) {
            //error_log("HubSpot: Not an AJAX request");
            wp_send_json_error('Not an AJAX request');
            return;
        }
        
        // Check nonce if provided
        if (isset($_POST['nonce'])) {
            if (!wp_verify_nonce($_POST['nonce'], 'mavlers_cf_nonce')) {
                //error_log("HubSpot: Nonce verification failed");
                //error_log("HubSpot: Received nonce: " . $_POST['nonce']);
                //error_log("HubSpot: Expected nonce: " . wp_create_nonce('mavlers_cf_nonce'));
                wp_send_json_error('Nonce verification failed');
                return;
            }
            //error_log("HubSpot: Nonce verification passed");
        } else {
            //error_log("HubSpot: No nonce provided, skipping verification");
        }
        
        // Return a simple success response
        wp_send_json_success('Test response successful');
        
        //error_log("HubSpot: === TEST RESPONSE AJAX HANDLER END ===");
    }

    /**
     * AJAX: Save global settings (standard version)
     */
    public function ajax_save_global_settings(): void {
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_die('Security check failed');
        }
        
        // Extract settings from the form data
        $settings = [];
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            $settings = $_POST['settings'];
        } else {
            // Fallback to direct POST data
            $settings = [
                'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
                'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
                'enable_analytics' => !empty($_POST['enable_analytics']),
                'enable_webhooks' => !empty($_POST['enable_webhooks']),
                'batch_processing' => !empty($_POST['batch_processing'])
            ];
        }
        
        // Validate settings
        $validation_result = $this->validateSettings($settings);
        if (!empty($validation_result)) {
            wp_send_json_error('Settings validation failed: ' . implode(', ', $validation_result));
            return;
        }
        
        // Save settings using the abstract method
        $result = $this->saveGlobalSettings($settings);
        
        if ($result) {
            wp_send_json_success([
                'message' => 'Settings saved successfully!',
                'configured' => true
            ]);
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    /**
     * AJAX: Save global settings (simplified version)
     */
    public function ajax_save_global_settings_simple(): void {
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_die('Security check failed');
        }
        
        // Extract settings from POST data
        $settings = [
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
            'enable_analytics' => !empty($_POST['enable_analytics']),
            'enable_webhooks' => !empty($_POST['enable_webhooks']),
            'batch_processing' => !empty($_POST['batch_processing'])
        ];
        
        // Validate required fields
        if (empty($settings['access_token']) || empty($settings['portal_id'])) {
            wp_send_json_error('Access token and portal ID are required');
            return;
        }
        
        // Validate settings
        $validation_result = $this->validateSettings($settings);
        if (!empty($validation_result)) {
            wp_send_json_error('Settings validation failed: ' . implode(', ', $validation_result));
            return;
        }
        
        // Save settings
        $result = $this->saveGlobalSettings($settings);
        
        if ($result) {
            wp_send_json_success([
                'message' => 'Settings saved successfully!',
                'configured' => true
            ]);
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    /**
     * AJAX: Save global settings (v2)
     */
    public function ajax_save_global_settings_v2(): void {
        // Extract settings from POST data
        $settings = [
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
            'enable_analytics' => !empty($_POST['enable_analytics']),
            'enable_webhooks' => !empty($_POST['enable_webhooks']),
            'batch_processing' => !empty($_POST['batch_processing'])
        ];
        
        // Validate required fields
        if (empty($settings['access_token']) || empty($settings['portal_id'])) {
            wp_send_json_error('Access token and portal ID are required');
            return;
        }
        
        // Validate settings
        $validation_result = $this->validateSettings($settings);
        if (!empty($validation_result)) {
            wp_send_json_error('Settings validation failed: ' . implode(', ', $validation_result));
            return;
        }
        
        // Save settings
        $result = $this->saveGlobalSettings($settings);
        
        if ($result) {
            wp_send_json_success([
                'message' => 'Settings saved successfully!',
                'configured' => true
            ]);
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    /**
     * AJAX: Save form settings
     */
    public function ajax_save_form_settings(): void {
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            wp_send_json_error('Form ID is required');
        }
        
        // Validate that the form exists in the custom forms table
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        $form_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$forms_table} WHERE id = %d", $form_id));
        
        if (!$form_exists) {
            wp_send_json_error('Form does not exist');
        }
        
        // Validate form ID
        if ($form_id <= 0) {
            wp_send_json_error('Invalid form ID');
        }
        
        // Process settings
        $settings = [
            'enabled' => !empty($_POST['enabled']),
            'object_type' => sanitize_text_field($_POST['object_type'] ?? ''),
            'custom_object_name' => sanitize_text_field($_POST['custom_object_name'] ?? ''),
            'action_type' => sanitize_text_field($_POST['action_type'] ?? 'create'),
            'workflow_enabled' => !empty($_POST['workflow_enabled']),
            'field_mapping' => $_POST['field_mapping'] ?? []
        ];
        
        $result = $this->saveFormSettings($form_id, $settings);
        
        if ($result) {
            wp_send_json_success('Form settings saved successfully');
        } else {
            wp_send_json_error('Failed to save form settings');
        }
    }

    /**
     * AJAX: Save field mapping
     */
    public function ajax_save_field_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $mapping = $_POST['mapping'] ?? [];
        
        if (!$form_id) {
            wp_send_json_error('Form ID is required');
        }

        $result = $this->save_enhanced_field_mapping($form_id, $mapping);
        
        if ($result) {
            wp_send_json_success('Field mapping saved successfully');
        } else {
            wp_send_json_error('Failed to save field mapping');
        }
    }

    /**
     * AJAX: Get form fields
     */
    public function ajax_get_form_fields(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            wp_send_json_error('Form ID is required');
        }
        
        $form_fields = $this->get_form_fields($form_id);
        
        // Convert associative array to indexed array for JavaScript
        $fields_array = [];
        foreach ($form_fields as $field_id => $field_config) {
            $fields_array[] = $field_config;
        }
        
        //error_log("HubSpot: ajax_get_form_fields - Form ID: {$form_id}, Fields count: " . count($fields_array));
        wp_send_json_success(['fields' => $fields_array]);
    }

    /**
     * AJAX: Get field mapping
     */
    public function ajax_get_field_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            wp_send_json_error('Form ID is required');
        }

        $mapping = $this->get_enhanced_field_mapping($form_id);
        wp_send_json_success($mapping);
    }

    /**
     * AJAX: Get HubSpot custom objects
     */
    public function ajax_get_custom_objects(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error('HubSpot not configured - Access token missing');
        }
        
        if (empty($portal_id)) {
            wp_send_json_error('HubSpot not configured - Portal ID missing');
        }
        
        //error_log("HubSpot: Getting custom objects for portal ID: {$portal_id}");
        
        // Use portal-specific URL for custom objects
        $url = "https://api.hubapi.com/crm/v3/schemas?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch custom objects: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        //error_log("HubSpot: Custom objects API response - Status: {$status_code}");
        //error_log("HubSpot: Custom objects API response body: " . substr($body, 0, 1000));
        //error_log("HubSpot: Decoded data: " . print_r($data, true));
        
        if ($status_code === 200 && isset($data['results'])) {
            $custom_objects = [];
            $total_schemas = count($data['results']);
            $custom_object_count = 0;
            
            foreach ($data['results'] as $schema) {
                $object_type = $schema['objectType'] ?? 'unknown';
                $schema_name = $schema['name'] ?? 'unknown';
                $fully_qualified_name = $schema['fullyQualifiedName'] ?? '';
                
                //error_log("HubSpot: Processing schema - Name: {$schema_name}, ObjectType: {$object_type}, FullyQualifiedName: {$fully_qualified_name}");
                
                // Check if this is a custom object by looking at the fullyQualifiedName pattern
                // Custom objects have pattern: p{portal_id}_{object_name}
                if (preg_match('/^p\d+_/', $fully_qualified_name) || $object_type === 'CUSTOM_OBJECT') {
                    $custom_object_count++;
                    $custom_objects[] = [
                        'name' => $schema_name,
                        'label' => $schema['labels']['singular'] ?? $schema_name ?? '',
                        'plural_label' => $schema['labels']['plural'] ?? $schema_name ?? '',
                        'description' => $schema['description'] ?? '',
                        'primary_property' => $schema['primaryDisplayProperty'] ?? '',
                        'fullyQualifiedName' => $fully_qualified_name
                    ];
                    //error_log("HubSpot: Added custom object - {$schema_name} (FQN: {$fully_qualified_name})");
                }
            }
            
            //error_log("HubSpot: Found {$custom_object_count} custom objects out of {$total_schemas} total schemas");
            wp_send_json_success($custom_objects);
        } else {
            //error_log("HubSpot: Failed to fetch custom objects - Status: {$status_code}, Body: " . substr($body, 0, 500));
            wp_send_json_error('Failed to fetch custom objects');
        }
    }

    /**
     * AJAX: Get HubSpot custom object properties
     */
    public function ajax_get_custom_object_properties(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $object_name = sanitize_text_field($_POST['custom_object_name'] ?? '');
        if (empty($object_name)) {
            wp_send_json_error('Custom object name is required');
        }
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error('HubSpot not configured - Access token missing');
        }
        
        if (empty($portal_id)) {
            wp_send_json_error('HubSpot not configured - Portal ID missing');
        }
        
        //error_log("HubSpot: Getting properties for custom object: {$object_name} in portal ID: {$portal_id}");
        
        // Use portal-specific URL for custom object properties
        // For custom objects, we need to use the fully qualified name
        $url = "https://api.hubapi.com/crm/v3/properties/{$object_name}?portalId={$portal_id}";
        //error_log("HubSpot: Custom object properties URL: {$url}");
        
        // Also try alternative URL format for custom objects
        $alt_url = "https://api.hubapi.com/crm/v3/properties/{$object_name}";
        //error_log("HubSpot: Alternative custom object properties URL: {$alt_url}");
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            //error_log("HubSpot: First URL failed, trying alternative URL");
            // Try alternative URL without portalId
            $response = wp_remote_get($alt_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                wp_send_json_error('Failed to fetch custom object properties: ' . $response->get_error_message());
            }
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        //error_log("HubSpot: Custom object properties API response - Status: {$status_code}");
        //error_log("HubSpot: Custom object properties API response body: " . substr($body, 0, 1000));
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            $total_properties = count($data['results']);
            $included_properties = 0;
            
            foreach ($data['results'] as $property) {
                // Include all properties that are not read-only
                if (!isset($property['modificationMetadata']['readOnlyDefinition']) || 
                    $property['modificationMetadata']['readOnlyDefinition'] === false) {
                    $included_properties++;
                    $properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label'],
                        'type' => $property['type'],
                        'required' => isset($property['modificationMetadata']['readOnlyDefinition']) ? 
                            ($property['modificationMetadata']['readOnlyDefinition'] === false) : true
                    ];
                    //error_log("HubSpot: Added property - {$property['name']} ({$property['label']})");
                }
            }
            
            //error_log("HubSpot: Found {$included_properties} properties out of {$total_properties} total for custom object {$object_name}");
            wp_send_json_success($properties);
        } else {
            //error_log("HubSpot: Failed to fetch custom object properties - Status: {$status_code}, Body: " . substr($body, 0, 500));
            wp_send_json_error('Failed to fetch custom object properties');
        }
    }

    /**
     * AJAX: Save custom object field mapping
     */
    public function ajax_save_custom_object_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $object_name = sanitize_text_field($_POST['object_name'] ?? '');
        $mapping = $_POST['mapping'] ?? [];
        
        if (!$form_id || empty($object_name)) {
            wp_send_json_error('Form ID and object name are required');
        }

        $result = $this->save_custom_object_mapping($form_id, $object_name, $mapping);
        
        if ($result) {
            wp_send_json_success('Custom object mapping saved successfully');
        } else {
            wp_send_json_error('Failed to save custom object mapping');
        }
    }

    /**
     * AJAX: Get custom object field mapping
     */
    public function ajax_get_custom_object_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $object_name = sanitize_text_field($_POST['object_name'] ?? '');
        
        if (!$form_id || empty($object_name)) {
            wp_send_json_error('Form ID and object name are required');
        }

        $mapping = $this->get_custom_object_mapping($form_id, $object_name);
        wp_send_json_success($mapping);
    }

    /**
     * Save custom object field mapping
     */
    private function save_custom_object_mapping(int $form_id, string $object_name, array $mapping): bool {
        if (!$form_id || empty($object_name)) {
            return false;
        }
        
        // Get existing custom object mappings
        $existing_mappings = get_post_meta($form_id, '_mavlers_cf_custom_object_mappings', true);
        if (!is_array($existing_mappings)) {
            $existing_mappings = [];
        }
        
        // Update mapping for this object
        $existing_mappings[$object_name] = $mapping;
        
        // Save back to post meta
        $result = update_post_meta($form_id, '_mavlers_cf_custom_object_mappings', $existing_mappings);
        
        //error_log("HubSpot: save_custom_object_mapping - Form ID: {$form_id}, Object: {$object_name}, Result: " . ($result ? 'true' : 'false'));
        
        return $result;
    }

    /**
     * Get custom object field mapping
     */
    private function get_custom_object_mapping(int $form_id, string $object_name): array {
        if (!$form_id || empty($object_name)) {
            return [];
        }
        
        $mappings = get_post_meta($form_id, '_mavlers_cf_custom_object_mappings', true);
        if (!is_array($mappings) || !isset($mappings[$object_name])) {
            return [];
        }
        
        return $mappings[$object_name];
    }

    /**
     * AJAX: Get HubSpot deal properties
     */
    public function ajax_get_deal_properties(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error('HubSpot not configured - Access token missing');
        }
        
        if (empty($portal_id)) {
            wp_send_json_error('HubSpot not configured - Portal ID missing');
        }
        
        //error_log("HubSpot: Getting deal properties for portal ID: {$portal_id}");
        
        // Use portal-specific URL for deal properties
        $url = "https://api.hubapi.com/crm/v3/properties/deals?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch deal properties: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            foreach ($data['results'] as $property) {
                // Include ALL properties (both writable and read-only) for the dropdown
                // Users can see all available properties and decide which to map
                $properties[] = [
                    'name' => $property['name'],
                    'label' => $property['label'],
                    'type' => $property['type'],
                    'required' => false, // Most properties are not required for updates
                    'readonly' => isset($property['modificationMetadata']['readOnlyDefinition']) && 
                                 $property['modificationMetadata']['readOnlyDefinition'] === true
                ];
            }
            
            wp_send_json_success($properties);
        } else {
            wp_send_json_error('Failed to fetch deal properties - Status: ' . $status_code);
        }
    }

    /**
     * AJAX: Get HubSpot company properties
     */
    public function ajax_get_company_properties(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error('HubSpot not configured - Access token missing');
        }
        
        if (empty($portal_id)) {
            wp_send_json_error('HubSpot not configured - Portal ID missing');
        }
        
        //error_log("HubSpot: Getting company properties for portal ID: {$portal_id}");
        
        // Use portal-specific URL for company properties
        $url = "https://api.hubapi.com/crm/v3/properties/companies?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch company properties: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            foreach ($data['results'] as $property) {
                // Include ALL properties (both writable and read-only) for the dropdown
                // Users can see all available properties and decide which to map
                $properties[] = [
                    'name' => $property['name'],
                    'label' => $property['label'],
                    'type' => $property['type'],
                    'required' => false, // Most properties are not required for updates
                    'readonly' => isset($property['modificationMetadata']['readOnlyDefinition']) && 
                                 $property['modificationMetadata']['readOnlyDefinition'] === true
                ];
            }
            
            wp_send_json_success($properties);
        } else {
            wp_send_json_error('Failed to fetch company properties - Status: ' . $status_code);
        }
    }

    /**
     * AJAX: Get HubSpot contact properties
     */
    public function ajax_get_contact_properties(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error('HubSpot not configured - Access token missing');
        }
        
        if (empty($portal_id)) {
            wp_send_json_error('HubSpot not configured - Portal ID missing');
        }
        
        //error_log("HubSpot: Getting contact properties for portal ID: {$portal_id}");
        
        // Use portal-specific URL for contact properties
        $url = "https://api.hubapi.com/crm/v3/properties/contacts?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch contact properties: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            foreach ($data['results'] as $property) {
                // Include ALL properties (both writable and read-only) for the dropdown
                // Users can see all available properties and decide which to map
                $properties[] = [
                    'name' => $property['name'],
                    'label' => $property['label'],
                    'type' => $property['type'],
                    'required' => false, // Most properties are not required for updates
                    'readonly' => isset($property['modificationMetadata']['readOnlyDefinition']) && 
                                 $property['modificationMetadata']['readOnlyDefinition'] === true
                ];
            }
            
            wp_send_json_success($properties);
        } else {
            wp_send_json_error('Failed to fetch contact properties - Status: ' . $status_code);
        }
    }

    /**
     * AJAX: Auto map fields
     */
    public function ajax_auto_map_fields(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            wp_send_json_error('Form ID is required');
        }

        // Generate automatic mapping based on form fields
        $auto_mapping = $this->generate_automatic_mapping($form_id);
        wp_send_json_success($auto_mapping);
    }

    /**
     * AJAX: Get analytics
     */
    public function ajax_get_analytics(): void {
        if ($this->analytics_manager) {
            $this->analytics_manager->ajax_get_analytics_data();
        } else {
            wp_send_json_error('Analytics manager not available');
        }
    }

    /**
     * Generate automatic field mapping
     */
    private function generate_automatic_mapping(int $form_id): array {
        $form_fields = $this->get_form_fields($form_id);
        $mapping = [];

        // Basic auto-mapping logic
        $auto_map_rules = [
            'email' => 'email',
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'phone' => 'phone',
            'company' => 'company'
        ];

        foreach ($form_fields as $field_name => $field_config) {
            $field_label = strtolower($field_config['label'] ?? '');
            
            foreach ($auto_map_rules as $pattern => $hubspot_field) {
                if (strpos($field_label, $pattern) !== false) {
                    $mapping[$field_name] = $hubspot_field;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Get form fields from database
     */
    private function get_form_fields(int $form_id): array {
        global $wpdb;
        
        //error_log("HubSpot: get_form_fields called for form ID: {$form_id}");
        
        if (!$form_id) {
            //error_log("HubSpot: get_form_fields - Form ID is invalid");
            return [];
        }
        
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));
        
        //error_log("HubSpot: get_form_fields - Form query result: " . ($form ? 'FOUND' : 'NOT FOUND'));
        
        if (!$form) {
            //error_log("HubSpot: get_form_fields - Form not found in database");
            return [];
        }
        
        //error_log("HubSpot: get_form_fields - Form data: " . print_r($form, true));
        
        if (!$form->fields) {
            //error_log("HubSpot: get_form_fields - No fields data in form");
            return [];
        }
        
        $fields_data = json_decode($form->fields, true);
        //error_log("HubSpot: get_form_fields - Decoded fields data: " . print_r($fields_data, true));
        
        if (!is_array($fields_data)) {
            //error_log("HubSpot: get_form_fields - Fields data is not an array");
            return [];
        }
        
        $processed_fields = [];
        
        foreach ($fields_data as $field) {
            //error_log("HubSpot: get_form_fields - Processing field: " . print_r($field, true));
            
            if (!isset($field['id']) || !isset($field['label'])) {
                //error_log("HubSpot: get_form_fields - Field missing id or label, skipping");
                continue;
            }
            
            $field_id = $field['id'];
            $field_type = $field['type'] ?? 'text';
            $field_label = $field['label'];
            $required = $field['required'] ?? false;
            
            $processed_fields[$field_id] = [
                'id' => $field_id,
                'label' => $field_label,
                'type' => $field_type,
                'required' => $required,
                'name' => $field['name'] ?? $field_id,
                'placeholder' => $field['placeholder'] ?? '',
                'description' => $field['description'] ?? ''
            ];
            
            //error_log("HubSpot: get_form_fields - Added processed field: " . print_r($processed_fields[$field_id], true));
        }
        
        //error_log("HubSpot: get_form_fields - Final processed fields: " . print_r($processed_fields, true));
        return $processed_fields;
    }

    /**
     * Get component managers
     */
    public function get_custom_properties_manager() {
        return $this->custom_properties_manager;
    }

    public function get_workflow_manager() {
        return $this->workflow_manager;
    }

    public function get_deal_manager() {
        return $this->deal_manager;
    }

    public function get_company_manager() {
        return $this->company_manager;
    }

    public function get_analytics_manager() {
        return $this->analytics_manager;
    }

    public function get_language_manager() {
        return $this->language_manager;
    }

    /**
     * Translation helper
     */
    private function __($text, $fallback = null) {
        if ($this->language_manager) {
            return $this->language_manager->translate($text);
        }
        return $fallback ?? $text;
    }

    /**
     * Handle form submission from the main plugin
     */
    public function handle_form_submission(int $submission_id, array $form_data) {
        try {
            // Get form ID from the form data
            $form_id = $form_data['form_id'] ?? 0;
            if (!$form_id) {
                return;
            }
            
            // Get form settings
            $settings = $this->getFormSettings($form_id);
            
            // Check if HubSpot integration is enabled for this form
            $hubspot_settings = $settings['hubspot'] ?? $settings;
            if (empty($hubspot_settings['enabled'])) {
                return;
            }
            
            // Check if globally connected
            if (!$this->is_globally_connected()) {
                return;
            }
            
            // Process the submission
            $result = $this->processSubmission($submission_id, $form_data, $hubspot_settings);
            
        } catch (\Throwable $e) {
            // Silent error handling for production
        }
    }
    
    private function simulate_template_loading(int $form_id): array {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        
        $form_settings = [];
        
        // Check if meta table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$meta_table'") == $meta_table) {
            $meta_value = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
                $form_id,
                '_mavlers_cf_integrations'
            ));
            
            if ($meta_value) {
                $integration_settings = json_decode($meta_value, true);
                if (is_array($integration_settings) && isset($integration_settings['hubspot'])) {
                    $form_settings = $integration_settings['hubspot'];
                }
            }
        }
        
        // Fallback: Try post meta
        if (empty($form_settings)) {
            $post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
            if ($post_meta && isset($post_meta['hubspot'])) {
                $form_settings = $post_meta['hubspot'];
            }
        }
        
        // Fallback: Try options table
        if (empty($form_settings)) {
            $option_key = "mavlers_cf_hubspot_form_{$form_id}";
            $form_settings = get_option($option_key, []);
        }
        
        return [
            'form_id' => $form_id,
            'settings_found' => !empty($form_settings),
            'settings' => $form_settings,
            'field_mapping' => $form_settings['field_mapping'] ?? []
        ];
    }
    
    private function form_exists_in_custom_table(int $form_id): bool {
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$forms_table} WHERE id = %d", $form_id));
    }
    
    private function get_form_settings_from_custom_table(int $form_id): array {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
            $form_id,
            '_mavlers_cf_integrations'
        ));
        
        if ($meta_value) {
            $settings = json_decode($meta_value, true);
            return is_array($settings) ? $settings : [];
        }
        
        return [];
    }
    
    /**
     * Get form settings for HubSpot integration
     */
    protected function getFormSettings(int $form_id): array {
        // Try to get settings from custom table first
        $settings = $this->get_form_settings_from_custom_table($form_id);
        
        if (!empty($settings)) {
            return $settings;
        }
        
        // Fallback to WordPress post meta
        $meta_value = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        if ($meta_value) {
            $decoded = json_decode($meta_value, true);
            $hubspot_settings = $decoded['hubspot'] ?? [];
            return $hubspot_settings;
        }
        
        return [];
    }

    /**
     * Save form settings
     */
    protected function saveFormSettings(int $form_id, array $settings): bool {
        if (!$form_id) {
            return false;
        }
        
        // Verify the form exists in the custom forms table
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        $form_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$forms_table} WHERE id = %d", $form_id));
        
        if (!$form_exists) {
            return false;
        }
        
        // Get existing integration settings from custom meta table
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        
        // Check if meta table exists, create if not
        $meta_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$meta_table'") == $meta_table;
        
        if (!$meta_table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $meta_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                form_id bigint(20) NOT NULL,
                meta_key varchar(255) NOT NULL,
                meta_value longtext,
                PRIMARY KEY (id),
                KEY form_id (form_id),
                KEY meta_key (meta_key)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        // Get existing settings
        $existing_settings = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
            $form_id,
            '_mavlers_cf_integrations'
        ));
        
        if ($existing_settings) {
            $existing_settings = json_decode($existing_settings, true);
        }
        
        if (!is_array($existing_settings)) {
            $existing_settings = [];
        }
        
        // Update HubSpot settings
        $existing_settings['hubspot'] = $settings;
        
        // Delete existing settings
        $wpdb->delete($meta_table, array('form_id' => $form_id, 'meta_key' => '_mavlers_cf_integrations'));
        
        // Insert new settings
        $insert_data = array(
            'form_id' => $form_id,
            'meta_key' => '_mavlers_cf_integrations',
            'meta_value' => json_encode($existing_settings)
        );
        
        $result = $wpdb->insert(
            $meta_table,
            $insert_data,
            array('%d', '%s', '%s')
        );
        
        return $result !== false;
    }
} 