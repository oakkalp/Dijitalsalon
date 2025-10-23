<?php
require_once __DIR__ . '/bootstrap.php';

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
        
        // Add user to event
        $stmt = $pdo->prepare("
            INSERT INTO dugun_katilimcilar (dugun_id, kullanici_id, rol, katilim_tarihi) 
            VALUES (?, ?, 'kullanici', NOW())
        ");
        $stmt->execute([$actual_event_id, $user_id]);
        
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
