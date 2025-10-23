# 📱 Digital Salon Flutter Projesi

## 🎯 Proje Özeti
Digital Salon, düğün etkinliklerini yönetmek için geliştirilmiş Flutter tabanlı mobil uygulama ve PHP backend sistemi.

## 📅 Son Güncelleme: 2025-01-27
## 👤 Geliştirici: Onur Akkalp
## 🚀 Durum: Aktif Geliştirme

---

## ✨ ÖZELLİKLER

### 🔐 Yetki Yönetim Sistemi
- **Roller:** Admin, Moderator, Yetkili Katılımcı, Katılımcı
- **Yetkiler:** Medya paylaşma, yorum yapma, hikaye paylaşma, kullanıcı engelleme, yetki düzenleme
- **Real-time Güncelleme:** Yetki değişiklikleri anında UI'da görünür

### 👥 Katılımcı Yönetimi
- **Modal Sistem:** Kullanıcı bilgilerine dokunma → Yetki düzenleme/Yasaklama
- **Yasaklama Sistemi:** Yasaklanan kullanıcılar otomatik etkinlikten çıkar
- **Periyodik Kontrol:** 10 saniye timer ile yasaklanan kullanıcı kontrolü

### 📱 Event Ekranı
- **4 Tab Yapısı:** Ana Sayfa, Medya, Hikayeler, Katılımcılar
- **AppBar:** Event adı + sağ üstte profil ikonu
- **Hikayeler:** Stories bar + tıklanabilir hikaye listesi

### 🌐 Web-Mobil Ortak Sistem
- **Ortak Yetki Sistemi:** Web ve mobil aynı yetki mantığını kullanır
- **JSON Yetkiler:** Tüm sistemlerde ortak yetki formatı
- **API Entegrasyonu:** Flutter ↔ PHP backend entegrasyonu

---

## 🛠️ TEKNOLOJİLER

### **Frontend (Flutter)**
- Flutter SDK
- Provider (State Management)
- HTTP (API Calls)
- Image Picker
- File Picker

### **Backend (PHP)**
- PHP 8.2
- MySQL Database
- PDO (Database Connection)
- JSON API Endpoints

### **Database**
- MySQL
- `dugun_katilimcilar` tablosu (yetkiler JSON, durum ENUM)
- `dugun_etkinlikler` tablosu
- `dugun_medya` tablosu

---

## 📁 PROJE YAPISI

```
digimobil_new/
├── lib/
│   ├── screens/
│   │   ├── event_detail_screen.dart    # Ana event ekranı
│   │   ├── events_screen.dart           # Etkinlikler listesi
│   │   └── login_screen.dart            # Giriş ekranı
│   ├── widgets/
│   │   ├── permission_grant_modal.dart  # Yetki modal'ı
│   │   ├── story_viewer_modal.dart     # Hikaye görüntüleme
│   │   └── instagram_post_card.dart    # Medya kartları
│   ├── services/
│   │   └── api_service.dart            # API servisleri
│   └── models/
│       └── event.dart                  # Event model
├── digimobiapi/
│   ├── grant_permissions.php           # Yetki verme/alma
│   ├── update_participant.php         # Katılımcı güncelleme
│   ├── participants.php                # Katılımcı listesi
│   └── events.php                      # Event listesi
└── docs/
    ├── SOHBET_YEDEK.md                 # Sohbet yedek ve durum raporu
    ├── TODO_LIST.md                    # TODO listesi
    └── FLUTTER_EKRANLAR.md             # Ekran dokümantasyonu
```

---

## 🚀 KURULUM VE ÇALIŞTIRMA

### **Gereksinimler**
- Flutter SDK
- PHP 8.2+
- MySQL Database
- XAMPP (Local Development)

### **Kurulum**
1. Flutter projesini klonlayın
2. `flutter pub get` komutunu çalıştırın
3. MySQL database'i oluşturun
4. PHP backend'i XAMPP'a kopyalayın
5. Database bağlantı ayarlarını yapın

### **Çalıştırma**
```bash
# Flutter uygulamasını çalıştır
flutter run --hot

# Backend API'leri test et
# http://localhost/dijitalsalon/digimobiapi/
```

---

## 📊 PROJE DURUMU

### ✅ Tamamlanan Özellikler
- [x] Yetki yönetim sistemi
- [x] Katılımcı yönetimi
- [x] Event ekranı yeniden tasarımı
- [x] Web-mobil ortak yetki sistemi
- [x] Yasaklama sistemi
- [x] Real-time güncelleme

### 🔄 Devam Eden Çalışmalar
- [ ] Test ve doğrulama
- [ ] UI iyileştirmeleri
- [ ] Performans optimizasyonu

### ⏳ Planlanan Özellikler
- [ ] Bildirim sistemi
- [ ] Etkinlik istatistikleri
- [ ] Real-time chat
- [ ] Video streaming

---

## 🐛 BİLİNEN SORUNLAR

### **Çözülen Sorunlar**
- ✅ TabController length uyumsuzluğu
- ✅ Duplicate metod hatası
- ✅ Import eksikliği
- ✅ RenderBox layout hataları
- ✅ ScaffoldMessenger dispose hatası

### **Aktif Sorunlar**
- 🔄 Hot reload hızı optimize edilecek
- 🔄 Memory leak kontrolü yapılacak

---

## 📞 İLETİŞİM VE DESTEK

- **Proje Sahibi:** Onur Akkalp
- **Teknoloji:** Flutter + PHP + MySQL
- **Sunucu:** XAMPP (Local Development)
- **Database:** MySQL (dijitalsalon)

---

## 📝 LİSANS

Bu proje özel geliştirme projesidir. Tüm hakları saklıdır.

---

## 🎯 SONRAKI ADIMLAR

1. **Test ve Doğrulama** (1-2 gün)
2. **UI İyileştirmeleri** (1 hafta)
3. **Yeni Özellikler** (1 ay)
4. **Production Deployment** (2 ay)

---

*Bu README dosyası projenin mevcut durumunu ve gelecek planlarını içerir. Her güncelleme sonrası bu dosya yenilenmelidir.*