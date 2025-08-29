<?php
/**
 * Integrations Handler Class
 * 
 * Handles third-party integrations like Mailchimp and HubSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Connect2Form_Integrations {
    
    /**
     * Initialize the integrations
     */
    public function __construct() {
        // Hook for processing integrations after form submission
    }
    
    /**
     * Process integrations after form submission
     * 
     * @param int $submission_id The submission ID
     * @param array $form_data The form data
     * @param object $form The form object
     */
    public function process_integrations($submission_id, $form_data, $form) {
        if (!$form || !$form->settings) {
            return;
        }
        
        $settings = is_string($form->settings) ? json_decode($form->settings, true) : (is_array($form->settings) ? $form->settings : array());
        if (!$settings) {
            return;
        }
        
        // Process Mailchimp integration
        if (!empty($settings['enable_mailchimp']) && $settings['enable_mailchimp']) {
            $this->process_mailchimp_integration($submission_id, $form_data, $settings);
        }
        
        // Process HubSpot integration
        if (!empty($settings['enable_hubspot']) && $settings['enable_hubspot']) {
            $this->process_hubspot_integration($submission_id, $form_data, $settings);
        }
    }
    
    /**
     * Process Mailchimp integration
     */
    private function process_mailchimp_integration($submission_id, $form_data, $settings) {
        // Extract required settings
        $api_key = $settings['mailchimp_api_key'] ?? '';
        $list_id = $settings['mailchimp_list_id'] ?? '';
        $email_field = $settings['mailchimp_email_field'] ?? '';
        $name_field = $settings['mailchimp_name_field'] ?? '';
        $double_optin = $settings['mailchimp_double_optin'] ?? true;
        
        if (empty($api_key) || empty($list_id) || empty($email_field)) {
            return;
        }
        
        // Get email value from form data
        $email = '';
        $name = '';
        
        foreach ($form_data['fields'] as $field_id => $field_value) {
            if ($field_id === $email_field) {
                $email = $field_value;
            }
            if ($field_id === $name_field) {
                $name = $field_value;
            }
        }
        
        if (empty($email)) {
            return;
        }
        
        // Extract datacenter from API key
        $dc = 'us1'; // default
        if (strpos($api_key, '-') !== false) {
            $dc = substr($api_key, strpos($api_key, '-') + 1);
        }
        
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members";
        
        $data = array(
            'email_address' => $email,
            'status' => $double_optin ? 'pending' : 'subscribed'
        );
        
        if (!empty($name)) {
            $data['merge_fields'] = array(
                'FNAME' => $name
            );
        }
        
        // Make API request
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($data),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
    }
    
    /**
     * Process HubSpot integration
     */
    private function process_hubspot_integration($submission_id, $form_data, $settings) {
        // Extract required settings
        $api_key = $settings['hubspot_api_key'] ?? '';
        $portal_id = $settings['hubspot_portal_id'] ?? '';
        $email_field = $settings['hubspot_email_field'] ?? '';
        
        if (empty($api_key) || empty($email_field)) {
            return;
        }
        
        // Get field values
        $email = '';
        $firstname = '';
        $lastname = '';
        $phone = '';
        $company = '';
        
        foreach ($form_data['fields'] as $field_id => $field_value) {
            if ($field_id === $email_field) {
                $email = $field_value;
            }
            if ($field_id === ($settings['hubspot_firstname_field'] ?? '')) {
                $firstname = $field_value;
            }
            if ($field_id === ($settings['hubspot_lastname_field'] ?? '')) {
                $lastname = $field_value;
            }
            if ($field_id === ($settings['hubspot_phone_field'] ?? '')) {
                $phone = $field_value;
            }
            if ($field_id === ($settings['hubspot_company_field'] ?? '')) {
                $company = $field_value;
            }
        }
        
        if (empty($email)) {
            return;
        }
        
        $url = 'https://api.hubapi.com/crm/v3/objects/contacts';
        
        $properties = array(
            'email' => $email
        );
        
        if (!empty($firstname)) $properties['firstname'] = $firstname;
        if (!empty($lastname)) $properties['lastname'] = $lastname;
        if (!empty($phone)) $properties['phone'] = $phone;
        if (!empty($company)) $properties['company'] = $company;
        
        $data = array(
            'properties' => $properties
        );
        
        // Make API request
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($data),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
    }
}

// Initialize the integrations
new Connect2Form_Integrations(); 
