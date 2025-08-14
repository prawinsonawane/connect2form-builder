<?php
/**
 * Mailchimp Custom Fields Manager Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get Mailchimp integration instance
$mailchimp_integration = null;
if (class_exists('Mavlers_CF_Mailchimp_Integration')) {
    global $mavlers_cf_integrations;
    $mailchimp_integration = $mavlers_cf_integrations['mailchimp'] ?? null;
}

$is_connected = $mailchimp_integration && $mailchimp_integration->is_globally_connected();
$audiences = array();

if ($is_connected) {
    $audiences_result = $mailchimp_integration->get_audiences();
    if ($audiences_result['success']) {
        $audiences = $audiences_result['data'];
    }
}
?>

<div class="mailchimp-custom-fields-manager">
    <?php if (!$is_connected): ?>
        <div class="connection-required">
            <div class="connection-notice">
                <h3>🔗 Connection Required</h3>
                <p><?php _e('Please connect to Mailchimp first to manage custom fields.', 'mavlers-cf'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=mavlers-cf-integrations&integration=mailchimp'); ?>" 
                   class="button button-primary">
                    <?php _e('Configure Mailchimp', 'mavlers-cf'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        
        <!-- Header -->
        <div class="custom-fields-header">
            <h3>
                🔧 <?php _e('Custom Fields Manager', 'mavlers-cf'); ?>
            </h3>
            <div class="custom-fields-actions">
                <button type="button" id="sync-fields" class="button" title="<?php _e('Sync with Mailchimp', 'mavlers-cf'); ?>">
                    🔄 <?php _e('Sync Fields', 'mavlers-cf'); ?>
                </button>
                <button type="button" id="create-field" class="button button-primary">
                    ➕ <?php _e('Create Field', 'mavlers-cf'); ?>
                </button>
            </div>
        </div>

        <!-- Audience Selector -->
        <div class="audience-selector">
            <label for="audience-selector">
                <?php _e('Select Mailchimp Audience:', 'mavlers-cf'); ?>
            </label>
            <select id="audience-selector" class="audience-selector-dropdown">
                <option value=""><?php _e('-- Select an audience --', 'mavlers-cf'); ?></option>
                <?php foreach ($audiences as $audience): ?>
                    <option value="<?php echo esc_attr($audience['id']); ?>">
                        <?php echo esc_html($audience['name']); ?>
                        (<?php echo number_format($audience['stats']['member_count']); ?> <?php _e('members', 'mavlers-cf'); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Custom Fields Grid -->
        <div class="custom-fields-grid">
            <!-- Merge Fields Section -->
            <div class="fields-section">
                <div class="fields-section-header">
                    <h4>
                        📝 <?php _e('Merge Fields', 'mavlers-cf'); ?>
                    </h4>
                    <div class="section-actions">
                        <button type="button" class="button button-small" id="refresh-merge-fields">
                            🔄 <?php _e('Refresh', 'mavlers-cf'); ?>
                        </button>
                    </div>
                </div>
                <div class="fields-list" id="merge-fields-list">
                    <div class="empty-state">
                        <div class="empty-state-icon">📄</div>
                        <h4><?php _e('Select an audience', 'mavlers-cf'); ?></h4>
                        <p><?php _e('Choose a Mailchimp audience to view and manage custom fields.', 'mavlers-cf'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Interest Categories Section -->
            <div class="fields-section">
                <div class="fields-section-header">
                    <h4>
                        🎯 <?php _e('Interest Categories', 'mavlers-cf'); ?>
                    </h4>
                    <div class="section-actions">
                        <button type="button" class="button button-small" id="refresh-interests">
                            🔄 <?php _e('Refresh', 'mavlers-cf'); ?>
                        </button>
                    </div>
                </div>
                <div class="fields-list" id="interest-categories-list">
                    <div class="empty-state">
                        <div class="empty-state-icon">🎯</div>
                        <h4><?php _e('Select an audience', 'mavlers-cf'); ?></h4>
                        <p><?php _e('Choose a Mailchimp audience to view interest categories.', 'mavlers-cf'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Field Modal -->
        <div class="field-modal-overlay">
            <div class="field-modal">
                <div class="field-modal-header">
                    <h4 id="modal-title"><?php _e('Create Merge Field', 'mavlers-cf'); ?></h4>
                    <button type="button" class="field-modal-close">&times;</button>
                </div>
                
                <form id="field-form" class="field-modal-body">
                    <div class="form-group">
                        <label for="field-name"><?php _e('Field Name', 'mavlers-cf'); ?> *</label>
                        <input type="text" id="field-name" name="field-name" required 
                               placeholder="<?php _e('e.g., Company Name', 'mavlers-cf'); ?>">
                        <div class="form-help">
                            <?php _e('The display name for this field that subscribers will see.', 'mavlers-cf'); ?>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="field-tag"><?php _e('Merge Tag', 'mavlers-cf'); ?> *</label>
                            <input type="text" id="field-tag" name="field-tag" required 
                                   placeholder="<?php _e('e.g., COMPANY', 'mavlers-cf'); ?>"
                                   pattern="[A-Z0-9_]+" title="<?php _e('Only uppercase letters, numbers, and underscores', 'mavlers-cf'); ?>">
                            <div class="form-help">
                                <?php _e('Unique identifier used in email templates (A-Z, 0-9, _).', 'mavlers-cf'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="field-type"><?php _e('Field Type', 'mavlers-cf'); ?> *</label>
                            <select id="field-type" name="field-type" required>
                                <option value="text"><?php _e('Text', 'mavlers-cf'); ?></option>
                                <option value="number"><?php _e('Number', 'mavlers-cf'); ?></option>
                                <option value="phone"><?php _e('Phone', 'mavlers-cf'); ?></option>
                                <option value="date"><?php _e('Date', 'mavlers-cf'); ?></option>
                                <option value="birthday"><?php _e('Birthday', 'mavlers-cf'); ?></option>
                                <option value="address"><?php _e('Address', 'mavlers-cf'); ?></option>
                                <option value="url"><?php _e('Website URL', 'mavlers-cf'); ?></option>
                                <option value="dropdown"><?php _e('Dropdown', 'mavlers-cf'); ?></option>
                                <option value="radio"><?php _e('Radio Buttons', 'mavlers-cf'); ?></option>
                                <option value="zip"><?php _e('ZIP Code', 'mavlers-cf'); ?></option>
                                <option value="imageurl"><?php _e('Image URL', 'mavlers-cf'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="field-help-text"><?php _e('Help Text', 'mavlers-cf'); ?></label>
                        <textarea id="field-help-text" name="field-help-text" rows="2" 
                                  placeholder="<?php _e('Optional help text for subscribers', 'mavlers-cf'); ?>"></textarea>
                        <div class="form-help">
                            <?php _e('Additional instructions or description for this field.', 'mavlers-cf'); ?>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="field-default-value"><?php _e('Default Value', 'mavlers-cf'); ?></label>
                            <input type="text" id="field-default-value" name="field-default-value" 
                                   placeholder="<?php _e('Optional default value', 'mavlers-cf'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="field-display-order"><?php _e('Display Order', 'mavlers-cf'); ?></label>
                            <input type="number" id="field-display-order" name="field-display-order" 
                                   min="0" placeholder="0">
                            <div class="form-help">
                                <?php _e('Order in signup forms (0 = automatic)', 'mavlers-cf'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Choices Manager (for dropdown/radio fields) -->
                    <div id="choices-manager" class="form-group choices-manager" style="display: none;">
                        <div class="choices-header">
                            <h5><?php _e('Available Choices', 'mavlers-cf'); ?></h5>
                            <button type="button" id="add-choice" class="add-choice">
                                ➕ <?php _e('Add Choice', 'mavlers-cf'); ?>
                            </button>
                        </div>
                        <div id="choices-container">
                            <!-- Dynamic choices will be added here -->
                        </div>
                    </div>

                    <!-- Field Options -->
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="field-required" name="field-required">
                            <label for="field-required"><?php _e('Required field', 'mavlers-cf'); ?></label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="field-public" name="field-public" checked>
                            <label for="field-public"><?php _e('Show in signup forms', 'mavlers-cf'); ?></label>
                        </div>
                    </div>
                </form>

                <div class="field-modal-footer">
                    <button type="button" class="button" onclick="MailchimpCustomFields.closeFieldModal()">
                        <?php _e('Cancel', 'mavlers-cf'); ?>
                    </button>
                    <button type="submit" form="field-form" id="save-field" class="button button-primary">
                        <?php _e('Create Field', 'mavlers-cf'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Field Mapping Guide -->
        <div class="field-mapping-guide">
            <h3><?php _e('📋 Field Mapping Guide', 'mavlers-cf'); ?></h3>
            <p><?php _e('Custom fields you create here will be available for mapping in your contact forms. Here\'s how different field types work:', 'mavlers-cf'); ?></p>
            
            <div class="mapping-examples">
                <div class="mapping-example">
                    <h4>📝 <?php _e('Text Fields', 'mavlers-cf'); ?></h4>
                    <p><?php _e('Perfect for names, job titles, company names, or any short text input.', 'mavlers-cf'); ?></p>
                    <code><?php _e('Example: COMPANY, JOBTITLE, WEBSITE', 'mavlers-cf'); ?></code>
                </div>

                <div class="mapping-example">
                    <h4>🔢 <?php _e('Number Fields', 'mavlers-cf'); ?></h4>
                    <p><?php _e('Use for numeric data like employee count, budget, or rating scores.', 'mavlers-cf'); ?></p>
                    <code><?php _e('Example: EMPLOYEES, BUDGET, RATING', 'mavlers-cf'); ?></code>
                </div>

                <div class="mapping-example">
                    <h4>📞 <?php _e('Phone Fields', 'mavlers-cf'); ?></h4>
                    <p><?php _e('Automatically formats phone numbers and validates input.', 'mavlers-cf'); ?></p>
                    <code><?php _e('Example: WORKPHONE, MOBILE', 'mavlers-cf'); ?></code>
                </div>

                <div class="mapping-example">
                    <h4>📅 <?php _e('Date Fields', 'mavlers-cf'); ?></h4>
                    <p><?php _e('Collect dates like anniversary, start date, or any important dates.', 'mavlers-cf'); ?></p>
                    <code><?php _e('Example: ANNIVERSARY, STARTDATE', 'mavlers-cf'); ?></code>
                </div>

                <div class="mapping-example">
                    <h4>📋 <?php _e('Dropdown/Radio Fields', 'mavlers-cf'); ?></h4>
                    <p><?php _e('Provide predefined options for consistent data collection.', 'mavlers-cf'); ?></p>
                    <code><?php _e('Example: INDUSTRY, SIZE, SOURCE', 'mavlers-cf'); ?></code>
                </div>
            </div>

            <div class="mapping-tips">
                <h4><?php _e('💡 Pro Tips', 'mavlers-cf'); ?></h4>
                <ul>
                    <li><?php _e('Use clear, descriptive names that your team will understand', 'mavlers-cf'); ?></li>
                    <li><?php _e('Keep merge tags short but meaningful (e.g., COMPANY instead of COMPANYNAME)', 'mavlers-cf'); ?></li>
                    <li><?php _e('Mark important fields as required to ensure data quality', 'mavlers-cf'); ?></li>
                    <li><?php _e('Use help text to guide subscribers on what information to provide', 'mavlers-cf'); ?></li>
                    <li><?php _e('Test your fields in a form before launching to ensure they work as expected', 'mavlers-cf'); ?></li>
                </ul>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
/* Connection Required Styles */
.connection-required {
    max-width: 600px;
    margin: 40px auto;
    text-align: center;
}

.connection-notice {
    padding: 40px;
    background: white;
    border: 2px dashed #e3e8ee;
    border-radius: 12px;
    color: #6c757d;
}

.connection-notice h3 {
    margin: 0 0 16px 0;
    color: #374151;
    font-size: 24px;
}

.connection-notice p {
    margin: 0 0 20px 0;
    font-size: 16px;
    line-height: 1.5;
}

/* Field Mapping Guide Styles */
.field-mapping-guide {
    margin-top: 40px;
    padding: 30px;
    background: white;
    border: 1px solid #e3e8ee;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.field-mapping-guide h3 {
    margin: 0 0 16px 0;
    color: #374151;
    font-size: 20px;
}

.field-mapping-guide > p {
    margin: 0 0 25px 0;
    color: #6c757d;
    line-height: 1.6;
}

.mapping-examples {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.mapping-example {
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
}

.mapping-example h4 {
    margin: 0 0 8px 0;
    color: #495057;
    font-size: 16px;
}

.mapping-example p {
    margin: 0 0 10px 0;
    color: #6c757d;
    font-size: 14px;
    line-height: 1.4;
}

.mapping-example code {
    display: block;
    background: #e9ecef;
    color: #495057;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-family: monospace;
}

.mapping-tips {
    padding: 20px;
    background: #e7f3ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
}

.mapping-tips h4 {
    margin: 0 0 12px 0;
    color: #004085;
    font-size: 16px;
}

.mapping-tips ul {
    margin: 0;
    padding-left: 20px;
    color: #004085;
}

.mapping-tips li {
    margin-bottom: 6px;
    line-height: 1.4;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .mapping-examples {
        grid-template-columns: 1fr;
    }
    
    .field-mapping-guide {
        margin: 20px;
        padding: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Auto-generate merge tag from field name
    $('#field-name').on('input', function() {
        if (!$('#field-tag').prop('readonly')) {
            let tag = $(this).val()
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, '_')
                .replace(/_+/g, '_')
                .replace(/^_|_$/g, '');
            
            $('#field-tag').val(tag);
        }
    });

    // Update modal title when editing
    $(document).on('click', '.edit-field', function() {
        setTimeout(function() {
            $('#save-field').text('<?php _e('Update Field', 'mavlers-cf'); ?>');
        }, 100);
    });

    // Reset modal title when creating
    $(document).on('click', '#create-field', function() {
        setTimeout(function() {
            $('#save-field').text('<?php _e('Create Field', 'mavlers-cf'); ?>');
        }, 100);
    });
});
</script> 