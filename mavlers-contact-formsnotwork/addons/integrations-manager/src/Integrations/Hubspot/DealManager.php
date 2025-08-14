<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Deal Manager
 * 
 * Handles deal creation, management, and pipeline operations
 */
class DealManager {

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
	 * Get available pipelines from HubSpot
	 *
	 * @return array
	 */
	public function get_pipelines(): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = 'https://api.hubapi.com/crm/v3/pipelines/deals';
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
				'error' => 'Failed to fetch pipelines: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			$pipelines = array();
			
			if (isset($data['results'])) {
				foreach ($data['results'] as $pipeline) {
					$pipelines[] = array(
						'id' => sanitize_text_field($pipeline['id']),
						'label' => sanitize_text_field($pipeline['label']),
						'displayOrder' => (int) ($pipeline['displayOrder'] ?? 0),
						'stages' => is_array($pipeline['stages'] ?? array()) ? $pipeline['stages'] : array()
					);
				}
			}

			return array(
				'success' => true,
				'data' => $pipelines
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to fetch pipelines (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get stages for a specific pipeline
	 *
	 * @param string $pipeline_id Pipeline ID.
	 * @return array
	 */
	public function get_pipeline_stages(string $pipeline_id): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/crm/v3/pipelines/deals/%s', sanitize_text_field($pipeline_id));
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
				'error' => 'Failed to fetch pipeline stages: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			$stages = array();
			
			if (isset($data['stages'])) {
				foreach ($data['stages'] as $stage) {
					$stages[] = array(
						'id' => sanitize_text_field($stage['id']),
						'label' => sanitize_text_field($stage['label']),
						'displayOrder' => (int) ($stage['displayOrder'] ?? 0),
						'probability' => (float) ($stage['probability'] ?? 0)
					);
				}
			}

			return array(
				'success' => true,
				'data' => $stages
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to fetch pipeline stages (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Create a new deal in HubSpot
	 *
	 * @param array  $deal_data Deal data to create.
	 * @param string $pipeline_id Pipeline ID.
	 * @param string $stage_id Stage ID.
	 * @return array
	 */
	public function create_deal(array $deal_data, string $pipeline_id = '', string $stage_id = ''): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		// Prepare deal properties
		$properties = $deal_data['properties'] ?? array();
		
		// Add pipeline and stage if provided
		if (!empty($pipeline_id)) {
			$properties['pipeline'] = sanitize_text_field($pipeline_id);
		}
		
		if (!empty($stage_id)) {
			$properties['dealstage'] = sanitize_text_field($stage_id);
		}

		$deal_payload = array(
			'properties' => $properties
		);

		$url = 'https://api.hubapi.com/crm/v3/objects/deals';
		$response = wp_remote_post($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($deal_payload),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to create deal: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 || $status_code === 201) {
			return array(
				'success' => true,
				'message' => 'Deal created successfully',
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to create deal (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Update an existing deal in HubSpot
	 *
	 * @param string $deal_id Deal ID.
	 * @param array  $deal_data Deal data to update.
	 * @return array
	 */
	public function update_deal(string $deal_id, array $deal_data): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$deal_payload = array(
			'properties' => $deal_data['properties'] ?? array()
		);

		$url = sprintf('https://api.hubapi.com/crm/v3/objects/deals/%s', sanitize_text_field($deal_id));
		$response = wp_remote_patch($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($deal_payload),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to update deal: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			return array(
				'success' => true,
				'message' => 'Deal updated successfully',
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to update deal (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Associate deal with contact
	 *
	 * @param string $deal_id Deal ID.
	 * @param string $contact_id Contact ID.
	 * @return array
	 */
	public function associate_deal_with_contact(string $deal_id, string $contact_id): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$association_data = array(
			'types' => array(
				array(
					'associationCategory' => 'HUBSPOT_DEFINED',
					'associationTypeId' => 4 // Deal to Contact association
				)
			)
		);

		$url = sprintf(
			'https://api.hubapi.com/crm/v3/objects/deals/%s/associations/contacts/%s',
			sanitize_text_field($deal_id),
			sanitize_text_field($contact_id)
		);
		$response = wp_remote_put($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($association_data),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to associate deal with contact: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);

		if ($status_code === 200 || $status_code === 201) {
			return array(
				'success' => true,
				'message' => 'Deal associated with contact successfully'
			);
		} else {
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			
			return array(
				'success' => false,
				'error' => sprintf('Failed to associate deal with contact (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get deal by ID
	 *
	 * @param string $deal_id Deal ID.
	 * @return array
	 */
	public function get_deal(string $deal_id): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/crm/v3/objects/deals/%s', sanitize_text_field($deal_id));
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
				'error' => 'Failed to fetch deal: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			return array(
				'success' => true,
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to fetch deal (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get deals with filters
	 *
	 * @param array $filters Deal filters.
	 * @param int   $limit Number of deals to retrieve.
	 * @return array
	 */
	public function get_deals(array $filters = array(), int $limit = 100): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/crm/v3/objects/deals?limit=%d', $limit);
		
		// Add filters if provided
		if (!empty($filters)) {
			$url .= '&filter=' . urlencode(wp_json_encode($filters));
		}

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
				'error' => 'Failed to fetch deals: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			return array(
				'success' => true,
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to fetch deals (%d): %s', $status_code, $error_message)
			);
		}
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