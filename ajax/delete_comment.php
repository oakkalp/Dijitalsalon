<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'kullanici';
$input = json_decode(file_get_contents('php://input'), true);
$comment_id = $input['comment_id'] ?? null;

if (!$comment_id) {
    echo json_encode(['success' => false, 'message' => 'Comment ID is required']);
    exit;
}

try {
    // Yetki kontrolü
    $stmt = $pdo->prepare("
        SELECT 
            y.kullanici_id as comment_owner,
            y.medya_id,
            d.moderator_id,
            dk.rol as participant_role
        FROM yorumlar y
        JOIN medyalar m ON y.medya_id = m.id
        JOIN dugunler d ON m.dugun_id = d.id
        LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id AND dk.kullanici_id = ?
        WHERE y.id = ? AND y.durum = 'aktif'
    ");
    $stmt->execute([$user_id, $comment_id]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        exit;
    }
    
    $can_delete = false;
    
    // Süper admin her şeyi yapabilir
    if ($user_role === 'super_admin') {
        $can_delete = true;
    }
    // Moderator kendi düğünlerindeki yorumları yönetebilir
    elseif ($user_role === 'moderator' && $comment['moderator_id'] == $user_id) {
        $can_delete = true;
    }
    // Yetkili kullanıcı kendi düğünlerindeki yorumları yönetebilir
    elseif ($user_role === 'yetkili_kullanici' && $comment['participant_role'] === 'yetkili_kullanici') {
        $can_delete = true;
    }
    // Normal kullanıcı sadece kendi yorumlarını yönetebilir
    elseif ($comment['comment_owner'] == $user_id) {
        $can_delete = true;
    }
    
    if (!$can_delete) {
        echo json_encode(['success' => false, 'message' => 'Bu yorumu silme yetkiniz yok']);
        exit;
    }
    
    // Yorumu sil (soft delete)
    $stmt = $pdo->prepare("UPDATE yorumlar SET durum = 'silindi', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$comment_id]);
    
    echo json_encode(['success' => true, 'message' => 'Yorum başarıyla silindi']);

} catch (PDOException $e) {
    error_log("Delete comment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
