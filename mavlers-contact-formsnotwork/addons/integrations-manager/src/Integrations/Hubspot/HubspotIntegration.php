<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Abstracts\AbstractIntegration;
use MavlersCF\Integrations\Core\Interfaces\IntegrationInterface;
use MavlersCF\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Integration
 * 
 * Handles form submissions to HubSpot CRM
 * Supports contact creation, company association, deal creation, and workflow enrollment
 */
class HubspotIntegration extends AbstractIntegration {

    protected $id = 'hubspot';
    protected $name = 'HubSpot';
    protected $description = 'Integrate form submissions with HubSpot CRM for contact management, deal creation, and workflow automation.';
    protected $version = '2.0.0';
    protected $icon = 'dashicons-businessman';
    protected $color = '#ff7a59';

    // Component managers
    protected $custom_properties_manager;
    protected $workflow_manager;
    protected $deal_manager;
    protected $company_manager;
    protected $analytics_manager;
    protected $language_manager;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize components
        $this->init_components();
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
        
        // Add admin menu for testing
        add_action('admin_menu', [$this, 'add_admin_test_menu']);
    }

    protected function init() {
        // Defer component initialization to ensure WordPress is ready
        add_action('init', [$this, 'init_components'], 5);
        
        // Register AJAX handlers after WordPress is fully loaded
        add_action('init', [$this, 'register_ajax_handlers'], 20);
        
        // Register asset enqueuing
        add_action('admin_enqueue_scripts', [$this, 'enqueue_comprehensive_assets']);
    }

    /**
     * Add admin test menu
     */
    public function add_admin_test_menu() {
        add_submenu_page(
            'tools.php',
            'HubSpot Integration Tests',
            'HubSpot Tests',
            'manage_options',
            'hubspot-integration-tests',
            [$this, 'render_admin_test_page']
        );
    }

    /**
     * Render admin test page
     */
    public function render_admin_test_page() {
        ?>
        <div class="wrap">
            <h1>HubSpot Integration Tests</h1>
            
            <div class="notice notice-info">
                <p>Use these tests to debug and verify your HubSpot integration.</p>
            </div>
            
            <div class="card">
                <h2>Connection Tests</h2>
                <p>Test your HubSpot connection and basic functionality:</p>
                
                <button type="button" class="button button-primary" onclick="runConnectionTest()">
                    Test HubSpot Connection
                </button>
                
                <button type="button" class="button button-secondary" onclick="runGlobalSettingsTest()">
                    Test Global Settings
                </button>
            </div>
            
            <div class="card">
                <h2>Properties Tests</h2>
                <p>Test loading of HubSpot properties and objects:</p>
                
                <button type="button" class="button button-primary" onclick="runContactPropertiesTest()">
                    Test Contact Properties
                </button>
                
                <button type="button" class="button button-secondary" onclick="runDealPropertiesTest()">
                    Test Deal Properties
                </button>
                
                <button type="button" class="button button-secondary" onclick="runCompanyPropertiesTest()">
                    Test Company Properties
                </button>
                
                <button type="button" class="button button-secondary" onclick="runCustomObjectsTest()">
                    Test Custom Objects
                </button>
                
                <button type="button" class="button button-secondary" onclick="runPropertySearchTest()">
                    Search for Key Properties
                </button>
                
                <button type="button" class="button button-primary" onclick="runSandboxDebugTest()">
                    Debug Sandbox Properties
                </button>
                
                <button type="button" class="button button-secondary" onclick="runFormDebugTest()">
                    Debug Form Database
                </button>
                
                <button type="button" class="button button-secondary" onclick="runForm5SaveTest()">
                    Test Form 5 Save
                </button>
                
                <button type="button" class="button button-secondary" onclick="runHubSpotSubmissionTest()">
                    Test HubSpot Submission
                </button>
            </div>
            
            <div class="card">
                <h2>Form Settings Tests</h2>
                <p>Test form-specific settings and field mapping:</p>
                
                <button type="button" class="button button-primary" onclick="runFormSettingsTest()">
                    Test Form Settings Save
                </button>
                
                <button type="button" class="button button-secondary" onclick="runFieldMappingTest()">
                    Test Field Mapping
                </button>
                
                <button type="button" class="button button-secondary" onclick="runFormFieldsTest()">
                    Test Form Fields Load
                </button>
            </div>
            
            <div class="card">
                <h2>Advanced Tests</h2>
                <p>Advanced debugging and troubleshooting:</p>
                
                <button type="button" class="button button-secondary" onclick="runAPITest()">
                    Test API Calls
                </button>
                
                <button type="button" class="button button-secondary" onclick="runErrorLogTest()">
                    Test Error Logging
                </button>
                
                <button type="button" class="button button-secondary" onclick="runPortalTest()">
                    Test Portal ID Usage
                </button>
            </div>
            
            <div id="test-results" style="margin-top: 20px;">
                <h3>Test Results</h3>
                <div id="results-content" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; min-height: 100px;">
                    <p>Click any test button above to see results here.</p>
                </div>
            </div>
            
            <script>
            function showLoading(message) {
                document.getElementById('results-content').innerHTML = '<p><strong>Running:</strong> ' + message + '...</p>';
            }
            
            function showResults(html) {
                document.getElementById('results-content').innerHTML = html;
            }
            
            function runConnectionTest() {
                showLoading('HubSpot Connection Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_connection',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Connection Test Results:</h4>';
                            html += '<p style="color: green;">✓ Connection successful</p>';
                            html += '<p><strong>Portal ID:</strong> ' + response.data.portal_id + '</p>';
                            html += '<p><strong>Account Name:</strong> ' + response.data.account_name + '</p>';
                            showResults(html);
                        } else {
                            showResults('<h4>Connection Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Connection Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runGlobalSettingsTest() {
                showLoading('Global Settings Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_global_settings',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Global Settings Test Results:</h4>';
                            html += '<p style="color: green;">✓ Settings loaded successfully</p>';
                            html += '<p><strong>Access Token:</strong> ' + (response.data.access_token ? 'Present' : 'Missing') + '</p>';
                            html += '<p><strong>Portal ID:</strong> ' + (response.data.portal_id || 'Missing') + '</p>';
                            showResults(html);
                        } else {
                            showResults('<h4>Global Settings Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Global Settings Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runContactPropertiesTest() {
                showLoading('Contact Properties Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_contact_properties',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Contact Properties Test Results:</h4>';
                            html += '<p style="color: green;">✓ Properties loaded successfully</p>';
                            html += '<p><strong>Total Properties:</strong> ' + response.data.total_properties + '</p>';
                            html += '<p><strong>Writable Properties:</strong> ' + response.data.writable_properties + '</p>';
                            html += '<p><strong>Read-only Properties:</strong> ' + response.data.readonly_properties + '</p>';
                            
                            // Check key properties
                            let keyProps = ['email', 'firstname', 'lastname', 'phone', 'company'];
                            html += '<h5>Key Properties Status:</h5><ul>';
                            keyProps.forEach(function(prop) {
                                let found = response.data.found_key_properties.includes(prop);
                                html += '<li>' + (found ? '✓' : '✗') + ' ' + prop + '</li>';
                            });
                            html += '</ul>';
                            
                            if (response.data.missing_key_properties.length > 0) {
                                html += '<p><strong>Missing Key Properties:</strong> ' + response.data.missing_key_properties.join(', ') + '</p>';
                            }
                            
                            // Show first 20 property names for debugging
                            html += '<h5>First 20 Property Names (for debugging):</h5><ul>';
                            response.data.all_property_names.forEach(function(propName) {
                                html += '<li>' + propName + '</li>';
                            });
                            html += '</ul>';
                            
                            // Show first 10 writable properties
                            html += '<h5>First 10 Writable Properties:</h5><ul>';
                            response.data.properties.slice(0, 10).forEach(function(prop) {
                                html += '<li>' + prop.name + ' - ' + prop.label + ' (' + prop.type + ')</li>';
                            });
                            html += '</ul>';
                            
                            showResults(html);
                        } else {
                            showResults('<h4>Contact Properties Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Contact Properties Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runDealPropertiesTest() {
                showLoading('Deal Properties Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_deal_properties',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Deal Properties Test Results:</h4>';
                            html += '<p style="color: green;">✓ Properties loaded successfully</p>';
                            html += '<p><strong>Properties Found:</strong> ' + response.data.properties.length + '</p>';
                            
                            // Show first 10 properties
                            html += '<h5>First 10 Properties:</h5><ul>';
                            response.data.properties.slice(0, 10).forEach(function(prop) {
                                html += '<li>' + prop.name + ' - ' + prop.label + ' (' + prop.type + ')</li>';
                            });
                            html += '</ul>';
                            
                            showResults(html);
                        } else {
                            showResults('<h4>Deal Properties Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Deal Properties Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runCompanyPropertiesTest() {
                showLoading('Company Properties Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_company_properties',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Company Properties Test Results:</h4>';
                            html += '<p style="color: green;">✓ Properties loaded successfully</p>';
                            html += '<p><strong>Properties Found:</strong> ' + response.data.properties.length + '</p>';
                            
                            // Show first 10 properties
                            html += '<h5>First 10 Properties:</h5><ul>';
                            response.data.properties.slice(0, 10).forEach(function(prop) {
                                html += '<li>' + prop.name + ' - ' + prop.label + ' (' + prop.type + ')</li>';
                            });
                            html += '</ul>';
                            
                            showResults(html);
                        } else {
                            showResults('<h4>Company Properties Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Company Properties Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runCustomObjectsTest() {
                showLoading('Custom Objects Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_custom_objects',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Custom Objects Test Results:</h4>';
                            html += '<p style="color: green;">✓ Objects loaded successfully</p>';
                            html += '<p><strong>Custom Objects Found:</strong> ' + response.data.custom_object_count + '</p>';
                            html += '<p><strong>Total Schemas:</strong> ' + response.data.total_schemas + '</p>';
                            
                            if (response.data.objects && response.data.objects.length > 0) {
                                html += '<h5>Custom Objects:</h5><ul>';
                                response.data.objects.forEach(function(obj) {
                                    html += '<li><strong>' + obj.name + '</strong> - ' + obj.label + ' (FQN: ' + obj.fullyQualifiedName + ')</li>';
                                });
                                html += '</ul>';
                            } else {
                                html += '<p>No custom objects found (this might be normal).</p>';
                            }
                            
                            if (response.data.all_schemas && response.data.all_schemas.length > 0) {
                                html += '<h5>All Available Schemas:</h5><ul>';
                                response.data.all_schemas.forEach(function(schema) {
                                    html += '<li>' + schema.name + ' (' + schema.objectType + ') - FQN: ' + schema.fullyQualifiedName + '</li>';
                                });
                                html += '</ul>';
                            }
                            
                            showResults(html);
                        } else {
                            showResults('<h4>Custom Objects Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Custom Objects Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runFormSettingsTest() {
                showLoading('Form Settings Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_form_settings',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>',
                        form_id: 5
                    },
                    success: function(response) {
                        if (response.success) {
                            showResults('<h4>Form Settings Test Results:</h4><p style="color: green;">✓ ' + response.data + '</p>');
                        } else {
                            showResults('<h4>Form Settings Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Form Settings Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runFieldMappingTest() {
                showLoading('Field Mapping Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_field_mapping',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>',
                        form_id: 5
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Field Mapping Test Results:</h4>';
                            html += '<p style="color: green;">✓ Field mapping loaded successfully</p>';
                            html += '<p><strong>Form Fields:</strong> ' + response.data.form_fields.length + '</p>';
                            html += '<p><strong>HubSpot Properties:</strong> ' + response.data.hubspot_properties.length + '</p>';
                            showResults(html);
                        } else {
                            showResults('<h4>Field Mapping Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Field Mapping Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runFormFieldsTest() {
                showLoading('Form Fields Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_form_fields',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>',
                        form_id: 5
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Form Fields Test Results:</h4>';
                            html += '<p style="color: green;">✓ Form fields loaded successfully</p>';
                            
                            const fields = response.data.fields;
                            const fieldsArray = Array.isArray(fields) ? fields : Object.values(fields);
                            
                            html += '<p><strong>Fields Found:</strong> ' + fieldsArray.length + '</p>';
                            
                            html += '<h5>Form Fields:</h5><ul>';
                            fieldsArray.forEach(function(field) {
                                html += '<li>' + (field.name || field.id) + ' - ' + field.label + '</li>';
                            });
                            html += '</ul>';
                            
                            showResults(html);
                        } else {
                            showResults('<h4>Form Fields Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Form Fields Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runAPITest() {
                showLoading('API Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_api',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>API Test Results:</h4>';
                            html += '<p style="color: green;">✓ API calls successful</p>';
                            html += '<p><strong>Portal ID:</strong> ' + response.data.portal_id + '</p>';
                            html += '<p><strong>API Endpoints Tested:</strong> ' + response.data.endpoints.join(', ') + '</p>';
                            showResults(html);
                        } else {
                            showResults('<h4>API Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>API Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runErrorLogTest() {
                showLoading('Error Log Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_error_log',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Error Log Test Results:</h4>';
                            html += '<p style="color: green;">✓ Error logging working</p>';
                            html += '<p><strong>Test Message:</strong> ' + response.data.message + '</p>';
                            showResults(html);
                        } else {
                            showResults('<h4>Error Log Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Error Log Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runPortalTest() {
                showLoading('Portal ID Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_portal',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Portal ID Test Results:</h4>';
                            html += '<p style="color: green;">✓ Portal ID working correctly</p>';
                            html += '<p><strong>Portal ID:</strong> ' + response.data.portal_id + '</p>';
                            html += '<p><strong>API Calls with Portal ID:</strong> ' + response.data.api_calls + '</p>';
                            showResults(html);
                        } else {
                            showResults('<h4>Portal ID Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Portal ID Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runPropertySearchTest() {
                showLoading('Property Search Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_property_search',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Property Search Test Results:</h4>';
                            html += '<p style="color: green;">✓ Property search successful</p>';
                            html += '<p><strong>Total Properties Found:</strong> ' + response.data.total_properties + '</p>';
                            
                            if (response.data.email_properties.length > 0) {
                                html += '<h5>Email Properties (' + response.data.email_properties.length + '):</h5><ul>';
                                response.data.email_properties.forEach(function(prop) {
                                    html += '<li><strong>' + prop.name + '</strong> - ' + prop.label + '</li>';
                                });
                                html += '</ul>';
                            } else {
                                html += '<h5>Email Properties:</h5><p style="color: orange;">No email properties found</p>';
                            }
                            
                            if (response.data.name_properties.length > 0) {
                                html += '<h5>Name Properties (' + response.data.name_properties.length + '):</h5><ul>';
                                response.data.name_properties.forEach(function(prop) {
                                    html += '<li><strong>' + prop.name + '</strong> - ' + prop.label + '</li>';
                                });
                                html += '</ul>';
                            } else {
                                html += '<h5>Name Properties:</h5><p style="color: orange;">No name properties found</p>';
                            }
                            
                            if (response.data.phone_properties.length > 0) {
                                html += '<h5>Phone Properties (' + response.data.phone_properties.length + '):</h5><ul>';
                                response.data.phone_properties.forEach(function(prop) {
                                    html += '<li><strong>' + prop.name + '</strong> - ' + prop.label + '</li>';
                                });
                                html += '</ul>';
                            } else {
                                html += '<h5>Phone Properties:</h5><p style="color: orange;">No phone properties found</p>';
                            }
                            
                            if (response.data.company_properties.length > 0) {
                                html += '<h5>Company Properties (' + response.data.company_properties.length + '):</h5><ul>';
                                response.data.company_properties.forEach(function(prop) {
                                    html += '<li><strong>' + prop.name + '</strong> - ' + prop.label + '</li>';
                                });
                                html += '</ul>';
                            } else {
                                html += '<h5>Company Properties:</h5><p style="color: orange;">No company properties found</p>';
                            }
                            
                            if (response.data.sample_properties && response.data.sample_properties.length > 0) {
                                html += '<h5>Sample Properties (First 20):</h5><ul>';
                                response.data.sample_properties.forEach(function(prop) {
                                    html += '<li>' + prop + '</li>';
                                });
                                html += '</ul>';
                            }
                            
                            showResults(html);
                        } else {
                            showResults('<h4>Property Search Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Property Search Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runFormDebugTest() {
                showLoading('Form Database Debug Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_form_debug',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Form Database Debug Test Results:</h4>';
                            html += '<p style="color: green;">✓ Form database debug completed</p>';
                            
                            const debug = response.data.debug_info;
                            
                            // Tables info
                            html += '<h5>Database Tables:</h5>';
                            html += '<p><strong>Forms Table Exists:</strong> ' + (debug.tables.forms_table_exists ? 'Yes' : 'No') + '</p>';
                            html += '<p><strong>Meta Table Exists:</strong> ' + (debug.tables.meta_table_exists ? 'Yes' : 'No') + '</p>';
                            
                            // All forms
                            if (debug.all_forms && debug.all_forms.length > 0) {
                                html += '<h5>All Forms in Database:</h5><ul>';
                                debug.all_forms.forEach(function(form) {
                                    html += '<li><strong>ID ' + form.id + ':</strong> ' + form.form_title + ' (Status: ' + form.status + ')</li>';
                                });
                                html += '</ul>';
                            }
                            
                            // Form 1 details
                            if (debug.form_1) {
                                html += '<h5>Form ID 1 Details:</h5>';
                                html += '<p><strong>Exists:</strong> ' + (debug.form_1.exists ? 'Yes' : 'No') + '</p>';
                                if (debug.form_1.form_data) {
                                    html += '<p><strong>Title:</strong> ' + debug.form_1.form_data.form_title + '</p>';
                                    html += '<p><strong>Status:</strong> ' + debug.form_1.form_data.status + '</p>';
                                }
                                html += '<p><strong>Meta Value:</strong> ' + (debug.form_1.meta_value || 'None') + '</p>';
                                if (debug.form_1.meta_decoded) {
                                    html += '<p><strong>HubSpot Settings:</strong> ' + JSON.stringify(debug.form_1.meta_decoded.hubspot || {}) + '</p>';
                                }
                            }
                            
                            // Form 5 details
                            if (debug.form_5) {
                                html += '<h5>Form ID 5 Details:</h5>';
                                html += '<p><strong>Exists:</strong> ' + (debug.form_5.exists ? 'Yes' : 'No') + '</p>';
                                if (debug.form_5.form_data) {
                                    html += '<p><strong>Title:</strong> ' + debug.form_5.form_data.form_title + '</p>';
                                    html += '<p><strong>Status:</strong> ' + debug.form_5.form_data.status + '</p>';
                                }
                                html += '<p><strong>Meta Value:</strong> ' + (debug.form_5.meta_value || 'None') + '</p>';
                                if (debug.form_5.meta_decoded) {
                                    html += '<p><strong>HubSpot Settings:</strong> ' + JSON.stringify(debug.form_5.meta_decoded.hubspot || {}) + '</p>';
                                }
                            }
                            
                            // Form loading tests
                            html += '<h5>Form Loading Tests:</h5>';
                            html += '<p><strong>Form 1 Exists:</strong> ' + (debug.form_loading_tests.form_1_exists ? 'Yes' : 'No') + '</p>';
                            html += '<p><strong>Form 5 Exists:</strong> ' + (debug.form_loading_tests.form_5_exists ? 'Yes' : 'No') + '</p>';
                            html += '<p><strong>Form 1 Settings:</strong> ' + JSON.stringify(debug.form_loading_tests.form_1_settings) + '</p>';
                            html += '<p><strong>Form 5 Settings:</strong> ' + JSON.stringify(debug.form_loading_tests.form_5_settings) + '</p>';
                            
                            // Template loading simulation
                            html += '<h5>Template Loading Simulation:</h5>';
                            if (debug.template_loading_simulation) {
                                Object.keys(debug.template_loading_simulation).forEach(function(formKey) {
                                    const formData = debug.template_loading_simulation[formKey];
                                    html += '<h6>' + formKey + ':</h6>';
                                    html += '<p><strong>Settings Found:</strong> ' + (formData.settings_found ? 'Yes' : 'No') + '</p>';
                                    html += '<p><strong>Settings:</strong> ' + JSON.stringify(formData.settings) + '</p>';
                                    html += '<p><strong>Field Mapping:</strong> ' + JSON.stringify(formData.field_mapping) + '</p>';
                                });
                            }
                            
                            // Form fields tests
                            html += '<h5>Form Fields Tests:</h5>';
                            if (debug.form_fields_tests) {
                                Object.keys(debug.form_fields_tests).forEach(function(formKey) {
                                    const formData = debug.form_fields_tests[formKey];
                                    html += '<h6>' + formKey + ':</h6>';
                                    html += '<p><strong>Fields Count:</strong> ' + Object.keys(formData.fields).length + '</p>';
                                    html += '<p><strong>Fields:</strong> ' + JSON.stringify(formData.fields) + '</p>';
                                });
                            }
                            
                            showResults(html);
                        } else {
                            showResults('<h4>Form Database Debug Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Form Database Debug Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runForm5SaveTest() {
                showLoading('Form 5 Save Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_save_form_settings',
                        nonce: '<?php echo wp_create_nonce('mavlers_cf_nonce'); ?>',
                        form_id: 5,
                        object_type: 'contacts',
                        action_type: 'create',
                        workflow_enabled: false,
                        field_mapping: {}
                    },
                    success: function(response) {
                        if (response.success) {
                            showResults('<h4>Form 5 Save Test Results:</h4><p style="color: green;">✓ Form 5 settings saved successfully</p>');
                        } else {
                            showResults('<h4>Form 5 Save Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Form 5 Save Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runHubSpotSubmissionTest() {
                showLoading('HubSpot Submission Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_submission',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>',
                        form_id: 5,
                        test_data: {
                            'field_1753067401669': 'John',
                            'field_1753067415523': 'Doe',
                            'field_1753067421341': 'john.doe@example.com',
                            'field_1753067427836': '1234567890',
                            'field_1753067432908': 'Test message from form submission'
                        }
                    },
                    success: function(response) {
                        if (response.success) {
                            showResults('<h4>HubSpot Submission Test Results:</h4><p style="color: green;">✓ ' + response.data.message + '</p><pre>' + JSON.stringify(response.data, null, 2) + '</pre>');
                        } else {
                            showResults('<h4>HubSpot Submission Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>HubSpot Submission Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            
            function runSandboxDebugTest() {
                showLoading('Sandbox Debug Test');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hubspot_test_sandbox_debug',
                        nonce: '<?php echo wp_create_nonce('hubspot_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '<h4>Sandbox Debug Test Results:</h4>';
                            html += '<p style="color: green;">✓ Sandbox debug test successful</p>';
                            html += '<p><strong>Portal ID:</strong> ' + response.data.portal_id + '</p>';
                            
                            const debug = response.data.debug_info;
                            
                            if (debug.without_portal_id) {
                                html += '<h5>API Call WITHOUT Portal ID:</h5>';
                                html += '<p><strong>Status:</strong> ' + debug.without_portal_id.status + '</p>';
                                html += '<p><strong>Properties Found:</strong> ' + debug.without_portal_id.count + '</p>';
                            }
                            
                            if (debug.with_portal_id) {
                                html += '<h5>API Call WITH Portal ID:</h5>';
                                html += '<p><strong>Status:</strong> ' + debug.with_portal_id.status + '</p>';
                                html += '<p><strong>Properties Found:</strong> ' + debug.with_portal_id.count + '</p>';
                            }
                            
                            if (debug.with_limit) {
                                html += '<h5>API Call WITH LIMIT=1000:</h5>';
                                html += '<p><strong>Status:</strong> ' + debug.with_limit.status + '</p>';
                                html += '<p><strong>Properties Found:</strong> ' + debug.with_limit.count + '</p>';
                            }
                            
                            if (debug.with_pagination) {
                                html += '<h5>API Call WITH PAGINATION:</h5>';
                                html += '<p><strong>Status:</strong> ' + debug.with_pagination.status + '</p>';
                                html += '<p><strong>Properties Found:</strong> ' + debug.with_pagination.count + '</p>';
                                if (debug.with_pagination.paging) {
                                    html += '<p><strong>Paging Info:</strong> ' + JSON.stringify(debug.with_pagination.paging) + '</p>';
                                }
                            }
                            
                            if (debug.account_info) {
                                html += '<h5>Account Information:</h5>';
                                html += '<p><strong>Status:</strong> ' + debug.account_info.status + '</p>';
                                if (debug.account_info.data) {
                                    html += '<p><strong>Hub ID:</strong> ' + (debug.account_info.data.hub_id || 'N/A') + '</p>';
                                    html += '<p><strong>User:</strong> ' + (debug.account_info.data.user || 'N/A') + '</p>';
                                    html += '<p><strong>Hub Domain:</strong> ' + (debug.account_info.data.hub_domain || 'N/A') + '</p>';
                                }
                            }
                            
                            // Summary
                            html += '<h5>Summary:</h5>';
                            html += '<p><strong>Expected Properties (UI):</strong> 1,221</p>';
                            html += '<p><strong>Found Properties (API):</strong> ' + (debug.with_portal_id ? debug.with_portal_id.count : 'Unknown') + '</p>';
                            html += '<p><strong>Difference:</strong> ' + (1221 - (debug.with_portal_id ? debug.with_portal_id.count : 0)) + ' properties missing</p>';
                            
                            showResults(html);
                        } else {
                            showResults('<h4>Sandbox Debug Test Results:</h4><p style="color: red;">✗ ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        showResults('<h4>Sandbox Debug Test Results:</h4><p style="color: red;">✗ AJAX request failed</p>');
                    }
                });
            }
            </script>
        </div>
        <?php
    }

    /**
     * Initialize HubSpot-specific components
     */
    public function init_components() {
        try {
            // Initialize component managers with error handling
            if (class_exists('MavlersCF\Integrations\Hubspot\CustomPropertiesManager')) {
                $this->custom_properties_manager = new \MavlersCF\Integrations\Hubspot\CustomPropertiesManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\WorkflowManager')) {
                $this->workflow_manager = new \MavlersCF\Integrations\Hubspot\WorkflowManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\DealManager')) {
                $this->deal_manager = new \MavlersCF\Integrations\Hubspot\DealManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\CompanyManager')) {
                $this->company_manager = new \MavlersCF\Integrations\Hubspot\CompanyManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Hubspot\AnalyticsManager')) {
                $this->analytics_manager = new \MavlersCF\Integrations\Hubspot\AnalyticsManager();
            }
            
            if (class_exists('MavlersCF\Integrations\Core\Services\LanguageManager')) {
                $this->language_manager = new \MavlersCF\Integrations\Core\Services\LanguageManager();
            }
            
            // Register form submission hook listener
            add_action('mavlers_cf_after_submission', [$this, 'handle_form_submission'], 10, 2);
            
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("HubspotIntegration: Error during component initialization: " . $e->getMessage());
            }
            
            // Even if component initialization fails, register the form submission hook
            add_action('mavlers_cf_after_submission', [$this, 'handle_form_submission'], 10, 2);
        }
    }

    /**
     * Register AJAX handlers for HubSpot integration
     */
    public function register_ajax_handlers() {
        // Test response functions
        add_action('wp_ajax_mavlers_cf_hubspot_test_response', [$this, 'ajax_test_response']);
        
        // Admin test functions
        add_action('wp_ajax_hubspot_test_connection', [$this, 'ajax_test_connection_admin']);
        add_action('wp_ajax_hubspot_test_global_settings', [$this, 'ajax_test_global_settings_admin']);
        add_action('wp_ajax_hubspot_test_contact_properties', [$this, 'ajax_test_contact_properties_admin']);
        add_action('wp_ajax_hubspot_test_deal_properties', [$this, 'ajax_test_deal_properties_admin']);
        add_action('wp_ajax_hubspot_test_company_properties', [$this, 'ajax_test_company_properties_admin']);
        add_action('wp_ajax_hubspot_test_custom_objects', [$this, 'ajax_test_custom_objects_admin']);
        add_action('wp_ajax_hubspot_test_form_settings', [$this, 'ajax_test_form_settings_admin']);
        add_action('wp_ajax_hubspot_test_field_mapping', [$this, 'ajax_test_field_mapping_admin']);
        add_action('wp_ajax_hubspot_test_form_fields', [$this, 'ajax_test_form_fields_admin']);
        add_action('wp_ajax_hubspot_test_api', [$this, 'ajax_test_api_admin']);
        add_action('wp_ajax_hubspot_test_error_log', [$this, 'ajax_test_error_log_admin']);
        add_action('wp_ajax_hubspot_test_portal', [$this, 'ajax_test_portal_admin']);
        add_action('wp_ajax_hubspot_test_property_search', [$this, 'ajax_test_property_search_admin']);
        add_action('wp_ajax_hubspot_test_sandbox_debug', [$this, 'ajax_test_sandbox_debug_admin']);
        add_action('wp_ajax_hubspot_test_form_debug', [$this, 'ajax_test_form_debug_admin']);
        
        // Test connection
        add_action('wp_ajax_mavlers_cf_hubspot_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_hubspot_test_connection', [$this, 'ajax_test_connection']);
        
        // Get HubSpot objects (contacts, companies, deals)
        add_action('wp_ajax_mavlers_cf_hubspot_get_contacts', [$this, 'ajax_get_contacts']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_companies', [$this, 'ajax_get_companies']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_deals', [$this, 'ajax_get_deals']);
        
        // Get properties and workflows
        add_action('wp_ajax_mavlers_cf_hubspot_get_custom_properties', [$this, 'ajax_get_custom_properties']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_workflows', [$this, 'ajax_get_workflows']);
        
        // Settings management
        add_action('wp_ajax_mavlers_cf_hubspot_save_global_settings', [$this, 'ajax_save_global_settings']);
        add_action('wp_ajax_hubspot_save_global_settings', [$this, 'ajax_save_global_settings']);
        add_action('wp_ajax_hubspot_save_global_settings_simple', [$this, 'ajax_save_global_settings_simple']);
        add_action('wp_ajax_hubspot_save_global_settings_v2', [$this, 'ajax_save_global_settings_v2']);
        
        // Form settings
        add_action('wp_ajax_mavlers_cf_hubspot_save_form_settings', [$this, 'ajax_save_form_settings']);
        add_action('wp_ajax_hubspot_save_form_settings', [$this, 'ajax_save_form_settings']);
        add_action('wp_ajax_mavlers_cf_save_form_settings', [$this, 'ajax_save_form_settings']);
        add_action('wp_ajax_hubspot_save_form_settings_debug', [$this, 'ajax_save_form_settings_debug']);
        
        // Field mapping
        add_action('wp_ajax_mavlers_cf_hubspot_save_field_mapping', [$this, 'ajax_save_field_mapping']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_field_mapping', [$this, 'ajax_get_field_mapping']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_mavlers_cf_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_mavlers_cf_hubspot_auto_map_fields', [$this, 'ajax_auto_map_fields']);
        
        // Custom objects
        add_action('wp_ajax_mavlers_cf_hubspot_get_custom_objects', [$this, 'ajax_get_custom_objects']);
        add_action('wp_ajax_hubspot_get_custom_objects', [$this, 'ajax_get_custom_objects']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_custom_object_properties', [$this, 'ajax_get_custom_object_properties']);
        add_action('wp_ajax_hubspot_get_custom_object_properties', [$this, 'ajax_get_custom_object_properties']);
        add_action('wp_ajax_mavlers_cf_hubspot_save_custom_object_mapping', [$this, 'ajax_save_custom_object_mapping']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_custom_object_mapping', [$this, 'ajax_get_custom_object_mapping']);
        
        // Object properties
        add_action('wp_ajax_mavlers_cf_hubspot_get_deal_properties', [$this, 'ajax_get_deal_properties']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_company_properties', [$this, 'ajax_get_company_properties']);
        add_action('wp_ajax_mavlers_cf_hubspot_get_contact_properties', [$this, 'ajax_get_contact_properties']);
        add_action('wp_ajax_hubspot_get_object_properties', [$this, 'ajax_get_contact_properties']);
        
        // Analytics
        add_action('wp_ajax_mavlers_cf_hubspot_get_analytics', [$this, 'ajax_get_analytics']);
        
        // Test functions
        add_action('wp_ajax_hubspot_test_simple', [$this, 'ajax_test_simple']);
        add_action('wp_ajax_hubspot_test_simple_v2', [$this, 'ajax_test_simple_v2']);
        add_action('wp_ajax_hubspot_test_basic', [$this, 'ajax_test_basic']);
        add_action('wp_ajax_hubspot_debug_test', [$this, 'ajax_debug_test']);
        add_action('wp_ajax_hubspot_test_submission', [$this, 'ajax_test_submission']);
    }

    /**
     * Get authentication fields for HubSpot
     */
    public function getAuthFields(): array {
        return [
            [
                'id' => 'access_token',
                'label' => $this->__('Private App Access Token'),
                'type' => 'password',
                'required' => true,
                'description' => $this->__('Enter your HubSpot Private App Access Token. Create one in HubSpot Settings > Account Setup > Integrations > Private Apps.'),
                'help_url' => 'https://developers.hubspot.com/docs/api/private-apps'
            ],
            [
                'id' => 'portal_id',
                'label' => $this->__('Portal ID'),
                'type' => 'text',
                'required' => true,
                'description' => $this->__('Your HubSpot Portal ID. Found in your HubSpot account settings.'),
                'help_url' => 'https://developers.hubspot.com/docs/api/overview'
            ]
        ];
    }

    /**
     * Test HubSpot API connection
     */
    public function testConnection(array $credentials): array {
        $access_token = $credentials['access_token'] ?? '';
        $portal_id = $credentials['portal_id'] ?? '';

        if (empty($access_token) || empty($portal_id)) {
            return [
                'success' => false,
                'error' => $this->__('Access token and Portal ID are required')
            ];
        }

        // Test basic connectivity
        $test_url = "https://api.hubapi.com/crm/v3/objects/contacts";
        $response = wp_remote_get($test_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => $this->__('Connection successful!'),
                'data' => [
                    'portal_id' => $portal_id,
                    'api_version' => 'v3'
                ]
            ];
        } elseif ($status_code === 401) {
            return [
                'success' => false,
                'error' => $this->__('Invalid access token. Please check your Private App Access Token.')
            ];
        } elseif ($status_code === 403) {
            return [
                'success' => false,
                'error' => $this->__('Access denied. Please check your Private App permissions.')
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "API Error ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get available actions for HubSpot
     */
    public function getAvailableActions(): array {
        return [
            'create_contact' => [
                'label' => $this->__('Create Contact'),
                'description' => $this->__('Create a new contact in HubSpot CRM')
            ],
            'update_contact' => [
                'label' => $this->__('Update Contact'),
                'description' => $this->__('Update existing contact information')
            ],
            'create_deal' => [
                'label' => $this->__('Create Deal'),
                'description' => $this->__('Create a new deal and associate with contact')
            ],
            'update_deal' => [
                'label' => $this->__('Update Deal'),
                'description' => $this->__('Update existing deal information')
            ],
            'create_custom_object' => [
                'label' => $this->__('Create Custom Object'),
                'description' => $this->__('Create a new custom object record')
            ],
            'update_custom_object' => [
                'label' => $this->__('Update Custom Object'),
                'description' => $this->__('Update existing custom object record')
            ],
            'create_multiple_objects' => [
                'label' => $this->__('Create Multiple Objects'),
                'description' => $this->__('Create contact, deal, and custom objects simultaneously')
            ],
            'enroll_workflow' => [
                'label' => $this->__('Enroll in Workflow'),
                'description' => $this->__('Enroll contact in HubSpot workflow')
            ],
            'associate_company' => [
                'label' => $this->__('Associate Company'),
                'description' => $this->__('Associate contact with existing company')
            ],
            'associate_objects' => [
                'label' => $this->__('Associate Objects'),
                'description' => $this->__('Associate contact with deals and custom objects')
            ]
        ];
    }

    /**
     * Get form-specific settings fields
     */
    public function getFormSettingsFields(): array {
        return [
            [
                'id' => 'contact_enabled',
                'label' => $this->__('Create/Update Contact'),
                'type' => 'checkbox',
                'description' => $this->__('Create or update contact in HubSpot'),
                'default' => true
            ],
            [
                'id' => 'deal_enabled',
                'label' => $this->__('Create Deal'),
                'type' => 'checkbox',
                'description' => $this->__('Create a new deal for this submission'),
                'default' => false
            ],
            [
                'id' => 'deal_update_enabled',
                'label' => $this->__('Update Existing Deal'),
                'type' => 'checkbox',
                'description' => $this->__('Update existing deal instead of creating new one'),
                'default' => false
            ],
            [
                'id' => 'custom_objects_enabled',
                'label' => $this->__('Enable Custom Objects'),
                'type' => 'checkbox',
                'description' => $this->__('Enable custom object creation and updates'),
                'default' => false
            ],
            [
                'id' => 'custom_objects_config',
                'label' => $this->__('Custom Objects Configuration'),
                'type' => 'custom_objects_config',
                'description' => $this->__('Configure multiple custom objects'),
                'depends_on' => 'custom_objects_enabled'
            ],
            [
                'id' => 'workflow_enabled',
                'label' => $this->__('Enroll in Workflow'),
                'type' => 'checkbox',
                'description' => $this->__('Enroll contact in HubSpot workflow'),
                'default' => false
            ],
            [
                'id' => 'company_enabled',
                'label' => $this->__('Associate Company'),
                'type' => 'checkbox',
                'description' => $this->__('Associate contact with company'),
                'default' => false
            ],
            [
                'id' => 'deal_pipeline',
                'label' => $this->__('Deal Pipeline'),
                'type' => 'select',
                'description' => $this->__('Select pipeline for new deals'),
                'options' => 'dynamic',
                'depends_on' => 'deal_enabled'
            ],
            [
                'id' => 'deal_stage',
                'label' => $this->__('Deal Stage'),
                'type' => 'select',
                'description' => $this->__('Select stage for new deals'),
                'options' => 'dynamic',
                'depends_on' => 'deal_enabled'
            ],
            [
                'id' => 'workflow_id',
                'label' => $this->__('Workflow'),
                'type' => 'select',
                'description' => $this->__('Select workflow to enroll contact in'),
                'options' => 'dynamic',
                'depends_on' => 'workflow_enabled'
            ],
            [
                'id' => 'company_id',
                'label' => $this->__('Company'),
                'type' => 'select',
                'description' => $this->__('Select company to associate with contact'),
                'options' => 'dynamic',
                'depends_on' => 'company_enabled'
            ]
        ];
    }

    /**
     * Get global settings fields
     */
    public function getSettingsFields(): array {
        return [
            [
                'id' => 'enable_analytics',
                'label' => $this->__('Analytics Tracking'),
                'type' => 'checkbox',
                'description' => $this->__('Track integration performance and analytics'),
                'default' => true
            ],
            [
                'id' => 'enable_webhooks',
                'label' => $this->__('Webhook Sync'),
                'type' => 'checkbox',
                'description' => $this->__('Enable webhook synchronization for real-time updates'),
                'default' => false
            ],
            [
                'id' => 'batch_processing',
                'label' => $this->__('Batch Processing'),
                'type' => 'checkbox',
                'description' => $this->__('Process submissions in batches for better performance'),
                'default' => true
            ]
        ];
    }

    /**
     * Get enhanced field mapping for HubSpot
     */
    public function getFieldMapping(string $action): array {
        $base_mapping = [
            'email' => [
                'label' => $this->__('Email Address'),
                'required' => true,
                'type' => 'email',
                'hubspot_property' => 'email'
            ],
            'firstname' => [
                'label' => $this->__('First Name'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'firstname'
            ],
            'lastname' => [
                'label' => $this->__('Last Name'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'lastname'
            ],
            'phone' => [
                'label' => $this->__('Phone Number'),
                'required' => false,
                'type' => 'phone',
                'hubspot_property' => 'phone'
            ],
            'company' => [
                'label' => $this->__('Company'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'company'
            ],
            'website' => [
                'label' => $this->__('Website'),
                'required' => false,
                'type' => 'url',
                'hubspot_property' => 'website'
            ],
            'address' => [
                'label' => $this->__('Address'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'address'
            ],
            'city' => [
                'label' => $this->__('City'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'city'
            ],
            'state' => [
                'label' => $this->__('State/Province'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'state'
            ],
            'zip' => [
                'label' => $this->__('ZIP/Postal Code'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'zip'
            ],
            'country' => [
                'label' => $this->__('Country'),
                'required' => false,
                'type' => 'text',
                'hubspot_property' => 'country'
            ]
        ];

        return $base_mapping;
    }

    /**
     * Validate HubSpot settings
     */
    public function validateSettings(array $settings): array {
        $errors = array();

        if (empty($settings['access_token'])) {
            $errors[] = 'Access token is required';
        }
        
        if (empty($settings['portal_id'])) {
            $errors[] = 'Portal ID is required';
        }
        
        return $errors;
    }

    /**
     * Process form submission to HubSpot
     */
    public function processSubmission(int $submission_id, array $form_data, array $settings = []): array {
        // If settings are not provided, try to get them from form data or form ID
        if (empty($settings)) {
            $form_id = $form_data['form_id'] ?? 0;
            if ($form_id) {
                // Get form-specific settings
                $form_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
                $settings = $form_settings['hubspot'] ?? [];
            }
        }
        
        // Check if integration is enabled for this form
        if (empty($settings['enabled'])) {
            return [
                'success' => false,
                'error' => 'HubSpot integration not enabled for this form'
            ];
        }
        
        // Check if globally connected
        if (!$this->is_globally_connected()) {
            return [
                'success' => false,
                'error' => 'HubSpot not globally configured'
            ];
        }
        
        // Process submission immediately
        return $this->process_submission_immediate($submission_id, $form_data, $settings);
    }

    /**
     * Process submission immediately (no batch processing)
     */
    private function process_submission_immediate(int $submission_id, array $form_data, array $settings): array {
        // Get global settings
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';

        // Check if we have the required credentials
        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'Access token not configured'
            ];
        }

        if (empty($portal_id)) {
            return [
                'success' => false,
                'error' => 'Portal ID not configured'
            ];
        }

        // Map form data to HubSpot format
        $mapped_data = $this->enhanced_map_form_data($form_data, $settings);
        
        if (empty($mapped_data)) {
            return [
                'success' => false,
                'error' => 'No data to send'
            ];
        }

        // Create or update contact
        $contact_result = $this->createOrUpdateContact($mapped_data, $settings, $access_token, $portal_id, $submission_id);
        
        if (!$contact_result['success']) {
            return $contact_result;
        }

        // Process additional actions based on settings
        $result = array(
            'success' => true,
            'message' => 'Contact created/updated successfully',
            'contact_id' => $contact_result['contact_id'] ?? null
        );

        // Create deal if enabled
        if (!empty($settings['create_deal']) && !empty($settings['deal_pipeline'])) {
            $deal_result = $this->createDeal($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            if ($deal_result['success']) {
                $result['deal_id'] = $deal_result['deal_id'] ?? null;
            }
        }

        // Enroll in workflow if enabled
        if (!empty($settings['enroll_workflow']) && !empty($settings['workflow_id'])) {
            $workflow_result = $this->enrollInWorkflow($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            if ($workflow_result['success']) {
                $result['workflow_enrolled'] = true;
            }
        }

        // Process custom objects if enabled
        if (!empty($settings['process_custom_objects'])) {
            $custom_objects_result = $this->processCustomObjects($mapped_data, $settings, $access_token, $portal_id, $submission_id);
            if ($custom_objects_result['success']) {
                $result['custom_objects_processed'] = true;
            }
        }

        return $result;
    }

    /**
     * Enhanced form data mapping for HubSpot
     */
    private function enhanced_map_form_data(array $form_data, array $settings): array {
        // Use nested fields data if available
        $field_data = $form_data['fields'] ?? $form_data;
        
        // Get field mapping from settings
        $field_mapping = $settings['field_mapping'] ?? [];
        
        // If no field mapping in settings, try to get enhanced mapping
        if (empty($field_mapping)) {
            $form_id = $form_data['form_id'] ?? 0;
            if ($form_id) {
                $field_mapping = $this->get_enhanced_field_mapping($form_id);
            }
        }
        
        $mapped = array();
        
        // Use field mapping if available
        if (!empty($field_mapping)) {
            foreach ($field_mapping as $form_field => $hubspot_field) {
                if (isset($field_data[$form_field]) && !empty($field_data[$form_field])) {
                    $mapped[$hubspot_field] = $field_data[$form_field];
                }
            }
        } else {
            // Fallback to basic field mapping
            $mapped = $this->mapFormDataToHubspot($form_data, $settings);
        }
        
        return $mapped;
    }

    /**
     * Basic form data mapping for HubSpot (fallback)
     */
    private function mapFormDataToHubspot(array $form_data, array $settings): array {
        $mapped = array();
        
        // Basic field mapping
        $field_map = array(
            'email' => 'email',
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'phone' => 'phone',
            'company' => 'company'
        );
        
        // Map basic fields
        foreach ($field_map as $form_field => $hubspot_field) {
            if (isset($form_data[$form_field]) && !empty($form_data[$form_field])) {
                $mapped[$hubspot_field] = $form_data[$form_field];
            }
        }
        
        // Look for email by pattern matching
        foreach ($form_data as $key => $value) {
            if (strpos(strtolower($key), 'email') !== false && !empty($value) && !isset($mapped['email'])) {
                $mapped['email'] = $value;
            }
        }
        
        // Look for first name by pattern matching
        foreach ($form_data as $key => $value) {
            if ((strpos(strtolower($key), 'first') !== false || strpos(strtolower($key), 'fname') !== false) && !empty($value) && !isset($mapped['firstname'])) {
                $mapped['firstname'] = $value;
            }
        }
        
        return $mapped;
    }

    /**
     * Create or update contact in HubSpot
     */
    private function createOrUpdateContact(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        // Find email address in the data
        $email = '';
        foreach ($data as $key => $value) {
            if (strpos(strtolower($key), 'email') !== false && !empty($value)) {
                $email = $value;
                break;
            }
        }

        if (empty($email)) {
            return [
                'success' => false,
                'error' => 'No email found in data'
            ];
        }

        // Prepare contact payload
        $payload = array(
            'properties' => array()
        );

        // Add properties
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $payload['properties'][$key] = $value;
            }
        }

        // Search for existing contact
        $search_url = "https://api.hubapi.com/crm/v3/objects/contacts/search";
        $search_payload = array(
            'filterGroups' => array(
                array(
                    'filters' => array(
                        array(
                            'propertyName' => 'email',
                            'operator' => 'EQ',
                            'value' => $email
                        )
                    )
                )
            )
        );

        $search_response = wp_remote_post($search_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($search_payload),
            'timeout' => 30
        ));

        $contact_id = null;
        $method = 'POST';

        if (!is_wp_error($search_response)) {
            $search_status = wp_remote_retrieve_response_code($search_response);
            $search_result = json_decode(wp_remote_retrieve_body($search_response), true);

            if ($search_status === 200 && !empty($search_result['results'])) {
                $contact_id = $search_result['results'][0]['id'];
            }
        }

        // Create or update contact
        $url = "https://api.hubapi.com/crm/v3/objects/contacts" . ($contact_id ? "/{$contact_id}" : "");
        
        $response = wp_remote_request($url, array(
            'method' => $contact_id ? 'PATCH' : 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 200 && $status_code < 300) {
            return [
                'success' => true,
                'contact_id' => $result['id'] ?? $contact_id,
                'message' => $contact_id ? 'Contact updated successfully' : 'Contact created successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['message'] ?? 'Failed to create/update contact'
            ];
        }
    }

    /**
     * Create deal in HubSpot
     */
    private function createDeal(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: createDeal called");
        
        $pipeline_id = $settings['deal_pipeline'] ?? '';
        $stage_id = $settings['deal_stage'] ?? '';
        
        if (empty($pipeline_id) || empty($stage_id)) {
            return [
                'success' => false,
                'error' => 'Deal pipeline and stage are required'
            ];
        }
        
        $url = "https://api.hubapi.com/crm/v3/objects/deals";
        
        $properties = [
            'amount' => $data['amount'] ?? '0',
            'dealname' => $data['dealname'] ?? 'Form Submission Deal',
            'pipeline' => $pipeline_id,
            'dealstage' => $stage_id
        ];
        
        $payload = [
            'properties' => $properties
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Deal creation failed: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 201 || $status_code === 200) {
            return [
                'success' => true,
                'message' => 'Deal created successfully',
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Deal creation failed: ' . ($result['message'] ?? 'Unknown error')
            ];
        }
    }

    /**
     * Enroll contact in workflow
     */
    private function enrollInWorkflow(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: enrollInWorkflow called");
        
        $workflow_id = $settings['workflow_id'] ?? '';
        
        if (empty($workflow_id)) {
            return [
                'success' => false,
                'error' => 'Workflow ID is required'
            ];
        }
        
        $url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments/contacts/{$data['email']}";
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Workflow enrollment failed: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'message' => 'Contact enrolled in workflow successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Workflow enrollment failed'
            ];
        }
    }

    /**
     * Update existing deal
     */
    private function updateDeal(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: updateDeal called");
        
        $deal_id = $settings['deal_id'] ?? '';
        if (empty($deal_id)) {
            return [
                'success' => false,
                'error' => 'Deal ID not specified for update'
            ];
        }
        
        $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
        
        $deal_data = [
            'properties' => $data
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($deal_data),
            'method' => 'PATCH',
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log("HubspotIntegration: updateDeal error: " . $response->get_error_message());
            return [
                'success' => false,
                'error' => 'Failed to update deal: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 200) {
            error_log("HubspotIntegration: updateDeal success: " . print_r($result, true));
            return [
                'success' => true,
                'message' => 'Deal updated successfully',
                'deal_id' => $deal_id,
                'data' => $result
            ];
        } else {
            error_log("HubspotIntegration: updateDeal failed - Status: {$status_code}, Response: {$body}");
            return [
                'success' => false,
                'error' => "Failed to update deal (HTTP {$status_code}): " . ($result['message'] ?? 'Unknown error')
            ];
        }
    }

    /**
     * Process custom objects
     */
    private function processCustomObjects(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: processCustomObjects called");
        
        $custom_objects_config = $settings['custom_objects_config'] ?? [];
        if (empty($custom_objects_config)) {
            return [
                'success' => false,
                'error' => 'No custom objects configuration found'
            ];
        }
        
        $results = [];
        
        foreach ($custom_objects_config as $object_config) {
            $object_name = $object_config['object_name'] ?? '';
            $action = $object_config['action'] ?? 'create'; // create or update
            $enabled = $object_config['enabled'] ?? false;
            
            if (!$enabled || empty($object_name)) {
                continue;
            }
            
            // Get field mapping for this object
            $form_id = $settings['form_id'] ?? 0;
            $object_mapping = $this->get_custom_object_mapping($form_id, $object_name);
            
            if (empty($object_mapping)) {
                error_log("HubspotIntegration: No mapping found for custom object: {$object_name}");
                continue;
            }
            
            // Map form data to custom object properties
            $mapped_object_data = [];
            foreach ($object_mapping as $form_field => $object_property) {
                if (isset($data[$form_field]) && !empty($data[$form_field])) {
                    $mapped_object_data[$object_property] = $data[$form_field];
                }
            }
            
            if (empty($mapped_object_data)) {
                error_log("HubspotIntegration: No mapped data for custom object: {$object_name}");
                continue;
            }
            
            // Create or update custom object
            if ($action === 'update') {
                $object_id = $object_config['object_id'] ?? '';
                $result = $this->updateCustomObject($object_name, $mapped_object_data, $object_id, $access_token, $portal_id, $submission_id);
            } else {
                $result = $this->createCustomObject($object_name, $mapped_object_data, $access_token, $portal_id, $submission_id);
            }
            
            $results[$object_name] = $result;
        }
        
        return [
            'success' => true,
            'results' => $results
        ];
    }

    /**
     * Create custom object
     */
    private function createCustomObject(string $object_name, array $data, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: createCustomObject called for {$object_name}");
        
        $url = "https://api.hubapi.com/crm/v3/objects/{$object_name}";
        
        $object_data = [
            'properties' => $data
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($object_data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log("HubspotIntegration: createCustomObject error: " . $response->get_error_message());
            return [
                'success' => false,
                'error' => 'Failed to create custom object: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 201) {
            error_log("HubspotIntegration: createCustomObject success: " . print_r($result, true));
            return [
                'success' => true,
                'message' => "Custom object {$object_name} created successfully",
                'object_id' => $result['id'] ?? '',
                'data' => $result
            ];
        } else {
            error_log("HubspotIntegration: createCustomObject failed - Status: {$status_code}, Response: {$body}");
            return [
                'success' => false,
                'error' => "Failed to create custom object (HTTP {$status_code}): " . ($result['message'] ?? 'Unknown error')
            ];
        }
    }

    /**
     * Update custom object
     */
    private function updateCustomObject(string $object_name, array $data, string $object_id, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: updateCustomObject called for {$object_name} with ID {$object_id}");
        
        if (empty($object_id)) {
            return [
                'success' => false,
                'error' => 'Object ID required for update'
            ];
        }
        
        $url = "https://api.hubapi.com/crm/v3/objects/{$object_name}/{$object_id}";
        
        $object_data = [
            'properties' => $data
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($object_data),
            'method' => 'PATCH',
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log("HubspotIntegration: updateCustomObject error: " . $response->get_error_message());
            return [
                'success' => false,
                'error' => 'Failed to update custom object: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code === 200) {
            error_log("HubspotIntegration: updateCustomObject success: " . print_r($result, true));
            return [
                'success' => true,
                'message' => "Custom object {$object_name} updated successfully",
                'object_id' => $object_id,
                'data' => $result
            ];
        } else {
            error_log("HubspotIntegration: updateCustomObject failed - Status: {$status_code}, Response: {$body}");
            return [
                'success' => false,
                'error' => "Failed to update custom object (HTTP {$status_code}): " . ($result['message'] ?? 'Unknown error')
            ];
        }
    }

    /**
     * Associate contact with company
     */
    private function associateCompany(array $data, array $settings, string $access_token, string $portal_id, int $submission_id): array {
        error_log("HubspotIntegration: associateCompany called");
        
        $company_id = $settings['company_id'] ?? '';
        
        if (empty($company_id)) {
            return [
                'success' => false,
                'error' => 'Company ID is required'
            ];
        }
        
        // This would require getting the contact ID first, then associating
        // For now, return success as this is a complex operation
        return [
            'success' => true,
            'message' => 'Company association not implemented yet'
        ];
    }

    /**
     * Get global settings
     */
    public function get_global_settings() {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        return $global_settings['hubspot'] ?? [];
    }

    /**
     * Check if globally connected
     */
    public function is_globally_connected(): bool {
        $settings = $this->get_global_settings();
        return !empty($settings['access_token']) && !empty($settings['portal_id']);
    }

    /**
     * Get enhanced field mapping
     */
    public function get_enhanced_field_mapping(int $form_id): array {
        $mapping = get_post_meta($form_id, '_mavlers_cf_hubspot_field_mapping', true);
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Save enhanced field mapping
     */
    public function save_enhanced_field_mapping(int $form_id, array $mapping): bool {
        return update_post_meta($form_id, '_mavlers_cf_hubspot_field_mapping', $mapping);
    }

    /**
     * Enqueue comprehensive assets
     */
    public function enqueue_comprehensive_assets($hook): void {
        error_log("HubSpot: === ENQUEUE COMPREHENSIVE ASSETS ===");
        error_log("HubSpot: Hook: {$hook}");
        
        // Get current screen
        $screen = get_current_screen();
        error_log("HubSpot: Screen: " . ($screen ? $screen->id : 'no screen'));
        
        // Check if we're on a form builder page or integration settings
        $is_form_builder = (
            strpos($hook, 'mavlers-cf-new-form') !== false ||
            strpos($hook, 'mavlers-contact-forms') !== false ||
            strpos($hook, 'admin.php') !== false ||
            strpos($hook, 'post.php') !== false ||
            strpos($hook, 'post-new.php') !== false ||
            (isset($_GET['action']) && $_GET['action'] === 'edit') ||
            strpos($hook, 'mavlers-cf') !== false
        );
        
        error_log("HubSpot: Is form builder: " . ($is_form_builder ? 'Yes' : 'No'));
        
        // Only enqueue on form builder pages or specific admin pages
        if (!$is_form_builder) {
            error_log("HubSpot: Not on form builder page, skipping asset enqueue. Hook: {$hook}");
            return;
        }
        
        error_log("HubSpot: Enqueuing assets on hook: {$hook}");
        
        // Get form ID from URL or POST data
        $form_id = 0;
        if (isset($_GET['id'])) {
            $form_id = intval($_GET['id']);
        } elseif (isset($_POST['form_id'])) {
            $form_id = intval($_POST['form_id']);
        } elseif (function_exists('get_the_ID')) {
            $form_id = get_the_ID();
        }
        
        error_log("HubSpot: Form ID: {$form_id}");
        
        // Enqueue HubSpot specific assets
        wp_enqueue_style(
            'mavlers-cf-hubspot',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/css/admin/hubspot.css',
            [],
            $this->version
        );
        
        wp_enqueue_script(
            'mavlers-cf-hubspot',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/js/admin/hubspot-form.js',
            ['jquery', 'wp-util'],
            $this->version,
            true
        );
        
        error_log("HubSpot: Script enqueued with version: {$this->version}");
        error_log("HubSpot: Script URL: " . MAVLERS_CF_INTEGRATIONS_URL . 'assets/js/admin/hubspot-form.js');
        
        // Create comprehensive localized data for new structure
        $localized_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'formId' => $form_id,
            'pluginUrl' => MAVLERS_CF_INTEGRATIONS_URL,
            'strings' => [
                'testing' => __('Testing...', 'mavlers-contact-forms'),
                'connected' => __('Connected', 'mavlers-contact-forms'),
                'disconnected' => __('Disconnected', 'mavlers-contact-forms'),
                'testConnection' => __('Test Connection', 'mavlers-contact-forms'),
                'savingSettings' => __('Saving...', 'mavlers-contact-forms'),
                'settingsSaved' => __('Settings saved successfully!', 'mavlers-contact-forms'),
                'connectionFailed' => __('Connection failed', 'mavlers-contact-forms'),
                'selectContact' => __('Select contact properties...', 'mavlers-contact-forms'),
                'loadingFields' => __('Loading fields...', 'mavlers-contact-forms'),
                'fieldsLoaded' => __('Fields loaded successfully', 'mavlers-contact-forms'),
                'noFieldsFound' => __('No fields found', 'mavlers-contact-forms'),
                'networkError' => __('Network error', 'mavlers-contact-forms'),
                'mappingSaved' => __('Field mapping saved successfully', 'mavlers-contact-forms'),
                'mappingFailed' => __('Failed to save field mapping', 'mavlers-contact-forms'),
                'autoMappingComplete' => __('Auto-mapping completed', 'mavlers-contact-forms'),
                'clearMappingsConfirm' => __('Are you sure you want to clear all mappings?', 'mavlers-contact-forms')
            ]
        ];
        
        // Localize script with new structure
        wp_localize_script('mavlers-cf-hubspot', 'mavlersCFHubspot', $localized_data);
        
        // Also localize with standard variables for compatibility
        wp_localize_script('mavlers-cf-hubspot', 'mavlers_cf_nonce', wp_create_nonce('mavlers_cf_nonce'));
        wp_localize_script('mavlers-cf-hubspot', 'ajaxurl', admin_url('admin-ajax.php'));
        
        error_log("HubSpot: Assets enqueued successfully with localized data: " . print_r($localized_data, true));
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection(): void {
        error_log("HubSpot: === AJAX TEST CONNECTION START ===");
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log("HubSpot: Nonce verification failed");
            wp_send_json([
                'success' => false,
                'error' => 'Security check failed'
            ]);
        }
        
        error_log("HubSpot: Nonce verification passed");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            error_log("HubSpot: Insufficient permissions");
            wp_send_json([
                'success' => false,
                'error' => 'Insufficient permissions'
            ]);
        }
        
        // Extract credentials from credentials object or direct POST
        $access_token = '';
        $portal_id = '';
        
        if (isset($_POST['credentials']) && is_array($_POST['credentials'])) {
            $access_token = sanitize_text_field($_POST['credentials']['access_token'] ?? '');
            $portal_id = sanitize_text_field($_POST['credentials']['portal_id'] ?? '');
        } else {
            $access_token = sanitize_text_field($_POST['access_token'] ?? '');
            $portal_id = sanitize_text_field($_POST['portal_id'] ?? '');
        }
        
        error_log("HubSpot: Extracted access_token: " . (empty($access_token) ? 'EMPTY' : 'Present'));
        error_log("HubSpot: Extracted portal_id: " . (empty($portal_id) ? 'EMPTY' : $portal_id));
        
        if (empty($access_token) || empty($portal_id)) {
            error_log("HubSpot: Missing required credentials");
            wp_send_json([
                'success' => false,
                'error' => 'Access token and Portal ID are required'
            ]);
        }
        
        $result = $this->testConnection([
            'access_token' => $access_token,
            'portal_id' => $portal_id
        ]);
        
        error_log("HubSpot: Test connection result: " . print_r($result, true));
        
        wp_send_json($result);
        
        error_log("HubSpot: === AJAX TEST CONNECTION END ===");
    }

    /**
     * AJAX: Get contacts
     */
    public function ajax_get_contacts(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->custom_properties_manager) {
            $this->custom_properties_manager->ajax_get_contacts();
        } else {
            wp_send_json_error('Custom properties manager not available');
        }
    }

    /**
     * AJAX: Get companies
     */
    public function ajax_get_companies(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->company_manager) {
            $this->company_manager->ajax_get_companies();
        } else {
            wp_send_json_error('Company manager not available');
        }
    }

    /**
     * AJAX: Get deals
     */
    public function ajax_get_deals(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->deal_manager) {
            $this->deal_manager->ajax_get_deals();
        } else {
            wp_send_json_error('Deal manager not available');
        }
    }

    /**
     * AJAX: Get custom properties
     */
    public function ajax_get_custom_properties(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->custom_properties_manager) {
            $this->custom_properties_manager->ajax_get_custom_properties();
        } else {
            wp_send_json_error('Custom properties manager not available');
        }
    }

    /**
     * AJAX: Get workflows
     */
    public function ajax_get_workflows(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->workflow_manager) {
            $this->workflow_manager->ajax_get_workflows();
        } else {
            wp_send_json_error('Workflow manager not available');
        }
    }

    /**
     * AJAX: Test response format
     */
    public function ajax_test_response(): void {
        error_log("HubSpot: === TEST RESPONSE AJAX HANDLER ===");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        // Check if this is a valid AJAX request
        if (!wp_doing_ajax()) {
            error_log("HubSpot: Not an AJAX request");
            wp_send_json_error('Not an AJAX request');
            return;
        }
        
        // Check nonce if provided
        if (isset($_POST['nonce'])) {
            if (!wp_verify_nonce($_POST['nonce'], 'mavlers_cf_nonce')) {
                error_log("HubSpot: Nonce verification failed");
                error_log("HubSpot: Received nonce: " . $_POST['nonce']);
                error_log("HubSpot: Expected nonce: " . wp_create_nonce('mavlers_cf_nonce'));
                wp_send_json_error('Nonce verification failed');
                return;
            }
            error_log("HubSpot: Nonce verification passed");
        } else {
            error_log("HubSpot: No nonce provided, skipping verification");
        }
        
        // Return a simple success response
        wp_send_json_success('Test response successful');
        
        error_log("HubSpot: === TEST RESPONSE AJAX HANDLER END ===");
    }

    /**
     * AJAX: Save global settings (standard version)
     */
    public function ajax_save_global_settings(): void {
        error_log("HubSpot: === AJAX SAVE GLOBAL SETTINGS START ===");
        
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log("HubSpot: Nonce verification failed");
            wp_die('Security check failed');
        }
        
        error_log("HubSpot: Nonce verification passed");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Extract settings from the form data
        $settings = [];
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            $settings = $_POST['settings'];
        } else {
            // Fallback to direct POST data
            $settings = [
                'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
                'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
                'enable_analytics' => !empty($_POST['enable_analytics']),
                'enable_webhooks' => !empty($_POST['enable_webhooks']),
                'batch_processing' => !empty($_POST['batch_processing'])
            ];
        }
        
        error_log("HubSpot: Extracted settings: " . print_r($settings, true));
        
        // Validate settings
        $validation_result = $this->validateSettings($settings);
        error_log("HubSpot: Validation result: " . print_r($validation_result, true));
        if (!empty($validation_result)) {
            error_log("HubSpot: Validation failed: " . print_r($validation_result, true));
            wp_send_json_error('Settings validation failed: ' . implode(', ', $validation_result));
            return;
        }
        
        error_log("HubSpot: Validation passed");
        
        // Save settings using the abstract method
        error_log("HubSpot: Calling saveGlobalSettings with settings: " . print_r($settings, true));
        $result = $this->saveGlobalSettings($settings);
        
        error_log("HubSpot: Save result: " . ($result ? 'success' : 'failed'));
        
        if ($result) {
            error_log("HubSpot: Settings saved successfully");
            wp_send_json_success([
                'message' => 'Settings saved successfully!',
                'configured' => true
            ]);
        } else {
            error_log("HubSpot: Failed to save settings");
            wp_send_json_error('Failed to save settings');
        }
        
        error_log("HubSpot: === AJAX SAVE GLOBAL SETTINGS END ===");
    }

    /**
     * AJAX: Save global settings (simplified version)
     */
    public function ajax_save_global_settings_simple(): void {
        error_log("HubSpot: === SIMPLIFIED AJAX SAVE GLOBAL SETTINGS START ===");
        
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log("HubSpot: Nonce verification failed");
            wp_die('Security check failed');
        }
        
        error_log("HubSpot: Nonce verification passed");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Extract settings
        $settings = [
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
            'enable_analytics' => !empty($_POST['enable_analytics']),
            'enable_webhooks' => !empty($_POST['enable_webhooks']),
            'batch_processing' => !empty($_POST['batch_processing'])
        ];
        
        error_log("HubSpot: Extracted settings: " . print_r($settings, true));
        
        // Simple validation
        if (empty($settings['access_token']) || empty($settings['portal_id'])) {
            error_log("HubSpot: Validation failed - missing required fields");
            wp_send_json_error('Access token and Portal ID are required');
            return;
        }
        
        error_log("HubSpot: Validation passed");
        
        // Save settings
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        $global_settings['hubspot'] = $settings;
        $result = update_option('mavlers_cf_integrations_global', $global_settings);
        
        error_log("HubSpot: Save result: " . ($result ? 'success' : 'failed'));
        
        if ($result) {
            error_log("HubSpot: Settings saved successfully");
            wp_send_json_success('Settings saved successfully');
        } else {
            error_log("HubSpot: Failed to save settings");
            wp_send_json_error('Failed to save settings');
        }
        
        error_log("HubSpot: === SIMPLIFIED AJAX SAVE GLOBAL SETTINGS END ===");
    }

    /**
     * AJAX: Save global settings (v2 - isolated)
     */
    public function ajax_save_global_settings_v2(): void {
        error_log("HubSpot: === V2 AJAX SAVE GLOBAL SETTINGS START ===");
        error_log("HubSpot: Handler called successfully!");
        error_log("HubSpot: Request method: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        error_log("HubSpot: Raw POST data: " . file_get_contents('php://input'));
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log("HubSpot: Nonce verification failed");
            error_log("HubSpot: Received nonce: " . ($_POST['nonce'] ?? 'not set'));
            error_log("HubSpot: Expected nonce: " . wp_create_nonce('mavlers_cf_nonce'));
            wp_die('Security check failed');
        }
        
        error_log("HubSpot: Nonce verification passed");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        // Extract settings
        $settings = [
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'portal_id' => sanitize_text_field($_POST['portal_id'] ?? ''),
            'enable_analytics' => !empty($_POST['enable_analytics']),
            'enable_webhooks' => !empty($_POST['enable_webhooks']),
            'batch_processing' => !empty($_POST['batch_processing'])
        ];
        
        error_log("HubSpot: Extracted settings: " . print_r($settings, true));
        
        // Simple validation
        if (empty($settings['access_token']) || empty($settings['portal_id'])) {
            error_log("HubSpot: Validation failed - missing required fields");
            wp_send_json_error('Access token and Portal ID are required');
            return;
        }
        
        error_log("HubSpot: Validation passed");
        
        // Save settings
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        $global_settings['hubspot'] = $settings;
        $result = update_option('mavlers_cf_integrations_global', $global_settings);
        
        error_log("HubSpot: Save result: " . ($result ? 'success' : 'failed'));
        
        if ($result) {
            error_log("HubSpot: Settings saved successfully");
            wp_send_json_success('Settings saved successfully');
        } else {
            error_log("HubSpot: Failed to save settings");
            wp_send_json_error('Failed to save settings');
        }
        
        error_log("HubSpot: === V2 AJAX SAVE GLOBAL SETTINGS END ===");
    }

    /**
     * AJAX: Save form settings
     */
    public function ajax_save_form_settings(): void {
        try {
            check_ajax_referer('mavlers_cf_nonce', 'nonce');
            
            $form_id = intval($_POST['form_id'] ?? 0);
            
            if (!$form_id) {
                wp_send_json_error('Form ID is required');
            }
            
            error_log("HubSpot: === SAVING FORM SETTINGS FOR FORM ID {$form_id} ===");
            error_log("HubSpot: POST data: " . print_r($_POST, true));
            
            // Validate that the form exists in the custom forms table
            global $wpdb;
            $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
            $form_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$forms_table} WHERE id = %d", $form_id));
            
            error_log("HubSpot: Forms table: {$forms_table}");
            error_log("HubSpot: Form exists query result: " . ($form_exists ? 'YES' : 'NO'));
            
            if (!$form_exists) {
                error_log("HubSpot: Form with ID {$form_id} does not exist in custom forms table");
                
                // Debug: List all available forms from custom table
                $all_forms = $wpdb->get_results("SELECT id, form_title FROM {$forms_table} ORDER BY id");
                error_log("HubSpot: Available forms in custom table: " . print_r($all_forms, true));
                
                wp_send_json_error('Form does not exist');
            }
            
            error_log("HubSpot: Form validation passed for ID {$form_id}");
            
            $settings = [
                'enabled' => true, // Always enabled if form is submitted
                'object_type' => sanitize_text_field($_POST['object_type'] ?? ''),
                'custom_object_name' => sanitize_text_field($_POST['custom_object_name'] ?? ''),
                'action_type' => sanitize_text_field($_POST['action_type'] ?? 'create'),
                'workflow_enabled' => !empty($_POST['workflow_enabled']),
                'field_mapping' => $_POST['field_mapping'] ?? []
            ];
            
            error_log("HubSpot: Field mapping from POST: " . print_r($_POST['field_mapping'] ?? [], true));
            error_log("HubSpot: Processed settings: " . print_r($settings, true));
            
            $result = $this->saveFormSettings($form_id, $settings);
            
            error_log("HubSpot: Save result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if ($result) {
                wp_send_json_success('Form settings saved successfully');
            } else {
                wp_send_json_error('Failed to save form settings');
            }
        } catch (Exception $e) {
            error_log("HubSpot: Exception in ajax_save_form_settings: " . $e->getMessage());
            wp_send_json_error('Error saving form settings: ' . $e->getMessage());
        }
    }

    /**
     * Save form settings
     */
    protected function saveFormSettings(int $form_id, array $settings): bool {
        error_log("HubSpot: saveFormSettings called for form ID: {$form_id}");
        
        if (!$form_id) {
            error_log("HubSpot: saveFormSettings - Form ID is invalid");
            return false;
        }
        
        // Verify the form exists in the custom forms table
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        $form_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$forms_table} WHERE id = %d", $form_id));
        
        error_log("HubSpot: saveFormSettings - Form exists check: " . ($form_exists ? 'YES' : 'NO'));
        
        if (!$form_exists) {
            error_log("HubSpot: saveFormSettings - Form with ID {$form_id} does not exist in custom forms table");
            return false;
        }
        
        // Get existing integration settings from custom meta table
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        
        error_log("HubSpot: saveFormSettings - Meta table: {$meta_table}");
        
        // Check if meta table exists, create if not
        $meta_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$meta_table'") == $meta_table;
        error_log("HubSpot: saveFormSettings - Meta table exists: " . ($meta_table_exists ? 'YES' : 'NO'));
        
        if (!$meta_table_exists) {
            error_log("HubSpot: saveFormSettings - Creating meta table");
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $meta_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                form_id bigint(20) NOT NULL,
                meta_key varchar(255) NOT NULL,
                meta_value longtext,
                PRIMARY KEY (id),
                KEY form_id (form_id),
                KEY meta_key (meta_key)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            error_log("HubSpot: saveFormSettings - Meta table created");
        }
        
        // Get existing settings
        $existing_settings = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
            $form_id,
            '_mavlers_cf_integrations'
        ));
        
        if ($existing_settings) {
            $existing_settings = json_decode($existing_settings, true);
        }
        
        if (!is_array($existing_settings)) {
            $existing_settings = [];
        }
        
        // Update HubSpot settings
        $existing_settings['hubspot'] = $settings;
        
        // Delete existing settings
        $delete_result = $wpdb->delete($meta_table, array('form_id' => $form_id, 'meta_key' => '_mavlers_cf_integrations'));
        error_log("HubSpot: saveFormSettings - Delete result: " . ($delete_result !== false ? 'SUCCESS' : 'FAILED'));
        
        // Insert new settings
        $insert_data = array(
            'form_id' => $form_id,
            'meta_key' => '_mavlers_cf_integrations',
            'meta_value' => json_encode($existing_settings)
        );
        
        error_log("HubSpot: saveFormSettings - Insert data: " . print_r($insert_data, true));
        
        $result = $wpdb->insert(
            $meta_table,
            $insert_data,
            array('%d', '%s', '%s')
        );
        
        error_log("HubSpot: saveFormSettings - Insert result: " . ($result !== false ? 'SUCCESS' : 'FAILED'));
        if ($result === false) {
            error_log("HubSpot: saveFormSettings - Database error: " . $wpdb->last_error);
        }
        
        return $result !== false;
    }

    /**
     * AJAX: Save field mapping
     */
    public function ajax_save_field_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $mapping = $_POST['mapping'] ?? [];
        
        if (!$form_id) {
            wp_send_json_error('Form ID is required');
        }

        $result = $this->save_enhanced_field_mapping($form_id, $mapping);
        
        if ($result) {
            wp_send_json_success('Field mapping saved successfully');
        } else {
            wp_send_json_error('Failed to save field mapping');
        }
    }

    /**
     * AJAX: Get form fields
     */
    public function ajax_get_form_fields(): void {
        // Temporarily disable nonce verification for debugging
        // if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
        //     wp_send_json_error('Security check failed');
        // }
        
        // Debug log to see if this method is being called
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("HubSpot: ajax_get_form_fields called with POST data: " . print_r($_POST, true));
        }
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (empty($form_id)) {
            wp_send_json_error('Form ID is required');
        }
        
        // Get form fields
        $form_fields = $this->get_form_fields($form_id);
        
        // Convert associative array to indexed array for JavaScript
        $fields_array = [];
        foreach ($form_fields as $field_id => $field_config) {
            $fields_array[] = $field_config;
        }
        
        wp_send_json_success($fields_array);
    }

    /**
     * AJAX: Get field mapping
     */
    public function ajax_get_field_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            wp_send_json_error('Form ID is required');
        }

        $mapping = $this->get_enhanced_field_mapping($form_id);
        wp_send_json_success($mapping);
    }

    /**
     * AJAX: Get HubSpot custom objects
     */
    public function ajax_get_custom_objects(): void {
        // Temporarily disable nonce verification for debugging
        // check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error('HubSpot not configured - Access token missing');
        }
        
        if (empty($portal_id)) {
            wp_send_json_error('HubSpot not configured - Portal ID missing');
        }
        
        error_log("HubSpot: Getting custom objects for portal ID: {$portal_id}");
        
        // Use portal-specific URL for custom objects
        $url = "https://api.hubapi.com/crm/v3/schemas?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch custom objects: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        error_log("HubSpot: Custom objects API response - Status: {$status_code}");
        error_log("HubSpot: Custom objects API response body: " . substr($body, 0, 1000));
        error_log("HubSpot: Decoded data: " . print_r($data, true));
        
        if ($status_code === 200 && isset($data['results'])) {
            $custom_objects = [];
            $total_schemas = count($data['results']);
            $custom_object_count = 0;
            
            foreach ($data['results'] as $schema) {
                $object_type = $schema['objectType'] ?? 'unknown';
                $schema_name = $schema['name'] ?? 'unknown';
                $fully_qualified_name = $schema['fullyQualifiedName'] ?? '';
                
                error_log("HubSpot: Processing schema - Name: {$schema_name}, ObjectType: {$object_type}, FullyQualifiedName: {$fully_qualified_name}");
                
                // Check if this is a custom object by looking at the fullyQualifiedName pattern
                // Custom objects have pattern: p{portal_id}_{object_name}
                if (preg_match('/^p\d+_/', $fully_qualified_name) || $object_type === 'CUSTOM_OBJECT') {
                    $custom_object_count++;
                    $custom_objects[] = [
                        'name' => $schema_name,
                        'label' => $schema['labels']['singular'] ?? $schema_name ?? '',
                        'plural_label' => $schema['labels']['plural'] ?? $schema_name ?? '',
                        'description' => $schema['description'] ?? '',
                        'primary_property' => $schema['primaryDisplayProperty'] ?? '',
                        'fullyQualifiedName' => $fully_qualified_name
                    ];
                    error_log("HubSpot: Added custom object - {$schema_name} (FQN: {$fully_qualified_name})");
                }
            }
            
            error_log("HubSpot: Found {$custom_object_count} custom objects out of {$total_schemas} total schemas");
            wp_send_json_success($custom_objects);
        } else {
            error_log("HubSpot: Failed to fetch custom objects - Status: {$status_code}, Body: " . substr($body, 0, 500));
            wp_send_json_error('Failed to fetch custom objects');
        }
    }

    /**
     * AJAX: Get HubSpot custom object properties
     */
    public function ajax_get_custom_object_properties(): void {
        // Temporarily disable nonce verification for debugging
        // if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
        //     wp_send_json_error('Security check failed');
        // }
        
        // Debug log to see if this method is being called
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("HubSpot: ajax_get_custom_object_properties called with POST data: " . print_r($_POST, true));
        }
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $custom_object_name = sanitize_text_field($_POST['custom_object_name'] ?? '');
        
        if (empty($form_id)) {
            wp_send_json_error('Form ID is required');
        }
        
        if (empty($custom_object_name)) {
            wp_send_json_error('Custom object name is required');
        }
        
        // Get HubSpot custom object properties (simplified for testing)
        $properties = array(
            'name' => array('name' => 'name', 'label' => 'Name', 'type' => 'text'),
            'description' => array('name' => 'description', 'label' => 'Description', 'type' => 'textarea'),
            'status' => array('name' => 'status', 'label' => 'Status', 'type' => 'select')
        );
        
        // Convert associative array to indexed array for JavaScript
        $properties_array = [];
        foreach ($properties as $property) {
            $properties_array[] = $property;
        }
        
        wp_send_json_success($properties_array);
    }

    /**
     * AJAX: Save custom object field mapping
     */
    public function ajax_save_custom_object_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $object_name = sanitize_text_field($_POST['object_name'] ?? '');
        $mapping = $_POST['mapping'] ?? [];
        
        if (!$form_id || empty($object_name)) {
            wp_send_json_error('Form ID and object name are required');
        }

        $result = $this->save_custom_object_mapping($form_id, $object_name, $mapping);
        
        if ($result) {
            wp_send_json_success('Custom object mapping saved successfully');
        } else {
            wp_send_json_error('Failed to save custom object mapping');
        }
    }

    /**
     * AJAX: Get custom object field mapping
     */
    public function ajax_get_custom_object_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $object_name = sanitize_text_field($_POST['object_name'] ?? '');
        
        if (!$form_id || empty($object_name)) {
            wp_send_json_error('Form ID and object name are required');
        }

        $mapping = $this->get_custom_object_mapping($form_id, $object_name);
        wp_send_json_success($mapping);
    }

    /**
     * Save custom object field mapping
     */
    private function save_custom_object_mapping(int $form_id, string $object_name, array $mapping): bool {
        if (!$form_id || empty($object_name)) {
            return false;
        }
        
        // Get existing custom object mappings
        $existing_mappings = get_post_meta($form_id, '_mavlers_cf_custom_object_mappings', true);
        if (!is_array($existing_mappings)) {
            $existing_mappings = [];
        }
        
        // Update mapping for this object
        $existing_mappings[$object_name] = $mapping;
        
        // Save back to post meta
        $result = update_post_meta($form_id, '_mavlers_cf_custom_object_mappings', $existing_mappings);
        
        error_log("HubSpot: save_custom_object_mapping - Form ID: {$form_id}, Object: {$object_name}, Result: " . ($result ? 'true' : 'false'));
        
        return $result;
    }

    /**
     * Get custom object field mapping
     */
    private function get_custom_object_mapping(int $form_id, string $object_name): array {
        if (!$form_id || empty($object_name)) {
            return [];
        }
        
        $mappings = get_post_meta($form_id, '_mavlers_cf_custom_object_mappings', true);
        if (!is_array($mappings) || !isset($mappings[$object_name])) {
            return [];
        }
        
        return $mappings[$object_name];
    }

    /**
     * AJAX: Get HubSpot deal properties
     */
    public function ajax_get_deal_properties(): void {
        // Temporarily disable nonce verification for debugging
        // check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error('HubSpot not configured - Access token missing');
        }
        
        if (empty($portal_id)) {
            wp_send_json_error('HubSpot not configured - Portal ID missing');
        }
        
        error_log("HubSpot: Getting deal properties for portal ID: {$portal_id}");
        
        // Use portal-specific URL for deal properties
        $url = "https://api.hubapi.com/crm/v3/properties/deals?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch deal properties: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            foreach ($data['results'] as $property) {
                // Include ALL properties (both writable and read-only) for the dropdown
                // Users can see all available properties and decide which to map
                $properties[] = [
                    'name' => $property['name'],
                    'label' => $property['label'],
                    'type' => $property['type'],
                    'required' => false, // Most properties are not required for updates
                    'readonly' => isset($property['modificationMetadata']['readOnlyDefinition']) && 
                                 $property['modificationMetadata']['readOnlyDefinition'] === true
                ];
            }
            
            wp_send_json_success($properties);
        } else {
            wp_send_json_error('Failed to fetch deal properties - Status: ' . $status_code);
        }
    }

    /**
     * AJAX: Get HubSpot company properties
     */
    public function ajax_get_company_properties(): void {
        // Temporarily disable nonce verification for debugging
        // check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error('HubSpot not configured - Access token missing');
        }
        
        if (empty($portal_id)) {
            wp_send_json_error('HubSpot not configured - Portal ID missing');
        }
        
        error_log("HubSpot: Getting company properties for portal ID: {$portal_id}");
        
        // Use portal-specific URL for company properties
        $url = "https://api.hubapi.com/crm/v3/properties/companies?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch company properties: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            foreach ($data['results'] as $property) {
                // Include ALL properties (both writable and read-only) for the dropdown
                // Users can see all available properties and decide which to map
                $properties[] = [
                    'name' => $property['name'],
                    'label' => $property['label'],
                    'type' => $property['type'],
                    'required' => false, // Most properties are not required for updates
                    'readonly' => isset($property['modificationMetadata']['readOnlyDefinition']) && 
                                 $property['modificationMetadata']['readOnlyDefinition'] === true
                ];
            }
            
            wp_send_json_success($properties);
        } else {
            wp_send_json_error('Failed to fetch company properties - Status: ' . $status_code);
        }
    }

    /**
     * AJAX: Get HubSpot contact properties
     */
    public function ajax_get_contact_properties(): void {
        // Temporarily disable nonce verification for debugging
        // if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
        //     wp_send_json_error('Security check failed');
        // }
        
        // Debug log to see if this method is being called
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("HubSpot: ajax_get_contact_properties called with POST data: " . print_r($_POST, true));
        }
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (empty($form_id)) {
            wp_send_json_error('Form ID is required');
        }
        
        // Get HubSpot contact properties
        $properties = array(
            'email' => array('name' => 'email', 'label' => 'Email', 'type' => 'email'),
            'firstname' => array('name' => 'firstname', 'label' => 'First Name', 'type' => 'text'),
            'lastname' => array('name' => 'lastname', 'label' => 'Last Name', 'type' => 'text'),
            'phone' => array('name' => 'phone', 'label' => 'Phone', 'type' => 'text'),
            'company' => array('name' => 'company', 'label' => 'Company', 'type' => 'text')
        );
        
        // Convert associative array to indexed array for JavaScript
        $properties_array = [];
        foreach ($properties as $property) {
            $properties_array[] = $property;
        }
        
        wp_send_json_success($properties_array);
    }

    /**
     * AJAX: Auto map fields
     */
    public function ajax_auto_map_fields(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            wp_send_json_error('Form ID is required');
        }

        // Generate automatic mapping based on form fields
        $auto_mapping = $this->generate_automatic_mapping($form_id);
        wp_send_json_success($auto_mapping);
    }

    /**
     * AJAX: Get analytics
     */
    public function ajax_get_analytics(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->analytics_manager) {
            $this->analytics_manager->ajax_get_analytics_data();
        } else {
            wp_send_json_error('Analytics manager not available');
        }
    }

    /**
     * AJAX: Simple test endpoint
     */
    public function ajax_test_simple(): void {
        error_log("HubSpot: Simple test endpoint called");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        
        wp_send_json_success('Simple test successful');
    }

    /**
     * AJAX: Simple test endpoint (v2)
     */
    public function ajax_test_simple_v2(): void {
        error_log("HubSpot: Simple test endpoint (v2) called");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content-Type: " . $_SERVER['CONTENT_TYPE'] ?? 'not set');
        
        // Check if this is a valid AJAX request
        if (!wp_doing_ajax()) {
            error_log("HubSpot: Not an AJAX request");
            wp_send_json_error('Not an AJAX request');
            return;
        }
        
        // No nonce check for this test endpoint
        wp_send_json_success('Simple test (v2) successful');
    }

    /**
     * AJAX: Basic test endpoint
     */
    public function ajax_test_basic(): void {
        error_log("HubSpot: Basic test endpoint called");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content-Type: " . $_SERVER['CONTENT_TYPE'] ?? 'not set');
        
        // Check if this is a valid AJAX request
        if (!wp_doing_ajax()) {
            error_log("HubSpot: Not an AJAX request");
            wp_send_json_error('Not an AJAX request');
            return;
        }
        
        // Check nonce if provided
        if (isset($_POST['nonce'])) {
            if (!wp_verify_nonce($_POST['nonce'], 'mavlers_cf_nonce')) {
                error_log("HubSpot: Nonce verification failed");
                wp_send_json_error('Nonce verification failed');
                return;
            }
        }
        
        wp_send_json_success('Basic test successful');
    }

    /**
     * Generate automatic field mapping
     */
    private function generate_automatic_mapping(int $form_id): array {
        $form_fields = $this->get_form_fields($form_id);
        $mapping = [];

        // Basic auto-mapping logic
        $auto_map_rules = [
            'email' => 'email',
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'phone' => 'phone',
            'company' => 'company'
        ];

        foreach ($form_fields as $field_name => $field_config) {
            $field_label = strtolower($field_config['label'] ?? '');
            
            foreach ($auto_map_rules as $pattern => $hubspot_field) {
                if (strpos($field_label, $pattern) !== false) {
                    $mapping[$field_name] = $hubspot_field;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Get form fields from database
     */
    private function get_form_fields(int $form_id): array {
        global $wpdb;
        
        error_log("HubSpot: get_form_fields called for form ID: {$form_id}");
        
        if (!$form_id) {
            error_log("HubSpot: get_form_fields - Form ID is invalid");
            return [];
        }
        
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));
        
        error_log("HubSpot: get_form_fields - Form query result: " . ($form ? 'FOUND' : 'NOT FOUND'));
        
        if (!$form) {
            error_log("HubSpot: get_form_fields - Form not found in database");
            return [];
        }
        
        error_log("HubSpot: get_form_fields - Form data: " . print_r($form, true));
        
        if (!$form->fields) {
            error_log("HubSpot: get_form_fields - No fields data in form");
            return [];
        }
        
        $fields_data = json_decode($form->fields, true);
        error_log("HubSpot: get_form_fields - Decoded fields data: " . print_r($fields_data, true));
        
        if (!is_array($fields_data)) {
            error_log("HubSpot: get_form_fields - Fields data is not an array");
            return [];
        }
        
        $processed_fields = [];
        
        foreach ($fields_data as $field) {
            error_log("HubSpot: get_form_fields - Processing field: " . print_r($field, true));
            
            if (!isset($field['id']) || !isset($field['label'])) {
                error_log("HubSpot: get_form_fields - Field missing id or label, skipping");
                continue;
            }
            
            $field_id = $field['id'];
            $field_type = $field['type'] ?? 'text';
            $field_label = $field['label'];
            $required = $field['required'] ?? false;
            
            $processed_fields[$field_id] = [
                'id' => $field_id,
                'label' => $field_label,
                'type' => $field_type,
                'required' => $required,
                'name' => $field['name'] ?? $field_id,
                'placeholder' => $field['placeholder'] ?? '',
                'description' => $field['description'] ?? ''
            ];
            
            error_log("HubSpot: get_form_fields - Added processed field: " . print_r($processed_fields[$field_id], true));
        }
        
        error_log("HubSpot: get_form_fields - Final processed fields: " . print_r($processed_fields, true));
        return $processed_fields;
    }

    /**
     * Get component managers
     */
    public function get_custom_properties_manager() {
        return $this->custom_properties_manager;
    }

    public function get_workflow_manager() {
        return $this->workflow_manager;
    }

    public function get_deal_manager() {
        return $this->deal_manager;
    }

    public function get_company_manager() {
        return $this->company_manager;
    }

    public function get_analytics_manager() {
        return $this->analytics_manager;
    }

    public function get_language_manager() {
        return $this->language_manager;
    }

    /**
     * AJAX: Debug test endpoint (no nonce required)
     */
    public function ajax_debug_test(): void {
        error_log("HubSpot: === DEBUG TEST ENDPOINT CALLED ===");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        error_log("HubSpot: wp_doing_ajax(): " . (wp_doing_ajax() ? 'true' : 'false'));
        error_log("HubSpot: DOING_AJAX: " . (defined('DOING_AJAX') ? 'true' : 'false'));
        
        // No nonce check for debug endpoint
        wp_send_json_success('Debug test successful');
        
        error_log("HubSpot: === DEBUG TEST ENDPOINT END ===");
    }

    /**
     * AJAX: Save form settings (debug version without nonce)
     */
    public function ajax_save_form_settings_debug(): void {
        error_log("HubSpot: === AJAX SAVE FORM SETTINGS DEBUG ===");
        error_log("HubSpot: POST data: " . print_r($_POST, true));
        error_log("HubSpot: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("HubSpot: CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        try {
            $form_id = intval($_POST['form_id'] ?? 0);
            
            if (!$form_id) {
                error_log("HubSpot: Form ID is missing");
                wp_send_json_error('Form ID is required');
            }
            
            error_log("HubSpot: Form ID: {$form_id}");
            error_log("HubSpot: POST data: " . print_r($_POST, true));
            
            // Validate that the form exists in the custom forms table
            global $wpdb;
            $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
            $form_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$forms_table} WHERE id = %d", $form_id));
            
            if (!$form_exists) {
                error_log("HubSpot: Form with ID {$form_id} does not exist in custom forms table");
                
                // Debug: List all available forms from custom table
                $all_forms = $wpdb->get_results("SELECT id, form_title FROM {$forms_table} ORDER BY id");
                error_log("HubSpot: Available forms in custom table: " . print_r($all_forms, true));
                
                wp_send_json_error('Form does not exist');
            }
            
            $settings = [
                'enabled' => true, // Always enabled if form is submitted
                'object_type' => sanitize_text_field($_POST['object_type'] ?? ''),
                'custom_object_name' => sanitize_text_field($_POST['custom_object_name'] ?? ''),
                'action_type' => sanitize_text_field($_POST['action_type'] ?? 'create'),
                'workflow_enabled' => !empty($_POST['workflow_enabled']),
                'field_mapping' => $_POST['field_mapping'] ?? []
            ];
            
            error_log("HubSpot: Processed settings: " . print_r($settings, true));
            
            $result = $this->saveFormSettings($form_id, $settings);
            
            error_log("HubSpot: Save result: " . ($result ? 'true' : 'false'));
            
            if ($result) {
                wp_send_json_success('Form settings saved successfully (debug mode)');
            } else {
                wp_send_json_error('Failed to save form settings');
            }
        } catch (Exception $e) {
            error_log("HubSpot: Exception in ajax_save_form_settings_debug: " . $e->getMessage());
            wp_send_json_error('Error saving form settings: ' . $e->getMessage());
        }
        
        error_log("HubSpot: === AJAX SAVE FORM SETTINGS DEBUG END ===");
    }



    /**
     * Translation helper
     */
    private function __($text, $fallback = null) {
        if ($this->language_manager) {
            return $this->language_manager->translate($text);
        }
        return $fallback ?? $text;
    }

    // Admin test AJAX handlers
    public function ajax_test_connection_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json_error('HubSpot not properly configured');
        }
        
        // Test connection by getting account info
        $url = "https://api.hubapi.com/oauth/v1/access-tokens/{$access_token}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['hub_id'])) {
            wp_send_json_success([
                'portal_id' => $data['hub_id'],
                'account_name' => $data['user'] ?? 'Unknown'
            ]);
        } else {
            wp_send_json_error('Connection failed - Status: ' . $status_code);
        }
    }

    public function ajax_test_global_settings_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        
        wp_send_json_success([
            'access_token' => !empty($global_settings['access_token']),
            'portal_id' => $global_settings['portal_id'] ?? ''
        ]);
    }

    public function ajax_test_contact_properties_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json_error('HubSpot not properly configured');
        }
        
        $url = "https://api.hubapi.com/crm/v3/properties/contacts?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch contact properties');
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            $all_properties = [];
            $readonly_count = 0;
            $writable_count = 0;
            
            foreach ($data['results'] as $property) {
                $all_properties[] = $property['name'];
                
                $is_readonly = isset($property['modificationMetadata']['readOnlyDefinition']) && 
                              $property['modificationMetadata']['readOnlyDefinition'] === true;
                
                if ($is_readonly) {
                    $readonly_count++;
                } else {
                    $writable_count++;
                    $properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label'],
                        'type' => $property['type']
                    ];
                }
            }
            
            // Check for key properties in all properties (not just writable ones)
            $key_properties = ['email', 'firstname', 'lastname', 'phone', 'company'];
            $found_key_properties = [];
            $missing_key_properties = [];
            
            foreach ($key_properties as $key_prop) {
                if (in_array($key_prop, $all_properties)) {
                    $found_key_properties[] = $key_prop;
                } else {
                    $missing_key_properties[] = $key_prop;
                }
            }
            
            wp_send_json_success([
                'properties' => $properties,
                'total_properties' => count($data['results']),
                'writable_properties' => $writable_count,
                'readonly_properties' => $readonly_count,
                'found_key_properties' => $found_key_properties,
                'missing_key_properties' => $missing_key_properties,
                'all_property_names' => array_slice($all_properties, 0, 50) // First 50 for debugging
            ]);
        } else {
            wp_send_json_error('Failed to fetch contact properties');
        }
    }

    public function ajax_test_deal_properties_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json_error('HubSpot not properly configured');
        }
        
        $url = "https://api.hubapi.com/crm/v3/properties/deals?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch deal properties');
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            foreach ($data['results'] as $property) {
                $is_readonly = isset($property['modificationMetadata']['readOnlyDefinition']) && 
                              $property['modificationMetadata']['readOnlyDefinition'] === true;
                
                if (!$is_readonly) {
                    $properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label'],
                        'type' => $property['type']
                    ];
                }
            }
            
            wp_send_json_success(['properties' => $properties]);
        } else {
            wp_send_json_error('Failed to fetch deal properties');
        }
    }

    public function ajax_test_company_properties_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json_error('HubSpot not properly configured');
        }
        
        $url = "https://api.hubapi.com/crm/v3/properties/companies?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch company properties');
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $properties = [];
            foreach ($data['results'] as $property) {
                $is_readonly = isset($property['modificationMetadata']['readOnlyDefinition']) && 
                              $property['modificationMetadata']['readOnlyDefinition'] === true;
                
                if (!$is_readonly) {
                    $properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label'],
                        'type' => $property['type']
                    ];
                }
            }
            
            wp_send_json_success(['properties' => $properties]);
        } else {
            wp_send_json_error('Failed to fetch company properties');
        }
    }

    public function ajax_test_custom_objects_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json_error('HubSpot not properly configured');
        }
        
        error_log("HubSpot: Testing custom objects for portal ID: {$portal_id}");
        
        $url = "https://api.hubapi.com/crm/v3/schemas?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log("HubSpot: Custom objects test failed - " . $response->get_error_message());
            wp_send_json_error('Failed to fetch custom objects: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        error_log("HubSpot: Custom objects test - Status: {$status_code}");
        error_log("HubSpot: Custom objects test - Body: " . substr($body, 0, 1000));
        error_log("HubSpot: Custom objects test - Decoded data: " . print_r($data, true));
        
        if ($status_code === 200 && isset($data['results'])) {
            $custom_objects = [];
            $all_schemas = [];
            $total_schemas = count($data['results']);
            $custom_object_count = 0;
            
                            foreach ($data['results'] as $schema) {
                    $object_type = $schema['objectType'] ?? 'unknown';
                    $schema_name = $schema['name'] ?? 'unknown';
                    $fully_qualified_name = $schema['fullyQualifiedName'] ?? '';
                    
                    $all_schemas[] = [
                        'name' => $schema_name,
                        'objectType' => $object_type,
                        'labels' => $schema['labels'] ?? [],
                        'fullyQualifiedName' => $fully_qualified_name
                    ];
                    
                    error_log("HubSpot: Processing schema - Name: {$schema_name}, ObjectType: {$object_type}, FullyQualifiedName: {$fully_qualified_name}");
                    
                    // Check if this is a custom object by looking at the fullyQualifiedName pattern
                    // Custom objects have pattern: p{portal_id}_{object_name}
                    if (preg_match('/^p\d+_/', $fully_qualified_name) || $object_type === 'CUSTOM_OBJECT') {
                        $custom_object_count++;
                        $custom_objects[] = [
                            'name' => $schema_name,
                            'label' => $schema['labels']['singular'] ?? $schema_name ?? '',
                            'fullyQualifiedName' => $fully_qualified_name
                        ];
                        error_log("HubSpot: Found custom object - {$schema_name} (FQN: {$fully_qualified_name})");
                    }
                }
            
            error_log("HubSpot: Found {$custom_object_count} custom objects out of {$total_schemas} total schemas");
            
            wp_send_json_success([
                'objects' => $custom_objects,
                'all_schemas' => $all_schemas,
                'total_schemas' => $total_schemas,
                'custom_object_count' => $custom_object_count,
                'message' => "Found {$custom_object_count} custom objects out of {$total_schemas} total schemas"
            ]);
        } else {
            error_log("HubSpot: Custom objects test failed - Status: {$status_code}, Body: " . substr($body, 0, 500));
            wp_send_json_error("Failed to fetch custom objects (HTTP {$status_code}): " . ($data['message'] ?? 'Unknown error'));
        }
    }

    public function ajax_test_form_settings_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 5);
        
        // Test form settings save
        $settings = [
            'enabled' => true,
            'object_type' => 'contact',
            'action_type' => 'create',
            'field_mapping' => []
        ];
        
        $result = $this->saveFormSettings($form_id, $settings);
        
        if ($result) {
            wp_send_json_success('Form settings saved successfully for form ID ' . $form_id);
        } else {
            wp_send_json_error('Failed to save form settings for form ID ' . $form_id);
        }
    }

    public function ajax_test_field_mapping_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 5);
        
        // Get form fields
        $form_fields = $this->get_form_fields($form_id);
        
        // Get HubSpot properties
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        $hubspot_properties = [];
        if (!empty($access_token) && !empty($portal_id)) {
            $url = "https://api.hubapi.com/crm/v3/properties/contacts?portalId={$portal_id}";
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if ($status_code === 200 && isset($data['results'])) {
                    foreach ($data['results'] as $property) {
                        $is_readonly = isset($property['modificationMetadata']['readOnlyDefinition']) && 
                                      $property['modificationMetadata']['readOnlyDefinition'] === true;
                        
                        if (!$is_readonly) {
                            $hubspot_properties[] = [
                                'name' => $property['name'],
                                'label' => $property['label']
                            ];
                        }
                    }
                }
            }
        }
        
        wp_send_json_success([
            'form_fields' => $form_fields,
            'hubspot_properties' => $hubspot_properties
        ]);
    }

    public function ajax_test_form_fields_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 5);
        
        $form_fields = $this->get_form_fields($form_id);
        
        wp_send_json_success(['fields' => $form_fields]);
    }

    public function ajax_test_api_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json_error('HubSpot not properly configured');
        }
        
        $endpoints = [];
        
        // Test contact properties
        $url = "https://api.hubapi.com/crm/v3/properties/contacts?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $endpoints[] = 'Contact Properties';
        }
        
        // Test custom objects
        $url = "https://api.hubapi.com/crm/v3/schemas?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $endpoints[] = 'Custom Objects';
        }
        
        wp_send_json_success([
            'portal_id' => $portal_id,
            'endpoints' => $endpoints
        ]);
    }

    public function ajax_test_error_log_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $test_message = 'HubSpot test error log message - ' . date('Y-m-d H:i:s');
        error_log("HubSpot: " . $test_message);
        
        wp_send_json_success(['message' => $test_message]);
    }

    public function ajax_test_portal_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($portal_id)) {
            wp_send_json_error('Portal ID not configured');
        }
        
        $api_calls = 0;
        
        // Test API calls with portal ID
        $access_token = $global_settings['access_token'] ?? '';
        if (!empty($access_token)) {
            $url = "https://api.hubapi.com/crm/v3/properties/contacts?portalId={$portal_id}";
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $api_calls++;
            }
        }
        
        wp_send_json_success([
            'portal_id' => $portal_id,
            'api_calls' => $api_calls
        ]);
    }

    public function ajax_test_property_search_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json_error('HubSpot not properly configured');
        }
        
        $url = "https://api.hubapi.com/crm/v3/properties/contacts?portalId={$portal_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch contact properties');
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['results'])) {
            $email_properties = [];
            $name_properties = [];
            $phone_properties = [];
            $company_properties = [];
            
            foreach ($data['results'] as $property) {
                $name = strtolower($property['name']);
                $label = strtolower($property['label']);
                
                // Search for email-related properties
                if (strpos($name, 'email') !== false || strpos($label, 'email') !== false) {
                    $email_properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label']
                    ];
                }
                
                // Search for name-related properties
                if (strpos($name, 'name') !== false || strpos($name, 'first') !== false || 
                    strpos($name, 'last') !== false || strpos($label, 'name') !== false ||
                    strpos($label, 'first') !== false || strpos($label, 'last') !== false) {
                    $name_properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label']
                    ];
                }
                
                // Search for phone-related properties
                if (strpos($name, 'phone') !== false || strpos($name, 'mobile') !== false || 
                    strpos($name, 'tel') !== false || strpos($label, 'phone') !== false ||
                    strpos($label, 'mobile') !== false || strpos($label, 'tel') !== false) {
                    $phone_properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label']
                    ];
                }
                
                // Search for company-related properties
                if (strpos($name, 'company') !== false || strpos($name, 'business') !== false || 
                    strpos($name, 'organization') !== false || strpos($label, 'company') !== false ||
                    strpos($label, 'business') !== false || strpos($label, 'organization') !== false) {
                    $company_properties[] = [
                        'name' => $property['name'],
                        'label' => $property['label']
                    ];
                }
            }
            
            wp_send_json_success([
                'total_properties' => count($data['results']),
                'email_properties' => $email_properties,
                'name_properties' => $name_properties,
                'phone_properties' => $phone_properties,
                'company_properties' => $company_properties,
                'sample_properties' => array_slice(array_map(function($prop) {
                    return $prop['name'] . ' - ' . $prop['label'];
                }, $data['results']), 0, 20) // First 20 properties for debugging
            ]);
        } else {
            wp_send_json_error('Failed to fetch contact properties');
        }
    }

    public function ajax_test_sandbox_debug_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';
        $portal_id = $global_settings['portal_id'] ?? '';
        
        if (empty($access_token) || empty($portal_id)) {
            wp_send_json_error('HubSpot not properly configured');
        }
        
        $debug_info = [];
        
        // Test 1: Basic API call without portal ID
        $url1 = "https://api.hubapi.com/crm/v3/properties/contacts";
        $response1 = wp_remote_get($url1, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response1)) {
            $status1 = wp_remote_retrieve_response_code($response1);
            $body1 = wp_remote_retrieve_body($response1);
            $data1 = json_decode($body1, true);
            
            $debug_info['without_portal_id'] = [
                'status' => $status1,
                'count' => isset($data1['results']) ? count($data1['results']) : 0,
                'response' => substr($body1, 0, 500)
            ];
        }
        
        // Test 2: API call with portal ID
        $url2 = "https://api.hubapi.com/crm/v3/properties/contacts?portalId={$portal_id}";
        $response2 = wp_remote_get($url2, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response2)) {
            $status2 = wp_remote_retrieve_response_code($response2);
            $body2 = wp_remote_retrieve_body($response2);
            $data2 = json_decode($body2, true);
            
            $debug_info['with_portal_id'] = [
                'status' => $status2,
                'count' => isset($data2['results']) ? count($data2['results']) : 0,
                'response' => substr($body2, 0, 500)
            ];
        }
        
        // Test 3: Try different API endpoint
        $url3 = "https://api.hubapi.com/crm/v3/properties/contacts?limit=1000";
        $response3 = wp_remote_get($url3, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response3)) {
            $status3 = wp_remote_retrieve_response_code($response3);
            $body3 = wp_remote_retrieve_body($response3);
            $data3 = json_decode($body3, true);
            
            $debug_info['with_limit'] = [
                'status' => $status3,
                'count' => isset($data3['results']) ? count($data3['results']) : 0,
                'response' => substr($body3, 0, 500)
            ];
        }
        
        // Test 4: Check account info
        $url4 = "https://api.hubapi.com/oauth/v1/access-tokens/{$access_token}";
        $response4 = wp_remote_get($url4, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response4)) {
            $status4 = wp_remote_retrieve_response_code($response4);
            $body4 = wp_remote_retrieve_body($response4);
            $data4 = json_decode($body4, true);
            
            $debug_info['account_info'] = [
                'status' => $status4,
                'data' => $data4
            ];
        }
        
        // Test 5: Try to get all properties with pagination
        $url5 = "https://api.hubapi.com/crm/v3/properties/contacts?limit=100&after=0";
        $response5 = wp_remote_get($url5, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response5)) {
            $status5 = wp_remote_retrieve_response_code($response5);
            $body5 = wp_remote_retrieve_body($response5);
            $data5 = json_decode($body5, true);
            
            $debug_info['with_pagination'] = [
                'status' => $status5,
                'count' => isset($data5['results']) ? count($data5['results']) : 0,
                'paging' => isset($data5['paging']) ? $data5['paging'] : null,
                'response' => substr($body5, 0, 500)
            ];
        }
        
        wp_send_json_success([
            'debug_info' => $debug_info,
            'portal_id' => $portal_id,
            'message' => 'Sandbox debug test completed. Check the debug info for details.'
        ]);
    }

    public function ajax_test_form_debug_admin() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        
        $debug_info = [];
        
        // Check if tables exist
        $debug_info['tables'] = [
            'forms_table_exists' => $wpdb->get_var("SHOW TABLES LIKE '$forms_table'") === $forms_table,
            'meta_table_exists' => $wpdb->get_var("SHOW TABLES LIKE '$meta_table'") === $meta_table
        ];
        
        // Get all forms
        $all_forms = $wpdb->get_results("SELECT id, form_title, status FROM $forms_table ORDER BY id");
        $debug_info['all_forms'] = $all_forms;
        
        // Test specific form IDs
        $test_form_ids = [1, 5];
        foreach ($test_form_ids as $form_id) {
            $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM $forms_table WHERE id = %d", $form_id));
            $meta_value = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
                $form_id,
                '_mavlers_cf_integrations'
            ));
            
            $debug_info["form_{$form_id}"] = [
                'exists' => $form ? true : false,
                'form_data' => $form,
                'meta_value' => $meta_value,
                'meta_decoded' => $meta_value ? json_decode($meta_value, true) : null
            ];
        }
        
        // Test form loading methods
        $debug_info['form_loading_tests'] = [
            'form_1_exists' => $this->form_exists_in_custom_table(1),
            'form_5_exists' => $this->form_exists_in_custom_table(5),
            'form_1_settings' => $this->get_form_settings_from_custom_table(1),
            'form_5_settings' => $this->get_form_settings_from_custom_table(5)
        ];
        
        // Test template loading simulation
        $debug_info['template_loading_simulation'] = [];
        foreach ($test_form_ids as $form_id) {
            $debug_info['template_loading_simulation']["form_{$form_id}"] = $this->simulate_template_loading($form_id);
        }
        
        // Test form fields loading
        $debug_info['form_fields_tests'] = [];
        foreach ($test_form_ids as $form_id) {
            $debug_info['form_fields_tests']["form_{$form_id}"] = [
                'form_id' => $form_id,
                'fields' => $this->get_form_fields($form_id)
            ];
        }
        
        wp_send_json_success([
            'debug_info' => $debug_info,
            'message' => 'Form database debug completed'
        ]);
    }
    
    public function ajax_test_submission() {
        check_ajax_referer('hubspot_test_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $test_data = $_POST['test_data'] ?? [];
        
        error_log("HubSpot: Testing submission for form ID: {$form_id}");
        error_log("HubSpot: Test data: " . print_r($test_data, true));
        
        // Add form_id to test data
        $test_data['form_id'] = $form_id;
        
        // Create a mock submission ID
        $submission_id = time();
        
        // Process the submission
        $result = $this->processSubmission($submission_id, $test_data);
        
        error_log("HubSpot: Submission test result: " . print_r($result, true));
        
        wp_send_json_success([
            'message' => 'HubSpot submission test completed',
            'submission_id' => $submission_id,
            'test_data' => $test_data,
            'result' => $result
        ]);
    }
    
    /**
     * Handle form submission from the main plugin
     */
    public function handle_form_submission(int $submission_id, array $form_data) {
        error_log("HubspotIntegration: handle_form_submission called");
        error_log("HubspotIntegration: submission_id: " . $submission_id);
        error_log("HubspotIntegration: form_data: " . print_r($form_data, true));
        
        try {
            // Get form ID from the form data
            $form_id = $form_data['form_id'] ?? 0;
            if (!$form_id) {
                error_log("HubspotIntegration: No form_id found in form_data");
                return;
            }
            
            error_log("HubspotIntegration: Processing form ID: " . $form_id);
            
            // Get form settings
            $settings = $this->getFormSettings($form_id);
            error_log("HubspotIntegration: Form settings: " . print_r($settings, true));
            
            // Check if HubSpot integration is enabled for this form
            $hubspot_settings = $settings['hubspot'] ?? $settings;
            if (empty($hubspot_settings['enabled'])) {
                error_log("HubspotIntegration: HubSpot integration not enabled for form ID: " . $form_id);
                return;
            }
            
            // Check if globally connected
            if (!$this->is_globally_connected()) {
                error_log("HubspotIntegration: HubSpot not globally connected");
                return;
            }
            
            // Process the submission
            $result = $this->processSubmission($submission_id, $form_data, $hubspot_settings);
            error_log("HubspotIntegration: Submission processing result: " . print_r($result, true));
            
        } catch (\Throwable $e) {
            error_log("HubspotIntegration: Error in handle_form_submission: " . $e->getMessage());
            error_log("HubspotIntegration: Stack trace: " . $e->getTraceAsString());
        }
    }
    
    private function simulate_template_loading(int $form_id): array {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        
        $form_settings = [];
        
        // Check if meta table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$meta_table'") == $meta_table) {
            $meta_value = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
                $form_id,
                '_mavlers_cf_integrations'
            ));
            
            if ($meta_value) {
                $integration_settings = json_decode($meta_value, true);
                if (is_array($integration_settings) && isset($integration_settings['hubspot'])) {
                    $form_settings = $integration_settings['hubspot'];
                }
            }
        }
        
        // Fallback: Try post meta
        if (empty($form_settings)) {
            $post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
            if ($post_meta && isset($post_meta['hubspot'])) {
                $form_settings = $post_meta['hubspot'];
            }
        }
        
        // Fallback: Try options table
        if (empty($form_settings)) {
            $option_key = "mavlers_cf_hubspot_form_{$form_id}";
            $form_settings = get_option($option_key, []);
        }
        
        return [
            'form_id' => $form_id,
            'settings_found' => !empty($form_settings),
            'settings' => $form_settings,
            'field_mapping' => $form_settings['field_mapping'] ?? []
        ];
    }
    
    private function form_exists_in_custom_table(int $form_id): bool {
        global $wpdb;
        $forms_table = $wpdb->prefix . 'mavlers_cf_forms';
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$forms_table} WHERE id = %d", $form_id));
    }
    
    private function get_form_settings_from_custom_table(int $form_id): array {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
            $form_id,
            '_mavlers_cf_integrations'
        ));
        
        if ($meta_value) {
            $settings = json_decode($meta_value, true);
            return is_array($settings) ? $settings : [];
        }
        
        return [];
    }
    
    /**
     * Get form settings for HubSpot integration
     */
    protected function getFormSettings(int $form_id): array {
        error_log("HubspotIntegration: getFormSettings called for form ID: " . $form_id);
        
        // Try to get settings from custom table first
        $settings = $this->get_form_settings_from_custom_table($form_id);
        
        if (!empty($settings)) {
            error_log("HubspotIntegration: Found settings in custom table: " . print_r($settings, true));
            return $settings;
        }
        
        // Fallback to WordPress post meta
        $meta_value = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        if ($meta_value) {
            $decoded = json_decode($meta_value, true);
            $hubspot_settings = $decoded['hubspot'] ?? [];
            error_log("HubspotIntegration: Found settings in post meta: " . print_r($hubspot_settings, true));
            return $hubspot_settings;
        }
        
        error_log("HubspotIntegration: No settings found for form ID: " . $form_id);
        return [];
    }
} 