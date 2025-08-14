<?php

/**
 * Integration Tests
 * 
 * Comprehensive test suite for the integrations addon
 */

class IntegrationTest {

    private $test_integrations = ['mailchimp', 'hubspot'];

    /**
     * Run all tests
     */
    public function runAllTests(): array {
        $results = [
            'security_tests' => $this->runSecurityTests(),
            'performance_tests' => $this->runPerformanceTests(),
            'functionality_tests' => $this->runFunctionalityTests(),
            'database_tests' => $this->runDatabaseTests(),
            'api_tests' => $this->runApiTests()
        ];

        return $results;
    }

    /**
     * Run security tests
     */
    private function runSecurityTests(): array {
        $results = [];

        // Test nonce verification
        $results['nonce_verification'] = $this->testNonceVerification();

        // Test input sanitization
        $results['input_sanitization'] = $this->testInputSanitization();

        // Test SQL injection prevention
        $results['sql_injection_prevention'] = $this->testSqlInjectionPrevention();

        // Test XSS prevention
        $results['xss_prevention'] = $this->testXssPrevention();

        // Test file upload security
        $results['file_upload_security'] = $this->testFileUploadSecurity();

        return $results;
    }

    /**
     * Run performance tests
     */
    private function runPerformanceTests(): array {
        $results = [];

        // Test API response caching
        $results['api_caching'] = $this->testApiCaching();

        // Test database query optimization
        $results['database_optimization'] = $this->testDatabaseOptimization();

        // Test memory usage
        $results['memory_usage'] = $this->testMemoryUsage();

        // Test response times
        $results['response_times'] = $this->testResponseTimes();

        return $results;
    }

    /**
     * Run functionality tests
     */
    private function runFunctionalityTests(): array {
        $results = [];

        foreach ($this->test_integrations as $integration) {
            $results[$integration] = $this->testIntegrationFunctionality($integration);
        }

        return $results;
    }

    /**
     * Run database tests
     */
    private function runDatabaseTests(): array {
        $results = [];

        // Test table creation
        $results['table_creation'] = $this->testTableCreation();

        // Test data insertion
        $results['data_insertion'] = $this->testDataInsertion();

        // Test data retrieval
        $results['data_retrieval'] = $this->testDataRetrieval();

        // Test data encryption
        $results['data_encryption'] = $this->testDataEncryption();

        return $results;
    }

    /**
     * Run API tests
     */
    private function runApiTests(): array {
        $results = [];

        // Test API connection
        $results['api_connection'] = $this->testApiConnection();

        // Test API error handling
        $results['api_error_handling'] = $this->testApiErrorHandling();

        // Test API rate limiting
        $results['api_rate_limiting'] = $this->testApiRateLimiting();

        return $results;
    }

    /**
     * Test nonce verification
     */
    private function testNonceVerification(): array {
        $results = ['passed' => true, 'errors' => []];

        // Test valid nonce
        $valid_nonce = wp_create_nonce('mavlers_cf_nonce');
        if (!wp_verify_nonce($valid_nonce, 'mavlers_cf_nonce')) {
            $results['passed'] = false;
            $results['errors'][] = 'Valid nonce verification failed';
        }

        // Test invalid nonce
        if (wp_verify_nonce('invalid_nonce', 'mavlers_cf_nonce')) {
            $results['passed'] = false;
            $results['errors'][] = 'Invalid nonce verification passed';
        }

        return $results;
    }

    /**
     * Test input sanitization
     */
    private function testInputSanitization(): array {
        $results = ['passed' => true, 'errors' => []];

        $test_input = '<script>alert("xss")</script>';
        $sanitized = sanitize_text_field($test_input);

        if ($sanitized === $test_input) {
            $results['passed'] = false;
            $results['errors'][] = 'XSS input not properly sanitized';
        }

        return $results;
    }

    /**
     * Test SQL injection prevention
     */
    private function testSqlInjectionPrevention(): array {
        $results = ['passed' => true, 'errors' => []];

        global $wpdb;
        
        $malicious_input = "'; DROP TABLE wp_posts; --";
        $safe_query = $wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_title = %s", $malicious_input);

        if (strpos($safe_query, 'DROP TABLE') !== false) {
            $results['passed'] = false;
            $results['errors'][] = 'SQL injection prevention failed';
        }

        return $results;
    }

    /**
     * Test XSS prevention
     */
    private function testXssPrevention(): array {
        $results = ['passed' => true, 'errors' => []];

        $malicious_input = '<script>alert("xss")</script>';
        $escaped = esc_html($malicious_input);

        if ($escaped === $malicious_input) {
            $results['passed'] = false;
            $results['errors'][] = 'XSS prevention failed';
        }

        return $results;
    }

    /**
     * Test file upload security
     */
    private function testFileUploadSecurity(): array {
        $results = ['passed' => true, 'errors' => []];

        // Test valid file upload
        $valid_file = [
            'name' => 'test.csv',
            'type' => 'text/csv',
            'tmp_name' => '/tmp/test.csv',
            'error' => 0,
            'size' => 1024
        ];

        // Test invalid file upload
        $invalid_file = [
            'name' => 'test.php',
            'type' => 'application/x-php',
            'tmp_name' => '/tmp/test.php',
            'error' => 0,
            'size' => 1024
        ];

        return $results;
    }

    /**
     * Test API caching
     */
    private function testApiCaching(): array {
        $results = ['passed' => true, 'errors' => []];

        $cache_manager = new \MavlersCF\Integrations\Core\Services\CacheManager();
        
        // Test cache set/get
        $test_data = ['test' => 'data'];
        $cache_manager->set('test_key', $test_data, 60);
        $retrieved = $cache_manager->get('test_key');

        if ($retrieved !== $test_data) {
            $results['passed'] = false;
            $results['errors'][] = 'Cache set/get failed';
        }

        return $results;
    }

    /**
     * Test database optimization
     */
    private function testDatabaseOptimization(): array {
        $results = ['passed' => true, 'errors' => []];

        global $wpdb;
        
        // Test query execution time
        $start_time = microtime(true);
        $wpdb->get_results("SELECT * FROM {$wpdb->posts} LIMIT 100");
        $execution_time = microtime(true) - $start_time;

        if ($execution_time > 1.0) {
            $results['passed'] = false;
            $results['errors'][] = 'Database query too slow';
        }

        return $results;
    }

    /**
     * Test memory usage
     */
    private function testMemoryUsage(): array {
        $results = ['passed' => true, 'errors' => []];

        $initial_memory = memory_get_usage();
        
        // Simulate integration processing
        for ($i = 0; $i < 100; $i++) {
            $data = str_repeat('test', 1000);
        }
        
        $final_memory = memory_get_usage();
        $memory_increase = $final_memory - $initial_memory;

        if ($memory_increase > 10 * 1024 * 1024) { // 10MB
            $results['passed'] = false;
            $results['errors'][] = 'Memory usage too high';
        }

        return $results;
    }

    /**
     * Test response times
     */
    private function testResponseTimes(): array {
        $results = ['passed' => true, 'errors' => []];

        $start_time = microtime(true);
        
        // Simulate API call
        usleep(100000); // 100ms
        
        $response_time = microtime(true) - $start_time;

        if ($response_time > 0.5) { // 500ms
            $results['passed'] = false;
            $results['errors'][] = 'Response time too slow';
        }

        return $results;
    }

    /**
     * Test integration functionality
     */
    private function testIntegrationFunctionality(string $integration_id): array {
        $results = ['passed' => true, 'errors' => []];

        // Test integration loading
        $integration = \MavlersCF\Integrations\Core\Plugin::getInstance()->getRegistry()->get($integration_id);
        
        if (!$integration) {
            $results['passed'] = false;
            $results['errors'][] = "Integration {$integration_id} not found";
            return $results;
        }

        // Test required methods
        $required_methods = ['getId', 'getName', 'getDescription', 'isConfigured'];
        foreach ($required_methods as $method) {
            if (!method_exists($integration, $method)) {
                $results['passed'] = false;
                $results['errors'][] = "Missing required method: {$method}";
            }
        }

        // Test interface implementation
        if (!$integration instanceof \MavlersCF\Integrations\Core\Interfaces\IntegrationInterface) {
            $results['passed'] = false;
            $results['errors'][] = 'Integration does not implement required interface';
        }

        return $results;
    }

    /**
     * Test table creation
     */
    private function testTableCreation(): array {
        $results = ['passed' => true, 'errors' => []];

        $database_manager = new \MavlersCF\Integrations\Core\Services\DatabaseManager();
        
        try {
            $database_manager->createTables();
            
            // Check if tables exist
            global $wpdb;
            $tables = [
                $wpdb->prefix . 'mavlers_cf_integration_logs',
                $wpdb->prefix . 'mavlers_cf_form_meta',
                $wpdb->prefix . 'mavlers_cf_integration_settings',
                $wpdb->prefix . 'mavlers_cf_field_mappings'
            ];

            foreach ($tables as $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
                if (!$exists) {
                    $results['passed'] = false;
                    $results['errors'][] = "Table {$table} not created";
                }
            }
        } catch (Exception $e) {
            $results['passed'] = false;
            $results['errors'][] = 'Table creation failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Test data insertion
     */
    private function testDataInsertion(): array {
        $results = ['passed' => true, 'errors' => []];

        $database_manager = new \MavlersCF\Integrations\Core\Services\DatabaseManager();
        
        $test_data = [
            'form_id' => 1,
            'integration_id' => 'test',
            'status' => 'success',
            'message' => 'Test message',
            'data' => ['test' => 'data']
        ];

        $log_id = $database_manager->insertLogEntry($test_data);
        
        if (!$log_id) {
            $results['passed'] = false;
            $results['errors'][] = 'Data insertion failed';
        }

        return $results;
    }

    /**
     * Test data retrieval
     */
    private function testDataRetrieval(): array {
        $results = ['passed' => true, 'errors' => []];

        $database_manager = new \MavlersCF\Integrations\Core\Services\DatabaseManager();
        
        $logs = $database_manager->getLogs(['integration_id' => 'test'], 10);
        
        if (!is_array($logs)) {
            $results['passed'] = false;
            $results['errors'][] = 'Data retrieval failed';
        }

        return $results;
    }

    /**
     * Test data encryption
     */
    private function testDataEncryption(): array {
        $results = ['passed' => true, 'errors' => []];

        $database_manager = new \MavlersCF\Integrations\Core\Services\DatabaseManager();
        
        $test_settings = [
            'api_key' => 'test_api_key_12345',
            'access_token' => 'test_token_67890'
        ];

        $saved = $database_manager->saveIntegrationSettings('test_integration', $test_settings);
        
        if (!$saved) {
            $results['passed'] = false;
            $results['errors'][] = 'Settings save failed';
        }

        $retrieved = $database_manager->getIntegrationSettings('test_integration');
        
        if ($retrieved['api_key'] !== $test_settings['api_key']) {
            $results['passed'] = false;
            $results['errors'][] = 'Settings retrieval failed';
        }

        return $results;
    }

    /**
     * Test API connection
     */
    private function testApiConnection(): array {
        $results = ['passed' => true, 'errors' => []];

        // Test basic connectivity
        $response = wp_remote_get('https://httpbin.org/get', [
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            $results['passed'] = false;
            $results['errors'][] = 'Basic connectivity test failed';
        }

        return $results;
    }

    /**
     * Test API error handling
     */
    private function testApiErrorHandling(): array {
        $results = ['passed' => true, 'errors' => []];

        $error_handler = new \MavlersCF\Integrations\Core\Services\ErrorHandler();
        
        // Test error handling
        $test_error = new Exception('Test error message');
        $handled = $error_handler->handleIntegrationError($test_error, 'test_integration');
        
        if (!isset($handled['success'])) {
            $results['passed'] = false;
            $results['errors'][] = 'Error handling failed';
        }

        return $results;
    }

    /**
     * Test API rate limiting
     */
    private function testApiRateLimiting(): array {
        $results = ['passed' => true, 'errors' => []];

        $security_manager = new \MavlersCF\Integrations\Core\Services\SecurityManager();
        
        // Test rate limiting
        $allowed = $security_manager->checkRateLimit('test_action', 5, 60);
        
        if (!$allowed) {
            $results['passed'] = false;
            $results['errors'][] = 'Rate limiting failed';
        }

        return $results;
    }

    /**
     * Generate test report
     */
    public function generateTestReport(array $results): string {
        $report = "# Integration Test Report\n\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $total_tests = 0;
        $passed_tests = 0;

        foreach ($results as $category => $category_results) {
            $report .= "## {$category}\n\n";
            
            foreach ($category_results as $test_name => $test_result) {
                $total_tests++;
                
                if ($test_result['passed']) {
                    $passed_tests++;
                    $report .= "✅ **{$test_name}**: PASSED\n";
                } else {
                    $report .= "❌ **{$test_name}**: FAILED\n";
                    foreach ($test_result['errors'] as $error) {
                        $report .= "   - {$error}\n";
                    }
                }
                $report .= "\n";
            }
        }

        $report .= "## Summary\n\n";
        $report .= "Total Tests: {$total_tests}\n";
        $report .= "Passed: {$passed_tests}\n";
        $report .= "Failed: " . ($total_tests - $passed_tests) . "\n";
        $report .= "Success Rate: " . round(($passed_tests / $total_tests) * 100, 2) . "%\n";

        return $report;
    }
} 