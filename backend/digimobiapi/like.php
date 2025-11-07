<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $media_id = $_POST['media_id'] ?? '';
    $action = $_POST['action'] ?? 'like'; // 'like' or 'unlike'
    
    if (empty($media_id)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Media ID is required.']);
        exit;
    }
    
    try {
        // Check if media exists and user has access (with event info)
        $stmt = $pdo->prepare("
            SELECT 
                m.id, 
                m.dugun_id,
                m.tur,
                d.baslik as event_title
            FROM medyalar m
            JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
            JOIN dugunler d ON m.dugun_id = d.id
            WHERE m.id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$media_id, $user_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$media) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Media not found or access denied.']);
            exit;
        }
        
        // Check if user already liked this media
        $stmt = $pdo->prepare("SELECT id FROM begeniler WHERE medya_id = ? AND kullanici_id = ?");
        $stmt->execute([$media_id, $user_id]);
        $existing_like = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($action === 'like') {
            if ($existing_like) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Already liked.']);
                exit;
            }
            
            // Add like
            $stmt = $pdo->prepare("
                INSERT INTO begeniler (medya_id, kullanici_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$media_id, $user_id]);
            
            // Get updated likes count
            $stmt = $pdo->prepare("SELECT COUNT(*) as likes_count FROM begeniler WHERE medya_id = ?");
            $stmt->execute([$media_id]);
            $likes_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $likes_count = $likes_result['likes_count'] ?? 0;
            
            // Log activity to user_logs with detailed info
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $device_info = $_POST['device_info'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // ✅ Story beğenisi için ayrı action kullan
            $log_action = ($media['tur'] === 'hikaye') ? 'story_like' : 'like';
            
            $log_details = json_encode([
                'media_id' => (int)$media_id,
                'story_id' => ($media['tur'] === 'hikaye') ? (int)$media_id : null,
                'event_id' => (int)$media['dugun_id'],
                'event_title' => $media['event_title'],
                'media_type' => $media['tur'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO user_logs (
                    user_id, action, details, ip_address, device_info, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $log_action, $log_details, $ip_address, $device_info, $user_agent]);
            
            // ✅ BİLDİRİM GÖNDER (medya/story sahibine)
            try {
                // Medya/story sahibini bul
                $stmt = $pdo->prepare("SELECT kullanici_id, aciklama FROM medyalar WHERE id = ?");
                $stmt->execute([$media_id]);
                $media_owner = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Debug log
                error_log("Like.php Debug - Media ID: $media_id, User ID: $user_id, Media Owner: " . ($media_owner ? $media_owner['kullanici_id'] : 'NOT FOUND'));
                
                // Kendi medyasını beğenen kullanıcıya bildirim gönderme
                if ($media_owner && $media_owner['kullanici_id'] != $user_id) {
                    $owner_id = $media_owner['kullanici_id'];
                    
                    error_log("Like.php Debug - Sending notification to owner: $owner_id");
                    
                    // Beğenen kullanıcının adını al
                    $stmt = $pdo->prepare("SELECT CONCAT(ad, ' ', soyad) as full_name FROM kullanicilar WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $liker = $stmt->fetch(PDO::FETCH_ASSOC);
                    $liker_name = $liker['full_name'] ?? 'Bir kullanıcı';
                    
                    // Bildirim mesajı oluştur
                    $media_type_text = ($media['tur'] === 'hikaye') ? 'hikayenizi' : 'medyanızı';
                    $notification_message = "$liker_name, {$media['event_title']} etkinliğinde $media_type_text beğendi.";
                    
                    // ✅ Bildirim data JSON oluştur (sender_name ekle - silinmiş kullanıcılar için)
                    $notification_data = json_encode([
                        'media_id' => (string)$media_id,
                        'event_id' => (string)$media['dugun_id'],
                        'media_type' => $media['tur'],
                        'sender_name' => $liker_name, // ✅ Kullanıcı adını data'da sakla (silinmiş kullanıcılar için)
                        'sender_id' => (string)$user_id
                    ]);
                    
                    // Bildirimi database'e kaydet
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, sender_id, event_id, type, title, message, data, created_at)
                        VALUES (?, ?, ?, 'like', ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $owner_id,
                        $user_id,
                        $media['dugun_id'],
                        $media['event_title'] . ' Etkinliği',
                        $notification_message,
                        $notification_data
                    ]);
                    
                    $notification_id = $pdo->lastInsertId();
                    error_log("Like.php Debug - Notification saved with ID: $notification_id");
                    
                    // FCM ile push notification gönder (arka planda)
                    try {
                        require_once 'notification_service.php';
                        sendNotification(
                            [$owner_id], 
                            'Yeni Beğeni!', 
                            $notification_message, 
                            [
                                'type' => 'like', 
                                'media_id' => (string)$media_id,
                                'event_id' => (string)$media['dugun_id']
                            ]
                        );
                        error_log("Like.php Debug - FCM notification sent to owner: $owner_id");
                    } catch (Exception $fcm_error) {
                        // FCM hatası uygulamayı durdurmamalı
                        error_log("FCM error in like.php: " . $fcm_error->getMessage());
                    }
                } else {
                    if (!$media_owner) {
                        error_log("Like.php Debug - Media owner not found for media_id: $media_id");
                    } else {
                        error_log("Like.php Debug - User is liking their own media (user_id: $user_id, owner_id: {$media_owner['kullanici_id']})");
                    }
                }
            } catch (Exception $notif_error) {
                // Bildirim hatası uygulamayı durdurmamalı
                error_log("Notification error in like.php: " . $notif_error->getMessage() . " | Trace: " . $notif_error->getTraceAsString());
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Media liked successfully.',
                'likes_count' => (int)$likes_count,
                'is_liked' => true
            ]);
            exit;
            
        } elseif ($action === 'unlike') {
            if (!$existing_like) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Not liked yet.']);
                exit;
            }
            
            // Remove like
            $stmt = $pdo->prepare("DELETE FROM begeniler WHERE medya_id = ? AND kullanici_id = ?");
            $stmt->execute([$media_id, $user_id]);
            
            // Get updated likes count
            $stmt = $pdo->prepare("SELECT COUNT(*) as likes_count FROM begeniler WHERE medya_id = ?");
            $stmt->execute([$media_id]);
            $likes_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $likes_count = $likes_result['likes_count'] ?? 0;
            
            // Log activity to user_logs with detailed info
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $device_info = $_POST['device_info'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // ✅ Story beğenisi için ayrı action kullan
            $log_action = ($media['tur'] === 'hikaye') ? 'story_unlike' : 'unlike';
            
            $log_details = json_encode([
                'media_id' => (int)$media_id,
                'story_id' => ($media['tur'] === 'hikaye') ? (int)$media_id : null,
                'event_id' => (int)$media['dugun_id'],
                'event_title' => $media['event_title'],
                'media_type' => $media['tur'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO user_logs (
                    user_id, action, details, ip_address, device_info, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $log_action, $log_details, $ip_address, $device_info, $user_agent]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Media unliked successfully.',
                'likes_count' => (int)$likes_count,
                'is_liked' => false
            ]);
            exit;
        } else {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use "like" or "unlike".']);
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Like API Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
    
} else {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
?>
