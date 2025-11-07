<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $comment_id = $input['comment_id'] ?? null;
    
    if (!$comment_id) {
        throw new Exception('Yorum ID gerekli');
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Giriş yapmalısınız');
    }
    
    // Mevcut beğeniyi kontrol et
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM yorum_begeniler WHERE yorum_id = ? AND kullanici_id = ?");
    $stmt->execute([$comment_id, $user_id]);
    $exists = $stmt->fetch()['count'] > 0;
    
    if ($exists) {
        // Beğeniyi kaldır
        $stmt = $pdo->prepare("DELETE FROM yorum_begeniler WHERE yorum_id = ? AND kullanici_id = ?");
        $stmt->execute([$comment_id, $user_id]);
        $liked = false;
    } else {
        // Beğeniyi ekle
        $stmt = $pdo->prepare("INSERT INTO yorum_begeniler (yorum_id, kullanici_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$comment_id, $user_id]);
        $liked = true;
    }
    
    // Yeni beğeni sayısını al
    $stmt = $pdo->prepare("SELECT COUNT(*) as likes FROM yorum_begeniler WHERE yorum_id = ?");
    $stmt->execute([$comment_id]);
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