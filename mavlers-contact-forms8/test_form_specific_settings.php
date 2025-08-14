<?php
/**
 * Test script to verify form-specific settings isolation
 */

// Include WordPress
require_once('../../../wp-load.php');

echo "<h1>Form-Specific Settings Test</h1>";

// Test with two different form IDs
$form_id_1 = 1;
$form_id_2 = 2;

echo "<h2>Testing Form ID Isolation</h2>";

// Test 1: Save different settings for each form
echo "<h3>1. Saving different settings for each form:</h3>";

$settings_form_1 = [
    'enabled' => true,
    'audience_id' => 'f7bbcd8e8f',
    'double_optin' => true,
    'update_existing' => false,
    'tags' => 'form-1-tag',
    'field_mapping' => [
        'field_1752556770336' => 'FNAME',
        'field_1752556781495' => 'LNAME',
        'field_1752556786279' => 'email_address'
    ]
];

$settings_form_2 = [
    'enabled' => true,
    'audience_id' => 'f7bbcd8e8f',
    'double_optin' => false,
    'update_existing' => true,
    'tags' => 'form-2-tag',
    'field_mapping' => [
        'field_1752556770336' => 'LNAME',
        'field_1752556781495' => 'FNAME',
        'field_1752556786279' => 'email_address'
    ]
];

// Save settings for form 1
$post_meta_result_1 = update_post_meta($form_id_1, '_mavlers_cf_integrations', ['mailchimp' => $settings_form_1]);
echo "<p><strong>Form 1 Settings Save:</strong> " . ($post_meta_result_1 ? 'Success' : 'Failed') . "</p>";

// Save settings for form 2
$post_meta_result_2 = update_post_meta($form_id_2, '_mavlers_cf_integrations', ['mailchimp' => $settings_form_2]);
echo "<p><strong>Form 2 Settings Save:</strong> " . ($post_meta_result_2 ? 'Success' : 'Failed') . "</p>";

// Test 2: Load settings for each form
echo "<h3>2. Loading settings for each form:</h3>";

// Load settings for form 1
$loaded_settings_1 = get_post_meta($form_id_1, '_mavlers_cf_integrations', true);
$mailchimp_settings_1 = $loaded_settings_1['mailchimp'] ?? [];

// Load settings for form 2
$loaded_settings_2 = get_post_meta($form_id_2, '_mavlers_cf_integrations', true);
$mailchimp_settings_2 = $loaded_settings_2['mailchimp'] ?? [];

echo "<p><strong>Form 1 Settings:</strong></p>";
echo "<pre>" . json_encode($mailchimp_settings_1, JSON_PRETTY_PRINT) . "</pre>";

echo "<p><strong>Form 2 Settings:</strong></p>";
echo "<pre>" . json_encode($mailchimp_settings_2, JSON_PRETTY_PRINT) . "</pre>";

// Test 3: Verify isolation
echo "<h3>3. Verifying isolation:</h3>";

$tags_match = ($mailchimp_settings_1['tags'] ?? '') === ($mailchimp_settings_2['tags'] ?? '');
$double_optin_match = ($mailchimp_settings_1['double_optin'] ?? false) === ($mailchimp_settings_2['double_optin'] ?? false);
$update_existing_match = ($mailchimp_settings_1['update_existing'] ?? false) === ($mailchimp_settings_2['update_existing'] ?? false);

echo "<p><strong>Tags are different:</strong> " . (!$tags_match ? '✅ PASS' : '❌ FAIL') . "</p>";
echo "<p><strong>Double optin are different:</strong> " . (!$double_optin_match ? '✅ PASS' : '❌ FAIL') . "</p>";
echo "<p><strong>Update existing are different:</strong> " . (!$update_existing_match ? '✅ PASS' : '❌ FAIL') . "</p>";

// Test 4: Simulate template loading
echo "<h3>4. Simulating template loading:</h3>";

// Simulate loading for form 1
$_GET['id'] = $form_id_1;
$form_id = intval($_GET['id']);
$form_settings = [];
$post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
if ($post_meta && isset($post_meta['mailchimp'])) {
    $form_settings = $post_meta['mailchimp'];
}

echo "<p><strong>Form 1 Template Loading:</strong></p>";
echo "<p>Form ID: $form_id</p>";
echo "<p>Settings: " . json_encode($form_settings) . "</p>";

// Simulate loading for form 2
$_GET['id'] = $form_id_2;
$form_id = intval($_GET['id']);
$form_settings = [];
$post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
if ($post_meta && isset($post_meta['mailchimp'])) {
    $form_settings = $post_meta['mailchimp'];
}

echo "<p><strong>Form 2 Template Loading:</strong></p>";
echo "<p>Form ID: $form_id</p>";
echo "<p>Settings: " . json_encode($form_settings) . "</p>";

// Test 5: Check for any global variables that might cause issues
echo "<h3>5. Checking for global variable issues:</h3>";

echo "<p><strong>Global variables:</strong></p>";
echo "<pre>";
print_r($GLOBALS);
echo "</pre>";

echo "<p><strong>Test complete!</strong></p>";
?> 