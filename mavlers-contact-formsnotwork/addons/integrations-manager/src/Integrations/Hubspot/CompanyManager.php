<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Company Manager
 * 
 * Handles company operations and contact associations
 */
class CompanyManager {

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
	 * Get companies from HubSpot
	 *
	 * @param int $limit Number of companies to retrieve.
	 * @return array
	 */
	public function get_companies(int $limit = 100): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/crm/v3/objects/companies?limit=%d', $limit);
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
				'error' => 'Failed to fetch companies: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			$companies = array();
			
			if (isset($data['results'])) {
				foreach ($data['results'] as $company) {
					$companies[] = array(
						'id' => sanitize_text_field($company['id']),
						'name' => sanitize_text_field($company['properties']['name']['value'] ?? ''),
						'domain' => sanitize_text_field($company['properties']['domain']['value'] ?? ''),
						'industry' => sanitize_text_field($company['properties']['industry']['value'] ?? ''),
						'city' => sanitize_text_field($company['properties']['city']['value'] ?? ''),
						'state' => sanitize_text_field($company['properties']['state']['value'] ?? ''),
						'country' => sanitize_text_field($company['properties']['country']['value'] ?? '')
					);
				}
			}

			return array(
				'success' => true,
				'data' => $companies
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to fetch companies (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Create a new company in HubSpot
	 *
	 * @param array $company_data Company data to create.
	 * @return array
	 */
	public function create_company(array $company_data): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$company_payload = array(
			'properties' => $company_data
		);

		$url = 'https://api.hubapi.com/crm/v3/objects/companies';
		$response = wp_remote_post($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($company_payload),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to create company: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 || $status_code === 201) {
			return array(
				'success' => true,
				'message' => 'Company created successfully',
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to create company (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Associate contact with company
	 *
	 * @param string $contact_id Contact ID.
	 * @param string $company_id Company ID.
	 * @return array
	 */
	public function associate_contact_with_company(string $contact_id, string $company_id): array {
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
					'associationTypeId' => 1 // Contact to Company association
				)
			)
		);

		$url = sprintf(
			'https://api.hubapi.com/crm/v3/objects/contacts/%s/associations/companies/%s',
			sanitize_text_field($contact_id),
			sanitize_text_field($company_id)
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
				'error' => 'Failed to associate contact with company: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);

		if ($status_code === 200 || $status_code === 201) {
			return array(
				'success' => true,
				'message' => 'Contact associated with company successfully'
			);
		} else {
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			
			return array(
				'success' => false,
				'error' => sprintf('Failed to associate contact with company (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get company by ID
	 *
	 * @param string $company_id Company ID.
	 * @return array
	 */
	public function get_company(string $company_id): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/crm/v3/objects/companies/%s', sanitize_text_field($company_id));
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
				'error' => 'Failed to fetch company: ' . sanitize_text_field($response->get_error_message())
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
				'error' => sprintf('Failed to fetch company (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Update company in HubSpot
	 *
	 * @param string $company_id Company ID.
	 * @param array  $company_data Company data to update.
	 * @return array
	 */
	public function update_company(string $company_id, array $company_data): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$company_payload = array(
			'properties' => $company_data
		);

		$url = sprintf('https://api.hubapi.com/crm/v3/objects/companies/%s', sanitize_text_field($company_id));
		$response = wp_remote_patch($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($company_payload),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to update company: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			return array(
				'success' => true,
				'message' => 'Company updated successfully',
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to update company (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Search companies by name
	 *
	 * @param string $search_term Search term.
	 * @param int    $limit Number of results to return.
	 * @return array
	 */
	public function search_companies(string $search_term, int $limit = 10): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$filter = array(
			'propertyName' => 'name',
			'operator' => 'CONTAINS_TOKEN',
			'value' => sanitize_text_field($search_term)
		);

		$url = sprintf(
			'https://api.hubapi.com/crm/v3/objects/companies?limit=%d&filter=%s',
			$limit,
			urlencode(wp_json_encode($filter))
		);
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
				'error' => 'Failed to search companies: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			$companies = array();
			
			if (isset($data['results'])) {
				foreach ($data['results'] as $company) {
					$companies[] = array(
						'id' => sanitize_text_field($company['id']),
						'name' => sanitize_text_field($company['properties']['name']['value'] ?? ''),
						'domain' => sanitize_text_field($company['properties']['domain']['value'] ?? ''),
						'industry' => sanitize_text_field($company['properties']['industry']['value'] ?? '')
					);
				}
			}

			return array(
				'success' => true,
				'data' => $companies
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to search companies (%d): %s', $status_code, $error_message)
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