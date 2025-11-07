<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo->beginTransaction();
    
    $alterations = [];
    
    // ✅ 1. profil_fotografi sütunu var mı kontrol et
    $stmt = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'profil_fotografi'");
    if ($stmt->rowCount() == 0) {
        // ✅ profil_resmi sütunu var mı kontrol et
        $stmt2 = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'profil_resmi'");
        if ($stmt2->rowCount() > 0) {
            // profil_resmi var, profil_fotografi'ye dönüştür
            $pdo->exec("ALTER TABLE kullanicilar CHANGE COLUMN profil_resmi profil_fotografi VARCHAR(500) NULL");
            $alterations[] = "✅ profil_resmi → profil_fotografi olarak değiştirildi";
            
            // Eğer durum sütununda profil fotoğrafı yolu varsa, profil_fotografi'ye taşı
            $stmt3 = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'durum'");
            if ($stmt3->rowCount() > 0) {
                // durum sütununda profil fotoğrafı yolu olan kayıtları kontrol et
                $stmt4 = $pdo->query("
                    SELECT id, durum, profil_fotografi 
                    FROM kullanicilar 
                    WHERE durum IS NOT NULL 
                    AND durum != '' 
                    AND durum != 'aktif' 
                    AND durum != 'pasif'
                    AND profil_fotografi IS NULL
                ");
                $moved_count = 0;
                while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
                    // durum sütunundaki değer profil fotoğrafı yolu olabilir
                    $possible_path = $row['durum'];
                    if (strpos($possible_path, 'http') === 0 || strpos($possible_path, '/') === 0 || strpos($possible_path, 'uploads/') !== false) {
                        $pdo->prepare("UPDATE kullanicilar SET profil_fotografi = ? WHERE id = ?")->execute([$possible_path, $row['id']]);
                        $pdo->prepare("UPDATE kullanicilar SET durum = 'aktif' WHERE id = ?")->execute([$row['id']]);
                        $moved_count++;
                    }
                }
                if ($moved_count > 0) {
                    $alterations[] = "✅ $moved_count kullanıcının profil fotoğrafı durum sütunundan profil_fotografi'ye taşındı";
                }
            }
        } else {
            // Hiçbir profil fotoğrafı sütunu yok, yeni oluştur
            $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN profil_fotografi VARCHAR(500) NULL AFTER telefon");
            $alterations[] = "✅ profil_fotografi sütunu oluşturuldu";
        }
    } else {
        $alterations[] = "✅ profil_fotografi sütunu zaten mevcut";
    }
    
    // ✅ 2. google_id sütunu var mı kontrol et
    $stmt = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'google_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email");
        $alterations[] = "✅ google_id sütunu oluşturuldu";
    } else {
        $alterations[] = "✅ google_id sütunu zaten mevcut";
    }
    
    // ✅ 3. apple_id sütunu var mı kontrol et
    $stmt = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'apple_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN apple_id VARCHAR(255) NULL UNIQUE AFTER google_id");
        $alterations[] = "✅ apple_id sütunu oluşturuldu";
    } else {
        $alterations[] = "✅ apple_id sütunu zaten mevcut";
    }
    
    // ✅ 4. durum sütunu kontrol et (eğer yoksa oluştur, ama profil fotoğrafı için değil)
    $stmt = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'durum'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN durum ENUM('aktif', 'pasif') DEFAULT 'aktif' AFTER rol");
        $alterations[] = "✅ durum sütunu oluşturuldu (aktif/pasif için)";
    } else {
        // durum sütununda profil fotoğrafı yolu olan kayıtları temizle
        $stmt = $pdo->query("
            SELECT id, durum, profil_fotografi 
            FROM kullanicilar 
            WHERE durum IS NOT NULL 
            AND durum != '' 
            AND durum != 'aktif' 
            AND durum != 'pasif'
        ");
        $cleaned_count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $possible_path = $row['durum'];
            // Profil fotoğrafı yolu olup olmadığını kontrol et
            if (strpos($possible_path, 'http') === 0 || 
                strpos($possible_path, '/') === 0 || 
                strpos($possible_path, 'uploads/') !== false || 
                strpos($possible_path, '.jpg') !== false || 
                strpos($possible_path, '.jpeg') !== false ||
                strpos($possible_path, '.png') !== false ||
                strpos($possible_path, '.gif') !== false ||
                strpos($possible_path, 'googleusercontent.com') !== false ||
                strpos($possible_path, 'apple') !== false) {
                // Bu bir profil fotoğrafı yolu, profil_fotografi'ye taşı
                if (empty($row['profil_fotografi'])) {
                    $pdo->prepare("UPDATE kullanicilar SET profil_fotografi = ?, durum = 'aktif' WHERE id = ?")->execute([$possible_path, $row['id']]);
                    $cleaned_count++;
                } else {
                    // Profil fotoğrafı zaten var, sadece durum'u düzelt
                    $pdo->prepare("UPDATE kullanicilar SET durum = 'aktif' WHERE id = ?")->execute([$row['id']]);
                }
            }
        }
        if ($cleaned_count > 0) {
            $alterations[] = "✅ $cleaned_count kullanıcının durum sütunundaki profil fotoğrafı yolu profil_fotografi'ye taşındı ve durum 'aktif' yapıldı";
        }
    }
    
    // ✅ 5. Mevcut Google kullanıcılarının profil fotoğraflarını kontrol et
    $stmt = $pdo->query("
        SELECT id, google_id, profil_fotografi 
        FROM kullanicilar 
        WHERE google_id IS NOT NULL 
        AND (profil_fotografi IS NULL OR profil_fotografi = '')
    ");
    $google_users_without_photo = $stmt->rowCount();
    if ($google_users_without_photo > 0) {
        $alterations[] = "⚠️ $google_users_without_photo Google kullanıcısının profil fotoğrafı yok (bir sonraki girişte güncellenecek)";
    }
    
    $pdo->commit();
    
    json_ok([
        'success' => true,
        'message' => 'Veritabanı yapısı kontrol edildi ve güncellendi',
        'alterations' => $alterations
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database structure fix error: " . $e->getMessage());
    json_err(500, 'Veritabanı hatası: ' . $e->getMessage());
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Structure fix error: " . $e->getMessage());
    json_err(500, 'Hata: ' . $e->getMessage());
}
?>

