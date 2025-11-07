# Dijitalsalon Admin Panel

Modern, responsive ve güvenli admin paneli. Super Admin ve Moderator rolleri için tasarlanmış.

## 🚀 Özellikler

### 🔐 Güvenlik
- **Session Tabanlı Kimlik Doğrulama**: Güvenli giriş sistemi
- **Rol Tabanlı Erişim Kontrolü**: Super Admin ve Moderator rolleri
- **SQL Injection Koruması**: Prepared statements kullanımı
- **XSS Koruması**: HTML escape işlemleri

### 👥 Kullanıcı Rolleri
- **Super Admin**: Tüm yetkilere sahip
- **Moderator**: Düğün yönetimi ve kullanıcı atama yetkisi

### 📊 Dashboard
- **İstatistikler**: Kullanıcı, düğün, medya sayıları
- **Son Aktiviteler**: Son düğünler ve kullanıcılar
- **Gerçek Zamanlı Veriler**: Canlı istatistikler

### 🎉 Düğün Yönetimi
- **Düğün Oluşturma**: Yeni düğün ekleme
- **QR Kod Oluşturma**: Otomatik QR kod üretimi
- **Paket Yönetimi**: Temel, Premium, Lüks paketler
- **Katılımcı Yönetimi**: Kullanıcı ekleme/çıkarma

### 👤 Kullanıcı Yönetimi
- **Rol Atama**: Yetkili kullanıcı atama
- **Durum Yönetimi**: Aktif/Pasif durum kontrolü
- **Katılımcı Listesi**: Düğün katılımcıları görüntüleme

## 📁 Dosya Yapısı

```
admin/
├── index.php              # Login sayfası
├── dashboard.php          # Ana dashboard
├── events.php             # Düğün yönetimi
├── event-participants.php # Katılımcı yönetimi
├── logout.php             # Çıkış sayfası
└── README.md              # Bu dosya
```

## 🎨 Tasarım Özellikleri

### ✨ Modern UI/UX
- **Gradient Renkler**: Modern gradient tasarım
- **Smooth Animasyonlar**: CSS transitions
- **Responsive Design**: Mobil uyumlu
- **Material Design**: Modern tasarım prensipleri

### 📱 Responsive
- **Mobile-First**: Mobil öncelikli tasarım
- **Hamburger Menu**: Mobil navigasyon
- **Touch-Friendly**: Dokunmatik uyumlu
- **Flexible Grid**: Esnek grid sistemi

### 🎯 Kullanıcı Deneyimi
- **Loading States**: Yükleme durumları
- **Success/Error Messages**: Bildirim sistemi
- **Modal Dialogs**: Popup formlar
- **Confirmation Dialogs**: Onay pencereleri

## 🔧 Teknik Detaylar

### 📊 Veritabanı Bağlantısı
```php
// config/database.php
$pdo = new PDO("mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4", 'root', '');
```

### 🔐 Session Yönetimi
```php
// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}
```

### 👥 Rol Kontrolü
```php
// Sadece super_admin ve moderator erişebilir
if (!in_array($admin_user_role, ['super_admin', 'moderator'])) {
    header('Location: dashboard.php');
    exit;
}
```

## 🚀 Kullanım

### 1. Giriş Yapma
1. `https://dijitalsalon.cagapps.app/admin` adresine gidin
2. Super Admin veya Moderator bilgileri ile giriş yapın
3. Dashboard'a yönlendirileceksiniz

### 2. Düğün Oluşturma
1. "Düğünler" menüsüne gidin
2. "Yeni Düğün" butonuna tıklayın
3. Düğün bilgilerini doldurun
4. QR kod otomatik oluşturulacak

### 3. Katılımcı Yönetimi
1. Düğün kartından "Katılımcılar" butonuna tıklayın
2. Email ile katılımcı ekleyin
3. Rol değiştirin (Kullanıcı ↔ Yetkili Kullanıcı)
4. Gerektiğinde katılımcıları kaldırın

## 📱 Responsive Breakpoints

- **Desktop**: 1200px+
- **Tablet**: 768px - 1199px
- **Mobile**: 320px - 767px

## 🔒 Güvenlik Önlemleri

### 🛡️ Kimlik Doğrulama
- Session tabanlı giriş sistemi
- Şifre hash'leme (password_hash)
- Oturum timeout kontrolü

### 🔐 Yetkilendirme
- Rol tabanlı erişim kontrolü
- Sayfa seviyesinde yetki kontrolü
- İşlem seviyesinde yetki kontrolü

### 🚫 Güvenlik Açıkları
- SQL Injection koruması
- XSS koruması
- CSRF koruması (gelecekte eklenecek)

## 📊 Veritabanı Tabloları

### 👥 kullanicilar
- `id`, `ad`, `soyad`, `email`, `telefon`
- `kullanici_adi`, `sifre`, `rol`, `durum`
- `created_at`, `son_giris`

### 🎉 etkinlikler
- `id`, `title`, `description`, `date`, `location`
- `creator_id`, `qr_code`, `package_type`
- `free_access_days`, `created_at`

### 👥 dugun_katilimcilar
- `id`, `dugun_id`, `kullanici_id`, `rol`
- `durum`, `katilim_tarihi`

### 📸 medya
- `id`, `etkinlik_id`, `kullanici_id`, `dosya_yolu`
- `dosya_tipi`, `created_at`

## 🎯 Gelecek Geliştirmeler

### 📅 Kısa Vadeli
- [ ] Kullanıcı yönetimi sayfası
- [ ] Medya yönetimi sayfası
- [ ] Raporlar sayfası
- [ ] Ayarlar sayfası

### 📅 Orta Vadeli
- [ ] Bulk operations (toplu işlemler)
- [ ] Export/Import özellikleri
- [ ] Advanced filtering
- [ ] Real-time notifications

### 📅 Uzun Vadeli
- [ ] API endpoints
- [ ] Mobile admin app
- [ ] Advanced analytics
- [ ] Multi-language support

## 📞 İletişim

- **Email**: info@dijitalsalon.com
- **Telefon**: +90 (555) 123 45 67
- **Website**: www.dijitalsalon.com

---

**Dijitalsalon Admin Panel ile düğünlerinizi profesyonelce yönetin!** 💒✨
