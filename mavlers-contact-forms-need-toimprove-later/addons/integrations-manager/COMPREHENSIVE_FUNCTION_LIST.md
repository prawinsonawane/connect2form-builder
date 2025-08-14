# Comprehensive Function List - Integrations Manager Addon

## Overview
This document provides a complete inventory of all functions, classes, methods, AJAX calls, and JavaScript functions found in the Integrations Manager addon.

## PHP Classes and Methods

### 1. Core Classes

#### Plugin.php (245 lines)
**Class:** `MavlersCF\Integrations\Core\Plugin`

**Methods:**
- `getInstance()` - Singleton instance getter
- `__construct()` - Private constructor
- `init()` - Plugin initialization
- `register_hooks()` - WordPress hooks registration
- `load_integrations()` - Dynamic integration loading
- `discover_integrations()` - Integration discovery hook
- `process_form_submission($submission_id, $form_data)` - Form processing
- `get_enabled_integrations_for_form($form_id)` - Get form integrations
- `getRegistry()` - Get integration registry
- `getAssetManager()` - Get asset manager
- `activate()` - Plugin activation
- `deactivate()` - Plugin deactivation
- `create_tables()` - Database table creation
- `set_default_options()` - Default options setup

#### IntegrationRegistry.php (81 lines)
**Class:** `MavlersCF\Integrations\Core\Registry\IntegrationRegistry`

**Methods:**
- `register(IntegrationInterface $integration)` - Register integration
- `get(string $id)` - Get integration by ID
- `getAll()` - Get all integrations
- `getConfigured()` - Get configured integrations
- `has(string $id)` - Check if integration exists
- `count()` - Get integration count
- `toArray()` - Get JSON representation

#### AbstractIntegration.php (279 lines)
**Class:** `MavlersCF\Integrations\Core\Abstracts\AbstractIntegration`

**Methods:**
- `__construct()` - Constructor with API client and logger
- `init()` - Initialize integration
- `getId()` - Get integration ID
- `getName()` - Get integration name
- `getDescription()` - Get integration description
- `getVersion()` - Get integration version
- `getIcon()` - Get integration icon
- `getColor()` - Get integration color
- `isConfigured()` - Check if configured
- `isEnabled(array $settings)` - Check if enabled
- `getGlobalSettings()` - Get global settings
- `saveGlobalSettings(array $settings)` - Save global settings
- `getFormSettings(int $form_id)` - Get form settings
- `saveFormSettings(int $form_id, array $settings)` - Save form settings
- `log(string $level, string $message, array $context)` - Logging
- `logSuccess(string $message, array $context)` - Success logging
- `logError(string $message, array $context)` - Error logging
- `makeApiRequest(string $method, string $url, array $args)` - API requests
- `mapFormData(array $form_data, array $field_mapping)` - Data mapping
- `validateRequiredFields(array $data, array $required_fields)` - Field validation
- `getFieldMapping(string $action)` - Get field mapping
- `getDefaultSettings()` - Get default settings
- `validateSettings(array $settings)` - Validate settings
- `getSettingsFields()` - Get settings fields

### 2. Integration Classes

#### HubspotIntegration.php (2,377 lines)
**Class:** `MavlersCF\Integrations\Hubspot\HubspotIntegration`

**Methods:**
- `__construct()` - Constructor with component initialization
- `init()` - Initialize with hooks
- `init_components()` - Initialize component managers
- `register_ajax_handlers()` - Register AJAX handlers
- `getAuthFields()` - Get authentication fields
- `testConnection(array $credentials)` - Test API connection
- `getAvailableActions()` - Get available actions
- `getFormSettingsFields()` - Get form settings fields
- `getSettingsFields()` - Get settings fields
- `getFieldMapping(string $action)` - Get field mapping
- `validateSettings(array $settings)` - Validate settings
- `processSubmission(int $submission_id, array $form_data, array $settings)` - Process submission
- `process_submission_immediate()` - Immediate processing
- `enhanced_map_form_data()` - Enhanced data mapping
- `mapFormDataToHubspot()` - HubSpot data mapping
- `createOrUpdateContact()` - Contact creation/update
- `createDeal()` - Deal creation
- `enrollInWorkflow()` - Workflow enrollment
- `updateDeal()` - Deal update
- `processCustomObjects()` - Custom object processing
- `createCustomObject()` - Custom object creation
- `updateCustomObject()` - Custom object update
- `associateCompany()` - Company association
- `get_global_settings()` - Get global settings
- `is_globally_connected()` - Check global connection
- `get_enhanced_field_mapping()` - Get field mapping
- `save_enhanced_field_mapping()` - Save field mapping
- `enqueue_comprehensive_assets()` - Asset enqueuing

**AJAX Handlers:**
- `ajax_test_connection()` - Test connection
- `ajax_get_contacts()` - Get contacts
- `ajax_get_companies()` - Get companies
- `ajax_get_deals()` - Get deals
- `ajax_get_custom_properties()` - Get custom properties
- `ajax_get_workflows()` - Get workflows
- `ajax_test_response()` - Test response
- `ajax_save_global_settings()` - Save global settings
- `ajax_save_global_settings_simple()` - Simple settings save
- `ajax_save_global_settings_v2()` - V2 settings save
- `ajax_save_form_settings()` - Save form settings
- `ajax_save_field_mapping()` - Save field mapping
- `ajax_get_form_fields()` - Get form fields
- `ajax_get_field_mapping()` - Get field mapping
- `ajax_get_custom_objects()` - Get custom objects
- `ajax_get_custom_object_properties()` - Get custom object properties
- `ajax_save_custom_object_mapping()` - Save custom object mapping
- `ajax_get_custom_object_mapping()` - Get custom object mapping
- `ajax_get_deal_properties()` - Get deal properties
- `ajax_get_company_properties()` - Get company properties
- `ajax_get_contact_properties()` - Get contact properties
- `ajax_auto_map_fields()` - Auto map fields
- `ajax_get_analytics()` - Get analytics

#### MailchimpIntegration.php (1,415 lines)
**Class:** `MavlersCF\Integrations\Mailchimp\MailchimpIntegration`

**Methods:**
- `__construct()` - Constructor
- `init()` - Initialize integration
- `init_components()` - Initialize components
- `register_ajax_handlers()` - Register AJAX handlers
- `getAuthFields()` - Get authentication fields
- `testConnection(array $credentials)` - Test API connection
- `testBasicConnectivity(string $dc)` - Test basic connectivity
- `getAvailableActions()` - Get available actions
- `getSettingsFields()` - Get settings fields
- `getFormSettingsFields()` - Get form settings fields
- `validateSettings(array $settings)` - Validate settings
- `getFieldMapping(string $action)` - Get field mapping
- `processSubmission(int $submission_id, array $form_data, array $settings)` - Process submission
- `process_submission_immediate()` - Immediate processing
- `enhanced_map_form_data()` - Enhanced data mapping
- `subscribeToAudience()` - Audience subscription
- `maybe_register_webhook()` - Webhook registration
- `mapFormDataToMailchimp()` - Mailchimp data mapping
- `get_global_settings()` - Get global settings
- `is_globally_connected()` - Check global connection
- `get_enhanced_field_mapping()` - Get field mapping
- `save_enhanced_field_mapping()` - Save field mapping
- `extractDatacenter(string $api_key)` - Extract datacenter
- `makeMailchimpApiRequest()` - Mailchimp API requests
- `getAudiences(string $api_key)` - Get audiences
- `enqueue_comprehensive_assets()` - Asset enqueuing
- `init_webhook_endpoints()` - Webhook endpoints

**AJAX Handlers:**
- `ajax_test_connection()` - Test connection
- `ajax_get_audiences()` - Get audiences
- `ajax_save_global_settings()` - Save global settings
- `ajax_save_global_settings_v2()` - V2 settings save
- `ajax_save_form_settings()` - Save form settings
- `ajax_save_audience_selection()` - Save audience selection
- `ajax_get_form_fields()` - Get form fields
- `ajax_save_field_mapping()` - Save field mapping
- `ajax_get_field_mapping()` - Get field mapping
- `ajax_auto_map_fields()` - Auto map fields
- `ajax_get_audience_merge_fields()` - Get merge fields
- `ajax_get_multilingual_interface()` - Get multilingual interface
- `ajax_load_analytics_dashboard()` - Load analytics dashboard
- `ajax_export_analytics()` - Export analytics

### 3. Admin Classes

#### AdminManager.php (271 lines)
**Class:** `MavlersCF\Integrations\Admin\AdminManager`

**Methods:**
- `__construct(IntegrationRegistry $registry, AssetManager $asset_manager)` - Constructor
- `init_controllers()` - Initialize controllers
- `init_hooks()` - Initialize hooks
- `add_admin_menu()` - Add admin menu
- `ajax_test_integration()` - Test integration AJAX
- `ajax_save_integration_settings()` - Save settings AJAX
- `ajax_get_integration_data()` - Get data AJAX
- `save_global_settings(string $integration_id, array $settings)` - Save global settings
- `save_form_settings(string $integration_id, int $form_id, array $settings)` - Save form settings
- `sanitize_settings(array $settings)` - Sanitize settings

#### IntegrationsController.php (150 lines)
**Class:** `MavlersCF\Integrations\Admin\Controllers\IntegrationsController`

**Methods:**
- `__construct(IntegrationRegistry $registry, AssetManager $asset_manager)` - Constructor
- `render_page()` - Render main page
- `render_overview_tab(array $data)` - Render overview tab
- `render_settings_tab(array $data)` - Render settings tab
- `render_logs_tab(array $data)` - Render logs tab
- `get_current_tab()` - Get current tab
- `get_integrations_stats()` - Get statistics
- `get_global_settings(string $integration_id)` - Get global settings
- `get_logs(string $integration_id, int $limit)` - Get logs

#### SettingsController.php (120 lines)
**Class:** `MavlersCF\Integrations\Admin\Controllers\SettingsController`

**Methods:**
- `__construct(IntegrationRegistry $registry, AssetManager $asset_manager)` - Constructor
- `render_page()` - Render settings page
- `get_integrations()` - Get integrations
- `get_global_settings(string $integration_id)` - Get global settings

#### IntegrationsView.php (153 lines)
**Class:** `MavlersCF\Integrations\Admin\Views\IntegrationsView`

**Methods:**
- `__construct()` - Constructor
- `render(string $template, array $data)` - Render template
- `partial(string $partial, array $data)` - Render partial
- `render_error(string $message)` - Render error
- `e(string $text)` - Escape and output
- `esc(string $text)` - Escape and return
- `attr(string $attr)` - Output attribute
- `url(string $url)` - Output URL
- `is_configured($integration)` - Check if configured
- `integration_icon($integration)` - Get icon HTML
- `status_badge(string $status)` - Get status badge
- `format_date(string $date)` - Format date
- `integration_url(string $integration_id, string $tab)` - Get integration URL

### 4. Service Classes

#### ApiClient.php (208 lines)
**Class:** `MavlersCF\Integrations\Core\Services\ApiClient`

**Methods:**
- `__construct()` - Constructor
- `request(string $method, string $url, array $args)` - Make HTTP request
- `get(string $url, array $args)` - GET request
- `post(string $url, array $data, array $args)` - POST request
- `put(string $url, array $data, array $args)` - PUT request
- `delete(string $url, array $args)` - DELETE request
- `patch(string $url, array $data, array $args)` - PATCH request
- `handle_response($response, string $url, string $method)` - Handle response
- `get_error_message(int $status_code, $body)` - Get error message
- `setTimeout(int $timeout)` - Set timeout
- `setUserAgent(string $user_agent)` - Set user agent
- `createBasicAuthHeader(string $username, string $password)` - Basic auth header
- `createBearerTokenHeader(string $token)` - Bearer token header

#### Logger.php (225 lines)
**Class:** `MavlersCF\Integrations\Core\Services\Logger`

**Methods:**
- `__construct()` - Constructor
- `log(string $level, string $message, array $context)` - Log message
- `info(string $message, array $context)` - Info logging
- `error(string $message, array $context)` - Error logging
- `warning(string $message, array $context)` - Warning logging
- `success(string $message, array $context)` - Success logging
- `logIntegration(string $integration_id, int $form_id, string $status, string $message, array $data, ?int $submission_id)` - Integration logging
- `getLogs(string $integration_id, int $limit, int $offset)` - Get logs
- `getStats(string $integration_id, int $days)` - Get statistics
- `clearOldLogs()` - Clear old logs
- `deleteLogs(string $integration_id)` - Delete logs
- `insert_log_entry(string $level, string $message, array $context)` - Insert log entry

#### SecurityManager.php (136 lines) - NEW
**Class:** `MavlersCF\Integrations\Core\Services\SecurityManager`

**Methods:**
- `verifyNonce(string $nonce, string $action)` - Verify nonce
- `sanitizeSettings(array $settings)` - Sanitize settings
- `validateApiCredentials(array $credentials, string $integration_type)` - Validate credentials
- `escapeOutput($value)` - Escape output
- `validateFileUpload(array $file)` - Validate file upload
- `checkRateLimit(string $action, int $max_attempts, int $time_window)` - Check rate limit
- `logSecurityEvent(string $event, array $context)` - Log security event

#### ErrorHandler.php (297 lines) - NEW
**Class:** `MavlersCF\Integrations\Core\Services\ErrorHandler`

**Methods:**
- `__construct()` - Constructor
- `handleIntegrationError(\Throwable $error, string $integration_id, array $context)` - Handle integration error
- `handleApiError(array $response, string $integration_id, array $context)` - Handle API error
- `isRecoverableError(\Throwable $error)` - Check if recoverable
- `attemptRecovery(\Throwable $error, string $integration_id, array $context)` - Attempt recovery
- `handleRateLimitRecovery(string $integration_id, array $context)` - Rate limit recovery
- `handleTimeoutRecovery(string $integration_id, array $context)` - Timeout recovery
- `handleNetworkRecovery(string $integration_id, array $context)` - Network recovery
- `shouldRetryApiCall(int $status_code)` - Check if should retry
- `retryApiCall(string $integration_id, array $context)` - Retry API call
- `queueForRetry(string $integration_id, array $context)` - Queue for retry
- `getUserFriendlyMessage(\Throwable $error)` - Get user-friendly message
- `getApiErrorMessage(int $status_code, string $original_message)` - Get API error message
- `processRetryQueue()` - Process retry queue
- `executeRetry(string $option_name, array $retry_data)` - Execute retry

#### CacheManager.php (252 lines) - NEW
**Class:** `MavlersCF\Integrations\Core\Services\CacheManager`

**Methods:**
- `get(string $key, $default)` - Get cached data
- `set(string $key, $value, int $expiry)` - Set cached data
- `delete(string $key)` - Delete cached data
- `remember(string $key, callable $callback, int $expiry)` - Remember with callback
- `cacheApiResponse(string $integration_id, string $endpoint, array $params, $response, int $expiry)` - Cache API response
- `getCachedApiResponse(string $integration_id, string $endpoint, array $params)` - Get cached API response
- `generateApiCacheKey(string $integration_id, string $endpoint, array $params)` - Generate cache key
- `cacheIntegrationSettings(string $integration_id, array $settings)` - Cache settings
- `getCachedIntegrationSettings(string $integration_id)` - Get cached settings
- `cacheFormFields(int $form_id, array $fields)` - Cache form fields
- `getCachedFormFields(int $form_id)` - Get cached form fields
- `cacheFieldMappings(int $form_id, string $integration_id, array $mappings)` - Cache field mappings
- `getCachedFieldMappings(int $form_id, string $integration_id)` - Get cached mappings
- `clearIntegrationCaches(string $integration_id)` - Clear integration caches
- `clearApiCaches(string $integration_id)` - Clear API caches
- `clearFormCaches(int $form_id)` - Clear form caches
- `getCacheStats()` - Get cache statistics
- `warmUpCache()` - Warm up cache
- `getRecentForms()` - Get recent forms
- `getFormFieldsFromDatabase(int $form_id)` - Get form fields from DB
- `testCache()` - Test cache functionality

#### CodeQualityManager.php (292 lines) - NEW
**Class:** `MavlersCF\Integrations\Core\Services\CodeQualityManager`

**Methods:**
- `validateMethodNaming(string $method_name)` - Validate method naming
- `validateClassName(string $class_name)` - Validate class naming
- `validateVariableNaming(string $variable_name)` - Validate variable naming
- `detectCodeSmells(string $file_path)` - Detect code smells
- `validateFileStructure(string $file_path)` - Validate file structure
- `generateQualityReport(string $directory)` - Generate quality report
- `getPhpFiles(string $directory)` - Get PHP files
- `autoFixIssues(string $file_path)` - Auto-fix issues
- `validateIntegrationClass(string $class_name)` - Validate integration class
- `generateClassDocumentation(string $class_name)` - Generate documentation
- `checkDeprecatedUsage(string $file_path)` - Check deprecated usage

#### LanguageManager.php (102 lines)
**Class:** `MavlersCF\Integrations\Core\Services\LanguageManager`

**Methods:**
- `__construct()` - Constructor
- `translate(string $text, array $args)` - Translate text
- `getCurrentLanguage()` - Get current language
- `isRTL()` - Check if RTL
- `getTextDirection()` - Get text direction
- `formatDate(string $date, string $format)` - Format date
- `formatNumber(float $number, int $decimals)` - Format number
- `getCurrencySymbol(string $currency)` - Get currency symbol

#### AssetManager.php (Asset management class)
**Methods:**
- `init_default_assets()` - Initialize default assets
- `enqueue_style()` - Enqueue CSS
- `enqueue_script()` - Enqueue JavaScript
- `localize_script()` - Localize script

## JavaScript Functions

### hubspot-form.js (600 lines)

**Main Functions:**
- `initializeHubSpotSettings()` - Initialize HubSpot settings
- `initializeEventHandlers()` - Initialize event handlers
- `getFormIdFromUrl()` - Get form ID from URL
- `handleObjectTypeChange()` - Handle object type change
- `handleCustomObjectChange()` - Handle custom object change
- `loadCustomObjects()` - Load custom objects
- `updateCustomObjectOptions(customObjects)` - Update custom object options
- `loadObjectProperties(objectType)` - Load object properties
- `loadCustomObjectProperties(objectName)` - Load custom object properties
- `loadFormFields()` - Load form fields
- `loadHubSpotProperties()` - Load HubSpot properties
- `loadSavedSettings()` - Load saved settings
- `toggleFieldMappingSection()` - Toggle field mapping section
- `updateFieldMappingTable()` - Update field mapping table
- `handleFieldMappingChange()` - Handle field mapping change
- `updateMappingCount()` - Update mapping count
- `handleAutoMapFields()` - Handle auto map fields
- `handleClearMappings()` - Handle clear mappings
- `handleFormSettingsSave()` - Handle form settings save
- `showMessage(message, type)` - Show message

**AJAX Calls:**
- `hubspot_get_custom_objects`
- `hubspot_get_contact_properties`
- `hubspot_get_deal_properties`
- `hubspot_get_company_properties`
- `hubspot_get_custom_object_properties`
- `hubspot_get_form_fields`
- `hubspot_save_form_settings`

### mailchimp-form.js (858 lines)

**Main Functions:**
- `initializeMailchimpFormSettings()` - Initialize Mailchimp settings
- `shouldLoadAudiences()` - Check if should load audiences
- `loadInitialSettings()` - Load initial settings
- `loadSavedSettings()` - Load saved settings
- `bindEvents()` - Bind events
- `loadFormFields()` - Load form fields
- `loadAudiences()` - Load audiences
- `getApiKey()` - Get API key
- `populateAudienceSelect(audiences)` - Populate audience select
- `autoSaveAudienceSelection(audienceId)` - Auto save audience selection
- `loadMailchimpFields(audienceId)` - Load Mailchimp fields
- `loadMailchimpFieldsInternal(audienceId)` - Load fields internally
- `updateFieldMappingTable()` - Update field mapping table
- `populateMailchimpFieldSelects()` - Populate field selects
- `getFieldIcon(type)` - Get field icon
- `applyExistingMappings()` - Apply existing mappings
- `autoMapFields()` - Auto map fields
- `clearAllMappings()` - Clear all mappings
- `updateMappingStatus()` - Update mapping status
- `collectCurrentMappings()` - Collect current mappings
- `saveFormSettings()` - Save form settings
- `showMessage(message, type)` - Show message

**AJAX Calls:**
- `mailchimp_get_form_fields`
- `mailchimp_get_audiences`
- `save_mailchimp_audience_selection`
- `mailchimp_get_merge_fields`
- `mailchimp_save_form_settings`

### integrations-admin.js (433 lines)
**Functions:**
- `initializeIntegrationsAdmin()` - Initialize admin
- `bindAdminEvents()` - Bind admin events
- `handleTestConnection()` - Handle connection testing
- `showConnectionModal()` - Show connection modal
- `hideConnectionModal()` - Hide connection modal
- `updateConnectionStatus()` - Update connection status
- `handleSettingsSave()` - Handle settings save
- `showMessage()` - Show message
- `hideMessage()` - Hide message

### mailchimp.js (540 lines)
**Functions:**
- `initializeMailchimpAdmin()` - Initialize Mailchimp admin
- `loadAudiences()` - Load audiences
- `testConnection()` - Test connection
- `saveSettings()` - Save settings
- `showAnalytics()` - Show analytics
- `handleFieldMapping()` - Handle field mapping

## CSS Files

### hubspot.css (25 lines)
- Basic HubSpot integration styling
- Settings section styling
- Field mapping styling

### mailchimp.css (301 lines)
- Loading overlay styles
- Message styling (success, error, warning, info)
- Field mapping table styles
- Status badges
- Responsive design

### mailchimp-form.css (969 lines)
- Comprehensive form styling
- Field mapping interface
- Status indicators
- Loading states
- Responsive design

### integrations-admin.css (518 lines)
- Admin interface styling
- Grid layouts
- Status badges
- Modal styling
- Navigation tabs

## Template Files

### overview.php (157 lines)
- Main integrations overview page
- Integration cards
- Status indicators
- Connection test modal

### integration-settings.php (567 lines)
- Integration settings form
- Authentication fields
- Field mapping interface
- Settings validation

### settings-list.php (183 lines)
- Settings list page
- Integration selection
- Global settings

### logs.php (414 lines)
- Logs display page
- Log filtering
- Log statistics

### settings-main.php (201 lines)
- Main settings page
- Settings tabs
- Configuration options

## Summary

**Total Count:**
- **PHP Classes:** 15+ classes
- **PHP Methods:** 200+ methods
- **AJAX Handlers:** 50+ handlers
- **JavaScript Functions:** 60+ functions
- **CSS Files:** 4 files
- **Template Files:** 5 files

**Lines of Code:**
- **PHP:** ~8,000+ lines
- **JavaScript:** ~2,400+ lines
- **CSS:** ~1,800+ lines
- **Templates:** ~1,500+ lines

**Total:** ~13,700+ lines of code

This comprehensive addon provides robust integration capabilities for Mailchimp and HubSpot with extensive functionality for form processing, field mapping, analytics, and administration. 