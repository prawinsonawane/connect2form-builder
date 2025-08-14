<?php

namespace MavlersCF\Integrations\Core\Services;

/**
 * Code Quality Manager
 * 
 * Helps maintain code quality and standards
 */
class CodeQualityManager {

    /**
     * Validate method naming conventions
     */
    public static function validateMethodNaming(string $method_name): bool {
        // Should be camelCase
        return preg_match('/^[a-z][a-zA-Z0-9]*$/', $method_name) === 1;
    }

    /**
     * Validate class naming conventions
     */
    public static function validateClassName(string $class_name): bool {
        // Should be PascalCase
        return preg_match('/^[A-Z][a-zA-Z0-9]*$/', $class_name) === 1;
    }

    /**
     * Validate variable naming conventions
     */
    public static function validateVariableNaming(string $variable_name): bool {
        // Should be snake_case for variables
        return preg_match('/^[a-z][a-z0-9_]*$/', $variable_name) === 1;
    }

    /**
     * Check for common code smells
     */
    public static function detectCodeSmells(string $file_path): array {
        $smells = [];
        $content = file_get_contents($file_path);
        
        if (!$content) {
            return $smells;
        }

        // Check for debug code in production
        if (strpos($content, 'error_log(') !== false) {
            $smells[] = 'Debug logging found - should be removed in production';
        }

        // Check for hardcoded values
        if (preg_match('/\'[a-f0-9]{32}\'/', $content)) {
            $smells[] = 'Hardcoded API keys found';
        }

        // Check for SQL injection risks
        if (preg_match('/\$wpdb->query\s*\(\s*\$/', $content)) {
            $smells[] = 'Potential SQL injection risk - use prepared statements';
        }

        // Check for missing nonce verification
        if (strpos($content, 'wp_ajax_') !== false && strpos($content, 'wp_verify_nonce') === false) {
            $smells[] = 'AJAX handler missing nonce verification';
        }

        // Check for XSS vulnerabilities
        if (preg_match('/echo\s+\$[^;]+;/', $content) && strpos($content, 'esc_html') === false) {
            $smells[] = 'Potential XSS vulnerability - use esc_html()';
        }

        return $smells;
    }

    /**
     * Validate file structure
     */
    public static function validateFileStructure(string $file_path): array {
        $issues = [];
        $content = file_get_contents($file_path);
        
        if (!$content) {
            return $issues;
        }

        // Check for proper namespace
        if (strpos($content, 'namespace') === false) {
            $issues[] = 'Missing namespace declaration';
        }

        // Check for proper class declaration
        if (strpos($content, 'class ') === false) {
            $issues[] = 'Missing class declaration';
        }

        // Check for proper file header
        if (strpos($content, '/**') === false) {
            $issues[] = 'Missing file documentation header';
        }

        // Check for proper exit statement
        if (strpos($content, 'if (!defined(\'ABSPATH\'))') === false) {
            $issues[] = 'Missing ABSPATH check';
        }

        return $issues;
    }

    /**
     * Generate code quality report
     */
    public static function generateQualityReport(string $directory): array {
        $report = [
            'total_files' => 0,
            'issues_found' => 0,
            'files_with_issues' => 0,
            'code_smells' => [],
            'structure_issues' => []
        ];

        $files = self::getPhpFiles($directory);
        
        foreach ($files as $file) {
            $report['total_files']++;
            
            $smells = self::detectCodeSmells($file);
            $structure_issues = self::validateFileStructure($file);
            
            if (!empty($smells) || !empty($structure_issues)) {
                $report['files_with_issues']++;
                $report['issues_found'] += count($smells) + count($structure_issues);
                
                $report['code_smells'][$file] = $smells;
                $report['structure_issues'][$file] = $structure_issues;
            }
        }

        return $report;
    }

    /**
     * Get all PHP files in directory
     */
    private static function getPhpFiles(string $directory): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Auto-fix common issues
     */
    public static function autoFixIssues(string $file_path): array {
        $fixed_issues = [];
        $content = file_get_contents($file_path);
        
        if (!$content) {
            return $fixed_issues;
        }

        // Fix missing ABSPATH check
        if (strpos($content, 'if (!defined(\'ABSPATH\'))') === false) {
            $content = "<?php\nif (!defined('ABSPATH')) {\n    exit;\n}\n\n" . $content;
            $fixed_issues[] = 'Added ABSPATH check';
        }

        // Remove debug logging
        $content = preg_replace('/error_log\s*\([^)]+\);/', '', $content);
        if (preg_match('/error_log\s*\([^)]+\);/', $content)) {
            $fixed_issues[] = 'Removed debug logging';
        }

        // Add proper escaping
        $content = preg_replace('/echo\s+(\$[^;]+);/', 'echo esc_html($1);', $content);
        if (preg_match('/echo\s+(\$[^;]+);/', $content)) {
            $fixed_issues[] = 'Added proper escaping';
        }

        // Write back to file
        if (!empty($fixed_issues)) {
            file_put_contents($file_path, $content);
        }

        return $fixed_issues;
    }

    /**
     * Validate integration class structure
     */
    public static function validateIntegrationClass(string $class_name): array {
        $issues = [];
        
        if (!class_exists($class_name)) {
            $issues[] = 'Class does not exist';
            return $issues;
        }

        $reflection = new \ReflectionClass($class_name);

        // Check required methods
        $required_methods = [
            'getId',
            'getName', 
            'getDescription',
            'getVersion',
            'isConfigured',
            'testConnection',
            'processSubmission'
        ];

        foreach ($required_methods as $method) {
            if (!$reflection->hasMethod($method)) {
                $issues[] = "Missing required method: {$method}";
            }
        }

        // Check interface implementation
        if (!$reflection->implementsInterface('MavlersCF\Integrations\Core\Interfaces\IntegrationInterface')) {
            $issues[] = 'Class does not implement IntegrationInterface';
        }

        return $issues;
    }

    /**
     * Generate documentation for a class
     */
    public static function generateClassDocumentation(string $class_name): string {
        if (!class_exists($class_name)) {
            return 'Class not found';
        }

        $reflection = new \ReflectionClass($class_name);
        $doc = "/**\n";
        $doc .= " * {$reflection->getName()}\n";
        $doc .= " *\n";

        // Add class description
        $class_doc = $reflection->getDocComment();
        if ($class_doc) {
            $lines = explode("\n", $class_doc);
            foreach ($lines as $line) {
                if (strpos($line, ' * ') === 0) {
                    $doc .= $line . "\n";
                }
            }
        }

        $doc .= " *\n";
        $doc .= " * @package MavlersCF\\Integrations\n";
        $doc .= " * @since " . MAVLERS_CF_INTEGRATIONS_VERSION . "\n";
        $doc .= " */\n";

        return $doc;
    }

    /**
     * Check for deprecated code usage
     */
    public static function checkDeprecatedUsage(string $file_path): array {
        $deprecated = [];
        $content = file_get_contents($file_path);
        
        if (!$content) {
            return $deprecated;
        }

        $deprecated_patterns = [
            'mysql_' => 'Use wpdb instead of mysql_ functions',
            'ereg' => 'Use preg_match instead of ereg functions',
            'split' => 'Use explode or preg_split instead of split',
            'create_function' => 'Use anonymous functions instead of create_function'
        ];

        foreach ($deprecated_patterns as $pattern => $message) {
            if (preg_match('/\b' . preg_quote($pattern) . '/', $content)) {
                $deprecated[] = $message;
            }
        }

        return $deprecated;
    }
} 