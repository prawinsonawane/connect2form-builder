# Integration Templates Structure

This document outlines the proper structure for integration templates to maintain clean separation and scalability.

## Directory Structure

```
addons/integrations-manager/
├── src/
│   └── Integrations/
│       └── {IntegrationName}/
│           └── templates/
│               ├── {integration-name}-form-settings.php
│               ├── {integration-name}-admin-settings.php (future)
│               └── {integration-name}-widget.php (future)
├── assets/
│   ├── css/
│   │   └── admin/
│   │       ├── {integration-name}-form.css
│   │       └── {integration-name}-admin.css (future)
│   └── js/
│       └── admin/
│           ├── {integration-name}-form.js
│           └── {integration-name}-admin.js (future)
└── templates/
    └── integration-section.php (main integration loader)
```

## Naming Conventions

### Templates
- **Form Settings**: `{integration-name}-form-settings.php`
- **Admin Settings**: `{integration-name}-admin-settings.php` 
- **Widgets/Components**: `{integration-name}-widget.php`

### Assets
- **Form CSS**: `{integration-name}-form.css`
- **Admin CSS**: `{integration-name}-admin.css`
- **Form JS**: `{integration-name}-form.js`
- **Admin JS**: `{integration-name}-admin.js`

## Example: Mailchimp Integration

```
src/Integrations/Mailchimp/templates/
├── mailchimp-form-settings.php      ✅ Form-specific settings
├── mailchimp-admin-settings.php     🔄 Future: Admin-specific settings
└── mailchimp-widget.php             🔄 Future: Widget components

assets/css/admin/
├── mailchimp-form.css                ✅ Form integration styles
└── mailchimp-admin.css               🔄 Future: Admin page styles

assets/js/admin/
├── mailchimp-form.js                 ✅ Form integration scripts
└── mailchimp-admin.js                🔄 Future: Admin page scripts
```

## Rules

1. **No CSS in PHP files** - All styles in separate `.css` files
2. **No JavaScript in PHP files** - All scripts in separate `.js` files  
3. **Integration-specific templates** - In `src/Integrations/{Name}/templates/`
4. **Integration-specific assets** - Named with integration prefix
5. **Auto-loading** - Main `integration-section.php` automatically loads assets

## Benefits

- ✅ **Clean separation** of concerns
- ✅ **Easy maintenance** and debugging
- ✅ **Scalable** for multiple integrations
- ✅ **No conflicts** between integrations
- ✅ **Consistent** naming and structure 