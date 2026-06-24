/**
 * SepetYoneticisi - Sepet yönetimi ve undo özelliği
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
 */
class SepetYoneticisi {
    constructor() {
        this.items = [];
        this.undoStack = [];
        this.undoTimeout = 5000;
        this.undoTimer = null;
        this.notificationManager = null;
    }

    setNotificationManager(manager) {
        this.notificationManager = manager;
    }

    addItem(product, quantity = 1) {
        let bulundu = false;
        this.items = this.items.map(item => {
            if (item.id === product.id) {
                item.adet += quantity;
                bulundu = true;
            }
            return item;
        });

        if (!bulundu) {
            this.items.push({
                id: product.id,
                baslik: product.baslik,
                fiyat: product.fiyat,
                adet: quantity
            });
        }

        this.updateUI();
    }

    removeItem(index) {
        if (index < 0 || index >= this.items.length) return;

        const removedItem = { ...this.items[index] };
        this.items.splice(index, 1);
        this.enableUndo({ type: 'remove', item: removedItem, index: index });
        this.updateUI();
    }

    decreaseQuantity(index) {
        if (index < 0 || index >= this.items.length) return;

        const item = this.items[index];

        if (item.adet > 1) {
            const previousItem = { ...item };
            item.adet--;
            this.enableUndo({ type: 'decrease', item: previousItem, index: index });
        } else {
            this.removeItem(index);
            return;
        }

        this.updateUI();
    }

    enableUndo(action) {
        if (this.undoTimer) {
            clearTimeout(this.undoTimer);
        }

        this.undoStack.push(action);
        this.showUndoNotification();

        this.undoTimer = setTimeout(() => {
            this.undoStack = [];
            this.hideUndoNotification();
        }, this.undoTimeout);
    }

    undo() {
        if (this.undoStack.length === 0) return;

        const action = this.undoStack.pop();

        if (action.type === 'remove') {
            this.items.splice(action.index, 0, action.item);
        } else if (action.type === 'decrease') {
            if (this.items[action.index]) {
                this.items[action.index].adet = action.item.adet;
            } else {
                this.items.splice(action.index, 0, action.item);
            }
        }

        if (this.undoTimer) {
            clearTimeout(this.undoTimer);
            this.undoTimer = null;
        }

        this.hideUndoNotification();
        this.updateUI();

        if (this.notificationManager) {
            this.notificationManager.showSuccess('İşlem geri alındı');
        }
    }

    showUndoNotification() {
        jQuery('.undo-notification').remove();

        const notification = jQuery('<div>')
            .addClass('undo-notification')
            .html(`
                <span class="undo-message">İşlem gerçekleştirildi</span>
                <button class="undo-button">Geri Al</button>
            `)
            .appendTo('body');

        setTimeout(() => notification.addClass('show'), 10);

        notification.find('.undo-button').on('click', () => {
            this.undo();
        });
    }

    hideUndoNotification() {
        const notification = jQuery('.undo-notification');
        notification.removeClass('show');
        setTimeout(() => notification.remove(), 300);
    }

    clearCart() {
        this.items = [];
        this.undoStack = [];
        if (this.undoTimer) {
            clearTimeout(this.undoTimer);
            this.undoTimer = null;
        }
        this.hideUndoNotification();
        this.updateUI();
    }

    calculateTotal() {
        let total = 0;
        this.items.forEach(item => {
            total += item.fiyat * item.adet;
        });
        return total;
    }

    updateUI() {
        let html = '';

        this.items.forEach((item, index) => {
            const urunToplam = item.fiyat * item.adet;

            html += `
                <div data-index="${index}" class="pos-sepet-urun">
                    <div class="sepet-urun-bilgi">
                        <span class="sepet-urun-baslik">${this.escapeHtml(item.baslik)}</span>
                        <span class="sepet-urun-detay">${item.adet} adet × ${item.fiyat.toFixed(2)} ₺</span>
                        <span class="sepet-urun-toplam">${urunToplam.toFixed(2)} ₺</span>
                    </div>
                    <div class="sepet-urun-butonlar">
                        <button class="sepet-azalt-btn" data-index="${index}" title="Adet azalt">−</button>
                        <button class="sepet-cikar-btn" data-index="${index}" title="Sepetten çıkar">🗑</button>
                    </div>
                </div>
            `;
        });

        jQuery('#sepetUrunleri').html(html);

        const total = this.calculateTotal();
        jQuery('#sepetToplam').text(total.toFixed(2));

        if (this.items.length === 0) {
            jQuery('#kullanilanPuanInput').prop('disabled', true).val('');
            jQuery('#puanUygulaBtn').prop('disabled', true);
            jQuery('#puanIndirim').text('0.00');
            jQuery('#sepetYeniToplam').text('0.00');
            window.kullanilanPuan = 0;
        } else {
            jQuery('#kullanilanPuanInput').prop('disabled', false);
            jQuery('#puanUygulaBtn').prop('disabled', false);
            const kullanilanPuan = window.kullanilanPuan || 0;
            const puanIndirimTutar = kullanilanPuan * (2 / 100);
            const yeniToplam = Math.max(0, total - puanIndirimTutar);
            jQuery('#sepetYeniToplam').text(yeniToplam.toFixed(2));
        }

        if (window.modSelector) {
            window.modSelector.setSepetDolu(this.items.length > 0);
        }

        window.sepetUrunleri = this.items;
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

    getItems() {
        return this.items;
    }

    setItems(items) {
        this.items = items;
        this.updateUI();
    }
}
