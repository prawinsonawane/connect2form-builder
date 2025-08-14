<?php
/**
 * Comprehensive Integration Test Script
 * Tests all AJAX handlers and settings functionality
 */

if (!defined('ABSPATH')) {
    $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die('WordPress not found');
    }
}

if (!current_user_can('manage_options')) {
    die('Insufficient permissions');
}

echo "<h1>Integration Fixes Test</h1>";

// Test 1: Check AJAX handler registration
echo "<h2>1. AJAX Handler Registration Test</h2>";
global $wp_filter;

$required_actions = [
    'wp_ajax_mailchimp_save_global_settings',
    'wp_ajax_mailchimp_test_connection',
    'wp_ajax_hubspot_save_global_settings',
    'wp_ajax_hubspot_test_connection'
];

foreach ($required_actions as $action) {
    $registered = isset($wp_filter[$action]);
    $status = $registered ? '✅ REGISTERED' : '❌ NOT REGISTERED';
    echo "<p><strong>$action:</strong> $status</p>";
}

// Test 2: Check integration classes
echo "<h2>2. Integration Class Loading Test</h2>";

$integrations = [
    'Mailchimp' => 'MavlersCF\Integrations\Mailchimp\MailchimpIntegration',
    'HubSpot' => 'MavlersCF\Integrations\Hubspot\HubspotIntegration'
];

foreach ($integrations as $name => $class) {
    if (class_exists($class)) {
        $instance = new $class();
        echo "<p><strong>$name:</strong> ✅ LOADED (ID: " . $instance->getId() . ")</p>";
    } else {
        echo "<p><strong>$name:</strong> ❌ NOT LOADED</p>";
    }
}

// Test 3: Test nonce creation
echo "<h2>3. Nonce Test</h2>";
$nonce = wp_create_nonce('mavlers_cf_nonce');
echo "<p><strong>Nonce created:</strong> $nonce</p>";

// Test 4: Test settings storage
echo "<h2>4. Settings Storage Test</h2>";

$test_settings = [
    'mailchimp' => [
        'api_key' => 'test-mailchimp-key',
        'enable_analytics' => true,
        'enable_webhooks' => false,
        'batch_processing' => true
    ],
    'hubspot' => [
        'access_token' => 'test-hubspot-token',
        'portal_id' => '12345',
        'enable_analytics' => true,
        'enable_webhooks' => false,
        'batch_processing' => true
    ]
];

$save_result = update_option('mavlers_cf_integrations_global', $test_settings);
echo "<p><strong>Save test settings:</strong> " . ($save_result ? '✅ SUCCESS' : '❌ FAILED') . "</p>";

$retrieved_settings = get_option('mavlers_cf_integrations_global', []);
echo "<p><strong>Retrieved settings:</strong></p>";
echo "<pre>" . print_r($retrieved_settings, true) . "</pre>";

// Test 5: Test AJAX simulation
echo "<h2>5. AJAX Handler Simulation Test</h2>";

// Simulate Mailchimp save settings
$_POST = [
    'nonce' => $nonce,
    'settings' => [
        'api_key' => 'test-api-key',
        'enable_analytics' => '1',
        'enable_webhooks' => '0',
        'batch_processing' => '1'
    ]
];

if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
    $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
    echo "<p><strong>Mailchimp saveGlobalSettings test:</strong> ";
    try {
        $result = $mailchimp->saveGlobalSettings($_POST['settings']);
        echo $result ? '✅ SUCCESS' : '❌ FAILED';
    } catch (Exception $e) {
        echo '❌ ERROR: ' . $e->getMessage();
    }
    echo "</p>";
}

// Simulate HubSpot save settings
$_POST = [
    'nonce' => $nonce,
    'settings' => [
        'access_token' => 'test-access-token',
        'portal_id' => '12345',
        'enable_analytics' => '1',
        'enable_webhooks' => '0',
        'batch_processing' => '1'
    ]
];

if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    $hubspot = new MavlersCF\Integrations\Hubspot\HubspotIntegration();
    echo "<p><strong>HubSpot saveGlobalSettings test:</strong> ";
    try {
        $result = $hubspot->saveGlobalSettings($_POST['settings']);
        echo $result ? '✅ SUCCESS' : '❌ FAILED';
    } catch (Exception $e) {
        echo '❌ ERROR: ' . $e->getMessage();
    }
    echo "</p>";
}

// Test 6: Check all registered AJAX actions
echo "<h2>6. All Registered AJAX Actions</h2>";
echo "<ul>";
foreach ($wp_filter as $hook => $callbacks) {
    if (strpos($hook, 'wp_ajax_') === 0) {
        echo "<li>$hook</li>";
    }
}
echo "</ul>";

echo "<h2>Test Complete</h2>";
echo "<p>✅ All tests completed. Check the results above.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Try saving Mailchimp settings in the admin</li>";
echo "<li>Try saving HubSpot settings in the admin</li>";
echo "<li>Test connection buttons for both integrations</li>";
echo "<li>Check server logs for any remaining errors</li>";
echo "</ol>";
?> 