# Digital Salon Mobile App

<div align="center">

![Digital Salon Feature](digisalon/dijital_salon_feature.png)

**Modern ve kullanıcı dostu etkinlik yönetim uygulaması**

[![Flutter](https://img.shields.io/badge/Flutter-3.0+-blue.svg)](https://flutter.dev)
[![Android](https://img.shields.io/badge/Android-5.0+-green.svg)](https://www.android.com)
[![iOS](https://img.shields.io/badge/iOS-12.0+-lightgrey.svg)](https://www.apple.com/ios)

</div>

---

## 📱 Ekran Görüntüleri

<div align="center">

### Ana Ekranlar

![01](digisalon/01.png)
![02](digisalon/02.png)
![03](digisalon/03.png)

### Etkinlik Özellikleri

![04](digisalon/04.png)
![05](digisalon/05.png)
![06](digisalon/06.png)

### QR Kod ve Davet

![davetqrı](digisalon/davetqrı.png)

### Paylaşım Modalı

![paylasımmodal](digisalon/paylasımmodal.png)

### Kullanıcı Arama

![kullanıcı arama](digisalon/kullanıcı%20arama.png)

</div>

---

## ✨ Özellikler

### 🎯 Temel Özellikler
- ✅ Modern ve kullanıcı dostu arayüz
- ✅ Dark Mode desteği
- ✅ Gerçek zamanlı etkinlik güncellemeleri
- ✅ QR kod ile etkinlik katılımı
- ✅ Medya paylaşımı (fotoğraf ve video)
- ✅ Hikaye (Story) özelliği
- ✅ Yorum ve beğeni sistemi
- ✅ Bildirim sistemi

### 📸 Medya Özellikleri
- ✅ Kamera ile fotoğraf/video çekme
- ✅ Galeriden medya seçimi
- ✅ Medya düzenleme (filtreler, metin ekleme)
- ✅ Thumbnail ve preview desteği
- ✅ Medya limitleri kontrolü

### 🔐 Güvenlik ve Kimlik Doğrulama
- ✅ Email/Şifre ile giriş
- ✅ Google Sign-In entegrasyonu
- ✅ Apple Sign-In entegrasyonu
- ✅ Güvenli oturum yönetimi
- ✅ Otomatik oturum yenileme

### 👥 Sosyal Özellikler
- ✅ Kullanıcı profilleri
- ✅ Etkinlik katılımcı listesi
- ✅ Kullanıcı arama
- ✅ Bildirim sistemi
- ✅ Yorum ve beğeni sistemi

---

## 🚀 Kurulum

### Gereksinimler
- Flutter SDK 3.0 veya üzeri
- Dart SDK 3.0 veya üzeri
- Android Studio / Xcode
- Firebase hesabı

### Adımlar

1. **Projeyi klonlayın**
```bash
git clone https://github.com/yourusername/digimobil_new.git
cd digimobil_new
```

2. **Bağımlılıkları yükleyin**
```bash
flutter pub get
```

3. **Firebase yapılandırması**
   - `google-services.json` dosyasını `android/app/` klasörüne ekleyin
   - Firebase Console'dan SHA-1 fingerprint'lerini ekleyin

4. **Uygulamayı çalıştırın**
```bash
flutter run
```

---

## 📦 Build

### APK Build
```bash
flutter build apk --release
```

### App Bundle (Play Store)
```bash
flutter build appbundle --release
```

---

## 🛠️ Teknolojiler

### Frontend
- **Flutter** - Cross-platform framework
- **Dart** - Programlama dili
- **Provider** - State management
- **Material Design 3** - UI framework

### Backend Integration
- **REST API** - Backend servisleri
- **Firebase** - Authentication, Cloud Messaging
- **Session Management** - Güvenli oturum yönetimi

### Paketler
- `mobile_scanner` - QR kod tarama
- `camera` - Kamera erişimi
- `image_picker` - Medya seçimi
- `photo_manager` - Galeri yönetimi
- `video_player` - Video oynatma
- `cached_network_image` - Resim önbellekleme
- `permission_handler` - İzin yönetimi
- `firebase_auth` - Kimlik doğrulama
- `firebase_messaging` - Push bildirimleri

---

## 📁 Proje Yapısı

```
lib/
├── main.dart                 # Ana uygulama dosyası
├── models/                   # Veri modelleri
├── providers/                # State management
├── screens/                  # Ekranlar
│   ├── login_screen.dart
│   ├── instagram_home_screen.dart
│   ├── event_detail_screen.dart
│   └── ...
├── services/                 # Servisler
│   ├── api_service.dart
│   └── firebase_service.dart
├── utils/                    # Yardımcı sınıflar
│   ├── colors.dart
│   ├── theme_colors.dart
│   └── ...
└── widgets/                  # Widget'lar
    ├── camera_modal.dart
    ├── share_modal.dart
    └── ...
```

---

## 🎨 Temalar

Uygulama hem Light hem de Dark mode desteği sunar:

- **Light Mode**: Modern ve temiz görünüm
- **Dark Mode**: Göz yormayan karanlık tema

Tema renkleri `lib/utils/theme_colors.dart` dosyasında tanımlanmıştır.

---

## 📝 Lisans

Bu proje özel bir projedir ve tüm hakları saklıdır.

---

## 👨‍💻 Geliştirici

**Cag Apps**
- Email: app@cagapps.app
- Website: https://cagapps.app

---

## 📞 Destek

Sorularınız veya önerileriniz için:
- Issue açın: [GitHub Issues](https://github.com/yourusername/digimobil_new/issues)
- Email gönderin: app@cagapps.app

---

<div align="center">

**⭐ Bu projeyi beğendiyseniz yıldız vermeyi unutmayın! ⭐**

Made with ❤️ using Flutter

</div>

