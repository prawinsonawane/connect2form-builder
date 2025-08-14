<?php
/**
 * Integrations Manager - Clean Architecture
 *
 * @package Connect2Form_Integrations
 * @version 2.0.0
 * @author Connect2Form
 * @description Modern integration system with proper separation of concerns
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent duplicate loading
if (class_exists('Connect2Form\\Integrations\\Core\\Plugin')) {
    return;
}

// Plugin constants
if (!defined('CONNECT2FORM_INTEGRATIONS_VERSION')) {
    define('CONNECT2FORM_INTEGRATIONS_VERSION', '2.0.0');
}
if (!defined('CONNECT2FORM_INTEGRATIONS_DIR')) {
    define('CONNECT2FORM_INTEGRATIONS_DIR', plugin_dir_path(__FILE__));
}
if (!defined('CONNECT2FORM_INTEGRATIONS_URL')) {
    define('CONNECT2FORM_INTEGRATIONS_URL', plugin_dir_url(__FILE__));
}
if (!defined('CONNECT2FORM_INTEGRATIONS_BASENAME')) {
    define('CONNECT2FORM_INTEGRATIONS_BASENAME', plugin_basename(__FILE__));
}
if (!defined('CONNECT2FORM_INTEGRATIONS_FILE')) {
    define('CONNECT2FORM_INTEGRATIONS_FILE', __FILE__);
}

// Check if main plugin is active
if (!class_exists('Connect2Form_Admin')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'Integrations Manager requires Connect2Form plugin to be active.', 'connect2form' );
        echo '</p></div>';
    });
    return;
}

// Autoloader for modern PSR-4 style loading
spl_autoload_register(function ($class) {
    $prefix = 'Connect2Form\\Integrations\\';
    $base_dir = CONNECT2FORM_INTEGRATIONS_DIR . 'src/Integrations/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Autoloader for Core classes
spl_autoload_register(function ($class) {
    $prefix = 'Connect2Form\\Integrations\\Core\\';
    $base_dir = CONNECT2FORM_INTEGRATIONS_DIR . 'src/Core/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Autoloader for Admin classes
spl_autoload_register(function ($class) {
    $prefix = 'Connect2Form\\Integrations\\Admin\\';
    $base_dir = CONNECT2FORM_INTEGRATIONS_DIR . 'src/Admin/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('Connect2Form\\Integrations\\Core\\Plugin')) {
        Connect2Form\Integrations\Core\Plugin::getInstance();
    }
}, 20);

// Enable debug mode for Mailchimp testing if URL parameter is present (read-only, admin only)
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only debug flag for administrators; no data is persisted
/*if (isset($_GET['mailchimp_debug']) && current_user_can('manage_options')) {
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    if (!defined('WP_DEBUG_LOG')) {
        define('WP_DEBUG_LOG', true);
    }
}*/

// Activation and deactivation hooks
register_activation_hook(__FILE__, function() {
    if (class_exists('Connect2Form\\Integrations\\Core\\Plugin')) {
        Connect2Form\Integrations\Core\Plugin::activate();
    }
});

register_deactivation_hook(__FILE__, function() {
    if (class_exists('Connect2Form\\Integrations\\Core\\Plugin')) {
        Connect2Form\Integrations\Core\Plugin::deactivate();
    }
}); 
