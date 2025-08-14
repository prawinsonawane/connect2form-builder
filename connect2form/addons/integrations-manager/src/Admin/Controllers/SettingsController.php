<?php

namespace Connect2Form\Integrations\Admin\Controllers;

use Connect2Form\Integrations\Core\Registry\IntegrationRegistry;
use Connect2Form\Integrations\Core\Assets\AssetManager;
use Connect2Form\Integrations\Admin\Views\IntegrationsView;

/**
 * Settings Controller
 *
 * Handles integration settings pages
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Settings Controller Class
 *
 * Handles integration settings pages
 *
 * @since    2.0.0
 */
class SettingsController {

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
	 * Render the settings page
	 *
	 * @since    2.0.0
	 */
	public function render_page(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'connect2form' ) );
		}

		// Handle form submission.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verification occurs in handle_settings_form_submission()
		if ( ! empty( $_POST ) && isset( $_POST['connect2form_integrations_nonce'] ) ) {
			$this->handle_settings_form_submission();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading view-only param
		$integration_id = isset( $_GET['integration'] ) ? sanitize_text_field( wp_unslash( $_GET['integration'] ) ) : '';

		// If specific integration is requested, render its settings.
		if ( ! empty( $integration_id ) ) {
			$this->render_integration_settings( $integration_id );
			return;
		}

		// Otherwise render the main settings page.
		$this->view->render( 'settings-main', array(
			'integrations'    => $this->registry->getAll(),
			'global_settings' => $this->get_global_settings(),
		) );
	}

	/**
	 * Render individual integration settings
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $integration_id Integration ID.
	 */
	private function render_integration_settings( string $integration_id ): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'connect2form' ) );
		}

		$integration = $this->registry->get( $integration_id );

		if ( ! $integration ) {
			wp_die( esc_html__( 'Integration not found', 'connect2form' ) );
		}

		// Load integration-specific assets.
		$this->load_integration_assets( $integration_id );

		// Render the integration settings template.
		$this->view->render( 'integration-settings', array(
			'integration'     => $integration,
			'integration_id'  => $integration_id,
		) );
	}

	/**
	 * Load integration-specific assets
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $integration_id Integration ID.
	 */
	private function load_integration_assets( string $integration_id ): void {
		// Load integration-specific CSS.
		$css_file = CONNECT2FORM_INTEGRATIONS_DIR . "assets/css/admin/{$integration_id}.css";
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				"connect2form-{$integration_id}",
				CONNECT2FORM_INTEGRATIONS_URL . "assets/css/admin/{$integration_id}.css",
				array(),
				'2.0.0'
			);
		}

		// Load integration-specific JS.
		$js_file = CONNECT2FORM_INTEGRATIONS_DIR . "assets/js/admin/{$integration_id}.js";
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				"connect2form-{$integration_id}",
				CONNECT2FORM_INTEGRATIONS_URL . "assets/js/admin/{$integration_id}.js",
				array( 'jquery', 'wp-util' ),
				'2.0.0',
				true
			);

			// Localize script with integration-specific data.
			wp_localize_script( "connect2form-{$integration_id}", "connect2formCF{$integration_id}", array(
				'nonce'         => wp_create_nonce( 'connect2form_nonce' ),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'integrationId' => $integration_id,
				'strings'       => array(
					'testing'         => __( 'Testing...', 'connect2form' ),
					'connected'       => __( 'Connected', 'connect2form' ),
					'disconnected'    => __( 'Disconnected', 'connect2form' ),
					'testConnection'  => __( 'Test Connection', 'connect2form' ),
					'savingSettings'  => __( 'Saving...', 'connect2form' ),
					'settingsSaved'   => __( 'Settings saved successfully!', 'connect2form' ),
					'connectionFailed' => __( 'Connection failed', 'connect2form' ),
				),
			) );
		}
	}

	/**
	 * Handle settings form submission
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function handle_settings_form_submission(): void {
        // Verify nonce.
        $nonce = isset( $_POST['connect2form_integrations_nonce'] )
            ? sanitize_text_field( wp_unslash( (string) $_POST['connect2form_integrations_nonce'] ) )
            : '';
        if ( ! wp_verify_nonce( $nonce, 'connect2form_integrations_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'connect2form' ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'connect2form' ) );
		}

		// Get current settings.
		$current_settings = $this->get_global_settings();

        // Update settings.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- already verified above
		$posted_settings_raw = isset( $_POST['connect2form_integrations_settings'] )
			? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['connect2form_integrations_settings'] ) )
			: array();


        // Sanitize individual settings fields.
        $enable_logging       = ! empty( $posted_settings_raw['enable_logging'] );
        $log_retention_days   = isset( $posted_settings_raw['log_retention_days'] )
            ? absint( $posted_settings_raw['log_retention_days'] )
            : 30;
        $batch_processing     = ! empty( $posted_settings_raw['batch_processing'] );

        $current_settings['enable_logging']     = (bool) $enable_logging;
        $current_settings['log_retention_days'] = $log_retention_days > 0 ? $log_retention_days : 30;
        $current_settings['batch_processing']   = (bool) $batch_processing;

		// Save settings.
		$updated = update_option( 'connect2form_integrations_settings', $current_settings );

		// Show success/error message.
		if ( $updated ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>' .
					 esc_html__( 'Settings saved successfully!', 'connect2form' ) . '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>' .
					 esc_html__( 'Failed to save settings.', 'connect2form' ) . '</p></div>';
			} );
		}
	}

	/**
	 * Get global plugin settings
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   array
	 */
	private function get_global_settings(): array {
		return get_option( 'connect2form_integrations_settings', array(
			'enable_logging'     => true,
			'log_retention_days' => 30,
			'batch_processing'   => false,
		) );
	}
} 
