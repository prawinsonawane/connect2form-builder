<?php

namespace Connect2Form\Integrations\Admin\Controllers;

use Connect2Form\Integrations\Core\Registry\IntegrationRegistry;
use Connect2Form\Integrations\Core\Assets\AssetManager;
use Connect2Form\Integrations\Admin\Views\IntegrationsView;

/**
 * Integrations Controller
 *
 * Handles integrations overview page
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Integrations Controller Class
 *
 * Handles integrations overview page
 *
 * @since    2.0.0
 */
class IntegrationsController {

	/**
	 * Integration registry instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      IntegrationRegistry    $registry    Integration registry instance.
	 */
	private $registry;

	/**
	 * Asset manager instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      AssetManager    $asset_manager    Asset manager instance.
	 */
	private $asset_manager;

	/**
	 * View instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      IntegrationsView    $view    View instance.
	 */
	private $view;

	/**
	 * Constructor
	 *
	 * @since    2.0.0
	 * @param    IntegrationRegistry $registry      Integration registry.
	 * @param    AssetManager        $asset_manager Asset manager.
	 */
	public function __construct( IntegrationRegistry $registry, AssetManager $asset_manager ) {
		$this->registry      = $registry;
		$this->asset_manager = $asset_manager;
		$this->view          = new IntegrationsView();
	}

	/**
	 * Enqueue integration assets
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function enqueue_integrations_assets(): void {
		// Enqueue main integrations admin CSS.
		wp_enqueue_style(
			'connect2form-integrations-admin',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/css/admin/integrations-admin.css',
			array(),
			CONNECT2FORM_INTEGRATIONS_VERSION
		);

		// Enqueue main integrations admin JS.
		wp_enqueue_script(
			'connect2form-integrations-admin',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/integrations-admin.js',
			array( 'jquery', 'wp-util' ),
			CONNECT2FORM_INTEGRATIONS_VERSION,
			true
		);

		// Enqueue Mailchimp assets.
		wp_enqueue_style(
			'connect2form-mailchimp',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/css/admin/mailchimp.css',
			array(),
			CONNECT2FORM_INTEGRATIONS_VERSION
		);

		wp_enqueue_script(
			'connect2form-mailchimp',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/mailchimp.js',
			array( 'jquery', 'wp-util' ),
			CONNECT2FORM_INTEGRATIONS_VERSION,
			true
		);

		// Enqueue HubSpot assets.
		wp_enqueue_style(
			'connect2form-hubspot',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/css/admin/hubspot.css',
			array(),
			CONNECT2FORM_INTEGRATIONS_VERSION
		);

		wp_enqueue_script(
			'connect2form-hubspot',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/hubspot.js',
			array( 'jquery', 'wp-util' ),
			CONNECT2FORM_INTEGRATIONS_VERSION,
			true
		);

		// Localize scripts.
		wp_localize_script( 'connect2form-mailchimp', 'connect2formCFMailchimp', array(
			'nonce'   => wp_create_nonce( 'connect2form_nonce' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'strings' => array(
				'testing'         => __( 'Testing...', 'connect2form' ),
				'connected'       => __( 'Connected', 'connect2form' ),
				'disconnected'    => __( 'Disconnected', 'connect2form' ),
				'testConnection'  => __( 'Test Connection', 'connect2form' ),
				'savingSettings'  => __( 'Saving...', 'connect2form' ),
				'settingsSaved'   => __( 'Settings saved successfully!', 'connect2form' ),
				'connectionFailed' => __( 'Connection failed', 'connect2form' ),
			),
		) );

		wp_localize_script( 'connect2form-hubspot', 'connect2formCFHubspot', array(
			'nonce'   => wp_create_nonce( 'connect2form_nonce' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'strings' => array(
				'testing'         => __( 'Testing...', 'connect2form' ),
				'connected'       => __( 'Connected', 'connect2form' ),
				'disconnected'    => __( 'Disconnected', 'connect2form' ),
				'testConnection'  => __( 'Test Connection', 'connect2form' ),
				'savingSettings'  => __( 'Saving...', 'connect2form' ),
				'settingsSaved'   => __( 'Settings saved successfully!', 'connect2form' ),
				'connectionFailed' => __( 'Connection failed', 'connect2form' ),
			),
		) );

		// Also localize integrations admin script.
		wp_localize_script( 'connect2form-integrations-admin', 'connect2formCFIntegrations', array(
			'nonce'    => wp_create_nonce( 'connect2form_nonce' ),
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'pluginUrl' => CONNECT2FORM_INTEGRATIONS_URL,
		) );
	}

	/**
	 * Render the integrations page
	 *
	 * @since    2.0.0
	 */
	public function render_page(): void {
		// Enqueue admin assets for integrations.
		$this->enqueue_integrations_assets();
		$current_tab   = $this->get_current_tab();
		$integrations  = $this->registry->getAll();

		// Prepare data for view.
		$view_data = array(
			'current_tab'      => $current_tab,
			'integrations'     => $integrations,
			'stats'            => $this->get_integrations_stats(),
			'configured_count' => count( $this->registry->getConfigured() ),
			'total_count'      => $this->registry->count(),
		);

		// Handle different tabs.
		switch ( $current_tab ) {
			case 'settings':
				$this->render_settings_tab( $view_data );
				break;
			case 'logs':
				$this->render_logs_tab( $view_data );
				break;
			default:
				$this->render_overview_tab( $view_data );
				break;
		}
	}

	/**
	 * Render overview tab
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $data View data.
	 */
	private function render_overview_tab( array $data ): void {
		$this->view->render( 'overview', $data );
	}

	/**
	 * Render settings tab
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $data View data.
	 */
	private function render_settings_tab( array $data ): void {
		// Read-only admin state; use filter_input to avoid direct superglobal access.
		$integration_raw = filter_input( INPUT_GET, 'integration', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$integration_id  = $integration_raw !== null ? sanitize_text_field( (string) $integration_raw ) : '';

		if ( ! empty( $integration_id ) ) {
			$integration = $this->registry->get( $integration_id );
			if ( $integration ) {
				$global_settings = $this->get_global_settings( $integration_id );

				// Debug logging.

				$data['integration']     = $integration;
				$data['global_settings'] = $global_settings;
				$this->view->render( 'integration-settings', $data );
				return;
			}
		}

		$this->view->render( 'settings-list', $data );
	}

	/**
	 * Render logs tab
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $data View data.
	 */
	private function render_logs_tab( array $data ): void {
		// Read-only admin state; use filter_input to avoid direct superglobal access.
		$integration_raw = filter_input( INPUT_GET, 'integration', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$integration_id  = $integration_raw !== null ? sanitize_text_field( (string) $integration_raw ) : '';
		$data['selected_integration'] = $integration_id;
		$data['logs']                 = $this->get_logs( $integration_id );

		$this->view->render( 'logs', $data );
	}

	/**
	 * Get current tab
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   string
	 */
	private function get_current_tab(): string {
		// Read-only admin state; use filter_input to avoid direct superglobal access.
		$tab_raw  = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$tab_value = $tab_raw !== null ? (string) $tab_raw : 'overview';
		return sanitize_text_field( $tab_value );
	}

	/**
	 * Get integrations statistics
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   array
	 */
	private function get_integrations_stats(): array {
		// This would typically come from the logger service.
		return array(
			'total_submissions'     => 0,
			'successful_submissions' => 0,
			'failed_submissions'    => 0,
			'last_30_days'          => array(),
		);
	}

	/**
	 * Get global settings for integration
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $integration_id Integration ID.
	 * @return   array
	 */
	private function get_global_settings( string $integration_id ): array {
		$global_settings = get_option( 'connect2form_integrations_global', array() );
		return $global_settings[ $integration_id ] ?? array();
	}

	/**
	 * Get logs for integration
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $integration_id Integration ID.
	 * @param    int    $limit          Log limit.
	 * @return   array
	 */
	private function get_logs( string $integration_id = '', int $limit = 50 ): array {
		if ( class_exists( '\\Connect2Form\\Integrations\\Core\\Services\\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$filters = array();
			if ( ! empty( $integration_id ) ) {
				$filters['integration_id'] = $integration_id;
			}
			return $service_manager->database()->getLogs( $filters, $limit );
		}

		// Fallback to direct DB.
        global $wpdb;

        $table_name = $wpdb->prefix . 'connect2form_integration_logs';

        // Validate table identifier to avoid unsafe interpolation in SQL identifiers.
        if ( function_exists( '\\c2f_is_valid_prefixed_table' ) && ! \c2f_is_valid_prefixed_table( $table_name ) ) {
            return array();
        }

		// Ensure table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check; no caching needed for schema queries
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- INFORMATION_SCHEMA check for schema validation; not cacheable
        if ( $wpdb->get_var( $wpdb->prepare( 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table_name ) ) !== $table_name ) {
			$this->create_integration_logs_table();
		}

		$limit = absint( $limit );

		if ( ! empty( $integration_id ) ) {
			// Ensure that the table name is validated
			$table_name = c2f_is_valid_prefixed_table( $table_name ) ? $table_name : ''; // Validate table name
		
			if ( ! empty( $table_name ) ) {
				return $wpdb->get_results(
					// The table name is now validated, and it's safe to interpolate here
					$wpdb->prepare(
						"SELECT * FROM {$table_name} WHERE integration_id = %s ORDER BY created_at DESC LIMIT %d",
						$integration_id,
						$limit
					),
					ARRAY_A
				);
			} else {
				// Handle case where the table name is not valid
				return false;
			}
		}
		
		
        return $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
            $wpdb->prepare(
                "SELECT * FROM `{$table_name}` ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
	}

	/**
	 * Create integration logs table if it doesn't exist
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function create_integration_logs_table(): void {
		global $wpdb;

		$table_name     = $wpdb->prefix . 'connect2form_integration_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			submission_id bigint(20) unsigned DEFAULT NULL,
			integration_id varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			message text DEFAULT NULL,
			data longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_form_id (form_id),
			KEY idx_integration_id (integration_id),
			KEY idx_status (status),
			KEY idx_created_at (created_at),
			KEY idx_form_integration (form_id, integration_id),
			KEY idx_status_created (status, created_at)
		 ) {$charset_collate};";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
} 


