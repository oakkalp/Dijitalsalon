<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $media_id = $input['media_id'] ?? null;
    
    if (!$media_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Media ID is required.']);
        exit;
    }
    
    try {
        // Check if user already liked this media
        $stmt = $pdo->prepare("
            SELECT id FROM begeniler 
            WHERE medya_id = ? AND kullanici_id = ?
        ");
        $stmt->execute([$media_id, $user_id]);
        $existing_like = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_like) {
            // Unlike - remove the like
            $stmt = $pdo->prepare("DELETE FROM begeniler WHERE id = ?");
            $stmt->execute([$existing_like['id']]);
            
            echo json_encode([
                'success' => true,
                'action' => 'unliked',
                'message' => 'Media unliked successfully'
            ]);
        } else {
            // Like - add the like
            $stmt = $pdo->prepare("
                INSERT INTO begeniler (medya_id, kullanici_id, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$media_id, $user_id]);
            
            echo json_encode([
                'success' => true,
                'action' => 'liked',
                'message' => 'Media liked successfully'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Like/Unlike error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
?>

