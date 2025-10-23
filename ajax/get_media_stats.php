<?php
/**
 * Get Media Statistics
 * Digital Salon - Medya istatistiklerini getir
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$media_id = (int)($_GET['media_id'] ?? 0);

if (!$media_id) {
    echo json_encode(['success' => false, 'message' => 'Missing media ID']);
    exit;
}

try {
    // Beğeni sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM begeniler WHERE medya_id = ?");
    $stmt->execute([$media_id]);
    $likes = $stmt->fetchColumn();
    
    // Yorum sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM yorumlar WHERE medya_id = ?");
    $stmt->execute([$media_id]);
    $comments = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'comments' => $comments
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
