<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $story_id = $input['story_id'] ?? null;
    $caption = $input['caption'] ?? '';
    
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
    $canEdit = false;
    
    if ($user_role === 'super_admin' || $user_role === 'moderator') {
        $canEdit = true;
    } elseif ($user_role === 'yetkili_kullanici') {
        // Yetkili kullanıcı sadece kendi düğünündeki hikayeleri düzenleyebilir
        $stmt = $pdo->prepare("SELECT rol FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$story['dugun_id'], $user_id]);
        $participant = $stmt->fetch();
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici') {
            $canEdit = true;
        }
    } elseif ($story['story_owner_id'] == $user_id) {
        // Hikaye sahibi kendi hikayesini düzenleyebilir
        $canEdit = true;
    }
    
    if (!$canEdit) {
        throw new Exception('Bu hikayeyi düzenleme yetkiniz bulunmuyor');
    }
    
    // Açıklamayı güncelle
    $stmt = $pdo->prepare("UPDATE medyalar SET aciklama = ? WHERE id = ?");
    $stmt->execute([$caption, $story_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Hikaye açıklaması güncellendi'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

