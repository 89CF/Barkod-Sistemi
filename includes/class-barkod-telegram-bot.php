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
        
        $message = "📦 <b>Yeni Satış!</b>\n\n";
        $message .= "🔢 Sipariş: #" . (int)$data['order_id'] . "\n";
        $message .= "💰 Tutar: " . number_format((float)$data['total'], 2) . " TL\n";
        
        if (!empty($data['customer'])) {
            $message .= "👤 Müşteri: " . esc_html($data['customer']) . "\n";
        }
        
        if ($data['points_used'] > 0) {
            $message .= "⭐ Kullanılan Puan: " . (int)$data['points_used'] . "\n";
        }
        
        if ($data['points_earned'] > 0) {
            $message .= "🎁 Kazanılan Puan: " . (int)$data['points_earned'] . "\n";
        }
        
        // Requirement 1.1, 2.1: Satış modunu bildir
        if (!empty($data['sale_mode'])) {
            $mode_icon = ($data['sale_mode'] === 'Hızlı Satış Modu') ? '⚡' : '🔧';
            $message .= "{$mode_icon} Mod: " . esc_html($data['sale_mode']) . "\n";
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
            $message = "⚠️ <b>Düşük Stok Uyarısı!</b>\n\n";
            $message .= "📦 Ürün: " . esc_html($product->get_name()) . "\n";
            $message .= "📊 Kalan: " . (int)$stock . " adet\n";
            $message .= "🔢 Barkod: " . esc_html($product->get_sku()) . "\n";
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
        
        $message = "📊 <b>Günlük Satış Raporu</b>\n";
        $message .= "📅 Tarih: " . current_time('d.m.Y') . "\n";
        $message .= "━━━━━━━━━━━━━━━━\n\n";
        $message .= "💰 Toplam Satış: " . number_format((float)$total_sales, 2) . " TL\n";
        $message .= "🛒 Sipariş Sayısı: " . count($orders) . "\n";
        $message .= "👥 Müşteri Sayısı: " . count($customers) . "\n";
        $message .= "⭐ Kullanılan Puan: " . number_format((int)$total_points_used) . "\n";
        $message .= "🎁 Kazanılan Puan: " . number_format((int)$total_points_earned) . "\n";
        
        $this->send_message($message);
        $this->log_bot_action('DAILY_REPORT', "Sales: {$total_sales} TL");
    }
    
    // 4. Security Alerts
    public function send_security_alert(string $event, string $details): void {
        if (!$this->is_configured()) return;
        
        $message = "🚨 <b>Güvenlik Uyarısı!</b>\n\n";
        $message .= "⚠️ Olay: {$event}\n";
        $message .= "📝 Detay: {$details}\n";
        $message .= "⏰ Zaman: " . current_time('d.m.Y H:i:s');
        
        $this->send_message($message);
        $this->log_bot_action('SECURITY_ALERT', $event);
    }
    
    // 5. Handle Bot Commands
    public function handle_command(string $command, array $args): string {
        if (!$this->is_configured()) {
            return "❌ Bot yapılandırılmamış.";
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
                return "❌ Bilinmeyen komut. /help yazarak komutları görebilirsiniz.";
        }
    }
    
    // Customer Query
    private function handle_customer_query(array $args): string {
        if (empty($args[0])) {
            return "❌ Kullanım: /musteri [telefon]";
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
            return "❌ Müşteri bulunamadı.";
        }
        
        $user = $users[0];
        $points = (int) get_user_meta($user->ID, 'wps_wpr_points', true);
        
        $message = "👤 <b>Müşteri Bilgisi</b>\n\n";
        $message .= "📛 Ad: " . esc_html($user->display_name) . "\n";
        $message .= "📧 E-posta: " . esc_html($user->user_email) . "\n";
        $message .= "📱 Telefon: " . esc_html($phone) . "\n";
        $message .= "⭐ Puan: " . number_format((int)$points);
        
        return $message;
    }
    
    // Stock Query
    private function handle_stock_query(array $args): string {
        if (empty($args[0])) {
            return "❌ Kullanım: /stok [barkod]";
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
            return "❌ Ürün bulunamadı.";
        }
        
        $query->the_post();
        $product = wc_get_product(get_the_ID());
        wp_reset_postdata();
        
        $message = "📦 <b>Stok Bilgisi</b>\n\n";
        $message .= "📛 Ürün: " . esc_html($product->get_name()) . "\n";
        $message .= "🔢 Barkod: " . esc_html($barcode) . "\n";
        $message .= "💰 Fiyat: " . number_format((float)$product->get_price(), 2) . " TL\n";
        
        if ($product->managing_stock()) {
            $stock = $product->get_stock_quantity();
            $message .= "📊 Stok: {$stock} adet\n";
            
            if ($stock <= self::STOCK_THRESHOLD) {
                $message .= "⚠️ Düşük stok!";
            }
        } else {
            $message .= "📊 Stok: Takip edilmiyor";
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
        
        $message = "💰 <b>Bugünkü Satışlar</b>\n\n";
        $message .= "📅 Tarih: " . current_time('d.m.Y') . "\n";
        $message .= "🛒 Sipariş: " . count($orders) . "\n";
        $message .= "💵 Toplam: " . number_format((float)$total, 2) . " TL";
        
        return $message;
    }
    
    // Report Query
    private function handle_report_query(): string {
        $this->send_daily_report();
        return "✅ Detaylı rapor gönderildi.";
    }
    
    // 6. Remote Management Commands
    private function handle_discount_command(array $args): string {
        if (count($args) < 2) {
            return "❌ Kullanım: /indirim [barkod] [yüzde]";
        }
        
        $barcode = sanitize_text_field($args[0]);
        $percent = (float) $args[1];
        
        if ($percent < 0 || $percent > 100) {
            return "❌ İndirim yüzdesi 0-100 arasında olmalı.";
        }
        
        // Find product
        $product_id = wc_get_product_id_by_sku($barcode);
        
        if (!$product_id) {
            return "❌ Ürün bulunamadı.";
        }
        
        $product = wc_get_product($product_id);
        $regular_price = $product->get_regular_price();
        $sale_price = $regular_price * (1 - $percent / 100);
        
        $product->set_sale_price($sale_price);
        $product->save();
        
        $this->log_bot_action('DISCOUNT_APPLIED', "Product: {$barcode}, Discount: {$percent}%");
        
        return "✅ İndirim uygulandı!\n\n📦 Ürün: {$product->get_name()}\n💰 Eski Fiyat: " . number_format((float)$regular_price, 2) . " TL\n🏷️ Yeni Fiyat: " . number_format((float)$sale_price, 2) . " TL\n📉 İndirim: %{$percent}";
    }
    
    private function handle_add_stock_command(array $args): string {
        if (count($args) < 2) {
            return "❌ Kullanım: /stokekle [barkod] [adet]";
        }
        
        $barcode = sanitize_text_field($args[0]);
        $quantity = (int) $args[1];
        
        if ($quantity <= 0) {
            return "❌ Adet pozitif olmalı.";
        }
        
        $product_id = wc_get_product_id_by_sku($barcode);
        
        if (!$product_id) {
            return "❌ Ürün bulunamadı.";
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product->managing_stock()) {
            return "❌ Bu ürün için stok takibi yapılmıyor.";
        }
        
        $old_stock = $product->get_stock_quantity();
        $new_stock = $old_stock + $quantity;
        
        $product->set_stock_quantity($new_stock);
        $product->save();
        
        $this->log_bot_action('STOCK_ADDED', "Product: {$barcode}, Added: {$quantity}");
        
        return "✅ Stok eklendi!\n\n📦 Ürün: {$product->get_name()}\n📊 Eski Stok: {$old_stock}\n➕ Eklenen: {$quantity}\n📈 Yeni Stok: {$new_stock}";
    }
    
    private function handle_give_points_command(array $args): string {
        if (count($args) < 2) {
            return "❌ Kullanım: /puanver [telefon] [puan]";
        }
        
        $phone = sanitize_text_field($args[0]);
        $points = (int) $args[1];
        
        if ($points <= 0) {
            return "❌ Puan pozitif olmalı.";
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
            return "❌ Müşteri bulunamadı.";
        }
        
        $user = $users[0];
        $old_points = (int) get_user_meta($user->ID, 'wps_wpr_points', true);
        $new_points = $old_points + $points;
        
        update_user_meta($user->ID, 'wps_wpr_points', $new_points);
        
        $this->log_bot_action('POINTS_GIVEN', "User: {$user->ID}, Points: {$points}");
        
        return "✅ Puan verildi!\n\n👤 Müşteri: {$user->display_name}\n⭐ Eski Puan: " . number_format((int)$old_points) . "\n➕ Verilen: " . number_format((int)$points) . "\n🎁 Yeni Puan: " . number_format((int)$new_points);
    }
    
    // Help Message
    private function get_help_message(): string {
        $message = "🤖 <b>POS Bot Komutları</b>\n\n";
        $message .= "<b>📊 Sorgular:</b>\n";
        $message .= "/musteri [telefon] - Müşteri bilgisi\n";
        $message .= "/stok [barkod] - Stok kontrolü\n";
        $message .= "/satis - Bugünkü satışlar\n";
        $message .= "/rapor - Detaylı rapor\n\n";
        $message .= "<b>⚙️ Yönetim:</b>\n";
        $message .= "/indirim [barkod] [%] - İndirim uygula\n";
        $message .= "/stokekle [barkod] [adet] - Stok ekle\n";
        $message .= "/puanver [telefon] [puan] - Puan ver\n\n";
        $message .= "/help - Bu mesajı göster";
        
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
