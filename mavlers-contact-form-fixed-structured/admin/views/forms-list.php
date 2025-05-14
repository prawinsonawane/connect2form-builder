<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$forms_table = $wpdb->prefix . 'mavlers_forms';
$forms = $wpdb->get_results("SELECT * FROM $forms_table ORDER BY created_at DESC");

// Register and enqueue admin scripts
wp_register_script(
    'mavlers-admin',
    MAVLERS_FORM_PLUGIN_URL . 'assets/js/admin.js',
    array('jquery'),
    MAVLERS_FORM_VERSION,
    true
);

wp_localize_script('mavlers-admin', 'mavlersForms', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('mavlers_forms_nonce'),
    'strings' => array(
        'deleteConfirm' => __('Are you sure you want to delete this form? This action cannot be undone.', 'mavlers-contact-form'),
        'error' => __('An error occurred. Please try again.', 'mavlers-contact-form'),
        'noForms' => __('No forms found. Create your first form to get started!', 'mavlers-contact-form')
    )
));

wp_enqueue_script('mavlers-admin');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Mavlers Forms', 'mavlers-contact-form'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=mavlers-forms-new'); ?>" class="page-title-action"><?php _e('Add New', 'mavlers-contact-form'); ?></a>
    <hr class="wp-header-end">

    <?php if (empty($forms)): ?>
        <div class="notice notice-info">
            <p><?php _e('No forms found. Create your first form to get started!', 'mavlers-contact-form'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped mavlers-forms-list">
            <thead>
                <tr>
                    <th scope="col" class="column-id"><?php _e('ID', 'mavlers-contact-form'); ?></th>
                    <th scope="col" class="column-title"><?php _e('Form Title', 'mavlers-contact-form'); ?></th>
                    <th scope="col" class="column-shortcode"><?php _e('Shortcode', 'mavlers-contact-form'); ?></th>
                    <th scope="col" class="column-status"><?php _e('Status', 'mavlers-contact-form'); ?></th>
                    <th scope="col" class="column-created"><?php _e('Created', 'mavlers-contact-form'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form): ?>
                    <tr data-form-id="<?php echo esc_attr($form->id); ?>">
                        <td class="column-id"><?php echo esc_html($form->id); ?></td>
                        <td class="column-title">
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=edit&form_id=' . $form->id); ?>" class="row-title">
                                    <?php echo esc_html($form->form_name); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=edit&form_id=' . $form->id); ?>">
                                        <?php _e('Edit', 'mavlers-contact-form'); ?>
                                    </a> |
                                </span>
                                <span class="settings">
                                    <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=settings&form_id=' . $form->id); ?>">
                                        <?php _e('Settings', 'mavlers-contact-form'); ?>
                                    </a> |
                                </span>
                                <span class="entries">
                                    <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=entries&form_id=' . $form->id); ?>">
                                        <?php _e('Entries', 'mavlers-contact-form'); ?>
                                    </a> |
                                </span>
                                <span class="duplicate">
                                    <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=duplicate&form_id=' . $form->id); ?>">
                                        <?php _e('Duplicate', 'mavlers-contact-form'); ?>
                                    </a> |
                                </span>
                                <span class="preview">
                                    <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=preview&form_id=' . $form->id); ?>" target="_blank">
                                        <?php _e('Preview', 'mavlers-contact-form'); ?>
                                    </a> |
                                </span>
                                <span class="delete">
                                    <a href="#" class="mavlers-delete-form" data-form-id="<?php echo $form->id; ?>">
                                        <?php _e('Delete', 'mavlers-contact-form'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-shortcode">
                            <code>[mavlers_form id="<?php echo $form->id; ?>"]</code>
                            <button class="button button-small copy-shortcode" data-shortcode='[mavlers_form id="<?php echo $form->id; ?>"]'>
                                <?php _e('Copy', 'mavlers-contact-form'); ?>
                            </button>
                        </td>
                        <td class="column-status">
                            <span class="mavlers-form-status <?php echo esc_attr($form->status); ?>">
                                <?php echo esc_html(ucfirst($form->status)); ?>
                            </span>
                        </td>
                        <td class="column-created">
                            <?php echo date_i18n(get_option('date_format'), strtotime($form->created_at)); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.mavlers-forms-list .column-id {
    width: 30px;
}
.mavlers-forms-list .column-title {
    width: 45%;
}
.mavlers-forms-list .column-shortcode {
    width: 300px;
}
.mavlers-forms-list .column-status {
    width: 100px;
}
.mavlers-forms-list .column-created {
    width: 120px;
}
.mavlers-forms-list .copy-shortcode {
    margin-left: 10px;
}
.mavlers-form-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    line-height: 1.4;
}
.mavlers-form-status.active {
    background-color: #dff0d8;
    color: #3c763d;
}
.mavlers-form-status.inactive {
    background-color: #f2dede;
    color: #a94442;
}
.mavlers-form-status.draft {
    background-color: #fcf8e3;
    color: #8a6d3b;
}
.row-actions {
    visibility: hidden;
}
tr:hover .row-actions {
    visibility: visible;
}
.row-actions span {
    padding: 0;
}
.preview {
    float: none;
}
</style> 