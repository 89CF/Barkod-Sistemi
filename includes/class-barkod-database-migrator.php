<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Barkod_Database_Migrator {
    
    private const DB_VERSION = '2.0.0';
    private const DB_VERSION_OPTION = 'barkod_sistemi_db_version';
    
    /**
     * Run database migrations
     */
    public function migrate(): void {
        $current_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->migrate_v2();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }
    
    /**
     * Create tables for version 2.0.0
     */
    private function migrate_v2(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create wp_kumbara table
        $table_kumbara = $wpdb->prefix . 'kumbara';
        $sql_kumbara = "CREATE TABLE {$table_kumbara} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            total_points BIGINT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            INDEX idx_owner (owner_user_id),
            INDEX idx_active (is_active)
        ) {$charset_collate};";
        
        dbDelta($sql_kumbara);
        
        // Create wp_puan_bagis_log table
        $table_puan_bagis = $wpdb->prefix . 'puan_bagis_log';
        $sql_puan_bagis = "CREATE TABLE {$table_puan_bagis} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            donor_user_id BIGINT UNSIGNED NOT NULL,
            kumbara_id BIGINT UNSIGNED NOT NULL,
            points INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_donor (donor_user_id),
            INDEX idx_kumbara (kumbara_id),
            INDEX idx_date (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql_puan_bagis);
        
        // Create wp_barkod_basma_log table
        $table_barkod_basma = $wpdb->prefix . 'barkod_basma_log';
        $sql_barkod_basma = "CREATE TABLE {$table_barkod_basma} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity INT NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_product (product_id),
            INDEX idx_user (user_id),
            INDEX idx_date (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql_barkod_basma);
        
        // Create wp_sms_log table
        $table_sms_log = $wpdb->prefix . 'sms_log';
        $sql_sms_log = "CREATE TABLE {$table_sms_log} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('success', 'failed') NOT NULL,
            error_message TEXT,
            created_at DATETIME NOT NULL,
            INDEX idx_phone (phone),
            INDEX idx_status (status),
            INDEX idx_date (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql_sms_log);
        
        // Create wp_barkod_error_log table
        // Requirements: 5.5, 6.1
        $table_error_log = $wpdb->prefix . 'barkod_error_log';
        $sql_error_log = "CREATE TABLE {$table_error_log} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(20) NOT NULL,
            category VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            user_id BIGINT UNSIGNED DEFAULT 0,
            ip_address VARCHAR(45) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_level (level),
            INDEX idx_category (category),
            INDEX idx_user (user_id),
            INDEX idx_date (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql_error_log);
        
        // Log migration completion
        error_log('[BARKOD_MIGRATION] Database migrated to version ' . self::DB_VERSION);
    }
    
    /**
     * Drop all plugin tables (for uninstall)
     */
    public static function drop_tables(): void {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'kumbara',
            $wpdb->prefix . 'puan_bagis_log',
            $wpdb->prefix . 'barkod_basma_log',
            $wpdb->prefix . 'sms_log',
            $wpdb->prefix . 'barkod_error_log'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        delete_option(self::DB_VERSION_OPTION);
    }
}
