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
    $action = $_POST['action'] ?? null; // 'like' or 'unlike'

    if (empty($comment_id) || empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Comment ID and action are required.']);
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
        
        // Check if user already liked this comment
        $stmt = $pdo->prepare("SELECT id FROM yorum_begeniler WHERE yorum_id = ? AND kullanici_id = ?");
        $stmt->execute([$comment_id, $user_id]);
        $existing_like = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($action === 'like') {
            if ($existing_like) {
                echo json_encode(['success' => true, 'message' => 'Already liked.']);
                exit;
            }
            
            // Add like
            $stmt = $pdo->prepare("
                INSERT INTO yorum_begeniler (yorum_id, kullanici_id, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$comment_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Comment liked successfully.']);
            exit;
            
        } elseif ($action === 'unlike') {
            if (!$existing_like) {
                echo json_encode(['success' => true, 'message' => 'Not liked yet.']);
                exit;
            }
            
            // Remove like
            $stmt = $pdo->prepare("DELETE FROM yorum_begeniler WHERE yorum_id = ? AND kullanici_id = ?");
            $stmt->execute([$comment_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Comment unliked successfully.']);
            exit;
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
            exit;
        }
        
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

