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
        // Remove the like
        $stmt = $pdo->prepare("
            DELETE FROM begeniler 
            WHERE medya_id = ? AND kullanici_id = ?
        ");
        $stmt->execute([$media_id, $user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Media unliked successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Unlike error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
?>

