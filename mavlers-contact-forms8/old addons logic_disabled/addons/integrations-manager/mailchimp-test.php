<?php
/**
 * Mailchimp Integration Test Script
 *
 * Use this to test if your Mailchimp integration is working correctly
 * 
 * To run this test:
 * 1. Configure your Mailchimp API key in the global settings
 * 2. Access this file via: yoursite.com/wp-content/plugins/mavlers-contact-forms/addons/integrations-manager/mailchimp-test.php
 * 3. Or run via WP-CLI: wp eval-file mailchimp-test.php
 */

// WordPress environment check
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        __DIR__ . '/../../../../wp-load.php',
        __DIR__ . '/../../../../../wp-load.php',
        __DIR__ . '/../../../../../../wp-load.php'
    ];
    
    foreach ($wp_load_paths as $wp_load) {
        if (file_exists($wp_load)) {
            require_once $wp_load;
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die('WordPress not found. Please run this from WordPress admin or adjust the path.');
    }
}

// Check if user has admin permissions
if (!current_user_can('manage_options') && !defined('WP_CLI')) {
    wp_die('You need admin permissions to run this test.');
}

/**
 * Test Results Class
 */
class MailchimpTestResults {
    private $tests = [];
    private $passed = 0;
    private $failed = 0;
    
    public function addTest($name, $passed, $message = '', $data = null) {
        $this->tests[] = [
            'name' => $name,
            'passed' => $passed,
            'message' => $message,
            'data' => $data
        ];
        
        if ($passed) {
            $this->passed++;
        } else {
            $this->failed++;
        }
    }
    
    public function display() {
        $total = $this->passed + $this->failed;
        
        echo "<div style='max-width: 800px; margin: 20px auto; font-family: Arial, sans-serif;'>";
        echo "<h1>🧪 Mailchimp Integration Test Results</h1>";
        
        // Summary
        echo "<div style='padding: 15px; background: " . ($this->failed === 0 ? '#d4edda' : '#f8d7da') . "; border-radius: 8px; margin-bottom: 20px;'>";
        echo "<h3>Summary: {$this->passed}/{$total} tests passed</h3>";
        if ($this->failed === 0) {
            echo "<p style='color: #155724; margin: 0;'>✅ All tests passed! Your Mailchimp integration is working correctly.</p>";
        } else {
            echo "<p style='color: #721c24; margin: 0;'>❌ {$this->failed} test(s) failed. Please check the issues below.</p>";
        }
        echo "</div>";
        
        // Individual test results
        foreach ($this->tests as $test) {
            $bgColor = $test['passed'] ? '#d4edda' : '#f8d7da';
            $textColor = $test['passed'] ? '#155724' : '#721c24';
            $icon = $test['passed'] ? '✅' : '❌';
            
            echo "<div style='padding: 12px; background: {$bgColor}; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid " . ($test['passed'] ? '#28a745' : '#dc3545') . ";'>";
            echo "<h4 style='margin: 0 0 8px 0; color: {$textColor};'>{$icon} {$test['name']}</h4>";
            
            if ($test['message']) {
                echo "<p style='margin: 0; color: {$textColor};'>{$test['message']}</p>";
            }
            
            if ($test['data'] && is_array($test['data'])) {
                echo "<details style='margin-top: 8px;'>";
                echo "<summary style='cursor: pointer; color: {$textColor};'>View Details</summary>";
                echo "<pre style='background: rgba(0,0,0,0.1); padding: 8px; border-radius: 4px; margin-top: 8px; font-size: 12px; overflow-x: auto;'>";
                echo htmlspecialchars(print_r($test['data'], true));
                echo "</pre>";
                echo "</details>";
            }
            
            echo "</div>";
        }
        
        // Next steps
        if ($this->failed > 0) {
            echo "<div style='padding: 15px; background: #fff3cd; border-radius: 8px; margin-top: 20px;'>";
            echo "<h3>🔧 Next Steps</h3>";
            echo "<ul>";
            echo "<li>Check your Mailchimp API key in the global settings</li>";
            echo "<li>Ensure your WordPress site can make external HTTP requests</li>";
            echo "<li>Verify your server has cURL enabled</li>";
            echo "<li>Check WordPress debug logs for additional error details</li>";
            echo "</ul>";
            echo "</div>";
        }
        
        echo "</div>";
    }
}

/**
 * Run the tests
 */
function runMailchimpTests() {
    $results = new MailchimpTestResults();
    
    // Test 1: Check if integration class exists
    $results->addTest(
        'Integration Class Available',
        class_exists('Mavlers_CF_Mailchimp_Integration'),
        class_exists('Mavlers_CF_Mailchimp_Integration') 
            ? 'Mailchimp integration class is loaded correctly.' 
            : 'Mailchimp integration class not found. Check if the addon is activated.'
    );
    
    if (!class_exists('Mavlers_CF_Mailchimp_Integration')) {
        $results->display();
        return;
    }
    
    // Initialize integration
    $mailchimp = new Mavlers_CF_Mailchimp_Integration();
    
    // Test 2: Check global settings
    $global_settings = $mailchimp->get_global_settings();
    $has_api_key = !empty($global_settings['api_key']);
    
    $results->addTest(
        'Global Settings Configuration',
        $has_api_key,
        $has_api_key 
            ? 'API key is configured in global settings.' 
            : 'No API key found. Please configure your Mailchimp API key in the global settings.',
        ['settings' => $global_settings]
    );
    
    if (!$has_api_key) {
        $results->display();
        return;
    }
    
    // Test 3: Test API connection
    $connection_test = $mailchimp->test_api_connection();
    $results->addTest(
        'API Connection Test',
        $connection_test['success'],
        $connection_test['message'],
        $connection_test['data']
    );
    
    if (!$connection_test['success']) {
        $results->display();
        return;
    }
    
    // Test 4: Get audiences
    $audiences_result = $mailchimp->get_audiences();
    $has_audiences = $audiences_result['success'] && !empty($audiences_result['data']);
    
    $results->addTest(
        'Audiences Retrieval',
        $has_audiences,
        $has_audiences 
            ? 'Successfully retrieved ' . count($audiences_result['data']) . ' audience(s).' 
            : 'Failed to retrieve audiences: ' . ($audiences_result['message'] ?? 'Unknown error'),
        $audiences_result['data'] ?? null
    );
    
    // Test 5: Test form settings
    $test_form_id = 1; // Use form ID 1 for testing
    $form_settings = $mailchimp->get_form_settings($test_form_id);
    
    $results->addTest(
        'Form Settings Functionality',
        is_array($form_settings) && isset($form_settings['enabled']),
        'Form settings structure is correct and accessible.',
        $form_settings
    );
    
    // Test 6: Test save functionality
    $test_settings = [
        'enabled' => true,
        'audience_id' => $has_audiences ? $audiences_result['data'][0]['id'] : 'test',
        'double_optin' => true,
        'update_existing' => true,
        'tags' => 'test-tag'
    ];
    
    $save_result = $mailchimp->save_form_settings($test_form_id, $test_settings);
    $results->addTest(
        'Settings Save Functionality',
        $save_result !== false,
        $save_result !== false 
            ? 'Settings save functionality is working correctly.' 
            : 'Failed to save form settings.',
        $save_result
    );
    
    // Test 7: Test AJAX endpoints
    $ajax_tests = [
        'mailchimp_test_connection' => 'Test Connection AJAX',
        'mailchimp_get_audiences' => 'Get Audiences AJAX',
        'mailchimp_save_global_settings' => 'Save Global Settings AJAX',
        'mailchimp_save_form_settings' => 'Save Form Settings AJAX'
    ];
    
    foreach ($ajax_tests as $action => $test_name) {
        $hook_exists = has_action("wp_ajax_{$action}");
        $results->addTest(
            $test_name,
            $hook_exists !== false,
            $hook_exists !== false 
                ? 'AJAX endpoint is properly registered.' 
                : 'AJAX endpoint not found. This may cause issues with the admin interface.'
        );
    }
    
    // Test 8: Test submission processing hook
    $submission_hook = has_action('mavlers_cf_after_submission', [$mailchimp, 'process_form_submission']);
    $results->addTest(
        'Form Submission Processing',
        $submission_hook !== false,
        $submission_hook !== false 
            ? 'Form submission processing hook is properly registered.' 
            : 'Form submission hook not found. Submissions will not be processed.',
        ['priority' => $submission_hook]
    );
    
    // Test 9: Test utility methods
    $is_connected = $mailchimp->is_globally_connected();
    $status_message = $mailchimp->get_connection_status_message();
    
    $results->addTest(
        'Utility Methods',
        $is_connected && !empty($status_message),
        "Connection status: {$status_message}",
        ['is_connected' => $is_connected, 'status_message' => $status_message]
    );
    
    // Test 10: Test database options
    $db_tests = [
        'Global settings option' => get_option('mavlers_cf_mailchimp_global'),
        'Form settings option' => get_option("mavlers_cf_mailchimp_form_{$test_form_id}")
    ];
    
    $db_working = true;
    foreach ($db_tests as $test_name => $option_value) {
        if ($option_value === false) {
            $db_working = false;
            break;
        }
    }
    
    $results->addTest(
        'Database Storage',
        $db_working,
        $db_working 
            ? 'Database options are being stored and retrieved correctly.' 
            : 'Some database options are missing. Settings may not persist.',
        $db_tests
    );
    
    return $results;
}

// Run tests and display results
echo "<!DOCTYPE html><html><head><title>Mailchimp Integration Test</title></head><body>";

try {
    $results = runMailchimpTests();
    $results->display();
} catch (Exception $e) {
    echo "<div style='max-width: 800px; margin: 20px auto; padding: 20px; background: #f8d7da; border-radius: 8px; color: #721c24;'>";
    echo "<h2>❌ Test Failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?> 