<?php
/**
 * Test script to verify integrations are working
 * Run this in the WordPress admin area
 */

// Ensure we're in WordPress admin
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Check if we're in admin
if (!is_admin()) {
    wp_die('This script must be run in the WordPress admin area');
}

echo "<h1>Integration Test Results</h1>";

// Test 1: Check if integrations are loaded
echo "<h2>1. Integration Loading Test</h2>";

// Get the plugin instance
$plugin = \MavlersCF\Integrations\Core\Plugin::getInstance();
$registry = $plugin->getRegistry();

echo "<p>Plugin instance created: " . ($plugin ? 'Yes' : 'No') . "</p>";
echo "<p>Registry instance created: " . ($registry ? 'Yes' : 'No') . "</p>";

// Check registered integrations
$integrations = $registry->getAll();
echo "<p>Total integrations registered: " . count($integrations) . "</p>";

foreach ($integrations as $id => $integration) {
    echo "<p>Integration: {$id} - {$integration->getName()} (v{$integration->getVersion()})</p>";
}

// Test 2: Check specific integrations
echo "<h2>2. Specific Integration Tests</h2>";

// Test Mailchimp
$mailchimp = $registry->get('mailchimp');
if ($mailchimp) {
    echo "<p>✅ Mailchimp integration found</p>";
    echo "<p>- ID: {$mailchimp->getId()}</p>";
    echo "<p>- Name: {$mailchimp->getName()}</p>";
    echo "<p>- Configured: " . ($mailchimp->isConfigured() ? 'Yes' : 'No') . "</p>";
} else {
    echo "<p>❌ Mailchimp integration not found</p>";
}

// Test HubSpot
$hubspot = $registry->get('hubspot');
if ($hubspot) {
    echo "<p>✅ HubSpot integration found</p>";
    echo "<p>- ID: {$hubspot->getId()}</p>";
    echo "<p>- Name: {$hubspot->getName()}</p>";
    echo "<p>- Configured: " . ($hubspot->isConfigured() ? 'Yes' : 'No') . "</p>";
} else {
    echo "<p>❌ HubSpot integration not found</p>";
}

// Test 3: Check AJAX handlers
echo "<h2>3. AJAX Handler Tests</h2>";

// Check if AJAX actions are registered
$ajax_actions = [
    'mailchimp' => [
        'mailchimp_test_connection',
        'mailchimp_get_audiences',
        'save_mailchimp_form_settings'
    ],
    'hubspot' => [
        'mavlers_cf_hubspot_test_connection',
        'mavlers_cf_hubspot_test_response',
        'hubspot_save_global_settings_v2'
    ]
];

foreach ($ajax_actions as $integration => $actions) {
    echo "<h3>{$integration} AJAX handlers:</h3>";
    foreach ($actions as $action) {
        $has_action = has_action("wp_ajax_{$action}");
        echo "<p>{$action}: " . ($has_action ? '✅ Registered' : '❌ Not registered') . "</p>";
    }
}

// Test 4: Check assets
echo "<h2>4. Asset Loading Tests</h2>";

// Check if scripts are enqueued
$enqueued_scripts = wp_scripts()->queue;
$mailchimp_script = in_array('mavlers-cf-mailchimp', $enqueued_scripts);
$hubspot_script = in_array('mavlers-cf-hubspot', $enqueued_scripts);

echo "<p>Mailchimp script enqueued: " . ($mailchimp_script ? '✅ Yes' : '❌ No') . "</p>";
echo "<p>HubSpot script enqueued: " . ($hubspot_script ? '✅ Yes' : '❌ No') . "</p>";

// Test 5: Check file existence
echo "<h2>5. File Existence Tests</h2>";

$files_to_check = [
    'Mailchimp' => [
        'addons/integrations-manager/src/Integrations/Mailchimp/MailchimpIntegration.php',
        'addons/integrations-manager/assets/js/admin/mailchimp-form.js',
        'addons/integrations-manager/assets/css/admin/mailchimp-form.css'
    ],
    'HubSpot' => [
        'addons/integrations-manager/src/Integrations/HubSpot/HubSpotIntegration.php',
        'addons/integrations-manager/assets/js/admin/hubspot-form.js',
        'addons/integrations-manager/assets/css/admin/hubspot-form.css'
    ]
];

foreach ($files_to_check as $integration => $files) {
    echo "<h3>{$integration} files:</h3>";
    foreach ($files as $file) {
        $exists = file_exists($file);
        echo "<p>{$file}: " . ($exists ? '✅ Exists' : '❌ Missing') . "</p>";
    }
}

// Test 6: Check database tables
echo "<h2>6. Database Tests</h2>";

global $wpdb;

// Check if forms table exists
$forms_table = $wpdb->prefix . 'mavlers_cf_forms';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$forms_table}'");
echo "<p>Forms table exists: " . ($table_exists ? '✅ Yes' : '❌ No') . "</p>";

// Check if submissions table exists
$submissions_table = $wpdb->prefix . 'mavlers_cf_submissions';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$submissions_table}'");
echo "<p>Submissions table exists: " . ($table_exists ? '✅ Yes' : '❌ No') . "</p>";

// Test 7: Check options
echo "<h2>7. Options Tests</h2>";

$global_settings = get_option('mavlers_cf_integrations_global', []);
echo "<p>Global settings option exists: " . (!empty($global_settings) ? '✅ Yes' : '❌ No') . "</p>";

if (!empty($global_settings)) {
    echo "<p>Global settings keys: " . implode(', ', array_keys($global_settings)) . "</p>";
}

echo "<h2>Test Complete</h2>";
echo "<p>Check the results above to identify any issues with the integrations.</p>";
?> 