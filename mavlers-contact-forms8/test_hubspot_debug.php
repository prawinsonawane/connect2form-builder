<?php
/**
 * HubSpot Integration Debug Test Script
 * 
 * This script tests the HubSpot integration functionality
 * Run this by visiting: /wp-admin/admin.php?page=mavlers-cf-integrations&tab=settings&integration=hubspot&debug=test
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only run for admin users
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Check if debug parameter is present
if (!isset($_GET['debug']) || $_GET['debug'] !== 'test') {
    return;
}

echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
echo '<h2>HubSpot Integration Debug Test</h2>';

// Test 1: Check if HubSpot integration is loaded
echo '<h3>Test 1: Integration Loading</h3>';
if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    echo '<p style="color: green;">✓ HubSpot integration class is loaded</p>';
    
    try {
        $hubspot = new MavlersCF\Integrations\Hubspot\HubspotIntegration();
        echo '<p style="color: green;">✓ HubSpot integration instance created successfully</p>';
        echo '<p>Integration ID: ' . $hubspot->getId() . '</p>';
        echo '<p>Integration Name: ' . $hubspot->getName() . '</p>';
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error creating HubSpot integration: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">✗ HubSpot integration class not found</p>';
}

// Test 2: Check global settings
echo '<h3>Test 2: Global Settings</h3>';
$global_settings = get_option('mavlers_cf_integrations_global', []);
echo '<p>Global settings: ' . print_r($global_settings, true) . '</p>';

$hubspot_settings = $global_settings['hubspot'] ?? [];
echo '<p>HubSpot settings: ' . print_r($hubspot_settings, true) . '</p>';

// Test 3: Test settings save
echo '<h3>Test 3: Settings Save Test</h3>';
if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    try {
        $hubspot = new MavlersCF\Integrations\Hubspot\HubspotIntegration();
        
        $test_settings = [
            'access_token' => 'test-token-' . time(),
            'portal_id' => '12345',
            'enable_analytics' => 1,
            'enable_webhooks' => 0,
            'batch_processing' => 1
        ];
        
        $save_result = $hubspot->saveGlobalSettings($test_settings);
        echo '<p>Save result: ' . ($save_result ? 'SUCCESS' : 'FAILED') . '</p>';
        
        // Verify the save
        $verify_settings = get_option('mavlers_cf_integrations_global', []);
        $verify_hubspot = $verify_settings['hubspot'] ?? [];
        echo '<p>Verification - saved settings: ' . print_r($verify_hubspot, true) . '</p>';
        
        if (!empty($verify_hubspot)) {
            echo '<p style="color: green;">✓ Settings saved successfully</p>';
        } else {
            echo '<p style="color: red;">✗ Settings not saved</p>';
        }
        
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error testing settings save: ' . $e->getMessage() . '</p>';
    }
}

// Test 4: Check AJAX handlers
echo '<h3>Test 4: AJAX Handlers</h3>';
$ajax_actions = [
    'hubspot_save_global_settings',
    'hubspot_save_global_settings_v2',
    'hubspot_test_connection',
    'hubspot_debug_test'
];

foreach ($ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        echo '<p style="color: green;">✓ AJAX handler registered: ' . $action . '</p>';
    } else {
        echo '<p style="color: red;">✗ AJAX handler not registered: ' . $action . '</p>';
    }
}

// Test 5: Test connection functionality
echo '<h3>Test 5: Connection Test</h3>';
if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    try {
        $hubspot = new MavlersCF\Integrations\Hubspot\HubspotIntegration();
        
        // Test with dummy credentials
        $test_credentials = [
            'access_token' => 'pat-test-token',
            'portal_id' => '12345'
        ];
        
        $connection_result = $hubspot->testConnection($test_credentials);
        echo '<p>Connection test result: ' . print_r($connection_result, true) . '</p>';
        
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error testing connection: ' . $e->getMessage() . '</p>';
    }
}

// Test 6: Check if components are loaded
echo '<h3>Test 6: Component Loading</h3>';
$component_files = [
    'CustomPropertiesManager.php',
    'WorkflowsManager.php',
    'DealsManager.php',
    'CompaniesManager.php',
    'AnalyticsManager.php'
];

$component_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/Hubspot/';

foreach ($component_files as $file) {
    $file_path = $component_dir . $file;
    if (file_exists($file_path)) {
        echo '<p style="color: green;">✓ Component file exists: ' . $file . '</p>';
    } else {
        echo '<p style="color: red;">✗ Component file missing: ' . $file . '</p>';
    }
}

// Test 7: Check template files
echo '<h3>Test 7: Template Files</h3>';
$template_files = [
    'hubspot-form-settings.php'
];

$template_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/Hubspot/templates/';

foreach ($template_files as $file) {
    $file_path = $template_dir . $file;
    if (file_exists($file_path)) {
        echo '<p style="color: green;">✓ Template file exists: ' . $file . '</p>';
    } else {
        echo '<p style="color: red;">✗ Template file missing: ' . $file . '</p>';
    }
}

echo '</div>'; 