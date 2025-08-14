<?php

namespace MavlersCF\Integrations\Hubspot;

use MavlersCF\Integrations\Core\Services\SecurityManager;

/**
 * HubSpot Security Enhancement
 * 
 * Provides comprehensive security enhancements for the HubSpot integration
 */
class HubspotSecurityEnhancement {

    /**
     * Validate and sanitize AJAX request
     */
    public static function validateAjaxRequest(string $nonce_action, string $capability = 'edit_posts'): array {
        $errors = [];

        // Check if request is AJAX
        if (!wp_doing_ajax()) {
            $errors[] = 'Invalid request method';
            return ['valid' => false, 'errors' => $errors];
        }

        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            $errors[] = 'Security check failed';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check user capabilities
        if (!current_user_can($capability)) {
            $errors[] = 'Insufficient permissions';
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Sanitize HubSpot settings
     */
    public static function sanitizeHubSpotSettings(array $settings): array {
        $sanitized = [];

        // Required fields
        $sanitized['access_token'] = sanitize_text_field($settings['access_token'] ?? '');
        $sanitized['portal_id'] = sanitize_text_field($settings['portal_id'] ?? '');

        // Optional fields
        $sanitized['enabled'] = (bool) ($settings['enabled'] ?? false);
        $sanitized['object_type'] = sanitize_key($settings['object_type'] ?? 'contacts');
        $sanitized['custom_object_name'] = sanitize_text_field($settings['custom_object_name'] ?? '');
        $sanitized['action_type'] = sanitize_key($settings['action_type'] ?? 'create_or_update');
        $sanitized['workflow_enabled'] = (bool) ($settings['workflow_enabled'] ?? false);

        // Field mapping (array)
        if (isset($settings['field_mapping']) && is_array($settings['field_mapping'])) {
            $sanitized['field_mapping'] = [];
            foreach ($settings['field_mapping'] as $key => $value) {
                $sanitized['field_mapping'][sanitize_key($key)] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Validate HubSpot credentials
     */
    public static function validateHubSpotCredentials(array $credentials): array {
        $errors = [];

        if (empty($credentials['access_token'])) {
            $errors[] = 'Access token is required';
        } elseif (strlen($credentials['access_token']) < 10) {
            $errors[] = 'Access token appears to be invalid';
        }

        if (empty($credentials['portal_id'])) {
            $errors[] = 'Portal ID is required';
        } elseif (!is_numeric($credentials['portal_id'])) {
            $errors[] = 'Portal ID must be numeric';
        }

        return $errors;
    }

    /**
     * Rate limiting for API calls
     */
    public static function checkApiRateLimit(string $action, int $max_requests = 100, int $time_window = 3600): bool {
        $user_id = get_current_user_id();
        $rate_limit_key = "hubspot_api_limit_{$action}_{$user_id}";
        
        $current_requests = get_transient($rate_limit_key);
        
        if ($current_requests === false) {
            set_transient($rate_limit_key, 1, $time_window);
            return true;
        }
        
        if ($current_requests >= $max_requests) {
            return false;
        }
        
        set_transient($rate_limit_key, $current_requests + 1, $time_window);
        return true;
    }

    /**
     * Sanitize form data for HubSpot
     */
    public static function sanitizeFormData(array $form_data): array {
        $sanitized = [];

        foreach ($form_data as $key => $value) {
            if (is_array($value)) {
                $sanitized[sanitize_key($key)] = self::sanitizeFormData($value);
            } else {
                $sanitized[sanitize_key($key)] = sanitize_text_field($value);
            }
        }

        return $sanitized;
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
     * Log security events
     */
    public static function logSecurityEvent(string $event, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("HubSpot Security Event: {$event} - " . json_encode($context));
        }
    }

    /**
     * Generate secure nonce
     */
    public static function generateNonce(string $action): string {
        return wp_create_nonce($action);
    }

    /**
     * Validate form ID
     */
    public static function validateFormId(int $form_id): bool {
        if ($form_id <= 0) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_cf_forms';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE id = %d",
            $form_id
        ));

        return $exists !== null;
    }

    /**
     * Sanitize API response
     */
    public static function sanitizeApiResponse(array $response): array {
        $sanitized = [];

        foreach ($response as $key => $value) {
            if (is_array($value)) {
                $sanitized[sanitize_key($key)] = self::sanitizeApiResponse($value);
            } else {
                $sanitized[sanitize_key($key)] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
} 