<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * Cache Manager
 * 
 * Handles caching for API responses and integration data
 */
class CacheManager {

    private $cache_group = 'mavlers_cf_integrations';
    private $default_expiry = 3600; // 1 hour

    /**
     * Get cached data
     */
    public function get(string $key, $default = null) {
        $cached = wp_cache_get($key, $this->cache_group);
        return $cached !== false ? $cached : $default;
    }

    /**
     * Set cached data
     */
    public function set(string $key, $value, int $expiry = null): bool {
        $expiry = $expiry ?? $this->default_expiry;
        return wp_cache_set($key, $value, $this->cache_group, $expiry);
    }

    /**
     * Delete cached data
     */
    public function delete(string $key): bool {
        return wp_cache_delete($key, $this->cache_group);
    }

    /**
     * Get or set cached data with callback
     */
    public function remember(string $key, callable $callback, int $expiry = null) {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $expiry);
        
        return $value;
    }

    /**
     * Cache API response with proper key generation
     */
    public function cacheApiResponse(string $integration_id, string $endpoint, array $params, $response, int $expiry = null): bool {
        $key = $this->generateApiCacheKey($integration_id, $endpoint, $params);
        return $this->set($key, $response, $expiry);
    }

    /**
     * Get cached API response
     */
    public function getCachedApiResponse(string $integration_id, string $endpoint, array $params) {
        $key = $this->generateApiCacheKey($integration_id, $endpoint, $params);
        return $this->get($key);
    }

    /**
     * Generate cache key for API calls
     */
    private function generateApiCacheKey(string $integration_id, string $endpoint, array $params): string {
        $param_string = md5(serialize($params));
        return "api_{$integration_id}_{$endpoint}_{$param_string}";
    }

    /**
     * Cache integration settings
     */
    public function cacheIntegrationSettings(string $integration_id, array $settings): bool {
        $key = "settings_{$integration_id}";
        return $this->set($key, $settings, 7200); // 2 hours
    }

    /**
     * Get cached integration settings
     */
    public function getCachedIntegrationSettings(string $integration_id): ?array {
        $key = "settings_{$integration_id}";
        return $this->get($key);
    }

    /**
     * Cache form fields
     */
    public function cacheFormFields(int $form_id, array $fields): bool {
        $key = "form_fields_{$form_id}";
        return $this->set($key, $fields, 1800); // 30 minutes
    }

    /**
     * Get cached form fields
     */
    public function getCachedFormFields(int $form_id): ?array {
        $key = "form_fields_{$form_id}";
        return $this->get($key);
    }

    /**
     * Cache field mappings
     */
    public function cacheFieldMappings(int $form_id, string $integration_id, array $mappings): bool {
        $key = "field_mappings_{$form_id}_{$integration_id}";
        return $this->set($key, $mappings, 3600); // 1 hour
    }

    /**
     * Get cached field mappings
     */
    public function getCachedFieldMappings(int $form_id, string $integration_id): ?array {
        $key = "field_mappings_{$form_id}_{$integration_id}";
        return $this->get($key);
    }

    /**
     * Clear all integration caches
     */
    public function clearIntegrationCaches(string $integration_id): void {
        global $wpdb;
        
        // Clear WordPress object cache
        wp_cache_flush_group($this->cache_group);
        
        // Clear specific integration caches
        $this->delete("settings_{$integration_id}");
        
        // Clear API response caches for this integration
        $this->clearApiCaches($integration_id);
    }

    /**
     * Clear API caches for specific integration
     */
    private function clearApiCaches(string $integration_id): void {
        // This would need to be implemented based on the specific cache keys used
        // For now, we'll clear the entire cache group
        wp_cache_flush_group($this->cache_group);
    }

    /**
     * Clear form-specific caches
     */
    public function clearFormCaches(int $form_id): void {
        $this->delete("form_fields_{$form_id}");
        
        // Clear field mappings for all integrations
        $integrations = ['mailchimp', 'hubspot'];
        foreach ($integrations as $integration_id) {
            $this->delete("field_mappings_{$form_id}_{$integration_id}");
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array {
        global $wpdb;
        
        $stats = [
            'total_keys' => 0,
            'memory_usage' => 0,
            'hit_rate' => 0
        ];

        // This is a simplified implementation
        // In production, you'd want to use a proper cache monitoring system
        
        return $stats;
    }

    /**
     * Warm up cache for frequently accessed data
     */
    public function warmUpCache(): void {
        // Cache integration settings
        $integrations = ['mailchimp', 'hubspot'];
        foreach ($integrations as $integration_id) {
            $settings = get_option("mavlers_cf_integrations_global_{$integration_id}", []);
            if (!empty($settings)) {
                $this->cacheIntegrationSettings($integration_id, $settings);
            }
        }

        // Cache recent form fields
        $recent_forms = $this->getRecentForms();
        foreach ($recent_forms as $form_id) {
            $fields = $this->getFormFieldsFromDatabase($form_id);
            if (!empty($fields)) {
                $this->cacheFormFields($form_id, $fields);
            }
        }
    }

    /**
     * Get recent forms for cache warming
     */
    private function getRecentForms(): array {
        global $wpdb;
        
        $forms = $wpdb->get_col(
            "SELECT id FROM {$wpdb->prefix}mavlers_cf_forms 
             ORDER BY updated_at DESC 
             LIMIT 10"
        );

        return array_map('intval', $forms);
    }

    /**
     * Get form fields from database
     */
    private function getFormFieldsFromDatabase(int $form_id): array {
        global $wpdb;
        
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT fields FROM {$wpdb->prefix}mavlers_cf_forms WHERE id = %d",
            $form_id
        ));

        if (!$form || !$form->fields) {
            return [];
        }

        $fields_data = json_decode($form->fields, true);
        return is_array($fields_data) ? $fields_data : [];
    }

    /**
     * Check if cache is working properly
     */
    public function testCache(): bool {
        $test_key = 'cache_test_' . time();
        $test_value = 'test_value_' . time();
        
        $set_result = $this->set($test_key, $test_value, 60);
        $get_result = $this->get($test_key);
        $delete_result = $this->delete($test_key);
        
        return $set_result && $get_result === $test_value && $delete_result;
    }
} 