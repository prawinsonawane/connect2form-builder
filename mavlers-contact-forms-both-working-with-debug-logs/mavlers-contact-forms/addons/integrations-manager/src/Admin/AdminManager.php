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
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $credentials = $_POST['credentials'] ?? [];

        if (empty($integration_id)) {
            wp_send_json_error(__('Integration ID is required', 'mavlers-contact-forms'));
        }

        $integration = $this->registry->get($integration_id);
        if (!$integration) {
            wp_send_json_error(__('Integration not found', 'mavlers-contact-forms'));
        }

        // Sanitize credentials
        $credentials = array_map('sanitize_text_field', $credentials);

        // Debug logging
        error_log('Testing connection for integration: ' . $integration_id);
        error_log('Credentials: ' . print_r($credentials, true));

        try {
            $result = $integration->testConnection($credentials);
            
            // Debug logging
            error_log('Test connection result: ' . print_r($result, true));
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => __('Connection successful!', 'mavlers-contact-forms'),
                    'data' => $result['data'] ?? []
                ]);
            } else {
                wp_send_json_error($result['error'] ?? __('Connection failed', 'mavlers-contact-forms'));
            }
        } catch (\Exception $e) {
            error_log('Test connection exception: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Save integration settings
     */
    public function ajax_save_integration_settings(): void {
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $settings_type = sanitize_text_field($_POST['settings_type'] ?? 'global'); // global or form
        $form_id = intval($_POST['form_id'] ?? 0);
        $settings = $_POST['settings'] ?? [];

        if (empty($integration_id)) {
            wp_send_json_error(__('Integration ID is required', 'mavlers-contact-forms'));
        }

        $integration = $this->registry->get($integration_id);
        if (!$integration) {
            wp_send_json_error(__('Integration not found', 'mavlers-contact-forms'));
        }

        // Sanitize settings
        $settings = $this->sanitize_settings($settings);

        // Validate settings
        $validation_errors = $integration->validateSettings($settings);
        if (!empty($validation_errors)) {
            wp_send_json_error([
                'message' => __('Settings validation failed', 'mavlers-contact-forms'),
                'errors' => $validation_errors
            ]);
        }

        try {
            if ($settings_type === 'global') {
                $result = $this->save_global_settings($integration_id, $settings);
            } else {
                $result = $this->save_form_settings($integration_id, $form_id, $settings);
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
        check_ajax_referer('mavlers_cf_integrations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'mavlers-contact-forms'));
        }

        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $data_type = sanitize_text_field($_POST['data_type'] ?? '');

        if (empty($integration_id)) {
            wp_send_json_error(__('Integration ID is required', 'mavlers-contact-forms'));
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
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_settings($value);
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            } elseif (is_numeric($value)) {
                $sanitized[$key] = is_float($value) ? floatval($value) : intval($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
} 