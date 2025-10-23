<?php
/**
 * Get Replies AJAX Handler
 * Digital Salon - Yorum yanıtlarını getirme işlemi
 */

session_start();

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$comment_id = (int)($_GET['comment_id'] ?? 0);

if (!$comment_id) {
    echo json_encode(['success' => false, 'message' => 'Comment ID is required']);
    exit;
}

try {
    // Get replies
    $stmt = $pdo->prepare("
        SELECT 
            y.*,
            k.ad as user_name,
            k.soyad as user_surname,
            k.profil_fotografi as user_profile_photo
        FROM yorumlar y
        JOIN kullanicilar k ON y.kullanici_id = k.id
        WHERE y.parent_comment_id = ? AND y.durum = 'aktif'
        ORDER BY y.created_at ASC
    ");
    $stmt->execute([$comment_id]);
    $replies = $stmt->fetchAll();

    $formatted_replies = [];
    foreach ($replies as $reply) {
        $formatted_replies[] = [
            'id' => $reply['id'],
            'content' => htmlspecialchars($reply['yorum_metni']),
            'user_name' => htmlspecialchars($reply['user_name'] . ' ' . $reply['user_surname']),
            'user_profile_photo' => htmlspecialchars($reply['user_profile_photo'] ?: 'assets/images/default_profile.svg'),
            'created_at' => date('d.m.Y H:i', strtotime($reply['created_at']))
        ];
    }

    echo json_encode(['success' => true, 'replies' => $formatted_replies]);

} catch (PDOException $e) {
    error_log("Get replies error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
