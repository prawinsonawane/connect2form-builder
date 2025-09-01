<?php
/**
 * Connect2Form Submissions List Table Class
 *
 * Handles the admin submissions list table display and management
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

class Connect2Form_Submissions_List_Table extends WP_List_Table {
    public $form_id;
    public $form_fields;

    public function __construct($form_id = 0) {
        parent::__construct(array(
            'singular' => 'submission',
            'plural'   => 'submissions',
            'ajax'     => false
        ));

        // Get form_id from URL if not provided (read-only filter UI)
        if ( ! $form_id && isset( $_GET['form_id'] ) ) {
            // Verify admin context and permissions for security
            if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
                $form_id = 0;
            } else {
                $form_id = absint( wp_unslash( $_GET['form_id'] ) );
            }
        }
        
        $this->form_id = $form_id;
        
        if ($form_id) {
            // Use service class instead of direct database call
            if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
                $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
                $form = $service_manager->forms()->get_form($form_id);
                if ($form) {
                    $this->form_fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
                }
            } else {
                // Fallback to direct database call if service not available
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form fields query for submissions list; service layer preferred but this is a fallback
                $form = $wpdb->get_row($wpdb->prepare(
                    "SELECT fields FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
                    $form_id
                ));
                if ($form) {
                    $this->form_fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
                }
            }
        }
    }

    public function get_columns() {
        $columns = array(
            'cb'        => '<input type="checkbox" />',
                         'id'        => esc_html__('ID', 'connect2form-builder'),
             'date'      => esc_html__('Date', 'connect2form-builder')
        );

        // If no form_id is provided, show standard columns plus form info
        if (!$this->form_id) {
                         $columns['form_id'] = esc_html__('Form', 'connect2form-builder');
             $columns['actions'] = esc_html__('Actions', 'connect2form-builder');
            return $columns;
        }

        if ($this->form_fields && is_array($this->form_fields)) {
            $field_count = 0;
            $max_fields = 4; // Limit to 4 form fields
            
            foreach ($this->form_fields as $index => $field) {
                // Skip certain field types that shouldn't be displayed in the table
                if (isset($field['type']) && in_array($field['type'], array('html', 'hidden', 'submit', 'captcha'))) {
                    continue;
                }
                
                // Limit to 4 form fields
                if ($field_count >= $max_fields) {
                    break;
                }
                
                // Use field label or generate a default label
                $field_label = isset($field['label']) && !empty($field['label']) ? $field['label'] : ucfirst(str_replace('_', ' ', $field['id']));
                $columns[$field['id']] = $field_label;
                
                $field_count++;
            }
        }

                 $columns['actions'] = esc_html__('Actions', 'connect2form-builder');
        return $columns;
    }

    /**
     * Override the display method to ensure all columns are shown
     */
    public function display() {
        // Force all columns to be visible
        $this->_column_headers = array(
            $this->get_columns(),
            array(), // hidden columns - keep empty
            $this->get_sortable_columns()
        );
        
        parent::display();
    }

    /**
     * Override to ensure all columns are shown by default
     */
    public function get_hidden_columns() {
        return array();
    }

    /**
     * Override to prevent column hiding
     */
    public function get_columns_to_hide() {
        return array();
    }

    /**
     * Override to ensure all columns are always visible
     */
    public function get_columns_to_show() {
        return array_keys($this->get_columns());
    }

    /**
     * Override to ensure all columns are shown
     */
    public function get_sortable_columns() {
        return array(
            'id'   => array('id', true),
            'date' => array('created_at', false)
        );
    }

    public function prepare_items() {
        // Process bulk actions
        $this->process_bulk_action();
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // Use direct database call due to schema mismatch with ServiceManager
        // ServiceManager expects extended schema with utm_data column
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'connect2form_submissions';
        // Validate table identifier before interpolation
        if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! \c2f_is_valid_prefixed_table( $submissions_table ) ) {
            $this->items = array();
            $this->set_pagination_args( array( 'total_items' => 0, 'per_page' => $per_page ) );
            return;
        }
        
        // If no form_id is provided, show all submissions
        if (!$this->form_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- submissions count query for admin list; direct access needed due to schema mismatch
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated identifier, admin count query
            $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM `{$submissions_table}`" );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- submissions list query for admin; direct access needed due to schema mismatch
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin list query
            $this->items = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$submissions_table}` ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated identifier
                $per_page,
                ($current_page - 1) * $per_page
            ), ARRAY_A );
        } else {
            // Filter by specific form
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- form submissions count query for admin list; direct access needed due to schema mismatch
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin count query
            $total_items = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$submissions_table}` WHERE form_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated identifier
                $this->form_id
            ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- form submissions list query for admin; direct access needed due to schema mismatch
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin list query
            $this->items = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$submissions_table}` WHERE form_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated identifier
                $this->form_id,
                $per_page,
                ($current_page - 1) * $per_page
            ), ARRAY_A );
        }
            
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));

        // Set column headers for WP_List_Table
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
    }

    public function column_default($item, $column_name) {
        if ($column_name === 'actions') {
            return $this->get_row_actions($item);
        }

        if ($column_name === 'form_id') {
            // Use service class instead of direct database call
            $form = null;
            if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
                $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
                $form = $service_manager->forms()->get_form($item['form_id']);
            } else {
                // Fallback to direct database call if service not available
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form title query for submissions list display; service layer preferred but this is a fallback
                $form = $wpdb->get_row($wpdb->prepare(
                    "SELECT form_title FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
                    $item['form_id']
                ));
            }
                         return $form ? esc_html($form->form_title) : __('Unknown Form', 'connect2form-builder');
        }

        $data = json_decode($item['data'], true);
        if (!$data) {
            $data = array();
        }

        if (isset($data[$column_name])) {
            return $this->format_field_value($column_name, $data[$column_name]);
        }

        // If this is a field that's not displayed due to the 4-column limit, show a note
        if ($this->form_fields && is_array($this->form_fields)) {
            foreach ($this->form_fields as $field) {
                if ($field['id'] === $column_name) {
                    return '<em>' . esc_html__('Field not displayed (limit: 4 fields)', 'connect2form-builder') . '</em>';
                }
            }
        }

        return '<em>' . esc_html__('No data', 'connect2form-builder') . '</em>';
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="submission[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    public function column_id($item) {
        return '#' . esc_html($item['id']);
    }

    public function column_date($item) {
        return esc_html( date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['created_at'])) );
    }

    private function get_row_actions($item) {
        $actions = array(
            'view' => sprintf(
                '<a href="?page=connect2form-submissions&action=view&id=%s">%s</a>',
                esc_attr($item['id']),
                esc_html__('View', 'connect2form-builder')
            ),
            'delete' => sprintf(
                '<a href="#" class="delete-submission-ajax" data-submission-id="%s" data-nonce="%s">%s</a>',
                esc_attr($item['id']),
                esc_attr(wp_create_nonce('connect2form_delete_submission')),
                esc_html__('Delete', 'connect2form-builder')
            )
        );

        return $this->row_actions($actions);
    }

    private function format_field_value($field_id, $value) {
        if (empty($this->form_fields)) {
            return $value;
        }

        $field = null;
        foreach ($this->form_fields as $f) {
            if ($f['id'] === $field_id) {
                $field = $f;
                break;
            }
        }

        if (!$field) {
            return $value;
        }

        switch ($field['type']) {
            case 'file':
            case 'file_upload':
                if (is_array($value)) {
                    $links = array();
                    foreach ($value as $url) {
                        $links[] = sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            esc_url($url),
                            __('View File', 'connect2form-builder')
                        );
                    }
                    return implode(', ', $links);
                }
                if (!empty($value)) {
                    return sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        esc_url($value),
                        __('View File', 'connect2form-builder')
                    );
                }
                return '<em>' . esc_html__('No file uploaded', 'connect2form-builder') . '</em>';

            case 'checkbox':
            case 'radio':
            case 'select':
                if (is_array($value)) {
                    return implode(', ', array_map('esc_html', $value));
                }
                return esc_html($value);

            case 'date':
                if (!empty($value)) {
                    return date_i18n(get_option('date_format'), strtotime($value));
                }
                return '<em>' . esc_html('No date', 'connect2form-builder') . '</em>';

            case 'utm':
                if (is_array($value)) {
                    $utm_parts = array();
                    foreach ($value as $param => $param_value) {
                        if (!empty($param_value)) {
                            $utm_parts[] = '<strong>' . esc_html($param) . ':</strong> ' . esc_html($param_value);
                        }
                    }
                                         return !empty($utm_parts) ? implode('<br>', $utm_parts) : '<em>' . esc_html__('No UTM data', 'connect2form-builder') . '</em>';
                }
                return esc_html($value);

            case 'textarea':
                if (!empty($value)) {
                    return '<div style="max-height: 100px; overflow-y: auto;">' . esc_html($value) . '</div>';
                }
                                 return '<em>' . esc_html__('No content', 'connect2form-builder') . '</em>';

            default:
                if (!empty($value)) {
                    return esc_html($value);
                }
                                 return '<em>' . esc_html__('No data', 'connect2form-builder') . '</em>';
        }
    }

    public function get_bulk_actions() {
        return array(
            'delete' => esc_html__('Delete', 'connect2form-builder'),
            'export' => esc_html__('Export', 'connect2form-builder')
        );
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        $action = $this->current_action();
        
        if ($action === 'delete') {
            // Return early - bulk delete will be handled by AJAX
            return;
        }
    }

    public function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
    
        global $wpdb;
    
        // Build table name (no user input). phpcs ignore added because no placeholders are needed.
        $table = $wpdb->prefix . 'connect2form_forms';
        if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! \c2f_is_valid_prefixed_table( $table ) ) {
            return;
        }
    
        // Safe: no user input in the query; table name is built from a trusted prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static forms list query for filter dropdown with trusted table identifier; no caching needed for admin filter dropdown; table name is built from trusted prefix
        $forms = $wpdb->get_results( "SELECT id, form_title FROM `{$table}` ORDER BY form_title" );
    
        $action_url     = ( admin_url( 'admin.php' ) );
        $list_page_url  = ( admin_url( 'admin.php?page=connect2form-submissions' ) );
        $current_formid = isset( $this->form_id ) ? (int) $this->form_id : 0;
        ?>
        <div class="alignleft actions">
            <form method="get" action="<?php echo esc_url( $action_url ); ?>" id="connect2form-filter-form" style="display:inline-block;">
                <input type="hidden" name="page" value="connect2form-submissions" />
    
                <label for="form-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by form', 'connect2form-builder'); ?></label>
                <select name="form_id" id="form-filter">
                    <option value=""><?php esc_html_e( 'All Forms', 'connect2form-builder'); ?></option>
                    <?php if ( ! empty( $forms ) ) : ?>
                        <?php foreach ( $forms as $form ) : ?>
                            <?php
                            $fid         = isset( $form->id ) ? (int) $form->id : 0;
                            $is_selected = ( $current_formid === $fid ) ? 'selected' : '';
                            ?>
                            <option value="<?php echo esc_attr( $fid ); ?>" <?php echo esc_attr( $is_selected ? 'selected="selected"' : '' ); ?>>
                                <?php echo esc_html( (string) $form->form_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
    
                                <input type="submit" name="filter_submissions" value="<?php echo esc_attr__( 'Filter', 'connect2form-builder'); ?>" class="button" />
                
                <?php if ( $current_formid ) : ?>
                    <a href="<?php echo esc_url( $list_page_url ); ?>" class="button">
                        <?php esc_html_e( 'Clear Filter', 'connect2form-builder'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    
        <script type="text/javascript">
        jQuery(function($){
            var $filterForm = $('#form-filter').closest('form');
            if ($filterForm.length) {
                if (!$filterForm.attr('action')) { $filterForm.attr('action', <?php echo wp_json_encode( $action_url ); ?>); }
                if ($filterForm.attr('method') !== 'get') { $filterForm.attr('method', 'get'); }
                if (!$filterForm.attr('id')) { $filterForm.attr('id', 'connect2form-filter-form'); }
    
                $filterForm.on('submit', function(){
                    $('<div id="form-submitting" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border:2px solid #0073aa;padding:20px;border-radius:5px;z-index:9999;box-shadow:0 0 10px rgba(0,0,0,.3);"><?php echo esc_js( __( 'Form is being submitted...', 'connect2form-builder') ); ?></div>').appendTo('body');
                });
            }
        });
        </script>
        <?php
    }
    

    /**
     * Override to show message when no submissions are found
     */
    public function no_items() {
        if (!$this->form_id) {
            esc_html_e('No submissions found.', 'connect2form-builder');
        } else {
            esc_html_e('No submissions found for the selected form.', 'connect2form-builder');
        }
    }
} 
