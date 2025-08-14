<?php

namespace MavlersCF\Integrations\Core\Interfaces;

/**
 * Integration Interface
 * 
 * Contract that all integrations must implement
 */
interface IntegrationInterface {

	/**
	 * Get unique integration identifier
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Get human-readable integration name
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get integration description
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Get integration version
	 *
	 * @return string
	 */
	public function getVersion(): string;

	/**
	 * Get integration icon (dashicon class or URL)
	 *
	 * @return string
	 */
	public function getIcon(): string;

	/**
	 * Get integration color (hex code)
	 *
	 * @return string
	 */
	public function getColor(): string;

	/**
	 * Check if integration is properly configured
	 *
	 * @return bool
	 */
	public function isConfigured(): bool;

	/**
	 * Check if integration is enabled for given settings
	 *
	 * @param array $settings Integration settings.
	 * @return bool
	 */
	public function isEnabled( array $settings ): bool;

	/**
	 * Get authentication fields configuration
	 *
	 * @return array
	 */
	public function getAuthFields(): array;

	/**
	 * Test API connection with given credentials
	 *
	 * @param array $credentials Integration credentials.
	 * @return array
	 */
	public function testConnection( array $credentials ): array;

	/**
	 * Get available actions this integration supports
	 *
	 * @return array
	 */
	public function getAvailableActions(): array;

	/**
	 * Get field mapping configuration for an action
	 *
	 * @param string $action Action name.
	 * @return array
	 */
	public function getFieldMapping( string $action ): array;

	/**
	 * Process form submission
	 *
	 * @param int   $submission_id Submission ID.
	 * @param array $form_data     Form data.
	 * @param array $settings      Integration settings.
	 * @return array
	 */
	public function processSubmission( int $submission_id, array $form_data, array $settings ): array;

	/**
	 * Get integration-specific settings fields
	 *
	 * @return array
	 */
	public function getSettingsFields(): array;

	/**
	 * Validate integration settings
	 *
	 * @param array $settings Integration settings.
	 * @return array
	 */
	public function validateSettings( array $settings ): array;

	/**
	 * Get default settings values
	 *
	 * @return array
	 */
	public function getDefaultSettings(): array;
} 