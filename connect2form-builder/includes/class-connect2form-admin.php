<?php
/**
 * Connect2Form Admin Class
 *
 * Handles all administrative functionality for the Connect2Form plugin
 *
 * @package Connect2Form
 * @since 1.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.NonceVerification.Missing
// phpcs:disable WordPress.Security.NonceVerification.Recommended

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Connect2Form Admin Class
 *
 * @since 1.0.0
 */
class Connect2Form_Admin {
    /**
     * Plugin name
     *
     * @var string
     */
    private $plugin_name;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Settings instance
     *
     * @var Connect2Form_Settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @param string $plugin_name Plugin name.
     * @param string $version     Plugin version.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;

        // Initialize settings
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-connect2form-settings.php';
        $this->settings = new Connect2Form_Settings();

        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        
        // Add admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        // Add AJAX handlers
        add_action( 'wp_ajax_connect2form_save_form', array( $this, 'ajax_save_form' ) );
        add_action( 'wp_ajax_connect2form_delete_form', array( $this, 'ajax_delete_form' ) );
        add_action( 'wp_ajax_connect2form_bulk_delete_forms', array( $this, 'ajax_bulk_delete_forms' ) );
        add_action( 'wp_ajax_connect2form_delete_submission', array( $this, 'ajax_delete_submission' ) );
        add_action( 'wp_ajax_connect2form_bulk_delete_submissions', array( $this, 'ajax_bulk_delete_submissions' ) );
        add_action( 'wp_ajax_connect2form_duplicate_form', array( $this, 'ajax_duplicate_form' ) );
        add_action( 'wp_ajax_connect2form_toggle_status', array( $this, 'ajax_toggle_status' ) );
        add_action( 'wp_ajax_connect2form_get_form', array( $this, 'ajax_get_form' ) );
        add_action( 'wp_ajax_connect2form_preview_form', array( $this, 'ajax_preview_form' ) );
        add_action( 'wp_ajax_connect2form_test_email', array( $this, 'ajax_test_email' ) );
        add_action( 'wp_ajax_connect2form_test_recaptcha', array( $this, 'ajax_test_recaptcha' ) );
        add_action( 'wp_ajax_connect2form_bulk_export_submissions', array( $this, 'ajax_bulk_export_submissions' ) );
    }

    /**
     * Log internal admin errors using Logger with safe fallback
     *
     * @param string $message Error message.
     * @param array  $context Error context.
     */
    private function log_error( $message, $context = array() ) {
        try {
            if ( class_exists( 'Connect2Form\\Integrations\\Core\\Services\\Logger' ) ) {
                $logger = new Connect2Form\Integrations\Core\Services\Logger();
                $logger->error( $message, $context );
                return;
            }
        } catch ( \Throwable $e ) {
            // fall through to error_log
        }
        if ( function_exists( 'error_log' ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Connect2Form Admin: ' . $message );
        }
    }

    /**
     * Helper method to safely get form setting value
     *
     * @param object $form   Form object.
     * @param string $key    Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    private function get_form_setting( $form, $key, $default = '' ) {
        if ( ! $form || ! isset( $form->settings ) ) {
            return $default;
        }
        
        $settings = is_array( $form->settings ) ? $form->settings : array();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        add_menu_page(
            __( 'Contact Forms', 'connect2form-builder'),
            __( 'Contact Forms', 'connect2form-builder'),
            'manage_options',
            'connect2form',
            array( $this, 'render_forms_list_page' ),
            'dashicons-email',
            30
        );

        add_submenu_page(
            'connect2form',
            __( 'All Forms', 'connect2form-builder'),
            __( 'All Forms', 'connect2form-builder'),
            'manage_options',
            'connect2form',
            array( $this, 'render_forms_list_page' )
        );

        add_submenu_page(
            'connect2form',
            __( 'Add New', 'connect2form-builder'),
            __( 'Add New', 'connect2form-builder'),
            'manage_options',
            'connect2form-new-form',
            array( $this, 'render_form_builder' )
        );

        add_submenu_page(
            'connect2form',
            __('Submissions', 'connect2form-builder'),
            __('Submissions', 'connect2form-builder'),
            'manage_options',
            'connect2form-submissions',
            array($this, 'render_submissions_page')
        );

        add_submenu_page(
            'connect2form',
            __('Settings', 'connect2form-builder'),
            __('Settings', 'connect2form-builder'),
            'manage_options',
            'connect2form-settings',
            array($this->settings, 'render_settings_page')
        );

        // Add debug page if user has manage_options capability
        add_submenu_page(
            'connect2form',
            __('Debug', 'connect2form-builder'),
            __('Debug', 'connect2form-builder'),
            'manage_options',
            'connect2form-debug',
            array($this, 'render_debug_page')
        );
    }

    /**
     * Render debug page
     */
    public function render_debug_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Connect2Form Debug', 'connect2form-builder'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php echo esc_html__('This debug page helps diagnose issues with the Connect2Form plugin and its integrations.', 'connect2form-builder'); ?></p>
            </div>

            <style>
                .debug-section { border: 1px solid #ccc; margin: 15px 0; padding: 15px; background: #fff; }
                .debug-success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
                .debug-error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
                .debug-warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
                .debug-pre { background: #f8f9fa; padding: 10px; overflow-x: auto; font-family: monospace; border: 1px solid #dee2e6; }
                .debug-status { font-weight: bold; }
            </style>
            
            <div class="debug-section">
                <h2><?php echo esc_html__('1. Plugin Status', 'connect2form-builder'); ?></h2>
                <?php
                echo '<p><span class="debug-status">' . esc_html__('Connect2Form Active:', 'connect2form-builder') . '</span> ' . (class_exists('Connect2Form_Admin') ? esc_html__('Yes', 'connect2form-builder') : esc_html__('No', 'connect2form-builder')) . '</p>';
                echo '<p><span class="debug-status">' . esc_html__('Integrations Manager Active:', 'connect2form-builder') . '</span> ' . (class_exists('Connect2Form\\Integrations\\Core\\Plugin') ? esc_html__('Yes', 'connect2form-builder') : esc_html__('No', 'connect2form-builder')) . '</p>';
                
                if (defined('CONNECT2FORM_VERSION')) {
                    echo '<p><span class="debug-status">' . esc_html__('Connect2Form Version:', 'connect2form-builder') . '</span> ' . esc_html(CONNECT2FORM_VERSION) . '</p>';
                }
                
                if (defined('CONNECT2FORM_INTEGRATIONS_VERSION')) {
                    echo '<p><span class="debug-status">' . esc_html__('Integrations Version:', 'connect2form-builder') . '</span> ' . esc_html(CONNECT2FORM_INTEGRATIONS_VERSION) . '</p>';
                } else {
                    echo '<p><span class="debug-status">' . esc_html__('Integrations:', 'connect2form-builder') . '</span> <span class="debug-status-inactive">' . esc_html__('Not Installed', 'connect2form-builder') . '</span></p>';
                }
                ?>
            </div>
            
            <div class="debug-section">
                <h2><?php echo esc_html__('2. File Existence Check', 'connect2form-builder'); ?></h2>
                <?php
                $files_to_check = [
                    'connect2form.php',
                    'includes/class-connect2form-admin.php',
                    'includes/class-connect2form-form-builder.php',
                    'includes/class-connect2form-form-renderer.php',
                    'includes/class-connect2form-submission-handler.php'
                ];
                
                foreach ($files_to_check as $file) {
                    $full_path = CONNECT2FORM_PLUGIN_DIR . $file;
                    $exists = file_exists($full_path);
                    $status_class = $exists ? 'debug-success' : 'debug-error';
                    $status_text = $exists ? esc_html__('EXISTS', 'connect2form-builder') : esc_html__('MISSING', 'connect2form-builder');
                    echo '<p class="' . esc_attr($status_class) . '"><span class="debug-status">' . esc_html($file) . ':</span> ' . esc_html($status_text) . '</p>';
                }
                ?>
            </div>
            
            <div class="debug-section">
                <h2><?php echo esc_html__('3. Integration Registry Test', 'connect2form-builder'); ?></h2>
                <?php
                if (class_exists('Connect2Form\\Integrations\\Core\\Plugin')) {
                    try {
                        $plugin = Connect2Form\Integrations\Core\Plugin::getInstance();
                        echo '<p class="debug-success">' . esc_html__('Plugin instance created successfully', 'connect2form-builder') . '</p>';
                        
                        if (method_exists($plugin, 'getRegistry')) {
                            $registry = $plugin->getRegistry();
                            echo '<p class="debug-success">' . esc_html__('Registry obtained successfully', 'connect2form-builder') . '</p>';
                            
                            if ($registry && method_exists($registry, 'getAll')) {
                                $integrations = $registry->getAll();
                                echo '<p><span class="debug-status">' . esc_html__('Registered Integrations:', 'connect2form-builder') . '</span> ' . esc_html(count($integrations)) . '</p>';
                                
                                foreach ($integrations as $id => $integration) {
                                    echo '<p>- ' . esc_html($id) . ' (' . esc_html(get_class($integration)) . ')</p>';
                                    
                                    if ($id === 'mailchimp') {
                                        echo '<div style="margin-left: 20px;">';
                                        try {
                                            $actions = $integration->getAvailableActions();
                                            echo '<p class="debug-success">✓ ' . esc_html__('getAvailableActions() works', 'connect2form-builder') . '</p>';
                                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- debug output guarded for admin UI only
                                            echo '<pre class="debug-pre">' . esc_html(print_r($actions, true)) . '</pre>';
                                        } catch (Exception $e) {
                                            echo '<p class="debug-error">✗ ' . esc_html__('getAvailableActions() failed:', 'connect2form-builder') . ' ' . esc_html($e->getMessage()) . '</p>';
                                        }
                                        echo '</div>';
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        echo '<p class="debug-error">' . esc_html__('Error:', 'connect2form-builder') . ' ' . esc_html($e->getMessage()) . '</p>';
                    }
                } else {
                    echo '<p class="debug-status">' . esc_html__('Connect2Form Integrations plugin not installed', 'connect2form-builder') . '</p>';
                    echo '<p><a href="' . esc_url(admin_url('plugin-install.php?s=connect2form+integrations&tab=search&type=term')) . '" class="button button-primary">' . esc_html__('Install Integrations Plugin', 'connect2form-builder') . '</a></p>';
                }
                ?>
            </div>
            
            <div class="debug-section">
                <h2><?php echo esc_html__('4. AJAX Handlers Test', 'connect2form-builder'); ?></h2>
                <?php
                global $wp_filter;
                
                $ajax_actions_to_check = [
                    'wp_ajax_connect2form_save_form',
                    'wp_ajax_connect2form_delete_form',
                    'wp_ajax_connect2form_get_form',
                    'wp_ajax_connect2form_preview_form'
                ];
                
                foreach ($ajax_actions_to_check as $action) {
                    $registered = isset($wp_filter[$action]) && !empty($wp_filter[$action]->callbacks);
                    $status_class = $registered ? 'debug-success' : 'debug-error';
                    $status_text = $registered ? esc_html__('REGISTERED', 'connect2form-builder') : esc_html__('NOT REGISTERED', 'connect2form-builder');
                    echo '<p class="' . esc_attr($status_class) . '"><span class="debug-status">' . esc_html($action) . ':</span> ' . esc_html($status_text) . '</p>';
                    
                    if ($registered) {
                        echo '<div style="margin-left: 20px;">';
                        foreach ($wp_filter[$action]->callbacks as $priority => $callbacks) {
                            foreach ($callbacks as $callback) {
                                if (is_array($callback['function'])) {
                                    $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                                    echo '<p>- ' . esc_html($class) . '::' . esc_html($callback['function'][1]) . ' ' . esc_html__('(priority:', 'connect2form-builder') . ' ' . esc_html($priority) . ')</p>';
                                }
                            }
                        }
                        echo '</div>';
                    }
                }
                
                // Check for integration AJAX handlers if plugin is active
                if (class_exists('Connect2Form\\Integrations\\Core\\Plugin')) {
                    echo '<h3>' . esc_html__('Integration AJAX Handlers:', 'connect2form-builder') . '</h3>';
                    $integration_ajax_actions = [
                        'wp_ajax_mailchimp_save_global_settings',
                        'wp_ajax_connect2form_test_mailchimp_connection',
                        'wp_ajax_connect2form_mailchimp_save_global_settings_v2'
                    ];
                    
                    foreach ($integration_ajax_actions as $action) {
                        $registered = isset($wp_filter[$action]) && !empty($wp_filter[$action]->callbacks);
                        $status_class = $registered ? 'debug-success' : 'debug-error';
                        $status_text = $registered ? esc_html__('REGISTERED', 'connect2form-builder') : esc_html__('NOT REGISTERED', 'connect2form-builder');
                        echo '<p class="' . esc_attr($status_class) . '"><span class="debug-status">' . esc_html($action) . ':</span> ' . esc_html($status_text) . '</p>';
                    }
                }
                ?>
            </div>
            
            <div class="debug-section">
                <h2><?php echo esc_html__('5. Text Domain Test', 'connect2form-builder'); ?></h2>
                <?php
                $domain_loaded = is_textdomain_loaded('connect2form-builder');
                $status_class = $domain_loaded ? 'debug-success' : 'debug-warning';
                $status_text = $domain_loaded ? esc_html__('Yes', 'connect2form-builder') : esc_html__('No', 'connect2form-builder');
                echo '<p class="' . esc_attr($status_class) . '"><span class="debug-status">' . esc_html__('Text domain "connect2form" loaded:', 'connect2form-builder') . '</span> ' . esc_html($status_text) . '</p>';
                
                $test_translation = __('Test String', 'connect2form-builder');
                echo '<p><span class="debug-status">' . esc_html__('Test translation:', 'connect2form-builder') . '</span> ' . esc_html($test_translation) . '</p>';
                ?>
            </div>
            
            <div class="debug-section">
                <h2><?php echo esc_html__('6. URL Tests', 'connect2form-builder'); ?></h2>
                <?php
                echo '<p><span class="debug-status">' . esc_html__('Integrations Page URL:', 'connect2form-builder') . '</span> <a href="' . esc_url(admin_url('admin.php?page=connect2form-integrations')) . '" target="_blank">' . esc_html(esc_url(admin_url('admin.php?page=connect2form-integrations'))) . '</a></p>';
                echo '<p><span class="debug-status">' . esc_html__('Mailchimp Settings URL:', 'connect2form-builder') . '</span> <a href="' . esc_url(admin_url('admin.php?page=connect2form-integrations&tab=settings&integration=mailchimp')) . '" target="_blank">' . esc_html(esc_url(admin_url('admin.php?page=connect2form-integrations&tab=settings&integration=mailchimp'))) . '</a></p>';
                ?>
            </div>
            
            <div class="debug-section">
                <h2><?php echo esc_html__('7. WordPress Environment', 'connect2form-builder'); ?></h2>
                <?php
                echo '<p><span class="debug-status">' . esc_html__('WordPress Version:', 'connect2form-builder') . '</span> ' . esc_html(get_bloginfo('version')) . '</p>';
                echo '<p><span class="debug-status">' . esc_html__('PHP Version:', 'connect2form-builder') . '</span> ' . esc_html(PHP_VERSION) . '</p>';
                echo '<p><span class="debug-status">' . esc_html__('Debug Mode:', 'connect2form-builder') . '</span> ' . (WP_DEBUG ? esc_html__('Enabled', 'connect2form-builder') : esc_html__('Disabled', 'connect2form-builder')) . '</p>';
                echo '<p><span class="debug-status">' . esc_html__('Current User:', 'connect2form-builder') . '</span> ' . esc_html(wp_get_current_user()->user_login) . '</p>';
                echo '<p><span class="debug-status">' . esc_html__('Current Time:', 'connect2form-builder') . '</span> ' . esc_html(current_time('mysql')) . '</p>';
                ?>
            </div>
            
            <div class="debug-section">
                <h2><?php echo esc_html__('8. JavaScript Console Test', 'connect2form-builder'); ?></h2>
                <p><?php echo esc_html__('Check the browser console (F12) for JavaScript debug information.', 'connect2form-builder'); ?></p>
                <script>




                
                // Test if we can detect the integration
                jQuery(document).ready(function($) {



                });
                </script>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        // Check if we're on a Connect2Form page.
        $is_connect2form_page = false;

        // Check various possible hook patterns.
        if ( strpos( $hook, 'connect2form' ) !== false ) {
            $is_connect2form_page = true;
        } elseif ( strpos( $hook, 'toplevel_page_connect2form' ) !== false ) {
            $is_connect2form_page = true;
        } elseif ( strpos( $hook, 'connect2form_page_' ) !== false ) {
            $is_connect2form_page = true;
        } elseif ( isset( $_GET['page'] ) && $_GET['page'] === 'connect2form' ) {
            $is_connect2form_page = true;
        }

        // Additional check for edit form page.
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'connect2form' && isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) {
            $is_connect2form_page = true;
        }

        if ( ! $is_connect2form_page ) {
            return;
        }

        // Enqueue WordPress media scripts.
        wp_enqueue_media();

        // Enqueue dashicons.
        wp_enqueue_style( 'dashicons' );

        // Enqueue jQuery UI.
        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'jquery-ui-draggable' );
        wp_enqueue_script( 'jquery-ui-droppable' );
        wp_enqueue_script( 'jquery-ui-dialog' );
        wp_enqueue_script( 'jquery-ui-tabs' );
        wp_enqueue_script( 'jquery-ui-accordion' );

        // Enqueue admin styles.
        wp_enqueue_style(
            'connect2form-admin',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin.css',
            array( 'dashicons' ),
            CONNECT2FORM_VERSION
        );

        // Enqueue form builder styles.
        wp_enqueue_style(
            'connect2form-form-builder',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/form-builder.css',
            array( 'dashicons' ),
            CONNECT2FORM_VERSION
        );

        // Register and enqueue admin scripts.
        wp_register_script(
            'connect2form-admin',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-core', 'jquery-ui-sortable' ),
            CONNECT2FORM_VERSION,
            true
        );
        wp_enqueue_script( 'connect2form-admin' );

        // Register and enqueue form builder scripts.
        $form_builder_script_url = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/form-builder.js';

        // Check if script file exists.
        $form_builder_script_path = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/form-builder.js';

        wp_register_script(
            'connect2form-form-builder',
            $form_builder_script_url,
            array( 'jquery', 'jquery-ui-core', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable' ),
            CONNECT2FORM_VERSION,
            true
        );
        wp_enqueue_script( 'connect2form-form-builder' );

        // Localize form builder script.
        $form_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        $form = null;
        if ( $form_id ) {
            // Use service class instead of direct database call.
            if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
                $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
                $form = $service_manager->forms()->get_form( $form_id );
            } else {
                // Fallback to direct database call if service not available.
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form query; service layer preferred but this is a fallback
                $form = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
                    $form_id
                ) );
            }
        }

        wp_localize_script( 'connect2form-form-builder', 'connect2formCF', array(
            'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce' => wp_create_nonce( 'connect2form_nonce' ),
            'formDeleteNonce' => wp_create_nonce( 'connect2form_delete_form' ),
            'formBulkDeleteNonce' => wp_create_nonce( 'connect2form_bulk_delete_forms' ),
            'submissionDeleteNonce' => wp_create_nonce( 'connect2form_delete_submission' ),
            'submissionBulkDeleteNonce' => wp_create_nonce( 'connect2form_bulk_delete_submissions' ),
            'submissionBulkExportNonce' => wp_create_nonce( 'connect2form_bulk_export_submissions' ),
            'formsListUrl' => esc_url( admin_url( 'admin.php?page=connect2form' ) ),
            'currentFormId' => $form_id,
            'adminEmail' => get_option( 'admin_email' ),
            'formData' => $form ? array(
                'id' => $form->id,
                'title' => $form->form_title,
                'fields' => is_array( $form->fields ) ? $form->fields : array(),
                'settings' => is_array( $form->settings ) ? $form->settings : array(),
            ) : array(),
            'i18n' => array(
                'confirmDelete' => __('Are you sure you want to delete this field?', 'connect2form-builder'),
                'fieldRequired' => __('This field is required', 'connect2form-builder'),
                'saveSuccess' => __('Form saved successfully', 'connect2form-builder'),
                'saveError' => __('Error saving form', 'connect2form-builder'),
            ),
        ) );

        // Add inline script to test if JavaScript is working.

        // Load integration scripts if addon is active.
        if ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'addons/integrations-manager/integrations-manager.php' ) ) {
            // Enqueue integration styles.
            wp_enqueue_style(
                'connect2form-integrations-admin',
                plugin_dir_url( dirname( __FILE__ ) ) . 'addons/integrations-manager/assets/css/admin/integrations-admin.css',
                array( 'dashicons' ),
                CONNECT2FORM_VERSION
            );

            // Register and enqueue integration scripts.
            wp_register_script(
                'connect2form-integrations-admin',
                plugin_dir_url( dirname( __FILE__ ) ) . 'addons/integrations-manager/assets/js/admin/integrations-admin.js',
                array( 'jquery', 'wp-util' ),
                CONNECT2FORM_VERSION,
                true
            );
            wp_enqueue_script( 'connect2form-integrations-admin' );

            // Register and enqueue Mailchimp scripts for form edit pages.
            // Note: HubSpot handles its own script loading to avoid conflicts.
            wp_register_script(
                'connect2form-mailchimp',
                plugin_dir_url( dirname( __FILE__ ) ) . 'addons/integrations-manager/assets/js/admin/mailchimp.js',
                array( 'jquery', 'wp-util' ),
                CONNECT2FORM_VERSION,
                true
            );
            wp_enqueue_script( 'connect2form-mailchimp' );

            wp_register_script(
                'connect2form-mailchimp-form',
                plugin_dir_url( dirname( __FILE__ ) ) . 'addons/integrations-manager/assets/js/admin/mailchimp-form.js',
                array( 'jquery', 'wp-util', 'connect2form-mailchimp' ),
                CONNECT2FORM_VERSION,
                true
            );
            wp_enqueue_script( 'connect2form-mailchimp-form' );

            // Note: Each integration handles its own localization to avoid conflicts.
        }

        // Fallback: Ensure form-builder.js is loaded on edit form pages.
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'connect2form' && isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) {
            // Double-check if form-builder script is already enqueued.
            if ( ! wp_script_is( 'connect2form-form-builder', 'enqueued' ) ) {
                wp_enqueue_script( 'connect2form-form-builder' );
            }
        }
    }



    /**
     * Render the forms list page
     */
    public function render_forms_list_page() {
        // Check user permissions first.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('Sorry, you are not allowed to access this page.', 'connect2form-builder') );
        }

        // Check if we're editing a form.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only routing
            $form_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
            $this->render_form_builder( $form_id );
            return;
        }

        // Check if we're previewing a form.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'preview' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only routing
            $form_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
            $this->render_form_preview( $form_id );
            return;
        }

        // Include the list table class.
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-connect2form-forms-list-table.php';

        // Create an instance of our list table class.
        $forms_table = new Connect2Form_Forms_List_Table();
        $forms_table->prepare_items();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Contact Forms', 'connect2form-builder'); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=connect2form-new-form' ) ); ?>" class="page-title-action"><?php esc_html_e('Add New', 'connect2form-builder'); ?></a>

            <hr class="wp-header-end">

            <form method="post">
                <?php
                $forms_table->search_box( __('Search Forms', 'connect2form-builder'), 'search_id' );
                $forms_table->display();
                ?>
            </form>
        </div>
        <?php

        // Localize admin script for forms list page.
        wp_localize_script( 'connect2form-admin', 'connect2formCF', array(
            'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce' => wp_create_nonce( 'connect2form_nonce' ),
            'formDeleteNonce' => wp_create_nonce( 'connect2form_delete_form' ),
            'formBulkDeleteNonce' => wp_create_nonce( 'connect2form_bulk_delete_forms' ),
            'submissionDeleteNonce' => wp_create_nonce( 'connect2form_delete_submission' ),
            'submissionBulkDeleteNonce' => wp_create_nonce( 'connect2form_bulk_delete_submissions' ),
            'submissionBulkExportNonce' => wp_create_nonce( 'connect2form_bulk_export_submissions' ),
            'confirmDelete' => __('Are you sure you want to delete this form?', 'connect2form-builder'),
            'confirmDeleteSubmission' => __('Are you sure you want to delete this submission?', 'connect2form-builder'),
            'copiedText' => __('Copied!', 'connect2form-builder'),
            'previewUrl' => esc_url( admin_url( 'admin.php?page=connect2form&action=preview' ) ),
        ) );
    }

    /**
     * Render the form builder page
     */
    public function render_form_builder($form_id = null) {
        // If no form_id passed, check URL parameters
        if (!$form_id && isset($_GET['id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only routing
            $form_id = absint( wp_unslash( $_GET['id'] ) );
        }
        
        $form = null;
        if ($form_id) {
            // Use service class instead of direct database call
            if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
                $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
                $form = $service_manager->forms()->get_form($form_id);
                

            } else {
                // Fallback to direct database call if service not available
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form query; service layer preferred but this is a fallback
                $form = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
                    $form_id
                ));
                
                if ($form) {
                    // Decode JSON for direct database calls - only if they are strings
                    $form->fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
                    $form->settings = is_string($form->settings) ? json_decode($form->settings, true) : (is_array($form->settings) ? $form->settings : array());
                }
            }
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($form_id ? 'Edit Form' : 'Add New Form'); ?></h1>
            <hr class="wp-header-end">

            <div class="form-builder-header">
                <div class="form-title-section">
                    <input type="text" id="form-title" name="form_title" value="<?php echo esc_attr($form ? $form->form_title : ''); ?>" placeholder="Enter form title" class="regular-text">
                </div>
                <div class="form-actions">
                    <button type="button" class="button button-primary" id="save-form">Save Form</button>
                    <button type="button" class="button button-secondary" id="preview-form">Preview Form</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form')); ?>" class="button">Cancel</a>
                </div>
            </div>
            <div id="form-notification" class="connect2form-notice" style="display: none;"></div>
            <div class="form-builder-vertical-layout" id="form-builder" data-form-id="<?php echo esc_attr($form_id ? $form_id : 0); ?>">
                <div class="form-builder-tabs">
                    <button type="button" class="tab-link active" data-tab="fields-tab">
                        <span class="dashicons dashicons-editor-table"></span> Fields
                    </button>
                    <button type="button" class="tab-link" data-tab="email-tab">
                        <span class="dashicons dashicons-email-alt"></span> Email Notifications
                    </button>
                    <button type="button" class="tab-link" data-tab="messages-tab">
                        <span class="dashicons dashicons-format-chat"></span> Messages
                    </button>
                    <button type="button" class="tab-link" data-tab="integration-tab">
                        <span class="dashicons dashicons-admin-plugins"></span> Integration
                    </button>
                </div>
                <div class="form-builder-tab-contents">
                    <div class="form-builder-tab-content fields-tab active">
                        <div class="form-builder-fields-layout">
                            <div class="form-fields-container">
                                <div class="form-fields" id="form-fields">
                                    <?php
                                    if ($form && !empty($form->fields)) {
                                        $fields = is_array($form->fields) ? $form->fields : array();
                                        foreach ($fields as $field) {
                                            $this->render_field_preview($field);
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="form-fields-sidebar">
                                <div class="field-types">
                                    <h3><?php echo esc_html__('Field Types', 'connect2form-builder'); ?></h3>
                                    <div class="field-type-list">
                                        <?php
                                        $field_types = array(
                                            'text' => array(
                                                'label' => __('Textbox', 'connect2form-builder'),
                                                'icon' => 'dashicons-editor-textcolor',
                                                'settings' => array('label', 'placeholder', 'required', 'validation')
                                            ),
                                            'textarea' => array(
                                                'label' => __('Textarea', 'connect2form-builder'),
                                                'icon' => 'dashicons-editor-paragraph'
                                            ),
                                            'email' => array(
                                                'label' => __('Email', 'connect2form-builder'),
                                                'icon' => 'dashicons-email'
                                            ),
                                            /* 'number' => array(
                                                'label' => __('Number', 'connect2form-builder'),
                                                'icon' => 'dashicons-calculator'
                                            ), */
                                            'date' => array(
                                                'label' => __('Date', 'connect2form-builder'),
                                                'icon' => 'dashicons-calendar-alt'
                                            ),
                                            'phone' => array(
                                                'label' => __('Phone Number', 'connect2form-builder'),
                                                'icon' => 'dashicons-phone'
                                            ),
                                            'select' => array(
                                                'label' => __('Drop Down', 'connect2form-builder'),
                                                'icon' => 'dashicons-arrow-down-alt2'
                                            ),
                                            'radio' => array(
                                                'label' => __('Radio Buttons', 'connect2form-builder'),
                                                'icon' => 'dashicons-marker'
                                            ),
                                            'checkbox' => array(
                                                'label' => __('Checkboxes', 'connect2form-builder'),
                                                'icon' => 'dashicons-yes'
                                            ),
                                            'file' => array(
                                                'label' => __('File Upload', 'connect2form-builder'),
                                                'icon' => 'dashicons-upload'
                                            ),
                                            'utm' => array(
                                                'label' => __('UTM Tracking', 'connect2form-builder'),
                                                'icon' => 'dashicons-chart-line'
                                            ),
                                            'captcha' => array(
                                                'label' => __('reCAPTCHA', 'connect2form-builder'),
                                                'icon' => 'dashicons-shield'
                                            )
                                        );

                                        foreach ($field_types as $type => $field) {
                                            ?>
                                            <div class="field-type" data-type="<?php echo esc_attr($type); ?>">
                                                <span class="dashicons <?php echo esc_attr($field['icon']); ?>"></span>
                                                <span class="field-type-label"><?php echo esc_html($field['label']); ?></span>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="form-builder-tab-content email-tab">
                        <h3>Email Notifications</h3>
                        <div id="email-notifications-list"></div>
                        <button type="button" class="button" id="add-email-notification">Add Notification</button>
                        <div id="email-notification-editor" style="display:none;"></div>
                    </div>
                    <div class="form-builder-tab-content messages-tab">
                        <h3>Form Messages</h3>
                        <div class="messages-settings">
                            <div class="message-setting">
                                <label for="error-message">Error Message</label>
                                <textarea id="error-message" class="widefat" rows="3" placeholder="Please fix the errors below and try again."><?php echo esc_textarea($this->get_form_setting($form, 'error_message', '')); ?></textarea>
                                <p class="description">Message displayed when there are validation errors.</p>
                            </div>
                            
                            <div class="message-setting">
                                <label for="submit-button-text">Submit Button Text</label>
                                <input type="text" id="submit-button-text" class="widefat" value="<?php echo esc_attr($this->get_form_setting($form, 'submit_text', 'Submit')); ?>" placeholder="Submit">
                                <p class="description">Text displayed on the submit button.</p>
                            </div>
                            
                            <div class="message-setting">
                                <label for="required-field-message">Required Field Message</label>
                                <input type="text" id="required-field-message" class="widefat" value="<?php echo esc_attr($this->get_form_setting($form, 'required_message', 'This field is required.')); ?>" placeholder="This field is required.">
                                <p class="description">Message displayed for required fields that are not filled.</p>
                            </div>
                            
                            <div class="message-setting">
                                <label for="redirect-type">After Submission Action</label>
                                <select id="redirect-type" class="widefat">
                                    <option value="message" <?php selected($this->get_form_setting($form, 'redirect_type', 'message'), 'message'); ?>><?php echo esc_html__('Show Thank You Message', 'connect2form-builder'); ?></option>
                                    <option value="redirect" <?php selected($this->get_form_setting($form, 'redirect_type', 'message'), 'redirect'); ?>><?php echo esc_html__('Redirect to URL', 'connect2form-builder'); ?></option>
                                </select>
                                <p class="description">Choose what happens after successful form submission.</p>
                            </div>
                            
                            <div class="message-setting" id="thank-you-message-container">
                                <label for="thank-you-message">Thank You Message</label>
                                <textarea id="thank-you-message" class="widefat" rows="5" placeholder="Thank you for your submission! We'll get back to you soon."><?php echo esc_textarea($this->get_form_setting($form, 'thank_you_message', '')); ?></textarea>
                                <p class="description">Message displayed after successful form submission (when "Show Thank You Message" is selected).</p>
                            </div>
                            
                            <div class="message-setting" id="redirect-url-container" style="display: none;">
                                <label for="redirect-url">Redirect URL</label>
                                <input type="url" id="redirect-url" class="widefat" value="<?php echo esc_attr($this->get_form_setting($form, 'redirect_url', '')); ?>" placeholder="https://example.com/thank-you">
                                <p class="description">URL to redirect to after successful form submission (when "Redirect to URL" is selected).</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-builder-tab-content integration-tab">
                        <h3>Form Integration</h3>
                        <div class="integration-settings">

                        <!-- Integration System -->

                        <?php if (defined('CONNECT2FORM_INTEGRATIONS_DIR') && file_exists(CONNECT2FORM_INTEGRATIONS_DIR . 'templates/integration-section.php')): ?>

<?php

// Set form ID for the integration templates

$GLOBALS['connect2form_current_form_id'] = $form_id;



// Include the integration section template

include CONNECT2FORM_INTEGRATIONS_DIR . 'templates/integration-section.php';

?>

<?php else: ?>

<!-- Fallback if integrations addon not available -->

<div class="integration-setting">

    <h4>Third-party Integrations</h4>

    <p class="description">Install the Integrations Manager addon to connect with Mailchimp, HubSpot, and other services.</p>

</div>

<?php endif; ?>



<?php

// Hook for addon integrations to inject their UI

do_action('connect2form_render_additional_integrations', $form_id, $form);

?>
                            <div class="integration-setting">
                                <h4>Shortcode</h4>
                                <div class="shortcode-display">
                                    <code id="form-shortcode">[connect2form id="<?php echo esc_attr($form_id ? $form_id : 'FORM_ID'); ?>"]</code>
                                    <button type="button" class="button copy-shortcode" data-clipboard-target="#form-shortcode">
                                        <span class="dashicons dashicons-clipboard"></span> Copy
                                    </button>
                                </div>
                                <p class="description">Use this shortcode to display the form on any page or post.</p>
                            </div>
                            
                            <div class="integration-setting">
                                <h4>PHP Code</h4>
                                <div class="php-code-display">
                                    <code id="form-php-code">&lt;?php echo do_shortcode('[connect2form id="<?php echo esc_attr($form_id ? $form_id : 'FORM_ID'); ?>"]'); ?&gt;</code>
                                    <button type="button" class="button copy-php-code" data-clipboard-target="#form-php-code">
                                        <span class="dashicons dashicons-clipboard"></span> Copy
                                    </button>
                                </div>
                                <p class="description">Use this PHP code to display the form in your theme files.</p>
                            </div>
                            
                            <div class="integration-setting">
                                <h4>Form Settings</h4>
                                <div class="form-options">
                                    <label>
                                        <input type="checkbox" id="enable-ajax" <?php checked($this->get_form_setting($form, 'enable_ajax', true)); ?>>
                                        Enable AJAX submission
                                    </label>
                                    <p class="description">Submit form without page reload.</p>
                                    
                                    <label>
                                        <input type="checkbox" id="enable-scroll-to-error" <?php checked($this->get_form_setting($form, 'scroll_to_error', true)); ?>>
                                        Scroll to error on validation failure
                                    </label>
                                    <p class="description">Automatically scroll to the first error field.</p>
                                    
                                    <label>
                                        <input type="checkbox" id="enable-honeypot" <?php checked($this->get_form_setting($form, 'enable_honeypot', true)); ?>>
                                        Enable honeypot protection
                                    </label>
                                    <p class="description">Add hidden field to prevent spam bots.</p>
                                </div>
                            </div>


                        </div>
                    </div>
                </div>
            </div>
        </div>
        

        
        <!-- Tab functionality is now handled cleanly by form-builder.js -->
        <?php
        
        // Localize admin script
        wp_localize_script('connect2form-admin', 'connect2formCF', array(
            'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('connect2form_nonce'),
            'formDeleteNonce' => wp_create_nonce('connect2form_delete_form'),
            'formBulkDeleteNonce' => wp_create_nonce('connect2form_bulk_delete_forms'),
            'submissionDeleteNonce' => wp_create_nonce('connect2form_delete_submission'),
            'submissionBulkDeleteNonce' => wp_create_nonce('connect2form_bulk_delete_submissions'),
            'submissionBulkExportNonce' => wp_create_nonce('connect2form_bulk_export_submissions'),
            'confirmDelete' => __('Are you sure you want to delete this form?', 'connect2form-builder'),
            'confirmDeleteSubmission' => __('Are you sure you want to delete this submission?', 'connect2form-builder'),
            'copiedText' => __('Copied!', 'connect2form-builder'),
            'previewUrl' => esc_url(admin_url('admin.php?page=connect2form&action=preview'))
        ));
    }

    /**
     * Render a field preview
     */
    private function render_field_preview($field) {
        $field_id = $field['id'] ?? uniqid('field_');
        $field_type = $field['type'] ?? 'text';
        $field_label = $field['label'] ?? '';
        $field_required = isset($field['required']) ? (bool)$field['required'] : false;
        $field_placeholder = $field['placeholder'] ?? '';
        $field_description = $field['description'] ?? '';
        $field_options = $field['options'] ?? [];
        $field_validation = $field['validation'] ?? [];
        $field_conditional = $field['conditional'] ?? null;
        $field_advanced = $field['advanced'] ?? [];
        ?>
        <div class="form-field" data-field-id="<?php echo esc_attr($field_id); ?>" data-field-type="<?php echo esc_attr($field_type); ?>" data-field-data='<?php echo esc_attr(wp_json_encode($field)); ?>'>
            <div class="field-header">
                <span class="field-label"><?php echo esc_html($field_label); ?></span>
                <div class="field-actions">
                    <button type="button" class="edit-field" title="Edit Field">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="delete-field" title="Delete Field">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            <div class="field-preview">
                <?php
                switch ($field_type) {
                    case 'text':
                    case 'email':
                    case 'number':
                    case 'date':
                    case 'phone':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="<?php echo esc_attr($field_type); ?>" 
                               placeholder="<?php echo esc_attr($field_placeholder); ?>"
                               <?php if ($field_required): ?>required<?php endif; ?>
                               <?php if ($field_type === 'phone'): ?>pattern="[0-9\+\-\(\)\s]+" title="Please enter a valid phone number"<?php endif; ?>>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'textarea':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <textarea placeholder="<?php echo esc_attr($field_placeholder); ?>"
                                  <?php if ($field_required): ?>required<?php endif; ?>></textarea>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'select':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <select <?php if ($field_required): ?>required<?php endif; ?>>
                            <option value=""><?php echo esc_html($field_placeholder); ?></option>
                            <?php foreach ($field_options as $option): ?>
                                <?php
                                // Handle both string options and object options
                                if (is_string($option)) {
                                    $option_value = $option;
                                    $option_label = $option;
                                } else {
                                    $option_value = $option['value'] ?? $option;
                                    $option_label = $option['label'] ?? $option_value;
                                }
                                ?>
                                <option value="<?php echo esc_attr($option_value); ?>">
                                    <?php echo esc_html($option_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'radio':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <?php foreach ($field_options as $option): ?>
                            <?php
                            // Handle both string options and object options
                            if (is_string($option)) {
                                $option_value = $option;
                                $option_label = $option;
                            } else {
                                $option_value = $option['value'] ?? $option;
                                $option_label = $option['label'] ?? $option_value;
                            }
                            ?>
                            <label class="radio-option">
                                <input type="radio" 
                                       name="<?php echo esc_attr($field_id); ?>"
                                       value="<?php echo esc_attr($option_value); ?>"
                                       <?php if ($field_required): ?>required<?php endif; ?>>
                                <?php echo esc_html($option_label); ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'checkbox':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <?php foreach ($field_options as $option): ?>
                            <?php
                            // Handle both string options and object options
                            if (is_string($option)) {
                                $option_value = $option;
                                $option_label = $option;
                            } else {
                                $option_value = $option['value'] ?? $option;
                                $option_label = $option['label'] ?? $option_value;
                            }
                            ?>
                            <label class="checkbox-option">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr($field_id); ?>[]"
                                       value="<?php echo esc_attr($option_value); ?>"
                                       <?php if ($field_required): ?>required<?php endif; ?>>
                                <?php echo esc_html($option_label); ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;

                    case 'file':
                        ?>
                        <label>
                            <?php echo esc_html($field_label); ?>
                            <?php if ($field_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="file" <?php if ($field_required): ?>required<?php endif; ?>>
                        <?php if ($field_description): ?>
                            <p class="description"><?php echo esc_html($field_description); ?></p>
                        <?php endif;
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function render_submissions_page() {
        // Check if we're viewing a specific submission
        if (isset($_GET['action']) && $_GET['action'] === 'view') {
            $submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $this->render_submission_view($submission_id);
            return;
        }

        // Include the list table class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-connect2form-submissions-list-table.php';

        // Get form ID from URL if specified
        $form_id = isset($_GET['form_id']) && !empty($_GET['form_id']) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        


        // Create an instance of our list table class with form ID
        $submissions_table = new Connect2Form_Submissions_List_Table($form_id);
        
        // Set the form_id in the table instance for proper filtering
        $submissions_table->form_id = $form_id;
        
        $submissions_table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Form Submissions', 'connect2form-builder'); ?></h1>
            <hr class="wp-header-end">



            <?php if ($form_id): ?>
                <?php
                // Use service class instead of direct database call
                $form = null;
                if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
                    $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
                    $form = $service_manager->forms()->get_form($form_id);
                } else {
                    // Fallback to direct database call if service not available
                    global $wpdb;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form title query; service layer preferred but this is a fallback
                    $form = $wpdb->get_row($wpdb->prepare("SELECT form_title FROM {$wpdb->prefix}connect2form_forms WHERE id = %d", $form_id));
                }
                if ($form): ?>
                    <div class="notice notice-info">
                        <p><?php echo wp_kses_post(sprintf(
                            /* translators: %s: Form title */
                            __('Showing submissions for form: <strong>%s</strong>', 'connect2form-builder'), 
                            esc_html($form->form_title)
                        )); ?></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php echo esc_html__('Showing all form submissions. Use the filter below to view submissions for a specific form.', 'connect2form-builder'); ?></p>
                </div>
            <?php endif; ?>

            <div class="notice notice-warning">
                <p><?php echo wp_kses_post(__('&lt;strong&gt;Note:&lt;/strong&gt; The submissions table displays only the first 4 form fields for better readability. To view all field data, click on the "View" action for individual submissions.', 'connect2form-builder')); ?></p>
            </div>

            <form method="post">
                <?php wp_nonce_field('bulk-submissions'); ?>
                <?php
                $submissions_table->search_box(__('Search Submissions', 'connect2form-builder'), 'search_id');
                $submissions_table->display();
                ?>
            </form>
        </div>
        <?php

        // Localize admin script for submissions page
        wp_localize_script('connect2form-admin', 'connect2formCF', array(
            'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('connect2form_nonce'),
            'formDeleteNonce' => wp_create_nonce('connect2form_delete_form'),
            'formBulkDeleteNonce' => wp_create_nonce('connect2form_bulk_delete_forms'),
            'submissionDeleteNonce' => wp_create_nonce('connect2form_delete_submission'),
            'submissionBulkDeleteNonce' => wp_create_nonce('connect2form_bulk_delete_submissions'),
            'submissionBulkExportNonce' => wp_create_nonce('connect2form_bulk_export_submissions'),
            'confirmDelete' => __('Are you sure you want to delete this form?', 'connect2form-builder'),
            'confirmDeleteSubmission' => __('Are you sure you want to delete this submission?', 'connect2form-builder'),
            'copiedText' => __('Copied!', 'connect2form-builder'),
            'previewUrl' => esc_url(admin_url('admin.php?page=connect2form&action=preview'))
        ));
    }

    /**
     * Render individual submission view
     */
    private function render_submission_view($submission_id) {
        // Use direct database call due to schema mismatch with ServiceManager
        // ServiceManager expects extended schema with utm_data column
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- submission query for admin display; direct access needed due to schema mismatch
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}connect2form_submissions WHERE id = %d",
            $submission_id
        ));

        if (!$submission) {
            wp_die(esc_html__('Submission not found.', 'connect2form-builder'));
        }

        // Get form data
        $form = $this->get_form($submission->form_id);
        if (!$form) {
            wp_die(esc_html__('Form not found.', 'connect2form-builder'));
        }

        // Parse submission data
        if (is_string($submission->data)) {
            $submission_data = json_decode($submission->data, true);
        } else {
            $submission_data = is_array($submission->data) ? $submission->data : array();
        }
        if (!$submission_data) {
            $submission_data = array();
        }




        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html(sprintf(
                    /* translators: %d: Submission ID number */
                    __('Submission #%d', 'connect2form-builder'), 
                    $submission_id
                )); ?>
            </h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-submissions')); ?>" class="page-title-action">
                <?php esc_html_e('← Back to Submissions', 'connect2form-builder'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="submission-details">
                <div class="submission-meta">
                    <h2><?php echo esc_html__('Submission Details', 'connect2form-builder'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Form', 'connect2form-builder'); ?></th>
                            <td><?php echo esc_html($form->form_title); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Submitted', 'connect2form-builder'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at))); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('IP Address', 'connect2form-builder'); ?></th>
                            <td><?php echo esc_html($submission->ip_address ?? __('N/A', 'connect2form-builder')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('User Agent', 'connect2form-builder'); ?></th>
                            <td><?php echo esc_html($submission->user_agent ?? __('N/A', 'connect2form-builder')); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="submission-fields">
                    <h2><?php echo esc_html__('Form Data', 'connect2form-builder'); ?></h2>
                    <table class="form-table">
                        <?php
                        if (!empty($form->fields) && is_array($form->fields)) {
                            foreach ($form->fields as $field) {
                                $field_id = $field['id'];
                                $field_label = $field['label'] ?? $field_id;
                                $field_value = $submission_data[$field_id] ?? '';
                                
                                // Skip certain field types
                                if ($field['type'] === 'html' || $field['type'] === 'hidden' || $field['type'] === 'submit') {
                                    continue;
                                }

                                echo '<tr>';
                                echo '<th scope="row">' . esc_html($field_label) . '</th>';
                                echo '<td>' . wp_kses_post($this->format_submission_field_value($field, $field_value)) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="2">' . esc_html__('No form fields found or form fields not properly configured.', 'connect2form-builder') . '</td></tr>';
                        }
                        ?>
                    </table>
                </div>

                <div class="submission-actions">
                    <h2><?php echo esc_html__('Actions', 'connect2form-builder'); ?></h2>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-submissions')); ?>" class="button">
                            <?php esc_html_e('← Back to Submissions', 'connect2form-builder'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form-submissions&action=delete&id=' . $submission_id)); ?>" 
                           class="button button-link-delete" 
                           onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this submission?', 'connect2form-builder')); ?>');">
                            <?php esc_html_e('Delete Submission', 'connect2form-builder'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Format submission field value for display
     */
    private function format_submission_field_value($field, $value) {
        if (empty($value)) {
            return '<em>' . __('No value', 'connect2form-builder') . '</em>';
        }

        switch ($field['type']) {
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
                    return implode('<br>', $links);
                }
                return sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($value),
                    __('View File', 'connect2form-builder')
                );

            case 'utm':
                if (is_array($value)) {
                    $utm_parts = array();
                    foreach ($value as $param => $param_value) {
                        if (!empty($param_value)) {
                            $utm_parts[] = '<strong>' . esc_html($param) . ':</strong> ' . esc_html($param_value);
                        }
                    }
                    return !empty($utm_parts) ? implode('<br>', $utm_parts) : '<em>' . __('No UTM data captured', 'connect2form-builder') . '</em>';
                }
                return esc_html($value);

            case 'checkbox':
            case 'multiselect':
                if (is_array($value)) {
                    return implode(', ', array_map('esc_html', $value));
                }
                return esc_html($value);

            case 'radio':
            case 'select':
                return esc_html($value);

            case 'textarea':
                return '<pre>' . esc_html($value) . '</pre>';

            case 'date':
                return esc_html(date_i18n(get_option('date_format'), strtotime($value)));

            case 'email':
                return sprintf('<a href="mailto:%s">%s</a>', esc_attr($value), esc_html($value));

            default:
                return esc_html($value);
        }
    }

    private function get_form($form_id) {
        // Use service class instead of direct database call
        if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $form = $service_manager->forms()->get_form($form_id);
            
            if ($form) {
                // FormService already decodes these, so ensure they are arrays
                $form->fields = is_array($form->fields) ? $form->fields : array();
                $form->settings = is_array($form->settings) ? $form->settings : array();
            }
            
            return $form;
        } else {
            // Fallback to direct database call if service not available
            global $wpdb;
            $table_name = $wpdb->prefix . 'connect2form_forms';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fallback form query with validated table identifier; service layer preferred but this is a fallback
            $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table_name}` WHERE id = %d", $form_id));
            
            if ($form) {
                // Decode JSON for direct database calls - only if they are strings
                $form->fields = is_string($form->fields) ? json_decode($form->fields, true) : (is_array($form->fields) ? $form->fields : array());
                $form->settings = is_string($form->settings) ? json_decode($form->settings, true) : (is_array($form->settings) ? $form->settings : array());
            }
            
            return $form;
        }
    }

    /**
     * AJAX handler for saving forms
     */
    public function ajax_save_form() {
        try {
            // Verify nonce.
            if ( ! check_ajax_referer( 'connect2form_nonce', 'nonce', false ) ) {
                throw new Exception( esc_html__('Invalid security token.', 'connect2form-builder') );
            }

            // Check if database tables exist.
            global $wpdb;
            $table_name = $wpdb->prefix . 'connect2form_forms';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for admin save; schema validation requires direct query, caching not applicable
            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

            if ( ! $table_exists ) {
                $this->log_error( 'Database table does not exist: ' . $table_name );
                throw new Exception( esc_html__('Database table is missing. Please deactivate and reactivate the plugin.', 'connect2form-builder') );
            }

            // Check permissions - allow administrators.
            if ( ! current_user_can( 'manage_options' ) ) {
                throw new Exception( esc_html__('Permission denied.', 'connect2form-builder') );
            }

            // Get and validate form data.
            $form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
            $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $fields = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string decoded and validated below
            $settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string decoded and validated below

            // Filter form data before validation.
            $form_data = apply_filters( 'connect2form_form_data_before_save', array(
                'form_id' => $form_id,
                'title' => $title,
                'fields' => $fields,
                'settings' => $settings,
            ) );

            $form_id = $form_data['form_id'];
            $title = $form_data['title'];
            $fields = $form_data['fields'];
            $settings = $form_data['settings'];

            if ( empty( $title ) ) {
                throw new Exception( esc_html__('Form title is required.', 'connect2form-builder') );
            }

            if ( empty( $fields ) ) {
                throw new Exception( esc_html__('Form fields are required.', 'connect2form-builder') );
            }

            // Validate JSON.
            $fields_data = json_decode( $fields, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $this->log_error( 'Invalid form fields JSON: ' . wp_strip_all_tags( json_last_error_msg() ) );
                throw new Exception( esc_html__('Invalid form fields data.', 'connect2form-builder') );
            }

            // Filter fields data.
            $fields_data = apply_filters( 'connect2form_fields_data_before_save', $fields_data, $form_id );
            $fields = wp_json_encode( $fields_data );

            if ( empty( $settings ) ) {
                $settings = wp_json_encode( array(
                    'submit_text' => 'Submit',
                    'success_message' => 'Form submitted successfully',
                    'error_message' => 'Please fix the errors below',
                    'required_message' => 'This field is required.',
                    'redirect_type' => 'message',
                    'thank_you_message' => 'Thank you for your submission! We\'ll get back to you soon.',
                    'redirect_url' => '',
                ) );
            }

            // Validate settings JSON.
            $settings_data = json_decode( $settings, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $this->log_error( 'Invalid form settings JSON: ' . wp_strip_all_tags( json_last_error_msg() ) );
                throw new Exception( esc_html__('Invalid form settings data.', 'connect2form-builder') );
            }

            // Filter settings data.
            $settings_data = apply_filters( 'connect2form_settings_data_before_save', $settings_data, $form_id );
            $settings = wp_json_encode( $settings_data );

            $result = false;
            $new_form_id = $form_id;

            if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
                try {
                    $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
                } catch ( Exception $service_error ) {
                    $this->log_error( 'ServiceManager initialization failed: ' . $service_error->getMessage() );
                    // Force fallback to direct database method.
                    $service_manager = null;
                }
            } else {
                $service_manager = null;
            }

            if ( $service_manager ) {
                $form_data = array(
                    'title' => $title,
                    'fields' => $fields_data, // Use decoded array, not JSON string.
                    'settings' => $settings_data, // Use decoded array, not JSON string.
                    'status' => 'active',
                );

                // Filter database data before saving.
                $form_data = apply_filters( 'connect2form_db_data_before_save', $form_data, $form_id );

                // Action hook before saving form.
                do_action( 'connect2form_before_save_form', $form_data, $form_id );

                if ( $form_id ) {
                    // Action hook before updating form.
                    do_action( 'connect2form_before_update_form', $form_data, $form_id );

                    $form_data['form_id'] = $form_id; // Add form_id to the data.
                    $result = $service_manager->forms()->save_form( $form_data );

                    if ( $result === false ) {
                        throw new Exception( esc_html__('Failed to update form.', 'connect2form-builder') );
                    }

                    // Action hook after updating form.
                    do_action( 'connect2form_after_update_form', $form_id, $form_data );
                } else {
                    // Action hook before creating form.
                    do_action( 'connect2form_before_create_form', $form_data );

                    $new_form_id = $service_manager->forms()->save_form( $form_data );

                    if ( $new_form_id === false ) {
                        throw new Exception( esc_html__('Failed to create form.', 'connect2form-builder') );
                    }

                    $form_id = $new_form_id;

                    // Action hook after creating form.
                    do_action( 'connect2form_after_create_form', $form_id, $form_data );
                }

                // Clear service caches.
                $service_manager->clear_all_caches();
            } else {
                // Fallback to direct database call if service not available.
                global $wpdb;
                $table_name = $wpdb->prefix . 'connect2form_forms';

                $data = array(
                    'form_title' => $title,
                    'fields' => $fields,
                    'settings' => $settings,
                    'status' => 'active',
                    'updated_at' => current_time( 'mysql' ),
                );

                // Filter database data before saving.
                $data = apply_filters( 'connect2form_db_data_before_save', $data, $form_id );

                $format = array(
                    '%s', // form_title.
                    '%s', // fields.
                    '%s', // settings.
                    '%s', // status.
                    '%s', // updated_at.
                );

                // Action hook before saving form.
                do_action( 'connect2form_before_save_form', $data, $form_id );

                if ( $form_id ) {
                    // Action hook before updating form.
                    do_action( 'connect2form_before_update_form', $data, $form_id );

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- form update operation; no caching needed for form saving
                    $result = $wpdb->update(
                        $table_name,
                        $data,
                        array( 'id' => $form_id ),
                        $format,
                        array( '%d' )
                    );

                    if ( $result === false ) {
                        $this->log_error( 'DB update failed: ' . wp_strip_all_tags( $wpdb->last_error ) );
                        throw new Exception( esc_html__('Failed to update form.', 'connect2form-builder') );
                    }

                    // Action hook after updating form
                    do_action('connect2form_after_update_form', $form_id, $data);
                } else {
                    $data['created_at'] = current_time('mysql');
                    $format[] = '%s'; // created_at

                    // Action hook before creating form
                    do_action('connect2form_before_create_form', $data);
                    
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- form creation operation; no caching needed for form saving
                    $result = $wpdb->insert($table_name, $data, $format);

                    if ($result === false) {
                        $this->log_error('DB insert failed: ' . wp_strip_all_tags($wpdb->last_error));
                        throw new Exception( esc_html__('Failed to create form.', 'connect2form-builder') );
                    }

                    $new_form_id = $wpdb->insert_id;
                    $form_id = $new_form_id;

                    // Action hook after creating form
                    do_action('connect2form_after_create_form', $form_id, $data);
                }
            }

            // Action hook after saving form
            do_action('connect2form_after_save_form', $form_id, $form_data);

            $response_data = array(
                'message' => 'Form saved successfully',
                'form_id' => $form_id
            );

            // Filter response data
            $response_data = apply_filters('connect2form_save_form_response', $response_data, $form_id, $form_data);

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            $error_message = apply_filters('connect2form_save_form_error', $e->getMessage(), $e);
            
            // Enhanced error logging for debugging
            $this->log_error('Form save failed: ' . $e->getMessage(), array(
                'form_id' => $form_id,
                'user_id' => get_current_user_id(),
                'trace' => $e->getTraceAsString()
            ));
            
            wp_send_json_error($error_message);
        }
    }

    /**
     * AJAX handler for deleting forms
     */
    public function ajax_delete_form() {
        check_ajax_referer( 'connect2form_delete_form', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

      //  $form_id = isset( $_POST['form_id'] ) ? (int) wp_unslash( $_POST['form_id'] ) : 0;
      $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        if ( ! $form_id ) {
            wp_send_json_error( 'Invalid form ID' );
        }

        // Filter form ID before deletion.
        $form_id = apply_filters( 'connect2form_form_id_before_delete', $form_id );

        // Action hook before deleting form.
        do_action( 'connect2form_before_delete_form', $form_id );

        // Get form data before deletion for hooks using service class.
        $form_data = null;
        $result = false;

        if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $form_data = $service_manager->forms()->get_form( $form_id );
            $result = $service_manager->forms()->delete_form( $form_id );
        } else {
            // Fallback to direct database call if service not available.
            global $wpdb;
            $table_name = $wpdb->prefix . 'connect2form_forms';

            // Get form data before deletion for hooks.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fallback form query for deletion hook with validated table identifier; service layer preferred but this is a fallback
            $form_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE id = %d", $form_id ) );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- form deletion operation; no caching needed for form deletion
            $result = $wpdb->delete( $table_name, array( 'id' => $form_id ) );
        }

        if ( $result === false || $result === 0 ) {
            $error_message = apply_filters( 'connect2form_delete_form_error', 'Failed to delete form', $form_id );
            wp_send_json_error( $error_message );
        }

        // Action hook after deleting form.
        do_action( 'connect2form_after_delete_form', $form_id, $form_data );

        $response_data = array(
            'message' => __('Form deleted successfully.', 'connect2form-builder'),
            'form_id' => $form_id,
        );

        // Filter response data.
        $response_data = apply_filters( 'connect2form_delete_form_response', $response_data, $form_id );

        wp_send_json_success( $response_data );
    }

    /**
     * AJAX handler for toggling form status
     */
    public function ajax_toggle_status() {
        check_ajax_referer( 'connect2form_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

        if ( ! $form_id || ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
            wp_send_json_error( 'Invalid parameters' );
        }

        // Toggle form status logic would go here.
        // For now, just return success.
        wp_send_json_success( array(
            'message' => 'Status updated successfully',
            'form_id' => $form_id,
            'status' => $status,
        ) );
    }

    /**
     * AJAX handler for deleting submissions
     */
    public function ajax_delete_submission() {
        check_ajax_referer( 'connect2form_delete_submission', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;

        if ( ! $submission_id ) {
            wp_send_json_error( 'Invalid submission ID' );
        }

        // Action hook before deleting submission.
        do_action( 'connect2form_before_delete_submission', $submission_id );

        global $wpdb;
        $table_name = $wpdb->prefix . 'connect2form_submissions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin deletion operation; no caching applicable
        $result = $wpdb->delete(
            $table_name,
            array( 'id' => $submission_id ),
            array( '%d' )
        );

        if ( $result === false ) {
            wp_send_json_error( 'Failed to delete submission' );
        }

        // Action hook after deleting submission.
        do_action( 'connect2form_after_delete_submission', $submission_id );

        $response_data = array(
            'message' => 'Submission deleted successfully',
            'submission_id' => $submission_id,
        );

        wp_send_json_success( $response_data );
    }

    /**
     * AJAX handler for bulk deleting forms
     */
    public function ajax_bulk_delete_forms() {
        check_ajax_referer( 'connect2form_bulk_delete_forms', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $form_ids = isset( $_POST['form_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['form_ids'] ) ) : array();

        if ( empty( $form_ids ) ) {
            wp_send_json_error( 'No forms selected' );
        }

        $deleted_count = 0;
        $errors = array();

        foreach ( $form_ids as $form_id ) {
            // Filter form ID before deletion.
            $form_id = apply_filters( 'connect2form_form_id_before_delete', $form_id );

            // Action hook before deleting form.
            do_action( 'connect2form_before_delete_form', $form_id );

            // Get form data before deletion for hooks using service class.
            $form_data = null;
            $result = false;

            if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
                $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
                $form_data = $service_manager->forms()->get_form( $form_id );
                $result = $service_manager->forms()->delete_form( $form_id );
            } else {
                // Fallback to direct database call if service not available.
                global $wpdb;
                $table_name = $wpdb->prefix . 'connect2form_forms';

                // Get form data before deletion for hooks.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fallback form query for deletion hook; service layer preferred but this is a fallback; table name is built from trusted prefix
                $form_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE id = %d", $form_id ) );

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk form deletion operation; no caching needed for form deletion
                $result = $wpdb->delete( $table_name, array( 'id' => $form_id ), array( '%d' ) );
            }

            if ( $result !== false ) {
                $deleted_count++;
                // Action hook after deleting form.
                do_action( 'connect2form_after_delete_form', $form_id, $form_data );
            } else {
                $errors[] = 'Failed to delete form ID: ' . $form_id;
            }
        }

        if ( $deleted_count > 0 ) {
            $message = sprintf(
                /* translators: %d: Number of forms deleted */
                _n('%d form deleted successfully.', '%d forms deleted successfully.', $deleted_count, 'connect2form-builder'),
                $deleted_count
            );

            if ( ! empty( $errors ) ) {
                $message .= ' Some forms could not be deleted: ' . implode( ', ', $errors );
            }

            wp_send_json_success( array( 'message' => $message, 'deleted_count' => $deleted_count ) );
        } else {
            $error_message = 'No forms were deleted. ' . implode( ', ', $errors );
            wp_send_json_error( $error_message );
        }
    }

    /**
     * Delete a form (for direct URL deletion)
     *
     * @param int $form_id The form ID to delete.
     * @return bool True on success, false on failure.
     */
    public function delete_form( $form_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        // Filter form ID before deletion.
        $form_id = apply_filters( 'connect2form_form_id_before_delete', $form_id );

        // Action hook before deleting form.
        do_action( 'connect2form_before_delete_form', $form_id );

        $form_data = null;
        $result = false;

        if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $form_data = $service_manager->forms()->get_form( $form_id );
            $result = $service_manager->forms()->delete_form( $form_id );
        } else {
            // Fallback to direct database call if service not available.
            global $wpdb;
            $table_name = $wpdb->prefix . 'connect2form_forms';

            // Get form data before deletion for hooks.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fallback form query for deletion hook with validated table identifier; service layer preferred but this is a fallback
            $form_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE id = %d", $form_id ) );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- form deletion operation; no caching needed for form deletion
            $result = $wpdb->delete( $table_name, array( 'id' => $form_id ) );
        }

        if ( $result === false ) {
            return false;
        }

        // Action hook after deleting form.
        do_action( 'connect2form_after_delete_form', $form_id, $form_data );

        return true;
    }

    /**
     * AJAX handler for duplicating forms
     */
    public function ajax_duplicate_form() {
        check_ajax_referer( 'connect2form_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        if ( ! $form_id ) {
            wp_send_json_error( 'Invalid form ID' );
        }

        // Filter form ID before duplication.
        $form_id = apply_filters( 'connect2form_form_id_before_duplicate', $form_id );

        $form = null;

        if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $form = $service_manager->forms()->get_form( $form_id );
        } else {
            // Fallback to direct database call if service not available.
            global $wpdb;
            $table_name = $wpdb->prefix . 'connect2form_forms';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fallback form query with validated table identifier; service layer preferred but this is a fallback
            $form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE id = %d", $form_id ) );
        }

        if ( ! $form ) {
            wp_send_json_error( 'Form not found' );
        }

        // Filter original form data.
        $form = apply_filters( 'connect2form_form_before_duplicate', $form );

        $data = array(
            'form_title' => $form->form_title . ' (Copy)',
            'fields' => $form->fields,
            'settings' => $form->settings,
            'status' => 'active',
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );

        // Filter duplicate data.
        $data = apply_filters( 'connect2form_duplicate_form_data', $data, $form );

        // Action hook before duplicating form.
        do_action( 'connect2form_before_duplicate_form', $data, $form );

        $result = false;
        $new_form_id = 0;

        if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();

            $duplicate_data = array(
                'form_title' => $form->form_title . ' (Copy)',
                'fields' => $form->fields,
                'settings' => $form->settings,
                'status' => 'active',
            );

            $new_form_id = $service_manager->forms()->create_form( $duplicate_data );

            if ( $new_form_id === false ) {
                $error_message = apply_filters( 'connect2form_duplicate_form_error', 'Failed to duplicate form', $form_id );
                wp_send_json_error( $error_message );
            }

            $result = true;
        } else {
            // Fallback to direct database call if service not available.
            global $wpdb;
            $table_name = $wpdb->prefix . 'connect2form_forms';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- form duplication operation; no caching needed for form creation
            $result = $wpdb->insert( $table_name, $data );

            if ( $result === false ) {
                $error_message = apply_filters( 'connect2form_duplicate_form_error', 'Failed to duplicate form: ' . $wpdb->last_error, $form_id );
                wp_send_json_error( $error_message );
            }

            $new_form_id = $wpdb->insert_id;
        }

        // Action hook after duplicating form.
        do_action( 'connect2form_after_duplicate_form', $new_form_id, $data, $form );

        $response_data = array(
            'message' => 'Form duplicated successfully',
            'form_id' => $new_form_id,
            'redirect_url' => esc_url_raw( admin_url( 'admin.php?page=connect2form&action=edit&id=' . $new_form_id ) ),
        );

        // Filter response data.
        $response_data = apply_filters( 'connect2form_duplicate_form_response', $response_data, $new_form_id, $form );

        wp_send_json_success( $response_data );
    }

    /**
     * AJAX handler for getting form data
     */
    public function ajax_get_form() {
        check_ajax_referer( 'connect2form_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;

        if ( ! $form_id ) {
            wp_send_json_error( 'Invalid form ID' );
        }

        $form = $this->get_form( $form_id );

        if ( ! $form ) {
            wp_send_json_error( 'Form not found' );
        }

        wp_send_json_success( $form );
    }

    /**
     * AJAX handler for form preview
     */
    public function ajax_preview_form() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'connect2form_nonce', 'nonce', false ) ) {
            wp_die( 'Invalid security token' );
        }

        // Check permissions.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }

        $form_id = isset( $_GET['form_id'] ) ? intval( $_GET['form_id'] ) : 0;

        if ( ! $form_id ) {
            wp_die( 'Invalid form ID' );
        }

        // Redirect to preview page.
        $preview_url = esc_url_raw( admin_url( 'admin.php?page=connect2form&action=preview&id=' . $form_id ) );
        wp_redirect( $preview_url );
        exit;
    }

    /**
     * Render form preview page
     */
    public function render_form_preview($form_id) {
        // Use service class instead of direct database call
        $form = null;
        if (class_exists('\Connect2Form\Integrations\Core\Services\ServiceManager')) {
            $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
            $form = $service_manager->forms()->get_form($form_id);
        } else {
            // Fallback to direct database call if service not available
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form preview query; service layer preferred but this is a fallback
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
                $form_id
            ));
        }

        if (!$form) {
            wp_die(esc_html__('Form not found', 'connect2form-builder'));
        }

        // Initialize form renderer
        $form_renderer = new Connect2Form_Form_Renderer();
        $form_html = $form_renderer->render_form(array('id' => $form_id));

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($form->form_title); ?> - <?php bloginfo('name'); ?></title>
            <?php 
            // Properly enqueue WordPress core assets
            wp_enqueue_style('dashicons');
            wp_enqueue_style('buttons');
            wp_enqueue_script('jquery');
            wp_head(); 
            ?>
            <style>
                * {
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background: #f5f5f5;
                    min-height: 100vh;
                }
                .preview-header {
                    background: #fff;
                    padding: 30px 20px;
                    margin: 0;
                    border-bottom: 1px solid #e1e1e1;
                    text-align: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .preview-header h1 {
                    margin: 0 0 10px 0;
                    color: #2271b1;
                    font-size: 28px;
                    font-weight: 600;
                }
                .preview-header p {
                    margin: 0;
                    color: #666;
                    font-size: 16px;
                }
                .form-preview-container {
                    max-width: 800px;
                    margin: 40px auto;
                    background: #fff;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                .preview-actions {
                    text-align: center;
                    margin: 40px auto;
                    padding: 30px;
                    background: #fff;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    max-width: 800px;
                }
                .preview-actions .button {
                    margin: 0 8px;
                    padding: 12px 24px;
                    font-size: 14px;
                    font-weight: 500;
                    border-radius: 6px;
                    text-decoration: none;
                    display: inline-block;
                    transition: all 0.3s ease;
                }
                .preview-actions .button-primary {
                    background: #2271b1;
                    color: #fff;
                    border: 1px solid #2271b1;
                }
                .preview-actions .button-primary:hover {
                    background: #135e96;
                    border-color: #135e96;
                }
                .preview-actions .button:not(.button-primary) {
                    background: #f6f7f7;
                    color: #2271b1;
                    border: 1px solid #2271b1;
                }
                .preview-actions .button:not(.button-primary):hover {
                    background: #f0f0f1;
                    color: #135e96;
                    border-color: #135e96;
                }
                
                /* Form styling for preview */
                .connect2form-form-wrapper {
                    max-width: 100%;
                }
                .connect2form-form {
                    display: block;
                }
                .connect2form-field {
                    margin-bottom: 20px;
                }
                .connect2form-field label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 600;
                    color: #333;
                }
                .connect2form-field input[type="text"],
                .connect2form-field input[type="email"],
                .connect2form-field input[type="number"],
                .connect2form-field input[type="date"],
                .connect2form-field textarea,
                .connect2form-field select {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                    box-sizing: border-box;
                }
                .connect2form-field input[type="file"] {
                    padding: 10px 0;
                }
                .connect2form-field .connect2form-radio-label,
                .connect2form-field .connect2form-checkbox-label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: normal;
                }
                .connect2form-field .connect2form-radio-label input,
                .connect2form-field .connect2form-checkbox-label input {
                    margin-right: 8px;
                }
                .connect2form-submit-wrapper {
                    margin-top: 20px;
                }
                .connect2form-submit {
                    background: #2271b1;
                    color: #fff;
                    padding: 12px 24px;
                    border: none;
                    border-radius: 4px;
                    font-size: 16px;
                    cursor: pointer;
                    transition: background-color 0.3s ease;
                }
                .connect2form-submit:hover {
                    background: #135e96;
                }
                .connect2form-message {
                    margin-top: 15px;
                    padding: 10px;
                    border-radius: 4px;
                    display: none;
                }
                .connect2form-message.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                .connect2form-message.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                .required {
                    color: #dc3545;
                }
                .connect2form-field .description {
                    font-size: 12px;
                    color: #666;
                    margin-top: 5px;
                }
            </style>
        </head>
        <body>
            <div class="preview-header">
                <h1><?php echo esc_html($form->form_title); ?></h1>
                <p><?php echo esc_html__('Form Preview', 'connect2form-builder'); ?></p>
            </div>

            <div class="form-preview-container">
                <?php echo wp_kses_post($form_html); ?>
            </div>

            <div class="preview-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form&action=edit&id=' . $form_id)); ?>" class="button button-primary">
                    <?php esc_html_e('Edit Form', 'connect2form-builder'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=connect2form')); ?>" class="button">
                    <?php esc_html_e('Back to Forms', 'connect2form-builder'); ?>
                </a>
                <?php // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- request URI is escaped below ?>
                <a href="<?php echo esc_url( add_query_arg( 'preview', '1', esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ) ); ?>" class="button">
                    <?php esc_html_e('Preview', 'connect2form-builder'); ?>
                </a>
                <button type="button" class="button" onclick="window.close();">
                    <?php esc_html_e('Close Preview', 'connect2form-builder'); ?>
                </button>
            </div>

            <script>
            // Form functionality is handled by properly enqueued scripts
            // No inline JavaScript needed
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * AJAX handler for testing email functionality
     */
    public function ajax_test_email() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'connect2form_nonce', 'nonce', false ) ) {
            wp_die( 'Invalid security token' );
        }

        // Check permissions.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }

        // Get submission handler instance.
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-connect2form-submission-handler.php';
        $submission_handler = new Connect2Form_Submission_Handler();

        // Get email configuration information.
        $email_info = $submission_handler->get_email_debug_info();

        // Test email functionality.
        $test_result = $submission_handler->test_email_functionality();

        if ( $test_result ) {
            $response = array(
                'success' => true,
                'message' => 'Test email sent successfully to ' . $email_info['admin_email'],
                'email_info' => $email_info,
            );
            wp_send_json_success( $response );
        } else {
            $response = array(
                'success' => false,
                'message' => 'Failed to send test email. Please check your WordPress email configuration.',
                'email_info' => $email_info,
                'suggestions' => array(
                    'Check if your hosting provider allows outgoing emails',
                    'Consider installing an SMTP plugin like WP Mail SMTP',
                    'Verify that the admin email address is correct',
                    'Check your server\'s error logs for more details',
                ),
            );
            wp_send_json_error( $response );
        }
    }

    /**
     * AJAX handler for testing reCAPTCHA functionality
     */
    public function ajax_test_recaptcha() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'connect2form_test', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid security token' );
        }

        // Check permissions.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        // Get reCAPTCHA response.
        $recaptcha_response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';

        if ( empty( $recaptcha_response ) ) {
            wp_send_json_error( 'No reCAPTCHA response received' );
        }

        // Get reCAPTCHA settings.
        $recaptcha_settings = get_option( 'connect2form_recaptcha_settings', array() );
        $secret_key = isset( $recaptcha_settings['secret_key'] ) ? $recaptcha_settings['secret_key'] : '';

        if ( empty( $secret_key ) ) {
            wp_send_json_error( 'reCAPTCHA secret key not configured' );
        }

        // Test reCAPTCHA verification.
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-connect2form-submission-handler.php';
        $submission_handler = new Connect2Form_Submission_Handler();

        // Use reflection to access the private method.
        $reflection = new ReflectionClass( $submission_handler );
        $verify_method = $reflection->getMethod( 'verify_captcha' );
        $verify_method->setAccessible( true );

        $verification_result = $verify_method->invoke( $submission_handler, $recaptcha_response, $secret_key );

        if ( $verification_result ) {
            wp_send_json_success( array(
                'message' => 'reCAPTCHA verification successful!',
                'response_length' => strlen( $recaptcha_response ),
            ) );
        } else {
            wp_send_json_error( 'reCAPTCHA verification failed. Please check your configuration.' );
        }
    }


}
