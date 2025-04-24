<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get all forms from database
global $wpdb;
$table_name = $wpdb->prefix . 'mavlers_forms';
$forms = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Mavlers Contact Forms', 'mavlers-contact-form'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=mavlers-forms-new'); ?>" class="page-title-action">
        <?php _e('Add New', 'mavlers-contact-form'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Form updated successfully!', 'mavlers-contact-form'); ?></p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-title"><?php _e('Form Name', 'mavlers-contact-form'); ?></th>
                <th scope="col" class="manage-column column-shortcode"><?php _e('Shortcode', 'mavlers-contact-form'); ?></th>
                <th scope="col" class="manage-column column-entries"><?php _e('Entries', 'mavlers-contact-form'); ?></th>
                <th scope="col" class="manage-column column-date"><?php _e('Created', 'mavlers-contact-form'); ?></th>
                <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'mavlers-contact-form'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($forms)) : ?>
                <?php foreach ($forms as $form) : ?>
                    <tr>
                        <td class="column-title">
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=mavlers-forms-new&form_id=' . $form->id); ?>">
                                    <?php echo esc_html($form->form_name); ?>
                                </a>
                            </strong>
                        </td>
                        <td class="column-shortcode">
                            <code>[mavlers_form id="<?php echo $form->id; ?>"]</code>
                        </td>
                        <td class="column-entries">
                            <?php
                            $entries_table = $wpdb->prefix . 'mavlers_form_entries';
                            $count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM $entries_table WHERE form_id = %d",
                                $form->id
                            ));
                            echo esc_html($count);
                            ?>
                        </td>
                        <td class="column-date">
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($form->created_at))); ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo admin_url('admin.php?page=mavlers-forms-new&form_id=' . $form->id); ?>" class="button button-small">
                                <?php _e('Edit', 'mavlers-contact-form'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=mavlers-forms&action=delete&form_id=' . $form->id), 'delete_form_' . $form->id); ?>" 
                               class="button button-small button-link-delete" 
                               onclick="return confirm('<?php _e('Are you sure you want to delete this form?', 'mavlers-contact-form'); ?>');">
                                <?php _e('Delete', 'mavlers-contact-form'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5" class="column-title">
                        <?php _e('No forms found. Create your first form!', 'mavlers-contact-form'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div> 