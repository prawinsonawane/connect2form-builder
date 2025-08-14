<?php
/**
 * Addon Registry Class
 * 
 * Manages registration and retrieval of integration addons
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Addon_Registry {
    
    private $registered_integrations = array();
    
    public function __construct() {
        add_action('mavlers_cf_load_integrations', array($this, 'discover_integrations'), 5);
    }
    
    /**
     * Register an integration addon
     */
    public function register_integration($integration) {
        if (!$integration instanceof Mavlers_CF_Base_Integration) {
            return new WP_Error('invalid_integration', 'Integration must extend Mavlers_CF_Base_Integration');
        }
        
        $integration_id = $integration->get_integration_id();
        $this->registered_integrations[$integration_id] = $integration;
        
        // Store integration info in database
        $this->store_integration_info($integration);
        
        do_action('mavlers_cf_integration_registered', $integration_id, $integration);
        
        return true;
    }
    
    /**
     * Get a specific integration by ID
     */
    public function get_integration($integration_id) {
        return isset($this->registered_integrations[$integration_id]) 
            ? $this->registered_integrations[$integration_id] 
            : null;
    }
    
    /**
     * Get all available integrations
     */
    public function get_available_integrations() {
        return $this->registered_integrations;
    }
    
    /**
     * Get active integrations only
     */
    public function get_active_integrations() {
        global $wpdb;
        
        $active_ids = $wpdb->get_col(
            "SELECT integration_id FROM {$wpdb->prefix}mavlers_cf_integrations 
             WHERE integration_status = 'active'"
        );
        
        $active_integrations = array();
        foreach ($active_ids as $id) {
            if (isset($this->registered_integrations[$id])) {
                $active_integrations[$id] = $this->registered_integrations[$id];
            }
        }
        
        return $active_integrations;
    }
    
    /**
     * Store integration information in database
     */
    private function store_integration_info($integration) {
        global $wpdb;
        
        $integration_id = $integration->get_integration_id();
        
        // Check if already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mavlers_cf_integrations WHERE integration_id = %s",
            $integration_id
        ));
        
        $data = array(
            'integration_id' => $integration_id,
            'integration_name' => $integration->get_integration_name(),
            'integration_version' => $integration->get_integration_version(),
            'integration_config' => json_encode(array(
                'description' => $integration->get_integration_description(),
                'icon' => $integration->get_integration_icon(),
                'color' => $integration->get_integration_color(),
                'auth_fields' => $integration->get_auth_fields(),
                'available_actions' => $integration->get_available_actions(),
                'supports_oauth' => $integration->supports_oauth()
            )),
            'updated_at' => current_time('mysql')
        );
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $wpdb->prefix . 'mavlers_cf_integrations',
                $data,
                array('integration_id' => $integration_id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%s')
            );
        } else {
            // Insert new record
            $data['integration_status'] = 'active';
            $data['created_at'] = current_time('mysql');
            
            $wpdb->insert(
                $wpdb->prefix . 'mavlers_cf_integrations',
                $data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Discover and auto-register integrations
     */
    public function discover_integrations() {
        // This method is called early to allow integrations to register themselves
        do_action('mavlers_cf_register_integrations', $this);
    }
    
    /**
     * Get integration configuration from database
     */
    public function get_integration_config($integration_id) {
        global $wpdb;
        
        $config = $wpdb->get_var($wpdb->prepare(
            "SELECT integration_config FROM {$wpdb->prefix}mavlers_cf_integrations WHERE integration_id = %s",
            $integration_id
        ));
        
        return $config ? json_decode($config, true) : null;
    }
    
    /**
     * Get integration auth data from database
     */
    public function get_integration_auth_data($integration_id) {
        global $wpdb;
        
        $auth_data = $wpdb->get_var($wpdb->prepare(
            "SELECT auth_data FROM {$wpdb->prefix}mavlers_cf_integrations WHERE integration_id = %s",
            $integration_id
        ));
        
        return $auth_data ? json_decode($auth_data, true) : null;
    }
    
    /**
     * Save integration auth data
     */
    public function save_integration_auth_data($integration_id, $auth_data) {
        global $wpdb;
        
        // Encrypt sensitive data before storing
        $encrypted_data = Mavlers_CF_Security_Manager::encrypt($auth_data);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'mavlers_cf_integrations',
            array(
                'auth_data' => json_encode($encrypted_data),
                'updated_at' => current_time('mysql')
            ),
            array('integration_id' => $integration_id),
            array('%s', '%s'),
            array('%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Update integration status
     */
    public function update_integration_status($integration_id, $status) {
        global $wpdb;
        
        $valid_statuses = array('active', 'inactive', 'error');
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'mavlers_cf_integrations',
            array(
                'integration_status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('integration_id' => $integration_id),
            array('%s', '%s'),
            array('%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Get integration statistics
     */
    public function get_integration_stats($integration_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_submissions,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed,
                AVG(execution_time) as avg_execution_time
             FROM {$wpdb->prefix}mavlers_cf_integration_logs 
             WHERE integration_id = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $integration_id
        ), ARRAY_A);
    }
    
    /**
     * Validate integration before registration
     */
    private function validate_integration($integration) {
        $errors = array();
        
        // Check required methods
        $required_methods = array(
            'get_integration_id',
            'get_integration_name',
            'get_integration_description',
            'get_integration_version',
            'get_auth_fields',
            'get_available_actions',
            'handle_submission'
        );
        
        foreach ($required_methods as $method) {
            if (!method_exists($integration, $method)) {
                $errors[] = "Missing required method: {$method}";
            }
        }
        
        // Validate integration ID
        $integration_id = $integration->get_integration_id();
        if (empty($integration_id) || !preg_match('/^[a-zA-Z0-9_-]+$/', $integration_id)) {
            $errors[] = 'Invalid integration ID. Must contain only letters, numbers, hyphens, and underscores.';
        }
        
        // Check for duplicate IDs
        if (isset($this->registered_integrations[$integration_id])) {
            $errors[] = "Integration ID '{$integration_id}' is already registered.";
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Unregister an integration
     */
    public function unregister_integration($integration_id) {
        if (isset($this->registered_integrations[$integration_id])) {
            unset($this->registered_integrations[$integration_id]);
            
            // Update status in database
            $this->update_integration_status($integration_id, 'inactive');
            
            do_action('mavlers_cf_integration_unregistered', $integration_id);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get integrations by category
     */
    public function get_integrations_by_category($category = null) {
        if (!$category) {
            return $this->registered_integrations;
        }
        
        $filtered = array();
        foreach ($this->registered_integrations as $id => $integration) {
            $config = $this->get_integration_config($id);
            if ($config && isset($config['category']) && $config['category'] === $category) {
                $filtered[$id] = $integration;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Search integrations by name or description
     */
    public function search_integrations($search_term) {
        $search_term = strtolower($search_term);
        $results = array();
        
        foreach ($this->registered_integrations as $id => $integration) {
            $name = strtolower($integration->get_integration_name());
            $description = strtolower($integration->get_integration_description());
            
            if (strpos($name, $search_term) !== false || strpos($description, $search_term) !== false) {
                $results[$id] = $integration;
            }
        }
        
        return $results;
    }
} 