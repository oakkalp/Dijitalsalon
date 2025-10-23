<?php
/**
 * Toggle Like AJAX Handler
 * Digital Salon - Beğeni toggle işlemi
 */

require_once '../config/database.php';
require_once '../includes/security.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor']);
    exit;
}

$user_id = $_SESSION['user_id'];

// JSON input al
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['media_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
    exit;
}

$media_id = (int)$input['media_id'];

try {
    $pdo->beginTransaction();
    
    // Medya var mı kontrol et
    $stmt = $pdo->prepare("SELECT id FROM medyalar WHERE id = ?");
    $stmt->execute([$media_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Medya bulunamadı');
    }
    
    // Kullanıcının daha önce beğenip beğenmediğini kontrol et
    $stmt = $pdo->prepare("SELECT id FROM begeniler WHERE medya_id = ? AND kullanici_id = ?");
    $stmt->execute([$media_id, $user_id]);
    $existing_like = $stmt->fetch();
    
    if ($existing_like) {
        // Beğeniyi kaldır
        $stmt = $pdo->prepare("DELETE FROM begeniler WHERE id = ?");
        $stmt->execute([$existing_like['id']]);
        $liked = false;
    } else {
        // Beğeni ekle
        $stmt = $pdo->prepare("INSERT INTO begeniler (medya_id, kullanici_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$media_id, $user_id]);
        $liked = true;
    }
    
    // Beğeni sayısını al
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM begeniler WHERE medya_id = ?");
    $stmt->execute([$media_id]);
    $like_count = $stmt->fetch()['count'];
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'like_count' => $like_count
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
