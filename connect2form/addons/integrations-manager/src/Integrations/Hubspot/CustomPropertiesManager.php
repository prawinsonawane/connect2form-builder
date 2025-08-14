<?php

namespace Connect2Form\Integrations\Hubspot;

use Connect2Form\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Custom Properties Manager
 *
 * Handles custom properties, field mapping, and property management
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * HubSpot Custom Properties Manager Class
 *
 * Handles custom properties, field mapping, and property management
 *
 * @since    2.0.0
 */
class CustomPropertiesManager {

	/**
	 * Version number.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $version    Version number.
	 */
	protected $version = '1.0.0';

	/**
	 * Language manager instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      LanguageManager    $language_manager    Language manager instance.
	 */
	protected $language_manager;

	/**
	 * Constructor
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->language_manager = new LanguageManager();
	}

	/**
	 * Get custom properties from HubSpot
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_custom_properties(): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		// Get contact properties.
		$url = 'https://api.hubapi.com/crm/v3/properties/contacts';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to fetch custom properties: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			$properties = array();

			if ( isset( $data['results'] ) ) {
				foreach ( $data['results'] as $property ) {
					$properties[] = array(
						'name'        => $property['name'],
						'label'       => $property['label'],
						'type'        => $property['type'],
						'groupName'   => $property['groupName'] ?? '',
						'description' => $property['description'] ?? '',
						'options'     => $property['options'] ?? array(),
					);
				}
			}

			return array(
				'success' => true,
				'data'    => $properties,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to fetch properties ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get merge fields for contact properties
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_merge_fields(): array {
		$properties_result = $this->get_custom_properties();

		if ( ! $properties_result['success'] ) {
			return $properties_result;
		}

		$merge_fields = array();
		$properties   = $properties_result['data'];

		// Add standard fields.
		$merge_fields[] = array(
			'tag'      => 'email',
			'name'     => 'Email',
			'type'     => 'text',
			'required' => true,
		);

		$merge_fields[] = array(
			'tag'      => 'firstname',
			'name'     => 'First Name',
			'type'     => 'text',
			'required' => false,
		);

		$merge_fields[] = array(
			'tag'      => 'lastname',
			'name'     => 'Last Name',
			'type'     => 'text',
			'required' => false,
		);

		$merge_fields[] = array(
			'tag'      => 'phone',
			'name'     => 'Phone',
			'type'     => 'text',
			'required' => false,
		);

		$merge_fields[] = array(
			'tag'      => 'company',
			'name'     => 'Company',
			'type'     => 'text',
			'required' => false,
		);

		// Add custom properties.
		foreach ( $properties as $property ) {
			if ( $property['name'] !== 'email' && $property['name'] !== 'firstname' &&
				$property['name'] !== 'lastname' && $property['name'] !== 'phone' &&
				$property['name'] !== 'company' ) {

				$merge_fields[] = array(
					'tag'         => $property['name'],
					'name'        => $property['label'],
					'type'        => $property['type'],
					'required'    => false,
					'groupName'   => $property['groupName'],
					'description' => $property['description'],
				);
			}
		}

		return array(
			'success' => true,
			'data'    => $merge_fields,
		);
	}

	/**
	 * Create custom property in HubSpot
	 *
	 * @since    2.0.0
	 * @param    array $property_data Property data.
	 * @return   array
	 */
	public function create_custom_property( array $property_data ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = 'https://api.hubapi.com/crm/v3/properties/contacts';
		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $property_data ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to create property: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 || $status_code === 201 ) {
			return array(
				'success' => true,
				'message' => 'Custom property created successfully',
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to create property ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Update custom property in HubSpot
	 *
	 * @since    2.0.0
	 * @param    string $property_name Property name.
	 * @param    array  $property_data Property data.
	 * @return   array
	 */
	public function update_custom_property( string $property_name, array $property_data ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/crm/v3/properties/contacts/{$property_name}";
		$response = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $property_data ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to update property: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => 'Custom property updated successfully',
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to update property ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Delete custom property from HubSpot
	 *
	 * @since    2.0.0
	 * @param    string $property_name Property name.
	 * @return   array
	 */
	public function delete_custom_property( string $property_name ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/crm/v3/properties/contacts/{$property_name}";
		$response = wp_remote_request( $url, array(
			'method'  => 'DELETE',
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to delete property: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 204 ) {
			return array(
				'success' => true,
				'message' => 'Custom property deleted successfully',
			);
		} else {
			$body         = wp_remote_retrieve_body( $response );
			$data         = json_decode( $body, true );
			$error_message = $data['message'] ?? 'Unknown error';

			return array(
				'success' => false,
				'error'   => "Failed to delete property ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get field mapping for form
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	public function get_field_mapping( int $form_id ): array {
		if ( ! $form_id ) {
			return array();
		}

		$mapping = get_option( "connect2form_hubspot_field_mapping_{$form_id}", array() );
		return is_array( $mapping ) ? $mapping : array();
	}

	/**
	 * Save field mapping for form
	 *
	 * @since    2.0.0
	 * @param    int   $form_id Form ID.
	 * @param    array $mapping Field mapping.
	 * @return   bool
	 */
	public function save_field_mapping( int $form_id, array $mapping ): bool {
		if ( ! $form_id ) {
			return false;
		}

		return update_option( "connect2form_hubspot_field_mapping_{$form_id}", $mapping );
	}

	/**
	 * Generate automatic field mapping
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	public function generate_automatic_mapping( int $form_id ): array {
		$form_fields = $this->get_form_fields( $form_id );
		$mapping     = array();

		// Basic auto-mapping logic.
		$auto_map_rules = array(
			'email'      => 'email',
			'first_name' => 'firstname',
			'last_name'  => 'lastname',
			'phone'      => 'phone',
			'company'    => 'company',
			'website'    => 'website',
		);

		foreach ( $form_fields as $field_name => $field_config ) {
			$field_label = strtolower( $field_config['label'] ?? '' );

			foreach ( $auto_map_rules as $pattern => $hubspot_field ) {
				if ( strpos( $field_label, $pattern ) !== false ) {
					$mapping[ $field_name ] = $hubspot_field;
					break;
				}
			}
		}

		return $mapping;
	}

	/**
	 * Get form fields from database
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	private function get_form_fields( int $form_id ): array {
		if ( ! $form_id ) {
			return array();
		}

		// Use service class instead of direct database call.
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$form            = $service_manager->forms()->get_form( $form_id );
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form fields query for custom properties; service layer preferred but this is a fallback
			$form = $wpdb->get_row( $wpdb->prepare(
				"SELECT fields FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
				$form_id
			) );
		}

		if ( ! $form || ! $form->fields ) {
			return array();
		}

		$fields_data = is_string( $form->fields ) ? json_decode( $form->fields, true ) : ( is_array( $form->fields ) ? $form->fields : array() );
		if ( ! is_array( $fields_data ) ) {
			return array();
		}

		$processed_fields = array();

		foreach ( $fields_data as $field ) {
			if ( ! isset( $field['id'] ) || ! isset( $field['label'] ) ) {
				continue;
			}

			$field_id    = $field['id'];
			$field_type  = $field['type'] ?? 'text';
			$field_label = $field['label'];
			$required    = $field['required'] ?? false;

			$processed_fields[ $field_id ] = array(
				'id'          => $field_id,
				'label'       => $field_label,
				'type'        => $field_type,
				'required'    => $required,
				'name'        => $field['name'] ?? $field_id,
				'placeholder' => $field['placeholder'] ?? '',
				'description' => $field['description'] ?? '',
			);
		}

		return $processed_fields;
	}

	/**
	 * Get global settings for HubSpot
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   array
	 */
	private function get_global_settings() {
		$global_settings = get_option( 'connect2form_integrations_global', array() );
		return $global_settings['hubspot'] ?? array();
	}

	/**
	 * Translation helper
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $text     Text to translate.
	 * @param    string $fallback Fallback text.
	 * @return   string
	 */
	private function __( $text, $fallback = null ) {
		if ( $this->language_manager ) {
			return $this->language_manager->translate( $text );
		}
		return $fallback ?: $text;
	}
} 

