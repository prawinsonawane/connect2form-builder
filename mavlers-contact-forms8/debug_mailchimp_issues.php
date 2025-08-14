<?php
/**
 * Debug Mailchimp Issues
 * 
 * Identifies specific issues with Mailchimp settings save and connection test
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
if (!isset($_GET['debug']) || $_GET['debug'] !== 'mailchimp_issues') {
    return;
}

echo '<div style="background: #fff3cd; padding: 20px; margin: 20px; border: 1px solid #ffeaa7;">';
echo '<h2>🔍 Debug Mailchimp Issues</h2>';

// Test 1: Check if Mailchimp integration is loaded
echo '<h3>Test 1: Integration Loading</h3>';

if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
    echo '<p style="color: green;">✓ Mailchimp integration class is loaded</p>';
    
    try {
        $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
        echo '<p style="color: green;">✓ Mailchimp integration instance created successfully</p>';
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error creating Mailchimp integration: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">✗ Mailchimp integration class not found</p>';
}

// Test 2: Check AJAX handlers
echo '<h3>Test 2: AJAX Handlers</h3>';

$mailchimp_ajax_actions = [
    'mailchimp_test_connection',
    'mailchimp_save_global_settings',
    'mailchimp_save_global_settings_v2',
    'mailchimp_test_save_debug'
];

foreach ($mailchimp_ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        echo '<p style="color: green;">✓ AJAX handler registered: ' . $action . '</p>';
    } else {
        echo '<p style="color: red;">✗ AJAX handler not registered: ' . $action . '</p>';
    }
}

// Test 3: Check current settings
echo '<h3>Test 3: Current Settings</h3>';

$global_settings = get_option('mavlers_cf_integrations_global', []);
$mailchimp_settings = $global_settings['mailchimp'] ?? [];

echo '<p>Global settings: ' . print_r($global_settings, true) . '</p>';
echo '<p>Mailchimp settings: ' . print_r($mailchimp_settings, true) . '</p>';

// Test 4: Test settings save manually
echo '<h3>Test 4: Manual Settings Save Test</h3>';

try {
    $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
    
    $test_settings = [
        'api_key' => 'test-api-key-' . time(),
        'enable_analytics' => 1,
        'enable_webhooks' => 0
    ];
    
    echo '<p>Testing with settings: ' . print_r($test_settings, true) . '</p>';
    
    // Test validation
    $validation_result = $mailchimp->validateSettings($test_settings);
    echo '<p>Validation result: ' . print_r($validation_result, true) . '</p>';
    
    // Test save
    $save_result = $mailchimp->saveGlobalSettings($test_settings);
    echo '<p>Save result: ' . ($save_result ? 'SUCCESS' : 'FAILED') . '</p>';
    
    // Verify save
    $verify_settings = get_option('mavlers_cf_integrations_global', []);
    $verify_mailchimp = $verify_settings['mailchimp'] ?? [];
    echo '<p>Verified settings: ' . print_r($verify_mailchimp, true) . '</p>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">✗ Error testing settings save: ' . $e->getMessage() . '</p>';
}

// Test 5: Test connection manually
echo '<h3>Test 5: Manual Connection Test</h3>';

try {
    $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
    
    $test_credentials = [
        'api_key' => 'test-api-key-' . time()
    ];
    
    echo '<p>Testing connection with credentials: ' . print_r($test_credentials, true) . '</p>';
    
    $connection_result = $mailchimp->testConnection($test_credentials);
    echo '<p>Connection result: ' . print_r($connection_result, true) . '</p>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">✗ Error testing connection: ' . $e->getMessage() . '</p>';
}

// Test 6: Check for PHP errors
echo '<h3>Test 6: PHP Error Check</h3>';

$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $recent_errors = file_get_contents($error_log);
    if (strpos($recent_errors, 'mailchimp') !== false) {
        echo '<p style="color: orange;">⚠ Recent Mailchimp errors found in log file</p>';
        echo '<pre style="background: #fff; padding: 10px; max-height: 200px; overflow-y: auto;">';
        echo htmlspecialchars(substr($recent_errors, -1000));
        echo '</pre>';
    } else {
        echo '<p style="color: green;">✓ No recent Mailchimp errors found</p>';
    }
} else {
    echo '<p style="color: blue;">ℹ No error log file found or configured</p>';
}

echo '</div>';

// Add JavaScript test functions
echo '<script>
console.log("=== MAILCHIMP ISSUES DEBUG ===");

// Test Mailchimp AJAX handlers
window.testMailchimpAjax = function() {
    console.log("Testing Mailchimp AJAX handlers...");
    
    // Test 1: Test connection
    jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
            action: "mailchimp_test_connection",
            nonce: "' . wp_create_nonce('mavlers_cf_nonce') . '",
            api_key: "test-api-key-' . time() . '"
        },
        success: function(response) {
            console.log("Mailchimp test connection success:", response);
            alert("✅ Mailchimp test connection successful!");
        },
        error: function(xhr, status, error) {
            console.error("Mailchimp test connection error:", error);
            console.error("Response:", xhr.responseText);
            alert("❌ Mailchimp test connection failed: " + error);
        }
    });
};

// Test 2: Save settings
window.testMailchimpSave = function() {
    console.log("Testing Mailchimp settings save...");
    
    jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
            action: "mailchimp_save_global_settings_v2",
            nonce: "' . wp_create_nonce('mavlers_cf_nonce') . '",
            settings: JSON.stringify({
                api_key: "test-api-key-' . time() . '",
                enable_analytics: 1,
                enable_webhooks: 0
            })
        },
        success: function(response) {
            console.log("Mailchimp settings save success:", response);
            alert("✅ Mailchimp settings save successful!");
        },
        error: function(xhr, status, error) {
            console.error("Mailchimp settings save error:", error);
            console.error("Response:", xhr.responseText);
            alert("❌ Mailchimp settings save failed: " + error);
        }
    });
};

// Test 3: Debug save
window.testMailchimpDebug = function() {
    console.log("Testing Mailchimp debug save...");
    
    jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
            action: "mailchimp_test_save_debug",
            nonce: "' . wp_create_nonce('mavlers_cf_nonce') . '",
            test_data: "test-value-' . time() . '"
        },
        success: function(response) {
            console.log("Mailchimp debug save success:", response);
            alert("✅ Mailchimp debug save successful!");
        },
        error: function(xhr, status, error) {
            console.error("Mailchimp debug save error:", error);
            console.error("Response:", xhr.responseText);
            alert("❌ Mailchimp debug save failed: " + error);
        }
    });
};

console.log("Available test functions:");
console.log("- testMailchimpAjax() - Test connection");
console.log("- testMailchimpSave() - Test settings save");
console.log("- testMailchimpDebug() - Test debug save");
</script>';

echo '<div style="background: #fff; padding: 15px; margin: 20px; border: 1px solid #ffeaa7;">';
echo '<h3>JavaScript Test Functions</h3>';
echo '<p>Open browser console and run these functions:</p>';
echo '<ul>';
echo '<li><code>testMailchimpAjax()</code> - Test connection</li>';
echo '<li><code>testMailchimpSave()</code> - Test settings save</li>';
echo '<li><code>testMailchimpDebug()</code> - Test debug save</li>';
echo '</ul>';
echo '</div>'; 