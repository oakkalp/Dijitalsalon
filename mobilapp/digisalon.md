# 📱 Dijital Salon - Detaylı Dokümantasyon

> **Modern Etkinlik Medya Paylaşım Platformu**  
> Instagram tarzı tasarım ile düğün, nişan ve özel etkinlikler için kapsamlı medya yönetim sistemi

---

## 📑 İçindekiler

1. [Proje Hakkında](#proje-hakkında)
2. [Sistem Mimarisi](#sistem-mimarisi)
3. [Kurulum ve Başlangıç](#kurulum-ve-başlangıç)
4. [Ekranlar ve Özellikler](#ekranlar-ve-özellikler)
5. [Backend API Yapısı](#backend-api-yapısı)
6. [Veritabanı Yapısı](#veritabanı-yapısı)
7. [Kullanıcı Rehberi](#kullanıcı-rehberi)
8. [Geliştirici Notları](#geliştirici-notları)
9. [Gelecek Geliştirmeler](#gelecek-geliştirmeler)
10. [UI/UX İyileştirme Önerileri](#uiux-iyileştirme-önerileri)

---

## 🎯 Proje Hakkında

### Genel Bakış
Dijital Salon, etkinlik katılımcılarının fotoğraf ve video paylaşımını kolaylaştıran, Instagram benzeri modern bir mobil uygulamadır. Kullanıcılar QR kod ile etkinliklere katılabilir, medya paylaşabilir, hikaye ekleyebilir ve gerçek zamanlı bildirimleri alabilirler.

### Temel Özellikler
- ✅ **Instagram-Inspired UI**: Modern, kullanıcı dostu arayüz
- ✅ **QR Kod ile Katılım**: Hızlı ve kolay etkinlik katılımı
- ✅ **Medya Paylaşımı**: Fotoğraf ve video yükleme
- ✅ **Hikaye (Story) Sistemi**: 24 saat sonra silinen içerik
- ✅ **Gerçek Zamanlı Bildirimler**: Firebase Cloud Messaging
- ✅ **Rol Tabanlı Yetkilendirme**: Moderatör, Admin, Kullanıcı rolleri
- ✅ **Gelişmiş İzin Sistemi**: Granüler izin kontrolü
- ✅ **Profil Yönetimi**: Kullanıcı profilleri ve özelleştirme
- ✅ **Arama ve Keşfet**: Kullanıcı arama özelliği

### Teknoloji Stack

**Frontend (Flutter):**
- Flutter 3.x
- Dart
- Provider (State Management)
- Firebase (FCM, Auth)
- Dio (HTTP Client)
- CachedNetworkImage (Image Caching)
- VideoPlayer (Video Playback)

**Backend (PHP):**
- PHP 8.2+
- MySQL 8.0+
- PDO (Database)
- Firebase Admin SDK
- JWT Authentication via Service Account

**Altyapı:**
- XAMPP (Local Development)
- IIS/Apache (Production)
- Firebase Cloud Messaging
- AWS S3 / Local Storage

---

## 🏗️ Sistem Mimarisi

### Katmanlı Mimari

```
┌─────────────────────────────────────────────────┐
│           Flutter Mobile App (Client)           │
│  ┌──────────────────────────────────────────┐  │
│  │  Screens (UI Layer)                      │  │
│  │  ┌─────────┐ ┌─────────┐ ┌──────────┐   │  │
│  │  │ Home    │ │ Events  │ │ Profile  │   │  │
│  │  └─────────┘ └─────────┘ └──────────┘   │  │
│  └──────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────┐  │
│  │  Providers (State Management)            │  │
│  │  • AuthProvider                          │  │
│  │  • EventProvider                         │  │
│  └──────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────┐  │
│  │  Services (Business Logic)               │  │
│  │  • ApiService (REST)                     │  │
│  │  • FirebaseService (FCM)                 │  │
│  └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
                      ↕ HTTPS
┌─────────────────────────────────────────────────┐
│         Backend API (PHP + MySQL)               │
│  ┌──────────────────────────────────────────┐  │
│  │  API Endpoints (digimobiapi/)            │  │
│  │  • events.php, add_media.php, ...       │  │
│  └──────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────┐  │
│  │  Notification Service                    │  │
│  │  • notification_service.php              │  │
│  │  • sendNotification() helper             │  │
│  └──────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────┐  │
│  │  Database (MySQL)                        │  │
│  │  • kullanicilar, dugunler, medya, ...   │  │
│  └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
                      ↕
┌─────────────────────────────────────────────────┐
│          Firebase Cloud Messaging               │
│  • Push Notifications                           │
│  • Token Management                             │
└─────────────────────────────────────────────────┘
```

### Veri Akışı

1. **Kullanıcı Girişi**:
   ```
   LoginScreen → ApiService.login() → Backend (login.php)
   → Session + Cookie → AuthProvider (State Update) → InstagramMainScreen
   ```

2. **Medya Paylaşımı**:
   ```
   EventDetailScreen → File Picker → ApiService.addMedia()
   → Backend (add_media.php) → File Upload → Thumbnail Generation
   → Database Insert → Success Response → UI Update
   ```

3. **Bildirim Gönderme**:
   ```
   Event Card → Send Notification Button → Modal (Message Input)
   → ApiService.sendCustomNotification() → Backend (send_custom_notification.php)
   → FCM Token Fetch → Firebase Admin SDK → FCM API
   → User's Device → Push Notification
   ```

---

## 🚀 Kurulum ve Başlangıç

### Ön Gereksinimler

```bash
# Flutter SDK (3.x+)
flutter --version

# Dart SDK (3.x+)
dart --version

# Android Studio / VS Code
# Xcode (iOS için)

# Backend için:
# - PHP 8.2+
# - MySQL 8.0+
# - XAMPP veya Apache/IIS
```

### Flutter Projesi Kurulumu

```bash
# 1. Depoyu klonlayın
git clone <repository-url>
cd digimobil_new

# 2. Bağımlılıkları yükleyin
flutter pub get

# 3. Firebase yapılandırması
# android/app/google-services.json dosyasını ekleyin
# ios/Runner/GoogleService-Info.plist dosyasını ekleyin

# 4. Emulator/Device'da çalıştırın
flutter run

# 5. Release APK oluşturun
flutter build apk --release

# 6. iOS için
flutter build ios --release
```

### Backend Kurulumu

```bash
# 1. XAMPP kurulumu
# htdocs klasörüne backend dosyalarını kopyalayın

# 2. MySQL veritabanını oluşturun
mysql -u root -p
CREATE DATABASE dijitalsalon;
USE dijitalsalon;
SOURCE database_schema.sql;

# 3. config/database.php ayarları
# - DB_HOST, DB_USER, DB_PASS, DB_NAME

# 4. Firebase Service Account
# config/dijital-salon-xxxx.json dosyasını ekleyin

# 5. XAMPP'i başlatın
# Apache ve MySQL servislerini çalıştırın

# 6. Test edin
# https://dijitalsalon.cagapps.app/digimobiapi/events.php
```

### Firebase Yapılandırması

```bash
# 1. Firebase Console'da proje oluşturun
# 2. Android/iOS uygulaması ekleyin
# 3. google-services.json / GoogleService-Info.plist indirin
# 4. FCM API etkinleştirin
# 5. Service Account JSON indirin
```

---

## 📱 Ekranlar ve Özellikler

### 1. 🔐 Login Screen (`lib/screens/login_screen.dart`)

**Amaç**: Kullanıcı kimlik doğrulama  
**Dosya Adı**: `login_screen.dart`  
**Backend API**: `digimobiapi/login.php`

#### Özellikler:
- Email ve şifre ile giriş
- "Beni Hatırla" checkbox
- "Şifremi Unuttum" linki (placeholder)
- Google ile Giriş (placeholder)
- Apple ile Giriş (placeholder)
- Kayıt ol linki

#### UI Bileşenleri:
- **Email TextField**: Email girişi
- **Password TextField**: Şifre girişi (obscureText)
- **Login Button**: Giriş yapar
- **Google/Apple Buttons**: Sosyal giriş (gelecek özellik)

#### Veri Akışı:
```dart
1. Kullanıcı email/password girer
2. "Giriş Yap" butonuna basar
3. ApiService.login() çağrılır
4. Backend session key döner
5. SharedPreferences'a kaydedilir
6. AuthProvider.login() state günceller
7. FCM Token alınır ve backend'e kaydedilir
8. Navigator → InstagramMainScreen
```

#### Database Tabloları:
- `kullanicilar`: User credentials

---

### 2. 🏠 Instagram Home Screen (`lib/screens/instagram_home_screen.dart`)

**Amaç**: Ana sayfa - Etkinlikleri listeleme  
**Dosya Adı**: `instagram_home_screen.dart`  
**Backend API**: `digimobiapi/events.php`

#### Özellikler:
- Bugünkü etkinlikler
- Yaklaşan etkinlikler  
- Geçmiş etkinlikler
- Pull-to-refresh
- Event Card'larında:
  - Etkinlik görseli
  - Başlık, tarih, saat, konum
  - Katılımcı sayısı
  - Medya/Hikaye sayısı
  - Bildirim gönder butonu (moderatörler için)

#### UI Bileşenleri:
```
AppBar:
  - "Dijital Salon" başlık
  - Bildirim ikonu (sağ)

Body:
  - TabBar: Bugün | Yaklaşan | Geçmiş
  - Event Cards (ListView):
    - Kapak fotoğrafı
    - Başlık
    - Tarih/Saat/Konum
    - İstatistikler (katılımcı, medya, hikaye)
    - Bildirim butonu (yetkili kullanıcılar için)
```

#### Buton Fonksiyonları:
1. **Event Card Tap**: `EventDetailScreen`'e yönlendirir
2. **Bildirim Butonu**: 
   - Modal açar (mesaj girişi)
   - `_showSendNotificationModal(Event event)`
   - `ApiService.sendCustomNotification()` çağrılır
   - Başlık: "{Etkinlik Adı} Etkinliği"
   - Mesaj: "Durum Bildirimi\n\n{Kullanıcı Mesajı}"

#### Veri Akışı:
```dart
initState() 
  → _loadEvents() 
  → ApiService.getEvents() 
  → Backend: events.php
  → JSON Response
  → Event.fromJson() 
  → setState()
  → UI Güncellenir
```

#### Database Tabloları:
- `dugunler`: Events
- `dugun_katilimcilar`: Participants
- `medya`: Media items
- `paketler`: Packages

---

### 3. 🎉 Event Detail Screen (`lib/screens/event_detail_screen.dart`)

**Amaç**: Etkinlik detayları ve medya yönetimi  
**Dosya Adı**: `event_detail_screen.dart`  
**Backend API**: 
- `digimobiapi/event_media.php`
- `digimobiapi/add_media.php`
- `digimobiapi/add_story.php`
- `digimobiapi/delete_media.php`

#### Özellikler:
- **TabController**: 3 sekme
  - **Gönderiler**: Medya grid
  - **Hikayeler**: Story list
  - **Katılımcılar**: Participant list
- **Medya Yükleme**: Fotoğraf/Video
- **Hikaye Ekleme**: 24 saatlik içerik
- **Real-time Data Refresh**: Timer (30 saniye)
- **Ban Check**: Yasaklı kullanıcı kontrolü
- **Permission-based Actions**: Rol bazlı UI

#### UI Bileşenleri:

**AppBar**:
```dart
- Geri butonu
- Etkinlik başlığı
- QR kod butonu (moderatörler için)
- 3-nokta menü:
  - Etkinlik Profili
  - Ayarlar (moderatörler için)
```

**Stories Bar** (Üst kısım):
```dart
- Yatay ScrollView
- "+" butonu (hikaye ekle)
- Story circles (kullanıcı profil resimleri)
- Tap → StoryViewerModal açılır
```

**TabBar**:
```dart
Tab 1: Gönderiler
  - GridView (2 sütun)
  - InstagramPostCard widget'ları
  - Lazy loading (scroll pagination)
  - Tap → MediaViewerModal açılır
    - Büyük görünüm
    - Beğeni/Yorum
    - Silme butonu (yetkili kullanıcılar)

Tab 2: Hikayeler
  - ListView
  - Her hikaye için card:
    - Thumbnail
    - Kullanıcı adı
    - Tarih
    - Silme butonu (yetkili kullanıcılar)

Tab 3: Katılımcılar
  - ListView
  - Her katılımcı için card:
    - Profil resmi
    - Ad/Soyad
    - Rol (moderator/admin/user)
    - Medya sayısı
    - 3-nokta menü:
      - Yasakla/Yasağı Kaldır
      - Yetkileri Düzenle
      - Profili Görüntüle
```

**Floating Action Button**:
```dart
- Konum: Sağ alt
- İkon: Kamera
- Fonksiyon: Medya paylaşımı
- Tap → Modal açılır:
  - Galeriden Seç
  - Kamera ile Çek (Fotoğraf)
  - Video Çek
  → Medya/Hikaye seçimi
  → Açıklama girişi
  → Upload progress notification
```

#### Buton Fonksiyonları:

1. **+ (Hikaye Ekle)**:
   ```dart
   _showStoryOptions() 
     → BottomSheet: Galeri / Kamera / Video
     → ImagePicker / FilePicker
     → Açıklama Modal
     → ApiService.addStory()
     → Upload Notification (Progress)
     → Success → Data Refresh
   ```

2. **Story Circle Tap**:
   ```dart
   onTap(userId, stories) 
     → StoryViewerModal.show()
     → PageView (story navigation)
     → Video player / Image viewer
     → Swipe → Next/Previous story
     → 3-dot menu (delete için)
   ```

3. **Media Card Tap**:
   ```dart
   onTap(media) 
     → MediaViewerModal.show()
     → Full screen view
     → Pinch to zoom
     → Like/Comment section
     → Share button
     → Delete button (if owner/moderator)
   ```

4. **FAB (Camera Button)**:
   ```dart
   onPressed() 
     → _checkSharePermission()
     → _showMediaOptions()
     → BottomSheet: Galeri / Kamera / Video
     → _showContentTypeModal()
     → "Gönderi" / "Hikaye" seçimi
     → _showDescriptionModal()
     → _performMediaUpload() / _performStoryUpload()
     → FlutterLocalNotifications (progress)
     → ApiService.addMedia/addStory()
     → _refreshData()
   ```

5. **Participant Ban/Unban**:
   ```dart
   _showParticipantActionModal()
     → "Yasakla" / "Yasağı Kaldır"
     → ApiService.banParticipant()
     → Success → Participant list refresh
   ```

6. **Edit Permissions**:
   ```dart
   _showParticipantActionModal()
     → "Yetkileri Düzenle"
     → PermissionGrantModal.show()
     → Checkbox list:
       - Medya Paylaşabilir
       - Yorum Yapabilir
       - Hikaye Paylaşabilir
       - Medya Silebilir
       - Yorum Silebilir
       - Kullanıcı Engelleyebilir
       - Yetki Düzenleyebilir
       - Bildirim Gönderebilir
     → ApiService.grantPermissions()
     → Success → Modal close
   ```

#### Veri Akışı:

**Medya Yükleme**:
```
File Selection 
  → Content Type (Media/Story)
  → Description Modal
  → Notification (Preparing)
  → Multipart Upload (ApiService)
  → Backend Processing:
    - File save (uploads/events/{id}/)
    - Thumbnail generation (GD/ImageMagick)
    - Video preview (FFmpeg)
    - Database insert (medya table)
  → Success Response
  → Notification (Success/Error)
  → Data Refresh (Timer-based)
```

**Real-time Refresh**:
```
_dataRefreshTimer (30s interval)
  → _refreshData()
  → Fetch currentLoadedCount + 5 media
  → Compare with existing
  → If new media → Update UI
  → If user banned → Show modal + Navigate home
```

#### Database Tabloları:
- `medya`: Media items (photos/videos)
- `hikayeler`: Stories (24h auto-delete)
- `dugun_katilimcilar`: Participants
- `begeniler`: Likes
- `yorumlar`: Comments
- `yasakli_kullanicilar`: Banned users

---

### 4. 📝 Join Event Screen (`lib/screens/join_event_screen.dart`)

**Amaç**: QR kod ile etkinliğe katılım  
**Dosya Adı**: `join_event_screen.dart`  
**Backend API**: `digimobiapi/join_event.php`

#### Özellikler:
- QR kod tarayıcı
- Kamera izni kontrolü
- Otomatik event join
- Success/Error modals

#### UI Bileşenleri:
```dart
QRView Widget:
  - Kamera preview
  - Scan overlay
  - Flash toggle
  - QR detection

Bottom Instructions:
  - "QR kodu kameranın önüne tutun"
```

#### Buton Fonksiyonları:
1. **QR Kod Tarama**:
   ```dart
   onDetect(QRCode) 
     → Parse QR data
     → ApiService.joinEvent(qr_code)
     → Backend: join_event.php
     → Success → SuccessModal
     → Navigate → EventDetailScreen
     → Error → ErrorModal
   ```

#### Veri Akışı:
```
QR Scan 
  → QR Code String (QR_xxxx)
  → ApiService.joinEvent()
  → Backend checks:
    - Valid QR?
    - Already joined?
    - Event exists?
  → Insert dugun_katilimcilar
  → Return event details
  → EventProvider.lastJoinedEvent = event
  → Navigate to EventDetailScreen
```

#### Database Tabloları:
- `dugunler`: Event lookup by QR
- `dugun_katilimcilar`: New participant insert

---

### 5. 👤 User Profile Screen (`lib/screens/user_profile_screen.dart`)

**Amaç**: Kendi profilini görüntüleme ve düzenleme  
**Dosya Adı**: `user_profile_screen.dart`  
**Backend API**: 
- `digimobiapi/get_user_profile.php`
- `digimobiapi/update_profile.php`

#### Özellikler:
- Profil resmi görüntüleme
- Ad/Soyad/Email bilgileri
- İstatistikler:
  - Etkinlik sayısı
  - Paylaşım sayısı
  - Hikaye sayısı
- Profil düzenleme
- Çıkış yap butonu

#### UI Bileşenleri:
```dart
Header:
  - Profil resmi (CircleAvatar)
  - Ad/Soyad (Text)
  - Email (Text)

Stats Row:
  - Etkinlikler: {count}
  - Paylaşımlar: {count}
  - Hikayeler: {count}

Actions:
  - Profili Düzenle butonu
  - Çıkış Yap butonu
```

#### Buton Fonksiyonları:
1. **Profili Düzenle**:
   ```dart
   onPressed() 
     → _showEditProfileModal()
     → TextField (ad, soyad, email)
     → Image picker (profil resmi)
     → ApiService.updateProfile()
     → Success → State update
   ```

2. **Çıkış Yap**:
   ```dart
   onPressed()
     → AuthProvider.logout()
     → SharedPreferences.clear()
     → Navigator → LoginScreen
   ```

#### Database Tabloları:
- `kullanicilar`: User profile data

---

### 6. 🔍 User Search Screen (`lib/screens/user_search_screen.dart`)

**Amaç**: Kullanıcı arama  
**Dosya Adı**: `user_search_screen.dart`  
**Backend API**: `digimobiapi/search_users.php`

#### Özellikler:
- Gerçek zamanlı arama
- Debounce (500ms)
- Kullanıcı listesi
- Profil görüntüleme

#### UI Bileşenleri:
```dart
SearchBar:
  - TextField (arama)
  - Debounced search

UserList:
  - ListView
  - UserCard:
    - Profil resmi
    - Ad/Soyad
    - Email
    - Tap → ProfileScreen (user_id)
```

#### Buton Fonksiyonları:
1. **User Card Tap**:
   ```dart
   onTap(userId) 
     → Navigator.push(ProfileScreen(userId: userId))
   ```

#### Veri Akışı:
```
TextField.onChanged 
  → Debounce (500ms)
  → ApiService.searchUsers(query)
  → Backend: search_users.php
    - LIKE query on ad, soyad, email
  → Return user list
  → setState()
  → UI update
```

#### Database Tabloları:
- `kullanicilar`: User search

---

### 7. 👥 Profile Screen (Other User) (`lib/screens/profile_screen.dart`)

**Amaç**: Başka kullanıcının profilini görüntüleme  
**Dosya Adı**: `profile_screen.dart`  
**Backend API**: 
- `digimobiapi/get_user_profile.php`
- `digimobiapi/get_user_media.php`
- `digimobiapi/stories.php`

#### Özellikler:
- Kullanıcı bilgileri
- Paylaştığı medya
- Paylaştığı hikayeler (event bazlı)
- Event highlight'ları

#### UI Bileşenleri:
```dart
Header:
  - Profil resmi
  - Ad/Soyad
  - Etkinlik sayısı

Highlights (Story Circles):
  - Event covers
  - Tap → Event stories viewer

Media Grid:
  - 3 sütun grid
  - Tüm paylaşımlar
  - Tap → MediaViewerModal
```

#### Database Tabloları:
- `kullanicilar`: User info
- `medya`: User's media
- `hikayeler`: User's stories
- `dugunler`: Events user participated in

---

## 🔌 Backend API Yapısı

### API Endpoint'leri

#### 1. **Authentication**

**`login.php`**
```php
Method: POST
Body: {
  "email": "user@example.com",
  "password": "password123"
}
Response: {
  "success": true,
  "user": {
    "id": 1,
    "ad": "John",
    "soyad": "Doe",
    "email": "user@example.com",
    "profil_resmi": "https://..."
  },
  "session_key": "abc123..."
}
Tables: kullanicilar
```

**`register.php`**
```php
Method: POST
Body: {
  "ad": "John",
  "soyad": "Doe",
  "email": "user@example.com",
  "sifre": "password123"
}
Response: {
  "success": true,
  "message": "Kayıt başarılı"
}
Tables: kullanicilar
```

#### 2. **Events**

**`events.php`**
```php
Method: GET
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "events": [
    {
      "id": 1,
      "baslik": "Düğün",
      "tarih": "2025-12-01",
      "saat": "19:00:00",
      "konum": "İstanbul",
      "kapak_fotografi": "https://...",
      "katilimci_sayisi": 50,
      "medya_sayisi": 120,
      "hikaye_sayisi": 30,
      "user_permissions": {...}
    }
  ]
}
Tables: dugunler, dugun_katilimcilar, medya, paketler
Query:
  - JOIN dugun_katilimcilar (user's events)
  - COUNT medya (media count)
  - COUNT hikayeler (story count)
  - Fetch permissions JSON
```

**`event_media.php`**
```php
Method: GET
Params: 
  - event_id (required)
  - page (default: 1)
  - per_page (default: 10)
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "media": [
    {
      "id": 1,
      "dugun_id": 1,
      "kullanici_id": 2,
      "dosya_yolu": "https://...",
      "kucuk_resim_yolu": "https://...",
      "onizleme_yolu": "https://...",
      "tur": "foto",
      "aciklama": "Beautiful moment",
      "begeni_sayisi": 10,
      "yorum_sayisi": 5,
      "olusturma_tarihi": "2025-11-01 12:00:00",
      "kullanici_ad": "John",
      "kullanici_soyad": "Doe",
      "kullanici_profil_resmi": "https://..."
    }
  ],
  "total": 120,
  "page": 1,
  "per_page": 10,
  "has_more": true
}
Tables: medya, kullanicilar, begeniler, yorumlar
Query:
  - WHERE dugun_id = {event_id} AND tur != 'hikaye'
  - ORDER BY olusturma_tarihi DESC
  - LIMIT page, per_page
  - COUNT begeniler, yorumlar
```

**`join_event.php`**
```php
Method: POST
Body: {
  "qr_code": "QR_abc123..."
}
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "event": {...},
  "message": "Etkinliğe başarıyla katıldınız"
}
Tables: dugunler, dugun_katilimcilar
Logic:
  1. Validate QR code
  2. Check if user already joined
  3. Insert dugun_katilimcilar (rol: 'kullanici')
  4. Return event details
```

#### 3. **Media**

**`add_media.php`**
```php
Method: POST (multipart/form-data)
Body:
  - event_id: 1
  - description: "Description"
  - media_file: (binary)
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "media": {
    "id": 123,
    "dosya_yolu": "https://...",
    "kucuk_resim_yolu": "https://...",
    ...
  }
}
Tables: medya
Logic:
  1. Validate user permissions (medya_paylasabilir)
  2. Check file type (image/video)
  3. Check media limit (package-based)
  4. Upload file → uploads/events/{event_id}/
  5. Generate thumbnail (GD/ImageMagick)
  6. Generate preview for video (FFmpeg)
  7. Insert database record
  8. Return media info
```

**`delete_media.php`**
```php
Method: POST
Body: {
  "media_id": 123
}
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "message": "Medya silindi"
}
Tables: medya, begeniler, yorumlar
Logic:
  1. Validate ownership or permissions
  2. Delete physical files:
     - Original file
     - Thumbnail
     - Preview (if video)
  3. Delete database record (CASCADE begeniler, yorumlar)
```

#### 4. **Stories**

**`add_story.php`**
```php
Method: POST (multipart/form-data)
Body:
  - event_id: 1
  - description: "Story text"
  - media_file: (binary)
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "story": {...}
}
Tables: medya (tur='hikaye')
Logic: Same as add_media.php but tur='hikaye'
```

**`stories.php`**
```php
Method: GET
Params:
  - event_id: 1
  - user_id: (optional)
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "stories": [
    {
      "id": 1,
      "user_id": 2,
      "user_name": "John Doe",
      "user_profile_image": "https://...",
      "stories": [
        {
          "id": 10,
          "dosya_yolu": "https://...",
          "kucuk_resim_yolu": "https://...",
          "aciklama": "Story text",
          "olusturma_tarihi": "2025-11-01 12:00:00"
        }
      ]
    }
  ]
}
Tables: medya, kullanicilar
Query:
  - WHERE dugun_id = {event_id} AND tur = 'hikaye'
  - GROUP BY kullanici_id
  - ORDER BY olusturma_tarihi DESC
```

**`delete_story.php`**
```php
Method: POST
Body: {
  "story_id": 123
}
Tables: medya
Logic: Same as delete_media.php
```

#### 5. **Participants**

**`event_participants.php`**
```php
Method: GET
Params: event_id=1
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "participants": [
    {
      "id": 1,
      "kullanici_id": 2,
      "ad": "John",
      "soyad": "Doe",
      "email": "user@example.com",
      "profil_resmi": "https://...",
      "rol": "kullanici",
      "media_count": 10,
      "story_count": 5,
      "banned": false
    }
  ]
}
Tables: dugun_katilimcilar, kullanicilar, medya, yasakli_kullanicilar
Query:
  - JOIN dugun_katilimcilar + kullanicilar
  - COUNT medya per user
  - CHECK yasakli_kullanicilar
```

**`ban_participant.php`**
```php
Method: POST
Body: {
  "event_id": 1,
  "user_id": 2,
  "action": "ban" // or "unban"
}
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "message": "Kullanıcı yasaklandı"
}
Tables: yasakli_kullanicilar
Logic:
  1. Check permissions (kullanici_engelleyebilir)
  2. action == "ban" → INSERT yasakli_kullanicilar
  3. action == "unban" → DELETE yasakli_kullanicilar
```

**`grant_permissions.php`**
```php
Method: POST
Body: {
  "event_id": 1,
  "user_id": 2,
  "permissions": {
    "medya_paylasabilir": true,
    "yorum_yapabilir": true,
    "hikaye_paylasabilir": true,
    "medya_silebilir": false,
    ...
  }
}
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "message": "Yetkiler güncellendi"
}
Tables: dugun_katilimcilar
Logic:
  1. Check permissions (yetki_duzenleyebilir)
  2. UPDATE dugun_katilimcilar SET yetkiler = JSON
```

#### 6. **Notifications**

**`send_custom_notification.php`**
```php
Method: POST
Body: {
  "event_id": 1,
  "title": "Etkinlik Adı Etkinliği",
  "message": "Bildirim mesajı"
}
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "recipient_count": 50,
  "fcm_success_count": 48,
  "fcm_failed_count": 2
}
Tables: dugun_katilimcilar, notifications, fcm_tokens
Logic:
  1. Check permissions (bildirim_gonderebilir)
  2. Fetch all participants (except sender)
  3. Insert notifications table
  4. Fetch FCM tokens
  5. Call sendNotification() helper
  6. Return success/failure counts
```

**`save_fcm_token.php`**
```php
Method: POST
Body: {
  "fcm_token": "fXXXXXXX..."
}
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true
}
Tables: fcm_tokens
Logic:
  1. Check if token exists for user
  2. UPDATE or INSERT fcm_tokens
  3. Set updated_at = NOW()
```

**`get_notifications.php`**
```php
Method: GET
Params: is_read=0 (optional)
Headers: Cookie: PHPSESSID={session_key}
Response: {
  "success": true,
  "notifications": [
    {
      "id": 1,
      "title": "Yeni Bildirim",
      "message": "Mesaj içeriği",
      "type": "custom",
      "is_read": false,
      "created_at": "2025-11-01 12:00:00"
    }
  ]
}
Tables: notifications
```

#### 7. **Helper Services**

**`notification_service.php`**
```php
Function: sendNotification($user_ids, $title, $message, $data)
Purpose: Send FCM push notifications
Logic:
  1. Loop through user_ids
  2. Fetch FCM token from fcm_tokens table
  3. Get Firebase access token (Service Account JWT)
  4. Call FCM API:
     - URL: https://fcm.googleapis.com/v1/projects/dijital-salon/messages:send
     - Headers: Authorization: Bearer {access_token}
     - Body: {
         "message": {
           "token": "{fcm_token}",
           "notification": {
             "title": "{title}",
             "body": "{message}"
           },
           "data": {...}
         }
       }
  5. Log success/failure
  6. Save to notifications table
  7. Return { success_count, failures[] }

Class: NotificationService
Methods:
  - sendFCMNotification($userId, $title, $message, $data)
  - getAccessToken() → JWT generation
  - base64UrlEncode($data)
  - saveNotificationToDb(...)
```

---

## 🗄️ Veritabanı Yapısı

### Tablo Şeması

#### 1. **kullanicilar** (Users)
```sql
CREATE TABLE kullanicilar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ad VARCHAR(100) NOT NULL,
  soyad VARCHAR(100) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  sifre VARCHAR(255) NOT NULL, -- bcrypt hash
  profil_resmi VARCHAR(500),
  telefon VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 2. **dugunler** (Events)
```sql
CREATE TABLE dugunler (
  id INT PRIMARY KEY AUTO_INCREMENT,
  baslik VARCHAR(255) NOT NULL,
  aciklama TEXT,
  dugun_tarihi DATE NOT NULL,
  saat TIME,
  konum TEXT,
  salon_adresi TEXT,
  moderator_id INT, -- olusturan_id
  kapak_fotografi VARCHAR(500),
  kapak_fotografi_thumbnail VARCHAR(500),
  kapak_fotografi_preview VARCHAR(500),
  qr_kod VARCHAR(100) UNIQUE,
  paket_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (moderator_id) REFERENCES kullanicilar(id),
  FOREIGN KEY (paket_id) REFERENCES paketler(id)
);
```

#### 3. **dugun_katilimcilar** (Event Participants)
```sql
CREATE TABLE dugun_katilimcilar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  dugun_id INT NOT NULL,
  kullanici_id INT NOT NULL,
  rol ENUM('moderator', 'admin', 'yetkili_kullanici', 'kullanici') DEFAULT 'kullanici',
  yetkiler JSON, -- Permissions object
  katilim_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dugun_id) REFERENCES dugunler(id) ON DELETE CASCADE,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  UNIQUE KEY (dugun_id, kullanici_id)
);

-- yetkiler JSON format:
{
  "medya_paylasabilir": true,
  "yorum_yapabilir": true,
  "hikaye_paylasabilir": true,
  "medya_silebilir": false,
  "yorum_silebilir": false,
  "kullanici_engelleyebilir": false,
  "yetki_duzenleyebilir": false,
  "baska_kullanici_yetki_degistirebilir": false,
  "baska_kullanici_yasaklayabilir": false,
  "baska_kullanici_silebilir": false,
  "bildirim_gonderebilir": false
}
```

#### 4. **medya** (Media & Stories)
```sql
CREATE TABLE medya (
  id INT PRIMARY KEY AUTO_INCREMENT,
  dugun_id INT NOT NULL,
  kullanici_id INT NOT NULL,
  dosya_yolu VARCHAR(500) NOT NULL,
  kucuk_resim_yolu VARCHAR(500),
  onizleme_yolu VARCHAR(500), -- Video preview
  tur ENUM('foto', 'video', 'hikaye') NOT NULL,
  aciklama TEXT,
  olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dugun_id) REFERENCES dugunler(id) ON DELETE CASCADE,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
);

-- Index for performance
CREATE INDEX idx_dugun_medya ON medya(dugun_id, tur, olusturma_tarihi);
CREATE INDEX idx_kullanici_medya ON medya(kullanici_id, olusturma_tarihi);
```

#### 5. **begeniler** (Likes)
```sql
CREATE TABLE begeniler (
  id INT PRIMARY KEY AUTO_INCREMENT,
  medya_id INT NOT NULL,
  kullanici_id INT NOT NULL,
  olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medya_id) REFERENCES medya(id) ON DELETE CASCADE,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  UNIQUE KEY (medya_id, kullanici_id)
);
```

#### 6. **yorumlar** (Comments)
```sql
CREATE TABLE yorumlar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  medya_id INT NOT NULL,
  kullanici_id INT NOT NULL,
  yorum TEXT NOT NULL,
  olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (medya_id) REFERENCES medya(id) ON DELETE CASCADE,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
);
```

#### 7. **yasakli_kullanicilar** (Banned Users)
```sql
CREATE TABLE yasakli_kullanicilar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  dugun_id INT NOT NULL,
  kullanici_id INT NOT NULL,
  yasaklayan_kullanici_id INT NOT NULL,
  yasak_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sebep TEXT,
  FOREIGN KEY (dugun_id) REFERENCES dugunler(id) ON DELETE CASCADE,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (yasaklayan_kullanici_id) REFERENCES kullanicilar(id),
  UNIQUE KEY (dugun_id, kullanici_id)
);
```

#### 8. **paketler** (Packages)
```sql
CREATE TABLE paketler (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ad VARCHAR(100) NOT NULL,
  aciklama TEXT,
  max_katilimci INT,
  max_medya INT,
  medya_limiti INT, -- Per user media limit
  fiyat DECIMAL(10,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default packages:
INSERT INTO paketler (ad, max_katilimci, max_medya, medya_limiti) VALUES
('Temel Paket', 50, 500, 10),
('Standart Paket', 100, 1000, 20),
('Premium Paket', 200, 2000, 50),
('Sınırsız Paket', 999999, 999999, 999999);
```

#### 9. **fcm_tokens** (Firebase Cloud Messaging Tokens)
```sql
CREATE TABLE fcm_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  token VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  UNIQUE KEY (user_id, token)
);
```

#### 10. **notifications** (Notification History)
```sql
CREATE TABLE notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  sender_id INT,
  event_id INT,
  type VARCHAR(50) NOT NULL DEFAULT 'custom',
  title VARCHAR(255),
  message TEXT NOT NULL,
  data JSON,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES kullanicilar(id) ON DELETE SET NULL,
  FOREIGN KEY (event_id) REFERENCES dugunler(id) ON DELETE CASCADE
);
```

#### 11. **user_logs** (Activity Logs)
```sql
CREATE TABLE user_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  details JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
);
```

---

## 📖 Kullanıcı Rehberi

### Başlangıç

#### 1. Hesap Oluşturma ve Giriş

**Kayıt Olma:**
1. Uygulamayı açın
2. "Kayıt Ol" linkine tıklayın
3. Ad, Soyad, Email, Şifre bilgilerinizi girin
4. "Kayıt Ol" butonuna basın
5. Email doğrulama (opsiyonel)
6. Otomatik giriş yapılır

**Giriş Yapma:**
1. Email ve şifrenizi girin
2. "Beni Hatırla" seçeneğini işaretleyin (opsiyonel)
3. "Giriş Yap" butonuna basın
4. Ana sayfaya yönlendirilirsiniz

#### 2. Etkinliğe Katılma

**QR Kod ile Katılım:**
1. Alt menüden "+" (QR) ikonuna tıklayın
2. Kamera izni verin
3. QR kodu kameranın önüne tutun
4. Otomatik olarak etkinliğe katılırsınız
5. Etkinlik detay sayfası açılır

**Manuel Katılım:**
(Gelecek özellik - QR kod metni elle girme)

#### 3. Medya Paylaşımı

**Fotoğraf/Video Yükleme:**
1. Etkinlik detay sayfasında "Kamera" FAB'ına tıklayın
2. Seçeneklerden birini seçin:
   - **Galeriden Seç**: Var olan medya
   - **Kamera ile Çek**: Yeni fotoğraf
   - **Video Çek**: Yeni video
3. Medya tipini seçin:
   - **Gönderi**: Ana feed'de görünür
   - **Hikaye**: 24 saat sonra silinir
4. Açıklama girin (opsiyonel)
5. "Paylaş" butonuna basın
6. Upload bildirimi görünür
7. Başarılı olduğunda medya feed'de görünür

**Upload Süreci:**
- Progress notification gösterilir
- Thumbnail otomatik oluşturulur
- Video için preview kaydedilir
- 5-30 saniye sürebilir (dosya boyutuna göre)

#### 4. Hikaye Paylaşımı

**Hikaye Ekleme:**
1. Hikaye bar'ında "+" ikonuna tıklayın
2. Medya seçin (galeri/kamera)
3. Açıklama ekleyin
4. "Hikaye Olarak Paylaş"a tıklayın
5. 24 saat sonra otomatik silinir

**Hikaye Görüntüleme:**
1. Hikaye bar'ında bir kullanıcının resmine tıklayın
2. Story viewer açılır:
   - Sağa/sola kaydır: Sonraki/önceki hikaye
   - Yukarı kaydır: Kapat
   - Ekrana bas: Duraklat/devam et
   - 3-nokta: Sil (kendinse)

#### 5. Medya İşlemleri

**Medya Görüntüleme:**
1. Gönderiler sekmesinde bir medyaya tıklayın
2. Tam ekran görüntü açılır:
   - Pinch to zoom (fotoğraflar için)
   - Play/pause (videolar için)
   - Beğen butonu (kalp ikonu)
   - Yorum yap (mesaj balonu ikonu)
   - Paylaş butonu
   - Sil butonu (kendinse veya yetkiniz varsa)

**Beğeni ve Yorum:**
1. Medya detayında kalp ikonuna tıklayın (beğen)
2. Yorum ikonuna tıklayın
3. Yorumunuzu yazın ve gönderin
4. Yorumlar listesi görünür

**Medya Silme:**
1. Medya detayında 3-nokta menüsüne tıklayın
2. "Sil" seçeneğine basın
3. Onay dialog'u
4. Silme işlemi başarılı

#### 6. Katılımcı Yönetimi (Moderatörler İçin)

**Katılımcıları Görüntüleme:**
1. Etkinlik detayında "Katılımcılar" sekmesine tıklayın
2. Tüm katılımcılar listelenir:
   - Profil resmi
   - Ad/Soyad
   - Rol (Moderator/Admin/User)
   - Medya sayısı

**Katılımcı İşlemleri:**
1. Katılımcının 3-nokta menüsüne tıklayın
2. Seçenekler:
   - **Yasakla/Yasağı Kaldır**: Kullanıcıyı engelle
   - **Yetkileri Düzenle**: İzinleri değiştir
   - **Profili Görüntüle**: Kullanıcı profiline git

**Yetki Düzenleme:**
1. "Yetkileri Düzenle" seçeneğine tıklayın
2. İzinleri işaretle/kaldır:
   - ✅ Medya Paylaşabilir
   - ✅ Yorum Yapabilir
   - ✅ Hikaye Paylaşabilir
   - ❌ Medya Silebilir
   - ❌ Yorum Silebilir
   - ❌ Kullanıcı Engelleyebilir
   - ❌ Yetki Düzenleyebilir
   - ❌ Bildirim Gönderebilir
3. "Kaydet" butonuna basın

#### 7. Bildirim Gönderme (Yetkili Kullanıcılar İçin)

**Tüm Katılımcılara Bildirim:**
1. Ana sayfada etkinlik kartındaki "Bildirim" ikonuna tıklayın
2. Mesaj yazın (max 200 karakter)
3. "Gönder" butonuna basın
4. Başarı mesajı görünür
5. Tüm katılımcılar push notification alır

**Bildirim Formatı:**
```
Başlık: "Etkinlik Adı Etkinliği"
Mesaj: "Durum Bildirimi

[Sizin Mesajınız]"
```

#### 8. Profil Yönetimi

**Profilinizi Görüntüleme:**
1. Alt menüden profil ikonuna (sağ) tıklayın
2. Profil bilgileri görünür:
   - Profil resmi
   - Ad/Soyad
   - Email
   - İstatistikler (Etkinlik, Paylaşım, Hikaye sayısı)

**Profil Düzenleme:**
1. "Profili Düzenle" butonuna tıklayın
2. Bilgileri güncelleyin:
   - Profil resmi değiştir
   - Ad/Soyad güncelle
   - Email güncelle (doğrulama gerekir)
3. "Kaydet" butonuna basın

**Çıkış Yapma:**
1. Profil sayfasında "Çıkış Yap" butonuna tıklayın
2. Onay dialog'u
3. Login sayfasına yönlendirilirsiniz

#### 9. Kullanıcı Arama

**Kullanıcı Bulma:**
1. Alt menüden "Arama" (🔍) ikonuna tıklayın
2. Arama çubuğuna ad/soyad/email yazın
3. Sonuçlar gerçek zamanlı gösterilir
4. Bir kullanıcıya tıklayın
5. Kullanıcı profili açılır

---

## 💻 Geliştirici Notları

### Kod Yapısı

#### Flutter Projesi Dizin Yapısı
```
lib/
├── main.dart                 # Entry point, routing
├── models/                   # Data models
│   ├── event.dart           # Event model
│   ├── media.dart           # Media model (placeholder)
│   └── user.dart            # User model
├── providers/               # State management (Provider)
│   ├── auth_provider.dart   # Authentication state
│   └── event_provider.dart  # Event state
├── screens/                 # UI Screens
│   ├── login_screen.dart
│   ├── register_screen.dart
│   ├── instagram_home_screen.dart
│   ├── event_detail_screen.dart
│   ├── join_event_screen.dart
│   ├── profile_screen.dart
│   ├── user_profile_screen.dart
│   ├── user_search_screen.dart
│   └── event_profile_screen.dart
├── services/                # Business logic
│   ├── api_service.dart     # REST API client
│   └── firebase_service.dart # FCM service
├── utils/                   # Utilities
│   ├── colors.dart          # App colors
│   └── constants.dart       # App constants
└── widgets/                 # Reusable widgets
    ├── instagram_stories_bar.dart
    ├── instagram_post_card.dart
    ├── story_viewer_modal.dart
    ├── story_video_player.dart
    ├── media_viewer_modal.dart
    ├── permission_grant_modal.dart
    ├── success_modal.dart
    └── error_modal.dart
```

#### Backend Dizin Yapısı
```
dijitalsalon.cagapps.app/
├── admin/                   # Admin panel (PHP)
│   ├── test_notification.php
│   ├── check_duplicate_fcm_tokens.php
│   ├── fix_notifications_type_column.php
│   └── ...
├── config/                  # Configuration
│   ├── database.php         # DB connection
│   └── dijital-salon-xxxx.json # Firebase service account
├── digimobiapi/            # Mobile API endpoints
│   ├── bootstrap.php        # Common includes
│   ├── login.php
│   ├── register.php
│   ├── events.php
│   ├── event_media.php
│   ├── add_media.php
│   ├── add_story.php
│   ├── delete_media.php
│   ├── delete_story.php
│   ├── join_event.php
│   ├── event_participants.php
│   ├── ban_participant.php
│   ├── grant_permissions.php
│   ├── send_custom_notification.php
│   ├── save_fcm_token.php
│   ├── get_notifications.php
│   ├── mark_notification_read.php
│   ├── notification_service.php # FCM helper
│   ├── stories.php
│   ├── search_users.php
│   └── get_user_profile.php
└── uploads/                # User uploaded files
    └── events/
        └── {event_id}/
            ├── original files
            ├── *_thumb.jpg (thumbnails)
            └── *_preview.mp4 (video previews)
```

### State Management

**Provider Pattern:**
```dart
// AuthProvider: User authentication state
- user: User? (current user)
- isLoggedIn: bool
- login()
- logout()
- checkAuthStatus()

// EventProvider: Event data state
- events: List<Event>
- lastJoinedEvent: Event?
- loadEvents()
- joinEvent()
```

### API İletişimi

**Session Management:**
```dart
// Login response
Set-Cookie: PHPSESSID={session_key}

// Subsequent requests
Cookie: PHPSESSID={session_key}

// Flutter side (SharedPreferences)
await prefs.setString('session_key', sessionKey);
final sessionKey = prefs.getString('session_key');
```

**Error Handling:**
```dart
try {
  final response = await apiService.someMethod();
  // Success
} catch (e) {
  if (e.toString().contains('403')) {
    // Unauthorized
    ErrorModal.show(context, 'Yetkiniz yok');
  } else if (e.toString().contains('500')) {
    // Server error
    ErrorModal.show(context, 'Sunucu hatası');
  } else {
    // Generic error
    ErrorModal.show(context, e.toString());
  }
}
```

### Firebase Integration

**FCM Token Flow:**
```
App Start
  → FirebaseService.initialize()
  → FirebaseMessaging.requestPermission()
  → FirebaseMessaging.getToken()
  → ApiService.saveFCMToken()
  → Backend: fcm_tokens table INSERT/UPDATE
```

**Notification Sending:**
```
Backend: send_custom_notification.php
  → Fetch fcm_tokens for user_ids
  → NotificationService.sendFCMNotification()
    → Get Firebase access token (JWT)
    → Call FCM API (POST /messages:send)
    → Return success/failure
  → Save to notifications table
```

### File Upload

**Multipart Upload:**
```dart
// Flutter side
var request = http.MultipartRequest('POST', uri);
request.fields['event_id'] = eventId.toString();
request.fields['description'] = description;
request.files.add(await http.MultipartFile.fromPath('media_file', filePath));
final response = await request.send();
```

**Backend Processing:**
```php
// PHP side
$file = $_FILES['media_file'];
$event_id = $_POST['event_id'];
$description = $_POST['description'];

// Validate
if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new Exception('Upload error');
}

// Save
$target_dir = "uploads/events/$event_id/";
$target_file = $target_dir . uniqid() . '_' . basename($file['name']);
move_uploaded_file($file['tmp_name'], $target_file);

// Generate thumbnail
$thumbnail = generateThumbnail($target_file);

// Insert DB
$stmt->execute([$dugun_id, $kullanici_id, $target_file, $thumbnail, ...]);
```

### Permission System

**Permission Check Flow:**
```php
// Backend
1. Fetch dugun_katilimcilar.yetkiler (JSON)
2. Parse JSON → array
3. Check specific permission:
   if ($permissions['medya_paylasabilir'] === true) {
       // Allow upload
   } else {
       json_err('Permission denied', 403);
   }

// Alternative: Role-based
if ($rol === 'moderator' || $rol === 'admin') {
    // Full access
} else {
    // Check granular permissions
}
```

### Real-time Updates

**Polling Strategy:**
```dart
// EventDetailScreen
Timer.periodic(Duration(seconds: 30), (timer) {
  _refreshData(); // Fetch new media/stories
});

// Alternative: WebSocket (future)
// socket.on('new_media', (data) => _addMediaToList(data));
```

### Image Optimization

**Thumbnail Generation:**
```php
function generateThumbnail($source, $max_width = 300) {
    $image = imagecreatefromstring(file_get_contents($source));
    $width = imagesx($image);
    $height = imagesy($image);
    
    $ratio = $max_width / $width;
    $new_width = $max_width;
    $new_height = $height * $ratio;
    
    $thumb = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, 
                       $new_width, $new_height, $width, $height);
    
    $target = str_replace('.jpg', '_thumb.jpg', $source);
    imagejpeg($thumb, $target, 80);
    imagedestroy($thumb);
    imagedestroy($image);
    
    return $target;
}
```

**Video Preview:**
```bash
# FFmpeg command (PHP exec)
ffmpeg -i input.mp4 -ss 00:00:01 -vframes 1 preview.jpg
ffmpeg -i input.mp4 -t 5 -vf scale=640:-1 preview.mp4
```

### Testing

**Manual Testing Checklist:**
- [ ] Login/Register
- [ ] QR Code Scan & Join Event
- [ ] View Event Details
- [ ] Upload Photo
- [ ] Upload Video
- [ ] Add Story
- [ ] View Story
- [ ] Like Media
- [ ] Comment on Media
- [ ] Delete Own Media
- [ ] Delete Others' Media (moderator)
- [ ] Ban User (moderator)
- [ ] Grant Permissions (moderator)
- [ ] Send Notification (moderator)
- [ ] Receive Push Notification
- [ ] Search Users
- [ ] View User Profile
- [ ] Edit Own Profile
- [ ] Logout

**Debug Logging:**
```dart
// Flutter
if (kDebugMode) {
  debugPrint('🔍 DEBUG: $message');
}

// PHP
error_log("DEBUG: $message");
```

---

## 🚀 Gelecek Geliştirmeler

### Eksik Özellikler

#### 1. **Şifremi Unuttum**
- Email ile şifre sıfırlama linki
- Token bazlı doğrulama
- Yeni şifre belirleme

#### 2. **Email Doğrulama**
- Kayıt sonrası email gönderimi
- Doğrulama linki
- Hesap aktifleştirme

#### 3. **Sosyal Giriş**
- ✅ Google Sign-In (UI hazır, backend eksik)
- ✅ Apple Sign-In (UI hazır, backend eksik)
- Facebook Login (opsiyonel)

#### 4. **WebSocket ile Real-time**
- Anlık medya güncellemeleri
- Anlık bildirimler
- Typing indicator (yorumlar için)

#### 5. **Gelişmiş Arama**
- Medya arama (etiket, açıklama)
- Etkinlik arama
- Tarih bazlı filtreleme

#### 6. **Medya İndirme**
- Toplu indirme (ZIP)
- Galeri entegrasyonu
- İzin kontrolü

#### 7. **Video Düzenleme**
- Trim/Crop
- Filter uygulama
- Text overlay

#### 8. **Hikaye Özellikleri**
- Text/Sticker ekle
- Drawing tool
- Filter ve efektler
- Music ekleme

#### 9. **Chat Sistemi**
- Katılımcılar arası mesajlaşma
- Group chat (etkinlik bazlı)
- Medya paylaşımı (chat içinde)

#### 10. **Event Templates**
- Hazır etkinlik şablonları
- Tema/Renk özelleştirme
- Logo/Branding

#### 11. **Analytics Dashboard**
- Etkinlik istatistikleri
- En çok beğenilen medyalar
- Aktif kullanıcılar
- Engagement metrics

#### 12. **Ödeme Sistemi**
- Paket satın alma
- In-app purchase
- Stripe/PayPal integration

#### 13. **Admin Panel Web**
- Etkinlik yönetimi (web)
- Kullanıcı yönetimi
- Raporlama
- Moderasyon tools

#### 14. **Offline Mode**
- SQLite local cache
- Sync when online
- Draft sistemi

#### 15. **Multi-language**
- i18n support
- Türkçe/İngilizce/Almanca
- Dynamic language switching

### Bug Fixes ve İyileştirmeler

#### Performance
- [ ] Image caching optimization
- [ ] Lazy loading için IntersectionObserver
- [ ] Video streaming buffer optimization
- [ ] Database query optimization (indexes)
- [ ] CDN entegrasyonu

#### Security
- [ ] HTTPS enforcement
- [ ] SQL injection prevention (prepared statements)
- [ ] XSS protection
- [ ] CSRF token
- [ ] Rate limiting
- [ ] Input validation (frontend & backend)

#### UX/UI
- [ ] Loading skeletons (shimmer effect)
- [ ] Error boundary (Flutter)
- [ ] Haptic feedback
- [ ] Animation polish
- [ ] Accessibility (screen reader)
- [ ] Dark mode

---

## 🎨 UI/UX İyileştirme Önerileri

### Modern UI Trendleri

#### 1. **Glassmorphism**
```dart
// Örnek: Bulanık arka plan efekti
Container(
  decoration: BoxDecoration(
    borderRadius: BorderRadius.circular(20),
    gradient: LinearGradient(
      colors: [
        Colors.white.withOpacity(0.1),
        Colors.white.withOpacity(0.05),
      ],
    ),
  ),
  child: BackdropFilter(
    filter: ImageFilter.blur(sigmaX: 10, sigmaY: 10),
    child: ...
  ),
)
```

**Nerede Kullanılabilir:**
- Modal bottom sheets
- Floating panels
- Navigation bar
- Card overlays

#### 2. **Neumorphism**
```dart
// Soft UI design
Container(
  decoration: BoxDecoration(
    color: Colors.grey[300],
    borderRadius: BorderRadius.circular(20),
    boxShadow: [
      BoxShadow(
        color: Colors.grey[500]!,
        offset: Offset(4, 4),
        blurRadius: 15,
      ),
      BoxShadow(
        color: Colors.white,
        offset: Offset(-4, -4),
        blurRadius: 15,
      ),
    ],
  ),
)
```

**Nerede Kullanılabilir:**
- Buttons
- Input fields
- Cards
- Toggle switches

#### 3. **Micro-interactions**
```dart
// Örnek: Like button animation
AnimatedScale(
  scale: _isLiked ? 1.2 : 1.0,
  duration: Duration(milliseconds: 150),
  curve: Curves.easeInOut,
  child: IconButton(
    icon: Icon(
      _isLiked ? Icons.favorite : Icons.favorite_border,
      color: _isLiked ? Colors.red : Colors.grey,
    ),
    onPressed: _toggleLike,
  ),
)
```

**Nerede Kullanılabilir:**
- Like/unlike animations
- Loading states
- Button press feedback
- Page transitions
- Pull-to-refresh

#### 4. **Gradient Overlays**
```dart
// Örnek: Event card gradient
Stack(
  children: [
    Image.network(event.coverPhoto),
    Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Colors.transparent,
            Colors.black.withOpacity(0.7),
          ],
        ),
      ),
    ),
    Positioned(
      bottom: 20,
      left: 20,
      child: Text(
        event.title,
        style: TextStyle(
          color: Colors.white,
          fontSize: 24,
          fontWeight: FontWeight.bold,
        ),
      ),
    ),
  ],
)
```

**Nerede Kullanılabilir:**
- Event cards
- Story viewer
- Hero images
- Splash screen

#### 5. **Bottom Sheet Redesign**
```dart
// Modern bottom sheet
showModalBottomSheet(
  context: context,
  isScrollControlled: true,
  backgroundColor: Colors.transparent,
  builder: (context) => Container(
    height: MediaQuery.of(context).size.height * 0.7,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.vertical(
        top: Radius.circular(25),
      ),
    ),
    child: Column(
      children: [
        // Drag handle
        Container(
          width: 40,
          height: 5,
          margin: EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: Colors.grey[300],
            borderRadius: BorderRadius.circular(10),
          ),
        ),
        // Content
        ...
      ],
    ),
  ),
);
```

#### 6. **Card Redesign**
```dart
// Modern card with elevation
Card(
  elevation: 0,
  shape: RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(20),
  ),
  child: Container(
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(20),
      gradient: LinearGradient(
        colors: [Colors.white, Colors.grey[50]!],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.05),
          blurRadius: 20,
          offset: Offset(0, 10),
        ),
      ],
    ),
    child: ...
  ),
)
```

#### 7. **Shimmer Loading**
```dart
// Placeholder loading effect
Shimmer.fromColors(
  baseColor: Colors.grey[300]!,
  highlightColor: Colors.grey[100]!,
  child: Container(
    width: double.infinity,
    height: 200,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
    ),
  ),
)
```

**Nerede Kullanılabilir:**
- Event list loading
- Media grid loading
- Profile loading

#### 8. **Animated Page Transitions**
```dart
// Custom page route
PageRouteBuilder(
  pageBuilder: (context, animation, secondaryAnimation) => NewScreen(),
  transitionsBuilder: (context, animation, secondaryAnimation, child) {
    var begin = Offset(1.0, 0.0);
    var end = Offset.zero;
    var curve = Curves.easeInOut;
    var tween = Tween(begin: begin, end: end).chain(
      CurveTween(curve: curve),
    );
    return SlideTransition(
      position: animation.drive(tween),
      child: child,
    );
  },
)
```

#### 9. **Floating Action Button Variants**
```dart
// Speed dial FAB
SpeedDial(
  icon: Icons.add,
  activeIcon: Icons.close,
  children: [
    SpeedDialChild(
      child: Icon(Icons.photo_library),
      label: 'Galeriden Seç',
      onTap: () => _pickFromGallery(),
    ),
    SpeedDialChild(
      child: Icon(Icons.camera_alt),
      label: 'Fotoğraf Çek',
      onTap: () => _takePhoto(),
    ),
    SpeedDialChild(
      child: Icon(Icons.videocam),
      label: 'Video Çek',
      onTap: () => _takeVideo(),
    ),
  ],
)
```

#### 10. **Story Viewer Improvements**
```dart
// Instagram-style story viewer
- Tap left/right to navigate
- Hold to pause
- Swipe up for details
- Progress bars at top
- User info at top
- Reply input at bottom
```

### Renk Paleti Önerileri

#### Güncel Tema (Instagram-inspired)
```dart
class AppColors {
  static const primary = Color(0xFFE1306C);      // Pink
  static const secondary = Color(0xFF833AB4);    // Purple
  static const accent = Color(0xFFFD1D1D);       // Red
  static const background = Color(0xFFFAFAFA);   // Light gray
  static const textPrimary = Color(0xFF262626);  // Dark gray
  static const textSecondary = Color(0xFF8E8E8E); // Gray
}
```

#### Modern Alternatif #1 (Minimalist)
```dart
class ModernColors {
  static const primary = Color(0xFF0A84FF);      // Blue
  static const secondary = Color(0xFF5AC8FA);    // Light blue
  static const accent = Color(0xFFFF9500);       // Orange
  static const background = Color(0xFFFFFFFF);   // White
  static const textPrimary = Color(0xFF000000);  // Black
  static const textSecondary = Color(0xFF3C3C43); // Dark gray
  static const success = Color(0xFF34C759);      // Green
  static const error = Color(0xFFFF3B30);        // Red
}
```

#### Modern Alternatif #2 (Dark Mode)
```dart
class DarkColors {
  static const primary = Color(0xFF00C853);      // Green
  static const secondary = Color(0xFF00E676);    // Light green
  static const accent = Color(0xFFFFD600);       // Yellow
  static const background = Color(0xFF121212);   // Almost black
  static const surface = Color(0xFF1E1E1E);      // Dark gray
  static const textPrimary = Color(0xFFFFFFFF);  // White
  static const textSecondary = Color(0xFFB3B3B3); // Light gray
}
```

### Tipografi Önerileri

```dart
class AppTypography {
  // Headlines
  static const h1 = TextStyle(
    fontSize: 32,
    fontWeight: FontWeight.bold,
    letterSpacing: -0.5,
  );
  
  static const h2 = TextStyle(
    fontSize: 24,
    fontWeight: FontWeight.w600,
    letterSpacing: -0.3,
  );
  
  static const h3 = TextStyle(
    fontSize: 20,
    fontWeight: FontWeight.w600,
  );
  
  // Body
  static const bodyLarge = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.normal,
    height: 1.5,
  );
  
  static const bodyMedium = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.normal,
    height: 1.4,
  );
  
  static const caption = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.normal,
    color: Colors.grey,
  );
  
  // Buttons
  static const button = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w600,
    letterSpacing: 0.5,
  );
}
```

### Font Önerileri

**Sans-serif (Modern):**
- SF Pro (iOS style) ✅
- Inter
- Poppins
- Montserrat

**Serif (Elegant):**
- Playfair Display
- Lora
- Crimson Text

### Icon Set

**Önerilen Icon Packs:**
- Material Icons (current) ✅
- Ionicons
- Feather Icons
- Font Awesome

### Spacing System

```dart
class AppSpacing {
  static const xxs = 4.0;
  static const xs = 8.0;
  static const sm = 12.0;
  static const md = 16.0;
  static const lg = 24.0;
  static const xl = 32.0;
  static const xxl = 48.0;
}
```

### Border Radius

```dart
class AppRadius {
  static const small = BorderRadius.all(Radius.circular(8));
  static const medium = BorderRadius.all(Radius.circular(12));
  static const large = BorderRadius.all(Radius.circular(20));
  static const xlarge = BorderRadius.all(Radius.circular(30));
}
```

### Gölge (Elevation)

```dart
class AppShadows {
  static final small = [
    BoxShadow(
      color: Colors.black.withOpacity(0.05),
      blurRadius: 4,
      offset: Offset(0, 2),
    ),
  ];
  
  static final medium = [
    BoxShadow(
      color: Colors.black.withOpacity(0.08),
      blurRadius: 8,
      offset: Offset(0, 4),
    ),
  ];
  
  static final large = [
    BoxShadow(
      color: Colors.black.withOpacity(0.1),
      blurRadius: 16,
      offset: Offset(0, 8),
    ),
  ];
}
```

---

## 📝 Geliştirme İpuçları

### Best Practices

1. **Kod Organizasyonu:**
   - Single Responsibility Principle
   - DRY (Don't Repeat Yourself)
   - SOLID principles
   - Clean Architecture

2. **Naming Conventions:**
   - Dart: camelCase (variables, methods)
   - Dart: PascalCase (classes)
   - PHP: snake_case (files, functions)
   - PHP: PascalCase (classes)

3. **Error Handling:**
   - Always wrap API calls in try-catch
   - Show user-friendly error messages
   - Log errors for debugging

4. **State Management:**
   - Use Provider for global state
   - Use setState for local state
   - Avoid rebuilding entire trees

5. **Performance:**
   - Use const constructors
   - Lazy load lists
   - Cache images
   - Optimize database queries

6. **Security:**
   - Never commit sensitive data (API keys, passwords)
   - Use environment variables
   - Validate all inputs
   - Sanitize user content

### Debugging

**Flutter Debugging:**
```bash
# Run with verbose logging
flutter run -v

# View device logs
flutter logs

# Analyze code
flutter analyze

# Run tests
flutter test
```

**Backend Debugging:**
```php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log to file
error_log("DEBUG: $message");

// View logs
tail -f /path/to/error.log
```

**MySQL Debugging:**
```sql
-- Enable query log
SET GLOBAL general_log = 'ON';
SET GLOBAL log_output = 'TABLE';

-- View slow queries
SELECT * FROM mysql.slow_log;

-- Explain query
EXPLAIN SELECT * FROM medya WHERE dugun_id = 1;
```

---

## 🔒 Güvenlik

### Önemli Güvenlik Notları

1. **API Keys:**
   - Firebase API keys `.gitignore`'da
   - Service Account JSON dosyası sunucu dışında
   - Backend'de environment variables kullan

2. **Passwords:**
   - bcrypt hash (cost 12+)
   - Never log passwords
   - Enforce strong password policy

3. **Sessions:**
   - Secure cookies (HttpOnly, Secure, SameSite)
   - Session timeout (30 dakika)
   - Regenerate session ID after login

4. **File Uploads:**
   - Validate file types
   - Check file size
   - Sanitize file names
   - Store outside web root (if possible)
   - Serve via PHP (prevent direct access)

5. **Database:**
   - Prepared statements (PDO)
   - Principle of least privilege (user permissions)
   - Regular backups
   - Encrypt sensitive data

---

## 📞 Destek ve İletişim

### Geliştirici İletişim
- Email: [developer@dijitalsalon.com]
- GitHub: [repository-url]
- Slack: [workspace-url]

### Dokümantasyon Güncellemeleri
Bu dokümantasyon son güncellenme tarihi: **31 Ekim 2025**

---

## 📄 Lisans

[MIT License]

---

**© 2025 Dijital Salon. Tüm hakları saklıdır.**

