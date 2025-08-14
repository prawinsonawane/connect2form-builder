<?php

namespace MavlersCF\Integrations\Admin\Controllers;

use MavlersCF\Integrations\Core\Registry\IntegrationRegistry;
use MavlersCF\Integrations\Core\Assets\AssetManager;
use MavlersCF\Integrations\Admin\Views\IntegrationsView;

/**
 * Integrations Controller
 * 
 * Handles integrations overview page
 */
class IntegrationsController {

    private $registry;
    private $asset_manager;
    private $view;

    public function __construct(IntegrationRegistry $registry, AssetManager $asset_manager) {
        $this->registry = $registry;
        $this->asset_manager = $asset_manager;
        $this->view = new IntegrationsView();
    }

    /**
     * Render the integrations page
     */
    public function render_page(): void {
        $current_tab = $this->get_current_tab();
        $integrations = $this->registry->getAll();

        // Prepare data for view
        $view_data = [
            'current_tab' => $current_tab,
            'integrations' => $integrations,
            'stats' => $this->get_integrations_stats(),
            'configured_count' => count($this->registry->getConfigured()),
            'total_count' => $this->registry->count()
        ];

        // Handle different tabs
        switch ($current_tab) {
            case 'settings':
                $this->render_settings_tab($view_data);
                break;
            case 'logs':
                $this->render_logs_tab($view_data);
                break;
            default:
                $this->render_overview_tab($view_data);
                break;
        }
    }

    /**
     * Render overview tab
     */
    private function render_overview_tab(array $data): void {
        $this->view->render('overview', $data);
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab(array $data): void {
        $integration_id = sanitize_text_field($_GET['integration'] ?? '');
        
        if (!empty($integration_id)) {
            $integration = $this->registry->get($integration_id);
            if ($integration) {
                $global_settings = $this->get_global_settings($integration_id);
                
                // Debug logging
             //   error_log('IntegrationsController: Rendering settings for ' . $integration_id);
              //  error_log('IntegrationsController: Global settings: ' . print_r($global_settings, true));
                
                $data['integration'] = $integration;
                $data['global_settings'] = $global_settings;
                $this->view->render('integration-settings', $data);
                return;
            }
        }

        $this->view->render('settings-list', $data);
    }

    /**
     * Render logs tab
     */
    private function render_logs_tab(array $data): void {
        $integration_id = sanitize_text_field($_GET['integration'] ?? '');
        $data['selected_integration'] = $integration_id;
        $data['logs'] = $this->get_logs($integration_id);
        
        $this->view->render('logs', $data);
    }

    /**
     * Get current tab
     */
    private function get_current_tab(): string {
        return sanitize_text_field($_GET['tab'] ?? 'overview');
    }

    /**
     * Get integrations statistics
     */
    private function get_integrations_stats(): array {
        // This would typically come from the logger service
        return [
            'total_submissions' => 0,
            'successful_submissions' => 0,
            'failed_submissions' => 0,
            'last_30_days' => []
        ];
    }

    /**
     * Get global settings for integration
     */
    private function get_global_settings(string $integration_id): array {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        return $global_settings[$integration_id] ?? [];
    }

    /**
     * Get logs for integration
     */
    private function get_logs(string $integration_id = '', int $limit = 50): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mavlers_cf_integration_logs';
        
        $where = '';
        $params = [];
        
        if (!empty($integration_id)) {
            $where = 'WHERE integration_id = %s';
            $params[] = $integration_id;
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d",
            array_merge($params, [$limit])
        );
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
} 