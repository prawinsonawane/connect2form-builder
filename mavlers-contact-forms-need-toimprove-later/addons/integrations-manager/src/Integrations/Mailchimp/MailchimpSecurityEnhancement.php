<?php

namespace MavlersCF\Integrations\Mailchimp;

/**
 * Mailchimp Security Enhancement
 * 
 * Provides comprehensive security enhancements for the Mailchimp integration
 */
class MailchimpSecurityEnhancement {

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
     * Sanitize Mailchimp settings
     */
    public static function sanitizeMailchimpSettings(array $settings): array {
        $sanitized = [];

        // Required fields
        $sanitized['api_key'] = sanitize_text_field($settings['api_key'] ?? '');
        $sanitized['datacenter'] = sanitize_text_field($settings['datacenter'] ?? '');

        // Optional fields
        $sanitized['enabled'] = (bool) ($settings['enabled'] ?? false);
        $sanitized['audience_id'] = sanitize_text_field($settings['audience_id'] ?? '');
        $sanitized['double_optin'] = (bool) ($settings['double_optin'] ?? true);
        $sanitized['update_existing'] = (bool) ($settings['update_existing'] ?? false);
        $sanitized['tags'] = sanitize_text_field($settings['tags'] ?? '');
        $sanitized['enable_analytics'] = (bool) ($settings['enable_analytics'] ?? true);
        $sanitized['enable_webhooks'] = (bool) ($settings['enable_webhooks'] ?? false);
        $sanitized['batch_processing'] = (bool) ($settings['batch_processing'] ?? false);

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
     * Validate Mailchimp credentials
     */
    public static function validateMailchimpCredentials(array $credentials): array {
        $errors = [];

        if (empty($credentials['api_key'])) {
            $errors[] = 'API key is required';
        } elseif (!preg_match('/^[a-f0-9]{32}-[a-z0-9]+$/', $credentials['api_key'])) {
            $errors[] = 'Invalid API key format. Expected format: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-xxx';
        }

        return $errors;
    }

    /**
     * Rate limiting for API calls
     */
    public static function checkApiRateLimit(string $action, int $max_requests = 100, int $time_window = 3600): bool {
        $user_id = get_current_user_id();
        $rate_limit_key = "mailchimp_api_limit_{$action}_{$user_id}";
        
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
     * Sanitize form data for Mailchimp
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
     * Validate webhook signature
     */
    public static function validateWebhookSignature(string $raw_data, string $signature, string $secret): bool {
        if (empty($secret)) {
            return true; // Skip verification if no secret is set
        }

        if (empty($signature)) {
            return false;
        }

        // Calculate expected signature
        $expected_signature = base64_encode(hash_hmac('sha1', $raw_data, $secret, true));
        
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Validate audience ID format
     */
    public static function validateAudienceId(string $audience_id): bool {
        // Mailchimp audience IDs are typically 32-character hexadecimal strings
        return preg_match('/^[a-f0-9]{32}$/', $audience_id);
    }

    /**
     * Validate email address for Mailchimp
     */
    public static function validateEmailForMailchimp(string $email): bool {
        // Basic email validation
        if (!is_email($email)) {
            return false;
        }

        // Check for common disposable email domains
        $disposable_domains = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
            'tempmail.org', 'throwaway.email', 'yopmail.com'
        ];

        $domain = strtolower(substr(strrchr($email, '@'), 1));
        if (in_array($domain, $disposable_domains)) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize merge fields
     */
    public static function sanitizeMergeFields(array $merge_fields): array {
        $sanitized = [];

        foreach ($merge_fields as $key => $value) {
            $sanitized_key = sanitize_key($key);
            $sanitized_value = sanitize_text_field($value);
            
            if (!empty($sanitized_value)) {
                $sanitized[$sanitized_key] = $sanitized_value;
            }
        }

        return $sanitized;
    }

    /**
     * Validate API response
     */
    public static function validateApiResponse($response): array {
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code >= 400) {
            $error_detail = 'Unknown error';
            if ($decoded && isset($decoded['detail'])) {
                $error_detail = $decoded['detail'];
            } elseif ($decoded && isset($decoded['title'])) {
                $error_detail = $decoded['title'];
            }
            
            return [
                'valid' => false,
                'error' => $error_detail,
                'status_code' => $status_code
            ];
        }

        return [
            'valid' => true,
            'data' => $decoded
        ];
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent(string $event, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Mailchimp Security Event: {$event} - " . json_encode($context));
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

    /**
     * Validate datacenter format
     */
    public static function validateDatacenter(string $datacenter): bool {
        // Mailchimp datacenters are typically 2-3 character codes like 'us1', 'eu1', etc.
        return preg_match('/^[a-z]{2,3}[0-9]$/', $datacenter);
    }

    /**
     * Extract datacenter from API key
     */
    public static function extractDatacenter(string $api_key): ?string {
        $parts = explode('-', $api_key);
        $datacenter = end($parts);
        
        return self::validateDatacenter($datacenter) ? $datacenter : null;
    }

    /**
     * Validate webhook URL
     */
    public static function validateWebhookUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Sanitize webhook data
     */
    public static function sanitizeWebhookData(array $webhook_data): array {
        $sanitized = [];

        // Only allow specific webhook event types
        $allowed_events = ['subscribe', 'unsubscribe', 'profile', 'cleaned', 'upemail', 'campaign'];
        
        if (isset($webhook_data['type']) && in_array($webhook_data['type'], $allowed_events)) {
            $sanitized['type'] = sanitize_key($webhook_data['type']);
        }

        if (isset($webhook_data['data']) && is_array($webhook_data['data'])) {
            $sanitized['data'] = self::sanitizeApiResponse($webhook_data['data']);
        }

        return $sanitized;
    }
} 