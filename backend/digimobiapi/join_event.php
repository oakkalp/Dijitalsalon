<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/cache_invalidation.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_POST['event_id'] ?? '';
    
    if (empty($event_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Event ID is required.']);
        exit;
    }
    
    try {
        // Check if event exists by ID or QR code
        $stmt = $pdo->prepare("
            SELECT id, baslik, qr_kod 
            FROM dugunler 
            WHERE id = ? OR qr_kod = ?
        ");
        $stmt->execute([$event_id, $event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Event not found.']);
            exit;
        }
        
        // Use the actual event ID for further operations
        $actual_event_id = $event['id'];
        
        // Check if user is already a participant
        $stmt = $pdo->prepare("SELECT id, durum FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$actual_event_id, $user_id]);
        $existing_participant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_participant) {
            // ✅ Eğer kullanıcı yasaklanmışsa katılımı engelle
            if ($existing_participant['durum'] === 'yasakli') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Bu etkinlikten yasaklandınız.']);
                exit;
            }
            
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'You are already a participant in this event.']);
            exit;
        }
        
        // ✅ Default yetkiler: kullanıcı etkinliğe katıldığında bu yetkiler otomatik verilir
        $default_permissions = json_encode([
            'hikaye_paylasabilir' => true,
            'medya_paylasabilir' => true,
            'yorum_yapabilir' => true,
        ]);
        
        // Add user to event with default permissions
        $stmt = $pdo->prepare("
            INSERT INTO dugun_katilimcilar (dugun_id, kullanici_id, rol, katilim_tarihi, yetkiler) 
            VALUES (?, ?, 'kullanici', NOW(), ?)
        ");
        $stmt->execute([$actual_event_id, $user_id, $default_permissions]);
        
        // ✅ Cache'i temizle - Kullanıcının events cache'ini sil (real-time güncelleme için)
        try {
            require_once __DIR__ . '/cache_helper.php';
            // Events cache key'i: "SELECT * FROM dugun_katilimcilar WHERE kullanici_id = ? AND durum = 'aktif'"
            $cache_key_query = "SELECT * FROM dugun_katilimcilar WHERE kullanici_id = ? AND durum = 'aktif'";
            $cache_key = md5($cache_key_query . serialize([$user_id]));
            $cache_file = __DIR__ . '/cache/query_cache/' . $cache_key . '.cache';
            
            if (file_exists($cache_file)) {
                @unlink($cache_file);
                error_log("Join Event - Cleared events cache for user_id: $user_id (cache file: $cache_file)");
            } else {
                // Cache dosyası yoksa, tüm cache'i temizle (güvenli tarafta olmak için)
                QueryCache::clear();
                error_log("Join Event - Cache file not found, cleared all cache for user_id: $user_id");
            }
        } catch (Exception $e) {
            // Cache temizleme hatası ana işlemi engellemesin
            error_log("Join Event - Cache clear error: " . $e->getMessage());
        }
        
        // ✅ Log event join activity
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $device_info = $_POST['device_info'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $log_details = json_encode([
                'event_id' => (int)$actual_event_id,
                'event_title' => $event['baslik'],
                'qr_code' => $event['qr_kod'],
                'join_method' => 'manual',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO user_logs (
                    user_id, action, details, ip_address, device_info, user_agent, created_at
                ) VALUES (?, 'event_join', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $log_details, $ip_address, $device_info, $user_agent]);
        } catch (Exception $e) {
            // Log hatası ana işlemi engellemesin
            error_log("Event join log error: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully joined event: ' . $event['baslik'],
            'event_id' => (int)$actual_event_id,
            'event_title' => $event['baslik'],
            'qr_code' => $event['qr_kod']
        ]);
        exit;
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
?>
