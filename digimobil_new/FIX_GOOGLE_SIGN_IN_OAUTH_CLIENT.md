# Google Sign In OAuth Client Sorunu - Çözüm

## 🔴 Sorun

Yeni indirdiğiniz `google-services.json` dosyasında Android OAuth client yok, sadece web client var. Bu yüzden Google Sign In çalışmayabilir.

**Mevcut durum:**
- ❌ Android OAuth client yok (client_type: 1)
- ✅ Web client var (client_type: 3)

## ✅ Çözüm: SHA-1'i Tekrar Ekleyin

### Adım 1: Firebase Console'a SHA-1 Ekleyin

1. Firebase Console → **Project Settings** → **Your apps** → **DijitalSalon** (Android)
2. **SHA certificate fingerprints** bölümüne gidin
3. **Add fingerprint** butonuna tıklayın
4. SHA-1 değerini yapıştırın:
   ```
   8C:CC:1A:4D:4C:57:BE:E4:8D:A3:A8:5C:7B:FE:5D:22:BB:2E:7B:53
   ```
5. **Save** butonuna tıklayın

### Adım 2: Yeni google-services.json İndirin

1. Aynı sayfada **"Download google-services.json"** butonuna tıklayın
2. İndirilen dosyayı `android/app/google-services.json` ile **değiştirin**

### Adım 3: Kontrol Edin

Yeni dosyada şunlar olmalı:

```json
"oauth_client": [
  {
    "client_id": "...",  // Android OAuth client
    "client_type": 1,
    "android_info": {
      "package_name": "com.cagapps.dijitalsalon",
      "certificate_hash": "8CCC1A4D4C57BEE48DA3A85C7BFE5D22BB2E7B53"
    }
  },
  {
    "client_id": "...",  // Web client
    "client_type": 3
  }
]
```

## ⚠️ Uyarı Hakkında

**"One or more of your Android apps have a SHA-1 fingerprint and package name combination that's already in use"**

Bu uyarı şu durumlarda çıkar:
- Aynı SHA-1 ve package name kombinasyonu başka bir Firebase projesinde kullanılıyor
- Veya Google Cloud Console'da aynı OAuth client zaten var

**Çözüm:**
- Bu uyarı genellikle sorun yaratmaz
- Eğer sorun olursa, farklı bir package name kullanın veya SHA-1'i başka bir projeden kaldırın

## 📝 Not

`serverClientId` zaten kodda var, bu SHA-1 olmadan da çalışmasını sağlar. Ancak production için Android OAuth client (SHA-1 ile) eklemek önerilir.

