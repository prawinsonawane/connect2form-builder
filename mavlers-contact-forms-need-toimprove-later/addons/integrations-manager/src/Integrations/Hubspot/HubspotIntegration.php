<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Abstracts\AbstractIntegration;
use MavlersCF\Integrations\Core\Interfaces\IntegrationInterface;
use MavlersCF\Integrations\Core\Services\Logger;

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

    // Manager instances
    private $contact_manager;
    private $deal_manager;
    private $workflow_manager;
    private $custom_object_manager;
    private $settings_manager;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize managers
        $this->init_managers();
        
        // Register form submission hook
        \add_action('mavlers_cf_after_submission', [$this, 'handle_form_submission'], 10, 2);
        
        // Register AJAX handlers immediately if WordPress functions are available, otherwise on init
        if (function_exists('add_action')) {
            $this->register_ajax_handlers();
        } else {
            \add_action('init', [$this, 'register_ajax_handlers']);
        }
    }

    /**
     * Initialize manager instances
     */
    private function init_managers(): void {
        try {
            // Create HubSpot-specific API client
            $hubspot_api_client = new HubspotApiClient();
            $this->contact_manager = new HubspotContactManager($hubspot_api_client);
            $this->deal_manager = new HubspotDealManager($hubspot_api_client);
            $this->workflow_manager = new HubspotWorkflowManager($hubspot_api_client);
            $this->custom_object_manager = new HubspotCustomObjectManager($hubspot_api_client);
            $this->settings_manager = new HubspotSettingsManager();
        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', 0, 'error', 
                'Failed to initialize managers: ' . $e->getMessage(), [
                    'exception' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * Register AJAX handlers
     */
    public function register_ajax_handlers(): void {
        
        
        // Global settings handlers (admin only)
        \add_action('wp_ajax_hubspot_test_connection', [$this, 'ajax_test_connection']);
        \add_action('wp_ajax_hubspot_get_global_settings', [$this, 'ajax_get_global_settings']);
        \add_action('wp_ajax_hubspot_save_global_settings', [$this, 'ajax_save_global_settings']);
        
        // Simple test handler
        \add_action('wp_ajax_hubspot_test_simple', [$this, 'ajax_test_simple']);
        \add_action('wp_ajax_hubspot_get_contacts', [$this, 'ajax_get_contacts']);
        \add_action('wp_ajax_hubspot_get_companies', [$this, 'ajax_get_companies']);
        \add_action('wp_ajax_hubspot_get_deals', [$this, 'ajax_get_deals']);
        \add_action('wp_ajax_hubspot_get_contact_properties', [$this, 'ajax_get_contact_properties']);
        \add_action('wp_ajax_hubspot_get_deal_properties', [$this, 'ajax_get_deal_properties']);
        \add_action('wp_ajax_hubspot_get_company_properties', [$this, 'ajax_get_company_properties']);
        \add_action('wp_ajax_hubspot_get_workflows', [$this, 'ajax_get_workflows']);
        \add_action('wp_ajax_hubspot_get_custom_objects', [$this, 'ajax_get_custom_objects']);
        \add_action('wp_ajax_hubspot_get_custom_object_properties', [$this, 'ajax_get_custom_object_properties']);
        
        // Form-specific handlers (form editors)
        \add_action('wp_ajax_hubspot_form_save_settings', [$this, 'ajax_form_save_settings']);
        \add_action('wp_ajax_hubspot_form_get_fields', [$this, 'ajax_form_get_fields']);
        \add_action('wp_ajax_hubspot_form_auto_map_fields', [$this, 'ajax_form_auto_map_fields']);
        \add_action('wp_ajax_hubspot_form_get_contacts', [$this, 'ajax_form_get_contacts']);
        \add_action('wp_ajax_hubspot_form_get_companies', [$this, 'ajax_form_get_companies']);
        \add_action('wp_ajax_hubspot_form_get_deals', [$this, 'ajax_form_get_deals']);
        \add_action('wp_ajax_hubspot_form_get_contact_properties', [$this, 'ajax_form_get_contact_properties']);
        \add_action('wp_ajax_hubspot_form_get_deal_properties', [$this, 'ajax_form_get_deal_properties']);
        \add_action('wp_ajax_hubspot_form_get_company_properties', [$this, 'ajax_form_get_company_properties']);
        \add_action('wp_ajax_hubspot_form_get_workflows', [$this, 'ajax_form_get_workflows']);
        \add_action('wp_ajax_hubspot_form_get_custom_objects', [$this, 'ajax_form_get_custom_objects']);
        \add_action('wp_ajax_hubspot_form_get_custom_object_properties', [$this, 'ajax_form_get_custom_object_properties']);
        
        // Add a simple test handler
        \add_action('wp_ajax_hubspot_form_test', [$this, 'ajax_form_test']);
        \add_action('wp_ajax_hubspot_form_get_fields_test', [$this, 'ajax_form_get_fields_test']);
    }

    /**
     * Get authentication fields
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
     * Test connection
     */
    public function testConnection(array $credentials): array {
        return $this->settings_manager->testConnection($credentials);
    }

    /**
     * Get available actions
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
     * Get settings fields
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
     * Get field mapping
     */
    public function getFieldMapping(string $action): array {
        return $this->settings_manager->getFieldMapping($action);
    }

    /**
     * Validate settings
     */
    public function validateSettings(array $settings): array {
        $errors = [];

        // Debug the settings
        error_log('DEBUG: HubSpot validateSettings - workflow_enabled: ' . ($settings['workflow_enabled'] ?? 'not set'));
        error_log('DEBUG: HubSpot validateSettings - workflow_id: ' . ($settings['workflow_id'] ?? 'not set'));
        error_log('DEBUG: HubSpot validateSettings - workflow_enabled type: ' . gettype($settings['workflow_enabled'] ?? null));
        error_log('DEBUG: HubSpot validateSettings - workflow_id type: ' . gettype($settings['workflow_id'] ?? null));

        // Check if integration is enabled
        if (empty($settings['enabled'])) {
            $errors[] = 'Integration is not enabled';
        }

        // Check for required global settings
        if (empty($settings['access_token'])) {
            $errors[] = 'Access token is required';
        }

        if (empty($settings['portal_id'])) {
            $errors[] = 'Portal ID is required';
        }

        // Only require workflow ID if workflow enrollment is enabled and workflow_id is actually empty
        // TEMPORARILY DISABLED FOR TESTING
        // if (!empty($settings['workflow_enabled']) && (empty($settings['workflow_id']) || $settings['workflow_id'] === '')) {
        //     $errors[] = 'Workflow ID is required when workflow enrollment is enabled';
        // }

        error_log('DEBUG: HubSpot validateSettings - errors: ' . print_r($errors, true));

        return $errors;
    }

    /**
     * Process form submission
     */
    public function processSubmission(int $submission_id, array $form_data, array $settings = []): array {
        try {
            // Debug logging
            error_log('DEBUG: HubSpot processSubmission called - Submission ID: ' . $submission_id);
            error_log('DEBUG: HubSpot form_data: ' . print_r($form_data, true));
            error_log('DEBUG: HubSpot settings: ' . print_r($settings, true));
            
            // Get global settings if not provided
            if (empty($settings)) {
                $settings = $this->getGlobalSettings();
                error_log('DEBUG: HubSpot retrieved global settings: ' . print_r($settings, true));
            }

            // Validate settings
            $validation_errors = $this->validateSettings($settings);
            if (!empty($validation_errors)) {
                error_log('DEBUG: HubSpot validation errors: ' . print_r($validation_errors, true));
                return [
                    'success' => false,
                    'error' => 'Settings validation failed: ' . implode(', ', $validation_errors)
                ];
            }

            // Check if integration is enabled
            if (empty($settings['enabled'])) {
                error_log('DEBUG: HubSpot integration not enabled');
                return [
                    'success' => false,
                    'error' => 'HubSpot integration is not enabled'
                ];
            }

            $access_token = $settings['access_token'] ?? '';
            $portal_id = $settings['portal_id'] ?? '';

            error_log('DEBUG: HubSpot access_token: ' . ($access_token ? 'present' : 'missing'));
            error_log('DEBUG: HubSpot portal_id: ' . ($portal_id ? 'present' : 'missing'));

            if (empty($access_token) || empty($portal_id)) {
                error_log('DEBUG: HubSpot missing access_token or portal_id');
                return [
                    'success' => false,
                    'error' => 'Access token and Portal ID are required'
                ];
            }

            error_log('DEBUG: HubSpot proceeding with submission processing');

            $results = [];

            // Process based on object type
            switch ($settings['object_type'] ?? 'contacts') {
                case 'contacts':
                    error_log('DEBUG: HubSpot processing contacts object type');
                    $results['contact'] = $this->contact_manager->createOrUpdateContact($form_data, $settings, $access_token, $submission_id);
                    break;

                case 'deals':
                    error_log('DEBUG: HubSpot processing deals object type');
                    $results['deal'] = $this->deal_manager->createDeal($form_data, $settings, $access_token, $submission_id);
                    break;

                case 'companies':
                    error_log('DEBUG: HubSpot processing companies object type');
                    // Company creation would be handled by a company manager
                    $results['company'] = ['success' => false, 'error' => 'Company creation not implemented yet'];
                    break;

                case 'custom':
                    error_log('DEBUG: HubSpot processing custom object type');
                    if (!empty($settings['custom_object_name'])) {
                        $results['custom_object'] = $this->custom_object_manager->createCustomObject($form_data, $settings, $access_token, $submission_id);
                    }
                    break;
            }

            // Handle workflow enrollment if enabled
            if (!empty($settings['workflow_enabled']) && !empty($settings['workflow_id'])) {
                error_log('DEBUG: HubSpot processing workflow enrollment');
                $results['workflow'] = $this->workflow_manager->enrollInWorkflow($form_data, $settings, $access_token, $submission_id);
            }

            error_log('DEBUG: HubSpot processing results: ' . print_r($results, true));

            return [
                'success' => true,
                'results' => $results
            ];

        } catch (\Exception $e) {
            error_log('DEBUG: HubSpot exception: ' . $e->getMessage());
            $this->logger->logIntegration('hubspot', $settings['form_id'] ?? 0, 'error', 
                'Exception in submission processing: ' . $e->getMessage(), [
                    'submission_id' => $submission_id,
                    'exception' => $e->getMessage()
                ], $submission_id
            );

            return [
                'success' => false,
                'error' => 'Submission processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle form submission
     */
    public function handle_form_submission(int $submission_id, array $form_data): void {
        try {
            // Get form ID from submission
            $form_id = $this->get_form_id_from_submission($submission_id);
            if (!$form_id) {
                return;
            }

            // Get form settings
            $settings = $this->getFormSettings($form_id);
            if (empty($settings) || empty($settings['enabled'])) {
                return;
            }

            // Process submission
            $result = $this->processSubmission($submission_id, $form_data, $settings);

            if (!$result['success']) {
                $this->logger->logIntegration('hubspot', $form_id, 'error', 
                    'Form submission processing failed: ' . ($result['error'] ?? 'Unknown error'), [
                        'submission_id' => $submission_id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ], $submission_id
                );
            }

        } catch (\Exception $e) {
            $this->logger->logIntegration('hubspot', 0, 'error', 
                'Exception in form submission handler: ' . $e->getMessage(), [
                    'submission_id' => $submission_id,
                    'exception' => $e->getMessage()
                ], $submission_id
            );
        }
    }

    /**
     * Get form ID from submission
     */
    private function get_form_id_from_submission(int $submission_id): ?int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_submissions';
        $form_id = $wpdb->get_var($wpdb->prepare(
            "SELECT form_id FROM {$table_name} WHERE id = %d",
            $submission_id
        ));

        return $form_id ? (int) $form_id : null;
    }

    // AJAX Handlers

    /**
     * AJAX: Simple test handler
     */
    public function ajax_test_simple(): void {
        \wp_send_json_success(['message' => 'Simple test successful']);
    }
    


    /**
     * AJAX: Test connection
     */
        public function ajax_test_connection(): void {
        // Set proper headers for JSON response
        header('Content-Type: application/json');
        
        try {
            // Basic error handling
            if (!isset($_POST['nonce'])) {
                wp_send_json_error(['message' => 'Nonce is required']);
                return;
            }

                    // Check for multiple nonce formats to support both global and form-specific
        $nonce_verified = \wp_verify_nonce($_POST['nonce'], 'mavlers_cf_nonce') || 
                         \wp_verify_nonce($_POST['nonce'], 'mavlers_cf_integrations_nonce');
        
        if (!$nonce_verified) {
            \wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Check for multiple capabilities to support different user roles
        $has_permission = \current_user_can('manage_options') || 
                         \current_user_can('administrator') || 
                         \current_user_can('edit_posts');
        
        if (!$has_permission) {
            \wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        // Get credentials from the generic template approach
        // Handle both formats: direct POST data and nested credentials
        $credentials = $_POST;
        
        // If credentials are nested, extract them
        if (isset($_POST['credentials']) && is_array($_POST['credentials'])) {
            $credentials = $_POST['credentials'];
        }
        
        // Extract required fields from the credentials
        $access_token = $credentials['access_token'] ?? '';
        $portal_id = $credentials['portal_id'] ?? '';
        
        // Sanitize the values
        if (!empty($access_token)) {
            $access_token = \sanitize_text_field($access_token);
        }
        if (!empty($portal_id)) {
            $portal_id = \sanitize_text_field($portal_id);
        }

        if (empty($access_token)) {
            \wp_send_json_error(['message' => 'Access token is required']);
        }

        if (empty($portal_id)) {
            \wp_send_json_error(['message' => 'Portal ID is required']);
        }

        $test_credentials = [
            'access_token' => $access_token,
            'portal_id' => $portal_id
        ];

        $result = $this->testConnection($test_credentials);
        \wp_send_json($result);
        } catch (Exception $e) {
            \wp_send_json_error(['message' => 'Test connection failed: ' . $e->getMessage()]);
        } catch (Error $e) {
            \wp_send_json_error(['message' => 'Test connection failed: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Save global settings
     */
    public function ajax_save_global_settings(): void {
        // Check for multiple nonce formats to support both global and form-specific
        $nonce_verified = false;
        
        if (isset($_POST['nonce'])) {
            $nonce_verified = wp_verify_nonce($_POST['nonce'], 'mavlers_cf_nonce') || 
                             wp_verify_nonce($_POST['nonce'], 'mavlers_cf_integrations_nonce');
        }
        
        if (!$nonce_verified) {
            wp_die(__('Security check failed', 'mavlers-contact-forms'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        // Get settings from the generic template approach
        $settings = $_POST['settings'] ?? [];
        

        
        if (!is_array($settings)) {
            wp_send_json_error(['message' => 'Invalid settings format']);
        }

        // Sanitize settings
        $sanitized_settings = [];
        foreach ($settings as $key => $value) {
            if (is_string($value)) {
                $sanitized_settings[sanitize_key($key)] = sanitize_text_field($value);
            } else {
                $sanitized_settings[sanitize_key($key)] = $value;
            }
        }

        // Validate required fields
        if (empty($sanitized_settings['access_token'])) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        if (empty($sanitized_settings['portal_id'])) {
            wp_send_json_error(['message' => 'Portal ID is required']);
        }

        // Save settings using the abstract method
        $result = $this->saveGlobalSettings($sanitized_settings);

        if ($result) {
            wp_send_json_success(['message' => 'Settings saved successfully!', 'configured' => true]);
        } else {
            wp_send_json_error(['message' => 'Failed to save settings']);
        }
    }

    /**
     * AJAX: Get global settings
     */
    public function ajax_get_global_settings(): void {
        // Check for multiple nonce formats to support both global and form-specific
        $nonce_verified = false;
        
        if (isset($_POST['nonce'])) {
            $nonce_verified = wp_verify_nonce($_POST['nonce'], 'mavlers_cf_nonce') || 
                             wp_verify_nonce($_POST['nonce'], 'mavlers_cf_integrations_nonce');
        }
        
        if (!$nonce_verified) {
            wp_die(__('Security check failed', 'mavlers-contact-forms'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        // Get global settings using the abstract method
        $settings = $this->getGlobalSettings();

        if ($settings) {
            wp_send_json_success($settings);
        } else {
            wp_send_json_error(['message' => 'No settings found']);
        }
    }

    /**
     * AJAX: Get contacts
     */
    public function ajax_get_contacts(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $hubspot_api_client = new HubspotApiClient();
        $result = $hubspot_api_client->getContacts($access_token);
        wp_send_json($result);
    }

    /**
     * AJAX: Get companies
     */
    public function ajax_get_companies(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $hubspot_api_client = new HubspotApiClient();
        $result = $hubspot_api_client->getCompanies($access_token);
        wp_send_json($result);
    }

    /**
     * AJAX: Get deals
     */
    public function ajax_get_deals(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $hubspot_api_client = new HubspotApiClient();
        $result = $hubspot_api_client->getDeals($access_token);
        wp_send_json($result);
    }

    /**
     * AJAX: Get contact properties
     */
    public function ajax_get_contact_properties(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $result = $this->contact_manager->getContactProperties($access_token);
        wp_send_json($result);
    }

    /**
     * AJAX: Get deal properties
     */
    public function ajax_get_deal_properties(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $result = $this->deal_manager->getDealProperties($access_token);
        wp_send_json($result);
    }

    /**
     * AJAX: Get company properties
     */
    public function ajax_get_company_properties(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $hubspot_api_client = new HubspotApiClient();
        $result = $hubspot_api_client->getCompanyProperties($access_token);
        wp_send_json($result);
    }

    /**
     * AJAX: Get workflows
     */
    public function ajax_get_workflows(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $result = $this->workflow_manager->getWorkflows($access_token);
        wp_send_json($result);
    }

    /**
     * AJAX: Get custom objects
     */
    public function ajax_get_custom_objects(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $result = $this->custom_object_manager->getCustomObjects($access_token);
        wp_send_json($result);
    }

    /**
     * AJAX: Get custom object properties
     */
    public function ajax_get_custom_object_properties(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $object_name = sanitize_text_field($_POST['object_name'] ?? '');
        
        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }
        
        if (empty($object_name)) {
            wp_send_json_error(['message' => 'Object name is required']);
        }

        $result = $this->custom_object_manager->getCustomObjectProperties($access_token, $object_name);
        wp_send_json($result);
    }

    /**
     * AJAX: Save form settings
     */
    public function ajax_save_form_settings(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        $settings = $_POST['settings'] ?? [];

        if ($form_id <= 0) {
            wp_send_json_error('Invalid form ID');
        }

        // Sanitize settings
        $sanitized_settings = [];
        foreach ($settings as $key => $value) {
            $sanitized_settings[sanitize_key($key)] = sanitize_text_field($value);
        }

        $result = $this->saveFormSettings($form_id, $sanitized_settings);
        wp_send_json(['success' => $result]);
    }

    /**
     * AJAX: Get form fields
     */
    public function ajax_get_form_fields(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error('Invalid form ID');
        }

        $fields = $this->get_form_fields($form_id);
        wp_send_json_success($fields);
    }

    /**
     * AJAX: Auto map fields
     */
    public function ajax_auto_map_fields(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error('Invalid form ID');
        }

        $mapping = $this->generate_automatic_mapping($form_id);
        wp_send_json_success($mapping);
    }

    /**
     * Get form fields
     */
    private function get_form_fields(int $form_id): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_cf_forms';
        
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $form_id
        ));
        
        if (!$form) {
            return [];
        }

        // Check if fields property exists, if not try other possible column names
        $form_data_json = null;
        if (isset($form->fields)) {
            $form_data_json = $form->fields;
        } elseif (isset($form->form_data)) {
            $form_data_json = $form->form_data;
        } elseif (isset($form->data)) {
            $form_data_json = $form->data;
        } elseif (isset($form->form_json)) {
            $form_data_json = $form->form_json;
        } else {
            return [];
        }
        
        $form_data = json_decode($form_data_json, true);
        
        if (!$form_data) {
            return [];
        }
        
        $fields = [];

        // Check if form_data itself is the fields array
        if (is_array($form_data) && isset($form_data[0]) && isset($form_data[0]['id'])) {
            foreach ($form_data as $field) {
                if (isset($field['id']) && isset($field['label'])) {
                    $fields[] = [
                        'id' => $field['id'],
                        'label' => $field['label'],
                        'type' => $field['type'] ?? 'text'
                    ];
                }
            }
        } elseif (isset($form_data['fields']) && is_array($form_data['fields'])) {
            foreach ($form_data['fields'] as $field) {
                if (isset($field['id']) && isset($field['label'])) {
                    $fields[] = [
                        'id' => $field['id'],
                        'label' => $field['label'],
                        'type' => $field['type'] ?? 'text'
                    ];
                }
            }
        }

        return $fields;
    }

    /**
     * Generate automatic field mapping
     */
    private function generate_automatic_mapping(int $form_id): array {
        $fields = $this->get_form_fields($form_id);
        $mapping = [];

        foreach ($fields as $field) {
            $field_label = strtolower($field['label']);
            $field_id = strtolower($field['id']);

            // Common field mappings
            $common_mappings = [
                'email' => ['email', 'e-mail', 'mail', 'email_address'],
                'firstname' => ['first name', 'firstname', 'fname', 'first_name'],
                'lastname' => ['last name', 'lastname', 'lname', 'last_name'],
                'phone' => ['phone', 'telephone', 'tel', 'mobile'],
                'company' => ['company', 'organization', 'org', 'business'],
                'jobtitle' => ['job title', 'jobtitle', 'position', 'title'],
                'website' => ['website', 'site', 'url', 'web'],
                'address' => ['address', 'street', 'location'],
                'city' => ['city', 'town'],
                'state' => ['state', 'province', 'region'],
                'zip' => ['zip', 'postal', 'postcode', 'zipcode']
            ];

            foreach ($common_mappings as $hubspot_field => $possible_values) {
                foreach ($possible_values as $value) {
                    if (strpos($field_label, $value) !== false || strpos($field_id, $value) !== false) {
                        $mapping[$hubspot_field] = $field['id'];
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Enqueue assets
     */
    public function enqueue_comprehensive_assets($hook): void {
        // Only enqueue on our admin pages
        if (strpos($hook, 'mavlers-contact-forms') === false) {
            return;
        }

        // Enqueue HubSpot specific assets
        \wp_enqueue_script(
            'mavlers-cf-hubspot-admin',
            \plugin_dir_url(__FILE__) . '../../../assets/js/admin/hubspot-form.js',
            ['jquery'],
            '1.0.0',
            true
        );

        \wp_enqueue_style(
            'mavlers-cf-hubspot-admin',
            \plugin_dir_url(__FILE__) . '../../../assets/css/admin/hubspot.css',
            [],
            '1.0.0'
        );

        // Localize script
        \wp_localize_script('mavlers-cf-hubspot-admin', 'mavlersCFHubspot', [
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('mavlers_cf_integrations_nonce'),
            'formNonce' => \wp_create_nonce('mavlers_cf_nonce'),
            'strings' => [
                'testConnection' => \__('Test Connection', 'mavlers-contact-forms'),
                'connectionSuccess' => \__('Connection successful!', 'mavlers-contact-forms'),
                'connectionFailed' => \__('Connection failed', 'mavlers-contact-forms'),
                'saveSettings' => \__('Save Settings', 'mavlers-contact-forms'),
                'settingsSaved' => \__('Settings saved successfully!', 'mavlers-contact-forms'),
                'settingsFailed' => \__('Failed to save settings', 'mavlers-contact-forms'),
                'clearMappingsConfirm' => \__('Are you sure you want to clear all field mappings?', 'mavlers-contact-forms')
            ]
        ]);
    }

    // ========================================
    // FORM-SPECIFIC AJAX HANDLERS
    // ========================================

    /**
     * AJAX: Form - Save settings
     */
    public function ajax_form_save_settings(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        // Collect settings from individual POST fields
        $settings = [
            'enabled' => sanitize_text_field($_POST['enabled'] ?? ''),
            'object_type' => sanitize_text_field($_POST['object_type'] ?? ''),
            'custom_object_name' => sanitize_text_field($_POST['custom_object_name'] ?? ''),
            'action_type' => sanitize_text_field($_POST['action_type'] ?? 'create_or_update'),
            'workflow_enabled' => sanitize_text_field($_POST['workflow_enabled'] ?? ''),
            'field_mapping' => $_POST['field_mapping'] ?? []
        ];

        // Debug logging
        if (current_user_can('manage_options')) {
            error_log('DEBUG: HubSpot saving form settings - Form ID: ' . $form_id);
            error_log('DEBUG: Raw POST field_mapping: ' . print_r($_POST['field_mapping'] ?? 'not set', true));
            error_log('DEBUG: Settings to save: ' . print_r($settings, true));
        }

        $result = $this->saveFormSettings($form_id, $settings);
        
        // Debug logging
        if (current_user_can('manage_options')) {
            error_log('DEBUG: HubSpot save result: ' . ($result ? 'success' : 'failed'));
        }
        
        if ($result) {
            wp_send_json_success(['message' => 'Settings saved successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to save settings']);
        }
    }

    /**
     * AJAX: Simple test handler for form-specific requests
     */
    public function ajax_form_test(): void {
        error_log('HubSpot: ajax_form_test called');
        \wp_send_json_success(['message' => 'Form test successful']);
    }
    
    /**
     * AJAX: Test handler for form fields
     */
    public function ajax_form_get_fields_test(): void {
        error_log('HubSpot: ajax_form_get_fields_test called');
        \wp_send_json_success(['message' => 'Form fields test successful']);
    }
    
    /**
     * AJAX: Form - Get form fields
     */
    public function ajax_form_get_fields(): void {
        try {
            // Check for either nonce format
            $nonce_valid = \wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                          \wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

            if (!$nonce_valid) {
                \wp_send_json_error(['message' => 'Security check failed']);
                return;
            }

            // Use edit_posts capability for form settings
            if (!\current_user_can('edit_posts')) {
                \wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $form_id = intval($_POST['form_id'] ?? 0);
            
            if ($form_id <= 0) {
                \wp_send_json_error(['message' => 'Invalid form ID']);
                return;
            }

            $fields = $this->get_form_fields($form_id);
            \wp_send_json_success(['fields' => $fields]);
            
        } catch (\Exception $e) {
            \wp_send_json_error(['message' => 'Internal server error: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Form - Auto map fields
     */
    public function ajax_form_auto_map_fields(): void {
        // Check for either nonce format
        $nonce_valid = \wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      \wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            \wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!\current_user_can('edit_posts')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            \wp_send_json_error(['message' => 'Invalid form ID']);
        }

        $mapping = $this->generate_automatic_mapping($form_id);
        \wp_send_json_success(['mapping' => $mapping, 'message' => 'Field mapping generated successfully!']);
    }

    /**
     * AJAX: Form - Get contacts
     */
    public function ajax_form_get_contacts(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $hubspot_api_client = new HubspotApiClient();
        $result = $hubspot_api_client->getContacts($access_token);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['contacts' => $result['data'] ?? [], 'message' => 'Contacts loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load contacts']);
        }
    }

    /**
     * AJAX: Form - Get companies
     */
    public function ajax_form_get_companies(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $hubspot_api_client = new HubspotApiClient();
        $result = $hubspot_api_client->getCompanies($access_token);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['companies' => $result['data'] ?? [], 'message' => 'Companies loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load companies']);
        }
    }

    /**
     * AJAX: Form - Get deals
     */
    public function ajax_form_get_deals(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $hubspot_api_client = new HubspotApiClient();
        $result = $hubspot_api_client->getDeals($access_token);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['deals' => $result['data'] ?? [], 'message' => 'Deals loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load deals']);
        }
    }

    /**
     * AJAX: Form - Get contact properties
     */
    public function ajax_form_get_contact_properties(): void {
        error_log('HubSpot: ajax_form_get_contact_properties called');
        
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $result = $this->contact_manager->getContactProperties($access_token);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['properties' => $result['data'] ?? [], 'message' => 'Contact properties loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load contact properties']);
        }
    }

    /**
     * AJAX: Form - Get deal properties
     */
    public function ajax_form_get_deal_properties(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $result = $this->deal_manager->getDealProperties($access_token);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['properties' => $result['data'] ?? [], 'message' => 'Deal properties loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load deal properties']);
        }
    }

    /**
     * AJAX: Form - Get company properties
     */
    public function ajax_form_get_company_properties(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $hubspot_api_client = new HubspotApiClient();
        $result = $hubspot_api_client->getCompanyProperties($access_token);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['properties' => $result['data'] ?? [], 'message' => 'Company properties loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load company properties']);
        }
    }

    /**
     * AJAX: Form - Get workflows
     */
    public function ajax_form_get_workflows(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $result = $this->workflow_manager->getWorkflows($access_token);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['workflows' => $result['data'] ?? [], 'message' => 'Workflows loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load workflows']);
        }
    }

    /**
     * AJAX: Form - Get custom objects
     */
    public function ajax_form_get_custom_objects(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        $result = $this->custom_object_manager->getCustomObjects($access_token);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['objects' => $result['data'] ?? [], 'message' => 'Custom objects loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load custom objects']);
        }
    }

    /**
     * AJAX: Form - Get custom object properties
     */
    public function ajax_form_get_custom_object_properties(): void {
        // Check for either nonce format
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce') ||
                      wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Use edit_posts capability for form settings
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');
        $object_name = sanitize_text_field($_POST['object_name'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id <= 0) {
            wp_send_json_error(['message' => 'Invalid form ID']);
        }

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Access token is required']);
        }

        if (empty($object_name)) {
            wp_send_json_error(['message' => 'Object name is required']);
        }

        $result = $this->custom_object_manager->getCustomObjectProperties($access_token, $object_name);
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success(['properties' => $result['data'] ?? [], 'message' => 'Custom object properties loaded successfully!']);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? 'Failed to load custom object properties']);
        }
    }
} 