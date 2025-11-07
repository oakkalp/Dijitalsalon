<?php
/**
 * ✅ Güvenli Index Oluşturma Script'i
 * Mevcut index'leri kontrol eder ve sadece olmayan index'leri oluşturur
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = get_pdo();
    
    // Index'ler ve açıklamaları
    $indexes = [
        // Events
        ['table' => 'dugun_katilimcilar', 'name' => 'idx_dugun_katilimcilar_user_status', 'sql' => 'CREATE INDEX idx_dugun_katilimcilar_user_status ON dugun_katilimcilar(kullanici_id, durum)'],
        ['table' => 'dugun_katilimcilar', 'name' => 'idx_dugun_katilimcilar_dugun_id', 'sql' => 'CREATE INDEX idx_dugun_katilimcilar_dugun_id ON dugun_katilimcilar(dugun_id)'],
        ['table' => 'dugunler', 'name' => 'idx_dugunler_tarih', 'sql' => 'CREATE INDEX idx_dugunler_tarih ON dugunler(dugun_tarihi DESC)'],
        ['table' => 'dugunler', 'name' => 'idx_dugunler_moderator', 'sql' => 'CREATE INDEX idx_dugunler_moderator ON dugunler(moderator_id)'],
        
        // Notifications
        ['table' => 'notifications', 'name' => 'idx_notifications_user_created', 'sql' => 'CREATE INDEX idx_notifications_user_created ON notifications(user_id, created_at DESC)'],
        ['table' => 'notifications', 'name' => 'idx_notifications_sender', 'sql' => 'CREATE INDEX idx_notifications_sender ON notifications(sender_id)'],
        ['table' => 'notifications', 'name' => 'idx_notifications_type', 'sql' => 'CREATE INDEX idx_notifications_type ON notifications(type)'],
        ['table' => 'notifications', 'name' => 'idx_notifications_event', 'sql' => 'CREATE INDEX idx_notifications_event ON notifications(event_id)'],
        ['table' => 'notifications', 'name' => 'idx_notifications_read', 'sql' => 'CREATE INDEX idx_notifications_read ON notifications(is_read)'],
        
        // Media
        ['table' => 'medyalar', 'name' => 'idx_medyalar_dugun_tur', 'sql' => 'CREATE INDEX idx_medyalar_dugun_tur ON medyalar(dugun_id, tur)'],
        ['table' => 'medyalar', 'name' => 'idx_medyalar_created', 'sql' => 'CREATE INDEX idx_medyalar_created ON medyalar(created_at DESC)'],
        ['table' => 'medyalar', 'name' => 'idx_medyalar_user', 'sql' => 'CREATE INDEX idx_medyalar_user ON medyalar(kullanici_id)'],
        ['table' => 'begeniler', 'name' => 'idx_begeniler_medya', 'sql' => 'CREATE INDEX idx_begeniler_medya ON begeniler(medya_id)'],
        ['table' => 'begeniler', 'name' => 'idx_begeniler_user_medya', 'sql' => 'CREATE INDEX idx_begeniler_user_medya ON begeniler(kullanici_id, medya_id)'],
        ['table' => 'yorumlar', 'name' => 'idx_yorumlar_medya', 'sql' => 'CREATE INDEX idx_yorumlar_medya ON yorumlar(medya_id)'],
        
        // Comments
        ['table' => 'yorumlar', 'name' => 'idx_yorumlar_created', 'sql' => 'CREATE INDEX idx_yorumlar_created ON yorumlar(created_at DESC)'],
        ['table' => 'yorumlar', 'name' => 'idx_yorumlar_user', 'sql' => 'CREATE INDEX idx_yorumlar_user ON yorumlar(kullanici_id)'],
        
        // Stories
        ['table' => 'medyalar', 'name' => 'idx_medyalar_story_created', 'sql' => 'CREATE INDEX idx_medyalar_story_created ON medyalar(dugun_id, tur, created_at DESC)'],
        
        // Profile stats
        ['table' => 'medyalar', 'name' => 'idx_medyalar_user_created', 'sql' => 'CREATE INDEX idx_medyalar_user_created ON medyalar(kullanici_id, created_at DESC)'],
        
        // Composite
        ['table' => 'dugun_katilimcilar', 'name' => 'idx_dugun_katilimcilar_composite', 'sql' => 'CREATE INDEX idx_dugun_katilimcilar_composite ON dugun_katilimcilar(kullanici_id, durum, dugun_id)'],
        ['table' => 'notifications', 'name' => 'idx_notifications_composite', 'sql' => 'CREATE INDEX idx_notifications_composite ON notifications(user_id, type, created_at DESC)'],
    ];
    
    $created = 0;
    $skipped = 0;
    $errors = [];
    
    echo "🔍 Index kontrolü başlatılıyor...\n\n";
    
    foreach ($indexes as $index) {
        // Mevcut index'i kontrol et
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = ? 
            AND index_name = ?
        ");
        $stmt->execute([$index['table'], $index['name']]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC); // ✅ fetchAll kullan (buffering için)
        $exists = $result[0]['count'] > 0;
        
        if ($exists) {
            echo "⏭️  [SKIP] {$index['name']} - Zaten mevcut\n";
            $skipped++;
        } else {
            try {
                $pdo->exec($index['sql']);
                echo "✅ [OK] {$index['name']} - Oluşturuldu\n";
                $created++;
            } catch (PDOException $e) {
                echo "❌ [ERROR] {$index['name']} - {$e->getMessage()}\n";
                $errors[] = $index['name'] . ': ' . $e->getMessage();
            }
        }
    }
    
    echo "\n📊 Özet:\n";
    echo "   ✅ Oluşturulan: $created\n";
    echo "   ⏭️  Atlanan: $skipped\n";
    echo "   ❌ Hatalar: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\n❌ Hatalar:\n";
        foreach ($errors as $error) {
            echo "   - $error\n";
        }
    }
    
    // Tablo istatistiklerini güncelle
    echo "\n📈 Tablo istatistikleri güncelleniyor...\n";
    $tables = ['dugun_katilimcilar', 'dugunler', 'notifications', 'medyalar', 'begeniler', 'yorumlar'];
    foreach ($tables as $table) {
        try {
            // ✅ fetchAll kullanarak query'yi tamamen bitir
            $stmt = $pdo->query("ANALYZE TABLE $table");
            $stmt->fetchAll(PDO::FETCH_ASSOC); // ✅ Result set'i tamamen oku
            $stmt->closeCursor(); // ✅ Cursor'ı kapat
            echo "✅ $table - İstatistikler güncellendi\n";
        } catch (PDOException $e) {
            echo "❌ $table - Hata: {$e->getMessage()}\n";
        }
    }
    
    echo "\n✅ İşlem tamamlandı!\n";
    
} catch (Exception $e) {
    echo "❌ Genel Hata: " . $e->getMessage() . "\n";
}

