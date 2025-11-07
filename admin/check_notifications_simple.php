<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap kullan (config.php yerine)
require_once __DIR__ . '/../digimobiapi/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "📊 NOTIFICATIONS TABLE CHECK\n";
echo "============================\n\n";

try {
    $pdo = get_pdo();
    
    // Son 10 bildirim
    $stmt = $pdo->query("
        SELECT 
            n.id,
            n.user_id,
            n.sender_id,
            n.type,
            n.title,
            LEFT(n.message, 50) as message,
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
        echo "Beğeni yapıldığında bildirim kaydedilmemiş olabilir.\n\n";
    } else {
        echo "✅ " . count($notifications) . " bildirim bulundu:\n\n";
        foreach ($notifications as $notif) {
            echo "ID: {$notif['id']} | Alıcı: {$notif['receiver_name']} (#{$notif['user_id']}) | ";
            echo "Gönderen: {$notif['sender_name']} (#{$notif['sender_id']}) | ";
            echo "Tip: {$notif['type']} | Tarih: {$notif['created_at']}\n";
        }
    }
    
    // User ID'leri
    echo "\n👥 KULLANICILAR:\n";
    $stmt = $pdo->query("SELECT id, ad, soyad, email FROM kullanicilar ORDER BY id LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "ID {$user['id']}: {$user['ad']} {$user['soyad']} ({$user['email']})\n";
    }
    
    // Test 3 etkinliğindeki medya sahipleri
    echo "\n📸 'test 3' ETKİNLİĞİNDEKİ MEDYALAR:\n";
    $stmt = $pdo->query("
        SELECT 
            m.id as media_id,
            m.kullanici_id,
            k.ad,
            k.soyad,
            m.tur
        FROM medyalar m
        JOIN kullanicilar k ON k.id = m.kullanici_id
        JOIN dugunler d ON d.id = m.dugun_id
        WHERE d.baslik LIKE '%test 3%'
        ORDER BY m.id
        LIMIT 10
    ");
    $medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($medias)) {
        echo "❌ 'test 3' etkinliğinde medya bulunamadı!\n";
    } else {
        foreach ($medias as $media) {
            echo "Medya #{$media['media_id']}: Sahibi {$media['ad']} {$media['soyad']} (ID: {$media['kullanici_id']}) - {$media['tur']}\n";
        }
    }
    
    // Son beğeniler
    echo "\n❤️ SON BEĞENİLER:\n";
    $stmt = $pdo->query("
        SELECT 
            b.id,
            b.medya_id,
            b.kullanici_id as liker_id,
            k.ad as liker_name,
            m.kullanici_id as owner_id,
            k2.ad as owner_name,
            b.created_at
        FROM begeniler b
        JOIN kullanicilar k ON k.id = b.kullanici_id
        JOIN medyalar m ON m.id = b.medya_id
        JOIN kullanicilar k2 ON k2.id = m.kullanici_id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $likes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($likes)) {
        echo "❌ Hiç beğeni bulunamadı!\n";
    } else {
        foreach ($likes as $like) {
            echo "Medya #{$like['medya_id']}: {$like['liker_name']} (#{$like['liker_id']}) -> ";
            echo "{$like['owner_name']} (#{$like['owner_id']}) | {$like['created_at']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
?>

