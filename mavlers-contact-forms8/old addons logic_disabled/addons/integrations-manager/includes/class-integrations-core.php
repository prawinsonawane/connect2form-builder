<?php
/**
 * Modern Integrations Core System
 *
 * Clean, user-friendly integration management system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Integrations_Core {

    /**
     * Initialize the integration system
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu and pages
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Form builder integration
        add_action('mavlers_cf_render_additional_integrations', array($this, 'render_form_integrations'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_mavlers_cf_get_integration_config', array($this, 'ajax_get_integration_config'));
        add_action('wp_ajax_mavlers_cf_save_integration', array($this, 'ajax_save_integration'));
        
        // Integration processing
        add_action('mavlers_cf_after_submission', array($this, 'process_integrations'), 10, 2);
    }

    /**
     * Add admin menu for integrations
     */
    public function add_admin_menu() {
        add_submenu_page(
            'mavlers-contact-forms',
            __('Integrations', 'mavlers-contact-forms'),
            __('Integrations', 'mavlers-contact-forms'),
            'manage_options',
            'mavlers-cf-integrations',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our pages
        if (strpos($hook, 'mavlers-cf') === false) {
            return;
        }

        wp_enqueue_script(
            'mavlers-cf-integrations',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/js/integrations-admin.js',
            array('jquery'),
            MAVLERS_CF_INTEGRATIONS_VERSION,
            true
        );

        wp_enqueue_style(
            'mavlers-cf-integrations',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/css/integrations-admin.css',
            array(),
            MAVLERS_CF_INTEGRATIONS_VERSION
        );

        // Localize script
        wp_localize_script('mavlers-cf-integrations', 'mavlersCFIntegrations', array(
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'isFormBuilder' => strpos($hook, 'form-builder') !== false
        ));
    }

    /**
     * Render the main integrations admin page
     */
    public function render_admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        ?>
        <div class="wrap mavlers-cf-integrations-page">
            <h1><?php _e('Mavlers Contact Forms - Integrations', 'mavlers-contact-forms'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=mavlers-cf-integrations&tab=overview'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Overview', 'mavlers-contact-forms'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=mavlers-cf-integrations&tab=mailchimp'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'mailchimp' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php _e('Mailchimp', 'mavlers-contact-forms'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'mailchimp':
                        $this->render_mailchimp_settings();
                        break;
                    case 'overview':
                    default:
                        $this->render_overview();
                        break;
                }
                ?>
            </div>
        </div>

        <style>
        .mavlers-cf-integrations-page {
            margin: 20px 20px 0 2px;
        }
        
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        
        .nav-tab .dashicons {
            margin-right: 5px;
            line-height: inherit;
        }
        
        .tab-content {
            background: #fff;
            padding: 0;
            border: 1px solid #ccd0d4;
            border-top: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        </style>
        <?php
    }

    /**
     * Render the overview tab
     */
    private function render_overview() {
        ?>
        <div class="integrations-overview">
            <div class="overview-header">
                <h2><?php _e('Available Integrations', 'mavlers-contact-forms'); ?></h2>
                <p class="description">
                    <?php _e('Connect your contact forms with popular email marketing and CRM platforms.', 'mavlers-contact-forms'); ?>
                </p>
            </div>

            <div class="integrations-grid">
                <?php $this->render_integration_card('mailchimp'); ?>
                <?php $this->render_integration_card('hubspot', false); ?>
                <?php $this->render_integration_card('convertkit', false); ?>
                <?php $this->render_integration_card('activecampaign', false); ?>
            </div>
        </div>

        <style>
        .integrations-overview {
            padding: 30px;
        }
        
        .overview-header {
            margin-bottom: 30px;
        }
        
        .overview-header h2 {
            margin: 0 0 10px 0;
            color: #23282d;
        }
        
        .overview-header .description {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .integrations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .integration-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: box-shadow 0.2s;
        }
        
        .integration-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .integration-card.disabled {
            opacity: 0.6;
        }
        
        .integration-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .integration-card h3 {
            margin: 0 0 10px 0;
            color: #23282d;
        }
        
        .integration-card .description {
            margin: 0 0 20px 0;
            color: #666;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .integration-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .integration-status.connected {
            background: #d4edda;
            color: #155724;
        }
        
        .integration-status.disconnected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .integration-status.coming-soon {
            background: #d1ecf1;
            color: #0c5460;
        }
        </style>
        <?php
    }

    /**
     * Render an integration card
     */
    private function render_integration_card($integration_id, $available = true) {
        $integrations = array(
            'mailchimp' => array(
                'name' => 'Mailchimp',
                'description' => 'Connect your forms to Mailchimp audiences with ease',
                'icon' => 'dashicons-email-alt',
                'color' => '#ffe01b'
            ),
            'hubspot' => array(
                'name' => 'HubSpot',
                'description' => 'Integrate with HubSpot CRM and marketing tools',
                'icon' => 'dashicons-businessman',
                'color' => '#ff7a59'
            ),
            'convertkit' => array(
                'name' => 'ConvertKit',
                'description' => 'Add subscribers to your ConvertKit sequences',
                'icon' => 'dashicons-email',
                'color' => '#fb6970'
            ),
            'activecampaign' => array(
                'name' => 'ActiveCampaign',
                'description' => 'Connect with ActiveCampaign for email marketing',
                'icon' => 'dashicons-email-alt2',
                'color' => '#356ae6'
            )
        );

        $integration = $integrations[$integration_id];
        $is_connected = false;
        
        if ($integration_id === 'mailchimp' && class_exists('Mavlers_CF_Mailchimp_Integration')) {
            $mailchimp = new Mavlers_CF_Mailchimp_Integration();
            $is_connected = $mailchimp->is_globally_connected();
        }
        ?>
        <div class="integration-card <?php echo !$available ? 'disabled' : ''; ?>">
            <div class="integration-icon">
                <span class="dashicons <?php echo $integration['icon']; ?>" 
                      style="color: <?php echo $integration['color']; ?>;"></span>
            </div>
            
            <h3><?php echo $integration['name']; ?></h3>
            <p class="description"><?php echo $integration['description']; ?></p>
            
            <?php if ($available): ?>
                <div class="integration-status <?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
                    <span class="dashicons <?php echo $is_connected ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                    <?php echo $is_connected ? __('Connected', 'mavlers-contact-forms') : __('Not Connected', 'mavlers-contact-forms'); ?>
                </div>
                
                <a href="<?php echo admin_url("admin.php?page=mavlers-cf-integrations&tab={$integration_id}"); ?>" 
                   class="button button-primary">
                    <?php _e('Configure', 'mavlers-contact-forms'); ?>
                </a>
            <?php else: ?>
                <div class="integration-status coming-soon">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Coming Soon', 'mavlers-contact-forms'); ?>
                </div>
                
                <button class="button button-secondary" disabled>
                    <?php _e('Not Available', 'mavlers-contact-forms'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Mailchimp settings
     */
    private function render_mailchimp_settings() {
        if (file_exists(MAVLERS_CF_INTEGRATIONS_DIR . 'templates/mailchimp-global-settings.php')) {
            include_once MAVLERS_CF_INTEGRATIONS_DIR . 'templates/mailchimp-global-settings.php';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Mailchimp settings template not found.', 'mavlers-contact-forms') . '</p></div>';
        }
    }

    /**
     * Render form integrations in the form builder
     */
    public function render_form_integrations($form_id, $form = null) {
        // Only show if we have a valid form ID
        if (!$form_id) {
            return;
        }

        echo '<div class="integration-section" id="mavlers-cf-form-integrations">';
        echo '<h3>' . __('Integrations', 'mavlers-contact-forms') . '</h3>';
        
        // Render Mailchimp form settings
        if (class_exists('Mavlers_CF_Mailchimp_Integration')) {
            if (file_exists(MAVLERS_CF_INTEGRATIONS_DIR . 'templates/mailchimp-form-settings.php')) {
                include_once MAVLERS_CF_INTEGRATIONS_DIR . 'templates/mailchimp-form-settings.php';
            }
        }
        
        echo '</div>';
    }

    /**
     * Process integrations on form submission
     */
    public function process_integrations($submission_id, $addon_form_data) {
        // The individual integrations handle this via their own hooks
        // This is just a central processing point if needed
        
        // Extract form data for compatibility
        $form_id = isset($addon_form_data['form_id']) ? $addon_form_data['form_id'] : 0;
        $form_fields = isset($addon_form_data['fields']) ? $addon_form_data['fields'] : array();
        
        // Log integration processing
        error_log("Integrations Core: Processing submission {$submission_id} for form {$form_id}");
        
        // Trigger the integration processing hook with the data structure individual integrations expect
        do_action('mavlers_cf_process_integrations', $submission_id, $addon_form_data, $form_id);
    }

    /**
     * AJAX: Get integration configuration
     */
    public function ajax_get_integration_config() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);
        $mode = sanitize_text_field($_POST['mode'] ?? 'configure');

        // Handle different integrations
        switch ($integration_id) {
            case 'mailchimp':
                if (class_exists('Mavlers_CF_Mailchimp_Integration')) {
                    $mailchimp = new Mavlers_CF_Mailchimp_Integration();
                    
                    if ($mode === 'global') {
                        $settings = $mailchimp->get_global_settings();
                        wp_send_json_success(array('settings' => $settings));
                    } else {
                        $settings = $mailchimp->get_form_settings($form_id);
                        wp_send_json_success(array('settings' => $settings));
                    }
                }
                break;
                
            default:
                wp_send_json_error('Unknown integration');
        }

        wp_send_json_error('Integration not available');
    }

    /**
     * AJAX: Save integration settings
     */
    public function ajax_save_integration() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $form_id = intval($_POST['form_id'] ?? 0);

        // Handle different integrations
        switch ($integration_id) {
            case 'mailchimp':
                // Mailchimp handles its own AJAX endpoints
                wp_send_json_error('Use specific Mailchimp endpoints');
                break;
                
            default:
                wp_send_json_error('Unknown integration');
        }
    }

    /**
     * Activation hook
     */
    public static function activate() {
        // Create necessary database tables or options
        self::create_integration_tables();
    }

    /**
     * Create integration tables
     */
    private static function create_integration_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Integration logs table
        $table_name = $wpdb->prefix . 'mavlers_cf_integration_logs';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id mediumint(9) NOT NULL,
            integration_id varchar(50) NOT NULL,
            submission_id mediumint(9) DEFAULT NULL,
            status varchar(20) NOT NULL,
            message text DEFAULT NULL,
            response_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY integration_id (integration_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log integration activity
     */
    public static function log_integration($form_id, $integration_id, $status, $message = '', $data = null, $submission_id = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mavlers_cf_integration_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'form_id' => $form_id,
                'integration_id' => $integration_id,
                'submission_id' => $submission_id,
                'status' => $status,
                'message' => $message,
                'response_data' => is_array($data) ? json_encode($data) : $data,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
}

// Initialize the system
new Mavlers_CF_Integrations_Core();