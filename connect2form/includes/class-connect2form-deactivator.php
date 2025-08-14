<?php
/**
 * Connect2Form Deactivator Class
 *
 * Handles plugin deactivation tasks
 *
 * @package Connect2Form
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Connect2Form_Deactivator {
    /**
     * Plugin deactivation hook
     * 
     * Performs cleanup tasks when the plugin is deactivated
     */
    public static function deactivate() {
        // Clean up any plugin-specific data if needed
        // For now, we'll keep the tables as they might be needed if the plugin is reactivated
    }
}
