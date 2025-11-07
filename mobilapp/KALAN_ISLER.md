# 📋 Kalan İşler - Dijital Salon

> **Son Güncelleme:** 3 Kasım 2025  
> **Apple Developer Hesabı:** Bekleniyor ⏳

---

## ✅ Tamamlanan İşler

1. ✅ **Google Sign-In** - Backend + Frontend tamam
2. ✅ **Şifremi Unuttum** - Email doğrulama kodu ile şifre sıfırlama
3. ✅ **Dark Mode** - Tüm ekranlar dark mode uyumlu
4. ✅ **Shimmer Loading** - Instagram tarzı loading animasyonları
5. ✅ **Profil Optimizasyonu** - 48 saniye → 6 saniye
6. ✅ **Terms Agreement** - Sözleşme modalı ve checkbox
7. ✅ **Profil Fotoğrafı Güncelleme** - Session handling düzeltildi

---

## 🎯 Kalan Önemli İşler (Apple Dışında)

### 1. ⚡ Bildirim Zamanı Formatı
**Durum:** 🔴 Yapılmamış  
**Öncelik:** Yüksek  
**Süre:** ~30 dakika

**Yapılacaklar:**
- `notifications_screen.dart`'a relative time formatter ekle
- "2 saat önce", "3 gün önce", "1 hafta önce" formatı
- Flutter `intl` paketi kullanılabilir

**Dosyalar:**
- `lib/screens/notifications_screen.dart`

---

### 2. 🔍 Bildirim Filtreleme
**Durum:** 🔴 Yapılmamış  
**Öncelik:** Orta  
**Süre:** ~1 saat

**Yapılacaklar:**
- Bildirim ekranına filter chips ekle (Like, Comment, Custom, Tümü)
- Backend `get_notifications.php`'ye `type` parametresi ekle
- Flutter tarafında filtreleme state yönetimi

**Dosyalar:**
- `lib/screens/notifications_screen.dart`
- `Z:\dijitalsalon.cagapps.app\digimobiapi\get_notifications.php`

---

### 3. 🔎 Bildirim Arama
**Durum:** 🔴 Yapılmamış  
**Öncelik:** Orta  
**Süre:** ~1 saat

**Yapılacaklar:**
- Bildirim ekranına SearchBar ekle
- Kullanıcı adı, etkinlik adı, bildirim mesajına göre arama
- Real-time arama (debounce ile)

**Dosyalar:**
- `lib/screens/notifications_screen.dart`

---

### 4. 🖼️ Image Caching Optimization
**Durum:** 🔴 Yapılmamış  
**Öncelik:** Orta  
**Süre:** ~1 saat

**Yapılacaklar:**
- `CachedNetworkImage` için daha agresif cache stratejisi
- Memory cache boyutunu artır
- Disk cache boyutunu optimize et
- Image compression ayarları

**Dosyalar:**
- Tüm `CachedNetworkImage` kullanılan yerler

---

### 5. 📥 Medya İndirme
**Durum:** 🔴 Yapılmamış  
**Öncelik:** Düşük  
**Süre:** ~3 saat

**Yapılacaklar:**
- Medya detay ekranına indirme butonu
- Toplu indirme (ZIP) için backend endpoint
- Galeri entegrasyonu (Android/iOS)
- İzin kontrolü

**Dosyalar:**
- `lib/widgets/media_viewer_modal.dart`
- `Z:\dijitalsalon.cagapps.app\digimobiapi\download_media.php` (yeni)
- `Z:\dijitalsalon.cagapps.app\digimobiapi\download_zip.php` (yeni)

---

### 6. 🎬 Video Düzenleme
**Durum:** 🔴 Yapılmamış  
**Öncelik:** Düşük  
**Süre:** ~8 saat

**Yapılacaklar:**
- Video trim/crop
- Filter uygulama
- Text overlay
- Video düzenleme ekranı

**Dosyalar:**
- `lib/screens/video_editor_screen.dart` (yeni)
- Video editing package (FFmpeg wrapper)

---

## 🍎 Apple Sign-In (Developer Hesabı Bekleniyor)

**Durum:** 🟡 Backend hazır, sadece developer hesabı gerekiyor  
**Öncelik:** Orta-Yüksek  
**Süre:** ~1 saat (developer hesabı alındıktan sonra)

**Yapılacaklar:**
- Apple Developer Console'da App ID yapılandırması
- Xcode'da Signing & Capabilities ayarları
- Apple Services ID ve Domain ayarları
- Test etme

**Dosyalar:**
- `lib/screens/login_screen.dart` (zaten hazır)
- `Z:\dijitalsalon.cagapps.app\digimobiapi\oauth\apple.php` (zaten hazır)
- `APPLE_SIGN_IN_SETUP.md` (yapılandırma talimatları)

---

## 📊 Özet

**Öncelikli (Hızlı):**
1. ⚡ Bildirim Zamanı Formatı (30 dk)
2. 🔍 Bildirim Filtreleme (1 saat)
3. 🔎 Bildirim Arama (1 saat)

**Orta Öncelik:**
4. 🖼️ Image Caching Optimization (1 saat)
5. 📥 Medya İndirme (3 saat)

**Düşük Öncelik:**
6. 🎬 Video Düzenleme (8 saat)

**Bekliyor:**
🍎 Apple Sign-In (Developer hesabı alındığında 1 saat)

---

## 🎯 Hangi Göreve Başlayalım?

**Önerim:** Bildirim zamanı formatı (30 dakika, kolay ve kullanıcı deneyimi için önemli)

Hangisine başlayalım?

