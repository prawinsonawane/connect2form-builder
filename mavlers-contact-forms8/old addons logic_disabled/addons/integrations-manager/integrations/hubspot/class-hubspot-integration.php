<?php
/**
 * Enhanced HubSpot Integration
 *
 * Advanced HubSpot integration with contact management, deals, pipelines, and workflow automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_HubSpot_Integration extends Mavlers_CF_Base_Integration {

    /**
     * Integration configuration
     */
    protected $integration_id = 'hubspot';
    protected $integration_name = 'HubSpot';
    protected $integration_description = 'Complete HubSpot CRM integration with contact management, deal creation, and workflow automation';
    protected $integration_version = '2.0.0';
    protected $integration_icon = 'dashicons-businessman';
    protected $integration_color = '#ff7a59';
    protected $api_base_url = 'https://api.hubapi.com/';

    /**
     * Get integration ID
     */
    public function get_integration_id() {
        return $this->integration_id;
    }

    /**
     * Get integration name
     */
    public function get_integration_name() {
        return $this->integration_name;
    }

    /**
     * Get integration description
     */
    public function get_integration_description() {
        return $this->integration_description;
    }

    /**
     * Get integration version
     */
    public function get_integration_version() {
        return $this->integration_version;
    }

    /**
     * Get available actions
     */
    public function get_available_actions() {
        return array(
            'create_contact' => array(
                'label' => __('Create Contact', 'mavlers-contact-forms'),
                'description' => __('Create a new contact in HubSpot', 'mavlers-contact-forms')
            ),
            'create_deal' => array(
                'label' => __('Create Deal', 'mavlers-contact-forms'),
                'description' => __('Create a new deal in HubSpot', 'mavlers-contact-forms')
            ),
            'create_contact_and_deal' => array(
                'label' => __('Create Contact & Deal', 'mavlers-contact-forms'),
                'description' => __('Create both contact and deal in HubSpot', 'mavlers-contact-forms')
            )
        );
    }

    /**
     * Get available fields for mapping
     */
    public function get_available_fields() {
        return $this->get_integration_fields();
    }

    /**
     * Handle form submission
     */
    public function handle_submission($action, $form_fields, $settings, $field_mappings) {
        return $this->process_submission($form_fields, $settings, $field_mappings);
    }

    /**
     * Get authentication fields
     */
    public function get_auth_fields() {
        return array(
            'api_key' => array(
                'label' => __('API Key', 'mavlers-contact-forms'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your HubSpot API key', 'mavlers-contact-forms')
            )
        );
    }

    /**
     * Get action fields
     */
    public function get_action_fields() {
        return array(
            'action_type' => array(
                'label' => __('Action', 'mavlers-contact-forms'),
                'type' => 'select',
                'required' => true,
                'options' => array(
                    'create_contact' => __('Create Contact', 'mavlers-contact-forms'),
                    'create_deal' => __('Create Deal', 'mavlers-contact-forms'),
                    'create_contact_and_deal' => __('Create Contact & Deal', 'mavlers-contact-forms')
                ),
                'default' => 'create_contact'
            ),
            'lifecycle_stage' => array(
                'label' => __('Lifecycle Stage', 'mavlers-contact-forms'),
                'type' => 'select',
                'options' => array(
                    'subscriber' => __('Subscriber', 'mavlers-contact-forms'),
                    'lead' => __('Lead', 'mavlers-contact-forms'),
                    'customer' => __('Customer', 'mavlers-contact-forms')
                ),
                'default' => 'lead'
            )
        );
    }

    /**
     * Get integration fields
     */
    public function get_integration_fields() {
        return array(
            'email' => array(
                'key' => 'email',
                'label' => __('Email Address', 'mavlers-contact-forms'),
                'type' => 'email',
                'required' => true,
                'description' => __('Primary email address for the contact', 'mavlers-contact-forms')
            ),
            'firstname' => array(
                'key' => 'firstname',
                'label' => __('First Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'lastname' => array(
                'key' => 'lastname',
                'label' => __('Last Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'phone' => array(
                'key' => 'phone',
                'label' => __('Phone Number', 'mavlers-contact-forms'),
                'type' => 'phone',
                'required' => false
            ),
            'company' => array(
                'key' => 'company',
                'label' => __('Company', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'jobtitle' => array(
                'key' => 'jobtitle',
                'label' => __('Job Title', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'website' => array(
                'key' => 'website',
                'label' => __('Website', 'mavlers-contact-forms'),
                'type' => 'url',
                'required' => false
            ),
            'city' => array(
                'key' => 'city',
                'label' => __('City', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'state' => array(
                'key' => 'state',
                'label' => __('State/Province', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'country' => array(
                'key' => 'country',
                'label' => __('Country', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'zip' => array(
                'key' => 'zip',
                'label' => __('ZIP/Postal Code', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'message' => array(
                'key' => 'message',
                'label' => __('Message/Notes', 'mavlers-contact-forms'),
                'type' => 'textarea',
                'required' => false,
                'description' => __('Custom message or notes about the contact', 'mavlers-contact-forms')
            ),
            'deal_name' => array(
                'key' => 'deal_name',
                'label' => __('Deal Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false,
                'description' => __('Name for the deal (if creating deals)', 'mavlers-contact-forms')
            ),
            'deal_amount' => array(
                'key' => 'deal_amount',
                'label' => __('Deal Amount', 'mavlers-contact-forms'),
                'type' => 'number',
                'required' => false,
                'description' => __('Deal value (if creating deals)', 'mavlers-contact-forms')
            )
        );
    }

    /**
     * Test connection
     */
    public function test_connection($credentials) {
        try {
            if (!$this->api_client) {
                throw new Exception(__('API client not available', 'mavlers-contact-forms'));
            }
            
            $headers = $this->get_auth_headers($credentials);
            
            $response = $this->api_client->request(
                'GET',
                $this->api_base_url . 'crm/v3/owners',
                array(),
                $headers
            );

            if ($response['success']) {
                return array(
                    'success' => true,
                    'message' => __('Successfully connected to HubSpot', 'mavlers-contact-forms')
                );
            }

            return array(
                'success' => false,
                'message' => __('Invalid credentials or connection failed', 'mavlers-contact-forms')
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Process form submission
     */
    public function process_submission($form_data, $settings, $field_mappings) {
        try {
            $headers = $this->get_auth_headers($settings);
            $action_type = $settings['action_type'] ?? 'create_contact';

            // Map form data to HubSpot fields
            $contact_data = $this->map_contact_data($form_data, $field_mappings);
            
            if (empty($contact_data['email'])) {
                throw new Exception(__('Email address is required', 'mavlers-contact-forms'));
            }

            // Execute the appropriate action
            switch ($action_type) {
                case 'create_contact':
                    return $this->create_contact($contact_data, $settings, $headers);
                
                case 'update_contact':
                    return $this->update_contact($contact_data, $settings, $headers);
                
                case 'create_deal':
                    return $this->create_deal($contact_data, $settings, $headers);
                
                case 'create_contact_and_deal':
                    return $this->create_contact_and_deal($contact_data, $settings, $headers);
                
                case 'add_to_workflow':
                    return $this->add_to_workflow($contact_data, $settings, $headers);
                
                default:
                    throw new Exception(__('Invalid action type', 'mavlers-contact-forms'));
            }

        } catch (Exception $e) {
            $this->logger->error('HubSpot submission failed', array(
                'error' => $e->getMessage(),
                'form_data' => $form_data,
                'settings' => $settings
            ));

            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Create contact
     */
    private function create_contact($contact_data, $settings, $headers) {
        // Prepare properties
        $properties = array();
        foreach ($contact_data as $key => $value) {
            if (!empty($value) && !in_array($key, array('deal_name', 'deal_amount'))) {
                $properties[$key] = $value;
            }
        }

        // Add lifecycle stage
        if (!empty($settings['lifecycle_stage'])) {
            $properties['lifecyclestage'] = $settings['lifecycle_stage'];
        }

        // Add owner
        if (!empty($settings['owner_id'])) {
            $properties['hubspot_owner_id'] = $settings['owner_id'];
        }

        $data = array(
            'properties' => $properties
        );

        $response = $this->api_client->request(
            'POST',
            $this->api_base_url . 'crm/v3/objects/contacts',
            $data,
            $headers
        );

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Contact created successfully in HubSpot', 'mavlers-contact-forms'),
                'data' => $response['data']
            );
        }

        throw new Exception($response['message'] ?? __('Failed to create contact', 'mavlers-contact-forms'));
    }

    /**
     * Update contact
     */
    private function update_contact($contact_data, $settings, $headers) {
        $email = $contact_data['email'];
        
        // Find contact by email
        $search_response = $this->api_client->request(
            'POST',
            $this->api_base_url . 'crm/v3/objects/contacts/search',
            array(
                'filterGroups' => array(
                    array(
                        'filters' => array(
                            array(
                                'propertyName' => 'email',
                                'operator' => 'EQ',
                                'value' => $email
                            )
                        )
                    )
                )
            ),
            $headers
        );

        if (!$search_response['success'] || empty($search_response['data']['results'])) {
            throw new Exception(__('Contact not found', 'mavlers-contact-forms'));
        }

        $contact_id = $search_response['data']['results'][0]['id'];

        // Prepare properties
        $properties = array();
        foreach ($contact_data as $key => $value) {
            if (!empty($value) && !in_array($key, array('deal_name', 'deal_amount'))) {
                $properties[$key] = $value;
            }
        }

        $data = array(
            'properties' => $properties
        );

        $response = $this->api_client->request(
            'PATCH',
            $this->api_base_url . "crm/v3/objects/contacts/{$contact_id}",
            $data,
            $headers
        );

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Contact updated successfully in HubSpot', 'mavlers-contact-forms'),
                'data' => $response['data']
            );
        }

        throw new Exception($response['message'] ?? __('Failed to update contact', 'mavlers-contact-forms'));
    }

    /**
     * Create deal
     */
    private function create_deal($contact_data, $settings, $headers) {
        // First find or create contact
        $contact_result = $this->find_or_create_contact($contact_data, $settings, $headers);
        $contact_id = $contact_result['id'];

        // Prepare deal properties
        $deal_properties = array(
            'dealname' => $contact_data['deal_name'] ?? 'New Deal from ' . ($contact_data['firstname'] ?? 'Contact'),
            'amount' => $contact_data['deal_amount'] ?? $settings['deal_amount'] ?? 0,
            'pipeline' => $settings['pipeline_id'],
            'dealstage' => $settings['deal_stage']
        );

        if (!empty($settings['owner_id'])) {
            $deal_properties['hubspot_owner_id'] = $settings['owner_id'];
        }

        $deal_data = array(
            'properties' => $deal_properties,
            'associations' => array(
                array(
                    'to' => array('id' => $contact_id),
                    'types' => array(
                        array(
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 3
                        )
                    )
                )
            )
        );

        $response = $this->api_client->request(
            'POST',
            $this->api_base_url . 'crm/v3/objects/deals',
            $deal_data,
            $headers
        );

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Deal created successfully in HubSpot', 'mavlers-contact-forms'),
                'data' => $response['data']
            );
        }

        throw new Exception($response['message'] ?? __('Failed to create deal', 'mavlers-contact-forms'));
    }

    /**
     * Create contact and deal
     */
    private function create_contact_and_deal($contact_data, $settings, $headers) {
        // Create contact first
        $contact_result = $this->create_contact($contact_data, $settings, $headers);
        
        if (!$contact_result['success']) {
            return $contact_result;
        }

        // Then create deal
        $deal_result = $this->create_deal($contact_data, $settings, $headers);
        
        return array(
            'success' => true,
            'message' => __('Contact and deal created successfully in HubSpot', 'mavlers-contact-forms'),
            'data' => array(
                'contact' => $contact_result['data'],
                'deal' => $deal_result['data']
            )
        );
    }

    /**
     * Add to workflow
     */
    private function add_to_workflow($contact_data, $settings, $headers) {
        // First find or create contact
        $contact_result = $this->find_or_create_contact($contact_data, $settings, $headers);
        $contact_id = $contact_result['id'];

        $workflow_data = array(
            'contactIds' => array($contact_id)
        );

        $response = $this->api_client->request(
            'POST',
            $this->api_base_url . "automation/v2/workflows/{$settings['workflow_id']}/enrollments/contacts/enroll",
            $workflow_data,
            $headers
        );

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Contact added to workflow successfully', 'mavlers-contact-forms'),
                'data' => $response['data']
            );
        }

        throw new Exception($response['message'] ?? __('Failed to add contact to workflow', 'mavlers-contact-forms'));
    }

    /**
     * Get dynamic field options
     */
    public function get_dynamic_field_options($field_key, $credentials, $settings = array()) {
        try {
            $headers = $this->get_auth_headers($credentials);

            switch ($field_key) {
                case 'owner_id':
                    return $this->get_owners($headers);
                
                case 'pipeline_id':
                    return $this->get_pipelines($headers);
                
                case 'deal_stage':
                    if (empty($settings['pipeline_id'])) {
                        return array();
                    }
                    return $this->get_deal_stages($headers, $settings['pipeline_id']);
                
                case 'workflow_id':
                    return $this->get_workflows($headers);
                
                default:
                    return array();
            }

        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Get HubSpot owners
     */
    private function get_owners($headers) {
        $response = $this->api_client->request(
            'GET',
            $this->api_base_url . 'crm/v3/owners',
            array(),
            $headers
        );

        if (!$response['success'] || empty($response['data']['results'])) {
            return array();
        }

        $owners = array();
        foreach ($response['data']['results'] as $owner) {
            $owners[$owner['id']] = $owner['firstName'] . ' ' . $owner['lastName'] . ' (' . $owner['email'] . ')';
        }

        return $owners;
    }

    /**
     * Get deal pipelines
     */
    private function get_pipelines($headers) {
        $response = $this->api_client->request(
            'GET',
            $this->api_base_url . 'crm/v3/pipelines/deals',
            array(),
            $headers
        );

        if (!$response['success'] || empty($response['data']['results'])) {
            return array();
        }

        $pipelines = array();
        foreach ($response['data']['results'] as $pipeline) {
            $pipelines[$pipeline['id']] = $pipeline['label'];
        }

        return $pipelines;
    }

    /**
     * Get deal stages for pipeline
     */
    private function get_deal_stages($headers, $pipeline_id) {
        $response = $this->api_client->request(
            'GET',
            $this->api_base_url . "crm/v3/pipelines/deals/{$pipeline_id}",
            array(),
            $headers
        );

        if (!$response['success'] || empty($response['data']['stages'])) {
            return array();
        }

        $stages = array();
        foreach ($response['data']['stages'] as $stage) {
            $stages[$stage['id']] = $stage['label'];
        }

        return $stages;
    }

    /**
     * Get workflows
     */
    private function get_workflows($headers) {
        $response = $this->api_client->request(
            'GET',
            $this->api_base_url . 'automation/v3/workflows',
            array(),
            $headers
        );

        if (!$response['success'] || empty($response['data']['workflows'])) {
            return array();
        }

        $workflows = array();
        foreach ($response['data']['workflows'] as $workflow) {
            if ($workflow['enabled']) {
                $workflows[$workflow['id']] = $workflow['name'];
            }
        }

        return $workflows;
    }

    /**
     * Map form data to contact data
     */
    private function map_contact_data($form_data, $field_mappings) {
        $contact_data = array();

        foreach ($field_mappings as $mapping) {
            $form_field = $mapping['form_field'];
            $integration_field = $mapping['integration_field'];

            if (isset($form_data[$form_field])) {
                $contact_data[$integration_field] = $form_data[$form_field];
            }
        }

        return $contact_data;
    }

    /**
     * Find or create contact
     */
    private function find_or_create_contact($contact_data, $settings, $headers) {
        $email = $contact_data['email'];
        
        // First try to find existing contact
        $search_response = $this->api_client->request(
            'POST',
            $this->api_base_url . 'crm/v3/objects/contacts/search',
            array(
                'filterGroups' => array(
                    array(
                        'filters' => array(
                            array(
                                'propertyName' => 'email',
                                'operator' => 'EQ',
                                'value' => $email
                            )
                        )
                    )
                )
            ),
            $headers
        );

        if ($search_response['success'] && !empty($search_response['data']['results'])) {
            return $search_response['data']['results'][0];
        }

        // Create new contact if not found
        $create_result = $this->create_contact($contact_data, $settings, $headers);
        if (!$create_result['success']) {
            throw new Exception('Failed to create contact');
        }

        return $create_result['data'];
    }

    /**
     * Get authentication headers
     */
    private function get_auth_headers($credentials) {
        if (!empty($credentials['access_token'])) {
            return array(
                'Authorization' => 'Bearer ' . $credentials['access_token']
            );
        } elseif (!empty($credentials['api_key'])) {
            return array(
                'Authorization' => 'Bearer ' . $credentials['api_key']
            );
        }

        throw new Exception(__('No authentication credentials provided', 'mavlers-contact-forms'));
    }

    /**
     * Get OAuth URL
     */
    public function get_oauth_url($params = array()) {
        $client_id = get_option('mavlers_cf_hubspot_client_id');
        $redirect_uri = admin_url('admin-ajax.php?action=mavlers_cf_hubspot_oauth_callback');
        
        // Merge default scopes with any passed params
        $default_scopes = 'crm.objects.contacts.write%20crm.objects.deals.write%20automation';
        $scopes = isset($params['scope']) ? urlencode($params['scope']) : $default_scopes;
        
        $url = "https://app.hubspot.com/oauth/authorize?client_id={$client_id}&redirect_uri=" . urlencode($redirect_uri) . "&scope={$scopes}";
        
        // Add any additional parameters
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                if ($key !== 'scope') { // scope already handled above
                    $url .= '&' . urlencode($key) . '=' . urlencode($value);
                }
            }
        }
        
        return $url;
    }
}

// Register the integration
add_action('mavlers_cf_register_integrations', function($registry) {
    if (class_exists('Mavlers_CF_HubSpot_Integration')) {
        $integration = new Mavlers_CF_HubSpot_Integration();
        $registry->register_integration($integration);
    }
}); 