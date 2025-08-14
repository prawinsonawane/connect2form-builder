<?php
/**
 * Enhanced Field Mapping Template
 * 
 * Visual drag-and-drop field mapping interface for Mailchimp integration
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="enhanced-field-mapping">
    <h4><?php _e('Advanced Field Mapping', 'mavlers-contact-forms'); ?></h4>
    
    <div class="mapping-container">
        <!-- Form Fields Column -->
        <div class="mapping-column form-fields-column">
            <div class="column-header">
                <h5><?php _e('Form Fields', 'mavlers-contact-forms'); ?></h5>
                <span class="field-count" id="form-field-count">0 fields</span>
            </div>
            <div class="fields-list" id="form-fields-list">
                <!-- Dynamically populated via JavaScript -->
            </div>
        </div>

        <!-- Mapping Lines -->
        <div class="mapping-connections" id="mapping-connections">
            <svg class="connection-svg" width="100%" height="100%">
                <!-- Connection lines drawn here -->
            </svg>
        </div>

        <!-- Mailchimp Fields Column -->
        <div class="mapping-column mailchimp-fields-column">
            <div class="column-header">
                <h5><?php _e('Mailchimp Fields', 'mavlers-contact-forms'); ?></h5>
                <button type="button" class="button button-secondary refresh-fields" id="refresh-mailchimp-fields">
                    <span class="dashicons dashicons-update-alt"></span>
                    <?php _e('Refresh', 'mavlers-contact-forms'); ?>
                </button>
            </div>
            <div class="fields-list" id="mailchimp-fields-list">
                <!-- Standard Mailchimp fields -->
                <div class="field-item mailchimp-field required" data-field="email_address" data-type="email">
                    <div class="field-info">
                        <span class="field-name">Email Address</span>
                        <span class="field-type">email</span>
                        <span class="required-indicator">*</span>
                    </div>
                    <div class="field-connector" data-field="email_address"></div>
                </div>
                
                <div class="field-item mailchimp-field" data-field="FNAME" data-type="text">
                    <div class="field-info">
                        <span class="field-name">First Name</span>
                        <span class="field-type">text</span>
                    </div>
                    <div class="field-connector" data-field="FNAME"></div>
                </div>
                
                <div class="field-item mailchimp-field" data-field="LNAME" data-type="text">
                    <div class="field-info">
                        <span class="field-name">Last Name</span>
                        <span class="field-type">text</span>
                    </div>
                    <div class="field-connector" data-field="LNAME"></div>
                </div>
                
                <div class="field-item mailchimp-field" data-field="PHONE" data-type="phone">
                    <div class="field-info">
                        <span class="field-name">Phone Number</span>
                        <span class="field-type">phone</span>
                    </div>
                    <div class="field-connector" data-field="PHONE"></div>
                </div>
            </div>
            
            <!-- Custom Field Addition -->
            <div class="add-custom-field">
                <button type="button" class="button button-secondary" id="add-custom-field">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Custom Field', 'mavlers-contact-forms'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Auto-Mapping Controls -->
    <div class="mapping-controls">
        <button type="button" class="button button-primary" id="auto-map-fields">
            <span class="dashicons dashicons-admin-links"></span>
            <?php _e('Auto-Map Fields', 'mavlers-contact-forms'); ?>
        </button>
        
        <button type="button" class="button button-secondary" id="clear-mappings">
            <span class="dashicons dashicons-dismiss"></span>
            <?php _e('Clear All Mappings', 'mavlers-contact-forms'); ?>
        </button>
        
        <div class="mapping-status">
            <span id="mapping-count">0 mappings</span>
            <span id="required-status" class="status-indicator"></span>
        </div>
    </div>

    <!-- Mapping Preview -->
    <div class="mapping-preview">
        <h5><?php _e('Mapping Preview', 'mavlers-contact-forms'); ?></h5>
        <div class="preview-table" id="mapping-preview-table">
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th><?php _e('Form Field', 'mavlers-contact-forms'); ?></th>
                        <th><?php _e('Mailchimp Field', 'mavlers-contact-forms'); ?></th>
                        <th><?php _e('Action', 'mavlers-contact-forms'); ?></th>
                    </tr>
                </thead>
                <tbody id="mapping-preview-body">
                    <!-- Preview rows populated via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.enhanced-field-mapping {
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
}

.mapping-container {
    display: grid;
    grid-template-columns: 1fr 80px 1fr;
    gap: 0;
    min-height: 400px;
    position: relative;
}

.mapping-column {
    padding: 15px;
    border-right: 1px solid #eee;
}

.mapping-column:last-child {
    border-right: none;
}

.column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.column-header h5 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.field-count {
    font-size: 12px;
    color: #666;
    background: #f5f5f5;
    padding: 2px 8px;
    border-radius: 10px;
}

.fields-list {
    max-height: 300px;
    overflow-y: auto;
}

.field-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    margin-bottom: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.field-item:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.field-item.connected {
    background: #e8f5e8;
    border-color: #00a32a;
}

.field-item.required {
    border-left: 4px solid #d63638;
}

.field-info {
    flex-grow: 1;
}

.field-name {
    display: block;
    font-weight: 500;
    color: #333;
    font-size: 13px;
}

.field-type {
    display: block;
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    margin-top: 2px;
}

.required-indicator {
    color: #d63638;
    font-weight: bold;
    margin-left: 5px;
}

.field-connector {
    width: 12px;
    height: 12px;
    border: 2px solid #0073aa;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
    position: relative;
}

.field-connector.connected {
    background: #0073aa;
}

.mapping-connections {
    position: relative;
    background: #f9f9f9;
    border-left: 1px solid #eee;
    border-right: 1px solid #eee;
}

.connection-svg {
    position: absolute;
    top: 0;
    left: 0;
    pointer-events: none;
}

.mapping-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
}

.mapping-controls .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.mapping-status {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #666;
}

.status-indicator {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
}

.status-indicator.valid {
    background: #d4edda;
    color: #155724;
}

.status-indicator.invalid {
    background: #f8d7da;
    color: #721c24;
}

.mapping-preview {
    padding: 15px;
    border-top: 1px solid #eee;
    background: #f8f9fa;
}

.mapping-preview h5 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #333;
}

.preview-table table {
    font-size: 13px;
}

.add-custom-field {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

@media (max-width: 768px) {
    .mapping-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .mapping-connections {
        display: none;
    }
    
    .mapping-column {
        border-right: none;
        border-bottom: 1px solid #eee;
    }
}
</style>

<script>
(function($) {
    'use strict';
    
    class EnhancedFieldMapper {
        constructor() {
            this.mappings = new Map();
            this.formFields = [];
            this.mailchimpFields = [];
            this.init();
        }
        
        init() {
            this.loadFormFields();
            this.loadMailchimpFields();
            this.bindEvents();
            this.updatePreview();
        }
        
        loadFormFields() {
            // Load form fields via AJAX
            const formId = $('input[name="form_id"]').val();
            if (!formId) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_cf_get_form_fields',
                    form_id: formId,
                    nonce: $('#mailchimp_form_nonce').val()
                },
                success: (response) => {
                    if (response.success) {
                        this.renderFormFields(response.data);
                    }
                }
            });
        }
        
        renderFormFields(fields) {
            this.formFields = fields;
            const container = $('#form-fields-list');
            container.empty();
            
            fields.forEach(field => {
                const fieldHtml = `
                    <div class="field-item form-field ${field.required ? 'required' : ''}" 
                         data-field="${field.id}" data-type="${field.type}">
                        <div class="field-info">
                            <span class="field-name">${field.label}</span>
                            <span class="field-type">${field.type}</span>
                            ${field.required ? '<span class="required-indicator">*</span>' : ''}
                        </div>
                        <div class="field-connector" data-field="${field.id}"></div>
                    </div>
                `;
                container.append(fieldHtml);
            });
            
            $('#form-field-count').text(`${fields.length} fields`);
        }
        
        bindEvents() {
            // Auto-map button
            $('#auto-map-fields').on('click', () => this.autoMapFields());
            
            // Clear mappings button
            $('#clear-mappings').on('click', () => this.clearMappings());
            
            // Field connector clicks
            $(document).on('click', '.field-connector', (e) => this.handleConnectorClick(e));
            
            // Add custom field
            $('#add-custom-field').on('click', () => this.addCustomField());
        }
        
        autoMapFields() {
            this.clearMappings();
            
            // Smart auto-mapping logic
            this.formFields.forEach(formField => {
                const mapping = this.findBestMapping(formField);
                if (mapping) {
                    this.createMapping(formField.id, mapping.field);
                }
            });
            
            this.updatePreview();
            this.showNotification('Fields auto-mapped successfully!', 'success');
        }
        
        findBestMapping(formField) {
            const fieldName = formField.label.toLowerCase();
            const fieldType = formField.type;
            
            // Type-based mapping
            if (fieldType === 'email') {
                return { field: 'email_address', score: 100 };
            }
            
            // Label-based mapping
            if (fieldName.includes('first') || fieldName.includes('fname')) {
                return { field: 'FNAME', score: 90 };
            }
            
            if (fieldName.includes('last') || fieldName.includes('lname')) {
                return { field: 'LNAME', score: 90 };
            }
            
            if (fieldName.includes('phone') || fieldType === 'tel') {
                return { field: 'PHONE', score: 85 };
            }
            
            return null;
        }
        
        createMapping(formFieldId, mailchimpField) {
            this.mappings.set(formFieldId, mailchimpField);
            
            // Update UI
            $(`.form-field[data-field="${formFieldId}"]`).addClass('connected');
            $(`.mailchimp-field[data-field="${mailchimpField}"]`).addClass('connected');
            
            this.drawConnections();
        }
        
        clearMappings() {
            this.mappings.clear();
            $('.field-item').removeClass('connected');
            $('.connection-svg').empty();
            this.updatePreview();
        }
        
        updatePreview() {
            const tbody = $('#mapping-preview-body');
            tbody.empty();
            
            this.mappings.forEach((mailchimpField, formFieldId) => {
                const formField = this.formFields.find(f => f.id === formFieldId);
                const row = `
                    <tr>
                        <td>${formField ? formField.label : formFieldId}</td>
                        <td>${mailchimpField}</td>
                        <td>
                            <button type="button" class="button button-small remove-mapping" 
                                    data-form-field="${formFieldId}">
                                Remove
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
            
            // Update mapping count
            $('#mapping-count').text(`${this.mappings.size} mappings`);
            
            // Update required status
            const hasEmailMapping = Array.from(this.mappings.values()).includes('email_address');
            const statusEl = $('#required-status');
            
            if (hasEmailMapping) {
                statusEl.removeClass('invalid').addClass('valid').text('Email mapped ✓');
            } else {
                statusEl.removeClass('valid').addClass('invalid').text('Email required!');
            }
        }
        
        showNotification(message, type = 'info') {
            const notification = $(`
                <div class="notice notice-${type} is-dismissible" style="margin: 10px 0;">
                    <p>${message}</p>
                </div>
            `);
            
            $('.enhanced-field-mapping').prepend(notification);
            
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 3000);
        }
    }
    
    // Initialize when document is ready
    $(document).ready(() => {
        if ($('.enhanced-field-mapping').length) {
            new EnhancedFieldMapper();
        }
    });
    
})(jQuery);
</script> 