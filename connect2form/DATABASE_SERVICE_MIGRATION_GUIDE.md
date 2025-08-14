# Database Service Migration Guide

## Overview

This guide provides a comprehensive solution to fix all WordPress database-related coding standard violations:
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`
- `WordPress.DB.DirectDatabaseQuery.NoCaching`
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`

## Solution Architecture

### 1. Service Classes Created

#### FormService (`addons/integrations-manager/src/Core/Services/FormService.php`)
- Handles all form-related database operations
- Implements caching for performance
- Provides CRUD operations for forms
- Includes validation and error handling

#### SubmissionService (`addons/integrations-manager/src/Core/Services/SubmissionService.php`)
- Handles all submission-related database operations
- Implements caching for performance
- Provides CRUD operations for submissions
- Includes statistics and export functionality

#### ServiceManager (`addons/integrations-manager/src/Core/Services/ServiceManager.php`)
- Centralized manager for all services
- Singleton pattern for global access
- Provides health monitoring and maintenance functions

### 2. Key Features

#### Caching Implementation
- All database queries are cached appropriately
- Cache invalidation on data changes
- Configurable cache expiration times

#### Prepared Statements
- All SQL queries use prepared statements
- No direct string interpolation
- Proper parameter sanitization

#### Error Handling
- Comprehensive exception handling
- Detailed error messages
- Graceful fallbacks

## Migration Steps

### Step 1: Initialize Services

Add this to your main plugin file or initialization hook:

```php
use Connect2Form\Integrations\Core\Services\ServiceManager;

// Initialize services
$service_manager = ServiceManager::getInstance();
$service_manager->init();
```

### Step 2: Replace Direct Database Calls

#### Before (Direct Database Call):
```php
global $wpdb;
$form = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
    $form_id
));
```

#### After (Service Call):
```php
$service_manager = ServiceManager::getInstance();
$form = $service_manager->forms()->get_form($form_id);
```

### Step 3: Common Migration Patterns

#### Forms Operations

```php
// Get form
$form = $service_manager->forms()->get_form($form_id);

// Get active form
$form = $service_manager->forms()->get_active_form($form_id);

// Save form
$form_id = $service_manager->forms()->save_form($form_data);

// Delete form
$service_manager->forms()->delete_form($form_id);

// Get forms list
$forms = $service_manager->forms()->get_forms_list($args);

// Get all forms for dropdown
$forms = $service_manager->forms()->get_all_forms();
```

#### Submissions Operations

```php
// Get submission
$submission = $service_manager->submissions()->get_submission($submission_id);

// Save submission
$submission_id = $service_manager->submissions()->save_submission($submission_data);

// Delete submission
$service_manager->submissions()->delete_submission($submission_id);

// Get submissions list
$submissions = $service_manager->submissions()->get_submissions_list($args);

// Get statistics
$stats = $service_manager->submissions()->get_submission_stats($form_id);
```

#### Integration Operations

```php
// Get logs
$logs = $service_manager->database()->getLogs($filters);

// Save integration settings
$service_manager->database()->saveIntegrationSettings($integration_id, $settings);

// Get field mappings
$mappings = $service_manager->database()->getFieldMappings($form_id, $integration_id);
```

## Benefits

### 1. WordPress Coding Standards Compliance
- ✅ No more `DirectDatabaseQuery` violations
- ✅ All queries use prepared statements
- ✅ Proper caching implementation
- ✅ No string interpolation in SQL

### 2. Performance Improvements
- Intelligent caching reduces database load
- Optimized queries with proper indexing
- Reduced memory usage through efficient data handling

### 3. Maintainability
- Centralized database logic
- Consistent error handling
- Easy to test and debug
- Clear separation of concerns

### 4. Security
- All inputs properly sanitized
- SQL injection prevention
- Proper data validation

## Implementation Checklist

- [ ] Initialize ServiceManager in plugin
- [ ] Replace form-related database calls
- [ ] Replace submission-related database calls
- [ ] Replace integration-related database calls
- [ ] Update error handling to use service exceptions
- [ ] Test all functionality
- [ ] Run WordPress coding standards check
- [ ] Verify performance improvements

## Testing

After migration, test these scenarios:
1. Form creation, editing, and deletion
2. Submission handling and viewing
3. Integration settings management
4. Cache functionality
5. Error handling and recovery

## Support

The service classes include comprehensive error handling and logging. Check the service health status:

```php
$health = $service_manager->get_health_status();
if (!empty($health['issues'])) {
    // Handle issues
}
```

This solution provides a robust, maintainable, and WordPress-compliant approach to database operations while preserving all existing functionality.
