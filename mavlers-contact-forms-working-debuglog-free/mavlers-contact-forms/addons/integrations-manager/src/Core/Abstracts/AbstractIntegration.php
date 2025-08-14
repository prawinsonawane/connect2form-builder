<?php

namespace MavlersCF\Integrations\Core\Abstracts;

use MavlersCF\Integrations\Core\Interfaces\IntegrationInterface;
use MavlersCF\Integrations\Core\Services\ApiClient;
use MavlersCF\Integrations\Core\Services\Logger;

/**
 * Abstract Integration Base Class
 * 
 * Provides common functionality for all integrations
 */
abstract class AbstractIntegration implements IntegrationInterface {

    protected $id;
    protected $name;
    protected $description;
    protected $version;
    protected $icon;
    protected $color;
    protected $api_client;
    protected $logger;

    public function __construct() {
        $this->api_client = new ApiClient();
        $this->logger = new Logger();
        $this->init();
    }

    /**
     * Initialize integration-specific setup
     */
    protected function init() {
        // Override in child classes if needed
    }

    /**
     * Get unique integration identifier
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Get human-readable integration name
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Get integration description
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Get integration version
     */
    public function getVersion(): string {
        return $this->version;
    }

    /**
     * Get integration icon
     */
    public function getIcon(): string {
        return $this->icon;
    }

    /**
     * Get integration color
     */
    public function getColor(): string {
        return $this->color;
    }

    /**
     * Check if integration is properly configured
     */
    public function isConfigured(): bool {
        $global_settings = $this->getGlobalSettings();
        $auth_fields = $this->getAuthFields();

        foreach ($auth_fields as $field) {
            if ($field['required'] && empty($global_settings[$field['id']])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if integration is enabled for given settings
     */
    public function isEnabled(array $settings): bool {
        return !empty($settings['enabled']) && $this->isConfigured();
    }

    /**
     * Get global settings for this integration
     */
    protected function getGlobalSettings(): array {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        return $global_settings[$this->getId()] ?? [];
    }

    /**
     * Save global settings for this integration
     */
    public function saveGlobalSettings(array $settings): bool {
      //  error_log('AbstractIntegration: saveGlobalSettings called for integration: ' . $this->getId());
      //  error_log('AbstractIntegration: Settings to save: ' . print_r($settings, true));
        
        $global_settings = get_option('mavlers_cf_integrations_global', []);
      //  error_log('AbstractIntegration: Existing global settings: ' . print_r($global_settings, true));
        
        $global_settings[$this->getId()] = $settings;
      //  error_log('AbstractIntegration: Updated global settings: ' . print_r($global_settings, true));
        
        $result = update_option('mavlers_cf_integrations_global', $global_settings);
      //  error_log('AbstractIntegration: update_option result: ' . ($result ? 'success' : 'failed'));
        
        // Verify the save worked by reading it back
        $verify_settings = get_option('mavlers_cf_integrations_global', []);
      //  error_log('AbstractIntegration: Verification - saved settings: ' . print_r($verify_settings, true));
        
        $integration_settings = $verify_settings[$this->getId()] ?? [];
      //  error_log('AbstractIntegration: Verification - integration settings: ' . print_r($integration_settings, true));
        
        // Check if settings were saved (don't validate specific fields as they vary by integration)
        $verification_success = !empty($integration_settings);
      //  error_log('AbstractIntegration: Verification success: ' . ($verification_success ? 'true' : 'false'));
        
        // update_option returns false if value hasn't changed, but that's still success
        // We only care if the verification shows the settings are actually saved
        return $verification_success;
    }

    /**
     * Get form settings for this integration
     */
    protected function getFormSettings(int $form_id): array {
        $form_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        return $form_settings[$this->getId()] ?? [];
    }

    /**
     * Save form settings for this integration
     */
    protected function saveFormSettings(int $form_id, array $settings): bool {
      //  error_log('AbstractIntegration: saveFormSettings called for form ID: ' . $form_id);
      //  error_log('AbstractIntegration: Integration ID: ' . $this->getId());
      //  error_log('AbstractIntegration: Settings: ' . print_r($settings, true));
        
        $form_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
      //  error_log('AbstractIntegration: Existing form settings: ' . print_r($form_settings, true));
        
        if (!is_array($form_settings)) {
            $form_settings = [];
        }
        $form_settings[$this->getId()] = $settings;
      //  error_log('AbstractIntegration: Updated form settings: ' . print_r($form_settings, true));
        
        $result = update_post_meta($form_id, '_mavlers_cf_integrations', $form_settings);
      //  error_log('AbstractIntegration: update_post_meta result: ' . ($result ? 'success' : 'failed'));
        
        // Verify the save worked by reading it back
        $verify_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        $integration_settings = $verify_settings[$this->getId()] ?? [];
        $verification_success = !empty($integration_settings);
      //  error_log('AbstractIntegration: Form settings verification success: ' . ($verification_success ? 'true' : 'false'));
        
        // update_post_meta returns false if value hasn't changed, but that's still success
        // We only care if the verification shows the settings are actually saved
        return $verification_success;
    }

    /**
     * Log integration activity
     */
    protected function log(string $level, string $message, array $context = []): void {
        $this->logger->log($level, "[{$this->getId()}] {$message}", $context);
    }

    /**
     * Log success message
     */
    protected function logSuccess(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }

    /**
     * Log error message
     */
    protected function logError(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }

    /**
     * Make API request
     */
    protected function makeApiRequest(string $method, string $url, array $args = []): array {
        return $this->api_client->request($method, $url, $args);
    }

    /**
     * Map form data to integration fields
     */
    protected function mapFormData(array $form_data, array $field_mapping): array {
        $mapped_data = [];

        foreach ($field_mapping as $integration_field => $form_field) {
            if (isset($form_data[$form_field])) {
                $mapped_data[$integration_field] = $form_data[$form_field];
            }
        }

        return $mapped_data;
    }

    /**
     * Validate required fields are present
     */
    protected function validateRequiredFields(array $data, array $required_fields): array {
        $errors = [];

        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('Field %s is required', 'mavlers-contact-forms'), $field);
            }
        }

        return $errors;
    }

    /**
     * Get default field mapping configuration
     */
    public function getFieldMapping(string $action): array {
        // Override in child classes
        return [];
    }

    /**
     * Get default settings values
     */
    public function getDefaultSettings(): array {
        return [
            'enabled' => false,
            'action' => 'subscribe'
        ];
    }

    /**
     * Validate integration settings
     */
    public function validateSettings(array $settings): array {
        $errors = [];

        // Basic validation - override in child classes for specific validation
        if (empty($settings['action'])) {
            $errors[] = __('Action is required', 'mavlers-contact-forms');
        }

        return $errors;
    }

    /**
     * Get integration-specific settings fields
     */
    public function getSettingsFields(): array {
        // Override in child classes
        return [];
    }
} 