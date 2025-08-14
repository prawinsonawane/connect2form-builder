# Connect2Form - Advanced WordPress Form Builder with CRM Integrations

## Overview

Connect2Form is a production-ready, comprehensive WordPress form builder plugin that enables users to create custom contact forms with advanced features and seamless integrations with popular CRM and email marketing platforms. The plugin includes a powerful form builder, submission management, and an extensive integrations manager addon.

**Version 2.0.0** - Production Release  
**WordPress Compatibility**: 5.0 - 6.8+  
**PHP Compatibility**: 7.4+

## Features

### Core Plugin Features

#### Form Builder
- **Drag & Drop Interface**: Intuitive visual form builder with drag-and-drop functionality
- **Multiple Field Types**: Text, email, number, date, select, radio, checkbox, textarea, file upload
- **Field Validation**: Built-in validation with customizable error messages
- **Conditional Logic**: Show/hide fields based on user selections
- **Form Templates**: Pre-built form templates for common use cases
- **Responsive Design**: Mobile-friendly forms that adapt to any screen size

#### Form Management
- **Form List**: Comprehensive list view with search, filter, and bulk actions
- **Form Status**: Enable/disable forms with status indicators
- **Form Duplication**: Clone existing forms for quick setup
- **Form Preview**: Preview forms before publishing
- **Shortcode Generation**: Automatic shortcode generation for easy embedding

#### Submission Handling
- **Submission Storage**: Secure database storage for all form submissions
- **Submission Management**: View, edit, and manage submissions from admin panel
- **Export Functionality**: Export submissions to CSV format
- **Email Notifications**: Configurable email notifications for new submissions
- **File Upload Support**: Handle file uploads with security validation

#### Security Features
- **reCAPTCHA Integration**: Google reCAPTCHA v2 and v3 support
- **Honeypot Protection**: Built-in honeypot fields to prevent spam
- **CSRF Protection**: Nonce verification for all form submissions
- **Input Sanitization**: Comprehensive input sanitization and validation
- **Rate Limiting**: Configurable rate limiting to prevent abuse

#### Email System
- **SMTP Support**: Integration with popular SMTP plugins
- **Email Templates**: Customizable email templates
- **Multiple Recipients**: Send notifications to multiple email addresses
- **Email Testing**: Built-in email testing functionality
- **Debug Information**: Detailed email debugging and troubleshooting

### Integrations Manager Addon

#### Supported Platforms
- **Mailchimp**: Email marketing and audience management
- **HubSpot**: CRM, marketing automation, and sales tools
- **Extensible Architecture**: Easy to add new integrations

#### Integration Features
- **Global Settings**: Centralized configuration for each platform
- **Form-Specific Settings**: Individual form integration settings
- **Field Mapping**: Visual field mapping between forms and platforms
- **Real-time Sync**: Immediate data synchronization
- **Batch Processing**: High-performance batch processing for large datasets
- **Webhook Support**: Real-time webhook synchronization
- **Analytics Tracking**: Comprehensive analytics and reporting

#### Mailchimp Integration
- **Audience Management**: Select and manage Mailchimp audiences
- **Custom Fields**: Map form fields to Mailchimp merge fields
- **Tags Support**: Automatic tagging of subscribers
- **Double Opt-in**: Configurable double opt-in settings
- **Analytics Dashboard**: Track subscription performance
- **Webhook Sync**: Real-time audience updates

#### HubSpot Integration
- **Contact Management**: Create and update HubSpot contacts
- **Deal Creation**: Automatically create deals from form submissions
- **Custom Objects**: Support for HubSpot custom objects
- **Workflow Enrollment**: Enroll contacts in HubSpot workflows
- **Company Association**: Associate contacts with companies
- **Form Submission**: Submit directly to HubSpot forms
- **Property Mapping**: Map form fields to HubSpot properties

## Technical Architecture

### Core Plugin Structure

```
Connect2Form/
├── connect2form.php                 # Main plugin file
├── includes/                        # Core classes
│   ├── class-connect2form-activator.php
│   ├── class-connect2form-admin.php
│   ├── class-connect2form-deactivator.php
│   ├── class-connect2form-form-builder.php
│   ├── class-connect2form-form-renderer.php
│   ├── class-connect2form-forms-list-table.php
│   ├── class-connect2form-integrations.php
│   ├── class-connect2form-settings.php
│   ├── class-connect2form-submission-handler.php
│   └── class-connect2form-submissions-list-table.php
├── assets/                          # Frontend assets
│   ├── css/
│   │   ├── admin.css
│   │   ├── form-builder.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       ├── form-builder.js
│       └── frontend.js
```

### Integrations Manager Structure

```
addons/integrations-manager/
├── integrations-manager.php         # Addon main file
├── src/
│   ├── Admin/                       # Admin interface
│   │   ├── AdminManager.php
│   │   ├── Controllers/
│   │   │   ├── IntegrationsController.php
│   │   │   └── SettingsController.php
│   │   └── Views/
│   │       └── IntegrationsView.php
│   ├── Core/                        # Core framework
│   │   ├── Abstracts/
│   │   │   └── AbstractIntegration.php
│   │   ├── Assets/
│   │   │   └── AssetManager.php
│   │   ├── Interfaces/
│   │   │   └── IntegrationInterface.php
│   │   ├── Plugin.php
│   │   ├── Registry/
│   │   │   └── IntegrationRegistry.php
│   │   └── Services/
│   │       ├── ApiClient.php
│   │       ├── CacheManager.php
│   │       ├── CodeQualityManager.php
│   │       ├── DatabaseManager.php
│   │       ├── ErrorHandler.php
│   │       ├── LanguageManager.php
│   │       ├── Logger.php
│   │       └── SecurityManager.php
│   └── Integrations/                # Platform integrations
│       ├── Hubspot/
│       │   ├── AnalyticsManager.php
│       │   ├── CompanyManager.php
│       │   ├── CustomPropertiesManager.php
│       │   ├── DealManager.php
│       │   ├── HubspotIntegration.php
│       │   ├── WorkflowManager.php
│       │   └── templates/
│       │       └── hubspot-form-settings.php
│       └── Mailchimp/
│           ├── AnalyticsManager.php
│           ├── BatchProcessor.php
│           ├── CustomFieldsManager.php
│           ├── MailchimpIntegration.php
│           ├── WebhookHandler.php
│           └── templates/
│               ├── mailchimp-form-settings.php
│               └── templates-structure.md
└── templates/                       # Admin templates
    └── admin/
        ├── integration-settings.php
        ├── logs.php
        ├── overview.php
        ├── settings-list.php
        └── settings-main.php
```

## Installation

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- cURL extension enabled

### Installation Steps

1. **Upload Plugin Files**
   - Upload the `Connect2Form` folder to `/wp-content/plugins/`
   - Or install via WordPress admin panel

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "Connect2Form"

3. **Install Integrations Manager** (Optional)
   - Upload the `addons/integrations-manager` folder to `/wp-content/plugins/`
   - Activate "Connect2Form Integrations Manager"

4. **Configure Settings**
   - Go to Connect2Form → Settings
   - Configure email settings, reCAPTCHA, and other options

## Usage

### Creating Forms

1. **Access Form Builder**
   - Go to Connect2Form → Forms → Add New

2. **Build Your Form**
   - Drag fields from the sidebar to the form area
   - Configure field settings in the right panel
   - Set form title and description

3. **Configure Settings**
   - Set form submission settings
   - Configure email notifications
   - Set up integrations (if available)

4. **Publish Form**
   - Save the form
   - Copy the generated shortcode
   - Paste shortcode on any page or post

### Managing Submissions

1. **View Submissions**
   - Go to Connect2Form → Submissions
   - Filter by form, date, or status
   - View submission details

2. **Export Data**
   - Select submissions to export
   - Choose CSV format
   - Download the file

3. **Email Notifications**
   - Configure recipient emails
   - Customize email templates
   - Test email functionality

### Setting Up Integrations

#### Mailchimp Setup

1. **Get API Key**
   - Log in to your Mailchimp account
   - Go to Account → Extras → API keys
   - Create a new API key

2. **Configure Integration**
   - Go to Connect2Form → Integrations → Mailchimp
   - Enter your API key
   - Test the connection

3. **Map Fields**
   - Select a form to configure
   - Map form fields to Mailchimp merge fields
   - Choose audience and settings

#### HubSpot Setup

1. **Create Private App**
   - Log in to your HubSpot account
   - Go to Settings → Account Setup → Integrations → Private Apps
   - Create a new private app with required scopes

2. **Configure Integration**
   - Go to Connect2Form → Integrations → HubSpot
   - Enter your access token and portal ID
   - Test the connection

3. **Set Up Objects**
   - Choose which objects to create (contacts, deals, companies)
   - Map form fields to HubSpot properties
   - Configure workflows and automation

## API Reference

### Hooks and Filters

#### Action Hooks

```php
// Form submission hooks
do_action('connect2form_before_submission', $form_id, $form_data);
do_action('connect2form_after_submission', $submission_id, $form_data);
do_action('connect2form_submission_error', $form_id, $error_message);

// Integration hooks
do_action('connect2form_integration_processed', $integration_id, $submission_id, $result);
do_action('connect2form_integration_failed', $integration_id, $submission_id, $error);
```

#### Filter Hooks

```php
// Form data filters
$form_data = apply_filters('connect2form_submission_data', $form_data, $form_id);
$email_content = apply_filters('connect2form_email_content', $content, $submission_id);

// Integration filters
$integration_settings = apply_filters('connect2form_integration_settings', $settings, $form_id);
```

### Database Schema

#### Forms Table
```sql
CREATE TABLE wp_connect2form_forms (
    id int(11) NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    description text,
    fields longtext NOT NULL,
    settings longtext,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

#### Submissions Table
```sql
CREATE TABLE wp_connect2form_submissions (
    id int(11) NOT NULL AUTO_INCREMENT,
    form_id int(11) NOT NULL,
    form_data longtext NOT NULL,
    ip_address varchar(45),
    user_agent text,
    utm_data longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY form_id (form_id),
    KEY created_at (created_at)
);
```

#### Form Meta Table
```sql
CREATE TABLE wp_connect2form_form_meta (
    id int(11) NOT NULL AUTO_INCREMENT,
    form_id int(11) NOT NULL,
    meta_key varchar(255) NOT NULL,
    meta_value longtext,
    PRIMARY KEY (id),
    KEY form_id (form_id),
    KEY meta_key (meta_key)
);
```

## Configuration

### Settings Options

#### Email Settings
- **From Email**: Default sender email address
- **From Name**: Default sender name
- **Reply To**: Default reply-to email address
- **SMTP Configuration**: SMTP server settings

#### Security Settings
- **reCAPTCHA Site Key**: Google reCAPTCHA site key
- **reCAPTCHA Secret Key**: Google reCAPTCHA secret key
- **Honeypot Fields**: Enable/disable honeypot protection
- **Rate Limiting**: Configure submission rate limits

#### Integration Settings
- **Global API Keys**: Centralized API key management
- **Default Settings**: Default integration settings
- **Webhook URLs**: Webhook endpoint configuration
- **Analytics**: Enable/disable analytics tracking

### Customization

#### Styling Forms
```css
/* Custom form styles */
.connect2form-form {
    /* Your custom styles */
}

.connect2form-field {
    /* Field-specific styles */
}

.connect2form-submit {
    /* Submit button styles */
}
```

#### Custom Field Types
```php
// Register custom field type
add_action('connect2form_register_field_types', function($field_types) {
    $field_types['custom_field'] = new CustomFieldType();
});
```

#### Custom Integrations
```php
// Create custom integration
class CustomIntegration extends AbstractIntegration {
    protected $id = 'custom_integration';
    protected $name = 'Custom Integration';
    
    public function processSubmission($submission_id, $form_data, $settings) {
        // Your integration logic
    }
}
```

## Troubleshooting

### Common Issues

#### Email Not Sending
1. Check SMTP settings
2. Verify email configuration
3. Test with built-in email tester
4. Check server mail logs

#### Integration Failures
1. Verify API credentials
2. Check API rate limits
3. Review error logs
4. Test connection manually

#### Form Display Issues
1. Check for JavaScript conflicts
2. Verify CSS compatibility
3. Test in different browsers
4. Check responsive design

### Debug Mode

Enable debug mode in WordPress:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at `/wp-content/debug.log`

### Support

For support and documentation:
- Check the plugin documentation
- Review error logs
- Test with default WordPress theme
- Disable other plugins temporarily

## Development

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

### Coding Standards

- Follow WordPress coding standards
- Use proper documentation
- Include unit tests
- Follow security best practices

### Building

```bash
# Install dependencies
composer install

# Run tests
phpunit

# Build assets
npm run build
```

## Changelog

### Version 2.0.0
- Added Integrations Manager addon
- Enhanced form builder interface
- Improved security features
- Added comprehensive analytics
- Enhanced email system

### Version 1.5.0
- Added reCAPTCHA integration
- Improved submission management
- Enhanced export functionality
- Added form templates

### Version 1.0.0
- Initial release
- Basic form builder
- Submission handling
- Email notifications

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Built with WordPress best practices
- Uses modern PHP 7.4+ features
- Integrates with popular CRM platforms
- Follows accessibility guidelines

---

For more information, visit the plugin documentation or contact support. 