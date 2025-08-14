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
     */
    public function getId(): string;

    /**
     * Get human-readable integration name
     */
    public function getName(): string;

    /**
     * Get integration description
     */
    public function getDescription(): string;

    /**
     * Get integration version
     */
    public function getVersion(): string;

    /**
     * Get integration icon (dashicon class or URL)
     */
    public function getIcon(): string;

    /**
     * Get integration color (hex code)
     */
    public function getColor(): string;

    /**
     * Check if integration is properly configured
     */
    public function isConfigured(): bool;

    /**
     * Check if integration is enabled for given settings
     */
    public function isEnabled(array $settings): bool;

    /**
     * Get authentication fields configuration
     */
    public function getAuthFields(): array;

    /**
     * Test API connection with given credentials
     */
    public function testConnection(array $credentials): array;

    /**
     * Get available actions this integration supports
     */
    public function getAvailableActions(): array;

    /**
     * Get field mapping configuration for an action
     */
    public function getFieldMapping(string $action): array;

    /**
     * Process form submission
     */
    public function processSubmission(int $submission_id, array $form_data, array $settings): array;

    /**
     * Get integration-specific settings fields
     */
    public function getSettingsFields(): array;

    /**
     * Validate integration settings
     */
    public function validateSettings(array $settings): array;

    /**
     * Get default settings values
     */
    public function getDefaultSettings(): array;
} 