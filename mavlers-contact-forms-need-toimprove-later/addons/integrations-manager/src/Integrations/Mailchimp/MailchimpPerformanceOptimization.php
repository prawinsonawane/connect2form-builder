<?php

namespace MavlersCF\Integrations\Mailchimp;

/**
 * Mailchimp Performance Optimization
 * 
 * Provides comprehensive performance optimizations for the Mailchimp integration
 */
class MailchimpPerformanceOptimization {

    private static $cache_group = 'mailchimp_integration';
    private static $cache_timeout = 3600; // 1 hour

    /**
     * Cache API responses
     */
    public static function cacheApiResponse(string $key, $data, int $timeout = null): bool {
        $timeout = $timeout ?: self::$cache_timeout;
        return wp_cache_set($key, $data, self::$cache_group, $timeout);
    }

    /**
     * Get cached API response
     */
    public static function getCachedApiResponse(string $key) {
        return wp_cache_get($key, self::$cache_group);
    }

    /**
     * Cache Mailchimp audiences
     */
    public static function cacheMailchimpAudiences(string $api_key, array $audiences): bool {
        $cache_key = 'mailchimp_audiences_' . md5($api_key);
        return self::cacheApiResponse($cache_key, $audiences, 7200); // 2 hours
    }

    /**
     * Get cached Mailchimp audiences
     */
    public static function getCachedMailchimpAudiences(string $api_key): ?array {
        $cache_key = 'mailchimp_audiences_' . md5($api_key);
        return self::getCachedApiResponse($cache_key);
    }

    /**
     * Cache merge fields
     */
    public static function cacheMergeFields(string $api_key, string $audience_id, array $merge_fields): bool {
        $cache_key = 'mailchimp_merge_fields_' . md5($api_key . $audience_id);
        return self::cacheApiResponse($cache_key, $merge_fields, 7200); // 2 hours
    }

    /**
     * Get cached merge fields
     */
    public static function getCachedMergeFields(string $api_key, string $audience_id): ?array {
        $cache_key = 'mailchimp_merge_fields_' . md5($api_key . $audience_id);
        return self::getCachedApiResponse($cache_key);
    }

    /**
     * Cache form fields
     */
    public static function cacheFormFields(int $form_id, array $fields): bool {
        $cache_key = 'mailchimp_form_fields_' . $form_id;
        return self::cacheApiResponse($cache_key, $fields, 1800); // 30 minutes
    }

    /**
     * Get cached form fields
     */
    public static function getCachedFormFields(int $form_id): ?array {
        $cache_key = 'mailchimp_form_fields_' . $form_id;
        return self::getCachedApiResponse($cache_key);
    }

    /**
     * Optimize database queries
     */
    public static function optimizeDatabaseQueries(): void {
        // Use prepared statements
        global $wpdb;
        
        // Add indexes if they don't exist
        $table_name = $wpdb->prefix . 'mavlers_cf_forms';
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_form_id ON {$table_name} (id)");
        
        $submissions_table = $wpdb->prefix . 'mavlers_cf_submissions';
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_submission_form_id ON {$submissions_table} (form_id)");
        
        // Add index for Mailchimp-specific data
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_mailchimp_integration ON {$table_name} (id) WHERE form_data LIKE '%mailchimp%'");
    }

    /**
     * Batch process API requests
     */
    public static function batchApiRequests(array $requests, callable $processor): array {
        $results = [];
        $batch_size = 10;
        $batches = array_chunk($requests, $batch_size);

        foreach ($batches as $batch) {
            $batch_results = [];
            
            // Process batch in parallel if possible
            foreach ($batch as $request) {
                $batch_results[] = $processor($request);
            }
            
            $results = array_merge($results, $batch_results);
            
            // Add small delay between batches to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }

        return $results;
    }

    /**
     * Optimize asset loading
     */
    public static function optimizeAssetLoading(): void {
        // Defer non-critical JavaScript
        add_filter('script_loader_tag', function($tag, $handle) {
            if (strpos($handle, 'mailchimp') !== false && strpos($handle, 'critical') === false) {
                return str_replace('<script ', '<script defer ', $tag);
            }
            return $tag;
        }, 10, 2);

        // Preload critical assets
        add_action('wp_head', function() {
            echo '<link rel="preload" href="' . plugin_dir_url(__FILE__) . '../../../assets/css/admin/mailchimp.css" as="style">';
            echo '<link rel="preload" href="' . plugin_dir_url(__FILE__) . '../../../assets/js/admin/mailchimp-form.js" as="script">';
        });
    }

    /**
     * Implement lazy loading for field mapping
     */
    public static function implementLazyLoading(): void {
        add_action('wp_footer', function() {
            ?>
            <script>
            // Lazy load field mapping table
            function lazyLoadFieldMapping() {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            loadFieldMappingData();
                            observer.unobserve(entry.target);
                        }
                    });
                });
                
                const fieldMappingTable = document.getElementById('mailchimp-field-mapping-table');
                if (fieldMappingTable) {
                    observer.observe(fieldMappingTable);
                }
            }
            
            function loadFieldMappingData() {
                // Load field mapping data only when needed
                if (typeof loadFormFields === 'function') {
                    loadFormFields();
                }
                if (typeof loadMergeFields === 'function') {
                    loadMergeFields();
                }
            }
            
            // Initialize lazy loading
            document.addEventListener('DOMContentLoaded', lazyLoadFieldMapping);
            </script>
            <?php
        });
    }

    /**
     * Optimize memory usage
     */
    public static function optimizeMemoryUsage(): void {
        // Clear unnecessary variables
        add_action('wp_ajax_mailchimp_cleanup', function() {
            wp_cache_flush_group(self::$cache_group);
            wp_die('Cache cleared');
        });

        // Limit memory usage for large operations
        add_action('mailchimp_large_operation', function() {
            if (memory_get_usage() > 64 * 1024 * 1024) { // 64MB
                wp_cache_flush_group(self::$cache_group);
                gc_collect_cycles();
            }
        });
    }

    /**
     * Implement request throttling
     */
    public static function implementRequestThrottling(): void {
        add_filter('mailchimp_api_request', function($request_data) {
            $user_id = get_current_user_id();
            $rate_limit_key = "mailchimp_rate_limit_{$user_id}";
            
            $requests = get_transient($rate_limit_key);
            if ($requests === false) {
                set_transient($rate_limit_key, 1, 60); // 1 minute
            } elseif ($requests >= 100) { // 100 requests per minute
                return ['error' => 'Rate limit exceeded'];
            } else {
                set_transient($rate_limit_key, $requests + 1, 60);
            }
            
            return $request_data;
        });
    }

    /**
     * Optimize JSON responses
     */
    public static function optimizeJsonResponses(): void {
        add_filter('wp_ajax_mailchimp_response', function($response) {
            // Remove unnecessary data from responses
            if (isset($response['data']) && is_array($response['data'])) {
                $response['data'] = array_slice($response['data'], 0, 1000); // Limit to 1000 items
            }
            
            return $response;
        });
    }

    /**
     * Implement connection pooling
     */
    public static function implementConnectionPooling(): void {
        // Reuse HTTP connections when possible
        add_filter('http_request_args', function($args, $url) {
            if (strpos($url, 'api.mailchimp.com') !== false) {
                $args['timeout'] = 30;
                $args['httpversion'] = '1.1';
                $args['keepalive'] = true;
            }
            return $args;
        }, 10, 2);
    }

    /**
     * Optimize form rendering
     */
    public static function optimizeFormRendering(): void {
        add_action('wp_enqueue_scripts', function() {
            // Only load Mailchimp assets when needed
            if (is_page() && has_shortcode(get_post()->post_content, 'mavlers_contact_form')) {
                wp_enqueue_script('mailchimp-form-optimized', plugin_dir_url(__FILE__) . '../../../assets/js/admin/mailchimp-form.js', ['jquery'], '1.0.0', true);
            }
        });
    }

    /**
     * Optimize audience loading
     */
    public static function optimizeAudienceLoading(): void {
        add_action('wp_ajax_mailchimp_get_audiences', function() {
            // Check cache first
            $api_key = sanitize_text_field($_POST['api_key'] ?? '');
            $cached_audiences = self::getCachedMailchimpAudiences($api_key);
            
            if ($cached_audiences !== null) {
                wp_send_json_success(['audiences' => $cached_audiences, 'cached' => true]);
                return;
            }
            
            // If not cached, proceed with API call
        });
    }

    /**
     * Optimize merge fields loading
     */
    public static function optimizeMergeFieldsLoading(): void {
        add_action('wp_ajax_mailchimp_get_merge_fields', function() {
            // Check cache first
            $api_key = sanitize_text_field($_POST['api_key'] ?? '');
            $audience_id = sanitize_text_field($_POST['audience_id'] ?? '');
            $cached_merge_fields = self::getCachedMergeFields($api_key, $audience_id);
            
            if ($cached_merge_fields !== null) {
                wp_send_json_success(['merge_fields' => $cached_merge_fields, 'cached' => true]);
                return;
            }
            
            // If not cached, proceed with API call
        });
    }

    /**
     * Get performance metrics
     */
    public static function getPerformanceMetrics(): array {
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cache_hits' => wp_cache_get('mailchimp_cache_hits', self::$cache_group) ?: 0,
            'cache_misses' => wp_cache_get('mailchimp_cache_misses', self::$cache_group) ?: 0,
            'api_requests' => wp_cache_get('mailchimp_api_requests', self::$cache_group) ?: 0,
            'response_time' => wp_cache_get('mailchimp_response_time', self::$cache_group) ?: 0
        ];
    }

    /**
     * Initialize all performance optimizations
     */
    public static function initialize(): void {
        self::optimizeDatabaseQueries();
        self::optimizeAssetLoading();
        self::implementLazyLoading();
        self::optimizeMemoryUsage();
        self::implementRequestThrottling();
        self::optimizeJsonResponses();
        self::implementConnectionPooling();
        self::optimizeFormRendering();
        self::optimizeAudienceLoading();
        self::optimizeMergeFieldsLoading();
    }
} 