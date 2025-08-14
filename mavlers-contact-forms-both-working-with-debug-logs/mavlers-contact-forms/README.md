# Mavlers Contact Forms

A comprehensive contact form builder for WordPress with advanced features including email notifications, file uploads, reCAPTCHA integration, and third-party service integrations.

## Description

Mavlers Contact Forms is a powerful and user-friendly contact form plugin that allows you to create custom forms with ease. Built with security and performance in mind, it provides all the features you need for professional contact forms.

## Features

### Core Features
- **Drag & Drop Form Builder** - Intuitive visual form builder
- **Multiple Field Types** - Text, email, textarea, select, radio, checkbox, file upload, date, number
- **reCAPTCHA Integration** - Protect your forms from spam
- **File Upload Support** - Secure file uploads with validation
- **Email Notifications** - Multiple email notifications with merge tags
- **Form Submissions** - View and manage form submissions
- **Responsive Design** - Mobile-friendly forms

### Advanced Features
- **Rate Limiting** - Prevent spam and abuse
- **Honeypot Protection** - Additional spam protection
- **Third-party Integrations** - Mailchimp and HubSpot support
- **Custom Messages** - Customizable success/error messages
- **Redirect Options** - Redirect after submission or show thank you message
- **AJAX Submission** - Submit forms without page reload
- **Export Submissions** - Export form data to CSV

### Security Features
- **CSRF Protection** - Nonce verification on all forms
- **Input Validation** - Comprehensive input sanitization
- **File Upload Security** - MIME type and content validation
- **SQL Injection Protection** - Prepared statements for all database queries
- **XSS Protection** - Output escaping on all user data
- **Rate Limiting** - Prevent form abuse
- **Honeypot Protection** - Catch automated spam

## Developer Extensibility

This plugin is built with developers in mind and provides extensive hooks and filters for customization:

### Hooks Available

The plugin includes over 50+ hooks (actions and filters) covering:

- **Form Rendering**: Customize form HTML, attributes, and styling
- **Form Submission**: Intercept and modify submission data
- **Field Rendering**: Add custom field types and modify field output
- **Email Processing**: Customize email content, headers, and attachments
- **Validation**: Add custom validation rules and error messages
- **Integrations**: Extend with custom third-party integrations
- **Form Management**: Hook into form creation, updates, and deletion

### Quick Examples

```php
// Add custom field type
add_filter('mavlers_cf_custom_field_type', 'my_custom_field', 10, 3);

// Custom form validation
add_action('mavlers_cf_after_validation', 'my_validation', 10, 3);

// Modify email content
add_filter('mavlers_cf_email_data', 'my_email_modifier', 10, 2);

// Add custom integration
add_action('mavlers_cf_process_custom_integrations', 'my_integration', 10, 4);
```

### Documentation

For a complete reference of all available hooks, see [HOOKS_REFERENCE.md](HOOKS_REFERENCE.md).

## Installation

1. Upload the plugin files to the `/wp-content/plugins/mavlers-contact-forms` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Contact Forms menu to configure and create your forms

## Frequently Asked Questions

### Is this plugin secure?
Yes, Mavlers Contact Forms follows WordPress security best practices including:
- CSRF protection with nonces
- Input validation and sanitization
- SQL injection protection
- XSS protection
- File upload security
- Rate limiting
- Honeypot protection

### Does it support reCAPTCHA?
Yes, the plugin supports Google reCAPTCHA v2 and v3. You can configure reCAPTCHA settings in the plugin settings.

### Can I export form submissions?
Yes, you can export form submissions to CSV format from the submissions page.

### Does it work with page builders?
Yes, you can use the shortcode `[mavlers_contact_form id="FORM_ID"]` in any page builder or theme.

### Is it mobile-friendly?
Yes, all forms are responsive and work well on mobile devices.

## Screenshots

1. Form Builder Interface
2. Form Submissions Management
3. Email Notification Settings
4. reCAPTCHA Configuration

## Changelog

### 1.0.0
* Initial release
* Form builder with drag & drop interface
* Multiple field types support
* reCAPTCHA integration
* File upload functionality
* Email notifications
* Third-party integrations (Mailchimp, HubSpot)
* Security features (rate limiting, honeypot, CSRF protection)

## Upgrade Notice

### 1.0.0
Initial release of Mavlers Contact Forms.

## Support

For support, please visit our [support page](https://mavlers.com/support) or contact us at support@mavlers.com.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Mavlers](https://mavlers.com) 