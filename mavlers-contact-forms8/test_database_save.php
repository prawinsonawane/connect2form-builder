<?php
/**
 * Database Save Test
 * Tests if the database save operations work
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

echo "<h1>Database Save Test</h1>";

// Test 1: Basic option save
echo "<h2>1. Basic Option Save Test</h2>";
$test_option = 'mavlers_cf_test_option';
$test_data = ['test' => 'value', 'number' => 123];

$save_result = update_option($test_option, $test_data);
echo "<p><strong>Basic save result:</strong> " . ($save_result ? '✅ SUCCESS' : '❌ FAILED') . "</p>";

$retrieved_data = get_option($test_option, []);
echo "<p><strong>Retrieved data:</strong></p>";
echo "<pre>" . print_r($retrieved_data, true) . "</pre>";

// Test 2: Global settings save
echo "<h2>2. Global Settings Save Test</h2>";
$global_settings = [
    'mailchimp' => [
        'api_key' => 'test-api-key-123',
        'enable_analytics' => true,
        'enable_webhooks' => false,
        'batch_processing' => true
    ],
    'hubspot' => [
        'access_token' => 'test-access-token-456',
        'portal_id' => '12345',
        'enable_analytics' => true,
        'enable_webhooks' => false,
        'batch_processing' => true
    ]
];

$global_save_result = update_option('mavlers_cf_integrations_global', $global_settings);
echo "<p><strong>Global settings save result:</strong> " . ($global_save_result ? '✅ SUCCESS' : '❌ FAILED') . "</p>";

$retrieved_global = get_option('mavlers_cf_integrations_global', []);
echo "<p><strong>Retrieved global settings:</strong></p>";
echo "<pre>" . print_r($retrieved_global, true) . "</pre>";

// Test 3: Check if options table is writable
echo "<h2>3. Database Permissions Test</h2>";
global $wpdb;

$table_name = $wpdb->options;
$test_query = $wpdb->prepare("SELECT option_value FROM $table_name WHERE option_name = %s", 'test_permission');
$query_result = $wpdb->get_var($test_query);

echo "<p><strong>Database read test:</strong> " . ($query_result !== null ? '✅ SUCCESS' : '❌ FAILED') . "</p>";

// Test 4: Test update_option with different data types
echo "<h2>4. Data Type Save Test</h2>";

$test_cases = [
    'string' => 'test string',
    'number' => 123,
    'boolean' => true,
    'array' => ['key' => 'value'],
    'empty_string' => '',
    'null' => null
];

foreach ($test_cases as $type => $value) {
    $option_name = "mavlers_cf_test_{$type}";
    $save_result = update_option($option_name, $value);
    $retrieved = get_option($option_name);
    
    echo "<p><strong>$type:</strong> Save: " . ($save_result ? '✅' : '❌') . 
         " | Retrieved: " . ($retrieved === $value ? '✅' : '❌') . 
         " | Value: " . var_export($retrieved, true) . "</p>";
}

// Test 5: Check current global settings
echo "<h2>5. Current Global Settings</h2>";
$current_global = get_option('mavlers_cf_integrations_global', []);
echo "<p><strong>Current global settings:</strong></p>";
echo "<pre>" . print_r($current_global, true) . "</pre>";

echo "<h2>Test Complete</h2>";
echo "<p>If all tests pass, the database operations are working correctly.</p>";
echo "<p>If any test fails, there may be a database permissions issue.</p>";
?> 