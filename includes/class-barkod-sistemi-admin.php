<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Barkod_Sistemi_Admin {
    
    // Security constants
    private const RATE_LIMIT_MAX_ATTEMPTS = 100;
    private const RATE_LIMIT_WINDOW = 60; // seconds
    
    // Validation constants
    private const PHONE_LENGTH = 10;
    private const EMAIL_MAX_LENGTH = 100;
    private const USERNAME_MIN_LENGTH = 3;
    private const USERNAME_MAX_LENGTH = 60;
    private const PASSWORD_LENGTH = 16;
    private const BARCODE_MIN_LENGTH = 8;
    private const BARCODE_MAX_LENGTH = 20;
    
    // Point system constants
    private const POINT_DISCOUNT_RATE = 0.02; // 1 puan = 0.02 TL indirim (100 puan = 2 TL)
    private const POINT_EARN_RATE = 1;     // 1 TL harcama = 1 puan
    
    /**
     * Logger instance
     * @var Barkod_Logger
     */
    private $logger;
    
    public function __construct() {
        $this->logger = new Barkod_Logger();
    }

    public function init(): void {
        add_action('admin_menu', array($this, 'admin_menuyu_ekle'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scriptleri_yukle'), 999); // En son yükle
        add_action('admin_init', array($this, 'add_security_headers'));
        add_action('wp_ajax_musteri_tel_ara', [$this, 'musteri_tel_ara_callback']); 
        add_action('wp_ajax_urun_barkod_ara', [$this, 'urun_barkod_ara_callback']);
        add_action('wp_ajax_satisi_tamamla', [$this, 'satisi_tamamla_callback']);  
        add_action('wp_ajax_pos_kullanici_olustur', [$this, 'pos_kullanici_olustur']);
        add_action('wp_ajax_hizli_satis_urun_ekle', [$this, 'hizli_satis_urun_ekle_callback']);
        add_action('wp_ajax_barkod_bas', [$this, 'barkod_bas_callback']);
        add_action('wp_ajax_puan_bagis_yap', [$this, 'puan_bagis_yap_callback']);
        add_action('wp_ajax_kumbara_listesi_getir', [$this, 'kumbara_listesi_getir_callback']);
        add_action('wp_ajax_iade_siparis_ara', [$this, 'iade_siparis_ara_callback']);
        add_action('wp_ajax_iade_islemini_tamamla', [$this, 'iade_islemini_tamamla_callback']);
        add_action('wp_ajax_kasa_kapanisi_getir', [$this, 'kasa_kapanisi_getir_callback']);
        add_action('wp_ajax_skt_urunleri_getir', [$this, 'skt_urunleri_getir_callback']);
        
        // SMS notification triggers - Requirement 5.1, 5.5
        add_action('barkod_kumbara_activated', [$this, 'send_kumbara_activation_sms'], 10, 1);
        add_action('woof_kumbara_created', [$this, 'send_kumbara_activation_sms'], 10, 1);
        
        // POS siparişlerinde otomatik SMS gönderimini engelle
        add_action('woocommerce_order_status_completed', [$this, 'prevent_pos_order_sms'], 5, 2);
        
        // İletiMerkezi SMS plugin'i için özel engelleme
        add_filter('iletimerkezi_should_send_sms', [$this, 'block_pos_order_sms'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', [$this, 'maybe_disable_completed_email'], 10, 2);
    }

    public function add_security_headers(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    public function admin_scriptleri_yukle($hook) {
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        $allowed_pages = [
            'dukkan-musterisi',
            'barkod-gecmisi',
            'barkod-telegram-settings',
            'barkod-iade',
            'barkod-kasa-kapanisi',
        ];

        if (!in_array($current_page, $allowed_pages)) return;

        // CSS - Modüler yapı (@import ile yükleniyor)
        wp_enqueue_style(
            'barkod-sistemi-css',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BARKOD_SISTEMI_VERSION
        );

        // Override CSS - Hemen sonra yükle
        wp_enqueue_style(
            'barkod-sistemi-override',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/css/admin/utilities/override.css',
            array('barkod-sistemi-css'),
            BARKOD_SISTEMI_VERSION . '-' . time() // Cache busting
        );

        // Inline CSS - Maksimum Güç
        $inline_css = '
        body.wp-admin .pos-wrap h1,
        body.wp-admin .pos-wrap h1.pos-title,
        body.wp-admin .pos-wrap .pos-header h1,
        body.wp-admin .pos-wrap .pos-header .pos-title,
        .pos-wrap h1.pos-title,
        .pos-wrap h2,
        .pos-wrap h3 {
            color: #263238 !important;
            font-family: "Fredoka", sans-serif !important;
            opacity: 1 !important;
            visibility: visible !important;
            background: transparent !important;
            text-shadow: none !important;
            -webkit-text-fill-color: #263238 !important;
        }
        ';
        wp_add_inline_style('barkod-sistemi-override', $inline_css);

        // JavaScript Modülleri - Dependency sırasına göre
        
        // 1. NotificationManager (temel bağımlılık)
        wp_enqueue_script(
            'barkod-notification-manager',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/js/modules/notification-manager.js',
            array('jquery'),
            BARKOD_SISTEMI_VERSION,
            true
        );

        // 2. HizliSatisModulu
        wp_enqueue_script(
            'barkod-hizli-satis',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/js/modules/hizli-satis-modulu.js',
            array('jquery', 'barkod-notification-manager'),
            BARKOD_SISTEMI_VERSION,
            true
        );

        // 3. GelistiriciModulu
        wp_enqueue_script(
            'barkod-gelistirici',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/js/modules/gelistirici-modulu.js',
            array('jquery', 'barkod-notification-manager'),
            BARKOD_SISTEMI_VERSION,
            true
        );

        // 4. SepetYoneticisi
        wp_enqueue_script(
            'barkod-sepet-yoneticisi',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/js/modules/sepet-yoneticisi.js',
            array('jquery', 'barkod-notification-manager'),
            BARKOD_SISTEMI_VERSION,
            true
        );

        // 5. PuanBagisModulu
        wp_enqueue_script(
            'barkod-puan-bagis',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/js/modules/puan-bagis-modulu.js',
            array('jquery', 'barkod-notification-manager'),
            BARKOD_SISTEMI_VERSION,
            true
        );

        // 6. KullaniciModulu
        wp_enqueue_script(
            'barkod-kullanici',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/js/modules/kullanici-modulu.js',
            array('jquery', 'barkod-notification-manager'),
            BARKOD_SISTEMI_VERSION,
            true
        );

        // 7. ModSelector
        wp_enqueue_script(
            'barkod-mod-selector',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/js/modules/mod-selector.js',
            array('jquery'),
            BARKOD_SISTEMI_VERSION,
            true
        );

        // 8. Ana dosya (tüm modüllere bağımlı - en son yüklenir)
        wp_enqueue_script(
            'barkod-sistemi-js',
            BARKOD_SISTEMI_PLUGIN_URL . 'assets/js/admin.js',
            array(
                'jquery',
                'barkod-notification-manager',
                'barkod-hizli-satis',
                'barkod-gelistirici',
                'barkod-sepet-yoneticisi',
                'barkod-puan-bagis',
                'barkod-kullanici',
                'barkod-mod-selector'
            ),
            BARKOD_SISTEMI_VERSION,
            true
        );

        // AJAX değişkenlerini ana dosyaya ekle
        wp_localize_script('barkod-sistemi-js', 'barkodSistemiAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('barkod_sistemi_nonce'),
            'pluginUrl' => BARKOD_SISTEMI_PLUGIN_URL
        ));
    }

    public function admin_menuyu_ekle() {
        add_menu_page(
            __('POS Sistemi', 'barkod-sistemi'),
            __('POS Sistemi', 'barkod-sistemi'),
            'manage_woocommerce',
            'dukkan-musterisi',
            array($this, 'admin_sayfasini_goster'),
            'dashicons-cart',
            56
        );
        
        // Barkod basma geçmişi alt menüsü
        // Requirement 6.4: Barkod basma geçmişini raporlama ekranında göster
        add_submenu_page(
            'dukkan-musterisi',
            __('Barkod Geçmişi', 'barkod-sistemi'),
            __('Barkod Geçmişi', 'barkod-sistemi'),
            'manage_woocommerce',
            'barkod-gecmisi',
            array($this, 'barkod_gecmisi_sayfasi')
        );
        
        // SMS Test ve Ayarlar alt menüsü
        add_submenu_page(
            'dukkan-musterisi',
            __('SMS Test & Ayarlar', 'barkod-sistemi'),
            __('SMS Test & Ayarlar', 'barkod-sistemi'),
            'manage_options',
            'barkod-sms-test',
            array($this, 'sms_test_sayfasi')
        );

        // İade İşlemi alt menüsü
        add_submenu_page(
            'dukkan-musterisi',
            __('İade İşlemi', 'barkod-sistemi'),
            __('İade İşlemi', 'barkod-sistemi'),
            'manage_woocommerce',
            'barkod-iade',
            array($this, 'iade_sayfasi')
        );

        // Kasa Kapanış Raporu alt menüsü
        add_submenu_page(
            'dukkan-musterisi',
            __('Kasa Kapanışı', 'barkod-sistemi'),
            __('Kasa Kapanışı', 'barkod-sistemi'),
            'manage_woocommerce',
            'barkod-kasa-kapanisi',
            array($this, 'kasa_kapanisi_sayfasi')
        );
    }

    public function admin_sayfasini_goster() {
        ?>
        <div class="pos-wrap">
            <div class="pos-header">
                <h1 class="pos-title" style="color: #263238 !important; opacity: 1 !important; visibility: visible !important;">POS Satış Sistemi</h1>
                <p class="pos-subtitle">Dostlarınız için hızlı ve güvenli alışveriş</p>
            </div>

            <!-- Mod Seçici -->
            <div class="mod-selector">
                <button type="button" class="mod-button active" data-mode="hizli" id="hizliSatisModBtn">
                    <span class="mod-icon">⚡</span>
                    <span class="mod-label">Hızlı Satış</span>
                </button>
                <button type="button" class="mod-button" data-mode="gelistirici" id="gelistiriciModBtn">
                    <span class="mod-icon">🛠️</span>
                    <span class="mod-label">Geliştirici</span>
                </button>
            </div>

            <div class="pos-grid">
                <!-- Sol Kolon: Müşteri ve Ürün İşlemleri -->
                <div class="pos-col-8">
                    
                    <!-- Müşteri İşlemleri -->
                    <div class="pos-card">
                        <div class="d-flex justify-between align-center mb-4">
                            <h3 class="pos-label" style="font-size: 1.5rem; margin:0;">Müşteri İşlemleri</h3>
                            <label class="d-flex align-center gap-2" style="cursor: pointer; font-weight: 500;">
                                <input type="checkbox" id="uyesizDevam" style="width: 20px; height: 20px;" />
                                <span class="pos-text-muted">Üyesiz Devam Et</span>
                            </label>
                        </div>

                        <div class="d-flex gap-2 mb-4">
                            <input type="text" id="musteriTel" placeholder="Telefon numarası (5XX...)" class="pos-input" />
                            <button id="musteriAraBtn" class="pos-btn pos-btn-primary">
                                <span class="dashicons dashicons-search"></span> Ara
                            </button>
                        </div>

                        <div class="d-flex gap-2">
                            <button id="kullaniciOlusturBtn" class="pos-btn pos-btn-secondary w-full">
                                <span class="dashicons dashicons-admin-users"></span> Kullanıcı Oluştur
                            </button>
                            <button id="puanBagisBtn" class="pos-btn pos-btn-secondary w-full" style="border-color: var(--pos-accent); color: var(--pos-accent);">
                                <span class="dashicons dashicons-heart"></span> Puan Bağışı
                            </button>
                        </div>

                        <div id="sonuc" class="pos-result-card" style="display:none;"></div>
                    </div>

                    <!-- Ürün Arama -->
                    <div class="pos-card">
                        <h3 class="pos-label" style="font-size: 1.5rem;">Ürün Ekle</h3>
                        <div class="pos-form-group">
                            <input type="text" id="urunBarkod" placeholder="Barkodu okutun veya yazın..." disabled class="pos-input" style="font-size: 1.4rem; padding: 25px; text-align: center; letter-spacing: 2px;" />
                        </div>
                        <div id="urunBilgi"></div>
                    </div>

                </div>

                <!-- Sağ Kolon: Sepet -->
                <div class="pos-col-4">
                    <div id="pos-empty-state" class="pos-empty-state">
                        <div class="pos-empty-state-icon">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <h3 class="pos-empty-state-title">Sepetiniz Boş</h3>
                        <p class="pos-empty-state-text">Ürün eklemek için barkod okutun veya hızlı satış modunu kullanın.</p>
                    </div>

                    <div class="pos-card" id="sepet" style="display:none; position: sticky; top: 32px;">
                        <h3 class="pos-label" style="font-size: 1.8rem; margin-bottom: 25px; border-bottom: 3px solid var(--pos-secondary); padding-bottom: 15px;">Sepetim</h3>
                        
                        <div id="sepetUrunleri" style="max-height: 500px; overflow-y: auto; padding-right: 10px;"></div>
                        
                        <div style="margin-top: 30px; border-top: 3px solid var(--pos-secondary); padding-top: 25px;">
                            <div class="d-flex justify-between mb-4">
                                <span class="pos-label" style="font-size: 1.4rem;">Toplam</span>
                                <span class="pos-label" style="font-size: 2rem; color: var(--pos-primary);">
                                    <span id="sepetToplam">0.00</span> <?php echo esc_html(get_woocommerce_currency_symbol()); ?>
                                </span>
                            </div>

                            <div id="pos-puan" style="display:none; background: var(--pos-secondary); padding: 20px; border-radius: var(--pos-radius-md); margin-bottom: 25px; border: 1px solid rgba(45, 90, 39, 0.1);">
                                <label class="pos-label">Puan Kullanımı</label>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="number" id="kullanilanPuanInput" class="pos-input" min="0" value="0" disabled />
                                    <button id="puanUygulaBtn" class="pos-btn pos-btn-secondary" disabled>Uygula</button>
                                </div>
                                <div class="d-flex justify-between" style="font-size: 1rem; margin-bottom: 5px;">
                                    <span>Puan İndirimi:</span>
                                    <strong style="color: var(--pos-success);">-<span id="puanIndirim">0.00</span> <?php echo esc_html(get_woocommerce_currency_symbol()); ?></strong>
                                </div>
                                <div class="d-flex justify-between" style="font-size: 1.3rem; margin-top: 15px; color: var(--pos-primary-dark); border-top: 1px dashed rgba(45, 90, 39, 0.2); padding-top: 10px;">
                                    <span>Ödenecek:</span>
                                    <strong><span id="sepetYeniToplam">0.00</span> <?php echo esc_html(get_woocommerce_currency_symbol()); ?></strong>
                                </div>
                            </div>

                            <button id="siparisTamamlaBtn" class="pos-btn pos-btn-primary w-full" style="padding: 25px; font-size: 1.4rem;">
                                Satışı Bitir <span class="dashicons dashicons-arrow-right-alt"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // Security event logging
    private function log_security_event(string $event, string $details = ''): void {
        error_log(sprintf(
            '[BARKOD_SECURITY] %s | User: %d | IP: %s | %s',
            $event,
            get_current_user_id(),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $details
        ));
    }

    // Rate limiting helper
    private function check_rate_limit(string $action): void {
        $user_id = get_current_user_id();
        $transient_key = 'barkod_rate_limit_' . $action . '_' . $user_id;
        $attempts = get_transient($transient_key);
        
        if ($attempts && $attempts >= self::RATE_LIMIT_MAX_ATTEMPTS) {
            $this->log_security_event('RATE_LIMIT_EXCEEDED', "Action: {$action}");
            wp_send_json_error('Çok fazla deneme yaptınız. Lütfen 1 dakika bekleyin.');
        }
        
        set_transient($transient_key, ($attempts ? $attempts + 1 : 1), self::RATE_LIMIT_WINDOW);
    }

    // Input validation helpers
    private function validate_phone(string $phone): bool {
    // Başında 0, 90 veya direkt 5 ile başlayan ve toplam 10-12 haneli rakamlar
    return preg_match('/^(0|90|5)\d{9,10}$/', $phone) === 1;
}


    private function validate_email(?string $email): bool {
    // Eğer email boş ise (null, "", ya da sadece boşluk) → geçerli say
    if (empty(trim($email))) {
        return true;
    }

    // Doluysa normal validasyon
    return is_email($email) && strlen($email) <= self::EMAIL_MAX_LENGTH;
	}

    private function validate_username(string $username): bool {
    // Başta/sonda boşlukları temizle
    $username = trim($username);

    return strlen($username) >= self::USERNAME_MIN_LENGTH
        && strlen($username) <= self::USERNAME_MAX_LENGTH
        && preg_match('/^[a-zA-Z0-9_ ]+$/', $username) === 1;
	}
	
	private function normalize_phone(string $phone): string {
    // Boşluk veya tireleri temizle
    $phone = preg_replace('/\D/', '', $phone);

    // 05XXXXXXXXX -> 5XXXXXXXXX
    if (substr($phone, 0, 2) === '05') {
        $phone = substr($phone, 1); // baştaki 0'ı çıkar
    }

    // 5XXXXXXXXX -> 90XXXXXXXXX
    if (substr($phone, 0, 1) === '5') {
        $phone = '90' . $phone;
    }

    // 90XXXXXXXXX zaten doğruysa değiştirme
    return $phone;
}

    /**
     * Validate donation points amount
     * Requirements: 4.4
     * 
     * Checks:
     * - Points must be positive
     * - Points must not exceed user's balance
     * 
     * @param int $points Points to donate
     * @param int $user_balance User's current points balance
     * @return bool True if valid, false otherwise
     */
    private function validate_donation_points(int $points, int $user_balance): bool {
        return $points > 0 && $points <= $user_balance;
    }
    
    /**
     * Validate kumbara ID with Woof Kumbara plugin
     * Requirements: 4.4, 6.1
     * 
     * Checks:
     * - Kumbara ID must be positive
     * - Kumbara must exist in Woof Kumbara system
     * - Kumbara must be active
     * 
     * @param int $kumbara_id Kumbara ID to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_kumbara_id(int $kumbara_id): bool {
        if ($kumbara_id <= 0) {
            return false;
        }
        
        $kumbara_manager = new Barkod_Kumbara_Manager();
        $kumbara = $kumbara_manager->get_kumbara($kumbara_id);
        
        if (!$kumbara) {
            return false;
        }
        
        // Check if kumbara is active
        return $kumbara['is_active'] === true;
    }
    
    /**
     * Validate customer ID with Points and Rewards system
     * Requirements: 4.4, 6.1
     * 
     * Checks:
     * - Customer ID must be positive
     * - Customer must exist in WordPress
     * - Customer must have wps_wpr_points meta (Points and Rewards integration)
     * 
     * @param int $customer_id Customer ID to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_customer_id(int $customer_id): bool {
        if ($customer_id <= 0) {
            return false;
        }
        
        $customer = get_userdata($customer_id);
        
        if (!$customer) {
            return false;
        }
        
        // Check if customer has Points and Rewards meta
        // This confirms integration with Points and Rewards for WooCommerce plugin
        $has_points_meta = metadata_exists('user', $customer_id, 'wps_wpr_points');
        
        return $has_points_meta;
    }
    
    /**
     * Validate barcode format
     * Requirements: 1.3
     * 
     * Note: This method already exists but is documented here for completeness
     * Barkod validation is already implemented in the class
     * 
     * Checks:
     * - Length between 8-20 characters
     * - Only numeric characters
     * 
     * @param string $barcode Barcode to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_barcode(string $barcode): bool {
        if (empty($barcode)) {
            return false;
        }
        
        $length = strlen($barcode);
        
        if ($length < self::BARCODE_MIN_LENGTH || $length > self::BARCODE_MAX_LENGTH) {
            return false;
        }
        
        // Only numeric characters allowed
        return preg_match('/^[0-9]+$/', $barcode) === 1;
    }

    
    public function pos_kullanici_olustur(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Yetkisiz işlem');
        }

        check_ajax_referer('barkod_sistemi_nonce', 'nonce');
        $this->check_rate_limit('create_user');

        $username = sanitize_user($_POST['username'] ?? '');
        $email    = sanitize_email($_POST['email'] ?? '');
        $phone    = sanitize_text_field($_POST['phone'] ?? '');

        if (!$this->validate_username($username)) {
            wp_send_json_error('Geçersiz kullanıcı adı (3-60 karakter, sadece harf, rakam, boşluk ve alt çizgi)');
        }

        if (!empty($email) && !$this->validate_email($email)) {
            wp_send_json_error('Geçersiz e-posta adresi');
        }

        if (!$this->validate_phone($phone)) {
            wp_send_json_error('Geçersiz telefon numarası (10 rakam gerekli)');
        }

        $normalized_phone = $this->normalize_phone($phone);

        if (!empty($email) && email_exists($email)) {
            wp_send_json_error('Bu e-posta adresi zaten kayıtlı');
        }

        $existing_users = get_users([
            'meta_key'   => 'billing_phone',
            'meta_value' => $normalized_phone,
            'number'     => 1,
            'fields'     => 'ID'
        ]);

        if (!empty($existing_users)) {
            wp_send_json_error('Bu telefon numarası zaten kayıtlı');
        }

        if (username_exists($username)) {
            wp_send_json_error('Bu kullanıcı adı zaten kullanılıyor');
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => !empty($email) ? $email : '',
            'user_pass'  => wp_generate_password(12, false),
            'role'       => 'customer'
        ]);

        if (is_wp_error($user_id)) {
            error_log('[BARKOD_USER_CREATE_ERROR] ' . $user_id->get_error_message());
            wp_send_json_error('Kullanıcı oluşturulamadı: ' . $user_id->get_error_message());
        }

        update_user_meta($user_id, 'billing_phone', $normalized_phone);
        update_user_meta($user_id, 'wps_wpr_points', 0);

        wp_send_json_success('Kullanıcı başarıyla oluşturuldu');
    }


    public function musteri_tel_ara_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            $this->log_security_event('UNAUTHORIZED_ACCESS', 'musteri_tel_ara');
            wp_send_json_error('Yetkisiz işlem');
        }
        check_ajax_referer('barkod_sistemi_nonce', 'nonce');
        $this->check_rate_limit('search_customer');

        $telefon = sanitize_text_field($_POST['telefon'] ?? '');

        if (empty($telefon) || !$this->validate_phone($telefon)) {
            wp_send_json_error('Geçerli bir telefon numarası girin (10 rakam)');
        }
        $telefon = $this->normalize_phone($telefon);

        $users = get_users([
            'meta_query' => [
                [
                    'key'     => 'billing_phone',
                    'value'   => $telefon,
                    'compare' => '='
                ]
            ],
            'number' => 1,
        ]);

        if (!empty($users)) {
            $user = $users[0];
            $puan = intval(get_user_meta($user->ID, 'wps_wpr_points', true));

            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'limit'       => 20,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ]);

            $siparisler = [];
            $urun_sayaci = 0;
            foreach ($orders as $order) {
                if ($urun_sayaci >= 5) break;

                foreach ($order->get_items() as $item) {
                    if ($urun_sayaci >= 5) break;

                    $product = $item->get_product();
                    if ($product) {
                        $siparisler[] = [
                            'urun_adi'    => $product->get_name(),
                            'urun_fiyati' => $item->get_total(),
                            'tarih'       => $order->get_date_created()->date('d.m.Y H:i'),
                        ];
                        $urun_sayaci++;
                    }
                }
            }

            wp_send_json_success([
                'display_name'   => $user->display_name,
                'user_email'     => $user->user_email,
                'ID'             => $user->ID,
                'wps_wpr_points' => $puan,
                'siparisler'     => $siparisler,
            ]);
        } else {
            wp_send_json_error('Müşteri bulunamadı');
        }
    }

    public function urun_barkod_ara_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            $this->log_security_event('UNAUTHORIZED_ACCESS', 'urun_barkod_ara');
            wp_send_json_error('Yetkisiz işlem');
        }
        check_ajax_referer('barkod_sistemi_nonce', 'nonce');
        $this->check_rate_limit('search_product');

        $barkod = isset($_POST['barkod']) ? trim(sanitize_text_field($_POST['barkod'])) : '';
        
        if (empty($barkod) || strlen($barkod) < self::BARCODE_MIN_LENGTH || strlen($barkod) > self::BARCODE_MAX_LENGTH) {
            wp_send_json_error('Geçersiz barkod formatı (8-20 karakter olmalı)');
        }

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_sku',
                    'value'   => $barkod,
                    'compare' => '='
                ],
            ],
        ];

        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            
            if (!$product->is_in_stock()) {
                wp_reset_postdata();
                wp_send_json_error('Ürün stokta yok');
            }

            $expiration_date = $product->get_meta('_expiration_date') ?: '';
            
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price();
            $current_price = $product->get_price();
            $discount_percentage = 0;
            
            if ($product->is_on_sale() && $regular_price > 0) {
                $discount_percentage = round((($regular_price - $sale_price) / $regular_price) * 100);
            }
            
            $urun_bilgisi = [
                'id'               => $product->get_id(),
                'baslik' 		   => $product->get_name(),
                'resim'            => wp_get_attachment_image_src($product->get_image_id(), 'thumbnail')[0] ?? '',
                'fiyat'            => $current_price,
                'eski_fiyat'       => $regular_price,
                'yeni_fiyat'       => $sale_price ?: $regular_price,
                'indirim_yuzdesi'  => $discount_percentage,
                'edit_url' => admin_url('post.php?post=' . $product->get_id() . '&action=edit'),
                'stok'             => $product->managing_stock() ? $product->get_stock_quantity() : null,
                'skt'              => $expiration_date,
            ];
            
            wp_reset_postdata();
            wp_send_json_success($urun_bilgisi);
        } else {
            wp_send_json_error('Ürün bulunamadı');
        }
    }

    /**
     * Hızlı satış modu için ürün ekleme AJAX handler
     * Requirements: 1.1, 1.5
     * 
     * Güvenlik kontrolleri:
     * - Nonce validation
     * - Yetki kontrolü
     * - Rate limiting
     * 
     * İşlem akışı:
     * 1. Barkod validasyonu
     * 2. Ürün arama (SKU ile)
     * 3. Stok kontrolü
     * 4. JSON response döndürme
     */
    public function hizli_satis_urun_ekle_callback(): void {
        try {
            // Yetki kontrolü
            if (!current_user_can('manage_woocommerce')) {
                $this->log_security_event('UNAUTHORIZED_ACCESS', 'hizli_satis_urun_ekle');
                throw new Barkod_Authorization_Exception('Yetkisiz işlem');
            }
            
            // Nonce kontrolü
            check_ajax_referer('barkod_sistemi_nonce', 'nonce');
            
            // Rate limiting
            $this->check_rate_limit('hizli_satis_urun_ekle');
            
            // Barkod al ve temizle
            $barkod = isset($_POST['barkod']) ? trim(sanitize_text_field($_POST['barkod'])) : '';
            
            // Barkod validasyonu
            // Requirement 1.3: Geçersiz barkod kontrolü
            if (!$this->validate_barcode($barkod)) {
                throw new Barkod_Validation_Exception('Geçersiz barkod formatı (8-20 karakter, sadece rakam)', 'invalid_barcode');
            }
            
            // Ürün arama (SKU ile)
            // Requirement 1.1: Barkod ile ürün arama
            $args = [
                'post_type'      => 'product',
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'     => '_sku',
                        'value'   => $barkod,
                        'compare' => '='
                    ],
                ],
            ];
            
            $query = new WP_Query($args);
            
            if (!$query->have_posts()) {
                throw new Barkod_Product_Exception('Ürün bulunamadı', 'product_not_found');
            }
            
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            
            // Stok kontrolü
            // Requirement 1.5: Stokta olmayan ürünleri sepete ekleme
            if (!$product->is_in_stock()) {
                wp_reset_postdata();
                throw new Barkod_Product_Exception('Ürün stokta yok', 'out_of_stock');
            }
            
            // Stok miktarı kontrolü (managing_stock aktifse)
            if ($product->managing_stock() && $product->get_stock_quantity() <= 0) {
                wp_reset_postdata();
                throw new Barkod_Product_Exception('Ürün stokta yok', 'out_of_stock');
            }
            
            // Ürün bilgilerini hazırla
            $current_price = $product->get_price();
            
            $urun_bilgisi = [
                'id'      => $product->get_id(),
                'baslik'  => $product->get_name(),
                'fiyat'   => floatval($current_price),
                'stok'    => $product->managing_stock() ? $product->get_stock_quantity() : null,
            ];
            
            wp_reset_postdata();
            
            // Başarı logu
            $this->logger->info(
                'Hızlı satış: Ürün sepete eklendi',
                [
                    'product_id' => $urun_bilgisi['id'],
                    'barcode' => $barkod,
                    'product_name' => $urun_bilgisi['baslik']
                ],
                Barkod_Logger::CATEGORY_SALE
            );
            
            // Başarılı response
            // Requirement 1.1: JSON response döndürme
            wp_send_json_success($urun_bilgisi);
            
        } catch (Barkod_Validation_Exception $e) {
            $this->logger->warning(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_VALIDATION
            );
            wp_send_json_error($e->getMessage());
            
        } catch (Barkod_Authorization_Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_SECURITY
            );
            wp_send_json_error($e->getMessage());
            
        } catch (Barkod_Product_Exception $e) {
            $this->logger->debug(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_SALE
            );
            wp_send_json_error($e->getMessage());
            
        } catch (Exception $e) {
            $this->logger->critical(
                'Hızlı satış hatası: ' . $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
                Barkod_Logger::CATEGORY_ERROR
            );
            wp_send_json_error('Beklenmeyen bir hata oluştu');
        }
    }

    /**
     * Barkod basma AJAX handler
     * Requirements: 2.4, 2.5, 6.1, 6.2, 6.3
     * 
     * İşlem akışı:
     * 1. Yetki kontrolü (manage_woocommerce)
     * 2. Ürün ID ve adet validasyonu
     * 3. Barkod yazdırma işlemi (simüle edilmiş)
     * 4. Stok miktarı artırma
     * 5. İşlem loguna kayıt
     * 6. Başarı/hata response
     */
    public function barkod_bas_callback(): void {
        try {
            // Yetki kontrolü
            // Requirement 6.1: Sadece yetkili kullanıcılar barkod basabilir
            if (!current_user_can('manage_woocommerce')) {
                $this->log_security_event('UNAUTHORIZED_ACCESS', 'barkod_bas');
                throw new Barkod_Authorization_Exception('Yetkisiz işlem');
            }
            
            // Nonce kontrolü
            check_ajax_referer('barkod_sistemi_nonce', 'nonce');
            
            // Rate limiting
            $this->check_rate_limit('barkod_bas');
            
            // Parametreleri al ve validate et
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
            
            // Validasyon
            if ($product_id <= 0) {
                throw new Barkod_Validation_Exception('Geçersiz ürün ID', 'invalid_product_id');
            }
            
            if ($quantity <= 0 || $quantity > 1000) {
                throw new Barkod_Validation_Exception('Barkod adedi 1-1000 arasında olmalıdır', 'invalid_quantity');
            }
            
            // Ürünü kontrol et
            $product = wc_get_product($product_id);
            if (!$product) {
                throw new Barkod_Product_Exception('Ürün bulunamadı', 'product_not_found');
            }
            
            // Barkod yazdırma işlemi
            // Requirement 2.4: Barkod yazdırma işlemi
            $print_result = $this->print_barcode_to_printer($product, $quantity);
            
            if (!$print_result['success']) {
                throw new Barkod_Exception($print_result['message'], 'print_failed');
            }
            
            // Stok miktarını artır
            // Requirement 2.5: Stok miktarı güncelleme
            if ($product->managing_stock()) {
                $current_stock = $product->get_stock_quantity();
                $new_stock = $current_stock + $quantity;
                $product->set_stock_quantity($new_stock);
                $product->save();
                
                // Stok değişikliğini WooCommerce'e bildir
                do_action('woocommerce_product_set_stock', $product);
            } else {
                // Stok yönetimi kapalıysa, açalım ve stok ekleyelim
                $product->set_manage_stock(true);
                $product->set_stock_quantity($quantity);
                $product->set_stock_status('instock');
                $product->save();
            }
            
            // İşlem loguna kayıt
            // Requirement 6.2: İşlem kaydı
            $log_result = $this->log_barcode_printing($product_id, $quantity, get_current_user_id());
            
            if (!$log_result) {
                // Log hatası olsa bile işlem başarılı sayılır
                $this->logger->warning(
                    'Barkod basma log kaydı başarısız',
                    ['product_id' => $product_id, 'quantity' => $quantity],
                    Barkod_Logger::CATEGORY_BARCODE
                );
            }
            
            // Telegram bildirimi (opsiyonel)
            // Requirement 2.5: Bildirim gönderme
            $this->send_barcode_print_notification($product, $quantity);
            
            // Başarı logu
            $this->logger->info(
                'Barkod basma işlemi başarılı',
                [
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'quantity' => $quantity,
                    'new_stock' => $product->get_stock_quantity()
                ],
                Barkod_Logger::CATEGORY_BARCODE
            );
            
            // Başarı response
            // Requirement 6.3: Başarı/hata response
            wp_send_json_success([
                'message' => 'Barkod basma işlemi başarılı',
                'product_id' => $product_id,
                'quantity' => $quantity,
                'new_stock' => $product->get_stock_quantity()
            ]);
            
        } catch (Barkod_Validation_Exception $e) {
            $this->logger->warning(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_VALIDATION
            );
            wp_send_json_error($e->getMessage());
            
        } catch (Barkod_Authorization_Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_SECURITY
            );
            wp_send_json_error($e->getMessage());
            
        } catch (Barkod_Product_Exception $e) {
            $this->logger->warning(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_BARCODE
            );
            wp_send_json_error($e->getMessage());
            
        } catch (Exception $e) {
            $this->logger->critical(
                'Barkod basma hatası: ' . $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
                Barkod_Logger::CATEGORY_ERROR
            );
            wp_send_json_error('Barkod basma işlemi başarısız');
        }
    }
    

    



    /**
     * Satış tamamlama AJAX handler
     * Requirements: 1.1, 2.1
     * 
     * Entegrasyon:
     * - Mod bilgisini sipariş notuna ekler (Hızlı Satış / Geliştirici)
     * - Telegram bot bildirimi gönderir
     * - Puan sistemi ile entegre çalışır
     */
    public function satisi_tamamla_callback(): void {
        try {
            if (!current_user_can('manage_woocommerce')) {
                $this->log_security_event('UNAUTHORIZED_ACCESS', 'satisi_tamamla');
                wp_send_json_error('Yetkisiz işlem');
            }
            check_ajax_referer('barkod_sistemi_nonce', 'nonce');
            $this->check_rate_limit('complete_sale');

            $musteri_id = intval($_POST['musteri_id'] ?? 0);
            $urunler = json_decode(stripslashes($_POST['urunler'] ?? '[]'), true);
            $kullanilan_puan = intval($_POST['kullanilan_puan'] ?? 0);

            $satis_modu = isset($_POST['satis_modu']) ? sanitize_text_field($_POST['satis_modu']) : 'hizli';
            $mod_adi = ($satis_modu === 'gelistirici') ? 'Geliştirici Modu' : 'Hızlı Satış Modu';

            if (empty($urunler) || !is_array($urunler)) {
                wp_send_json_error('Sepet boş veya geçersiz');
            }

            $order = wc_create_order();
            if (is_wp_error($order)) {
                error_log('[BARKOD_SATIS_ERROR] Order creation failed: ' . $order->get_error_message());
                wp_send_json_error('Sipariş oluşturulamadı');
            }

            $toplam_tutar = 0;

            foreach ($urunler as $urun) {
                $product_id = intval($urun['id'] ?? 0);
                $adet = intval($urun['adet'] ?? 0);

                if ($product_id <= 0 || $adet <= 0) continue;

                $product = wc_get_product($product_id);
                if (!$product) continue;

                if (!$product->is_in_stock()) {
                    $order->delete(true);
                    wp_send_json_error('Ürün stokta yok: ' . $product->get_name());
                }

                if ($product->managing_stock() && !$product->has_enough_stock($adet)) {
                    $order->delete(true);
                    wp_send_json_error('Yetersiz stok: ' . $product->get_name());
                }

                $order->add_product($product, $adet);
                $toplam_tutar += (float) $product->get_price() * $adet;
            }

            if ($toplam_tutar <= 0) {
                $order->delete(true);
                wp_send_json_error('Sipariş tutarı geçersiz');
            }

            if ($musteri_id > 0) {
                $order->set_customer_id($musteri_id);
            }

            $puanIndirimTutar = 0;
            $puan_ekle = 0;

            if ($musteri_id > 0 && $kullanilan_puan > 0) {
                $mevcut_puan = (int) get_user_meta($musteri_id, 'wps_wpr_points', true);

                if ($kullanilan_puan > $mevcut_puan) {
                    $kullanilan_puan = $mevcut_puan;
                }

                $puanIndirimTutar = $kullanilan_puan * self::POINT_DISCOUNT_RATE;

                if ($puanIndirimTutar > $toplam_tutar) {
                    $puanIndirimTutar = $toplam_tutar;
                    $kullanilan_puan = (int) ceil($puanIndirimTutar / self::POINT_DISCOUNT_RATE);
                }
            }

            $order_toplam = max(0, $toplam_tutar - $puanIndirimTutar);

            if ($musteri_id > 0) {
                global $wpdb;
                $wpdb->query('START TRANSACTION');
                $eski_puan = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'wps_wpr_points' FOR UPDATE",
                    $musteri_id
                ));
                $puan_ekle = (int) floor($order_toplam * self::POINT_EARN_RATE);
                $yeni_puan = max(0, $eski_puan - $kullanilan_puan + $puan_ekle);
                update_user_meta($musteri_id, 'wps_wpr_points', $yeni_puan);
                $wpdb->query('COMMIT');

                if ($kullanilan_puan > 0) {
                    $order->add_order_note(sprintf('Kullanılan Puan: %d (İndirim: %s)', $kullanilan_puan, wc_price($puanIndirimTutar)));
                }
                $order->add_order_note(sprintf('Kazanılan Puan: %d', $puan_ekle));
            }

            $order->add_order_note(sprintf('Satış Modu: %s', $mod_adi));

            $order->set_payment_method('pos');
            $order->set_payment_method_title('POS Ödemesi');
            $order->update_meta_data('_is_pos_order', 'yes');
            $order->update_meta_data('_pos_sale_mode', $satis_modu);
            $order->update_meta_data('_iletimerkezi_sms_sent', 'yes');
            $order->update_meta_data('_skip_order_sms', 'yes');
            $order->save_meta_data();

            $order->set_status('completed');
            $order->calculate_totals();
            $order->set_total($order_toplam);
            $order->save();

            $this->log_security_event('SALE_COMPLETED', "Order ID: {$order->get_id()}, Total: {$order_toplam}, Mode: {$satis_modu}");

            $this->send_sale_notification($order, $musteri_id, $kullanilan_puan, $puan_ekle, $satis_modu);

            wp_send_json_success([
                'message'    => 'Satış tamamlandı',
                'siparis_id' => $order->get_id()
            ]);

        } catch (Exception $e) {
            error_log('[BARKOD_SATIS_ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }
            wp_send_json_error('Satış tamamlanamadı. Lütfen tekrar deneyin.');
        }
    }
    
    /**
     * Barkod basma geçmişi sayfası
     * Requirements: 6.4, 6.5
     */
    public function barkod_gecmisi_sayfasi(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'barkod-sistemi'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'barkod_basma_log';
        
        // Tablo var mı kontrol et
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            ?>
            <div class="pos-wrap">
                <div class="pos-header">
                    <h1 class="pos-title"><?php echo esc_html__('Barkod Basma Geçmişi', 'barkod-sistemi'); ?></h1>
                </div>
                <div class="notice notice-error">
                    <p>
                        <strong><?php echo esc_html__('Hata:', 'barkod-sistemi'); ?></strong>
                        <?php echo esc_html__('Barkod basma geçmişi tablosu bulunamadı. Lütfen plugin\'i devre dışı bırakıp tekrar etkinleştirin.', 'barkod-sistemi'); ?>
                    </p>
                    <p>
                        <em><?php echo esc_html__('Tablo adı:', 'barkod-sistemi'); ?> <?php echo esc_html($table_name); ?></em>
                    </p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Filtreleme parametreleri
        $filter_product = isset($_GET['filter_product']) ? intval($_GET['filter_product']) : 0;
        $filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
        $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
        
        // CSV dışa aktarma
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_barcode_history_csv($filter_product, $filter_user, $filter_date_from, $filter_date_to);
            return;
        }
        
        // Sayfalama
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // SQL sorgusu oluştur
        $where_clauses = ['1=1'];
        $where_values = [];
        
        if ($filter_product > 0) {
            $where_clauses[] = 'product_id = %d';
            $where_values[] = $filter_product;
        }
        
        if ($filter_user > 0) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $filter_user;
        }
        
        if (!empty($filter_date_from)) {
            $where_clauses[] = 'DATE(created_at) >= %s';
            $where_values[] = $filter_date_from;
        }
        
        if (!empty($filter_date_to)) {
            $where_clauses[] = 'DATE(created_at) <= %s';
            $where_values[] = $filter_date_to;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Toplam kayıt sayısı
        $total_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
        if (!empty($where_values)) {
            $total_query = $wpdb->prepare($total_query, $where_values);
        }
        $total_items = (int) $wpdb->get_var($total_query);
        
        // SQL hatası kontrolü
        if ($wpdb->last_error) {
            error_log('[BARKOD_HISTORY_ERROR] SQL Error: ' . $wpdb->last_error);
        }
        
        $total_pages = $total_items > 0 ? ceil($total_items / $per_page) : 1;
        
        // Kayıtları çek
        $query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $results = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        // SQL hatası kontrolü
        if ($wpdb->last_error) {
            error_log('[BARKOD_HISTORY_ERROR] SQL Error: ' . $wpdb->last_error);
        }
        
        // Kullanıcı listesi (filtre için)
        $users = get_users(['role' => 'administrator']);
        
        ?>
        <div class="pos-wrap">
            <div class="pos-header">
                <h1 class="pos-title" style="color: #263238 !important; opacity: 1 !important; visibility: visible !important;"><?php echo esc_html__('Barkod Geçmişi', 'barkod-sistemi'); ?></h1>
                <p class="pos-subtitle">Basılan tüm barkodların detaylı dökümü</p>
            </div>
            
            <!-- Filtreleme Formu -->
            <div class="pos-card" style="margin-bottom: 40px; background: var(--pos-secondary); border: none;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="barkod-gecmisi" />
                    
                    <div class="pos-grid" style="align-items: flex-end;">
                        <div class="pos-col-2">
                            <div class="pos-form-group" style="margin:0;">
                                <label class="pos-label" for="filter_product"><?php echo esc_html__('Ürün ID', 'barkod-sistemi'); ?></label>
                                <input type="number" id="filter_product" name="filter_product" value="<?php echo esc_attr($filter_product); ?>" class="pos-input" style="padding: 12px 20px;" />
                            </div>
                        </div>
                        
                        <div class="pos-col-3">
                            <div class="pos-form-group" style="margin:0;">
                                <label class="pos-label" for="filter_user"><?php echo esc_html__('Kullanıcı', 'barkod-sistemi'); ?></label>
                                <select id="filter_user" name="filter_user" class="pos-input" style="padding: 12px 20px;">
                                    <option value="0"><?php echo esc_html__('Tüm Kullanıcılar', 'barkod-sistemi'); ?></option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($filter_user, $user->ID); ?>>
                                            <?php echo esc_html($user->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="pos-col-2">
                            <div class="pos-form-group" style="margin:0;">
                                <label class="pos-label" for="filter_date_from"><?php echo esc_html__('Başlangıç', 'barkod-sistemi'); ?></label>
                                <input type="date" id="filter_date_from" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>" class="pos-input" style="padding: 12px 20px;" />
                            </div>
                        </div>
                        
                        <div class="pos-col-2">
                            <div class="pos-form-group" style="margin:0;">
                                <label class="pos-label" for="filter_date_to"><?php echo esc_html__('Bitiş', 'barkod-sistemi'); ?></label>
                                <input type="date" id="filter_date_to" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>" class="pos-input" style="padding: 12px 20px;" />
                            </div>
                        </div>
                        
                        <div class="pos-col-3">
                            <div class="d-flex gap-2">
                                <button type="submit" class="pos-btn pos-btn-primary" style="padding: 12px 20px; flex: 1;">
                                    <span class="dashicons dashicons-filter" style="margin-top: 4px;"></span> <?php echo esc_html__('Filtrele', 'barkod-sistemi'); ?>
                                </button>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=barkod-gecmisi')); ?>" class="pos-btn pos-btn-secondary" style="padding: 12px 20px;">
                                    <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="d-flex justify-between align-center mb-4">
                <h2 style="font-size: 1.5rem;"><?php echo esc_html__('İşlem Kayıtları', 'barkod-sistemi'); ?></h2>
                <a href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export' => 'csv']))); ?>" class="pos-btn pos-btn-secondary" style="border-color: var(--pos-success); color: var(--pos-success); padding: 10px 20px;">
                    <span class="dashicons dashicons-download"></span> <?php echo esc_html__('CSV Olarak İndir', 'barkod-sistemi'); ?>
                </a>
            </div>
            
            <!-- Tablo -->
            <div class="history-table-wrap">
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('ID', 'barkod-sistemi'); ?></th>
                            <th><?php echo esc_html__('Ürün', 'barkod-sistemi'); ?></th>
                            <th><?php echo esc_html__('SKU', 'barkod-sistemi'); ?></th>
                            <th><?php echo esc_html__('Adet', 'barkod-sistemi'); ?></th>
                            <th><?php echo esc_html__('Kullanıcı', 'barkod-sistemi'); ?></th>
                            <th><?php echo esc_html__('Tarih', 'barkod-sistemi'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--pos-text-muted);">
                                    <?php echo esc_html__('Kayıt bulunamadı.', 'barkod-sistemi'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results as $row): ?>
                                <?php
                                $product = wc_get_product($row->product_id);
                                $user = get_user_by('id', $row->user_id);
                                ?>
                                <tr>
                                    <td>#<?php echo esc_html($row->id); ?></td>
                                    <td>
                                        <?php if ($product): ?>
                                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $row->product_id . '&action=edit')); ?>" target="_blank" style="color: var(--pos-primary); font-weight: 600; text-decoration: none;">
                                                <?php echo esc_html($product->get_name()); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--pos-danger);"><?php echo esc_html__('Ürün bulunamadı', 'barkod-sistemi'); ?> (ID: <?php echo esc_html($row->product_id); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product ? esc_html($product->get_sku()) : '-'; ?></td>
                                    <td>
                                        <span style="background: var(--pos-bg-body); padding: 4px 10px; border-radius: 12px; font-weight: 600;">
                                            <?php echo esc_html($row->quantity); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="dashicons dashicons-admin-users" style="color: var(--pos-text-muted);"></span>
                                            <?php echo $user ? esc_html($user->display_name) : esc_html__('Bilinmeyen', 'barkod-sistemi'); ?>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($row->created_at))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Sayfalama -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        
                        if ($page_links) {
                            echo '<span class="displaying-num" style="margin-right: 10px; color: var(--pos-text-muted);">' . sprintf(
                                _n('%s kayıt', '%s kayıt', $total_items, 'barkod-sistemi'),
                                number_format_i18n($total_items)
                            ) . '</span>';
                            echo $page_links;
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Barkod geçmişini CSV olarak dışa aktar
     * Requirement 6.5: Dışa aktarma
     */
    private function export_barcode_history_csv(int $filter_product, int $filter_user, string $filter_date_from, string $filter_date_to): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'barkod_basma_log';
        
        // SQL sorgusu oluştur
        $where_clauses = ['1=1'];
        $where_values = [];
        
        if ($filter_product > 0) {
            $where_clauses[] = 'product_id = %d';
            $where_values[] = $filter_product;
        }
        
        if ($filter_user > 0) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $filter_user;
        }
        
        if (!empty($filter_date_from)) {
            $where_clauses[] = 'DATE(created_at) >= %s';
            $where_values[] = $filter_date_from;
        }
        
        if (!empty($filter_date_to)) {
            $where_clauses[] = 'DATE(created_at) <= %s';
            $where_values[] = $filter_date_to;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Tüm kayıtları çek
        $query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC";
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        $results = $wpdb->get_results($query);
        
        // CSV başlıkları
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=barkod-gecmisi-' . date('Y-m-d') . '.csv');
        
        // UTF-8 BOM ekle (Excel için)
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Başlık satırı
        fputcsv($output, ['ID', 'Ürün ID', 'Ürün Adı', 'SKU', 'Adet', 'Kullanıcı ID', 'Kullanıcı Adı', 'Tarih']);
        
        // Veri satırları
        foreach ($results as $row) {
            $product = wc_get_product($row->product_id);
            $user = get_user_by('id', $row->user_id);
            
            fputcsv($output, [
                $row->id,
                $row->product_id,
                $product ? $product->get_name() : 'Ürün bulunamadı',
                $product ? $product->get_sku() : '-',
                $row->quantity,
                $row->user_id,
                $user ? $user->display_name : 'Bilinmeyen',
                date_i18n('d.m.Y H:i', strtotime($row->created_at))
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Puan bağış AJAX handler
     * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
     * 
     * İşlem akışı:
     * 1. Güvenlik kontrolleri (nonce, yetki, rate limiting)
     * 2. Müşteri doğrulama
     * 3. Kumbara doğrulama
     * 4. Puan bakiyesi kontrolü
     * 5. Atomic transaction ile puan transferi
     * 6. İşlem loguna kayıt
     */
    public function puan_bagis_yap_callback(): void {
        try {
            // Yetki kontrolü
            if (!current_user_can('manage_woocommerce')) {
                $this->log_security_event('UNAUTHORIZED_ACCESS', 'puan_bagis_yap');
                throw new Barkod_Authorization_Exception('Yetkisiz işlem');
            }
            
            // Nonce kontrolü
            check_ajax_referer('barkod_sistemi_nonce', 'nonce');
            
            // Rate limiting
            $this->check_rate_limit('puan_bagis_yap');
            
            // Parametreleri al ve validate et
            $donor_user_id = isset($_POST['donor_user_id']) ? intval($_POST['donor_user_id']) : 0;
            $kumbara_id = isset($_POST['kumbara_id']) ? intval($_POST['kumbara_id']) : 0;
            $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
            
            // Validasyon
            // Requirement 4.1: Müşteri doğrulama
            if (!$this->validate_customer_id($donor_user_id)) {
                throw new Barkod_Validation_Exception('Geçersiz veya bulunamayan müşteri', 'invalid_customer');
            }
            
            $donor = get_userdata($donor_user_id);
            
            // Requirement 4.3: Kumbara doğrulama
            if (!$this->validate_kumbara_id($kumbara_id)) {
                throw new Barkod_Validation_Exception('Geçersiz veya aktif olmayan kumbara', 'invalid_kumbara');
            }
            
            // Kumbara manager'ı başlat
            $kumbara_manager = new Barkod_Kumbara_Manager();
            $kumbara = $kumbara_manager->get_kumbara($kumbara_id);
            
            // Requirement 4.4: Puan bakiyesi kontrolü
            $donor_points = (int) get_user_meta($donor_user_id, 'wps_wpr_points', true);
            
            if (!$this->validate_donation_points($points, $donor_points)) {
                throw new Barkod_Validation_Exception('Geçersiz bağış miktarı veya yetersiz puan bakiyesi', 'insufficient_points');
            }
            
            // Requirement 4.5: Atomic transaction ile puan transferi
            global $wpdb;
            
            // Transaction başlat
            $wpdb->query('START TRANSACTION');
            
            try {
                // 1. Bağışçıdan puan düş
                $new_donor_points = $donor_points - $points;
                $update_donor = update_user_meta($donor_user_id, 'wps_wpr_points', $new_donor_points);
                
                if ($update_donor === false) {
                    throw new Barkod_Database_Exception('Bağışçı puan güncellemesi başarısız');
                }
                
                // 2. Kumbaraya puan ekle
                $update_kumbara = $kumbara_manager->update_kumbara_points($kumbara_id, $points);
                
                if (!$update_kumbara) {
                    throw new Barkod_Database_Exception('Kumbara puan güncellemesi başarısız');
                }
                
                // 3. İşlem loguna kayıt
                $table_name = $wpdb->prefix . 'puan_bagis_log';
                $log_result = $wpdb->insert(
                    $table_name,
                    [
                        'donor_user_id' => $donor_user_id,
                        'kumbara_id' => $kumbara_id,
                        'points' => $points,
                        'created_at' => current_time('mysql')
                    ],
                    [
                        '%d',
                        '%d',
                        '%d',
                        '%s'
                    ]
                );
                
                if ($log_result === false) {
                    throw new Barkod_Database_Exception('Log kaydı başarısız');
                }
                
                // Transaction commit
                $wpdb->query('COMMIT');
                
                // Başarı logu
                $this->logger->info(
                    'Puan bağışı başarıyla tamamlandı',
                    [
                        'donor_user_id' => $donor_user_id,
                        'kumbara_id' => $kumbara_id,
                        'points' => $points,
                        'new_donor_balance' => $new_donor_points
                    ],
                    Barkod_Logger::CATEGORY_DONATION
                );
                
                // Requirement 5.1, 5.2: SMS bildirimleri gönder
                $this->send_donation_sms_notifications($donor, $kumbara, $points);
                
                // Requirement 4.5: Telegram bot bildirimi gönder
                $this->send_donation_telegram_notification($donor, $kumbara, $points);
                
                // Başarı response
                wp_send_json_success([
                    'message' => 'Puan bağışı başarıyla tamamlandı',
                    'donor_new_balance' => $new_donor_points,
                    'kumbara_new_total' => $kumbara['total_points'] + $points
                ]);
                
            } catch (Exception $e) {
                // Transaction rollback
                $wpdb->query('ROLLBACK');
                throw $e;
            }
            
        } catch (Barkod_Validation_Exception $e) {
            $this->logger->warning(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_VALIDATION
            );
            wp_send_json_error($e->getMessage());
            
        } catch (Barkod_Authorization_Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_SECURITY
            );
            wp_send_json_error($e->getMessage());
            
        } catch (Barkod_Database_Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                ['error_code' => $e->getErrorCode()],
                Barkod_Logger::CATEGORY_ERROR
            );
            wp_send_json_error('Puan bağışı işlemi başarısız: Veritabanı hatası');
            
        } catch (Exception $e) {
            $this->logger->critical(
                'Beklenmeyen hata: ' . $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                Barkod_Logger::CATEGORY_ERROR
            );
            wp_send_json_error('Beklenmeyen bir hata oluştu');
        }
    }
    
    /**
     * Send SMS notifications for donation
     * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
     * 
     * @param WP_User $donor Donor user object
     * @param array $kumbara Kumbara data
     * @param int $points Donation amount
     * @return void
     */
    private function send_donation_sms_notifications($donor, array $kumbara, int $points): void {
        $sms_service = new Barkod_SMS_Service();

        if (!$sms_service->is_enabled()) {
            return;
        }

        $donor_phone = get_user_meta($donor->ID, 'billing_phone', true);
        if (empty($donor_phone)) {
            $donor_phone = get_user_meta($donor->ID, 'phone', true);
        }
        if (empty($donor_phone)) {
            $donor_phone = get_user_meta($donor->ID, 'shipping_phone', true);
        }

        $owner_user_id = $kumbara['owner_user_id'];
        $owner = get_userdata($owner_user_id);
        $owner_phone = '';

        if (!empty($kumbara['telefon'])) {
            $owner_phone = $kumbara['telefon'];
        }

        if (empty($owner_phone) && $owner) {
            $owner_phone = get_user_meta($owner_user_id, 'billing_phone', true);
            if (empty($owner_phone)) {
                $owner_phone = get_user_meta($owner_user_id, 'phone', true);
            }
            if (empty($owner_phone)) {
                $owner_phone = get_user_meta($owner_user_id, 'shipping_phone', true);
            }
        }

        $owner_name = '';
        if ($owner) {
            $owner_name = $owner->display_name;
        } elseif (!empty($kumbara['ad']) || !empty($kumbara['soyad'])) {
            $owner_name = trim(($kumbara['ad'] ?? '') . ' ' . ($kumbara['soyad'] ?? ''));
        }

        $sms_data = [
            'donor_name'   => $donor->display_name,
            'donor_phone'  => $donor_phone,
            'owner_name'   => $owner_name,
            'owner_phone'  => $owner_phone,
            'kumbara_name' => $kumbara['name'],
            'tl'           => ($points / 100) * 2,
            'total_donate' => $kumbara['total_points'],
        ];

        if (!empty($donor_phone)) {
            if (!$sms_service->send_donation_sms_to_donor($sms_data)) {
                error_log("[BARKOD_SMS] Failed to send donor SMS to {$donor_phone}");
                $this->log_security_event('SMS_FAILED', "Failed to send donor SMS to {$donor_phone}");
            }
        } else {
            error_log("[BARKOD_SMS] Donor phone not found for user {$donor->ID}");
        }

        if (!empty($owner_phone)) {
            if (!$sms_service->send_donation_sms_to_owner($sms_data)) {
                error_log("[BARKOD_SMS] Failed to send owner SMS to {$owner_phone}");
                $this->log_security_event('SMS_FAILED', "Failed to send owner SMS to {$owner_phone}");
            }
        } else {
            error_log("[BARKOD_SMS] Owner phone not found for user {$owner_user_id}");
        }
    }
    
    /**
     * Send SMS notification when kumbara is activated
     * Requirements: 5.1, 5.5
     * 
     * This method is triggered by WordPress action hooks:
     * - barkod_kumbara_activated
     * - woof_kumbara_created
     * 
     * @param int $kumbara_id Kumbara ID
     * @return void
     */
	
	
	
	//Kumbara Aktif Olduğunda Buradan Aktif Olduğuna Dair Sms Gidiyor
	//
	//
	//
    public function send_kumbara_activation_sms(int $kumbara_id): void {
        $sms_service = new Barkod_SMS_Service();
        
        // Check if SMS service is enabled
        if (!$sms_service->is_enabled()) {
            error_log('[BARKOD_SMS] SMS service is disabled for kumbara activation');
            return;
        }
        
        // Get kumbara details
        $kumbara_manager = new Barkod_Kumbara_Manager();
        $kumbara = $kumbara_manager->get_kumbara($kumbara_id);
        
        if (!$kumbara) {
            error_log("[BARKOD_SMS] Kumbara not found for activation SMS (ID: {$kumbara_id})");
            return;
        }
        
        // Get owner details
        $owner_user_id = $kumbara['owner_user_id'];
        $owner = get_userdata($owner_user_id);
        
        if (!$owner) {
            error_log("[BARKOD_SMS] Owner not found for kumbara activation SMS (User ID: {$owner_user_id})");
            return;
        }
        
        // Get owner phone
        $owner_phone = get_user_meta($owner_user_id, 'billing_phone', true);
        if (empty($owner_phone)) {
            $owner_phone = get_user_meta($owner_user_id, 'phone', true);
        }
        
        // Also check kumbara table phone field
        if (empty($owner_phone) && !empty($kumbara['telefon'])) {
            $owner_phone = $kumbara['telefon'];
        }
        
        if (empty($owner_phone)) {
            error_log("[BARKOD_SMS] Owner phone number not found for kumbara activation (User ID: {$owner_user_id})");
            return;
        }
        
        // Prepare SMS message
        $template = get_option(
            'barkod_sms_kumbara_activation_template',
            'Sayın {owner_name}, {kumbara_name} kumbaranız başarıyla oluşturuldu ve aktif edildi. Artık bağış almaya hazırsınız! - {shop_name}'
        );
        
        $shop_name = get_bloginfo('name');
        
        $replacements = [
            '{owner_name}' => $owner->display_name,
            '{kumbara_name}' => $kumbara['name'],
            '{shop_name}' => $shop_name,
            '{date}' => current_time('d.m.Y'),
            '{time}' => current_time('H:i')
        ];
        
        $message = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
        
        // Allow filtering the message
        $message = apply_filters('barkod_sms_kumbara_activation_message', $message, $kumbara, $owner);
        
        // Send SMS using the SMS service
        $sms_data = [
            'owner_name' => $owner->display_name,
            'owner_phone' => $owner_phone,
            'kumbara_name' => $kumbara['name']
        ];
        
        try {
            $sms_sent = $sms_service->send_sms($owner_phone, $message, 'kumbara_activation');
            
            if ($sms_sent) {
                error_log("[BARKOD_SMS] Kumbara activation SMS sent successfully to {$owner_phone} (Kumbara ID: {$kumbara_id})");
            } else {
                // Requirement 5.5: Log failed SMS
                error_log("[BARKOD_SMS] Failed to send kumbara activation SMS to {$owner_phone} (Kumbara ID: {$kumbara_id})");
                $this->log_security_event('SMS_FAILED', "Failed to send kumbara activation SMS to {$owner_phone}");
            }
        } catch (Exception $e) {
            error_log("[BARKOD_SMS] Exception sending kumbara activation SMS: " . $e->getMessage());
            $this->log_security_event('SMS_FAILED', "Exception sending kumbara activation SMS: " . $e->getMessage());
        }
    }
    
    /**
     * Kumbara listesi AJAX handler
     * Requirements: 8.1, 8.2, 8.4, 8.5
     * 
     * İşlem akışı:
     * 1. Güvenlik kontrolleri
     * 2. Kumbara manager'dan aktif kumbaraları çek
     * 3. Alfabetik sıralama
     * 4. JSON response döndür
     */
    public function kumbara_listesi_getir_callback(): void {
        // Yetki kontrolü
        if (!current_user_can('manage_woocommerce')) {
            $this->log_security_event('UNAUTHORIZED_ACCESS', 'kumbara_listesi_getir');
            wp_send_json_error('Yetkisiz işlem');
        }
        
        // Nonce kontrolü
        check_ajax_referer('barkod_sistemi_nonce', 'nonce');
        
        // Rate limiting
        $this->check_rate_limit('kumbara_listesi_getir');
        
        // Arama parametresi (opsiyonel)
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        // Kumbara manager'ı başlat
        $kumbara_manager = new Barkod_Kumbara_Manager();
        
        // Aktif kumbaraları çek
        // Requirement 8.1, 8.2: Aktif kumbaraları listele
        $args = [
            'search' => $search,
            'orderby' => 'name',  // Requirement 8.4: Alfabetik sıralama
            'order' => 'ASC',
            'limit' => 100
        ];
        
        $kumbaras = $kumbara_manager->list_active_kumbaras($args);
        
        if (empty($kumbaras)) {
            wp_send_json_success([]);
        }
        
        // Her kumbara için gerekli bilgileri hazırla
        // Requirement 8.5: id, name, description, total_points
        $formatted_kumbaras = array_map(function($kumbara) {
            return [
                'id' => $kumbara['id'],
                'name' => $kumbara['name'],
                'description' => $kumbara['description'] ?? '',
                'total_points' => $kumbara['total_points']
            ];
        }, $kumbaras);
        
        // Başarı logu
        $this->log_security_event(
            'KUMBARA_LIST_FETCHED',
            "Count: " . count($formatted_kumbaras)
        );
        
        // JSON response
        wp_send_json_success($formatted_kumbaras);
    }
    
    /**
     * Send sale notification to Telegram bot
     * Requirements: 1.1, 2.1
     * 
     * @param WC_Order $order Order object
     * @param int $customer_id Customer ID
     * @param int $points_used Points used in sale
     * @param int $points_earned Points earned from sale
     * @param string $sale_mode Sale mode (hizli/gelistirici)
     * @return void
     */
    private function send_sale_notification($order, int $customer_id, int $points_used, int $points_earned, string $sale_mode): void {
        $telegram_bot = new Barkod_Telegram_Bot();
        
        if (!$telegram_bot->is_configured()) {
            return;
        }
        
        $customer_name = '';
        if ($customer_id > 0) {
            $customer = get_userdata($customer_id);
            if ($customer) {
                $customer_name = $customer->display_name;
            }
        }
        
        $mode_label = ($sale_mode === 'gelistirici') ? 'Geliştirici Modu' : 'Hızlı Satış Modu';
        
        $data = [
            'order_id' => $order->get_id(),
            'total' => $order->get_total(),
            'customer' => $customer_name,
            'points_used' => $points_used,
            'points_earned' => $points_earned,
            'sale_mode' => $mode_label
        ];
        
        $telegram_bot->send_sale_notification($data);
    }
    
    /**
     * Prevent automatic SMS sending for POS orders
     * POS siparişlerinde otomatik SMS gönderimini engelle
     * 
     * Bu fonksiyon ekstra güvenlik için tutuluyor.
     * Meta'lar zaten sipariş oluşturulurken ekleniyor.
     * 
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     * @return void
     */
    public function prevent_pos_order_sms(int $order_id, $order): void {
        // POS siparişi mi kontrol et
        $is_pos_order = $order->get_meta('_is_pos_order');
        
        if ($is_pos_order === 'yes') {
            // Eğer meta'lar eksikse ekle (ekstra güvenlik)
            if (!$order->get_meta('_iletimerkezi_sms_sent')) {
                $order->update_meta_data('_iletimerkezi_sms_sent', 'yes');
            }
            if (!$order->get_meta('_skip_order_sms')) {
                $order->update_meta_data('_skip_order_sms', 'yes');
            }
            $order->save_meta_data();
        }
    }
    
    /**
     * Block SMS sending for POS orders (İletiMerkezi plugin filter)
     * İletiMerkezi SMS plugin'i için POS siparişlerinde SMS engelleme
     * 
     * @param bool $should_send Whether SMS should be sent
     * @param int|WC_Order $order Order ID or object
     * @return bool False if POS order, original value otherwise
     */
    public function block_pos_order_sms(bool $should_send, $order): bool {
        // Order object'e dönüştür
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }
        
        if (!$order) {
            return $should_send;
        }
        
        // POS siparişi ise SMS gönderme
        $is_pos_order = $order->get_meta('_is_pos_order');
        if ($is_pos_order === 'yes') {
            error_log('[BARKOD_SMS_BLOCK] POS siparişi için SMS engellendi - Sipariş ID: ' . $order->get_id());
            return false;
        }
        
        return $should_send;
    }
    
    /**
     * Disable completed order email for POS orders (optional)
     * POS siparişleri için tamamlanma e-postasını devre dışı bırak (opsiyonel)
     * 
     * @param bool $enabled Whether email is enabled
     * @param WC_Order $order Order object
     * @return bool False if POS order, original value otherwise
     */
    public function maybe_disable_completed_email(bool $enabled, $order): bool {
        if (!$order) {
            return $enabled;
        }
        
        // POS siparişi ise e-posta gönderme (opsiyonel)
        $is_pos_order = $order->get_meta('_is_pos_order');
        if ($is_pos_order === 'yes') {
            // E-posta göndermek istiyorsanız bu satırı yoruma alın
            // return false;
        }
        
        return $enabled;
    }
    
    /**
     * Send barcode print notification to Telegram bot
     * Requirements: 2.5
     * 
     * @param WC_Product $product Product object
     * @param int $quantity Quantity of barcodes printed
     * @return void
     */
    private function send_barcode_print_notification($product, int $quantity): void {
        $telegram_bot = new Barkod_Telegram_Bot();
        
        if (!$telegram_bot->is_configured()) {
            return;
        }
        
        $message = "🖨️ <b>Barkod Basıldı!</b>\n\n";
        $message .= "📦 Ürün: " . esc_html($product->get_name()) . "\n";
        $message .= "🔢 Barkod: " . esc_html($product->get_sku()) . "\n";
        $message .= "📊 Adet: " . (int)$quantity . "\n";
        $message .= "📈 Yeni Stok: " . ($product->managing_stock() ? $product->get_stock_quantity() : 'Takip edilmiyor') . "\n";
        $message .= "👤 Kullanıcı: " . wp_get_current_user()->display_name . "\n";
        $message .= "\n⏰ " . current_time('d.m.Y H:i');
        
        $telegram_bot->send_message($message);
    }
    
    /**
     * Print barcode to printer (simulated)
     * Requirements: 2.4
     * 
     * @param WC_Product $product Product object
     * @param int $quantity Quantity of barcodes to print
     * @return array Result array with success status and message
     */
    private function print_barcode_to_printer($product, int $quantity): array {
        // Simulated barcode printing
        // In a real implementation, this would interface with a physical printer
        // or generate PDF labels for printing
        
        $barcode = $product->get_sku();
        $product_name = $product->get_name();
        
        // Log the print request
        error_log(sprintf(
            '[BARKOD_PRINT] Printing %d barcodes for product: %s (SKU: %s)',
            $quantity,
            $product_name,
            $barcode
        ));
        
        // Simulate successful printing
        // In production, you would:
        // 1. Connect to printer API
        // 2. Generate barcode image/PDF
        // 3. Send to printer
        // 4. Wait for confirmation
        
        return [
            'success' => true,
            'message' => 'Barkod yazdırma işlemi başarılı',
            'quantity' => $quantity
        ];
    }
    
    /**
     * Log barcode printing operation
     * Requirements: 6.2
     * 
     * @param int $product_id Product ID
     * @param int $quantity Quantity printed
     * @param int $user_id User ID who performed the operation
     * @return bool Success status
     */
    private function log_barcode_printing(int $product_id, int $quantity, int $user_id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'barkod_basma_log';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'user_id' => $user_id,
                'created_at' => current_time('mysql')
            ],
            [
                '%d',
                '%d',
                '%d',
                '%s'
            ]
        );
        
        return $result !== false;
    }
    
    /**
     * Send point donation notification to Telegram bot
     * Requirements: 4.5
     * 
     * @param WP_User $donor Donor user object
     * @param array $kumbara Kumbara data
     * @param int $points Donation amount
     * @return void
     */
    private function send_donation_telegram_notification($donor, array $kumbara, int $points): void {
        $telegram_bot = new Barkod_Telegram_Bot();
        
        if (!$telegram_bot->is_configured()) {
            return;
        }
        
        // Get kumbara owner
        $owner_user_id = $kumbara['owner_user_id'];
        $owner = get_userdata($owner_user_id);
        $owner_name = $owner ? $owner->display_name : 'Bilinmeyen';
        
        $message = "💝 <b>Puan Bağışı Yapıldı!</b>\n\n";
        $message .= "👤 Bağışçı: " . esc_html($donor->display_name) . "\n";
        $message .= "🎯 Kumbara: " . esc_html($kumbara['name']) . "\n";
        $message .= "👥 Kumbara Sahibi: " . esc_html($owner_name) . "\n";
        $message .= "⭐ Bağış Miktarı: " . number_format($points) . " puan\n";
        $message .= "📊 Kumbara Toplam: " . number_format($kumbara['total_points'] + $points) . " puan\n";
        $message .= "\n⏰ " . current_time('d.m.Y H:i');
        
        $telegram_bot->send_message($message);
    }
    
    /**
     * SMS Test & Ayarlar Sayfası
     */
    public function sms_test_sayfasi(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'barkod-sistemi'));
        }
        
        // İşlem yapılacak mı?
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $message = '';
        $message_type = '';
        
        if ($action === 'disable_limits' && check_admin_referer('barkod_sms_action', 'barkod_sms_nonce')) {
            // Spam limitlerini devre dışı bırak
            $sms_options = get_option('iletimerkezi_sms_options', array());
            $sms_options['sms_hourly_limit'] = 0;
            $sms_options['sms_daily_limit'] = 0;
            update_option('iletimerkezi_sms_options', $sms_options);
            
            $message = 'Spam limitleri başarıyla devre dışı bırakıldı!';
            $message_type = 'success';
        }
        
        if ($action === 'increase_limits' && check_admin_referer('barkod_sms_action', 'barkod_sms_nonce')) {
            // Spam limitlerini artır
            $sms_options = get_option('iletimerkezi_sms_options', array());
            $sms_options['sms_hourly_limit'] = 100;
            $sms_options['sms_daily_limit'] = 500;
            update_option('iletimerkezi_sms_options', $sms_options);
            
            $message = 'Spam limitleri başarıyla artırıldı! (100 SMS/saat, 500 SMS/gün)';
            $message_type = 'success';
        }
        
        if ($action === 'clear_logs' && check_admin_referer('barkod_sms_action', 'barkod_sms_nonce')) {
            // Son 24 saatteki logları temizle
            global $wpdb;
            $table_name = $wpdb->prefix . 'iletimerkezi_sms_logs';
            
            $deleted = $wpdb->query(
                "DELETE FROM $table_name WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            
            $message = 'Son 24 saatteki ' . $deleted . ' SMS log kaydı temizlendi!';
            $message_type = 'success';
        }
        
        if ($action === 'enable_sms' && check_admin_referer('barkod_sms_action', 'barkod_sms_nonce')) {
            // SMS servisini etkinleştir
            update_option('barkod_sms_enabled', '1');
            
            $message = 'Barkod SMS servisi etkinleştirildi!';
            $message_type = 'success';
        }
        
        // Mevcut ayarları al
        $barkod_sms_enabled = get_option('barkod_sms_enabled', '0');
        $barkod_sms_provider = get_option('barkod_sms_provider', 'iletimerkezi');
        $sms_options = get_option('iletimerkezi_sms_options', array());
        
        ?>
        <div class="wrap">
            <h1>📱 SMS Test & Ayarlar</h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card" style="max-width: none;">
                <h2>1. Barkod SMS Ayarları</h2>
                <table class="form-table">
                    <tr>
                        <th>SMS Servisi Durumu:</th>
                        <td>
                            <?php if ($barkod_sms_enabled === '1'): ?>
                                <span style="color: #28a745; font-weight: bold;">✓ ETKİN</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-weight: bold;">✗ DEVRE DIŞI</span>
                                <p class="description">
                                    <a href="<?php echo wp_nonce_url(add_query_arg('action', 'enable_sms'), 'barkod_sms_action', 'barkod_sms_nonce'); ?>" class="button button-primary">
                                        SMS Servisini Etkinleştir
                                    </a>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>SMS Sağlayıcı:</th>
                        <td><?php echo esc_html($barkod_sms_provider); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: none; margin-top: 20px;">
                <h2>2. İletiMerkezi SMS Ayarları</h2>
                <?php if (empty($sms_options)): ?>
                    <div class="notice notice-error inline">
                        <p><strong>✗ İletiMerkezi SMS ayarları bulunamadı!</strong></p>
                        <p>İletiMerkezi SMS plugin'ini yükleyin ve ayarlarını yapılandırın.</p>
                    </div>
                <?php else: ?>
                    <table class="form-table">
                        <tr>
                            <th>API Key:</th>
                            <td>
                                <?php if (!empty($sms_options['key'])): ?>
                                    <span style="color: #28a745;">✓ Tanımlı</span>
                                    <code><?php echo esc_html(substr($sms_options['key'], 0, 10) . '...'); ?></code>
                                <?php else: ?>
                                    <span style="color: #dc3545;">✗ Tanımlı Değil</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>API Hash:</th>
                            <td>
                                <?php if (!empty($sms_options['hash'])): ?>
                                    <span style="color: #28a745;">✓ Tanımlı</span>
                                    <code><?php echo esc_html(substr($sms_options['hash'], 0, 10) . '...'); ?></code>
                                <?php else: ?>
                                    <span style="color: #dc3545;">✗ Tanımlı Değil</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Sender (Gönderici Adı):</th>
                            <td>
                                <?php if (!empty($sms_options['sender'])): ?>
                                    <span style="color: #28a745;">✓ Tanımlı</span>
                                    <code><?php echo esc_html($sms_options['sender']); ?></code>
                                <?php else: ?>
                                    <span style="color: #dc3545;">✗ Tanımlı Değil</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="card" style="max-width: none; margin-top: 20px;">
                <h2>3. Spam Limit Ayarları</h2>
                <?php if (!empty($sms_options)): ?>
                    <?php
                    $hourly_limit = isset($sms_options['sms_hourly_limit']) ? (int)$sms_options['sms_hourly_limit'] : 10;
                    $daily_limit = isset($sms_options['sms_daily_limit']) ? (int)$sms_options['sms_daily_limit'] : 50;
                    ?>
                    <table class="form-table">
                        <tr>
                            <th>Saatlik SMS Limiti:</th>
                            <td>
                                <?php if ($hourly_limit === 0): ?>
                                    <span style="color: #28a745;">✓ LİMİT YOK (Sınırsız)</span>
                                <?php else: ?>
                                    <span style="color: #ffc107;">⚠ <?php echo $hourly_limit; ?> SMS/saat</span>
                                    <p class="description">Aynı telefona 1 saat içinde en fazla <?php echo $hourly_limit; ?> SMS gönderilebilir.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Günlük SMS Limiti:</th>
                            <td>
                                <?php if ($daily_limit === 0): ?>
                                    <span style="color: #28a745;">✓ LİMİT YOK (Sınırsız)</span>
                                <?php else: ?>
                                    <span style="color: #ffc107;">⚠ <?php echo $daily_limit; ?> SMS/gün</span>
                                    <p class="description">Aynı telefona 24 saat içinde en fazla <?php echo $daily_limit; ?> SMS gönderilebilir.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if ($hourly_limit > 0 || $daily_limit > 0): ?>
                        <div class="notice notice-warning inline">
                            <p><strong>⚠ UYARI: Spam limitleri aktif!</strong></p>
                            <p>Eğer aynı telefona kısa sürede birden fazla bağış yapılırsa, SMS'ler spam limiti nedeniyle engellenebilir.</p>
                        </div>
                        
                        <p>
                            <a href="<?php echo wp_nonce_url(add_query_arg('action', 'disable_limits'), 'barkod_sms_action', 'barkod_sms_nonce'); ?>" 
                               class="button button-primary"
                               onclick="return confirm('Spam limitleri devre dışı bırakılacak. Emin misiniz?')">
                                Limitleri Devre Dışı Bırak (Önerilen)
                            </a>
                            
                            <a href="<?php echo wp_nonce_url(add_query_arg('action', 'increase_limits'), 'barkod_sms_action', 'barkod_sms_nonce'); ?>" 
                               class="button"
                               onclick="return confirm('Spam limitleri artırılacak. Emin misiniz?')">
                                Limitleri Artır (100/500)
                            </a>
                            
                            <a href="<?php echo wp_nonce_url(add_query_arg('action', 'clear_logs'), 'barkod_sms_action', 'barkod_sms_nonce'); ?>" 
                               class="button"
                               onclick="return confirm('Son 24 saatteki tüm SMS logları silinecek. Emin misiniz?')">
                                Logları Temizle
                            </a>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>İletiMerkezi SMS ayarları bulunamadı.</p>
                <?php endif; ?>
            </div>
            
            <div class="card" style="max-width: none; margin-top: 20px;">
                <h2>4. Son 24 Saatteki SMS İstatistikleri</h2>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'iletimerkezi_sms_logs';
                
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name):
                    $stats = $wpdb->get_results(
                        "SELECT 
                            phone,
                            COUNT(*) as sms_count,
                            MAX(created_at) as last_sms,
                            SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_count
                        FROM $table_name 
                        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        GROUP BY phone
                        HAVING sms_count > 1
                        ORDER BY sms_count DESC
                        LIMIT 20",
                        ARRAY_A
                    );
                    
                    if (!empty($stats)):
                ?>
                        <p>Aşağıdaki telefon numaralarına son 24 saat içinde birden fazla SMS gönderilmiş:</p>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Telefon</th>
                                    <th>SMS Sayısı</th>
                                    <th>Engellenen</th>
                                    <th>Son SMS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats as $stat): ?>
                                    <?php $is_blocked = $stat['blocked_count'] > 0; ?>
                                    <tr <?php echo $is_blocked ? 'style="background: #f8d7da;"' : ''; ?>>
                                        <td><?php echo esc_html($stat['phone']); ?></td>
                                        <td><?php echo esc_html($stat['sms_count']); ?> SMS</td>
                                        <td>
                                            <?php if ($is_blocked): ?>
                                                <span style="color: #dc3545; font-weight: bold;">
                                                    <?php echo $stat['blocked_count']; ?> engellendi
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($stat['last_sms']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php
                        $total_blocked = $wpdb->get_var(
                            "SELECT COUNT(*) FROM $table_name 
                            WHERE status = 'blocked' 
                            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                        );
                        
                        if ($total_blocked > 0):
                        ?>
                            <div class="notice notice-error inline" style="margin-top: 15px;">
                                <p><strong>✗ <?php echo $total_blocked; ?> SMS spam limiti nedeniyle engellendi!</strong></p>
                                <p>Bu SMS'ler gönderilmedi. Spam limitlerini devre dışı bırakmanız önerilir.</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #28a745;">✓ Son 24 saat içinde tekrarlanan SMS gönderimi yok.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #ffc107;">⚠ SMS log tablosu bulunamadı.</p>
                <?php endif; ?>
            </div>
            
            <div class="card" style="max-width: none; margin-top: 20px;">
                <h2>5. Debug Log Talimatları</h2>
                <p>Bir bağış yaptığınızda, WordPress debug.log dosyasında şu logları göreceksiniz:</p>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;">[BARKOD_SMS_DEBUG] ========== SMS NOTIFICATION START ==========
[BARKOD_SMS_DEBUG] send_donation_sms_notifications called
[BARKOD_SMS_DEBUG] Donor ID: X, Kumbara ID: Y
[BARKOD_SMS_DEBUG] ========== is_enabled CHECK ==========
[BARKOD_SMS_DEBUG] barkod_sms_enabled option value: "1"
[BARKOD_SMS_DEBUG] provider_available: TRUE
[BARKOD_SMS_DEBUG] Final result: TRUE (ENABLED)
...
[BARKOD_SMS] ✓ Donor SMS sent successfully to 905XXXXXXXXX
[BARKOD_SMS] ✓ Owner SMS sent successfully to 905XXXXXXXXX
[BARKOD_SMS_DEBUG] ========== SMS NOTIFICATION END ==========</pre>
                <p><strong>Debug log dosyası konumu:</strong> <code>wp-content/debug.log</code></p>
                <p>Eğer SMS gönderilmiyorsa, bu loglar sorunu tespit etmenize yardımcı olacak.</p>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // İADE EKRANI
    // =========================================================================

    public function iade_sayfasi(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'barkod-sistemi'));
        }
        ?>
        <div class="pos-wrap">
            <div class="pos-header">
                <h1 class="pos-title" style="color: #263238 !important; opacity: 1 !important; visibility: visible !important;">İade İşlemi</h1>
                <p class="pos-subtitle">Tamamlanmış siparişleri iade edin</p>
            </div>

            <div class="pos-grid">
                <div class="pos-col-8">
                    <div class="pos-card">
                        <h3 class="pos-label" style="font-size: 1.4rem; margin-bottom: 20px;">Sipariş Ara</h3>
                        <div style="display: flex; gap: 12px; margin-bottom: 10px;">
                            <input type="number" id="iadeSiparisId" placeholder="Sipariş numarası girin..." class="pos-input" style="font-size: 1.3rem;" />
                            <button id="iadeSiparisAraBtn" class="pos-btn pos-btn-primary" style="white-space: nowrap;">
                                <span class="dashicons dashicons-search"></span> Ara
                            </button>
                        </div>
                        <div id="iadeSonuc"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#iadeSiparisId').on('keypress', function(e) {
                if (e.which === 13) $('#iadeSiparisAraBtn').trigger('click');
            });

            $('#iadeSiparisAraBtn').on('click', function() {
                const siparisId = $('#iadeSiparisId').val().trim();
                if (!siparisId) {
                    alert('Lütfen sipariş numarası girin.');
                    return;
                }

                $.ajax({
                    url: barkodSistemiAjax.ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'iade_siparis_ara',
                        siparis_id: siparisId,
                        nonce: barkodSistemiAjax.nonce
                    },
                    beforeSend: function() {
                        $('#iadeSiparisAraBtn').prop('disabled', true).html('<span class="dashicons dashicons-update"></span>');
                        $('#iadeSonuc').html('<p style="color:var(--pos-text-muted);padding:20px 0;">Aranıyor...</p>');
                    },
                    success: function(response) {
                        $('#iadeSiparisAraBtn').prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Ara');
                        if (response.success) {
                            renderIadeForm(response.data);
                        } else {
                            $('#iadeSonuc').html('<div class="notice notice-error inline" style="margin-top:15px;"><p>' + escHtml(response.data) + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#iadeSiparisAraBtn').prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Ara');
                        $('#iadeSonuc').html('<div class="notice notice-error inline" style="margin-top:15px;"><p>Sunucu hatası oluştu.</p></div>');
                    }
                });
            });

            function renderIadeForm(data) {
                let itemsHtml = '';
                data.items.forEach(function(item) {
                    const refunded = data.refunded_items[item.product_id] || 0;
                    const available = item.quantity - refunded;
                    if (available <= 0) return;

                    const unitPrice = item.quantity > 0 ? (item.total / item.quantity) : 0;
                    let options = '';
                    for (let i = 1; i <= available; i++) {
                        options += '<option value="' + i + '">' + i + '</option>';
                    }

                    itemsHtml += '<div class="pos-sepet-urun" style="align-items:center;padding:14px 0;">' +
                        '<label style="display:flex;align-items:center;gap:12px;flex:1;cursor:pointer;">' +
                        '<input type="checkbox" class="iade-item-check" data-item-id="' + item.item_id + '" data-unit-price="' + unitPrice.toFixed(4) + '" checked style="width:20px;height:20px;cursor:pointer;">' +
                        '<div><div class="sepet-urun-baslik">' + escHtml(item.name) + '</div>' +
                        '<div class="sepet-urun-detay">' + available + ' adet mevcut · ' + unitPrice.toFixed(2) + ' ₺/adet</div></div>' +
                        '</label>' +
                        '<div style="display:flex;align-items:center;gap:8px;">' +
                        '<span style="font-size:0.9rem;color:var(--pos-text-muted);">Adet:</span>' +
                        '<select class="iade-item-qty pos-input" data-item-id="' + item.item_id + '" style="padding:8px 12px;width:auto;">' + options + '</select>' +
                        '</div></div>';
                });

                if (!itemsHtml) {
                    $('#iadeSonuc').html('<div class="notice notice-warning inline" style="margin-top:15px;"><p>Bu siparişte iade edilebilecek ürün bulunmuyor.</p></div>');
                    return;
                }

                const puanHtml = data.kazanilan_puan > 0
                    ? '<div style="margin-top:20px;padding:15px 20px;background:var(--pos-secondary);border-radius:var(--pos-radius-sm);">' +
                      '<label style="display:flex;align-items:center;gap:10px;cursor:pointer;">' +
                      '<input type="checkbox" id="puanGeriAlCheck" checked style="width:18px;height:18px;cursor:pointer;">' +
                      '<span>Bu siparişten kazanılan <strong>' + data.kazanilan_puan + ' puan</strong> orantılı olarak geri al</span>' +
                      '</label></div>'
                    : '';

                $('#iadeSonuc').html(
                    '<div style="margin-top:25px;border-top:2px solid var(--pos-border);padding-top:25px;">' +
                    '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">' +
                    '<div><strong style="font-size:1.1rem;">' + escHtml(data.customer_name || 'Üyesiz Müşteri') + '</strong>' +
                    '<div style="color:var(--pos-text-muted);font-size:0.95rem;margin-top:4px;">Sipariş #' + data.order_id + ' · ' + data.date + '</div></div>' +
                    '<div style="text-align:right;">' +
                    '<div style="font-size:0.85rem;color:var(--pos-text-muted);">Sipariş Tutarı</div>' +
                    '<strong style="font-size:1.3rem;color:var(--pos-primary);">' + parseFloat(data.total).toFixed(2) + ' ₺</strong>' +
                    '</div></div>' +
                    '<h4 style="margin-bottom:12px;">İade Edilecek Ürünler:</h4>' +
                    '<div id="iadeUrunler">' + itemsHtml + '</div>' +
                    puanHtml +
                    '<div style="margin-top:25px;padding:20px;background:var(--pos-bg-body);border-radius:var(--pos-radius-sm);border:1px solid var(--pos-border);">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;">' +
                    '<span class="pos-label" style="font-size:1.1rem;">İade Tutarı:</span>' +
                    '<strong id="iadeToplam" style="font-size:1.8rem;color:#e53935;">0.00 ₺</strong>' +
                    '</div></div>' +
                    '<button id="iadeEtBtn" class="pos-btn pos-btn-primary w-full" style="margin-top:20px;padding:18px;font-size:1.2rem;background:#e53935;border-color:#e53935;" data-order-id="' + data.order_id + '">' +
                    '<span class="dashicons dashicons-undo"></span> İadeyi Tamamla' +
                    '</button></div>'
                );

                updateIadeToplam();
                $(document).off('change.iade').on('change.iade', '.iade-item-check, .iade-item-qty', updateIadeToplam);
                $(document).off('click.iade', '#iadeEtBtn').on('click.iade', '#iadeEtBtn', function() {
                    processIade(data.order_id);
                });
            }

            function updateIadeToplam() {
                let toplam = 0;
                $('.iade-item-check:checked').each(function() {
                    const itemId = $(this).data('item-id');
                    const unitPrice = parseFloat($(this).data('unit-price'));
                    const qty = parseInt($('.iade-item-qty[data-item-id="' + itemId + '"]').val()) || 1;
                    toplam += unitPrice * qty;
                });
                $('#iadeToplam').text(toplam.toFixed(2) + ' ₺');
            }

            function processIade(orderId) {
                const items = [];
                $('.iade-item-check:checked').each(function() {
                    const itemId = $(this).data('item-id');
                    const qty = parseInt($('.iade-item-qty[data-item-id="' + itemId + '"]').val()) || 1;
                    items.push({ item_id: itemId, quantity: qty });
                });

                if (items.length === 0) {
                    alert('En az bir ürün seçin.');
                    return;
                }

                if (!confirm('İade işlemini onaylıyor musunuz? Bu işlem geri alınamaz.')) return;

                $.ajax({
                    url: barkodSistemiAjax.ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'iade_islemini_tamamla',
                        siparis_id: orderId,
                        iade_items: JSON.stringify(items),
                        puan_geri_al: $('#puanGeriAlCheck').is(':checked') ? '1' : '0',
                        nonce: barkodSistemiAjax.nonce
                    },
                    beforeSend: function() {
                        $('#iadeEtBtn').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> İşleniyor...');
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#iadeSonuc').html(
                                '<div style="text-align:center;padding:60px 0;">' +
                                '<div style="font-size:4rem;margin-bottom:15px;">✅</div>' +
                                '<h2 style="color:var(--pos-primary);">İade Tamamlandı!</h2>' +
                                '<p style="color:var(--pos-text-muted);font-size:1.1rem;">' + escHtml(response.data.message) + '</p>' +
                                '<button class="pos-btn pos-btn-secondary" style="margin-top:20px;" onclick="jQuery(\'#iadeSiparisId\').val(\'\').focus();jQuery(\'#iadeSonuc\').empty();">Yeni İade</button>' +
                                '</div>'
                            );
                            $('#iadeSiparisId').val('');
                        } else {
                            alert('Hata: ' + response.data);
                            $('#iadeEtBtn').prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> İadeyi Tamamla');
                        }
                    },
                    error: function() {
                        alert('Sunucu hatası oluştu.');
                        $('#iadeEtBtn').prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> İadeyi Tamamla');
                    }
                });
            }

            function escHtml(text) {
                if (!text) return '';
                return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }
        });
        </script>
        <?php
    }

    public function iade_siparis_ara_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Yetkisiz işlem');
        }
        check_ajax_referer('barkod_sistemi_nonce', 'nonce');
        $this->check_rate_limit('iade_siparis_ara');

        $siparis_id = intval($_POST['siparis_id'] ?? 0);
        if ($siparis_id <= 0) {
            wp_send_json_error('Geçerli bir sipariş numarası girin');
        }

        $order = wc_get_order($siparis_id);
        if (!$order) {
            wp_send_json_error('Sipariş bulunamadı (#' . $siparis_id . ')');
        }

        if ($order->get_status() !== 'completed') {
            wp_send_json_error('Sadece tamamlanmış (completed) siparişler iade edilebilir');
        }

        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $items[] = [
                'item_id'    => $item_id,
                'product_id' => $item->get_product_id(),
                'name'       => $item->get_name(),
                'quantity'   => $item->get_quantity(),
                'total'      => (float) $item->get_total(),
            ];
        }

        // Daha önce yapılmış iadelerden iade edilen adetleri topla
        $refunded_items = [];
        foreach ($order->get_refunds() as $refund) {
            foreach ($refund->get_items() as $ri) {
                $pid = $ri->get_product_id();
                $refunded_items[$pid] = ($refunded_items[$pid] ?? 0) + abs($ri->get_quantity());
            }
        }

        // Sipariş notlarından puan bilgilerini çek
        $kazanilan_puan = 0;
        $kullanilan_puan = 0;
        foreach ($order->get_customer_order_notes() as $note) {
            if (preg_match('/Kazanılan Puan: (\d+)/', $note->comment_content, $m)) {
                $kazanilan_puan = intval($m[1]);
            }
            if (preg_match('/Kullanılan Puan: (\d+)/', $note->comment_content, $m)) {
                $kullanilan_puan = intval($m[1]);
            }
        }

        $customer_name = '';
        $customer_id = $order->get_customer_id();
        if ($customer_id) {
            $u = get_userdata($customer_id);
            if ($u) $customer_name = $u->display_name;
        }

        wp_send_json_success([
            'order_id'       => $order->get_id(),
            'customer_id'    => $customer_id,
            'customer_name'  => $customer_name,
            'total'          => (float) $order->get_total(),
            'date'           => $order->get_date_created()->date('d.m.Y H:i'),
            'items'          => $items,
            'refunded_items' => $refunded_items,
            'kazanilan_puan' => $kazanilan_puan,
            'kullanilan_puan' => $kullanilan_puan,
        ]);
    }

    public function iade_islemini_tamamla_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Yetkisiz işlem');
        }
        check_ajax_referer('barkod_sistemi_nonce', 'nonce');
        $this->check_rate_limit('iade_islemini_tamamla');

        $siparis_id   = intval($_POST['siparis_id'] ?? 0);
        $iade_items   = json_decode(stripslashes($_POST['iade_items'] ?? '[]'), true);
        $puan_geri_al = ($_POST['puan_geri_al'] ?? '0') === '1';

        try {
            $order = wc_get_order($siparis_id);
            if (!$order) throw new Exception('Sipariş bulunamadı');
            if ($order->get_status() !== 'completed') {
                throw new Exception('Sadece tamamlanmış siparişler iade edilebilir');
            }

            $refund_amount = 0;
            $line_items    = [];

            foreach ($iade_items as $iade) {
                $item_id = intval($iade['item_id'] ?? 0);
                $qty     = intval($iade['quantity'] ?? 1);
                $oi      = $order->get_item($item_id);
                if (!$oi) continue;

                $unit_price      = $oi->get_quantity() > 0 ? ($oi->get_total() / $oi->get_quantity()) : 0;
                $item_refund_amt = $unit_price * $qty;
                $refund_amount  += $item_refund_amt;

                $line_items[$item_id] = [
                    'qty'          => $qty,
                    'refund_total' => $item_refund_amt,
                ];
            }

            if ($refund_amount <= 0) throw new Exception('İade tutarı geçersiz');

            $refund = wc_create_refund([
                'order_id'     => $siparis_id,
                'amount'       => $refund_amount,
                'reason'       => 'POS İade İşlemi',
                'line_items'   => $line_items,
                'restock_items' => true,
            ]);

            if (is_wp_error($refund)) {
                throw new Exception($refund->get_error_message());
            }

            $customer_id = $order->get_customer_id();
            $puan_mesaj  = '';

            if ($customer_id && $puan_geri_al) {
                $kazanilan_puan = 0;
                foreach ($order->get_customer_order_notes() as $note) {
                    if (preg_match('/Kazanılan Puan: (\d+)/', $note->comment_content, $m)) {
                        $kazanilan_puan = intval($m[1]);
                    }
                }

                if ($kazanilan_puan > 0) {
                    $iade_orani          = $order->get_total() > 0 ? ($refund_amount / $order->get_total()) : 0;
                    $geri_alinacak_puan  = (int) round($kazanilan_puan * $iade_orani);
                    $mevcut_puan         = (int) get_user_meta($customer_id, 'wps_wpr_points', true);
                    $yeni_puan           = max(0, $mevcut_puan - $geri_alinacak_puan);
                    update_user_meta($customer_id, 'wps_wpr_points', $yeni_puan);
                    $order->add_order_note(sprintf('İade: %d puan geri alındı.', $geri_alinacak_puan));
                    $puan_mesaj = " {$geri_alinacak_puan} puan geri alındı.";
                }
            }

            $order->add_order_note(sprintf('POS İade: %.2f ₺ iade edildi.', $refund_amount));

            $this->send_refund_notification($order, $refund_amount, $puan_mesaj);

            wp_send_json_success([
                'message'       => number_format($refund_amount, 2, ',', '.') . ' ₺ tutarında iade tamamlandı.' . $puan_mesaj,
                'refund_id'     => $refund->get_id(),
                'refund_amount' => $refund_amount,
            ]);

        } catch (Exception $e) {
            error_log('[BARKOD_IADE_ERROR] ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    private function send_refund_notification($order, float $refund_amount, string $puan_mesaj): void {
        if (!class_exists('Barkod_Telegram_Bot')) return;
        $bot = new Barkod_Telegram_Bot();
        if (!$bot->is_configured()) return;

        $customer_name = '';
        if ($order->get_customer_id()) {
            $u = get_userdata($order->get_customer_id());
            if ($u) $customer_name = $u->display_name;
        }

        $msg  = "↩️ <b>İade İşlemi!</b>\n\n";
        $msg .= "🔢 Sipariş: #" . $order->get_id() . "\n";
        $msg .= "💸 İade Tutarı: " . number_format($refund_amount, 2, ',', '.') . " TL\n";
        if ($customer_name) $msg .= "👤 Müşteri: " . esc_html($customer_name) . "\n";
        if ($puan_mesaj)    $msg .= "⭐" . esc_html(trim($puan_mesaj)) . "\n";
        $msg .= "\n⏰ " . current_time('d.m.Y H:i');

        $bot->send_message($msg);
    }

    // =========================================================================
    // KASA KAPANIŞ RAPORU
    // =========================================================================

    public function kasa_kapanisi_sayfasi(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'barkod-sistemi'));
        }
        $bugun = current_time('Y-m-d');
        ?>
        <div class="pos-wrap">
            <div class="pos-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
                <div>
                    <h1 class="pos-title" style="color: #263238 !important; opacity: 1 !important; visibility: visible !important;">Kasa Kapanış Raporu</h1>
                    <p class="pos-subtitle">Günlük POS satış özeti</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-shrink:0;">
                    <input type="date" id="kasaTarih" class="pos-input" value="<?php echo esc_attr($bugun); ?>" style="padding:10px 16px;" />
                    <button id="kasaYenileBtn" class="pos-btn pos-btn-secondary">
                        <span class="dashicons dashicons-update"></span> Yenile
                    </button>
                    <button onclick="window.print()" class="pos-btn pos-btn-secondary" style="border-color:var(--pos-primary);color:var(--pos-primary);">
                        <span class="dashicons dashicons-printer"></span> Yazdır
                    </button>
                </div>
            </div>

            <div id="kasaRaporIcerik" style="margin-top:10px;">
                <div style="text-align:center;padding:60px;color:var(--pos-text-muted);">
                    <span class="dashicons dashicons-update" style="font-size:2.5rem;width:2.5rem;height:2.5rem;"></span>
                    <p style="margin-top:15px;">Rapor yükleniyor...</p>
                </div>
            </div>

            <div class="pos-card" style="margin-top:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 class="pos-label" style="font-size:1.2rem;margin:0;">📅 Son Kullanma Tarihi Takibi</h3>
                    <button id="sktYenileBtn" class="pos-btn pos-btn-secondary" style="padding:8px 16px;font-size:0.9rem;">
                        <span class="dashicons dashicons-update"></span> Yenile
                    </button>
                </div>
                <div id="sktRaporIcerik">
                    <p style="color:var(--pos-text-muted);">Yükleniyor...</p>
                </div>
            </div>
        </div>

        <style>
        @media print {
            #adminmenuwrap, #wpadminbar, .pos-header button, #kasaTarih, #kasaYenileBtn,
            .notice, .update-nag, #wpfooter { display: none !important; }
            .pos-wrap { padding: 0 !important; }
            .pos-card { box-shadow: none !important; border: 1px solid #ddd !important; }
            #wpcontent, #wpbody-content { margin: 0 !important; padding: 0 !important; }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            loadKasaRaporu();

            $('#kasaYenileBtn').on('click', loadKasaRaporu);
            $('#kasaTarih').on('change', loadKasaRaporu);

            function loadKasaRaporu() {
                const tarih = $('#kasaTarih').val();
                $('#kasaRaporIcerik').html('<div style="text-align:center;padding:60px;color:var(--pos-text-muted);"><span class="dashicons dashicons-update" style="font-size:2rem;width:2rem;height:2rem;"></span><p style="margin-top:12px;">Yükleniyor...</p></div>');

                $.ajax({
                    url: barkodSistemiAjax.ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'kasa_kapanisi_getir',
                        tarih: tarih,
                        nonce: barkodSistemiAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) renderKasaRaporu(response.data);
                        else $('#kasaRaporIcerik').html('<div class="notice notice-error"><p>' + escHtml(response.data) + '</p></div>');
                    },
                    error: function() {
                        $('#kasaRaporIcerik').html('<div class="notice notice-error"><p>Sunucu hatası oluştu.</p></div>');
                    }
                });
            }

            function renderKasaRaporu(d) {
                let topHtml = '';
                if (d.top_urunler.length > 0) {
                    d.top_urunler.forEach(function(u, i) {
                        topHtml += '<div style="display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px dashed var(--pos-border);">' +
                            '<div><span style="background:var(--pos-primary);color:#fff;padding:2px 8px;border-radius:12px;font-size:0.8rem;margin-right:8px;">' + (i+1) + '</span>' +
                            '<strong>' + escHtml(u.name) + '</strong></div>' +
                            '<div style="text-align:right;">' +
                            '<span style="color:var(--pos-text-muted);margin-right:14px;">' + u.quantity + ' adet</span>' +
                            '<strong style="color:var(--pos-primary);">' + parseFloat(u.total).toFixed(2) + ' ₺</strong>' +
                            '</div></div>';
                    });
                } else {
                    topHtml = '<p style="color:var(--pos-text-muted);text-align:center;padding:20px 0;">Bu tarihte POS satışı bulunamadı.</p>';
                }

                const netPuan = parseInt(d.kazanilan_puan) - parseInt(d.kullanilan_puan);

                $('#kasaRaporIcerik').html(
                    '<div style="text-align:center;margin-bottom:24px;color:var(--pos-text-muted);">' +
                    '<strong style="font-size:1.15rem;">' + d.tarih + '</strong> tarihli POS raporu' +
                    '</div>' +

                    // Stat cards row
                    '<div class="pos-grid" style="margin-bottom:24px;">' +
                    statCard(parseFloat(d.toplam_satis).toFixed(2) + ' ₺', 'Toplam Satış') +
                    statCard(d.siparis_sayisi, 'İşlem Sayısı') +
                    statCard(d.musteri_sayisi, 'Üye Müşteri') +
                    statCard(parseFloat(d.ortalama_satis).toFixed(2) + ' ₺', 'Ortalama Sepet') +
                    '</div>' +

                    // Bottom two cards
                    '<div class="pos-grid">' +
                    '<div class="pos-col-6"><div class="pos-card">' +
                    '<h3 class="pos-label" style="font-size:1.2rem;margin-bottom:18px;">⭐ Puan Özeti</h3>' +
                    '<div style="display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px dashed var(--pos-border);">' +
                    '<span>Kazanılan Puan</span><strong style="color:#2e7d32;">+' + parseInt(d.kazanilan_puan).toLocaleString('tr-TR') + '</strong></div>' +
                    '<div style="display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px dashed var(--pos-border);">' +
                    '<span>Kullanılan Puan</span><strong style="color:#c62828;">-' + parseInt(d.kullanilan_puan).toLocaleString('tr-TR') + '</strong></div>' +
                    '<div style="display:flex;justify-content:space-between;padding:13px 0;margin-top:4px;">' +
                    '<strong>Net Değişim</strong><strong style="color:var(--pos-primary);">' + (netPuan >= 0 ? '+' : '') + netPuan.toLocaleString('tr-TR') + '</strong>' +
                    '</div></div></div>' +

                    '<div class="pos-col-6"><div class="pos-card">' +
                    '<h3 class="pos-label" style="font-size:1.2rem;margin-bottom:18px;">🏆 En Çok Satan Ürünler</h3>' +
                    topHtml +
                    '</div></div></div>' +

                    '<div style="text-align:center;margin-top:35px;color:var(--pos-text-muted);font-size:0.88rem;">' +
                    'Oluşturulma: ' + new Date().toLocaleString('tr-TR') +
                    '</div>'
                );
            }

            function statCard(value, label) {
                return '<div class="pos-col-3"><div class="pos-card" style="text-align:center;background:var(--pos-secondary);border:none;padding:24px 16px;">' +
                    '<div style="font-size:2.2rem;font-weight:800;color:var(--pos-primary);font-family:var(--font-display);">' + value + '</div>' +
                    '<div style="color:var(--pos-text-muted);font-size:0.95rem;margin-top:5px;">' + label + '</div>' +
                    '</div></div>';
            }

            function escHtml(text) {
                if (!text) return '';
                return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            // SKT raporu — tarihten bağımsız, her sayfa açılışında yüklenir
            loadSktRaporu();
            $('#sktYenileBtn').on('click', loadSktRaporu);

            function loadSktRaporu() {
                $('#sktRaporIcerik').html('<p style="color:var(--pos-text-muted);">Yükleniyor...</p>');
                $.ajax({
                    url: barkodSistemiAjax.ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: { action: 'skt_urunleri_getir', nonce: barkodSistemiAjax.nonce },
                    success: function(r) {
                        if (r.success) renderSktRaporu(r.data);
                        else $('#sktRaporIcerik').html('<div class="notice notice-error inline"><p>' + escHtml(r.data) + '</p></div>');
                    },
                    error: function() {
                        $('#sktRaporIcerik').html('<div class="notice notice-error inline"><p>Sunucu hatası.</p></div>');
                    }
                });
            }

            function renderSktRaporu(d) {
                const toplam = d.expired.length + d.warn_30.length + d.warn_60.length + d.ok.length;
                if (toplam === 0) {
                    $('#sktRaporIcerik').html('<p style="color:var(--pos-text-muted);text-align:center;padding:20px 0;">SKT bilgisi girilmiş ürün bulunamadı.</p>');
                    return;
                }

                // Özet rozetleri
                let html = '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">';
                if (d.expired.length)  html += sktBadge(d.expired.length, 'Süresi Geçmiş', '#c62828', '#ffebee');
                if (d.warn_30.length)  html += sktBadge(d.warn_30.length,  '30 Gün İçinde', '#e65100', '#fff3e0');
                if (d.warn_60.length)  html += sktBadge(d.warn_60.length,  '60 Gün İçinde', '#f9a825', '#fffde7');
                if (d.ok.length)       html += sktBadge(d.ok.length,       'Normal',         '#2e7d32', '#e8f5e9');
                html += '</div>';

                // Grupları listele — önce kritikler
                html += sktGrup(d.expired,  '🔴 Süresi Geçmiş',       '#ffebee', '#c62828');
                html += sktGrup(d.warn_30,  '🟠 30 Gün İçinde Dolacak', '#fff3e0', '#e65100');
                html += sktGrup(d.warn_60,  '🟡 60 Gün İçinde Dolacak', '#fffde7', '#f9a825');

                $('#sktRaporIcerik').html(html);
            }

            function sktBadge(count, label, color, bg) {
                return '<span style="padding:6px 14px;border-radius:20px;font-weight:700;font-size:0.9rem;background:' + bg + ';color:' + color + ';border:1px solid ' + color + '33;">' +
                    count + ' · ' + label + '</span>';
            }

            function sktGrup(items, title, bgColor, textColor) {
                if (!items.length) return '';
                let html = '<div style="margin-bottom:18px;">' +
                    '<div style="font-weight:700;font-size:0.95rem;color:' + textColor + ';margin-bottom:8px;">' + title + '</div>' +
                    '<div style="border:1px solid ' + textColor + '33;border-radius:var(--pos-radius-sm);overflow:hidden;">';

                items.forEach(function(u, i) {
                    const kalanStr = u.kalan_gun <= 0
                        ? Math.abs(u.kalan_gun) + ' gün önce doldu'
                        : u.kalan_gun + ' gün kaldı';
                    const stokStr = u.stok !== null ? u.stok + ' adet' : '∞';
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:' + (i % 2 === 0 ? bgColor : '#fff') + ';border-bottom:' + (i < items.length - 1 ? '1px solid ' + textColor + '22' : 'none') + ';">' +
                        '<div>' +
                        '<strong style="font-size:0.95rem;">' + escHtml(u.name) + '</strong>' +
                        (u.sku ? '<span style="color:var(--pos-text-muted);font-size:0.82rem;margin-left:8px;">SKU: ' + escHtml(u.sku) + '</span>' : '') +
                        '</div>' +
                        '<div style="display:flex;align-items:center;gap:20px;flex-shrink:0;">' +
                        '<span style="font-size:0.88rem;color:var(--pos-text-muted);">Stok: ' + stokStr + '</span>' +
                        '<span style="font-weight:600;color:' + textColor + ';font-size:0.88rem;">' + escHtml(u.skt) + '</span>' +
                        '<span style="font-size:0.82rem;color:' + textColor + ';">' + kalanStr + '</span>' +
                        '<a href="' + escHtml(u.edit_url) + '" target="_blank" style="font-size:0.82rem;color:var(--pos-primary);text-decoration:none;white-space:nowrap;">Düzenle →</a>' +
                        '</div></div>';
                });

                html += '</div></div>';
                return html;
            }
        });
        </script>
        <?php
    }

    public function kasa_kapanisi_getir_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Yetkisiz işlem');
        }
        check_ajax_referer('barkod_sistemi_nonce', 'nonce');
        $this->check_rate_limit('kasa_kapanisi_getir');

        $tarih = sanitize_text_field($_POST['tarih'] ?? current_time('Y-m-d'));

        // Günün başı ve sonu (WordPress site zamanına göre)
        $date_start = $tarih . ' 00:00:00';
        $date_end   = $tarih . ' 23:59:59';

        $orders = wc_get_orders([
            'date_created' => $date_start . '...' . $date_end,
            'status'       => ['completed'],
            'limit'        => -1,
            'meta_query'   => [[
                'key'   => '_is_pos_order',
                'value' => 'yes',
            ]],
        ]);

        $toplam_satis         = 0.0;
        $siparis_sayisi       = count($orders);
        $kazanilan_puan_toplam = 0;
        $kullanilan_puan_toplam = 0;
        $urun_sayaci          = [];
        $musteri_ids          = [];

        foreach ($orders as $order) {
            $toplam_satis += (float) $order->get_total();

            $cid = $order->get_customer_id();
            if ($cid) $musteri_ids[$cid] = true;

            foreach ($order->get_customer_order_notes() as $note) {
                if (preg_match('/Kazanılan Puan: (\d+)/', $note->comment_content, $m)) {
                    $kazanilan_puan_toplam += intval($m[1]);
                }
                if (preg_match('/Kullanılan Puan: (\d+)/', $note->comment_content, $m)) {
                    $kullanilan_puan_toplam += intval($m[1]);
                }
            }

            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                if (!isset($urun_sayaci[$pid])) {
                    $urun_sayaci[$pid] = ['name' => $item->get_name(), 'quantity' => 0, 'total' => 0.0];
                }
                $urun_sayaci[$pid]['quantity'] += $item->get_quantity();
                $urun_sayaci[$pid]['total']    += (float) $item->get_total();
            }
        }

        usort($urun_sayaci, fn($a, $b) => $b['quantity'] - $a['quantity']);
        $top_urunler = array_slice(array_values($urun_sayaci), 0, 5);

        wp_send_json_success([
            'tarih'           => date_i18n('d.m.Y', strtotime($tarih)),
            'toplam_satis'    => $toplam_satis,
            'siparis_sayisi'  => $siparis_sayisi,
            'musteri_sayisi'  => count($musteri_ids),
            'ortalama_satis'  => $siparis_sayisi > 0 ? $toplam_satis / $siparis_sayisi : 0,
            'kazanilan_puan'  => $kazanilan_puan_toplam,
            'kullanilan_puan' => $kullanilan_puan_toplam,
            'top_urunler'     => $top_urunler,
        ]);
    }

    public function skt_urunleri_getir_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Yetkisiz işlem');
        }
        check_ajax_referer('barkod_sistemi_nonce', 'nonce');

        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT DISTINCT p.ID, pm.meta_value AS skt
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'
               AND pm.meta_key = '_expiration_date'
               AND pm.meta_value != ''
             ORDER BY pm.meta_value ASC"
        );

        $bugun_ts = strtotime(current_time('Y-m-d'));

        $groups = ['expired' => [], 'warn_30' => [], 'warn_60' => [], 'ok' => []];

        foreach ($rows as $row) {
            $product = wc_get_product($row->ID);
            if (!$product) continue;

            // Y-m-d ve d.m.Y formatlarının her ikisini destekle
            $skt_ts = strtotime($row->skt);
            if ($skt_ts === false || $skt_ts === 0) {
                $parts = explode('.', $row->skt);
                if (count($parts) === 3) {
                    $skt_ts = mktime(0, 0, 0, (int)$parts[1], (int)$parts[0], (int)$parts[2]);
                }
            }
            if (!$skt_ts) continue;

            $kalan_gun = (int) ceil(($skt_ts - $bugun_ts) / DAY_IN_SECONDS);

            $item = [
                'id'        => $row->ID,
                'name'      => $product->get_name(),
                'sku'       => $product->get_sku(),
                'skt'       => $row->skt,
                'kalan_gun' => $kalan_gun,
                'stok'      => $product->managing_stock() ? $product->get_stock_quantity() : null,
                'edit_url'  => admin_url('post.php?post=' . $row->ID . '&action=edit'),
            ];

            if ($kalan_gun <= 0) {
                $groups['expired'][] = $item;
            } elseif ($kalan_gun <= 30) {
                $groups['warn_30'][] = $item;
            } elseif ($kalan_gun <= 60) {
                $groups['warn_60'][] = $item;
            } else {
                $groups['ok'][] = $item;
            }
        }

        wp_send_json_success($groups);
    }
}
