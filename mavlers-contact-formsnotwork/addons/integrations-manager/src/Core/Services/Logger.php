<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * Logger Service
 * 
 * Handles logging of integration activities
 */
class Logger {

	/**
	 * Database table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'mavlers_cf_integration_logs';
	}

	/**
	 * Log a message
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		// Check if logging is enabled
		$settings = get_option( 'mavlers_cf_integrations_settings', array() );
		if ( empty( $settings['enable_logging'] ) ) {
			return;
		}

		// Insert log entry into database
		$this->insert_log_entry( $level, $message, $context );

		// Also log to WordPress error log for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[Mavlers CF Integrations] [{$level}] {$message}" );
		}
	}

	/**
	 * Log info message
	 *
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log error message
	 *
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log success message
	 *
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 */
	public function success( string $message, array $context = array() ): void {
		$this->log( 'success', $message, $context );
	}

	/**
	 * Log integration activity
	 *
	 * @param string $integration_id Integration ID.
	 * @param int    $form_id       Form ID.
	 * @param string $status        Status.
	 * @param string $message       Message.
	 * @param array  $data          Additional data.
	 * @param int    $submission_id Submission ID.
	 */
	public function logIntegration( string $integration_id, int $form_id, string $status, string $message, array $data = array(), ?int $submission_id = null ): void {
		global $wpdb;

		$wpdb->insert(
			$this->table_name,
			array(
				'form_id' => $form_id,
				'submission_id' => $submission_id,
				'integration_id' => sanitize_text_field( $integration_id ),
				'status' => sanitize_text_field( $status ),
				'message' => sanitize_textarea_field( $message ),
				'data' => wp_json_encode( $data ),
				'created_at' => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Get logs for integration
	 *
	 * @param string $integration_id Integration ID.
	 * @param int    $limit         Number of logs to retrieve.
	 * @param int    $offset        Offset for pagination.
	 * @return array
	 */
	public function getLogs( string $integration_id = '', int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$where = '';
		$params = array();

		if ( ! empty( $integration_id ) ) {
			$where = 'WHERE integration_id = %s';
			$params[] = sanitize_text_field( $integration_id );
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			array_merge( $params, array( $limit, $offset ) )
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get log statistics
	 *
	 * @param string $integration_id Integration ID.
	 * @param int    $days          Number of days to look back.
	 * @return array
	 */
	public function getStats( string $integration_id = '', int $days = 30 ): array {
		global $wpdb;

		$where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
		$params = array();

		if ( ! empty( $integration_id ) ) {
			$where .= ' AND integration_id = %s';
			$params[] = sanitize_text_field( $integration_id );
		}

		$sql = "SELECT 
					status,
					COUNT(*) as count
				FROM {$this->table_name} 
				{$where}
				GROUP BY status";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$stats = array(
			'success' => 0,
			'error' => 0,
			'warning' => 0,
			'total' => 0,
		);

		foreach ( $results as $row ) {
			$stats[ $row['status'] ] = (int) $row['count'];
			$stats['total'] += (int) $row['count'];
		}

		return $stats;
	}

	/**
	 * Clear old logs
	 *
	 * @return int Number of deleted logs.
	 */
	public function clearOldLogs(): int {
		global $wpdb;

		$settings = get_option( 'mavlers_cf_integrations_settings', array() );
		$retention_days = $settings['log_retention_days'] ?? 30;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);

		return $deleted ?: 0;
	}

	/**
	 * Delete logs for specific integration
	 *
	 * @param string $integration_id Integration ID.
	 * @return int Number of deleted logs.
	 */
	public function deleteLogs( string $integration_id ): int {
		global $wpdb;

		$deleted = $wpdb->delete(
			$this->table_name,
			array( 'integration_id' => sanitize_text_field( $integration_id ) ),
			array( '%s' )
		);

		return $deleted ?: 0;
	}

	/**
	 * Insert log entry into database
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 */
	private function insert_log_entry( string $level, string $message, array $context ): void {
		global $wpdb;

		// Extract integration and form info from context
		$integration_id = $context['integration_id'] ?? '';
		$form_id = $context['form_id'] ?? 0;
		$submission_id = $context['submission_id'] ?? null;

		$wpdb->insert(
			$this->table_name,
			array(
				'form_id' => $form_id,
				'submission_id' => $submission_id,
				'integration_id' => sanitize_text_field( $integration_id ),
				'status' => sanitize_text_field( $level ),
				'message' => sanitize_textarea_field( $message ),
				'data' => wp_json_encode( $context ),
				'created_at' => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}
} 