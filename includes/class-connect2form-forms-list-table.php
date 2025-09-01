<?php
/**
 * Connect2Form Forms List Table Class
 *
 * Handles the admin forms list table display and management
 *
 * @package Connect2Form
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Connect2Form_Forms_List_Table extends WP_List_Table {
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
        // Set up pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();

        // Use service class instead of direct database call
        if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            
            // Set up sorting (read-only admin params; no nonce required)
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading non-sensitive ordering params
            $orderby_raw = filter_input(INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading non-sensitive ordering params
            $order_raw   = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $orderby     = $orderby_raw ? sanitize_sql_orderby((string) $orderby_raw) : 'id';
            $order       = $order_raw ? sanitize_sql_orderby((string) $order_raw) : 'DESC';

            // Set up search
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading search term only
            $search_raw = filter_input(INPUT_GET, 's', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $search     = $search_raw !== null ? sanitize_text_field((string) $search_raw) : '';
            
            $total_items = $service_manager->forms()->get_forms_count($search);
            $this->items = $service_manager->forms()->get_forms($per_page, ($current_page - 1) * $per_page, $orderby, $order, $search);

            // Set up pagination args
            $this->set_pagination_args(array(
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => ceil($total_items / $per_page)
            ));
		} else {
            // Fallback to direct database call if service not available
            global $wpdb;
            $table_name = $wpdb->prefix . 'connect2form_forms';

			// Validate identifier to avoid unsafe interpolation in SQL identifiers
			if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! c2f_is_valid_prefixed_table( $table_name ) ) {
				$this->items = array();
				$this->set_pagination_args( array(
					'total_items' => 0,
					'per_page'    => $per_page,
					'total_pages' => 0,
				) );
				return;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated identifier; admin pagination count
			$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );

            // Set up sorting (read-only admin params; no nonce required)
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading non-sensitive ordering params
            $orderby_raw = filter_input(INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading non-sensitive ordering params
            $order_raw   = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $orderby     = $orderby_raw ? sanitize_sql_orderby((string) $orderby_raw) : 'id';
            $order       = $order_raw ? sanitize_sql_orderby((string) $order_raw) : 'DESC';

            // Set up search
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading search term only
            $search_raw = filter_input(INPUT_GET, 's', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $search     = $search_raw !== null ? sanitize_text_field((string) $search_raw) : '';

            // Get items
            $limit  = absint($per_page);
            $offset = absint(($current_page - 1) * $per_page);
            // Validate orderby/direction using strict whitelist approach
            $allowed_cols = array(
                'id' => 'id',
                'form_title' => 'form_title',
                'status' => 'status',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at'
            );
            
            $safe_orderby = isset($allowed_cols[$orderby]) ? $allowed_cols[$orderby] : 'id';
            $safe_direction = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
            $like = '%' . $wpdb->esc_like($search) . '%';
            
            // Build safe ORDER BY clause using validated components
            $order_clause = "`{$safe_orderby}` {$safe_direction}";
            
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin list table query; ORDER BY uses whitelisted safe values
			$this->items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE (%s = '' OR form_title LIKE %s) ORDER BY {$order_clause} LIMIT %d OFFSET %d",
					$search,
					$like,
					$limit,
					$offset
				),
				ARRAY_A
			);

            // Set up pagination args
            $this->set_pagination_args(array(
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => ceil($total_items / $per_page)
            ));
        }

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
            'id' => __('ID', 'connect2form-builder'),
            'form_title' => __('Form Title', 'connect2form-builder'),
            'shortcode' => __('Shortcode', 'connect2form-builder'),
            'status' => __('Status', 'connect2form-builder'),
            'created_at' => __('Created', 'connect2form-builder'),
            'updated_at' => __('Last Modified', 'connect2form-builder')
        );
    }

    /**
     * Column form_title
     */
    public function column_form_title($item) {
        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=connect2form&action=edit&id=' . $item['id'])),
                esc_html__('Edit', 'connect2form-builder')
            ),
            'duplicate' => sprintf(
                '<a href="%s" class="duplicate-form" data-form-id="%d">%s</a>',
                '#',
                esc_attr($item['id']),
                esc_html__('Duplicate', 'connect2form-builder')
            ),
            'preview' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url(admin_url('admin.php?page=connect2form&action=preview&id=' . $item['id'])),
                esc_html__('Preview', 'connect2form-builder')
            ),
            'delete' => sprintf(
                '<a href="#" class="submitdelete delete-form-ajax" data-form-id="%d">%s</a>',
                esc_attr($item['id']),
                esc_html__('Delete', 'connect2form-builder')
            )
        );

        return sprintf(
            '<strong><a href="%1$s">%2$s</a></strong> %3$s',
            esc_url(admin_url('admin.php?page=connect2form&action=edit&id=' . $item['id'])),
            esc_html($item['form_title']),
            $this->row_actions($actions)
        );
    }

    /**
     * Column shortcode
     */
    public function column_shortcode($item) {
        return sprintf(
            '<code>[connect2form id="%d"]</code>',
            esc_attr($item['id'])
        );
    }

    /**
     * Column status
     */
    public function column_status($item) {
        $status = $item['status'] ? 'active' : 'inactive';
        $label = $status === 'active' ? __('Active', 'connect2form-builder') : __('Inactive', 'connect2form-builder');
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
            esc_attr($item['id'])
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
            'delete' => __('Delete', 'connect2form-builder')
        );
    }

    public function process_bulk_action() {
        // Bulk actions are now handled via AJAX in admin.js
        // This method is kept for compatibility but doesn't process bulk deletes
        return;
    }
} 
