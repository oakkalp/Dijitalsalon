# Emulator SHA-1 Sorunu - Çözüm

## 🔴 Sorun

Emulator'ün kullandığı debug keystore'un SHA-1'i ile `google-services.json` dosyasındaki SHA-1 eşleşmiyor!

**Emulator SHA-1:**
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

## 📝 Not

- Emulator ve gerçek cihaz farklı debug keystore kullanabilir
- Her biri için ayrı SHA-1 eklemek gerekebilir
- `serverClientId` tek başına Android'de yeterli olmayabilir

## ⚠️ Uyarı

Eğer "SHA-1 fingerprint already in use" hatası alırsanız:
- Bu SHA-1 başka bir projede kullanılıyor olabilir
- O projeden kaldırın veya yeni bir Firebase projesi oluşturun

