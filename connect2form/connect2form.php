<?php
/**
 * Plugin Name: Connect2Form – Advanced Contact Form Builder with Marketing Tool Integrations (Mailchimp, HubSpot, and More)
 * Plugin URI: https://connect2form.com
 * Description: A comprehensive contact form builder for WordPress with advanced features including email notifications, file uploads, reCAPTCHA integration, and third-party service integrations.
 * Version: 2.0.0
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: pravinsonawane71
 * Author URI: https://connect2form.com
 * Text Domain: connect2form
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * @package connect2form
 * @version 2.0.0
 * @author pravinsonawane71
 * @license GPL-2.0+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent direct file access
if (!defined('WPINC')) {
    die;
}

// Define plugin constants first
define('CONNECT2FORM_VERSION', '2.0.0');
define('CONNECT2FORM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CONNECT2FORM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CONNECT2FORM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CONNECT2FORM_PLUGIN_FILE', __FILE__);
define('CONNECT2FORM_MIN_WP_VERSION', '5.0');
define('CONNECT2FORM_MIN_PHP_VERSION', '7.4');
define('CONNECT2FORM_START_TIME', microtime(true));



// Check WordPress version
if ( version_compare( get_bloginfo( 'version' ), CONNECT2FORM_MIN_WP_VERSION, '<' ) ) {
    add_action( 'admin_notices', function() {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice with proper escaping
        echo '<div class="notice notice-error"><p>' .
            esc_html( sprintf( /* translators: %s: Minimum WordPress version */ __( 'Connect2Form requires WordPress %s or higher. Please upgrade WordPress.', 'connect2form' ), CONNECT2FORM_MIN_WP_VERSION ) ) .
            '</p></div>';
    } );
    return;
}

// Check PHP version
if ( version_compare( PHP_VERSION, CONNECT2FORM_MIN_PHP_VERSION, '<' ) ) {
    add_action( 'admin_notices', function() {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice with proper escaping
        echo '<div class="notice notice-error"><p>' .
            esc_html( sprintf( /* translators: %s: Minimum PHP version */ __( 'Connect2Form requires PHP %s or higher. Please contact your hosting provider.', 'connect2form' ), CONNECT2FORM_MIN_PHP_VERSION ) ) .
            '</p></div>';
    } );
    return;
}

// Define additional constants
if ( ! defined( 'CONNECT2FORM_UPLOADS_DIR' ) ) {
    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Plugin constant definition
    define( 'CONNECT2FORM_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/connect2form-uploads/' );
}
if ( ! defined( 'CONNECT2FORM_UPLOADS_URL' ) ) {
    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Plugin constant definition
    define( 'CONNECT2FORM_UPLOADS_URL', wp_upload_dir()['baseurl'] . '/connect2form-uploads/' );
}

// Integration Manager addon constants
if ( file_exists( CONNECT2FORM_PLUGIN_DIR . 'addons/integrations-manager/integrations-manager.php' ) ) {
    if ( ! defined( 'CONNECT2FORM_INTEGRATIONS_DIR' ) ) {
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Plugin constant definition
        define( 'CONNECT2FORM_INTEGRATIONS_DIR', CONNECT2FORM_PLUGIN_DIR . 'addons/integrations-manager/' );
    }
    if ( ! defined( 'CONNECT2FORM_INTEGRATIONS_URL' ) ) {
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Plugin constant definition
        define( 'CONNECT2FORM_INTEGRATIONS_URL', CONNECT2FORM_PLUGIN_URL . 'addons/integrations-manager/' );
    }
}

/**
 * Activation hook
 */
function activate_connect2form() {
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-activator.php';
    Connect2Form_Activator::activate();
}

/**
 * Deactivation hook
 */
function deactivate_connect2form() {
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-deactivator.php';
    Connect2Form_Deactivator::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook( __FILE__, 'activate_connect2form' );
register_deactivation_hook( __FILE__, 'deactivate_connect2form' );

/**
 * Initialize the plugin
 */
function run_connect2form() {
    // Check if WordPress and PHP requirements are met
    if ( version_compare( get_bloginfo( 'version' ), CONNECT2FORM_MIN_WP_VERSION, '<' ) ||
        version_compare( PHP_VERSION, CONNECT2FORM_MIN_PHP_VERSION, '<' ) ) {
        return;
    }


    // Load helpers and plugin classes
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/c2f-wpdb-helpers.php';
    // Include security and compliance classes first
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-security.php';
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-performance.php';
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-accessibility.php';
    
    // Include core classes
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-admin.php';
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-form-builder.php';
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-form-renderer.php';
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-submission-handler.php';
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-settings.php';
    require_once CONNECT2FORM_PLUGIN_DIR . 'includes/class-connect2form-integrations.php';

    // Initialize admin functionality
    if ( is_admin() ) {
        $admin = new Connect2Form_Admin( 'connect2form', CONNECT2FORM_VERSION );
        
        // Note: Tab functionality is now handled by form-builder.js
        $form_builder = new Connect2Form_Form_Builder();
        $settings = new Connect2Form_Settings();
    }

    // Initialize frontend functionality
    $form_renderer = new Connect2Form_Form_Renderer();
    $submission_handler = new Connect2Form_Submission_Handler();
    $integrations = new Connect2Form_Integrations();

    // Load integrations manager addon if available
    if ( defined( 'CONNECT2FORM_INTEGRATIONS_DIR' ) && 
        file_exists( CONNECT2FORM_INTEGRATIONS_DIR . 'integrations-manager.php' ) ) {
        // Only require once to prevent duplicate loading
        if ( ! class_exists( 'Connect2Form\\Integrations\\Core\\Plugin' ) ) {
            require_once CONNECT2FORM_INTEGRATIONS_DIR . 'integrations-manager.php';
        }
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', 'run_connect2form', 10 );
