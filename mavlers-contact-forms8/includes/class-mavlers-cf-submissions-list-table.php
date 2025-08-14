<?php
if (!defined('WPINC')) {
    die;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Mavlers_CF_Submissions_List_Table extends WP_List_Table {
    private $form_id;
    private $form_fields;

    public function __construct($form_id = null) {
        parent::__construct(array(
            'singular' => 'submission',
            'plural'   => 'submissions',
            'ajax'     => false
        ));
        
        $this->form_id = $form_id;
        
        if ($this->form_id) {
            // Load form fields for this specific form
            global $wpdb;
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
                $this->form_id
            ));
            
            if ($form) {
                $this->form_fields = json_decode($form->fields, true);
            }
        }
    }

    public function get_columns() {
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'id'        => __('ID', 'mavlers-contact-forms'),
            'date'      => __('Date', 'mavlers-contact-forms')
        );

        if ($this->form_fields) {
            foreach ($this->form_fields as $field) {
                if ($field['type'] !== 'html' && $field['type'] !== 'hidden') {
                    $columns[$field['id']] = $field['label'];
                }
            }
        }
        // Debug output
        // error_log('Form fields: ' . print_r($this->form_fields, true));

        $columns['actions'] = __('Actions', 'mavlers-contact-forms');
        return $columns;
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_cf_submissions';
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $where = '';
        if ($this->form_id) {
            $where = $wpdb->prepare('WHERE form_id = %d', $this->form_id);
        }
        
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name $where");

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name $where ORDER BY id DESC LIMIT %d OFFSET %d",
                $per_page,
                ($current_page - 1) * $per_page
            ),
            ARRAY_A
        );
        // Debug output
        // error_log('Loaded submissions: ' . print_r($this->items, true));

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

        $data = json_decode($item['data'], true);
        return isset($data[$column_name]) ? $this->format_field_value($column_name, $data[$column_name]) : '';
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="submission[]" value="%s" />',
            $item['id']
        );
    }

    public function column_id($item) {
        return '#' . $item['id'];
    }

    public function column_date($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['created_at']));
    }

    private function get_row_actions($item) {
        $actions = array(
            'view' => sprintf(
                '<a href="?page=mavlers-cf-submissions&action=view&id=%s">%s</a>',
                $item['id'],
                __('View', 'mavlers-contact-forms')
            ),
            'delete' => sprintf(
                '<a href="?page=mavlers-cf-submissions&action=delete&id=%s" onclick="return confirm(\'%s\');">%s</a>',
                $item['id'],
                __('Are you sure you want to delete this submission?', 'mavlers-contact-forms'),
                __('Delete', 'mavlers-contact-forms')
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
            case 'file_upload':
                if (is_array($value)) {
                    $links = array();
                    foreach ($value as $url) {
                        $links[] = sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            esc_url($url),
                            __('View File', 'mavlers-contact-forms')
                        );
                    }
                    return implode(', ', $links);
                }
                return sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($value),
                    __('View File', 'mavlers-contact-forms')
                );

            case 'checkbox':
            case 'multiselect':
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return $value;

            case 'date':
                return date_i18n(get_option('date_format'), strtotime($value));

            default:
                return $value;
        }
    }

    public function get_sortable_columns() {
        return array(
            'id'   => array('id', true),
            'date' => array('created_at', false)
        );
    }

    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete', 'mavlers-contact-forms'),
            'export' => __('Export', 'mavlers-contact-forms')
        );
    }

    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        global $wpdb;
        $forms = $wpdb->get_results("SELECT id, form_title FROM {$wpdb->prefix}mavlers_cf_forms ORDER BY form_title");
        ?>
        <div class="alignleft actions">
            <select name="form_id">
                <option value=""><?php _e('All Forms', 'mavlers-contact-forms'); ?></option>
                <?php foreach ($forms as $form): ?>
                    <option value="<?php echo esc_attr($form->id); ?>" <?php selected($this->form_id, $form->id); ?>>
                        <?php echo esc_html($form->form_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php submit_button(__('Filter', 'mavlers-contact-forms'), 'action', 'filter_action', false); ?>
        </div>
        <?php
    }
} 