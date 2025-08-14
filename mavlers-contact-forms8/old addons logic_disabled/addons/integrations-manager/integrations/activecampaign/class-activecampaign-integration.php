<?php
/**
 * ActiveCampaign Integration
 *
 * ActiveCampaign integration for email marketing automation, contact management, and automation workflows
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_ActiveCampaign_Integration extends Mavlers_CF_Base_Integration {

    /**
     * Integration configuration
     */
    protected $integration_id = 'activecampaign';
    protected $integration_name = 'ActiveCampaign';
    protected $integration_description = 'Professional email marketing automation with ActiveCampaign contacts, lists, and automation workflows';
    protected $integration_version = '1.0.0';
    protected $integration_icon = 'dashicons-email-alt';
    protected $integration_color = '#356ae6';
    protected $api_base_url = 'https://{account}.api-us1.com/api/3/';

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
            'add_contact' => array(
                'label' => __('Add Contact', 'mavlers-contact-forms'),
                'description' => __('Add contact to ActiveCampaign', 'mavlers-contact-forms')
            ),
            'add_to_list' => array(
                'label' => __('Add to List', 'mavlers-contact-forms'),
                'description' => __('Add contact to specific list', 'mavlers-contact-forms')
            ),
            'add_tags' => array(
                'label' => __('Add Tags', 'mavlers-contact-forms'),
                'description' => __('Add tags to contact', 'mavlers-contact-forms')
            )
        );
    }

    /**
     * Get available fields for mapping
     */
    public function get_available_fields() {
        return array(
            'email' => array(
                'label' => __('Email Address', 'mavlers-contact-forms'),
                'type' => 'email',
                'required' => true
            ),
            'firstName' => array(
                'label' => __('First Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'lastName' => array(
                'label' => __('Last Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'phone' => array(
                'label' => __('Phone Number', 'mavlers-contact-forms'),
                'type' => 'phone',
                'required' => false
            )
        );
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
            'api_url' => array(
                'label' => __('API URL', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your ActiveCampaign API URL (e.g., https://youraccountname.api-us1.com)', 'mavlers-contact-forms'),
                'placeholder' => 'https://youraccountname.api-us1.com'
            ),
            'api_key' => array(
                'label' => __('API Key', 'mavlers-contact-forms'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your ActiveCampaign API key', 'mavlers-contact-forms')
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
            
            $api_url = rtrim($credentials['api_url'], '/');
            $api_key = $credentials['api_key'];
            
            $response = $this->api_client->request(
                'GET',
                $api_url . '/api/3/users/me',
                array(),
                array(
                    'Api-Token' => $api_key
                )
            );

            if ($response['success'] && isset($response['data']['user'])) {
                return array(
                    'success' => true,
                    'message' => __('Successfully connected to ActiveCampaign', 'mavlers-contact-forms')
                );
            }

            return array(
                'success' => false,
                'message' => __('Invalid API credentials', 'mavlers-contact-forms')
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
            if (!$this->api_client) {
                throw new Exception(__('API client not available', 'mavlers-contact-forms'));
            }
            
            $contact_data = $this->map_contact_data($form_data, $field_mappings);
            
            if (empty($contact_data['email'])) {
                throw new Exception(__('Email address is required', 'mavlers-contact-forms'));
            }

            return $this->add_contact($contact_data, $settings);

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('ActiveCampaign submission failed', array(
                    'error' => $e->getMessage(),
                    'form_data' => $form_data,
                    'settings' => $settings
                ));
            }

            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Add contact to ActiveCampaign
     */
    private function add_contact($contact_data, $settings) {
        $api_url = rtrim($settings['api_url'], '/');
        $api_key = $settings['api_key'];

        $data = array(
            'contact' => $contact_data
        );

        $response = $this->api_client->request(
            'POST',
            $api_url . '/api/3/contacts',
            $data,
            array(
                'Api-Token' => $api_key,
                'Content-Type' => 'application/json'
            )
        );

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Successfully added contact to ActiveCampaign', 'mavlers-contact-forms'),
                'data' => $response['data']
            );
        }

        throw new Exception(__('Failed to add contact', 'mavlers-contact-forms'));
    }

    /**
     * Map form data to contact data
     */
    private function map_contact_data($form_data, $field_mappings) {
        $contact_data = array();

        foreach ($field_mappings as $mapping) {
            $form_field = $mapping['form_field'];
            $ac_field = $mapping['integration_field'];

            if (isset($form_data[$form_field])) {
                $contact_data[$ac_field] = $form_data[$form_field];
            }
        }

        return $contact_data;
    }
}

// Register the integration
add_action('mavlers_cf_register_integrations', function($registry) {
    if (class_exists('Mavlers_CF_ActiveCampaign_Integration')) {
        $integration = new Mavlers_CF_ActiveCampaign_Integration();
        $registry->register_integration($integration);
    }
}); 