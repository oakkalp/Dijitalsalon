<?php
/**
 * Kendi medyasını beğenen/yorum yapan kullanıcıların bildirimlerini temizle
 * 
 * Bu script, kullanıcıların kendi medyalarını beğendiğinde veya yorum yaptığında
 * oluşturulmuş bildirimleri veritabanından siler.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $pdo = get_pdo();
    
    // ✅ Kendi medyasını beğenen kullanıcıların bildirimlerini bul
    // sender_id == user_id olan bildirimler
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.user_id,
            n.sender_id,
            n.type,
            n.created_at,
            m.kullanici_id as media_owner_id,
            m.id as media_id
        FROM notifications n
        LEFT JOIN notifications temp_n ON temp_n.id = n.id
        LEFT JOIN (
            SELECT 
                id,
                CAST(JSON_EXTRACT(data, '$.media_id') AS UNSIGNED) as media_id_extracted
            FROM notifications
            WHERE data IS NOT NULL AND JSON_EXTRACT(data, '$.media_id') IS NOT NULL
        ) n_data ON n_data.id = n.id
        LEFT JOIN medyalar m ON m.id = CAST(COALESCE(
            JSON_EXTRACT(n.data, '$.media_id'),
            n_data.media_id_extracted
        ) AS UNSIGNED)
        WHERE n.type IN ('like', 'comment')
        AND n.sender_id IS NOT NULL
        AND n.sender_id = n.user_id
    ");
    $stmt->execute();
    $self_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "🔍 Bulunan kendi beğeni/yorum bildirimleri: " . count($self_notifications) . "\n\n";
    
    if (count($self_notifications) > 0) {
        // ✅ data JSON'dan sender_id kontrol et
        $to_delete = [];
        foreach ($self_notifications as $notif) {
            $dataStr = $notif['data'] ?? '{}';
            $data = json_decode($dataStr, true) ?? [];
            $data_sender_id = $data['sender_id'] ?? null;
            
            // ✅ sender_id kolonu veya data JSON'daki sender_id user_id ile eşleşiyorsa sil
            if ($notif['sender_id'] == $notif['user_id'] || 
                ($data_sender_id && (int)$data_sender_id == (int)$notif['user_id'])) {
                $to_delete[] = $notif['id'];
            }
        }
        
        echo "🗑️  Silinecek bildirim sayısı: " . count($to_delete) . "\n\n";
        
        if (count($to_delete) > 0) {
            // ✅ Bildirimleri sil
            $placeholders = implode(',', array_fill(0, count($to_delete), '?'));
            $delete_stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders)");
            $delete_stmt->execute($to_delete);
            
            echo "✅ " . count($to_delete) . " bildirim başarıyla silindi.\n";
            echo "\n📋 Silinen bildirim ID'leri:\n";
            foreach ($to_delete as $id) {
                echo "  - ID: $id\n";
            }
        } else {
            echo "ℹ️  Silinecek bildirim bulunamadı.\n";
        }
    } else {
        echo "✅ Kendi beğeni/yorum bildirimi bulunamadı. Temiz!\n";
    }
    
    // ✅ Ayrıca data JSON'dan sender_id kontrol et (eski bildirimler için)
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.user_id,
            n.sender_id,
            n.type,
            n.data
        FROM notifications n
        WHERE n.type IN ('like', 'comment')
        AND n.data IS NOT NULL
        AND JSON_EXTRACT(n.data, '$.sender_id') IS NOT NULL
    ");
    $stmt->execute();
    $data_based_notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data_to_delete = [];
    foreach ($data_based_notifs as $notif) {
        $data = json_decode($notif['data'], true) ?? [];
        $data_sender_id = $data['sender_id'] ?? null;
        
        if ($data_sender_id && (int)$data_sender_id == (int)$notif['user_id']) {
            // ✅ Medya sahibini kontrol et (güvenlik için)
            $media_id = $data['media_id'] ?? null;
            if ($media_id) {
                $media_stmt = $pdo->prepare("SELECT kullanici_id FROM medyalar WHERE id = ?");
                $media_stmt->execute([$media_id]);
                $media = $media_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($media && (int)$media['kullanici_id'] == (int)$notif['user_id']) {
                    $data_to_delete[] = $notif['id'];
                }
            }
        }
    }
    
    if (count($data_to_delete) > 0) {
        echo "\n📋 Data JSON'dan bulunan kendi bildirimler: " . count($data_to_delete) . "\n";
        $placeholders = implode(',', array_fill(0, count($data_to_delete), '?'));
        $delete_stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders)");
        $delete_stmt->execute($data_to_delete);
        echo "✅ " . count($data_to_delete) . " ek bildirim data JSON kontrolünden silindi.\n";
    }
    
    echo "\n✅ Temizleme işlemi tamamlandı!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

