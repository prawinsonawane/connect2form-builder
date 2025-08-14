<?php

namespace Connect2Form\Integrations\Hubspot;

/**
 * HubSpot Workflow Manager
 *
 * Handles workflow enrollment, management, and automation
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * HubSpot Workflow Manager Class
 *
 * Handles workflow enrollment, management, and automation
 *
 * @since    2.0.0
 */
class WorkflowManager {

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
	 * Get available workflows from HubSpot
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_workflows(): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		// Get workflows.
		$url = 'https://api.hubapi.com/automation/v3/workflows';
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
				'error'   => 'Failed to fetch workflows: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			$workflows = array();

			if ( isset( $data['results'] ) ) {
				foreach ( $data['results'] as $workflow ) {
					// Only include active workflows that can be enrolled.
					if ( $workflow['enabled'] && $workflow['type'] === 'DRIP_DELAY' ) {
						$workflows[] = array(
							'id'          => $workflow['id'],
							'name'        => $workflow['name'],
							'type'        => $workflow['type'],
							'enabled'     => $workflow['enabled'],
							'description' => $workflow['description'] ?? '',
						);
					}
				}
			}

			return array(
				'success' => true,
				'data'    => $workflows,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to fetch workflows ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Enroll contact in workflow
	 *
	 * @since    2.0.0
	 * @param    string $workflow_id Workflow ID.
	 * @param    string $email       Email address.
	 * @param    array  $properties  Contact properties.
	 * @return   array
	 */
	public function enroll_contact( string $workflow_id, string $email, array $properties = array() ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		if ( empty( $email ) ) {
			return array(
				'success' => false,
				'error'   => 'Email address is required for workflow enrollment',
			);
		}

		$enrollment_data = array(
			'email' => $email,
		);

		// Add custom properties if provided.
		if ( ! empty( $properties ) ) {
			$enrollment_data['properties'] = $properties;
		}

		$url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments";
		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $enrollment_data ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to enroll in workflow: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 || $status_code === 201 ) {
			return array(
				'success' => true,
				'message' => 'Contact enrolled in workflow successfully',
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to enroll in workflow ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Unenroll contact from workflow
	 *
	 * @since    2.0.0
	 * @param    string $workflow_id Workflow ID.
	 * @param    string $email       Email address.
	 * @return   array
	 */
	public function unenroll_contact( string $workflow_id, string $email ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		if ( empty( $email ) ) {
			return array(
				'success' => false,
				'error'   => 'Email address is required for workflow unenrollment',
			);
		}

		$url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments/{$email}";
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
				'error'   => 'Failed to unenroll from workflow: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 204 ) {
			return array(
				'success' => true,
				'message' => 'Contact unenrolled from workflow successfully',
			);
		} else {
			$body         = wp_remote_retrieve_body( $response );
			$data         = json_decode( $body, true );
			$error_message = $data['message'] ?? 'Unknown error';

			return array(
				'success' => false,
				'error'   => "Failed to unenroll from workflow ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get workflow enrollment status
	 *
	 * @since    2.0.0
	 * @param    string $workflow_id Workflow ID.
	 * @param    string $email       Email address.
	 * @return   array
	 */
	public function get_enrollment_status( string $workflow_id, string $email ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		if ( empty( $email ) ) {
			return array(
				'success' => false,
				'error'   => 'Email address is required',
			);
		}

		$url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments/{$email}";
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
				'error'   => 'Failed to get enrollment status: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success'  => true,
				'enrolled' => true,
				'data'     => $data,
			);
		} elseif ( $status_code === 404 ) {
			return array(
				'success'  => true,
				'enrolled' => false,
				'data'     => null,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to get enrollment status ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get workflow performance metrics
	 *
	 * @since    2.0.0
	 * @param    string $workflow_id Workflow ID.
	 * @return   array
	 */
	public function get_workflow_metrics( string $workflow_id ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/automation/v3/workflows/{$workflow_id}/metrics";
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
				'error'   => 'Failed to fetch workflow metrics: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to fetch workflow metrics ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get workflow details
	 *
	 * @since    2.0.0
	 * @param    string $workflow_id Workflow ID.
	 * @return   array
	 */
	public function get_workflow_details( string $workflow_id ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/automation/v3/workflows/{$workflow_id}";
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
				'error'   => 'Failed to fetch workflow details: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to fetch workflow details ({$status_code}): {$error_message}",
			);
		}
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

