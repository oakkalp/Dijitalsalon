<?php
/**
 * OAuth Kolonları Ekleme Scripti
 * kullanicilar tablosuna google_id ve apple_id kolonlarını ekler
 */

require_once __DIR__ . '/../config/database.php';

try {
    // ✅ google_id kolonu var mı kontrol et
    $check_google = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'google_id'");
    if ($check_google->rowCount() == 0) {
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN google_id VARCHAR(255) NULL AFTER sifre");
        echo "✅ google_id kolonu eklendi\n";
    } else {
        echo "ℹ️ google_id kolonu zaten mevcut\n";
    }
    
    // ✅ apple_id kolonu var mı kontrol et
    $check_apple = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'apple_id'");
    if ($check_apple->rowCount() == 0) {
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN apple_id VARCHAR(255) NULL AFTER google_id");
        echo "✅ apple_id kolonu eklendi\n";
    } else {
        echo "ℹ️ apple_id kolonu zaten mevcut\n";
    }
    
    // ✅ Index'ler ekle (performans için)
    try {
        $pdo->exec("CREATE INDEX idx_google_id ON kullanicilar(google_id)");
        echo "✅ google_id index'i eklendi\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "ℹ️ google_id index'i zaten mevcut\n";
        } else {
            echo "⚠️ google_id index hatası: " . $e->getMessage() . "\n";
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_apple_id ON kullanicilar(apple_id)");
        echo "✅ apple_id index'i eklendi\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "ℹ️ apple_id index'i zaten mevcut\n";
        } else {
            echo "⚠️ apple_id index hatası: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ OAuth kolonları başarıyla eklendi!\n";
    
} catch (PDOException $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    exit(1);
}
?>

