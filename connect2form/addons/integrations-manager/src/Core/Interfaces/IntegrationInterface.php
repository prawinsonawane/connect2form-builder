<?php

namespace Connect2Form\Integrations\Core\Interfaces;

/**
 * Integration Interface
 *
 * Contract that all integrations must implement
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Integration Interface
 *
 * Contract that all integrations must implement
 *
 * @since    2.0.0
 */
interface IntegrationInterface {

	/**
	 * Get unique integration identifier
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getId(): string;

	/**
	 * Get human-readable integration name
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getName(): string;

	/**
	 * Get integration description
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getDescription(): string;

	/**
	 * Get integration version
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getVersion(): string;

	/**
	 * Get integration icon (dashicon class or URL)
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getIcon(): string;

	/**
	 * Get integration color (hex code)
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	public function getColor(): string;

	/**
	 * Check if integration is properly configured
	 *
	 * @since    2.0.0
	 * @return   bool
	 */
	public function isConfigured(): bool;

	/**
	 * Check if integration is enabled for given settings
	 *
	 * @since    2.0.0
	 * @param    array $settings Integration settings.
	 * @return   bool
	 */
	public function isEnabled( array $settings ): bool;

	/**
	 * Get authentication fields configuration
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getAuthFields(): array;

	/**
	 * Test API connection with given credentials
	 *
	 * @since    2.0.0
	 * @param    array $credentials API credentials.
	 * @return   array
	 */
	public function testConnection( array $credentials ): array;

	/**
	 * Get available actions this integration supports
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getAvailableActions(): array;

	/**
	 * Get field mapping configuration for an action
	 *
	 * @since    2.0.0
	 * @param    string $action Action name.
	 * @return   array
	 */
	public function getFieldMapping( string $action ): array;

	/**
	 * Process form submission
	 *
	 * @since    2.0.0
	 * @param    int   $submission_id Submission ID.
	 * @param    array $form_data     Form data.
	 * @param    array $settings      Integration settings.
	 * @return   array
	 */
	public function processSubmission( int $submission_id, array $form_data, array $settings ): array;

	/**
	 * Get integration-specific settings fields
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getSettingsFields(): array;

	/**
	 * Validate integration settings
	 *
	 * @since    2.0.0
	 * @param    array $settings Settings to validate.
	 * @return   array
	 */
	public function validateSettings( array $settings ): array;

	/**
	 * Get default settings values
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getDefaultSettings(): array;
} 
