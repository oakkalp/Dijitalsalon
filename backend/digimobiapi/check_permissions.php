<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = $_GET['event_id'] ?? null;

if (empty($event_id)) {
    http_response_code(400);
    header('Content-Type: application/json');
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
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'user_id' => $user_id,
            'event_id' => $event_id,
            'is_participant' => false,
            'error' => 'You are not a participant of this event.'
        ]);
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
    
    // ✅ Rol kontrolü (case-insensitive)
    $user_role_raw = $participant['rol'] ?: 'kullanici';
    $user_role = strtolower(trim($user_role_raw)); // ✅ Case-insensitive karşılaştırma için
    $is_moderator = $user_role === 'moderator';
    $is_admin = $user_role === 'admin';
    $is_authorized = $user_role === 'yetkili_kullanici';
    
    // ✅ Yetki kontrolü
    $permissions = $participant['yetkiler'] ? json_decode($participant['yetkiler'], true) : [];
    
    // ✅ Yetkiler array olarak geliyorsa (["medya_paylasabilir", ...]) in_array kullan
    // ✅ Ya da object olarak geliyorsa ({"medya_paylasabilir": true, ...}) key kontrolü yap
    if (is_array($permissions) && isset($permissions[0])) {
        // Array formatı: ["medya_paylasabilir", "hikaye_paylasabilir"]
        $has_media_permission = in_array('medya_paylasabilir', $permissions);
    } else {
        // Object formatı: {"medya_paylasabilir": true}
        $has_media_permission = ($permissions['medya_paylasabilir'] ?? false);
    }
    
    if ($is_moderator || $is_admin) {
        $can_share_media = true;
        $reason = 'Moderator/Admin: Her zaman medya paylaşabilir';
    } elseif ($is_authorized) {
        $can_share_media = $has_media_permission && !$is_before_event && !$is_after_access;
        if (!$has_media_permission) {
            $reason = 'Yetkili kullanıcı ama medya_paylasabilir yetkisi yok';
        } elseif ($is_before_event) {
            $reason = 'Etkinlik henüz başlamadı';
        } elseif ($is_after_access) {
            $reason = "Ücretsiz erişim süresi doldu ({$free_access_days} gün)";
        } else {
            $reason = 'Yetkili kullanıcı - medya paylaşabilir';
        }
    } else {
        $can_share_media = !$is_before_event && !$is_after_access;
        if ($is_before_event) {
            $reason = 'Etkinlik henüz başlamadı';
        } elseif ($is_after_access) {
            $reason = "Ücretsiz erişim süresi doldu ({$free_access_days} gün)";
        } else {
            $reason = 'Normal kullanıcı - aktif dönemde';
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'event_id' => $event_id,
        'is_participant' => true,
        'role' => $user_role,
        'permissions' => $permissions,
        'permission_details' => [
            'has_medya_paylasabilir' => $has_media_permission,
            'is_moderator' => $is_moderator,
            'is_admin' => $is_admin,
            'is_authorized' => $is_authorized,
        ],
        'date_checks' => [
            'event_date' => $participant['dugun_tarihi'],
            'today' => $today->format('Y-m-d H:i:s'),
            'access_end_date' => $access_end_date->format('Y-m-d H:i:s'),
            'free_access_days' => $free_access_days,
            'is_before_event' => $is_before_event,
            'is_after_access' => $is_after_access,
        ],
        'can_share_media' => $can_share_media,
        'reason' => $reason,
    ]);
    
} catch (Exception $e) {
    error_log("Check Permissions Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

