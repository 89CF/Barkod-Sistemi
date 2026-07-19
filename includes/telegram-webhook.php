<?php
declare(strict_types=1);
/**
 * Telegram Webhook Handler
 *
 * URL: https://yoursite.com/wp-content/plugins/barkod-sistemi/includes/telegram-webhook.php
 * Set webhook: https://api.telegram.org/bot[TOKEN]/setWebhook?url=[WEBHOOK_URL]
 */

// Prevent direct access before WordPress loads
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'telegram-webhook.php') {
    if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
        http_response_code(403);
        exit('Direct access not allowed');
    }
}

// Load WordPress
require_once('../../../../wp-load.php');

if (!defined('ABSPATH')) exit;

// Verify Telegram secret token (önce kontrol et, içeriği sonra oku)
$webhook_secret = get_option('barkod_telegram_webhook_secret', '');
if (!empty($webhook_secret)) {
    $incoming_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($webhook_secret, $incoming_token)) {
        http_response_code(403);
        exit;
    }
}

// Verify request
$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update || !isset($update['message'])) {
    http_response_code(200);
    exit;
}

// Check if commands are enabled
if (get_option('barkod_telegram_enable_commands') !== '1') {
    http_response_code(200);
    exit;
}

$message = $update['message'];
$chat_id = $message['chat']['id'] ?? 0;
$telegram_user_id = $message['from']['id'] ?? 0;
$text = $message['text'] ?? '';

// Verify chat ID
$allowed_chat_id = get_option('barkod_telegram_chat_id');
if ($chat_id != $allowed_chat_id) {
    http_response_code(403);
    exit;
}

// Rate limiting
$rate_limit_key = 'telegram_webhook_' . $chat_id;
$attempts = get_transient($rate_limit_key);

if ($attempts && $attempts >= 20) {
    http_response_code(429);
    exit;
}

set_transient($rate_limit_key, ($attempts ? $attempts + 1 : 1), 60);

// Parse command
if (strpos($text, '/') === 0) {
    $parts = explode(' ', $text);
    $command = $parts[0];
    $args = array_slice($parts, 1);
    
    // Check if command requires admin privileges
    $admin_commands = ['/indirim', '/stokekle', '/puanver'];
    if (in_array($command, $admin_commands)) {
        $admin_telegram_users = get_option('barkod_telegram_admin_users', []);
        
        if (!in_array($telegram_user_id, $admin_telegram_users)) {
            $response = __("❌ Bu komut için yetkiniz yok.\n\nSadece yetkili kullanıcılar yönetim komutlarını kullanabilir.", 'barkod-sistemi');
            
            // Send unauthorized response
            $bot_token = get_option('barkod_telegram_bot_token');
            $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
            
            wp_remote_post($url, [
                'body' => [
                    'chat_id' => $chat_id,
                    'text' => $response,
                    'parse_mode' => 'HTML'
                ]
            ]);
            
            // Log unauthorized attempt
            error_log(sprintf(
                '[TELEGRAM_SECURITY] UNAUTHORIZED_COMMAND | User: %d | Command: %s',
                $telegram_user_id,
                $command
            ));
            
            http_response_code(200);
            exit;
        }
    }
    
    // Initialize bot
    require_once('class-barkod-telegram-bot.php');
    $bot = new Barkod_Telegram_Bot();
    
    // Handle command
    $response = $bot->handle_command($command, $args);
    
    // Send response
    $bot_token = get_option('barkod_telegram_bot_token');
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    
    wp_remote_post($url, [
        'body' => [
            'chat_id' => $chat_id,
            'text' => $response,
            'parse_mode' => 'HTML'
        ]
    ]);
}

http_response_code(200);
