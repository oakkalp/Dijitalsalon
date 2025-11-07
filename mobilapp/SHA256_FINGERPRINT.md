# SHA-256 Fingerprint Ekleme

## 🔴 Sorun
SHA-1 deprecated (kullanımdan kaldırılmış) ve Firebase Console'da uyarı veriyor.

## ✅ SHA-256 Fingerprint

**SHA-256 Değeri:**
```
C5:32:D7:E7:5C:DB:F8:E0:38:AA:3E:5B:E5:50:C2:A1:DA:34:56:9B:8C:8F:3A:F5:35:58:DF:F5:CE:7E:53:40
```

## 📝 Firebase Console'a Ekleme

1. Firebase Console → **Project Settings** → **Your apps** → **DijitalSalon** (Android)
2. **SHA certificate fingerprints** bölümüne gidin
3. **Add fingerprint** butonuna tıklayın
4. SHA-256 değerini yapıştırın: `C5:32:D7:E7:5C:DB:F8:E0:38:AA:3E:5B:E5:50:C2:A1:DA:34:56:9B:8C:8F:3A:F5:35:58:DF:F5:CE:7E:53:40`
5. **Save** butonuna tıklayın

## ⚠️ Not

`serverClientId` kullanıldığı için SHA-1/SHA-256 olmadan da çalışır, ancak SHA-256 eklemek önerilir.

## ✅ Mevcut Durum

- ✅ SHA-1 eklendi (uyarı veriyor ama çalışıyor)
- ⚠️ SHA-256 eklenmeli (daha güvenli)
- ✅ `serverClientId` eklendi (SHA gerektirmez)

