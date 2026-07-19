<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

class Barkod_Telegram_Bot {
    
    private const API_URL = 'https://api.telegram.org/bot';
    private const STOCK_THRESHOLD = 10;
    
    private string $bot_token;
    private string $chat_id;
    
    public function __construct() {
        $this->bot_token = get_option('barkod_telegram_bot_token', '');
        $this->chat_id = get_option('barkod_telegram_chat_id', '');
    }
    
    public function is_configured(): bool {
        return !empty($this->bot_token) && !empty($this->chat_id);
    }
    
    // 1. Real-Time Sale Notifications
    public function send_sale_notification(array $data): void {
        if (!$this->is_configured()) return;
        
        $message = __("📦 <b>Yeni Satış!</b>\n\n", 'barkod-sistemi');
        $message .= __("🔢 Sipariş: #", 'barkod-sistemi') . (int)$data['order_id'] . "\n";
        $message .= __("💰 Tutar: ", 'barkod-sistemi') . number_format((float)$data['total'], 2) . " TL\n";

        if (!empty($data['customer'])) {
            $message .= __("👤 Müşteri: ", 'barkod-sistemi') . esc_html($data['customer']) . "\n";
        }

        if ($data['points_used'] > 0) {
            $message .= __("⭐ Kullanılan Puan: ", 'barkod-sistemi') . (int)$data['points_used'] . "\n";
        }

        if ($data['points_earned'] > 0) {
            $message .= __("🎁 Kazanılan Puan: ", 'barkod-sistemi') . (int)$data['points_earned'] . "\n";
        }

        // Requirement 1.1, 2.1: Satış modunu bildir
        if (!empty($data['sale_mode'])) {
            $mode_icon = ($data['sale_mode'] === 'Hızlı Satış Modu') ? '⚡' : '🔧';
            $message .= "{$mode_icon} " . __("Mod: ", 'barkod-sistemi') . esc_html($data['sale_mode']) . "\n";
        }

        $message .= "\n⏰ " . esc_html(current_time('d.m.Y H:i'));
        
        $this->send_message($message);
        $this->log_bot_action('SALE_NOTIFICATION', "Order: {$data['order_id']}");
    }
    
    // 2. Stock Alerts
    public function check_and_send_stock_alert(object $product): void {
        if (!$this->is_configured()) return;
        
        if (!$product->managing_stock()) return;
        
        $stock = $product->get_stock_quantity();
        $threshold = (int) get_option('barkod_telegram_stock_threshold', self::STOCK_THRESHOLD);
        
        if ($stock <= $threshold) {
            $message = __("⚠️ <b>Düşük Stok Uyarısı!</b>\n\n", 'barkod-sistemi');
            $message .= __("📦 Ürün: ", 'barkod-sistemi') . esc_html($product->get_name()) . "\n";
            $message .= __("📊 Kalan: ", 'barkod-sistemi') . (int)$stock . " " . __("adet", 'barkod-sistemi') . "\n";
            $message .= __("🔢 Barkod: ", 'barkod-sistemi') . esc_html($product->get_sku()) . "\n";
            $message .= "\n⏰ " . esc_html(current_time('d.m.Y H:i'));
            
            $this->send_message($message);
            $this->log_bot_action('STOCK_ALERT', "Product: {$product->get_name()}, Stock: {$stock}");
        }
    }
    
    // 3. Daily Reports
    public function send_daily_report(): void {
        if (!$this->is_configured()) return;
        
        $today = current_time('Y-m-d');
        
        // Get today's orders
        $orders = wc_get_orders([
            'date_created' => $today,
            'status' => 'completed',
            'limit' => -1
        ]);
        
        $total_sales = 0;
        $total_points_used = 0;
        $total_points_earned = 0;
        $customers = [];
        
        foreach ($orders as $order) {
            $total_sales += $order->get_total();
            
            // Extract points from order notes
            foreach ($order->get_customer_order_notes() as $note) {
                if (strpos($note->comment_content, 'Kullanılan Puan:') !== false) {
                    preg_match('/(\d+)/', $note->comment_content, $matches);
                    if (isset($matches[1])) {
                        $total_points_used += (int) $matches[1];
                    }
                }
                if (strpos($note->comment_content, 'Kazanılan Puan:') !== false) {
                    preg_match('/(\d+)/', $note->comment_content, $matches);
                    if (isset($matches[1])) {
                        $total_points_earned += (int) $matches[1];
                    }
                }
            }
            
            if ($order->get_customer_id()) {
                $customers[$order->get_customer_id()] = true;
            }
        }
        
        $message = __("📊 <b>Günlük Satış Raporu</b>\n", 'barkod-sistemi');
        $message .= __("📅 Tarih: ", 'barkod-sistemi') . current_time('d.m.Y') . "\n";
        $message .= "━━━━━━━━━━━━━━━━\n\n";
        $message .= __("💰 Toplam Satış: ", 'barkod-sistemi') . number_format((float)$total_sales, 2) . " TL\n";
        $message .= __("🛒 Sipariş Sayısı: ", 'barkod-sistemi') . count($orders) . "\n";
        $message .= __("👥 Müşteri Sayısı: ", 'barkod-sistemi') . count($customers) . "\n";
        $message .= __("⭐ Kullanılan Puan: ", 'barkod-sistemi') . number_format((int)$total_points_used) . "\n";
        $message .= __("🎁 Kazanılan Puan: ", 'barkod-sistemi') . number_format((int)$total_points_earned) . "\n";
        
        $this->send_message($message);
        $this->log_bot_action('DAILY_REPORT', "Sales: {$total_sales} TL");
    }
    
    // 4. Security Alerts
    public function send_security_alert(string $event, string $details): void {
        if (!$this->is_configured()) return;
        
        $message = __("🚨 <b>Güvenlik Uyarısı!</b>\n\n", 'barkod-sistemi');
        $message .= sprintf(__("⚠️ Olay: %s\n", 'barkod-sistemi'), $event);
        $message .= sprintf(__("📝 Detay: %s\n", 'barkod-sistemi'), $details);
        $message .= __("⏰ Zaman: ", 'barkod-sistemi') . current_time('d.m.Y H:i:s');
        
        $this->send_message($message);
        $this->log_bot_action('SECURITY_ALERT', $event);
    }
    
    // 5. Handle Bot Commands
    public function handle_command(string $command, array $args): string {
        if (!$this->is_configured()) {
            return __("❌ Bot yapılandırılmamış.", 'barkod-sistemi');
        }
        
        switch ($command) {
            case '/musteri':
                return $this->handle_customer_query($args);
            
            case '/stok':
                return $this->handle_stock_query($args);
            
            case '/satis':
                return $this->handle_sales_query();
            
            case '/rapor':
                return $this->handle_report_query();
            
            case '/indirim':
                return $this->handle_discount_command($args);
            
            case '/stokekle':
                return $this->handle_add_stock_command($args);
            
            case '/puanver':
                return $this->handle_give_points_command($args);
            
            case '/help':
            case '/start':
                return $this->get_help_message();
            
            default:
                return __("❌ Bilinmeyen komut. /help yazarak komutları görebilirsiniz.", 'barkod-sistemi');
        }
    }
    
    // Customer Query
    private function handle_customer_query(array $args): string {
        if (empty($args[0])) {
            return __("❌ Kullanım: /musteri [telefon]", 'barkod-sistemi');
        }
        
        $phone = sanitize_text_field($args[0]);
        
        $users = get_users([
            'meta_query' => [
                [
                    'key' => 'billing_phone',
                    'value' => $phone,
                    'compare' => '='
                ]
            ],
            'number' => 1
        ]);
        
        if (empty($users)) {
            return __("❌ Müşteri bulunamadı.", 'barkod-sistemi');
        }

        $user = $users[0];
        $points = (int) get_user_meta($user->ID, 'wps_wpr_points', true);

        $message = __("👤 <b>Müşteri Bilgisi</b>\n\n", 'barkod-sistemi');
        $message .= __("📛 Ad: ", 'barkod-sistemi') . esc_html($user->display_name) . "\n";
        $message .= __("📧 E-posta: ", 'barkod-sistemi') . esc_html($user->user_email) . "\n";
        $message .= __("📱 Telefon: ", 'barkod-sistemi') . esc_html($phone) . "\n";
        $message .= __("⭐ Puan: ", 'barkod-sistemi') . number_format((int)$points);
        
        return $message;
    }
    
    // Stock Query
    private function handle_stock_query(array $args): string {
        if (empty($args[0])) {
            return __("❌ Kullanım: /stok [barkod]", 'barkod-sistemi');
        }
        
        $barcode = sanitize_text_field($args[0]);
        
        $args_query = [
            'post_type' => 'product',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_sku',
                    'value' => $barcode,
                    'compare' => '='
                ]
            ]
        ];
        
        $query = new WP_Query($args_query);
        
        if (!$query->have_posts()) {
            return __("❌ Ürün bulunamadı.", 'barkod-sistemi');
        }

        $query->the_post();
        $product = wc_get_product(get_the_ID());
        wp_reset_postdata();

        $message = __("📦 <b>Stok Bilgisi</b>\n\n", 'barkod-sistemi');
        $message .= __("📛 Ürün: ", 'barkod-sistemi') . esc_html($product->get_name()) . "\n";
        $message .= __("🔢 Barkod: ", 'barkod-sistemi') . esc_html($barcode) . "\n";
        $message .= __("💰 Fiyat: ", 'barkod-sistemi') . number_format((float)$product->get_price(), 2) . " TL\n";

        if ($product->managing_stock()) {
            $stock = $product->get_stock_quantity();
            $message .= sprintf(__("📊 Stok: %d adet\n", 'barkod-sistemi'), $stock);

            if ($stock <= self::STOCK_THRESHOLD) {
                $message .= __("⚠️ Düşük stok!", 'barkod-sistemi');
            }
        } else {
            $message .= __("📊 Stok: Takip edilmiyor", 'barkod-sistemi');
        }
        
        return $message;
    }
    
    // Sales Query
    private function handle_sales_query(): string {
        $today = current_time('Y-m-d');
        
        $orders = wc_get_orders([
            'date_created' => $today,
            'status' => 'completed',
            'limit' => -1
        ]);
        
        $total = 0;
        foreach ($orders as $order) {
            $total += $order->get_total();
        }
        
        $message = __("💰 <b>Bugünkü Satışlar</b>\n\n", 'barkod-sistemi');
        $message .= __("📅 Tarih: ", 'barkod-sistemi') . current_time('d.m.Y') . "\n";
        $message .= __("🛒 Sipariş: ", 'barkod-sistemi') . count($orders) . "\n";
        $message .= __("💵 Toplam: ", 'barkod-sistemi') . number_format((float)$total, 2) . " TL";

        return $message;
    }

    // Report Query
    private function handle_report_query(): string {
        $this->send_daily_report();
        return __("✅ Detaylı rapor gönderildi.", 'barkod-sistemi');
    }

    // 6. Remote Management Commands
    private function handle_discount_command(array $args): string {
        if (count($args) < 2) {
            return __("❌ Kullanım: /indirim [barkod] [yüzde]", 'barkod-sistemi');
        }

        $barcode = sanitize_text_field($args[0]);
        $percent = (float) $args[1];

        if ($percent < 0 || $percent > 100) {
            return __("❌ İndirim yüzdesi 0-100 arasında olmalı.", 'barkod-sistemi');
        }

        // Find product
        $product_id = wc_get_product_id_by_sku($barcode);

        if (!$product_id) {
            return __("❌ Ürün bulunamadı.", 'barkod-sistemi');
        }

        $product = wc_get_product($product_id);
        $regular_price = $product->get_regular_price();
        $sale_price = $regular_price * (1 - $percent / 100);

        $product->set_sale_price($sale_price);
        $product->save();

        $this->log_bot_action('DISCOUNT_APPLIED', "Product: {$barcode}, Discount: {$percent}%");

        return sprintf(
            __("✅ İndirim uygulandı!\n\n📦 Ürün: %1\$s\n💰 Eski Fiyat: %2\$s TL\n🏷️ Yeni Fiyat: %3\$s TL\n📉 İndirim: %%%4\$s", 'barkod-sistemi'),
            $product->get_name(),
            number_format((float)$regular_price, 2),
            number_format((float)$sale_price, 2),
            $percent
        );
    }

    private function handle_add_stock_command(array $args): string {
        if (count($args) < 2) {
            return __("❌ Kullanım: /stokekle [barkod] [adet]", 'barkod-sistemi');
        }

        $barcode = sanitize_text_field($args[0]);
        $quantity = (int) $args[1];

        if ($quantity <= 0) {
            return __("❌ Adet pozitif olmalı.", 'barkod-sistemi');
        }

        $product_id = wc_get_product_id_by_sku($barcode);

        if (!$product_id) {
            return __("❌ Ürün bulunamadı.", 'barkod-sistemi');
        }

        $product = wc_get_product($product_id);

        if (!$product->managing_stock()) {
            return __("❌ Bu ürün için stok takibi yapılmıyor.", 'barkod-sistemi');
        }

        $old_stock = $product->get_stock_quantity();
        $new_stock = $old_stock + $quantity;

        $product->set_stock_quantity($new_stock);
        $product->save();

        $this->log_bot_action('STOCK_ADDED', "Product: {$barcode}, Added: {$quantity}");

        return sprintf(
            __("✅ Stok eklendi!\n\n📦 Ürün: %1\$s\n📊 Eski Stok: %2\$s\n➕ Eklenen: %3\$s\n📈 Yeni Stok: %4\$s", 'barkod-sistemi'),
            $product->get_name(),
            $old_stock,
            $quantity,
            $new_stock
        );
    }

    private function handle_give_points_command(array $args): string {
        if (count($args) < 2) {
            return __("❌ Kullanım: /puanver [telefon] [puan]", 'barkod-sistemi');
        }

        $phone = sanitize_text_field($args[0]);
        $points = (int) $args[1];

        if ($points <= 0) {
            return __("❌ Puan pozitif olmalı.", 'barkod-sistemi');
        }

        $users = get_users([
            'meta_query' => [
                [
                    'key' => 'billing_phone',
                    'value' => $phone,
                    'compare' => '='
                ]
            ],
            'number' => 1
        ]);

        if (empty($users)) {
            return __("❌ Müşteri bulunamadı.", 'barkod-sistemi');
        }

        $user = $users[0];
        $old_points = (int) get_user_meta($user->ID, 'wps_wpr_points', true);
        $new_points = $old_points + $points;

        update_user_meta($user->ID, 'wps_wpr_points', $new_points);

        $this->log_bot_action('POINTS_GIVEN', "User: {$user->ID}, Points: {$points}");

        return sprintf(
            __("✅ Puan verildi!\n\n👤 Müşteri: %1\$s\n⭐ Eski Puan: %2\$s\n➕ Verilen: %3\$s\n🎁 Yeni Puan: %4\$s", 'barkod-sistemi'),
            $user->display_name,
            number_format((int)$old_points),
            number_format((int)$points),
            number_format((int)$new_points)
        );
    }

    // Help Message
    private function get_help_message(): string {
        $message = __("🤖 <b>POS Bot Komutları</b>\n\n", 'barkod-sistemi');
        $message .= __("<b>📊 Sorgular:</b>\n", 'barkod-sistemi');
        $message .= __("/musteri [telefon] - Müşteri bilgisi\n", 'barkod-sistemi');
        $message .= __("/stok [barkod] - Stok kontrolü\n", 'barkod-sistemi');
        $message .= __("/satis - Bugünkü satışlar\n", 'barkod-sistemi');
        $message .= __("/rapor - Detaylı rapor\n\n", 'barkod-sistemi');
        $message .= __("<b>⚙️ Yönetim:</b>\n", 'barkod-sistemi');
        $message .= __("/indirim [barkod] [%] - İndirim uygula\n", 'barkod-sistemi');
        $message .= __("/stokekle [barkod] [adet] - Stok ekle\n", 'barkod-sistemi');
        $message .= __("/puanver [telefon] [puan] - Puan ver\n\n", 'barkod-sistemi');
        $message .= __("/help - Bu mesajı göster", 'barkod-sistemi');

        return $message;
    }
    
    // Core send message function
    public function send_message(string $message): bool {
        if (!$this->is_configured()) return false;
        
        $url = self::API_URL . $this->bot_token . '/sendMessage';
        
        $response = wp_remote_post($url, [
            'body' => [
                'chat_id' => $this->chat_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            error_log('Telegram Bot Error: ' . $response->get_error_message());
            return false;
        }
        
        return true;
    }
    
    // Logging
    private function log_bot_action(string $action, string $details = ''): void {
        error_log(sprintf(
            '[TELEGRAM_BOT] %s | %s',
            $action,
            $details
        ));
    }
}
