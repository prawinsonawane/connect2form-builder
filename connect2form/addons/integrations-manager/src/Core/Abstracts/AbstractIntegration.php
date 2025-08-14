<?php

namespace Connect2Form\Integrations\Core\Abstracts;

use Connect2Form\Integrations\Core\Interfaces\IntegrationInterface;
use Connect2Form\Integrations\Core\Services\ApiClient;
use Connect2Form\Integrations\Core\Services\Logger;

/**
 * Abstract Integration Base Class
 *
 * Provides common functionality for all integrations
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Abstract Integration Base Class
 *
 * Provides common functionality for all integrations
 *
 * @since    2.0.0
 */
abstract class AbstractIntegration implements IntegrationInterface {

	/**
	 * Integration ID.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $id    Integration ID.
	 */
	protected $id;

	/**
	 * Integration name.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $name    Integration name.
	 */
	protected $name;

	/**
	 * Integration description.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $description    Integration description.
	 */
	protected $description;

	/**
	 * Integration version.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $version    Integration version.
	 */
	protected $version;

	/**
	 * Integration icon.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $icon    Integration icon.
	 */
	protected $icon;

	/**
	 * Integration color.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $color    Integration color.
	 */
	protected $color;

	/**
	 * API client instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      ApiClient    $api_client    API client instance.
	 */
	protected $api_client;

	/**
	 * Logger instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      Logger    $logger    Logger instance.
	 */
	protected $logger;

	/**
	 * Constructor
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->api_client = new ApiClient();
		$this->logger     = new Logger();
		$this->init();
	}

	/**
	 * Initialize integration-specific setup
	 *
	 * @since    2.0.0
	 * @access   protected
	 */
	protected function init() {
		// Override in child classes if needed.
	}

	/**
	 * Get unique integration identifier
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get human-readable integration name
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get integration description
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * Get integration version
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * Get integration icon
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getIcon(): string {
		return $this->icon;
	}

	/**
	 * Get integration color
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getColor(): string {
		return $this->color;
	}

	/**
	 * Check if integration is properly configured
	 *
	 * @since    2.0.0
	 * @return   bool
	 */
	public function isConfigured(): bool {
		$global_settings = $this->getGlobalSettings();
		$auth_fields     = $this->getAuthFields();

		foreach ( $auth_fields as $field ) {
			if ( $field['required'] && empty( $global_settings[ $field['id'] ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if integration is enabled for given settings
	 *
	 * @since    2.0.0
	 * @param    array $settings Integration settings.
	 * @return   bool
	 */
	public function isEnabled( array $settings ): bool {
		return ! empty( $settings['enabled'] ) && $this->isConfigured();
	}

	/**
	 * Get global settings for this integration
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @return   array
	 */
	protected function getGlobalSettings(): array {
		$global_settings = get_option( 'connect2form_integrations_global', array() );
		return $global_settings[ $this->getId() ] ?? array();
	}

	/**
	 * Save global settings for this integration
	 *
	 * @since    2.0.0
	 * @param    array $settings Settings to save.
	 * @return   bool
	 */
	public function saveGlobalSettings( array $settings ): bool {
		$global_settings = get_option( 'connect2form_integrations_global', array() );

		$global_settings[ $this->getId() ] = $settings;

		$result = update_option( 'connect2form_integrations_global', $global_settings );

		// Verify the save worked by reading it back.
		$verify_settings = get_option( 'connect2form_integrations_global', array() );

		$integration_settings = $verify_settings[ $this->getId() ] ?? array();

		// Check if settings were saved (don't validate specific fields as they vary by integration).
		$verification_success = ! empty( $integration_settings );

		// update_option returns false if value hasn't changed, but that's still success.
		// We only care if the verification shows the settings are actually saved.
		return $verification_success;
	}

	/**
	 * Get form settings for this integration
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	protected function getFormSettings( int $form_id ): array {
		$form_settings = get_post_meta( $form_id, '_connect2form_integrations', true );
		return $form_settings[ $this->getId() ] ?? array();
	}

	/**
	 * Save form settings for this integration
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @param    int   $form_id  Form ID.
	 * @param    array $settings Settings to save.
	 * @return   bool
	 */
	protected function saveFormSettings( int $form_id, array $settings ): bool {
		$form_settings = get_post_meta( $form_id, '_connect2form_integrations', true );

		if ( ! is_array( $form_settings ) ) {
			$form_settings = array();
		}
		$form_settings[ $this->getId() ] = $settings;

		$result = update_post_meta( $form_id, '_connect2form_integrations', $form_settings );

		// Verify the save worked by reading it back.
		$verify_settings = get_post_meta( $form_id, '_connect2form_integrations', true );
		$integration_settings = $verify_settings[ $this->getId() ] ?? array();
		$verification_success = ! empty( $integration_settings );

		// update_post_meta returns false if value hasn't changed, but that's still success.
		// We only care if the verification shows the settings are actually saved.
		return $verification_success;
	}

	/**
	 * Log integration activity
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @param    string $level   Log level.
	 * @param    string $message Log message.
	 * @param    array  $context Log context.
	 */
	protected function log( string $level, string $message, array $context = array() ): void {
		$this->logger->log( $level, "[{$this->getId()}] {$message}", $context );
	}

	/**
	 * Log success message
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @param    string $message Log message.
	 * @param    array  $context Log context.
	 */
	protected function logSuccess( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log error message
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @param    string $message Log message.
	 * @param    array  $context Log context.
	 */
	protected function logError( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Make API request
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @param    string $method HTTP method.
	 * @param    string $url    Request URL.
	 * @param    array  $args   Request arguments.
	 * @return   array
	 */
	protected function makeApiRequest( string $method, string $url, array $args = array() ): array {
		return $this->api_client->request( $method, $url, $args );
	}

	/**
	 * Map form data to integration fields
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @param    array $form_data     Form data.
	 * @param    array $field_mapping Field mapping.
	 * @return   array
	 */
	protected function mapFormData( array $form_data, array $field_mapping ): array {
		$mapped_data = array();

		foreach ( $field_mapping as $integration_field => $form_field ) {
			if ( isset( $form_data[ $form_field ] ) ) {
				$mapped_data[ $integration_field ] = $form_data[ $form_field ];
			}
		}

		return $mapped_data;
	}

	/**
	 * Validate required fields are present
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @param    array $data           Data to validate.
	 * @param    array $required_fields Required fields.
	 * @return   array
	 */
	protected function validateRequiredFields( array $data, array $required_fields ): array {
		$errors = array();

		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				/* translators: %s: Field name */
				$errors[] = sprintf( __( 'Field %s is required', 'connect2form' ), $field );
			}
		}

		return $errors;
	}

	/**
	 * Get default field mapping configuration
	 *
	 * @since    2.0.0
	 * @param    string $action Action name.
	 * @return   array
	 */
	public function getFieldMapping( string $action ): array {
		// Override in child classes.
		return array();
	}

	/**
	 * Get default settings values
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getDefaultSettings(): array {
		return array(
			'enabled' => false,
			'action'  => 'subscribe',
		);
	}

	/**
	 * Validate integration settings
	 *
	 * @since    2.0.0
	 * @param    array $settings Settings to validate.
	 * @return   array
	 */
	public function validateSettings( array $settings ): array {
		$errors = array();

		// Basic validation - override in child classes for specific validation.
		if ( empty( $settings['action'] ) ) {
			$errors[] = __( 'Action is required', 'connect2form' );
		}

		return $errors;
	}

	/**
	 * Get integration-specific settings fields
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getSettingsFields(): array {
		// Override in child classes.
		return array();
	}
} 
