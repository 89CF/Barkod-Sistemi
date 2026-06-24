/**
 * KullaniciModulu - Yeni müşteri oluşturma frontend modülü
 * Redesigned with Premium Aesthetic
 */
class KullaniciModulu {
    constructor(notificationManager) {
        this.notificationManager = notificationManager;
    }

    showCreateUserInterface() {
        jQuery('#kullaniciModal').remove();

        const html = `
            <div class="puan-bagis-modal" id="kullaniciModal">
                <div class="puan-bagis-content" style="height: auto; max-height: 90vh;">
                    <div class="puan-bagis-header user-header">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h2 style="margin: 0; font-size: 2rem; font-family: var(--font-display);"><i class="dashicons dashicons-admin-users"></i> Yeni Müşteri Kaydı</h2>
                            <span class="puan-bagis-close user-close" style="cursor: pointer; font-size: 2.5rem; line-height: 1;">&times;</span>
                        </div>
                    </div>

                    <div class="puan-bagis-body" style="padding: 50px;">
                        <div class="pos-form-group">
                            <label class="pos-label">Ad Soyad</label>
                            <input type="text" id="yeniKullaniciAdi" class="pos-input" placeholder="Müşteri adını girin..." />
                        </div>

                        <div class="pos-form-group">
                            <label class="pos-label">E-Posta (Opsiyonel)</label>
                            <input type="email" id="yeniEmail" class="pos-input" placeholder="ornek@email.com" />
                        </div>

                        <div class="pos-form-group">
                            <label class="pos-label">Telefon Numarası</label>
                            <input type="text" id="yeniTelefon" class="pos-input" placeholder="5XX XXX XX XX" />
                        </div>

                        <div id="kullaniciOlusturSonuc"></div>
                    </div>

                    <div class="puan-bagis-footer" style="padding: 30px 50px;">
                        <button class="pos-btn pos-btn-secondary user-close">İptal</button>
                        <button id="kullaniciKaydetBtn" class="pos-btn pos-btn-primary user-btn-primary" style="padding: 20px 40px;">
                            Müşteriyi Kaydet <i class="dashicons dashicons-saved"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        jQuery('body').append(html);
        this.attachEventListeners();
        jQuery('#kullaniciModal').css('display', 'flex').hide().fadeIn(300);
    }

    attachEventListeners() {
        const self = this;
        jQuery('.user-close').on('click', () => this.closeInterface());

        jQuery('#kullaniciKaydetBtn').on('click', () => this.processUserCreation());

        jQuery('#kullaniciModal').on('click', function (e) {
            if (e.target.id === 'kullaniciModal') {
                self.closeInterface();
            }
        });

        jQuery('#yeniTelefon, #yeniKullaniciAdi, #yeniEmail').on('keypress', (e) => {
            if (e.which === 13) this.processUserCreation();
        });
    }

    processUserCreation() {
        const username = jQuery('#yeniKullaniciAdi').val().trim();
        const email = jQuery('#yeniEmail').val().trim();
        const phone = jQuery('#yeniTelefon').val().trim();

        if (!username || !phone) {
            this.notificationManager.showError('Lütfen ad ve telefon alanlarını doldurun.');
            return;
        }

        jQuery.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'pos_kullanici_olustur',
                username: username,
                email: email,
                phone: phone,
                nonce: barkodSistemiAjax.nonce
            },
            beforeSend: () => {
                jQuery('#kullaniciKaydetBtn').prop('disabled', true).html('<i class="dashicons dashicons-update spin"></i> Kaydediliyor...');
            },
            success: (response) => {
                if (response.success) {
                    this.showSuccessState(username, phone);
                } else {
                    this.notificationManager.showError(response.data || 'Kayıt işlemi başarısız.');
                    jQuery('#kullaniciKaydetBtn').prop('disabled', false).html('Müşteriyi Kaydet <i class="dashicons dashicons-saved"></i>');
                }
            },
            error: () => {
                this.notificationManager.showError('Sunucu hatası oluştu.');
                jQuery('#kullaniciKaydetBtn').prop('disabled', false).html('Müşteriyi Kaydet <i class="dashicons dashicons-saved"></i>');
            }
        });
    }

    showSuccessState(name, phone) {
        const html = `
            <div class="success-state" style="text-align: center; padding: 50px 0; animation: modalPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);">
                <div class="success-checkmark">
                    <div class="check-icon user-check">
                        <span class="icon-line line-tip"></span>
                        <span class="icon-line line-long"></span>
                    </div>
                </div>
                <h2 style="color: var(--pos-primary-dark); font-size: 2.5rem; font-weight: 800; margin-bottom: 15px; font-family: var(--font-display);">Müşteri Kaydedildi!</h2>
                <p style="color: var(--pos-text-muted); font-size: 1.3rem; margin-bottom: 40px; font-weight: 500;">
                    <strong>${name}</strong> başarıyla sisteme eklendi.
                </p>
                <div style="margin-top: 50px;">
                    <button class="pos-btn pos-btn-primary user-btn-primary" style="margin: 0 auto; padding: 20px 50px; font-size: 1.3rem;" onclick="jQuery('.user-close').first().click()">
                        Harika! <i class="dashicons dashicons-smiley"></i>
                    </button>
                </div>
            </div>
        `;
        jQuery('#kullaniciModal .puan-bagis-body').html(html);
        jQuery('#kullaniciModal .puan-bagis-footer').fadeOut(400);

        jQuery('#musteriTel').val(phone);
        setTimeout(() => {
            jQuery('#musteriAraBtn').click();
        }, 1000);

        this.notificationManager.showSuccess('Müşteri başarıyla oluşturuldu.');
    }

    closeInterface() {
        jQuery('#kullaniciModal').fadeOut(300, function () {
            jQuery(this).remove();
        });
    }
}
