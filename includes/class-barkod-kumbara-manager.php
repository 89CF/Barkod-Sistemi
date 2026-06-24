<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kumbara Management Wrapper Class
 * 
 * Wraps the Woof Kumbara plugin functionality to provide a consistent API
 * for the Barkod Sistemi plugin. Integrates with Points and Rewards for WooCommerce.
 * 
 * This class does NOT create its own kumbara system - it wraps the existing
 * Woof Kumbara plugin (wp_kumbara_sahipleri table).
 */
class Barkod_Kumbara_Manager {
    
    /**
     * Create a new kumbara (NOT USED - Woof Kumbara handles creation)
     * 
     * This method is kept for API compatibility but should not be used.
     * Kumbaras are created through the Woof Kumbara plugin interface.
     * 
     * @param array $data Kumbara data
     * @return int Always returns 0 (not implemented)
     */
    public function create_kumbara(array $data): int {
        // Kumbaras are created through Woof Kumbara plugin, not here
        error_log('[BARKOD_KUMBARA] create_kumbara() called but not implemented - use Woof Kumbara plugin');
        return 0;
    }
    
    /**
     * Get kumbara by ID from Woof Kumbara plugin
     * 
     * @param int $id Kumbara ID (from wp_kumbara_sahipleri table)
     * @return array|null Kumbara data or null if not found
     */
    public function get_kumbara(int $id): ?array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'kumbara_sahipleri';
        
        $kumbara = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        if (!$kumbara) {
            return null;
        }
        
        // Convert to consistent format
        return [
            'id' => (int) $kumbara['id'],
            'name' => $kumbara['kumbara_adi'],
            'description' => '', // Woof Kumbara doesn't have description in table
            'owner_user_id' => (int) $kumbara['user_id'],
            'total_points' => (float) $kumbara['toplam_bagis'],
            'hedef_puan' => (int) ($kumbara['hedef_puan'] ?? 25000),
            'is_active' => true, // All records in kumbara_sahipleri are active
            'created_at' => $kumbara['aktif_olma_tarihi'] ?? '',
            'post_id' => isset($kumbara['post_id']) ? (int) $kumbara['post_id'] : null,
            // Additional Woof Kumbara fields
            'ad' => $kumbara['ad'] ?? '',
            'soyad' => $kumbara['soyad'] ?? '',
            'telefon' => $kumbara['telefon'] ?? '',
            'email' => $kumbara['email'] ?? '',
            'instagram' => $kumbara['instagram'] ?? '',
            'il' => $kumbara['il'] ?? '',
            'ilce' => $kumbara['ilce'] ?? '',
        ];
    }
    
    /**
     * Get kumbara by ID (alias for compatibility)
     * 
     * @param int $id Kumbara ID
     * @return array|null Kumbara data or null if not found
     */
    public function get_kumbara_by_id(int $id): ?array {
        return $this->get_kumbara($id);
    }
    
    /**
     * List all active kumbaras from Woof Kumbara plugin
     * 
     * @param array $args Optional arguments (search, orderby, order, limit, offset)
     * @return array Array of kumbara records
     */
    public function list_active_kumbaras(array $args = []): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'kumbara_sahipleri';
        
        // Default arguments
        $defaults = [
            'search' => '',
            'orderby' => 'kumbara_adi',
            'order' => 'ASC',
            'limit' => 100,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query - all records in kumbara_sahipleri are active
        $where = "WHERE 1=1";
        
        // Add search condition
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= $wpdb->prepare(" AND (kumbara_adi LIKE %s OR ad LIKE %s OR soyad LIKE %s)", $search, $search, $search);
        }
        
        // Validate orderby
        $allowed_orderby = ['kumbara_adi', 'toplam_bagis', 'aktif_olma_tarihi', 'ad', 'soyad'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'kumbara_adi';
        
        // Validate order
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        // Build final query
        $sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}";
        
        // Add limit and offset
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        if (!$results) {
            return [];
        }
        
        // Convert to consistent format
        $kumbaras = [];
        foreach ($results as $kumbara) {
            $kumbaras[] = [
                'id' => (int) $kumbara['id'],
                'name' => $kumbara['kumbara_adi'],
                'description' => '', // Woof Kumbara doesn't have description
                'owner_user_id' => (int) $kumbara['user_id'],
                'total_points' => (float) $kumbara['toplam_bagis'],
                'hedef_puan' => (int) ($kumbara['hedef_puan'] ?? 25000),
                'is_active' => true,
                'created_at' => $kumbara['aktif_olma_tarihi'] ?? '',
                'post_id' => isset($kumbara['post_id']) ? (int) $kumbara['post_id'] : null,
                // Additional fields for display
                'owner_name' => trim(($kumbara['ad'] ?? '') . ' ' . ($kumbara['soyad'] ?? '')),
                'telefon' => $kumbara['telefon'] ?? '',
                'email' => $kumbara['email'] ?? '',
            ];
        }
        
        return $kumbaras;
    }
    
    /**
     * Get all active kumbaras (alias for compatibility)
     * 
     * @return array Array of active kumbara records
     */
    public function get_active_kumbaras(): array {
        return $this->list_active_kumbaras();
    }
    
    /**
     * Update kumbara points (integrates with Woof Kumbara)
     * 
     * @param int $id Kumbara ID
     * @param int $points Points to add (can be negative to subtract)
     * @return bool True on success, false on failure
     */
    public function update_kumbara_points(int $id, int $points): bool {
        global $wpdb;
        
        // Validate kumbara exists
        $kumbara = $this->get_kumbara($id);
        if (!$kumbara) {
            error_log('[BARKOD_KUMBARA] Cannot update points: Kumbara not found (ID: ' . $id . ')');
            return false;
        }
        
        // Calculate new total
        $new_total = $kumbara['total_points'] + $points;
        
        // Prevent negative points
        if ($new_total < 0) {
            error_log('[BARKOD_KUMBARA] Cannot update points: Result would be negative (ID: ' . $id . ', Current: ' . $kumbara['total_points'] . ', Change: ' . $points . ')');
            return false;
        }
        
        $table = $wpdb->prefix . 'kumbara_sahipleri';
        
        $result = $wpdb->update(
            $table,
            ['toplam_bagis' => $new_total],
            ['id' => $id],
            ['%f'],
            ['%d']
        );
        
        if ($result === false) {
            error_log('[BARKOD_KUMBARA] Failed to update points: ' . $wpdb->last_error);
            return false;
        }
        
        // Clear cache if Woof Kumbara uses caching
        wp_cache_delete("user_has_kumbara_{$kumbara['owner_user_id']}", 'woof_kumbara');
        
        return true;
    }
    
    /**
     * Get kumbara donations history from Woof Kumbara log
     * 
     * @param int $id Kumbara ID
     * @param int $limit Number of records to retrieve
     * @return array Array of donation records
     */
    public function get_kumbara_donations(int $id, int $limit = 10): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'kumbara_bagis_log';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE kumbara_id = %d ORDER BY tarih DESC LIMIT %d",
                $id,
                $limit
            ),
            ARRAY_A
        );
        
        if (!$results) {
            return [];
        }
        
        // Convert to consistent format
        $donations = [];
        foreach ($results as $donation) {
            $donor = get_userdata((int) $donation['user_id']);
            
            $donations[] = [
                'id' => (int) $donation['id'],
                'donor_user_id' => (int) $donation['user_id'],
                'kumbara_id' => (int) $donation['kumbara_id'],
                'points' => (float) $donation['bagis_tl'], // Using bagis_tl as points equivalent
                'bagis_tipi' => $donation['bagis_tipi'],
                'bagis_puani' => isset($donation['bagis_puani']) ? (float) $donation['bagis_puani'] : null,
                'urun_adi' => $donation['urun_adi'] ?? null,
                'urun_tutari' => isset($donation['urun_tutari']) ? (float) $donation['urun_tutari'] : null,
                'aciklama' => $donation['aciklama'] ?? '',
                'durum' => $donation['durum'] ?? 'teslim edildi',
                'created_at' => $donation['tarih'],
                'donor_name' => $donor ? $donor->display_name : 'Unknown',
            ];
        }
        
        return $donations;
    }
    
    /**
     * Update kumbara details (NOT FULLY SUPPORTED - Woof Kumbara manages this)
     * 
     * Limited support - only toplam_bagis can be updated directly.
     * Other fields should be updated through Woof Kumbara plugin.
     * 
     * @param int $id Kumbara ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public function update_kumbara(int $id, array $data): bool {
        global $wpdb;
        
        // Validate kumbara exists
        if (!$this->get_kumbara($id)) {
            return false;
        }
        
        $table = $wpdb->prefix . 'kumbara_sahipleri';
        $update_data = [];
        $format = [];
        
        // Only allow updating total points through this method
        if (isset($data['total_points'])) {
            $update_data['toplam_bagis'] = (float) $data['total_points'];
            $format[] = '%f';
        }
        
        // Nothing to update
        if (empty($update_data)) {
            return true;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a kumbara (NOT SUPPORTED - Woof Kumbara manages this)
     * 
     * Kumbaras should be managed through the Woof Kumbara plugin interface.
     * 
     * @param int $id Kumbara ID
     * @return bool Always returns false
     */
    public function delete_kumbara(int $id): bool {
        error_log('[BARKOD_KUMBARA] delete_kumbara() not supported - use Woof Kumbara plugin');
        return false;
    }
    
    /**
     * Get kumbara owner information
     * 
     * @param int $id Kumbara ID
     * @return array|null Owner data or null if not found
     */
    public function get_kumbara_owner(int $id): ?array {
        $kumbara = $this->get_kumbara($id);
        
        if (!$kumbara) {
            return null;
        }
        
        $owner = get_userdata($kumbara['owner_user_id']);
        
        if (!$owner) {
            return null;
        }
        
        return [
            'id' => $owner->ID,
            'display_name' => $owner->display_name,
            'user_email' => $owner->user_email,
            'user_login' => $owner->user_login
        ];
    }
    
    /**
     * Get total number of active kumbaras
     * 
     * @return int Total count
     */
    public function get_active_kumbaras_count(): int {
        global $wpdb;
        
        $table = $wpdb->prefix . 'kumbara_sahipleri';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        return (int) $count;
    }
    
    /**
     * Get kumbara by user ID (owner)
     * 
     * @param int $user_id User ID
     * @return array|null Kumbara data or null if not found
     */
    public function get_kumbara_by_user_id(int $user_id): ?array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'kumbara_sahipleri';
        
        $kumbara = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $user_id),
            ARRAY_A
        );
        
        if (!$kumbara) {
            return null;
        }
        
        // Convert to consistent format (reuse get_kumbara logic)
        return $this->get_kumbara((int) $kumbara['id']);
    }
    
    /**
     * Validate if user has sufficient points for donation
     * 
     * @param int $user_id User ID
     * @param int $points Points to donate
     * @return bool True if user has sufficient points
     */
    public function validate_user_points(int $user_id, int $points): bool {
        $user_points = (int) get_user_meta($user_id, 'wps_wpr_points', true);
        return $user_points >= $points && $points > 0;
    }
    
    /**
     * Get user's current points balance
     * 
     * @param int $user_id User ID
     * @return int Current points balance
     */
    public function get_user_points(int $user_id): int {
        return (int) get_user_meta($user_id, 'wps_wpr_points', true);
    }
}
