<?php
/**
 * Test script to verify HubSpot integration fixes
 */

// Ensure we're in WordPress admin
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Check if we're in admin
if (!is_admin()) {
    wp_die('This script must be run in the WordPress admin area');
}

echo "<h1>HubSpot Integration Fix Test</h1>";

// Test 1: Check if HubSpot integration is loaded
echo "<h2>1. HubSpot Integration Loading Test</h2>";

try {
    $plugin = \MavlersCF\Integrations\Core\Plugin::getInstance();
    $registry = $plugin->getRegistry();
    $hubspot = $registry->get('hubspot');
    
    if ($hubspot) {
        echo "<p>✅ HubSpot integration found</p>";
        echo "<p>- ID: {$hubspot->getId()}</p>";
        echo "<p>- Name: {$hubspot->getName()}</p>";
        echo "<p>- Version: {$hubspot->getVersion()}</p>";
        echo "<p>- Configured: " . ($hubspot->isConfigured() ? 'Yes' : 'No') . "</p>";
    } else {
        echo "<p>❌ HubSpot integration not found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading HubSpot integration: " . $e->getMessage() . "</p>";
}

// Test 2: Check if AJAX handlers are registered
echo "<h2>2. AJAX Handler Registration Test</h2>";

$ajax_handlers = [
    'mavlers_cf_hubspot_test_response',
    'mavlers_cf_hubspot_test_connection',
    'hubspot_save_global_settings_v2'
];

foreach ($ajax_handlers as $handler) {
    $has_action = has_action("wp_ajax_{$handler}");
    echo "<p>{$handler}: " . ($has_action ? '✅ Registered' : '❌ Not registered') . "</p>";
}

// Test 3: Check if assets are being enqueued
echo "<h2>3. Asset Enqueuing Test</h2>";

// Simulate the enqueue process
$hook = 'admin.php';
$screen = get_current_screen();

echo "<p>Hook: {$hook}</p>";
echo "<p>Screen ID: " . ($screen ? $screen->id : 'no screen') . "</p>";

// Check if the script file exists
$script_file = MAVLERS_CF_INTEGRATIONS_DIR . 'assets/js/admin/hubspot-form.js';
$css_file = MAVLERS_CF_INTEGRATIONS_DIR . 'assets/css/admin/hubspot.css';

echo "<p>Script file exists: " . (file_exists($script_file) ? '✅ Yes' : '❌ No') . "</p>";
echo "<p>CSS file exists: " . (file_exists($css_file) ? '✅ Yes' : '❌ No') . "</p>";

// Test 4: Check if the old file is gone
$old_script_file = MAVLERS_CF_INTEGRATIONS_DIR . 'assets/js/admin/hubspot.js';
echo "<p>Old script file removed: " . (!file_exists($old_script_file) ? '✅ Yes' : '❌ No') . "</p>";

// Test 5: Check integration settings
echo "<h2>4. Integration Settings Test</h2>";

$global_settings = get_option('mavlers_cf_integrations_global', []);
$hubspot_settings = $global_settings['hubspot'] ?? [];

echo "<p>Global settings exist: " . (!empty($global_settings) ? '✅ Yes' : '❌ No') . "</p>";
echo "<p>HubSpot settings exist: " . (!empty($hubspot_settings) ? '✅ Yes' : '❌ No') . "</p>";

if (!empty($hubspot_settings)) {
    echo "<p>HubSpot settings keys: " . implode(', ', array_keys($hubspot_settings)) . "</p>";
}

// Test 6: Check if the integration is properly initialized
echo "<h2>5. Integration Initialization Test</h2>";

if ($hubspot) {
    try {
        // Test if the integration has the required methods
        $methods = ['getId', 'getName', 'getDescription', 'getVersion', 'isConfigured'];
        foreach ($methods as $method) {
            if (method_exists($hubspot, $method)) {
                echo "<p>✅ Method {$method} exists</p>";
            } else {
                echo "<p>❌ Method {$method} missing</p>";
            }
        }
        
        // Test if AJAX handlers are callable
        $ajax_methods = ['ajax_test_response', 'ajax_test_connection', 'ajax_save_global_settings_v2'];
        foreach ($ajax_methods as $method) {
            if (method_exists($hubspot, $method)) {
                echo "<p>✅ AJAX method {$method} exists</p>";
            } else {
                echo "<p>❌ AJAX method {$method} missing</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error testing integration methods: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>Test Complete</h2>";
echo "<p>If all tests pass, the HubSpot integration should be working properly.</p>";
echo "<p>Next steps:</p>";
echo "<ol>";
echo "<li>Go to a form edit page</li>";
echo "<li>Navigate to HubSpot integration settings</li>";
echo "<li>Check browser console for debug messages</li>";
echo "<li>Try the 'Test AJAX Endpoint' button</li>";
echo "<li>Try saving global settings</li>";
echo "</ol>";
?> 