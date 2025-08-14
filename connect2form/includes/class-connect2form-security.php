<?php
/**
 * Connect2Form Security Manager
 *
 * Handles security validation, sanitization, and WordPress standards compliance
 *
 * @package Connect2Form
 * @since 2.0.0
 */

if (!defined('WPINC')) {
    die;
}

class Connect2Form_Security {
    
    /**
     * Initialize security hooks and filters
     */
    public function __construct() {
        // Add security headers
        add_action('wp_headers', array($this, 'add_security_headers'));
        
        // Sanitize all admin inputs
        add_filter('connect2form_sanitize_input', array($this, 'sanitize_input'), 10, 2);
        
        // Validate nonces for all AJAX requests
        add_action('wp_ajax_connect2form_validate_nonce', array($this, 'validate_nonce'));
        
        // Rate limiting for form submissions
        add_filter('connect2form_rate_limit_check', array($this, 'enhanced_rate_limiting'), 10, 2);
        
        // File upload security
        add_filter('connect2form_allowed_file_types', array($this, 'restrict_file_types'));
        add_filter('connect2form_max_file_size', array($this, 'enforce_file_size_limits'));
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers($headers) {
        // Only add headers on our plugin pages
        if (!$this->is_plugin_page()) {
            return $headers;
        }
        
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        
        return $headers;
    }
    
    /**
     * Check if current page is plugin related
     */
    private function is_plugin_page() {
        if (!is_admin()) {
            return false;
        }
        
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }
        
        return strpos($screen->id, 'connect2form') !== false;
    }
    
    /**
     * Enhanced input sanitization
     */
    public function sanitize_input($value, $type = 'text') {
        if (is_array($value)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize_input($item, $type);
            }, $value);
        }
        
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'html':
                return wp_kses_post($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'key':
                return sanitize_key($value);
            case 'filename':
                return sanitize_file_name($value);
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            case 'bool':
                return (bool) $value;
            case 'json':
                // Validate JSON structure
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return wp_json_encode($decoded);
                }
                return '';
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Validate nonce with enhanced security
     */
    public function validate_nonce() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        $action = sanitize_text_field(wp_unslash($_POST['action'] ?? ''));
        
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(array(
                'message' => __('Security verification failed. Please refresh the page and try again.', 'connect2form'),
                'code' => 'invalid_nonce'
            ));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Enhanced rate limiting
     */
    public function enhanced_rate_limiting($is_limited, $form_id) {
        $ip = $this->get_client_ip();
        $transient_key = 'connect2form_rate_limit_' . md5($ip . $form_id);
        
        $attempts = get_transient($transient_key) ?: 0;
        $max_attempts = apply_filters('connect2form_max_attempts_per_hour', 10, $form_id);
        
        if ($attempts >= $max_attempts) {
            return true;
        }
        
        set_transient($transient_key, $attempts + 1, HOUR_IN_SECONDS);
        return false;
    }
    
    /**
     * Get client IP safely
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Restrict allowed file types
     */
    public function restrict_file_types($allowed_types) {
        // Only allow safe file types
        return array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain'
        );
    }
    
    /**
     * Enforce file size limits
     */
    public function enforce_file_size_limits($max_size) {
        // Default to 5MB, but allow filtering
        $default_max = 5 * 1024 * 1024; // 5MB
        return apply_filters('connect2form_absolute_max_file_size', min($max_size, $default_max));
    }
    
    /**
     * Validate capability with context
     */
    public static function check_capability($capability = 'manage_options', $context = '') {
        if (!current_user_can($capability)) {
            $message = sprintf(
                /* translators: 1: Action context (e.g., "edit forms"), 2: Required capability name */
                __('You do not have permission to %1$s. Required capability: %2$s', 'connect2form'),
                $context,
                $capability
            );
            
            if (wp_doing_ajax()) {
                wp_send_json_error(esc_html($message));
            } else {
                wp_die(esc_html($message));
            }
        }
    }
    
    /**
     * Validate and sanitize AJAX request
     */
    public static function validate_ajax_request( string $nonce_action, string $capability = 'manage_options' ): void {
        // Must be an AJAX context
        if ( ! wp_doing_ajax() ) {
            wp_send_json_error(
                array( 'message' => esc_html__( 'This endpoint can only be called via AJAX.', 'connect2form' ) ),
                400
            );
        }
    
        // Nonce check (expects a field named "nonce")
        if ( false === check_ajax_referer( $nonce_action, 'nonce', false ) ) {
            wp_send_json_error(
                array( 'message' => esc_html__( 'Security verification failed. Please refresh and try again.', 'connect2form' ) ),
                403
            );
        }
    
        // Capability check
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error(
                array( 'message' => esc_html__( 'You are not allowed to perform this action.', 'connect2form' ) ),
                403
            );
        }
    }
    
}

// Initialize security manager
new Connect2Form_Security();

