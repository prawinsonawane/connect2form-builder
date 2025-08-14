<?php
/**
 * Field Mapping Template
 * 
 * @package Mavlers Contact Forms
 * @subpackage Integrations Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$integration_id = isset($_GET['integration_id']) ? sanitize_text_field($_GET['integration_id']) : '';

if (!$form_id || !$integration_id) {
    echo '<div class="notice notice-error"><p>Invalid form or integration ID.</p></div>';
    return;
}

// Get form details
global $wpdb;
$form = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
    $form_id
));

if (!$form) {
    echo '<div class="notice notice-error"><p>Form not found.</p></div>';
    return;
}

// Get existing field mappings
$existing_mapping = $wpdb->get_row($wpdb->prepare(
    "SELECT field_mappings, integration_settings FROM {$wpdb->prefix}mavlers_cf_form_integrations 
     WHERE form_id = %d AND integration_id = %s",
    $form_id,
    $integration_id
));

$field_mappings = array();
$integration_settings = array();

if ($existing_mapping) {
    $field_mappings = json_decode($existing_mapping->field_mappings, true) ?: array();
    $integration_settings = json_decode($existing_mapping->integration_settings, true) ?: array();
}
?>

<div class="wrap">
    <h1><?php printf(__('Map Fields: %s → %s', 'mavlers-contact-forms'), esc_html($form->name), esc_html(ucfirst($integration_id))); ?></h1>
    
    <div class="field-mapping-container">
        <div class="mapping-header">
            <div class="form-preview">
                <h3><?php _e('Form Fields', 'mavlers-contact-forms'); ?></h3>
                <div id="form-fields-list">
                    <div class="loading-spinner">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading form fields...', 'mavlers-contact-forms'); ?>
        </div>
    </div>
            </div>
            
            <div class="integration-settings">
                <h3><?php printf(__('%s Settings', 'mavlers-contact-forms'), ucfirst($integration_id)); ?></h3>
                <div id="integration-settings-form">
                    <div class="loading-spinner">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading integration settings...', 'mavlers-contact-forms'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="mapping-interface">
            <h3><?php _e('Field Mapping', 'mavlers-contact-forms'); ?></h3>
            <div class="mapping-instructions">
                <p><?php _e('Map your form fields to the corresponding integration fields. Required fields are marked with an asterisk (*).', 'mavlers-contact-forms'); ?></p>
            </div>
            
            <div id="field-mapping-table">
                <div class="loading-spinner">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading field mapping interface...', 'mavlers-contact-forms'); ?>
                </div>
            </div>
            
            <div class="mapping-actions">
                <button type="button" id="auto-map-fields" class="button button-secondary">
                    <?php _e('Auto Map Fields', 'mavlers-contact-forms'); ?>
                </button>
                <button type="button" id="save-field-mapping" class="button button-primary">
                    <?php _e('Save Field Mapping', 'mavlers-contact-forms'); ?>
                </button>
                <button type="button" id="test-integration" class="button button-secondary">
                    <?php _e('Test Integration', 'mavlers-contact-forms'); ?>
                </button>
            </div>
        </div>

        <div class="test-results" id="test-results" style="display: none;">
            <h3><?php _e('Test Results', 'mavlers-contact-forms'); ?></h3>
            <div id="test-output"></div>
        </div>
    </div>
            </div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var formId = <?php echo $form_id; ?>;
    var integrationId = '<?php echo esc_js($integration_id); ?>';
    var existingMappings = <?php echo json_encode($field_mappings); ?>;
    var existingSettings = <?php echo json_encode($integration_settings); ?>;
    
    // Initialize field mapping interface
    var fieldMapper = {
        formFields: {},
        integrationFields: {},
        
        init: function() {
            this.loadFormFields();
            this.loadIntegrationFields();
            this.loadIntegrationSettings();
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('#auto-map-fields').on('click', this.autoMapFields.bind(this));
            $('#save-field-mapping').on('click', this.saveMapping.bind(this));
            $('#test-integration').on('click', this.testIntegration.bind(this));
            
            // Dynamic field loading
            $(document).on('change', '.integration-setting-field', this.onSettingChange.bind(this));
        },
        
        loadFormFields: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_get_form_fields',
                    form_id: formId,
                    nonce: mavlersCFIntegrations.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.formFields = response.data;
                        this.renderFormFields();
                    } else {
                        this.showError('Failed to load form fields: ' + response.data);
                    }
                }.bind(this)
            });
        },
        
        loadIntegrationFields: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_get_integration_fields',
                    integration_id: integrationId,
                    nonce: mavlersCFIntegrations.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.integrationFields = response.data;
                        this.renderMappingInterface();
                    } else {
                        this.showError('Failed to load integration fields: ' + response.data);
                    }
                }.bind(this)
            });
        },
        
        loadIntegrationSettings: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_get_integration_config',
                    integration_id: integrationId,
                    nonce: mavlersCFIntegrations.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.renderIntegrationSettings(response.data);
                    } else {
                        this.showError('Failed to load integration settings: ' + response.data);
                    }
                }.bind(this)
            });
        },
        
        renderFormFields: function() {
            var html = '<div class="form-fields-grid">';
            
            if (this.formFields.length === 0) {
                html += '<p>No fields found in this form.</p>';
            } else {
                this.formFields.forEach(function(field) {
                    html += '<div class="form-field-item">';
                    html += '<span class="field-type">' + field.type + '</span>';
                    html += '<span class="field-label">' + field.label + '</span>';
                    html += '<span class="field-id">' + field.id + '</span>';
                    html += '</div>';
                });
            }
            
            html += '</div>';
            $('#form-fields-list').html(html);
        },
        
        renderIntegrationSettings: function(config) {
            var html = '<div class="integration-settings-form">';
            
            if (config.action_fields) {
                config.action_fields.forEach(function(field) {
                    html += this.renderSettingField(field);
                }.bind(this));
            }
            
            html += '</div>';
            $('#integration-settings-form').html(html);
            
            // Apply existing settings
            if (existingSettings) {
                for (var key in existingSettings) {
                    var $field = $('[name="' + key + '"]');
                    if ($field.length) {
                        $field.val(existingSettings[key]);
                    }
                }
            }
            
            // Load dynamic fields
            this.loadDynamicFields();
        },
        
        renderSettingField: function(field) {
            var html = '<div class="setting-field">';
            html += '<label for="' + field.id + '">' + field.label;
            if (field.required) html += ' <span class="required">*</span>';
            html += '</label>';
            
            switch (field.type) {
                case 'select':
                    html += '<select name="' + field.id + '" id="' + field.id + '" class="integration-setting-field"';
                    if (field.dynamic) html += ' data-dynamic="' + field.dynamic + '"';
                    html += '>';
                    
                    if (field.options) {
                        for (var value in field.options) {
                            html += '<option value="' + value + '">' + field.options[value] + '</option>';
                        }
                    }
                    html += '</select>';
                    break;
                    
                case 'text':
                    html += '<input type="text" name="' + field.id + '" id="' + field.id + '" class="integration-setting-field" />';
                    break;
                    
                case 'textarea':
                    html += '<textarea name="' + field.id + '" id="' + field.id + '" class="integration-setting-field"></textarea>';
                    break;
            }
            
            if (field.description) {
                html += '<p class="description">' + field.description + '</p>';
            }
            
            html += '</div>';
            return html;
        },
        
        loadDynamicFields: function() {
            $('.integration-setting-field[data-dynamic]').each(function() {
                var $field = $(this);
                var dynamicKey = $field.data('dynamic');
                
                this.loadDynamicFieldOptions(dynamicKey, $field);
            }.bind(this));
        },
        
        loadDynamicFieldOptions: function(fieldKey, $field) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_get_dynamic_field_options',
                    integration_id: integrationId,
                    field_key: fieldKey,
                    nonce: mavlersCFIntegrations.nonce
                },
                beforeSend: function() {
                    $field.prop('disabled', true);
                    $field.html('<option>Loading...</option>');
                },
                success: function(response) {
                    $field.prop('disabled', false);
                    
                    if (response.success) {
                        var html = '<option value="">Select...</option>';
                        for (var value in response.data) {
                            html += '<option value="' + value + '">' + response.data[value] + '</option>';
                        }
                        $field.html(html);
                        
                        // Apply existing value
                        if (existingSettings && existingSettings[fieldKey]) {
                            $field.val(existingSettings[fieldKey]);
                        }
                    } else {
                        $field.html('<option value="">Error loading options</option>');
                        this.showError('Failed to load ' + fieldKey + ' options: ' + response.data);
                    }
                }.bind(this)
            });
        },
        
        renderMappingInterface: function() {
            var html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th>' + mavlersCFIntegrations.strings.integration_field + '</th>';
            html += '<th>' + mavlersCFIntegrations.strings.form_field + '</th>';
            html += '<th>' + mavlersCFIntegrations.strings.actions + '</th>';
            html += '</tr></thead>';
            html += '<tbody>';
            
            if (this.integrationFields.length === 0) {
                html += '<tr><td colspan="3">No fields available for this integration.</td></tr>';
            } else {
                this.integrationFields.forEach(function(field) {
                    html += this.renderMappingRow(field);
                }.bind(this));
            }
            
            html += '</tbody></table>';
            $('#field-mapping-table').html(html);
            
            // Apply existing mappings
            if (existingMappings) {
                for (var integrationFieldId in existingMappings) {
                    var $select = $('select[name="mapping[' + integrationFieldId + ']"]');
                    if ($select.length) {
                        $select.val(existingMappings[integrationFieldId]);
                    }
                }
            }
        },
        
        renderMappingRow: function(field) {
            var html = '<tr>';
            html += '<td>';
            html += '<strong>' + field.label + '</strong>';
            if (field.required) html += ' <span class="required">*</span>';
            if (field.description) html += '<br><small>' + field.description + '</small>';
            html += '</td>';
            html += '<td>';
            html += '<select name="mapping[' + field.id + ']" class="field-mapping-select">';
            html += '<option value="">-- Select Form Field --</option>';
            
            this.formFields.forEach(function(formField) {
                var compatible = this.isFieldCompatible(formField.type, field.type);
                var disabled = compatible ? '' : ' disabled';
                var className = compatible ? '' : ' class="incompatible"';
                
                html += '<option value="' + formField.id + '"' + disabled + className + '>';
                html += formField.label + ' (' + formField.type + ')';
                html += '</option>';
            }.bind(this));
            
            html += '</select>';
            html += '</td>';
            html += '<td>';
            html += '<button type="button" class="button button-small clear-mapping" data-field="' + field.id + '">Clear</button>';
            html += '</td>';
            html += '</tr>';
            
            return html;
        },
        
        isFieldCompatible: function(formFieldType, integrationFieldType) {
            var compatibilityMap = {
                'text': ['text', 'email', 'url', 'number'],
                'email': ['email', 'text'],
                'number': ['number', 'text'],
                'textarea': ['textarea', 'text'],
                'select': ['select', 'radio', 'text'],
                'checkbox': ['checkbox', 'select'],
                'radio': ['radio', 'select', 'text'],
                'date': ['date', 'text'],
                'url': ['url', 'text'],
                'phone': ['phone', 'text']
            };
            
            var allowedTypes = compatibilityMap[integrationFieldType] || [integrationFieldType];
            return allowedTypes.includes(formFieldType);
        },
        
        autoMapFields: function() {
            this.integrationFields.forEach(function(integrationField) {
                var bestMatch = this.findBestFieldMatch(integrationField);
                if (bestMatch) {
                    $('select[name="mapping[' + integrationField.id + ']"]').val(bestMatch.id);
                }
            }.bind(this));
            
            this.showSuccess('Fields auto-mapped successfully!');
        },
        
        findBestFieldMatch: function(integrationField) {
            var scores = [];
            
            this.formFields.forEach(function(formField) {
                if (!this.isFieldCompatible(formField.type, integrationField.type)) {
                    return;
                }
                
                var score = 0;
                
                // Exact label match
                if (formField.label.toLowerCase() === integrationField.label.toLowerCase()) {
                    score += 100;
                }
                
                // Partial label match
                var formLabel = formField.label.toLowerCase();
                var integrationLabel = integrationField.label.toLowerCase();
                
                if (formLabel.includes(integrationLabel) || integrationLabel.includes(formLabel)) {
                    score += 50;
                }
                
                // Common field name patterns
                var commonMappings = {
                    'email': ['email', 'e-mail', 'email_address'],
                    'first_name': ['first', 'fname', 'first_name', 'name'],
                    'last_name': ['last', 'lname', 'last_name', 'surname'],
                    'phone': ['phone', 'telephone', 'mobile', 'cell'],
                    'company': ['company', 'organization', 'business']
                };
                
                for (var pattern in commonMappings) {
                    if (integrationField.id.includes(pattern)) {
                        commonMappings[pattern].forEach(function(keyword) {
                            if (formLabel.includes(keyword)) {
                                score += 30;
                            }
                        });
                    }
                }
                
                if (score > 0) {
                    scores.push({field: formField, score: score});
                }
            }.bind(this));
            
            if (scores.length === 0) {
                return null;
            }
            
            scores.sort(function(a, b) {
                return b.score - a.score;
            });
            
            return scores[0].field;
        },
        
        saveMapping: function() {
            var mappings = {};
            var settings = {};
            
            // Collect field mappings
            $('.field-mapping-select').each(function() {
                var $select = $(this);
                var fieldName = $select.attr('name').match(/mapping\[(.+)\]/)[1];
                var formFieldId = $select.val();
                
                if (formFieldId) {
                    mappings[fieldName] = formFieldId;
                }
            });
            
            // Collect integration settings
            $('.integration-setting-field').each(function() {
                var $field = $(this);
                var fieldName = $field.attr('name');
                var value = $field.val();
                
                if (value) {
                    settings[fieldName] = value;
                }
            });
            
            // Validate required fields
            var missingFields = [];
            this.integrationFields.forEach(function(field) {
                if (field.required && !mappings[field.id]) {
                    missingFields.push(field.label);
                }
            });
            
            $('.integration-setting-field[required]').each(function() {
                var $field = $(this);
                if (!$field.val()) {
                    missingFields.push($field.prev('label').text().replace('*', '').trim());
                }
            });
            
            if (missingFields.length > 0) {
                this.showError('Please map/fill the following required fields: ' + missingFields.join(', '));
                return;
            }
            
            // Save mapping
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_save_field_mapping',
                    form_id: formId,
                    integration_id: integrationId,
                    mappings: mappings,
                    settings: settings,
                    nonce: mavlersCFIntegrations.nonce
                },
                beforeSend: function() {
                    $('#save-field-mapping').prop('disabled', true).text('Saving...');
                },
                success: function(response) {
                    $('#save-field-mapping').prop('disabled', false).text('Save Field Mapping');
                    
                    if (response.success) {
                        this.showSuccess('Field mapping saved successfully!');
                        existingMappings = mappings;
                        existingSettings = settings;
                    } else {
                        this.showError('Failed to save field mapping: ' + response.data);
                    }
                }.bind(this)
            });
        },
        
        testIntegration: function() {
            // Create test data based on mappings
            var testData = {};
            
            $('.field-mapping-select').each(function() {
                var $select = $(this);
                var fieldName = $select.attr('name').match(/mapping\[(.+)\]/)[1];
                var formFieldId = $select.val();
                
                if (formFieldId) {
                    var formField = this.formFields.find(function(f) { return f.id === formFieldId; });
                    if (formField) {
                        // Generate test data based on field type
                        switch (formField.type) {
                            case 'email':
                                testData[formFieldId] = 'test@example.com';
                                break;
                            case 'number':
                                testData[formFieldId] = '123';
                                break;
                            case 'phone':
                                testData[formFieldId] = '+1234567890';
                                                break;
                            default:
                                testData[formFieldId] = 'Test ' + formField.label;
                        }
                    }
                }
            }.bind(this));
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_test_integration',
                    form_id: formId,
                    integration_id: integrationId,
                    test_data: testData,
                    nonce: mavlersCFIntegrations.nonce
                },
                beforeSend: function() {
                    $('#test-integration').prop('disabled', true).text('Testing...');
                    $('#test-results').show();
                    $('#test-output').html('<div class="spinner is-active"></div> Running test...');
                },
                success: function(response) {
                    $('#test-integration').prop('disabled', false).text('Test Integration');
                    
                    if (response.success) {
                        $('#test-output').html('<div class="notice notice-success"><p>✅ Test successful!</p><pre>' + JSON.stringify(response.data, null, 2) + '</pre></div>');
                    } else {
                        $('#test-output').html('<div class="notice notice-error"><p>❌ Test failed: ' + response.data + '</p></div>');
                    }
                }
            });
        },
        
        onSettingChange: function(e) {
            var $field = $(e.target);
            if ($field.data('dynamic')) {
                // Reload dependent fields if needed
                this.loadDynamicFields();
            }
        },
        
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        }
    };
    
    // Initialize
    fieldMapper.init();
    
    // Clear mapping buttons
    $(document).on('click', '.clear-mapping', function() {
        var fieldId = $(this).data('field');
        $('select[name="mapping[' + fieldId + ']"]').val('');
    });
});
</script>

<style>
.field-mapping-container {
    max-width: 1200px;
}

.mapping-header {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.form-preview,
.integration-settings {
    flex: 1;
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
}

.form-fields-grid {
    display: grid;
    gap: 10px;
}

.form-field-item {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 8px;
    background: #f9f9f9;
    border-left: 3px solid #0073aa;
}

.field-type {
    background: #0073aa;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
}

.field-label {
    font-weight: 500;
}

.field-id {
    font-family: monospace;
    color: #666;
    font-size: 12px;
}

.mapping-interface {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
}

.mapping-instructions {
    background: #f0f6fc;
    border-left: 4px solid #0073aa;
    padding: 12px;
    margin-bottom: 20px;
}

.setting-field {
    margin-bottom: 15px;
}

.setting-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.setting-field .required {
    color: #d63638;
}

.setting-field input,
.setting-field select,
.setting-field textarea {
    width: 100%;
    max-width: 400px;
}

.field-mapping-select option.incompatible {
    color: #999;
    font-style: italic;
}

.mapping-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.mapping-actions .button {
    margin-right: 10px;
}

.test-results {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
}

.test-results pre {
    background: #f6f7f7;
    padding: 15px;
    border-radius: 3px;
    overflow-x: auto;
}

.loading-spinner {
    text-align: center;
    padding: 20px;
    color: #666;
}

.loading-spinner .spinner {
    float: none;
    margin-right: 10px;
}
</style> 