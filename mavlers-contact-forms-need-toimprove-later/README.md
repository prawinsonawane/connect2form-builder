# Mavlers Contact Forms - HubSpot Integration

A comprehensive WordPress contact form plugin with advanced HubSpot CRM integration capabilities.

## 🚀 Features

### Core Form Builder
- **Drag & Drop Form Builder** - Intuitive visual form builder
- **Multiple Field Types** - Text, email, phone, textarea, select, checkbox, radio, file upload
- **Form Validation** - Client-side and server-side validation
- **File Upload Support** - Secure file upload handling
- **reCAPTCHA Integration** - Google reCAPTCHA v2 and v3 support
- **Anti-spam Protection** - Built-in spam protection mechanisms

### HubSpot Integration
- **Contact Management** - Create and update HubSpot contacts
- **Company Management** - Create and update HubSpot companies
- **Deal Management** - Create and update HubSpot deals
- **Custom Objects** - Support for HubSpot custom objects
- **Workflow Enrollment** - Automatically enroll contacts in HubSpot workflows
- **Field Mapping** - Visual field mapping between form fields and HubSpot properties
- **Real-time Testing** - Test connection and field mapping in real-time

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **HubSpot Account**: Private app with appropriate permissions
- **Web Server**: Apache/Nginx with mod_rewrite enabled

## 🛠️ Installation

1. **Upload Plugin Files**
   ```bash
   # Upload to wp-content/plugins/mavlers-contact-forms/
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "Mavlers Contact Forms"

3. **Configure HubSpot Integration**
   - Go to Forms → Integrations → HubSpot
   - Enter your HubSpot Access Token and Portal ID
   - Test the connection

## 🔧 HubSpot Setup

### 1. Create HubSpot Private App
1. Log into your HubSpot account
2. Go to Settings → Account Setup → Integrations → Private Apps
3. Click "Create private app"
4. Configure the following scopes:
   - **Contacts**: Read/Write
   - **Companies**: Read/Write
   - **Deals**: Read/Write
   - **Custom Objects**: Read/Write (if using custom objects)
   - **Workflows**: Read/Write (if using workflows)

### 2. Get Access Token
1. After creating the private app, copy the Access Token
2. Note your Portal ID (found in HubSpot URL: `https://app.hubspot.com/contacts/{PORTAL_ID}`)

### 3. Configure Plugin
1. Go to WordPress Admin → Forms → Integrations → HubSpot
2. Enter your Access Token and Portal ID
3. Click "Test Connection" to verify setup

## 📝 Usage

### Creating Forms
1. Go to Forms → Add New
2. Use the drag & drop builder to create your form
3. Add fields as needed
4. Save the form

### Configuring HubSpot Integration
1. Edit your form
2. Go to the "Integrations" tab
3. Enable HubSpot integration
4. Select object type (Contact, Company, Deal, or Custom Object)
5. Map form fields to HubSpot properties
6. Save settings

### Using Forms
1. Copy the shortcode: `[mavlers_contact_form id="FORM_ID"]`
2. Paste into any page or post
3. Forms will automatically submit to HubSpot based on your configuration

## 🔄 Field Mapping

### Automatic Mapping
The plugin automatically suggests field mappings based on common field names:
- `email` → `email`
- `first_name` → `firstname`
- `last_name` → `lastname`
- `phone` → `phone`
- `company` → `company`

### Manual Mapping
1. Select your form fields from the dropdown
2. Choose corresponding HubSpot properties
3. Save the mapping

## 🎯 Supported HubSpot Objects

### Contacts
- All standard contact properties
- Custom contact properties
- Contact lifecycle stages

### Companies
- All standard company properties
- Custom company properties
- Industry and company size

### Deals
- All standard deal properties
- Custom deal properties
- Deal stages and amounts

### Custom Objects
- Any custom object in your HubSpot account
- Custom object properties
- Object relationships

## 🔧 Configuration Options

### Global Settings
- **Access Token**: HubSpot private app access token
- **Portal ID**: Your HubSpot portal ID
- **Default Object Type**: Default object type for new forms

### Form-Specific Settings
- **Enable Integration**: Enable/disable for specific forms
- **Object Type**: Contact, Company, Deal, or Custom Object
- **Action Type**: Create, Update, or Create/Update
- **Field Mapping**: Map form fields to HubSpot properties
- **Workflow Enrollment**: Automatically enroll in workflows

## 🛡️ Security Features

- **Nonce Verification**: All AJAX requests include security nonces
- **Capability Checks**: Proper WordPress capability checks
- **Input Sanitization**: All user inputs are sanitized
- **Error Handling**: Comprehensive error handling and logging
- **Rate Limiting**: API rate limiting to prevent abuse

## 📊 Logging and Monitoring

### Integration Logs
- All integration activities are logged
- Success/failure tracking
- Error details for debugging
- Performance monitoring

### Debug Mode
Enable debug logging in WordPress:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🔧 Troubleshooting

### Common Issues

**"Access token is required"**
- Verify your HubSpot access token is correct
- Check that the private app has proper permissions

**"Form fields not loading"**
- Ensure the form has fields configured
- Check database permissions

**"Custom objects not appearing"**
- Verify custom objects exist in HubSpot
- Check private app has custom object permissions

**"Field mapping not saving"**
- Ensure you have proper WordPress permissions
- Check for JavaScript errors in browser console

### Debug Steps
1. Check WordPress debug log
2. Verify HubSpot API credentials
3. Test connection in integration settings
4. Check browser console for JavaScript errors

## 📈 Performance Optimization

### Production Recommendations
- Enable WordPress caching
- Use a CDN for static assets
- Optimize database queries
- Monitor API rate limits

### Caching
- Form configurations are cached
- HubSpot properties are cached
- API responses are cached for performance

## 🔄 Updates and Maintenance

### Regular Maintenance
- Monitor HubSpot API usage
- Review integration logs
- Update field mappings as needed
- Test connections periodically

### Version Compatibility
- Test with new WordPress versions
- Verify HubSpot API changes
- Update integration as needed

## 📞 Support

For support and questions:
- Check the troubleshooting section above
- Review WordPress debug logs
- Contact support with detailed error information

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🔄 Changelog

### Version 1.0.0
- Initial release
- HubSpot integration
- Form builder
- Field mapping
- Custom objects support
- Workflow enrollment

---

**Ready for Production** ✅

The HubSpot integration is now production-ready with:
- Clean, optimized code
- Comprehensive error handling
- Security best practices
- Performance optimizations
- Detailed documentation 