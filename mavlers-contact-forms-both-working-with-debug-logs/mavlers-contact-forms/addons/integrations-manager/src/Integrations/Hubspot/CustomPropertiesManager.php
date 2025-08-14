<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\LanguageManager;

// Debug: Log when this file is loaded
error_log("CustomPropertiesManager.php: File loaded successfully");

/**
 * HubSpot Custom Properties Manager
 * 
 * Handles custom properties, field mapping, and property management
 */
class CustomPropertiesManager {

    protected $version = '1.0.0';
    protected $language_manager;

    public function __construct() {
        $this->language_manager = new LanguageManager();
    }

    /**
     * Get custom properties from HubSpot
     */
    public function get_custom_properties(): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        // Get contact properties
        $url = "https://api.hubapi.com/crm/v3/properties/contacts";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to fetch custom properties: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            $properties = [];
            
            if (isset($data['results'])) {
                foreach ($data['results'] as $property) {
                    $properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label'],
                        'type' => $property['type'],
                        'groupName' => $property['groupName'] ?? '',
                        'description' => $property['description'] ?? '',
                        'options' => $property['options'] ?? []
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $properties
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to fetch properties ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get merge fields for contact properties
     */
    public function get_merge_fields(): array {
        $properties_result = $this->get_custom_properties();
        
        if (!$properties_result['success']) {
            return $properties_result;
        }

        $merge_fields = [];
        $properties = $properties_result['data'];

        // Add standard fields
        $merge_fields[] = [
            'tag' => 'email',
            'name' => 'Email',
            'type' => 'text',
            'required' => true
        ];

        $merge_fields[] = [
            'tag' => 'firstname',
            'name' => 'First Name',
            'type' => 'text',
            'required' => false
        ];

        $merge_fields[] = [
            'tag' => 'lastname',
            'name' => 'Last Name',
            'type' => 'text',
            'required' => false
        ];

        $merge_fields[] = [
            'tag' => 'phone',
            'name' => 'Phone',
            'type' => 'text',
            'required' => false
        ];

        $merge_fields[] = [
            'tag' => 'company',
            'name' => 'Company',
            'type' => 'text',
            'required' => false
        ];

        // Add custom properties
        foreach ($properties as $property) {
            if ($property['name'] !== 'email' && $property['name'] !== 'firstname' && 
                $property['name'] !== 'lastname' && $property['name'] !== 'phone' && 
                $property['name'] !== 'company') {
                
                $merge_fields[] = [
                    'tag' => $property['name'],
                    'name' => $property['label'],
                    'type' => $property['type'],
                    'required' => false,
                    'groupName' => $property['groupName'],
                    'description' => $property['description']
                ];
            }
        }

        return [
            'success' => true,
            'data' => $merge_fields
        ];
    }

    /**
     * Create custom property in HubSpot
     */
    public function create_custom_property(array $property_data): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/properties/contacts";
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($property_data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to create property: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'message' => 'Custom property created successfully',
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to create property ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Update custom property in HubSpot
     */
    public function update_custom_property(string $property_name, array $property_data): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/properties/contacts/{$property_name}";
        $response = wp_remote_patch($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($property_data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to update property: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => 'Custom property updated successfully',
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to update property ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Delete custom property from HubSpot
     */
    public function delete_custom_property(string $property_name): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/properties/contacts/{$property_name}";
        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to delete property: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 204) {
            return [
                'success' => true,
                'message' => 'Custom property deleted successfully'
            ];
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $error_message = $data['message'] ?? 'Unknown error';
            
            return [
                'success' => false,
                'error' => "Failed to delete property ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get field mapping for form
     */
    public function get_field_mapping(int $form_id): array {
        if (!$form_id) {
            return [];
        }

        $mapping = get_option("mavlers_cf_hubspot_field_mapping_{$form_id}", []);
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Save field mapping for form
     */
    public function save_field_mapping(int $form_id, array $mapping): bool {
        if (!$form_id) {
            return false;
        }

        return update_option("mavlers_cf_hubspot_field_mapping_{$form_id}", $mapping);
    }

    /**
     * Generate automatic field mapping
     */
    public function generate_automatic_mapping(int $form_id): array {
        $form_fields = $this->get_form_fields($form_id);
        $mapping = [];

        // Basic auto-mapping logic
        $auto_map_rules = [
            'email' => 'email',
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'phone' => 'phone',
            'company' => 'company',
            'website' => 'website'
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
     * Get global settings for HubSpot
     */
    private function get_global_settings() {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        return $global_settings['hubspot'] ?? [];
    }

    /**
     * Translation helper
     */
    private function __($text, $fallback = null) {
        if ($this->language_manager) {
            return $this->language_manager->translate($text);
        }
        return $fallback ?: $text;
    }
} 