<?php
/**
 * Özel Bildirim Gönderme
 * Sadece moderator/admin ve `bildirim_gonderebilir` yetkisine sahip kullanıcılar
 * bir etkinliğin tüm katılımcılarına özel bildirim gönderebilir.
 */
require_once 'bootstrap.php';

header('Content-Type: application/json');

// ✅ Session kontrolü (bootstrap.php already starts session)
if (!isset($_SESSION['user_id'])) {
    json_err('Unauthorized', 401);
}

$user_id = $_SESSION['user_id'];

// ✅ JSON input al
$input = json_decode(file_get_contents('php://input'), true);
$event_id = $input['event_id'] ?? null;
$message = $input['message'] ?? '';
$title = $input['title'] ?? null;

if (!$event_id || empty($message)) {
    json_err('Event ID and message are required');
}

// ✅ Eğer title belirtilmemişse, etkinlik adından oluştur
if (empty($title)) {
    $title = 'Yeni Bildirim';
}

try {
    $pdo = get_pdo();
    
    // ✅ Etkinlik bilgilerini al ve yetki kontrolü yap
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.baslik as event_title,
            d.moderator_id as olusturan_id,
            dk.rol,
            dk.yetkiler
        FROM dugunler d
        JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id
        WHERE d.id = ? AND dk.kullanici_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        json_err('Event not found or access denied', 404);
    }
    
    // ✅ Yetki kontrolü: Etkinlik sahibi, moderator veya admin olmalı
    $is_owner = ($event['olusturan_id'] == $user_id);
    $is_moderator = ($event['rol'] === 'moderator');
    $is_admin = ($event['rol'] === 'admin');
    
    // ✅ Ek yetki kontrolü: bildirim_gonderebilir
    $permissions = $event['yetkiler'] ? json_decode($event['yetkiler'], true) : [];
    $can_send_notification = false;
    
    if (is_array($permissions)) {
        // Array formatı: ["medya_paylasabilir", "bildirim_gonderebilir"]
        if (isset($permissions[0])) {
            $can_send_notification = in_array('bildirim_gonderebilir', $permissions);
        } 
        // Object formatı: {"bildirim_gonderebilir": true}
        else {
            $can_send_notification = isset($permissions['bildirim_gonderebilir']) && $permissions['bildirim_gonderebilir'] === true;
        }
    }
    
    if (!$is_owner && !$is_moderator && !$is_admin && !$can_send_notification) {
        json_err('You do not have permission to send notifications for this event', 403);
    }
    
    // ✅ Etkinlikteki tüm katılımcıları al (gönderen hariç)
    $stmt = $pdo->prepare("
        SELECT DISTINCT dk.kullanici_id
        FROM dugun_katilimcilar dk
        WHERE dk.dugun_id = ? AND dk.kullanici_id != ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($participants)) {
        json_err('No participants found in this event');
    }
    
    // ✅ Her katılımcıya bildirim kaydet
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, sender_id, event_id, type, message, created_at)
        VALUES (?, ?, ?, 'custom', ?, NOW())
    ");
    
    $notification_count = 0;
    foreach ($participants as $participant_id) {
        $stmt->execute([$participant_id, $user_id, $event_id, $message]);
        $notification_count++;
    }
    
    // ✅ Log this action
    $log_details = json_encode([
        'event_id' => (int)$event_id,
        'event_title' => $event['event_title'],
        'message' => $message,
        'recipient_count' => $notification_count,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO user_logs (
            user_id, action, details, ip_address, user_agent, created_at
        ) VALUES (?, 'send_custom_notification', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $log_details,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // ✅ FCM ile push notification gönder
    require_once 'notification_service.php';
    
    // Bildirim formatı: Başlık = "Etkinlik Adı", Mesaj = "Durum Bildirimi\n[Kullanıcı mesajı]"
    $notification_message = "Durum Bildirimi\n\n" . $message;
    
    $fcm_result = sendNotification(
        $participants, 
        $title,  // "Etkinlik Adı Etkinliği"
        $notification_message,  // "Durum Bildirimi\n\n[Mesaj]"
        [
            'type' => 'custom',
            'event_id' => (string)$event_id,
            'sender_id' => (string)$user_id,
            'timestamp' => (string)time()
        ]
    );
    
    json_ok([
        'message' => 'Notification sent successfully',
        'recipient_count' => $notification_count,
        'fcm_success_count' => $fcm_result['success_count'] ?? 0,
        'fcm_failed_count' => count($fcm_result['failures'] ?? [])
    ]);

} catch (PDOException $e) {
    error_log("Send Custom Notification Error: " . $e->getMessage());
    json_err('Database error');
}

