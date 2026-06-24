/**
 * HizliSatisModulu - Hızlı satış modu için otomatik ürün ekleme
 * Requirements: 1.1, 1.2, 1.3, 1.4
 */
class HizliSatisModulu {
    constructor(notificationManager) {
        this.notificationManager = notificationManager;
        this.autoAddEnabled = false;
        this.lastBarcode = '';
        this.barcodeTimeout = null;
        this.isProcessing = false;
    }

    enableAutoAdd() {
        this.autoAddEnabled = true;
        console.log('Hızlı satış modu aktif - Otomatik ekleme açık');
    }

    disableAutoAdd() {
        this.autoAddEnabled = false;
        console.log('Hızlı satış modu devre dışı');
    }

    handleBarcodeInput(barcode) {
        if (!barcode || barcode.trim().length === 0) {
            return;
        }

        barcode = barcode.trim();

        if (barcode.length < 8) {
            return;
        }

        if (this.isProcessing && this.lastBarcode === barcode) {
            return;
        }

        if (!this.autoAddEnabled) {
            return;
        }

        this.lastBarcode = barcode;
        this.isProcessing = true;

        this.autoAddToCart(barcode);
    }

    autoAddToCart(barcode) {
        jQuery.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'hizli_satis_urun_ekle',
                barkod: barcode,
                nonce: barkodSistemiAjax.nonce
            },
            success: (response) => {
                this.isProcessing = false;

                if (response.success) {
                    const urun = response.data;
                    this.addOrUpdateCartItem(urun);
                    this.notificationManager.showSuccess(
                        `${urun.baslik} sepete eklendi (${urun.fiyat} ₺)`
                    );
                    jQuery('#urunBarkod').val('').focus();
                } else {
                    this.notificationManager.showError(
                        response.data || 'Ürün eklenemedi'
                    );
                    jQuery('#urunBarkod').val('').focus();
                }
            },
            error: () => {
                this.isProcessing = false;
                this.notificationManager.showError('Bağlantı hatası oluştu');
                jQuery('#urunBarkod').val('').focus();
            }
        });
    }

    addOrUpdateCartItem(urun) {
        if (window.sepetYoneticisi) {
            window.sepetYoneticisi.addItem(urun, 1);
        } else {
            if (!window.sepetUrunleri) {
                window.sepetUrunleri = [];
            }

            let bulundu = false;
            window.sepetUrunleri = window.sepetUrunleri.map(item => {
                if (item.id === urun.id) {
                    item.adet++;
                    bulundu = true;
                }
                return item;
            });

            if (!bulundu) {
                window.sepetUrunleri.push({
                    id: urun.id,
                    baslik: urun.baslik,
                    fiyat: urun.fiyat,
                    adet: 1
                });
            }

            if (typeof window.sepetiGuncelle === 'function') {
                window.sepetiGuncelle();
            }
        }
    }
}
