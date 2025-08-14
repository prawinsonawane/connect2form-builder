<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * Security Manager
 * 
 * Centralized security functions for the integrations addon
 */
class SecurityManager {

    /**
     * Verify nonce with proper error handling
     */
    public static function verifyNonce(string $nonce, string $action): bool {
        if (empty($nonce)) {
            return false;
        }
        
        return \wp_verify_nonce($nonce, $action) !== false;
    }

    /**
     * Sanitize integration settings
     */
    public static function sanitizeSettings(array $settings): array {
        $sanitized = [];
        
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeSettings($value);
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            } elseif (is_numeric($value)) {
                $sanitized[$key] = is_float($value) ? floatval($value) : intval($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Validate API credentials format
     */
    public static function validateApiCredentials(array $credentials, string $integration_type): array {
        $errors = [];
        
        switch ($integration_type) {
            case 'mailchimp':
                if (empty($credentials['api_key'])) {
                    $errors[] = 'API key is required';
                } elseif (!preg_match('/^[a-f0-9]{32}-[a-z0-9]+$/', $credentials['api_key'])) {
                    $errors[] = 'Invalid API key format';
                }
                break;
                
            case 'hubspot':
                if (empty($credentials['access_token'])) {
                    $errors[] = 'Access token is required';
                }
                if (empty($credentials['portal_id'])) {
                    $errors[] = 'Portal ID is required';
                }
                break;
        }
        
        return $errors;
    }

    /**
     * Escape output for display
     */
    public static function escapeOutput($value): string {
        if (is_array($value)) {
            return esc_html(json_encode($value));
        }
        return esc_html($value);
    }

    /**
     * Validate file uploads
     */
    public static function validateFileUpload(array $file): array {
        $errors = [];
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload';
            return $errors;
        }
        
        // Check file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'File size exceeds 2MB limit';
        }
        
        // Check file type
        $allowed_types = ['text/csv', 'application/json', 'text/plain'];
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'Invalid file type';
        }
        
        return $errors;
    }

    /**
     * Rate limiting for API calls
     */
    public static function checkRateLimit(string $action, int $max_attempts = 10, int $time_window = 3600): bool {
        $user_id = get_current_user_id();
        $key = "rate_limit_{$action}_{$user_id}";
        
        $attempts = get_transient($key);
        if ($attempts === false) {
            set_transient($key, 1, $time_window);
            return true;
        }
        
        if ($attempts >= $max_attempts) {
            return false;
        }
        
        set_transient($key, $attempts + 1, $time_window);
        return true;
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent(string $event, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Security event logging disabled for production
        }
    }
} 