<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $media_id = $_POST['media_id'] ?? '';
    $action = $_POST['action'] ?? 'like'; // 'like' or 'unlike'
    
    if (empty($media_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Media ID is required.']);
        exit;
    }
    
    try {
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
        
        // Check if user already liked this media
        $stmt = $pdo->prepare("SELECT id FROM begeniler WHERE medya_id = ? AND kullanici_id = ?");
        $stmt->execute([$media_id, $user_id]);
        $existing_like = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($action === 'like') {
            if ($existing_like) {
                echo json_encode(['success' => true, 'message' => 'Already liked.']);
                exit;
            }
            
            // Add like
            $stmt = $pdo->prepare("
                INSERT INTO begeniler (medya_id, kullanici_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$media_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Media liked successfully.']);
            exit;
            
        } elseif ($action === 'unlike') {
            if (!$existing_like) {
                echo json_encode(['success' => true, 'message' => 'Not liked yet.']);
                exit;
            }
            
            // Remove like
            $stmt = $pdo->prepare("DELETE FROM begeniler WHERE medya_id = ? AND kullanici_id = ?");
            $stmt->execute([$media_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Media unliked successfully.']);
            exit;
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use "like" or "unlike".']);
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
