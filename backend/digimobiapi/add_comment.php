<?php
require_once 'bootstrap.php';
require_once __DIR__ . '/cache_invalidation.php';

header('Content-Type: application/json');

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

$user_id = $_SESSION['user_id'];

// JSON input al
$input = json_decode(file_get_contents('php://input'), true);
$media_id = $input['media_id'] ?? null;
$comment_text = $input['comment'] ?? '';

if (!$media_id || empty(trim($comment_text))) {
    json_err(400, 'Media ID and comment are required');
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
        json_err(404, 'Media not found');
    }
    
    // Yorum ekle
    $stmt = $pdo->prepare("
        INSERT INTO yorumlar (medya_id, kullanici_id, yorum_metni, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$media_id, $user_id, trim($comment_text)]);
    $comment_id = $pdo->lastInsertId();
    
    // Yorumu geri döndür
    $stmt = $pdo->prepare("
        SELECT y.*, k.ad, k.soyad, k.profil_fotografi as profil_resmi
        FROM yorumlar y
        JOIN kullanicilar k ON k.id = y.kullanici_id
        WHERE y.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ Bildirim gönder (kendi medyası değilse)
    if ($media['kullanici_id'] != $user_id) {
        require_once 'notification_service.php';
        
        $commenter_name = trim($comment['ad'] . ' ' . $comment['soyad']);
        
        // Medya açıklaması veya "fotoğrafına"
        $media_desc = !empty($media['aciklama']) 
            ? substr($media['aciklama'], 0, 30) . (strlen($media['aciklama']) > 30 ? '...' : '')
            : ($media['tur'] === 'video' ? 'videosuna' : 'fotoğrafına');
        
        // Bildirim title
        $title = $media['event_title'] . ' Etkinliği';
        
        // Bildirim mesajı
        $message = "$commenter_name $media_desc yorum yaptı: " . substr($comment_text, 0, 50);
        
        // Database'e kaydet
        $notif_service = new NotificationService($pdo);
        $notif_service->saveNotification(
            $media['kullanici_id'], // alıcı
            $user_id, // gönderen
            'comment',
            $title,
            $message,
            [
                'media_id' => (string)$media_id,
                'event_id' => (string)$media['event_id'],
                'commenter_id' => (string)$user_id,
                'commenter_name' => $commenter_name,
                'comment_text' => $comment_text,
                'media_type' => $media['tur']
            ]
        );
        
        // FCM push notification gönder
        $notif_service->sendFCMNotification(
            $media['kullanici_id'],
            $title,
            $message,
            [
                'type' => 'comment',
                'media_id' => (string)$media_id,
                'event_id' => (string)$media['event_id'],
                'commenter_id' => (string)$user_id,
                'comment_id' => (string)$comment_id
            ]
        );
        
        // ✅ Cache'i temizle (yorum sayısı değişti)
        clear_media_cache($media['event_id'], null);
        clear_notifications_cache($media['kullanici_id']); // Bildirim eklendi
    }
    
    json_ok([
        'message' => 'Yorum eklendi',
        'comment' => [
            'id' => $comment['id'],
            'user_id' => $comment['kullanici_id'],
            'user_name' => trim($comment['ad'] . ' ' . $comment['soyad']),
            'user_profile_image' => $comment['profil_resmi'] ? 'https://dijitalsalon.cagapps.app/' . $comment['profil_resmi'] : null,
            'content' => $comment['yorum_metni'],
            'comment' => $comment['yorum_metni'], // Backward compatibility
            'created_at' => $comment['created_at']
        ]
    ]);
    
} catch (PDOException $e) {
    $error_msg = "Add Comment Error: " . $e->getMessage();
    error_log($error_msg);
    // Development için detaylı hata mesajı
    if (isset($_ENV['DEBUG']) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
        json_err(500, $error_msg);
    } else {
        json_err(500, 'Database error');
    }
} catch (Exception $e) {
    $error_msg = "Add Comment Error: " . $e->getMessage();
    error_log($error_msg);
    json_err(500, 'Server error');
}
?>

