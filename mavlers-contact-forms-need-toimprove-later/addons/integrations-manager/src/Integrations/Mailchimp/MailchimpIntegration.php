<?php
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
 * @package MavlersCF\Integrations\Mailchimp
 * @since 2.0.0
 */

namespace MavlersCF\Integrations\Mailchimp;

use MavlersCF\Integrations\Core\Abstracts\AbstractIntegration;
use MavlersCF\Integrations\Core\Services\LanguageManager;

if (!defined('ABSPATH')) {
    exit;
}

class MailchimpIntegration extends AbstractIntegration {

    protected $id = 'mailchimp';
    protected $name = 'Mailchimp';
    protected $description = 'Advanced Mailchimp integration with custom fields, analytics, and automation';
    protected $version = '2.0.0';
    protected $icon = 'dashicons-email-alt';
    protected $color = '#ffe01b';

    private $api_base_url = 'https://{dc}.api.mailchimp.com/3.0/';

    /**
     * Component instances
     */
    private $custom_fields_manager;
    private $language_manager;
    private $analytics_manager;
    private $webhook_handler;
    private $batch_processor;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Debug: Log constructor
        error_log('Mailchimp Integration: Constructor called');
        
        // Register AJAX handlers immediately as fallback
        if (function_exists('add_action')) {
            error_log('Mailchimp Integration: Registering AJAX handlers in constructor');
            $this->register_ajax_handlers();
        }
        
        // Debug: Log constructor completed
        error_log('Mailchimp Integration: Constructor completed');
    }

    /**
     * Initialize integration
     */
    protected function init() {
        // Debug: Log that init is being called
        error_log('Mailchimp Integration: Init method called');
        
        // Test if WordPress init action is firing
        add_action('init', function() {
            error_log('Mailchimp Integration: WordPress init action is firing!');
        }, 1);
        
        // Defer component initialization until WordPress is ready
        add_action('init', [$this, 'init_components'], 5);
        
        // Register AJAX handlers
        add_action('init', [$this, 'register_ajax_handlers'], 10);
        
        // Test action to verify init is firing
        add_action('init', [$this, 'test_init_hook'], 1);
        
        // Register assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_comprehensive_assets']);
        
        // Form processing
        add_action('mavlers_cf_after_submission', [$this, 'processSubmission'], 10, 2);
        
        // Webhook handling
        add_action('init', [$this, 'init_webhook_endpoints'], 15);
        
        // Debug: Log that init is complete
        error_log('Mailchimp Integration: Init method completed');
    }

    /**
     * Test method to verify init hook is firing
     */
    public function test_init_hook() {
        error_log('Mailchimp Integration: Init hook is firing!');
        
        // Manually register AJAX handlers to test
        $this->register_ajax_handlers();
    }

    /**
     * Initialize component instances
     */
    public function init_components() {
        // Debug: Log that components are being initialized
        error_log('Mailchimp Integration: Init components called');
        
        // Load component files
        $components_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/Mailchimp/';
        
        $component_files = [
            'LanguageManager.php',
            'AnalyticsManager.php', 
            'CustomFieldsManager.php',
            'WebhookHandler.php',
            'BatchProcessor.php'
        ];
        
        foreach ($component_files as $file) {
            if (file_exists($components_dir . $file)) {
                require_once $components_dir . $file;
            }
        }
        
        try {
            // Initialize language manager first (needed by other components)
            $this->language_manager = new LanguageManager();
            
            // Initialize analytics manager
            $this->analytics_manager = new AnalyticsManager();
            
            // Initialize custom fields manager
            $this->custom_fields_manager = new CustomFieldsManager($this);
            
            // Initialize webhook handler
            $this->webhook_handler = new WebhookHandler($this->analytics_manager);
            
            // Initialize batch processor
            $this->batch_processor = new BatchProcessor($this, $this->logger);
            
            // Debug: Log that components initialization is complete
            error_log('Mailchimp Integration: Init components completed');
        } catch (Exception $e) {
            // Component initialization failed
            error_log('Mailchimp Integration: Init components failed - ' . $e->getMessage());
        }
    }

    /**
     * Register comprehensive AJAX handlers
     */
    public function register_ajax_handlers() {
        // Debug: Log that the registration is being called
        error_log('Mailchimp Integration: Registering AJAX handlers');
        
        // Simple test handler
        add_action('wp_ajax_mailchimp_test_simple', [$this, 'ajax_test_simple']);
        
        // Basic handlers (admin only)
        add_action('wp_ajax_mailchimp_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_mailchimp_get_audiences', [$this, 'ajax_get_audiences']);
        add_action('wp_ajax_mailchimp_save_global_settings', [$this, 'ajax_save_global_settings']);
        add_action('wp_ajax_mailchimp_save_form_settings', [$this, 'ajax_save_form_settings']);
        add_action('wp_ajax_save_mailchimp_form_settings', [$this, 'ajax_save_form_settings']);
        add_action('wp_ajax_save_mailchimp_audience_selection', [$this, 'ajax_save_audience_selection']);
        
        // Enhanced field mapping (admin only)
        add_action('wp_ajax_mailchimp_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_mailchimp_save_field_mapping', [$this, 'ajax_save_field_mapping']);
        add_action('wp_ajax_mailchimp_get_field_mapping', [$this, 'ajax_get_field_mapping']);
        add_action('wp_ajax_mailchimp_auto_map_fields', [$this, 'ajax_auto_map_fields']);
        
        // Audience and merge fields (admin only)
        add_action('wp_ajax_mailchimp_get_audience_merge_fields', [$this, 'ajax_get_audience_merge_fields']);
        add_action('wp_ajax_mailchimp_get_merge_fields', [$this, 'ajax_get_audience_merge_fields']);
        
        // Multilingual interface (admin only)
        add_action('wp_ajax_mailchimp_get_multilingual_interface', [$this, 'ajax_get_multilingual_interface']);
        
        // Analytics (admin only)
        add_action('wp_ajax_mailchimp_load_analytics_dashboard', [$this, 'ajax_load_analytics_dashboard']);
        add_action('wp_ajax_mailchimp_export_analytics', [$this, 'ajax_export_analytics']);
        
        // Settings handlers
        add_action('wp_ajax_mailchimp_save_global_settings_v2', [$this, 'ajax_save_global_settings_v2']);
        
        // Get settings handlers
        add_action('wp_ajax_mailchimp_get_global_settings', [$this, 'ajax_get_global_settings']);
        add_action('wp_ajax_mailchimp_get_form_settings', [$this, 'ajax_get_form_settings']);
        
        // Debug: Log that registration is complete
        error_log('Mailchimp Integration: AJAX handlers registered successfully');
    }

    /**
     * Simple test AJAX handler
     */
    public function ajax_test_simple(): void {
        error_log('Mailchimp Simple Test AJAX Handler Called');
        wp_send_json_success('Mailchimp AJAX handler is working!');
    }

    /**
     * Get authentication fields
     */
    public function getAuthFields(): array {
        return [
            [
                'id' => 'api_key',
                'label' => $this->__('API Key'),
                'type' => 'password',
                'required' => true,
                'description' => $this->__('Your Mailchimp API key. Find it in your Mailchimp account under Account > Extras > API keys.'),
                'placeholder' => $this->__('Enter your Mailchimp API key...')
            ]
        ];
    }

    /**
     * Test API connection with enhanced validation
     */
    public function testConnection(array $credentials): array {
        $api_key = $credentials['api_key'] ?? '';
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => 'API key is required'
            ];
        }

        // Validate API key format - be more flexible with format
        if (!preg_match('/^[a-f0-9]{32}-[a-z0-9]+$/', $api_key)) {
            return [
                'success' => false,
                'error' => 'Invalid API key format. Expected format: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-xxx'
            ];
        }

        $dc = $this->extractDatacenter($api_key);
        if (!$dc) {
            return [
                'success' => false,
                'error' => 'Could not extract datacenter from API key'
            ];
        }

        // Test with ping endpoint first
        $ping_response = $this->makeMailchimpApiRequest('GET', '/ping', $api_key, $dc);
        
        if (!$ping_response) {
            return [
                'success' => false,
                'error' => 'No response from Mailchimp API'
            ];
        }

        if (isset($ping_response['status']) && $ping_response['status'] >= 400) {
            $error_message = $ping_response['detail'] ?? $ping_response['title'] ?? 'Authentication failed';
            
            // Provide specific error messages for common issues
            if ($ping_response['status'] === 401) {
                $error_message = 'Invalid API key. Please check your Mailchimp API key.';
            } elseif ($ping_response['status'] === 403) {
                $error_message = 'API key does not have sufficient permissions.';
            } elseif ($ping_response['status'] === 404) {
                $error_message = 'Mailchimp API endpoint not found. Check your datacenter.';
            } elseif ($ping_response['status'] === 400) {
                $error_message = 'Bad request. Please check your API key format and datacenter.';
            }

            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        // If ping succeeds, get account info for additional validation
        $account_response = $this->makeMailchimpApiRequest('GET', '/', $api_key, $dc);
        
        if ($account_response && !isset($account_response['status'])) {
            return [
                'success' => true,
                'message' => 'Connection successful!',
                'data' => [
                    'account_name' => $account_response['account_name'] ?? '',
                    'email' => $account_response['email'] ?? '',
                    'total_subscribers' => $account_response['total_subscribers'] ?? 0,
                    'datacenter' => $dc
                ]
            ];
        } elseif ($account_response && isset($account_response['status'])) {
            $error_message = $account_response['detail'] ?? $account_response['title'] ?? 'Failed to get account information';
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        return [
            'success' => false,
            'error' => 'Connection test completed but failed to verify account details'
        ];
    }

    /**
     * Test basic connectivity to Mailchimp servers
     */
    private function testBasicConnectivity(string $dc): array {
        $test_url = "https://{$dc}.api.mailchimp.com";
        
        $response = wp_remote_get($test_url, [
            'timeout' => 10,
            'sslverify' => true,
            'headers' => [
                'User-Agent' => 'MavlersCF-Integrations/' . MAVLERS_CF_INTEGRATIONS_VERSION
            ]
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            // Provide specific guidance for common connection issues
            if (strpos($error_message, 'SSL') !== false || strpos($error_message, 'certificate') !== false) {
                $error_message = 'SSL certificate verification failed. Your server may have outdated SSL certificates.';
            } elseif (strpos($error_message, 'resolve') !== false) {
                $error_message = 'DNS resolution failed. Unable to resolve Mailchimp server address.';
            } elseif (strpos($error_message, 'timeout') !== false) {
                $error_message = 'Connection timeout. Your server may be behind a firewall blocking external connections.';
            }
            
            return [
                'success' => false,
                'error' => 'Network connectivity issue: ' . $error_message
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        
        // We expect a 401 or other response here since we're not sending auth
        // Any response means we can connect to Mailchimp servers
        if ($status_code === 0) {
            return [
                'success' => false,
                'error' => 'Unable to connect to Mailchimp servers. Please check your server\'s internet connection.'
            ];
        }

        return ['success' => true];
    }

    /**
     * Get available actions
     */
    public function getAvailableActions(): array {
        return [
            'subscribe' => [
                'label' => $this->language_manager->translate('Subscribe to Audience'),
                'description' => $this->language_manager->translate('Add contact to a Mailchimp audience/list')
            ],
            'update_subscriber' => [
                'label' => $this->language_manager->translate('Update Subscriber'),
                'description' => $this->language_manager->translate('Update existing subscriber information')
            ],
            'unsubscribe' => [
                'label' => $this->language_manager->translate('Unsubscribe'),
                'description' => $this->language_manager->translate('Remove subscriber from audience')
            ]
        ];
    }

    /**
     * Get comprehensive settings fields
     */
    public function getSettingsFields(): array {
        return [
            [
                'id' => 'enable_analytics',
                'label' => $this->__('Global Analytics Tracking'),
                'type' => 'checkbox',
                'description' => $this->__('Enable analytics tracking for all Mailchimp submissions'),
                'default' => true
            ],
            [
                'id' => 'enable_webhooks',
                'label' => $this->__('Global Webhook Sync'),
                'type' => 'checkbox',
                'description' => $this->__('Enable webhook synchronization for real-time updates'),
                'default' => false
            ],
            [
                'id' => 'batch_processing',
                'label' => $this->__('Global Batch Processing'),
                'type' => 'checkbox',
                'description' => $this->__('Process all submissions in batches for better performance'),
                'default' => true
            ]
        ];
    }

    /**
     * Get form-specific settings fields
     */
    public function getFormSettingsFields(): array {
        return [
            [
                'id' => 'audience_id',
                'label' => $this->__('Audience'),
                'type' => 'select',
                'required' => true,
                'description' => $this->__('Select the Mailchimp audience to add subscribers to'),
                'options' => 'dynamic',
                'depends_on' => 'api_key'
            ],
            [
                'id' => 'double_optin',
                'label' => $this->__('Double Opt-in'),
                'type' => 'checkbox',
                'description' => $this->__('Require subscribers to confirm their email address'),
                'default' => true
            ],
            [
                'id' => 'update_existing',
                'label' => $this->__('Update Existing'),
                'type' => 'checkbox',
                'description' => $this->__('Update existing subscribers instead of creating duplicates'),
                'default' => false
            ],
            [
                'id' => 'tags',
                'label' => $this->__('Tags'),
                'type' => 'text',
                'description' => $this->__('Comma-separated list of tags to add to subscribers'),
                'placeholder' => $this->__('tag1, tag2, tag3')
            ]
        ];
    }

    /**
     * Override parent validation for global settings
     */
    public function validateSettings(array $settings): array {
        $errors = [];

        if (empty($settings['api_key'])) {
            $errors[] = 'API key is required';
        }
        
        return $errors;
    }

    /**
     * Get enhanced field mapping
     */
    public function getFieldMapping(string $action): array {
        $base_mapping = [
            'email' => [
                'label' => $this->__('Email Address'),
                'required' => true,
                'type' => 'email',
                'merge_field' => 'EMAIL'
            ],
            'first_name' => [
                'label' => $this->__('First Name'),
                'required' => false,
                'type' => 'text',
                'merge_field' => 'FNAME'
            ],
            'last_name' => [
                'label' => $this->__('Last Name'),
                'required' => false,
                'type' => 'text',
                'merge_field' => 'LNAME'
            ]
        ];

        return $base_mapping;
    }

    /**
     * Process form submission with comprehensive features
     */
    public function processSubmission(int $submission_id, array $form_data, array $settings = []): array {
        // Debug logging
        error_log('DEBUG: Mailchimp processSubmission called - Submission ID: ' . $submission_id);
        error_log('DEBUG: Mailchimp form_data: ' . print_r($form_data, true));
        error_log('DEBUG: Mailchimp settings: ' . print_r($settings, true));
        
        // Get settings if not provided
        if (empty($settings)) {
            $form_id = $form_data['form_id'] ?? 0;
            if ($form_id) {
                $form_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
                $settings = $form_settings['mailchimp'] ?? [];
                error_log('DEBUG: Mailchimp retrieved settings from post meta: ' . print_r($settings, true));
            }
        }
        
        // Check if globally connected
        $global_settings = $this->get_global_settings();
        error_log('DEBUG: Mailchimp global settings: ' . print_r($global_settings, true));
        
        if (!$this->is_globally_connected()) {
            error_log('DEBUG: Mailchimp not globally connected - API key missing');
            $this->logError('Mailchimp not globally connected', ['submission_id' => $submission_id]);
            return [
                'success' => false,
                'error' => $this->language_manager->translate('Mailchimp not configured')
            ];
        }

        $audience_id = $settings['audience_id'] ?? '';
        if (empty($audience_id)) {
            error_log('DEBUG: Mailchimp audience ID not specified');
            $this->logError('Audience ID not specified', ['submission_id' => $submission_id]);
            return [
                'success' => false,
                'error' => $this->language_manager->translate('Audience ID not specified')
            ];
        }

        error_log('DEBUG: Mailchimp proceeding with submission processing');
        
        // Check if batch processing is enabled - TEMPORARILY DISABLED FOR TESTING
        $batch_processing = false; // $settings['batch_processing'] ?? true;
        error_log('DEBUG: Mailchimp batch_processing setting: ' . ($batch_processing ? 'enabled' : 'disabled'));
        
        if ($batch_processing) {
            error_log('DEBUG: Mailchimp batch processing is enabled, checking batch processor');
            if ($this->batch_processor) {
                error_log('DEBUG: Mailchimp batch processor available, adding to queue');
                $queue_result = $this->batch_processor->add_to_batch_queue($form_data, $settings, $submission_id);
                
                if ($queue_result) {
                    error_log('DEBUG: Mailchimp successfully added to batch queue');
                    // Track analytics for queued submission
                    if ($this->analytics_manager && ($settings['enable_analytics'] ?? true)) {
                        $this->analytics_manager->track_subscription(
                            $form_data['email'] ?? '',
                            $submission_id,
                            $audience_id,
                            array('method' => 'batch', 'queue_id' => $queue_result)
                        );
                    }
                    
                    return [
                        'success' => true,
                        'message' => $this->language_manager->translate('Subscription queued for processing')
                    ];
                } else {
                    error_log('DEBUG: Mailchimp batch queue failed, falling back to immediate processing');
                    return $this->process_submission_immediate($submission_id, $form_data, $settings);
                }
            } else {
                error_log('DEBUG: Mailchimp batch processor not available, using immediate processing');
                return $this->process_submission_immediate($submission_id, $form_data, $settings);
            }
        } else {
            error_log('DEBUG: Mailchimp batch processing disabled, using immediate processing');
            return $this->process_submission_immediate($submission_id, $form_data, $settings);
        }
    }

    /**
     * Process submission immediately
     */
    private function process_submission_immediate(int $submission_id, array $form_data, array $settings): array {
        error_log('DEBUG: Mailchimp process_submission_immediate called');
        error_log('DEBUG: Mailchimp submission_id: ' . $submission_id);
        error_log('DEBUG: Mailchimp form_data: ' . print_r($form_data, true));
        error_log('DEBUG: Mailchimp settings: ' . print_r($settings, true));
        
        $global_settings = $this->get_global_settings();
        $api_key = $global_settings['api_key'] ?? '';
        $audience_id = $settings['audience_id'] ?? '';

        error_log('DEBUG: Mailchimp api_key: ' . ($api_key ? 'present' : 'missing'));
        error_log('DEBUG: Mailchimp audience_id: ' . $audience_id);

        // Enhanced field mapping
        $mapped_data = $this->enhanced_map_form_data($form_data, $settings);
        error_log('DEBUG: Mailchimp mapped_data: ' . print_r($mapped_data, true));

        // Subscribe to audience
        $result = $this->subscribeToAudience($mapped_data, $settings, $api_key, $audience_id, $submission_id);
        error_log('DEBUG: Mailchimp subscribeToAudience result: ' . print_r($result, true));

        // Track analytics
        if ($this->analytics_manager && ($settings['enable_analytics'] ?? true)) {
            if ($result['success']) {
                $this->analytics_manager->track_subscription(
                    $form_data['email'] ?? '',
                    $submission_id,
                    $audience_id,
                    array('method' => 'immediate')
                );
            } else {
                $this->analytics_manager->track_subscription_error(
                    $form_data['email'] ?? '',
                    $submission_id,
                    $audience_id,
                    array('error' => $result['error'] ?? 'Unknown error')
                );
            }
        }

        return $result;
    }

    /**
     * Enhanced form data mapping
     */
    private function enhanced_map_form_data(array $form_data, array $settings): array {
        $mapped = [];

        // Get the actual field data - handle nested structure
        $field_data = $form_data;
        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            $field_data = $form_data['fields'];
        }

        // Get field mapping from settings first, then fall back to enhanced mapping
        $field_mapping = $settings['field_mapping'] ?? [];

        if (empty($field_mapping)) {
            // Fall back to enhanced field mapping from options table
            $form_id = $settings['form_id'] ?? $form_data['form_id'] ?? 0;
            $field_mapping = $this->get_enhanced_field_mapping($form_id);
        }

        if (!empty($field_mapping)) {
            // Use field mapping
            foreach ($field_mapping as $form_field => $mailchimp_field) {
                if (isset($field_data[$form_field]) && !empty($field_data[$form_field])) {
                    $mapped[$mailchimp_field] = $field_data[$form_field];
                }
            }
        } else {
            // Fall back to basic mapping
            $mapped = $this->mapFormDataToMailchimp($field_data, $settings);
        }

        return $mapped;
    }

    /**
     * Subscribe to audience with comprehensive error handling
     */
    private function subscribeToAudience(array $data, array $settings, string $api_key, string $audience_id, int $submission_id): array {
        try {
            error_log('DEBUG: Mailchimp subscribeToAudience called');
            error_log('DEBUG: Mailchimp data: ' . print_r($data, true));
            error_log('DEBUG: Mailchimp audience_id: ' . $audience_id);
            error_log('DEBUG: Mailchimp api_key present: ' . (!empty($api_key) ? 'yes' : 'no'));
            
            // Extract datacenter from API key
            $dc = $this->extractDatacenter($api_key);
            if (!$dc) {
                error_log('DEBUG: Mailchimp failed to extract datacenter from API key');
                return ['success' => false, 'error' => 'Invalid API key format'];
            }
            
            error_log('DEBUG: Mailchimp datacenter: ' . $dc);
            
            // Prepare subscription data
            $email_address = $data['EMAIL'] ?? $data['email'] ?? '';
            $merge_fields = [];
            
            // Extract merge fields (excluding EMAIL which is the primary email)
            foreach ($data as $key => $value) {
                if ($key !== 'EMAIL' && $key !== 'email' && !empty($value)) {
                    $merge_fields[$key] = $value;
                }
            }
            
            $subscription_data = [
                'email_address' => $email_address,
                'status' => $settings['double_optin'] ? 'pending' : 'subscribed',
                'merge_fields' => $merge_fields
            ];
            
            // Add tags if specified
            if (!empty($settings['tags'])) {
                $subscription_data['tags'] = explode(',', $settings['tags']);
            }
            
            error_log('DEBUG: Mailchimp subscription_data: ' . print_r($subscription_data, true));
            
            // Make API request
            $endpoint = "/lists/{$audience_id}/members";
            $method = $settings['update_existing'] ? 'PUT' : 'POST';
            
            if ($method === 'PUT') {
                // For updates, we need to hash the email
                $email_hash = md5(strtolower($subscription_data['email_address']));
                $endpoint = "/lists/{$audience_id}/members/{$email_hash}";
            }
            
            error_log('DEBUG: Mailchimp making API request - Method: ' . $method . ', Endpoint: ' . $endpoint);
            
            $response = $this->makeMailchimpApiRequest($method, $endpoint, $api_key, $dc, $subscription_data);
            
            error_log('DEBUG: Mailchimp API response: ' . print_r($response, true));
            
            if ($response && isset($response['id']) && !empty($response['id'])) {
                error_log('DEBUG: Mailchimp subscription successful');
                return ['success' => true, 'message' => 'Subscription successful'];
            } else {
                $error_message = $response['detail'] ?? $response['message'] ?? 'Unknown error';
                error_log('DEBUG: Mailchimp subscription failed: ' . $error_message);
                return ['success' => false, 'error' => $error_message];
            }
            
        } catch (\Exception $e) {
            error_log('DEBUG: Mailchimp exception in subscribeToAudience: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Maybe register webhook for audience
     */
    private function maybe_register_webhook(string $api_key, string $audience_id): void {
        if ($this->webhook_handler) {
            $webhook_status = $this->webhook_handler->get_webhook_status($api_key, $audience_id);
            
            if (!$webhook_status['registered']) {
                $this->webhook_handler->register_webhook($api_key, $audience_id);
            }
        }
    }

    /**
     * Basic form data mapping (fallback)
     */
    private function mapFormDataToMailchimp(array $form_data, array $settings): array {
        $mapped = [];
        
        // Map common fields with multiple variations
        $field_map = [
            'email' => 'EMAIL',
            'first_name' => 'FNAME',
            'last_name' => 'LNAME',
            'name' => 'FNAME', // fallback
            'email_address' => 'EMAIL',
            'firstname' => 'FNAME',
            'lastname' => 'LNAME',
            'fname' => 'FNAME',
            'lname' => 'LNAME'
        ];

        foreach ($field_map as $form_field => $mailchimp_field) {
            if (isset($form_data[$form_field]) && !empty($form_data[$form_field])) {
                $mapped[$mailchimp_field] = $form_data[$form_field];
            }
        }

        // Also check for any field that contains 'email' in the key
        if (!isset($mapped['EMAIL'])) {
            foreach ($form_data as $key => $value) {
                if (stripos($key, 'email') !== false && !empty($value)) {
                    $mapped['EMAIL'] = $value;
                    break;
                }
            }
        }

        // Also check for any field that contains 'name' in the key for first name
        if (!isset($mapped['FNAME'])) {
            foreach ($form_data as $key => $value) {
                if ((stripos($key, 'first') !== false || stripos($key, 'name') !== false) && !empty($value)) {
                    $mapped['FNAME'] = $value;
                    break;
                }
            }
        }

        return $mapped;
    }

    /**
     * Get global settings for Mailchimp
     */
    public function get_global_settings() {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        return $global_settings['mailchimp'] ?? [];
    }

    /**
     * Check if Mailchimp is globally connected
     */
    public function is_globally_connected(): bool {
        $settings = $this->get_global_settings();
        return !empty($settings['api_key']);
    }

    /**
     * Get enhanced field mapping
     */
    public function get_enhanced_field_mapping(int $form_id): array {
        if (!$form_id) {
            return [];
        }

        $mapping = get_option("mavlers_cf_mailchimp_field_mapping_{$form_id}", []);
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Save enhanced field mapping
     */
    public function save_enhanced_field_mapping(int $form_id, array $mapping): bool {
        if (!$form_id) {
            return false;
        }

        return update_option("mavlers_cf_mailchimp_field_mapping_{$form_id}", $mapping);
    }

    /**
     * Extract datacenter from API key
     */
    private function extractDatacenter(string $api_key): ?string {
        $parts = explode('-', $api_key);
        $datacenter = end($parts) ?: null;
        
        // Log for debugging
        error_log('Mailchimp Extract Datacenter - API Key Length: ' . strlen($api_key));
        error_log('Mailchimp Extract Datacenter - Parts: ' . print_r($parts, true));
        error_log('Mailchimp Extract Datacenter - Result: ' . ($datacenter ?: 'null'));
        
        return $datacenter;
    }

    /**
     * Make Mailchimp API request
     */
    private function makeMailchimpApiRequest(string $method, string $endpoint, string $api_key, string $dc, array $data = []): ?array {
        $url = "https://{$dc}.api.mailchimp.com/3.0" . $endpoint;
        
        // Define version safely
        $version = defined('MAVLERS_CF_INTEGRATIONS_VERSION') ? MAVLERS_CF_INTEGRATIONS_VERSION : '1.0.0';
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json',
                'User-Agent' => 'MavlersCF-Integrations/' . $version . ' WordPress/' . get_bloginfo('version')
            ],
            'timeout' => 30,
            'sslverify' => true
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($data);
        }

        // Log request for debugging
        error_log('Mailchimp API Request - URL: ' . $url);
        error_log('Mailchimp API Request - Method: ' . $method);
        error_log('Mailchimp API Request - Headers: ' . print_r($args['headers'], true));

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Mailchimp API Request Error: ' . $error_message);
            return ['status' => 500, 'detail' => $error_message];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        // Log response for debugging
        error_log('Mailchimp API Response - Status: ' . $status_code);
        error_log('Mailchimp API Response - Body: ' . $body);

        // Handle different status codes
        if ($status_code >= 400) {
            $error_detail = 'Unknown error';
            if ($decoded && isset($decoded['detail'])) {
                $error_detail = $decoded['detail'];
            } elseif ($decoded && isset($decoded['title'])) {
                $error_detail = $decoded['title'];
            } elseif ($decoded && isset($decoded['error'])) {
                $error_detail = $decoded['error'];
            }
            
            error_log('Mailchimp API Error - Status: ' . $status_code . ', Detail: ' . $error_detail);
            return array_merge($decoded ?: [], ['status' => $status_code]);
        }

        return $decoded;
    }

    /**
     * Get audiences with caching
     */
    public function getAudiences(string $api_key = ''): array {
        if (empty($api_key)) {
            $global_settings = $this->get_global_settings();
            $api_key = $global_settings['api_key'] ?? '';
        }

        if (empty($api_key)) {
            return [];
        }

        $dc = $this->extractDatacenter($api_key);
        if (!$dc) {
            return [];
        }

        // Check cache
        $cache_key = 'mailchimp_audiences_' . md5($api_key);
        $cached = wp_cache_get($cache_key, 'mavlers_cf_mailchimp');
        
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->makeMailchimpApiRequest('GET', '/lists?count=100', $api_key, $dc);

        if ($response && isset($response['lists'])) {
            $audiences = [];
            foreach ($response['lists'] as $list) {
                $audiences[] = [
                    'id' => $list['id'],
                    'name' => $list['name'],
                    'member_count' => $list['stats']['member_count'] ?? 0
                ];
            }

            // Cache for 1 hour
            wp_cache_set($cache_key, $audiences, 'mavlers_cf_mailchimp', 3600);
            
            return $audiences;
        }

        return [];
    }

    /**
     * Enqueue comprehensive assets
     */
    public function enqueue_comprehensive_assets($hook): void {
        if (strpos($hook, 'mavlers-cf') === false && strpos($hook, 'mavlers-contact-forms') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'mavlers-cf-mailchimp',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/css/admin/mailchimp.css',
            [],
            $this->version
        );

        // Enqueue main JS
        wp_enqueue_script(
            'mavlers-cf-mailchimp',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/js/admin/mailchimp.js',
            ['jquery', 'wp-util'],
            $this->version,
            true
        );

        // Also enqueue form-specific JS for form settings pages
        wp_enqueue_script(
            'mavlers-cf-mailchimp-form',
            MAVLERS_CF_INTEGRATIONS_URL . 'assets/js/admin/mailchimp-form.js',
            ['jquery', 'wp-util', 'mavlers-cf-mailchimp'],
            $this->version,
            true
        );

        // Ensure components are initialized before using them
        if (!$this->language_manager) {
            $this->init_components();
        }

        // Localize with comprehensive data for both scripts
        $localization_data = [
            'nonce' => wp_create_nonce('mavlers_cf_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'isRTL' => $this->language_manager ? $this->language_manager->is_rtl() : false,
            'currentLanguage' => $this->language_manager ? $this->language_manager->get_current_language() : 'en_US',
            'strings' => $this->language_manager ? $this->language_manager->get_language_strings_for_js() : [],
            'version' => $this->version
        ];

        wp_localize_script('mavlers-cf-mailchimp', 'mavlersCFMailchimp', $localization_data);
        wp_localize_script('mavlers-cf-mailchimp-form', 'mavlersCFMailchimp', $localization_data);
    }

    /**
     * Initialize webhook endpoints
     */
    public function init_webhook_endpoints(): void {
        if ($this->webhook_handler) {
            // Webhook endpoints are initialized by the WebhookHandler
        }
    }

    // AJAX Handlers

    /**
     * AJAX: Test Mailchimp connection
     */
    public function ajax_test_connection(): void {
        // Debug: Log that the handler is being called
        error_log('Mailchimp AJAX Test Connection Handler Called');
        error_log('Mailchimp AJAX Test Connection - POST Data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            error_log('Mailchimp AJAX Test Connection - Nonce verification failed');
            wp_send_json([
                'success' => false,
                'error' => 'Security check failed'
            ]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            error_log('Mailchimp AJAX Test Connection - Insufficient permissions');
            wp_send_json([
                'success' => false,
                'error' => 'Insufficient permissions'
            ]);
        }
        
        // Extract API key from different possible sources
        $api_key = '';
        
        // Check direct api_key parameter first (most common)
        if (isset($_POST['api_key']) && !empty($_POST['api_key'])) {
            $api_key = sanitize_text_field($_POST['api_key']);
        } elseif (isset($_POST['credentials']) && is_array($_POST['credentials'])) {
            $api_key = sanitize_text_field($_POST['credentials']['api_key'] ?? '');
        } elseif (isset($_POST['settings']) && is_array($_POST['settings'])) {
            $api_key = sanitize_text_field($_POST['settings']['api_key'] ?? '');
        } elseif (isset($_POST['settings']) && is_string($_POST['settings'])) {
            $settings = json_decode(stripslashes($_POST['settings']), true);
            $api_key = sanitize_text_field($settings['api_key'] ?? '');
        }
        
        // Log for debugging
        error_log('Mailchimp Test Connection - API Key Length: ' . strlen($api_key));
        error_log('Mailchimp Test Connection - POST Data: ' . print_r($_POST, true));
        
        if (empty($api_key)) {
            wp_send_json([
                'success' => false,
                'error' => 'API key is required'
            ]);
        }

        // Validate API key format
        if (!preg_match('/^[a-f0-9]{32}-[a-z0-9]+$/', $api_key)) {
            wp_send_json([
                'success' => false,
                'error' => 'Invalid API key format. Expected format: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-xxx'
            ]);
        }
        
        try {
            $result = $this->testConnection(['api_key' => $api_key]);
            wp_send_json($result);
        } catch (Exception $e) {
            error_log('Mailchimp Test Connection Exception: ' . $e->getMessage());
            wp_send_json([
                'success' => false,
                'error' => 'Test connection failed: ' . $e->getMessage()
            ]);
        }
    }

    public function ajax_get_audiences(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $audiences = $this->getAudiences($api_key);
        
        wp_send_json_success($audiences);
    }

    /**
     * AJAX: Save Mailchimp global settings
     */
    public function ajax_save_global_settings(): void {
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Extract settings from different possible formats
        $settings = [];
        
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            $settings = $_POST['settings'];
        } elseif (isset($_POST['settings']) && is_string($_POST['settings'])) {
            $settings = json_decode(stripslashes($_POST['settings']), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Invalid settings format');
                return;
            }
        } else {
            // Fallback to direct POST data
            $settings = [
                'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
                'enable_analytics' => !empty($_POST['enable_analytics']),
                'enable_webhooks' => !empty($_POST['enable_webhooks']),
                'batch_processing' => !empty($_POST['batch_processing'])
            ];
        }
        
        // Validate settings
        $validation_result = $this->validateSettings($settings);
        if (!empty($validation_result)) {
            wp_send_json_error('Settings validation failed: ' . implode(', ', $validation_result));
            return;
        }
        
        // Save settings using the abstract method
        $result = $this->saveGlobalSettings($settings);
        
        if ($result) {
            wp_send_json_success([
                'message' => 'Settings saved successfully!',
                'configured' => true
            ]);
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    /**
     * AJAX: Save Mailchimp global settings (v2)
     */
    public function ajax_save_global_settings_v2(): void {
        // Basic nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Extract settings from different possible formats
        $settings = [];
        
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            $settings = $_POST['settings'];
        } elseif (isset($_POST['settings']) && is_string($_POST['settings'])) {
            $settings = json_decode(stripslashes($_POST['settings']), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Invalid settings format');
                return;
            }
        } else {
            // Fallback to direct POST data
            $settings = [
                'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
                'datacenter' => sanitize_text_field($_POST['datacenter'] ?? ''),
                'enable_analytics' => !empty($_POST['enable_analytics']),
                'enable_webhooks' => !empty($_POST['enable_webhooks']),
                'batch_processing' => !empty($_POST['batch_processing'])
            ];
        }
        
        // Validate settings
        $validation_result = $this->validateSettings($settings);
        if (!empty($validation_result)) {
            wp_send_json_error('Settings validation failed: ' . implode(', ', $validation_result));
            return;
        }
        
        // Save settings using the abstract method
        $result = $this->saveGlobalSettings($settings);
        
        if ($result) {
            wp_send_json_success([
                'message' => 'Settings saved successfully!',
                'configured' => true
            ]);
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }



    public function ajax_save_form_settings(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Form ID is required') : 'Form ID is required';
            wp_send_json_error($error_msg);
        }

        // Get settings from POST data
        $settings = [];
        
        // Check if settings are sent as an object
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            $settings = $_POST['settings'];
        } else {
            // Fallback to individual POST parameters
            $settings = [
                'enabled' => !empty($_POST['enabled']) && ($_POST['enabled'] === true || $_POST['enabled'] === 'true' || $_POST['enabled'] === '1'),
                'audience_id' => sanitize_text_field($_POST['audience_id'] ?? ''),
                'double_optin' => !empty($_POST['double_optin']) && ($_POST['double_optin'] === true || $_POST['double_optin'] === 'true' || $_POST['double_optin'] === '1'),
                'update_existing' => !empty($_POST['update_existing']) && ($_POST['update_existing'] === true || $_POST['update_existing'] === 'true' || $_POST['update_existing'] === '1'),
                'tags' => sanitize_text_field($_POST['tags'] ?? ''),
                'field_mapping' => $_POST['field_mapping'] ?? []
            ];
        }
        
        // Sanitize settings (but preserve field_mapping as array)
        $field_mapping = $settings['field_mapping'] ?? [];
        unset($settings['field_mapping']); // Remove from settings before sanitizing
        
        // Sanitize other settings
        $settings = array_map('sanitize_text_field', $settings);
        
        // Restore field_mapping as array
        $settings['field_mapping'] = $field_mapping;
        
        // Ensure field_mapping is an array
        if (!is_array($settings['field_mapping'])) {
            $settings['field_mapping'] = [];
        }

        // Try to save using the abstract method first
        $result = $this->saveFormSettings($form_id, $settings);
        
        // If that fails, try alternative storage methods
        if (!$result) {
            // Try saving to post meta
            $existing_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
            if (!is_array($existing_meta)) {
                $existing_meta = [];
            }
            $existing_meta['mailchimp'] = $settings;
            $result = update_post_meta($form_id, '_mavlers_cf_integrations', $existing_meta);
            
            // If that also fails, try options table (legacy method)
            if (!$result) {
                $option_key = "mavlers_cf_mailchimp_form_{$form_id}";
                $result = update_option($option_key, $settings);
            }
        }
        
        if ($result) {
            $success_msg = $this->language_manager ? $this->language_manager->translate('Form settings saved successfully') : 'Form settings saved successfully';
            wp_send_json_success($success_msg);
        } else {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Failed to save form settings') : 'Failed to save form settings';
            wp_send_json_error($error_msg);
        }
    }

    /**
     * AJAX handler for auto-saving audience selection
     */
    public function ajax_save_audience_selection(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');

        $form_id = intval($_POST['form_id'] ?? 0);
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');

        if (!$form_id) {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Form ID is required') : 'Form ID is required';
            wp_send_json_error($error_msg);
        }

        // Get existing form integration settings
        global $wpdb;
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
            $form_id,
            '_mavlers_cf_integrations'
        ));
        
        $integration_settings = [];
        if ($meta_value) {
            $integration_settings = json_decode($meta_value, true);
            if (!is_array($integration_settings)) {
                $integration_settings = [];
            }
        }
        
        // Update Mailchimp settings
        if (!isset($integration_settings['mailchimp'])) {
            $integration_settings['mailchimp'] = [];
        }
        
        $integration_settings['mailchimp']['audience_id'] = $audience_id;
        
        // Save back to database
        $result = $wpdb->replace(
            $meta_table,
            [
                'form_id' => $form_id,
                'meta_key' => '_mavlers_cf_integrations',
                'meta_value' => json_encode($integration_settings)
            ],
            ['%d', '%s', '%s']
        );
        
        if ($result !== false) {
            $success_msg = $this->language_manager ? $this->language_manager->translate('Audience selection saved') : 'Audience selection saved';
            wp_send_json_success($success_msg);
        } else {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Failed to save audience selection') : 'Failed to save audience selection';
            wp_send_json_error($error_msg);
        }
    }

    public function ajax_get_form_fields(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Form ID is required') : 'Form ID is required';
            wp_send_json_error($error_msg);
        }

        // Get form fields (this would integrate with your form builder)
        $form_fields = $this->get_form_fields($form_id);
        
        wp_send_json_success($form_fields);
    }

    public function ajax_save_field_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $mapping = $_POST['mapping'] ?? [];
        
        if (!$form_id) {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Form ID is required') : 'Form ID is required';
            wp_send_json_error($error_msg);
        }

        $result = $this->save_enhanced_field_mapping($form_id, $mapping);
        
        if ($result) {
            $success_msg = $this->language_manager ? $this->language_manager->translate('Field mapping saved successfully') : 'Field mapping saved successfully';
            wp_send_json_success($success_msg);
        } else {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Failed to save field mapping') : 'Failed to save field mapping';
            wp_send_json_error($error_msg);
        }
    }

    public function ajax_get_field_mapping(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Form ID is required') : 'Form ID is required';
            wp_send_json_error($error_msg);
        }

        $mapping = $this->get_enhanced_field_mapping($form_id);
        
        wp_send_json_success($mapping);
    }

    public function ajax_auto_map_fields(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        
        if (!$form_id || !$audience_id) {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Form ID and Audience ID are required') : 'Form ID and Audience ID are required';
            wp_send_json_error($error_msg);
        }

        // Generate automatic mapping
        $auto_mapping = $this->generate_automatic_mapping($form_id, $audience_id);
        
        wp_send_json_success($auto_mapping);
    }

    public function ajax_get_audience_merge_fields(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        
        if (!$audience_id) {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Audience ID is required') : 'Audience ID is required';
            wp_send_json_error($error_msg);
        }

        // Ensure components are initialized
        if (!$this->custom_fields_manager) {
            $this->init_components();
        }

        if ($this->custom_fields_manager) {
            $result = $this->custom_fields_manager->get_merge_fields($audience_id);
            wp_send_json($result);
        } else {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Custom fields manager not available') : 'Custom fields manager not available';
            wp_send_json_error($error_msg);
        }
    }

    public function ajax_get_multilingual_interface(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->language_manager) {
            $this->language_manager->ajax_get_multilingual_interface();
        } else {
            $error_msg = 'Language manager not available';
            wp_send_json_error($error_msg);
        }
    }

    public function ajax_load_analytics_dashboard(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->analytics_manager) {
            $this->analytics_manager->ajax_get_analytics_data();
        } else {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Analytics manager not available') : 'Analytics manager not available';
            wp_send_json_error($error_msg);
        }
    }

    public function ajax_export_analytics(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if ($this->analytics_manager) {
            $this->analytics_manager->ajax_export_analytics();
        } else {
            $error_msg = $this->language_manager ? $this->language_manager->translate('Analytics manager not available') : 'Analytics manager not available';
            wp_send_json_error($error_msg);
        }
    }

    /**
     * AJAX: Get Mailchimp global settings
     */
    public function ajax_get_global_settings(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $global_settings = $this->get_global_settings();
        wp_send_json_success($global_settings);
    }

    /**
     * AJAX: Get Mailchimp form settings
     */
    public function ajax_get_form_settings(): void {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            wp_send_json_error($this->language_manager ? $this->language_manager->translate('Form ID is required') : 'Form ID is required');
            return;
        }

        $form_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        $mailchimp_settings = $form_settings['mailchimp'] ?? [];

        // Ensure 'field_mapping' is an array, even if empty
        if (!is_array($mailchimp_settings['field_mapping'])) {
            $mailchimp_settings['field_mapping'] = [];
        }

        wp_send_json_success($mailchimp_settings);
    }


    /**
     * Helper methods - Get REAL form fields from database
     */
    private function get_form_fields(int $form_id): array {
        global $wpdb;
        
        if (!$form_id) {
            return [];
        }
        
        // Get form from database
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));
        
        if (!$form || !$form->fields) {
            return [];
        }
        
        // Parse form fields JSON
        $fields_data = json_decode($form->fields, true);
        if (!is_array($fields_data)) {
            return [];
        }
        
        $processed_fields = [];
        
        foreach ($fields_data as $field) {
            if (!isset($field['id']) || !isset($field['label'])) {
                continue;
            }
            
            $field_id = $field['id'];
            $field_type = $field['type'] ?? 'text';
            $field_label = $field['label'];
            $required = $field['required'] ?? false;
            
            $processed_fields[$field_id] = [
                'id' => $field_id,
                'label' => $field_label,
                'type' => $field_type,
                'required' => $required,
                'name' => $field['name'] ?? $field_id,
                'placeholder' => $field['placeholder'] ?? '',
                'description' => $field['description'] ?? ''
            ];
        }
        
        return $processed_fields;
    }

    private function generate_automatic_mapping(int $form_id, string $audience_id): array {
        $form_fields = $this->get_form_fields($form_id);
        $mapping = [];

        // Basic auto-mapping logic
        $auto_map_rules = [
            'email' => 'EMAIL',
            'first_name' => 'FNAME',
            'last_name' => 'LNAME'
        ];

        foreach ($form_fields as $field_name => $field_config) {
            if (isset($auto_map_rules[$field_name])) {
                $mapping[$field_name] = $auto_map_rules[$field_name];
            }
        }

        return $mapping;
    }

    /**
     * Get component instances for external access
     */
    public function get_custom_fields_manager() {
        return $this->custom_fields_manager;
    }

    public function get_language_manager() {
        return $this->language_manager;
    }

    public function get_analytics_manager() {
        return $this->analytics_manager;
    }

    public function get_webhook_handler() {
        return $this->webhook_handler;
    }

    public function get_batch_processor() {
        return $this->batch_processor;
    }
    
    /**
     * Safe translation helper
     */
    private function __($text, $fallback = null) {
        if ($this->language_manager) {
            return $this->language_manager->translate($text);
        }
        return $fallback ?: $text;
    }
    

} 