# Google Sign In - Final Fix

## 🔴 Sorun

Emulator'ün kullandığı **DEBUG keystore** SHA-1'i ile `google-services.json` dosyasındaki SHA-1 **eşleşmiyor**!

**Emulator DEBUG keystore SHA-1:**
```
8C:CC:1A:4D:4C:57:BE:E4:8D:A3:A8:5C:7B:FE:5D:22:BB:2E:7B:53
```

**google-services.json'daki SHA-1:**
```
880684c32581a76c1764b9d2df383f7d8dd17415
```

❌ **Eşleşmiyor!** Bu yüzden `ApiException: 10` hatası alıyorsunuz.

## ✅ Çözüm

### Adım 1: Firebase Console'a Emulator SHA-1'ini Ekleyin

1. **Firebase Console** → **Project Settings** → **Your apps** → **DijitalSalon** (Android)
2. **SHA certificate fingerprints** bölümüne gidin
3. **Add fingerprint** butonuna tıklayın
4. Emulator SHA-1 değerini yapıştırın:
   ```
   8C:CC:1A:4D:4C:57:BE:E4:8D:A3:A8:5C:7B:FE:5D:22:BB:2E:7B:53
   ```
5. **Save** butonuna tıklayın

### Adım 2: Yeni google-services.json İndirin

1. Aynı sayfada **"Download google-services.json"** butonuna tıklayın
2. İndirilen dosyayı `android/app/google-services.json` ile **değiştirin**

### Adım 3: Kontrol Edin

Yeni dosyada şu olmalı:
```json
"certificate_hash": "8CCC1A4D4C57BEE48DA3A85C7BFE5D22BB2E7B53"
```

### Adım 4: Uygulamayı Tamamen Kapatıp Yeniden Başlatın

⚠️ **ÖNEMLİ**: Hot restart yeterli değil!
1. Uygulamayı tamamen kapatın
2. `flutter clean`
3. `flutter run`

## 📝 Notlar

- **Debug keystore**: `%USERPROFILE%\.android\debug.keystore` (emulator bu kullanıyor)
- **Release keystore**: `android/app/my-release-key.keystore` (şu an kullanılmıyor, build.gradle'da debug kullanılıyor)
- **Gerçek cihaz**: Farklı bir debug keystore kullanabilir, o da eklenmeli

## ⚠️ Uyarı

Eğer "SHA-1 fingerprint already in use" hatası alırsanız:
- Bu SHA-1 başka bir Firebase projesinde kullanılıyor olabilir
- O projeden kaldırın veya yeni bir Firebase projesi oluşturun

## ✅ Mevcut Durum

- ✅ `serverClientId` kodda var (SHA gerektirmez ama Android'de yeterli olmayabilir)
- ⚠️ Emulator SHA-1 Firebase'e eklenmeli
- ✅ google-services.json güncellenmeli

