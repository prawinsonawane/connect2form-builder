<?php
/**
 * Comprehensive debug script to check form settings for all forms
 */

// Include WordPress
require_once('../../../wp-load.php');

echo "<h1>Form Settings Debug - All Forms</h1>";

// Test with multiple form IDs
$form_ids = [1, 2, 3];

foreach ($form_ids as $form_id) {
    echo "<h2>=== FORM ID: {$form_id} ===</h2>";
    
    // Check if the form exists in the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'mavlers_cf_forms';
    $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $form_id));
    
    if (!$form) {
        echo "<p style='color: red;'><strong>❌ Form ID {$form_id} does not exist in database!</strong></p>";
        continue;
    }
    
    echo "<p><strong>✅ Form exists:</strong> {$form->form_title}</p>";
    
    // Check post meta
    $post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
    echo "<p><strong>Post Meta:</strong></p>";
    echo "<pre>" . print_r($post_meta, true) . "</pre>";
    
    // Check custom meta table
    $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
    $meta_value = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
        $form_id,
        '_mavlers_cf_integrations'
    ));
    
    echo "<p><strong>Custom Meta Table:</strong></p>";
    echo "<pre>" . ($meta_value ? $meta_value : 'Not found') . "</pre>";
    
    // Check options table
    $option_key = "mavlers_cf_mailchimp_form_{$form_id}";
    $option_settings = get_option($option_key, []);
    echo "<p><strong>Options Table ({$option_key}):</strong></p>";
    echo "<pre>" . print_r($option_settings, true) . "</pre>";
    
    // Simulate template loading logic
    echo "<h3>Template Loading Simulation:</h3>";
    
    $form_settings = [];
    $found_settings = false;
    
    // Method 1: Try post meta FIRST
    if ($post_meta && isset($post_meta['mailchimp'])) {
        $form_settings = $post_meta['mailchimp'];
        $found_settings = true;
        echo "<p><strong>✅ Settings loaded from post meta</strong></p>";
    }
    
    // Method 2: Try custom meta table as fallback
    if (!$found_settings && $meta_value) {
        $integration_settings = json_decode($meta_value, true);
        if ($integration_settings && isset($integration_settings['mailchimp'])) {
            $form_settings = $integration_settings['mailchimp'];
            $found_settings = true;
            echo "<p><strong>✅ Settings loaded from custom meta table</strong></p>";
        }
    }
    
    // Method 3: Try options table as final fallback
    if (!$found_settings && !empty($option_settings)) {
        $form_settings = $option_settings;
        $found_settings = true;
        echo "<p><strong>✅ Settings loaded from options table</strong></p>";
    }
    
    if (!$found_settings) {
        echo "<p><strong>❌ No settings found for form ID {$form_id}</strong></p>";
    } else {
        echo "<p><strong>✅ Final loaded settings for form ID {$form_id}:</strong></p>";
        echo "<pre>" . print_r($form_settings, true) . "</pre>";
        
        // Check specific fields
        echo "<p><strong>Field Mapping:</strong> " . (isset($form_settings['field_mapping']) ? count($form_settings['field_mapping']) . ' mappings' : 'No mappings') . "</p>";
        echo "<p><strong>Audience ID:</strong> " . ($form_settings['audience_id'] ?? 'Not set') . "</p>";
        echo "<p><strong>Enabled:</strong> " . ($form_settings['enabled'] ? 'Yes' : 'No') . "</p>";
    }
    
    echo "<hr>";
}

// Test URL parameter detection
echo "<h2>=== URL PARAMETER TEST ===</h2>";

// Simulate different URL scenarios
$test_urls = [
    "?page=mavlers-contact-forms&action=edit&id=1",
    "?page=mavlers-contact-forms&action=edit&id=2", 
    "?page=mavlers-contact-forms&action=edit&id=3"
];

foreach ($test_urls as $test_url) {
    echo "<h3>Testing URL: {$test_url}</h3>";
    
    // Parse the URL
    parse_str(parse_url($test_url, PHP_URL_QUERY), $query_params);
    
    $detected_form_id = 0;
    
    // Try multiple ways to get the form ID (same as template)
    if (isset($query_params['id']) && $query_params['id']) {
        $detected_form_id = intval($query_params['id']);
        echo "<p><strong>Form ID from \$_GET[id]:</strong> {$detected_form_id}</p>";
    } elseif (isset($query_params['form_id']) && $query_params['form_id']) {
        $detected_form_id = intval($query_params['form_id']);
        echo "<p><strong>Form ID from \$_GET[form_id]:</strong> {$detected_form_id}</p>";
    } elseif (isset($GLOBALS['mavlers_cf_current_form_id'])) {
        $detected_form_id = intval($GLOBALS['mavlers_cf_current_form_id']);
        echo "<p><strong>Form ID from global:</strong> {$detected_form_id}</p>";
    } else {
        echo "<p><strong>❌ No form ID found from any source</strong></p>";
    }
    
    if ($detected_form_id) {
        // Check if settings exist for this form
        $post_meta = get_post_meta($detected_form_id, '_mavlers_cf_integrations', true);
        $has_settings = $post_meta && isset($post_meta['mailchimp']);
        
        echo "<p><strong>Settings exist for form {$detected_form_id}:</strong> " . ($has_settings ? '✅ Yes' : '❌ No') . "</p>";
        
        if ($has_settings) {
            echo "<p><strong>Settings:</strong></p>";
            echo "<pre>" . print_r($post_meta['mailchimp'], true) . "</pre>";
        }
    }
    
    echo "<hr>";
}

// Test JavaScript output simulation
echo "<h2>=== JAVASCRIPT OUTPUT SIMULATION ===</h2>";

foreach ($form_ids as $form_id) {
    echo "<h3>Form ID: {$form_id}</h3>";
    
    // Simulate the template loading logic
    $form_settings = [];
    $post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
    if ($post_meta && isset($post_meta['mailchimp'])) {
        $form_settings = $post_meta['mailchimp'];
    }
    
    // Add form-specific identifier
    $form_settings['form_id'] = $form_id;
    $form_settings['timestamp'] = time();
    
    echo "<p><strong>JavaScript output for form {$form_id}:</strong></p>";
    echo "<script>";
    echo "console.log('=== FORM {$form_id} SETTINGS ===');";
    echo "window.mailchimpFormSettings = " . json_encode($form_settings) . ";";
    echo "window.mailchimpFormId = {$form_id};";
    echo "console.log('Form settings:', window.mailchimpFormSettings);";
    echo "console.log('Form ID:', window.mailchimpFormId);";
    echo "console.log('Field mappings:', window.mailchimpFormSettings.field_mapping);";
    echo "console.log('=== END FORM {$form_id} ===');";
    echo "</script>";
    
    echo "<p><strong>JSON Output:</strong></p>";
    echo "<pre>" . json_encode($form_settings, JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<hr>";
}

echo "<h2>=== RECOMMENDATIONS ===</h2>";

echo "<p><strong>If forms 2 and 3 have no settings:</strong></p>";
echo "<ol>";
echo "<li>Save settings for form 2 and 3 first</li>";
echo "<li>Check if the form IDs are being detected correctly</li>";
echo "<li>Verify the database has the correct data</li>";
echo "</ol>";

echo "<p><strong>If forms 2 and 3 have settings but aren't loading:</strong></p>";
echo "<ol>";
echo "<li>Check the browser console for JavaScript errors</li>";
echo "<li>Verify the form ID is being passed correctly</li>";
echo "<li>Check if the template is loading the right form ID</li>";
echo "</ol>";

echo "<p><strong>Debug steps:</strong></p>";
echo "<ol>";
echo "<li>Go to form 2 edit page and check the debug output</li>";
echo "<li>Look for 'Form ID' in the debug information</li>";
echo "<li>Check if 'Form Settings' shows any data</li>";
echo "<li>Use the 'Debug Form Settings' button in the interface</li>";
echo "</ol>";

echo "<p><strong>Test complete!</strong></p>";
?> 