<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * Error Handler
 * 
 * Centralized error handling and recovery for integrations
 */
class ErrorHandler {

    private $logger;

    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Handle integration errors with proper logging and recovery
     */
    public function handleIntegrationError(\Throwable $error, string $integration_id, array $context = []): array {
        $error_data = [
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
            'integration_id' => $integration_id,
            'context' => $context
        ];

        // Log the error
        $this->logger->error("Integration Error: {$error->getMessage()}", $error_data);

        // Determine if this is a recoverable error
        $is_recoverable = $this->isRecoverableError($error);

        // Attempt recovery if possible
        if ($is_recoverable) {
            $recovery_result = $this->attemptRecovery($error, $integration_id, $context);
            if ($recovery_result['success']) {
                return [
                    'success' => true,
                    'recovered' => true,
                    'message' => 'Error recovered automatically'
                ];
            }
        }

        // Return error response
        return [
            'success' => false,
            'error' => $this->getUserFriendlyMessage($error),
            'error_code' => $error->getCode(),
            'recoverable' => $is_recoverable
        ];
    }

    /**
     * Handle API errors with retry logic
     */
    public function handleApiError(array $response, string $integration_id, array $context = []): array {
        $status_code = $response['status_code'] ?? 0;
        $error_message = $response['error'] ?? 'Unknown API error';

        // Log API error
        $this->logger->error("API Error: {$error_message}", [
            'integration_id' => $integration_id,
            'status_code' => $status_code,
            'context' => $context
        ]);

        // Determine if retry is appropriate
        if ($this->shouldRetryApiCall($status_code)) {
            $retry_result = $this->retryApiCall($integration_id, $context);
            if ($retry_result['success']) {
                return $retry_result;
            }
        }

        return [
            'success' => false,
            'error' => $this->getApiErrorMessage($status_code, $error_message),
            'status_code' => $status_code
        ];
    }

    /**
     * Check if error is recoverable
     */
    private function isRecoverableError(\Throwable $error): bool {
        $recoverable_codes = [
            'CURL_TIMEOUT',
            'API_RATE_LIMIT',
            'TEMPORARY_NETWORK_ERROR',
            'API_MAINTENANCE'
        ];

        $error_message = strtolower($error->getMessage());
        $recoverable_patterns = [
            'timeout',
            'rate limit',
            'temporary',
            'maintenance',
            'service unavailable'
        ];

        foreach ($recoverable_patterns as $pattern) {
            if (strpos($error_message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempt error recovery
     */
    private function attemptRecovery(\Throwable $error, string $integration_id, array $context): array {
        $error_message = strtolower($error->getMessage());

        // Handle rate limiting
        if (strpos($error_message, 'rate limit') !== false) {
            return $this->handleRateLimitRecovery($integration_id, $context);
        }

        // Handle timeout errors
        if (strpos($error_message, 'timeout') !== false) {
            return $this->handleTimeoutRecovery($integration_id, $context);
        }

        // Handle network errors
        if (strpos($error_message, 'network') !== false) {
            return $this->handleNetworkRecovery($integration_id, $context);
        }

        return ['success' => false];
    }

    /**
     * Handle rate limit recovery
     */
    private function handleRateLimitRecovery(string $integration_id, array $context): array {
        // Wait for rate limit to reset
        sleep(2);
        
        // Queue for retry
        $this->queueForRetry($integration_id, $context);
        
        return [
            'success' => true,
            'message' => 'Request queued for retry due to rate limiting'
        ];
    }

    /**
     * Handle timeout recovery
     */
    private function handleTimeoutRecovery(string $integration_id, array $context): array {
        // Increase timeout and retry once
        $context['timeout'] = ($context['timeout'] ?? 30) * 2;
        
        return $this->retryApiCall($integration_id, $context);
    }

    /**
     * Handle network recovery
     */
    private function handleNetworkRecovery(string $integration_id, array $context): array {
        // Wait and retry
        sleep(1);
        
        return $this->retryApiCall($integration_id, $context);
    }

    /**
     * Check if API call should be retried
     */
    private function shouldRetryApiCall(int $status_code): bool {
        $retryable_codes = [408, 429, 500, 502, 503, 504];
        return in_array($status_code, $retryable_codes);
    }

    /**
     * Retry API call
     */
    private function retryApiCall(string $integration_id, array $context): array {
        // Implement retry logic here
        // This would typically involve re-executing the original API call
        return ['success' => false, 'message' => 'Retry not implemented'];
    }

    /**
     * Queue request for retry
     */
    private function queueForRetry(string $integration_id, array $context): void {
        $retry_data = [
            'integration_id' => $integration_id,
            'context' => $context,
            'attempts' => ($context['attempts'] ?? 0) + 1,
            'scheduled_at' => time() + 300 // 5 minutes from now
        ];

        // Store in WordPress options for background processing
        $retry_key = "mavlers_cf_retry_{$integration_id}_" . time();
        update_option($retry_key, $retry_data, false);
    }

    /**
     * Get user-friendly error message
     */
    private function getUserFriendlyMessage(\Throwable $error): string {
        $message = $error->getMessage();
        
        // Map technical errors to user-friendly messages
        $error_mappings = [
            'CURL_TIMEOUT' => 'Request timed out. Please try again.',
            'API_RATE_LIMIT' => 'Too many requests. Please wait a moment and try again.',
            'INVALID_API_KEY' => 'Invalid API credentials. Please check your settings.',
            'NETWORK_ERROR' => 'Network connection error. Please check your internet connection.',
            'API_MAINTENANCE' => 'Service temporarily unavailable. Please try again later.'
        ];

        foreach ($error_mappings as $code => $friendly_message) {
            if (strpos($message, $code) !== false) {
                return $friendly_message;
            }
        }

        return 'An unexpected error occurred. Please try again or contact support.';
    }

    /**
     * Get API error message
     */
    private function getApiErrorMessage(int $status_code, string $original_message): string {
        $status_messages = [
            400 => 'Invalid request. Please check your settings.',
            401 => 'Authentication failed. Please check your API credentials.',
            403 => 'Access denied. Please check your API permissions.',
            404 => 'Resource not found. Please check your configuration.',
            408 => 'Request timed out. Please try again.',
            429 => 'Too many requests. Please wait a moment and try again.',
            500 => 'Server error. Please try again later.',
            502 => 'Bad gateway. Please try again later.',
            503 => 'Service unavailable. Please try again later.',
            504 => 'Gateway timeout. Please try again later.'
        ];

        return $status_messages[$status_code] ?? $original_message;
    }

    /**
     * Process retry queue
     */
    public function processRetryQueue(): void {
        global $wpdb;
        
        $options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE 'mavlers_cf_retry_%' 
             AND option_value LIKE '%\"scheduled_at\"%'"
        );

        foreach ($options as $option) {
            $retry_data = json_decode($option->option_value, true);
            
            if (!$retry_data || !isset($retry_data['scheduled_at'])) {
                continue;
            }

            // Check if it's time to retry
            if ($retry_data['scheduled_at'] <= time()) {
                $this->executeRetry($option->option_name, $retry_data);
            }
        }
    }

    /**
     * Execute retry
     */
    private function executeRetry(string $option_name, array $retry_data): void {
        // Maximum retry attempts
        if (($retry_data['attempts'] ?? 0) >= 3) {
            delete_option($option_name);
            $this->logger->error('Max retry attempts reached', $retry_data);
            return;
        }

        // Execute the retry logic here
        // This would involve re-executing the original integration call
        
        // Clean up the retry entry
        delete_option($option_name);
    }
} 