<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

class Barkod_Telegram_Settings {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    public function add_settings_page(): void {
        add_submenu_page(
            'dukkan-musterisi',
            __('Telegram Bot Ayarları', 'barkod-sistemi'),
            __('Telegram Bot', 'barkod-sistemi'),
            'manage_woocommerce',
            'barkod-telegram-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function register_settings(): void {
        register_setting('barkod_telegram_settings', 'barkod_telegram_bot_token');
        register_setting('barkod_telegram_settings', 'barkod_telegram_chat_id');
        register_setting('barkod_telegram_settings', 'barkod_telegram_webhook_secret', [
            'sanitize_callback' => [$this, 'sanitize_webhook_secret']
        ]);
        register_setting('barkod_telegram_settings', 'barkod_telegram_enable_sale_notifications');
        register_setting('barkod_telegram_settings', 'barkod_telegram_enable_stock_alerts');
        register_setting('barkod_telegram_settings', 'barkod_telegram_stock_threshold');
        register_setting('barkod_telegram_settings', 'barkod_telegram_enable_daily_reports', [
            'sanitize_callback' => [$this, 'sanitize_daily_report_toggle']
        ]);
        register_setting('barkod_telegram_settings', 'barkod_telegram_report_time', [
            'sanitize_callback' => [$this, 'sanitize_report_time']
        ]);
        register_setting('barkod_telegram_settings', 'barkod_telegram_enable_security_alerts');
        register_setting('barkod_telegram_settings', 'barkod_telegram_enable_commands');
        register_setting('barkod_telegram_settings', 'barkod_telegram_admin_users', [
            'sanitize_callback' => [$this, 'sanitize_admin_users']
        ]);
    }

    public function sanitize_daily_report_toggle($input): string {
        $this->reschedule_daily_report_cron($input === '1', get_option('barkod_telegram_report_time', '20:45'));
        return $input === '1' ? '1' : '0';
    }

    public function sanitize_report_time($input): string {
        $time = sanitize_text_field($input);
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '20:45';
        }
        if (get_option('barkod_telegram_enable_daily_reports') === '1') {
            $this->reschedule_daily_report_cron(true, $time);
        }
        return $time;
    }

    private function reschedule_daily_report_cron(bool $enable, string $time): void {
        $timestamp = wp_next_scheduled('barkod_telegram_daily_report');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'barkod_telegram_daily_report');
        }

        if ($enable) {
            list($hour, $minute) = explode(':', $time);
            $next = strtotime("today {$hour}:{$minute}");
            if ($next <= time()) {
                $next = strtotime("tomorrow {$hour}:{$minute}");
            }
            wp_schedule_event($next, 'daily', 'barkod_telegram_daily_report');
        }
    }
    
    public function sanitize_webhook_secret($input): string {
        $secret = sanitize_text_field($input);
        // Boşsa otomatik oluştur
        if (empty($secret)) {
            $secret = wp_generate_password(32, false);
        }
        return $secret;
    }

    public function sanitize_admin_users($input): array {
        if (empty($input)) return [];
        
        $user_ids = array_map('trim', explode(',', $input));
        $user_ids = array_filter($user_ids, 'is_numeric');
        $user_ids = array_map('intval', $user_ids);
        
        return array_unique($user_ids);
    }
    
    public function render_settings_page(): void {
        ?>
        <div class="pos-wrap">
            <div class="pos-header">
                <h1 class="pos-title" style="color: #263238 !important; opacity: 1 !important; visibility: visible !important;"><?php echo esc_html__('Telegram Bot', 'barkod-sistemi'); ?></h1>
                <p class="pos-subtitle"><?php echo esc_html__('Mağazanızı her yerden yönetin ve takip edin', 'barkod-sistemi'); ?></p>
            </div>

            <div class="pos-grid">
                <!-- Sol Kolon: Ayarlar -->
                <div class="pos-col-8">
                    <form method="post" action="options.php">
                        <?php settings_fields('barkod_telegram_settings'); ?>

                        <!-- Bot Kimlik Bilgileri -->
                        <div class="telegram-section">
                            <div class="telegram-header">
                                <div class="telegram-icon">🤖</div>
                                <h2 style="font-size: 1.8rem; margin: 0;"><?php echo esc_html__('Bot Kimlik Bilgileri', 'barkod-sistemi'); ?></h2>
                            </div>

                            <div class="pos-grid">
                                <div class="pos-col-6">
                                    <div class="pos-form-group">
                                        <label class="pos-label" for="barkod_telegram_bot_token"><?php echo esc_html__('Bot Token', 'barkod-sistemi'); ?></label>
                                        <input type="text"
                                               id="barkod_telegram_bot_token"
                                               name="barkod_telegram_bot_token"
                                               value="<?php echo esc_attr(get_option('barkod_telegram_bot_token')); ?>"
                                               class="pos-input"
                                               placeholder="123456789:ABC...">
                                        <p class="description" style="margin-top: 10px; color: var(--pos-text-muted);"><?php echo esc_html__("BotFather'dan aldığınız anahtar", 'barkod-sistemi'); ?></p>
                                    </div>
                                </div>
                                <div class="pos-col-6">
                                    <div class="pos-form-group">
                                        <label class="pos-label" for="barkod_telegram_chat_id"><?php echo esc_html__('Chat ID', 'barkod-sistemi'); ?></label>
                                        <input type="text"
                                               id="barkod_telegram_chat_id"
                                               name="barkod_telegram_chat_id"
                                               value="<?php echo esc_attr(get_option('barkod_telegram_chat_id')); ?>"
                                               class="pos-input"
                                               placeholder="-100123...">
                                        <p class="description" style="margin-top: 10px; color: var(--pos-text-muted);"><?php echo esc_html__("Grup veya kişi ID'si", 'barkod-sistemi'); ?></p>
                                    </div>
                                </div>

                                <div class="pos-col-12">
                                    <div class="pos-form-group">
                                        <label class="pos-label" for="barkod_telegram_webhook_secret"><?php echo esc_html__('Webhook Secret Token', 'barkod-sistemi'); ?></label>
                                        <?php
                                        $secret = get_option('barkod_telegram_webhook_secret', '');
                                        if (empty($secret)) {
                                            $secret = wp_generate_password(32, false);
                                            update_option('barkod_telegram_webhook_secret', $secret);
                                        }
                                        ?>
                                        <input type="text"
                                               id="barkod_telegram_webhook_secret"
                                               name="barkod_telegram_webhook_secret"
                                               value="<?php echo esc_attr($secret); ?>"
                                               class="pos-input"
                                               readonly>
                                        <p class="description" style="margin-top: 10px; color: var(--pos-text-muted);">
                                            <?php echo esc_html__("Webhook URL'inizi kurarken bu token'ı kullanın:", 'barkod-sistemi'); ?><br>
                                            <code>https://api.telegram.org/bot[TOKEN]/setWebhook?url=[URL]&secret_token=<?php echo esc_attr($secret); ?></code>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bildirim Ayarları -->
                        <div class="telegram-section">
                            <div class="telegram-header">
                                <div class="telegram-icon">📢</div>
                                <h2 style="font-size: 1.8rem; margin: 0;"><?php echo esc_html__('Bildirim Ayarları', 'barkod-sistemi'); ?></h2>
                            </div>

                            <div class="pos-grid">
                                <div class="pos-col-6">
                                    <div class="pos-card" style="padding: 25px; background: var(--pos-secondary); border: none; height: 100%;">
                                        <label class="d-flex align-center gap-2 mb-3" style="cursor: pointer; font-size: 1.2rem; font-weight: 700; color: var(--pos-text-main);">
                                            <input type="checkbox"
                                                   name="barkod_telegram_enable_sale_notifications"
                                                   value="1"
                                                   style="width: 22px !important; height: 22px !important;"
                                                   <?php checked(get_option('barkod_telegram_enable_sale_notifications'), '1'); ?>>
                                            <?php echo esc_html__('Satış Bildirimleri', 'barkod-sistemi'); ?>
                                        </label>
                                        <p style="margin: 0; color: var(--pos-primary-light); font-size: 0.95rem;"><?php echo esc_html__('Her başarılı satışta anlık bilgi mesajı gönderir.', 'barkod-sistemi'); ?></p>
                                    </div>
                                </div>

                                <div class="pos-col-6">
                                    <div class="pos-card" style="padding: 25px; background: var(--pos-secondary); border: none; height: 100%;">
                                        <label class="d-flex align-center gap-2 mb-3" style="cursor: pointer; font-size: 1.2rem; font-weight: 700; color: var(--pos-text-main);">
                                            <input type="checkbox"
                                                   name="barkod_telegram_enable_security_alerts"
                                                   value="1"
                                                   style="width: 22px !important; height: 22px !important;"
                                                   <?php checked(get_option('barkod_telegram_enable_security_alerts'), '1'); ?>>
                                            <?php echo esc_html__('Güvenlik Uyarıları', 'barkod-sistemi'); ?>
                                        </label>
                                        <p style="margin: 0; color: var(--pos-primary-light); font-size: 0.95rem;"><?php echo esc_html__('Şüpheli giriş denemeleri ve sistem hatalarını bildirir.', 'barkod-sistemi'); ?></p>
                                    </div>
                                </div>

                                <div class="pos-col-12">
                                    <div class="pos-card" style="padding: 30px; border: 2px dashed var(--pos-primary-light); background: white;">
                                        <div class="d-flex justify-between align-center mb-4">
                                            <label class="d-flex align-center gap-2" style="cursor: pointer; font-size: 1.3rem; font-weight: 700; color: var(--pos-text-main);">
                                                <input type="checkbox"
                                                       name="barkod_telegram_enable_stock_alerts"
                                                       value="1"
                                                       style="width: 24px !important; height: 24px !important;"
                                                       <?php checked(get_option('barkod_telegram_enable_stock_alerts'), '1'); ?>>
                                                <?php echo esc_html__('Düşük Stok Uyarıları', 'barkod-sistemi'); ?>
                                            </label>
                                            <div class="d-flex align-center gap-2">
                                                <span class="pos-label" style="margin:0;"><?php echo esc_html__('Eşik:', 'barkod-sistemi'); ?></span>
                                                <input type="number"
                                                       name="barkod_telegram_stock_threshold"
                                                       value="<?php echo esc_attr(get_option('barkod_telegram_stock_threshold', '10')); ?>"
                                                       min="1"
                                                       max="100"
                                                       class="pos-input"
                                                       style="width: 100px; padding: 10px 15px;">
                                                <span style="font-weight: 700; color: var(--pos-primary);"><?php echo esc_html__('ADET', 'barkod-sistemi'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pos-col-12">
                                    <div class="pos-card" style="padding: 30px; border: 2px dashed var(--pos-primary-light); background: white;">
                                        <div class="d-flex justify-between align-center">
                                            <label class="d-flex align-center gap-2" style="cursor: pointer; font-size: 1.3rem; font-weight: 700; color: var(--pos-text-main);">
                                                <input type="checkbox"
                                                       name="barkod_telegram_enable_daily_reports"
                                                       value="1"
                                                       style="width: 24px !important; height: 24px !important;"
                                                       <?php checked(get_option('barkod_telegram_enable_daily_reports'), '1'); ?>>
                                                <?php echo esc_html__('Günlük Özet Raporu', 'barkod-sistemi'); ?>
                                            </label>
                                            <div class="d-flex align-center gap-2">
                                                <span class="pos-label" style="margin:0;"><?php echo esc_html__('Rapor Saati:', 'barkod-sistemi'); ?></span>
                                                <input type="time"
                                                       name="barkod_telegram_report_time"
                                                       value="<?php echo esc_attr(get_option('barkod_telegram_report_time', '09:00')); ?>"
                                                       class="pos-input"
                                                       style="width: auto; padding: 10px 15px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bot Komutları -->
                        <div class="telegram-section">
                            <div class="telegram-header">
                                <div class="telegram-icon">⚡</div>
                                <h2 style="font-size: 1.8rem; margin: 0;"><?php echo esc_html__('Bot Komutları', 'barkod-sistemi'); ?></h2>
                            </div>

                            <div class="pos-form-group">
                                <label class="d-flex align-center gap-2" style="cursor: pointer; font-size: 1.2rem; font-weight: 700; color: var(--pos-text-main);">
                                    <input type="checkbox"
                                           name="barkod_telegram_enable_commands"
                                           value="1"
                                           style="width: 22px !important; height: 22px !important;"
                                           <?php checked(get_option('barkod_telegram_enable_commands'), '1'); ?>>
                                    <?php echo esc_html__('İnteraktif Komutlar', 'barkod-sistemi'); ?>
                                </label>

                                <div style="background: var(--pos-secondary); padding: 30px; border-radius: var(--pos-radius-md); margin-top: 25px;">
                                    <strong style="display: block; margin-bottom: 20px; color: var(--pos-text-main); font-size: 1.2rem; font-family: var(--font-display);"><?php echo esc_html__('Kullanılabilir Komutlar:', 'barkod-sistemi'); ?></strong>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <code style="background: white; padding: 10px 15px; border-radius: 10px; border: 1px solid var(--pos-border); color: var(--pos-text-main); font-weight: 600;">/musteri [tel]</code>
                                        <code style="background: white; padding: 10px 15px; border-radius: 10px; border: 1px solid var(--pos-border); color: var(--pos-text-main); font-weight: 600;">/stok [barkod]</code>
                                        <code style="background: white; padding: 10px 15px; border-radius: 10px; border: 1px solid var(--pos-border); color: var(--pos-text-main); font-weight: 600;">/satis</code>
                                        <code style="background: white; padding: 10px 15px; border-radius: 10px; border: 1px solid var(--pos-border); color: var(--pos-text-main); font-weight: 600;">/rapor</code>
                                        <code style="background: white; padding: 10px 15px; border-radius: 10px; border: 1px solid var(--pos-border); color: var(--pos-text-main); font-weight: 600;">/indirim [barkod] [%]</code>
                                        <code style="background: white; padding: 10px 15px; border-radius: 10px; border: 1px solid var(--pos-border); color: var(--pos-text-main); font-weight: 600;">/stokekle [barkod] [adet]</code>
                                    </div>
                                </div>
                            </div>

                            <div class="pos-form-group" style="margin-top: 30px;">
                                <label class="pos-label" for="barkod_telegram_admin_users"><?php echo esc_html__('Yetkili Kullanıcılar', 'barkod-sistemi'); ?></label>
                                <input type="text"
                                       name="barkod_telegram_admin_users"
                                       value="<?php echo esc_attr(implode(', ', get_option('barkod_telegram_admin_users', []))); ?>"
                                       class="pos-input"
                                       placeholder="123456789, 987654321">
                                <p class="description" style="margin-top: 10px; color: var(--pos-text-muted);">
                                    <?php echo esc_html__("Yönetim komutlarını kullanabilecek Telegram ID'leri (virgülle ayırın).", 'barkod-sistemi'); ?>
                                </p>
                            </div>
                        </div>

                        <div style="text-align: right; margin-top: 50px;">
                            <?php submit_button(__('Ayarları Kaydet', 'barkod-sistemi'), 'primary', 'submit', false, ['class' => 'pos-btn pos-btn-primary', 'style' => 'width: 100%; max-width: 300px;']); ?>
                        </div>
                    </form>
                </div>

                <!-- Sağ Kolon: Rehber & Test -->
                <div class="pos-col-4">
                    <div class="pos-card" style="background: var(--pos-secondary); border: none; position: sticky; top: 30px;">
                        <div class="d-flex align-center gap-2 mb-4">
                            <span class="mod-icon" style="font-size: 2rem;">💡</span>
                            <h3 style="margin:0; font-size: 1.5rem;"><?php echo esc_html__('Nasıl Kurulur?', 'barkod-sistemi'); ?></h3>
                        </div>
                        <ul style="margin: 0; padding: 0; list-style: none; color: var(--pos-text-main); font-weight: 500;">
                            <li style="margin-bottom: 20px; display: flex; gap: 15px;">
                                <span style="background: var(--pos-primary); color: white; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.8rem;">1</span>
                                <span><?php echo wp_kses(sprintf(__("Telegram'da <strong>@BotFather</strong>'ı bulun ve <code>/newbot</code> ile botunuzu oluşturun.", 'barkod-sistemi')), ['strong' => [], 'code' => []]); ?></span>
                            </li>
                            <li style="margin-bottom: 20px; display: flex; gap: 15px;">
                                <span style="background: var(--pos-primary); color: white; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.8rem;">2</span>
                                <span><?php echo wp_kses(__("Size verilen <strong>Bot Token</strong>'ı kopyalayıp sola yapıştırın.", 'barkod-sistemi'), ['strong' => []]); ?></span>
                            </li>
                            <li style="margin-bottom: 20px; display: flex; gap: 15px;">
                                <span style="background: var(--pos-primary); color: white; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.8rem;">3</span>
                                <span><?php echo wp_kses(__("Botunuza bir mesaj gönderin ve <strong>Chat ID</strong>'nizi alın.", 'barkod-sistemi'), ['strong' => []]); ?></span>
                            </li>
                            <li style="display: flex; gap: 15px;">
                                <span style="background: var(--pos-primary); color: white; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.8rem;">4</span>
                                <span><?php echo wp_kses(__('Tüm ayarları kaydedip <strong>Bağlantı Testi</strong> yapın.', 'barkod-sistemi'), ['strong' => []]); ?></span>
                            </li>
                        </ul>

                        <div style="margin-top: 40px; padding-top: 30px; border-top: 2px solid rgba(45, 90, 39, 0.1);">
                            <h4 style="margin-bottom: 20px; font-size: 1.2rem;"><?php echo esc_html__('Bağlantı Testi', 'barkod-sistemi'); ?></h4>
                            <button type="button" class="pos-btn pos-btn-secondary w-full" onclick="testTelegramBot()">
                                <span class="dashicons dashicons-email-alt"></span> <?php echo esc_html__('Test Mesajı Gönder', 'barkod-sistemi'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            function testTelegramBot() {
                if (confirm('<?php echo esc_js(__('Test mesajı göndermek istediğinize emin misiniz?', 'barkod-sistemi')); ?>')) {
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action: 'test_telegram_bot',
                            nonce: '<?php echo wp_create_nonce('test_telegram_bot'); ?>'
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        alert(data.success ? '<?php echo esc_js(__('✅ Test mesajı gönderildi!', 'barkod-sistemi')); ?>' : '<?php echo esc_js(__('❌ Hata: ', 'barkod-sistemi')); ?>' + data.data);
                    });
                }
            }
            </script>
        </div>
        <?php
    }
}
