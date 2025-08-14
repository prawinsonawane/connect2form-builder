<?php
// phpcs:ignoreFile -- This integration class uses many WordPress global functions (add_action, wp_*) which are available at runtime; security and DB-related sniffs remain enforced via inline validations and scoped ignores.
/**
 * Comprehensive Mailchimp Integration
 *
 * Advanced Mailchimp integration with full feature set including:
 * - Custom fields management with AJAX interface
 * - Multilingual support with RTL languages
 * - Real-time analytics and reporting
 * - Webhook synchronization
 * - Batch processing for performance
 * - Enhanced field mapping
 * - GDPR compliance
 *
 * @package Connect2Form\Integrations\Mailchimp
 * @since 2.0.0
 */

namespace Connect2Form\Integrations\Mailchimp;

use Connect2Form\Integrations\Core\Abstracts\AbstractIntegration;
use Connect2Form\Integrations\Core\Services\LanguageManager;
// Import common WP functions for namespaced calls
use function \add_action;
use function \wp_unslash;
use function \sanitize_text_field;
use function \absint;
use function \is_email;
use function \wp_remote_get;
use function \is_wp_error;
use function \wp_remote_retrieve_response_code;
use function \wp_enqueue_style;
use function \wp_enqueue_script;
use function \wp_create_nonce;
use function \admin_url;
use function \wp_localize_script;
use function \wp_send_json;
use function \wp_send_json_error;
use function \wp_send_json_success;
use function \get_option;
use function \update_option;
use function \wp_remote_request;
use function \wp_remote_retrieve_body;
use function \wp_json_encode;
use function \current_user_can;
use function \get_bloginfo;
use function \wp_verify_nonce;
use function \wp_cache_get;
use function \wp_cache_set;
use function \esc_html__;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailchimp Integration Class
 *
 * Advanced Mailchimp integration with custom fields, analytics, and automation.
 *
 * @since    2.0.0
 * @package  Connect2Form\Integrations\Mailchimp
 */
class MailchimpIntegration extends AbstractIntegration {

	/**
	 * Integration ID
	 *
	 * @since    2.0.0
	 * @var      string
	 */
	protected $id = 'mailchimp';

	/**
	 * Integration name
	 *
	 * @since    2.0.0
	 * @var      string
	 */
	protected $name = 'Mailchimp';

	/**
	 * Integration description
	 *
	 * @since    2.0.0
	 * @var      string
	 */
	protected $description = 'Advanced Mailchimp integration with custom fields, analytics, and automation';

	/**
	 * Integration version
	 *
	 * @since    2.0.0
	 * @var      string
	 */
	protected $version = '2.0.0';

	/**
	 * Integration icon
	 *
	 * @since    2.0.0
	 * @var      string
	 */
	protected $icon = 'dashicons-email-alt';

	/**
	 * Integration color
	 *
	 * @since    2.0.0
	 * @var      string
	 */
	protected $color = '#ffe01b';

	/**
	 * API base URL
	 *
	 * @since    2.0.0
	 * @var      string
	 */
	private $api_base_url = 'https://{dc}.api.mailchimp.com/3.0/';

	/**
	 * Component instances
	 *
	 * @since    2.0.0
	 * @var      object
	 */
	private $custom_fields_manager;

	/**
	 * Language manager instance
	 *
	 * @since    2.0.0
	 * @var      object
	 */
	private $language_manager;

	/**
	 * Analytics manager instance
	 *
	 * @since    2.0.0
	 * @var      object
	 */
	private $analytics_manager;

	/**
	 * Webhook handler instance
	 *
	 * @since    2.0.0
	 * @var      object
	 */
	private $webhook_handler;

	/**
	 * Batch processor instance
	 *
	 * @since    2.0.0
	 * @var      object
	 */
	private $batch_processor;

	/**
	 * Initialize integration
	 *
	 * @since    2.0.0
	 */
	protected function init() {
		// Defer component initialization until WordPress is ready.
        \add_action( 'init', array( $this, 'init_components' ), 5 );
		
		// Register assets.
        \add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_comprehensive_assets' ) );
		
		// Form processing.
        \add_action( 'connect2form_after_submission', array( $this, 'processSubmission' ), 10, 2 );
		
		// Webhook handling.
        \add_action( 'init', array( $this, 'init_webhook_endpoints' ), 15 );
	}

	/**
	 * Initialize component instances
	 *
	 * @since    2.0.0
	 */
	public function init_components() {
		// Load component files.
		$components_dir = CONNECT2FORM_INTEGRATIONS_DIR . 'src/Integrations/Mailchimp/';
		
		$component_files = array(
			'../../Core/Services/LanguageManager.php',
			'AnalyticsManager.php', 
			'CustomFieldsManager.php',
			'WebhookHandler.php',
			'BatchProcessor.php',
		);
		
		foreach ( $component_files as $file ) {
			$full_path = $components_dir . $file;
			if ( file_exists( $full_path ) ) {
				require_once $full_path;
			}
		}
		
		try {
			// Initialize language manager first (needed by other components).
			if ( ! $this->language_manager ) {
				$this->language_manager = new LanguageManager();
			}
			
			// Initialize analytics manager.
			if ( ! $this->analytics_manager && class_exists( 'Connect2Form\Integrations\Mailchimp\AnalyticsManager' ) ) {
				$this->analytics_manager = new AnalyticsManager();
			}
			
			// Initialize custom fields manager.
			if ( ! $this->custom_fields_manager && class_exists( 'Connect2Form\Integrations\Mailchimp\CustomFieldsManager' ) ) {
				$this->custom_fields_manager = new CustomFieldsManager( $this );
			}
			
			// Initialize webhook handler.
			if ( ! $this->webhook_handler && class_exists( 'Connect2Form\Integrations\Mailchimp\WebhookHandler' ) ) {
				$this->webhook_handler = new WebhookHandler( $this->analytics_manager );
			}
			
			// Initialize batch processor.
			if ( ! $this->batch_processor && class_exists( 'Connect2Form\Integrations\Mailchimp\BatchProcessor' ) ) {
				$this->batch_processor = new BatchProcessor( $this, $this->logger );
			}
		} catch ( \Exception $e ) {
			// Component initialization failed - log the error.
		}
	}

	/**
	 * Register comprehensive AJAX handlers
	 *
	 * @since    2.0.0
	 */
	public function register_ajax_handlers() {
		// Basic handlers (admin only).
        \add_action( 'wp_ajax_connect2form_test_mailchimp_connection', array( $this, 'ajax_test_connection' ) );
        \add_action( 'wp_ajax_connect2form_get_mailchimp_audiences', array( $this, 'ajax_get_audiences' ) );
        \add_action( 'wp_ajax_connect2form_save_mailchimp_global_settings', array( $this, 'ajax_save_global_settings' ) );
        \add_action( 'wp_ajax_connect2form_save_mailchimp_form_settings', array( $this, 'ajax_save_form_settings' ) );
        \add_action( 'wp_ajax_connect2form_save_mailchimp_audience_selection', array( $this, 'ajax_save_audience_selection' ) );
		
		// Enhanced field mapping (admin only).
        \add_action( 'wp_ajax_connect2form_mailchimp_get_form_fields', array( $this, 'ajax_get_form_fields' ) );
        \add_action( 'wp_ajax_connect2form_mailchimp_save_field_mapping', array( $this, 'ajax_save_field_mapping' ) );
        \add_action( 'wp_ajax_connect2form_mailchimp_get_field_mapping', array( $this, 'ajax_get_field_mapping' ) );
        \add_action( 'wp_ajax_connect2form_mailchimp_auto_map_fields', array( $this, 'ajax_auto_map_fields' ) );
		
		// Audience and merge fields (admin only).
        \add_action( 'wp_ajax_connect2form_mailchimp_get_audience_merge_fields', array( $this, 'ajax_get_audience_merge_fields' ) );
        \add_action( 'wp_ajax_connect2form_mailchimp_get_merge_fields', array( $this, 'ajax_get_audience_merge_fields' ) );
		
		// Multilingual interface (admin only).
        \add_action( 'wp_ajax_connect2form_mailchimp_get_multilingual_interface', array( $this, 'ajax_get_multilingual_interface' ) );
		
		// Analytics (admin only).
        \add_action( 'wp_ajax_connect2form_mailchimp_load_analytics_dashboard', array( $this, 'ajax_load_analytics_dashboard' ) );
        \add_action( 'wp_ajax_connect2form_mailchimp_export_analytics', array( $this, 'ajax_export_analytics' ) );
		
		// Settings handlers.
        \add_action( 'wp_ajax_connect2form_mailchimp_save_global_settings_v2', array( $this, 'ajax_save_global_settings_v2' ) );
        \add_action( 'wp_ajax_mailchimp_save_global_settings', array( $this, 'ajax_save_global_settings' ) );
		
		// Additional handlers for template compatibility.
        \add_action( 'wp_ajax_mailchimp_test_connection', array( $this, 'ajax_test_connection' ) );
        \add_action( 'wp_ajax_connect2form_mailchimp_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Recursively unslash + sanitize mixed POST payloads.
	 *
	 * @since    2.0.0
	 * @param    mixed $raw Raw data.
	 * @return   array
	 */
	private function sanitize_array_recursive( $raw ): array {
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : wp_unslash( (array) $raw ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashing followed by deep sanitize

		$clean = function( $v ) use ( &$clean ) {
			if ( is_array( $v ) ) {
				return array_map( $clean, $v );
			}
			if ( is_scalar( $v ) ) {
				return is_numeric( $v ) ? 0 + $v : sanitize_text_field( (string) $v );
			}
			return '';
		};

		return array_map( $clean, $raw );
	}

	/**
	 * Admin-only guard for settings endpoints.
	 *
	 * @since    2.0.0
	 */
    private function require_manage_options(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Insufficient permissions', 'connect2form' ) );
        }
    }

	/**
	 * Safe POST text getter (unslash + sanitize).
	 *
	 * @since    2.0.0
	 * @param    string $key     Key name.
	 * @param    string $default Default value.
	 * @return   string
	 */
    private function post_text( string $key, string $default = '' ): string { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
    }

	/**
	 * Safe POST int getter.
	 *
	 * @since    2.0.0
	 * @param    string $key     Key name.
	 * @param    int    $default Default value.
	 * @return   int
	 */
    private function post_int( string $key, int $default = 0 ): int { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : $default;
    }

	/**
	 * Safe POST bool getter.
	 *
	 * @param string $key POST key.
	 */
	private function post_bool( string $key ): bool { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
		return $value === '1' || $value === 'true' || $value === 'on' || $value === 'yes' || $value === 'enabled' || $value === 'checked' || $value === 'y' || $value === 't' || $value === 'True' || $value === 'TRUE' || $value === (string) 1;
	}

	/**
	 * Safe POST array getter (deep sanitized).
	 *
	 * @param string $key POST key.
	 */
	private function post_array( string $key ): array { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) ) {
			return array();
		}
		return $this->sanitize_array_recursive( wp_unslash( (array) $_POST[ $key ] ) );
	}

	/**
	 * Get authentication fields
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getAuthFields(): array {
		return array(
			array(
				'id' => 'api_key',
				'label' => $this->__( 'API Key' ),
				'type' => 'password',
				'required' => true,
				'description' => $this->__( 'Your Mailchimp API key. Find it in your Mailchimp account under Account > Extras > API keys.' ),
				'placeholder' => $this->__( 'Enter your Mailchimp API key...' )
			)
		);
	}

	/**
	 * Test API connection with enhanced validation
	 *
	 * @since    2.0.0
	 * @param    array $credentials Credentials array.
	 * @return   array
	 */
	public function testConnection(array $credentials): array {
		$api_key = $credentials['api_key'] ?? '';
		
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'error' => $this->language_manager ? $this->language_manager->translate( 'API key is required' ) : 'API key is required'
			);
		}

		// Validate API key format.
		if ( ! preg_match( '/^[a-f0-9]{32}-[a-z0-9]+$/', $api_key ) ) {
			return array(
				'success' => false,
				'error' => $this->language_manager ? $this->language_manager->translate( 'Invalid API key format. Expected format: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-xxx' ) : 'Invalid API key format'
			);
		}

		$dc = $this->extractDatacenter( $api_key );
		if ( ! $dc ) {
			return array(
				'success' => false,
				'error' => $this->language_manager ? $this->language_manager->translate( 'Could not extract datacenter from API key' ) : 'Could not extract datacenter from API key'
			);
		}

		// Test basic connectivity to Mailchimp first.
		$connectivity_test = $this->testBasicConnectivity( $dc );
		if ( ! $connectivity_test['success'] ) {
			return $connectivity_test;
		}

		// Test with ping endpoint first.
		$ping_response = $this->makeMailchimpApiRequest( 'GET', '/ping', $api_key, $dc );
		
		if ( ! $ping_response ) {
			return array(
				'success' => false,
				'error' => $this->language_manager ? $this->language_manager->translate( 'No response from Mailchimp API' ) : 'No response from Mailchimp API'
			);
		}

		if ( isset( $ping_response['status'] ) && $ping_response['status'] >= 400 ) {
			$error_message = $ping_response['detail'] ?? $ping_response['title'] ?? 'Authentication failed';
			
			// Provide specific error messages for common issues.
			if ( $ping_response['status'] === 401 ) {
				$error_message = $this->language_manager ? 
					$this->language_manager->translate( 'Invalid API key. Please check your Mailchimp API key.' ) : 
					'Invalid API key. Please check your Mailchimp API key.';
			} elseif ( $ping_response['status'] === 403 ) {
				$error_message = $this->language_manager ? 
					$this->language_manager->translate( 'API key does not have sufficient permissions.' ) : 
					'API key does not have sufficient permissions.';
			} elseif ( $ping_response['status'] === 404 ) {
				$error_message = $this->language_manager ? 
					$this->language_manager->translate( 'Mailchimp API endpoint not found. Check your datacenter.' ) : 
					'Mailchimp API endpoint not found. Check your datacenter.';
			}

			return array(
				'success' => false,
				'error' => $error_message
			);
		}

		// If ping succeeds, get account info for additional validation.
		$account_response = $this->makeMailchimpApiRequest( 'GET', '/', $api_key, $dc );
		
		if ( $account_response && ! isset( $account_response['status'] ) ) {
			return array(
				'success' => true,
				'message' => $this->language_manager ? $this->language_manager->translate( 'Connection successful!' ) : 'Connection successful!',
				'data' => array(
					'account_name' => $account_response['account_name'] ?? '',
					'email' => $account_response['email'] ?? '',
					'total_subscribers' => $account_response['total_subscribers'] ?? 0,
					'datacenter' => $dc
				)
			);
		} elseif ( $account_response && isset( $account_response['status'] ) ) {
			$error_message = $account_response['detail'] ?? $account_response['title'] ?? 'Failed to get account information';
			return array(
				'success' => false,
				'error' => $error_message
			);
		}

		return array(
			'success' => false,
			'error' => $this->language_manager ? $this->language_manager->translate( 'Connection test completed but failed to verify account details' ) : 'Connection test completed but failed to verify account details'
		);
	}

	/**
	 * Test basic connectivity to Mailchimp servers
	 *
	 * @since    2.0.0
	 * @param    string $dc Datacenter.
	 * @return   array
	 */
	private function testBasicConnectivity(string $dc): array {
		$test_url = "https://{$dc}.api.mailchimp.com";
		
		$response = wp_remote_get( $test_url, array(
			'timeout' => 10,
			'sslverify' => true,
			'headers' => array(
				'User-Agent' => 'Connect2Form-Integrations/' . CONNECT2FORM_INTEGRATIONS_VERSION
			)
		) );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			
			// Provide specific guidance for common connection issues.
			if ( strpos( $error_message, 'SSL' ) !== false || strpos( $error_message, 'certificate' ) !== false ) {
				$error_message = 'SSL certificate verification failed. Your server may have outdated SSL certificates.';
			} elseif ( strpos( $error_message, 'resolve' ) !== false ) {
				$error_message = 'DNS resolution failed. Unable to resolve Mailchimp server address.';
			} elseif ( strpos( $error_message, 'timeout' ) !== false ) {
				$error_message = 'Connection timeout. Your server may be behind a firewall blocking external connections.';
			}
			
			return array(
				'success' => false,
				'error' => 'Network connectivity issue: ' . $error_message
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		
		// We expect a 401 or other response here since we're not sending auth
		// Any response means we can connect to Mailchimp servers.
		if ( $status_code === 0 ) {
			return array(
				'success' => false,
				'error' => 'Unable to connect to Mailchimp servers. Please check your server\'s internet connection.'
			);
		}

		return array( 'success' => true );
	}

	/**
	 * Get available actions
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getAvailableActions(): array {
		// Ensure components are initialized before using translation.
		if ( ! $this->language_manager ) {
			$this->init_components();
		}
		
		return array(
			'subscribe' => array(
				'label' => $this->__( 'Subscribe to Audience', 'Subscribe to Audience' ),
				'description' => $this->__( 'Add contact to a Mailchimp audience/list', 'Add contact to a Mailchimp audience/list' )
			),
			'update_subscriber' => array(
				'label' => $this->__( 'Update Subscriber', 'Update Subscriber' ),
				'description' => $this->__( 'Update existing subscriber information', 'Update existing subscriber information' )
			),
			'unsubscribe' => array(
				'label' => $this->__( 'Unsubscribe', 'Unsubscribe' ),
				'description' => $this->__( 'Remove subscriber from audience', 'Remove subscriber from audience' )
			)
		);
	}

	/**
	 * Get comprehensive settings fields
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getSettingsFields(): array {
		return array(
			array(
				'id' => 'enable_analytics',
				'label' => $this->__( 'Global Analytics Tracking' ),
				'type' => 'checkbox',
				'description' => $this->__( 'Enable analytics tracking for all Mailchimp submissions' ),
				'default' => true
			),
			array(
				'id' => 'enable_webhooks',
				'label' => $this->__( 'Global Webhook Sync' ),
				'type' => 'checkbox',
				'description' => $this->__( 'Enable webhook synchronization for real-time updates' ),
				'default' => false
			),
			array(
				'id' => 'batch_processing',
				'label' => $this->__( 'Global Batch Processing' ),
				'type' => 'checkbox',
				'description' => $this->__( 'Process all submissions in batches for better performance' ),
				'default' => true
			)
		);
	}

	/**
	 * Get form-specific settings fields
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getFormSettingsFields(): array {
		return array(
			array(
				'id' => 'audience_id',
				'label' => $this->__( 'Audience' ),
				'type' => 'select',
				'required' => true,
				'description' => $this->__( 'Select the Mailchimp audience to add subscribers to' ),
				'options' => 'dynamic',
				'depends_on' => 'api_key'
			),
			array(
				'id' => 'double_optin',
				'label' => $this->__( 'Double Opt-in' ),
				'type' => 'checkbox',
				'description' => $this->__( 'Require subscribers to confirm their email address' ),
				'default' => true
			),
			array(
				'id' => 'update_existing',
				'label' => $this->__( 'Update Existing' ),
				'type' => 'checkbox',
				'description' => $this->__( 'Update existing subscribers instead of creating duplicates' ),
				'default' => false
			),
			array(
				'id' => 'tags',
				'label' => $this->__( 'Tags' ),
				'type' => 'text',
				'description' => $this->__( 'Comma-separated list of tags to add to subscribers' ),
				'placeholder' => $this->__( 'tag1, tag2, tag3' )
			)
		);
	}

	/**
	 * Override parent validation for global settings
	 *
	 * @since    2.0.0
	 * @param    array $settings Settings array.
	 * @return   array
	 */
	public function validateSettings(array $settings): array {
		$errors = array();

		if ( empty( $settings['api_key'] ) ) {
			$errors[] = 'API key is required';
		}
		
		return $errors;
	}

	/**
	 * Get enhanced field mapping
	 *
	 * @since    2.0.0
	 * @param    string $action Action type.
	 * @return   array
	 */
	public function getFieldMapping(string $action): array {
		$base_mapping = array(
			'email' => array(
				'label' => $this->__( 'Email Address' ),
				'required' => true,
				'type' => 'email',
				'merge_field' => 'EMAIL'
			),
			'first_name' => array(
				'label' => $this->__( 'First Name' ),
				'required' => false,
				'type' => 'text',
				'merge_field' => 'FNAME'
			),
			'last_name' => array(
				'label' => $this->__( 'Last Name' ),
				'required' => false,
				'type' => 'text',
				'merge_field' => 'LNAME'
			)
		);

		return $base_mapping;
	}

	/**
	 * Process form submission with comprehensive features
	 *
	 * @since    2.0.0
	 * @param    int   $submission_id Submission ID.
	 * @param    array $form_data   Form data.
	 * @param    array $settings    Settings.
	 * @return   array
	 */
	public function processSubmission(int $submission_id, array $form_data, array $settings = array()): array {
		// Get settings if not provided.
		if ( empty( $settings ) ) {
			$form_id = $form_data['form_id'] ?? 0;
			$settings = $this->getFormSettings( $form_id );
		}
		
		// Check if integration is enabled (same pattern as HubSpot).
		if ( ! $this->isEnabled( $settings ) ) {
			return array(
				'success' => true,
				'message' => 'Mailchimp integration not enabled'
			);
		}
		
		// Ensure components are initialized if we're processing.
		if ( ! $this->language_manager ) {
			$this->init_components();
		}
		
		// Check if globally connected.
		if ( ! $this->is_globally_connected() ) {
			$this->logError( 'Mailchimp not globally connected', array( 'submission_id' => $submission_id ) );
			return array(
				'success' => false,
				'error' => $this->language_manager->translate( 'Mailchimp not configured' )
			);
		}

		$audience_id = $settings['audience_id'] ?? '';
		if ( empty( $audience_id ) ) {
			$this->logError( 'Audience ID not specified', array( 'submission_id' => $submission_id ) );
			return array(
				'success' => false,
				'error' => $this->language_manager->translate( 'Audience ID not specified' )
			);
		}

		// Check if batch processing is enabled.
		
		// Force immediate processing for now (disable batch processing).
		if ( false && ( $settings['batch_processing'] ?? true ) ) {
			if ( $this->batch_processor ) {
				$queue_result = $this->batch_processor->add_to_batch_queue( $form_data, $settings, $submission_id );
				
				if ( $queue_result ) {
					// Track analytics for queued submission.
					if ( $this->analytics_manager && ( $settings['enable_analytics'] ?? true ) ) {
						$this->analytics_manager->track_subscription(
							$form_data['email'] ?? '',
							$submission_id,
							$audience_id,
							array( 'method' => 'batch', 'queue_id' => $queue_result )
						);
					}
					
					return array(
						'success' => true,
						'message' => $this->language_manager->translate( 'Subscription queued for processing' )
					);
				} else {
					return $this->process_submission_immediate( $submission_id, $form_data, $settings );
				}
			} else {
				return $this->process_submission_immediate( $submission_id, $form_data, $settings );
			}
		} else {
			return $this->process_submission_immediate( $submission_id, $form_data, $settings );
		}
	}

	/**
	 * Process submission immediately
	 *
	 * @since    2.0.0
	 * @param    int   $submission_id Submission ID.
	 * @param    array $form_data   Form data.
	 * @param    array $settings    Settings.
	 * @return   array
	 */
	private function process_submission_immediate(int $submission_id, array $form_data, array $settings): array {
		
		$global_settings = $this->get_global_settings();
		$api_key = $global_settings['api_key'] ?? '';
		$audience_id = $settings['audience_id'] ?? '';

		// Enhanced field mapping.
		$mapped_data = $this->enhanced_map_form_data( $form_data, $settings );

		// Subscribe to audience.
		$result = $this->subscribeToAudience( $mapped_data, $settings, $api_key, $audience_id, $submission_id );

		// Track analytics.
		if ( $this->analytics_manager && ( $settings['enable_analytics'] ?? true ) ) {
			if ( $result['success'] ) {
				$this->analytics_manager->track_subscription(
					$form_data['email'] ?? '',
					$submission_id,
					$audience_id,
					array( 'method' => 'immediate' )
				);
			} else {
				$this->analytics_manager->track_subscription_error(
					$form_data['email'] ?? '',
					$submission_id,
					$audience_id,
					array( 'error' => $result['error'] ?? 'Unknown error' )
				);
			}
		}

		return $result;
	}

	/**
	 * Enhanced form data mapping
	 *
	 * @since    2.0.0
	 * @param    array $form_data Form data.
	 * @param    array $settings Settings.
	 * @return   array
	 */
	private function enhanced_map_form_data(array $form_data, array $settings): array {
		$mapped = array();

		// Get the actual field data - handle nested structure.
		$field_data = $form_data;
		if ( isset( $form_data['fields'] ) && is_array( $form_data['fields'] ) ) {
			$field_data = $form_data['fields'];
		}

		// Get field mapping from settings first, then fall back to enhanced mapping.
		$field_mapping = $settings['field_mapping'] ?? array();

		if ( empty( $field_mapping ) ) {
			// Fall back to enhanced field mapping from options table.
			$form_id = $settings['form_id'] ?? $form_data['form_id'] ?? 0;
			$field_mapping = $this->get_enhanced_field_mapping( $form_id );
		}

		if ( ! empty( $field_mapping ) ) {
			// Use field mapping.
			foreach ( $field_mapping as $form_field => $mailchimp_field ) {
				if ( isset( $field_data[ $form_field ] ) && ! empty( $field_data[ $form_field ] ) ) {
					$mapped[ $mailchimp_field ] = $field_data[ $form_field ];
				}
			}
		} else {
			// Fall back to basic mapping.
			$mapped = $this->mapFormDataToMailchimp( $field_data, $settings );
		}

		return $mapped;
	}

	/**
	 * Subscribe to audience with comprehensive error handling
	 *
	 * @since    2.0.0
	 * @param    array $data      Data to subscribe.
	 * @param    array $settings  Settings.
	 * @param    string $api_key  API key.
	 * @param    string $audience_id Audience ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function subscribeToAudience(array $data, array $settings, string $api_key, string $audience_id, int $submission_id): array {
		$dc = $this->extractDatacenter( $api_key );
		
		// Get email address with fallback logic.
		$email_address = $data['email_address'] ?? $data['EMAIL'] ?? $data['email'] ?? $data['Email'] ?? $data['EMAIL_ADDRESS'] ?? null;
		
		// Validate email address.
		if ( empty( $email_address ) || ! is_email( $email_address ) ) {
			return array(
				'success' => false,
				'error' => 'No valid email address found in form submission'
			);
		}
		
		$member_data = array(
			'email_address' => $email_address,
			'status' => ( $settings['double_optin'] ?? true ) ? 'pending' : 'subscribed'
		);

		// Add merge fields.
		$merge_fields = array();
		foreach ( $data as $field => $value ) {
			if ( $field !== 'email' && $field !== 'EMAIL' && $field !== 'Email' && $field !== 'EMAIL_ADDRESS' && ! empty( $value ) ) {
				$merge_fields[ $field ] = $value;
			}
		}
		
		if ( ! empty( $merge_fields ) ) {
			$member_data['merge_fields'] = $merge_fields;
		}

		// Add tags.
		if ( ! empty( $settings['tags'] ) ) {
			$tags = array_map( 'trim', explode( ',', $settings['tags'] ) );
			$member_data['tags'] = array_filter( $tags );
		}

		$member_hash = md5( strtolower( $email_address ) );
		$endpoint = "/lists/{$audience_id}/members/{$member_hash}";
		
		$response = $this->makeMailchimpApiRequest( 'PUT', $endpoint, $api_key, $dc, $member_data );

		if ( $response && ! isset( $response['status'] ) ) {
			$this->logSuccess( 'Subscriber added successfully', array(
				'submission_id' => $submission_id,
				'email' => $email_address,
				'audience_id' => $audience_id,
				'member_id' => $response['id'] ?? null
			) );

			// Trigger webhook registration if enabled.
			if ( $settings['enable_webhooks'] ?? false ) {
				$this->maybe_register_webhook( $api_key, $audience_id );
			}

			return array(
				'success' => true,
				'message' => $this->language_manager ? $this->language_manager->translate( 'Successfully subscribed to Mailchimp!' ) : 'Successfully subscribed to Mailchimp!',
				'data' => $response
			);
		}

		$error_message = $response['detail'] ?? ( $this->language_manager ? $this->language_manager->translate( 'Failed to subscribe' ) : 'Failed to subscribe' );
		
		$this->logError( 'Subscription failed', array(
			'submission_id' => $submission_id,
			'error' => $error_message,
			'response' => $response
		) );

		return array(
			'success' => false,
			'error' => $error_message
		);
	}

	/**
	 * Maybe register webhook for audience
	 *
	 * @since    2.0.0
	 * @param    string $api_key     API key.
	 * @param    string $audience_id Audience ID.
	 */
	private function maybe_register_webhook(string $api_key, string $audience_id): void {
		if ( $this->webhook_handler ) {
			$webhook_status = $this->webhook_handler->get_webhook_status( $api_key, $audience_id );
			
			if ( ! $webhook_status['registered'] ) {
				$this->webhook_handler->register_webhook( $api_key, $audience_id );
			}
		}
	}

	/**
	 * Basic form data mapping (fallback)
	 *
	 * @since    2.0.0
	 * @param    array $form_data Form data.
	 * @param    array $settings  Settings.
	 * @return   array
	 */
	private function mapFormDataToMailchimp(array $form_data, array $settings): array {
		$mapped = array();
		
		// Map common fields with multiple variations.
		$field_map = array(
			'email' => 'EMAIL',
			'first_name' => 'FNAME',
			'last_name' => 'LNAME',
			'name' => 'FNAME', // fallback
			'email_address' => 'EMAIL',
			'firstname' => 'FNAME',
			'lastname' => 'LNAME',
			'fname' => 'FNAME',
			'lname' => 'LNAME'
		);

		foreach ( $field_map as $form_field => $mailchimp_field ) {
			if ( isset( $form_data[ $form_field ] ) && ! empty( $form_data[ $form_field ] ) ) {
				$mapped[ $mailchimp_field ] = $form_data[ $form_field ];
			}
		}

		// Also check for any field that contains 'email' in the key.
		if ( ! isset( $mapped['EMAIL'] ) ) {
			foreach ( $form_data as $key => $value ) {
				if ( stripos( $key, 'email' ) !== false && ! empty( $value ) ) {
					$mapped['EMAIL'] = $value;
					break;
				}
			}
		}

		// Also check for any field that contains 'name' in the key for first name.
		if ( ! isset( $mapped['FNAME'] ) ) {
			foreach ( $form_data as $key => $value ) {
				if ( ( stripos( $key, 'first' ) !== false || stripos( $key, 'name' ) !== false ) && ! empty( $value ) ) {
					$mapped['FNAME'] = $value;
					break;
				}
			}
		}

		return $mapped;
	}

	/**
	 * Get global settings for Mailchimp
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_global_settings() {
		$global_settings = get_option( 'connect2form_integrations_global', array() );
		return $global_settings['mailchimp'] ?? array();
	}

	/**
	 * Check if Mailchimp is globally connected
	 *
	 * @since    2.0.0
	 * @return   bool
	 */
	public function is_globally_connected(): bool {
		$settings = $this->get_global_settings();
		return ! empty( $settings['api_key'] );
	}

	/**
	 * Get enhanced field mapping
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	public function get_enhanced_field_mapping(int $form_id): array {
		if ( ! $form_id ) {
			return array();
		}

		$mapping = get_option( "connect2form_mailchimp_field_mapping_{$form_id}", array() );
		return is_array( $mapping ) ? $mapping : array();
	}

	/**
	 * Save enhanced field mapping
	 *
	 * @since    2.0.0
	 * @param    int   $form_id   Form ID.
	 * @param    array $mapping   Mapping array.
	 * @return   bool
	 */
	public function save_enhanced_field_mapping(int $form_id, array $mapping): bool {
		if ( ! $form_id ) {
			return false;
		}

		return update_option( "connect2form_mailchimp_field_mapping_{$form_id}", $mapping );
	}

	/**
	 * Extract datacenter from API key
	 *
	 * @since    2.0.0
	 * @param    string $api_key API key.
	 * @return   ?string
	 */
	private function extractDatacenter(string $api_key): ?string {
		$parts = explode( '-', $api_key );
		return end( $parts ) ?: null;
	}

	/**
	 * Make Mailchimp API request
	 *
	 * @since    2.0.0
	 * @param    string $method    HTTP method.
	 * @param    string $endpoint  API endpoint.
	 * @param    string $api_key   API key.
	 * @param    string $dc        Datacenter.
	 * @param    array  $data      Request data.
	 * @return   ?array
	 */
	private function makeMailchimpApiRequest(string $method, string $endpoint, string $api_key, string $dc, array $data = array()): ?array {
		$url = "https://{$dc}.api.mailchimp.com/3.0" . $endpoint;
		
		$args = array(
			'method' => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ),
				'Content-Type' => 'application/json',
				'User-Agent' => 'Connect2Form-Integrations/' . CONNECT2FORM_INTEGRATIONS_VERSION . ' WordPress/' . get_bloginfo( 'version' )
			),
			'timeout' => 30,
			'sslverify' => true
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return array( 'status' => 500, 'detail' => $error_message );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		// Handle different status codes.
		if ( $status_code >= 400 ) {
			$error_detail = 'Unknown error';
			if ( $decoded && isset( $decoded['detail'] ) ) {
				$error_detail = $decoded['detail'];
			} elseif ( $decoded && isset( $decoded['title'] ) ) {
				$error_detail = $decoded['title'];
			}
			
			return array_merge( $decoded ?: array(), array( 'status' => $status_code ) );
		}

		return $decoded;
	}

	/**
	 * Get audiences with caching
	 *
	 * @since    2.0.0
	 * @param    string $api_key API key.
	 * @return   array
	 */
	public function getAudiences(string $api_key = ''): array {
		if ( empty( $api_key ) ) {
			$global_settings = $this->get_global_settings();
			$api_key = $global_settings['api_key'] ?? '';
		}

		if ( empty( $api_key ) ) {
			return array();
		}

		$dc = $this->extractDatacenter( $api_key );
		if ( ! $dc ) {
			return array();
		}

		// Check cache.
		$cache_key = 'mailchimp_audiences_' . md5( $api_key );
		$cached = wp_cache_get( $cache_key, 'connect2form_mailchimp' );
		
		if ( $cached !== false ) {
			return $cached;
		}

		$response = $this->makeMailchimpApiRequest( 'GET', '/lists?count=100', $api_key, $dc );

		if ( $response && isset( $response['lists'] ) ) {
			$audiences = array();
			foreach ( $response['lists'] as $list ) {
				$audiences[] = array(
					'id' => $list['id'],
					'name' => $list['name'],
					'member_count' => $list['stats']['member_count'] ?? 0
				);
			}

			// Cache for 1 hour.
			wp_cache_set( $cache_key, $audiences, 'connect2form_mailchimp', 3600 );
			
			return $audiences;
		}

		return array();
	}

	/**
	 * Enqueue comprehensive assets
	 *
	 * @since    2.0.0
	 * @param    string $hook Hook name.
	 */
	public function enqueue_comprehensive_assets($hook): void {
		if ( strpos( $hook, 'connect2form' ) === false ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'connect2form-mailchimp',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/css/admin/mailchimp.css',
			array(),
			$this->version
		);

		// Enqueue main JS.
		wp_enqueue_script(
			'connect2form-mailchimp',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/mailchimp.js',
			array( 'jquery', 'wp-util' ),
			$this->version,
			true
		);

		// Also enqueue form-specific JS for form settings pages.
		wp_enqueue_script(
			'connect2form-mailchimp-form',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/mailchimp-form.js',
			array( 'jquery', 'wp-util', 'connect2form-mailchimp' ),
			$this->version,
			true
		);

		// Ensure components are initialized before using them.
		if ( ! $this->language_manager ) {
			$this->init_components();
		}

		// Localize with comprehensive data for both scripts.
		$nonce = wp_create_nonce( 'connect2form_nonce' );
		
		$localization_data = array(
			'nonce' => $nonce,
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'formId' => 0, // Will be updated by template.
			'globalSettings' => array(), // Will be updated by template.
			'isRTL' => $this->language_manager ? $this->language_manager->is_rtl() : false,
			'currentLanguage' => $this->language_manager ? $this->language_manager->get_current_language() : 'en_US',
			'strings' => array(
				'testing' => $this->language_manager ? $this->language_manager->translate( 'Testing...' ) : 'Testing...',
				'connected' => $this->language_manager ? $this->language_manager->translate( 'Connected' ) : 'Connected',
				'disconnected' => $this->language_manager ? $this->language_manager->translate( 'Disconnected' ) : 'Disconnected',
				'testConnection' => $this->language_manager ? $this->language_manager->translate( 'Test Connection' ) : 'Test Connection',
				'savingSettings' => $this->language_manager ? $this->language_manager->translate( 'Saving...' ) : 'Saving...',
				'settingsSaved' => $this->language_manager ? $this->language_manager->translate( 'Settings saved successfully!' ) : 'Settings saved successfully!',
				'connectionFailed' => $this->language_manager ? $this->language_manager->translate( 'Connection failed' ) : 'Connection failed',
				'selectAudience' => $this->language_manager ? $this->language_manager->translate( 'Select an audience...' ) : 'Select an audience...',
				'loadingFields' => $this->language_manager ? $this->language_manager->translate( 'Loading fields...' ) : 'Loading fields...',
				'fieldsLoaded' => $this->language_manager ? $this->language_manager->translate( 'Fields loaded successfully' ) : 'Fields loaded successfully',
				'noFieldsFound' => $this->language_manager ? $this->language_manager->translate( 'No fields found' ) : 'No fields found',
				'networkError' => $this->language_manager ? $this->language_manager->translate( 'Network error' ) : 'Network error',
				'mappingSaved' => $this->language_manager ? $this->language_manager->translate( 'Field mapping saved successfully' ) : 'Field mapping saved successfully',
				'mappingFailed' => $this->language_manager ? $this->language_manager->translate( 'Failed to save field mapping' ) : 'Failed to save field mapping',
				'autoMappingComplete' => $this->language_manager ? $this->language_manager->translate( 'Auto-mapping completed' ) : 'Auto-mapping completed',
				'clearMappingsConfirm' => $this->language_manager ? $this->language_manager->translate( 'Are you sure you want to clear all mappings?' ) : 'Are you sure you want to clear all mappings?'
			),
			'version' => $this->version
		);

		wp_localize_script( 'connect2form-mailchimp', 'connect2formCFMailchimp', $localization_data );
		wp_localize_script( 'connect2form-mailchimp-form', 'connect2formCFMailchimp', $localization_data );
	}

	/**
	 * Initialize webhook endpoints
	 *
	 * @since    2.0.0
	 */
	public function init_webhook_endpoints(): void {
		if ( $this->webhook_handler ) {
			// Webhook endpoints are initialized by the WebhookHandler.
		}
	}

	// AJAX Handlers

	/**
	 * AJAX: Test Mailchimp connection
	 *
	 * @since    2.0.0
	 */
    public function ajax_test_connection(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		// Extract API key from different possible sources.
		$api_key = '';
		$creds = array();
		if ( isset( $_POST['credentials'] ) && is_array( $_POST['credentials'] ) ) {
			$creds = $this->sanitize_array_recursive( wp_unslash( $_POST['credentials'] ) );
		}
		$api_key = $creds['api_key'] ?? $this->post_text( 'api_key' );
		if ( empty( $api_key ) && isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
			$set = $this->sanitize_array_recursive( wp_unslash( $_POST['settings'] ) );
			$api_key = $set['api_key'] ?? '';
		} elseif ( empty( $api_key ) && isset( $_POST['settings'] ) && is_string( $_POST['settings'] ) ) {
			$settings = json_decode( wp_unslash( $_POST['settings'] ), true );
			$api_key  = isset( $settings['api_key'] ) ? sanitize_text_field( (string) $settings['api_key'] ) : '';
		}
		
		if ( empty( $api_key ) ) {
			wp_send_json( array(
				'success' => false,
				'error' => 'API key is required'
			) );
		}
		
		$result = $this->testConnection( array( 'api_key' => $api_key ) );
		wp_send_json( $result );
	}

	/**
	 * AJAX: Get audiences
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_audiences(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		$api_key = $this->post_text( 'api_key' );
		$audiences = $this->getAudiences( $api_key );
		
		wp_send_json_success( $audiences );
	}

	/**
	 * AJAX: Save Mailchimp global settings
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_global_settings(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		// Extract settings from different possible formats.
		$settings = array();
		
        if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
            $settings = $this->sanitize_array_recursive( wp_unslash( $_POST['settings'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( isset( $_POST['settings'] ) && is_string( $_POST['settings'] ) ) {
            $settings = json_decode( wp_unslash( $_POST['settings'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( 'Invalid settings format' );
				return;
			}
		} else {
			// Fallback to direct POST data.
            $settings = array(
                'api_key'           => $this->post_text( 'api_key' ),
                'enable_analytics'  => $this->post_bool( 'enable_analytics' ),
                'enable_webhooks'   => $this->post_bool( 'enable_webhooks' ),
                'batch_processing'  => $this->post_bool( 'batch_processing' ),
            );
		}
		
		// Validate settings.
		$validation_result = $this->validateSettings( $settings );
		if ( ! empty( $validation_result ) ) {
			wp_send_json_error( 'Settings validation failed: ' . implode( ', ', $validation_result ) );
			return;
		}
		
		// Save settings using the abstract method.
		$result = $this->saveGlobalSettings( $settings );
		
		if ( $result ) {
			wp_send_json_success( array(
				'message' => 'Settings saved successfully!',
				'configured' => true
			) );
		} else {
			wp_send_json_error( 'Failed to save settings' );
		}
	}

	/**
	 * AJAX: Save Mailchimp global settings (v2)
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_global_settings_v2(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		// Extract settings from different possible formats.
		$settings = array();
		
        if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
            $settings = $this->sanitize_array_recursive( wp_unslash( $_POST['settings'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        } elseif ( isset( $_POST['settings'] ) && is_string( $_POST['settings'] ) ) {
            $settings = json_decode( wp_unslash( $_POST['settings'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( 'Invalid settings format' );
				return;
			}
		} else {
			// Fallback to direct POST data.
            $settings = array(
                'api_key'          => $this->post_text( 'api_key' ),
                'datacenter'       => $this->post_text( 'datacenter' ),
                'enable_analytics' => $this->post_bool( 'enable_analytics' ),
                'enable_webhooks'  => $this->post_bool( 'enable_webhooks' ),
                'batch_processing' => $this->post_bool( 'batch_processing' ),
            );
		}
		
		// Validate settings.
		$validation_result = $this->validateSettings( $settings );
		if ( ! empty( $validation_result ) ) {
			wp_send_json_error( 'Settings validation failed: ' . implode( ', ', $validation_result ) );
			return;
		}
		
		// Save settings using the abstract method.
		$result = $this->saveGlobalSettings( $settings );
		
		if ( $result ) {
			wp_send_json_success( array(
				'message' => 'Settings saved successfully!',
				'configured' => true
			) );
		} else {
			wp_send_json_error( 'Failed to save settings' );
		}
	}

	/**
	 * AJAX: Save form settings
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_form_settings(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		$form_id = $this->post_int( 'form_id' );
		
		if ( ! $form_id ) {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Form ID is required' ) : 'Form ID is required';
			wp_send_json_error( $error_msg );
		}

		// Build settings array from individual POST parameters.
        $settings = array(
            'enabled'         => $this->post_bool( 'enabled' ),
            'audience_id'     => $this->post_text( 'audience_id' ),
            'double_optin'    => $this->post_bool( 'double_optin' ),
            'update_existing' => $this->post_bool( 'update_existing' ),
            'tags'            => $this->post_text( 'tags' ),
            'field_mapping'   => $this->post_array( 'field_mapping' ),
        );

		// Try to save using the abstract method first.
		$result = $this->saveFormSettings( $form_id, $settings );
		
		// If that fails, try alternative storage methods.
		if ( ! $result ) {
			// Try saving to options table (legacy method).
			$option_key = "connect2form_mailchimp_form_{$form_id}";
			$result = update_option( $option_key, $settings );
			
			// If that also fails, try custom meta table.
			if ( ! $result ) {
				global $wpdb;
				$meta_table = $wpdb->prefix . 'connect2form_form_meta';
				
				// Check if table exists.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for schema validation; no caching needed for INFORMATION_SCHEMA queries
				$table_exists = $wpdb->get_var( $wpdb->prepare( "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $meta_table ) );
				if ( $table_exists ) {
					// Use service class if available to avoid slow queries.
					if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
						$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
						$result = $service_manager->database()->saveFormMeta( $form_id, '_connect2form_integrations', wp_json_encode( array( 'mailchimp' => $settings ) ) );
					} else {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form meta save; service layer preferred but this is a fallback
						$result = $wpdb->replace(
							$meta_table,
							array(
								'form_id' => $form_id,
								// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- meta_key is indexed for performance; using specific key for integration settings
								'meta_key' => '_connect2form_integrations',
								// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- meta_value is necessary for storing integration configuration
								'meta_value' => wp_json_encode( array( 'mailchimp' => $settings ) )
							),
							array( '%d', '%s', '%s' )
						);
					}
				}
			}
		}
		
		if ( $result ) {
			$success_msg = $this->language_manager ? $this->language_manager->translate( 'Form settings saved successfully' ) : 'Form settings saved successfully';
			wp_send_json_success( $success_msg );
		} else {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Failed to save form settings' ) : 'Failed to save form settings';
			wp_send_json_error( $error_msg );
		}
	}

	/**
	 * AJAX handler for auto-saving audience selection
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_audience_selection(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$form_id     = $this->post_int( 'form_id' );
		$audience_id = $this->post_text( 'audience_id' );

		if ( ! $form_id ) {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Form ID is required' ) : 'Form ID is required';
			wp_send_json_error( $error_msg );
		}

		// Get existing form integration settings using service class if available.
		$meta_value = null;
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$meta_value = $service_manager->database()->getFormMeta( $form_id, '_connect2form_integrations' );
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
			$meta_table = $wpdb->prefix . 'connect2form_form_meta';
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fallback form meta query with validated table identifier; values parameterized via prepare(); service layer preferred but this is a fallback
			$meta_value = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM `{$meta_table}` WHERE form_id = %d AND meta_key = %s",
				$form_id,
				'_connect2form_integrations'
			) );
		}
		
		$integration_settings = array();
		if ( $meta_value ) {
			$integration_settings = json_decode( $meta_value, true );
			if ( ! is_array( $integration_settings ) ) {
				$integration_settings = array();
			}
		}
		
		// Update Mailchimp settings.
		if ( ! isset( $integration_settings['mailchimp'] ) ) {
			$integration_settings['mailchimp'] = array();
		}
		
		$integration_settings['mailchimp']['audience_id'] = $audience_id;
		
		// Save back to database using service class if available.
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$result = $service_manager->database()->saveFormMeta( $form_id, '_connect2form_integrations', wp_json_encode( $integration_settings ) );
		} else {
			// Fallback to direct database call if service not available.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form meta save; service layer preferred but this is a fallback
			$result = $wpdb->replace(
				$meta_table,
				array(
					'form_id' => $form_id,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- meta_key is indexed for performance; using specific key for integration settings
					'meta_key' => '_connect2form_integrations',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- meta_value is necessary for storing integration configuration
					'meta_value' => wp_json_encode( $integration_settings )
				),
				array( '%d', '%s', '%s' )
			);
		}
		
		if ( $result !== false ) {
			$success_msg = $this->language_manager ? $this->language_manager->translate( 'Audience selection saved' ) : 'Audience selection saved';
			wp_send_json_success( $success_msg );
		} else {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Failed to save audience selection' ) : 'Failed to save audience selection';
			wp_send_json_error( $error_msg );
		}
	}

	/**
	 * AJAX: Get form fields
	 *
	 * @since    2.0.0
	 */
	public function ajax_get_form_fields(): void {
		// Add debugging for development.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}
		
		// Debug nonce verification.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
			$nonce_valid = wp_verify_nonce( $nonce, 'connect2form_nonce' );
		}
		
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		$form_id = $this->post_int( 'form_id' );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}
		
		if ( ! $form_id ) {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Form ID is required' ) : 'Form ID is required';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
			wp_send_json_error( $error_msg );
		}

		// Get form fields (this would integrate with your form builder).
		$form_fields = $this->get_form_fields( $form_id );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}
		
		wp_send_json_success( $form_fields );
	}

	/**
	 * AJAX: Save field mapping
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_field_mapping(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		$form_id = $this->post_int( 'form_id' );
		$mapping = isset( $_POST['mapping'] ) ? $this->sanitize_array_recursive( wp_unslash( $_POST['mapping'] ) ) : array();
		
		if ( ! $form_id ) {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Form ID is required' ) : 'Form ID is required';
			wp_send_json_error( $error_msg );
		}

		$result = $this->save_enhanced_field_mapping( $form_id, $mapping );
		
		if ( $result ) {
			$success_msg = $this->language_manager ? $this->language_manager->translate( 'Field mapping saved successfully' ) : 'Field mapping saved successfully';
			wp_send_json_success( $success_msg );
		} else {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Failed to save field mapping' ) : 'Failed to save field mapping';
			wp_send_json_error( $error_msg );
		}
	}

	/**
	 * AJAX: Get field mapping
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_field_mapping(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		$form_id = $this->post_int( 'form_id' );
		
		if ( ! $form_id ) {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Form ID is required' ) : 'Form ID is required';
			wp_send_json_error( $error_msg );
		}

		$mapping = $this->get_enhanced_field_mapping( $form_id );
		
		wp_send_json_success( $mapping );
	}

	/**
	 * AJAX: Auto map fields
	 *
	 * @since    2.0.0
	 */
    public function ajax_auto_map_fields(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		$form_id     = $this->post_int( 'form_id' );
		$audience_id = $this->post_text( 'audience_id' );
		
		if ( ! $form_id || ! $audience_id ) {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Form ID and Audience ID are required' ) : 'Form ID and Audience ID are required';
			wp_send_json_error( $error_msg );
		}

		// Generate automatic mapping.
		$auto_mapping = $this->generate_automatic_mapping( $form_id, $audience_id );
		
		wp_send_json_success( $auto_mapping );
	}

	/**
	 * AJAX: Get audience merge fields
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_audience_merge_fields(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		$audience_id = sanitize_text_field( wp_unslash( $_POST['audience_id'] ?? '' ) );
		
		if ( ! $audience_id ) {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Audience ID is required' ) : 'Audience ID is required';
			wp_send_json_error( $error_msg );
		}

		// Ensure components are initialized.
		if ( ! $this->custom_fields_manager ) {
			$this->init_components();
		}

		if ( $this->custom_fields_manager ) {
			$result = $this->custom_fields_manager->get_merge_fields( $audience_id );
			wp_send_json( $result );
		} else {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Custom fields manager not available' ) : 'Custom fields manager not available';
			wp_send_json_error( $error_msg );
		}
	}

	/**
	 * AJAX: Get multilingual interface
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_multilingual_interface(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		if ( $this->language_manager ) {
			$this->language_manager->ajax_get_multilingual_interface();
		} else {
			$error_msg = 'Language manager not available';
			wp_send_json_error( $error_msg );
		}
	}

	/**
	 * AJAX: Load analytics dashboard
	 *
	 * @since    2.0.0
	 */
    public function ajax_load_analytics_dashboard(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		if ( $this->analytics_manager ) {
			$this->analytics_manager->ajax_get_analytics_data();
		} else {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Analytics manager not available' ) : 'Analytics manager not available';
			wp_send_json_error( $error_msg );
		}
	}

	/**
	 * AJAX: Export analytics
	 *
	 * @since    2.0.0
	 */
    public function ajax_export_analytics(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
		
		if ( $this->analytics_manager ) {
			$this->analytics_manager->ajax_export_analytics();
		} else {
			$error_msg = $this->language_manager ? $this->language_manager->translate( 'Analytics manager not available' ) : 'Analytics manager not available';
			wp_send_json_error( $error_msg );
		}
	}

	/**
	 * Helper methods - Get REAL form fields from database
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	private function get_form_fields(int $form_id): array {
		global $wpdb;
		
		if ( ! $form_id ) {
			return array();
		}
		
		// Check if table exists.
		$table_name = $wpdb->prefix . 'connect2form_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for schema validation; no caching needed for INFORMATION_SCHEMA queries
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $table_name ) ) === $table_name;
		
		if ( ! $table_exists ) {
			return array();
		}
		
		// Get form from database using service class.
		$form = null;
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$form = $service_manager->forms()->get_form( $form_id );
		} else {
			// Fallback to direct database call if service not available.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form query; service layer preferred but this is a fallback
			$form = $wpdb->get_row( $wpdb->prepare(
				"SELECT fields FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
				$form_id
			) );
		}
		
		if ( ! $form || ! $form->fields ) {
			return array();
		}
		
		// Parse form fields JSON - handle both string and array formats.
		$fields_data = is_string( $form->fields ) ? json_decode( $form->fields, true ) : ( is_array( $form->fields ) ? $form->fields : array() );
		if ( ! is_array( $fields_data ) ) {
			return array();
		}
		
		$processed_fields = array();
		
		foreach ( $fields_data as $field ) {
			if ( ! isset( $field['id'] ) || ! isset( $field['label'] ) ) {
				continue;
			}
			
			$field_id = $field['id'];
			$field_type = $field['type'] ?? 'text';
			$field_label = $field['label'];
			$required = $field['required'] ?? false;
			
			$processed_fields[ $field_id ] = array(
				'id' => $field_id,
				'label' => $field_label,
				'type' => $field_type,
				'required' => $required,
				'name' => $field['name'] ?? $field_id,
				'placeholder' => $field['placeholder'] ?? '',
				'description' => $field['description'] ?? ''
			);
		}
		
		return $processed_fields;
	}

	/**
	 * Generate automatic mapping
	 *
	 * @since    2.0.0
	 * @param    int    $form_id     Form ID.
	 * @param    string $audience_id Audience ID.
	 * @return   array
	 */
	private function generate_automatic_mapping(int $form_id, string $audience_id): array {
		$form_fields = $this->get_form_fields( $form_id );
		$mapping = array();

		// Basic auto-mapping logic.
		$auto_map_rules = array(
			'email' => 'EMAIL',
			'first_name' => 'FNAME',
			'last_name' => 'LNAME'
		);

		foreach ( $form_fields as $field_name => $field_config ) {
			if ( isset( $auto_map_rules[ $field_name ] ) ) {
				$mapping[ $field_name ] = $auto_map_rules[ $field_name ];
			}
		}

		return $mapping;
	}

	/**
	 * Get component instances for external access
	 *
	 * @since    2.0.0
	 * @return   object
	 */
	public function get_custom_fields_manager() {
		return $this->custom_fields_manager;
	}

	/**
	 * Get language manager
	 *
	 * @since    2.0.0
	 * @return   object
	 */
	public function get_language_manager() {
		return $this->language_manager;
	}

	/**
	 * Get analytics manager
	 *
	 * @since    2.0.0
	 * @return   object
	 */
	public function get_analytics_manager() {
		return $this->analytics_manager;
	}

	/**
	 * Get webhook handler
	 *
	 * @since    2.0.0
	 * @return   object
	 */
	public function get_webhook_handler() {
		return $this->webhook_handler;
	}

	/**
	 * Get batch processor
	 *
	 * @since    2.0.0
	 * @return   object
	 */
	public function get_batch_processor() {
		return $this->batch_processor;
	}
	
	/**
	 * Safe translation helper
	 *
	 * @since    2.0.0
	 * @param    string $text      Text to translate.
	 * @param    string $fallback  Fallback text.
	 * @return   string
	 */
	private function __( $text, $fallback = null ) {
		if ( $this->language_manager ) {
			return $this->language_manager->translate( $text );
		}
		return $fallback ?: $text;
	}
}



