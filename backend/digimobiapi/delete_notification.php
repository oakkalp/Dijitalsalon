<?php
require_once 'bootstrap.php';

header('Content-Type: application/json');

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['notification_id'] ?? null;
$media_id = $input['media_id'] ?? null;
$event_id = $input['event_id'] ?? null;
$type = $input['type'] ?? null; // 'like' veya 'comment'

try {
    $pdo = get_pdo();
    
    // ✅ Eğer notification_id varsa, tek bildirimi sil (eski format)
    if ($notification_id) {
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notification_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            json_ok(['message' => 'Bildirim silindi', 'deleted_count' => $stmt->rowCount()]);
        } else {
            json_err(404, 'Bildirim bulunamadı veya yetkiniz yok');
        }
    }
    // ✅ Gruplu bildirim silme: media_id + event_id + type ile tüm ilgili bildirimleri sil
    else if ($media_id && $event_id && $type) {
        // ✅ data JSON'ında media_id, event_id ve type'a göre sil
        // JSON_EXTRACT string döndürebilir, bu yüzden CAST kullanıyoruz
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE user_id = ? 
            AND type = ?
            AND (
                CAST(JSON_EXTRACT(data, '$.media_id') AS UNSIGNED) = ?
                OR JSON_UNQUOTE(JSON_EXTRACT(data, '$.media_id')) = ?
            )
            AND (
                CAST(JSON_EXTRACT(data, '$.event_id') AS UNSIGNED) = ?
                OR JSON_UNQUOTE(JSON_EXTRACT(data, '$.event_id')) = ?
            )
        ");
        $stmt->execute([
            $user_id, 
            $type, 
            $media_id, 
            (string)$media_id, 
            $event_id, 
            (string)$event_id
        ]);
        
        $deleted_count = $stmt->rowCount();
        
        if ($deleted_count > 0) {
            json_ok(['message' => 'Bildirimler silindi', 'deleted_count' => $deleted_count]);
        } else {
            json_err(404, 'Bildirim bulunamadı veya yetkiniz yok');
        }
    } else {
        json_err(400, 'Notification ID veya (media_id + event_id + type) gereklidir');
    }
    
} catch (PDOException $e) {
    error_log("Delete Notification Error: " . $e->getMessage());
    json_err(500, 'Database error');
}
?>

