<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_id = $_POST['comment_id'] ?? null;
    $content = $_POST['content'] ?? null;

    if (empty($comment_id) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Comment ID and content are required.']);
        exit;
    }

    if (strlen($content) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Comment is too long (max 500 characters).']);
        exit;
    }

    try {
        // Check if comment exists and user has permission to edit
        $stmt = $pdo->prepare("
            SELECT y.id, y.kullanici_id, y.medya_id, m.dugun_id, dk.kullanici_id as participant_id, dk.rol
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
        
        // Check if user can edit (own comment or moderator/admin)
        $canEdit = false;
        
        // Own comment
        if ($comment['kullanici_id'] == $user_id) {
            $canEdit = true;
        }
        
        // Moderator or admin
        if (in_array($comment['rol'], ['yetkili_kullanici', 'moderator', 'admin'])) {
            $canEdit = true;
        }
        
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You do not have permission to edit this comment.']);
            exit;
        }
        
        // Update comment
        $stmt = $pdo->prepare("UPDATE yorumlar SET yorum_metni = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $comment_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment updated successfully.',
            'comment' => [
                'id' => (int)$comment_id,
                'content' => $content,
                'updated_at' => date('Y-m-d H:i:s'),
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
