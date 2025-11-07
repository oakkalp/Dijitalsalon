# 📱 Medya Paylaşım Akışı Dokümantasyonu

## 🎯 Genel Bakış

Instagram benzeri medya paylaşım akışı, üç ana ekrandan oluşur:
1. **MediaSelectModal** - Galeri seçimi
2. **CameraModal** - Kamera çekimi
3. **ShareModal** - Paylaşım ve açıklama

## 📋 Ekranlar ve Özellikleri

### 1️⃣ MediaSelectModal (Medya Seçimi Ekranı)

**Dosya:** `lib/widgets/media_select_modal.dart`

**Ne İşe Yarar:**
- Kullanıcının galeriden fotoğraf veya video seçmesini sağlar
- Üstte büyük önizleme alanı gösterir
- Alt kısımda galeri grid görünümü sunar

**Bileşenler:**
- ✅ Üstte büyük medya önizleme alanı (fotoğraf veya video)
- ✅ Alt kısımda 3 sütunlu galeri grid görünümü
- ✅ "Yakınlardakiler" başlığı ve filtreleme seçenekleri
- ✅ "BİRDEN FAZLA SEÇ" seçeneği (gelecek özellik)
- ✅ Sağ üstte "İleri" butonu (medya seçildiğinde aktif)
- ✅ Video önizleme desteği (VideoPlayerController ile)

**Nasıl Çağrılır:**
```dart
await MediaSelectModal.show(
  context,
  onMediaSelected: (File file) {
    // Seçilen dosya ile işlem yap
  },
  shareType: 'post', // 'post', 'story', 'reels'
);
```

**Akış Sırası:**
1. Kullanıcı galeri butonuna basar
2. MediaSelectModal açılır
3. Kullanıcı bir medya seçer
4. Üstte önizleme gösterilir
5. "İleri" butonuna basılır
6. Seçilen dosya `onMediaSelected` callback'i ile döndürülür

---

### 2️⃣ CameraModal (Kamera Ekranı)

**Dosya:** `lib/widgets/camera_modal.dart`

**Ne İşe Yarar:**
- Kullanıcının kamera ile fotoğraf çekmesini veya video kaydetmesini sağlar
- Çeşitli çekim modları sunar (Normal, Boomerang, Yerleşim, Metin)
- Instagram benzeri kamera arayüzü

**Bileşenler:**
- ✅ Sol tarafta çekim modları (Normal, Boomerang, Yerleşim, Metin)
- ✅ Ortada büyük kamera önizlemesi (CameraPreview)
- ✅ Üstte kontroller: Kapat (X), Flaş, Galeri
- ✅ Alt ortada büyük dairesel çekim butonu
- ✅ Sağ altta kamera değiştir butonu
- ✅ Alt kısımda paylaşım türü seçimi (GÖNDERİ, HİKAYE, REELS)
- ✅ Sol altta galeri önizleme thumbnail'ları

**Nasıl Çağrılır:**
```dart
await CameraModal.show(
  context,
  onMediaCaptured: (File file) {
    // Çekilen dosya ile işlem yap
  },
  shareType: 'post', // 'post', 'story', 'reels'
);
```

**Akış Sırası:**
1. Kullanıcı kamera butonuna basar
2. CameraModal açılır ve kamera başlatılır
3. Kullanıcı çekim modunu seçer (Normal, Boomerang, vb.)
4. Çekim butonuna basar (fotoğraf) veya basılı tutar (video)
5. Çekilen dosya `onMediaCaptured` callback'i ile döndürülür

**Özellikler:**
- ✅ Ön/arka kamera değiştirme
- ✅ Flaş modu (Otomatik, Açık, Kapalı)
- ✅ Galeri butonu (MediaSelectModal'a yönlendirir)
- ✅ Video kayıt sırasında kırmızı gösterge

---

### 3️⃣ ShareModal (Paylaşım Ekranı)

**Dosya:** `lib/widgets/share_modal.dart`

**Ne İşe Yarar:**
- Seçilen/çekilen medyanın son gözden geçirme ve paylaşım ekranı
- Açıklama ekleme
- Etiketleme, konum, müzik gibi ek seçenekler

**Bileşenler:**
- ✅ Üstte medya önizlemesi (fotoğraf veya video)
- ✅ Video için oynat/durdur butonu
- ✅ Açıklama yazma alanı (çok satırlı TextField)
- ✅ Kişileri Etiketle seçeneği
- ✅ Konum Ekle seçeneği
- ✅ Müzik Ekle seçeneği (sadece hikaye için)
- ✅ Kimler görebilir? seçeneği
- ✅ Sağ üstte "Paylaş" butonu

**Nasıl Çağrılır:**
```dart
await ShareModal.show(
  context,
  mediaFile: File('/path/to/file'),
  onShare: (String description, Map<String, dynamic>? tags) {
    // Paylaşım işlemi
  },
  shareType: 'post', // 'post', 'story', 'reels'
);
```

**Akış Sırası:**
1. MediaSelectModal veya CameraModal'dan dosya seçilir
2. MediaEditorScreen'de düzenleme yapılır (opsiyonel)
3. ShareModal açılır
4. Kullanıcı açıklama yazar ve ek seçenekleri ayarlar
5. "Paylaş" butonuna basılır
6. `onShare` callback'i çağrılır ve paylaşım yapılır

---

## 🔄 Tam Akış Sırası

### Senaryo 1: Galeriden Seçim
```
EventDetailScreen
  ↓
Seçenekler Modal (Galeriden Seç / Kamera)
  ↓
MediaSelectModal (Galeri seçimi)
  ↓
MediaEditorScreen (Düzenleme - opsiyonel)
  ↓
ShareModal (Paylaşım)
  ↓
Upload işlemi
```

### Senaryo 2: Kamera ile Çekim
```
EventDetailScreen
  ↓
Seçenekler Modal (Galeriden Seç / Kamera)
  ↓
CameraModal (Kamera çekimi)
  ↓
MediaEditorScreen (Düzenleme - opsiyonel)
  ↓
ShareModal (Paylaşım)
  ↓
Upload işlemi
```

## 🎨 Tasarım Özellikleri

- ✅ **Koyu tema**: Tüm ekranlar siyah arka plan
- ✅ **Minimalist ikonlar**: Instagram benzeri basit ikonlar
- ✅ **16px boşluklar**: UI bileşenleri arasında tutarlı boşluklar
- ✅ **12px border-radius**: Köşeler yuvarlatılmış
- ✅ **Yumuşak animasyonlar**: Ekran geçişleri fadeIn/slideUp animasyonları ile

## 📝 Notlar

- MediaEditorScreen opsiyoneldir - kullanıcı düzenleme yapmak istemezse atlanabilir
- ShareModal'daki etiketleme, konum, müzik özellikleri şu an placeholder - gelecekte implement edilecek
- Video önizlemeleri için VideoPlayerController kullanılıyor
- Tüm ekranlar fullscreen dialog olarak açılıyor (Instagram benzeri)


