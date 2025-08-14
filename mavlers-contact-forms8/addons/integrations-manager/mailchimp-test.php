<?php
/**
 * Mailchimp Field Mapping Test Script
 * 
 * This script helps debug Mailchimp field mapping issues
 * Access via: /wp-content/plugins/mavlers-contact-forms/addons/integrations-manager/mailchimp-test.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress if not already loaded
    $wp_load = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die('WordPress not found');
    }
}

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Mailchimp Field Mapping Test</h1>";

// Test 1: Check if integration is loaded
echo "<h2>Test 1: Integration Loading</h2>";
if (class_exists('MavlersCF\\Integrations\\Mailchimp\\MailchimpIntegration')) {
    echo "✅ MailchimpIntegration class found<br>";
} else {
    echo "❌ MailchimpIntegration class not found<br>";
}

if (class_exists('MavlersCF\\Integrations\\Mailchimp\\CustomFieldsManager')) {
    echo "✅ CustomFieldsManager class found<br>";
} else {
    echo "❌ CustomFieldsManager class not found<br>";
}

// Test 2: Check global settings
echo "<h2>Test 2: Global Settings</h2>";
$global_settings = get_option('mavlers_cf_integrations_global', []);
$mailchimp_settings = $global_settings['mailchimp'] ?? [];

if (!empty($mailchimp_settings)) {
    echo "✅ Global settings found<br>";
    echo "API Key: " . (isset($mailchimp_settings['api_key']) ? 'Set' : 'Not set') . "<br>";
} else {
    echo "❌ No global settings found<br>";
}

// Test 3: Test API connection
echo "<h2>Test 3: API Connection</h2>";
if (!empty($mailchimp_settings['api_key'])) {
    $api_key = $mailchimp_settings['api_key'];
    $dc = '';
    
    // Extract datacenter
    if (strpos($api_key, '-') !== false) {
        $dc = substr($api_key, strpos($api_key, '-') + 1);
    }
    
    echo "API Key: " . substr($api_key, 0, 10) . "...<br>";
    echo "Datacenter: {$dc}<br>";
    
    // Test basic connectivity
    $url = "https://{$dc}.api.mailchimp.com/3.0/ping";
    $args = array(
        'method' => 'GET',
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    );
    
    $response = wp_remote_request($url, $args);
    
    if (is_wp_error($response)) {
        echo "❌ API connection failed: " . $response->get_error_message() . "<br>";
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        echo "Response Code: {$response_code}<br>";
        echo "Response Body: {$body}<br>";
        
        if ($response_code === 200) {
            echo "✅ API connection successful<br>";
        } else {
            echo "❌ API connection failed with code {$response_code}<br>";
        }
    }
} else {
    echo "❌ No API key found<br>";
}

// Test 4: Test audiences
echo "<h2>Test 4: Audiences</h2>";
if (!empty($mailchimp_settings['api_key'])) {
    $api_key = $mailchimp_settings['api_key'];
    $dc = '';
    
    if (strpos($api_key, '-') !== false) {
        $dc = substr($api_key, strpos($api_key, '-') + 1);
    }
    
    $url = "https://{$dc}.api.mailchimp.com/3.0/lists?count=10";
    $args = array(
        'method' => 'GET',
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    );
    
    $response = wp_remote_request($url, $args);
    
    if (is_wp_error($response)) {
        echo "❌ Failed to get audiences: " . $response->get_error_message() . "<br>";
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        echo "Response Code: {$response_code}<br>";
        
        if ($response_code === 200 && isset($data['lists'])) {
            echo "✅ Found " . count($data['lists']) . " audiences<br>";
            foreach ($data['lists'] as $list) {
                echo "- {$list['name']} (ID: {$list['id']})<br>";
            }
        } else {
            echo "❌ Failed to get audiences<br>";
            echo "Response: {$body}<br>";
        }
    }
} else {
    echo "❌ No API key available for audience test<br>";
}

// Test 5: Test merge fields for first audience
echo "<h2>Test 5: Merge Fields</h2>";
if (!empty($mailchimp_settings['api_key'])) {
    $api_key = $mailchimp_settings['api_key'];
    $dc = '';
    
    if (strpos($api_key, '-') !== false) {
        $dc = substr($api_key, strpos($api_key, '-') + 1);
    }
    
    // Get first audience
    $url = "https://{$dc}.api.mailchimp.com/3.0/lists?count=1";
    $args = array(
        'method' => 'GET',
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    );
    
    $response = wp_remote_request($url, $args);
    
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['lists'][0]['id'])) {
            $audience_id = $data['lists'][0]['id'];
            echo "Testing merge fields for audience: {$audience_id}<br>";
            
            // Get merge fields
            $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$audience_id}/merge-fields?count=100";
            $response = wp_remote_request($url, $args);
            
            if (is_wp_error($response)) {
                echo "❌ Failed to get merge fields: " . $response->get_error_message() . "<br>";
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                echo "Response Code: {$response_code}<br>";
                
                if ($response_code === 200 && isset($data['merge_fields'])) {
                    echo "✅ Found " . count($data['merge_fields']) . " merge fields<br>";
                    foreach ($data['merge_fields'] as $field) {
                        echo "- {$field['name']} (Tag: {$field['tag']}, Type: {$field['type']})<br>";
                    }
                } else {
                    echo "❌ Failed to get merge fields<br>";
                    echo "Response: {$body}<br>";
                }
            }
        } else {
            echo "❌ No audiences found to test merge fields<br>";
        }
    } else {
        echo "❌ Failed to get audiences for merge field test<br>";
    }
} else {
    echo "❌ No API key available for merge field test<br>";
}

// Test 6: Check AJAX handlers
echo "<h2>Test 6: AJAX Handlers</h2>";
$ajax_actions = array(
    'mailchimp_get_merge_fields',
    'mailchimp_get_audience_merge_fields',
    'mailchimp_get_audiences',
    'mailchimp_test_connection'
);

foreach ($ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        echo "✅ AJAX handler '{$action}' is registered<br>";
    } else {
        echo "❌ AJAX handler '{$action}' is NOT registered<br>";
    }
}

// Test 7: Check form data
echo "<h2>Test 7: Form Data</h2>";
global $wpdb;
$forms = $wpdb->get_results("SELECT id, form_title, fields FROM {$wpdb->prefix}mavlers_cf_forms LIMIT 3");

if ($forms) {
    echo "✅ Found " . count($forms) . " forms<br>";
    foreach ($forms as $form) {
        echo "Form ID: {$form->id}, Title: {$form->form_title}<br>";
        $fields = json_decode($form->fields, true);
        if ($fields) {
            echo "  - Has " . count($fields) . " fields<br>";
        } else {
            echo "  - No fields or invalid JSON<br>";
        }
    }
} else {
    echo "❌ No forms found in database<br>";
}

echo "<h2>Test Complete</h2>";
echo "<p>Check the WordPress error log for additional debugging information.</p>";
echo "<p>Error log location: " . ini_get('error_log') . "</p>";
?> 