<?php
require_once 'config.php';

echo "📊 NOTIFICATIONS TABLE CHECK\n";
echo "============================\n\n";

try {
    // Son 10 bildirim
    $stmt = $pdo->query("
        SELECT 
            n.id,
            n.user_id,
            n.sender_id,
            n.type,
            n.title,
            n.message,
            n.is_read,
            n.created_at,
            k1.ad as receiver_name,
            k2.ad as sender_name
        FROM notifications n
        LEFT JOIN kullanicilar k1 ON k1.id = n.user_id
        LEFT JOIN kullanicilar k2 ON k2.id = n.sender_id
        ORDER BY n.created_at DESC 
        LIMIT 10
    ");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo "❌ Hiç bildirim bulunamadı!\n";
        echo "Beğeni yapıldığında bildirim kaydedilmemiş olabilir.\n";
    } else {
        echo "✅ " . count($notifications) . " bildirim bulundu:\n\n";
        foreach ($notifications as $notif) {
            echo "ID: {$notif['id']}\n";
            echo "Alıcı: {$notif['receiver_name']} (ID: {$notif['user_id']})\n";
            echo "Gönderen: {$notif['sender_name']} (ID: {$notif['sender_id']})\n";
            echo "Tip: {$notif['type']}\n";
            echo "Başlık: {$notif['title']}\n";
            echo "Mesaj: {$notif['message']}\n";
            echo "Okundu: " . ($notif['is_read'] ? 'Evet' : 'Hayır') . "\n";
            echo "Tarih: {$notif['created_at']}\n";
            echo "---\n";
        }
    }
    
    // User ID'leri kontrol et
    echo "\n👥 KULLANICILAR:\n";
    $stmt = $pdo->query("SELECT id, ad, soyad, email FROM kullanicilar ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "ID {$user['id']}: {$user['ad']} {$user['soyad']} ({$user['email']})\n";
    }
    
    // Test 3 etkinliğindeki medya sahiplerini kontrol et
    echo "\n📸 TEST 3 ETKİNLİĞİ MEDYA SAHİPLERİ:\n";
    $stmt = $pdo->query("
        SELECT 
            m.id as media_id,
            m.kullanici_id,
            k.ad,
            k.soyad,
            m.dosya_adi
        FROM medyalar m
        JOIN kullanicilar k ON k.id = m.kullanici_id
        JOIN dugunler d ON d.id = m.dugun_id
        WHERE d.baslik LIKE '%test 3%'
        ORDER BY m.id
    ");
    $medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($medias)) {
        echo "❌ Test 3 etkinliğinde medya bulunamadı!\n";
    } else {
        foreach ($medias as $media) {
            echo "Medya ID {$media['media_id']}: Sahibi {$media['ad']} {$media['soyad']} (ID: {$media['kullanici_id']}) - {$media['dosya_adi']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>

