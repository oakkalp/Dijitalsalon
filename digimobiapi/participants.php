<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_err(405, 'Method not allowed');
}

$user_id = require_auth();

// Get event_id from query parameters
$event_id = $_GET['event_id'] ?? null;
if (!$event_id) {
    json_err(400, 'Event ID is required');
}

try {
    // Check if user is a participant of the event
    $stmt = $pdo->prepare("
        SELECT dk.rol 
        FROM dugun_katilimcilar dk 
        WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $user_role = $stmt->fetchColumn();
    
    // ✅ Tüm katılımcılar katılımcıları görebilir, sadece yönetme yetkisi kontrol edilir
    if (!$user_role) {
        json_err(403, 'Bu etkinliğe katılımcı değilsiniz');
    }
    
    // Get all participants for the event
    $stmt = $pdo->prepare("
        SELECT 
            dk.kullanici_id,
            k.ad,
            k.soyad,
            k.email,
            k.profil_fotografi,
            dk.rol,
            dk.katilim_tarihi,
            dk.durum,
            dk.yetkiler,
            (SELECT COUNT(*) FROM medyalar m WHERE m.kullanici_id = dk.kullanici_id AND m.dugun_id = ?) as medya_sayisi,
            (SELECT COUNT(*) FROM hikayeler h WHERE h.kullanici_id = dk.kullanici_id AND h.dugun_id = ?) as hikaye_sayisi
        FROM dugun_katilimcilar dk
        JOIN kullanicilar k ON dk.kullanici_id = k.id
        WHERE dk.dugun_id = ?
        ORDER BY 
            CASE dk.rol 
                WHEN 'admin' THEN 1
                WHEN 'moderator' THEN 2
                WHEN 'yetkili_kullanici' THEN 3
                ELSE 4
            END,
            dk.katilim_tarihi ASC
    ");
    $stmt->execute([$event_id, $event_id, $event_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format participants for mobile app
    $formatted_participants = [];
    foreach ($participants as $participant) {
        $formatted_participants[] = [
            'id' => (int)$participant['kullanici_id'],
            'name' => trim($participant['ad'] . ' ' . $participant['soyad']),
            'email' => $participant['email'],
            'avatar' => $participant['profil_fotografi'] ? 'http://192.168.1.137/dijitalsalon/' . $participant['profil_fotografi'] : null,
            'role' => $participant['rol'] ?: 'kullanici',
            'join_date' => $participant['katilim_tarihi'],
            'status' => $participant['durum'] ?: 'aktif',
            'permissions' => $participant['yetkiler'] ? json_decode($participant['yetkiler'], true) : [], // ✅ Array formatında gönder
            'media_count' => (int)$participant['medya_sayisi'],
            'story_count' => (int)$participant['hikaye_sayisi'],
        ];
    }
    
    json_success([
        'participants' => $formatted_participants,
        'total' => count($formatted_participants),
    ]);
    
} catch (Exception $e) {
    error_log("Participants API Error: " . $e->getMessage());
    error_log("Participants API Error Trace: " . $e->getTraceAsString());
    json_err(500, 'Failed to fetch participants: ' . $e->getMessage());
}
?>
