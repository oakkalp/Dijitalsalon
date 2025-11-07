<?php
/**
 * Password Resets Tablosu Oluşturma Script'i
 * 
 * Kullanım: Tarayıcıdan şu URL'yi açın:
 * https://dijitalsalon.cagapps.app/digimobiapi/create_password_resets_table.php
 * 
 * NOT: Tablo oluşturulduktan sonra bu dosyayı güvenlik için silin veya korumalı klasöre taşıyın!
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Resets Tablosu Oluştur</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #E1306C;
        }
        .success {
            color: #27ae60;
            background: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: #e74c3c;
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            color: #0c5460;
            background: #d1ecf1;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Password Resets Tablosu Oluştur</h1>
        
        <?php
        try {
            // ✅ Tablo var mı kontrol et
            $checkTable = $pdo->query("SHOW TABLES LIKE 'password_resets'");
            $tableExists = $checkTable->rowCount() > 0;
            
            if ($tableExists) {
                echo '<div class="info">';
                echo '<strong>ℹ️ Bilgi:</strong> <code>password_resets</code> tablosu zaten mevcut.';
                echo '</div>';
                
                // ✅ Tablo yapısını göster
                $stmt = $pdo->query("DESCRIBE password_resets");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<h3>Mevcut Tablo Yapısı:</h3>';
                echo '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
                echo '<tr style="background: #f0f0f0;"><th>Alan</th><th>Tip</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
                foreach ($columns as $col) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($col['Field']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($col['Extra']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                // ✅ Tabloyu yeniden oluştur seçeneği
                if (isset($_GET['recreate']) && $_GET['recreate'] === 'yes') {
                    echo '<div class="info" style="margin-top: 20px;">';
                    echo '<strong>⚠️ UYARI:</strong> Tablo yeniden oluşturulacak, mevcut veriler silinecek!';
                    echo '</div>';
                    
                    // Önce tabloyu sil
                    $pdo->exec("DROP TABLE IF EXISTS password_resets");
                    echo '<div class="info">Mevcut tablo silindi.</div>';
                    $tableExists = false;
                } else {
                    echo '<div style="margin-top: 20px;">';
                    echo '<a href="?recreate=yes" style="color: #e74c3c; text-decoration: none; background: #f8d7da; padding: 10px 20px; border-radius: 5px; display: inline-block;">Tablo Yeniden Oluştur (Veriler Silinecek)</a>';
                    echo '</div>';
                    exit;
                }
            }
            
            if (!$tableExists) {
                // ✅ Önce kullanicilar tablosunun yapısını kontrol et
                $userTableCheck = $pdo->query("SHOW TABLES LIKE 'kullanicilar'");
                if ($userTableCheck->rowCount() == 0) {
                    throw new Exception("kullanicilar tablosu bulunamadı!");
                }
                
                // ✅ kullanicilar tablosunun id alanını kontrol et
                $userTableInfo = $pdo->query("DESCRIBE kullanicilar");
                $userColumns = $userTableInfo->fetchAll(PDO::FETCH_ASSOC);
                $userIdColumn = null;
                foreach ($userColumns as $col) {
                    if ($col['Field'] === 'id') {
                        $userIdColumn = $col;
                        break;
                    }
                }
                
                if (!$userIdColumn) {
                    throw new Exception("kullanicilar tablosunda 'id' alanı bulunamadı!");
                }
                
                // ✅ kullanicilar tablosunun engine'ini kontrol et
                $userTableEngine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kullanicilar'");
                $engine = $userTableEngine->fetch(PDO::FETCH_ASSOC);
                $useInnoDB = ($engine && strtoupper($engine['ENGINE']) === 'INNODB');
                
                echo '<div class="info">';
                echo '<strong>🔍 Kontrol:</strong><br>';
                echo 'kullanicilar.id tipi: <code>' . htmlspecialchars($userIdColumn['Type']) . '</code><br>';
                echo 'kullanicilar tablosu Engine: <code>' . htmlspecialchars($engine['ENGINE'] ?? 'Bilinmiyor') . '</code><br>';
                echo '</div>';
                
                // ✅ user_id tipini kullanicilar.id ile eşleştir
                $userIdType = $userIdColumn['Type']; // Örn: INT, BIGINT, etc.
                
                // ✅ Foreign key için InnoDB gerekli, eğer kullanicilar MyISAM ise foreign key kullanmayalım
                if (!$useInnoDB) {
                    echo '<div class="info">';
                    echo '<strong>⚠️ Uyarı:</strong> kullanicilar tablosu InnoDB değil. Foreign key constraint eklenmeyecek (manuel kontrol gerekli).';
                    echo '</div>';
                    
                    $sql = "
                    CREATE TABLE password_resets (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id $userIdType NOT NULL,
                        token VARCHAR(64) NOT NULL UNIQUE,
                        expires_at DATETIME NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_token (token),
                        INDEX idx_user_id (user_id),
                        INDEX idx_expires_at (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ";
                } else {
                    // ✅ InnoDB ise foreign key ekle
                    $sql = "
                    CREATE TABLE password_resets (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id $userIdType NOT NULL,
                        token VARCHAR(64) NOT NULL UNIQUE,
                        expires_at DATETIME NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
                        INDEX idx_token (token),
                        INDEX idx_user_id (user_id),
                        INDEX idx_expires_at (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ";
                }
                
                $pdo->exec($sql);
                
                echo '<div class="success">';
                echo '<strong>✅ Başarılı!</strong> <code>password_resets</code> tablosu başarıyla oluşturuldu.';
                echo '</div>';
                
                // ✅ Tablo yapısını göster
                $stmt = $pdo->query("DESCRIBE password_resets");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<h3>Oluşturulan Tablo Yapısı:</h3>';
                echo '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
                echo '<tr style="background: #f0f0f0;"><th>Alan</th><th>Tip</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
                foreach ($columns as $col) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($col['Field']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($col['Extra']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                echo '<div class="info" style="margin-top: 20px;">';
                echo '<strong>🔒 Güvenlik:</strong> Bu dosyayı tablo oluşturulduktan sonra silmeniz veya korumalı klasöre taşımanız önerilir.';
                echo '</div>';
            }
            
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<strong>❌ Hata:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
            
            // ✅ Hata detayları
            echo '<div class="info" style="margin-top: 20px;">';
            echo '<strong>Hata Detayları:</strong><br>';
            echo '<code>' . htmlspecialchars($e->getTraceAsString()) . '</code>';
            echo '</div>';
        }
        ?>
        
        <hr style="margin: 30px 0;">
        
        <div class="info">
            <strong>📝 Not:</strong>
            <ul>
                <li>Bu script <code>password_resets</code> tablosunu oluşturur</li>
                <li>Tablo şifre sıfırlama token'larını saklar</li>
                <li>Token'lar 24 saat geçerlidir</li>
                <li>Foreign key ile <code>kullanicilar</code> tablosuna bağlıdır</li>
            </ul>
        </div>
    </div>
</body>
</html>

