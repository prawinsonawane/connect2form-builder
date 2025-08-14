<?php
/**
 * Mailchimp Modern Bridge
 * 
 * Provides backward compatibility while introducing modern PHP features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Mavlers_CF_Mailchimp_Modern_Bridge
 * 
 * Bridges legacy code with modern PHP features
 */
class Mavlers_CF_Mailchimp_Modern_Bridge {
    
    private $type_manager;
    private $legacy_mode = true;
    
    public function __construct() {
        $this->init_type_manager();
        $this->detect_php_version();
    }
    
    /**
     * Initialize type manager if PHP version supports it
     */
    private function init_type_manager() {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            require_once dirname(__FILE__) . '/class-mailchimp-type-manager.php';
            $this->type_manager = new Mavlers_CF_Mailchimp_Type_Manager();
            $this->legacy_mode = false;
        }
    }
    
    /**
     * Detect PHP version and set appropriate mode
     */
    private function detect_php_version() {
        $php_version = PHP_VERSION;
        $min_required = '7.4.0';
        $recommended = '8.0.0';
        
        if (version_compare($php_version, $min_required, '<')) {
            add_action('admin_notices', array($this, 'show_php_version_warning'));
        } elseif (version_compare($php_version, $recommended, '<')) {
            add_action('admin_notices', array($this, 'show_php_upgrade_notice'));
        }
    }
    
    /**
     * Show PHP version warning
     */
    public function show_php_version_warning() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div class="notice notice-error">';
        echo '<p><strong>Mailchimp Integration Warning:</strong> ';
        echo sprintf(
            __('Your PHP version (%s) is outdated. Please upgrade to PHP 7.4 or higher for optimal performance and security.', 'mavlers-cf'),
            PHP_VERSION
        );
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Show PHP upgrade notice
     */
    public function show_php_upgrade_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only show if not dismissed
        if (get_option('mavlers_cf_mailchimp_php_notice_dismissed')) {
            return;
        }
        
        echo '<div class="notice notice-info is-dismissible" data-notice="php-upgrade">';
        echo '<p><strong>Mailchimp Integration:</strong> ';
        echo sprintf(
            __('For enhanced type safety and better performance, consider upgrading to PHP 8.0+. Current version: %s', 'mavlers-cf'),
            PHP_VERSION
        );
        echo '</p>';
        echo '</div>';
        
        // Add JavaScript to handle dismissal
        echo '<script>
        jQuery(document).on("click", ".notice[data-notice=\'php-upgrade\'] .notice-dismiss", function() {
            jQuery.post(ajaxurl, {
                action: "dismiss_php_upgrade_notice",
                nonce: "' . wp_create_nonce('dismiss_notice') . '"
            });
        });
        </script>';
        
        // Add AJAX handler
        add_action('wp_ajax_dismiss_php_upgrade_notice', array($this, 'dismiss_php_upgrade_notice'));
    }
    
    /**
     * Dismiss PHP upgrade notice
     */
    public function dismiss_php_upgrade_notice() {
        check_ajax_referer('dismiss_notice', 'nonce');
        
        if (current_user_can('manage_options')) {
            update_option('mavlers_cf_mailchimp_php_notice_dismissed', true);
        }
        
        wp_die();
    }
    
    /**
     * Validate field value with fallback
     */
    public function validate_field_value($value, $field_type) {
        if (!$this->legacy_mode && $this->type_manager) {
            try {
                return $this->type_manager->validateFieldValue((string) $value, (string) $field_type);
            } catch (TypeError $e) {
                // Fallback to legacy validation
                error_log('Mailchimp Modern Bridge: Type error, falling back to legacy validation: ' . $e->getMessage());
            }
        }
        
        return $this->legacy_validate_field_value($value, $field_type);
    }
    
    /**
     * Legacy field validation
     */
    private function legacy_validate_field_value($value, $field_type) {
        if (empty($value)) {
            return array('valid' => true, 'message' => '');
        }
        
        $patterns = array(
            'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'phone' => '/^[\+]?[1-9][\d]{0,15}$/',
            'url' => '/^https?:\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?$/',
            'number' => '/^-?\d*\.?\d+$/',
            'zip' => '/^\d{5}(-\d{4})?$/',
            'date' => '/^\d{4}-\d{2}-\d{2}$/',
            'birthday' => '/^(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])$/'
        );
        
        $pattern = isset($patterns[$field_type]) ? $patterns[$field_type] : null;
        
        if ($pattern === null) {
            return array('valid' => true, 'message' => '');
        }
        
        $is_valid = preg_match($pattern, $value) === 1;
        $message = $is_valid ? '' : $this->get_legacy_validation_message($field_type);
        
        return array('valid' => $is_valid, 'message' => $message);
    }
    
    /**
     * Get legacy validation message
     */
    private function get_legacy_validation_message($field_type) {
        $messages = array(
            'email' => __('Please enter a valid email address', 'mavlers-cf'),
            'phone' => __('Please enter a valid phone number', 'mavlers-cf'),
            'url' => __('Please enter a valid URL', 'mavlers-cf'),
            'number' => __('Please enter a valid number', 'mavlers-cf'),
            'zip' => __('Please enter a valid ZIP code', 'mavlers-cf'),
            'date' => __('Please enter a valid date (YYYY-MM-DD)', 'mavlers-cf'),
            'birthday' => __('Please enter a valid birthday (MM/DD)', 'mavlers-cf')
        );
        
        return isset($messages[$field_type]) ? $messages[$field_type] : __('Invalid value', 'mavlers-cf');
    }
    
    /**
     * Format field value with fallback
     */
    public function format_field_value($value, $field_type) {
        if (!$this->legacy_mode && $this->type_manager) {
            try {
                return $this->type_manager->formatFieldValue((string) $value, (string) $field_type);
            } catch (TypeError $e) {
                error_log('Mailchimp Modern Bridge: Type error, falling back to legacy formatting: ' . $e->getMessage());
            }
        }
        
        return $this->legacy_format_field_value($value, $field_type);
    }
    
    /**
     * Legacy field formatting
     */
    private function legacy_format_field_value($value, $field_type) {
        if (empty($value)) {
            return $value;
        }
        
        switch ($field_type) {
            case 'phone':
                return $this->legacy_format_phone($value);
            case 'url':
                return $this->legacy_format_url($value);
            case 'date':
                return $this->legacy_format_date($value);
            case 'birthday':
                return $this->legacy_format_birthday($value);
            case 'email':
                return strtolower(trim($value));
            case 'text':
                return trim($value);
            default:
                return $value;
        }
    }
    
    /**
     * Legacy phone formatting
     */
    private function legacy_format_phone($phone) {
        $cleaned = preg_replace('/[^+0-9]/', '', $phone);
        
        if (strpos($cleaned, '+') !== 0 && strlen($cleaned) >= 10) {
            $cleaned = '+1' . $cleaned;
        }
        
        return $cleaned;
    }
    
    /**
     * Legacy URL formatting
     */
    private function legacy_format_url($url) {
        if (!preg_match('/^https?:\/\//', $url)) {
            return 'https://' . $url;
        }
        
        return $url;
    }
    
    /**
     * Legacy date formatting
     */
    private function legacy_format_date($date) {
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return $date;
    }
    
    /**
     * Legacy birthday formatting
     */
    private function legacy_format_birthday($birthday) {
        // Simple MM/DD formatting
        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $birthday, $matches)) {
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            return "{$month}/{$day}";
        }
        
        return $birthday;
    }
    
    /**
     * Sanitize API response with fallback
     */
    public function sanitize_api_response($response) {
        if (!is_array($response)) {
            return array();
        }
        
        if (!$this->legacy_mode && $this->type_manager) {
            try {
                return $this->type_manager->sanitizeApiResponse($response);
            } catch (TypeError $e) {
                error_log('Mailchimp Modern Bridge: Type error, falling back to legacy sanitization: ' . $e->getMessage());
            }
        }
        
        return $this->legacy_sanitize_response($response);
    }
    
    /**
     * Legacy response sanitization
     */
    private function legacy_sanitize_response($response) {
        $sanitized = array();
        
        foreach ($response as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->legacy_sanitize_response($value);
            } elseif (is_numeric($value) || is_bool($value)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = sanitize_text_field((string) $value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Create standardized response
     */
    public function create_response($success, $message, $data = null) {
        if (!$this->legacy_mode && $this->type_manager) {
            try {
                if ($success) {
                    $response = $this->type_manager->createSuccessResponse($message, $data);
                } else {
                    $response = $this->type_manager->createErrorResponse($message, 400, $data);
                }
                
                return $response->toArray();
            } catch (TypeError $e) {
                error_log('Mailchimp Modern Bridge: Type error, falling back to legacy response: ' . $e->getMessage());
            }
        }
        
        return array(
            'success' => $success,
            'message' => $message,
            'data' => $data
        );
    }
    
    /**
     * Safe array access
     */
    public function safe_array_get($array, $key, $default = null, $expected_type = null) {
        if (!is_array($array) || !array_key_exists($key, $array)) {
            return $default;
        }
        
        $value = $array[$key];
        
        if ($expected_type === null) {
            return $value;
        }
        
        // Type casting with error handling
        switch ($expected_type) {
            case 'string':
                return is_string($value) ? $value : (string) $value;
            case 'int':
                return is_int($value) ? $value : (int) $value;
            case 'float':
                return is_float($value) ? $value : (float) $value;
            case 'bool':
                return is_bool($value) ? $value : (bool) $value;
            case 'array':
                return is_array($value) ? $value : array($value);
            default:
                return $value;
        }
    }
    
    /**
     * Enhanced error logging
     */
    public function log_error($message, $context = array(), $level = 'error') {
        if (!$this->legacy_mode && $this->type_manager) {
            try {
                $this->type_manager->logError((string) $message, (array) $context, (string) $level);
                return;
            } catch (TypeError $e) {
                // Continue to legacy logging
            }
        }
        
        // Legacy logging
        $log_message = sprintf(
            '[Mailchimp %s] %s',
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        
        error_log($log_message);
    }
    
    /**
     * Performance timing
     */
    public function start_timer($operation) {
        if (!$this->legacy_mode && $this->type_manager) {
            try {
                return $this->type_manager->startTimer((string) $operation);
            } catch (TypeError $e) {
                // Continue to legacy timing
            }
        }
        
        // Legacy timing
        $start_time = microtime(true);
        $GLOBALS["mavlers_cf_timer_{$operation}"] = $start_time;
        return $start_time;
    }
    
    /**
     * End performance timing
     */
    public function end_timer($operation) {
        if (!$this->legacy_mode && $this->type_manager) {
            try {
                return $this->type_manager->endTimer((string) $operation);
            } catch (TypeError $e) {
                // Continue to legacy timing
            }
        }
        
        // Legacy timing
        $end_time = microtime(true);
        $start_time = isset($GLOBALS["mavlers_cf_timer_{$operation}"]) 
            ? $GLOBALS["mavlers_cf_timer_{$operation}"] 
            : $end_time;
        $duration = ($end_time - $start_time) * 1000;
        
        unset($GLOBALS["mavlers_cf_timer_{$operation}"]);
        
        return $duration;
    }
    
    /**
     * Get PHP environment info
     */
    public function get_environment_info() {
        return array(
            'php_version' => PHP_VERSION,
            'modern_features_available' => !$this->legacy_mode,
            'type_declarations_supported' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'union_types_supported' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'match_expressions_supported' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'readonly_properties_supported' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'wordpress_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        );
    }
    
    /**
     * Check if feature is supported
     */
    public function is_feature_supported($feature) {
        $features = array(
            'type_declarations' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'union_types' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'match_expressions' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'readonly_properties' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'enums' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'fibers' => version_compare(PHP_VERSION, '8.1.0', '>=')
        );
        
        return isset($features[$feature]) ? $features[$feature] : false;
    }
    
    /**
     * Get type manager instance (if available)
     */
    public function get_type_manager() {
        return $this->type_manager;
    }
    
    /**
     * Check if running in legacy mode
     */
    public function is_legacy_mode() {
        return $this->legacy_mode;
    }
    
    /**
     * Validate form data with enhanced error reporting
     */
    public function validate_form_data($form_data, $field_mappings) {
        if (!$this->legacy_mode && $this->type_manager) {
            try {
                return $this->type_manager->validateFormData((array) $form_data, (array) $field_mappings);
            } catch (TypeError $e) {
                $this->log_error('Type error in form validation, falling back to legacy: ' . $e->getMessage());
            }
        }
        
        // Legacy form validation
        $errors = array();
        $sanitized_data = array();
        
        foreach ($field_mappings as $form_field => $mailchimp_field) {
            $value = isset($form_data[$form_field]) ? $form_data[$form_field] : '';
            
            if (empty($value)) {
                continue;
            }
            
            // Simple validation
            $validation_result = $this->validate_field_value($value, 'text'); // Default to text
            
            if (!$validation_result['valid']) {
                $errors[$form_field] = $validation_result['message'];
                continue;
            }
            
            $sanitized_data[$form_field] = sanitize_text_field($value);
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_data' => $sanitized_data
        );
    }
    
    /**
     * Migration helper - convert legacy arrays to modern objects
     */
    public function modernize_legacy_config($legacy_config) {
        if (!is_array($legacy_config)) {
            return array();
        }
        
        $modern_config = array();
        
        // Convert known legacy keys to modern equivalents
        $key_mapping = array(
            'api_key' => 'api_key',
            'audience_id' => 'audience_id',
            'double_optin' => 'double_opt_in',
            'update_existing' => 'allow_updates',
            'tags' => 'default_tags'
        );
        
        foreach ($legacy_config as $old_key => $value) {
            $new_key = isset($key_mapping[$old_key]) ? $key_mapping[$old_key] : $old_key;
            $modern_config[$new_key] = $value;
        }
        
        return $modern_config;
    }
} 