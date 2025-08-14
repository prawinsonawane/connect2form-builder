<?php

namespace Connect2Form\Integrations\Hubspot;

/**
 * HubSpot Company Manager
 *
 * Handles company operations and contact associations
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * HubSpot Company Manager Class
 *
 * Handles company operations and contact associations
 *
 * @since    2.0.0
 */
class CompanyManager {

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
	 * Get companies from HubSpot
	 *
	 * @since    2.0.0
	 * @param    int $limit Company limit.
	 * @return   array
	 */
	public function get_companies( int $limit = 100 ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/crm/v3/objects/companies?limit={$limit}";
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
				'error'   => 'Failed to fetch companies: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			$companies = array();

			if ( isset( $data['results'] ) ) {
				foreach ( $data['results'] as $company ) {
					$companies[] = array(
						'id'      => $company['id'],
						'name'    => $company['properties']['name']['value'] ?? '',
						'domain'  => $company['properties']['domain']['value'] ?? '',
						'industry' => $company['properties']['industry']['value'] ?? '',
						'city'    => $company['properties']['city']['value'] ?? '',
						'state'   => $company['properties']['state']['value'] ?? '',
						'country' => $company['properties']['country']['value'] ?? '',
					);
				}
			}

			return array(
				'success' => true,
				'data'    => $companies,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to fetch companies ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Create a new company in HubSpot
	 *
	 * @since    2.0.0
	 * @param    array $company_data Company data.
	 * @return   array
	 */
	public function create_company( array $company_data ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$company_payload = array(
			'properties' => $company_data,
		);

		$url = 'https://api.hubapi.com/crm/v3/objects/companies';
		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $company_payload ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to create company: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 || $status_code === 201 ) {
			return array(
				'success' => true,
				'message' => 'Company created successfully',
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to create company ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Associate contact with company
	 *
	 * @since    2.0.0
	 * @param    string $contact_id Contact ID.
	 * @param    string $company_id Company ID.
	 * @return   array
	 */
	public function associate_contact_with_company( string $contact_id, string $company_id ): array {
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
					'associationTypeId'   => 1, // Contact to Company association
				),
			),
		);

		$url = "https://api.hubapi.com/crm/v3/objects/contacts/{$contact_id}/associations/companies/{$company_id}";
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
				'error'   => 'Failed to associate contact with company: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 200 || $status_code === 201 ) {
			return array(
				'success' => true,
				'message' => 'Contact associated with company successfully',
			);
		} else {
			$body         = wp_remote_retrieve_body( $response );
			$data         = json_decode( $body, true );
			$error_message = $data['message'] ?? 'Unknown error';

			return array(
				'success' => false,
				'error'   => "Failed to associate contact with company ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get company by ID
	 *
	 * @since    2.0.0
	 * @param    string $company_id Company ID.
	 * @return   array
	 */
	public function get_company( string $company_id ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$url = "https://api.hubapi.com/crm/v3/objects/companies/{$company_id}";
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
				'error'   => 'Failed to fetch company: ' . $response->get_error_message(),
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
				'error'   => "Failed to fetch company ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Update company in HubSpot
	 *
	 * @since    2.0.0
	 * @param    string $company_id   Company ID.
	 * @param    array  $company_data Company data.
	 * @return   array
	 */
	public function update_company( string $company_id, array $company_data ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$company_payload = array(
			'properties' => $company_data,
		);

		$url = "https://api.hubapi.com/crm/v3/objects/companies/{$company_id}";
		$response = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $company_payload ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to update company: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => 'Company updated successfully',
				'data'    => $data,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to update company ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Search companies by name
	 *
	 * @since    2.0.0
	 * @param    string $search_term Search term.
	 * @param    int    $limit       Search limit.
	 * @return   array
	 */
	public function search_companies( string $search_term, int $limit = 10 ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		$filter = array(
			'propertyName' => 'name',
			'operator'     => 'CONTAINS_TOKEN',
			'value'        => $search_term,
		);

		$url = "https://api.hubapi.com/crm/v3/objects/companies?limit={$limit}&filter=" . urlencode( wp_json_encode( $filter ) );
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
				'error'   => 'Failed to search companies: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			$companies = array();

			if ( isset( $data['results'] ) ) {
				foreach ( $data['results'] as $company ) {
					$companies[] = array(
						'id'       => $company['id'],
						'name'     => $company['properties']['name']['value'] ?? '',
						'domain'   => $company['properties']['domain']['value'] ?? '',
						'industry' => $company['properties']['industry']['value'] ?? '',
					);
				}
			}

			return array(
				'success' => true,
				'data'    => $companies,
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "Failed to search companies ({$status_code}): {$error_message}",
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

