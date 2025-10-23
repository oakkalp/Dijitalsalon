<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parent_comment_id = $_POST['parent_comment_id'] ?? null;
    $content = $_POST['content'] ?? null;

    if (empty($parent_comment_id) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parent comment ID and content are required.']);
        exit;
    }

    if (strlen($content) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Reply is too long (max 500 characters).']);
        exit;
    }

    try {
        // Get media_id from parent comment
        $stmt = $pdo->prepare("
            SELECT y.medya_id, m.dugun_id
            FROM yorumlar y
            JOIN medyalar m ON y.medya_id = m.id
            JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
            WHERE y.id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$parent_comment_id, $user_id]);
        $parent_comment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$parent_comment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Parent comment not found or access denied.']);
            exit;
        }
        
        // Add reply
        $stmt = $pdo->prepare("
            INSERT INTO yorumlar (medya_id, kullanici_id, yorum_metni, parent_comment_id, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$parent_comment['medya_id'], $user_id, $content, $parent_comment_id]);
        
        $reply_id = $pdo->lastInsertId();
        
        // Get user info for response
        $stmt = $pdo->prepare("
            SELECT ad, soyad, profil_fotografi 
            FROM kullanicilar
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Reply added successfully.',
            'reply' => [
                'id' => (int)$reply_id,
                'parent_comment_id' => (int)$parent_comment_id,
                'user_name' => $user_info['ad'] . ' ' . $user_info['soyad'],
                'user_avatar' => $user_info['profil_fotografi'] ? 'http://192.168.1.137/dijitalsalon/' . $user_info['profil_fotografi'] : null,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'), // Use current time for immediate response
                'likes' => 0,
                'is_liked' => false,
            ]
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

