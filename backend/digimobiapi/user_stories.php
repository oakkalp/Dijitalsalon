<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $target_user_id = $_GET['user_id'] ?? null;
    $event_id = $_GET['event_id'] ?? null;

    if (empty($target_user_id) || empty($event_id)) {
        json_err(400, 'User ID and Event ID are required.');
    }

    try {
        // Check if user is participant of this event
        $stmt = $pdo->prepare("
            SELECT dk.id 
            FROM dugun_katilimcilar dk 
            WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$event_id, $user_id]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$participant) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not a participant of this event.']);
            exit;
        }
        
        // Get all stories for specific user
        $stmt = $pdo->prepare("
            SELECT 
                m.id, m.dosya_yolu, m.tur, m.created_at, m.hikaye_bitis_tarihi,
                k.ad as user_name,
                k.soyad as user_surname,
                k.profil_fotografi as user_profile
            FROM medyalar m
            JOIN kullanicilar k ON m.kullanici_id = k.id
            WHERE m.dugun_id = ? AND m.kullanici_id = ? AND m.tur = 'hikaye'
            AND (m.hikaye_bitis_tarihi IS NULL OR m.hikaye_bitis_tarihi > NOW())
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$event_id, $target_user_id]);
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format stories for mobile app
        $formatted_stories = [];
        foreach ($stories as $story) {
            $formatted_stories[] = [
                'id' => (int)$story['id'],
                'media_url' => $story['dosya_yolu'] ? 'https://dijitalsalon.cagapps.app/' . $story['dosya_yolu'] : null,
                'type' => $story['tur'], // 'foto' or 'video'
                'created_at' => $story['created_at'],
                'user_name' => $story['user_name'] . ' ' . $story['user_surname'],
                'user_avatar' => $story['user_profile'] ? 'https://dijitalsalon.cagapps.app/' . $story['user_profile'] : null,
            ];
        }
        
        echo json_encode([
            'success' => true,
            'stories' => $formatted_stories
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

