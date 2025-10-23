<?php
/**
 * Get Comments AJAX Handler
 * Digital Salon - Yorumları getirme işlemi
 */

session_start();

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    error_log("Session user_id not found. Session data: " . print_r($_SESSION, true));
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor']);
    exit;
}

$media_id = (int)($_GET['media_id'] ?? 0);

if (!$media_id) {
    error_log("Invalid media_id: " . ($_GET['media_id'] ?? 'null'));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz medya ID']);
    exit;
}

error_log("Getting comments for media_id: $media_id, user_id: " . $_SESSION['user_id']);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'kullanici';

try {
    // Yorumları al
    $stmt = $pdo->prepare("
        SELECT 
            y.*,
            k.ad as user_name,
            k.soyad as user_surname,
            k.profil_fotografi as user_profile_photo,
            (SELECT COUNT(*) FROM yorum_begeniler WHERE yorum_id = y.id) as like_count,
            (SELECT COUNT(*) FROM yorum_begeniler WHERE yorum_id = y.id AND kullanici_id = ?) as user_liked
        FROM yorumlar y
        JOIN kullanicilar k ON y.kullanici_id = k.id
        WHERE y.medya_id = ? AND y.durum = 'aktif' AND y.parent_comment_id IS NULL
        ORDER BY y.created_at ASC
    ");
    $stmt->execute([$user_id, $media_id]);
    $comments = $stmt->fetchAll();

    // Yorumları formatla
    $formatted_comments = [];
    foreach ($comments as $comment) {
        $can_edit = false;
        
        // Super Admin her şeyi yapabilir
        if ($user_role === 'super_admin') {
            $can_edit = true;
        }
        // Kullanıcı kendi yorumunu düzenleyebilir
        elseif ($comment['kullanici_id'] == $user_id) {
            $can_edit = true;
        }
        // Moderator kendi düğünlerindeki yorumları yönetebilir
        elseif ($user_role === 'moderator') {
            // Medya sahibinin moderator olup olmadığını kontrol et
            $media_stmt = $pdo->prepare("
                SELECT d.moderator_id 
                FROM medyalar m 
                JOIN dugunler d ON m.dugun_id = d.id 
                WHERE m.id = ?
            ");
            $media_stmt->execute([$media_id]);
            $media_data = $media_stmt->fetch();
            
            if ($media_data && $media_data['moderator_id'] == $user_id) {
                $can_edit = true;
            }
        }
        // Yetkili kullanıcılar sadece kendi yorumlarını düzenleyebilir (yukarıda kontrol edildi)

        $formatted_comments[] = [
            'id' => $comment['id'],
            'content' => htmlspecialchars($comment['yorum_metni']),
            'user_name' => htmlspecialchars($comment['user_name'] . ' ' . $comment['user_surname']),
            'user_profile_photo' => htmlspecialchars($comment['user_profile_photo'] ?: 'assets/images/default_profile.svg'),
            'created_at' => date('d.m.Y H:i', strtotime($comment['created_at'])),
            'can_edit' => $can_edit,
            'like_count' => (int)$comment['like_count'],
            'user_liked' => (bool)$comment['user_liked']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $formatted_comments
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
