<?php
/**
 * Zapier Integration
 *
 * Universal Zapier integration that allows connecting forms to thousands of apps via webhooks
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Zapier_Integration extends Mavlers_CF_Base_Integration {

    /**
     * Integration configuration
     */
    protected $integration_id = 'zapier';
    protected $integration_name = 'Zapier';
    protected $integration_description = 'Connect your forms to 3000+ apps through Zapier webhooks. Automate workflows with endless possibilities.';
    protected $integration_version = '1.0.0';
    protected $integration_icon = 'dashicons-admin-links';
    protected $integration_color = '#ff4a00';

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
            'send_webhook' => array(
                'label' => __('Send Webhook', 'mavlers-contact-forms'),
                'description' => __('Send form data to Zapier webhook', 'mavlers-contact-forms')
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
            'webhook_url' => array(
                'label' => __('Zapier Webhook URL', 'mavlers-contact-forms'),
                'type' => 'url',
                'required' => true,
                'description' => __('Copy the webhook URL from your Zapier webhook trigger.', 'mavlers-contact-forms'),
                'placeholder' => 'https://hooks.zapier.com/hooks/catch/123456/abcdef/'
            )
        );
    }

    /**
     * Get action fields
     */
    public function get_action_fields() {
        return array(
            'data_format' => array(
                'label' => __('Data Format', 'mavlers-contact-forms'),
                'type' => 'select',
                'required' => true,
                'options' => array(
                    'raw' => __('Raw Form Data', 'mavlers-contact-forms'),
                    'formatted' => __('Formatted Data with Labels', 'mavlers-contact-forms'),
                    'custom' => __('Custom JSON Structure', 'mavlers-contact-forms')
                ),
                'default' => 'formatted',
                'description' => __('How the form data should be structured when sent to Zapier', 'mavlers-contact-forms')
            ),
            'include_metadata' => array(
                'label' => __('Include Metadata', 'mavlers-contact-forms'),
                'type' => 'checkbox',
                'description' => __('Include form metadata like submission time, IP address, and form ID', 'mavlers-contact-forms'),
                'default' => true
            ),
            'custom_fields' => array(
                'label' => __('Custom Fields', 'mavlers-contact-forms'),
                'type' => 'textarea',
                'description' => __('Add custom fields as JSON. Example: {"source": "website", "campaign": "summer2023"}', 'mavlers-contact-forms'),
                'show_if' => array('data_format' => array('formatted', 'custom'))
            ),
            'test_mode' => array(
                'label' => __('Test Mode', 'mavlers-contact-forms'),
                'type' => 'checkbox',
                'description' => __('Add a test_mode flag to identify test submissions', 'mavlers-contact-forms'),
                'default' => false
            ),
            'retry_failed' => array(
                'label' => __('Retry Failed Webhooks', 'mavlers-contact-forms'),
                'type' => 'checkbox',
                'description' => __('Automatically retry failed webhook calls up to 3 times', 'mavlers-contact-forms'),
                'default' => true
            ),
            'delay_seconds' => array(
                'label' => __('Delay (seconds)', 'mavlers-contact-forms'),
                'type' => 'number',
                'description' => __('Delay webhook execution by specified seconds (useful for processing order)', 'mavlers-contact-forms'),
                'min' => 0,
                'max' => 300,
                'default' => 0
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
                'required' => false,
                'description' => __('Email field from the form', 'mavlers-contact-forms')
            ),
            'name' => array(
                'key' => 'name',
                'label' => __('Full Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'first_name' => array(
                'key' => 'first_name',
                'label' => __('First Name', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'last_name' => array(
                'key' => 'last_name',
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
            'website' => array(
                'key' => 'website',
                'label' => __('Website', 'mavlers-contact-forms'),
                'type' => 'url',
                'required' => false
            ),
            'message' => array(
                'key' => 'message',
                'label' => __('Message', 'mavlers-contact-forms'),
                'type' => 'textarea',
                'required' => false
            ),
            'subject' => array(
                'key' => 'subject',
                'label' => __('Subject', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'custom_field_1' => array(
                'key' => 'custom_field_1',
                'label' => __('Custom Field 1', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'custom_field_2' => array(
                'key' => 'custom_field_2',
                'label' => __('Custom Field 2', 'mavlers-contact-forms'),
                'type' => 'text',
                'required' => false
            ),
            'custom_field_3' => array(
                'key' => 'custom_field_3',
                'label' => __('Custom Field 3', 'mavlers-contact-forms'),
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
            if (!$this->api_client) {
                throw new Exception(__('API client not available', 'mavlers-contact-forms'));
            }
            
            $webhook_url = $credentials['webhook_url'];
            
            // Validate webhook URL format
            if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
                return array(
                    'success' => false,
                    'message' => __('Invalid webhook URL format', 'mavlers-contact-forms')
                );
            }

            if (strpos($webhook_url, 'hooks.zapier.com') === false) {
                return array(
                    'success' => false,
                    'message' => __('URL does not appear to be a Zapier webhook', 'mavlers-contact-forms')
                );
            }

            // Send test payload
            $test_data = array(
                'test' => true,
                'message' => 'Test connection from Mavlers Contact Forms',
                'timestamp' => current_time('c'),
                'plugin_version' => MAVLERS_CF_VERSION ?? '1.0.0'
            );

            $response = $this->api_client->request(
                'POST',
                $webhook_url,
                $test_data,
                array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Mavlers-Contact-Forms/' . (MAVLERS_CF_VERSION ?? '1.0.0')
                )
            );

            if ($response['success']) {
                return array(
                    'success' => true,
                    'message' => __('Successfully sent test data to Zapier webhook', 'mavlers-contact-forms')
                );
            }

            return array(
                'success' => false,
                'message' => sprintf(
                    __('Failed to send test data: %s', 'mavlers-contact-forms'),
                    $response['message'] ?? 'Unknown error'
                )
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
            
            $webhook_url = $settings['webhook_url'];
            
            // Apply delay if specified
            if (!empty($settings['delay_seconds']) && $settings['delay_seconds'] > 0) {
                // Schedule delayed execution
                wp_schedule_single_event(
                    time() + intval($settings['delay_seconds']),
                    'mavlers_cf_zapier_delayed_webhook',
                    array($webhook_url, $form_data, $settings, $field_mappings)
                );
                
                return array(
                    'success' => true,
                    'message' => sprintf(
                        __('Zapier webhook scheduled for %d seconds delay', 'mavlers-contact-forms'),
                        $settings['delay_seconds']
                    )
                );
            }

            return $this->send_webhook($webhook_url, $form_data, $settings, $field_mappings);

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Zapier webhook failed', array(
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
     * Send webhook to Zapier
     */
    public function send_webhook($webhook_url, $form_data, $settings, $field_mappings) {
        $data_format = $settings['data_format'] ?? 'formatted';
        
        // Prepare payload based on format
        switch ($data_format) {
            case 'raw':
                $payload = $this->prepare_raw_data($form_data, $settings);
                break;
            case 'custom':
                $payload = $this->prepare_custom_data($form_data, $settings, $field_mappings);
                break;
            case 'formatted':
            default:
                $payload = $this->prepare_formatted_data($form_data, $settings, $field_mappings);
                break;
        }

        // Add metadata if enabled
        if (!empty($settings['include_metadata'])) {
            $payload['_metadata'] = $this->get_submission_metadata();
        }

        // Add test mode flag if enabled
        if (!empty($settings['test_mode'])) {
            $payload['_test_mode'] = true;
        }

        // Add custom fields
        if (!empty($settings['custom_fields'])) {
            $custom_fields = json_decode($settings['custom_fields'], true);
            if (is_array($custom_fields)) {
                $payload = array_merge($payload, $custom_fields);
            }
        }

        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mavlers-Contact-Forms/' . (MAVLERS_CF_VERSION ?? '1.0.0'),
            'X-Zapier-Source' => 'mavlers-contact-forms'
        );

        $response = $this->api_client->request('POST', $webhook_url, $payload, $headers);

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Data sent to Zapier successfully', 'mavlers-contact-forms'),
                'data' => $payload
            );
        }

        // Handle retry logic
        if (!empty($settings['retry_failed'])) {
            $this->schedule_retry($webhook_url, $payload, $headers, 1);
        }

        throw new Exception(
            sprintf(
                __('Failed to send data to Zapier: %s', 'mavlers-contact-forms'),
                $response['message'] ?? 'Unknown error'
            )
        );
    }

    /**
     * Prepare raw data format
     */
    private function prepare_raw_data($form_data, $settings) {
        return $form_data;
    }

    /**
     * Prepare formatted data with labels
     */
    private function prepare_formatted_data($form_data, $settings, $field_mappings) {
        $formatted_data = array();

        // Map the form data to Zapier-friendly field names
        foreach ($field_mappings as $mapping) {
            $form_field = $mapping['form_field'];
            $zapier_field = $mapping['integration_field'];

            if (isset($form_data[$form_field])) {
                $formatted_data[$zapier_field] = $form_data[$form_field];
            }
        }

        // Add unmapped fields with their original names
        foreach ($form_data as $field_name => $field_value) {
            $is_mapped = false;
            foreach ($field_mappings as $mapping) {
                if ($mapping['form_field'] === $field_name) {
                    $is_mapped = true;
                    break;
                }
            }
            
            if (!$is_mapped) {
                $formatted_data[$field_name] = $field_value;
            }
        }

        return $formatted_data;
    }

    /**
     * Prepare custom data structure
     */
    private function prepare_custom_data($form_data, $settings, $field_mappings) {
        // Start with formatted data as base
        $custom_data = $this->prepare_formatted_data($form_data, $settings, $field_mappings);

        // Add structured sections
        $payload = array(
            'form_submission' => $custom_data,
            'contact_info' => array(),
            'additional_data' => array()
        );

        // Organize data into logical sections
        $contact_fields = array('email', 'name', 'first_name', 'last_name', 'phone', 'company', 'website');
        
        foreach ($custom_data as $key => $value) {
            if (in_array($key, $contact_fields)) {
                $payload['contact_info'][$key] = $value;
            } elseif (!in_array($key, array('message', 'subject'))) {
                $payload['additional_data'][$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Get submission metadata
     */
    private function get_submission_metadata() {
        return array(
            'submission_time' => current_time('c'),
            'submission_date' => current_time('Y-m-d'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'site_url' => home_url(),
            'form_id' => $_POST['form_id'] ?? '',
            'page_url' => $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?? ''
        );
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Schedule retry for failed webhook
     */
    private function schedule_retry($webhook_url, $payload, $headers, $attempt) {
        if ($attempt > 3) {
            return; // Max retries reached
        }

        $delay = $attempt * 60; // 1 min, 2 min, 3 min delays

        wp_schedule_single_event(
            time() + $delay,
            'mavlers_cf_zapier_retry_webhook',
            array($webhook_url, $payload, $headers, $attempt)
        );
    }

    /**
     * Handle delayed webhook (used by scheduled event)
     */
    public function handle_delayed_webhook($webhook_url, $form_data, $settings, $field_mappings) {
        return $this->send_webhook($webhook_url, $form_data, $settings, $field_mappings);
    }

    /**
     * Handle webhook retry (used by scheduled event)
     */
    public function handle_webhook_retry($webhook_url, $payload, $headers, $attempt) {
        $response = $this->api_client->request('POST', $webhook_url, $payload, $headers);

        if (!$response['success'] && $attempt < 3) {
            $this->schedule_retry($webhook_url, $payload, $headers, $attempt + 1);
        }

        return $response;
    }

    /**
     * Get setup instructions
     */
    public function get_setup_instructions() {
        return array(
            'title' => __('How to Connect with Zapier', 'mavlers-contact-forms'),
            'steps' => array(
                __('1. Log into your Zapier account and create a new Zap', 'mavlers-contact-forms'),
                __('2. Choose "Webhooks by Zapier" as the trigger app', 'mavlers-contact-forms'),
                __('3. Select "Catch Hook" as the trigger event', 'mavlers-contact-forms'),
                __('4. Copy the webhook URL provided by Zapier', 'mavlers-contact-forms'),
                __('5. Paste the webhook URL in the field above', 'mavlers-contact-forms'),
                __('6. Test the connection and submit a form to send sample data', 'mavlers-contact-forms'),
                __('7. Return to Zapier to continue setting up your Zap', 'mavlers-contact-forms')
            ),
            'tips' => array(
                __('Use "Formatted Data" for easier field mapping in Zapier', 'mavlers-contact-forms'),
                __('Enable metadata to get additional context about submissions', 'mavlers-contact-forms'),
                __('Test your webhook before going live to ensure data flows correctly', 'mavlers-contact-forms')
            )
        );
    }
}

// Register the integration
add_action('mavlers_cf_register_integrations', function($registry) {
    if (class_exists('Mavlers_CF_Zapier_Integration')) {
        $integration = new Mavlers_CF_Zapier_Integration();
        $registry->register_integration($integration);
    }
}); 