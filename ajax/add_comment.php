<?php
/**
 * Add Comment AJAX Handler
 * Digital Salon - Yorum ekleme işlemi
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

if (!$input || !isset($input['media_id']) || !isset($input['content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
    exit;
}

$media_id = (int)$input['media_id'];
$content = trim($input['content']);

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Yorum boş olamaz']);
    exit;
}

if (strlen($content) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Yorum çok uzun (max 500 karakter)']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Medya var mı ve kullanıcı katılımcı mı kontrol et
    $stmt = $pdo->prepare("
        SELECT m.id, m.dugun_id, dk.yetkiler
        FROM medyalar m
        JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id AND dk.kullanici_id = ?
        WHERE m.id = ?
    ");
    $stmt->execute([$user_id, $media_id]);
    $media = $stmt->fetch();
    
    if (!$media) {
        throw new Exception('Medya bulunamadı veya bu düğüne katılımcı değilsiniz');
    }
    
    // ✅ Yeni yetki sistemi - JSON'dan yetkileri parse et
    $permissions = [];
    if ($media['yetkiler']) {
        $permissions = json_decode($media['yetkiler'], true) ?: [];
    }
    
    // Yorum yapma yetkisi kontrolü
    if (!in_array('yorum_yapabilir', $permissions)) {
        throw new Exception('Yorum yapma yetkiniz bulunmuyor');
    }
    
    // Yorum ekle
    $stmt = $pdo->prepare("INSERT INTO yorumlar (medya_id, kullanici_id, yorum_metni, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$media_id, $user_id, $content]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Yorum başarıyla eklendi'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
