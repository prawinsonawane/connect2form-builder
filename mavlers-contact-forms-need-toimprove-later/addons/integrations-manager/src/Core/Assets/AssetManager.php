<?php

namespace MavlersCF\Integrations\Core\Assets;

/**
 * Asset Manager
 * 
 * Handles enqueuing of CSS and JS files with proper separation of concerns
 */
class AssetManager {

    private $assets = [];
    private $localized_data = [];

    public function __construct() {
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        \add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Register a CSS file
     */
    public function registerStyle(string $handle, string $file, array $deps = [], string $context = 'admin'): void {
        $this->assets['styles'][$context][$handle] = [
            'file' => $file,
            'deps' => $deps,
            'version' => MAVLERS_CF_INTEGRATIONS_VERSION
        ];
    }

    /**
     * Register a JS file
     */
    public function registerScript(string $handle, string $file, array $deps = ['jquery'], string $context = 'admin'): void {
        $this->assets['scripts'][$context][$handle] = [
            'file' => $file,
            'deps' => $deps,
            'version' => MAVLERS_CF_INTEGRATIONS_VERSION,
            'in_footer' => true
        ];
    }

    /**
     * Add localized data for a script
     */
    public function localizeScript(string $handle, string $object_name, array $data): void {
        $this->localized_data[$handle] = [
            'object_name' => $object_name,
            'data' => $data
        ];
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook): void {
        // Only load on our pages
        if (!$this->should_load_admin_assets($hook)) {
            return;
        }

        $this->enqueue_assets('admin');
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void {
        $this->enqueue_assets('frontend');
    }

    /**
     * Check if we should load admin assets
     */
    private function should_load_admin_assets(string $hook): bool {
        $valid_pages = [
            'mavlers-cf',
            'mavlers-contact-forms'
        ];

        foreach ($valid_pages as $page) {
            if (strpos($hook, $page) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enqueue assets for given context
     */
    private function enqueue_assets(string $context): void {
        // Enqueue styles
        if (!empty($this->assets['styles'][$context])) {
            foreach ($this->assets['styles'][$context] as $handle => $asset) {
                \wp_enqueue_style(
                    $handle,
                    $this->get_asset_url($asset['file']),
                    $asset['deps'],
                    $asset['version']
                );
            }
        }

        // Enqueue scripts
        if (!empty($this->assets['scripts'][$context])) {
            foreach ($this->assets['scripts'][$context] as $handle => $asset) {
                \wp_enqueue_script(
                    $handle,
                    $this->get_asset_url($asset['file']),
                    $asset['deps'],
                    $asset['version'],
                    $asset['in_footer']
                );

                // Add localized data if exists
                if (isset($this->localized_data[$handle])) {
                    $localize = $this->localized_data[$handle];
                    \wp_localize_script($handle, $localize['object_name'], $localize['data']);
                }
            }
        }
    }

    /**
     * Get asset URL
     */
    private function get_asset_url(string $file): string {
        return MAVLERS_CF_INTEGRATIONS_URL . 'assets/' . ltrim($file, '/');
    }

    /**
     * Initialize default assets
     */
    public function init_default_assets() {
        // Register styles
        $this->registerStyle(
            'mavlers-cf-integrations-admin',
            'css/admin/integrations-admin.css'
        );

        // Register scripts (will be enqueued at proper hook)
        $this->registerScript(
            'mavlers-cf-integrations-admin',
            'js/admin/integrations-admin.js'
        );

        $this->registerScript(
            'mavlers-cf-mailchimp-form',
            'js/admin/mailchimp-form.js',
            ['mavlers-cf-integrations-admin']
        );

        $this->registerScript(
            'mavlers-cf-hubspot-form',
            'js/admin/hubspot-form.js',
            ['mavlers-cf-integrations-admin']
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts() {
        // Enqueue core admin script
        wp_enqueue_script(
            'mavlers-cf-integrations-admin',
            $this->get_asset_url('assets/js/admin/integrations-admin.js'),
            array('jquery'),
            '1.0.1',
            true
        );

        // Add common localized data
        wp_localize_script('mavlers-cf-integrations-admin', 'mavlersCFIntegrations', [
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pluginUrl' => MAVLERS_CF_INTEGRATIONS_URL,
            'textDomain' => 'mavlers-contact-forms',
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'mavlers-contact-forms'),
                'saving' => __('Saving...', 'mavlers-contact-forms'),
                'saved' => __('Saved!', 'mavlers-contact-forms'),
                'error' => __('An error occurred. Please try again.', 'mavlers-contact-forms'),
                'testing_connection' => __('Testing connection...', 'mavlers-contact-forms'),
                'connection_successful' => __('Connection successful!', 'mavlers-contact-forms'),
                'connection_failed' => __('Connection failed. Please check your settings.', 'mavlers-contact-forms')
            ]
        ]);

        // Enqueue Mailchimp form script
        wp_enqueue_script(
            'mavlers-cf-mailchimp-form',
            $this->get_asset_url('assets/js/admin/mailchimp-form.js'),
            array('jquery', 'mavlers-cf-integrations-admin'),
            '1.0.1',
            true
        );

        // Add Mailchimp localized data
        wp_localize_script('mavlers-cf-mailchimp-form', 'mavlersCFMailchimp', [
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'strings' => [
                'connection_successful' => __('Connection successful!', 'mavlers-contact-forms'),
                'connection_failed' => __('Connection failed. Please check your settings.', 'mavlers-contact-forms'),
                'settings_saved' => __('Settings saved successfully!', 'mavlers-contact-forms'),
                'settings_error' => __('Failed to save settings. Please try again.', 'mavlers-contact-forms')
            ]
        ]);

        // Enqueue HubSpot form script
        wp_enqueue_script(
            'mavlers-cf-hubspot-form',
            $this->get_asset_url('assets/js/admin/hubspot-form.js'),
            array('jquery', 'mavlers-cf-integrations-admin'),
            '1.0.2',
            true
        );

        // Add HubSpot localized data
        wp_localize_script('mavlers-cf-hubspot-form', 'mavlersCFHubspot', [
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'strings' => [
                'connection_successful' => __('Connection successful!', 'mavlers-contact-forms'),
                'connection_failed' => __('Connection failed. Please check your settings.', 'mavlers-contact-forms'),
                'settings_saved' => __('Settings saved successfully!', 'mavlers-contact-forms'),
                'settings_error' => __('Failed to save settings. Please try again.', 'mavlers-contact-forms')
            ]
        ]);
    }
} 