<?php
/**
 * Database Configuration
 * Digital Salon - Veritabanı konfigürasyonu
 * ✅ OPTIMIZED: Connection Pooling + Persistent Connections
 */

// VPS Database Configuration (Production - dijitalsalon.cagapps.app)
$db_host = 'localhost';
$db_name = 'admin_digisalon';
$db_user = 'admin';
$db_pass = 'zd3up16Hzmpy!';

// ✅ Global PDO instance (Singleton pattern)
$pdo = null;

/**
 * Get PDO connection (Singleton pattern + Connection Pooling)
 * ✅ OPTIMIZED: Persistent connections ve query buffering ile
 * ✅ Connection Pool: MySQL'in kendi connection pool'u kullanılıyor
 * ✅ Persistent Connections: PDO::ATTR_PERSISTENT => true ile aktif
 * ✅ Query Buffering: MYSQL_ATTR_USE_BUFFERED_QUERY => true ile aktif
 */
function get_pdo() {
    global $pdo, $db_host, $db_name, $db_user, $db_pass;
    
    if ($pdo === null) {
        try {
            // ✅ Connection pooling için persistent connection kullan
            // ✅ MySQL'in kendi connection pool'u otomatik olarak kullanılır
            // ✅ Persistent connections sayesinde her request'te yeni connection açılmaz
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // ✅ Connection pooling için persistent connection (MySQL pool kullanır)
                PDO::ATTR_PERSISTENT => true,
                // ✅ Query buffering (MySQL için - performans artışı)
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                // ✅ Connection timeout (5 saniye)
                PDO::ATTR_TIMEOUT => 5,
                // ✅ Character set (UTF-8 MB4 - emoji desteği)
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, $options);
            
            // ✅ Connection pool ayarları (ekstra optimizasyon)
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            // ✅ MySQL connection pool ayarları (MySQL tarafında)
            // MySQL'in kendi connection pool'u otomatik olarak kullanılır
            // max_connections, max_user_connections gibi ayarlar MySQL config'de yapılmalı
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die('Veritabanı bağlantı hatası: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

// ✅ Initialize PDO on first load (connection pool başlatılır)
$pdo = get_pdo();
?>
