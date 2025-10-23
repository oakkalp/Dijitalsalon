# 📱 Flutter Uygulaması Ekran Dokümantasyonu

## 🎯 Genel Bakış
Bu dokümantasyon, Digital Salon Flutter uygulamasındaki tüm ekranları ve özelliklerini detaylı olarak açıklar.

---

## 📋 Ana Ekranlar (Screens)

### 1. `login_screen.dart` - Giriş Ekranı
**Dosya Yolu:** `lib/screens/login_screen.dart`

**İçerik:**
- Kullanıcı girişi için email/şifre alanları
- "Beni Hatırla" checkbox
- Giriş butonu
- Kayıt olma linki

**Özellikler:**
- ✅ Form validasyonu
- ✅ Session yönetimi
- ✅ Hata mesajları
- ✅ Loading durumu

**Kullanım:** Uygulama açılışında ilk görünen ekran

---

### 2. `events_screen.dart` - Etkinlikler Listesi
**Dosya Yolu:** `lib/screens/events_screen.dart`

**İçerik:**
- Katıldığın etkinliklerin listesi
- QR kod ile etkinliğe katılma butonu
- Etkinlik kartları

**Özellikler:**
- ✅ Etkinlik kartları (başlık, tarih, katılımcı sayısı)
- ✅ QR kod tarama butonu
- ✅ "Etkinlik Yok" durumu
- ✅ Etkinlik detayına gitme

**Kullanım:** Login sonrası ana ekran

---

### 3. `event_detail_screen.dart` - Etkinlik Detayı (Event Profile)
**Dosya Yolu:** `lib/screens/event_detail_screen.dart`

**İçerik:**
- Instagram tarzı etkinlik profili
- Stories bar (hikayeler)
- Medya gönderileri (posts)
- Alt navigasyon

**Özellikler:**
- ✅ **Stories Bar** → Hikayeler çubuğu
- ✅ **Post Cards** → Medya gönderileri
- ✅ **Like/Comment** → Beğeni/yorum sistemi
- ✅ **Add Media** → Medya ekleme (+ butonu)
- ✅ **Add Story** → Hikaye ekleme (kamera butonu)
- ✅ **Edit/Delete** → Medya düzenleme/silme (3 dots menü)
- ✅ **Pagination** → Sayfalama sistemi
- ✅ **Real-time Refresh** → Anlık yenileme

**Kullanım:** Etkinlik kartına tıklandığında

---

### 4. `join_event_screen.dart` - Etkinliğe Katılma
**Dosya Yolu:** `lib/screens/join_event_screen.dart`

**İçerik:**
- QR kod ile etkinliğe katılma
- Manuel QR kod girişi
- Etkinlik bilgileri

**Özellikler:**
- ✅ QR kod tarama
- ✅ Manuel QR kod girişi
- ✅ Etkinlik bilgileri gösterimi
- ✅ Katılma butonu
- ✅ Hata mesajları

**Kullanım:** Events ekranından QR kod butonuna tıklandığında

---

### 5. `qr_scanner_screen.dart` - QR Kod Tarayıcı
**Dosya Yolu:** `lib/screens/qr_scanner_screen.dart`

**İçerik:**
- Kamera ile QR kod okuma
- Tarama sonucu işleme

**Özellikler:**
- ✅ Kamera preview
- ✅ QR kod tespit
- ✅ Tarama sonucu işleme
- ✅ Hata yönetimi

**Kullanım:** QR kod tarama için

---

### 6. `profile_screen.dart` - Profil Ekranı
**Dosya Yolu:** `lib/screens/profile_screen.dart`

**İçerik:**
- Kullanıcı profili
- Etkinlik geçmişi
- Çıkış yapma

**Özellikler:**
- ✅ Profil fotoğrafı
- ✅ Kullanıcı bilgileri
- ✅ Etkinlik geçmişi
- ✅ Çıkış yapma butonu

**Kullanım:** Alt navigasyondan profil sekmesi

---

### 7. `instagram_home_screen.dart` - Instagram Ana Ekran
**Dosya Yolu:** `lib/screens/instagram_home_screen.dart`

**İçerik:**
- Instagram tarzı ana sayfa
- Stories bar
- Post feed

**Özellikler:**
- ✅ Stories bar
- ✅ Post feed
- ✅ Alt navigasyon

**Kullanım:** Ana ekran (şu an kullanılmıyor)

---

### 8. `instagram_profile_screen.dart` - Instagram Profil
**Dosya Yolu:** `lib/screens/instagram_profile_screen.dart`

**İçerik:**
- Instagram tarzı profil
- Profil grid
- Tab bar

**Özellikler:**
- ✅ Profil grid
- ✅ Tab bar (gönderiler/hikayeler)
- ✅ Instagram tasarımı

**Kullanım:** Profil görünümü (şu an kullanılmıyor)

---

## 🧩 Widget'lar (Modals & Components)

### 1. `comments_modal.dart` - Yorumlar Modal
**Dosya Yolu:** `lib/widgets/comments_modal.dart`

**İçerik:**
- Gönderi yorumları
- Yorum ekleme
- Yanıt verme sistemi

**Özellikler:**
- ✅ **Yorum Listesi** → Tüm yorumları gösterir
- ✅ **Yorum Ekleme** → Yeni yorum ekleme
- ✅ **Yanıt Verme** → Yorumlara yanıt verme (replies)
- ✅ **Nested Replies** → Yanıtlara yanıt verme
- ✅ **Yorum Beğenme** → Yorumları beğenme
- ✅ **Yorum Silme/Düzenleme** → Yetkili kullanıcılar için
- ✅ **Real-time Updates** → Anlık güncelleme
- ✅ **Pagination** → Sayfalama

**Kullanım:** Post'taki yorum butonuna tıklandığında

---

### 2. `story_viewer_modal.dart` - Hikaye Görüntüleyici
**Dosya Yolu:** `lib/widgets/story_viewer_modal.dart`

**İçerik:**
- Tam ekran hikaye izleme
- Progress bar'lar
- Hikaye etkileşimi

**Özellikler:**
- ✅ **Full Screen** → Tam ekran hikaye
- ✅ **Progress Bars** → İlerleme çubukları
- ✅ **Auto Play** → Otomatik oynatma
- ✅ **Swipe Navigation** → Kaydırma geçişi
- ✅ **Like/Comment** → Hikaye etkileşimi
- ✅ **Edit/Delete** → Hikaye düzenleme/silme
- ✅ **Duration Control** → Foto: 24s, Video: 59s
- ✅ **Play/Pause** → Oynatma kontrolü

**Kullanım:** Stories bar'daki hikayeye tıklandığında

---

### 3. `instagram_stories_bar.dart` - Stories Bar
**Dosya Yolu:** `lib/widgets/instagram_stories_bar.dart`

**İçerik:**
- Üstteki hikayeler çubuğu
- Kullanıcı avatarları
- Hikaye ekleme

**Özellikler:**
- ✅ **Kullanıcı Avatarları** → Profil fotoğrafları
- ✅ **Hikaye Sayısı** → Her kullanıcının hikaye sayısı
- ✅ **Hikaye Ekleme** → + butonu ile yeni hikaye
- ✅ **Hikaye Tıklama** → Hikaye görüntüleyici açma
- ✅ **Viewed Indicator** → Görülen hikayeler işareti

**Kullanım:** Event detail ekranında

---

### 4. `instagram_post_card.dart` - Post Kartı
**Dosya Yolu:** `lib/widgets/instagram_post_card.dart`

**İçerik:**
- Instagram tarzı gönderi kartı
- Medya görüntüleme
- Etkileşim butonları

**Özellikler:**
- ✅ **Kullanıcı Bilgileri** → Avatar, isim
- ✅ **Medya Görüntüleme** → Fotoğraf/video
- ✅ **Beğeni Butonu** → Kalp ikonu
- ✅ **Yorum Butonu** → CupertinoIcons.chat_bubble_2
- ✅ **Paylaş Butonu** → Gönder ikonu
- ✅ **Kaydet Butonu** → Bookmark ikonu
- ✅ **Medya Silme/Düzenleme** → 3 dots menü
- ✅ **Yorum Sayısı Badge'i** → Kırmızı daire
- ✅ **Real-time Refresh** → Silme sonrası yenileme

**Kullanım:** Event detail ekranında post'lar için

---

### 5. `post_card.dart` - Eski Post Kartı
**Dosya Yolu:** `lib/widgets/post_card.dart`

**İçerik:**
- Eski tema post kartı
- Cupertino tasarımı

**Özellikler:**
- ✅ CupertinoIcons kullanımı
- ✅ Eski tema tasarımı
- ✅ Medya görüntüleme

**Kullanım:** Eski tema için (şu an kullanılmıyor)

---

### 6. `robust_image_widget.dart` - Güçlü Resim Widget'ı
**Dosya Yolu:** `lib/widgets/robust_image_widget.dart`

**İçerik:**
- Hata toleranslı resim yükleme
- Fallback sistemi

**Özellikler:**
- ✅ **CachedNetworkImage** → Önbellekli resim
- ✅ **Fallback System** → Hata durumunda alternatif
- ✅ **Loading States** → Yükleme durumları
- ✅ **Error Handling** → Hata yönetimi

**Kullanım:** Tüm resim yüklemelerinde

---

### 7. `gradient_background.dart` - Gradient Arka Plan
**Dosya Yolu:** `lib/widgets/gradient_background.dart`

**İçerik:**
- Gradient arka plan widget'ı

**Özellikler:**
- ✅ Gradient renkler
- ✅ Özelleştirilebilir

**Kullanım:** Arka plan için

---

### 8. `sociogram_bottom_nav.dart` - Alt Navigasyon
**Dosya Yolu:** `lib/widgets/sociogram_bottom_nav.dart`

**İçerik:**
- Alt navigasyon çubuğu

**Özellikler:**
- ✅ Ana sayfa
- ✅ Profil
- ✅ Etkinlikler

**Kullanım:** Ana navigasyon için

---

### 9. `stories_bar.dart` - Eski Stories Bar
**Dosya Yolu:** `lib/widgets/stories_bar.dart`

**İçerik:**
- Eski tema stories bar

**Özellikler:**
- ✅ Eski tema tasarımı
- ✅ Hikaye görüntüleme

**Kullanım:** Eski tema için (şu an kullanılmıyor)

---

## 🎯 Ekran Akışı (Navigation Flow)

```
1. login_screen.dart (Giriş)
   ↓
2. events_screen.dart (Etkinlikler Listesi)
   ↓
3. event_detail_screen.dart (Etkinlik Detayı)
   ├── instagram_stories_bar.dart (Stories)
   ├── instagram_post_card.dart (Posts)
   ├── comments_modal.dart (Yorumlar)
   └── story_viewer_modal.dart (Hikaye İzleme)
   ↓
4. join_event_screen.dart (Etkinliğe Katılma)
   └── qr_scanner_screen.dart (QR Tarama)
   ↓
5. profile_screen.dart (Profil)
```

---

## 🔧 Ana Özellikler

### Event Detail Screen (Ana Ekran):
- ✅ **Stories Bar** → Hikayeler çubuğu
- ✅ **Post Cards** → Medya gönderileri
- ✅ **Like/Comment** → Beğeni/yorum sistemi
- ✅ **Add Media** → Medya ekleme (+)
- ✅ **Add Story** → Hikaye ekleme (kamera)
- ✅ **Edit/Delete** → Medya düzenleme/silme
- ✅ **Real-time Refresh** → Anlık yenileme
- ✅ **Pagination** → Sayfalama

### Comments Modal:
- ✅ **Comment List** → Yorum listesi
- ✅ **Add Comment** → Yorum ekleme
- ✅ **Replies** → Yanıt verme
- ✅ **Nested Replies** → Yanıtlara yanıt
- ✅ **Like Comments** → Yorum beğenme
- ✅ **Edit/Delete** → Yorum düzenleme/silme
- ✅ **Real-time Updates** → Anlık güncelleme

### Story Viewer:
- ✅ **Full Screen** → Tam ekran hikaye
- ✅ **Progress Bars** → İlerleme çubukları
- ✅ **Auto Play** → Otomatik oynatma
- ✅ **Swipe Navigation** → Kaydırma geçişi
- ✅ **Like/Comment** → Hikaye etkileşimi
- ✅ **Edit/Delete** → Hikaye düzenleme/silme
- ✅ **Duration Control** → Süre kontrolü

---

## 📱 Kullanım Örnekleri

### "Event Detail Screen'deki Stories Bar'da"
- Hikayeler çubuğundaki özellikler
- Kullanıcı avatarları
- Hikaye ekleme butonu

### "Comments Modal'daki Yorum Ekleme Kısmında"
- Yorum yazma alanı
- Gönder butonu
- Yanıt verme sistemi

### "Post Card'daki 3 Dots Menüsünde"
- Düzenle seçeneği
- Sil seçeneği
- Yetki kontrolü

### "Story Viewer'daki Progress Bar'larda"
- İlerleme çubukları
- Otomatik geçiş
- Manuel kontrol

---

## 🎨 Tasarım Sistemi

### Renkler:
- **Primary:** Ana renk
- **Success:** Başarı mesajları
- **Error:** Hata mesajları
- **Info:** Bilgi mesajları

### İkonlar:
- **CupertinoIcons.chat_bubble_2** → Mesaj butonu (eski tema)
- **Icons.favorite** → Beğeni butonu
- **Icons.send** → Paylaş butonu
- **Icons.bookmark_border** → Kaydet butonu

### Boyutlar:
- **Post Card:** Tam genişlik
- **Stories Bar:** Üst kısım
- **Modal:** Tam ekran
- **Buttons:** 28px ikon boyutu

---

## 🔄 Real-time Özellikler

### Medya Silme:
1. 3 dots menüden "Sil" seçeneği
2. Onay dialog'u
3. API çağrısı
4. Callback ile parent widget yenileme
5. UI'dan medya kaldırma

### Yorum Ekleme:
1. Yorum yazma alanı
2. Gönder butonu
3. API çağrısı
4. Local state güncelleme
5. Modal içinde anlık görünüm

### Hikaye Ekleme:
1. Kamera butonu
2. Fotoğraf/video çekme
3. Açıklama ekleme
4. API upload
5. Stories bar'da anlık görünüm

---

## 📝 Notlar

- **Instagram Theme:** Ana ekran Instagram tarzında
- **Cupertino Icons:** Eski tema ikonları kullanılıyor
- **Real-time:** Tüm işlemler anlık güncelleniyor
- **Responsive:** Tüm ekran boyutlarına uyumlu
- **Error Handling:** Kapsamlı hata yönetimi
- **Loading States:** Yükleme durumları gösteriliyor

---

## 🚀 Geliştirme Notları

### Yapılacaklar:
- [ ] Video oynatma optimizasyonu
- [ ] Offline mod desteği
- [ ] Push notification
- [ ] Dark mode
- [ ] Çoklu dil desteği

### Bilinen Sorunlar:
- [ ] Bazı büyük dosyalarda yükleme sorunu
- [ ] Emülatörde kamera sorunu
- [ ] Network timeout durumları

---

*Son güncelleme: 2025-01-22*
*Versiyon: 1.0.0*
