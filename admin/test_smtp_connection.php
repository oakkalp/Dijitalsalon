<?php
/**
 * SMTP Bağlantı Test Scripti
 * Sunucudan direkt SMTP bağlantısını test eder
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

// SMTP ayarlarını yükle
$smtp_config = require __DIR__ . '/../config/smtp.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "========================================\n";
echo "SMTP BAĞLANTI TEST RAPORU\n";
echo "========================================\n\n";

echo "SMTP Ayarları:\n";
echo "- Host: " . ($smtp_config['host'] ?? 'Ayarlanmamış') . "\n";
echo "- Port: " . ($smtp_config['port'] ?? 'Ayarlanmamış') . "\n";
echo "- Encryption: " . ($smtp_config['encryption'] ?? 'Yok') . "\n";
echo "- Username: " . ($smtp_config['username'] ?? 'Ayarlanmamış') . "\n\n";

if (empty($smtp_config['host']) || empty($smtp_config['port'])) {
    echo "❌ HATA: SMTP ayarları eksik!\n";
    exit;
}

$host = $smtp_config['host'];
$port = (int)($smtp_config['port']);
$encryption = $smtp_config['encryption'] ?? '';

// 1. PHP OpenSSL Kontrolü
echo "1. PHP OpenSSL Extension Kontrolü:\n";
if (extension_loaded('openssl')) {
    echo "   ✓ OpenSSL aktif\n";
    $openssl_version = OPENSSL_VERSION_TEXT ?? 'Bilinmiyor';
    echo "   - Versiyon: $openssl_version\n";
} else {
    echo "   ✗ OpenSSL aktif değil!\n";
    echo "   → Çözüm: php.ini dosyasında 'extension=openssl' satırını aktif edin\n";
}
echo "\n";

// 2. TCP Socket Testi
echo "2. TCP Socket Bağlantı Testi (Port $port):\n";
$tcp_connection = @fsockopen($host, $port, $errno, $errstr, 10);
if ($tcp_connection) {
    echo "   ✓ Başarılı - Port $port açık\n";
    fclose($tcp_connection);
} else {
    echo "   ✗ Başarısız - $errstr ($errno)\n";
    echo "   → Bu genellikle firewall engeli veya port kapalı anlamına gelir\n";
}
echo "\n";

// 3. SSL/TLS Stream Testi
if ($encryption === 'ssl' || $encryption === 'tls') {
    echo "3. " . strtoupper($encryption) . " Stream Bağlantı Testi:\n";
    
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    if ($encryption === 'ssl') {
        $socket_address = "ssl://{$host}:{$port}";
    } else {
        $socket_address = "tcp://{$host}:{$port}";
    }
    
    $ssl_connection = @stream_socket_client(
        $socket_address, 
        $ssl_errno, 
        $ssl_errstr, 
        10, 
        STREAM_CLIENT_CONNECT, 
        $context
    );
    
    if ($ssl_connection) {
        echo "   ✓ Bağlantı kuruldu\n";
        
        if ($encryption === 'tls') {
            echo "   → STARTTLS komutu gönderiliyor...\n";
            stream_set_blocking($ssl_connection, true);
            
            // İlk yanıt
            $response = fgets($ssl_connection, 515);
            echo "   - Server: " . trim($response) . "\n";
            
            if (preg_match('/^220/', $response)) {
                // EHLO
                fwrite($ssl_connection, "EHLO {$host}\r\n");
                $response = fgets($ssl_connection, 515);
                echo "   - EHLO: " . trim($response) . "\n";
                
                // STARTTLS
                fwrite($ssl_connection, "STARTTLS\r\n");
                $response = fgets($ssl_connection, 515);
                echo "   - STARTTLS: " . trim($response) . "\n";
                
                if (preg_match('/^220/', $response)) {
                    if (stream_socket_enable_crypto($ssl_connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        echo "   ✓ TLS şifreleme aktif\n";
                    } else {
                        echo "   ✗ TLS handshake başarısız\n";
                    }
                }
            }
        }
        
        fclose($ssl_connection);
    } else {
        echo "   ✗ Bağlantı kurulamadı - $ssl_errstr ($ssl_errno)\n";
    }
    echo "\n";
}

// 4. Alternatif Port Önerisi
echo "4. Alternatif Port Önerisi:\n";
if ($port == 465 && $encryption === 'ssl') {
    echo "   → Port 465 bağlanamıyorsa, Port 587 (TLS) kullanmayı deneyin:\n";
    echo "     - Port: 587\n";
    echo "     - Encryption: tls\n";
    echo "     - Yandex hem 465 (SSL) hem de 587 (TLS) destekler\n";
} else if ($port == 587 && $encryption === 'tls') {
    echo "   → Port 587 bağlanamıyorsa, Port 465 (SSL) kullanmayı deneyin:\n";
    echo "     - Port: 465\n";
    echo "     - Encryption: ssl\n";
}
echo "\n";

// 5. Sistem Bilgileri
echo "5. Sistem Bilgileri:\n";
echo "   - PHP Version: " . PHP_VERSION . "\n";
echo "   - OS: " . PHP_OS . "\n";
echo "   - Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Bilinmiyor') . "\n";
echo "\n";

// Sonuç
echo "========================================\n";
echo "SONUÇ:\n";
if (isset($ssl_connection) && $ssl_connection) {
    echo "✓ SMTP bağlantısı başarılı!\n";
} else if (isset($tcp_connection) && $tcp_connection) {
    echo "⚠ TCP bağlantısı başarılı ama SSL/TLS sorunlu\n";
} else {
    echo "✗ SMTP bağlantısı başarısız!\n";
    echo "\nÖneriler:\n";
    echo "1. Firewall'da port $port'u açın\n";
    echo "2. Port 587 (TLS) kullanmayı deneyin\n";
    echo "3. Hosting sağlayıcınızdan port durumunu kontrol edin\n";
    echo "4. Yandex Mail'de 'Uygulama şifreleri' kullanın\n";
}
echo "========================================\n";

