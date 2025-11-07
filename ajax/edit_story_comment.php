<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $comment_id = $input['comment_id'] ?? null;
    $content = trim($input['content'] ?? '');
    
    if (!$comment_id || !$content) {
        throw new Exception('Yorum ID ve içerik gerekli');
    }
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'] ?? 'kullanici';
    
    // Yorum bilgilerini al
    $stmt = $pdo->prepare("
        SELECT y.*, m.dugun_id
        FROM yorumlar y
        JOIN medyalar m ON y.medya_id = m.id
        WHERE y.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        throw new Exception('Yorum bulunamadı');
    }
    
    // Yetki kontrolü: Super Admin, Moderator, Yetkili Kullanıcı veya yorum sahibi
    $canEdit = false;
    
    if ($user_role === 'super_admin' || $user_role === 'moderator') {
        $canEdit = true;
    } elseif ($user_role === 'yetkili_kullanici') {
        // Yetkili kullanıcı sadece kendi düğünündeki yorumları düzenleyebilir
        $stmt = $pdo->prepare("SELECT rol FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$comment['dugun_id'], $user_id]);
        $participant = $stmt->fetch();
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici') {
            $canEdit = true;
        }
    } elseif ($comment['kullanici_id'] == $user_id) {
        // Yorum sahibi kendi yorumunu düzenleyebilir
        $canEdit = true;
    }
    
    if (!$canEdit) {
        throw new Exception('Bu yorumu düzenleme yetkiniz bulunmuyor');
    }
    
    // Yorumu güncelle
    $stmt = $pdo->prepare("UPDATE yorumlar SET yorum_metni = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$content, $comment_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Yorum başarıyla güncellendi'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

