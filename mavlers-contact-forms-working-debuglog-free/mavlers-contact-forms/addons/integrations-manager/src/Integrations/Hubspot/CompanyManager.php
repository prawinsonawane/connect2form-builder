<?php

namespace MavlersCF\Integrations\Hubspot;

/**
 * HubSpot Company Manager
 * 
 * Handles company operations and contact associations
 */
class CompanyManager {

    protected $version = '1.0.0';
    protected $language_manager;

    public function __construct() {
        $this->language_manager = new LanguageManager();
    }

    /**
     * Get companies from HubSpot
     */
    public function get_companies(int $limit = 100): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/objects/companies?limit={$limit}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to fetch companies: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            $companies = [];
            
            if (isset($data['results'])) {
                foreach ($data['results'] as $company) {
                    $companies[] = [
                        'id' => $company['id'],
                        'name' => $company['properties']['name']['value'] ?? '',
                        'domain' => $company['properties']['domain']['value'] ?? '',
                        'industry' => $company['properties']['industry']['value'] ?? '',
                        'city' => $company['properties']['city']['value'] ?? '',
                        'state' => $company['properties']['state']['value'] ?? '',
                        'country' => $company['properties']['country']['value'] ?? ''
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $companies
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to fetch companies ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Create a new company in HubSpot
     */
    public function create_company(array $company_data): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $company_payload = [
            'properties' => $company_data
        ];

        $url = "https://api.hubapi.com/crm/v3/objects/companies";
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($company_payload),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to create company: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'message' => 'Company created successfully',
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to create company ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Associate contact with company
     */
    public function associate_contact_with_company(string $contact_id, string $company_id): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $association_data = [
            'types' => [
                [
                    'associationCategory' => 'HUBSPOT_DEFINED',
                    'associationTypeId' => 1 // Contact to Company association
                ]
            ]
        ];

        $url = "https://api.hubapi.com/crm/v3/objects/contacts/{$contact_id}/associations/companies/{$company_id}";
        $response = wp_remote_put($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($association_data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to associate contact with company: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'message' => 'Contact associated with company successfully'
            ];
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $error_message = $data['message'] ?? 'Unknown error';
            
            return [
                'success' => false,
                'error' => "Failed to associate contact with company ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get company by ID
     */
    public function get_company(string $company_id): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/objects/companies/{$company_id}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to fetch company: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to fetch company ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Update company in HubSpot
     */
    public function update_company(string $company_id, array $company_data): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $company_payload = [
            'properties' => $company_data
        ];

        $url = "https://api.hubapi.com/crm/v3/objects/companies/{$company_id}";
        $response = wp_remote_patch($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($company_payload),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to update company: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => 'Company updated successfully',
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to update company ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Search companies by name
     */
    public function search_companies(string $search_term, int $limit = 10): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $filter = [
            'propertyName' => 'name',
            'operator' => 'CONTAINS_TOKEN',
            'value' => $search_term
        ];

        $url = "https://api.hubapi.com/crm/v3/objects/companies?limit={$limit}&filter=" . urlencode(json_encode($filter));
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to search companies: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            $companies = [];
            
            if (isset($data['results'])) {
                foreach ($data['results'] as $company) {
                    $companies[] = [
                        'id' => $company['id'],
                        'name' => $company['properties']['name']['value'] ?? '',
                        'domain' => $company['properties']['domain']['value'] ?? '',
                        'industry' => $company['properties']['industry']['value'] ?? ''
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $companies
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to search companies ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get global settings for HubSpot
     */
    private function get_global_settings() {
        $global_settings = get_option('mavlers_cf_integrations_global', []);
        return $global_settings['hubspot'] ?? [];
    }

    /**
     * Translation helper
     */
    private function __($text, $fallback = null) {
        if ($this->language_manager) {
            return $this->language_manager->translate($text);
        }
        return $fallback ?: $text;
    }
} 