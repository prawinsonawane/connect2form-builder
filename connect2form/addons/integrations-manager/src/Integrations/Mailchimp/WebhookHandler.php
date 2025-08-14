<?php
/**
 * Mailchimp Webhook Handler
 * 
 * Handles real-time webhook events from Mailchimp for synchronization and analytics
 * 
 * @package Connect2Form\Integrations\Mailchimp
 * @since 1.0.0
 */

namespace Connect2Form\Integrations\Mailchimp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailchimp Webhook Handler Class
 *
 * @since    1.0.0
 */
class WebhookHandler {
	
	/**
	 * Webhook endpoint URL
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private $webhook_endpoint = 'connect2form-mailchimp-webhook';
	
	/**
	 * Supported webhook events
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $supported_events = array(
		'subscribe',
		'unsubscribe',
		'profile',
		'cleaned',
		'upemail',
		'campaign'
	);
	
	/**
	 * Security secret for webhook verification
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private $webhook_secret;
	
	/**
	 * Analytics manager instance
	 *
	 * @since    1.0.0
	 * @var      object
	 */
	private $analytics_manager;
	
	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 * @param    object $analytics_manager Analytics manager instance.
	 */
	public function __construct($analytics_manager = null) {
		$this->analytics_manager = $analytics_manager;
		$this->webhook_secret = $this->get_webhook_secret();
		
		$this->init_hooks();
	}
	
	/**
	 * Initialize WordPress hooks
	 *
	 * @since    1.0.0
	 */
	private function init_hooks() {
		// Register webhook endpoint.
		add_action( 'init', array( $this, 'add_webhook_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_webhook_request' ) );
		
		// AJAX handlers for webhook management.
		add_action( 'wp_ajax_mailchimp_register_webhook', array( $this, 'ajax_register_webhook' ) );
		add_action( 'wp_ajax_mailchimp_unregister_webhook', array( $this, 'ajax_unregister_webhook' ) );
		add_action( 'wp_ajax_mailchimp_test_webhook', array( $this, 'ajax_test_webhook' ) );
		add_action( 'wp_ajax_mailchimp_get_webhook_status', array( $this, 'ajax_get_webhook_status' ) );
		add_action( 'wp_ajax_mailchimp_get_webhook_logs', array( $this, 'ajax_get_webhook_logs' ) );
		
		// Webhook processing actions.
		add_action( 'connect2form_mailchimp_webhook_subscribe', array( $this, 'process_subscribe_event' ), 10, 2 );
		add_action( 'connect2form_mailchimp_webhook_unsubscribe', array( $this, 'process_unsubscribe_event' ), 10, 2 );
		add_action( 'connect2form_mailchimp_webhook_profile', array( $this, 'process_profile_event' ), 10, 2 );
		add_action( 'connect2form_mailchimp_webhook_cleaned', array( $this, 'process_cleaned_event' ), 10, 2 );
		add_action( 'connect2form_mailchimp_webhook_upemail', array( $this, 'process_upemail_event' ), 10, 2 );
		add_action( 'connect2form_mailchimp_webhook_campaign', array( $this, 'process_campaign_event' ), 10, 2 );
	}
	
	/**
	 * Add webhook endpoint
	 *
	 * @since    1.0.0
	 */
	public function add_webhook_endpoint() {
		add_rewrite_rule(
			'^' . $this->webhook_endpoint . '/?$',
			'index.php?connect2form_mailchimp_webhook=1',
			'top'
		);
		
		add_filter( 'query_vars', function($vars) {
			$vars[] = 'connect2form_mailchimp_webhook';
			return $vars;
		} );
	}
	
	/**
	 * Handle webhook request
	 *
	 * @since    1.0.0
	 */
	public function handle_webhook_request() {
		if ( ! get_query_var( 'connect2form_mailchimp_webhook' ) ) {
			return;
		}
		
		// Only accept POST requests.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- superglobal sanitized immediately
		$req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( $req_method !== 'POST' ) {
			http_response_code( 405 );
			exit( 'Method Not Allowed' );
		}
		
		// Get raw post data.
		$raw_data = file_get_contents( 'php://input' );
		$data = json_decode( $raw_data, true );
		
		// Log webhook request for debugging.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_SERVER is inspected and sanitized inside logger helpers
		$this->log_webhook_request( $raw_data, $_SERVER );
		
		// Verify webhook authenticity.
		if ( ! $this->verify_webhook_signature( $raw_data ) ) {
			http_response_code( 401 );
			exit( 'Unauthorized' );
		}
		
		// Process webhook data.
		if ( $data && isset( $data['type'] ) && isset( $data['data'] ) ) {
			$this->process_webhook_event( $data['type'], $data['data'] );
		}
		
		// Return 200 OK to acknowledge receipt.
		http_response_code( 200 );
		exit( 'OK' );
	}
	
	/**
	 * Verify webhook signature
	 *
	 * @since    1.0.0
	 * @param    string $raw_data Raw webhook data.
	 * @return   bool
	 */
	private function verify_webhook_signature($raw_data) {
		// Check if signature verification is enabled.
		if ( ! $this->webhook_secret ) {
			return true; // Skip verification if no secret is set.
		}
		
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- header sanitized immediately
		$signature = isset( $_SERVER['HTTP_X_MC_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_MC_SIGNATURE'] ) ) : '';
		if ( ! $signature ) {
			return false;
		}
		
		// Calculate expected signature.
		$expected_signature = base64_encode( hash_hmac( 'sha1', $raw_data, $this->webhook_secret, true ) );
		
		return hash_equals( $expected_signature, $signature );
	}
	
	/**
	 * Process webhook event
	 *
	 * @since    1.0.0
	 * @param    string $event_type Event type.
	 * @param    array  $event_data Event data.
	 */
	private function process_webhook_event($event_type, $event_data) {
		// Validate event type.
		if ( ! in_array( $event_type, $this->supported_events, true ) ) {
			$this->log_webhook_error( "Unsupported event type: {$event_type}", $event_data );
			return;
		}
		
		try {
			// Trigger specific event action.
			do_action( "connect2form_mailchimp_webhook_{$event_type}", $event_data, $event_type );
			
			// Track analytics if manager is available.
			if ( $this->analytics_manager ) {
				$this->analytics_manager->track_webhook_event( $event_type, $event_data );
			}
			
			// Log successful processing.
			$this->log_webhook_success( $event_type, $event_data );
			
		} catch ( \Exception $e ) {
			$this->log_webhook_error( "Error processing {$event_type} event: " . $e->getMessage(), $event_data );
		}
	}
	
	/**
	 * Process subscribe event
	 *
	 * @since    1.0.0
	 * @param    array  $event_data Event data.
	 * @param    string $event_type Event type.
	 */
	public function process_subscribe_event($event_data, $event_type) {
		$email = $event_data['email'] ?? '';
		$list_id = $event_data['list_id'] ?? '';
		
		if ( ! $email || ! $list_id ) {
			return;
		}
		
		// Update local subscriber status.
		$this->update_subscriber_status( $email, $list_id, 'subscribed', $event_data );
		
		// Trigger custom actions.
		do_action( 'connect2form_mailchimp_subscriber_added', $email, $list_id, $event_data );
	}
	
	/**
	 * Process unsubscribe event
	 *
	 * @since    1.0.0
	 * @param    array  $event_data Event data.
	 * @param    string $event_type Event type.
	 */
	public function process_unsubscribe_event($event_data, $event_type) {
		$email = $event_data['email'] ?? '';
		$list_id = $event_data['list_id'] ?? '';
		$reason = $event_data['reason'] ?? 'unknown';
		
		if ( ! $email || ! $list_id ) {
			return;
		}
		
		// Update local subscriber status.
		$this->update_subscriber_status( $email, $list_id, 'unsubscribed', $event_data );
		
		// Trigger custom actions.
		do_action( 'connect2form_mailchimp_subscriber_removed', $email, $list_id, $reason, $event_data );
	}
	
	/**
	 * Process profile update event
	 *
	 * @since    1.0.0
	 * @param    array  $event_data Event data.
	 * @param    string $event_type Event type.
	 */
	public function process_profile_event($event_data, $event_type) {
		$email = $event_data['email'] ?? '';
		$list_id = $event_data['list_id'] ?? '';
		
		if ( ! $email || ! $list_id ) {
			return;
		}
		
		// Update local subscriber data.
		$this->update_subscriber_profile( $email, $list_id, $event_data );
		
		// Trigger custom actions.
		do_action( 'connect2form_mailchimp_subscriber_updated', $email, $list_id, $event_data );
	}
	
	/**
	 * Process cleaned email event
	 *
	 * @since    1.0.0
	 * @param    array  $event_data Event data.
	 * @param    string $event_type Event type.
	 */
	public function process_cleaned_event($event_data, $event_type) {
		$email = $event_data['email'] ?? '';
		$list_id = $event_data['list_id'] ?? '';
		$reason = $event_data['reason'] ?? 'unknown';
		
		if ( ! $email || ! $list_id ) {
			return;
		}
		
		// Update local subscriber status.
		$this->update_subscriber_status( $email, $list_id, 'cleaned', $event_data );
		
		// Trigger custom actions.
		do_action( 'connect2form_mailchimp_subscriber_cleaned', $email, $list_id, $reason, $event_data );
	}
	
	/**
	 * Process email change event
	 *
	 * @since    1.0.0
	 * @param    array  $event_data Event data.
	 * @param    string $event_type Event type.
	 */
	public function process_upemail_event($event_data, $event_type) {
		$old_email = $event_data['old_email'] ?? '';
		$new_email = $event_data['new_email'] ?? '';
		$list_id = $event_data['list_id'] ?? '';
		
		if ( ! $old_email || ! $new_email || ! $list_id ) {
			return;
		}
		
		// Update local records.
		$this->update_subscriber_email( $old_email, $new_email, $list_id, $event_data );
		
		// Trigger custom actions.
		do_action( 'connect2form_mailchimp_subscriber_email_changed', $old_email, $new_email, $list_id, $event_data );
	}
	
	/**
	 * Process campaign event
	 *
	 * @since    1.0.0
	 * @param    array  $event_data Event data.
	 * @param    string $event_type Event type.
	 */
	public function process_campaign_event($event_data, $event_type) {
		$campaign_id = $event_data['id'] ?? '';
		$list_id = $event_data['list_id'] ?? '';
		
		if ( ! $campaign_id || ! $list_id ) {
			return;
		}
		
		// Store campaign activity.
		$this->store_campaign_activity( $campaign_id, $list_id, $event_data );
		
		// Trigger custom actions.
		do_action( 'connect2form_mailchimp_campaign_activity', $campaign_id, $list_id, $event_data );
	}
	
	/**
	 * Update subscriber status
	 *
	 * @since    1.0.0
	 * @param    string $email      Email address.
	 * @param    string $list_id    List ID.
	 * @param    string $status     Status.
	 * @param    array  $event_data Event data.
	 */
	private function update_subscriber_status($email, $list_id, $status, $event_data) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'connect2form_mailchimp_subscribers';
		
		// Check if subscriber record exists.
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$existing = $service_manager->database()->getSubscriberRecord( $email, $list_id );
		} else {
			// Fallback to direct database call if service not available.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- webhook subscriber lookup; service layer preferred but this is a fallback
			// Validate identifier before interpolation
			if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! \c2f_is_valid_prefixed_table( $table_name ) ) {
				$existing = null;
			} else {
				$existing = $wpdb->get_row( $wpdb->prepare(
					"SELECT id FROM `{$table_name}` WHERE email = %s AND list_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated identifier
				$email,
				$list_id
				) );
			}
		}
		
		$data = array(
			'email' => $email,
			'list_id' => $list_id,
			'status' => $status,
			'last_updated' => current_time( 'mysql' ),
			'webhook_data' => wp_json_encode( $event_data )
		);
		
		if ( $existing ) {
			// Update existing record.
			if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
				$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
				$service_manager->database()->updateSubscriberStatus( $email, $list_id, $status, $event_data );
			} else {
				// Fallback to direct database call if service not available.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- webhook subscriber update; service layer preferred but this is a fallback
				$wpdb->update(
					$table_name,
					$data,
					array( 'id' => $existing->id )
				);
			}
		} else {
			// Insert new record.
			if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
				$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
				$service_manager->database()->updateSubscriberStatus( $email, $list_id, $status, $event_data );
			} else {
				// Fallback to direct database call if service not available.
				$data['created_at'] = current_time( 'mysql' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- webhook subscriber insert; service layer preferred but this is a fallback
				$wpdb->insert( $table_name, $data );
			}
		}
	}
	
	/**
	 * Update subscriber profile
	 *
	 * @since    1.0.0
	 * @param    string $email      Email address.
	 * @param    string $list_id    List ID.
	 * @param    array  $event_data Event data.
	 */
	private function update_subscriber_profile($email, $list_id, $event_data) {
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$service_manager->database()->updateSubscriberProfile( $email, $list_id, $event_data );
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
			
			$table_name = $wpdb->prefix . 'connect2form_mailchimp_subscribers';
			
			$merge_fields = wp_json_encode( $event_data['merges'] ?? array() );
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- webhook subscriber profile update; service layer preferred but this is a fallback
				$wpdb->update(
					$table_name,
				array(
					'merge_fields' => $merge_fields,
					'last_updated' => current_time( 'mysql' ),
					'webhook_data' => wp_json_encode( $event_data )
				),
				array(
					'email' => $email,
					'list_id' => $list_id
				)
			);
		}
	}
	
	/**
	 * Update subscriber email
	 *
	 * @since    1.0.0
	 * @param    string $old_email  Old email address.
	 * @param    string $new_email  New email address.
	 * @param    string $list_id    List ID.
	 * @param    array  $event_data Event data.
	 */
	private function update_subscriber_email($old_email, $new_email, $list_id, $event_data) {
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$service_manager->database()->updateSubscriberEmail( $old_email, $new_email, $list_id, $event_data );
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
			
			$table_name = $wpdb->prefix . 'connect2form_mailchimp_subscribers';
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- webhook email change update; service layer preferred but this is a fallback
				$wpdb->update(
					$table_name,
				array(
					'email' => $new_email,
					'last_updated' => current_time( 'mysql' ),
					'webhook_data' => wp_json_encode( $event_data )
				),
				array(
					'email' => $old_email,
					'list_id' => $list_id
				)
			);
		}
	}
	
	/**
	 * Store campaign activity
	 *
	 * @since    1.0.0
	 * @param    string $campaign_id Campaign ID.
	 * @param    string $list_id     List ID.
	 * @param    array  $event_data  Event data.
	 */
	private function store_campaign_activity($campaign_id, $list_id, $event_data) {
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$service_manager->database()->storeCampaignActivity( $campaign_id, $list_id, $event_data );
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
			
			$table_name = $wpdb->prefix . 'connect2form_mailchimp_campaigns';
			
			$data = array(
				'campaign_id' => $campaign_id,
				'list_id' => $list_id,
				'activity_type' => $event_data['type'] ?? 'unknown',
				'activity_data' => wp_json_encode( $event_data ),
				'created_at' => current_time( 'mysql' )
			);
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- webhook activity log insert; service layer preferred but this is a fallback
				$wpdb->insert( $table_name, $data );
		}
	}
	
	/**
	 * Register webhook with Mailchimp
	 *
	 * @since    1.0.0
	 * @param    string $api_key  Mailchimp API key.
	 * @param    string $list_id  List ID.
	 * @param    array  $events   Optional. Specific events to register.
	 * @return   array
	 */
	public function register_webhook($api_key, $list_id, $events = null) {
		if ( ! $events ) {
			$events = $this->supported_events;
		}
		
		$dc = $this->extract_datacenter( $api_key );
		$webhook_url = home_url( $this->webhook_endpoint );
		
		// Prepare webhook data.
		$webhook_data = array(
			'url' => $webhook_url,
			'events' => array_fill_keys( $events, true ),
			'sources' => array(
				'user' => true,
				'admin' => true,
				'api' => true
			)
		);
		
		// Add secret if available.
		if ( $this->webhook_secret ) {
			$webhook_data['secret'] = $this->webhook_secret;
		}
		
		// Make API request.
		$response = $this->make_mailchimp_request(
			'POST',
			"/lists/{$list_id}/webhooks",
			$api_key,
			$dc,
			$webhook_data
		);
		
		if ( isset( $response['id'] ) ) {
			// Store webhook ID for later reference.
			$this->save_webhook_id( $list_id, $response['id'] );
			return array( 'success' => true, 'webhook_id' => $response['id'] );
		} else {
			return array(
				'success' => false,
				'message' => $response['detail'] ?? 'Failed to register webhook'
			);
		}
	}
	
	/**
	 * Unregister webhook from Mailchimp
	 *
	 * @since    1.0.0
	 * @param    string $api_key  Mailchimp API key.
	 * @param    string $list_id  List ID.
	 * @return   array
	 */
	public function unregister_webhook($api_key, $list_id) {
		$webhook_id = $this->get_webhook_id( $list_id );
		
		if ( ! $webhook_id ) {
			return array( 'success' => false, 'message' => 'No webhook found for this list' );
		}
		
		$dc = $this->extract_datacenter( $api_key );
		
		// Make API request.
		$response = $this->make_mailchimp_request(
			'DELETE',
			"/lists/{$list_id}/webhooks/{$webhook_id}",
			$api_key,
			$dc
		);
		
		if ( ! isset( $response['status'] ) || $response['status'] < 400 ) {
			// Remove stored webhook ID.
			$this->remove_webhook_id( $list_id );
			return array( 'success' => true );
		} else {
			return array(
				'success' => false,
				'message' => $response['detail'] ?? 'Failed to unregister webhook'
			);
		}
	}
	
	/**
	 * Get webhook status
	 *
	 * @since    1.0.0
	 * @param    string $api_key  Mailchimp API key.
	 * @param    string $list_id  List ID.
	 * @return   array
	 */
	public function get_webhook_status($api_key, $list_id) {
		$webhook_id = $this->get_webhook_id( $list_id );
		
		if ( ! $webhook_id ) {
			return array( 'registered' => false );
		}
		
		$dc = $this->extract_datacenter( $api_key );
		
		// Get webhook details from Mailchimp.
		$response = $this->make_mailchimp_request(
			'GET',
			"/lists/{$list_id}/webhooks/{$webhook_id}",
			$api_key,
			$dc
		);
		
		if ( isset( $response['id'] ) ) {
			return array(
				'registered' => true,
				'webhook_id' => $response['id'],
				'url' => $response['url'],
				'events' => $response['events'],
				'sources' => $response['sources']
			);
		} else {
			// Webhook not found, clean up stored ID.
			$this->remove_webhook_id( $list_id );
			return array( 'registered' => false );
		}
	}
	
	/**
	 * Get webhook secret
	 *
	 * @since    1.0.0
	 * @return   string
	 */
	private function get_webhook_secret() {
		$secret = get_option( 'connect2form_mailchimp_webhook_secret' );
		
		if ( ! $secret ) {
			// Generate new secret.
			$secret = wp_generate_password( 32, false );
			update_option( 'connect2form_mailchimp_webhook_secret', $secret );
		}
		
		return $secret;
	}
	
	/**
	 * Save webhook ID
	 *
	 * @since    1.0.0
	 * @param    string $list_id    List ID.
	 * @param    string $webhook_id Webhook ID.
	 */
	private function save_webhook_id($list_id, $webhook_id) {
		$webhook_ids = get_option( 'connect2form_mailchimp_webhook_ids', array() );
		$webhook_ids[ $list_id ] = $webhook_id;
		update_option( 'connect2form_mailchimp_webhook_ids', $webhook_ids );
	}
	
	/**
	 * Get webhook ID
	 *
	 * @since    1.0.0
	 * @param    string $list_id List ID.
	 * @return   string|null
	 */
	private function get_webhook_id($list_id) {
		$webhook_ids = get_option( 'connect2form_mailchimp_webhook_ids', array() );
		return $webhook_ids[ $list_id ] ?? null;
	}
	
	/**
	 * Remove webhook ID
	 *
	 * @since    1.0.0
	 * @param    string $list_id List ID.
	 */
	private function remove_webhook_id($list_id) {
		$webhook_ids = get_option( 'connect2form_mailchimp_webhook_ids', array() );
		unset( $webhook_ids[ $list_id ] );
		update_option( 'connect2form_mailchimp_webhook_ids', $webhook_ids );
	}
	
	/**
	 * Extract datacenter from API key
	 *
	 * @since    1.0.0
	 * @param    string $api_key Mailchimp API key.
	 * @return   string
	 */
	private function extract_datacenter($api_key) {
		$parts = explode( '-', $api_key );
		return end( $parts );
	}
	
	/**
	 * Make Mailchimp API request
	 *
	 * @since    1.0.0
	 * @param    string $method   HTTP method.
	 * @param    string $endpoint API endpoint.
	 * @param    string $api_key  Mailchimp API key.
	 * @param    string $dc       Datacenter.
	 * @param    array  $data     Optional. Request data.
	 * @return   array
	 */
	private function make_mailchimp_request($method, $endpoint, $api_key, $dc, $data = array()) {
		$url = "https://{$dc}.api.mailchimp.com/3.0" . $endpoint;
		
		$args = array(
			'method' => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ),
				'Content-Type' => 'application/json'
			),
			'timeout' => 30
		);
		
		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
			$args['body'] = wp_json_encode( $data );
		}
		
		$response = wp_remote_request( $url, $args );
		
		if ( is_wp_error( $response ) ) {
			return array( 'status' => 500, 'detail' => $response->get_error_message() );
		}
		
		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}
	
	/**
	 * Log webhook request
	 *
	 * @since    1.0.0
	 * @param    string $raw_data Raw webhook data.
	 * @param    array  $server_data Server data.
	 */
	private function log_webhook_request($raw_data, $server_data) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		
		$log_data = array(
			'timestamp' => current_time( 'mysql' ),
			'method' => $server_data['REQUEST_METHOD'] ?? '',
			'headers' => $this->get_relevant_headers( $server_data ),
			'body' => $raw_data,
			'ip' => $server_data['REMOTE_ADDR'] ?? ''
		);
		
	}
	
	/**
	 * Log webhook success
	 *
	 * @since    1.0.0
	 * @param    string $event_type Event type.
	 * @param    array  $event_data Event data.
	 */
	private function log_webhook_success($event_type, $event_data) {
		$log_data = array(
			'timestamp' => current_time( 'mysql' ),
			'event_type' => $event_type,
			'status' => 'success',
			'email' => $event_data['email'] ?? '',
			'list_id' => $event_data['list_id'] ?? ''
		);
		
	}
	
	/**
	 * Log webhook error
	 *
	 * @since    1.0.0
	 * @param    string $message    Error message.
	 * @param    array  $event_data Optional. Event data.
	 */
	private function log_webhook_error($message, $event_data = array()) {
		$log_data = array(
			'timestamp' => current_time( 'mysql' ),
			'status' => 'error',
			'message' => $message,
			'event_data' => $event_data
		);
		
	}
	
	/**
	 * Get relevant headers for logging
	 *
	 * @since    1.0.0
	 * @param    array  $server_data Server data.
	 * @return   array
	 */
	private function get_relevant_headers($server_data) {
		$relevant_headers = array();
		$header_keys = array( 'HTTP_X_MC_SIGNATURE', 'HTTP_USER_AGENT', 'HTTP_X_FORWARDED_FOR' );
		
		foreach ( $header_keys as $key ) {
			if ( isset( $server_data[ $key ] ) ) {
				$relevant_headers[ $key ] = $server_data[ $key ];
			}
		}
		
		return $relevant_headers;
	}
	
	/**
	 * AJAX: Register webhook
	 *
	 * @since    1.0.0
	 */
	public function ajax_register_webhook() {
		check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		// Unslash then sanitize inputs
		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$list_id = sanitize_text_field( wp_unslash( $_POST['list_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		// Events can be an array; unslash and sanitize each, then whitelist against supported events
		if ( isset( $_POST['events'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw_events = (array) wp_unslash( $_POST['events'] );
			$events_sanitized = array_map( 'sanitize_text_field', $raw_events );
			$events = array_values( array_intersect( $events_sanitized, $this->supported_events ) );
			if ( empty( $events ) ) {
				$events = $this->supported_events;
			}
		} else {
			$events = $this->supported_events;
		}
		
		if ( ! $api_key || ! $list_id ) {
			wp_send_json_error( 'API key and list ID are required' );
		}
		
		$result = $this->register_webhook( $api_key, $list_id, $events );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}
	
	/**
	 * AJAX: Unregister webhook
	 *
	 * @since    1.0.0
	 */
	public function ajax_unregister_webhook() {
		check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$list_id = sanitize_text_field( wp_unslash( $_POST['list_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		
		if ( ! $api_key || ! $list_id ) {
			wp_send_json_error( 'API key and list ID are required' );
		}
		
		$result = $this->unregister_webhook( $api_key, $list_id );
		
		if ( $result['success'] ) {
			wp_send_json_success( 'Webhook unregistered successfully' );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}
	
	/**
	 * AJAX: Test webhook
	 *
	 * @since    1.0.0
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		// Simulate a webhook event for testing.
		$test_data = array(
			'type' => 'subscribe',
			'data' => array(
				'email' => 'test@example.com',
				'list_id' => 'test123',
				'merges' => array( 'FNAME' => 'Test', 'LNAME' => 'User' )
			)
		);
		
		$this->process_webhook_event( $test_data['type'], $test_data['data'] );
		
		wp_send_json_success( 'Test webhook processed successfully' );
	}
	
	/**
	 * AJAX: Get webhook status
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_webhook_status() {
		check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$list_id = sanitize_text_field( wp_unslash( $_POST['list_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		
		if ( ! $api_key || ! $list_id ) {
			wp_send_json_error( 'API key and list ID are required' );
		}
		
		$status = $this->get_webhook_status( $api_key, $list_id );
		
		wp_send_json_success( $status );
	}
	
	/**
	 * AJAX: Get webhook logs
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_webhook_logs() {
		check_ajax_referer( 'connect2form_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}
		
		// This would fetch logs from a log table or file.
		// For now, return sample data.
		$logs = array(
			array(
				'timestamp' => current_time( 'mysql' ),
				'event_type' => 'subscribe',
				'status' => 'success',
				'email' => 'user@example.com',
				'list_id' => 'abc123'
			)
		);
		
		wp_send_json_success( $logs );
	}
} 


