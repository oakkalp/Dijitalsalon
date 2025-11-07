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
    $description = $_POST['description'] ?? '';
    
    if (empty($media_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Media ID is required.']);
        exit;
    }
    
    try {
        // Get media info
        $stmt = $pdo->prepare("
            SELECT m.*, m.dugun_id 
            FROM medyalar m
            WHERE m.id = ?
        ");
        $stmt->execute([$media_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$media) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Media not found.']);
            exit;
        }
        
        // Check if user is participant of this event
        $stmt = $pdo->prepare("
            SELECT dk.id 
            FROM dugun_katilimcilar dk 
            WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$media['dugun_id'], $user_id]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$participant) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not a participant of this event.']);
            exit;
        }
        
        // Check if user can edit this media
        $can_edit = false;
        
        // Own media
        if ($media['kullanici_id'] == $user_id) {
            $can_edit = true;
        }
        
        // Get user role
        $stmt = $pdo->prepare("SELECT rol FROM kullanicilar WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_role = $stmt->fetchColumn();
        
        // Yetkili kullanıcılar
        if (in_array($user_role, ['yetkili_kullanici', 'moderator', 'admin'])) {
            $can_edit = true;
        }
        
        if (!$can_edit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not authorized to edit this media.']);
            exit;
        }
        
        // Update media description
        $stmt = $pdo->prepare("UPDATE medyalar SET aciklama = ? WHERE id = ?");
        $stmt->execute([$description, $media_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Media updated successfully.'
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
