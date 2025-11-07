<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $story_id = $input['story_id'] ?? null;
    $content = trim($input['content'] ?? '');
    
    if (!$story_id || !$content) {
        throw new Exception('Hikaye ID ve yorum içeriği gerekli');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Kullanıcının yorum yapma yetkisini kontrol et
    $stmt = $pdo->prepare("
        SELECT dk.yorum_yapabilir 
        FROM medyalar m
        JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id AND dk.kullanici_id = ?
        WHERE m.id = ? AND m.tur = 'hikaye'
    ");
    $stmt->execute([$user_id, $story_id]);
    $permission = $stmt->fetch();
    
    if (!$permission || !$permission['yorum_yapabilir']) {
        throw new Exception('Bu hikayeye yorum yapma yetkiniz bulunmuyor');
    }
    
    // Yorumu ekle
    $stmt = $pdo->prepare("
        INSERT INTO yorumlar (medya_id, kullanici_id, yorum_metni, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$story_id, $user_id, $content]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Yorum başarıyla eklendi'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
