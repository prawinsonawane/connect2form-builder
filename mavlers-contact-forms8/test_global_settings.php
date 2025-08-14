<?php
/**
 * Test Global Settings
 * 
 * Test to check what global settings are saved in the database
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only run for admin users
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Check if test parameter is present
if (!isset($_GET['test']) || $_GET['test'] !== 'global_settings') {
    return;
}

echo '<div style="background: #e3f2fd; padding: 20px; margin: 20px; border: 1px solid #2196f3;">';
echo '<h2>🔍 Test Global Settings</h2>';

// Test 1: Check raw option value
echo '<h3>Test 1: Raw Option Value</h3>';
$raw_option = get_option('mavlers_cf_integrations_global', 'NOT_FOUND');
echo '<p><strong>Raw option value:</strong></p>';
echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ccc; max-height: 200px; overflow-y: auto;">';
var_dump($raw_option);
echo '</pre>';

// Test 2: Check Mailchimp settings specifically
echo '<h3>Test 2: Mailchimp Settings</h3>';
$global_settings = get_option('mavlers_cf_integrations_global', []);
$mailchimp_settings = $global_settings['mailchimp'] ?? 'NOT_FOUND';
echo '<p><strong>Mailchimp settings:</strong></p>';
echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ccc; max-height: 200px; overflow-y: auto;">';
var_dump($mailchimp_settings);
echo '</pre>';

// Test 3: Check if API key exists
echo '<h3>Test 3: API Key Check</h3>';
if (is_array($mailchimp_settings) && isset($mailchimp_settings['api_key'])) {
    $api_key = $mailchimp_settings['api_key'];
    $api_key_length = strlen($api_key);
    $api_key_preview = substr($api_key, 0, 10) . '...' . substr($api_key, -3);
    echo '<p style="color: green;">✓ API key found</p>';
    echo '<p><strong>API key length:</strong> ' . $api_key_length . '</p>';
    echo '<p><strong>API key preview:</strong> ' . $api_key_preview . '</p>';
} else {
    echo '<p style="color: red;">✗ API key not found</p>';
}

// Test 4: Test the integration's get_global_settings method
echo '<h3>Test 4: Integration Method Test</h3>';
if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
    try {
        $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
        $integration_settings = $mailchimp->get_global_settings();
        echo '<p><strong>Integration get_global_settings() result:</strong></p>';
        echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ccc; max-height: 200px; overflow-y: auto;">';
        var_dump($integration_settings);
        echo '</pre>';
        
        $is_connected = $mailchimp->is_globally_connected();
        echo '<p><strong>is_globally_connected():</strong> ' . ($is_connected ? 'true' : 'false') . '</p>';
        
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">✗ Mailchimp integration class not found</p>';
}

// Test 5: Check all options that start with mavlers_cf
echo '<h3>Test 5: All Mavlers CF Options</h3>';
global $wpdb;
$options = $wpdb->get_results(
    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'mavlers_cf%' ORDER BY option_name"
);

if ($options) {
    echo '<p><strong>Found options:</strong></p>';
    echo '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
    echo '<tr style="background: #f5f5f5;"><th style="border: 1px solid #ddd; padding: 8px;">Option Name</th><th style="border: 1px solid #ddd; padding: 8px;">Value</th></tr>';
    
    foreach ($options as $option) {
        $value = $option->option_value;
        if (strlen($value) > 100) {
            $value = substr($value, 0, 100) . '...';
        }
        echo '<tr>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($option->option_name) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($value) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p style="color: orange;">⚠ No mavlers_cf options found</p>';
}

// Test 6: Manual save test
echo '<h3>Test 6: Manual Save Test</h3>';
echo '<form method="post" style="background: #fff; padding: 15px; border: 1px solid #ccc; margin-top: 10px;">';
echo '<p><label>API Key: <input type="text" name="test_api_key" style="width: 300px;" placeholder="Enter test API key"></label></p>';
echo '<p><input type="submit" name="test_save" value="Save Test Settings" class="button button-primary"></p>';
echo '</form>';

if (isset($_POST['test_save']) && !empty($_POST['test_api_key'])) {
    $test_api_key = sanitize_text_field($_POST['test_api_key']);
    
    // Save using the integration method
    if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
        $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
        $test_settings = ['api_key' => $test_api_key];
        $save_result = $mailchimp->saveGlobalSettings($test_settings);
        
        echo '<p style="color: ' . ($save_result ? 'green' : 'red') . ';">';
        echo ($save_result ? '✓' : '✗') . ' Save result: ' . ($save_result ? 'success' : 'failed');
        echo '</p>';
        
        // Verify the save
        $verify_settings = $mailchimp->get_global_settings();
        echo '<p><strong>Verification - saved settings:</strong></p>';
        echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ccc;">';
        var_dump($verify_settings);
        echo '</pre>';
    }
}

echo '</div>'; 