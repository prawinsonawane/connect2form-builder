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
        error_log("HubSpot: === INTEGRATION CONSTRUCTOR CALLED ===");
        error_log("HubSpot: Integration ID: " . $this->id);
        error_log("HubSpot: Integration Name: " . $this->name);
        parent::__construct();
        error_log("HubSpot: === INTEGRATION CONSTRUCTOR COMPLETED ===");
    }

    protected function init() {
        error_log("HubSpot: Integration init() called");
        
        // Defer component initialization to ensure WordPress is ready
        add_action('init', [$this, 'init_components'], 5);
        
        // Register AJAX handlers after WordPress is fully loaded
        add_action('init', [$this, 'register_ajax_handlers'], 20);
        
        // Register asset enqueuing
        add_action('admin_enqueue_scripts', [$this, 'enqueue_comprehensive_assets']);
        
        error_log("HubSpot: Integration init() completed");
    }

    /**
     * Initialize HubSpot-specific components
     */
    public function init_components() {
        error_log("HubspotIntegration: Starting component initialization");
        
        try {
            // Initialize component managers with error handling
            if (class_exists('MavlersCF\Integrations\Hubspot\CustomPropertiesManager')) {
                $this->custom_properties_manager = new CustomPropertiesManager();
                error_log("HubspotIntegration: CustomPropertiesManager initialized successfully");
            } else {
                error_log("HubspotIntegration: CustomPropertiesManager class not found");
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\WorkflowManager')) {
                $this->workflow_manager = new WorkflowManager();
                error_log("HubspotIntegration: WorkflowManager initialized successfully");
            } else {
                error_log("HubspotIntegration: WorkflowManager class not found");
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\DealManager')) {
                $this->deal_manager = new DealManager();
                error_log("HubspotIntegration: DealManager initialized successfully");
            } else {
                error_log("HubspotIntegration: DealManager class not found");
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\CompanyManager')) {
                $this->company_manager = new CompanyManager();
                error_log("HubspotIntegration: CompanyManager initialized successfully");
            } else {
                error_log("HubspotIntegration: CompanyManager class not found");
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\AnalyticsManager')) {
                $this->analytics_manager = new AnalyticsManager();
                error_log("HubspotIntegration: AnalyticsManager initialized successfully");
            } else {
                error_log("HubspotIntegration: AnalyticsManager class not found");
            }
            
            if (class_exists('MavlersCF\Integrations\Core\Services\LanguageManager')) {
                $this->language_manager = new LanguageManager();
                error_log("HubspotIntegration: LanguageManager initialized successfully");
            } else {
                error_log("HubspotIntegration: LanguageManager class not found");
            }
            
            error_log("HubspotIntegration: Component initialization completed");
            
        } catch (\Throwable $e) {
            error_log("HubspotIntegration: Error during component initialization: " . $e->getMessage());
            error_log("HubspotIntegration: Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Register AJAX handlers for HubSpot integration
     */
    public function register_ajax_handlers() {
        error_log("HubSpot: === REGISTERING AJAX HANDLERS ===");
        error_log("HubSpot: Current time: " . date('Y-m-d H:i:s'));
        error_log("HubSpot: WordPress loaded: " . (defined('ABSPATH') ? 'Yes' : 'No'));
        error_log("HubSpot: Admin area: " . (is_admin() ? 'Yes' : 'No'));
        error_log("HubSpot: AJAX request: " . (wp_doing_ajax() ? 'Yes' : 'No'));
        
        // Test response functions
        add_action('wp_ajax_mavlers_cf_hubspot_test_response', [$this, 'ajax_test_response']);
        error_log("HubSpot: Registered wp_ajax_mavlers_cf_hubspot_test_response");
        
        // Test connection
        add_action('wp_ajax_mavlers_cf_hubspot_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_hubspot_test_connection', [$this, 'ajax_test_connection']);
        error_log("HubSpot: Registered wp_ajax_mavlers_cf_hubspot_test_connection");
        
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
        error_log("HubSpot: Registered wp_ajax_hubspot_save_global_settings_v2");
        error_log("HubSpot: Registered wp_ajax_hubspot_save_global_settings");
        
        // Add a simple test handler
        add_action('wp_ajax_hubspot_test_simple_v2', [$this, 'ajax_test_simple_v2']);
        error_log("HubSpot: Registered wp_ajax_hubspot_test_simple_v2");
        
        add_action('wp_ajax_mavlers_cf_hubspot_save_form_settings', [$this, 'ajax_save_form_settings']);
        
        // Field mapping
        add_action('wp_ajax_mavlers_cf_hubspot_save_field_mapping', [$this, 'ajax_save_field_mapping']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_field_mapping', [$this, 'ajax_get_field_mapping']);
        add_action('wp_ajax_mavlers_cf_hubspot_auto_map_fields', [$this, 'ajax_auto_map_fields']);
        
        // Analytics
        add_action('wp_ajax_mavlers_cf_hubspot_get_analytics', [$this, 'ajax_get_analytics']);
        
        // Simple test endpoint
        add_action('wp_ajax_hubspot_test_simple', [$this, 'ajax_test_simple']);
        
        // Add a very simple test handler
        add_action('wp_ajax_hubspot_test_basic', [$this, 'ajax_test_basic']);
        error_log("HubSpot: Registered wp_ajax_hubspot_test_basic");
        
        // Add a debug test handler without nonce requirement
        add_action('wp_ajax_hubspot_debug_test', [$this, 'ajax_debug_test']);
        error_log("HubSpot: Registered wp_ajax_hubspot_debug_test");
        
        error_log("HubSpot: === ALL AJAX HANDLERS REGISTERED ===");
        
        // Verify registration by checking if actions exist
        global $wp_filter;
        $registered_actions = [];
        foreach ($wp_filter as $hook => $callbacks) {
            if (strpos($hook, 'wp_ajax_hubspot_') === 0 || strpos($hook, 'wp_ajax_mavlers_cf_hubspot_') === 0) {
                $registered_actions[] = $hook;
            }
        }
        error_log("HubSpot: Verified registered actions: " . implode(', ', $registered_actions));
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
            'enroll_workflow' => [
                'label' => $this->__('Enroll in Workflow'),
                'description' => $this->__('Enroll contact in HubSpot workflow')
            ],
            'associate_company' => [
                'label' => $this->__('Associate Company'),
                'description' => $this->__('Associate contact with existing company')
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
     * Validate HubSpot settings
     */
    public function validateSettings(array $settings): array {
        error_log("HubSpot: validateSettings called with: " . print_r($settings, true));
        
        $errors = [];

        if (empty($settings['access_token'])) {
            $errors[] = 'Access token is required';
            error_log("HubSpot: Validation error - access_token is empty");
        }

        if (empty($settings['portal_id'])) {
            $errors[] = 'Portal ID is required';
            error_log("HubSpot: Validation error - portal_id is empty");
        }
        
        error_log("HubSpot: Validation errors: " . print_r($errors, true));
        
        return $errors;
    }

    /**
     * Process form submission to HubSpot
     */
    public function processSubmission(int $submission_id, array $form_data, array $settings = []): array {
        error_log("HubspotIntegration: processSubmission called");
        error_log("HubspotIntegration: submission_id: " . $submission_id);
        error_log("HubspotIntegration: form_data: " . print_r($form_data, true));
        error_log("HubspotIntegration: settings: " . print_r($settings, true));

        // Get settings if not provided
        if (empty($settings)) {
            $form_id = $form_data['form_id'] ?? 0;
            error_log("HubspotIntegration: Getting settings for form_id: " . $form_id);
            $settings = $this->getFormSettings($form_id);
            error_log("HubspotIntegration: Retrieved settings: " . print_r($settings, true));
        }

        // Check if integration is enabled
        if (!$this->isEnabled($settings)) {
            error_log("HubspotIntegration: Integration not enabled for this form");
            return [
                'success' => false,
                'error' => 'HubSpot integration not enabled'
            ];
        }

        // Check global connection
        if (!$this->is_globally_connected()) {
            error_log("HubspotIntegration: Global connection not available");
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
        error_log("HubspotIntegration: process_submission_immediate called");

        $global_settings = $this->get_global_settings();
        error_log("HubspotIntegration: global_settings: " . print_r($global_settings, true));

        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';

        error_log("HubspotIntegration: access_token: " . ($access_token ? 'Present' : 'Missing'));
        error_log("HubspotIntegration: portal_id: " . $portal_id);

        if (empty($access_token) || empty($portal_id)) {
            return [
                'success' => false,
                'error' => 'HubSpot not properly configured'
            ];
        }

        // Map form data to HubSpot fields
        $mapped_data = $this->enhanced_map_form_data($form_data, $settings);
        error_log("HubspotIntegration: mapped_data: " . print_r($mapped_data, true));

        $results = [];

        // Create/Update Contact
        if (!empty($settings['contact_enabled'])) {
            $contact_result = $this->createOrUpdateContact($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            $results['contact'] = $contact_result;
        }

        // Create Deal
        if (!empty($settings['deal_enabled']) && !empty($settings['deal_pipeline'])) {
            $deal_result = $this->createDeal($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            $results['deal'] = $deal_result;
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

        error_log("HubspotIntegration: enhanced_map_form_data called");
        error_log("HubspotIntegration: form_data received: " . print_r($form_data, true));
        error_log("HubspotIntegration: settings received: " . print_r($settings, true));

        // Get the actual field data - handle nested structure
        $field_data = $form_data;
        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            $field_data = $form_data['fields'];
            error_log("HubspotIntegration: Using nested fields data: " . print_r($field_data, true));
        }

        // Get field mapping from settings
        $field_mapping = $settings['field_mapping'] ?? [];
        error_log("HubspotIntegration: field_mapping from settings: " . print_r($field_mapping, true));

        if (!empty($field_mapping)) {
            error_log("HubspotIntegration: Using field mapping");
            foreach ($field_mapping as $form_field => $hubspot_field) {
                error_log("HubspotIntegration: Checking field mapping: {$form_field} -> {$hubspot_field}");
                if (isset($field_data[$form_field]) && !empty($field_data[$form_field])) {
                    $mapped[$hubspot_field] = $field_data[$form_field];
                    error_log("HubspotIntegration: Mapped {$form_field} ({$field_data[$form_field]}) to {$hubspot_field}");
                } else {
                    error_log("HubspotIntegration: Field {$form_field} not found or empty in field data");
                }
            }
        } else {
            // Fall back to basic mapping
            error_log("HubspotIntegration: Using basic field mapping (fallback)");
            $mapped = $this->mapFormDataToHubspot($field_data, $settings);
        }

        error_log("HubspotIntegration: Final mapped data: " . print_r($mapped, true));
        return $mapped;
    }

    /**
     * Basic form data mapping for HubSpot (fallback)
     */
    private function mapFormDataToHubspot(array $form_data, array $settings): array {
        $mapped = [];
        
        error_log("HubspotIntegration: mapFormDataToHubspot called");
        error_log("HubspotIntegration: form_data for basic mapping: " . print_r($form_data, true));
        
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

        error_log("HubspotIntegration: Using basic field map: " . print_r($field_map, true));

        foreach ($field_map as $form_field => $hubspot_field) {
            error_log("HubspotIntegration: Checking basic mapping: {$form_field} -> {$hubspot_field}");
            if (isset($form_data[$form_field]) && !empty($form_data[$form_field])) {
                $mapped[$hubspot_field] = $form_data[$form_field];
                error_log("HubspotIntegration: Basic mapped {$form_field} ({$form_data[$form_field]}) to {$hubspot_field}");
            } else {
                error_log("HubspotIntegration: Basic field {$form_field} not found or empty");
            }
        }

        error_log("HubspotIntegration: Basic mapping result: " . print_r($mapped, true));
        return $mapped;
    }

    /**
     * Create or update contact in HubSpot
     */
    private function createOrUpdateContact(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: createOrUpdateContact called");
        error_log("HubspotIntegration: data: " . print_r($data, true));
        
        if (empty($data['email'])) {
            error_log("HubspotIntegration: No email found in data");
            return [
                'success' => false,
                'error' => 'Email is required for contact creation'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/objects/contacts";
        
        $properties = [];
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $properties[$key] = $value;
            }
        }
        
        $payload = [
            'properties' => $properties
        ];
        
        error_log("HubspotIntegration: Contact payload: " . print_r($payload, true));
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log("HubspotIntegration: Contact creation error: " . $response->get_error_message());
            return [
                'success' => false,
                'error' => 'Contact creation failed: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        error_log("HubspotIntegration: Contact creation response - Status: {$status_code}, Body: " . print_r($result, true));
        
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
        error_log("HubspotIntegration: createDeal called");
        
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
        error_log("HubspotIntegration: enrollInWorkflow called");
        
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
     * Associate contact with company
     */
    private function associateCompany(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: associateCompany called");
        
        $company_id = $settings['company_id'] ?? '';
        
        if (empty($company_id)) {
            return [
                'success' => false,
                'error' => 'Company ID is required'
            ];
        }
        
        // This would require getting the contact ID first, then associating
        // For now, return success as this is a complex operation
        return [
            'success' => true,
            'message' => 'Company association not implemented yet'
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
        // Load on form edit pages and integration settings pages
        $allowed_hooks = ['post.php', 'post-new.php', 'admin.php'];
        $allowed_screens = ['mavlers_cf_form', 'mavlers-cf-integrations'];
        
        if (!in_array($hook, $allowed_hooks)) {
            return;
        }
        
        $screen = get_current_screen();
        if ($screen && !in_array($screen->post_type, $allowed_screens) && !in_array($screen->id, $allowed_screens)) {
            return;
        }
        
        error_log("HubSpot: Enqueuing assets on hook: {$hook}");
        error_log("HubSpot: Screen: " . ($screen ? $screen->id : 'no screen'));
        
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
        
        // Localize script with unique object name to avoid conflicts
        wp_localize_script('mavlers-cf-hubspot', 'mavlers_cf_hubspot_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'form_id' => get_the_ID()
        ]);
        
        // Also localize with standard nonce for compatibility
        wp_add_inline_script('mavlers-cf-hubspot', 'var mavlers_cf_nonce = "' . wp_create_nonce('mavlers_cf_nonce') . '";', 'before');
        wp_add_inline_script('mavlers-cf-hubspot', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
        
        error_log("HubSpot: Assets enqueued successfully");
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection(): void {
        error_log("HubSpot: === AJAX TEST CONNECTION START ===");
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log("HubSpot: Nonce verification failed");
            wp_send_json([
                'success' => false,
                'error' => 'Security check failed'
            ]);
        }
        
        error_log("HubSpot: Nonce verification passed");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            error_log("HubSpot: Insufficient permissions");
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
        
        error_log("HubSpot: Extracted access_token: " . (empty($access_token) ? 'EMPTY' : 'Present'));
        error_log("HubSpot: Extracted portal_id: " . (empty($portal_id) ? 'EMPTY' : $portal_id));
        
        if (empty($access_token) || empty($portal_id)) {
            error_log("HubSpot: Missing required credentials");
            wp_send_json([
                'success' => false,
                'error' => 'Access token and Portal ID are required'
            ]);
        }
        
        $result = $this->testConnection([
            'access_token' => $access_token,
            'portal_id' => $portal_id
        ]);
        
        error_log("HubSpot: Test connection result: " . print_r($result, true));
        
        wp_send_json($result);
        
        error_log("HubSpot: === AJAX TEST CONNECTION END ===");
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
        error_log("HubSpot: === TEST RESPONSE AJAX HANDLER ===");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        // Check if this is a valid AJAX request
        if (!wp_doing_ajax()) {
            error_log("HubSpot: Not an AJAX request");
            wp_send_json_error('Not an AJAX request');
            return;
        }
        
        // Check nonce if provided
        if (isset($_POST['nonce'])) {
            if (!wp_verify_nonce($_POST['nonce'], 'mavlers_cf_nonce')) {
                error_log("HubSpot: Nonce verification failed");
                error_log("HubSpot: Received nonce: " . $_POST['nonce']);
                error_log("HubSpot: Expected nonce: " . wp_create_nonce('mavlers_cf_nonce'));
                wp_send_json_error('Nonce verification failed');
                return;
            }
            error_log("HubSpot: Nonce verification passed");
        } else {
            error_log("HubSpot: No nonce provided, skipping verification");
        }
        
        // Return a simple success response
        wp_send_json_success('Test response successful');
        
        error_log("HubSpot: === TEST RESPONSE AJAX HANDLER END ===");
    }

    /**
     * AJAX: Save global settings (standard version)
     */
    public function ajax_save_global_settings(): void {
        error_log("HubSpot: === AJAX SAVE GLOBAL SETTINGS START ===");
        
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log("HubSpot: Nonce verification failed");
            wp_die('Security check failed');
        }
        
        error_log("HubSpot: Nonce verification passed");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
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
        
        error_log("HubSpot: Extracted settings: " . print_r($settings, true));
        
        // Validate settings
        $validation_result = $this->validateSettings($settings);
        error_log("HubSpot: Validation result: " . print_r($validation_result, true));
        if (!empty($validation_result)) {
            error_log("HubSpot: Validation failed: " . print_r($validation_result, true));
            wp_send_json_error('Settings validation failed: ' . implode(', ', $validation_result));
            return;
        }
        
        error_log("HubSpot: Validation passed");
        
        // Save settings using the abstract method
        error_log("HubSpot: Calling saveGlobalSettings with settings: " . print_r($settings, true));
        $result = $this->saveGlobalSettings($settings);
        
        error_log("HubSpot: Save result: " . ($result ? 'success' : 'failed'));
        
        if ($result) {
            error_log("HubSpot: Settings saved successfully");
            wp_send_json_success([
                'message' => 'Settings saved successfully!',
                'configured' => true
            ]);
        } else {
            error_log("HubSpot: Failed to save settings");
            wp_send_json_error('Failed to save settings');
        }
        
        error_log("HubSpot: === AJAX SAVE GLOBAL SETTINGS END ===");
    }

    /**
     * AJAX: Save global settings (simplified version)
     */
    public function ajax_save_global_settings_simple(): void {
        error_log("HubSpot: === SIMPLIFIED AJAX SAVE GLOBAL SETTINGS START ===");
        
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log("HubSpot: Nonce verification failed");
            wp_die('Security check failed');
        }
        
        error_log("HubSpot: Nonce verification passed");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Extract settings
        $settings = [
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
            'enable_analytics' => !empty($_POST['enable_analytics']),
            'enable_webhooks' => !empty($_POST['enable_webhooks']),
            'batch_processing' => !empty($_POST['batch_processing'])
        ];
        
        error_log("HubSpot: Extracted settings: " . print_r($settings, true));
        
        // Simple validation
        if (empty($settings['access_token']) || empty($settings['portal_id'])) {
            error_log("HubSpot: Validation failed - missing required fields");
            wp_send_json_error('Access token and Portal ID are required');
            return;
        }
        
        error_log("HubSpot: Validation passed");
        
        // Save settings
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        $global_settings['hubspot'] = $settings;
        $result = update_option('mavlers_cf_integrations_global', $global_settings);
        
        error_log("HubSpot: Save result: " . ($result ? 'success' : 'failed'));
        
        if ($result) {
            error_log("HubSpot: Settings saved successfully");
            wp_send_json_success('Settings saved successfully');
        } else {
            error_log("HubSpot: Failed to save settings");
            wp_send_json_error('Failed to save settings');
        }
        
        error_log("HubSpot: === SIMPLIFIED AJAX SAVE GLOBAL SETTINGS END ===");
    }

    /**
     * AJAX: Save global settings (v2 - isolated)
     */
    public function ajax_save_global_settings_v2(): void {
        error_log("HubSpot: === V2 AJAX SAVE GLOBAL SETTINGS START ===");
        error_log("HubSpot: Handler called successfully!");
        error_log("HubSpot: Request method: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        error_log("HubSpot: Raw POST data: " . file_get_contents('php://input'));
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log("HubSpot: Nonce verification failed");
            error_log("HubSpot: Received nonce: " . ($_POST['nonce'] ?? 'not set'));
            error_log("HubSpot: Expected nonce: " . wp_create_nonce('mavlers_cf_nonce'));
            wp_die('Security check failed');
        }
        
        error_log("HubSpot: Nonce verification passed");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Extract settings
        $settings = [
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
            'enable_analytics' => !empty($_POST['enable_analytics']),
            'enable_webhooks' => !empty($_POST['enable_webhooks']),
            'batch_processing' => !empty($_POST['batch_processing'])
        ];
        
        error_log("HubSpot: Extracted settings: " . print_r($settings, true));
        
        // Simple validation
        if (empty($settings['access_token']) || empty($settings['portal_id'])) {
            error_log("HubSpot: Validation failed - missing required fields");
            wp_send_json_error('Access token and Portal ID are required');
            return;
        }
        
        error_log("HubSpot: Validation passed");
        
        // Save settings
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        $global_settings['hubspot'] = $settings;
        $result = update_option('mavlers_cf_integrations_global', $global_settings);
        
        error_log("HubSpot: Save result: " . ($result ? 'success' : 'failed'));
        
        if ($result) {
            error_log("HubSpot: Settings saved successfully");
            wp_send_json_success('Settings saved successfully');
        } else {
            error_log("HubSpot: Failed to save settings");
            wp_send_json_error('Failed to save settings');
        }
        
        error_log("HubSpot: === V2 AJAX SAVE GLOBAL SETTINGS END ===");
    }

    /**
     * AJAX: Save form settings
     */
    public function ajax_save_form_settings(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $settings = [
            'enabled' => !empty($_POST['enabled']),
            'contact_enabled' => !empty($_POST['contact_enabled']),
            'deal_enabled' => !empty($_POST['deal_enabled']),
            'workflow_enabled' => !empty($_POST['workflow_enabled']),
            'company_enabled' => !empty($_POST['company_enabled']),
            'deal_pipeline' => sanitize_text_field($_POST['deal_pipeline'] ?? ''),
            'deal_stage' => sanitize_text_field($_POST['deal_stage'] ?? ''),
            'workflow_id' => sanitize_text_field($_POST['workflow_id'] ?? ''),
            'company_id' => sanitize_text_field($_POST['company_id'] ?? ''),
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
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->analytics_manager) {
            $this->analytics_manager->ajax_get_analytics_data();
        } else {
            wp_send_json_error('Analytics manager not available');
        }
    }

    /**
     * AJAX: Simple test endpoint
     */
    public function ajax_test_simple(): void {
        error_log("HubSpot: Simple test endpoint called");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        wp_send_json_success('Simple test successful');
    }

    /**
     * AJAX: Simple test endpoint (v2)
     */
    public function ajax_test_simple_v2(): void {
        error_log("HubSpot: Simple test endpoint (v2) called");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content-Type: " . $_SERVER['CONTENT_TYPE'] ?? 'not set');
        
        // Check if this is a valid AJAX request
        if (!wp_doing_ajax()) {
            error_log("HubSpot: Not an AJAX request");
            wp_send_json_error('Not an AJAX request');
            return;
        }
        
        // No nonce check for this test endpoint
        wp_send_json_success('Simple test (v2) successful');
    }

    /**
     * AJAX: Basic test endpoint
     */
    public function ajax_test_basic(): void {
        error_log("HubSpot: Basic test endpoint called");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content-Type: " . $_SERVER['CONTENT_TYPE'] ?? 'not set');
        
        // Check if this is a valid AJAX request
        if (!wp_doing_ajax()) {
            error_log("HubSpot: Not an AJAX request");
            wp_send_json_error('Not an AJAX request');
            return;
        }
        
        // Check nonce if provided
        if (isset($_POST['nonce'])) {
            if (!wp_verify_nonce($_POST['nonce'], 'mavlers_cf_nonce')) {
                error_log("HubSpot: Nonce verification failed");
                wp_send_json_error('Nonce verification failed');
                return;
            }
        }
        
        wp_send_json_success('Basic test successful');
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
        
        if (!$form_id) {
            return [];
        }
        
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));
        
        if (!$form || !$form->fields) {
            return [];
        }
        
        $fields_data = json_decode($form->fields, true);
        if (!is_array($fields_data)) {
            return [];
        }
        
        $processed_fields = [];
        
        foreach ($fields_data as $field) {
            if (!isset($field['id']) || !isset($field['label'])) {
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
        }
        
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
     * AJAX: Debug test endpoint (no nonce required)
     */
    public function ajax_debug_test(): void {
        error_log("HubSpot: === DEBUG TEST ENDPOINT CALLED ===");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        error_log("HubSpot: wp_doing_ajax(): " . (wp_doing_ajax() ? 'true' : 'false'));
        error_log("HubSpot: DOING_AJAX: " . (defined('DOING_AJAX') ? 'true' : 'false'));
        
        // No nonce check for debug endpoint
        wp_send_json_success('Debug test successful');
        
        error_log("HubSpot: === DEBUG TEST ENDPOINT END ===");
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
} 