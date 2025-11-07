<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$user_id = require_auth();

// Get POST data
$event_id = (int)($_POST['event_id'] ?? 0);
$target_user_id = (int)($_POST['target_user_id'] ?? 0);
$new_role = $_POST['new_role'] ?? null;
$new_status = $_POST['new_status'] ?? null;

if (!$event_id || !$target_user_id) {
    json_err(400, 'Event ID and Target User ID are required');
}

if (!$new_role && !$new_status) {
    json_err(400, 'New role or status is required');
}

try {
    // Check if user is authorized to manage participants
    // Süper Admin, Moderatör ve Yetkili Kullanıcı katılımcıları yönetebilir
    $stmt = $pdo->prepare("
        SELECT dk.rol 
        FROM dugun_katilimcilar dk 
        WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $user_role = $stmt->fetchColumn();
    
    if (!$user_role || !in_array($user_role, ['admin', 'moderator', 'yetkili_kullanici'])) {
        json_err(403, 'Bu etkinlikte katılımcı yönetimi yetkiniz bulunmuyor');
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
    
    // Prevent users from changing their own role/status
    if ($target_user_id == $user_id) {
        json_err(400, 'Kendi rolünüzü veya durumunuzu değiştiremezsiniz');
    }
    
    // Yetki hiyerarşisi kontrolü
    if ($user_role === 'yetkili_kullanici') {
        // Yetkili Kullanıcı sadece normal kullanıcıları yönetebilir
        if (in_array($target_user['rol'], ['admin', 'moderator', 'yetkili_kullanici'])) {
            json_err(403, 'Yetkili kullanıcılar sadece normal kullanıcıları yönetebilir');
        }
    } elseif ($user_role === 'moderator') {
        // Moderatör sadece Yetkili Kullanıcı atayabilir, Admin'i değiştiremez
        if ($target_user['rol'] === 'admin') {
            json_err(403, 'Moderatörler admin rollerini değiştiremez');
        }
        if ($new_role && !in_array($new_role, ['kullanici', 'yetkili_kullanici'])) {
            json_err(403, 'Moderatörler sadece normal kullanıcı ve yetkili kullanıcı rolleri atayabilir');
        }
    }
    // Admin her şeyi yapabilir, kontrol gerekmez
    
    // Build update query
    $update_fields = [];
    $update_values = [];
    
    if ($new_role) {
        $update_fields[] = "rol = ?";
        $update_values[] = $new_role;
    }
    
    if ($new_status) {
        $update_fields[] = "durum = ?";
        $update_values[] = $new_status;
    }
    
    if (empty($update_fields)) {
        json_err(400, 'No valid fields to update');
    }
    
    $update_values[] = $event_id;
    $update_values[] = $target_user_id;
    
    $sql = "UPDATE dugun_katilimcilar SET " . implode(', ', $update_fields) . " WHERE dugun_id = ? AND kullanici_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($update_values);
    
    // ✅ Yasaklanan kullanıcı etkinlikten çıkarılmaz, sadece durum güncellenir
    // Bu sayede katılımcı listesinde görünür ama etkinliğe giremez
    
    // Log the change
    $action = [];
    if ($new_role) $action[] = "role to $new_role";
    if ($new_status) $action[] = "status to $new_status";
    
    $log_message = "User {$user_id} changed {$target_user['ad']} {$target_user['soyad']}'s " . implode(' and ', $action) . " in event $event_id";
    error_log("Participant Management: $log_message");
    
    json_success([
        'message' => 'Participant updated successfully',
        'target_user' => [
            'id' => (int)$target_user_id,
            'name' => trim($target_user['ad'] . ' ' . $target_user['soyad']),
            'role' => $new_role ?: $target_user['rol'],
            'status' => $new_status ?: 'aktif',
        ],
    ]);
    
} catch (Exception $e) {
    error_log("Update Participant API Error: " . $e->getMessage());
    json_err(500, 'Failed to update participant');
}
?>
