<?php
/**
 * Security Manager Class
 * 
 * Handles encryption and security for sensitive integration data
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Security_Manager {
    
    private static $encryption_key = null;
    
    /**
     * Get or generate encryption key
     * @return string
     */
    private static function get_encryption_key() {
        if (self::$encryption_key !== null) {
            return self::$encryption_key;
        }
        
        // Try to get key from wp-config or database
        if (defined('MAVLERS_CF_ENCRYPTION_KEY')) {
            self::$encryption_key = MAVLERS_CF_ENCRYPTION_KEY;
        } else {
            // Generate or retrieve from database
            $key = get_option('mavlers_cf_encryption_key');
            if (!$key) {
                $key = self::generate_encryption_key();
                update_option('mavlers_cf_encryption_key', $key);
            }
            self::$encryption_key = $key;
        }
        
        return self::$encryption_key;
    }
    
    /**
     * Generate a secure encryption key
     * @return string
     */
    private static function generate_encryption_key() {
        if (function_exists('random_bytes')) {
            return base64_encode(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return base64_encode(openssl_random_pseudo_bytes(32));
        } else {
            // Fallback for older PHP versions
            return base64_encode(wp_generate_uuid4() . wp_generate_uuid4());
        }
    }
    
    /**
     * Encrypt sensitive data
     * @param mixed $data
     * @return string
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        $data = is_array($data) || is_object($data) ? json_encode($data) : $data;
        
        if (!function_exists('openssl_encrypt')) {
            // Fallback to base64 encoding if OpenSSL is not available
            return base64_encode($data);
        }
        
        $key = self::get_encryption_key();
        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        
        $encrypted = openssl_encrypt($data, $method, base64_decode($key), 0, $iv);
        
        if ($encrypted === false) {
            // Fallback if encryption fails
            return base64_encode($data);
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     * @param string $encrypted_data
     * @return mixed
     */
    public static function decrypt($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }
        
        if (!function_exists('openssl_decrypt')) {
            // Fallback from base64 if OpenSSL is not available
            $decoded = base64_decode($encrypted_data);
            return $decoded !== false ? $decoded : $encrypted_data;
        }
        
        $key = self::get_encryption_key();
        $method = 'AES-256-CBC';
        
        $data = base64_decode($encrypted_data);
        if ($data === false) {
            return $encrypted_data;
        }
        
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt($encrypted, $method, base64_decode($key), 0, $iv);
        
        if ($decrypted === false) {
            // Try fallback base64 decode
            $decoded = base64_decode($encrypted_data);
            return $decoded !== false ? $decoded : $encrypted_data;
        }
        
        // Try to decode JSON
        $json_decoded = json_decode($decrypted, true);
        return json_last_error() === JSON_ERROR_NONE ? $json_decoded : $decrypted;
    }
    
    /**
     * Hash sensitive data (one-way)
     * @param string $data
     * @param string $salt
     * @return string
     */
    public static function hash($data, $salt = '') {
        if (empty($salt)) {
            $salt = wp_salt('auth');
        }
        
        return hash('sha256', $data . $salt);
    }
    
    /**
     * Verify hashed data
     * @param string $data
     * @param string $hash
     * @param string $salt
     * @return bool
     */
    public static function verify_hash($data, $hash, $salt = '') {
        return hash_equals($hash, self::hash($data, $salt));
    }
    
    /**
     * Sanitize API credentials before storage
     * @param array $credentials
     * @return array
     */
    public static function sanitize_credentials($credentials) {
        $sanitized = array();
        
        foreach ($credentials as $key => $value) {
            if (is_string($value)) {
                // Remove potentially dangerous characters
                $sanitized[$key] = preg_replace('/[<>"\']/', '', trim($value));
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize_credentials($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate API endpoint URL
     * @param string $url
     * @return bool
     */
    public static function validate_api_url($url) {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsed = parse_url($url);
        
        // Must be HTTPS for security
        if (!isset($parsed['scheme']) || $parsed['scheme'] !== 'https') {
            return false;
        }
        
        // Block local/internal IPs
        if (isset($parsed['host'])) {
            $ip = gethostbyname($parsed['host']);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate secure token
     * @param int $length
     * @return string
     */
    public static function generate_token($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            // Fallback
            return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
        }
    }
    
    /**
     * Rate limiting check
     * @param string $key
     * @param int $limit
     * @param int $window
     * @return bool
     */
    public static function check_rate_limit($key, $limit = 60, $window = 60) {
        $transient_key = 'mavlers_cf_rate_' . md5($key);
        $current_count = get_transient($transient_key);
        
        if ($current_count === false) {
            set_transient($transient_key, 1, $window);
            return true;
        }
        
        if ($current_count >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, $window);
        return true;
    }
    
    /**
     * Validate webhook signature
     * @param string $payload
     * @param string $signature
     * @param string $secret
     * @param string $algorithm
     * @return bool
     */
    public static function validate_webhook_signature($payload, $signature, $secret, $algorithm = 'sha256') {
        $expected_signature = hash_hmac($algorithm, $payload, $secret);
        
        // Handle different signature formats
        if (strpos($signature, '=') !== false) {
            list($algo, $hash) = explode('=', $signature, 2);
            $signature = $hash;
        }
        
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Mask sensitive data for logging
     * @param string $data
     * @param int $visible_chars
     * @return string
     */
    public static function mask_sensitive_data($data, $visible_chars = 4) {
        if (strlen($data) <= $visible_chars * 2) {
            return str_repeat('*', strlen($data));
        }
        
        $start = substr($data, 0, $visible_chars);
        $end = substr($data, -$visible_chars);
        $middle = str_repeat('*', strlen($data) - ($visible_chars * 2));
        
        return $start . $middle . $end;
    }
    
    /**
     * Check if data contains sensitive information
     * @param mixed $data
     * @return bool
     */
    public static function contains_sensitive_data($data) {
        if (is_array($data)) {
            $data = json_encode($data);
        }
        
        $sensitive_patterns = array(
            '/api[_-]?key/i',
            '/access[_-]?token/i',
            '/refresh[_-]?token/i',
            '/client[_-]?secret/i',
            '/password/i',
            '/private[_-]?key/i',
            '/secret/i',
            '/auth/i'
        );
        
        foreach ($sensitive_patterns as $pattern) {
            if (preg_match($pattern, $data)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize data for logging
     * @param mixed $data
     * @return mixed
     */
    public static function sanitize_for_logging($data) {
        if (is_string($data)) {
            if (self::contains_sensitive_data($data)) {
                return self::mask_sensitive_data($data);
            }
            return $data;
        }
        
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                if (self::contains_sensitive_data($key)) {
                    $sanitized[$key] = self::mask_sensitive_data(is_string($value) ? $value : json_encode($value));
                } else {
                    $sanitized[$key] = self::sanitize_for_logging($value);
                }
            }
            return $sanitized;
        }
        
        return $data;
    }
    
    /**
     * Generate CSRF token
     * @param string $action
     * @return string
     */
    public static function generate_csrf_token($action = 'default') {
        return wp_create_nonce('mavlers_cf_integration_' . $action);
    }
    
    /**
     * Verify CSRF token
     * @param string $token
     * @param string $action
     * @return bool
     */
    public static function verify_csrf_token($token, $action = 'default') {
        return wp_verify_nonce($token, 'mavlers_cf_integration_' . $action);
    }
    
    /**
     * Validate OAuth state parameter
     * @param string $state
     * @param string $expected_state
     * @return bool
     */
    public static function validate_oauth_state($state, $expected_state) {
        return hash_equals($expected_state, $state);
    }
    
    /**
     * Generate OAuth state parameter
     * @return string
     */
    public static function generate_oauth_state() {
        $state = self::generate_token(40);
        set_transient('mavlers_cf_oauth_state_' . $state, true, 600); // 10 minutes
        return $state;
    }
    
    /**
     * Verify OAuth state parameter
     * @param string $state
     * @return bool
     */
    public static function verify_oauth_state($state) {
        $transient_key = 'mavlers_cf_oauth_state_' . $state;
        $valid = get_transient($transient_key);
        delete_transient($transient_key); // Use once
        return $valid !== false;
    }
    
    /**
     * Clean expired tokens and data
     */
    public static function cleanup_expired_data() {
        global $wpdb;
        
        // Clean expired OAuth states
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_mavlers_cf_oauth_state_%',
                time()
            )
        );
        
        // Clean old integration logs (older than 90 days)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}mavlers_cf_integration_logs'")) {
            $wpdb->query(
                "DELETE FROM {$wpdb->prefix}mavlers_cf_integration_logs 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
        }
    }
} 