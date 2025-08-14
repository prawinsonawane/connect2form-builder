<?php
/**
 * Debug script for Mailchimp field loading issues
 * 
 * This script helps diagnose why Mailchimp fields are not loading properly.
 * Run this script by visiting: /wp-content/plugins/mavlers-contact-forms/addons/integrations-manager/debug-mailchimp-fields.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress
    $wp_load_path = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die('WordPress not found');
    }
}

// Security check
if (!current_user_can('manage_options')) {
    die('Insufficient permissions');
}

echo "<h1>Mailchimp Field Loading Debug</h1>\n";
echo "<p>This script helps diagnose Mailchimp field loading issues.</p>\n";

// Test 1: Check if classes are loaded
echo "<h2>1. Class Loading Test</h2>\n";
$classes_to_check = [
    'MavlersCF\Integrations\Mailchimp\MailchimpIntegration',
    'MavlersCF\Integrations\Mailchimp\CustomFieldsManager',
    'MavlersCF\Integrations\Core\Abstracts\AbstractIntegration'
];

foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "✅ <strong>{$class}</strong> is loaded<br>\n";
    } else {
        echo "❌ <strong>{$class}</strong> is NOT loaded<br>\n";
    }
}

// Test 2: Check global settings
echo "<h2>2. Global Settings Test</h2>\n";
$global_settings = get_option('mavlers_cf_mailchimp_global_settings', []);
if (!empty($global_settings)) {
    echo "✅ Global settings found<br>\n";
    echo "API Key: " . (isset($global_settings['api_key']) ? substr($global_settings['api_key'], 0, 10) . '...' : 'Not set') . "<br>\n";
    echo "Datacenter: " . ($global_settings['datacenter'] ?? 'Not set') . "<br>\n";
} else {
    echo "❌ No global settings found<br>\n";
}

// Test 3: Check connection
echo "<h2>3. Connection Test</h2>\n";
if (!empty($global_settings['api_key'])) {
    $api_key = $global_settings['api_key'];
    $dc = $global_settings['datacenter'] ?? '';
    
    if (empty($dc)) {
        // Extract datacenter from API key
        if (preg_match('/-([a-z0-9]+)$/', $api_key, $matches)) {
            $dc = $matches[1];
        }
    }
    
    if ($dc) {
        echo "✅ Datacenter extracted: {$dc}<br>\n";
        
        // Test basic connectivity
        $url = "https://{$dc}.api.mailchimp.com/3.0/ping";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            echo "❌ Connection failed: " . $response->get_error_message() . "<br>\n";
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                echo "✅ Connection successful<br>\n";
                echo "Response: " . $body . "<br>\n";
            } else {
                echo "❌ Connection failed with status: {$status_code}<br>\n";
                echo "Response: " . $body . "<br>\n";
            }
        }
    } else {
        echo "❌ Could not extract datacenter from API key<br>\n";
    }
} else {
    echo "❌ No API key found in global settings<br>\n";
}

// Test 4: Check audiences
echo "<h2>4. Audiences Test</h2>\n";
if (!empty($global_settings['api_key'])) {
    $api_key = $global_settings['api_key'];
    $dc = $global_settings['datacenter'] ?? '';
    
    if (empty($dc)) {
        if (preg_match('/-([a-z0-9]+)$/', $api_key, $matches)) {
            $dc = $matches[1];
        }
    }
    
    if ($dc) {
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists?count=100";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            echo "❌ Failed to get audiences: " . $response->get_error_message() . "<br>\n";
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                if (isset($data['lists']) && !empty($data['lists'])) {
                    echo "✅ Found " . count($data['lists']) . " audiences<br>\n";
                    foreach ($data['lists'] as $list) {
                        echo "- {$list['name']} (ID: {$list['id']})<br>\n";
                    }
                } else {
                    echo "❌ No audiences found<br>\n";
                }
            } else {
                echo "❌ Failed to get audiences with status: {$status_code}<br>\n";
                echo "Response: " . $body . "<br>\n";
            }
        }
    }
}

// Test 5: Check merge fields for first audience
echo "<h2>5. Merge Fields Test</h2>\n";
if (!empty($global_settings['api_key'])) {
    $api_key = $global_settings['api_key'];
    $dc = $global_settings['datacenter'] ?? '';
    
    if (empty($dc)) {
        if (preg_match('/-([a-z0-9]+)$/', $api_key, $matches)) {
            $dc = $matches[1];
        }
    }
    
    if ($dc) {
        // First get audiences
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists?count=100";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['lists']) && !empty($data['lists'])) {
                $first_audience = $data['lists'][0];
                $audience_id = $first_audience['id'];
                
                echo "Testing merge fields for audience: {$first_audience['name']} (ID: {$audience_id})<br>\n";
                
                // Get merge fields
                $merge_url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$audience_id}/merge-fields?count=100";
                $merge_response = wp_remote_get($merge_url, [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                        'Content-Type' => 'application/json'
                    ],
                    'timeout' => 10
                ]);
                
                if (is_wp_error($merge_response)) {
                    echo "❌ Failed to get merge fields: " . $merge_response->get_error_message() . "<br>\n";
                } else {
                    $merge_status = wp_remote_retrieve_response_code($merge_response);
                    $merge_body = wp_remote_retrieve_body($merge_response);
                    
                    if ($merge_status === 200) {
                        $merge_data = json_decode($merge_body, true);
                        if (isset($merge_data['merge_fields']) && !empty($merge_data['merge_fields'])) {
                            echo "✅ Found " . count($merge_data['merge_fields']) . " merge fields<br>\n";
                            foreach ($merge_data['merge_fields'] as $field) {
                                echo "- {$field['name']} (Tag: {$field['tag']}, Type: {$field['type']})<br>\n";
                            }
                        } else {
                            echo "❌ No merge fields found<br>\n";
                        }
                    } else {
                        echo "❌ Failed to get merge fields with status: {$merge_status}<br>\n";
                        echo "Response: " . $merge_body . "<br>\n";
                    }
                }
            } else {
                echo "❌ No audiences available for merge fields test<br>\n";
            }
        }
    }
}

// Test 6: Check AJAX handler registration
echo "<h2>6. AJAX Handler Registration Test</h2>\n";
$ajax_actions = [
    'mailchimp_get_merge_fields',
    'mailchimp_get_audience_merge_fields',
    'mailchimp_get_audiences',
    'mailchimp_test_connection'
];

foreach ($ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        echo "✅ AJAX action '{$action}' is registered<br>\n";
    } else {
        echo "❌ AJAX action '{$action}' is NOT registered<br>\n";
    }
}

// Test 7: Check form data structure
echo "<h2>7. Form Data Structure Test</h2>\n";
global $wpdb;
$forms = $wpdb->get_results("SELECT id, name, fields FROM {$wpdb->prefix}mavlers_cf_forms LIMIT 3");

if ($forms) {
    echo "✅ Found " . count($forms) . " forms in database<br>\n";
    foreach ($forms as $form) {
        echo "Form ID: {$form->id}, Name: {$form->name}<br>\n";
        $fields = json_decode($form->fields, true);
        if (is_array($fields)) {
            echo "- Has " . count($fields) . " fields<br>\n";
            foreach ($fields as $field) {
                if (isset($field['id']) && isset($field['label'])) {
                    echo "  * {$field['label']} (ID: {$field['id']}, Type: " . ($field['type'] ?? 'unknown') . ")<br>\n";
                }
            }
        } else {
            echo "- Invalid fields data<br>\n";
        }
    }
} else {
    echo "❌ No forms found in database<br>\n";
}

// Test 8: Manual AJAX test simulation
echo "<h2>8. Manual AJAX Test Simulation</h2>\n";
if (!empty($global_settings['api_key'])) {
    $api_key = $global_settings['api_key'];
    $dc = $global_settings['datacenter'] ?? '';
    
    if (empty($dc)) {
        if (preg_match('/-([a-z0-9]+)$/', $api_key, $matches)) {
            $dc = $matches[1];
        }
    }
    
    if ($dc) {
        // Get first audience ID
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists?count=100";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['lists']) && !empty($data['lists'])) {
                $audience_id = $data['lists'][0]['id'];
                
                echo "Testing manual merge fields request for audience: {$audience_id}<br>\n";
                
                // Simulate the exact request that the AJAX handler would make
                $merge_url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$audience_id}/merge-fields?count=100";
                $merge_response = wp_remote_get($merge_url, [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                        'Content-Type' => 'application/json'
                    ],
                    'timeout' => 10
                ]);
                
                if (is_wp_error($merge_response)) {
                    echo "❌ Manual request failed: " . $merge_response->get_error_message() . "<br>\n";
                } else {
                    $merge_status = wp_remote_retrieve_response_code($merge_response);
                    $merge_body = wp_remote_retrieve_body($merge_response);
                    
                    echo "Status: {$merge_status}<br>\n";
                    echo "Response: " . substr($merge_body, 0, 500) . (strlen($merge_body) > 500 ? '...' : '') . "<br>\n";
                    
                    if ($merge_status === 200) {
                        $merge_data = json_decode($merge_body, true);
                        if (isset($merge_data['merge_fields'])) {
                            echo "✅ Manual request successful - found " . count($merge_data['merge_fields']) . " merge fields<br>\n";
                        } else {
                            echo "❌ Manual request successful but no merge_fields in response<br>\n";
                        }
                    } else {
                        echo "❌ Manual request failed with status {$merge_status}<br>\n";
                    }
                }
            }
        }
    }
}

echo "<h2>Debug Complete</h2>\n";
echo "<p>Check the output above to identify the issue with Mailchimp field loading.</p>\n";
echo "<p>If the manual AJAX test works but the JavaScript doesn't, the issue is likely in:</p>\n";
echo "<ul>\n";
echo "<li>JavaScript AJAX call</li>\n";
echo "<li>AJAX handler registration</li>\n";
echo "<li>Script localization</li>\n";
echo "<li>Nonce verification</li>\n";
echo "</ul>\n"; 