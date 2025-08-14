<?php
if (!function_exists('c2f_is_valid_identifier')) {
    function c2f_is_valid_identifier(string $s): bool {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $s);
    }
}
if (!function_exists('c2f_is_valid_prefixed_table')) {
    function c2f_is_valid_prefixed_table(string $table): bool {
        global $wpdb;
        if (!isset($wpdb->prefix) || strpos($table, $wpdb->prefix) !== 0) {
            return false;
        }
        $bare = substr($table, strlen($wpdb->prefix));
        return c2f_is_valid_identifier($bare);
    }
}


