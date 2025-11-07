# Apple Sign In Kurulum Rehberi

## 📱 Apple Developer Console Ayarları

### 1. Apple Developer Hesabı
- ✅ **Ücretli Apple Developer Account** gerekli ($99/yıl)
- ❌ Ücretsiz hesap ile **Apple Sign In kullanılamaz**

### 2. App ID Yapılandırması

1. [Apple Developer Portal](https://developer.apple.com/account/) → **Certificates, Identifiers & Profiles**
2. Sol menüden **Identifiers** → **App IDs**
3. Mevcut App ID'yi bul (veya yeni oluştur):
   - **Bundle ID**: `com.cagapps.dijitalsalon`
4. **Edit** butonuna tıkla
5. **Capabilities** bölümünde **Sign In with Apple** seçeneğini **✅ işaretle**
6. **Save** → **Continue** → **Register**

### 3. Service ID Oluşturma (Opsiyonel - Web için)

Eğer web'den de Apple Sign In kullanmak isterseniz:

1. **Identifiers** → **Services IDs** → **+** (New)
2. **Description**: `Digital Salon Web Service`
3. **Identifier**: `com.cagapps.dijitalsalon.web` (unique bir ID)
4. **Configure** → **Sign In with Apple**:
   - **Primary App ID**: `com.cagapps.dijitalsalon` seç
   - **Domains and Subdomains**: `dijitalsalon.cagapps.app`
   - **Return URLs**: `https://dijitalsalon.cagapps.app/digimobiapi/oauth/apple.php`
5. **Save** → **Continue** → **Register**

---

## 🔧 Xcode Yapılandırması

### 1. Signing & Capabilities

1. Xcode'u aç
2. `ios/Runner.xcworkspace` dosyasını aç
3. Sol panelde **Runner** projesini seç
4. **Signing & Capabilities** sekmesine git
5. **+ Capability** butonuna tıkla
6. **Sign In with Apple** ekle
7. ✅ Otomatik olarak Capability eklenecek

### 2. Bundle ID Kontrolü

- **Bundle Identifier**: `com.cagapps.dijitalsalon` olmalı
- **Team**: Apple Developer Team seçili olmalı
- **Signing Certificate**: Valid bir certificate olmalı

---

## 🚀 Flutter Tarafı (Hazır)

Kod tarafında her şey hazır:
- ✅ `sign_in_with_apple` paketi eklendi
- ✅ `LoginScreen`'de Apple butonu var (sadece iOS'ta görünür)
- ✅ `AuthProvider` ve `ApiService` metodları hazır
- ✅ Backend endpoint hazır

---

## 📋 Test Adımları

### 1. iOS Simulator/Device'da Test

```bash
flutter run --release
# veya
flutter build ios
```

### 2. Apple Sign In Akışı

1. Uygulamayı aç
2. Login ekranında **"Apple ile Giriş Yap"** butonuna tıkla
3. Apple ID ile giriş yap
4. İzinleri onayla (Email, Name)
5. Backend'e token gönderilir
6. Otomatik olarak ana sayfaya yönlendirilir

---

## ⚠️ Önemli Notlar

### Apple'nin Kısıtlamaları:
1. **Gerçek Cihaz Gerekli**: iOS Simulator'da **çalışmaz**, gerçek cihazda test edilmeli
2. **iOS 13+**: Apple Sign In iOS 13 ve üzeri gerektirir
3. **Sandbox Test**: Test için Apple Developer Account ile giriş yapılmalı

### Production için Ek Yapılandırma:

Şu an backend'de basit token verification yapıyoruz. Production için:

1. **Apple JWT Verification** eklenebilir:
   - Apple'nin public key'leri ile JWT doğrulama
   - `oauth/apple.php` dosyasında JWT verification kodu

2. **Privacy Policy URL**: Apple Sign In butonu için privacy policy URL'i gerekli

---

## 🔍 Troubleshooting

### "Sign In with Apple is not enabled"
- ✅ Apple Developer Console'da App ID'de capability aktif mi kontrol et
- ✅ Xcode'da Capability eklendi mi kontrol et
- ✅ Bundle ID eşleşiyor mu kontrol et

### "Invalid client"
- ✅ Service ID doğru mu kontrol et
- ✅ Return URL doğru mu kontrol et

### iOS Simulator'da çalışmıyor
- ✅ **Normal davranış** - Gerçek cihazda test et

---

## 📝 Özet Checklist

- [ ] Apple Developer Account var ($99/yıl)
- [ ] App ID'de "Sign In with Apple" capability aktif
- [ ] Xcode'da "Sign In with Apple" capability eklendi
- [ ] Bundle ID doğru: `com.cagapps.dijitalsalon`
- [ ] Team seçili ve valid
- [ ] Gerçek iOS cihazında test edildi
- [ ] Backend endpoint hazır: `/digimobiapi/oauth/apple.php`
- [ ] Database migration çalıştırıldı: `add_oauth_columns.php`

---

## 🎯 Şu Anki Durum

✅ **Flutter Kodları Hazır**
✅ **Backend Endpoint Hazır** (`oauth/apple.php`)
✅ **Database Migration Hazır** (`add_oauth_columns.php`)

❌ **Apple Developer Console Yapılandırması Gerekli**
❌ **Xcode Capability Ekleme Gerekli**

