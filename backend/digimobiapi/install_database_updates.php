<?php
/**
 * Database Updates Installer
 * Bu script database_updates.sql dosyasını otomatik olarak çalıştırır
 */

require_once 'bootstrap.php';

header('Content-Type: application/json');

// ✅ Güvenlik kontrolü (sadece admin/developer erişebilir)
// Bu script'i çalıştırdıktan sonra MUTLAKA silin veya şifre koyun!
$INSTALL_KEY = 'dijitalsalon2025'; // Değiştirin!
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $INSTALL_KEY) {
    die(json_encode([
        'success' => false,
        'error' => 'Unauthorized. Provide correct install key.',
        'usage' => 'install_database_updates.php?key=YOUR_KEY'
    ]));
}

try {
    $pdo = get_pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    $errors = [];
    
    echo "<h2>🚀 Database Updates Installation</h2>";
    echo "<pre>";
    
    // ✅ 1. fcm_tokens tablosu
    echo "\n📦 Creating fcm_tokens table...\n";
    try {
        // Önce foreign key constraint olmadan tabloyu oluştur
        $sql = "CREATE TABLE IF NOT EXISTS `fcm_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `token` VARCHAR(255) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        // Sonra foreign key'i ekle (eğer yoksa)
        try {
            $pdo->exec("ALTER TABLE `fcm_tokens` ADD CONSTRAINT `fk_fcm_user` 
                FOREIGN KEY (`user_id`) REFERENCES `kullanicilar`(`id`) ON DELETE CASCADE");
        } catch (PDOException $fk_error) {
            // Foreign key zaten varsa veya eklenemezse devam et
            if (strpos($fk_error->getMessage(), 'Duplicate') === false && 
                strpos($fk_error->getMessage(), 'already exists') === false) {
                echo "⚠️  Warning: Could not add foreign key: " . $fk_error->getMessage() . "\n";
            }
        }
        
        echo "✅ fcm_tokens table created successfully!\n";
        $results[] = 'fcm_tokens table created';
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ️  fcm_tokens table already exists (skipped)\n";
            $results[] = 'fcm_tokens already exists';
        } else {
            echo "❌ Error creating fcm_tokens: " . $e->getMessage() . "\n";
            $errors[] = 'fcm_tokens: ' . $e->getMessage();
        }
    }
    
    // ✅ 2. notifications tablosu
    echo "\n📦 Creating notifications table...\n";
    try {
        // Önce foreign key constraint olmadan tabloyu oluştur
        $sql = "CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `sender_id` INT NULL,
            `event_id` INT NULL,
            `media_id` INT NULL,
            `story_id` INT NULL,
            `type` ENUM('like', 'comment', 'custom', 'event_reminder') NOT NULL,
            `message` TEXT NOT NULL,
            `is_read` BOOLEAN DEFAULT FALSE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_is_read` (`is_read`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        // Sonra foreign key'leri ekle (eğer yoksa)
        try {
            $pdo->exec("ALTER TABLE `notifications` 
                ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `kullanicilar`(`id`) ON DELETE CASCADE");
        } catch (PDOException $fk_error) {
            if (strpos($fk_error->getMessage(), 'Duplicate') === false) {
                echo "⚠️  Warning: Could not add user foreign key: " . $fk_error->getMessage() . "\n";
            }
        }
        
        try {
            $pdo->exec("ALTER TABLE `notifications` 
                ADD CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `kullanicilar`(`id`) ON DELETE SET NULL");
        } catch (PDOException $fk_error) {
            if (strpos($fk_error->getMessage(), 'Duplicate') === false) {
                echo "⚠️  Warning: Could not add sender foreign key: " . $fk_error->getMessage() . "\n";
            }
        }
        
        try {
            $pdo->exec("ALTER TABLE `notifications` 
                ADD CONSTRAINT `fk_notif_event` FOREIGN KEY (`event_id`) REFERENCES `dugunler`(`id`) ON DELETE CASCADE");
        } catch (PDOException $fk_error) {
            if (strpos($fk_error->getMessage(), 'Duplicate') === false) {
                echo "⚠️  Warning: Could not add event foreign key: " . $fk_error->getMessage() . "\n";
            }
        }
        
        try {
            $pdo->exec("ALTER TABLE `notifications` 
                ADD CONSTRAINT `fk_notif_media` FOREIGN KEY (`media_id`) REFERENCES `medyalar`(`id`) ON DELETE CASCADE");
        } catch (PDOException $fk_error) {
            if (strpos($fk_error->getMessage(), 'Duplicate') === false) {
                echo "⚠️  Warning: Could not add media foreign key: " . $fk_error->getMessage() . "\n";
            }
        }
        
        echo "✅ notifications table created successfully!\n";
        $results[] = 'notifications table created';
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ️  notifications table already exists (skipped)\n";
            $results[] = 'notifications already exists';
        } else {
            echo "❌ Error creating notifications: " . $e->getMessage() . "\n";
            $errors[] = 'notifications: ' . $e->getMessage();
        }
    }
    
    // ✅ 3. dugunler tablosuna saat kolonu ekle
    echo "\n📦 Adding 'saat' column to dugunler table...\n";
    try {
        // Önce kolonun var olup olmadığını kontrol et
        $stmt = $pdo->query("SHOW COLUMNS FROM `dugunler` LIKE 'saat'");
        if ($stmt->rowCount() === 0) {
            // ✅ Tarih kolonu ismini bul
            $date_column = null;
            $columns = $pdo->query("SHOW COLUMNS FROM `dugunler`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if (stripos($col['Field'], 'tarih') !== false || 
                    stripos($col['Field'], 'date') !== false) {
                    $date_column = $col['Field'];
                    break;
                }
            }
            
            if ($date_column) {
                $sql = "ALTER TABLE `dugunler` ADD COLUMN `saat` TIME NULL AFTER `$date_column`";
                echo "   Using date column: $date_column\n";
            } else {
                // Tarih kolonu bulunamazsa sona ekle
                $sql = "ALTER TABLE `dugunler` ADD COLUMN `saat` TIME NULL";
                echo "   Date column not found, adding at the end\n";
            }
            
            $pdo->exec($sql);
            echo "✅ 'saat' column added to dugunler table!\n";
            $results[] = 'saat column added to dugunler';
        } else {
            echo "ℹ️  'saat' column already exists in dugunler (skipped)\n";
            $results[] = 'saat column already exists';
        }
    } catch (PDOException $e) {
        echo "❌ Error adding saat column: " . $e->getMessage() . "\n";
        $errors[] = 'saat column: ' . $e->getMessage();
    }
    
    // ✅ 4. kullanici_izinleri tablosuna bildirim_gonderebilir yetkisi ekle (opsiyonel)
    echo "\n📦 Checking bildirim_gonderebilir permission...\n";
    try {
        // Bu kısım yetkiler yapınıza göre değişir
        // Eğer kullanici_izinleri tablosu varsa:
        $stmt = $pdo->query("SHOW TABLES LIKE 'kullanici_izinleri'");
        if ($stmt->rowCount() > 0) {
            // Yetkinin var olup olmadığını kontrol et
            echo "ℹ️  kullanici_izinleri table exists. You may need to manually add 'bildirim_gonderebilir' permission.\n";
            $results[] = 'bildirim_gonderebilir permission check (manual required)';
        } else {
            echo "ℹ️  kullanici_izinleri table not found (skipped)\n";
            $results[] = 'kullanici_izinleri not found';
        }
    } catch (PDOException $e) {
        echo "ℹ️  Permission check: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "📊 INSTALLATION SUMMARY:\n";
    echo str_repeat('=', 60) . "\n";
    
    if (!empty($results)) {
        echo "\n✅ SUCCESSFUL OPERATIONS:\n";
        foreach ($results as $result) {
            echo "  ✓ $result\n";
        }
    }
    
    if (!empty($errors)) {
        echo "\n❌ ERRORS:\n";
        foreach ($errors as $error) {
            echo "  ✗ $error\n";
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "🎉 Database updates installation completed!\n";
    echo "⚠️  IMPORTANT: Delete this file (install_database_updates.php) after installation!\n";
    echo str_repeat('=', 60) . "\n";
    echo "</pre>";
    
    // JSON response
    echo "\n\n<script>console.log(" . json_encode([
        'success' => empty($errors),
        'results' => $results,
        'errors' => $errors,
        'message' => empty($errors) ? 'All updates installed successfully' : 'Some errors occurred'
    ]) . ");</script>";
    
} catch (Exception $e) {
    echo "<h2>❌ FATAL ERROR</h2>";
    echo "<pre>";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "</pre>";
    
    http_response_code(500);
    die();
}
?>

