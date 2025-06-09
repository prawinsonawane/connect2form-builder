<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get all forms
global $wpdb;
$forms_table = $wpdb->prefix . 'mavlers_forms';
$forms = $wpdb->get_results("SELECT * FROM {$forms_table} ORDER BY created_at DESC");

// Get form submissions count
$submissions_table = $wpdb->prefix . 'mavlers_form_entries';
$submissions_count = array();
foreach ($forms as $form) {
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d",
        $form->id
    ));
    $submissions_count[$form->id] = $count;
}

// Get form analytics data
$analytics_data = array();
foreach ($forms as $form) {
    $analytics_data[$form->id] = array(
        'views' => get_post_meta($form->id, '_mavlers_form_views', true) ?: 0,
        'submissions' => $submissions_count[$form->id] ?: 0,
        'conversion_rate' => 0
    );
    
    if ($analytics_data[$form->id]['views'] > 0) {
        $analytics_data[$form->id]['conversion_rate'] = round(
            ($analytics_data[$form->id]['submissions'] / $analytics_data[$form->id]['views']) * 100,
            2
        );
    }
}

// Register and enqueue admin scripts
wp_register_script(
    'mavlers-admin',
    MAVLERS_CONTACT_FORM_PLUGIN_URL . 'assets/js/admin.js',
    array('jquery'),
    MAVLERS_CONTACT_FORM_VERSION,
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
    <h1 class="wp-heading-inline">
        <?php _e('Forms', 'mavlers-contact-form'); ?>
    </h1>
    <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=new'); ?>" class="page-title-action">
        <?php _e('Add New', 'mavlers-contact-form'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (empty($forms)) : ?>
        <div class="notice notice-info">
            <p><?php _e('No forms found. Create your first form!', 'mavlers-contact-form'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-title column-primary">
                        <?php _e('Form Name', 'mavlers-contact-form'); ?>
                    </th>
                    <th scope="col" class="manage-column column-views">
                        <?php _e('Views', 'mavlers-contact-form'); ?>
                    </th>
                    <th scope="col" class="manage-column column-submissions">
                        <?php _e('Submissions', 'mavlers-contact-form'); ?>
                    </th>
                    <th scope="col" class="manage-column column-conversion">
                        <?php _e('Conversion Rate', 'mavlers-contact-form'); ?>
                    </th>
                    <th scope="col" class="manage-column column-date">
                        <?php _e('Created', 'mavlers-contact-form'); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Actions', 'mavlers-contact-form'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form) : ?>
                    <tr>
                        <td class="title column-title has-row-actions column-primary">
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=mavlers-forms&action=edit&form_id=' . $form->id); ?>">
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
                                <span class="duplicate">
                                    <a href="#" class="duplicate-form" data-form-id="<?php echo $form->id; ?>">
                                        <?php _e('Duplicate', 'mavlers-contact-form'); ?>
                                    </a> |
                                </span>
                                <span class="trash">
                                    <a href="#" class="delete-form" data-form-id="<?php echo $form->id; ?>">
                                        <?php _e('Delete', 'mavlers-contact-form'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-views">
                            <?php echo number_format($analytics_data[$form->id]['views']); ?>
                        </td>
                        <td class="column-submissions">
                            <?php echo number_format($analytics_data[$form->id]['submissions']); ?>
                        </td>
                        <td class="column-conversion">
                            <?php echo $analytics_data[$form->id]['conversion_rate']; ?>%
                        </td>
                        <td class="column-date">
                            <?php echo date_i18n(get_option('date_format'), strtotime($form->created_at)); ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo admin_url('admin.php?page=mavlers-forms-submissions&form_id=' . $form->id); ?>" class="button">
                                <?php _e('View Submissions', 'mavlers-contact-form'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=mavlers-forms-analytics&form_id=' . $form->id); ?>" class="button">
                                <?php _e('View Analytics', 'mavlers-contact-form'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle form deletion
    $('.delete-form').on('click', function(e) {
        e.preventDefault();
        if (!confirm('<?php _e('Are you sure you want to delete this form? This action cannot be undone.', 'mavlers-contact-form'); ?>')) {
            return;
        }

        var formId = $(this).data('form-id');
        $.post(ajaxurl, {
            action: 'mavlers_delete_form',
            form_id: formId,
            nonce: '<?php echo wp_create_nonce('mavlers_forms_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('<?php _e('Error deleting form. Please try again.', 'mavlers-contact-form'); ?>');
            }
        });
    });

    // Handle form duplication
    $('.duplicate-form').on('click', function(e) {
        e.preventDefault();
        var formId = $(this).data('form-id');
        $.post(ajaxurl, {
            action: 'mavlers_duplicate_form',
            form_id: formId,
            nonce: '<?php echo wp_create_nonce('mavlers_forms_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('<?php _e('Error duplicating form. Please try again.', 'mavlers-contact-form'); ?>');
            }
        });
    });
});
</script>

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