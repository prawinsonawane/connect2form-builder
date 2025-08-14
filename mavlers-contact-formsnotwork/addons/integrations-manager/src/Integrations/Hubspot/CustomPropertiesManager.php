<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Custom Properties Manager
 * 
 * Handles custom properties, field mapping, and property management
 */
class CustomPropertiesManager {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * Language manager instance
	 *
	 * @var LanguageManager
	 */
	protected $language_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->language_manager = new LanguageManager();
	}

	/**
	 * Get custom properties from HubSpot
	 *
	 * @return array
	 */
	public function get_custom_properties(): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		// Get contact properties
		$url = 'https://api.hubapi.com/crm/v3/properties/contacts';
		$response = wp_remote_get($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to fetch custom properties: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			$properties = array();
			
			if (isset($data['results'])) {
				foreach ($data['results'] as $property) {
					$properties[] = array(
						'name' => sanitize_text_field($property['name']),
						'label' => sanitize_text_field($property['label']),
						'type' => sanitize_text_field($property['type']),
						'groupName' => sanitize_text_field($property['groupName'] ?? ''),
						'description' => sanitize_textarea_field($property['description'] ?? ''),
						'options' => is_array($property['options'] ?? array()) ? $property['options'] : array()
					);
				}
			}

			return array(
				'success' => true,
				'data' => $properties
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to fetch properties (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get merge fields for contact properties
	 *
	 * @return array
	 */
	public function get_merge_fields(): array {
		$properties_result = $this->get_custom_properties();
		
		if (!$properties_result['success']) {
			return $properties_result;
		}

		$merge_fields = array();
		$properties = $properties_result['data'];

		// Add standard fields
		$merge_fields[] = array(
			'tag' => 'email',
			'name' => 'Email',
			'type' => 'text',
			'required' => true
		);

		$merge_fields[] = array(
			'tag' => 'firstname',
			'name' => 'First Name',
			'type' => 'text',
			'required' => false
		);

		$merge_fields[] = array(
			'tag' => 'lastname',
			'name' => 'Last Name',
			'type' => 'text',
			'required' => false
		);

		$merge_fields[] = array(
			'tag' => 'phone',
			'name' => 'Phone',
			'type' => 'text',
			'required' => false
		);

		$merge_fields[] = array(
			'tag' => 'company',
			'name' => 'Company',
			'type' => 'text',
			'required' => false
		);

		// Add custom properties
		foreach ($properties as $property) {
			if ($property['name'] !== 'email' && $property['name'] !== 'firstname' && 
				$property['name'] !== 'lastname' && $property['name'] !== 'phone' && 
				$property['name'] !== 'company') {
				
				$merge_fields[] = array(
					'tag' => sanitize_text_field($property['name']),
					'name' => sanitize_text_field($property['label']),
					'type' => sanitize_text_field($property['type']),
					'required' => false,
					'groupName' => sanitize_text_field($property['groupName']),
					'description' => sanitize_textarea_field($property['description'])
				);
			}
		}

		return array(
			'success' => true,
			'data' => $merge_fields
		);
	}

	/**
	 * Create custom property in HubSpot
	 *
	 * @param array $property_data Property data to create.
	 * @return array
	 */
	public function create_custom_property(array $property_data): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = 'https://api.hubapi.com/crm/v3/properties/contacts';
		$response = wp_remote_post($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($property_data),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to create property: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 || $status_code === 201) {
			return array(
				'success' => true,
				'message' => 'Custom property created successfully',
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to create property (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Update custom property in HubSpot
	 *
	 * @param string $property_name Property name to update.
	 * @param array  $property_data Property data to update.
	 * @return array
	 */
	public function update_custom_property(string $property_name, array $property_data): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/crm/v3/properties/contacts/%s', sanitize_text_field($property_name));
		$response = wp_remote_patch($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($property_data),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to update property: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			return array(
				'success' => true,
				'message' => 'Custom property updated successfully',
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to update property (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Delete custom property from HubSpot
	 *
	 * @param string $property_name Property name to delete.
	 * @return array
	 */
	public function delete_custom_property(string $property_name): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/crm/v3/properties/contacts/%s', sanitize_text_field($property_name));
		$response = wp_remote_request($url, array(
			'method' => 'DELETE',
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to delete property: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);

		if ($status_code === 204) {
			return array(
				'success' => true,
				'message' => 'Custom property deleted successfully'
			);
		} else {
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			
			return array(
				'success' => false,
				'error' => sprintf('Failed to delete property (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get field mapping for form
	 *
	 * @param int $form_id Form ID.
	 * @return array
	 */
	public function get_field_mapping(int $form_id): array {
		if (!$form_id) {
			return array();
		}

		$mapping = get_option(sprintf('mavlers_cf_hubspot_field_mapping_%d', $form_id), array());
		return is_array($mapping) ? $mapping : array();
	}

	/**
	 * Save field mapping for form
	 *
	 * @param int   $form_id Form ID.
	 * @param array $mapping Field mapping data.
	 * @return bool
	 */
	public function save_field_mapping(int $form_id, array $mapping): bool {
		if (!$form_id) {
			return false;
		}

		return update_option(sprintf('mavlers_cf_hubspot_field_mapping_%d', $form_id), $mapping);
	}

	/**
	 * Generate automatic field mapping
	 *
	 * @param int $form_id Form ID.
	 * @return array
	 */
	public function generate_automatic_mapping(int $form_id): array {
		$form_fields = $this->get_form_fields($form_id);
		$mapping = array();

		// Basic auto-mapping logic
		$auto_map_rules = array(
			'email' => 'email',
			'first_name' => 'firstname',
			'last_name' => 'lastname',
			'phone' => 'phone',
			'company' => 'company',
			'website' => 'website'
		);

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
	 *
	 * @param int $form_id Form ID.
	 * @return array
	 */
	private function get_form_fields(int $form_id): array {
		global $wpdb;
		
		if (!$form_id) {
			return array();
		}
		
		$form = $wpdb->get_row($wpdb->prepare(
			"SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
			$form_id
		));
		
		if (!$form || !$form->fields) {
			return array();
		}
		
		$fields_data = json_decode($form->fields, true);
		if (!is_array($fields_data)) {
			return array();
		}
		
		$processed_fields = array();
		
		foreach ($fields_data as $field) {
			if (!isset($field['id']) || !isset($field['label'])) {
				continue;
			}
			
			$field_id = sanitize_text_field($field['id']);
			$field_type = sanitize_text_field($field['type'] ?? 'text');
			$field_label = sanitize_text_field($field['label']);
			$required = (bool) ($field['required'] ?? false);
			
			$processed_fields[$field_id] = array(
				'id' => $field_id,
				'label' => $field_label,
				'type' => $field_type,
				'required' => $required,
				'name' => sanitize_text_field($field['name'] ?? $field_id),
				'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
				'description' => sanitize_textarea_field($field['description'] ?? '')
			);
		}
		
		return $processed_fields;
	}

	/**
	 * Get global settings for HubSpot
	 *
	 * @return array
	 */
	private function get_global_settings() {
		$global_settings = get_option('mavlers_cf_integrations_global', array());
		return $global_settings['hubspot'] ?? array();
	}

	/**
	 * Translation helper
	 *
	 * @param string $text Text to translate.
	 * @param string $fallback Fallback text.
	 * @return string
	 */
	private function __($text, $fallback = null) {
		if ($this->language_manager) {
			return $this->language_manager->translate($text);
		}
		return $fallback ?: $text;
	}
} 