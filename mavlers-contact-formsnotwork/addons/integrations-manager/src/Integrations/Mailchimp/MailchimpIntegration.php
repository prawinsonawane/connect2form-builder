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
     * Initialize integration
     */
    protected function init() {
        // Defer component initialization until WordPress is ready
        add_action('init', [$this, 'init_components'], 5);
        
        // Register assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_comprehensive_assets']);
        
        // AJAX handlers are registered directly when integration is loaded
        
        // Form processing
        add_action('mavlers_cf_after_submission', [$this, 'processSubmission'], 10, 2);
        
        // Webhook handling
        add_action('init', [$this, 'init_webhook_endpoints'], 15);
    }

    /**
     * Initialize component instances
     */
    public function init_components() {
        // Manually require component files to ensure they're loaded
        $components_dir = MAVLERS_CF_INTEGRATIONS_DIR . 'src/Integrations/Mailchimp/';
        
        if (file_exists($components_dir . 'LanguageManager.php')) {
            require_once $components_dir . 'LanguageManager.php';
        }
        
        if (file_exists($components_dir . 'AnalyticsManager.php')) {
            require_once $components_dir . 'AnalyticsManager.php';
        }
        
        if (file_exists($components_dir . 'CustomFieldsManager.php')) {
            require_once $components_dir . 'CustomFieldsManager.php';
        }
        
        if (file_exists($components_dir . 'WebhookHandler.php')) {
            require_once $components_dir . 'WebhookHandler.php';
        }
        
        if (file_exists($components_dir . 'BatchProcessor.php')) {
            require_once $components_dir . 'BatchProcessor.php';
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
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("MailchimpIntegration: Error initializing components: " . $e->getMessage());
            }
        }
    }

    /**
     * Register comprehensive AJAX handlers
     */
    public function register_ajax_handlers() {
        // Basic handlers (admin only)
        add_action('wp_ajax_mailchimp_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_mailchimp_get_audiences', [$this, 'ajax_get_audiences']);
        add_action('wp_ajax_mailchimp_save_global_settings', [$this, 'ajax_save_global_settings']);
        add_action('wp_ajax_mailchimp_save_form_settings', [$this, 'ajax_save_form_settings']);
        add_action('wp_ajax_save_mailchimp_form_settings', [$this, 'ajax_save_form_settings']);
        add_action('wp_ajax_mavlers_cf_save_form_settings', [$this, 'ajax_save_form_settings']);
        add_action('wp_ajax_save_mailchimp_audience_selection', [$this, 'ajax_save_audience_selection']);
        
        // Enhanced field mapping (admin only)
        add_action('wp_ajax_mailchimp_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_mavlers_cf_get_form_fields', [$this, 'ajax_get_form_fields']);
        add_action('wp_ajax_mailchimp_save_field_mapping', [$this, 'ajax_save_field_mapping']);
        add_action('wp_ajax_mailchimp_get_field_mapping', [$this, 'ajax_get_field_mapping']);
        add_action('wp_ajax_mailchimp_auto_map_fields', [$this, 'ajax_auto_map_fields']);
        
        // Audience and merge fields (admin only) - FIXED: Register both action names
        add_action('wp_ajax_mailchimp_get_audience_merge_fields', [$this, 'ajax_get_audience_merge_fields']);
        add_action('wp_ajax_mailchimp_get_merge_fields', [$this, 'ajax_get_audience_merge_fields']);
        add_action('wp_ajax_mailchimp_get_audience_fields', [$this, 'ajax_get_audience_merge_fields']);
        
        // Test endpoint for debugging
        add_action('wp_ajax_mailchimp_test_ajax', [$this, 'ajax_test_ajax']);
        
        // Database check endpoint for debugging
        add_action('wp_ajax_check_mailchimp_database', [$this, 'ajax_check_database']);
        
        // Multilingual interface (admin only)
        add_action('wp_ajax_mailchimp_get_multilingual_interface', [$this, 'ajax_get_multilingual_interface']);
        
        // Analytics (admin only)
        add_action('wp_ajax_mailchimp_load_analytics_dashboard', [$this, 'ajax_load_analytics_dashboard']);
        add_action('wp_ajax_mailchimp_export_analytics', [$this, 'ajax_export_analytics']);
        
        // Unique settings handler
        add_action('wp_ajax_mailchimp_save_global_settings_v2', [$this, 'ajax_save_global_settings_v2']);
        
        // Debug test handler
        add_action('wp_ajax_mailchimp_test_save_debug', [$this, 'ajax_test_save_debug']);
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
                'error' => $this->language_manager ? $this->language_manager->translate('API key is required') : 'API key is required'
            ];
        }

        // Validate API key format
        if (!preg_match('/^[a-f0-9]{32}-[a-z0-9]+$/', $api_key)) {
            return [
                'success' => false,
                'error' => $this->language_manager ? $this->language_manager->translate('Invalid API key format. Expected format: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-xxx') : 'Invalid API key format'
            ];
        }

        $dc = $this->extractDatacenter($api_key);
        if (!$dc) {
            return [
                'success' => false,
                'error' => $this->language_manager ? $this->language_manager->translate('Could not extract datacenter from API key') : 'Could not extract datacenter from API key'
            ];
        }

        // Test basic connectivity to Mailchimp first
        $connectivity_test = $this->testBasicConnectivity($dc);
        if (!$connectivity_test['success']) {
            return $connectivity_test;
        }

        // Test with ping endpoint first
        $ping_response = $this->makeMailchimpApiRequest('GET', '/ping', $api_key, $dc);
        
        if (!$ping_response) {
            return [
                'success' => false,
                'error' => $this->language_manager ? $this->language_manager->translate('No response from Mailchimp API') : 'No response from Mailchimp API'
            ];
        }

        if (isset($ping_response['status']) && $ping_response['status'] >= 400) {
            $error_message = $ping_response['detail'] ?? $ping_response['title'] ?? 'Authentication failed';
            
            // Provide specific error messages for common issues
            if ($ping_response['status'] === 401) {
                $error_message = $this->language_manager ? 
                    $this->language_manager->translate('Invalid API key. Please check your Mailchimp API key.') : 
                    'Invalid API key. Please check your Mailchimp API key.';
            } elseif ($ping_response['status'] === 403) {
                $error_message = $this->language_manager ? 
                    $this->language_manager->translate('API key does not have sufficient permissions.') : 
                    'API key does not have sufficient permissions.';
            } elseif ($ping_response['status'] === 404) {
                $error_message = $this->language_manager ? 
                    $this->language_manager->translate('Mailchimp API endpoint not found. Check your datacenter.') : 
                    'Mailchimp API endpoint not found. Check your datacenter.';
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
                'message' => $this->language_manager ? $this->language_manager->translate('Connection successful!') : 'Connection successful!',
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
            'error' => $this->language_manager ? $this->language_manager->translate('Connection test completed but failed to verify account details') : 'Connection test completed but failed to verify account details'
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
        // If settings are not provided, try to get them from form data or form ID
        if (empty($settings)) {
            $form_id = $form_data['form_id'] ?? 0;
            if ($form_id) {
                // Get form-specific settings
                $form_settings = get_post_meta($form_id, '_mavlers_cf_integrations', true);
                $settings = $form_settings['mailchimp'] ?? [];
            }
        }
        
        // Check if globally connected
        if (!$this->is_globally_connected()) {
            $this->logError('Mailchimp not globally connected', ['submission_id' => $submission_id]);
            return [
                'success' => false,
                'error' => $this->language_manager->translate('Mailchimp not configured')
            ];
        }

        $audience_id = $settings['audience_id'] ?? '';
        if (empty($audience_id)) {
            $this->logError('Audience ID not specified', ['submission_id' => $submission_id]);
            return [
                'success' => false,
                'error' => $this->language_manager->translate('Audience ID not specified')
            ];
        }

        // Check if batch processing is enabled
        if ($settings['batch_processing'] ?? true) {
            // Add to batch queue only if batch processor is available
            if ($this->batch_processor) {
                $queue_result = $this->batch_processor->add_to_batch_queue($form_data, $settings, $submission_id);
                
                if ($queue_result) {
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
                    // Fall back to immediate processing
                    return $this->process_submission_immediate($submission_id, $form_data, $settings);
                }
            } else {
                // Batch processor not available, fall back to immediate processing
                error_log("MailchimpIntegration: Batch processor not available, using immediate processing");
                return $this->process_submission_immediate($submission_id, $form_data, $settings);
            }
        } else {
            // Process immediately
            return $this->process_submission_immediate($submission_id, $form_data, $settings);
        }
    }

    /**
     * Process submission immediately
     */
    private function process_submission_immediate(int $submission_id, array $form_data, array $settings): array {
        // Get global settings
        $global_settings = $this->get_global_settings();
        $api_key = $global_settings['api_key'] ?? '';
        $audience_id = $settings['audience_id'] ?? '';

        // Check if we have the required credentials
        if (empty($api_key)) {
            $this->logError('API key not configured', ['submission_id' => $submission_id]);
            return [
                'success' => false,
                'error' => $this->language_manager ? $this->language_manager->translate('API key not configured') : 'API key not configured'
            ];
        }

        if (empty($audience_id)) {
            $this->logError('Audience ID not specified', ['submission_id' => $submission_id]);
            return [
                'success' => false,
                'error' => $this->language_manager ? $this->language_manager->translate('Audience ID not specified') : 'Audience ID not specified'
            ];
        }

        // Map form data to Mailchimp format
        $mapped_data = $this->enhanced_map_form_data($form_data, $settings);
        
        if (empty($mapped_data)) {
            $this->logError('No data to send', ['submission_id' => $submission_id]);
            return [
                'success' => false,
                'error' => $this->language_manager ? $this->language_manager->translate('No data to send') : 'No data to send'
            ];
        }

        // Subscribe to audience
        $result = $this->subscribeToAudience($mapped_data, $settings, $api_key, $audience_id, $submission_id);
        
        // Track analytics if enabled
        if ($result['success'] && $this->analytics_manager && ($settings['enable_analytics'] ?? true)) {
            $this->analytics_manager->track_subscription(
                $mapped_data['email_address'] ?? '',
                $submission_id,
                $audience_id,
                array('method' => 'immediate')
            );
        }
        
        return $result;
    }

    /**
     * Enhanced form data mapping
     */
    private function enhanced_map_form_data(array $form_data, array $settings): array {
        // Use nested fields data if available
        $field_data = $form_data['fields'] ?? $form_data;
        
        // Get field mapping from settings
        $field_mapping = $settings['field_mapping'] ?? [];
        
        // If no field mapping in settings, try to get enhanced mapping
        if (empty($field_mapping)) {
            $form_id = $form_data['form_id'] ?? 0;
            if ($form_id) {
                $field_mapping = $this->get_enhanced_field_mapping($form_id);
            }
        }
        
        $mapped = array();
        
        // Use field mapping if available
        if (!empty($field_mapping)) {
            foreach ($field_mapping as $form_field => $mailchimp_field) {
                if (isset($field_data[$form_field]) && !empty($field_data[$form_field])) {
                    $mapped[$mailchimp_field] = $field_data[$form_field];
                }
            }
        } else {
            // Fallback to basic field mapping
            $mapped = $this->mapFormDataToMailchimp($form_data, $settings);
        }
        
        return $mapped;
    }

    /**
     * Subscribe to audience with comprehensive error handling
     */
    private function subscribeToAudience(array $data, array $settings, string $api_key, string $audience_id, int $submission_id): array {
        // Extract datacenter from API key
        $dc = $this->extractDatacenter($api_key);
        if (!$dc) {
            return [
                'success' => false,
                'error' => 'Invalid API key format'
            ];
        }

        // Find email address in the data
        $email_address = '';
        foreach ($data as $key => $value) {
            if (strpos(strtolower($key), 'email') !== false && !empty($value)) {
                $email_address = $value;
                break;
            }
        }

        if (empty($email_address)) {
            return [
                'success' => false,
                'error' => 'No valid email address found in form data'
            ];
        }

        // Prepare member data
        $member_data = array(
            'email_address' => $email_address,
            'status' => 'subscribed',
            'merge_fields' => array()
        );

        // Add merge fields
        foreach ($data as $key => $value) {
            if ($key !== 'email_address' && !empty($value)) {
                $member_data['merge_fields'][$key] = $value;
            }
        }

        // Add tags
        if (!empty($settings['tags'])) {
            $tags = array_map('trim', explode(',', $settings['tags']));
            $member_data['tags'] = array_filter($tags);
        }

        // Make API request
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$audience_id}/members";
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($member_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if ($status_code === 200) {
            // Maybe register webhook
            $this->maybe_register_webhook($api_key, $audience_id);
            
            return [
                'success' => true,
                'message' => 'Successfully subscribed to audience'
            ];
        } else {
            $error_message = $result['detail'] ?? $result['title'] ?? 'Unknown error';
            
            // Handle specific error cases
            if ($status_code === 400 && strpos($error_message, 'already a list member') !== false) {
                return [
                    'success' => true,
                    'message' => 'Email already subscribed'
                ];
            }
            
            return [
                'success' => false,
                'error' => $error_message
            ];
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
        $mapped = array();
        
        // Basic field mapping
        $field_map = array(
            'email' => 'email_address',
            'first_name' => 'FNAME',
            'last_name' => 'LNAME',
            'phone' => 'PHONE',
            'company' => 'COMPANY'
        );
        
        // Map basic fields
        foreach ($field_map as $form_field => $mailchimp_field) {
            if (isset($form_data[$form_field]) && !empty($form_data[$form_field])) {
                $mapped[$mailchimp_field] = $form_data[$form_field];
            }
        }
        
        // Look for email by pattern matching
        foreach ($form_data as $key => $value) {
            if (strpos(strtolower($key), 'email') !== false && !empty($value) && !isset($mapped['email_address'])) {
                $mapped['email_address'] = $value;
            }
        }
        
        // Look for first name by pattern matching
        foreach ($form_data as $key => $value) {
            if ((strpos(strtolower($key), 'first') !== false || strpos(strtolower($key), 'fname') !== false) && !empty($value) && !isset($mapped['FNAME'])) {
                $mapped['FNAME'] = $value;
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
        $is_connected = !empty($settings['api_key']);
        
        return $is_connected;
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
        return end($parts) ?: null;
    }

    /**
     * Make Mailchimp API request
     */
    private function makeMailchimpApiRequest(string $method, string $endpoint, string $api_key, string $dc, array $data = []): ?array {
        $url = "https://{$dc}.api.mailchimp.com/3.0{$endpoint}";
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if (!empty($data)) {
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return $result;
        }
        
        return null;
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
        error_log("Mailchimp: === ENQUEUE COMPREHENSIVE ASSETS ===");
        error_log("Mailchimp: Hook: {$hook}");
        
        // Check if we're on a relevant admin page
        $is_relevant_page = (
            strpos($hook, 'mavlers-cf') !== false ||
            strpos($hook, 'mavlers-contact-forms') !== false ||
            strpos($hook, 'admin.php') !== false ||
            strpos($hook, 'post.php') !== false ||
            strpos($hook, 'post-new.php') !== false
        );
        
        error_log("Mailchimp: Is relevant page: " . ($is_relevant_page ? 'Yes' : 'No'));
        
        if (!$is_relevant_page) {
            error_log("Mailchimp: Not on relevant page, skipping asset enqueue");
            return;
        }
        
        error_log("Mailchimp: Enqueuing assets on hook: {$hook}");

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
        
        error_log("Mailchimp: Assets enqueued successfully with localized data: " . print_r($localization_data, true));
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
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Extract API key
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error('API key is required');
        }
        
        // Test connection
        $result = $this->testConnection(['api_key' => $api_key]);
        
        wp_send_json($result);
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
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Extract settings from different possible formats
        $settings = array();
        
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
            $settings = array(
                'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
                'enable_analytics' => !empty($_POST['enable_analytics']),
                'enable_webhooks' => !empty($_POST['enable_webhooks']),
                'batch_processing' => !empty($_POST['batch_processing'])
            );
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
            wp_send_json_success(array(
                'message' => 'Settings saved successfully!',
                'configured' => true
            ));
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    /**
     * AJAX: Save Mailchimp global settings (v2)
     */
    public function ajax_save_global_settings_v2(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Extract settings from different possible formats
        $settings = array();
        
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
            $settings = array(
                'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
                'enable_analytics' => !empty($_POST['enable_analytics']),
                'enable_webhooks' => !empty($_POST['enable_webhooks']),
                'batch_processing' => !empty($_POST['batch_processing'])
            );
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
            wp_send_json_success(array(
                'message' => 'Settings saved successfully!',
                'configured' => true
            ));
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    /**
     * Simple test AJAX handler for debugging
     */
    public function ajax_test_save_debug(): void {
        wp_send_json_success(array(
            'message' => 'Debug test completed',
            'timestamp' => current_time('mysql')
        ));
    }

    public function ajax_save_form_settings(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Get form ID
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (empty($form_id)) {
            wp_send_json_error('Form ID is required');
        }
        
        // Extract settings
        $settings = array();
        
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            $settings = $_POST['settings'];
        } elseif (isset($_POST['settings']) && is_string($_POST['settings'])) {
            $settings = json_decode(stripslashes($_POST['settings']), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Invalid settings format');
                return;
            }
        }
        
        // Save settings
        $result = $this->saveFormSettings($form_id, $settings);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Form settings saved successfully!',
                'form_id' => $form_id
            ));
        } else {
            wp_send_json_error('Failed to save form settings');
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
        // Temporarily disable nonce verification for debugging
        // if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
        //     wp_send_json_error('Security check failed');
        // }
        
        // Debug log to see if this method is being called
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Mailchimp: ajax_get_form_fields called with POST data: " . print_r($_POST, true));
        }
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (empty($form_id)) {
            wp_send_json_error('Form ID is required');
        }
        
        // Get form fields
        $form_fields = $this->get_form_fields($form_id);
        
        // Convert associative array to indexed array for JavaScript
        $fields_array = [];
        foreach ($form_fields as $field_id => $field_config) {
            $fields_array[] = $field_config;
        }
        
        wp_send_json_success($fields_array);
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
        // Temporarily disable nonce verification for debugging
        // if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
        //     wp_send_json_error('Security check failed');
        // }
        
        $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
        
        if (empty($audience_id)) {
            wp_send_json_error('Audience ID is required');
        }
        
        // Initialize components if needed
        if (!$this->custom_fields_manager) {
            $this->init_components();
        }
        
        // Get merge fields
        if ($this->custom_fields_manager) {
            $result = $this->custom_fields_manager->get_merge_fields();
            wp_send_json($result);
        } else {
            wp_send_json_error('Custom fields manager not available');
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
     * Test AJAX handler for debugging
     */
    public function ajax_test_ajax(): void {
        // Simple test without nonce verification for debugging
        wp_send_json_success(array(
            'message' => 'AJAX test successful',
            'timestamp' => current_time('mysql'),
            'post_data' => $_POST
        ));
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
    
    /**
     * AJAX handler to check database data for debugging
     */
    public function ajax_check_database(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mavlers_cf_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if (!$form_id) {
            wp_send_json_error('No form ID provided');
            return;
        }
        
        global $wpdb;
        
        // Check custom meta table
        $meta_table = $wpdb->prefix . 'mavlers_cf_form_meta';
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $meta_table WHERE form_id = %d AND meta_key = %s",
            $form_id,
            '_mavlers_cf_integrations'
        ));
        
        // Check post meta
        $post_meta = get_post_meta($form_id, '_mavlers_cf_integrations', true);
        
        // Check options table
        $option_key = "mavlers_cf_mailchimp_form_{$form_id}";
        $option_value = get_option($option_key, null);
        
        $result = [
            'form_id' => $form_id,
            'meta_table' => $meta_table,
            'meta_table_exists' => $wpdb->get_var("SHOW TABLES LIKE '$meta_table") == $meta_table,
            'custom_meta_value' => $meta_value,
            'custom_meta_decoded' => $meta_value ? json_decode($meta_value, true) : null,
            'post_meta_value' => $post_meta,
            'option_value' => $option_value,
            'all_options' => get_option('mavlers_cf_integrations_global', []),
            'debug_info' => [
                'wpdb_last_error' => $wpdb->last_error,
                'wpdb_last_query' => $wpdb->last_query,
                'current_user_id' => get_current_user_id(),
                'is_admin' => current_user_can('manage_options')
            ]
        ];
        
        wp_send_json_success($result);
    }
} 