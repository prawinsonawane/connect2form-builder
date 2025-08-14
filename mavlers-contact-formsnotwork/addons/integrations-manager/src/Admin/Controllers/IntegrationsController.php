<?php

namespace MavlersCF\Integrations\Admin\Controllers;

use MavlersCF\Integrations\Core\Registry\IntegrationRegistry;
use MavlersCF\Integrations\Core\Assets\AssetManager;
use MavlersCF\Integrations\Admin\Views\IntegrationsView;

/**
 * Integrations Controller
 * 
 * Handles integrations overview page
 */
class IntegrationsController {

	/**
	 * Integration registry
	 *
	 * @var IntegrationRegistry
	 */
	private $registry;

	/**
	 * Asset manager
	 *
	 * @var AssetManager
	 */
	private $asset_manager;

	/**
	 * View instance
	 *
	 * @var IntegrationsView
	 */
	private $view;

	/**
	 * Constructor
	 *
	 * @param IntegrationRegistry $registry     Integration registry.
	 * @param AssetManager       $asset_manager Asset manager.
	 */
	public function __construct( IntegrationRegistry $registry, AssetManager $asset_manager ) {
		$this->registry = $registry;
		$this->asset_manager = $asset_manager;
		$this->view = new IntegrationsView();
	}

	/**
	 * Render the integrations page
	 */
	public function render_page(): void {
		$current_tab = $this->get_current_tab();
		$integrations = $this->registry->getAll();

		// Prepare data for view
		$view_data = array(
			'current_tab' => $current_tab,
			'integrations' => $integrations,
			'stats' => $this->get_integrations_stats(),
			'configured_count' => count( $this->registry->getConfigured() ),
			'total_count' => $this->registry->count(),
		);

		// Handle different tabs
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
	 * @param array $data View data.
	 */
	private function render_overview_tab( array $data ): void {
		$this->view->render( 'overview', $data );
	}

	/**
	 * Render settings tab
	 *
	 * @param array $data View data.
	 */
	private function render_settings_tab( array $data ): void {
		$integration_id = sanitize_text_field( $_GET['integration'] ?? '' );
		
		if ( ! empty( $integration_id ) ) {
			$integration = $this->registry->get( $integration_id );
			if ( $integration ) {
				$global_settings = $this->get_global_settings( $integration_id );
				
				$data['integration'] = $integration;
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
	 * @param array $data View data.
	 */
	private function render_logs_tab( array $data ): void {
		$integration_id = sanitize_text_field( $_GET['integration'] ?? '' );
		$data['selected_integration'] = $integration_id;
		$data['logs'] = $this->get_logs( $integration_id );
		
		$this->view->render( 'logs', $data );
	}

	/**
	 * Get current tab
	 *
	 * @return string
	 */
	private function get_current_tab(): string {
		return sanitize_text_field( $_GET['tab'] ?? 'overview' );
	}

	/**
	 * Get integrations statistics
	 *
	 * @return array
	 */
	private function get_integrations_stats(): array {
		// This would typically come from the logger service
		return array(
			'total_submissions' => 0,
			'successful_submissions' => 0,
			'failed_submissions' => 0,
			'last_30_days' => array(),
		);
	}

	/**
	 * Get global settings for integration
	 *
	 * @param string $integration_id Integration ID.
	 * @return array
	 */
	private function get_global_settings( string $integration_id ): array {
		$global_settings = get_option( 'mavlers_cf_integrations_global', array() );
		return $global_settings[ $integration_id ] ?? array();
	}

	/**
	 * Get logs for integration
	 *
	 * @param string $integration_id Integration ID.
	 * @param int    $limit         Number of logs to retrieve.
	 * @return array
	 */
	private function get_logs( string $integration_id = '', int $limit = 50 ): array {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'mavlers_cf_integration_logs';
		
		$where = '';
		$params = array();
		
		if ( ! empty( $integration_id ) ) {
			$where = 'WHERE integration_id = %s';
			$params[] = sanitize_text_field( $integration_id );
		}
		
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d",
			array_merge( $params, array( $limit ) )
		);
		
		return $wpdb->get_results( $sql, ARRAY_A );
	}
} 