# Firebase google-services.json Güncelleme

## 🔴 Sorun
Firebase Console'da birden fazla SHA-1 var, ancak `google-services.json` dosyası eski.

## ✅ Çözüm

### Adım 1: Firebase Console'dan Yeni Dosyayı İndirin

1. Firebase Console → **Project Settings** → **Your apps** → **DijitalSalon** (Android)
2. **"google-services.json"** butonuna tıklayın (sağ üstte "Download google-services.json" butonu)
3. İndirilen dosyayı `android/app/google-services.json` ile **değiştirin**

### Adım 2: Kontrol

Yeni dosyada şunları kontrol edin:
- ✅ Yeni SHA-1: `8CCC1A4D4C57BEE48DA3A85C7BFE5D22BB2E7B53` (satır 21'de)
- ✅ Eski SHA-1: `880684c32581a76c1764b9d2df383f7d8dd17415` (varsa, sorun değil - birden fazla OAuth client olabilir)

### Adım 3: Uygulamayı Tamamen Kapatıp Yeniden Başlatın

⚠️ **ÖNEMLİ**: Hot restart yeterli değil!
1. Uygulamayı tamamen kapatın
2. `flutter clean` (opsiyonel ama önerilir)
3. `flutter run` ile yeniden başlatın

## 📝 Not

Firebase Console'da **birden fazla SHA-1** olması sorun değil. Her SHA-1 için ayrı bir OAuth client oluşturulur. Ancak `google-services.json` dosyasında sadece bir tanesi `certificate_hash` olarak görünür. 

Eğer Firebase Console'dan yeni dosyayı indirmezseniz, Google Sign In yine çalışmayabilir çünkü Firebase backend'i her iki SHA-1'i de tanıyor olabilir ama dosyada sadece bir tanesi var.

