<?php
if (!defined('WPINC')) {
    die;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Mavlers_CF_Forms_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct(array(
            'singular' => 'form',
            'plural'   => 'forms',
            'ajax'     => false
        ));
    }

    /**
     * Prepare items for the table
     */
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_cf_forms';

        // Set up pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Set up sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_sql_orderby($_REQUEST['order']) : 'DESC';

        // Set up search
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $where = '';
        if (!empty($search)) {
            $where = $wpdb->prepare(" WHERE form_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }

        // Get items
        $sql = "SELECT * FROM $table_name" . $where;
        $sql .= " ORDER BY $orderby $order";
        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ($current_page - 1) * $per_page;

        $this->items = $wpdb->get_results($sql, ARRAY_A);

        // Set up pagination args
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));

        // Set up columns
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
    }

    /**
     * Get columns
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'mavlers-contact-forms'),
            'form_title' => __('Form Title', 'mavlers-contact-forms'),
            'shortcode' => __('Shortcode', 'mavlers-contact-forms'),
            'status' => __('Status', 'mavlers-contact-forms'),
            'created_at' => __('Created', 'mavlers-contact-forms'),
            'updated_at' => __('Last Modified', 'mavlers-contact-forms')
        );
    }

    /**
     * Column form_title
     */
    public function column_form_title($item) {
        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=mavlers-contact-forms&action=edit&id=' . $item['id']),
                __('Edit', 'mavlers-contact-forms')
            ),
            'duplicate' => sprintf(
                '<a href="%s" class="duplicate-form" data-form-id="%d">%s</a>',
                '#',
                $item['id'],
                __('Duplicate', 'mavlers-contact-forms')
            ),
            'preview' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                admin_url('admin.php?page=mavlers-contact-forms&action=preview&id=' . $item['id']),
                __('Preview', 'mavlers-contact-forms')
            ),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(admin_url('admin.php?page=mavlers-contact-forms&action=delete&id=' . $item['id']), 'delete_form_' . $item['id']),
                esc_js(__('Are you sure you want to delete this form?', 'mavlers-contact-forms')),
                __('Delete', 'mavlers-contact-forms')
            )
        );

        return sprintf(
            '<strong><a href="%1$s">%2$s</a></strong> %3$s',
            admin_url('admin.php?page=mavlers-contact-forms&action=edit&id=' . $item['id']),
            esc_html($item['form_title']),
            $this->row_actions($actions)
        );
    }

    /**
     * Column shortcode
     */
    public function column_shortcode($item) {
        return sprintf(
            '<code>[mavlers_contact_form id="%d"]</code>',
            $item['id']
        );
    }

    /**
     * Column status
     */
    public function column_status($item) {
        $status = $item['status'] ? 'active' : 'inactive';
        $label = $status === 'active' ? __('Active', 'mavlers-contact-forms') : __('Inactive', 'mavlers-contact-forms');
        return sprintf(
            '<span class="form-status form-status-%s">%s</span>',
            esc_attr($status),
            esc_html($label)
        );
    }

    /**
     * Column created_at
     */
    public function column_created_at($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['created_at']));
    }

    /**
     * Column updated_at
     */
    public function column_updated_at($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['updated_at']));
    }

    /**
     * Column default
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    /**
     * Column checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="form[]" value="%s" />',
            $item['id']
        );
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'id' => array('id', true),
            'form_title' => array('form_title', false),
            'status' => array('status', false),
            'created_at' => array('created_at', false),
            'updated_at' => array('updated_at', false)
        );
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete', 'mavlers-contact-forms')
        );
    }

    public function process_bulk_action() {
        if ('delete' === $this->current_action()) {
            $nonce = esc_attr($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                wp_die('Security check failed');
            }

            $form_ids = isset($_REQUEST['form']) ? array_map('intval', $_REQUEST['form']) : array();
            if (!empty($form_ids)) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'mavlers_cf_forms';
                foreach ($form_ids as $form_id) {
                    $wpdb->delete($table_name, array('id' => $form_id), array('%d'));
                }
            }
        }
    }
} 