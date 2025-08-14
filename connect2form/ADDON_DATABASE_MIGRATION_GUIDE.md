# Addon Database Migration Guide

## Overview

This guide addresses all database-related coding standard violations in the addon folder by replacing direct database calls with the new service classes.

## Issues Found in Addon Folder

### 1. Direct Database Calls in Integration Files

#### HubSpot Integration Files:
- `src/Integrations/Hubspot/CustomPropertiesManager.php` - Line 372
- `src/Integrations/Hubspot/HubspotIntegration.php` - Lines 2353, 2517, 2571

#### Mailchimp Integration Files:
- `src/Integrations/Mailchimp/MailchimpIntegration.php` - Lines 1391, 1400

#### Admin Controller Files:
- `src/Admin/Controllers/IntegrationsController.php` - Lines 229, 261

## Solution: Replace Direct Database Calls

### Step 1: Update Integration Files

#### HubSpot CustomPropertiesManager.php

**Before:**
```php
private function get_form_fields(int $form_id): array {
    global $wpdb;
    
    if (!$form_id) {
        return [];
    }
    
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT fields FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
        $form_id
    ));
    
    if (!$form || !$form->fields) {
        return [];
    }
    
    $fields_data = json_decode($form->fields, true);
    // ... rest of processing
}
```

**After:**
```php
private function get_form_fields(int $form_id): array {
    if (!$form_id) {
        return [];
    }
    
    // Use the service manager
    $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
    $fields = $service_manager->forms()->get_form_fields($form_id);
    
    if (empty($fields)) {
        return [];
    }
    
    $processed_fields = [];
    
    foreach ($fields as $field) {
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
```

#### HubSpot Integration.php

**Before:**
```php
private function get_form_fields(int $form_id): array {
    global $wpdb;
    
    if (!$form_id) {
        return [];
    }
    
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
        $form_id
    ));
    
    if (!$form) {
        return [];
    }
    
    if (!$form->fields) {
        return [];
    }
    
    $fields_data = json_decode($form->fields, true);
    // ... rest of processing
}
```

**After:**
```php
private function get_form_fields(int $form_id): array {
    if (!$form_id) {
        return [];
    }
    
    // Use the service manager
    $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
    $fields = $service_manager->forms()->get_form_fields($form_id);
    
    if (empty($fields)) {
        return [];
    }
    
    $processed_fields = [];
    
    foreach ($fields as $field) {
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
```

#### Mailchimp Integration.php

**Before:**
```php
private function get_form_fields(int $form_id): array {
    global $wpdb;
    
    if (!$form_id) {
        return [];
    }
    
    // Check if table exists
    $table_name = $wpdb->prefix . 'connect2form_forms';
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
    
    if (!$table_exists) {
        return [];
    }
    
    // Get form from database
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT fields FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
        $form_id
    ));
    
    if (!$form || !$form->fields) {
        return [];
    }
    
    $fields_data = json_decode($form->fields, true);
    // ... rest of processing
}
```

**After:**
```php
private function get_form_fields(int $form_id): array {
    if (!$form_id) {
        return [];
    }
    
    // Use the service manager
    $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
    $fields = $service_manager->forms()->get_form_fields($form_id);
    
    if (empty($fields)) {
        return [];
    }
    
    $processed_fields = [];
    
    foreach ($fields as $field) {
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
```

### Step 2: Update Admin Controller

#### IntegrationsController.php

**Before:**
```php
private function get_logs(string $integration_id = '', int $limit = 50): array {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'connect2form_integration_logs';
    
    // Check if table exists, create it if it doesn't
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
        $this->create_integration_logs_table();
    }
    
    // Build query branches to keep $wpdb->prepare() literal and satisfy sniff
    if (!empty($integration_id)) {
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE integration_id = %s ORDER BY created_at DESC LIMIT %d",
            $integration_id,
            $limit
        );
    } else {
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
            $limit
        );
    }
    
    return $wpdb->get_results($sql, ARRAY_A);
}
```

**After:**
```php
private function get_logs(string $integration_id = '', int $limit = 50): array {
    // Use the service manager
    $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
    
    $filters = [];
    if (!empty($integration_id)) {
        $filters['integration_id'] = $integration_id;
    }
    
    return $service_manager->database()->getLogs($filters, $limit, 0);
}
```

### Step 3: Update CacheManager.php

The CacheManager already has some direct database calls that should be updated:

**Before:**
```php
private function getRecentForms(): array {
    global $wpdb;
    
    $forms = $wpdb->get_col(
        "SELECT id FROM {$wpdb->prefix}connect2form_forms 
         ORDER BY updated_at DESC 
         LIMIT 10"
    );

    return array_map('intval', $forms);
}

private function getFormFieldsFromDatabase(int $form_id): array {
    global $wpdb;
    
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT fields FROM {$wpdb->prefix}connect2form_forms WHERE id = %d",
        $form_id
    ));

    if (!$form || !$form->fields) {
        return [];
    }

    $fields_data = json_decode($form->fields, true);
    return is_array($fields_data) ? $fields_data : [];
}
```

**After:**
```php
private function getRecentForms(): array {
    // Use the service manager
    $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
    $forms = $service_manager->forms()->get_forms_list(['per_page' => 10, 'orderby' => 'updated_at', 'order' => 'DESC']);
    
    return array_map(function($form) {
        return (int) $form['id'];
    }, $forms['items']);
}

private function getFormFieldsFromDatabase(int $form_id): array {
    // Use the service manager
    $service_manager = \Connect2Form\Integrations\Core\Services\ServiceManager::getInstance();
    return $service_manager->forms()->get_form_fields($form_id);
}
```

## Benefits of This Migration

### 1. WordPress Coding Standards Compliance
- ✅ Eliminates all `DirectDatabaseQuery` violations
- ✅ All queries use prepared statements through services
- ✅ Proper caching implementation
- ✅ No string interpolation in SQL

### 2. Performance Improvements
- ✅ Cached form fields reduce database load
- ✅ Optimized queries through service layer
- ✅ Better memory management

### 3. Maintainability
- ✅ Centralized database logic
- ✅ Consistent error handling
- ✅ Easy to test and debug

### 4. Security
- ✅ All inputs properly sanitized through services
- ✅ SQL injection prevention
- ✅ Proper data validation

## Implementation Checklist

- [ ] Update HubSpot CustomPropertiesManager.php
- [ ] Update HubSpot Integration.php
- [ ] Update Mailchimp Integration.php
- [ ] Update IntegrationsController.php
- [ ] Update CacheManager.php
- [ ] Test all integration functionality
- [ ] Run WordPress coding standards check
- [ ] Verify performance improvements

## Testing

After migration, test these scenarios:
1. HubSpot form field mapping
2. Mailchimp form field mapping
3. Integration logs display
4. Cache functionality
5. Form field retrieval in integrations

## Summary

This migration ensures that all addon folder code follows WordPress coding standards while maintaining full functionality. The service classes provide a robust, maintainable, and secure approach to database operations.
