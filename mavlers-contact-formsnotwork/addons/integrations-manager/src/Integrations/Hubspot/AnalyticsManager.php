<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Analytics Manager
 * 
 * Handles analytics, reporting, and performance tracking
 */
class AnalyticsManager {

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
	 * Get analytics data for HubSpot integration
	 *
	 * @param array $filters Analytics filters.
	 * @return array
	 */
	public function get_analytics_data(array $filters = array()): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array(
				'success' => false,
				'error' => 'HubSpot not configured'
			);
		}

		// Get basic analytics data
		$analytics = array(
			'contacts_created' => $this->get_contacts_created_count($filters),
			'deals_created' => $this->get_deals_created_count($filters),
			'workflow_enrollments' => $this->get_workflow_enrollments_count($filters),
			'recent_activities' => $this->get_recent_activities($filters),
			'performance_metrics' => $this->get_performance_metrics($filters)
		);

		return array(
			'success' => true,
			'data' => $analytics
		);
	}

	/**
	 * Get contacts created count
	 *
	 * @param array $filters Analytics filters.
	 * @return int
	 */
	private function get_contacts_created_count(array $filters = array()): int {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return 0;
		}

		// Get contacts created in the last 30 days
		$start_date = date('Y-m-d', strtotime('-30 days'));
		$end_date = date('Y-m-d');

		$filter_data = array(
			'propertyName' => 'createdate',
			'operator' => 'BETWEEN',
			'values' => array($start_date, $end_date)
		);

		$url = sprintf(
			'https://api.hubapi.com/crm/v3/objects/contacts?limit=1&filter=%s',
			urlencode(wp_json_encode($filter_data))
		);

		$response = wp_remote_get($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return 0;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 && isset($data['total'])) {
			return (int) $data['total'];
		}

		return 0;
	}

	/**
	 * Get deals created count
	 *
	 * @param array $filters Analytics filters.
	 * @return int
	 */
	private function get_deals_created_count(array $filters = array()): int {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return 0;
		}

		// Get deals created in the last 30 days
		$start_date = date('Y-m-d', strtotime('-30 days'));
		$end_date = date('Y-m-d');

		$filter_data = array(
			'propertyName' => 'createdate',
			'operator' => 'BETWEEN',
			'values' => array($start_date, $end_date)
		);

		$url = sprintf(
			'https://api.hubapi.com/crm/v3/objects/deals?limit=1&filter=%s',
			urlencode(wp_json_encode($filter_data))
		);

		$response = wp_remote_get($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return 0;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 && isset($data['total'])) {
			return (int) $data['total'];
		}

		return 0;
	}

	/**
	 * Get workflow enrollments count
	 *
	 * @param array $filters Analytics filters.
	 * @return int
	 */
	private function get_workflow_enrollments_count(array $filters = array()): int {
		// This would require tracking workflow enrollments
		// For now, return a placeholder
		return 0;
	}

	/**
	 * Get recent activities
	 *
	 * @param array $filters Analytics filters.
	 * @return array
	 */
	private function get_recent_activities(array $filters = array()): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array();
		}

		// Get recent contacts
		$url = 'https://api.hubapi.com/crm/v3/objects/contacts?limit=10&properties=firstname,lastname,email,createdate';
		$response = wp_remote_get($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return array();
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 && isset($data['results'])) {
			$activities = array();
			
			foreach ($data['results'] as $contact) {
				$first_name = sanitize_text_field($contact['properties']['firstname']['value'] ?? '');
				$last_name = sanitize_text_field($contact['properties']['lastname']['value'] ?? '');
				$email = sanitize_email($contact['properties']['email']['value'] ?? '');
				$created_date = sanitize_text_field($contact['properties']['createdate']['value'] ?? '');

				$activities[] = array(
					'type' => 'contact_created',
					'id' => sanitize_text_field($contact['id']),
					'name' => trim($first_name . ' ' . $last_name),
					'email' => $email,
					'date' => $created_date,
					'description' => 'New contact created'
				);
			}

			return $activities;
		}

		return array();
	}

	/**
	 * Get performance metrics
	 *
	 * @param array $filters Analytics filters.
	 * @return array
	 */
	private function get_performance_metrics(array $filters = array()): array {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return array();
		}

		// Get basic metrics
		$metrics = array(
			'total_contacts' => $this->get_total_contacts_count(),
			'total_deals' => $this->get_total_deals_count(),
			'active_workflows' => $this->get_active_workflows_count(),
			'conversion_rate' => $this->calculate_conversion_rate()
		);

		return $metrics;
	}

	/**
	 * Get total contacts count
	 *
	 * @return int
	 */
	private function get_total_contacts_count(): int {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return 0;
		}

		$url = 'https://api.hubapi.com/crm/v3/objects/contacts?limit=1';
		$response = wp_remote_get($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return 0;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 && isset($data['total'])) {
			return (int) $data['total'];
		}

		return 0;
	}

	/**
	 * Get total deals count
	 *
	 * @return int
	 */
	private function get_total_deals_count(): int {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return 0;
		}

		$url = 'https://api.hubapi.com/crm/v3/objects/deals?limit=1';
		$response = wp_remote_get($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return 0;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 && isset($data['total'])) {
			return (int) $data['total'];
		}

		return 0;
	}

	/**
	 * Get active workflows count
	 *
	 * @return int
	 */
	private function get_active_workflows_count(): int {
		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';

		if (empty($access_token)) {
			return 0;
		}

		$url = 'https://api.hubapi.com/automation/v3/workflows';
		$response = wp_remote_get($url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field($access_token),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		));

		if (is_wp_error($response)) {
			return 0;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status_code === 200 && isset($data['results'])) {
			$active_count = 0;
			foreach ($data['results'] as $workflow) {
				if (!empty($workflow['enabled'])) {
					$active_count++;
				}
			}
			return $active_count;
		}

		return 0;
	}

	/**
	 * Calculate conversion rate
	 *
	 * @return float
	 */
	private function calculate_conversion_rate(): float {
		$contacts = $this->get_total_contacts_count();
		$deals = $this->get_total_deals_count();

		if ($contacts === 0) {
			return 0.0;
		}

		return round(($deals / $contacts) * 100, 2);
	}

	/**
	 * Export analytics data
	 *
	 * @param array $filters Analytics filters.
	 * @return array
	 */
	public function export_analytics_data(array $filters = array()): array {
		$analytics_data = $this->get_analytics_data($filters);
		
		if (!$analytics_data['success']) {
			return $analytics_data;
		}

		// Format data for export
		$export_data = array(
			'export_date' => date('Y-m-d H:i:s'),
			'period' => 'Last 30 days',
			'metrics' => $analytics_data['data']['performance_metrics'],
			'activities' => $analytics_data['data']['recent_activities']
		);

		return array(
			'success' => true,
			'data' => $export_data,
			'filename' => 'hubspot_analytics_' . date('Y-m-d') . '.json'
		);
	}

	/**
	 * Get analytics dashboard data
	 *
	 * @return array
	 */
	public function get_dashboard_data(): array {
		$analytics_data = $this->get_analytics_data();
		
		if (!$analytics_data['success']) {
			return $analytics_data;
		}

		// Format for dashboard display
		$dashboard_data = array(
			'summary' => array(
				'contacts_created' => $analytics_data['data']['contacts_created'],
				'deals_created' => $analytics_data['data']['deals_created'],
				'workflow_enrollments' => $analytics_data['data']['workflow_enrollments']
			),
			'metrics' => $analytics_data['data']['performance_metrics'],
			'recent_activities' => array_slice($analytics_data['data']['recent_activities'], 0, 5)
		);

		return array(
			'success' => true,
			'data' => $dashboard_data
		);
	}

	/**
	 * AJAX handler for analytics data
	 */
	public function ajax_get_analytics_data(): void {
		check_ajax_referer('mavlers_cf_nonce', 'nonce');
		
		$filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : array();
		$result = $this->get_analytics_data($filters);
		
		wp_send_json($result);
	}

	/**
	 * AJAX handler for dashboard data
	 */
	public function ajax_get_dashboard_data(): void {
		check_ajax_referer('mavlers_cf_nonce', 'nonce');
		
		$result = $this->get_dashboard_data();
		
		wp_send_json($result);
	}

	/**
	 * AJAX handler for export
	 */
	public function ajax_export_analytics(): void {
		check_ajax_referer('mavlers_cf_nonce', 'nonce');
		
		$filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : array();
		$result = $this->export_analytics_data($filters);
		
		wp_send_json($result);
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