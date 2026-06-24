# Ses Dosyaları

Bu klasör, POS sisteminin bildirim sesleri için kullanılır.

## Gerekli Dosyalar

- `success.mp3` - Başarılı işlem sesi (ürün ekleme, bağış tamamlama vb.)
- `error.mp3` - Hata bildirimi sesi (geçersiz barkod, stok yok vb.)

## Mevcut Durum

⚠️ **ÖNEMLİ**: Şu anda placeholder (yer tutucu) dosyalar bulunmaktadır. Gerçek MP3 ses dosyaları ile değiştirilmelidir.

## Ses Dosyalarını Ekleme

1. **Süre**: Kısa (0.5-1 saniye) bildirim sesleri kullanın
2. **Boyut**: Dosya boyutunu küçük tutun (< 50KB önerilir)
3. **Format**: MP3 formatında olmalıdır
4. **Ses Seviyesi**: Orta düzeyde normalize edilmiş olmalıdır
5. **Kalite**: 128kbps veya daha düşük bit rate yeterlidir

## Önerilen Sesler

### Success (Başarı) Sesi
- Pozitif, hoş bir bildirim sesi
- Örnek: Hafif "ding", "chime" veya "bell" sesi
- Kullanım: Ürün sepete eklendiğinde, bağış tamamlandığında

### Error (Hata) Sesi
- Dikkat çekici ama rahatsız edici olmayan bir uyarı sesi
- Örnek: Kısa "buzz", "beep" veya "alert" sesi
- Kullanım: Geçersiz barkod, stok yok, yetersiz puan

## Ücretsiz Ses Kaynakları

Ücretsiz ve telif hakkı uyumlu bildirim sesleri için:
- https://notificationsounds.com/ (Creative Commons)
- https://freesound.org/ (Çeşitli lisanslar, filtrelenebilir)
- https://mixkit.co/free-sound-effects/ (Ücretsiz lisans)
- https://pixabay.com/sound-effects/ (Pixabay License)

## Kurulum Adımları

1. Yukarıdaki kaynaklardan uygun ses dosyalarını indirin
2. Dosyaları `success.mp3` ve `error.mp3` olarak yeniden adlandırın
3. Bu klasördeki placeholder dosyaların üzerine yazın
4. Tarayıcı cache'ini temizleyin
5. POS sisteminde test edin

## Test Etme

Ses dosyalarını test etmek için:
1. POS ekranını açın
2. Hızlı satış modunda bir barkod okutun (success sesi çalmalı)
3. Geçersiz bir barkod deneyin (error sesi çalmalı)

## Teknik Detaylar

Sesler `NotificationManager` JavaScript sınıfı tarafından yönetilir:
- Ses seviyesi: 0.5 (50%)
- Otomatik oynatma: Kullanıcı etkileşimi sonrası
- Hata yönetimi: Ses çalınamazsa sessizce başarısız olur

Not: Ses dosyaları telif hakkı kurallarına uygun olmalıdır.
