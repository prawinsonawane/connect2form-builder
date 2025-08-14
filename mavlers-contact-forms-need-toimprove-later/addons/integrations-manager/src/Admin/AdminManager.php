<?php

namespace MavlersCF\Integrations\Admin;

use MavlersCF\Integrations\Core\Registry\IntegrationRegistry;
use MavlersCF\Integrations\Core\Assets\AssetManager;
use MavlersCF\Integrations\Admin\Controllers\IntegrationsController;
use MavlersCF\Integrations\Admin\Controllers\SettingsController;

/**
 * Admin Manager
 * 
 * Manages admin interface components
 */
class AdminManager {

    private $registry;
    private $asset_manager;
    private $integrations_controller;
    private $settings_controller;

    public function __construct(IntegrationRegistry $registry, AssetManager $asset_manager) {
        $this->registry = $registry;
        $this->asset_manager = $asset_manager;
        
        $this->init_controllers();
        $this->init_hooks();
    }

    /**
     * Initialize controllers
     */
    private function init_controllers(): void {
        $this->integrations_controller = new IntegrationsController($this->registry, $this->asset_manager);
        $this->settings_controller = new SettingsController($this->registry, $this->asset_manager);
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Assets
        $this->asset_manager->init_default_assets();
        
        // AJAX handlers
        add_action('wp_ajax_mavlers_cf_test_integration', [$this, 'ajax_test_integration']);
        add_action('wp_ajax_mavlers_cf_save_integration_settings', [$this, 'ajax_save_integration_settings']);
        add_action('wp_ajax_mavlers_cf_get_integration_data', [$this, 'ajax_get_integration_data']);
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu(): void {
        // Main integrations page
        add_submenu_page(
            'mavlers-contact-forms',
            __('Integrations', 'mavlers-contact-forms'),
            __('Integrations', 'mavlers-contact-forms'),
            'manage_options',
            'mavlers-cf-integrations',
            [$this->integrations_controller, 'render_page']
        );

        // Settings page
        add_submenu_page(
            'mavlers-contact-forms',
            __('Integration Settings', 'mavlers-contact-forms'),
            __('Integration Settings', 'mavlers-contact-forms'),
            'manage_options',
            'mavlers-cf-integration-settings',
            [$this->settings_controller, 'render_page']
        );
    }

    /**
     * AJAX: Test integration connection
     */
    public function ajax_test_integration(): void {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce')) {
            wp_send_json_error(__('Security check failed', 'mavlers-contact-forms'));
        }

        // Check for multiple capabilities to support both global and form-specific settings
        $has_permission = current_user_can('manage_options') || 
                         current_user_can('administrator') || 
                         current_user_can('edit_posts');
        
        if (!$has_permission) {
            wp_send_json_error(__('Unauthorized', 'mavlers-contact-forms'));
        }

        // Check rate limiting for API calls
        $user_id = get_current_user_id();
        $rate_limit_key = "test_integration_{$user_id}";
        if (!SecurityManager::checkRateLimit($rate_limit_key, 10, 3600)) {
            wp_send_json_error(__('Rate limit exceeded. Please wait before trying again.', 'mavlers-contact-forms'));
        }

        // Validate and sanitize input
        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $credentials = $_POST['credentials'] ?? [];

        if (empty($integration_id)) {
            wp_send_json_error(__('Integration ID is required', 'mavlers-contact-forms'));
        }

        // Validate integration ID format
        if (!preg_match('/^[a-z0-9_-]+$/', $integration_id)) {
            wp_send_json_error(__('Invalid integration ID format', 'mavlers-contact-forms'));
        }

        $integration = $this->registry->get($integration_id);
        if (!$integration) {
            wp_send_json_error(__('Integration not found', 'mavlers-contact-forms'));
        }

        // Validate credentials structure
        if (!is_array($credentials)) {
            wp_send_json_error(__('Invalid credentials format', 'mavlers-contact-forms'));
        }

        // Sanitize credentials
        $sanitized_credentials = [];
        foreach ($credentials as $key => $value) {
            if (is_string($value)) {
                $sanitized_credentials[sanitize_text_field($key)] = sanitize_text_field($value);
            }
        }

        try {
            $result = $integration->testConnection($sanitized_credentials);
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => __('Connection successful!', 'mavlers-contact-forms'),
                    'data' => $result['data'] ?? []
                ]);
            } else {
                wp_send_json_error($result['error'] ?? __('Connection failed', 'mavlers-contact-forms'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Save integration settings
     */
    public function ajax_save_integration_settings(): void {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce')) {
            wp_send_json_error(__('Security check failed', 'mavlers-contact-forms'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'mavlers-contact-forms'));
        }

        // Check rate limiting for settings saves
        $user_id = get_current_user_id();
        $rate_limit_key = "save_settings_{$user_id}";
        if (!SecurityManager::checkRateLimit($rate_limit_key, 20, 3600)) {
            wp_send_json_error(__('Rate limit exceeded. Please wait before trying again.', 'mavlers-contact-forms'));
        }

        // Validate and sanitize input
        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $settings_type = sanitize_text_field($_POST['settings_type'] ?? 'global');
        $form_id = intval($_POST['form_id'] ?? 0);
        $settings = $_POST['settings'] ?? [];

        // Validate required fields
        if (empty($integration_id)) {
            wp_send_json_error(__('Integration ID is required', 'mavlers-contact-forms'));
        }

        // Validate integration ID format
        if (!preg_match('/^[a-z0-9_-]+$/', $integration_id)) {
            wp_send_json_error(__('Invalid integration ID format', 'mavlers-contact-forms'));
        }

        // Validate settings type
        if (!in_array($settings_type, ['global', 'form'])) {
            wp_send_json_error(__('Invalid settings type', 'mavlers-contact-forms'));
        }

        // Validate form ID for form settings
        if ($settings_type === 'form' && $form_id <= 0) {
            wp_send_json_error(__('Valid form ID is required for form settings', 'mavlers-contact-forms'));
        }

        $integration = $this->registry->get($integration_id);
        if (!$integration) {
            wp_send_json_error(__('Integration not found', 'mavlers-contact-forms'));
        }

        // Validate settings structure
        if (!is_array($settings)) {
            wp_send_json_error(__('Invalid settings format', 'mavlers-contact-forms'));
        }

        // Sanitize settings
        $sanitized_settings = $this->sanitize_settings($settings);

        // Validate settings
        $validation_errors = $integration->validateSettings($sanitized_settings);
        if (!empty($validation_errors)) {
            wp_send_json_error([
                'message' => __('Settings validation failed', 'mavlers-contact-forms'),
                'errors' => $validation_errors
            ]);
        }

        try {
            if ($settings_type === 'global') {
                $result = $this->save_global_settings($integration_id, $sanitized_settings);
            } else {
                $result = $this->save_form_settings($integration_id, $form_id, $sanitized_settings);
            }

            if ($result) {
                wp_send_json_success(__('Settings saved successfully!', 'mavlers-contact-forms'));
            } else {
                wp_send_json_error(__('Failed to save settings', 'mavlers-contact-forms'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get integration data
     */
    public function ajax_get_integration_data(): void {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_integrations_nonce')) {
            wp_send_json_error(__('Security check failed', 'mavlers-contact-forms'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'mavlers-contact-forms'));
        }

        // Check rate limiting for data retrieval
        $user_id = get_current_user_id();
        $rate_limit_key = "get_data_{$user_id}";
        if (!SecurityManager::checkRateLimit($rate_limit_key, 50, 3600)) {
            wp_send_json_error(__('Rate limit exceeded. Please wait before trying again.', 'mavlers-contact-forms'));
        }

        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $data_type = sanitize_text_field($_POST['data_type'] ?? '');

        if (empty($integration_id)) {
            wp_send_json_error(__('Integration ID is required', 'mavlers-contact-forms'));
        }

        // Validate integration ID format
        if (!preg_match('/^[a-z0-9_-]+$/', $integration_id)) {
            wp_send_json_error(__('Invalid integration ID format', 'mavlers-contact-forms'));
        }

        // Validate data type
        $valid_data_types = ['auth_fields', 'settings_fields', 'available_actions', 'field_mapping'];
        if (!in_array($data_type, $valid_data_types)) {
            wp_send_json_error(__('Invalid data type', 'mavlers-contact-forms'));
        }

        $integration = $this->registry->get($integration_id);
        if (!$integration) {
            wp_send_json_error(__('Integration not found', 'mavlers-contact-forms'));
        }

        try {
            $data = [];

            switch ($data_type) {
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
                    $action = sanitize_text_field($_POST['action'] ?? '');
                    if (empty($action)) {
                        wp_send_json_error(__('Action is required for field mapping', 'mavlers-contact-forms'));
                    }
                    $data = $integration->getFieldMapping($action);
                    break;
                default:
                    wp_send_json_error(__('Invalid data type', 'mavlers-contact-forms'));
            }

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Save global settings for integration
     */
    private function save_global_settings(string $integration_id, array $settings): bool {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        $global_settings[$integration_id] = $settings;
        return update_option('mavlers_cf_integrations_global', $global_settings);
    }

    /**
     * Save form settings for integration
     */
    private function save_form_settings(string $integration_id, int $form_id, array $settings): bool {
        $form_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        if (!is_array($form_settings)) {
            $form_settings = [];
        }
        $form_settings[$integration_id] = $settings;
        return update_post_meta($form_id, '_mavlers_cf_integrations', $form_settings);
    }

    /**
     * Sanitize settings array
     */
    private function sanitize_settings(array $settings): array {
        $sanitized = [];

        foreach ($settings as $key => $value) {
            // Sanitize key
            $sanitized_key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitize_settings($value);
            } elseif (is_bool($value)) {
                $sanitized[$sanitized_key] = (bool) $value;
            } elseif (is_numeric($value)) {
                $sanitized[$sanitized_key] = is_float($value) ? floatval($value) : intval($value);
            } elseif (is_string($value)) {
                // Special handling for different field types
                if (strpos($sanitized_key, 'api_key') !== false || strpos($sanitized_key, 'token') !== false) {
                    // Don't sanitize API keys/tokens to preserve special characters
                    $sanitized[$sanitized_key] = sanitize_text_field($value);
                } elseif (strpos($sanitized_key, 'email') !== false) {
                    $sanitized[$sanitized_key] = sanitize_email($value);
                } elseif (strpos($sanitized_key, 'url') !== false) {
                    $sanitized[$sanitized_key] = esc_url_raw($value);
                } else {
                    $sanitized[$sanitized_key] = sanitize_text_field($value);
                }
            } else {
                // Skip non-string, non-array, non-numeric, non-boolean values
                continue;
            }
        }

        return $sanitized;
    }
} 