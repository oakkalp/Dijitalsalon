# Firebase Console'a Fingerprint Ekleme

## 📋 Eklenmesi Gereken Fingerprint'ler

### 1️⃣ SHA-1 (Eski - Zaten Çalışıyor)
```
880684c32581a76c1764b9d2df383f7d8dd17415
```

**Format (Firebase Console için):**
```
88:06:84:c3:25:81:a7:6c:17:64:b9:d2:df:38:3f:7d:8d:d1:74:15
```

### 2️⃣ SHA-256 (Yeni - Önerilen)
```
C5:32:D7:E7:5C:DB:F8:E0:38:AA:3E:5B:E5:50:C2:A1:DA:34:56:9B:8C:8F:3A:F5:35:58:DF:F5:CE:7E:53:40
```

## 📝 Firebase Console Adımları

1. **Firebase Console** açın: https://console.firebase.google.com
2. **Project Settings** → **Your apps** → **DijitalSalon** (Android) seçin
3. **SHA certificate fingerprints** bölümüne gidin
4. **Add fingerprint** butonuna tıklayın
5. **SHA-1 ekleyin:**
   - Değer: `88:06:84:c3:25:81:a7:6c:17:64:b9:d2:df:38:3f:7d:8d:d1:74:15`
   - **Save** tıklayın
6. **SHA-256 ekleyin:**
   - **Add fingerprint** butonuna tekrar tıklayın
   - Değer: `C5:32:D7:E7:5C:DB:F8:E0:38:AA:3E:5B:E5:50:C2:A1:DA:34:56:9B:8C:8F:3A:F5:35:58:DF:F5:CE:7E:53:40`
   - **Save** tıklayın

## ⚠️ Not

- SHA-1 deprecated ama hala çalışıyor
- SHA-256 eklemek önerilir (gelecek için)
- Her iki fingerprint'i de ekleyebilirsiniz
- `serverClientId` zaten var, bu yüzden SHA olmadan da çalışır

## ✅ Kontrol

Firebase Console'da şunları görmelisiniz:
- ✅ SHA-1: `88:06:84:c3:25:81:a7:6c:17:64:b9:d2:df:38:3f:7d:8d:d1:74:15`
- ✅ SHA-256: `C5:32:D7:E7:5C:DB:F8:E0:38:AA:3E:5B:E5:50:C2:A1:DA:34:56:9B:8C:8F:3A:F5:35:58:DF:F5:CE:7E:53:40`

