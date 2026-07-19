<?php
declare(strict_types=1);

/**
 * Barkod SMS Service
 * 
 * Wrapper class for SMS integration with İletiMerkezi or other SMS providers.
 * This class provides a unified interface for sending SMS notifications.
 * 
 * @package Barkod_Sistemi
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Barkod_SMS_Service {
    
    /**
     * SMS provider type
     * 
     * @var string
     */
    private $provider;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->provider = get_option('barkod_sms_provider', 'iletimerkezi');
    }
    
    /**
     * Check if SMS service is configured and enabled
     * 
     * @return bool
     */
    public function is_enabled(): bool {
        $enabled = get_option('barkod_sms_enabled', '0');
        $provider_available = $this->is_provider_available();
        return $enabled === '1' && $provider_available;
    }
    
    /**
     * Check if the SMS provider plugin is available
     * 
     * @return bool
     */
    private function is_provider_available(): bool {
        if ($this->provider === 'iletimerkezi') {
            if (class_exists('IletiMerkezi_SMS_API')) {
                return true;
            }
            $sms_options = get_option('iletimerkezi_sms_options', array());
            return !empty($sms_options['key']) && !empty($sms_options['hash']) && !empty($sms_options['sender']);
        }
        return apply_filters('barkod_sms_provider_available', false, $this->provider);
    }
    
    /**
     * Send donation SMS to donor
     * 
     * @param array $data Donation data
     * @return bool Success status
     */
    public function send_donation_sms_to_donor(array $data): bool {
        if (!$this->is_enabled()) {
            $this->log_sms(
                $data['donor_phone'] ?? '',
                __('SMS servisi devre dışı', 'barkod-sistemi'),
                false,
                'service_disabled'
            );
            return false;
        }

        $message = $this->get_donor_sms_template($data);
        $phone = $this->normalize_phone($data['donor_phone'] ?? '');

        if (empty($phone)) {
            $this->log_sms($phone, $message, false, 'invalid_phone');
            return false;
        }

        return $this->send_sms($phone, $message, 'donation_donor');
    }
    
    /**
     * Send donation SMS to kumbara owner
     * 
     * @param array $data Donation data
     * @return bool Success status
     */
    public function send_donation_sms_to_owner(array $data): bool {
        if (!$this->is_enabled()) {
            $this->log_sms(
                $data['owner_phone'] ?? '',
                __('SMS servisi devre dışı', 'barkod-sistemi'),
                false,
                'service_disabled'
            );
            return false;
        }

        $message = $this->get_owner_sms_template($data);
        $phone = $this->normalize_phone($data['owner_phone'] ?? '');

        if (empty($phone)) {
            $this->log_sms($phone, $message, false, 'invalid_phone');
            return false;
        }

        return $this->send_sms($phone, $message, 'donation_owner');
    }
    
    /**
     * Send SMS via configured provider
     * 
     * @param string $phone Phone number
     * @param string $message Message content
     * @param string $type SMS type for logging
     * @return bool Success status
     */
    public function send_sms(string $phone, string $message, string $type = 'general'): bool {
        // Allow filtering before sending
        $send = apply_filters('barkod_sms_before_send', true, $phone, $message, $type);
        
        if (!$send) {
            $this->log_sms($phone, $message, false, 'filtered');
            return false;
        }
        
        $success = false;
        $error_message = '';
        
        try {
            // İletiMerkezi integration
            if ($this->provider === 'iletimerkezi') {
                $success = $this->send_via_iletimerkezi($phone, $message);
            } else {
                // Allow custom providers via action
                $result = apply_filters('barkod_sms_send', null, $phone, $message, $this->provider);
                $success = $result === true;
            }
            
            if (!$success) {
                $error_message = __('SMS gönderimi başarısız', 'barkod-sistemi');
            }
            
        } catch (Exception $e) {
            $success = false;
            $error_message = $e->getMessage();
            error_log('Barkod SMS Error: ' . $error_message);
        }
        
        // Log the SMS
        $this->log_sms($phone, $message, $success, $error_message);
        
        // Trigger action after sending
        do_action('barkod_sms_after_send', $success, $phone, $message, $type);
        
        return $success;
    }
    
    /**
     * Send SMS via İletiMerkezi
     * 
     * @param string $phone Phone number
     * @param string $message Message content
     * @return bool Success status
     */
    private function send_via_iletimerkezi(string $phone, string $message): bool {
        if (class_exists('IletiMerkezi_SMS_API')) {
            $api = new IletiMerkezi_SMS_API();
            $normalized_phone = $api->telefon_normalize_tr($phone);

            if (empty($normalized_phone)) {
                error_log('[BARKOD_SMS] Invalid phone number after normalization: ' . $phone);
                return false;
            }

            $result = $api->sms_gonder($normalized_phone, $message, 'barkod_donation');

            if ($result === true) {
                return true;
            } elseif ($result === 'blocked') {
                error_log('[BARKOD_SMS] SMS blocked due to spam limit: ' . $phone);
                return false;
            }

            error_log('[BARKOD_SMS] SMS send failed: ' . $phone);
            return false;
        }

        error_log('[BARKOD_SMS] IletiMerkezi_SMS_API class not found, using direct API');
        return $this->send_via_iletimerkezi_api($phone, $message);
    }
    
    /**
     * Send SMS via İletiMerkezi API directly
     * 
     * @param string $phone Phone number
     * @param string $message Message content
     * @return bool Success status
     */
    private function send_via_iletimerkezi_api(string $phone, string $message): bool {
        // Get İletiMerkezi settings from the SMS plugin
        $sms_options = get_option('iletimerkezi_sms_options', array());
        
        $api_key = $sms_options['key'] ?? '';
        $api_hash = $sms_options['hash'] ?? '';
        $sender = $sms_options['sender'] ?? '';
        $endpoint = $sms_options['endpoint'] ?? 'https://api.iletimerkezi.com/v1/send-sms';
        
        if (empty($api_key) || empty($api_hash) || empty($sender)) {
            error_log('[BARKOD_SMS] İletiMerkezi API credentials not configured');
            return false;
        }
        
        // Build XML request (İletiMerkezi format)
        $xml_body = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<request>'
            . '<authentication>'
            . '<key>' . esc_xml($api_key) . '</key>'
            . '<hash>' . esc_xml($api_hash) . '</hash>'
            . '</authentication>'
            . '<order>'
            . '<sender>' . esc_xml($sender) . '</sender>'
            . '<sendDateTime></sendDateTime>'
            . '<iys>1</iys>'
            . '<iysList>BIREYSEL</iysList>'
            . '<message>'
            . '<text><![CDATA[' . $message . ']]></text>'
            . '<receipents>'
            . '<number>' . esc_xml($phone) . '</number>'
            . '</receipents>'
            . '</message>'
            . '</order>'
            . '</request>';
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/xml; charset=UTF-8',
            ),
            'body' => $xml_body,
            'timeout' => 20,
        );
        
        $response = wp_remote_post($endpoint, $args);
        
        if (is_wp_error($response)) {
            error_log('[BARKOD_SMS] API request failed: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // HTTP 200-299 arası başarılı
        if ($code >= 200 && $code < 300) {
            // XML yanıtını kontrol et
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            
            if ($xml !== false && isset($xml->status->code)) {
                $status_code = (string)$xml->status->code;
                if ($status_code == '200') {
                    return true;
                } else {
                    $status_message = isset($xml->status->message) ? (string)$xml->status->message : 'Unknown error';
                    error_log('[BARKOD_SMS] API error: ' . $status_code . ' - ' . $status_message);
                    return false;
                }
            }
            
            // XML parse edilemedi ama HTTP başarılı
            return true;
        }
        
        error_log('[BARKOD_SMS] HTTP error: ' . $code);
        return false;
    }
    
    /**
     * Get donor SMS template
     * 
     * @param array $data Donation data
     * @return string SMS message
     */
    private function get_donor_sms_template(array $data): string {
        $template = get_option(
            'barkod_sms_donor_template',
            __('Merhaba {donor_name}, {kumbara_name} kumbarasına {tl} TL değerindeki puan bağışınız gerçekleşti. Teşekkürler! - {shop_name}', 'barkod-sistemi')
        );
        
        $shop_name = get_bloginfo('name');
        $date_time = current_time('d.m.Y H:i');
        
        $replacements = array(
            '{donor_name}' => $data['donor_name'] ?? '',
            '{kumbara_name}' => $data['kumbara_name'] ?? '',
            '{tl}' => number_format($data['tl'] ?? 0, 0, ',', '.'),
            '{shop_name}' => $shop_name,
            '{date}' => current_time('d.m.Y'),
            '{time}' => current_time('H:i'),
            '{date_time}' => $date_time
        );
        
        $message = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
        
        return apply_filters('barkod_sms_donor_message', $message, $data);
    }
    
    /**
     * Get owner SMS template
     * 
     * @param array $data Donation data
     * @return string SMS message
     */
    private function get_owner_sms_template(array $data): string {
        $template = get_option(
            'barkod_sms_owner_template',
            __('Merhaba {owner_name}, {donor_name} tarafından {kumbara_name} kumbaranıza {tl} TL değerinde puan bağışı yapıldı.Kumbaranızda toplam {total_donate} TL değerinde para puan bulunmakta! - {shop_name}', 'barkod-sistemi')
        );
        
        $shop_name = get_bloginfo('name');
        $date_time = current_time('d.m.Y H:i');
        
        $replacements = array(
            '{owner_name}' => $data['owner_name'] ?? '',
            '{donor_name}' => $data['donor_name'] ?? '',
            '{kumbara_name}' => $data['kumbara_name'] ?? '',
            '{tl}' => number_format($data['tl'] ?? 0, 0, ',', '.'),
            '{shop_name}' => $shop_name,
            '{date}' => current_time('d.m.Y'),
            '{time}' => current_time('H:i'),
            '{date_time}' => $date_time,
			'{total_donate}' => number_format((($data['total_donate'] / 100) * 2) + ($data['tl'] ?? 0), 0, ',', '.')
        );
        
        $message = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
        
        return apply_filters('barkod_sms_owner_message', $message, $data);
    }
    
    /**
     * Normalize phone number to international format
     * 
     * @param string $phone Phone number
     * @return string Normalized phone number
     */
    private function normalize_phone(string $phone): string {
        // Use İletiMerkezi's normalization if available
        if (class_exists('IletiMerkezi_SMS_API')) {
            $api = new IletiMerkezi_SMS_API();
            $normalized = $api->telefon_normalize_tr($phone);
            
            if (!empty($normalized)) {
                return $normalized;
            }
        }
        
        // Fallback: Manual normalization
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with country code (Turkey: 90)
        if (substr($phone, 0, 1) === '0') {
            $phone = '90' . substr($phone, 1);
        }
        
        // If doesn't start with country code, add it
        if (strlen($phone) === 10) {
            $phone = '90' . $phone;
        }
        
        // Validate length (should be 12 digits for Turkey: 90 + 10 digits)
        if (strlen($phone) !== 12) {
            return '';
        }
        
        return $phone;
    }
    
    /**
     * Log SMS to database
     * 
     * @param string $phone Phone number
     * @param string $message Message content
     * @param bool $success Success status
     * @param string $error_message Error message if failed
     * @return void
     */
    private function log_sms(string $phone, string $message, bool $success, string $error_message = ''): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'phone' => $phone,
                'message' => $message,
                'status' => $success ? 'success' : 'failed',
                'error_message' => $error_message,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        // Log to WordPress error log if failed
        if (!$success) {
            error_log(sprintf(
                'Barkod SMS Failed: Phone=%s, Error=%s',
                $phone,
                $error_message
            ));
        }
    }
    
    /**
     * Get SMS log entries
     * 
     * @param array $args Query arguments
     * @return array Log entries
     */
    public function get_sms_log(array $args = array()): array {
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'status' => '', // 'success' or 'failed'
            'phone' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'sms_log';
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['phone'])) {
            $where[] = 'phone LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($args['phone']) . '%';
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
}
