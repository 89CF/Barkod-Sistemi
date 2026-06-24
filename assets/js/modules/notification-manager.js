/**
 * NotificationManager - Görsel ve sesli bildirimler için
 * Requirements: 1.3
 */
class NotificationManager {
    constructor() {
        this.soundsEnabled = true;
        this.sounds = {
            success: barkodSistemiAjax.pluginUrl + 'assets/sounds/success.mp3',
            error: barkodSistemiAjax.pluginUrl + 'assets/sounds/error.mp3'
        };
    }

    showSuccess(message, duration = 3000) {
        this.showNotification(message, 'success', duration);
        this.playSound('success');
    }

    showError(message, duration = 5000) {
        this.showNotification(message, 'error', duration);
        this.playSound('error');
    }

    showWarning(message, duration = 4000) {
        this.showNotification(message, 'warning', duration);
    }

    showNotification(message, type, duration) {
        jQuery('.pos-notification').remove();

        const typeClasses = {
            success: 'pos-notification-success',
            error: 'pos-notification-error',
            warning: 'pos-notification-warning'
        };

        const notification = jQuery('<div>')
            .addClass('pos-notification')
            .addClass(typeClasses[type] || '')
            .text(message)
            .appendTo('body');

        setTimeout(() => notification.addClass('show'), 10);

        setTimeout(() => {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }

    playSound(type) {
        if (!this.soundsEnabled || !this.sounds[type]) {
            return;
        }

        try {
            const audio = new Audio(this.sounds[type]);
            audio.volume = 0.5;
            audio.play().catch(e => {
                console.log('Ses çalınamadı:', e);
            });
        } catch (e) {
            console.log('Ses hatası:', e);
        }
    }
}
