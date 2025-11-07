<?php
/**
 * SMTP Email Configuration
 * 
 * Bu dosya SMTP email ayarlarını içerir.
 * Production ortamında bu ayarları doldurmalısınız.
 */

return [
    'enabled' => true, // ✅ SMTP kullanımını etkinleştir/devre dışı bırak
    
    // SMTP Server Ayarları - Yandex
    'host' => 'smtp.yandex.com', // Yandex SMTP
    'port' => 465, // Yandex için 465 (SSL) - Port 465 firewall'dan açıldı
    'encryption' => 'ssl', // Yandex için 'ssl' (465 port için)
    'auth' => true, // Authentication gerekiyor mu?
    
    // SMTP Kullanıcı Bilgileri
    'username' => 'dijitalsalon@cagapps.app', // ✅ Yandex email adresi
    'password' => 'uifoniopybgmkcae', // ✅ Yandex uygulama şifresi (⚠️ Güvenlik: Admin panelden değiştirilebilir)
    
    // Gönderen Bilgileri
    'from_email' => 'dijitalsalon@cagapps.app',
    'from_name' => 'Digital Salon',
    
    // Alternatif SMTP Servisleri:
    // Gmail: smtp.gmail.com:587 (TLS), username: email@gmail.com, password: App Password
    // Outlook/Hotmail: smtp-mail.outlook.com:587 (TLS)
    // Yandex: smtp.yandex.com:465 (SSL) ✅ Şu an kullanılan
    // SendGrid: smtp.sendgrid.net:587 (TLS), username: apikey, password: API key
    // Mailgun: smtp.mailgun.org:587 (TLS)
];

