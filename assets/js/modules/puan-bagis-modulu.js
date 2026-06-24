/**
 * PuanBagisModulu - Puan bağış sistemi frontend modülü
 * Redesigned with Premium Aesthetic and Step-by-Step Flow
 */
class PuanBagisModulu {
    constructor(notificationManager) {
        this.notificationManager = notificationManager;
        this.donorCustomer = null;
        this.selectedKumbara = null;
        this.donationAmount = 0;
        this.kumbaraList = [];
        this.currentStep = 1;
    }

    showDonationInterface() {
        jQuery('#puanBagisModal').remove();

        const html = `
            <div class="puan-bagis-modal" id="puanBagisModal">
                <div class="puan-bagis-content">
                    <style>
                        .puan-bagis-steps { display: flex; justify-content: space-between; margin-bottom: 40px; position: relative; }
                        .puan-bagis-steps::before { content: ''; position: absolute; top: 18px; left: 0; width: 100%; height: 3px; background: var(--pos-secondary); z-index: 0; }
                        .step-item { position: relative; z-index: 1; background: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid var(--pos-secondary); font-weight: 700; color: var(--pos-text-muted); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); font-family: var(--font-display); }
                        .step-item.active { border-color: var(--pos-primary); color: var(--pos-primary); background: var(--pos-secondary); transform: scale(1.2); }
                        .step-item.completed { border-color: var(--pos-primary); background: var(--pos-primary); color: white; }
                        .step-label { position: absolute; top: 45px; left: 50%; transform: translateX(-50%); font-size: 0.9rem; white-space: nowrap; color: var(--pos-text-muted); font-family: var(--font-display); }
                        .step-item.active .step-label { color: var(--pos-primary); font-weight: 700; }
                        
                        .kumbara-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; max-height: 350px; overflow-y: auto; padding: 10px; }
                        .kumbara-card { cursor: pointer; border: 2px solid var(--pos-secondary) !important; transition: all 0.3s ease !important; background: white !important; }
                        .kumbara-card:hover { border-color: var(--pos-primary-light) !important; transform: translateY(-5px) !important; box-shadow: var(--pos-shadow-md) !important; }
                        .kumbara-card.selected { border-color: var(--pos-primary) !important; background: var(--pos-secondary) !important; transform: scale(1.02) !important; box-shadow: var(--pos-shadow-md) !important; }
                        
                        .puan-bagis-step-content { display: none; animation: slideUp 0.4s ease-out; }
                        .puan-bagis-step-content.active { display: block; }
                        
                        .donation-amount-display { text-align: center; margin-bottom: 30px; padding: 30px; background: var(--pos-secondary); border-radius: var(--pos-radius-md); border: 2px dashed var(--pos-primary-light); }
                        .donation-amount-display .value { font-size: 4rem; font-weight: 800; color: var(--pos-primary); line-height: 1; font-family: var(--font-display); }
                        .donation-amount-display .label { font-size: 1.1rem; color: var(--pos-primary-dark); margin-top: 10px; font-weight: 600; }
                    </style>

                    <div class="puan-bagis-header">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h2 style="margin: 0; font-size: 2rem; font-family: var(--font-display);"><i class="dashicons dashicons-heart"></i> Puan Bağışı</h2>
                            <span class="puan-bagis-close" style="cursor: pointer; font-size: 2.5rem; line-height: 1;">&times;</span>
                        </div>
                    </div>

                    <div class="puan-bagis-body">
                        <div class="puan-bagis-steps">
                            <div class="step-item active" data-step="1">
                                1
                                <div class="step-label">Müşteri</div>
                            </div>
                            <div class="step-item" data-step="2">
                                2
                                <div class="step-label">Kumbara</div>
                            </div>
                            <div class="step-item" data-step="3">
                                3
                                <div class="step-label">Miktar</div>
                            </div>
                        </div>

                        <div class="puan-bagis-step-content active" data-step="1">
                            <div class="pos-form-group">
                                <label class="pos-label">Bağışçı Müşteri</label>
                                <div style="display: flex; gap: 15px;">
                                    <input type="text" id="bagisciTelefon" class="pos-input" placeholder="Telefon numarası girin..." />
                                    <button id="bagisciAraBtn" class="pos-btn pos-btn-primary">Ara</button>
                                </div>
                            </div>
                            <div id="bagisciSonuc"></div>
                        </div>

                        <div class="puan-bagis-step-content" data-step="2">
                            <div class="pos-form-group">
                                <label class="pos-label">Kumbara Seçin</label>
                                <input type="text" id="kumbaraArama" class="pos-input" placeholder="Kumbara ara..." />
                            </div>
                            <div id="kumbaraListesi" class="kumbara-grid"></div>
                        </div>

                        <div class="puan-bagis-step-content" data-step="3">
                            <div class="donation-amount-display">
                                <div class="value" id="displayAmount">0</div>
                                <div class="label">Bağışlanacak Puan</div>
                                <div class="mevcut-puan" style="margin-top: 25px; font-size: 1.2rem;">
                                    Mevcut Bakiyeniz: <strong id="mevcutPuanBakiye" style="color: var(--pos-primary-dark);">0</strong> Puan
                                </div>
                            </div>
                            
                            <div class="pos-form-group">
                                <label class="pos-label">Miktar Girin</label>
                                <input type="number" id="bagisMiktari" class="pos-input" min="1" value="0" style="text-align: center; font-size: 2.5rem; padding: 20px; font-family: var(--font-display); font-weight: 700;" />
                            </div>
                        </div>
                    </div>

                    <div class="puan-bagis-footer">
                        <button id="bagisPrevBtn" class="pos-btn pos-btn-secondary" style="visibility: hidden;">
                            <i class="dashicons dashicons-arrow-left-alt2"></i> Geri
                        </button>
                        <button id="bagisNextBtn" class="pos-btn pos-btn-primary" disabled>
                            Devam Et <i class="dashicons dashicons-arrow-right-alt2"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        jQuery('body').append(html);
        this.currentStep = 1;
        this.attachEventListeners();

        jQuery('#puanBagisModal').css('display', 'flex').hide().fadeIn(300);

        const posTelefon = jQuery('#musteriTel').val();
        if (posTelefon && posTelefon.trim().length >= 10) {
            jQuery('#bagisciTelefon').val(posTelefon);
            this.searchCustomer(posTelefon);
        }
    }

    attachEventListeners() {
        const self = this;

        jQuery('.puan-bagis-close').on('click', () => this.closeDonationInterface());

        jQuery('#bagisciAraBtn').on('click', () => {
            const telefon = jQuery('#bagisciTelefon').val().trim();
            this.searchCustomer(telefon);
        });

        jQuery('#bagisciTelefon').on('keypress', (e) => {
            if (e.which === 13) {
                this.searchCustomer(jQuery('#bagisciTelefon').val().trim());
            }
        });

        jQuery('#kumbaraArama').on('input', function () {
            self.filterKumbaraList(jQuery(this).val().trim().toLowerCase());
        });

        jQuery('#bagisMiktari').on('input', function () {
            const val = parseInt(jQuery(this).val()) || 0;
            self.validateDonation(val);
        });

        jQuery('#bagisNextBtn').on('click', () => this.nextStep());
        jQuery('#bagisPrevBtn').on('click', () => this.prevStep());

        jQuery('#puanBagisModal').on('click', function (e) {
            if (e.target.id === 'puanBagisModal') {
                self.closeDonationInterface();
            }
        });
    }

    nextStep() {
        if (this.currentStep === 1 && !this.donorCustomer) return;
        if (this.currentStep === 2 && !this.selectedKumbara) return;

        if (this.currentStep === 3) {
            this.processDonation();
            return;
        }

        this.currentStep++;
        this.updateStepUI();
    }

    prevStep() {
        if (this.currentStep <= 1) return;
        this.currentStep--;
        this.updateStepUI();
    }

    updateStepUI() {
        jQuery('.puan-bagis-step-content').removeClass('active');
        jQuery(`.puan-bagis-step-content[data-step="${this.currentStep}"]`).addClass('active');

        jQuery('.step-item').removeClass('active completed');
        jQuery('.step-item').each((index, element) => {
            const $el = jQuery(element);
            const step = $el.data('step');
            if (step === this.currentStep) $el.addClass('active');
            if (step < this.currentStep) $el.addClass('completed');
        });

        if (this.currentStep === 1) {
            jQuery('#bagisPrevBtn').css('visibility', 'hidden');
            jQuery('#bagisNextBtn').prop('disabled', !this.donorCustomer).html('Devam Et <i class="dashicons dashicons-arrow-right-alt2"></i>');
        } else if (this.currentStep === 2) {
            jQuery('#bagisPrevBtn').css('visibility', 'visible');
            jQuery('#bagisNextBtn').prop('disabled', !this.selectedKumbara).html('Devam Et <i class="dashicons dashicons-arrow-right-alt2"></i>');
        } else if (this.currentStep === 3) {
            jQuery('#bagisPrevBtn').css('visibility', 'visible');
            jQuery('#bagisNextBtn').prop('disabled', this.donationAmount <= 0).html('Bağışı Tamamla <i class="dashicons dashicons-heart"></i>');

            // Show balance immediately
            if (this.donorCustomer) {
                jQuery('#mevcutPuanBakiye').text(this.donorCustomer.points);
            }
        }
    }

    searchCustomer(telefon) {
        if (!telefon || telefon.length < 3) {
            this.notificationManager.showError('Lütfen geçerli bir telefon numarası girin');
            return;
        }

        jQuery.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'musteri_tel_ara',
                telefon: telefon,
                nonce: barkodSistemiAjax.nonce
            },
            beforeSend: () => {
                jQuery('#bagisciSonuc').html('<div class="loading-dots">Aranıyor...</div>');
                jQuery('#bagisciAraBtn').prop('disabled', true);
            },
            success: (response) => {
                jQuery('#bagisciAraBtn').prop('disabled', false);
                if (response.success) {
                    this.donorCustomer = {
                        id: response.data.ID,
                        name: response.data.display_name,
                        points: response.data.wps_wpr_points || 0
                    };

                    jQuery('#bagisciSonuc').html(`
                        <div class="pos-card" style="margin-top: 20px; background: var(--pos-secondary); border-color: var(--pos-primary-light); animation: slideUp 0.4s ease-out;">
                            <p style="font-size: 1.4rem; margin-bottom: 8px; color: var(--pos-primary-dark); font-family: var(--font-display); font-weight: 700;"><strong>${this.escapeHtml(this.donorCustomer.name)}</strong></p>
                            <p style="color: var(--pos-primary); font-size: 1.1rem; font-weight: 600;">Mevcut Puan: <span style="background: var(--pos-primary); color: white; padding: 4px 12px; border-radius: 20px; font-weight: 800; margin-left: 5px;">${this.donorCustomer.points}</span></p>
                        </div>
                    `);

                    jQuery('#bagisNextBtn').prop('disabled', false);
                    this.loadKumbaraList();

                    // Auto-advance to next step
                    setTimeout(() => this.nextStep(), 600);
                } else {
                    jQuery('#bagisciSonuc').html(`<div class="pos-card" style="margin-top: 15px; background: #fef2f2; border-color: #fecaca; color: #991b1b;">${response.data}</div>`);
                    this.donorCustomer = null;
                    jQuery('#bagisNextBtn').prop('disabled', true);
                }
            }
        });
    }

    loadKumbaraList() {
        jQuery.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'kumbara_listesi_getir',
                nonce: barkodSistemiAjax.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.kumbaraList = response.data;
                    this.renderKumbaraList();
                }
            }
        });
    }

    renderKumbaraList(filteredList = null) {
        const list = filteredList || this.kumbaraList;
        let html = '';

        list.forEach((kumbara, index) => {
            const isSelected = this.selectedKumbara && this.selectedKumbara.id === kumbara.id;
            html += `
                <div class="pos-card kumbara-card ${isSelected ? 'selected' : ''}" data-kumbara-id="${kumbara.id}" style="padding: 20px; margin-bottom: 0; animation: slideUp 0.4s ease ${index * 0.05}s forwards; display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                    <div>
                        <div class="kumbara-icon" style="width: 50px; height: 50px; background: var(--pos-secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: var(--pos-primary);">
                            <span class="dashicons dashicons-heart" style="font-size: 24px; width: 24px; height: 24px;"></span>
                        </div>
                        <h4 style="margin: 0 0 10px 0; color: var(--pos-text-main); font-family: var(--font-display); font-size: 1.1rem;">${this.escapeHtml(kumbara.name)}</h4>
                        <div style="font-size: 0.9rem; color: var(--pos-text-muted); margin-bottom: 15px; line-height: 1.4;">${this.escapeHtml(kumbara.description || 'Açıklama yok.')}</div>
                    </div>
                    <div style="font-weight: 700; color: var(--pos-primary); background: var(--pos-secondary); padding: 8px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 5px; align-self: flex-start;">
                        <i class="dashicons dashicons-chart-bar"></i> ${kumbara.total_points} Puan
                    </div>
                </div>
            `;
        });

        jQuery('#kumbaraListesi').html(html || '<p style="text-align:center; color: var(--pos-text-muted);">Kumbara bulunamadı.</p>');

        jQuery('.kumbara-card').on('click', (e) => {
            const id = jQuery(e.currentTarget).data('kumbara-id');
            this.selectKumbara(id);
        });
    }

    filterKumbaraList(term) {
        const filtered = this.kumbaraList.filter(k =>
            k.name.toLowerCase().includes(term) ||
            (k.description && k.description.toLowerCase().includes(term))
        );
        this.renderKumbaraList(filtered);
    }

    selectKumbara(id) {
        this.selectedKumbara = this.kumbaraList.find(k => k.id === id);
        jQuery('.kumbara-card').removeClass('selected');
        jQuery(`.kumbara-card[data-kumbara-id="${id}"]`).addClass('selected');
        jQuery('#bagisNextBtn').prop('disabled', false);

        setTimeout(() => this.nextStep(), 300);
    }

    validateDonation(amount) {
        if (amount > this.donorCustomer.points) {
            amount = this.donorCustomer.points;
            jQuery('#bagisMiktari').val(amount);
            this.notificationManager.showWarning('Mevcut puanınızdan fazlasını bağışlayamazsınız.');
        }

        this.donationAmount = amount;
        jQuery('#displayAmount').text(amount);
        jQuery('#mevcutPuanBakiye').text(this.donorCustomer.points);
        jQuery('#bagisNextBtn').prop('disabled', amount <= 0);
    }

    processDonation() {
        if (!this.selectedKumbara || this.donationAmount <= 0) return;

        jQuery.ajax({
            url: barkodSistemiAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'puan_bagis_yap',
                donor_user_id: this.donorCustomer.id,
                kumbara_id: this.selectedKumbara.id,
                points: this.donationAmount,
                nonce: barkodSistemiAjax.nonce
            },
            beforeSend: () => {
                jQuery('#bagisNextBtn').prop('disabled', true).html('<i class="dashicons dashicons-update spin"></i> İşleniyor...');
            },
            success: (response) => {
                if (response.success) {
                    this.showSuccessState();
                } else {
                    this.notificationManager.showError(response.data || 'Bağış işlemi başarısız.');
                    this.updateStepUI();
                }
            },
            error: () => {
                this.notificationManager.showError('Sunucu hatası oluştu.');
                this.updateStepUI();
            }
        });
    }

    showSuccessState() {
        const html = `
            <div class="success-state" style="text-align: center; padding: 50px 0; animation: modalPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);">
                <div class="success-checkmark">
                    <div class="check-icon">
                        <span class="icon-line line-tip"></span>
                        <span class="icon-line line-long"></span>
                    </div>
                </div>
                <h2 style="color: var(--pos-primary-dark); font-size: 2.5rem; font-weight: 800; margin-bottom: 15px; font-family: var(--font-display);">Bağış Tamamlandı!</h2>
                <p style="color: var(--pos-text-muted); font-size: 1.3rem; margin-bottom: 40px; font-weight: 500;">
                    <strong>${this.donationAmount}</strong> puan başarıyla <strong>${this.escapeHtml(this.selectedKumbara.name)}</strong> kumbarasına aktarıldı.
                </p>
                <div style="background: var(--pos-secondary); border-radius: 25px; padding: 25px 40px; display: inline-block; border: 2px solid var(--pos-primary-light); box-shadow: var(--pos-shadow-sm);">
                    <p style="color: var(--pos-primary-dark); font-weight: 800; margin: 0; font-size: 1.2rem; font-family: var(--font-display);">Yeni Puan Bakiyeniz: ${this.donorCustomer.points - this.donationAmount}</p>
                </div>
                <div style="margin-top: 50px;">
                    <button class="pos-btn pos-btn-primary" style="margin: 0 auto; padding: 20px 50px; font-size: 1.3rem;" onclick="jQuery('.puan-bagis-close').click()">
                        Harika! <i class="dashicons dashicons-smiley"></i>
                    </button>
                </div>
            </div>
        `;
        jQuery('#puanBagisModal .puan-bagis-body').html(html);
        jQuery('#puanBagisModal .puan-bagis-footer, #puanBagisModal .puan-bagis-steps').fadeOut(400);
        this.notificationManager.showSuccess('Bağış başarıyla tamamlandı.');
    }

    closeDonationInterface() {
        jQuery('#puanBagisModal').fadeOut(300, function () {
            jQuery(this).remove();
        });
        this.donorCustomer = null;
        this.selectedKumbara = null;
        this.donationAmount = 0;
        this.currentStep = 1;
    }

    escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
}
