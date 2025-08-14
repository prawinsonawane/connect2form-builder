<?php
/**
 * API Client Class
 * 
 * Handles HTTP requests to integration APIs
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_API_Client {
    
    private $default_timeout = 30;
    private $default_headers = array();
    private $user_agent;
    
    public function __construct() {
        $this->user_agent = 'Mavlers Contact Forms/' . MAVLERS_CF_INTEGRATIONS_VERSION . ' (WordPress)';
        $this->default_headers = array(
            'User-Agent' => $this->user_agent,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        );
    }
    
    /**
     * Make HTTP request
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return array
     */
    public function request($method, $url, $data = array(), $headers = array(), $options = array()) {
        $method = strtoupper($method);
        
        // Merge headers
        $headers = array_merge($this->default_headers, $headers);
        
        // Prepare request arguments
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => $options['timeout'] ?? $this->default_timeout,
            'user-agent' => $this->user_agent,
            'sslverify' => $options['sslverify'] ?? true
        );
        
        // Add body for POST, PUT, PATCH requests
        if (in_array($method, array('POST', 'PUT', 'PATCH')) && !empty($data)) {
            if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
                $args['body'] = json_encode($data);
            } elseif (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/x-www-form-urlencoded') {
                $args['body'] = http_build_query($data);
            } else {
                $args['body'] = $data;
            }
        }
        
        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        }
        
        // Log request (in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Mavlers CF API Request: %s %s with data: %s',
                $method,
                $url,
                json_encode($data)
            ));
        }
        
        // Make the request
        $start_time = microtime(true);
        $response = wp_remote_request($url, $args);
        $execution_time = microtime(true) - $start_time;
        
        // Handle errors
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'code' => 0,
                'execution_time' => $execution_time
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        // Log response (in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Mavlers CF API Response: %d in %.2fs - %s',
                $response_code,
                $execution_time,
                substr($response_body, 0, 200)
            ));
        }
        
        // Parse response
        $parsed_response = $this->parse_response($response_body, $response_headers);
        
        return array(
            'success' => $response_code >= 200 && $response_code < 300,
            'code' => $response_code,
            'data' => $parsed_response,
            'headers' => $response_headers,
            'execution_time' => $execution_time,
            'raw_body' => $response_body
        );
    }
    
    /**
     * Make GET request
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param array $options
     * @return array
     */
    public function get($url, $params = array(), $headers = array(), $options = array()) {
        return $this->request('GET', $url, $params, $headers, $options);
    }
    
    /**
     * Make POST request
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return array
     */
    public function post($url, $data = array(), $headers = array(), $options = array()) {
        return $this->request('POST', $url, $data, $headers, $options);
    }
    
    /**
     * Make PUT request
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return array
     */
    public function put($url, $data = array(), $headers = array(), $options = array()) {
        return $this->request('PUT', $url, $data, $headers, $options);
    }
    
    /**
     * Make PATCH request
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return array
     */
    public function patch($url, $data = array(), $headers = array(), $options = array()) {
        return $this->request('PATCH', $url, $data, $headers, $options);
    }
    
    /**
     * Make DELETE request
     * @param string $url
     * @param array $headers
     * @param array $options
     * @return array
     */
    public function delete($url, $headers = array(), $options = array()) {
        return $this->request('DELETE', $url, array(), $headers, $options);
    }
    
    /**
     * Parse response body based on content type
     * @param string $body
     * @param array $headers
     * @return mixed
     */
    private function parse_response($body, $headers) {
        $content_type = '';
        
        // Get content type from headers
        if (isset($headers['content-type'])) {
            $content_type = $headers['content-type'];
        } elseif (isset($headers['Content-Type'])) {
            $content_type = $headers['Content-Type'];
        }
        
        // Parse JSON
        if (strpos($content_type, 'application/json') !== false || 
            strpos($content_type, 'text/json') !== false) {
            $decoded = json_decode($body, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
        }
        
        // Parse XML
        if (strpos($content_type, 'application/xml') !== false || 
            strpos($content_type, 'text/xml') !== false) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            if ($xml !== false) {
                return json_decode(json_encode($xml), true);
            }
        }
        
        // Return raw body for other content types
        return $body;
    }
    
    /**
     * Set default timeout
     * @param int $timeout
     */
    public function set_timeout($timeout) {
        $this->default_timeout = intval($timeout);
    }
    
    /**
     * Set default headers
     * @param array $headers
     */
    public function set_default_headers($headers) {
        $this->default_headers = array_merge($this->default_headers, $headers);
    }
    
    /**
     * Add authentication to headers
     * @param array $auth_data
     * @param string $auth_type
     * @return array
     */
    public function prepare_auth_headers($auth_data, $auth_type = 'bearer') {
        $headers = array();
        
        switch ($auth_type) {
            case 'bearer':
                if (!empty($auth_data['access_token'])) {
                    $headers['Authorization'] = 'Bearer ' . $auth_data['access_token'];
                }
                break;
                
            case 'api_key':
                if (!empty($auth_data['api_key'])) {
                    $headers['Authorization'] = 'ApiKey ' . $auth_data['api_key'];
                }
                break;
                
            case 'basic':
                if (!empty($auth_data['username']) && !empty($auth_data['password'])) {
                    $credentials = base64_encode($auth_data['username'] . ':' . $auth_data['password']);
                    $headers['Authorization'] = 'Basic ' . $credentials;
                }
                break;
                
            case 'custom':
                if (!empty($auth_data['header_name']) && !empty($auth_data['header_value'])) {
                    $headers[$auth_data['header_name']] = $auth_data['header_value'];
                }
                break;
        }
        
        return $headers;
    }
    
    /**
     * Handle OAuth 2.0 token refresh
     * @param array $auth_data
     * @param string $refresh_url
     * @return array
     */
    public function refresh_oauth_token($auth_data, $refresh_url) {
        if (empty($auth_data['refresh_token'])) {
            return array(
                'success' => false,
                'error' => 'No refresh token available'
            );
        }
        
        $data = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $auth_data['refresh_token']
        );
        
        if (!empty($auth_data['client_id'])) {
            $data['client_id'] = $auth_data['client_id'];
        }
        
        if (!empty($auth_data['client_secret'])) {
            $data['client_secret'] = $auth_data['client_secret'];
        }
        
        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        
        $response = $this->post($refresh_url, $data, $headers);
        
        if ($response['success'] && isset($response['data']['access_token'])) {
            return array(
                'success' => true,
                'access_token' => $response['data']['access_token'],
                'refresh_token' => $response['data']['refresh_token'] ?? $auth_data['refresh_token'],
                'expires_in' => $response['data']['expires_in'] ?? 3600
            );
        }
        
        return array(
            'success' => false,
            'error' => $response['data']['error_description'] ?? 'Token refresh failed'
        );
    }
    
    /**
     * Upload file to API
     * @param string $url
     * @param string $file_path
     * @param array $additional_data
     * @param array $headers
     * @return array
     */
    public function upload_file($url, $file_path, $additional_data = array(), $headers = array()) {
        if (!file_exists($file_path)) {
            return array(
                'success' => false,
                'error' => 'File not found'
            );
        }
        
        $boundary = wp_generate_uuid4();
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
        
        $body = '';
        
        // Add additional form data
        foreach ($additional_data as $key => $value) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
            $body .= $value . "\r\n";
        }
        
        // Add file
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . '"' . "\r\n";
        $body .= 'Content-Type: ' . mime_content_type($file_path) . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= '--' . $boundary . '--' . "\r\n";
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout' => 60, // Longer timeout for file uploads
            'user-agent' => $this->user_agent
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        return array(
            'success' => $response_code >= 200 && $response_code < 300,
            'code' => $response_code,
            'data' => $this->parse_response($response_body, $response_headers),
            'headers' => $response_headers
        );
    }
    
    /**
     * Handle paginated API requests
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param string $page_param
     * @param string $per_page_param
     * @param int $max_pages
     * @return array
     */
    public function get_paginated($url, $params = array(), $headers = array(), $page_param = 'page', $per_page_param = 'per_page', $max_pages = 10) {
        $all_data = array();
        $page = 1;
        $total_pages = 1;
        
        do {
            $page_params = array_merge($params, array(
                $page_param => $page,
                $per_page_param => $params[$per_page_param] ?? 100
            ));
            
            $response = $this->get($url, $page_params, $headers);
            
            if (!$response['success']) {
                break;
            }
            
            $data = $response['data'];
            
            // Handle different pagination response formats
            if (isset($data['data'])) {
                $all_data = array_merge($all_data, $data['data']);
                $total_pages = $data['total_pages'] ?? $data['last_page'] ?? 1;
            } elseif (is_array($data)) {
                $all_data = array_merge($all_data, $data);
                // If we get less than requested, assume it's the last page
                if (count($data) < ($params[$per_page_param] ?? 100)) {
                    break;
                }
            }
            
            $page++;
            
        } while ($page <= $total_pages && $page <= $max_pages);
        
        return array(
            'success' => true,
            'data' => $all_data,
            'total_pages' => $total_pages,
            'total_items' => count($all_data)
        );
    }
    
    /**
     * Test API connection
     * @param string $url
     * @param array $headers
     * @return array
     */
    public function test_connection($url, $headers = array()) {
        $start_time = microtime(true);
        
        $response = $this->get($url, array(), $headers, array('timeout' => 10));
        
        $execution_time = microtime(true) - $start_time;
        
        return array(
            'success' => $response['success'],
            'response_time' => $execution_time,
            'status_code' => $response['code'],
            'error' => $response['success'] ? null : ($response['error'] ?? 'Connection failed')
        );
    }
} 