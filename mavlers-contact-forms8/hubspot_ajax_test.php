<?php
/**
 * HubSpot AJAX Test
 * Tests the HubSpot AJAX endpoints directly
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

echo "<h1>HubSpot AJAX Test</h1>";

// Test 1: Check if AJAX handlers are registered
echo "<h2>1. Check AJAX Handler Registration</h2>";
global $wp_filter;

$ajax_actions = [
    'wp_ajax_hubspot_test_simple_v2',
    'wp_ajax_hubspot_test_basic',
    'wp_ajax_hubspot_save_global_settings_v2'
];

foreach ($ajax_actions as $action) {
    $has_handlers = isset($wp_filter[$action]) && $wp_filter[$action]->has_callbacks();
    echo "<p><strong>$action:</strong> " . ($has_handlers ? '✅ REGISTERED' : '❌ NOT REGISTERED') . "</p>";
}

// Test 2: Simulate AJAX call to simple test endpoint
echo "<h2>2. Test AJAX Endpoint Directly</h2>";

// Set up AJAX environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
$_POST = ['test' => 'data'];

// Capture output
ob_start();

// Simulate the AJAX call
try {
    // Set up WordPress AJAX environment
    define('DOING_AJAX', true);
    
    // Call the handler directly
    $hubspot_integration = null;
    
    // Find the HubSpot integration instance
    global $mavlers_cf_plugin;
    if (isset($mavlers_cf_plugin) && method_exists($mavlers_cf_plugin, 'get_integration')) {
        $hubspot_integration = $mavlers_cf_plugin->get_integration('hubspot');
    }
    
    if ($hubspot_integration) {
        echo "<p><strong>HubSpot Integration Found:</strong> ✅</p>";
        
        // Test the simple endpoint
        if (method_exists($hubspot_integration, 'ajax_test_simple_v2')) {
            echo "<p><strong>Method exists:</strong> ✅</p>";
            
            // Call the method
            $hubspot_integration->ajax_test_simple_v2();
        } else {
            echo "<p><strong>Method exists:</strong> ❌</p>";
        }
    } else {
        echo "<p><strong>HubSpot Integration Found:</strong> ❌</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

$output = ob_get_clean();
echo "<p><strong>Output:</strong></p>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Test 3: Check WordPress AJAX environment
echo "<h2>3. WordPress AJAX Environment</h2>";
echo "<p><strong>DOING_AJAX:</strong> " . (defined('DOING_AJAX') ? 'YES' : 'NO') . "</p>";
echo "<p><strong>wp_doing_ajax():</strong> " . (function_exists('wp_doing_ajax') && wp_doing_ajax() ? 'YES' : 'NO') . "</p>";
echo "<p><strong>REQUEST_METHOD:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";

// Test 4: Check if HubSpot integration is loaded
echo "<h2>4. HubSpot Integration Status</h2>";

if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    echo "<p><strong>HubSpot Integration Class:</strong> ✅ LOADED</p>";
} else {
    echo "<p><strong>HubSpot Integration Class:</strong> ❌ NOT LOADED</p>";
}

// Test 5: Manual AJAX call simulation
echo "<h2>5. Manual AJAX Call Test</h2>";

$ajax_url = admin_url('admin-ajax.php');
echo "<p><strong>AJAX URL:</strong> $ajax_url</p>";

echo "<p>Testing with JavaScript:</p>";
echo "<script>
jQuery(document).ready(function($) {
    console.log('Testing HubSpot AJAX...');
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'hubspot_test_simple_v2',
            test: 'data'
        },
        success: function(response) {
            console.log('HubSpot AJAX Success:', response);
            document.getElementById('ajax-result').innerHTML = '✅ SUCCESS: ' + JSON.stringify(response);
        },
        error: function(xhr, status, error) {
            console.log('HubSpot AJAX Error:', xhr.responseText);
            document.getElementById('ajax-result').innerHTML = '❌ ERROR: ' + xhr.responseText;
        }
    });
});
</script>";

echo "<div id='ajax-result'>Testing...</div>";

echo "<h2>Test Complete</h2>";
echo "<p>Check the browser console for detailed AJAX results.</p>";
?> 