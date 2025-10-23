<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $story_id = $_GET['id'] ?? null;
    
    if (!$story_id) {
        throw new Exception('Hikaye ID gerekli');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Beğeni sayısını al
    $stmt = $pdo->prepare("SELECT COUNT(*) as likes FROM begeniler WHERE medya_id = ?");
    $stmt->execute([$story_id]);
    $likes = $stmt->fetch()['likes'];
    
    // Yorum sayısını al
    $stmt = $pdo->prepare("SELECT COUNT(*) as comments FROM yorumlar WHERE medya_id = ?");
    $stmt->execute([$story_id]);
    $comments = $stmt->fetch()['comments'];
    
    // Kullanıcının beğenip beğenmediğini kontrol et
    $stmt = $pdo->prepare("SELECT COUNT(*) as user_liked FROM begeniler WHERE medya_id = ? AND kullanici_id = ?");
    $stmt->execute([$story_id, $user_id]);
    $user_liked = $stmt->fetch()['user_liked'] > 0;
    
    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'comments' => $comments,
        'user_liked' => $user_liked
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
