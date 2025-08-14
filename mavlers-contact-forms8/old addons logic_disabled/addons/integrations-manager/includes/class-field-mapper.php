<?php
/**
 * Field Mapper Class
 * 
 * Handles mapping between form fields and integration fields
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Field_Mapper {
    
    public function __construct() {
        // Constructor
    }
    
    /**
     * Get all fields from a specific form
     * @param int $form_id
     * @return array
     */
    public function get_form_fields($form_id) {
        global $wpdb;
        
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));
        
        if (!$form || empty($form->fields)) {
            return array();
        }
        
        $fields = json_decode($form->fields, true);
        if (!is_array($fields)) {
            return array();
        }
        
        $mapped_fields = array();
        foreach ($fields as $field) {
            $mapped_fields[] = array(
                'id' => $field['id'] ?? '',
                'label' => $field['label'] ?? '',
                'type' => $field['type'] ?? 'text',
                'required' => isset($field['required']) ? (bool)$field['required'] : false,
                'options' => $field['options'] ?? array(),
                'description' => $field['description'] ?? ''
            );
        }
        
        return $mapped_fields;
    }
    
    /**
     * Get form fields organized by type
     * @param int $form_id
     * @return array
     */
    public function get_form_fields_by_type($form_id) {
        $fields = $this->get_form_fields($form_id);
        $organized = array();
        
        foreach ($fields as $field) {
            $type = $field['type'];
            if (!isset($organized[$type])) {
                $organized[$type] = array();
            }
            $organized[$type][] = $field;
        }
        
        return $organized;
    }
    
    /**
     * Get available field types with their display names
     * @return array
     */
    public function get_field_types() {
        return array(
            'text' => array(
                'label' => __('Text', 'mavlers-contact-forms'),
                'icon' => 'dashicons-editor-textcolor',
                'compatible_with' => array('text', 'name', 'title', 'company', 'job_title', 'notes')
            ),
            'email' => array(
                'label' => __('Email', 'mavlers-contact-forms'),
                'icon' => 'dashicons-email',
                'compatible_with' => array('email', 'email_address')
            ),
            'number' => array(
                'label' => __('Number', 'mavlers-contact-forms'),
                'icon' => 'dashicons-calculator',
                'compatible_with' => array('number', 'phone', 'age', 'quantity', 'amount')
            ),
            'textarea' => array(
                'label' => __('Textarea', 'mavlers-contact-forms'),
                'icon' => 'dashicons-editor-paragraph',
                'compatible_with' => array('text', 'description', 'notes', 'comments', 'message')
            ),
            'select' => array(
                'label' => __('Select', 'mavlers-contact-forms'),
                'icon' => 'dashicons-arrow-down-alt2',
                'compatible_with' => array('select', 'dropdown', 'list', 'category')
            ),
            'radio' => array(
                'label' => __('Radio', 'mavlers-contact-forms'),
                'icon' => 'dashicons-marker',
                'compatible_with' => array('select', 'option', 'choice')
            ),
            'checkbox' => array(
                'label' => __('Checkbox', 'mavlers-contact-forms'),
                'icon' => 'dashicons-yes',
                'compatible_with' => array('boolean', 'yes_no', 'true_false', 'consent')
            ),
            'date' => array(
                'label' => __('Date', 'mavlers-contact-forms'),
                'icon' => 'dashicons-calendar-alt',
                'compatible_with' => array('date', 'datetime', 'birth_date', 'created_date')
            ),
            'file' => array(
                'label' => __('File', 'mavlers-contact-forms'),
                'icon' => 'dashicons-upload',
                'compatible_with' => array('file', 'attachment', 'upload')
            )
        );
    }
    
    /**
     * Suggest field mappings based on field names and types
     * @param array $form_fields
     * @param array $integration_fields
     * @return array
     */
    public function suggest_field_mappings($form_fields, $integration_fields) {
        $suggestions = array();
        $field_types = $this->get_field_types();
        
        foreach ($integration_fields as $integration_field) {
            $best_match = $this->find_best_field_match(
                $integration_field,
                $form_fields,
                $field_types
            );
            
            if ($best_match) {
                $suggestions[$integration_field['id']] = $best_match['id'];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Find the best matching form field for an integration field
     * @param array $integration_field
     * @param array $form_fields
     * @param array $field_types
     * @return array|null
     */
    private function find_best_field_match($integration_field, $form_fields, $field_types) {
        $best_match = null;
        $highest_score = 0;
        
        foreach ($form_fields as $form_field) {
            $score = $this->calculate_field_match_score(
                $integration_field,
                $form_field,
                $field_types
            );
            
            if ($score > $highest_score) {
                $highest_score = $score;
                $best_match = $form_field;
            }
        }
        
        return $highest_score >= 50 ? $best_match : null; // Minimum 50% match
    }
    
    /**
     * Calculate how well two fields match
     * @param array $integration_field
     * @param array $form_field
     * @param array $field_types
     * @return int
     */
    private function calculate_field_match_score($integration_field, $form_field, $field_types) {
        $score = 0;
        
        // Type compatibility (40 points max)
        if ($this->are_types_compatible($integration_field, $form_field, $field_types)) {
            $score += 40;
        }
        
        // Name similarity (40 points max)
        $name_score = $this->calculate_name_similarity(
            $integration_field['label'] ?? $integration_field['id'],
            $form_field['label']
        );
        $score += intval($name_score * 40 / 100);
        
        // Required field match (10 points max)
        if (isset($integration_field['required']) && isset($form_field['required'])) {
            if ($integration_field['required'] === $form_field['required']) {
                $score += 10;
            }
        }
        
        // Exact ID match bonus (20 points)
        if (strtolower($integration_field['id']) === strtolower($form_field['id'])) {
            $score += 20;
        }
        
        // Common field patterns bonus
        $score += $this->get_pattern_bonus($integration_field, $form_field);
        
        return min($score, 100); // Cap at 100
    }
    
    /**
     * Check if two field types are compatible
     * @param array $integration_field
     * @param array $form_field
     * @param array $field_types
     * @return bool
     */
    private function are_types_compatible($integration_field, $form_field, $field_types) {
        $integration_type = $integration_field['type'] ?? 'text';
        $form_type = $form_field['type'] ?? 'text';
        
        // Exact match
        if ($integration_type === $form_type) {
            return true;
        }
        
        // Check compatibility matrix
        if (isset($field_types[$form_type]['compatible_with'])) {
            return in_array($integration_type, $field_types[$form_type]['compatible_with']);
        }
        
        return false;
    }
    
    /**
     * Calculate name similarity percentage
     * @param string $name1
     * @param string $name2
     * @return float
     */
    private function calculate_name_similarity($name1, $name2) {
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));
        
        // Exact match
        if ($name1 === $name2) {
            return 100;
        }
        
        // Levenshtein distance
        $distance = levenshtein($name1, $name2);
        $max_length = max(strlen($name1), strlen($name2));
        
        if ($max_length === 0) {
            return 100;
        }
        
        $similarity = (1 - $distance / $max_length) * 100;
        
        // Bonus for partial matches
        if (strpos($name1, $name2) !== false || strpos($name2, $name1) !== false) {
            $similarity += 20;
        }
        
        return min($similarity, 100);
    }
    
    /**
     * Get bonus points for common field patterns
     * @param array $integration_field
     * @param array $form_field
     * @return int
     */
    private function get_pattern_bonus($integration_field, $form_field) {
        $bonus = 0;
        
        $patterns = array(
            'email' => array('email', 'e-mail', 'mail'),
            'name' => array('name', 'full_name', 'fullname', 'first_name', 'last_name'),
            'phone' => array('phone', 'telephone', 'mobile', 'cell'),
            'company' => array('company', 'organization', 'business'),
            'message' => array('message', 'comment', 'note', 'description'),
            'subject' => array('subject', 'title', 'topic'),
            'website' => array('website', 'url', 'site', 'web')
        );
        
        $integration_id = strtolower($integration_field['id'] ?? '');
        $form_label = strtolower($form_field['label'] ?? '');
        
        foreach ($patterns as $pattern_group => $keywords) {
            $integration_match = false;
            $form_match = false;
            
            foreach ($keywords as $keyword) {
                if (strpos($integration_id, $keyword) !== false) {
                    $integration_match = true;
                }
                if (strpos($form_label, $keyword) !== false) {
                    $form_match = true;
                }
            }
            
            if ($integration_match && $form_match) {
                $bonus += 15;
                break;
            }
        }
        
        return $bonus;
    }
    
    /**
     * Validate field mappings
     * @param array $mappings
     * @param array $form_fields
     * @param array $integration_fields
     * @return array
     */
    public function validate_field_mappings($mappings, $form_fields, $integration_fields) {
        $errors = array();
        $warnings = array();
        
        // Create lookup arrays for easier validation
        $form_fields_lookup = array();
        foreach ($form_fields as $field) {
            $form_fields_lookup[$field['id']] = $field;
        }
        
        $integration_fields_lookup = array();
        foreach ($integration_fields as $field) {
            $integration_fields_lookup[$field['id']] = $field;
        }
        
        foreach ($mappings as $integration_field_id => $form_field_id) {
            // Skip empty mappings
            if (empty($form_field_id)) {
                // Check if integration field is required
                if (isset($integration_fields_lookup[$integration_field_id]['required']) 
                    && $integration_fields_lookup[$integration_field_id]['required']) {
                    $errors[] = sprintf(
                        __('Required integration field "%s" is not mapped to any form field.', 'mavlers-contact-forms'),
                        $integration_fields_lookup[$integration_field_id]['label'] ?? $integration_field_id
                    );
                }
                continue;
            }
            
            // Check if form field exists
            if (!isset($form_fields_lookup[$form_field_id])) {
                $errors[] = sprintf(
                    __('Form field "%s" no longer exists but is mapped to integration field "%s".', 'mavlers-contact-forms'),
                    $form_field_id,
                    $integration_field_id
                );
                continue;
            }
            
            // Check if integration field exists
            if (!isset($integration_fields_lookup[$integration_field_id])) {
                $warnings[] = sprintf(
                    __('Integration field "%s" no longer exists but has a mapping.', 'mavlers-contact-forms'),
                    $integration_field_id
                );
                continue;
            }
            
            // Type compatibility warning
            $form_field = $form_fields_lookup[$form_field_id];
            $integration_field = $integration_fields_lookup[$integration_field_id];
            
            if (!$this->are_types_compatible($integration_field, $form_field, $this->get_field_types())) {
                $warnings[] = sprintf(
                    __('Field type mismatch: Form field "%s" (%s) mapped to integration field "%s" (%s).', 'mavlers-contact-forms'),
                    $form_field['label'],
                    $form_field['type'],
                    $integration_field['label'] ?? $integration_field_id,
                    $integration_field['type'] ?? 'unknown'
                );
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
    
    /**
     * Transform field value based on field types
     * @param mixed $value
     * @param string $from_type
     * @param string $to_type
     * @return mixed
     */
    public function transform_field_value($value, $from_type, $to_type) {
        // If types are the same, return as-is
        if ($from_type === $to_type) {
            return $value;
        }
        
        // Handle empty values
        if (empty($value) && $value !== '0' && $value !== 0) {
            return null;
        }
        
        switch ($to_type) {
            case 'email':
                return sanitize_email($value);
                
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;
                
            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }
                $value = strtolower(trim($value));
                return in_array($value, array('1', 'yes', 'true', 'on', 'checked'));
                
            case 'date':
                if ($from_type === 'datetime') {
                    return date('Y-m-d', strtotime($value));
                }
                return date('Y-m-d', strtotime($value));
                
            case 'datetime':
                return date('Y-m-d H:i:s', strtotime($value));
                
            case 'url':
                return esc_url_raw($value);
                
            case 'phone':
                return preg_replace('/[^0-9+\-\s\(\)]/', '', $value);
                
            case 'array':
                if (is_array($value)) {
                    return $value;
                }
                // Try to parse as JSON
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                // Split by common delimiters
                return preg_split('/[,;\|]/', $value);
                
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Get predefined field mapping templates
     * @return array
     */
    public function get_field_mapping_templates() {
        return array(
            'contact_form' => array(
                'name' => __('Standard Contact Form', 'mavlers-contact-forms'),
                'mappings' => array(
                    'email' => 'email',
                    'first_name' => 'name',
                    'message' => 'message',
                    'phone' => 'phone'
                )
            ),
            'newsletter_signup' => array(
                'name' => __('Newsletter Signup', 'mavlers-contact-forms'),
                'mappings' => array(
                    'email' => 'email',
                    'first_name' => 'first_name',
                    'last_name' => 'last_name'
                )
            ),
            'lead_generation' => array(
                'name' => __('Lead Generation', 'mavlers-contact-forms'),
                'mappings' => array(
                    'email' => 'email',
                    'first_name' => 'first_name',
                    'last_name' => 'last_name',
                    'company' => 'company',
                    'phone' => 'phone',
                    'website' => 'website'
                )
            )
        );
    }
    
    /**
     * Apply a field mapping template
     * @param string $template_id
     * @param array $form_fields
     * @return array
     */
    public function apply_mapping_template($template_id, $form_fields) {
        $templates = $this->get_field_mapping_templates();
        
        if (!isset($templates[$template_id])) {
            return array();
        }
        
        $template = $templates[$template_id];
        $mappings = array();
        
        foreach ($template['mappings'] as $integration_field => $suggested_form_field) {
            // Try to find a matching form field
            $matched_field = $this->find_form_field_by_pattern($form_fields, $suggested_form_field);
            if ($matched_field) {
                $mappings[$integration_field] = $matched_field['id'];
            }
        }
        
        return $mappings;
    }
    
    /**
     * Find form field by pattern matching
     * @param array $form_fields
     * @param string $pattern
     * @return array|null
     */
    private function find_form_field_by_pattern($form_fields, $pattern) {
        $pattern = strtolower($pattern);
        
        foreach ($form_fields as $field) {
            $field_id = strtolower($field['id'] ?? '');
            $field_label = strtolower($field['label'] ?? '');
            
            if ($field_id === $pattern || 
                strpos($field_id, $pattern) !== false || 
                strpos($field_label, $pattern) !== false) {
                return $field;
            }
        }
        
        return null;
    }
} 