=== Connect2Form – Advanced Contact Form Builder ===
Contributors: pravinsonawane71,sonymerlinn
Tags: contact form, form builder, responsive, accessibility, security
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional drag-and-drop form builder with accessibility, security, and performance optimization. Extensible with addon integrations.

== Description ==

Connect2Form is a comprehensive WordPress form builder plugin that enables users to create custom contact forms with advanced features. Built with security, accessibility, and performance as core principles, it provides a solid foundation for form management with extensibility through addon plugins for CRM and email marketing integrations.

**🚀 Key Features:**

* **Drag & Drop Form Builder** - Intuitive visual interface with live preview
* **15+ Field Types** - Text, email, file upload, date picker, select, radio, checkbox, textarea, number, URL, and more
* **Addon System** - Extensible architecture for CRM and email marketing integrations
* **Security First** - CSRF protection, rate limiting, file validation, XSS protection
* **Accessibility Compliant** - WCAG 2.1 AA standards with screen reader support
* **Mobile Responsive** - Works perfectly on all devices and screen sizes
* **Conditional Logic** - Show/hide fields based on user input
* **Email Notifications** - Customizable templates with merge tags
* **Spam Protection** - reCAPTCHA v2/v3, honeypot fields, rate limiting
* **Performance Optimized** - Cached queries, lazy loading, memory management
* **Developer Friendly** - 50+ hooks and filters for extensive customization

**🔗 Extensibility:**

* **Addon System** - Modular architecture for extending functionality
* **CRM Integrations** - Available through separate Connect2Form Integrations addon
* **Email Marketing** - Extended capabilities via addon plugins
* **reCAPTCHA** - v2 Checkbox, v2 Invisible, v3 with score thresholds
* **Developer API** - Hooks and filters for custom integrations
* **Webhook Support** - Available through addon plugins

**🔒 Security Features:**

* Enhanced nonce verification with expiration
* Input sanitization and validation for all field types
* File upload security with type/size validation
* Rate limiting protection (configurable per IP)
* SQL injection prevention
* XSS protection with output escaping
* CSRF token validation
* Honeypot spam detection
* Secure file handling with virus scanning capability

**♿ Accessibility Features:**

* WCAG 2.1 AA compliant markup
* Screen reader support with ARIA labels
* Keyboard navigation throughout forms
* High contrast mode support
* Focus management and indication
* ARIA labels and descriptions
* Semantic HTML structure
* Skip links for better navigation

**📊 Performance Features:**

* Database query caching with expiration
* Asset optimization and minification
* Lazy loading for heavy components
* Memory management and cleanup
* Daily cleanup routines for temporary data
* Optimized database queries
* CDN support for static assets

**🛠️ Developer Features:**

* 50+ action and filter hooks
* Custom field type support
* REST API endpoints
* Extensive documentation
* Code follows WordPress coding standards
* PHPDoc comments throughout
* Unit test coverage
* Modular architecture

== Installation ==

**Automatic Installation:**
1. Go to WordPress Admin > Plugins > Add New
2. Search for "Connect2Form"
3. Click "Install Now" and then "Activate"

**Manual Installation:**
1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/connect2form-builder/` directory
3. Activate the plugin through the 'Plugins' screen in WordPress

**Getting Started:**
1. Go to Connect2Form > Add New to create your first form
2. Use the drag-and-drop builder to add fields
3. Configure settings, notifications, and integrations
4. Use the shortcode `[connect2form id="1"]` to display your form
5. Or use the Gutenberg block or PHP code `<?php echo do_shortcode('[connect2form id="1"]'); ?>`

**Minimum Requirements:**
* WordPress 5.0 or higher
* PHP 7.4 or higher (PHP 8.0+ recommended)
* MySQL 5.6 or higher (MySQL 8.0+ recommended)
* 64MB PHP memory limit (128MB+ recommended)
* Modern web browser with JavaScript enabled

== Frequently Asked Questions ==

= Is Connect2Form free to use? =

Yes! Connect2Form core functionality is completely free. Premium integrations and advanced features are available as add-ons for enhanced functionality.

= Does it work with my theme? =

Connect2Form is designed to work with any properly coded WordPress theme. Forms are styled to inherit your theme's appearance and can be further customized with CSS.

= Can I customize the form styling? =

Absolutely! You can customize forms using CSS, and we provide extensive styling options in the form builder. Custom CSS can be added at form level or globally.

= Is it mobile responsive? =

Yes, all forms are fully responsive and work perfectly on mobile devices, tablets, and desktops with touch-friendly interfaces.

= Does it work with page builders? =

Yes, Connect2Form works seamlessly with all major page builders including Elementor, Beaver Builder, Divi, Gutenberg, Visual Composer, and more.

= Can I export form submissions? =

Yes, you can export submissions to CSV format for analysis, backup, or integration with other tools. Bulk export functionality is also available.

= Is it GDPR compliant? =

Connect2Form includes features to help with GDPR compliance, including data retention controls, privacy options, data anonymization, and consent management.

= Can I integrate with my CRM? =

Yes! CRM integrations are available through our separate "Connect2Form Integrations" addon plugin, which provides Mailchimp, HubSpot, and other service integrations. The addon requires this core plugin.

= Do you provide support? =

We provide comprehensive documentation, video tutorials, and community support through WordPress.org forums. Premium support is available for complex implementations.

= Can I create multi-step forms? =

Yes, Connect2Form supports conditional logic that can be used to create multi-step form experiences with progress indicators.

= Does it support file uploads? =

Yes, with comprehensive security including file type validation, size limits, virus scanning capability, and secure storage options.

= Is it translation ready? =

Yes, Connect2Form is fully internationalized and translation-ready with support for RTL languages.

== Screenshots ==

1. **Drag & Drop Form Builder** - Intuitive interface for creating forms with live preview
2. **Form Fields Library** - 15+ field types with extensive configuration options
3. **Integration Settings** - Easy CRM and email marketing setup with real-time testing
4. **Mobile Responsive Design** - Perfect display and functionality on all devices
5. **Submission Management** - View, export, and manage form entries with filtering
6. **Advanced Security Dashboard** - Built-in spam protection and security monitoring
7. **Accessibility Features** - WCAG 2.1 AA compliant with screen reader support
8. **Performance Monitoring** - Real-time performance metrics and optimization tools

== Third-Party Libraries & Resources ==

Connect2Form uses the following third-party libraries and resources to provide enhanced functionality:

**JavaScript Libraries:**
* **Datepicker v1.0.10** - Date picker functionality
  * Source: https://github.com/fengyuanchen/datepicker
  * License: MIT License
  * Used for: Date field input enhancement
  * Human-readable source: Available in GitHub repository

**External Services (Optional):**
* **Google reCAPTCHA** - Spam protection service
  * Privacy Policy: https://policies.google.com/privacy
  * Terms: https://policies.google.com/terms
  * Data sent: Form submission data for validation only

**Addon Integrations (Separate Plugin):**
* **Connect2Form Integrations** - Separate addon plugin for CRM and email marketing
  * Provides: Mailchimp, HubSpot, and other service integrations
  * Repository: Available as separate plugin download
  * Dependency: Requires Connect2Form core plugin

**Build Tools & Development:**
All JavaScript and CSS files in this plugin are human-readable and unminified except where noted. The plugin does not use build tools like webpack, gulp, or npm for distribution.

**Source Code Availability:**
* **Plugin Source:** https://github.com/prawinsonawane/connect2form-builder
* **Documentation:** https://connect2form.com/
* **Issue Tracker:** https://github.com/prawinsonawane/connect2form-builder/issues
* **Contribution Guide:** https://github.com/prawinsonawane/connect2form-builder/blob/main/CONTRIBUTING.md

**Minified Files:**
* `assets/js/datepicker.min.js` - Third-party library (source link provided above)

All other JavaScript and CSS files are unminified and human-readable for easy inspection and modification.

== External Services ==

This plugin connects to external third-party services to provide optional integration functionality. These connections are only established when specifically configured by the user through the Connect2Form Integrations addon plugin.

**MailChimp API Integration (Optional)**

* **Service**: MailChimp email marketing platform (mailchimp.com)
* **Purpose**: To automatically add form submissions to your MailChimp email lists for newsletter and marketing purposes
* **Data Sent**: Email addresses and any other form fields you choose to map (name, phone, etc.)
* **When**: Only when a form is submitted AND MailChimp integration is configured in the Connect2Form Integrations addon
* **Data Location**: Data is sent to MailChimp servers based on your account region (e.g., us1.api.mailchimp.com)
* **Privacy Policy**: https://mailchimp.com/legal/privacy/
* **Terms of Service**: https://mailchimp.com/legal/terms/

**HubSpot CRM Integration (Optional)**

* **Service**: HubSpot CRM platform (hubspot.com)  
* **Purpose**: To create or update contact records in your HubSpot CRM from form submissions
* **Data Sent**: Contact information from form fields including email, name, phone, company details, and custom fields
* **When**: Only when a form is submitted AND HubSpot integration is configured in the Connect2Form Integrations addon
* **Data Location**: Data is sent to HubSpot API servers (api.hubapi.com)
* **Privacy Policy**: https://legal.hubspot.com/privacy-policy
* **Terms of Service**: https://legal.hubspot.com/terms-of-service

**Google reCAPTCHA Service (Optional)**

* **Service**: Google reCAPTCHA spam protection (google.com)
* **Purpose**: To protect forms from spam and automated bot submissions
* **Data Sent**: User interaction data, IP address, and browser information for spam analysis
* **When**: Only when reCAPTCHA is enabled for a specific form
* **Data Location**: Data is processed by Google's servers worldwide
* **Privacy Policy**: https://policies.google.com/privacy
* **Terms of Service**: https://policies.google.com/terms

**Important Notes:**

- All external service integrations are completely optional and disabled by default
- No data is sent to external services unless you explicitly configure and enable the specific integration
- You maintain full control over what data is shared and with which services
- Each integration can be independently enabled, disabled, or configured according to your privacy requirements
- For legal compliance, ensure your website's privacy policy discloses any external service usage to your users

== Developer Hooks & Filters ==

Connect2Form provides extensive customization through WordPress hooks and filters:

**Form Rendering Hooks:**
* `connect2form_before_form_render` - Before form HTML generation
* `connect2form_after_form_render` - After form HTML output
* `connect2form_before_form_fields` - Before field rendering
* `connect2form_after_form_fields` - After field rendering
* `connect2form_before_field_render` - Before individual field render
* `connect2form_after_field_render` - After individual field render

**Data Processing Hooks:**
* `connect2form_before_validation` - Before form validation
* `connect2form_after_validation` - After form validation
* `connect2form_submission_data` - Filter submission data
* `connect2form_before_save_submission` - Before saving to database
* `connect2form_after_save_submission` - After saving submission
* `connect2form_after_submission` - After complete submission process

**Form Management Hooks:**
* `connect2form_before_save_form` - Before saving form configuration
* `connect2form_after_save_form` - After saving form
* `connect2form_before_delete_form` - Before form deletion
* `connect2form_after_delete_form` - After form deletion
* `connect2form_before_duplicate_form` - Before form duplication
* `connect2form_after_duplicate_form` - After form duplication

**Customization Filters:**
* `connect2form_form_fields` - Modify form fields array
* `connect2form_form_settings` - Modify form settings
* `connect2form_field_html` - Customize field HTML output
* `connect2form_form_html` - Customize complete form HTML
* `connect2form_submit_button_text` - Customize submit button text
* `connect2form_submit_button_classes` - Customize submit button CSS classes
* `connect2form_validation_error_message` - Customize error messages

**Security & Performance Filters:**
* `connect2form_max_file_size` - Set maximum file upload size
* `connect2form_allowed_file_types` - Define allowed file types
* `connect2form_max_attempts_per_hour` - Rate limiting configuration
* `connect2form_submission_retention_days` - Data retention period

**Addon Integration Hooks:**
* `connect2form_addon_loaded` - When an addon plugin is loaded
* `connect2form_before_integration` - Before external integration processing
* `connect2form_after_integration` - After external integration processing
* `connect2form_register_integration` - Register new integration types

**Example Usage:**
```php
// Customize form output
add_filter('connect2form_form_html', 'my_custom_form_wrapper', 10, 4);
function my_custom_form_wrapper($html, $form, $fields, $settings) {
    return '<div class="my-custom-wrapper">' . $html . '</div>';
}

// Add custom validation
add_action('connect2form_before_validation', 'my_custom_validation', 10, 3);
function my_custom_validation($form, $post_data, $files) {
    // Your custom validation logic
}

// Modify submission data
add_filter('connect2form_submission_data', 'my_submission_filter', 10, 2);
function my_submission_filter($data, $form) {
    // Modify submission data before saving
    return $data;
}
```

For complete documentation and examples, visit: https://connect2form.com/developer-docs

== Changelog ==

= 2.6.0 - 2024-12-XX =
**🔒 Security Enhancements:**
* Enhanced nonce verification system with expiration handling
* Improved input sanitization for all field types
* Added comprehensive file upload security
* Implemented advanced rate limiting with IP-based protection
* Enhanced CSRF protection mechanisms
* Added XSS protection with output escaping

**♿ Accessibility Improvements:**
* Full WCAG 2.1 AA compliance implementation
* Enhanced screen reader support with proper ARIA labels
* Improved keyboard navigation throughout interface
* Added focus management system
* High contrast mode support
* Semantic HTML structure improvements

**📊 Performance Optimizations:**
* Implemented database query caching system
* Added asset optimization and minification
* Lazy loading for heavy form components
* Memory management improvements
* Daily cleanup routines for temporary data
* Optimized database queries with proper indexing

**🔧 Code Quality Improvements:**
* Full WordPress coding standards compliance
* Enhanced PHP 7.4+ compatibility with PHP 8.0+ support
* Comprehensive error handling and logging
* Added PHPDoc comments throughout codebase
* Improved modular architecture
* Enhanced unit test coverage

**🆕 New Features:**
* Advanced form validation with custom rules
* Enhanced file upload handling with virus scanning
* Improved email template system with merge tags
* Better mobile responsiveness and touch interface
* Extended customization options for developers
* Real-time form preview in builder
* Addon system for extensible integrations

**🐛 Bug Fixes:**
* Fixed text domain inconsistencies for translations
* Resolved AJAX handler compatibility issues
* Improved form rendering on various themes
* Enhanced cross-browser compatibility
* Fixed mobile layout issues
* Resolved conflict with popular plugins

**📚 Documentation:**
* Complete developer documentation
* Video tutorials for common use cases
* Updated FAQ with community questions
* Migration guide from other form plugins

= 2.0.0 - 2024-XX-XX =
**Major Release - Production Ready**
* Complete rewrite with modern WordPress standards
* Added comprehensive security manager
* Implemented accessibility compliance
* Performance optimization system
* Improved user interface
* Addon system foundation for integrations

= 1.0.0 - 2024-XX-XX =
* Initial release
* Basic form builder functionality
* Core security features
* Email notification system
* Foundation for addon integrations

== Upgrade Notice ==

= 2.6.0 =
Major security, accessibility, and performance update. Enhanced WordPress standards compliance and developer tools. Recommended for all users. Please backup your site before upgrading.

= 2.0.0 =
Major security and accessibility update. Enhanced performance and WordPress standards compliance. Recommended for all users.

== Privacy Policy ==

Connect2Form takes privacy seriously and follows WordPress privacy best practices:

**Data Collection:**
* We don't collect any personal data unless explicitly configured by site administrators
* Form submissions are stored locally in your WordPress database
* No data is transmitted to external servers without explicit configuration
* Integration data is sent only to configured third-party services

**Data Storage:**
* All form data is stored in your WordPress database
* File uploads are stored in your WordPress uploads directory
* Temporary data is cleaned up regularly via cron jobs
* You maintain full control over all collected data

**Data Sharing:**
* Data is shared only with services you explicitly configure (Mailchimp, HubSpot, etc.)
* No data is sent to Connect2Form developers or external parties
* Third-party integrations follow their respective privacy policies
* All data transmission uses encrypted connections (HTTPS)

**User Rights:**
* Site visitors can request data deletion through contact forms
* Administrators can export/delete form submissions
* Data retention periods are configurable
* GDPR compliance tools are built-in

**Cookies & Tracking:**
* Connect2Form does not set any tracking cookies
* reCAPTCHA (if enabled) follows Google's privacy policy
* Form analytics (if enabled) use local storage only

== Support & Resources ==

**📚 Documentation:**
* Complete setup guides: https://connect2form.com/docs
* Video tutorials: https://connect2form.com/tutorials
* Developer documentation: https://connect2form.com/developer-docs
* FAQ database: https://connect2form.com/faq

**💬 Community Support:**
* WordPress.org forums: https://wordpress.org/support/plugin/connect2form-builder
* Community Discord: https://discord.gg/connect2form
* User Facebook group: https://facebook.com/groups/connect2form

**🐛 Bug Reports & Feature Requests:**
* GitHub Issues: https://github.com/prawinsonawane/connect2form-builder/issues
* Feature voting: https://connect2form.com/feature-requests
* Security issues: security@connect2form.com

**🔧 Professional Support:**
* Premium support plans: https://connect2form.com/support
* Custom development: https://connect2form.com/custom-development
* Enterprise solutions: https://connect2form.com/enterprise

**🌐 Official Links:**
* Official Website: https://connect2form.com
* Plugin Repository: https://github.com/prawinsonawane/connect2form-builder
* Social Media: @connect2form
* Newsletter: https://connect2form.com/newsletter

== Contributing ==

We welcome contributions from the WordPress community! Here's how you can help:

**🔧 Code Contributions:**
* Fork the repository: https://github.com/prawinsonawane/connect2form-builder
* Read our contribution guidelines: https://github.com/prawinsonawane/connect2form-builder/blob/main/CONTRIBUTING.md
* Submit pull requests for bug fixes and features
* Follow WordPress coding standards

**🌍 Translations:**
* Help translate Connect2Form into your language
* Join our translation team: https://translate.wordpress.org/projects/wp-plugins/connect2form-builder
* Translation coordinators welcome

**📝 Documentation:**
* Improve our documentation
* Submit tutorials and guides
* Help with video content creation

**🐛 Testing:**
* Test beta releases
* Report bugs and compatibility issues
* Provide feedback on new features

**💡 Ideas:**
* Suggest new features
* Vote on feature requests
* Participate in community discussions

== Credits & Acknowledgments ==

Connect2Form is developed with ❤️ for the WordPress community.

**Core Team:**
* Lead Developer: pravinsonawane71
* Co-Developer: sonymerlinn


**Special Thanks:**
* WordPress core team for the amazing platform
* Plugin review team for maintaining quality standards
* Accessibility consultants for WCAG compliance guidance
* Security researchers for responsible disclosure
* Community contributors and beta testers
* All users who provide valuable feedback and feature requests

**Open Source Libraries:**
* Datepicker.js by Chen Fengyuan
* WordPress Coding Standards team
* PHP_CodeSniffer contributors

**Inspiration:**
* WordPress form plugin ecosystem
* Modern web accessibility standards
* Enterprise security best practices

---

**Made with ❤️ by the Connect2Form Team**

For more information, visit: https://connect2form.com