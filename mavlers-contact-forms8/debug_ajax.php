<?php
/**
 * Debug script to check AJAX handler registration and test endpoints
 */

// Load WordPress
require_once('../../../wp-load.php');

echo "<h1>HubSpot AJAX Debug Script</h1>\n";

// Check if we're in admin
if (!is_admin()) {
    echo "<p style='color: red;'>This script must be run from admin context</p>\n";
    exit;
}

// Check if user is logged in and has permissions
if (!current_user_can('manage_options')) {
    echo "<p style='color: red;'>Insufficient permissions</p>\n";
    exit;
}

echo "<h2>1. Checking Integration Loading</h2>\n";

// Check if the integration class exists
if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    echo "<p style='color: green;'>✓ HubSpot integration class exists</p>\n";
} else {
    echo "<p style='color: red;'>✗ HubSpot integration class not found</p>\n";
    exit;
}

// Check if the integration is registered
$integrations = apply_filters('mavlers_cf_integrations', []);
echo "<p>Registered integrations: " . implode(', ', array_keys($integrations)) . "</p>\n";

if (isset($integrations['hubspot'])) {
    echo "<p style='color: green;'>✓ HubSpot integration is registered</p>\n";
} else {
    echo "<p style='color: red;'>✗ HubSpot integration not found in registry</p>\n";
}

echo "<h2>2. Checking AJAX Handler Registration</h2>\n";

// Get all registered AJAX actions
global $wp_filter;
$ajax_actions = [];

if (isset($wp_filter['wp_ajax_hubspot_save_global_settings_v2'])) {
    echo "<p style='color: green;'>✓ AJAX handler 'hubspot_save_global_settings_v2' is registered</p>\n";
    $ajax_actions[] = 'hubspot_save_global_settings_v2';
} else {
    echo "<p style='color: red;'>✗ AJAX handler 'hubspot_save_global_settings_v2' is NOT registered</p>\n";
}

if (isset($wp_filter['wp_ajax_hubspot_test_simple_v2'])) {
    echo "<p style='color: green;'>✓ AJAX handler 'hubspot_test_simple_v2' is registered</p>\n";
    $ajax_actions[] = 'hubspot_test_simple_v2';
} else {
    echo "<p style='color: red;'>✗ AJAX handler 'hubspot_test_simple_v2' is NOT registered</p>\n";
}

if (isset($wp_filter['wp_ajax_mavlers_cf_hubspot_test_response'])) {
    echo "<p style='color: green;'>✓ AJAX handler 'mavlers_cf_hubspot_test_response' is registered</p>\n";
    $ajax_actions[] = 'mavlers_cf_hubspot_test_response';
} else {
    echo "<p style='color: red;'>✗ AJAX handler 'mavlers_cf_hubspot_test_response' is NOT registered</p>\n";
}

echo "<h2>3. Testing AJAX Endpoints</h2>\n";

// Test simple endpoint
echo "<h3>Testing Simple Endpoint</h3>\n";
$_POST = [
    'action' => 'hubspot_test_simple_v2',
    'nonce' => wp_create_nonce('mavlers_cf_nonce')
];

// Capture output
ob_start();
do_action('wp_ajax_hubspot_test_simple_v2');
$output = ob_get_clean();

echo "<p>Output: " . htmlspecialchars($output) . "</p>\n";

// Test save endpoint
echo "<h3>Testing Save Endpoint</h3>\n";
$_POST = [
    'action' => 'hubspot_save_global_settings_v2',
    'nonce' => wp_create_nonce('mavlers_cf_nonce'),
    'access_token' => 'test-token',
    'portal_id' => 'test-portal',
    'enable_analytics' => '1',
    'enable_webhooks' => '0',
    'batch_processing' => '1'
];

ob_start();
do_action('wp_ajax_hubspot_save_global_settings_v2');
$output = ob_get_clean();

echo "<p>Output: " . htmlspecialchars($output) . "</p>\n";

echo "<h2>4. Manual AJAX Handler Test</h2>\n";

// Try to call the handler directly
if (isset($integrations['hubspot'])) {
    $integration = $integrations['hubspot'];
    
    if (method_exists($integration, 'ajax_test_simple_v2')) {
        echo "<p style='color: green;'>✓ Method ajax_test_simple_v2 exists</p>\n";
        
        // Test the method directly
        $_POST = [
            'action' => 'hubspot_test_simple_v2',
            'nonce' => wp_create_nonce('mavlers_cf_nonce')
        ];
        
        ob_start();
        $integration->ajax_test_simple_v2();
        $output = ob_get_clean();
        
        echo "<p>Direct method call output: " . htmlspecialchars($output) . "</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Method ajax_test_simple_v2 does not exist</p>\n";
    }
    
    if (method_exists($integration, 'ajax_save_global_settings_v2')) {
        echo "<p style='color: green;'>✓ Method ajax_save_global_settings_v2 exists</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Method ajax_save_global_settings_v2 does not exist</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ HubSpot integration not available for testing</p>\n";
}

echo "<h2>5. WordPress AJAX Debug</h2>\n";

// Check if WordPress AJAX is working
echo "<p>AJAX URL: " . admin_url('admin-ajax.php') . "</p>\n";
echo "<p>Nonce: " . wp_create_nonce('mavlers_cf_nonce') . "</p>\n";

// List all registered AJAX actions
echo "<h3>All Registered AJAX Actions:</h3>\n";
echo "<ul>\n";
foreach ($wp_filter as $hook => $callbacks) {
    if (strpos($hook, 'wp_ajax_') === 0) {
        echo "<li>" . htmlspecialchars($hook) . "</li>\n";
    }
}
echo "</ul>\n";

echo "<h2>6. JavaScript Test</h2>\n";
echo "<p>Add this to browser console to test:</p>\n";
echo "<pre>\n";
echo "jQuery.ajax({\n";
echo "    url: ajaxurl,\n";
echo "    type: 'POST',\n";
echo "    data: {\n";
echo "        action: 'hubspot_test_simple_v2',\n";
echo "        nonce: '" . wp_create_nonce('mavlers_cf_nonce') . "'\n";
echo "    },\n";
echo "    success: function(response) {\n";
echo "        console.log('Success:', response);\n";
echo "    },\n";
echo "    error: function(xhr, status, error) {\n";
echo "        console.log('Error:', error);\n";
echo "        console.log('Status:', xhr.status);\n";
echo "        console.log('Response:', xhr.responseText);\n";
echo "    }\n";
echo "});\n";
echo "</pre>\n";

echo "<h2>Debug Complete</h2>\n";
echo "<p>Check the output above to identify the issue with AJAX handler registration.</p>\n";
?> 