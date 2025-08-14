<?php
/**
 * Mailchimp Language Manager
 * 
 * Comprehensive internationalization and localization support
 * for the Mailchimp integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Mailchimp_Language_Manager {
    
    private $current_locale;
    private $supported_locales;
    private $translations_cache = array();
    private $date_formats = array();
    private $number_formats = array();
    
    public function __construct() {
        $this->current_locale = get_locale();
        $this->init_supported_locales();
        $this->init_locale_formats();
        $this->init_hooks();
        $this->load_textdomain();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Language switching
        add_action('admin_init', array($this, 'handle_language_switch'));
        add_action('wp_ajax_mailchimp_switch_language', array($this, 'ajax_switch_language'));
        
        // Admin interface localization
        add_action('admin_enqueue_scripts', array($this, 'enqueue_localization_scripts'));
        add_filter('mailchimp_admin_texts', array($this, 'localize_admin_texts'));
        
        // Error message localization
        add_filter('mailchimp_error_message', array($this, 'localize_error_message'), 10, 2);
        add_filter('mailchimp_success_message', array($this, 'localize_success_message'), 10, 2);
        
        // Date and number formatting
        add_filter('mailchimp_format_date', array($this, 'format_date_localized'), 10, 2);
        add_filter('mailchimp_format_number', array($this, 'format_number_localized'), 10, 2);
        
        // RTL support
        add_action('admin_head', array($this, 'add_rtl_styles'));
    }
    
    /**
     * Initialize supported locales
     */
    private function init_supported_locales() {
        $this->supported_locales = array(
            // Major languages
            'en_US' => array(
                'name' => 'English (US)',
                'native' => 'English',
                'rtl' => false,
                'date_format' => 'M j, Y',
                'time_format' => 'g:i A',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            'en_GB' => array(
                'name' => 'English (UK)',
                'native' => 'English',
                'rtl' => false,
                'date_format' => 'j M Y',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            'es_ES' => array(
                'name' => 'Spanish (Spain)',
                'native' => 'Español',
                'rtl' => false,
                'date_format' => 'j \d\e F \d\e Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => '.'
            ),
            'es_MX' => array(
                'name' => 'Spanish (Mexico)',
                'native' => 'Español',
                'rtl' => false,
                'date_format' => 'j \d\e F \d\e Y',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            'fr_FR' => array(
                'name' => 'French (France)',
                'native' => 'Français',
                'rtl' => false,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            'de_DE' => array(
                'name' => 'German',
                'native' => 'Deutsch',
                'rtl' => false,
                'date_format' => 'j. F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => '.'
            ),
            'it_IT' => array(
                'name' => 'Italian',
                'native' => 'Italiano',
                'rtl' => false,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => '.'
            ),
            'pt_BR' => array(
                'name' => 'Portuguese (Brazil)',
                'native' => 'Português',
                'rtl' => false,
                'date_format' => 'j \d\e F \d\e Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => '.'
            ),
            'pt_PT' => array(
                'name' => 'Portuguese (Portugal)',
                'native' => 'Português',
                'rtl' => false,
                'date_format' => 'j \d\e F \d\e Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            'nl_NL' => array(
                'name' => 'Dutch',
                'native' => 'Nederlands',
                'rtl' => false,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => '.'
            ),
            'ru_RU' => array(
                'name' => 'Russian',
                'native' => 'Русский',
                'rtl' => false,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            'zh_CN' => array(
                'name' => 'Chinese (Simplified)',
                'native' => '简体中文',
                'rtl' => false,
                'date_format' => 'Y年n月j日',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            'ja' => array(
                'name' => 'Japanese',
                'native' => '日本語',
                'rtl' => false,
                'date_format' => 'Y年n月j日',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            'ko_KR' => array(
                'name' => 'Korean',
                'native' => '한국어',
                'rtl' => false,
                'date_format' => 'Y년 n월 j일',
                'time_format' => 'A g:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            // RTL languages
            'ar' => array(
                'name' => 'Arabic',
                'native' => 'العربية',
                'rtl' => true,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            'he_IL' => array(
                'name' => 'Hebrew',
                'native' => 'עברית',
                'rtl' => true,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            'fa_IR' => array(
                'name' => 'Persian',
                'native' => 'فارسی',
                'rtl' => true,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            // Nordic languages
            'sv_SE' => array(
                'name' => 'Swedish',
                'native' => 'Svenska',
                'rtl' => false,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            'no' => array(
                'name' => 'Norwegian',
                'native' => 'Norsk',
                'rtl' => false,
                'date_format' => 'j. F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            'da_DK' => array(
                'name' => 'Danish',
                'native' => 'Dansk',
                'rtl' => false,
                'date_format' => 'j. F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => '.'
            ),
            'fi' => array(
                'name' => 'Finnish',
                'native' => 'Suomi',
                'rtl' => false,
                'date_format' => 'j.n.Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            // Eastern European
            'pl_PL' => array(
                'name' => 'Polish',
                'native' => 'Polski',
                'rtl' => false,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            'cs_CZ' => array(
                'name' => 'Czech',
                'native' => 'Čeština',
                'rtl' => false,
                'date_format' => 'j. F Y',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            'hu_HU' => array(
                'name' => 'Hungarian',
                'native' => 'Magyar',
                'rtl' => false,
                'date_format' => 'Y. F j.',
                'time_format' => 'H:i',
                'decimal_sep' => ',',
                'thousands_sep' => ' '
            ),
            // Indian subcontinent
            'hi_IN' => array(
                'name' => 'Hindi',
                'native' => 'हिन्दी',
                'rtl' => false,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            ),
            'bn_BD' => array(
                'name' => 'Bengali',
                'native' => 'বাংলা',
                'rtl' => false,
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'decimal_sep' => '.',
                'thousands_sep' => ','
            )
        );
        
        // Allow filtering for custom locales
        $this->supported_locales = apply_filters('mailchimp_supported_locales', $this->supported_locales);
    }
    
    /**
     * Initialize locale-specific formats
     */
    private function init_locale_formats() {
        $locale_info = $this->get_locale_info($this->current_locale);
        
        $this->date_formats = array(
            'date' => $locale_info['date_format'] ?? 'M j, Y',
            'time' => $locale_info['time_format'] ?? 'g:i A',
            'datetime' => ($locale_info['date_format'] ?? 'M j, Y') . ' ' . ($locale_info['time_format'] ?? 'g:i A')
        );
        
        $this->number_formats = array(
            'decimal_separator' => $locale_info['decimal_sep'] ?? '.',
            'thousands_separator' => $locale_info['thousands_sep'] ?? ','
        );
    }
    
    /**
     * Load text domain for translations
     */
    private function load_textdomain() {
        $domain = 'mavlers-cf-mailchimp';
        $languages_path = plugin_dir_path(__FILE__) . '../languages/';
        
        // Load plugin translations
        load_plugin_textdomain($domain, false, $languages_path);
        
        // Load WordPress locale translations if available
        $wp_locale_file = $languages_path . $domain . '-' . $this->current_locale . '.mo';
        if (file_exists($wp_locale_file)) {
            load_textdomain($domain, $wp_locale_file);
        }
    }
    
    /**
     * Get locale information
     */
    public function get_locale_info($locale = null) {
        $locale = $locale ?? $this->current_locale;
        
        // Try exact match first
        if (isset($this->supported_locales[$locale])) {
            return $this->supported_locales[$locale];
        }
        
        // Try language match (e.g., 'es' for 'es_ES')
        $language = substr($locale, 0, 2);
        foreach ($this->supported_locales as $supported_locale => $info) {
            if (substr($supported_locale, 0, 2) === $language) {
                return $info;
            }
        }
        
        // Fallback to English
        return $this->supported_locales['en_US'];
    }
    
    /**
     * Check if current locale is RTL
     */
    public function is_rtl() {
        $locale_info = $this->get_locale_info();
        return $locale_info['rtl'] ?? false;
    }
    
    /**
     * Translate text with context support
     */
    public function translate($text, $context = '', $domain = 'mavlers-cf-mailchimp') {
        if (!empty($context)) {
            return _x($text, $context, $domain);
        }
        return __($text, $domain);
    }
    
    /**
     * Translate text with plural support
     */
    public function translate_plural($single, $plural, $number, $domain = 'mavlers-cf-mailchimp') {
        return _n($single, $plural, $number, $domain);
    }
    
    /**
     * Format date according to locale
     */
    public function format_date_localized($date, $format = 'datetime') {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        if (!$timestamp) {
            return $date; // Return original if can't parse
        }
        
        $format_string = $this->date_formats[$format] ?? $this->date_formats['datetime'];
        
        // Use WordPress date_i18n for proper localization
        return date_i18n($format_string, $timestamp);
    }
    
    /**
     * Format number according to locale
     */
    public function format_number_localized($number, $decimals = 0) {
        if (!is_numeric($number)) {
            return $number;
        }
        
        return number_format(
            $number,
            $decimals,
            $this->number_formats['decimal_separator'],
            $this->number_formats['thousands_separator']
        );
    }
    
    /**
     * Get localized error messages
     */
    public function get_error_messages() {
        return array(
            'connection_failed' => $this->translate('Connection to Mailchimp failed. Please check your API key.', 'error'),
            'invalid_api_key' => $this->translate('Invalid API key format. Please enter a valid Mailchimp API key.', 'error'),
            'no_audiences' => $this->translate('No audiences found in your Mailchimp account.', 'error'),
            'audience_not_found' => $this->translate('Selected audience was not found.', 'error'),
            'email_required' => $this->translate('Email address is required for subscription.', 'error'),
            'invalid_email' => $this->translate('Please enter a valid email address.', 'error'),
            'subscription_failed' => $this->translate('Failed to subscribe to Mailchimp. Please try again.', 'error'),
            'rate_limit_exceeded' => $this->translate('Too many requests. Please wait before trying again.', 'error'),
            'server_error' => $this->translate('Server error occurred. Please contact support.', 'error'),
            'network_error' => $this->translate('Network connection error. Please check your internet connection.', 'error'),
            'validation_failed' => $this->translate('Form validation failed. Please check your input.', 'error'),
            'already_subscribed' => $this->translate('This email address is already subscribed.', 'info'),
            'pending_confirmation' => $this->translate('Please check your email to confirm your subscription.', 'info')
        );
    }
    
    /**
     * Get localized success messages
     */
    public function get_success_messages() {
        return array(
            'connection_successful' => $this->translate('Successfully connected to Mailchimp!', 'success'),
            'settings_saved' => $this->translate('Settings saved successfully.', 'success'),
            'subscription_successful' => $this->translate('Thank you for subscribing!', 'success'),
            'subscription_pending' => $this->translate('Please check your email to confirm your subscription.', 'success'),
            'test_successful' => $this->translate('Test completed successfully.', 'success'),
            'form_configured' => $this->translate('Form integration configured successfully.', 'success'),
            'field_mapping_saved' => $this->translate('Field mapping saved successfully.', 'success'),
            'audience_selected' => $this->translate('Audience selected successfully.', 'success')
        );
    }
    
    /**
     * Get localized admin interface texts
     */
    public function get_admin_texts() {
        return array(
            // Navigation and headers
            'mailchimp_integration' => $this->translate('Mailchimp Integration', 'admin'),
            'global_settings' => $this->translate('Global Settings', 'admin'),
            'form_settings' => $this->translate('Form Settings', 'admin'),
            'field_mapping' => $this->translate('Field Mapping', 'admin'),
            'analytics' => $this->translate('Analytics', 'admin'),
            'custom_fields' => $this->translate('Custom Fields', 'admin'),
            
            // Form labels
            'api_key' => $this->translate('API Key', 'admin'),
            'audience' => $this->translate('Audience', 'admin'),
            'double_optin' => $this->translate('Double Opt-in', 'admin'),
            'update_existing' => $this->translate('Update Existing Subscribers', 'admin'),
            'tags' => $this->translate('Tags', 'admin'),
            'enable_integration' => $this->translate('Enable Integration', 'admin'),
            
            // Buttons
            'save_settings' => $this->translate('Save Settings', 'admin'),
            'test_connection' => $this->translate('Test Connection', 'admin'),
            'load_audiences' => $this->translate('Load Audiences', 'admin'),
            'auto_map_fields' => $this->translate('Auto Map Fields', 'admin'),
            'clear_mapping' => $this->translate('Clear Mapping', 'admin'),
            'export_data' => $this->translate('Export Data', 'admin'),
            
            // Status indicators
            'connected' => $this->translate('Connected', 'status'),
            'disconnected' => $this->translate('Disconnected', 'status'),
            'testing' => $this->translate('Testing...', 'status'),
            'loading' => $this->translate('Loading...', 'status'),
            'saving' => $this->translate('Saving...', 'status'),
            'enabled' => $this->translate('Enabled', 'status'),
            'disabled' => $this->translate('Disabled', 'status'),
            
            // Descriptions and help text
            'api_key_description' => $this->translate('Enter your Mailchimp API key. You can find this in your Mailchimp account under Account → Extras → API keys.', 'help'),
            'double_optin_description' => $this->translate('When enabled, subscribers will receive a confirmation email before being added to your audience.', 'help'),
            'update_existing_description' => $this->translate('When enabled, existing subscribers will be updated instead of showing an error.', 'help'),
            'tags_description' => $this->translate('Enter comma-separated tags to add to new subscribers.', 'help'),
            'field_mapping_description' => $this->translate('Map your form fields to Mailchimp merge fields. Drag and drop to create mappings.', 'help'),
            
            // Statistics and analytics
            'total_subscribers' => $this->translate('Total Subscribers', 'analytics'),
            'successful_submissions' => $this->translate('Successful Submissions', 'analytics'),
            'failed_submissions' => $this->translate('Failed Submissions', 'analytics'),
            'success_rate' => $this->translate('Success Rate', 'analytics'),
            'average_response_time' => $this->translate('Average Response Time', 'analytics'),
            'last_submission' => $this->translate('Last Submission', 'analytics'),
            
            // Time periods
            'today' => $this->translate('Today', 'time'),
            'yesterday' => $this->translate('Yesterday', 'time'),
            'last_7_days' => $this->translate('Last 7 Days', 'time'),
            'last_30_days' => $this->translate('Last 30 Days', 'time'),
            'this_month' => $this->translate('This Month', 'time'),
            'last_month' => $this->translate('Last Month', 'time'),
            
            // Common actions
            'edit' => $this->translate('Edit', 'action'),
            'delete' => $this->translate('Delete', 'action'),
            'view' => $this->translate('View', 'action'),
            'refresh' => $this->translate('Refresh', 'action'),
            'cancel' => $this->translate('Cancel', 'action'),
            'confirm' => $this->translate('Confirm', 'action'),
            'yes' => $this->translate('Yes', 'action'),
            'no' => $this->translate('No', 'action')
        );
    }
    
    /**
     * Localize error message
     */
    public function localize_error_message($message, $error_code = '') {
        $error_messages = $this->get_error_messages();
        return $error_messages[$error_code] ?? $message;
    }
    
    /**
     * Localize success message
     */
    public function localize_success_message($message, $success_code = '') {
        $success_messages = $this->get_success_messages();
        return $success_messages[$success_code] ?? $message;
    }
    
    /**
     * Localize admin texts
     */
    public function localize_admin_texts($texts) {
        return array_merge($texts, $this->get_admin_texts());
    }
    
    /**
     * Handle language switching
     */
    public function handle_language_switch() {
        if (isset($_GET['mailchimp_lang']) && current_user_can('manage_options')) {
            $new_locale = sanitize_text_field($_GET['mailchimp_lang']);
            
            if (isset($this->supported_locales[$new_locale])) {
                update_user_meta(get_current_user_id(), 'mailchimp_preferred_locale', $new_locale);
                $this->current_locale = $new_locale;
                $this->init_locale_formats();
            }
        }
    }
    
    /**
     * AJAX: Switch language
     */
    public function ajax_switch_language() {
        check_ajax_referer('mavlers_cf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $locale = sanitize_text_field($_POST['locale'] ?? '');
        
        if (isset($this->supported_locales[$locale])) {
            update_user_meta(get_current_user_id(), 'mailchimp_preferred_locale', $locale);
            wp_send_json_success(array(
                'message' => $this->translate('Language changed successfully.', 'success'),
                'locale' => $locale,
                'reload_required' => true
            ));
        } else {
            wp_send_json_error(array(
                'message' => $this->translate('Invalid language selection.', 'error')
            ));
        }
    }
    
    /**
     * Enqueue localization scripts
     */
    public function enqueue_localization_scripts($hook) {
        if (strpos($hook, 'mailchimp') === false) {
            return;
        }
        
        // Localize JavaScript strings
        wp_localize_script('mailchimp-admin', 'mailchimpL10n', array(
            'locale' => $this->current_locale,
            'isRTL' => $this->is_rtl(),
            'dateFormat' => $this->date_formats['date'],
            'timeFormat' => $this->date_formats['time'],
            'decimalSeparator' => $this->number_formats['decimal_separator'],
            'thousandsSeparator' => $this->number_formats['thousands_separator'],
            'texts' => $this->get_admin_texts(),
            'errors' => $this->get_error_messages(),
            'success' => $this->get_success_messages()
        ));
    }
    
    /**
     * Add RTL styles for supported languages
     */
    public function add_rtl_styles() {
        if (!$this->is_rtl()) {
            return;
        }
        
        echo '<style type="text/css">
        .mailchimp-admin-container {
            direction: rtl;
        }
        .mailchimp-admin-container .form-table th {
            text-align: right;
        }
        .mailchimp-admin-container .field-mapping-item {
            direction: rtl;
        }
        .mailchimp-admin-container .drag-handle {
            margin-left: 0;
            margin-right: 10px;
        }
        .mailchimp-admin-container .button-group {
            text-align: left;
        }
        .mailchimp-admin-container .status-indicator {
            float: left;
        }
        </style>';
    }
    
    /**
     * Get language selector HTML
     */
    public function get_language_selector() {
        if (!current_user_can('manage_options')) {
            return '';
        }
        
        $html = '<div class="mailchimp-language-selector">';
        $html .= '<label for="mailchimp-language">' . $this->translate('Language:', 'admin') . '</label>';
        $html .= '<select id="mailchimp-language" name="mailchimp_language">';
        
        foreach ($this->supported_locales as $locale => $info) {
            $selected = ($locale === $this->current_locale) ? ' selected="selected"' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s (%s)</option>',
                esc_attr($locale),
                $selected,
                esc_html($info['native']),
                esc_html($info['name'])
            );
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get supported locales
     */
    public function get_supported_locales() {
        return $this->supported_locales;
    }
    
    /**
     * Get current locale
     */
    public function get_current_locale() {
        return $this->current_locale;
    }
    
    /**
     * Format field validation messages
     */
    public function get_validation_messages() {
        return array(
            'email_invalid' => $this->translate('Please enter a valid email address.', 'validation'),
            'email_required' => $this->translate('Email address is required.', 'validation'),
            'name_required' => $this->translate('Name is required.', 'validation'),
            'phone_invalid' => $this->translate('Please enter a valid phone number.', 'validation'),
            'url_invalid' => $this->translate('Please enter a valid URL.', 'validation'),
            'date_invalid' => $this->translate('Please enter a valid date.', 'validation'),
            'number_invalid' => $this->translate('Please enter a valid number.', 'validation'),
            'field_too_long' => $this->translate('This field is too long.', 'validation'),
            'field_too_short' => $this->translate('This field is too short.', 'validation')
        );
    }
    
    /**
     * Format regional address patterns
     */
    public function get_address_formats() {
        return array(
            'US' => array(
                'format' => '{name}\n{company}\n{address1}\n{address2}\n{city}, {state} {zip}',
                'required' => array('address1', 'city', 'state', 'zip'),
                'labels' => array(
                    'state' => $this->translate('State', 'address'),
                    'zip' => $this->translate('ZIP Code', 'address')
                )
            ),
            'CA' => array(
                'format' => '{name}\n{company}\n{address1}\n{address2}\n{city}, {state} {zip}',
                'required' => array('address1', 'city', 'state', 'zip'),
                'labels' => array(
                    'state' => $this->translate('Province', 'address'),
                    'zip' => $this->translate('Postal Code', 'address')
                )
            ),
            'GB' => array(
                'format' => '{name}\n{company}\n{address1}\n{address2}\n{city}\n{state}\n{zip}',
                'required' => array('address1', 'city', 'zip'),
                'labels' => array(
                    'state' => $this->translate('County', 'address'),
                    'zip' => $this->translate('Postcode', 'address')
                )
            ),
            'DE' => array(
                'format' => '{name}\n{company}\n{address1}\n{address2}\n{zip} {city}',
                'required' => array('address1', 'city', 'zip'),
                'labels' => array(
                    'zip' => $this->translate('PLZ', 'address')
                )
            ),
            'FR' => array(
                'format' => '{name}\n{company}\n{address1}\n{address2}\n{zip} {city}',
                'required' => array('address1', 'city', 'zip'),
                'labels' => array(
                    'zip' => $this->translate('Code Postal', 'address')
                )
            ),
            'JP' => array(
                'format' => '〒{zip}\n{state}{city}\n{address1}\n{address2}\n{company}\n{name}',
                'required' => array('address1', 'city', 'state', 'zip'),
                'labels' => array(
                    'state' => $this->translate('Prefecture', 'address'),
                    'zip' => $this->translate('Postal Code', 'address')
                )
            )
        );
    }
} 