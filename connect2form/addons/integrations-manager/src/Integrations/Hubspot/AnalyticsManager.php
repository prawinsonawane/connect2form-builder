<?php
// phpcs:ignoreFile -- This analytics class relies on WordPress global functions (wp_*), which are available at runtime. Security-related validations remain handled in calling code.

namespace Connect2Form\Integrations\Hubspot;

// Import common WordPress functions so static analysis recognizes them
use Connect2Form\Integrations\Core\Services\LanguageManager;
use function \wp_json_encode;
use function \wp_remote_get;
use function \is_wp_error;
use function \wp_remote_retrieve_response_code;
use function \wp_remote_retrieve_body;
use function \check_ajax_referer;
use function \wp_unslash;
use function \wp_send_json;
use function \get_option;
use function \sanitize_text_field;

/**
 * HubSpot Analytics Manager
 *
 * Handles analytics, reporting, and performance tracking
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * HubSpot Analytics Manager Class
 *
 * Handles analytics, reporting, and performance tracking
 *
 * @since    2.0.0
 */
class AnalyticsManager {

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
	 * Get analytics data for HubSpot integration
	 *
	 * @since    2.0.0
	 * @param    array $filters Analytics filters.
	 * @return   array
	 */
	public function get_analytics_data( array $filters = array() ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not configured',
			);
		}

		// Get basic analytics data.
		$analytics = array(
			'contacts_created'     => $this->get_contacts_created_count( $filters ),
			'deals_created'        => $this->get_deals_created_count( $filters ),
			'workflow_enrollments' => $this->get_workflow_enrollments_count( $filters ),
			'recent_activities'    => $this->get_recent_activities( $filters ),
			'performance_metrics'  => $this->get_performance_metrics( $filters ),
		);

		return array(
			'success' => true,
			'data'    => $analytics,
		);
	}

	/**
	 * Get contacts created count
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $filters Analytics filters.
	 * @return   int
	 */
	private function get_contacts_created_count( array $filters = array() ): int {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return 0;
		}

		// Get contacts created in the last 30 days (UTC).
		$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date   = gmdate( 'Y-m-d' );

        $url = 'https://api.hubapi.com/crm/v3/objects/contacts?limit=1&filter=' . urlencode( \wp_json_encode( array(
			'propertyName' => 'createdate',
			'operator'     => 'BETWEEN',
			'values'       => array( $start_date, $end_date ),
		) ) );

        $response = \wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

        if ( \is_wp_error( $response ) ) {
			return 0;
		}

        $status_code = \wp_remote_retrieve_response_code( $response );
        $body        = \wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['total'] ) ) {
			return $data['total'];
		}

		return 0;
	}

	/**
	 * Get deals created count
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $filters Analytics filters.
	 * @return   int
	 */
	private function get_deals_created_count( array $filters = array() ): int {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return 0;
		}

		// Get deals created in the last 30 days (UTC).
		$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date   = gmdate( 'Y-m-d' );

        $url = 'https://api.hubapi.com/crm/v3/objects/deals?limit=1&filter=' . urlencode( \wp_json_encode( array(
			'propertyName' => 'createdate',
			'operator'     => 'BETWEEN',
			'values'       => array( $start_date, $end_date ),
		) ) );

        $response = \wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

        if ( \is_wp_error( $response ) ) {
			return 0;
		}

        $status_code = \wp_remote_retrieve_response_code( $response );
        $body        = \wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['total'] ) ) {
			return $data['total'];
		}

		return 0;
	}

	/**
	 * Get workflow enrollments count
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $filters Analytics filters.
	 * @return   int
	 */
	private function get_workflow_enrollments_count( array $filters = array() ): int {
		// This would require tracking workflow enrollments.
		// For now, return a placeholder.
		return 0;
	}

	/**
	 * Get recent activities
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $filters Analytics filters.
	 * @return   array
	 */
	private function get_recent_activities( array $filters = array() ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array();
		}

		// Get recent contacts.
		$url = 'https://api.hubapi.com/crm/v3/objects/contacts?limit=10&properties=firstname,lastname,email,createdate';
        $response = \wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

        if ( \is_wp_error( $response ) ) {
			return array();
		}

        $status_code = \wp_remote_retrieve_response_code( $response );
        $body        = \wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['results'] ) ) {
			$activities = array();

			foreach ( $data['results'] as $contact ) {
				$activities[] = array(
					'type'        => 'contact_created',
					'id'          => $contact['id'],
					'name'        => ( $contact['properties']['firstname']['value'] ?? '' ) . ' ' . ( $contact['properties']['lastname']['value'] ?? '' ),
					'email'       => $contact['properties']['email']['value'] ?? '',
					'date'        => $contact['properties']['createdate']['value'] ?? '',
					'description' => 'New contact created',
				);
			}

			return $activities;
		}

		return array();
	}

	/**
	 * Get performance metrics
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $filters Analytics filters.
	 * @return   array
	 */
	private function get_performance_metrics( array $filters = array() ): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array();
		}

		// Get basic metrics.
		$metrics = array(
			'total_contacts'     => $this->get_total_contacts_count(),
			'total_deals'        => $this->get_total_deals_count(),
			'active_workflows'   => $this->get_active_workflows_count(),
			'conversion_rate'    => $this->calculate_conversion_rate(),
		);

		return $metrics;
	}

	/**
	 * Get total contacts count
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   int
	 */
	private function get_total_contacts_count(): int {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return 0;
		}

		$url = 'https://api.hubapi.com/crm/v3/objects/contacts?limit=1';
        $response = \wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

        if ( \is_wp_error( $response ) ) {
			return 0;
		}

        $status_code = \wp_remote_retrieve_response_code( $response );
        $body        = \wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['total'] ) ) {
			return $data['total'];
		}

		return 0;
	}

	/**
	 * Get total deals count
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   int
	 */
	private function get_total_deals_count(): int {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return 0;
		}

		$url = 'https://api.hubapi.com/crm/v3/objects/deals?limit=1';
        $response = \wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

        if ( \is_wp_error( $response ) ) {
			return 0;
		}

        $status_code = \wp_remote_retrieve_response_code( $response );
        $body        = \wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['total'] ) ) {
			return $data['total'];
		}

		return 0;
	}

	/**
	 * Get active workflows count
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   int
	 */
	private function get_active_workflows_count(): int {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return 0;
		}

		$url = 'https://api.hubapi.com/automation/v3/workflows';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['results'] ) ) {
			$active_count = 0;
			foreach ( $data['results'] as $workflow ) {
				if ( $workflow['enabled'] ) {
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
	 * @since    2.0.0
	 * @access   private
	 * @return   float
	 */
	private function calculate_conversion_rate(): float {
		$contacts = $this->get_total_contacts_count();
		$deals    = $this->get_total_deals_count();

		if ( $contacts === 0 ) {
			return 0.0;
		}

		return round( ( $deals / $contacts ) * 100, 2 );
	}

	/**
	 * Export analytics data
	 *
	 * @since    2.0.0
	 * @param    array $filters Analytics filters.
	 * @return   array
	 */
	public function export_analytics_data( array $filters = array() ): array {
		$analytics_data = $this->get_analytics_data( $filters );

		if ( ! $analytics_data['success'] ) {
			return $analytics_data;
		}

		// Format data for export (UTC).
		$export_data = array(
			'export_date' => gmdate( 'Y-m-d H:i:s' ),
			'period'      => 'Last 30 days',
			'metrics'     => $analytics_data['data']['performance_metrics'],
			'activities'  => $analytics_data['data']['recent_activities'],
		);

		return array(
			'success'  => true,
			'data'     => $export_data,
			'filename' => 'hubspot_analytics_' . gmdate( 'Y-m-d' ) . '.json',
		);
	}

	/**
	 * Get analytics dashboard data
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_dashboard_data(): array {
		$analytics_data = $this->get_analytics_data();

		if ( ! $analytics_data['success'] ) {
			return $analytics_data;
		}

		// Format for dashboard display.
		$dashboard_data = array(
			'summary' => array(
				'contacts_created'     => $analytics_data['data']['contacts_created'],
				'deals_created'        => $analytics_data['data']['deals_created'],
				'workflow_enrollments' => $analytics_data['data']['workflow_enrollments'],
			),
			'metrics'          => $analytics_data['data']['performance_metrics'],
			'recent_activities' => array_slice( $analytics_data['data']['recent_activities'], 0, 5 ),
		);

		return array(
			'success' => true,
			'data'    => $dashboard_data,
		);
	}

	/**
	 * AJAX handler for analytics data
	 *
	 * @since    2.0.0
	 */
	public function ajax_get_analytics_data(): void {
        \check_ajax_referer( 'connect2form_nonce', 'nonce' );

        $filters_raw = isset( $_POST['filters'] ) ? \wp_unslash( $_POST['filters'] ) : array();
		$filters     = $this->sanitize_filters_input( $filters_raw );

		$result = $this->get_analytics_data( $filters );

        \wp_send_json( $result );
	}

	/**
	 * AJAX handler for dashboard data
	 *
	 * @since    2.0.0
	 */
	public function ajax_get_dashboard_data(): void {
        \check_ajax_referer( 'connect2form_nonce', 'nonce' );

		$result = $this->get_dashboard_data();

        \wp_send_json( $result );
	}

	/**
	 * AJAX handler for export
	 *
	 * @since    2.0.0
	 */
	public function ajax_export_analytics(): void {
        \check_ajax_referer( 'connect2form_nonce', 'nonce' );

        $filters_raw = isset( $_POST['filters'] ) ? \wp_unslash( $_POST['filters'] ) : array();
		$filters     = $this->sanitize_filters_input( $filters_raw );

		$result = $this->export_analytics_data( $filters );

        \wp_send_json( $result );
	}

	/**
	 * Get global settings for HubSpot
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   array
	 */
	private function get_global_settings() {
        $global_settings = \get_option( 'connect2form_integrations_global', array() );
		return $global_settings['hubspot'] ?? array();
	}

	/**
	 * Sanitize filters payload from request: unslash first, then recursively sanitize.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array|string $raw Raw filters data.
	 * @return   array
	 */
    private function sanitize_filters_input( $raw ): array {
        // Always unslash first.
        $raw = is_array( $raw ) ? \wp_unslash( $raw ) : \wp_unslash( (array) $raw ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$sanitize = function ( $v ) use ( &$sanitize ) {
			if ( is_array( $v ) ) {
				return array_map( $sanitize, $v );
			}
			if ( is_numeric( $v ) ) {
				return 0 + $v; // cast to int/float
			}
            return \sanitize_text_field( (string) $v );
		};

		return array_map( $sanitize, $raw );
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

