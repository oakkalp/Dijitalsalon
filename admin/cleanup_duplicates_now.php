<?php
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Duplicate Cleanup</title></head><body>";
echo "<h1>🗑️ Duplicate Participant Cleanup</h1>";
echo "<pre>";

try {
    $pdo = get_pdo();
    
    // Tüm etkinlikler için duplicate'leri bul ve temizle
    $events_stmt = $pdo->query("SELECT DISTINCT dugun_id FROM dugun_katilimcilar");
    $events = $events_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $total_deleted = 0;
    
    foreach ($events as $event_id) {
        echo "\n📋 Event ID: $event_id\n";
        
        // Bu etkinlikteki duplicate'leri bul
        $dup_stmt = $pdo->prepare("
            SELECT kullanici_id, COUNT(*) as count
            FROM dugun_katilimcilar
            WHERE dugun_id = ?
            GROUP BY kullanici_id
            HAVING count > 1
        ");
        $dup_stmt->execute([$event_id]);
        $duplicates = $dup_stmt->fetchAll();
        
        if (empty($duplicates)) {
            echo "  ✅ No duplicates found\n";
            continue;
        }
        
        foreach ($duplicates as $dup) {
            $user_id = $dup['kullanici_id'];
            $count = $dup['count'];
            
            echo "  👤 User ID $user_id: $count kayıt bulundu\n";
            
            // Bu kullanıcının tüm kayıtlarını al
            $records_stmt = $pdo->prepare("
                SELECT id, katilim_tarihi 
                FROM dugun_katilimcilar 
                WHERE dugun_id = ? AND kullanici_id = ?
                ORDER BY id ASC
            ");
            $records_stmt->execute([$event_id, $user_id]);
            $records = $records_stmt->fetchAll();
            
            // İlk kaydı tut, diğerlerini sil
            $keep_id = $records[0]['id'];
            echo "     ⭐ Keeping record ID: $keep_id\n";
            
            for ($i = 1; $i < count($records); $i++) {
                $delete_id = $records[$i]['id'];
                echo "     🗑️  Deleting record ID: $delete_id\n";
                
                $delete_stmt = $pdo->prepare("DELETE FROM dugun_katilimcilar WHERE id = ?");
                $delete_stmt->execute([$delete_id]);
                $total_deleted++;
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ CLEANUP COMPLETE!\n";
    echo "📊 Total deleted: $total_deleted records\n";
    echo str_repeat("=", 50) . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<p><a href='event-participants.php?id=5' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>← Back to Participants</a></p>";
echo "</body></html>";
?>

