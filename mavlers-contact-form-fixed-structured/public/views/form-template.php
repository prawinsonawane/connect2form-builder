<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get form settings
$settings = Mavlers_Settings::get_instance()->get_form_settings($form_id);
?>

<div class="mavlers-form-container" id="mavlers-form-<?php echo esc_attr($form_id); ?>">
    <?php if (!empty($settings['form_title'])) : ?>
        <h2 class="mavlers-form-title"><?php echo esc_html($settings['form_title']); ?></h2>
    <?php endif; ?>

    <?php if (!empty($settings['form_description'])) : ?>
        <div class="mavlers-form-description">
            <?php echo wp_kses_post($settings['form_description']); ?>
        </div>
    <?php endif; ?>

    <form class="mavlers-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
        <?php wp_nonce_field('mavlers_form_submission_nonce', 'mavlers_nonce'); ?>

        <?php foreach ($fields as $field) : 
            $field_meta = maybe_unserialize($field->field_meta);
            $required = isset($field_meta['required']) && $field_meta['required'] ? 'required' : '';
            $placeholder = isset($field_meta['placeholder']) ? $field_meta['placeholder'] : '';
        ?>
            <div class="mavlers-form-field">
                <?php if ($field->field_type !== 'html' && $field->field_type !== 'divider') : ?>
                    <label for="<?php echo esc_attr($field->field_name); ?>">
                        <?php echo esc_html($field->field_label); ?>
                        <?php if ($required) : ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                <?php endif; ?>

                <?php switch ($field->field_type) :
                    case 'text':
                    case 'email':
                    case 'number': ?>
                        <input type="<?php echo esc_attr($field->field_type); ?>"
                               name="<?php echo esc_attr($field->field_name); ?>"
                               id="<?php echo esc_attr($field->field_name); ?>"
                               class="mavlers-input"
                               <?php echo $required; ?>
                               <?php echo $placeholder ? 'placeholder="' . esc_attr($placeholder) . '"' : ''; ?>>
                        <?php break;

                    case 'textarea': ?>
                        <textarea name="<?php echo esc_attr($field->field_name); ?>"
                                  id="<?php echo esc_attr($field->field_name); ?>"
                                  class="mavlers-textarea"
                                  <?php echo $required; ?>
                                  <?php echo $placeholder ? 'placeholder="' . esc_attr($placeholder) . '"' : ''; ?>></textarea>
                        <?php break;

                    case 'select': ?>
                        <select name="<?php echo esc_attr($field->field_name); ?>"
                                id="<?php echo esc_attr($field->field_name); ?>"
                                class="mavlers-select"
                                <?php echo $required; ?>>
                            <?php if (!empty($field_meta['options'])) : ?>
                                <?php foreach ($field_meta['options'] as $option) : ?>
                                    <option value="<?php echo esc_attr($option); ?>">
                                        <?php echo esc_html($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php break;

                    case 'radio': ?>
                        <div class="mavlers-radio-group">
                            <?php if (!empty($field_meta['options'])) : ?>
                                <?php foreach ($field_meta['options'] as $option) : ?>
                                    <label class="mavlers-radio-label">
                                        <input type="radio"
                                               name="<?php echo esc_attr($field->field_name); ?>"
                                               value="<?php echo esc_attr($option); ?>"
                                               <?php echo $required; ?>>
                                        <?php echo esc_html($option); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php break;

                    case 'checkbox': ?>
                        <div class="mavlers-checkbox-group">
                            <?php if (!empty($field_meta['options'])) : ?>
                                <?php foreach ($field_meta['options'] as $option) : ?>
                                    <label class="mavlers-checkbox-label">
                                        <input type="checkbox"
                                               name="<?php echo esc_attr($field->field_name); ?>[]"
                                               value="<?php echo esc_attr($option); ?>"
                                               <?php echo $required; ?>>
                                        <?php echo esc_html($option); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php break;

                    case 'file': ?>
                        <input type="file"
                               name="<?php echo esc_attr($field->field_name); ?>"
                               id="<?php echo esc_attr($field->field_name); ?>"
                               class="mavlers-file"
                               <?php echo $required; ?>>
                        <?php break;

                    case 'html': ?>
                        <div class="mavlers-html-content">
                            <?php echo wp_kses_post($field_meta['content']); ?>
                        </div>
                        <?php break;

                    case 'divider': ?>
                        <hr class="mavlers-divider" style="
                            border-style: <?php echo esc_attr($field_meta['divider_style'] ?? 'solid'); ?>;
                            border-color: <?php echo esc_attr($field_meta['divider_color'] ?? '#ddd'); ?>;
                            width: <?php echo esc_attr($field_meta['divider_width'] === 'half' ? '50%' : ($field_meta['divider_width'] === 'third' ? '33.33%' : '100%')); ?>;
                        ">
                        <?php if (!empty($field_meta['divider_text'])) : ?>
                            <div class="mavlers-divider-text">
                                <?php echo esc_html($field_meta['divider_text']); ?>
                            </div>
                        <?php endif; ?>
                        <?php break;
                endswitch; ?>

                <?php if (!empty($field_meta['description'])) : ?>
                    <p class="mavlers-field-description">
                        <?php echo esc_html($field_meta['description']); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="mavlers-form-submit">
            <button type="submit" class="mavlers-submit-button">
                <?php echo esc_html($settings['submit_button_text'] ?? __('Submit', 'mavlers-contact-form')); ?>
            </button>
        </div>

        <div class="mavlers-form-message" style="display: none;"></div>
    </form>
</div>

<style>
.mavlers-form-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.mavlers-form-title {
    margin-bottom: 20px;
    font-size: 24px;
    font-weight: 600;
}

.mavlers-form-description {
    margin-bottom: 30px;
    color: #666;
}

.mavlers-form-field {
    margin-bottom: 20px;
}

.mavlers-form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.mavlers-form-field .required {
    color: #dc3545;
    margin-left: 3px;
}

.mavlers-input,
.mavlers-textarea,
.mavlers-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.mavlers-textarea {
    min-height: 100px;
    resize: vertical;
}

.mavlers-radio-group,
.mavlers-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.mavlers-radio-label,
.mavlers-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: normal;
}

.mavlers-field-description {
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

.mavlers-submit-button {
    background-color: #007bff;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

.mavlers-submit-button:hover {
    background-color: #0056b3;
}

.mavlers-form-message {
    margin-top: 20px;
    padding: 10px;
    border-radius: 4px;
}

.mavlers-form-message.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.mavlers-form-message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.mavlers-divider {
    margin: 30px 0;
    border: none;
    border-top: 1px solid #ddd;
}

.mavlers-divider-text {
    text-align: center;
    margin-top: -12px;
    background-color: #fff;
    display: inline-block;
    padding: 0 10px;
    color: #666;
}

.mavlers-html-content {
    margin: 20px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    const form = $('#mavlers-form-<?php echo esc_js($form_id); ?> form');
    const messageContainer = form.find('.mavlers-form-message');

    form.on('submit', function(e) {
        e.preventDefault();

        // Clear previous messages
        messageContainer.removeClass('success error').hide();

        // Disable submit button
        const submitButton = form.find('.mavlers-submit-button');
        submitButton.prop('disabled', true);

        // Collect form data
        const formData = new FormData(this);

        // Add action
        formData.append('action', 'mavlers_submit_form');

        // Submit form
        $.ajax({
            url: mavlersForm.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    messageContainer
                        .addClass('success')
                        .html(response.data.message)
                        .show();
                    
                    // Reset form
                    form[0].reset();
                } else {
                    messageContainer
                        .addClass('error')
                        .html(response.data.message)
                        .show();

                    // Show field errors if any
                    if (response.data.errors) {
                        Object.keys(response.data.errors).forEach(function(fieldName) {
                            const field = form.find('[name="' + fieldName + '"]');
                            field.addClass('error');
                            field.after('<span class="field-error">' + response.data.errors[fieldName] + '</span>');
                        });
                    }
                }
            },
            error: function() {
                messageContainer
                    .addClass('error')
                    .html('<?php esc_attr_e('An error occurred. Please try again.', 'mavlers-contact-form'); ?>')
                    .show();
            },
            complete: function() {
                // Re-enable submit button
                submitButton.prop('disabled', false);
            }
        });
    });

    // Remove error styling on field change
    form.find('input, textarea, select').on('change', function() {
        $(this).removeClass('error');
        $(this).next('.field-error').remove();
    });
});
</script> 