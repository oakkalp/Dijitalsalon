<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$media_id = $_GET['media_id'] ?? null;

if (!$media_id) {
    echo json_encode(['success' => false, 'message' => 'Media ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT aciklama FROM medyalar WHERE id = ?");
    $stmt->execute([$media_id]);
    $media = $stmt->fetch();
    
    if (!$media) {
        echo json_encode(['success' => false, 'message' => 'Media not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'description' => $media['aciklama']
    ]);

} catch (PDOException $e) {
    error_log("Get media info error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
