<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Workflow Manager
 * 
 * Handles workflow enrollment, management, and automation
 */
class WorkflowManager {

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
	 * Get available workflows from HubSpot
	 *
	 * @return array
	 */
	public function get_workflows(): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		// Get workflows
		$url = 'https://api.hubapi.com/automation/v3/workflows';
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
				'error' => 'Failed to fetch workflows: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			$workflows = array();
			
			if (isset($data['results'])) {
				foreach ($data['results'] as $workflow) {
					// Only include active workflows that can be enrolled
					if (!empty($workflow['enabled']) && $workflow['type'] === 'DRIP_DELAY') {
						$workflows[] = array(
							'id' => sanitize_text_field($workflow['id']),
							'name' => sanitize_text_field($workflow['name']),
							'type' => sanitize_text_field($workflow['type']),
							'enabled' => (bool) $workflow['enabled'],
							'description' => sanitize_textarea_field($workflow['description'] ?? '')
						);
					}
				}
			}

			return array(
				'success' => true,
				'data' => $workflows
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to fetch workflows (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Enroll contact in workflow
	 *
	 * @param string $workflow_id Workflow ID.
	 * @param string $email Email address.
	 * @param array  $properties Custom properties.
	 * @return array
	 */
	public function enroll_contact(string $workflow_id, string $email, array $properties = array()): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		if (empty($email)) {
			return array(
				'success' => false,
				'error' => 'Email address is required for workflow enrollment'
			);
		}

		$enrollment_data = array(
			'email' => sanitize_email($email)
		);

		// Add custom properties if provided
		if (!empty($properties)) {
			$enrollment_data['properties'] = $properties;
		}

		$url = sprintf('https://api.hubapi.com/automation/v2/workflows/%s/enrollments', sanitize_text_field($workflow_id));
		$response = wp_remote_post($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($enrollment_data),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'error' => 'Failed to enroll in workflow: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 || $status_code === 201) {
			return array(
				'success' => true,
				'message' => 'Contact enrolled in workflow successfully',
				'data' => $data
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to enroll in workflow (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Unenroll contact from workflow
	 *
	 * @param string $workflow_id Workflow ID.
	 * @param string $email Email address.
	 * @return array
	 */
	public function unenroll_contact(string $workflow_id, string $email): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		if (empty($email)) {
			return array(
				'success' => false,
				'error' => 'Email address is required for workflow unenrollment'
			);
		}

		$url = sprintf(
			'https://api.hubapi.com/automation/v2/workflows/%s/enrollments/%s',
			sanitize_text_field($workflow_id),
			sanitize_email($email)
		);
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
				'error' => 'Failed to unenroll from workflow: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);

		if ($status_code === 204) {
			return array(
				'success' => true,
				'message' => 'Contact unenrolled from workflow successfully'
			);
		} else {
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			
			return array(
				'success' => false,
				'error' => sprintf('Failed to unenroll from workflow (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get workflow enrollment status
	 *
	 * @param string $workflow_id Workflow ID.
	 * @param string $email Email address.
	 * @return array
	 */
	public function get_enrollment_status(string $workflow_id, string $email): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		if (empty($email)) {
			return array(
				'success' => false,
				'error' => 'Email address is required'
			);
		}

		$url = sprintf(
			'https://api.hubapi.com/automation/v2/workflows/%s/enrollments/%s',
			sanitize_text_field($workflow_id),
			sanitize_email($email)
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
				'error' => 'Failed to get enrollment status: ' . sanitize_text_field($response->get_error_message())
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200) {
			return array(
				'success' => true,
				'enrolled' => true,
				'data' => $data
			);
		} elseif ($status_code === 404) {
			return array(
				'success' => true,
				'enrolled' => false,
				'data' => null
			);
		} else {
			$error_message = sanitize_text_field($data['message'] ?? 'Unknown error');
			return array(
				'success' => false,
				'error' => sprintf('Failed to get enrollment status (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get workflow performance metrics
	 *
	 * @param string $workflow_id Workflow ID.
	 * @return array
	 */
	public function get_workflow_metrics(string $workflow_id): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/automation/v3/workflows/%s/metrics', sanitize_text_field($workflow_id));
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
				'error' => 'Failed to fetch workflow metrics: ' . sanitize_text_field($response->get_error_message())
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
				'error' => sprintf('Failed to fetch workflow metrics (%d): %s', $status_code, $error_message)
			);
		}
	}

	/**
	 * Get workflow details
	 *
	 * @param string $workflow_id Workflow ID.
	 * @return array
	 */
	public function get_workflow_details(string $workflow_id): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		$url = sprintf('https://api.hubapi.com/automation/v3/workflows/%s', sanitize_text_field($workflow_id));
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
				'error' => 'Failed to fetch workflow details: ' . sanitize_text_field($response->get_error_message())
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
				'error' => sprintf('Failed to fetch workflow details (%d): %s', $status_code, $error_message)
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