<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qr_code = $_POST['qr_code'] ?? '';
    
    if (empty($qr_code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'QR code is required.']);
        exit;
    }
    
    try {
        // Check if QR code exists in dugunler table
        $stmt = $pdo->prepare("
            SELECT d.*
            FROM dugunler d
            WHERE d.qr_kod = ?
        ");
        $stmt->execute([$qr_code]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            exit;
        }
        
        // Check if user is already a participant
        $stmt = $pdo->prepare("
            SELECT id, rol 
            FROM dugun_katilimcilar 
            WHERE dugun_id = ? AND kullanici_id = ?
        ");
        $stmt->execute([$event['id'], $user_id]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($participant) {
            // User is already a participant
            echo json_encode([
                'success' => true,
                'message' => 'You are already a participant of this event.',
                'event' => [
                    'id' => (int)$event['id'],
                    'title' => $event['baslik'],
                    'description' => $event['aciklama'],
                    'date' => $event['tarih'] ?? '',
                    'location' => $event['konum'] ?? '',
                    'participant_role' => $participant['rol'],
                ]
            ]);
            exit;
        }
        
        // Add user as participant
        $stmt = $pdo->prepare("
            INSERT INTO dugun_katilimcilar (dugun_id, kullanici_id, katilim_tarihi, rol)
            VALUES (?, ?, NOW(), 'kullanici')
        ");
        $stmt->execute([$event['id'], $user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully joined the event!',
            'event' => [
                'id' => (int)$event['id'],
                'title' => $event['baslik'],
                'description' => $event['aciklama'],
                'date' => $event['tarih'] ?? '',
                'location' => $event['konum'] ?? '',
                'participant_role' => 'kullanici',
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
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
