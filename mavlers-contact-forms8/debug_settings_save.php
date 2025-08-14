<?php
/**
 * Debug Settings Save Test
 * Tests the settings save functionality for both integrations
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

echo "<h1>Settings Save Debug Test</h1>";

// Test Mailchimp settings save
echo "<h2>1. Mailchimp Settings Save Test</h2>";

if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
    $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
    
    $test_settings = [
        'api_key' => 'test-mailchimp-api-key',
        'enable_analytics' => true,
        'enable_webhooks' => false,
        'batch_processing' => true
    ];
    
    echo "<p><strong>Test settings:</strong></p>";
    echo "<pre>" . print_r($test_settings, true) . "</pre>";
    
    // Test validation
    echo "<p><strong>Validation test:</strong></p>";
    $validation_result = $mailchimp->validateSettings($test_settings);
    echo "<p>Validation result: " . print_r($validation_result, true) . "</p>";
    
    // Test save
    echo "<p><strong>Save test:</strong></p>";
    $save_result = $mailchimp->saveGlobalSettings($test_settings);
    echo "<p>Save result: " . ($save_result ? 'SUCCESS' : 'FAILED') . "</p>";
    
    // Verify save
    $retrieved_settings = get_option('mavlers_cf_integrations_global', []);
    echo "<p><strong>Retrieved settings:</strong></p>";
    echo "<pre>" . print_r($retrieved_settings, true) . "</pre>";
    
} else {
    echo "<p>❌ Mailchimp integration not loaded</p>";
}

// Test HubSpot settings save
echo "<h2>2. HubSpot Settings Save Test</h2>";

if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    $hubspot = new MavlersCF\Integrations\Hubspot\HubspotIntegration();
    
    $test_settings = [
        'access_token' => 'test-hubspot-access-token',
        'portal_id' => '12345',
        'enable_analytics' => true,
        'enable_webhooks' => false,
        'batch_processing' => true
    ];
    
    echo "<p><strong>Test settings:</strong></p>";
    echo "<pre>" . print_r($test_settings, true) . "</pre>";
    
    // Test validation
    echo "<p><strong>Validation test:</strong></p>";
    $validation_result = $hubspot->validateSettings($test_settings);
    echo "<p>Validation result: " . print_r($validation_result, true) . "</p>";
    
    // Test save
    echo "<p><strong>Save test:</strong></p>";
    $save_result = $hubspot->saveGlobalSettings($test_settings);
    echo "<p>Save result: " . ($save_result ? 'SUCCESS' : 'FAILED') . "</p>";
    
    // Verify save
    $retrieved_settings = get_option('mavlers_cf_integrations_global', []);
    echo "<p><strong>Retrieved settings:</strong></p>";
    echo "<pre>" . print_r($retrieved_settings, true) . "</pre>";
    
} else {
    echo "<p>❌ HubSpot integration not loaded</p>";
}

// Test AJAX simulation
echo "<h2>3. AJAX Simulation Test</h2>";

$nonce = wp_create_nonce('mavlers_cf_nonce');

// Simulate Mailchimp AJAX save
echo "<h3>Mailchimp AJAX Save Simulation</h3>";
$_POST = [
    'nonce' => $nonce,
    'settings' => [
        'api_key' => 'test-ajax-mailchimp-key',
        'enable_analytics' => '1',
        'enable_webhooks' => '0',
        'batch_processing' => '1'
    ]
];

if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
    $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
    
    echo "<p><strong>POST data:</strong></p>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    // Extract settings like the AJAX handler does
    $settings = [];
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        $settings = $_POST['settings'];
    } else {
        $settings = [
            'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
            'enable_analytics' => !empty($_POST['enable_analytics']),
            'enable_webhooks' => !empty($_POST['enable_webhooks']),
            'batch_processing' => !empty($_POST['batch_processing'])
        ];
    }
    
    echo "<p><strong>Extracted settings:</strong></p>";
    echo "<pre>" . print_r($settings, true) . "</pre>";
    
    // Test validation
    $validation_result = $mailchimp->validateSettings($settings);
    echo "<p><strong>Validation result:</strong> " . print_r($validation_result, true) . "</p>";
    
    // Test save
    $save_result = $mailchimp->saveGlobalSettings($settings);
    echo "<p><strong>Save result:</strong> " . ($save_result ? 'SUCCESS' : 'FAILED') . "</p>";
}

// Simulate HubSpot AJAX save
echo "<h3>HubSpot AJAX Save Simulation</h3>";
$_POST = [
    'nonce' => $nonce,
    'settings' => [
        'access_token' => 'test-ajax-hubspot-token',
        'portal_id' => '67890',
        'enable_analytics' => '1',
        'enable_webhooks' => '0',
        'batch_processing' => '1'
    ]
];

if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    $hubspot = new MavlersCF\Integrations\Hubspot\HubspotIntegration();
    
    echo "<p><strong>POST data:</strong></p>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    // Extract settings like the AJAX handler does
    $settings = [];
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        $settings = $_POST['settings'];
    } else {
        $settings = [
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
            'enable_analytics' => !empty($_POST['enable_analytics']),
            'enable_webhooks' => !empty($_POST['enable_webhooks']),
            'batch_processing' => !empty($_POST['batch_processing'])
        ];
    }
    
    echo "<p><strong>Extracted settings:</strong></p>";
    echo "<pre>" . print_r($settings, true) . "</pre>";
    
    // Test validation
    $validation_result = $hubspot->validateSettings($settings);
    echo "<p><strong>Validation result:</strong> " . print_r($validation_result, true) . "</p>";
    
    // Test save
    $save_result = $hubspot->saveGlobalSettings($settings);
    echo "<p><strong>Save result:</strong> " . ($save_result ? 'SUCCESS' : 'FAILED') . "</p>";
}

echo "<h2>Test Complete</h2>";
echo "<p>Check the server logs for detailed debugging information.</p>";
?> 