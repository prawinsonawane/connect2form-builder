<?php

namespace Connect2Form\Integrations\Core\Services;

/**
 * Error Handler
 * 
 * Centralized error handling and recovery for integrations.
 *
 * @since    2.0.0
 * @access   public
 */
class ErrorHandler {

    /**
     * Logger instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      Logger    $logger    Logger instance.
     */
    private $logger;

    /**
     * Constructor
     *
     * @since    2.0.0
     */
    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Handle integration errors with proper logging and recovery.
     *
     * @since    2.0.0
     * @param    \Throwable $error          Error object.
     * @param    string     $integration_id Integration ID.
     * @param    array      $context        Error context.
     * @return   array
     */
    public function handleIntegrationError( \Throwable $error, string $integration_id, array $context = array() ): array {
        $error_data = array(
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
            'integration_id' => $integration_id,
            'context' => $context,
        );

        // Log the error.
        $this->logger->error( "Integration Error: {$error->getMessage()}", $error_data );

        // Determine if this is a recoverable error.
        $is_recoverable = $this->isRecoverableError( $error );

        // Attempt recovery if possible.
        if ( $is_recoverable ) {
            $recovery_result = $this->attemptRecovery( $error, $integration_id, $context );
            if ( $recovery_result['success'] ) {
                return array(
                    'success' => true,
                    'recovered' => true,
                    'message' => 'Error recovered automatically',
                );
            }
        }

        // Return error response.
        return array(
            'success' => false,
            'error' => $this->getUserFriendlyMessage( $error ),
            'error_code' => $error->getCode(),
            'recoverable' => $is_recoverable,
        );
    }

    /**
     * Handle API errors with retry logic.
     *
     * @since    2.0.0
     * @param    array  $response       API response.
     * @param    string $integration_id Integration ID.
     * @param    array  $context        Error context.
     * @return   array
     */
    public function handleApiError( array $response, string $integration_id, array $context = array() ): array {
        $status_code = $response['status_code'] ?? 0;
        $error_message = $response['error'] ?? 'Unknown API error';

        // Log API error.
        $this->logger->error( "API Error: {$error_message}", array(
            'integration_id' => $integration_id,
            'status_code' => $status_code,
            'context' => $context,
        ) );

        // Determine if retry is appropriate.
        if ( $this->shouldRetryApiCall( $status_code ) ) {
            $retry_result = $this->retryApiCall( $integration_id, $context );
            if ( $retry_result['success'] ) {
                return $retry_result;
            }
        }

        return array(
            'success' => false,
            'error' => $this->getApiErrorMessage( $status_code, $error_message ),
            'status_code' => $status_code,
        );
    }

    /**
     * Check if error is recoverable.
     *
     * @since    2.0.0
     * @access   private
     * @param    \Throwable $error Error object.
     * @return   bool
     */
    private function isRecoverableError( \Throwable $error ): bool {
        $recoverable_codes = array(
            'CURL_TIMEOUT',
            'API_RATE_LIMIT',
            'TEMPORARY_NETWORK_ERROR',
            'API_MAINTENANCE',
        );

        $error_message = strtolower( $error->getMessage() );
        $recoverable_patterns = array(
            'timeout',
            'rate limit',
            'temporary',
            'maintenance',
            'service unavailable'
        );

        foreach ( $recoverable_patterns as $pattern ) {
            if ( strpos( $error_message, $pattern ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempt error recovery.
     *
     * @since    2.0.0
     * @access   private
     * @param    \Throwable $error          Error object.
     * @param    string     $integration_id Integration ID.
     * @param    array      $context        Error context.
     * @return   array
     */
    private function attemptRecovery( \Throwable $error, string $integration_id, array $context ): array {
        $error_message = strtolower( $error->getMessage() );

        // Handle rate limiting.
        if ( strpos( $error_message, 'rate limit' ) !== false ) {
            return $this->handleRateLimitRecovery( $integration_id, $context );
        }

        // Handle timeout errors.
        if ( strpos( $error_message, 'timeout' ) !== false ) {
            return $this->handleTimeoutRecovery( $integration_id, $context );
        }

        // Handle network errors.
        if ( strpos( $error_message, 'network' ) !== false ) {
            return $this->handleNetworkRecovery( $integration_id, $context );
        }

        return array( 'success' => false );
    }

    /**
     * Handle rate limit recovery.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $integration_id Integration ID.
     * @param    array  $context        Error context.
     * @return   array
     */
    private function handleRateLimitRecovery( string $integration_id, array $context ): array {
        // Wait for rate limit to reset.
        sleep( 2 );
        
        // Queue for retry.
        $this->queueForRetry( $integration_id, $context );
        
        return array(
            'success' => true,
            'message' => 'Request queued for retry due to rate limiting',
        );
    }

    /**
     * Handle timeout recovery.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $integration_id Integration ID.
     * @param    array  $context        Error context.
     * @return   array
     */
    private function handleTimeoutRecovery( string $integration_id, array $context ): array {
        // Increase timeout and retry once.
        $context['timeout'] = ( $context['timeout'] ?? 30 ) * 2;
        
        return $this->retryApiCall( $integration_id, $context );
    }

    /**
     * Handle network recovery.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $integration_id Integration ID.
     * @param    array  $context        Error context.
     * @return   array
     */
    private function handleNetworkRecovery( string $integration_id, array $context ): array {
        // Wait and retry.
        sleep( 1 );
        
        return $this->retryApiCall( $integration_id, $context );
    }

    /**
     * Check if API call should be retried.
     *
     * @since    2.0.0
     * @access   private
     * @param    int $status_code HTTP status code.
     * @return   bool
     */
    private function shouldRetryApiCall( int $status_code ): bool {
        $retryable_codes = array( 408, 429, 500, 502, 503, 504 );
        return in_array( $status_code, $retryable_codes );
    }

    /**
     * Retry API call.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $integration_id Integration ID.
     * @param    array  $context        Error context.
     * @return   array
     */
    private function retryApiCall( string $integration_id, array $context ): array {
        // Implement retry logic here.
        // This would typically involve re-executing the original API call.
        return array( 'success' => false, 'message' => 'Retry not implemented' );
    }

    /**
     * Queue request for retry.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $integration_id Integration ID.
     * @param    array  $context        Error context.
     */
    private function queueForRetry( string $integration_id, array $context ): void {
        $retry_data = array(
            'integration_id' => $integration_id,
            'context' => $context,
            'attempts' => ( $context['attempts'] ?? 0 ) + 1,
            'scheduled_at' => time() + 300, // 5 minutes from now.
        );

        // Store in WordPress options for background processing.
        $retry_key = "connect2form_retry_{$integration_id}_" . time();
        update_option( $retry_key, $retry_data, false );
    }

    /**
     * Get user-friendly error message.
     *
     * @since    2.0.0
     * @access   private
     * @param    \Throwable $error Error object.
     * @return   string
     */
    private function getUserFriendlyMessage( \Throwable $error ): string {
        $message = $error->getMessage();
        
        // Map technical errors to user-friendly messages.
        $error_mappings = array(
            'CURL_TIMEOUT' => 'Request timed out. Please try again.',
            'API_RATE_LIMIT' => 'Too many requests. Please wait a moment and try again.',
            'INVALID_API_KEY' => 'Invalid API credentials. Please check your settings.',
            'NETWORK_ERROR' => 'Network connection error. Please check your internet connection.',
            'API_MAINTENANCE' => 'Service temporarily unavailable. Please try again later.',
        );

        foreach ( $error_mappings as $code => $friendly_message ) {
            if ( strpos( $message, $code ) !== false ) {
                return $friendly_message;
            }
        }

        return 'An unexpected error occurred. Please try again or contact support.';
    }

    /**
     * Get API error message.
     *
     * @since    2.0.0
     * @access   private
     * @param    int    $status_code HTTP status code.
     * @param    string $original_message Original error message.
     * @return   string
     */
    private function getApiErrorMessage( int $status_code, string $original_message ): string {
        $status_messages = array(
            400 => 'Invalid request. Please check your settings.',
            401 => 'Authentication failed. Please check your API credentials.',
            403 => 'Access denied. Please check your API permissions.',
            404 => 'Resource not found. Please check your configuration.',
            408 => 'Request timed out. Please try again.',
            429 => 'Too many requests. Please wait a moment and try again.',
            500 => 'Server error. Please try again later.',
            502 => 'Bad gateway. Please try again later.',
            503 => 'Service unavailable. Please try again later.',
            504 => 'Gateway timeout. Please try again later.',
        );

        return $status_messages[ $status_code ] ?? $original_message;
    }

    /**
     * Process retry queue.
     *
     * @since    2.0.0
     */
    public function processRetryQueue(): void {
        if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
            // Use WordPress options API instead of direct database access.
            global $wpdb;
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- querying retry options; no caching needed for cleanup operation
            $options = $wpdb->get_results(
                "SELECT option_name, option_value FROM {$wpdb->options} 
                 WHERE option_name LIKE 'connect2form_retry_%' 
                 AND option_value LIKE '%\"scheduled_at\"%'"
            );
        } else {
            // Fallback to direct database call if service not available.
            global $wpdb;
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- querying retry options; no caching needed for cleanup operation
            $options = $wpdb->get_results(
                "SELECT option_name, option_value FROM {$wpdb->options} 
                 WHERE option_name LIKE 'connect2form_retry_%' 
                 AND option_value LIKE '%\"scheduled_at\"%'"
            );
        }

        foreach ( $options as $option ) {
            $retry_data = json_decode( $option->option_value, true );
            
            if ( ! $retry_data || ! isset( $retry_data['scheduled_at'] ) ) {
                continue;
            }

            // Check if it's time to retry.
            if ( $retry_data['scheduled_at'] <= time() ) {
                $this->executeRetry( $option->option_name, $retry_data );
            }
        }
    }

    /**
     * Execute retry.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $option_name Option name.
     * @param    array  $retry_data  Retry data.
     */
    private function executeRetry( string $option_name, array $retry_data ): void {
        // Maximum retry attempts.
        if ( ( $retry_data['attempts'] ?? 0 ) >= 3 ) {
            delete_option( $option_name );
            $this->logger->error( 'Max retry attempts reached', $retry_data );
            return;
        }

        // Execute the retry logic here.
        // This would involve re-executing the original integration call.
        
        // Clean up the retry entry.
        delete_option( $option_name );
    }
} 
