<?php

namespace Connect2Form\Integrations\Admin;

use Connect2Form\Integrations\Core\Registry\IntegrationRegistry;
use Connect2Form\Integrations\Core\Assets\AssetManager;
use Connect2Form\Integrations\Admin\Controllers\IntegrationsController;
use Connect2Form\Integrations\Admin\Controllers\SettingsController;

/**
 * Admin Manager
 *
 * Manages admin interface components
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Admin Manager Class
 *
 * Manages admin interface components
 *
 * @since    2.0.0
 */
class AdminManager {

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
	 * Integrations controller instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      IntegrationsController    $integrations_controller    Integrations controller instance.
	 */
	private $integrations_controller;

	/**
	 * Settings controller instance.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      SettingsController    $settings_controller    Settings controller instance.
	 */
	private $settings_controller;

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

		$this->init_controllers();
		$this->init_hooks();
	}

	/**
	 * Initialize controllers
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function init_controllers(): void {
		$this->integrations_controller = new IntegrationsController( $this->registry, $this->asset_manager );
		$this->settings_controller     = new SettingsController( $this->registry, $this->asset_manager );
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function init_hooks(): void {
		// Admin menu - use lower priority to ensure main menu is registered first.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );

		// Assets.
		$this->asset_manager->init_default_assets();

		// AJAX handlers.
		add_action( 'wp_ajax_connect2form_test_integration', array( $this, 'ajax_test_integration' ) );
		add_action( 'wp_ajax_connect2form_save_integration_settings', array( $this, 'ajax_save_integration_settings' ) );
		add_action( 'wp_ajax_connect2form_get_integration_data', array( $this, 'ajax_get_integration_data' ) );
	}

	/**
	 * Add admin menu pages
	 *
	 * @since    2.0.0
	 */
	public function add_admin_menu(): void {
		// Check if the parent menu exists.
		global $menu;
		$parent_menu_exists = false;

		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[2] ) && ( $menu_item[2] === 'connect2form' || $menu_item[2] === 'connect2form' ) ) {
				$parent_menu_exists = true;
				break;
			}
		}

		if ( ! $parent_menu_exists ) {
			return; // Don't add submenu pages if parent menu doesn't exist.
		}

		// Main integrations page.
		add_submenu_page(
			'connect2form',
			__( 'Integrations', 'connect2form' ),
			__( 'Integrations', 'connect2form' ),
			'manage_options',
			'connect2form-integrations',
			array( $this->integrations_controller, 'render_page' )
		);

		// Settings page.
		add_submenu_page(
			'connect2form',
			__( 'Integration Settings', 'connect2form' ),
			__( 'Integration Settings', 'connect2form' ),
			'manage_options',
			'connect2form-integration-settings',
			array( $this->settings_controller, 'render_page' )
		);
	}

	/**
	 * AJAX: Test integration connection
	 *
	 * @since    2.0.0
	 */
	public function ajax_test_integration(): void {
		check_ajax_referer( 'connect2form_integrations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'connect2form' ) );
		}

		$integration_id = isset( $_POST['integration_id'] ) ? sanitize_text_field( wp_unslash( $_POST['integration_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- credentials array is sanitized below with array_map
		$credentials_raw = isset( $_POST['credentials'] ) ? wp_unslash( $_POST['credentials'] ) : array();
		$credentials_raw = is_array( $credentials_raw ) ? $credentials_raw : array();
		$credentials     = array_map( 'sanitize_text_field', $credentials_raw );

		if ( empty( $integration_id ) ) {
			wp_send_json_error( __( 'Integration ID is required', 'connect2form' ) );
		}

		$integration = $this->registry->get( $integration_id );
		if ( ! $integration ) {
			wp_send_json_error( __( 'Integration not found', 'connect2form' ) );
		}

		// Credentials already sanitized above.

		// Debug logging.

		try {
			$result = $integration->testConnection( $credentials );

			// Debug logging.

			if ( $result['success'] ) {
				wp_send_json_success( array(
					'message' => __( 'Connection successful!', 'connect2form' ),
					'data'    => $result['data'] ?? array(),
				) );
			} else {
				wp_send_json_error( $result['error'] ?? __( 'Connection failed', 'connect2form' ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Save integration settings
	 *
	 * @since    2.0.0
	 */
	public function ajax_save_integration_settings(): void {
		check_ajax_referer( 'connect2form_integrations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'connect2form' ) );
		}

		$integration_id = isset( $_POST['integration_id'] ) ? sanitize_text_field( wp_unslash( $_POST['integration_id'] ) ) : '';
		$settings_type  = isset( $_POST['settings_type'] ) ? sanitize_text_field( wp_unslash( $_POST['settings_type'] ) ) : 'global'; // global or form
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- form_id is cast to int for sanitization
		$form_id        = isset( $_POST['form_id'] ) ? (int) wp_unslash( $_POST['form_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- settings array is sanitized below with sanitize_settings()
		$settings_raw   = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$settings_raw   = is_array( $settings_raw ) ? $settings_raw : array();
		// Sanitize nested settings.
		$settings_sanitized = $this->sanitize_settings( $settings_raw );

		if ( empty( $integration_id ) ) {
			wp_send_json_error( __( 'Integration ID is required', 'connect2form' ) );
		}

		$integration = $this->registry->get( $integration_id );
		if ( ! $integration ) {
			wp_send_json_error( __( 'Integration not found', 'connect2form' ) );
		}

		// Sanitize settings.
		$settings = $this->sanitize_settings( $settings_raw );

		// Validate settings.
		$validation_errors = $integration->validateSettings( $settings );
		if ( ! empty( $validation_errors ) ) {
			wp_send_json_error( array(
				'message' => __( 'Settings validation failed', 'connect2form' ),
				'errors'  => $validation_errors,
			) );
		}

		try {
			if ( $settings_type === 'global' ) {
				$result = $this->save_global_settings( $integration_id, $settings_sanitized );
			} else {
				$result = $this->save_form_settings( $integration_id, $form_id, $settings_sanitized );
			}

			if ( $result ) {
				wp_send_json_success( __( 'Settings saved successfully!', 'connect2form' ) );
			} else {
				wp_send_json_error( __( 'Failed to save settings', 'connect2form' ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Get integration data
	 *
	 * @since    2.0.0
	 */
	public function ajax_get_integration_data(): void {
		check_ajax_referer( 'connect2form_integrations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'connect2form' ) );
		}

		$integration_id = isset( $_POST['integration_id'] ) ? sanitize_text_field( wp_unslash( $_POST['integration_id'] ) ) : '';
		$data_type      = isset( $_POST['data_type'] ) ? sanitize_text_field( wp_unslash( $_POST['data_type'] ) ) : '';

		if ( empty( $integration_id ) ) {
			wp_send_json_error( __( 'Integration ID is required', 'connect2form' ) );
		}

		$integration = $this->registry->get( $integration_id );
		if ( ! $integration ) {
			wp_send_json_error( __( 'Integration not found', 'connect2form' ) );
		}

		try {
			$data = array();

			switch ( $data_type ) {
				case 'auth_fields':
					$data = $integration->getAuthFields();
					break;
				case 'settings_fields':
					$data = $integration->getSettingsFields();
					break;
				case 'available_actions':
					$data = $integration->getAvailableActions();
					break;
				case 'field_mapping':
					$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
					$data   = $integration->getFieldMapping( $action );
					break;
				default:
					wp_send_json_error( __( 'Invalid data type', 'connect2form' ) );
			}

			wp_send_json_success( $data );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Save global settings for integration
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $integration_id Integration ID.
	 * @param    array  $settings      Settings to save.
	 * @return   bool
	 */
	private function save_global_settings( string $integration_id, array $settings ): bool {
		$global_settings = get_option( 'connect2form_integrations_global', array() );
		$global_settings[ $integration_id ] = $settings;
		return update_option( 'connect2form_integrations_global', $global_settings );
	}

	/**
	 * Save form settings for integration
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $integration_id Integration ID.
	 * @param    int    $form_id       Form ID.
	 * @param    array  $settings      Settings to save.
	 * @return   bool
	 */
	private function save_form_settings( string $integration_id, int $form_id, array $settings ): bool {
		$form_settings = get_post_meta( $form_id, '_connect2form_integrations', true );
		if ( ! is_array( $form_settings ) ) {
			$form_settings = array();
		}
		$form_settings[ $integration_id ] = $settings;
		return update_post_meta( $form_id, '_connect2form_integrations', $form_settings );
	}

	/**
	 * Sanitize settings array
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    array $settings Settings to sanitize.
	 * @return   array
	 */
	private function sanitize_settings( array $settings ): array {
		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_settings( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = is_float( $value ) ? floatval( $value ) : intval( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}
} 

