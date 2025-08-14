<?php
/**
 * Comprehensive Integration Test Script
 * 
 * Tests both Mailchimp and HubSpot integrations simultaneously
 * Run this by visiting: /wp-admin/admin.php?page=mavlers-cf-integrations&tab=settings&integration=mailchimp&debug=both
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
if (!isset($_GET['debug']) || $_GET['debug'] !== 'both') {
    return;
}

echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
echo '<h2>Comprehensive Integration Test - Mailchimp & HubSpot</h2>';

// Test 1: Check if both integrations are loaded
echo '<h3>Test 1: Integration Loading</h3>';

// Mailchimp Integration
if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
    echo '<p style="color: green;">✓ Mailchimp integration class is loaded</p>';
    
    try {
        $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
        echo '<p style="color: green;">✓ Mailchimp integration instance created successfully</p>';
        echo '<p>Mailchimp ID: ' . $mailchimp->getId() . '</p>';
        echo '<p>Mailchimp Name: ' . $mailchimp->getName() . '</p>';
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error creating Mailchimp integration: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">✗ Mailchimp integration class not found</p>';
}

// HubSpot Integration
if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    echo '<p style="color: green;">✓ HubSpot integration class is loaded</p>';
    
    try {
        $hubspot = new MavlersCF\Integrations\Hubspot\HubspotIntegration();
        echo '<p style="color: green;">✓ HubSpot integration instance created successfully</p>';
        echo '<p>HubSpot ID: ' . $hubspot->getId() . '</p>';
        echo '<p>HubSpot Name: ' . $hubspot->getName() . '</p>';
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error creating HubSpot integration: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">✗ HubSpot integration class not found</p>';
}

// Test 2: Check for AJAX handler conflicts
echo '<h3>Test 2: AJAX Handler Conflicts</h3>';

global $wp_filter;
$all_ajax_handlers = [];
foreach ($wp_filter as $hook => $callbacks) {
    if (strpos($hook, 'wp_ajax_') === 0) {
        $all_ajax_handlers[] = $hook;
    }
}

// Check for duplicate action names
$action_names = [];
$duplicates = [];
foreach ($all_ajax_handlers as $handler) {
    $action_name = str_replace('wp_ajax_', '', $handler);
    if (in_array($action_name, $action_names)) {
        $duplicates[] = $action_name;
    } else {
        $action_names[] = $action_name;
    }
}

if (empty($duplicates)) {
    echo '<p style="color: green;">✓ No AJAX handler conflicts detected</p>';
} else {
    echo '<p style="color: red;">✗ AJAX handler conflicts detected: ' . implode(', ', $duplicates) . '</p>';
}

// Test 3: Check specific integration AJAX handlers
echo '<h3>Test 3: Integration AJAX Handlers</h3>';

// Mailchimp AJAX actions
$mailchimp_ajax_actions = [
    'mailchimp_test_connection',
    'mailchimp_save_global_settings',
    'mailchimp_save_global_settings_v2',
    'mailchimp_get_audiences',
    'mailchimp_save_form_settings',
    'mailchimp_get_audience_merge_fields',
    'mailchimp_export_analytics'
];

echo '<h4>Mailchimp AJAX Handlers:</h4>';
foreach ($mailchimp_ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        echo '<p style="color: green;">✓ AJAX handler registered: ' . $action . '</p>';
    } else {
        echo '<p style="color: red;">✗ AJAX handler not registered: ' . $action . '</p>';
    }
}

// HubSpot AJAX actions
$hubspot_ajax_actions = [
    'hubspot_test_connection',
    'hubspot_save_global_settings',
    'hubspot_save_global_settings_v2',
    'hubspot_debug_test',
    'mavlers_cf_hubspot_get_contacts',
    'mavlers_cf_hubspot_get_companies',
    'mavlers_cf_hubspot_get_deals'
];

echo '<h4>HubSpot AJAX Handlers:</h4>';
foreach ($hubspot_ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        echo '<p style="color: green;">✓ AJAX handler registered: ' . $action . '</p>';
    } else {
        echo '<p style="color: red;">✗ AJAX handler not registered: ' . $action . '</p>';
    }
}

// Test 4: Check component loading
echo '<h3>Test 4: Component Loading</h3>';

// Mailchimp components
$mailchimp_components = [
    'CustomFieldsManager.php',
    'AnalyticsManager.php',
    'WebhookHandler.php',
    'BatchProcessor.php'
];

$mailchimp_component_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/Mailchimp/';

echo '<h4>Mailchimp Components:</h4>';
foreach ($mailchimp_components as $component) {
    $file_path = $mailchimp_component_dir . $component;
    if (file_exists($file_path)) {
        echo '<p style="color: green;">✓ Component file exists: ' . $component . '</p>';
    } else {
        echo '<p style="color: red;">✗ Component file missing: ' . $component . '</p>';
    }
}

// HubSpot components
$hubspot_components = [
    'CustomPropertiesManager.php',
    'WorkflowManager.php',
    'DealManager.php',
    'CompanyManager.php',
    'AnalyticsManager.php'
];

$hubspot_component_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/Hubspot/';

echo '<h4>HubSpot Components:</h4>';
foreach ($hubspot_components as $component) {
    $file_path = $hubspot_component_dir . $component;
    if (file_exists($file_path)) {
        echo '<p style="color: green;">✓ Component file exists: ' . $component . '</p>';
    } else {
        echo '<p style="color: red;">✗ Component file missing: ' . $component . '</p>';
    }
}

// Test 5: Check asset files
echo '<h3>Test 5: Asset Files</h3>';

// Mailchimp assets
$mailchimp_assets = [
    'mailchimp.js',
    'mailchimp.css',
    'mailchimp-form.js'
];

$mailchimp_assets_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'assets/js/admin/';

echo '<h4>Mailchimp Assets:</h4>';
foreach ($mailchimp_assets as $asset) {
    $file_path = $mailchimp_assets_dir . $asset;
    if (file_exists($file_path)) {
        echo '<p style="color: green;">✓ Asset file exists: ' . $asset . '</p>';
    } else {
        echo '<p style="color: red;">✗ Asset file missing: ' . $asset . '</p>';
    }
}

// HubSpot assets
$hubspot_assets = [
    'hubspot.js',
    'hubspot.css'
];

$hubspot_assets_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'assets/js/admin/';

echo '<h4>HubSpot Assets:</h4>';
foreach ($hubspot_assets as $asset) {
    $file_path = $hubspot_assets_dir . $asset;
    if (file_exists($file_path)) {
        echo '<p style="color: green;">✓ Asset file exists: ' . $asset . '</p>';
    } else {
        echo '<p style="color: red;">✗ Asset file missing: ' . $asset . '</p>';
    }
}

// Test 6: Check global settings
echo '<h3>Test 6: Global Settings</h3>';
$global_settings = get_option('mavlers_cf_integrations_global', []);
echo '<p>Global settings: ' . print_r($global_settings, true) . '</p>';

$mailchimp_settings = $global_settings['mailchimp'] ?? [];
$hubspot_settings = $global_settings['hubspot'] ?? [];

echo '<p>Mailchimp settings: ' . print_r($mailchimp_settings, true) . '</p>';
echo '<p>HubSpot settings: ' . print_r($hubspot_settings, true) . '</p>';

// Test 7: Check for old system conflicts
echo '<h3>Test 7: Old System Conflicts</h3>';

$old_system_path = MAVLERS_CF_PLUGIN_DIR . 'old addons logic_disabled/';
if (is_dir($old_system_path)) {
    echo '<p style="color: green;">✓ Old system has been disabled (renamed to old addons logic_disabled)</p>';
} else {
    echo '<p style="color: orange;">⚠ Old system directory not found (may have been removed)</p>';
}

// Test 8: Check for any PHP errors
echo '<h3>Test 8: PHP Error Check</h3>';

$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $recent_errors = file_get_contents($error_log);
    if (strpos($recent_errors, 'mailchimp') !== false || strpos($recent_errors, 'hubspot') !== false) {
        echo '<p style="color: orange;">⚠ Recent errors found in log file</p>';
        echo '<pre style="background: #fff; padding: 10px; max-height: 200px; overflow-y: auto;">';
        echo htmlspecialchars(substr($recent_errors, -1000));
        echo '</pre>';
    } else {
        echo '<p style="color: green;">✓ No recent integration errors found</p>';
    }
} else {
    echo '<p style="color: blue;">ℹ No error log file found or configured</p>';
}

echo '</div>';

// Add JavaScript test functions
echo '<script>
console.log("=== INTEGRATION TEST SCRIPT LOADED ===");

// Test Mailchimp AJAX
window.testMailchimpAjax = function() {
    console.log("Testing Mailchimp AJAX...");
    jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
            action: "mailchimp_test_ajax",
            nonce: "' . wp_create_nonce('mavlers_cf_nonce') . '"
        },
        success: function(response) {
            console.log("Mailchimp AJAX success:", response);
            alert("Mailchimp AJAX test successful!");
        },
        error: function(xhr, status, error) {
            console.error("Mailchimp AJAX error:", error);
            alert("Mailchimp AJAX test failed: " + error);
        }
    });
};

// Test HubSpot AJAX
window.testHubspotAjax = function() {
    console.log("Testing HubSpot AJAX...");
    jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
            action: "hubspot_debug_test",
            nonce: "' . wp_create_nonce('mavlers_cf_nonce') . '"
        },
        success: function(response) {
            console.log("HubSpot AJAX success:", response);
            alert("HubSpot AJAX test successful!");
        },
        error: function(xhr, status, error) {
            console.error("HubSpot AJAX error:", error);
            alert("HubSpot AJAX test failed: " + error);
        }
    });
};

// Test both integrations simultaneously
window.testBothIntegrations = function() {
    console.log("Testing both integrations...");
    testMailchimpAjax();
    setTimeout(testHubspotAjax, 1000);
};

// Test integration settings save
window.testIntegrationSettings = function() {
    console.log("Testing integration settings save...");
    
    // Test Mailchimp settings save
    jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
            action: "mailchimp_save_global_settings_v2",
            nonce: "' . wp_create_nonce('mavlers_cf_nonce') . '",
            settings: JSON.stringify({
                api_key: "test-mailchimp-key-' . time() . '",
                enable_analytics: 1,
                enable_webhooks: 0
            })
        },
        success: function(response) {
            console.log("Mailchimp settings save success:", response);
            
            // Test HubSpot settings save
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "hubspot_save_global_settings_v2",
                    nonce: "' . wp_create_nonce('mavlers_cf_nonce') . '",
                    settings: JSON.stringify({
                        access_token: "test-hubspot-token-' . time() . '",
                        portal_id: "12345",
                        enable_analytics: 1
                    })
                },
                success: function(response) {
                    console.log("HubSpot settings save success:", response);
                    alert("Both integration settings saved successfully!");
                },
                error: function(xhr, status, error) {
                    console.error("HubSpot settings save error:", error);
                    alert("HubSpot settings save failed: " + error);
                }
            });
        },
        error: function(xhr, status, error) {
            console.error("Mailchimp settings save error:", error);
            alert("Mailchimp settings save failed: " + error);
        }
    });
};

console.log("Available test functions:");
console.log("- testMailchimpAjax() - Test Mailchimp AJAX");
console.log("- testHubspotAjax() - Test HubSpot AJAX");
console.log("- testBothIntegrations() - Test both integrations");
console.log("- testIntegrationSettings() - Test settings save for both");
</script>';

echo '<div style="background: #fff; padding: 15px; margin: 20px; border: 1px solid #ccc;">';
echo '<h3>JavaScript Test Functions</h3>';
echo '<p>Open browser console and run these functions:</p>';
echo '<ul>';
echo '<li><code>testMailchimpAjax()</code> - Test Mailchimp AJAX</li>';
echo '<li><code>testHubspotAjax()</code> - Test HubSpot AJAX</li>';
echo '<li><code>testBothIntegrations()</code> - Test both integrations</li>';
echo '<li><code>testIntegrationSettings()</code> - Test settings save for both integrations</li>';
echo '</ul>';
echo '</div>'; 