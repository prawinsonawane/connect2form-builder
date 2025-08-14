<?php
/**
 * Test Integration Loading
 * 
 * Simple test to check if the Mailchimp integration is being loaded
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
if (!isset($_GET['test']) || $_GET['test'] !== 'integration_loading') {
    return;
}

echo '<div style="background: #e8f5e8; padding: 20px; margin: 20px; border: 1px solid #4caf50;">';
echo '<h2>🔍 Test Integration Loading</h2>';

// Test 1: Check if Plugin class exists
echo '<h3>Test 1: Plugin Class</h3>';
if (class_exists('MavlersCF\Integrations\Core\Plugin')) {
    echo '<p style="color: green;">✓ Plugin class exists</p>';
    
    try {
        $plugin = MavlersCF\Integrations\Core\Plugin::getInstance();
        echo '<p style="color: green;">✓ Plugin instance created</p>';
        
        $registry = $plugin->getRegistry();
        echo '<p style="color: green;">✓ Registry retrieved</p>';
        
        $integrations = $registry->getAll();
        echo '<p style="color: green;">✓ Got all integrations: ' . count($integrations) . ' found</p>';
        
        foreach ($integrations as $id => $integration) {
            echo '<p>Integration: ' . $id . ' - ' . get_class($integration) . '</p>';
        }
        
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">✗ Plugin class does not exist</p>';
}

// Test 2: Check if Mailchimp integration class exists
echo '<h3>Test 2: Mailchimp Integration Class</h3>';
if (class_exists('MavlersCF\Integrations\Mailchimp\MailchimpIntegration')) {
    echo '<p style="color: green;">✓ Mailchimp integration class exists</p>';
    
    try {
        $mailchimp = new MavlersCF\Integrations\Mailchimp\MailchimpIntegration();
        echo '<p style="color: green;">✓ Mailchimp integration instance created</p>';
        
        if (method_exists($mailchimp, 'register_ajax_handlers')) {
            echo '<p style="color: green;">✓ Mailchimp has register_ajax_handlers method</p>';
            $mailchimp->register_ajax_handlers();
            echo '<p style="color: green;">✓ AJAX handlers registered</p>';
        } else {
            echo '<p style="color: red;">✗ Mailchimp does not have register_ajax_handlers method</p>';
        }
        
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ Error creating Mailchimp integration: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">✗ Mailchimp integration class does not exist</p>';
}

// Test 3: Check AJAX handlers
echo '<h3>Test 3: AJAX Handlers</h3>';
$mailchimp_ajax_actions = [
    'mailchimp_test_connection',
    'mailchimp_save_global_settings',
    'mailchimp_save_global_settings_v2',
    'mailchimp_save_form_settings',
    'mailchimp_test_save_debug'
];

foreach ($mailchimp_ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        echo '<p style="color: green;">✓ AJAX handler registered: ' . $action . '</p>';
    } else {
        echo '<p style="color: red;">✗ AJAX handler not registered: ' . $action . '</p>';
    }
}

// Test 4: Check file existence
echo '<h3>Test 4: File Existence</h3>';
$files_to_check = [
    'addons/integrations-manager/src/Integrations/Mailchimp/MailchimpIntegration.php',
    'addons/integrations-manager/src/Core/Plugin.php',
    'addons/integrations-manager/integrations-manager.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo '<p style="color: green;">✓ File exists: ' . $file . '</p>';
    } else {
        echo '<p style="color: red;">✗ File missing: ' . $file . '</p>';
    }
}

echo '</div>'; 