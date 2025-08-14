<?php
/**
 * ConvertKit Integration
 *
 * ConvertKit integration for email marketing automation with forms, sequences, and tagging
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_ConvertKit_Integration extends Mavlers_CF_Base_Integration {

    /**
     * Integration configuration
     */
    protected $integration_id = 'convertkit';
    protected $integration_name = 'ConvertKit';
    protected $integration_description = 'Professional email marketing automation with ConvertKit forms, sequences, and subscriber management';
    protected $integration_version = '1.0.0';
    protected $integration_icon = 'dashicons-email-alt';
    protected $integration_color = '#fb6970';
    protected $api_base_url = 'https://api.convertkit.com/v3/';

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
            'subscribe_to_form' => array(
                'label' => __('Subscribe to Form', 'mavlers-contact-forms'),
                'description' => __('Subscribe contact to a ConvertKit form', 'mavlers-contact-forms')
            ),
            'add_tags' => array(
                'label' => __('Add Tags', 'mavlers-contact-forms'),
                'description' => __('Add tags to a ConvertKit subscriber', 'mavlers-contact-forms')
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
            'first_name' => array(
                'label' => __('First Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            )
        );
    }

    /**
     * Handle form submission
     */
    public function handle_submission($action, $form_fields, $settings, $field_mappings) {
        // Map the method signature to match the existing process_submission method
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
                'description' => __('Your ConvertKit API key', 'mavlers-contact-forms')
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
                    'subscribe_to_form' => __('Subscribe to Form', 'mavlers-contact-forms'),
                    'add_tags' => __('Add Tags', 'mavlers-contact-forms')
                ),
                'default' => 'subscribe_to_form'
            ),
            'form_id' => array(
                'label' => __('ConvertKit Form', 'mavlers-contact-forms'),
                'type' => 'select',
                'dynamic' => true,
                'depends_on' => 'api_key',
                'show_if' => array('action_type' => 'subscribe_to_form')
            ),
            'tags' => array(
                'label' => __('Tags', 'mavlers-contact-forms'),
                'type' => 'text',
                'description' => __('Comma-separated list of tags', 'mavlers-contact-forms'),
                'placeholder' => 'lead, website-form'
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
                'required' => true
            ),
            'first_name' => array(
                'key' => 'first_name',
                'label' => __('First Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            )
        );
    }

    /**
     * Test connection
     */
    public function test_connection($credentials) {
        try {
            $api_key = $credentials['api_key'];
            
            if (!$this->api_client) {
                throw new Exception(__('API client not available', 'mavlers-contact-forms'));
            }
            
            $response = $this->api_client->request(
                'GET',
                $this->api_base_url . 'account?api_key=' . $api_key,
                array(),
                array()
            );

            if ($response['success'] && isset($response['data']['account'])) {
                return array(
                    'success' => true,
                    'message' => __('Successfully connected to ConvertKit', 'mavlers-contact-forms')
                );
            }

            return array(
                'success' => false,
                'message' => __('Invalid API key', 'mavlers-contact-forms')
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
            $subscriber_data = $this->map_subscriber_data($form_data, $field_mappings);
            
            if (empty($subscriber_data['email'])) {
                throw new Exception(__('Email address is required', 'mavlers-contact-forms'));
            }

            return $this->subscribe_to_form($subscriber_data, $settings);

        } catch (Exception $e) {
            $this->logger->error('ConvertKit submission failed', array(
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
     * Subscribe to ConvertKit form
     */
    private function subscribe_to_form($subscriber_data, $settings) {
        $form_id = $settings['form_id'];
        $api_key = $settings['api_key'];

        $data = array(
            'api_key' => $api_key,
            'email' => $subscriber_data['email']
        );

        if (!empty($subscriber_data['first_name'])) {
            $data['first_name'] = $subscriber_data['first_name'];
        }

        if (!empty($settings['tags'])) {
            $tags = array_map('trim', explode(',', $settings['tags']));
            $data['tags'] = $tags;
        }

        $response = $this->api_client->request(
            'POST',
            $this->api_base_url . "forms/{$form_id}/subscribe",
            $data,
            array('Content-Type' => 'application/json')
        );

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Successfully subscribed to ConvertKit form', 'mavlers-contact-forms'),
                'data' => $response['data']
            );
        }

        throw new Exception(__('Failed to subscribe to form', 'mavlers-contact-forms'));
    }

    /**
     * Get dynamic field options
     */
    public function get_dynamic_field_options($field_key, $credentials, $settings = array()) {
        try {
            $api_key = $credentials['api_key'];

            if ($field_key === 'form_id') {
                return $this->get_forms($api_key);
            }

            return array();

        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Get ConvertKit forms
     */
    private function get_forms($api_key) {
        $response = $this->api_client->request(
            'GET',
            $this->api_base_url . "forms?api_key={$api_key}",
            array(),
            array()
        );

        if (!$response['success'] || empty($response['data']['forms'])) {
            return array();
        }

        $forms = array();
        foreach ($response['data']['forms'] as $form) {
            $forms[$form['id']] = $form['name'];
        }

        return $forms;
    }

    /**
     * Map form data to subscriber data
     */
    private function map_subscriber_data($form_data, $field_mappings) {
        $subscriber_data = array();

        foreach ($field_mappings as $mapping) {
            $form_field = $mapping['form_field'];
            $ck_field = $mapping['integration_field'];

            if (isset($form_data[$form_field])) {
                $subscriber_data[$ck_field] = $form_data[$form_field];
            }
        }

        return $subscriber_data;
    }
}

// Register the integration
add_action('mavlers_cf_register_integrations', function($registry) {
    if (class_exists('Mavlers_CF_ConvertKit_Integration')) {
        $integration = new Mavlers_CF_ConvertKit_Integration();
        $registry->register_integration($integration);
    }
}); 