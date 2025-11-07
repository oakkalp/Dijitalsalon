<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$comment_id = $_POST['comment_id'] ?? '';

if (empty($comment_id)) {
    json_err(400, 'Comment ID required');
}

try {
    $user_id = require_auth();
    
    // Check if comment exists and get details
    $stmt = $pdo->prepare("SELECT * FROM yorumlar WHERE id = ?");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        json_err(404, 'Comment not found');
    }
    
    // Check if user can delete this comment
    $canDelete = false;
    
    // User can delete their own comments
    if ($comment['kullanici_id'] == $user_id) {
        $canDelete = true;
    }
    
    // Check if user has yorum_silebilir permission in this event
    if (!$canDelete) {
        // Get media's event
        $stmt = $pdo->prepare("SELECT dugun_id FROM medyalar WHERE id = ?");
        $stmt->execute([$comment['medya_id']]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($media) {
            // Check user's permissions in this event
            $stmt = $pdo->prepare("SELECT yetkiler FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
            $stmt->execute([$media['dugun_id'], $user_id]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($participant && $participant['yetkiler']) {
                $permissions = json_decode($participant['yetkiler'], true);
                if (in_array('yorum_silebilir', $permissions)) {
                    $canDelete = true;
                }
            }
        }
    }
    
    if (!$canDelete) {
        json_err(403, 'You do not have permission to delete this comment');
    }
    
    // Delete the comment
    $stmt = $pdo->prepare("DELETE FROM yorumlar WHERE id = ?");
    $stmt->execute([$comment_id]);
    
    json_ok(['message' => 'Comment deleted successfully']);
    
} catch (Exception $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}
?>