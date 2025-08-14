<?php
/**
 * Integrations Handler Class
 * 
 * Handles third-party integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_CF_Integrations {
    
    /**
     * Initialize the integrations
     */
    public function __construct() {
        add_action('mavlers_cf_after_submission', array($this, 'handle_integrations'), 10, 2);
    }
    
    /**
     * Handle integrations after form submission
     * 
     * @param int $submission_id The submission ID
     * @param array $form_data The form data
     */
    public function handle_integrations($submission_id, $form_data) {
        // Action hook for custom integrations
        do_action('mavlers_cf_process_custom_integrations', $submission_id, $form_data);
    }

    /**
     * Process integrations after form submission
     */
    public function process_integrations($submission_id, $form_data, $form) {
        // Filter form data before processing integrations
        $form_data = apply_filters('mavlers_cf_integrations_form_data', $form_data, $submission_id, $form);
        
        // Action hook before processing integrations
        do_action('mavlers_cf_before_process_integrations', $submission_id, $form_data, $form);

        $form_settings = json_decode($form->settings, true);
        $fields = json_decode($form->fields, true);
        $submission = $form_data['fields'];

        // Filter submission data
        $submission = apply_filters('mavlers_cf_integrations_submission_data', $submission, $submission_id, $form);

        // Action hook for custom integrations
        do_action('mavlers_cf_process_custom_integrations', $submission_id, $submission, $form_settings, $form);

        // Action hook after processing integrations
        do_action('mavlers_cf_after_process_integrations', $submission_id, $form_data, $form);
    }
}

// Initialize the integrations
new Mavlers_CF_Integrations(); 