<?php
/**
 * Integrations Auto-Loader
 *
 * Automatically discovers and loads all integration classes from subdirectories
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Integrations_Loader {

    /**
     * List of discovered integrations
     */
    private static $integrations = array();

    /**
     * Integration instances cache
     */
    private static $instances = array();

    /**
     * Initialize the loader
     */
    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'load_integrations'), 10);
        add_filter('mavlers_cf_available_integrations', array(__CLASS__, 'register_integrations'));
    }

    /**
     * Load all integration files
     */
    public static function load_integrations() {
        $integrations_dir = dirname(__FILE__);
        $integration_folders = glob($integrations_dir . '/*', GLOB_ONLYDIR);

        foreach ($integration_folders as $folder) {
            $integration_name = basename($folder);
            $class_file = $folder . '/class-' . str_replace('_', '-', $integration_name) . '-integration.php';

            if (file_exists($class_file)) {
                require_once $class_file;
                
                $class_name = 'Mavlers_CF_' . ucfirst($integration_name) . '_Integration';
                if (class_exists($class_name)) {
                    self::$integrations[$integration_name] = $class_name;
                    error_log('✅ Mavlers CF: Loaded integration class: ' . $class_name);
                }
            }
        }

        // Register discovered integrations with the registry
        add_action('mavlers_cf_register_integrations', array(__CLASS__, 'register_with_registry'), 10, 1);

        // Fire action for other plugins to register integrations
        do_action('mavlers_cf_integrations_loaded', self::$integrations);
    }

    /**
     * Register discovered integrations with the addon registry
     */
    public static function register_with_registry($registry) {
        error_log('🔧 Mavlers CF: Registering ' . count(self::$integrations) . ' discovered integrations');
        
        foreach (self::$integrations as $integration_id => $class_name) {
            if (class_exists($class_name)) {
                try {
                    $integration_instance = new $class_name();
                    $result = $registry->register_integration($integration_instance);
                    
                    if (is_wp_error($result)) {
                        error_log('❌ Mavlers CF: Failed to register ' . $integration_id . ': ' . $result->get_error_message());
                    } else {
                        error_log('✅ Mavlers CF: Registered integration: ' . $integration_id);
                    }
                } catch (Exception $e) {
                    error_log('❌ Mavlers CF: Error instantiating ' . $class_name . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Register integrations with the registry
     */
    public static function register_integrations($integrations) {
        foreach (self::$integrations as $integration_id => $class_name) {
            $integrations[$integration_id] = $class_name;
        }

        return $integrations;
    }

    /**
     * Get an integration instance
     */
    public static function get_integration($integration_id) {
        if (!isset(self::$instances[$integration_id])) {
            if (isset(self::$integrations[$integration_id])) {
                $class_name = self::$integrations[$integration_id];
                if (class_exists($class_name)) {
                    self::$instances[$integration_id] = new $class_name();
                }
            }
        }

        return self::$instances[$integration_id] ?? null;
    }

    /**
     * Get all loaded integrations
     */
    public static function get_loaded_integrations() {
        return self::$integrations;
    }

    /**
     * Check if integration is loaded
     */
    public static function is_integration_loaded($integration_id) {
        return isset(self::$integrations[$integration_id]);
    }

    /**
     * Get integration information
     */
    public static function get_integration_info($integration_id) {
        $integration = self::get_integration($integration_id);
        
        if (!$integration) {
            return null;
        }

        return array(
            'id' => $integration_id,
            'name' => $integration->get_integration_name(),
            'description' => $integration->get_integration_description(),
            'version' => $integration->get_integration_version(),
            'icon' => $integration->get_integration_icon(),
            'color' => $integration->get_integration_color(),
            'class' => get_class($integration)
        );
    }

    /**
     * Get all integrations info
     */
    public static function get_all_integrations_info() {
        $info = array();
        
        foreach (self::$integrations as $integration_id => $class_name) {
            $integration_info = self::get_integration_info($integration_id);
            if ($integration_info) {
                $info[$integration_id] = $integration_info;
            }
        }

        return $info;
    }
}

// Initialize the loader
Mavlers_CF_Integrations_Loader::init(); 