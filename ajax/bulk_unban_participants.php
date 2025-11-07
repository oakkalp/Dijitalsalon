<?php
/**
 * Bulk Unban Participants
 * Digital Salon - Toplu kullanıcı yasağı kaldırma
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

if (empty($user_ids) || !$event_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Yetki kontrolü
    $can_unban = false;
    
    // Süper admin her şeyi yapabilir
    if ($user_role === 'super_admin') {
        $can_unban = true;
    }
    // Moderator kendi düğünlerinde yasağı kaldırabilir
    elseif ($user_role === 'moderator') {
        $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch()) {
            $can_unban = true;
        }
    }
    // Yetkili kullanıcı yasağı kaldırabilir
    else {
        $stmt = $pdo->prepare("SELECT rol, kullanici_engelleyebilir FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$event_id, $user_id]);
        $participant = $stmt->fetch();
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici' && $participant['kullanici_engelleyebilir']) {
            $can_unban = true;
        }
    }
    
    if (!$can_unban) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit;
    }
    
    $unbanned_count = 0;
    
    // Her kullanıcının yasağını kaldır
    foreach ($user_ids as $target_user_id) {
        $stmt = $pdo->prepare("DELETE FROM blocked_users WHERE dugun_id = ? AND blocked_user_id = ?");
        $stmt->execute([$event_id, $target_user_id]);
        
        if ($stmt->rowCount() > 0) {
            $unbanned_count++;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'unbanned_count' => $unbanned_count,
        'message' => "$unbanned_count kullanıcının yasağı kaldırıldı"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
