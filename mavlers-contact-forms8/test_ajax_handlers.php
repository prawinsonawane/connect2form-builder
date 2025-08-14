<?php
/**
 * Debug script to test AJAX handlers
 * Place this in the plugin root and access via: /wp-content/plugins/mavlers-contact-forms/test_ajax_handlers.php
 */

if (!defined('ABSPATH')) {
    // Load WordPress
    $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die('WordPress not found');
    }
}

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Insufficient permissions');
}

echo "<h1>AJAX Handler Test</h1>";

// Test Mailchimp AJAX handler
echo "<h2>Testing Mailchimp AJAX Handler</h2>";

// Check if action is registered
global $wp_filter;
$mailchimp_action = 'wp_ajax_mailchimp_save_global_settings';
$hubspot_action = 'wp_ajax_hubspot_save_global_settings';

echo "<p><strong>Mailchimp action registered:</strong> " . (isset($wp_filter[$mailchimp_action]) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>HubSpot action registered:</strong> " . (isset($wp_filter[$hubspot_action]) ? 'YES' : 'NO') . "</p>";

// List all registered AJAX actions
echo "<h3>All registered AJAX actions:</h3>";
echo "<ul>";
foreach ($wp_filter as $hook => $callbacks) {
    if (strpos($hook, 'wp_ajax_') === 0) {
        echo "<li>$hook</li>";
    }
}
echo "</ul>";

// Test nonce creation
$nonce = wp_create_nonce('mavlers_cf_nonce');
echo "<p><strong>Test nonce:</strong> $nonce</p>";

// Test settings data
$test_settings = [
    'api_key' => 'test-api-key',
    'enable_analytics' => '1',
    'enable_webhooks' => '0',
    'batch_processing' => '1'
];

echo "<h3>Test settings data:</h3>";
echo "<pre>" . print_r($test_settings, true) . "</pre>";

// Check if integrations are loaded
echo "<h2>Integration Loading Test</h2>";

// Check Mailchimp integration
if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
    echo "<p><strong>Mailchimp integration class:</strong> LOADED</p>";
    $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
    echo "<p><strong>Mailchimp ID:</strong> " . $mailchimp->getId() . "</p>";
} else {
    echo "<p><strong>Mailchimp integration class:</strong> NOT LOADED</p>";
}

// Check HubSpot integration
if (class_exists('MavlersCF\Integrations\Hubspot\HubspotIntegration')) {
    echo "<p><strong>HubSpot integration class:</strong> LOADED</p>";
    $hubspot = new MavlersCF\Integrations\Hubspot\HubspotIntegration();
    echo "<p><strong>HubSpot ID:</strong> " . $hubspot->getId() . "</p>";
} else {
    echo "<p><strong>HubSpot integration class:</strong> NOT LOADED</p>";
}

// Test global settings storage
echo "<h2>Global Settings Storage Test</h2>";

$test_global_settings = [
    'mailchimp' => [
        'api_key' => 'test-mailchimp-key',
        'enable_analytics' => true,
        'enable_webhooks' => false,
        'batch_processing' => true
    ],
    'hubspot' => [
        'access_token' => 'test-hubspot-token',
        'portal_id' => '12345',
        'enable_analytics' => true,
        'enable_webhooks' => false,
        'batch_processing' => true
    ]
];

$save_result = update_option('mavlers_cf_integrations_global', $test_global_settings);
echo "<p><strong>Save test settings:</strong> " . ($save_result ? 'SUCCESS' : 'FAILED') . "</p>";

$retrieved_settings = get_option('mavlers_cf_integrations_global', []);
echo "<p><strong>Retrieved settings:</strong></p>";
echo "<pre>" . print_r($retrieved_settings, true) . "</pre>";

echo "<h2>Test Complete</h2>";
echo "<p>Check the browser console and server logs for AJAX handler debugging information.</p>";
?> 