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
    $user_role = $_SESSION['user_role'] ?? 'kullanici';
    
    // Hikaye bilgilerini al
    $stmt = $pdo->prepare("
        SELECT m.*, m.dugun_id, m.kullanici_id as story_owner_id
        FROM medyalar m
        WHERE m.id = ? AND m.tur = 'hikaye'
    ");
    $stmt->execute([$story_id]);
    $story = $stmt->fetch();
    
    if (!$story) {
        throw new Exception('Hikaye bulunamadı');
    }
    
    // Yetki kontrolü: Super Admin, Moderator, Yetkili Kullanıcı veya hikaye sahibi
    $canDelete = false;
    
    if ($user_role === 'super_admin' || $user_role === 'moderator') {
        $canDelete = true;
    } elseif ($user_role === 'yetkili_kullanici') {
        // Yetkili kullanıcı sadece kendi düğünündeki hikayeleri silebilir
        $stmt = $pdo->prepare("SELECT rol FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$story['dugun_id'], $user_id]);
        $participant = $stmt->fetch();
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici') {
            $canDelete = true;
        }
    } elseif ($story['story_owner_id'] == $user_id) {
        // Hikaye sahibi kendi hikayesini silebilir
        $canDelete = true;
    }
    
    if (!$canDelete) {
        throw new Exception('Bu hikayeyi silme yetkiniz bulunmuyor');
    }
    
    // Dosyayı sil
    if (file_exists($story['dosya_yolu'])) {
        unlink($story['dosya_yolu']);
    }
    
    // Veritabanından sil
    $stmt = $pdo->prepare("DELETE FROM medyalar WHERE id = ?");
    $stmt->execute([$story_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Hikaye başarıyla silindi'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

