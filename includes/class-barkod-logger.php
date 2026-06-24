<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logger class for Barkod Sistemi plugin
 * Requirements: 5.5, 6.1
 * 
 * Provides structured logging with different severity levels
 * and categories for better debugging and monitoring.
 */
class Barkod_Logger {
    
    // Log levels (PSR-3 compatible)
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    // Log categories
    const CATEGORY_SECURITY = 'SECURITY';
    const CATEGORY_SALE = 'SALE';
    const CATEGORY_BARCODE = 'BARCODE';
    const CATEGORY_DONATION = 'DONATION';
    const CATEGORY_SMS = 'SMS';
    const CATEGORY_ERROR = 'ERROR';
    const CATEGORY_KUMBARA = 'KUMBARA';
    const CATEGORY_VALIDATION = 'VALIDATION';
    
    /**
     * Log a message with specified level and category
     * 
     * @param string $level Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $category Log category (SECURITY, SALE, etc.)
     * @return void
     */
    public function log(string $level, string $message, array $context = [], string $category = ''): void {
        // Get current user info
        $user_id = get_current_user_id();
        $user = $user_id > 0 ? get_userdata($user_id) : null;
        $username = $user ? $user->user_login : 'guest';
        
        // Get IP address
        $ip_address = $this->get_client_ip();
        
        // Build log entry
        $log_parts = [
            '[BARKOD]',
            "[{$level}]"
        ];
        
        if (!empty($category)) {
            $log_parts[] = "[{$category}]";
        }
        
        $log_parts[] = $message;
        $log_parts[] = "| User: {$username} (ID: {$user_id})";
        $log_parts[] = "| IP: {$ip_address}";
        
        // Add context if provided
        if (!empty($context)) {
            $context_str = json_encode($context, JSON_UNESCAPED_UNICODE);
            $log_parts[] = "| Context: {$context_str}";
        }
        
        $log_entry = implode(' ', $log_parts);
        
        // Write to error log
        error_log($log_entry);
        
        // For critical errors, also trigger WordPress admin notice
        if ($level === self::LEVEL_CRITICAL) {
            $this->trigger_admin_notice($message, 'error');
        }
        
        // Store in database for critical operations (optional)
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
            $this->store_in_database($level, $message, $context, $category);
        }
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $category Log category
     * @return void
     */
    public function debug(string $message, array $context = [], string $category = ''): void {
        $this->log(self::LEVEL_DEBUG, $message, $context, $category);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $category Log category
     * @return void
     */
    public function info(string $message, array $context = [], string $category = ''): void {
        $this->log(self::LEVEL_INFO, $message, $context, $category);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $category Log category
     * @return void
     */
    public function warning(string $message, array $context = [], string $category = ''): void {
        $this->log(self::LEVEL_WARNING, $message, $context, $category);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $category Log category
     * @return void
     */
    public function error(string $message, array $context = [], string $category = ''): void {
        $this->log(self::LEVEL_ERROR, $message, $context, $category);
    }
    
    /**
     * Log critical message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $category Log category
     * @return void
     */
    public function critical(string $message, array $context = [], string $category = ''): void {
        $this->log(self::LEVEL_CRITICAL, $message, $context, $category);
    }
    
    /**
     * Log exception
     * 
     * @param Throwable $exception Exception to log
     * @param string $category Log category
     * @return void
     */
    public function logException(Throwable $exception, string $category = self::CATEGORY_ERROR): void {
        $context = [
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        if ($exception instanceof Barkod_Exception) {
            $context['error_code'] = $exception->getErrorCode();
        }
        
        $this->error($exception->getMessage(), $context, $category);
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Trigger WordPress admin notice
     * 
     * @param string $message Notice message
     * @param string $type Notice type (error, warning, success, info)
     * @return void
     */
    private function trigger_admin_notice(string $message, string $type = 'error'): void {
        add_action('admin_notices', function() use ($message, $type) {
            $class = 'notice notice-' . $type;
            printf('<div class="%1$s"><p><strong>Barkod Sistemi:</strong> %2$s</p></div>', esc_attr($class), esc_html($message));
        });
    }
    
    /**
     * Store log entry in database
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @param string $category Log category
     * @return void
     */
    private function store_in_database(string $level, string $message, array $context, string $category): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'barkod_error_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // Table doesn't exist, skip database logging
            return;
        }
        
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        $wpdb->insert(
            $table_name,
            [
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
                'user_id' => $user_id,
                'ip_address' => $ip_address,
                'created_at' => current_time('mysql')
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s'
            ]
        );
    }
    
    /**
     * Get recent log entries from database
     * 
     * @param int $limit Number of entries to retrieve
     * @param string $level Filter by log level (optional)
     * @param string $category Filter by category (optional)
     * @return array Log entries
     */
    public function get_recent_logs(int $limit = 100, string $level = '', string $category = ''): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'barkod_error_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return [];
        }
        
        $where_clauses = ['1=1'];
        $where_values = [];
        
        if (!empty($level)) {
            $where_clauses[] = 'level = %s';
            $where_values[] = $level;
        }
        
        if (!empty($category)) {
            $where_clauses[] = 'category = %s';
            $where_values[] = $category;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d";
        $where_values[] = $limit;
        
        $results = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Clear old log entries from database
     * 
     * @param int $days Number of days to keep
     * @return int Number of deleted entries
     */
    public function clear_old_logs(int $days = 30): int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'barkod_error_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return 0;
        }
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $date_threshold
            )
        );
        
        return $deleted ?: 0;
    }
}
