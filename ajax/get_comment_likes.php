<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $comment_id = $_GET['id'] ?? null;
    
    if (!$comment_id) {
        throw new Exception('Yorum ID gerekli');
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Giriş yapmalısınız');
    }
    
    // Beğeni sayısını al
    $stmt = $pdo->prepare("SELECT COUNT(*) as likes FROM yorum_begeniler WHERE yorum_id = ?");
    $stmt->execute([$comment_id]);
    $likes = $stmt->fetch()['likes'];
    
    // Kullanıcının beğenip beğenmediğini kontrol et
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM yorum_begeniler WHERE yorum_id = ? AND kullanici_id = ?");
    $stmt->execute([$comment_id, $user_id]);
    $user_liked = $stmt->fetch()['count'] > 0;
    
    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'user_liked' => $user_liked
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
