<?php
/**
 * SMS Spam Limit Düzeltme Scripti
 * 
 * Bu script SMS spam limitlerini devre dışı bırakır veya artırır.
 * Kullanım: WordPress admin panelinde bu dosyayı yükleyin ve çalıştırın.
 */

// WordPress'i yükle
require_once('../../../wp-load.php');

// Sadece admin kullanıcılar erişebilir
if (!current_user_can('manage_options')) {
    die('Bu sayfaya erişim yetkiniz yok.');
}

echo '<h1>SMS Spam Limit Düzeltme</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
    .button:hover { background: #0056b3; }
    .button-danger { background: #dc3545; }
    .button-danger:hover { background: #c82333; }
    .button-success { background: #28a745; }
    .button-success:hover { background: #218838; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
    table td:first-child { font-weight: bold; width: 250px; }
</style>';

// İşlem yapılacak mı?
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'disable_limits') {
    // Spam limitlerini devre dışı bırak
    $sms_options = get_option('iletimerkezi_sms_options', array());
    $sms_options['sms_hourly_limit'] = 0;
    $sms_options['sms_daily_limit'] = 0;
    update_option('iletimerkezi_sms_options', $sms_options);
    
    echo '<div class="section">';
    echo '<p class="success">✓ Spam limitleri başarıyla devre dışı bırakıldı!</p>';
    echo '<p>Artık aynı telefona sınırsız SMS gönderebilirsiniz.</p>';
    echo '<p><a href="?" class="button">← Geri Dön</a></p>';
    echo '</div>';
    exit;
}

if ($action === 'increase_limits') {
    // Spam limitlerini artır
    $sms_options = get_option('iletimerkezi_sms_options', array());
    $sms_options['sms_hourly_limit'] = 100; // Saatte 100 SMS
    $sms_options['sms_daily_limit'] = 500;  // Günde 500 SMS
    update_option('iletimerkezi_sms_options', $sms_options);
    
    echo '<div class="section">';
    echo '<p class="success">✓ Spam limitleri başarıyla artırıldı!</p>';
    echo '<p>Yeni limitler: 100 SMS/saat, 500 SMS/gün</p>';
    echo '<p><a href="?" class="button">← Geri Dön</a></p>';
    echo '</div>';
    exit;
}

if ($action === 'clear_logs') {
    // Son 24 saatteki logları temizle (spam kontrolünü sıfırla)
    global $wpdb;
    $table_name = $wpdb->prefix . 'iletimerkezi_sms_logs';
    
    $deleted = $wpdb->query(
        "DELETE FROM $table_name WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    
    echo '<div class="section">';
    echo '<p class="success">✓ Son 24 saatteki ' . $deleted . ' SMS log kaydı temizlendi!</p>';
    echo '<p>Spam sayaçları sıfırlandı. Artık yeniden SMS gönderebilirsiniz.</p>';
    echo '<p><a href="?" class="button">← Geri Dön</a></p>';
    echo '</div>';
    exit;
}

// Mevcut durumu göster
echo '<div class="section">';
echo '<h2>Mevcut Spam Limit Ayarları</h2>';

$sms_options = get_option('iletimerkezi_sms_options', array());

echo '<table>';

$hourly_limit = isset($sms_options['sms_hourly_limit']) ? (int)$sms_options['sms_hourly_limit'] : 10;
echo '<tr><td>Saatlik SMS Limiti:</td><td>';
if ($hourly_limit === 0) {
    echo '<span class="success">✓ DEVRE DIŞI (Sınırsız)</span>';
} else {
    echo '<span class="warning">⚠ ' . $hourly_limit . ' SMS/saat</span>';
}
echo '</td></tr>';

$daily_limit = isset($sms_options['sms_daily_limit']) ? (int)$sms_options['sms_daily_limit'] : 50;
echo '<tr><td>Günlük SMS Limiti:</td><td>';
if ($daily_limit === 0) {
    echo '<span class="success">✓ DEVRE DIŞI (Sınırsız)</span>';
} else {
    echo '<span class="warning">⚠ ' . $daily_limit . ' SMS/gün</span>';
}
echo '</td></tr>';

echo '</table>';

if ($hourly_limit > 0 || $daily_limit > 0) {
    echo '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-top: 15px;">';
    echo '<p><strong>⚠ UYARI: Spam limitleri aktif!</strong></p>';
    echo '<p>Bu limitler nedeniyle aynı telefona kısa sürede birden fazla SMS gönderilemeyebilir.</p>';
    echo '</div>';
}

echo '</div>';

// Son 24 saatteki SMS istatistikleri
echo '<div class="section">';
echo '<h2>Son 24 Saatteki SMS İstatistikleri</h2>';

global $wpdb;
$table_name = $wpdb->prefix . 'iletimerkezi_sms_logs';

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
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
    
    if (!empty($stats)) {
        echo '<p>Aşağıdaki telefon numaralarına son 24 saat içinde birden fazla SMS gönderilmiş:</p>';
        echo '<table>';
        echo '<tr style="background: #f8f9fa;"><td><strong>Telefon</strong></td><td><strong>SMS Sayısı</strong></td><td><strong>Engellenen</strong></td><td><strong>Son SMS</strong></td></tr>';
        
        foreach ($stats as $stat) {
            $is_blocked = $stat['blocked_count'] > 0;
            $row_style = $is_blocked ? 'background: #f8d7da;' : '';
            
            echo '<tr style="' . $row_style . '">';
            echo '<td>' . esc_html($stat['phone']) . '</td>';
            echo '<td>' . esc_html($stat['sms_count']) . ' SMS</td>';
            echo '<td>';
            if ($is_blocked) {
                echo '<span class="error">' . $stat['blocked_count'] . ' engellendi</span>';
            } else {
                echo '<span class="success">-</span>';
            }
            echo '</td>';
            echo '<td>' . esc_html($stat['last_sms']) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        // Engellenen SMS var mı?
        $total_blocked = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name 
            WHERE status = 'blocked' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        if ($total_blocked > 0) {
            echo '<div style="background: #f8d7da; border: 1px solid #dc3545; padding: 15px; border-radius: 4px; margin-top: 15px;">';
            echo '<p class="error"><strong>✗ ' . $total_blocked . ' SMS spam limiti nedeniyle engellendi!</strong></p>';
            echo '<p>Bu SMS\'ler gönderilmedi. Spam limitlerini devre dışı bırakmanız önerilir.</p>';
            echo '</div>';
        }
    } else {
        echo '<p class="success">✓ Son 24 saat içinde tekrarlanan SMS gönderimi yok.</p>';
    }
} else {
    echo '<p class="warning">⚠ SMS log tablosu bulunamadı.</p>';
}

echo '</div>';

// İşlem butonları
echo '<div class="section">';
echo '<h2>İşlemler</h2>';

echo '<h3>1. Spam Limitlerini Devre Dışı Bırak (Önerilen)</h3>';
echo '<p>Tüm spam limitlerini kaldırır. Aynı telefona sınırsız SMS gönderilebilir.</p>';
echo '<a href="?action=disable_limits" class="button button-success" onclick="return confirm(\'Spam limitleri devre dışı bırakılacak. Emin misiniz?\')">Limitleri Devre Dışı Bırak</a>';

echo '<h3>2. Spam Limitlerini Artır</h3>';
echo '<p>Limitleri yüksek değerlere ayarlar (100 SMS/saat, 500 SMS/gün).</p>';
echo '<a href="?action=increase_limits" class="button" onclick="return confirm(\'Spam limitleri artırılacak. Emin misiniz?\')">Limitleri Artır</a>';

echo '<h3>3. Son 24 Saatteki Logları Temizle</h3>';
echo '<p>Spam sayaçlarını sıfırlar. Engellenen telefonlar yeniden SMS alabilir.</p>';
echo '<a href="?action=clear_logs" class="button button-danger" onclick="return confirm(\'Son 24 saatteki tüm SMS logları silinecek. Emin misiniz?\')">Logları Temizle</a>';

echo '</div>';

echo '<div class="section">';
echo '<p><a href="' . admin_url() . '">← WordPress Admin Paneline Dön</a></p>';
echo '<p><a href="test-sms-config.php">SMS Konfigürasyon Testine Git →</a></p>';
echo '</div>';
