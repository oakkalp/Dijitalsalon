<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'] ?? null;
$description = $_POST['description'] ?? '';

if (empty($event_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Event ID is required.']);
    exit;
}

try {
    // Check if user is a participant of the event with package info
    $stmt = $pdo->prepare("
        SELECT 
            dk.rol, 
            dk.yetkiler,
            d.dugun_tarihi,
            p.ucretsiz_erisim_gun
        FROM dugun_katilimcilar dk
        JOIN dugunler d ON dk.dugun_id = d.id
        LEFT JOIN paketler p ON d.paket_id = p.id
        WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not a participant of this event.']);
        exit;
    }

    // ✅ Yetki kontrolü için tarih hesaplamaları
    $event_date = new DateTime($participant['dugun_tarihi']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $event_date->setTime(0, 0, 0);
    
    $free_access_days = (int)($participant['ucretsiz_erisim_gun'] ?? 7);
    $access_end_date = clone $event_date;
    $access_end_date->add(new DateInterval("P{$free_access_days}D"));
    
    $is_before_event = $today < $event_date;
    $is_after_access = $today > $access_end_date;
    
    // ✅ Rol kontrolü
    $user_role = $participant['rol'] ?: 'kullanici';
    $is_moderator = $user_role === 'moderator';
    $is_authorized = $user_role === 'yetkili_kullanici';
    
    // ✅ Yetki kontrolü
    if ($is_moderator) {
        // Moderator: Her zaman medya paylaşabilir
        $can_share_media = true;
    } elseif ($is_authorized) {
        // Yetkili kullanıcı: JSON yetkiler + tarih kontrolü
        $permissions = $participant['yetkiler'] ? json_decode($participant['yetkiler'], true) : [];
        $can_share_media = ($permissions['medya_paylasabilir'] ?? false) && !$is_before_event && !$is_after_access;
    } else {
        // Normal kullanıcı: Sadece aktif dönemde
        $can_share_media = !$is_before_event && !$is_after_access;
    }
    
    if (!$can_share_media) {
        $error_message = 'You do not have permission to share media in this event.';
        if ($is_before_event) {
            $error_message = 'Event has not started yet. You cannot share media before the event date.';
        } elseif ($is_after_access) {
            $error_message = "Free access period has ended. You cannot share media after {$free_access_days} days from the event date.";
        }
        
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $error_message]);
        exit;
    }

    // Check if file was uploaded
    if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
        exit;
    }

    $file = $_FILES['media_file'];

    // Debug: Log file info
    error_log("Media Upload - File Type: " . $file['type']);
    error_log("Media Upload - File Name: " . $file['name']);
    error_log("Media Upload - File Size: " . $file['size']);

    // Validate file type (more flexible)
    $allowed_types = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/quicktime', 'video/avi', 'video/mov'
    ];

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi'];

    if (!in_array($file['type'], $allowed_types) && !in_array($file_extension, $allowed_extensions)) {
        error_log("Media Upload - Invalid file type: " . $file['type'] . " or extension: " . $file_extension);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images and videos are allowed.']);
        exit;
    }

    // Validate file size (max 50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 50MB.']);
        exit;
    }

    $upload_dir = '../uploads/media/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = 'media_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $file_path_db = 'uploads/media/' . $file_name;
        $media_type = (strpos($file['type'], 'image') !== false) ? 'foto' : 'video';

        $stmt = $pdo->prepare("
            INSERT INTO medyalar (dugun_id, kullanici_id, dosya_yolu, tur, aciklama, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$event_id, $user_id, $file_path_db, $media_type, $description]);
        $media_id = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'message' => 'Media uploaded successfully.', 'media_id' => $media_id, 'file_path' => $file_path_db]);
    } else {
        error_log("Media Upload - Failed to move uploaded file: " . $file['tmp_name'] . " to " . $target_file);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to upload media.']);
    }

} catch (PDOException $e) {
    error_log("Database error adding media: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
