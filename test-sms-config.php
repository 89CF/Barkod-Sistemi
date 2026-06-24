<?php
/**
 * SMS Konfigürasyon Test Dosyası
 * 
 * Bu dosyayı WordPress admin panelinde çalıştırarak SMS ayarlarınızı kontrol edebilirsiniz.
 * Kullanım: WordPress admin panelinde bu dosyayı yükleyin ve çalıştırın.
 */

// WordPress'i yükle
require_once('../../../wp-load.php');

// Sadece admin kullanıcılar erişebilir
if (!current_user_can('manage_options')) {
    die('Bu sayfaya erişim yetkiniz yok.');
}

echo '<h1>SMS Konfigürasyon Test</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    table td { padding: 8px; border-bottom: 1px solid #dee2e6; }
    table td:first-child { font-weight: bold; width: 250px; }
</style>';

// 1. Barkod SMS Ayarları
echo '<div class="section">';
echo '<h2>1. Barkod SMS Ayarları</h2>';
echo '<table>';

$barkod_sms_enabled = get_option('barkod_sms_enabled', 'NOT_SET');
echo '<tr><td>barkod_sms_enabled:</td><td>';
if ($barkod_sms_enabled === '1') {
    echo '<span class="success">✓ ENABLED (value: "' . $barkod_sms_enabled . '")</span>';
} else {
    echo '<span class="error">✗ DISABLED (value: "' . $barkod_sms_enabled . '")</span>';
    echo '<br><small>Çözüm: WordPress admin panelinde SMS ayarlarından "SMS Gönderimini Etkinleştir" seçeneğini aktif edin.</small>';
}
echo '</td></tr>';

$barkod_sms_provider = get_option('barkod_sms_provider', 'NOT_SET');
echo '<tr><td>barkod_sms_provider:</td><td>' . $barkod_sms_provider . '</td></tr>';

echo '</table>';
echo '</div>';

// 2. İletiMerkezi SMS Plugin Ayarları
echo '<div class="section">';
echo '<h2>2. İletiMerkezi SMS Plugin Ayarları</h2>';

$sms_options = get_option('iletimerkezi_sms_options', array());

if (empty($sms_options)) {
    echo '<p class="error">✗ İletiMerkezi SMS ayarları bulunamadı!</p>';
    echo '<p><small>Çözüm: İletiMerkezi SMS plugin\'ini yükleyin ve ayarlarını yapılandırın.</small></p>';
} else {
    echo '<table>';
    
    $has_key = !empty($sms_options['key']);
    echo '<tr><td>API Key:</td><td>';
    if ($has_key) {
        echo '<span class="success">✓ SET</span> (değer: ' . substr($sms_options['key'], 0, 10) . '...)';
    } else {
        echo '<span class="error">✗ NOT SET</span>';
    }
    echo '</td></tr>';
    
    $has_hash = !empty($sms_options['hash']);
    echo '<tr><td>API Hash:</td><td>';
    if ($has_hash) {
        echo '<span class="success">✓ SET</span> (değer: ' . substr($sms_options['hash'], 0, 10) . '...)';
    } else {
        echo '<span class="error">✗ NOT SET</span>';
    }
    echo '</td></tr>';
    
    $has_sender = !empty($sms_options['sender']);
    echo '<tr><td>Sender (Gönderici Adı):</td><td>';
    if ($has_sender) {
        echo '<span class="success">✓ SET</span> (değer: ' . $sms_options['sender'] . ')';
    } else {
        echo '<span class="error">✗ NOT SET</span>';
    }
    echo '</td></tr>';
    
    $endpoint = $sms_options['endpoint'] ?? 'https://api.iletimerkezi.com/v1/send-sms';
    echo '<tr><td>API Endpoint:</td><td>' . $endpoint . '</td></tr>';
    
    echo '</table>';
    
    echo '<h3>Tüm Ayarlar (Debug):</h3>';
    echo '<pre>' . print_r($sms_options, true) . '</pre>';
}

echo '</div>';

// 3. İletiMerkezi SMS API Class Kontrolü
echo '<div class="section">';
echo '<h2>3. İletiMerkezi SMS API Class</h2>';

if (class_exists('IletiMerkezi_SMS_API')) {
    echo '<p class="success">✓ IletiMerkezi_SMS_API class FOUND</p>';
    echo '<p>Plugin aktif ve kullanılabilir durumda.</p>';
} else {
    echo '<p class="error">✗ IletiMerkezi_SMS_API class NOT FOUND</p>';
    echo '<p><small>Not: Class bulunamadı ama direct API kullanılabilir (yukarıdaki ayarlar geçerliyse).</small></p>';
}

echo '</div>';

// 4. SMS Şablonları
echo '<div class="section">';
echo '<h2>4. SMS Şablonları</h2>';
echo '<table>';

$donor_template = get_option('barkod_sms_donor_template', 'NOT_SET');
echo '<tr><td>Bağışçı Şablonu:</td><td>';
if ($donor_template === 'NOT_SET') {
    echo '<span class="warning">⚠ Varsayılan şablon kullanılacak</span>';
} else {
    echo '<span class="success">✓ Özel şablon tanımlı</span>';
}
echo '<br><small>' . htmlspecialchars($donor_template) . '</small>';
echo '</td></tr>';

$owner_template = get_option('barkod_sms_owner_template', 'NOT_SET');
echo '<tr><td>Kumbara Sahibi Şablonu:</td><td>';
if ($owner_template === 'NOT_SET') {
    echo '<span class="warning">⚠ Varsayılan şablon kullanılacak</span>';
} else {
    echo '<span class="success">✓ Özel şablon tanımlı</span>';
}
echo '<br><small>' . htmlspecialchars($owner_template) . '</small>';
echo '</td></tr>';

echo '</table>';
echo '</div>';

// 5. Test Sonucu
echo '<div class="section">';
echo '<h2>5. Genel Durum</h2>';

$all_ok = true;
$issues = array();

if ($barkod_sms_enabled !== '1') {
    $all_ok = false;
    $issues[] = 'Barkod SMS servisi devre dışı (barkod_sms_enabled != "1")';
}

if (empty($sms_options)) {
    $all_ok = false;
    $issues[] = 'İletiMerkezi SMS ayarları bulunamadı';
} else {
    if (empty($sms_options['key'])) {
        $all_ok = false;
        $issues[] = 'İletiMerkezi API Key eksik';
    }
    if (empty($sms_options['hash'])) {
        $all_ok = false;
        $issues[] = 'İletiMerkezi API Hash eksik';
    }
    if (empty($sms_options['sender'])) {
        $all_ok = false;
        $issues[] = 'İletiMerkezi Sender (Gönderici Adı) eksik';
    }
}

if ($all_ok) {
    echo '<p class="success" style="font-size: 18px;">✓ SMS SİSTEMİ HAZIR!</p>';
    echo '<p>Tüm ayarlar doğru yapılandırılmış. SMS gönderimi çalışmalı.</p>';
} else {
    echo '<p class="error" style="font-size: 18px;">✗ SMS SİSTEMİ ÇALIŞMIYOR</p>';
    echo '<p><strong>Tespit Edilen Sorunlar:</strong></p>';
    echo '<ul>';
    foreach ($issues as $issue) {
        echo '<li class="error">' . $issue . '</li>';
    }
    echo '</ul>';
    
    echo '<h3>Çözüm Adımları:</h3>';
    echo '<ol>';
    echo '<li>WordPress admin paneline gidin</li>';
    echo '<li>İletiMerkezi SMS plugin ayarlarını açın</li>';
    echo '<li>API Key, API Hash ve Sender bilgilerini girin</li>';
    echo '<li>Barkod Sistemi SMS ayarlarından "SMS Gönderimini Etkinleştir" seçeneğini aktif edin</li>';
    echo '<li>Bu sayfayı yenileyin ve kontrol edin</li>';
    echo '</ol>';
}

echo '</div>';

// 6. Debug Log Kontrolü
echo '<div class="section">';
echo '<h2>6. Spam Limit Ayarları</h2>';

if (!empty($sms_options)) {
    echo '<table>';
    
    $hourly_limit = isset($sms_options['sms_hourly_limit']) ? (int)$sms_options['sms_hourly_limit'] : 10;
    echo '<tr><td>Saatlik SMS Limiti:</td><td>';
    if ($hourly_limit === 0) {
        echo '<span class="success">✓ LİMİT YOK (Sınırsız)</span>';
    } else {
        echo '<span class="warning">⚠ ' . $hourly_limit . ' SMS/saat</span>';
        echo '<br><small>Aynı telefona 1 saat içinde en fazla ' . $hourly_limit . ' SMS gönderilebilir.</small>';
    }
    echo '</td></tr>';
    
    $daily_limit = isset($sms_options['sms_daily_limit']) ? (int)$sms_options['sms_daily_limit'] : 50;
    echo '<tr><td>Günlük SMS Limiti:</td><td>';
    if ($daily_limit === 0) {
        echo '<span class="success">✓ LİMİT YOK (Sınırsız)</span>';
    } else {
        echo '<span class="warning">⚠ ' . $daily_limit . ' SMS/gün</span>';
        echo '<br><small>Aynı telefona 24 saat içinde en fazla ' . $daily_limit . ' SMS gönderilebilir.</small>';
    }
    echo '</td></tr>';
    
    echo '</table>';
    
    if ($hourly_limit > 0 || $daily_limit > 0) {
        echo '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-top: 15px;">';
        echo '<p><strong>⚠ UYARI: Spam limitleri aktif!</strong></p>';
        echo '<p>Eğer aynı telefona kısa sürede birden fazla bağış yapılırsa, SMS\'ler spam limiti nedeniyle engellenebilir.</p>';
        echo '<p><strong>Çözüm:</strong> İletiMerkezi SMS ayarlarından spam limitlerini artırın veya 0 yaparak devre dışı bırakın.</p>';
        echo '</div>';
    }
} else {
    echo '<p class="error">İletiMerkezi SMS ayarları bulunamadı.</p>';
}

echo '</div>';

// 7. Debug Log Kontrolü
echo '<div class="section">';
echo '<h2>7. Debug Log Talimatları</h2>';
echo '<p>Bir bağış yaptığınızda, WordPress debug.log dosyasında şu logları göreceksiniz:</p>';
echo '<pre>';
echo '[BARKOD_SMS_DEBUG] ========== SMS NOTIFICATION START ==========
[BARKOD_SMS_DEBUG] send_donation_sms_notifications called
[BARKOD_SMS_DEBUG] Donor ID: X, Kumbara ID: Y
[BARKOD_SMS_DEBUG] ========== is_enabled CHECK ==========
[BARKOD_SMS_DEBUG] barkod_sms_enabled option value: "1"
[BARKOD_SMS_DEBUG] provider_available: TRUE
[BARKOD_SMS_DEBUG] Final result: TRUE (ENABLED)
...
[BARKOD_SMS] ✓ Donor SMS sent successfully to 905XXXXXXXXX
[BARKOD_SMS] ✓ Owner SMS sent successfully to 905XXXXXXXXX
[BARKOD_SMS_DEBUG] ========== SMS NOTIFICATION END ==========';
echo '</pre>';
echo '<p><strong>Debug log dosyası konumu:</strong> wp-content/debug.log</p>';
echo '<p>Eğer SMS gönderilmiyorsa, bu loglar sorunu tespit etmenize yardımcı olacak.</p>';
echo '</div>';

echo '<div class="section">';
echo '<p><a href="' . admin_url() . '">← WordPress Admin Paneline Dön</a></p>';
echo '</div>';
