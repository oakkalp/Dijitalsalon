# 🚀 Google Play Store Build ve Yükleme Rehberi

## 📋 Ön Hazırlık

### 1. Versiyon Kontrolü
- `pubspec.yaml` dosyasındaki versiyon numarasını kontrol edin:
  ```yaml
  version: 1.0.0+1
  ```
  - `1.0.0` = Kullanıcıya gösterilen versiyon (versionName)
  - `+1` = Play Store için iç versiyon numarası (versionCode)

### 2. Keystore Dosyası
- Keystore dosyası zaten mevcut: `android/app/my-release-key.keystore`
- Şifreler: `123456` (storePassword ve keyPassword)
- Key Alias: `my-key-alias`

---

## 🏗️ Build İşlemleri

### ADIM 1: APK Build (Test için)

```bash
# 1. Proje dizinine git
cd C:\xampp\htdocs\dijitalsalon\digimobil_new

# 2. Flutter clean
flutter clean

# 3. Dependencies yükle
flutter pub get

# 4. Release APK build
flutter build apk --release

# APK dosyası: build/app/outputs/flutter-apk/app-release.apk
```

### ADIM 2: App Bundle Build (Play Store için - ÖNERİLEN)

```bash
# 1. Proje dizinine git
cd C:\xampp\htdocs\dijitalsalon\digimobil_new

# 2. Flutter clean
flutter clean

# 3. Dependencies yükle
flutter pub get

# 4. App Bundle build (Play Store için)
flutter build appbundle --release

# AAB dosyası: build/app/outputs/bundle/release/app-release.aab
```

---

## 📱 Google Play Store'a Yükleme

### ADIM 1: Google Play Console'a Giriş

1. **Google Play Console**'a giriş yapın:
   - https://play.google.com/console
   - Google Developer hesabınızla giriş yapın
   - Developer hesabı ücreti: **$25 (tek seferlik)**

### ADIM 2: Yeni Uygulama Oluşturma

1. **"Uygulamalar"** sekmesine gidin
2. **"Uygulama oluştur"** butonuna tıklayın
3. Bilgileri doldurun:
   - **Uygulama adı:** DigitalSalon
   - **Varsayılan dil:** Türkçe
   - **Uygulama türü:** Uygulama
   - **Ücretsiz mi yoksa ücretli mi?** Ücretsiz

### ADIM 3: Uygulama Detayları

1. **"Uygulama içeriği"** bölümüne gidin
2. Şunları doldurun:
   - ✅ **Gizlilik Politikası URL:** (Zaten var: `dijitalsalon.cagapps.app`)
   - ✅ **Uygulama kategorisi:** Sosyal
   - ✅ **İçerik derecelendirmesi:** Soruları yanıtlayın

### ADIM 4: Store Listing (Mağaza Listesi)

1. **"Store listing"** sekmesine gidin
2. Doldurulması gerekenler:
   - **Kısa açıklama:** (En fazla 80 karakter)
   - **Tam açıklama:** (En fazla 4000 karakter)
   - **Ekran görüntüleri:** (En az 2, en fazla 8)
     - Telefon: 16:9 veya 9:16
     - Tablet: 16:9 veya 9:16
   - **Özellik grafiği:** (512x512 PNG, şeffaf arka plan)
   - **Yüksek kaliteli ikon:** (512x512 PNG, şeffaf arka plan)
   - **Görüntüler:** (En az 1, en fazla 8)

### ADIM 5: Sürüm Yükleme

1. **"Üretim"** (Production) sekmesine gidin
2. **"Yeni sürüm oluştur"** butonuna tıklayın
3. **"App Bundle veya APK yükle"** butonuna tıklayın
4. `app-release.aab` dosyasını seçin:
   ```
   build/app/outputs/bundle/release/app-release.aab
   ```
5. Yükleme tamamlanınca:
   - ✅ **Sürüm adı:** 1.0.0 (1)
   - ✅ **Sürüm notları:** İlk sürüm yayınlandı

### ADIM 6: İçerik Derecelendirmesi

1. **"İçerik derecelendirmesi"** bölümüne gidin
2. Soruları yanıtlayın:
   - ✅ **Kullanıcılar arası etkileşim var mı?** Evet
   - ✅ **Kullanıcı içeriği paylaşabilir mi?** Evet (Fotoğraf/Video)
   - ✅ **Konum bilgisi kullanılıyor mu?** Evet (Etkinlik lokasyonları)
   - ✅ **Kamera kullanılıyor mu?** Evet
   - ✅ **Galeri erişimi var mı?** Evet

### ADIM 7: Yayınlama

1. Tüm bölümler tamamlandıktan sonra:
   - ✅ **"Gözden geçir"** butonuna tıklayın
   - ✅ Tüm eksiklikleri kontrol edin
   - ✅ **"Yayınla"** butonuna tıklayın

2. **İnceleme süresi:**
   - İlk yayın: 1-7 gün
   - Güncellemeler: 1-3 gün

---

## 🔧 Build Komutları (Özet)

### APK Build (Test için)
```bash
flutter clean
flutter pub get
flutter build apk --release
```

### App Bundle Build (Play Store için)
```bash
flutter clean
flutter pub get
flutter build appbundle --release
```

### Versiyon Güncelleme
`pubspec.yaml` dosyasında:
```yaml
version: 1.0.1+2  # 1.0.1 = versionName, +2 = versionCode
```

---

## 📦 Dosya Konumları

### APK (Test için)
```
build/app/outputs/flutter-apk/app-release.apk
```

### App Bundle (Play Store için)
```
build/app/outputs/bundle/release/app-release.aab
```

---

## ⚠️ Önemli Notlar

1. **Keystore Güvenliği:**
   - Keystore dosyasını ve şifrelerini **GÜVENLİ** bir yerde saklayın
   - Keystore kaybedilirse uygulama güncellemesi yapılamaz!

2. **Versiyon Numarası:**
   - Her yeni sürümde `versionCode` artırılmalı (1, 2, 3, ...)
   - `versionName` kullanıcıya gösterilen versiyon (1.0.0, 1.0.1, 1.1.0, ...)

3. **İlk Yayın:**
   - Google Play Console'da ilk yayın için **tüm bilgiler** doldurulmalı
   - Gizlilik politikası URL'i zorunlu

4. **Test:**
   - Yayınlamadan önce **Internal Testing** veya **Closed Testing** ile test edin
   - İlk sürümü doğrudan Production'a yüklemek önerilmez

---

## 🎯 Hızlı Başlangıç

1. **Build:**
   ```bash
   flutter build appbundle --release
   ```

2. **Play Console'a git:**
   - https://play.google.com/console

3. **Uygulama oluştur** → **Store listing doldur** → **AAB yükle** → **Yayınla**

---

## 📞 Yardım

- **Flutter Build Docs:** https://docs.flutter.dev/deployment/android
- **Play Console Help:** https://support.google.com/googleplay/android-developer

