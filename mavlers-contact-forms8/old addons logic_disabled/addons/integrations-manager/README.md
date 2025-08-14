# Mavlers Contact Forms - Integrations Manager

A powerful addon system that transforms the basic contact form plugin into a comprehensive integration platform with support for popular email marketing services, CRMs, and automation tools.

## 🚀 **What's Included**

### **Complete Integration System**
- **Advanced Integration Framework** - Enterprise-grade backend architecture
- **Beautiful Admin Interface** - WordPress native UI with drag-and-drop field mapping
- **Security First** - AES-256-CBC encryption for API credentials
- **Developer Friendly** - Simple API for creating custom integrations
- **Auto-Discovery** - Automatic loading of integration addons

### **Pre-Built Integrations**
- **Enhanced Mailchimp** - Advanced audience management, tagging, and automation
- **HubSpot CRM** - Contact management, deal creation, and workflow automation
- **Zapier** - Universal webhook integration for 3000+ apps
- **ActiveCampaign** - Complete marketing automation with lists, tags, and pipelines
- **ConvertKit** - Professional email marketing with forms and sequences

---

## 📋 **Features Overview**

### **For WordPress Admins**
- ✅ **Visual Integration Setup** - Configure integrations directly in the form builder
- ✅ **One-Click Testing** - Test connections before going live
- ✅ **Intelligent Field Mapping** - Auto-suggestions based on field names and types
- ✅ **Real-Time Monitoring** - Activity logs and success rate statistics
- ✅ **Secure Credentials** - Encrypted storage of API keys and tokens
- ✅ **Background Processing** - Fast form submissions without delays

### **For Developers**
- ✅ **Simple Integration API** - Extend base class with 5 required methods
- ✅ **Auto-Discovery System** - Drop integration files and they're automatically loaded
- ✅ **Built-in OAuth Support** - OAuth 2.0 with automatic token refresh
- ✅ **Comprehensive Logging** - Multi-level logging with database and file storage
- ✅ **Rate Limiting & Caching** - Optimized for high-traffic websites
- ✅ **Hook System** - WordPress native actions and filters

### **Technical Architecture**
- ✅ **Modular Design** - Each integration is a separate, self-contained addon
- ✅ **Database Schema** - Optimized tables for integrations, mappings, and logs
- ✅ **Security Layer** - CSRF protection, input sanitization, and encryption
- ✅ **Performance Optimized** - Caching, background jobs, and efficient queries
- ✅ **WordPress Standards** - Follows WordPress coding standards and best practices

---

## 🛠 **Installation & Setup**

### **Automatic Installation**
The Integrations Manager is automatically loaded when you activate the main Mavlers Contact Forms plugin.

### **Manual Setup**
1. Ensure the main plugin is active
2. The integrations manager will initialize automatically
3. Access integrations through **WordPress Admin > Mavlers CF > Integrations**

---

## 📖 **How to Use**

### **Setting Up Integrations**

1. **Navigate to Form Builder**
   - Go to your form in the admin panel
   - Click on the "Integration" tab

2. **Choose Your Integration**
   - Browse available integrations
   - Click "Setup" on your desired service

3. **Configure Authentication**
   - Enter your API keys or credentials
   - Test the connection

4. **Map Form Fields**
   - Drag and drop to map form fields to integration fields
   - Use auto-mapping suggestions for quick setup

5. **Configure Actions**
   - Choose what happens when forms are submitted
   - Set up tags, lists, or automation triggers

6. **Save & Test**
   - Save your integration settings
   - Submit a test form to verify everything works

### **Available Integrations**

#### **Mailchimp Integration**
```php
Features:
- Subscribe to audiences
- Add tags and interests
- Update existing subscribers
- Double opt-in support
- Merge field mapping
```

#### **HubSpot Integration**
```php
Features:
- Create/update contacts
- Create deals and opportunities
- Assign contact owners
- Set lifecycle stages
- Trigger workflows
```

#### **Zapier Integration**
```php
Features:
- Universal webhook support
- Connect to 3000+ apps
- Custom data formatting
- Metadata inclusion
- Retry failed webhooks
```

#### **ActiveCampaign Integration**
```php
Features:
- Contact management
- List subscriptions
- Tag automation
- Deal creation
- Pipeline management
```

#### **ConvertKit Integration**
```php
Features:
- Form subscriptions
- Tag management
- Sequence automation
- Subscriber management
```

---

## 👨‍💻 **Developer Guide**

### **Creating Custom Integrations**

1. **Create Integration Directory**
```bash
addons/integrations-manager/integrations/your-service/
```

2. **Create Integration Class**
```php
// class-your-service-integration.php
class Mavlers_CF_Your_Service_Integration extends Mavlers_CF_Base_Integration {
    
    protected $integration_id = 'your_service';
    protected $integration_name = 'Your Service';
    
    public function get_auth_fields() {
        return array(
            'api_key' => array(
                'label' => 'API Key',
                'type' => 'password',
                'required' => true
            )
        );
    }
    
    public function get_action_fields() {
        return array(
            'action_type' => array(
                'label' => 'Action',
                'type' => 'select',
                'options' => array(
                    'create_contact' => 'Create Contact'
                )
            )
        );
    }
    
    public function get_integration_fields() {
        return array(
            'email' => array(
                'key' => 'email',
                'label' => 'Email Address',
                'type' => 'email',
                'required' => true
            )
        );
    }
    
    public function test_connection($credentials) {
        // Test API connection
        return array(
            'success' => true,
            'message' => 'Connection successful'
        );
    }
    
    public function process_submission($form_data, $settings, $field_mappings) {
        // Process form submission
        return array(
            'success' => true,
            'message' => 'Data sent successfully'
        );
    }
}
```

3. **File Structure**
```
your-service/
├── class-your-service-integration.php
└── README.md (optional)
```

### **Integration Auto-Discovery**
The system automatically discovers and loads integrations placed in the `integrations/` directory. No manual registration required!

### **Available Helper Methods**
```php
// API Client
$this->api_client->request($url, $method, $data, $headers);

// Logger
$this->logger->info('Message', $context);
$this->logger->error('Error', $context);

// Security
$encrypted = $this->security->encrypt($data);
$decrypted = $this->security->decrypt($encrypted_data);

// Field Mapping
$mapped_data = $this->field_mapper->map_fields($form_data, $mappings);
```

---

## 🔧 **Configuration Options**

### **Global Settings**
Access global integration settings at **WordPress Admin > Mavlers CF > Integrations**

- **Enable Background Processing** - Process submissions in background
- **Retry Failed Requests** - Automatically retry failed API calls
- **Log Level** - Set logging verbosity (Emergency to Debug)
- **Cache Duration** - Set API response cache duration
- **Rate Limiting** - Configure API rate limits

### **Form-Level Settings**
Each form can have multiple integrations with individual settings:

- **Integration Selection** - Enable/disable specific integrations
- **Field Mapping** - Map form fields to integration fields
- **Action Configuration** - Set up integration-specific actions
- **Conditional Logic** - Run integrations based on form data

---

## 📊 **Monitoring & Logs**

### **Statistics Dashboard**
- Total submissions processed
- Success/failure rates by integration
- Most active integrations
- Recent activity timeline

### **Activity Logs**
- Real-time submission processing logs
- Error tracking and debugging
- Performance metrics
- Filterable by integration, date, and log level

### **Log Levels**
- **Emergency** - System unusable
- **Alert** - Action must be taken immediately
- **Critical** - Critical conditions
- **Error** - Error conditions
- **Warning** - Warning conditions
- **Notice** - Normal but significant conditions
- **Info** - Informational messages
- **Debug** - Debug-level messages

---

## 🔒 **Security Features**

### **Data Protection**
- **AES-256-CBC Encryption** - All API credentials encrypted at rest
- **Secure Key Management** - Encryption keys stored separately
- **CSRF Protection** - All forms protected against CSRF attacks
- **Input Sanitization** - All inputs sanitized and validated

### **Access Control**
- **WordPress Capabilities** - Respects WordPress user roles
- **Nonce Verification** - All AJAX requests verified
- **Rate Limiting** - Prevents API abuse
- **Audit Logging** - All actions logged for security auditing

---

## 🚀 **Performance Optimization**

### **Background Processing**
- Form submissions don't wait for API calls
- Queued processing for reliability
- Automatic retry for failed requests

### **Caching System**
- API response caching
- Dynamic field options cached
- Database query optimization

### **Rate Limiting**
- Configurable rate limits per integration
- Prevents API quota exhaustion
- Intelligent backoff strategies

---

## 🐛 **Troubleshooting**

### **Common Issues**

#### **Integration Not Appearing**
1. Check if integration file exists in correct directory
2. Verify class name follows naming convention
3. Check error logs for PHP errors

#### **API Connection Failed**
1. Verify API credentials are correct
2. Check if API endpoint is accessible
3. Review API rate limits
4. Check integration logs for specific errors

#### **Field Mapping Issues**
1. Ensure form fields exist
2. Check integration field requirements
3. Verify data types match

### **Debug Mode**
Enable debug logging to get detailed information:
1. Go to **Mavlers CF > Integrations > Settings**
2. Set log level to "Debug"
3. Check logs after submission

### **Log Files**
Logs are stored in:
- Database: `wp_mavlers_cf_integration_logs` table
- Files: `wp-content/uploads/mavlers-cf-logs/`

---

## 📞 **Support & Contributing**

### **Getting Help**
- Check the troubleshooting section above
- Review integration logs for specific errors
- Contact plugin support for assistance

### **Contributing**
We welcome contributions! To add new integrations:
1. Follow the developer guide above
2. Test thoroughly with various scenarios
3. Include proper error handling
4. Document any special requirements

### **Creating Integration Packages**
You can create standalone integration packages that work with this system. Simply follow the integration API and include the auto-loader compatibility.

---

## 📜 **Changelog**

### **Version 1.0.0**
- Initial release
- Core integration framework
- Pre-built integrations (Mailchimp, HubSpot, Zapier, ActiveCampaign, ConvertKit)
- Visual field mapping interface
- Security and encryption layer
- Comprehensive logging system
- Background processing
- Auto-discovery system

---

## 📄 **License**

This addon is part of the Mavlers Contact Forms plugin and follows the same licensing terms.

---

*Transform your contact forms into a powerful integration platform with unlimited possibilities!* 