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
$target_user_id = $input['target_user_id'] ?? null;
$event_id = $input['event_id'] ?? null;

if (!$target_user_id || !$event_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Debug için log
    error_log("Ban participant - User ID: $user_id, Role: $user_role, Target: $target_user_id, Event: $event_id");
    
    // Yetki kontrolü
    $can_ban = false;
    
    // Süper admin her şeyi yapabilir
    if ($user_role === 'super_admin') {
        $can_ban = true;
    }
    // Moderator kendi düğünlerinde yasaklayabilir
    elseif ($user_role === 'moderator') {
        $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch()) {
            $can_ban = true;
        }
    }
    // Yetkili kullanıcı yasaklayabilir (katılımcı rolü yetkili_kullanici olan)
    else {
        $stmt = $pdo->prepare("SELECT rol, kullanici_engelleyebilir FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$event_id, $user_id]);
        $participant = $stmt->fetch();
        error_log("Yetkili kullanıcı ban kontrolü - Participant: " . json_encode($participant));
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici' && $participant['kullanici_engelleyebilir']) {
            $can_ban = true;
            error_log("Yetkili kullanıcı ban izni verildi");
        } else {
            error_log("Yetkili kullanıcı ban izni reddedildi");
        }
    }
    
    if (!$can_ban) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit;
    }
    
    // Hedef kullanıcının moderator olup olmadığını kontrol et
    $stmt = $pdo->prepare("SELECT moderator_id FROM dugunler WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if ($event && $event['moderator_id'] == $target_user_id) {
        echo json_encode(['success' => false, 'message' => 'Cannot ban event moderator']);
        exit;
    }
    
    // Kullanıcıyı yasakla
    $stmt = $pdo->prepare("
        INSERT INTO blocked_users (dugun_id, blocked_user_id, blocked_by_user_id, reason, created_at) 
        VALUES (?, ?, ?, 'Banned by moderator', NOW())
        ON DUPLICATE KEY UPDATE 
        blocked_by_user_id = VALUES(blocked_by_user_id), 
        created_at = VALUES(created_at), 
        reason = VALUES(reason)
    ");
    $stmt->execute([$event_id, $target_user_id, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'User banned successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
