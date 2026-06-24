/**
 * POS Sistemi - Ana JavaScript Dosyası
 * 
 * Bu dosya sadece başlatma kodunu içerir.
 * Tüm modüller ayrı dosyalarda tanımlanmıştır ve WordPress enqueue sistemi ile yüklenir.
 * 
 * Modül Sırası (PHP'de enqueue edilir):
 * 1. notification-manager.js
 * 2. hizli-satis-modulu.js
 * 3. gelistirici-modulu.js
 * 4. sepet-yoneticisi.js
 * 5. puan-bagis-modulu.js
 * 6. kullanici-modulu.js
 * 7. mod-selector.js
 * 8. admin.js (bu dosya - initialization)
 */

jQuery(document).ready(function ($) {

    // ========================================
    // Modül Başlatma
    // ========================================

    console.log('POS Sistemi başlatılıyor...');

    // Debug: jQuery ve modüllerin yüklenip yüklenmediğini kontrol et
    console.log('jQuery yüklendi:', typeof jQuery !== 'undefined');
    console.log('NotificationManager yüklendi:', typeof NotificationManager !== 'undefined');
    console.log('KullaniciModulu yüklendi:', typeof KullaniciModulu !== 'undefined');
    console.log('PuanBagisModulu yüklendi:', typeof PuanBagisModulu !== 'undefined');
    console.log('ModSelector yüklendi:', typeof ModSelector !== 'undefined');

    // NotificationManager'ı başlat
    window.notificationManager = new NotificationManager();

    // SepetYoneticisi'ni başlat
    window.sepetYoneticisi = new SepetYoneticisi();
    window.sepetYoneticisi.setNotificationManager(window.notificationManager);

    // HizliSatisModulu'nu başlat
    window.hizliSatisModulu = new HizliSatisModulu(window.notificationManager);

    // GelistiriciModulu'nu başlat
    window.gelistiriciModulu = new GelistiriciModulu(window.notificationManager);

    // PuanBagisModulu'nu başlat
    window.puanBagisModulu = new PuanBagisModulu(window.notificationManager);

    // KullaniciModulu'nu başlat
    window.kullaniciModulu = new KullaniciModulu(window.notificationManager);

    // ModSelector'ı başlat
    window.modSelector = new ModSelector();

    // ========================================
    // Global Değişkenler
    // ========================================

    let seciliMusteri = null;
    let sepetUrunleri = [];
    window.mevcutPuan = 0;
    window.sepetUrunleri = window.sepetYoneticisi.getItems();

    // ========================================
    // Event Listeners
    // ========================================

    // Mod değişikliğini dinle
    $(document).on('pos:modeChanged', function (e, newMode) {
        $('#urunBilgi').empty();
        $('#urunBarkod').val('').focus();

        if (newMode === 'hizli') {
            window.hizliSatisModulu.enableAutoAdd();
            window.gelistiriciModulu.disable();
            window.notificationManager.showSuccess('Hızlı Satış Modu Aktif');
        } else {
            window.hizliSatisModulu.disableAutoAdd();
            window.gelistiriciModulu.enable();
            window.notificationManager.showSuccess('Geliştirici Modu Aktif');
        }
    });

    // İlk yüklemede modu kontrol et
    if (window.modSelector.isHizliSatisMode()) {
        window.hizliSatisModulu.enableAutoAdd();
        window.gelistiriciModulu.disable();
    } else {
        window.hizliSatisModulu.disableAutoAdd();
        window.gelistiriciModulu.enable();
    }

    // ========================================
    // Helper Functions
    // ========================================

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // ========================================
    // UI Event Handlers
    // ========================================

    // Üyesiz devam et checkbox kontrolü
    $('#uyesizDevam').on('change', function () {
        if ($(this).is(':checked')) {
            seciliMusteri = null;
            $('#musteriTel, #musteriAraBtn').prop('disabled', true);
            $('#sonuc').html('<p>Üyesiz alışveriş modunda.</p>');
            $('#urunBarkod').prop('disabled', false).focus();
            $('#sepet').show();
            $('#pos-empty-state').hide();
            $('#pos-puan').hide();
        } else {
            $('#musteriTel, #musteriAraBtn').prop('disabled', false).val('');
            $('#musteriBilgi').empty();
            $('#urunBarkod').prop('disabled', true).val('');
            $('#sonuc').empty();
            $('#sepet').hide();
            $('#pos-empty-state').show();
            window.sepetYoneticisi.clearCart();
        }
    });

    // Kullanıcı Oluştur Modal Aç - Güçlü Event Listener
    $(document).off('click', '#kullaniciOlusturBtn').on('click', '#kullaniciOlusturBtn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Kullanıcı Oluştur butonu tıklandı');
        console.log('KullaniciModulu mevcut:', typeof window.kullaniciModulu !== 'undefined');
        if (window.kullaniciModulu) {
            window.kullaniciModulu.showCreateUserInterface();
        } else {
            console.error('KullaniciModulu bulunamadı!');
            alert('Kullanıcı modülü yüklenmedi. Sayfayı yenileyin.');
        }
    });

    // Puan Bağış Modal Aç - Güçlü Event Listener
    $(document).off('click', '#puanBagisBtn').on('click', '#puanBagisBtn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Puan Bağış butonu tıklandı');
        console.log('PuanBagisModulu mevcut:', typeof window.puanBagisModulu !== 'undefined');
        if (window.puanBagisModulu) {
            window.puanBagisModulu.showDonationInterface();
        } else {
            console.error('PuanBagisModulu bulunamadı!');
            alert('Puan bağış modülü yüklenmedi. Sayfayı yenileyin.');
        }
    });

    // Mod Butonları - Güçlü Event Listener
    $(document).off('click', '.mod-button').on('click', '.mod-button', function (e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Mod butonu tıklandı:', this);
        const newMode = $(this).data('mode');
        console.log('Yeni mod:', newMode);
        if (window.modSelector) {
            window.modSelector.switchMode(newMode);
        } else {
            console.error('ModSelector bulunamadı!');
        }
    });

    // Müşteri telefon arama — Enter tuşu desteği
    $('#musteriTel').on('keypress', function (e) {
        if (e.which === 13) $('#musteriAraBtn').trigger('click');
    });

    // Müşteri telefon arama
    $('#musteriAraBtn').on('click', function () {
        let telefon = $('#musteriTel').val().trim();

        if (telefon.length < 3) {
            $('#sonuc').html('Lütfen en az 3 karakter girin.');
            return;
        }

        $.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'musteri_tel_ara',
                telefon: telefon,
                nonce: barkodSistemiAjax.nonce
            },
            beforeSend: function () {
                $('#sonuc').html('Aranıyor...');
            },
            success: function (response) {
                if (response.success) {
                    seciliMusteri = response.data.ID;

                    let siparisHtml = '';
                    if (response.data.siparisler && response.data.siparisler.length > 0) {
                        siparisHtml = '<h4>Son 5 Ürün</h4><ul>';
                        response.data.siparisler.forEach(function (urun) {
                            siparisHtml += `<li>
                                ${escapeHtml(urun.urun_adi)} - ${escapeHtml(urun.urun_fiyati)}₺ - ${escapeHtml(urun.tarih)}
                            </li>`;
                        });
                        siparisHtml += '</ul>';
                    } else {
                        siparisHtml = '<p>Son ürün bulunamadı.</p>';
                    }

                    let html = `
                        <div class="pos-result-card" style="animation: slideUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                            <div class="pos-info-row">
                                <span class="pos-info-label">Müşteri Adı</span>
                                <span class="pos-info-value" style="font-size: 1.2rem;">${escapeHtml(response.data.display_name)}</span>
                            </div>
                            <div class="pos-info-row">
                                <span class="pos-info-label">E-posta</span>
                                <span class="pos-info-value">${escapeHtml(response.data.user_email)}</span>
                            </div>
                            <div class="pos-info-row" style="background: var(--pos-secondary); padding: 15px; border-radius: var(--pos-radius-sm); margin-top: 10px;">
                                <span class="pos-info-label" style="color: var(--pos-primary-dark); font-weight: 700;">Güncel Puan</span>
                                <span class="pos-info-value" style="color: var(--pos-primary); font-size: 1.4rem;">${escapeHtml(response.data.wps_wpr_points || 0)} Puan</span>
                            </div>
                            <div class="siparisler-section" style="margin-top: 20px; border-top: 1px dashed var(--pos-border); padding-top: 15px;">
                                ${siparisHtml}
                            </div>
                        </div>
                    `;

                    $('#sonuc').html(html).show();
                    $('#urunBarkod').prop('disabled', false).focus();
                    $('#sepet').show();
                    $('#pos-empty-state').hide();
                    $('#pos-puan').show();
                    window.mevcutPuan = response.data.wps_wpr_points || 0;

                    // Puan inputunu güncelle
                    $('#kullanilanPuanInput').attr('max', window.mevcutPuan);
                } else {
                    $('#sonuc').html('<p>' + response.data + '</p>');
                    $('#urunBarkod').prop('disabled', true).val('');
                    $('#sepet').hide();
                    $('#pos-empty-state').show();
                    seciliMusteri = null;
                    sepetUrunleri = [];
                    sepetiGuncelle();
                }
            },
            error: function () {
                $('#sonuc').html('Bir hata oluştu.');
            }
        });
    });

    // Ürün barkod arama
    $('#urunBarkod').on('input', function () {
        let barkod = $(this).val().trim();

        // Hızlı satış modunda otomatik ekleme
        if (window.modSelector && window.modSelector.isHizliSatisMode()) {
            if (barkod.length >= 8) {
                clearTimeout(window.barcodeInputTimeout);
                window.barcodeInputTimeout = setTimeout(() => {
                    window.hizliSatisModulu.handleBarcodeInput(barkod);
                }, 300);
            }
            return;
        }

        // Geliştirici modunda manuel arama
        if (barkod.length < 13) {
            $('#urunBilgi').empty();
            return;
        }

        $.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'urun_barkod_ara',
                barkod: barkod,
                nonce: barkodSistemiAjax.nonce
            },
            beforeSend: function () {
                $('#urunBilgi').html('Aranıyor...');
            },
            success: function (response) {
                if (response.success) {
                    let urun = response.data;

                    if (window.modSelector && window.modSelector.isGelistiriciMode()) {
                        window.gelistiriciModulu.showProductDetails(urun);
                        $('#urunBarkod').val('').focus();
                        return;
                    }

                    // Fallback için premium gösterim
                    let sktHtml = '';
                    if (urun.skt) {
                        let today = new Date();
                        let expiration = new Date(urun.skt);
                        let diffTime = expiration - today;
                        let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        sktHtml = `<div class="skt-info ${diffDays <= 30 ? 'danger' : (diffDays <= 60 ? 'warning' : '')}">
                            SKT: ${escapeHtml(urun.skt)} (${diffDays} gün kaldı)
                        </div>`;
                    }

                    let html = `
                        <div class="urun-premium-card" style="animation: popup 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);">
                            <div class="urun-header">
                                <img src="${escapeHtml(urun.resim)}" alt="${escapeHtml(urun.baslik)}" class="urun-thumb">
                                <div class="urun-title-group">
                                    <h3>${escapeHtml(urun.baslik)}</h3>
                                    <div class="urun-price-tag">${escapeHtml(urun.fiyat)} ₺</div>
                                </div>
                            </div>
                            <div class="urun-meta-grid">
                                <div class="meta-item">
                                    <span class="label">Stok</span>
                                    <span class="value">${urun.stok !== null ? escapeHtml(urun.stok) : '∞'}</span>
                                </div>
                                ${sktHtml}
                            </div>
                            <div class="urun-actions">
                                <button id="sepeteEkleBtn" class="pos-button primary" data-id="${escapeHtml(urun.id)}" data-baslik="${escapeHtml(urun.baslik)}" data-fiyat="${escapeHtml(urun.fiyat)}">
                                    <i class="dashicons dashicons-cart"></i> Sepete Ekle
                                </button>
                                <a href="${escapeHtml(urun.edit_url)}" target="_blank" class="urunu-duzenle-btn">
                                    <i class="dashicons dashicons-edit"></i> Düzenle
                                </a>
                            </div>
                        </div>
                    `;

                    $('#urunBilgi').html(html);
                    $('#urunBarkod').val('').focus();
                } else {
                    $('#urunBilgi').html('<p>' + response.data + '</p>');
                }
            },
            error: function () {
                $('#urunBilgi').html('<p>Bir hata oluştu.</p>');
            }
        });
    });

    // Sepete ekle butonu
    $(document).on('click', '#sepeteEkleBtn', function () {
        let id = $(this).data('id');
        let fiyat = $(this).data('fiyat');
        let baslik = $(this).data('baslik');

        window.sepetYoneticisi.addItem({
            id: id,
            baslik: baslik,
            fiyat: fiyat
        }, 1);

        $('#urunBilgi').empty();
        $('#urunBarkod').val('').focus();
    });

    // Sepet butonları
    $(document).on('click', '.sepet-azalt-btn', function () {
        const index = parseInt($(this).data('index'));
        window.sepetYoneticisi.decreaseQuantity(index);
    });

    $(document).on('click', '.sepet-cikar-btn', function () {
        const index = parseInt($(this).data('index'));
        window.sepetYoneticisi.removeItem(index);
    });

    // Puan Uygula
    $('#puanUygulaBtn').on('click', function () {
        let kullanilanPuan = parseInt($('#kullanilanPuanInput').val()) || 0;

        if (kullanilanPuan < 0) kullanilanPuan = 0;
        if (kullanilanPuan > mevcutPuan) {
            kullanilanPuan = mevcutPuan;
            $('#kullanilanPuanInput').val(kullanilanPuan);
        }

        let tlIndirimOrani = 2 / 100;
        let puanIndirimTutar = kullanilanPuan * tlIndirimOrani;
        let toplam = window.sepetYoneticisi.calculateTotal();
        let yeniToplam = toplam - puanIndirimTutar;
        if (yeniToplam < 0) yeniToplam = 0;

        $('#puanIndirim').text(puanIndirimTutar.toFixed(2));
        $('#sepetYeniToplam').text(yeniToplam.toFixed(2));
        window.kullanilanPuan = kullanilanPuan;
    });

    // Siparişi tamamlama
    $('#siparisTamamlaBtn').on('click', function () {
        const sepetItems = window.sepetYoneticisi.getItems();

        if (sepetItems.length === 0) {
            alert('Sepet boş!');
            return;
        }

        $.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'satisi_tamamla',
                musteri_id: seciliMusteri || 0,
                urunler: JSON.stringify(sepetItems),
                kullanilan_puan: window.kullanilanPuan || 0,
                satis_modu: window.modSelector ? window.modSelector.getCurrentMode() : 'hizli',
                nonce: barkodSistemiAjax.nonce
            },
            beforeSend: function () {
                $('#siparisTamamlaBtn').prop('disabled', true).html('İşleniyor...');
            },
            success: function (response) {
                if (response.success) {
                    alert('Satış tamamlandı! Sipariş ID: ' + response.data.siparis_id);
                    window.sepetYoneticisi.clearCart();
                    $('#urunBilgi').empty();
                    $('#urunBarkod').val('');
                    $('#musteriBilgi').empty();
                    $('#musteriTel').val('');
                    $('#urunBarkod').prop('disabled', true);
                    $('#sepet').hide();
                    $('#pos-empty-state').show();
                    seciliMusteri = null;
                    window.kullanilanPuan = 0;
                    window.mevcutPuan = 0;
                    $('#kullanilanPuanInput').val(0);
                    $('#puanIndirim').text('0.00');
                    $('#sepetYeniToplam').text('0.00');
                    $('#uyesizDevam').prop('checked', false).trigger('change');
                } else {
                    alert('Hata: ' + response.data);
                }
                $('#siparisTamamlaBtn').prop('disabled', false).html('Satışı Bitir <span class="dashicons dashicons-arrow-right-alt"></span>');
            },
            error: function () {
                alert('Sunucu hatası.');
                $('#siparisTamamlaBtn').prop('disabled', false).html('Satışı Bitir <span class="dashicons dashicons-arrow-right-alt"></span>');
            }
        });
    });

    // Sepeti güncelleyen fonksiyon
    function sepetiGuncelle() {
        window.sepetYoneticisi.updateUI();
    }

    window.sepetiGuncelle = sepetiGuncelle;

    console.log('POS Sistemi başarıyla başlatıldı!');

    // Fallback: Eğer modüller yüklenmediyse 2 saniye sonra tekrar dene
    setTimeout(function() {
        if (typeof window.kullaniciModulu === 'undefined' || 
            typeof window.puanBagisModulu === 'undefined' || 
            typeof window.modSelector === 'undefined') {
            
            console.warn('Bazı modüller yüklenmedi, fallback sistemi devreye giriyor...');
            
            // Manuel event listener'lar ekle
            jQuery('#kullaniciOlusturBtn').off('click').on('click', function(e) {
                e.preventDefault();
                alert('Kullanıcı oluşturma modülü yüklenmedi. Sayfayı yenileyin.');
            });
            
            jQuery('#puanBagisBtn').off('click').on('click', function(e) {
                e.preventDefault();
                alert('Puan bağış modülü yüklenmedi. Sayfayı yenileyin.');
            });
            
            jQuery('.mod-button').off('click').on('click', function(e) {
                e.preventDefault();
                alert('Mod seçici yüklenmedi. Sayfayı yenileyin.');
            });
        }
    }, 2000);
});
