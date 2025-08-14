<?php

namespace Connect2Form\Integrations\Hubspot;

/**
 * HubSpot Deal Manager
 *
 * Handles deal creation, management, and pipeline operations
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * HubSpot Deal Manager Class
 *
 * Handles deal creation, management, and pipeline operations
 *
 * @since    2.0.0
 */
class DealManager {

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
	 * Get available pipelines from HubSpot
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_pipelines(): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = 'https://api.hubapi.com/crm/v3/pipelines/deals';
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
				'error'   => 'Failed to fetch pipelines: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			$pipelines = array();

			if ( isset( $data['results'] ) ) {
				foreach ( $data['results'] as $pipeline ) {
					$pipelines[] = array(
						'id'           => $pipeline['id'],
						'label'        => $pipeline['label'],
						'displayOrder' => $pipeline['displayOrder'],
						'stages'       => $pipeline['stages'] ?? array(),
					);
				}
			}

			return array(
				'success' => true,
				'data'    => $pipelines,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to fetch pipelines ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get stages for a specific pipeline
	 *
	 * @since    2.0.0
	 * @param    string $pipeline_id Pipeline ID.
	 * @return   array
	 */
	public function get_pipeline_stages( string $pipeline_id ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/crm/v3/pipelines/deals/{$pipeline_id}";
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
				'error'   => 'Failed to fetch pipeline stages: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			$stages = array();

			if ( isset( $data['stages'] ) ) {
				foreach ( $data['stages'] as $stage ) {
					$stages[] = array(
						'id'           => $stage['id'],
						'label'        => $stage['label'],
						'displayOrder' => $stage['displayOrder'],
						'probability'  => $stage['probability'] ?? 0,
					);
				}
			}

			return array(
				'success' => true,
				'data'    => $stages,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to fetch pipeline stages ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Create a new deal in HubSpot
	 *
	 * @since    2.0.0
	 * @param    array  $deal_data   Deal data.
	 * @param    string $pipeline_id Pipeline ID.
	 * @param    string $stage_id    Stage ID.
	 * @return   array
	 */
	public function create_deal( array $deal_data, string $pipeline_id = '', string $stage_id = '' ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		// Prepare deal properties.
		$properties = $deal_data['properties'] ?? array();

		// Add pipeline and stage if provided.
		if ( ! empty( $pipeline_id ) ) {
			$properties['pipeline'] = $pipeline_id;
		}

		if ( ! empty( $stage_id ) ) {
			$properties['dealstage'] = $stage_id;
		}

		$deal_payload = array(
			'properties' => $properties,
		);

		$url = 'https://api.hubapi.com/crm/v3/objects/deals';
		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $deal_payload ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to create deal: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 || $status_code === 201 ) {
			return array(
				'success' => true,
				'message' => 'Deal created successfully',
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to create deal ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Update an existing deal in HubSpot
	 *
	 * @since    2.0.0
	 * @param    string $deal_id   Deal ID.
	 * @param    array  $deal_data Deal data.
	 * @return   array
	 */
	public function update_deal( string $deal_id, array $deal_data ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$deal_payload = array(
			'properties' => $deal_data['properties'] ?? array(),
		);

		$url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
		$response = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $deal_payload ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to update deal: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => 'Deal updated successfully',
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to update deal ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Associate deal with contact
	 *
	 * @since    2.0.0
	 * @param    string $deal_id    Deal ID.
	 * @param    string $contact_id Contact ID.
	 * @return   array
	 */
	public function associate_deal_with_contact( string $deal_id, string $contact_id ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$association_data = array(
			'types' => array(
				array(
					'associationCategory' => 'HUBSPOT_DEFINED',
					'associationTypeId'   => 4, // Deal to Contact association
				),
			),
		);

		$url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}/associations/contacts/{$contact_id}";
		$response = wp_remote_put( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $association_data ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to associate deal with contact: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 200 || $status_code === 201 ) {
			return array(
				'success' => true,
				'message' => 'Deal associated with contact successfully',
			);
		} else {
			$body         = wp_remote_retrieve_body( $response );
			$data         = json_decode( $body, true );
			$error_message = $data['message'] ?? 'Unknown error';

			return array(
				'success' => false,
				'error'   => "Failed to associate deal with contact ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get deal by ID
	 *
	 * @since    2.0.0
	 * @param    string $deal_id Deal ID.
	 * @return   array
	 */
	public function get_deal( string $deal_id ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
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
				'error'   => 'Failed to fetch deal: ' . $response->get_error_message(),
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
				'error'   => "Failed to fetch deal ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get deals with filters
	 *
	 * @since    2.0.0
	 * @param    array $filters Deal filters.
	 * @param    int   $limit   Deal limit.
	 * @return   array
	 */
	public function get_deals( array $filters = array(), int $limit = 100 ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/crm/v3/objects/deals?limit={$limit}";

		// Add filters if provided.
		if ( ! empty( $filters ) ) {
			$url .= '&filter=' . urlencode( wp_json_encode( $filters ) );
		}

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
				'error'   => 'Failed to fetch deals: ' . $response->get_error_message(),
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
				'error'   => "Failed to fetch deals ({$status_code}): {$error_message}",
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

