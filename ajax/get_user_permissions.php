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
$target_user_id = (int)($_GET['user_id'] ?? 0);
$event_id = (int)($_GET['event_id'] ?? 0);

if (!$target_user_id || !$event_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Yetki kontrolü
    $can_view_permissions = false;
    
    // Süper admin her şeyi görebilir
    if ($user_role === 'super_admin') {
        $can_view_permissions = true;
    }
    // Moderator kendi düğünlerinde görebilir
    elseif ($user_role === 'moderator') {
        $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch()) {
            $can_view_permissions = true;
        }
    }
    // Yetkili kullanıcı görebilir (katılımcı rolü yetkili_kullanici olan)
    else {
        $stmt = $pdo->prepare("SELECT rol, kullanici_engelleyebilir FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$event_id, $user_id]);
        $participant = $stmt->fetch();
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici' && $participant['kullanici_engelleyebilir']) {
            $can_view_permissions = true;
        }
    }
    
    if (!$can_view_permissions) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit;
    }
    
    // Get user permissions for this event
    $stmt = $pdo->prepare("
        SELECT 
            medya_silebilir,
            yorum_silebilir,
            kullanici_engelleyebilir,
            medya_paylasabilir,
            yorum_yapabilir,
            hikaye_paylasabilir,
            profil_degistirebilir,
            rol
        FROM dugun_katilimcilar 
        WHERE dugun_id = ? AND kullanici_id = ?
    ");
    $stmt->execute([$event_id, $target_user_id]);
    $permissions = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permissions) {
        echo json_encode(['success' => false, 'message' => 'User is not a participant']);
        exit;
    }
    
    // Convert boolean values to proper format
    $permissions['medya_silebilir'] = (bool)$permissions['medya_silebilir'];
    $permissions['yorum_silebilir'] = (bool)$permissions['yorum_silebilir'];
    $permissions['kullanici_engelleyebilir'] = (bool)$permissions['kullanici_engelleyebilir'];
    $permissions['medya_paylasabilir'] = (bool)$permissions['medya_paylasabilir'];
    $permissions['yorum_yapabilir'] = (bool)$permissions['yorum_yapabilir'];
    $permissions['hikaye_paylasabilir'] = (bool)$permissions['hikaye_paylasabilir'];
    $permissions['profil_degistirebilir'] = (bool)$permissions['profil_degistirebilir'];
    
    echo json_encode([
        'success' => true,
        'permissions' => $permissions
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>