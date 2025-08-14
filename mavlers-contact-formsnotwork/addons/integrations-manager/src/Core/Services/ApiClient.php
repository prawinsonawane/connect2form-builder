<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * API Client Service
 * 
 * Handles HTTP requests for integrations
 */
class ApiClient {

	/**
	 * Default request timeout
	 *
	 * @var int
	 */
	private $default_timeout = 30;

	/**
	 * User agent string
	 *
	 * @var string
	 */
	private $user_agent;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->user_agent = 'Mavlers Contact Forms Integrations/' . MAVLERS_CF_INTEGRATIONS_VERSION;
	}

	/**
	 * Make HTTP request
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Request URL.
	 * @param array  $args   Request arguments.
	 * @return array
	 */
	public function request( string $method, string $url, array $args = array() ): array {
		$method = strtoupper( $method );
		
		$default_args = array(
			'timeout' => $this->default_timeout,
			'user-agent' => $this->user_agent,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);

		$args = wp_parse_args( $args, $default_args );

		// Handle different HTTP methods
		switch ( $method ) {
			case 'GET':
				$response = wp_remote_get( $url, $args );
				break;
			case 'POST':
				$response = wp_remote_post( $url, $args );
				break;
			case 'PUT':
				$args['method'] = 'PUT';
				$response = wp_remote_request( $url, $args );
				break;
			case 'DELETE':
				$args['method'] = 'DELETE';
				$response = wp_remote_request( $url, $args );
				break;
			case 'PATCH':
				$args['method'] = 'PATCH';
				$response = wp_remote_request( $url, $args );
				break;
			default:
				return array(
					'success' => false,
					'error' => "Unsupported HTTP method: {$method}",
				);
		}

		return $this->handle_response( $response, $url, $method );
	}

	/**
	 * Make GET request
	 *
	 * @param string $url  Request URL.
	 * @param array  $args Request arguments.
	 * @return array
	 */
	public function get( string $url, array $args = array() ): array {
		return $this->request( 'GET', $url, $args );
	}

	/**
	 * Make POST request
	 *
	 * @param string $url  Request URL.
	 * @param array  $data Request data.
	 * @param array  $args Request arguments.
	 * @return array
	 */
	public function post( string $url, array $data = array(), array $args = array() ): array {
		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}
		return $this->request( 'POST', $url, $args );
	}

	/**
	 * Make PUT request
	 *
	 * @param string $url  Request URL.
	 * @param array  $data Request data.
	 * @param array  $args Request arguments.
	 * @return array
	 */
	public function put( string $url, array $data = array(), array $args = array() ): array {
		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}
		return $this->request( 'PUT', $url, $args );
	}

	/**
	 * Make DELETE request
	 *
	 * @param string $url  Request URL.
	 * @param array  $args Request arguments.
	 * @return array
	 */
	public function delete( string $url, array $args = array() ): array {
		return $this->request( 'DELETE', $url, $args );
	}

	/**
	 * Make PATCH request
	 *
	 * @param string $url  Request URL.
	 * @param array  $data Request data.
	 * @param array  $args Request arguments.
	 * @return array
	 */
	public function patch( string $url, array $data = array(), array $args = array() ): array {
		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}
		return $this->request( 'PATCH', $url, $args );
	}

	/**
	 * Handle API response
	 *
	 * @param mixed  $response Response from wp_remote_* function.
	 * @param string $url      Request URL.
	 * @param string $method   HTTP method.
	 * @return array
	 */
	private function handle_response( $response, string $url, string $method ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error' => $response->get_error_message(),
				'error_code' => $response->get_error_code(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		// Try to decode JSON response
		$decoded_body = json_decode( $body, true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			$body = $decoded_body;
		}

		$result = array(
			'success' => $status_code >= 200 && $status_code < 300,
			'status_code' => $status_code,
			'data' => $body,
			'headers' => wp_remote_retrieve_headers( $response ),
		);

		// Add error information for non-success responses
		if ( ! $result['success'] ) {
			$result['error'] = $this->get_error_message( $status_code, $body );
			$result['error_code'] = $status_code;
		}

		return $result;
	}

	/**
	 * Get error message based on status code
	 *
	 * @param int   $status_code HTTP status code.
	 * @param mixed $body        Response body.
	 * @return string
	 */
	private function get_error_message( int $status_code, $body ): string {
		// If body contains error message, use it
		if ( is_array( $body ) && isset( $body['error'] ) ) {
			return sanitize_text_field( $body['error'] );
		}
		
		if ( is_array( $body ) && isset( $body['message'] ) ) {
			return sanitize_text_field( $body['message'] );
		}

		// Default error messages based on status code
		$error_messages = array(
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
			504 => 'Gateway Timeout',
		);

		return $error_messages[ $status_code ] ?? "HTTP Error {$status_code}";
	}

	/**
	 * Set request timeout
	 *
	 * @param int $timeout Timeout in seconds.
	 */
	public function setTimeout( int $timeout ): void {
		$this->default_timeout = $timeout;
	}

	/**
	 * Set user agent
	 *
	 * @param string $user_agent User agent string.
	 */
	public function setUserAgent( string $user_agent ): void {
		$this->user_agent = sanitize_text_field( $user_agent );
	}

	/**
	 * Create basic auth header
	 *
	 * @param string $username Username.
	 * @param string $password Password.
	 * @return array
	 */
	public function createBasicAuthHeader( string $username, string $password ): array {
		return array(
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
		);
	}

	/**
	 * Create bearer token header
	 *
	 * @param string $token Bearer token.
	 * @return array
	 */
	public function createBearerTokenHeader( string $token ): array {
		return array(
			'Authorization' => 'Bearer ' . sanitize_text_field( $token ),
		);
	}
} 