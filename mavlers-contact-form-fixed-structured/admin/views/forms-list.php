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
        'error' => __('An error occurred. Please try again.', 'mavlers-contact-form')
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
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Form Name', 'mavlers-contact-form'); ?></th>
                    <th scope="col"><?php _e('Shortcode', 'mavlers-contact-form'); ?></th>
                    <th scope="col"><?php _e('Created', 'mavlers-contact-form'); ?></th>
                    <th scope="col"><?php _e('Actions', 'mavlers-contact-form'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form): ?>
                    <tr>
                        <td><?php echo esc_html($form->form_name); ?></td>
                        <td><code>[mavlers_form id="<?php echo $form->id; ?>"]</code></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($form->created_at)); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=edit&form_id=' . $form->id); ?>" class="button button-small">
                                <?php _e('Edit', 'mavlers-contact-form'); ?>
                            </a>
                            <a href="#" class="button button-small button-link-delete mavlers-delete-form" data-form-id="<?php echo $form->id; ?>">
                                <?php _e('Delete', 'mavlers-contact-form'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.mavlers-forms-list .column-shortcode {
    width: 300px;
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
</style> 