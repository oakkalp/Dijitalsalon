<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$user_id = require_auth();

// Get POST data
$event_id = $_POST['event_id'] ?? null;
$target_user_id = $_POST['target_user_id'] ?? null;
$permissions_json = $_POST['permissions'] ?? '[]';

// Parse permissions from JSON
$permissions = json_decode($permissions_json, true);
if (!is_array($permissions)) {
    $permissions = [];
}

if (!$event_id || !$target_user_id) {
    json_err(400, 'Event ID and Target User ID are required');
}

try {
    // Check if user is authorized to grant permissions
    $stmt = $pdo->prepare("
        SELECT dk.rol, dk.yetkiler 
        FROM dugun_katilimcilar dk 
        WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        json_err(403, 'Bu etkinlikte yetki verme yetkiniz bulunmuyor');
    }
    
    $user_role = $user_data['rol'];
    $user_permissions = $user_data['yetkiler'] ? json_decode($user_data['yetkiler'], true) : [];
    
    // Yetki kontrolü: Admin, Moderator veya "yetki_duzenleyebilir" yetkisi olan kullanıcılar
    $can_grant_permissions = in_array($user_role, ['admin', 'moderator']) || 
                            (isset($user_permissions['yetki_duzenleyebilir']) && $user_permissions['yetki_duzenleyebilir'] === true);
    
    if (!$can_grant_permissions) {
        json_err(403, 'Bu etkinlikte yetki verme yetkiniz bulunmuyor');
    }
    
    // Check if target user exists in the event
    $stmt = $pdo->prepare("
        SELECT dk.rol, k.ad, k.soyad
        FROM dugun_katilimcilar dk
        JOIN kullanicilar k ON dk.kullanici_id = k.id
        WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
    ");
    $stmt->execute([$event_id, $target_user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        json_err(404, 'User not found in this event');
    }
    
    // Prevent users from changing their own permissions
    if ($target_user_id == $user_id) {
        json_err(400, 'Kendi yetkilerinizi değiştiremezsiniz');
    }
    
    // ✅ Yeni mantık: "Yetki Düzenleyebilir" yetkisi olanlar herkesi yönetebilir
    $can_manage = false;
    if ($user_role === 'admin' || $user_role === 'moderator') {
        $can_manage = true;
    } elseif ($user_role === 'yetkili_kullanici' && isset($user_permissions['yetki_duzenleyebilir']) && $user_permissions['yetki_duzenleyebilir'] === true) {
        $can_manage = true;
    }
    
    if (!$can_manage) {
        json_err(403, 'Bu etkinlikte yetki düzenleme yetkiniz bulunmuyor');
    }
    
    // ✅ Yeni rol mantığı: Sadece "Kullanıcı Engelleyebilir" VE "Yetki Düzenleyebilir" yetkileri olanlar "Yetkili Katılımcı"
    $has_kullanici_engelleyebilir = in_array('kullanici_engelleyebilir', $permissions);
    $has_yetki_duzenleyebilir = in_array('yetki_duzenleyebilir', $permissions);
    
    if ($has_kullanici_engelleyebilir && $has_yetki_duzenleyebilir) {
        $new_role = 'yetkili_kullanici';
    } else {
        $new_role = 'kullanici';
    }
    
    $permissions_json = json_encode($permissions);
    
    // Update user role and permissions
    $stmt = $pdo->prepare("
        UPDATE dugun_katilimcilar 
        SET rol = ?, yetkiler = ?
        WHERE dugun_id = ? AND kullanici_id = ?
    ");
    $stmt->execute([$new_role, $permissions_json, $event_id, $target_user_id]);
    
    // Log the change
    $log_message = "User {$user_id} granted permissions to {$target_user['ad']} {$target_user['soyad']} in event $event_id: " . implode(', ', $permissions);
    error_log("Permission Grant: $log_message");
    
    json_success([
        'message' => 'Yetkiler başarıyla güncellendi',
        'target_user' => [
            'id' => (int)$target_user_id,
            'name' => trim($target_user['ad'] . ' ' . $target_user['soyad']),
            'role' => $new_role, // ✅ Dinamik rol
            'permissions' => $permissions,
        ],
    ]);
    
} catch (Exception $e) {
    error_log("Grant Permission API Error: " . $e->getMessage());
    json_err(500, 'Failed to grant permissions: ' . $e->getMessage());
}
?>

