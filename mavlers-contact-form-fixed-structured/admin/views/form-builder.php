<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get form ID from URL
$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$form = null;
$form_fields = array();

if ($form_id) {
    global $wpdb;
    $forms_table = $wpdb->prefix . 'mavlers_forms';
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$forms_table} WHERE id = %d",
        $form_id
    ));

    if ($form) {
        $form_fields = json_decode($form->form_fields, true) ?? array();
    }
}

$form_name = $form ? $form->form_name : '';

// Create nonce for form builder
$form_builder_nonce = wp_create_nonce('mavlers_form_builder');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $form_id ? __('Edit Form', 'mavlers-contact-form') : __('Add New Form', 'mavlers-contact-form'); ?>
    </h1>
<script>
    var mavlersFormBuilder = {
        form_id: <?php echo intval($form_id); ?>,
        fields: <?php echo json_encode($form_fields); ?>,
        nonce: "<?php echo esc_js($form_builder_nonce); ?>"
    };
</script>

    <a href="<?php echo admin_url('admin.php?page=mavlers-forms'); ?>" class="page-title-action">
        <?php _e('Back to Forms', 'mavlers-contact-form'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="mavlers-form-builder">
        <!-- Form Builder Sidebar -->
        <div class="mavlers-form-sidebar">
            <div class="mavlers-field-types">
                <h3><?php _e('Available Fields', 'mavlers-contact-form'); ?></h3>
                <div class="mavlers-field-buttons">
                    <?php
                    $field_types = Mavlers_Form_Builder::get_instance()->get_field_types();
                    foreach ($field_types as $type => $field) :
                    ?>
                        <button type="button" class="mavlers-field-button" data-type="<?php echo esc_attr($type); ?>">
                            <span class="dashicons <?php echo esc_attr($field['icon']); ?>"></span>
                            <?php echo esc_html($field['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Form Builder Main Area -->
        <div class="mavlers-form-content">
            <div class="mavlers-form-settings">
                <div class="mavlers-form-header">
                    <input type="hidden" id="form-id" value="<?php echo esc_attr($form_id); ?>">
                    <input type="hidden" id="mavlers_nonce" value="<?php echo esc_attr($form_builder_nonce); ?>">
                    <input type="text" id="form-title" class="widefat" 
                           placeholder="<?php esc_attr_e('Enter form title', 'mavlers-contact-form'); ?>"
                           value="<?php echo $form ? esc_attr($form->form_name) : ''; ?>">
                    <div class="mavlers-form-actions">
                        <button type="button" class="button button-primary" id="save-form">
                            <?php echo $form_id ? __('Update Form', 'mavlers-contact-form') : __('Save Form', 'mavlers-contact-form'); ?>
                        </button>
                        <button type="button" class="button" id="preview-form">
                            <?php _e('Preview', 'mavlers-contact-form'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Form Preview Area -->
            <div class="mavlers-form-preview">
                <div class="mavlers-preview-header">
                    <h3><?php _e('Form Preview', 'mavlers-contact-form'); ?></h3>
                </div>
                <div class="mavlers-preview-content">
                    <div id="form-fields" class="mavlers-fields-container">
                        <?php if (!empty($form_fields)) : ?>
                            <?php foreach ($form_fields as $field) : ?>
                                <div class="mavlers-field" 
                                     data-field-id="<?php echo esc_attr($field['id']); ?>"
                                     data-field-type="<?php echo esc_attr($field['field_type']); ?>"
                                     data-field-data='<?php echo esc_attr(json_encode($field)); ?>'>
                                    <div class="mavlers-field-header">
                                        <span class="mavlers-field-title"><?php echo esc_html($field['field_label']); ?></span>
                                        <div class="mavlers-field-actions">
                                            <button type="button" class="mavlers-edit-field">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            <button type="button" class="mavlers-delete-field">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                            <span class="mavlers-field-move">
                                                <span class="dashicons dashicons-move"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mavlers-field-content">
                                        <?php echo Mavlers_Form_Builder::get_instance()->render_field_preview($field); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="mavlers-empty-form">
                                <p><?php _e('No fields added yet. Add fields from the sidebar.', 'mavlers-contact-form'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Field Settings Modal -->
<div id="field-settings-modal" class="mavlers-modal">
    <div class="mavlers-modal-content">
        <div class="mavlers-modal-header">
            <h3><?php _e('Field Settings', 'mavlers-contact-form'); ?></h3>
            <button type="button" class="mavlers-modal-close">&times;</button>
        </div>
        <div class="mavlers-modal-body"></div>
        <div class="mavlers-modal-footer">
            <button type="button" class="button mavlers-modal-cancel"><?php _e('Cancel', 'mavlers-contact-form'); ?></button>
            <button type="button" class="button button-primary" id="save-field-settings"><?php _e('Save Changes', 'mavlers-contact-form'); ?></button>
        </div>
    </div>
</div>

<?php wp_nonce_field('mavlers_form_builder', 'mavlers_nonce'); ?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Debug form ID
    console.log('Form ID from PHP:', <?php echo $form_id; ?>);
    console.log('Form ID from input:', $('#form-id').val());
    console.log('Nonce:', $('#mavlers_nonce').val());
    
    // Initialize form builder
    if (typeof FormBuilder !== 'undefined') {
        FormBuilder.init();
    }
});
</script> 