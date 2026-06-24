/**
 * ModSelector - Hızlı Satış ve Geliştirici modları arasında geçiş yapar
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
 */
class ModSelector {
    constructor() {
        this.currentMode = 'hizli';
        this.sepetDolu = false;
        this.storageKey = 'barkod_pos_mode';

        this.init();
    }

    init() {
        this.loadMode();

        // Güçlü event listener - diğer pluginlerin müdahalesini engelle
        jQuery(document).off('click', '.mod-button').on('click', '.mod-button', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('Mod butonu tıklandı:', e.currentTarget);
            const newMode = jQuery(e.currentTarget).data('mode');
            console.log('Yeni mod:', newMode);
            this.switchMode(newMode);
        });

        this.updateUI();
    }

    switchMode(newMode) {
        if (newMode === this.currentMode) {
            return;
        }

        if (this.sepetDolu) {
            const onay = confirm(
                'Sepetinizde ürün var. Mod değiştirirseniz sepet temizlenecek. Devam etmek istiyor musunuz?'
            );

            if (!onay) {
                return;
            }

            this.clearCart();
        }

        this.currentMode = newMode;
        this.saveMode();
        this.updateUI();

        jQuery(document).trigger('pos:modeChanged', [newMode]);
    }

    updateUI() {
        jQuery('.mod-button').removeClass('active');
        jQuery(`.mod-button[data-mode="${this.currentMode}"]`).addClass('active');
    }

    saveMode() {
        try {
            localStorage.setItem(this.storageKey, this.currentMode);
        } catch (e) {
            console.error('localStorage kaydetme hatası:', e);
        }
    }

    loadMode() {
        try {
            const savedMode = localStorage.getItem(this.storageKey);
            if (savedMode && (savedMode === 'hizli' || savedMode === 'gelistirici')) {
                this.currentMode = savedMode;
            }
        } catch (e) {
            console.error('localStorage okuma hatası:', e);
        }
    }

    clearCart() {
        if (window.sepetYoneticisi) {
            window.sepetYoneticisi.clearCart();
        } else {
            if (window.sepetUrunleri) {
                window.sepetUrunleri = [];
            }

            if (typeof window.sepetiGuncelle === 'function') {
                window.sepetiGuncelle();
            }
        }

        this.sepetDolu = false;
    }

    setSepetDolu(dolu) {
        this.sepetDolu = dolu;
    }

    getCurrentMode() {
        return this.currentMode;
    }

    isHizliSatisMode() {
        return this.currentMode === 'hizli';
    }

    isGelistiriciMode() {
        return this.currentMode === 'gelistirici';
    }
}
