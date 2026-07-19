<?php
declare(strict_types=1);
/*
 * Plugin Name: Barkod Sistemi
 * Description: Dükkan Müşterisi İçin Barkod Sistemi.
 * Version: 1.5.0
 * Author: AEA
 * Text Domain: barkod-sistemi
 * Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BARKOD_SISTEMI_VERSION', '1.5.0');
define('BARKOD_SISTEMI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BARKOD_SISTEMI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-sistemi.php';
require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-sistemi-admin.php';
require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-telegram-bot.php';
require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-telegram-settings.php';
require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-database-migrator.php';
require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-kumbara-manager.php';
require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-sms-service.php';
require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-exceptions.php';
require_once BARKOD_SISTEMI_PLUGIN_DIR . 'includes/class-barkod-logger.php';

if (!function_exists('barkod_sistemi_init')) :
function barkod_sistemi_init() {
    load_plugin_textdomain('barkod-sistemi', false, dirname(plugin_basename(__FILE__)) . '/languages');

    if (!did_action('woocommerce_loaded')) {
        return;
    }
    $plugin = new Barkod_Sistemi();
    $plugin->init();
    
    // Initialize Telegram settings
    new Barkod_Telegram_Settings();
    
    // Schedule daily report cron if enabled
    if (get_option('barkod_telegram_enable_daily_reports') === '1') {
        if (!wp_next_scheduled('barkod_telegram_daily_report')) {
            $time = get_option('barkod_telegram_report_time', '20:45');
            list($hour, $minute) = explode(':', $time);
            $timestamp = strtotime("today {$hour}:{$minute}");
            wp_schedule_event($timestamp, 'daily', 'barkod_telegram_daily_report');
        }
    }
}
add_action('init', 'barkod_sistemi_init', 20);
endif;

// Daily report cron handler
add_action('barkod_telegram_daily_report', function() {
    $bot = new Barkod_Telegram_Bot();
    $bot->send_daily_report();
});

// Test bot AJAX handler
add_action('wp_ajax_test_telegram_bot', function() {
    check_ajax_referer('test_telegram_bot', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Yetkisiz işlem', 'barkod-sistemi'));
    }

    $bot = new Barkod_Telegram_Bot();

    if (!$bot->is_configured()) {
        wp_send_json_error(__('Bot yapılandırılmamış', 'barkod-sistemi'));
    }

    $bot->send_message(sprintf(__("✅ Test mesajı!\n\n🤖 Telegram bot başarıyla yapılandırıldı.\n⏰ %s", 'barkod-sistemi'), current_time('d.m.Y H:i:s')));
    wp_send_json_success();
});

// Plugin activation hook
register_activation_hook(__FILE__, 'barkod_sistemi_activate');

if (!function_exists('barkod_sistemi_activate')) :
function barkod_sistemi_activate() {
    // Run database migrations
    $migrator = new Barkod_Database_Migrator();
    $migrator->migrate();
}
endif;

// Run migrations on plugin updates (when already active)
add_action('plugins_loaded', function() {
    $migrator = new Barkod_Database_Migrator();
    $migrator->migrate();
});