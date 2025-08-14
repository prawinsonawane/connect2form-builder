<?php
/**
 * Mailchimp Type Manager
 * 
 * Provides type safety, modern PHP features, and improved error handling
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Mavlers_CF_Mailchimp_Type_Manager
 * 
 * Handles type safety and modern PHP features for Mailchimp integration
 */
class Mavlers_CF_Mailchimp_Type_Manager {
    
    private const SUPPORTED_FIELD_TYPES = [
        'text', 'email', 'number', 'phone', 'date', 
        'birthday', 'address', 'url', 'dropdown', 
        'radio', 'zip', 'imageurl'
    ];
    
    private const VALIDATION_PATTERNS = [
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        'phone' => '/^[\+]?[1-9][\d]{0,15}$/',
        'url' => '/^https?:\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?$/',
        'number' => '/^-?\d*\.?\d+$/',
        'zip' => '/^\d{5}(-\d{4})?$/',
        'date' => '/^\d{4}-\d{2}-\d{2}$/',
        'birthday' => '/^(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])$/'
    ];
    
    /**
     * Validate field type
     */
    public function isValidFieldType(string $fieldType): bool {
        return in_array($fieldType, self::SUPPORTED_FIELD_TYPES, true);
    }
    
    /**
     * Validate field value against its type
     */
    public function validateFieldValue(string $value, string $fieldType): ValidationResult {
        if (empty($value)) {
            return new ValidationResult(true, '');
        }
        
        $pattern = self::VALIDATION_PATTERNS[$fieldType] ?? null;
        
        if ($pattern === null) {
            return new ValidationResult(true, '');
        }
        
        $isValid = preg_match($pattern, $value) === 1;
        $message = $isValid ? '' : $this->getValidationErrorMessage($fieldType);
        
        return new ValidationResult($isValid, $message);
    }
    
    /**
     * Get validation error message for field type
     */
    private function getValidationErrorMessage(string $fieldType): string {
        $messages = [
            'email' => __('Please enter a valid email address', 'mavlers-cf'),
            'phone' => __('Please enter a valid phone number', 'mavlers-cf'),
            'url' => __('Please enter a valid URL', 'mavlers-cf'),
            'number' => __('Please enter a valid number', 'mavlers-cf'),
            'zip' => __('Please enter a valid ZIP code', 'mavlers-cf'),
            'date' => __('Please enter a valid date (YYYY-MM-DD)', 'mavlers-cf'),
            'birthday' => __('Please enter a valid birthday (MM/DD)', 'mavlers-cf')
        ];
        
        return $messages[$fieldType] ?? __('Invalid value', 'mavlers-cf');
    }
    
    /**
     * Format field value according to its type
     */
    public function formatFieldValue(string $value, string $fieldType): string {
        if (empty($value)) {
            return $value;
        }
        
        switch ($fieldType) {
            case 'phone':
                return $this->formatPhoneNumber($value);
            case 'url':
                return $this->formatUrl($value);
            case 'date':
                return $this->formatDate($value);
            case 'birthday':
                return $this->formatBirthday($value);
            case 'email':
                return strtolower(trim($value));
            case 'text':
                return trim($value);
            default:
                return $value;
        }
    }
    
    /**
     * Format phone number
     */
    private function formatPhoneNumber(string $phone): string {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^+0-9]/', '', $phone);
        
        // Ensure international format if not already
        if (!str_starts_with($cleaned, '+') && strlen($cleaned) >= 10) {
            $cleaned = '+1' . $cleaned;
        }
        
        return $cleaned;
    }
    
    /**
     * Format URL
     */
    private function formatUrl(string $url): string {
        if (!preg_match('/^https?:\/\//', $url)) {
            return 'https://' . $url;
        }
        
        return $url;
    }
    
    /**
     * Format date
     */
    private function formatDate(string $date): string {
        try {
            $dateTime = new DateTime($date);
            return $dateTime->format('Y-m-d');
        } catch (Exception $e) {
            return $date; // Return original if parsing fails
        }
    }
    
    /**
     * Format birthday
     */
    private function formatBirthday(string $birthday): string {
        // Try to parse various birthday formats and convert to MM/DD
        $patterns = [
            '/^(\d{1,2})\/(\d{1,2})$/',           // MM/DD or M/D
            '/^(\d{1,2})-(\d{1,2})$/',           // MM-DD or M-D
            '/^(\d{4})-(\d{1,2})-(\d{1,2})$/',   // YYYY-MM-DD
            '/^(\d{1,2})\/(\d{1,2})\/\d{4}$/'    // MM/DD/YYYY
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $birthday, $matches)) {
                if (count($matches) === 3) { // MM/DD format
                    $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    return "{$month}/{$day}";
                } elseif (count($matches) === 4) { // YYYY-MM-DD format
                    $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
                    return "{$month}/{$day}";
                }
            }
        }
        
        return $birthday; // Return original if no pattern matches
    }
    
    /**
     * Sanitize API response data
     */
    public function sanitizeApiResponse(array $response): array {
        $sanitized = [];
        
        foreach ($response as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeApiResponse($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = sanitize_text_field((string) $value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate form data array
     */
    public function validateFormData(array $formData, array $fieldMappings): FormValidationResult {
        $errors = [];
        $sanitizedData = [];
        
        foreach ($fieldMappings as $formField => $mailchimpField) {
            $value = $formData[$formField] ?? '';
            
            if (empty($value)) {
                continue;
            }
            
            // Get field type from Mailchimp field configuration
            $fieldType = $this->extractFieldType($mailchimpField);
            
            // Validate value
            $validationResult = $this->validateFieldValue($value, $fieldType);
            
            if (!$validationResult->isValid()) {
                $errors[$formField] = $validationResult->getMessage();
                continue;
            }
            
            // Format and sanitize value
            $formattedValue = $this->formatFieldValue($value, $fieldType);
            $sanitizedData[$formField] = sanitize_text_field($formattedValue);
        }
        
        return new FormValidationResult(empty($errors), $errors, $sanitizedData);
    }
    
    /**
     * Extract field type from Mailchimp field configuration
     */
    private function extractFieldType(string $mailchimpField): string {
        // Map common Mailchimp field tags to types
        $typeMapping = [
            'EMAIL' => 'email',
            'FNAME' => 'text',
            'LNAME' => 'text',
            'PHONE' => 'phone',
            'BIRTHDAY' => 'birthday',
            'ADDRESS' => 'address',
            'WEBSITE' => 'url'
        ];
        
        return $typeMapping[$mailchimpField] ?? 'text';
    }
    
    /**
     * Create error response with proper structure
     */
    public function createErrorResponse(string $message, int $code = 400, ?array $data = null): ErrorResponse {
        return new ErrorResponse($message, $code, $data);
    }
    
    /**
     * Create success response with proper structure
     */
    public function createSuccessResponse(string $message, ?array $data = null): SuccessResponse {
        return new SuccessResponse($message, $data);
    }
    
    /**
     * Convert legacy response format to modern format
     */
    public function modernizeResponse(array $legacyResponse): ResponseInterface {
        $success = $legacyResponse['success'] ?? false;
        $message = $legacyResponse['message'] ?? '';
        $data = $legacyResponse['data'] ?? null;
        
        if ($success) {
            return $this->createSuccessResponse($message, $data);
        } else {
            return $this->createErrorResponse($message, 400, $data);
        }
    }
    
    /**
     * Safe array access with type checking
     */
    public function safeArrayGet(array $array, string $key, $default = null, ?string $expectedType = null) {
        if (!array_key_exists($key, $array)) {
            return $default;
        }
        
        $value = $array[$key];
        
        if ($expectedType === null) {
            return $value;
        }
        
        switch ($expectedType) {
            case 'string':
                return is_string($value) ? $value : (string) $value;
            case 'int':
                return is_int($value) ? $value : (int) $value;
            case 'float':
                return is_float($value) ? $value : (float) $value;
            case 'bool':
                return is_bool($value) ? $value : (bool) $value;
            case 'array':
                return is_array($value) ? $value : [$value];
            default:
                return $value;
        }
    }
    
    /**
     * Type-safe configuration retrieval
     */
    public function getTypedConfig(array $config, string $key, $default = null, string $type = 'mixed') {
        $value = $this->safeArrayGet($config, $key, $default);
        
        return match ($type) {
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value
        };
    }
    
    /**
     * Enhanced error logging with context
     */
    public function logError(string $message, array $context = [], string $level = 'error'): void {
        $logMessage = sprintf(
            '[Mailchimp %s] %s',
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }
        
        error_log($logMessage);
        
        // Also trigger WordPress action for other plugins to hook into
        do_action('mavlers_cf_mailchimp_log', $level, $message, $context);
    }
    
    /**
     * Performance timing utilities
     */
    public function startTimer(string $operation): float {
        $startTime = microtime(true);
        $GLOBALS["mavlers_cf_timer_{$operation}"] = $startTime;
        return $startTime;
    }
    
    /**
     * End timer and log performance
     */
    public function endTimer(string $operation): float {
        $endTime = microtime(true);
        $startTime = $GLOBALS["mavlers_cf_timer_{$operation}"] ?? $endTime;
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $this->logError("Performance: {$operation} took {$duration}ms", [
            'operation' => $operation,
            'duration_ms' => $duration,
            'start_time' => $startTime,
            'end_time' => $endTime
        ], 'info');
        
        unset($GLOBALS["mavlers_cf_timer_{$operation}"]);
        
        return $duration;
    }
}

/**
 * Validation Result Value Object
 */
class ValidationResult {
    private bool $valid;
    private string $message;
    
    public function __construct(bool $valid, string $message = '') {
        $this->valid = $valid;
        $this->message = $message;
    }
    
    public function isValid(): bool {
        return $this->valid;
    }
    
    public function getMessage(): string {
        return $this->message;
    }
}

/**
 * Form Validation Result Value Object
 */
class FormValidationResult {
    private bool $valid;
    private array $errors;
    private array $sanitizedData;
    
    public function __construct(bool $valid, array $errors = [], array $sanitizedData = []) {
        $this->valid = $valid;
        $this->errors = $errors;
        $this->sanitizedData = $sanitizedData;
    }
    
    public function isValid(): bool {
        return $this->valid;
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
    
    public function getSanitizedData(): array {
        return $this->sanitizedData;
    }
    
    public function hasError(string $field): bool {
        return isset($this->errors[$field]);
    }
    
    public function getError(string $field): string {
        return $this->errors[$field] ?? '';
    }
}

/**
 * Response Interface
 */
interface ResponseInterface {
    public function isSuccess(): bool;
    public function getMessage(): string;
    public function getData(): ?array;
    public function toArray(): array;
}

/**
 * Success Response Value Object
 */
class SuccessResponse implements ResponseInterface {
    private string $message;
    private ?array $data;
    
    public function __construct(string $message, ?array $data = null) {
        $this->message = $message;
        $this->data = $data;
    }
    
    public function isSuccess(): bool {
        return true;
    }
    
    public function getMessage(): string {
        return $this->message;
    }
    
    public function getData(): ?array {
        return $this->data;
    }
    
    public function toArray(): array {
        return [
            'success' => true,
            'message' => $this->message,
            'data' => $this->data
        ];
    }
}

/**
 * Error Response Value Object
 */
class ErrorResponse implements ResponseInterface {
    private string $message;
    private int $code;
    private ?array $data;
    
    public function __construct(string $message, int $code = 400, ?array $data = null) {
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;
    }
    
    public function isSuccess(): bool {
        return false;
    }
    
    public function getMessage(): string {
        return $this->message;
    }
    
    public function getCode(): int {
        return $this->code;
    }
    
    public function getData(): ?array {
        return $this->data;
    }
    
    public function toArray(): array {
        return [
            'success' => false,
            'message' => $this->message,
            'code' => $this->code,
            'data' => $this->data
        ];
    }
} 