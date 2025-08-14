<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * API Client Service
 * 
 * Handles HTTP requests for integrations
 */
class ApiClient {

    private $default_timeout = 30;
    private $user_agent;

    public function __construct() {
        $this->user_agent = 'Mavlers Contact Forms Integrations/' . MAVLERS_CF_INTEGRATIONS_VERSION;
    }

    /**
     * Make HTTP request
     */
    public function request(string $method, string $url, array $args = []): array {
        // Check rate limiting
        $rate_limit_key = 'api_request_' . md5($url);
        if (!SecurityManager::checkRateLimit($rate_limit_key, 100, 3600)) {
            return [
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED'
            ];
        }

        $method = strtoupper($method);
        
        $default_args = [
            'timeout' => $this->default_timeout,
            'user-agent' => $this->user_agent,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ];

        $args = wp_parse_args($args, $default_args);

        // Handle different HTTP methods
        switch ($method) {
            case 'GET':
                $response = wp_remote_get($url, $args);
                break;
            case 'POST':
                $response = wp_remote_post($url, $args);
                break;
            case 'PUT':
                $args['method'] = 'PUT';
                $response = wp_remote_request($url, $args);
                break;
            case 'DELETE':
                $args['method'] = 'DELETE';
                $response = wp_remote_request($url, $args);
                break;
            case 'PATCH':
                $args['method'] = 'PATCH';
                $response = wp_remote_request($url, $args);
                break;
            default:
                return [
                    'success' => false,
                    'error' => "Unsupported HTTP method: {$method}"
                ];
        }

        return $this->handle_response($response, $url, $method);
    }

    /**
     * Make GET request
     */
    public function get(string $url, array $args = []): array {
        return $this->request('GET', $url, $args);
    }

    /**
     * Make POST request
     */
    public function post(string $url, array $data = [], array $args = []): array {
        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }
        return $this->request('POST', $url, $args);
    }

    /**
     * Make PUT request
     */
    public function put(string $url, array $data = [], array $args = []): array {
        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }
        return $this->request('PUT', $url, $args);
    }

    /**
     * Make DELETE request
     */
    public function delete(string $url, array $args = []): array {
        return $this->request('DELETE', $url, $args);
    }

    /**
     * Make PATCH request
     */
    public function patch(string $url, array $data = [], array $args = []): array {
        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }
        return $this->request('PATCH', $url, $args);
    }

    /**
     * Handle API response
     */
    private function handle_response($response, string $url, string $method): array {
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Try to decode JSON response
        $decoded_body = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $body = $decoded_body;
        }

        $result = [
            'success' => $status_code >= 200 && $status_code < 300,
            'status_code' => $status_code,
            'data' => $body,
            'headers' => wp_remote_retrieve_headers($response)
        ];

        // Add error information for non-success responses
        if (!$result['success']) {
            $result['error'] = $this->get_error_message($status_code, $body);
            $result['error_code'] = $status_code;
        }

        return $result;
    }

    /**
     * Get error message based on status code
     */
    private function get_error_message(int $status_code, $body): string {
        // If body contains error message, use it
        if (is_array($body) && isset($body['error'])) {
            return $body['error'];
        }
        
        if (is_array($body) && isset($body['message'])) {
            return $body['message'];
        }

        // Default error messages based on status code
        $error_messages = [
            400 => 'Bad Request - Invalid parameters',
            401 => 'Unauthorized - Invalid credentials',
            403 => 'Forbidden - Access denied',
            404 => 'Not Found - Resource not found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity - Validation failed',
            429 => 'Too Many Requests - Rate limit exceeded',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout'
        ];

        return $error_messages[$status_code] ?? "HTTP Error {$status_code}";
    }

    /**
     * Set request timeout
     */
    public function setTimeout(int $timeout): void {
        $this->default_timeout = $timeout;
    }

    /**
     * Set user agent
     */
    public function setUserAgent(string $user_agent): void {
        $this->user_agent = $user_agent;
    }

    /**
     * Create basic auth header
     */
    public function createBasicAuthHeader(string $username, string $password): array {
        return [
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        ];
    }

    /**
     * Create bearer token header
     */
    public function createBearerTokenHeader(string $token): array {
        return [
            'Authorization' => 'Bearer ' . $token
        ];
    }
} 