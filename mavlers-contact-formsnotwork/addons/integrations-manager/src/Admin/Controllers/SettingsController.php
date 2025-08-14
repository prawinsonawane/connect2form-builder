<?php

namespace MavlersCF\Integrations\Admin\Controllers;

use MavlersCF\Integrations\Core\Registry\IntegrationRegistry;
use MavlersCF\Integrations\Core\Assets\AssetManager;
use MavlersCF\Integrations\Admin\Views\IntegrationsView;

/**
 * Settings Controller
 * 
 * Handles integration settings pages
 */
class SettingsController {

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
	 * Render the settings page
	 */
	public function render_page(): void {
		$current_tab = sanitize_text_field( $_GET['tab'] ?? 'global' );
		$integration_id = sanitize_text_field( $_GET['integration'] ?? '' );
		
		// If specific integration is requested, render its settings
		if ( ! empty( $integration_id ) ) {
			$this->render_integration_settings( $integration_id );
			return;
		}
		
		// Otherwise render the main settings page
		$this->view->render( 'settings-main', array(
			'integrations' => $this->registry->getAll(),
			'global_settings' => $this->get_global_settings(),
		) );
	}

	/**
	 * Render individual integration settings
	 *
	 * @param string $integration_id Integration ID.
	 */
	private function render_integration_settings( string $integration_id ): void {
		$integration = $this->registry->get( $integration_id );
		
		if ( ! $integration ) {
			wp_die( __( 'Integration not found', 'mavlers-contact-forms' ) );
		}

		// Load integration-specific assets
		$this->load_integration_assets( $integration_id );
		
		// Render the integration settings template
		$this->view->render( 'integration-settings', array(
			'integration' => $integration,
			'integration_id' => $integration_id,
		) );
	}

	/**
	 * Load integration-specific assets
	 *
	 * @param string $integration_id Integration ID.
	 */
	private function load_integration_assets( string $integration_id ): void {
		// Load integration-specific CSS
		$css_file = MAVLERS_CF_INTEGRATIONS_DIR . "assets/css/admin/{$integration_id}.css";
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				"mavlers-cf-{$integration_id}",
				MAVLERS_CF_INTEGRATIONS_URL . "assets/css/admin/{$integration_id}.css",
				array(),
				'2.0.0'
			);
		}

		// Load integration-specific JS
		$js_file = MAVLERS_CF_INTEGRATIONS_DIR . "assets/js/admin/{$integration_id}.js";
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				"mavlers-cf-{$integration_id}",
				MAVLERS_CF_INTEGRATIONS_URL . "assets/js/admin/{$integration_id}.js",
				array( 'jquery', 'wp-util' ),
				'2.0.0',
				true
			);

			// Localize script with integration-specific data
			wp_localize_script( "mavlers-cf-{$integration_id}", "mavlersCF{$integration_id}", array(
				'nonce' => wp_create_nonce( 'mavlers_cf_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'integrationId' => sanitize_text_field( $integration_id ),
				'strings' => array(
					'testing' => __( 'Testing...', 'mavlers-contact-forms' ),
					'connected' => __( 'Connected', 'mavlers-contact-forms' ),
					'disconnected' => __( 'Disconnected', 'mavlers-contact-forms' ),
					'testConnection' => __( 'Test Connection', 'mavlers-contact-forms' ),
					'savingSettings' => __( 'Saving...', 'mavlers-contact-forms' ),
					'settingsSaved' => __( 'Settings saved successfully!', 'mavlers-contact-forms' ),
					'connectionFailed' => __( 'Connection failed', 'mavlers-contact-forms' ),
				),
			) );
		}
	}

	/**
	 * Get global plugin settings
	 *
	 * @return array
	 */
	private function get_global_settings(): array {
		return get_option( 'mavlers_cf_integrations_settings', array(
			'enable_logging' => true,
			'log_retention_days' => 30,
			'batch_processing' => false,
		) );
	}
} 