<?php

namespace MavlersCF\Integrations\Hubspot;

/**
 * HubSpot Deal Manager
 * 
 * Handles deal creation, management, and pipeline operations
 */
class DealManager {

    protected $version = '1.0.0';
    protected $language_manager;

    public function __construct() {
        $this->language_manager = new LanguageManager();
    }

    /**
     * Get available pipelines from HubSpot
     */
    public function get_pipelines(): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/pipelines/deals";
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
                'error' => 'Failed to fetch pipelines: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            $pipelines = [];
            
            if (isset($data['results'])) {
                foreach ($data['results'] as $pipeline) {
                    $pipelines[] = [
                        'id' => $pipeline['id'],
                        'label' => $pipeline['label'],
                        'displayOrder' => $pipeline['displayOrder'],
                        'stages' => $pipeline['stages'] ?? []
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $pipelines
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to fetch pipelines ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get stages for a specific pipeline
     */
    public function get_pipeline_stages(string $pipeline_id): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/pipelines/deals/{$pipeline_id}";
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
                'error' => 'Failed to fetch pipeline stages: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            $stages = [];
            
            if (isset($data['stages'])) {
                foreach ($data['stages'] as $stage) {
                    $stages[] = [
                        'id' => $stage['id'],
                        'label' => $stage['label'],
                        'displayOrder' => $stage['displayOrder'],
                        'probability' => $stage['probability'] ?? 0
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $stages
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to fetch pipeline stages ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Create a new deal in HubSpot
     */
    public function create_deal(array $deal_data, string $pipeline_id = '', string $stage_id = ''): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        // Prepare deal properties
        $properties = $deal_data['properties'] ?? [];
        
        // Add pipeline and stage if provided
        if (!empty($pipeline_id)) {
            $properties['pipeline'] = $pipeline_id;
        }
        
        if (!empty($stage_id)) {
            $properties['dealstage'] = $stage_id;
        }

        $deal_payload = [
            'properties' => $properties
        ];

        $url = "https://api.hubapi.com/crm/v3/objects/deals";
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($deal_payload),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to create deal: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'message' => 'Deal created successfully',
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to create deal ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Update an existing deal in HubSpot
     */
    public function update_deal(string $deal_id, array $deal_data): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $deal_payload = [
            'properties' => $deal_data['properties'] ?? []
        ];

        $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
        $response = wp_remote_patch($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($deal_payload),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to update deal: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code === 200) {
            return [
                'success' => true,
                'message' => 'Deal updated successfully',
                'data' => $data
            ];
        } else {
            $error_message = $data['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => "Failed to update deal ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Associate deal with contact
     */
    public function associate_deal_with_contact(string $deal_id, string $contact_id): array {
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
                    'associationTypeId' => 4 // Deal to Contact association
                ]
            ]
        ];

        $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}/associations/contacts/{$contact_id}";
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
                'error' => 'Failed to associate deal with contact: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'message' => 'Deal associated with contact successfully'
            ];
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $error_message = $data['message'] ?? 'Unknown error';
            
            return [
                'success' => false,
                'error' => "Failed to associate deal with contact ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get deal by ID
     */
    public function get_deal(string $deal_id): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/objects/deals/{$deal_id}";
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
                'error' => 'Failed to fetch deal: ' . $response->get_error_message()
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
                'error' => "Failed to fetch deal ({$status_code}): {$error_message}"
            ];
        }
    }

    /**
     * Get deals with filters
     */
    public function get_deals(array $filters = [], int $limit = 100): array {
        $global_settings = $this->get_global_settings();
        $access_token = $global_settings['access_token'] ?? '';

        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'HubSpot not configured'
            ];
        }

        $url = "https://api.hubapi.com/crm/v3/objects/deals?limit={$limit}";
        
        // Add filters if provided
        if (!empty($filters)) {
            $url .= '&filter=' . urlencode(json_encode($filters));
        }

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
                'error' => 'Failed to fetch deals: ' . $response->get_error_message()
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
                'error' => "Failed to fetch deals ({$status_code}): {$error_message}"
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