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
$media_id = $input['media_id'] ?? null;

if (!$media_id) {
    echo json_encode(['success' => false, 'message' => 'Media ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Yetki kontrolü
    $stmt = $pdo->prepare("
        SELECT 
            m.kullanici_id as media_owner,
            m.dosya_yolu,
            m.kucuk_resim_yolu,
            m.dugun_id,
            d.moderator_id,
            dk.rol as participant_role
        FROM medyalar m
        JOIN dugunler d ON m.dugun_id = d.id
        LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id AND dk.kullanici_id = ?
        WHERE m.id = ?
    ");
    $stmt->execute([$user_id, $media_id]);
    $media = $stmt->fetch();
    
    if (!$media) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Media not found']);
        exit;
    }
    
    $can_delete = false;
    
    // Süper admin her şeyi yapabilir
    if ($user_role === 'super_admin') {
        $can_delete = true;
    }
    // Moderator kendi düğünlerindeki medyaları yönetebilir
    elseif ($user_role === 'moderator' && $media['moderator_id'] == $user_id) {
        $can_delete = true;
    }
    // Yetkili kullanıcı kendi düğünlerindeki medyaları yönetebilir
    elseif ($media['participant_role'] === 'yetkili_kullanici') {
        $can_delete = true;
    }
    // Normal kullanıcı sadece kendi medyalarını yönetebilir
    elseif ($media['media_owner'] == $user_id) {
        $can_delete = true;
    }
    
    if (!$can_delete) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Bu medyayı silme yetkiniz yok']);
        exit;
    }
    
    // İlgili yorumları sil
    $stmt = $pdo->prepare("DELETE FROM yorumlar WHERE medya_id = ?");
    $stmt->execute([$media_id]);
    
    // İlgili beğenileri sil
    $stmt = $pdo->prepare("DELETE FROM begeniler WHERE medya_id = ?");
    $stmt->execute([$media_id]);
    
    // Medyayı sil
    $stmt = $pdo->prepare("DELETE FROM medyalar WHERE id = ?");
    $stmt->execute([$media_id]);
    
    // Dosyaları sil
    if ($media['dosya_yolu'] && file_exists($media['dosya_yolu'])) {
        unlink($media['dosya_yolu']);
    }
    if ($media['kucuk_resim_yolu'] && file_exists($media['kucuk_resim_yolu'])) {
        unlink($media['kucuk_resim_yolu']);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Medya başarıyla silindi']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete media error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
