# 📱 Uygulama Icon ve Modal Dokümantasyonu

## 📍 Event Detail Screen (Etkinlik Detay Sayfası)

**Dosya:** `lib/screens/event_detail_screen.dart`

### Iconlar:

1. **Kamera İkonu (FloatingActionButton)**
   - **Icon:** `Icons.camera_alt`
   - **Konum:** Sağ alt köşe (FloatingActionButton)
   - **Renk:** Beyaz (color: Colors.white)
   - **Arka Plan:** `AppColors.primary`
   - **Hero Tag:** `"event_detail_camera_fab"`
   - **Fonksiyon:** `_openCamera()` - Direkt `CameraModal.show()` açar
   - **Akış:** CameraModal → ShareModal → Upload
   - **Görünürlük:** `_canShareContent()` true ise gösterilir

2. **Galeri İkonu (FloatingActionButton - Mini)**
   - **Icon:** `Icons.photo_library`
   - **Konum:** Sağ alt köşe, kamera butonunun solunda (70px sağda, mini FAB)
   - **Renk:** Beyaz (color: Colors.white)
   - **Arka Plan:** `AppColors.primary`
   - **Hero Tag:** `"event_detail_gallery_fab"`
   - **Fonksiyon:** `_openGallery()` - Direkt `MediaSelectModal.show()` açar
   - **Akış:** MediaSelectModal → ShareModal → Upload
   - **Görünürlük:** `_canShareContent()` true ise gösterilir

2. **Profil İkonu (AppBar)**
   - **Icon:** `Icons.person`
   - **Konum:** AppBar sağ üst (actions)
   - **Renk:** `AppColors.primary`
   - **Fonksiyon:** `_openEventProfile()` - Etkinlik profil sayfasını açar

3. **Tab Iconları:**
   - `Icons.home` - Ana Sayfa sekmesi
   - `Icons.photo_library` - Medya sekmesi
   - `Icons.auto_stories` - Hikayeler sekmesi
   - `Icons.people` - Katılımcılar sekmesi

4. **Boş Durum Iconları:**
   - `Icons.photo_library_outlined` - Gönderi yok durumu

### Modallar:

1. **MediaSelectModal**
   - **Çağrılma:** `MediaSelectModal.show()` - Galeri seçimi için
   - **Açıklama:** Galeriden fotoğraf/video seçme ekranı

2. **CameraModal**
   - **Çağrılma:** `CameraModal.show()` - Kamera çekimi için
   - **Açıklama:** Kamera ile fotoğraf/video çekme ekranı

3. **ShareModal**
   - **Çağrılma:** `ShareModal.show()` - Paylaşım için
   - **Açıklama:** Açıklama ekleme ve paylaşım ekranı

4. **MediaEditorScreen**
   - **Çağrılma:** `Navigator.push(MediaEditorScreen(...))` - Düzenleme için
   - **Açıklama:** Fotoğraf/video düzenleme ekranı (crop, filtre, metin, bindirme)

5. **Seçenekler Modal (Bottom Sheet)**
   - **Iconlar:**
     - `Icons.photo_library` - "Galeriden Seç"
     - `Icons.camera_alt` - "Kamera"

---

## 🏠 Instagram Home Screen (Ana Sayfa)

**Dosya:** `lib/screens/instagram_home_screen.dart`

### Iconlar:

1. **Menü İkonu (AppBar)**
   - **Icon:** `Icons.menu`
   - **Konum:** AppBar sol üst
   - **Fonksiyon:** `_showMenu()` - Menü modalını açar

2. **Kullanıcı Arama İkonu**
   - **Icon:** `Icons.person_search`
   - **Konum:** AppBar sağ üst (actions)
   - **Fonksiyon:** `UserSearchScreen()` - Kullanıcı arama sayfasını açar

3. **Admin Panel İkonu** (sadece super_admin için)
   - **Icon:** `Icons.admin_panel_settings`
   - **Renk:** Kırmızı (Colors.red)
   - **Fonksiyon:** `AdminLogsScreen()` - Admin logları sayfasını açar

4. **QR Kod Tarayıcı İkonu**
   - **Icon:** `Icons.qr_code_scanner`
   - **Konum:** AppBar sağ üst (actions)
   - **Fonksiyon:** `_scanQRCode()` - QR kod tarayıcıyı açar

5. **Bildirim İkonu**
   - **Icon:** `Icons.favorite_border`
   - **Konum:** AppBar sağ üst (actions)
   - **Badge:** Okunmamış bildirim sayısı gösterir
   - **Fonksiyon:** `NotificationsScreen()` - Bildirimler sayfasını açar

6. **Arama İkonu**
   - **Icon:** `Icons.search`
   - **Konum:** Arama text field içinde (prefixIcon)

7. **Event İkonları:**
   - `Icons.event` - Event placeholder ikonu
   - `Icons.event_busy` - Etkinlik yok durumu
   - `Icons.people` - Katılımcı sayısı
   - `Icons.photo_library` - Medya sayısı
   - `Icons.location_on` - Konum
   - `Icons.calendar_today` - Tarih
   - `Icons.access_time` - Saat
   - `Icons.notifications_active` - Bildirim aktif

### Modallar:

1. **Menü Modal (Bottom Sheet)**
   - **Iconlar:**
     - `Icons.edit` - Profil Düzenle
     - `Icons.settings` - Ayarlar
     - `Icons.logout` - Çıkış Yap

2. **QRCodeScannerScreen**
   - **Çağrılma:** `Navigator.push(QRCodeScannerScreen())`
   - **Açıklama:** QR kod tarama ekranı

---

## 👤 Profile Screen (Profil Sayfası)

**Dosya:** `lib/screens/profile_screen.dart`

### Iconlar:

1. **Geri İkonu**
   - **Icon:** `Icons.arrow_back`
   - **Konum:** SliverAppBar sol üst (leading)
   - **Fonksiyon:** `Navigator.pop()` - Önceki sayfaya döner

2. **Kilit İkonu**
   - **Icon:** `Icons.lock_outline`
   - **Konum:** AppBar başlık yanında
   - **Açıklama:** Profil kilidi gösterimi

3. **Kullanıcı Arama İkonu**
   - **Icon:** `Icons.person_search`
   - **Konum:** AppBar sağ üst (actions)
   - **Fonksiyon:** `UserSearchScreen()` - Kullanıcı arama

4. **QR Kod Tarayıcı İkonu**
   - **Icon:** `Icons.qr_code_scanner`
   - **Konum:** AppBar sağ üst (actions)
   - **Fonksiyon:** `QRCodeScannerScreen()` - QR kod tarama

5. **Bildirim İkonu**
   - **Icon:** `Icons.favorite_border`
   - **Konum:** AppBar sağ üst (actions)
   - **Fonksiyon:** `NotificationsScreen()` - Bildirimler

6. **Tema Değiştir İkonu**
   - **Icon:** `Icons.light_mode` / `Icons.dark_mode`
   - **Konum:** AppBar sağ üst (actions)
   - **Fonksiyon:** `themeProvider.toggleTheme()` - Tema değiştirir

7. **Çıkış İkonu**
   - **Icon:** `Icons.logout`
   - **Renk:** Kırmızı (Colors.red)
   - **Konum:** AppBar sağ üst (actions)
   - **Fonksiyon:** `_showLogoutDialog()` - Çıkış dialogu

8. **Bottom Navigation Iconları:**
   - `Icons.home` - Ana Sayfa
   - `Icons.add_box_outlined` - Etkinliğe Katıl
   - `Icons.search` - Arama
   - `Icons.person` - Profil (CircleAvatar)

9. **Tab Iconları:**
   - `Icons.grid_on` - Gönderiler sekmesi
   - `Icons.event_note` - Etkinlikler sekmesi

10. **Medya Iconları:**
    - `Icons.image` - Resim placeholder
    - `Icons.play_circle_filled` - Video oynatma
    - `Icons.photo_library_outlined` - Galeri
    - `Icons.camera_alt` - Kamera
    - `Icons.delete` - Silme (kırmızı)

11. **Diğer Iconlar:**
    - `Icons.person_add` - Kullanıcı ekleme
    - `Icons.event` - Etkinlik placeholder
    - `Icons.add_circle` - Ekleme butonu
    - `Icons.close` - Kapatma

### Modallar:

1. **QRCodeScannerScreen**
   - QR kod tarama için

2. **UserSearchScreen**
   - Kullanıcı arama için

3. **NotificationsScreen**
   - Bildirimler için

4. **JoinEventScreen**
   - Etkinliğe katılma için

5. **MediaViewerModal**
   - Medya görüntüleme için

6. **StoryViewerModal**
   - Hikaye görüntüleme için

---

## 📸 Media Select Modal (Medya Seçim Modalı)

**Dosya:** `lib/widgets/media_select_modal.dart`

### Iconlar:

1. **Kapat İkonu**
   - **Icon:** `Icons.close`
   - **Konum:** AppBar sol üst
   - **Fonksiyon:** Modal'ı kapatır

2. **Galeri İkonu**
   - **Icon:** `Icons.photo_library`
   - **Konum:** Boş durum gösterimi
   - **Açıklama:** Medya seçilmediğinde gösterilir

3. **Grid İkonu**
   - **Icon:** `Icons.grid_view`
   - **Konum:** "Yakınlardakiler" başlığı yanında
   - **Açıklama:** Grid görünümü

4. **Video İkonları:**
   - `Icons.play_arrow` - Video oynatma ikonu (thumbnail'da)

---

## 📷 Camera Modal (Kamera Modalı)

**Dosya:** `lib/widgets/camera_modal.dart`

### Iconlar:

1. **Kapat İkonu**
   - **Icon:** `Icons.close`
   - **Konum:** Üst sol
   - **Fonksiyon:** Kamera modalını kapatır

2. **Flaş İkonları:**
   - `Icons.flash_on` - Flaş açık
   - `Icons.flash_off` - Flaş kapalı
   - `Icons.flash_auto` - Flaş otomatik
   - **Konum:** Üst orta
   - **Fonksiyon:** `_toggleFlash()` - Flaş modunu değiştirir

3. **Galeri İkonu**
   - **Icon:** `Icons.photo_library`
   - **Konum:** Üst sağ
   - **Fonksiyon:** `_openGallery()` - Galeri seçim ekranını açar

4. **Çekim Modu İkonları (Sol tarafta):**
   - `Icons.camera_alt` - Normal mod
   - `Icons.autorenew` - Boomerang modu
   - `Icons.grid_view` - Yerleşim modu
   - `Icons.text_fields` - Metin modu

5. **Kamera Değiştir İkonu**
   - **Icon:** `Icons.cameraswitch`
   - **Konum:** Alt sağ
   - **Fonksiyon:** `_switchCamera()` - Ön/arka kamera değiştirir

6. **Foto İkonu**
   - **Icon:** `Icons.photo`
   - **Konum:** Alt sol (galeri önizleme)

---

## 📤 Share Modal (Paylaşım Modalı)

**Dosya:** `lib/widgets/share_modal.dart`

### Iconlar:

1. **Kapat İkonu**
   - **Icon:** `Icons.close`
   - **Konum:** AppBar sol üst
   - **Fonksiyon:** Modal'ı kapatır

2. **Video Oynatma İkonları:**
   - `Icons.play_arrow` - Oynat
   - `Icons.pause` - Duraklat

3. **Seçenek İkonları:**
   - `Icons.person_add` - Kişileri Etiketle
   - `Icons.location_on` - Konum Ekle
   - `Icons.music_note` - Müzik Ekle
   - `Icons.people` - Kimler görebilir?

4. **Chevron İkonu**
   - **Icon:** `Icons.chevron_right`
   - **Konum:** Seçenek satırlarının sağında

---

## ✏️ Media Editor Screen (Medya Düzenleme Ekranı)

**Dosya:** `lib/screens/media_editor_screen.dart`

### Iconlar:

1. **Kapat İkonu**
   - **Icon:** `Icons.close`
   - **Konum:** AppBar sol üst
   - **Fonksiyon:** Düzenleme ekranını kapatır

2. **Onay İkonu**
   - **Icon:** `Icons.check`
   - **Konum:** AppBar sağ üst
   - **Fonksiyon:** Düzenlemeyi kaydeder

3. **Araç İkonları (Alt bar):**
   - `Icons.crop` - Kesme (Crop)
   - `Icons.tune` - Filtre
   - `Icons.text_fields` - Metin
   - `Icons.layers` - Bindirme

4. **Bindirme İkonları:**
   - `Icons.close` - Bindirme kaldır
   - Emoji ikonları (❤️, ⭐, 😊, ☁️, 🔥, ⚡)

---

## 🔔 Notifications Screen (Bildirimler Sayfası)

**Dosya:** `lib/screens/notifications_screen.dart`

### Iconlar:

1. **Geri İkonu**
   - **Icon:** `Icons.arrow_back`
   - **Konum:** AppBar sol üst

2. **Başlık İkonu**
   - **Icon:** `Icons.favorite_border` veya `Icons.favorite`
   - **Konum:** AppBar başlık yanında

---

## 🔍 User Search Screen (Kullanıcı Arama Sayfası)

**Dosya:** `lib/screens/user_search_screen.dart`

### Iconlar:

1. **Geri İkonu**
   - **Icon:** `Icons.arrow_back`
   - **Konum:** AppBar sol üst

2. **Arama İkonu**
   - **Icon:** `Icons.search`
   - **Konum:** Arama field içinde

---

## 📋 QR Code Scanner Screen (QR Kod Tarayıcı)

**Dosya:** `lib/screens/qr_code_scanner_screen.dart`

### Iconlar:

1. **Geri İkonu**
   - **Icon:** `Icons.arrow_back`
   - **Konum:** AppBar sol üst

2. **Flaş İkonları:**
   - `Icons.flash_on` / `Icons.flash_off`
   - **Konum:** Alt ortada

---

## 📝 Join Event Screen (Etkinliğe Katıl Sayfası)

**Dosya:** `lib/screens/join_event_screen.dart`

### Iconlar:

1. **Geri İkonu**
   - **Icon:** `Icons.arrow_back`
   - **Konum:** AppBar sol üst

---

## 🎨 Widget Iconları

### Instagram Post Card
**Dosya:** `lib/widgets/instagram_post_card.dart`

- `Icons.favorite` / `Icons.favorite_border` - Beğeni
- `Icons.comment` - Yorum
- `Icons.send` - Paylaş
- `Icons.bookmark` / `Icons.bookmark_border` - Kaydet
- `Icons.more_vert` - Daha fazla seçenek

### Instagram Stories Bar
**Dosya:** `lib/widgets/instagram_stories_bar.dart`

- `Icons.add` - Hikaye ekle
- `Icons.play_circle` - Hikaye oynat

### Comments Modal
**Dosya:** `lib/widgets/comments_modal.dart`

- `Icons.close` - Kapat
- `Icons.send` - Yorum gönder
- `Icons.more_vert` - Daha fazla seçenek

### Story Viewer Modal
**Dosya:** `lib/widgets/story_viewer_modal.dart`

- `Icons.close` - Kapat
- `Icons.pause` - Duraklat
- `Icons.play_arrow` - Oynat

### Error Modal
**Dosya:** `lib/widgets/error_modal.dart`

- `Icons.error_outline` - Hata ikonu
- `Icons.close` - Kapat

### Success Modal
**Dosya:** `lib/widgets/success_modal.dart`

- `Icons.check_circle` - Başarı ikonu

### Permission Grant Modal
**Dosya:** `lib/widgets/permission_grant_modal.dart`

- `Icons.lock` - İzin ikonu

---

## 🎯 Özet: Tüm Iconların Listesi

### Navigasyon Iconları:
- `Icons.arrow_back` - Geri git
- `Icons.close` - Kapat
- `Icons.check` - Onayla
- `Icons.menu` - Menü

### Medya Iconları:
- `Icons.camera_alt` - Kamera
- `Icons.photo_library` - Galeri
- `Icons.videocam` - Video kamera
- `Icons.image` - Resim
- `Icons.play_arrow` - Oynat
- `Icons.pause` - Duraklat
- `Icons.crop` - Kesme
- `Icons.tune` - Filtre
- `Icons.text_fields` - Metin

### Sosyal Iconlar:
- `Icons.favorite` / `Icons.favorite_border` - Beğeni
- `Icons.comment` - Yorum
- `Icons.send` - Gönder
- `Icons.bookmark` - Kaydet
- `Icons.share` - Paylaş

### Kullanıcı Iconları:
- `Icons.person` - Kullanıcı
- `Icons.person_search` - Kullanıcı ara
- `Icons.person_add` - Kullanıcı ekle
- `Icons.people` - Kullanıcılar

### Etkinlik Iconları:
- `Icons.event` - Etkinlik
- `Icons.event_busy` - Etkinlik yok
- `Icons.calendar_today` - Takvim
- `Icons.access_time` - Saat
- `Icons.location_on` - Konum

### Sistem Iconları:
- `Icons.settings` - Ayarlar
- `Icons.logout` - Çıkış
- `Icons.notifications_active` - Bildirim
- `Icons.admin_panel_settings` - Admin
- `Icons.qr_code_scanner` - QR kod
- `Icons.lock_outline` - Kilit
- `Icons.light_mode` / `Icons.dark_mode` - Tema

### Diğer Iconlar:
- `Icons.search` - Arama
- `Icons.more_vert` - Daha fazla
- `Icons.grid_view` - Grid görünümü
- `Icons.home` - Ana sayfa
- `Icons.add` - Ekle
- `Icons.delete` - Sil
- `Icons.check_circle` - Başarı
- `Icons.error_outline` - Hata

---

## 📱 Modal ve Screen Listesi

### Screens (Ekranlar):
1. `EventDetailScreen` - Etkinlik detay sayfası
2. `InstagramHomeScreen` - Ana sayfa
3. `ProfileScreen` - Profil sayfası
4. `NotificationsScreen` - Bildirimler
5. `UserSearchScreen` - Kullanıcı arama
6. `QRCodeScannerScreen` - QR kod tarayıcı
7. `JoinEventScreen` - Etkinliğe katıl
8. `MediaEditorScreen` - Medya düzenleme
9. `LoginScreen` - Giriş
10. `RegisterScreen` - Kayıt
11. `ForgotPasswordScreen` - Şifre unuttum
12. `VerifyCodeScreen` - Kod doğrulama
13. `ResetPasswordScreen` - Şifre sıfırlama

### Modals (Modallar):
1. `MediaSelectModal` - Medya seçim modalı
2. `CameraModal` - Kamera modalı
3. `ShareModal` - Paylaşım modalı
4. `ModernShareModal` - Modern paylaşım modalı (eski)
5. `CommentsModal` - Yorumlar modalı
6. `StoryViewerModal` - Hikaye görüntüleme modalı
7. `MediaViewerModal` - Medya görüntüleme modalı
8. `ErrorModal` - Hata modalı
9. `SuccessModal` - Başarı modalı
10. `PermissionGrantModal` - İzin modalı
11. `TermsAgreementModal` - Kullanım şartları modalı

---

## 🔗 Icon ve Modal İlişkileri

### Event Detail Screen:
- **Kamera İkonu (FAB)** → Direkt `CameraModal` açar → `ShareModal` → Upload
- **Galeri İkonu (Mini FAB)** → Direkt `MediaSelectModal` açar → `ShareModal` → Upload
- **Profil İkonu** → `EventProfileScreen` açar

### Instagram Home Screen:
- **Menü İkonu** → Menü bottom sheet açar
- **QR İkonu** → `QRCodeScannerScreen` açar
- **Bildirim İkonu** → `NotificationsScreen` açar
- **Arama İkonu** → `UserSearchScreen` açar

### Profile Screen:
- **QR İkonu** → `QRCodeScannerScreen` açar
- **Bildirim İkonu** → `NotificationsScreen` açar
- **Arama İkonu** → `UserSearchScreen` açar
- **Tema İkonu** → Tema değiştirir

---

## 📌 Kullanım Notları

1. **Event Detail Screen'deki kamera ikonu:**
   - Icon: `Icons.camera_alt`
   - FloatingActionButton olarak sağ alt köşede
   - `_showCameraOptions()` fonksiyonunu çağırır
   - Bu fonksiyon `MediaSelectModal` veya `CameraModal` açar

2. **Tüm iconlar Material Design Icons kullanıyor**

3. **Modal çağrıları genellikle:**
   - `showModalBottomSheet()` - Bottom sheet için
   - `Navigator.push()` - Full screen için
   - `Modal.show()` - Özel modal widget'ları için

4. **Icon renkleri:**
   - Varsayılan: Temaya göre (dark/light)
   - Özel: `AppColors.primary` veya `Colors.white` gibi sabit renkler

---

**Son Güncelleme:** 2025-11-04
**Not:** Bu dokümantasyon tüm uygulamadaki icon ve modalları içerir. Yeni eklenenler için güncellenmelidir.

