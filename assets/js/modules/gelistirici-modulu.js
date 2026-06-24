/**
 * GelistiriciModulu - Geliştirici modu için manuel ürün ekleme ve barkod basma
 * Requirements: 2.1, 2.2, 2.3, 2.4
 */
class GelistiriciModulu {
    constructor(notificationManager) {
        this.notificationManager = notificationManager;
        this.selectedProduct = null;
        this.barcodeCount = 1;
        this.isEnabled = false;
    }

    enable() {
        this.isEnabled = true;
        console.log('Geliştirici modu aktif');
    }

    disable() {
        this.isEnabled = false;
        this.selectedProduct = null;
        console.log('Geliştirici modu devre dışı');
    }

    showProductDetails(product) {
        if (!this.isEnabled) return;

        this.selectedProduct = product;

        let sktHtml = '';
        if (product.skt) {
            let today = new Date();
            let expiration = new Date(product.skt);
            let diffTime = expiration - today;
            let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            sktHtml = `<div class="pos-info-row"><span class="pos-info-label">Son Kullanma Tarihi:</span> <span class="pos-info-value">${this.escapeHtml(product.skt)}</span></div>`;
            if (diffDays <= 60 && diffDays > 0) {
                sktHtml += `<div style="color:var(--pos-accent); font-weight:bold; margin-top:5px;">⚠️ Bu ürünün son kullanma tarihi ${diffDays} gün sonra doluyor!</div>`;
            } else if (diffDays <= 0) {
                sktHtml += `<div style="color:var(--pos-danger); font-weight:bold; margin-top:5px;">❌ Bu ürünün son kullanma tarihi geçmiş!</div>`;
            }
        }

        let fiyatHtml = '';
        if (product.indirim_yuzdesi > 0) {
            fiyatHtml = `
                <div class="pos-info-row"><span class="pos-info-label">Eski Fiyat:</span> <span class="pos-info-value" style="text-decoration: line-through; color: var(--pos-text-muted);">${this.escapeHtml(product.eski_fiyat)}</span></div>
                <div class="pos-info-row"><span class="pos-info-label">Yeni Fiyat:</span> <span class="pos-info-value" style="color: var(--pos-primary); font-size: 1.2rem;">${this.escapeHtml(product.yeni_fiyat)}</span></div>
                <div class="pos-info-row"><span class="pos-info-label">İndirim:</span> <span class="pos-info-value" style="color: var(--pos-success);">%${this.escapeHtml(product.indirim_yuzdesi)}</span></div>
            `;
        } else {
            fiyatHtml = `<div class="pos-info-row"><span class="pos-info-label">Fiyat:</span> <span class="pos-info-value" style="font-size: 1.2rem; color: var(--pos-primary);">${this.escapeHtml(product.fiyat)}</span></div>`;
        }

        let barkodBasmaHtml = `
            <div class="pos-card" style="margin-top: 20px; padding: 20px; border: 1px solid var(--pos-border);">
                <h4 style="margin-bottom: 15px; color: var(--pos-text-main);">Barkod Basma</h4>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="barkodAdet" class="pos-label" style="margin: 0;">Adet:</label>
                    <input type="number" id="barkodAdet" class="pos-input" min="1" max="1000" value="1" style="width: 80px;" />
                    <button id="barkodBasBtn" class="pos-btn pos-btn-secondary" data-product-id="${this.escapeHtml(product.id)}">
                        <i class="dashicons dashicons-printer"></i> Barkod Bas
                    </button>
                </div>
            </div>
        `;

        let html = `
            <div class="pos-result-card urun-premium-card" style="display: block;">
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <img src="${this.escapeHtml(product.resim)}" alt="${this.escapeHtml(product.baslik)}" class="urun-thumb" style="width: 120px; height: 120px;">
                    <div style="flex: 1;">
                        <h3 style="margin-top: 0; color: var(--pos-primary-dark);">${this.escapeHtml(product.baslik)}</h3>
                        <div class="urun-meta">
                            ${fiyatHtml}
                            <div class="pos-info-row"><span class="pos-info-label">Stok:</span> <span class="pos-info-value">${product.stok !== null ? this.escapeHtml(product.stok) : 'Yok'}</span></div>
                            ${sktHtml}
                        </div>
                        <div class="urun-actions" style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                            <button id="sepeteEkleBtn" class="pos-btn pos-btn-primary" data-id="${this.escapeHtml(product.id)}" data-baslik="${this.escapeHtml(product.baslik)}" data-fiyat="${this.escapeHtml(product.fiyat)}">
                                <i class="dashicons dashicons-cart"></i> Sepete Ekle
                            </button>
                            <a href="${this.escapeHtml(product.edit_url)}" target="_blank" class="pos-btn pos-btn-secondary">
                                <i class="dashicons dashicons-edit"></i> Düzenle
                            </a>
                        </div>
                    </div>
                </div>
                ${barkodBasmaHtml}
            </div>
        `;

        jQuery('#urunBilgi').html(html);
        this.attachBarcodeButtonListener();
    }

    attachBarcodeButtonListener() {
        jQuery('#barkodBasBtn').off('click').on('click', (e) => {
            const productId = jQuery(e.currentTarget).data('product-id');
            const count = parseInt(jQuery('#barkodAdet').val()) || 1;

            if (count < 1 || count > 1000) {
                this.notificationManager.showError('Barkod adedi 1-1000 arasında olmalıdır');
                return;
            }

            this.printBarcode(productId, count);
        });
    }

    printBarcode(productId, count) {
        if (!this.isEnabled) return;

        const confirmMessage = `${count} adet barkod basılacak ve stok ${count} adet artırılacak. Onaylıyor musunuz?`;
        if (!confirm(confirmMessage)) return;

        jQuery.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'barkod_bas',
                product_id: productId,
                quantity: count,
                nonce: barkodSistemiAjax.nonce
            },
            beforeSend: () => {
                jQuery('#barkodBasBtn').prop('disabled', true).html('<i class="dashicons dashicons-update spin"></i> İşleniyor...');
            },
            success: (response) => {
                jQuery('#barkodBasBtn').prop('disabled', false).html('<i class="dashicons dashicons-printer"></i> Barkod Bas');

                if (response.success) {
                    this.notificationManager.showSuccess(
                        `${count} adet barkod başarıyla basıldı. Stok güncellendi.`
                    );

                    if (this.selectedProduct) {
                        if (this.selectedProduct.stok !== null) {
                            this.selectedProduct.stok += count;
                        }
                        jQuery('#urunBarkod').val('').focus();
                        jQuery('#urunBilgi').empty();
                    }
                } else {
                    this.notificationManager.showError(
                        response.data || 'Barkod basma işlemi başarısız'
                    );
                }
            },
            error: () => {
                jQuery('#barkodBasBtn').prop('disabled', false).html('<i class="dashicons dashicons-printer"></i> Barkod Bas');
                this.notificationManager.showError('Bağlantı hatası oluştu');
            }
        });
    }

    escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}
