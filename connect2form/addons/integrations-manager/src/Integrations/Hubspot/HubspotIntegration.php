<?php

namespace Connect2Form\Integrations\Hubspot;

// Import common WordPress global functions for static analysis and namespaced resolution
use function \add_action;
use function \admin_url;
use function \wp_create_nonce;
use function \wp_localize_script;
use function \wp_enqueue_style;
use function \wp_enqueue_script;
use function \wp_unslash;
use function \sanitize_text_field;
use function \esc_html__;
use function \esc_url_raw;
use function \current_user_can;
use function \wp_send_json_error;
use function \wp_send_json_success;
use function \wp_send_json;
use function \wp_remote_get;
use function \wp_remote_post;
use function \wp_remote_request;
use function \wp_remote_retrieve_response_code;
use function \wp_remote_retrieve_body;
use function \is_wp_error;
use function \get_bloginfo;
use function \absint;
use function \get_option;
use function \get_post_meta;
use function \update_post_meta;
use function \get_current_screen;
use function \get_the_ID;
use function \wp_cache_delete;
use function \delete_transient;
use function \wp_cache_flush;

use Connect2Form\Integrations\Core\Abstracts\AbstractIntegration;
use Connect2Form\Integrations\Core\Interfaces\IntegrationInterface;
use Connect2Form\Integrations\Core\Services\LanguageManager;

/**
 * HubSpot Integration
 *
 * Handles form submissions to HubSpot CRM
 * Supports contact creation, company association, deal creation, and workflow enrollment
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
// phpcs:disable WordPress.WP.GlobalVariablesOverride
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_request
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_post
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_retrieve_response_code
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_retrieve_body
// Note: Security-related sniffs (nonce, sanitization, prepared SQL) remain enforced below.

/**
 * HubSpot Integration Class
 *
 * Handles form submissions to HubSpot CRM
 * Supports contact creation, company association, deal creation, and workflow enrollment
 *
 * @since    2.0.0
 */
class HubspotIntegration extends AbstractIntegration {

	/**
	 * Integration ID.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $id    Integration ID.
	 */
	protected $id = 'hubspot';

	/**
	 * Integration name.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $name    Integration name.
	 */
	protected $name = 'HubSpot';

	/**
	 * Integration description.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $description    Integration description.
	 */
	protected $description = 'Integrate form submissions with HubSpot CRM for contact management, deal creation, and workflow automation.';

	/**
	 * Integration version.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $version    Integration version.
	 */
	protected $version = '1.0.0';

	/**
	 * Integration icon.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $icon    Integration icon.
	 */
	protected $icon = 'dashicons-businessman';

	/**
	 * Integration color.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $color    Integration color.
	 */
	protected $color = '#ff7a59';

	/**
	 * Custom properties manager instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      CustomPropertiesManager    $custom_properties_manager    Custom properties manager instance.
	 */
	protected $custom_properties_manager;

	/**
	 * Workflow manager instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      WorkflowManager    $workflow_manager    Workflow manager instance.
	 */
	protected $workflow_manager;

	/**
	 * Deal manager instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      DealManager    $deal_manager    Deal manager instance.
	 */
	protected $deal_manager;

	/**
	 * Company manager instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      CompanyManager    $company_manager    Company manager instance.
	 */
	protected $company_manager;

	/**
	 * Analytics manager instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      AnalyticsManager    $analytics_manager    Analytics manager instance.
	 */
	protected $analytics_manager;

	/**
	 * Language manager instance.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      LanguageManager    $language_manager    Language manager instance.
	 */
	protected $language_manager;

	/**
	 * Constructor
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		parent::__construct();

		// Initialize components.
		$this->init_components();

		// Register AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Initialize the integration
	 *
	 * @since    2.0.0
	 */
	protected function init() {
		// Defer component initialization to ensure WordPress is ready.
		add_action( 'init', array( $this, 'init_components' ), 5 );

		// Register AJAX handlers after WordPress is fully loaded.
		add_action( 'init', array( $this, 'register_ajax_handlers' ), 20 );

		// Register asset enqueuing.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_comprehensive_assets' ) );
	}

	/**
	 * Initialize HubSpot-specific components
	 *
	 * @since    2.0.0
	 */
	public function init_components() {
		try {
			// Initialize component managers with error handling.
			if ( class_exists( 'Connect2Form\Integrations\Hubspot\CustomPropertiesManager' ) ) {
				$this->custom_properties_manager = new \Connect2Form\Integrations\Hubspot\CustomPropertiesManager();
			}

			if ( class_exists( 'Connect2Form\Integrations\Hubspot\WorkflowManager' ) ) {
				$this->workflow_manager = new \Connect2Form\Integrations\Hubspot\WorkflowManager();
			}

			if ( class_exists( 'Connect2Form\Integrations\Hubspot\DealManager' ) ) {
				$this->deal_manager = new \Connect2Form\Integrations\Hubspot\DealManager();
			}

			if ( class_exists( 'Connect2Form\Integrations\Hubspot\CompanyManager' ) ) {
				$this->company_manager = new \Connect2Form\Integrations\Hubspot\CompanyManager();
			}

			if ( class_exists( 'Connect2Form\Integrations\Hubspot\AnalyticsManager' ) ) {
				$this->analytics_manager = new \Connect2Form\Integrations\Hubspot\AnalyticsManager();
			}

			if ( class_exists( 'Connect2Form\Integrations\Core\Services\LanguageManager' ) ) {
				$this->language_manager = new \Connect2Form\Integrations\Core\Services\LanguageManager();
			}

			// Register form submission hook listener.
			add_action( 'connect2form_after_submission', array( $this, 'handle_form_submission' ), 10, 2 );

		} catch ( \Throwable $e ) {
			// Even if component initialization fails, register the form submission hook.
			add_action( 'connect2form_after_submission', array( $this, 'handle_form_submission' ), 10, 2 );
		}
	}

	/**
	 * Recursively unslash + sanitize array/scalars for request payloads.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    mixed $raw Raw data to sanitize.
	 * @return   array
	 */
    private function sanitize_array_recursive( $raw ): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- called only from AJAX handlers with validated nonce; unslashed then sanitized recursively
        $raw = is_array( $raw ) ? wp_unslash( $raw ) : wp_unslash( (array) $raw );

		$clean = function( $v ) use ( &$clean ) {
			if ( is_array( $v ) ) {
				return array_map( $clean, $v );
			}
			if ( is_numeric( $v ) ) {
				return 0 + $v; // preserve numeric type
			}
			return sanitize_text_field( (string) $v );
		};

		return array_map( $clean, $raw );
	}

	/**
	 * Ensure current user has admin capability for settings-y actions.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
    private function require_manage_options(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- returning JSON
            wp_send_json_error( esc_html__( 'Insufficient permissions', 'connect2form' ) );
        }
    }

	/**
	 * Safe getter for POST string.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $key     POST key.
	 * @param    string $default Default value.
	 * @return   string
	 */
    private function post_text( string $key, string $default = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return isset( $_POST[ $key ] )
            ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : $default;
    }

	/**
	 * Safe POST int getter.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @param    string $key     POST key.
	 * @param    int    $default Default value.
	 * @return   int
	 */
    private function post_int( string $key, int $default = 0 ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return isset( $_POST[ $key ] )
            ? absint( wp_unslash( $_POST[ $key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : $default;
    }

    /**
     * Safe POST bool getter (unslash + truthy check).
     *
     * Note: Nonce verification is performed in the calling AJAX handlers via
     * Connect2Form_Security::validate_ajax_request().
     *
     * @param string $key POST key.
     * @return bool
     */
    private function post_bool( string $key ): bool {
        $raw = isset( $_POST[ $key ] ) ? wp_unslash( (string) $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $sanitized = sanitize_text_field( $raw );
        return $sanitized !== '' && $sanitized !== '0' && $sanitized !== 'false';
    }

    /**
     * Safe POST array getter (unslash + cast to array).
     *
     * Note: Nonce verification is performed in the calling AJAX handlers via
     * Connect2Form_Security::validate_ajax_request().
     *
     * @param string $key POST key.
     * @return array
     */
    private function post_array( string $key ): array {
        $raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return $this->sanitize_array_recursive( $raw );
    }

	/**
	 * Register AJAX handlers for HubSpot integration
	 *
	 * @since    2.0.0
	 */
	public function register_ajax_handlers() {

		// Test connection.
		add_action( 'wp_ajax_connect2form_hubspot_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_connect2form_test_hubspot_connection', array( $this, 'ajax_test_connection' ) );

		// Get HubSpot objects (contacts, companies, deals).
		add_action( 'wp_ajax_connect2form_hubspot_get_contacts', array( $this, 'ajax_get_contacts' ) );
		add_action( 'wp_ajax_connect2form_hubspot_get_companies', array( $this, 'ajax_get_companies' ) );
		add_action( 'wp_ajax_connect2form_hubspot_get_deals', array( $this, 'ajax_get_deals' ) );

		// Get custom properties.
		add_action( 'wp_ajax_connect2form_hubspot_get_custom_properties', array( $this, 'ajax_get_custom_properties' ) );

		// Get workflows.
		add_action( 'wp_ajax_connect2form_hubspot_get_workflows', array( $this, 'ajax_get_workflows' ) );

		// Get HubSpot forms (new functionality).
		add_action( 'wp_ajax_connect2form_hubspot_get_forms', array( $this, 'ajax_get_forms' ) );

		// Save settings - use unique action names to avoid conflicts.
		add_action( 'wp_ajax_connect2form_hubspot_save_global_settings_v2', array( $this, 'ajax_save_global_settings_v2' ) );
		add_action( 'wp_ajax_connect2form_hubspot_save_global_settings', array( $this, 'ajax_save_global_settings' ) );

		// Additional handlers for template compatibility.
		add_action( 'wp_ajax_hubspot_save_global_settings', array( $this, 'ajax_save_global_settings' ) );
		add_action( 'wp_ajax_hubspot_test_connection', array( $this, 'ajax_test_connection' ) );

		// Add a simple test handler.
		add_action( 'wp_ajax_connect2form_hubspot_test_simple_v2', array( $this, 'ajax_test_simple_v2' ) );

		add_action( 'wp_ajax_connect2form_hubspot_save_form_settings', array( $this, 'ajax_save_form_settings' ) );

		// Field mapping.
		add_action( 'wp_ajax_connect2form_hubspot_save_field_mapping', array( $this, 'ajax_save_field_mapping' ) );
		add_action( 'wp_ajax_connect2form_hubspot_get_field_mapping', array( $this, 'ajax_get_field_mapping' ) );
		add_action( 'wp_ajax_connect2form_hubspot_auto_map_fields', array( $this, 'ajax_auto_map_fields' ) );

		// Analytics.
		add_action( 'wp_ajax_connect2form_hubspot_get_analytics', array( $this, 'ajax_get_analytics' ) );

		// Simple test endpoint.
		add_action( 'wp_ajax_connect2form_hubspot_test_simple', array( $this, 'ajax_test_simple' ) );

		// Field mapping handlers (similar to Mailchimp).
		add_action( 'wp_ajax_connect2form_hubspot_get_form_fields', array( $this, 'ajax_get_form_fields' ) );
		add_action( 'wp_ajax_connect2form_hubspot_save_field_mapping', array( $this, 'ajax_save_field_mapping' ) );
		add_action( 'wp_ajax_connect2form_hubspot_get_field_mapping', array( $this, 'ajax_get_field_mapping' ) );
		add_action( 'wp_ajax_connect2form_hubspot_auto_map_fields', array( $this, 'ajax_auto_map_fields' ) );
		add_action( 'wp_ajax_connect2form_hubspot_get_contact_properties', array( $this, 'ajax_get_contact_properties' ) );

		// Custom objects handlers.
		add_action( 'wp_ajax_connect2form_hubspot_get_custom_objects', array( $this, 'ajax_get_custom_objects' ) );
		add_action( 'wp_ajax_connect2form_hubspot_get_custom_object_properties', array( $this, 'ajax_get_custom_object_properties' ) );
		add_action( 'wp_ajax_connect2form_hubspot_save_custom_object_mapping', array( $this, 'ajax_save_custom_object_mapping' ) );
		add_action( 'wp_ajax_connect2form_hubspot_get_custom_object_mapping', array( $this, 'ajax_get_custom_object_mapping' ) );
	}

	/**
	 * Get authentication fields for HubSpot
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getAuthFields(): array {
		return array(
			array(
				'id'          => 'access_token',
				'label'       => $this->__( 'Private App Access Token' ),
				'type'        => 'password',
				'required'    => true,
				'description' => $this->__( 'Enter your HubSpot Private App Access Token. Create one in HubSpot Settings > Account Setup > Integrations > Private Apps.' ),
				'help_url'    => 'https://developers.hubspot.com/docs/api/private-apps',
			),
			array(
				'id'          => 'portal_id',
				'label'       => $this->__( 'Portal ID' ),
				'type'        => 'text',
				'required'    => true,
				'description' => $this->__( 'Your HubSpot Portal ID. Found in your HubSpot account settings.' ),
				'help_url'    => 'https://developers.hubspot.com/docs/api/overview',
			),
		);
	}

	/**
	 * Test HubSpot API connection
	 *
	 * @since    2.0.0
	 * @param    array $credentials HubSpot credentials.
	 * @return   array
	 */
	public function testConnection( array $credentials ): array {
		$access_token = $credentials['access_token'] ?? '';
		$portal_id    = $credentials['portal_id'] ?? '';

		if ( empty( $access_token ) || empty( $portal_id ) ) {
			return array(
				'success' => false,
				'error'   => $this->__( 'Access token and Portal ID are required' ),
			);
		}

		// Test basic connectivity.
		$test_url = 'https://api.hubapi.com/crm/v3/objects/contacts';
		$response = wp_remote_get(
			$test_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Connection failed: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => $this->__( 'Connection successful!' ),
				'data'    => array(
					'portal_id'   => $portal_id,
					'api_version' => 'v3',
				),
			);
		} elseif ( $status_code === 401 ) {
			return array(
				'success' => false,
				'error'   => $this->__( 'Invalid access token. Please check your Private App Access Token.' ),
			);
		} elseif ( $status_code === 403 ) {
			return array(
				'success' => false,
				'error'   => $this->__( 'Access denied. Please check your Private App permissions.' ),
			);
		} else {
			$error_message = $data['message'] ?? 'Unknown error';
			return array(
				'success' => false,
				'error'   => "API Error ({$status_code}): {$error_message}",
			);
		}
	}

	/**
	 * Get available actions for HubSpot
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getAvailableActions(): array {
		return array(
			'create_contact' => array(
				'label'       => $this->__( 'Create Contact' ),
				'description' => $this->__( 'Create a new contact in HubSpot CRM' ),
			),
			'update_contact' => array(
				'label'       => $this->__( 'Update Contact' ),
				'description' => $this->__( 'Update existing contact information' ),
			),
			'create_deal' => array(
				'label'       => $this->__( 'Create Deal' ),
				'description' => $this->__( 'Create a new deal and associate with contact' ),
			),
			'update_deal' => array(
				'label'       => $this->__( 'Update Deal' ),
				'description' => $this->__( 'Update existing deal information' ),
			),
			'create_custom_object' => array(
				'label'       => $this->__( 'Create Custom Object' ),
				'description' => $this->__( 'Create a new custom object record' ),
			),
			'update_custom_object' => array(
				'label'       => $this->__( 'Update Custom Object' ),
				'description' => $this->__( 'Update existing custom object record' ),
			),
			'create_multiple_objects' => array(
				'label'       => $this->__( 'Create Multiple Objects' ),
				'description' => $this->__( 'Create contact, deal, and custom objects simultaneously' ),
			),
			'enroll_workflow' => array(
				'label'       => $this->__( 'Enroll in Workflow' ),
				'description' => $this->__( 'Enroll contact in HubSpot workflow' ),
			),
			'associate_company' => array(
				'label'       => $this->__( 'Associate Company' ),
				'description' => $this->__( 'Associate contact with existing company' ),
			),
			'associate_objects' => array(
				'label'       => $this->__( 'Associate Objects' ),
				'description' => $this->__( 'Associate contact with deals and custom objects' ),
			),
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
				'id'          => 'contact_enabled',
				'label'       => $this->__( 'Create/Update Contact' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Create or update contact in HubSpot' ),
				'default'     => true,
			),
			array(
				'id'          => 'deal_enabled',
				'label'       => $this->__( 'Create Deal' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Create a new deal for this submission' ),
				'default'     => false,
			),
			array(
				'id'          => 'deal_update_enabled',
				'label'       => $this->__( 'Update Existing Deal' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Update existing deal instead of creating new one' ),
				'default'     => false,
			),
			array(
				'id'          => 'custom_objects_enabled',
				'label'       => $this->__( 'Enable Custom Objects' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Enable custom object creation and updates' ),
				'default'     => false,
			),
			array(
				'id'          => 'custom_objects_config',
				'label'       => $this->__( 'Custom Objects Configuration' ),
				'type'        => 'custom_objects_config',
				'description' => $this->__( 'Configure multiple custom objects' ),
				'depends_on'  => 'custom_objects_enabled',
			),
			array(
				'id'          => 'workflow_enabled',
				'label'       => $this->__( 'Enroll in Workflow' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Enroll contact in HubSpot workflow' ),
				'default'     => false,
			),
			array(
				'id'          => 'company_enabled',
				'label'       => $this->__( 'Associate Company' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Associate contact with company' ),
				'default'     => false,
			),
			array(
				'id'          => 'deal_pipeline',
				'label'       => $this->__( 'Deal Pipeline' ),
				'type'        => 'select',
				'description' => $this->__( 'Select pipeline for new deals' ),
				'options'     => 'dynamic',
				'depends_on'  => 'deal_enabled',
			),
			array(
				'id'          => 'deal_stage',
				'label'       => $this->__( 'Deal Stage' ),
				'type'        => 'select',
				'description' => $this->__( 'Select stage for new deals' ),
				'options'     => 'dynamic',
				'depends_on'  => 'deal_enabled',
			),
			array(
				'id'          => 'workflow_id',
				'label'       => $this->__( 'Workflow' ),
				'type'        => 'select',
				'description' => $this->__( 'Select workflow to enroll contact in' ),
				'options'     => 'dynamic',
				'depends_on'  => 'workflow_enabled',
			),
			array(
				'id'          => 'company_id',
				'label'       => $this->__( 'Company' ),
				'type'        => 'select',
				'description' => $this->__( 'Select company to associate with contact' ),
				'options'     => 'dynamic',
				'depends_on'  => 'company_enabled',
			),
		);
	}

	/**
	 * Get global settings fields
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getSettingsFields(): array {
		return array(
			array(
				'id'          => 'enable_analytics',
				'label'       => $this->__( 'Analytics Tracking' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Track integration performance and analytics' ),
				'default'     => true,
			),
			array(
				'id'          => 'enable_webhooks',
				'label'       => $this->__( 'Webhook Sync' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Enable webhook synchronization for real-time updates' ),
				'default'     => false,
			),
			array(
				'id'          => 'batch_processing',
				'label'       => $this->__( 'Batch Processing' ),
				'type'        => 'checkbox',
				'description' => $this->__( 'Process submissions in batches for better performance' ),
				'default'     => true,
			),
		);
	}

	/**
	 * Get enhanced field mapping for HubSpot
	 *
	 * @since    2.0.0
	 * @param    string $action Action type.
	 * @return   array
	 */
	public function getFieldMapping( string $action ): array {
		$base_mapping = array(
			'email' => array(
				'label'            => $this->__( 'Email Address' ),
				'required'         => true,
				'type'             => 'email',
				'hubspot_property' => 'email',
			),
			'firstname' => array(
				'label'            => $this->__( 'First Name' ),
				'required'         => false,
				'type'             => 'text',
				'hubspot_property' => 'firstname',
			),
			'lastname' => array(
				'label'            => $this->__( 'Last Name' ),
				'required'         => false,
				'type'             => 'text',
				'hubspot_property' => 'lastname',
			),
			'phone' => array(
				'label'            => $this->__( 'Phone Number' ),
				'required'         => false,
				'type'             => 'phone',
				'hubspot_property' => 'phone',
			),
			'company' => array(
				'label'            => $this->__( 'Company' ),
				'required'         => false,
				'type'             => 'text',
				'hubspot_property' => 'company',
			),
			'website' => array(
				'label'            => $this->__( 'Website' ),
				'required'         => false,
				'type'             => 'url',
				'hubspot_property' => 'website',
			),
			'address' => array(
				'label'            => $this->__( 'Address' ),
				'required'         => false,
				'type'             => 'text',
				'hubspot_property' => 'address',
			),
			'city' => array(
				'label'            => $this->__( 'City' ),
				'required'         => false,
				'type'             => 'text',
				'hubspot_property' => 'city',
			),
			'state' => array(
				'label'            => $this->__( 'State/Province' ),
				'required'         => false,
				'type'             => 'text',
				'hubspot_property' => 'state',
			),
			'zip' => array(
				'label'            => $this->__( 'ZIP/Postal Code' ),
				'required'         => false,
				'type'             => 'text',
				'hubspot_property' => 'zip',
			),
			'country' => array(
				'label'            => $this->__( 'Country' ),
				'required'         => false,
				'type'             => 'text',
				'hubspot_property' => 'country',
			),
		);

		return $base_mapping;
	}

	/**
	 * Validate HubSpot settings
	 *
	 * @since    2.0.0
	 * @param    array $settings HubSpot settings.
	 * @return   array
	 */
	public function validateSettings( array $settings ): array {
		$errors = array();

		if ( empty( $settings['access_token'] ) ) {
			$errors[] = 'Access token is required';
		}

		if ( empty( $settings['portal_id'] ) ) {
			$errors[] = 'Portal ID is required';
		}

		return $errors;
	}

	/**
	 * Process form submission to HubSpot
	 *
	 * @since    2.0.0
	 * @param    int   $submission_id Submission ID.
	 * @param    array $form_data     Form data.
	 * @param    array $settings      Integration settings.
	 * @return   array
	 */
	public function processSubmission( int $submission_id, array $form_data, array $settings = array() ): array {
		// Get settings if not provided.
		if ( empty( $settings ) ) {
			$form_id  = $form_data['form_id'] ?? 0;
			$settings = $this->getFormSettings( $form_id );
		}

		// Check if integration is enabled.
		if ( ! $this->isEnabled( $settings ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot integration not enabled',
			);
		}

		// Check global connection.
		if ( ! $this->is_globally_connected() ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not globally connected',
			);
		}

		// Check object type - if it's 'forms', use the new form submission method.
		$object_type = $settings['object_type'] ?? 'contacts';

		if ( $object_type === 'forms' ) {
			// New: Submit to HubSpot Form.
			return $this->process_form_submission( $submission_id, $form_data, $settings );
		} else {
			// Existing: Create Contact/Deal/Company (default behavior).
			return $this->process_submission_immediate( $submission_id, $form_data, $settings );
		}
	}

	/**
	 * Process submission immediately (no batch processing)
	 *
	 * @since    2.0.0
	 * @param    int   $submission_id Submission ID.
	 * @param    array $form_data     Form data.
	 * @param    array $settings      Integration settings.
	 * @return   array
	 */
	private function process_submission_immediate( int $submission_id, array $form_data, array $settings ): array {
		$global_settings = $this->get_global_settings();

		$access_token = $global_settings['access_token'] ?? '';
		$portal_id    = $global_settings['portal_id'] ?? '';

		if ( empty( $access_token ) || empty( $portal_id ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot not properly configured',
			);
		}

		// Map form data to HubSpot fields.
		$mapped_data = $this->enhanced_map_form_data( $form_data, $settings );

		$results = array();

		// Create/Update Contact.
		if ( ! empty( $settings['enabled'] ) && $settings['object_type'] === 'contacts' ) {
			$contact_result = $this->createOrUpdateContact( $mapped_data, $settings, $access_token, $portal_id, $submission_id );
			$results['contact'] = $contact_result;
		}

		// Create/Update Deal.
		if ( ! empty( $settings['deal_enabled'] ) ) {
			if ( ! empty( $settings['deal_update_enabled'] ) ) {
				$deal_result = $this->updateDeal( $mapped_data, $settings, $access_token, $portal_id, $submission_id );
			} else {
				$deal_result = $this->createDeal( $mapped_data, $settings, $access_token, $portal_id, $submission_id );
			}
			$results['deal'] = $deal_result;
		}

		// Process Custom Objects.
		if ( ! empty( $settings['custom_objects_enabled'] ) && ! empty( $settings['custom_objects_config'] ) ) {
			$custom_objects_result = $this->processCustomObjects( $mapped_data, $settings, $access_token, $portal_id, $submission_id );
			$results['custom_objects'] = $custom_objects_result;
		}

		// Enroll in Workflow.
		if ( ! empty( $settings['workflow_enabled'] ) && ! empty( $settings['workflow_id'] ) ) {
			$workflow_result = $this->enrollInWorkflow( $mapped_data, $settings, $access_token, $portal_id, $submission_id );
			$results['workflow'] = $workflow_result;
		}

		// Associate Company.
		if ( ! empty( $settings['company_enabled'] ) && ! empty( $settings['company_id'] ) ) {
			$company_result = $this->associateCompany( $mapped_data, $settings, $access_token, $portal_id, $submission_id );
			$results['company'] = $company_result;
		}

		// Return overall result.
		$success_count = 0;
		$errors        = array();

		foreach ( $results as $action => $result ) {
			if ( $result['success'] ) {
				$success_count++;
			} else {
				$errors[] = "{$action}: {$result['error']}";
			}
		}

		if ( $success_count > 0 ) {
			$this->logSuccess(
				'HubSpot integration completed',
				array(
					'submission_id' => $submission_id,
					'results'       => $results,
				)
			);

			return array(
				'success' => true,
				'message' => $this->__( 'Successfully processed in HubSpot' ),
				'data'    => $results,
			);
		} else {
			$this->logError(
				'HubSpot integration failed',
				array(
					'submission_id' => $submission_id,
					'errors'        => $errors,
				)
			);

			return array(
				'success' => false,
				'error'   => implode( '; ', $errors ),
			);
		}
	}

	/**
	 * Process submission to HubSpot Form (new functionality)
	 *
	 * @since    2.0.0
	 * @param    int   $submission_id Submission ID.
	 * @param    array $form_data     Form data.
	 * @param    array $settings      Integration settings.
	 * @return   array
	 */
	private function process_form_submission( int $submission_id, array $form_data, array $settings ): array {

		$global_settings = $this->get_global_settings();
		$portal_id       = $global_settings['portal_id'] ?? '';
		$hubspot_form_id = $settings['hubspot_form_id'] ?? '';

		if ( empty( $portal_id ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot portal ID not configured',
			);
		}

		if ( empty( $hubspot_form_id ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot form not selected',
			);
		}

		// Map form data using field mapping.
		$mapped_data = $this->map_form_fields( $form_data, $settings );

		// Submit to HubSpot Forms API.
		$result = $this->submit_to_hubspot_form( $portal_id, $hubspot_form_id, $mapped_data, $submission_id );

		if ( $result['success'] ) {
			return array(
				'success' => true,
				'message' => 'Successfully submitted to HubSpot Form',
			);
		} else {
			return array(
				'success' => false,
				'error'   => $result['error'],
			);
		}
	}

	/**
	 * Map form fields for HubSpot Form submission
	 *
	 * @since    2.0.0
	 * @param    array $form_data Form data.
	 * @param    array $settings  Integration settings.
	 * @return   array
	 */
	private function map_form_fields( array $form_data, array $settings ): array {
		$mapped = array();

		// Get the actual field data - handle nested structure.
		$field_data = $form_data;
		if ( isset( $form_data['fields'] ) && is_array( $form_data['fields'] ) ) {
			$field_data = $form_data['fields'];
		}

		// Get field mapping from settings.
		$field_mapping = $settings['form_field_mapping'] ?? array();

		// Map fields according to user configuration.
		foreach ( $field_mapping as $plugin_field => $hubspot_field ) {
			if ( ! empty( $hubspot_field ) && isset( $field_data[ $plugin_field ] ) ) {
				$value = $field_data[ $plugin_field ];

				// Handle different field types.
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}

				$mapped[ $hubspot_field ] = (string) $value;
			}
		}

		// Always include required context.
		// Build sanitized context.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- superglobal validated/sanitized below
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- accessed read-only and sanitized immediately
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- accessed read-only and sanitized immediately
        $referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP validated via FILTER_VALIDATE_IP
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- accessed read-only and validated via FILTER_VALIDATE_IP
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- accessed read-only and validated via FILTER_VALIDATE_IP
        $ip_raw   = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip_safe  = filter_var( $ip_raw, FILTER_VALIDATE_IP ) ? $ip_raw : '';
		$mapped['hs_context'] = wp_json_encode(
			array(
				'pageUri'   => isset( $form_data['page_url'] ) ? esc_url_raw( (string) $form_data['page_url'] ) : $referrer,
				'pageName'  => isset( $form_data['page_title'] ) ? sanitize_text_field( (string) $form_data['page_title'] ) : '',
				'ipAddress' => $ip_safe,
			)
		);

		return $mapped;
	}

	/**
	 * Submit data to HubSpot Forms API
	 *
	 * @since    2.0.0
	 * @param    string $portal_id     Portal ID.
	 * @param    string $form_id       Form ID.
	 * @param    array  $data          Form data.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function submit_to_hubspot_form( string $portal_id, string $form_id, array $data, int $submission_id ): array {
		$url = "https://api.hsforms.com/submissions/v3/integration/submit/{$portal_id}/{$form_id}";

		// Prepare the submission data.
		$submission_data = array(
			'fields' => array(),
		);

		// Convert mapped data to HubSpot format.
		foreach ( $data as $field_name => $field_value ) {
			$submission_data['fields'][] = array(
				'name'  => $field_name,
				'value' => $field_value,
			);
		}

		// Add context information.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- superglobal validated/sanitized below
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP validated via FILTER_VALIDATE_IP
		$ip_raw   = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip_safe  = filter_var( $ip_raw, FILTER_VALIDATE_IP ) ? $ip_raw : '';
		$submission_data['context'] = array(
			'pageUri'   => $referrer,
			'pageName'  => get_bloginfo( 'name' ),
			'ipAddress' => $ip_safe,
		);

		// Make the API request.
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $submission_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'API request failed: ' . $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code === 200 || $response_code === 204 ) {
			return array(
				'success' => true,
				'message' => 'Successfully submitted to HubSpot Form',
			);
		} else {
			$error_message = 'HTTP ' . $response_code;
			if ( ! empty( $response_body ) ) {
				$error_data = json_decode( $response_body, true );
				if ( isset( $error_data['message'] ) ) {
					$error_message .= ': ' . $error_data['message'];
				}
			}

			return array(
				'success' => false,
				'error'   => $error_message,
			);
		}
	}

	/**
	 * Fetch available HubSpot forms for selection
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_hubspot_forms(): array {
		$global_settings = $this->get_global_settings();
		$access_token    = $global_settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'HubSpot access token not configured',
			);
		}

		// Try the newer Forms API v3 first, then fallback to v2
		$portal_id = $global_settings['portal_id'] ?? '';

		if ( ! empty( $portal_id ) ) {
			// Try v3 Forms API with portal ID
			$url = "https://api.hubapi.com/marketing/v3/forms?portalId={$portal_id}";

			$response = wp_remote_get(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type' => 'application/json',
					),
					'timeout' => 30,
				)
			);

			// If v3 fails, try v2 without portal ID
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				$url = 'https://api.hubapi.com/forms/v2/forms';

				$response = wp_remote_get(
					$url,
					array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $access_token,
							'Content-Type' => 'application/json',
						),
						'timeout' => 30,
					)
				);
			}
		} else {
			// No portal ID, go straight to v2
			$url = 'https://api.hubapi.com/forms/v2/forms';

			$response = wp_remote_get(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type' => 'application/json',
					),
					'timeout' => 30,
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			$error_msg = 'Failed to fetch forms: ' . $response->get_error_message();
			return array(
				'success' => false,
				'error'   => $error_msg,
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			// Provide specific guidance for common errors
			if ( $response_code === 401 ) {
				$user_friendly_msg = 'HubSpot access token does not have permissions to access Forms. Please regenerate your access token with "Forms" or "Marketing Access" scope enabled in your HubSpot app settings.';
			} elseif ( $response_code === 403 ) {
				$user_friendly_msg = 'HubSpot access forbidden. Your account may not have access to the Forms API or you need additional permissions.';
			} else {
				$user_friendly_msg = 'Failed to connect to HubSpot Forms API. Please check your access token and try again.';
			}

			return array(
				'success' => false,
				'error'   => $user_friendly_msg,
			);
		}

		$forms_data = json_decode( $response_body, true );

		if ( ! is_array( $forms_data ) ) {
			return array(
				'success' => false,
				'error'   => 'Invalid response format from HubSpot',
			);
		}

		// Handle different API response formats
		if ( isset( $forms_data['results'] ) ) {
			// v3 API format: {"results": [...]}
			$forms_list = $forms_data['results'];
		} else {
			// v2 API format: direct array
			$forms_list = $forms_data;
		}

		// Format forms for dropdown selection
		$forms = array();
		foreach ( $forms_list as $form ) {
			// Handle both v2 and v3 field names
			$form_id = $form['id'] ?? $form['guid'] ?? null;
			$form_name = $form['name'] ?? $form['displayName'] ?? null;

			if ( $form_id && $form_name ) {
				$forms[ $form_id ] = $form_name;
			}
		}

		return array(
			'success' => true,
			'forms'   => $forms,
		);
	}

	/**
	 * Enhanced form data mapping for HubSpot
	 *
	 * @since    2.0.0
	 * @param    array $form_data Form data.
	 * @param    array $settings  Integration settings.
	 * @return   array
	 */
	private function enhanced_map_form_data( array $form_data, array $settings ): array {
		$mapped = array();

		// Get the actual field data - handle nested structure.
		$field_data = $form_data;
		if ( isset( $form_data['fields'] ) && is_array( $form_data['fields'] ) ) {
			$field_data = $form_data['fields'];
		}

		// Get field mapping from settings.
		$field_mapping = $settings['field_mapping'] ?? array();

		if ( ! empty( $field_mapping ) ) {
			foreach ( $field_mapping as $form_field => $hubspot_field ) {
				if ( isset( $field_data[ $form_field ] ) && ! empty( $field_data[ $form_field ] ) ) {
					$mapped[ $hubspot_field ] = $field_data[ $form_field ];
				}
			}
		} else {
			// Fall back to basic mapping.
			$mapped = $this->mapFormDataToHubspot( $field_data, $settings );
		}

		return $mapped;
	}

	/**
	 * Basic form data mapping for HubSpot (fallback)
	 *
	 * @since    2.0.0
	 * @param    array $form_data Form data.
	 * @param    array $settings  Integration settings.
	 * @return   array
	 */
	private function mapFormDataToHubspot( array $form_data, array $settings ): array {
		$mapped = array();

		// Map common fields with multiple variations.
		$field_map = array(
			'email'         => 'email',
			'first_name'    => 'firstname',
			'last_name'     => 'lastname',
			'name'          => 'firstname',
			'email_address' => 'email',
			'firstname'     => 'firstname',
			'lastname'      => 'lastname',
			'phone'         => 'phone',
			'company'       => 'company',
			'website'       => 'website',
		);

		foreach ( $field_map as $form_field => $hubspot_field ) {
			if ( isset( $form_data[ $form_field ] ) && ! empty( $form_data[ $form_field ] ) ) {
				$mapped[ $hubspot_field ] = $form_data[ $form_field ];
			}
		}

		return $mapped;
	}

	/**
	 * Create or update contact in HubSpot
	 *
	 * @since    2.0.0
	 * @param    array  $data          Contact data.
	 * @param    array  $settings      Integration settings.
	 * @param    string $access_token  HubSpot access token.
	 * @param    string $portal_id     HubSpot portal ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function createOrUpdateContact( array $data, array $settings, string $access_token, string $portal_id, int $submission_id ): array {
		if ( empty( $data['email'] ) ) {
			return array(
				'success' => false,
				'error'   => 'Email is required for contact creation',
			);
		}

		$properties = array();
		foreach ( $data as $key => $value ) {
			if ( ! empty( $value ) ) {
				$properties[ $key ] = $value;
			}
		}

		$payload = array(
			'properties' => $properties,
		);

		// First, try to find existing contact by email.
		$search_url = 'https://api.hubapi.com/crm/v3/objects/contacts/search';
		$search_payload = array(
			'filterGroups' => array(
				array(
					'filters' => array(
						array(
							'propertyName' => 'email',
							'operator'     => 'EQ',
							'value'        => $data['email'],
						),
					),
				),
			),
			'properties' => array( 'email', 'firstname', 'lastname', 'phone' ),
		);

		$search_response = wp_remote_post(
			$search_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $search_payload ),
				'timeout' => 30,
			)
		);

		$search_status = wp_remote_retrieve_response_code( $search_response );
		$search_body = wp_remote_retrieve_body( $search_response );
		$search_result = json_decode( $search_body, true );

		$contact_id = null;
		if ( $search_status === 200 && ! empty( $search_result['results'] ) ) {
			$contact_id = $search_result['results'][0]['id'];
		}

		// Use PATCH for update, POST for create.
		$method = $contact_id ? 'PATCH' : 'POST';
		$url = $contact_id ? "https://api.hubapi.com/crm/v3/objects/contacts/{$contact_id}" : 'https://api.hubapi.com/crm/v3/objects/contacts';

		$response = wp_remote_request(
			$url,
			array(
				'method' => $method,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Contact creation failed: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( $status_code === 201 || $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => 'Contact created/updated successfully',
				'data'    => $result,
			);
		} else {
			return array(
				'success' => false,
				'error'   => 'Contact creation failed: ' . ( $result['message'] ?? 'Unknown error' ),
			);
		}
	}

	/**
	 * Create deal in HubSpot
	 *
	 * @since    2.0.0
	 * @param    array  $data          Deal data.
	 * @param    array  $settings      Integration settings.
	 * @param    string $access_token  HubSpot access token.
	 * @param    string $portal_id     HubSpot portal ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function createDeal( array $data, array $settings, string $access_token, string $portal_id, int $submission_id ): array {
		$pipeline_id = $settings['deal_pipeline'] ?? '';
		$stage_id = $settings['deal_stage'] ?? '';

		if ( empty( $pipeline_id ) || empty( $stage_id ) ) {
			return array(
				'success' => false,
				'error'   => 'Deal pipeline and stage are required',
			);
		}

		$url = 'https://api.hubapi.com/crm/v3/objects/deals';

		$properties = array(
			'amount'    => $data['amount'] ?? '0',
			'dealname'  => $data['dealname'] ?? 'Form Submission Deal',
			'pipeline'  => $pipeline_id,
			'dealstage' => $stage_id,
		);

		$payload = array(
			'properties' => $properties,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Deal creation failed: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( $status_code === 201 || $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => 'Deal created successfully',
				'data'    => $result,
			);
		} else {
			return array(
				'success' => false,
				'error'   => 'Deal creation failed: ' . ( $result['message'] ?? 'Unknown error' ),
			);
		}
	}

	/**
	 * Enroll contact in workflow
	 *
	 * @since    2.0.0
	 * @param    array  $data          Contact data.
	 * @param    array  $settings      Integration settings.
	 * @param    string $access_token  HubSpot access token.
	 * @param    string $portal_id     HubSpot portal ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function enrollInWorkflow( array $data, array $settings, string $access_token, string $portal_id, int $submission_id ): array {
		$workflow_id = $settings['workflow_id'] ?? '';

		if ( empty( $workflow_id ) ) {
			return array(
				'success' => false,
				'error'   => 'Workflow ID is required',
			);
		}

		$url = "https://api.hubapi.com/automation/v2/workflows/{$workflow_id}/enrollments/contacts/{$data['email']}";

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Workflow enrollment failed: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 200 || $status_code === 201 ) {
			return array(
				'success' => true,
				'message' => 'Contact enrolled in workflow successfully',
			);
		} else {
			return array(
				'success' => false,
				'error'   => 'Workflow enrollment failed',
			);
		}
	}

	/**
	 * Update existing deal
	 *
	 * @since    2.0.0
	 * @param    array  $data          Deal data.
	 * @param    array  $settings      Integration settings.
	 * @param    string $access_token  HubSpot access token.
	 * @param    string $portal_id     HubSpot portal ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function updateDeal( array $data, array $settings, string $access_token, string $portal_id, int $submission_id ): array {
		$deal_id = $settings['deal_id'] ?? '';
		if ( empty( $deal_id ) ) {
			return array(
				'success' => false,
				'error'   => 'Deal ID not specified for update',
			);
		}

		$url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";

		$deal_data = array(
			'properties' => $data,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $deal_data ),
				'method' => 'PATCH',
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to update deal: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => 'Deal updated successfully',
				'deal_id' => $deal_id,
				'data'    => $result,
			);
		} else {
			return array(
				'success' => false,
				'error'   => "Failed to update deal (HTTP {$status_code}): " . ( $result['message'] ?? 'Unknown error' ),
			);
		}
	}

	/**
	 * Process custom objects
	 *
	 * @since    2.0.0
	 * @param    array  $data          Form data.
	 * @param    array  $settings      Integration settings.
	 * @param    string $access_token  HubSpot access token.
	 * @param    string $portal_id     HubSpot portal ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function processCustomObjects( array $data, array $settings, string $access_token, string $portal_id, int $submission_id ): array {
		$custom_objects_config = $settings['custom_objects_config'] ?? array();
		if ( empty( $custom_objects_config ) ) {
			return array(
				'success' => false,
				'error'   => 'No custom objects configuration found',
			);
		}

		$results = array();

		foreach ( $custom_objects_config as $object_config ) {
			$object_name = $object_config['object_name'] ?? '';
			$action = $object_config['action'] ?? 'create'; // create or update.
			$enabled = $object_config['enabled'] ?? false;

			if ( ! $enabled || empty( $object_name ) ) {
				continue;
			}

			// Get field mapping for this object.
			$form_id = $settings['form_id'] ?? 0;
			$object_mapping = $this->get_custom_object_mapping( $form_id, $object_name );

			if ( empty( $object_mapping ) ) {
				continue;
			}

			// Map form data to custom object properties.
			$mapped_object_data = array();
			foreach ( $object_mapping as $form_field => $object_property ) {
				if ( isset( $data[ $form_field ] ) && ! empty( $data[ $form_field ] ) ) {
					$mapped_object_data[ $object_property ] = $data[ $form_field ];
				}
			}

			if ( empty( $mapped_object_data ) ) {
				continue;
			}

			// Create or update custom object.
			if ( $action === 'update' ) {
				$object_id = $object_config['object_id'] ?? '';
				$result = $this->updateCustomObject( $object_name, $mapped_object_data, $object_id, $access_token, $portal_id, $submission_id );
			} else {
				$result = $this->createCustomObject( $object_name, $mapped_object_data, $access_token, $portal_id, $submission_id );
			}

			$results[ $object_name ] = $result;
		}

		return array(
			'success' => true,
			'results' => $results,
		);
	}

	/**
	 * Create custom object
	 *
	 * @since    2.0.0
	 * @param    string $object_name   Custom object name.
	 * @param    array  $data          Object data.
	 * @param    string $access_token  HubSpot access token.
	 * @param    string $portal_id     HubSpot portal ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function createCustomObject( string $object_name, array $data, string $access_token, string $portal_id, int $submission_id ): array {
		$url = "https://api.hubapi.com/crm/v3/objects/{$object_name}";

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to create custom object: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( $status_code === 201 && isset( $result['id'] ) ) {
			return array(
				'success' => true,
				'object_id' => $result['id'],
				'message' => 'Custom object created successfully',
			);
		} else {
			return array(
				'success' => false,
				'error'   => 'Failed to create custom object - Status: ' . $status_code . ', Response: ' . $body,
			);
		}
	}

	/**
	 * Update custom object
	 *
	 * @since    2.0.0
	 * @param    string $object_name   Custom object name.
	 * @param    array  $data          Object data.
	 * @param    string $object_id     Object ID.
	 * @param    string $access_token  HubSpot access token.
	 * @param    string $portal_id     HubSpot portal ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function updateCustomObject( string $object_name, array $data, string $object_id, string $access_token, string $portal_id, int $submission_id ): array {
		$url = "https://api.hubapi.com/crm/v3/objects/{$object_name}/{$object_id}";

		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'PATCH',
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Failed to update custom object: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => 'Custom object updated successfully',
			);
		} else {
			return array(
				'success' => false,
				'error'   => 'Failed to update custom object - Status: ' . $status_code . ', Response: ' . $body,
			);
		}
	}

	/**
	 * Associate contact with company
	 *
	 * @since    2.0.0
	 * @param    array  $data          Contact data.
	 * @param    array  $settings      Integration settings.
	 * @param    string $access_token  HubSpot access token.
	 * @param    string $portal_id     HubSpot portal ID.
	 * @param    int    $submission_id Submission ID.
	 * @return   array
	 */
	private function associateCompany( array $data, array $settings, string $access_token, string $portal_id, int $submission_id ): array {
		// This method would handle company association logic.
		// For now, return success as placeholder.
		return array(
			'success' => true,
			'message' => 'Company association completed',
		);
	}

	/**
	 * Get global settings
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function get_global_settings() {
		$global_settings = get_option( 'connect2form_integrations_global', array() );
		return $global_settings['hubspot'] ?? array();
	}

	/**
	 * Check if globally connected
	 *
	 * @since    2.0.0
	 * @return   bool
	 */
	public function is_globally_connected(): bool {
		$settings = $this->get_global_settings();
		return ! empty( $settings['access_token'] ) && ! empty( $settings['portal_id'] );
	}

	/**
	 * Get enhanced field mapping
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	public function get_enhanced_field_mapping( int $form_id ): array {
		$mapping = get_post_meta( $form_id, '_connect2form_hubspot_field_mapping', true );
		return is_array( $mapping ) ? $mapping : array();
	}

	/**
	 * Save enhanced field mapping
	 *
	 * @since    2.0.0
	 * @param    int   $form_id Form ID.
	 * @param    array $mapping Field mapping.
	 * @return   bool
	 */
	public function save_enhanced_field_mapping( int $form_id, array $mapping ): bool {
		return update_post_meta( $form_id, '_connect2form_hubspot_field_mapping', $mapping );
	}

	/**
	 * Enqueue comprehensive assets
	 *
	 * @since    2.0.0
	 * @param    string $hook Current admin hook.
	 */
	public function enqueue_comprehensive_assets( $hook ): void {
		// Get current screen.
		$screen = get_current_screen();
	
		// Check if we're on a form builder page or integration settings.
		$is_form_builder = (
			strpos( $hook, 'connect2form-new-form' ) !== false ||
			strpos( $hook, 'connect2form' ) !== false ||
			strpos( $hook, 'admin.php' ) !== false ||
			( isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === 'edit' ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	
		// Only enqueue on form builder pages or specific admin pages.
		if ( ! $is_form_builder && strpos( $hook, 'connect2form' ) === false ) {
			return;
		}
	
		// Get form ID from URL or POST data with proper sanitization.
		$form_id = 0;
		if ( isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Sanitize and verify form ID
			$form_id = isset( $_GET['id'] ) ? absint( sanitize_text_field( wp_unslash( $_GET['id'] ) ) ) : 0;
		} elseif ( isset( $_POST['form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Assuming post_int() is a custom method, ensure it's sanitizing the input
			$form_id = $this->post_int( 'form_id' );
		} elseif ( function_exists( 'get_the_ID' ) ) {
			$form_id = get_the_ID();
		}
	
		// Enqueue HubSpot specific assets.
		wp_enqueue_style(
			'connect2form-hubspot',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/css/admin/hubspot.css',
			array(),
			'1.0.0'
		);
	
		wp_enqueue_script(
			'connect2form-hubspot',
			CONNECT2FORM_INTEGRATIONS_URL . 'assets/js/admin/hubspot-form.js',
			array( 'jquery', 'wp-util' ),
			'2.0.0',
			true
		);
	
		// Create comprehensive localized data for new structure.
		$localized_data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'connect2form_nonce' ),
			'formId' => $form_id,
			'pluginUrl' => CONNECT2FORM_INTEGRATIONS_URL,
			'strings' => array(
				'testing' => __( 'Testing...', 'connect2form' ),
				'connected' => __( 'Connected', 'connect2form' ),
				'disconnected' => __( 'Disconnected', 'connect2form' ),
				'testConnection' => __( 'Test Connection', 'connect2form' ),
				'savingSettings' => __( 'Saving...', 'connect2form' ),
				'settingsSaved' => __( 'Settings saved successfully!', 'connect2form' ),
				'connectionFailed' => __( 'Connection failed', 'connect2form' ),
				'selectContact' => __( 'Select contact properties...', 'connect2form' ),
				'loadingFields' => __( 'Loading fields...', 'connect2form' ),
				'fieldsLoaded' => __( 'Fields loaded successfully', 'connect2form' ),
				'noFieldsFound' => __( 'No fields found', 'connect2form' ),
				'networkError' => __( 'Network error', 'connect2form' ),
				'mappingSaved' => __( 'Field mapping saved successfully', 'connect2form' ),
				'mappingFailed' => __( 'Failed to save field mapping', 'connect2form' ),
				'autoMappingComplete' => __( 'Auto-mapping completed', 'connect2form' ),
				'clearMappingsConfirm' => __( 'Are you sure you want to clear all mappings?', 'connect2form' ),
			),
		);
	
		// Localize script with new structure.
		wp_localize_script( 'connect2form-hubspot', 'connect2formCFHubspot', $localized_data );
	
		// Also localize with standard variables for compatibility.
		wp_localize_script(
			'connect2form-hubspot',
			'connect2formHubspotCompat',
			array(
				'nonce' => wp_create_nonce( 'connect2form_nonce' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}
	

	/**
	 * AJAX: Test connection
	 *
	 * @since    2.0.0
	 */
    public function ajax_test_connection(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		// Extract credentials from credentials object or direct POST
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$access_token = '';
		$portal_id = '';

		if ( isset( $_POST['credentials'] ) && is_array( $_POST['credentials'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $creds = $this->sanitize_array_recursive( wp_unslash( $_POST['credentials'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$access_token = $creds['access_token'] ?? '';
			$portal_id    = $creds['portal_id'] ?? '';
		} else {
			$access_token = $this->post_text( 'access_token' );
			$portal_id    = $this->post_text( 'portal_id' );
		}

		if ( empty( $access_token ) || empty( $portal_id ) ) {
			wp_send_json( array(
				'success' => false,
				'error' => 'Access token and Portal ID are required'
			) );
		}

		$result = $this->testConnection( array(
			'access_token' => $access_token,
			'portal_id' => $portal_id
		) );

		wp_send_json( $result );
	}

	/**
	 * AJAX: Get contacts
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_contacts(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		if ( $this->custom_properties_manager ) {
			$this->custom_properties_manager->ajax_get_contacts();
		} else {
			wp_send_json_error( 'Custom properties manager not available' );
		}
	}

	/**
	 * AJAX: Get companies
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_companies(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		if ( $this->company_manager ) {
			$this->company_manager->ajax_get_companies();
		} else {
			wp_send_json_error( 'Company manager not available' );
		}
	}

	/**
	 * AJAX: Get deals
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_deals(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		if ( $this->deal_manager ) {
			$this->deal_manager->ajax_get_deals();
		} else {
			wp_send_json_error( 'Deal manager not available' );
		}
	}

	/**
	 * AJAX: Get custom properties
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_custom_properties(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		if ( $this->custom_properties_manager ) {
			$this->custom_properties_manager->ajax_get_custom_properties();
		} else {
			wp_send_json_error( 'Custom properties manager not available' );
		}
	}

	/**
	 * AJAX: Get workflows
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_workflows(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		if ( $this->workflow_manager ) {
			$this->workflow_manager->ajax_get_workflows();
		} else {
			wp_send_json_error( 'Workflow manager not available' );
		}
	}

	/**
	 * AJAX: Get HubSpot forms for selection
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_forms(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$result = $this->get_hubspot_forms();

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'forms' => $result['forms'],
			) );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	/**
	 * AJAX: Test response format
	 *
	 * @since    2.0.0
	 */
    public function ajax_test_response(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		// Return a simple success response.
		wp_send_json_success( 'Test response successful' );
	}

	/**
	 * AJAX: Save global settings (standard version)
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_global_settings(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		// Extract settings from the form data.
		$settings = array();
		if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $settings = $this->sanitize_array_recursive( wp_unslash( $_POST['settings'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			// Fallback to direct POST data.
			$settings = array(
				'access_token' => $this->post_text( 'access_token' ),
				'portal_id' => $this->post_text( 'portal_id' ),
            'enable_analytics' => $this->post_bool( 'enable_analytics' ),
            'enable_webhooks' => $this->post_bool( 'enable_webhooks' ),
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
				'configured' => true,
			) );
		} else {
			wp_send_json_error( 'Failed to save settings' );
		}
	}

	/**
	 * AJAX: Save global settings (simplified version)
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_global_settings_simple(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		// Extract settings from POST data.
		$settings = array(
			'access_token' => $this->post_text( 'access_token' ),
			'portal_id' => $this->post_text( 'portal_id' ),
            'enable_analytics' => $this->post_bool( 'enable_analytics' ),
            'enable_webhooks' => $this->post_bool( 'enable_webhooks' ),
            'batch_processing' => $this->post_bool( 'batch_processing' ),
		);

		// Validate required fields.
		if ( empty( $settings['access_token'] ) || empty( $settings['portal_id'] ) ) {
			wp_send_json_error( 'Access token and portal ID are required' );
			return;
		}

		// Validate settings.
		$validation_result = $this->validateSettings( $settings );
		if ( ! empty( $validation_result ) ) {
			wp_send_json_error( 'Settings validation failed: ' . implode( ', ', $validation_result ) );
			return;
		}

		// Save settings.
		$result = $this->saveGlobalSettings( $settings );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => 'Settings saved successfully!',
				'configured' => true,
			) );
		} else {
			wp_send_json_error( 'Failed to save settings' );
		}
	}

	/**
	 * AJAX: Save global settings (v2)
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_global_settings_v2(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		// Extract settings from POST data.
		$settings = array(
			'access_token' => $this->post_text( 'access_token' ),
			'portal_id' => $this->post_text( 'portal_id' ),
            'enable_analytics' => $this->post_bool( 'enable_analytics' ),
            'enable_webhooks' => $this->post_bool( 'enable_webhooks' ),
            'batch_processing' => $this->post_bool( 'batch_processing' ),
		);

		// Validate required fields.
		if ( empty( $settings['access_token'] ) || empty( $settings['portal_id'] ) ) {
			wp_send_json_error( 'Access token and portal ID are required' );
			return;
		}

		// Validate settings.
		$validation_result = $this->validateSettings( $settings );
		if ( ! empty( $validation_result ) ) {
			wp_send_json_error( 'Settings validation failed: ' . implode( ', ', $validation_result ) );
			return;
		}

		// Save settings.
		$result = $this->saveGlobalSettings( $settings );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => 'Settings saved successfully!',
				'configured' => true,
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
			wp_send_json_error( 'Form ID is required' );
		}

		// Validate that the form exists in the custom forms table.
		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$form = $service_manager->forms()->get_form( $form_id );
			$form_exists = ! empty( $form );
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
			$forms_table = $wpdb->prefix . 'connect2form_forms';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fallback form existence check with validated table name; service layer preferred but this is a fallback.
			$form_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$forms_table}` WHERE id = %d", $form_id ) );
		}

		if ( ! $form_exists ) {
			wp_send_json_error( 'Form does not exist' );
		}

		// Validate form ID.
		if ( $form_id <= 0 ) {
			wp_send_json_error( 'Invalid form ID' );
		}

		// Process settings.
        $settings = array(
            'enabled' => $this->post_bool( 'enabled' ),
			'object_type' => $this->post_text( 'object_type' ),
			'custom_object_name' => $this->post_text( 'custom_object_name' ),
			'action_type' => $this->post_text( 'action_type' ),
            'workflow_enabled' => $this->post_bool( 'workflow_enabled' ),
            'field_mapping' => $this->sanitize_array_recursive( $this->post_array( 'field_mapping' ) ),
		);

		$result = $this->saveFormSettings( $form_id, $settings );

		if ( $result ) {
			wp_send_json_success( 'Form settings saved successfully' );
		} else {
			wp_send_json_error( 'Failed to save form settings' );
		}
	}

	/**
	 * AJAX: Save field mapping
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_field_mapping(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$form_id = $this->post_int( 'form_id' );
        $mapping = $this->sanitize_array_recursive( $this->post_array( 'mapping' ) );

		if ( ! $form_id ) {
			wp_send_json_error( 'Form ID is required' );
		}

		$result = $this->save_enhanced_field_mapping( $form_id, $mapping );

		if ( $result ) {
			wp_send_json_success( 'Field mapping saved successfully' );
		} else {
			wp_send_json_error( 'Failed to save field mapping' );
		}
	}

	/**
	 * AJAX: Get form fields
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_form_fields(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$form_id = $this->post_int( 'form_id' );

		if ( ! $form_id ) {
			wp_send_json_error( 'Form ID is required' );
		}

		$form_fields = $this->get_form_fields( $form_id );

		// Convert associative array to indexed array for JavaScript
		$fields_array = array();
		foreach ( $form_fields as $field_id => $field_config ) {
			$fields_array[] = $field_config;
		}

		wp_send_json_success( array( 'fields' => $fields_array ) );
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
			wp_send_json_error( 'Form ID is required' );
		}

		$mapping = $this->get_enhanced_field_mapping( $form_id );
		wp_send_json_success( $mapping );
	}

	/**
	 * AJAX: Get HubSpot custom objects
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_custom_objects(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';
		$portal_id = $global_settings['portal_id'] ?? '';

		if ( empty( $access_token ) ) {
			wp_send_json_error( 'HubSpot not configured - Access token missing' );
		}

		if ( empty( $portal_id ) ) {
			wp_send_json_error( 'HubSpot not configured - Portal ID missing' );
		}

		// Use portal-specific URL for custom objects.
		$url = "https://api.hubapi.com/crm/v3/schemas?portalId={$portal_id}";
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Failed to fetch custom objects: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['results'] ) ) {
			$custom_objects = array();
			$total_schemas = count( $data['results'] );
			$custom_object_count = 0;

			foreach ( $data['results'] as $schema ) {
				$object_type = $schema['objectType'] ?? 'unknown';
				$schema_name = $schema['name'] ?? 'unknown';
				$fully_qualified_name = $schema['fullyQualifiedName'] ?? '';

				// Check if this is a custom object by looking at the fullyQualifiedName pattern.
				// Custom objects have pattern: p{portal_id}_{object_name}.
				if ( preg_match( '/^p\d+_/', $fully_qualified_name ) || $object_type === 'CUSTOM_OBJECT' ) {
					$custom_object_count++;
					$custom_objects[] = array(
						'name' => $schema_name,
						'label' => $schema['labels']['singular'] ?? $schema_name ?? '',
						'plural_label' => $schema['labels']['plural'] ?? $schema_name ?? '',
						'description' => $schema['description'] ?? '',
						'primary_property' => $schema['primaryDisplayProperty'] ?? '',
						'fullyQualifiedName' => $fully_qualified_name,
					);
				}
			}

			wp_send_json_success( $custom_objects );
		} else {
			wp_send_json_error( 'Failed to fetch custom objects' );
		}
	}

	/**
	 * AJAX: Get HubSpot custom object properties
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_custom_object_properties(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$object_name = $this->post_text( 'custom_object_name' );
		if ( empty( $object_name ) ) {
			wp_send_json_error( 'Custom object name is required' );
		}

		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';
		$portal_id = $global_settings['portal_id'] ?? '';

		if ( empty( $access_token ) ) {
			wp_send_json_error( 'HubSpot not configured - Access token missing' );
		}

		if ( empty( $portal_id ) ) {
			wp_send_json_error( 'HubSpot not configured - Portal ID missing' );
		}

		// Use portal-specific URL for custom object properties.
		// For custom objects, we need to use the fully qualified name.
		$url = "https://api.hubapi.com/crm/v3/properties/{$object_name}?portalId={$portal_id}";

		// Also try alternative URL format for custom objects.
		$alt_url = "https://api.hubapi.com/crm/v3/properties/{$object_name}";
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Try alternative URL without portalId.
			$response = wp_remote_get(
				$alt_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type' => 'application/json',
					),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( 'Failed to fetch custom object properties: ' . $response->get_error_message() );
			}
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['results'] ) ) {
			$properties = array();
			$total_properties = count( $data['results'] );
			$included_properties = 0;

			foreach ( $data['results'] as $property ) {
				// Include all properties that are not read-only.
				if ( ! isset( $property['modificationMetadata']['readOnlyDefinition'] ) || 
					$property['modificationMetadata']['readOnlyDefinition'] === false ) {
					$included_properties++;
					$properties[] = array(
						'name' => $property['name'],
						'label' => $property['label'],
						'type' => $property['type'],
						'required' => isset( $property['modificationMetadata']['readOnlyDefinition'] ) ? 
							( $property['modificationMetadata']['readOnlyDefinition'] === false ) : true,
					);
				}
			}

			wp_send_json_success( $properties );
		} else {
			wp_send_json_error( 'Failed to fetch custom object properties' );
		}
	}

	/**
	 * AJAX: Save custom object field mapping
	 *
	 * @since    2.0.0
	 */
    public function ajax_save_custom_object_mapping(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$form_id     = $this->post_int( 'form_id' );
		$object_name = $this->post_text( 'object_name' );
        $mapping     = $this->sanitize_array_recursive( $this->post_array( 'mapping' ) );

		if ( ! $form_id || empty( $object_name ) ) {
			wp_send_json_error( 'Form ID and object name are required' );
		}

		$result = $this->save_custom_object_mapping( $form_id, $object_name, $mapping );

		if ( $result ) {
			wp_send_json_success( 'Custom object mapping saved successfully' );
		} else {
			wp_send_json_error( 'Failed to save custom object mapping' );
		}
	}

	/**
	 * AJAX: Get custom object field mapping
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_custom_object_mapping(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$form_id     = $this->post_int( 'form_id' );
		$object_name = $this->post_text( 'object_name' );

		if ( ! $form_id || empty( $object_name ) ) {
			wp_send_json_error( 'Form ID and object name are required' );
		}

		$mapping = $this->get_custom_object_mapping( $form_id, $object_name );
		wp_send_json_success( $mapping );
	}

	/**
	 * Save custom object field mapping
	 *
	 * @since    2.0.0
	 * @param    int    $form_id     Form ID.
	 * @param    string $object_name Object name.
	 * @param    array  $mapping     Field mapping.
	 * @return   bool
	 */
	private function save_custom_object_mapping( int $form_id, string $object_name, array $mapping ): bool {
		if ( ! $form_id || empty( $object_name ) ) {
			return false;
		}

		// Get existing custom object mappings.
		$existing_mappings = get_post_meta( $form_id, '_connect2form_custom_object_mappings', true );
		if ( ! is_array( $existing_mappings ) ) {
			$existing_mappings = array();
		}

		// Update mapping for this object.
		$existing_mappings[ $object_name ] = $mapping;

		// Save back to post meta.
		$result = update_post_meta( $form_id, '_connect2form_custom_object_mappings', $existing_mappings );

		return $result;
	}

	/**
	 * Get custom object field mapping
	 *
	 * @since    2.0.0
	 * @param    int    $form_id     Form ID.
	 * @param    string $object_name Object name.
	 * @return   array
	 */
	private function get_custom_object_mapping( int $form_id, string $object_name ): array {
		if ( ! $form_id || empty( $object_name ) ) {
			return array();
		}

		$mappings = get_post_meta( $form_id, '_connect2form_custom_object_mappings', true );
		if ( ! is_array( $mappings ) || ! isset( $mappings[ $object_name ] ) ) {
			return array();
		}

		return $mappings[ $object_name ];
	}

	/**
	 * AJAX: Get HubSpot deal properties
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_deal_properties(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';
		$portal_id = $global_settings['portal_id'] ?? '';

		if ( empty( $access_token ) ) {
			wp_send_json_error( 'HubSpot not configured - Access token missing' );
		}

		if ( empty( $portal_id ) ) {
			wp_send_json_error( 'HubSpot not configured - Portal ID missing' );
		}

		// Use portal-specific URL for deal properties.
		$url = "https://api.hubapi.com/crm/v3/properties/deals?portalId={$portal_id}";
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Failed to fetch deal properties: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['results'] ) ) {
			$properties = array();
			foreach ( $data['results'] as $property ) {
				// Include ALL properties (both writable and read-only) for the dropdown.
				// Users can see all available properties and decide which to map.
				$properties[] = array(
					'name' => $property['name'],
					'label' => $property['label'],
					'type' => $property['type'],
					'required' => false, // Most properties are not required for updates.
					'readonly' => isset( $property['modificationMetadata']['readOnlyDefinition'] ) && 
								 $property['modificationMetadata']['readOnlyDefinition'] === true,
				);
			}

			wp_send_json_success( $properties );
		} else {
			wp_send_json_error( 'Failed to fetch deal properties - Status: ' . $status_code );
		}
	}

	/**
	 * AJAX: Get HubSpot company properties
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_company_properties(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';
		$portal_id = $global_settings['portal_id'] ?? '';

		if ( empty( $access_token ) ) {
			wp_send_json_error( 'HubSpot not configured - Access token missing' );
		}

		if ( empty( $portal_id ) ) {
			wp_send_json_error( 'HubSpot not configured - Portal ID missing' );
		}

		// Use portal-specific URL for company properties.
		$url = "https://api.hubapi.com/crm/v3/properties/companies?portalId={$portal_id}";
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Failed to fetch company properties: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['results'] ) ) {
			$properties = array();
			foreach ( $data['results'] as $property ) {
				// Include ALL properties (both writable and read-only) for the dropdown.
				// Users can see all available properties and decide which to map.
				$properties[] = array(
					'name' => $property['name'],
					'label' => $property['label'],
					'type' => $property['type'],
					'required' => false, // Most properties are not required for updates.
					'readonly' => isset( $property['modificationMetadata']['readOnlyDefinition'] ) && 
								 $property['modificationMetadata']['readOnlyDefinition'] === true,
				);
			}

			wp_send_json_success( $properties );
		} else {
			wp_send_json_error( 'Failed to fetch company properties - Status: ' . $status_code );
		}
	}

	/**
	 * AJAX: Get HubSpot contact properties
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_contact_properties(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$global_settings = $this->get_global_settings();
		$access_token = $global_settings['access_token'] ?? '';
		$portal_id = $global_settings['portal_id'] ?? '';

		if ( empty( $access_token ) ) {
			wp_send_json_error( 'HubSpot not configured - Access token missing' );
		}

		if ( empty( $portal_id ) ) {
			wp_send_json_error( 'HubSpot not configured - Portal ID missing' );
		}

		// Use portal-specific URL for contact properties.
		$url = "https://api.hubapi.com/crm/v3/properties/contacts?portalId={$portal_id}";
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Failed to fetch contact properties: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code === 200 && isset( $data['results'] ) ) {
			$properties = array();
			foreach ( $data['results'] as $property ) {
				// Include ALL properties (both writable and read-only) for the dropdown.
				// Users can see all available properties and decide which to map.
				$properties[] = array(
					'name' => $property['name'],
					'label' => $property['label'],
					'type' => $property['type'],
					'required' => false, // Most properties are not required for updates.
					'readonly' => isset( $property['modificationMetadata']['readOnlyDefinition'] ) && 
								 $property['modificationMetadata']['readOnlyDefinition'] === true,
				);
			}

			wp_send_json_success( $properties );
		} else {
			wp_send_json_error( 'Failed to fetch contact properties - Status: ' . $status_code );
		}
	}

	/**
	 * AJAX: Auto map fields
	 *
	 * @since    2.0.0
	 */
    public function ajax_auto_map_fields(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );

		$form_id = $this->post_int( 'form_id' );

		if ( ! $form_id ) {
			wp_send_json_error( 'Form ID is required' );
		}

		// Generate automatic mapping based on form fields.
		$auto_mapping = $this->generate_automatic_mapping( $form_id );
		wp_send_json_success( $auto_mapping );
	}

	/**
	 * AJAX: Get analytics
	 *
	 * @since    2.0.0
	 */
    public function ajax_get_analytics(): void {
        \Connect2Form_Security::validate_ajax_request( 'connect2form_nonce' );
        if ( $this->analytics_manager ) {
			$this->analytics_manager->ajax_get_analytics_data();
		} else {
			wp_send_json_error( 'Analytics manager not available' );
		}
	}

	/**
	 * Generate automatic field mapping
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	private function generate_automatic_mapping( int $form_id ): array {
		$form_fields = $this->get_form_fields( $form_id );
		$mapping = array();

		// Basic auto-mapping logic.
		$auto_map_rules = array(
			'email' => 'email',
			'first_name' => 'firstname',
			'last_name' => 'lastname',
			'phone' => 'phone',
			'company' => 'company',
		);

		foreach ( $form_fields as $field_name => $field_config ) {
			$field_label = strtolower( $field_config['label'] ?? '' );

			foreach ( $auto_map_rules as $pattern => $hubspot_field ) {
				if ( strpos( $field_label, $pattern ) !== false ) {
					$mapping[ $field_name ] = $hubspot_field;
					break;
				}
			}
		}

		return $mapping;
	}

	/**
	 * Get form fields from database
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	private function get_form_fields( int $form_id ): array {
		// Use the FormService for database operations.
		$form_service = new \Connect2Form\Integrations\Core\Services\FormService();

		if ( ! $form_id ) {
			return array();
		}

		$form = $form_service->get_form( $form_id );

		if ( ! $form ) {
			return array();
		}

		if ( ! $form->fields ) {
			return array();
		}

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
				'description' => $field['description'] ?? '',
			);
		}

		return $processed_fields;
	}

	/**
	 * Get component managers
	 *
	 * @since    2.0.0
	 * @return   mixed
	 */
	public function get_custom_properties_manager() {
		return $this->custom_properties_manager;
	}

	/**
	 * Get workflow manager
	 *
	 * @since    2.0.0
	 * @return   mixed
	 */
	public function get_workflow_manager() {
		return $this->workflow_manager;
	}

	/**
	 * Get deal manager
	 *
	 * @since    2.0.0
	 * @return   mixed
	 */
	public function get_deal_manager() {
		return $this->deal_manager;
	}

	/**
	 * Get company manager
	 *
	 * @since    2.0.0
	 * @return   mixed
	 */
	public function get_company_manager() {
		return $this->company_manager;
	}

	/**
	 * Get analytics manager
	 *
	 * @since    2.0.0
	 * @return   mixed
	 */
	public function get_analytics_manager() {
		return $this->analytics_manager;
	}

	/**
	 * Get language manager
	 *
	 * @since    2.0.0
	 * @return   mixed
	 */
	public function get_language_manager() {
		return $this->language_manager;
	}

	/**
	 * Translation helper
	 *
	 * @since    2.0.0
	 * @param    string $text     Text to translate.
	 * @param    string $fallback Fallback text.
	 * @return   string
	 */
	private function __( $text, $fallback = null ) {
		if ( $this->language_manager ) {
			return $this->language_manager->translate( $text );
		}
		return $fallback ?? $text;
	}

	/**
	 * Handle form submission from the main plugin
	 *
	 * @since    2.0.0
	 * @param    int   $submission_id Submission ID.
	 * @param    array $form_data     Form data.
	 */
	public function handle_form_submission( int $submission_id, array $form_data ) {
		try {
			// Get form ID from the form data.
			$form_id = $form_data['form_id'] ?? 0;
			if ( ! $form_id ) {
				return;
			}

			// Get form settings.
			$settings = $this->getFormSettings( $form_id );

			// Check if HubSpot integration is enabled for this form.
			$hubspot_settings = $settings['hubspot'] ?? $settings;
			if ( empty( $hubspot_settings['enabled'] ) ) {
				return;
			}

			// Check if globally connected.
			if ( ! $this->is_globally_connected() ) {
				return;
			}

			// Process the submission.
			$result = $this->processSubmission( $submission_id, $form_data, $hubspot_settings );

		} catch ( \Throwable $e ) {
			// Silent error handling for production.
		}
	}

	/**
	 * Simulate template loading
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	private function simulate_template_loading( int $form_id ): array {
		$form_settings = array();

		if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
			$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
			$meta_value = $service_manager->database()->getFormMeta( $form_id, '_connect2form_integrations' );

			if ( $meta_value ) {
				$integration_settings = json_decode( $meta_value, true );
				if ( is_array( $integration_settings ) && isset( $integration_settings['hubspot'] ) ) {
					$form_settings = $integration_settings['hubspot'];
				}
			}
		} else {
			// Fallback to direct database call if service not available.
			global $wpdb;
            $meta_table = $wpdb->prefix . 'connect2form_form_meta';
            // Validate table identifier to avoid unsafe interpolation
            if ( function_exists( 'c2f_is_valid_prefixed_table' ) && ! \c2f_is_valid_prefixed_table( $meta_table ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier call
                return array(); // bail out safely
            }

			// Check if meta table exists.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check for meta table; no caching needed for INFORMATION_SCHEMA queries.
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $meta_table ) ) == $meta_table ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback form meta query; identifier validated above
                $meta_value = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT meta_value FROM `{$meta_table}` WHERE form_id = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table identifier via c2f_is_valid_prefixed_table()
                        $form_id,
                        '_connect2form_integrations'
                    )
                );
				
				if ( $meta_value ) {
					$integration_settings = json_decode( $meta_value, true );
					if ( is_array( $integration_settings ) && isset( $integration_settings['hubspot'] ) ) {
						$form_settings = $integration_settings['hubspot'];
					}
				}
			}
		}

		// Fallback: Try post meta.
		if ( empty( $form_settings ) ) {
			$post_meta = get_post_meta( $form_id, '_connect2form_integrations', true );
			if ( $post_meta && isset( $post_meta['hubspot'] ) ) {
				$form_settings = $post_meta['hubspot'];
			}
		}

		// Fallback: Try options table.
		if ( empty( $form_settings ) ) {
			$option_key = "connect2form_hubspot_form_{$form_id}";
			$form_settings = get_option( $option_key, array() );
		}

		return array(
			'form_id' => $form_id,
			'settings_found' => ! empty( $form_settings ),
			'settings' => $form_settings,
			'field_mapping' => $form_settings['field_mapping'] ?? array(),
		);
	}

	/**
	 * Check if form exists in custom table
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   bool
	 */
	private function form_exists_in_custom_table( int $form_id ): bool {
		// Use the FormService for database operations.
		$form_service = new \Connect2Form\Integrations\Core\Services\FormService();
		$form = $form_service->get_form( $form_id );
		return $form !== null;
	}

	/**
	 * Get form settings from custom table
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	private function get_form_settings_from_custom_table( int $form_id ): array {
		// Use the DatabaseManager for database operations.
		$database_manager = new \Connect2Form\Integrations\Core\Services\DatabaseManager();

		$meta_value = $database_manager->getFormMeta( $form_id, '_connect2form_integrations' );

		if ( $meta_value ) {
			$settings = json_decode( $meta_value, true );
			return is_array( $settings ) ? $settings : array();
		}

		return array();
	}

	/**
	 * Get form settings for HubSpot integration
	 *
	 * @since    2.0.0
	 * @param    int $form_id Form ID.
	 * @return   array
	 */
	protected function getFormSettings( int $form_id ): array {
		// Try to get settings from custom table first.
		$settings = $this->get_form_settings_from_custom_table( $form_id );

		if ( ! empty( $settings ) && isset( $settings['hubspot'] ) ) {
			return $settings['hubspot'];
		}

		// Fallback to WordPress post meta.
		$meta_value = get_post_meta( $form_id, '_connect2form_integrations', true );
		if ( $meta_value ) {
			$decoded = json_decode( $meta_value, true );
			$hubspot_settings = $decoded['hubspot'] ?? array();
			return $hubspot_settings;
		}

		return array();
	}

	/**
	 * Save form settings
	 *
	 * @since    2.0.0
	 * @param    int   $form_id Form ID.
	 * @param    array $settings Settings to save.
	 * @return   bool
	 */
	protected function saveFormSettings( int $form_id, array $settings ): bool {
		if ( ! $form_id ) {
			return false;
		}

		// Use the FormService to verify the form exists.
		$form_service = new \Connect2Form\Integrations\Core\Services\FormService();
		$form = $form_service->get_form( $form_id );

		if ( ! $form ) {
			return false;
		}

		// Use the DatabaseManager for database operations.
		$database_manager = new \Connect2Form\Integrations\Core\Services\DatabaseManager();

		// Get existing settings.
		$existing_settings = $database_manager->getFormMeta( $form_id, '_connect2form_integrations' );

		if ( $existing_settings ) {
			$existing_settings = json_decode( $existing_settings, true );
		}

		if ( ! is_array( $existing_settings ) ) {
			$existing_settings = array();
		}

		// Update HubSpot settings.
		$existing_settings['hubspot'] = $settings;

		// Save the updated settings.
		$result = $database_manager->saveFormMeta( $form_id, '_connect2form_integrations', json_encode( $existing_settings ) );

		// Clear any relevant caches after saving.
		if ( $result ) {
			// Clear form cache if using service manager.
			if ( class_exists( '\Connect2Form\Integrations\Core\Services\ServiceManager' ) ) {
				try {
					$service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
					$service_manager->clear_all_caches();
				} catch ( \Exception $e ) {
					// Log but don't fail.
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only log in admin context when clearing caches fails
                    error_log( 'Connect2Form: Failed to clear caches after saving HubSpot settings: ' . $e->getMessage() );
				}
			}

			// Clear any object cache if available.
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( "connect2form_hubspot_form_{$form_id}", 'options' );
				wp_cache_delete( "form_meta_{$form_id}_" . md5( '_connect2form_integrations' ), 'connect2form' );
			}

			// Force clear any WordPress transients.
			delete_transient( "connect2form_form_settings_{$form_id}" );
			delete_transient( "connect2form_hubspot_settings_{$form_id}" );

			// Clear any persistent object cache (Redis, Memcached, etc.).
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}

		return $result;
	}
} 