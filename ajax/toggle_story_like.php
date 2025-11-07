<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $story_id = $input['story_id'] ?? null;
    
    if (!$story_id) {
        throw new Exception('Hikaye ID gerekli');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Mevcut beğeniyi kontrol et
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM begeniler WHERE medya_id = ? AND kullanici_id = ?");
    $stmt->execute([$story_id, $user_id]);
    $exists = $stmt->fetch()['count'] > 0;
    
    if ($exists) {
        // Beğeniyi kaldır
        $stmt = $pdo->prepare("DELETE FROM begeniler WHERE medya_id = ? AND kullanici_id = ?");
        $stmt->execute([$story_id, $user_id]);
        $liked = false;
    } else {
        // Beğeniyi ekle
        $stmt = $pdo->prepare("INSERT INTO begeniler (medya_id, kullanici_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$story_id, $user_id]);
        $liked = true;
    }
    
    // Yeni beğeni sayısını al
    $stmt = $pdo->prepare("SELECT COUNT(*) as likes FROM begeniler WHERE medya_id = ?");
    $stmt->execute([$story_id]);
    $likes = $stmt->fetch()['likes'];
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'likes' => $likes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
