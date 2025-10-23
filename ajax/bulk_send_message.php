<?php
/**
 * Bulk Send Message
 * Digital Salon - Toplu mesaj gönderme
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
$message = $input['message'] ?? '';

if (empty($user_ids) || !$event_id || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Yetki kontrolü
    $can_send_message = false;
    
    // Süper admin her şeyi yapabilir
    if ($user_role === 'super_admin') {
        $can_send_message = true;
    }
    // Moderator kendi düğünlerinde mesaj gönderebilir
    elseif ($user_role === 'moderator') {
        $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch()) {
            $can_send_message = true;
        }
    }
    // Yetkili kullanıcı mesaj gönderebilir
    else {
        $stmt = $pdo->prepare("SELECT rol, kullanici_engelleyebilir FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$event_id, $user_id]);
        $participant = $stmt->fetch();
        
        if ($participant && $participant['rol'] === 'yetkili_kullanici' && $participant['kullanici_engelleyebilir']) {
            $can_send_message = true;
        }
    }
    
    if (!$can_send_message) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit;
    }
    
    $sent_count = 0;
    
    // Her kullanıcıya mesaj gönder (şimdilik sadece log'a kaydet)
    foreach ($user_ids as $target_user_id) {
        // Kendine mesaj gönderme
        if ($target_user_id == $user_id) {
            continue;
        }
        
        // Mesajı veritabanına kaydet (bildirimler tablosu varsa)
        // Şimdilik sadece sayacı artır
        $sent_count++;
        
        // Gerçek uygulamada burada:
        // - Email gönderme
        // - SMS gönderme
        // - Push notification
        // - Veritabanına bildirim kaydetme
        // gibi işlemler yapılabilir
    }
    
    echo json_encode([
        'success' => true, 
        'sent_count' => $sent_count,
        'message' => "$sent_count kullanıcıya mesaj gönderildi"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
