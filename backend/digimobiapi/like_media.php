<?php
require_once 'bootstrap.php';
require_once __DIR__ . '/cache_invalidation.php';

header('Content-Type: application/json');

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    json_err('Unauthorized', 401);
}

$user_id = $_SESSION['user_id'];

// JSON input al
$input = json_decode(file_get_contents('php://input'), true);
$media_id = $input['media_id'] ?? null;
$action = $input['action'] ?? 'like'; // 'like' or 'unlike'

if (!$media_id) {
    json_err('Media ID is required');
}

try {
    $pdo = get_pdo();
    
    // Medya var mı kontrol et
    $stmt = $pdo->prepare("
        SELECT m.*, d.baslik as event_title, d.id as event_id
        FROM medyalar m
        JOIN dugunler d ON d.id = m.dugun_id
        WHERE m.id = ?
    ");
    $stmt->execute([$media_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$media) {
        json_err('Media not found', 404);
    }
    
    if ($action === 'like') {
        // Beğen
        try {
            $stmt = $pdo->prepare("
                INSERT INTO begeniler (medya_id, kullanici_id, olusturma_tarihi)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$media_id, $user_id]);
            
            // ✅ Bildirim gönder (kendi medyası değilse)
            if ($media['kullanici_id'] != $user_id) {
                require_once 'notification_service.php';
                
                // Kullanıcı adını al
                $stmt = $pdo->prepare("SELECT ad, soyad FROM kullanicilar WHERE id = ?");
                $stmt->execute([$user_id]);
                $liker = $stmt->fetch(PDO::FETCH_ASSOC);
                $liker_name = trim($liker['ad'] . ' ' . $liker['soyad']);
                
                // Medya açıklaması veya "fotoğrafını"
                $media_desc = !empty($media['aciklama']) 
                    ? substr($media['aciklama'], 0, 30) . (strlen($media['aciklama']) > 30 ? '...' : '')
                    : ($media['tur'] === 'video' ? 'videosunu' : 'fotoğrafını');
                
                // Bildirim title
                $title = $media['event_title'] . ' Etkinliği';
                
                // Bildirim mesajı
                $message = "$liker_name $media_desc beğendi";
                
                // Database'e kaydet
                $notif_service = new NotificationService($pdo);
                $notif_service->saveNotification(
                    $media['kullanici_id'], // alıcı
                    $user_id, // gönderen
                    'like',
                    $title,
                    $message,
                    [
                        'media_id' => (string)$media_id,
                        'event_id' => (string)$media['event_id'],
                        'liker_id' => (string)$user_id,
                        'liker_name' => $liker_name,
                        'media_type' => $media['tur']
                    ]
                );
                
                // FCM push notification gönder
                $notif_service->sendFCMNotification(
                    $media['kullanici_id'],
                    $title,
                    $message,
                    [
                        'type' => 'like',
                        'media_id' => (string)$media_id,
                        'event_id' => (string)$media['event_id'],
                        'liker_id' => (string)$user_id
                    ]
                );
            }
            
            // ✅ Cache'i temizle (beğeni sayısı değişti)
            clear_media_cache($media['event_id'], null);
            clear_notifications_cache($media['kullanici_id']); // Bildirim eklendi
            
            json_ok(['message' => 'Beğenildi', 'action' => 'liked']);
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                json_err('Zaten beğenilmiş');
            }
            throw $e;
        }
        
    } else if ($action === 'unlike') {
        // Beğeniyi kaldır
        $stmt = $pdo->prepare("
            DELETE FROM begeniler 
            WHERE medya_id = ? AND kullanici_id = ?
        ");
        $stmt->execute([$media_id, $user_id]);
        
        // ✅ Cache'i temizle (beğeni sayısı değişti)
        clear_media_cache($media['event_id'], null);
        
        json_ok(['message' => 'Beğeni kaldırıldı', 'action' => 'unliked']);
    }
    
} catch (PDOException $e) {
    error_log("Like Media Error: " . $e->getMessage());
    json_err('Database error');
}
?>
