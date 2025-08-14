<?php

namespace Connect2Form\Integrations\Core\Services;

/**
 * Cache Manager
 * 
 * Handles caching for API responses and integration data.
 *
 * @since    2.0.0
 * @access   public
 */
class CacheManager {

    /**
     * Cache group name.
     *
     * @since    2.0.0
     * @access   private
     * @var      string    $cache_group    Cache group name.
     */
    private $cache_group = 'connect2form_integrations';

    /**
     * Default expiry time in seconds.
     *
     * @since    2.0.0
     * @access   private
     * @var      int    $default_expiry    Default expiry time in seconds.
     */
    private $default_expiry = 3600; // 1 hour.

    /**
     * Get cached data.
     *
     * @since    2.0.0
     * @param    string $key     Cache key.
     * @param    mixed  $default Default value if cache miss.
     * @return   mixed
     */
    public function get( string $key, $default = null ) {
        $cached = wp_cache_get( $key, $this->cache_group );
        return $cached !== false ? $cached : $default;
    }

    /**
     * Set cached data.
     *
     * @since    2.0.0
     * @param    string $key    Cache key.
     * @param    mixed  $value  Value to cache.
     * @param    int    $expiry Expiry time in seconds.
     * @return   bool
     */
    public function set( string $key, $value, int $expiry = null ): bool {
        $expiry = $expiry ?? $this->default_expiry;
        return wp_cache_set( $key, $value, $this->cache_group, $expiry );
    }

    /**
     * Delete cached data.
     *
     * @since    2.0.0
     * @param    string $key Cache key.
     * @return   bool
     */
    public function delete( string $key ): bool {
        return wp_cache_delete( $key, $this->cache_group );
    }

    /**
     * Get or set cached data with callback.
     *
     * @since    2.0.0
     * @param    string   $key      Cache key.
     * @param    callable $callback Callback function to generate value.
     * @param    int      $expiry   Expiry time in seconds.
     * @return   mixed
     */
    public function remember( string $key, callable $callback, int $expiry = null ) {
        $cached = $this->get( $key );
        
        if ( $cached !== null ) {
            return $cached;
        }

        $value = $callback();
        $this->set( $key, $value, $expiry );
        
        return $value;
    }

    /**
     * Cache API response with proper key generation.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @param    string $endpoint      API endpoint.
     * @param    array  $params        API parameters.
     * @param    mixed  $response      API response.
     * @param    int    $expiry        Expiry time in seconds.
     * @return   bool
     */
    public function cacheApiResponse( string $integration_id, string $endpoint, array $params, $response, int $expiry = null ): bool {
        $key = $this->generateApiCacheKey( $integration_id, $endpoint, $params );
        return $this->set( $key, $response, $expiry );
    }

    /**
     * Get cached API response.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @param    string $endpoint      API endpoint.
     * @param    array  $params        API parameters.
     * @return   mixed
     */
    public function getCachedApiResponse( string $integration_id, string $endpoint, array $params ) {
        $key = $this->generateApiCacheKey( $integration_id, $endpoint, $params );
        return $this->get( $key );
    }

    /**
     * Generate cache key for API calls.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $integration_id Integration ID.
     * @param    string $endpoint      API endpoint.
     * @param    array  $params        API parameters.
     * @return   string
     */
    private function generateApiCacheKey( string $integration_id, string $endpoint, array $params ): string {
        $param_string = md5( serialize( $params ) );
        return "api_{$integration_id}_{$endpoint}_{$param_string}";
    }

    /**
     * Cache integration settings.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @param    array  $settings      Settings to cache.
     * @return   bool
     */
    public function cacheIntegrationSettings( string $integration_id, array $settings ): bool {
        $key = "settings_{$integration_id}";
        return $this->set( $key, $settings, 7200 ); // 2 hours.
    }

    /**
     * Get cached integration settings.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     * @return   array|null
     */
    public function getCachedIntegrationSettings( string $integration_id ): ?array {
        $key = "settings_{$integration_id}";
        return $this->get( $key );
    }

    /**
     * Cache form fields.
     *
     * @since    2.0.0
     * @param    int   $form_id Form ID.
     * @param    array $fields  Form fields.
     * @return   bool
     */
    public function cacheFormFields( int $form_id, array $fields ): bool {
        $key = "form_fields_{$form_id}";
        return $this->set( $key, $fields, 1800 ); // 30 minutes.
    }

    /**
     * Get cached form fields.
     *
     * @since    2.0.0
     * @param    int $form_id Form ID.
     * @return   array|null
     */
    public function getCachedFormFields( int $form_id ): ?array {
        $key = "form_fields_{$form_id}";
        return $this->get( $key );
    }

    /**
     * Cache field mappings.
     *
     * @since    2.0.0
     * @param    int    $form_id       Form ID.
     * @param    string $integration_id Integration ID.
     * @param    array  $mappings      Field mappings.
     * @return   bool
     */
    public function cacheFieldMappings( int $form_id, string $integration_id, array $mappings ): bool {
        $key = "field_mappings_{$form_id}_{$integration_id}";
        return $this->set( $key, $mappings, 3600 ); // 1 hour.
    }

    /**
     * Get cached field mappings.
     *
     * @since    2.0.0
     * @param    int    $form_id       Form ID.
     * @param    string $integration_id Integration ID.
     * @return   array|null
     */
    public function getCachedFieldMappings( int $form_id, string $integration_id ): ?array {
        $key = "field_mappings_{$form_id}_{$integration_id}";
        return $this->get( $key );
    }

    /**
     * Clear all integration caches.
     *
     * @since    2.0.0
     * @param    string $integration_id Integration ID.
     */
    public function clearIntegrationCaches( string $integration_id ): void {
        global $wpdb;
        
        // Clear WordPress object cache
        wp_cache_flush_group( $this->cache_group );
        
        // Clear specific integration caches
        $this->delete( "settings_{$integration_id}" );
        
        // Clear API response caches for this integration
        $this->clearApiCaches( $integration_id );
    }

    /**
     * Clear API caches for specific integration.
     *
     * @since    2.0.0
     * @access   private
     * @param    string $integration_id Integration ID.
     */
    private function clearApiCaches( string $integration_id ): void {
        // This would need to be implemented based on the specific cache keys used
        // For now, we'll clear the entire cache group
        wp_cache_flush_group( $this->cache_group );
    }

    /**
     * Clear form-specific caches.
     *
     * @since    2.0.0
     * @param    int $form_id Form ID.
     */
    public function clearFormCaches( int $form_id ): void {
        $this->delete( "form_fields_{$form_id}" );
        
        // Clear field mappings for all integrations
        $integrations = ['mailchimp', 'hubspot'];
        foreach ( $integrations as $integration_id ) {
            $this->delete( "field_mappings_{$form_id}_{$integration_id}" );
        }
    }

    /**
     * Get cache statistics.
     *
     * @since    2.0.0
     * @return   array
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
     * Warm up cache for frequently accessed data.
     *
     * @since    2.0.0
     */
    public function warmUpCache(): void {
        // Cache integration settings
        $integrations = ['mailchimp', 'hubspot'];
        foreach ( $integrations as $integration_id ) {
            $settings = get_option( "connect2form_integrations_global_{$integration_id}", [] );
            if ( !empty( $settings ) ) {
                $this->cacheIntegrationSettings( $integration_id, $settings );
            }
        }

        // Cache recent form fields
        $recent_forms = $this->getRecentForms();
        foreach ( $recent_forms as $form_id ) {
            $fields = $this->getFormFieldsFromDatabase( $form_id );
            if ( !empty( $fields ) ) {
                $this->cacheFormFields( $form_id, $fields );
            }
        }
    }

    /**
     * Get recent forms for cache warming.
     *
     * @since    2.0.0
     * @return   array
     */
    private function getRecentForms(): array {
        // Use the FormService for database operations
        $form_service = new FormService();
        $forms = $form_service->get_recent_forms( 10 );
        
        return array_map( function( $form ) {
            return $form->id;
        }, $forms );
    }

    /**
     * Get form fields from database.
     *
     * @since    2.0.0
     * @param    int $form_id Form ID.
     * @return   array
     */
    private function getFormFieldsFromDatabase( int $form_id ): array {
        // Use the FormService for database operations
        $form_service = new FormService();
        $form = $form_service->get_form( $form_id );
        
        if ( !$form || !$form->fields ) {
            return [];
        }

        $fields_data = is_string( $form->fields ) ? json_decode( $form->fields, true ) : ( is_array( $form->fields ) ? $form->fields : array() );
        return is_array( $fields_data ) ? $fields_data : [];
    }

    /**
     * Check if cache is working properly.
     *
     * @since    2.0.0
     * @return   bool
     */
    public function testCache(): bool {
        $test_key = 'cache_test_' . time();
        $test_value = 'test_value_' . time();
        
        $set_result = $this->set( $test_key, $test_value, 60 );
        $get_result = $this->get( $test_key );
        $delete_result = $this->delete( $test_key );
        
        return $set_result && $get_result === $test_value && $delete_result;
    }

    /**
     * Initialize cache system.
     *
     * @since    2.0.0
     */
    public function init(): void {
        // Test cache functionality
        if ( !$this->testCache() ) {
    
        }
    }

    /**
     * Clear all caches.
     *
     * @since    2.0.0
     */
    public function clear_all(): void {
        // WordPress doesn't have a built-in way to clear all cache keys
        // This is a simplified implementation
        // In production, you might want to use a more sophisticated approach
        
        // Clear known cache patterns
        $this->clearIntegrationCaches( 'mailchimp' );
        $this->clearIntegrationCaches( 'hubspot' );
        
        // Clear form-related caches
        $this->clearFormCaches( 0 ); // This will clear general form caches
        
        // Clear API caches
        $this->clearApiCaches( 'mailchimp' );
        $this->clearApiCaches( 'hubspot' );
    }

    /**
     * Delete cache keys matching a pattern.
     *
     * @since    2.0.0
     * @param    string $pattern Cache pattern.
     */
    public function delete_pattern( string $pattern ): void {
        // Convert pattern to specific keys we know about
        if ( strpos( $pattern, 'submission_*' ) !== false ) {
            // Clear submission-related caches
            $this->delete( 'recent_submissions_10' );
            $this->delete( 'recent_submissions_20' );
            $this->delete( 'recent_submissions_50' );
        }
        
        if ( strpos( $pattern, 'form_submissions_*' ) !== false ) {
            // Clear form submissions count caches
            // This would need to be more specific in a real implementation
        }
        
        if ( strpos( $pattern, 'submission_stats*' ) !== false ) {
            // Clear submission stats caches
            $this->delete( 'submission_stats' );
            $this->delete( 'submission_stats_0' );
        }
        
        if ( strpos( $pattern, 'recent_submissions_*' ) !== false ) {
            // Clear recent submissions caches
            $this->delete( 'recent_submissions_10' );
            $this->delete( 'recent_submissions_20' );
            $this->delete( 'recent_submissions_50' );
        }
    }
} 
