<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get comments for specific media
    $media_id = $_GET['media_id'] ?? '';
    
    if (empty($media_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Media ID is required.']);
        exit;
    }
    
    try {
        // Debug log
        error_log("Comments API - Media ID: $media_id, User ID: $user_id");
        
        // Check if media exists and user has access
        $stmt = $pdo->prepare("
            SELECT m.id, m.dugun_id
            FROM medyalar m
            JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
            WHERE m.id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$media_id, $user_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$media) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Media not found or access denied.']);
            exit;
        }
        
        // Get comments
        $stmt = $pdo->prepare("
            SELECT 
                my.*,
                my.kullanici_id as user_id,
                k.ad as user_name,
                k.soyad as user_surname,
                k.profil_fotografi as user_avatar,
                (SELECT COUNT(*) FROM yorum_begeniler myb WHERE myb.yorum_id = my.id) as likes_count,
                (SELECT COUNT(*) FROM yorum_begeniler myb2 WHERE myb2.yorum_id = my.id AND myb2.kullanici_id = ?) as is_liked
            FROM yorumlar my
            JOIN kullanicilar k ON my.kullanici_id = k.id
            WHERE my.medya_id = ? AND my.parent_comment_id IS NULL
            ORDER BY my.id ASC
        ");
        $stmt->execute([$user_id, $media_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format comments for mobile app
        $formatted_comments = [];
        foreach ($comments as $comment) {
            $formatted_comments[] = [
                'id' => (int)$comment['id'],
                'user_id' => (int)$comment['user_id'],
                'content' => $comment['yorum_metni'],
                'user_name' => $comment['user_name'] . ' ' . $comment['user_surname'],
                'user_avatar' => $comment['user_avatar'] ? 'http://192.168.1.137/dijitalsalon/' . $comment['user_avatar'] : null,
                'likes' => (int)$comment['likes_count'],
                'is_liked' => (bool)$comment['is_liked'],
                'created_at' => $comment['created_at'],
            ];
        }
        
        echo json_encode([
            'success' => true,
            'comments' => $formatted_comments
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new comment
    $media_id = $_POST['media_id'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (empty($media_id) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Media ID and content are required.']);
        exit;
    }
    
    try {
        // Check if media exists and user has access with package info
        $stmt = $pdo->prepare("
            SELECT 
                m.id, 
                m.dugun_id, 
                dk.yetkiler,
                dk.rol,
                d.dugun_tarihi,
                p.ucretsiz_erisim_gun
            FROM medyalar m
            JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
            JOIN dugunler d ON m.dugun_id = d.id
            LEFT JOIN paketler p ON d.paket_id = p.id
            WHERE m.id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$media_id, $user_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$media) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Media not found or access denied.']);
            exit;
        }

        // ✅ Yetki kontrolü için tarih hesaplamaları
        $event_date = new DateTime($media['dugun_tarihi']);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $event_date->setTime(0, 0, 0);
        
        $free_access_days = (int)($media['ucretsiz_erisim_gun'] ?? 7);
        $access_end_date = clone $event_date;
        $access_end_date->add(new DateInterval("P{$free_access_days}D"));
        
        $is_before_event = $today < $event_date;
        $is_after_access = $today > $access_end_date;
        
        // ✅ Rol kontrolü
        $user_role = $media['rol'] ?: 'kullanici';
        $is_moderator = $user_role === 'moderator';
        $is_authorized = $user_role === 'yetkili_kullanici';
        
        // ✅ Yetki kontrolü
        if ($is_moderator) {
            // Moderator: Her zaman yorum yapabilir
            $can_comment = true;
        } elseif ($is_authorized) {
            // Yetkili kullanıcı: JSON yetkiler + tarih kontrolü
            $permissions = $media['yetkiler'] ? json_decode($media['yetkiler'], true) : [];
            $can_comment = ($permissions['yorum_yapabilir'] ?? false) && !$is_before_event && !$is_after_access;
        } else {
            // Normal kullanıcı: Sadece aktif dönemde
            $can_comment = !$is_before_event && !$is_after_access;
        }
        
        if (!$can_comment) {
            $error_message = 'You do not have permission to comment in this event.';
            if ($is_before_event) {
                $error_message = 'Event has not started yet. You cannot comment before the event date.';
            } elseif ($is_after_access) {
                $error_message = "Free access period has ended. You cannot comment after {$free_access_days} days from the event date.";
            }
            
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => $error_message]);
            exit;
        }
        
        // Add comment
        $stmt = $pdo->prepare("
            INSERT INTO yorumlar (medya_id, kullanici_id, yorum_metni, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$media_id, $user_id, $content]);
        
        $comment_id = $pdo->lastInsertId();
        
        // Get user info for response
        $stmt = $pdo->prepare("
            SELECT ad, soyad, profil_fotografi 
            FROM kullanicilar 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully.',
            'comment' => [
                'id' => (int)$comment_id,
                'content' => $content,
                'user_name' => $user['ad'] . ' ' . $user['soyad'],
                'user_avatar' => $user['profil_fotografi'] ? 'http://192.168.1.137/dijitalsalon/' . $user['profil_fotografi'] : null,
                'likes' => 0,
                'is_liked' => false,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
?>
