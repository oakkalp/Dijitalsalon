<?php
/**
 * Bulk Change Role
 * Digital Salon - Toplu rol değiştirme
 */

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
$user_ids = $input['user_ids'] ?? [];
$event_id = $input['event_id'] ?? null;
$new_role = $input['new_role'] ?? null;

if (empty($user_ids) || !$event_id || !$new_role) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Geçerli roller
$valid_roles = ['normal_kullanici', 'yetkili_kullanici', 'moderator'];
if (!in_array($new_role, $valid_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

try {
    // Yetki kontrolü
    $can_change_role = false;
    
    // Süper admin her şeyi yapabilir
    if ($user_role === 'super_admin') {
        $can_change_role = true;
    }
    // Moderator kendi düğünlerinde rol değiştirebilir
    elseif ($user_role === 'moderator') {
        $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch()) {
            $can_change_role = true;
        }
    }
    // Yetkili kullanıcı rol değiştirebilir (sadece normal_kullanici ve yetkili_kullanici arasında)
    else {
        $stmt = $pdo->prepare("SELECT rol, kullanici_engelleyebilir FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$event_id, $user_id]);
        $participant = $stmt->fetch();
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici' && $participant['kullanici_engelleyebilir']) {
            // Yetkili kullanıcı sadece normal_kullanici ve yetkili_kullanici arasında değiştirebilir
            if (in_array($new_role, ['normal_kullanici', 'yetkili_kullanici'])) {
                $can_change_role = true;
            }
        }
    }
    
    if (!$can_change_role) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit;
    }
    
    $updated_count = 0;
    
    // Her kullanıcının rolünü değiştir
    foreach ($user_ids as $target_user_id) {
        // Kendi rolünü değiştiremez
        if ($target_user_id == $user_id) {
            continue;
        }
        
        $stmt = $pdo->prepare("UPDATE dugun_katilimcilar SET rol = ? WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$new_role, $event_id, $target_user_id]);
        
        if ($stmt->rowCount() > 0) {
            $updated_count++;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'updated_count' => $updated_count,
        'message' => "$updated_count kullanıcının rolü değiştirildi"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
