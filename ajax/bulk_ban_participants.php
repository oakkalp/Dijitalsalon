<?php
/**
 * Bulk Ban Participants
 * Digital Salon - Toplu kullanıcı yasaklama
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
    // Yetkili kullanıcı yasaklayabilir
    else {
        $stmt = $pdo->prepare("SELECT rol, kullanici_engelleyebilir FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$event_id, $user_id]);
        $participant = $stmt->fetch();
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici' && $participant['kullanici_engelleyebilir']) {
            $can_ban = true;
        }
    }
    
    if (!$can_ban) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit;
    }
    
    $banned_count = 0;
    
    // Her kullanıcıyı yasakla
    foreach ($user_ids as $target_user_id) {
        // Kendini yasaklayamaz
        if ($target_user_id == $user_id) {
            continue;
        }
        
        // Zaten yasaklı mı kontrol et
        $stmt = $pdo->prepare("SELECT id FROM blocked_users WHERE dugun_id = ? AND blocked_user_id = ?");
        $stmt->execute([$event_id, $target_user_id]);
        if ($stmt->fetch()) {
            continue; // Zaten yasaklı
        }
        
        // Yasakla
        $stmt = $pdo->prepare("INSERT INTO blocked_users (dugun_id, blocked_user_id, blocked_by_user_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$event_id, $target_user_id, $user_id]);
        $banned_count++;
    }
    
    echo json_encode([
        'success' => true, 
        'banned_count' => $banned_count,
        'message' => "$banned_count kullanıcı yasaklandı"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
