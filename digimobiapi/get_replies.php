<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $comment_id = $_GET['comment_id'] ?? null;

    if (empty($comment_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Comment ID is required.']);
        exit;
    }

    try {
        // Check if comment exists and user has access
        $stmt = $pdo->prepare("
            SELECT y.id, y.medya_id, m.dugun_id
            FROM yorumlar y
            JOIN medyalar m ON y.medya_id = m.id
            JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
            WHERE y.id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$comment_id, $user_id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Comment not found or access denied.']);
            exit;
        }
        
        // Get replies
        $stmt = $pdo->prepare("
            SELECT 
                y.*,
                y.kullanici_id as user_id,
                k.ad as user_name,
                k.soyad as user_surname,
                k.profil_fotografi as user_avatar,
                (SELECT COUNT(*) FROM yorum_begeniler yb WHERE yb.yorum_id = y.id) as likes_count,
                (SELECT COUNT(*) FROM yorum_begeniler yb2 WHERE yb2.yorum_id = y.id AND yb2.kullanici_id = ?) as is_liked
            FROM yorumlar y
            JOIN kullanicilar k ON y.kullanici_id = k.id
            WHERE y.parent_comment_id = ?
            ORDER BY y.created_at ASC
        ");
        $stmt->execute([$user_id, $comment_id]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format replies for mobile app
        $formatted_replies = [];
        foreach ($replies as $reply) {
            $formatted_replies[] = [
                'id' => (int)$reply['id'],
                'user_id' => (int)$reply['user_id'],
                'parent_comment_id' => (int)$reply['parent_comment_id'],
                'content' => $reply['yorum_metni'],
                'user_name' => $reply['user_name'] . ' ' . $reply['user_surname'],
                'user_avatar' => $reply['user_avatar'] ? 'http://192.168.1.137/dijitalsalon/' . $reply['user_avatar'] : null,
                'likes' => (int)$reply['likes_count'],
                'is_liked' => (bool)$reply['is_liked'],
                'created_at' => $reply['created_at'],
            ];
        }

        echo json_encode([
            'success' => true,
            'replies' => $formatted_replies
        ]);
        exit;

    } catch (PDOException $e) {
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
