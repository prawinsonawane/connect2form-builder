<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$form = null;
$form_fields = array();

if ($form_id) {
    $table_name = $wpdb->prefix . 'mavlers_forms';
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $form_id
    ));

    if ($form) {
        $form_fields = json_decode($form->form_fields, true);
        if (!is_array($form_fields)) {
            $form_fields = array();
        }
    }
}

$form_name = $form ? $form->form_name : '';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $form_id ? __('Edit Form', 'mavlers-contact-form') : __('Add New Form', 'mavlers-contact-form'); ?>
    </h1>
    <a href="<?php echo admin_url('admin.php?page=mavlers-forms'); ?>" class="page-title-action">
        <?php _e('Back to Forms', 'mavlers-contact-form'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="mavlers-form-builder">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('mavlers_save_form', 'mavlers_form_nonce'); ?>
            <input type="hidden" name="action" value="mavlers_save_form">
            <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">

            <div class="form-builder-header">
                <div class="form-name">
                    <label for="form_name"><?php _e('Form Name', 'mavlers-contact-form'); ?></label>
                    <input type="text" id="form_name" name="form_name" value="<?php echo esc_attr($form_name); ?>" required>
                </div>
            </div>

            <div class="form-builder-content">
                <div class="form-fields">
                    <h3><?php _e('Form Fields', 'mavlers-contact-form'); ?></h3>
                    <div id="form-fields-container">
                        <?php if (!empty($form_fields)) : ?>
                            <?php foreach ($form_fields as $field) : ?>
                                <div class="form-field" 
                                     data-field-id="<?php echo esc_attr($field['id']); ?>"
                                     data-field-type="<?php echo esc_attr($field['type']); ?>"
                                     data-field-data='<?php echo esc_attr(json_encode($field)); ?>'>
                                    <div class="field-header">
                                        <span class="field-title"><?php echo esc_html($field['label']); ?></span>
                                        <div class="field-actions">
                                            <button type="button" class="edit-field">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            <button type="button" class="delete-field">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="field-preview">
                                        <?php echo $this->get_field_preview($field); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-form-message">
                                <?php _e('Click on fields from the sidebar to add them to your form', 'mavlers-contact-form'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize form builder
    const formBuilder = {
        init: function() {
            this.bindEvents();
            this.initFieldTypes();
        },

        bindEvents: function() {
            // Field type buttons
            $('.field-type-button').on('click', (e) => {
                e.preventDefault();
                const fieldType = $(e.currentTarget).data('type');
                this.addField(fieldType);
            });

            // Field actions
            $(document).on('click', '.edit-field', (e) => {
                e.preventDefault();
                const $field = $(e.currentTarget).closest('.form-field');
                this.editField($field);
            });

            $(document).on('click', '.delete-field', (e) => {
                e.preventDefault();
                const $field = $(e.currentTarget).closest('.form-field');
                this.deleteField($field);
            });
        },

        initFieldTypes: function() {
            // Initialize available field types
            const fieldTypes = <?php echo json_encode($this->get_field_types()); ?>;
            this.fieldTypes = fieldTypes;
        },

        addField: function(type) {
            const fieldData = {
                id: 'field_' + Date.now(),
                type: type,
                label: this.fieldTypes[type].label,
                required: false
            };

            this.saveField(fieldData);
        },

        editField: function($field) {
            const fieldData = JSON.parse($field.data('field-data'));
            this.openFieldSettings(fieldData);
        },

        deleteField: function($field) {
            if (!confirm('<?php _e('Are you sure you want to delete this field?', 'mavlers-contact-form'); ?>')) {
                return;
            }

            const fieldId = $field.data('field-id');
            this.removeField(fieldId);
        },

        saveField: function(fieldData) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_save_field',
                    nonce: mavlersFormBuilder.nonce,
                    form_id: <?php echo $form_id; ?>,
                    field_data: fieldData
                },
                success: (response) => {
                    if (response.success) {
                        this.updateFieldInUI(response.data.field);
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },

        removeField: function(fieldId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mavlers_delete_field',
                    nonce: mavlersFormBuilder.nonce,
                    form_id: <?php echo $form_id; ?>,
                    field_id: fieldId
                },
                success: (response) => {
                    if (response.success) {
                        $(`[data-field-id="${fieldId}"]`).remove();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },

        updateFieldInUI: function(fieldData) {
            const $existingField = $(`[data-field-id="${fieldData.id}"]`);
            const fieldHtml = this.getFieldHtml(fieldData);

            if ($existingField.length) {
                $existingField.replaceWith(fieldHtml);
            } else {
                $('#form-fields-container').append(fieldHtml);
                $('.empty-form-message').hide();
            }
        },

        getFieldHtml: function(fieldData) {
            return `
                <div class="form-field" 
                     data-field-id="${fieldData.id}"
                     data-field-type="${fieldData.type}"
                     data-field-data='${JSON.stringify(fieldData)}'>
                    <div class="field-header">
                        <span class="field-title">${fieldData.label}</span>
                        <div class="field-actions">
                            <button type="button" class="edit-field">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="delete-field">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="field-preview">
                        ${this.getFieldPreview(fieldData)}
                    </div>
                </div>
            `;
        },

        getFieldPreview: function(fieldData) {
            // Implement field preview based on type
            switch (fieldData.type) {
                case 'text':
                    return `<input type="text" placeholder="${fieldData.placeholder || ''}" ${fieldData.required ? 'required' : ''}>`;
                case 'textarea':
                    return `<textarea placeholder="${fieldData.placeholder || ''}" ${fieldData.required ? 'required' : ''}></textarea>`;
                case 'email':
                    return `<input type="email" placeholder="${fieldData.placeholder || ''}" ${fieldData.required ? 'required' : ''}>`;
                case 'select':
                    return `<select ${fieldData.required ? 'required' : ''}>
                        ${(fieldData.options || []).map(option => `
                            <option value="${option.value}">${option.label}</option>
                        `).join('')}
                    </select>`;
                default:
                    return '';
            }
        }
    };

    formBuilder.init();
});
</script> 