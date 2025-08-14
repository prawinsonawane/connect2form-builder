<?php
/**
 * Addon Name: Integrations Manager
 * Description: Core integration system for Mavlers Contact Forms
 * Version: 1.0.0
 * Author: Mavlers
 * Requires: Mavlers Contact Forms 1.0.0+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define addon constants
define('MAVLERS_CF_INTEGRATIONS_VERSION', '1.0.0');
define('MAVLERS_CF_INTEGRATIONS_DIR', plugin_dir_path(__FILE__));
define('MAVLERS_CF_INTEGRATIONS_URL', plugin_dir_url(__FILE__));

// Check if main plugin is active
if (!class_exists('Mavlers_CF_Admin')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __('Integrations Manager requires Mavlers Contact Forms plugin to be active.', 'mavlers-contact-forms');
        echo '</p></div>';
    });
    return;
}

// Include core files
require_once MAVLERS_CF_INTEGRATIONS_DIR . 'includes/class-integrations-core.php';
require_once MAVLERS_CF_INTEGRATIONS_DIR . 'includes/class-addon-registry.php';
require_once MAVLERS_CF_INTEGRATIONS_DIR . 'includes/class-base-integration.php';
require_once MAVLERS_CF_INTEGRATIONS_DIR . 'includes/class-field-mapper.php';
require_once MAVLERS_CF_INTEGRATIONS_DIR . 'includes/class-api-client.php';
require_once MAVLERS_CF_INTEGRATIONS_DIR . 'includes/class-security-manager.php';
require_once MAVLERS_CF_INTEGRATIONS_DIR . 'includes/class-integration-logger.php';

// Load integration auto-loader
require_once MAVLERS_CF_INTEGRATIONS_DIR . 'integrations/integrations-loader.php';

// Initialize the integration system
function mavlers_cf_integrations_init() {
    // Initialize core integration system
    $integrations_core = new Mavlers_CF_Integrations_Core();
    
    // Load individual integrations
    do_action('mavlers_cf_load_integrations');
}
add_action('plugins_loaded', 'mavlers_cf_integrations_init', 20);

// Activation hook for addon
register_activation_hook(__FILE__, array('Mavlers_CF_Integrations_Core', 'activate'));

// Helper function to check if integrations are active
function mavlers_cf_integrations_active() {
    return class_exists('Mavlers_CF_Integrations_Core');
}

// Auto-load integration addons
function mavlers_cf_load_integration_addons() {
    $integrations_dir = MAVLERS_CF_INTEGRATIONS_DIR . '../integrations/';
    
    if (!is_dir($integrations_dir)) {
        return;
    }
    
    $integration_folders = scandir($integrations_dir);
    
    foreach ($integration_folders as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }
        
        $integration_file = $integrations_dir . $folder . '/' . $folder . '.php';
        if (file_exists($integration_file)) {
            include_once $integration_file;
        }
    }
}
add_action('mavlers_cf_load_integrations', 'mavlers_cf_load_integration_addons'); 