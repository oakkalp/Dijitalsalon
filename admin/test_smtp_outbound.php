<?php
/**
 * SMTP Outbound Bağlantı Testi
 * Windows Firewall ve Outbound bağlantı kontrolü
 */

session_start();

// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

$admin_user_role = $_SESSION['admin_user_role'];

// Sadece super_admin erişebilir
if ($admin_user_role !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

echo "========================================\n";
echo "OUTBOUND SMTP BAĞLANTI DETAYLI TEST\n";
echo "========================================\n\n";

// Test edilecek portlar
$test_ports = [
    ['port' => 587, 'type' => 'TLS', 'host' => 'smtp.yandex.com'],
    ['port' => 465, 'type' => 'SSL', 'host' => 'smtp.yandex.com'],
    ['port' => 25, 'type' => 'Plain', 'host' => 'smtp.yandex.com'],
];

foreach ($test_ports as $test) {
    echo "Test: {$test['host']}:{$test['port']} ({$test['type']})\n";
    echo str_repeat('-', 50) . "\n";
    
    // TCP Test
    $start_time = microtime(true);
    $connection = @fsockopen($test['host'], $test['port'], $errno, $errstr, 5);
    $end_time = microtime(true);
    $duration = round(($end_time - $start_time) * 1000, 2);
    
    if ($connection) {
        echo "✓ TCP Bağlantı: BAŞARILI ({$duration}ms)\n";
        fclose($connection);
    } else {
        echo "✗ TCP Bağlantı: BAŞARISIZ\n";
        echo "  Hata: $errstr ($errno)\n";
        echo "  Süre: {$duration}ms\n";
        
        // Hata analizi
        if ($errno == 10060) {
            echo "  → Windows Timeout: Sunucu yanıt vermiyor veya firewall engelliyor\n";
        } elseif ($errno == 10061) {
            echo "  → Connection Refused: Port kapalı\n";
        } elseif ($errno == 11001) {
            echo "  → Host not found: DNS sorunu\n";
        }
    }
    echo "\n";
}

// DNS Kontrolü
echo "DNS Kontrolü:\n";
echo str_repeat('-', 50) . "\n";
$dns_result = gethostbyname('smtp.yandex.com');
if ($dns_result !== 'smtp.yandex.com') {
    echo "✓ DNS Çözümleme: BAŞARILI\n";
    echo "  IP: $dns_result\n";
    
    // Tüm IP'leri listele
    $ips = gethostbynamel('smtp.yandex.com');
    if ($ips) {
        echo "  Tüm IP'ler: " . implode(', ', $ips) . "\n";
    }
} else {
    echo "✗ DNS Çözümleme: BAŞARISIZ\n";
}
echo "\n";

// Yandex MX kayıtları kontrolü
echo "Yandex MX Kayıtları:\n";
echo str_repeat('-', 50) . "\n";
$mx_records = [];
if (function_exists('getmxrr')) {
    getmxrr('yandex.com', $mx_hosts, $mx_weights);
    if (!empty($mx_hosts)) {
        foreach ($mx_hosts as $index => $mx) {
            echo "  MX {$mx_weights[$index]}: $mx\n";
        }
    }
}
echo "\n";

// Alternatif test: Doğrudan IP ile bağlan
echo "Doğrudan IP ile Test:\n";
echo str_repeat('-', 50) . "\n";
$yandex_ips = ['87.250.250.25', '87.250.250.242']; // Yandex SMTP IP'leri (örnek)
foreach ($yandex_ips as $ip) {
    echo "Test: $ip:587\n";
    $connection = @fsockopen($ip, 587, $errno, $errstr, 5);
    if ($connection) {
        echo "  ✓ Bağlantı başarılı!\n";
        fclose($connection);
        break;
    } else {
        echo "  ✗ Bağlantı başarısız: $errstr ($errno)\n";
    }
}
echo "\n";

// Windows Firewall kontrolü (eğer mümkünse)
echo "Sistem Bilgileri:\n";
echo str_repeat('-', 50) . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Bilinmiyor') . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? '✓ Aktif' : '✗ Pasif') . "\n";
if (extension_loaded('openssl')) {
    echo "OpenSSL Version: " . OPENSSL_VERSION_TEXT . "\n";
}
echo "\n";

// Öneriler
echo "========================================\n";
echo "ÖNERİLER:\n";
echo "========================================\n\n";
echo "1. Plesk Firewall:\n";
echo "   - Sadece inbound değil, OUTBOUND bağlantıları da kontrol edin\n";
echo "   - Plesk > Tools & Settings > Firewall\n";
echo "   - Outbound SMTP (Port 587, 465) için izin ekleyin\n\n";

echo "2. Windows Firewall:\n";
echo "   - Windows Defender Firewall > Outbound Rules\n";
echo "   - SMTP Outbound (Port 587, 465) için yeni kural oluşturun\n\n";

echo "3. IIS Application Pool:\n";
echo "   - Application Pool identity'nin dışarıya bağlanma izni olmalı\n";
echo "   - Network Service veya özel bir service account kullanın\n\n";

echo "4. Hosting Sağlayıcı:\n";
echo "   - Port 587 ve 465 için OUTBOUND erişim istendiğini belirtin\n";
echo "   - Bazı hosting'ler SMTP çıkışını sınırlar\n\n";

echo "5. Alternatif Çözüm:\n";
echo "   - PHPMailer kütüphanesini kullanın (Composer ile)\n";
echo "   - Veya SendGrid/Mailgun gibi SMTP servisleri kullanın\n\n";

